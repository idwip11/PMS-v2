<?php
$roomCounts = array_fill_keys(['Available', 'Dirty', 'Maintenance', 'Occupied'], 0);
foreach ($rooms as $room) $roomCounts[$room['status']] = ($roomCounts[$room['status']] ?? 0) + 1;
$roomStatusClass = static fn (string $status): string => match ($status) {
    'Available' => 'text-bg-success',
    'Dirty' => 'text-bg-warning',
    'Maintenance' => 'text-bg-danger',
    'Occupied' => 'text-bg-primary',
    default => 'text-bg-secondary',
};
$taskStatusClass = static fn (string $status): string => match ($status) {
    'Completed' => 'text-bg-success',
    'In_Progress' => 'text-bg-primary',
    default => 'text-bg-warning',
};
?>
<div class="row g-3 mb-4">
    <?php foreach ([['Total Rooms', count($rooms), 'primary'], ['Available', $roomCounts['Available'], 'success'], ['Occupied', $roomCounts['Occupied'], 'primary'], ['Dirty', $roomCounts['Dirty'], 'warning'], ['Maintenance', $roomCounts['Maintenance'], 'danger']] as [$label, $value, $color]): ?>
        <div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-secondary"><?= esc($label) ?></small><div class="display-6 fw-semibold text-<?= $color ?>"><?= $value ?></div></div></div></div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3"><h3 class="h6 mb-0">Status Kamar</h3></div>
    <div class="card-body"><div class="row g-3">
        <?php foreach ($rooms as $room): ?>
            <div class="col-sm-6 col-lg-4 col-xxl-3"><div class="border rounded-3 p-3 h-100 bg-light-subtle">
                <div class="d-flex justify-content-between align-items-start mb-3"><div><div class="h5 mb-0">Room <?= esc($room['room_number']) ?></div><small class="text-secondary"><?= esc($room['room_type']) ?></small></div><span class="badge <?= $roomStatusClass($room['status']) ?>"><?= esc($room['status']) ?></span></div>
                <form class="d-flex gap-2" hx-post="<?= site_url('housekeeping/rooms/' . $room['id'] . '/status') ?>" hx-target="#housekeeping-feedback">
                    <?= csrf_field() ?>
                    <select class="form-select form-select-sm" name="status"><?php foreach (['Available', 'Dirty', 'Maintenance', 'Occupied'] as $status): ?><option <?= $room['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select>
                    <button class="btn btn-sm btn-outline-primary">Update</button>
                </form>
            </div></div>
        <?php endforeach; ?>
    </div></div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h3 class="h5 mb-3">Buat Cleaning Task</h3>
        <form class="row g-3" hx-post="<?= site_url('housekeeping/tasks') ?>" hx-target="#housekeeping-feedback" hx-swap="innerHTML">
            <?= csrf_field() ?>
            <div class="col-md-4"><label class="form-label">Deskripsi</label><input class="form-control" name="description" required maxlength="255" placeholder="Deep cleaning, ganti linen..."></div>
            <div class="col-md-2"><label class="form-label">Kamar/Area</label><input class="form-control" name="room_area" required maxlength="100" placeholder="Room 101"></div>
            <div class="col-md-2"><label class="form-label">Tanggal</label><input class="form-control" type="date" name="scheduled_date" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-2"><label class="form-label">Prioritas</label><select class="form-select" name="priority"><option>Low</option><option selected>Medium</option><option>High</option><option>Urgent</option></select></div>
            <div class="col-md-2"><label class="form-label">Assign ke</label><select class="form-select" name="user_id"><option value="0">Belum ditentukan</option><?php foreach ($users as $user): ?><option value="<?= $user['id'] ?>"><?= esc($user['full_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-12 text-end"><button class="btn btn-primary">Buat Task</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3"><h3 class="h6 mb-0">Cleaning Tasks</h3></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Tanggal</th><th>Area</th><th>Task</th><th>Petugas</th><th>Prioritas</th><th style="width:230px">Status</th><th style="width:90px">Aksi</th></tr></thead><tbody>
        <?php if ($tasks === []): ?><tr><td colspan="7" class="text-center text-secondary py-5">Belum ada cleaning task.</td></tr><?php endif; ?>
        <?php foreach ($tasks as $task): ?><tr><td><?= esc($task['scheduled_date']) ?></td><td class="fw-semibold"><?= esc($task['room_area']) ?></td><td><?= esc($task['description']) ?></td><td><?= esc($task['assignees'] ?: 'Belum ditentukan') ?></td><td><span class="badge <?= $task['priority'] === 'Urgent' || $task['priority'] === 'High' ? 'text-bg-danger' : 'text-bg-secondary' ?>"><?= esc($task['priority']) ?></span></td><td><form class="d-flex gap-2" hx-post="<?= site_url('housekeeping/tasks/' . $task['id'] . '/status') ?>" hx-target="#housekeeping-feedback"><?= csrf_field() ?><select class="form-select form-select-sm" name="status"><?php foreach (['Pending' => 'Pending', 'In_Progress' => 'In Progress', 'Completed' => 'Completed'] as $value => $label): ?><option value="<?= $value ?>" <?= $task['status'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-primary">Update</button></form></td><td><form hx-post="<?= site_url('housekeeping/tasks/' . $task['id'] . '/delete') ?>" hx-target="#housekeeping-feedback" hx-confirm="Hapus cleaning task ini?"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus task" aria-label="Hapus task"><span aria-hidden="true">🗑</span></button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
