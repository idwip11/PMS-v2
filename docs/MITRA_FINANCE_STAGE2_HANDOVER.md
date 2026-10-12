# Handover Tahap 2 — Scope dan Skema Finance Mitra

Tanggal: 12 Oktober 2026 (Asia/Makassar)
Status: **Tahap 2 selesai pada aplikasi/database lokal.**

## Hasil

Migrasi `2026-10-12000000_AddMitraFinanceReportGroups` telah diterapkan melalui migration runner CodeIgniter pada database lokal `homiq_pms` dan dicatat pada tabel `migrations`.

Tiga tabel tambahan tersedia:

| Tabel | Isi/constraint |
| --- | --- |
| `finance_report_groups` | Satu kelompok `MITRA_COMBINED`, label `Mitra — Homestay & Kos`, aktif; kode unik, flag aktif 0/1 |
| `finance_report_group_properties` | Dua anggota: properti 2 dan 3; primary key pasangan group/property, setiap properti hanya dapat menjadi anggota satu kelompok; CHECK membatasi anggota pada 2/3, FK ke kelompok/properti |
| `finance_report_group_owner_setups` | Kosong; satu setup per kelompok, nama owner wajib tidak kosong, persentase owner wajib 0–100, tanpa default persentase; FK ke kelompok |

Pada fondasi tahap 2, constraint membership dibatasi pada properti Mitra 2/3. Kelompok tambahan untuk properti lain memerlukan desain/migrasi tersendiri. HomiQ tetap menggunakan scope satu properti dan setup GH yang sudah ada.

Migrasi memeriksa identitas kode properti sebelum membuat tabel. Eksekusi ulang `up()` aman terhadap duplikasi seed. `down()` dapat menghapus skema baru ketika setup owner masih kosong; rollback ditolak bila setup owner gabungan sudah berisi data. Rollback ini tidak memulihkan migrasi role/setup tahap berikutnya; pemulihan tahap tersebut perlu mengikuti pemetaan dan backup masing-masing.

Tidak ada nilai owner, persentase contoh, perubahan password/grant, atau salinan transaksi yang dibuat oleh tahap 2. Konfigurasi owner sebenarnya diisi pada tahap 4 berdasarkan keputusan tahap 1. Pemindahan role/grant lama dilakukan pada tahap 5.

## API yang siap digunakan

File: `app/Libraries/FinanceReportScope.php`.

```php
use App\Libraries\FinanceReportScope;

// Halaman Finance / panel / chart / ekspor umum:
$scope = FinanceReportScope::current(FinanceReportScope::REPORT);

// Halaman / ekspor owner:
$scope = FinanceReportScope::current(FinanceReportScope::OWNER_REPORT);

// Membaca atau mengelola setup owner:
$scope = FinanceReportScope::current(FinanceReportScope::OWNER_SETUP);

// Untuk pemanggilan tanpa konteks HTTP, memakai identitas yang sudah dipercaya:
$scope = FinanceReportScope::forUser($authenticatedUserId, $activePropertyId, $permission, $db);
```

Parameter user/active property harus berasal dari autentikasi dan konteks server, bukan query string pengguna. Resolver tidak menerima daftar property ID atau group ID dari request.

Nilai keluaran merupakan properti `readonly`:

| Properti | Keterangan |
| --- | --- |
| `type` | `property` untuk HomiQ; `group` untuk Mitra |
| `label` | Nama properti atau label kelompok; tetap gunakan `esc()` ketika ditampilkan |
| `propertyIds` | `[1]` untuk HomiQ; `[2,3]` untuk Mitra |
| `groupId` | `null` untuk HomiQ; ID kelompok nyata untuk Mitra |
| `permissions` | Daftar izin Finance yang diizinkan pada scope tersebut |
| `ownerGuesthouse` | GH terkunci untuk role Owner GH; `null` untuk staff/Mitra |

Tersedia `isCombined()` dan `allows($permission)`. Mengubah menu/dropdown operasional tidak diperlukan oleh resolver ini. Resolver tidak menulis sesi atau mengganti `PropertyContext::activeId()`.

`RuntimeException` dengan kode **403** berarti izin scope tidak lengkap/ditolak; kode **503** berarti skema atau konfigurasi kelompok belum tersedia/valid. Parameter permission yang tidak dikenali menghasilkan `InvalidArgumentException`. Pada integrasi controller tahap berikutnya, ubah penolakan 403/503 tersebut menjadi respons yang jelas dan jangan menurunkan scope menjadi laporan satu properti. Kesalahan database lain tetap ditangani sebagai kesalahan server, bukan disamarkan sebagai izin yang berhasil.

## Aturan izin

