# Ledger perubahan database PMS multi properti

Dokumen ini harus diperbarui setiap fase. Source migrasi CodeIgniter adalah acuan utama; ledger ini adalah daftar rilis dan pemeriksaan agar perubahan dapat diterapkan ke database Rumahweb yang sudah berisi data tanpa mengimpor ulang atau menimpa database tersebut.

Status per 30 September 2026: Fase 1–4 diterapkan dan diuji pada aplikasi/database **lokal** `homiq_pms`. Fase 4 tidak menambah migrasi atau mengubah data utama. Belum ada migrasi multi properti yang dijalankan di Rumahweb.

Update 1 Oktober 2026: CF-1 holding/ledger kas diterapkan lokal sebagai sembilan tabel kosong. Detail schema, manifest source, hasil uji dan catatan backup/override DB_NAME ada di `COMPANY_FINANCE_CF1_RELEASE.md`. Tidak ada grant holding, saldo/rekening atau backfill transaksi otomatis; Rumahweb belum berubah.

## Manifest migrasi

| Fase | File migrasi | Status lokal | Status Rumahweb | Ringkasan |
| --- | --- | --- | --- | --- |
| 1 | `app/Database/Migrations/2026-09-29000000_AddPropertiesFoundation.php` | Berhasil | Belum dijalankan | Tabel properti, kolom relasi, backfill HomiQ, index/foreign key per properti |
| 2 | `app/Database/Migrations/2026-09-29010000_AddUserPropertyRoles.php` | Berhasil | Belum dijalankan | Tabel assignment role per properti dan backfill akun lama ke HomiQ |
| 3 | `app/Database/Migrations/2026-09-29020000_AddMitraPricingAndRooms.php` | Berhasil | Belum dijalankan | Mode/tarif per properti dan 10 kamar Mitra; tidak mengubah reservasi/nominal HomiQ |
| 4 | **Tidak ada migrasi baru** | Tidak diperlukan | Tidak diperlukan | Memakai `property_id`/indeks/FK dari Fase 1 dan assignment Fase 2; perubahan Fase 4 hanya source aplikasi |
| 5 | **Tidak ada migrasi baru** | Tidak diperlukan | Tidak diperlukan | Dashboard, analytics, dan ekspor memakai `property_id` hasil Fase 1; perubahan hanya source aplikasi |
| 6 | Menunggu implementasi | Belum | Belum | Audit migrasi dan rilis |
| CF-1 | `app/Database/Migrations/2026-10-01000000_AddCompanyCashFoundation.php` | Berhasil, tabel kosong | Belum dijalankan | Holding, grant terpisah, rekening, opening balance, ledger dan audit/period lock; tanpa backfill bisnis |
| CF-2 | `app/Database/Migrations/2026-10-01010000_AddCompanyCashIntegration.php` | Berhasil, enam tabel kosong | Belum dijalankan | Link penerimaan/refund, mapping saldo awal, pembayaran expense parsial dan overhead holding; tanpa perubahan data lama |
| CF-3 | `app/Database/Migrations/2026-10-01020000_AddCompanyEntitlementsAndObligations.php` | Berhasil, empat tabel kosong | Belum dijalankan | Perjanjian effective-dated, kewajiban keuangan, pelunasan utang tanpa beban ganda, dan alokasi overhead holding |
| CF-4 | **Tidak ada migrasi baru** | Tidak diperlukan | Tidak diperlukan | UI 5 tab anti-AI-slop, HTMX 30s auto-refresh, SpreadsheetML XML (.xls) 5 worksheet; perubahan hanya source aplikasi |
| CF-5 | `app/Database/Migrations/2026-10-01030000_AddCompanyReconciliationAndSnapshots.php` | Berhasil, dua tabel kosong | Belum dijalankan | Rekonsiliasi bank bulanan, gate integritas saldo penutup, pembekuan snapshot SHA-256, reopen audit |

## CF-5 — Rekonsiliasi, tutup buku, snapshot freeze, UAT lokal, 1 Oktober 2026

- **Dua tabel baru**: `company_bank_reconciliations` dan `company_finance_snapshots`.
  - `company_bank_reconciliations`: menyimpan saldo rekening koran bank (*statement balance*), saldo buku ledger kas (*book balance*), selisih (*variance/discrepancy*), status (`reconciled`, `discrepancy`), tanggal rekening koran, catatan penjelasan, dokumen referensi, dan pengguna yang merekonsiliasi.
  - `company_finance_snapshots`: membekukan laporan bulanan, arus kas eksternal IAS 7, rekapitulasi rekonsiliasi, dan saldo penutup per rekening dalam format JSON dengan versi berjenjang (`version: 1, 2, ...`) serta `snapshot_hash` (kriptografi SHA-256 sepanjang 64 karakter) untuk jaminan integritas anti-tamper.
- **Pemeriksaan Integritas Matematis (Gate Tutup Buku)**:
  - Validasi ketat bahwa $\text{Saldo Penutup} = \text{Saldo Pembuka} + \text{Inflow} - \text{Outflow}$ untuk setiap rekening aktif holding sebelum periode dapat ditutup.
