# Inventaris Fase 0 — PMS Multi Properti

Tanggal pemeriksaan: 29 September 2026. Cakupan: source lokal `PMS-guesthouse-fullstack-main 3`; tidak memeriksa atau mengubah server Rumahweb.

## Ringkasan

Aplikasi sudah punya konsep lima guesthouse HomiQ, tetapi belum punya entitas properti yang berdiri sendiri. Identitas guesthouse saat ini diturunkan terutama dari `rooms.floor`, sedangkan identitas role disimpan satu nilai global pada pengguna. Menambah kamar 3 + 7 tanpa mengganti asumsi ini berisiko mencampur data, laporan, biaya, dan akses.

Stack aktif: PHP 8.2+ / CodeIgniter 4.7, MySQL/MariaDB, Bootstrap 5.3, HTMX 2, JavaScript biasa, Chart.js, dan Dompdf. `npm run dev` adalah launcher untuk development PHP/MySQL lokal.

## Relasi data saat ini

| Data | Hubungan / asumsi sekarang | Dampak untuk multi properti |
| --- | --- | --- |
| `users` | Kolom `role` tunggal; username global unik | Perlu role per properti dan pengecualian admin pusat yang eksplisit. |
| `guests` | Direktori global; reservasi menunjuk ke `guest_id` | Harus diputuskan apakah profil tamu terpisah per properti atau profil global dengan izin lintas properti. Rekomendasi awal: pisahkan sampai aturan berbagi data disepakati. |
| `rooms` | `room_number` unik global; `floor` digunakan sebagai nomor GH 1–5 | Tambah relasi properti/guesthouse; nomor kamar menjadi unik per properti; `floor` kembali hanya bermakna lantai bila diperlukan. |
| `reservations` | Terkait ke `guest_id` dan `room_id`; kelompok booking berbagi `booking_group_id`/`order_id` | Properti dapat diturunkan dari kamar, tetapi semua endpoint grup harus memastikan seluruh kamar berada di properti yang sama. |
| `payments`, `invoice_items` | Terkait ke reservasi | Cakupan diwariskan dari reservasi/kamar; akses file pembayaran juga harus memeriksa properti reservasi. |
| `expenses` | Memiliki angka `guesthouse`; `expense_guesthouses` mendukung alokasi ke beberapa GH; angka 0 berarti perusahaan | Perlu model alokasi properti yang jelas, termasuk aturan biaya bersama dan histori HomiQ. |
| `owner_guesthouse_setups` | Satu baris per nomor GH, unique pada `guesthouse` | Setup pemilik harus mencakup properti dan unit/guesthouse di dalamnya. |
| `inventory_items`, `extra_bed_settings` | Inventaris dan stok extra bed bersifat global saat ini; pengaturan extra bed berupa satu total stok | Stok serta biaya barang perlu dipisahkan atau dibagi secara sengaja per properti. |
| `maintenance_categories` | Katalog kategori umum; aset dan jadwal maintenance terkait ke kamar | Katalog boleh tetap umum, tetapi aset, tiket, jadwal, biaya, ekspor, dan lampiran wajib mengikuti properti kamar. |
| `shifts`, `cleaning_tasks`, `operational_budgets`, `financial_targets`, `marketing_budgets` | Dibuat global; beberapa tabel memakai unik global (misalnya tahun-bulan) atau tidak punya properti/kamar | Tambahkan konteks properti atau tetapkan secara eksplisit mana yang memang lintas properti. Cleaning task juga belum punya `room_id`. |
| `marketing analytics` | Tamu utama dan sumber reservasi dihubungkan lewat reservasi lalu `rooms.floor` | Filter properti perlu mengikuti relasi kamar dan izin pengguna. |

Migration/seed penting: `CreateHomiqSchema`, `DefaultDataSeeder`, `SyncGuesthouseRooms`, `AddExpenseGuesthouses`, `AddOwnerGuesthouseSetups`, `AddExtraBedManagement`, serta migration maintenance. Seeder mengambil digit pertama nomor kamar untuk mengisi `floor`; ini hanya aman untuk pola kamar HomiQ sekarang.

## Titik kode yang membatasi HomiQ ke GH 1–5

