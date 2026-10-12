<?php

namespace App\Commands;

use App\Libraries\CashAmount;
use App\Libraries\CashLedgerService;
use App\Libraries\CompanyCashIntegration;
use App\Libraries\CompanyEntitlementService;
use App\Libraries\CompanyFinanceAccess;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;
use Throwable;

final class TestCompanyCashCF3 extends BaseCommand
{
    protected $group = 'Testing';
    protected $name = 'company:cf3-test';
    protected $description = 'Uji CF-3 hanya di clone lokal homiq_pms_cf3_test_YYYYMMDD.';
    protected $options = ['--http-fixtures' => 'Akun uji HTTP sementara di clone.'];

    public function run(array $params)
    {
        $db = db_connect();
        if (ENVIRONMENT !== 'development' || ! in_array($db->hostname, ['localhost', '127.0.0.1'], true)
            || preg_match('/^homiq_pms_cf3_test_[0-9]{8}$/D', $db->database) !== 1) {
            throw new RuntimeException('Wajib clone CF-3 lokal (homiq_pms_cf3_test_YYYYMMDD); main/live ditolak.');
        }

        $n = 0;
        $check = static function (bool $ok, string $name) use (&$n): void {
            if (! $ok) {
                throw new RuntimeException('FAIL ' . $name);
            }
            ++$n;
            CLI::write('PASS ' . $name, 'green');
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

        // 1. Setup users & company
        $users = $db->table('users')->orderBy('id')->limit(3)->get()->getResultArray();
        if (count($users) < 3) {
            throw new RuntimeException('Clone harus memiliki minimal 3 user.');
        }
        $approver = (int) $users[0]['id'];
        $finance = (int) $users[1]['id'];
        $viewer = (int) $users[2]['id'];

        $db->table('companies')->insert([
            'code' => 'CF3_TEST',
            'name' => 'CF3 Holding Test',
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

        // Map properties 1 (HomiQ), 2 (Homestay Mitra), 3 (Kos Mitra)
        foreach ([1, 2, 3] as $pId) {
            $db->table('company_properties')->where('property_id', $pId)->delete();
            $db->table('company_properties')->insert([
                'company_id' => $company,
                'property_id' => $pId,
            ]);
        }

        $ledger = new CashLedgerService($db);
        $cash = new CompanyCashIntegration($db);
        $entitlement = new CompanyEntitlementService($db);

        // Create accounts & opening balances
        $accBank = $ledger->createAccount($approver, $company, 'BANK_A', 'Rekening Bank Holding', 'bank');
        $accCash = $ledger->createAccount($approver, $company, 'KAS_B', 'Kas Operasional Holding', 'cash');
        $ledger->opening($approver, $company, $accBank, '2026-01-01 00:00:00', '10000000.00', 'Saldo awal cutover');
        $ledger->opening($approver, $company, $accCash, '2026-01-01 00:00:00', '2000000.00', 'Saldo awal kas');

        // =====================================================================
        // TEST 1: Agreements (Effective-Dated) & Permissions
        // =====================================================================
        $reject(
            fn () => $entitlement->createAgreement($viewer, $company, 1, 'revenue_share', 'room_revenue', '30.00', '70.00', '0.00', '2026-01-01', '2026-06-30', 'Viewer test'),
            'viewer cannot create agreement'
        );
        $reject(
            fn () => $entitlement->createAgreement($finance, $company, 1, 'revenue_share', 'room_revenue', '30.00', '70.00', '0.00', '2026-01-01', '2026-06-30', 'Finance test'),
            'finance cannot create agreement'
        );
        $reject(
            fn () => $entitlement->createAgreement($approver, $company, 1, 'revenue_share', 'room_revenue', '60.00', '50.00', '0.00', '2026-01-01', '2026-06-30', 'Over 100 pct'),
            'shares exceeding 100 pct rejected'
        );

        $ag1 = $entitlement->createAgreement($approver, $company, 1, 'revenue_share', 'room_revenue', '30.00', '70.00', '0.00', '2026-01-01', '2026-06-30', 'HomiQ Semester 1');
        $check($ag1 > 0, 'agreement 1 created');

        $ag2 = $entitlement->createAgreement($approver, $company, 1, 'revenue_share', 'room_revenue', '35.00', '65.00', '100000.00', '2026-07-01', null, 'HomiQ Semester 2 onwards');
        $check($ag2 > 0, 'agreement 2 created');

        // Overlapping date rejection
        $reject(
            fn () => $entitlement->createAgreement($approver, $company, 1, 'revenue_share', 'room_revenue', '20.00', '80.00', '0.00', '2026-03-01', '2026-05-01', 'Overlap'),
            'overlapping agreement dates rejected'
        );

        // Agreement resolving
        $res1 = $entitlement->resolveAgreement($company, 1, '2026-03-15');
        $check($res1 !== null && (int) $res1['id'] === $ag1, 'resolves agreement 1 for 2026-03-15');
        $res2 = $entitlement->resolveAgreement($company, 1, '2026-09-01');
        $check($res2 !== null && (int) $res2['id'] === $ag2, 'resolves agreement 2 for 2026-09-01');
        $resOld = $entitlement->resolveAgreement($company, 1, '2025-12-31');
        $check($resOld === null, 'returns null before effective_from');

        // Agreement for Homestay Mitra (Property 2)
        $agMitra = $entitlement->createAgreement($approver, $company, 2, 'revenue_share', 'gross_revenue', '25.00', '75.00', '0.00', '2026-01-01', null, 'Homestay Mitra Agreement');
        $check($agMitra > 0, 'homestay agreement created');

        // =====================================================================
        // TEST 2: GATE: Homestay sekali per rumah
        // =====================================================================
        // In Homestay Mitra (whole_home_nightly), a booking blocks 3 rooms (e.g. rooms 01, 02, 03)
        // Rate is Rp600.000/night for the whole house.
        $homestayRooms = $db->table('rooms')->where('property_id', 2)->get()->getResultArray();
        $db->table('guests')->insert([
            'full_name' => 'Tamu Homestay Test',
            'property_id' => 2,
            'created_at' => '2026-02-01 00:00:00',
        ]);
        $guestHomestayId = (int) $db->insertID();

        $groupKey = 'HOMESTAY_GRP_1';
        $resIds = [];
        foreach ($homestayRooms as $idx => $r) {
            $db->table('reservations')->insert([
                'property_id' => 2,
                'room_id' => $r['id'],
                'guest_id' => $guestHomestayId,
                'order_id' => 'HOM-202602-00' . ($idx + 1),
                'booking_group_id' => $groupKey,
                'check_in_date' => '2026-02-10',
                'check_out_date' => '2026-02-12', // 2 nights
                'total_amount' => 400000.00, // 3 rooms x 400.000 = 1.200.000 (2 nights x 600.000)
                'status' => 'Confirmed',
                'created_at' => '2026-02-01 00:00:00',
            ]);
            $resIds[] = (int) $db->insertID();
        }

        // Post confirmed payment for Homestay booking
        $db->table('payments')->insert([
            'reservation_id' => $resIds[0],
            'amount' => '1200000.00',
            'payment_date' => '2026-02-10',
            'payment_method' => 'Transfer',
            'status' => 'Paid',
            'type' => 'Completed',
        ]);
        $payHomestay = (int) $db->insertID();
        $cash->receipt($finance, $company, $payHomestay, $accBank, '1200000.00', '0.00', '2026-02-10 10:00:00', 'homestay_pay1', 'Penerimaan sewa rumah');

        $repFeb = $entitlement->monthlyReport($viewer, $company, '2026-02', 2);
        $homestayRep = $repFeb['properties'][0];

        $check((int) $homestayRep['booking_count'] === 1, 'Homestay 3 kamar dihitung tepat 1 booking unik');
        $check((int) $homestayRep['room_nights_sold'] === 2, 'Homestay 2 malam dihitung 2 malam rumah, bukan 6 kamar-malam');
        $check($homestayRep['gross_booked'] === '1200000.00', 'Homestay gross booked tepat Rp1.200.000, bukan 3x');
        $check($homestayRep['confirmed_net_cash'] === '1200000.00', 'Homestay net cash confirmed Rp1.200.000');

        // =====================================================================
        // TEST 3: GATE: Fee perusahaan tidak ditambahkan dua kali
        // =====================================================================
        // In HomiQ (property 1):
        // Confirmed payment Rp1.050.000 where room rental = Rp1.000.000 and Breakfast (direct company) = Rp50.000.
        // Agreement Semester 1: 30% company, 70% owner on room_revenue.
        // Expected:
        // Room revenue base = Rp1.000.000
        // Company fee = 30% x 1.000.000 = Rp300.000
        // Direct services = Rp50.000
        // Total company entitlement = Rp350.000 (NOT fee calculated on 1.050.000 + 50.000 double counting!)
        $roomHomiq = $db->table('rooms')->where('property_id', 1)->get()->getRowArray();
        $guestHomiq = $db->table('guests')->where('property_id', 1)->get()->getRowArray();
        $db->table('reservations')->insert([
            'property_id' => 1,
            'room_id' => $roomHomiq['id'],
            'guest_id' => $guestHomiq['id'],
            'order_id' => 'HOMIQ-202603-001',
            'check_in_date' => '2026-03-01',
            'check_out_date' => '2026-03-03',
            'total_amount' => 1050000.00,
            'status' => 'Confirmed',
            'created_at' => '2026-03-01 00:00:00',
        ]);
        $resHomiq = (int) $db->insertID();

        // Invoice items: Room Rp1.000.000, Breakfast Rp50.000
        $db->table('invoice_items')->insert(['reservation_id' => $resHomiq, 'item_name' => 'Room Charge', 'amount' => 1000000.00]);
        $db->table('invoice_items')->insert(['reservation_id' => $resHomiq, 'item_name' => 'Breakfast', 'amount' => 50000.00]);

        $db->table('payments')->insert([
            'reservation_id' => $resHomiq,
            'amount' => '1050000.00',
            'payment_date' => '2026-03-01',
            'payment_method' => 'Transfer',
            'status' => 'Paid',
            'type' => 'Completed',
        ]);
        $payHomiq = (int) $db->insertID();
        $cash->receipt($finance, $company, $payHomiq, $accBank, '1050000.00', '0.00', '2026-03-01 10:00:00', 'homiq_pay1', 'Penerimaan kamar + breakfast');

        $repMar = $entitlement->monthlyReport($viewer, $company, '2026-03', 1);
        $homiqRep = $repMar['properties'][0];

        $check($homiqRep['direct_services'] === '50000.00', 'Layanan langsung perusahaan Rp50.000');
        $check($homiqRep['company_fee'] === '300000.00', 'Fee pengelola 30% dari room rental Rp1.000.000 = Rp300.000');
        $check($homiqRep['owner_share'] === '700000.00', 'Hak owner 70% dari room rental = Rp700.000');
        $check($homiqRep['total_company_entitlement'] === '350000.00', 'Total hak perusahaan Rp350.000 tanpa double count fee');

        // =====================================================================
        // TEST 4: GATE: Owner payout tidak menjadi expense kedua
        // =====================================================================
        $expenseCountBefore = $db->table('expenses')->countAllResults();

        // 1. Create obligation for owner payout Rp700.000
        $obId = $entitlement->createObligation(
            $finance,
            $company,
            1,
            'owner_payable',
            'owner_report',
            'HOMIQ-2026-03-OWNER',
            '2026-03',
            'Bagi hasil owner Maret 2026',
            '700000.00',
            'estimated'
        );
        $check($obId > 0, 'obligation created as estimated');

        // Cannot settle estimated obligation
        $reject(
            fn () => $entitlement->settleObligation($finance, $company, $obId, $accBank, '700000.00', '2026-03-05 10:00:00', 'payout1', 'Owner payout'),
            'cannot settle estimated obligation before finalization'
        );

        // Approver finalizes obligation
        $entitlement->finalizeObligation($approver, $company, $obId);
        $obRow = $db->table('financial_obligations')->where('id', $obId)->get()->getRowArray();
        $check($obRow['status'] === 'finalized', 'obligation finalized by approver');

        // Settle obligation via cash disbursement
        $balBeforePayout = CashAmount::cents((string) $ledger->balances($viewer, $company)[0]['balance']);
        $settleTx = $entitlement->settleObligation($finance, $company, $obId, $accBank, '700000.00', '2026-03-05 10:00:00', 'payout1', 'Pelunasan bagi hasil owner Maret');
        $balAfterPayout = CashAmount::cents((string) $ledger->balances($viewer, $company)[0]['balance']);

        $check($settleTx > 0, 'obligation settlement transaction posted');
        $check($balBeforePayout - $balAfterPayout === 70000000, 'cash balance reduced by Rp700.000');
        $check($db->table('financial_obligations')->where('id', $obId)->get()->getRowArray()['status'] === 'settled', 'obligation status updated to settled');
        $check($db->table('expenses')->countAllResults() === $expenseCountBefore, 'GATE PASS: expenses table row count UNCHANGED, no second expense created');

        // Overpayment rejection
        $reject(
            fn () => $entitlement->settleObligation($finance, $company, $obId, $accBank, '1000.00', '2026-03-05 11:00:00', 'payout_extra', 'Extra'),
            'settlement exceeding obligation amount rejected'
        );

        // =====================================================================
        // TEST 5: GATE: Alokasi overhead jumlahnya cocok ke sumber
        // =====================================================================
        // 1. Create holding company overhead Rp500.000
        $ohId = $cash->createOverhead($finance, $company, 'OH_202603', 'Biaya Lisensi Holding & Internet', 'Operasional Kantor', '500000.00', '2026-03-01');
        $reject(
            fn () => $entitlement->allocateOverhead($approver, $company, $ohId, 1, '50.00', '250000.00', '2026-03'),
            'cannot allocate unapproved overhead'
        );

        // Approve overhead
        $cash->approveOverhead($approver, $company, $ohId);

        // Allocate Rp200.000 (40%) to Property 1
        $al1 = $entitlement->allocateOverhead($approver, $company, $ohId, 1, '40.00', '200000.00', '2026-03');
        $check($al1 > 0, 'overhead allocated 40 pct to Property 1');

        // Allocate Rp300.000 (60%) to Property 2
        $al2 = $entitlement->allocateOverhead($approver, $company, $ohId, 2, '60.00', '300000.00', '2026-03');
        $check($al2 > 0, 'overhead allocated 60 pct to Property 2');

        // Attempting to allocate another Rp50.000 (10%) to Property 3 is REJECTED
        $reject(
            fn () => $entitlement->allocateOverhead($approver, $company, $ohId, 3, '10.00', '50000.00', '2026-03'),
            'allocation exceeding 100 pct / original amount rejected'
        );

        // Settle overhead via cash disbursement
        $cash->settleExpense($finance, $company, $ohId, $accBank, '500000.00', '2026-03-06 10:00:00', 'settle_oh1', 'Bayar lisensi kantor', true);

        // Check report holding overhead is counted ONCE (Rp500.000), not doubled (Rp500k + Rp500k allocations)
        $repMarHolding = $entitlement->monthlyReport($viewer, $company, '2026-03');
        $check($repMarHolding['bridge']['holding_overhead_paid'] === '500000.00', 'holding total counts base overhead once (Rp500.000), not double counted');

        // =====================================================================
        // TEST 6: Estimasi Kas Bebas & Cadangan
        // =====================================================================
        // Create an unpaid obligation for refund reserve Rp200.000
        $entitlement->createObligation(
            $finance,
            $company,
            1,
            'refund_deposit',
            'guest_deposit',
            'DEP_TAMU_001',
            '2026-03',
            'Titipan jaminan deposit tamu',
            '200000.00',
            'finalized'
        );

        $repPostReserve = $entitlement->monthlyReport($viewer, $company, '2026-03');
        $currentCash = CashAmount::cents($repPostReserve['cards']['current_cash_total']);
        $totalReserves = CashAmount::cents($repPostReserve['cards']['total_reserves']);
        $freeCash = CashAmount::cents($repPostReserve['cards']['estimated_free_cash']);

        $check($totalReserves === 20000000, 'total reserves includes pending refund/deposit obligation (Rp200.000)');
        $check($freeCash === $currentCash - $totalReserves, 'estimated free cash = current cash - total reserves');

        // =====================================================================
        // TEST 7: Period Lock Enforcement on CF-3 Settlements
        // =====================================================================
        $ledger->setPeriod($approver, $company, '2026-03', true, 'Tutup buku Maret');
        $obLocked = $entitlement->createObligation(
            $finance,
            $company,
            1,
            'other',
            'tax',
            'TAX-001',
            '2026-03',
            'Kewajiban pajak',
            '100000.00',
            'finalized'
        );
        $reject(
            fn () => $entitlement->settleObligation($finance, $company, $obLocked, $accBank, '100000.00', '2026-03-10 10:00:00', 'tax_pay', 'Pajak'),
            'settling obligation in closed period rejected'
        );
        $ledger->setPeriod($approver, $company, '2026-03', false, 'Buka kembali Maret');

        CLI::write("CF-3 PASS: {$n} checks; all gates & rules verified on clone.", 'green');
    }
}
