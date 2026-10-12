# CF-3 — Hak Perusahaan, Kewajiban dan Laporan Bulanan

Status 1 Oktober 2026: diterapkan **lokal**; production Rumahweb tidak disentuh.

## Perilaku yang tersedia

- **Perjanjian Effective-Dated (`property_finance_agreements`)**:
  - Penetapan aturan bagi hasil/fee pengelola per properti dengan rentang tanggal berlaku (`effective_from` s/d `effective_to`).
  - Mendukung tipe perjanjian: `revenue_share`, `net_profit_share`, `fixed_fee`, `full_management`, `custom`.
  - Dasar perhitungan: `room_revenue`, `gross_revenue`, `net_revenue`, `operating_profit`, `none`.
  - Pengecekan overlap: sistem menolak rentang tanggal yang saling bertabrakan untuk properti yang sama.
  - Properti yang belum memiliki perjanjian ditandai eksplisit sebagai *unconfigured* pada laporan manajemen, tanpa asumsi angka fiktif.
- **Kewajiban Keuangan & Pelunasan (`financial_obligations`, `obligation_settlements`)**:
  - Pencatatan utang/kewajiban: `owner_payable` (hak bagi hasil owner), `refund_deposit` (deposit/refund tamu), `tax_withholding`, dan lainnya.
  - Status bertahap: `estimated` (estimasi perhitungan awal) → `finalized` (disetujui Approver untuk bayar) → `settled` (lunas via kas) / `cancelled`.
  - Pelunasan kas langsung melunasi kewajiban melalui cash ledger transaction (`obligation_settlement`).
  - **Gate lulus:** Pembayaran kewajiban owner payout **tidak menambah baris tabel `expenses`**, sehingga tidak terjadi pencatatan beban ganda.
  - Mendukung pelunasan parsial dan menolak pembayaran yang melebihi sisa kewajiban.
- **Alokasi Overhead Pusat ke Properti (`company_expense_allocations`)**:
  - Beban overhead holding yang berstatus `Approved` dapat didistribusikan ke properti dalam cakupan untuk analisis kontribusi.
  - **Gate lulus:** Total alokasi dibatasi maksimal 100% dari nilai beban induk; laporan total holding menghitung overhead tepat 1 kali tanpa menduplikasi nilai alokasi.
- **Laporan Manajemen Bulanan & Jembatan Hak Perusahaan (Reconciliation Bridge)**:
  - **Gate lulus:** Homestay Mitra (`whole_home_nightly`) dihitung tepat 1 booking unik dan malam rumah proporsional per reservasi, bukan 3x lipat kamar fisik.
  - **Gate lulus:** Fee pengelola dan layanan langsung milik perusahaan (Breakfast, Extra Bed, Laundry, Massage, Sewa Kendaraan) dipisahkan agar fee tidak dihitung dua kali.
  - Jembatan rekonsiliasi menghubungkan: Kas Masuk Tamu Terkonfirmasi → (-) Hak Owner → (+) Layanan Langsung Perusahaan → (-) Beban Operasional Unit → (-) Overhead Holding Terbayar → (=) Hasil Usaha Bersih Perusahaan (Basis Kas).
  - Ringkasan KPI: Saldo Kas Terkini, Estimasi Kas Bebas (dikurangi cadangan kewajiban owner & refund titipan, dapat bernilai negatif), Kas Masuk, Kas Keluar, Arus Kas Bersih, dan Hasil Usaha Holding.
  - Peringatan sistem: transaksi berstatus Paid di Front Desk yang belum dikonfirmasi ke treasury holding, properti tanpa perjanjian, dan kewajiban estimasi yang menunggu finalisasi.

## Tambahan database

Migration `app/Database/Migrations/2026-10-01020000_AddCompanyEntitlementsAndObligations.php`, bergantung CF-1 dan CF-2. Empat tabel baru:

| Tabel | Fungsi |
| --- | --- |
| `property_finance_agreements` | Perjanjian pembagian hak/fee pengelola dengan tanggal berlaku dan dasar perhitungan |
| `financial_obligations` | Kewajiban hak owner, deposit/refund, dan titipan berstatus estimated/finalized |
| `obligation_settlements` | Pelunasan kas aktual untuk kewajiban via cash transaction tanpa beban ganda |
| `company_expense_allocations` | Distribusi overhead pusat ke properti untuk pelaporan manajemen unit |

Tidak ada ALTER tabel bisnis, backfill atau grant otomatis. Lokal utama: empat tabel baru kosong, holding/rekening/grant tetap belum diisi. Sumber migration adalah acuan FK/index/tipe data. Migration down menolak penghapusan data.

## Manifest source terbaru

Gabungkan dengan manifest CF-1, CF-2, dan fase multi-property; unggah sekali versi **terbaru** setiap file ke root aplikasi aktif:

- `app/Database/Migrations/2026-10-01020000_AddCompanyEntitlementsAndObligations.php`
- `app/Libraries/CompanyEntitlementService.php`
- `app/Libraries/CashLedgerService.php`
- `app/Libraries/CompanyCashIntegration.php`
- `app/Libraries/CashSourceGuard.php`
- `app/Libraries/CompanyFinanceAccess.php`
- `app/Libraries/CompanyFinanceContext.php`
- `app/Libraries/CashAmount.php`
- `app/Controllers/CompanyFinanceController.php`
- `app/Views/pages/company_finance_foundation.php`
- `app/Views/components/company_monthly_report.php`
- `app/Views/components/company_obligations.php`
- `app/Views/components/company_agreements.php`
- `app/Views/components/company_overhead_allocations.php`
- `app/Views/components/company_cash_integration.php`

`app/Commands/TestCompanyCashCF3.php` hanya untuk pengujian lokal, **tidak perlu upload**. Jangan upload `.env`, SQL backup, test credentials/fixtures, writable, atau database localhost.

## Pengujian dan deployment

- Clone `homiq_pms_cf3_test_20261001`: migration lulus, 34 pemeriksaan backend & fungsional lulus termasuk:
  - 10 checks untuk perjanjian effective-dated, overlap rejection, dan permission boundaries.
  - 4 checks untuk gate Homestay 1 rumah per booking (malam, booking count, total sales).
  - 4 checks untuk gate isolasi layanan langsung perusahaan tanpa double count fee.
  - 7 checks untuk gate owner payout tidak menambah beban di tabel `expenses` serta validasi finalisasi.
  - 5 checks untuk gate alokasi overhead maksimal 100% dan pencegahan double count holding.
  - 2 checks untuk estimasi kas bebas terpotong cadangan kewajiban.
  - 2 checks untuk penegakan period lock pada pelunasan kewajiban.
- Seluruh 29 pemeriksaan regression CF-2 pada clone tetap lulus 100%.
- Tujuh checksum tabel bisnis sebelum/sesudah migration identik:
  - users = 1521949837
  - rooms = 941710999
  - guests = 3378459516
  - reservations = 3504074905
  - payments = 3357593375
  - expenses = 3807269205
  - invoice_items = 1914596919
- Backup lokal pra-CF3: `/Users/mac/PMS-guesthouse-fullstack/.local-patch/backups/homiq_pms_pre_cf3_20261001.sql` (mode 0600).
- Rumahweb: jalankan migration CF-3 setelah CF-1 dan CF-2 pada jendela pemeliharaan, verifikasi tabel baru kosong, kemudian deploy source final. Tidak ada grant atau perubahan data existing otomatis.
