# Implementation plan — Keuangan Perusahaan (Holding)

Tanggal: 1 Oktober 2026. Status: **CF-1, CF-2, dan CF-3 selesai secara lokal; fase lainnya belum diimplementasikan**. Fondasi tidak mengaktifkan holding, memberi grant, atau mengisi saldo secara otomatis. Production belum berubah.

## 1. Tujuan dan batas laporan

Menu ini menjadi pusat keuangan seluruh properti: HomiQ/Adiputra (GH 1–5), Homestay Mitra, Kos Mitra, serta properti berikutnya. Keuangan Properti tetap dipakai untuk operasi masing-masing unit. Keuangan Perusahaan memakai konteks perusahaan tersendiri, bukan properti yang kebetulan aktif pada dropdown header.

Rekomendasi: bangun **laporan manajemen gabungan + pengendalian kas** terlebih dahulu. Jangan menyebutnya laporan konsolidasi akuntansi formal sebelum kepemilikan badan usaha, kontrak pengelolaan, dan kebijakan akuntansinya disepakati. Semua properti yang dikelola belum tentu merupakan anak perusahaan yang dimiliki holding.

Pisahkan tiga pandangan:

1. **Kinerja seluruh properti:** aktivitas usaha gabungan dan kontribusi masing-masing properti.
2. **Hak ekonomi perusahaan:** pendapatan/fee yang menjadi hak pengelola setelah memisahkan hak owner dan dana titipan.
3. **Kas aktual:** uang yang benar-benar diterima/dibayar melalui kas atau rekening yang dikelola, termasuk dana yang belum boleh digunakan perusahaan.

Kas masuk bukan otomatis pendapatan; kas tersedia bukan otomatis laba. Jika pengelola bertindak sebagai agen, pendapatan perusahaan dapat berupa fee, bukan seluruh pembayaran tamu. Penentuan principal/agent harus berdasar kontrak, bukan nama properti atau persentase default.

## 2. Temuan aplikasi saat ini

Sumber yang diperiksa: `FinanceController.php`, `RoleAccess.php`, route Finance, migration schema, ledger multi-property, dan `finance_company.php`.

- CodeIgniter 4/PHP, MySQL/MariaDB, Bootstrap, HTMX, JavaScript, Chart.js; ekspor Finance memakai XML SpreadsheetML berformat `.xls`.
- `/finance/company` masih placeholder. Izin route masih `access:finance`; izin tersebut belum membedakan Finance properti dengan Finance holding.
- Keuangan Properti mengambil pembayaran `Paid` berdasarkan `payment_date`, dan expense selain `Rejected` berdasarkan `date_incurred`. Expense yang dicatat belum tentu sudah dibayar.
- Dalam schema yang diperiksa belum ada ledger rekening kas, saldo awal rekening, dan settlement expense untuk dasar saldo bank aktual.
- Laporan owner HomiQ sudah membedakan beberapa layanan milik perusahaan dan membagi biaya antar GH. Hasil perhitungan bagian owner belum menjadi ledger utang owner beserta pelunasannya.
- Homestay dijual Rp600.000/rumah/malam dengan tiga kamar diblokir; Kos Rp500.000/kamar/bulan dari check-in. Pendapatan Homestay tidak boleh menjadi tiga kali lipat karena tiga baris kamar.
- Pembagian hasil Mitra pernah ditunda. Ini **bukan** bukti bahwa seluruh penerimaannya milik holding; perjanjian terbaru dan source owner harus diaudit sebelum implementasi.

Laporan existing tidak diubah diam-diam. Bila laporan holding memakai basis berbeda, tampilkan label basis dan tabel rekonsiliasi perbedaan.

## 3. Rancangan tampilan

### Header dan filter

- Banner `Keuangan Holding • seluruh properti dalam cakupan perusahaan` dan daftar properti yang tercakup.
- Filter bulan, properti (semua/satu/beberapa), rekening, dan basis laporan. Jangan menjumlah rekening di luar kendali perusahaan.
- Dropdown properti global tetap untuk navigasi modul properti; pemilihannya tidak mengubah cakupan holding secara diam-diam. Endpoint holding tidak mengganti sesi properti aktif.
- Tanggal/jam pembaruan, basis laporan, mata uang IDR, status rekonsiliasi, serta peringatan transaksi belum dipetakan.
- Jika cakupan hanya sebagian unit, judul `Laporan gabungan properti terpilih`, bukan total seluruh perusahaan.

