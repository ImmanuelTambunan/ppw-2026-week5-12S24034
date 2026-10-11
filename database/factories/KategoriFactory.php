<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class KategoriFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode_kategori' => strtoupper(fake()->unique()->bothify('KAT-###')),
            'nama_kategori' => fake()->unique()->randomElement([
                'Sains & Teknologi',
                'Pemrograman & Rekayasa Perangkat Lunak',
                'Sistem Informasi Bisnis',
                'Jaringan & Keamanan Siber',
                'Kecerdasan Buatan',
                'Manajemen Proyek TI',
            ]),
        ];
    }
}