- **Immutability & Reopen Terkontrol**:
  - Penolakan transaksi susulan (*late posting*) dan perubahan rekonsiliasi pada periode tertutup (`status = 'closed'`).
  - Pembukaan kembali (*reopen*) dibatasi secara eksklusif hanya untuk `Company Approver` dengan kewajiban mencantumkan alasan audit; aksi dicatat permanen di `company_finance_audit`.
- **Integrasi UI & Ekspor SpreadsheetML**:
  - Sub-tab Rekonsiliasi Bank & Kas pada Tab Treasury dengan kartu ringkasan status rekening dan form rekonsiliasi.
  - Badge penanda `Periode Terkunci (Closed)` dan indikator snapshot `Snapshot vX` pada header portal holding.
  - Ekspor SpreadsheetML XML (`.xls`) diperkaya dengan tabel status rekonsiliasi bank di Worksheet 3 dan status snapshot di Worksheet 1.
- **Uji Lokal Otomatis & Regresi**:
  - 45 dari 45 pemeriksaan uji CF-5 lulus 100% pada clone database `homiq_pms_cf5_test_20261001` via `php spark company:cf5-test`.
  - Uji regresi CF-4 lulus 45 dari 45 pemeriksaan (100%).
  - Uji regresi CF-3 lulus 34 dari 34 pemeriksaan (100%).
  - Checksum tabel bisnis existing `homiq_pms` identik/intact: `users=1521949837, rooms=941710999, guests=3378459516, reservations=3504074905, payments=3357593375, expenses=3807269205, invoice_items=1914596919`.
- Manifest source dan rilis lengkap dicatat di `docs/COMPANY_FINANCE_CF5_RELEASE.md`. Belum diunggah ke Rumahweb.

- **Tidak ada migrasi DDL, tabel, atau kolom baru.** Fitur CF-4 beroperasi di atas skema CF-1, CF-2, dan CF-3 yang telah teruji.
- Checksum tabel bisnis existing `homiq_pms` identik/intact: `users=1521949837, rooms=941710999, guests=3378459516, reservations=3504074905, payments=3357593375, expenses=3807269205, invoice_items=1914596919`.
- **Desain & UI Anti-AI-Slop**:
  - Menggunakan tipografi monospace tabular (`.holding-num`) untuk seluruh angka moneter guna memastikan presisi audit vertikal ala software perbankan/Stripe/Linear, bukan template dashboard AI generik.
  - Memisahkan secara tegas metrik saldo kumulatif (*As-Of*) dari metrik mutasi periodik (*Month MTD*).
  - Enam kartu KPI eksekutif dengan hirarki visual kuat, badge status operasional, dan indikator perubahan MoM.
  - Menghindari gradient pastel, card-padding berlebihan, atau neon glows yang identik dengan AI template boilerplate.
- **5 Core Tab Terintegrasi**:
  1. `summary`: Ringkasan 6 KPI eksekutif, grafik tren 6 bulan (Chart.js), tabel perbandingan MoM (Bulan Ini vs Bulan Lalu), snapshot kontribusi properti, dan panel integritas audit.
  2. `monthly`: Laporan bulanan & hak holding (tabel kontribusi unit properti dan jembatan rekonsiliasi kas gross operasional → hak bersih holding).
  3. `cashflow`: Arus kas eksternal (IAS 7), rekap saldo rekening bank terverifikasi, dan mutasi kas ledger berhalaman server-side dengan filter akun/jenis/klasifikasi/pencarian.
  4. `obligations`: Ringkasan kewajiban keuangan (utang owner, utang refund/deposit, beban disetujui belum lunas) dengan aksi settlement langsung (modal terintegrasi).
  5. `treasury`: Operasi kas harian, konfirmasi penerimaan PMS, penguncian/pembukaan periode buku (period lock), daftar perjanjian manajemen, dan alokasi overhead holding.
- **Pembaruan Terkini (HTMX Polling)**:
  - Auto-refresh setiap 30 detik (`hx-trigger="every 30s [!document.hidden], refresh from:body"`) yang otomatis terjeda saat tab browser tersembunyi guna menghemat bandwidth dan beban server.
  - Indikator status visual `Live · Diperbarui HH:mm:ss WIB` dengan animasi denyut (`.live-pulse`) dan tombol refresh manual tanpa reload halaman.
- **Ekspor SpreadsheetML XML (.xls)**:
  - Route: `GET /finance/company/export` dengan filter otorisasi `companyFinance:export`.
  - Format murni SpreadsheetML XML dengan ekstensi berkas `.xls` (bukan `.xlsx` palsu) yang dapat dibuka langsung di Microsoft Excel, LibreOffice, atau Google Sheets.
  - Terdiri dari 5 worksheet terstruktur: `Ringkasan & Jembatan`, `Kontribusi Properti`, `Arus Kas & Rekening`, `Mutasi Kas`, dan `Kewajiban Keuangan`.
  - Dilengkapi pertahanan formula injection: setiap sel teks yang diawali karakter formula (`=`, `+`, `-`, `@`) diawali dengan tanda petik tunggal (`'`).