- HomiQ hanya mengembalikan `[1]`. Role Owner GH mempertahankan angka GH-nya melalui `ownerGuesthouse`; query laporan owner tetap wajib menerapkan batas GH tersebut.
- Staff Mitra harus memiliki role yang diizinkan oleh `RoleAccess::allows($role, 'finance')` pada **kedua** properti. Campuran Manager/Finance/Operational yang sama-sama memiliki izin Finance diterima.
- Izin staff pada scope: `finance`, `owner_finance`, `owner_setup`, mengikuti kebijakan Finance yang sekarang ada.
- Dua grant `Owner Mitra` mengizinkan **hanya** `owner_finance`. Satu grant, campuran owner/staff, dan dua role owner legacy tidak otomatis menjadi grant gabungan.
- Owner Mitra pada HomiQ ditolak. Grup dengan anggota tidak tepat `[2,3]`, kelompok tidak aktif, atau anggota tidak aktif tidak menghasilkan laporan parsial.
- Grant dibaca ulang langsung dari database setiap pemanggilan; pencabutan atau penurunan role tidak tertahan oleh cache/session role lama.

Pengakuan role `Owner Mitra` pada resolver adalah fondasi kontrak untuk tahap 5. Pilihan role di Settings, `RoleAccess::OWNER_ROLES`, landing/login, serta pemberian grant berpasangan belum diubah pada tahap 2. Endpoint laporan dan bukti keuangan juga belum dipasang ke resolver: pekerjaan integrasi tersebut mengikuti tahap 3–5.

## Bukti verifikasi

Runner pemeriksaan: `tests/finance/verify_scope_stage2.php`. Runner ini dapat dijalankan tanpa PHPUnit; dependensi PHPUnit tidak tersedia pada checkout saat tahap 2 dikerjakan.

```bash
# Default: database SQLite memory, tanpa mengakses data aplikasi.
php tests/finance/verify_scope_stage2.php

# MariaDB/MySQL: wajib clone lokal dengan nama sesuai guard.
# Buat clone struktur properties/users/user_property_roles/owner_guesthouse_setups,
# salin konfigurasi properti 1–3, dan gunakan tabel user/grant/owner yang kosong.
DB_NAME=homiq_pms_mitra_stage2_test_YYYYMMDD_SUFFIX php tests/finance/verify_scope_stage2.php --mysql-clone
```

Untuk mode MySQL, ganti `YYYYMMDD_SUFFIX` dengan tanggal delapan digit dan suffix huruf kecil/angka. Gunakan clone baru untuk setiap run; runner memasukkan fixture yang disengaja. Mode ini menolak database utama/remote dan nama clone yang tidak sesuai sebelum membuat fixture.

Hasil aktual:

- **47 pemeriksaan lolos** pada SQLite memory.
- **47 pemeriksaan lolos** pada clone struktur MariaDB **10.4.28** lokal. Clone tidak menyalin pengguna, password, transaksi, atau konfigurasi owner nyata.
- Validasi mencakup identitas properti, seed ulang, isolasi HomiQ, lock Owner GH, grant lengkap/tidak lengkap, role legacy, owner gabungan, pencabutan/downgrade role, metadata tidak lengkap/nonaktif, constraint anggota, persentase 0/100/pecahan/invalid, foreign key, setup tunggal, rollback, serta sesi operasional yang tidak berubah.
- Sesudah penerapan ke database aplikasi lokal, **19 tabel lama memiliki isi identik** berdasarkan perbandingan jumlah baris dan hash seluruh baris sebelum/sesudah. Cakupan meliputi properti, akun, grant, setup owner lama, kamar, tamu, reservasi, pembayaran, invoice items, expenses, inventaris, Extra Bed, shift, housekeeping, dan tabel perusahaan/ledger terkait.
- Sintaks migration, resolver, dan runner PHP diperiksa.

## Lanjutan tahap 3

1. Pakai `FinanceReportScope::current(REPORT)` untuk halaman, panel, chart, outstanding, pagination, dan ekspor Finance umum.
2. Ganti hanya query laporan dari `where(property_id = activeId)` ke scope tepercaya `whereIn(property_id, $scope->propertyIds)` pada sumber yang sesuai. Tetap pertahankan status pembayaran, refund, tanggal periode, dan rumus saat ini.
3. Tambahkan asal properti pada transaksi dan label cakupan yang sama saat membuka Finance dari Homestay/Kos.
4. Jangan menggunakan scope gabungan pada booking, Dashboard, Analytics, expense input, inventory, maintenance, atau modul operasional.
5. Tangani akses tidak lengkap sebagai penolakan, bukan angka parsial. Pengguna demo yang hanya diberi grant HomiQ tidak otomatis mendapat grant Mitra melalui tahap ini.
6. Query, pagination, chart, dan ekspor wajib memakai resolver yang sama. Integrasi setup owner/perhitungan dan role UI mengikuti tahap 4/5.

Halaman laporan masih memakai implementasi sebelumnya sampai tahap 3 dihubungkan. Tidak ada penerapan ke produksi pada tahap 2 ini.
