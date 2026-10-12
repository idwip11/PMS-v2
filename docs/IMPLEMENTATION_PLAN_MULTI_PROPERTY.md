# Rencana Implementasi PMS Multi Properti

Status: Fase 0–5 selesai pada aplikasi/database lokal; Fase 6 (uji penerimaan dan rilis) belum dimulai. Rincian perubahan database untuk rilis dicatat di [ledger migrasi database](DATABASE_MIGRATION_LEDGER.md). **Production Rumahweb belum diperbarui.**

## Sasaran dan kondisi saat ini

| Properti | Inventaris awal | Posisi dalam rencana |
| --- | ---: | --- |
| Adiputra Guesthouse by HomiQ | 5 GH, 29 kamar saat ini | Seluruh data lama dipertahankan dan dipetakan ke properti ini |
| Homestay Mitra | 3 kamar | Properti baru |
| Kos Mitra | 7 kamar | Properti baru |

Pengguna memilih properti di header. Menu dan alur kerja tetap sama, tetapi semua data yang ditampilkan dan semua tindakan mengikuti properti yang dipilih. Dropdown hanya menampilkan properti yang boleh diakses pengguna. Satu akun dapat mempunyai role berbeda pada tiap properti; sebagai contoh, Finance HomiQ tidak mendapat akses ke Homestay Mitra atau Kos Mitra. Admin pusat dapat diberi akses lintas properti secara eksplisit.

Aplikasi sekarang menggunakan PHP 8.2+ dan CodeIgniter 4.7, MySQL/MariaDB, Bootstrap 5.3, HTMX 2, JavaScript biasa, serta Chart.js. `npm run dev` menjalankan server PHP dan MySQL lokal; npm bukan framework aplikasi. Pengembangan tetap memakai stack tersebut dan migrasi database CodeIgniter. Model AI di bawah adalah bantuan untuk *mengerjakan kode di Codex*, bukan komponen yang harus dipasang ke PMS.

Temuan yang memengaruhi rancangan: `rooms.room_number` unik untuk seluruh database; GH HomiQ 1–5 disimpulkan dari nomor kamar dan dibatasi di sejumlah controller/view; `users.role` adalah satu string untuk seluruh aplikasi. Beberapa data operasional, seperti expense dan inventaris, belum memiliki identitas properti. Karena itu, menambah nomor GH 6–7 saja tidak cukup untuk pemisahan data dan hak akses.

## Fase 0 — Inventaris dan perbaikan header

- [x] Petakan relasi tabel, batas GH 1–5, role, halaman, dan endpoint yang perlu dicakup; rinciannya ada di [inventaris Fase 0](PHASE0_INVENTORY.md).
- [x] Rapikan aturan CSS header supaya jam/tanggal tidak menyusut melewati garis pemisah; pada layar menengah, metadata header turun ke baris berikutnya.
- [x] Pastikan stylesheet terbaru dilayani aplikasi lokal (HTTP 200). Pemeriksaan visual setelah login belum dilakukan.
- Hasil: daftar titik perubahan tersedia dan perubahan header sudah berada di `public/css/app.css`.
- Model Codex: **GPT-6 Luna, medium** untuk perbaikan CSS yang terbatas; **GPT-6 Sol, medium** untuk inventaris kode.

## Fase 1 — Fondasi database dan migrasi data HomiQ

- [x] Buat tabel `properties` dengan identitas stabil: HomiQ ID 1, Homestay Mitra ID 2, Kos Mitra ID 3. Hanya HomiQ aktif selama modul belum memakai scope properti.
- [x] Tambah `property_id` ke kamar, tamu, reservasi, expense, inventaris, stok Extra Bed, shift, cleaning task, target/budget, dan setup owner. Data lama mendapat ID 1 tanpa mengubah nilai kolom lama.
- [x] Ubah unik nomor kamar menjadi `(property_id, room_number)`; pisahkan `rooms.guesthouse_number` dari `floor` dengan menyalin nomor GH 1–5 lama. Unik SKU, owner setup, dan budget bulanan juga menjadi per properti.
- [x] Tegakkan konsistensi properti reservasi terhadap kamar dan tamu melalui foreign key gabungan. Pembayaran, invoice, dan file mengikuti relasi reservasi/tamu yang sudah ada.
- [x] Jalankan migrasi pada salinan database lalu database lokal utama; jumlah data utama dan kesesuaian properti diverifikasi.
- [x] Masukkan 3 kamar Homestay Mitra (01–03) dan 7 kamar Kos Mitra (k1–k7) pada Fase 3. Tipe kamar sementara bernama Homestay/Kos sesuai properti; tarif berlaku pada properti, bukan `price_per_night` setiap kamar.
- Hasil: fondasi dan backfill HomiQ selesai. Modul bisnis belum menggunakan properti aktif pada query dan form; cakupan data tersebut menjadi pekerjaan Fase 3–5.
- Model Codex: **GPT-6 Astra, high** untuk desain skema dan migrasi data; **GPT-6 Sol, high** untuk implementasi migrasi.

