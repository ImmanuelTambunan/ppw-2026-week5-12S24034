# SIPUS-Del - Sistem Informasi Perpustakaan Kampus Del
> **Penugasan Mandiri Praktikum Minggu 09 (Tahap 3)**  
> **Mata Kuliah:** Pemrograman & Pengujian Web (12S3101)  
> **Topik:** Distributed Web Services, SOAP Legacy Clearance Service, & Deep RESTful Web API (Provider & Consumer, HATEOAS, ACID Circulation, Sanctum Bearer Token, RFC 7807, & OpenAPI 3.0)  
> **Program Studi:** Sarjana Sistem Informasi  
> **Institusi:** Institut Teknologi Del  

---

## 👤 1. Identitas Mahasiswa
- **Nama Lengkap:** Immanuel Tambunan
- **NIM:** 12S24034
- **Kelas:** 12SI1
- **Program Studi:** Sarjana Sistem Informasi
- **Tahun Akademik:** 2026/2027 (Semester Genap)

---

## 🏛️ 2. Analisis Kebutuhan & Kelayakan Web Services (Feasibility Analysis)

Pada era transformasi digital kampus modern, arsitektur monolitik yang hanya menyajikan antarmuka visual peramban web (HTML + CSS via Blade) tidak lagi memadai untuk melayani ekosistem multi-perangkat dan integrasi antar-sistem kampus. Sesuai prinsip *Domain Focus*, *Service Orchestration*, dan *Service Contract*, SIPUS-Del Tahap 3 bertransformasi menjadi simpul layanan web terdistribusi.

### A. Matriks Peran Layanan pada SIPUS-Del (Consumer vs Provider)

| Komponen / Skenario Integrasi | Peran SIPUS-Del | Protokol / Gaya Arsitektur | Format Payload | Justifikasi & Karakteristik Teknis |
| :--- | :--- | :--- | :--- | :--- |
| **Sistem Bebas Pustaka Yudisium** | **Service Provider** (Legacy) | **SOAP 1.1** over HTTP (RPC) | XML (`<soap:Envelope>`) | Aplikasi administrasi kelulusan/yudisium kampus versi lama (*core enterprise system*) mewajibkan kontrak kaku formal berdokumen WSDL (`.wsdl`) dan format pertukaran XML ber-namespace dengan penanganan galat terstandar `<soap:Fault>`. |
| **Katalog Buku & Kiosk Perpustakaan** | **Service Provider** (Modern) | **RESTful Web API (API v1)** | JSON (`application/json`) | Aplikasi Android/iOS Mahasiswa dan Kiosk Mandiri membutuhkan pertukaran data yang cepat, berbobot ringan, hemat memori/CPU, serta mematuhi **Richardson Maturity Model (RMM) Level 3 (HATEOAS)** untuk navigasi dinamis. |
| **Transaksi Sirkulasi Peminjaman & Pengembalian** | **Service Provider** (Secure) | **RESTful API** + **Sanctum Token** | JSON (`application/json`) | Memerlukan otentikasi *Stateless Bearer Token* (`Authorization: Bearer <token>`) tanpa cookie sesi browser, dilengkapi isolasi transaksi bisnis ketat (**ACID + Pessimistic Locking `lockForUpdate()`**) guna mencegah kondisi *Race Condition* dan selisih stok fisik buku. |
| **Pengayaan Metadata Buku Berbasis ISBN** | **Service Consumer** | **RESTful API Client** (Laravel `Http` Facade) | JSON (`application/json`) | Mengonsumsi layanan publik eksternal **OpenLibrary REST API** (`https://openlibrary.org/api/books`) untuk melengkapi data sinopsis, penerbit, jumlah halaman, dan cover buku secara otomatis tanpa membebani pustakawan dengan input berulang. Dilengkapi mekanisme *timeout* (8 detik) dan *graceful fallback*. |

---

### B. Komparasi Mendalam: SOAP Web Service vs RESTful Web API

