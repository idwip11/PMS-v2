# Audit Database & Backend untuk Rilis Mitra Finance

Tanggal: 12 Oktober 2026 (Asia/Makassar). Cakupan: source saat ini, migrasi, database **lokal**, handover tahap 2–7 dan arsip app.zip/new.zip. **Production belum diakses atau diubah.** Audit menggunakan SELECT/SHOW di dalam transaksi READ ONLY; tidak menjalankan migrasi, seeder, atau service yang menulis data.

## 1. Kesimpulan perubahan sejak sebelum plan Mitra

Delta khusus IMPLEMENTATION_PLAN_MITRA_FINANCE_COMBINED: **1 migration, 3 tabel baru, 0 rename tabel, 0 rename kolom lama, 0 ALTER tipe/kolom tabel lama dalam migration ini**. Database sebelum delta tersebut memiliki 52 tabel; lokal saat audit memiliki 55 tabel. Penambahan role adalah nilai VARCHAR yang baru, bukan perubahan ENUM/skema.

Tidak ada pemindahan atau penggabungan fisik data reservasi, pembayaran, invoice, expense, tamu, dan kamar. Data sumber tetap pada properti 2/3; aplikasi menggabungkan hasil query dan perhitungan. HomiQ properti 1 tetap terpisah.

Tabel migrations memperoleh record versi `2026-10-12000000` ketika migration runner dijalankan. Setup owner dan grant akun riil **tidak** dibuat atau dipindahkan otomatis oleh migration itu.

## 2. Skema baru: seluruh kolom dan constraint

Migration: `app/Database/Migrations/2026-10-12000000_AddMitraFinanceReportGroups.php`.

| Tabel | Kolom baru |
| --- | --- |
| finance_report_groups | id INT UNSIGNED AUTO_INCREMENT PK; code VARCHAR(40) NOT NULL UNIQUE; name VARCHAR(150) NOT NULL; is_active TINYINT NOT NULL DEFAULT 1; created_at DATETIME NOT NULL; updated_at DATETIME NOT NULL |
| finance_report_group_properties | group_id INT UNSIGNED NOT NULL; property_id INT UNSIGNED NOT NULL |
| finance_report_group_owner_setups | id INT UNSIGNED AUTO_INCREMENT PK; group_id INT UNSIGNED NOT NULL UNIQUE; owner_name VARCHAR(150) NOT NULL; owner_share_percentage DECIMAL(5,2) NOT NULL; created_at DATETIME NOT NULL; updated_at DATETIME NOT NULL |

- finance_report_groups: CHECK is_active IN (0,1).
- finance_report_group_properties: PK (group_id,property_id), UNIQUE(property_id), CHECK property_id IN (2,3). FK group_id → finance_report_groups.id, DELETE CASCADE / UPDATE RESTRICT. FK property_id → properties.id, DELETE/UPDATE RESTRICT.
- finance_report_group_owner_setups: FK group_id → finance_report_groups.id, DELETE/UPDATE RESTRICT. CHECK owner_share_percentage 0–100 dan nama owner tidak kosong setelah TRIM. Persentase signed DECIMAL, tanpa default nilai bisnis; aplikasi juga menolak precision di atas dua desimal.
- Ketiga tabel lokal menggunakan InnoDB dan utf8mb4_general_ci. DDL aktual dan nama index/FK ada pada bukti JSON audit.
- Seed hanya kelompok `MITRA_COMBINED`, nama `Mitra — Homestay & Kos`, aktif, dan membership properti 2/3. Gunakan lookup code untuk group_id; ID 1 adalah kondisi lokal, bukan kontrak yang boleh di-hardcode saat import.
- Migration memeriksa **ID 2 = HOMESTAY_MITRA**, **ID 3 = KOS_MITRA** sebelum DDL. Pembacaan runtime juga membutuhkan keduanya aktif dan membership tepat [2,3]. Tidak tersedia pemetaan ID production yang berbeda secara otomatis.
- createTable(..., true) melewati tabel yang sudah ada; ini tidak memperbaiki schema drift pada tabel hasil SQL manual/partial migration. Jika pernah gagal, periksa SHOW CREATE TABLE sebelum retry.

## 3. Perubahan sumber konfigurasi, bukan rename kolom

