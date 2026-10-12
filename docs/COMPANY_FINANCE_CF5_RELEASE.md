# CF-5 — Rekonsiliasi, Tutup Buku (Close), Snapshot Freeze, dan UAT

Status 1 Oktober 2026: diterapkan **lokal**; production Rumahweb belum disentuh.

## Perilaku yang Tersedia

- **Rekonsiliasi Bank & Kas (`company_bank_reconciliations`)**:
  - Rekonsiliasi bulanan presisi antara saldo buku ledger kas internal (*book balance*) dan saldo rekening koran bank (*statement balance*).
  - Perhitungan selisih otomatis (*variance / discrepancy* = saldo rekening koran - saldo buku ledger).
  - Klasifikasi status otomatis:
    - `reconciled`: Saldo rekening koran cocok persis dengan saldo buku ledger (selisih Rp0.00).
    - `discrepancy`: Ditemukan selisih antara rekening koran dan ledger (disertai nominal selisih positif/negatif).
    - `pending`: Rekening aktif belum diinput data rekening korannya untuk periode bersangkutan.
  - Form pencatatan rekonsiliasi dilengkapi tanggal rekening koran (*statement date*), catatan penjelasan selisih, nomor/nama dokumen referensi (*bank statement ref*), serta pencatatan audit pengguna dan timestamp rekonsiliasi.
  - Izin rekonsiliasi: Dapat dilakukan oleh `Company Finance` dan `Company Approver` (ditolak untuk `Company Viewer`).

- **Mathematical Integrity Gate Sebelum Tutup Buku**:
  - Validasi matematis otomatis (`verifyPeriodIntegrity`) yang memverifikasi bahwa untuk setiap rekening aktif holding:
    $$\text{Saldo Penutup Akhir Bulan} = \text{Saldo Pembuka Awal Bulan} + \text{Mutasi Kas Masuk} - \text{Mutasi Kas Keluar}$$
  - Jika terdapat anomali atau ketidakkonsistenan saldo pada rekening holding, penutupan periode ditolak dengan pengecualian tegas (*gate integrity failure*).

- **Pembekuan Snapshot Laporan Bulanan (`company_finance_snapshots`)**:
  - Penutupan periode membekukan seluruh laporan keuangan bulan bersangkutan ke dalam payload JSON immutable yang mencakup:
    - Versi snapshot (`version` berjenjang mulai v1).
    - Laporan hak ekonomi bulanan (`monthly_report` / bridge gross-to-net).
    - Laporan arus kas eksternal standar IAS 7 (`external_flow`).
    - Rekapitulasi rekonsiliasi seluruh rekening kas/bank (`reconciliation_summary`).
    - Saldo pembuka, mutasi masuk, mutasi keluar, dan saldo penutup per rekening (`accounts_audit`).
  - **Integritas Kriptografis SHA-256**: Menghitung dan menyimpan `snapshot_hash` (64 karakter hex) dari payload snapshot untuk menjamin laporan tidak dapat diubah tanpa terdeteksi (anti-tamper).
  - Pencatatan log audit atomik pada `company_finance_audit` dengan aksi `period_closed_snapshot`.

- **Immutability Periode Tertutup & Proteksi Late Posting**:
  - Seluruh transaksi kas baru, setoran modal, pembayaran biaya bank, transfer antar-rekening, pelunasan beban, dan mapping penerimaan pada periode berstatus `closed` **ditolak keras** oleh ledger.
  - Perubahan atau simpan rekonsiliasi pada periode tertutup ditolak.
  - Penutupan ulang periode yang sudah tertutup ditolak.

- **Pembukaan Kembali Periode (*Reopen*) Terkontrol**:
  - Pembukaan kembali periode tertutup **dibatasi secara ketat hanya untuk role `Company Approver`** (ditolak untuk `Company Finance` dan `Company Viewer`).
  - **Wajib Alasan Audit**: Reopen menolak eksekusi jika alasan audit kosong.
  - Mencatat aksi audit `period_reopened` secara permanen.
  - Jika periode dibuka kembali, koreksi transaksi diizinkan, dan penutupan berikutnya akan membekukan snapshot baru dengan nomor versi berjenjang (`version: 2`, `version: 3`, dst.).

- **Integrasi Antarmuka (UI) & Header Badges**:
  - Sub-pill **Rekonsiliasi Bank & Kas** pada Tab Treasury (`tab=treasury&sub=reconciliation`):
    - Kartu metrik: Total Rekening, Rekening Cocok (Matched), Rekening Selisih (Discrepancy), Rekening Pending, dan Total Selisih Mutlak.
    - Tabel rekonsiliasi per rekening dengan indikator badge warna profesional, komparasi saldo buku vs rekening koran, selisih, tanggal koran, catatan, dan tombol edit rekonsiliasi modal/inline.
  - Sub-panel **Tutup Buku & Periode** (`tab=treasury&sub=close_period`):
    - Menampilkan status periode saat ini (`TERBUKA` / `TERKUNCI & DIBEKUKAN`).
    - Rangkuman snapshot aktif (versi, waktu penutupan, pelaksana, alasan penutupan, dan hash SHA-256).
    - Peringatan jika masih ada rekening yang belum cocok/reconciled sebelum approver menutup periode.
    - Form tutup buku dengan input alasan wajib bagi Approver.
    - Form buka kembali periode (*reopen*) dengan input alasan audit wajib bagi Approver jika periode terkunci.
  - Badge Header Global: Menampilkan label status `Periode Terkunci (Closed)` dan versi snapshot `Snapshot v{version}` pada kartu header ringkasan holding jika periode bersangkutan sudah ditutup.

