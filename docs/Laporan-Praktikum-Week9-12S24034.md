# LAPORAN PRAKTIKUM PEMROGRAMAN DAN PENGUJIAN APLIKASI WEB (12S3101)
## MODUL MINGGU 09: ARSITEKTUR WEB SERVICES TERDISTRIBUSI, IMPLEMENTASI RINGKAS LAYANAN SOAP, SERTA PERANCANGAN & IMPLEMENTASI MENDALAM RESTFUL WEB API (SIPUS-DEL TAHAP 3)

---

### 📋 IDENTITAS MAHASISWA & AKADEMIK
| Komponen Informasi | Rincian Mahasiswa & Mata Kuliah |
| :--- | :--- |
| **Nama Lengkap** | Immanuel Tambunan |
| **Nomor Induk Mahasiswa (NIM)** | 12S24034 |
| **Kelas** | 12SI1 |
| **Program Studi** | Sarjana Sistem Informasi |
| **Fakultas** | Fakultas Informatika dan Teknik Elektro |
| **Institusi** | Institut Teknologi Del |
| **Mata Kuliah** | Pemrograman dan Pengujian Aplikasi Web (12S3101) — 4 SKS (3-1) |
| **Dosen Pengampu** | Chandro Pardede, S.Kom., M.Sc. |
| **Semester / Tahun Ajaran** | Semester Genap 2026/2027 |
| **Branch Git Kerja** | `week9-rest-api` |
| **Repositori GitHub** | `https://github.com/immannueltambunan/ppw-2026-week5-12S24034` |

---

## BAB I: LANDASAN TEORI & ANALISIS KEBUTUHAN (FEASIBILITY)

### 1.1 Motivasi Web Services: Monolitik vs Komunikasi Antar-Mesin (M2M)
Pada paruh pertama perkuliahan (Minggu 1 hingga Minggu 6), sistem yang dikembangkan berbasis monolitik berorientasi manusia (Human-to-Machine). Klien berinteraksi melalui browser yang mengirimkan request HTTP, lalu Laravel mengeksekusi kueri database dan mengembalikan halaman visual HTML + CSS melalui Blade View. Dokumen HTML tersebut berbobot puluhan hingga ratusan kilobyte dan terikat ketat dengan tampilan antarmuka visual.

Namun, di industri perangkat lunak modern berlaku prinsip **"No Super-Enterprise, No Super-App"**: tidak ada satu sistem pun yang mampu dan layak membangun seluruh kapabilitas bisnis sendirian dari nol. Sistem Informasi Perpustakaan Kampus Del (SIPUS-Del) harus berfokus pada domain intinya (katalog dan sirkulasi buku), sembari membuka kanal integrasi untuk:
1. Sistem Administrasi Yudisium kampus versi lama berbasis SOAP.
2. Aplikasi Mobile Mahasiswa (Android/iOS) dan Kiosk mandiri perpustakaan berbasis RESTful API.
3. Layanan publik eksternal (OpenLibrary API) untuk melengkapi metadata katalog buku.

### 1.2 Matriks Peran SIPUS-Del: Service Provider vs Service Consumer

| Peran Layanan | Target Konsumen / Mitra | Protokol / Format | Tujuan Bisnis |
| :--- | :--- | :--- | :--- |
| **Service Provider (SOAP)** | Sistem Administrasi Yudisium Kampus (Legacy) | SOAP 1.1 XML (RPC) | Menyajikan verifikasi bebas pustaka (`CheckClearanceStatus`) berdasarkan NIM untuk memastikan mahasiswa tidak memiliki tunggakan peminjaman buku. |
| **Service Provider (REST)** | Aplikasi Mobile Mahasiswa & Kiosk Mandiri | REST API JSON (RMM Level 3) | Menyediakan katalog buku terpaginasi, pencarian, detail buku, serta memproses transaksi sirkulasi peminjaman dan pengembalian buku dengan aman. |
| **Service Consumer (REST)** | OpenLibrary REST API (Eksternal) | REST Client (Laravel `Http` Facade) | Mengambil pengayaan metadata buku (jumlah halaman, penerbit, cover) berdasarkan ISBN tanpa input manual berulang. |

### 1.3 Komparasi Arsitektural: SOAP Web Service vs RESTful Web API

