# Implementation Plan — Keuangan Gabungan Homestay Mitra & Kos Mitra

Tanggal: 12 Oktober 2026 (Asia/Makassar)
Status: **Selesai 100% (Tahap 1–7 Lengkap).** Seluruh implementasi lokal dan validasi otomatis telah tuntas. Sistem siap ditinjau secara lokal oleh klien sebelum keputusan bisnis dan rilis produksi. Rincian: [handover tahap 6](MITRA_FINANCE_STAGE6_HANDOVER.md) dan [handover tahap 7](MITRA_FINANCE_STAGE7_HANDOVER.md).

## 1. Tujuan dan pemahaman permintaan

Homestay Mitra dan Kos Mitra menjadi **satu kelompok laporan keuangan Mitra**, dengan satu konfigurasi owner, satu persentase bagi hasil, dan satu jenis role **Owner Mitra**.

Penggabungan mencakup tiga submenu Finance yang ditunjukkan pengguna:

1. **Keuangan Properti**: satu laporan pemasukan, pengeluaran, laba/rugi, outstanding, transaksi, grafik, dan ekspor untuk kedua properti.
2. **Keuangan Owner/Investor**: satu laporan owner dan satu hasil pembagian untuk kedua properti.
3. **Setup Owner**: satu nama owner dan satu persentase owner yang berlaku pada kelompok Mitra.

Adiputra Guesthouse by HomiQ tetap memakai laporan dan setup GH 1–5 seperti saat ini. Homestay dan Kos tetap merupakan dua properti operasional dengan identitas dan data sumber masing-masing.

## 2. Batas perubahan

| Area | Homestay Mitra & Kos Mitra setelah perubahan | Adiputra/HomiQ |
| --- | --- | --- |
| Dropdown properti | Tetap dua pilihan berbeda | Tetap seperti saat ini |
| Booking, kamar, tamu, harga, invoice booking | Tetap terpisah per properti | Tetap seperti saat ini |
| Dashboard dan Analytics | Tetap per properti | Tetap seperti saat ini |
| Expense input, inventory, Extra Bed, maintenance, housekeeping, shift | Tetap dicatat dan dikelola per properti | Tetap seperti saat ini |
| Laporan Keuangan Properti | Gabungan **Mitra — Homestay & Kos** | Tetap per properti HomiQ |
| Laporan Keuangan Owner/Investor | Gabungan untuk owner Mitra | Tetap per GH |
| Setup dan persentase owner | Satu konfigurasi kelompok Mitra | Tetap per GH |
| Role owner | Satu role **Owner Mitra** | Owner GH 1–5 tetap |

Penggabungan adalah perubahan cakupan laporan dan kepemilikan keuangan. `property_id` transaksi, kamar, reservasi, dan biaya tidak diganti. Tidak membuat properti operasional keempat untuk mewakili gabungan.

## 3. Kondisi implementasi yang ditemukan

- Properti 1: Adiputra Guesthouse by HomiQ; 29 kamar dalam GH 1–5; sewa kamar per malam.
- Properti 2: Homestay Mitra; tiga kamar dibooking sebagai satu rumah; sewa rumah per malam.
- Properti 3: Kos Mitra; tujuh kamar; sewa kamar per bulan dan perpanjangan sewa.
- `FinanceController` masih mengambil laporan, chart, outstanding, dan setup owner berdasarkan satu `PropertyContext::activeId()`.
- `owner_guesthouse_setups` menyimpan konfigurasi berdasarkan properti dan GH. Field `cooperation_fee_percentage` saat ini digunakan sebagai **persentase owner**, walaupun namanya dapat menimbulkan salah tafsir.
- Owner Mitra masih memiliki dua role: `Owner HS Mitra` dan `Owner Kos Mitra`. Pilihan tersebut muncul pada Settings dan divalidasi berdasarkan ID properti.
- `CompanyEntitlementService` juga membaca setup owner tiap properti. Pembacaan ini harus diperiksa agar konfigurasi baru tidak menghasilkan persentase berbeda antar laporan.
- Kode terbaru sudah mendukung laporan owner Mitra. Beberapa dokumen lama yang menyebut laporan owner Mitra belum tersedia tidak dipakai sebagai acuan perubahan.
- Pada pemeriksaan database lokal sebelum pembuatan rencana ini, setup owner belum terisi. Kondisi tersebut harus dibaca ulang sebelum migrasi; jangan mengasumsikan database lain juga kosong.