- **Ekspor SpreadsheetML XML (`.xls`) Terintegrasi CF-5**:
  - Metadata header pada Worksheet 1 (`Ringkasan & Jembatan`) secara otomatis mencantumkan status penutupan periode dan informasi hash snapshot (misalnya: `Periode Ditutup & Dibekukan (Snapshot v2 - Hash: 5ac60fa70b92db2c...)`).
  - Worksheet 3 (`Arus Kas & Rekening`) diperkaya dengan tabel **Status Rekonsiliasi Bank & Kas Holding** yang merinci saldo buku, saldo rekening koran, selisih, dan status matched/discrepancy untuk setiap rekening.

---

## Skema Database & Migrasi (CF-5)

Migrasi CodeIgniter diterapkan pada file:
`app/Database/Migrations/2026-10-01030000_AddCompanyReconciliationAndSnapshots.php`

Tabel baru yang ditambahkan:
1. `company_bank_reconciliations`:
   - `id`: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
   - `company_id`: INT UNSIGNED NOT NULL
   - `account_id`: INT UNSIGNED NOT NULL
   - `month`: VARCHAR(7) NOT NULL (YYYY-MM)
   - `statement_date`: DATE NOT NULL
   - `statement_balance`: DECIMAL(14,2) NOT NULL
   - `ledger_balance`: DECIMAL(14,2) NOT NULL
   - `discrepancy`: DECIMAL(14,2) NOT NULL
   - `status`: ENUM('reconciled', 'discrepancy') NOT NULL
   - `notes`: TEXT NULL
   - `reference_document`: VARCHAR(150) NULL
   - `reconciled_by`: INT UNSIGNED NOT NULL
   - `reconciled_at`: DATETIME NOT NULL
   - `created_at`: DATETIME NOT NULL
   - `updated_at`: DATETIME NOT NULL
   - Indexes: UNIQUE KEY `idx_company_account_month` (`company_id`, `account_id`, `month`), KEY `idx_company_month_status` (`company_id`, `month`, `status`).

2. `company_finance_snapshots`:
   - `id`: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
   - `company_id`: INT UNSIGNED NOT NULL
   - `month`: VARCHAR(7) NOT NULL (YYYY-MM)
   - `version`: INT UNSIGNED NOT NULL DEFAULT 1
   - `snapshot_data`: LONGTEXT NOT NULL (JSON)
   - `snapshot_hash`: CHAR(64) NOT NULL (SHA-256)
   - `closed_by`: INT UNSIGNED NOT NULL
   - `created_at`: DATETIME NOT NULL
   - Indexes: UNIQUE KEY `idx_company_month_version` (`company_id`, `month`, `version`), KEY `idx_company_month` (`company_id`, `month`).

---

## Manifest Source CF-5

File-file berikut dibuat atau diperbarui pada tahap CF-5:

1. **Database & Migrasi**:
   - `app/Database/Migrations/2026-10-01030000_AddCompanyReconciliationAndSnapshots.php`
2. **Otorisasi & Keamanan**:
   - `app/Libraries/CompanyFinanceAccess.php` (tambah izin `'reconcile'` untuk Company Finance dan Approver; izin `'close'` dan reopen tetap eksklusif Approver)
3. **Service Layer**:
   - `app/Libraries/CompanyReconciliationService.php` (layanan rekonsiliasi, integritas matematis gate, pembekuan snapshot SHA-256, buka/tutup periode)
4. **Controller & Endpoint**:
   - `app/Controllers/CompanyFinanceController.php` (penanganan aksi `reconcile_bank`, integrasi snapshot pada `set_period`, data rekonsiliasi pada ekspor `.xls`)
5. **Views / Antarmuka**:
   - `app/Views/pages/company_finance_foundation.php` (badge periode closed & snapshot vX di header, passing data rekonsiliasi ke sub-komponen)
   - `app/Views/components/company_tab_treasury.php` (sub-tab rekonsiliasi bank & kas, formulir rekonsiliasi, panel snapshot, form tutup dan buka periode)
6. **Testing & QA**:
   - `app/Commands/TestCompanyCashCF5.php` (perintah uji komprehensif `company:cf5-test`)
   - `app/Commands/TestCompanyCashCF3.php` (penyesuaian pembersihan mapping properti pada awal pengujian clone)

---

## Hasil Pengujian Clone Lokal (1 Oktober 2026)

Pengujian dijalankan pada database clone terisolasi `homiq_pms_cf5_test_20261001` via perintah:
`DB_NAME=homiq_pms_cf5_test_20261001 php spark company:cf5-test`

