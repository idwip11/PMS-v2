<?php
use App\Libraries\RoleAccess;
use App\Libraries\PropertyContext;

$role = (string) session('role');
$canCreateBooking = PropertyContext::frontDeskReady() && RoleAccess::allows($role, 'front_desk');
$propertyAssignments = PropertyContext::assignments((int) session('userId'));
?>
<header class="app-header bg-white border-bottom px-3 px-lg-4 py-3 d-flex align-items-center gap-3">
    <!-- Hamburger toggle — always visible on non-desktop -->
    <button class="sidebar-toggle-btn btn btn-sm btn-light d-xl-none flex-shrink-0" id="sidebar-toggle-btn" aria-label="Buka menu" aria-controls="app-sidebar" aria-expanded="false" type="button">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>

    <div class="app-header-title min-width-0 flex-grow-1">
        <h1 class="h4 mb-0"><?= esc($pageTitle ?? 'Dashboard') ?></h1>
        <small class="text-secondary d-none d-sm-block"><?= esc($pageDescription ?? 'Property Management System') ?></small>
    </div>

    <div class="app-header-meta d-flex align-items-center gap-3 gap-lg-4 ms-auto">
        <div class="app-clock d-none d-xl-block text-lg-end fw-semibold" data-wib-clock>Memuat waktu...</div>
        <div class="app-property d-flex align-items-center gap-3">
            <span class="app-property-icon" aria-hidden="true">GH</span>
            <form method="post" action="<?= site_url('properties/select') ?>" class="app-property-form" id="property-switch-form">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect_url" id="property-switch-redirect" value="<?= esc($_SERVER['REQUEST_URI'] ?? '') ?>">
                <label class="form-label small text-secondary mb-1" for="active-property-select">Properti · <?= esc($role) ?></label>
                <select class="form-select form-select-sm app-property-select" id="active-property-select" name="property_id" aria-label="Pilih properti" onchange="const r=document.getElementById('property-switch-redirect');if(r){r.value=window.location.pathname+window.location.search;}this.form.submit();">
                    <?php foreach ($propertyAssignments as $assignment): ?>
                        <option value="<?= (int) $assignment['property_id'] ?>" <?= (int) session('activePropertyId') === (int) $assignment['property_id'] ? 'selected' : '' ?>><?= esc($assignment['name']) ?><?= (int) $assignment['is_active'] === 1 ? '' : ' (persiapan)' ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <div class="app-header-actions d-flex align-items-center gap-2 flex-shrink-0">
        <?php if ($canCreateBooking): ?>
        <button class="btn btn-primary text-nowrap"
                hx-get="<?= site_url('reservations/new') ?>"
                hx-target="#modal-container"
                hx-swap="innerHTML">+ New Booking</button>
        <?php endif; ?>
    </div>
</header>
