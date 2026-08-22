<?php
$canManage = in_array((string) session('role'), ['Atmin', 'Admin', 'Manager', 'Operational'], true);
$conditionClass = static fn (string $condition): string => match ($condition) {
    'Good' => 'text-bg-success',
    'Attention' => 'text-bg-warning',
    'Broken' => 'text-bg-danger',
    default => 'text-bg-secondary',
};
?>

<?php if ($canManage): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h3 class="h5 mb-3">Tambah Aset Kamar</h3>
            <form class="row g-3 asset-create-form" hx-post="<?= site_url('ops/maintenance/assets') ?>" hx-target="#ops-feedback" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <div class="col-md-2"><label class="form-label">Kamar</label><select class="form-select" name="room_id" required><option value="">Pilih kamar</option><?php foreach ($rooms as $room): ?><option value="<?= $room['id'] ?>"><?= esc($room['room_number']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Kategori</label><select class="form-select" name="maintenance_category_id" required><option value="">Pilih kategori</option><?php foreach ($categories as $category): ?><?php if ((int) $category['is_active'] === 1): ?><option value="<?= $category['id'] ?>"><?= esc($category['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Nama aset</label><input class="form-control" name="asset_name" required maxlength="150" placeholder="Contoh: AC Utama"></div>
                <div class="col-md-2"><label class="form-label">Merek <span class="text-secondary small">(opsional)</span></label><input class="form-control" name="brand" maxlength="100"></div>
                <div class="col-md-2"><label class="form-label">Kondisi</label><select class="form-select" name="condition_status"><option>Good</option><option>Attention</option><option>Broken</option><option>Retired</option></select></div>
                <div class="col-md-2"><label class="form-label">Tanggal beli <span class="text-secondary small">(opsional)</span></label><input class="form-control" type="date" name="purchase_date"></div>
                <div class="col-md-2"><label class="form-label">Tanggal pasang <span class="text-secondary small">(opsional)</span></label><input class="form-control" type="date" name="installed_at"></div>
                <div class="col-md-3"><label class="form-label">Garansi sampai <span class="text-secondary small">(opsional)</span></label><input class="form-control" type="date" name="warranty_until"></div>
                <div class="col-md-9"><label class="form-label">Catatan <span class="text-secondary small">(opsional)</span></label><input class="form-control" name="notes" maxlength="2000"></div>
                <div class="col-md-3 d-flex align-items-end justify-content-end"><input type="hidden" name="is_active" value="1"><button class="btn btn-primary px-4">Tambah Aset</button></div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <h3 class="h6 mb-0">Daftar Aset per Kamar</h3>
        <form class="d-flex gap-2" hx-get="<?= site_url('ops/panel?tab=maintenance&section=assets') ?>" hx-target="#ops-panel" hx-push-url="true">
            <label class="visually-hidden" for="asset-room-filter">Filter kamar</label>
            <select id="asset-room-filter" class="form-select form-select-sm" name="room_id" onchange="this.form.requestSubmit()">
                <option value="0">Semua kamar</option>
                <?php foreach ($rooms as $room): ?><option value="<?= $room['id'] ?>" <?= (int) $selectedRoomId === (int) $room['id'] ? 'selected' : '' ?>>Kamar <?= esc($room['room_number']) ?></option><?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Kamar</th><th>Aset</th><th>Kategori</th><th>Merek</th><th>Kondisi</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
                <?php if ($items === []): ?><tr><td colspan="7" class="text-center text-secondary py-5">Belum ada aset untuk pilihan kamar ini.</td></tr><?php endif; ?>
                <?php foreach ($items as $asset): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($asset['room_number']) ?></td>
                        <td><?= esc($asset['asset_name']) ?></td>
                        <td><?= esc($asset['category_name']) ?></td>
                        <td><?= esc($asset['brand'] ?? '-') ?></td>
                        <td><span class="badge <?= $conditionClass($asset['condition_status']) ?>"><?= esc($asset['condition_status']) ?></span></td>
                        <td><span class="badge <?= (int) $asset['is_active'] === 1 ? 'text-bg-primary' : 'text-bg-secondary' ?>"><?= (int) $asset['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
                        <td class="text-end"><?php if ($canManage): ?><button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#asset-edit-<?= $asset['id'] ?>">Edit</button><?php endif; ?></td>
                    </tr>
                    <?php if ($canManage): ?>
                        <tr class="collapse bg-light-subtle" id="asset-edit-<?= $asset['id'] ?>">
                            <td colspan="7" class="p-4">
                                <form class="row g-3" hx-post="<?= site_url('ops/maintenance/assets/' . $asset['id']) ?>" hx-target="#ops-feedback" hx-swap="innerHTML">
                                    <?= csrf_field() ?>
                                    <div class="col-md-2"><label class="form-label small">Kamar</label><select class="form-select form-select-sm" name="room_id" required><?php foreach ($rooms as $room): ?><option value="<?= $room['id'] ?>" <?= (int) $asset['room_id'] === (int) $room['id'] ? 'selected' : '' ?>><?= esc($room['room_number']) ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-2"><label class="form-label small">Kategori</label><select class="form-select form-select-sm" name="maintenance_category_id" required><?php foreach ($categories as $category): ?><option value="<?= $category['id'] ?>" <?= (int) $asset['maintenance_category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= esc($category['name'] . ((int) $category['is_active'] === 0 ? ' (nonaktif)' : '')) ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-2"><label class="form-label small">Nama aset</label><input class="form-control form-control-sm" name="asset_name" value="<?= esc($asset['asset_name']) ?>" required maxlength="150"></div>
                                    <div class="col-md-2"><label class="form-label small">Merek <span class="text-secondary">(opsional)</span></label><input class="form-control form-control-sm" name="brand" value="<?= esc($asset['brand'] ?? '') ?>" maxlength="100"></div>
                                    <div class="col-md-2"><label class="form-label small">Tanggal beli <span class="text-secondary">(opsional)</span></label><input class="form-control form-control-sm" type="date" name="purchase_date" value="<?= esc($asset['purchase_date'] ?? '') ?>"></div>
                                    <div class="col-md-2"><label class="form-label small">Tanggal pasang <span class="text-secondary">(opsional)</span></label><input class="form-control form-control-sm" type="date" name="installed_at" value="<?= esc($asset['installed_at'] ?? '') ?>"></div>
                                    <div class="col-md-2"><label class="form-label small">Garansi sampai <span class="text-secondary">(opsional)</span></label><input class="form-control form-control-sm" type="date" name="warranty_until" value="<?= esc($asset['warranty_until'] ?? '') ?>"></div>
                                    <div class="col-md-2"><label class="form-label small">Kondisi</label><select class="form-select form-select-sm" name="condition_status"><?php foreach (['Good', 'Attention', 'Broken', 'Retired'] as $condition): ?><option <?= $asset['condition_status'] === $condition ? 'selected' : '' ?>><?= $condition ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-2"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="is_active"><option value="1" <?= (int) $asset['is_active'] === 1 ? 'selected' : '' ?>>Aktif</option><option value="0" <?= (int) $asset['is_active'] === 0 ? 'selected' : '' ?>>Nonaktif</option></select></div>
                                    <div class="col-md-2"><label class="form-label small">Catatan <span class="text-secondary">(opsional)</span></label><input class="form-control form-control-sm" name="notes" value="<?= esc($asset['notes'] ?? '') ?>" maxlength="2000"></div>
                                    <div class="col-12 text-end"><button class="btn btn-sm btn-primary">Simpan Perubahan</button></div>
                                </form>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
