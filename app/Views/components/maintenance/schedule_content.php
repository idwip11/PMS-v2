<?php
$role = (string) session('role');
$canEdit = in_array($role, ['Atmin', 'Admin', 'Manager', 'Operational'], true);
$dateTimeValue = static fn ($value): string => empty($value) ? '' : date('Y-m-d\TH:i', strtotime((string) $value));
$checklist = json_decode((string) ($schedule['checklist'] ?? ''), true);
if (! is_array($checklist)) $checklist = [];
?>
<div class="modal-header">
    <div><h2 class="modal-title fs-5" id="schedule-title"><?= esc($schedule['title']) ?></h2><div class="small text-secondary"><?= esc($schedule['room_number'] . ' · ' . $schedule['category_name'] . ' · ' . $schedule['asset_name']) ?></div></div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
</div>
<div class="modal-body bg-body-tertiary">
    <?php if ($feedbackMessage): ?><div class="alert alert-<?= esc($feedbackType ?? 'info') ?>"><?= esc($feedbackMessage) ?></div><?php endif; ?>
    <?php if ($canEdit): ?>
        <form class="row g-3" hx-post="<?= site_url('ops/maintenance/schedules/' . $schedule['id']) ?>" hx-target="#schedule-modal-content" hx-swap="innerHTML">
            <?= csrf_field() ?>
            <div class="col-md-6"><label class="form-label">Aset kamar</label><select class="form-select" name="room_asset_id" required><?php foreach ($roomAssets as $asset): ?><option value="<?= $asset['id'] ?>" <?= (int) $schedule['room_asset_id'] === (int) $asset['id'] ? 'selected' : '' ?>><?= esc($asset['room_number'] . ' · ' . $asset['category_name'] . ' · ' . $asset['asset_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Nama pekerjaan rutin</label><input class="form-control" name="title" maxlength="160" value="<?= esc($schedule['title']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Interval</label><input class="form-control" type="number" name="frequency_value" min="1" value="<?= esc($schedule['frequency_value']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Satuan</label><select class="form-select" name="frequency_unit"><?php foreach (['Day' => 'Hari', 'Week' => 'Minggu', 'Month' => 'Bulan', 'Year' => 'Tahun'] as $value => $label): ?><option value="<?= $value ?>" <?= $schedule['frequency_unit'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Pengingat (hari)</label><input class="form-control" type="number" name="reminder_days" min="0" value="<?= esc($schedule['reminder_days']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="is_active"><option value="1" <?= (int) $schedule['is_active'] === 1 ? 'selected' : '' ?>>Aktif</option><option value="0" <?= (int) $schedule['is_active'] !== 1 ? 'selected' : '' ?>>Nonaktif</option></select></div>
            <div class="col-md-6"><label class="form-label">Perawatan terakhir</label><input class="form-control" type="datetime-local" name="last_completed_at" value="<?= esc($dateTimeValue($schedule['last_completed_at'])) ?>"></div>
            <div class="col-md-6"><label class="form-label">Jatuh tempo berikutnya</label><input class="form-control" type="datetime-local" name="next_due_at" value="<?= esc($dateTimeValue($schedule['next_due_at'])) ?>"></div>
            <div class="col-md-6"><label class="form-label">Teknisi default</label><select class="form-select" name="default_assignee_user_id"><option value="">Belum ditentukan</option><?php foreach ($users as $user): ?><option value="<?= $user['id'] ?>" <?= (int) ($schedule['default_assignee_user_id'] ?? 0) === (int) $user['id'] ? 'selected' : '' ?>><?= esc($user['full_name'] . ' · ' . $user['role']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Vendor default</label><input class="form-control" name="default_vendor" maxlength="150" value="<?= esc($schedule['default_vendor'] ?? '') ?>"></div>
            <div class="col-12"><label class="form-label">Checklist <span class="text-secondary">(satu baris satu item)</span></label><textarea class="form-control" name="checklist" rows="5" maxlength="8000"><?= esc(implode("\n", $checklist)) ?></textarea></div>
            <div class="col-md-7 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="blocks_room" value="1" id="edit-schedule-blocks" <?= (int) $schedule['blocks_room'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="edit-schedule-blocks">Blokir kamar saat pekerjaan preventive dimulai</label></div></div>
            <div class="col-md-5 d-flex justify-content-end align-items-end"><button class="btn btn-primary text-nowrap">Simpan Perubahan</button></div>
        </form>
    <?php else: ?>
        <div class="alert alert-secondary mb-0">Anda hanya memiliki akses baca untuk jadwal ini.</div>
    <?php endif; ?>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button></div>
