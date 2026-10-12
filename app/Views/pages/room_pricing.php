<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="post" action="<?= site_url('room-pricing/all') ?>" class="row g-3 align-items-end" onsubmit="return confirm('<?= $pricing['pricing_mode'] === 'whole_home_nightly' ? 'Simpan tarif sewa satu rumah ini?' : ($pricing['pricing_mode'] === 'room_monthly' ? 'Simpan tarif kos ini?' : 'Terapkan harga ini ke semua kamar?') ?>')">
            <?= csrf_field() ?>
            <div class="col-lg-7">
                <label class="form-label fw-semibold" for="bulk-room-price"><?= $pricing['pricing_mode'] === 'whole_home_nightly' ? 'Tarif satu rumah per malam' : ($pricing['pricing_mode'] === 'room_monthly' ? 'Tarif per kamar per bulan' : 'Set Semua Kamar') ?></label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input id="bulk-room-price" class="form-control" type="text" inputmode="numeric" data-currency-input name="bulk_price_per_night" value="<?= $pricing['pricing_mode'] === 'room_nightly' ? '' : esc(number_format((float) $pricing['base_rate'], 0, ',', '.')) ?>" placeholder="Masukkan tarif" required>
                </div>
                <small class="text-secondary"><?= $pricing['pricing_mode'] === 'whole_home_nightly' ? 'Satu reservasi selalu memblokir seluruh rumah (3 kamar); harga tidak dikalikan 3.' : ($pricing['pricing_mode'] === 'room_monthly' ? 'Tarif berlaku untuk setiap kamar, per bulan sejak tanggal masuk.' : 'Nominal ini akan diterapkan ke seluruh ' . count($rooms) . ' kamar.') ?></small>
            </div>
            <div class="col-lg-5 text-lg-end">
                <button class="btn btn-primary" type="submit"><?= $pricing['pricing_mode'] === 'room_nightly' ? 'Terapkan ke Semua Kamar' : 'Simpan Tarif Properti' ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <div>
            <span class="fw-semibold"><?= $pricing['pricing_mode'] === 'whole_home_nightly' ? 'Daftar Tarif Unit' : 'Daftar Harga Kamar' ?></span>
            <small class="d-block text-secondary"><?= $pricing['pricing_mode'] === 'whole_home_nightly' ? 'Tarif berlaku untuk pemesanan satu rumah utuh (seluruh kamar).' : 'Harga disimpan langsung ke database dan berlaku untuk booking berikutnya.' ?></small>
        </div>
        <span class="badge text-bg-light border text-secondary"><?= $pricing['pricing_mode'] === 'whole_home_nightly' ? '1 unit rumah (' . count($rooms) . ' kamar)' : count($rooms) . ' kamar' ?></span>
    </div>
    <div class="table-responsive mt-3 border-top">
        <table class="table table-sm table-hover align-middle mb-0 front-desk-table small">
            <thead class="table-light">
                <tr>
                    <th><?= $pricing['pricing_mode'] === 'whole_home_nightly' ? 'Unit' : 'Kamar' ?></th>
                    <th>Tipe</th>
                    <th><?= \App\Libraries\PropertyContext::activeId() === \App\Libraries\PropertyContext::HOMIQ_ID ? 'Guesthouse' : 'Properti' ?></th>
                    <th><?= $pricing['pricing_mode'] === 'room_monthly' ? 'Harga per bulan' : ($pricing['pricing_mode'] === 'whole_home_nightly' ? 'Harga satu rumah/malam' : 'Harga per malam') ?></th>
                    <th class="text-end" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($pricing['pricing_mode'] === 'whole_home_nightly'): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark"><?= esc((string) session('activePropertyName')) ?> · 1 Rumah</div>
                            <small class="text-muted">Mencakup <?= count($rooms) ?> kamar tidur (<?= esc(implode(', ', array_map(static fn($r) => $r['room_number'], $rooms))) ?>)</small>
                        </td>
                        <td><span class="badge bg-light text-dark border">Homestay (<?= count($rooms) ?> Kamar)</span></td>
                        <td><?= esc((string) session('activePropertyName')) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-1.5">
                                <span class="fw-semibold text-dark fs-6">Rp<?= number_format((float) $pricing['base_rate'], 0, ',', '.') ?></span>
                                <span class="text-secondary small">/ malam (paket rumah)</span>
                            </div>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 px-2.5 py-1 text-nowrap"
                                    type="button"
                                    onclick="const el = document.getElementById('bulk-room-price'); if (el) { el.focus(); el.select(); window.scrollTo({top: 0, behavior: 'smooth'}); }">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                                    <path d="m15 5 4 4"/>
                                </svg>
                                <span>Ubah Tarif</span>
                            </button>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rooms as $room): ?>
                        <?php
                            $roomPrice = (float) $room['price_per_night'];
                            $effectivePrice = $roomPrice > 0 ? $roomPrice : (float) $pricing['base_rate'];
                            $hasCustomPrice = $roomPrice > 0 && abs($roomPrice - (float) $pricing['base_rate']) > 0.01;
                            $roomDisplayName = str_starts_with(strtolower($room['room_number']), 'room') || str_starts_with(strtolower($room['room_number']), 'kamar')
                                ? $room['room_number']
                                : 'Room ' . $room['room_number'];
                        ?>
                        <tr>
                            <td class="fw-semibold text-dark"><?= esc($roomDisplayName) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= esc($room['room_type']) ?></span></td>
                            <td><?= \App\Libraries\PropertyContext::activeId() === \App\Libraries\PropertyContext::HOMIQ_ID ? 'GH_' . esc((string) $room['guesthouse_number']) : esc((string) session('activePropertyName')) ?></td>
                            <td>
                                <?php if ($pricing['pricing_mode'] === 'room_nightly'): ?>
                                    <form id="room-price-<?= (int) $room['id'] ?>" method="post" action="<?= site_url('room-pricing/' . $room['id']) ?>" class="d-flex align-items-center gap-1.5" style="max-width: 200px;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="room_number" value="<?= esc($room['room_number']) ?>">
                                        <input type="hidden" name="room_type" value="<?= esc($room['room_type']) ?>">
                                        <span class="text-secondary small">Rp</span>
                                        <input class="form-control form-control-sm room-price-input" type="text" inputmode="numeric" data-currency-input name="price_per_night" value="<?= esc(number_format((float) $room['price_per_night'], 0, ',', '.')) ?>" required aria-label="Harga kamar <?= esc($room['room_number']) ?>">
                                    </form>
                                <?php else: ?>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span class="fw-semibold text-dark">Rp<?= number_format($effectivePrice, 0, ',', '.') ?></span>
                                        <?php if ($hasCustomPrice): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.65rem;">Kustom</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1.5">
                                    <?php if ($pricing['pricing_mode'] === 'room_nightly'): ?>
                                        <button class="btn btn-outline-primary btn-sm" type="submit" form="room-price-<?= (int) $room['id'] ?>">Simpan</button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 px-2.5 py-1 text-nowrap"
                                            type="button"
                                            hx-get="<?= site_url('room-pricing/' . $room['id'] . '/edit') ?>"
                                            hx-target="#modal-container"
                                            hx-swap="innerHTML"
                                            title="Edit nama dan tarif kamar <?= esc($room['room_number']) ?>">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                                            <path d="m15 5 4 4"/>
                                        </svg>
                                        <span>Edit</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