## Fase 2 — Role per properti dan pemilihan properti

- [x] Buat tabel `user_property_roles` (`user_id`, `property_id`, `role`) dan backfill seluruh akun lama ke HomiQ saja. Role owner GH 1–5 tetap terikat pada GH di dalam HomiQ.
- [x] Tambah properti aktif ke sesi setelah login dan dropdown di header. Hanya properti yang tercatat untuk akun tersebut yang muncul; pergantian properti memvalidasi hak akses di server.
- [x] Sinkronkan ulang assignment/role dari database pada tiap request, lalu jalankan izin fitur berdasarkan role properti aktif. Endpoint dengan ID objek utama (termasuk bukti pembayaran, foto KTP, receipt, dan file maintenance) memeriksa kepemilikan properti; ID objek penting di POST juga divalidasi.
- [x] Tambahkan pengelolaan assignment role per properti di Settings. Akun lama tidak otomatis mendapat role Mitra.
- [x] Selama query/form bisnis Fase 3–5 belum di-scope, setiap pilihan Mitra hanya membuka halaman *properti dalam persiapan*. Semua route modul lama, termasuk HTMX dan ekspor, diblokir. Ini adalah pagar sementara agar data HomiQ tidak tampil sebagai data Mitra; belum berarti modul Mitra siap dipakai.
- Hasil uji lokal: Finance HomiQ tidak melihat Mitra pada dropdown dan POST pilihan Mitra langsung ditolak; akun uji dua properti berganti role Finance/Marketing sesuai assignment. Detail uji dan migrasi ada di ledger.
- Model Codex: **GPT-6 Astra, high** untuk desain otorisasi dan peninjauan akses; **GPT-6 Sol, high** untuk implementasi filter, session, dan UI.

## Fase 3 — Reservasi dan Front Desk

- [x] Terapkan cakupan properti pada harga kamar, New Booking, ketersediaan kamar, Room Map, reservasi grup, check-in/out, layanan tambahan, pembayaran/DP, bukti pembayaran, invoice, dan data tamu.
- [x] Satu booking grup berada dalam satu properti. Nomor booking tetap unik; setiap kamar yang dipilih harus milik properti aktif.
- [x] Booking hanya menawarkan kamar properti aktif; setelah menyimpan, Room Map tetap pada properti aktif. Reservasi Mitra tidak muncul di HomiQ.
- [x] Homestay Mitra: Rp600.000/malam untuk satu rumah; reservasi memblokir kamar 01, 02, 03 sebagai satu paket. Check-in/out dan pembatalan juga berlaku satu paket. Kos Mitra: Rp500.000/kamar/bulan; bulan dihitung sejak check-in dan checkout hanya pada kelipatan bulan, tanpa prorata (tanggal 31 diklem ke hari terakhir bulan yang lebih pendek).
- [x] Migrasi dan pengujian booking/isolasi dijalankan pada salinan DB, lalu migrasi diterapkan ke DB lokal utama. Front Desk Mitra dibuka **hanya pada localhost** untuk akun admin yang diberi assignment eksplisit. Modul Finance/Operasional/Dashboard/Analytics Mitra tetap terkunci sampai Fase 4–5.
- Hasil: HomiQ tetap 18 reservasi, 29 kamar, 13 pembayaran setelah migrasi. Detail uji dan manifest ada di ledger. Production belum disentuh.
- Model Codex: **GPT-6 Sol, high** untuk perubahan modul; **GPT-6 Astra, high** untuk peninjauan logika booking dan pembayaran.

## Fase 4 — Operasional dan keuangan

