<?php
$role = (string) session('role');
$canWork = in_array($role, ['Atmin', 'Admin', 'Manager', 'Operational'], true);
$canManage = in_array($role, ['Atmin', 'Admin', 'Manager', 'Operational'], true);
$canCancel = in_array($role, ['Atmin', 'Admin', 'Manager', 'Operational'], true);
$status = (string) $ticket['status'];
$editableClassification = $canWork && in_array($status, ['Open', 'Assigned', 'In_Progress', 'Waiting_Part'], true);
$editableAssignment = $canWork && in_array($status, ['Open', 'Assigned', 'In_Progress', 'Waiting_Part'], true);
$editableWork = $canWork && in_array($status, ['Open', 'Assigned', 'In_Progress', 'Waiting_Part'], true);
$money = static fn ($value): string => 'Rp' . number_format((float) $value, 0, ',', '.');
$statusClass = static fn (string $value): string => match ($value) {
    'Open' => 'text-bg-warning',
    'Assigned' => 'text-bg-info',
    'In_Progress' => 'text-bg-primary',
    'Waiting_Part' => 'text-bg-secondary',
    'Completed', 'Verified', 'Closed', 'Resolved' => 'text-bg-success',
    'Cancelled' => 'text-bg-danger',
    default => 'text-bg-light',
};
$dateTimeValue = static fn ($value): string => empty($value) ? '' : date('Y-m-d\TH:i', strtotime((string) $value));
$ticketParts = $ticketParts ?? [];
$spareParts = $spareParts ?? [];
$attachments = $attachments ?? [];
$categories = $categories ?? [];
$roomAssets = $roomAssets ?? [];
$materialCost = (float) ($materialCost ?? 0);
$checklistItems = json_decode((string) ($ticket['checklist_snapshot'] ?? ''), true);
if (! is_array($checklistItems)) $checklistItems = [];
$checklistCompletedRows = json_decode((string) ($ticket['checklist_completed'] ?? ''), true);
$checklistCompletedItems = [];
if (is_array($checklistCompletedRows)) {
    foreach ($checklistCompletedRows as $row) {
        if (is_array($row) && isset($row['item'])) $checklistCompletedItems[] = (string) $row['item'];
    }
}
?>
<div class="modal-header">
    <div>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <h2 class="modal-title fs-5" id="work-order-title"><?= esc($ticket['ticket_number'] ?? ('WO-' . $ticket['id'])) ?></h2>
            <span class="badge <?= $statusClass($status) ?>"><?= esc(str_replace('_', ' ', $status)) ?></span>
            <?php if (($ticket['ticket_type'] ?? 'Corrective') === 'Preventive'): ?><span class="badge text-bg-info">Preventive</span><?php endif; ?>
            <?php if ((int) ($ticket['blocks_room'] ?? 0) === 1): ?><span class="badge text-bg-danger">Kamar diblokir</span><?php endif; ?>
        </div>
        <div class="small text-secondary">Kamar <?= esc($ticket['room_number']) ?> · <?= esc($ticket['category_name'] ?? $ticket['issue_type']) ?><?= ! empty($ticket['asset_name']) ? ' · ' . esc($ticket['asset_name']) : '' ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
