# CF-4 — UI, Pembaruan Terkini, dan Ekspor SpreadsheetML

Status 1 Oktober 2026: diterapkan **lokal**; production Rumahweb belum disentuh.

## Perilaku yang tersedia

- **Aset & Estetika Enterprise (Anti-AI-Slop)**:
  - Antarmuka dirancang dengan standar portal perbankan/keuangan enterprise (Stripe/Linear/NetSuite style) dengan kepadatan informasi tinggi, border slate halus (`#e2e8f0`), tipografi monospaced tabular figures (`.holding-num`) untuk seluruh nominal mata uang agar sejajar sempurna dalam tabel komparasi.
  - Memisahkan secara tegas dan transparan antara **Saldo Kas & Bank As-Of Terkini** (posisi kas riil di rekening bank saat ini) dengan **Kinerja Periode Akuntansi Terpilih** (laba/rugi, penerimaan, dan beban bulanan).
  - Enam kartu KPI eksekutif responsif: Saldo Kas/Bank As-Of, Kas Masuk Periode Ini (dengan badge MoM delta & persentase), Kas Keluar Periode Ini (dengan badge MoM), Arus Kas Bersih (dengan badge MoM), Hak Bersih Holding, dan Kewajiban Belum Lunas (Outstanding).
  - Peringatan integritas kas otomatis yang mendeteksi transaksi penerimaan reservasi di modul PMS yang belum dikonfirmasi ke kas/bank holding.

- **Struktur 5 Tab Inti Manajemen Holding**:
  1. **Tab 1: `summary` (Ringkasan & Tren)**:
     - Grafik tren arus kas 6 bulan terakhir bertenaga Chart.js (Kas Masuk, Kas Keluar, Arus Kas Bersih, dan Hak Bersih Holding) dengan palet muted corporate (Emerald, Rose, Blue, Slate) dan tooltip monospaced IDR.
     - Tabel komparasi Month-over-Month (MoM) yang membandingkan performa bulan berjalan terhadap bulan sebelumnya.
     - Ringkasan kontribusi unit properti operasional bulan berjalan.
     - Panel status integritas data: timestamp transaksi ledger terakhir, log audit terakhir, dan status penguncian periode akuntansi.
  2. **Tab 2: `monthly` (Laporan Bulanan & Hak Holding)**:
     - Jembatan Rekonsiliasi Hak Holding (Gross Operasional → Beban Operasional Unit → Hak Pengelola → Beban Overhead Holding → Hak Bersih Holding).
     - Rincian kinerja dan hak per unit properti.
  3. **Tab 3: `cashflow` (Arus Kas, Rekening & Mutasi Ledger)**:
     - Rekapitulasi saldo rekening kas/bank terverifikasi per tanggal cutover.
     - Laporan Arus Kas Eksternal standar IAS 7 (Aktivitas Operasi, Investasi, dan Pendanaan; mengecualikan mutasi transfer internal).
     - Buku mutasi ledger kas riil dengan **server-side pagination** dan filter multi-kriteria: Rekening, Jenis Transaksi (`receipt`, `disbursement`, `transfer`, `opening`, `reversal`), Klasifikasi IAS 7 (`operating`, `investing`, `financing`, `internal`), dan pencarian kata kunci (deskripsi/referensi/event key).
  4. **Tab 4: `obligations` (Kewajiban & Tagihan)**:
     - Manajemen kewajiban keuangan holding (bagi hasil owner, deposit/refund tamu, tagihan beban).
     - Alur status berjenjang: `estimated` → `finalized` → `settled` / `cancelled`.
     - Pelunasan kas atomik tanpa duplikasi beban di tabel `expenses`.
  5. **Tab 5: `treasury` (Rekonsiliasi & Operasi Kas)**:
     - Sub-panel terorganisir: Konfirmasi Kas & Integrasi PMS (link pembayaran dan expense), Tutup Buku & Penguncian Periode, Perjanjian Pengelolaan Unit, dan Alokasi Biaya Overhead.
     - Penguncian periode (Tutup Buku) oleh Company Approver untuk membekukan periode lampau dan menolak posting tanggal mundur (back-dated).

- **Pembaruan Terkini Real-Time (HTMX Live Polling 30s)**:
  - Menggunakan polling berkala ringan HTMX setiap 30 detik: `hx-trigger="every 30s [!document.hidden], refresh from:body"`.
  - **Efisiensi & Anti-Stale:** Polling otomatis berhenti saat tab browser berada di latar belakang (hidden) dan kembali aktif saat tab dibuka.
  - Indikator status live: badge visual dengan pulsing green dot (`.live-pulse`), penunjuk waktu pembaruan (`Diperbarui HH:mm:ss WIB`), dan tombol manual `Refresh` yang memicu pembaruan instan tanpa reload browser.
  - Chart.js chart instance otomatis di-destroy dan di-render ulang secara bersih saat HTMX memicu `htmx:afterSettle`.