| Sebelumnya | Saat ini | Dampak production |
| --- | --- | --- |
| Mitra menyimpan setup per properti pada owner_guesthouse_setups | Satu baris pada finance_report_group_owner_setups per kelompok | Pilih satu nama/persentase resmi; isi lewat Setup Owner |
| owner_guesthouse_setups.cooperation_fee_percentage | finance_report_group_owner_setups.owner_share_percentage | Dua kolom pada **dua tabel berbeda**. Kolom lama tetap ada; tidak di-rename |
| Owner HS Mitra / Owner Kos Mitra pada user_property_roles.role | Owner Mitra pada dua baris, property_id 2 dan 3, user_id sama | Tentukan akun yang dipertahankan; lakukan perubahan via Settings agar atomik |
| Perjanjian individual property_finance_agreements untuk Mitra | Holding Mitra memakai setup kelompok | Perjanjian lama tetap tersimpan, tetapi tidak menjadi fallback perhitungan Mitra |

Kolom lama cooperation_fee_percentage memang dipakai source HomiQ sebagai **persentase owner**, walaupun namanya menyebut fee. Jangan menafsirkan 70% sebagai fee pengelola dan menyalinnya tanpa pemeriksaan. HomiQ/GH 1–5 tetap memakai tabel/kolom lama dan perilaku bagi hasilnya.

users.role dan user_property_roles.role keduanya VARCHAR(50), tidak perlu ALTER untuk memasukkan Owner Mitra. Autorisasi per properti mengambil user_property_roles; mengubah users.role saja tidak memberikan scope gabungan. Membuat user baru lewat UI mengisi users dan dua grant; mengubah grant akun existing tidak membutuhkan reset password.

Grant owner lama tidak otomatis diterima sebagai Owner Mitra. Dua akun owner lama tidak digabung menjadi satu akun oleh migration; username, password_hash, dan transaksi lama tetap. Staff Finance/Manager/Operational memerlukan izin Finance pada **kedua** properti untuk laporan gabungan. Admin bukan otomatis pemegang izin Finance; setup memakai akun Manager yang memiliki scope memadai (atau role Finance yang diizinkan aplikasi).

## 4. Kondisi lokal saat audit — menggantikan asumsi handover lama

| Item | Hasil pembacaan terbaru |
| --- | --- |
| Server database | MariaDB 10.4.28 |
| Jumlah tabel / migration tercatat | 55 / 31; seluruh 31 file migration App tercatat |
| Properti | HOMIQ=1, HOMESTAY_MITRA=2, KOS_MITRA=3; ketiganya aktif |
| Kelompok | MITRA_COMBINED id 1, aktif; membership (1,2) dan (1,3) |
| Setup owner gabungan | 1 baris; nama terisi; persentase owner **80,00%** |
| Setup owner lama | 1 baris HomiQ/GH1, 70,00%; tidak ada setup Mitra lama |
| Akun / grant Owner Mitra | 4 akun total; 1 akun dengan 2 grant Owner Mitra pada properti 2/3 |
| Staff Finance | 1 assignment Finance hanya pada properti 2; bukan otomatis izin Finance gabungan |
| Holding | 1 company, 3 mapping properti, 1 company_user_role |
| Rekening / opening balance | 1 cash_account dan 1 cash_opening_balance |
| Ledger dan snapshot | cash_transactions/cash_entries/company_finance_snapshots masing-masing 0 baris |
| Sumber operasional | rooms 39, reservations 14, payments 11, expenses 1 |
| Extra Bed stock | HomiQ 7; Homestay 5; Kos 0 |

Nilai 80%, akun lokal, rekening, opening balance, serta transaksi lokal **bukan payload deploy**. Tidak ada bukti bahwa nilai tersebut merupakan keputusan bisnis production. Jangan menimpa database production dengan dump lokal.

Handover tahap 4/6 yang mengatakan setup kosong/2 akun merupakan hasil pada waktu sebelumnya. Handover tahap 7 juga memuat keadaan awal review. Bukti audit baru adalah acuan kondisi lokal sekarang; tidak dapat membuktikan siapa atau proses mana yang melakukan setiap perubahan setelah tahap 6 karena audit log lengkapnya tidak tersedia untuk seluruh tabel.

