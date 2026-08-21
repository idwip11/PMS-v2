# Rencana Implementasi Analytics Marketing HomiQ PMS

Dokumen ini menjadi acuan pengerjaan bertahap untuk dashboard Marketing yang menggunakan data nyata dari reservasi, tamu, kamar, pembayaran, dan expense.

## Tujuan

Dashboard Marketing akan membantu melihat:

1. Asal tamu untuk seluruh guesthouse.
2. Tamu paling loyal berdasarkan frekuensi booking.
3. Perbandingan performa setiap guesthouse.
4. Trend okupansi bulanan lintas guesthouse.
5. Rata-rata okupansi tahunan dan trend jumlah kamar tersewa.
6. Proyeksi jumlah kamar tersewa.

Implementasi tetap menggunakan CodeIgniter 4, MySQL, dan Chart.js agar sesuai batasan hosting Medium. Tidak ada service Python atau server forecasting terpisah.

## Kondisi data saat ini

- `guests.origin` sudah tersedia sebagai sumber asal tamu.
- Sebagian tamu masih belum memiliki nilai `origin`; nilai kosong ditampilkan sebagai `Tidak diketahui`.
- Reservasi memiliki `guest_id`, `room_id`, `booking_group_id`, tanggal menginap, status, sumber booking, dan nominal.
- Satu booking beberapa kamar dapat memiliki beberapa baris reservasi dengan `booking_group_id` yang sama.
- Expense dapat dikaitkan ke satu atau beberapa GH.
- Histori aktual saat ini masih pendek (terutama Agustus 2026), sehingga forecasting belum boleh dipaksakan sebelum histori mencukupi.

## Filter global dashboard

Semua kartu, tabel, dan grafik memakai filter yang sama:

- Tahun.
- Bulan atau seluruh bulan.
- Multi-select guesthouse: `Semua GH`, `GH 1`, `GH 2`, dan seterusnya.

Perubahan filter harus memperbarui seluruh komponen secara konsisten dan dapat dibagikan melalui query string URL.

## Definisi metrik

### 1. Asal tamu

- Sumber: `guests.origin`.
- Normalisasi teks agar perbedaan kapitalisasi/spasi tidak membuat kategori ganda.
- Nilai kosong menjadi `Tidak diketahui`.
- Versi awal menghitung tamu utama unik (`guest_id`) pada reservasi yang tidak cancelled.
- Grafik yang dipakai: horizontal bar atau donut; kategori dapat diurutkan dari terbesar.

Catatan: bila kebutuhan berubah menjadi jumlah rombongan atau jumlah orang, gunakan `guest_count` sebagai metrik terpisah. Jangan mencampurnya dengan jumlah tamu utama.

### 2. Tamu paling loyal

- Grup berdasarkan `guest_id`.
- Hitung `COUNT(DISTINCT booking_group_id)` agar booking beberapa kamar tidak terhitung berkali-kali.
- Reservasi cancelled tidak dihitung.
- Tampilkan Top 10 dengan jumlah booking, total malam, dan total nominal.
- Untuk jangka panjang, data tamu duplikat perlu direkonsiliasi berdasarkan email/nomor telepon.

### 3. Perbandingan performa GH

Kolom yang ditampilkan:

- Revenue.
- Cost/expense.
- Profit.
- Margin.

Rumus:

```text
Profit = Revenue - Cost
Margin = Profit / Revenue * 100%
```

Jika revenue nol, margin ditampilkan `-` agar tidak menyesatkan.

Definisi waktu:

- Revenue mengikuti tanggal pembayaran yang benar-benar diterima.
- Cost mengikuti tanggal expense dicatat.
- Periode laporan harus ditampilkan jelas pada UI.

### 4. Okupansi bulanan

```text
Occupancy = kamar-malam terjual / kamar-malam tersedia * 100%
```

- Kamar-malam terjual dihitung dari interval check-in/check-out.
- Reservasi lintas bulan dipecah sesuai jumlah malam pada setiap bulan.
- Reservasi cancelled tidak dihitung.
- Kapasitas awal: jumlah kamar aktif x jumlah hari pada bulan tersebut.
- Pengurangan kapasitas karena maintenance diterapkan setelah histori blokir kamar tersedia dengan baik.

Grafik berupa line chart dengan satu garis per tahun dan dukungan multi-GH.

### 5. Rata-rata okupansi tahunan dan kamar tersewa

- Rata-rata okupansi tahunan menggunakan weighted occupancy:

```text
total kamar-malam terjual selama tahun
/ total kamar-malam tersedia selama tahun
* 100%
```

- Jumlah kamar tersewa menggunakan kamar-malam terjual agar konsisten dengan okupansi.
- Tampilkan total tahunan dan perubahan year-over-year.
- Bila diperlukan, jumlah booking unik dapat ditampilkan sebagai metrik tambahan, bukan pengganti kamar-malam.

### 6. Forecast kamar tersewa

- Input: total kamar-malam terjual per bulan.
- Horizon awal: 6 bulan ke depan.
- Metode: Holt-Winters yang dihitung di PHP.
- Tampilkan data aktual, forecast, batas atas, dan batas bawah.
- Minimal 24 bulan histori lengkap sebelum forecast utama diaktifkan.
- Bila histori belum cukup, tampilkan pesan `Histori belum cukup untuk forecasting` dan jangan membuat angka semu.

Fallback yang dapat dipakai bila dibutuhkan:

- 12–23 bulan: trend/moving average dengan label kepercayaan rendah.
- Kurang dari 12 bulan: forecast dinonaktifkan.

## Tahapan pengerjaan

### Tahap 0 — Fondasi dan audit data