</div>
<div class="modal-body bg-body-tertiary">
    <?php if ($feedbackMessage): ?><div class="alert alert-<?= esc($feedbackType ?? 'info') ?>"><?= esc($feedbackMessage) ?></div><?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-8">
            <div class="card border-0 h-100"><div class="card-body">
                <h3 class="h6 text-secondary">Masalah</h3>
                <p class="mb-3 maintenance-description"><?= nl2br(esc($ticket['description'] ?: '-')) ?></p>
                <div class="row g-3 small">
                    <div class="col-sm-4"><span class="text-secondary d-block">Prioritas</span><strong><?= esc($ticket['priority']) ?></strong></div>
                    <div class="col-sm-4"><span class="text-secondary d-block">Dilaporkan oleh</span><strong><?= esc($ticket['reported_by_name'] ?? '-') ?></strong></div>
                    <div class="col-sm-4"><span class="text-secondary d-block">Dibuat</span><strong><?= esc(substr((string) $ticket['created_at'], 0, 16)) ?></strong></div>
                </div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 h-100"><div class="card-body small">
                <h3 class="h6">Ringkasan Pekerjaan</h3>
                <dl class="row mb-0">
                    <dt class="col-5 text-secondary fw-normal">Teknisi</dt><dd class="col-7 fw-semibold"><?= esc($ticket['assigned_to_name'] ?? '-') ?></dd>
                    <dt class="col-5 text-secondary fw-normal">Vendor</dt><dd class="col-7 fw-semibold"><?= esc($ticket['vendor_name'] ?? '-') ?></dd>
                    <dt class="col-5 text-secondary fw-normal">Jadwal</dt><dd class="col-7 fw-semibold"><?= esc(empty($ticket['scheduled_at']) ? '-' : substr($ticket['scheduled_at'], 0, 16)) ?></dd>
                    <?php if (! empty($ticket['next_service_at'])): ?><dt class="col-5 text-secondary fw-normal">Servis berikutnya</dt><dd class="col-7 fw-semibold"><?= esc(substr($ticket['next_service_at'], 0, 16)) ?></dd><?php endif; ?>
                    <dt class="col-5 text-secondary fw-normal">Material</dt><dd class="col-7 fw-semibold"><?= $money($materialCost + (float) $ticket['external_cost']) ?></dd>
                    <dt class="col-5 text-secondary fw-normal">Jasa & vendor</dt><dd class="col-7 fw-semibold"><?= $money((float) $ticket['labor_cost']) ?></dd>
                    <dt class="col-5 text-secondary fw-normal">Total biaya</dt><dd class="col-7 fw-semibold"><?= $money($materialCost + (float) $ticket['labor_cost'] + (float) $ticket['external_cost']) ?></dd>
                    <dt class="col-5 text-secondary fw-normal">Status kamar</dt><dd class="col-7 fw-semibold"><?= esc($ticket['room_status']) ?></dd>
                </dl>
            </div></div>
        </div>
    </div>

    <?php if ($editableClassification): ?>
        <div class="card border-0 mb-3"><div class="card-body">
            <h3 class="h6 mb-3">Klasifikasi Gangguan</h3>
            <form class="row g-3" data-maintenance-ticket-form hx-post="<?= site_url('ops/maintenance/work-orders/' . $ticket['id'] . '/classification') ?>" hx-target="#work-order-modal-content" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <input type="hidden" data-room-select value="<?= (int) $ticket['room_id'] ?>">
                <div class="col-md-5"><label class="form-label">Kategori kerusakan</label><select class="form-select" name="maintenance_category_id" data-category-select required><option value="">Pilih kategori</option><?php foreach ($categories as $category): ?><option value="<?= $category['id'] ?>" <?= (int) ($ticket['maintenance_category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= esc($category['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-5"><label class="form-label">Aset kamar <span class="text-secondary">(opsional)</span></label><select class="form-select" name="room_asset_id" data-asset-select <?= empty($ticket['maintenance_category_id']) ? 'disabled' : '' ?>><option value="">Tidak terkait aset tertentu</option><?php foreach ($roomAssets as $asset): ?><option value="<?= $asset['id'] ?>" data-room-id="<?= (int) $ticket['room_id'] ?>" data-category-id="<?= (int) $asset['maintenance_category_id'] ?>" <?= (int) ($ticket['room_asset_id'] ?? 0) === (int) $asset['id'] ? 'selected' : '' ?> <?= (int) ($ticket['maintenance_category_id'] ?? 0) !== (int) $asset['maintenance_category_id'] ? 'hidden disabled' : '' ?>><?= esc($asset['asset_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Simpan</button></div>
            </form>
        </div></div>
    <?php endif; ?>

    <?php if ($editableAssignment): ?>
        <div class="card border-0 mb-3"><div class="card-body">
            <h3 class="h6 mb-3">Penugasan</h3>
            <form class="row g-3" hx-post="<?= site_url('ops/maintenance/work-orders/' . $ticket['id'] . '/assign') ?>" hx-target="#work-order-modal-content" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <div class="col-md-4"><label class="form-label">Teknisi internal</label><select class="form-select" name="assigned_to_user_id"><option value="">Tidak ada</option><?php foreach ($users as $user): ?><option value="<?= $user['id'] ?>" <?= (int) ($ticket['assigned_to_user_id'] ?? 0) === (int) $user['id'] ? 'selected' : '' ?>><?= esc($user['full_name'] . ' · ' . $user['role']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Vendor eksternal</label><input class="form-control" name="vendor_name" maxlength="150" value="<?= esc($ticket['vendor_name'] ?? '') ?>"></div>
                <div class="col-md-3"><label class="form-label">Jadwal</label><input class="form-control" type="datetime-local" name="scheduled_at" value="<?= esc($dateTimeValue($ticket['scheduled_at'])) ?>"></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100 text-nowrap">Simpan</button></div>
            </form>
        </div></div>
    <?php endif; ?>

    <?php if ($editableWork): ?>
        <div class="card border-0 mb-3"><div class="card-body">
            <h3 class="h6 mb-3">Catatan & Hasil Pekerjaan</h3>
            <form class="row g-3">
                <?= csrf_field() ?>
                <?php if ($checklistItems !== []): ?>
                    <div class="col-12"><div class="border rounded-3 p-3 bg-light-subtle"><div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><label class="form-label mb-0 fw-semibold">Checklist preventive</label><small class="text-secondary">Semua item wajib dicentang sebelum selesai.</small></div><?php foreach ($checklistItems as $index => $item): ?><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="checklist_completed[]" value="<?= $index ?>" id="checklist-<?= $ticket['id'] ?>-<?= $index ?>" <?= in_array((string) $item, $checklistCompletedItems, true) ? 'checked' : '' ?>><label class="form-check-label" for="checklist-<?= $ticket['id'] ?>-<?= $index ?>"><?= esc((string) $item) ?></label></div><?php endforeach; ?></div></div>
                <?php endif; ?>
                <div class="col-md-6"><label class="form-label">Diagnosis <span class="text-danger">*</span></label><textarea class="form-control" name="diagnosis" rows="3" maxlength="4000" placeholder="Penyebab atau hasil pemeriksaan" required><?= esc($ticket['diagnosis'] ?? '') ?></textarea><div class="form-text">Wajib diisi sebelum Work Order diselesaikan.</div></div>
                <div class="col-md-6"><label class="form-label">Tindakan / penyelesaian <span class="text-danger">*</span></label><textarea class="form-control" name="resolution" rows="3" maxlength="4000" placeholder="Perbaikan yang dilakukan" required><?= esc($ticket['resolution'] ?? '') ?></textarea><div class="form-text">Wajib diisi sebelum Work Order diselesaikan.</div></div>
                <div class="col-md-6"><label class="form-label">Catatan tindak lanjut</label><textarea class="form-control" name="follow_up_notes" rows="2" maxlength="4000" placeholder="Opsional"><?= esc($ticket['follow_up_notes'] ?? '') ?></textarea></div>
                <div class="col-md-2"><label class="form-label">Biaya tenaga</label><input class="form-control" type="text" inputmode="numeric" data-currency-input name="labor_cost" value="<?= (float) ($ticket['labor_cost'] ?? 0) > 0 ? esc(number_format((float) $ticket['labor_cost'], 0, ',', '.')) : '0' ?>"></div>
                <div class="col-md-2"><label class="form-label">Biaya material</label><input class="form-control" type="text" inputmode="numeric" data-currency-input name="external_cost" value="<?= (float) ($ticket['external_cost'] ?? 0) > 0 ? esc(number_format((float) $ticket['external_cost'], 0, ',', '.')) : '0' ?>"></div>
                <div class="col-md-2 d-flex flex-column justify-content-end gap-2">
                    <button class="btn btn-outline-primary text-nowrap" formnovalidate hx-post="<?= site_url('ops/maintenance/work-orders/' . $ticket['id'] . '/work') ?>" hx-target="#work-order-modal-content" hx-swap="innerHTML">Simpan Catatan</button>
                    <?php if (in_array($status, ['In_Progress', 'Waiting_Part'], true)): ?><button class="btn btn-success text-nowrap" hx-post="<?= site_url('ops/maintenance/work-orders/' . $ticket['id'] . '/complete') ?>" hx-target="#work-order-modal-content" hx-swap="innerHTML">Selesai</button><?php endif; ?>
                </div>
            </form>
        </div></div>
    <?php elseif (! empty($ticket['diagnosis']) || ! empty($ticket['resolution'])): ?>
        <div class="card border-0 mb-3"><div class="card-body">
            <h3 class="h6 mb-3">Hasil Pekerjaan</h3>
            <div class="row g-3"><div class="col-md-6"><div class="text-secondary small">Diagnosis</div><div class="maintenance-description"><?= nl2br(esc($ticket['diagnosis'] ?? '-')) ?></div></div><div class="col-md-6"><div class="text-secondary small">Tindakan</div><div class="maintenance-description"><?= nl2br(esc($ticket['resolution'] ?? '-')) ?></div></div></div>
            <?php if (! empty($ticket['follow_up_notes'])): ?><hr><div class="text-secondary small">Tindak lanjut</div><div class="maintenance-description"><?= nl2br(esc($ticket['follow_up_notes'])) ?></div><?php endif; ?>
            <?php if ($checklistItems !== []): ?><hr><div class="text-secondary small mb-2">Checklist preventive terselesaikan</div><ul class="list-unstyled mb-0 small"><?php foreach ($checklistItems as $item): ?><li class="mb-1"><?= in_array((string) $item, $checklistCompletedItems, true) ? '✓' : '•' ?> <?= esc((string) $item) ?></li><?php endforeach; ?></ul><?php endif; ?>
        </div></div>
    <?php endif; ?>

    <?php if ($editableWork || $ticketParts !== []): ?>
        <div class="card border-0 mb-3"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h3 class="h6 mb-1">Spare Part & Material</h3><p class="small text-secondary mb-0">Pemakaian langsung mengurangi stok dan tercatat pada riwayat stok.</p></div><span class="badge text-bg-light border text-secondary">Total material: <?= $money($materialCost + (float) $ticket['external_cost']) ?></span></div>
            <?php if ($editableWork): ?>
                <?php if ($spareParts === []): ?>
                    <div class="alert alert-warning small mb-3">Belum ada spare part dengan stok tersedia. Tambahkan dari Operasional → Spare Part.</div>
                <?php else: ?>
                    <form class="row g-2 mb-3" hx-post="<?= site_url('ops/maintenance/work-orders/' . $ticket['id'] . '/parts') ?>" hx-target="#work-order-modal-content" hx-swap="innerHTML">
                        <?= csrf_field() ?>
                        <div class="col-md-5"><label class="form-label">Spare part</label><select class="form-select" name="inventory_item_id" required><option value="">Pilih spare part</option><?php foreach ($spareParts as $part): ?><option value="<?= $part['id'] ?>"><?= esc($part['name'] . ' · stok ' . $part['current_stock'] . ' ' . $part['unit'] . ' · ' . $money($part['unit_cost'])) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-2"><label class="form-label">Jumlah</label><input class="form-control" type="number" name="quantity_used" min="1" value="1" required></div>
                        <div class="col-md-3"><label class="form-label">Catatan <span class="text-secondary">(opsional)</span></label><input class="form-control" name="notes" maxlength="500" placeholder="Contoh: penggantian unit lama"></div>
                        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100 text-nowrap">Gunakan</button></div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($ticketParts === []): ?>
                <div class="text-secondary small">Belum ada spare part yang dicatat untuk Work Order ini.</div>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Spare part</th><th class="text-end">Digunakan</th><th class="text-end">Dikembalikan</th><th class="text-end">Biaya material</th><?php if ($editableWork): ?><th class="text-end">Aksi</th><?php endif; ?></tr></thead><tbody><?php foreach ($ticketParts as $ticketPart): ?><?php $remaining = (int) $ticketPart['quantity_used'] - (int) $ticketPart['quantity_returned']; ?><tr><td><div class="fw-semibold"><?= esc($ticketPart['inventory_item_name']) ?></div><?php if (! empty($ticketPart['notes'])): ?><small class="text-secondary"><?= esc($ticketPart['notes']) ?></small><?php endif; ?></td><td class="text-end"><?= esc($ticketPart['quantity_used'] . ' ' . $ticketPart['unit']) ?></td><td class="text-end"><?= esc($ticketPart['quantity_returned'] . ' ' . $ticketPart['unit']) ?></td><td class="text-end"><?= $money($remaining * (float) $ticketPart['unit_cost_snapshot']) ?></td><?php if ($editableWork): ?><td class="text-end"><?php if ($remaining > 0): ?><form class="d-inline-flex gap-1" hx-post="<?= site_url('ops/maintenance/work-orders/' . $ticket['id'] . '/parts/' . $ticketPart['id'] . '/return') ?>" hx-target="#work-order-modal-content" hx-swap="innerHTML"><?= csrf_field() ?><input class="form-control form-control-sm" type="number" name="quantity_returned" min="1" max="<?= $remaining ?>" value="1" style="width:72px"><button class="btn btn-sm btn-outline-secondary text-nowrap">Kembalikan</button></form><?php else: ?><span class="small text-secondary">Selesai</span><?php endif; ?></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </div></div>
    <?php endif; ?>

    <?php if ($canWork && in_array($status, ['Assigned', 'In_Progress', 'Waiting_Part'], true)): ?>
        <div class="card border-0 mb-3"><div class="card-body d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold me-auto">Aksi Workflow</span>
            <?php if ($status === 'Assigned' || $status === 'Waiting_Part'): ?><form hx-post="<?= site_url('ops/maintenance/work-orders/' . $ticket['id'] . '/transition') ?>" hx-target="#work-order-modal-content"><input type="hidden" name="status" value="In_Progress"><?= csrf_field() ?><button class="btn btn-primary"><?= $status === 'Assigned' ? 'Mulai Pekerjaan' : 'Lanjutkan Pekerjaan' ?></button></form><?php endif; ?>
            <?php if ($status === 'In_Progress'): ?><form hx-post="<?= site_url('ops/maintenance/work-orders/' . $ticket['id'] . '/transition') ?>" hx-target="#work-order-modal-content"><input type="hidden" name="status" value="Waiting_Part"><?= csrf_field() ?><button class="btn btn-outline-secondary">Menunggu Spare Part</button></form><?php endif; ?>
        </div></div>
    <?php endif; ?>

    <?php if ($canManage && in_array($status, ['Completed', 'Resolved', 'Verified'], true)): ?>
        <div class="card border-success mb-3"><div class="card-body d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto"><div class="fw-semibold">Tutup Work Order</div><small class="text-secondary">Tiket lama dapat langsung ditutup tanpa tahap verifikasi.</small></div>
            <?php if (in_array($status, ['Completed', 'Resolved'], true)): ?><form hx-post="<?= site_url('ops/maintenance/work-orders/' . $ticket['id'] . '/transition') ?>" hx-target="#work-order-modal-content"><?= csrf_field() ?><input type="hidden" name="status" value="Verified"><button class="btn btn-outline-success">Verifikasi Work Order</button></form><?php endif; ?>
            <form hx-post="<?= site_url('ops/maintenance/work-orders/' . $ticket['id'] . '/transition') ?>" hx-target="#work-order-modal-content"><?= csrf_field() ?><input type="hidden" name="status" value="Closed"><button class="btn btn-success">Tutup Work Order</button></form>
        </div></div>
    <?php endif; ?>

    <?php if ($canCancel && in_array($status, ['Open', 'Assigned', 'In_Progress', 'Waiting_Part'], true)): ?>
        <details class="card border-0 mb-3"><summary class="card-body maintenance-summary text-danger fw-semibold">Batalkan Work Order</summary><div class="card-body border-top pt-3"><form class="row g-2" hx-post="<?= site_url('ops/maintenance/work-orders/' . $ticket['id'] . '/transition') ?>" hx-target="#work-order-modal-content"><input type="hidden" name="status" value="Cancelled"><?= csrf_field() ?><div class="col-md-9"><input class="form-control" name="transition_note" maxlength="4000" placeholder="Alasan pembatalan (opsional)"></div><div class="col-md-3"><button class="btn btn-outline-danger w-100">Batalkan</button></div></form></div></details>
    <?php endif; ?>

    <div class="card border-0 mb-3"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3"><div><h3 class="h6 mb-1">Foto & Dokumen</h3><p class="small text-secondary mb-0">Simpan foto sebelum/sesudah pekerjaan atau invoice vendor. Foto JPG/PNG/WEBP dikompres maksimal 200 KB, PDF maksimal 5 MB.</p></div><span class="badge text-bg-light border text-secondary"><?= count($attachments) ?> lampiran</span></div>
        <?php if ($canWork && ! in_array($status, ['Verified', 'Closed', 'Cancelled'], true)): ?>
            <form class="row g-2 mb-3" data-maintenance-attachment-form data-image-upload-form enctype="multipart/form-data" hx-encoding="multipart/form-data" hx-post="<?= site_url('ops/maintenance/work-orders/' . $ticket['id'] . '/attachments') ?>" hx-target="#work-order-modal-content" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <div class="col-md-3"><label class="form-label">Jenis</label><select class="form-select" name="attachment_type" required><option value="Before">Foto sebelum</option><option value="After">Foto sesudah</option><option value="Invoice">Invoice</option><option value="Other">Lainnya</option></select></div>
                <div class="col-md-7"><label class="form-label">File</label><input class="form-control" type="file" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf" data-image-file-input required></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100 text-nowrap">Unggah</button></div>
                <div class="col-12"><small class="text-secondary" data-image-compression-status></small></div>
            </form>
        <?php endif; ?>
        <?php if ($attachments === []): ?>
            <div class="text-secondary small">Belum ada foto atau dokumen untuk Work Order ini.</div>
        <?php else: ?>
            <div class="maintenance-attachment-grid">
                <?php foreach ($attachments as $attachment): ?>
                    <?php $isImage = str_starts_with((string) $attachment['mime_type'], 'image/'); ?>
                    <a class="maintenance-attachment-card text-decoration-none text-body" href="<?= site_url('ops/maintenance/attachments/' . $attachment['id']) ?>" target="_blank" rel="noopener">
                        <?php if ($isImage): ?><img src="<?= site_url('ops/maintenance/attachments/' . $attachment['id']) ?>" alt="<?= esc($attachment['original_name'], 'attr') ?>" loading="lazy"><?php else: ?><div class="maintenance-attachment-file">PDF</div><?php endif; ?>
                        <div class="p-2 min-width-0"><div class="fw-semibold small text-truncate"><?= esc($attachment['original_name']) ?></div><div class="text-secondary small"><?= esc(match ($attachment['attachment_type']) { 'Before' => 'Sebelum', 'After' => 'Sesudah', 'Invoice' => 'Invoice', default => 'Lainnya' }) ?> · <?= number_format((int) $attachment['file_size'] / 1024, 0, ',', '.') ?> KB</div><div class="text-secondary small text-truncate"><?= esc($attachment['uploaded_by_name'] ?? 'Sistem') ?> · <?= esc(substr((string) $attachment['created_at'], 0, 16)) ?></div></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div></div>

    <div class="card border-0"><div class="card-body">
        <h3 class="h6 mb-3">Riwayat Status</h3>
        <div class="maintenance-timeline">
            <?php foreach ($logs as $log): ?>
                <div class="maintenance-timeline-item">
                    <div class="d-flex flex-column flex-sm-row justify-content-between gap-1"><strong><?= esc(str_replace('_', ' ', $log['to_status'])) ?></strong><small class="text-secondary text-nowrap"><?= esc(substr((string) $log['created_at'], 0, 16)) ?></small></div>
                    <div class="small text-secondary"><?= esc($log['changed_by_name'] ?? 'Sistem') ?><?= ! empty($log['from_status']) ? ' · dari ' . esc(str_replace('_', ' ', $log['from_status'])) : '' ?></div>
                    <?php if (! empty($log['notes'])): ?><p class="small mb-0 mt-1 maintenance-description"><?= esc($log['notes']) ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
</div>
