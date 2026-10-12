# Matriks Akses Role HomiQ PMS

Matriks ini berlaku untuk navigasi desktop/mobile dan proteksi URL langsung.

| Role | Dashboard | Finance | Analytics | Operasional | Front Desk | Housekeeping | Settings | Support |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Admin | Ya* | Tidak | Ya | Ya | Ya | Ya | Ya | Ya |
| Manager | Ya | Ya | Ya | Ya | Ya | Ya | Ya | Ya |
| Finance | Ya | Ya | Ya | Ya | Ya | Ya | Tidak | Ya |
| Marketing | Ya | Tidak | Ya | Tidak | Ya | Ya | Tidak | Ya |
| Operational | Ya | Ya | Ya | Ya | Ya | Ya | Tidak | Ya |
| Housekeeping | Tidak | Tidak | Tidak | Tidak | Tidak | Ya | Tidak | Tidak |
| Staff | Ya* | Tidak | Ya | Tidak | Ya | Ya | Tidak | Ya |
| Owner GH 1–5 | Tidak | Keuangan Owner/Investor saja | Tidak | Tidak | Tidak | Tidak | Tidak | Tidak |
| Owner HS Mitra | Tidak | Keuangan Owner/Investor saja | Tidak | Tidak | Tidak | Tidak | Tidak | Tidak |
| Owner Kos Mitra | Tidak | Keuangan Owner/Investor saja | Tidak | Tidak | Tidak | Tidak | Tidak | Tidak |

`*` Nominal Revenue dan Net Profit pada Dashboard disensor untuk Admin dan Staff. Nominal expense tetap ditampilkan.

Role Housekeeping diarahkan langsung ke halaman Housekeeping setelah login. Tombol New Booking juga tidak ditampilkan untuk role tersebut.

### Ketentuan khusus menu Settings

- **Manager**: Akses penuh ke seluruh Settings termasuk membuat, mengedit, dan menghapus user mana pun (termasuk sesama Manager).
- **Admin**: Dapat membuka menu Settings dan **membuat** user baru dengan role apa pun (termasuk Manager), namun **tidak dapat mengedit maupun menghapus** user yang memiliki role Manager di properti mana pun. Form edit/hapus/ganti role pada kartu user Manager akan ditampilkan sebagai *read-only* untuk Admin.

Role legacy `Atmin` dipertahankan sebagai superuser internal agar akun lama tidak terkunci. Hak aksi sensitif di dalam modul Operasional—seperti mengubah work order, inventaris, dan maintenance—tetap dapat lebih sempit daripada hak melihat menu sesuai filter aksi yang sudah diterapkan pada route.

## Role owner guesthouse & mitra

- **Owner GH 1–5**: Berlaku untuk properti HomiQ. Hanya dapat membuka submenu **Finance → Keuangan Owner/Investor**. Guesthouse pada laporan otomatis dikunci sesuai role (Owner GH 1 ke GH 1, dst.).
- **Owner HS Mitra**: Berlaku khusus properti Homestay Mitra. Hanya dapat membuka **Finance → Keuangan Owner/Investor** untuk Homestay Mitra miliknya saja. Tidak dapat membuka menu operasional umum.
- **Owner Kos Mitra**: Berlaku khusus properti Kos Mitra. Hanya dapat membuka **Finance → Keuangan Owner/Investor** untuk Kos Mitra miliknya saja. Tidak dapat membuka menu operasional umum.