- **Ekspor Laporan SpreadsheetML XML (`.xls`) Multi-Worksheet**:
  - Endpoint terproteksi filter otorisasi: `GET /finance/company/export` dengan izin `companyFinance:export`.
  - Menghasilkan file SpreadsheetML XML dengan ekstensi `.xls` murni (`holding-finance-{company_id}-{month}.xls`) dengan `Content-Type: application/vnd.ms-excel; charset=UTF-8`.
  - Berisi **5 Worksheet Komprehensif**:
    1. `Ringkasan & Jembatan`: Metadata holding, periode, cutoff as-of, basis akuntansi kas riil, kartu indikator, dan langkah jembatan rekonsiliasi hak holding.
    2. `Kontribusi Properti`: Rekap penerimaan gross/net, beban unit, surplus, hak owner, hak holding, dan kontribusi bersih per unit.
    3. `Arus Kas & Rekening`: Posisi saldo kas/bank terverifikasi per rekening dan laporan arus kas eksternal format IAS 7.
    4. `Mutasi Kas`: Daftar mutasi kas ledger lengkap bulan terpilih beserta rincian entri debit/kredit per rekening.
    5. `Kewajiban Keuangan`: Daftar tagihan owner, refund, dan deposit berstatus terbuka maupun lunas.
  - **Gate Lulus:** Seluruh angka ekspor dihitung menggunakan service kalkulasi yang identik dengan UI (tidak ada divergensi angka).
  - **Perlindungan Injeksi Formula Excel:** Seluruh sel string yang diawali karakter formula berbahaya (`=`, `+`, `-`, `@`) otomatis dinetralisir dengan prepend single quote (`'`), mencegah eksploitasi CSV/Excel formula injection saat dibuka di Microsoft Excel.

## Tambahan Database & Skema

- **Tidak ada migrasi atau DDL baru pada CF-4.**
- Seluruh fitur CF-4 memanfaatkan skema database yang telah diterapkan pada CF-1 (`2026-10-01000000`), CF-2 (`2026-10-01010000`), dan CF-3 (`2026-10-01020000`).
- Indeks komposit pada `cash_transactions` (`company_id, effective_at`) dan `cash_entries` (`company_id, account_id`) memastikan query mutasi, paginasi, dan tren berjalan cepat.

## Manifest Source CF-4 Terbaru

Unggah versi terbaru file-file berikut ke root aplikasi aktif (gabungkan dengan manifest CF-1 s/d CF-3):

- `app/Config/Routes.php` (penambahan route `finance/company/export`)
- `app/Controllers/CompanyFinanceController.php` (controller 5 tab, paginasi transaksi, dispatch tutup periode, dan generator SpreadsheetML)
- `app/Libraries/CashLedgerService.php` (metode `transactions` paginasi server-side dan entry aggregation)
- `app/Libraries/CompanyEntitlementService.php` (metode `trendSixMonths` dan `auditSummary`)
- `app/Views/pages/company_finance_foundation.php` (halaman utama anti-AI-slop, 6 KPI cards, 5 tabs, container HTMX auto-refresh)
- `app/Views/components/company_tab_summary.php` (komponen Tab 1: Chart.js 6 bulan, tabel MoM, snapshot properti, status audit)
- `app/Views/components/company_tab_cashflow.php` (komponen Tab 3: rekening kas/bank, IAS 7 flow, tabel mutasi paginated)
- `app/Views/components/company_tab_treasury.php` (komponen Tab 5: operasi kas, penguncian periode tutup buku, agreements, overhead)
- `public/css/app.css` (kelas monospaced `.holding-num`, animasi `.live-pulse`, `.holding-kpi-card`)
- `public/js/app.js` (inisialisasi `companyTrendChart` dengan listener `DOMContentLoaded` dan `htmx:afterSettle`)
- `app/Commands/TestCompanyCashCF4.php` (perintah uji komprehensif `company:cf4-test`)

## Hasil Pengujian Clone Lokal (1 Oktober 2026)

Pengujian dijalankan pada database clone terisolasi `homiq_pms_cf4_test_20261001` via `DB_NAME=homiq_pms_cf4_test_20261001 php spark company:cf4-test`:

- **45 Pemeriksaan CF-4 Selesai & Lulus 100%**:
  - CF4-01 s/d CF4-07: Setup rekening, multi-month transactions, trend 6 bulan, dataset kas masuk/keluar, formula MoM delta (+33.3%), audit summary timestamp.
  - CF4-08 s/d CF4-14: Server-side pagination ledger (total count, perPage=2 limit, totalPages), filter bulan (`2026-10`), filter jenis (`receipt`), filter klasifikasi (`operating`).
  - CF4-15 s/d CF4-24: Ekspor HTTP 200, Content-Type `application/vnd.ms-excel`, XML declaration, progid `Excel.Sheet`, validasi keberadaan seluruh 5 Worksheets.
  - CF4-25 s/d CF4-28: **Gate Konsistensi:** Nilai kas masuk, kas keluar, net cash, dan hak holding antara UI dan XML ekspor terbukti 100% identik.
  - CF4-29 s/d CF4-31: **Gate Keamanan Formula Injection:** Nilai diawali `=`, `+`, `@` terbukti dinetralisir dengan prepend single-quote.
  - CF4-32 s/d CF4-35: **Gate Immutability:** Penutupan periode September berhasil, penolakan posting mundur (back-dated), dan angka laporan periode tertutup tidak berubah.
  - CF4-36 s/d CF4-45: Rendering view HTML untuk seluruh 5 tab (`summary`, `monthly`, `cashflow`, `obligations`, `treasury`) berhasil dengan container HTMX dan trigger auto-refresh 30s.

- **Uji Regresi CF-3:** 34 pemeriksaan lulus 100% pada clone `homiq_pms_cf3_test_20261001`.
- **Integritas Database Utama:** Checksum tabel bisnis utama `homiq_pms` terverifikasi tidak berubah:
  `users=1521949837, rooms=941710999, guests=3378459516, reservations=3504074905, payments=3357593375, expenses=3807269205, invoice_items=1914596919`.
