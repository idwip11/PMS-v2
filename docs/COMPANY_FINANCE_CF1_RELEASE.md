# CF-1 — catatan schema, uji, dan manifest rilis

Status 1 Oktober 2026: **lokal saja**. Production belum diubah. Aktivasi holding utama menunggu nama perusahaan dan username pilihan pengguna. Semua sembilan tabel utama kosong; tidak ada rekening/saldo atau hak lintas properti otomatis.

## Schema migrasi 2026-10-01000000

1. `companies`: identitas holding, kode unik, nama, status aktif (default nonaktif), waktu dibuat.
2. `company_properties`: pasangan perusahaan/properti; satu properti satu holding dalam model awal. Mapping ini cakupan manajemen, bukan bukti kepemilikan hukum.
3. `company_user_roles`: assignment user/perusahaan; role terpisah dari `user_property_roles`; grant pertama hanya eksplisit melalui operator bootstrap.
4. `cash_accounts`: kas/bank, mata uang IDR, properti opsional (NULL = perusahaan), pembuat/waktu; kode unik per holding. Composite FK memaksa scope properti konsisten.
5. `cash_opening_balances`: satu saldo awal per rekening, waktu cutover UTC, nominal, verifier, alasan dan waktu. Tidak masuk transaksi revenue/cashflow.
6. `cash_transactions`: header immutable, jenis/classification, effective time UTC, deskripsi/source ref, user/waktu, payload hash; unique company/event key dan unique reverses_id.
7. `cash_entries`: nominal bertanda per rekening, FK gabungan ke header/rekening dalam perusahaan yang sama; satu kaki per rekening per transaksi. Transfer dua kaki seimbang.
8. `company_finance_periods`: bulan open/closed, user/alasan/waktu. Company-row lock menyerialkan posting/close, termasuk bulan yang belum mempunyai baris.
9. `company_finance_audit`: event, referensi, details, actor dan timestamp.

Tidak menambah/mengganti kolom tables users/payments/expenses/properties/reservations. Foreign key RESTRICT mempertahankan jejak ledger saat akun sumber hendak dihapus. Migrasi down sengaja menolak penghapusan tabel ledger; rollback release melalui source/feature disable, bukan menghapus transaksi.

Immutability ditegakkan service aplikasi: tidak ada operasi edit/delete posting. Administrator DB tetap punya kemampuan mengubah tabel secara langsung; lindungi akses DB dan backup. Ini ledger kas, **bukan** jurnal akuntansi double-entry lengkap.

## Manifest source final CF-1

Berkas baru:

- `app/Database/Migrations/2026-10-01000000_AddCompanyCashFoundation.php`
- `app/Libraries/CompanyFinanceAccess.php`
- `app/Libraries/CompanyFinanceContext.php`
- `app/Libraries/CashAmount.php`
- `app/Libraries/CashLedgerService.php`
- `app/Filters/CompanyFinanceFilter.php`
- `app/Controllers/CompanyFinanceController.php`
- `app/Commands/BootstrapCompanyFinance.php`
- `app/Views/pages/company_finance_foundation.php`

Berkas existing diperbarui (gunakan versi terbaru gabungan seluruh fase):

- `app/Config/Routes.php`: company route keluar dari group propertyReady/access:finance, memakai filter holding.
- `app/Config/Filters.php`: alias `companyFinance`.
- `app/Views/components/sidebar.php`: holding link hanya tampil dengan grant terpisah.

Dokumentasi: plan holding, ledger DB, dan catatan rilis ini. `app/Commands/TestCompanyCashFoundation.php` khusus developer/test dan **tidak perlu diunggah ke production**. Script HTTP smoke di staging juga bukan berkas rilis. `.env`, backup SQL dan akun test tidak diunggah.

## Hasil dan batas uji

- PHP syntax seluruh 13 file lulus; route holding tunggal memakai `companyFinance:view`, auto-routing tetap nonaktif.
- 38 pemeriksaan CLI di clone `homiq_pms_cf1_test_20261002` lulus, termasuk idempotency concurrent dua proses, precision, rollback kegagalan kaki kedua, scope perusahaan, grant revoke, cutover, future-dated, reversal, dan period lock.
- 10 pemeriksaan HTTP di server clone 8088 lulus; grant holding eksplisit bekerja dengan role properti Staff, Finance properti tidak mendapat akses global, query ID/array palsu ditolak, no-store, serta navigasi properti tidak rusak.
- Baseline lama sebelum/sesudah lokal: 5 users, 39 rooms, 21 reservations, 15 payments, total reservations 18.550.000, payments 6.550.000, expenses 729.000. Grant properti dan data bisnis lama tidak ditulis oleh implementasi.
- Test concurrency posting vs close dan pemeriksaan volume besar/semua role masih masuk UAT CF-5; fondasi memakai lock yang sama untuk kedua operasi. Rekonsiliasi bank, maker/checker workflow, laporan gabungan, accrual, bukti holding dan expense settlement belum diimplementasikan pada CF-1.
- Login existing masih memerlukan assignment properti. CF-1 tidak membuat akun holding-only tanpa assignment properti; role holding diberikan sebagai izin tambahan kepada akun existing yang dipilih secara eksplisit.

## Catatan eksekusi dan backup

Migrasi tabel kosong lokal utama sempat dijalankan sebelum pengujian clone karena override `database.default.database` tidak dipakai constructor aplikasi. Constructor memakai **`DB_NAME`**. Sesudahnya clone diuji dengan DB_NAME yang benar. Tidak ada fixtures, mutasi ledger, saldo atau grant yang dibuat di database utama.

Dump awal `homiq_pms_pre_cf1_20261001.sql` keluar dengan error metadata `mysql.proc` MariaDB lokal saat membaca routines. Jangan mengandalkan dump awal itu sebagai backup penuh. Tidak menjalankan mysql_upgrade atau mengubah service DB.

Backup sukses `homiq_pms_cf1_schema_20261001.sql` memakai `--skip-routines`, permission 0600, disimpan pada `.local-patch/backups/`. Berisi seluruh tabel/data utama termasuk fondasi kosong. Restore ke clone kedua berhasil dan nominal/jumlah legacy cocok. Routine server tidak tercakup; bila digunakan, lakukan backup routine melalui server yang metadata-nya sehat sebelum deployment production.

Clone, akun test, dan server 8088 dibersihkan setelah uji. Fixture dapat dibuat ulang melalui command test pada clone baru dengan pola nama `homiq_pms_cf1_test_YYYYMMDD`; command menolak main DB dan environment selain development.

Sebelum Rumahweb: backup tervalidasi, audit gabungan multi-property Fase 6, deploy source terbaru, migrate schema additive, validasi zero implicit grants, lalu bootstrap hanya dengan nama/user/properti yang disetujui. CF-1 sendiri belum mengaktifkan integrasi penerimaan atau laporan perusahaan real-time.