### Ringkasan di atas

| Kartu | Definisi |
| --- | --- |
| Saldo kas/bank terkini | Saldo awal + mutasi posted sampai waktu sekarang, per rekening dan total |
| Estimasi kas bebas | Saldo kas dikurangi dana yang harus dicadangkan: owner, refundable deposit/refund, dan cadangan disepakati; hanya kewajiban terkait uang yang dikelola, tanpa hitung ganda |
| Kas masuk bulan terpilih | Penerimaan aktual eksternal selama bulan laporan |
| Kas keluar bulan terpilih | Pembayaran aktual eksternal selama bulan laporan |
| Arus kas bersih bulan | Kas masuk − kas keluar; bukan laba |
| Hasil usaha perusahaan | Pendapatan yang menjadi hak perusahaan − beban perusahaan menurut basis yang ditampilkan |

Saldo **terkini** dan laporan **bulan terpilih** diberi judul berbeda. Saat memilih September, sediakan saldo penutup September; jangan menampilkan saldo hari ini seolah saldo September. Kas bebas merupakan estimasi yang dapat negatif, bukan saldo bank kedua atau dana otomatis boleh dibagikan.

### Tab utama

1. **Ringkasan:** kartu, tren enam bulan, kontribusi properti, perbandingan bulan sebelumnya, dan alert.
2. **Laporan bulanan:** gabungan usaha properti, jembatan ke hak perusahaan, overhead kantor pusat, hasil perusahaan. Pada MVP label `Ringkasan berbasis kas`; laba rugi akrual hanya setelah fase akrual selesai.
3. **Arus kas & rekening:** saldo awal, penerimaan, pengeluaran, saldo akhir; kategori operasi/investasi/pendanaan; saldo setiap rekening; drill-down mutasi dan bukti.
4. **Kewajiban & tagihan:** hak owner belum disetor, refund/deposit, expense belum dibayar, tagihan tamu dan OTA belum cair. Kelompok ini bukan tambahan saldo kas.
5. **Rekonsiliasi & tutup bulan:** saldo sistem vs rekening koran/kas fisik, selisih, transaksi belum dipetakan, status penutupan, dan jejak koreksi.

Tabel kontribusi: properti, penerimaan tamu, pembayaran operasional, surplus kas unit, hak owner, fee/hak perusahaan, alokasi overhead bila digunakan. Angka yang basisnya berbeda tidak ditempatkan dalam satu total tanpa rekonsiliasi.

Tabel mutasi: waktu transaksi, properti/holding, rekening, jenis, kategori, nominal masuk/keluar, referensi booking/expense, catatan, bukti, pembuat, dan status. Catatan harus ikut ekspor. Layout responsif; gunakan scroll horizontal tabel, bukan memampatkan header/tanggal.

## 4. Aturan keuangan wajib

