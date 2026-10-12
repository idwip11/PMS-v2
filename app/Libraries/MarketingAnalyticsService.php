<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

final class MarketingAnalyticsService
{
    private readonly MarketingAnalyticsQueryService $queries;

    public function __construct(private readonly BaseConnection $db)
    {
        helper('marketing_analytics');
        $this->queries = new MarketingAnalyticsQueryService($db);
    }

    /**
     * @return array{
     *     summary:array{unique_guests:int,unique_bookings:int,reservation_rows:int,unknown_origins:int,unknown_origin_percentage:float},
     *     origins:list<array{label:string,value:int,percentage:float}>,
     *     loyal_guests:list<array<string,mixed>>
     * }
     */
    public function stageOne(MarketingAnalyticsFilter $filter): array
    {
        $summary = $this->summary($filter);

        return [
            'summary' => $summary,
            'origins' => $this->origins($filter, $summary['unique_guests']),
            'loyal_guests' => $this->loyalGuests($filter),
        ];
    }

    /** @return array<string,mixed> */
    public function dashboard(MarketingAnalyticsFilter $filter): array
    {
        $isMonthly = PropertyPricing::forProperty($filter->propertyId())['pricing_mode'] === 'room_monthly';

        $data = $this->stageOne($filter) + [
            'performance' => $this->performance($filter),
        ];

        if ($isMonthly) {
            $data += [
                'vacant_days' => $this->vacantDays($filter),
                'tenant_turnover' => $this->tenantTurnover($filter),
                'committed_rent' => $this->committedRent($filter),
                'lease_expirations' => $this->leaseExpirations($filter),
            ];
        } else {
            $data += [
                'service_revenue' => $this->serviceRevenue($filter),
                'cancellations' => $this->cancellations($filter),
                'booking_window' => $this->bookingWindow($filter),
                'occupancy' => $this->occupancy($filter),
                'annual_summary' => $this->annualSummary($filter),
                'forecast' => $this->forecast($filter),
            ];
        }

        return $data;
    }

    /** @return array{services:array<string,float>,total:float,payment_count:int} */
    private function serviceRevenue(MarketingAnalyticsFilter $filter): array
    {
        $services = array_fill_keys(['Breakfast', 'Extra Bed', 'Laundry', 'Massage'], 0.0);
        $payments = $this->queries->payments($filter)
            ->select('payments.reservation_id, payments.amount, reservations.total_amount')
            ->get()
            ->getResultArray();
        $reservationIds = array_values(array_unique(array_map('intval', array_column($payments, 'reservation_id'))));
        if ($reservationIds === []) {
            return ['services' => $services, 'total' => 0.0, 'payment_count' => 0];
        }

        $invoiceTotals = [];
        $serviceAmounts = [];
        foreach ($this->db->table('invoice_items')
            ->select('reservation_id, item_name, amount')
            ->whereIn('reservation_id', $reservationIds)
            ->get()
            ->getResultArray() as $item) {
            $reservationId = (int) $item['reservation_id'];
            $amount = (float) $item['amount'];
            $invoiceTotals[$reservationId] = ($invoiceTotals[$reservationId] ?? 0.0) + $amount;
            $name = trim((string) $item['item_name']);
            if (array_key_exists($name, $services)) {
                $serviceAmounts[$reservationId][$name] = ($serviceAmounts[$reservationId][$name] ?? 0.0) + $amount;
            }
        }

        foreach ($payments as $payment) {
            $reservationId = (int) $payment['reservation_id'];
            $invoiceTotal = (float) ($invoiceTotals[$reservationId] ?? $payment['total_amount'] ?? 0);
            if ($invoiceTotal <= 0) {
                continue;
            }
            foreach ($serviceAmounts[$reservationId] ?? [] as $name => $amount) {
                $services[$name] += (float) $payment['amount'] * ($amount / $invoiceTotal);
            }
        }

        return ['services' => $services, 'total' => array_sum($services), 'payment_count' => count($payments)];
    }

