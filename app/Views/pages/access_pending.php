<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm"><div class="card-body p-5 text-center"><h2 class="h3 mb-2">Akses belum diatur</h2><p class="text-secondary mb-0">Role <?= esc((string) session('role')) ?> sudah terdaftar, tetapi hak akses menu belum ditentukan. Hubungi administrator.</p></div></div>
<?= $this->endSection() ?>
