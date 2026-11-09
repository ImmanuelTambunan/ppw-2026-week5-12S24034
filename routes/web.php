<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\BukuController;
use App\Http\Controllers\Soap\LibrarySoapController;

Route::get('/', fn () => redirect()->route('buku.index'));

Route::resource('kategori', KategoriController::class);
Route::resource('buku', BukuController::class);

// =========================================================================
// SOAP Legacy Web Service Endpoint (Bebas Pustaka Yudisium)
// =========================================================================
Route::get('/soap/sipus.wsdl', [LibrarySoapController::class, 'wsdl']);
Route::post('/soap/sipus', [LibrarySoapController::class, 'handle']);
