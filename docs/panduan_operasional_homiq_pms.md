# Panduan Operasional HomiQ PMS

Dokumen ringkas untuk penggunaan harian HomiQ PMS. Berlaku sesuai implementasi aplikasi saat ini.

## 1. Ringkasan aplikasi

HomiQ PMS adalah sistem internal untuk mengelola:

- Reservasi kamar, booking multi-kamar, pembayaran, dan pembatalan.
- Data tamu serta unggahan foto KTP.
- Room Map dan status kamar.
- Housekeeping, inventaris, Extra Bed, maintenance, shift, dan pengeluaran.
- Laporan keuangan perusahaan dan laporan per Owner GH.
- Analytics okupansi, profil tamu, dan forecasting.

## 2. Alur kerja harian singkat

1. Front Desk membuat booking dari tombol **New Booking**.
2. Pilih tanggal, satu atau beberapa kamar, data tamu, layanan tambahan, lalu pembayaran awal bila ada.
3. Pantau reservasi dari **Room Map** atau **Reservasi List**.
4. Saat tamu datang, lakukan **Check In**; saat pulang lakukan **Check Out**.
5. Catat pelunasan atau pembayaran tambahan pada detail booking.
6. Pantau notifikasi sarapan, Early Check In, Late Checkout, housekeeping, dan operasional dari Dashboard.
7. Finance melakukan rekonsiliasi serta memeriksa laporan perusahaan dan Owner GH.

## 3. Booking dan data tamu

### Data yang diisi

- Nama lengkap, email, telepon, asal, catatan tamu, dan sumber booking.
- Check-in, check-out, kamar, jumlah tamu, serta permintaan khusus.
- Satu booking dapat memuat lebih dari satu kamar. Sistem membuat satu **booking group** dan satu ID reservasi per kamar.
- Kamar yang sudah terisi pada tanggal yang sama atau sedang maintenance tidak dapat dipesan.

### Foto KTP

- Foto KTP dapat diunggah saat membuat booking, di bawah kolom **Asal**.
- File gambar dikompresi otomatis hingga maksimal sekitar **200 KB**.
- Foto KTP dapat dibuka dari detail reservasi melalui tombol **Lihat Foto KTP**.
- Dokumen hanya digunakan untuk kebutuhan operasional; jangan membagikan tautan atau file KTP ke pihak yang tidak berwenang.

### Harga dan pembayaran awal

- Harga standar dihitung dari harga kamar × jumlah malam, ditambah layanan yang dipilih.
- Nilai **Konfirmasi total booking** dapat digunakan bila ada harga khusus atau diskon.
- Status pembayaran awal: **Belum Bayar**, **DP**, atau **Lunas**.
- Bukti pembayaran hanya dapat diunggah untuk metode **Transfer** dan gambar dikompresi otomatis.
- Pembayaran metode OTA dapat dicatat sebagai pending lalu diberi tanggal saat dana diterima.

## 4. Layanan tambahan dan pembagian pendapatan

| Layanan | Masuk pendapatan perusahaan | Masuk pendapatan Owner GH |
|---|:---:|:---:|
| Harga kamar, termasuk penyesuaian harga khusus | Ya | Ya |
| Early Check In | Ya | Ya, kategori Charge Tamu |
| Late Checkout | Ya | Ya, kategori Charge Tamu |
| Breakfast | Ya | Tidak |
| Biaya Extra Bed | Ya | Tidak |
| Laundry | Ya | Tidak |
| Massage | Ya | Tidak |

Ketentuan penting:

- Breakfast, Extra Bed, Laundry, dan Massage tetap terlihat sebagai bagian transaksi perusahaan, tetapi dikeluarkan dari pendapatan Owner GH dan perhitungan bagi hasil Owner/Pengelola.
- Untuk DP atau pembayaran sebagian, sistem menghitung porsi Owner GH secara proporsional berdasarkan komposisi invoice. Dengan demikian, layanan perusahaan tidak ikut terbagi ke Owner GH.
- Laporan Owner GH hanya menghitung transaksi pembayaran berstatus **Paid**.