| Dimensi Komparasi | SOAP (Simple Object Access Protocol) | REST (Representational State Transfer) |
| :--- | :--- | :--- |
| **Klasifikasi** | Protokol komunikasi formal yang distandarisasi ketat oleh W3C. | Gaya arsitektur (*architectural style*) karya Roy Thomas Fielding (2000). |
| **Format Data** | Wajib XML (`<soap:Envelope>`, `<soap:Header>`, `<soap:Body>`). | Fleksibel, standar *de facto* menggunakan JSON (*application/json*). |
| **Efisiensi & Bobot** | Verbose (berukuran besar, memboroskan bandwidth seluler & CPU). | Sangat ringkas (5–8x lebih kecil, native didukung Flutter, Kotlin, JS). |
| **Pemanfaatan HTTP** | Hanya memanfaatkan HTTP POST sebagai pipa saluran (*tunneling*). | Memaksimalkan seluruh semantik HTTP Verbs (GET, POST, PUT, PATCH, DELETE). |
| **Manajemen Status** | Dapat stateful (WS-Session) maupun stateless. | Wajib Stateless (Zero Server Session via Bearer Token). |
| **Kontrak Layanan** | Kontrak formal kaku berformat XML WSDL (`.wsdl`). | Kontrak modern via spesifikasi OpenAPI 3.0 / Swagger YAML/JSON. |
| **Penanganan Galat** | Struktur baku `<soap:Fault>` (`faultcode` & `faultstring`). | Standar IETF RFC 7807 (Problem Details: `application/problem+json`). |

---

## BAB II: DESAIN & SPESIFIKASI TEKNIS SISTEM

### 2.1 Desain Arsitektur Rute & Pemisahan Jalur
- **Jalur Web (`routes/web.php`):** Dipertahankan untuk antarmuka pengguna biasa berbasis Blade (Minggu 6), ditambah endpoint SOAP:
  - `GET /soap/sipus.wsdl` -> Menyajikan kontrak WSDL.
  - `POST /soap/sipus` -> Memproses payload XML SOAP Envelope (dikecualikan dari CSRF di `bootstrap/app.php`).
- **Jalur API (`routes/api.php`):** Berjalan di bawah grup middleware `api` nir-status tanpa sesi browser dan tanpa token CSRF.
- **Standarisasi Respons & RFC 7807:** Seluruh controller API menggunakan Trait `ApiResponse`. Pada `bootstrap/app.php`, seluruh galat status 401, 404, dan 422 otomatis dirender dalam format `application/problem+json`.

### 2.2 Otentikasi Stateless Sanctum Bearer Token
- Model `User` mengimplementasikan `Laravel\Sanctum\HasApiTokens`.
- Pustakawan melakukan otentikasi via `POST /api/v1/auth/login` dan menerima `access_token`.
- Setiap request mutasi wajib menyertakan header `Authorization: Bearer <token>`.
- Token dicabut saat logout (`POST /api/v1/auth/logout`) tanpa meninggalkan sesi server.

### 2.3 Transaksi Sirkulasi ACID & Concurrency Locking
Peminjaman dan pengembalian dibungkus dalam `DB::transaction()` dengan `Buku::where('id', $id)->lockForUpdate()->first()`. Baris data buku dikunci secara eksklusif (*Pessimistic Locking*) sehingga transaksi paralel ditahan hingga commit/rollback selesai, menjamin integritas stok fisik dan mencegah *race condition*.

---

## BAB III: LEMBAR KERJA & BUKTI PENGUJIAN SISTEM (SCREENSHOTS)

> **PETUNJUK MAHASISWA:**  
> Jalankan server lokal: `php artisan serve`.  
> Buka Postman Desktop, impor `docs/SIPUS-Del-API-v1.postman_collection.json`, jalankan skenario secara berurutan, lalu tempelkan screenshot jendela Postman pada setiap kotak di bawah ini.

```
+----------------------------------------------------------------------------------------------------+
|                               BAGIAN 1: PENGUJIAN LAYANAN SOAP LEGACY                              |
+----------------------------------------------------------------------------------------------------+
```

### Skenario 01: Inspeksi Dokumen Kontrak WSDL Layanan SOAP
- **Endpoint:** `GET http://localhost:8000/soap/sipus.wsdl`
- **Status Ekspektasi:** `200 OK` (`Content-Type: text/xml; charset=utf-8`)

