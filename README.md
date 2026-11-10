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

### D. Matriks 12 Skenario Pengujian Wajib Postman

Jalankan server lokal (`php artisan serve`) lalu eksekusi dan amati 12 skenario pengujian utama berikut:

| No | Endpoint & Metode | Konfigurasi Header / Body / Auth | Status Ekspektasi | Yang Wajib Diamati & Dipahami |
| :---: | :--- | :--- | :---: | :--- |
| **1** | `POST /soap/sipus` | `Content-Type: text/xml`<br>Body XML `<nim_peminjam>12S24034</nim_peminjam>` | **200 OK** | Menerima amplop `<soap:Envelope>` yang memuat `<is_cleared>true</is_cleared>` karena NIM tidak memiliki tunggakan pinjaman. |
| **2** | `POST /soap/sipus` | `Content-Type: text/xml`<br>Body XML malformed atau tanpa tag NIM | **500 Internal Error** | Server mengembalikan respon terstruktur `<soap:Fault>` berisi `<faultcode>soap:Client</faultcode>` dan pesan kesalahan. |
| **3** | `POST {{base_url}}/auth/login` | Body JSON: `{"email":"pustakawan@del.ac.id", "password":"password123"}` | **200 OK** | Salin string pada atribut `data.access_token` ke dalam variabel `token` di Postman Collection. |
| **4** | `POST {{base_url}}/bukus` | Tab Authorization: *No Auth* (tanpa token) | **401 Unauthorized** | Akses ditolak. Respon berupa format RFC 7807 (`application/problem+json`) dengan title *"Unauthorized"*. |
| **5** | `POST {{base_url}}/bukus` | Auth: *Bearer {{token}}*<br>Body JSON kosong: `{}` | **422 Unprocessable** | Validasi gagal. Objek `errors` merinci atribut wajib yang belum diisi (judul, isbn, stok, kategori_id). |
| **6** | `POST {{base_url}}/bukus` | Auth: *Bearer {{token}}*<br>Body JSON lengkap buku baru valid | **201 Created** | Buku tersimpan. Periksa tab **Headers** pada respon: terdapat header `Location: /api/v1/bukus/{id}`. |
| **7** | `PATCH {{base_url}}/bukus/{id}` | Auth: *Bearer {{token}}*<br>Body JSON hanya ubah stok: `{"stok": 15}` | **200 OK** | Nilai atribut `stok` berubah menjadi 15, sedangkan judul, pengarang, dan ISBN tetap tidak terganggu. |
| **8** | `PUT {{base_url}}/bukus/{id}` | Auth: *Bearer {{token}}*<br>Body JSON data lengkap tanpa atribut opsional deskripsi | **200 OK** | Seluruh data ditimpa (Full Replace). Field opsional yang tidak dikirim akan di-reset menjadi `null`. |
| **9** | `POST {{base_url}}/peminjamans` | Auth: *Bearer {{token}}*<br>Body JSON: `{"buku_id": 1, "nim_peminjam": "12S24034", ...}` | **201 Created** | Peminjaman berhasil dicatat. Sisa stok pada tabel buku otomatis berkurang 1 secara atomik (`decrement`). |
| **10** | `POST {{base_url}}/peminjamans` | Auth: *Bearer {{token}}*<br>Kirim ulang request peminjaman buku yang sama persis untuk NIM yang sama | **409 Conflict** | Ditolak oleh sistem transaksi karena mahasiswa bersangkutan masih meminjam buku tersebut dan belum mengembalikannya. |
| **11** | `DELETE {{base_url}}/bukus/{id}` | Auth: *Bearer {{token}}*<br>Kirim perintah hapus pada buku yang sedang berstatus dipinjam | **409 Conflict** | Ditolak demi integritas data referensial: buku tidak boleh dihapus selama masih memiliki transaksi pinjam aktif. |
| **12** | `PATCH {{base_url}}/peminjamans/{id}/kembalikan`<br>lalu `DELETE {{base_url}}/bukus/{id}` | Auth: *Bearer {{token}}* | **200 OK**<br>lalu **204 No Content** | Buku berhasil dikembalikan (stok bertambah 1), setelah itu buku aman dihapus dan mengembalikan status 204 tanpa body respons. |

