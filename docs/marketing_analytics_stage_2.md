# Analytics Marketing — Tahap 2

Tahap 2 menambahkan perbandingan performa setiap guesthouse ke halaman Marketing dan menghapus permanen fitur Campaign serta Program Loyalty.

## Performa guesthouse

Tabel dan grafik menampilkan `Revenue`, `Cost`, `Profit`, dan `Margin` untuk setiap GH dalam filter.

| Metrik | Basis waktu | Aturan |
|---|---|---|
| Revenue | `payments.payment_date` | Hanya pembayaran berstatus `Paid` yang diterima. |
| Cost | `expenses.date_incurred` | Expense `Rejected` dikeluarkan. |
| Profit | periode dan GH yang sama | `Revenue - Cost`. |
| Margin | periode dan GH yang sama | `Profit / Revenue × 100%`; `-` bila revenue nol. |

Aturan alokasi expense:

- Satu GH: nominal penuh ke GH tersebut.
- Multi-GH: nominal dibagi rata ke semua GH pada `expense_guesthouses`.
- Tanpa GH: tidak dialokasikan dan ditampilkan terpisah sebagai expense belum dialokasikan.

Karena setiap expense dialokasikan hanya sekali secara total, penjumlahan cost lintas GH tidak menggandakan nominal.

## Penghapusan Campaign dan Loyalty

Endpoint, controller action, view, dan tab kedua fitur tersebut sudah dihapus; URL lama seperti `/marketing?tab=loyalty` kini kembali ke dashboard Marketing. Migration `2026-08-12030000_RemoveMarketingCampaignAndLoyalty` telah menghapus tabel `marketing_campaigns` dan `loyalty_members` beserta seluruh datanya pada 12 Agustus 2026.

## QA

```bash
php spark marketing:analytics-audit --year 2026 --month 8
php spark marketing:analytics-audit --year 2026 --month 8 --guesthouses 1,2
```

Audit memvalidasi profit setiap GH terhadap rumus revenue dikurangi cost. Saat semua GH dipilih, audit juga memeriksa bahwa seluruh nominal expense sama dengan cost yang dialokasikan ditambah expense yang belum dialokasikan.