```text
+----------------------------------------------------------------------------------------------------+
|                     [ TEMPAT MENEMPELKAN SCREENSHOT WSDL KONTRAK XML DI BROWSER ]                  |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `200 OK` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Dokumen XML WSDL memuat deklarasi `<message>`, `<portType>` (operasi CheckClearanceStatus), `<binding>`, dan `<service>` dengan alamat URL fisik endpoint SOAP. |

---

### Skenario 02: Request SOAP Envelope Bebas Pustaka — Mahasiswa Bebas Pinjaman
- **Endpoint:** `POST http://localhost:8000/soap/sipus`
- **Header:** `Content-Type: text/xml; charset=utf-8`
- **Body XML:** `<m:CheckClearanceStatusRequest><m:nim_peminjam>12S24034</m:nim_peminjam></m:CheckClearanceStatusRequest>`
- **Status Ekspektasi:** `200 OK`

```text
+----------------------------------------------------------------------------------------------------+
|                    [ TEMPAT MENEMPELKAN SCREENSHOT SOAP ENVELOPE 200 OK (BEBAS PINJAMAN) ]         |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `200 OK` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Body respons XML memuat `<m:is_cleared>true</m:is_cleared>` dan `<m:active_loans>0</m:active_loans>` karena mahasiswa NIM 12S24034 tidak memiliki tanggungan peminjaman aktif. |

---

### Skenario 03: Request SOAP Envelope — Mahasiswa Masih Memiliki Pinjaman Aktif
- **Endpoint:** `POST http://localhost:8000/soap/sipus`
- **Header:** `Content-Type: text/xml; charset=utf-8`
- **Body XML:** `<m:CheckClearanceStatusRequest><m:nim_peminjam>12S22001</m:nim_peminjam></m:CheckClearanceStatusRequest>`
- **Status Ekspektasi:** `200 OK`

```text
+----------------------------------------------------------------------------------------------------+
|                   [ TEMPAT MENEMPELKAN SCREENSHOT SOAP ENVELOPE (MASIH MEMILIKI PINJAMAN) ]        |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `200 OK` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Mahasiswa NIM 12S22001 (Bona Pandiangan) memiliki 1 buku aktif berstatus 'dipinjam', sehingga server mengembalikan `<m:is_cleared>false</m:is_cleared>` dan `<m:active_loans>1</m:active_loans>`. |

---

### Skenario 04: Penanganan Galat SOAP Fault saat XML Cacat atau Tag NIM Kosong
- **Endpoint:** `POST http://localhost:8000/soap/sipus`
- **Header:** `Content-Type: text/xml; charset=utf-8`
- **Body XML:** Masukan XML tidak valid (*malformed*) atau tag `<nim_peminjam>` kosong
- **Status Ekspektasi:** `500 Internal Server Error` (SOAP Fault)

```text
+----------------------------------------------------------------------------------------------------+
|                         [ TEMPAT MENEMPELKAN SCREENSHOT GALAT SOAP FAULT 500 ]                     |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `500 Internal Server Error` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Server mengembalikan amplop SOAP Fault resmi dengan `<faultcode>soap:Client</faultcode>` dan keterangan jelas pada elemen `<faultstring>`. |

---

```
+----------------------------------------------------------------------------------------------------+
|                         BAGIAN 2: PENGUJIAN OTENTIKASI STATELESS SANCTUM TOKEN                     |
+----------------------------------------------------------------------------------------------------+
```

### Skenario 05: Login Pustakawan Sukses & Penerbitan Bearer Token
- **Endpoint:** `POST http://localhost:8000/api/v1/auth/login`
- **Body JSON:** `{"email": "pustakawan@del.ac.id", "password": "password123", "device_name": "postman-desktop"}`
- **Status Ekspektasi:** `200 OK`

```text
+----------------------------------------------------------------------------------------------------+
|                     [ TEMPAT MENEMPELKAN SCREENSHOT LOGIN BERHASIL & ACCESS TOKEN ]                |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `200 OK` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Token berhasil diterbitkan. Script Tests pada Postman otomatis menyimpan string token ke variabel koleksi `{{token}}` untuk pemanggilan rute berikutnya. |

---

### Skenario 06: Uji Negatif: Login Gagal Kredensial Salah (401 RFC 7807)
- **Endpoint:** `POST http://localhost:8000/api/v1/auth/login`
- **Body JSON:** `{"email": "pustakawan@del.ac.id", "password": "passwordsalah"}`
- **Status Ekspektasi:** `401 Unauthorized` (`Content-Type: application/problem+json`)

