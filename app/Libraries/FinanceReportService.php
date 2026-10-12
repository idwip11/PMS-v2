<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use RuntimeException;

/**
 * Service for centralized Finance reporting (Property & Combined Mitra).
 * Never mutates active property or session state.
 */
final class FinanceReportService
{
    public const PER_PAGE = 10;

    /**
     * @return array<string, mixed>
     */
    public static function getPropertyReportData(
        FinanceReportScope $scope,
        string $month,
        int $page = 1,
        ?BaseConnection $db = null,
    ): array {
        if (! $scope->allows(FinanceReportScope::REPORT)) {
            throw new RuntimeException('Akses laporan keuangan umum ditolak.', 403);
        }
        $db ??= db_connect();

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 ? $month : date('Y-m');
        $first = new DateTimeImmutable($month . '-01');
        $last = $first->modify('last day of this month');

        // Fetch properties metadata for the scope
        $propertyRows = $db->table('properties')
            ->select('id, code, name, pricing_mode, base_rate')
            ->whereIn('id', $scope->propertyIds)
            ->get()->getResultArray();
        $propertyMap = [];
        foreach ($propertyRows as $row) {
            $propertyMap[(int) $row['id']] = $row;
        }

        // 1. Fetch Paid Payments within date range
        $payments = $db->table('payments')->select(
            'payments.*, guests.full_name AS guest_name, reservations.check_in_date, reservations.check_out_date, reservations.property_id, '
            . 'reservations.booking_group_id, '
            . 'rooms.room_number, rooms.floor AS guesthouse, '
            . '(SELECT COUNT(*) FROM reservations AS grouped_reservations '
            . 'WHERE grouped_reservations.property_id = reservations.property_id '
            . 'AND COALESCE(NULLIF(grouped_reservations.booking_group_id, \'\'), grouped_reservations.order_id) '
            . '= COALESCE(NULLIF(reservations.booking_group_id, \'\'), reservations.order_id)) AS room_count',
            false
        )
            ->join('reservations', 'reservations.id = payments.reservation_id')
            ->join('guests', 'guests.id = reservations.guest_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->whereIn('reservations.property_id', $scope->propertyIds)
            ->where('payments.status', 'Paid')
            ->where('payments.payment_date >=', $first->format('Y-m-d'))
            ->where('payments.payment_date <=', $last->format('Y-m-d'))
            ->get()->getResultArray();

        // 2. Fetch Non-rejected Expenses within date range
        $expenses = $db->table('expenses')
            ->whereIn('property_id', $scope->propertyIds)
            ->where('status !=', 'Rejected')
            ->where('date_incurred >=', $first->format('Y-m-d'))
            ->where('date_incurred <=', $last->format('Y-m-d'))
            ->get()->getResultArray();

        // 3. Assemble and standardize transaction list
        $transactions = [];
        $wholeHomeAggregated = [];

        foreach ($payments as $payment) {
            $propId = (int) $payment['property_id'];
            $propMeta = $propertyMap[$propId] ?? ['name' => 'Properti ' . $propId, 'pricing_mode' => 'room_nightly'];
            $checkIn = new DateTimeImmutable((string) $payment['check_in_date']);
            $checkOut = new DateTimeImmutable((string) $payment['check_out_date']);
            $nights = max(1, (int) $checkIn->diff($checkOut)->days);
            $isMonthly = ($propMeta['pricing_mode'] ?? '') === 'room_monthly' || $propId === 3;
            $isWholeHome = ($propMeta['pricing_mode'] ?? '') === 'whole_home_nightly' || $propId === PropertyContext::HOMESTAY_ID;

            if ($isMonthly) {
                $months = PropertyPricing::wholeMonths((string) $payment['check_in_date'], (string) $payment['check_out_date'])
                    ?? max(1, (int) round($nights / 30));
                $durationLabel = $months . ' Bulan';
            } else {
                $durationLabel = $nights . ' Malam';
            }

            if ($isWholeHome) {
                $bookingGroupKey = trim((string) ($payment['booking_group_id'] ?? ''));
                if ($bookingGroupKey === '') {
                    $bookingGroupKey = preg_replace('/-\d+$/', '', (string) ($payment['order_id'] ?? '')) ?: (string) $payment['id'];
                }

                $paymentType = self::paymentTypeLabel($payment['type'] ?? null);
                $paymentMethod = $payment['payment_method'] ?? '-';
                $aggKey = $propId . '|' . $bookingGroupKey . '|' . $payment['payment_date'] . '|' . ($payment['type'] ?? '') . '|' . $paymentMethod;

                if (isset($wholeHomeAggregated[$aggKey])) {
                    $prev = $wholeHomeAggregated[$aggKey];
                    $aggCents = CashAmount::cents((string) $prev['amount']) + CashAmount::cents((string) $payment['amount']);
                    $prev['amount'] = (float) ($aggCents / 100);
                    $prev['sortId'] = max($prev['sortId'], (int) $payment['id']);

                    $earliestCheckIn = min($prev['check_in'], $payment['check_in_date']);
                    $latestCheckOut = max($prev['check_out'], $payment['check_out_date']);
                    $prev['check_in'] = $earliestCheckIn;
                    $prev['check_out'] = $latestCheckOut;
                    $combinedNights = max(1, (int) (new DateTimeImmutable($earliestCheckIn))->diff(new DateTimeImmutable($latestCheckOut))->days);
                    $prev['nights'] = $combinedNights;
                    $prev['duration'] = $combinedNights . ' Malam';

                    $wholeHomeAggregated[$aggKey] = $prev;
                } else {
                    $wholeHomeAggregated[$aggKey] = [
                        'date' => $payment['payment_date'],
                        'type' => 'Income',
                        'property_id' => $propId,
                        'property_name' => (string) ($propMeta['name'] ?? ''),
                        'payment_type' => $paymentType,
                        'reference' => $bookingGroupKey,
                        'description' => $payment['guest_name'],
                        'method' => $paymentMethod,
                        'amount' => (float) $payment['amount'],
                        'room' => '1 Rumah',
                        'guesthouse' => (string) ($propMeta['name'] ?? ''),
                        'check_in' => $payment['check_in_date'],
                        'check_out' => $payment['check_out_date'],
                        'nights' => $nights,
                        'duration' => $durationLabel,
                        'room_count' => 3,
                        'sortId' => (int) $payment['id'],
                    ];
                }
            } else {
                $transactions[] = [
                    'date' => $payment['payment_date'],
                    'type' => 'Income',
                    'property_id' => $propId,
                    'property_name' => (string) ($propMeta['name'] ?? ''),
                    'payment_type' => self::paymentTypeLabel($payment['type'] ?? null),
                    'reference' => $payment['order_id'] ?? '-',
                    'description' => $payment['guest_name'],
                    'method' => $payment['payment_method'] ?? '-',
                    'amount' => (float) $payment['amount'],
                    'room' => 'Room ' . $payment['room_number'],
                    'guesthouse' => $propId === PropertyContext::HOMIQ_ID ? 'GH ' . (int) $payment['guesthouse'] : (string) ($propMeta['name'] ?? ''),
                    'check_in' => $payment['check_in_date'],
                    'check_out' => $payment['check_out_date'],
                    'nights' => $nights,
                    'duration' => $durationLabel,
                    'room_count' => max(1, (int) $payment['room_count']),
                    'sortId' => (int) $payment['id'],
                ];
            }
        }

        foreach ($wholeHomeAggregated as $aggregatedRow) {
            $transactions[] = $aggregatedRow;
        }

        foreach ($expenses as $expense) {
            $propId = (int) $expense['property_id'];
            $propMeta = $propertyMap[$propId] ?? ['name' => 'Properti ' . $propId, 'pricing_mode' => 'room_nightly'];
            $isMonthly = ($propMeta['pricing_mode'] ?? '') === 'room_monthly' || $propId === 3;
            $expenseCategory = (string) $expense['category'];
            if ($isMonthly && $expenseCategory === 'Kebutuhan Operasional Guesthouse') {
                $expenseCategory = 'Kebutuhan Operasional Kos';
            }

            $transactions[] = [
                'date' => $expense['date_incurred'],
                'type' => 'Expense',
                'property_id' => $propId,
                'property_name' => (string) ($propMeta['name'] ?? ''),
                'payment_type' => '-',
                'reference' => 'EXP-' . $expense['id'],
                'description' => $expense['description'],
                'method' => $expenseCategory,
                'amount' => (float) $expense['amount'],
                'room' => '',
                'guesthouse' => '',
                'check_in' => '',
                'check_out' => '',
                'nights' => '',
                'duration' => '',
                'room_count' => '',
                'sortId' => (int) $expense['id'],
            ];
        }

        // Sort descending by date
        usort($transactions, static fn ($a, $b) => [$b['date'], $b['sortId'], $b['type'], $b['property_id']]
            <=> [$a['date'], $a['sortId'], $a['type'], $a['property_id']]);

        // 4. Pagination
        $perPage = self::PER_PAGE;
        $totalTransactions = count($transactions);
        $totalPages = max(1, (int) ceil($totalTransactions / $perPage));
        $validPage = max(1, min($page, $totalPages));
        $pagedTransactions = array_slice($transactions, ($validPage - 1) * $perPage, $perPage);

        // 5. Summary calculations
        $revenueCents = array_sum(array_map(static fn ($row) => CashAmount::cents((string) $row['amount']), $payments));
        $expenseCents = array_sum(array_map(static fn ($row) => CashAmount::cents((string) $row['amount']), $expenses));
        $revenue = (float) ($revenueCents / 100);
        $expenseTotal = (float) ($expenseCents / 100);

        // Unpaid balances belong to each reservation. An overpayment/cancelled
        // booking must never reduce a different reservation's receivable.
        $balances = $db->table('reservations r')->select("r.total_amount, COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.reservation_id = r.id AND p.status = 'Paid'), 0) AS paid", false)
            ->whereIn('r.property_id', $scope->propertyIds)->where('r.status !=', 'Cancelled')->get()->getResultArray();
        $outstandingCents = array_sum(array_map(static fn ($r) => max(0, CashAmount::cents((string) $r['total_amount']) - CashAmount::cents((string) $r['paid'])), $balances));

        // 6. Payment method counts
        $isSingleMonthlyKos = count($scope->propertyIds) === 1 && in_array(3, $scope->propertyIds, true);
        $methodCounts = $isSingleMonthlyKos
            ? ['Cash' => 0, 'Transfer' => 0]
            : ['Cash' => 0, 'Transfer' => 0, 'Credit Card' => 0, 'OTA' => 0];

        foreach ($payments as $payment) {
            $method = strtolower(trim((string) ($payment['payment_method'] ?? '')));
            $methodName = match ($method) {
                'cash' => 'Cash',
                'transfer', 'bank transfer' => 'Transfer',
                'credit card', 'creditcard' => 'Credit Card',
                'ota', 'online travel agency' => 'OTA',
                default => null,
            };
            if ($methodName !== null && isset($methodCounts[$methodName])) {
                $methodCounts[$methodName]++;
            }
        }

        $paymentCount = array_sum($methodCounts);
        $paymentMethods = [];
        foreach ($methodCounts as $name => $count) {
            $paymentMethods[] = [
                'name' => $name,
                'count' => $count,
                'percentage' => $paymentCount === 0 ? 0 : (int) round(($count / $paymentCount) * 100),
            ];
        }

        // 7. Cash flow 6-month trend ending at the reporting month
        $cashFlow = ['labels' => [], 'income' => [], 'expenses' => []];
        for ($offset = 5; $offset >= 0; $offset--) {
            $period = $first->modify('-' . $offset . ' months');
            $periodStart = $period->modify('first day of this month')->format('Y-m-d');
            $periodEnd = $period->modify('last day of this month')->format('Y-m-d');

            $incomeRow = $db->table('payments')
                ->selectSum('payments.amount')
                ->join('reservations', 'reservations.id = payments.reservation_id')
                ->whereIn('reservations.property_id', $scope->propertyIds)
                ->where('payments.status', 'Paid')
                ->where('payments.payment_date >=', $periodStart)
                ->where('payments.payment_date <=', $periodEnd)
                ->get()->getRowArray();

            $expenseRow = $db->table('expenses')
                ->selectSum('amount')
                ->whereIn('property_id', $scope->propertyIds)
                ->where('status !=', 'Rejected')
                ->where('date_incurred >=', $periodStart)
                ->where('date_incurred <=', $periodEnd)
                ->get()->getRowArray();

            $cashFlow['labels'][] = $period->format('M Y');
            $cashFlow['income'][] = (float) (CashAmount::cents((string) ($incomeRow['amount'] ?? 0)) / 100);
            $cashFlow['expenses'][] = (float) (CashAmount::cents((string) ($expenseRow['amount'] ?? 0)) / 100);
        }

        return [
            'month' => $month,
            'scope' => $scope,
            'isCombined' => $scope->isCombined(),
            'scopeLabel' => $scope->label,
            'transactions' => $pagedTransactions,
            'allTransactions' => $transactions,
            'pagination' => [
                'page' => $validPage,
                'perPage' => $perPage,
                'totalItems' => $totalTransactions,
                'totalPages' => $totalPages,
            ],
            'cashFlow' => $cashFlow,
            'paymentMethods' => $paymentMethods,
            'summary' => [
                'revenue' => $revenue,
                'expenses' => $expenseTotal,
                'netProfit' => (float) (($revenueCents - $expenseCents) / 100),
                'outstanding' => (float) ($outstandingCents / 100),
            ],
        ];
    }

    public static function paymentTypeLabel(?string $type): string
    {
        return match ($type) {
            'DP' => 'DP',
            'Completed' => 'Completed / Pelunasan',
            'Additional Charges' => 'Tambahan',
            default => $type ?: '-',
        };
    }
}
