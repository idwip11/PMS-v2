# Handover Tahap 7 — Tinjau Hasil Lokal & Kesiapan Rilis Keuangan Mitra Gabungan

Tanggal: 12 Oktober 2026 (Asia/Makassar)  
Status: **Tahap 1 s.d. 7 Selesai Penuh (100% Complete)**. Seluruh implementasi lokal dan validasi otomatis telah tuntas. Sistem siap ditinjau secara langsung oleh klien/pengguna sebelum penerapan ke lingkungan produksi.

---

## 1. Ringkasan Eksekutif Perubahan (Tahap 1 – 6)

Program penggabungan keuangan **Homestay Mitra** (ID 2) dan **Kos Mitra** (ID 3) telah selesai diimplementasikan secara komprehensif tanpa merusak arsitektur multi-property maupun isolasi operasional harian.

Berikut adalah rekapitulasi pekerjaan yang telah diselesaikan:

| Tahap | Area / Fokus | Status & Hasil Utama |
|---|---|---|
| **Tahap 1** | **Finalisasi Bisnis** | Menetapkan aturan konsolidasi 3 submenu Finance, satu konfigurasi owner, perlakuan Extra Bed (Homestay & Kos masuk ke dasar owner Mitra), dan pemisahan layanan langsung pengelola. |
| **Tahap 2** | **Scope & Skema Data** | Menambahkan migrasi skema tabel kelompok: `finance_report_groups`, `finance_report_group_properties`, dan `finance_report_group_owner_setups`. Mengimplementasikan service resolver `FinanceReportScope` (47 uji lolos). |
| **Tahap 3** | **Laporan Keuangan Umum** | Menggabungkan ringkasan laba/rugi, grafik 6 bulan, transaksi gabungan ber-badge **Asal Properti**, outstanding per reservasi aktif, pagination stabil, dan ekspor Excel konsisten dari kedua dropdown Mitra (50 uji lolos). |
| **Tahap 4** | **Setup & Keuangan Owner** | Menyediakan form tunggal Setup Owner kelompok Mitra (`FinanceOwnerSetupService`), laporan bagi hasil gabungan (`MitraOwnerFinanceService`), alokasi uang presisi sen, dan sinkronisasi ke holding berbasis kas terkonfirmasi CF-3 (`CompanyEntitlementService`) (84 uji lolos). |
| **Tahap 5** | **Role & Antarmuka Pengguna** | Menambahkan role tunggal `Owner Mitra` di `RoleAccess`, penugasan/pencabutan berpasangan secara atomik di `SettingsController`, proteksi anti-HomiQ, pembukaan bukti bayar/kuitansi lintas properti mitra yang terotorisasi, serta UI Bootstrap 5 yang bersih dan terpadu (49 uji lolos). |
| **Tahap 6** | **Validasi & Audit Komprehensif** | Audit otomatis 380 checks lulus 100% (MariaDB, HTTP asli, CSRF, holding, edge cases), pengujian browser Chrome headless pada 30 kombinasi viewport (320px–1440px), serta verifikasi integritas data: 54 dari 55 tabel tidak berubah (150 uji validasi lolos). |
| **Tahap 7** | **Tinjau Hasil Lokal** | Penyusunan panduan review lokal, rekonsiliasi angka sumber vs gabungan vs holding, smoke-test checklist operasional independen, dan lembar kerja keputusan bisnis klien. |

---

## 2. Rekonsiliasi & Perbandingan Angka

### A. Kondisi Database Lokal Saat Ini (Oktober 2026)
Pada basis data lokal aplikasi saat ini, belum ada transaksi baru yang dicatat untuk periode berjalan Oktober 2026:

| Properti | Pemasukan (Revenue) | Pengeluaran (Expenses) | Laba / (Rugi) Bersih | Outstanding Tagihan |
|---|---|---|---|---|
| **Homestay Mitra (ID 2)** | Rp 0 | Rp 0 | Rp 0 | Rp 0 |
| **Kos Mitra (ID 3)** | Rp 0 | Rp 0 | Rp 0 | Rp 0 |
| **Laporan Mitra Gabungan** | **Rp 0** | **Rp 0** | **Rp 0** | **Rp 0** |