- **Pembayaran tamu:** mutasi terjadi setelah penerimaan uang dikonfirmasi ke rekening/kas. Bukti upload atau status Paid lama saja tidak menjamin bank sudah menerima uang. Bedakan `posted` dan `reconciled`.
- **DP:** menambah kas ketika diterima; pengakuan pendapatan mengikuti kewajiban layanan/kontrak. DP hangus mengikuti aturan pembatalan yang sah; tidak otomatis pendapatan saat booking dibuat.
- **OTA:** total booking, piutang OTA, komisi, dan pencairan bersih dipisah. Rekening hanya bertambah sebesar dana yang benar-benar diterima; hindari pengakuan booking dan pencairan sebagai dua penerimaan.
- **Expense:** tanggal terjadi biaya, persetujuan, dan tanggal pembayaran terpisah. Mendukung pembayaran parsial. Expense belum dibayar tidak mengurangi kas.
- **Refund:** perubahan pembayaran menjadi `Refund_Pending` tidak menghapus penerimaan historis. Kas berkurang hanya saat refund dibayar; perubahan status tidak boleh membuat ledger kehilangan jejak uang masuk.
- **Owner:** kewajiban owner dihitung sesuai kontrak dan dapat belum final sebelum tutup bulan. Pembayaran owner melunasi kewajiban, bukan biaya kedua setelah bagian owner sudah dipisah.
- **Transfer internal:** dua kaki mutasi terkait dalam satu transaksi database; saldo rekening berubah, total kas holding tidak berubah. Hilangkan transfer internal dari total penerimaan/pengeluaran eksternal. Biaya transfer bank tetap pengeluaran eksternal. Rekening di luar cakupan bukan transfer internal.
- **Modal/pinjaman:** kas masuk pendanaan, bukan pendapatan usaha; pelunasan pokok bukan beban usaha. Bunga dan pajak memakai kebijakan yang disepakati, tanpa asumsi tarif.
- **Aset:** pembelian aset adalah kas keluar investasi; bukan seluruhnya beban operasional saat dibeli. Penyusutan masuk fase akrual.
- **Overhead holding:** dicatat satu kali, tidak disamarkan menjadi properti HomiQ. Alokasi ke unit hanya lapisan laporan manajemen; total holding tidak menghitung biaya asal plus alokasi dua kali.
- **Pendapatan Kos lintas bulan:** kas diterima pada tanggal pembayaran; pendapatan akrual, bila diaktifkan, dialokasikan sesuai layanan sewa pada masing-masing periode. Tidak mengubah tarif kontrak atau membuat prorata tarif booking baru.
- **Saldo awal:** bukan pemasukan usaha. Satu tanggal cutover dan saldo per rekening yang diverifikasi; transaksi sebelum cutover tidak ditambahkan lagi di atas saldo awal.

Contoh ilustratif, bukan data aplikasi: saldo rekening Rp10 juta dengan Rp7 juta hak owner dan Rp1 juta dana refund/titipan berarti estimasi kas bebas Rp2 juta, bukan laba Rp10 juta. Setiap kewajiban hanya masuk satu cadangan untuk mencegah pemotongan ganda.

## 5. Hak akses dan keamanan

- Assignment tingkat perusahaan terpisah dari `user_property_roles` existing. Tidak otomatis memberi akses holding kepada Admin/Manager/Finance/Operational suatu properti.
- Peran usulan: `Company Viewer`, `Company Finance`, `Company Approver`; capability granular `view`, `export`, `post`, `approve`, `reconcile`, `close`, `manage_access`.
- Pemberian akses awal kepada akun tertentu harus eksplisit. Finance HomiQ tetap tidak boleh mengakses laporan Homestay/Kos lewat URL, export, polling, atau bukti.
- Konteks perusahaan menentukan daftar properti dan rekening yang sah di server; parameter GET tidak menentukan kewenangan. Grant dicabut berlaku pada request berikutnya.
- Bukti yang dibuka dari holding memakai endpoint berizin perusahaan + relasi sumber/properti, tanpa memperluas endpoint bukti properti lama.
- Mutasi memakai CSRF, validasi, audit pengguna/waktu/alasan, serta pemisahan pembuat/pemeriksa untuk transaksi manual berisiko. Period lock diperiksa dalam transaksi database saat posting.
- Data keuangan posted tidak dihapus atau ditimpa: reversal dan posting pengganti, dengan audit trail. Unggah bukti tidak membuat transaksi uang baru.

## 6. Rencana database additive

Daftar berikut adalah rancangan lintas fase. Tabel fondasi CF-1 sudah dibuat lokal sebagaimana bagian 12; tabel settlement, overhead, agreement, obligation, dan reconciliation masih rencana. Jangan mengimpor ulang database live.

| Tabel baru usulan | Fungsi |
| --- | --- |
| `companies`, `company_properties` | Identitas holding dan keanggotaan properti; tidak memakai properti fiktif ID 0 |
| `company_user_roles` | Grant perusahaan dan capability, terpisah dari role properti |
| `cash_accounts`, `cash_opening_balances` | Kas/rekening yang dikelola, cakupan, mata uang, tanggal cutover dan saldo terverifikasi |
| `cash_transactions`, `cash_entries` | Header transaksi dan kaki mutasi rekening; referensi sumber/revisi, klasifikasi, posted/reversed, tanggal efektif, waktu pencatatan dan audit |
| `expense_settlements` | Pembayaran parsial expense existing; relasi ke mutasi, nominal, tanggal bayar |
| `company_expenses`, `company_expense_allocations` | Overhead pusat dan distribusi opsional, tanpa mengganti FK `expenses.property_id` existing |
| `property_finance_agreements` | Aturan hak perusahaan/owner, dasar perhitungan dan tanggal berlaku; tidak mengubah setup lama secara retroaktif |
| `financial_obligations`, `obligation_settlements` | Kewajiban owner/refund/deposit dan pelunasannya; nilai estimasi/final dibedakan |
| `cash_reconciliations`, `cash_reconciliation_items`, `company_finance_periods` | Rekonsiliasi, saldo statement, exception dan tutup/buka periode |

