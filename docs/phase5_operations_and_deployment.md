# Phase 5 — Operasional, Cron, Backup, dan Deployment

Dokumen ini menjadi checklist penggunaan modul maintenance di localhost dan saat dipindahkan ke shared hosting Rumahweb/cPanel.

## Fitur final

- Ringkasan maintenance menampilkan Work Order aktif, jadwal terlambat, kamar diblokir, biaya bulan berjalan, MTTR, aset berulang, dan spare part minimum.
- Lampiran Work Order mendukung JPG, PNG, dan PDF maksimal 5 MB. File disimpan di `writable/uploads/maintenance`, tidak dapat diakses tanpa login, dan pada Docker dipertahankan dalam volume `homiq_uploads`.
- Export CSV tersedia di sub-menu Riwayat untuk Admin, Manager, dan Finance. Export mengikuti filter kamar, kategori, status, dan rentang tanggal.
- Semua waktu menggunakan zona `Asia/Makassar`.

## Menjalankan cron di localhost

Jalankan manual ketika ingin membuat Work Order dari jadwal yang masuk masa pengingat:

```bash
docker compose exec -T web php spark maintenance:generate-due
```

Untuk simulasi cron macOS/Linux setiap hari pukul 06:00, gunakan path absolut proyek:

```cron
0 6 * * * cd /path/ke/homiq-pms-php && docker compose exec -T web php spark maintenance:generate-due >> /tmp/homiq-maintenance-cron.log 2>&1
```

## Cron di Rumahweb/cPanel

1. Buka cPanel → Cron Jobs.
2. Pilih satu kali setiap hari, disarankan pukul 06:00 waktu server.
3. Pastikan path PHP dan project disesuaikan dengan akun hosting.
4. Gunakan command berikut sebagai pola:

```bash
/usr/local/bin/php /home/CPANEL_USER/homiq/spark maintenance:generate-due >> /home/CPANEL_USER/logs/homiq-maintenance.log 2>&1
```

Jika cron belum tersedia, Admin/Manager dapat membuka Maintenance → Jadwal Rutin lalu menekan **Generate pekerjaan jatuh tempo**.

## Backup database localhost

Buat folder backup di luar folder publik, lalu jalankan:

```bash
mkdir -p backups
docker compose exec -T mysql mysqldump -uroot -proot_local_password --single-transaction --routines --triggers homiq_pms > backups/homiq_pms-$(date +%Y%m%d-%H%M%S).sql
```

Backup lampiran:

```bash
docker run --rm -v homiq-pms-php_homiq_uploads:/source -v "$PWD/backups:/backup" alpine tar -czf /backup/homiq_uploads-$(date +%Y%m%d-%H%M%S).tar.gz -C /source .
```

Nama volume dapat diperiksa dengan `docker volume ls` bila nama project Compose berbeda.

## Verifikasi restore yang aman

Jangan langsung restore ke `homiq_pms`. Buat database uji terpisah:

```bash
docker compose exec -T mysql mysql -uroot -proot_local_password -e "CREATE DATABASE homiq_pms_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
docker compose exec -T mysql mysql -uroot -proot_local_password homiq_pms_restore_test < backups/NAMA_BACKUP.sql
docker compose exec -T mysql mysql -uroot -proot_local_password -e "SELECT COUNT(*) AS jumlah_tabel FROM information_schema.tables WHERE table_schema='homiq_pms_restore_test'"
```

Setelah jumlah tabel dan data penting diperiksa, database uji dapat dihapus secara manual melalui phpMyAdmin. Restore ke database produksi hanya dilakukan saat maintenance window dan setelah backup terbaru diverifikasi.

## Checklist deployment shared hosting

1. Backup database serta folder `writable/uploads`.
2. Set `CI_ENVIRONMENT=production`, `app.baseURL`, dan kredensial MySQL pada `.env` hosting.
3. Arahkan document root domain ke folder `public`.
4. Pastikan `writable`, `writable/cache`, `writable/logs`, `writable/session`, dan `writable/uploads` dapat ditulis PHP.
5. Jalankan `php spark migrate --all`, kemudian cek tabel `maintenance_attachments`.
6. Pastikan limit upload PHP minimal `upload_max_filesize=5M` dan `post_max_size=6M`.
7. Pasang cron harian dan jalankan satu kali secara manual.
8. Uji login tiap role, CSRF form, upload JPG/PNG/PDF, penolakan file selain format tersebut, export CSV, dan tampilan mobile.
9. Ganti password seed/default sebelum domain dibuka untuk publik.

## Matriks akses Phase 5

| Fitur | Admin/Manager | Operational | Finance | Marketing |
|---|---:|---:|---:|---:|
| Lihat ringkasan/riwayat/detail | Ya | Ya | Ya | Ya |
| Membuat dan mengerjakan Work Order | Ya | Ya | Tidak | Tidak |
| Upload foto/invoice | Ya | Ya | Tidak | Tidak |
| Verifikasi/tutup Work Order | Ya | Tidak | Tidak | Tidak |
| Export CSV biaya maintenance | Ya | Tidak | Ya | Tidak |
| Kelola kategori dan generate jadwal | Ya | Tidak | Tidak | Tidak |

