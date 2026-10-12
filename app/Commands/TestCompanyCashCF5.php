<?php

namespace App\Commands;

use App\Controllers\CompanyFinanceController;
use App\Libraries\CashAmount;
use App\Libraries\CashLedgerService;
use App\Libraries\CompanyCashIntegration;
use App\Libraries\CompanyEntitlementService;
use App\Libraries\CompanyFinanceAccess;
use App\Libraries\CompanyReconciliationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use RuntimeException;
use Throwable;

final class TestCompanyCashCF5 extends BaseCommand
{
    protected $group = 'Testing';
    protected $name = 'company:cf5-test';
    protected $description = 'Uji CF-5 Rekonsiliasi, Tutup Buku (Close), Snapshot, dan UAT hanya di clone lokal homiq_pms_cf5_test_YYYYMMDD.';

    public function run(array $params)
    {
        $db = db_connect();
        if (ENVIRONMENT !== 'development' || ! in_array($db->hostname, ['localhost', '127.0.0.1'], true)
            || preg_match('/^homiq_pms_cf5_test_[0-9]{8}$/D', $db->database) !== 1) {
            throw new RuntimeException('Wajib clone CF-5 lokal (homiq_pms_cf5_test_YYYYMMDD); main/live ditolak.');
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

        CLI::write('=== Memulai Pengujian CF-5 (Rekonsiliasi, Tutup Buku, Snapshot, UAT) ===', 'yellow');

        // 1. Setup users & company
        $users = $db->table('users')->orderBy('id')->limit(3)->get()->getResultArray();
        if (count($users) < 3) {
            throw new RuntimeException('Clone harus memiliki minimal 3 user.');
        }
        $approver = (int) $users[0]['id'];
        $finance = (int) $users[1]['id'];
        $viewer = (int) $users[2]['id'];

        $old = $db->table('companies')->where('code', 'CF5_TEST')->get()->getRowArray();
        if ($old) {
            $cId = (int) $old['id'];
            $db->table('company_bank_reconciliations')->where('company_id', $cId)->delete();
            $db->table('company_finance_snapshots')->where('company_id', $cId)->delete();
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
            'code' => 'CF5_TEST',
            'name' => 'CF5 Holding Portal Test',
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
        $entitlement = new CompanyEntitlementService($db, $ledger);
        $integration = new CompanyCashIntegration($db);
        $reconciliation = new CompanyReconciliationService($db, $ledger, $entitlement);

        // 2. Setup Accounts & Opening Balances
        $bcaId = $ledger->createAccount($approver, $company, 'BCA_CF5', 'BCA Operasional Holding', 'bank');
        $mandiriId = $ledger->createAccount($approver, $company, 'MANDIRI_CF5', 'Mandiri Escrow Holding', 'bank');

        $ledger->opening($approver, $company, $bcaId, '2026-08-01 00:00:00', '10000000.00', 'Saldo awal BCA');
        $ledger->opening($approver, $company, $mandiriId, '2026-08-01 00:00:00', '5000000.00', 'Saldo awal Mandiri');

        $check(true, 'CF5-01: Rekening dan saldo awal cutover berhasil disiapkan');

        // 3. Catat transaksi kas bulan September 2026
        // Transaksi 1: Penerimaan operasional BCA Rp4.000.000
        $integration->manual(
            $approver, $company, 'capital', $bcaId, null, '4000000.00',
            '2026-09-10 10:00:00', 'CF5_MAN_01', 'Setoran kas operasional'
        );
        // Transaksi 2: Pengeluaran operasional BCA Rp1.500.000
        $integration->manual(
            $approver, $company, 'bank_fee', $bcaId, null, '1500000.00',
            '2026-09-15 11:00:00', 'CF5_MAN_02', 'Biaya administrasi dan pemeliharaan'
        );
        // Transaksi 3: Transfer BCA -> Mandiri Rp2.000.000
        $integration->manual(
            $approver, $company, 'transfer', $bcaId, $mandiriId, '2000000.00',
            '2026-09-20 12:00:00', 'CF5_MAN_03', 'Transfer likuiditas internal'
        );

        $check(true, 'CF5-02: Transaksi kas September 2026 berhasil dicatat');

        // Saldo per akhir September:
        // BCA: Saldo awal 10.000.000 + 4.000.000 - 1.500.000 - 2.000.000 = 10.500.000
        // Mandiri: Saldo awal 5.000.000 + 2.000.000 = 7.000.000
        $bcaBal = $ledger->balances($viewer, $company, '2026-09-30 23:59:59');
        $bcaBalanceSept = '0.00';
        $mandiriBalanceSept = '0.00';
        foreach ($bcaBal as $b) {
            if ((int) $b['id'] === $bcaId) $bcaBalanceSept = (string) $b['balance'];
            if ((int) $b['id'] === $mandiriId) $mandiriBalanceSept = (string) $b['balance'];
        }
        $check($bcaBalanceSept === '10500000.00', 'CF5-03: Saldo buku ledger BCA akhir September Rp10.500.000');
        $check($mandiriBalanceSept === '7000000.00', 'CF5-04: Saldo buku ledger Mandiri akhir September Rp7.000.000');

        // 4. Uji Rekonsiliasi Bank (CF-5)
        // A. Viewer dilarang merekonsiliasi
        $reject(function () use ($reconciliation, $viewer, $company, $bcaId) {
            $reconciliation->saveReconciliation($viewer, $company, $bcaId, '2026-09', '2026-09-30', '10500000.00');
        }, 'CF5-05: Company Viewer dilarang menyimpan rekonsiliasi');

        // B. Finance menyimpan rekonsiliasi matched untuk BCA (Rp10.500.000)
        $recBca = $reconciliation->saveReconciliation(
            $finance, $company, $bcaId, '2026-09', '2026-09-30', '10500000.00',
            'Sesuai rekening koran BCA 30 Sept 2026', 'RK-BCA-202609'
        );
        $check($recBca['status'] === 'reconciled', 'CF5-06: Status rekonsiliasi BCA adalah reconciled');
        $check($recBca['discrepancy'] === '0.00', 'CF5-07: Selisih rekonsiliasi BCA adalah 0.00');

        // C. Finance menyimpan rekonsiliasi dengan discrepancy untuk Mandiri (Koran Rp7.050.000 vs Buku Rp7.000.000 -> Selisih +Rp50.000)
        $recMandiri = $reconciliation->saveReconciliation(
            $finance, $company, $mandiriId, '2026-09', '2026-09-30', '7050000.00',
            'Bunga jasa giro bank Rp50.000 belum dicatat', 'RK-MDR-202609'
        );
        $check($recMandiri['status'] === 'discrepancy', 'CF5-08: Status rekonsiliasi Mandiri adalah discrepancy');
        $check($recMandiri['discrepancy'] === '50000.00', 'CF5-09: Selisih Mandiri terhitung tepat Rp50.000');

        // D. Uji ringkasan status rekonsiliasi
        $recSummary = $reconciliation->getReconciliation($viewer, $company, '2026-09');
        $check($recSummary['total_accounts'] === 2, 'CF5-10: Total rekening aktif adalah 2');
        $check($recSummary['reconciled_count'] === 1, 'CF5-11: 1 rekening terekonsiliasi');
        $check($recSummary['discrepancy_count'] === 1, 'CF5-12: 1 rekening terdapat selisih');
        $check($recSummary['pending_count'] === 0, 'CF5-13: 0 rekening pending');
        $check($recSummary['all_reconciled'] === false, 'CF5-14: all_reconciled bernilai false karena ada selisih');
        $check($recSummary['total_discrepancy'] === '50000.00', 'CF5-15: Total selisih mutlak Rp50.000');

        // E. Audit log rekonsiliasi tercatat
        $auditRec = $db->table('company_finance_audit')
            ->where('company_id', $company)->where('action', 'bank_reconciled')
            ->countAllResults();
        $check($auditRec >= 2, 'CF5-16: Audit log rekonsiliasi bank tercatat lengkap');

        // 5. Uji Gate Integritas Matematis (Saldo Pembuka + Mutasi = Saldo Penutup)
        $integrity = $reconciliation->verifyPeriodIntegrity($company, '2026-09');
        $check($integrity['is_valid'] === true, 'CF5-17: Verifikasi matematis integritas periode September lulus');
        $check(count($integrity['accounts']) === 2, 'CF5-18: Kedua rekening terverifikasi konsisten');

        // 6. Uji Tutup Buku & Pembekuan Snapshot (CF-5 Gate)
        // A. Viewer dan Finance dilarang menutup periode
        $reject(function () use ($reconciliation, $viewer, $company) {
            $reconciliation->closePeriodWithSnapshot($viewer, $company, '2026-09', 'Tutup buku viewer');
        }, 'CF5-19: Company Viewer dilarang menutup periode');

        $reject(function () use ($reconciliation, $finance, $company) {
            $reconciliation->closePeriodWithSnapshot($finance, $company, '2026-09', 'Tutup buku finance');
        }, 'CF5-20: Company Finance dilarang menutup periode (wajib Approver)');

        // B. Alasan kosong ditolak
        $reject(function () use ($reconciliation, $approver, $company) {
            $reconciliation->closePeriodWithSnapshot($approver, $company, '2026-09', '');
        }, 'CF5-21: Penutupan periode tanpa alasan audit ditolak');

        // C. Approver menutup periode September dan membekukan snapshot
        $closeRes = $reconciliation->closePeriodWithSnapshot(
            $approver, $company, '2026-09', 'Tutup buku September selesai dan disetujui'
        );
        $check($closeRes['status'] === 'closed', 'CF5-22: Status periode September resmi closed');
        $check($closeRes['version'] === 1, 'CF5-23: Snapshot version 1 dibekukan');
        $check(strlen($closeRes['snapshot_hash']) === 64, 'CF5-24: Snapshot hash SHA-256 sepanjang 64 karakter valid');

        // D. Verifikasi snapshot tersimpan di database
        $snapRow = $reconciliation->getSnapshot($viewer, $company, '2026-09');
        $check($snapRow !== null, 'CF5-25: Snapshot periode September dapat dibaca');
        $check($snapRow['version'] === 1, 'CF5-26: Versi snapshot tersimpan cocok');
        $check($snapRow['snapshot_hash'] === $closeRes['snapshot_hash'], 'CF5-27: Hash snapshot cocok dengan output penutupan');
        $check(isset($snapRow['data']['monthly_report']), 'CF5-28: Snapshot membekukan monthly_report');
        $check(isset($snapRow['data']['external_flow']), 'CF5-29: Snapshot membekukan external_flow IAS 7');
        $check(isset($snapRow['data']['accounts_audit']), 'CF5-30: Snapshot membekukan rekap saldo penutup akun');

        // E. Audit log penutupan periode tercatat
        $auditClose = $db->table('company_finance_audit')
            ->where('company_id', $company)->where('action', 'period_closed_snapshot')
            ->countAllResults();
        $check($auditClose === 1, 'CF5-31: Audit log period_closed_snapshot tercatat');

        // 7. Uji Immutability Periode Tertutup & Penolakan Late Posting
        // A. Posting transaksi baru ke periode September ditolak
        $reject(function () use ($integration, $approver, $company, $bcaId) {
            $integration->manual(
                $approver, $company, 'capital', $bcaId, null, '500000.00',
                '2026-09-25 15:00:00', 'CF5_LATE_01', 'Late posting ke September'
            );
        }, 'CF5-32: Late posting transaksi ke periode tertutup September ditolak');

        // B. Rekonsiliasi baru ke periode tertutup ditolak
        $reject(function () use ($reconciliation, $finance, $company, $bcaId) {
            $reconciliation->saveReconciliation($finance, $company, $bcaId, '2026-09', '2026-09-30', '10600000.00');
        }, 'CF5-33: Perubahan rekonsiliasi pada periode tertutup ditolak');

        // C. Menutup ulang periode yang sudah tertutup ditolak
        $reject(function () use ($reconciliation, $approver, $company) {
            $reconciliation->closePeriodWithSnapshot($approver, $company, '2026-09', 'Tutup ulang');
        }, 'CF5-34: Penutupan ulang periode yang sudah tertutup ditolak');

        // 8. Uji Pembukaan Kembali (Reopen) Periode
        // A. Viewer dan Finance dilarang reopen
        $reject(function () use ($reconciliation, $viewer, $company) {
            $reconciliation->reopenPeriod($viewer, $company, '2026-09', 'Reopen viewer');
        }, 'CF5-35: Company Viewer dilarang membuka kembali periode');

        $reject(function () use ($reconciliation, $finance, $company) {
            $reconciliation->reopenPeriod($finance, $company, '2026-09', 'Reopen finance');
        }, 'CF5-36: Company Finance dilarang membuka kembali periode (wajib Approver)');

        // B. Approver membuka kembali periode September dengan alasan audit
        $reconciliation->reopenPeriod($approver, $company, '2026-09', 'Koreksi penyesuaian bunga jasa giro Mandiri');
        $periodSept = $db->table('company_finance_periods')
            ->where('company_id', $company)->where('month', '2026-09')->get()->getRowArray();
        $check($periodSept['status'] === 'open', 'CF5-37: Periode September berhasil dibuka kembali (status: open)');

        // C. Audit log reopen tercatat
        $auditReopen = $db->table('company_finance_audit')
            ->where('company_id', $company)->where('action', 'period_reopened')
            ->countAllResults();
        $check($auditReopen === 1, 'CF5-38: Audit log period_reopened tercatat');

        // D. Setelah dibuka kembali, transaksi koreksi jasa giro Rp50.000 diizinkan
        $integration->manual(
            $approver, $company, 'capital', $mandiriId, null, '50000.00',
            '2026-09-30 23:50:00', 'CF5_ADJ_01', 'Pencatatan bunga jasa giro bank Mandiri'
        );
        $check(true, 'CF5-39: Transaksi koreksi berhasil diposting setelah reopen');

        // E. Rekonsiliasi Mandiri disesuaikan menjadi matched
        $recMandiri2 = $reconciliation->saveReconciliation(
            $finance, $company, $mandiriId, '2026-09', '2026-09-30', '7050000.00',
            'Sesuai rekening koran setelah koreksi bunga jasa giro', 'RK-MDR-202609-REV'
        );
        $check($recMandiri2['status'] === 'reconciled', 'CF5-40: Rekonsiliasi Mandiri kini berstatus reconciled');
        $check($recMandiri2['discrepancy'] === '0.00', 'CF5-41: Selisih Mandiri kini Rp0.00');

        $recSummary2 = $reconciliation->getReconciliation($viewer, $company, '2026-09');
        $check($recSummary2['all_reconciled'] === true, 'CF5-42: Seluruh rekening kini terekonsiliasi penuh (all_reconciled: true)');

        // F. Approver menutup kembali periode September (menghasilkan snapshot v2)
        $closeRes2 = $reconciliation->closePeriodWithSnapshot(
            $approver, $company, '2026-09', 'Tutup buku final September setelah rekonsiliasi 100%'
        );
        $check($closeRes2['version'] === 2, 'CF5-43: Snapshot versi 2 berhasil dibekukan');

        // 9. Uji Ekspor SpreadsheetML XML (.xls) dengan Data Rekonsiliasi CF-5
        session()->set(['isLoggedIn' => true, 'userId' => $approver, 'role' => 'Company Approver']);
        $_GET = ['company' => (string) $company, 'month' => '2026-09'];

        $controller = new CompanyFinanceController();
        $controller->initController(Services::request(), Services::response(), Services::logger());

        $exportResponse = $controller->export();
        $exportResponse->buildHeaders();
        ob_start();
        $exportResponse->sendBody();
        $xml = (string) ob_get_clean();

        $check(str_contains($xml, 'STATUS REKONSILIASI BANK'), 'CF5-44: Ekspor Worksheet 3 memuat tabel status rekonsiliasi bank');
        $check(str_contains($xml, 'Periode Ditutup &amp; Dibekukan') || str_contains($xml, 'Snapshot v2'), 'CF5-45: Ekspor Worksheet 1 mencantumkan status periode ditutup & dibekukan');

        CLI::write('', 'white');
        CLI::write('=== SELURUH 45 PENGUJIAN CF-5 SELESAI & LULUS 100% ===', 'green');
    }
}