## 4. Perilaku halaman yang dituju

### Keuangan Properti

Saat properti aktif adalah Homestay atau Kos, halaman Finance menampilkan judul **Keuangan Mitra — Homestay & Kos**, label cakupan kedua properti, dan satu periode laporan. Memindahkan dropdown dari Homestay ke Kos menghasilkan angka keuangan gabungan yang sama untuk periode yang sama, bagi pengguna yang berhak.

- Ringkasan dan grafik enam bulan mengambil kedua properti.
- Daftar transaksi memiliki kolom/badge **Asal properti**.
- Detail tetap dapat ditelusuri ke transaksi asli; tidak menyalin transaksi.
- Pagination, jumlah transaksi, dan ekspor memakai kumpulan gabungan yang sama.
- Metode pembayaran menampung metode valid dari kedua properti, termasuk OTA Homestay dan Cash/Transfer Kos.
- Informasi durasi mempertahankan satuan tiap sumber: malam untuk Homestay, bulan untuk Kos.
- Tidak menyediakan mode laporan keuangan terpisah sebagai tampilan utama; identitas sumber tetap terlihat untuk penelusuran.

### Keuangan Owner/Investor

Menampilkan **Owner Mitra — Homestay & Kos** dengan total pendapatan yang menjadi hak owner, pengeluaran, laba/rugi, persentase owner/pengelola, dan nilai bagi hasil gabungan.

Metrik nonkeuangan yang sekarang ada di halaman owner tidak dijumlahkan secara sembarang. Jika tetap ditampilkan, okupansi Homestay dan okupansi Kos diberi dua ringkasan terpisah dengan satuan yang jelas; persentase okupansi tidak dijumlahkan atau dirata-ratakan tanpa bobot. Dashboard dan Analytics utama tetap per properti.

### Setup Owner

Saat salah satu properti Mitra aktif, tampil satu konfigurasi **Homestay Mitra + Kos Mitra**. Konfigurasi berisi nama owner dan persentase owner; persentase pengelola adalah `100% − persentase owner`. Perubahan dari konteks Homestay langsung berlaku pula ketika membuka konteks Kos.

Pemilihan scope, edit, dan hapus diperiksa server. Mengubah ID setup atau parameter properti tidak boleh menyentuh konfigurasi HomiQ.

### Settings dan role

Pilihan role owner Mitra menjadi satu: **Owner Mitra (Homestay + Kos)**. Membuat, mengganti, atau mencabut akses gabungan dilakukan sebagai satu tindakan yang jelas di UI dan satu transaksi database. Owner Mitra diarahkan ke laporan owner gabungan setelah login dan tidak mendapat akses operasional umum atau Setup Owner.

## 5. Aturan angka dan bagi hasil

### Laporan keuangan umum

Untuk satu periode yang sama:

- Pemasukan gabungan = pemasukan Homestay + pemasukan Kos.
- Pengeluaran gabungan = pengeluaran Homestay + pengeluaran Kos.
- Laba/rugi gabungan = pemasukan gabungan − pengeluaran gabungan.
- Outstanding gabungan = outstanding valid dari kedua properti, memakai aturan status dan pembayaran yang sama seperti sekarang.

Pertahankan aturan status pembayaran, refund, pembatalan, kategori biaya, dan tanggal pengakuan masing-masing laporan. Hindari penggandaan baris karena join pembayaran, invoice, reservasi grup, atau alokasi biaya. Homestay tetap satu sewa rumah walaupun memiliki tiga baris kamar. Jumlahkan uang dari record sumber yang tepat, bukan menambahkan total booking dan pembayaran sebagai dua pendapatan.

### Laporan owner