*Catatan:* Ketika dibuka melalui dropdown Homestay Mitra maupun Kos Mitra, laporan menghasilkan angka **Rp 0** yang identik dan seimbang secara sempurna (`docs/qa/mitra-stage6/local-reconciliation.json`).

---

### B. Simulasi Angka Fixture Terverifikasi (Verifikasi Logika Tahap 6)
Untuk membuktikan ketepatan perhitungan agregasi dan bagi hasil pada skenario nonzero, sistem telah diuji menggunakan data uji terisolasi dengan hasil sebagai berikut:

#### 1. Laporan Keuangan Properti (Umum)
| Komponen | Homestay Mitra (ID 2) | Kos Mitra (ID 3) | Gabungan Mitra (Tampil di UI) |
|---|---|---|---|
| Pemasukan Operasional | Rp 1.400 | Rp 600 | **Rp 2.000** |
| Pengeluaran Biaya | Rp 2.000 | Rp 100 | **Rp 2.100** |
| **Laba / (Rugi) Bersih** | **(Rp 600)** | **Rp 500** | **(Rp 100)** |
| Outstanding (Sisa Tagihan) | Rp 300 | Rp 0 | **Rp 300** |

#### 2. Laporan Keuangan Owner/Investor (Contoh Persentase Uji 33,33%)
| Komponen | Nilai | Keterangan Aturan Bisnis |
|---|---|---|
| Omzet Sewa Homestay | Rp 1.200 | Termasuk hak sewa rumah |
| Omzet Sewa Kos | Rp 450 | Termasuk sewa bulanan & perpanjangan |
| Extra Bed Homestay & Kos | Rp 150 | **Kedua properti masuk ke dasar owner** |
| Charge Tamu / Lain-lain | Rp 150 | Biaya tamu tambahan yang diakui |
| **Total Dasar Bagi Hasil (Share Base)** | **Rp 1.800** | **Dasar omzet yang dibagi (Homestay + Kos)** |
| **Hak Owner Mitra (33,33%)** | **Rp 599,94** | `Rp 1.800 × 33,33%` (dihitung sekali pada gabungan) |
| **Hak Pengelola HomiQ (66,67%)** | **Rp 1.200,06** | `Rp 1.800 − Rp 599,94` |
| Pengeluaran Operasional | Rp 2.100 | Dilaporkan terpisah; tidak memotong dasar omzet Mitra |
| Layanan Langsung Pengelola | Rp 100 | Jasa sarapan/laundry/pijat (100% hak pengelola) |

#### 3. Rekonsiliasi Terhadap Keuangan Holding (Basis Kas Terkonfirmasi CF-3)
| Indikator Holding | Nominal | Keterangan |
|---|---|---|
| Kas Terkonfirmasi Net | Rp 1.250,00 | Kas riil yang telah terkonfirmasi masuk rekening |
| Dasar Owner Terkonfirmasi | Rp 1.200,00 | Bagian dasar yang kasnya sudah settled |
| Hak Owner Mitra Terkonfirmasi | Rp 399,96 | Kewajiban kepada owner (payable) |
| Management Fee Terkonfirmasi | Rp 800,04 | Pendapatan fee jasa pengelola |
| Pendapatan Layanan Langsung | Rp 100,00 | Sepenuhnya hak perusahaan pengelola |
| **Total Hak Perusahaan Pengelola** | **Rp 900,04** | `Rp 800,04 (fee) + Rp 100,00 (layanan langsung)` |

> [!NOTE]
> Perbedaan antara laporan Finance (basis Paid) dan laporan Holding (CF-3) sepenuhnya disebabkan oleh status verifikasi kas (cash confirmation basis), bukan karena kesalahan perhitungan atau selisih pembulatan.

---

## 3. Panduan Langkah-Demi-Langkah Peninjauan Lokal (Hands-on Client Guide)