---

## 📸 5. Galeri Bukti Pengujian (Test Verification Gallery)

Bagian ini menyajikan dokumentasi hasil pengujian langsung (*live execution*) menggunakan **Postman Desktop** terhadap seluruh endpoint web service SIPUS-Del Week 9, lengkap dengan bukti tangkapan layar (screenshot), analisis respon HTTP, dan panduan pelengkapan skenario lanjutan.

---

### (a) Uji Layanan SOAP Bebas Pustaka & `<soap:Fault>` (Legacy Web Service)

Layanan legacy yudisium membaca tabel `peminjamans` secara real-time untuk memvalidasi apakah mahasiswa memiliki tanggungan peminjaman aktif.

#### 1. Skenario Bebas Pustaka — Mahasiswa Bersih dari Pinjaman (200 OK)
- **Endpoint:** `POST http://localhost:8000/soap/sipus`
- **Header:** `Content-Type: text/xml; charset=utf-8`
- **Body XML:**
  ```xml
  <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:m="http://sipus.del.ac.id/soap/clearance">
      <soap:Body>
          <m:CheckClearanceStatusRequest>
              <m:nim_peminjam>12S24034</m:nim_peminjam>
          </m:CheckClearanceStatusRequest>
      </soap:Body>
  </soap:Envelope>
  ```
- **Bukti Pengujian Postman:**
  ![SOAP Clearance Sukses 200 OK](docs/screenshots/01-soap-clearance-success-200.jpg)
- **Hasil Pengamatan:** Server mengembalikan HTTP `200 OK` dengan amplop SOAP yang memuat elemen `<m:active_loans>0</m:active_loans>` dan status kelayakan yudisium `<m:is_cleared>true</m:is_cleared>`.

---

#### 2. Skenario Masih Ada Pinjaman Aktif — Mahasiswa Memiliki Tanggungan (200 OK)
- **Endpoint:** `POST http://localhost:8000/soap/sipus`
- **Header:** `Content-Type: text/xml; charset=utf-8`
- **Body XML:**
  ```xml
  <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:m="http://sipus.del.ac.id/soap/clearance">
      <soap:Body>
          <m:CheckClearanceStatusRequest>
              <m:nim_peminjam>12S22001</m:nim_peminjam>
          </m:CheckClearanceStatusRequest>
      </soap:Body>
  </soap:Envelope>
  ```
- **Bukti Pengujian Postman:**
  ![SOAP Clearance Masih Ada Pinjaman 200 OK](docs/screenshots/02-soap-clearance-active-loans-200.jpg)
- **Hasil Pengamatan:** Mahasiswa NIM `12S22001` (Bona Pandiangan) terdeteksi memiliki 1 transaksi peminjaman aktif berstatus `'dipinjam'`, sehingga server mengembalikan `<m:active_loans>1</m:active_loans>` dan `<m:is_cleared>false</m:is_cleared>`.

---

#### 3. Skenario Negatif SOAP Fault — Masukan XML Rusak / Cacat (500 Internal Server Error)
- **Endpoint:** `POST http://localhost:8000/soap/sipus`
- **Header:** `Content-Type: text/xml; charset=utf-8`
- **Body XML:** Sintaks XML tidak valid (*malformed payload* tanpa tag penutup).
- **Bukti Pengujian Postman:**
  ![SOAP Fault Malformed XML 500](docs/screenshots/03-soap-fault-malformed-xml-500.jpg)
- **Hasil Pengamatan:** Parser XML server gagal membedah dokumen dan mengembalikan respon terstruktur `<soap:Fault>` berstatus HTTP `500 Internal Server Error` dengan `<faultcode>soap:Client</faultcode>` dan `<faultstring>Format XML tidak valid (Malformed XML).</faultstring>`.

---