Asumsi berdasarkan permintaan: satu persentase owner digunakan pada **total dasar bagi hasil gabungan**. Dasar Mitra tetap omzet pendapatan yang memenuhi aturan owner saat ini; biaya operasional ditampilkan tetapi tidak menjadi pengurang dasar pembagian Mitra.

```text
Dasar owner gabungan = dasar owner Homestay + dasar owner Kos
Bagian owner = dasar gabungan × persentase owner
Bagian pengelola = dasar gabungan − bagian owner
```

Pemilahan layanan tetap mengikuti sumbernya. **Extra Bed / Biaya Extra Bed Homestay dan Kos sama-sama masuk ke dasar owner gabungan.** Layanan langsung milik pengelola yang saat ini dikecualikan dari dasar owner tetap dikecualikan. **Total pemasukan laporan umum tidak otomatis identik dengan dasar bagi hasil owner.**

Contoh ilustratif, bukan penetapan tarif/persentase klien: dasar Homestay Rp6.000.000 + dasar Kos Rp3.500.000 = Rp9.500.000. Bila persentase owner 70%, bagian owner Rp6.650.000 dan pengelola Rp2.850.000. Pengeluaran operasional tetap dilaporkan tersendiri.

Gunakan nominal dengan ketelitian uang yang konsisten dan pembulatan sekali pada hasil gabungan. Jangan menghitung bagi hasil gabungan lagi di atas nilai bagi hasil tiap properti. Bila laporan holding memerlukan alokasi detail per properti, selisih pembulatan dialokasikan secara deterministik agar detail menjumlah tepat ke total.

Tidak menetapkan persentase baru dari contoh atau mengambil persentase salah satu properti secara diam-diam. Konfigurasi gabungan harus berasal dari data yang disepakati; bila belum tersedia, tampilkan kebutuhan Setup Owner dengan jelas. Perilaku default HomiQ tidak diubah.

## 6. Rancangan teknis yang direkomendasikan

### Cakupan laporan khusus Finance

Tambah helper/service `FinanceReportScope` yang mengembalikan jenis scope, label, properti anggota, dan izin laporan. Scope HomiQ tetap `[1]`; scope Mitra berasal dari kelompok tetap beranggota Homestay dan Kos. Cakupan ini digunakan hanya oleh laporan Finance, setup owner, dan pembaca konfigurasi bagi hasil yang terkait.

**Jangan mengubah `PropertyContext::activeId()` menjadi scope gabungan.** Query booking dan modul operasional tetap memakai satu properti aktif. Jangan menerima daftar ID arbitrer dari query string sebagai izin laporan.

Pusatkan query laporan gabungan dan perhitungan owner dalam service agar halaman, HTMX, grafik, pagination, ekspor, dan pembaca setup tidak memakai rumus/scope yang berbeda.

### Struktur data

Usulan migrasi tambahan:

| Tabel | Isi utama |
| --- | --- |
| `finance_report_groups` | ID, kode unik `MITRA_COMBINED`, nama kelompok, status aktif, timestamps |
| `finance_report_group_properties` | Relasi kelompok ke properti Homestay/Kos; unik per anggota |
| `finance_report_group_owner_setups` | Satu konfigurasi owner per kelompok: nama owner, persentase owner 0–100, timestamps |

Nama kolom persentase pada tabel baru dibuat jelas, misalnya `owner_share_percentage`. Setup GH HomiQ tetap memakai tabel yang ada. Data anggota kelompok tidak boleh memasukkan HomiQ ke `MITRA_COMBINED`.

### Otorisasi

Untuk memanfaatkan arsitektur akses per properti yang sudah ada, role `Owner Mitra` direpresentasikan oleh dua assignment pada `user_property_roles`, masing-masing untuk Homestay dan Kos. Keduanya dikelola sebagai satu paket di Settings; akses role tetap hanya laporan owner dan ekspor/bukti yang memang diperbolehkan.

