# Implementation Plan — Operasional & Preventive Maintenance HomiQ PMS

## 1. Tujuan

Mengembangkan menu **Operasional** menjadi pusat pengelolaan aset dan maintenance hotel yang dapat:

- menyimpan kategori maintenance yang dapat ditambah sendiri oleh user, misalnya AC, TV, water heater, lampu, plumbing, pintu, dan furniture;
- memetakan perangkat/aset tersebut ke masing-masing kamar;
- membuat jadwal maintenance rutin dengan interval yang fleksibel;
- mencatat kerusakan insidental dan proses perbaikannya;
- melihat riwayat servis per kamar maupun per aset;
- menghubungkan spare part yang digunakan dengan stok inventory;
- mencatat teknisi, vendor, biaya, foto, dan hasil pekerjaan;
- memberikan peringatan pekerjaan yang akan jatuh tempo atau sudah terlambat.

Implementasi tetap menggunakan stack yang sudah berjalan: **CodeIgniter 4, MySQL, Bootstrap, HTMX, PHP Session, dan Docker untuk localhost**.

---

## 2. Kondisi Sistem Saat Ini

Modul Ops saat ini sudah memiliki tab:

1. Expenses
2. Amenities
3. Maintenance
4. Shifts

Fitur maintenance saat ini baru mencakup:

- memilih kamar;
- mengisi jenis masalah dalam bentuk teks bebas;
- memilih prioritas;
- membuat tiket;
- mengubah status Open, In Progress, atau Resolved;
- mengubah status kamar menjadi Maintenance saat tiket dibuat.

Keterbatasan yang perlu diselesaikan:

- belum ada master kategori maintenance;
- belum ada daftar aset/perangkat per kamar;
- belum ada jadwal preventive maintenance;
- belum ada penanggung jawab atau teknisi;
- belum ada histori perubahan status;
- belum ada tanggal mulai dan selesai pekerjaan;
- belum ada pencatatan hasil perbaikan dan biaya;
- belum ada relasi pemakaian spare part ke inventory;
- semua tiket otomatis memblokir kamar, termasuk pekerjaan ringan/rutin;
- stok hanya menyimpan saldo akhir, belum memiliki riwayat pergerakan.

---

## 3. Rekomendasi Struktur Menu

Label sidebar `Ops` diubah menjadi **Operasional**. Tab utama tetap dipertahankan agar perubahan tidak membingungkan user:

- Expenses
- Amenities
- Maintenance
- Shifts

Di dalam tab **Maintenance** ditambahkan sub-menu:

1. **Ringkasan** — pekerjaan jatuh tempo, terlambat, tiket terbuka, dan biaya bulan ini.
2. **Aset Kamar** — pemetaan AC, TV, dan aset lain ke tiap kamar.
3. **Jadwal Rutin** — preventive maintenance berulang.
4. **Work Order** — tiket kerusakan dan tugas maintenance yang harus dikerjakan.
5. **Riwayat** — histori pekerjaan, biaya, teknisi, dan spare part.
6. **Kategori** — master kategori yang dapat dikelola Admin/Manager.

Pada layar kecil, sub-menu dibuat horizontal-scroll agar lebar halaman tetap konsisten.

---

## 4. Alur Bisnis yang Diusulkan

### 4.1 Menambahkan kategori maintenance

Admin atau Manager dapat membuat kategori seperti:

- AC — servis rutin setiap 90 hari;
- TV — inspeksi rutin setiap 180 hari;
- Water Heater — inspeksi setiap 90 hari;
- Plumbing — inspeksi setiap 30 hari;
- Electrical — inspeksi setiap 90 hari.

Interval tersebut menjadi nilai default dan masih dapat diubah untuk aset tertentu.

### 4.2 Memetakan aset ke kamar

Contoh untuk kamar 101:

- AC 1 PK, Daikin, serial number tertentu;
- TV 43 inch, Samsung;
- Water Heater 15 liter;
- Smart lock/pintu;
- kamar mandi/plumbing.

Satu kategori dapat digunakan oleh banyak kamar. Satu kamar juga dapat memiliki lebih dari satu aset dalam kategori yang sama, misalnya dua unit AC.