Pertahankan payments, expenses, invoice, properti, room, dan reservasi lama. Relasi sumber dibuat pada tabel tambahan; perusahaan dan properti harus konsisten pada foreign key/service. Posting pembayaran sumber dan ledger harus atomik; bila asynchronous diperlukan, tambahkan transactional outbox dengan retry idempotent.

Gunakan `DECIMAL` dan aritmetika desimal, bukan float untuk ledger. Unique source-event/revision mencegah pembayaran/expense dicatat ulang. Index berdasarkan company, account, tanggal efektif, status dan referensi sumber. Partial settlement tidak melebihi kewajiban dalam pemeriksaan terkunci/transaksi. Historical correction tidak mengganti kontrak/rate transaksi lama.

Jika laba rugi akrual formal diminta, fase terpisah menambah chart of accounts, journal headers/lines berimbang, pengakuan pendapatan, beban/utang, aset/penyusutan dan kewajiban. Ledger kas saja tidak menghasilkan neraca atau laba rugi akrual yang lengkap.

## 7. Phase implementasi dan acceptance gate

### CF-0 — Kebijakan, mapping, dan baseline

- Audit schema/source terbaru, status pembayaran/refund/expense dan perubahan owner setelah fase multi-property.
- Sepakati kepemilikan/kontrak unit, hak perusahaan, rekening yang dikelola, tanggal cutover, basis laporan, role perusahaan, timezone dan periode tutup.
- Tentukan mapping akun/kategori dan sumber data setiap kartu. Ambil baseline DB dan backup; uji hanya pada clone.
- Gate: mapping disetujui; item belum diketahui dicatat sebagai unresolved, tidak diberi persentase/rekening fiktif.

### CF-1 — Fondasi holding, izin dan ledger kas

Status: selesai lokal, hasil dan batas scope pada bagian 12; aktivasi/grant pertama masih menunggu keputusan pengguna.

- Migrasi additive untuk scope perusahaan, grants, rekening, opening balance, cash transactions/entries.
- Service terpisah `CompanyFinanceContext`, `CompanyFinanceAccess`, `CashLedgerService`; jangan memperluas `PropertyContext::activeId()` menjadi semua properti.
- Endpoint holding deny-by-default, audit, immutable posting/reversal, pengamanan periode dan idempotency.
- Gate: Finance properti tidak dapat membaca holding; transfer dua rekening menjaga total saldo; saldo awal tidak masuk revenue.

### CF-2 — Integrasi penerimaan, expense dan overhead

Status: selesai lokal 1 Oktober 2026; enam tabel kosong diterapkan, 29 uji backend dan 16 uji HTTP lulus. Detail manifest/batas aktivasi di `COMPANY_FINANCE_CF2_RELEASE.md`. Production belum diubah.

- Integrasi penerimaan booking/OTA, pembayaran expense parsial, refund aktual, transfer internal, modal/pinjaman dan overhead.
- Form pemilihan rekening/tanggal penerimaan atau pembayaran; pisahkan status approval expense dan settlement.
- Mapping sejarah melalui preview/import terkontrol, tidak mengubah pembayaran existing. Historical unmapped tampil sebagai exception, tidak dianggap saldo bank terverifikasi.
- Gate: source disimpan ulang tidak menggandakan kas; pembatalan tidak menghapus penerimaan; expense belum dibayar tidak mengurangi saldo.

Implementasi memakai konfirmasi treasury eksplisit dari halaman holding, bukan otomatis menganggap status Paid/Approved sebagai saldo bank. Pilih rekening, nominal aktual, timestamp UTC dan referensi; posting serta link sumber dalam satu transaksi atomik. Riwayat sebelum cutover dapat dipetakan ke saldo awal tanpa posting ulang. Preview maksimal 100 sumber per kelompok, menandai belum/parsial dipetakan dan perubahan nominal sumber. Overhead holding memiliki approval terpisah maker/checker; laporan hak ekonomi/owner dan kas bebas masih CF-3, dashboard realtime/ekspor holding CF-4.