### 45 Pemeriksaan CF-5 Lulus 100%:
- **CF5-01 s/d CF5-04**: Setup rekening holding, cutover saldo awal, pencatatan transaksi September (BCA & Mandiri), saldo buku ledger akhir bulan terverifikasi (BCA Rp10.500.000, Mandiri Rp7.000.000).
- **CF5-05 s/d CF5-16**: Uji Rekonsiliasi Bank:
  - Viewer ditolak menyimpan rekonsiliasi.
  - Rekonsiliasi matched BCA (selisih Rp0.00, status `reconciled`).
  - Rekonsiliasi discrepancy Mandiri (rekening koran Rp7.050.000 vs buku Rp7.000.000 = selisih tepat Rp50.000, status `discrepancy`).
  - Rekapitulasi rekonsiliasi akurat: 2 rekening, 1 reconciled, 1 discrepancy, 0 pending, `all_reconciled = false`, total selisih mutlak Rp50.000.
  - Audit log rekonsiliasi tercatat lengkap.
- **CF5-17 s/d CF5-18**: Uji Matematis Gate Integritas:
  - `verifyPeriodIntegrity` memverifikasi kedua rekening konsisten ($Saldo Penutup = Saldo Pembuka + Inflow - Outflow$).
- **CF5-19 s/d CF5-31**: Uji Tutup Buku & Snapshot Freeze:
  - Viewer dan Finance dilarang menutup periode (wajib Approver).
  - Penutupan tanpa alasan audit ditolak.
  - Approver berhasil menutup periode September (status `closed`).
  - Snapshot versi 1 berhasil dibekukan dengan hash SHA-256 sepanjang 64 karakter valid.
  - Snapshot data membekukan `monthly_report`, `external_flow` IAS 7, dan rekap saldo penutup akun.
  - Audit log `period_closed_snapshot` tercatat.
- **CF5-32 s/d CF5-34**: Uji Immutability Periode Tertutup:
  - Transaksi baru (late posting) ke periode September ditolak.
  - Perubahan rekonsiliasi pada periode tertutup ditolak.
  - Penutupan ulang periode yang sudah tertutup ditolak.
- **CF5-35 s/d CF5-43**: Uji Pembukaan Kembali (Reopen) & Penutupan Snapshot Berjenjang:
  - Viewer dan Finance dilarang membuka kembali periode.
  - Approver membuka kembali periode September dengan alasan audit (status kembali `open`).
  - Audit log `period_reopened` tercatat.
  - Transaksi penyesuaian (bunga jasa giro Rp50.000) berhasil dicatat setelah reopen.
  - Rekonsiliasi Mandiri disesuaikan menjadi matched (selisih Rp0.00, status `reconciled`, `all_reconciled = true`).
  - Penutupan ulang periode menghasilkan Snapshot versi 2 (`version: 2`).
- **CF5-44 s/d CF5-45**: Uji Ekspor SpreadsheetML XML:
  - Worksheet 3 memuat tabel status rekonsiliasi bank holding.
  - Worksheet 1 mencantumkan status periode ditutup & dibekukan beserta versi snapshot.

### Uji Regresi Modul Sebelumnya:
- **Uji Regresi CF-4 (`company:cf4-test`)**: Seluruh 45 pemeriksaan lulus 100% pada clone `homiq_pms_cf4_test_20261001`.
- **Uji Regresi CF-3 (`company:cf3-test`)**: Seluruh 34 pemeriksaan lulus 100% pada clone `homiq_pms_cf3_test_20261001`.

### Integritas Data Bisnis Utama:
Checksum tabel operasional utama pada `homiq_pms` terbukti **100% tidak berubah** sebelum dan sesudah pengujian:
- `users`: `1521949837`
- `rooms`: `941710999`
- `guests`: `3378459516`
- `reservations`: `3504074905`
- `payments`: `3357593375`
- `expenses`: `3807269205`
- `invoice_items`: `1914596919`

---

## Prosedur Penerapan ke Server Produksi (Rumahweb)

> [!IMPORTANT]
> Server produksi Rumahweb **belum disentuh sama sekali**. Penerapan hanya dilakukan setelah mendapat persetujuan final dari pengguna.

Langkah-langkah deployment ke Rumahweb jika telah disetujui:
1. **Cadangkan Database Produksi**:
   ```bash
   mysqldump -u <db_user> -p <db_name> > backup_pre_cf5_$(date +%Y%m%d_%H%M%S).sql
   ```
2. **Unggah File Source Aplikasi**:
   Unggah file-file yang tercantum pada **Manifest Source CF-5** (beserta file CF-1 s/d CF-4) ke direktori server Rumahweb.
3. **Jalankan Migrasi Database**:
   ```bash
   php spark migrate
   ```
   Migrasi hanya akan mengeksekusi migrasi yang belum berjalan (`2026-10-01030000_AddCompanyReconciliationAndSnapshots.php`), menambahkan tabel `company_bank_reconciliations` dan `company_finance_snapshots` tanpa mengubah atau menghapus data pada tabel bisnis existing.
4. **Verifikasi Checksum & UAT**:
   Verifikasi checksum tabel `users`, `rooms`, `reservations`, `payments`, `expenses` untuk memastikan integritas data.