### 4.3 Membuat jadwal rutin

Untuk setiap aset, user dapat menentukan:

- jenis pekerjaan/checklist;
- interval harian, mingguan, bulanan, atau tahunan;
- tanggal maintenance terakhir;
- tanggal maintenance berikutnya;
- teknisi internal atau vendor;
- estimasi durasi;
- apakah pekerjaan memblokir kamar.

Sistem membuat Work Order ketika jadwal memasuki batas pengingat.

### 4.4 Menangani kerusakan insidental

Staf memilih kamar dan aset, mengisi masalah, prioritas, foto, serta dampaknya terhadap kamar. Tiket berjalan melalui status:

`Open → Assigned → In Progress → Waiting Part → Completed → Verified/Closed`

Status `Cancelled` tersedia untuk tiket duplikat atau tidak valid.

### 4.5 Menyelesaikan pekerjaan

Teknisi mengisi:

- diagnosis;
- tindakan perbaikan;
- tanggal/jam mulai dan selesai;
- spare part yang digunakan;
- biaya jasa/vendor;
- foto sebelum dan sesudah;
- rekomendasi tindak lanjut;
- tanggal maintenance berikutnya.

Stok spare part berkurang dalam transaksi database yang sama. Pekerjaan tidak dapat ditutup jika stok yang dipakai melebihi stok tersedia, kecuali Admin melakukan penyesuaian stok terlebih dahulu.

### 4.6 Dampak terhadap status kamar

Tambahkan pilihan **“Blokir kamar selama pekerjaan”**.

- Jika aktif, status kamar menjadi `Maintenance`.
- Jika tidak aktif, status kamar tetap seperti sebelumnya.
- Setelah pekerjaan selesai, status kamar dikembalikan ke status sebelum maintenance atau menjadi `Dirty` jika perlu pemeriksaan housekeeping.

Dengan aturan ini, inspeksi TV atau pembersihan filter AC tidak selalu membuat kamar tidak dapat dijual.

---

## 5. Perubahan Database

Gunakan migration baru yang bersifat **additive**. Migration awal yang sudah dijalankan tidak diedit agar database localhost yang ada tetap aman.

### 5.1 Tabel baru `maintenance_categories`

| Kolom | Fungsi |
|---|---|
| `id` | Primary key |
| `name` | Nama kategori, unik, contoh AC |
| `code` | Kode singkat opsional |
| `description` | Penjelasan kategori |
| `default_interval_value` | Nilai interval default |
| `default_interval_unit` | Day, Week, Month, Year |
| `default_checklist` | Checklist JSON atau TEXT |
| `is_active` | Menonaktifkan kategori tanpa menghapus histori |
| timestamps | Audit waktu |

### 5.2 Tabel baru `room_assets`

| Kolom | Fungsi |
|---|---|
| `id` | Primary key |
| `room_id` | Relasi ke kamar |
| `maintenance_category_id` | Relasi ke kategori |
| `asset_name` | Nama/label aset |
| `brand`, `model`, `serial_number` | Identitas perangkat |
| `purchase_date`, `installed_at` | Informasi umur aset |
| `warranty_until` | Masa garansi |
| `condition_status` | Good, Attention, Broken, Retired |
| `notes` | Catatan |
| `is_active` | Soft deactivation |
| timestamps | Audit waktu |

Index penting: `room_id`, `maintenance_category_id`, dan unique nullable `serial_number`.

### 5.3 Tabel baru `maintenance_schedules`

| Kolom | Fungsi |
|---|---|
| `id` | Primary key |
| `room_asset_id` | Aset yang dirawat |
| `title` | Nama pekerjaan rutin |
| `checklist` | Langkah pemeriksaan |
| `frequency_value`, `frequency_unit` | Interval berulang |
| `last_completed_at` | Terakhir selesai |
| `next_due_at` | Jatuh tempo berikutnya |
| `reminder_days` | Pengingat sebelum jatuh tempo |
| `default_assignee_user_id` | Teknisi internal default |
| `default_vendor` | Vendor default |
| `blocks_room` | Apakah kamar harus diblokir |
| `is_active` | Status jadwal |
| timestamps | Audit waktu |

### 5.4 Pengembangan tabel `maintenance_tickets`