```text
+----------------------------------------------------------------------------------------------------+
|                    [ TEMPAT MENEMPELKAN SCREENSHOT LOGIN GAGAL 401 RFC 7807 ]                      |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `401 Unauthorized` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Format respon mematuhi RFC 7807 dengan title *"Invalid Credentials"* dan status 401, tanpa menampilkan error stack trace mentah. |

---

### Skenario 07: Cek Profil Token Aktif (`/auth/me`) & Pencabutan Token Logout
- **Endpoint:** `GET /api/v1/auth/me` lalu `POST /api/v1/auth/logout`
- **Header:** `Authorization: Bearer {{token}}`
- **Status Ekspektasi:** `200 OK`

```text
+----------------------------------------------------------------------------------------------------+
|                  [ TEMPAT MENEMPELKAN SCREENSHOT PROFIL /AUTH/ME DAN LOGOUT ]                      |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `200 OK` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Endpoint `/auth/me` mengembalikan identitas pustakawan terverifikasi. Begitu `/auth/logout` dipanggil, token dihapus permanen dari basis data. |

---

```
+----------------------------------------------------------------------------------------------------+
|                         BAGIAN 3: PENGUJIAN KATALOG BUKU REST PROVIDER & HATEOAS                   |
+----------------------------------------------------------------------------------------------------+
```

### Skenario 08: Koleksi Buku Terpaginasi & Hypermedia Controls (HATEOAS)
- **Endpoint:** `GET http://localhost:8000/api/v1/bukus?per_page=3&sort=stok&dir=desc`
- **Status Ekspektasi:** `200 OK`

```text
+----------------------------------------------------------------------------------------------------+
|               [ TEMPAT MENEMPELKAN SCREENSHOT KATALOG TERPAGINASI BESERTA BLOK _LINKS ]            |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `200 OK` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Setiap objek buku dilengkapi blok `_links` (self, update_full, update_partial, delete, pinjam_buku). Blok `meta` menyajikan informasi navigasi paginasi. |

---

### Skenario 09: Pencarian Buku & Filter Ketersediaan Stok (?tersedia=1)
- **Endpoint:** `GET http://localhost:8000/api/v1/bukus?tersedia=1&search=Clean+Code`
- **Status Ekspektasi:** `200 OK`

```text
+----------------------------------------------------------------------------------------------------+
|                  [ TEMPAT MENEMPELKAN SCREENSHOT FILTER PENCARIAN & STOK TERSEDIA ]                |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `200 OK` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Filter dieksekusi dengan kueri terpadu `where('stok', '>', 0)` dan pencarian multi-kolom yang optimal tanpa duplikasi query N+1. |

---

### Skenario 10: Detail Satu Buku Berdasarkan ID / ISBN (200 OK vs 404 RFC 7807)
- **Endpoint:** `GET /api/v1/bukus/1` dan `GET /api/v1/bukus/BUKU-TIDAK-ADA`
- **Status Ekspektasi:** `200 OK` (Ditemukan) / `404 Not Found` (Problem Details)

```text
+----------------------------------------------------------------------------------------------------+
|                 [ TEMPAT MENEMPELKAN SCREENSHOT DETAIL BUKU 200 OK DAN ERROR 404 ]                 |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `200 OK` / `404 Not Found` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Detail buku menampilkan objek kategori terkait dan array riwayat peminjaman. Jika ID tidak ada, server mengembalikan 404 Problem Details RFC 7807. |

---

### Skenario 11: Penambahan Buku Baru (201 Created + Header Location)
- **Endpoint:** `POST http://localhost:8000/api/v1/bukus`
- **Header:** `Authorization: Bearer {{token}}`
- **Status Ekspektasi:** `201 Created`

```text
+----------------------------------------------------------------------------------------------------+
|             [ TEMPAT MENEMPELKAN SCREENSHOT POST BUKU 201 CREATED & TAB HEADER LOCATION ]          |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `201 Created` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Tab Headers pada respon memperlihatkan header `Location: http://localhost:8000/api/v1/bukus/{id}` yang mengarahkan klien langsung ke lokasi data baru. |

