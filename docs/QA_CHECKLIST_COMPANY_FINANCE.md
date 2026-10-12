# Checklist QA — Keuangan Perusahaan / Holding

Tanggal: 2 Oktober 2026. Scope: submenu Keuangan Perusahaan, seluruh tab, endpoint, ekspor dan integrasi sumber transaksi.

Dokumen ini adalah rencana pengujian, **bukan bukti seluruh pengujian sudah lulus**. Dasar: screenshot pengguna dan inspeksi source read-only. Pengujian mutasi wajib dilakukan di clone database lokal/production yang terisolasi; jangan memakai transaksi uji di production.

## 1. Prioritas dan status

- **P0 / kritis:** saldo salah, transaksi ganda/parsial, akses tanpa izin, perubahan bulan tertutup. Menahan deployment.
- **P1 / tinggi:** klasifikasi/periode/laporan/ekspor salah, rekonsiliasi atau kewajiban tidak konsisten. Harus selesai sebelum penerimaan modul.
- **P2 / sedang:** tampilan, kemudahan penggunaan dan performa tanpa dampak integritas angka.
- Status setiap kasus: Belum diuji → Lulus / Gagal / Terblokir. Jangan menganggap test fase sebelumnya otomatis mencakup source terbaru.

## 2. Temuan awal yang perlu diverifikasi

| ID | Prioritas | Observasi dan risiko | Verifikasi / hasil yang diharapkan |
| --- | --- | --- | --- |
| OBS-01 | P0 | `CompanyFinanceController::index()` memanggil `ensureConsolidatedAccount()`. Fungsi dapat membuat rekening/saldo awal otomatis dan tidak memeriksa izin posting/approval di dalamnya. | GET/refresh oleh Viewer tidak membuat rekening, saldo awal atau audit mutasi. Pembuatan harus eksplisit, berizin, atomik dan dapat diaudit. |
| OBS-02 | P0 | Screenshot menunjukkan saldo Rp6.550.000, sedangkan ledger Oktober menampilkan 0 transaksi. Ini **belum tentu bug**, bisa berasal dari saldo awal/bulan sebelumnya. | Rekonstruksi asal saldo per rekening; jelaskan saldo awal dan mutasi sebelum Oktober. Cocokkan dengan bukti, bukan hanya total pembayaran PMS. |
| OBS-03 | P1 | Label “Kas Terverifikasi Sistem” bisa disalahartikan sebagai sudah cocok rekening koran. | Bedakan kas tercatat ledger, kas terekonsiliasi bank, dan kas bebas setelah kewajiban. |
| OBS-04 | P1 | Waktu “Live / Diperbarui” pada screenshot berbeda dari jam header. Source view menampilkan waktu render; ini belum membuktikan polling data berhasil. | Label mencerminkan refresh data sukses, menunjukkan stale/error bila gagal. Clock berjalan bukan bukti data realtime. |
| OBS-05 | P0 | Controller menghitung laporan berjalan sambil mengambil snapshot. Ini belum membuktikan laporan tertutup memakai angka beku. | Tampilan dan ekspor bulan tertutup memakai snapshot konsisten; perubahan sumber/kontrak berikutnya tidak mengubah angka beku. |

## 3. Matriks pengujian wajib

Seluruh kasus berikut berstatus **Belum diuji dalam audit ini**.