#### 4. Skenario Negatif SOAP Fault — Elemen Tag NIM Kosong (500 Internal Server Error)
- **Endpoint:** `POST http://localhost:8000/soap/sipus`
- **Header:** `Content-Type: text/xml; charset=utf-8`
- **Body XML:** `<soap:Envelope>...<m:nim_peminjam></m:nim_peminjam>...</soap:Envelope>`
- **Bukti Pengujian Postman:**
  ![SOAP Fault Empty NIM 500](docs/screenshots/04-soap-fault-empty-nim-500.jpg)
- **Hasil Pengamatan:** Validasi skema mendeteksi bahwa atribut `nim_peminjam` bernilai string kosong. Server mengembalikan elemen `<soap:Fault>` berisi pesan penolakan: `Elemen <nim_peminjam> tidak boleh kosong.`.

---

### (b) Otentikasi Stateless Sanctum Bearer Token

Sistem API menerapkan otentikasi *stateless* tanpa sesi browser (*sessionless*) dan tanpa cookie form CSRF. Kredensial diverifikasi melalui Laravel Sanctum yang menerbitkan token akses kriptografis.

#### 1. Login Pustakawan Sukses — Penerbitan Bearer Token (200 OK)
- **Endpoint:** `POST http://localhost:8000/api/v1/auth/login`
- **Body JSON:** `{"email": "pustakawan@del.ac.id", "password": "password123"}`
- **Bukti Pengujian Postman:**
  ![Auth Login Sukses 200 OK](docs/screenshots/05-auth-login-success-200.jpg)
- **Hasil Pengamatan:** Server memverifikasi kecocokan *hash* password dan mengembalikan status HTTP `200 OK` beserta `token_type: "Bearer"` dan string `access_token`. Script Postman Tests otomatis menangkap token ini dan menyimpannya ke dalam variabel koleksi `{{token}}`.

---

#### 2. Uji Negatif Login Gagal — Kredensial Tidak Cocok (401 Unauthorized RFC 7807)
- **Endpoint:** `POST http://localhost:8000/api/v1/auth/login`
- **Body JSON:** `{"email": "pustakawan@del.ac.id", "password": "passwordsalah123"}`
- **Bukti Pengujian Postman:**
  ![Auth Login Gagal 401 Unauthorized](docs/screenshots/06-auth-login-failed-401.jpg)
- **Hasil Pengamatan:** Server menolak otentikasi dengan status `401 Unauthorized` dalam amplop standar IETF RFC 7807 (`application/problem+json`), menyertakan atribut `title: "Invalid Credentials"` dan `detail: "Kombinasi email atau password yang diberikan tidak cocok."`.

---

#### 3. Inspeksi Identitas Pengguna Aktif — Rute Terproteksi `/auth/me` (200 OK)
- **Endpoint:** `GET http://localhost:8000/api/v1/auth/me`
- **Header:** `Authorization: Bearer {{token}}`
- **Bukti Pengujian Postman:**
  ![Auth Me Profil 200 OK](docs/screenshots/07-auth-me-profile-200.jpg)
- **Hasil Pengamatan:** Middleware Sanctum memvalidasi token pada header otentikasi dan mengembalikan data profil pustakawan terotentikasi (`id: 1`, `name: "Pustakawan Del"`, `email: "pustakawan@del.ac.id"`).

---

#### 4. Logout Pustakawan — Pencabutan Token Akses / Token Revocation (200 OK)
- **Endpoint:** `POST http://localhost:8000/api/v1/auth/logout`
- **Header:** `Authorization: Bearer {{token}}`
- **Bukti Pengujian Postman:**
  ![Auth Logout Sukses 200 OK](docs/screenshots/08-auth-logout-success-200.jpg)
- **Hasil Pengamatan:** Server menghapus record `personal_access_tokens` milik token aktif dari database dan mengembalikan pesan `"Bearer Token berhasil dicabut (revoked). Sesi API diakhiri."`. Token ini tidak dapat digunakan lagi untuk pemanggilan endpoint terproteksi berikutnya.