| Area | Temuan utama |
| --- | --- |
| Hak akses dan akun | `app/Libraries/RoleAccess.php` berisi `Owner GH 1`–`Owner GH 5`; `app/Controllers/SettingsController.php` menyediakan role tersebut sebagai role global. |
| Reservasi dan tamu | `app/Controllers/ReservationController.php` membatasi filter GH ke 1–5 dan membaca guesthouse lewat `rooms.floor`; termasuk daftar, ekspor, booking baru, ketersediaan, detail, dan operasi booking. `GuestController` dan `GuestDirectory` membaca direktori tamu global. |
| Room Map, dashboard, pricing | `RoomMapController`, `DashboardController`, `RoomPricingController`, `app/Views/pages/room_map.php`, `dashboard.php`, dan `reservations.php` memakai `floor` atau daftar angka 1–5. Harga tersimpan per kamar, tetapi belum per properti. |
| Keuangan | `FinanceController` membatasi pilihan owner ke GH 1–5 dan memakai `rooms.floor`; `finance_owner.php`, `owner_setup.php`, dan export owner menampilkan GH tersebut. Pengeluaran alokasi beberapa GH dan budget bulanan belum terhubung ke properti. |
| Operasional | `OpsController` membatasi filter/alokasi expense ke GH 1–5. `ops_panel.php` dan `expense_edit.php` mengulang daftar tersebut. Inventory, extra bed, maintenance summary, shifts dan budget berjalan tanpa konteks properti konsisten. |
| Housekeeping | `HousekeepingController` memvalidasi GH dengan pola angka 1–5; `housekeeping.php` menampilkan pilihan tetap tersebut. Task kebersihan belum terikat ke properti atau kamar. |
| Analytics | `MarketingAnalyticsService`, `MarketingAnalyticsAuditService`, `MarketingAnalyticsQueryService`, dan panel analytics mengelompokkan guesthouse dari `rooms.floor`; beberapa validasi juga menerima hanya 1–5. |
| Header | `app/Views/components/header.php` menampilkan nama Adiputra Guesthouse statis. Dropdown properti nantinya perlu mengambil daftar berdasarkan izin pengguna. |

Pencarian repository menemukan validasi/list statis 1–5 di `ReservationController`, `RoomMapController`, `DashboardController`, `FinanceController`, `OpsController`, `HousekeepingController`, `RoleAccess`, `SettingsController`, `MarketingAnalyticsService`, `MarketingAnalyticsAuditService`, serta view reservasi, dashboard, room map, housekeeping, owner finance/setup dan operasional. Semua ini perlu diganti dengan data properti dari database pada fase yang sesuai.

## Endpoint dan data yang harus ikut dibatasi

Seluruh rute aplikasi terautentikasi didefinisikan di `app/Config/Routes.php`. Filter menu yang sekarang ada membatasi jenis role, tetapi belum membatasi role berdasarkan properti.

