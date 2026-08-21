# Analytics Marketing — Tahap 5

Tahap 5 menambahkan forecasting kamar-malam terjual untuk enam bulan berikutnya.

## Guardrail histori

- Input model adalah kamar-malam terjual per bulan lengkap.
- Bulan berjalan tidak dimasukkan karena datanya belum selesai.
- Minimal 24 bulan lengkap diperlukan sebelum forecast aktif.
- Bila histori kurang dari 24 bulan, dashboard hanya menampilkan status kelengkapan dan pesan `Histori belum cukup untuk forecasting`; tidak ada angka forecast buatan.
- Filter guesthouse tetap diterapkan. Batas waktu histori mengikuti akhir periode terpilih atau awal bulan berjalan, mana yang lebih dahulu.

## Model dan tampilan

Forecast memakai Holt-Winters additive di PHP dengan periode musiman 12 bulan dan horizon enam bulan. Hasil negatif dinormalkan menjadi nol dan hasil di atas kapasitas kamar dibatasi ke kapasitas bulan bersangkutan.

Saat aktif, grafik menampilkan:

- garis aktual;
- garis forecast putus-putus;
- rentang estimasi 95% berdasarkan RMSE residual model;
- label kualitas `Cukup` untuk 24–35 bulan dan `Baik` mulai 36 bulan.

Forecast merupakan estimasi, bukan data aktual atau jaminan tingkat penjualan.

## QA

```bash
php spark marketing:analytics-audit --year 2026
vendor/bin/phpunit --no-coverage --testsuite App
```

Test unit memvalidasi syarat minimal 24 titik, horizon enam bulan, hasil non-negatif, dan urutan batas bawah/forecast/batas atas. Audit dashboard memastikan forecast tidak muncul ketika histori tidak cukup.
