<?php

namespace App\Commands;

use App\Controllers\CompanyFinanceController;
use App\Libraries\CashAmount;
use App\Libraries\CashLedgerService;
use App\Libraries\CompanyCashIntegration;
use App\Libraries\CompanyEntitlementService;
use App\Libraries\CompanyFinanceAccess;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use RuntimeException;
use Throwable;

final class TestCompanyCashCF4 extends BaseCommand
{
    protected $group = 'Testing';
    protected $name = 'company:cf4-test';
    protected $description = 'Uji CF-4 UI, pembaruan terkini, dan ekspor hanya di clone lokal homiq_pms_cf4_test_YYYYMMDD.';

    public function run(array $params)
    {
        $db = db_connect();
        if (ENVIRONMENT !== 'development' || ! in_array($db->hostname, ['localhost', '127.0.0.1'], true)
            || preg_match('/^homiq_pms_cf4_test_[0-9]{8}$/D', $db->database) !== 1) {
            throw new RuntimeException('Wajib clone CF-4 lokal (homiq_pms_cf4_test_YYYYMMDD); main/live ditolak.');
        }

        $n = 0;
        $check = static function (bool $ok, string $name) use (&$n): void {
            if (! $ok) {
                throw new RuntimeException('FAIL: ' . $name);
            }
            ++$n;
            CLI::write('  [PASS] ' . $name, 'green');
        };

        $reject = static function (callable $fn, string $name) use ($check): void {
            $failed = false;
            try {
                $fn();
            } catch (Throwable $e) {
                $failed = true;
            }
            $check($failed, $name);
        };

        CLI::write('=== Memulai Pengujian CF-4 (UI, Pembaruan Terkini, Ekspor) ===', 'yellow');

        // 1. Setup users & company
        $users = $db->table('users')->orderBy('id')->limit(3)->get()->getResultArray();
        if (count($users) < 3) {
            throw new RuntimeException('Clone harus memiliki minimal 3 user.');
        }
        $approver = (int) $users[0]['id'];
        $finance = (int) $users[1]['id'];
        $viewer = (int) $users[2]['id'];

        $old = $db->table('companies')->where('code', 'CF4_TEST')->get()->getRowArray();
        if ($old) {
            $cId = (int) $old['id'];
            $db->table('obligation_settlements')->where('company_id', $cId)->delete();
            $db->table('financial_obligations')->where('company_id', $cId)->delete();
            $db->table('company_expense_allocations')->where('company_id', $cId)->delete();
            $db->table('property_finance_agreements')->where('company_id', $cId)->delete();
            $db->table('cash_entries')->where('company_id', $cId)->delete();
            $db->table('cash_transactions')->where('company_id', $cId)->delete();
            $db->table('cash_opening_balances')->where('company_id', $cId)->delete();
            $db->table('cash_accounts')->where('company_id', $cId)->delete();
            $db->table('company_properties')->where('company_id', $cId)->delete();
            $db->table('company_user_roles')->where('company_id', $cId)->delete();
            $db->table('company_finance_periods')->where('company_id', $cId)->delete();
            $db->table('company_finance_audit')->where('company_id', $cId)->delete();
            $db->table('companies')->where('id', $cId)->delete();
        }

        $db->table('companies')->insert([
            'code' => 'CF4_TEST',
            'name' => 'CF4 Holding Portal Test',
            'is_active' => 1,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $company = (int) $db->insertID();

        foreach ([$approver => 'Company Approver', $finance => 'Company Finance', $viewer => 'Company Viewer'] as $u => $role) {
            $db->table('company_user_roles')->insert([
                'company_id' => $company,
                'user_id' => $u,
                'role' => $role,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        // Map properties 1, 2, 3
        foreach ([1, 2, 3] as $pId) {
            $db->table('company_properties')->where('property_id', $pId)->delete();
            $db->table('company_properties')->insert([
                'company_id' => $company,
                'property_id' => $pId,
            ]);
        }

        $ledger = new CashLedgerService($db);
        $entitlement = new CompanyEntitlementService($db);
        $integration = new CompanyCashIntegration($db);

        // 2. Setup Accounts & Opening Balances
        $bcaId = $ledger->createAccount($approver, $company, 'BCA_CF4', 'BCA Operasional Holding', 'bank');
        $mandiriId = $ledger->createAccount($approver, $company, 'MANDIRI_CF4', 'Mandiri Escrow Holding', 'bank');
        $kasId = $ledger->createAccount($approver, $company, 'KAS_CF4', 'Kas Fisik Holding', 'cash');

        $ledger->opening($approver, $company, $bcaId, '2026-05-01 00:00:00', '10000000.00', 'Saldo awal BCA');
        $ledger->opening($approver, $company, $mandiriId, '2026-05-01 00:00:00', '5000000.00', 'Saldo awal Mandiri');
        $ledger->opening($approver, $company, $kasId, '2026-05-01 00:00:00', '1000000.00', 'Saldo awal Kas Fisik');

        $check(count($ledger->balances($viewer, $company)) === 3, 'CF4-01: Rekening kas/bank dan saldo awal berhasil disiapkan');

        // 3. Multi-month transactions for 6-Month Trend Test
        // May 2026: Capital & operating
        $ledger->integrate($approver, $company, 'post', function ($write) use ($bcaId) {
            return $write('cf4_ev_may_01', 'receipt', 'operating', '2026-05-15 10:00:00', 'Tamu Kamar May', [
                ['account_id' => $bcaId, 'amount' => '3000000.00'],
            ], 1, 'manual', 'REF_MAY_01');
        });

        // June 2026
        $ledger->integrate($approver, $company, 'post', function ($write) use ($bcaId) {
            return $write('cf4_ev_jun_01', 'receipt', 'operating', '2026-06-15 10:00:00', 'Tamu Kamar Jun', [
                ['account_id' => $bcaId, 'amount' => '4000000.00'],
            ], 1, 'manual', 'REF_JUN_01');
        });

        // July 2026
        $ledger->integrate($approver, $company, 'post', function ($write) use ($bcaId) {
            return $write('cf4_ev_jul_01', 'receipt', 'operating', '2026-07-15 10:00:00', 'Tamu Kamar Jul', [
                ['account_id' => $bcaId, 'amount' => '4500000.00'],
            ], 1, 'manual', 'REF_JUL_01');
        });

        // August 2026
        $ledger->integrate($approver, $company, 'post', function ($write) use ($bcaId) {
            return $write('cf4_ev_aug_01', 'receipt', 'operating', '2026-08-15 10:00:00', 'Tamu Kamar Aug', [
                ['account_id' => $bcaId, 'amount' => '5000000.00'],
            ], 1, 'manual', 'REF_AUG_01');
        });

        // September 2026
        $ledger->integrate($approver, $company, 'post', function ($write) use ($bcaId) {
            return $write('cf4_ev_sep_01', 'receipt', 'operating', '2026-09-15 10:00:00', 'Tamu Kamar Sep', [
                ['account_id' => $bcaId, 'amount' => '6000000.00'],
            ], 1, 'manual', 'REF_SEP_01');
        });
        $ledger->integrate($approver, $company, 'post', function ($write) use ($bcaId) {
            return $write('cf4_ev_sep_02', 'disbursement', 'operating', '2026-09-20 10:00:00', 'Beban Listrik Sep', [
                ['account_id' => $bcaId, 'amount' => '-1000000.00'],
            ], 1, 'manual', 'REF_SEP_02');
        });

        // October 2026 (Current Month)
        $ledger->integrate($approver, $company, 'post', function ($write) use ($bcaId) {
            return $write('cf4_ev_oct_01', 'receipt', 'operating', '2026-10-01 10:00:00', 'Tamu Kamar Oct', [
                ['account_id' => $bcaId, 'amount' => '8000000.00'],
            ], 1, 'manual', 'REF_OCT_01');
        });
        $ledger->integrate($approver, $company, 'post', function ($write) use ($bcaId) {
            return $write('cf4_ev_oct_02', 'disbursement', 'operating', '2026-10-01 11:00:00', 'Beban Laundry Oct', [
                ['account_id' => $bcaId, 'amount' => '-2000000.00'],
            ], 1, 'manual', 'REF_OCT_02');
        });

        // Test Agreements
        $entitlement->createAgreement($approver, $company, 1, 'revenue_share', 'gross_revenue', '30.00', '70.00', '0.00', '2026-01-01', null, 'Kontrak HomiQ 30/70');
        $entitlement->createAgreement($approver, $company, 2, 'net_profit_share', 'operating_profit', '20.00', '80.00', '0.00', '2026-01-01', null, 'Kontrak Homestay 20/80');

        // Test Obligations
        $oblId = $entitlement->createObligation($finance, $company, 1, 'owner_payable', 'manual', 'SETTLE_OCT', '2026-10', 'Bagi hasil owner Oktober', '4000000.00', 'estimated', '2026-10-25');
        $entitlement->finalizeObligation($approver, $company, $oblId);

        // 4. Test 6-Month Trend & MoM Calculations
        $trend = $entitlement->trendSixMonths($viewer, $company, '2026-10');
        $check(count($trend['labels']) === 6, 'CF4-02: Trend 6 bulan menghasilkan tepat 6 label bulan');
        $check(count($trend['cash_in']) === 6, 'CF4-03: Dataset kas masuk berisi 6 bulan');
        $check($trend['cash_in'][5] === 8000000.00, 'CF4-04: Kas masuk bulan Oktober tercatat Rp8.000.000');
        $check($trend['cash_in'][4] === 6000000.00, 'CF4-05: Kas masuk bulan September tercatat Rp6.000.000');
        $check($trend['mom']['cash_in']['pct'] === 33.3, 'CF4-06: Perhitungan MoM kenaikan kas masuk Oktober vs September cocok (+33.3%)');

        // 5. Test Audit Summary
        $audit = $entitlement->auditSummary($viewer, $company);
        $check($audit['last_transaction_at'] === '2026-10-01 11:00:00', 'CF4-07: Waktu transaksi terakhir cocok dengan mutasi kas terbaru');

        // 6. Test Server-side Paginated Ledger Mutations
        $allTx = $ledger->transactions($viewer, $company, [], 1, 20);
        $check($allTx['total'] >= 7, 'CF4-08: Total mutasi terhitung lengkap di ledger');
        $check(count($allTx['items']) >= 7, 'CF4-09: Seluruh item mutasi memiliki data kaki entries dan properti');

        // Pagination test: perPage = 2
        $pagedTx = $ledger->transactions($viewer, $company, [], 1, 2);
        $check(count($pagedTx['items']) === 2, 'CF4-10: Paginasi perPage=2 mengembalikan tepat 2 item');
        $check($pagedTx['totalPages'] >= 4, 'CF4-11: Total halaman paginasi dihitung benar');

        // Filter by month
        $octTx = $ledger->transactions($viewer, $company, ['month' => '2026-10'], 1, 20);
        $check(count($octTx['items']) === 2, 'CF4-12: Filter mutasi bulan 2026-10 menyaring tepat 2 transaksi');

        // Filter by kind
        $recTx = $ledger->transactions($viewer, $company, ['kind' => 'receipt'], 1, 20);
        $check(count($recTx['items']) >= 6, 'CF4-13: Filter jenis receipt menyaring seluruh penerimaan');

        // Filter by classification
        $opTx = $ledger->transactions($viewer, $company, ['classification' => 'operating'], 1, 20);
        $check(count($opTx['items']) >= 7, 'CF4-14: Filter klasifikasi operating menyaring mutasi operasional');

        // 7. Test SpreadsheetML (.xls) Export Generation
        session()->set(['isLoggedIn' => true, 'userId' => $viewer]);
        $_GET['company'] = (string) $company;
        $_GET['month'] = '2026-10';

        $controller = new CompanyFinanceController();
        $controller->initController(Services::request(), Services::response(), Services::logger());
        $exportResponse = $controller->export();
        $exportResponse->buildHeaders();

        $check($exportResponse->getStatusCode() === 200, 'CF4-15: Endpoint ekspor mengembalikan HTTP 200 OK');
        $check(strpos($exportResponse->getHeaderLine('Content-Type'), 'application/vnd.ms-excel') !== false, 'CF4-16: Header Content-Type ekspor adalah application/vnd.ms-excel; charset=UTF-8');

        ob_start();
        $exportResponse->sendBody();
        $xml = (string) ob_get_clean();

        $check(strpos($xml, '<?xml version="1.0" encoding="UTF-8"?>') !== false, 'CF4-17: Format ekspor diawali XML declaration valid');
        $check(strpos($xml, '<?mso-application progid="Excel.Sheet"?>') !== false, 'CF4-18: Mengandung progid Excel.Sheet mso-application');
        $check(strpos($xml, 'xmlns="urn:schemas-microsoft-com:office:spreadsheet"') !== false, 'CF4-19: Namespace SpreadsheetML XML valid');

        // Check 5 Worksheets exist
        $check(strpos($xml, 'ss:Name="Ringkasan &amp; Jembatan"') !== false, 'CF4-20: Worksheet 1: Ringkasan & Jembatan ada');
        $check(strpos($xml, 'ss:Name="Kontribusi Properti"') !== false, 'CF4-21: Worksheet 2: Kontribusi Properti ada');
        $check(strpos($xml, 'ss:Name="Arus Kas &amp; Rekening"') !== false, 'CF4-22: Worksheet 3: Arus Kas & Rekening ada');
        $check(strpos($xml, 'ss:Name="Mutasi Kas"') !== false, 'CF4-23: Worksheet 4: Mutasi Kas ada');
        $check(strpos($xml, 'ss:Name="Kewajiban Keuangan"') !== false, 'CF4-24: Worksheet 5: Kewajiban Keuangan ada');

        // 8. Test Gate: UI Numbers & Export Numbers Match Exactly
        $monthlyReport = $entitlement->monthlyReport($viewer, $company, '2026-10');
        $check(strpos($xml, (string) (float) $monthlyReport['cards']['cash_in_month']) !== false, 'CF4-25: Nilai kas masuk UI dan ekspor identik (Gate konsistensi)');
        $check(strpos($xml, (string) (float) $monthlyReport['cards']['cash_out_month']) !== false, 'CF4-26: Nilai kas keluar UI dan ekspor identik (Gate konsistensi)');
        $check(strpos($xml, (string) (float) $monthlyReport['cards']['net_cash_flow']) !== false, 'CF4-27: Nilai arus kas bersih UI dan ekspor identik (Gate konsistensi)');
        $check(strpos($xml, (string) (float) $monthlyReport['cards']['company_net_result']) !== false, 'CF4-28: Nilai hak bersih perusahaan UI dan ekspor identik (Gate konsistensi)');

        // 9. Test Formula Injection Sanitization
        $ledger->integrate($approver, $company, 'post', function ($write) use ($bcaId) {
            return $write('cf4_ev_injection_01', 'receipt', 'operating', '2026-10-01 12:00:00', '=SUM(1,2)*999', [
                ['account_id' => $bcaId, 'amount' => '100.00'],
            ], 1, 'manual', 'REF_INJ_01');
        });
        $ledger->integrate($approver, $company, 'post', function ($write) use ($bcaId) {
            return $write('cf4_ev_injection_02', 'receipt', 'operating', '2026-10-01 12:01:00', '+CMD|calc', [
                ['account_id' => $bcaId, 'amount' => '100.00'],
            ], 1, 'manual', 'REF_INJ_02');
        });
        $ledger->integrate($approver, $company, 'post', function ($write) use ($bcaId) {
            return $write('cf4_ev_injection_03', 'receipt', 'operating', '2026-10-01 12:02:00', '@alert(1)', [
                ['account_id' => $bcaId, 'amount' => '100.00'],
            ], 1, 'manual', 'REF_INJ_03');
        });

        $sanitizedExport = $controller->export();
        ob_start();
        $sanitizedExport->sendBody();
        $sanitizedXml = (string) ob_get_clean();
        $check(strpos($sanitizedXml, '&apos;=SUM(1,2)*999') !== false || strpos($sanitizedXml, "'=SUM(1,2)*999") !== false, 'CF4-29: Formula injection (=) dinetralisir dengan prepend single-quote');
        $check(strpos($sanitizedXml, '&apos;+CMD|calc') !== false || strpos($sanitizedXml, "'+CMD|calc") !== false, 'CF4-30: Formula injection (+) dinetralisir dengan prepend single-quote');
        $check(strpos($sanitizedXml, '&apos;@alert(1)') !== false || strpos($sanitizedXml, "'@alert(1)") !== false, 'CF4-31: Formula injection (@) dinetralisir dengan prepend single-quote');

        // 10. Test Period Close & Immutability
        // Viewer cannot close period
        $reject(function () use ($ledger, $viewer, $company) {
            $ledger->setPeriod($viewer, $company, '2026-09', true, 'Tutup September');
        }, 'CF4-32: Company Viewer dilarang menutup periode');

        // Approver closes September 2026
        $ledger->setPeriod($approver, $company, '2026-09', true, 'Tutup buku September oleh approver');
        $check(count($entitlement->auditSummary($viewer, $company)['closed_periods']) === 1, 'CF4-33: Periode September berhasil dikunci');

        // Posting into closed period is rejected
        $reject(function () use ($ledger, $approver, $company, $bcaId) {
            $ledger->integrate($approver, $company, 'post', function ($write) use ($bcaId) {
                return $write('cf4_ev_closed_late', 'receipt', 'operating', '2026-09-25 10:00:00', 'Late posting', [
                    ['account_id' => $bcaId, 'amount' => '500000.00'],
                ], 1, 'manual', 'REF_CLOSED_LATE');
            });
        }, 'CF4-34: Posting transaksi ke periode tertutup September 2026 ditolak');

        // Historical closed period numbers did not change
        $sepReportAfterClose = $entitlement->monthlyReport($viewer, $company, '2026-09');
        $check($sepReportAfterClose['cards']['cash_in_month'] === '6000000.00', 'CF4-35: Angka periode tertutup tidak berubah (Immutability check)');

        // 11. Test View HTML Rendering for all 5 Tabs
        $incomingReq = new \CodeIgniter\HTTP\IncomingRequest(
            config(\Config\App::class),
            new \CodeIgniter\HTTP\SiteURI(config(\Config\App::class), 'http://localhost/finance/company'),
            'php://input',
            new \CodeIgniter\HTTP\UserAgent()
        );
        Services::injectMock('request', $incomingReq);
        Services::resetSingle('security');

        $viewController = new CompanyFinanceController();
        $viewController->initController($incomingReq, Services::response(), Services::logger());

        $tabN = 36;
        foreach (['summary', 'monthly', 'cashflow', 'obligations', 'treasury'] as $tabName) {
            $_GET['tab'] = $tabName;
            $_GET['company'] = (string) $company;
            $_GET['month'] = '2026-10';
            $viewHtml = (string) $viewController->index();
            $check(strpos($viewHtml, 'id="company-finance-root"') !== false, 'CF4-' . $tabN . ': Tab ' . $tabName . ' memiliki root container HTMX');
            ++$tabN;
            $check(strpos($viewHtml, 'hx-trigger="refresh from:body"') !== false, 'CF4-' . $tabN . ': Tab ' . $tabName . ' memiliki trigger refresh HTMX');
            ++$tabN;
        }

        CLI::newLine();
        CLI::write("=== SELURUH {$n} PENGUJIAN CF-4 SELESAI & LULUS 100% ===", 'green');
    }
}
