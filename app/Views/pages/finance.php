<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <form class="d-flex flex-column flex-sm-row gap-2 min-width-0 flex-grow-1 flex-sm-grow-0" hx-get="<?= site_url('finance/panel') ?>" hx-target="#finance-panel">
            <input class="form-control" type="month" name="month" value="<?= esc($month) ?>" aria-label="Pilih bulan laporan">
            <button class="btn btn-primary">Tampilkan</button>
        </form>
        <?php if (! empty($isCombined)): ?>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fs-6 text-wrap lh-base">
                Cakupan: Homestay Mitra &amp; Kos Mitra
            </span>
        <?php endif; ?>
    </div>
</div>
<div id="finance-panel" hx-get="<?= site_url('finance/panel?month=' . $month) ?>" hx-trigger="load, opsChanged from:body" hx-swap="innerHTML"><div class="text-center text-secondary py-5">Memuat laporan...</div></div>
<?= $this->endSection() ?>
