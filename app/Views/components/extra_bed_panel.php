<?php
$availability = $extraBedAvailability ?? ['total_stock' => 0, 'rented' => 0, 'available' => 0];
$settings = $extraBedSettings ?? ['total_stock' => 7];
$formatDate = static fn (?string $date): string => $date ? date('d/m/Y', strtotime($date)) : '-';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm"><div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
                <div><h3 class="h5 mb-1">Monitor Extra Bed</h3><p class="text-secondary mb-0">Pantau stok, pemakaian per tanggal, dan kamar yang sedang menggunakan Extra Bed.</p></div>
                <form class="d-flex gap-2" hx-get="<?= site_url('ops/panel?tab=extra_beds') ?>" hx-target="#ops-panel" hx-push-url="<?= site_url('ops?tab=extra_beds') ?>">
                    <input class="form-control" type="date" name="date" value="<?= esc($extraBedDate) ?>"><button class="btn btn-primary">Tampilkan</button>
                </form>
            </div>
        </div></div>
    </div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><div class="text-secondary">Stok total</div><div class="display-6 fw-semibold"><?= (int) $availability['total_stock'] ?></div><small class="text-secondary">unit Extra Bed</small></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><div class="text-secondary">Disewa pada <?= esc($formatDate($extraBedDate)) ?></div><div class="display-6 fw-semibold text-warning"><?= (int) $availability['rented'] ?></div><small class="text-secondary">unit sedang digunakan</small></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><div class="text-secondary">Tersedia</div><div class="display-6 fw-semibold text-success"><?= (int) $availability['available'] ?></div><small class="text-secondary">unit bisa disewakan</small></div></div></div>
    <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body p-4">
        <h3 class="h5 mb-1">Atur stok Extra Bed</h3><p class="text-secondary">Stok awal saat ini: <?= (int) $settings['total_stock'] ?>. Stok tidak dapat diturunkan di bawah alokasi aktif tertinggi.</p>
        <form class="row g-3 align-items-end" hx-post="<?= site_url('ops/extra-beds/settings') ?>" hx-target="#ops-feedback" hx-swap="innerHTML">
            <?= csrf_field() ?><div class="col-sm-4"><label class="form-label">Jumlah stok</label><input class="form-control" type="number" min="0" name="total_stock" value="<?= (int) $settings['total_stock'] ?>" required></div><div class="col-sm-3"><button class="btn btn-primary w-100">Simpan stok</button></div>
        </form>
    </div></div></div>
    <div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-header bg-white py-3"><h3 class="h6 mb-0">Extra Bed di kamar — <?= esc($formatDate($extraBedDate)) ?></h3></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Kamar</th><th>Tamu</th><th>Jumlah</th><th>Periode</th><th>Booking</th></tr></thead><tbody>
        <?php if (($extraBedAllocations ?? []) === []): ?><tr><td colspan="5" class="text-center text-secondary py-5">Belum ada Extra Bed yang disewa pada tanggal ini.</td></tr><?php endif; ?>
        <?php foreach ($extraBedAllocations ?? [] as $allocation): ?><tr><td class="fw-semibold">Room <?= esc($allocation['room_number']) ?></td><td><?= esc($allocation['guest_name'] ?? '-') ?></td><td><?= (int) $allocation['quantity'] ?></td><td><?= esc($formatDate($allocation['check_in_date'])) ?>–<?= esc($formatDate($allocation['check_out_date'])) ?></td><td><small><?= esc($allocation['order_id']) ?></small></td></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
    <div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-header bg-white py-3"><h3 class="h6 mb-0">Alokasi mendatang</h3></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Check-in</th><th>Kamar</th><th>Jumlah</th></tr></thead><tbody>
        <?php if (($extraBedUpcoming ?? []) === []): ?><tr><td colspan="3" class="text-center text-secondary py-5">Belum ada alokasi mendatang.</td></tr><?php endif; ?>
        <?php foreach (array_slice($extraBedUpcoming ?? [], 0, 20) as $allocation): ?><tr><td><?= esc($formatDate($allocation['check_in_date'])) ?><small class="d-block text-secondary"><?= esc($allocation['guest_name'] ?? '-') ?></small></td><td>Room <?= esc($allocation['room_number']) ?></td><td><?= (int) $allocation['quantity'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
</div>