| Modul | Rute yang perlu scope properti |
| --- | --- |
| Dashboard | `GET /dashboard`, `/dashboard/metrics`, `/dashboard/activity/{segment}`, `/dashboard/recent-reservations/export` |
| Housekeeping | `GET /housekeeping`, `/housekeeping/panel`; `POST /housekeeping/rooms/{id}/status`, `/housekeeping/tasks`, `/housekeeping/tasks/{id}/status`, `/housekeeping/tasks/{id}/delete` |
| Room Map | `GET /room-map`, `/room-map/grid` |
| Front Desk dan reservasi | `GET /reservations`, `/reservations/export`, `/reservations/new`, `/reservations/available-rooms`, `/reservations/{id}`, `/reservations/{id}/invoice`; `POST /reservations`, `/reservations/{id}/payments`, `/room-price`, `/booking-com-settlement`, `/services`, `/stay-dates`, `/important-note`, `/reconciliation`, `/status` |
| Dokumen tamu/pembayaran | `GET` dan `POST /reservations/{id}/ktp`; `GET` dan `POST /payments/{id}/proof`; `POST /payments/{id}/date` |
| Direktori tamu | `GET /guests`, `/guests/suggestions`; `POST /guests`, `/guests/{id}`, `/guests/{id}/delete` |
| Harga kamar | `GET /room-pricing`; `POST /room-pricing/all`, `/room-pricing/{id}` |
| Finance | `GET /finance`, `/finance/panel`, `/finance/export`, `/finance/owner`, `/finance/owner/export`, `/finance/owner-setup`; `POST /finance/owner-setup`, `/finance/owner-setup/{id}/delete` |
| Operasional | `GET /ops`, `/ops/panel`, `/ops/expenses/{id}/edit`, `/ops/expenses/{id}/receipt`; `POST /ops/budgets`, `/ops/maintenance/rooms/{id}`, `/ops/expenses`, `/ops/expenses/{id}`, `/ops/expenses/{id}/delete`, `/ops/inventory`, `/ops/inventory/amenities/{id}`, `/ops/inventory/{id}/stock`, `/ops/inventory/{id}/vendor-whatsapp`, `/ops/extra-beds/settings`, `/ops/shifts` |
| Maintenance | `GET /ops/maintenance/work-orders/{id}`, `/ops/maintenance/attachments/{id}`, `/ops/maintenance/schedules/{id}`, `/ops/maintenance/reports/export`; `POST` pada `/ops/maintenance`, `/work-orders/{id}/assign`, `/classification`, `/work`, `/parts`, `/parts/{id}/return`, `/attachments`, `/complete`, `/transition`, `/schedules`, `/schedules/generate`, `/schedules/{id}`, `/categories`, `/categories/{id}`, `/assets`, `/assets/{id}` |
| Pengaturan akses | `GET /settings`, `/settings/panel`; `POST /settings/users`, `/settings/users/{id}`, `/settings/users/{id}/delete` perlu mengelola assignment role per properti. |

Rute yang mengirim file atau mengubah data berdasarkan ID paling sensitif terhadap akses lintas properti: ekspor reservasi/dashboard/finance/maintenance, invoice, foto KTP, bukti pembayaran, tanda terima expense, lampiran maintenance, perubahan pembayaran/status/harga/tanggal, serta unggahan. Menyembunyikan link di UI tidak cukup; controller/filter perlu memvalidasi properti objek terhadap properti aktif dan assignment role.

## Header yang diminta

Screenshot menunjukkan teks tanggal/jam terlalu rapat ke garis pemisah properti. `public/css/app.css` kini menjaga metadata header agar tidak menyusut melewati separator dan membuat metadata turun ke baris baru pada lebar 1200–1600 px. Layout mengambil versi CSS dari `filemtime`, dan `GET http://localhost:8080/css/app.css` mengembalikan HTTP 200. Verifikasi visual setelah login belum dilakukan.

## Keputusan yang perlu dibawa ke Fase 1–2

1. Profil tamu: tetap terpisah per properti (rekomendasi demi isolasi role) atau berbagi profil global dengan relasi izin.
2. Operasional yang boleh lintas properti: user/admin pusat, katalog kategori maintenance, vendor, stok, biaya perusahaan, shift, dan laporan gabungan.
3. Guesthouse HomiQ: pertahankan GH 1–5 sebagai unit di dalam Adiputra HomiQ; jangan menafsirkannya sebagai properti tersendiri.
4. Nama/kode dan nomor kamar 3 kamar Homestay Mitra serta 7 kamar Kos Mitra akan dimasukkan sebagai konfigurasi data setelah skema dan akses siap.

## Berkas utama untuk implementasi lanjutan

- Database: `app/Database/Migrations`, `app/Database/Seeds/DefaultDataSeeder.php`.
- Akses: `app/Libraries/RoleAccess.php`, `app/Filters`, `app/Controllers/SettingsController.php`, `app/Config/Routes.php`.
- Booking: `ReservationController.php`, `GuestController.php`, `RoomMapController.php`, `RoomPricingController.php`, `DashboardController.php`.
- Finance/operasional: `FinanceController.php`, `OpsController.php`, `HousekeepingController.php`, `MaintenanceController.php`, `MaintenanceScheduleController.php`, `RoomAssetController.php`, laporan terkait.
- Analytics dan UI: `MarketingAnalyticsService.php`, `MarketingAnalyticsAuditService.php`, `app/Views/components/header.php`, halaman/view panel modul, `public/css/app.css`, `public/js/app.js`.