Tambahkan kolom berikut tanpa menghapus data lama:

- `ticket_number`;
- `maintenance_category_id` nullable;
- `room_asset_id` nullable;
- `maintenance_schedule_id` nullable;
- `assigned_to_user_id` nullable;
- `ticket_type`: Corrective atau Preventive;
- `scheduled_at`, `started_at`, `completed_at`, `verified_at`;
- `diagnosis`, `resolution`, `follow_up_notes`;
- `vendor_name`, `labor_cost`, `external_cost`;
- `blocks_room`, `previous_room_status`;
- `next_service_at`;
- `verified_by_user_id` nullable.

Kolom `issue_type` lama tetap dipertahankan sementara untuk kompatibilitas dan dapat diisi dari nama kategori.

### 5.5 Tabel baru `maintenance_status_logs`

Menyimpan setiap perpindahan status:

- `maintenance_ticket_id`;
- `from_status`, `to_status`;
- `changed_by_user_id`;
- `notes`;
- `created_at`.

Histori ini tidak boleh diedit atau dihapus melalui UI biasa.

### 5.6 Tabel baru `inventory_stock_movements`

Menjadikan stok dapat diaudit:

- `inventory_item_id`;
- `movement_type`: Opening, Purchase, Usage, Adjustment, Return;
- `quantity_change`: positif atau negatif;
- `balance_after`;
- `reference_type`, `reference_id`;
- `notes`;
- `created_by_user_id`;
- `created_at`.

### 5.7 Tabel baru `maintenance_ticket_items`

Relasi spare part dengan pekerjaan:

- `maintenance_ticket_id`;
- `inventory_item_id`;
- `quantity_used`;
- `unit_cost_snapshot`;
- `notes`.

### 5.8 Pengembangan `inventory_items`

Tambahkan:

- `item_type`: Amenity, Spare Part, Operational Supply;
- `unit_cost`;
- `sku` nullable;
- `is_active`.

Tab Amenities hanya menampilkan `item_type = Amenity`. Spare part ditampilkan di area Maintenance sehingga penamaan Amenities yang sudah disepakati tetap konsisten.

### 5.9 Tabel opsional `maintenance_attachments`

Digunakan untuk foto sebelum/sesudah atau invoice vendor:

- `maintenance_ticket_id`;
- `file_path`, `original_name`, `mime_type`, `file_size`;
- `attachment_type`: Before, After, Invoice, Other;
- `uploaded_by_user_id`;
- `created_at`.

Upload dibatasi pada JPG, PNG, dan PDF dengan ukuran maksimum yang dikonfigurasi.

---

## 6. Controller, Route, dan View

### Controller

Pisahkan tanggung jawab agar `OpsController` tidak semakin besar:

- `MaintenanceController` — ringkasan, work order, status, penyelesaian;
- `MaintenanceCategoryController` — CRUD kategori;
- `RoomAssetController` — CRUD aset per kamar;
- `MaintenanceScheduleController` — CRUD jadwal rutin;
- `InventoryController` — stok dan stock movement.

### Route utama

- `GET /ops/maintenance/summary`
- `GET /ops/maintenance/assets`
- `POST /ops/maintenance/assets`
- `POST /ops/maintenance/assets/{id}`
- `GET /ops/maintenance/schedules`
- `POST /ops/maintenance/schedules`
- `GET /ops/maintenance/work-orders`
- `POST /ops/maintenance/work-orders`
- `GET /ops/maintenance/work-orders/{id}`
- `POST /ops/maintenance/work-orders/{id}/assign`
- `POST /ops/maintenance/work-orders/{id}/status`
- `POST /ops/maintenance/work-orders/{id}/complete`
- `POST /ops/maintenance/work-orders/{id}/parts`
- `GET /ops/maintenance/history`
- `GET/POST /ops/maintenance/categories`

Semua endpoint tetap memakai auth, role filter, CSRF, dan partial HTMX.

### View/komponen

- `maintenance_summary.php`
- `room_assets.php`
- `room_asset_form.php`
- `maintenance_schedules.php`
- `maintenance_schedule_form.php`
- `maintenance_work_orders.php`
- `maintenance_work_order_detail.php`
- `maintenance_completion_form.php`
- `maintenance_history.php`
- `maintenance_categories.php`
- `stock_movement_history.php`

