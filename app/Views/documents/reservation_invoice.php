<?php
$money = static fn (float $amount): string => 'Rp ' . number_format($amount, 2, ',', '.');
$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$dateLabel = static function (?string $value) use ($months): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    try {
        $date = new DateTimeImmutable($value);
        return $date->format('j') . ' ' . $months[(int) $date->format('n')] . ' ' . $date->format('Y');
    } catch (Throwable) {
        return $value;
    }
};

$firstRoom = $groupReservations[0] ?? $reservation;
$roomNames = array_map(
    static fn (array $room): string => 'Room ' . $room['room_number'],
    $groupReservations,
);
$guesthouses = array_values(array_unique(array_filter(array_map(
    static fn (array $room): string => trim((string) ($room['guesthouse'] ?? '')),
    $groupReservations,
))));
$propertySuffix = $guesthouses === [] ? '' : ' - GH ' . implode(', ', $guesthouses);
$isTransit = (string) $reservation['check_in_date'] === (string) $reservation['check_out_date'];
$isCompact = (bool) ($isCompact ?? false);
$hasServiceCharges = $additionalRows !== [];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice <?= esc($invoiceNumber) ?></title>
    <style>
        @page { margin: 13mm 14mm 28mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #17233d;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.2px;
            line-height: 1.45;
        }
        .muted { color: #6c7789; }
        .blue { color: #1671e8; }
        .top-table, .property-table, .card-table, .summary-layout { width: 100%; border-collapse: collapse; }
        .top-table td, .property-table td, .card-table td, .summary-layout td { vertical-align: top; }
        .room-title { font-size: 13px; font-weight: 700; margin-bottom: 3px; }
        .invoice-title { color: #1671e8; font-size: 22px; font-weight: 800; letter-spacing: 1px; text-align: right; }
        .invoice-meta { font-size: 8px; text-align: right; }
        .divider { border-top: 4px solid #1671e8; margin: 8px 0 10px; }
        .property-name { font-size: 15px; font-weight: 800; padding-top: 4px; }
        .payment-box {
            width: 188px;
            border: 1px solid #f2aeb4;
            background: #fff6f7;
            border-radius: 5px;
            padding: 7px 10px;
            text-align: right;
        }
        .payment-box .outstanding { color: #dc3545; font-size: 13px; font-weight: 800; }
        .payment-box .paid { margin-top: 2px; color: #258753; font-size: 8px; }
        .card-table { margin-top: 11px; table-layout: fixed; }
        .card-table td { width: 50%; }
        .card-table td:first-child { padding-right: 5px; }
        .card-table td:last-child { padding-left: 5px; }
        .card {
            min-height: 106px;
            border-radius: 5px;
            background: #f3f5f8;
            padding: 10px 12px;
        }
        .card-title { font-size: 10px; font-weight: 800; margin-bottom: 8px; }
        .detail-table { width: 100%; border-collapse: collapse; }
        .detail-table td { padding: 1.5px 0; vertical-align: top; }
        .detail-table td:first-child { width: 43%; color: #6c7789; }
        .section-title { font-size: 10.5px; font-weight: 800; margin: 14px 0 5px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead { display: table-header-group; }
        .data-table th {
            border-bottom: 1px solid #aeb7c5;
            color: #5a6577;
            font-size: 8px;
            font-weight: 700;
            padding: 5px 4px;
            text-align: left;
        }
        .data-table td {
            border-bottom: 1px solid #e0e4ea;
            padding: 5px 4px;
        }
        .data-table .amount { text-align: right; white-space: nowrap; }
        .data-table tfoot td { border-top: 1px solid #aeb7c5; border-bottom: 0; font-weight: 800; }
        .summary-layout { margin-top: 9px; table-layout: fixed; }
        .summary-layout .notes { width: 58%; padding-right: 18px; }
        .summary-layout .totals { width: 42%; }
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 3px 0; }
        .totals-table td:last-child { text-align: right; white-space: nowrap; }
        .totals-table .grand td { border-top: 1px solid #9ca7b7; padding-top: 6px; font-size: 10px; font-weight: 800; }
        .totals-table .outstanding td { color: #dc3545; font-weight: 800; }
        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -20mm;
            height: 20mm;
            text-align: center;
        }
        .footer img { width: 84px; height: auto; }
        .footer .tagline { margin-top: -1px; color: #14aede; font-size: 5.7px; font-weight: 700; }
        .footer .website {
            display: inline-block;
            margin-top: 2px;
            border-radius: 7px;
            background: #42bee7;
            color: #fff;
            font-size: 5.5px;
            padding: 1px 12px;
        }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <table class="top-table">
        <tr>
            <td>
                <div class="room-title"><?= $isCompact ? 'Invoice Rombongan' : esc(implode(', ', $roomNames)) ?></div>
                <div class="muted"><?= count($groupReservations) ?> kamar</div>
                <div class="blue" style="margin-top: 5px;"><?= esc($invoiceNumber) ?></div>
            </td>
            <td>
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-meta muted">No. <?= esc($invoiceNumber) ?></div>
                <div class="invoice-meta muted">Tanggal <?= esc($dateLabel($invoiceDate)) ?></div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="property-table">
        <tr>
            <td>
                <div class="property-name">Adiputra Guesthouse by HomiQ<?= esc($propertySuffix) ?></div>
                <div class="muted">Tanda pemesanan dan rincian pembayaran tamu</div>
            </td>
            <td style="width: 198px;">
                <div class="payment-box">
                    <div class="muted">Sisa Pelunasan</div>
                    <div class="outstanding"><?= esc($money((float) $outstanding)) ?></div>
                    <div class="paid">Terbayar <?= esc($money((float) $paid)) ?></div>
                </div>
            </td>
        </tr>
    </table>

    <table class="card-table">
        <tr>
            <td>
                <div class="card">
                    <div class="card-title">Detail Invoice</div>
                    <table class="detail-table">
                        <tr><td>Channel</td><td><?= esc((string) ($reservation['source'] ?? '-')) ?></td></tr>
                        <tr><td>Tanggal Reservasi</td><td><?= esc($dateLabel($invoiceDate)) ?></td></tr>
                        <tr><td>Check-in</td><td><?= esc($dateLabel((string) $reservation['check_in_date'])) ?></td></tr>
                        <tr><td>Check-out</td><td><?= esc($dateLabel((string) $reservation['check_out_date'])) ?></td></tr>
                        <tr><td>Jenis Menginap</td><td><?= $isTransit ? 'Transit' : 'Menginap' ?></td></tr>
                        <tr><td>Jumlah Kamar</td><td><?= count($groupReservations) ?> kamar</td></tr>
                    </table>
                </div>
            </td>
            <td>
                <div class="card">
                    <div class="card-title">Detail Tamu</div>
                    <table class="detail-table">
                        <tr><td>Nama</td><td><?= esc((string) ($reservation['guest_name'] ?? '-')) ?></td></tr>
                        <tr><td>Email</td><td><?= esc((string) ($reservation['email'] ?: '-')) ?></td></tr>
                        <tr><td>Telepon</td><td><?= esc((string) ($reservation['phone'] ?: '-')) ?></td></tr>
                        <tr><td>Asal</td><td><?= esc((string) ($reservation['origin'] ?: '-')) ?></td></tr>
                        <tr><td>Jumlah Tamu</td><td><?= esc((string) ($reservation['guest_count'] ?? 1)) ?> orang</td></tr>
                        <tr><td>Kamar</td><td><?= $isCompact ? count($groupReservations) . ' kamar' : esc(implode(', ', $roomNames)) ?></td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <?php if (! $isCompact): ?>
        <div class="section-title">Biaya Kamar</div>
        <table class="data-table">
            <thead>
                <tr><th style="width: 21%;">Tanggal</th><th>Kamar</th><th class="amount" style="width: 25%;">Biaya</th></tr>
            </thead>
            <tbody>
                <?php foreach ($roomRows as $row): ?>
                    <tr>
                        <td><?= esc($dateLabel((string) $row['date'])) ?></td>
                        <td><?= esc((string) $row['room']) ?></td>
                        <td class="amount"><?= esc($money((float) $row['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="2">Total Biaya Kamar</td><td class="amount"><?= esc($money((float) $roomTotal)) ?></td></tr></tfoot>
        </table>
    <?php endif; ?>

    <?php if (! $isCompact && $additionalRows !== []): ?>
        <div class="section-title">Layanan Tambahan</div>
        <table class="data-table">
            <thead>
                <tr><th style="width: 21%;">Tanggal</th><th>Jenis Biaya</th><th>Detail</th><th class="amount" style="width: 25%;">Biaya</th></tr>
            </thead>
            <tbody>
                <?php foreach ($additionalRows as $row): ?>
                    <tr>
                        <td><?= esc($dateLabel((string) $row['date'])) ?></td>
                        <td><?= esc((string) $row['type']) ?></td>
                        <td><?= esc((string) ($row['details'] ?: '-')) ?></td>
                        <td class="amount"><?= esc($money((float) $row['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="3">Total Layanan Tambahan</td><td class="amount"><?= esc($money((float) $additionalTotal)) ?></td></tr></tfoot>
        </table>
    <?php endif; ?>

    <table class="summary-layout">
        <tr>
            <td class="notes">
                <div class="section-title" style="margin-top: 0;">Catatan</div>
                <div class="muted">
                    Invoice ini dibuat otomatis dari data reservasi terbaru. Layanan tambahan dan pembayaran yang dicatat setelah invoice dicetak akan muncul pada cetakan berikutnya.
                </div>
            </td>
            <td class="totals">
                <table class="totals-table">
                    <?php if ($hasServiceCharges): ?>
                        <tr><td>Biaya Kamar</td><td><?= esc($money((float) $roomTotal)) ?></td></tr>
                        <tr><td>Layanan Tambahan</td><td><?= esc($money((float) $additionalTotal)) ?></td></tr>
                    <?php else: ?>
                        <tr><td>Biaya Kamar</td><td><?= esc($money((float) $total)) ?></td></tr>
                    <?php endif; ?>
                    <tr class="grand"><td>Grand Total</td><td><?= esc($money((float) $total)) ?></td></tr>
                    <tr><td>Terbayar</td><td><?= esc($money((float) $paid)) ?></td></tr>
                    <tr class="outstanding"><td>Sisa Pelunasan</td><td><?= esc($money((float) $outstanding)) ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        <?php if ($logoData !== ''): ?><img src="<?= esc($logoData, 'attr') ?>" alt="HomiQ"><?php endif; ?>
        <div class="tagline">Where every stay #feelslikehome</div>
        <div class="website">homiq.co.id</div>
    </div>
</body>
</html>
