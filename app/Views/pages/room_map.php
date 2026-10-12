<?php
$current = new DateTimeImmutable($month . '-01');
$previous = $current->modify('-1 month')->format('Y-m');
$next = $current->modify('+1 month')->format('Y-m');
$guesthouseQuery = $guesthouse !== null ? '&guesthouse=' . $guesthouse : '';
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="room-map-page">
    <div class="room-map-toolbar d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-3">
        <div class="d-flex flex-wrap gap-3 small">
            <span><i class="legend-dot bg-primary"></i> Lunas</span>
            <span><i class="legend-dot bg-warning"></i> DP</span>
            <span><i class="legend-dot bg-danger"></i> Belum Bayar</span>
            <span><i class="legend-dot legend-reconciled"></i> Terekonsiliasi</span>
            <span><i class="legend-checkin">✓</i> <?= (\App\Libraries\PropertyPricing::active()['pricing_mode'] === 'room_monthly') ? 'Sudah Masuk' : 'Sudah Check-in' ?></span>
            <span><i class="legend-checkout">✓</i> <?= (\App\Libraries\PropertyPricing::active()['pricing_mode'] === 'room_monthly') ? 'Sudah Keluar' : 'Sudah Check-out' ?></span>
        </div>
        <div class="room-map-controls d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group shadow-sm" role="group" aria-label="Navigasi bulan">
                <a id="room-map-previous" class="btn btn-light border" href="<?= site_url('room-map?month=' . $previous . $guesthouseQuery) ?>"
                   hx-get="<?= site_url('room-map/grid?month=' . $previous . $guesthouseQuery) ?>" hx-target="#room-map-grid" hx-push-url="<?= site_url('room-map?month=' . $previous . $guesthouseQuery) ?>">&larr;</a>
                <span id="month-label" class="btn btn-light border disabled px-4"><?= esc($current->format('F Y')) ?></span>
                <a id="room-map-next" class="btn btn-light border" href="<?= site_url('room-map?month=' . $next . $guesthouseQuery) ?>"
                   hx-get="<?= site_url('room-map/grid?month=' . $next . $guesthouseQuery) ?>" hx-target="#room-map-grid" hx-push-url="<?= site_url('room-map?month=' . $next . $guesthouseQuery) ?>">&rarr;</a>
            </div>
            <?php if (\App\Libraries\PropertyContext::activeId() === \App\Libraries\PropertyContext::HOMIQ_ID): ?>
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-secondary" for="guesthouse-filter">Guesthouse</label>
                <select id="guesthouse-filter" class="form-select room-map-gh-filter" name="guesthouse"
                        hx-get="<?= site_url('room-map/grid?month=' . $month) ?>"
                        hx-target="#room-map-grid">
                    <option value="" <?= $guesthouse === null ? 'selected' : '' ?>>Semua GH</option>
                    <?php for ($gh = 1; $gh <= 5; $gh++): ?>
                        <option value="<?= $gh ?>" <?= $guesthouse === $gh ? 'selected' : '' ?>>GH_<?= $gh ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div id="room-map-grid"
         hx-headers='{"Cache-Control":"no-cache","Pragma":"no-cache"}'
         hx-get="<?= site_url('room-map/grid?month=' . $month . $guesthouseQuery) ?>"
         hx-trigger="load, reservationChanged from:body"
         hx-swap="innerHTML">
        <div class="text-center text-secondary py-5">Memuat room map...</div>
    </div>
</div>
<?= $this->endSection() ?>
