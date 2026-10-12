<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

final class CompanyReconciliationService
{
    private BaseConnection $db;
    private CashLedgerService $cashLedger;
    private CompanyEntitlementService $entitlementService;

    public function __construct(
        ?BaseConnection $db = null,
        ?CashLedgerService $cashLedger = null,
        ?CompanyEntitlementService $entitlementService = null
    ) {
        $this->db = $db ?? db_connect();
        $this->cashLedger = $cashLedger ?? new CashLedgerService($this->db);
        $this->entitlementService = $entitlementService ?? new CompanyEntitlementService($this->db, $this->cashLedger);
    }

    /**
     * Ambil status rekonsiliasi seluruh akun kas/bank pada periode tertentu.
     *
     * @return array{
     *     accounts: list<array<string, mixed>>,
     *     total_accounts: int,
     *     reconciled_count: int,
     *     discrepancy_count: int,
     *     pending_count: int,
     *     all_reconciled: bool,
     *     total_discrepancy: string,
     *     is_period_closed: bool,
     *     period_info: ?array<string, mixed>
     * }
     */
    public function getReconciliation(int $user, int $company, string $month): array
    {
        CompanyFinanceAccess::require($user, $company, 'view', $this->db);
        $this->validateMonth($month);

        $monthEnd = date('Y-m-t 23:59:59', strtotime($month . '-01'));

        $accounts = $this->db->table('cash_accounts')
            ->select('id, code, name, account_type, currency, is_active')
            ->where('company_id', $company)
            ->where('is_active', 1)
            ->orderBy('id')
            ->get()->getResultArray();

        $records = $this->db->table('company_bank_reconciliations r')
            ->select('r.*, u.full_name as reconciled_by_name')
            ->join('users u', 'u.id = r.reconciled_by', 'left')
            ->where('r.company_id', $company)
            ->where('r.month', $month)
            ->get()->getResultArray();

        $recordMap = [];
        foreach ($records as $r) {
            $recordMap[(int) $r['account_id']] = $r;
        }

        $period = $this->db->table('company_finance_periods')
            ->where('company_id', $company)
            ->where('month', $month)
            ->get()->getRowArray();
        $isClosed = ($period['status'] ?? 'open') === 'closed';

        $accountItems = [];
        $reconciledCount = 0;
        $discrepancyCount = 0;
        $pendingCount = 0;
        $totalDiscrepancyCents = 0;

        foreach ($accounts as $acc) {
            $accId = (int) $acc['id'];
            $existing = $recordMap[$accId] ?? null;

            // Hitung saldo buku ledger per akhir bulan
            $ledgerBalance = $this->calculateLedgerBalanceAsOf($company, $accId, $monthEnd);

            if ($existing !== null) {
                $statementBalance = CashAmount::format(CashAmount::cents((string) $existing['statement_balance']));
                $discrepancyCents = CashAmount::cents($statementBalance) - CashAmount::cents($ledgerBalance);
                $discrepancy = CashAmount::format($discrepancyCents);
                $status = ($discrepancyCents === 0) ? 'reconciled' : 'discrepancy';

                if ($status === 'reconciled') {
                    $reconciledCount++;
                } else {
                    $discrepancyCount++;
                    $totalDiscrepancyCents += abs(CashAmount::cents($discrepancy));
                }

                $accountItems[] = [
                    'account_id' => $accId,
                    'code' => (string) $acc['code'],
                    'name' => (string) $acc['name'],
                    'account_type' => (string) $acc['account_type'],
                    'currency' => (string) $acc['currency'],
                    'ledger_balance' => $ledgerBalance,
                    'statement_balance' => $statementBalance,
                    'discrepancy' => $discrepancy,
                    'status' => $status,
                    'statement_date' => (string) $existing['statement_date'],
                    'notes' => (string) ($existing['notes'] ?? ''),
                    'reference_document' => (string) ($existing['reference_document'] ?? ''),
                    'reconciled_by' => (int) $existing['reconciled_by'],
                    'reconciled_by_name' => (string) ($existing['reconciled_by_name'] ?? 'Staff'),
                    'reconciled_at' => (string) $existing['reconciled_at'],
                ];
            } else {
                $pendingCount++;
                $accountItems[] = [
                    'account_id' => $accId,
                    'code' => (string) $acc['code'],
                    'name' => (string) $acc['name'],
                    'account_type' => (string) $acc['account_type'],
                    'currency' => (string) $acc['currency'],
                    'ledger_balance' => $ledgerBalance,
                    'statement_balance' => null,
                    'discrepancy' => null,
                    'status' => 'pending',
                    'statement_date' => date('Y-m-t', strtotime($month . '-01')),
                    'notes' => '',
                    'reference_document' => '',
                    'reconciled_by' => null,
                    'reconciled_by_name' => null,
                    'reconciled_at' => null,
                ];
            }
        }

        $totalAccounts = count($accounts);
        $allReconciled = ($totalAccounts > 0 && $reconciledCount === $totalAccounts && $discrepancyCount === 0);

        return [
            'accounts' => $accountItems,
            'total_accounts' => $totalAccounts,
            'reconciled_count' => $reconciledCount,
            'discrepancy_count' => $discrepancyCount,
            'pending_count' => $pendingCount,
            'all_reconciled' => $allReconciled,
            'total_discrepancy' => CashAmount::format($totalDiscrepancyCents),
            'is_period_closed' => $isClosed,
            'period_info' => $period,
        ];
    }

