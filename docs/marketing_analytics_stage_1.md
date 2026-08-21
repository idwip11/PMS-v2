# Analytics Marketing — Tahap 1

Tahap 1 menambahkan tampilan analytics pada halaman Marketing. Fitur Campaign dan Program Loyalty kemudian dihapus permanen pada Tahap 2.

## Komponen

- Filter tahun, bulan/seluruh bulan, dan multi-select GH. Nilai filter tersimpan di query string URL.
- Kartu tamu utama unik, booking unik, dan origin yang belum diketahui.
- Grafik horizontal asal tamu yang diurutkan dari jumlah terbesar.
- Tabel Top 10 tamu loyal dengan booking unik, total malam, dan total nominal reservasi.
- Empty state untuk periode tanpa reservasi valid.
- Keterangan periode, scope GH, basis tanggal, serta definisi metrik pada halaman.

## Definisi metrik

Semua komponen Tahap 1 memakai:

- periode berdasarkan `reservations.check_in_date`;
- GH berdasarkan `rooms.floor`;
- status `Confirmed`, `Checked_In`, dan `Checked_Out`;
- tamu utama unik berdasarkan `guest_id`;
- booking unik berdasarkan `booking_group_id`, dengan fallback `order_id`;
- origin yang dinormalisasi saat agregasi, dengan nilai kosong menjadi `Tidak diketahui`.

Untuk ranking loyalitas, satu booking multi-kamar tetap dihitung satu booking. Total malam adalah durasi menginap per grup booking, sehingga kamar kedua tidak menggandakan malam. Total nominal menjumlahkan nilai baris reservasi yang masuk scope GH.

## Rekonsiliasi lokal — Agustus 2026

Semua GH:

- 14 baris reservasi menjadi 9 booking unik dan 9 tamu utama unik.
- Origin: `Tidak diketahui` 5, `Mataram` 3, dan `Bogor` 1.
- 5 dari 9 tamu utama (55,56%) belum memiliki origin.
- Rekonsiliasi command audit mengonfirmasi jumlah tamu dan booking dashboard cocok dengan audit sumber.

GH 1 dan GH 2:

- 9 baris reservasi menjadi 6 booking unik dan 6 tamu utama unik.
- Origin: `Tidak diketahui` 3, `Mataram` 2, dan `Bogor` 1.

Perintah QA:

```bash
php spark marketing:analytics-audit --year 2026 --month 8
php spark marketing:analytics-audit --year 2026 --month 8 --guesthouses 1,2 --json
```
