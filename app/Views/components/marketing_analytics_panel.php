<?php
$monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$periodLabel = $filter->month() === null
    ? 'Tahun ' . $filter->year()
    : $monthNames[$filter->month()] . ' ' . $filter->year();
$guesthouseLabel = $filter->includesAllGuesthouses()
    ? 'Semua guesthouse'
    : 'GH ' . implode(', GH ', $filter->guesthouses());
$summary = $analytics['summary'];
$origins = $analytics['origins'];
$loyalGuests = $analytics['loyal_guests'];
$originLabels = array_column($origins, 'label');
$originValues = array_map('intval', array_column($origins, 'value'));
$originPercentages = array_map('floatval', array_column($origins, 'percentage'));
$chartHeight = max(280, min(560, 115 + count($origins) * 42));
$performance = $analytics['performance'];
$performanceRows = $performance['rows'];
$performanceLabels = array_map(static fn (array $row): string => 'GH ' . $row['guesthouse'], $performanceRows);
$performanceRevenue = array_map(static fn (array $row): float => $row['revenue'], $performanceRows);
$performanceCost = array_map(static fn (array $row): float => $row['cost'], $performanceRows);
$performanceProfit = array_map(static fn (array $row): float => $row['profit'], $performanceRows);
$occupancy = $analytics['occupancy'];
$occupancyPoints = $occupancy['points'];
$occupancyLabels = array_column($occupancyPoints, 'label');
$occupancyValues = array_map(static fn (array $point): float => $point['occupancy'], $occupancyPoints);
$annualSummary = $analytics['annual_summary'];
$annualPoints = $annualSummary['points'];
$annualLabels = array_map(static fn (array $point): string => (string) $point['year'], $annualPoints);
$annualOccupancy = array_map(static fn (array $point): float => $point['occupancy'], $annualPoints);
$annualRoomNights = array_map(static fn (array $point): int => $point['room_nights_sold'], $annualPoints);
$annualSelected = $annualSummary['selected'];
$forecast = $analytics['forecast'];
$forecastHistory = $forecast['history'];
$forecastPoints = $forecast['points'];
$serviceRevenue = $analytics['service_revenue'];
$cancellations = $analytics['cancellations'];
$bookingWindow = $analytics['booking_window'];
?>

