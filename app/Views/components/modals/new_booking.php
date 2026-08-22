<div class="modal fade" id="dynamicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable booking-modal-dialog">
        <div class="modal-content border-0 booking-modal">
            <div class="modal-header">
                <div><h2 class="modal-title h5 mb-0">New Booking</h2><small class="text-secondary">Data tamu, masa inap, layanan, dan pembayaran awal</small></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form enctype="multipart/form-data" hx-encoding="multipart/form-data" hx-post="<?= site_url('reservations') ?>" hx-target="#booking-form-feedback" hx-swap="innerHTML" data-booking-form data-image-upload-form data-availability-url="<?= site_url('reservations/available-rooms') ?>" data-guest-suggestions-url="<?= site_url('guests/suggestions') ?>">
                <div class="modal-body booking-modal-body">
                    <div id="booking-form-feedback" class="mb-3"></div>
                    <?= csrf_field() ?>
                    <div class="row g-4">
                        <div class="col-lg-6 d-flex">
                            <section class="booking-section w-100">
                            <h3 class="booking-section-title">Informasi Tamu</h3>
                            <div class="row g-3">
                                <div class="col-12 position-relative">
                                    <label class="form-label">Nama lengkap</label>
                                    <input class="form-control" name="guest_name" required autocomplete="off" data-guest-suggestion-input>
                                    <input type="hidden" name="guest_id" data-selected-guest-id>
                                    <div class="list-group position-absolute w-100 shadow d-none" data-guest-suggestion-list style="z-index: 1080;"></div>
                                    <small class="text-secondary d-block mt-1">Ketik nama untuk memakai data tamu yang sudah tersimpan.</small>
                                </div>
                                <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" type="email"></div>
                                <div class="col-md-6"><label class="form-label">Telepon</label><input class="form-control" name="phone"></div>
                                <div class="col-12"><label class="form-label">Asal</label><input class="form-control" name="origin"></div>
                                <div class="col-12">
                                    <label class="form-label">Foto KTP <span class="text-secondary fw-normal">(opsional)</span></label>
                                    <input class="form-control" type="file" name="ktp_photo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" data-image-file-input>
                                    <small class="text-secondary d-block mt-1">Foto otomatis dikompres maksimal 200 KB.</small>
                                    <small class="text-primary d-block" data-image-compression-status></small>
                                </div>
                                <div class="col-12"><label class="form-label">Catatan tamu</label><textarea class="form-control" name="guest_notes" rows="2"></textarea></div>
                            </div>
                            </section>
                        </div>
                        <div class="col-lg-6 d-flex">
                            <section class="booking-section w-100">
                            <h3 class="booking-section-title">Detail Menginap</h3>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Check-in</label><input class="form-control" name="check_in_date" type="date" value="<?= date('Y-m-d') ?>" required></div>
                                <div class="col-md-6"><label class="form-label">Check-out</label><input class="form-control" name="check_out_date" type="date" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></div>
                                <div class="col-12"><small class="text-secondary" data-stay-type>Check-in dan check-out di tanggal yang sama akan dicatat sebagai transit.</small></div>
                                <div class="col-12">
                                    <label class="form-label">Kamar <span class="text-secondary fw-normal">(bisa pilih lebih dari satu)</span></label>
                                    <div class="booking-room-picker" data-booking-room-picker>
                                        <div class="booking-room-picker-summary">
                                            <div class="d-flex align-items-center justify-content-between gap-2">
                                                <strong data-booking-room-count>0 kamar dipilih</strong>
                                                <a class="btn btn-outline-primary btn-sm py-0" href="<?= site_url('room-map') ?>" target="_blank" rel="noopener" data-booking-room-map>Lihat Room Map</a>
                                            </div>
                                            <small class="text-secondary" data-booking-room-availability-status>Pilih tanggal terlebih dahulu untuk melihat kamar yang tersedia.</small>
                                        </div>
                                        <div class="booking-room-options">
                                            <?php foreach ($rooms as $room): ?>
                                                <?php $maintenance = $room['status'] === 'Maintenance'; ?>
                                                <label class="booking-room-option is-disabled" data-booking-room-option data-price-per-night="<?= esc($room['price_per_night']) ?>">
                                                    <input class="form-check-input" type="checkbox" name="room_ids[]" value="<?= (int) $room['id'] ?>" disabled>
                                                    <span class="min-width-0">
                                                        <strong class="d-block">Room <?= esc($room['room_number']) ?> · <?= esc($room['room_type']) ?></strong>
                                                        <small class="text-secondary"><span data-booking-room-reason><?= $maintenance ? ' · Maintenance' : ' · Pilih tanggal terlebih dahulu' ?></span></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Extra Bed <span class="text-secondary fw-normal">(opsional)</span></label>
                                    <div class="booking-extra-bed-picker d-none" data-extra-bed-picker data-extra-bed-available="0">
                                        <div class="booking-room-picker-summary">
                                            <strong data-extra-bed-summary>0 unit Extra Bed dipilih</strong>
                                            <small class="text-secondary" data-extra-bed-availability>Stok tersedia mengikuti tanggal menginap.</small>
                                        </div>
                                        <div class="booking-extra-bed-options">
                                            <?php foreach ($rooms as $room): ?>
                                                <label class="booking-extra-bed-option d-none" data-extra-bed-option data-room-id="<?= (int) $room['id'] ?>">
                                                    <span class="min-width-0">
                                                        <strong class="d-block">Room <?= esc($room['room_number']) ?></strong>
                                                        <small class="text-secondary"><?= esc($room['room_type']) ?></small>
                                                    </span>
                                                    <input class="form-control form-control-sm" type="number" name="extra_bed_quantity[<?= (int) $room['id'] ?>]" min="0" max="0" value="0" data-extra-bed-quantity disabled>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6"><label class="form-label">Sumber</label><select class="form-select" name="source" data-booking-source><option>Direct</option><option>Booking.com</option><option>Agoda</option><option>Traveloka</option><option>Airbnb</option><option>Tiket.com</option></select></div>
                                <div class="col-md-6"><label class="form-label">Jumlah tamu</label><input class="form-control" name="guest_count" type="number" min="1" value="1" required></div>
                                <div class="col-12">
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" role="switch" id="agent-guest" name="is_agent_guest" value="1" data-agent-guest-toggle>
                                        <label class="form-check-label fw-semibold" for="agent-guest">Tamu dari agen</label>
                                    </div>
                                    <small class="text-secondary">Pengingat untuk mencatat fee kerja sama di pengeluaran perusahaan.</small>
                                </div>
                                <div class="col-12 d-none" data-agent-name-field>
                                    <label class="form-label" for="agent-name">Nama agen</label>
                                    <input id="agent-name" class="form-control" name="agent_name" maxlength="150" placeholder="Contoh: Agen Budi Travel" disabled data-agent-name-input>
                                </div>
                                <div class="col-12"><label class="form-label">Permintaan khusus</label><textarea class="form-control" name="special_request" rows="2"></textarea></div>
                            </div>
                            </section>
                        </div>
                        <div class="col-lg-6 d-flex">
                            <section class="booking-section w-100">
                            <h3 class="booking-section-title">Layanan Tambahan</h3>
                            <p class="text-secondary small mb-3">Breakfast, Biaya Extra Bed, Laundry, Massage, dan Sewa Kendaraan masuk ke kas perusahaan dan tidak dihitung sebagai pemasukan Owner GH.</p>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label">Breakfast</label><input class="form-control" name="breakfast_cost" type="text" inputmode="numeric" data-currency-input value="0"></div>
                                <div class="col-md-4"><label class="form-label">Biaya Extra Bed</label><input class="form-control" name="extra_bed_cost" type="text" inputmode="numeric" data-currency-input value="0"></div>
                                <div class="col-md-4"><label class="form-label">Early Check In</label><input class="form-control" name="early_checkin_cost" type="text" inputmode="numeric" data-currency-input value="0"></div>
                                <div class="col-md-4"><label class="form-label">Late Checkout</label><input class="form-control" name="late_checkout_cost" type="text" inputmode="numeric" data-currency-input value="0"></div>
                                <div class="col-md-4"><label class="form-label">Laundry</label><input class="form-control" name="laundry_cost" type="text" inputmode="numeric" data-currency-input value="0"></div>
                                <div class="col-md-4"><label class="form-label">Massage</label><input class="form-control" name="massage_cost" type="text" inputmode="numeric" data-currency-input value="0"></div>
                                <div class="col-md-4"><label class="form-label">Sewa Kendaraan</label><input class="form-control" name="vehicle_rental_cost" type="text" inputmode="numeric" data-currency-input value="0"></div>
                            </div>
                            </section>
                        </div>
                        <div class="col-lg-6 d-flex">
                            <section class="booking-section w-100">
                            <h3 class="booking-section-title">Pembayaran Awal</h3>
                            <div class="row g-3">
                                <div class="col-12"><label class="form-label">Konfirmasi total booking</label><div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" name="confirmed_total" type="text" inputmode="numeric" data-currency-input value="0" data-confirmed-total></div><div class="d-flex justify-content-between gap-2 mt-1"><small class="text-secondary" data-confirmed-total-help>Harga standar akan muncul setelah kamar dipilih.</small><button class="btn btn-link btn-sm p-0 text-nowrap" type="button" data-reset-confirmed-total>Gunakan harga standar</button></div></div>
                                <div class="col-12"><label class="form-label">Status pembayaran</label><select class="form-select" name="payment_type" data-payment-type><option value="unpaid">Belum Bayar</option><option value="dp">DP</option><option value="full">Lunas / Pelunasan</option></select><small class="text-secondary" data-payment-type-help>Belum ada pembayaran yang dicatat.</small></div>
                                <div class="col-12"><label class="form-label">Nominal pembayaran</label><input class="form-control" name="payment_amount" type="text" inputmode="numeric" data-currency-input value="0" data-payment-amount disabled></div>
                                <div class="col-12"><label class="form-label">Tanggal pembayaran</label><input class="form-control" name="payment_date" type="date" value="<?= date('Y-m-d') ?>" data-payment-date disabled><small class="text-secondary" data-payment-date-help></small></div>
                                <div class="col-12"><label class="form-label">Metode</label><select class="form-select" name="payment_method" data-payment-method disabled><option>Cash</option><option>Transfer</option><option>Credit Card</option><option>OTA</option></select></div>
                                <div class="col-12"><label class="form-label">Catatan penting <span class="text-secondary fw-normal">(opsional)</span></label><textarea class="form-control" name="important_note" rows="2" maxlength="2000" placeholder="Contoh: pembayaran menyusul, kebutuhan khusus, atau informasi penting booking."></textarea></div>
                                <div class="col-12 d-none" data-payment-proof-field>
                                    <label class="form-label">Bukti transfer <span class="text-secondary fw-normal">(opsional)</span></label>
                                    <input class="form-control" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" data-image-file-input disabled>
                                    <small class="text-secondary d-block mt-1">Foto otomatis dikompres maksimal 200 KB.</small>
                                    <small class="text-primary d-block" data-image-compression-status></small>
                                </div>
                            </div>
                            </section>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" type="submit">Simpan Booking</button></div>
            </form>
        </div>
    </div>
</div>
