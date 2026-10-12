<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
        <span class="badge text-bg-warning mb-3">Dalam persiapan</span>
        <h2 class="h4 mb-2"><?= esc((string) session('activePropertyName')) ?></h2>
        <p class="text-secondary mb-0">Akses Anda ke properti ini sudah tercatat dengan role <strong><?= esc((string) session('role')) ?></strong>. Data dan menu operasionalnya akan tersedia setelah pembaruan modul multi properti selesai.</p>
    </div>
</div>
<?= $this->endSection() ?>
