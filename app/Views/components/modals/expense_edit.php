<?php
$selectedGuesthouses = array_flip($guesthouses);
$isHomiq = \App\Libraries\PropertyContext::activeId() === 1;
$isKos = \App\Controllers\OpsController::isKosProperty();
$changes = static function (array $audit) use ($isKos): array {
    $before = json_decode((string) ($audit['before_data'] ?? ''), true) ?: [];
    $after = json_decode((string) ($audit['after_data'] ?? ''), true) ?: [];
    $labels = ['description' => 'Deskripsi', 'category' => 'Kategori', 'amount' => 'Nominal', 'date_incurred' => 'Tanggal', 'notes' => 'Catatan', 'guesthouses' => ($isKos ? 'Kamar' : 'Guesthouse'), 'receipt_url' => 'Bukti invoice'];
    $result = [];
    foreach ($labels as $key => $label) if (($before[$key] ?? null) !== ($after[$key] ?? null)) $result[] = $label;
    return $result;
};
?>
<div class="modal fade" id="dynamicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content border-0">
        <div class="modal-header"><div><h2 class="modal-title h5 mb-0">Edit Expense</h2><small class="text-secondary">Perubahan dicatat pada audit expense.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <form class="expense-edit-form" data-expense-form data-image-upload-form enctype="multipart/form-data" hx-encoding="multipart/form-data" hx-post="<?= site_url('ops/expenses/' . $expense['id']) ?>" hx-target="#ops-feedback" hx-swap="innerHTML">
            <?= csrf_field() ?>
            <div class="modal-body p-4"><div class="row g-3">
                <div class="col-md-6"><label class="form-label">Deskripsi</label><input class="form-control" name="description" value="<?= esc($expense['description']) ?>" maxlength="255" required></div>
                <div class="col-md-3"><label class="form-label">Kategori</label><select class="form-select" name="category" required><?php foreach ($expenseCategories as $category): ?><option value="<?= esc($category) ?>" <?= ($expense['category'] === $category || ($category === 'Kebutuhan Operasional Kos' && $expense['category'] === 'Kebutuhan Operasional Guesthouse')) ? 'selected' : '' ?>><?= esc($category) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Nominal</label><input class="form-control" type="text" inputmode="numeric" data-currency-input name="amount" value="<?= esc(number_format((float) $expense['amount'], 0, ',', '.')) ?>" required></div>
                <div class="col-md-4"><label class="form-label">Tanggal</label><input class="form-control" type="date" name="date_incurred" value="<?= esc($expense['date_incurred']) ?>" required></div>
                <?php if ($isHomiq): ?>
                    <div class="col-md-8"><label class="form-label">Guesthouse <span class="text-secondary">(opsional)</span></label><select class="form-select" name="guesthouses[]" multiple size="6"><?php for ($guesthouse = 1; $guesthouse <= 5; $guesthouse++): ?><option value="<?= $guesthouse ?>" <?= isset($selectedGuesthouses[$guesthouse]) ? 'selected' : '' ?>>GH <?= $guesthouse ?></option><?php endfor; ?><option value="0" <?= $guesthouses === [] && (int) ($expense['guesthouse'] ?? 0) === 0 ? 'selected' : '' ?>>Perusahaan</option></select><div class="form-text">Pilih lebih dari satu dengan Cmd/Ctrl + klik.</div></div>
                <?php elseif ($isKos): ?>
                    <div class="col-md-8"><label class="form-label">Kamar <span class="text-secondary">(opsional)</span></label><select class="form-select" name="guesthouses[]" multiple size="6"><?php for ($kamar = 1; $kamar <= 7; $kamar++): ?><option value="<?= $kamar ?>" <?= isset($selectedGuesthouses[$kamar]) ? 'selected' : '' ?>>Kamar <?= $kamar ?></option><?php endfor; ?><option value="0" <?= $guesthouses === [] && (int) ($expense['guesthouse'] ?? 0) === 0 ? 'selected' : '' ?>>Perusahaan</option></select><div class="form-text">Pilih lebih dari satu dengan Cmd/Ctrl + klik.</div></div>
                <?php else: ?>
                    <div class="col-md-8"><label class="form-label">Properti</label><input class="form-control" value="<?= esc((string) session('activePropertyName')) ?>" readonly><div class="form-text">Biaya hanya dicatat untuk properti aktif.</div></div>
                <?php endif; ?>
                <div class="col-12"><label class="form-label">Catatan</label><input class="form-control" name="notes" value="<?= esc($expense['notes'] ?? '') ?>" placeholder="Opsional"></div>
                <div class="col-12"><label class="form-label">Ganti bukti invoice <span class="text-secondary">(opsional)</span></label><input class="form-control" type="file" name="receipt" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" data-image-file-input><div class="form-text" data-image-compression-status><?= empty($expense['receipt_url']) ? 'Belum ada bukti.' : 'Pilih file baru hanya bila bukti ingin diganti.' ?></div></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan Perubahan</button></div>
        </form>
        <div class="border-top p-4"><h3 class="h6">Riwayat Audit</h3><?php if ($auditLogs === []): ?><p class="small text-secondary mb-0">Belum ada perubahan pada expense ini.</p><?php else: ?><ul class="list-group list-group-flush"><?php foreach ($auditLogs as $audit): ?><?php $fields = $changes($audit); ?><li class="list-group-item px-0"><div class="fw-semibold small"><?= esc($audit['changed_by_name'] ?? 'Sistem') ?> · <?= esc($audit['created_at']) ?></div><div class="small text-secondary">Mengubah: <?= esc($fields === [] ? 'data expense' : implode(', ', $fields)) ?></div></li><?php endforeach; ?></ul><?php endif; ?></div>
    </div></div>
</div>
