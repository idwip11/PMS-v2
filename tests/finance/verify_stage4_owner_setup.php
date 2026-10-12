<?php
/** Stage 4: isolated financial, configuration, controller and holding integration checks. */
ob_start();
$root = dirname(__DIR__, 2);
define('ENVIRONMENT', 'development');
define('FCPATH', $root . '/public/');
chdir($root);
require $root . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);

use App\Libraries\FinanceReportScope as Scope;
use App\Libraries\FinanceOwnerSetupService as Setup;
use App\Libraries\MitraOwnerFinanceService as Owner;
use App\Libraries\CompanyEntitlementService as Holding;
use App\Controllers\FinanceController;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use Config\Services;

$config = config('Database');
$clone = $argv[1] ?? '';
if ($clone !== '') {
    if (preg_match('/^homiq_pms_mitra_stage4_test_[0-9]{8}_[a-z0-9]+$/D', $clone) !== 1
        || $config->default['DBDriver'] !== 'MySQLi'
        || ! in_array($config->default['hostname'], ['localhost', '127.0.0.1'], true)) {
        throw new RuntimeException('Only a guarded local empty test database is allowed.');
    }
    $config->default['database'] = $clone;
} else {
    $config->default = ['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => '', 'DBDebug' => true, 'foreignKeys' => true];
}
$db = db_connect();
if ($db->listTables() !== []) {
    throw new RuntimeException('Test database must be empty. Never use the application database.');
}
$schemas = [
    'properties' => 'id INTEGER PRIMARY KEY, code VARCHAR(40) UNIQUE NOT NULL, name VARCHAR(150), is_active INTEGER, pricing_mode VARCHAR(32), base_rate DECIMAL(14,2)',
    'users' => 'id INTEGER PRIMARY KEY, username VARCHAR(100), role VARCHAR(50), full_name VARCHAR(150)',
    'user_property_roles' => 'id INTEGER PRIMARY KEY, user_id INTEGER, property_id INTEGER, role VARCHAR(50), UNIQUE(user_id, property_id)',
    'owner_guesthouse_setups' => 'id INTEGER PRIMARY KEY, property_id INTEGER, guesthouse INTEGER, owner_name VARCHAR(150), cooperation_fee_percentage DECIMAL(5,2), created_at DATETIME, updated_at DATETIME',
    'rooms' => 'id INTEGER PRIMARY KEY, property_id INTEGER, room_number VARCHAR(30), floor INTEGER, guesthouse_number INTEGER',
    'guests' => 'id INTEGER PRIMARY KEY, property_id INTEGER, full_name VARCHAR(150)',
    'reservations' => 'id INTEGER PRIMARY KEY, property_id INTEGER, room_id INTEGER, guest_id INTEGER, total_amount DECIMAL(14,2), status VARCHAR(32), order_id VARCHAR(50), booking_group_id VARCHAR(50), check_in_date DATE, check_out_date DATE',
    'payments' => 'id INTEGER PRIMARY KEY, reservation_id INTEGER, payment_date DATE, type VARCHAR(32), status VARCHAR(32), amount DECIMAL(14,2), proof_path VARCHAR(255)',
    'invoice_items' => 'id INTEGER PRIMARY KEY, reservation_id INTEGER, item_name VARCHAR(150), amount DECIMAL(14,2)',
    'expenses' => 'id INTEGER PRIMARY KEY, property_id INTEGER, guesthouse INTEGER, date_incurred DATE, category VARCHAR(150), description VARCHAR(255), amount DECIMAL(14,2), status VARCHAR(32), receipt_url VARCHAR(255)',
    'expense_guesthouses' => 'expense_id INTEGER, guesthouse INTEGER',
    'maintenance_tickets' => 'id INTEGER PRIMARY KEY, room_id INTEGER, blocks_room INTEGER, created_at DATETIME, completed_at DATETIME',
    'companies' => 'id INTEGER PRIMARY KEY, is_active INTEGER, name VARCHAR(150), code VARCHAR(40)',
    'company_user_roles' => 'user_id INTEGER, company_id INTEGER, role VARCHAR(50)',
    'company_properties' => 'company_id INTEGER, property_id INTEGER',
    'cash_transactions' => 'id INTEGER PRIMARY KEY, company_id INTEGER, reverses_id INTEGER, effective_at DATETIME, classification VARCHAR(32), source_type VARCHAR(50)',
    'cash_payment_receipts' => 'id INTEGER PRIMARY KEY, company_id INTEGER, transaction_id INTEGER, payment_id INTEGER, amount DECIMAL(14,2), withheld_fee DECIMAL(14,2), source_amount DECIMAL(14,2)',
    'expense_settlements' => 'company_id INTEGER, transaction_id INTEGER, expense_id INTEGER, amount DECIMAL(14,2)',
    'property_finance_agreements' => 'id INTEGER PRIMARY KEY, company_id INTEGER, property_id INTEGER, effective_from DATE, effective_to DATE, status VARCHAR(32), agreement_type VARCHAR(32), calculation_base VARCHAR(32), owner_share_pct DECIMAL(5,2), company_share_pct DECIMAL(5,2), fixed_fee_monthly DECIMAL(14,2)',
    'company_expense_allocations' => 'company_id INTEGER, property_id INTEGER, month VARCHAR(7), amount DECIMAL(14,2)',
    'company_expense_settlements' => 'company_id INTEGER, transaction_id INTEGER, amount DECIMAL(14,2)',
    'company_expenses' => 'company_id INTEGER, date_incurred DATE, status VARCHAR(32), amount DECIMAL(14,2)',
    'cash_entries' => 'company_id INTEGER, transaction_id INTEGER, account_id INTEGER, amount DECIMAL(14,2)',
    'cash_accounts' => 'id INTEGER PRIMARY KEY, company_id INTEGER, property_id INTEGER, code VARCHAR(40), name VARCHAR(150), is_active INTEGER, currency VARCHAR(3)',
    'cash_opening_balances' => 'company_id INTEGER, account_id INTEGER, effective_at DATETIME, amount DECIMAL(14,2)',
    'financial_obligations' => 'id INTEGER PRIMARY KEY, company_id INTEGER, obligation_type VARCHAR(32), status VARCHAR(32), amount DECIMAL(14,2)',
    'obligation_settlements' => 'company_id INTEGER, transaction_id INTEGER, obligation_id INTEGER, amount DECIMAL(14,2)',
];
foreach ($schemas as $table => $fields) {
    if ($clone !== '') {
        $fields = str_replace('INTEGER PRIMARY KEY', 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY', $fields);
    }
    $db->query('CREATE TABLE ' . $table . ' (' . $fields . ')');
}
$db->resetDataCache();
foreach ([1 => ['HOMIQ', 'Adiputra Guesthouse by HomiQ', 'room_nightly'], 2 => ['HOMESTAY_MITRA', 'Homestay Mitra', 'whole_home_nightly'], 3 => ['KOS_MITRA', 'Kos Mitra', 'room_monthly']] as $id => [$code, $name, $mode]) {
    $db->table('properties')->insert(['id' => $id, 'code' => $code, 'name' => $name, 'pricing_mode' => $mode, 'is_active' => 1, 'base_rate' => 0]);
}
require $root . '/app/Database/Migrations/2026-10-12000000_AddMitraFinanceReportGroups.php';
$migration = new App\Database\Migrations\AddMitraFinanceReportGroups(Config\Database::forge($db));
$migration->up();
foreach ([10 => 'Manager', 11 => 'Owner Mitra', 12 => 'Finance', 13 => 'Owner GH 3', 14 => 'Manager'] as $id => $role) {
    $db->table('users')->insert(['id' => $id, 'username' => 'synthetic_' . $id, 'role' => $role, 'full_name' => 'Test ' . $id]);
}
foreach ([[10, 1, 'Manager'], [10, 2, 'Manager'], [10, 3, 'Finance'], [11, 2, 'Owner Mitra'], [11, 3, 'Owner Mitra'], [12, 2, 'Finance'], [13, 1, 'Owner GH 3'], [14, 1, 'Manager']] as [$user, $prop, $role]) {
    $db->table('user_property_roles')->insert(['user_id' => $user, 'property_id' => $prop, 'role' => $role]);
}
foreach ([1, 2, 3] as $id) {
    $db->table('rooms')->insert(['id' => $id, 'property_id' => $id, 'room_number' => 'test-' . $id, 'floor' => 3, 'guesthouse_number' => 3]);
    $db->table('guests')->insert(['id' => $id, 'property_id' => $id, 'full_name' => 'Guest ' . $id]);
}
$db->table('owner_guesthouse_setups')->insertBatch([
    ['id' => 501, 'property_id' => 1, 'guesthouse' => 3, 'owner_name' => 'HomiQ Owner', 'cooperation_fee_percentage' => '80.00'],
    ['id' => 502, 'property_id' => 2, 'guesthouse' => 0, 'owner_name' => 'Legacy HS', 'cooperation_fee_percentage' => '65.00'],
    ['id' => 503, 'property_id' => 3, 'guesthouse' => 0, 'owner_name' => 'Legacy Kos', 'cooperation_fee_percentage' => '75.00'],
]);
$passed = 0;
$check = static function (bool $ok, string $message) use (&$passed): void {
    if (! $ok) throw new RuntimeException('FAIL: ' . $message);
    $passed++;
    echo '[PASS] ' . $message . "\n";
};
$reject = static function (callable $fn, string $message, ?int $code = null) use ($check): void {
    try { $fn(); } catch (InvalidArgumentException | RuntimeException $e) {
        $check($code === null || $e->getCode() === $code, $message);
        return;
    }
    $check(false, $message);
};
$fingerprint = static function (array $tables) use ($db): string {
    $rows = [];
    foreach ($tables as $table) $rows[$table] = $db->table($table)->get()->getResultArray();
    return hash('sha256', serialize($rows));
};
$unchangedTables = ['properties', 'users', 'user_property_roles', 'owner_guesthouse_setups'];
$before = $fingerprint($unchangedTables);
$setup = new Setup($db);
$owner = new Owner($db);
$hs = Scope::forUser(10, 2, Scope::OWNER_SETUP, $db);
$kos = Scope::forUser(10, 3, Scope::OWNER_SETUP, $db);
$ownerScope = Scope::forUser(11, 2, Scope::OWNER_REPORT, $db);
$homiq = Scope::forUser(10, 1, Scope::OWNER_SETUP, $db);
$check($setup->read() === null, 'Conflicting legacy values do not select a shared setup');
$check(count($setup->legacy()) === 2, 'Legacy setups remain available as explicit references');
$empty = $owner->report($hs, '2026-10');
$check($empty['summary']['setupRequired'] && $empty['summary']['investorShare'] === null && $empty['summary']['ownerSharePercentage'] === null, 'Missing setup never guesses 70% or displays zero entitlement');
$check($empty['summary']['shareBase'] == 0 && $empty['allTransactions'] === [], 'Empty month is valid');
$reject(fn () => $setup->save($ownerScope, 'Owner', '70'), 'Owner report permission cannot create setup', 403);
$reject(fn () => $setup->delete($ownerScope, 1), 'Owner report permission cannot delete setup', 403);
$reject(fn () => $setup->save($homiq, 'Owner', '70'), 'HomiQ scope cannot edit a group setup', 403);
$reject(fn () => Scope::forUser(12, 2, Scope::OWNER_REPORT, $db), 'Incomplete Finance grants cannot read owner report', 403);
foreach (['-1', '100.01', '70.001', '1e2', 'abc', '', '70,5'] as $pct) $reject(fn () => $setup->save($hs, 'Owner', $pct), 'Reject invalid percentage ' . $pct);
$reject(fn () => $setup->save($hs, '   ', '70'), 'Blank owner name is refused');
$reject(fn () => $setup->save($hs, str_repeat('a', 151), '70'), 'Overlong owner name is refused');
$setup->save($hs, 'Shared Mitra Owner', '33.33');
$firstSetup = $setup->read();
$check($firstSetup['owner_name'] === 'Shared Mitra Owner' && (float) $firstSetup['owner_share_percentage'] === 33.33, 'Shared setup saves exact agreed percentage');
$setup->save($kos, 'Shared Mitra Owner Updated', '33.33', (int) $firstSetup['id']);
$check((int) $setup->read()['id'] === (int) $firstSetup['id'] && $setup->read()['owner_name'] === 'Shared Mitra Owner Updated' && $db->table('finance_report_group_owner_setups')->countAllResults() === 1, 'Saving from Kos updates the same single row');
$reject(fn () => $setup->save($kos, 'Wrong', '0', 501), 'Forged HomiQ setup ID cannot edit shared setup');
$reject(fn () => $setup->delete($hs, 501), 'Forged HomiQ setup ID cannot delete shared setup');
$check($fingerprint($unchangedTables) === $before, 'CRUD preserves HomiQ, legacy configurations, users and grants');

// Independent source amounts: HS 1,000 rental + 100 extra bed + 100 charge + 200 company services.
// Kos 500 rental + 50 charge + 50 extra bed, all owner-eligible.
foreach ([[101, 2, 1400], [102, 3, 600], [103, 1, 9000], [104, 2, 50]] as [$id, $prop, $amount]) {
    $db->table('reservations')->insert(['id' => $id, 'property_id' => $prop, 'room_id' => $prop, 'guest_id' => $prop, 'total_amount' => $amount, 'status' => 'Confirmed', 'order_id' => 'TEST-' . $id, 'check_in_date' => '2026-10-01', 'check_out_date' => '2026-11-01']);
}
foreach ([[101, 'Room', 1000], [101, 'Extra Bed', 100], [101, 'Early Check In', 100], [101, 'Breakfast', 100], [101, 'Laundry', 100], [102, 'Room', 500], [102, 'Late Checkout', 50], [102, 'Biaya Extra Bed', 50]] as [$res, $name, $amount]) {
    $db->table('invoice_items')->insert(['reservation_id' => $res, 'item_name' => $name, 'amount' => $amount]);
}
foreach ([[201, 101, 700, '2026-10-01', 'Paid'], [202, 101, 700, '2026-10-31', 'Paid'], [203, 102, 600, '2026-10-05', 'Paid'], [204, 103, 9000, '2026-10-05', 'Paid'], [205, 104, 50, '2026-11-01', 'Paid'], [206, 104, 50, '2026-10-05', 'Pending'], [207, 104, -50, '2026-10-05', 'Paid']] as [$id, $res, $amount, $date, $status]) {
    $db->table('payments')->insert(['id' => $id, 'reservation_id' => $res, 'amount' => $amount, 'payment_date' => $date, 'status' => $status, 'type' => $id === 201 ? 'Down_Payment' : 'Completed', 'proof_path' => $id === 201 ? 'synthetic-proof.jpg' : null]);
}
foreach ([[301, 2, 2000, 'Supplies', 'Approved'], [302, 3, 100, 'Kebutuhan Operasional Guesthouse', 'Pending'], [303, 1, 800, 'Supplies', 'Approved'], [304, 2, 900, 'Supplies', 'Rejected'], [305, 2, 900, 'Fee Kerjasama', 'Approved']] as [$id, $prop, $amount, $category, $status]) {
    $db->table('expenses')->insert(['id' => $id, 'property_id' => $prop, 'guesthouse' => 3, 'amount' => $amount, 'category' => $category, 'status' => $status, 'description' => 'Cost ' . $id, 'date_incurred' => '2026-10-31']);
}
$db->table('expense_guesthouses')->insertBatch([['expense_id' => 301, 'guesthouse' => 1], ['expense_id' => 301, 'guesthouse' => 2]]);
$operationalTables = ['reservations', 'payments', 'invoice_items', 'expenses', 'expense_guesthouses', 'rooms', 'guests'];
$operationalBefore = $fingerprint($operationalTables);
$report = $owner->report($hs, '2026-10');
$check(serialize($report) === serialize($owner->report($kos, '2026-10')), 'Either Mitra active property produces the identical owner report');
$s = $report['summary'];
$check($s['sourceIncome'][2] == 1200 && $s['sourceIncome'][3] == 600 && $s['shareBase'] == 1800, 'Eligible source revenue sums once to combined owner base');
$check($s['incomeCategories']['Penyewaan'] == 1500 && $s['incomeCategories']['Extra Bed'] == 150 && $s['incomeCategories']['Charge Tamu'] == 150, 'Extra Bed in both properties belongs to owner, and guest charges reconcile');
$check($s['totalExpense'] == 2100 && $s['netProfit'] == -300, 'Mitra costs are not divided by legacy GH allocations; loss is displayed');
$check($s['investorShare'] == 599.94 && $s['managerShare'] == 1200.06, 'Owner split uses the combined eligible omzet despite operating loss');
$check(array_sum(array_column(array_filter($report['allTransactions'], fn ($t) => $t['flow'] === 'Pemasukan'), 'amount')) == $s['totalIncome'], 'Income transactions equal category total');
$check($s['expenseCategories']['Kebutuhan Operasional Kos'] == 100, 'Kos category is mapped by source, not active dropdown');
$check(count($report['allTransactions']) === 5 && array_diff(array_column($report['allTransactions'], 'property_id'), [2, 3]) === [], 'No HomiQ, Pending, negative/refund income or next-month transaction enters owner base');
$check($report['allTransactions'][0]['date'] === '2026-10-31', 'Global chronological ordering across properties');
$check($owner->report($ownerScope, '2026-10')['summary'] === $s, 'Owner role reads the same combined numbers as Finance staff');
$check($owner->report($hs, '2026-11')['summary']['totalIncome'] == 50, 'No-invoice fallback uses Paid amount and correct month');
$check($owner->report($hs, '2026-12')['summary']['investorShare'] == 0, 'Configured empty month has zero entitlement');
$reject(fn () => $owner->report($homiq, '2026-10'), 'Group owner service refuses HomiQ scope', 403);
foreach (['0', '100', '33.33'] as $pct) {
    $setup->save($hs, 'Shared Mitra Owner', $pct);
    $values = $owner->report($hs, '2026-10')['summary'];
    $expected = ['0' => 0, '100' => 1800, '33.33' => 599.94][$pct];
    $check($values['investorShare'] == $expected && round($values['investorShare'] + $values['managerShare'], 2) == 1800, 'Conserved aggregate split at ' . $pct . '%');
}
$check(Owner::split(2, '33.33') === ['owner' => 1, 'manager' => 1], 'One split on the aggregate differs correctly from rounding each source separately');
$check(Owner::allocate(1, [2 => 1, 3 => 1]) === [2 => 1, 3 => 0], 'Tie cents allocate deterministically to Homestay');
$check(Owner::allocate(99999999999999, ['a' => 99999999999998, 'b' => 1]) === ['a' => 99999999999998, 'b' => 1], 'Large proportional amounts do not overflow or lose cents');
$check(Owner::portions(100, ['rental' => 100, 'extra' => 0, 'charge' => 0, 'direct' => 100]) === ['rental' => 50, 'extra' => 0, 'charge' => 0, 'direct' => 50], 'Deposit composition recognizes only its proportional service share');
$check(Owner::portions(100, ['rental' => 120, 'extra' => 0, 'charge' => 0, 'direct' => -20])['rental'] === 100, 'Negative adjustments cannot create more recognized revenue than the payment');

// Add tiny transactions to exercise pagination and single rounding on the combined basis.
for ($n = 0; $n < 12; $n++) {
    $db->table('payments')->insert(['id' => 220 + $n, 'reservation_id' => 104, 'amount' => '0.01', 'payment_date' => '2026-10-10', 'status' => 'Paid', 'type' => 'Completed']);
}
$paginated = $owner->report($hs, '2026-10', 999);
$check($paginated['pagination'] === ['page' => 2, 'perPage' => 10, 'totalItems' => 17, 'totalPages' => 2] && count($paginated['transactions']) === 7, 'Global pagination clamps to the last page and retains all export rows');
$check(count($paginated['allTransactions']) === 17, 'Export data is not limited by pagination');
$db->table('payments')->where('id >=', 220)->delete();

// Confirmed cash differs from Paid report: DP only, net 650+withheld50, and Kos 600.
$db->table('companies')->insertBatch([['id' => 1, 'is_active' => 1], ['id' => 2, 'is_active' => 1]]);
$db->table('company_user_roles')->insertBatch([['user_id' => 10, 'company_id' => 1, 'role' => 'Company Viewer'], ['user_id' => 10, 'company_id' => 2, 'role' => 'Company Viewer']]);
foreach ([[1, 1], [1, 2], [1, 3], [2, 2]] as [$company, $prop]) $db->table('company_properties')->insert(['company_id' => $company, 'property_id' => $prop]);
foreach ([[401, 1, null, '2026-10-02'], [402, 1, null, '2026-10-06'], [403, 1, null, '2026-10-31'], [404, 1, 403, '2026-10-31'], [405, 2, null, '2026-10-02'], [406, 1, null, '2026-11-01']] as [$id, $company, $reverses, $date]) {
    $db->table('cash_transactions')->insert(['id' => $id, 'company_id' => $company, 'reverses_id' => $reverses, 'effective_at' => $date . ' 12:00:00', 'classification' => 'external']);
}
foreach ([[401, 1, 201, 650, 50], [402, 1, 203, 600, 0], [403, 1, 202, 700, 0], [405, 2, 201, 700, 0], [406, 1, 202, 700, 0]] as [$tx, $company, $payment, $amount, $fee]) {
    $db->table('cash_payment_receipts')->insert(['transaction_id' => $tx, 'company_id' => $company, 'payment_id' => $payment, 'amount' => $amount, 'withheld_fee' => $fee, 'source_amount' => $amount + $fee]);
}
$confirmed = $owner->confirmedHolding(10, 1, '2026-10');
$check($confirmed['bases'] === [2 => 60000, 3 => 60000] && $confirmed['direct'] === [2 => 10000, 3 => 0], 'Holding treats Extra Bed in both properties as owner-eligible while excluding direct services');
$check(array_sum($confirmed['owner']) === 39996 && array_sum($confirmed['manager']) === 80004, 'Confirmed group entitlement rounds once at 33.33%');
$check($confirmed['owner'][2] + $confirmed['manager'][2] === 60000 && $confirmed['owner'][3] + $confirmed['manager'][3] === 60000, 'Holding detail allocation conserves source bases');
$check($owner->confirmedHolding(10, 2, '2026-10')['error'] !== null, 'One company member never borrows revenue from another company');
$reject(fn () => $owner->confirmedHolding(11, 1, '2026-10'), 'Property owner permission does not imply holding view');
$db->table('property_finance_agreements')->insert(['company_id' => 1, 'property_id' => 2, 'effective_from' => '2026-01-01', 'status' => 'approved', 'agreement_type' => 'management', 'calculation_base' => 'gross_revenue', 'owner_share_pct' => 99, 'company_share_pct' => 1, 'fixed_fee_monthly' => 999]);
$holding = new Holding($db);
$monthly = $holding->monthlyReport(10, 1, '2026-10');
$holdingHs = array_values(array_filter($monthly['properties'], fn ($p) => $p['property_id'] === 2))[0];
$check($monthly['bridge']['total_owner_share'] === '399.96' && $monthly['bridge']['total_company_fee'] === '800.04' && $monthly['bridge']['total_direct_services'] === '100.00', 'Actual holding monthlyReport reads shared setup and conserves combined rights');
$check($holdingHs['owner_share_base'] === '600.00' && str_contains($holdingHs['agreement_note'], 'gabungan'), 'Holding displays source eligible basis and shared configuration origin');
$filtered = $holding->monthlyReport(10, 1, '2026-10', 2);
$check($filtered['properties'][0] === $holdingHs, 'Filtering holding to Homestay does not recalculate its allocated share');
$setup->delete($kos, (int) $firstSetup['id']);
$afterDelete = $holding->monthlyReport(10, 1, '2026-10');
$check(count($afterDelete['warnings']['unconfigured_properties']) === 2 && $afterDelete['bridge']['total_owner_share'] === '0.00', 'Deleted shared setup cannot fall back to conflicting legacy or individual agreements');
$check($afterDelete['bridge']['total_direct_services'] === '100.00', 'Direct company services remain recognized while owner setup is missing');
$setup->save($kos, 'Shared Mitra Owner', '33.33');
$db->table('finance_report_groups')->where('code', Scope::GROUP_CODE)->update(['is_active' => 0]);
$check($owner->confirmedHolding(10, 1, '2026-10')['error'] !== null, 'Inactive group prevents holding allocation');
$db->table('finance_report_groups')->where('code', Scope::GROUP_CODE)->update(['is_active' => 1]);

// Render the real controllers using the isolated default database and a synthetic session.
helper(['url', 'form']);
$session = Services::session();
$session->set(['userId' => 10, 'role' => 'Manager', 'activePropertyId' => 2, 'activePropertyName' => 'Homestay Mitra', 'activePropertyCode' => 'HOMESTAY_MITRA', 'isLoggedIn' => true]);
$makeController = static function (array $get = [], array $post = []) {
    $request = new IncomingRequest(config('App'), new SiteURI(config('App'), 'http://localhost:8080/finance/owner'), null, new UserAgent());
    $request->setGlobal('get', $get);
    $request->setGlobal('post', $post);
    Services::injectMock('request', $request);
    $response = Services::response(null, false);
    Services::injectMock('response', $response);
    $controller = new FinanceController();
    $controller->initController($request, $response, Services::logger());
    return $controller;
};
$html = $makeController(['month' => '2026-10'])->owner();
$check(is_string($html) && str_contains($html, 'Homestay Mitra &amp; Kos Mitra') && str_contains($html, 'Asal Properti'), 'Real owner page renders combined scope and source column');
$check(! str_contains($html, 'Kamar-malam terjual'), 'Combined Finance does not invent an operational occupancy across incompatible units');
$setupHtml = $makeController()->ownerSetup();
$check(is_string($setupHtml) && str_contains($setupHtml, 'Setup Owner Bersama') && str_contains($setupHtml, 'Legacy HS'), 'Real setup page renders shared form and legacy conflict reference');
$previewDir = getenv('MITRA_STAGE4_PREVIEW_DIR');
if ($previewDir !== false && $previewDir !== '') {
    if (! is_dir($previewDir)) mkdir($previewDir, 0700, true);
    foreach (['owner' => $html, 'setup' => $setupHtml] as $name => $previewHtml) {
        file_put_contents($previewDir . '/' . $name . '.html', $previewHtml);
    }
}
$download = $makeController(['month' => '2026-10'])->ownerExport();
$download->buildHeaders();
ob_start(); $download->sendBody(); $xml = ob_get_clean();
$check(str_contains($download->getHeaderLine('Content-Disposition'), 'owner-mitra-combined-2026-10.xls'), 'Combined export file name is independent of selected property');
$check(simplexml_load_string($xml) !== false && str_contains($xml, 'Asal Properti') && str_contains($xml, 'Homestay Mitra') && str_contains($xml, 'Kos Mitra'), 'Excel XML parses and contains both tagged source properties');
$session->set('activePropertyId', 3);
$downloadKos = $makeController(['month' => '2026-10'])->ownerExport();
ob_start(); $downloadKos->sendBody(); $kosXml = ob_get_clean();
$check($kosXml === $xml, 'Excel export is identical from Kos and Homestay');
$check(str_contains($xml, '#,##0.00') && str_contains($html, 'Rp599,94'), 'Combined UI and Excel preserve fractional cents in entitlement');
$sharedId = (int) $setup->read()['id'];
$check($makeController(['edit' => 501])->ownerSetup()->getStatusCode() === 404, 'Forged edit ID cannot render a HomiQ setup in Mitra');
$saved = $makeController([], ['id' => $sharedId, 'owner_name' => 'Owner & Partner', 'cooperation_fee_percentage' => '70.25', 'property_id' => 1, 'group_id' => 999, 'guesthouse' => 5])->saveOwnerSetup();
$check($saved->getStatusCode() === 302 && $setup->read()['owner_name'] === 'Owner & Partner' && (float) $setup->read()['owner_share_percentage'] === 70.25, 'Real save controller updates scoped setup and ignores forged property/group/GH inputs');
$invalid = $makeController([], ['id' => $sharedId, 'owner_name' => 'Invalid', 'cooperation_fee_percentage' => '70.123'])->saveOwnerSetup();
$check($invalid->getStatusCode() === 302 && (float) $setup->read()['owner_share_percentage'] === 70.25, 'Real save controller rejects excess precision without changing setup');
$check($makeController()->deleteOwnerSetup(501)->getStatusCode() === 302 && $setup->read() !== null, 'Real delete controller cannot use a HomiQ setup ID');
$check($makeController()->deleteOwnerSetup($sharedId)->getStatusCode() === 302 && $setup->read() === null, 'Real delete controller removes only the scoped shared row');
$missingHtml = $makeController(['month' => '2026-10'])->owner();
$missingDownload = $makeController(['month' => '2026-10'])->ownerExport();
ob_start(); $missingDownload->sendBody(); $missingXml = ob_get_clean();
$check(str_contains($missingHtml, 'Setup diperlukan') && str_contains($missingHtml, 'Isi Setup Owner'), 'Missing setup UI makes the required action explicit');
$check(str_contains($missingXml, 'bagi hasil belum dihitung') && ! str_contains($missingXml, '(70%)'), 'Missing setup Excel reports pending entitlement rather than a guessed percentage');
$makeController([], ['owner_name' => 'Shared Mitra Owner', 'cooperation_fee_percentage' => '33.33'])->saveOwnerSetup();
$session->remove('_ci_old_input');
$session->set(['userId' => 12, 'role' => 'Finance', 'activePropertyId' => 2]);
foreach (['owner', 'ownerExport', 'ownerSetup', 'saveOwnerSetup'] as $action) {
    $response = $makeController(['month' => '2026-10'], ['owner_name' => 'Forged', 'cooperation_fee_percentage' => '100'])->$action();
    $check($response->getStatusCode() === 403, 'Controller ' . $action . ' denies incomplete grants');
}
$check($makeController()->deleteOwnerSetup((int) $setup->read()['id'])->getStatusCode() === 403, 'Delete controller denies incomplete grants');
$session->set(['userId' => 10, 'role' => 'Manager', 'activePropertyId' => 2]);
$grant = $db->table('user_property_roles')->where('user_id', 10)->where('property_id', 3)->get()->getRowArray();
$db->table('user_property_roles')->where('user_id', 10)->where('property_id', 3)->delete();
$check($makeController()->owner()->getStatusCode() === 403, 'Fresh controller resolution observes grant revocation despite stale session');
$db->table('user_property_roles')->insert($grant);
$session->set(['userId' => 13, 'role' => 'Manager', 'activePropertyId' => 1, 'activePropertyName' => 'Adiputra Guesthouse by HomiQ']);
$homiqHtml = $makeController(['month' => '2026-10', 'guesthouse' => 1])->owner();
$check(str_contains($homiqHtml, 'Laba/Rugi GH 3') && str_contains($homiqHtml, 'HomiQ Owner'), 'Fresh Owner GH assignment locks GH3 even with stale Manager session and forged GH query');
$homiqExport = $makeController(['month' => '2026-10', 'guesthouse' => 1])->ownerExport();
$homiqExport->buildHeaders();
ob_start(); $homiqExport->sendBody(); $homiqXml = ob_get_clean();
$check(str_contains($homiqExport->getHeaderLine('Content-Disposition'), 'owner-property-1-gh-3-2026-10.xls') && ! str_contains($homiqXml, 'Asal Properti'), 'HomiQ export retains GH file name and original columns');
$check(str_contains($homiqXml, '>6560<') && str_contains($homiqHtml, 'Rp6.560'), 'HomiQ still splits positive net profit by the assigned GH percentage');
// Nonzero HomiQ holding regression is independent of the shared setup.
$db->table('cash_transactions')->insert(['id' => 407, 'company_id' => 1, 'effective_at' => '2026-10-05 12:00:00', 'classification' => 'external']);
$db->table('cash_payment_receipts')->insert(['transaction_id' => 407, 'company_id' => 1, 'payment_id' => 204, 'amount' => 9000, 'withheld_fee' => 0, 'source_amount' => 9000]);
$homiqHolding = $holding->monthlyReport(10, 1, '2026-10', 1)['properties'][0];
$check($homiqHolding['owner_share'] === '7200.00' && $homiqHolding['company_fee'] === '1800.00', 'HomiQ holding keeps its existing multi-GH confirmed-revenue split');
$setup->save($kos, 'Shared Mitra Owner', '100');
$check($holding->monthlyReport(10, 1, '2026-10', 1)['properties'][0] === $homiqHolding, 'Changing shared Mitra percentage cannot change HomiQ holding');
$session->set(['userId' => 10, 'role' => 'Manager', 'activePropertyId' => 1]);
$check(str_contains($makeController()->ownerSetup(), 'Daftar Setup Owner') && ! str_contains($makeController()->ownerSetup(), 'Legacy HS'), 'HomiQ setup retains per-GH form and isolated entries');
$check($fingerprint($unchangedTables) === $before, 'All checks preserve legacy setup and grants');
$check($fingerprint($operationalTables) === $operationalBefore, 'Reports and shared setup never mutate operations');
$db->close();
$output = ob_get_clean();
echo $output . "\nStage 4: {$passed} checks passed (" . ($clone === '' ? 'SQLite memory' : 'isolated MariaDB') . ").\n";