## 5. Status reservasi dan kamar

| Status reservasi | Status kamar | Arti |
|---|---|---|
| Confirmed | Tetap tersedia pada hari ini, tetapi terblokir untuk tanggal booking | Booking aktif sebelum tamu datang. |
| Checked_In | Occupied | Tamu sedang menginap. |
| Checked_Out | Dirty | Tamu sudah keluar; kamar perlu dibersihkan. |
| Cancelled | Available | Booking dibatalkan; kamar dapat dipesan kembali. |

Catatan: Room Map tidak menampilkan reservasi yang berstatus **Cancelled**.

## 6. Pembatalan reservasi dan uang pembayaran

### Batalkan satu kamar pada booking multi-kamar

- Di tabel **Kamar dalam Booking**, gunakan tombol **Batalkan kamar ini** pada kamar yang ingin dibatalkan.
- Sistem hanya membatalkan kamar tersebut. Kamar lain pada booking yang sama tetap aktif.
- Kamar yang dibatalkan berubah menjadi **Available** dan langsung bisa dipesan kembali.
- Pembayaran yang sudah dialokasikan pada kamar tersebut diubah menjadi **Kredit/refund pending**.

### Batalkan seluruh booking

- Gunakan tombol **Batalkan Seluruh Booking (N kamar)**.
- Sistem membatalkan seluruh kamar dalam booking group dan mengembalikan semuanya menjadi **Available**.
- Semua pembayaran berstatus **Paid** yang terkait kamar-kamar tersebut berubah menjadi **Kredit/refund pending**.

### Ketentuan uang: apakah hangus atau dikembalikan?

Sistem **tidak otomatis menetapkan uang hangus** dan **tidak otomatis melakukan refund**.

- Pembayaran yang terdampak pembatalan tidak dihapus; riwayat dan bukti tetap tersimpan.
- Statusnya berubah menjadi **Kredit/refund pending**, sehingga tidak lagi dihitung sebagai pemasukan aktif berstatus `Paid` pada laporan.
- Keputusan akhir—refund penuh, refund sebagian, kredit untuk booking berikutnya, atau uang hangus—harus ditentukan oleh manajemen sesuai kebijakan hotel dan dicatat secara terpisah saat ini.

Rekomendasi kebijakan internal: tetapkan aturan tertulis berdasarkan jarak pembatalan terhadap tanggal check-in, lalu simpan bukti komunikasi/refund bersama catatan booking.

### Perhatian untuk layanan pada booking multi-kamar

- Layanan tambahan dicatat pada baris kamar utama/pertama saat booking dibuat.
- Bila membatalkan kamar utama, periksa kembali kebutuhan Breakfast, Laundry, Massage, Extra Bed, Early Check In, dan Late Checkout untuk kamar yang tersisa.
- Aplikasi belum menyediakan perubahan otomatis nominal layanan setelah sebagian kamar dibatalkan; lakukan evaluasi dan catat penyesuaian sesuai kebijakan operasional.

## 7. Dashboard

Dashboard menampilkan periode dan filter GH yang dipilih:

- Occupancy, Revenue, Net Profit, Average Stay, dan Rooms Rented.
- Grafik Revenue vs Expense.
- Check-in dan check-out hari ini.
- Operational Alerts: kamar dirty, maintenance terbuka, dan stok rendah.
- **Notif Pesanan Sarapan** untuk check-in maksimal 7 hari ke depan: nama tamu, nomor kamar, serta tanggal check-in. Panel dibatasi maksimal 12 data agar tetap ringan.
- Notifikasi Early Check In H-1 dan Late Checkout hari ini.
- Cleaning task hari ini serta reservasi terbaru.

Untuk role **Admin** dan **Staff**, nilai Revenue dan Net Profit pada Dashboard disensor.

## 8. Front Desk

### Room Map

