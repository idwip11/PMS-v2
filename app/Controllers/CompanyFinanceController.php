<?php

namespace App\Controllers;

use App\Libraries\CashAmount;
use App\Libraries\CashLedgerService;
use App\Libraries\CompanyFinanceAccess;
use App\Libraries\CompanyFinanceContext;
use App\Libraries\CompanyCashIntegration;
use App\Libraries\CompanyEntitlementService;
use App\Libraries\CompanyReconciliationService;
use App\Libraries\RoleAccess;
use DateTimeImmutable;

final class CompanyFinanceController extends BaseController
{
    public function index()
    {
        if (! RoleAccess::allows((string) session('role'), 'company_finance')) {
            return $this->response->setStatusCode(403)->setBody('Akses keuangan perusahaan hanya untuk role Manager dan Finance.');
        }

        $user = (int) session('userId');
        $company = CompanyFinanceContext::resolve($user, $this->request->getGet('company'));
        if ($company === null || ! CompanyFinanceAccess::allows($user, $company, 'view')) {
            return $this->response->setStatusCode(403)->setBody('Akses holding ditolak.');
        }

        $month = trim((string) $this->request->getGet('month'));
        if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $month) !== 1) {
            $month = gmdate('Y-m');
        }

        $tab = trim((string) $this->request->getGet('tab'));
        $allowedTabs = ['summary', 'monthly', 'cashflow', 'treasury', 'overhead', 'cash'];
        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'summary';
        }

        $rawProp = $this->request->getGet('property_id');
        $propertyFilter = filter_var($rawProp, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

        $db = db_connect();
        $entitlementService = new CompanyEntitlementService($db);
        $ledgerService = new CashLedgerService($db);
        $cashIntegration = new CompanyCashIntegration($db);
        $defaultAccountId = $this->ensureConsolidatedAccount($user, $company);

        // Date range for external IAS 7 cash flow
        $monthStart = $month . '-01 00:00:00';
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('+1 month')->format('Y-m-d 00:00:00');

        // Ledger transactions pagination & filters
        $ledgerPage = max(1, (int) $this->request->getGet('ledger_page'));
        $ledgerPerPage = filter_var($this->request->getGet('ledger_per_page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]) ?: 10;
        $ledgerFilters = [
            'account_id' => filter_var($this->request->getGet('ledger_account'), FILTER_VALIDATE_INT) ?: null,
            'kind' => trim((string) $this->request->getGet('ledger_kind')) ?: null,
            'classification' => trim((string) $this->request->getGet('ledger_classification')) ?: null,
            'property_id' => $propertyFilter,
            'month' => $month,
            'search' => trim((string) $this->request->getGet('ledger_search')) ?: null,
        ];

        $reconciliationService = new CompanyReconciliationService($db, $ledgerService, $entitlementService);
        $isTreasury = in_array($tab, ['treasury', 'cash', 'overhead'], true);
        $sub = trim((string) $this->request->getGet('sub')) ?: 'reconciliation';
        if (! in_array($sub, ['reconciliation', 'close_period', 'overhead', 'operations'], true)) {
            $sub = 'reconciliation';
        }
        // Keep period status in the shared header without calculating every account balance.
        $period = $db->table('company_finance_periods')
            ->where('company_id', $company)->where('month', $month)->get()->getRowArray();
        $reconciliationData = $isTreasury && in_array($sub, ['reconciliation', 'close_period'], true)
            ? $reconciliationService->getReconciliation($user, $company, $month)
            : ['is_period_closed' => ($period['status'] ?? 'open') === 'closed'];
        $snapshotData = $reconciliationService->getSnapshot($user, $company, $month);

        $monthlyReport = $entitlementService->monthlyReport($user, $company, $month, $propertyFilter);
        // Trend remains company-wide; never reuse a property-filtered report here.
        $trend = $entitlementService->trendSixMonths(
            $user, $company, $month, $tab === 'summary', $propertyFilter === null ? $monthlyReport : null
        );

        return view('pages/company_finance_foundation', [
            'partial' => $this->request->getHeaderLine('HX-Request') === 'true'
                && $this->request->getHeaderLine('HX-History-Restore-Request') !== 'true',
            'pageTitle' => 'Keuangan Holding & Kas Perusahaan',
            'pageDescription' => 'Konsolidasi arus kas, hak holding, kewajiban, dan mutasi ledger — CF-5',
            'companies' => CompanyFinanceContext::assignments($user),
            'company' => $db->table('companies')->where('id', $company)->get()->getRowArray(),
            'properties' => CompanyFinanceContext::properties($user, $company),
            'accounts' => $ledgerService->balances($user, $company),
            'defaultAccountId' => $defaultAccountId,
            'asOf' => gmdate('Y-m-d H:i:s'),
            'selectedMonth' => $month,
            'activeTab' => $tab,
            'treasurySub' => $isTreasury ? $sub : null,
            'selectedProperty' => $propertyFilter,
            'monthlyReport' => $monthlyReport,
            'trend' => $trend,
            'auditSummary' => $entitlementService->auditSummary($user, $company),
            'externalFlow' => $tab === 'cashflow' ? $ledgerService->externalFlow($user, $company, $monthStart, $monthEnd) : [],
            'transactionsData' => $tab === 'cashflow' ? $ledgerService->transactions($user, $company, $ledgerFilters, $ledgerPage, $ledgerPerPage) : [],
            'ledgerFilters' => $ledgerFilters,
            'ledgerPage' => $ledgerPage,
            'ledgerPerPage' => $ledgerPerPage,
            'allocations' => $isTreasury && $sub === 'overhead' ? $entitlementService->getAllocations($user, $company, $month) : [],
            'preview' => $isTreasury && in_array($sub, ['operations', 'overhead'], true) ? $cashIntegration->preview($user, $company) : [],
            'reconciliation' => $reconciliationData,
            'snapshot' => $snapshotData,
            'canPost' => CompanyFinanceAccess::allows($user, $company, 'post'),
            'canApprove' => CompanyFinanceAccess::allows($user, $company, 'approve'),
            'canExport' => CompanyFinanceAccess::allows($user, $company, 'export'),
            'canClose' => CompanyFinanceAccess::allows($user, $company, 'close'),
            'canReconcile' => CompanyFinanceAccess::allows($user, $company, 'reconcile'),
        ]);
    }

    public function export()
    {
        if (! RoleAccess::allows((string) session('role'), 'company_finance')) {
            return $this->response->setStatusCode(403)->setBody('Akses keuangan perusahaan hanya untuk role Manager dan Finance.');
        }

        $user = (int) session('userId');
        $rawCompany = $this->request->getGet('company') ?? ($_GET['company'] ?? null);
        $company = CompanyFinanceContext::resolve($user, $rawCompany);
        if ($company === null || ! CompanyFinanceAccess::allows($user, $company, 'export')) {
            return $this->response->setStatusCode(403)->setBody('Akses ekspor keuangan holding ditolak.');
        }

        $month = trim((string) ($this->request->getGet('month') ?? ($_GET['month'] ?? '')));
        if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $month) !== 1) {
            $month = gmdate('Y-m');
        }

        $rawProp = $this->request->getGet('property_id') ?? ($_GET['property_id'] ?? null);
        $propertyFilter = filter_var($rawProp, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

        $db = db_connect();
        $ledgerService = new CashLedgerService($db);
        $entitlementService = new CompanyEntitlementService($db, $ledgerService);
        $reconciliationService = new CompanyReconciliationService($db, $ledgerService, $entitlementService);

        $companyRow = $db->table('companies')->where('id', $company)->get()->getRowArray();
        $monthlyReport = $entitlementService->monthlyReport($user, $company, $month, $propertyFilter);
        $auditSummary = $entitlementService->auditSummary($user, $company);
        $accounts = $ledgerService->balances($user, $company);
        $reconciliationData = $reconciliationService->getReconciliation($user, $company, $month);
        $snapshotData = $reconciliationService->getSnapshot($user, $company, $month);

        $monthStart = $month . '-01 00:00:00';
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('+1 month')->format('Y-m-d 00:00:00');
        $externalFlow = $ledgerService->externalFlow($user, $company, $monthStart, $monthEnd);

        $txFilters = ['month' => $month];
        if ($propertyFilter !== null) {
            $txFilters['property_id'] = $propertyFilter;
        }
        $transactionsData = $ledgerService->transactions($user, $company, $txFilters, 1, 5000);
        $obligations = $entitlementService->getObligations($user, $company, $propertyFilter ? ['property_id' => $propertyFilter] : []);

        // Formula injection protection: prepend single-quote to characters =, +, -, @
        $sanitizeString = static function ($value): string {
            $str = (string) $value;
            if ($str !== '' && in_array($str[0], ['=', '+', '-', '@'], true)) {
                $str = "'" . $str;
            }
            return $str;
        };

        $stringCell = static function ($value, string $style = '', ?string $href = null) use ($sanitizeString): string {
            $clean = $sanitizeString($value);
            $attributes = $style === '' ? '' : ' ss:StyleID="' . $style . '"';
            if ($href !== null) {
                $attributes .= ' ss:HRef="' . htmlspecialchars($href, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"';
            }
            return '<Cell' . $attributes . '><Data ss:Type="String">' . htmlspecialchars($clean, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</Data></Cell>';
        };

        $numberCell = static fn ($value, string $style = 'Currency'): string =>
            '<Cell ss:StyleID="' . $style . '"><Data ss:Type="Number">' . (float) $value . '</Data></Cell>';

        $emptyRow = '<Row/>';

        // 1. WORKSHEET: Ringkasan & Jembatan
        $propScopeName = 'Semua Properti Terdaftar';
        if ($propertyFilter !== null) {
            $p = $db->table('properties')->where('id', $propertyFilter)->get()->getRowArray();
            $propScopeName = $p ? $p['name'] . ' (' . $p['code'] . ')' : 'Properti #' . $propertyFilter;
        }

        $ws1Rows = [
            '<Row>' . $stringCell('LAPORAN KEUANGAN KONSOLIDASI HOLDING', 'Title') . '</Row>',
            '<Row>' . $stringCell('Ringkasan Kinerja & Jembatan Rekonsiliasi Hak Holding', 'SubTitle') . '</Row>',
            $emptyRow,
            '<Row>' . $stringCell('Perusahaan:', 'Section') . $stringCell(($companyRow['name'] ?? 'Holding') . ' (' . ($companyRow['code'] ?? '') . ')') . '</Row>',
            '<Row>' . $stringCell('Periode Laporan:', 'Section') . $stringCell($month) . '</Row>',
            '<Row>' . $stringCell('Cakupan Properti:', 'Section') . $stringCell($propScopeName) . '</Row>',
            '<Row>' . $stringCell('Waktu Unduh (As-Of):', 'Section') . $stringCell(gmdate('Y-m-d H:i:s') . ' UTC') . '</Row>',
            '<Row>' . $stringCell('Basis Akuntansi:', 'Section') . $stringCell('Kas Riil (Cash Basis - IAS 7 / IFRS 15)') . '</Row>',
            '<Row>' . $stringCell('Status Rekonsiliasi & Periode:', 'Section') . $stringCell(
                $snapshotData !== null
                    ? 'Periode Ditutup & Dibekukan (Snapshot v' . $snapshotData['version'] . ' - Hash: ' . substr($snapshotData['snapshot_hash'], 0, 16) . '...)'
                    : ($reconciliationData['all_reconciled'] ? 'Seluruh Rekening Terekonsiliasi (Matched)' : 'Terdapat ' . $reconciliationData['discrepancy_count'] . ' Rekening Selisih / Belum Rekonsiliasi')
            ) . '</Row>',
            '<Row>' . $stringCell('Waktu Transaksi Terakhir:', 'Section') . $stringCell($auditSummary['last_transaction_at'] ?? 'Belum ada transaksi') . '</Row>',
            $emptyRow,
            '<Row>' . $stringCell('KARTU INDIKATOR KINERJA BULAN INI', 'Header') . $stringCell('Nominal (IDR)', 'Header') . $stringCell('Keterangan', 'Header') . '</Row>',
            '<Row>' . $stringCell('Total Kas Masuk Periode Ini') . $numberCell($monthlyReport['cards']['cash_in_month']) . $stringCell('Penerimaan kas operasional terverifikasi di bank') . '</Row>',
            '<Row>' . $stringCell('Total Kas Keluar Periode Ini') . $numberCell($monthlyReport['cards']['cash_out_month']) . $stringCell('Pengeluaran kas operasional dan pelunasan kewajiban') . '</Row>',
            '<Row>' . $stringCell('Arus Kas Bersih (Net Cash Flow)', 'Total') . $numberCell($monthlyReport['cards']['net_cash_flow'], 'CurrencyTotal') . $stringCell('Surplus / (Defisit) kas bulan ini', 'Total') . '</Row>',
            '<Row>' . $stringCell('Hak Bersih Perusahaan (Net Entitlement)', 'Total') . $numberCell($monthlyReport['cards']['company_net_result'], 'CurrencyTotal') . $stringCell('Hasil ekonomi holding setelah overhead', 'Total') . '</Row>',
            '<Row>' . $stringCell('Kewajiban Belum Lunas (Outstanding)') . $numberCell($monthlyReport['cards']['total_reserves']) . $stringCell('Tagihan bagi hasil owner dan refund menunggu kas') . '</Row>',
            $emptyRow,
            '<Row>' . $stringCell('JEMBATAN REKONSILIASI HAK HOLDING (GROSS TO NET)', 'Header') . $stringCell('Nominal (IDR)', 'Header') . $stringCell('Catatan / Basis Perhitungan', 'Header') . '</Row>',
        ];

        $b = $monthlyReport['bridge'];
        $bridgeSteps = [
            ['label' => '1. Total Penerimaan Kas Gross (Dikonfirmasi)', 'amount' => $b['total_confirmed_gross'] ?? '0.00', 'note' => 'Penerimaan bruto operasional unit', 'is_result' => false],
            ['label' => '2. Penerimaan Kas Bersih (Setelah Fee Channel)', 'amount' => $b['total_confirmed_net'] ?? '0.00', 'note' => 'Kas riil masuk rekening operasional', 'is_result' => false],
            ['label' => '3. Pengeluaran Kas Operasional Unit Terbayar', 'amount' => $b['total_unit_paid_expenses'] ?? '0.00', 'note' => 'Beban unit yang diselesaikan kas', 'is_result' => false],
            ['label' => '4. Hak Pemilik / Investor (Owner Share)', 'amount' => $b['total_owner_share'] ?? '0.00', 'note' => 'Bagi hasil owner terhitung', 'is_result' => true],
            ['label' => '5. Fee Pengelolaan Unit (Management Fee)', 'amount' => $b['total_company_fee'] ?? '0.00', 'note' => 'Hak pengelola dari unit operasional', 'is_result' => false],
            ['label' => '6. Layanan Langsung Holding', 'amount' => $b['total_direct_services'] ?? '0.00', 'note' => 'Pendapatan langsung non-unit', 'is_result' => false],
            ['label' => '7. Total Hak Ekonomi Holding (Gross Entitlement)', 'amount' => $b['total_company_entitlement'] ?? '0.00', 'note' => 'Fee Unit + Layanan Langsung', 'is_result' => true],
            ['label' => '8. Beban Overhead Holding Terbayar Kas', 'amount' => $b['holding_overhead_paid'] ?? '0.00', 'note' => 'Beban umum & operasional holding', 'is_result' => false],
            ['label' => '9. Hasil Usaha Bersih Holding (Net Entitlement)', 'amount' => $b['company_net_result'] ?? '0.00', 'note' => 'Hak Bersih Holding (Basis Kas CF-3/CF-4)', 'is_result' => true],
        ];

        foreach ($bridgeSteps as $step) {
            $style = $step['is_result'] ? 'Total' : '';
            $numStyle = $step['is_result'] ? 'CurrencyTotal' : 'Currency';
            $ws1Rows[] = '<Row>'
                . $stringCell($step['label'], $style)
                . $numberCell($step['amount'], $numStyle)
                . $stringCell($step['note'], $style)
                . '</Row>';
        }

        // 2. WORKSHEET: Kontribusi Properti
        $ws2Rows = [
            '<Row>' . $stringCell('KONTRIBUSI KEUANGAN PER UNIT PROPERTI - BULAN ' . $month, 'Title') . '</Row>',
            $emptyRow,
            '<Row>'
                . $stringCell('Kode Properti', 'Header')
                . $stringCell('Nama Properti', 'Header')
                . $stringCell('Model Perjanjian', 'Header')
                . $stringCell('Kas Masuk (Gross)', 'Header')
                . $stringCell('Kas Masuk (Net)', 'Header')
                . $stringCell('Beban Kas Unit', 'Header')
                . $stringCell('Surplus Kas Unit', 'Header')
                . $stringCell('Hak Owner', 'Header')
                . $stringCell('Hak Holding', 'Header')
                . $stringCell('Kontribusi Bersih', 'Header')
                . '</Row>',
        ];

        $sumGross = 0; $sumNetCash = 0; $sumExp = 0; $sumSurplus = 0; $sumOwner = 0; $sumComp = 0; $sumNetContrib = 0;
        foreach ($monthlyReport['properties'] as $prop) {
            $gross = (float) ($prop['confirmed_gross_cash'] ?? 0);
            $netC = (float) ($prop['confirmed_net_cash'] ?? 0);
            $exp = (float) ($prop['paid_expenses'] ?? 0);
            $surp = (float) ($prop['cash_surplus'] ?? 0);
            $own = (float) ($prop['owner_share'] ?? 0);
            $comp = (float) ($prop['total_company_entitlement'] ?? 0);
            $nc = (float) ($prop['net_contribution'] ?? 0);

            $sumGross += $gross; $sumNetCash += $netC; $sumExp += $exp;
            $sumSurplus += $surp; $sumOwner += $own; $sumComp += $comp; $sumNetContrib += $nc;

            $ws2Rows[] = '<Row>'
                . $stringCell($prop['property_code'] ?? '')
                . $stringCell($prop['property_name'] ?? '')
                . $stringCell($prop['agreement_note'] ?? 'Belum Diatur')
                . $numberCell($gross)
                . $numberCell($netC)
                . $numberCell($exp)
                . $numberCell($surp)
                . $numberCell($own)
                . $numberCell($comp)
                . $numberCell($nc)
                . '</Row>';
        }

        $ws2Rows[] = '<Row>'
            . $stringCell('TOTAL KONSOLIDASI', 'Total')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . $numberCell($sumGross, 'CurrencyTotal')
            . $numberCell($sumNetCash, 'CurrencyTotal')
            . $numberCell($sumExp, 'CurrencyTotal')
            . $numberCell($sumSurplus, 'CurrencyTotal')
            . $numberCell($sumOwner, 'CurrencyTotal')
            . $numberCell($sumComp, 'CurrencyTotal')
            . $numberCell($sumNetContrib, 'CurrencyTotal')
            . '</Row>';

        // 3. WORKSHEET: Arus Kas & Rekening
        $ws3Rows = [
            '<Row>' . $stringCell('REKAPITULASI SALDO REKENING & ARUS KAS IAS 7', 'Title') . '</Row>',
            $emptyRow,
            '<Row>' . $stringCell('1. SALDO REKENING KAS / BANK TERVERIFIKASI (AS-OF SAAT INI)', 'Section') . '</Row>',
            '<Row>'
                . $stringCell('Kode Rekening', 'Header')
                . $stringCell('Nama Rekening', 'Header')
                . $stringCell('Jenis Akun', 'Header')
                . $stringCell('Mata Uang', 'Header')
                . $stringCell('Tanggal Cutover UTC', 'Header')
                . $stringCell('Saldo Terverifikasi (IDR)', 'Header')
                . '</Row>',
        ];

        $sumBalance = 0;
        foreach ($accounts as $acc) {
            $bal = $acc['balance'] !== null ? (float) $acc['balance'] : 0.0;
            $sumBalance += $bal;
            $ws3Rows[] = '<Row>'
                . $stringCell($acc['code'] ?? '')
                . $stringCell($acc['name'] ?? '')
                . $stringCell(strtoupper($acc['account_type'] ?? 'bank'))
                . $stringCell($acc['currency'] ?? 'IDR')
                . $stringCell($acc['cutover_at'] ?? 'Belum cutover')
                . ($acc['balance'] !== null ? $numberCell($bal) : $stringCell('Belum diverifikasi'))
                . '</Row>';
        }

        $ws3Rows[] = '<Row>'
            . $stringCell('TOTAL KAS & BANK TERVERIFIKASI', 'Total')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . $numberCell($sumBalance, 'CurrencyTotal')
            . '</Row>';

        $ws3Rows[] = $emptyRow;
        $ws3Rows[] = '<Row>' . $stringCell('2. LAPORAN ARUS KAS FORMAT IAS 7 (PERIODE BULAN ' . $month . ')', 'Section') . '</Row>';
        $ws3Rows[] = '<Row>'
            . $stringCell('Klasifikasi Arus Kas', 'Header')
            . $stringCell('Kas Masuk (Inflow)', 'Header')
            . $stringCell('Kas Keluar (Outflow)', 'Header')
            . $stringCell('Arus Kas Bersih (Net)', 'Header')
            . '</Row>';

        $flowNames = [
            'operating' => 'Aktivitas Operasi (Operating)',
            'investing' => 'Aktivitas Investasi (Investing)',
            'financing' => 'Aktivitas Pendanaan (Financing)',
        ];
        $sumFlowIn = 0; $sumFlowOut = 0; $sumFlowNet = 0;
        $flowsByKey = [];
        foreach ($externalFlow as $ef) {
            $flowsByKey[$ef['classification']] = $ef;
        }

        foreach ($flowNames as $key => $title) {
            $item = $flowsByKey[$key] ?? ['cash_in' => 0, 'cash_out' => 0, 'net' => 0];
            $in = (float) $item['cash_in'];
            $out = (float) $item['cash_out'];
            $net = (float) $item['net'];
            $sumFlowIn += $in; $sumFlowOut += $out; $sumFlowNet += $net;

            $ws3Rows[] = '<Row>'
                . $stringCell($title)
                . $numberCell($in)
                . $numberCell($out)
                . $numberCell($net)
                . '</Row>';
        }

        $ws3Rows[] = '<Row>'
            . $stringCell('KENAIKAN / (PENURUNAN) KAS BERSIH EKSTERNAL', 'Total')
            . $numberCell($sumFlowIn, 'CurrencyTotal')
            . $numberCell($sumFlowOut, 'CurrencyTotal')
            . $numberCell($sumFlowNet, 'CurrencyTotal')
            . '</Row>';

        $ws3Rows[] = $emptyRow;
        $ws3Rows[] = '<Row>' . $stringCell('3. STATUS REKONSILIASI BANK & SALDO KORAN (BULAN ' . $month . ')', 'Section') . '</Row>';
        $ws3Rows[] = '<Row>'
            . $stringCell('Kode Akun', 'Header')
            . $stringCell('Nama Rekening', 'Header')
            . $stringCell('Saldo Buku Ledger (IDR)', 'Header')
            . $stringCell('Saldo Rek. Koran (IDR)', 'Header')
            . $stringCell('Selisih (Variance)', 'Header')
            . $stringCell('Status', 'Header')
            . $stringCell('Tanggal Koran', 'Header')
            . $stringCell('Catatan / Dokumen Ref', 'Header')
            . '</Row>';

        foreach ($reconciliationData['accounts'] as $rAcc) {
            $ws3Rows[] = '<Row>'
                . $stringCell($rAcc['code'])
                . $stringCell($rAcc['name'])
                . $numberCell($rAcc['ledger_balance'])
                . ($rAcc['statement_balance'] !== null ? $numberCell($rAcc['statement_balance']) : $stringCell('-'))
                . ($rAcc['discrepancy'] !== null ? $numberCell($rAcc['discrepancy']) : $stringCell('-'))
                . $stringCell(strtoupper($rAcc['status']))
                . $stringCell($rAcc['statement_date'] ?? '-')
                . $stringCell($rAcc['notes'] ?: ($rAcc['reference_document'] ?: '-'))
                . '</Row>';
        }

        // 4. WORKSHEET: Mutasi Kas
        $ws4Rows = [
            '<Row>' . $stringCell('MUTASI LEDGER KAS RIIL - BULAN ' . $month, 'Title') . '</Row>',
            $emptyRow,
            '<Row>'
                . $stringCell('Tanggal Efektif (UTC)', 'Header')
                . $stringCell('ID Mutasi', 'Header')
                . $stringCell('Deskripsi Transaksi', 'Header')
                . $stringCell('Properti Terkait', 'Header')
                . $stringCell('Klasifikasi IAS 7', 'Header')
                . $stringCell('Jenis Transaksi', 'Header')
                . $stringCell('Rekening Terlibat', 'Header')
                . $stringCell('Kas Masuk (IDR)', 'Header')
                . $stringCell('Kas Keluar (IDR)', 'Header')
                . $stringCell('Diposting Oleh', 'Header')
                . $stringCell('Event Key / Referensi', 'Header')
                . '</Row>',
        ];

        $sumTxIn = 0; $sumTxOut = 0;
        foreach ($transactionsData['items'] as $tx) {
            $in = (float) ($tx['cash_in'] ?? 0);
            $out = (float) ($tx['cash_out'] ?? 0);
            $sumTxIn += $in; $sumTxOut += $out;

            $accList = [];
            foreach ($tx['entries'] ?? [] as $e) {
                $accList[] = ($e['account_code'] ?? '') . ' (' . ($e['amount'] ?? '') . ')';
            }
            $accStr = implode('; ', $accList);

            $ws4Rows[] = '<Row>'
                . $stringCell($tx['effective_at'] ?? '')
                . $stringCell('#' . ($tx['id'] ?? ''))
                . $stringCell($tx['description'] ?? '')
                . $stringCell($tx['property_name'] ?? 'Holding')
                . $stringCell(strtoupper($tx['classification'] ?? ''))
                . $stringCell(strtoupper($tx['kind'] ?? ''))
                . $stringCell($accStr)
                . $numberCell($in)
                . $numberCell($out)
                . $stringCell($tx['posted_by_username'] ?? '')
                . $stringCell($tx['event_key'] ?? '')
                . '</Row>';
        }

        $ws4Rows[] = '<Row>'
            . $stringCell('TOTAL MUTASI KAS BULAN INI', 'Total')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . $numberCell($sumTxIn, 'CurrencyTotal')
            . $numberCell($sumTxOut, 'CurrencyTotal')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . '</Row>';

        // 5. WORKSHEET: Kewajiban Keuangan
        $ws5Rows = [
            '<Row>' . $stringCell('DAFTAR KEWAJIBAN & TAGIHAN HOLDING', 'Title') . '</Row>',
            $emptyRow,
            '<Row>'
                . $stringCell('ID', 'Header')
                . $stringCell('Properti Terkait', 'Header')
                . $stringCell('Jenis Kewajiban', 'Header')
                . $stringCell('Keterangan', 'Header')
                . $stringCell('Referensi Sumber', 'Header')
                . $stringCell('Nominal Tagihan (IDR)', 'Header')
                . $stringCell('Terbayar (IDR)', 'Header')
                . $stringCell('Sisa Kewajiban (IDR)', 'Header')
                . $stringCell('Jatuh Tempo', 'Header')
                . $stringCell('Status', 'Header')
                . $stringCell('Tanggal Terbit UTC', 'Header')
                . '</Row>',
        ];

        $sumOblAmount = 0; $sumOblSettled = 0; $sumOblRemaining = 0;
        foreach ($obligations as $obl) {
            $amt = (float) ($obl['amount'] ?? 0);
            $set = (float) ($obl['settled_amount'] ?? 0);
            $rem = (float) ($obl['remaining_amount'] ?? 0);
            $sumOblAmount += $amt; $sumOblSettled += $set; $sumOblRemaining += $rem;

            $ws5Rows[] = '<Row>'
                . $stringCell('#' . ($obl['id'] ?? ''))
                . $stringCell($obl['property_name'] ?? 'Holding')
                . $stringCell(strtoupper($obl['obligation_type'] ?? ''))
                . $stringCell($obl['description'] ?? '')
                . $stringCell(($obl['reference_type'] ?? '') . ':' . ($obl['reference_id'] ?? ''))
                . $numberCell($amt)
                . $numberCell($set)
                . $numberCell($rem)
                . $stringCell($obl['due_date'] ?? '-')
                . $stringCell(strtoupper($obl['status'] ?? ''))
                . $stringCell($obl['created_at'] ?? '')
                . '</Row>';
        }

        $ws5Rows[] = '<Row>'
            . $stringCell('TOTAL KEWAJIBAN', 'Total')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . $numberCell($sumOblAmount, 'CurrencyTotal')
            . $numberCell($sumOblSettled, 'CurrencyTotal')
            . $numberCell($sumOblRemaining, 'CurrencyTotal')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . $stringCell('', 'Total')
            . '</Row>';

        // Build SpreadsheetML XML Document
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<?mso-application progid="Excel.Sheet"?>' . "\n"
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n"
            . ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n"
            . ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n"
            . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n"
            . ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n"
            . '<Styles>' . "\n"
            . ' <Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11"/><Interior/><NumberFormat/><Protection/></Style>' . "\n"
            . ' <Style ss:ID="Title"><Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1" ss:Color="#0F172A"/></Style>' . "\n"
            . ' <Style ss:ID="SubTitle"><Font ss:FontName="Calibri" ss:Size="10" ss:Color="#64748B"/></Style>' . "\n"
            . ' <Style ss:ID="Header"><Font ss:FontName="Calibri" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1E293B" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/></Style>' . "\n"
            . ' <Style ss:ID="Section"><Font ss:FontName="Calibri" ss:Bold="1" ss:Color="#0F172A"/><Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/></Style>' . "\n"
            . ' <Style ss:ID="Total"><Font ss:FontName="Calibri" ss:Bold="1" ss:Color="#0F172A"/><Interior ss:Color="#E2E8F0" ss:Pattern="Solid"/></Style>' . "\n"
            . ' <Style ss:ID="Currency"><NumberFormat ss:Format="#,##0"/></Style>' . "\n"
            . ' <Style ss:ID="CurrencyTotal"><Font ss:FontName="Calibri" ss:Bold="1" ss:Color="#0F172A"/><Interior ss:Color="#E2E8F0" ss:Pattern="Solid"/><NumberFormat ss:Format="#,##0"/></Style>' . "\n"
            . '</Styles>' . "\n"
            . '<Worksheet ss:Name="Ringkasan &amp; Jembatan"><Table>'
            . '<Column ss:Width="260"/><Column ss:Width="160"/><Column ss:Width="320"/>'
            . implode('', $ws1Rows)
            . '</Table></Worksheet>' . "\n"
            . '<Worksheet ss:Name="Kontribusi Properti"><Table>'
            . '<Column ss:Width="90"/><Column ss:Width="180"/><Column ss:Width="140"/><Column ss:Width="130"/><Column ss:Width="130"/><Column ss:Width="130"/><Column ss:Width="130"/><Column ss:Width="130"/><Column ss:Width="130"/><Column ss:Width="110"/>'
            . implode('', $ws2Rows)
            . '</Table></Worksheet>' . "\n"
            . '<Worksheet ss:Name="Arus Kas &amp; Rekening"><Table>'
            . '<Column ss:Width="110"/><Column ss:Width="200"/><Column ss:Width="100"/><Column ss:Width="80"/><Column ss:Width="160"/><Column ss:Width="160"/>'
            . implode('', $ws3Rows)
            . '</Table></Worksheet>' . "\n"
            . '<Worksheet ss:Name="Mutasi Kas"><Table>'
            . '<Column ss:Width="150"/><Column ss:Width="80"/><Column ss:Width="240"/><Column ss:Width="160"/><Column ss:Width="120"/><Column ss:Width="120"/><Column ss:Width="180"/><Column ss:Width="130"/><Column ss:Width="130"/><Column ss:Width="120"/><Column ss:Width="180"/>'
            . implode('', $ws4Rows)
            . '</Table></Worksheet>' . "\n"
            . '<Worksheet ss:Name="Kewajiban Keuangan"><Table>'
            . '<Column ss:Width="60"/><Column ss:Width="160"/><Column ss:Width="140"/><Column ss:Width="240"/><Column ss:Width="140"/><Column ss:Width="130"/><Column ss:Width="130"/><Column ss:Width="130"/><Column ss:Width="110"/><Column ss:Width="100"/><Column ss:Width="150"/>'
            . implode('', $ws5Rows)
            . '</Table></Worksheet>' . "\n"
            . '</Workbook>';

        $safeFilename = 'holding-finance-' . $company . '-' . $month . '.xls';

        return $this->response
            ->download($safeFilename, $content)
            ->setContentType('application/vnd.ms-excel', 'UTF-8');
    }

    private function input(string $name): string
    {
        $value = $this->request->getPost($name);
        if (! is_string($value)) {
            throw new \RuntimeException('Input ' . $name . ' tidak valid.');
        }
        return trim($value);
    }

    private function id(string $name): int
    {
        $id = filter_var($this->input($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new \RuntimeException('ID tidak valid.');
        }
        return $id;
    }

    private function cleanAmount(string $raw): string
    {
        $clean = trim(str_ireplace(['Rp', ' '], '', $raw));
        if ($clean === '') {
            return '0.00';
        }
        if (strpos($clean, ',') !== false) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
            $clean = preg_replace('/[^0-9.]/', '', $clean);
            return $clean !== '' ? $clean : '0.00';
        }
        if (strpos($clean, '.') !== false) {
            if (substr_count($clean, '.') > 1) {
                $clean = str_replace('.', '', $clean);
            } else {
                $parts = explode('.', $clean);
                if (isset($parts[1]) && strlen($parts[1]) === 3) {
                    $clean = str_replace('.', '', $clean);
                }
            }
        }
        $clean = preg_replace('/[^0-9.]/', '', $clean);
        return $clean !== '' ? $clean : '0.00';
    }

    private function ensureConsolidatedAccount(int $user, int $company): int
    {
        $db = db_connect();
        $existing = $db->table('cash_accounts')
            ->where('company_id', $company)
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->get()->getRowArray();

        if ($existing) {
            $accId = (int) $existing['id'];
            $opening = $db->table('cash_opening_balances')
                ->where('company_id', $company)
                ->where('account_id', $accId)
                ->get()->getRowArray();

            if (! $opening) {
                $db->table('cash_opening_balances')->insert([
                    'company_id' => $company,
                    'account_id' => $accId,
                    'effective_at' => '2026-01-01 00:00:00',
                    'amount' => '0.00',
                    'verified_by' => $user,
                    'reason' => 'Saldo awal kas konsolidasi default',
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }

            return $accId;
        }

        $db->table('cash_accounts')->insert([
            'company_id' => $company,
            'property_id' => null,
            'code' => 'KAS_HOLDING',
            'name' => 'Kas & Bank Konsolidasi Holding',
            'account_type' => 'bank',
            'currency' => 'IDR',
            'is_active' => 1,
            'created_by' => $user,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $accountId = (int) $db->insertID();

        $db->table('cash_opening_balances')->insert([
            'company_id' => $company,
            'account_id' => $accountId,
            'effective_at' => '2026-01-01 00:00:00',
            'amount' => '0.00',
            'verified_by' => $user,
            'reason' => 'Saldo awal kas konsolidasi default',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $db->table('company_finance_audit')->insert([
            'company_id' => $company,
            'user_id' => $user,
            'action' => 'account_created',
            'reference' => (string) $accountId,
            'details' => json_encode(['code' => 'KAS_HOLDING', 'name' => 'Kas & Bank Konsolidasi Holding', 'auto_pool' => true], JSON_THROW_ON_ERROR),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $accountId;
    }

    private function resolveAccountId(int $user, int $company): int
    {
        $raw = $this->request->getPost('account_id');
        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id !== false) {
            return $id;
        }
        return $this->ensureConsolidatedAccount($user, $company);
    }

    public function cash()
    {
        if (! RoleAccess::allows((string) session('role'), 'company_finance')) {
            return $this->response->setStatusCode(403)->setBody('Akses keuangan perusahaan hanya untuk role Manager dan Finance.');
        }

        $user = (int) session('userId');
        $company = CompanyFinanceContext::resolve($user, $this->request->getGet('company'));
        if ($company === null || ! CompanyFinanceAccess::allows($user, $company, 'post')) {
            return $this->response->setStatusCode(403)->setBody('Akses holding ditolak.');
        }

        $db = db_connect();
        $service = new CompanyCashIntegration($db);
        $entitlementService = new CompanyEntitlementService($db);
        $ledger = new CashLedgerService($db);
        $reconciliationService = new CompanyReconciliationService($db, $ledger, $entitlementService);

        $tab = $this->request->getPost('tab') ?: 'summary';
        $month = $this->request->getPost('month') ?: gmdate('Y-m');

        try {
            $operation = $this->input('operation');
            $approverOps = [
                'create_account', 'opening', 'refund', 'opening_link', 'approve_overhead',
                'transfer', 'capital', 'loan_received', 'loan_principal', 'bank_fee', 'management_salary', 'asset_purchase',
                'create_agreement', 'finalize_obligation', 'cancel_obligation', 'allocate_overhead',
                'set_period',
            ];

            if (in_array($operation, $approverOps, true) && ! CompanyFinanceAccess::allows($user, $company, 'approve')) {
                return $this->response->setStatusCode(403)->setBody('Izin approver holding diperlukan.');
            }

            if ($this->input('confirmed') !== '1') {
                throw new \RuntimeException('Konfirmasi tindakan wajib.');
            }

            // Operations dispatcher
            if ($operation === 'create_account') {
                $ledger->createAccount($user, $company, $this->input('code'), $this->input('name'), $this->input('account_type'));
            } elseif ($operation === 'approve_overhead') {
                $service->approveOverhead($user, $company, $this->id('source_id'));
            } elseif ($operation === 'create_overhead') {
                $service->createOverhead($user, $company, $this->input('event_key'), $this->input('description'), $this->input('category'), $this->input('amount'), $this->input('date_incurred'));
            } elseif ($operation === 'create_agreement') {
                $effectiveTo = trim((string) $this->request->getPost('effective_to')) ?: null;
                $entitlementService->createAgreement(
                    $user,
                    $company,
                    $this->id('property_id'),
                    $this->input('agreement_type'),
                    $this->input('calculation_base'),
                    $this->input('company_share_pct'),
                    $this->input('owner_share_pct'),
                    $this->input('fixed_fee_monthly'),
                    $this->input('effective_from'),
                    $effectiveTo,
                    trim((string) $this->request->getPost('terms_note'))
                );
            } elseif ($operation === 'create_obligation') {
                $propId = $this->request->getPost('property_id') ? (int) $this->request->getPost('property_id') : null;
                $m = trim((string) $this->request->getPost('month')) ?: null;
                $due = trim((string) $this->request->getPost('due_date')) ?: null;
                $status = trim((string) $this->request->getPost('status')) ?: 'estimated';
                $entitlementService->createObligation(
                    $user,
                    $company,
                    $propId,
                    $this->input('obligation_type'),
                    $this->input('reference_type'),
                    $this->input('reference_id'),
                    $m,
                    $this->input('description'),
                    $this->input('amount'),
                    $status,
                    $due
                );
            } elseif ($operation === 'finalize_obligation') {
                $entitlementService->finalizeObligation($user, $company, $this->id('obligation_id'));
            } elseif ($operation === 'cancel_obligation') {
                $entitlementService->cancelObligation($user, $company, $this->id('obligation_id'), $this->input('reason'));
            } elseif ($operation === 'allocate_overhead') {
                $entitlementService->allocateOverhead(
                    $user,
                    $company,
                    $this->id('expense_id'),
                    $this->id('property_id'),
                    $this->input('allocation_pct'),
                    $this->input('amount'),
                    $this->input('month')
                );
            } elseif ($operation === 'reconcile_bank') {
                if (! CompanyFinanceAccess::allows($user, $company, 'reconcile')) {
                    throw new \RuntimeException('Izin rekonsiliasi holding diperlukan.');
                }
                $reconciliationService->saveReconciliation(
                    $user,
                    $company,
                    $this->resolveAccountId($user, $company),
                    $this->input('period_month'),
                    $this->input('statement_date'),
                    $this->input('statement_balance'),
                    $this->request->getPost('notes'),
                    $this->request->getPost('reference_document')
                );
            } elseif ($operation === 'set_period') {
                if (! CompanyFinanceAccess::allows($user, $company, 'close')) {
                    throw new \RuntimeException('Izin penguncian periode (close) holding diperlukan.');
                }
                $periodMonth = $this->input('period_month');
                $periodStatus = $this->input('period_status');
                $reason = $this->input('reason');
                if ($periodStatus === 'closed') {
                    $reconciliationService->closePeriodWithSnapshot($user, $company, $periodMonth, $reason);
                } else {
                    $reconciliationService->reopenPeriod($user, $company, $periodMonth, $reason);
                }
            } elseif ($operation === 'receipt_all') {
                $account = $this->resolveAccountId($user, $company);
                $previewData = $service->preview($user, $company);
                $confirmedCount = 0;
                $totalConfirmedAmount = 0.0;
                $nowUtc = gmdate('Y-m-d H:i:s');
                foreach ($previewData['payments'] as $p) {
                    $unmapped = (float) $p['amount'] - (float) ($p['mapped'] ?? 0);
                    if ($unmapped > 0) {
                        $pAt = ! empty($p['payment_date']) ? str_replace('T', ' ', (string) $p['payment_date']) : $nowUtc;
                        if (strlen($pAt) === 10) {
                            $pAt .= ' 12:00:00';
                        } elseif (strlen($pAt) === 16) {
                            $pAt .= ':00';
                        }
                        if ($pAt > $nowUtc) {
                            $pAt = $nowUtc;
                        }
                        $pKey = bin2hex(random_bytes(24));
                        $pNote = 'Konfirmasi penerimaan kas booking #' . ($p['order_id'] ?? $p['id']);
                        $service->receipt(
                            $user,
                            $company,
                            (int) $p['id'],
                            $account,
                            (string) $unmapped,
                            '0.00',
                            $pAt,
                            $pKey,
                            $pNote
                        );
                        $confirmedCount++;
                        $totalConfirmedAmount += $unmapped;
                    }
                }
                $subParam = $this->request->getPost('sub') ? '&sub=' . urlencode((string) $this->request->getPost('sub')) : '';
                return redirect()->to(site_url('finance/company') . '?company=' . $company . '&tab=' . urlencode($tab) . $subParam . '&month=' . urlencode($month))
                    ->with('success', $confirmedCount > 0
                        ? "Berhasil mengonfirmasi {$confirmedCount} penerimaan kas tamu senilai total Rp" . number_format($totalConfirmedAmount, 2, '.', ',') . ' ke kas holding.'
                        : 'Semua pembayaran tamu dalam daftar sudah terkonfirmasi.');
            } else {
                $account = $this->resolveAccountId($user, $company);
                $amount = $this->cleanAmount($this->input('amount'));
                $at = str_replace('T', ' ', $this->input('effective_at'));
                if (strlen($at) === 10) {
                    $at .= ' ' . gmdate('H:i:s');
                } elseif (strlen($at) === 16) {
                    $at .= ':00';
                }
                $nowUtc = gmdate('Y-m-d H:i:s');
                if ($at > $nowUtc) {
                    $at = $nowUtc;
                }
                $note = $this->input('note');
                $key = trim((string) $this->request->getPost('event_key')) ?: bin2hex(random_bytes(16));

                $destId = null;
                if ($operation === 'transfer') {
                    $destId = $this->id('destination_id');
                    if ($destId === $account) {
                        throw new \RuntimeException('Rekening asal dan rekening tujuan transfer tidak boleh sama.');
                    }
                }

                match ($operation) {
                    'opening' => $at <= $nowUtc
                        ? $ledger->opening($user, $company, $account, $at, $amount, $note)
                        : throw new \RuntimeException('Saldo awal tidak boleh di masa depan.'),
                    'receipt' => $service->receipt($user, $company, $this->id('source_id'), $account, $amount, $this->input('fee'), $at, $key, $note),
                    'refund' => $service->refund($user, $company, $this->id('source_id'), $account, $amount, $at, $key, $note),
                    'expense', 'overhead' => $service->settleExpense($user, $company, $this->id('source_id'), $account, $amount, $at, $key, $note, $operation === 'overhead'),
                    'settle_obligation' => $entitlementService->settleObligation($user, $company, $this->id('obligation_id'), $account, $amount, $at, $key, $note),
                    'opening_link' => $service->linkOpeningPayment($user, $company, $this->id('source_id'), $account, $amount, $this->input('fee'), $at, $note),
                    'transfer', 'capital', 'loan_received', 'loan_principal', 'bank_fee', 'management_salary', 'asset_purchase' => $service->manual(
                        $user,
                        $company,
                        $operation,
                        $account,
                        $operation === 'transfer' ? $destId : null,
                        $amount,
                        $at,
                        $key,
                        $note
                    ),
                    default => throw new \RuntimeException('Operasi tidak dikenal: ' . $operation),
                };
            }

            $subParam = $this->request->getPost('sub') ? '&sub=' . urlencode((string) $this->request->getPost('sub')) : '';
            return redirect()->to(site_url('finance/company') . '?company=' . $company . '&tab=' . urlencode($tab) . $subParam . '&month=' . urlencode($month))
                ->with('success', 'Tindakan keuangan holding berhasil diproses.');
        } catch (\Throwable $error) {
            log_message('error', 'Company finance operation failed: {message}', ['message' => $error->getMessage()]);
            $subParam = $this->request->getPost('sub') ? '&sub=' . urlencode((string) $this->request->getPost('sub')) : '';
            return redirect()->to(site_url('finance/company') . '?company=' . $company . '&tab=' . urlencode($tab) . $subParam . '&month=' . urlencode($month))
                ->with('error', $error instanceof \RuntimeException ? $error->getMessage() : 'Transaksi ditolak. Periksa kelengkapan input dan otoritas.');
        }
    }
}
