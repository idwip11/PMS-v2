<?php
$today = new DateTimeImmutable('today');
try {
    $currentCheckOut = new DateTimeImmutable((string) $reservation['check_out_date']);
    $diffDays = (int) $today->diff($currentCheckOut)->format('%r%a');
} catch (\Throwable $e) {
    $diffDays = null;
}
?>
<div class="modal fade" id="dynamicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5 mb-0">Perpanjang Sewa Kos (Extend Lease)</h2>
                    <small class="text-secondary">Pencatatan periode sewa baru berkesinambungan tanpa jeda kamar kosong</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form hx-post="<?= site_url('reservations/' . $reservation['id'] . '/extend') ?>"
                  hx-target="#extend-form-feedback"
                  hx-swap="innerHTML"
                  data-extend-booking-form
                  data-monthly-rate="<?= (float) $monthlyRate ?>">
                <div class="modal-body p-4">
                    <div id="extend-form-feedback" class="mb-3"></div>
                    <?= csrf_field() ?>

                    <!-- Info Kontrak Saat Ini -->
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
                                <span class="badge text-bg-primary">Kontrak Aktif Saat Ini</span>
                                <small class="text-secondary">ID: <strong><?= esc($reservation['order_id']) ?></strong></small>
                            </div>
                            <div class="row g-3">
                                <div class="col-sm-6 col-md-3">
                                    <small class="text-secondary d-block">Penghuni</small>
                                    <strong class="d-block text-truncate"><?= esc($reservation['guest_name']) ?></strong>
                                    <small class="text-secondary"><?= esc($reservation['phone'] ?: '-') ?></small>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <small class="text-secondary d-block">Kamar</small>
                                    <strong class="d-block">Room <?= esc($room['room_number']) ?></strong>
                                    <small class="text-secondary"><?= esc($room['room_type'] ?: 'Standard') ?></small>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <small class="text-secondary d-block">Masa Sewa Berjalan</small>
                                    <strong class="d-block"><?= esc(date('d/m/Y', strtotime($reservation['check_in_date']))) ?> — <?= esc(date('d/m/Y', strtotime($reservation['check_out_date']))) ?></strong>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <small class="text-secondary d-block">Sisa Sewa</small>
                                    <?php if ($diffDays === null): ?>
                                        <span class="text-secondary">—</span>
                                    <?php elseif ($diffDays > 7): ?>
                                        <span class="badge text-bg-success-subtle text-success border border-success-subtle fw-semibold"><?= $diffDays ?> hari lagi</span>
                                    <?php elseif ($diffDays >= 1): ?>
                                        <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold"><?= $diffDays ?> hari lagi</span>
                                    <?php elseif ($diffDays === 0): ?>
                                        <span class="badge text-bg-danger text-white fw-semibold">Berakhir Hari ini</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger text-white fw-semibold">Lewat <?= abs($diffDays) ?> hari</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Periode Perpanjangan -->
                    <section class="mb-4">
                        <h3 class="h6 mb-3 text-primary d-flex align-items-center gap-2">
                            <span>📅</span> Periode Perpanjangan Sewa
                        </h3>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Mulai Perpanjangan</label>
                                <input class="form-control bg-light" type="date" name="check_in_date" value="<?= esc($startCheckIn) ?>" readonly data-extend-checkin>
                                <small class="text-secondary d-block mt-1">Sama dengan tgl keluar sebelumnya.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tambah Durasi</label>
                                <select class="form-select" name="duration_select" data-extend-duration-select>
                                    <option value="1" selected>1 Bulan</option>
                                    <option value="2">2 Bulan</option>
                                    <option value="3">3 Bulan</option>
                                    <option value="4">4 Bulan</option>
                                    <option value="5">5 Bulan</option>
                                    <option value="6">6 Bulan</option>
                                    <option value="9">9 Bulan</option>
                                    <option value="12">12 Bulan (1 Tahun)</option>
                                    <option value="custom">Kustom...</option>
                                </select>
                                <div class="input-group input-group-sm mt-1 d-none" data-extend-custom-wrap>
                                    <input class="form-control" type="number" name="custom_duration_months" min="1" max="120" value="1" placeholder="Jml bulan" data-extend-custom-input>
                                    <span class="input-group-text">Bulan</span>
                                </div>
                                <input type="hidden" name="duration_months" value="1" data-extend-months-hidden>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Selesai Baru <span class="badge text-bg-light border text-secondary fw-normal py-0 px-1" style="font-size:0.7rem;">Otomatis</span></label>
                                <input class="form-control bg-light" type="date" name="check_out_date" value="<?= esc($defaultCheckOut) ?>" readonly data-extend-checkout>
                                <small class="text-secondary d-block mt-1">Otomatis terhitung sesuai bulan.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sumber</label>
                                <select class="form-select" name="source">
                                    <option value="Direct" <?= ($reservation['source'] ?? '') === 'Direct' ? 'selected' : '' ?>>Direct</option>
                                    <option value="Online" <?= ($reservation['source'] ?? '') === 'Online' ? 'selected' : '' ?>>Online</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tarif Bulanan Kamar</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input class="form-control bg-light" type="text" value="<?= number_format($monthlyRate, 0, ',', '.') ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Form Pembayaran -->
                    <section class="border-top pt-4">
                        <h3 class="h6 mb-3 text-primary d-flex align-items-center gap-2">
                            <span>💳</span> Biaya & Pembayaran Perpanjangan
                        </h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Total Tagihan Perpanjangan</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input class="form-control fw-semibold" name="confirmed_total" type="text" inputmode="numeric" data-currency-input value="<?= number_format($monthlyRate, 0, ',', '.') ?>" data-extend-total>
                                </div>
                                <small class="text-secondary d-block mt-1">Otomatis terhitung durasi × tarif. Dapat disesuaikan jika ada diskon.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status Pembayaran</label>
                                <select class="form-select" name="payment_type" data-extend-payment-type>
                                    <option value="full" selected>Lunas / Pelunasan</option>
                                    <option value="dp">DP (Uang Muka)</option>
                                    <option value="unpaid">Belum Bayar</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nominal Bayar</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input class="form-control" name="payment_amount" type="text" inputmode="numeric" data-currency-input value="<?= number_format($monthlyRate, 0, ',', '.') ?>" data-extend-payment-amount>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Metode Pembayaran</label>
                                <select class="form-select" name="payment_method" data-extend-payment-method>
                                    <option value="Transfer" selected>Transfer</option>
                                    <option value="Cash">Cash</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Bayar</label>
                                <input class="form-control" type="date" name="payment_date" value="<?= date('Y-m-d') ?>" data-extend-payment-date>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Catatan Perpanjangan <span class="text-secondary fw-normal">(opsional)</span></label>
                                <input class="form-control" name="important_note" value="Perpanjangan sewa dari reservasi #<?= esc($reservation['order_id']) ?>" maxlength="500">
                            </div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success px-4 d-inline-flex align-items-center gap-2">
                        <span>Simpan Perpanjangan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
