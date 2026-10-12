# Handover Tahap 4 — Owner dan Setup Mitra Gabungan

Tanggal: 12 Oktober 2026 (Asia/Makassar). Status: **implementasi tahap 4 selesai lokal; kebijakan Extra Bed Kos telah diselaraskan dengan arahan tambahan pengguna. Uji regresi untuk penyesuaian terakhir belum dijalankan.** Tahap 5–7 masih perlu dijalankan.

## Hasil implementasi

- **Satu Setup Owner** untuk Homestay Mitra + Kos Mitra, memakai tabel `finance_report_group_owner_setups` dari tahap 2. Membuka, menyimpan, atau menghapus dari konteks Homestay maupun Kos menyentuh konfigurasi kelompok yang sama.
- **Laporan owner gabungan**: sumber Homestay dan Kos dijumlahkan sekali, dengan dasar per properti, asal setiap transaksi, pengurutan global, pagination, kategori biaya, dan ekspor seluruh transaksi. Nama file `owner-mitra-combined-YYYY-MM.xls` dan isi ekspor identik dari kedua dropdown.
- **Holding membaca setup kelompok** dan mempertahankan detail per properti. Dasar owner adalah omzet yang memenuhi hak owner dari penerimaan kas terkonfirmasi, termasuk potongan OTA/withheld fee pada nilai gross, dengan pengecualian transaksi kas yang direversal. Pembayaran berstatus Paid yang belum dikonfirmasi tetap hanya masuk laporan owner.
- **HomiQ tetap per GH**: setup lama, okupansi, format/nama ekspor, default persentase lama, dan dasar laba bersih positif pada laporan owner dipertahankan. Holding HomiQ tetap mengikuti perjanjian/multi-GH sebelumnya.

## Aturan perhitungan

```text
Dasar gabungan = dasar eligible Homestay + dasar eligible Kos
Owner = round(dasar gabungan × persentase owner, 2 desimal rupiah)
Pengelola = dasar gabungan − Owner
```

Nominal Mitra dihitung sebagai integer sen. Persentase maksimal dua angka pecahan; nilai di luar 0–100 dan precision berlebih ditolak, tanpa pembulatan diam-diam oleh database. Perkalian proporsional menggunakan quotient/remainder integer untuk menghindari overflow produk dan kehilangan sen. Alokasi memakai largest remainder dengan urutan kunci stabil; sisa sen pada detail holding dialokasikan deterministik sehingga detail menjumlah tepat ke total kelompok. Filter satu properti pada holding mengambil alokasi yang sama seperti laporan keseluruhan.

Komposisi invoice per reservasi digunakan secara proporsional pada **setiap pembayaran/penerimaan**, sehingga DP dan pelunasan tidak mengakui ulang seluruh nilai layanan invoice:

| Komponen | Homestay Mitra | Kos Mitra |
| --- | --- | --- |
| Sewa dan penyesuaian harga | Dasar owner | Dasar owner |
| Extra Bed / Biaya Extra Bed | Dasar owner | Dasar owner |
| Early Check In / Late Checkout | Dasar owner | Dasar owner |
| Breakfast, Laundry, Massage, Sewa Kendaraan | Layanan pengelola | Layanan pengelola |

Biaya operasional tetap ditampilkan dan membentuk laba/rugi, tetapi tidak mengurangi dasar pembagian Mitra. Fee Kerjasama dan biaya Rejected mengikuti pengecualian laporan owner sebelumnya. Biaya Mitra dihitung penuh per record properti sumber, tanpa pembagian berdasarkan alokasi GH HomiQ.

Status/tanggal pengakuan tetap mengikuti sumber: laporan owner memakai Paid dan tanggal pembayaran; holding memakai tanggal efektif ledger yang valid. Pembayaran negatif tidak menjadi pemasukan owner, mengikuti pembatasan lama; tahap ini tidak mendefinisikan ulang kebijakan refund. Komposisi kosong memakai nilai pembayaran/penerimaan sebagai sewa. Penyesuaian negatif tidak dapat membuat pendapatan teralokasi melebihi uang sumber.

