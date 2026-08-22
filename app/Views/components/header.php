<?php
use App\Libraries\RoleAccess;

$role = (string) session('role');
$canCreateBooking = RoleAccess::allows($role, 'front_desk');
?>
<header class="app-header bg-white border-bottom px-3 px-lg-4 py-3 d-flex align-items-center gap-3">
    <!-- Hamburger toggle — always visible on non-desktop -->
    <button class="sidebar-toggle-btn btn btn-sm btn-light d-lg-none flex-shrink-0" id="sidebar-toggle-btn" aria-label="Toggle menu" type="button">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>

    <div class="min-width-0 flex-grow-1">
        <h1 class="h4 mb-0"><?= esc($pageTitle ?? 'Dashboard') ?></h1>
        <small class="text-secondary d-none d-sm-block"><?= esc($pageDescription ?? 'Property Management System') ?></small>
    </div>

    <div class="app-header-meta d-none d-xl-flex align-items-center gap-3 gap-lg-4 ms-auto">
        <div class="app-clock text-lg-end fw-semibold" data-wib-clock>Memuat waktu...</div>
        <div class="app-property d-flex align-items-center gap-3">
            <span class="app-property-icon" aria-hidden="true">GH</span>
            <div class="lh-sm">
                <span class="d-block fw-semibold">Adiputra Guesthouse</span>
                <span class="d-block">by HomiQ</span>
            </div>
        </div>
    </div>

    <!-- Clock visible on tablet only -->
    <div class="app-clock fw-semibold d-none d-sm-block d-xl-none text-end text-truncate" style="max-width: 200px; font-size: .82rem;" data-wib-clock>Memuat waktu...</div>

    <div class="d-flex align-items-center gap-2 flex-shrink-0">
        <?php if ($canCreateBooking): ?>
        <button class="btn btn-primary text-nowrap"
                hx-get="<?= site_url('reservations/new') ?>"
                hx-target="#modal-container"
                hx-swap="innerHTML">+ New Booking</button>
        <?php endif; ?>
    </div>
</header>
