# CF-2 — Integrasi penerimaan, expense dan overhead

Status 1 Oktober 2026: diterapkan **lokal**; production Rumahweb tidak disentuh.

## Perilaku yang tersedia

- Konfirmasi penerimaan booking/OTA ke rekening/kas: net diterima, potongan OTA sebagai metadata nonkas. Tidak memposting gross dan net dua kali; tidak mengubah payment_date lama.
- Expense properti Approved dapat dibayar parsial. Approval tidak berarti pembayaran. Sumber expense yang sudah terhubung ledger tidak dapat ditimpa/dihapus; sinkronisasi maintenance tidak menimpa sumber yang terhubung.
- Refund aktual memerlukan Company Approver dan dibatasi penerimaan bersih yang telah dikonfirmasi sampai tanggal refund. Pembatalan/refund pending tidak menghapus kas masuk. Status legacy Refund_Pending tetap dipertahankan; lihat angka refund aktual terpisah.
- Overhead nyata perusahaan memakai company_expenses, bukan property_id palsu. Pembuat tidak boleh menyetujui overhead sendiri; setelah Approved baru dapat dibayar.
- Transfer internal dua kaki seimbang, modal/pinjaman sebagai financing, pembelian aset investing, fee bank operating. Transfer dan opening bukan cashflow eksternal.
- Riwayat sebelum cutover dapat dipetakan sebagai sudah termasuk saldo awal dengan verifikasi approver; mapping tidak menambah kas dan tidak dapat ditimpa. Tidak mengimpor seluruh pembayaran lama otomatis.
- Nominal decimal string/cents integer, company/source row locks, idempotent event key, atomic posting + source link, pemeriksaan scope/izin fresh dan period lock CF-1. CSRF dan konfirmasi tindakan wajib pada endpoint web.

## Tambahan database

Migration `app/Database/Migrations/2026-10-01010000_AddCompanyCashIntegration.php`, bergantung CF-1. Enam tabel baru:

| Tabel | Fungsi |
| --- | --- |
| cash_payment_receipts | Penerimaan net, potongan OTA dan snapshot nominal sumber |
| cash_payment_opening_links | Mapping penerimaan historis yang sudah termasuk saldo awal |
| cash_payment_refunds | Refund aktual terkait pembayaran sumber |
| expense_settlements | Pembayaran parsial expense properti |
| company_expenses | Overhead holding Pending/Approved/Rejected dengan maker/checker |
| company_expense_settlements | Pembayaran parsial overhead holding |

Tidak ada ALTER tabel bisnis, backfill atau grant otomatis. Lokal utama: enam tabel baru kosong, holding/rekening/grant tetap belum diisi. Sumber migration adalah acuan FK/index/tipe data. Migration down menolak penghapusan data.

## Manifest source terbaru

Gabungkan dengan manifest CF-1 dan fase multi-property; unggah sekali versi **terbaru** setiap file ke root aplikasi aktif (bukan menebak folder homiq_app_new).

- app/Database/Migrations/2026-10-01010000_AddCompanyCashIntegration.php
- app/Libraries/CashLedgerService.php
- app/Libraries/CompanyCashIntegration.php
- app/Libraries/CashSourceGuard.php
- app/Controllers/CompanyFinanceController.php
- app/Controllers/OpsController.php
- app/Controllers/MaintenanceController.php
- app/Config/Routes.php
- app/Views/pages/company_finance_foundation.php
- app/Views/components/company_cash_integration.php

`app/Commands/TestCompanyCashIntegration.php` dan skrip HTTP hanya untuk pengujian lokal, **tidak perlu upload**. Jangan upload `.env`, SQL backup, test credentials/fixtures, writable, atau database localhost.

## Pengujian dan deployment

- Dua clone homiq_pms_cf2_test_20261001/20261002: migration lulus, 29 pemeriksaan backend termasuk concurrent overpayment dan injected database failure/rollback. HTTP 16 pemeriksaan termasuk login, akses holding, CSRF, approver restriction, konfirmasi dan retry, tanggal masa depan. Retry menghasilkan satu transaksi.
- Tujuh checksum tabel bisnis sebelum/sesudah migration utama identik, detail di DATABASE_MIGRATION_LEDGER.md. Backup pra-CF2 lokal: `/Users/mac/PMS-guesthouse-fullstack/.local-patch/backups/homiq_pms_pre_cf2_20261001.sql`, berisi data pribadi, mode 0600.
- Rumahweb: backup source/DB terbaru, clone production dan UAT terlebih dahulu, pastikan migration multi-property + CF-1 tersedia, jalankan migration CF-2 dengan konfigurasi production yang benar pada jendela maintenance, kemudian upload source final sebagai satu rilis. Jangan restore SQL localhost ke production. Verifikasi data existing tetap utuh, tidak ada grant/backfill otomatis. Bila CLI tidak tersedia, prosedur SQL setara harus disiapkan dan diuji terlebih dahulu, bukan membuat enam tabel manual sebagian.
- Nama holding, assignment properti, pengguna holding, rekening, saldo awal dan tanggal cutover harus disepakati/ditambahkan secara eksplisit sebelum aktivasi; role Finance properti tidak otomatis mendapat holding.

## Batas fase

Konfirmasi kas dilakukan eksplisit dari menu holding; tidak mengubah alur booking existing menjadi auto-post. Preview dibatasi 100 sumber terbaru per kelompok, bukan daftar penuh; pagination/bulk import belum tersedia. Waktu input diberi label UTC (WITA minus 8 jam). UI ini fondasi konfirmasi, bukan dashboard realtime CF-4 atau tutup buku/reconciliation CF-5. Source berubah nominal ditandai untuk review, tidak mengoreksi ledger otomatis. Koreksi maintenance yang sumbernya telah dibayar membutuhkan review terpisah. Ledger refund tidak mengubah status legacy menjadi Refunded. Hak owner/deposit, kewajiban dan kas bebas belum dihitung (CF-3); saldo rekening bukan laba/hak bersih holding. Immutability melalui service dan kontrol DB operasional, bukan trigger seluruh tabel.