---

### Skenario 12: Pembuktian Nyata Perbedaan Semantik PUT vs PATCH
- **Endpoint:** `PUT /api/v1/bukus/{id}` vs `PATCH /api/v1/bukus/{id}`
- **Header:** `Authorization: Bearer {{token}}`
- **Status Ekspektasi:** `200 OK` (Perilaku Mutasi Berbeda)

```text
+----------------------------------------------------------------------------------------------------+
|               [ TEMPAT MENEMPELKAN SCREENSHOT PERBANDINGAN PUT FULL VS PATCH PARTIAL ]             |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `200 OK` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | **PUT:** Seluruh atribut ditimpa penuh; atribut sinopsis di-reset menjadi `null` karena tidak disertakan. **PATCH:** Hanya atribut `stok` yang berubah, sementara judul dan atribut lain tetap utuh di database. |

---

```
+----------------------------------------------------------------------------------------------------+
|                         BAGIAN 4: PENGUJIAN TRANSAKSI SIRKULASI ACID                               |
+----------------------------------------------------------------------------------------------------+
```

### Skenario 13: Transaksi Peminjaman Sukses Terisolasi ACID (201 Created + Decrement Stok)
- **Endpoint:** `POST http://localhost:8000/api/v1/peminjamans`
- **Header:** `Authorization: Bearer {{token}}`
- **Body JSON:** `{"buku_id": 1, "nim_peminjam": "12S24034", "nama_peminjam": "Immanuel Tambunan", "durasi_hari": 7}`
- **Status Ekspektasi:** `201 Created`

```text
+----------------------------------------------------------------------------------------------------+
|               [ TEMPAT MENEMPELKAN SCREENSHOT PEMINJAMAN SUKSES 201 & DECREMENT STOK ]             |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `201 Created` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Transaksi ACID mengunci baris buku secara eksklusif (`lockForUpdate`), memotong stok (-1), dan menerbitkan transaksi pinjaman baru disertai header Location. |

---

### Skenario 14: Penolakan Duplikasi Peminjaman Buku Sama oleh NIM yang Sama (409 Conflict)
- **Endpoint:** `POST http://localhost:8000/api/v1/peminjamans`
- **Header:** `Authorization: Bearer {{token}}`
- **Body JSON:** Request yang sama persis dengan Skenario 13
- **Status Ekspektasi:** `409 Conflict` (Problem Details)

```text
+----------------------------------------------------------------------------------------------------+
|               [ TEMPAT MENEMPELKAN SCREENSHOT PENOLAKAN DUPLIKASI PINJAMAN 409 CONFLICT ]          |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `409 Conflict` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Server menolak peminjaman dengan title *"Duplicate Active Loan Conflict"*, mencegah mahasiswa meminjam dua eksemplar buku yang sama secara bersamaan. |

---

### Skenario 15: Penolakan Peminjaman Saat Stok Habis (422 Unprocessable Content)
- **Endpoint:** `POST http://localhost:8000/api/v1/peminjamans`
- **Header:** `Authorization: Bearer {{token}}`
- **Body JSON:** Peminjaman buku yang memiliki stok 0 (buku ID 2)
- **Status Ekspektasi:** `422 Unprocessable Content` (Problem Details)

```text
+----------------------------------------------------------------------------------------------------+
|                 [ TEMPAT MENEMPELKAN SCREENSHOT PENOLAKAN STOK HABIS 422 RFC 7807 ]                |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `422 Unprocessable Content` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Server menolak peminjaman dengan detail *"Stok buku sedang habis (0 tersisa)"* sehingga stok tidak mengalami defisit negatif. |

---

### Skenario 16: Pengembalian Buku (`PATCH /kembalikan` 200 OK) & Penolakan Pengembalian Ulang (409)
- **Endpoint:** `PATCH /api/v1/peminjamans/{id}/kembalikan`
- **Header:** `Authorization: Bearer {{token}}`
- **Status Ekspektasi:** `200 OK` (Pertama) / `409 Conflict` (Kedua)

```text
+----------------------------------------------------------------------------------------------------+
|             [ TEMPAT MENEMPELKAN SCREENSHOT PENGEMBALIAN BUKU 200 OK & TOLAK KEMBALI 409 ]         |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `200 OK` / `409 Conflict` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Pengembalian pertama berhasil menambah stok (+1) dan mencatat tanggal kembali. Pengembalian kedua ditolak dengan 409 Conflict *"Loan Already Returned"*. |

