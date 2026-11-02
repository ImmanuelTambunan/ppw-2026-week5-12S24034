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
        // ══════════════════════════════════════════════════════════════
        // 1. Akun Pustakawan untuk Pengujian Autentikasi & Sesi
        // ══════════════════════════════════════════════════════════════
        $pustakawan = User::create([
            'name'     => 'Pustakawan Perpustakaan Del',
            'email'    => 'pustakawan@del.ac.id',
            'password' => Hash::make('password123'),
        ]);

        // ══════════════════════════════════════════════════════════════
        // 2. Kategori & Buku Umum (via Factory: 5 kategori × 4 buku)
        // ══════════════════════════════════════════════════════════════
        Kategori::factory()
            ->count(5)
            ->has(Buku::factory()->count(4))
            ->create();

        // ══════════════════════════════════════════════════════════════
        // 3. Buku Uji Edge-Case untuk Pengujian Transaksi ACID
        // ══════════════════════════════════════════════════════════════
        $kategoriUji = Kategori::first();

        // Buku stok = 0 → membuktikan rollback saat stok habis
        $bukuHabis = Buku::create([
            'isbn'         => '978-0-000-00001',
            'judul'        => '[UJI ACID] Buku Stok Habis - Rollback Test',
            'penulis'      => 'Tim Penguji Del',
            'penerbit'     => 'IT Del Press',
            'tahun_terbit' => 2026,
            'kategori_id'  => $kategoriUji->id,
            'stok'         => 0,
            'sinopsis'     => 'Buku ini sengaja memiliki stok 0 untuk menguji mekanisme rollback transaksi. Percobaan peminjaman harus DITOLAK dan transaksi di-rollback.',
        ]);

        // Buku stok = 1 → membuktikan pessimistic locking (lockForUpdate)
        $bukuTerakhir = Buku::create([
            'isbn'         => '978-0-000-00002',
            'judul'        => '[UJI ACID] Buku Stok Terakhir - Lock Test',
            'penulis'      => 'Tim Penguji Del',
            'penerbit'     => 'IT Del Press',
            'tahun_terbit' => 2026,
            'kategori_id'  => $kategoriUji->id,
            'stok'         => 1,
            'sinopsis'     => 'Buku ini sengaja memiliki stok 1 untuk menguji pessimistic locking (lockForUpdate). Hanya 1 peminjaman yang boleh berhasil secara atomik.',
        ]);

        // ══════════════════════════════════════════════════════════════
        // 4. Sampel Transaksi Peminjaman (status campuran)
        //    Agar dashboard analitik & withCount terisi data nyata
        // ══════════════════════════════════════════════════════════════
        $bukuNormal = Buku::where('stok', '>', 1)->take(3)->get();

        // --- Transaksi berstatus 'dipinjam' (buku masih di tangan peminjam) ---
        if ($bukuNormal->count() >= 1) {
            Peminjaman::create([
                'kode_pinjam'         => 'PJ-SEED-001',
                'buku_id'             => $bukuNormal[0]->id,
                'nim_peminjam'        => '12S24001',
                'nama_peminjam'       => 'Mahasiswa Alfa',
                'tanggal_pinjam'      => now()->subDays(5)->toDateString(),
                'tanggal_jatuh_tempo' => now()->addDays(2)->toDateString(),
                'status'              => 'dipinjam',
            ]);
            $bukuNormal[0]->decrement('stok');
        }

        if ($bukuNormal->count() >= 2) {
            Peminjaman::create([
                'kode_pinjam'         => 'PJ-SEED-002',
                'buku_id'             => $bukuNormal[1]->id,
                'nim_peminjam'        => '12S24002',
                'nama_peminjam'       => 'Mahasiswa Beta',
                'tanggal_pinjam'      => now()->subDays(3)->toDateString(),
                'tanggal_jatuh_tempo' => now()->addDays(4)->toDateString(),
                'status'              => 'dipinjam',
            ]);
            $bukuNormal[1]->decrement('stok');
        }

        if ($bukuNormal->count() >= 3) {
            Peminjaman::create([
                'kode_pinjam'         => 'PJ-SEED-003',
                'buku_id'             => $bukuNormal[2]->id,
                'nim_peminjam'        => '12S24003',
                'nama_peminjam'       => 'Mahasiswa Gamma',
                'tanggal_pinjam'      => now()->subDays(1)->toDateString(),
                'tanggal_jatuh_tempo' => now()->addDays(6)->toDateString(),
                'status'              => 'dipinjam',
            ]);
            $bukuNormal[2]->decrement('stok');
        }

        // --- Transaksi berstatus 'dikembalikan' (sudah selesai) ---
        if ($bukuNormal->count() >= 1) {
            Peminjaman::create([
                'kode_pinjam'         => 'PJ-SEED-004',
                'buku_id'             => $bukuNormal[0]->id,
                'nim_peminjam'        => '12S24010',
                'nama_peminjam'       => 'Mahasiswa Delta',
                'tanggal_pinjam'      => now()->subDays(14)->toDateString(),
                'tanggal_jatuh_tempo' => now()->subDays(7)->toDateString(),
                'tanggal_kembali'     => now()->subDays(8)->toDateString(),
                'status'              => 'dikembalikan',
            ]);
        }

        if ($bukuNormal->count() >= 2) {
            Peminjaman::create([
                'kode_pinjam'         => 'PJ-SEED-005',
                'buku_id'             => $bukuNormal[1]->id,
                'nim_peminjam'        => '12S24011',
                'nama_peminjam'       => 'Mahasiswa Epsilon',
                'tanggal_pinjam'      => now()->subDays(10)->toDateString(),
                'tanggal_jatuh_tempo' => now()->subDays(3)->toDateString(),
                'tanggal_kembali'     => now()->subDays(4)->toDateString(),
                'status'              => 'dikembalikan',
            ]);
        }
    }
}
