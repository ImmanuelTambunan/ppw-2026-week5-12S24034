<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PeminjamanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $links = [
            'self' => [
                'href' => url("/api/v1/peminjamans/{$this->id}"),
                'method' => 'GET',
            ],
            'buku' => [
                'href' => url("/api/v1/bukus/{$this->buku_id}"),
                'method' => 'GET',
            ],
        ];

        // HATEOAS Dinamis: Aksi kembalikan_buku HANYA muncul jika status peminjaman masih 'dipinjam'
        if ($this->status === 'dipinjam') {
            $links['kembalikan_buku'] = [
                'href' => url("/api/v1/peminjamans/{$this->id}/kembalikan"),
                'method' => 'PATCH',
            ];
        }

        return [
            'id' => $this->id,
            'buku_id' => $this->buku_id,
            'buku' => new BukuResource($this->whenLoaded('buku')),
            'nim_peminjam' => $this->nim_peminjam,
            'nama_peminjam' => $this->nama_peminjam,
            'tanggal_pinjam' => $this->tanggal_pinjam?->format('Y-m-d') ?? $this->tanggal_pinjam,
            'tanggal_kembali' => $this->tanggal_kembali?->format('Y-m-d') ?? $this->tanggal_kembali,
            'durasi_hari' => (int) $this->durasi_hari,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            '_links' => $links,
        ];
    }
}