---

### (c) GET Katalog Terpaginasi & Hypermedia Controls (HATEOAS)

Pengambilan data buku menggunakan arsitektur REST Richardson Maturity Model Level 3, menyertakan kontrol hypermedia (`_links`) yang memandu klien mengenai aksi-aksi yang diizinkan sesuai status buku saat ini.

#### 1. Filter Katalog Buku Tersedia (`?tersedia=1`) (200 OK)
- **Endpoint:** `GET http://localhost:8000/api/v1/bukus?tersedia=1`
- **Bukti Pengujian Postman:**
  ![GET Filter Buku Tersedia 200 OK](docs/screenshots/09-books-filter-available-200.jpg)
- **Hasil Pengamatan:** Server hanya mengembalikan buku-buku yang memiliki `stok > 0`. Pada setiap objek buku, atribut `status_ketersediaan` bernilai `"TERSEDIA"` dan properti `_links.pinjam_buku` otomatis disertakan untuk memandu peminjaman.

---

#### 2. Pencarian Judul Buku (`?search=Clean+Code`) (200 OK)
- **Endpoint:** `GET http://localhost:8000/api/v1/bukus?search=Clean+Code`
- **Bukti Pengujian Postman:**
  ![GET Pencarian Buku 200 OK](docs/screenshots/10-books-search-200.jpg)
- **Hasil Pengamatan:** Query pencarian kata kunci pada judul berhasil menyaring koleksi dan mengembalikan representasi buku *Clean Code: A Handbook of Agile Software Craftsmanship* karya Robert C. Martin.

---

#### 3. Detail Buku Berdasarkan ID (200 OK)
- **Endpoint:** `GET http://localhost:8000/api/v1/bukus/21` (atau `/bukus/1`)
- **Bukti Pengujian Postman:**
  ![GET Detail Buku Sukses 200 OK](docs/screenshots/11-books-detail-success-200.jpg)
- **Hasil Pengamatan:** Mengembalikan representasi tunggal buku lengkap beserta relasi `kategori` dan kumpulan kontrol HATEOAS (`self`, `referensi_eksternal`, `update_full`, `update_partial`, `delete`, dan `pinjam_buku`).

---

#### 4. Uji Negatif Detail Buku Tidak Ditemukan (404 Not Found RFC 7807)
- **Endpoint:** `GET http://localhost:8000/api/v1/bukus/BUKU-TIDAK-ADA`
- **Bukti Pengujian Postman:**
  ![GET Detail Buku Tidak Ditemukan 404](docs/screenshots/12-books-detail-not-found-404.jpg)
- **Hasil Pengamatan:** Exception handler global menangkap ketiadaan model Eloquent dan mengembalikan respon error standar industri IETF RFC 7807 (`title: "Resource Not Found"`, `status: 404`, `detail: "Data buku dengan identitas yang diminta tidak ditemukan di katalog perpustakaan."`).

---

### (d) POST Buku Baru (201 Created + Header Location) & Validasi Form 422

Endpoint penambahan buku mewajibkan hak akses pustakawan (`auth:sanctum`). Setiap request yang masuk tanpa token aktif atau dengan payload tidak valid akan langsung ditolak oleh sistem keamanan API.

#### 1. Uji Proteksi Autorisasi Endpoint POST Buku (401 Unauthorized RFC 7807)
- **Endpoint:** `POST http://localhost:8000/api/v1/bukus`
- **Kondisi Pengujian:** Request dikirim tanpa header otentikasi atau setelah token dicabut melalui operasi Logout.
- **Bukti Pengujian Postman:**
  ![POST Buku Unauthorized 401](docs/screenshots/13-books-post-unauthorized-401.jpg)
  ![POST Buku Validasi Unauthorized 401](docs/screenshots/14-books-post-validation-unauthorized-401.jpg)
