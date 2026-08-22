<?php
$money = static fn ($value): string => 'Rp' . number_format((float) $value, 0, ',', '.');
$duration = static function (int $minutes): string {
    if ($minutes <= 0) return '-';
    if ($minutes < 60) return $minutes . ' menit';
    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;
    return $hours . ' jam' . ($remaining > 0 ? ' ' . $remaining . ' menit' : '');
};
$tickets = $summary['tickets'] ?? [];
$schedules = $summary['schedules'] ?? [];
?>
<div class="row g-3 mb-4 maintenance-kpi-grid">
    <div class="col-sm-6 col-xl"><a class="card border-0 shadow-sm h-100 text-decoration-none text-body" href="<?= site_url('ops?tab=maintenance&section=tickets') ?>"><div class="card-body"><small class="text-secondary">Tiket terbuka</small><div class="display-6 fw-semibold mt-1"><?= (int) ($tickets['open'] ?? 0) ?></div><span class="small text-secondary">Belum ditugaskan</span></div></a></div>
    <div class="col-sm-6 col-xl"><a class="card border-0 shadow-sm h-100 text-decoration-none text-body" href="<?= site_url('ops?tab=maintenance&section=tickets') ?>"><div class="card-body"><small class="text-secondary">Sedang dikerjakan</small><div class="display-6 fw-semibold mt-1 text-primary"><?= (int) (($tickets['assigned'] ?? 0) + ($tickets['in_progress'] ?? 0) + ($tickets['waiting_part'] ?? 0)) ?></div><span class="small text-secondary"><?= (int) ($tickets['waiting_part'] ?? 0) ?> menunggu part</span></div></a></div>
    <div class="col-sm-6 col-xl"><a class="card border-0 shadow-sm h-100 text-decoration-none text-body" href="<?= site_url('ops?tab=maintenance&section=schedules') ?>"><div class="card-body"><small class="text-secondary">Jadwal terlambat</small><div class="display-6 fw-semibold mt-1 <?= (int) ($schedules['overdue'] ?? 0) > 0 ? 'text-danger' : 'text-success' ?>"><?= (int) ($schedules['overdue'] ?? 0) ?></div><span class="small text-secondary"><?= (int) ($schedules['due_7'] ?? 0) ?> jatuh tempo ≤ 7 hari</span></div></a></div>
    <div class="col-sm-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-secondary">Kamar diblokir</small><div class="display-6 fw-semibold mt-1"><?= (int) ($summary['blocked_rooms'] ?? 0) ?></div><span class="small text-secondary">Status Maintenance</span></div></div></div>
    <div class="col-sm-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-secondary">Biaya bulan ini</small><div class="h4 fw-semibold mt-2 mb-1"><?= $money($summary['month_cost'] ?? 0) ?></div><span class="small text-secondary">Material, jasa, dan vendor</span></div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2"><div><h3 class="h6 mb-1">Prioritas Work Order Aktif</h3><small class="text-secondary">Pekerjaan lama dan berprioritas tinggi perlu ditindaklanjuti lebih dahulu.</small></div><a class="btn btn-sm btn-outline-primary" href="<?= site_url('ops?tab=maintenance&section=tickets') ?>">Buka Tiket Aktif</a></div>
            <div class="table-responsive"><table class="table align-middle mb-0 maintenance-summary-table"><thead><tr><th>Work Order</th><th>Kamar</th><th>Kategori & Aset</th><th>Prioritas</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
                <?php if (($summary['active_tickets'] ?? []) === []): ?><tr><td colspan="6" class="text-center text-secondary py-5">Tidak ada Work Order aktif.</td></tr><?php endif; ?>
                <?php foreach (($summary['active_tickets'] ?? []) as $item): ?><tr><td class="fw-semibold text-nowrap"><?= esc($item['ticket_number'] ?? ('WO-' . $item['id'])) ?></td><td><?= esc($item['room_number']) ?></td><td><div><?= esc($item['category_name'] ?? '-') ?></div><small class="text-secondary"><?= esc($item['asset_name'] ?? 'Tanpa aset spesifik') ?></small></td><td><?= esc($item['priority']) ?></td><td><span class="badge text-bg-light border text-secondary"><?= esc(str_replace('_', ' ', $item['status'])) ?></span></td><td class="text-end"><button class="btn btn-sm btn-outline-primary" hx-get="<?= site_url('ops/maintenance/work-orders/' . $item['id']) ?>" hx-target="#modal-container" hx-swap="innerHTML">Detail</button></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4"><div class="card-body"><h3 class="h6 mb-3">Indikator Operasional</h3><div class="d-flex justify-content-between py-2 border-bottom"><span>Jatuh tempo 8–30 hari</span><strong><?= (int) ($schedules['due_30'] ?? 0) ?></strong></div><div class="d-flex justify-content-between py-2 border-bottom"><span>Rata-rata pengerjaan aktif</span><strong><?= esc($duration((int) ($summary['mttr_minutes'] ?? 0))) ?></strong></div><small class="d-block text-secondary pb-2 border-bottom">Dari <?= (int) ($summary['mttr_ticket_count'] ?? 0) ?> work order Closed; waktu menunggu tidak dihitung.</small><div class="d-flex justify-content-between pt-2"><span>Spare part stok minimum</span><strong><?= (int) ($summary['low_stock_part_count'] ?? 0) ?></strong></div></div></div>
        <div class="card border-0 shadow-sm"><div class="card-body"><h3 class="h6 mb-3">Perhatian</h3><?php if (($summary['repeat_assets'] ?? []) === [] && ($summary['low_stock_parts'] ?? []) === []): ?><p class="text-secondary small mb-0">Belum ada aset berulang atau spare part minimum.</p><?php endif; ?><?php foreach (($summary['repeat_assets'] ?? []) as $asset): ?><div class="mb-3"><div class="fw-semibold small">Kamar <?= esc($asset['room_number']) ?> · <?= esc($asset['asset_name']) ?></div><div class="text-secondary small"><?= esc($asset['category_name'] ?? '-') ?> · <?= (int) $asset['incident_count'] ?> gangguan/12 bulan</div></div><?php endforeach; ?><?php foreach (($summary['low_stock_parts'] ?? []) as $part): ?><div class="d-flex justify-content-between gap-2 small mb-2"><span><?= esc($part['name']) ?></span><span class="text-danger text-nowrap"><?= esc($part['current_stock'] . ' ' . $part['unit']) ?></span></div><?php endforeach; ?></div></div>
    </div>
</div>