Extra Bed default Homestay 5/Kos 0 diinisialisasi oleh ExtraBedService.settings() ketika belum ada row; ini DML saat penggunaan aplikasi, bukan migration plan Mitra. Aturan Extra Bed menjadi dasar owner hanya mengubah perhitungan layanan, tidak menambah kolom/tabel Extra Bed.

## 5. Temuan backend yang perlu ditangani sebelum production

### A. [P1] Akses holding otomatis berbeda dari kontrak grant eksplisit

Source saat audit memiliki perilaku tambahan pada CompanyFinanceContext.php dan CompanyFinanceAccess.php:

- ensureDefaultCompany() membuat HOMIQ_HOLDING ketika tidak menemukan company aktif. Bila company aktif belum mempunyai mapping, semua properties dipetakan. Ini dapat menulis companies/company_properties lewat pembukaan halaman, tanpa transaksi bootstrap/audit yang setara command operator.
- assignments() otomatis memasukkan Manager → Company Approver dan Finance → Company Finance berdasarkan session role, atau users.role jika sesi role kosong.
- allows() memakai fallback Manager/Finance walaupun grant eksplisit tidak ada atau tidak memberi permission yang diminta. Akibatnya, grant Viewer yang lebih terbatas bisa kalah oleh fallback, dan pencabutan grant holding dapat diberikan lagi pada akses berikutnya.
- Izin Manager/Finance pada satu properti dapat menjadi akses holding yang mencakup beberapa properti. Filter HTTP saat ini membatasi pintu masuk pada Manager/Finance, tetapi tidak menghilangkan perluasan cakupan tersebut.

Ini **bukan DDL migration Mitra** dan berbeda dari desain CF awal yang meminta bootstrap/grant eksplisit. Jika kebijakan yang dimaksud adalah akses berdasarkan company_user_roles, perilaku ini perlu diperbaiki dan diuji sebelum live. Jika universal Manager/Finance memang keputusan bisnis terbaru, catat cakupan, perilaku revoke dan auditnya secara eksplisit; hasil tes tahap 6 bukan bukti bahwa fallback ini aman untuk semua data/grant production.

### B. [P1] Setup baru tidak mengonversi konfigurasi lama otomatis

Setelah migration di production, tabel setup kelompok awalnya kosong. Laporan menandai setup diperlukan; tidak fallback ke owner legacy/70%. Akun legacy Owner HS/Kos tidak otomatis memperoleh akses gabungan. Siapkan nama, persentase, akun target, dan grant staff sebelum membuka kembali Finance Mitra agar tidak terjadi putus akses/perhitungan tanpa setup.

### C. [P2] Persentase baru berlaku untuk laporan historis yang dihitung ulang

Tabel setup kelompok tidak memiliki effective_from/effective_to, versi, atau histori audit konfigurasi. MitraOwnerFinanceService.report() membaca setup saat ini untuk bulan apa pun. Mengganti persentase menjadi 80% dapat mengubah laporan lama yang dihitung ulang, walaupun baris pembayaran tidak diubah. Holding Mitra juga menggunakan setup terkini. Snapshot yang sudah tersimpan tidak ditulis ulang, tetapi jangan menyamakan perlindungan snapshot dengan semua laporan bulan lama. Tentukan periode berlaku/arsip hasil lama sebelum mengganti kebijakan production; kebutuhan tarif/persentase berbeda per periode memerlukan desain lanjutan.

### D. [P2] Rollback bukan cukup menjalankan migrate:rollback

Migration Mitra down() menolak bila tabel setup gabungan memiliki row; lokal saat ini akan ditolak. Migrasi multi-properti/CF lebih awal bersifat forward-only. Rollback schema tidak mengembalikan grant Owner Mitra atau konfigurasi bisnis. Snapshot/data baru dan transaksi yang masuk setelah backup harus diperhitungkan sebelum restore. Jangan menghapus setup untuk memaksa rollback.

### E. Konsistensi environment dan deployment

Database.php mengisi koneksi dari DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_DRIVER, DB_PORT; simpan konfigurasi production, bukan .env lokal. composer.json meminta PHP ^8.2; CashAmount memerlukan PHP 64-bit (PHP_INT_SIZE >= 8). Uji lokal terakhir memakai PHP 8.3.33; versi/engine production belum diperiksa. Cek InnoDB, dukungan CHECK/FK, charset utf8mb4, dan privilege migration pada staging yang setara production. App Config migrations.lock saat ini false: jangan jalankan dua proses deploy/migration bersamaan.

