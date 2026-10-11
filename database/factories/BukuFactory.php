<?php

namespace Database\Factories;

use App\Models\Kategori;
use Illuminate\Database\Eloquent\Factories\Factory;

class BukuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'isbn' => fake()->unique()->isbn13(),
            'judul' => fake()->sentence(4),
            'penulis' => fake()->name(),
            'penerbit' => fake()->company(),
            'tahun_terbit' => fake()->numberBetween(2015, 2026),
            'kategori_id' => Kategori::factory(),
            'stok' => fake()->numberBetween(1, 50),
            'sinopsis' => fake()->paragraph(3),
        ];
    }
}