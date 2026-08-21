# Analytics Marketing — Tahap 0

Tahap 0 menyediakan satu definisi filter, status, normalisasi, dan audit yang dipakai oleh seluruh metrik analytics berikutnya.

## Kontrak filter

- `year`: tahun 2000–2100; default tahun berjalan.
- `month`: angka 1–12; kosong atau `all` berarti seluruh tahun.
- `guesthouses`: array atau daftar dipisahkan koma berisi GH 1–5; kosong berarti semua GH.
- Rentang tanggal menggunakan awal inklusif dan akhir eksklusif: `tanggal >= start_date AND tanggal < end_date`.
- Query string yang dapat dibagikan menggunakan `year`, `month`, dan `guesthouses[]`.

Contoh URL:

```text
/marketing?year=2026&month=8&guesthouses[]=1&guesthouses[]=2
```

`MarketingAnalyticsQueryService` menerapkan kontrak tersebut menurut basis waktu metrik:

| Dataset | Basis tanggal | Scope GH |
|---|---|---|
| Reservasi/asalan tamu/loyalitas | `reservations.check_in_date` | `rooms.floor` |
| Okupansi | overlap `check_in_date`/`check_out_date` | `rooms.floor` |
| Revenue | `payments.payment_date`, hanya `Paid`; status reservasi tidak menghapus kas yang sudah diterima | GH kamar reservasi |
| Cost | `expenses.date_incurred`, selain `Rejected` | tabel relasi `expense_guesthouses`, dengan fallback kolom legacy |

Status reservasi yang valid untuk metrik reservasi dan okupansi adalah `Confirmed`, `Checked_In`, dan `Checked_Out`. `Cancelled` dikeluarkan dari metrik tersebut. Revenue tetap mengikuti pembayaran `Paid`; pembayaran yang benar-benar diterima tidak hilang hanya karena status reservasi kemudian berubah.

## Normalisasi asal tamu

`normalize_guest_origin()` menghapus spasi berlebih, menyamakan kapitalisasi, dan mengubah nilai kosong menjadi `Tidak diketahui`. `GuestDirectory` juga menyimpan input origin baru dalam bentuk yang sudah dinormalisasi. Data lama tidak diubah secara massal; normalisasi tetap diterapkan saat agregasi sehingga data sumber tetap dapat diaudit.

## Menjalankan audit

```bash
php spark marketing:analytics-audit --year 2026 --month 8 --guesthouses 1,2
php spark marketing:analytics-audit --year 2026 --month 8 --json
```

Audit melaporkan:

- jumlah baris reservasi, reservasi valid, booking unik, tamu utama unik, status, dan tanggal menginap tidak valid;
- kelengkapan serta distribusi origin setelah normalisasi, baik dalam scope metrik maupun seluruh record tamu;
- grup tamu terduplikasi pada seluruh directory tamu berdasarkan email dan nomor telepon yang dikanonisasi;
- rentang histori menginap, pembayaran, dan expense;
- pembayaran `Paid` serta total nominal menurut tanggal pembayaran;
- expense tanpa GH, satu GH, multi-GH, dan mismatch dengan kolom legacy;
- jumlah kamar per GH sebagai baseline kapasitas.

Schema kamar saat ini belum memiliki flag aktif/nonaktif. Karena itu baseline kapasitas memakai semua record kamar GH 1–5; status `Maintenance` baru akan mengurangi kapasitas setelah histori blokir kamar memadai, sesuai plan utama.

Gunakan keluaran JSON sebagai bukti rekonsiliasi query dashboard dengan data sumber.

## Baseline audit lokal — 12 Agustus 2026

Filter: Agustus 2026, semua GH.

- 14 baris reservasi valid, 9 booking unik, dan 9 tamu utama unik.
- Tidak ada rentang check-in/check-out yang tidak valid.
- 5 dari 9 tamu utama (55,56%) belum memiliki origin.
- Tidak ada grup duplikat email atau telepon dalam scope reservasi valid.
- Histori menginap 10–26 Agustus 2026; pembayaran 10–12 Agustus 2026; expense 2–12 Agustus 2026.
- 10 pembayaran `Paid` senilai Rp3.700.000, terkait 8 reservasi dan 6 booking unik.
- 6 expense dengan total Rp729.000: 5 terkait satu GH dan 1 terkait beberapa GH.
- Kapasitas sumber saat ini: GH 1 memiliki 5 kamar; GH 2–5 masing-masing 6 kamar.

Baseline ini adalah snapshot, bukan fixture tetap; jalankan ulang command setelah data berubah.
