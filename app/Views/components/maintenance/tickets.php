<?php
$statusClass = static fn (string $status): string => match ($status) {
    'Open' => 'text-bg-warning',
    'Assigned' => 'text-bg-info',
    'In_Progress' => 'text-bg-primary',
    'Waiting_Part' => 'text-bg-secondary',
    'Completed' => 'text-bg-success',
    default => 'text-bg-light',
};
$statusLabel = static fn (string $status): string => str_replace('_', ' ', $status);
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
            <div>
                <h3 class="h5 mb-1">Buat Work Order</h3>
                <p class="small text-secondary mb-0">Catat gangguan, tentukan penanggung jawab, dan blokir kamar hanya bila diperlukan.</p>
            </div>
        </div>
        <?php if ($categories === []): ?>
            <div class="alert alert-warning mb-0">Aktifkan atau tambahkan kategori maintenance terlebih dahulu.</div>
        <?php else: ?>
            <form class="row g-3" data-maintenance-ticket-form hx-post="<?= site_url('ops/maintenance') ?>" hx-target="#ops-feedback" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <div class="col-md-3">
                    <label class="form-label">Kamar</label>
                    <select class="form-select" name="room_id" data-room-select required>
                        <option value="">Pilih kamar</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= $room['id'] ?>"><?= esc($room['room_number'] . ' · ' . $room['status']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kategori</label>
                    <select class="form-select" name="maintenance_category_id" data-category-select required>
                        <option value="">Pilih kategori</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>"><?= esc($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Aset kamar <span class="text-secondary">(opsional)</span></label>
                    <select class="form-select" name="room_asset_id" data-asset-select disabled>
                        <option value="">Tidak terkait aset tertentu</option>
                        <?php foreach ($roomAssets as $asset): ?>
                            <option value="<?= $asset['id'] ?>" data-room-id="<?= $asset['room_id'] ?>" data-category-id="<?= $asset['maintenance_category_id'] ?>" hidden disabled>
                                <?= esc($asset['room_number'] . ' · ' . $asset['asset_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Prioritas</label>
                    <select class="form-select" name="priority" required>
                        <option>Low</option><option selected>Medium</option><option>High</option><option>Urgent</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Deskripsi masalah</label>
                    <textarea class="form-control" name="description" rows="2" maxlength="2000" placeholder="Jelaskan gejala atau kerusakan yang ditemukan" required></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Teknisi internal <span class="text-secondary">(opsional)</span></label>
                    <select class="form-select" name="assigned_to_user_id">
                        <option value="">Belum ditentukan</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= esc($user['full_name'] . ' · ' . $user['role']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Vendor <span class="text-secondary">(opsional)</span></label>
                    <input class="form-control" name="vendor_name" maxlength="150" placeholder="Nama vendor eksternal">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Jadwal pengerjaan <span class="text-secondary">(opsional)</span></label>
                    <input class="form-control" type="datetime-local" name="scheduled_at">
                </div>
                <div class="col-md-5 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="blocks_room" value="1" id="blocks-room">
                        <label class="form-check-label" for="blocks-room">Blokir kamar selama maintenance</label>
                        <div class="form-text">Aktifkan hanya jika kamar tidak aman atau tidak layak dijual.</div>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100 text-nowrap">Buat Work Order</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center gap-3">
        <h3 class="h6 mb-0">Work Order Aktif</h3>
        <span class="badge text-bg-light border text-secondary"><?= count($items) ?> pekerjaan</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0 maintenance-work-table">
            <thead><tr><th>Work Order</th><th>Kamar</th><th>Kategori & Aset</th><th>Penanggung jawab</th><th>Prioritas</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
                <?php if ($items === []): ?><tr><td colspan="7" class="text-center text-secondary py-5">Tidak ada Work Order yang sedang aktif.</td></tr><?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-nowrap"><?= esc($item['ticket_number'] ?? ('WO-' . $item['id'])) ?></div>
                            <small class="text-secondary"><?= esc(substr((string) $item['created_at'], 0, 16)) ?></small>
                        </td>
                        <td class="fw-semibold"><?= esc($item['room_number']) ?><?= (int) ($item['blocks_room'] ?? 0) === 1 ? ' 🔒' : '' ?></td>
                        <td>
                            <div><?= esc($item['category_name'] ?? $item['issue_type']) ?></div>
                            <small class="text-secondary"><?= esc($item['asset_name'] ?? 'Tanpa aset spesifik') ?></small>
                        </td>
                        <td>
                            <div><?= esc($item['assigned_to_name'] ?? $item['vendor_name'] ?? 'Belum ditugaskan') ?></div>
                            <?php if (! empty($item['scheduled_at'])): ?><small class="text-secondary"><?= esc(substr($item['scheduled_at'], 0, 16)) ?></small><?php endif; ?>
                        </td>
                        <td><span class="badge <?= in_array($item['priority'], ['Urgent', 'High'], true) ? 'text-bg-danger' : 'text-bg-secondary' ?>"><?= esc($item['priority']) ?></span></td>
                        <td><span class="badge <?= $statusClass($item['status']) ?>"><?= esc($statusLabel($item['status'])) ?></span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary text-nowrap"
                                    hx-get="<?= site_url('ops/maintenance/work-orders/' . $item['id']) ?>"
                                    hx-target="#modal-container" hx-swap="innerHTML">Detail</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