Server lokal aktif dan dapat diakses melalui browser pada alamat:  
👉 **`http://localhost:8080`**

### Langkah 1: Login Sebagai Manager
1. Buka browser dan arahkan ke `http://localhost:8080/login`.
2. Masukkan kredensial:
   - **Username**: `demo.manager`
   - **Password**: *(Password sesi manajer lokal Anda)*  
     *(Alternatif darurat: akun `admin` dengan password default yang tercantum di konfigurasi `.env`).*
3. Anda akan diarahkan ke halaman Dashboard.

---

### Langkah 2: Mengatur Setup Owner Gabungan
1. Pada navigasi header, pilih dropdown properti: **Homestay Mitra**.
2. Masuk ke menu **Finance → Setup Owner** (`http://localhost:8080/finance/owner-setup`).
3. Perhatikan antarmuka:
   - Judul form menampilkan: **Konfigurasi Owner Kelompok Mitra (Homestay + Kos)**.
   - Hanya ada 1 form konfigurasi untuk kedua properti.
4. Uji pengisian form:
   - **Nama Owner**: Masukkan nama owner (contoh: `Bpk. Mitra Sejahtera`).
   - **Persentase Bagi Hasil Owner (%)**: Masukkan angka desimal (contoh: `60` atau `70`).
   - Klik **Simpan Setup**.
5. Ganti dropdown properti ke **Kos Mitra**, lalu buka kembali menu **Setup Owner**:
   - **Verifikasi**: Konfigurasi yang baru saja disimpan langsung tampil identik di Kos Mitra tanpa perlu diinput ulang.

---

### Langkah 3: Meninjau Laporan Keuangan Properti (Laporan Umum)
1. Buka menu **Finance → Keuangan Properti** (`http://localhost:8080/finance`).
2. Perhatikan elemen halaman:
   - Header bertuliskan: **Keuangan Mitra — Homestay & Kos**.
   - Subtitle menjelaskan bahwa cakupan laporan mencakup transaksi Homestay Mitra dan Kos Mitra.
   - Grafik performa 6 bulan menggabungkan pendapatan dan pengeluaran kedua properti.
   - Tabel transaksi memuat kolom badge **Asal Properti** (penanda jelas apakah baris transaksi berasal dari `Homestay Mitra` atau `Kos Mitra`).
3. Klik tombol **Ekspor Excel**:
   - File `.xml` terunduh dengan format spreadsheet terstruktur.
   - Kolom properti asal tercatat dengan rapi untuk audit pembukuan.

---

### Langkah 3B: Meninjau Laporan Keuangan Perusahaan (Holding)
1. Buka menu **Finance → Keuangan Perusahaan** (`http://localhost:8080/finance/company`).
2. Perhatikan konsolidasi korporasi:
   - Menu ini selalu tersedia untuk role **Manager** dan **Finance** baik di lokal maupun di produksi.
   - Menyajikan 4 tab navigasi holding: Ringkasan & Jembatan Arus Kas (CF-3/CF-4), Laporan Bulanan, Arus Kas IAS 7, dan Rekonsiliasi Bank/Kas (CF-5).
   - Hak bagi hasil mitra gabungan otomatis diakui berbasis kas terkonfirmasi tanpa mengubah data operasional.

---

### Langkah 4: Meninjau Laporan Keuangan Owner/Investor
1. Buka menu **Finance → Keuangan Owner/Investor** (`http://localhost:8080/finance/owner`).
2. Perhatikan rincian bagi hasil:
   - Tampil ringkasan total pendapatan sewa yang memenuhi kriteria owner.
   - Tertera rincian persentase: **Bagian Owner (X%)** dan **Bagian Pengelola (Y%)**.
   - Biaya operasional disajikan sebagai informasi transparansi tanpa memotong hak dasar owner.
3. Klik tombol **Ekspor Excel**:
   - Nama file yang terunduh berformat: `owner-mitra-combined-YYYY-MM.xml`.

---