```
+----------------------------------------------------------------------------------------------------+
|                                PERBANDINGAN ARSITEKTUR KOMUNIKASI                                  |
+----------------------------------------------------------------------------------------------------+
|  A. SOAP (Legacy Enterprise):                                                                      |
|     [Client Yudisium] ──(POST /soap/sipus + XML Envelope ~1.8 KB)──► [LibrarySoapController]      |
|     [Client Yudisium] ◄─(XML Envelope + <is_cleared>true</is_cleared>)─ [DB Check Active Loans]     |
|                                                                                                    |
|  B. RESTful API (Modern Mobile / Kiosk):                                                           |
|     [Mobile App Del ] ──(GET /api/v1/bukus?tersedia=1 + Bearer Token)──► [BukuApiController]       |
|     [Mobile App Del ] ◄─(JSON Data + HATEOAS _links ~350 Bytes)──────── [BukuResource + Cache]     |
+----------------------------------------------------------------------------------------------------+
```

1. **Format Payload & Efisiensi Jaringan:**
   - **SOAP:** Mewajibkan pembungkus XML berlapis (`<soap:Envelope>`, `<soap:Header>`, `<soap:Body>`) dengan deklarasi namespace XML (`xmlns:soap`, `xmlns:tns`). Ukuran dokumen relatif besar (verbose) sehingga memboroskan bandwidth jaringan seluler mahasiswa.
   - **REST:** Menggunakan format JSON (*application/json*) yang ringkas, berukuran 5–8 kali lebih kecil, dan dapat langsung diparsing secara native oleh peramban, aplikasi mobile (Flutter/Kotlin/Swift), maupun JavaScript.
2. **Semantik Operasi & Pemanfaatan Protokol HTTP:**
   - **SOAP:** Memperlakukan HTTP hanya sebagai pipa saluran transmisi (*tunneling*). Seluruh operasi (baik baca maupun tulis) selalu dikirim menggunakan metode tunggal **HTTP POST**.
   - **REST:** Memanfaatkan seluruh potensi semantik HTTP: kata kerja dibatasi ketat (**GET**, **POST**, **PUT**, **PATCH**, **DELETE**) dan URI merepresentasikan kata benda (*nouns/resources*).
3. **Manajemen Status (State Management):**
   - **SOAP:** Sering kali bergantung pada konfigurasi *WS-Session* atau *stateful server-side binding*.
   - **REST:** Wajib **Stateless** (Nir-Status). Setiap request membawa identitas dan otorisasi mandiri (*self-contained*) melalui header `Authorization: Bearer <sanctum_token>`, memungkinkan server diskalakan horizontal di belakang load balancer tanpa dependensi berkas sesi.
4. **Kontrak Layanan (Service Contract):**
   - **SOAP:** Menggunakan dokumen XML WSDL formal yang bersifat wajib (*machine-readable contract*) untuk men-generate stub/proxy kode klien.
   - **REST:** Menggunakan spesifikasi dokumentasi terbuka modern **OpenAPI 3.0 / Swagger** (`docs/openapi-sipus-v1.yaml`) yang mudah dibaca oleh manusia sekaligus dapat diuji langsung melalui Swagger UI atau Postman.

---

## 📋 3. Tabel Katalog Endpoint Web Services (SOAP & REST API v1)

