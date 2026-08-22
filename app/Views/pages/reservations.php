<?php
$statusLabels = [
    'Confirmed' => 'Terkonfirmasi',
    'Checked_In' => 'Check-in',
    'Checked_Out' => 'Check-out',
    'Cancelled' => 'Dibatalkan',
    'No_Show' => 'No-Show',
];
$statusClasses = [
    'Confirmed' => 'text-bg-primary',
    'Checked_In' => 'text-bg-warning',
    'Checked_Out' => 'text-bg-success',
    'Cancelled' => 'text-bg-danger',
    'No_Show' => 'text-bg-secondary',
];
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="get" action="<?= site_url('reservations') ?>" class="row g-3 align-items-end">
            <div class="col-12 col-xl-4">
                <label class="form-label" for="reservation-search">Cari reservasi</label>
                <input id="reservation-search" class="form-control" name="q" value="<?= esc($filters['q']) ?>" placeholder="Nama tamu, ID, telepon, atau kamar">
            </div>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="reservation-guesthouse">Guesthouse</label>
                <select id="reservation-guesthouse" class="form-select" name="guesthouse">
                    <option value="">Semua GH</option>
                    <?php for ($guesthouse = 1; $guesthouse <= 5; $guesthouse++): ?>
                        <option value="<?= $guesthouse ?>" <?= (int) $filters['guesthouse'] === $guesthouse ? 'selected' : '' ?>>GH <?= $guesthouse ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="reservation-status">Status</label>
                <select id="reservation-status" class="form-select" name="status">
                    <option value="">Semua status</option>
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="check-in-from">Check-in dari</label>
                <input id="check-in-from" class="form-control" type="date" name="check_in_from" value="<?= esc($filters['check_in_from']) ?>">
            </div>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="check-in-to">Sampai</label>
                <input id="check-in-to" class="form-control" type="date" name="check_in_to" value="<?= esc($filters['check_in_to']) ?>">
            </div>
            <div class="col-sm-6 col-xl-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1">Tampilkan</button>
                <a class="btn btn-outline-secondary" href="<?= site_url('reservations') ?>">Reset</a>
            </div>
        </form>
    </div>
</div>

<div id="reservation-list-card" class="card border-0 shadow-sm overflow-hidden" hx-get="<?= site_url('reservations?' . http_build_query($filters)) ?>" hx-trigger="reconciliationChanged from:body, reservationChanged from:body" hx-select="#reservation-list-card" hx-target="this" hx-swap="outerHTML">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-semibold">Daftar Reservasi</span>
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-outline-success btn-sm" href="<?= site_url('reservations/export?' . http_build_query($filters)) ?>">Export Excel</a>
            <span class="badge text-bg-light border text-secondary"><?= (int) $pagination['total'] ?> hasil</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 front-desk-table small">
            <thead class="table-light">
                <tr><th>ID Reservasi</th><th>Status</th><th>Nama Tamu</th><th>Guesthouse</th><th>Kamar</th><th>Sumber</th><th>Check-in</th><th>Check-out</th><th class="text-end">Total</th><th>Pembayaran</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
                <?php if ($reservations === []): ?>
                    <tr><td colspan="11" class="text-center text-secondary py-5">Belum ada reservasi sesuai filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($reservations as $reservation): ?>
                        <?php
                        $total = (float) $reservation['total_amount'];
                        $paid = (float) $reservation['paid_amount'];
                        $paymentLabel = $paid >= $total && $total > 0 ? 'Lunas' : ($paid > 0 ? 'DP' : 'Belum bayar');
                        $paymentClass = $paid >= $total && $total > 0 ? 'text-success' : ($paid > 0 ? 'text-warning-emphasis' : 'text-danger');
                        $isReconciled = (int) ($reservation['is_reconciled'] ?? 0) === 1;
                        ?>
                        <tr class="<?= $isReconciled ? 'reservation-row-reconciled' : '' ?>">
                            <td class="fw-semibold text-nowrap"><?= esc($reservation['order_id']) ?><?= $isReconciled ? '<span class="reconciled-marker ms-1">★ Rekonsiliasi</span>' : '' ?></td>
                            <td><span class="badge <?= esc($statusClasses[$reservation['status']] ?? 'text-bg-secondary') ?>"><?= esc($statusLabels[$reservation['status']] ?? $reservation['status']) ?></span></td>
                            <td><div class="fw-semibold"><?= esc($reservation['guest_name']) ?></div><small class="text-secondary"><?= esc($reservation['phone'] ?: '-') ?></small></td>
                            <td class="text-nowrap">GH <?= esc($reservation['floor']) ?></td>
                            <td class="text-nowrap"><?= esc($reservation['room_number']) ?><small class="d-block text-secondary"><?= esc($reservation['room_type']) ?></small></td>
                            <td><?= esc($reservation['source'] ?: 'Direct') ?><?php if (! empty($reservation['agent_name'])): ?><span class="badge text-bg-primary ms-1 mt-1" title="Tamu dari agen <?= esc($reservation['agent_name']) ?>">🤝 Agen</span><small class="d-block text-secondary"><?= esc($reservation['agent_name']) ?></small><?php endif; ?></td>
                            <td class="text-nowrap"><?= esc(date('d/m/Y', strtotime($reservation['check_in_date']))) ?></td>
                            <td class="text-nowrap"><?= esc(date('d/m/Y', strtotime($reservation['check_out_date']))) ?></td>
                            <td class="text-end text-nowrap">Rp<?= number_format($total, 0, ',', '.') ?></td>
                            <td><span class="<?= esc($paymentClass) ?> text-nowrap"><?= esc($paymentLabel) ?></span></td>
                            <td class="text-end"><button class="btn btn-outline-primary btn-sm" type="button" data-reservation-detail="<?= esc(site_url('reservations/' . $reservation['id'])) ?>">Detail</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagination['totalPages'] > 1): ?>
        <?php
        $pageQuery = $filters;
        $pageUrl = static fn (int $targetPage): string => site_url('reservations?' . http_build_query($pageQuery + ['page' => $targetPage]));
        $firstResult = (($pagination['page'] - 1) * $pagination['perPage']) + 1;
        $lastResult = min($pagination['page'] * $pagination['perPage'], $pagination['total']);
        ?>
        <div class="card-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 py-3">
            <small class="text-secondary">Menampilkan <?= $firstResult ?>–<?= $lastResult ?> dari <?= (int) $pagination['total'] ?> reservasi</small>
            <nav aria-label="Pagination reservasi">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= esc($pageUrl($pagination['page'] - 1)) ?>" hx-get="<?= esc($pageUrl($pagination['page'] - 1)) ?>" hx-select="#reservation-list-card" hx-target="#reservation-list-card" hx-swap="outerHTML">Sebelumnya</a>
                    </li>
                    <?php for ($pageNumber = 1; $pageNumber <= $pagination['totalPages']; $pageNumber++): ?>
                        <li class="page-item <?= $pageNumber === $pagination['page'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= esc($pageUrl($pageNumber)) ?>" hx-get="<?= esc($pageUrl($pageNumber)) ?>" hx-select="#reservation-list-card" hx-target="#reservation-list-card" hx-swap="outerHTML"><?= $pageNumber ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= esc($pageUrl($pagination['page'] + 1)) ?>" hx-get="<?= esc($pageUrl($pagination['page'] + 1)) ?>" hx-select="#reservation-list-card" hx-target="#reservation-list-card" hx-swap="outerHTML">Selanjutnya</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