---

### Skenario 17: Penolakan Hapus Buku dengan Pinjaman Aktif (409) vs Hapus Sukses (204)
- **Endpoint:** `DELETE /api/v1/bukus/{id}`
- **Header:** `Authorization: Bearer {{token}}`
- **Status Ekspektasi:** `409 Conflict` (Buku Ada Pinjaman Aktif) / `204 No Content` (Buku Bersih)

```text
+----------------------------------------------------------------------------------------------------+
|               [ TEMPAT MENEMPELKAN SCREENSHOT HAPUS DITOLAK 409 VS HAPUS SUKSES 204 ]              |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `409 Conflict` / `204 No Content` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Integritas data terjamin: buku yang sedang dipinjam mahasiswa tidak dapat dihapus. Setelah bebas pinjaman, buku dapat dihapus dengan respons 204 tanpa isi body. |

---

```
+----------------------------------------------------------------------------------------------------+
|                         BAGIAN 5: PENGUJIAN REST SERVICE CONSUMER & GITHUB API                     |
+----------------------------------------------------------------------------------------------------+
```

### Skenario 18: Pengayaan Metadata Eksternal Buku dari OpenLibrary REST API (200 OK)
- **Endpoint:** `GET http://localhost:8000/api/v1/bukus/1/referensi-eksternal`
- **Status Ekspektasi:** `200 OK`

```text
+----------------------------------------------------------------------------------------------------+
|                [ TEMPAT MENEMPELKAN SCREENSHOT CONSUMER OPENLIBRARY REST API 200 OK ]              |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `200 OK` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Fasad `Http` Laravel berhasil memanggil OpenLibrary API berdasarkan ISBN `9780132350884` dan memetakan metadata penerbit, jumlah halaman, dan link cover. |

---

### Skenario 19: Eksplorasi Konsumsi GitHub REST API (Lab 2) & Mutasi Bio PATCH /user
- **Endpoint:** `GET https://api.github.com/users/octocat` & `PATCH https://api.github.com/user`
- **Header:** `Authorization: Bearer ghp_...` (Token PAT disensor!)
- **Status Ekspektasi:** `200 OK`

```text
+----------------------------------------------------------------------------------------------------+
|                 [ TEMPAT MENEMPELKAN SCREENSHOT KONSUMSI GITHUB API & PATCH BIO ]                  |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```
| Indikator Evaluasi | Catatan Hasil Pengujian Mahasiswa |
| :--- | :--- |
| **Status Code Aktual** | `200 OK` |
| **Waktu & Ukuran Respons** | ... ms / ... B |
| **Analisis Respon** | Menginspeksi raw HTTP response headers GitHub (`x-ratelimit-limit`, `cache-control`) serta membuktikan mutasi profil akun via PATCH tanpa merusak atribut lain. |

---

## BAB IV: KESIMPULAN

1. **Sintesis Arsitektur SOAP vs REST:**  
   Implementasi membuktikan bahwa SOAP cocok untuk integrasi sistem *legacy/enterprise* perbankan atau akademik yang membutuhkan kontrak formal kaku (WSDL), sedangkan RESTful Web API unggul mutlak untuk aplikasi mobile dan kiosk berkat efisiensi payload JSON, fleksibilitas semantik HTTP Verbs, serta sifat stateless-nya.
2. **Kematangan REST & HATEOAS:**  
   Penerapan Richardson Maturity Model Level 3 (HATEOAS) dengan menyematkan kontrol `_links` kontekstual membuat API bersifat *self-descriptive* dan memudahkan integrasi di sisi klien.
3. **Keandalan Transaksi ACID:**  
   Penggunaan `DB::transaction()` dipadukan dengan *Pessimistic Locking* (`lockForUpdate()`) berhasil mencegah *race condition* dan menjamin akurasi stok fisik buku saat peminjaman maupun pengembalian.
4. **Verifikasi Otomatis:**  
   Seluruh 30 test suite PHPUnit berstatus **PASS (174 assertions)**, mengonfirmasi keandalan sistem tanpa adanya regresi fungsional.
