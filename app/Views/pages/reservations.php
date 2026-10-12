<?php
$pricingMode = \App\Libraries\PropertyPricing::active()['pricing_mode'];
$isMonthly = $pricingMode === 'room_monthly';
$isHomestay = $pricingMode === 'whole_home_nightly'
    || \App\Libraries\PropertyContext::activeId() === 2
    || (string) session('activePropertyCode') === 'HOMESTAY_MITRA';
$statusLabels = [
    'Confirmed' => 'Terkonfirmasi',
    'Checked_In' => $isMonthly ? 'Masuk' : 'Check-in',
    'Checked_Out' => $isMonthly ? 'Keluar' : 'Check-out',
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
$sourceLabels = $isMonthly ? [
    'Direct' => 'Direct',
    'Online' => 'Online',
] : [
    'Direct' => 'Direct',
    'Booking.com' => 'Booking.com',
    'Agoda' => 'Agoda',
    'Traveloka' => 'Traveloka',
    'Airbnb' => 'Airbnb',
    'Tiket.com' => 'Tiket.com',
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
            <?php if ($isHomiq): ?>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="reservation-guesthouse">Guesthouse</label>
                <select id="reservation-guesthouse" class="form-select" name="guesthouse">
                    <option value="">Semua GH</option>
                    <?php for ($guesthouse = 1; $guesthouse <= 5; $guesthouse++): ?>
                        <option value="<?= $guesthouse ?>" <?= (int) $filters['guesthouse'] === $guesthouse ? 'selected' : '' ?>>GH <?= $guesthouse ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endif; ?>
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
                <label class="form-label" for="reservation-source">Sumber</label>
                <select id="reservation-source" class="form-select" name="source">
                    <option value="">Semua sumber</option>
                    <?php foreach ($sourceLabels as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= $filters['source'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="w-100 d-none d-xl-block"></div>
            <div class="col-sm-6 col-xl-3">
                <label class="form-label" for="check-in-from"><?= $isMonthly ? 'Tanggal masuk dari' : 'Check-in dari' ?></label>
                <input id="check-in-from" class="form-control" type="date" name="check_in_from" value="<?= esc($filters['check_in_from']) ?>">
            </div>
            <div class="col-sm-6 col-xl-3">
                <label class="form-label" for="check-in-to">Sampai</label>
                <input id="check-in-to" class="form-control" type="date" name="check_in_to" value="<?= esc($filters['check_in_to']) ?>">
            </div>
            <div class="col-sm-6 col-xl-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1">Tampilkan</button>
                <a class="btn btn-outline-secondary" href="<?= site_url('reservations') ?>">Reset</a>
            </div>
        </form>
    </div>
</div>

<div id="reservation-list-card" class="card border-0 shadow-sm overflow-hidden" hx-headers='{"Cache-Control":"no-cache","Pragma":"no-cache"}' hx-get="<?= site_url('reservations?' . http_build_query($filters)) ?>" hx-trigger="reconciliationChanged from:body, reservationChanged from:body" hx-select="#reservation-list-card" hx-target="this" hx-swap="outerHTML">
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
                <tr>
                    <th>ID Reservasi</th>
                    <th>Status</th>
                    <th><?= $isMonthly ? 'Nama Penyewa' : 'Nama Tamu' ?></th>
                    <th><?= $isHomiq ? 'Guesthouse' : 'Properti' ?></th>
                    <th><?= $isHomestay ? 'Homestay' : 'Kamar' ?></th>
                    <th>Sumber</th>
                    <th>Catatan</th>
                    <th><?= $isMonthly ? 'Tanggal Masuk' : 'Check-in' ?></th>
                    <th><?= $isMonthly ? 'Tanggal Keluar' : 'Check-out' ?></th>
                    <?php if ($isMonthly): ?>
                        <th>Lama Sewa</th>
                        <th>Sisa Sewa</th>
                    <?php endif; ?>
                    <th class="text-end">Total</th>
                    <th>Pembayaran</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reservations === []): ?>
                    <tr><td colspan="<?= $isMonthly ? 14 : 12 ?>" class="text-center text-secondary py-5">Belum ada reservasi sesuai filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($reservations as $reservation): ?>
                        <?php
                        $total = (float) $reservation['total_amount'];
                        $paid = (float) $reservation['paid_amount'];
                        $paymentLabel = $paid >= $total && $total > 0 ? 'Lunas' : ($paid > 0 ? 'DP' : 'Belum bayar');
                        $paymentClass = $paid >= $total && $total > 0 ? 'text-success' : ($paid > 0 ? 'text-warning-emphasis' : 'text-danger');
                        $isReconciled = (int) ($reservation['is_reconciled'] ?? 0) === 1;

                        if ($isMonthly) {
                            $durationMonths = \App\Libraries\PropertyPricing::wholeMonths((string) $reservation['check_in_date'], (string) $reservation['check_out_date']);
                            if ($durationMonths === null) {
                                try {
                                    $cIn = new DateTimeImmutable((string) $reservation['check_in_date']);
                                    $cOut = new DateTimeImmutable((string) $reservation['check_out_date']);
                                    $durationMonths = max(1, (int) round($cIn->diff($cOut)->days / 30));
                                } catch (\Throwable $e) {
                                    $durationMonths = 1;
                                }
                            }

                            $today = new DateTimeImmutable('today');
                            try {
                                $checkOutDate = new DateTimeImmutable((string) $reservation['check_out_date']);
                                $diffDays = (int) $today->diff($checkOutDate)->format('%r%a');
                            } catch (\Throwable $e) {
                                $diffDays = null;
                            }
                        }

                        $rawPhone = trim((string) ($reservation['phone'] ?? ''));
                        $digits = preg_replace('/\D+/', '', $rawPhone);
                        $waPhone = '';
                        if ($digits !== '') {
                            if (str_starts_with($digits, '62')) {
                                $waPhone = $digits;
                            } elseif (str_starts_with($digits, '0')) {
                                $waPhone = '62' . substr($digits, 1);
                            } elseif (str_starts_with($digits, '8')) {
                                $waPhone = '62' . $digits;
                            } else {
                                $waPhone = $digits;
                            }
                        }
                        $propName = session('activePropertyName') ?: ($isMonthly ? 'Kos Mitra' : 'HomiQ');
                        $topicWord = $isMonthly ? 'sewa' : 'reservasi';
                        $unitWord = $isHomestay ? 'Homestay Mitra' : 'Kamar ' . trim((string) $reservation['room_number']);
                        $waText = "Halo Kak " . trim((string) $reservation['guest_name']) . ", kami dari manajemen " . $propName . " terkait " . $topicWord . " " . $unitWord . " (ID: " . trim((string) $reservation['order_id']) . ").";
                        $waLink = $waPhone !== '' ? 'https://wa.me/' . $waPhone . '?text=' . rawurlencode($waText) : '';
                        ?>
                        <tr class="<?= $isReconciled ? 'reservation-row-reconciled' : '' ?>">
                            <td class="fw-semibold text-nowrap"><?= esc($reservation['order_id']) ?><?= $isReconciled ? '<span class="reconciled-marker ms-1">★ Rekonsiliasi</span>' : '' ?></td>
                            <td><span class="badge <?= esc($statusClasses[$reservation['status']] ?? 'text-bg-secondary') ?>"><?= esc($statusLabels[$reservation['status']] ?? $reservation['status']) ?></span></td>
                            <td><div class="fw-semibold"><?= esc($reservation['guest_name']) ?></div><small class="text-secondary"><?= esc($reservation['phone'] ?: '-') ?></small></td>
                            <td class="text-nowrap"><?= $isHomiq ? 'GH ' . esc($reservation['floor']) : esc((string) session('activePropertyName')) ?></td>
                            <td class="text-nowrap"><?= $isHomestay ? '1 Rumah' : esc($reservation['room_number']) ?><small class="d-block text-secondary"><?= $isHomestay ? 'Seluruh Rumah' : esc($reservation['room_type']) ?></small></td>
                            <td><?= esc($reservation['source'] ?: 'Direct') ?><?php if (! empty($reservation['agent_name'])): ?><span class="badge text-bg-primary ms-1 mt-1" title="Tamu dari agen <?= esc($reservation['agent_name']) ?>">🤝 Agen</span><small class="d-block text-secondary"><?= esc($reservation['agent_name']) ?></small><?php endif; ?></td>
                            <td class="small" style="min-width: 220px; max-width: 280px"><?= ! empty($reservation['important_note']) ? nl2br(esc($reservation['important_note'])) : '<span class="text-secondary">—</span>' ?></td>
                            <td class="text-nowrap"><?= esc(date('d/m/Y', strtotime($reservation['check_in_date']))) ?></td>
                            <td class="text-nowrap"><?= esc(date('d/m/Y', strtotime($reservation['check_out_date']))) ?></td>
                            <?php if ($isMonthly): ?>
                                <td class="text-nowrap">
                                    <span class="badge text-bg-light border text-dark fw-semibold"><?= $durationMonths ?> Bulan</span>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($reservation['status'] === 'Checked_Out'): ?>
                                        <span class="badge text-bg-secondary">Selesai</span>
                                    <?php elseif (in_array($reservation['status'], ['Cancelled', 'No_Show'], true)): ?>
                                        <span class="text-secondary">—</span>
                                    <?php elseif ($diffDays === null): ?>
                                        <span class="text-secondary">—</span>
                                    <?php elseif ($diffDays > 7): ?>
                                        <span class="badge text-bg-success-subtle text-success border border-success-subtle fw-semibold"><?= $diffDays ?> hari</span>
                                    <?php elseif ($diffDays >= 1): ?>
                                        <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold"><?= $diffDays ?> hari</span>
                                    <?php elseif ($diffDays === 0): ?>
                                        <span class="badge text-bg-danger text-white fw-semibold">Hari ini</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger text-white fw-semibold">Lewat <?= abs($diffDays) ?> hari</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="text-end text-nowrap">Rp<?= number_format($total, 0, ',', '.') ?></td>
                            <td><span class="<?= esc($paymentClass) ?> text-nowrap"><?= esc($paymentLabel) ?></span></td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                    <?php if (! empty($waLink)): ?>
                                        <a class="btn btn-sm text-white text-nowrap d-inline-flex align-items-center gap-1" style="background-color: #25D366; border-color: #22bf5b;" href="<?= esc($waLink) ?>" target="_blank" rel="noopener noreferrer" title="Chat WhatsApp <?= esc($reservation['guest_name']) ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16"><path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.707 1.92 1.804 3.376c.114.148 1.93 2.946 4.678 4.129.654.282 1.164.45 1.564.577.658.21 1.258.18 1.733.11.53-.079 1.628-.667 1.859-1.312.23-.646.23-1.2.16-1.313-.069-.113-.247-.181-.444-.28"/></svg>
                                            <span>WA</span>
                                        </a>
                                    <?php elseif ($isMonthly): ?>
                                        <button class="btn btn-sm btn-outline-secondary opacity-50" type="button" disabled title="Nomor WhatsApp tidak tersedia">WA</button>
                                    <?php endif; ?>
                                    <?php if ($isMonthly && in_array($reservation['status'], ['Confirmed', 'Checked_In'], true)): ?>
                                        <button class="btn btn-sm btn-outline-success text-nowrap"
                                                type="button"
                                                hx-get="<?= esc(site_url('reservations/' . $reservation['id'] . '/extend')) ?>"
                                                hx-target="#modal-container"
                                                hx-swap="innerHTML"
                                                title="Perpanjang sewa Room <?= esc($reservation['room_number']) ?>">
                                            Extend
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-primary btn-sm" type="button" data-reservation-detail="<?= esc(site_url('reservations/' . $reservation['id'])) ?>">Detail</button>
                                </div>
                            </td>
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
