<div class="modal fade" id="dynamicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable booking-modal-dialog">
        <div class="modal-content border-0 booking-modal">
            <div class="modal-header">
                <div><h2 class="modal-title h5 mb-0">New Booking</h2><small class="text-secondary"><?= ($pricing['pricing_mode'] ?? '') === 'room_monthly' ? 'Data penyewa, periode sewa, fasilitas, dan pembayaran awal' : 'Data tamu, masa inap, layanan, dan pembayaran awal' ?></small></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form enctype="multipart/form-data" hx-encoding="multipart/form-data" hx-post="<?= site_url('reservations') ?>" hx-target="#booking-form-feedback" hx-swap="innerHTML" data-booking-form data-image-upload-form data-pricing-mode="<?= esc($pricing['pricing_mode']) ?>" data-base-rate="<?= esc((string) $pricing['base_rate']) ?>" data-availability-url="<?= site_url('reservations/available-rooms') ?>" data-guest-suggestions-url="<?= site_url('guests/suggestions') ?>">
                <div class="modal-body booking-modal-body">
                    <div id="booking-form-feedback" class="mb-3"></div>
                    <?= csrf_field() ?>
                    <div class="row g-4">
                        <div class="col-lg-6 d-flex">
                            <section class="booking-section w-100">
                            <h3 class="booking-section-title"><?= ($pricing['pricing_mode'] ?? '') === 'room_monthly' ? 'Informasi Penyewa' : 'Informasi Tamu' ?></h3>
                            <div class="row g-3">
                                <div class="col-12 position-relative">
                                     <label class="form-label">Nama lengkap</label>
                                     <input class="form-control" name="guest_name" required autocomplete="off" data-guest-suggestion-input>
                                     <input type="hidden" name="guest_id" data-selected-guest-id>
                                     <div class="list-group position-absolute w-100 shadow d-none" data-guest-suggestion-list style="z-index: 1080;"></div>
                                     <small class="text-secondary d-block mt-1"><?= ($pricing['pricing_mode'] ?? '') === 'room_monthly' ? 'Ketik nama untuk memakai data penyewa yang sudah tersimpan.' : 'Ketik nama untuk memakai data tamu yang sudah tersimpan.' ?></small>
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
                                <div class="col-12"><label class="form-label"><?= ($pricing['pricing_mode'] ?? '') === 'room_monthly' ? 'Catatan penghuni' : 'Catatan tamu' ?></label><textarea class="form-control" name="guest_notes" rows="2"></textarea></div>
                            </div>
                            </section>
                        </div>
                        <div class="col-lg-6 d-flex">
                            <section class="booking-section w-100">
                            <h3 class="booking-section-title"><?= ($pricing['pricing_mode'] ?? '') === 'room_monthly' ? 'Detail Sewa' : 'Detail Menginap' ?></h3>
                            <div class="row g-3">
                                <?php if (($pricing['pricing_mode'] ?? '') === 'room_monthly'): ?>
                                    <?php
                                    $defaultCheckIn = date('Y-m-d');
                                    $startDate = new DateTimeImmutable($defaultCheckIn);
                                    $targetMonth = $startDate->modify('first day of this month')->modify('+1 month');
                                    $targetDay = min((int) $startDate->format('d'), (int) $targetMonth->format('t'));
                                    $defaultCheckOut = $targetMonth->setDate((int) $targetMonth->format('Y'), (int) $targetMonth->format('m'), $targetDay)->format('Y-m-d');
                                    ?>
                                    <div class="col-md-4">
                                        <label class="form-label">Tanggal Masuk</label>
                                        <input class="form-control" name="check_in_date" type="date" value="<?= $defaultCheckIn ?>" required data-monthly-checkin>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Durasi Sewa</label>
                                        <select class="form-select" data-monthly-duration-select>
                                            <option value="1" selected>1 Bulan</option>
                                            <option value="2">2 Bulan</option>
                                            <option value="3">3 Bulan</option>
                                            <option value="4">4 Bulan</option>
                                            <option value="5">5 Bulan</option>
                                            <option value="6">6 Bulan</option>
                                            <option value="7">7 Bulan</option>
                                            <option value="8">8 Bulan</option>
                                            <option value="9">9 Bulan</option>
                                            <option value="10">10 Bulan</option>
                                            <option value="11">11 Bulan</option>
                                            <option value="12">12 Bulan (1 Thn)</option>
                                            <option value="18">18 Bulan</option>
                                            <option value="24">24 Bulan (2 Thn)</option>
                                            <option value="custom">Kustom...</option>
                                        </select>
                                        <div class="input-group input-group-sm mt-1 d-none" data-monthly-custom-wrap>
                                            <input class="form-control" type="number" min="1" max="120" value="1" placeholder="Jml bulan" data-monthly-custom-input>
                                            <span class="input-group-text">Bulan</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Tanggal Keluar <span class="badge text-bg-light border text-secondary fw-normal py-0 px-1" style="font-size:0.7rem;">Otomatis</span></label>
                                        <input class="form-control bg-light" name="check_out_date" type="date" value="<?= $defaultCheckOut ?>" required readonly data-monthly-checkout title="Otomatis terhitung dari tanggal masuk + durasi sewa">
                                    </div>
                                    <div class="col-12"><small class="text-secondary" data-stay-type>Kos Mitra: 1 bulan sejak tanggal masuk · Rp<?= number_format((float) ($pricing['base_rate'] ?? 0), 0, ',', '.') ?> per kamar/bulan.</small></div>
                                <?php else: ?>
                                    <div class="col-md-6"><label class="form-label">Check-in</label><input class="form-control" name="check_in_date" type="date" value="<?= date('Y-m-d') ?>" required></div>
                                    <div class="col-md-6"><label class="form-label">Check-out</label><input class="form-control" name="check_out_date" type="date" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></div>
                                    <div class="col-12"><small class="text-secondary" data-stay-type><?= ($pricing['pricing_mode'] ?? '') === 'whole_home_nightly' ? 'Homestay Mitra: tarif Rp600.000 per malam untuk satu rumah (seluruh 3 kamar).' : 'Check-in dan check-out di tanggal yang sama akan dicatat sebagai transit.' ?></small></div>
                                <?php endif; ?>
                                <div class="col-12">
                                    <label class="form-label"><?= ($pricing['pricing_mode'] ?? '') === 'whole_home_nightly' ? 'Pilihan Unit' : 'Kamar' ?> <span class="text-secondary fw-normal"><?= ($pricing['pricing_mode'] ?? '') === 'whole_home_nightly' ? '(sewa 1 rumah)' : '(bisa pilih lebih dari satu)' ?></span></label>
                                    <div class="booking-room-picker" data-booking-room-picker>
                                        <div class="booking-room-picker-summary">
                                            <div class="d-flex align-items-center justify-content-between gap-2">
                                                <strong data-booking-room-count><?= ($pricing['pricing_mode'] ?? '') === 'whole_home_nightly' ? '0 unit dipilih' : '0 kamar dipilih' ?></strong>
                                                <a class="btn btn-outline-primary btn-sm py-0" href="<?= site_url('room-map') ?>" target="_blank" rel="noopener" data-booking-room-map>Lihat Room Map</a>
                                            </div>
                                            <small class="text-secondary" data-booking-room-availability-status>Pilih tanggal terlebih dahulu untuk melihat ketersediaan <?= ($pricing['pricing_mode'] ?? '') === 'whole_home_nightly' ? 'unit' : 'kamar' ?>.</small>
                                        </div>
                                        <div class="booking-room-options">
                                            <?php if (($pricing['pricing_mode'] ?? '') === 'whole_home_nightly'): ?>
                                                <?php $anyMaintenance = in_array('Maintenance', array_column($rooms, 'status'), true); ?>
                                                <label class="booking-room-option is-disabled" data-booking-room-option data-whole-home="true" data-price-per-night="<?= esc((string) $pricing['base_rate']) ?>">
                                                    <input class="form-check-input" type="checkbox" data-whole-home-master disabled>
                                                    <?php foreach ($rooms as $room): ?>
                                                        <input type="checkbox" name="room_ids[]" value="<?= (int) $room['id'] ?>" class="d-none" disabled>
                                                    <?php endforeach; ?>
                                                    <span class="min-width-0">
                                                        <strong class="d-block">Homestay Mitra · Seluruh Rumah (3 Kamar)</strong>
                                                        <small class="text-secondary">Rp<?= number_format((float) ($pricing['base_rate'] ?? 600000), 0, ',', '.') ?> / malam<span data-booking-room-reason><?= $anyMaintenance ? ' · Sedang Maintenance' : ' · Pilih tanggal terlebih dahulu' ?></span></small>
                                                    </span>
                                                </label>
                                            <?php else: ?>
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
                                            <?php endif; ?>
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
                                            <?php if (($pricing['pricing_mode'] ?? '') === 'whole_home_nightly'): ?>
                                                <?php $primaryRoom = $rooms[0] ?? null; ?>
                                                <?php if ($primaryRoom !== null): ?>
                                                    <div class="booking-extra-bed-option d-none" data-extra-bed-option data-room-id="<?= (int) $primaryRoom['id'] ?>">
                                                        <span class="min-width-0 pe-2">
                                                            <strong class="d-block">Homestay Mitra (1 Rumah)</strong>
                                                            <small class="text-secondary">Tambahan kasur ekstra untuk unit homestay</small>
                                                        </span>
                                                        <div class="input-group input-group-sm flex-nowrap" style="width: 110px;">
                                                            <button class="btn btn-outline-secondary px-2" type="button" data-extra-bed-step="-1" title="Kurangi 1">-</button>
                                                            <input class="form-control form-control-sm text-center px-1" type="number" name="extra_bed_quantity[<?= (int) $primaryRoom['id'] ?>]" min="0" max="0" value="0" data-extra-bed-quantity disabled aria-label="Jumlah Extra Bed">
                                                            <button class="btn btn-outline-secondary px-2" type="button" data-extra-bed-step="1" title="Tambah 1">+</button>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php foreach ($rooms as $room): ?>
                                                    <div class="booking-extra-bed-option d-none" data-extra-bed-option data-room-id="<?= (int) $room['id'] ?>">
                                                        <span class="min-width-0 pe-2">
                                                            <strong class="d-block">Room <?= esc($room['room_number']) ?></strong>
                                                            <small class="text-secondary"><?= esc($room['room_type']) ?></small>
                                                        </span>
                                                        <div class="input-group input-group-sm flex-nowrap" style="width: 110px;">
                                                            <button class="btn btn-outline-secondary px-2" type="button" data-extra-bed-step="-1" title="Kurangi 1">-</button>
                                                            <input class="form-control form-control-sm text-center px-1" type="number" name="extra_bed_quantity[<?= (int) $room['id'] ?>]" min="0" max="0" value="0" data-extra-bed-quantity disabled aria-label="Jumlah Extra Bed">
                                                            <button class="btn btn-outline-secondary px-2" type="button" data-extra-bed-step="1" title="Tambah 1">+</button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sumber</label>
                                    <select class="form-select" name="source" data-booking-source>
                                        <?php if (($pricing['pricing_mode'] ?? '') === 'room_monthly'): ?>
                                            <option value="Direct">Direct</option>
                                            <option value="Online">Online</option>
                                        <?php else: ?>
                                            <option value="Direct">Direct</option>
                                            <option value="Booking.com">Booking.com</option>
                                            <option value="Agoda">Agoda</option>
                                            <option value="Traveloka">Traveloka</option>
                                            <option value="Airbnb">Airbnb</option>
                                            <option value="Tiket.com">Tiket.com</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-6"><label class="form-label"><?= ($pricing['pricing_mode'] ?? '') === 'room_monthly' ? 'Jumlah penghuni' : 'Jumlah tamu' ?></label><input class="form-control" name="guest_count" type="number" min="1" value="1" required></div>
                                <div class="col-12">
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" role="switch" id="agent-guest" name="is_agent_guest" value="1" data-agent-guest-toggle>
                                        <label class="form-check-label fw-semibold" for="agent-guest"><?= ($pricing['pricing_mode'] ?? '') === 'room_monthly' ? 'Penghuni dari agen' : 'Tamu dari agen' ?></label>
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
                            <p class="text-secondary small mb-3">
                                <?php if (in_array(\App\Libraries\PropertyContext::activeId(), [2, 3], true)): ?>
                                    Biaya Extra Bed masuk ke dasar bagi hasil Owner Mitra. Breakfast, Laundry, Massage, dan Sewa Kendaraan masuk ke kas perusahaan.
                                <?php else: ?>
                                    Breakfast, Biaya Extra Bed, Laundry, Massage, dan Sewa Kendaraan masuk ke kas perusahaan dan tidak dihitung sebagai pemasukan Owner GH.
                                <?php endif; ?>
                            </p>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label">Breakfast</label><input class="form-control" name="breakfast_cost" type="text" inputmode="numeric" data-currency-input value="0"></div>
                                <div class="col-md-4"><label class="form-label">Biaya Extra Bed</label><input class="form-control" name="extra_bed_cost" type="text" inputmode="numeric" data-currency-input value="0"><small class="text-secondary d-block mt-1">Tarif per kasur / malam</small></div>
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
                                <div class="col-12">
                                    <label class="form-label">Metode</label>
                                    <select class="form-select" name="payment_method" data-payment-method disabled>
                                        <?php if (($pricing['pricing_mode'] ?? '') === 'room_monthly'): ?>
                                            <option>Cash</option>
                                            <option>Transfer</option>
                                        <?php else: ?>
                                            <option>Cash</option>
                                            <option>Transfer</option>
                                            <option>Credit Card</option>
                                            <option>OTA</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
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