### CF-3 — Hak perusahaan, kewajiban dan laporan bulanan

Status: selesai lokal 1 Oktober 2026; empat tabel kosong diterapkan (`property_finance_agreements`, `financial_obligations`, `obligation_settlements`, `company_expense_allocations`), 34 uji backend/fungsional lulus pada clone test. Detail rilis di `COMPANY_FINANCE_CF3_RELEASE.md`. Production belum diubah.

- Perjanjian effective-dated, owner settlement/refund/deposit, jembatan gross properti → hak perusahaan, kontribusi unit, overhead dan cadangan kas.
- Ringkasan bulanan basis kas dengan label jelas; tetap tampilkan penerimaan gross operasional terpisah dari income entitlement. Pengakuan belum lengkap diberi peringatan.
- Gate: Homestay sekali per rumah; fee perusahaan tidak ditambahkan dua kali; owner payout tidak menjadi expense kedua; alokasi overhead jumlahnya cocok ke sumber. (Semua gate lulus).

### CF-4 — UI, pembaruan terkini, ekspor

Status: selesai lokal 1 Oktober 2026; UI enterprise banking anti-AI-slop (5 tab, monospace tabular figures, 6 executive KPI cards, pemisahan As-Of vs Period), HTMX 30s live auto-refresh dengan pause saat hidden, ekspor SpreadsheetML XML (.xls) 5 worksheet dengan proteksi formula injection. 45/45 pemeriksaan uji backend dan UI lulus pada clone test. Detail rilis di `COMPANY_FINANCE_CF4_RELEASE.md`. Production belum diubah.

- Implementasi lima tab (`summary`, `monthly`, `cashflow`, `obligations`, `treasury`), filter perusahaan/properti/rekening, pagination server-side, Chart.js, layout responsif header dan tanggal.
- HTMX refresh sekitar 30 detik ketika halaman aktif, hentikan polling tab tersembunyi; refresh setelah posting sukses. Tidak memerlukan WebSocket/Docker atau migrasi stack.
- Real-time berarti **berdasarkan transaksi yang sudah tercatat di sistem**, bukan akses rekening bank otomatis. Tampilkan waktu transaksi terakhir, pembaruan terakhir, data stale/error dan rekonsiliasi terakhir.
- Ekspor summary/properti/mutasi/saldo/kewajiban termasuk catatan, cakupan, basis, periode, timestamp dan status rekonsiliasi. Pakai XML `.xls` existing terlebih dahulu; cegah formula injection dan jangan menyebutnya `.xlsx`.
- Gate: export dan UI memakai service/rule yang sama; periode lampau tidak berubah saat kartu terkini refresh; query/index diuji pada volume realistis. (Semua gate lulus, 45/45 uji otomatis lolos).

### CF-5 — Rekonsiliasi, close, UAT dan Rumahweb

Status: selesai lokal 1 Oktober 2026; dua tabel kosong diterapkan (`company_bank_reconciliations`, `company_finance_snapshots`), mathematical integrity gate aktif, pembekuan snapshot SHA-256 versi berjenjang, penolakan late posting, pembukaan kembali terkontrol khusus Approver dengan alasan audit, sub-tab rekonsiliasi dan badge periode di UI, ekspor SpreadsheetML XML diperkaya status rekonsiliasi. 45/45 pengujian CF-5, 45/45 regresi CF-4, dan 34/34 regresi CF-3 lulus 100% pada clone test. Checksum tabel bisnis existing identik. Detail rilis di `COMPANY_FINANCE_CF5_RELEASE.md`. Production Rumahweb belum disentuh.

- Rekonsiliasi bank/kas, selisih yang perlu tindakan, approval dan penguncian bulan. Reopen hanya oleh approver dengan alasan/audit.
- Close membekukan snapshot/report version, basis dan input terkait; late posting ke periode tertutup ditolak atau perlu reopen. Koreksi/reversal tidak mengubah angka laporan tertutup diam-diam.
- UAT pada clone terkini production, backup, manifest source final, ledger migrasi, deployment bertahap feature flag dan smoke test role.
- Gate: saldo penutup setiap rekening cocok saldo pembuka + mutasi; cashflow konsisten ke mutasi; seluruh kasus keamanan dan migrasi lulus sebelum aktif live. (Semua gate lulus, 45/45 uji otomatis lolos).