- **Uji Lokal Otomatis**:
  - 45 dari 45 pemeriksaan uji lulus 100% pada clone database `homiq_pms_cf4_test_20261001` melalui perintah CLI `php spark company:cf4-test`.
  - Uji regresi CF-3 lulus 34 dari 34 pemeriksaan (100%).
  - Manifest source rilis terdokumentasi lengkap di `docs/COMPANY_FINANCE_CF4_RELEASE.md`. Belum diunggah ke Rumahweb.

## CF-3 — hak perusahaan, kewajiban dan laporan bulanan lokal, 1 Oktober 2026

- Empat tabel baru: `property_finance_agreements`, `financial_obligations`, `obligation_settlements`, `company_expense_allocations`. Nominal `DECIMAL(14,2)`, persentase `DECIMAL(5,2)`, tanggal effective DATE, FK sumber company/property/user/cash_transactions. Struktur lengkap acuan migration CF-3 forward-only, `down()` menolak penghapusan data.
- Tidak ada ALTER/backfill pada tabel bisnis existing. Semua tabel CF-3 utama kosong di database lokal utama `homiq_pms`; companies dan company_user_roles tetap kosong (hanya diisi lewat bootstrap operator eksplisit atau clone uji).
- Checksum sebelum/sesudah identik: users=1521949837, rooms=941710999, guests=3378459516, reservations=3504074905, payments=3357593375, expenses=3807269205, invoice_items=1914596919.
- Uji clone `homiq_pms_cf3_test_20261001`: 34 pemeriksaan lulus mencakup:
  1. Perjanjian effective-dated (resolving per tanggal, pencegahan overlap, validasi batas 100%).
  2. Gate Homestay sekali per rumah (booking 3 kamar Homestay dihitung tepat 1 booking unik dan malam rumah proporsional, bukan 3x).
  3. Gate fee perusahaan tidak ditambahkan dua kali (pemisahan room rental base dengan direct services breakfast/laundry/extrabed).
  4. Gate owner payout tidak menjadi expense kedua (pelunasan kewajiban owner_payable melalui cash transaction tanpa menambah baris tabel `expenses`).
  5. Gate alokasi overhead cocok ke sumber (alokasi holding overhead dibatasi <= 100% dari expense induk; laporan total holding menghitung overhead tepat 1 kali).
  6. Estimasi kas bebas terpotong cadangan kewajiban (owner payable & refund pending) dan dapat bernilai negatif.
  7. Penolakan pelunasan kewajiban pada periode yang dikunci (period lock).
- Backup lokal pra-CF3 tersimpan di `/Users/mac/PMS-guesthouse-fullstack/.local-patch/backups/homiq_pms_pre_cf3_20261001.sql` (mode 0600).
- Manifest dan rilis lengkap dicatat di `COMPANY_FINANCE_CF3_RELEASE.md`. CF-3 selesai lokal; belum dijalankan di Rumahweb.

## CF-2 — integrasi kas lokal, 1 Oktober 2026

- Enam tabel baru: `cash_payment_receipts`, `cash_payment_opening_links`, `cash_payment_refunds`, `expense_settlements`, `company_expenses`, `company_expense_settlements`. Nominal `DECIMAL(14,2)`, timestamp ledger UTC, FK sumber dan company/transaction/account, unique transaction/event key. Struktur lengkap acuan migration CF-2, bukan SQL manual parsial.
- Tidak ada ALTER/backfill pada tabel bisnis existing. Semua tabel CF-2 utama kosong; companies dan company_user_roles tetap kosong. Tidak memberi akses/rekening/saldo awal otomatis.
- Checksum sebelum/sesudah identik: users=1521949837, rooms=941710999, guests=3378459516, reservations=3504074905, payments=3357593375, expenses=3807269205, invoice_items=1914596919.
- Uji clone: 29 pemeriksaan backend (termasuk concurrent partial payment dan injected rollback) dan 16 pemeriksaan HTTP. Retry HTTP menghasilkan tepat satu transaksi. Backup lokal pra-CF2 tersimpan mode 0600, jangan upload.
- Manifest dan langkah deploy ada pada `COMPANY_FINANCE_CF2_RELEASE.md`. Gunakan versi terbaru controller/view/library yang juga berubah pada fase sebelumnya. CF-2 lokal selesai; belum dijalankan di Rumahweb.

## Fase 1 — perubahan skema yang sudah dibuat

1. Tabel baru `properties`: `id`, `code` unik, `name`, `is_active`, `created_at`, `updated_at`.
   - ID 1: `HOMIQ`, Adiputra Guesthouse by HomiQ, aktif.
   - ID 2: `HOMESTAY_MITRA`, Homestay Mitra, belum aktif.
   - ID 3: `KOS_MITRA`, Kos Mitra, belum aktif.