**Status: selesai (12 Agustus 2026).** Detail kontrak filter, cara menjalankan audit, dan baseline data tersedia di `docs/marketing_analytics_stage_0.md`.

- Buat service/query bersama untuk filter periode dan GH.
- Buat helper status reservasi yang dianggap valid.
- Tambahkan normalisasi asal tamu.
- Audit kelengkapan `origin`, duplikasi tamu, rentang tanggal, status reservasi, dan expense multi-GH.
- Tambahkan index database bila query dashboard mulai berat.

**Selesai jika:** semua metrik dapat memakai filter yang sama dan hasil query dapat direkonsiliasi dengan data sumber.

### Tahap 1 — Asal tamu dan loyalitas

**Status: selesai (12 Agustus 2026).** Implementasi, definisi metrik, dan hasil rekonsiliasi tersedia di `docs/marketing_analytics_stage_1.md`.

- Bangun kartu ringkasan jumlah tamu/booking.
- Tambahkan grafik asal tamu.
- Tambahkan tabel Top 10 tamu loyal.
- Tambahkan empty state dan penanda data asal tamu yang belum diketahui.

**Selesai jika:** booking multi-kamar tidak menggandakan hitungan loyalitas dan cancelled booking tidak masuk.

### Tahap 2 — Perbandingan performa GH

**Status: selesai (12 Agustus 2026).** Definisi metric, aturan alokasi expense, dan QA tersedia di `docs/marketing_analytics_stage_2.md`.

- Bangun tabel perbandingan revenue, cost, profit, dan margin.
- Tambahkan grafik perbandingan yang responsif.
- Tetapkan aturan alokasi expense multi-GH.

Rekomendasi awal: expense yang ditujukan ke beberapa GH dibagi rata. Tahap berikutnya dapat menambahkan nominal alokasi manual.

**Selesai jika:** total cost lintas GH tidak terduplikasi dan profit sesuai revenue dikurangi cost.

### Tahap 3 — Trend okupansi bulanan

**Status: selesai (12 Agustus 2026).** Definisi, pemecahan reservasi lintas bulan, dan QA tersedia di `docs/marketing_analytics_stage_3.md`.

- Buat query kamar-malam per bulan.
- Pecah reservasi lintas bulan.
- Tampilkan line chart per tahun dengan multi-GH.
- Tambahkan tooltip berisi kamar-malam terjual, kapasitas, dan persentase.

**Selesai jika:** periode check-in/check-out, cancelled booking, dan kapasitas kamar dihitung benar.

### Tahap 4 — Ringkasan tahunan

**Status: selesai (12 Agustus 2026).** Definisi weighted occupancy, aturan YoY, dan QA tersedia di `docs/marketing_analytics_stage_4.md`.

- Tambahkan bar chart rata-rata okupansi tahunan.
- Tambahkan trend kamar-malam terjual per tahun.
- Tampilkan perbandingan year-over-year.

**Selesai jika:** persentase tahunan memakai weighted occupancy dan dapat dijelaskan dari angka bulanan.

### Tahap 5 — Forecasting

**Status: selesai (12 Agustus 2026).** Metode, guardrail histori, confidence band, dan QA tersedia di `docs/marketing_analytics_stage_5.md`.

- Tambahkan pemeriksaan jumlah titik histori.
- Implementasikan Holt-Winters di PHP.
- Tambahkan confidence band dan label kualitas forecast.
- Sediakan pesan yang jelas bila data belum memadai.

**Selesai jika:** forecast tidak muncul ketika histori tidak cukup, dan hasil dapat diuji dengan data historis yang disisihkan sebagai validasi.

## Rancangan tampilan

Urutan komponen pada halaman Marketing:

1. Judul, filter tahun/periode, dan multi-select GH.
2. Kartu KPI ringkas: jumlah booking, kamar-malam terjual, okupansi, dan revenue.
3. Grafik asal tamu dan tabel tamu paling loyal.
4. Perbandingan revenue/cost/profit/margin antar-GH.
5. Trend okupansi bulanan.
6. Ringkasan okupansi tahunan dan trend kamar tersewa.
7. Forecast kamar tersewa.

Setiap tabel panjang mengikuti standar aplikasi: maksimal 10 baris per halaman dengan pagination.

## Validasi dan QA

- Bandingkan hasil dashboard dengan query SQL sampel.
- Uji booking satu kamar dan multi-kamar.
- Uji reservasi yang melewati pergantian bulan/tahun.
- Uji cancelled, confirmed, checked-in, dan checked-out.
- Uji GH tunggal, beberapa GH, dan seluruh GH.
- Uji periode tanpa transaksi.
- Uji revenue berbasis tanggal pembayaran dan okupansi berbasis tanggal menginap.
- Uji expense yang terhubung ke beberapa GH.
- Uji performa query pada jumlah data besar sebelum deployment.

## Keputusan yang perlu dipertahankan

- Forecast bukan data aktual dan harus diberi label jelas.
- Asal tamu berbeda dari sumber booking (misalnya Agoda atau Walk-in).
- Revenue, okupansi, dan expense menggunakan basis tanggal yang berbeda dan tidak boleh dicampur.
- Untuk perbandingan GH, aturan alokasi expense multi-GH harus konsisten.
- Data histori minimal 24 bulan direkomendasikan sebelum forecast utama dipercaya.

## Urutan implementasi yang direkomendasikan

```text
Fondasi/filter
  -> Asal tamu + loyalitas
  -> Performa tiap GH
  -> Okupansi bulanan
  -> Ringkasan tahunan
  -> Forecasting
```

Pengerjaan pertama yang paling aman adalah Tahap 0 dan Tahap 1, karena keduanya dapat berjalan dengan data yang sudah tersedia sekarang tanpa menunggu histori dua tahun.
