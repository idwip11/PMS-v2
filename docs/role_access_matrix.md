# Matriks Akses Role HomiQ PMS

Matriks ini berlaku untuk navigasi desktop/mobile dan proteksi URL langsung.

| Role | Dashboard | Finance | Analytics | Operasional | Front Desk | Housekeeping | Settings | Support |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Admin | Ya* | Tidak | Ya | Ya | Ya | Ya | Ya | Ya |
| Manager | Ya | Ya | Ya | Ya | Ya | Ya | Tidak | Ya |
| Finance | Ya | Ya | Ya | Ya | Ya | Ya | Tidak | Ya |
| Marketing | Ya | Tidak | Ya | Tidak | Ya | Ya | Tidak | Ya |
| Operational | Ya | Ya | Ya | Ya | Ya | Ya | Tidak | Ya |
| Housekeeping | Tidak | Tidak | Tidak | Tidak | Tidak | Ya | Tidak | Tidak |
| Staff | Ya* | Tidak | Ya | Tidak | Ya | Ya | Tidak | Ya |
| Owner GH 1–5 | Tidak | Keuangan Owner/Investor saja | Tidak | Tidak | Tidak | Tidak | Tidak | Tidak |

`*` Nominal Revenue dan Net Profit pada Dashboard disensor untuk Admin dan Staff. Nominal expense tetap ditampilkan.

Role Housekeeping diarahkan langsung ke halaman Housekeeping setelah login. Tombol New Booking juga tidak ditampilkan untuk role tersebut.

Role legacy `Atmin` dipertahankan sebagai superuser internal agar akun lama tidak terkunci. Hak aksi sensitif di dalam modul Operasional—seperti mengubah work order, inventaris, dan maintenance—tetap dapat lebih sempit daripada hak melihat menu sesuai filter aksi yang sudah diterapkan pada route.

## Role owner guesthouse

Role `Owner GH 1` sampai `Owner GH 5` hanya dapat membuka submenu **Finance → Keuangan Owner/Investor**. Guesthouse pada laporan otomatis dikunci sesuai role: Owner GH 1 hanya GH 1, hingga Owner GH 5 hanya GH 5. Parameter `guesthouse` di URL dan file Export Excel juga dipaksa ke GH milik owner, sehingga owner tidak dapat membuka laporan GH lain.