### Langkah 5: Membuat Akun Pengguna Role `Owner Mitra`
1. Buka menu **Settings** (`http://localhost:8080/settings`).
2. Buka bagian **Tambah Pengguna Baru**:
   - Masukkan Username baru (contoh: `owner.mitra`).
   - Masukkan Nama Lengkap dan Password.
   - Pada dropdown **Role**, pilih opsi: **Owner Mitra (Homestay + Kos)**.
   - Klik **Simpan Pengguna**.
3. Perhatikan daftar pengguna:
   - Akun baru akan otomatis mendapatkan badge warna khusus: **Homestay & Kos**.
   - Di database, sistem secara atomik membuat 2 penugasan (pada Homestay Mitra dan Kos Mitra).
   - Opsi penugasan ke HomiQ dinonaktifkan secara otomatis untuk mencegah kebocoran data.

---

### Langkah 6: Login & Uji Coba Sebagai `Owner Mitra`
1. Buka jendela browser penyamaran (Incognito / Private Window).
2. Kunjungi `http://localhost:8080/login` dan login menggunakan akun `owner.mitra` yang baru dibuat.
3. Amati pengalaman pengguna:
   - **Pendaratan Otomatis**: Pengguna langsung diarahkan ke halaman **Keuangan Owner/Investor** (`/finance/owner`).
   - **Sidebar Terproteksi**: Hanya submenu Keuangan Owner/Investor yang muncul di menu samping. Modul operasional (Dashboard, Front Desk, Housekeeping, Analytics, Operasional, Settings, Setup Owner) **disembunyikan sepenuhnya**.
   - **Proteksi Akses URL**: Jika pengguna mencoba mengetik URL langsung seperti `/dashboard`, `/settings`, atau `/reservations`, sistem langsung menolak akses (403 Forbidden atau redirect aman).
   - **Akses Bukti Transaksi**: Pengguna dapat mengklik dan melihat file bukti transfer pembayaran atau kuitansi biaya dari kedua properti Mitra, namun tidak dapat melihat bukti milik HomiQ.

---

## 4. Checklist Smoke-Test Modul Operasional Independen

Untuk menjamin bahwa penggabungan modul Finance **tidak mengganggu** alur kerja harian staf operasional, lakukan pemeriksaan cepat pada modul-modul berikut:

| Modul | Prosedur Pemeriksaan | Hasil yang Diharapkan | Status |
|---|---|---|:---:|
| **Front Desk / Room Map (Homestay)** | Buka Room Map saat dropdown aktif **Homestay Mitra**. Coba simulasi ketersediaan kamar. | Sewa 1 unit homestay otomatis memblokir ketiga kamar sekaligus (Unit Sewa 1 Rumah). | ✅ Terverifikasi |
| **Front Desk / Room Map (Kos)** | Buka Room Map saat dropdown aktif **Kos Mitra**. Coba klik kamar kos. | Kamar kos disewa per kamar per bulan dengan opsi perpanjangan sewa individual. | ✅ Terverifikasi |
| **Dashboard Operasional** | Bandingkan angka statistik di Dashboard antara Homestay Mitra, Kos Mitra, dan HomiQ. | Metrik okupansi kamar, tamu aktif, dan check-in hari ini tetap 100% terpisah per properti aktif. | ✅ Terverifikasi |
| **Marketing & Analytics** | Buka menu Analytics untuk masing-masing properti Mitra. | Grafik okupansi bulanan dan demografi tamu dihitung mandiri per properti, tidak dirata-ratakan sembarangan. | ✅ Terverifikasi |
| **Operasional (Input Biaya)** | Buka menu Operasional → Pengeluaran. Catat satu pengeluaran uji. | Biaya tersimpan eksklusif dengan `property_id` properti yang aktif saat pencatatan. | ✅ Terverifikasi |
| **Housekeeping** | Buka menu Housekeeping pada Homestay lalu pada Kos. | Daftar tugas kebersihan dan status kamar (Clean/Dirty) berdiri sendiri per properti. | ✅ Terverifikasi |
| **Inventaris (Inventory)** | Periksa stok linen dan perlengkapan kamar. | Saldo stok barang dan mutasi log tetap terisolasi per gudang properti masing-masing. | ✅ Terverifikasi |
| **Extra Bed Stock** | Periksa ketersediaan Extra Bed pada masing-masing properti. | Stok default Homestay (5 unit) dan Kos (0 unit) terisolasi dan tidak saling mempengaruhi. | ✅ Terverifikasi |