### CF-6 — Pengembangan lanjutan (opsional)

- Jurnal double-entry/akrual, neraca, aset/penyusutan, budget vs actual, forecast likuiditas 30/60/90 hari dan skenario pembayaran owner.
- Import rekening koran CSV dengan matching/review; integrasi bank hanya setelah akses/provider/biaya disepakati.
- Setelah fondasi angka stabil, alert cash runway/minimum balance dan forecast piutang jatuh tempo. Forecast bukan saldo aktual atau jaminan penerimaan.
- Estimasi durasi/biaya ditetapkan setelah CF-0; jangan menjanjikan laporan akrual lengkap sebagai bagian ringkasan kas MVP.

## 8. Skenario uji wajib

1. Role Finance hanya HomiQ mencoba company page, polling, export, ID rekening, dan bukti Mitra: ditolak.
2. Holding user eksplisit dapat membaca unit yang diizinkan tanpa mengubah sesi properti aktif; pencabutan grant berlaku segera.
3. Homestay tiga baris kamar tetap satu penerimaan; Kos beberapa bulan memisahkan tanggal kas dan periode layanan.
4. DP → pembatalan → refund pending → refund paid: kas bertambah sekali, lalu berkurang sekali saat dikembalikan.
5. Expense Rp1 juta dibayar dua kali Rp400 ribu + Rp600 ribu: dua mutasi kas, nol sisa; percobaan overpay/race ditolak.
6. Transfer Rp1 juta rekening A→B: total kas tetap, cashflow eksternal nol; fee Rp6.500 mengurangi total sekali.
7. OTA gross Rp1 juta, komisi Rp150 ribu, penerimaan bank Rp850 ribu: bank bertambah Rp850 ribu saja; gross/net terjelaskan.
8. Setoran modal/pinjaman tidak masuk income; owner payout tidak menggandakan biaya; overhead satu sumber tidak berlipat karena alokasi.
9. Saldo cutover tidak ditambah transaksi sebelum cutover; transaksi future-dated tidak menambah saldo hari ini; timezone/batas akhir bulan konsisten.
10. Dua request posting sama, kegagalan DB di tengah posting, reversal dan close bersamaan: tidak ada ledger parsial/duplikat atau perubahan periode terkunci.
11. Rekonsiliasi menunjukkan selisih; bukti upload saja tidak menandai reconciled. Kewajiban unresolved menahan klaim kas bebas terverifikasi.
12. UI/ekspor angka dan catatan cocok; input formula berbahaya aman; semua property filter dan link bukti mengikuti scope.
13. Migrasi pada clone DB berisi data: row count dan nominal lama tetap; report existing tetap berfungsi; rollback aplikasi tidak menghapus ledger baru.

## 9. Deployment dan catatan database

- Catat setiap migrasi nyata CF-1 dst. pada `DATABASE_MIGRATION_LEDGER.md`: tabel/kolom/index/FK, backfill, hasil clone, status lokal/live dan manifest file final. Daftar rencana di atas bukan daftar migrasi yang sudah diterapkan.
- Feature holding dinonaktifkan sampai akun, opening balance, grants dan mapping tervalidasi. Readiness multi-property Fase 6 tetap diperlukan; rencana ini tidak menganggapnya sudah lulus.
- Rumahweb: backup DB/file, upload versi source terbaru sesuai struktur aplikasi aktif, jalankan migrasi melalui metode hosting yang tersedia, validasi, lalu beri grant secara eksplisit. Jangan menebak folder aktif dari namanya.
- Jangan upload `.env` lokal, password, backup SQL, test fixtures, atau menimpa DB/writable/uploads production. Tidak melakukan destructive rollback migration untuk undo release; utamakan rollback source/feature flag.

## 10. Keputusan sebelum coding

