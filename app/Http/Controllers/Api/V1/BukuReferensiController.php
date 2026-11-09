<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Buku;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class BukuReferensiController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/bukus/{id}/referensi-eksternal (Publik)
     * Laravel bertindak sebagai REST SERVICE CONSUMER memanggil OpenLibrary REST API
     */
    public function show(string $id): JsonResponse
    {
        $buku = Buku::where('id', $id)
            ->orWhere('isbn', $id)
            ->first();

        if (!$buku) {
            return $this->problemResponse(
                title: 'Resource Not Found',
                detail: "Buku dengan pengenal '{$id}' tidak ditemukan di basis data.",
                status: 404,
                errorSlug: 'book-not-found'
            );
        }

        $cleanIsbn = preg_replace('/[^0-9X]/i', '', $buku->isbn);
        $bibkey = "ISBN:{$cleanIsbn}";

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get('https://openlibrary.org/api/books', [
                    'bibkeys' => $bibkey,
                    'format' => 'json',
                    'jscmd' => 'data',
                ]);
        } catch (Throwable $e) {
            return $this->problemResponse(
                title: 'Bad Gateway',
                detail: 'Gagal terhubung dengan layanan eksternal OpenLibrary (Koneksi timeout / jaringan bermasalah).',
                status: 502,
                errorSlug: 'external-service-timeout'
            );
        }

        if ($response->failed()) {
            return $this->problemResponse(
                title: 'Bad Gateway',
                detail: 'Layanan eksternal OpenLibrary mengembalikan respon kesalahan HTTP.',
                status: 502,
                errorSlug: 'external-api-failed'
            );
        }

        $data = $response->json();

        if (empty($data) || !isset($data[$bibkey])) {
            return $this->successResponse(
                data: [
                    'buku_id' => $buku->id,
                    'isbn' => $buku->isbn,
                    'judul_lokal' => $buku->judul,
                    'status_eksternal' => 'NOT_FOUND',
                    'sumber' => 'OpenLibrary REST API',
                    'metadata_eksternal' => null,
                ],
                message: "Metadata eksternal untuk ISBN '{$buku->isbn}' tidak ditemukan pada basis data OpenLibrary."
            );
        }

        $bookMeta = $data[$bibkey];
        $authors = collect($bookMeta['authors'] ?? [])->pluck('name')->values()->toArray();
        $publishers = collect($bookMeta['publishers'] ?? [])->pluck('name')->values()->toArray();
        $cover = $bookMeta['cover']['large'] ?? $bookMeta['cover']['medium'] ?? $bookMeta['cover']['small'] ?? null;

        return $this->successResponse(
            data: [
                'buku_id' => $buku->id,
                'isbn' => $buku->isbn,
                'judul_lokal' => $buku->judul,
                'status_eksternal' => 'FOUND',
                'sumber' => 'OpenLibrary REST API',
                'metadata_eksternal' => [
                    'title' => $bookMeta['title'] ?? $buku->judul,
                    'subtitle' => $bookMeta['subtitle'] ?? null,
                    'authors' => $authors,
                    'publishers' => $publishers,
                    'publish_date' => $bookMeta['publish_date'] ?? null,
                    'number_of_pages' => $bookMeta['number_of_pages'] ?? null,
                    'cover_url' => $cover,
                    'preview_url' => $bookMeta['url'] ?? null,
                ],
            ],
            message: 'Data referensi metadata eksternal berhasil diambil dari OpenLibrary REST API.'
        );
    }
}
