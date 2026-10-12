<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/** Owner-eligible Paid revenue; holding reuses composition with confirmed cash instead. */
final class MitraOwnerFinanceService
{
    public function __construct(private BaseConnection $db)
    {
    }

    /** Exact floor and remainder of a*b/divisor without overflowing an integer product. */
    private static function ratio(int $a, int $b, int $divisor): array
    {
        $quotient = $remainder = 0;
        foreach (str_split(decbin($a)) as $bit) {
            $remainder = 2 * $remainder + ($bit === '1' ? $b : 0);
            $quotient = 2 * $quotient + intdiv($remainder, $divisor);
            $remainder %= $divisor;
        }
        return [$quotient, $remainder];
    }

    /** Largest remainder allocation; stable key order breaks ties and conserves every cent. */
    public static function allocate(int $amount, array $weights): array
    {
        if ($amount < 0 || min($weights) < 0) {
            throw new InvalidArgumentException('Dasar alokasi tidak boleh negatif.');
        }
        $total = array_sum($weights);
        $parts = array_fill_keys(array_keys($weights), 0);
        if ($total === 0 || $amount === 0) {
            return $parts;
        }
        $remainders = [];
        foreach ($weights as $key => $weight) {
            [$parts[$key], $remainders[$key]] = self::ratio($amount, $weight, $total);
        }
        arsort($remainders, SORT_NUMERIC);
        $left = $amount - array_sum($parts);
        foreach (array_keys($remainders) as $key) {
            if ($left-- <= 0) {
                break;
            }
            $parts[$key]++;
        }
        return $parts;
    }

    public static function split(int $baseCents, string $percentage): array
    {
        FinanceOwnerSetupService::validate('Owner', $percentage);
        $bps = CashAmount::cents($percentage);
        [$owner, $remainder] = self::ratio(max(0, $baseCents), $bps, 10000);
        if ($remainder >= 5000) {
            $owner++;
        }
        return ['owner' => $owner, 'manager' => max(0, $baseCents) - $owner];
    }

    public function compositions(array $reservationIds): array
    {
        $compositions = [];
        if ($reservationIds === []) {
            return $compositions;
        }
        $items = $this->db->table('invoice_items i')->select('i.reservation_id, i.item_name, i.amount, r.property_id')
            ->join('reservations r', 'r.id = i.reservation_id')->whereIn('i.reservation_id', $reservationIds)->whereIn('r.property_id', [2, 3])->get()->getResultArray();
        foreach ($items as $item) {
            $id = (int) $item['reservation_id'];
            $compositions[$id] ??= ['rental' => 0, 'extra' => 0, 'charge' => 0, 'direct' => 0];
            $name = trim($item['item_name']);
            $key = match (true) {
                in_array($name, ['Extra Bed', 'Biaya Extra Bed'], true) || str_starts_with($name, 'Extra Bed') || str_starts_with($name, 'Biaya Extra Bed') => 'extra',
                in_array($name, ['Breakfast', 'Laundry', 'Massage', 'Sewa Kendaraan'], true) => 'direct',
                in_array($name, ['Early Check In', 'Late Checkout'], true) => 'charge',
                default => 'rental',
            };
            $compositions[$id][$key] += CashAmount::cents((string) $item['amount']);
        }
        return $compositions;
    }

    public static function portions(int $amount, ?array $composition): array
    {
        $weights = $composition === null ? ['rental' => 1, 'extra' => 0, 'charge' => 0, 'direct' => 0]
            : array_map(static fn (int $value): int => max(0, $value), $composition);
        if (array_sum($weights) === 0) {
            $weights['rental'] = 1;
        }
        return self::allocate(max(0, $amount), $weights);
    }

