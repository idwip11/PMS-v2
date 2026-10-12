# Handover Tahap 5 — Role dan UI Mitra Gabungan

Tanggal: 12 Oktober 2026 (Asia/Makassar)
Status: **Pekerjaan Tahap 5 — Role dan UI telah selesai diimplementasikan secara lokal dan lulus pengujian regresi.**

---

## 1. Hasil Implementasi

Pekerjaan **Tahap 5 — Role dan UI** telah diselesaikan sesuai dengan arahan pada [IMPLEMENTATION_PLAN_MITRA_FINANCE_COMBINED.md](IMPLEMENTATION_PLAN_MITRA_FINANCE_COMBINED.md) serta catatan pada [MITRA_FINANCE_STAGE4_HANDOVER.md](MITRA_FINANCE_STAGE4_HANDOVER.md). Desain antarmuka dibuat terpadu dengan sistem desain Bootstrap 5 aplikasi yang ada ("tidak AI slopp").

### A. Katalog Role & Otorisasi Terpusat (`RoleAccess.php`)
- Menambahkan role `'Owner Mitra'` ke dalam konstanta `RoleAccess::OWNER_ROLES`.
- Konfigurasi hak akses otomatis mencakup:
  - `owner_finance`: diizinkan (membuka laporan bagi hasil owner gabungan).
  - `payment_proof`: diizinkan (melihat bukti pembayaran transfer/OTA).
  - `expense_receipt`: diizinkan (melihat kuitansi/struk biaya operasional).
- Penolakan hak akses operasional:
  - `dashboard`: **ditolak** (403).
  - `finance` (laporan umum staf): **ditolak** (403).
  - `front_desk`, `operations`, `settings`, `housekeeping`: **ditolak** (403).
- Konfigurasi pendaratan & status:
  - `RoleAccess::landingPath('Owner Mitra')` mengembalikan `'finance/owner'`.
  - `RoleAccess::ownerGuesthouse('Owner Mitra')` mengembalikan `null` (tidak terikat guesthouse HomiQ).
  - `masksDashboardFinancials`: `false`.

### B. Arsitektur Paket Pasangan Atomik (`SettingsController.php`)
- Role `'Owner Mitra'` dikelola sebagai satu paket pasangan atomik untuk **Homestay Mitra (ID 2)** dan **Kos Mitra (ID 3)**:
  - **Tambah User Baru (`createUser`)**: jika role `'Owner Mitra'` dipilih, sistem mengeksekusi batch insert di dalam transaksi database untuk membuat dua baris penugasan pada tabel `user_property_roles` (satu untuk Homestay Mitra dan satu untuk Kos Mitra).
  - **Simpan/Ubah Role Properti (`savePropertyRole`)**: jika role disetel ke `'Owner Mitra'`, sistem secara atomik meng-upsert kedua penugasan Homestay Mitra dan Kos Mitra ke `'Owner Mitra'`.
  - **Cabut Akses Properti (`deletePropertyRole`)**: mencabut penugasan yang ber-role `'Owner Mitra'` secara atomik menghapus kedua penugasan (Homestay Mitra dan Kos Mitra) dalam satu transaksi.
- **Validasi Server-Side**:
  - Role `'Owner Mitra'` **dilarang keras** diberikan ke properti HomiQ (ID 1). Percobaan menetapkan role ini pada HomiQ akan ditolak dengan pesan peringatan yang jelas.
  - Role lama (`Owner HS Mitra` dan `Owner Kos Mitra`) dilarang diberikan ke HomiQ.
- **Perlindungan Invariant & Integritas Akun**:
  - Perlindungan akun Manager dari modifikasi/penghapusan oleh non-Manager.
  - Perlindungan Admin terakhir pada HomiQ dari penurunan role atau pencabutan.
  - Perlindungan sesi aktif: pengguna tidak dapat menghapus penugasan properti yang sedang aktif digunakan.
  - Batas penugasan minimum: pencabutan ditolak jika menyebabkan pengguna memiliki 0 properti.
  - Sinkronisasi sesi otomatis (`PropertyContext::synchronize()`) jika penugasan yang diubah adalah milik akun pengguna yang sedang login.

