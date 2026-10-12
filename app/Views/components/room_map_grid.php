<?php
$previous = $firstDay->modify('-1 month')->format('Y-m');
$next = $firstDay->modify('+1 month')->format('Y-m');
$guesthouseQuery = $guesthouse !== null ? '&guesthouse=' . $guesthouse : '';
$today = $today ?? date('Y-m-d');
$pricing = \App\Libraries\PropertyPricing::active();
$isMonthly = $pricing['pricing_mode'] === 'room_monthly';
$isWholeHome = $pricing['pricing_mode'] === 'whole_home_nightly';
?>
<a id="room-map-previous" class="btn btn-light border" href="<?= site_url('room-map?month=' . $previous . $guesthouseQuery) ?>"
   hx-get="<?= site_url('room-map/grid?month=' . $previous . $guesthouseQuery) ?>" hx-target="#room-map-grid"
   hx-push-url="<?= site_url('room-map?month=' . $previous . $guesthouseQuery) ?>" hx-swap-oob="true">&larr;</a>
<span id="month-label" class="btn btn-light border disabled px-4" hx-swap-oob="true"><?= esc($firstDay->format('F Y')) ?></span>
<a id="room-map-next" class="btn btn-light border" href="<?= site_url('room-map?month=' . $next . $guesthouseQuery) ?>"
   hx-get="<?= site_url('room-map/grid?month=' . $next . $guesthouseQuery) ?>" hx-target="#room-map-grid"
   hx-push-url="<?= site_url('room-map?month=' . $next . $guesthouseQuery) ?>" hx-swap-oob="true">&rarr;</a>
<?php if (\App\Libraries\PropertyContext::activeId() === \App\Libraries\PropertyContext::HOMIQ_ID): ?>
<select id="guesthouse-filter" class="form-select room-map-gh-filter" name="guesthouse"
        hx-get="<?= site_url('room-map/grid?month=' . $firstDay->format('Y-m')) ?>"
        hx-target="#room-map-grid" hx-swap-oob="true">
    <option value="" <?= $guesthouse === null ? 'selected' : '' ?>>Semua GH</option>
    <?php for ($gh = 1; $gh <= 5; $gh++): ?>
        <option value="<?= $gh ?>" <?= $guesthouse === $gh ? 'selected' : '' ?>>GH_<?= $gh ?></option>
    <?php endfor; ?>
