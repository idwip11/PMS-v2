<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div class="d-flex flex-column flex-md-row gap-2">
        <form method="get" action="<?= site_url('guests') ?>" class="d-flex gap-2 guest-search-form">
            <input class="form-control" name="q" value="<?= esc($search) ?>" placeholder="Cari nama, telepon, email, atau asal">
            <button class="btn btn-primary">Cari</button>
            <?php if ($search !== ''): ?><a class="btn btn-outline-secondary" href="<?= site_url('guests') ?>">Reset</a><?php endif; ?>
        </form>
        <button class="btn btn-primary text-nowrap" type="button" data-bs-toggle="modal" data-bs-target="#newGuestModal">+ Tamu Baru</button>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-semibold">Daftar Tamu</span>
        <span class="badge text-bg-light border text-secondary"><?= count($guests) ?> tamu</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 front-desk-table small">
            <thead class="table-light">
                <tr><th>Nama</th><th>Telepon</th><th>Email</th><th>Asal</th><th>Jumlah Menginap</th><th>Terakhir Menginap</th><th>Catatan</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
                <?php if ($guests === []): ?>
                    <tr><td colspan="8" class="text-center text-secondary py-5">Belum ada data tamu sesuai pencarian.</td></tr>
                <?php else: ?>
                    <?php foreach ($guests as $guest): ?>
                        <?php
                        $phone = preg_replace('/\D+/', '', (string) ($guest['phone'] ?? ''));
                        if (str_starts_with($phone, '0')) {
                            $phone = '62' . substr($phone, 1);
                        }
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($guest['full_name']) ?></td>
                            <td class="text-nowrap"><?= esc($guest['phone'] ?: '-') ?></td>
                            <td><?= esc($guest['email'] ?: '-') ?></td>
                            <td><?= esc($guest['origin'] ?: '-') ?></td>
                            <td><?= (int) $guest['reservation_count'] ?> reservasi</td>
                            <td class="text-nowrap"><?= $guest['last_stay'] ? esc(date('d/m/Y', strtotime($guest['last_stay']))) : '-' ?></td>
                            <td class="guest-notes"><?= esc($guest['notes'] ?: '-') ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($phone !== ''): ?><a class="btn btn-outline-success btn-sm" href="https://wa.me/<?= esc($phone) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
                                <a class="btn btn-outline-primary btn-sm" href="<?= site_url('reservations?q=' . rawurlencode($guest['full_name'])) ?>">Reservasi</a>
                                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#editGuestModal" data-edit-guest data-guest-action="<?= esc(site_url('guests/' . (int) $guest['id'])) ?>" data-guest-name="<?= esc($guest['full_name']) ?>" data-guest-phone="<?= esc($guest['phone'] ?? '') ?>" data-guest-email="<?= esc($guest['email'] ?? '') ?>" data-guest-origin="<?= esc($guest['origin'] ?? '') ?>" data-guest-notes="<?= esc($guest['notes'] ?? '') ?>">Edit</button>
                                <?php if ((int) $guest['reservation_count'] > 0): ?>
                                    <button class="btn btn-outline-danger btn-sm" type="button" disabled title="Tamu yang sudah memiliki reservasi tidak dapat dihapus">Hapus</button>
                                <?php else: ?>
                                    <form class="d-inline" method="post" action="<?= site_url('guests/' . (int) $guest['id'] . '/delete') ?>" onsubmit="return confirm('Hapus data tamu <?= esc($guest['full_name'], 'js') ?>? Tindakan ini tidak dapat dibatalkan.')">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="editGuestModal" tabindex="-1" aria-labelledby="editGuestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0">
            <form method="post" data-guest-edit-form>
                <?= csrf_field() ?>
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5 mb-0" id="editGuestModalLabel">Edit Data Tamu</h2>
                        <small class="text-secondary">Perubahan akan digunakan pada pencarian tamu dan reservasi berikutnya.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama lengkap</label>
                            <input class="form-control" name="full_name" maxlength="150" required data-guest-edit-field="name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telepon</label>
                            <input class="form-control" name="phone" maxlength="50" inputmode="tel" data-guest-edit-field="phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input class="form-control" type="email" name="email" maxlength="150" data-guest-edit-field="email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Asal</label>
                            <input class="form-control" name="origin" maxlength="150" data-guest-edit-field="origin">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" name="notes" rows="3" data-guest-edit-field="notes"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="newGuestModal" tabindex="-1" aria-labelledby="newGuestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0">
            <form method="post" action="<?= site_url('guests') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5 mb-0" id="newGuestModalLabel">Tamu Baru</h2>
                        <small class="text-secondary">Tambahkan data tamu tanpa membuat reservasi.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama lengkap</label>
                            <input class="form-control" name="full_name" maxlength="150" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telepon</label>
                            <input class="form-control" name="phone" maxlength="50" inputmode="tel">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input class="form-control" type="email" name="email" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Asal</label>
                            <input class="form-control" name="origin" maxlength="150" placeholder="Contoh: Jakarta">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary" type="submit">Simpan Tamu</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('editGuestModal')?.addEventListener('show.bs.modal', (event) => {
    const trigger = event.relatedTarget;
    if (!trigger?.matches('[data-edit-guest]')) return;

    const modal = event.currentTarget;
    const form = modal.querySelector('[data-guest-edit-form]');
    form.action = trigger.dataset.guestAction || '';
    modal.querySelector('[data-guest-edit-field="name"]').value = trigger.dataset.guestName || '';
    modal.querySelector('[data-guest-edit-field="phone"]').value = trigger.dataset.guestPhone || '';
    modal.querySelector('[data-guest-edit-field="email"]').value = trigger.dataset.guestEmail || '';
    modal.querySelector('[data-guest-edit-field="origin"]').value = trigger.dataset.guestOrigin || '';
    modal.querySelector('[data-guest-edit-field="notes"]').value = trigger.dataset.guestNotes || '';
});
</script>
<?= $this->endSection() ?>