### C. Otorisasi Bukti Keuangan Lintas Properti
- **`PropertyReadyFilter.php`**:
  - Endpoint bukti keuangan `#^payments/\d+/proof#` dan `#^ops/expenses/\d+/receipt#` diperluas bagi pengguna Mitra yang memiliki cakupan Finance gabungan terotorisasi (`FinanceReportScope::OWNER_REPORT`).
  - Target properti untuk endpoint bukti mencakup `[2, 3]` (Homestay Mitra & Kos Mitra).
  - Endpoint operasional lainnya (`reservations/{id}`, `payments/{id}`, `guests/{id}`, `ops/expenses/{id}`) **tetap 100% terkunci** pada properti tunggal aktif (`PropertyContext::activeId()`).
- **`ReservationController::paymentProof(int $id)`**:
  - Memverifikasi apakah pengguna memiliki scope gabungan terotorisasi. Jika ya, memperbolehkan membaca bukti pembayaran dari properti 2 atau 3. Bukti pembayaran milik HomiQ (ID 1) tetap mengembalikan 404.
- **`OpsController::receipt(int $id)`**:
  - Memverifikasi apakah pengguna memiliki scope gabungan terotorisasi. Jika ya, memperbolehkan membaca kuitansi biaya operasional dari properti 2 atau 3. Kuitansi milik HomiQ (ID 1) tetap mengembalikan 404.
- **Prinsip *Fails Closed***:
  - Staf yang hanya memiliki penugasan pada satu properti mitra (misal Homestay saja) tidak dapat membuka file bukti milik properti pasangannya (404).

### D. Tampilan Antarmuka & Navigasi Bersih ("Tidak AI Slopp")
- **`app/Views/components/settings_panel.php`**:
  - Menggantikan opsi dropdown terpisah `Owner HS Mitra` dan `Owner Kos Mitra` dengan satu opsi konsisten: `Owner Mitra (Homestay + Kos)`.
  - Tetap menyediakan fallback kompatibel untuk akun lama yang memiliki role legacy dengan tanda `(Legacy)`.
  - Memberikan indikator badge rapi: `<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Homestay &amp; Kos</span>` di sebelah label role aktif pada kartu pengguna.
  - Menampilkan petunjuk kontekstual elegan saat opsi `Owner Mitra` dipilih pada form pengguna baru.
  - Konfirmasi pencabutan role secara eksplisit menginformasikan bahwa akses ke Homestay dan Kos Mitra akan dicabut sekaligus.
  - Fungsi sinkronisasi JavaScript (`syncPropertyFromRole` dan `syncRoleFromProperty`) diselaraskan untuk mengunci dan memilih properti secara mulus tanpa konflik.
- **`app/Views/components/sidebar.php`**:
  - Untuk pengguna dengan role `'Owner Mitra'`, sidebar hanya menyajikan submenu **Finance → Keuangan Owner/Investor**.
  - Menu Dashboard, Front Desk, Housekeeping, Analytics, Operasional, Settings, Keuangan Properti (staf), dan Setup Owner tersembunyi sepenuhnya.
- **`app/Views/pages/support.php`**:
  - Memperbarui tabel matriks hak akses untuk mencantumkan deskripsi role `Owner Mitra`.

---

## 2. File yang Diubah & Dibuat

| File | Perubahan |
| --- | --- |
| `app/Libraries/RoleAccess.php` | Menambahkan `Owner Mitra` ke `OWNER_ROLES` (otomatis mewarisi `owner_finance`, `payment_proof`, `expense_receipt`, dan landing page `finance/owner`). |
| `app/Controllers/SettingsController.php` | Menambahkan `Owner Mitra` ke daftar role, validasi anti-HomiQ, pembuatan berpasangan atomik, update berpasangan atomik, dan pencabutan berpasangan atomik. |
| `app/Filters/PropertyReadyFilter.php` | Mengizinkan akses bukti pembayaran & receipt bagi scope gabungan terotorisasi pada properti 2 dan 3, sambil mempertahankan isolasi ketat operasi lainnya. |
| `app/Controllers/ReservationController.php` | Method `paymentProof` memperbolehkan scope gabungan terotorisasi membaca bukti transaksi Homestay & Kos. |
| `app/Controllers/OpsController.php` | Method `receipt` memperbolehkan scope gabungan terotorisasi membaca kuitansi expense Homestay & Kos. |
| `app/Views/components/settings_panel.php` | Antarmuka dropdown role tunggal, badge visual berpasangan, pesan konfirmasi atomik, dan JavaScript sinkronisasi role. |
| `app/Views/pages/support.php` | Dokumentasi matriks hak akses untuk role `Owner Mitra`. |
| `tests/unit/RoleAccessTest.php` | Penyesuaian unit test ekspektasi `OWNER_ROLES`. |
| `tests/finance/verify_stage4_owner_setup.php` | Penyelarasan nilai uji holding & UI pasca arahan Extra Bed Kos (semua 84 checks lolos). |
| `tests/finance/verify_stage5_role_ui.php` | Runner uji komprehensif baru untuk seluruh skenario Tahap 5 (49 checks). |