Filter server-side yang disediakan: kamar, kategori, status, prioritas, teknisi, rentang tanggal, dan overdue.

---

## 7. Hak Akses

| Role | Hak akses |
|---|---|
| Admin | Semua fitur termasuk master kategori dan adjustment stok |
| Manager | Semua operasional, verifikasi pekerjaan, laporan |
| Operational | Membuat, menerima, dan menyelesaikan work order; memakai spare part |
| Finance | Read-only biaya maintenance dan export |
| Role lain | Melaporkan masalah dan melihat tiket yang relevan |

Penghapusan kategori/aset yang sudah memiliki histori tidak dilakukan secara fisik; data hanya dinonaktifkan.

---

## 8. Otomasi dan Notifikasi

Tambahkan command CI4:

```bash
php spark maintenance:generate-due
```

Command dijalankan setiap hari melalui cPanel Cron saat deployment. Untuk localhost, command dapat dijalankan manual atau melalui Docker.

Tugas command:

1. mencari jadwal aktif yang memasuki tanggal reminder;
2. membuat satu work order preventive jika belum ada;
3. mencegah duplikasi dengan unique key schedule + due date;
4. menandai pekerjaan overdue;
5. memperbarui indikator pada dashboard.

Pada shared hosting yang belum memiliki Cron, sediakan tombol Admin **Generate pekerjaan jatuh tempo** sebagai fallback.

Notifikasi tahap awal cukup berupa badge dalam aplikasi. Email/WhatsApp diposisikan sebagai pengembangan lanjutan agar tidak menambah ketergantungan deployment.

---

## 9. Tahapan Implementasi

### Phase 1 — Master Data & Pemetaan Aset

- buat migration additive;
- buat kategori maintenance CRUD;
- buat daftar aset per kamar;
- seed kategori contoh AC, TV, Plumbing, Electrical, Water Heater;
- ubah tiket agar dapat memilih kategori dan aset;
- pertahankan kompatibilitas tiket lama.

**Hasil:** user dapat menentukan sendiri kategori dan melihat aset apa saja yang ada di setiap kamar.

### Phase 2 — Work Order & Riwayat Terstruktur

- detail work order;
- assignment teknisi/vendor;
- status workflow lengkap;
- diagnosis, tindakan, waktu pengerjaan, biaya;
- status log/audit trail;
- aturan blokir kamar yang aman;
- filter dan pencarian histori.

**Hasil:** setiap perbaikan memiliki jejak lengkap per kamar dan per aset.

### Phase 3 — Preventive Maintenance

- CRUD jadwal berulang;
- kalkulasi next due date;
- command `maintenance:generate-due`;
- overdue dan due-soon badges;
- checklist penyelesaian;
- pembaruan otomatis jadwal setelah pekerjaan selesai.

**Hasil:** maintenance rutin dapat direncanakan dan tidak bergantung pada ingatan staf.

### Phase 4 — Integrasi Inventory & Biaya

- klasifikasi stok Amenity/Spare Part/Supply;
- stock movement ledger;
- pemakaian dan pengembalian spare part;
- transaksi atomik saat penyelesaian work order;
- riwayat stok yang menunjuk ke nomor work order;
- total biaya material + jasa per pekerjaan.

**Hasil:** user dapat mengetahui spare part apa yang dipakai, kapan dipakai, dan untuk kamar mana.

### Phase 5 — Dashboard, Attachment, dan Finalisasi

- ringkasan maintenance;
- foto sebelum/sesudah dan invoice;
- export CSV laporan;
- responsive/mobile QA;
- permission dan security test;
- dokumentasi Cron untuk localhost dan Rumahweb;
- backup/restore test sebelum deployment.

**Hasil:** modul siap digunakan dalam operasional dan siap dipindahkan ke shared hosting.

---

## 10. KPI Ringkasan yang Disarankan

- work order Open dan In Progress;
- preventive maintenance jatuh tempo 7/30 hari;
- pekerjaan overdue;
- kamar yang sedang diblokir;
- biaya maintenance bulan berjalan;
- aset dengan kerusakan berulang;
- rata-rata waktu penyelesaian (MTTR);
- spare part low stock.