    /** @return array{summary:array{bookings:int,rooms:int,cancelled_amount:float,refund_pending:float},rows:list<array<string,mixed>>} */
    private function cancellations(MarketingAnalyticsFilter $filter): array
    {
        $refunds = $this->db->table('payments')
            ->select('reservation_id, SUM(amount) AS refund_pending', false)
            ->where('status', 'Refund_Pending')
            ->groupBy('reservation_id')
            ->getCompiledSelect();
        $builder = $this->db->table('reservations')
            ->select('reservations.id, reservations.order_id, reservations.booking_group_id, reservations.cancelled_at, reservations.total_amount, guests.full_name AS guest_name, rooms.room_number, COALESCE(refunds.refund_pending, 0) AS refund_pending', false)
            ->join('guests', 'guests.id = reservations.guest_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->join("({$refunds}) refunds", 'refunds.reservation_id = reservations.id', 'left', false)
            ->where('reservations.status', 'Cancelled')
            ->where('reservations.cancelled_at >=', $filter->startDate()->format('Y-m-d'))
            ->where('reservations.cancelled_at <', $filter->endDate()->format('Y-m-d'));
        $this->queries->applyGuesthouses($builder, $filter);
        $rows = $builder->orderBy('reservations.cancelled_at', 'DESC')->orderBy('reservations.id', 'DESC')->get()->getResultArray();
        $bookingKeys = [];
        $cancelledAmount = 0.0;
        $refundPending = 0.0;
        foreach ($rows as $row) {
            $bookingKeys[(string) ($row['booking_group_id'] ?: $row['order_id'])] = true;
            $cancelledAmount += (float) $row['total_amount'];
            $refundPending += (float) $row['refund_pending'];
        }

        return [
            'summary' => ['bookings' => count($bookingKeys), 'rooms' => count($rows), 'cancelled_amount' => $cancelledAmount, 'refund_pending' => $refundPending],
            'rows' => array_slice($rows, 0, 20),
        ];
    }

    /** @return array{summary:array{bookings:int,average_days:float,median_days:float},buckets:list<array{label:string,value:int}>} */
    private function bookingWindow(MarketingAnalyticsFilter $filter): array
    {
        $rows = $this->queries->reservations($filter)
            ->select(
                "COALESCE(NULLIF(reservations.booking_group_id, ''), reservations.order_id) AS booking_key, MIN(reservations.check_in_date) AS check_in_date, MIN(reservations.created_at) AS created_at",
                false,
            )
            ->groupBy("COALESCE(NULLIF(reservations.booking_group_id, ''), reservations.order_id)", false)
            ->get()
            ->getResultArray();
        $bucketLabels = ['H-0–1' => 0, 'H-2–3' => 0, 'H-4–7' => 0, 'H-8–14' => 0, 'H-15–30' => 0, '> H-30' => 0];
        $days = [];
        foreach ($rows as $row) {
            if (empty($row['created_at']) || empty($row['check_in_date'])) {
                continue;
            }
            $window = max(0, (int) (new \DateTimeImmutable((string) $row['created_at']))->diff(new \DateTimeImmutable((string) $row['check_in_date']))->format('%r%a'));
            $days[] = $window;
            $bucket = $window <= 1 ? 'H-0–1' : ($window <= 3 ? 'H-2–3' : ($window <= 7 ? 'H-4–7' : ($window <= 14 ? 'H-8–14' : ($window <= 30 ? 'H-15–30' : '> H-30'))));
            $bucketLabels[$bucket]++;
        }
        sort($days);
        $count = count($days);
        $median = $count === 0 ? 0.0 : ($count % 2 === 1 ? $days[intdiv($count, 2)] : ($days[($count / 2) - 1] + $days[$count / 2]) / 2);

        return [
            'summary' => ['bookings' => $count, 'average_days' => $count === 0 ? 0.0 : array_sum($days) / $count, 'median_days' => $median],
            'buckets' => array_map(static fn (string $label, int $value): array => ['label' => $label, 'value' => $value], array_keys($bucketLabels), $bucketLabels),
        ];
    }

    /** @return array{enabled:bool,complete_months:int,required_months:int,horizon:int,quality:string,cutoff:string,message:string,history:list<array<string,mixed>>,points:list<array<string,mixed>>} */
    private function forecast(MarketingAnalyticsFilter $filter): array
    {
        $todayMonth = new \DateTimeImmutable('first day of this month');
        $cutoff = min($filter->endDate(), $todayMonth);
        $reservations = $this->db->table('reservations')
            ->select('reservations.check_in_date, reservations.check_out_date')
            ->join('rooms', 'rooms.id = reservations.room_id');
        $this->queries->applyValidReservations($reservations);
        $this->queries->applyGuesthouses($reservations, $filter);
        $reservations = array_values(array_filter(
            $reservations->get()->getResultArray(),
            static fn (array $row): bool => (string) $row['check_in_date'] < $cutoff->format('Y-m-d'),
        ));

        $history = [];
        if ($reservations !== []) {
            $firstDate = min(array_column($reservations, 'check_in_date'));
            $month = new \DateTimeImmutable(substr((string) $firstDate, 0, 7) . '-01');
            while ($month < $cutoff) {
                $end = $month->modify('+1 month');
                $sold = 0;
                foreach ($reservations as $reservation) {
                    $sold += marketing_room_nights_in_period(
                        (string) $reservation['check_in_date'],
                        (string) $reservation['check_out_date'],
                        $month->format('Y-m-d'),
                        $end->format('Y-m-d'),
                    );
                }
                $history[] = ['month' => $month->format('Y-m'), 'label' => $this->monthLabel($month), 'value' => $sold];
                $month = $end;
            }
        }

        $completeMonths = count($history);
        $base = [
            'enabled' => false,
            'complete_months' => $completeMonths,
            'required_months' => HoltWintersForecaster::MINIMUM_POINTS,
            'horizon' => 6,
            'quality' => 'Belum tersedia',
            'cutoff' => $cutoff->format('Y-m-d'),
            'message' => 'Histori belum cukup untuk forecasting. Dibutuhkan minimal 12 bulan lengkap; tersedia ' . $completeMonths . ' bulan.',
            'history' => array_slice($history, -12),
            'points' => [],
        ];
        if ($completeMonths < HoltWintersForecaster::MINIMUM_POINTS) {
            return $base;
        }

        $rawForecast = (new HoltWintersForecaster())->forecast(array_column($history, 'value'), 6);
        $roomCount = $this->roomCount($filter);
        $points = [];
        $month = $cutoff;
        foreach ($rawForecast as $point) {
            $capacity = $roomCount * (int) $month->diff($month->modify('+1 month'))->days;
            $points[] = [
                'month' => $month->format('Y-m'),
                'label' => $this->monthLabel($month),
                'value' => round(min($capacity, $point['value']), 2),
                'lower' => round(min($capacity, $point['lower']), 2),
                'upper' => round(min($capacity, $point['upper']), 2),
            ];
            $month = $month->modify('+1 month');
        }

        return array_replace($base, [
            'enabled' => true,
            'quality' => $completeMonths >= 24 ? 'Baik' : 'Cukup',
            'message' => 'Forecast Holt-Winters untuk enam bulan ke depan.',
            'points' => $points,
        ]);
    }

    private function monthLabel(\DateTimeImmutable $month): string
    {
        $names = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        return $names[(int) $month->format('n')] . ' ' . $month->format('Y');
    }

    /**
     * Ringkasan selalu memakai tahun kalender penuh agar perbandingan YoY setara.
     * Filter bulan sengaja tidak dipakai pada bagian ini; filter GH tetap berlaku.
     *
     * @return array{points:list<array{year:int,room_nights_sold:int,room_nights_available:int,occupancy:float,yoy_room_nights:?float,yoy_occupancy_points:?float}>,selected:array<string,mixed>}
     */
    private function annualSummary(MarketingAnalyticsFilter $filter): array
    {
        $roomCount = $this->roomCount($filter);
        $reservations = $this->db->table('reservations')
            ->select('reservations.check_in_date, reservations.check_out_date')
            ->join('rooms', 'rooms.id = reservations.room_id');
        $this->queries->applyValidReservations($reservations);
        $this->queries->applyGuesthouses($reservations, $filter);
        $reservations = $reservations->get()->getResultArray();

        $years = [$filter->year()];
        foreach ($reservations as $reservation) {
            $checkInYear = (int) substr((string) $reservation['check_in_date'], 0, 4);
            $checkOutYear = (int) substr((string) $reservation['check_out_date'], 0, 4);
            foreach (range($checkInYear, min($checkOutYear, $filter->year())) as $year) {
                if ($year >= 2000 && $year <= $filter->year()) {
                    $years[] = $year;
                }
            }
        }
        $years = array_values(array_unique($years));
        sort($years);

        $points = [];
        $previous = null;
        foreach ($years as $year) {
            $start = new \DateTimeImmutable($year . '-01-01');
            $end = $start->modify('+1 year');
            $sold = 0;
            foreach ($reservations as $reservation) {
                $sold += marketing_room_nights_in_period(
                    (string) $reservation['check_in_date'],
                    (string) $reservation['check_out_date'],
                    $start->format('Y-m-d'),
                    $end->format('Y-m-d'),
                );
            }
            $available = $roomCount * (int) $start->diff($end)->days;
            $occupancy = $available === 0 ? 0.0 : round(($sold / $available) * 100, 2);
            $points[] = [
                'year' => $year,
                'room_nights_sold' => $sold,
                'room_nights_available' => $available,
                'occupancy' => $occupancy,
                'yoy_room_nights' => $previous === null || $previous['room_nights_sold'] === 0
                    ? null
                    : round((($sold - $previous['room_nights_sold']) / $previous['room_nights_sold']) * 100, 2),
                'yoy_occupancy_points' => $previous === null ? null : round($occupancy - $previous['occupancy'], 2),
            ];
            $previous = end($points);
        }

        $selected = end($points) ?: [
            'year' => $filter->year(),
            'room_nights_sold' => 0,
            'room_nights_available' => 0,
            'occupancy' => 0.0,
            'yoy_room_nights' => null,
            'yoy_occupancy_points' => null,
        ];

        return ['points' => $points, 'selected' => $selected];
    }

    /**
     * @return array{points:list<array{label:string,start_date:string,end_date_exclusive:string,room_nights_sold:int,room_nights_available:int,occupancy:float}>,summary:array{room_nights_sold:int,room_nights_available:int,occupancy:float,room_count:int,period_days:int}}
     */
    private function occupancy(MarketingAnalyticsFilter $filter): array
    {
        $roomCount = $this->roomCount($filter);
        $points = $this->occupancyPeriods($filter);
        $reservations = $this->queries->reservations($filter, true)
            ->select('reservations.check_in_date, reservations.check_out_date')
            ->get()
            ->getResultArray();
        $totalSold = 0;
        $totalAvailable = 0;
        $totalDays = 0;

        foreach ($points as &$point) {
            $sold = 0;
            foreach ($reservations as $reservation) {
                $sold += marketing_room_nights_in_period(
                    (string) $reservation['check_in_date'],
                    (string) $reservation['check_out_date'],
                    $point['start_date'],
                    $point['end_date_exclusive'],
                );
            }
            $available = $roomCount * (int) $point['days'];
            $point['room_nights_sold'] = $sold;
            $point['room_nights_available'] = $available;
            $point['occupancy'] = $available === 0 ? 0.0 : round(($sold / $available) * 100, 2);
            $totalDays += (int) $point['days'];
            unset($point['days']);
            $totalSold += $sold;
            $totalAvailable += $available;
        }
        unset($point);

        return [
            'points' => $points,
            'summary' => [
                'room_nights_sold' => $totalSold,
                'room_nights_available' => $totalAvailable,
                'occupancy' => $totalAvailable === 0 ? 0.0 : round(($totalSold / $totalAvailable) * 100, 2),
                'room_count' => $roomCount,
                'period_days' => $totalDays,
            ],
        ];
    }

    /** @return list<array{label:string,start_date:string,end_date_exclusive:string,days:int}> */
    private function occupancyPeriods(MarketingAnalyticsFilter $filter): array
    {
        $monthNames = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $months = $filter->month() === null ? range(1, 12) : [$filter->month()];
        $periods = [];

        foreach ($months as $month) {
            $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $filter->year(), $month));
            $end = $start->modify('+1 month');
            $periods[] = [
                'label' => $monthNames[$month] . ' ' . $filter->year(),
                'start_date' => $start->format('Y-m-d'),
                'end_date_exclusive' => $end->format('Y-m-d'),
                'days' => (int) $start->diff($end)->days,
            ];
        }

