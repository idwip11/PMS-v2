# HomiQ PMS — Guesthouse & Property Management System

[![PHP Version](https://img.shields.io/badge/PHP-8.2%20%7C%208.3-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.7-EF4223?style=flat&logo=codeigniter&logoColor=white)](https://codeigniter.com/)
[![MySQL](https://img.shields.io/badge/MySQL-8.4%20%7C%20MariaDB-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3.3-7952B3?style=flat&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![HTMX](https://img.shields.io/badge/HTMX-2.0.4-3366CC?style=flat)](https://htmx.org/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

**HomiQ PMS** adalah sistem informasi manajemen properti (*Property Management System*) terpadu berbasis web yang dirancang khusus untuk operasional harian *guesthouse* multi-unit. Sistem ini menghubungkan seluruh siklus operasional penginapan: mulai dari reservasi kamar multi-unit, penerimaan tamu (*check-in/check-out*), pengarsipan KTP, housekeeping, pemeliharaan aset (*maintenance* preventif & korektif), pengelolaan inventaris logistik, hingga pembukuan keuangan perusahaan dan pembagian hasil transparan untuk **Owner/Investor Guesthouse**.

---

## 📌 Daftar Isi

1. [Tentang Proyek](#-tentang-proyek)
2. [Tech Stack yang Digunakan](#-tech-stack-yang-digunakan)
3. [Fitur & Modul Utama](#-fitur--modul-utama)
4. [Matriks Hak Akses Pengguna (RBAC)](#-matriks-hak-akses-pengguna-rbac)
5. [Struktur Direktori Proyek](#-struktur-direktori-proyek)
6. [Prasyarat Sistem](#-prasyarat-sistem)
7. [Panduan Instalasi & Menjalankan Aplikasi](#-panduan-instalasi--menjalankan-aplikasi)
   - [Opsi 1: Menjalankan Lokal Cepat (macOS / XAMPP)](#opsi-1-menjalankan-lokal-cepat-macos--xampp)
   - [Opsi 2: Menjalankan Menggunakan Docker Compose](#opsi-2-menjalankan-menggunakan-docker-compose)
   - [Opsi 3: Instalasi Manual](#opsi-3-instalasi-manual)
8. [CLI Commands & Scheduled Tasks (Cron)](#-cli-commands--scheduled-tasks-cron)
9. [Dokumentasi Tambahan](#-dokumentasi-tambahan)

---

## 📖 Tentang Proyek

Operasional *guesthouse* sering kali menghadapi tantangan pencatatan manual yang terpecah antara meja resepsionis, tim kebersihan, teknisi, dan laporan keuangan ke pemilik modal (*owner*).

**HomiQ PMS** dibangun untuk menjawab kebutuhan tersebut dengan menyediakan:
- **Operasional Front Desk Cepat & Akurat**: Pembuatan reservasi single maupun multi-kamar, pencegahan *overbooking*, dan kompresi berkas KTP otomatis di sisi peramban (*client-side*).
- **Transparansi Keuangan Owner**: Memisahkan secara ketat antara pendapatan sewa kamar (yang dibagi hasil dengan investor) dan pendapatan layanan internal seperti sarapan, laundry, pijat, serta sewa Extra Bed (yang menjadi hak pengelola).
- **Sistem Perawatan Properti Terstruktur (CMMS)**: Pencatatan aset per kamar, tiket *work order* perbaikan, riwayat pemakaian suku cadang, dan jadwal *preventive maintenance* berkala.
- **Efisiensi Sumber Daya**: Antarmuka ringan memanfaatkan kombinasi Bootstrap 5 dan HTMX untuk pembaruan komponen secara asinkron tanpa beban berlebih pada server *shared hosting* / VPS.

---

## 🛠️ Tech Stack yang Digunakan

Aplikasi ini dibangun menggunakan arsitektur **Full-Stack PHP MVC** modern dengan fokus pada performa tinggi, efisiensi memori, dan kemudahan penerapan di berbagai lingkungan hosting.

### 1. Backend Core & Framework
- **PHP 8.2+ / 8.3**: Memanfaatkan fitur PHP modern (typed properties, match expressions, constructor promotion, attributes).
- **CodeIgniter 4.7**: Framework MVC PHP yang sangat ringan, cepat, aman, dengan dukungan filter rute, proteksi CSRF otomatis, session handling, validasi input, serta database migration & seeder.
- **Spark CLI**: Antarmuka baris perintah bawaan CodeIgniter untuk migrasi, seeding, dan otomasi *background task*.

### 2. Basis Data (Database)
- **MySQL 8.0+ / 8.4** atau **MariaDB 10.4+**: Basis data relasional dengan indeks performa untuk analitik reservasi.
- **MySQLi Driver**: Driver koneksi native berkinerja tinggi dengan konfigurasi charset `utf8mb4`.

### 3. Frontend & Antarmuka Pengguna
- **HTML5 & Semantic Structure**: Tata letak rapi, aksesibel, dan terstruktur.
- **Bootstrap 5.3.3**: Framework CSS responsif untuk komponen layout, modal dialog, formulir, dan grid sistem.
- **Vanilla CSS (`public/css/app.css`)**: Kustomisasi tema antarmuka modern, sistem palet warna, overlay sidebar mobile, badge status kamar, dan kartu metrik dashboard.
- **HTMX 2.0.4**: Mengaktifkan interaktivitas dinamis (AJAX swaps, server partial updates) langsung dari atribut HTML tanpa memerlukan framework SPA yang berat.
- **Vanilla JavaScript (`public/js/app.js`)**: Logika interaktif antarmuka, kalkulasi dinamis reservasi, modal handler, serta modul kompresi gambar (*canvas compression*) otomatis untuk foto KTP dan bukti pembayaran transfer (maks ~200 KB).
- **Chart.js 4.4.7 (UMD)**: Visualisasi grafik interaktif pada Dashboard (Revenue vs Expense) dan modul Marketing Analytics.

### 4. Pelaporan & Utilitas Dokumen
- **Dompdf 3.1**: Pustaka rendering HTML/CSS ke dokumen PDF untuk mencetak invoice reservasi dan dokumen tamu secara rapi.
- **Export Engine**: Ekspor data laporan keuangan, audit data, dan reservasi ke format spreadsheet (CSV/Excel).

### 5. DevOps, Server & Kontainerisasi
- **Docker & Docker Compose**: Konfigurasi multi-container (`web`, `mysql`, `phpmyadmin`) siap pakai untuk pengujian dan staging terisolasi.
- **Bash Script (`dev-local.sh`)**: Skrip otomasi untuk menjalankan MySQL XAMPP, phpMyAdmin lokal, dan PHP development server secara instan.
- **Timezone**: Dikonfigurasi default pada zona waktu operasional `Asia/Makassar` (WITA).

---

## 🚀 Fitur & Modul Utama

### 1. Front Desk & Reservasi Kamar
- **Multi-Room Booking**: Memungkinkan pemesanan satu atau banyak kamar sekaligus dalam satu *booking group* terpadu dengan ID reservasi per kamar yang terhubung.
- **Pengecekan Ketersediaan Real-Time**: Kamar yang telah terpesan pada rentang tanggal tertentu atau sedang berstatus *Maintenance* otomatis terkunci dan tidak dapat dipilih.
- **Unggah & Kompresi Foto KTP**: Kompresi otomatis gambar KTP tamu di peramban hingga berukuran maksimal ~200 KB sebelum diunggah ke server, menjaga kecepatan transfer data dan kapasitas hosting.
- **Check-In & Check-Out Terintegrasi**:
  - Saat *Check-In*, sistem mencatat konsumsi *standard amenity pack* di inventaris.
  - Saat *Check-Out*, status kamar secara otomatis beralih menjadi **Dirty** untuk dibersihkan tim housekeeping.
- **Layanan Tambahan (Add-On Services)**: Mendukung pesanan Early Check-In, Late Checkout, Breakfast, Extra Bed, Laundry, dan Massage.
- **Manajemen Pembayaran**: Pencatatan metode bayar Tunai, Transfer Bank (dengan unggah bukti pembayaran), OTA (Booking.com settlement), status DP, maupun Lunas.
- **Manajemen Pembatalan Fleksibel**: Pembatalan dapat dilakukan untuk satu kamar tertentu atau seluruh kamar dalam grup booking. Status dana yang terdampak diubah menjadi **Kredit/refund pending** tanpa merusak riwayat transaksi.
- **Cetak Invoice PDF**: Generator invoice resmi dalam format PDF yang siap dicetak atau dikirimkan ke tamu.

### 2. Room Map (Peta Okupansi)
- Visualisasi matriks kalender okupansi kamar per guesthouse dan per bulan.
- Pembedaan status kamar (*Available, Occupied, Dirty, Maintenance*) dan status pembayaran (*Lunas, DP, Belum Bayar, Terekonsiliasi*) dengan indikator visual yang jelas.
- Reservasi yang dibatalkan secara otomatis membebaskan blokir kamar pada peta.

### 3. Housekeeping & Kebersihan
- **Status Kamar**: Pemantauan status kebersihan kamar (*Clean, Dirty, Inspected*).
- **Cleaning Tasks**: Penugasan tugas pembersihan kamar harian untuk tim staf.
- **Akses Khusus**: Petugas kebersihan memiliki alur kerja fokus yang langsung mengarah ke modul ini tanpa gangguan menu administratif lainnya.

### 4. Operasional, Aset & Pemeliharaan (CMMS)
- **Work Order Maintenance**: Tiket keluhan atau perbaikan fasilitas kamar (alur status: *Unassigned, Assigned, In Progress, On Hold, Completed*).
- **Preventive Maintenance Schedules**: Penjadwalan perawatan berkala (misal: servis AC berkala, pengecekan water heater).
- **Manajemen Aset Kamar**: Pendataan inventaris aset tetap di setiap kamar (merek, nomor seri, kondisi).
- **Log Suku Cadang & Waktu Kerja**: Pencatatan pemakaian suku cadang dari inventaris dan durasi pengerjaan teknisi.
- **Lampiran Foto**: Dokumentasi visual kondisi kerusakan sebelum dan bukti hasil perbaikan sesudah pekerjaan selesai.

### 5. Inventaris, Extra Bed & Pengeluaran
- **Manajemen Stok**: Tracking persediaan amenities kamar, perlengkapan operasional, dan spare parts maintenance.
- **Order Cepat Vendor via WhatsApp**: Tombol instan untuk menghubungi vendor perlengkapan kamar via tautan WhatsApp API saat stok menipis.
- **Extra Bed Management**: Monitoring ketersediaan fisik Extra Bed dan alokasi tanggal pemakaian per kamar untuk mencegah *double-booking* kasur tambahan.
- **Pencatatan Pengeluaran (Expense Tracker)**: Catatan beban operasional per guesthouse, audit log perubahan data, upload bukti kuitansi, dan monitoring anggaran (*budgeting*).

### 6. Keuangan & Bagi Hasil Owner/Investor
- **Keuangan Perusahaan**: Pencatatan arus kas total (*cash flow*), pemasukan, pengeluaran, laba bersih, serta status *outstanding*.
- **Rekonsiliasi Kas**: Penandaan verifikasi transaksi antara mutasi bank dan pembukuan resepsionis.
- **Laporan Keuangan Khusus Owner GH (Multi-Tenancy View)**:
  - Laporan terisolasi per unit guesthouse (GH 1 s.d. GH 5).
  - **Logika Bagi Hasil yang Adil**:
    - *Pendapatan Owner*: Hanya berasal dari penyewaan kamar dan *charge* tamu (Early Check-In / Late Checkout).
    - *Eksklusi Layanan*: Pendapatan sarapan, extra bed, laundry, dan massage menjadi hak penuh pengelola guesthouse dan dikeluarkan dari bagi hasil owner.
    - *Kalkulasi DP Proporsional*: Pembayaran uang muka dihitung secara proporsional berdasarkan komposisi tagihan.
  - Pengurangan beban pengeluaran operasional guesthouse sebelum penentuan laba bersih yang dibagikan sesuai persentase kesepakatan kerjasama.
- **Ekspor Excel**: Seluruh laporan keuangan dapat diunduh dalam bentuk file spreadsheet.

### 7. Marketing Analytics & Forecasting
- **Metrik Kinerja Properti**: Analisis *Occupancy Rate*, *Rooms Rented*, *Average Length of Stay* (ALOS), dan *Average Daily Rate* (ADR).
- **Distribusi Booking Window**: Analisis jarak pemesanan terhadap tanggal kedatangan (H-0 s.d. >H-30) untuk optimasi strategi promosi.
- **Cancellation Analytics**: Evaluasi tingkat pembatalan, tren waktu pembatalan, dan nilai tagihan yang tertunda.
- **Forecasting Deret Waktu**: Proyeksi kebutuhan kamar-malam untuk horizon 6 bulan ke depan berbasis data historis minimal 12 bulan.

---

## 🔐 Matriks Hak Akses Pengguna (RBAC)

Aplikasi menerapkan sistem izin bertingkat (*Role-Based Access Control*) untuk memastikan keamanan data dan privasi finansial:

| Peran (Role) | Dashboard | Finance Perusahaan | Keuangan Owner | Analytics | Operasional | Front Desk | Housekeeping | Settings | Support |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Admin** | Ya *(sensor finansial)* | ❌ | ❌ | Ya | Ya | Ya | Ya | Ya | Ya |
| **Manager** | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya |
| **Finance** | Ya | Ya | Ya | Ya | Ya | Ya | Ya | ❌ | Ya |
| **Marketing** | Ya | ❌ | ❌ | Ya | ❌ | Ya | Ya | ❌ | Ya |
| **Operational** | Ya | Ya | Ya | Ya | Ya | Ya | Ya | ❌ | Ya |
| **Housekeeping** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | Ya *(fokus)* | ❌ | ❌ |
| **Staff** | Ya *(sensor finansial)* | ❌ | ❌ | Ya | ❌ | Ya | Ya | ❌ | Ya |
| **Owner GH 1–5**| ❌ | ❌ | Ya *(terkunci per GH)* | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Owner HS Mitra**| ❌ | ❌ | Ya *(terkunci Homestay Mitra)* | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Owner Kos Mitra**| ❌ | ❌ | Ya *(terkunci Kos Mitra)* | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Atmin (Legacy)**| Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya |

> ℹ️ **Catatan Keamanan Penting**:
> - Pada peran **Admin** dan **Staff**, angka *Revenue* dan *Net Profit* pada Dashboard disensor otomatis demi kerahasiaan neraca keuangan bisnis.
> - Akun **Owner** (Owner GH 1–5, Owner HS Mitra, Owner Kos Mitra) secara ketat dikunci pada properti/guesthouse masing-masing di level filter rute dan query database; pergantian parameter URL tidak dapat membocorkan data properti lain dan akun tidak dapat membuka menu operasional umum.

---

## 📁 Struktur Direktori Proyek

```text
├── app/
│   ├── Commands/              # Perintah kustom CLI CodeIgniter (Spark)
│   │   ├── AuditMarketingAnalytics.php   # Audit integritas data analitik
│   │   └── GenerateDueMaintenance.php    # Otomasi generate work order berkala
│   ├── Config/                # Konfigurasi aplikasi, rute (Routes.php), filter, dll.
│   ├── Controllers/           # Controller MVC (Auth, Dashboard, Finance, Ops, Reservations, dll.)
│   ├── Database/
│   │   ├── Migrations/        # Skema migrasi tabel basis data (24 file migrasi)
│   │   └── Seeds/             # Data awal / Seeder (kamar default, kategori maintenance, admin)
│   ├── Filters/               # Filter keamanan rute & RBAC (AuthFilter, AccessFilter, RoleFilter)
│   ├── Libraries/             # Service logic & domain engine (Maintenance, Analytics, Filter)
│   ├── Models/                # Model database (UserModel, dll.)
│   └── Views/                 # Template antarmuka pengguna
│       ├── components/        # Komponen parsial (sidebar, header, alerts)
│       ├── layouts/           # Template induk (main.php, auth.php)
│       └── pages/             # Tampilan halaman utama (dashboard, reservations, finance, dll.)
├── docker/                    # Konfigurasi lingkungan container
├── docs/                      # Dokumentasi teknis & panduan operasional mendalam
├── public/                    # Document Root web server (Aset statis)
│   ├── css/app.css            # Custom stylesheet & token desain
│   ├── js/app.js              # Klien JavaScript & kompresor gambar
│   └── index.php              # Front controller aplikasi
├── writable/                  # Direktori tulis runtime (uploads, cache, logs, session)
├── docker-compose.yml         # Konfigurasi Docker (PHP Web, MySQL 8.4, phpMyAdmin)
├── dev-local.sh               # Skrip runner lokal macOS / XAMPP
└── composer.json              # Dependensi pustaka PHP
```

---

## 💻 Prasyarat Sistem

Sebelum menjalankan aplikasi, pastikan perangkat atau server telah memenuhi persyaratan berikut:

- **PHP**: Versi **8.2** atau **8.3** (disarankan 8.3).
- **Ekstensi PHP Wajib**:
  - `intl` (Internasionalisasi dan format tanggal/angka)
  - `mbstring` (Manipulasi string multibyte)
  - `mysqli` / `mysqlnd` (Koneksi database MySQL)
  - `gd` (Kompresi & pemrosesan gambar)
  - `fileinfo` (Validasi tipe berkas unggahan)
  - `json` & `zip`
  - `curl` (Kebutuhan HTTP client)
- **Basis Data**: MySQL **8.0+** atau MariaDB **10.4+**.
- **Package Manager**: Composer versi 2.x.
- **Web Server**: Apache (`mod_rewrite` aktif) atau Nginx dengan *Document Root* diarahkan ke subdirektori `/public`.

---

## 🚀 Panduan Instalasi & Menjalankan Aplikasi

### Opsi 1: Menjalankan Lokal Cepat (macOS & XAMPP)

Jika Anda bekerja di lingkungan macOS dengan PHP 8.3 dan XAMPP terpasang:

1. Pastikan dependensi PHP terpasang:
   ```bash
   composer install
   ```
2. Jalankan skrip dev melalui perintah npm:
   ```bash
   npm run dev
   ```
   *Skrip ini akan otomatis memeriksa & mengaktifkan MySQL XAMPP, menyalakan phpMyAdmin di port 8081, dan menjalankan server CodeIgniter di port 8080.*

3. Buka peramban:
   - **Aplikasi**: `http://localhost:8080`
   - **phpMyAdmin**: `http://localhost:8081`

---

### Opsi 2: Menjalankan Menggunakan Docker Compose

Sangat direkomendasikan untuk pengujian lingkungan bersih dan terisolasi tanpa instalasi PHP/MySQL lokal:

1. Salin konfigurasi environment:
   ```bash
   cp .env.example .env
   ```
2. Jalankan container Docker:
   ```bash
   docker compose up -d --build
   ```
3. Lakukan migrasi skema dan pengisian data awal ke container web:
   ```bash
   docker compose exec web php spark migrate
   docker compose exec web php spark db:seed DefaultDataSeeder
   ```
4. Akses layanan:
   - **HomiQ PMS Web**: `http://localhost:8080`
   - **phpMyAdmin**: `http://localhost:8081` (User: `homiq`, Password: `homiq_local_password`)
   - **Port MySQL Langsung**: `localhost:3307`

---

### Opsi 3: Instalasi Manual

1. **Clone repository dan masuk ke direktori**:
   ```bash
   git clone <repository-url>
   cd PMS-guesthouse-fullstack
   ```

2. **Pasang dependensi Composer**:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Konfigurasi Berkas Lingkungan (`.env`)**:
   Salin berkas `env` ke `.env`:
   ```bash
   cp env .env
   ```
   Buka berkas `.env` dan sesuaikan parameter berikut:
   ```ini
   CI_ENVIRONMENT = development
   app.baseURL = 'http://localhost:8080/'
   app.indexPage = ''

   database.default.hostname = localhost
   database.default.database = homiq_pms
   database.default.username = root
   database.default.password =
   database.default.DBDriver = MySQLi
   database.default.port = 3306

   # Sandi awal untuk seeder akun Admin
   HOMIQ_SEED_ADMIN_PASSWORD = SandiKuatAdmin123!
   ```

4. **Jalankan Migrasi & Database Seeder**:
   ```bash
   php spark migrate
   php spark db:seed DefaultDataSeeder
   ```

5. **Pastikan Izin Akses Folder Writable**:
   Pastikan folder runtime dapat ditulis oleh web server:
   ```bash
   chmod -R 775 writable
   ```

6. **Jalankan Server Development**:
   ```bash
   php spark serve --port 8080
   ```
   Buka `http://localhost:8080` di browser Anda.

---

### 🔑 Kredensial Masuk Awal (Default)

Setelah menjalankan `DefaultDataSeeder`:
- **Username**: `admin`
- **Password**: Nilai dari `HOMIQ_SEED_ADMIN_PASSWORD` pada `.env` (atau bawaan `ChangeMe123!` jika tidak ditentukan di environment).
- **Role**: `Admin`

> ⚠️ **PENTING**: Segera masuk ke menu **Settings → Manajemen User** untuk memperbarui kata sandi akun administratif sebelum digunakan di lingkungan produksi!

---

## ⏰ CLI Commands & Scheduled Tasks (Cron)

HomiQ PMS dilengkapi perintah CLI khusus untuk pemeliharaan otomatis sistem:

### 1. Preventive Maintenance Generator
Menghasilkan tiket *Work Order* pemeliharaan otomatis untuk jadwal yang telah masuk periode pengingat:
```bash
php spark maintenance:generate-due
```
*Jadwalkan perintah ini pada cPanel Cron Job atau crontab server satu kali setiap hari (misalnya pukul 01:00 dini hari):*
```cron
0 1 * * * cd /path/to/homiq-pms && php spark maintenance:generate-due >> /dev/null 2>&1
```

### 2. Marketing Analytics Audit
Memverifikasi integritas kalkulasi data metrik pemasaran, booking window, dan rekonsiliasi data:
```bash
php spark marketing:analytics-audit --year=2026 --month=all
```
*Tambahkan flag `--json` untuk mendapatkan hasil terstruktur yang siap dibaca oleh API atau script monitoring eksternal.*

---

## 📚 Dokumentasi Tambahan

Untuk panduan penggunaan lebih mendalam, pelajari dokumen teknis di direktori `docs/`:

- [Panduan Operasional Lengkap](docs/panduan_operasional_homiq_pms.md): Alur operasional harian check-in, penanganan pembatalan, alokasi Extra Bed, dan checklist tutup buku harian.
- [Matriks Hak Akses Pengguna](docs/role_access_matrix.md): Rincian hak akses per rute untuk masing-masing peran.
- [Brief Kebutuhan Hosting](docs/hosting_medium_brief.md): Panduan pemilihan paket shared hosting/VPS dan spesifikasi teknis server.
- [Rencana Implementasi Maintenance](docs/maintenance_operations_implementation_plan.md): Arsitektur modul CMMS, jadwal berkala, dan aset kamar.

---

## 📄 Lisensi

Proyek ini dilisensikan di bawah lisensi [MIT License](LICENSE).
Dikembangkan untuk kebutuhan operasional manajemen guesthouse modern yang efisien dan andal.
