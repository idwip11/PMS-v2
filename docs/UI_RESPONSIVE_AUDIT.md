# Audit UI responsif — 3 Oktober 2026

Perbaikan dilakukan pada layout bersama dan komponen yang digunakan lintas halaman. Desain, hak akses, dan logika transaksi tetap mengikuti aplikasi yang ada.

## Temuan dan perbaikan

- Header melampaui layar 320 px sampai 333 px karena ukuran minimum pemilih properti. Header ponsel kini menempatkan judul, pemilih properti, dan tombol booking pada baris yang sesuai; kontrol dapat menyusut mengikuti layar.
- Baris akses properti pada Settings melampaui layar 320 px sampai 390 px. Dropdown role dan tombol kini dapat membungkus, dengan nama properti dan keterangan yang ikut menyesuaikan.
- Filter riwayat Expense melampaui viewport tablet 769 px sampai 793 px. Kelompok tombol mendapat ruang sendiri pada tablet, lalu kembali sejajar pada layar besar.
- Sidebar tetap menghabiskan ruang pada tablet landscape. Drawer kini berlaku di bawah 1200 px; tombol, CSS, dan JavaScript menggunakan breakpoint yang sama. Menu tersembunyi tidak dapat difokuskan; Escape menutup drawer, fokus kembali ke tombol menu, dan navigasi submenu ikut menutup drawer.
- Kelas fractional spacing yang sudah digunakan pada form belum memiliki definisi CSS. Delapan kelas tersebut sekarang memiliki aturan sehingga ikon, label, dan tombol mendapat jarak yang konsisten.
- Header kartu, judul dialog, label akses properti, informasi kontrak, dan summary konfirmasi kas dapat membungkus tanpa memaksa halaman melebar.
- Kontrol form di ponsel dan tablet menggunakan ukuran font 16 px dan target tombol/input minimal 44 px. Dialog dapat menggulir isi, footer membungkus tombol, dan tabel besar menggulir di dalam pembungkusnya.
- Form jadwal shift memakai kolom sesuai ruang yang tersedia; pilihan kamar dan extra bed tetap mengikuti aturan satu kolom pada ponsel.

## Validasi

Pengujian dilakukan di Codex In-app Browser menggunakan snapshot HTML yang dirender oleh controller/view dengan data lokal. Panel yang biasanya dimuat lewat HTMX dimasukkan ke snapshot agar isinya ikut diuji. Snapshot menonaktifkan pemuatan awal panel live; tidak ada form perubahan data atau transaksi yang disubmit.

| Lebar CSS yang terukur | Skenario | Hasil overflow halaman |
| --- | ---: | --- |
| 320 px | 88 | Tidak ditemukan |
| 769 px | 40 | Tidak ditemukan |
| 1024 px | 40 | Tidak ditemukan |
| 1440 px | 40 | Tidak ditemukan |
| **Total** | **208** | **Lolos** |

Skenario mencakup Dashboard, Finance, laporan Owner, setup Owner, Reservations, Guests, Room Map, Room Pricing, Housekeeping, Marketing, Settings, Support, seluruh tab Operasional, enam submenu Maintenance, tab/submenu Keuangan Holding, serta URL tab lama yang masih diterima controller. URL lama merupakan skenario kompatibilitas, bukan halaman baru.

Variasi Guesthouse, Homestay, dan Kos diuji pada ponsel. Tujuh dialog yang dirender mencakup booking baru dan detail booking pada ketiga properti, serta perpanjangan sewa Kos. Ketujuh dialog juga diperiksa pada tablet dan desktop.

Pemeriksaan DOM membandingkan lebar dokumen dengan viewport dan mencari elemen header/konten/dialog yang melewati batas kanan. Isi tabel dan navigasi yang memang memiliki area geser dikecualikan dari pemeriksaan elemen, tetapi pembungkusnya tetap harus berada di dalam viewport. Screenshot representatif diperiksa untuk header, Dashboard, Settings, navigasi keuangan, drawer tablet, dan dialog perpanjangan sewa.

Interaksi drawer pada tablet diuji: membuka menu, status `aria-expanded`, penguncian scroll halaman, penutupan dengan Escape, dan pemulihan scroll. Sintaks enam view PHP yang diubah dan JavaScript aplikasi lolos pemeriksaan. Setelah memperbesar target sentuh tablet, 80 skenario tablet diulang dan tetap lolos. Dashboard live pada ponsel 320 px serta perpindahan tab Operasional ke Expenses melalui HTMX juga diperiksa; konten dan penanda tab aktif berganti dengan benar, tanpa overflow halaman.

## Batas pengujian

Hasil ini memvalidasi layout dengan data dan status yang ada saat audit. Ini bukan jaminan untuk setiap teks, error, modal, atau status data yang mungkin muncul. Belum dilakukan pengujian pada perangkat fisik Android/iOS, seluruh browser, keyboard virtual, serta seluruh alur interaksi HTMX dan form. Penyimpanan transaksi tidak termasuk audit tampilan ini.
