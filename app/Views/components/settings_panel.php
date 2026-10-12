<?php
/**
 * Helper to determine role badge class and avatar background gradient
 */
$getRoleMeta = static function (string $role): array {
    if (str_starts_with($role, 'Owner')) {
        return ['badge-role-owner', 'settings-avatar-owner'];
    }
    return match ($role) {
        'Admin', 'Atmin' => ['badge-role-admin', 'settings-avatar-admin'],
        'Manager' => ['badge-role-manager', 'settings-avatar-manager'],
        'Finance' => ['badge-role-finance', 'settings-avatar-finance'],
        'Operational' => ['badge-role-operational', 'settings-avatar-ops'],
        'Marketing' => ['badge-role-marketing', 'settings-avatar-marketing'],
        'Housekeeping' => ['badge-role-housekeeping', 'settings-avatar-housekeeping'],
        'Staff' => ['badge-role-staff', 'settings-avatar-staff'],
        default => ['badge-role-staff', 'settings-avatar-default'],
    };
};

/**
 * Helper to get user initials (up to 2 chars)
 */
$getInitials = static function (string $name, string $username): string {
    $trimmed = trim($name !== '' ? $name : $username);
    $parts = preg_split('/\s+/', $trimmed);
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }
    return strtoupper(mb_substr($trimmed, 0, 2));
};

/**
 * Check if current session user is Admin (not Manager/Atmin)
 * and the target user has Manager role on any property.
 * Admin can CREATE Manager users but cannot edit/delete them.
 */
$sessionRole = (string) session('role');
$isAdminRole = ! in_array($sessionRole, ['Manager', 'Atmin'], true);
$checkManagerProtected = static function (array $userAssignments) use ($isAdminRole): bool {
    if (! $isAdminRole) {
        return false;
    }
    foreach ($userAssignments as $assignment) {
        if (($assignment['role'] ?? '') === 'Manager') {
            return true;
        }
    }
    return false;
};

$staffRoles = ['Admin', 'Manager', 'Finance', 'Operational', 'Marketing', 'Housekeeping', 'Staff'];
$ghOwnerRoles = ['Owner GH 1', 'Owner GH 2', 'Owner GH 3', 'Owner GH 4', 'Owner GH 5'];
$mitraOwnerRoles = [
    'Owner Mitra' => 'Owner Mitra (Homestay + Kos)',
];
$legacyRoles = ['Atmin'];
?>