| No | Protokol / Kategori | Metode | Alamat URI Endpoint | Proteksi Otentikasi | Deskripsi Fungsional Bisnis | Kode Status Respons (HTTP) |
| :---: | :---: | :---: | :--- | :---: | :--- | :--- |
| **1** | **SOAP Legacy** | `GET` | `/soap/sipus.wsdl` | Publik (Nir-Auth) | Menyajikan dokumen kontrak formal WSDL XML operasi `CheckClearanceStatus` | `200 OK` (XML) |
| **2** | **SOAP Legacy** | `POST` | `/soap/sipus` | Publik (Exempt CSRF) | Memproses pengecekan status bebas pustaka mahasiswa berdasarkan `<nim_peminjam>` | `200 OK` / `500 SOAP Fault` |
| **3** | **REST Auth** | `POST` | `/api/v1/auth/login` | Publik | Otentikasi kredensial pustakawan & menerbitkan Stateless Bearer Token | `200 OK`, `401 Unauthorized` |
| **4** | **REST Auth** | `GET` | `/api/v1/auth/me` | `Bearer Token` | Memeriksa profil pustakawan pemilik token yang sedang aktif | `200 OK`, `401 Unauthorized` |
| **5** | **REST Auth** | `POST` | `/api/v1/auth/logout` | `Bearer Token` | Mencabut (*revoke*) token aktif dari database `personal_access_tokens` | `200 OK`, `401 Unauthorized` |
| **6** | **Katalog Buku** | `GET` | `/api/v1/bukus` | Publik | Mengambil koleksi buku terpaginasi (mendukung `search`, `kategori_id`, `tersedia`, `sort`, `dir`) | `200 OK` (JSON Meta + HATEOAS) |
| **7** | **Katalog Buku** | `GET` | `/api/v1/bukus/{identifier}` | Publik | Mengambil detail satu buku via ID numerik atau ISBN (lengkap dengan kategori & riwayat) | `200 OK`, `404 Not Found (RFC 7807)` |
| **8** | **Katalog Buku** | `POST` | `/api/v1/bukus` | `Bearer Token` | Menambahkan buku baru ke basis data | `201 Created` (+ Header `Location`), `422 Unprocessable` |
| **9** | **Katalog Buku** | `PUT` | `/api/v1/bukus/{id}` | `Bearer Token` | **Full Replacement:** Mengganti seluruh representasi buku (field nullable tidak dikirim direset null) | `200 OK`, `404 Not Found`, `422 Unprocessable` |
| **10** | **Katalog Buku** | `PATCH` | `/api/v1/bukus/{id}` | `Bearer Token` | **Partial Update:** Memperbarui hanya atribut yang dikirim (misal `stok`) tanpa merusak atribut lain | `200 OK`, `400 Bad Request`, `404 Not Found` |
| **11** | **Katalog Buku** | `DELETE` | `/api/v1/bukus/{id}` | `Bearer Token` | Menghapus buku (ditolak jika ada peminjaman aktif) | `204 No Content`, `409 Conflict (RFC 7807)` |
| **12** | **REST Consumer** | `GET` | `/api/v1/bukus/{id}/referensi-eksternal` | Publik | Memanggil OpenLibrary REST API via fasad `Http` untuk pengayaan data penerbit & cover buku | `200 OK`, `404 Not Found`, `502 Bad Gateway` |
| **13** | **Sirkulasi ACID** | `GET` | `/api/v1/peminjamans` | `Bearer Token` | Mengambil daftar riwayat peminjaman terpaginasi (filter `status`, `nim`, `buku_id`) | `200 OK`, `401 Unauthorized` |
| **14** | **Sirkulasi ACID** | `GET` | `/api/v1/peminjamans/{id}` | `Bearer Token` | Mengambil detail satu transaksi peminjaman beserta tautan dinamis HATEOAS | `200 OK`, `401`, `404 Not Found` |
| **15** | **Sirkulasi ACID** | `POST` | `/api/v1/peminjamans` | `Bearer Token` | Mencatat transaksi peminjaman buku baru terisolasi ACID (`DB::transaction` + `lockForUpdate`) | `201 Created` (+ Header `Location`), `409 Conflict`, `422` |
| **16** | **Sirkulasi ACID** | `PATCH` | `/api/v1/peminjamans/{id}/kembalikan` | `Bearer Token` | Mengembalikan buku pinjaman secara atomik: status menjadi `dikembalikan`, stok buku bertambah (+1) | `200 OK`, `409 Conflict` (jika sudah dikembalikan) |

---

## ⚡ 4. Panduan Instalasi & Pengujian Cepat

### A. Prasyarat Lingkungan
- PHP `>= 8.2` (dengan ekstensi `pdo_sqlite`, `curl`, `dom`, `simplexml`, `mbstring`, `openssl`)
- Composer `>= 2.6`
- Postman Desktop Client (atau cURL CLI)

