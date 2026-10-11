# SIPUS-Del - Sistem Informasi Perpustakaan Kampus Del

> **Tugas Mandiri Praktikum Minggu 05**  
> **Mata Kuliah:** Pemrograman & Pengujian Web (1253101)  
> **Program Studi:** Sarjana Sistem Informasi / Sarjana Informatika  
> **Institusi:** Institut Teknologi Del  

---

## 📌 Deskripsi Proyek
SIPUS-Del adalah aplikasi web berbasis server-side rendering (SSR) yang dibangun menggunakan kerangka kerja **Laravel 11.x**. Proyek ini mengimplementasikan pola arsitektur **Model-View-Controller (MVC)**, relasi kardinalitas multi-entitas dengan **Eloquent ORM**, validasi formulir sisi server yang ketat, pencegahan serangan CSRF, antarmuka modular **Blade Components** bertema *Del Royal Purple* (`#4C1D95`), serta pembuktian optimasi performa melalui **Eager Loading** guna meniadakan *N+1 Query Problem*.

---

## 🚀 Fitur & Spesifikasi Teknis
- **Skema Data Relasional & Migrations:**
  - Tabel `kategoris`: `id`, `kode_kategori` (unik), `nama_kategori`, timestamps.
  - Tabel `bukus`: `id`, `isbn` (unik), `judul`, `penulis`, `penerbit`, `tahun_terbit`, `kategori_id` (foreign key berelasi ke `kategoris` dengan `cascadeOnDelete`), `stok`, `sinopsis`, timestamps.
- **Eloquent ORM & Kardinalitas:**
  - `Kategori` *hasMany* `Buku`
  - `Buku` *belongsTo* `Kategori`
  - Proteksi Mass Assignment menggunakan `$fillable`.
- **Routing RESTful & Resource Controllers:**
  - 7 aksi CRUD baku via `Route::resource('buku', BukuController::class)` dan `Route::resource('kategori', KategoriController::class)`.
  - Menerapkan *Implicit Route Model Binding*.
- **Validasi Server-Side & Keamanan Web:**
  - Validasi masukan form: ISBN unik, judul minimal 5 karakter, stok minimal 0, relasi kategori valid (`exists`).
  - Proteksi token keamanan `@csrf` di setiap form dan *method spoofing* (`@method('PUT')`, `@method('DELETE')`).
- **Antarmuka Modular Blade:**
  - Master Shell Layout `<x-layout>` bertema Del Purple (`#4C1D95`).
  - Penanganan data kosong menggunakan `@forelse`.
  - Flash session message interaktif dan Bootstrap 5 pagination.
- **Mock Data Realistis:**
  - Factory & Seeder menghasilkan data uji menggunakan pustaka Faker (5 kategori dan 20 data buku).

---

## 🔍 Bukti Audit Kueri SQL (Mitigasi N+1 Problem)
Audit performa dicatat melalui listener `DB::listen` pada `AppServiceProvider.php`. Saat memuat halaman katalog buku (`/buku`), sistem terbukti menerapkan **Eager Loading** (`Buku::with('kategori')`) sehingga hanya mengeksekusi kueri terpadu tanpa perulangan $N+1$:

```text
[SQL AUDIT] (0.17 ms) select count(*) as aggregate from "bukus"
[SQL AUDIT] (0.22 ms) select * from "bukus" order by "created_at" desc limit 10 offset 0
[SQL AUDIT] (0.13 ms) select * from "kategoris" where "kategoris"."id" in (2, 3, 4)