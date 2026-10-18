<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\BukuController;
use App\Http\Controllers\PeminjamanController;

// ─── Rute Autentikasi Pustakawan (Guest Only) ───
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// ─── Rute Logout (Auth Only) ───
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ─── Redirect Root ke Katalog Buku ───
Route::get('/', fn () => redirect()->route('buku.index'));

// ─── Katalog Publik (index & show boleh diakses tanpa login) ───
Route::resource('buku', BukuController::class)->only(['index', 'show']);
Route::resource('kategori', KategoriController::class)->only(['index', 'show']);

// ─── Rute yang Diproteksi Middleware Auth (Mutasi Data) ───
Route::middleware('auth')->group(function () {
    // Buku: create, store, edit, update, destroy
    Route::resource('buku', BukuController::class)->except(['index', 'show']);

    // Kategori: create, store, edit, update, destroy
    Route::resource('kategori', KategoriController::class)->except(['index', 'show']);

    // Seluruh rute Peminjaman
    Route::get('/peminjaman/export/csv', [PeminjamanController::class, 'exportCsv'])->name('peminjaman.export.csv');
    Route::get('/peminjaman', [PeminjamanController::class, 'index'])->name('peminjaman.index');
    Route::get('/peminjaman/create', [PeminjamanController::class, 'create'])->name('peminjaman.create');
    Route::post('/peminjaman', [PeminjamanController::class, 'store'])->name('peminjaman.store');
    Route::patch('/peminjaman/{peminjaman}/kembalikan', [PeminjamanController::class, 'kembalikan'])->name('peminjaman.kembalikan');
});