- Role staff tetap mengikuti izin saat ini. Laporan gabungan memerlukan izin Finance pada **kedua anggota**; akses pada Homestay saja tidak otomatis memberikan akses ke data Kos.
- Untuk pengguna yang belum memiliki cakupan lengkap, tampilkan penolakan/permintaan akses yang jelas. Jangan menampilkan angka sebagian dengan label laporan gabungan.
- Role owner gabungan tidak valid pada HomiQ. Owner GH tetap terkunci pada GH dan properti asal.
- UI bukan satu-satunya pembatas: validasi juga mencakup URL langsung, endpoint HTMX, ekspor, serta edit/hapus setup.
- Link bukti pembayaran/receipt dari laporan gabungan memerlukan validasi baca pada scope keuangan yang sah. Jika perlu pengecualian scope di filter, batasi pada endpoint bukti keuangan tersebut; jangan membuka detail booking, foto KTP, atau operasi lintas properti secara umum.
- Cabut/ganti grant gabungan dan sinkronisasi sesi harus mencegah akses lama bertahan setelah izin dicabut.

## 7. Migrasi data dan role lama

1. Inventarisasi ulang dua properti, akun owner lama, assignment pengguna, setup owner, serta referensi konfigurasi dari laporan holding.
2. Tambah tabel kelompok dan anggota tanpa memindahkan record operasional.
3. Rekonsiliasi setup owner lama:
   - Tidak ada setup: konfigurasi gabungan diisi secara eksplisit melalui Setup Owner.
   - Dua setup identik: dapat dipindahkan ke konfigurasi gabungan setelah kesesuaiannya dipastikan.
   - Satu setup, nama owner berbeda, atau persentase berbeda: catat sebagai kasus yang perlu keputusan; jangan memilih salah satu atau menjumlahkan persentase otomatis.
4. Tentukan akun mana yang menjadi owner gabungan. Role lama `Owner HS Mitra`/`Owner Kos Mitra` pada akun yang ditetapkan diubah menjadi `Owner Mitra` dengan dua assignment secara atomik.
5. Jangan menggabungkan dua akun pengguna, mengganti sandi, atau memberi akses ke kedua properti pada semua owner lama secara otomatis. Identitas penerima akses gabungan harus jelas sebelum tahap ini.
6. Perbarui pembaca setup Mitra agar memakai satu sumber kelompok. Setup lama yang sudah dipindahkan ditandai/diarsipkan menurut hasil inventarisasi, agar tidak ikut dihitung lagi; jangan menghapus data konfigurasi lama tanpa pemetaan pemulihan.
7. Selama transisi, penanganan role lama harus eksplisit. Akun yang belum dimigrasikan tidak boleh mendapatkan cakupan gabungan secara implisit. Hapus pilihan role lama dari UI setelah migrasi target selesai.
8. Siapkan backup konfigurasi/grant dan pemetaan sebelum-sesudah. Migrasi balik mengembalikan setup/grant berdasarkan pemetaan tersebut, tanpa membatalkan atau menghapus transaksi operasional baru.

Migrasi diuji pada salinan database terlebih dahulu. Implementasi lokal dan penerapan ke server produksi adalah tahap terpisah.

## 8. Keterkaitan dengan Keuangan Holding

`CompanyEntitlementService` perlu membaca konfigurasi owner gabungan untuk kedua properti Mitra, agar tidak tertinggal membaca dua setup lama. Detail sumber dan `property_id` pada ledger/alokasi tetap dipertahankan.

Basis kas terkonfirmasi pada laporan holding tetap mengikuti aturan holding saat ini; jangan menyamakannya begitu saja dengan laporan owner yang memakai pembayaran Paid. Tidak mengubah company membership, rekening kas, kewajiban, atau aturan alokasi overhead hanya untuk membuat laporan Mitra gabungan.

Snapshot/periode yang sudah ditutup tidak ditulis ulang. Jika ada kebutuhan mengubah hasil periode historis, keputusan dan prosesnya dibahas sebelum eksekusi.

## 9. File/komponen yang diperkirakan terdampak

