<?php
use App\Libraries\RoleAccess;

$maskFinancials = RoleAccess::masksDashboardFinancials((string) session('role'));
?>
<div class="dashboard-stat-grid mb-4">
    <div class="card stat-card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Occupancy</small><div class="display-6 fw-semibold"><?= number_format($stats['occupancy'], 1) ?>%</div><div class="progress mt-2" style="height:6px"><div class="progress-bar" style="width:<?= min(100, $stats['occupancy']) ?>%"></div></div></div></div>
    <div class="card stat-card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Revenue</small><div class="fs-4 fw-semibold mt-2 text-nowrap"><?= $maskFinancials ? 'Rp***.***' : 'Rp' . number_format($stats['revenue'], 0, ',', '.') ?></div><span class="text-success small">Paid payments</span></div></div>
    <div class="card stat-card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Net Profit</small><div class="fs-4 fw-semibold mt-2 text-nowrap <?= $stats['netProfit'] < 0 && ! $maskFinancials ? 'text-danger' : 'text-success' ?>"><?= $maskFinancials ? 'Rp***.***' : 'Rp' . number_format($stats['netProfit'], 0, ',', '.') ?></div><span class="text-secondary small">Expense Rp<?= number_format($stats['expenses'], 0, ',', '.') ?></span></div></div>
    <div class="card stat-card border-0 shadow-sm"><div class="card-body"><small class="text-secondary">Average Stay</small><div class="display-6 fw-semibold"><?= number_format($stats['averageStay'], 1) ?></div><span class="text-secondary small">malam / reservasi</span></div></div>
    <div class="card stat-card border-0 shadow-sm dashboard-rented-card"><div class="card-body"><small>Rooms Rented</small><div class="display-6 fw-semibold"><?= (int) $stats['roomsRented'] ?></div><span class="small">kamar-malam pada bulan terpilih</span></div></div>
</div>