---

## 3. Bukti Pengujian

Semua pengujian regresi dijalankan secara terisolasi:

```bash
php tests/finance/verify_scope_stage2.php
php tests/finance/verify_stage3_combined_report.php
php tests/finance/verify_stage4_owner_setup.php
php tests/finance/verify_stage5_role_ui.php
```

### Hasil Uji:
- **Tahap 2 (Scope & Skema)**: **47 / 47 PASS** (100%)
- **Tahap 3 (Laporan Umum)**: **50 / 50 PASS** (100%)
- **Tahap 4 (Owner & Setup)**: **84 / 84 PASS** (100%)
- **Tahap 5 (Role & UI)**: **49 / 49 PASS** (100%)
- **Total Uji Terverifikasi**: **230 / 230 PASS (100% Green, 0 Failures)**.

### Rincian Pemeriksaan Tahap 5 yang Lolos:
1. `RoleAccess::OWNER_ROLES` menyertakan `Owner Mitra`.
2. Izin `owner_finance`, `payment_proof`, dan `expense_receipt` aktif untuk `Owner Mitra`.
3. Seluruh izin operasional (`dashboard`, `finance`, `front_desk`, `operations`, `settings`, `housekeeping`) terkunci dari `Owner Mitra`.
4. Pendaratan login terarah tepat ke `finance/owner`.
5. Percobaan pemberian `Owner Mitra` ke HomiQ via `createUser` dan `savePropertyRole` ditolak server.
6. Pembuatan akun `Owner Mitra` menghasilkan tepat 2 assignment (`HOMESTAY_MITRA` & `KOS_MITRA`).
7. Update role akun ke `Owner Mitra` menghasilkan penugasan berpasangan pada kedua properti mitra.
8. Pencabutan role `Owner Mitra` menghapus penugasan pada kedua properti mitra secara atomik.
9. Penolakan pencabutan jika menyisakan 0 properti bagi pengguna atau menghapus role aktif sesi saat ini.
10. Perlindungan Admin HomiQ terakhir tetap terjaga.
11. Akses file bukti transfer dan kuitansi operasional lintas properti mitra berhasil dibuka untuk scope gabungan sah (`Owner Mitra` dan Staf Finance berpasangan).
12. Pembacaan bukti file HomiQ dari akun Mitra ditolak (404).
13. Pembacaan bukti file lintas properti oleh staf single-property ditolak (404, *fails closed*).
14. Isolasi endpoint booking dan operasional non-bukti tetap 100% terjaga per properti.
15. Render HTML Sidebar hanya menampilkan `Keuangan Owner/Investor` tanpa menu operasional atau dashboard.
16. Render Settings Panel menampilkan opsi `Owner Mitra (Homestay + Kos)` dan badge penanda pasangan.

---

## 4. Rekomendasi Menuju Tahap 6 & 7

1. **Tahap 6 — Validasi Lanjutan**:
   - Menjalankan audit menyeluruh terhadap matriks otorisasi pengguna riil.
   - Menguji alur login langsung melalui browser pada berbagai ukuran layar (mobile, tablet, desktop) untuk memastikan responsivitas dan konsistensi layout visual.
   - Memastikan tidak ada data operasional nyata (booking, kamar, tamu) yang terpengaruh.
2. **Tahap 7 — Tinjau Hasil Lokal**:
   - Menyiapkan ringkasan perbandingan data keuangan gabungan untuk diserahkan dan ditinjau oleh pengguna/klien sebelum diterapkan ke lingkungan produksi.
