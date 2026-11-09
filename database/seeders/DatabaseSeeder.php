<?php

namespace Database\Seeders;

use App\Models\Buku;
use App\Models\Kategori;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Uji Pustakawan
        User::firstOrCreate(
            ['email' => 'pustakawan@del.ac.id'],
            [
                'name' => 'Pustakawan Del',
                'password' => Hash::make('password123'),
            ]
        );

        // 2. Kategori & Buku Awal jika masih kosong
        if (Kategori::count() === 0) {
            Kategori::factory()
                ->count(5)
                ->has(Buku::factory()->count(4))
                ->create();
        }

        // 3. Pastikan ada buku ber-ISBN standar untuk pengujian referensi eksternal (Clean Code)
        $kategoriUtama = Kategori::first();
        if ($kategoriUtama) {
            $bukuRef = Buku::firstOrCreate(
                ['isbn' => '9780132350884'],
                [
                    'judul' => 'Clean Code: A Handbook of Agile Software Craftsmanship',
                    'penulis' => 'Robert C. Martin',
                    'penerbit' => 'Prentice Hall',
                    'tahun_terbit' => 2008,
                    'kategori_id' => $kategoriUtama->id,
                    'stok' => 5,
                    'sinopsis' => 'Buku panduan rekayasa perangkat lunak dan best practice clean code.',
                ]
            );

            // 4. Pastikan ada buku dengan stok 0 untuk pengujian skenario penolakan stok habis
            Buku::firstOrCreate(
                ['isbn' => '9786020388298'],
                [
                    'judul' => 'Buku Habis Stok (Test Out of Stock)',
                    'penulis' => 'Penulis Uji',
                    'penerbit' => 'Del Press',
                    'tahun_terbit' => 2024,
                    'kategori_id' => $kategoriUtama->id,
                    'stok' => 0,
                    'sinopsis' => 'Buku dengan stok 0 untuk menguji validasi dan HATEOAS.',
                ]
            );

            // 5. Data uji peminjaman aktif untuk pengujian SOAP Bebas Pustaka & konflik penghapusan buku
            Peminjaman::firstOrCreate(
                [
                    'buku_id' => $bukuRef->id,
                    'nim_peminjam' => '12S22001',
                ],
                [
                    'nama_peminjam' => 'Bona Pandiangan',
                    'tanggal_pinjam' => now()->subDays(3)->toDateString(),
                    'durasi_hari' => 7,
                    'status' => 'dipinjam',
                ]
            );
        }
    }
}