2. Kolom `property_id INT UNSIGNED NOT NULL DEFAULT 1` dan foreign key ke `properties.id` ditambahkan pada `guests`, `rooms`, `reservations`, `expenses`, `inventory_items`, `extra_bed_settings`, `shifts`, `cleaning_tasks`, `financial_targets`, `operational_budgets`, `marketing_budgets`, dan `owner_guesthouse_setups`. Default 1 mempertahankan operasi HomiQ lama sebelum semua controller ditingkatkan. Saat fitur properti lain dibuka, setiap penulisan baru wajib mengisi properti aktif secara eksplisit; default ini harus dicabut atau diganti mekanisme yang gagal bila scope hilang setelah seluruh modul siap.
3. `rooms.guesthouse_number TINYINT UNSIGNED NULL` ditambahkan. Untuk kamar HomiQ lama, nilai `floor` 1–5 disalin ke kolom baru; `floor` lama tetap utuh untuk kompatibilitas halaman lama.
4. Unique index lama diganti dengan index gabungan per properti untuk `rooms(room_number)`, `inventory_items(sku)`, `owner_guesthouse_setups(guesthouse)`, serta kombinasi `(year, month)` pada tiga tabel target/budget. Index baru memakai `property_id` sebagai kolom awal. `extra_bed_settings.property_id` juga unik.
5. Foreign key gabungan memastikan `reservations(property_id, room_id, guest_id)` konsisten dengan properti kamar dan tamu. Pembayaran, invoice, foto KTP, bukti pembayaran, dan alokasi Extra Bed masih mengikuti relasi reservasi/kamar yang sudah ada; pemeriksaan akses endpoint file baru diterapkan pada Fase 2–3.

Tidak ada baris reservasi, tamu, pembayaran, invoice, kamar, atau expense yang dihapus. Nilai kolom lama tidak diganti. Perubahan index lama adalah perubahan **struktur**, bukan penghapusan isi tabel.

### Hasil uji lokal

- Backup database lokal sebelum migrasi: `/Users/mac/PMS-guesthouse-fullstack/.local-patch/backups/homiq_pms_pre_phase1_20260929.sql` (berisi data pribadi; simpan lokal dan jangan unggah ke repository/hosting).
- Salinan uji `homiq_pms_phase1_test_20260929`: migrasi berhasil. Sebelum/sesudah: 29 kamar, 12 tamu, 18 reservasi, 13 pembayaran, 33 item invoice, 6 expense. Salinan uji kemudian dihapus; backup lokal tetap tersedia.
- ID properti pada semua kamar/tamu/reservasi lama = 1; mismatch properti antara reservasi, kamar, tamu = 0. Kamar bernomor `101` dapat ditambahkan pada properti ID 2 dalam transaksi uji lalu di-rollback.
- Database lokal utama `homiq_pms`: migrasi tercatat pada tabel `migrations` dengan versi `2026-09-29000000`; hitungan data utama sama seperti salinan uji. Sebanyak 12 kolom `property_id`, 14 foreign key baru, dan 29 nilai `guesthouse_number` lama terverifikasi. Server PHP uji pada port 8088 mengembalikan HTTP 200 untuk `/login`, lalu dihentikan.

## Fase 2 — role per properti dan pilihan properti

1. Tabel baru `user_property_roles`: `id`, `user_id` (FK ke `users`, cascade saat user dihapus), `property_id` (FK ke `properties`, restrict), `role`, `created_at`, `updated_at`. Pasangan `(user_id, property_id)` unik; satu user mempunyai paling banyak satu role pada tiap properti, tetapi dapat mempunyai role berbeda pada properti lain.
2. Migrasi menyalin `users.role` setiap akun lama menjadi satu assignment pada properti HomiQ ID 1. **Tidak ada** assignment otomatis ke Homestay Mitra ID 2 atau Kos Mitra ID 3. `users.role` lama tidak dihapus; ia tetap untuk kompatibilitas dan bukan sumber otorisasi setelah Fase 2.
3. Tidak ada kolom atau data booking/keuangan yang dihapus atau diubah oleh migrasi Fase 2. Properti aktif dan role aktif disimpan dalam sesi aplikasi dan disinkronkan dari assignment database pada tiap request. Menghapus/mengubah assignment akan berlaku pada request berikutnya.
4. File aplikasi yang wajib ikut saat rilis: migrasi di atas, `app/Libraries/PropertyContext.php`, filter/controller terkait, route, view header/sidebar/Settings/halaman persiapan, dan `public/css/app.css`. Manifest file lengkap perlu dibuat bersama seluruh perubahan Fase 3–6 sebelum upload.

### Hasil uji lokal Fase 2