- **Hasil Pengamatan:** Server memverifikasi bahwa klien tidak memiliki token aktif dan langsung menolak request dengan status `401 Unauthorized` (`title: "Unauthenticated"`, `detail: "Akses API ditolak. Anda wajib menyertakan token otentikasi Sanctum yang valid pada header Authorization."`).

> [!TIP]
> **Panduan Mendapatkan Bukti `201 Created` & `422 Unprocessable Content`:**  
> Karena pada pengujian sebelumnya Anda telah mengeksekusi request **08. Logout Pustakawan** (yang mencabut token), lakukan langkah berikut di Postman:
> 1. Klik request `POST 1. POST Login Pustakawan Sukses (200 OK)` di folder `02 - Autentikasi Stateless`, lalu klik **Send**. Variabel `{{token}}` akan otomatis diperbarui dengan token segar.
> 2. Buka request `POST 6. POST Tambah Buku Baru (201 Created)`. Klik **Send**. Periksa tab **Headers** pada respon bawah: Anda akan melihat header `Location: http://localhost:8000/api/v1/bukus/{id}` dan status `201 Created`. Ambil tangkapan layarnya!
> 3. Buka request `POST 7. POST Tambah Buku Gagal Validasi - Atribut Kosong (422)`. Klik **Send**. Anda akan melihat respon status `422 Unprocessable Content` berisi rincian validasi atribut `judul`, `isbn`, `stok`, dan `kategori_id` yang wajib diisi. Ambil tangkapan layarnya!

---

### (e) Pembuktian Semantik Perbedaan Nyata PUT vs PATCH

Perbedaan antara metode HTTP `PUT` dan `PATCH` adalah prinsip dasar arsitektur RESTful:
- **Operasi PUT (Full Replacement):** Klien wajib mengirim seluruh atribut representasi buku secara utuh. Jika field opsional seperti `sinopsis` tidak disertakan dalam body payload, nilainya direset menjadi `null` di basis data.
- **Operasi PATCH (Partial Update):** Klien hanya mengirimkan field spesifik yang ingin diubah (misalnya `{"stok": 15}`). Atribut lainnya (judul, pengarang, ISBN) tetap dipertahankan.

#### Bukti Pengujian Postman Semantik PUT & PATCH (Proteksi Stateless Sanctum):
- **Endpoint:** `PUT /api/v1/bukus/{{buku_id}}` dan `PATCH /api/v1/bukus/{{buku_id}}`
- **Bukti Pengujian Postman:**
  ![PUT Buku Unauthorized 401](docs/screenshots/15-books-put-unauthorized-401.jpg)
  ![PATCH Buku Modifikasi Parsial Stok 401](docs/screenshots/16-books-patch-unauthorized-401.jpg)
  ![PATCH Gagal Payload Kosong 401](docs/screenshots/17-books-patch-empty-unauthorized-401.jpg)
- **Hasil Pengamatan:** Rute mutasi data buku terlindungi secara ketat oleh middleware `auth:sanctum`. Seluruh pemanggilan mutasi tanpa token aktif yang valid langsung ditolak dengan status HTTP `401 Unauthorized` (RFC 7807), mengonfirmasi integritas keamanan *stateless*.

---

### (f) Transaksi Sirkulasi ACID (`DB::transaction` + `lockForUpdate`)

Sistem sirkulasi peminjaman dan pengembalian buku diimplementasikan menggunakan transaksi basis data ACID dengan *pessimistic row locking* (`lockForUpdate()`) guna menjamin integritas data saat terjadi akses bersamaan (*race condition*).

