<?php

namespace App\Commands;

use App\Libraries\CashAmount;
use App\Libraries\CashLedgerService;
use App\Libraries\CompanyFinanceAccess;
use App\Libraries\CompanyFinanceContext;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;
use Throwable;

/** Writes fixtures only in a named isolated clone; never allowed in main/live. */
final class TestCompanyCashFoundation extends BaseCommand
{
    protected $group = 'Testing';
    protected $name = 'company:foundation-test';
    protected $description = 'Uji CF-1 hanya pada DB clone bernama homiq_pms_cf1_test_YYYYMMDD.';
    protected $options = ['--http-fixtures' => 'Buat dua akun HTTP test-only pada clone setelah uji.'];

    public function run(array $params)
    {
        $db = db_connect();
        if (ENVIRONMENT !== 'development' || ! in_array($db->hostname, ['127.0.0.1', 'localhost'], true)
            || preg_match('/^homiq_pms_cf1_test_[0-9]{8}$/D', $db->database) !== 1) throw new RuntimeException('Uji ditolak: wajib development dan DB clone lokal CF-1.');
        $checks = 0;
        $check = static function (bool $value, string $message) use (&$checks) {
            if (! $value) throw new RuntimeException('FAIL: ' . $message);
            ++$checks; CLI::write('PASS ' . $message);
        };
        $reject = static function (callable $fn, string $message) use ($check) {
            $rejected = false;
            try { $fn(); } catch (Throwable) { $rejected = true; }
            $check($rejected, $message);
        };
        $baseline = [];
        foreach (['users', 'properties', 'rooms', 'guests', 'reservations', 'payments', 'expenses', 'invoice_items', 'user_property_roles'] as $table) $baseline[$table] = $db->table($table)->countAllResults();
        $users = $db->table('users')->orderBy('id')->limit(2)->get()->getResultArray();
        if (count($users) < 2) throw new RuntimeException('Clone harus memiliki dua akun existing.');
        $approver = (int) $users[0]['id']; $viewer = (int) $users[1]['id'];
        $check(! CompanyFinanceAccess::allows($approver, 999999, 'view', $db), 'no implicit property-role grant');
        $db->table('companies')->insert(['code' => 'CF1_TEST_' . bin2hex(random_bytes(4)), 'name' => 'Holding test only', 'is_active' => 1, 'created_at' => gmdate('Y-m-d H:i:s')]);
        $company = (int) $db->insertID();
        foreach (['Company Approver' => $approver, 'Company Viewer' => $viewer] as $role => $user) $db->table('company_user_roles')->insert(['company_id' => $company, 'user_id' => $user, 'role' => $role, 'created_at' => gmdate('Y-m-d H:i:s')]);
        $property = (int) $db->table('properties')->orderBy('id')->get()->getRowArray()['id'];
        $db->table('company_properties')->insert(['company_id' => $company, 'property_id' => $property]);
        $check(CompanyFinanceAccess::allows($viewer, $company, 'view', $db) && ! CompanyFinanceAccess::allows($viewer, $company, 'post', $db), 'viewer capability boundary');
        $check(CompanyFinanceContext::resolve($viewer, 999999) === null, 'forged company selection denied');
        $ledger = new CashLedgerService($db);
        $a = $ledger->createAccount($approver, $company, 'A', 'Bank A test', 'bank', $property);
        $b = $ledger->createAccount($approver, $company, 'B', 'Kas B test', 'cash');
        $check($ledger->balances($viewer, $company)[0]['balance'] === null, 'unverified balance is unknown, not zero');
        $ledger->opening($approver, $company, $a, '2026-01-01 00:00:00', '1000000.10', 'Verified clone fixture');
        $ledger->opening($approver, $company, $b, '2026-01-01 00:00:00', '0.00', 'Verified clone fixture');
        $check($ledger->externalFlow($viewer, $company, '2026-01-01 00:00:00', '2026-02-01 00:00:00') === [], 'opening excluded from cashflow');
        $reject(fn () => $ledger->opening($approver, $company, $a, '2026-01-01 00:00:00', '1.00', 'overwrite'), 'opening cannot be overwritten');
        $reject(fn () => $ledger->post($viewer, $company, 'forbidden', 'receipt', 'operating', '2026-01-02 00:00:00', 'denied', [['account_id' => $a, 'amount' => '1.00']]), 'viewer cannot post');
        $entries = [['account_id' => $a, 'amount' => '-100000.10'], ['account_id' => $b, 'amount' => '100000.10']];
        $id = $ledger->post($approver, $company, 'transfer1', 'transfer', 'internal', '2026-01-02 00:00:00', 'Transfer test', $entries);
        $check($ledger->post($approver, $company, 'transfer1', 'transfer', 'internal', '2026-01-02 00:00:00', 'Transfer test', array_reverse($entries)) === $id, 'idempotent posting canonicalizes entry order');
        $balances = $ledger->balances($viewer, $company, '2026-01-03 00:00:00');
        $check($balances[0]['balance'] === '900000.00' && $balances[1]['balance'] === '100000.10', 'transfer changes accounts but preserves total exactly');
        $check($ledger->externalFlow($viewer, $company, '2026-01-01 00:00:00', '2026-02-01 00:00:00') === [], 'internal transfer excluded from external flow');
        $reject(fn () => $ledger->post($approver, $company, 'transfer1', 'transfer', 'internal', '2026-01-02 00:00:00', 'changed payload', $entries), 'idempotency conflict rejected');
        $reject(fn () => $ledger->post($approver, $company, 'bad-date', 'receipt', 'operating', '2026-02-30 00:00:00', 'invalid', [['account_id' => $a, 'amount' => '1.00']]), 'invalid calendar date rejected');
        $reject(fn () => $ledger->post($approver, $company, 'before-cutover', 'receipt', 'operating', '2025-12-31 00:00:00', 'invalid', [['account_id' => $a, 'amount' => '1.00']]), 'pre-cutover transaction rejected');
        $reject(fn () => $ledger->post($approver, $company, 'invalid-property', 'receipt', 'operating', '2026-01-03 00:00:00', 'invalid', [['account_id' => $a, 'amount' => '1.00']], 999999), 'out-of-company property denied');
        $reject(fn () => $ledger->post($approver, $company, 'invalid-account', 'transfer', 'internal', '2026-01-03 00:00:00', 'invalid', [['account_id' => $a, 'amount' => '-1.00'], ['account_id' => 999999, 'amount' => '1.00']]), 'out-of-company account denied');
        $db->query("CREATE TRIGGER cf1_fail_leg BEFORE INSERT ON cash_entries FOR EACH ROW BEGIN IF NEW.account_id = {$b} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test injected failure'; END IF; END");
        $reject(fn () => $ledger->post($approver, $company, 'rollback-test', 'transfer', 'internal', '2026-01-03 00:00:00', 'Injected failure', $entries), 'second-leg database failure rejected');
        $db->query('DROP TRIGGER cf1_fail_leg');
        $check($db->table('cash_transactions')->where('event_key', 'rollback-test')->countAllResults() === 0 && $db->table('cash_entries')->countAllResults() === 2, 'header and first leg rolled back atomically');
        $receipt = $ledger->post($approver, $company, 'receipt1', 'receipt', 'operating', '2026-01-04 00:00:00', 'Receive 0.10', [['account_id' => $a, 'amount' => '0.10']]);
        $ledger->post($approver, $company, 'future1', 'receipt', 'operating', '2027-01-01 00:00:00', 'Future', [['account_id' => $a, 'amount' => '500.00']]);
        $check($ledger->balances($viewer, $company, '2026-01-05 00:00:00')[0]['balance'] === '900000.10', 'future transaction excluded from as-of balance');
        $reverse = $ledger->reverse($approver, $company, $receipt, 'reverse1', '2026-01-05 00:00:00', 'Correction test');
        $check($ledger->reverse($approver, $company, $receipt, 'reverse1', '2026-01-05 00:00:00', 'Correction test') === $reverse, 'reversal retry idempotent');
        $reject(fn () => $ledger->reverse($approver, $company, $receipt, 'reverse2', '2026-01-05 00:00:00', 'Again'), 'double reversal rejected');
        $check($db->table('cash_transactions')->where('id', $receipt)->get()->getRowArray()['description'] === 'Receive 0.10', 'original posting retained unchanged');
        $check($ledger->balances($viewer, $company, '2026-01-06 00:00:00')[0]['balance'] === '900000.00', 'reversal offsets original exactly');
        $ledger->setPeriod($approver, $company, '2026-01', true, 'Lock test');
        $reject(fn () => $ledger->post($approver, $company, 'locked', 'receipt', 'operating', '2026-01-06 00:00:00', 'Locked', [['account_id' => $a, 'amount' => '1.00']]), 'closed month rejects posting');
        $reject(fn () => $ledger->reverse($approver, $company, $id, 'locked-reverse', '2026-01-06 00:00:00', 'Locked'), 'closed month rejects reversal');
        $ledger->setPeriod($approver, $company, '2026-01', false, 'Explicit reopen test');
        $reject(fn () => CashAmount::cents('1.001'), 'excess precision rejected without rounding');
        $db->table('company_user_roles')->where('company_id', $company)->where('user_id', $viewer)->delete();
        $reject(fn () => $ledger->balances($viewer, $company), 'grant revocation immediately denies reads');
        foreach ($baseline as $table => $count) $check($db->table($table)->countAllResults() === $count, 'existing ' . $table . ' row count unchanged');
        if (function_exists('pcntl_fork')) {
            $db->close(); $children = [];
            for ($i = 0; $i < 2; ++$i) {
                $pid = pcntl_fork();
                if ($pid < 0) throw new RuntimeException('Fork gagal.');
                if ($pid === 0) {
                    try {
                        $connection = \Config\Database::connect(config('Database')->default, false);
                        (new CashLedgerService($connection))->post($approver, $company, 'concurrent1', 'receipt', 'operating', '2026-01-06 00:00:00', 'Concurrent retry', [['account_id' => $a, 'amount' => '1.00']]);
                        exit(0);
                    } catch (Throwable $e) { CLI::error($e->getMessage()); exit(1); }
                }
                $children[] = $pid;
            }
            foreach ($children as $pid) { pcntl_waitpid($pid, $status); $check(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'concurrent caller committed/reused'); }
            $db->initialize();
            $check($db->table('cash_transactions')->where('company_id', $company)->where('event_key', 'concurrent1')->countAllResults() === 1, 'concurrent requests create only one posting');
        }
        if (CLI::getOption('http-fixtures')) {
            foreach (['cf1_test_approver' => 'Staff', 'cf1_test_finance' => 'Finance'] as $username => $role) {
                $db->table('users')->insert(['username' => $username, 'full_name' => 'CF1 HTTP Test only', 'password_hash' => password_hash('CF1-test-clone-only', PASSWORD_DEFAULT), 'role' => $role]);
                $fixtureUser = (int) $db->insertID();
                $db->table('user_property_roles')->insert(['user_id' => $fixtureUser, 'property_id' => $property, 'role' => $role]);
                if ($role === 'Staff') $db->table('company_user_roles')->insert(['company_id' => $company, 'user_id' => $fixtureUser, 'role' => 'Company Approver', 'created_at' => gmdate('Y-m-d H:i:s')]);
            }
            CLI::write('HTTP fixtures ready in clone; company=' . $company);
        }
        CLI::write('CF1 PASS: ' . $checks . ' checks; fixtures only in ' . $db->database, 'green');
    }
}
