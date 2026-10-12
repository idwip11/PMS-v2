<?php
use App\Libraries\RoleAccess;
use App\Libraries\PropertyContext;

$role = (string) session('role');
$canDashboard = RoleAccess::allows($role, 'dashboard');
$canFinance = RoleAccess::allows($role, 'finance');
$canCompanyFinance = RoleAccess::allows($role, 'company_finance');
$canOwnerFinance = RoleAccess::allows($role, 'owner_finance');
$canAnalytics = RoleAccess::allows($role, 'analytics');
$canOperations = RoleAccess::allows($role, 'operations');
$canFrontDesk = RoleAccess::allows($role, 'front_desk');
$canHousekeeping = RoleAccess::allows($role, 'housekeeping');
$canManageSettings = RoleAccess::allows($role, 'settings');
$canSupport = RoleAccess::allows($role, 'support');
$lastDataUpdate = $lastDataUpdate ?? null;
$lastDataUpdateAt = isset($lastDataUpdate['updated_at']) ? strtotime((string) $lastDataUpdate['updated_at']) : false;
$uri = trim(service('uri')->getPath(), '/');
$financePaths = ['finance', 'finance/company', 'finance/owner', 'finance/owner-setup'];
$financeActive = in_array($uri, $financePaths, true) || str_starts_with($uri, 'finance/');
$frontDeskPaths = ['room-map', 'reservations', 'guests', 'housekeeping', 'room-pricing'];
$frontDeskActive = in_array($uri, $frontDeskPaths, true) || in_array(explode('/', $uri)[0] ?? '', $frontDeskPaths, true);
$icon = static function (string $name): string {
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'finance' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M16 11h5v4h-5a2 2 0 0 1 0-4Z"/><path d="M5 6V4h12v2"/>',
        'property-finance' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'company-finance' => '<path d="M3 21h18M5 21V8l7-4 7 4v13M9 11h2M13 11h2M9 15h2M13 15h2"/>',
        'owner-finance' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0M16 11h5M18.5 8.5v5"/>',
        'marketing' => '<path d="m3 11 14-6v14L3 13Z"/><path d="M7 14v5a2 2 0 0 0 2 2h2v-6"/><path d="M20 9v6"/>',
        'operations' => '<path d="M14.7 6.3a4 4 0 0 0-5 5L3 18l3 3 6.7-6.7a4 4 0 0 0 5-5l-2.4 2.4-3-3Z"/>',
        'front-desk' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 21h8M12 16v5"/>',
        'room-map' => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3Z"/><path d="M9 3v15M15 6v15"/>',
        'reservations' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h5"/>',
        'guests' => '<circle cx="9" cy="8" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 4.5a4 4 0 0 1 0 7.5M17 15a6 6 0 0 1 4 5.7"/>',
        'housekeeping' => '<path d="m15 3 6 6-8 8-6-6Z"/><path d="m7 11-4 8 8-4M4.5 16.5l3 3"/>',
        'pricing' => '<path d="M20 13 13 20l-9-9V4h7Z"/><circle cx="8.5" cy="8.5" r="1"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
        'support' => '<circle cx="12" cy="12" r="9"/><path d="M8.5 9a3.5 3.5 0 0 1 7 0c0 2.5-3.5 2.5-3.5 5M12 18h.01"/>',
        'close' => '<path d="M18 6 6 18M6 6l12 12"/>',
    ];

    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? '') . '</svg>';
};
$menu = $canDashboard ? [['dashboard', 'Dashboard', 'dashboard']] : [];
?>
<aside id="app-sidebar" class="sidebar bg-white border-end d-flex flex-column p-3" aria-label="Navigasi utama">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <a class="navbar-brand fw-bold fs-4 text-primary mb-0" href="<?= site_url(PropertyContext::landingPath()) ?>">HomiQ <span class="text-dark">PMS</span></a>
        <!-- Close button visible on mobile/tablet -->
        <button class="sidebar-close-btn btn btn-sm btn-light d-xl-none" id="sidebar-close-btn" aria-label="Tutup menu" type="button">
            <span class="sidebar-nav-icon"><?= $icon('close') ?></span>
        </button>
    </div>
    <nav class="nav nav-pills flex-column gap-1 flex-grow-1">
        <?php if (! PropertyContext::frontDeskReady()): ?>
            <a class="nav-link sidebar-nav-link active" href="<?= site_url('property-pending') ?>">Properti dalam persiapan</a>
        <?php else: ?>
        <?php foreach ($menu as [$path, $label, $menuIcon]): ?>
            <a class="nav-link sidebar-nav-link <?= $uri === $path ? 'active' : 'link-dark' ?>" href="<?= site_url($path) ?>"><span class="sidebar-nav-icon"><?= $icon($menuIcon) ?></span><span><?= esc($label) ?></span></a>
        <?php endforeach; ?>
        <?php if ($canFinance || $canOwnerFinance || $canCompanyFinance): ?>
            <details class="sidebar-group" <?= $financeActive ? 'open' : '' ?>>
                <summary class="sidebar-group-title <?= $financeActive ? 'is-active' : '' ?>">
                    <span class="sidebar-nav-icon"><?= $icon('finance') ?></span>
                    <span>Finance</span>
                    <span class="sidebar-chevron" aria-hidden="true">⌄</span>
                </summary>
                <div class="sidebar-submenu">
                    <?php if ($canFinance): ?>
                    <a class="sidebar-submenu-link sidebar-nav-link <?= $uri === 'finance' ? 'active' : '' ?>" href="<?= site_url('finance') ?>"><span class="sidebar-nav-icon"><?= $icon('property-finance') ?></span><span>Keuangan Properti</span></a>
                    <?php endif; ?>
                    <?php if ($canCompanyFinance): ?>
                    <a class="sidebar-submenu-link sidebar-nav-link <?= ($uri === 'finance/company' || str_starts_with($uri, 'finance/company/')) ? 'active' : '' ?>" href="<?= site_url('finance/company') ?>"><span class="sidebar-nav-icon"><?= $icon('company-finance') ?></span><span>Keuangan Perusahaan</span></a>
                    <?php endif; ?>
                    <?php if ($canFinance || $canOwnerFinance): ?>
                    <a class="sidebar-submenu-link sidebar-nav-link <?= ($uri === 'finance/owner' || (str_starts_with($uri, 'finance/owner/') && ! str_starts_with($uri, 'finance/owner-setup'))) ? 'active' : '' ?>" href="<?= site_url('finance/owner') ?>"><span class="sidebar-nav-icon"><?= $icon('owner-finance') ?></span><span>Keuangan Owner/Investor</span></a>
                    <?php endif; ?>
                    <?php if ($canFinance): ?>
                    <a class="sidebar-submenu-link sidebar-nav-link <?= ($uri === 'finance/owner-setup' || str_starts_with($uri, 'finance/owner-setup/')) ? 'active' : '' ?>" href="<?= site_url('finance/owner-setup') ?>"><span class="sidebar-nav-icon"><?= $icon('owner-finance') ?></span><span>Setup Owner</span></a>
                    <?php endif; ?>
                </div>
            </details>
        <?php endif; ?>
        <?php if ($canAnalytics): ?>
            <a class="nav-link sidebar-nav-link <?= $uri === 'marketing' ? 'active' : 'link-dark' ?>" href="<?= site_url('marketing') ?>"><span class="sidebar-nav-icon"><?= $icon('marketing') ?></span><span>Analytics</span></a>
        <?php endif; ?>
        <?php if ($canOperations): ?>
            <a class="nav-link sidebar-nav-link <?= $uri === 'ops' ? 'active' : 'link-dark' ?>" href="<?= site_url('ops') ?>"><span class="sidebar-nav-icon"><?= $icon('operations') ?></span><span>Operasional</span></a>
        <?php endif; ?>
        <?php if ($canFrontDesk || $canHousekeeping): ?>
        <details class="sidebar-group" <?= $frontDeskActive ? 'open' : '' ?>>
            <summary class="sidebar-group-title <?= $frontDeskActive ? 'is-active' : '' ?>">
                <span class="sidebar-nav-icon"><?= $icon('front-desk') ?></span>
                <span>Front Desk</span>
                <span class="sidebar-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="sidebar-submenu">
                <?php if ($canFrontDesk): ?>
                <a class="sidebar-submenu-link sidebar-nav-link <?= ($uri === 'room-map' || str_starts_with($uri, 'room-map/')) ? 'active' : '' ?>" href="<?= site_url('room-map') ?>"><span class="sidebar-nav-icon"><?= $icon('room-map') ?></span><span>Room Map</span></a>
                <a class="sidebar-submenu-link sidebar-nav-link <?= ($uri === 'reservations' || str_starts_with($uri, 'reservations/')) ? 'active' : '' ?>" href="<?= site_url('reservations') ?>"><span class="sidebar-nav-icon"><?= $icon('reservations') ?></span><span>Reservasi List</span></a>
                <a class="sidebar-submenu-link sidebar-nav-link <?= ($uri === 'guests' || str_starts_with($uri, 'guests/')) ? 'active' : '' ?>" href="<?= site_url('guests') ?>"><span class="sidebar-nav-icon"><?= $icon('guests') ?></span><span>Data Tamu</span></a>
                <?php endif; ?>
                <?php if ($canHousekeeping): ?>
                <a class="sidebar-submenu-link sidebar-nav-link <?= ($uri === 'housekeeping' || str_starts_with($uri, 'housekeeping/')) ? 'active' : '' ?>" href="<?= site_url('housekeeping') ?>"><span class="sidebar-nav-icon"><?= $icon('housekeeping') ?></span><span>Housekeeping</span></a>
                <?php endif; ?>
                <?php if ($canFrontDesk): ?><a class="sidebar-submenu-link sidebar-nav-link <?= ($uri === 'room-pricing' || str_starts_with($uri, 'room-pricing/')) ? 'active' : '' ?>" href="<?= site_url('room-pricing') ?>"><span class="sidebar-nav-icon"><?= $icon('pricing') ?></span><span>Harga Kamar</span></a><?php endif; ?>
            </div>
        </details>
        <?php endif; ?>
        <hr>
        <?php if ($canManageSettings): ?>
            <a class="nav-link sidebar-nav-link <?= ($uri === 'settings' || str_starts_with($uri, 'settings/')) ? 'active' : 'link-dark' ?>" href="<?= site_url('settings') ?>"><span class="sidebar-nav-icon"><?= $icon('settings') ?></span><span>Settings</span></a>
        <?php endif; ?>
        <?php if ($canSupport): ?><a class="nav-link sidebar-nav-link <?= ($uri === 'support' || str_starts_with($uri, 'support/')) ? 'active' : 'link-dark' ?>" href="<?= site_url('support') ?>"><span class="sidebar-nav-icon"><?= $icon('support') ?></span><span>Support</span></a><?php endif; ?>
        <div class="border-top mt-3 pt-3 px-2 text-secondary small">
            <?php if ($lastDataUpdate !== null && $lastDataUpdateAt !== false): ?>
                <div>Terakhir diperbarui oleh</div>
                <div class="fw-semibold text-dark"><?= esc((string) $lastDataUpdate['user_name']) ?></div>
                <div><?= esc(date('d/m/Y H:i', $lastDataUpdateAt)) ?> WITA</div>
            <?php else: ?>
                <div>Belum ada pembaruan data.</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </nav>
    <div class="border-top pt-3">
        <div class="fw-semibold small"><?= esc((string) session('fullName')) ?></div>
        <div class="text-secondary small mb-2"><?= esc((string) session('role')) ?></div>
        <form method="post" action="<?= site_url('logout') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger btn-sm w-100">Logout</button>
        </form>
    </div>
</aside>
