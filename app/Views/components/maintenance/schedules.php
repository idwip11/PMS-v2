<?php
$role = (string) session('role');
$canEdit = in_array($role, ['Atmin', 'Admin', 'Manager', 'Operational'], true);
$canGenerate = in_array($role, ['Atmin', 'Admin', 'Manager'], true);
$now = new DateTimeImmutable('now', new DateTimeZone(config('App')->appTimezone));
$dateTimeValue = static fn ($value): string => empty($value) ? '' : date('Y-m-d\TH:i', strtotime((string) $value));
$scheduleStatus = static function (array $schedule) use ($now): array {
    if ((int) $schedule['is_active'] !== 1) return ['Nonaktif', 'text-bg-secondary'];
    $due = new DateTimeImmutable((string) $schedule['next_due_at'], new DateTimeZone(config('App')->appTimezone));
    if ($due->format('Y-m-d') < $now->format('Y-m-d')) return ['Terlambat', 'text-bg-danger'];
    if ($due->format('Y-m-d') === $now->format('Y-m-d')) return ['Hari ini', 'text-bg-warning'];
    if ($due <= $now->modify('+' . max(0, (int) $schedule['reminder_days']) . ' days')) return ['Segera', 'text-bg-info'];
    return ['Terjadwal', 'text-bg-success'];
};
?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Terlambat</div><div class="display-6 fw-semibold text-danger"><?= $scheduleStats['overdue'] ?></div><div class="small text-secondary">Perlu ditangani</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Jatuh tempo hari ini</div><div class="display-6 fw-semibold text-warning-emphasis"><?= $scheduleStats['due_today'] ?></div><div class="small text-secondary">Masuk Work Order</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Dalam pengingat</div><div class="display-6 fw-semibold text-primary"><?= $scheduleStats['due_soon'] ?></div><div class="small text-secondary">Mendekati jadwal</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Jadwal aktif</div><div class="display-6 fw-semibold"><?= $scheduleStats['active'] ?></div><div class="small text-secondary">Preventive maintenance</div></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h3 class="h5 mb-1">Jadwal Rutin Otomatis</h3><p class="text-secondary mb-0">Jadwal preventive dibuat atau diperbarui otomatis saat Work Order kerusakan ditutup. Sistem memakai aset, kategori, interval, dan checklist default dari menu Kategori. Jadwal yang mendekati jatuh tempo akan muncul pada daftar di bawah ini.</p></div></div>