- Menampilkan kalender okupansi kamar serta status pembayaran.
- Filter GH dan bulan tersedia.
- Status pembayaran: Lunas, DP, Belum Bayar, dan Terekonsiliasi.
- Reservasi dibatalkan tidak lagi memblokir kamar pada Room Map.

### Reservasi List

- Cari berdasarkan nama tamu, ID reservasi, telepon, atau nomor kamar.
- Filter status serta rentang tanggal check-in.
- Detail reservasi menampilkan invoice, riwayat pembayaran, bukti bayar, foto KTP, rekonsiliasi, dan aksi status.

### Data Tamu dan Harga Kamar

- **Data Tamu** menyimpan direktori tamu dari input manual maupun booking.
- **Harga Kamar** digunakan untuk mengatur tarif properti, menerapkan harga massal, serta mengedit nama/nomor kamar dan tarif spesifik masing-masing kamar (termasuk tarif per kamar per bulan pada Kos Mitra).

## 9. Finance

### Keuangan Perusahaan

- Menampilkan seluruh pembayaran berstatus **Paid**, pengeluaran yang tidak ditolak, arus kas, dan outstanding.
- Pendapatan perusahaan mencakup pembayaran kamar serta seluruh layanan tambahan.
- Transaksi dapat diekspor ke Excel.

### Keuangan Owner/Investor

- Laporan dipisahkan per GH dan periode.
- Pendapatan Owner GH terdiri dari **Penyewaan** dan **Charge Tamu** (Early Check In + Late Checkout).
- Breakfast, Extra Bed, Laundry, dan Massage tidak masuk ke laba Owner GH.
- Pengeluaran operasional GH dikurangkan dari pendapatan untuk menghasilkan laba/rugi bersih.
- Bagi hasil hanya dihitung dari laba positif. Bila laba/rugi bersih nol atau negatif, nilai yang dibagikan adalah nol.
- Persentase Owner diatur pada **Setup Owner**. Sisa persentase adalah fee kerja sama pengelola.

## 10. Analytics

- Menyajikan ringkasan tamu, booking, asal tamu, dan performa GH dari data reservasi aktif.
- Reservasi berstatus **Cancelled** tidak dihitung.
- **Pendapatan layanan tambahan** merinci dana yang sudah diterima untuk Breakfast, Extra Bed, Laundry, dan Massage. DP dihitung proporsional dari komposisi invoice; panel ini adalah insight kas perusahaan dan bukan pendapatan Owner GH.
- **Cancellation Report** menggunakan tanggal pembatalan, menampilkan jumlah booking/kamar yang dibatalkan, nilai tagihan, dan nilai Kredit/refund pending.
- **Booking Window** menunjukkan jarak antara booking dibuat dan check-in, termasuk rata-rata, median, dan distribusi H-0–1 sampai lebih dari H-30. Booking multi-kamar dihitung satu kali.
- Forecast kamar-malam memakai horizon 6 bulan.
- Forecast memerlukan minimal **12 bulan lengkap**; bulan berjalan tidak dihitung sebagai bulan lengkap.
- Kualitas forecast ditampilkan sebagai Belum tersedia, Cukup, atau Baik sesuai kelengkapan histori.

## 11. Operasional dan Housekeeping

### Operasional

- Pengeluaran dan budget operasional.
- Inventaris amenities, spare part, dan operational supply.
- Stok Extra Bed serta alokasi Extra Bed per kamar/tanggal.
- Maintenance kamar, work order, jadwal preventive maintenance, aset kamar, dan lampiran.
- Shift operasional.

### Housekeeping

- Status kebersihan kamar dan cleaning task.
- Setelah Check Out, status kamar menjadi **Dirty** hingga ditangani Housekeeping.
- Check In mengonsumsi set amenity standar sekali untuk setiap kamar dalam booking.

## 12. Hak akses role

