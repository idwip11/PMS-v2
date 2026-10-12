# Handover Tahap 6 — Validasi Keuangan Mitra Gabungan

Tanggal: 12 Oktober 2026. Status: **selesai pada aplikasi lokal**; tahap 7 merupakan review akhir klien dan konfigurasi akun riil. Pengujian tambahan yang tidak menentukan hasil dihentikan mengikuti arahan pengguna.

## Hasil dan perbaikan

- Laporan, ekspor, dan setup dari Homestay/Kos menghasilkan scope gabungan yang sama. Extra Bed kedua properti masuk dasar owner. Layanan langsung pengelola dikecualikan dari dasar owner.
- DELETE setup gabungan sebelumnya diperiksa sebagai ID setup properti lama. Filter sekarang memeriksa tabel setup kelompok dan izin owner_setup.
- Mengganti Owner Mitra dengan role staff sekarang menghapus kedua grant owner secara atomik, kemudian memasang role baru pada properti yang dipilih. Kegagalan perubahan grant kedua membatalkan seluruh transaksi. Pembuatan role owner lama yang terpisah dicegah; kompatibilitas grant lama tetap ditangani secara eksplisit.
- Pembacaan bukti pembayaran/receipt memakai grant terbaru dari database. Owner lama, grant setengah, dan campuran Owner/Staff ditolak. Staff satu properti hanya membaca bukti lokal. Penggabungan bukti hanya untuk GET; upload tetap per properti. Respons bukti memakai private/no-store.
- Outstanding dihitung per reservasi aktif: kelebihan pembayaran suatu booking tidak menghapus tagihan booking lain; booking batal dikecualikan. Booking group dihitung dengan batas properti dan ID kosong memiliki fallback. Ringkasan/chart memakai cent integer dan urutan transaksi stabil untuk pagination.
- Konstanta HOMESTAY_ID/KOS_ID ditambahkan karena ketersediaan Extra Bed sebelumnya merujuk KOS_ID yang belum didefinisikan.
- Form filter Finance dan Setup/Settings diperbaiki agar muat pada 320px; kolom Settings tablet dibuat lebih lapang. Nominal hak owner/pengelola pada holding mempertahankan pecahan sen; keterangan layanan langsung sesuai kebijakan Extra Bed Mitra.

## Bukti pemeriksaan

| Pemeriksaan | Hasil |
| --- | --- |
| Regresi tahap 2 / 3 / 4 / 5 | 47 / 50 / 84 / 49 lulus |
| Tahap 6 pada skema MariaDB aplikasi | 70 lulus |
| Pecahan uang, batas bulan, grup booking dan pagination | 8 lulus |
| Holding, reversal, rekonsiliasi dan snapshot historis | 9 lulus |
| HTTP nyata: login, CSRF, filter, setup, ekspor dan bukti | 63 lulus |
| Total pemeriksaan otomatis | **380 lulus** |
| Browser: 6 halaman/alur × 5 ukuran viewport | **30 kombinasi lulus**, tanpa exception JavaScript |

PHP runtime 8.3.33; syntax check file aplikasi yang berubah lulus. Browser mencakup Finance, Owner, Setup Owner, Settings, modal booking, dan laporan bulanan holding pada 320, 390, 768, 1024, 1440px. Tabel lebar tetap dapat digeser. Pemeriksaan browser berlangsung sebelum perubahan akhir formatter nominal; perubahan formatter kemudian diperiksa lewat HTTP nyata.

Fixture laporan umum: pemasukan 2.000, biaya 2.100, net -100, outstanding 300. Dasar owner Mitra 1.800 × 33,33% = 599,94, walaupun laba operasional negatif. Fixture holding: kas terkonfirmasi net 1.250, gross 1.300; dasar owner 1.200, hak owner 399,96, fee pengelola 800,04 dan layanan langsung 100. Total hak perusahaan 900,04. Perbedaan laporan Paid dan holding mengikuti basis kas terkonfirmasi, bukan selisih perhitungan.

Persentase 0%, 100% dan pecahan, konfigurasi kosong, HomiQ/GH 1–5, refund/batal/DP/pelunasan, izin stale session, rollback grant kedua dan rollback migrasi telah diuji. Snapshot periode lama tidak ditulis ulang. Penolakan akses dapat berupa 403/404 atau redirect 302/303 sesuai filter rute; tidak semua penolakan berupa 403.

## Isolasi dan data aplikasi

Pengujian MariaDB memakai salinan struktur 55 tabel, metadata properti/kamar/kelompok, serta akun dan transaksi sintetis. Server uji localhost:8082 dan Chrome headless terpisah. Database uji telah dihapus; server dan Chrome uji telah ditutup; file bukti fixture dibersihkan.

Perbandingan hash akhir: **54 dari 55 tabel aplikasi tidak berubah**, termasuk transaksi keuangan, reservasi, grant pengguna, dan setup owner. Tabel extra_bed_settings memiliki inisialisasi default Homestay stok 5 dan Kos stok 0 pada jendela validasi (13:59). Baris default tersebut dipertahankan; tidak ada penghapusan atau rollback data pengguna untuk memaksakan hash identik. Ini merupakan batas isolasi pemeriksaan modal booking yang perlu diperhatikan saat mengulang tes browser.

Rekonsiliasi read-only aplikasi lokal bulan 2026-10: sumber Homestay dan Kos masing-masing pemasukan/biaya 0; total gabungan cocok dengan SQL sumber dan identik dari kedua dropdown. Data lokal kosong bukan satu-satunya bukti: perhitungan nonzero diverifikasi dengan fixture di atas.

Audit akun lokal: Manager memiliki akses gabungan yang memadai; role Admin tidak memperoleh izin Finance tambahan. Belum ada akun Owner Mitra atau setup owner gabungan riil. Nama dan persentase fixture hanya untuk tes, tidak disimpan sebagai keputusan bisnis.

## File dan pengulangan pemeriksaan

Runner di tests/finance: verify_stage6_validation.php, verify_stage6_financial_edges.php, verify_stage6_holding.php, verify_stage6_http.py, verify_stage6_browser.cjs. Tiga runner PHP mensyaratkan database clone lokal bernama homiq_pms_mitra_stage6_test_YYYYMMDD_suffix; runner utama mensyaratkan tanpa akun sebelum memasang fixture. Jalankan utama, financial_edges, lalu holding; HTTP/browser memakai server clone port 8082. Jangan arahkan runner atau request modal booking ke database aplikasi utama. Siapkan clone struktur dan metadata dahulu; fixture/test database sudah dibersihkan setelah validasi ini.

Bukti tersimpan di [ringkasan QA](qa/mitra-stage6/validation-summary.json), [matriks responsif](qa/mitra-stage6/responsive-matrix.json), [rekonsiliasi lokal](qa/mitra-stage6/local-reconciliation.json), [Finance 320px](qa/mitra-stage6/finance-320px.png), [Settings 768px](qa/mitra-stage6/settings-768px.png).

## Lanjutan tahap 7

Review manual klien, tetapkan nama/persentase owner dan akun penerima Owner Mitra, lalu konfigurasi melalui UI dengan izin Manager yang sesuai. Tidak ada persentase 70% otomatis atau pemilihan akun riil tanpa keputusan klien. Lakukan smoke check manual modul operasional lainnya (Dashboard, Analytics, inventory, maintenance dan input biaya); alur booking/ketersediaan utama sudah diperiksa. Tidak dilakukan deployment produksi pada tahap ini.
