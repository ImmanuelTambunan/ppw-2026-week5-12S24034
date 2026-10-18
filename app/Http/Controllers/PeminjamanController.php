<?php

namespace App\Http\Controllers;

use App\Models\Buku;
use App\Models\Peminjaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use RuntimeException;

class PeminjamanController extends Controller
{
    public function index()
    {
        $peminjamans = Peminjaman::with('buku')->latest()->paginate(10);
        return view('peminjaman.index', compact('peminjamans'));
    }

    public function create()
    {
        // Hanya tampilkan buku yang stoknya lebih dari 0 untuk opsi form
        $bukus = Buku::orderBy('judul')->get();
        return view('peminjaman.create', compact('bukus'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'buku_id'        => 'required|exists:bukus,id',
            'nim_peminjam'   => 'required|string|min:6|max:15',
            'nama_peminjam'  => 'required|string|min:3|max:150',
            'tanggal_pinjam' => 'required|date',
            'durasi_hari'    => 'required|integer|min:1|max:14',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                // 1. Pessimistic Locking: Kunci baris buku yang akan dipinjam
                $buku = Buku::where('id', $validated['buku_id'])->lockForUpdate()->firstOrFail();

                // 2. Pemeriksaan Stok Tersedia
                if ($buku->stok < 1) {
                    throw new RuntimeException("Stok buku '{$buku->judul}' habis! Transaksi dibatalkan.");
                }

                // 3. Validasi: Cegah NIM yang sama meminjam buku yang sama sebelum dikembalikan
                $sudahPinjam = Peminjaman::where('buku_id', $buku->id)
                    ->where('nim_peminjam', strtoupper(trim($validated['nim_peminjam'])))
                    ->where('status', 'dipinjam')
                    ->exists();

                if ($sudahPinjam) {
                    throw new RuntimeException("Mahasiswa dengan NIM {$validated['nim_peminjam']} masih meminjam buku ini.");
                }

                // 4. Hitung tanggal jatuh tempo & generate kode pinjam unik
                $tglPinjam = \Carbon\Carbon::parse($validated['tanggal_pinjam']);
                $tglJatuhTempo = $tglPinjam->copy()->addDays((int) $validated['durasi_hari']);
                $kodePinjam = 'PJ-' . strtoupper(uniqid());

                // 5. Potong stok buku secara atomik
                $buku->decrement('stok');

                // 6. Simpan transaksi peminjaman
                Peminjaman::create([
                    'kode_pinjam'         => $kodePinjam,
                    'buku_id'             => $buku->id,
                    'nim_peminjam'        => strtoupper(trim($validated['nim_peminjam'])),
                    'nama_peminjam'       => $validated['nama_peminjam'],
                    'tanggal_pinjam'      => $tglPinjam->toDateString(),
                    'tanggal_jatuh_tempo' => $tglJatuhTempo->toDateString(),
                    'status'              => 'dipinjam',
                ]);

                // Simpan state peminjam terakhir ke sesi
                session(['last_borrower_nim' => $validated['nim_peminjam']]);
            }, 3);

            return redirect()->route('peminjaman.index')->with('success', 'Buku berhasil dipinjam secara atomik.');
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (QueryException $e) {
            Log::error('[DB TRANSACTION FAILED]: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Terjadi kesalahan basis data saat memproses peminjaman.');
        }
    }

    public function kembalikan(Peminjaman $peminjaman)
    {
        if ($peminjaman->status === 'dikembalikan') {
            return back()->with('error', 'Buku ini sudah dikembalikan sebelumnya.');
        }

        try {
            DB::transaction(function () use ($peminjaman) {
                // 1. Kunci baris peminjaman dan baris buku terkait
                $trx = Peminjaman::where('id', $peminjaman->id)->lockForUpdate()->firstOrFail();
                $buku = Buku::where('id', $trx->buku_id)->lockForUpdate()->firstOrFail();

                // 2. Perbarui status dan catat tanggal kembali hari ini
                $trx->update([
                    'status'          => 'dikembalikan',
                    'tanggal_kembali' => now()->toDateString(),
                ]);

                // 3. Kembalikan stok buku
                $buku->increment('stok');
            }, 3);

            return redirect()->route('peminjaman.index')->with('success', 'Buku berhasil dikembalikan dan stok diperbarui.');
        } catch (QueryException $e) {
            Log::error('[DB RETURN TRANSACTION FAILED]: ' . $e->getMessage());
            return back()->with('error', 'Gagal memproses pengembalian buku.');
        } catch (\Exception $e) {
            Log::error('[DB RETURN UNEXPECTED ERROR]: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan sistem saat memproses pengembalian buku.');
        }
    }

    /**
     * Ekspor Laporan Sirkulasi Peminjaman ke CSV menggunakan Fluent Query Builder.
     */
    public function exportCsv()
    {
        $hasDenda = Schema::hasColumn('peminjamans', 'denda');

        $peminjamans = DB::table('peminjamans')
            ->join('bukus', 'peminjamans.buku_id', '=', 'bukus.id')
            ->select(
                'peminjamans.kode_pinjam',
                'bukus.judul as judul_buku',
                'bukus.isbn',
                'peminjamans.nim_peminjam',
                'peminjamans.nama_peminjam',
                'peminjamans.tanggal_pinjam',
                'peminjamans.tanggal_jatuh_tempo',
                'peminjamans.tanggal_kembali',
                'peminjamans.status',
                $hasDenda ? 'peminjamans.denda' : DB::raw('0 as denda')
            )
            ->orderBy('peminjamans.created_at', 'desc')
            ->get();

        $fileName = 'laporan-sirkulasi-sipus-del-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->streamDownload(function () use ($peminjamans) {
            $handle = fopen('php://output', 'w');

            // Tambahkan UTF-8 BOM untuk kompatibilitas Microsoft Excel
            fputs($handle, "\xEF\xBB\xBF");

            // Tulis baris header kolom CSV
            fputcsv($handle, [
                'Kode Pinjam',
                'Judul Buku',
                'ISBN',
                'NIM',
                'Nama',
                'Tgl Pinjam',
                'Jatuh Tempo',
                'Tgl Kembali',
                'Status',
                'Denda',
            ]);

            // Tulis seluruh baris rekaman data sirkulasi
            foreach ($peminjamans as $row) {
                fputcsv($handle, [
                    $row->kode_pinjam,
                    $row->judul_buku,
                    $row->isbn,
                    $row->nim_peminjam,
                    $row->nama_peminjam,
                    $row->tanggal_pinjam,
                    $row->tanggal_jatuh_tempo,
                    $row->tanggal_kembali ?? '-',
                    ucfirst($row->status),
                    $row->denda ?? 0,
                ]);
            }

            fclose($handle);
        }, $fileName, $headers);
    }
}