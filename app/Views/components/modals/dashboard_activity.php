<?php
$isMonthly = \App\Libraries\PropertyPricing::active()['pricing_mode'] === 'room_monthly';
$isCheckIn = $type === 'check-in';
$isH7 = $type === 'lease-h7';
$isMonth = $type === 'lease-month';

if ($isH7) {
    $title = "Jatuh Tempo Sewa H-7 (Segera)";
} elseif ($isMonth) {
    $title = "Jatuh Tempo Sewa Bulan Ini";
} else {
    $title = $isMonthly
        ? ($isCheckIn ? "Penyewa Masuk Hari Ini" : "Penyewa Keluar Hari Ini")
        : ($isCheckIn ? "Today's Check-ins" : "Today's Check-outs");
}
?>
<div class="modal fade" id="dynamicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="dashboard-activity-icon <?= $isH7 ? 'is-expiring-h7' : ($isMonth ? 'is-expiring-month' : ($isCheckIn ? 'is-checkin' : 'is-checkout')) ?>" aria-hidden="true"><?= $isH7 ? '⏳' : ($isMonth ? '📅' : ($isCheckIn ? '→' : '←')) ?></span>
                    <div><h2 class="modal-title h5 mb-0"><?= esc($title) ?></h2><small class="text-secondary"><?= esc(date('d/m/Y', strtotime($today))) ?> · <?= count($items) ?> <?= $isMonthly ? 'penyewa' : 'tamu' ?></small></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <?php if ($items === []): ?>
                    <div class="text-center text-secondary py-5">
                        <?php if ($isH7): ?>
                            Tidak ada masa sewa yang habis dalam 7 hari ke depan.
                        <?php elseif ($isMonth): ?>
                            Tidak ada masa sewa yang jatuh tempo pada bulan ini.
                        <?php else: ?>
                            Tidak ada <?= $isMonthly ? ($isCheckIn ? 'penyewa masuk' : 'penyewa keluar') : ($isCheckIn ? 'check-in' : 'check-out') ?> hari ini.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="dashboard-activity-modal-list">
                        <?php foreach ($items as $item): ?>
                            <?php
                            $words = preg_split('/\s+/', trim((string) $item['guest_name'])) ?: [];
                            $initials = strtoupper(substr($words[0] ?? '', 0, 1) . substr($words[1] ?? '', 0, 1));
                            ?>
                            <button class="dashboard-activity-modal-item" type="button" hx-get="<?= site_url('reservations/' . $item['id']) ?>" hx-target="#modal-container" hx-swap="innerHTML">
                                <span class="dashboard-guest-initials"><?= esc($initials ?: '?') ?></span>
                                <span class="flex-grow-1 min-width-0 text-start">
                                    <strong class="d-block text-truncate"><?= esc($item['guest_name']) ?></strong>
                                    <small class="text-secondary">
                                        <?= esc($item['room_label'] ?? ('Kamar ' . $item['room_number'])) ?>
                                        <?php if (!empty($item['check_out_date'])): ?>
                                            · Berakhir: <?= esc(date('d/m/Y', strtotime($item['check_out_date']))) ?>
                                        <?php endif; ?>
                                        <?= $item['source'] ? ' · ' . esc($item['source']) : '' ?>
                                    </small>
                                </span>
                                <?php if ((float) $item['outstanding'] > 0): ?><span class="badge text-bg-danger">Rp<?= number_format((float) $item['outstanding'], 0, ',', '.') ?></span><?php else: ?><span class="badge text-bg-success">Lunas</span><?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
