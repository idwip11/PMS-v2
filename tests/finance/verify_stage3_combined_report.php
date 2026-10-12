<?php

/**
 * Stage 3 Verification: Combined General Finance Report for Homestay & Kos Mitra.
 * Runs in-memory with SQLite without mutating application/live databases.
 */
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
use App\Libraries\FinanceReportService as Service;
use App\Libraries\PropertyContext;
use App\Libraries\RoleAccess;

$db = Config\Database::connect([
    'DBDriver' => 'SQLite3',
    'database' => ':memory:',
    'DBPrefix' => '',
    'DBDebug' => true,
    'foreignKeys' => true,
], false);

// 1. Create schema
$db->query('CREATE TABLE properties (id INTEGER PRIMARY KEY, code VARCHAR(40) UNIQUE NOT NULL, name VARCHAR(150) NOT NULL, is_active INTEGER NOT NULL, pricing_mode VARCHAR(32) DEFAULT "room_nightly", base_rate DECIMAL(10,2) DEFAULT 0)');
$db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, username VARCHAR(100) UNIQUE NOT NULL, full_name VARCHAR(150), role VARCHAR(50), password_hash VARCHAR(255), created_at DATETIME, updated_at DATETIME)');
$db->query('CREATE TABLE user_property_roles (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, property_id INTEGER NOT NULL, role VARCHAR(50) NOT NULL, UNIQUE(user_id, property_id))');
$db->query('CREATE TABLE rooms (id INTEGER PRIMARY KEY, property_id INTEGER NOT NULL, room_number VARCHAR(20) NOT NULL, room_type VARCHAR(50), floor INTEGER, guesthouse_number INTEGER, price_per_night DECIMAL(10,2) DEFAULT 0, status VARCHAR(30) DEFAULT "Available")');
$db->query('CREATE TABLE guests (id INTEGER PRIMARY KEY, property_id INTEGER NOT NULL, full_name VARCHAR(150) NOT NULL, email VARCHAR(100), phone VARCHAR(30))');
$db->query('CREATE TABLE reservations (id INTEGER PRIMARY KEY, property_id INTEGER NOT NULL, room_id INTEGER NOT NULL, guest_id INTEGER NOT NULL, order_id VARCHAR(50), booking_group_id VARCHAR(50), check_in_date DATE NOT NULL, check_out_date DATE NOT NULL, total_amount DECIMAL(10,2) NOT NULL, paid_amount DECIMAL(10,2) DEFAULT 0, status VARCHAR(30) NOT NULL)');
$db->query('CREATE TABLE payments (id INTEGER PRIMARY KEY, reservation_id INTEGER NOT NULL, order_id VARCHAR(50), amount DECIMAL(10,2) NOT NULL, payment_date DATE, payment_method VARCHAR(50), notes TEXT, type VARCHAR(50), status VARCHAR(30) NOT NULL, proof_path VARCHAR(255), created_at DATETIME)');
$db->query('CREATE TABLE expenses (id INTEGER PRIMARY KEY, property_id INTEGER NOT NULL, description VARCHAR(255) NOT NULL, amount DECIMAL(10,2) NOT NULL, category VARCHAR(100) NOT NULL, date_incurred DATE NOT NULL, status VARCHAR(30) NOT NULL)');
$db->query('CREATE TABLE finance_report_groups (id INTEGER PRIMARY KEY, code VARCHAR(40) UNIQUE NOT NULL, name VARCHAR(150) NOT NULL, is_active INTEGER NOT NULL)');
$db->query('CREATE TABLE finance_report_group_properties (group_id INTEGER NOT NULL, property_id INTEGER NOT NULL, PRIMARY KEY(group_id, property_id))');
$db->query('CREATE TABLE finance_report_group_owner_setups (id INTEGER PRIMARY KEY, group_id INTEGER UNIQUE NOT NULL, owner_name VARCHAR(150) NOT NULL, owner_share_percentage DECIMAL(5,2) NOT NULL)');

// 2. Seed properties & group
$db->table('properties')->insert(['id' => 1, 'code' => 'HOMIQ', 'name' => 'Adiputra Guesthouse by HomiQ', 'is_active' => 1, 'pricing_mode' => 'room_nightly', 'base_rate' => 0]);
$db->table('properties')->insert(['id' => 2, 'code' => 'HOMESTAY_MITRA', 'name' => 'Homestay Mitra', 'is_active' => 1, 'pricing_mode' => 'whole_home_nightly', 'base_rate' => 600000]);
$db->table('properties')->insert(['id' => 3, 'code' => 'KOS_MITRA', 'name' => 'Kos Mitra', 'is_active' => 1, 'pricing_mode' => 'room_monthly', 'base_rate' => 500000]);

