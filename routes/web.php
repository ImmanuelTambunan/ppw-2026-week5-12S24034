<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\BukuController;

Route::get('/', fn () => redirect()->route('buku.index'));

Route::resource('kategori', KategoriController::class);
Route::resource('buku', BukuController::class);