## 6. Jika live belum memiliki fondasi sebelum plan ini

Keberadaan tiga properti di UI/screenshot tidak membuktikan semua migration sudah terpasang di **server production saat ini**. Bandingkan tabel migrations dan schema production dahulu. Delapan migration tambahan berikut berbeda dari arsip app.zip/new.zip lama; 23 file migration Agustus di kedua arsip byte-identik dengan source sekarang. Arsip itu referensi historis, bukan dump/schema production yang terverifikasi.

| Urutan | Versi / migration | Perubahan jika belum terpasang |
| --- | --- | --- |
| 1 | 2026-09-29000000 AddPropertiesFoundation | Tabel properties; property_id di 12 tabel; rooms.guesthouse_number; unique index per properti dan FK |
| 2 | 2026-09-29010000 AddUserPropertyRoles | Tabel user_property_roles; backfill akun existing ke HomiQ id 1, tanpa grant Mitra |
| 3 | 2026-09-29020000 AddMitraPricingAndRooms | properties.pricing_mode/base_rate; mode tarif; 3 kamar Homestay + 7 kamar Kos |
| 4 | 2026-10-01000000 AddCompanyCashFoundation | 9 tabel CF-1 |
| 5 | 2026-10-01010000 AddCompanyCashIntegration | 6 tabel CF-2 |
| 6 | 2026-10-01020000 AddCompanyEntitlementsAndObligations | 4 tabel CF-3 |
| 7 | 2026-10-01030000 AddCompanyReconciliationAndSnapshots | 2 tabel CF-5 |
| 8 | 2026-10-12000000 AddMitraFinanceReportGroups | 3 tabel Mitra gabungan |

**12 tabel** yang menerima property_id pada fondasi September: guests, rooms, reservations, expenses, inventory_items, extra_bed_settings, shifts, cleaning_tasks, financial_targets, operational_budgets, marketing_budgets, owner_guesthouse_setups. Kolom INT UNSIGNED NOT NULL DEFAULT 1 mempertahankan data lama sebagai HomiQ. rooms.guesthouse_number TINYINT UNSIGNED NULL diisi dari floor 1–5 untuk kamar HomiQ; floor tetap ada, bukan rename.

Index unik rooms(room_number), inventory_items(sku), owner_guesthouse_setups(guesthouse), serta (year,month) pada tiga tabel target/budget diganti menjadi gabungan dengan property_id. Extra Bed unik per property_id; reservasi mendapat FK komposit untuk kecocokan properti kamar/tamu. Payments/invoice tidak mendapat property_id baru di plan ini; scope diwarisi lewat reservation_id.

Fondasi tarif menambah properties.pricing_mode VARCHAR(32), base_rate DECIMAL(10,2), dan seed tarif 600.000 Homestay/500.000 Kos. Ia tidak mengaktifkan Mitra secara otomatis. Jangan menjalankan ulang migration tarif yang sudah tercatat untuk deployment gabungan, karena up() dapat menulis kembali seed base_rate.

Daftar 21 tabel CF yang **mendahului** plan gabungan:

- CF-1: companies, company_properties, company_user_roles, cash_accounts, cash_opening_balances, company_finance_periods, cash_transactions, cash_entries, company_finance_audit.
- CF-2: cash_payment_receipts, cash_payment_opening_links, cash_payment_refunds, expense_settlements, company_expenses, company_expense_settlements.
- CF-3: property_finance_agreements, financial_obligations, obligation_settlements, company_expense_allocations.
- CF-5: company_bank_reconciliations, company_finance_snapshots.

Kalau seluruh fondasi September/CF tersebut sudah live, delta deployment database plan ini cukup **migration urutan 8**, diikuti konfigurasi bisnis/grant. Kalau live lebih lama, gunakan daftar pending sebenarnya dan review seluruh migration lama juga; sebagian migration Agustus melakukan cleanup/drop fitur lama dan tidak boleh dianggap bagian baru Mitra.

## 7. Urutan rilis yang disarankan

