<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <form class="d-flex flex-column flex-sm-row gap-2" hx-get="<?= site_url('dashboard/metrics') ?>" hx-target="#dashboard-metrics" hx-push-url="<?= site_url('dashboard') ?>">
        <select class="form-select" name="guesthouse" aria-label="Filter Guesthouse">
            <option value="" <?= $guesthouse === null ? 'selected' : '' ?>>Semua GH</option>
            <?php for ($gh = 1; $gh <= 5; $gh++): ?>
                <option value="<?= $gh ?>" <?= $guesthouse === $gh ? 'selected' : '' ?>>GH <?= $gh ?></option>
            <?php endfor; ?>
        </select>
        <input class="form-control" type="month" name="month" value="<?= esc($month) ?>">
        <button class="btn btn-primary">Tampilkan</button>
    </form>
</div>
<?php $metricsQuery = http_build_query(array_filter(['month' => $month, 'guesthouse' => $guesthouse], static fn ($value) => $value !== null && $value !== '')); ?>
<div id="dashboard-metrics" hx-get="<?= site_url('dashboard/metrics?' . $metricsQuery) ?>" hx-trigger="load, dashboardChanged from:body" hx-swap="innerHTML">
    <div class="text-center text-secondary py-5">Memuat KPI...</div>
</div>
<?= $this->endSection() ?>