- Backup database lokal sebelum migrasi: `/Users/mac/PMS-guesthouse-fullstack/.local-patch/backups/homiq_pms_pre_phase2_20260929.sql` (mode 0600, berisi data pribadi; **jangan** unggah ke repository atau Rumahweb).
- Salinan uji `homiq_pms_phase2_test_20260929`: migrasi berhasil; empat akun lama menghasilkan tepat empat assignment HomiQ dan nol assignment Mitra. Akun uji sementara hanya dibuat di salinan ini untuk memeriksa login, pembatasan Finance HomiQ, role berbeda pada akun dua properti, pengalihan modul/ekspor saat memilih Mitra, penambahan role dari Settings, dan pencabutan akses saat sesi masih aktif. Salinan uji beserta akun sementara kemudian dihapus; backup lokal tetap tersedia.
- Database lokal utama `homiq_pms`: migrasi versi `2026-09-29010000` berhasil; 4 akun, 4 assignment HomiQ, 0 assignment Mitra, 18 reservasi, 29 kamar, dan 13 pembayaran. Tidak ada akun uji yang ditambahkan ke database utama.

## Fase 3 — Reservasi dan Front Desk

1. Migrasi `2026-09-29020000` menambah `properties.pricing_mode VARCHAR(32) NOT NULL DEFAULT 'room_nightly'` dan `properties.base_rate DECIMAL(10,2) NOT NULL DEFAULT 0`. HomiQ ID 1 tetap `room_nightly`, tanpa perubahan harga kamar historis. Homestay ID 2 = `whole_home_nightly`, Rp600.000 per rumah per malam. Kos ID 3 = `room_monthly`, Rp500.000 per kamar per bulan sejak check-in. Kolom `rooms.price_per_night` untuk Mitra sengaja 0 karena bukan tarif malam per kamar.
2. Kamar baru: Homestay Mitra `01`, `02`, `03` (tipe generik `Homestay`); Kos Mitra `k1`–`k7` (tipe generik `Kos`). Setiap nomor dikunci unik **di dalam** propertinya. Booking Homestay selalu memilih/memblokir ketiga kamar, tarif hanya dihitung sekali. Booking Kos hanya menerima checkout pada kelipatan bulan yang dihitung dari tanggal check-in; bila tanggal asal 31 dan bulan tujuan lebih pendek, anniversary jatuh pada hari terakhir bulan itu. Tidak ada prorata harian.
3. Migrasi tidak mengaktifkan Mitra dan tidak memberikan role otomatis, sehingga aman bila file migrasi dijalankan sebelum modul lengkap di production. Khusus **database localhost utama**, setelah uji, `properties.is_active` untuk ID 2/3 diatur 1 dan akun `admin11` diberi assignment `Admin` eksplisit ke ID 2/3 agar Front Desk dapat dicoba. Jangan menyalin dua perubahan aktivasi/assignment ini ke Rumahweb sebelum Fase 4–6 siap. Akun lain tetap hanya punya assignment lama.
4. Modul bisnis Front Desk yang sudah dicakup: Reservasi List/export, New Booking, availability, Room Map, data tamu, harga kamar, grup, layanan tambahan, pembayaran/DP, bukti, invoice, status, dan pengubahan tanggal. Untuk Mitra, menu lain masih diblokir; `propertyReady` tetap menolak URL langsung ke Finance/Operasional/Dashboard/Analytics. Harga Homestay disimpan sebagai tarif properti, bukan dijumlah per tiga kamar. Invoice menampilkan satu rumah; Kos menampilkan periode bulan.

### Manifest source Fase 3 untuk rilis gabungan nanti

Jangan upload parsial sekarang; daftar ini **ditambahkan** ke manifest Fase 1–2 dan Fase 4–6 sebelum deployment Rumahweb. Berkas baru: `app/Database/Migrations/2026-09-29020000_AddMitraPricingAndRooms.php` dan `app/Libraries/PropertyPricing.php`. Berkas diperbarui: `app/Libraries/PropertyContext.php`, `app/Libraries/GuestDirectory.php`, `app/Libraries/ExtraBedService.php`, `app/Filters/PropertyReadyFilter.php`, `app/Controllers/ReservationController.php`, `app/Controllers/RoomMapController.php`, `app/Controllers/RoomPricingController.php`, `app/Controllers/GuestController.php`, `app/Views/components/header.php`, `app/Views/components/sidebar.php`, `app/Views/components/room_map_grid.php`, `app/Views/components/modals/new_booking.php`, `app/Views/components/modals/booking_details.php`, `app/Views/pages/reservations.php`, `app/Views/pages/room_map.php`, `app/Views/pages/room_pricing.php`, `app/Views/documents/reservation_invoice.php`, dan `public/js/app.js`. Versi source **terbaru** setelah Fase 4–6 yang harus diunggah, bukan versi setiap fase secara terpisah.

### Hasil uji lokal Fase 3

