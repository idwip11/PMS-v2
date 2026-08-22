<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <form class="d-flex gap-2" hx-get="<?= site_url('finance/panel') ?>" hx-target="#finance-panel">
        <input class="form-control" type="month" name="month" value="<?= esc($month) ?>">
        <button class="btn btn-primary">Tampilkan</button>
    </form>
</div>
<div id="finance-panel" hx-get="<?= site_url('finance/panel?month=' . $month) ?>" hx-trigger="load, opsChanged from:body" hx-swap="innerHTML"><div class="text-center text-secondary py-5">Memuat laporan...</div></div>
<?= $this->endSection() ?>
