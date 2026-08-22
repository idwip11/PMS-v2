<?php
$filters = $historyFilters ?? ['room_id' => 0, 'category_id' => 0, 'status' => '', 'date_from' => '', 'date_to' => ''];
$statuses = ['Open', 'Assigned', 'In_Progress', 'Waiting_Part', 'Completed', 'Resolved', 'Verified', 'Closed', 'Cancelled'];
$statusClass = static fn (string $status): string => match ($status) {
    'Open' => 'text-bg-light border text-dark',
    'Assigned' => 'text-bg-info',
    'In_Progress' => 'text-bg-primary',
    'Waiting_Part' => 'text-bg-secondary',
    'Completed', 'Verified', 'Closed', 'Resolved' => 'text-bg-success',
    'Cancelled' => 'text-bg-danger',
    default => 'text-bg-secondary',
};
$canExport = in_array((string) session('role'), ['Atmin', 'Admin', 'Manager', 'Finance'], true);
$exportQuery = http_build_query(array_filter([
    'room_id' => $filters['room_id'],
    'category_id' => $filters['category_id'],
    'status' => $filters['status'],
    'date_from' => $filters['date_from'],
    'date_to' => $filters['date_to'],
], static fn ($value): bool => $value !== '' && $value !== 0));
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h3 class="h5 mb-1">Filter Riwayat Maintenance</h3>
        <p class="small text-secondary mb-3">Menampilkan seluruh Work Order, termasuk yang masih aktif dan yang sudah selesai.</p>
        <form class="row g-3" hx-get="<?= site_url('ops/panel') ?>" hx-target="#ops-panel" hx-push-url="<?= site_url('ops?tab=maintenance&section=history') ?>">
            <input type="hidden" name="tab" value="maintenance">
            <input type="hidden" name="section" value="history">
            <div class="col-md-2">
                <label class="form-label">Kamar</label>
                <select class="form-select" name="room_id">
                    <option value="">Semua kamar</option>
                    <?php foreach ($rooms as $room): ?><option value="<?= $room['id'] ?>" <?= $filters['room_id'] === (int) $room['id'] ? 'selected' : '' ?>><?= esc($room['room_number']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Kategori</label>
                <select class="form-select" name="category_id">
                    <option value="">Semua kategori</option>
                    <?php foreach ($categories as $category): ?><option value="<?= $category['id'] ?>" <?= $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= esc($category['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Semua status</option>
                    <?php foreach ($statuses as $status): ?><option value="<?= $status ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= esc(str_replace('_', ' ', $status)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Dari tanggal</label><input class="form-control" type="date" name="date_from" value="<?= esc($filters['date_from']) ?>"></div>
            <div class="col-md-2"><label class="form-label">Sampai tanggal</label><input class="form-control" type="date" name="date_to" value="<?= esc($filters['date_to']) ?>"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100 text-nowrap">Filter</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h3 class="h6 mb-0">Riwayat Work Order</h3>
        <div class="d-flex align-items-center gap-2"><span class="badge text-bg-light border text-secondary"><?= count($items) ?> hasil</span><?php if ($canExport): ?><a class="btn btn-sm btn-success text-nowrap" href="<?= site_url('ops/maintenance/reports/export' . ($exportQuery === '' ? '' : '?' . $exportQuery)) ?>">Export CSV</a><?php endif; ?></div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Work Order</th><th>Kamar</th><th>Kategori & Aset</th><th>Penanggung jawab</th><th>Dibuat</th><th class="text-end">Total biaya</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
                <?php if ($items === []): ?><tr><td colspan="8" class="text-center text-secondary py-5">Belum ada riwayat sesuai filter.</td></tr><?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="fw-semibold text-nowrap"><?= esc($item['ticket_number'] ?? ('WO-' . $item['id'])) ?></td>
                        <td><?= esc($item['room_number']) ?></td>
                        <td><div><?= esc($item['category_name'] ?? $item['issue_type']) ?></div><small class="text-secondary"><?= esc($item['asset_name'] ?? 'Tanpa aset spesifik') ?></small></td>
                        <td><?= esc($item['assigned_to_name'] ?? $item['vendor_name'] ?? '-') ?></td>
                        <td><?= esc(substr((string) $item['created_at'], 0, 10)) ?></td>
                        <td class="text-end text-nowrap">Rp<?= number_format((float) ($item['material_cost'] ?? 0) + (float) ($item['labor_cost'] ?? 0) + (float) ($item['external_cost'] ?? 0), 0, ',', '.') ?></td>
                        <td><span class="badge <?= $statusClass($item['status']) ?>"><?= esc(str_replace('_', ' ', $item['status'])) ?></span></td>
                        <td class="text-end"><button class="btn btn-sm btn-outline-primary" hx-get="<?= site_url('ops/maintenance/work-orders/' . $item['id']) ?>" hx-target="#modal-container" hx-swap="innerHTML">Detail</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