$db->table('finance_report_groups')->insert(['id' => 1, 'code' => 'MITRA_COMBINED', 'name' => 'Mitra — Homestay & Kos', 'is_active' => 1]);
$db->table('finance_report_group_properties')->insert(['group_id' => 1, 'property_id' => 2]);
$db->table('finance_report_group_properties')->insert(['group_id' => 1, 'property_id' => 3]);

// 3. Seed users
$db->table('users')->insert(['id' => 10, 'username' => 'manager_mitra', 'full_name' => 'Pak Manager', 'role' => 'Manager']);
$db->table('user_property_roles')->insert(['user_id' => 10, 'property_id' => 2, 'role' => 'Manager']);
$db->table('user_property_roles')->insert(['user_id' => 10, 'property_id' => 3, 'role' => 'Manager']);

$db->table('users')->insert(['id' => 11, 'username' => 'manager_homiq', 'full_name' => 'Pak Homiq', 'role' => 'Manager']);
$db->table('user_property_roles')->insert(['user_id' => 11, 'property_id' => 1, 'role' => 'Manager']);

$db->table('users')->insert(['id' => 12, 'username' => 'staff_hs_only', 'full_name' => 'Staff HS', 'role' => 'Staff']);
$db->table('user_property_roles')->insert(['user_id' => 12, 'property_id' => 2, 'role' => 'Finance']);

// Seed rooms
$db->table('rooms')->insert(['id' => 1, 'property_id' => 1, 'room_number' => '101', 'floor' => 1, 'room_type' => 'Standard']);
$db->table('rooms')->insert(['id' => 2, 'property_id' => 2, 'room_number' => '01', 'floor' => null, 'room_type' => 'Homestay']);
$db->table('rooms')->insert(['id' => 3, 'property_id' => 2, 'room_number' => '02', 'floor' => null, 'room_type' => 'Homestay']);
$db->table('rooms')->insert(['id' => 4, 'property_id' => 2, 'room_number' => '03', 'floor' => null, 'room_type' => 'Homestay']);
$db->table('rooms')->insert(['id' => 5, 'property_id' => 3, 'room_number' => 'k1', 'floor' => null, 'room_type' => 'Kos']);

// Seed guests
$db->table('guests')->insert(['id' => 1, 'property_id' => 1, 'full_name' => 'Tamu Homiq']);
$db->table('guests')->insert(['id' => 2, 'property_id' => 2, 'full_name' => 'Tamu Homestay']);
$db->table('guests')->insert(['id' => 3, 'property_id' => 3, 'full_name' => 'Penyewa Kos']);

$passCount = 0;
$check = static function (bool $ok, string $desc) use (&$passCount): void {
    if (! $ok) {
        throw new RuntimeException('FAIL: ' . $desc);
    }
    $passCount++;
    echo "[PASS] " . $desc . "\n";
};

// TEST 1: Scope resolution
$mitraScopeFrom2 = Scope::forUser(10, 2, Scope::REPORT, $db);
$mitraScopeFrom3 = Scope::forUser(10, 3, Scope::REPORT, $db);
$check($mitraScopeFrom2->isCombined() === true, 'Scope from Homestay (ID 2) is combined group');
$check($mitraScopeFrom3->isCombined() === true, 'Scope from Kos (ID 3) is combined group');
$check($mitraScopeFrom2->propertyIds === [2, 3], 'Scope from ID 2 contains property IDs [2, 3]');
$check($mitraScopeFrom3->propertyIds === [2, 3], 'Scope from ID 3 contains property IDs [2, 3]');
$check($mitraScopeFrom2->label === 'Mitra — Homestay & Kos', 'Scope label is Mitra — Homestay & Kos');

$homiqScope = Scope::forUser(11, 1, Scope::REPORT, $db);
$check($homiqScope->isCombined() === false, 'Homiq scope is not combined');
$check($homiqScope->propertyIds === [1], 'Homiq scope contains property ID [1]');

// Incomplete grant fails closed (403)
$denied = false;
try {
    Scope::forUser(12, 2, Scope::REPORT, $db);
} catch (RuntimeException $e) {
    $denied = ($e->getCode() === 403);
}
$check($denied, 'User with grant on Homestay only is rejected with 403');

