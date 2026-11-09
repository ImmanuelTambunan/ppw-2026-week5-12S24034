<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PeminjamanResource;
use App\Models\Buku;
use App\Models\Peminjaman;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeminjamanApiController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/peminjamans (Wajib Bearer Token)
     * Menampilkan daftar transaksi peminjaman terpaginasi dengan filter status & NIM
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);

        $paginator = Peminjaman::query()
            ->with('buku')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('nim'), fn ($q, $nim) => $q->where('nim_peminjam', $nim))
            ->when($request->query('buku_id'), fn ($q, $bukuId) => $q->where('buku_id', (int) $bukuId))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return $this->successResponse(
            data: PeminjamanResource::collection($paginator->items()),
            message: 'Daftar riwayat peminjaman buku berhasil diambil.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total_items' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
                'links' => [
                    'first' => $paginator->url(1),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                    'last' => $paginator->url($paginator->lastPage()),
                ],
            ]
        );
    }

    /**
     * GET /api/v1/peminjamans/{id} (Wajib Bearer Token)
     * Menampilkan detail satu transaksi peminjaman
     */
    public function show(string $id): JsonResponse
    {
        $peminjaman = Peminjaman::with('buku')->find($id);

        if (!$peminjaman) {
            return $this->problemResponse(
                title: 'Resource Not Found',
                detail: "Transaksi peminjaman dengan ID '{$id}' tidak ditemukan.",
                status: 404,
                errorSlug: 'loan-not-found'
            );
        }

        return $this->successResponse(
            data: new PeminjamanResource($peminjaman),
            message: 'Detail transaksi peminjaman berhasil ditemukan.'
        );
    }

    /**
     * POST /api/v1/peminjamans (Wajib Bearer Token)
     * Mencatat peminjaman buku baru dengan jaminan Transaksi ACID + lockForUpdate()
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'buku_id' => 'required|integer|exists:bukus,id',
            'nim_peminjam' => 'required|string|max:20',
            'nama_peminjam' => 'required|string|max:150',
            'durasi_hari' => 'nullable|integer|min:1|max:30',
        ]);

        return DB::transaction(function () use ($validated) {
            // 1. Kunci baris buku secara eksklusif (SELECT ... FOR UPDATE) untuk menangkal Race Condition
            $buku = Buku::where('id', $validated['buku_id'])->lockForUpdate()->first();

            if (!$buku) {
                return $this->problemResponse(
                    title: 'Resource Not Found',
                    detail: 'Buku yang diminta tidak ditemukan.',
                    status: 404,
                    errorSlug: 'book-not-found'
                );
            }

            // 2. Cek apakah mahasiswa masih meminjam buku yang sama dan belum dikembalikan -> 409 Conflict
            $alreadyBorrowing = Peminjaman::where('buku_id', $buku->id)
                ->where('nim_peminjam', $validated['nim_peminjam'])
                ->where('status', 'dipinjam')
                ->exists();

            if ($alreadyBorrowing) {
                return $this->problemResponse(
                    title: 'Duplicate Active Loan Conflict',
                    detail: "Mahasiswa dengan NIM '{$validated['nim_peminjam']}' masih meminjam buku '{$buku->judul}' dan belum dikembalikan.",
                    status: 409,
                    errorSlug: 'duplicate-active-loan'
                );
            }

            // 3. Cek sisa stok buku -> 422 Unprocessable Content jika habis
            if ($buku->stok < 1) {
                return $this->problemResponse(
                    title: 'Book Out of Stock',
                    detail: "Stok buku '{$buku->judul}' sedang habis (0 tersisa).",
                    status: 422,
                    errorSlug: 'book-out-of-stock'
                );
            }

            // 4. Kurangi stok buku (-1) dan catat peminjaman secara atomik
            $buku->decrement('stok');

            $durasi = $validated['durasi_hari'] ?? 7;
            $peminjaman = Peminjaman::create([
                'buku_id' => $buku->id,
                'nim_peminjam' => strtoupper(trim($validated['nim_peminjam'])),
                'nama_peminjam' => trim($validated['nama_peminjam']),
                'tanggal_pinjam' => now()->toDateString(),
                'durasi_hari' => $durasi,
                'status' => 'dipinjam',
            ]);

            return $this->createdResponse(
                data: new PeminjamanResource($peminjaman->load('buku')),
                locationUrl: url("/api/v1/peminjamans/{$peminjaman->id}"),
                message: "Berhasil meminjam buku '{$buku->judul}'. Sisa stok kini: {$buku->fresh()->stok} eksemplar."
            );
        });
    }

    /**
     * PATCH /api/v1/peminjamans/{id}/kembalikan (Wajib Bearer Token)
     * Mengembalikan buku di dalam DB::transaction() + lockForUpdate() dan menambah stok (+1)
     */
    public function kembalikan(string $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $peminjaman = Peminjaman::find($id);

            if (!$peminjaman) {
                return $this->problemResponse(
                    title: 'Resource Not Found',
                    detail: "Transaksi peminjaman dengan ID '{$id}' tidak ditemukan.",
                    status: 404,
                    errorSlug: 'loan-not-found'
                );
            }

            // Jika buku sudah berstatus 'dikembalikan', tolak dengan 409 Conflict
            if ($peminjaman->status === 'dikembalikan') {
                return $this->problemResponse(
                    title: 'Loan Already Returned',
                    detail: "Peminjaman dengan ID '{$id}' sudah dikembalikan sebelumnya pada {$peminjaman->tanggal_kembali}.",
                    status: 409,
                    errorSlug: 'already-returned'
                );
            }

            // Kunci baris buku secara eksklusif
            $buku = Buku::where('id', $peminjaman->buku_id)->lockForUpdate()->first();

            // Perbarui status peminjaman
            $peminjaman->update([
                'status' => 'dikembalikan',
                'tanggal_kembali' => now()->toDateString(),
            ]);

            // Kembalikan stok buku (+1)
            if ($buku) {
                $buku->increment('stok');
            }

            return $this->successResponse(
                data: new PeminjamanResource($peminjaman->load('buku')),
                message: "Buku '{$buku?->judul}' berhasil dikembalikan. Stok kini: {$buku?->fresh()->stok} eksemplar."
            );
        });
    }
}
