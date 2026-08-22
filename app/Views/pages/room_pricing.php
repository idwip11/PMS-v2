<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="post" action="<?= site_url('room-pricing/all') ?>" class="row g-3 align-items-end" onsubmit="return confirm('Terapkan harga ini ke semua kamar?')">
            <?= csrf_field() ?>
            <div class="col-lg-7">
                <label class="form-label fw-semibold" for="bulk-room-price">Set Semua Kamar</label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input id="bulk-room-price" class="form-control" type="text" inputmode="numeric" data-currency-input name="bulk_price_per_night" placeholder="Masukkan harga per malam" required>
                </div>
                <small class="text-secondary">Nominal ini akan diterapkan ke seluruh <?= count($rooms) ?> kamar.</small>
            </div>
            <div class="col-lg-5 text-lg-end">
                <button class="btn btn-primary" type="submit">Terapkan ke Semua Kamar</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <div>
            <span class="fw-semibold">Daftar Harga Kamar</span>
            <small class="d-block text-secondary">Harga disimpan langsung ke database dan berlaku untuk booking berikutnya.</small>
        </div>
        <span class="badge text-bg-light border text-secondary"><?= count($rooms) ?> kamar</span>
    </div>
    <div class="table-responsive mt-3 border-top">
        <table class="table table-sm table-hover align-middle mb-0 front-desk-table small">
            <thead class="table-light">
                <tr><th>Kamar</th><th>Tipe</th><th>Guesthouse</th><th>Harga per malam</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td class="fw-semibold">Room <?= esc($room['room_number']) ?></td>
                        <td><?= esc($room['room_type']) ?></td>
                        <td>GH_<?= esc((string) $room['floor']) ?></td>
                        <td>
                            <form id="room-price-<?= (int) $room['id'] ?>" method="post" action="<?= site_url('room-pricing/' . $room['id']) ?>" class="d-flex align-items-center gap-2">
                                <?= csrf_field() ?>
                                <span class="text-secondary">Rp</span>
                                <input class="form-control room-price-input" type="text" inputmode="numeric" data-currency-input name="price_per_night" value="<?= esc((string) (int) $room['price_per_night']) ?>" required aria-label="Harga kamar <?= esc($room['room_number']) ?>">
                            </form>
                        </td>
                        <td class="text-end"><button class="btn btn-outline-primary btn-sm" type="submit" form="room-price-<?= (int) $room['id'] ?>">Simpan</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
