<?php
$effectivePrice = (float) $room['price_per_night'] > 0
    ? (float) $room['price_per_night']
    : (float) $pricing['base_rate'];

$priceLabel = match ($pricing['pricing_mode']) {
    'room_monthly' => 'Harga per Bulan',
    'whole_home_nightly' => 'Tarif per Malam',
    default => 'Harga per Malam',
};

$priceHelp = match ($pricing['pricing_mode']) {
    'room_monthly' => 'Tarif sewa per kamar per bulan untuk booking kos baru.',
    'whole_home_nightly' => 'Tarif sewa per malam untuk properti homestay.',
    default => 'Tarif sewa per malam untuk kamar guesthouse ini.',
};
?>
<div class="modal fade" id="dynamicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 rounded bg-primary-subtle text-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                            <path d="m15 5 4 4"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="modal-title h5 mb-0 fw-bold">Edit Kamar</h2>
                        <small class="text-secondary"><?= esc($propertyName) ?> · ID Kamar #<?= (int) $room['id'] ?></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form method="post" action="<?= site_url('room-pricing/' . $room['id']) ?>">
                <?= csrf_field() ?>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary mb-1">Nama / Nomor Kamar</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                    </svg>
                                </span>
                                <input class="form-control" type="text" name="room_number" value="<?= esc($room['room_number']) ?>" required maxlength="50" placeholder="cth: k1 atau Kamar 01">
                            </div>
                            <small class="form-text text-muted">Nama atau nomor identifikasi unik kamar pada properti ini.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary mb-1">Tipe Kamar</label>
                            <input class="form-control" type="text" name="room_type" value="<?= esc($room['room_type']) ?>" required maxlength="50" placeholder="cth: Kos, Double Bed, Standard, dll">
                            <small class="form-text text-muted">Kategori atau tipe fasilitas kamar.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary mb-1"><?= esc($priceLabel) ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-semibold text-secondary">Rp</span>
                                <input class="form-control" type="text" inputmode="numeric" data-currency-input name="price_per_night" value="<?= esc(number_format($effectivePrice, 0, ',', '.')) ?>" required placeholder="Masukkan nominal harga">
                            </div>
                            <small class="form-text text-muted"><?= esc($priceHelp) ?></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2.5 px-4 bg-light bg-opacity-50">
                    <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1.5 px-3">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
