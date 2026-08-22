<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div class="d-flex flex-column flex-sm-row gap-3">
        <div>
        <label class="form-label mb-1" for="housekeeping-filter">Guesthouse</label>
        <select id="housekeeping-filter" class="form-select" name="guesthouse"
                aria-label="Filter Guesthouse"
                hx-get="<?= site_url('housekeeping/panel') ?>"
                hx-trigger="change"
                hx-target="#housekeeping-panel"
                hx-swap="innerHTML"
                hx-include="#housekeeping-filter, #housekeeping-status-filter">
            <option value="" <?= $guesthouse === null ? 'selected' : '' ?>>Semua GH</option>
            <?php for ($gh = 1; $gh <= 5; $gh++): ?>
                <option value="<?= $gh ?>" <?= $guesthouse === $gh ? 'selected' : '' ?>>GH <?= $gh ?></option>
            <?php endfor; ?>
        </select>
        </div>
        <div>
        <label class="form-label mb-1" for="housekeeping-status-filter">Status Kamar</label>
        <select id="housekeeping-status-filter" class="form-select" name="status"
                aria-label="Filter Status Kamar"
                hx-get="<?= site_url('housekeeping/panel') ?>"
                hx-trigger="change"
                hx-target="#housekeeping-panel"
                hx-swap="innerHTML"
                hx-include="#housekeeping-filter, this">
            <option value="" <?= $status === null ? 'selected' : '' ?>>Semua Status</option>
            <?php foreach (['Available', 'Occupied', 'Dirty', 'Maintenance'] as $roomStatus): ?>
                <option value="<?= esc($roomStatus) ?>" <?= $status === $roomStatus ? 'selected' : '' ?>><?= esc($roomStatus) ?></option>
            <?php endforeach; ?>
        </select>
        </div>
    </div>
</div>
<div id="housekeeping-feedback" class="mb-3"></div>
<div id="housekeeping-panel"
     hx-get="<?= site_url('housekeeping/panel') ?>"
     hx-trigger="load, housekeepingChanged from:body"
     hx-include="#housekeeping-filter, #housekeeping-status-filter"
     hx-swap="innerHTML">
    <div class="text-center text-secondary py-5">Memuat data housekeeping...</div>
</div>
<?= $this->endSection() ?>