<?php if ($breakfastAlerts !== []): ?>
    <section class="card border-0 shadow-sm mb-4 dashboard-breakfast-strip">
        <div class="card-body py-2 px-3 d-flex flex-column flex-lg-row align-items-lg-center gap-2">
            <div class="d-flex align-items-center gap-2 text-nowrap"><strong class="small">Notif Pesanan Sarapan</strong><span class="badge text-bg-warning"><?= count($breakfastAlerts) ?></span></div>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($breakfastAlerts as $alert): ?>
                    <button class="btn btn-sm dashboard-breakfast-item" type="button" hx-get="<?= site_url('reservations/' . $alert['id']) ?>" hx-target="#modal-container" hx-swap="innerHTML"><?= esc($alert['guest_name']) ?> · Room <?= esc($alert['room_number']) ?> · <?= ($alert['status'] ?? '') === 'Checked_In' ? 'Sedang menginap' : 'CI ' . esc(date('d/m/Y', strtotime($alert['check_in_date']))) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-8 dashboard-chart-column">
        <div class="card border-0 shadow-sm h-100 dashboard-chart-card">
            <div class="card-body p-4">
                <h3 class="h6 mb-3">Revenue vs Expense — <?= esc($month) ?><?= $guesthouse !== null ? ' · GH ' . (int) $guesthouse : '' ?></h3>
                <div class="dashboard-chart-wrap"><canvas id="revenueExpenseChart" data-labels='<?= esc(json_encode($chart['labels']), 'attr') ?>' data-revenue='<?= esc(json_encode($maskFinancials ? array_fill(0, count($chart['revenue']), 0) : $chart['revenue']), 'attr') ?>' data-expenses='<?= esc(json_encode($chart['expenses']), 'attr') ?>'></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div><h3 class="h6 mb-1">Today's Activity</h3><small class="text-secondary"><?= esc(date('d/m/Y', strtotime($today))) ?> WIB</small></div>
                </div>
                <div class="row g-2 mb-3">
                    <?php $activitySuffix = $guesthouse !== null ? '?guesthouse=' . (int) $guesthouse : ''; ?>
                    <div class="col-6"><button class="dashboard-activity-count dashboard-checkin-count w-100" type="button" hx-get="<?= site_url('dashboard/activity/check-in') . $activitySuffix ?>" hx-target="#modal-container" hx-swap="innerHTML"><strong><?= (int) $activities['checkInCount'] ?></strong><span>Check-ins</span></button></div>
                    <div class="col-6"><button class="dashboard-activity-count dashboard-checkout-count w-100" type="button" hx-get="<?= site_url('dashboard/activity/check-out') . $activitySuffix ?>" hx-target="#modal-container" hx-swap="innerHTML"><strong><?= (int) $activities['checkOutCount'] ?></strong><span>Check-outs</span></button></div>
                </div>
                <p class="small text-secondary text-center mb-0">Klik jumlah untuk melihat daftar lengkap.</p>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h3 class="h6 mb-3">Operational Alerts</h3>
                <div class="list-group list-group-flush">
                    <div class="list-group-item px-0 d-flex justify-content-between"><span>Dirty Rooms</span><strong><?= $stats['dirtyRooms'] ?></strong></div>
                    <div class="list-group-item px-0 d-flex justify-content-between"><span>Open Maintenance</span><strong><?= $stats['openMaintenance'] ?></strong></div>
                    <div class="list-group-item px-0 d-flex justify-content-between"><span>Low Stock Items</span><strong><?= $stats['lowStock'] ?></strong></div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="card border-0 shadow-sm mt-4 dashboard-service-alert-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <div class="d-flex align-items-center gap-3">
                <span class="dashboard-service-alert-icon" aria-hidden="true">!</span>
                <div><h3 class="h6 mb-1">Notifikasi Layanan Tamu</h3><small class="text-secondary">Early Check In mendatang/aktif dan Late Checkout hari ini.</small></div>
            </div>
            <?php if ($serviceAlerts !== []): ?><span class="badge dashboard-service-alert-count"><?= count($serviceAlerts) ?> notifikasi</span><?php endif; ?>
        </div>
        <?php if ($serviceAlerts === []): ?>
            <div class="text-center text-secondary py-3">Tidak ada Early Check In atau Late Checkout yang perlu ditindaklanjuti.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0 dashboard-service-alert-table">
                    <thead><tr><th>Layanan</th><th>Tamu</th><th>Kamar</th><th>Jadwal</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($serviceAlerts as $alert): ?>
                            <?php $isEarlyCheckIn = $alert['type'] === 'early_checkin'; ?>
                            <tr class="dashboard-service-alert-row <?= $isEarlyCheckIn ? 'is-early-checkin' : 'is-late-checkout' ?>" role="button" hx-get="<?= site_url('reservations/' . $alert['id']) ?>" hx-target="#modal-container" hx-swap="innerHTML">
                                <td><span class="badge <?= $isEarlyCheckIn ? 'text-bg-primary' : 'text-bg-warning' ?>"><?= $isEarlyCheckIn ? 'Early Check In' : 'Late Checkout' ?></span></td>
                                <td class="fw-semibold"><?= esc($alert['guest_name']) ?></td>
                                <td>Room <?= esc($alert['room_number']) ?></td>
                                <td class="text-nowrap"><?= $isEarlyCheckIn ? 'Check-in ' . esc(date('d/m/Y', strtotime($alert['check_in_date']))) : 'Check-out hari ini' ?></td>
                                <td><?= esc(str_replace('_', ' ', $alert['status'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="card border-0 shadow-sm mt-4 dashboard-cleaning-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <div class="d-flex align-items-center gap-3">
                <span class="dashboard-cleaning-icon" aria-hidden="true">✓</span>
                <div><h3 class="h6 mb-1">Cleaning Task Hari Ini</h3><small class="text-secondary"><?= esc(date('d/m/Y', strtotime($today))) ?> WIB</small></div>
            </div>
            <?php if ($todayCleaningTasks !== []): ?><span class="badge dashboard-cleaning-count"><?= count($todayCleaningTasks) ?> task</span><?php endif; ?>
            <a class="small text-primary text-decoration-none" href="<?= site_url('housekeeping') ?>">Kelola task</a>
        </div>
        <?php if ($todayCleaningTasks === []): ?>
            <div class="text-center text-secondary py-3">Tidak ada cleaning task untuk hari ini.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0 dashboard-cleaning-table">
                    <thead><tr><th>Area</th><th>Task</th><th>Assign To</th><th>Prioritas</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($todayCleaningTasks as $task): ?>
                            <?php $priorityClass = in_array($task['priority'], ['High', 'Urgent'], true) ? 'text-bg-danger' : 'text-bg-secondary'; ?>
                            <tr class="dashboard-cleaning-row">
                                <td class="fw-semibold"><?= esc($task['room_area']) ?></td>
                                <td><?= esc($task['description']) ?></td>
                                <td><?= esc($task['assignees'] ?: 'Belum ditentukan') ?></td>
                                <td><span class="badge <?= esc($priorityClass) ?>"><?= esc($task['priority']) ?></span></td>
                                <td><?= esc(str_replace('_', ' ', $task['status'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="card border-0 shadow-sm mt-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <h3 class="h6 mb-0">Recent Reservations</h3>
            <div class="d-flex align-items-center gap-3">
                <?php $recentExportQuery = http_build_query(array_filter(['guesthouse' => $guesthouse], static fn ($value) => $value !== null && $value !== '')); ?>
                <a class="btn btn-outline-success btn-sm" href="<?= site_url('dashboard/recent-reservations/export' . ($recentExportQuery !== '' ? '?' . $recentExportQuery : '')) ?>">Export Excel</a>
                <a class="small text-primary text-decoration-none" href="<?= site_url('reservations') ?>">Lihat semua</a>
            </div>
        </div>
        <?php if ($recentReservations === []): ?>
            <div class="text-center text-secondary py-4">Belum ada reservasi.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table dashboard-recent-table align-middle mb-0">
                    <thead><tr><th>Nama Tamu</th><th>ID Booking</th><th>Kamar</th><th>Sumber</th><th>Check-in</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentReservations as $reservation): ?>
                            <?php
                            $status = (string) $reservation['status'];
                            $statusClass = match ($status) {
                                'Confirmed', 'Checked_In' => 'text-bg-success',
                                'Cancelled' => 'text-bg-danger',
                                'Checked_Out' => 'text-bg-secondary',
                                'No_Show' => 'text-bg-dark',
                                default => 'text-bg-primary',
                            };
                            ?>
                            <tr class="dashboard-recent-row" role="button" hx-get="<?= site_url('reservations/' . $reservation['id']) ?>" hx-target="#modal-container" hx-swap="innerHTML">
                                <td class="fw-semibold"><?= esc($reservation['guest_name']) ?></td>
                                <td class="text-primary fw-semibold text-nowrap"><?= esc($reservation['order_id']) ?></td>
                                <td><span class="dashboard-room-dot"></span><?= esc($reservation['room_number']) ?></td>
                                <td><?= esc($reservation['source'] ?? '-') ?></td>
                                <td class="text-nowrap"><?= esc(date('d/m/Y', strtotime($reservation['check_in_date']))) ?></td>
                                <td><span class="badge <?= esc($statusClass) ?>"><?= esc(str_replace('_', ' ', $status)) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