    /**
     * Simpan hasil rekonsiliasi rekening koran bank vs saldo buku ledger.
     */
    public function saveReconciliation(
        int $user,
        int $company,
        int $accountId,
        string $month,
        string $statementDate,
        string $statementBalance,
        ?string $notes = null,
        ?string $reference = null
    ): array {
        CompanyFinanceAccess::require($user, $company, 'reconcile', $this->db);
        $this->validateMonth($month);

        $period = $this->db->table('company_finance_periods')
            ->where('company_id', $company)->where('month', $month)->get()->getRowArray();
        if (($period['status'] ?? 'open') === 'closed') {
            throw new RuntimeException('Periode ' . $month . ' sudah ditutup. Buka kembali periode untuk melakukan rekonsiliasi.');
        }

        $account = $this->db->table('cash_accounts')
            ->where('company_id', $company)->where('id', $accountId)->where('is_active', 1)
            ->get()->getRowArray();
        if (! $account) {
            throw new RuntimeException('Rekening kas/bank holding tidak ditemukan atau nonaktif.');
        }

        $statementDate = trim($statementDate);
        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $statementDate) !== 1) {
            throw new InvalidArgumentException('Format tanggal rekening koran tidak valid (wajib YYYY-MM-DD).');
        }

        $statementBalanceCents = CashAmount::cents(trim($statementBalance));
        $formattedStatementBalance = CashAmount::format($statementBalanceCents);
        $cutoff = $statementDate . ' 23:59:59';
        $ledgerBalance = $this->calculateLedgerBalanceAsOf($company, $accountId, $cutoff);

        $discrepancyCents = $statementBalanceCents - CashAmount::cents($ledgerBalance);
        $discrepancy = CashAmount::format($discrepancyCents);
        $status = ($discrepancyCents === 0) ? 'reconciled' : 'discrepancy';

        $notes = mb_substr(trim((string) $notes), 0, 500);
        $reference = mb_substr(trim((string) $reference), 0, 150);
        $now = gmdate('Y-m-d H:i:s');

        $this->db->query(
            'INSERT INTO company_bank_reconciliations (
                company_id, account_id, month, statement_date, statement_balance,
                ledger_balance, discrepancy, status, notes, reference_document,
                reconciled_by, reconciled_at, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                statement_date = VALUES(statement_date),
                statement_balance = VALUES(statement_balance),
                ledger_balance = VALUES(ledger_balance),
                discrepancy = VALUES(discrepancy),
                status = VALUES(status),
                notes = VALUES(notes),
                reference_document = VALUES(reference_document),
                reconciled_by = VALUES(reconciled_by),
                reconciled_at = VALUES(reconciled_at),
                updated_at = VALUES(updated_at)',
            [
                $company, $accountId, $month, $statementDate, $formattedStatementBalance,
                $ledgerBalance, $discrepancy, $status, $notes, $reference,
                $user, $now, $now, $now,
            ]
        );

        $this->db->table('company_finance_audit')->insert([
            'company_id' => $company,
            'user_id' => $user,
            'action' => 'bank_reconciled',
            'reference' => 'ACC-' . $accountId . '-' . $month,
            'details' => json_encode([
                'account_id' => $accountId,
                'account_code' => $account['code'],
                'month' => $month,
                'statement_balance' => $formattedStatementBalance,
                'ledger_balance' => $ledgerBalance,
                'discrepancy' => $discrepancy,
                'status' => $status,
                'notes' => $notes,
                'reference_document' => $reference,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        return [
            'account_id' => $accountId,
            'month' => $month,
            'statement_balance' => $formattedStatementBalance,
            'ledger_balance' => $ledgerBalance,
            'discrepancy' => $discrepancy,
            'status' => $status,
        ];
    }

    /**
     * Verifikasi integritas matematis saldo rekening holding sebelum tutup buku.
     * Gate: Saldo Penutup = Saldo Pembuka + Mutasi Kas Masuk - Mutasi Kas Keluar.
     */
    public function verifyPeriodIntegrity(int $company, string $month): array
    {
        $this->validateMonth($month);
        $monthStart = $month . '-01 00:00:00';
        $monthEnd = date('Y-m-t 23:59:59', strtotime($monthStart));

        $accounts = $this->db->table('cash_accounts')
            ->where('company_id', $company)->where('is_active', 1)->get()->getResultArray();

        $accountAudits = [];
        $allValid = true;

        foreach ($accounts as $acc) {
            $accId = (int) $acc['id'];
            $opening = $this->calculateLedgerBalanceAsOf($company, $accId, date('Y-m-d H:i:s', strtotime($monthStart) - 1));

            // Mutasi dalam bulan berjalan
            $mutations = $this->db->query(
                'SELECT
                    COALESCE(SUM(CASE WHEN e.amount > 0 THEN e.amount ELSE 0 END), 0) AS cash_in,
                    COALESCE(SUM(CASE WHEN e.amount < 0 THEN -e.amount ELSE 0 END), 0) AS cash_out,
                    COALESCE(SUM(e.amount), 0) AS net
                FROM cash_entries e
                JOIN cash_transactions t ON t.id = e.transaction_id AND t.company_id = e.company_id
                WHERE e.company_id = ? AND e.account_id = ? AND t.effective_at >= ? AND t.effective_at <= ?',
                [$company, $accId, $monthStart, $monthEnd]
            )->getRowArray();

            $cashIn = CashAmount::format(CashAmount::cents((string) ($mutations['cash_in'] ?? '0.00')));
            $cashOut = CashAmount::format(CashAmount::cents((string) ($mutations['cash_out'] ?? '0.00')));
            $net = CashAmount::format(CashAmount::cents((string) ($mutations['net'] ?? '0.00')));

            $expectedClosingCents = CashAmount::cents($opening) + CashAmount::cents($net);
            $actualClosing = $this->calculateLedgerBalanceAsOf($company, $accId, $monthEnd);
            $actualClosingCents = CashAmount::cents($actualClosing);

            $match = ($expectedClosingCents === $actualClosingCents);
            if (! $match) {
                $allValid = false;
            }

            $accountAudits[] = [
                'account_id' => $accId,
                'code' => (string) $acc['code'],
                'opening_balance' => $opening,
                'cash_in' => $cashIn,
                'cash_out' => $cashOut,
                'net_mutations' => $net,
                'closing_balance' => $actualClosing,
                'is_consistent' => $match,
            ];
        }

        if (! $allValid) {
            throw new RuntimeException('Integritas saldo penutup gagal: saldo penutup tidak konsisten dengan saldo pembuka dan mutasi kas.');
        }

        return [
            'is_valid' => true,
            'accounts' => $accountAudits,
        ];
    }

    /**
     * Tutup buku periode holding & bekukan snapshot laporan (CF-5 Gate).
     */
    public function closePeriodWithSnapshot(int $user, int $company, string $month, string $reason): array
    {
        CompanyFinanceAccess::require($user, $company, 'close', $this->db);
        $this->validateMonth($month);
        $reason = mb_substr(trim($reason), 0, 500);
        if ($reason === '') {
            throw new RuntimeException('Alasan penutupan periode dan catatan audit wajib diisi.');
        }

        $period = $this->db->table('company_finance_periods')
            ->where('company_id', $company)->where('month', $month)->get()->getRowArray();
        if (($period['status'] ?? 'open') === 'closed') {
            throw new RuntimeException('Periode ' . $month . ' sudah berstatus tertutup (closed).');
        }

        // 1. Verifikasi integritas matematis saldo penutup = saldo pembuka + mutasi
        $integrity = $this->verifyPeriodIntegrity($company, $month);

        // 2. Kumpulkan seluruh data pembekuan laporan
        $monthStart = $month . '-01 00:00:00';
        $monthEnd = date('Y-m-t 23:59:59', strtotime($monthStart));

        $monthlyReport = $this->entitlementService->monthlyReport($user, $company, $month);
        $externalFlow = $this->cashLedger->externalFlow($user, $company, $monthStart, $monthEnd);
        $reconciliation = $this->getReconciliation($user, $company, $month);

        // Versi snapshot berikutnya
        $lastSnapshot = $this->db->table('company_finance_snapshots')
            ->where('company_id', $company)->where('month', $month)
            ->orderBy('version', 'DESC')->limit(1)->get()->getRowArray();
        $version = $lastSnapshot ? ((int) $lastSnapshot['version'] + 1) : 1;

        $snapshotPayload = [
            'version' => $version,
            'company_id' => $company,
            'month' => $month,
            'closed_at' => gmdate('Y-m-d H:i:s'),
            'closed_by' => $user,
            'reason' => $reason,
            'integrity_verification' => $integrity,
            'accounts_audit' => $integrity['accounts'],
            'monthly_report' => $monthlyReport,
            'external_flow' => $externalFlow,
            'reconciliation_summary' => [
                'total_accounts' => $reconciliation['total_accounts'],
                'reconciled_count' => $reconciliation['reconciled_count'],
                'discrepancy_count' => $reconciliation['discrepancy_count'],
                'total_discrepancy' => $reconciliation['total_discrepancy'],
                'all_reconciled' => $reconciliation['all_reconciled'],
                'accounts' => $reconciliation['accounts'],
            ],
        ];

        $jsonPayload = json_encode($snapshotPayload, JSON_THROW_ON_ERROR);
        $snapshotHash = hash('sha256', $jsonPayload);

        // 3. Simpan snapshot & kunci periode secara atomik
        $this->db->transStart();

        $this->db->table('company_finance_snapshots')->insert([
            'company_id' => $company,
            'month' => $month,
            'version' => $version,
            'snapshot_data' => $jsonPayload,
            'snapshot_hash' => $snapshotHash,
            'closed_by' => $user,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->db->query(
            'INSERT INTO company_finance_periods (company_id, month, status, changed_by, reason, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), changed_by = VALUES(changed_by), reason = VALUES(reason), updated_at = VALUES(updated_at)',
            [$company, $month, 'closed', $user, $reason, gmdate('Y-m-d H:i:s')]
        );

        $this->db->table('company_finance_audit')->insert([
            'company_id' => $company,
            'user_id' => $user,
            'action' => 'period_closed_snapshot',
            'reference' => 'PERIOD-' . $month,
            'details' => json_encode([
                'month' => $month,
                'version' => $version,
                'snapshot_hash' => $snapshotHash,
                'reason' => $reason,
                'total_accounts' => $reconciliation['total_accounts'],
                'all_reconciled' => $reconciliation['all_reconciled'],
            ], JSON_THROW_ON_ERROR),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('Gagal membekukan snapshot dan menutup periode.');
        }

        return [
            'month' => $month,
            'status' => 'closed',
            'version' => $version,
            'snapshot_hash' => $snapshotHash,
            'closed_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    /**
     * Buka kembali periode yang terkunci (hanya Company Approver dengan alasan audit wajib).
     */
    public function reopenPeriod(int $user, int $company, string $month, string $reason): void
    {
        CompanyFinanceAccess::require($user, $company, 'close', $this->db);
        $this->validateMonth($month);
        $reason = mb_substr(trim($reason), 0, 500);
        if ($reason === '') {
            throw new RuntimeException('Alasan audit pembukaan kembali periode wajib diisi.');
        }

        $period = $this->db->table('company_finance_periods')
            ->where('company_id', $company)->where('month', $month)->get()->getRowArray();
        if (! $period || $period['status'] !== 'closed') {
            throw new RuntimeException('Periode ' . $month . ' tidak dalam status tertutup.');
        }

        $this->db->transStart();

        $this->db->table('company_finance_periods')
            ->where('company_id', $company)->where('month', $month)
            ->update([
                'status' => 'open',
                'changed_by' => $user,
                'reason' => $reason,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

        $this->db->table('company_finance_audit')->insert([
            'company_id' => $company,
            'user_id' => $user,
            'action' => 'period_reopened',
            'reference' => 'PERIOD-' . $month,
            'details' => json_encode([
                'month' => $month,
                'reason' => $reason,
            ], JSON_THROW_ON_ERROR),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('Gagal membuka kembali periode akuntansi.');
        }
    }

    /**
     * Ambil snapshot laporan beku jika periode sudah tertutup.
     */
    public function getSnapshot(int $user, int $company, string $month): ?array
    {
        CompanyFinanceAccess::require($user, $company, 'view', $this->db);
        $this->validateMonth($month);

        $row = $this->db->table('company_finance_snapshots')
            ->where('company_id', $company)->where('month', $month)
            ->orderBy('version', 'DESC')->limit(1)->get()->getRowArray();

        if (! $row) {
            return null;
        }

        $decoded = json_decode((string) $row['snapshot_data'], true);
        return [
            'id' => (int) $row['id'],
            'company_id' => (int) $row['company_id'],
            'month' => (string) $row['month'],
            'version' => (int) $row['version'],
            'snapshot_hash' => (string) $row['snapshot_hash'],
            'closed_by' => (int) $row['closed_by'],
            'created_at' => (string) $row['created_at'],
            'data' => $decoded,
        ];
    }

    /**
     * Hitung saldo buku ledger untuk akun tertentu per tanggal/jam cutoff UTC.
     */
    private function calculateLedgerBalanceAsOf(int $company, int $accountId, string $cutoff): string
    {
        $opening = $this->db->table('cash_opening_balances')
            ->where('company_id', $company)->where('account_id', $accountId)
            ->get()->getRowArray();

        if (! $opening || $opening['effective_at'] > $cutoff) {
            return '0.00';
        }

        $row = $this->db->query(
            'SELECT COALESCE(SUM(e.amount), 0) AS net
             FROM cash_entries e
             JOIN cash_transactions t ON t.id = e.transaction_id AND t.company_id = e.company_id
             WHERE e.company_id = ? AND e.account_id = ?
               AND t.effective_at >= ? AND t.effective_at <= ?',
            [$company, $accountId, $opening['effective_at'], $cutoff]
        )->getRowArray();

        $openingCents = CashAmount::cents((string) $opening['amount']);
        $netCents = CashAmount::cents((string) ($row['net'] ?? '0.00'));

        return CashAmount::format($openingCents + $netCents);
    }

    private function validateMonth(string $month): void
    {
        if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $month) !== 1) {
            throw new InvalidArgumentException('Format periode bulan tidak valid (wajib YYYY-MM).');
        }
    }
}