| ID | Prioritas | Area / skenario | Kriteria lulus |
| --- | --- | --- | --- |
| QA-01 | P0 | Finance properti, Company Viewer, Finance holding, Approver; akses URL/POST/ekspor/bukti langsung | Role properti tidak otomatis memperoleh holding. Semua endpoint memeriksa scope dan izin backend. Viewer tidak dapat melakukan mutasi. |
| QA-02 | P0 | Ganti company/property/account/source ID, termasuk ID valid dari scope lain; cabut grant saat sesi aktif | Tidak ada kebocoran data/mutasi lintas scope. Pencabutan berlaku pada request berikutnya. Pilihan holding tidak mengganti sesi properti diam-diam. |
| QA-03 | P0 | GET, refresh, ekspor atau polling pada holding tanpa rekening/saldo awal | Tidak menciptakan data keuangan atau menandai saldo terverifikasi otomatis. |
| QA-04 | P0 | Saldo awal + seluruh penerimaan − seluruh pengeluaran per rekening; cutover dan rekening nonaktif | Saldo dapat direkonstruksi tepat. Saldo awal tidak masuk cashflow bulan; rekening nonaktif tetap dapat ditelusuri dalam histori. |
| QA-05 | P0 | Booking grup, Homestay 3 kamar, Kos beberapa bulan, DP/pelunasan, retry sumber | Setiap pembayaran aktual masuk satu kali, tidak digandakan menurut jumlah kamar atau bulan layanan. |
| QA-06 | P0 | OTA gross Rp1.000.000, potongan Rp150.000, net Rp850.000; pencairan parsial | Bank bertambah Rp850.000. Potongan tidak menjadi arus keluar bank kedua. Net + potongan tidak melebihi sumber. |
| QA-07 | P0 | Expense Pending, Approved belum dibayar, pembayaran Rp400.000 + Rp600.000 untuk sumber Rp1.000.000 | Approval tidak mengurangi kas. Dua settlement total Rp1.000.000, sisa nol. Overpay dan edit/hapus sumber terhubung ditolak. |
| QA-08 | P0 | Cancel → Refund_Pending → refund aktual parsial/penuh; refund sebelum penerimaan | Pending tidak menghapus penerimaan. Refund keluar saat benar-benar dibayar, tidak melebihi net terkonfirmasi dan tidak mendahului penerimaan. |
| QA-09 | P0 | Transfer Rp1.000.000 A→B dan fee bank Rp6.500 | Transfer menghasilkan dua kaki seimbang, total kas tidak berubah, cashflow eksternal nol. Fee mengurangi total Rp6.500 sekali. |
| QA-10 | P1 | Modal, pencairan pinjaman, pembayaran pokok, bunga/fee, pembelian aset | Modal/pokok pinjaman bukan pendapatan/beban operasional. Klasifikasi sesuai kebijakan yang disetujui; tidak menebak perlakuan bunga/dividen/pajak. |
| QA-11 | P0 | Hak holding/owner: persentase, basis gross/net, tanggal berlaku, kontrak berubah, aturan Mitra | Hak owner bukan hak holding. Tidak menerapkan persentase HomiQ ke Mitra otomatis. Pelunasan owner tidak menggandakan biaya/hak yang sudah dihitung. |
| QA-12 | P1 | Overhead Pending/Approved, maker/checker, pembayaran parsial, alokasi ke unit | Pembuat tidak menyetujui sendiri. Sumber overhead dihitung sekali; jumlah alokasi cocok dan tidak menggandakan konsolidasi. |
| QA-13 | P0 | Kewajiban owner/refund/deposit, settlement parsial, koreksi dan cadangan | Sisa tepat; tidak mencadangkan dana sama dua kali. Saldo kas, hak holding, dan kas bebas diberi basis/label berbeda. |
| QA-14 | P1 | Riwayat sebelum cutover, mapping saldo awal, sumber belum/parsial dipetakan, nominal sumber berubah | Tidak menambah saldo awal dua kali. Exception terlihat; sumber belum terpetakan tidak dianggap kas terverifikasi penuh. |
| QA-15 | P0 | Akhir bulan/tahun, UTC versus zona laporan WIB/WITA, bulan historis, future-dated | Kebijakan zona laporan eksplisit. Transaksi tepat periode; saldo as-of sekarang dibedakan dari saldo penutup bulan pilihan. |
| QA-16 | P1 | Rekening koran cocok/selisih/belum tersedia; posting/reversal setelah rekonsiliasi | Selisih dan tanggal verifikasi terlihat. Mutasi susulan tidak tetap dianggap matched tanpa validasi ulang. Bukti upload bukan rekonsiliasi otomatis. |
| QA-17 | P0 | Close, posting backdate, koreksi sumber/kontrak, reopen, snapshot/ekspor | Posting periode tertutup ditolak. Angka beku tetap sama. Reopen berizin, beralasan, diaudit dan menghasilkan versi snapshot yang dapat ditelusuri. |
| QA-18 | P0 | Klik dua kali, retry timeout, dua operator membayar sumber sama, payload berbeda dengan event key sama | Idempotent retry; tidak ada duplikasi/overpay. Payload berbeda ditolak. |
| QA-19 | P0 | Gagal DB di tengah posting; close bersamaan posting; approval/grant berubah saat transaksi | Ledger dan link sumber commit/rollback bersama. Tidak ada transaksi parsial atau lolos dari period/permission lock. |
| QA-20 | P0 | Reversal penerimaan, settlement, transfer, serta penerimaan yang sudah direfund | Append-only dan berizin. Saldo, batas sumber, refund dan kewajiban tetap konsisten; reversal tidak membuat kas/kewajiban fiktif. |
| QA-21 | P1 | Kartu, tren, laporan bulanan, kontribusi unit, cashflow eksternal dan ledger | Rumus/basis jelas dan dapat direkonsiliasi; transfer internal tidak tercampur cashflow eksternal. Persentase MoM aman saat pembanding nol/negatif. |
| QA-22 | P1 | Ekspor seluruh tab dengan company/bulan/properti/filter/pagination dan periode tertutup | Angka/catatan cocok layar dan scope. Ekspor tidak diam-diam hanya memuat satu halaman. Teks berawalan =,+,-,@ tidak menjadi formula berbahaya. |
| QA-23 | P0 | Audit tindakan, akses bukti via URL, MIME/ukuran/path file, referensi sumber | Pelaku/waktu/alasan/sumber dapat ditelusuri. Tidak ada akses file lintas scope atau upload berbahaya. |
| QA-24 | P2 | Mobile/desktop, tanggal panjang, nominal negatif/besar, tabel kosong, pagination, filter antar tab | Tidak ada teks tertabrak atau kontrol penting tersembunyi. Filter bertahan; kosong dibedakan dari gagal memuat data. |
| QA-25 | P1 | Ribuan transaksi, refresh gagal/berulang, polling saat tab berpindah | Waktu respons disepakati dan diukur. Tidak ada request menumpuk, data stale berlabel Live, atau mutasi akibat refresh. |

