<?php

namespace Database\Seeders;

use App\Models\Buku;
use App\Models\Kategori;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Membuat 5 kategori, dan setiap kategori memiliki 4 buku (total 20 buku)
        Kategori::factory()
            ->count(5)
            ->has(Buku::factory()->count(4))
            ->create();
    }
}