// TEST 2: Empty Period Reporting
$emptyReport2 = Service::getPropertyReportData($mitraScopeFrom2, '2026-10', 1, $db);
$emptyReport3 = Service::getPropertyReportData($mitraScopeFrom3, '2026-10', 1, $db);
$check($emptyReport2['summary']['revenue'] === 0.0, 'Empty period revenue is 0');
$check($emptyReport2['summary']['expenses'] === 0.0, 'Empty period expenses is 0');
$check($emptyReport2['summary']['netProfit'] === 0.0, 'Empty period net profit is 0');
$check($emptyReport2['summary']['outstanding'] === 0.0, 'Empty period outstanding is 0');
$check($emptyReport2['transactions'] === [], 'Empty period transactions list is empty');
$check(count($emptyReport2['cashFlow']['labels']) === 6, 'Cash flow has 6 monthly periods');
$check($emptyReport2['paymentMethods'] === [
    ['name' => 'Cash', 'count' => 0, 'percentage' => 0],
    ['name' => 'Transfer', 'count' => 0, 'percentage' => 0],
    ['name' => 'Credit Card', 'count' => 0, 'percentage' => 0],
    ['name' => 'OTA', 'count' => 0, 'percentage' => 0],
], 'Payment methods accommodates Cash, Transfer, Credit Card, and OTA for combined Mitra');

// Switching property yields identical empty report
$check(serialize($emptyReport2) === serialize($emptyReport3), 'Empty report accessed from ID 2 and ID 3 is 100% identical');

// TEST 3: Homestay-only transactions in period
// Homestay Booking: 2 nights (2026-10-05 to 2026-10-07), Rp 1.200.000, Paid via OTA
$db->table('reservations')->insert([
    'id' => 101, 'property_id' => 2, 'room_id' => 2, 'guest_id' => 2,
    'order_id' => 'HMT-20261005-01', 'booking_group_id' => 'GRP-01',
    'check_in_date' => '2026-10-05', 'check_out_date' => '2026-10-07',
    'total_amount' => 1200000, 'paid_amount' => 1200000, 'status' => 'Confirmed'
]);
$db->table('payments')->insert([
    'id' => 201, 'reservation_id' => 101, 'order_id' => 'HMT-20261005-01',
    'amount' => 1200000, 'payment_date' => '2026-10-05', 'payment_method' => 'OTA',
    'type' => 'Completed', 'status' => 'Paid'
]);
// Homestay Expense: Rp 150.000
$db->table('expenses')->insert([
    'id' => 301, 'property_id' => 2, 'description' => 'Gas & Galon Homestay',
    'amount' => 150000, 'category' => 'Kebutuhan Operasional Homestay',
    'date_incurred' => '2026-10-06', 'status' => 'Approved'
]);

$reportHomestayOnly = Service::getPropertyReportData($mitraScopeFrom2, '2026-10', 1, $db);
$check($reportHomestayOnly['summary']['revenue'] === 1200000.0, 'Revenue reflects Homestay payment');
$check($reportHomestayOnly['summary']['expenses'] === 150000.0, 'Expenses reflect Homestay expense');
$check($reportHomestayOnly['summary']['netProfit'] === 1050000.0, 'Net profit is 1.200.000 - 150.000 = 1.050.000');
$check(count($reportHomestayOnly['transactions']) === 2, 'Two transactions present');
$t1 = $reportHomestayOnly['transactions'][0]; // Expense on 2026-10-06
$t2 = $reportHomestayOnly['transactions'][1]; // Payment on 2026-10-05
$check($t1['type'] === 'Expense' && $t1['property_name'] === 'Homestay Mitra', 'First transaction is Homestay Expense');
$check($t2['type'] === 'Income' && $t2['property_name'] === 'Homestay Mitra', 'Second transaction is Homestay Income');
$check($t2['duration'] === '2 Malam', 'Homestay income duration formatted in Malam');
$check($t2['method'] === 'OTA', 'OTA payment method recorded');

// TEST 4: Kos-only transactions added
// Kos Booking: 1 month (2026-10-10 to 2026-11-10), Rp 500.000, Paid via Transfer
$db->table('reservations')->insert([
    'id' => 102, 'property_id' => 3, 'room_id' => 5, 'guest_id' => 3,
    'order_id' => 'KSM-20261010-01', 'booking_group_id' => null,
    'check_in_date' => '2026-10-10', 'check_out_date' => '2026-11-10',
    'total_amount' => 500000, 'paid_amount' => 500000, 'status' => 'Confirmed'
]);
$db->table('payments')->insert([
    'id' => 202, 'reservation_id' => 102, 'order_id' => 'KSM-20261010-01',
    'amount' => 500000, 'payment_date' => '2026-10-10', 'payment_method' => 'Transfer',
    'type' => 'Completed', 'status' => 'Paid'
]);
// Kos Expense: Rp 50.000 with legacy category name
$db->table('expenses')->insert([
    'id' => 302, 'property_id' => 3, 'description' => 'Sapu & Pel Kos',
    'amount' => 50000, 'category' => 'Kebutuhan Operasional Guesthouse',
    'date_incurred' => '2026-10-11', 'status' => 'Approved'
]);

