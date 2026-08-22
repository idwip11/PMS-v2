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
        return $this->stageOne($filter) + [
            'performance' => $this->performance($filter),
            'service_revenue' => $this->serviceRevenue($filter),
            'cancellations' => $this->cancellations($filter),
            'booking_window' => $this->bookingWindow($filter),
            'occupancy' => $this->occupancy($filter),
            'annual_summary' => $this->annualSummary($filter),
            'forecast' => $this->forecast($filter),
        ];
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
            ->where('floor >=', 1)
            ->where('floor <=', 5);
        $this->queries->applyGuesthouses($builder, $filter, 'floor');

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
        $guesthouses = $filter->includesAllGuesthouses()
            ? $this->allGuesthouses()
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
    private function allGuesthouses(): array
    {
        return array_map(
            'intval',
            array_column(
                $this->db->table('rooms')->select('floor')->where('floor >=', 1)->where('floor <=', 5)->groupBy('floor')->orderBy('floor', 'ASC')->get()->getResultArray(),
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
                . 'MAX(GREATEST(DATEDIFF(reservations.check_out_date, reservations.check_in_date), 0)) AS nights, '
                . 'SUM(reservations.total_amount) AS nominal',
                false,
            )
            ->groupBy(
                "reservations.guest_id, COALESCE(NULLIF(reservations.booking_group_id, ''), reservations.order_id)",
                false,
            );

        return $this->db->newQuery()
            ->fromSubquery($perBooking, 'booking_stats')
            ->select(
                'guests.id AS guest_id, guests.full_name, guests.email, guests.phone, guests.origin, '
                . 'COUNT(*) AS booking_count, SUM(booking_stats.nights) AS total_nights, '
                . 'SUM(booking_stats.nominal) AS total_nominal',
                false,
            )
            ->join('guests', 'guests.id = booking_stats.guest_id')
            ->groupBy('guests.id, guests.full_name, guests.email, guests.phone, guests.origin')
            ->orderBy('booking_count', 'DESC')
            ->orderBy('total_nights', 'DESC')
            ->orderBy('total_nominal', 'DESC')
            ->orderBy('guests.full_name', 'ASC')
            ->limit(10)
            ->get()
            ->getResultArray();
    }
}