| Area | File/komponen |
| --- | --- |
| Scope dan query bersama | Service baru `FinanceReportScope` dan service laporan/perhitungan Finance |
| Laporan, setup, dan ekspor | `app/Controllers/FinanceController.php` |
| Grant dan validasi role | `app/Controllers/SettingsController.php`, `app/Libraries/RoleAccess.php` |
| Login/landing dan pilihan properti | `app/Libraries/PropertyContext.php`, alur Settings yang memberi dua assignment |
| Filter dan bukti laporan | `app/Filters/AccessFilter.php`, `app/Filters/PropertyReadyFilter.php`, route terkait |
| UI laporan | `app/Views/pages/finance.php`, `app/Views/components/finance_panel.php`, `app/Views/pages/finance_owner.php` |
| UI setup dan role | `app/Views/pages/owner_setup.php`, `app/Views/components/settings_panel.php`, `app/Views/components/sidebar.php`, JavaScript pilihan role di Settings |
| Pembaca setup pada holding | `app/Libraries/CompanyEntitlementService.php` |
| Data dan dokumentasi | Migrasi tambahan serta pembaruan matriks role/panduan operasional |

## 10. Tahapan eksekusi

- [x] **Tahap 1 — Finalisasi bisnis:** pastikan cakupan tiga submenu, satu owner/persentase, dan akun penerima akses gabungan. Selesaikan konflik setup lama jika ada. **Pelaksana: Gemini, menurut informasi pengguna. Hasil nilai owner/persentase dan akun penerima belum tercatat dalam dokumen ini.**
- [x] **Tahap 2 — Scope dan skema:** tambah kelompok Finance dan konfigurasi owner gabungan; implementasikan resolver scope dengan izin yang lengkap. **Model: GPT-6.1 Sol, high.**
- [x] **Tahap 3 — Laporan umum:** gabungkan ringkasan, transaksi, grafik, outstanding, pagination, dan ekspor; tambahkan asal properti. **Rencana model: GPT-5.6 Terra, high. Pelaksana aktual: Gemini Flash 3.8 menurut pengguna; lihat `MITRA_FINANCE_STAGE3_HANDOVER.md`.**
- [x] **Tahap 4 — Owner dan setup:** implementasikan satu setup, satu dasar owner gabungan, serta konsistensi pembaca konfigurasi pada holding. **Selesai lokal: satu CRUD kelompok, laporan/ekspor owner, basis kas terkonfirmasi pada holding, alokasi uang presisi, dan pengujian; lihat `MITRA_FINANCE_STAGE4_HANDOVER.md`. Model: GPT-6.1 Sol, high.**
- [x] **Tahap 5 — Role dan UI:** tambah `Owner Mitra`, pemberian/pencabutan assignment berpasangan, landing owner, label cakupan, dan otorisasi bukti terpadu. **Selesai lokal: katalog role, paired grant atomik, UI non-AI-slopp, pembukaan bukti terotorisasi, dan pengujian 49 checks; lihat `MITRA_FINANCE_STAGE5_HANDOVER.md`.**
- [x] **Tahap 6 — Validasi:** uji angka, otorisasi, migrasi, tampilan desktop/ponsel/tablet, dan regresi pemisahan operasional. **Selesai lokal: 380 pemeriksaan otomatis lulus, 30 kombinasi viewport responsif lulus, integritas 54/55 tabel terjaga; lihat `MITRA_FINANCE_STAGE6_HANDOVER.md`. Model: GPT-6.1 Sol, high.**
- [x] **Tahap 7 — Tinjau hasil lokal:** tampilkan laporan dan perbandingan angka untuk peninjauan pengguna sebelum penerapan produksi. **Selesai: ringkasan perubahan, panduan hands-on review lokal, rekonsiliasi angka, smoke-test operasional independen, dan checklist keputusan bisnis; lihat `MITRA_FINANCE_STAGE7_HANDOVER.md`. Model: GPT-6 Luna, medium.**

### Pembagian model tahap 1–7

Pembagian berikut adalah rekomendasi untuk tingkat kerumitan dan risiko proyek ini. **Astra tidak digunakan pada tahap 1–7 dalam rencana ini**, sesuai arahan pengguna. Nama lengkap model: Sol = `gpt-6.1-sol`, Terra = `gpt-5.6-terra`, Luna = `gpt-6-luna`.