- Backup sebelum migrasi utama: `/Users/mac/PMS-guesthouse-fullstack/.local-patch/backups/homiq_pms_pre_phase3_20260929.sql` (mode 0600, berisi data pribadi; jangan unggah). Salinan `homiq_pms_phase3_test_20260929` menerima migrasi terlebih dahulu; HomiQ tetap 29 kamar, 18 reservasi (total nominal Rp14.900.000), 13 pembayaran. Setelah migrasi utama, jumlah HomiQ sama; Mitra 3+7 kamar, belum ada reservasi pada DB utama.
- Pada salinan uji: booking satu kamar Homestay ditolak, booking ketiganya berhasil Rp600.000 dan memblokir seluruh rumah; permintaan pembatalan satu kamar membatalkan semua tiga baris. Kos 10 Oktober–9 November ditolak; dua kamar 10 Oktober–10 Desember berhasil Rp2.000.000 (Rp1.000.000/kamar). Pembayaran Kos Rp500.000 tercatat tanpa mengubah pembayaran HomiQ.
- Detail reservasi lintas properti HTTP 404; invoice Kos HTTP 200/PDF; akses Finance saat Kos aktif dialihkan ke `property-pending`; setelah pindah HomiQ, Room Map HTTP 200 dan ID reservasi Kos tetap HTTP 404. Pemeriksaan sintaks PHP/JS lulus. Akun uji/password sementara hanya di salinan database, bukan database utama.
- Salinan uji `homiq_pms_phase3_test_20260929` sudah dihapus setelah uji; server port 8088 sudah dihentikan. Backup pre-Fase 3 tetap disimpan. Aplikasi localhost utama tetap berjalan di port 8080.

## Fase 4 — Operasional dan keuangan

1. **Tidak ada DDL, tabel, kolom, atau backfill baru.** Kolom `property_id` Fase 1 pada `expenses`, `inventory_items`, `extra_bed_settings`, `shifts`, `cleaning_tasks`, `operational_budgets`, dan `owner_guesthouse_setups` kini dipakai eksplisit oleh query/penulisan Fase 4. Data maintenance dan stock movement mengikuti kamar/item induknya, yang divalidasi pada properti aktif. `financial_targets` dan `marketing_budgets` sudah berskema per properti tetapi belum mempunyai antarmuka/logic aktif; keputusan Fase 5 dicatat di bawah.
2. Finance perusahaan menghitung pembayaran, outstanding, expense, dan cash flow hanya dari properti aktif. Expense baru selalu milik satu properti; tidak ada transaksi atau alokasi otomatis lintas properti. Alokasi beberapa GH **di dalam HomiQ** tetap didukung; bagian expense bersama dalam laporan owner HomiQ dibagi merata ke GH terkait agar jumlah gabungannya tidak berlipat.
3. Atas keputusan pengguna, Homestay Mitra dan Kos Mitra **tidak memakai bagi hasil owner saat ini**. Route owner/setup Mitra ditolak, bukan diberi persentase default HomiQ. Katalog `maintenance_categories` tetap global/read-only untuk Mitra, dipakai bersama untuk klasifikasi, dan mutasinya hanya tersedia pada HomiQ. Ini bukan data biaya/pendapatan bersama. Extra Bed HomiQ mempertahankan fallback stok awal 7 bila pengaturan belum ada; properti Mitra mulai 0 sampai stoknya diatur secara eksplisit.
4. Backup sebelum uji: `/Users/mac/PMS-guesthouse-fullstack/.local-patch/backups/homiq_pms_pre_phase4_20260929.sql` (data pribadi, mode 0600, **jangan unggah**). Semua penulisan uji memakai clone `homiq_pms_phase4_test_20260930`, bukan database utama. Sebelum uji utama tetap 18 reservasi HomiQ dan 6 expense HomiQ total Rp729.000.

### Manifest source Fase 4 untuk rilis gabungan nanti

Tambahkan **versi final terbaru** file berikut ke manifest Fase 1–3: `app/Controllers/FinanceController.php`, `app/Controllers/OpsController.php`, `app/Controllers/HousekeepingController.php`, `app/Controllers/MaintenanceController.php`, `app/Controllers/MaintenanceScheduleController.php`, `app/Controllers/RoomAssetController.php`, `app/Libraries/MaintenanceReportService.php`, `app/Libraries/MaintenanceScheduleService.php`, `app/Libraries/PropertyContext.php`, `app/Filters/PropertyReadyFilter.php`, `app/Views/components/sidebar.php`, `app/Views/components/ops_panel.php`, `app/Views/components/modals/expense_edit.php`, dan `app/Views/components/maintenance/categories.php`. Bila Fase 5–6 mengubah file yang sama, unggah sekali saja versi paling akhir. **Tidak ada file migrasi Fase 4 untuk dijalankan di Rumahweb.**

### Hasil uji lokal Fase 4

