<?php
$sections = [
    'summary' => 'Ringkasan',
    'tickets' => 'Tiket Aktif',
    'schedules' => 'Jadwal Rutin',
    'assets' => 'Aset Kamar',
    'categories' => 'Kategori',
    'history' => 'Riwayat',
];
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <div class="d-flex flex-column gap-3">
            <div>
                <h3 class="h5 mb-1">Maintenance Kamar</h3>
                <p class="text-secondary small mb-0">Kelola tiket, kategori, dan perangkat yang terpasang di setiap kamar.</p>
            </div>
            <nav class="nav nav-pills page-tabs maintenance-tabs gap-2 flex-nowrap w-100" aria-label="Sub-menu maintenance">
                <?php foreach ($sections as $key => $label): ?>
                    <a class="nav-link flex-fill <?= $section === $key ? 'active' : 'bg-white' ?>"
                       href="<?= site_url('ops?tab=maintenance&section=' . $key) ?>"
                       hx-get="<?= site_url('ops/panel?tab=maintenance&section=' . $key) ?>"
                       hx-target="#ops-panel"
                       hx-sync="#ops-panel:replace"
                       hx-push-url="<?= site_url('ops?tab=maintenance&section=' . $key) ?>"
                       data-tab-link>
                        <?= esc($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>
</div>

<?php if ($section === 'summary'): ?>
    <?= view('components/maintenance/summary', ['summary' => $maintenanceSummary]) ?>
<?php elseif ($section === 'assets'): ?>
    <?= view('components/maintenance/assets', compact('items', 'rooms', 'categories', 'selectedRoomId')) ?>
<?php elseif ($section === 'schedules'): ?>
    <?= view('components/maintenance/schedules', compact('items', 'roomAssets', 'users', 'scheduleStats')) ?>
<?php elseif ($section === 'categories'): ?>
    <?= view('components/maintenance/categories', compact('items')) ?>
<?php elseif ($section === 'history'): ?>
    <?= view('components/maintenance/history', compact('items', 'rooms', 'categories', 'historyFilters')) ?>
<?php else: ?>
    <?= view('components/maintenance/tickets', compact('items', 'rooms', 'categories', 'roomAssets', 'users')) ?>
<?php endif; ?>