<!-- KARTU TAMBAH USER BARU -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2.5 mb-2">
            <div class="prop-icon-wrap" style="background: #eff6ff; color: #2563eb;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <line x1="19" y1="8" x2="19" y2="14"/>
                    <line x1="22" y1="11" x2="16" y2="11"/>
                </svg>
            </div>
            <div>
                <h3 class="h5 mb-0 fw-bold text-dark">Tambah Pengguna Baru</h3>
                <p class="text-secondary small mb-0">Setiap akun harus mempunyai role pada setidaknya satu properti. Role Owner GH berlaku untuk HomiQ, dan Owner Mitra untuk gabungan Homestay &amp; Kos Mitra.</p>
            </div>
        </div>

        <form class="row g-3 align-items-end mt-1" hx-post="<?= site_url('settings/users') ?>" hx-target="#settings-feedback" hx-swap="innerHTML">
            <?= csrf_field() ?>
            <div class="col-md-6 col-xl-4">
                <label class="form-label small fw-semibold text-secondary mb-1">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary border-end-0">@</span>
                    <input class="form-control border-start-0 ps-1" name="username" required maxlength="100" autocomplete="off" placeholder="cth: ilham22">
                </div>
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label small fw-semibold text-secondary mb-1">Nama Lengkap</label>
                <input class="form-control" name="full_name" required maxlength="150" placeholder="cth: Ilham Pratama">
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label small fw-semibold text-secondary mb-1">Properti</label>
                <select class="form-select" name="property_id" id="new-user-property" required onchange="syncRoleFromProperty(this)">
                    <?php foreach ($properties as $property): ?>
                        <option value="<?= (int) $property['id'] ?>"><?= esc($property['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 col-xl-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Role / Hak Akses</label>
                <select class="form-select" name="role" id="new-user-role" required onchange="syncPropertyFromRole(this)">
                    <optgroup label="Manajemen & Staf">
                        <?php foreach ($staffRoles as $r): ?>
                            <option value="<?= esc($r) ?>" <?= $r === 'Admin' ? 'selected' : '' ?>><?= esc($r) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Owner HomiQ (Guesthouse 1–5)">
                        <?php foreach ($ghOwnerRoles as $r): ?>
                            <option value="<?= esc($r) ?>"><?= esc($r) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Owner Properti Mitra">
                        <?php foreach ($mitraOwnerRoles as $val => $lbl): ?>
                            <option value="<?= esc($val) ?>"><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Lainnya">
                        <?php foreach ($legacyRoles as $r): ?>
                            <option value="<?= esc($r) ?>"><?= esc($r) ?> (Legacy)</option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
                <div id="new-user-mitra-hint" class="small text-primary mt-1" style="display: none; font-size: 0.72rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="me-1"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>Otomatis berpasangan untuk Homestay &amp; Kos.
                </div>
            </div>
            <div class="col-md-6 col-xl-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Password Awal</label>
                <input class="form-control" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Min. 8 karakter">
            </div>
            <div class="col-12 text-end">
                <button class="btn btn-primary px-4 d-inline-flex align-items-center gap-2 shadow-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    <span>Tambah User</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- HEADER DAFTAR USER & PENCARIAN -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div class="d-flex align-items-center gap-2.5">
        <h3 class="h5 mb-0 fw-bold text-dark">Daftar User</h3>
        <span class="badge text-bg-light border px-2.5 py-1 text-secondary rounded-pill fw-medium" id="user-total-badge"><?= count($users) ?> akun</span>
    </div>

    <div class="position-relative settings-user-search">
        <input type="text" id="user-search-input" class="form-control form-control-sm ps-4 pe-3 rounded-pill bg-white shadow-sm border" placeholder="Cari nama, username, atau role..." oninput="filterUserCards(this.value)">
        <span class="position-absolute text-muted" style="left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <path d="m21 21-4.3-4.3"/>
            </svg>
        </span>
    </div>
</div>

<?php if ($users === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-secondary py-5">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-muted mb-2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <div>Belum ada akun user yang terdaftar.</div>
        </div>
    </div>
<?php endif; ?>

<div id="user-search-empty" class="card border-0 shadow-sm mb-3" style="display: none;">
    <div class="card-body text-center text-secondary py-4">
        Tidak ditemukan user yang cocok dengan pencarian Anda.
    </div>
</div>

<!-- LIST KARTU USER -->
<?php foreach ($users as $user): ?>
    <?php
        $userId = (int) $user['id'];
        $userAssignments = $assignments[$userId] ?? [];
        $missingProperties = array_values(array_filter(
            $properties,
            static fn (array $property): bool => ! isset($userAssignments[(int) $property['id']])
        ));
        $isCurrentUser = (int) session('userId') === $userId;
        $isManagerProtected = $checkManagerProtected($userAssignments);

        // Cari role utama untuk ditampilkan pada pill header (prioritaskan properti aktif atau HomiQ)
        $activePropId = (int) session('activePropertyId');
        $primaryRole = $userAssignments[$activePropId]['role']
            ?? ($userAssignments[1]['role']
            ?? (reset($userAssignments)['role'] ?? ($user['role'] ?? 'Staff')));

        [$primaryBadgeClass, $avatarBgClass] = $getRoleMeta($primaryRole);
        $initials = $getInitials((string) $user['full_name'], (string) $user['username']);
        $searchString = strtolower($user['username'] . ' ' . $user['full_name'] . ' ' . implode(' ', array_column($userAssignments, 'role')));
    ?>
    <div class="card settings-user-card shadow-sm mb-3 user-item-card" data-search="<?= esc($searchString) ?>">
        <!-- Card Header: Identitas User & Aksi Hapus -->
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3 min-width-0">
                <div class="settings-avatar <?= $avatarBgClass ?> shadow-sm">
                    <?= esc($initials) ?>
                </div>
                <div class="min-width-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h4 class="h6 mb-0 fw-bold text-dark text-break"><?= esc($user['full_name']) ?></h4>
                        <span class="badge-role <?= $primaryBadgeClass ?>"><?= esc($primaryRole) ?></span>
                        <?php if ($isCurrentUser): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5 rounded-pill small d-inline-flex align-items-center gap-1">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <span>Akun Anda</span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-secondary small d-flex align-items-center gap-2 mt-1 flex-wrap">
                        <span class="fw-medium text-muted text-break">@<?= esc($user['username']) ?></span>
                        <span class="text-muted opacity-50">•</span>
                        <span><?= count($userAssignments) ?> properti terhubung</span>
                    </div>
                </div>
            </div>

            <div>
                <form hx-post="<?= site_url('settings/users/' . $userId . '/delete') ?>" hx-target="#settings-feedback" hx-swap="innerHTML" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun @<?= esc($user['username']) ?>?')">
                    <?= csrf_field() ?>
                    <?php
                        $deleteDisabled = $isCurrentUser || $isManagerProtected;
                        $deleteTitle = $isCurrentUser
                            ? 'Akun yang sedang digunakan tidak dapat dihapus'
                            : ($isManagerProtected ? 'Admin tidak dapat menghapus akun Manager' : '');
                    ?>
                    <button class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-pill" <?= $deleteDisabled ? 'disabled title="' . esc($deleteTitle) . '"' : '' ?>>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6"/>
                        </svg>
                        <span>Hapus akun</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Card Body -->
        <div class="card-body p-4">
            <!-- 1. Form Profil & Password -->
            <div class="mb-4 pb-3 border-bottom">
                <?php if ($isManagerProtected): ?>
                    <div class="alert alert-warning-subtle bg-warning-subtle border border-warning-subtle text-warning-emphasis py-2 px-3 d-flex align-items-center gap-2 small mb-0 rounded-2">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="flex-shrink-0"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span>Anda tidak dapat mengedit akun ini. Hanya pengguna dengan role <strong>Manager</strong> yang dapat mengelola akun sesama Manager.</span>
                    </div>
                <?php else: ?>
                <form class="row g-3 align-items-end" hx-post="<?= site_url('settings/users/' . $userId) ?>" hx-target="#settings-feedback" hx-swap="innerHTML">
                    <?= csrf_field() ?>
                    <div class="col-md-6 col-xl-5">
                        <label class="form-label small fw-semibold text-secondary mb-1">Nama lengkap</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-secondary border-end-0">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                            </span>
                            <input class="form-control form-control-sm border-start-0 ps-1" name="full_name" value="<?= esc($user['full_name']) ?>" required maxlength="150" placeholder="Nama lengkap">
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-5">
                        <label class="form-label small fw-semibold text-secondary mb-1">
                            Password baru <span class="text-muted fw-normal">(kosongkan jika tetap)</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-secondary border-end-0">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            <input class="form-control form-control-sm border-start-0 ps-1" type="password" name="password" placeholder="Min. 8 karakter jika diubah" minlength="8" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="col-md-12 col-xl-2">
                        <button class="btn btn-sm btn-outline-primary w-100 d-inline-flex align-items-center justify-content-center gap-1.5 py-1.5 shadow-sm">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            <span>Simpan akun</span>
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>

            <!-- 2. Akses Properti & Role -->
            <div>
                <div class="settings-access-heading d-flex align-items-center justify-content-between mb-2.5">
                    <span class="small fw-bold text-dark text-uppercase tracking-wider">Akses Properti & Role</span>
                    <span class="small text-muted"><?= count($userAssignments) ?> properti terhubung</span>
                </div>

                <div class="d-flex flex-column gap-2 mb-3">
                    <?php foreach ($properties as $property): ?>
                        <?php
                            $propertyId = (int) $property['id'];
                            $assignment = $userAssignments[$propertyId] ?? null;
                            if ($assignment === null) { continue; }
                            $isSelfActive = $isCurrentUser && (int) session('activePropertyId') === $propertyId;
                            $isLastAssignment = count($userAssignments) <= 1;
                            [$propBadgeClass, ] = $getRoleMeta((string) $assignment['role']);
                        ?>
                        <div class="settings-prop-item d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-2.5">
                                <div class="prop-icon-wrap">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                        <polyline points="9 22 9 12 15 12 15 22"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                        <span class="fw-semibold text-dark"><?= esc($property['name']) ?></span>
                                        <?php if ($propertyId === 1): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.68rem;">Utama</span>
                                        <?php endif; ?>
                                        <?php if ((int) $property['is_active'] !== 1): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.68rem;">Persiapan</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted mt-0.5">
                                        Role aktif: <span class="badge-role <?= $propBadgeClass ?> py-0 px-2" style="font-size: 0.7rem;"><?= esc($assignment['role']) ?></span>
                                        <?php if ($assignment['role'] === 'Owner Mitra'): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill py-0 px-1.5 ms-1" style="font-size: 0.65rem;">Homestay &amp; Kos</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="settings-prop-actions ms-auto flex-nowrap">
                                <?php if ($isManagerProtected): ?>
                                    <!-- Admin cannot edit/delete Manager accounts -->
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border px-2.5 py-1.5 small">Hanya Manager yang dapat mengubah</span>
                                <?php else: ?>
                                <!-- Form Ganti Role Properti -->
                                <form class="d-flex align-items-center gap-1.5 m-0" hx-post="<?= site_url('settings/users/' . $userId . '/property-roles') ?>" hx-target="#settings-feedback" hx-swap="innerHTML" <?php if ($assignment['role'] === 'Owner Mitra'): ?>onsubmit="return this.elements.role.value === 'Owner Mitra' || confirm('Ganti paket Owner Mitra? Akses owner Homestay dan Kos akan dicabut bersama, lalu role baru diberikan hanya pada properti yang dipilih.');"<?php endif; ?>>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="property_id" value="<?= $propertyId ?>">
                                    <select class="form-select form-select-sm settings-prop-role-select" name="role" aria-label="Role <?= esc($property['name']) ?>" style="width: 175px; min-width: 175px; max-width: 175px;">
                                        <optgroup label="Manajemen & Staf">
                                            <?php foreach ($staffRoles as $r): ?>
                                                <option value="<?= esc($r) ?>" <?= $assignment['role'] === $r ? 'selected' : '' ?>><?= esc($r) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php if ($propertyId === 1): ?>
                                            <optgroup label="Owner HomiQ (Guesthouse 1–5)">
                                                <?php foreach ($ghOwnerRoles as $r): ?>
                                                    <option value="<?= esc($r) ?>" <?= $assignment['role'] === $r ? 'selected' : '' ?>><?= esc($r) ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php elseif (in_array($propertyId, [2, 3], true)): ?>
                                            <optgroup label="Owner Properti Mitra">
                                                <option value="Owner Mitra" <?= $assignment['role'] === 'Owner Mitra' ? 'selected' : '' ?>>Owner Mitra (Homestay + Kos)</option>
                                                <?php if (in_array($assignment['role'], ['Owner HS Mitra', 'Owner Kos Mitra'], true)): ?>
                                                    <option value="<?= esc($assignment['role']) ?>" selected><?= esc($assignment['role']) ?> (Legacy)</option>
                                                <?php endif; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <optgroup label="Lainnya">
                                            <?php foreach ($legacyRoles as $r): ?>
                                                <option value="<?= esc($r) ?>" <?= $assignment['role'] === $r ? 'selected' : '' ?>><?= esc($r) ?> (Legacy)</option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary settings-btn-save d-inline-flex align-items-center justify-content-center gap-1 px-2.5 py-1 text-nowrap" style="min-width: 82px;" title="Simpan role properti ini">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span>Simpan</span>
                                    </button>
                                </form>

                                <!-- Form Cabut Akses Properti -->
                                <form class="m-0" hx-post="<?= site_url('settings/users/' . $userId . '/property-roles/' . $propertyId . '/delete') ?>" hx-target="#settings-feedback" hx-swap="innerHTML" onsubmit="return confirm('<?= $assignment['role'] === 'Owner Mitra' ? 'Cabut akses Owner Mitra dari user @' . esc($user['username'], 'js') . '? Akses ke Homestay dan Kos Mitra akan dicabut sekaligus.' : 'Cabut akses properti ' . esc($property['name'], 'js') . ' dari user @' . esc($user['username'], 'js') . '?' ?>')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-light text-danger border settings-btn-revoke d-inline-flex align-items-center justify-content-center gap-1 px-2.5 py-1 text-nowrap" style="min-width: 76px;" <?= $isLastAssignment || $isSelfActive ? 'disabled title="' . ($isSelfActive ? 'Properti yang sedang aktif digunakan tidak dapat dicabut' : 'User harus memiliki setidaknya satu properti') . '"' : 'title="Cabut akses properti ini"' ?>>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                        <span>Cabut</span>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- 3. Form Tambah Akses ke Properti Lain (Bila masih ada properti yang belum terhubung) -->
                <?php if ($missingProperties !== [] && ! $isManagerProtected): ?>
                    <div class="settings-add-prop-box">
                        <div class="small fw-semibold text-secondary mb-2 d-flex align-items-center gap-1.5">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-primary">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="16"/>
                                <line x1="8" y1="12" x2="16" y2="12"/>
                            </svg>
                            <span>Tambah Akses ke Properti Lain</span>
                        </div>
                        <form class="row g-2 align-items-center" hx-post="<?= site_url('settings/users/' . $userId . '/property-roles') ?>" hx-target="#settings-feedback" hx-swap="innerHTML">
                            <?= csrf_field() ?>
                            <div class="col-md-5">
                                <select class="form-select form-select-sm" name="property_id" required onchange="syncRoleFromProperty(this)">
                                    <option value="" disabled selected>-- Pilih Properti --</option>
                                    <?php foreach ($missingProperties as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>"><?= esc($p['name']) ?><?= (int) $p['is_active'] === 1 ? '' : ' (Persiapan)' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <select class="form-select form-select-sm" name="role" required onchange="syncPropertyFromRole(this)">
                                    <option value="" disabled selected>-- Tentukan Role --</option>
                                    <optgroup label="Manajemen & Staf">
                                        <?php foreach ($staffRoles as $r): ?>
                                            <option value="<?= esc($r) ?>"><?= esc($r) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="Owner HomiQ (Guesthouse 1–5)">
                                        <?php foreach ($ghOwnerRoles as $r): ?>
                                            <option value="<?= esc($r) ?>"><?= esc($r) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="Owner Properti Mitra">
                                        <?php foreach ($mitraOwnerRoles as $val => $lbl): ?>
                                            <option value="<?= esc($val) ?>"><?= esc($lbl) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="Lainnya">
                                        <?php foreach ($legacyRoles as $r): ?>
                                            <option value="<?= esc($r) ?>"><?= esc($r) ?> (Legacy)</option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-sm btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-1 py-1.5">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <span>Tambah akses</span>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
function filterUserCards(query) {
    const q = (query || '').toLowerCase().trim();
    const cards = document.querySelectorAll('.user-item-card');
    let visibleCount = 0;
    cards.forEach(card => {
        const searchStr = (card.getAttribute('data-search') || '').toLowerCase();
        const match = !q || searchStr.includes(q);
        card.style.display = match ? '' : 'none';
        if (match) visibleCount++;
    });
    const emptyNotice = document.getElementById('user-search-empty');
    if (emptyNotice) {
        emptyNotice.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
    }
}

function syncPropertyFromRole(roleSelect) {
    if (!roleSelect) return;
    const form = roleSelect.closest('form');
    if (!form) return;
    const propSelect = form.querySelector('select[name="property_id"]');
    const hint = form.querySelector('#new-user-mitra-hint');

    const role = roleSelect.value;
    if (role === 'Owner Mitra') {
        if (propSelect && propSelect.value === '1') {
            propSelect.value = '2';
        }
        if (hint) hint.style.display = 'block';
    } else {
        if (hint) hint.style.display = 'none';
        if (role.startsWith('Owner GH ') && propSelect) {
            propSelect.value = '1';
        } else if (role === 'Owner HS Mitra' && propSelect) {
            propSelect.value = '2';
        } else if (role === 'Owner Kos Mitra' && propSelect) {
            propSelect.value = '3';
        }
    }
}

function syncRoleFromProperty(propSelect) {
    if (!propSelect) return;
    const form = propSelect.closest('form');
    if (!form) return;
    const roleSelect = form.querySelector('select[name="role"]');
    if (!roleSelect) return;

    const propId = String(propSelect.value || '');
    const currentRole = roleSelect.value;

    if ((propId === '2' || propId === '3') && currentRole.startsWith('Owner GH ')) {
        roleSelect.value = 'Owner Mitra';
    } else if (propId === '1' && (currentRole === 'Owner Mitra' || currentRole === 'Owner HS Mitra' || currentRole === 'Owner Kos Mitra')) {
        roleSelect.value = 'Admin';
    }
    syncPropertyFromRole(roleSelect);
}
</script>
