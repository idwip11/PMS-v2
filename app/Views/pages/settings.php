<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div id="settings-feedback" class="mb-3"></div>
<div id="settings-panel"
     hx-get="<?= site_url('settings/panel') ?>"
     hx-trigger="load, settingsChanged from:body"
     hx-swap="innerHTML">
    <div class="text-center text-secondary py-5">Memuat pengaturan...</div>
</div>
<?= $this->endSection() ?>