</select>
<?php endif; ?>
<div class="card border-0 shadow-sm overflow-hidden">
    <div class="table-responsive room-map-scroll">
        <table class="table room-map-table align-middle mb-0">
            <thead>
                <tr>
                    <th class="room-column sticky-column bg-white"><?= $isWholeHome ? 'Unit' : ($isMonthly ? 'Kamar' : 'Room') ?></th>
                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                        <?php
                        $date = $firstDay->setDate((int) $firstDay->format('Y'), (int) $firstDay->format('m'), $day);
                        $isToday = $date->format('Y-m-d') === $today;
                        $isSunday = $date->format('D') === 'Sun';
                        $dayClasses = trim('day-column text-center ' . ($isToday ? 'today-column ' : '') . ($isSunday ? 'sunday-column' : ''));
                        ?>
                        <th class="<?= esc($dayClasses) ?>">
                            <span class="d-block small <?= $isSunday ? 'text-danger' : 'text-secondary' ?>"><?= esc($date->format('D')) ?></span><?= $day ?>
                        </th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($rooms === []): ?>
                    <tr><td colspan="<?= $daysInMonth + 1 ?>" class="text-center text-secondary py-5">Belum ada kamar untuk properti ini.</td></tr>
                <?php endif; ?>
                <?php foreach ($rooms as $room): ?>
                        <?php
                        $roomStatus = (string) ($room['status'] ?? '');
                        $roomStatusClass = match ($roomStatus) {
                            'Available' => 'room-status-available',
                            'Occupied' => 'room-status-occupied',
                            'Maintenance' => 'room-status-maintenance',
                            'Dirty' => 'room-status-dirty',
                            default => 'room-status-neutral',
                        };
                        ?>
                        <tr>
                            <th class="room-column sticky-column bg-white">
                                <span class="fw-semibold"><?= esc($room['room_number']) ?></span>
                                <small class="d-block text-secondary"><?= esc($room['room_type']) ?></small>
                                <span class="badge rounded-pill room-status-badge <?= esc($roomStatusClass) ?> mt-1"><?= esc($roomStatus) ?></span>
                            </th>
                            <?php
                            // A reservation is rendered once, from its visible start date, with colspan
                            // equal to its stay length. This keeps multi-night stays as one connected card.
                            $day = 1;
                            $monthStart = $firstDay->format('Y-m-d');
                            $monthEndExclusive = $firstDay->modify('+1 month')->format('Y-m-d');
                            while ($day <= $daysInMonth):
                                $date = $firstDay->setDate((int) $firstDay->format('Y'), (int) $firstDay->format('m'), $day)->format('Y-m-d');
                                $booking = null;
                                foreach ($bookingsByRoom[(int) $room['id']] ?? [] as $candidate) {
                                    $visibleStart = max($candidate['check_in_date'], $monthStart);
                                    if ($visibleStart === $date) {
                                        $booking = $candidate;
                                        break;
                                    }
                                }

                                if ($booking === null):
                            ?>
                                <td class="booking-cell p-1 <?= $date === $today ? 'today-column' : '' ?>"></td>
                                <?php $day++; continue; ?>
                            <?php
                                endif;

                                $isTransit = $booking['check_in_date'] === $booking['check_out_date'];
                                $bookingEndExclusive = $isTransit ? (new DateTimeImmutable($booking['check_in_date']))->modify('+1 day')->format('Y-m-d') : $booking['check_out_date'];
                                $visibleEnd = min($bookingEndExclusive, $monthEndExclusive);
                                $span = max(1, (int) (new DateTimeImmutable($date))->diff(new DateTimeImmutable($visibleEnd))->days);
                                $paid = (float) $booking['paid_amount'];
                                $total = (float) $booking['total_amount'];
                                $paymentClass = $paid >= $total ? 'booking-paid' : ($paid > 0 ? 'booking-dp' : 'booking-unpaid');
                                $isReconciled = (int) ($booking['is_reconciled'] ?? 0) === 1;
                                $isCheckedIn = (string) ($booking['status'] ?? '') === 'Checked_In';
                                $isCheckedOut = (string) ($booking['status'] ?? '') === 'Checked_Out';
                                $coversToday = $booking['check_in_date'] <= $today && $bookingEndExclusive > $today;
                                $guestName = (string) $booking['guest_name'];
                                $agentName = trim((string) ($booking['agent_name'] ?? ''));
                            ?>
                                <td colspan="<?= $span ?>" class="booking-cell booking-span-cell p-1<?= $coversToday ? ' booking-covers-today' : '' ?>">
                                    <button type="button"
                                            class="booking-block booking-block-span <?= $paymentClass ?><?= $isReconciled ? ' booking-reconciled' : '' ?><?= $isCheckedIn ? ' booking-checked-in' : '' ?><?= $isCheckedOut ? ' booking-checked-out' : '' ?>"
                                            data-reservation-detail="<?= esc(site_url('reservations/' . $booking['id'])) ?>"
                                            title="<?= esc($guestName . ' — ' . $booking['order_id'] . ($agentName !== '' ? ' · Agen: ' . $agentName : '') . ($isCheckedIn ? ($isMonthly ? ' · Sudah masuk' : ' · Sudah check-in') : '') . ($isCheckedOut ? ($isMonthly ? ' · Sudah keluar' : ' · Sudah check-out') : '') . ($isReconciled ? ' · Terekonsiliasi' : '')) ?>"
                                            aria-label="Detail reservasi <?= esc($guestName) ?>">
                                        <span class="booking-block-title"><?= $isReconciled ? '★ ' : '' ?><?= esc($guestName) ?><?php if ($agentName !== ''): ?> <span class="booking-agent-marker" title="Agen: <?= esc($agentName) ?>">🤝 Agen</span><?php endif; ?><?php if ($isCheckedIn): ?> <span class="booking-checkin-marker">✓ CI</span><?php endif; ?><?php if ($isCheckedOut): ?> <span class="booking-checkout-marker">✓ CO</span><?php endif; ?></span>
                                        <?php if ($span > 1 || $isTransit): ?>
                                            <span class="booking-block-meta"><?= $isTransit ? 'Transit · ' : '' ?><?= esc($booking['check_in_date']) ?><?= $isTransit ? '' : ' – ' . esc($booking['check_out_date']) ?></span>
                                        <?php endif; ?>
                                    </button>
                                </td>
                                <?php $day += $span; ?>
                            <?php endwhile; ?>
                        </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