    private function period(string $month): array
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month) !== 1) {
            throw new InvalidArgumentException('Periode laporan tidak valid.');
        }
        $first = new DateTimeImmutable($month . '-01');
        return [$first->format('Y-m-d'), $first->modify('first day of next month')->format('Y-m-d')];
    }

    public function report(FinanceReportScope $scope, string $month, int $page = 1): array
    {
        if (! $scope->isCombined() || ! $scope->allows(FinanceReportScope::OWNER_REPORT)) {
            throw new RuntimeException('Akses laporan owner Mitra ditolak.', 403);
        }
        [$first, $next] = $this->period($month);
        $setup = (new FinanceOwnerSetupService($this->db))->read();
        $payments = $this->db->table('payments p')->select('p.id, p.reservation_id, p.payment_date, p.type, p.amount, p.proof_path, r.property_id, r.order_id, r.booking_group_id, g.full_name, prop.name AS property_name, prop.pricing_mode')
            ->join('reservations r', 'r.id = p.reservation_id')->join('guests g', 'g.id = r.guest_id')
            ->join('properties prop', 'prop.id = r.property_id')->whereIn('r.property_id', $scope->propertyIds)
            ->where('p.status', 'Paid')->where('p.payment_date >=', $first)->where('p.payment_date <', $next)->get()->getResultArray();
        $compositions = $this->compositions(array_values(array_unique(array_column($payments, 'reservation_id'))));
        $categories = ['Penyewaan' => 0, 'Extra Bed' => 0, 'Charge Tamu' => 0, 'Lain-lain' => 0];
        $transactions = [];
        $wholeHomeIncome = [];
        $sourceTotals = [2 => 0, 3 => 0];
        foreach ($payments as $payment) {
            $parts = self::portions(CashAmount::cents((string) $payment['amount']), $compositions[$payment['reservation_id']] ?? null);
            $eligible = $parts['rental'] + $parts['extra'] + $parts['charge'];
            $categories['Penyewaan'] += $parts['rental'];
            $categories['Extra Bed'] += $parts['extra'];
            $categories['Charge Tamu'] += $parts['charge'];
            $propId = (int) $payment['property_id'];
            $sourceTotals[$propId] = ($sourceTotals[$propId] ?? 0) + $eligible;
            if ($eligible > 0) {
                $isWholeHome = ($payment['pricing_mode'] ?? '') === 'whole_home_nightly' || $propId === PropertyContext::HOMESTAY_ID;
                $bookingGroup = trim((string) ($payment['booking_group_id'] ?? ''));

                if ($isWholeHome && $bookingGroup !== '') {
                    $aggKey = $propId . '|' . $bookingGroup . '|' . $payment['payment_date'] . '|' . ($payment['type'] ?? '');
                    if (isset($wholeHomeIncome[$aggKey])) {
                        $prev = $wholeHomeIncome[$aggKey];
                        $aggCents = CashAmount::cents((string) $prev['amount']) + $eligible;
                        $prev['amount'] = (float) ($aggCents / 100);
                        $prev['sortId'] = max($prev['sortId'], (int) $payment['id']);
                        if ($prev['proofUrl'] === null && ! empty($payment['proof_path'])) {
                            $prev['proofUrl'] = site_url('payments/' . $payment['id'] . '/proof');
                        }
                        $wholeHomeIncome[$aggKey] = $prev;
                    } else {
                        $wholeHomeIncome[$aggKey] = [
                            'date' => $payment['payment_date'],
                            'property_id' => $propId,
                            'property_name' => $payment['property_name'],
                            'category' => FinanceReportService::paymentTypeLabel($payment['type']),
                            'description' => $payment['full_name'],
                            'flow' => 'Pemasukan',
                            'amount' => (float) ($eligible / 100),
                            'proofUrl' => empty($payment['proof_path']) ? null : site_url('payments/' . $payment['id'] . '/proof'),
                            'sortId' => (int) $payment['id'],
                        ];
                    }
                } else {
                    $transactions[] = [
                        'date' => $payment['payment_date'],
                        'property_id' => $propId,
                        'property_name' => $payment['property_name'],
                        'category' => FinanceReportService::paymentTypeLabel($payment['type']),
                        'description' => $payment['full_name'],
                        'flow' => 'Pemasukan',
                        'amount' => (float) ($eligible / 100),
                        'proofUrl' => empty($payment['proof_path']) ? null : site_url('payments/' . $payment['id'] . '/proof'),
                        'sortId' => (int) $payment['id'],
                    ];
                }
            }
        }
        foreach ($wholeHomeIncome as $aggTx) {
            $transactions[] = $aggTx;
        }
        // Mitra costs belong to the source property. GH allocations apply only to HomiQ.
        $expenses = $this->db->table('expenses e')->select('e.*, p.name AS property_name')->join('properties p', 'p.id = e.property_id')
            ->whereIn('e.property_id', $scope->propertyIds)->where('e.category !=', 'Fee Kerjasama')->where('e.status !=', 'Rejected')
            ->where('e.date_incurred >=', $first)->where('e.date_incurred <', $next)->get()->getResultArray();
        $expenseCategories = [];
        foreach ($expenses as $expense) {
            $category = trim($expense['category']) ?: 'Lain-lain';
            if ((int) $expense['property_id'] === 3 && $category === 'Kebutuhan Operasional Guesthouse') {
                $category = 'Kebutuhan Operasional Kos';
            }
            $cents = CashAmount::cents((string) $expense['amount']);
            $expenseCategories[$category] = ($expenseCategories[$category] ?? 0) + $cents;
            $transactions[] = ['date' => $expense['date_incurred'], 'property_id' => (int) $expense['property_id'],
                'property_name' => $expense['property_name'], 'category' => $category, 'description' => $expense['description'],
                'flow' => 'Pengeluaran', 'amount' => $cents / 100, 'sortId' => (int) $expense['id'],
                'proofUrl' => empty($expense['receipt_url']) ? null : site_url('ops/expenses/' . $expense['id'] . '/receipt')];
        }
        ksort($expenseCategories, SORT_NATURAL | SORT_FLAG_CASE);
        usort($transactions, static fn (array $a, array $b): int => [$b['date'], $b['sortId'], $b['flow'], $b['property_id']] <=> [$a['date'], $a['sortId'], $a['flow'], $a['property_id']]);
        $income = array_sum($categories);
        $expense = array_sum($expenseCategories);
        $shares = $setup === null ? null : self::split($income, (string) $setup['owner_share_percentage']);
        $totalItems = count($transactions);
        $totalPages = max(1, (int) ceil($totalItems / 10));
        $page = min(max(1, $page), $totalPages);
        return ['month' => $month, 'guesthouse' => 0, 'ownerGuesthouseLocked' => false, 'scope' => $scope,
            'isCombined' => true, 'propertyLabel' => 'Homestay Mitra & Kos Mitra', 'occupancy' => null,
            'transactions' => array_slice($transactions, ($page - 1) * 10, 10), 'allTransactions' => $transactions,
            'pagination' => ['page' => $page, 'perPage' => 10, 'totalItems' => $totalItems, 'totalPages' => $totalPages],
            'summary' => ['incomeCategories' => array_map(static fn ($v) => $v / 100, $categories),
                'expenseCategories' => array_map(static fn ($v) => $v / 100, $expenseCategories),
                'sourceIncome' => array_map(static fn ($v) => $v / 100, $sourceTotals),
                'totalIncome' => $income / 100, 'totalExpense' => $expense / 100, 'netProfit' => ($income - $expense) / 100,
                'shareBase' => $income / 100, 'isMitraProperty' => true, 'setupRequired' => $setup === null,
                'ownerName' => $setup['owner_name'] ?? null,
                'ownerSharePercentage' => $setup === null ? null : (float) $setup['owner_share_percentage'],
                'managerFeePercentage' => $setup === null ? null : 100 - (float) $setup['owner_share_percentage'],
                'investorShare' => $shares === null ? null : $shares['owner'] / 100,
                'managerShare' => $shares === null ? null : $shares['manager'] / 100]];
    }

    /** Company permission is checked independently of property grants/session. */
    public function confirmedHolding(int $user, int $company, string $month): array
    {
        CompanyFinanceAccess::require($user, $company, 'view', $this->db);
        [$first, $next] = $this->period($month);
        $bases = $direct = [2 => 0, 3 => 0];
        $result = ['setup' => null, 'error' => null, 'bases' => $bases, 'direct' => $direct, 'owner' => $bases, 'manager' => $bases];
        try {
            $setup = (new FinanceOwnerSetupService($this->db))->read();
            $memberIds = array_map('intval', array_column($this->db->table('company_properties')->select('property_id')->where('company_id', $company)->whereIn('property_id', [2, 3])->orderBy('property_id')->get()->getResultArray(), 'property_id'));
            if ($memberIds !== [2, 3]) {
                throw new RuntimeException('Holding belum mencakup kedua properti Mitra.', 503);
            }
        } catch (RuntimeException $e) {
            $result['error'] = $e->getMessage();
            return $result;
        }
        $receipts = $this->db->query(
            'SELECT cr.amount, cr.withheld_fee, p.reservation_id, r.property_id
             FROM cash_payment_receipts cr
             JOIN cash_transactions t ON t.id = cr.transaction_id AND t.company_id = cr.company_id
             JOIN payments p ON p.id = cr.payment_id JOIN reservations r ON r.id = p.reservation_id
             WHERE cr.company_id = ? AND r.property_id IN (2, 3) AND t.effective_at >= ? AND t.effective_at < ?
               AND NOT EXISTS (SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id = t.id)',
            [$company, $first . ' 00:00:00', $next . ' 00:00:00'])->getResultArray();
        $compositions = $this->compositions(array_values(array_unique(array_column($receipts, 'reservation_id'))));
        foreach ($receipts as $receipt) {
            $gross = CashAmount::cents((string) $receipt['amount']) + CashAmount::cents((string) $receipt['withheld_fee']);
            $parts = self::portions($gross, $compositions[$receipt['reservation_id']] ?? null);
            $id = (int) $receipt['property_id'];
            $direct[$id] += $parts['direct'];
            $bases[$id] += $parts['rental'] + $parts['extra'] + $parts['charge'];
        }
        $result['setup'] = $setup;
        $result['bases'] = $bases;
        $result['direct'] = $direct;
        if ($setup !== null) {
            $total = self::split(array_sum($bases), (string) $setup['owner_share_percentage']);
            $result['owner'] = self::allocate($total['owner'], $bases);
            foreach ($bases as $id => $base) {
                $result['manager'][$id] = $base - $result['owner'][$id];
            }
        }
        return $result;
    }
}