<section aria-labelledby="marketing-analytics-heading">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h3 class="h5 mb-1" id="marketing-analytics-heading">Ringkasan tamu dan booking</h3>
            <p class="text-secondary small mb-0"><?= esc($periodLabel) ?> · <?= esc($guesthouseLabel) ?> · Berdasarkan tanggal check-in</p>
        </div>
        <span class="badge rounded-pill text-bg-light border align-self-start align-self-md-center">Data reservasi aktual</span>
    </div>

    <div class="marketing-kpi-grid mb-4">
        <article class="card stat-card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div><small class="text-secondary">Tamu utama unik</small><div class="display-6 fw-semibold mt-2"><?= number_format($summary['unique_guests'], 0, ',', '.') ?></div></div>
                    <span class="marketing-kpi-icon is-guests" aria-hidden="true">T</span>
                </div>
                <p class="small text-secondary mb-0 mt-3">Satu tamu utama dihitung sekali dalam periode.</p>
            </div>
        </article>
        <article class="card stat-card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div><small class="text-secondary">Booking unik</small><div class="display-6 fw-semibold mt-2"><?= number_format($summary['unique_bookings'], 0, ',', '.') ?></div></div>
                    <span class="marketing-kpi-icon is-bookings" aria-hidden="true">B</span>
                </div>
                <p class="small text-secondary mb-0 mt-3"><?= number_format($summary['reservation_rows'], 0, ',', '.') ?> baris reservasi; booking multi-kamar tidak digandakan.</p>
            </div>
        </article>
        <article class="card stat-card border-0 shadow-sm <?= $summary['unknown_origins'] > 0 ? 'marketing-quality-warning' : '' ?>">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div><small class="text-secondary">Origin belum diketahui</small><div class="display-6 fw-semibold mt-2"><?= number_format($summary['unknown_origins'], 0, ',', '.') ?></div></div>
                    <span class="marketing-kpi-icon is-quality" aria-hidden="true">!</span>
                </div>
                <p class="small text-secondary mb-0 mt-3"><?= number_format($summary['unknown_origin_percentage'], 2, ',', '.') ?>% dari tamu utama unik dalam filter.</p>
            </div>
        </article>
    </div>

    <div class="row g-4 align-items-stretch">
        <div class="col-12 min-width-0">
            <article class="card border-0 shadow-sm marketing-chart-card">
                <div class="card-body p-4">
                    <h3 class="h5 mb-1">Asal tamu utama</h3>
                    <p class="text-secondary small mb-3">Tamu unik per lokasi · n=<?= number_format($summary['unique_guests'], 0, ',', '.') ?></p>
                    <?php if ($origins === []): ?>
                        <div class="marketing-empty-state" role="status">
                            <span class="marketing-empty-icon" aria-hidden="true">○</span>
                            <strong>Belum ada data asal tamu</strong>
                            <span>Tidak ada reservasi valid dalam periode dan guesthouse yang dipilih.</span>
                        </div>
                    <?php else: ?>
                        <div class="marketing-origin-chart-wrap" style="height: <?= $chartHeight ?>px">
                            <canvas id="marketingOriginChart"
                                    role="img"
                                    aria-label="Grafik batang horizontal asal tamu utama"
                                    data-labels='<?= esc(json_encode($originLabels, JSON_UNESCAPED_UNICODE), 'attr') ?>'
                                    data-values='<?= esc(json_encode($originValues), 'attr') ?>'
                                    data-percentages='<?= esc(json_encode($originPercentages), 'attr') ?>'></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        </div>

        <div class="col-12 min-width-0">
            <article class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 p-4 pb-2">
                    <h3 class="h5 mb-1">Top 10 tamu paling loyal</h3>
                    <p class="text-secondary small mb-0">Diurutkan berdasarkan booking unik, lalu total malam dan nominal reservasi.</p>
                </div>
                <?php if ($loyalGuests === []): ?>
                    <div class="card-body p-4 pt-2">
                        <div class="marketing-empty-state" role="status">
                            <span class="marketing-empty-icon" aria-hidden="true">○</span>
                            <strong>Belum ada data loyalitas</strong>
                            <span>Tidak ada reservasi valid untuk filter yang dipilih.</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle marketing-loyalty-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Tamu</th>
                                    <th scope="col">Asal</th>
                                    <th class="text-end" scope="col">Booking</th>
                                    <th class="text-end" scope="col">Malam</th>
                                    <th class="text-end" scope="col">Total nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loyalGuests as $index => $guest): ?>
                                    <?php $origin = normalize_guest_origin($guest['origin'] ?? null); ?>
                                    <tr>
                                        <td><span class="marketing-rank <?= $index < 3 ? 'is-top' : '' ?>"><?= $index + 1 ?></span></td>
                                        <td>
                                            <div class="fw-semibold"><?= esc($guest['full_name']) ?></div>
                                            <small class="text-secondary"><?= esc($guest['phone'] ?: ($guest['email'] ?: 'Kontak belum tersedia')) ?></small>
                                        </td>
                                        <td><span class="badge <?= $origin === 'Tidak diketahui' ? 'text-bg-warning' : 'text-bg-light border' ?>"><?= esc($origin) ?></span></td>
                                        <td class="text-end fw-semibold"><?= number_format((int) $guest['booking_count'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format((int) $guest['total_nights'], 0, ',', '.') ?></td>
                                        <td class="text-end text-nowrap">Rp<?= number_format((float) $guest['total_nominal'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
        </div>
    </div>

    <section class="mt-4" aria-labelledby="marketing-performance-heading">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <div>
                <h3 class="h5 mb-1" id="marketing-performance-heading">Perbandingan performa guesthouse</h3>
                <p class="text-secondary small mb-0">Revenue berdasarkan tanggal pembayaran diterima; cost berdasarkan tanggal expense dicatat.</p>
            </div>
            <span class="badge rounded-pill text-bg-light border align-self-start align-self-md-center"><?= esc($periodLabel) ?></span>
        </div>

        <div class="row g-4 align-items-stretch">
            <div class="col-12 min-width-0">
                <article class="card border-0 shadow-sm marketing-chart-card">
                    <div class="card-body p-4">
                        <h4 class="h6 mb-1">Revenue, cost, dan profit per GH</h4>
                        <p class="text-secondary small mb-3">Dalam Rupiah · <?= esc($guesthouseLabel) ?></p>
                        <?php if ($performanceRows === []): ?>
                            <div class="marketing-empty-state" role="status"><span class="marketing-empty-icon" aria-hidden="true">○</span><strong>Belum ada guesthouse dalam filter</strong></div>
                        <?php else: ?>
                            <div class="marketing-performance-chart-wrap">
                                <canvas id="marketingPerformanceChart" role="img" aria-label="Grafik perbandingan revenue, cost, dan profit per guesthouse" data-labels='<?= esc(json_encode($performanceLabels), 'attr') ?>' data-revenue='<?= esc(json_encode($performanceRevenue), 'attr') ?>' data-cost='<?= esc(json_encode($performanceCost), 'attr') ?>' data-profit='<?= esc(json_encode($performanceProfit), 'attr') ?>'></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
            <div class="col-12 min-width-0">
                <article class="card border-0 shadow-sm">
                    <div class="table-responsive">
                        <table class="table align-middle marketing-performance-table mb-0">
                            <thead><tr><th>Guesthouse</th><th class="text-end">Revenue</th><th class="text-end">Cost</th><th class="text-end">Profit</th><th class="text-end">Margin</th></tr></thead>
                            <tbody>
                                <?php foreach ($performanceRows as $row): ?>
                                    <tr>
                                        <td class="fw-semibold">GH <?= (int) $row['guesthouse'] ?></td>
                                        <td class="text-end text-nowrap">Rp<?= number_format($row['revenue'], 0, ',', '.') ?></td>
                                        <td class="text-end text-nowrap">Rp<?= number_format($row['cost'], 0, ',', '.') ?></td>
                                        <td class="text-end text-nowrap fw-semibold <?= $row['profit'] < 0 ? 'text-danger' : 'text-success' ?>">Rp<?= number_format($row['profit'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= $row['margin'] === null ? '-' : number_format($row['margin'], 2, ',', '.') . '%' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot><tr><th>Total dialokasikan</th><th class="text-end">Rp<?= number_format($performance['totals']['revenue'], 0, ',', '.') ?></th><th class="text-end">Rp<?= number_format($performance['totals']['cost'], 0, ',', '.') ?></th><th class="text-end <?= $performance['totals']['profit'] < 0 ? 'text-danger' : 'text-success' ?>">Rp<?= number_format($performance['totals']['profit'], 0, ',', '.') ?></th><th class="text-end"><?= $performance['totals']['margin'] === null ? '-' : number_format($performance['totals']['margin'], 2, ',', '.') . '%' ?></th></tr></tfoot>
                        </table>
                    </div>
                    <div class="card-body pt-3">
                        <?php if ($performance['unallocated_expense'] > 0): ?>
                            <div class="alert alert-warning small mb-0"><strong>Expense belum dialokasikan:</strong> Rp<?= number_format($performance['unallocated_expense'], 0, ',', '.') ?> tidak dimasukkan ke GH mana pun.</div>
                        <?php else: ?>
                            <p class="text-secondary small mb-0">Semua expense pada periode ini telah dialokasikan. Expense multi-GH dibagi rata; total cost pada tabel tidak menduplikasi nominal expense.</p>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="mt-4" aria-labelledby="marketing-services-heading">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <div>
                <h3 class="h5 mb-1" id="marketing-services-heading">Pendapatan layanan tambahan</h3>
                <p class="text-secondary small mb-0">Dana diterima pada periode pembayaran · layanan ini masuk kas perusahaan, bukan pendapatan Owner GH.</p>
            </div>
            <span class="badge rounded-pill text-bg-light border align-self-start align-self-md-center"><?= number_format($serviceRevenue['payment_count'], 0, ',', '.') ?> pembayaran Paid</span>
        </div>
        <div class="row g-3">
            <?php foreach ($serviceRevenue['services'] as $serviceName => $amount): ?>
                <div class="col-sm-6 col-xl-3"><article class="card border-0 shadow-sm h-100"><div class="card-body p-3"><small class="text-secondary"><?= esc($serviceName) ?></small><strong class="d-block fs-5 mt-2">Rp<?= number_format($amount, 0, ',', '.') ?></strong></div></article></div>
            <?php endforeach; ?>
        </div>
        <div class="alert alert-light border small mt-3 mb-0"><strong>Total layanan diterima:</strong> Rp<?= number_format($serviceRevenue['total'], 0, ',', '.') ?>. Nilai DP dihitung proporsional berdasarkan komposisi invoice.</div>
    </section>

    <section class="mt-4" aria-labelledby="marketing-cancellation-heading">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <div>
                <h3 class="h5 mb-1" id="marketing-cancellation-heading">Cancellation Report</h3>
                <p class="text-secondary small mb-0">Berdasarkan tanggal pembatalan · <?= esc($periodLabel) ?> · <?= esc($guesthouseLabel) ?>.</p>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-3"><article class="card border-0 shadow-sm h-100"><div class="card-body p-3"><small class="text-secondary">Booking dibatalkan</small><strong class="d-block display-6 mt-1"><?= number_format($cancellations['summary']['bookings'], 0, ',', '.') ?></strong></div></article></div>
            <div class="col-sm-6 col-xl-3"><article class="card border-0 shadow-sm h-100"><div class="card-body p-3"><small class="text-secondary">Kamar dibatalkan</small><strong class="d-block display-6 mt-1"><?= number_format($cancellations['summary']['rooms'], 0, ',', '.') ?></strong></div></article></div>
            <div class="col-sm-6 col-xl-3"><article class="card border-0 shadow-sm h-100"><div class="card-body p-3"><small class="text-secondary">Nilai tagihan dibatalkan</small><strong class="d-block fs-5 mt-2">Rp<?= number_format($cancellations['summary']['cancelled_amount'], 0, ',', '.') ?></strong></div></article></div>
            <div class="col-sm-6 col-xl-3"><article class="card border-0 shadow-sm h-100"><div class="card-body p-3"><small class="text-secondary">Kredit/refund pending</small><strong class="d-block fs-5 text-warning-emphasis mt-2">Rp<?= number_format($cancellations['summary']['refund_pending'], 0, ',', '.') ?></strong></div></article></div>
        </div>
        <article class="card border-0 shadow-sm">
            <?php if ($cancellations['rows'] === []): ?>
                <div class="card-body text-center text-secondary py-4">Belum ada pembatalan pada periode ini.</div>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead class="table-light"><tr><th>Tanggal batal</th><th>Booking</th><th>Tamu</th><th>Kamar</th><th class="text-end">Tagihan</th><th class="text-end">Kredit/refund</th></tr></thead><tbody><?php foreach ($cancellations['rows'] as $cancellation): ?><tr><td class="text-nowrap"><?= esc(date('d/m/Y H:i', strtotime($cancellation['cancelled_at']))) ?></td><td class="fw-semibold text-nowrap"><?= esc($cancellation['order_id']) ?></td><td><?= esc($cancellation['guest_name']) ?></td><td>Room <?= esc($cancellation['room_number']) ?></td><td class="text-end text-nowrap">Rp<?= number_format($cancellation['total_amount'], 0, ',', '.') ?></td><td class="text-end text-nowrap">Rp<?= number_format($cancellation['refund_pending'], 0, ',', '.') ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </article>
    </section>

    <section class="mt-4" aria-labelledby="marketing-booking-window-heading">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <div><h3 class="h5 mb-1" id="marketing-booking-window-heading">Booking Window</h3><p class="text-secondary small mb-0">Jarak booking dibuat ke tanggal check-in · booking multi-kamar dihitung satu kali.</p></div>
        </div>
        <div class="row g-3">
            <div class="col-sm-4"><article class="card border-0 shadow-sm h-100"><div class="card-body p-3"><small class="text-secondary">Booking dianalisis</small><strong class="d-block display-6 mt-1"><?= number_format($bookingWindow['summary']['bookings'], 0, ',', '.') ?></strong></div></article></div>
            <div class="col-sm-4"><article class="card border-0 shadow-sm h-100"><div class="card-body p-3"><small class="text-secondary">Rata-rata waktu booking</small><strong class="d-block display-6 mt-1"><?= number_format($bookingWindow['summary']['average_days'], 1, ',', '.') ?> <small class="fs-6">hari</small></strong></div></article></div>
            <div class="col-sm-4"><article class="card border-0 shadow-sm h-100"><div class="card-body p-3"><small class="text-secondary">Median waktu booking</small><strong class="d-block display-6 mt-1"><?= number_format($bookingWindow['summary']['median_days'], 1, ',', '.') ?> <small class="fs-6">hari</small></strong></div></article></div>
        </div>
        <article class="card border-0 shadow-sm mt-3"><div class="card-body p-3"><div class="row g-2"><?php foreach ($bookingWindow['buckets'] as $bucket): ?><div class="col-6 col-md-4 col-xl-2"><div class="border rounded p-2 h-100"><small class="text-secondary d-block"><?= esc($bucket['label']) ?></small><strong class="fs-5"><?= number_format($bucket['value'], 0, ',', '.') ?></strong><small class="text-secondary"> booking</small></div></div><?php endforeach; ?></div></div></article>
    </section>

    <section class="mt-4" aria-labelledby="marketing-occupancy-heading">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <div>
                <h3 class="h5 mb-1" id="marketing-occupancy-heading">Trend okupansi bulanan</h3>
                <p class="text-secondary small mb-0">Berdasarkan tanggal menginap dan kamar-malam, bukan tanggal pembayaran.</p>
            </div>
            <span class="badge rounded-pill text-bg-light border align-self-start align-self-md-center"><?= esc($guesthouseLabel) ?></span>
        </div>

        <article class="card border-0 shadow-sm marketing-chart-card">
            <div class="card-body p-4">
                <div class="row g-3 align-items-center mb-3">
                    <div class="col-sm-auto"><span class="text-secondary small d-block">Kamar-malam terjual</span><strong class="fs-4"><?= number_format($occupancy['summary']['room_nights_sold'], 0, ',', '.') ?></strong></div>
                    <div class="col-sm-auto"><span class="text-secondary small d-block">Kapasitas periode <span class="text-primary" title="Kapasitas teoritis selama periode filter" aria-label="Kapasitas teoritis selama periode filter">ⓘ</span></span><strong class="fs-4"><?= number_format($occupancy['summary']['room_nights_available'], 0, ',', '.') ?></strong></div>
                    <div class="col-sm-auto"><span class="text-secondary small d-block">Okupansi weighted</span><strong class="fs-4 text-primary"><?= number_format($occupancy['summary']['occupancy'], 2, ',', '.') ?>%</strong></div>
                    <div class="col-sm"><p class="small text-secondary mb-0"><strong>Petunjuk:</strong> kapasitas periode = <?= number_format($occupancy['summary']['room_count'], 0, ',', '.') ?> kamar × <?= number_format($occupancy['summary']['period_days'], 0, ',', '.') ?> hari = <?= number_format($occupancy['summary']['room_nights_available'], 0, ',', '.') ?> kamar-malam teoritis. Ini bukan jumlah kamar fisik; maintenance belum mengurangi kapasitas.</p></div>
                </div>
                <?php if ($occupancyPoints === [] || $occupancy['summary']['room_count'] === 0): ?>
                    <div class="marketing-empty-state" role="status"><span class="marketing-empty-icon" aria-hidden="true">○</span><strong>Belum ada kamar pada guesthouse yang dipilih</strong></div>
                <?php else: ?>
                    <div class="marketing-occupancy-chart-wrap">
                        <canvas id="marketingOccupancyChart"
                                role="img"
                                aria-label="Grafik garis trend okupansi bulanan"
                                data-labels='<?= esc(json_encode($occupancyLabels, JSON_UNESCAPED_UNICODE), 'attr') ?>'
                                data-values='<?= esc(json_encode($occupancyValues), 'attr') ?>'
                                data-details='<?= esc(json_encode($occupancyPoints, JSON_UNESCAPED_UNICODE), 'attr') ?>'></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <section class="mt-4" aria-labelledby="marketing-annual-heading">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <div>
                <h3 class="h5 mb-1" id="marketing-annual-heading">Ringkasan okupansi tahunan</h3>
                <p class="text-secondary small mb-0">Tahun kalender penuh · <?= esc($guesthouseLabel) ?> · basis kamar-malam.</p>
            </div>
            <?php if ($filter->month() !== null): ?>
                <span class="badge rounded-pill text-bg-light border align-self-start align-self-md-center">Filter bulan tidak diterapkan agar YoY setara</span>
            <?php endif; ?>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <article class="card border-0 shadow-sm h-100"><div class="card-body p-4"><small class="text-secondary">Okupansi <?= (int) $annualSelected['year'] ?></small><div class="display-6 fw-semibold text-primary mt-2"><?= number_format($annualSelected['occupancy'], 2, ',', '.') ?>%</div><p class="small text-secondary mb-0 mt-3">Weighted dari <?= number_format($annualSelected['room_nights_sold'], 0, ',', '.') ?> kamar-malam terjual.</p></div></article>
            </div>
            <div class="col-md-4">
                <article class="card border-0 shadow-sm h-100"><div class="card-body p-4"><small class="text-secondary">Kamar-malam terjual <?= (int) $annualSelected['year'] ?></small><div class="display-6 fw-semibold mt-2"><?= number_format($annualSelected['room_nights_sold'], 0, ',', '.') ?></div><p class="small text-secondary mb-0 mt-3">Dari kapasitas <?= number_format($annualSelected['room_nights_available'], 0, ',', '.') ?> kamar-malam.</p></div></article>
            </div>
            <div class="col-md-4">
                <article class="card border-0 shadow-sm h-100"><div class="card-body p-4"><small class="text-secondary">Perubahan YoY kamar-malam</small><div class="display-6 fw-semibold mt-2 <?= $annualSelected['yoy_room_nights'] !== null && $annualSelected['yoy_room_nights'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $annualSelected['yoy_room_nights'] === null ? '-' : number_format($annualSelected['yoy_room_nights'], 2, ',', '.') . '%' ?></div><p class="small text-secondary mb-0 mt-3"><?= $annualSelected['yoy_occupancy_points'] === null ? 'Belum ada pembanding tahun sebelumnya.' : number_format($annualSelected['yoy_occupancy_points'], 2, ',', '.') . ' poin persentase okupansi dari tahun sebelumnya.' ?></p></div></article>
            </div>
        </div>

        <div class="row g-4 align-items-stretch">
            <div class="col-12 min-width-0"><article class="card border-0 shadow-sm marketing-chart-card"><div class="card-body p-4"><h4 class="h6 mb-1">Okupansi per tahun</h4><p class="text-secondary small mb-3">Persentase weighted; bukan rata-rata persentase bulan.</p><div class="marketing-annual-chart-wrap"><canvas id="marketingAnnualOccupancyChart" role="img" aria-label="Grafik batang okupansi tahunan" data-labels='<?= esc(json_encode($annualLabels), 'attr') ?>' data-values='<?= esc(json_encode($annualOccupancy), 'attr') ?>'></canvas></div></div></article></div>
            <div class="col-12 min-width-0"><article class="card border-0 shadow-sm marketing-chart-card"><div class="card-body p-4"><h4 class="h6 mb-1">Trend kamar-malam terjual</h4><p class="text-secondary small mb-3">Jumlah malam untuk setiap baris kamar yang benar-benar terjual.</p><div class="marketing-annual-chart-wrap"><canvas id="marketingAnnualRoomNightsChart" role="img" aria-label="Grafik garis kamar malam terjual per tahun" data-labels='<?= esc(json_encode($annualLabels), 'attr') ?>' data-values='<?= esc(json_encode($annualRoomNights), 'attr') ?>'></canvas></div></div></article></div>
        </div>
    </section>

    <section class="mt-4" aria-labelledby="marketing-forecast-heading">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <div>
                <h3 class="h5 mb-1" id="marketing-forecast-heading">Forecast kamar-malam terjual</h3>
                <p class="text-secondary small mb-0">Horizon 6 bulan · Holt-Winters musiman · <?= esc($guesthouseLabel) ?>.</p>
            </div>
            <span class="badge rounded-pill <?= $forecast['enabled'] ? 'text-bg-success' : 'text-bg-warning' ?> align-self-start align-self-md-center">Kualitas: <?= esc($forecast['quality']) ?></span>
        </div>

        <article class="card border-0 shadow-sm marketing-chart-card">
            <div class="card-body p-4">
                <?php if (! $forecast['enabled']): ?>
                    <div class="marketing-forecast-unavailable" role="status">
                        <span class="marketing-empty-icon" aria-hidden="true">○</span>
                        <div><strong>Histori belum cukup untuk forecasting</strong><p class="text-secondary small mb-2"><?= esc($forecast['message']) ?></p><div class="progress marketing-forecast-progress" role="progressbar" aria-label="Kelengkapan histori forecast" aria-valuenow="<?= min($forecast['complete_months'], $forecast['required_months']) ?>" aria-valuemin="0" aria-valuemax="<?= $forecast['required_months'] ?>"><div class="progress-bar" style="width: <?= min(100, ($forecast['complete_months'] / $forecast['required_months']) * 100) ?>%"></div></div><small class="text-secondary"><?= number_format($forecast['complete_months'], 0, ',', '.') ?> dari <?= number_format($forecast['required_months'], 0, ',', '.') ?> bulan lengkap tersedia. Bulan berjalan tidak dihitung.</small></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info small mb-3"><strong>Estimasi, bukan data aktual.</strong> Area biru muda menunjukkan rentang estimasi 95% berdasarkan error historis model. Forecast dibatasi oleh kapasitas kamar.</div>
                    <div class="marketing-forecast-chart-wrap">
                        <canvas id="marketingForecastChart" role="img" aria-label="Grafik aktual dan forecast kamar malam terjual" data-history='<?= esc(json_encode($forecastHistory, JSON_UNESCAPED_UNICODE), 'attr') ?>' data-forecast='<?= esc(json_encode($forecastPoints, JSON_UNESCAPED_UNICODE), 'attr') ?>'></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <div class="alert alert-light border small text-secondary mt-4 mb-0" role="note">
        <strong class="text-body">Definisi:</strong> cancelled tidak dihitung. Booking mengikuti grup booking; total malam pada tabel loyalitas memakai durasi menginap per booking sehingga kamar tambahan tidak menggandakan malam. Okupansi memakai kamar-malam per baris kamar dan memecah reservasi yang melewati bulan. Nominal adalah total reservasi dalam scope filter, bukan pembayaran diterima.
    </div>
</section>
