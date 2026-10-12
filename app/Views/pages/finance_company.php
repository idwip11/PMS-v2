<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-5 text-center">
        <div class="mb-3">
            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fs-6 fw-semibold">Segera Hadir</span>
        </div>
        <h2 class="h3 fw-bold mb-2">Keuangan Perusahaan</h2>
        <p class="text-secondary mx-auto mb-4" style="max-width: 540px;">
            Modul laporan keuangan perusahaan sedang dipersiapkan untuk konsolidasi finansial, laba rugi korporasi, dan arus kas antar seluruh unit properti.
        </p>
        <div class="d-inline-flex gap-2 text-start p-3 bg-light rounded-3 border">
            <div class="text-primary me-2">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
            </div>
            <div class="small text-secondary">
                Fitur ini akan menyajikan rekapan keuangan menyeluruh (Holding / Company Level). Untuk saat ini, laporan per unit dapat diakses melalui menu <a href="<?= site_url('finance') ?>" class="text-primary fw-medium text-decoration-none">Keuangan Properti</a>.
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
