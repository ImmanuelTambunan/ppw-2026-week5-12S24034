<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; // Tambahkan import ini

class Buku extends Model
{
    use HasFactory;

    protected $fillable = [
        'isbn',
        'judul',
        'penulis',
        'penerbit',
        'tahun_terbit',
        'kategori_id',
        'stok',
        'sinopsis',
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class);
    }

    /**
     * Relasi One-to-Many: Satu buku dapat memiliki banyak riwayat transaksi peminjaman.
     */
    public function peminjamans(): HasMany
    {
        return $this->hasMany(Peminjaman::class);
    }
}