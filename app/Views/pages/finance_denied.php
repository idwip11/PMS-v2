<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center my-5">
    <div class="col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm text-center p-4">
            <div class="card-body">
                <div class="text-warning mb-3">
                    <svg width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                        <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
                    </svg>
                </div>
                <h4 class="h5 fw-semibold mb-2"><?= esc($pageTitle ?? 'Akses Laporan Dibatasi') ?></h4>
                <p class="text-secondary mb-4"><?= esc($errorMessage ?? 'Anda belum memiliki izin lengkap untuk mengakses laporan ini.') ?></p>
                <a href="<?= site_url('dashboard') ?>" class="btn btn-outline-primary px-4">Kembali ke Dashboard</a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
