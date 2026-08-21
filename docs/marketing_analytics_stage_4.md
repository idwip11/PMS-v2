# Analytics Marketing — Tahap 4

Tahap 4 menambahkan ringkasan tahunan pada dashboard Marketing.

## Metrik tahunan

Setiap titik memakai satu tahun kalender penuh dan pilihan guesthouse dari filter global. Filter bulan tidak diterapkan di bagian ini agar perbandingan antar-tahun tetap setara; UI menampilkan penanda saat filter bulan sedang aktif.

```text
Okupansi tahunan weighted
= total kamar-malam terjual selama tahun
  / total kamar-malam tersedia selama tahun × 100%
```

Okupansi tidak dihitung dari rata-rata persentase bulanan. Kapasitas mengikuti jumlah kamar dalam pilihan GH dikalikan 365 atau 366 hari pada tahun tersebut.

Bagian ini menampilkan:

- kartu okupansi dan kamar-malam tahun yang dipilih;
- perubahan year-over-year (YoY) kamar-malam terjual dan perubahan poin persentase okupansi;
- bar chart okupansi tahunan weighted;
- line chart trend kamar-malam terjual per tahun.

YoY ditampilkan `-` bila belum terdapat pembanding tahun sebelumnya atau tahun pembanding memiliki nol kamar-malam terjual.

## QA

```bash
php spark marketing:analytics-audit --year 2026
php spark marketing:analytics-audit --year 2026 --month 8 --guesthouses 1,2
vendor/bin/phpunit --no-coverage --testsuite App
```

Audit memeriksa rumus weighted occupancy per tahun dan rumus perubahan YoY kamar-malam.