- Syntax semua PHP staging lulus `php -l`; route CodeIgniter dapat dihasilkan. Pada HomiQ, Finance, owner report, keenam tab Operasional, dan enam submenu maintenance mengembalikan HTTP 200.
- Pada Homestay Mitra, Finance, Operasional, dan Housekeeping HTTP 200. Expense uji Rp12.345 ditulis pada clone dengan `property_id=2` dan `guesthouse=0`; muncul di Finance Homestay, tidak muncul di HomiQ/Kos. Detail expense Homestay dari HomiQ/Kos dan work order HomiQ dari Mitra mengembalikan HTTP 404. Laporan owner Mitra HTTP 403.
- Pada Kos Mitra, Finance dan laporan maintenance HTTP 200 tanpa angka expense Homestay. Role Finance Kos boleh membaca panel sesuai role aktif, tetapi POST pembuatan inventory dialihkan karena tidak berizin dan tidak menambah item. Semua uji role tersebut pada clone saja; akun dan role database utama tidak diganti.
- Salinan uji Fase 4 dan server uji port 8088 sudah dihentikan/dihapus setelah pemeriksaan; backup pra-Fase 4 tetap tersedia untuk pemulihan lokal. Sebelum rilis tetap perlu uji penerimaan Fase 6 pada clone terbaru produksi, termasuk transaksi finance/maintenance nyata, bukti file, dan seluruh role; hasil ini adalah uji fungsional lokal, bukan persetujuan deployment.

## Fase 5 — Dashboard, analytics, dan ekspor

1. **Tidak ada DDL, tabel, kolom, backfill, atau perubahan baris database baru.** Dashboard membatasi seluruh metrik/aktivitas/alert/recent reservation menurut `property_id` aktif. Analytics membatasi reservasi, pembayaran, expense, tamu, kapasitas, forecast, pembatalan, dan audit menurut properti. HomiQ tetap dapat difilter GH 1–5; Homestay/Kos dihitung pada tingkat propertinya sendiri. Pembayaran berstatus Paid dihitung berdasarkan tanggal pembayaran; pembatalan tidak menambah malam terjual. Transit check-in = check-out dihitung satu malam. Booking grup dihitung sekali untuk booking/tamu loyal, namun setiap kamar yang terpakai dihitung dalam kapasitas kamar-malam.
2. Menu/endpoint Dashboard dan Analytics Mitra dibuka hanya untuk properti yang aktif dan role yang mempunyai izin. Ekspor Recent Reservations mengikuti properti aktif; nominalnya tetap dimask untuk role Admin/Staff sebagaimana kartu Dashboard. Ekspor reservasi/invoice serta keuangan perusahaan/owner sudah di-scope pada Fase 3–4; owner Mitra masih ditolak karena belum memakai bagi hasil. Audit CLI menerima `--property 1|2|3` (gunakan spasi, contoh `php spark marketing:analytics-audit --property 2 --year 2026 --month 9`).
3. `financial_targets` dan `marketing_budgets` sudah mempunyai `property_id` dari Fase 1, tetapi belum ada layar input maupun aturan target/budget yang disepakati. Fase 5 tidak membuat angka target/budget fiktif. Jika fitur itu diminta kemudian, buat spesifikasi dan perubahan source tersendiri sebelum deployment; **tidak perlu menambah/mengubah tabel untuk Fase 5 saat ini**.

### Manifest source Fase 5 untuk rilis gabungan nanti

Tambahkan versi **terakhir** file berikut ke manifest Fase 1–4: `app/Controllers/DashboardController.php`, `app/Controllers/MarketingController.php`, `app/Libraries/MarketingAnalyticsFilter.php`, `app/Libraries/MarketingAnalyticsQueryService.php`, `app/Libraries/MarketingAnalyticsService.php`, `app/Libraries/MarketingAnalyticsAuditService.php`, `app/Commands/AuditMarketingAnalytics.php`, `app/Views/pages/dashboard.php`, `app/Views/pages/marketing.php`, `app/Views/components/marketing_analytics_panel.php`, `app/Filters/PropertyReadyFilter.php`, `app/Libraries/PropertyContext.php`, dan `app/Views/components/sidebar.php`. File terakhir yang juga disentuh Fase sebelumnya cukup diunggah sekali dalam versi paling baru. `app/Views/components/dashboard_metrics.php` **tidak berubah** pada Fase 5. **Tidak ada file migrasi Fase 5**. Jangan unggah `.env`, backup SQL lokal, `writable/`, atau file Excel hasil uji.

### Hasil uji lokal Fase 5

- `php -l` pada semua file PHP yang diubah lulus; `php spark routes` berhasil menghasilkan route. Laman Dashboard dan Analytics HomiQ, Homestay Mitra, serta Dashboard Kos Mitra mengembalikan konten tanpa error pada localhost. Properti Mitra tidak menampilkan filter GH; data HomiQ tidak muncul dalam daftar reservasi/angka Mitra.
- Audit baca-saja September 2026: HomiQ 4 baris reservasi valid = 3 booking unik; 5 kamar-malam terjual/870 kapasitas teoretis, 3 pembayaran Paid Rp700.000, 0 rentang tidak valid (dua reservasi transit sah). Homestay 0/90 kamar-malam, Kos 0/210; keduanya 0 pembayaran. Rumus profit, okupansi, dan jumlah biaya yang diuji audit cocok. Dashboard HomiQ menunjukkan 5 kamar-malam, sama dengan Analytics; kapasitas Dashboard dapat berbeda dari Analytics bila ada blokir maintenance karena Analytics menampilkan kapasitas teoretis.
- Ekspor Recent Reservations HomiQ menghasilkan XML Excel valid berisi header + 5 reservasi serta kolom `Catatan`; untuk role Admin, 10 sel nominal biaya tersamarkan. Kos menghasilkan header saja dan tidak berisi ID booking HomiQ. Unduhan uji tersimpan lokal di Downloads, bukan di Rumahweb. Validasi transaksi nyata pada kedua Mitra dan seluruh kombinasi role tetap menjadi uji penerimaan Fase 6.