<?php if (false): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
            <div><h3 class="h5 mb-1">Tambah Jadwal Rutin</h3><p class="small text-secondary mb-0">Satu jadwal berlaku untuk satu aset kamar. Work Order dibuat saat masuk periode pengingat.</p></div>
            <?php if ($canGenerate): ?><form hx-post="<?= site_url('ops/maintenance/schedules/generate') ?>" hx-target="#ops-feedback" hx-swap="innerHTML"><?= csrf_field() ?><button class="btn btn-outline-primary text-nowrap">Generate Work Order Jatuh Tempo</button></form><?php endif; ?>
        </div>
        <?php if ($roomAssets === []): ?>
            <div class="alert alert-warning mb-0">Tambahkan aset kamar terlebih dahulu sebelum membuat jadwal rutin.</div>
        <?php else: ?>
            <form class="row g-3 schedule-create-form" hx-post="<?= site_url('ops/maintenance/schedules') ?>" hx-target="#ops-feedback" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <div class="col-md-4"><label class="form-label">Aset kamar</label><select class="form-select" name="room_asset_id" required><option value="">Pilih aset</option><?php foreach ($roomAssets as $asset): ?><option value="<?= $asset['id'] ?>"><?= esc($asset['room_number'] . ' · ' . $asset['category_name'] . ' · ' . $asset['asset_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Nama pekerjaan rutin</label><input class="form-control" name="title" maxlength="160" placeholder="Contoh: Servis filter AC" required></div>
                <div class="col-md-2"><label class="form-label">Interval</label><input class="form-control" type="number" name="frequency_value" min="1" value="3" required></div>
                <div class="col-md-2"><label class="form-label">Satuan</label><select class="form-select" name="frequency_unit"><option value="Day">Hari</option><option value="Week">Minggu</option><option value="Month" selected>Bulan</option><option value="Year">Tahun</option></select></div>
                <div class="col-md-3"><label class="form-label">Perawatan terakhir <span class="text-secondary">(opsional)</span></label><input class="form-control" type="datetime-local" name="last_completed_at" value="<?= esc($dateTimeValue(date('Y-m-d H:i:s'))) ?>"></div>
                <div class="col-md-3"><label class="form-label">Jatuh tempo berikutnya <span class="text-secondary">(opsional)</span></label><input class="form-control" type="datetime-local" name="next_due_at"><div class="form-text">Kosongkan untuk hitung otomatis.</div></div>
                <div class="col-md-2"><label class="form-label">Pengingat (hari)</label><input class="form-control" type="number" name="reminder_days" min="0" value="7" required></div>
                <div class="col-md-2"><label class="form-label">Teknisi default</label><select class="form-select" name="default_assignee_user_id"><option value="">Belum ditentukan</option><?php foreach ($users as $user): ?><option value="<?= $user['id'] ?>"><?= esc($user['full_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Vendor default</label><input class="form-control" name="default_vendor" maxlength="150" placeholder="Opsional"></div>
                <div class="col-md-8"><label class="form-label">Checklist pekerjaan <span class="text-secondary">(satu baris satu item)</span></label><textarea class="form-control" name="checklist" rows="3" maxlength="8000" placeholder="Matikan unit dan cek panel\nBersihkan filter\nUji fungsi setelah servis"></textarea></div>
                <div class="col-md-2 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="blocks_room" value="1" id="schedule-blocks-room"><label class="form-check-label" for="schedule-blocks-room">Blokir kamar</label></div></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100 text-nowrap">Simpan Jadwal</button></div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php elseif (false): ?>
    <form class="mb-4" hx-post="<?= site_url('ops/maintenance/schedules/generate') ?>" hx-target="#ops-feedback" hx-swap="innerHTML"><?= csrf_field() ?><button class="btn btn-outline-primary">Generate Work Order Jatuh Tempo</button></form>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2"><h3 class="h6 mb-0">Daftar Jadwal Rutin</h3><span class="badge text-bg-light border text-secondary"><?= count($items) ?> jadwal</span></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0 maintenance-schedule-table">
            <thead><tr><th>Aset</th><th>Pekerjaan & Interval</th><th>Jatuh tempo</th><th>Penanggung jawab</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
                <?php if ($items === []): ?><tr><td colspan="6" class="text-center text-secondary py-5">Belum ada jadwal preventive maintenance.</td></tr><?php endif; ?>
                <?php foreach ($items as $schedule): ?>
                    <?php [$label, $class] = $scheduleStatus($schedule); ?>
                    <tr>
                        <td><div class="fw-semibold"><?= esc($schedule['room_number'] . ' · ' . $schedule['asset_name']) ?></div><small class="text-secondary"><?= esc($schedule['category_name']) ?></small></td>
                        <td><div><?= esc($schedule['title']) ?></div><small class="text-secondary">Setiap <?= esc($schedule['frequency_value']) ?> <?= esc(match ($schedule['frequency_unit']) { 'Day' => 'hari', 'Week' => 'minggu', 'Year' => 'tahun', default => 'bulan' }) ?></small></td>
                        <td><div class="text-nowrap"><?= esc(substr((string) $schedule['next_due_at'], 0, 16)) ?></div><small class="text-secondary">Pengingat <?= esc($schedule['reminder_days']) ?> hari</small></td>
                        <td><div><?= esc($schedule['assignee_name'] ?? $schedule['default_vendor'] ?? '-') ?></div><?php if ((int) $schedule['blocks_room'] === 1): ?><small class="text-danger">Memblokir kamar</small><?php endif; ?></td>
                        <td><span class="badge <?= $class ?>"><?= esc($label) ?></span></td>
                        <td class="text-end"><button class="btn btn-sm btn-outline-primary text-nowrap" hx-get="<?= site_url('ops/maintenance/schedules/' . $schedule['id']) ?>" hx-target="#modal-container" hx-swap="innerHTML">Detail</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
