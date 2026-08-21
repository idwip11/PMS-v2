# Brief Teknis Hosting — HomiQ PMS

Dokumen ini dapat dikirimkan ke tim Customer Service (CS) hosting untuk mengecek kecocokan paket sebelum migrasi aplikasi HomiQ PMS dari Railway ke shared hosting/cPanel.

## Ringkasan aplikasi

HomiQ PMS adalah aplikasi web internal untuk operasional guesthouse: reservasi dan pembayaran, manajemen tamu/kamar, laporan keuangan owner, maintenance, stok amenities/spare part, jadwal shift, serta upload bukti pembayaran dan invoice.

Aplikasi ditujukan untuk penggunaan operasional harian oleh tim internal, bukan website publik dengan trafik pengunjung sangat besar.

## Tech stack yang digunakan

| Komponen | Teknologi | Keterangan |
|---|---|---|
| Bahasa backend | PHP 8.2 atau lebih baru | Proyek saat ini berjalan dengan PHP 8.3; PHP 8.2 adalah batas minimum dari dependensi aplikasi. |
| Framework | CodeIgniter 4.7 | Framework PHP MVC untuk routing, autentikasi, validasi form, session, dan migrasi database. |
| Database | MySQL / MariaDB | Koneksi memakai driver `MySQLi`, charset `utf8mb4`, port standar 3306. |
| Tampilan | HTML, CSS, JavaScript, Bootstrap, HTMX | Tidak memerlukan proses Node.js saat aplikasi sudah diproduksikan. |
| Dependency manager | Composer 2 | Dibutuhkan saat instalasi atau update dependency; idealnya tersedia melalui SSH/cPanel Terminal. |
| File upload | Filesystem hosting | Bukti pembayaran, invoice maintenance, dan lampiran disimpan di `writable/uploads`. |
| Session | File session CodeIgniter | Disimpan pada `writable/session`; Redis tidak digunakan dan tidak wajib. |
| Scheduled task | cPanel Cron Job | Menjalankan pembuatan work order maintenance rutin satu kali per hari. |

## Apakah paket Medium cukup?

Secara umum, **paket Medium cocok sebagai titik awal** untuk HomiQ PMS apabila dipakai oleh satu bisnis guesthouse kecil–menengah dengan jumlah staf internal dan akses bersamaan yang wajar.

Alasannya:

- Aplikasi adalah PHP + database MySQL/MariaDB standar, bukan aplikasi Node.js, Java, atau container yang membutuhkan VPS.
- Tidak memakai Redis, message queue, worker permanen, atau layanan realtime yang berat.
- File gambar dikompresi oleh aplikasi; lampiran PDF dibatasi maksimal 5 MB per file.
- Fitur cron hanya berupa pekerjaan harian, bukan proses yang berjalan terus-menerus.
- Paket Medium pada screenshot telah mencantumkan MariaDB, SSH, backup mingguan, SSL, dan penyimpanan, yang relevan untuk kebutuhan aplikasi ini.

Namun, istilah “unlimited storage/database” tidak otomatis berarti kapasitas CPU, RAM, inode, atau proses PHP tidak terbatas. Paket Medium sebaiknya dipilih **setelah CS mengonfirmasi poin pada bagian berikut**.

## Kebutuhan minimum yang perlu dikonfirmasi ke CS

Mohon konfirmasi bahwa paket Medium mendukung:

1. PHP **8.2 atau 8.3** (bukan PHP 8.1 atau lebih lama).
2. Ekstensi PHP aktif: `intl`, `mbstring`, `mysqli`/`mysqlnd`, `gd`, `fileinfo`, `json`, dan `zip`.
3. Database MySQL atau MariaDB dengan akses membuat database dan user database.
4. Pengaturan document root/subdomain ke folder `public` milik aplikasi, bukan ke folder root proyek.
5. `mod_rewrite`/aturan `.htaccess` aktif untuk routing CodeIgniter.
6. Akses tulis PHP pada folder berikut:
   - `writable/cache`
   - `writable/logs`
   - `writable/session`
   - `writable/uploads`
