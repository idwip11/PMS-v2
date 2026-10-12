<?php
$paid = (float) ($groupPaid ?? $reservation['paid_amount']);
$total = (float) ($groupTotal ?? $reservation['total_amount']);
$outstanding = max(0, $total - $paid);
$isTransit = (string) $reservation['check_in_date'] === (string) $reservation['check_out_date'];
$today = (new DateTimeImmutable('now', new DateTimeZone(config('App')->appTimezone)))->format('Y-m-d');
$canCheckIn = (string) $reservation['check_in_date'] <= $today;
$groupRooms = $groupReservations ?? [$reservation];
$isReconciled = (int) ($reservation['is_reconciled'] ?? 0) === 1;
$isWholeHome = \App\Libraries\PropertyPricing::active()['pricing_mode'] === 'whole_home_nightly'
    || \App\Libraries\PropertyContext::activeId() === 2
    || (string) session('activePropertyCode') === 'HOMESTAY_MITRA';
$isMonthly = \App\Libraries\PropertyPricing::active()['pricing_mode'] === 'room_monthly';
$groupRoomNumbers = array_values(array_filter(array_map(static fn (array $room): string => trim((string) ($room['room_number'] ?? '')), $groupRooms)));
$groupRoomLabel = $isWholeHome ? 'Homestay Mitra (Seluruh Rumah)' : implode(', ', array_map(static fn (string $roomNumber): string => 'Room ' . $roomNumber, $groupRoomNumbers));
$rawPhoneModal = trim((string) ($reservation['phone'] ?? ''));
$digitsModal = preg_replace('/\D+/', '', $rawPhoneModal);
$waPhoneModal = '';
if ($digitsModal !== '') {
    if (str_starts_with($digitsModal, '62')) {
        $waPhoneModal = $digitsModal;
    } elseif (str_starts_with($digitsModal, '0')) {
        $waPhoneModal = '62' . substr($digitsModal, 1);
    } elseif (str_starts_with($digitsModal, '8')) {
        $waPhoneModal = '62' . $digitsModal;
    } else {
        $waPhoneModal = $digitsModal;
    }
}
$propNameModal = session('activePropertyName') ?: ($isMonthly ? 'Kos Mitra' : 'HomiQ');
$topicModal = $isMonthly ? 'sewa' : 'reservasi';
$waTextModal = "Halo Kak " . trim((string) $reservation['guest_name']) . ", kami dari manajemen " . $propNameModal . " terkait " . $topicModal . " " . ($isWholeHome ? "Homestay Mitra" : "Kamar " . trim((string) $reservation['room_number'])) . " (ID: " . trim((string) $reservation['order_id']) . ").";
$waLinkModal = $waPhoneModal !== '' ? 'https://wa.me/' . $waPhoneModal . '?text=' . rawurlencode($waTextModal) : '';
?>
<div class="modal fade" id="dynamicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h2 class="modal-title h5 mb-0"><?= esc($reservation['guest_name']) ?></h2>
                        <?php if (! empty($waLinkModal)): ?>
                            <a class="btn btn-sm text-white text-nowrap d-inline-flex align-items-center gap-1 py-0 px-2" style="background-color: #25D366; border-color: #22bf5b; font-size: 0.78rem; line-height: 1.6;" href="<?= esc($waLinkModal) ?>" target="_blank" rel="noopener noreferrer" title="Chat WhatsApp <?= esc($reservation['guest_name']) ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.707 1.92 1.804 3.376c.114.148 1.93 2.946 4.678 4.129.654.282 1.164.45 1.564.577.658.21 1.258.18 1.733.11.53-.079 1.628-.667 1.859-1.312.23-.646.23-1.2.16-1.313-.069-.113-.247-.181-.444-.28"/></svg>
                                <span>WhatsApp</span>
                            </a>
                        <?php endif; ?>
                    </div>
                    <small class="text-secondary"><?= esc($bookingGroupId ?? $reservation['order_id']) ?> · <?= $isWholeHome ? '1 unit (seluruh rumah)' : count($groupRooms) . ' kamar' ?><?php if (! empty($reservation['phone'])): ?> · <span class="text-dark"><?= esc($reservation['phone']) ?></span><?php endif; ?></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="booking-detail-feedback"></div>
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card bg-light border-0 mb-3"><div class="card-body"><div class="row g-3"><div class="col-sm-6"><small class="text-secondary d-block"><?= $isMonthly ? 'Periode Sewa' : 'Stay' ?></small><strong><?= esc(date('d M Y', strtotime($reservation['check_in_date']))) ?> — <?= esc(date('d M Y', strtotime($reservation['check_out_date']))) ?></strong><?php if ($isTransit): ?> <span class="badge text-bg-info">Transit</span><?php endif; ?></div><div class="col-sm-3"><small class="text-secondary d-block">Status</small><span class="badge <?= esc($reservation['status'] === 'No_Show' ? 'text-bg-dark' : ($reservation['status'] === 'Cancelled' ? 'text-bg-danger' : 'text-bg-primary')) ?>"><?= esc($reservation['status'] === 'No_Show' ? 'No-Show' : ($isMonthly ? ($reservation['status'] === 'Checked_In' ? 'Masuk' : ($reservation['status'] === 'Checked_Out' ? 'Keluar' : $reservation['status'])) : $reservation['status'])) ?></span></div><div class="col-sm-3"><small class="text-secondary d-block">Source</small><strong><?= esc($reservation['source'] ?? '-') ?></strong><?php if (! empty($reservation['agent_name'])): ?><small class="d-block mt-1 text-primary">🤝 <?= $isMonthly ? 'Penghuni dari agen' : 'Tamu dari agen' ?>: <?= esc($reservation['agent_name']) ?></small><?php endif; ?></div></div></div></div>
                        <?php if ($reservation['status'] === 'Confirmed'): ?>
                            <div class="card border-primary-subtle mb-3">
                                <div class="card-body py-3">
                                    <h3 class="h6 text-primary mb-1"><?= $isMonthly ? 'Ubah Periode Sewa' : 'Ubah Tanggal Menginap' ?></h3>
                                    <p class="small text-secondary mb-3">Berlaku untuk <?= $isWholeHome ? 'seluruh unit homestay' : 'seluruh kamar dalam booking ini' ?>. Nominal booking tetap, tetapi bentrok <?= $isWholeHome ? 'unit' : 'kamar' ?> dan stok Extra Bed akan dicek ulang.</p>
                                    <form class="row g-2 align-items-end" hx-post="<?= site_url('reservations/' . $reservation['id'] . '/stay-dates') ?>" hx-target="#modal-container" hx-swap="innerHTML" <?= $isMonthly ? 'data-monthly-edit-form' : '' ?>>
                                        <?= csrf_field() ?>
                                        <?php if ($isMonthly): ?>
                                            <?php $curMonths = \App\Libraries\PropertyPricing::wholeMonths((string)$reservation['check_in_date'], (string)$reservation['check_out_date']) ?? 1; ?>
                                            <div class="col-sm-4">
                                                <label class="form-label small">Tanggal Masuk</label>
                                                <input class="form-control form-control-sm" type="date" name="check_in_date" value="<?= esc($reservation['check_in_date']) ?>" required data-monthly-checkin>
                                            </div>
                                            <div class="col-sm-3">
                                                <label class="form-label small">Durasi</label>
                                                <select class="form-select form-select-sm" data-monthly-duration-select>
                                                    <?php foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 18, 24] as $m): ?>
                                                        <option value="<?= $m ?>" <?= $m === $curMonths ? 'selected' : '' ?>><?= $m ?> Bulan<?= $m === 12 ? ' (1 Thn)' : '' ?></option>
                                                    <?php endforeach; ?>
                                                    <?php if (! in_array($curMonths, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 18, 24], true)): ?>
                                                        <option value="<?= $curMonths ?>" selected><?= $curMonths ?> Bulan</option>
                                                    <?php endif; ?>
                                                    <option value="custom">Kustom...</option>
                                                </select>
                                                <div class="input-group input-group-sm mt-1 d-none" data-monthly-custom-wrap>
                                                    <input class="form-control form-control-sm" type="number" min="1" max="120" value="<?= $curMonths ?>" placeholder="Bulan" data-monthly-custom-input>
                                                    <span class="input-group-text" style="font-size:0.75rem;">Bln</span>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <label class="form-label small">Tanggal Keluar</label>
                                                <input class="form-control form-control-sm bg-light" type="date" name="check_out_date" value="<?= esc($reservation['check_out_date']) ?>" required readonly data-monthly-checkout title="Otomatis terhitung">
                                            </div>
                                            <div class="col-sm-2 d-grid"><button class="btn btn-outline-primary btn-sm" type="submit">Simpan</button></div>
                                        <?php else: ?>
                                            <div class="col-sm-5"><label class="form-label small">Check-in</label><input class="form-control form-control-sm" type="date" name="check_in_date" value="<?= esc($reservation['check_in_date']) ?>" required></div>
                                            <div class="col-sm-5"><label class="form-label small">Check-out</label><input class="form-control form-control-sm" type="date" name="check_out_date" value="<?= esc($reservation['check_out_date']) ?>" required></div>
                                            <div class="col-sm-2 d-grid"><button class="btn btn-outline-primary btn-sm" type="submit">Simpan</button></div>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="card border-primary-subtle mb-3">
                            <div class="card-body py-3">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                    <div><h3 class="h6 mb-1">Dokumen Tamu</h3><small class="text-secondary"><?= ! empty($reservation['ktp_photo_path']) ? 'Foto KTP tersedia. Anda dapat menggantinya bila diperlukan.' : 'Belum ada foto KTP. Unggah dari sini bila data tamu sudah lengkap.' ?></small></div>
                                    <?php if (! empty($reservation['ktp_photo_path'])): ?><a class="btn btn-outline-primary btn-sm" href="<?= site_url('reservations/' . $reservation['id'] . '/ktp') ?>" target="_blank" rel="noopener">Lihat Foto KTP</a><?php endif; ?>
                                </div>
                                <form method="post" action="<?= site_url('reservations/' . $reservation['id'] . '/ktp') ?>" enctype="multipart/form-data" hx-encoding="multipart/form-data" hx-post="<?= site_url('reservations/' . $reservation['id'] . '/ktp') ?>" hx-target="#modal-container" hx-swap="innerHTML" data-image-upload-form>
                                    <?= csrf_field() ?>
                                    <label class="form-label small fw-semibold" for="ktp-photo-<?= (int) $reservation['id'] ?>"><?= ! empty($reservation['ktp_photo_path']) ? 'Ganti foto KTP' : 'Unggah foto KTP' ?></label>
                                    <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-start">
                                        <div class="flex-grow-1"><input class="form-control form-control-sm" id="ktp-photo-<?= (int) $reservation['id'] ?>" type="file" name="ktp_photo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" data-image-file-input required><small class="text-secondary d-block mt-1">JPG, PNG, atau WEBP. Otomatis dikompres maksimal 200 KB.</small><small class="text-primary d-block" data-image-compression-status></small></div>
                                        <button class="btn btn-primary btn-sm text-nowrap" type="submit"><?= ! empty($reservation['ktp_photo_path']) ? 'Simpan Foto Baru' : 'Simpan Foto KTP' ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card border-0 bg-light mb-3">
                            <div class="card-body py-3">
                                <h3 class="h6 mb-3">Catatan Booking</h3>
                                <?php if (! empty($reservation['guest_notes'])): ?><div class="mb-2"><small class="text-secondary d-block"><?= $isMonthly ? 'Catatan penghuni' : 'Catatan tamu' ?></small><div><?= nl2br(esc($reservation['guest_notes'])) ?></div></div><?php endif; ?>
                                <?php if (! empty($reservation['special_request'])): ?><div class="mb-3"><small class="text-secondary d-block">Permintaan khusus</small><div><?= nl2br(esc($reservation['special_request'])) ?></div></div><?php endif; ?>
                                <form hx-post="<?= site_url('reservations/' . $reservation['id'] . '/important-note') ?>" hx-target="#modal-container" hx-swap="innerHTML">
                                    <?= csrf_field() ?>
                                    <label class="form-label small fw-semibold" for="important-note-<?= (int) $reservation['id'] ?>">Catatan penting</label>
                                    <textarea class="form-control form-control-sm" id="important-note-<?= (int) $reservation['id'] ?>" name="important_note" rows="3" maxlength="2000" placeholder="Tambahkan atau perbarui informasi penting booking."><?= esc($reservation['important_note'] ?? '') ?></textarea>
                                    <div class="d-flex justify-content-between align-items-center gap-2 mt-2"><small class="text-secondary">Berlaku untuk <?= $isWholeHome ? 'seluruh unit homestay' : 'seluruh kamar dalam booking ini' ?>.</small><button class="btn btn-outline-primary btn-sm" type="submit">Simpan Catatan</button></div>
                                </form>
                            </div>
                        </div>
                            <h3 class="h6"><?= $isWholeHome ? 'Unit dalam Booking' : 'Kamar dalam Booking' ?></h3>
                            <div class="table-responsive mb-3"><table class="table table-sm align-middle"><thead><tr><th><?= $isWholeHome ? 'Homestay' : 'Kamar' ?></th><th>ID Reservasi</th><th>Status</th><th class="text-end">Tagihan</th><th class="text-end">Aksi</th></tr></thead><tbody>
                                <?php foreach ($groupRooms as $groupRoom): ?>
                                    <tr class="<?= (int) $groupRoom['id'] === (int) $reservation['id'] ? 'table-primary' : '' ?>">
                                        <td><?= $isWholeHome ? 'Mitra' : 'Room ' . esc($groupRoom['room_number']) ?></td><td><?= esc($groupRoom['order_id']) ?></td><td><?= esc($groupRoom['status']) ?></td>
                                        <td class="text-end">
                                            <?php if ($isWholeHome): ?>Rp<?= number_format((float) $groupRoom['total_amount'], 0, ',', '.') ?><?php else: ?>
                                                <form class="d-flex justify-content-end gap-1" hx-post="<?= site_url('reservations/' . $groupRoom['id'] . '/room-price') ?>" hx-target="#modal-container" hx-swap="innerHTML"><input class="form-control form-control-sm text-end" style="max-width: 150px" type="text" inputmode="numeric" data-currency-input name="total_amount" value="<?= esc(number_format((float) $groupRoom['total_amount'], 0, ',', '.')) ?>" aria-label="Harga Room <?= esc($groupRoom['room_number']) ?>" <?= in_array($groupRoom['status'], ['Cancelled', 'Checked_Out'], true) ? 'disabled' : '' ?>><?= csrf_field() ?><button class="btn btn-outline-primary btn-sm" type="submit" <?= in_array($groupRoom['status'], ['Cancelled', 'Checked_Out'], true) ? 'disabled' : '' ?>>Simpan</button></form>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if (! $isWholeHome && ! in_array($groupRoom['status'], ['Cancelled', 'Checked_Out'], true)): ?>
                                                <form class="d-inline" hx-post="<?= site_url('reservations/' . $groupRoom['id'] . '/status') ?>" hx-target="#booking-detail-feedback" hx-confirm="Batalkan Room <?= esc($groupRoom['room_number'], 'js') ?> saja? Kamar lain tetap aktif. DP yang telah dibayar tetap menjadi pendapatan, sedangkan pembayaran lainnya ditandai sebagai kredit/refund pending."><input type="hidden" name="status" value="Cancelled"><input type="hidden" name="cancel_scope" value="room"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm">Batalkan kamar ini</button></form>
                                            <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody></table></div>
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <h3 class="h6 mb-0">Invoice</h3>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Pilihan cetak invoice">
                                <a class="btn btn-outline-primary" href="<?= site_url('reservations/' . $reservation['id'] . '/invoice') ?>" target="_blank" rel="noopener">Cetak Detail</a>
                                <a class="btn btn-outline-primary" href="<?= site_url('reservations/' . $reservation['id'] . '/invoice?format=ringkas') ?>" target="_blank" rel="noopener">Cetak Ringkas</a>
                            </div>
                        </div>
                        <div class="table-responsive"><table class="table table-sm"><tbody><?php foreach ($items as $item): ?><tr><td><?= esc($isWholeHome && str_starts_with((string) $item['item_name'], 'Room ') ? preg_replace('/^Room \d+/', 'Homestay Mitra', (string) $item['item_name']) : $item['item_name']) ?></td><td class="text-end">Rp<?= number_format((float) $item['amount'], 0, ',', '.') ?></td></tr><?php endforeach; ?></tbody><tfoot><tr class="fw-bold"><td>Total</td><td class="text-end">Rp<?= number_format($total, 0, ',', '.') ?></td></tr></tfoot></table></div>
                        <?php if (in_array((string) ($reservation['status'] ?? ''), ['Confirmed', 'Checked_In'], true)): ?>
                            <div class="card border-primary-subtle mt-4 mb-3">
                                <div class="card-body">
                                    <h3 class="h6 text-primary mb-2">Layanan Tambahan</h3>
                                    <p class="text-secondary small mb-3">Tambahkan layanan setelah booking dibuat atau selama tamu menginap. Nominal layanan akan langsung menambah tagihan dan Outstanding untuk <?= $isWholeHome ? 'Homestay Mitra' : 'Room ' . esc($reservation['room_number']) ?>.</p>
                                    <form hx-post="<?= site_url('reservations/' . $reservation['id'] . '/services') ?>" hx-target="#modal-container" hx-swap="innerHTML">
                                        <?= csrf_field() ?>
                                        <div class="row g-2">
                                            <div class="col-md-4"><label class="form-label small">Breakfast</label><input class="form-control form-control-sm" type="text" inputmode="numeric" data-currency-input name="breakfast_cost" value="0"></div>
                                            <div class="col-md-4"><label class="form-label small">Biaya Extra Bed</label><input class="form-control form-control-sm" type="text" inputmode="numeric" data-currency-input name="extra_bed_cost" value="0"></div>
                                            <div class="col-md-4"><label class="form-label small">Early Check In</label><input class="form-control form-control-sm" type="text" inputmode="numeric" data-currency-input name="early_checkin_cost" value="0"></div>
                                            <div class="col-md-4"><label class="form-label small">Late Checkout</label><input class="form-control form-control-sm" type="text" inputmode="numeric" data-currency-input name="late_checkout_cost" value="0"></div>
                                            <div class="col-md-4"><label class="form-label small">Laundry</label><input class="form-control form-control-sm" type="text" inputmode="numeric" data-currency-input name="laundry_cost" value="0"></div>
                                            <div class="col-md-4"><label class="form-label small">Massage</label><input class="form-control form-control-sm" type="text" inputmode="numeric" data-currency-input name="massage_cost" value="0"></div>
                                            <div class="col-md-4"><label class="form-label small">Sewa Kendaraan</label><input class="form-control form-control-sm" type="text" inputmode="numeric" data-currency-input name="vehicle_rental_cost" value="0"></div>
                                        </div>
                                        <button class="btn btn-primary btn-sm mt-3" type="submit">Tambahkan ke Tagihan</button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                        <h3 class="h6 mt-4">Payment History</h3>
                        <?php if ($payments === []): ?><p class="text-secondary small">Belum ada pembayaran.</p><?php else: ?><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Tanggal</th><th>Metode</th><th>Tipe</th><th>Status</th><th>Bukti</th><th class="text-end">Jumlah</th></tr></thead><tbody><?php foreach ($payments as $payment): ?><?php $paymentStatus = (string) ($payment['status'] ?? 'Pending'); ?><tr><td><?php if (($payment['payment_method'] ?? '') === 'OTA' && $paymentStatus !== 'Refund_Pending'): ?><form class="d-flex gap-1" hx-post="<?= site_url('payments/' . $payment['id'] . '/date') ?>" hx-target="#modal-container" hx-swap="innerHTML"><?= csrf_field() ?><input class="form-control form-control-sm" type="date" name="payment_date" value="<?= esc($payment['payment_date'] ?? '') ?>" required><button class="btn btn-sm btn-outline-primary" type="submit">Simpan</button></form><?php else: ?><?= esc($payment['payment_date'] ?? '-') ?><?php endif; ?></td><td><?= esc($payment['payment_method'] ?? '-') ?></td><td><?= esc($payment['type']) ?></td><td><span class="badge <?= $paymentStatus === 'Refund_Pending' ? 'text-bg-warning' : ($paymentStatus === 'Paid' ? 'text-bg-success' : 'text-bg-secondary') ?>"><?= esc($paymentStatus === 'Refund_Pending' ? 'Kredit/refund pending' : $paymentStatus) ?></span></td><td><div class="d-flex flex-column gap-1 align-items-start"><?php if (! empty($payment['proof_path'])): ?><a class="btn btn-sm btn-outline-primary text-nowrap" href="<?= site_url('payments/' . $payment['id'] . '/proof') ?>" target="_blank" rel="noopener">Lihat bukti</a><?php endif; ?><form method="post" action="<?= site_url('payments/' . $payment['id'] . '/proof') ?>" enctype="multipart/form-data" hx-encoding="multipart/form-data" hx-post="<?= site_url('payments/' . $payment['id'] . '/proof') ?>" hx-target="#modal-container" hx-swap="innerHTML" data-image-upload-form><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><div class="d-flex flex-column gap-1"><input class="form-control form-control-sm" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" data-image-file-input required><button class="btn btn-sm btn-outline-success text-nowrap" type="submit"><?= ! empty($payment['proof_path']) ? 'Ganti bukti' : 'Upload bukti' ?></button></div></form></div></td><td class="text-end">Rp<?= number_format((float) $payment['amount'], 0, ',', '.') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                    </div>
                    <div class="col-lg-5">
                        <div class="row g-2 mb-4"><div class="col-6"><div class="p-3 rounded bg-success-subtle"><small class="text-secondary">Terbayar</small><strong class="d-block">Rp<?= number_format($paid, 0, ',', '.') ?></strong></div></div><div class="col-6"><div class="p-3 rounded bg-danger-subtle"><small class="text-secondary">Outstanding</small><strong class="d-block">Rp<?= number_format($outstanding, 0, ',', '.') ?></strong></div></div></div>
                        <?php if (($reservation['source'] ?? '') === 'Booking.com' && $reservation['status'] !== 'Cancelled'): ?>
                            <div class="card border-info-subtle mb-4">
                                <div class="card-body py-3">
                                    <h3 class="h6 text-info-emphasis mb-1">Pencairan Booking.com</h3>
                                    <p class="small text-secondary mb-3">Isi saat nominal final dari Booking.com sudah diketahui. Tanggal pencairan tetap diisi dari Payment History setelah nominal tersimpan.</p>
                                    <form class="row g-2" hx-post="<?= site_url('reservations/' . $reservation['id'] . '/booking-com-settlement') ?>" hx-target="#modal-container" hx-swap="innerHTML">
                                        <?= csrf_field() ?>
                                        <div class="col-12"><label class="form-label small">Konfirmasi total booking</label><input class="form-control form-control-sm" type="text" inputmode="numeric" data-currency-input name="booking_total" value="<?= esc(number_format((float) $total, 0, ',', '.')) ?>" required></div>
                                        <div class="col-12"><label class="form-label small">Nominal pencairan diterima</label><input class="form-control form-control-sm" type="text" inputmode="numeric" data-currency-input name="settlement_amount" value="0" required></div>
                                        <div class="col-12 d-grid"><button class="btn btn-info btn-sm" type="submit">Simpan Nominal OTA</button></div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                        <form class="reconciliation-card mb-4" hx-post="<?= site_url('reservations/' . $reservation['id'] . '/reconciliation') ?>" hx-target="#modal-container" hx-swap="innerHTML">
                            <?= csrf_field() ?>
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <div><h3 class="h6 mb-1">Rekonsiliasi Finance</h3><p class="small text-secondary mb-0">Tandai setelah booking ini dicocokkan dan dicatat.</p></div>
                                <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" id="reconciliation-toggle-<?= esc($reservation['id']) ?>" data-reconciliation-toggle <?= $isReconciled ? 'checked' : '' ?>><label class="form-check-label text-nowrap small fw-semibold" for="reconciliation-toggle-<?= esc($reservation['id']) ?>"><?= $isReconciled ? 'Terekonsiliasi' : 'Belum' ?></label></div>
                            </div>
                        </form>
                        <?php if ($outstanding > 0 && $reservation['status'] !== 'Cancelled'): ?>
                            <h3 class="h6">Log Payment</h3>
                            <form method="post" action="<?= site_url('reservations/' . $reservation['id'] . '/payments') ?>" enctype="multipart/form-data" hx-encoding="multipart/form-data" hx-post="<?= site_url('reservations/' . $reservation['id'] . '/payments') ?>" hx-target="#modal-container" hx-swap="innerHTML" class="mb-4" data-payment-log-form data-image-upload-form>
                                <?= csrf_field() ?>
                                <div class="mb-2"><label class="form-label">Nominal</label><input class="form-control" type="text" inputmode="numeric" data-currency-input name="amount" value="<?= esc(number_format((float) $outstanding, 0, ',', '.')) ?>" required></div>
                                <div class="row g-2">
                                    <div class="col-6"><label class="form-label">Tanggal</label><input class="form-control" type="date" name="payment_date" value="<?= date('Y-m-d') ?>"></div>
                                    <div class="col-6">
                                        <label class="form-label">Metode</label>
                                        <select class="form-select" name="payment_method" data-payment-log-method>
                                            <?php if ($isMonthly): ?>
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
                                </div>
                                <div class="mt-2 d-none" data-payment-proof-field><label class="form-label">Bukti transfer <span class="text-secondary fw-normal">(opsional)</span></label><input class="form-control" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" data-image-file-input disabled><small class="text-secondary d-block mt-1">Foto otomatis dikompres maksimal 200 KB.</small><small class="text-primary d-block" data-image-compression-status></small></div>
                                <div class="mt-2"><label class="form-label">Catatan</label><input class="form-control" name="notes"></div>
                                <button class="btn btn-success w-100 mt-3" type="submit">Simpan Pembayaran</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($reservation['status'] === 'Checked_Out'): ?>
                            <h3 class="h6">Koreksi Status</h3>
                            <div class="d-grid gap-2">
                                <form hx-post="<?= site_url('reservations/' . $reservation['id'] . '/status') ?>" hx-target="#booking-detail-feedback" hx-confirm="Kembalikan reservasi ini ke status <?= $isMonthly ? 'Masuk' : 'Check-in' ?>? Room akan ditandai Occupied kembali."><input type="hidden" name="status" value="Checked_In"><?= csrf_field() ?><button class="btn btn-outline-primary w-100">Kembalikan ke <?= $isMonthly ? 'Masuk' : 'Check-in' ?></button></form>
                                <div class="alert alert-warning small mb-0">Gunakan hanya jika <?= $isMonthly ? 'status Keluar' : 'Check Out' ?> sebelumnya salah. Sistem akan memeriksa bentrok kamar terlebih dahulu.</div>
                            </div>
                        <?php elseif (! in_array($reservation['status'], ['Cancelled', 'No_Show'], true)): ?>
                            <h3 class="h6">Aksi <?= $isWholeHome ? 'Homestay' : (count($groupRooms) > 1 ? 'Booking' : 'Room ' . esc($reservation['room_number'])) ?></h3>
                            <div class="d-grid gap-2">
                                <?php if ($isMonthly && in_array($reservation['status'], ['Confirmed', 'Checked_In'], true)): ?>
                                    <button class="btn btn-outline-success w-100 d-inline-flex align-items-center justify-content-center gap-2"
                                            type="button"
                                            hx-get="<?= site_url('reservations/' . $reservation['id'] . '/extend') ?>"
                                            hx-target="#modal-container"
                                            hx-swap="innerHTML">
                                        <span>➕ Perpanjang Sewa (Extend)</span>
                                    </button>
                                <?php endif; ?>
                                <?php if ($reservation['status'] === 'Confirmed'): ?>
                                    <?php if ($canCheckIn): ?>
                                        <form hx-post="<?= site_url('reservations/' . $reservation['id'] . '/status') ?>" hx-target="#booking-detail-feedback"><input type="hidden" name="status" value="Checked_In"><?= csrf_field() ?><button class="btn btn-primary w-100"><?= $isMonthly ? 'Masuk' : 'Check In' ?></button></form>
                                    <?php else: ?>
                                        <button class="btn btn-primary w-100" type="button" disabled><?= $isMonthly ? 'Masuk' : 'Check In' ?></button>
                                        <div class="alert alert-warning small mb-0"><?= $isMonthly ? 'Tanggal masuk' : 'Check In' ?> baru tersedia mulai <?= esc(date('d/m/Y', strtotime($reservation['check_in_date']))) ?>.</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($reservation['status'] === 'Checked_In'): ?>
                                    <?php if ($outstanding > 0): ?>
                                        <button class="btn btn-dark w-100" type="button" disabled><?= $isMonthly ? 'Keluar' : 'Check Out' ?></button>
                                        <div class="alert alert-danger small mb-0"><?= $isMonthly ? 'Keluar' : 'Check Out' ?> tidak dapat dilakukan. Outstanding booking masih Rp<?= number_format($outstanding, 0, ',', '.') ?>; lunasi tagihan terlebih dahulu.</div>
                                    <?php else: ?>
                                        <form hx-post="<?= site_url('reservations/' . $reservation['id'] . '/status') ?>" hx-target="#booking-detail-feedback"><input type="hidden" name="status" value="Checked_Out"><?= csrf_field() ?><button class="btn btn-dark w-100"><?= $isMonthly ? 'Keluar' : 'Check Out' ?></button></form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($reservation['status'] === 'Confirmed'): ?>
                                    <form hx-post="<?= site_url('reservations/' . $reservation['id'] . '/status') ?>" hx-target="#booking-detail-feedback" hx-confirm="Tandai booking ini sebagai No-Show? Kamar akan kembali tersedia dan pembayaran yang sudah masuk tetap menjadi pendapatan."><input type="hidden" name="status" value="No_Show"><input type="hidden" name="no_show_scope" value="booking"><?= csrf_field() ?><button class="btn btn-outline-warning w-100">Tandai No-Show</button></form>
                                <?php endif; ?>
                                <div class="alert alert-warning small mb-0">Pembatalan akan berlaku untuk <?= esc($isWholeHome ? 'Homestay Mitra (seluruh rumah)' : ($groupRoomLabel ?: 'kamar dalam booking ini')) ?>. DP yang sudah dibayar hangus dan tetap menjadi pendapatan; pembayaran selain DP ditandai sebagai kredit/refund pending.</div>
                                <form hx-post="<?= site_url('reservations/' . $reservation['id'] . '/status') ?>" hx-target="#booking-detail-feedback" hx-confirm="Batalkan booking untuk <?= esc($isWholeHome ? 'Homestay Mitra' : ($groupRoomLabel ?: 'kamar ini'), 'js') ?>? DP yang sudah dibayar tetap menjadi pendapatan, sedangkan pembayaran lain akan ditandai sebagai kredit/refund pending."><input type="hidden" name="status" value="Cancelled"><input type="hidden" name="cancel_scope" value="booking"><?= csrf_field() ?><button class="btn btn-outline-danger w-100">Batalkan <?= $isWholeHome ? 'Booking Homestay' : (count($groupRooms) > 1 ? 'Seluruh Booking (' . count($groupRooms) . ' kamar)' : 'Reservasi') ?></button></form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