- [x] Terapkan properti aktif pada expense, inventory/amenities, Extra Bed, maintenance (tiket, aset, jadwal, laporan, dan bukti), housekeeping, shift, budget operasional, serta laporan arus kas/laba-rugi perusahaan. Owner HomiQ tetap memakai GH 1–5.
- [x] Biaya baru Mitra dimiliki tepat satu properti dan tidak disalin ke properti lain. Alokasi GH HomiQ lama dipertahankan; expense bersama beberapa GH HomiQ dibagi merata dalam laporan owner sehingga tidak dihitung berulang.
- [x] Mitra **belum memakai bagi hasil owner** sesuai keputusan pengguna. Menu/endpoint owner dan pengaturan owner Mitra dikunci; laporan keuangan perusahaan per properti tetap tersedia. Angka 70% HomiQ tidak dipakai sebagai default Mitra.
- [x] Aksi tulis dan dokumen mengikuti role properti aktif; objek dari properti lain ditolak. Kategori maintenance masih katalog global yang bisa dibaca/dipakai Mitra, tetapi hanya diubah dari HomiQ. Target keuangan dan budget marketing belum mempunyai layar/logic aktif; skemanya sudah per properti. Pengaktifannya membutuhkan spesifikasi produk tersendiri, sebagaimana dicatat pada Fase 5.
- Hasil: stok, biaya, pembayaran, dan laporan keuangan perusahaan tiap properti terpisah di localhost. Uji salinan database dan manifest source ada di ledger. Dashboard/analytics Mitra dibuka pada Fase 5; tidak ada perubahan production.
- Model Codex: **GPT-6 Sol, high** untuk implementasi; **GPT-6 Astra, high** untuk pemeriksaan aturan keuangan lintas properti.

## Fase 5 — Dashboard, analytics, dan ekspor

- [x] Terapkan filter properti ke okupansi, pendapatan, marketing analytics, laporan owner, daftar reservasi, serta ekspor Excel/PDF. Dashboard/Analytics Mitra kini terbuka sesuai role aktif; pilihan GH 1–5 hanya ditampilkan untuk HomiQ.
- [x] Pastikan perhitungan transit, reservasi batal, grup kamar, dan pembayaran tetap sesuai aturan yang sudah berjalan, kini per properti. Reservasi transit dihitung satu kamar-malam dalam Analytics dan satu malam per booking pada loyalitas.
- [x] Uji Dashboard dan Analytics pada HomiQ/Homestay/Kos, audit rekonsiliasi, dan ekspor Recent Reservations; hasil serta manifest file ada di ledger. Tidak ada migrasi database baru di Fase 5.
- Hasil: angka sebuah properti tidak berubah karena transaksi properti lain; ekspor mengikuti properti aktif dan izin penggunanya. Target keuangan dan budget marketing sudah berskema per properti tetapi tetap belum punya antarmuka atau aturan bisnis; fitur target/budget memerlukan spesifikasi produk terpisah dan **bukan bagian dari KPI/ekspor yang diaktifkan di Fase 5 ini**.
- Model Codex: **GPT-6 Sol, medium/high** untuk query dan tampilan; **GPT-6 Astra, high** untuk audit konsistensi angka dan kebocoran data.

## Fase 6 — Uji penerimaan dan rilis Rumahweb

- Jalankan migrasi serta pemeriksaan pada salinan database lokal dahulu. Periksa contoh akun Admin pusat, Finance HomiQ, Finance Mitra, dan staf yang memiliki dua role berbeda.
- Uji alur reservasi dan pembayaran pada ketiga properti, ekspor, file bukti, laporan, serta akses langsung lewat URL yang tidak berhak.
- Siapkan cadangan database dan berkas production, jalankan migrasi sebelum mengaktifkan pemilih properti, lalu cocokkan jumlah reservasi/pendapatan lama HomiQ sebelum dan sesudah rilis. Siapkan prosedur kembali ke versi lama bila ada masalah.
- Hasil: ketiga properti dapat dipakai tanpa mengubah riwayat HomiQ atau membuka akses lintas properti yang tidak diizinkan.
- Model Codex: **GPT-6 Astra, high** untuk peninjauan akhir migrasi dan keamanan; **GPT-6 Sol, medium** untuk daftar rilis dan perbaikan temuan.

## Urutan yang disarankan

Fase 0 → 1 → 2 → 3 → 4 → 5 → 6. Fase 1–2 menjadi fondasi wajib sebelum fitur Mitra dibuka. Rilis dapat dilakukan sesudah seluruh modul yang dipakai kedua properti lulus pemeriksaan, karena menu yang sama harus benar-benar terisolasi per properti.

Panduan model resmi OpenAI: [model selection](https://developers.openai.com/api/docs/guides/model-selection) dan [code generation](https://developers.openai.com/api/docs/guides/code-generation). Ketersediaan model bergantung pada akun dan produk yang dipakai.