7. Batas upload minimal:
   - `upload_max_filesize = 5M`
   - `post_max_size = 6M` atau lebih
8. cPanel Cron Job dan PHP CLI untuk menjalankan perintah `php spark`.
9. SSH atau cPanel Terminal untuk menjalankan Composer dan migration. Bila tidak tersedia, mohon CS membantu menjalankan perintah deployment.
10. Batas proses PHP/CPU/memori yang cukup untuk aplikasi internal berbasis database. Rekomendasi awal: `memory_limit` minimal **256 MB**.
11. Backup database dan backup file akun hosting yang dapat dipulihkan.

## Konfigurasi yang akan dilakukan saat migrasi

- Source code disimpan di luar folder publik bila struktur hosting memungkinkan.
- Domain atau subdomain diarahkan ke folder `public`.
- File `.env` produksi diisi dengan URL domain, `CI_ENVIRONMENT=production`, serta kredensial database.
- Dependency dipasang menggunakan `composer install --no-dev --optimize-autoloader`.
- Struktur database dibuat/diperbarui dengan `php spark migrate --all`.
- Cron harian dipasang untuk maintenance, dengan pola perintah berikut (path disesuaikan akun hosting):

```bash
/usr/local/bin/php /home/CPANEL_USER/homiq/spark maintenance:generate-due >> /home/CPANEL_USER/logs/homiq-maintenance.log 2>&1
```

- Bukti/invoice disimpan di `writable/uploads` dan wajib ikut dalam backup. Folder ini tidak boleh dibersihkan otomatis oleh hosting.

## Kapan perlu upgrade ke paket Large atau VPS?

Paket Medium perlu dievaluasi ulang bila mulai terjadi salah satu kondisi berikut:

- Banyak staf mengakses aplikasi bersamaan dan halaman sering lambat.
- Jumlah upload bukti/invoice meningkat besar sehingga mendekati batas inode atau storage wajar hosting.
- Proses ekspor laporan atau backup database mulai sering timeout.
- Ada banyak guesthouse/cabang, atau kebutuhan integrasi API dan trafik publik meningkat tajam.
- CS menyatakan batas CPU, entry process, atau I/O pada paket Medium terlalu kecil untuk aplikasi PHP database yang aktif digunakan sepanjang hari.

Untuk kondisi tersebut, paket Large atau VPS/managed cloud lebih aman karena memberi resource yang lebih terukur dan isolasi performa yang lebih baik.

## Pesan siap kirim ke CS hosting

> Halo tim CS, saya ingin menempatkan aplikasi internal Property Management System berbasis PHP pada paket Medium. Aplikasi menggunakan PHP 8.2/8.3, CodeIgniter 4.7, dan database MySQL/MariaDB. Tidak membutuhkan Node.js, Redis, container, atau worker permanen.
>
> Mohon konfirmasi apakah paket Medium mendukung PHP 8.2/8.3 dengan ekstensi `intl`, `mbstring`, `mysqli/mysqlnd`, `gd`, `fileinfo`, `json`, dan `zip`; MySQL/MariaDB; SSH/cPanel Terminal; Composer; serta Cron Job PHP CLI.
>
> Kami juga perlu mengarahkan document root domain ke folder `public`, mengaktifkan rewrite `.htaccess`, dan memastikan folder `writable` dapat ditulis PHP untuk cache, session, log, serta upload invoice/bukti. Batas upload minimal yang diperlukan adalah 5 MB per file dan `post_max_size` minimal 6 MB. Mohon informasikan juga batas CPU, RAM, entry process, inode, dan kebijakan backup pada paket Medium.

## Catatan keputusan

Rekomendasi saat ini: mulai dari **Medium**, selama seluruh poin teknis di atas dikonfirmasi oleh CS. Monitoring kecepatan aplikasi, pemakaian inode/storage, dan error log tetap perlu dilakukan setelah aplikasi aktif.