| Tahap | Pekerjaan | Model utama | Reasoning | Alasan dan batas tugas |
| --- | --- | --- | --- | --- |
| 1 | Finalisasi bisnis | **GPT-5.6 Terra** | **medium** | Merapikan kebutuhan, daftar akun, nama owner, persentase, dan konflik setup. Keputusan bisnis tetap berasal dari pengguna/klien. |
| 2 | Scope dan skema | **GPT-6.1 Sol** | **high** | Mendesain kelompok Finance, migrasi data, resolver scope, serta syarat akses kedua properti. Kesalahan di fondasi ini memengaruhi seluruh laporan dan izin. |
| 3 | Laporan umum | **GPT-5.6 Terra** | **high** | Mengimplementasikan query agregasi dan tampilan sesuai scope yang sudah ditetapkan tahap 2: transaksi, grafik, outstanding, pagination, dan ekspor. Konsistensi angka diperiksa lagi oleh Sol pada tahap 6. |
| 4 | Owner dan setup | **GPT-6.1 Sol** | **high** | Mengubah dasar bagi hasil, pemilahan layanan, pembulatan, setup gabungan, serta pembaca konfigurasi pada holding. Perubahan langsung memengaruhi nilai hak owner/pengelola. |
| 5 | Role dan UI | **GPT-6.1 Sol** | **high** | Mengimplementasikan role Owner Mitra, grant berpasangan, migrasi akun, pencabutan akses, sinkronisasi sesi, dan proteksi endpoint. UI role harus mengikuti izin server yang sama. |
| 6 | Validasi | **GPT-6.1 Sol** | **high** | Memeriksa angka terhadap sumber, kebocoran akses lintas properti, migrasi balik, serta regresi HomiQ dan operasional. Menangani temuan yang memerlukan koreksi logika. |
| 7 | Tinjau hasil lokal | **GPT-6 Luna** | **medium** | Menyusun ringkasan perubahan, panduan mencoba, bukti hasil pemeriksaan, dan daftar keputusan pengguna berdasarkan hasil tahap 6. |

Tahap **2, 4, 5, dan 6** ditetapkan memakai **6.1 Sol** karena menyangkut fondasi data, pembagian uang, migrasi akses, dan pemeriksaan kebenaran. Tahap **1 dan 3** memakai **Terra** dengan ruang kerja yang sudah jelas. Tahap **7** memakai **Luna** untuk penyajian hasil dan dokumentasi.

Ketentuan pelaksanaan:

- Tahap 3 dimulai setelah desain scope dan izin dari tahap 2 selesai. Jika pekerjaan ternyata membutuhkan perubahan desain akses, rumus owner, atau skema di luar rancangan, bagian tersebut ditangani kembali dengan **6.1 Sol**.
- Tahap 7 dimulai setelah pemeriksaan tahap 6 lulus. Jika muncul ketidaksesuaian angka atau izin saat peninjauan, kembalikan ke **6.1 Sol** untuk memperbaikinya sebelum ringkasan diperbarui.
- Bila ada konflik data/grant yang sulit, tingkatkan reasoning Sol ke **xhigh** pada bagian tersebut. Tetap mengikuti batas pilihan model pengguna; perubahan pilihan di luar Sol/Terra/Luna memerlukan arahan baru.
- Saat berganti model, sertakan dokumen ini, tahap yang sedang dikerjakan, keputusan bisnis yang sudah disepakati, file yang berubah, serta bukti pemeriksaan dan temuan yang belum selesai.
- Penentuan model dalam dokumen ini belum menjalankan implementasi atau mengubah model aktif pada chat secara otomatis.

