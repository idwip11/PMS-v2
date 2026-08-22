<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$tabs = [
    'overview' => 'Overview',
    'expenses' => 'Expenses',
    'inventory' => 'Amenities',
    'extra_beds' => 'Extra Bed',
    'maintenance' => 'Maintenance',
    'shifts' => 'Shifts',
];
?>
<nav class="nav nav-pills page-tabs gap-2 mb-3" aria-label="Tab operasional">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="nav-link <?= $tab === $key ? 'active' : 'bg-white' ?>"
           href="<?= site_url('ops?tab=' . $key) ?>"
           hx-get="<?= site_url('ops/panel?tab=' . $key) ?>"
           hx-target="#ops-panel"
           hx-sync="#ops-panel:replace"
           hx-push-url="<?= site_url('ops?tab=' . $key) ?>"
           data-tab-link>
            <?= esc($label) ?>
        </a>
    <?php endforeach; ?>
</nav>

<div id="ops-feedback" class="mb-3"></div>
<div id="ops-panel"
     hx-get="<?= esc($panelUrl ?? site_url('ops/panel?tab=' . $tab . ($tab === 'maintenance' ? '&section=' . $maintenanceSection : ($tab === 'inventory' ? '&inventory_section=' . $inventorySection . ($inventorySection === 'history' ? '&page=' . $page : '') : ''))), 'attr') ?>"
     data-panel-url="<?= site_url('ops/panel') ?>"
     hx-trigger="load"
     hx-sync="this:replace"
     hx-swap="innerHTML">
    <div class="text-center text-secondary py-5">Memuat data operasional...</div>
</div>
<?= $this->endSection() ?>
