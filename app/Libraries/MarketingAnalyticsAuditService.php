<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

final class MarketingAnalyticsAuditService
{
    private readonly MarketingAnalyticsQueryService $queries;

    public function __construct(private readonly BaseConnection $db)
    {
        helper('marketing_analytics');
        $this->queries = new MarketingAnalyticsQueryService($db);
    }

    /** @return array<string, mixed> */
    public function audit(MarketingAnalyticsFilter $filter): array
    {
        $reservations = $this->scopedReservations($filter);
        $validReservations = array_values(array_filter(
            $reservations,
            static fn (array $row): bool => marketing_is_valid_reservation_status($row['status'] ?? null),
        ));

        $dashboard = (new MarketingAnalyticsService($this->db))->dashboard($filter);
        $stageOne = [
            'summary' => $dashboard['summary'],
            'origins' => $dashboard['origins'],
            'loyal_guests' => $dashboard['loyal_guests'],
        ];

        return [
            'generated_at' => date(DATE_ATOM),
            'filter' => $filter->toArray() + [
                'property_id' => $filter->propertyId(),
                'start_date' => $filter->startDate()->format('Y-m-d'),
                'end_date_exclusive' => $filter->endDate()->format('Y-m-d'),
            ],
            'reservations' => $this->reservationAudit($reservations, $validReservations),
            'origins' => $this->originAudit($validReservations, $filter),
            'duplicate_guests' => $this->duplicateGuestAudit($filter),
            'date_ranges' => $this->dateRanges($filter),
            'payments' => $this->paymentAudit($filter),
            'expenses' => $this->expenseAudit($filter),
            'room_capacity' => $this->roomCapacityAudit($filter),
            'stage_one_dashboard' => [
                'summary' => $stageOne['summary'],
                'origins' => $stageOne['origins'],
                'loyal_guests' => $stageOne['loyal_guests'],
                'reconciles_with_audit' => [
                    'unique_guests' => $stageOne['summary']['unique_guests'] === count(array_unique(array_column($validReservations, 'guest_id'))),
                    'unique_bookings' => $stageOne['summary']['unique_bookings'] === $this->uniqueBookingCount($validReservations),
                ],
            ],
            'stage_two_dashboard' => [
                'performance' => $dashboard['performance'],
                'reconciles_with_audit' => [
                    'profit_equals_revenue_less_cost' => $this->performanceProfitReconciles($dashboard['performance']['rows']),
                    'all_cost_allocated_once' => $filter->includesAllGuesthouses()
                        ? abs(($dashboard['performance']['totals']['cost'] + $dashboard['performance']['unallocated_expense']) - $this->expenseAudit($filter)['amount_in_period']) < 0.01
                        : null,
                ],
            ],
            'is_monthly' => $isMonthly = PropertyPricing::forProperty($filter->propertyId())['pricing_mode'] === 'room_monthly',
            'stage_three_dashboard' => ! $isMonthly ? [
                'occupancy' => $dashboard['occupancy'],
                'reconciles_with_audit' => $this->occupancyReconciles($dashboard['occupancy']),
            ] : [
                'vacant_days' => $dashboard['vacant_days'],
                'tenant_turnover' => $dashboard['tenant_turnover'],
                'committed_rent' => $dashboard['committed_rent'],
                'lease_expirations' => $dashboard['lease_expirations'],
            ],
            'stage_four_dashboard' => ! $isMonthly ? [
                'annual_summary' => $dashboard['annual_summary'],
                'reconciles_with_audit' => $this->annualSummaryReconciles($dashboard['annual_summary']),
            ] : null,
            'stage_five_dashboard' => ! $isMonthly ? [
                'forecast' => $dashboard['forecast'],
                'reconciles_with_audit' => [
                    'minimum_history_enforced' => $dashboard['forecast']['enabled']
                        ? $dashboard['forecast']['complete_months'] >= $dashboard['forecast']['required_months']
                        : $dashboard['forecast']['points'] === [],
                    'horizon_is_six_months' => ! $dashboard['forecast']['enabled'] || count($dashboard['forecast']['points']) === 6,
                    'bounds_are_ordered' => $this->forecastBoundsReconcile($dashboard['forecast']['points']),
                ],
            ] : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function scopedReservations(MarketingAnalyticsFilter $filter): array
    {
        $builder = $this->db->table('reservations')
            ->select(
                'reservations.id, reservations.guest_id, reservations.order_id, '
                . 'reservations.booking_group_id, reservations.status, '
                . 'reservations.check_in_date, reservations.check_out_date, '
                . 'guests.full_name, guests.email, guests.phone, guests.origin, rooms.floor AS guesthouse',
            )
            ->join('guests', 'guests.id = reservations.guest_id')
            ->join('rooms', 'rooms.id = reservations.room_id');

        $this->queries->applyPeriod($builder, $filter, 'reservations.check_in_date');
        $this->queries->applyGuesthouses($builder, $filter);

        return $builder->orderBy('reservations.id', 'ASC')->get()->getResultArray();
    }

    /**
     * @param list<array<string, mixed>> $reservations
     * @param list<array<string, mixed>> $validReservations
     * @return array<string, mixed>
     */
    private function reservationAudit(array $reservations, array $validReservations): array
    {
        $statusCounts = [];
        $guestIds = [];
        $bookingIds = [];
        $invalidDateRanges = 0;

        foreach ($reservations as $row) {
            $status = trim((string) ($row['status'] ?? '')) ?: '(kosong)';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            // Same-day checkout is a valid transit stay (one occupied night).
            if ((string) ($row['check_out_date'] ?? '') < (string) ($row['check_in_date'] ?? '')) {
                $invalidDateRanges++;
            }
        }

        foreach ($validReservations as $row) {
            $guestIds[(int) $row['guest_id']] = true;
            $bookingId = trim((string) ($row['booking_group_id'] ?? ''));
            if ($bookingId === '') {
                $bookingId = trim((string) ($row['order_id'] ?? '')) ?: 'reservation:' . (int) $row['id'];
            }
            $bookingIds[$bookingId] = true;
        }

        ksort($statusCounts);

        return [
            'rows_in_period' => count($reservations),
            'valid_rows' => count($validReservations),
            'excluded_rows' => count($reservations) - count($validReservations),
            'unique_main_guests' => count($guestIds),
            'unique_booking_groups' => count($bookingIds),
            'invalid_stay_date_ranges' => $invalidDateRanges,
            'status_counts' => $statusCounts,
            'valid_statuses' => marketing_valid_reservation_statuses(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $validReservations
     * @return array<string, mixed>
     */
    private function originAudit(array $validReservations, MarketingAnalyticsFilter $filter): array
    {
        $guests = [];
        foreach ($validReservations as $row) {
            $guests[(int) $row['guest_id']] = normalize_guest_origin($row['origin'] ?? null);
        }

        $categories = array_count_values(array_values($guests));
        arsort($categories);
        $unknown = $categories['Tidak diketahui'] ?? 0;
        $total = count($guests);
        $allGuests = $this->db->table('guests')->select('id, origin')->where('property_id', $filter->propertyId())->get()->getResultArray();
        $allUnknown = count(array_filter(
            $allGuests,
            static fn (array $guest): bool => normalize_guest_origin($guest['origin'] ?? null) === 'Tidak diketahui',
        ));

        return [
            'unique_main_guests' => $total,
            'unknown' => $unknown,
            'unknown_percentage' => $total === 0 ? 0.0 : round(($unknown / $total) * 100, 2),
            'normalized_counts' => $categories,
            'all_guest_records' => count($allGuests),
            'all_guest_records_unknown' => $allUnknown,
            'all_guest_records_unknown_percentage' => $allGuests === [] ? 0.0 : round(($allUnknown / count($allGuests)) * 100, 2),
        ];
    }

    /** @return array<string, mixed> */
    private function duplicateGuestAudit(MarketingAnalyticsFilter $filter): array
    {
        $guests = [];
        foreach ($this->db->table('guests')
            ->select('id, full_name, email, phone')
            ->where('property_id', $filter->propertyId())
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray() as $row) {
            $guests[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['full_name'],
                'email' => canonical_guest_email($row['email'] ?? null),
                'phone' => canonical_guest_phone($row['phone'] ?? null),
            ];
        }

        $emailGroups = $this->duplicateGroups($guests, 'email');
        $phoneGroups = $this->duplicateGroups($guests, 'phone');

        return [
            'scope' => 'Seluruh record tamu pada properti terpilih',
            'guest_record_count' => count($guests),
            'email_group_count' => count($emailGroups),
            'phone_group_count' => count($phoneGroups),
            'email_groups' => $emailGroups,
            'phone_groups' => $phoneGroups,
        ];
    }

    /**
     * @param array<int, array{id:int,name:string,email:?string,phone:?string}> $guests
     * @return list<array{guest_ids:list<int>,names:list<string>,record_count:int}>
     */
    private function duplicateGroups(array $guests, string $field): array
    {
        $groups = [];
        foreach ($guests as $guest) {
            $value = $guest[$field];
            if ($value !== null) {
                $groups[$value][] = $guest;
            }
        }

        $duplicates = [];
        foreach ($groups as $members) {
            if (count($members) < 2) {
                continue;
            }
            $duplicates[] = [
                'guest_ids' => array_column($members, 'id'),
                'names' => array_column($members, 'name'),
                'record_count' => count($members),
            ];
        }

        usort($duplicates, static fn (array $a, array $b): int => $b['record_count'] <=> $a['record_count']);

        return $duplicates;
    }

    /** @return array<string, array{min:?string,max:?string}> */
    private function dateRanges(MarketingAnalyticsFilter $filter): array
    {
        $reservations = $this->db->table('reservations')
            ->select('MIN(reservations.check_in_date) AS min_date, MAX(reservations.check_out_date) AS max_date')
            ->join('rooms', 'rooms.id = reservations.room_id');
        $this->queries->applyGuesthouses($reservations, $filter);

        $payments = $this->db->table('payments')
            ->select('MIN(payments.payment_date) AS min_date, MAX(payments.payment_date) AS max_date')
            ->join('reservations', 'reservations.id = payments.reservation_id')
            ->join('rooms', 'rooms.id = reservations.room_id');
        $this->queries->applyGuesthouses($payments, $filter);

        $expenses = $this->db->table('expenses')
            ->select('MIN(expenses.date_incurred) AS min_date, MAX(expenses.date_incurred) AS max_date');
        $this->queries->applyExpenseGuesthouses($expenses, $filter);

        return [
            'stays' => $this->rangeResult($reservations),
            'payments' => $this->rangeResult($payments),
            'expenses' => $this->rangeResult($expenses),
        ];
    }

    /** @return array{min:?string,max:?string} */
    private function rangeResult(BaseBuilder $builder): array
    {
        $row = $builder->get()->getRowArray() ?? [];

        return [
            'min' => isset($row['min_date']) ? (string) $row['min_date'] : null,
            'max' => isset($row['max_date']) ? (string) $row['max_date'] : null,
        ];
    }

    /** @return array<string, int|float> */
    private function expenseAudit(MarketingAnalyticsFilter $filter): array
    {
        $builder = $this->queries->expenses($filter)
            ->select('expenses.id, expenses.amount, expenses.guesthouse, expenses.status');
        $expenses = $builder->get()->getResultArray();

        $expenseIds = array_map('intval', array_column($expenses, 'id'));
        $assignments = [];
        if ($expenseIds !== []) {
            foreach ($this->db->table('expense_guesthouses')
                ->select('expense_id, guesthouse')
                ->whereIn('expense_id', $expenseIds)
                ->get()
                ->getResultArray() as $row) {
                $assignments[(int) $row['expense_id']][(int) $row['guesthouse']] = true;
            }
        }

        $none = 0;
        $single = 0;
        $multiple = 0;
        $legacyMismatch = 0;
        foreach ($expenses as $expense) {
            $id = (int) $expense['id'];
            $guesthouses = array_keys($assignments[$id] ?? []);
            $legacyGuesthouse = (int) $expense['guesthouse'];
            if ($guesthouses === [] && $legacyGuesthouse >= 1 && $legacyGuesthouse <= 5) {
                $guesthouses[] = $legacyGuesthouse;
            }

            match (true) {
                count($guesthouses) === 0 => $none++,
                count($guesthouses) === 1 => $single++,
                default => $multiple++,
            };

            if ($legacyGuesthouse >= 1 && $legacyGuesthouse <= 5
                && isset($assignments[$id])
                && ! isset($assignments[$id][$legacyGuesthouse])) {
                $legacyMismatch++;
            }
        }

        return [
            'rows_in_period' => count($expenses),
            'amount_in_period' => array_sum(array_map(static fn (array $row): float => (float) $row['amount'], $expenses)),
            'without_guesthouse' => $none,
            'single_guesthouse' => $single,
            'multiple_guesthouses' => $multiple,
            'legacy_assignment_mismatches' => $legacyMismatch,
        ];
    }

    /** @return array<string, int|float> */
    private function paymentAudit(MarketingAnalyticsFilter $filter): array
    {
        $payments = $this->queries->payments($filter)
            ->select(
                'payments.id, payments.reservation_id, payments.amount, '
                . 'reservations.booking_group_id, reservations.order_id',
            )
            ->get()
            ->getResultArray();
        $reservationIds = [];
        $bookingIds = [];

        foreach ($payments as $payment) {
            $reservationIds[(int) $payment['reservation_id']] = true;
            $bookingId = trim((string) ($payment['booking_group_id'] ?? ''));
            if ($bookingId === '') {
                $bookingId = trim((string) ($payment['order_id'] ?? '')) ?: 'reservation:' . (int) $payment['reservation_id'];
            }
            $bookingIds[$bookingId] = true;
        }

        return [
            'paid_rows_in_period' => count($payments),
            'paid_amount_in_period' => array_sum(array_map(static fn (array $row): float => (float) $row['amount'], $payments)),
            'reservation_count' => count($reservationIds),
            'booking_group_count' => count($bookingIds),
        ];
    }

    /** @return array<int, int> */
    private function roomCapacityAudit(MarketingAnalyticsFilter $filter): array
    {
        $builder = $this->db->table('rooms')
            ->select('floor AS guesthouse, COUNT(*) AS room_count')
            ->where('property_id', $filter->propertyId())
            ->groupBy('floor')
            ->orderBy('floor', 'ASC');
        if ($filter->propertyId() === 1 && ! $filter->includesAllGuesthouses()) {
            $builder->whereIn('floor', $filter->guesthouses());
        }

        $counts = [];
        foreach ($builder->get()->getResultArray() as $row) {
            $counts[(int) $row['guesthouse']] = (int) $row['room_count'];
        }

        return $counts;
    }

    /** @param list<array<string, mixed>> $reservations */
    private function uniqueBookingCount(array $reservations): int
    {
        $bookingIds = [];
        foreach ($reservations as $row) {
            $bookingId = trim((string) ($row['booking_group_id'] ?? ''));
            if ($bookingId === '') {
                $bookingId = trim((string) ($row['order_id'] ?? '')) ?: 'reservation:' . (int) $row['id'];
            }
            $bookingIds[$bookingId] = true;
        }

        return count($bookingIds);
    }

    /** @param list<array{revenue:float,cost:float,profit:float}> $rows */
    private function performanceProfitReconciles(array $rows): bool
    {
        foreach ($rows as $row) {
            if (abs(($row['revenue'] - $row['cost']) - $row['profit']) >= 0.01) {
                return false;
            }
        }

        return true;
    }

    /** @param array{points:list<array<string,mixed>>,summary:array<string,mixed>} $occupancy @return array{point_formula:bool,weighted_formula:bool} */
    private function occupancyReconciles(array $occupancy): array
    {
        $pointsReconcile = true;
        foreach ($occupancy['points'] as $point) {
            $available = (int) $point['room_nights_available'];
            $expected = $available === 0 ? 0.0 : round(((int) $point['room_nights_sold'] / $available) * 100, 2);
            if (abs((float) $point['occupancy'] - $expected) > 0.001) {
                $pointsReconcile = false;
                break;
            }
        }

        $summary = $occupancy['summary'];
        $available = (int) $summary['room_nights_available'];
        $expected = $available === 0 ? 0.0 : round(((int) $summary['room_nights_sold'] / $available) * 100, 2);

        return [
            'point_formula' => $pointsReconcile,
            'weighted_formula' => abs((float) $summary['occupancy'] - $expected) <= 0.001,
        ];
    }

    /** @param array{points:list<array<string,mixed>>,selected:array<string,mixed>} $summary @return array{weighted_formula:bool,yoy_formula:bool} */
    private function annualSummaryReconciles(array $summary): array
    {
        $weightedFormula = true;
        $yoyFormula = true;
        $previous = null;
        foreach ($summary['points'] as $point) {
            $available = (int) $point['room_nights_available'];
            $expectedOccupancy = $available === 0 ? 0.0 : round(((int) $point['room_nights_sold'] / $available) * 100, 2);
            if (abs((float) $point['occupancy'] - $expectedOccupancy) > 0.001) {
                $weightedFormula = false;
            }
            if ($previous !== null && $previous['room_nights_sold'] > 0) {
                $expectedYoy = round((((int) $point['room_nights_sold'] - $previous['room_nights_sold']) / $previous['room_nights_sold']) * 100, 2);
                if (abs((float) $point['yoy_room_nights'] - $expectedYoy) > 0.001) {
                    $yoyFormula = false;
                }
            }
            $previous = $point;
        }

        return ['weighted_formula' => $weightedFormula, 'yoy_formula' => $yoyFormula];
    }

    /** @param list<array<string,mixed>> $points */
    private function forecastBoundsReconcile(array $points): bool
    {
        foreach ($points as $point) {
            if ($point['lower'] < 0 || $point['lower'] > $point['value'] || $point['value'] > $point['upper']) {
                return false;
            }
        }

        return true;
    }
}