        return $periods;
    }

    private function roomCount(MarketingAnalyticsFilter $filter): int
    {
        $builder = $this->db->table('rooms')
            ->where('property_id', $filter->propertyId());
        if ($filter->propertyId() === 1 && ! $filter->includesAllGuesthouses()) {
            $builder->whereIn('floor', $filter->guesthouses());
        }

        return $builder->countAllResults();
    }

    /**
     * Expense yang memiliki beberapa GH dibagi rata ke setiap GH yang ditautkan.
     * Expense tanpa GH tidak dipaksakan ke GH mana pun dan dilaporkan terpisah.
     *
     * @return array{rows:list<array{guesthouse:int,revenue:float,cost:float,profit:float,margin:?float}>,totals:array{revenue:float,cost:float,profit:float,margin:?float},unallocated_expense:float}
     */
    private function performance(MarketingAnalyticsFilter $filter): array
    {
        if ($filter->propertyId() !== 1) {
            $paid = $this->queries->payments($filter)
                ->selectSum('payments.amount', 'total')
                ->get()->getRowArray();
            $expenses = $this->queries->expenses($filter)
                ->selectSum('expenses.amount', 'total')
                ->get()->getRowArray();
            $revenue = (float) ($paid['total'] ?? 0);
            $cost = (float) ($expenses['total'] ?? 0);
            $profit = $revenue - $cost;
            $margin = $revenue === 0.0 ? null : ($profit / $revenue) * 100;

            return [
                'rows' => [['guesthouse' => 0, 'revenue' => $revenue, 'cost' => $cost, 'profit' => $profit, 'margin' => $margin]],
                'totals' => ['revenue' => $revenue, 'cost' => $cost, 'profit' => $profit, 'margin' => $margin],
                'unallocated_expense' => 0.0,
            ];
        }
        $guesthouses = $filter->includesAllGuesthouses()
            ? $this->allGuesthouses($filter->propertyId())
            : $filter->guesthouses();
        $revenue = array_fill_keys($guesthouses, 0.0);
        $cost = array_fill_keys($guesthouses, 0.0);

        foreach ($this->queries->payments($filter)
            ->select('rooms.floor AS guesthouse, SUM(payments.amount) AS revenue', false)
            ->groupBy('rooms.floor')
            ->get()
            ->getResultArray() as $row) {
            $guesthouse = (int) $row['guesthouse'];
            if (array_key_exists($guesthouse, $revenue)) {
                $revenue[$guesthouse] = (float) $row['revenue'];
            }
        }

        $expenses = $this->db->table('expenses')
            ->select('id, amount, guesthouse')
            ->where('property_id', $filter->propertyId())
            ->where('status !=', 'Rejected');
        $this->queries->applyPeriod($expenses, $filter, 'date_incurred');
        $expenses = $expenses->get()->getResultArray();
        $assignments = $this->expenseAssignments(array_map('intval', array_column($expenses, 'id')));
        $unallocatedExpense = 0.0;

        foreach ($expenses as $expense) {
            $expenseId = (int) $expense['id'];
            $assignedGuesthouses = array_keys($assignments[$expenseId] ?? []);
            if ($assignedGuesthouses === []) {
                $legacyGuesthouse = (int) $expense['guesthouse'];
                if ($legacyGuesthouse >= 1 && $legacyGuesthouse <= 5) {
                    $assignedGuesthouses[] = $legacyGuesthouse;
                }
            }
            if ($assignedGuesthouses === []) {
                $unallocatedExpense += (float) $expense['amount'];
                continue;
            }

            $share = (float) $expense['amount'] / count($assignedGuesthouses);
            foreach ($assignedGuesthouses as $guesthouse) {
                if (array_key_exists($guesthouse, $cost)) {
                    $cost[$guesthouse] += $share;
                }
            }
        }

        $rows = [];
        foreach ($guesthouses as $guesthouse) {
            $guesthouseRevenue = $revenue[$guesthouse];
            $guesthouseCost = $cost[$guesthouse];
            $profit = $guesthouseRevenue - $guesthouseCost;
            $rows[] = [
                'guesthouse' => $guesthouse,
                'revenue' => $guesthouseRevenue,
                'cost' => $guesthouseCost,
                'profit' => $profit,
                'margin' => $guesthouseRevenue === 0.0 ? null : ($profit / $guesthouseRevenue) * 100,
            ];
        }

        $totalRevenue = array_sum($revenue);
        $totalCost = array_sum($cost);
        $totalProfit = $totalRevenue - $totalCost;

        return [
            'rows' => $rows,
            'totals' => [
                'revenue' => $totalRevenue,
                'cost' => $totalCost,
                'profit' => $totalProfit,
                'margin' => $totalRevenue === 0.0 ? null : ($totalProfit / $totalRevenue) * 100,
            ],
            'unallocated_expense' => $unallocatedExpense,
        ];
    }

    /** @param list<int> $expenseIds @return array<int,array<int,true>> */
    private function expenseAssignments(array $expenseIds): array
    {
        if ($expenseIds === []) {
            return [];
        }

        $assignments = [];
        foreach ($this->db->table('expense_guesthouses')
            ->select('expense_id, guesthouse')
            ->whereIn('expense_id', $expenseIds)
            ->get()
            ->getResultArray() as $assignment) {
            $assignments[(int) $assignment['expense_id']][(int) $assignment['guesthouse']] = true;
        }

        return $assignments;
    }

    /** @return list<int> */
    private function allGuesthouses(int $propertyId): array
    {
        return array_map(
            'intval',
            array_column(
                $this->db->table('rooms')->select('floor')->where('property_id', $propertyId)->where('floor >=', 1)->where('floor <=', 5)->groupBy('floor')->orderBy('floor', 'ASC')->get()->getResultArray(),
                'floor',
            ),
        );
    }

    /**
     * @return array{unique_guests:int,unique_bookings:int,reservation_rows:int,unknown_origins:int,unknown_origin_percentage:float}
     */
    private function summary(MarketingAnalyticsFilter $filter): array
    {
        $row = $this->queries->reservations($filter)
            ->select(
                'COUNT(*) AS reservation_rows, '
                . 'COUNT(DISTINCT reservations.guest_id) AS unique_guests, '
                . "COUNT(DISTINCT COALESCE(NULLIF(reservations.booking_group_id, ''), reservations.order_id)) AS unique_bookings, "
                . "COUNT(DISTINCT CASE WHEN TRIM(COALESCE(guests.origin, '')) = '' THEN reservations.guest_id END) AS unknown_origins",
                false,
            )
            ->join('guests', 'guests.id = reservations.guest_id')
            ->get()
            ->getRowArray() ?? [];

        $uniqueGuests = (int) ($row['unique_guests'] ?? 0);
        $unknownOrigins = (int) ($row['unknown_origins'] ?? 0);

        return [
            'unique_guests' => $uniqueGuests,
            'unique_bookings' => (int) ($row['unique_bookings'] ?? 0),
            'reservation_rows' => (int) ($row['reservation_rows'] ?? 0),
            'unknown_origins' => $unknownOrigins,
            'unknown_origin_percentage' => $uniqueGuests === 0
                ? 0.0
                : round(($unknownOrigins / $uniqueGuests) * 100, 2),
        ];
    }

    /** @return list<array{label:string,value:int,percentage:float}> */
    private function origins(MarketingAnalyticsFilter $filter, int $uniqueGuests): array
    {
        $rows = $this->queries->reservations($filter)
            ->select('reservations.guest_id, guests.origin')
            ->join('guests', 'guests.id = reservations.guest_id')
            ->groupBy('reservations.guest_id, guests.origin')
            ->get()
            ->getResultArray();
        $counts = [];

        foreach ($rows as $row) {
            $origin = normalize_guest_origin($row['origin'] ?? null);
            $counts[$origin] = ($counts[$origin] ?? 0) + 1;
        }

        uksort($counts, static function (string $left, string $right) use ($counts): int {
            $countComparison = $counts[$right] <=> $counts[$left];

            return $countComparison !== 0 ? $countComparison : strnatcasecmp($left, $right);
        });

        $origins = [];
        foreach ($counts as $label => $value) {
            $origins[] = [
                'label' => $label,
                'value' => $value,
                'percentage' => $uniqueGuests === 0 ? 0.0 : round(($value / $uniqueGuests) * 100, 2),
            ];
        }

        return $origins;
    }

    /** @return list<array<string, mixed>> */
    private function loyalGuests(MarketingAnalyticsFilter $filter): array
    {
        $perBooking = $this->queries->reservations($filter)
            ->select(
                'reservations.guest_id, '
                . "COALESCE(NULLIF(reservations.booking_group_id, ''), reservations.order_id) AS booking_key, "
                . 'MAX(GREATEST(DATEDIFF(reservations.check_out_date, reservations.check_in_date), 1)) AS nights, '
                . 'SUM(reservations.total_amount) AS nominal',
                false,
            )
            ->groupBy(
                "reservations.guest_id, COALESCE(NULLIF(reservations.booking_group_id, ''), reservations.order_id)",
                false,
            );

        $isMonthly = \App\Libraries\PropertyPricing::forProperty($filter->propertyId())['pricing_mode'] === 'room_monthly';

        $query = $this->db->newQuery()
            ->fromSubquery($perBooking, 'booking_stats')
            ->select(
                'guests.id AS guest_id, guests.full_name, guests.email, guests.phone, guests.origin, '
                . 'COUNT(*) AS booking_count, SUM(booking_stats.nights) AS total_nights, '
                . 'SUM(booking_stats.nominal) AS total_nominal',
                false,
            )
            ->join('guests', 'guests.id = booking_stats.guest_id')
            ->groupBy('guests.id, guests.full_name, guests.email, guests.phone, guests.origin');

        if ($isMonthly) {
            $query->orderBy('total_nights', 'DESC')
                ->orderBy('total_nominal', 'DESC')
                ->orderBy('booking_count', 'DESC');
        } else {
            $query->orderBy('booking_count', 'DESC')
                ->orderBy('total_nights', 'DESC')
                ->orderBy('total_nominal', 'DESC');
        }

        return $query
            ->orderBy('guests.full_name', 'ASC')
            ->limit(10)
            ->get()
            ->getResultArray();
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Kos-specific analytics (room_monthly properties only)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Rata-rata Waktu Kamar Kosong (Vacant Days).
     *
     * For each room, compute the gap (in days) between consecutive tenancies.
     * If a room is currently vacant, the gap from the last check-out to today
     * is included.
     *
     * @return array{average_vacant_days:float,total_gaps:int,rooms:list<array<string,mixed>>}
     */
    private function vacantDays(MarketingAnalyticsFilter $filter): array
    {
        $propertyId = $filter->propertyId();

        // All rooms for this property
        $rooms = $this->db->table('rooms')
            ->select('id, room_number')
            ->where('property_id', $propertyId)
            ->orderBy('room_number', 'ASC')
            ->get()
            ->getResultArray();

        // All valid reservations for property, ordered by room then check-in
        $reservations = $this->db->table('reservations')
            ->select('reservations.room_id, reservations.check_in_date, reservations.check_out_date, reservations.status')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->where('reservations.property_id', $propertyId)
            ->whereIn('reservations.status', ['Confirmed', 'Checked_In', 'Checked_Out'])
            ->orderBy('reservations.room_id', 'ASC')
            ->orderBy('reservations.check_in_date', 'ASC')
            ->get()
            ->getResultArray();

        // Group reservations by room_id
        $byRoom = [];
        foreach ($reservations as $r) {
            $byRoom[(int) $r['room_id']][] = $r;
        }

        $today = new \DateTimeImmutable('today');
        $allGaps = [];
        $roomResults = [];

        foreach ($rooms as $room) {
            $roomId = (int) $room['id'];
            $roomReservations = $byRoom[$roomId] ?? [];
            $gaps = [];
            $lastCheckout = null;
            $currentlyOccupied = false;

            foreach ($roomReservations as $i => $r) {
                $checkIn = new \DateTimeImmutable((string) $r['check_in_date']);
                $checkOut = new \DateTimeImmutable((string) $r['check_out_date']);

                if ($lastCheckout !== null && $checkIn > $lastCheckout) {
                    $gap = (int) $lastCheckout->diff($checkIn)->days;
                    if ($gap > 0) {
                        $gaps[] = $gap;
                    }
                }
                $lastCheckout = $checkOut;

                // Check if currently occupied
                if (in_array($r['status'], ['Confirmed', 'Checked_In'], true) && $checkIn <= $today && $checkOut > $today) {
                    $currentlyOccupied = true;
                }
            }

            // If room is currently vacant and has history, add gap from last checkout to today
            $vacantSinceLastCheckout = null;
            if (!$currentlyOccupied && $lastCheckout !== null && $lastCheckout <= $today) {
                $vacantSinceLastCheckout = (int) $lastCheckout->diff($today)->days;
                if ($vacantSinceLastCheckout > 0) {
                    $gaps[] = $vacantSinceLastCheckout;
                }
            }

            $avgGap = $gaps !== [] ? round(array_sum($gaps) / count($gaps), 1) : 0.0;
            $allGaps = array_merge($allGaps, $gaps);

            $roomResults[] = [
                'room_number' => $room['room_number'],
                'currently_occupied' => $currentlyOccupied,
                'last_checkout' => $lastCheckout?->format('Y-m-d'),
                'vacant_days_current' => $vacantSinceLastCheckout,
                'average_gap' => $avgGap,
                'total_tenancies' => count($roomReservations),
            ];
        }

        $overallAvg = $allGaps !== [] ? round(array_sum($allGaps) / count($allGaps), 1) : 0.0;

        return [
            'average_vacant_days' => $overallAvg,
            'total_gaps' => count($allGaps),
            'rooms' => $roomResults,
        ];
    }

    /**
     * Turnover Penghuni: Masuk vs Keluar per bulan.
     *
     * "Masuk" = reservasi yang check-in date falls in each month.
     * "Keluar" = reservasi yang check-out date falls in each month.
     *
     * @return array{months:list<array{label:string,month:string,in:int,out:int,net:int}>,summary:array{total_in:int,total_out:int,net:int}}
     */
    private function tenantTurnover(MarketingAnalyticsFilter $filter): array
    {
        $propertyId = $filter->propertyId();
        $monthNames = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $months = $filter->month() === null ? range(1, 12) : [$filter->month()];

        $result = [];
        $totalIn = 0;
        $totalOut = 0;

        foreach ($months as $m) {
            $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $filter->year(), $m));
            $end = $start->modify('+1 month');
            $startStr = $start->format('Y-m-d');
            $endStr = $end->format('Y-m-d');

            // Count check-ins in this month
            $inCount = (int) $this->db->table('reservations')
                ->where('property_id', $propertyId)
                ->whereIn('status', ['Confirmed', 'Checked_In', 'Checked_Out'])
                ->where('check_in_date >=', $startStr)
                ->where('check_in_date <', $endStr)
                ->countAllResults();

            // Count check-outs in this month
            $outCount = (int) $this->db->table('reservations')
                ->where('property_id', $propertyId)
                ->whereIn('status', ['Checked_Out'])
                ->where('check_out_date >=', $startStr)
                ->where('check_out_date <', $endStr)
                ->countAllResults();

            $result[] = [
                'label' => $monthNames[$m] . ' ' . $filter->year(),
                'month' => $start->format('Y-m'),
                'in' => $inCount,
                'out' => $outCount,
                'net' => $inCount - $outCount,
            ];
            $totalIn += $inCount;
            $totalOut += $outCount;
        }

        return [
            'months' => $result,
            'summary' => ['total_in' => $totalIn, 'total_out' => $totalOut, 'net' => $totalIn - $totalOut],
        ];
    }

    /**
     * Proyeksi Pendapatan Sewa Bulan Depan (Committed Rent).
     *
     * Finds all active reservations (Confirmed or Checked_In) whose
     * check_out_date extends beyond the start of next month, meaning
     * the tenant is still renting next month.
     *
     * @return array{next_month_label:string,committed_rooms:int,total_rooms:int,committed_rent:float,occupancy_rate:float,rooms:list<array<string,mixed>>}
     */
    private function committedRent(MarketingAnalyticsFilter $filter): array
    {
        $propertyId = $filter->propertyId();
        $today = new \DateTimeImmutable('today');
        $nextMonthStart = $today->modify('first day of next month');
        $nextMonthEnd = $nextMonthStart->modify('+1 month');
        $monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $nextMonthLabel = $monthNames[(int) $nextMonthStart->format('n')] . ' ' . $nextMonthStart->format('Y');

        $totalRooms = $this->roomCount($filter);

        // Active reservations that extend into next month
        $activeReservations = $this->db->table('reservations')
            ->select('reservations.id, reservations.total_amount, reservations.check_in_date, reservations.check_out_date, guests.full_name, rooms.room_number, rooms.price_per_night')
            ->join('guests', 'guests.id = reservations.guest_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->where('reservations.property_id', $propertyId)
            ->whereIn('reservations.status', ['Confirmed', 'Checked_In'])
            ->where('reservations.check_in_date <', $nextMonthEnd->format('Y-m-d'))
            ->where('reservations.check_out_date >', $nextMonthStart->format('Y-m-d'))
            ->orderBy('rooms.room_number', 'ASC')
            ->get()
            ->getResultArray();

        // Calculate monthly rent per room
        $committedRent = 0.0;
        $roomDetails = [];
        $pricing = PropertyPricing::forProperty($propertyId);
        $baseRate = $pricing['base_rate'];

        foreach ($activeReservations as $r) {
            $roomPrice = (float) ($r['price_per_night'] ?? 0);
            $monthlyRate = $roomPrice > 0 ? $roomPrice : $baseRate;
            $committedRent += $monthlyRate;

            $roomDetails[] = [
                'room_number' => $r['room_number'],
                'guest_name' => $r['full_name'],
                'check_out_date' => $r['check_out_date'],
                'monthly_rate' => $monthlyRate,
            ];
        }

        $committedRooms = count($activeReservations);
        $occupancyRate = $totalRooms > 0 ? round(($committedRooms / $totalRooms) * 100, 1) : 0.0;

        return [
            'next_month_label' => $nextMonthLabel,
            'committed_rooms' => $committedRooms,
            'total_rooms' => $totalRooms,
            'committed_rent' => $committedRent,
            'occupancy_rate' => $occupancyRate,
            'rooms' => $roomDetails,
        ];
    }

    /**
     * Radar Jatuh Tempo / Masa Sewa Akan Berakhir.
     *
     * Lists active reservations whose check_out_date falls within the
     * next 7 or 30 days from today.
     *
     * @return array{expiring_7:list<array<string,mixed>>,expiring_30:list<array<string,mixed>>,count_7:int,count_30:int}
     */
    private function leaseExpirations(MarketingAnalyticsFilter $filter): array
    {
        $propertyId = $filter->propertyId();
        $today = new \DateTimeImmutable('today');
        $in7 = $today->modify('+7 days');
        $in30 = $today->modify('+30 days');

        $rows = $this->db->table('reservations')
            ->select('reservations.check_in_date, reservations.check_out_date, reservations.total_amount, guests.full_name, guests.phone, guests.email, rooms.room_number')
            ->join('guests', 'guests.id = reservations.guest_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->where('reservations.property_id', $propertyId)
            ->whereIn('reservations.status', ['Confirmed', 'Checked_In'])
            ->where('reservations.check_out_date >', $today->format('Y-m-d'))
            ->where('reservations.check_out_date <=', $in30->format('Y-m-d'))
            ->orderBy('reservations.check_out_date', 'ASC')
            ->get()
            ->getResultArray();

        $expiring7 = [];
        $expiring30 = [];

        foreach ($rows as $row) {
            $checkOut = new \DateTimeImmutable((string) $row['check_out_date']);
            $daysLeft = (int) $today->diff($checkOut)->days;

            // Calculate rental duration in months
            $checkIn = new \DateTimeImmutable((string) $row['check_in_date']);
            $wholeMonths = PropertyPricing::wholeMonths($checkIn->format('Y-m-d'), $checkOut->format('Y-m-d'));
            if ($wholeMonths === null) {
                $wholeMonths = max(1, (int) round($checkIn->diff($checkOut)->days / 30));
            }

            // Build WA link
            $rawPhone = trim((string) ($row['phone'] ?? ''));
            $digits = preg_replace('/\D+/', '', $rawPhone);
            $waPhone = '';
            if ($digits !== '' && $digits !== '0') {
                if (str_starts_with($digits, '62')) {
                    $waPhone = $digits;
                } elseif (str_starts_with($digits, '0')) {
                    $waPhone = '62' . substr($digits, 1);
                } elseif (str_starts_with($digits, '8')) {
                    $waPhone = '62' . $digits;
                } else {
                    $waPhone = $digits;
                }
            }

            $entry = [
                'guest_name' => $row['full_name'],
                'room_number' => $row['room_number'],
                'check_in_date' => $row['check_in_date'],
                'check_out_date' => $row['check_out_date'],
                'days_left' => $daysLeft,
                'duration_months' => $wholeMonths,
                'phone' => $rawPhone,
                'wa_phone' => $waPhone,
            ];

            if ($checkOut <= $in7) {
                $expiring7[] = $entry;
            }
            $expiring30[] = $entry;
        }

        return [
            'expiring_7' => $expiring7,
            'expiring_30' => $expiring30,
            'count_7' => count($expiring7),
            'count_30' => count($expiring30),
        ];
    }
}