### B. Langkah Instalasi Langkah demi Langkah
1. **Clone dan Pindah ke Branch Kerja:**
   ```bash
   git clone https://github.com/immannueltambunan/ppw-2026-week5-12S24034.git
   cd ppw-2026-week5-12S24034
   git checkout week9-rest-api
   ```
2. **Salin Environment dan Pasang Dependensi:**
   ```bash
   cp .env.example .env
   composer install
   php artisan key:generate
   ```
3. **Eksekusi Migrasi & Pembenihan Data Uji (Seeder):**
   ```bash
   php artisan migrate:fresh --seed
   ```
   > [!IMPORTANT]
   > Akun uji pustakawan otomatis disiapkan pada basis data:
   > - **Email:** `pustakawan@del.ac.id`
   > - **Password:** `password123`

4. **Jalankan Seluruh Suite Pengujian Otomatis (PHPUnit Feature Tests):**
   ```bash
   php artisan test
   ```
   *Seluruh 30 skenario pengujian unit & feature test (SOAP, Sanctum, CRUD Buku, ACID Circulation, OpenLibrary Consumer) dipastikan berstatus **PASS**.*

5. **Nyalakan Server Lokal Development:**
   ```bash
   php artisan serve
   ```
   *Server aktif melayani request pada: `http://localhost:8000`.*

---

### C. Cara Mengimpor & Menjalankan Koleksi Postman

1. Buka aplikasi **Postman Desktop**.
2. Klik tombol **Import** di sudut kiri atas.
3. Pilih berkas koleksi yang telah disediakan pada repositori:
   `docs/SIPUS-Del-API-v1.postman_collection.json`
4. Koleksi bernama **`SIPUS-Del - Week 9 Web Services & REST API`** akan otomatis terpasang dengan struktur 5 folder rapi:
   - Variabel koleksi `{{base_url}}` telah diatur ke `http://localhost:8000/api/v1`.
   - Variabel koleksi `{{soap_url}}` telah diatur ke `http://localhost:8000/soap/sipus`.
5. **Jalankan Skenario Login Terlebih Dahulu:**
   - Buka folder `02 - Autentikasi Stateless (Sanctum Token)` -> jalankan request `1. POST Login Pustakawan Sukses (200 OK)`.
   - Script otomatis Postman (*Tests script*) akan langsung mengekstrak nilai `data.access_token` dan menyimpannya ke variabel `{{token}}`. Seluruh request berikutnya yang membutuhkan otentikasi akan otomatis terotentikasi tanpa salin-tempel manual!

---

## 📸 5. Galeri Bukti Pengujian (Test Verification Gallery)

Berikut adalah panduan dan representasi respons HTTP dari 7 skenario pengujian wajib praktikum:

### (a) Uji Layanan SOAP Bebas Pustaka & `<soap:Fault>`
- **Request Bebas Pustaka (NIM 12S24034 - Bersih):**
  - **Endpoint:** `POST http://localhost:8000/soap/sipus`
  - **Header:** `Content-Type: text/xml; charset=utf-8`
  - **Body XML:**
    ```xml
    <?xml version="1.0" encoding="UTF-8"?>
    <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:m="http://sipus.del.ac.id/soap/clearance">
        <soap:Body>
            <m:CheckClearanceStatusRequest>
                <m:nim_peminjam>12S24034</m:nim_peminjam>
            </m:CheckClearanceStatusRequest>
        </soap:Body>
    </soap:Envelope>
    ```
  - **HTTP Response (`200 OK`):**
    ```xml
    <?xml version="1.0" encoding="UTF-8"?>
    <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:m="http://sipus.del.ac.id/soap/clearance">
        <soap:Header>
            <m:ProviderInfo>SIPUS-Del Legacy SOAP Engine v1.0</m:ProviderInfo>
        </soap:Header>
        <soap:Body>
            <m:CheckClearanceStatusResponse>
                <m:nim_peminjam>12S24034</m:nim_peminjam>
                <m:nama_peminjam>Immanuel Tambunan</m:nama_peminjam>
                <m:active_loans>0</m:active_loans>
                <m:is_cleared>true</m:is_cleared>
            </m:CheckClearanceStatusResponse>
        </soap:Body>
    </soap:Envelope>
    ```
