# Analytics Marketing — Tahap 3

Tahap 3 menambahkan trend okupansi bulanan ke dashboard Marketing.

## Definisi okupansi

```text
Okupansi = kamar-malam terjual / kamar-malam tersedia × 100%
```

- Kamar-malam terjual memakai reservasi `Confirmed`, `Checked_In`, dan `Checked_Out`.
- Interval menginap menggunakan `[check_in_date, check_out_date)`; tanggal check-out tidak dihitung sebagai malam.
- Reservasi yang melewati batas bulan dipecah ke bulan yang bersangkutan.
- Kapasitas memakai seluruh record kamar pada GH dalam filter dikalikan jumlah hari setiap bulan. Schema saat ini belum memiliki histori blokir kamar untuk mengurangi kapasitas maintenance.
- Contoh: 29 kamar × 31 hari pada Agustus = 899 kamar-malam kapasitas. Jika filter satu tahun 2026, 29 kamar × 365 hari = 10.585 kamar-malam kapasitas periode.
- Jika beberapa GH dipilih, kamar-malam terjual dan kapasitas dijumlahkan terlebih dahulu. Persentase akhirnya merupakan occupancy gabungan yang weighted, bukan rata-rata persentase GH.

Grafik garis memakai tahun/periode serta pilihan GH dari filter global. Tooltip setiap titik menjelaskan kamar-malam terjual, kapasitas, dan okupansi.

## QA

```bash
php spark marketing:analytics-audit --year 2026 --month 8
php spark marketing:analytics-audit --year 2026 --guesthouses 1,2
vendor/bin/phpunit --no-coverage --testsuite App
```

Audit memeriksa rumus okupansi per titik dan weighted occupancy periode. Test unit juga mencakup pengirisan reservasi yang menyeberangi awal/akhir bulan dan rentang menginap tidak valid.