Perbaikan konsistensi: Extra Bed / Biaya Extra Bed Kos mengikuti perlakuan Homestay dan menjadi dasar owner gabungan. Charge Tamu Kos sebelumnya masuk nilai transaksi tetapi hilang dari kategori ringkasan. Kini kategori, transaksi, dan dasar gabungan memakai nilai eligible yang sama. Perhitungan layanan langsung holding Mitra sebelumnya dapat menambahkan seluruh nilai invoice pada setiap DP/pelunasan; kini hanya bagian proporsional penerimaan kas yang diakui.

## Konfigurasi dan data lama

Pembacaan lokal menemukan **0 setup Mitra lama dan 0 setup kelompok**. Tidak ada nama/persentase yang ditebak, dimigrasikan, atau diisikan ke database aplikasi.

Jika belum ada setup bersama:

- Laporan owner tetap menampilkan pendapatan, biaya, dan dasar, tetapi persentase/hak owner dan pengelola bernilai `null`; UI dan ekspor menjelaskan bahwa setup diperlukan.
- Holding memberi peringatan `has_agreement = false`, tidak menghitung hak otomatis, dan tetap mengakui layanan langsung yang valid. Field nominal hak pada kontrak holding lama tetap `0.00` bersama status belum dikonfigurasi.
- Bila deployment lain memiliki konfigurasi lama yang berbeda, halaman setup menampilkan referensi dua properti tanpa memilih salah satunya. Pengguna yang berwenang mengisi keputusan bersama secara eksplisit.

Konfigurasi lama tetap utuh sebagai referensi/pemulihan, dan **tidak dibaca sebagai fallback Mitra**. Perjanjian individual Mitra juga tidak menggantikan setup kelompok atau menambahkan fee/persentase kedua. Data perjanjian tidak dihapus. HomiQ tetap memakai sumber konfigurasi sebelumnya.

Tidak ada perubahan grant, password, company membership, rekening kas, alokasi overhead, booking, kamar, atau data operasional. Tidak ada migrasi skema tambahan dan tidak ada penulisan ulang snapshot/periode tertutup. Perubahan konfigurasi dipakai oleh pembaca laporan dinamis; penerapan ulang ke histori/snapshot tetap merupakan keputusan terpisah.

## Otorisasi

Semua endpoint owner/setup, termasuk ekspor dan POST edit/hapus, memanggil `FinanceReportScope::current()` untuk izin terkait. Izin kedua properti diperiksa ulang dari database. Sesi role lama tidak cukup setelah grant dicabut. Parameter `property_id`, `group_id`, dan `guesthouse` dari POST tidak menentukan kelompok; ID edit/hapus hanya dicari di kelompok yang sah.

Service holding memeriksa izin company `view` secara independen dan mensyaratkan kedua properti merupakan anggota company tersebut. Keanggotaan tidak dilengkapi otomatis. Owner GH dikunci berdasarkan assignment yang dibaca resolver, bukan role sesi atau parameter GH.

**Batas tahap 4:** resolver sudah mengenali pasangan assignment `Owner Mitra`, dan controller/service telah diuji dengannya. Pembuatan/pencabutan role berpasangan, landing/login, migrasi akun target, Settings/sidebar, serta akses endpoint bukti dari properti pasangan tetap pekerjaan **tahap 5**. Filter route sekarang masih mengikuti katalog role lama; jangan menganggap akun owner gabungan sudah dapat digunakan end-to-end sebelum tahap 5 selesai. Tidak memberikan grant otomatis kepada akun owner lama.

## File yang berubah

| File | Fungsi |
| --- | --- |
| `app/Libraries/FinanceOwnerSetupService.php` | Reader metadata/setup, validasi, CRUD kelompok, referensi legacy |
| `app/Libraries/MitraOwnerFinanceService.php` | Komposisi eligible, uang presisi, laporan owner, alokasi holding terkonfirmasi |
| `app/Controllers/FinanceController.php` | Resolusi scope owner/setup, CRUD gabungan, ekspor bertanda sumber, jalur HomiQ tersendiri |
| `app/Libraries/CompanyEntitlementService.php` | Pembaca kelompok dan alokasi detail Mitra; HomiQ memakai jalur lama |
| `app/Views/pages/finance_owner.php` | Cakupan, dasar per properti, status setup, pecahan uang, asal transaksi |
| `app/Views/pages/owner_setup.php` | Cabang setup kelompok; menghindari notifikasi ganda yang sudah dirender layout |
| `app/Views/components/mitra_owner_setup.php` | Form responsif satu konfigurasi dan referensi lama |
| `tests/finance/verify_stage4_owner_setup.php` | Runner terisolasi untuk angka, CRUD, kontroler, XML dan holding aktual |