- **Uji Skenario Negatif SOAP Fault (Tag Kosong / XML Cacat):**
  - **HTTP Response (`500 Internal Server Error`):**
    ```xml
    <?xml version="1.0" encoding="UTF-8"?>
    <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Body>
            <soap:Fault>
                <faultcode>soap:Client</faultcode>
                <faultstring>Elemen &lt;nim_peminjam&gt; tidak boleh kosong.</faultstring>
            </soap:Fault>
        </soap:Body>
    </soap:Envelope>
    ```

---

### (b) Otentikasi Stateless Sanctum Bearer Token
- **POST `/api/v1/auth/login` (Sukses 200 OK):**
  ```json
  {
      "success": true,
      "message": "Otentikasi API berhasil. Lampirkan access_token pada header Authorization: Bearer <token>.",
      "data": {
          "token_type": "Bearer",
          "access_token": "1|8x9K...[DISENSOR_UNTUK_KEAMANAN]...M4pQ",
          "user": {
              "id": 1,
              "name": "Pustakawan Del",
              "email": "pustakawan@del.ac.id"
          }
      }
  }
  ```
- **Uji Negatif Kredensial Salah (401 RFC 7807):**
  ```json
  {
      "type": "http://localhost:8000/docs/errors/invalid-credentials",
      "title": "Invalid Credentials",
      "status": 401,
      "detail": "Kombinasi email atau password yang diberikan tidak cocok.",
      "instance": "/api/v1/auth/login"
  }
  ```

---

### (c) GET Katalog Terpaginasi & Hypermedia Controls (HATEOAS)
- **Request:** `GET http://localhost:8000/api/v1/bukus?per_page=2&sort=stok&dir=desc`
- **HTTP Response (`200 OK`):**
  ```json
  {
      "success": true,
      "message": "Daftar koleksi buku perpustakaan berhasil diambil.",
      "data": [
          {
              "id": 1,
              "isbn": "9780132350884",
              "judul": "Clean Code: A Handbook of Agile Software Craftsmanship",
              "penulis": "Robert C. Martin",
              "penerbit": "Prentice Hall",
              "tahun_terbit": 2008,
              "stok": 5,
              "status_ketersediaan": "TERSEDIA",
              "_links": {
                  "self": { "href": "http://localhost:8000/api/v1/bukus/1", "method": "GET" },
                  "referensi_eksternal": { "href": "http://localhost:8000/api/v1/bukus/1/referensi-eksternal", "method": "GET" },
                  "update_full": { "href": "http://localhost:8000/api/v1/bukus/1", "method": "PUT" },
                  "update_partial": { "href": "http://localhost:8000/api/v1/bukus/1", "method": "PATCH" },
                  "delete": { "href": "http://localhost:8000/api/v1/bukus/1", "method": "DELETE" },
                  "pinjam_buku": { "href": "http://localhost:8000/api/v1/peminjamans", "method": "POST" }
              }
          }
      ],
      "meta": {
          "current_page": 1,
          "per_page": 2,
          "total_items": 22,
          "total_pages": 11,
          "links": {
              "first": "http://localhost:8000/api/v1/bukus?page=1",
              "next": "http://localhost:8000/api/v1/bukus?page=2"
          }
      }
  }
  ```
  > **Keindahan HATEOAS:** Perhatikan properti `_links.pinjam_buku`. Link aksi peminjaman hanya hadir saat `stok > 0`. Begitu stok bernilai `0`, link tersebut otomatis hilang dari representasi JSON.

---

### (d) POST Buku Baru (201 Created + Header Location)
- **Request:** `POST http://localhost:8000/api/v1/bukus`
- **Header:** `Authorization: Bearer <token>`
- **Response Headers:**
  - `HTTP/1.1 201 Created`
  - `Location: http://localhost:8000/api/v1/bukus/23`
  - `Content-Type: application/json`

---

