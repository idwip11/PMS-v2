<?php $canManage = in_array((string) session('role'), ['Atmin', 'Admin', 'Manager'], true); ?>

<?php if ($canManage): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h3 class="h5 mb-3">Tambah Kategori Maintenance</h3>
            <form class="row g-3" hx-post="<?= site_url('ops/maintenance/categories') ?>" hx-target="#ops-feedback" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <div class="col-md-3"><label class="form-label">Nama kategori</label><input class="form-control" name="name" required maxlength="100" placeholder="Contoh: AC"></div>
                <div class="col-md-2"><label class="form-label">Kode</label><input class="form-control" name="code" maxlength="30" placeholder="AC"></div>
                <div class="col-md-2"><label class="form-label">Interval default</label><input class="form-control" type="number" name="default_interval_value" min="1" max="3650" value="3" required></div>
                <div class="col-md-2"><label class="form-label">Satuan</label><select class="form-select" name="default_interval_unit" required><option>Day</option><option>Week</option><option selected>Month</option><option>Year</option></select></div>
                <div class="col-md-3"><label class="form-label">Deskripsi</label><input class="form-control" name="description" maxlength="1000"></div>
                <div class="col-md-10"><label class="form-label">Checklist default <span class="text-secondary">(satu langkah per baris)</span></label><textarea class="form-control" name="default_checklist" rows="2" maxlength="4000" placeholder="Bersihkan filter&#10;Periksa suhu"></textarea></div>
                <div class="col-md-2 d-flex align-items-end"><input type="hidden" name="is_active" value="1"><button class="btn btn-primary w-100">Tambah Kategori</button></div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0">Daftar Kategori</h3>
        <span class="badge text-bg-light"><?= count($items) ?> kategori</span>
    </div>
    <div class="list-group list-group-flush">
        <?php if ($items === []): ?><div class="text-center text-secondary py-5">Belum ada kategori maintenance.</div><?php endif; ?>
        <?php foreach ($items as $category): ?>
            <details class="list-group-item p-0">
                <summary class="maintenance-summary d-flex flex-wrap align-items-center gap-3 px-4 py-3">
                    <span class="fw-semibold"><?= esc($category['name']) ?></span>
                    <?php if ($category['code']): ?><span class="badge text-bg-light"><?= esc($category['code']) ?></span><?php endif; ?>
                    <span class="text-secondary small">Setiap <?= esc($category['default_interval_value'] . ' ' . $category['default_interval_unit']) ?></span>
                    <span class="badge <?= (int) $category['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?> ms-auto"><?= (int) $category['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
                </summary>
                <div class="border-top bg-light-subtle p-4">
                    <?php if ($canManage): ?>
                        <form class="row g-3" hx-post="<?= site_url('ops/maintenance/categories/' . $category['id']) ?>" hx-target="#ops-feedback" hx-swap="innerHTML">
                            <?= csrf_field() ?>
                            <div class="col-md-3"><label class="form-label small">Nama</label><input class="form-control form-control-sm" name="name" value="<?= esc($category['name']) ?>" required maxlength="100"></div>
                            <div class="col-md-2"><label class="form-label small">Kode</label><input class="form-control form-control-sm" name="code" value="<?= esc($category['code'] ?? '') ?>" maxlength="30"></div>
                            <div class="col-md-2"><label class="form-label small">Interval</label><input class="form-control form-control-sm" type="number" name="default_interval_value" min="1" max="3650" value="<?= esc($category['default_interval_value']) ?>" required></div>
                            <div class="col-md-2"><label class="form-label small">Satuan</label><select class="form-select form-select-sm" name="default_interval_unit"><?php foreach (['Day', 'Week', 'Month', 'Year'] as $unit): ?><option <?= $category['default_interval_unit'] === $unit ? 'selected' : '' ?>><?= $unit ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-3"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="is_active"><option value="1" <?= (int) $category['is_active'] === 1 ? 'selected' : '' ?>>Aktif</option><option value="0" <?= (int) $category['is_active'] === 0 ? 'selected' : '' ?>>Nonaktif</option></select></div>
                            <div class="col-md-5"><label class="form-label small">Deskripsi</label><textarea class="form-control form-control-sm" name="description" rows="3" maxlength="1000"><?= esc($category['description'] ?? '') ?></textarea></div>
                            <div class="col-md-5"><label class="form-label small">Checklist default</label><textarea class="form-control form-control-sm" name="default_checklist" rows="3" maxlength="4000"><?= esc($category['default_checklist'] ?? '') ?></textarea></div>
                            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-outline-primary w-100">Simpan Perubahan</button></div>
                        </form>
                    <?php else: ?>
                        <p class="mb-2"><?= esc($category['description'] ?: 'Tidak ada deskripsi.') ?></p>
                        <pre class="small text-secondary mb-0 maintenance-checklist"><?= esc($category['default_checklist'] ?: 'Belum ada checklist.') ?></pre>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</div>