KPI dihitung dari data transaksi, bukan disimpan manual.

---

## 11. Aturan Integritas Data

- serial number aset unik jika diisi;
- quantity stock tidak boleh negatif;
- setiap perubahan stok wajib membuat stock movement;
- work order preventive tidak boleh duplikat untuk schedule dan due date yang sama;
- tiket Completed harus memiliki resolution dan completed_at;
- tiket Closed/Verified tidak dapat diedit oleh Operational;
- kategori dan aset yang sudah dipakai hanya dapat dinonaktifkan;
- transaksi penyelesaian pekerjaan, pemakaian stok, dan update jadwal harus atomik;
- semua input teks di-escape dan semua POST menggunakan CSRF;
- file upload divalidasi berdasarkan MIME, ekstensi, dan ukuran.

---

## 12. Pengujian dan Acceptance Criteria

### Functional

- user dapat membuat kategori maintenance baru tanpa perubahan kode;
- user dapat menambahkan beberapa aset ke satu kamar;
- user dapat membuat jadwal berbeda untuk setiap aset;
- command menghasilkan work order tepat satu kali;
- work order dapat ditugaskan, dikerjakan, dan diverifikasi;
- riwayat dapat difilter berdasarkan kamar/aset/tanggal;
- spare part yang dipakai mengurangi stok dan membuat stock movement;
- riwayat stok membuka referensi work order terkait;
- tiket ringan tidak mengubah status kamar jika `blocks_room` tidak aktif;
- tiket yang memblokir kamar mengembalikan status kamar secara benar setelah selesai.

### Technical

- seluruh migration up/down lulus pada database test;
- tidak ada perubahan atau kehilangan data pada tiket lama;
- controller dan migration lulus PHP lint;
- permission test untuk setiap role;
- CSRF HTMX tetap diperbarui setelah request;
- tampilan desktop dan mobile tidak berubah lebar saat berganti sub-menu;
- Docker web, MySQL, dan phpMyAdmin tetap berjalan di localhost.

---

## 13. Estimasi

Estimasi realistis untuk satu developer:

| Phase | Estimasi |
|---|---:|
| Phase 1 | 2–3 hari |
| Phase 2 | 3–4 hari |
| Phase 3 | 2–3 hari |
| Phase 4 | 2–3 hari |
| Phase 5 | 2–3 hari |
| **Total** | **11–16 hari kerja** |

Estimasi dapat bertambah jika attachment disimpan ke cloud, menggunakan WhatsApp API, atau membutuhkan approval bertingkat.

---

## 14. Batas Scope Versi Pertama

Termasuk dalam versi pertama:

- kategori dinamis;
- aset per kamar;
- preventive dan corrective maintenance;
- work order dan histori;
- teknisi/vendor;
- biaya dan spare part;
- stock ledger;
- reminder dalam aplikasi;
- foto/invoice lokal;
- laporan CSV.

Ditunda ke versi berikutnya:

- WhatsApp/email otomatis;
- QR code pada setiap aset;
- purchase order dan approval vendor;
- multi-property/guesthouse penuh;
- predictive maintenance;
- aplikasi teknisi offline.

QR code layak menjadi pengembangan berikutnya: staf cukup memindai label AC/TV untuk membuka histori aset dan membuat tiket tanpa mencari kamar secara manual.

---

## 15. Keputusan Teknis yang Direkomendasikan

1. Pertahankan satu modul Operasional, tetapi pecah controller per domain.
2. Gunakan migration baru; jangan mengubah migration awal yang sudah diterapkan.
3. Jadikan `maintenance_tickets` sebagai Work Order agar data lama tetap dapat digunakan.
4. Gunakan status log dan stock movement sebagai audit trail yang immutable.
5. Pisahkan tampilan Amenities dan Spare Part memakai `item_type`, tetapi tetap menggunakan satu sumber inventory.
6. Gunakan cPanel Cron + CI4 Spark command, tanpa queue server tambahan.
7. Mulai implementasi dari Phase 1 dan lakukan verifikasi per phase sebelum melanjutkan.