1. Ekspor **schema + tabel migrations production** tanpa menyalin kredensial pengguna ke chat. Cocokkan dengan audit lokal; identifikasi pending migrations, owner legacy, grant, identitas properti, dan versi engine. Script read-only di bawah dapat dijalankan pada server dengan konfigurasi DB production yang sudah ada.
2. Selesaikan temuan akses holding dan keputusan bisnis nama/persentase/periode berlaku/akun target. Review perubahan source saat ini, bukan hanya dokumen yang menyebut 100% complete.
3. Backup database production konsisten + source rilis sebelumnya + writable/uploads dan konfigurasi privat. Pastikan backup bisa dipulihkan. Tidak mengimpor dump lokal atau menjalankan DefaultDataSeeder pada database existing.
4. Restore salinan production ke staging; terapkan pending migrations sesuai runner; uji konfigurasi owner/role pada salinan tersebut. Verifikasi nominal dan akses dengan **data production yang tersalin secara aman**, termasuk periode historis dan grant Finance hanya satu properti.
5. Saat rilis, hentikan penulisan sementara dan siapkan seluruh source versi baru. Terapkan schema sebelum melayani request dengan source baru; jangan membuka request ketika upload source masih parsial.
6. Cek `php spark migrate:status`, lalu `php spark migrate -n App` hanya setelah daftar pending disetujui. Perintah migrate menjalankan **semua** pending App, bukan selalu hanya file Mitra terbaru. Simpan tabel migrations lama; jangan hapus/truncate agar migration lama tidak diulang. Seed kelompok berada dalam migration; tidak perlu seed demo tambahan.
7. Konfigurasi Setup Owner dari akun Manager dengan kedua grant Finance, lalu set akun Owner Mitra via Settings agar dua grant berubah dalam satu transaksi. Periksa akun legacy yang perlu dicabut/dipertahankan; jangan reset password otomatis. Jika fondasi baru saja dipasang, grant staff Mitra dan aktivasi properti juga harus diatur eksplisit.
8. Verifikasi: membership tepat [2,3], satu setup sah, dua grant owner user_id sama, owner tidak memperoleh HomiQ/operasional, staff satu grant ditolak pada laporan gabungan, bukti file sah, saldo ledger/snapshot, laporan umum/owner/holding, ekspor dan periode lama. Tabel operasional tidak berpindah properti.
9. Buka kembali aplikasi dan pantau log/error serta rekonsiliasi periode. Jangan menghapus backup atau schema baru hanya karena source rollback.

Mitra setup/grant UI tidak membuat rekening bank atau opening balance; jangan memasukkan saldo lokal sebagai saldo riil holding. Gunakan nilai hasil rekonsiliasi production. Command company:bootstrap tetap tersedia untuk setup operator eksplisit, tetapi jangan menjalankannya tanpa memeriksa company/mapping/grant existing dan perilaku otomatis source saat ini.

## 8. Bukti dan batas audit

- Snapshot privat lokal `docs/qa/MITRA_DATABASE_AUDIT_20261012.json` (diabaikan Git, tidak dipublikasikan): SHOW CREATE TABLE, kolom, index, jumlah baris seluruh 55 tabel, migration history dan konfigurasi yang disamarkan nama pengguna/owner.
- [Manifest migration](qa/MITRA_DATABASE_MIGRATION_MANIFEST_20261012.json): SHA-256 delapan migration September/Oktober dan status lokal.
- [Runner audit read-only](../tests/finance/audit_mitra_database.php): dapat dipakai untuk pembandingan production; output tidak memuat username/password_hash atau credential koneksi. Output masih memuat metadata dan business configuration; simpan privat saat dijalankan pada live.

```bash
# Pada server target dengan DB_* yang benar; baca saja, tidak menjalankan migration:
php tests/finance/audit_mitra_database.php > /path/private/mitra-production-audit.json
```

Audit source/backend kali ini merupakan inspeksi skema dan alur tulis/otorisasi; tidak melakukan load test, penetration test, atau menjalankan semua regresi terhadap snapshot data production. Tidak mengubah migration, business service, grant, atau konfigurasi database. File baru hanya runner audit dan dokumentasi/bukti; syntax runner lulus dan eksekusi READ ONLY berhasil pada MariaDB lokal.