1. Apakah HomiQ/Homestay/Kos milik badan usaha yang sama atau dikelola untuk owner berbeda? Apa hak pengelola tiap unit dan tanggal berlakunya?
2. Rekening perusahaan mana yang dipakai, adakah rekening unit/owner di luar kendali holding, dan berapa saldo awal terverifikasi pada tanggal cutover?
3. Siapa yang boleh melihat seluruh holding, melakukan posting, memeriksa, dan tutup/buka bulan?
4. Apakah MVP ringkasan basis kas cukup, atau laba rugi akrual harus menjadi scope awal? Untuk kebutuhan kas terkini, rekomendasi mulai ledger kas + kewajiban dulu.
5. Bagaimana kebijakan deposit/refund/owner payout/biaya bersama dan minimum cadangan kas? Jangan menerapkan 70/30 HomiQ ke Mitra otomatis.

## 11. Referensi prinsip

Referensi ini untuk rancangan kontrol, bukan klaim kepatuhan laporan terhadap standar tertentu. Kebijakan final mengikuti kontrak, struktur badan usaha, standar yang relevan, serta review akuntan perusahaan.

- [IFRS Foundation — IAS 7 Statement of Cash Flows](https://www.ifrs.org/issued-standards/list-of-standards/ias-7-statement-of-cash-flows.html/): membedakan arus operasi, investasi, pendanaan dan transaksi nonkas.
- [IFRS Foundation — IFRS 15, principal/agent B34–B38](https://www.ifrs.org/content/dam/ifrs/publications/pdf-standards/english/2022/issued/part-a/ifrs-15-revenue-from-contracts-with-customers.pdf?bypass=on): gross vs fee/commission tergantung peran dan pengendalian layanan. Penerapannya pada kontrak PMS harus ditentukan, bukan diasumsikan.

Referensi ditinjau 1 Oktober 2026. Pengguna mengotorisasi implementasi CF-1; deployment production belum diotorisasi.

## 12. Hasil implementasi CF-1 lokal — 1 Oktober 2026

- Migrasi `2026-10-01000000_AddCompanyCashFoundation.php`: sembilan tabel fondasi kosong, tanpa perubahan kolom/data bisnis lama. Detail di `DATABASE_MIGRATION_LEDGER.md`.
- Izin Company Viewer/Finance/Approver terpisah; endpoint dan menu tidak memakai role properti sebagai grant holding. Tidak menyimpan pilihan holding dalam sesi properti.
- Ledger menggunakan cents integer 64-bit dan `DECIMAL(14,2)`, idempotent event key, company-row lock, transaksi atomik, opening balance immutable, reversal append-only dan period lock dengan audit. Semua timestamp ledger UTC.
- CF-1 belum mengekspos endpoint web untuk menulis rekening/mutasi atau close. Service fondasi diuji; integrasi pembayaran/expense dan UI operasional dimulai pada CF-2/CF-4. Period lock fondasi bukan pengganti prosedur tutup buku/snapshot CF-5.
- Service CF-1 memiliki batas transaksi sendiri dan menolak transaksi bersarang. CF-2 wajib menambahkan unit-of-work integrasi yang menjaga perubahan sumber + ledger atomik, atau transactional outbox; jangan sekadar memanggil posting setelah commit pembayaran.
- 38 pemeriksaan CLI lulus pada clone, termasuk dua proses concurrent dengan event key sama dan injected failure kaki kedua. Sepuluh pemeriksaan HTTP lulus: login, Finance properti ditolak, grant holding eksplisit diterima meski role properti Staff, query company palsu/array ditolak, no-store, dan navigasi properti tetap berfungsi.
- Local utama: 5 users, 39 rooms, 21 reservations, 15 payments; total reservation Rp18.550.000, payments Rp6.550.000, expenses Rp729.000 sama dengan baseline clone. Semua tabel holding utama kosong; belum ada grant atau rekening/saldo baru.
- Nama perusahaan, penerima grant pertama, kontrak dan rekening asli masih menunggu keputusan pengguna. Bootstrap hanya boleh dijalankan setelah pilihan eksplisit, bukan memakai nama/akun contoh.
- Cara bootstrap operator: `php spark company:bootstrap --code KODE_DISETUJUI --name "Nama holding disetujui" --user USERNAME_DISETUJUI --properties 1,2,3 --confirm KODE_DISETUJUI`. Command hanya membuat holding baru; tidak mengubah grant lama, perjanjian, rekening atau saldo. Pengelolaan grant berikutnya belum mempunyai UI.
- Uji clone memakai **`DB_NAME`**, bukan `database.default.database`, karena constructor konfigurasi aplikasi memakai `DB_NAME`. Jangan mengubah `.env` production untuk pengujian.