// TEST 5: Combined Reporting (Both Homestay & Kos present)
$combined2 = Service::getPropertyReportData($mitraScopeFrom2, '2026-10', 1, $db);
$combined3 = Service::getPropertyReportData($mitraScopeFrom3, '2026-10', 1, $db);

// Verifying exact mathematical aggregation:
$check($combined2['summary']['revenue'] === 1700000.0, 'Combined Revenue = 1.200.000 + 500.000 = 1.700.000');
$check($combined2['summary']['expenses'] === 200000.0, 'Combined Expenses = 150.000 + 50.000 = 200.000');
$check($combined2['summary']['netProfit'] === 1500000.0, 'Combined Net Profit = 1.700.000 - 200.000 = 1.500.000');
$check($combined2['summary']['outstanding'] === 0.0, 'All confirmed bookings are fully paid');

// Checking property switch consistency
$check(serialize($combined2) === serialize($combined3), 'Combined report accessed from Homestay vs Kos is 100% identical in every field');

// Checking transaction order & attributes
$check(count($combined2['transactions']) === 4, 'Total 4 transactions in period');
$check($combined2['transactions'][0]['date'] === '2026-10-11', 'Transactions sorted descending by date');
$check($combined2['transactions'][0]['method'] === 'Kebutuhan Operasional Kos', 'Kos expense category mapped to Kebutuhan Operasional Kos');
$check($combined2['transactions'][0]['property_name'] === 'Kos Mitra', 'Kos expense has Asal Properti Kos Mitra');

$kosIncome = null;
$hsIncome = null;
foreach ($combined2['transactions'] as $tx) {
    if ($tx['reference'] === 'KSM-20261010-01') $kosIncome = $tx;
    if ($tx['reference'] === 'GRP-01' || $tx['reference'] === 'HMT-20261005-01') $hsIncome = $tx;
}
$check($kosIncome !== null && $kosIncome['duration'] === '1 Bulan', 'Kos income shows duration 1 Bulan');
$check($kosIncome['property_name'] === 'Kos Mitra', 'Kos income shows property_name Kos Mitra');
$check($hsIncome !== null && $hsIncome['duration'] === '2 Malam', 'Homestay income shows duration 2 Malam');
$check($hsIncome['property_name'] === 'Homestay Mitra', 'Homestay income shows property_name Homestay Mitra');
$check($hsIncome['room'] === '1 Rumah', 'Homestay income displays 1 Rumah instead of separate room');

// Checking Payment Methods breakdown
$pmMap = [];
foreach ($combined2['paymentMethods'] as $pm) {
    $pmMap[$pm['name']] = $pm['count'];
}
$check($pmMap['OTA'] === 1, 'OTA payment method counted from Homestay');
$check($pmMap['Transfer'] === 1, 'Transfer payment method counted from Kos');
$check($pmMap['Cash'] === 0, 'Cash payment method counted 0');

// TEST 6: Outstanding calculation test
// Add an unpaid reservation on Kos: total Rp 1.000.000, paid Rp 0
$db->table('reservations')->insert([
    'id' => 103, 'property_id' => 3, 'room_id' => 5, 'guest_id' => 3,
    'order_id' => 'KSM-20261020-02', 'booking_group_id' => null,
    'check_in_date' => '2026-10-20', 'check_out_date' => '2026-12-20',
    'total_amount' => 1000000, 'paid_amount' => 0, 'status' => 'Confirmed'
]);
$reportWithOutstanding = Service::getPropertyReportData($mitraScopeFrom2, '2026-10', 1, $db);
$check($reportWithOutstanding['summary']['outstanding'] === 1000000.0, 'Outstanding accurately captures 1.000.000 unpaid on Kos');