`FinanceReportService`, tampilan laporan umum, dan runner tahap 3 tidak diubah. `FinanceReportScope` dan migrasi tahap 2 tidak diubah.

## Bukti pengujian

```bash
php tests/finance/verify_stage4_owner_setup.php
php tests/finance/verify_scope_stage2.php
php tests/finance/verify_stage3_combined_report.php
```

Runner tahap 4 memakai SQLite in-memory secara default. Untuk MariaDB, argumen opsional hanya menerima database lokal **kosong** dengan pola `homiq_pms_mitra_stage4_test_YYYYMMDD_suffix`; runner menolak database berisi tabel dan membuat schema/fixture sintetis, bukan menyalin pengguna atau transaksi aplikasi.

Hasil:

- Sebelum arahan tambahan Extra Bed Kos, tahap 4 lulus **84/84** pemeriksaan SQLite dan **84/84** pada database MariaDB terisolasi. Runner dan nilai harapan diperbarui untuk aturan baru ini; pemeriksaan tersebut belum dijalankan ulang.
- Tahap 2: **47/47** tetap lolos.
- Tahap 3: **50/50** tetap lolos.
- Sintaks PHP file baru/berubah dan runner lolos.
- CRUD nama/persentase, batas 0/100, precision berlebih, owner tanpa izin setup, forged ID/input, revocation dengan sesi lama, periode kosong/batas bulan, DP/pelunasan, extra bed kedua sumber, loss, pagination dan XML ekspor diperiksa.
- `CompanyEntitlementService::monthlyReport()` diuji langsung: data terkonfirmasi vs Paid, withheld fee, reversal, company lain, setup hilang, konflik legacy/perjanjian individual, alokasi setelah filter sumber, dan regresi angka HomiQ.
- Hash seluruh **55 tabel database aplikasi** sebelum/sesudah pengujian cocok. Database pengujian MariaDB dibuang setelah selesai.

### Pemeriksaan tampilan

HTML dari controller aktual dengan session/database sintetis dirender di Chrome headless. Pemeriksaan ini mencakup form/laporan tahap 4; audit lintas modul dan akun nyata tetap tahap 6.

| Ukuran | Halaman | Lebar dokumen | Hasil |
| --- | --- | --- | --- |
| 390 × 844 | Owner & Setup | 390 px | Form bertumpuk; teks cakupan membungkus; tabel bergeser dalam container |
| 768 × 1024 | Owner & Setup | 768 px | Form/tablet tidak keluar layar; cakupan kepemilikan membungkus |
| 1440 × 1050 | Owner & Setup | 1440 px | Tata letak desktop/sidebar sesuai layout aplikasi |

Tidak ada scroll horizontal pada keseluruhan halaman di enam kombinasi tersebut; tabel transaksi/setup ponsel memakai `.table-responsive`. Label kepemilikan dibuat sebagai teks yang membungkus agar tidak terpotong dalam input readonly yang sempit.

Bukti: [audit ukuran](qa/mitra-stage4-layout-audits.json), [setup tablet](qa/mitra-stage4-setup-tablet.png). Seluruh screenshot memakai data sintetis.

## Melanjutkan tahap 5

1. Tambahkan katalog/validasi `Owner Mitra`, pasangan grant atomik, pencabutan/sinkronisasi sesi, landing dan Settings/sidebar.
2. Pilih akun penerima akses berdasarkan keputusan pengguna; jangan otomatis memperluas semua akun lama.
3. Lengkapi akses bukti yang terotorisasi pada kedua sumber tanpa membuka booking atau operasi umum lintas properti.
4. Isi nama/persentase bersama yang disepakati lewat Setup Owner menggunakan staff dengan izin Finance kedua properti.
5. Tahap 6 memeriksa seluruh matriks izin, alur akun nyata, sumber produksi yang relevan, dan responsivitas lintas modul sebelum peninjauan/penerapan produksi.
