<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use InvalidArgumentException;

/**
 * CF-3 Service: Hak perusahaan, kewajiban keuangan, alokasi overhead,
 * dan laporan bulanan manajemen berbasis kas.
 */
final class CompanyEntitlementService
{
    private CashLedgerService $ledger;

    public function __construct(private BaseConnection $db)
    {
        $this->ledger = new CashLedgerService($db);
    }

    private function audit(int $user, int $company, string $action, string $reference, array $details): void
    {
        if (! $this->db->table('company_finance_audit')->insert([
            'company_id' => $company,
            'user_id' => $user,
            'action' => $action,
            'reference' => $reference,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ])) {
            throw new RuntimeException('Pencatatan audit CF-3 gagal.');
        }
    }

    private function text(string $value, int $max): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException('Teks input tidak valid atau melebihi batas ' . $max . ' karakter.');
        }
        return $value;
    }

    private function date(string $value): string
    {
        $val = trim($value);
        if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/D', $val) !== 1) {
            throw new InvalidArgumentException('Format tanggal harus YYYY-MM-DD.');
        }
        return $val;
    }

    private function month(string $value): string
    {
        $val = trim($value);
        if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $val) !== 1) {
            throw new InvalidArgumentException('Format bulan harus YYYY-MM.');
        }
        return $val;
    }

    private function percent(string $value): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Persentase harus berupa angka desimal.');
        }
        $val = (float) $value;
        if ($val < 0.0 || $val > 100.0) {
            throw new InvalidArgumentException('Persentase harus berada di antara 0.00 dan 100.00.');
        }
        return number_format($val, 2, '.', '');
    }

    private function positiveMoney(string $amount): int
    {
        $cents = CashAmount::cents($amount);
        if ($cents <= 0) {
            throw new InvalidArgumentException('Nominal harus bernilai lebih dari 0.00.');
        }
        return $cents;
    }

    private function nonNegativeMoney(string $amount): int
    {
        $cents = CashAmount::cents($amount);
        if ($cents < 0) {
            throw new InvalidArgumentException('Nominal tidak boleh negatif.');
        }
        return $cents;
    }

    // =========================================================================
    // 1. PERJANJIAN PENGELOLAAN (EFFECTIVE-DATED AGREEMENTS)
    // =========================================================================

    public function createAgreement(
        int $user,
        int $company,
        int $property,
        string $agreementType,
        string $calculationBase,
        string $companySharePct,
        string $ownerSharePct,
        string $fixedFeeMonthly,
        string $effectiveFrom,
        ?string $effectiveTo,
        string $termsNote
    ): int {
        CompanyFinanceAccess::require($user, $company, 'approve', $this->db);

        // Verify property is in company scope
        $cp = $this->db->table('company_properties')
            ->where('company_id', $company)
            ->where('property_id', $property)
            ->get()
            ->getRowArray();
        if (! $cp) {
            throw new RuntimeException('Properti di luar cakupan holding.');
        }

        $validTypes = ['revenue_share', 'net_profit_share', 'fixed_fee', 'full_management', 'custom'];
        if (! in_array($agreementType, $validTypes, true)) {
            throw new InvalidArgumentException('Jenis perjanjian tidak valid.');
        }

        $validBases = ['net_revenue', 'gross_revenue', 'room_revenue', 'operating_profit', 'none'];
        if (! in_array($calculationBase, $validBases, true)) {
            throw new InvalidArgumentException('Dasar perhitungan tidak valid.');
        }

        $companyPctStr = $this->percent($companySharePct);
        $ownerPctStr = $this->percent($ownerSharePct);
        if (((float) $companyPctStr + (float) $ownerPctStr) > 100.0) {
            throw new InvalidArgumentException('Jumlah persentase perusahaan dan owner tidak boleh melebihi 100%.');
        }

        $fixedFeeCents = $this->nonNegativeMoney($fixedFeeMonthly);
        $from = $this->date($effectiveFrom);
        $to = $effectiveTo !== null && trim($effectiveTo) !== '' ? $this->date($effectiveTo) : null;

        if ($to !== null && $from > $to) {
            throw new InvalidArgumentException('Tanggal berlaku mulai tidak boleh setelah tanggal berakhir.');
        }

        // Check for overlapping active agreements for the same property
        $existing = $this->db->table('property_finance_agreements')
            ->where('company_id', $company)
            ->where('property_id', $property)
            ->get()
            ->getResultArray();

        foreach ($existing as $ex) {
            $exFrom = $ex['effective_from'];
            $exTo = $ex['effective_to'] ?? '9999-12-31';
            $checkTo = $to ?? '9999-12-31';

            // Overlap condition: max(from1, from2) <= min(to1, to2)
            if (max($from, $exFrom) <= min($checkTo, $exTo)) {
                throw new RuntimeException('Rentang tanggal perjanjian bertabrakan dengan perjanjian ID #' . $ex['id'] . ' (' . $exFrom . ' s/d ' . ($ex['effective_to'] ?? 'seterusnya') . ').');
            }
        }

        $payload = [
            'company_id' => $company,
            'property_id' => $property,
            'agreement_type' => $agreementType,
            'calculation_base' => $calculationBase,
            'company_share_pct' => $companyPctStr,
            'owner_share_pct' => $ownerPctStr,
            'fixed_fee_monthly' => CashAmount::format($fixedFeeCents),
            'effective_from' => $from,
            'effective_to' => $to,
            'terms_note' => trim($termsNote),
            'created_by' => $user,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];

        if (! $this->db->table('property_finance_agreements')->insert($payload)) {
            throw new RuntimeException('Penyimpanan perjanjian gagal.');
        }

        $id = (int) $this->db->insertID();
        $this->audit($user, $company, 'agreement_created', (string) $id, $payload);
        return $id;
    }

    public function resolveAgreement(int $company, int $property, string $date): ?array
    {
        $d = $this->date($date);
        return $this->db->table('property_finance_agreements')
            ->where('company_id', $company)
            ->where('property_id', $property)
            ->where('effective_from <=', $d)
            ->groupStart()
                ->where('effective_to IS NULL', null, false)
                ->orWhere('effective_to >=', $d)
            ->groupEnd()
            ->orderBy('effective_from', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();
    }

    public function getAgreements(int $user, int $company, ?int $property = null): array
    {
        CompanyFinanceAccess::require($user, $company, 'view', $this->db);
        $b = $this->db->table('property_finance_agreements a')
            ->select('a.*, p.name AS property_name, p.code AS property_code, u.username AS creator_username')
            ->join('properties p', 'p.id = a.property_id')
            ->join('users u', 'u.id = a.created_by')
            ->where('a.company_id', $company);

        if ($property !== null) {
            $b->where('a.property_id', $property);
        }

        return $b->orderBy('a.effective_from', 'DESC')->get()->getResultArray();
    }

    // =========================================================================
    // 2. KEWAJIBAN KEUANGAN & PELUNASAN (FINANCIAL OBLIGATIONS & SETTLEMENTS)
    // =========================================================================

    public function createObligation(
        int $user,
        int $company,
        ?int $property,
        string $obligationType,
        string $referenceType,
        string $referenceId,
        ?string $month,
        string $description,
        string $amount,
        string $status = 'estimated',
        ?string $dueDate = null
    ): int {
        $canApprove = CompanyFinanceAccess::allows($user, $company, 'approve', $this->db);
        if (! $canApprove) {
            CompanyFinanceAccess::require($user, $company, 'post', $this->db);
            // Non-approvers can only create estimated obligations
            $status = 'estimated';
        }

        $validTypes = ['owner_payable', 'refund_deposit', 'tax_withholding', 'other'];
        if (! in_array($obligationType, $validTypes, true)) {
            throw new InvalidArgumentException('Jenis kewajiban tidak valid.');
        }

        $validStatuses = ['estimated', 'finalized'];
        if (! in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException('Status kewajiban awal harus estimated atau finalized.');
        }

        if ($property !== null) {
            $cp = $this->db->table('company_properties')
                ->where('company_id', $company)
                ->where('property_id', $property)
                ->get()
                ->getRowArray();
            if (! $cp) {
                throw new RuntimeException('Properti di luar cakupan holding.');
            }
        }

        $cents = $this->positiveMoney($amount);
        $amountStr = CashAmount::format($cents);
        $refType = $this->text($referenceType, 40);
        $refId = $this->text($referenceId, 120);
        $desc = $this->text($description, 500);
        $m = $month !== null && trim($month) !== '' ? $this->month($month) : null;
        $due = $dueDate !== null && trim($dueDate) !== '' ? $this->date($dueDate) : null;

        // Idempotency check on (company_id, reference_type, reference_id)
        $old = $this->db->table('financial_obligations')
            ->where('company_id', $company)
            ->where('reference_type', $refType)
            ->where('reference_id', $refId)
            ->get()
            ->getRowArray();

        if ($old) {
            if (CashAmount::cents($old['amount']) !== $cents || (int) ($old['property_id'] ?? 0) !== (int) ($property ?? 0)) {
                throw new RuntimeException('Kewajiban dengan referensi ini sudah ada dengan nilai berbeda.');
            }
            return (int) $old['id'];
        }

        $payload = [
            'company_id' => $company,
            'property_id' => $property,
            'obligation_type' => $obligationType,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'month' => $m,
            'description' => $desc,
            'amount' => $amountStr,
            'status' => $status,
            'due_date' => $due,
            'created_by' => $user,
            'approved_by' => $status === 'finalized' ? $user : null,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        if (! $this->db->table('financial_obligations')->insert($payload)) {
            throw new RuntimeException('Penyimpanan kewajiban keuangan gagal.');
        }

        $id = (int) $this->db->insertID();
        $this->audit($user, $company, 'obligation_created', (string) $id, $payload);
        return $id;
    }

    public function finalizeObligation(int $user, int $company, int $obligationId): void
    {
        CompanyFinanceAccess::require($user, $company, 'approve', $this->db);

        $row = $this->db->query(
            'SELECT * FROM financial_obligations WHERE company_id = ? AND id = ? FOR UPDATE',
            [$company, $obligationId]
        )->getRowArray();

        if (! $row) {
            throw new RuntimeException('Kewajiban keuangan tidak ditemukan.');
        }

        if ($row['status'] === 'finalized' || $row['status'] === 'settled') {
            return; // idempotent
        }

        if ($row['status'] !== 'estimated') {
            throw new RuntimeException('Kewajiban dengan status ' . $row['status'] . ' tidak dapat difinalisasi.');
        }

        $this->db->table('financial_obligations')
            ->where('id', $obligationId)
            ->update([
                'status' => 'finalized',
                'approved_by' => $user,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

        $this->audit($user, $company, 'obligation_finalized', (string) $obligationId, [
            'previous_status' => $row['status'],
            'amount' => $row['amount'],
        ]);
    }

    public function settleObligation(
        int $user,
        int $company,
        int $obligationId,
        int $account,
        string $amount,
        string $effectiveAt,
        string $key,
        string $note
    ): int {
        $cents = $this->positiveMoney($amount);
        $amountStr = CashAmount::format($cents);
        $key = $this->text($key, 120);
        $note = $this->text($note, 500);

        return $this->ledger->integrate($user, $company, 'post', function ($write) use (
            $user, $company, $obligationId, $account, $cents, $amountStr, $effectiveAt, $key, $note
        ) {
            // Lock obligation row
            $obligation = $this->db->query(
                'SELECT * FROM financial_obligations WHERE company_id = ? AND id = ? FOR UPDATE',
                [$company, $obligationId]
            )->getRowArray();

            if (! $obligation) {
                throw new RuntimeException('Kewajiban keuangan tidak ditemukan.');
            }

            if ($obligation['status'] === 'cancelled') {
                throw new RuntimeException('Kewajiban sudah dibatalkan; tidak dapat dibayar.');
            }

            if ($obligation['status'] === 'estimated') {
                throw new RuntimeException('Kewajiban masih estimasi; wajib difinalisasi oleh Company Approver sebelum dibayar kas.');
            }

            // Calculate already settled sum
            $settledSum = $this->db->query(
                "SELECT COALESCE(SUM(s.amount), 0) AS total
                 FROM obligation_settlements s
                 JOIN cash_transactions t ON t.id = s.transaction_id AND t.company_id = s.company_id
                 WHERE s.company_id = ? AND s.obligation_id = ?
                   AND NOT EXISTS (SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id = t.id)",
                [$company, $obligationId]
            )->getRowArray();

            $alreadySettledCents = CashAmount::cents((string) $settledSum['total']);
            $obligationCents = CashAmount::cents($obligation['amount']);

            if ($alreadySettledCents + $cents > $obligationCents) {
                throw new RuntimeException('Pembayaran melebihi sisa kewajiban. Sisa: ' . CashAmount::format($obligationCents - $alreadySettledCents));
            }

            // Write cash disbursement (Operating cash outflow, source = obligation_settlement)
            // Note: Does NOT write to `expenses`! Owner payout or refund settlement melunasi kewajiban langsung.
            $tx = $write(
                $key,
                'disbursement',
                'operating',
                $effectiveAt,
                $note,
                [['account_id' => $account, 'amount' => CashAmount::format(-$cents)]],
                $obligation['property_id'] !== null ? (int) $obligation['property_id'] : null,
                'obligation_settlement',
                (string) $obligationId
            );

            // Record settlement link
            $this->db->table('obligation_settlements')->insert([
                'company_id' => $company,
                'transaction_id' => $tx,
                'obligation_id' => $obligationId,
                'amount' => $amountStr,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);

            // If fully settled, update status
            if ($alreadySettledCents + $cents === $obligationCents) {
                $this->db->table('financial_obligations')
                    ->where('id', $obligationId)
                    ->update([
                        'status' => 'settled',
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
            }

            $this->audit($user, $company, 'obligation_settled', (string) $obligationId, [
                'transaction_id' => $tx,
                'amount' => $amountStr,
                'account_id' => $account,
                'is_full' => ($alreadySettledCents + $cents === $obligationCents),
            ]);

            return $tx;
        });
    }

    public function cancelObligation(int $user, int $company, int $obligationId, string $reason): void
    {
        CompanyFinanceAccess::require($user, $company, 'approve', $this->db);
        $reason = $this->text($reason, 500);

        $row = $this->db->query(
            'SELECT * FROM financial_obligations WHERE company_id = ? AND id = ? FOR UPDATE',
            [$company, $obligationId]
        )->getRowArray();

        if (! $row) {
            throw new RuntimeException('Kewajiban keuangan tidak ditemukan.');
        }

        $settlementsCount = $this->db->table('obligation_settlements')
            ->where('company_id', $company)
            ->where('obligation_id', $obligationId)
            ->countAllResults();

        if ($settlementsCount > 0) {
            throw new RuntimeException('Kewajiban yang sudah memiliki mutasi pembayaran tidak dapat dibatalkan.');
        }

        $this->db->table('financial_obligations')
            ->where('id', $obligationId)
            ->update([
                'status' => 'cancelled',
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

        $this->audit($user, $company, 'obligation_cancelled', (string) $obligationId, ['reason' => $reason]);
    }

    public function getObligations(int $user, int $company, array $filters = []): array
    {
        CompanyFinanceAccess::require($user, $company, 'view', $this->db);

        $b = $this->db->table('financial_obligations o')
            ->select('o.*, p.name AS property_name, p.code AS property_code,
                      COALESCE((SELECT SUM(s.amount) FROM obligation_settlements s JOIN cash_transactions t ON t.id = s.transaction_id AND t.company_id = s.company_id WHERE s.company_id = o.company_id AND s.obligation_id = o.id AND NOT EXISTS (SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id = t.id)), 0) AS settled_amount', false)
            ->join('properties p', 'p.id = o.property_id', 'left')
            ->where('o.company_id', $company);

        if (! empty($filters['type'])) {
            $b->where('o.obligation_type', $filters['type']);
        }
        if (! empty($filters['status'])) {
            $b->where('o.status', $filters['status']);
        }
        if (! empty($filters['month'])) {
            $b->where('o.month', $filters['month']);
        }
        if (! empty($filters['property_id'])) {
            $b->where('o.property_id', (int) $filters['property_id']);
        }

        $rows = $b->orderBy('o.id', 'DESC')->get()->getResultArray();

        foreach ($rows as &$row) {
            $totalCents = CashAmount::cents($row['amount']);
            $settledCents = CashAmount::cents((string) $row['settled_amount']);
            $row['remaining_balance'] = CashAmount::format(max(0, $totalCents - $settledCents));
        }
        unset($row);

        return $rows;
    }

    // =========================================================================
    // 3. ALOKASI OVERHEAD PUSAT KE PROPERTI (COMPANY EXPENSE ALLOCATIONS)
    // =========================================================================

    public function allocateOverhead(
        int $user,
        int $company,
        int $expenseId,
        int $propertyId,
        string $allocationPct,
        string $amount,
        string $month
    ): int {
        CompanyFinanceAccess::require($user, $company, 'approve', $this->db);

        $pctStr = $this->percent($allocationPct);
        $cents = $this->positiveMoney($amount);
        $amountStr = CashAmount::format($cents);
        $m = $this->month($month);

        // Lock expense row
        $expense = $this->db->query(
            'SELECT * FROM company_expenses WHERE company_id = ? AND id = ? FOR UPDATE',
            [$company, $expenseId]
        )->getRowArray();

        if (! $expense) {
            throw new RuntimeException('Beban overhead perusahaan tidak ditemukan.');
        }

        if ($expense['status'] !== 'Approved') {
            throw new RuntimeException('Overhead harus berstatus Approved sebelum dapat dialokasikan.');
        }

        // Verify property is in company scope
        $cp = $this->db->table('company_properties')
            ->where('company_id', $company)
            ->where('property_id', $propertyId)
            ->get()
            ->getRowArray();
        if (! $cp) {
            throw new RuntimeException('Properti tujuan alokasi di luar cakupan holding.');
        }

        // Sum other allocations for this expense
        $otherAlloc = $this->db->query(
            'SELECT COALESCE(SUM(amount), 0) AS total_amount, COALESCE(SUM(allocation_pct), 0) AS total_pct
             FROM company_expense_allocations
             WHERE company_id = ? AND expense_id = ? AND property_id != ?',
            [$company, $expenseId, $propertyId]
        )->getRowArray();

        $expenseTotalCents = CashAmount::cents($expense['amount']);
        $otherAllocCents = CashAmount::cents((string) $otherAlloc['total_amount']);
        $otherAllocPct = (float) $otherAlloc['total_pct'];

        if ($otherAllocCents + $cents > $expenseTotalCents) {
            throw new RuntimeException(
                'Total alokasi melebihi nominal beban overhead (Rp' . $expense['amount'] . '). '
                . 'Alokasi lain: Rp' . CashAmount::format($otherAllocCents) . ', usulan: Rp' . $amountStr
            );
        }

        if ($otherAllocPct + (float) $pctStr > 100.01) { // small tolerance for decimal
            throw new RuntimeException('Total persentase alokasi melebihi 100%.');
        }

        $payload = [
            'company_id' => $company,
            'expense_id' => $expenseId,
            'property_id' => $propertyId,
            'allocation_pct' => $pctStr,
            'amount' => $amountStr,
            'month' => $m,
            'created_by' => $user,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];

        $this->db->query(
            'INSERT INTO company_expense_allocations (company_id, expense_id, property_id, allocation_pct, amount, month, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE allocation_pct = VALUES(allocation_pct), amount = VALUES(amount), month = VALUES(month), created_by = VALUES(created_by), created_at = VALUES(created_at)',
            [$company, $expenseId, $propertyId, $pctStr, $amountStr, $m, $user, gmdate('Y-m-d H:i:s')]
        );

        $id = (int) $this->db->insertID();
        $this->audit($user, $company, 'overhead_allocated', (string) $expenseId, $payload);
        return $id;
    }

    public function getAllocations(int $user, int $company, string $month): array
    {
        CompanyFinanceAccess::require($user, $company, 'view', $this->db);
        $m = $this->month($month);

        return $this->db->table('company_expense_allocations a')
            ->select('a.*, p.name AS property_name, p.code AS property_code, e.description AS expense_description, e.category AS expense_category, e.amount AS expense_total_amount')
            ->join('properties p', 'p.id = a.property_id')
            ->join('company_expenses e', 'e.id = a.expense_id AND e.company_id = a.company_id')
            ->where('a.company_id', $company)
            ->where('a.month', $m)
            ->orderBy('a.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    // =========================================================================
    // 4. LAPORAN BULANAN MANAJEMEN, JEMBATAN HAK PERUSAHAAN & KAS BEBAS
    // =========================================================================

    public function monthlyReport(int $user, int $company, string $month, ?int $filterProperty = null): array
    {
        CompanyFinanceAccess::require($user, $company, 'view', $this->db);
        $m = $this->month($month);

        $firstDay = $m . '-01';
        $firstDate = new DateTimeImmutable($firstDay, new DateTimeZone('UTC'));
        $lastDay = $firstDate->modify('last day of this month')->format('Y-m-d');
        $startUtc = $firstDay . ' 00:00:00';
        $endUtc = $lastDay . ' 23:59:59';

        // Retrieve properties in company
        $propQuery = $this->db->table('company_properties cp')
            ->select('p.id, p.code, p.name, p.pricing_mode, p.base_rate, p.is_active')
            ->join('properties p', 'p.id = cp.property_id')
            ->where('cp.company_id', $company);

        if ($filterProperty !== null) {
            $propQuery->where('p.id', $filterProperty);
        }
        $properties = $propQuery->orderBy('p.id', 'ASC')->get()->getResultArray();

        // Calculate the whole Mitra basis once, even when the UI filters one source property.
        // Source rows are allocations of the combined entitlement, never a second calculation.
        $mitra = array_intersect([2, 3], array_map('intval', array_column($properties, 'id'))) === [] ? null
            : (new MitraOwnerFinanceService($this->db))->confirmedHolding($user, $company, $m);

        $companyDirectServiceNames = ['Breakfast', 'Extra Bed', 'Laundry', 'Massage', 'Sewa Kendaraan'];

        $propertyReports = [];
        $totalConfirmedGrossCents = 0;
        $totalConfirmedNetCents = 0;
        $totalUnitPaidExpensesCents = 0;
        $totalOwnerShareCents = 0;
        $totalCompanyFeeCents = 0;
        $totalDirectServicesCents = 0;
        $totalAllocatedOverheadCents = 0;

        $unconfirmedPaymentsWarning = [];
        $unconfiguredPropertiesWarning = [];

        foreach ($properties as $prop) {
            $propId = (int) $prop['id'];
            $pricingMode = (string) ($prop['pricing_mode'] ?? 'room_nightly');

            // --- 1. Operational Activity (Reservations / Room Nights) ---
            // GATE CHECK: Homestay whole_home_nightly counts 1 house per booking, NOT 3x rooms!
            $resBuilder = $this->db->table('reservations r')
                ->select("r.id, r.order_id, r.booking_group_id, r.total_amount, r.check_in_date, r.check_out_date,
                          COALESCE(NULLIF(r.booking_group_id, ''), r.order_id) AS booking_group_key", false)
                ->where('r.property_id', $propId)
                ->where('r.status !=', 'Cancelled')
                ->where('r.check_in_date <=', $lastDay)
                ->where('r.check_out_date >=', $firstDay);

            $rawReservations = $resBuilder->get()->getResultArray();

            $uniqueBookings = [];
            $roomNightsSold = 0;
            $grossBookedCents = 0;

            if ($pricingMode === 'whole_home_nightly') {
                // Whole home: group by booking_group_key
                foreach ($rawReservations as $r) {
                    $key = $r['booking_group_key'] ?: 'res_' . $r['id'];
                    if (! isset($uniqueBookings[$key])) {
                        $ci = new DateTimeImmutable(max($firstDay, $r['check_in_date']));
                        $co = new DateTimeImmutable(min($lastDay, $r['check_out_date']));
                        $nights = max(1, $ci->diff($co)->days);
                        $uniqueBookings[$key] = [
                            'nights' => $nights,
                            'amount_cents' => CashAmount::cents((string) $r['total_amount']),
                        ];
                    } else {
                        // Sum total_amount across rooms of the same booking (since rate is split across the 3 rooms)
                        $uniqueBookings[$key]['amount_cents'] += CashAmount::cents((string) $r['total_amount']);
                    }
                }
                foreach ($uniqueBookings as $ub) {
                    $roomNightsSold += $ub['nights'];
                    $grossBookedCents += $ub['amount_cents'];
                }
                $bookingCount = count($uniqueBookings);
            } else {
                // Room nightly or room monthly
                $bookingCount = count($rawReservations);
                foreach ($rawReservations as $r) {
                    $ci = new DateTimeImmutable(max($firstDay, $r['check_in_date']));
                    $co = new DateTimeImmutable(min($lastDay, $r['check_out_date']));
                    $nights = max(1, $ci->diff($co)->days);
                    $roomNightsSold += $nights;
                    $grossBookedCents += CashAmount::cents((string) $r['total_amount']);
                }
            }

            // --- 2. Confirmed Cash In (Penerimaan Tamu Terkonfirmasi) ---
            $confirmedReceipts = $this->db->query(
                "SELECT COALESCE(SUM(cr.amount), 0) AS net_cash,
                        COALESCE(SUM(cr.withheld_fee), 0) AS withheld_fee,
                        COALESCE(SUM(cr.source_amount), 0) AS source_total
                 FROM cash_payment_receipts cr
                 JOIN cash_transactions t ON t.id = cr.transaction_id AND t.company_id = cr.company_id
                 JOIN payments p ON p.id = cr.payment_id
                 JOIN reservations r ON r.id = p.reservation_id
                 WHERE cr.company_id = ? AND r.property_id = ?
                   AND t.effective_at >= ? AND t.effective_at <= ?
                   AND NOT EXISTS (SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id = t.id)",
                [$company, $propId, $startUtc, $endUtc]
            )->getRowArray();

            $confirmedNetCents = CashAmount::cents((string) $confirmedReceipts['net_cash']);
            $withheldFeeCents = CashAmount::cents((string) $confirmedReceipts['withheld_fee']);
            $confirmedGrossCents = $confirmedNetCents + $withheldFeeCents;

            // Check unconfirmed payments (Paid status in reservations but not yet confirmed in treasury)
            $unconfirmedCount = $this->db->query(
                "SELECT COUNT(p.id) AS cnt
                 FROM payments p
                 JOIN reservations r ON r.id = p.reservation_id
                 WHERE r.property_id = ? AND p.status = 'Paid'
                   AND p.payment_date >= ? AND p.payment_date <= ?
                   AND NOT EXISTS (SELECT 1 FROM cash_payment_receipts cr WHERE cr.payment_id = p.id AND cr.company_id = ?)",
                [$propId, $firstDay, $lastDay, $company]
            )->getRowArray()['cnt'] ?? 0;

            if ((int) $unconfirmedCount > 0) {
                $unconfirmedPaymentsWarning[] = [
                    'property' => $prop['name'],
                    'count' => (int) $unconfirmedCount,
                ];
            }

            // --- 3. Paid Operational Expenses (Beban Kas Properti) ---
            $paidExpenses = $this->db->query(
                "SELECT COALESCE(SUM(es.amount), 0) AS total_paid
                 FROM expense_settlements es
                 JOIN cash_transactions t ON t.id = es.transaction_id AND t.company_id = es.company_id
                 JOIN expenses e ON e.id = es.expense_id
                 WHERE es.company_id = ? AND e.property_id = ?
                   AND t.effective_at >= ? AND t.effective_at <= ?
                   AND NOT EXISTS (SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id = t.id)",
                [$company, $propId, $startUtc, $endUtc]
            )->getRowArray();

            $unitPaidExpensesCents = CashAmount::cents((string) $paidExpenses['total_paid']);

            // Recorded / Incurred expenses (Approved or Pending, date_incurred within month)
            $incurredExpenses = $this->db->query(
                "SELECT COALESCE(SUM(amount), 0) AS total_incurred
                 FROM expenses
                 WHERE property_id = ? AND status != 'Rejected'
                   AND date_incurred >= ? AND date_incurred <= ?",
                [$propId, $firstDay, $lastDay]
            )->getRowArray();
            $unitIncurredExpensesCents = CashAmount::cents((string) $incurredExpenses['total_incurred']);

            // Surplus Kas Operasional Unit
            $unitCashSurplusCents = $confirmedNetCents - $unitPaidExpensesCents;

            // --- 4. Company Direct Services (Breakfast, Laundry, Extra Bed, etc.) ---
            // GATE CHECK: Direct services are isolated from room rental base to prevent double counting
            // Khusus Homestay Mitra: Extra Bed bukan direct service holding melainkan masuk ke unit/owner.
            $propDirectServiceNames = ($pricingMode === 'whole_home_nightly' || $propId === 2 || ($prop['code'] ?? '') === 'HOMESTAY_MITRA')
                ? ['Breakfast', 'Laundry', 'Massage', 'Sewa Kendaraan']
                : $companyDirectServiceNames;

            if (in_array($propId, [2, 3], true)) {
                $directServicesCents = $mitra['direct'][$propId];
            } else {
                $directServicesSum = $this->db->query(
                    "SELECT COALESCE(SUM(ii.amount), 0) AS total_direct
                     FROM invoice_items ii
                     JOIN payments p ON p.reservation_id = ii.reservation_id
                     JOIN cash_payment_receipts cr ON cr.payment_id = p.id AND cr.company_id = ?
                     JOIN cash_transactions t ON t.id = cr.transaction_id AND t.company_id = cr.company_id
                     JOIN reservations r ON r.id = p.reservation_id
                     WHERE r.property_id = ? AND t.effective_at >= ? AND t.effective_at <= ?
                       AND ii.item_name IN ('" . implode("','", $propDirectServiceNames) . "')
                       AND NOT EXISTS (SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id = t.id)",
                    [$company, $propId, $startUtc, $endUtc]
                )->getRowArray();

                $directServicesCents = CashAmount::cents((string) $directServicesSum['total_direct']);
            }

            // Room rental confirmed portion = max(0, confirmedGrossCents - directServicesCents)
            $roomRentalConfirmedCents = max(0, $confirmedGrossCents - $directServicesCents);

            // --- 5. Effective Agreement & Entitlements ---
            $agreement = in_array($propId, [2, 3], true) ? null : $this->resolveAgreement($company, $propId, $firstDay);

            $ownerShareCents = 0;
            $companyFeeCents = 0;
            $hasAgreement = false;
            $agreementStatusNote = '';
            $agreementBreakdown = '';

            if (in_array($propId, [2, 3], true)) {
                $hasAgreement = $mitra['setup'] !== null && $mitra['error'] === null;
                if ($hasAgreement) {
                    $ownerShareCents = $mitra['owner'][$propId];
                    $companyFeeCents = $mitra['manager'][$propId];
                    $agreementStatusNote = 'Setup Owner Mitra gabungan (' . $mitra['setup']['owner_share_percentage'] . '% owner; omzet kas terkonfirmasi yang memenuhi hak owner)';
                    $agreementBreakdown = 'Homestay Mitra + Kos Mitra · ' . $mitra['setup']['owner_name'];
                } else {
                    $unconfiguredPropertiesWarning[] = $prop['name'];
                    $agreementStatusNote = $mitra['error'] ?? 'Setup owner Mitra gabungan diperlukan; bagi hasil belum dihitung';
                }
            } elseif ($agreement !== null) {
                $hasAgreement = true;
                $companyPct = (float) $agreement['company_share_pct'];
                $ownerPct = (float) $agreement['owner_share_pct'];
                $fixedFeeCents = CashAmount::cents($agreement['fixed_fee_monthly']);
                $calcBase = $agreement['calculation_base'];

                // Select base
                $baseCents = match ($calcBase) {
                    'room_revenue' => $roomRentalConfirmedCents,
                    'gross_revenue' => $confirmedGrossCents,
                    'net_revenue' => max(0, $unitCashSurplusCents),
                    'operating_profit' => max(0, $confirmedGrossCents - $unitIncurredExpensesCents),
                    'none' => 0,
                    default => $roomRentalConfirmedCents,
                };

                // Owner entitlement
                $ownerShareCents = (int) round($baseCents * ($ownerPct / 100.0));
                // Company management fee / revenue share
                $companyFeeCents = (int) round($baseCents * ($companyPct / 100.0)) + $fixedFeeCents;
                $agreementStatusNote = esc($agreement['agreement_type']) . ' (' . $companyPct . '% holding / ' . $ownerPct . '% owner)';
            } else {
                // If HomiQ ID 1, check fallback to multi-guesthouse owner setups
                if ($propId === 1) {
                    $rawSetups = $this->db->table('owner_guesthouse_setups')
                        ->where('property_id', 1)
                        ->get()
                        ->getResultArray();

                    $ghSetups = [];
                    for ($gh = 1; $gh <= 5; $gh++) {
                        $ghSetups[$gh] = [
                            'owner_pct' => 70.0,
                            'company_pct' => 30.0,
                            'configured' => false,
                        ];
                    }
                    foreach ($rawSetups as $s) {
                        $ghNum = (int) $s['guesthouse'];
                        if ($ghNum >= 1 && $ghNum <= 5) {
                            $ghSetups[$ghNum] = [
                                'owner_pct' => (float) $s['cooperation_fee_percentage'],
                                'company_pct' => 100.0 - (float) $s['cooperation_fee_percentage'],
                                'configured' => true,
                            ];
                        }
                    }

                    // Query confirmed receipts grouped by guesthouse
                    $ghReceipts = $this->db->query(
                        "SELECT rm.guesthouse_number,
                                COALESCE(SUM(cr.amount), 0) AS net_cash,
                                COALESCE(SUM(cr.withheld_fee), 0) AS withheld_fee
                         FROM cash_payment_receipts cr
                         JOIN cash_transactions t ON t.id = cr.transaction_id AND t.company_id = cr.company_id
                         JOIN payments p ON p.id = cr.payment_id
                         JOIN reservations r ON r.id = p.reservation_id
                         JOIN rooms rm ON rm.id = r.room_id
                         WHERE cr.company_id = ? AND r.property_id = 1
                           AND t.effective_at >= ? AND t.effective_at <= ?
                           AND NOT EXISTS (SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id = t.id)
                         GROUP BY rm.guesthouse_number",
                        [$company, $startUtc, $endUtc]
                    )->getResultArray();

                    // Query direct services per guesthouse
                    $ghDirectServices = [];
                    if (! empty($companyDirectServiceNames)) {
                        $ghDsQuery = $this->db->query(
                            "SELECT rm.guesthouse_number, COALESCE(SUM(ii.amount), 0) AS total_direct
                             FROM invoice_items ii
                             JOIN payments p ON p.reservation_id = ii.reservation_id
                             JOIN cash_payment_receipts cr ON cr.payment_id = p.id AND cr.company_id = ?
                             JOIN cash_transactions t ON t.id = cr.transaction_id AND t.company_id = cr.company_id
                             JOIN reservations r ON r.id = p.reservation_id
                             JOIN rooms rm ON rm.id = r.room_id
                             WHERE r.property_id = 1 AND t.effective_at >= ? AND t.effective_at <= ?
                               AND ii.item_name IN ('" . implode("','", $companyDirectServiceNames) . "')
                               AND NOT EXISTS (SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id = t.id)
                             GROUP BY rm.guesthouse_number",
                            [$company, $startUtc, $endUtc]
                        )->getResultArray();
                        foreach ($ghDsQuery as $dsRow) {
                            $ghDirectServices[(int) $dsRow['guesthouse_number']] = CashAmount::cents((string) $dsRow['total_direct']);
                        }
                    }

                    $ownerShareCents = 0;
                    $companyFeeCents = 0;
                    $handledRoomRentalCents = 0;

                    foreach ($ghReceipts as $row) {
                        $gh = (int) $row['guesthouse_number'];
                        $ghNet = CashAmount::cents((string) $row['net_cash']);
                        $ghWithheld = CashAmount::cents((string) $row['withheld_fee']);
                        $ghGross = $ghNet + $ghWithheld;
                        $ghDs = $ghDirectServices[$gh] ?? 0;
                        $ghRoomRental = max(0, $ghGross - $ghDs);

                        $ghOwnerPct = $ghSetups[$gh]['owner_pct'] ?? 70.0;
                        $ghCompanyPct = $ghSetups[$gh]['company_pct'] ?? 30.0;

                        $ownerShareCents += (int) round($ghRoomRental * ($ghOwnerPct / 100.0));
                        $companyFeeCents += (int) round($ghRoomRental * ($ghCompanyPct / 100.0));
                        $handledRoomRentalCents += $ghRoomRental;
                    }

                    // Any residual room rental not linked to a specific GH
                    $residualRoomRental = max(0, $roomRentalConfirmedCents - $handledRoomRentalCents);
                    if ($residualRoomRental > 0) {
                        $avgOwnerPct = array_sum(array_column($ghSetups, 'owner_pct')) / 5.0;
                        $avgCompanyPct = 100.0 - $avgOwnerPct;
                        $ownerShareCents += (int) round($residualRoomRental * ($avgOwnerPct / 100.0));
                        $companyFeeCents += (int) round($residualRoomRental * ($avgCompanyPct / 100.0));
                    }

                    // Determine note and breakdown
                    $uniqueOwnerPcts = array_values(array_unique(array_map(static fn($s) => round($s['owner_pct'], 1), $ghSetups)));
                    $isUniform = count($uniqueOwnerPcts) === 1;

                    if ($isUniform) {
                        $uOwner = $uniqueOwnerPcts[0];
                        $uCompany = 100.0 - $uOwner;
                        $agreementStatusNote = 'Multi-GH Setup (' . round($uCompany, 1) . '% / ' . round($uOwner, 1) . '%)';
                    } else {
                        if ($roomRentalConfirmedCents > 0) {
                            $effCompanyPct = round(($companyFeeCents / $roomRentalConfirmedCents) * 100.0, 1);
                            $effOwnerPct = round(100.0 - $effCompanyPct, 1);
                            $agreementStatusNote = 'Multi-GH Variatif (Tertimbang ' . $effCompanyPct . '% / ' . $effOwnerPct . '%)';
                        } else {
                            $minComp = min(array_column($ghSetups, 'company_pct'));
                            $maxComp = max(array_column($ghSetups, 'company_pct'));
                            $agreementStatusNote = 'Multi-GH Variatif (' . round($minComp, 1) . '%–' . round($maxComp, 1) . '% holding)';
                        }
                    }

                    $breakdownParts = [];
                    for ($gh = 1; $gh <= 5; $gh++) {
                        $breakdownParts[] = 'GH ' . $gh . ' (' . round($ghSetups[$gh]['company_pct']) . '/' . round($ghSetups[$gh]['owner_pct']) . '%)';
                    }
                    $agreementBreakdown = implode(' · ', $breakdownParts);
                    $hasAgreement = true;
                }

                if (! $hasAgreement) {
                    $unconfiguredPropertiesWarning[] = $prop['name'];
                    $agreementStatusNote = 'Belum ada perjanjian disepakati; hak belum dapat dihitung otomatis';
                }
            }

            // Total Company Entitlement for this property = Company Fee + Company Direct Services
            $totalCompanyEntitlementCents = $companyFeeCents + $directServicesCents;

            // --- 6. Allocated Holding Overhead ---
            $allocQuery = $this->db->table('company_expense_allocations')
                ->select('COALESCE(SUM(amount), 0) AS total_alloc')
                ->where('company_id', $company)
                ->where('property_id', $propId)
                ->where('month', $m)
                ->get()
                ->getRowArray();

            $allocatedOverheadCents = CashAmount::cents((string) $allocQuery['total_alloc']);

            // Net Unit Contribution to Company = Total Company Entitlement - Allocated Overhead
            $unitNetContributionCents = $totalCompanyEntitlementCents - $allocatedOverheadCents;

            // Accumulate company-wide totals
            $totalConfirmedGrossCents += $confirmedGrossCents;
            $totalConfirmedNetCents += $confirmedNetCents;
            $totalUnitPaidExpensesCents += $unitPaidExpensesCents;
            $totalOwnerShareCents += $ownerShareCents;
            $totalCompanyFeeCents += $companyFeeCents;
            $totalDirectServicesCents += $directServicesCents;
            $totalAllocatedOverheadCents += $allocatedOverheadCents;

            $propertyReports[] = [
                'property_id' => $propId,
                'property_code' => $prop['code'],
                'property_name' => $prop['name'],
                'pricing_mode' => $pricingMode,
                'booking_count' => $bookingCount,
                'room_nights_sold' => $roomNightsSold,
                'gross_booked' => CashAmount::format($grossBookedCents),
                'confirmed_net_cash' => CashAmount::format($confirmedNetCents),
                'confirmed_gross_cash' => CashAmount::format($confirmedGrossCents),
                'withheld_fee' => CashAmount::format($withheldFeeCents),
                'paid_expenses' => CashAmount::format($unitPaidExpensesCents),
                'incurred_expenses' => CashAmount::format($unitIncurredExpensesCents),
                'cash_surplus' => CashAmount::format($unitCashSurplusCents),
                'has_agreement' => $hasAgreement,
                'agreement_note' => $agreementStatusNote,
                'agreement_breakdown' => $agreementBreakdown,
                'owner_share' => CashAmount::format($ownerShareCents),
                'owner_share_base' => in_array($propId, [2, 3], true) ? CashAmount::format($mitra['bases'][$propId]) : null,
                'company_fee' => CashAmount::format($companyFeeCents),
                'direct_services' => CashAmount::format($directServicesCents),
                'total_company_entitlement' => CashAmount::format($totalCompanyEntitlementCents),
                'allocated_overhead' => CashAmount::format($allocatedOverheadCents),
                'net_contribution' => CashAmount::format($unitNetContributionCents),
            ];
        }

        // --- 7. Holding Overhead Total ---
        // GATE CHECK: Alokasi overhead jumlahnya cocok ke sumber.
        // Holding total only counts the actual company overhead expense once, NOT original + allocations!
        $holdingOverheadSettled = $this->db->query(
            "SELECT COALESCE(SUM(ces.amount), 0) AS total_settled
             FROM company_expense_settlements ces
             JOIN cash_transactions t ON t.id = ces.transaction_id AND t.company_id = ces.company_id
             WHERE ces.company_id = ?
               AND t.effective_at >= ? AND t.effective_at <= ?
               AND NOT EXISTS (SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id = t.id)",
            [$company, $startUtc, $endUtc]
        )->getRowArray();
        $holdingOverheadSettledCents = CashAmount::cents((string) $holdingOverheadSettled['total_settled']);

        // Gaji manajemen / mutasi operasional langsung kas holding
        $salarySettled = $this->db->query(
            "SELECT COALESCE(SUM(ABS(e.amount)), 0) AS total_salary
             FROM cash_entries e
             JOIN cash_transactions t ON t.id = e.transaction_id AND t.company_id = e.company_id
             WHERE t.company_id = ?
               AND t.source_type = 'management_salary'
               AND t.effective_at >= ? AND t.effective_at <= ?
               AND NOT EXISTS (SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id = t.id)",
            [$company, $startUtc, $endUtc]
        )->getRowArray();
        $salarySettledCents = CashAmount::cents((string) $salarySettled['total_salary']);
        $holdingOverheadSettledCents += $salarySettledCents;

        $holdingOverheadIncurred = $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) AS total_incurred
             FROM company_expenses
             WHERE company_id = ? AND status = 'Approved'
               AND date_incurred >= ? AND date_incurred <= ?",
            [$company, $firstDay, $lastDay]
        )->getRowArray();
        $holdingOverheadIncurredCents = CashAmount::cents((string) $holdingOverheadIncurred['total_incurred']) + $salarySettledCents;

        // --- 8. Reconciliation Bridge (Hasil Usaha Perusahaan) ---
        // Basis Kas: Total Company Fee + Total Direct Services - Holding Overhead Paid
        $companyNetResultCashCents = $totalCompanyFeeCents + $totalDirectServicesCents - $holdingOverheadSettledCents;

        // --- 9. Saldo Kas & Estimasi Kas Bebas ---
        $currentBalances = $this->ledger->balances($user, $company);
        $currentCashTotalCents = 0;
        foreach ($currentBalances as $cb) {
            if ($cb['balance'] !== null) {
                $currentCashTotalCents += CashAmount::cents((string) $cb['balance']);
            }
        }

        $monthEndBalances = $this->ledger->balances($user, $company, $endUtc);
        $monthEndCashTotalCents = 0;
        foreach ($monthEndBalances as $meb) {
            if ($meb['balance'] !== null) {
                $monthEndCashTotalCents += CashAmount::cents((string) $meb['balance']);
            }
        }

        // Outstanding Reserves:
        // 1. Owner payable pending
        $ownerPayableRes = $this->db->query(
            "SELECT COALESCE(SUM(o.amount), 0) - COALESCE(SUM((SELECT SUM(s.amount) FROM obligation_settlements s JOIN cash_transactions t ON t.id = s.transaction_id AND t.company_id = s.company_id WHERE s.obligation_id = o.id AND s.company_id = o.company_id AND NOT EXISTS (SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id = t.id))), 0) AS outstanding
             FROM financial_obligations o
             WHERE o.company_id = ? AND o.obligation_type = 'owner_payable'
               AND o.status IN ('estimated', 'finalized')",
            [$company]
        )->getRowArray();
        $ownerPayableReserveCents = max(0, CashAmount::cents((string) $ownerPayableRes['outstanding']));

        // 2. Refund / deposit pending
        $refundDepositRes = $this->db->query(
            "SELECT COALESCE(SUM(o.amount), 0) - COALESCE(SUM((SELECT SUM(s.amount) FROM obligation_settlements s JOIN cash_transactions t ON t.id = s.transaction_id AND t.company_id = s.company_id WHERE s.obligation_id = o.id AND s.company_id = o.company_id AND NOT EXISTS (SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id = t.id))), 0) AS outstanding
             FROM financial_obligations o
             WHERE o.company_id = ? AND o.obligation_type = 'refund_deposit'
               AND o.status IN ('estimated', 'finalized')",
            [$company]
        )->getRowArray();
        $refundDepositReserveCents = max(0, CashAmount::cents((string) $refundDepositRes['outstanding']));

        // 3. Other obligations pending
        $otherRes = $this->db->query(
            "SELECT COALESCE(SUM(o.amount), 0) - COALESCE(SUM((SELECT SUM(s.amount) FROM obligation_settlements s JOIN cash_transactions t ON t.id = s.transaction_id AND t.company_id = s.company_id WHERE s.obligation_id = o.id AND s.company_id = o.company_id AND NOT EXISTS (SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id = t.id))), 0) AS outstanding
             FROM financial_obligations o
             WHERE o.company_id = ? AND o.obligation_type NOT IN ('owner_payable', 'refund_deposit')
               AND o.status IN ('estimated', 'finalized')",
            [$company]
        )->getRowArray();
        $otherReserveCents = max(0, CashAmount::cents((string) $otherRes['outstanding']));

        $totalReservesCents = $ownerPayableReserveCents + $otherReserveCents;
        $estimatedFreeCashCents = $currentCashTotalCents - $totalReservesCents;

        // --- 10. Arus Kas Bulan Terpilih (Cash Flow) ---
        $externalFlow = $this->ledger->externalFlow($user, $company, $startUtc, $endUtc);
        $cashInTotalCents = 0;
        $cashOutTotalCents = 0;
        foreach ($externalFlow as $flow) {
            $cashInTotalCents += CashAmount::cents((string) $flow['cash_in']);
            $cashOutTotalCents += CashAmount::cents((string) $flow['cash_out']);
        }
        $netCashFlowCents = $cashInTotalCents - $cashOutTotalCents;

        // Estimated obligations needing review/finalization
        $estimatedObligationsCount = $this->db->table('financial_obligations')
            ->where('company_id', $company)
            ->where('status', 'estimated')
            ->countAllResults();

        return [
            'month' => $m,
            'firstDay' => $firstDay,
            'lastDay' => $lastDay,
            'properties' => $propertyReports,
            'bridge' => [
                'total_confirmed_gross' => CashAmount::format($totalConfirmedGrossCents),
                'total_confirmed_net' => CashAmount::format($totalConfirmedNetCents),
                'total_owner_share' => CashAmount::format($totalOwnerShareCents),
                'total_company_fee' => CashAmount::format($totalCompanyFeeCents),
                'total_direct_services' => CashAmount::format($totalDirectServicesCents),
                'total_company_entitlement' => CashAmount::format($totalCompanyFeeCents + $totalDirectServicesCents),
                'total_unit_paid_expenses' => CashAmount::format($totalUnitPaidExpensesCents),
                'holding_overhead_paid' => CashAmount::format($holdingOverheadSettledCents),
                'holding_overhead_incurred' => CashAmount::format($holdingOverheadIncurredCents),
                'company_net_result' => CashAmount::format($companyNetResultCashCents),
                'basis_label' => 'Ringkasan Berbasis Kas (CF-3)',
            ],
            'cards' => [
                'current_cash_total' => CashAmount::format($currentCashTotalCents),
                'month_end_cash_total' => CashAmount::format($monthEndCashTotalCents),
                'estimated_free_cash' => CashAmount::format($estimatedFreeCashCents),
                'total_reserves' => CashAmount::format($totalReservesCents),
                'reserves_detail' => [
                    'owner_payable' => CashAmount::format($ownerPayableReserveCents),
                    'refund_deposit' => CashAmount::format($refundDepositReserveCents),
                    'other' => CashAmount::format($otherReserveCents),
                ],
                'cash_in_month' => CashAmount::format($cashInTotalCents),
                'cash_out_month' => CashAmount::format($cashOutTotalCents),
                'net_cash_flow' => CashAmount::format($netCashFlowCents),
                'company_net_result' => CashAmount::format($companyNetResultCashCents),
            ],
            'externalFlow' => $externalFlow,
            'warnings' => [
                'unconfirmed_payments' => $unconfirmedPaymentsWarning,
                'unconfigured_properties' => $unconfiguredPropertiesWarning,
                'estimated_obligations_count' => (int) $estimatedObligationsCount,
            ],
        ];
    }

    public function trendSixMonths(int $user, int $company, string $month, bool $includeHistory = true, ?array $currentReport = null): array
    {
        CompanyFinanceAccess::require($user, $company, 'view', $this->db);
        $m = $this->month($month);
        $baseDate = new DateTimeImmutable($m . '-01', new DateTimeZone('UTC'));

        $months = [];
        // Other tabs only need the current/previous month for the shared KPI cards.
        for ($i = $includeHistory ? 5 : 1; $i >= 0; $i--) {
            $months[] = $baseDate->modify("-{$i} month")->format('Y-m');
        }

        $indoMonths = [
            '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
            '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
            '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des',
        ];

        $labels = [];
        $cashIn = [];
        $cashOut = [];
        $netCash = [];
        $companyResult = [];
        $monthReports = [];

        foreach ($months as $mon) {
            $part = explode('-', $mon);
            $labels[] = ($indoMonths[$part[1]] ?? $part[1]) . ' ' . $part[0];
            $rep = $mon === $m && $currentReport !== null
                ? $currentReport
                : $this->monthlyReport($user, $company, $mon);
            $monthReports[$mon] = $rep;

            $cashIn[] = (float) CashAmount::cents($rep['cards']['cash_in_month']) / 100.0;
            $cashOut[] = (float) CashAmount::cents($rep['cards']['cash_out_month']) / 100.0;
            $netCash[] = (float) CashAmount::cents($rep['cards']['net_cash_flow']) / 100.0;
            $companyResult[] = (float) CashAmount::cents($rep['cards']['company_net_result']) / 100.0;
        }

        $current = $monthReports[$m]['cards'];
        $previousMonth = $baseDate->modify('-1 month')->format('Y-m');
        $prev = $monthReports[$previousMonth]['cards'];

        $calcDelta = static function (string $currentStr, string $prevStr): array {
            $cur = CashAmount::cents($currentStr);
            $prv = CashAmount::cents($prevStr);
            $diff = $cur - $prv;
            $pct = $prv !== 0 ? round(($diff / abs($prv)) * 100, 1) : ($cur !== 0 ? 100.0 : 0.0);
            return [
                'current' => CashAmount::format($cur),
                'previous' => CashAmount::format($prv),
                'diff' => CashAmount::format($diff),
                'pct' => $pct,
                'is_up' => $diff > 0,
                'is_down' => $diff < 0,
            ];
        };

        return [
            'months' => $months,
            'labels' => $labels,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net_cash' => $netCash,
            'company_result' => $companyResult,
            'mom' => [
                'cash_in' => $calcDelta($current['cash_in_month'], $prev['cash_in_month']),
                'cash_out' => $calcDelta($current['cash_out_month'], $prev['cash_out_month']),
                'net_cash' => $calcDelta($current['net_cash_flow'], $prev['net_cash_flow']),
                'company_result' => $calcDelta($current['company_net_result'], $prev['company_net_result']),
            ],
        ];
    }

    public function auditSummary(int $user, int $company): array
    {
        CompanyFinanceAccess::require($user, $company, 'view', $this->db);

        $lastTx = $this->db->table('cash_transactions')
            ->select('effective_at, created_at')
            ->where('company_id', $company)
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        $lastAudit = $this->db->table('company_finance_audit')
            ->select('created_at, action, reference')
            ->where('company_id', $company)
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        $unconfirmedPayments = $this->db->query(
            "SELECT COUNT(p.id) AS cnt
             FROM payments p
             JOIN reservations r ON r.id = p.reservation_id
             JOIN company_properties cp ON cp.property_id = r.property_id
             WHERE cp.company_id = ? AND p.status = 'Paid'
               AND NOT EXISTS (SELECT 1 FROM cash_payment_receipts cr WHERE cr.payment_id = p.id AND cr.company_id = ?)",
            [$company, $company]
        )->getRowArray()['cnt'] ?? 0;

        $closedPeriods = $this->db->table('company_finance_periods')
            ->where('company_id', $company)
            ->where('status', 'closed')
            ->orderBy('month', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'last_transaction_at' => $lastTx['effective_at'] ?? null,
            'last_audit_at' => $lastAudit['created_at'] ?? null,
            'unconfirmed_payments_count' => (int) $unconfirmedPayments,
            'closed_periods' => $closedPeriods,
            'is_reconciled' => count($closedPeriods) > 0,
        ];
    }
}