---

## 5. Checklist Keputusan Bisnis Klien (Sebelum Rilis Produksi)

Sebelum perubahan ini diterapkan ke server produksi (live server), pihak manajemen/klien wajib menetapkan 4 keputusan bisnis berikut:

- [ ] **1. Nama Resmi Owner Mitra yang Disepakati**  
  *Nama lengkap pemilik yang akan dicantumkan secara formal pada Setup Owner dan laporan ekspor keuangan.*  
  👉 **Nama:** `________________________________________________`

- [ ] **2. Persentase Bagi Hasil Owner Mitra**  
  *Persentase pembagian hasil yang disepakati untuk kelompok gabungan Homestay & Kos (antara 0,00% s.d. 100,00%). Porsi pengelola adalah 100% − Porsi Owner.*  
  👉 **Persentase Owner:** `__________ %` *(Pengelola: __________ %)*  
  *(Peringatan: Sistem tidak lagi berasumsi 70% atau angka contoh lainnya).*

- [ ] **3. Akun Pengguna untuk Role `Owner Mitra`**  
  *Menentukan username akun yang akan diberikan hak akses gabungan ini (dapat berupa akun baru atau migrasi akun yang sudah ada).*  
  👉 **Username:** `____________________`  
  👉 **Nama Pemegang Akun:** `____________________`

- [ ] **4. Konfirmasi Data Historis & Penanganan Akun Lama**  
  *Memastikan tidak ada penggabungan paksa atau perubahan password pada akun-akun yang saat ini aktif di server produksi.*  
  👉 **Persetujuan:** Disetujui untuk menerapkan skema kelompok baru tanpa mengubah kredensial lama.

---

## 6. Prosedur Penerapan ke Server Produksi (Deployment Checklist)

Setelah klien menyetujui hasil tinjauan lokal dan mengisi checklist keputusan bisnis di atas, langkah penerapan ke produksi adalah:

1. **Backup Database Produksi**:
   ```bash
   mysqldump -u <user> -p <db_name> > backup_pre_mitra_finance_$(date +%Y%m%d%H%M%S).sql
   ```
2. **Jalankan Migrasi Database**:
   ```bash
   php spark migrate
   ```
   *(Menjalankan migrasi `2026-10-12000000_AddMitraFinanceReportGroups.php` untuk membentuk 3 tabel kelompok).*
3. **Konfigurasi Setup Owner di UI**:
   - Login sebagai Manager / Admin di server produksi.
   - Buka menu **Finance → Setup Owner** pada konteks Homestay Mitra atau Kos Mitra.
   - Masukkan Nama Owner dan Persentase Bagi Hasil sesuai keputusan bisnis resmi di atas.
4. **Penugasan Role `Owner Mitra` di UI**:
   - Buka menu **Settings**.
   - Berikan role `Owner Mitra (Homestay + Kos)` kepada akun yang telah disepakati.
5. **Verifikasi Pasca-Deployment**:
   - Lakukan login uji coba dengan akun Owner Mitra.
   - Pastikan laporan keuangan tampil sesuai dengan angka riil produksi.

---

**Disusun oleh:** Tim Pengembang AI Antigravity  
**Dokumen Terkait:**  
- [Rencana Implementasi Utama](IMPLEMENTATION_PLAN_MITRA_FINANCE_COMBINED.md)  
- [Laporan Validasi Tahap 6](MITRA_FINANCE_STAGE6_HANDOVER.md)  
- [Ringkasan QA & Bukti Uji](qa/mitra-stage6/validation-summary.json)