| Role | Akses utama |
|---|---|
| Atmin | Superuser internal/legacy: dashboard, finance, analytics, operasional, front desk, housekeeping, settings, dan support. |
| Admin | Dashboard (nilai finansial disensor), analytics, operasional, front desk, housekeeping, settings, dan support. Tidak membuka Finance perusahaan. |
| Manager | Semua menu: dashboard, finance, analytics, operasional, front desk, housekeeping, settings (kelola akun & role user), dan support. |
| Finance | Dashboard, finance, analytics, operasional, front desk, housekeeping, dan support. |
| Marketing | Dashboard, analytics, front desk, housekeeping, dan support. Tidak membuka finance maupun operasional. |
| Operational | Dashboard, finance, analytics, operasional, front desk, housekeeping, dan support. |
| Housekeeping | Housekeeping saja; setelah login langsung menuju halaman Housekeeping. |
| Staff | Dashboard (nilai finansial disensor), analytics, front desk, housekeeping, dan support. |
| Owner GH 1–5 | Keuangan Owner/Investor untuk GH (HomiQ) miliknya saja. |
| Owner HS Mitra | Keuangan Owner/Investor untuk Homestay Mitra miliknya saja. Tidak dapat membuka menu operasional umum. |
| Owner Kos Mitra | Keuangan Owner/Investor untuk Kos Mitra miliknya saja. Tidak dapat membuka menu operasional umum. |

Ketentuan tambahan role:

- Role **Manager**, **Admin**, dan **Atmin** dapat mengelola user/role pada Settings.
- Owner GH tidak dapat memilih GH lain melalui URL ataupun ekspor laporan; GH dikunci sesuai role.
- Owner HS Mitra dan Owner Kos Mitra terkunci pada properti mitra masing-masing; tidak dapat mengakses menu operasional umum atau properti lain.
- Bukti pembayaran dapat dibuka oleh role operasional yang memiliki akses Front Desk dan Owner terkait.
- Bukti pengeluaran dapat dibuka oleh Finance/Operasional/Manager/Admin/Atmin serta Owner terkait.

## 13. Keamanan dan batas penggunaan

- Gunakan akun pribadi; jangan berbagi username/password.
- Password user minimal 8 karakter.
- Simpan bukti pembayaran, KTP, dan lampiran hanya untuk kebutuhan kerja.
- Jangan menghapus file di folder upload aplikasi karena bukti dan dokumen dapat hilang dari detail transaksi.
- Aplikasi dirancang untuk hosting kelas Medium dan penggunaan internal. Hindari upload file besar, ekspor berulang tanpa kebutuhan, atau membuka banyak tab laporan berat secara bersamaan.

## 14. Batasan yang perlu diketahui

- Refund/hangus belum memiliki tombol atau jurnal otomatis; status `Kredit/refund pending` hanya penanda bahwa keputusan finance masih diperlukan.
- Penyesuaian layanan setelah pembatalan sebagian booking belum otomatis.
- Cancellation Report hanya mencakup pembatalan sejak kolom `cancelled_at` diterapkan; data pembatalan lama tanpa timestamp tidak dapat ditempatkan pada periode pembatalan yang akurat.
- Forecast membutuhkan histori bulanan yang cukup; tanpa 12 bulan lengkap forecast belum tersedia.
- Upload KTP dan bukti pembayaran bergantung pada penyimpanan server; pastikan folder upload ikut dalam backup.

## 15. Checklist sebelum tutup operasional harian

- [ ] Check-in dan check-out hari ini sudah diperbarui.
- [ ] Kamar Dirty sudah diteruskan ke Housekeeping.
- [ ] Pembayaran dan bukti transfer sudah dicatat.
- [ ] Booking yang dibatalkan sudah diperiksa status kredit/refund pending-nya.
- [ ] Notif pesanan sarapan untuk hari-hari mendatang sudah diteruskan ke tim terkait.
- [ ] Stok rendah, Extra Bed, dan maintenance terbuka sudah ditindaklanjuti.
- [ ] Rekonsiliasi finance dilakukan untuk booking yang selesai diperiksa.