Acuan penamaan dan karakter umum model diperiksa dari official OpenAI documentation pada 12 Oktober 2026: [GPT-6.1 Sol](https://developers.openai.com/api/docs/models/gpt-6.1-sol), [GPT-5.6 Terra](https://developers.openai.com/api/docs/models/gpt-5.6-terra), dan [GPT-6 Luna](https://developers.openai.com/api/docs/models/gpt-6-luna). Pembagian tahap dan tingkat reasoning di atas merupakan penilaian proyek ini, bukan aturan wajib dari dokumentasi OpenAI.

## 11. Kriteria penerimaan dan pengujian

- [x] Membuka Finance dari Homestay maupun Kos memberi total gabungan yang identik untuk periode yang sama.
- [x] Total laporan cocok dengan penjumlahan sumber masing-masing properti sebelum penggabungan; data HomiQ tidak masuk.
- [x] Transaksi memiliki asal properti yang benar, tanpa duplikasi pembayaran/booking grup atau biaya.
- [x] Pengurutan tanggal, pagination, jumlah baris, chart enam bulan, outstanding, dan ekspor memakai scope yang sama.
- [x] Pembayaran OTA Homestay tetap muncul, sementara validasi input pembayaran Kos tetap mengikuti alur Kos.
- [x] Kasus DP, pelunasan, refund, batal, periode kosong, satu properti tanpa transaksi, serta batas bulan diuji menggunakan aturan yang sudah berlaku.
- [x] Satu setup owner terlihat dari kedua konteks Mitra; perubahan berlaku pada kedua laporan dan tidak mengubah setup HomiQ.
- [x] Bagi hasil dihitung sekali dari dasar gabungan; uji persentase 0%, 100%, dan pecahan, termasuk pembulatan.
- [x] Extra Bed / Biaya Extra Bed Homestay dan Kos tetap termasuk dasar owner; layanan langsung pengelola tidak dihitung dua kali.
- [x] Role `Owner Mitra` dapat membaca laporan owner gabungan dan ekspor/bukti yang sah, tetapi tidak dapat mengedit setup, membuka operasional umum, atau mengakses HomiQ.
- [x] Staff yang hanya mempunyai satu assignment Mitra tidak dapat membaca data properti pasangan lewat laporan, HTMX, ekspor, atau URL bukti.
- [x] Penggantian/pencabutan role gabungan memperbarui kedua grant secara atomik; sesi lama tidak tetap berhak.
- [x] Booking Homestay masih memblokir satu rumah; Kos tetap menyewa/perpanjang per kamar per bulan. Ketersediaan kamar, Dashboard, Analytics, inventory, maintenance, dan biaya input tetap terpisah (terverifikasi lewat pengujian ketersediaan unit dan panduan smoke check tahap 7).
- [x] Owner GH 1–5, laporan HomiQ, dan dasar bagi hasil HomiQ tetap menghasilkan perilaku semula.
- [x] Migrasi tidak memilih otomatis saat owner/persentase lama konflik dan tidak mengubah password pengguna.
- [x] Label gabungan, asal properti, tabel geser, form Setup Owner, dan pilihan role diperiksa pada ponsel/tablet/desktop.

Catatan validasi: Seluruh checklist pengujian otomatis dan manual telah terpenuhi. Panduan hands-on review mandiri serta formulir keputusan bisnis klien (nama owner, persentase bagi hasil, dan akun penerima) telah disusun lengkap di `MITRA_FINANCE_STAGE7_HANDOVER.md`.

## 12. Keputusan yang dicatat sebelum implementasi

Draf ini memakai pemahaman berikut: **ketiga submenu Finance digabung, satu nama owner dan satu persentase untuk Homestay + Kos, dasar pembagian tetap omzet yang memenuhi aturan owner Mitra saat ini, dan seluruh operasional tetap terpisah.**

Nama owner, persentase yang sebenarnya, akun penerima role gabungan, serta penyelesaian setup lama yang berbeda ditetapkan saat persiapan eksekusi. Angka 70% pada contoh bukan keputusan klien.

Tahap 2 telah menambahkan migrasi skema dan `FinanceReportScope` pada aplikasi/database lokal. Konfigurasi owner, migrasi grant, dan integrasi laporan mengikuti tahap 3–5. Rincian API dan verifikasi tersedia di [handover tahap 2](MITRA_FINANCE_STAGE2_HANDOVER.md).