## Batas yang masih perlu ditangani

- Kamar dan tarif Mitra sudah diisi pada localhost. Tipe kamar masih generik `Homestay`/`Kos`, bukan klasifikasi fisik lebih rinci.
- Pada database lokal saat pengujian awal, `extra_bed_settings` tidak memiliki baris. Service sekarang membuat stok awal 7 untuk HomiQ sesuai perilaku lama dan 0 untuk setiap Mitra; operator harus mengatur stok fisik Mitra sebelum menjual Extra Bed di sana.
- `users.role` masih ada sebagai kolom kompatibilitas; otorisasi berjalan dari `user_property_roles`. Admin HomiQ pada Settings dapat memberikan assignment properti lain secara eksplisit. Tata kelola admin pusat yang lebih granular dapat dipertegas saat fitur Mitra diaktifkan.
- Mitra aktif **hanya di localhost** untuk Front Desk, Finance perusahaan, Operasional, Housekeeping, Dashboard, dan Analytics setelah Fase 5. Migrasi di production tidak otomatis mengaktifkan Mitra. Pembukaan penuh di production masih bergantung uji penerimaan Fase 6.
- Maintenance dan stok movement ditelusuri lewat kamar/item induk serta divalidasi pada properti aktif. Jangan menganggap ID tiket/item saja cukup untuk izin akses.
- Tabel bersifat global yang sengaja belum diberi `property_id`: `users`, `maintenance_categories`, `system_update_logs`, dan tabel migrasi. Kategori maintenance adalah katalog bersama tanpa hak ubah pada Mitra; expense “perusahaan” pada HomiQ tetap milik HomiQ, **bukan** biaya lintas properti.
- Jangan membuka properti ID 2/3 di **production** sebelum menu, endpoint, role, dan laporan selesai di-scope serta diuji. Default `property_id=1` adalah jembatan kompatibilitas sementara.

## Panduan penerapan ke Rumahweb saat semua fase siap

1. Periksa manifest ini beserta semua migration baru dari fase berikutnya. Cadangkan **database Rumahweb saat itu** melalui backup cPanel/SQL export, dan catat jumlah kamar, tamu, reservasi, pembayaran, invoice, dan expense sebelum perubahan.
2. Pada jendela pemeliharaan, unggah **file migrasi baru lebih dahulu** ke direktori aplikasi CodeIgniter yang benar, tanpa mengganti seluruh source aplikasi. Jangan unggah backup database lokal untuk menggantikan database Rumahweb.
3. Jalankan `php spark migrate --all` satu kali dengan konfigurasi database production yang aktif. Tabel `migrations` mencatat versi yang sudah dijalankan. Bila CLI tidak tersedia, siapkan prosedur SQL yang setara dan diuji pada salinan database production terlebih dahulu; jangan menjalankan sebagian instruksi secara manual. Setelah migrasi dan verifikasi berhasil, baru unggah source aplikasi terbaru (controller, filter, view, dan aset) sebagai satu rilis. Urutan ini penting karena login Fase 2 memerlukan tabel `user_property_roles`.
4. Verifikasi `properties` berisi ID/kode di atas, semua baris historis HomiQ ber-`property_id=1`, jumlah data lama tetap sama, dan semua migration yang diharapkan tercatat. Baru kemudian aktifkan properti Mitra sesuai checklist Fase 6.
5. Migrasi ini **forward-only**: `down()` menolak rollback yang bisa menghilangkan pemisahan properti. Jika rilis gagal, pemulihan menggunakan backup production sebelum migrasi dan versi aplikasi yang sesuai.

Fase 5–6 wajib menambahkan file migrasi bila ada, perubahan data, hasil uji, dan instruksi rilisnya ke ledger ini sebelum deployment.

## Audit 12 Oktober 2026 — Mitra Finance gabungan

- Migration baru `2026-10-12000000_AddMitraFinanceReportGroups.php`: tiga tabel finance_report_groups, finance_report_group_properties, finance_report_group_owner_setups; tidak ada rename/ALTER tabel lama. Lokal tercatat; status production belum diverifikasi.
- Kondisi audit lokal terbaru: 55 tabel, 31 migration; satu setup gabungan 80%, satu akun dengan dua grant Owner Mitra. Nilai lokal tidak untuk disalin ke live.
- Source holding saat audit mempunyai auto-bootstrap/auto-grant Manager/Finance dan fallback otorisasi; berbeda dari catatan grant eksplisit CF awal. Perlu review kebijakan/akses sebelum rilis.
- Rincian skema, dependency, risiko dan urutan deployment: [audit production database](MITRA_FINANCE_PRODUCTION_DATABASE_AUDIT.md).