### (e) Pembuktian Semantik Perbedaan Nyata PUT vs PATCH
- **Operasi PUT (Full Replacement):** Klien wajib mengirim seluruh representasi data buku secara utuh. Jika field opsional `sinopsis` tidak disertakan dalam body payload, nilainya direset menjadi `null` di basis data.
- **Operasi PATCH (Partial Update):** Klien cukup mengirimkan field yang ingin diubah (misalnya `{"stok": 15}`). Atribut judul, penulis, penerbit, tahun terbit, dan sinopsis tidak tersentuh sama sekali di basis data.

---

### (f) Transaksi Sirkulasi ACID (`DB::transaction` + `lockForUpdate`)
1. **Peminjaman Sukses (`201 Created`):**
   - Transaksi mengunci baris buku dengan *Pessimistic Locking* (`lockForUpdate()`), memotong stok (-1), dan menerbitkan baris data peminjaman baru beserta header `Location: /api/v1/peminjamans/{id}`.
2. **Penolakan Duplikasi Peminjaman (`409 Conflict`):**
   - Jika mahasiswa mencoba meminjam kembali buku yang sedang aktif dipinjamnya, server mengembalikan respon konflik:
   ```json
   {
       "type": "http://localhost:8000/docs/errors/duplicate-active-loan",
       "title": "Duplicate Active Loan Conflict",
       "status": 409,
       "detail": "Mahasiswa dengan NIM '12S24034' masih meminjam buku 'Clean Code' dan belum dikembalikan.",
       "instance": "/api/v1/peminjamans"
   }
   ```
3. **Penolakan Stok Habis (`422 Unprocessable Content`):**
   - Jika `stok < 1`, transaksi dibatalkan dengan format RFC 7807 (`title: Book Out of Stock`, `status: 422`).
4. **Pengembalian Buku (`PATCH /peminjamans/{id}/kembalikan`):**
   - Mengubah status peminjaman menjadi `dikembalikan`, mencatat `tanggal_kembali`, dan mengembalikan stok fisik buku (+1). Menolak percobaan pengembalian ulang dengan status `409 Conflict`.

---

### (g) Pengayaan Metadata Buku via OpenLibrary REST API (REST Consumer)
- **Request:** `GET http://localhost:8000/api/v1/bukus/1/referensi-eksternal`
- **HTTP Response (`200 OK`):**
  ```json
  {
      "success": true,
      "message": "Data referensi metadata eksternal berhasil diambil dari OpenLibrary REST API.",
      "data": {
          "buku_id": 1,
          "isbn": "9780132350884",
          "judul_lokal": "Clean Code: A Handbook of Agile Software Craftsmanship",
          "status_eksternal": "FOUND",
          "sumber": "OpenLibrary REST API",
          "metadata_eksternal": {
              "title": "Clean Code",
              "subtitle": "A Handbook of Agile Software Craftsmanship",
              "authors": ["Robert C. Martin"],
              "publishers": ["Prentice Hall"],
              "publish_date": "August 1, 2008",
              "number_of_pages": 464,
              "cover_url": "https://covers.openlibrary.org/b/id/12345-L.jpg",
              "preview_url": "https://openlibrary.org/books/OL12345M"
          }
      }
  }
  ```

---

## 🔒 6. Disiplin Keamanan & Konvensi Git Commits

Seluruh tahapan implementasi penugasan mandiri Minggu 9 dikelola mengikuti spesifikasi **Conventional Commits**:
1. `feat(soap): add concise LibrarySoapController and WSDL contract for clearance check`
2. `feat(api-core): install laravel api routing, ApiResponse trait, and RFC 7807 exception handler`
3. `feat(api-auth): implement stateless Sanctum bearer token login and revocation`
4. `feat(api-books): build BukuApiController and BukuResource with dynamic HATEOAS links`
5. `feat(api-loans): implement ACID book borrowing and return REST endpoints with lockForUpdate`
6. `feat(api-consumer): integrate external book metadata lookup via Laravel Http client`
7. `docs(contract): add OpenAPI 3.0 specification, Postman collection, and test report in README`

> [!NOTE]
> Repositori mematuhi aturan keamanan mutlak: berkas `.env` dan token rahasia tidak pernah di-commit ke publik (*zero secret leaks*).