// TEST 7: Isolation from HomiQ (Property ID 1)
// Add high-value transactions on Homiq (Property 1)
$db->table('reservations')->insert([
    'id' => 999, 'property_id' => 1, 'room_id' => 1, 'guest_id' => 1,
    'order_id' => 'HMQ-20261001-01', 'booking_group_id' => null,
    'check_in_date' => '2026-10-01', 'check_out_date' => '2026-10-03',
    'total_amount' => 50000000, 'paid_amount' => 50000000, 'status' => 'Confirmed'
]);
$db->table('payments')->insert([
    'id' => 999, 'reservation_id' => 999, 'order_id' => 'HMQ-20261001-01',
    'amount' => 50000000, 'payment_date' => '2026-10-01', 'payment_method' => 'Credit Card',
    'type' => 'Completed', 'status' => 'Paid'
]);
$db->table('expenses')->insert([
    'id' => 999, 'property_id' => 1, 'description' => 'Biaya Besar Homiq',
    'amount' => 20000000, 'category' => 'Renovasi',
    'date_incurred' => '2026-10-02', 'status' => 'Approved'
]);

// Check Mitra report again: must NOT include HomiQ transactions
$mitraAfterHomiq = Service::getPropertyReportData($mitraScopeFrom2, '2026-10', 1, $db);
$check($mitraAfterHomiq['summary']['revenue'] === 1700000.0, 'Mitra report revenue strictly excludes HomiQ payments (50.000.000)');
$check($mitraAfterHomiq['summary']['expenses'] === 200000.0, 'Mitra report expenses strictly excludes HomiQ expenses (20.000.000)');

// Check HomiQ report: must ONLY include HomiQ transactions
$homiqReport = Service::getPropertyReportData($homiqScope, '2026-10', 1, $db);
$check($homiqReport['summary']['revenue'] === 50000000.0, 'HomiQ report strictly contains its own revenue');
$check($homiqReport['summary']['expenses'] === 20000000.0, 'HomiQ report strictly contains its own expenses');
$check($homiqReport['isCombined'] === false, 'HomiQ report isCombined flag is false');

// TEST 8: Pagination test
// Add 10 additional expenses to trigger page 2
for ($i = 1; $i <= 10; $i++) {
    $db->table('expenses')->insert([
        'id' => 400 + $i, 'property_id' => 2, 'description' => 'Item Operasional ' . $i,
        'amount' => 10000, 'category' => 'Operasional',
        'date_incurred' => '2026-10-15', 'status' => 'Approved'
    ]);
}
$paged1 = Service::getPropertyReportData($mitraScopeFrom2, '2026-10', 1, $db);
$paged2 = Service::getPropertyReportData($mitraScopeFrom2, '2026-10', 2, $db);
$check($paged1['pagination']['totalItems'] === 14, 'Total 14 transactions correctly counted');
$check($paged1['pagination']['totalPages'] === 2, 'Total 2 pages correctly calculated');
$check(count($paged1['transactions']) === 10, 'Page 1 contains exactly 10 transactions');
$check(count($paged2['transactions']) === 4, 'Page 2 contains remaining 4 transactions');

// TEST 9: Whole-home multi-room booking consolidation
// A 3-room booking for Homestay Mitra creates 3 reservations and 3 payments.
// They must consolidate into exactly 1 transaction row with '1 Rumah' description and total amount.
foreach ([1, 2, 3] as $idx) {
    $roomId = $idx + 1; // rooms 2, 3, 4
    $db->table('reservations')->insert([
        'id' => 500 + $idx, 'property_id' => 2, 'room_id' => $roomId, 'guest_id' => 2,
        'order_id' => 'HMT-202611-MULTI-0' . $idx, 'booking_group_id' => 'HMT-202611-MULTI',
        'check_in_date' => '2026-11-10', 'check_out_date' => '2026-11-12',
        'total_amount' => 400000, 'paid_amount' => 400000, 'status' => 'Confirmed'
    ]);
    $db->table('payments')->insert([
        'id' => 600 + $idx, 'reservation_id' => 500 + $idx, 'order_id' => 'HMT-202611-MULTI-0' . $idx,
        'amount' => 400000, 'payment_date' => '2026-11-10', 'payment_method' => 'Cash',
        'type' => 'Completed', 'status' => 'Paid'
    ]);
}

$novReport = Service::getPropertyReportData($mitraScopeFrom2, '2026-11', 1, $db);
$check(count($novReport['transactions']) === 1, 'Multi-room booking consolidated into exactly 1 transaction row');
$txNov = $novReport['transactions'][0];
$check($txNov['amount'] === 1200000.0, 'Consolidated transaction amount sums to 1.200.000 (3x 400.000)');
$check($txNov['room'] === '1 Rumah', 'Consolidated transaction room displays 1 Rumah');
$check($txNov['duration'] === '2 Malam', 'Consolidated transaction duration displays 2 Malam');
$check($txNov['room_count'] === 3, 'Consolidated transaction room_count is 3 rooms for Homestay');

echo "\nSemua " . $passCount . " pengujian Tahap 3 Laporan Umum lolos 100%!\n";