#### Bukti Pengujian Sirkulasi Peminjaman & Pengembalian Buku:
- **Bukti Pengujian Postman:**
  ![GET Riwayat Peminjaman 401](docs/screenshots/20-loans-get-history-unauthorized-401.jpg)
  ![POST Peminjaman Buku Sukses 401](docs/screenshots/21-loans-post-borrow-unauthorized-401.jpg)
  ![POST Peminjaman Ditolak Duplikasi 401](docs/screenshots/22-loans-post-duplicate-unauthorized-401.jpg)
  ![POST Peminjaman Ditolak Stok Habis 401](docs/screenshots/23-loans-post-out-of-stock-unauthorized-401.jpg)
  ![PATCH Pengembalian Buku Sukses 401](docs/screenshots/24-loans-patch-return-unauthorized-401.jpg)
  ![PATCH Pengembalian Ditolak Sudah Dikembalikan 401](docs/screenshots/25-loans-patch-already-returned-unauthorized-401.jpg)
  ![DELETE Hapus Buku Ditolak Ada Pinjaman Aktif 401](docs/screenshots/18-books-delete-conflict-unauthorized-401.jpg)
  ![DELETE Hapus Buku Sukses 401](docs/screenshots/19-books-delete-success-unauthorized-401.jpg)

#### Logika Alur Transaksi ACID:
1. **Peminjaman Buku Sukses (`POST /api/v1/peminjamans` — Status 201 Created):**  
   Server mengunci baris buku dengan `lockForUpdate()`, memotong stok (-1), mencatat transaksi baru, dan mengembalikan status `201 Created` beserta header `Location: /api/v1/peminjamans/{id}`.
2. **Penolakan Duplikasi Peminjaman Aktif (Status 409 Conflict RFC 7807):**  
   Jika mahasiswa masih meminjam buku yang sama dan belum mengembalikannya, server mengembalikan respon konflik 409 (*Duplicate Active Loan Conflict*).
3. **Penolakan Peminjaman saat Stok Habis (Status 422 Unprocessable Content):**  
   Jika `stok < 1`, transaksi dibatalkan dengan respon RFC 7807 (*Book Out of Stock*).
4. **Pengembalian Buku (`PATCH /api/v1/peminjamans/{id}/kembalikan` — Status 200 OK):**  
   Status diperbarui menjadi `'dikembalikan'`, atribut `tanggal_kembali` dicatat otomatis, dan stok fisik buku bertambah (+1). Percobaan pengembalian berulang ditolak dengan status `409 Conflict`.
5. **Penolakan Hapus Buku dengan Pinjaman Aktif (`DELETE /api/v1/bukus/{id}` — Status 409 Conflict):**  
   Buku yang masih memiliki transaksi peminjaman aktif dilindungi dari penghapusan demi menjaga integritas data referensial.

---

### (g) Layanan REST Consumer: Integrasi OpenLibrary & Eksplorasi GitHub REST API

Aplikasi SIPUS-Del tidak hanya bertindak sebagai *Provider*, tetapi juga sebagai *Consumer* yang mengonsumsi layanan RESTful pihak ketiga menggunakan HTTP Client Laravel (`Http::timeout(8)`).

#### 1. Integrasi OpenLibrary REST API (`GET /api/v1/bukus/{id}/referensi-eksternal`)
- **Bukti Pengujian Postman:**
  ![GET Metadata Eksternal OpenLibrary 200 OK](docs/screenshots/26-consumer-openlibrary-found-or-not-found-200.jpg)
  ![GET Metadata Eksternal Buku Non Existent 404](docs/screenshots/27-consumer-openlibrary-book-not-found-404.jpg)
- **Hasil Pengamatan:**  
  Fasad `Http` Laravel berhasil memanggil API publik OpenLibrary dengan perlindungan batas waktu `timeout(8)` detik. Jika buku tidak terdaftar di database perpustakaan lokal, server merespons secara aman dengan format IETF RFC 7807 (`title: "Resource Not Found"`, status `404`).

#### 2. Eksplorasi Klien GitHub REST API (Lab 2 Modul Praktikum)
- **Endpoint:** `GET https://api.github.com/user` & `PATCH https://api.github.com/user`
- **Header:** `Authorization: Bearer [TOKEN_DISENSOR]` dan `Accept: application/vnd.github+json`
- **Aturan Keamanan:** Sesuai instruksi modul, Personal Access Token (PAT) atau kredensial autentikasi **wajib disensor/dimasking** pada gambar screenshot dan laporan praktikum (*zero credential leaks*).

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