## 4. Paket data uji minimum

- Dua holding/scope terisolasi untuk menguji ID valid lintas scope; tiga properti dengan mode booking HomiQ, Homestay dan Kos.
- Akun tanpa grant holding, Viewer, Finance holding dan Approver; dua pembuat berbeda untuk maker/checker.
- Dua rekening dengan saldo awal terverifikasi dan tanggal cutover eksplisit.
- Booking grup, transit, lintas bulan, DP/pelunasan, OTA net/fee dan pembatalan/refund.
- Expense Pending/Approved, pembayaran parsial, overhead dan alokasi.
- Periode terbuka/tertutup, rekening koran cocok/selisih, serta sumber historis belum terpetakan.
- Nilai kecil/desimal, nol/negatif yang harus ditolak, nominal besar, catatan berkarakter khusus dan input tidak valid/array.

Nominal di checklist adalah **contoh uji**, bukan transaksi perusahaan nyata. Jangan membuat grant, rekening, saldo, kontrak atau transaksi fiktif pada database utama.

## 5. Bukti dan pencatatan hasil

Untuk setiap QA-ID catat:

| QA-ID | Versi source / DB clone | Role dan scope | Input / langkah | Expected | Actual | Status | Bukti / defect ID | Retest |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| QA-xx | Diisi saat pengujian | Diisi | Diisi | Mengacu matriks | Diisi | Belum diuji | Screenshot, respons HTTP, query read-only / log tersanitasi | Diisi |

- Simpan baseline hitungan/checksum/nominal tabel bisnis sebelum migrasi dan bandingkan setelahnya.
- Catat event key/transaction ID serta saldo sebelum-sesudah untuk kasus kas. Verifikasi langsung pada DB clone, bukan hanya pesan sukses UI.
- Sertakan uji positif **dan** negatif, endpoint langsung, serta concurrent requests untuk P0 terkait transaksi.
- Jangan menyimpan password, token/session, bukti pribadi atau dump production dalam dokumen QA/repository.
- Setelah uji, hentikan server clone dan hapus fixture/clone sementara yang telah tervalidasi; pertahankan backup sesuai kebijakan.

## 6. Gate sebelum live

1. Tidak ada P0 atau P1 terbuka yang memengaruhi integritas angka, akses atau laporan.
2. GET/refresh/polling/ekspor tidak melakukan mutasi keuangan.
3. Saldo setiap rekening dapat direkonstruksi dan dicocokkan dengan bukti eksternal; exception historis diidentifikasi, bukan disembunyikan.
4. Uji scope, idempotency, concurrency, atomic rollback dan periode tertutup lulus pada source final.
5. Layar, ekspor dan snapshot konsisten pada periode/basis yang sama.
6. UAT dilakukan pada clone terbaru production dengan kebijakan kontrak, timezone, rekening dan cutover yang disetujui.
7. Backup, manifest file final, migrasi additive, prosedur deployment/pemulihan dan persetujuan penanggung jawab tersedia.

Urutan rekomendasi: OBS-01 → OBS-02 → OBS-05 → QA akses/transaksi P0 → rekonsiliasi dan hak/kewajiban → laporan/ekspor → UI/performa.

## 7. Referensi internal

- [Implementation plan](IMPLEMENTATION_PLAN_COMPANY_FINANCE.md)
- [Ledger migrasi](DATABASE_MIGRATION_LEDGER.md)
- [Release CF-2](COMPANY_FINANCE_CF2_RELEASE.md)
- [Release CF-3](COMPANY_FINANCE_CF3_RELEASE.md)
- [Release CF-4](COMPANY_FINANCE_CF4_RELEASE.md)
- [Release CF-5](COMPANY_FINANCE_CF5_RELEASE.md)

Checklist ini tidak mengklaim kepatuhan standar akuntansi. Label IAS 7 atau “terverifikasi” harus didukung klasifikasi, kebijakan dan bukti yang benar; review kebijakan akuntansi tetap dilakukan penanggung jawab finance/akuntan.
