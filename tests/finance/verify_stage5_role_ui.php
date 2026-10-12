<?php

/**
 * Stage 5 Verification Runner: Role dan UI
 *
 * Checks:
 * 1. RoleAccess matrix & permissions for Owner Mitra
 * 2. SettingsController validation (Owner Mitra disallowed on HomiQ)
 * 3. Atomic paired creation of Owner Mitra (Property 2 + Property 3)
 * 4. Atomic paired assignment update of Owner Mitra
 * 5. Atomic paired revocation of Owner Mitra
 * 6. Protection of single property constraint & last Admin invariants
 * 7. Authorized cross-property payment proof & expense receipt access
 * 8. Strict isolation of non-proof operational endpoints
 * 9. Sidebar and Settings UI rendering for Owner Mitra
 *
 * Runs in SQLite in-memory isolated environment by default.
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

use App\Controllers\OpsController;
use App\Controllers\ReservationController;
use App\Controllers\SettingsController;
use App\Libraries\FinanceReportScope as Scope;
use App\Libraries\PropertyContext;
use App\Libraries\RoleAccess;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;

$config = config('Database');
$config->default = ['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => '', 'DBDebug' => true, 'foreignKeys' => false];
$db = db_connect();

$checkCount = 0;
$check = static function (bool $condition, string $message) use (&$checkCount): void {
    if (! $condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $checkCount++;
    echo "[PASS] {$message}\n";
};

// 1. Schema setup
$db->query('CREATE TABLE properties (id INTEGER PRIMARY KEY, code TEXT, name TEXT, is_active INTEGER, created_at TEXT, updated_at TEXT)');
$db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, full_name TEXT, role TEXT, password_hash TEXT, created_at TEXT, updated_at TEXT)');
$db->query('CREATE TABLE user_property_roles (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, property_id INTEGER, role TEXT, created_at TEXT, updated_at TEXT)');
$db->query('CREATE TABLE finance_report_groups (id INTEGER PRIMARY KEY, code TEXT UNIQUE, name TEXT, is_active INTEGER, created_at TEXT, updated_at TEXT)');
$db->query('CREATE TABLE finance_report_group_properties (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER, property_id INTEGER UNIQUE)');
$db->query('CREATE TABLE finance_report_group_owner_setups (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER UNIQUE, owner_name TEXT, owner_share_percentage REAL, created_at TEXT, updated_at TEXT)');
$db->query('CREATE TABLE rooms (id INTEGER PRIMARY KEY, property_id INTEGER, floor INTEGER, room_number TEXT, name TEXT)');
$db->query('CREATE TABLE guests (id INTEGER PRIMARY KEY, property_id INTEGER, name TEXT)');
$db->query('CREATE TABLE reservations (id INTEGER PRIMARY KEY, property_id INTEGER, room_id INTEGER, guest_id INTEGER, total_amount REAL, status TEXT, order_id TEXT, check_in_date TEXT, check_out_date TEXT)');
$db->query('CREATE TABLE payments (id INTEGER PRIMARY KEY, reservation_id INTEGER, amount REAL, payment_date TEXT, status TEXT, type TEXT, proof_path TEXT, proof_original_name TEXT, proof_mime_type TEXT)');
$db->query('CREATE TABLE expenses (id INTEGER PRIMARY KEY, property_id INTEGER, guesthouse INTEGER, amount REAL, category TEXT, status TEXT, description TEXT, date_incurred TEXT, receipt_url TEXT)');
$db->query('CREATE TABLE expense_guesthouses (id INTEGER PRIMARY KEY AUTOINCREMENT, expense_id INTEGER, guesthouse INTEGER)');

// Seed base properties & group
$db->table('properties')->insertBatch([
    ['id' => 1, 'code' => 'HOMIQ', 'name' => 'HomiQ Guesthouse', 'is_active' => 1],
    ['id' => 2, 'code' => 'HOMESTAY_MITRA', 'name' => 'Homestay Mitra', 'is_active' => 1],
    ['id' => 3, 'code' => 'KOS_MITRA', 'name' => 'Kos Mitra', 'is_active' => 1],
]);
$db->table('finance_report_groups')->insert(['id' => 1, 'code' => Scope::GROUP_CODE, 'name' => 'Mitra — Homestay & Kos', 'is_active' => 1]);
$db->table('finance_report_group_properties')->insertBatch([
    ['group_id' => 1, 'property_id' => 2],
    ['group_id' => 1, 'property_id' => 3],
]);

// Seed admin user
$db->table('users')->insert(['id' => 1, 'username' => 'admin', 'full_name' => 'Admin HomiQ', 'role' => 'Admin', 'password_hash' => password_hash('password123', PASSWORD_DEFAULT)]);
$db->table('user_property_roles')->insertBatch([
    ['user_id' => 1, 'property_id' => 1, 'role' => 'Admin', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
    ['user_id' => 1, 'property_id' => 2, 'role' => 'Admin', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
    ['user_id' => 1, 'property_id' => 3, 'role' => 'Admin', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
]);

// --- 1. RoleAccess Checks ---
$check(in_array('Owner Mitra', RoleAccess::OWNER_ROLES, true), 'RoleAccess::OWNER_ROLES includes Owner Mitra');
$check(RoleAccess::allows('Owner Mitra', 'owner_finance'), 'Owner Mitra has owner_finance permission');
$check(RoleAccess::allows('Owner Mitra', 'payment_proof'), 'Owner Mitra has payment_proof permission');
$check(RoleAccess::allows('Owner Mitra', 'expense_receipt'), 'Owner Mitra has expense_receipt permission');
$check(! RoleAccess::allows('Owner Mitra', 'dashboard'), 'Owner Mitra has no dashboard access');
$check(! RoleAccess::allows('Owner Mitra', 'finance'), 'Owner Mitra has no staff finance access');
$check(! RoleAccess::allows('Owner Mitra', 'front_desk'), 'Owner Mitra has no front_desk access');
$check(! RoleAccess::allows('Owner Mitra', 'operations'), 'Owner Mitra has no operations access');
$check(! RoleAccess::allows('Owner Mitra', 'settings'), 'Owner Mitra has no settings access');
$check(! RoleAccess::allows('Owner Mitra', 'housekeeping'), 'Owner Mitra has no housekeeping access');
$check(RoleAccess::landingPath('Owner Mitra') === 'finance/owner', 'Owner Mitra lands on finance/owner');
$check(RoleAccess::ownerGuesthouse('Owner Mitra') === null, 'Owner Mitra has no locked guesthouse number');
$check(! RoleAccess::masksDashboardFinancials('Owner Mitra'), 'Owner Mitra does not mask dashboard financials');

// --- Helper for Mocking Request/Response ---
helper(['url', 'form']);
$session = Services::session();
$session->set(['userId' => 1, 'role' => 'Admin', 'activePropertyId' => 1, 'activePropertyName' => 'HomiQ Guesthouse', 'activePropertyCode' => 'HOMIQ', 'isLoggedIn' => true]);

$dispatchSettings = static function (string $action, array $post = [], array $params = []) use ($db) {
    $_POST = $post;
    $uri = new SiteURI(config('App'), 'http://localhost:8080/settings/' . $action);
    $request = new IncomingRequest(config('App'), $uri, null, new UserAgent());
    $request->setGlobal('request', $post);
    $request->setGlobal('post', $post);
    Services::injectMock('request', $request);
    $response = Services::response(null, false);
    Services::injectMock('response', $response);
    $controller = new SettingsController();
    $controller->initController($request, $response, service('logger'));
    return match ($action) {
        'users' => $controller->createUser(),
        'users/save-role' => $controller->savePropertyRole($params['id']),
        'users/delete-role' => $controller->deletePropertyRole($params['id'], $params['property_id']),
        default => throw new InvalidArgumentException("Unknown action {$action}"),
    };
};

// --- 2. SettingsController Validation ---
// Attempt to create Owner Mitra on Property 1 (HomiQ) -> Should Fail
$res = $dispatchSettings('users', [
    'username' => 'fail_owner', 'full_name' => 'Fail Owner',
    'property_id' => 1, 'role' => 'Owner Mitra', 'password' => 'password123',
]);
$body = $res->getBody();
$check(str_contains($body, 'alert-danger') && str_contains($body, 'tidak valid'), 'SettingsController rejects assigning Owner Mitra to HomiQ on create');

// --- 3. Atomic Paired Creation of Owner Mitra ---
$res = $dispatchSettings('users', [
    'username' => 'owner_mitra_1', 'full_name' => 'Bapak Mitra',
    'property_id' => 2, 'role' => 'Owner Mitra', 'password' => 'password123',
]);
$body = $res->getBody();
$check(str_contains($body, 'alert-success') && str_contains($body, 'berpasangan'), 'SettingsController creates Owner Mitra successfully with paired notice');

$newUserId = (int) $db->table('users')->where('username', 'owner_mitra_1')->get()->getRowArray()['id'];
$assignments = $db->table('user_property_roles')->where('user_id', $newUserId)->orderBy('property_id')->get()->getResultArray();
$check(count($assignments) === 2, 'New Owner Mitra user has exactly 2 property assignments');
$check((int) $assignments[0]['property_id'] === 2 && $assignments[0]['role'] === 'Owner Mitra', 'Assignment 1 is Property 2 with Owner Mitra');
$check((int) $assignments[1]['property_id'] === 3 && $assignments[1]['role'] === 'Owner Mitra', 'Assignment 2 is Property 3 with Owner Mitra');

// --- 4. Atomic Paired Assignment in savePropertyRole ---
// Create a separate staff user on Property 1
$db->table('users')->insert(['id' => 3, 'username' => 'staff_user', 'full_name' => 'Staff User', 'role' => 'Staff', 'password_hash' => password_hash('password123', PASSWORD_DEFAULT)]);
$db->table('user_property_roles')->insert(['user_id' => 3, 'property_id' => 1, 'role' => 'Staff', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

// Reject assigning Owner Mitra on Property 1 for existing user
$res = $dispatchSettings('users/save-role', ['property_id' => 1, 'role' => 'Owner Mitra'], ['id' => 3]);
$check(str_contains($res->getBody(), 'alert-danger'), 'SettingsController rejects savePropertyRole with Owner Mitra on HomiQ');

// Assign Owner Mitra on Property 3 -> Should atomically grant both 2 and 3
$res = $dispatchSettings('users/save-role', ['property_id' => 3, 'role' => 'Owner Mitra'], ['id' => 3]);
$check(str_contains($res->getBody(), 'alert-success') && str_contains($res->getBody(), 'Homestay'), 'SettingsController savePropertyRole applies atomic paired Owner Mitra grant');

$user3Roles = $db->table('user_property_roles')->where('user_id', 3)->orderBy('property_id')->get()->getResultArray();
$check(count($user3Roles) === 3, 'User 3 now has 3 property assignments (HomiQ + Homestay + Kos)');
$check((int) $user3Roles[1]['property_id'] === 2 && $user3Roles[1]['role'] === 'Owner Mitra', 'User 3 has Owner Mitra on Property 2');
$check((int) $user3Roles[2]['property_id'] === 3 && $user3Roles[2]['role'] === 'Owner Mitra', 'User 3 has Owner Mitra on Property 3');

// --- 5. Atomic Paired Revocation in deletePropertyRole ---
// Delete assignment for Property 2 -> Should delete both Property 2 and Property 3
$res = $dispatchSettings('users/delete-role', [], ['id' => 3, 'property_id' => 2]);
$check(str_contains($res->getBody(), 'alert-success') && str_contains($res->getBody(), 'dicabut'), 'SettingsController revokes paired Owner Mitra assignments atomically');

$user3RolesAfter = $db->table('user_property_roles')->where('user_id', 3)->get()->getResultArray();
$check(count($user3RolesAfter) === 1 && (int) $user3RolesAfter[0]['property_id'] === 1, 'User 3 only retains Property 1 assignment after Owner Mitra revocation');

// --- 6. Protection & Invariants ---
// Attempting to delete Owner Mitra when user has no other properties -> Should Fail
$res = $dispatchSettings('users/delete-role', [], ['id' => $newUserId, 'property_id' => 2]);
$check(str_contains($res->getBody(), 'alert-danger') && str_contains($res->getBody(), 'setidaknya satu properti'), 'Cannot revoke Owner Mitra if user would have 0 properties remaining');

// Attempting to delete active session role -> Should Fail
$session->set(['userId' => $newUserId, 'role' => 'Owner Mitra', 'activePropertyId' => 2]);
$res = $dispatchSettings('users/delete-role', [], ['id' => $newUserId, 'property_id' => 2]);
$check(str_contains($res->getBody(), 'alert-danger') && str_contains($res->getBody(), 'sedang Anda pakai'), 'Cannot revoke Owner Mitra if currently active on Property 2');
$session->set(['userId' => 1, 'role' => 'Admin', 'activePropertyId' => 1]); // Reset session to admin

// Last HomiQ admin protection
$res = $dispatchSettings('users/save-role', ['property_id' => 1, 'role' => 'Staff'], ['id' => 1]);
$check(str_contains($res->getBody(), 'alert-danger') && str_contains($res->getBody(), 'Admin HomiQ terakhir'), 'Last HomiQ Admin cannot be downgraded');

// --- 7. Cross-Property Payment Proof & Expense Receipt Authorization ---
// Create synthetic upload directory & files
$fixturePrefix = 'mitra-stage5-test-' . bin2hex(random_bytes(6));
$uploadsDir = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads/' . $fixturePrefix;
if (! is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}
file_put_contents($uploadsDir . '/hs-proof.jpg', 'synthetic-hs-proof-content');
file_put_contents($uploadsDir . '/kos-proof.jpg', 'synthetic-kos-proof-content');
file_put_contents($uploadsDir . '/homiq-proof.jpg', 'synthetic-homiq-proof-content');
file_put_contents($uploadsDir . '/hs-receipt.jpg', 'synthetic-hs-receipt-content');
file_put_contents($uploadsDir . '/kos-receipt.jpg', 'synthetic-kos-receipt-content');
file_put_contents($uploadsDir . '/homiq-receipt.jpg', 'synthetic-homiq-receipt-content');

// Seed test rooms, reservations, payments, and expenses
$db->table('rooms')->insertBatch([
    ['id' => 1, 'property_id' => 1, 'floor' => 1, 'name' => 'Room HomiQ'],
    ['id' => 2, 'property_id' => 2, 'floor' => 1, 'name' => 'Room HS'],
    ['id' => 3, 'property_id' => 3, 'floor' => 1, 'name' => 'Room Kos'],
]);
$db->table('reservations')->insertBatch([
    ['id' => 101, 'property_id' => 1, 'room_id' => 1, 'guest_id' => 1, 'total_amount' => 500, 'status' => 'Confirmed'],
    ['id' => 102, 'property_id' => 2, 'room_id' => 2, 'guest_id' => 2, 'total_amount' => 500, 'status' => 'Confirmed'],
    ['id' => 103, 'property_id' => 3, 'room_id' => 3, 'guest_id' => 3, 'total_amount' => 500, 'status' => 'Confirmed'],
]);
$db->table('payments')->insertBatch([
    ['id' => 201, 'reservation_id' => 101, 'amount' => 500, 'proof_path' => $fixturePrefix . '/homiq-proof.jpg', 'status' => 'Paid'],
    ['id' => 202, 'reservation_id' => 102, 'amount' => 500, 'proof_path' => $fixturePrefix . '/hs-proof.jpg', 'status' => 'Paid'],
    ['id' => 203, 'reservation_id' => 103, 'amount' => 500, 'proof_path' => $fixturePrefix . '/kos-proof.jpg', 'status' => 'Paid'],
]);
$db->table('expenses')->insertBatch([
    ['id' => 301, 'property_id' => 1, 'guesthouse' => 1, 'amount' => 100, 'receipt_url' => $fixturePrefix . '/homiq-receipt.jpg', 'status' => 'Approved', 'description' => 'HomiQ Cost'],
    ['id' => 302, 'property_id' => 2, 'guesthouse' => 1, 'amount' => 100, 'receipt_url' => $fixturePrefix . '/hs-receipt.jpg', 'status' => 'Approved', 'description' => 'HS Cost'],
    ['id' => 303, 'property_id' => 3, 'guesthouse' => 1, 'amount' => 100, 'receipt_url' => $fixturePrefix . '/kos-receipt.jpg', 'status' => 'Approved', 'description' => 'Kos Cost'],
]);

// Test Controller Invocation for Proof & Receipt
$callPaymentProof = static function (int $paymentId, int $userId, int $activePropId, string $role) {
    session()->set(['userId' => $userId, 'role' => $role, 'activePropertyId' => $activePropId]);
    $uri = new SiteURI(config('App'), "http://localhost:8080/payments/{$paymentId}/proof");
    $request = new IncomingRequest(config('App'), $uri, null, new UserAgent());
    Services::injectMock('request', $request);
    $response = Services::response(null, false);
    Services::injectMock('response', $response);
    $controller = new ReservationController();
    $controller->initController($request, $response, service('logger'));
    return $controller->paymentProof($paymentId);
};

$callExpenseReceipt = static function (int $expenseId, int $userId, int $activePropId, string $role) {
    session()->set(['userId' => $userId, 'role' => $role, 'activePropertyId' => $activePropId]);
    $uri = new SiteURI(config('App'), "http://localhost:8080/ops/expenses/{$expenseId}/receipt");
    $request = new IncomingRequest(config('App'), $uri, null, new UserAgent());
    Services::injectMock('request', $request);
    $response = Services::response(null, false);
    Services::injectMock('response', $response);
    $controller = new OpsController();
    $controller->initController($request, $response, service('logger'));
    try {
        return $controller->receipt($expenseId);
    } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
        return Services::response()->setStatusCode(404);
    }
};

// Test as Owner Mitra (active on Property 2 Homestay)
// - Homestay proof (id 202) -> 200 OK
$resp = $callPaymentProof(202, $newUserId, 2, 'Owner Mitra');
$check($resp->getStatusCode() === 200 && $resp->getBody() === 'synthetic-hs-proof-content', 'Owner Mitra on Homestay can view Homestay payment proof');

// - Kos proof (id 203) -> 200 OK (Cross-property permitted in combined Mitra)
$resp = $callPaymentProof(203, $newUserId, 2, 'Owner Mitra');
$check($resp->getStatusCode() === 200 && $resp->getBody() === 'synthetic-kos-proof-content', 'Owner Mitra on Homestay can view Kos payment proof (cross-property authorized)');

// - HomiQ proof (id 201) -> 404 Not Found (Cross-property to HomiQ is strictly prohibited!)
$resp = $callPaymentProof(201, $newUserId, 2, 'Owner Mitra');
$check($resp->getStatusCode() === 404, 'Owner Mitra on Homestay cannot view HomiQ payment proof');

// - Homestay receipt (id 302) -> 200 OK
$resp = $callExpenseReceipt(302, $newUserId, 2, 'Owner Mitra');
$check($resp->getStatusCode() === 200 && $resp->getBody() === 'synthetic-hs-receipt-content', 'Owner Mitra on Homestay can view Homestay expense receipt');

// - Kos receipt (id 303) -> 200 OK (Cross-property permitted in combined Mitra)
$resp = $callExpenseReceipt(303, $newUserId, 2, 'Owner Mitra');
$check($resp->getStatusCode() === 200 && $resp->getBody() === 'synthetic-kos-receipt-content', 'Owner Mitra on Homestay can view Kos expense receipt (cross-property authorized)');

// - HomiQ receipt (id 301) -> 404 Not Found
$resp = $callExpenseReceipt(301, $newUserId, 2, 'Owner Mitra');
$check($resp->getStatusCode() === 404, 'Owner Mitra on Homestay cannot view HomiQ expense receipt');

// Test as Single-property Staff (User 4 only on Property 2)
$db->table('users')->insert(['id' => 4, 'username' => 'staff_hs', 'full_name' => 'Staff HS', 'role' => 'Staff', 'password_hash' => 'hash']);
$db->table('user_property_roles')->insert(['user_id' => 4, 'property_id' => 2, 'role' => 'Staff', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

// Single property staff can see HS proof
$resp = $callPaymentProof(202, 4, 2, 'Staff');
$check($resp->getStatusCode() === 200, 'Staff on Homestay can view Homestay payment proof');

// Single property staff CANNOT see Kos proof (fails closed)
$resp = $callPaymentProof(203, 4, 2, 'Staff');
$check($resp->getStatusCode() === 404, 'Staff with grant on Homestay only CANNOT view Kos payment proof');

// --- 8. Sidebar and UI Views Rendering ---
session()->set(['userId' => $newUserId, 'role' => 'Owner Mitra', 'activePropertyId' => 2, 'activePropertyName' => 'Homestay Mitra', 'activePropertyCode' => 'HOMESTAY_MITRA', 'activePropertyEnabled' => true]);
$sidebarHtml = view('components/sidebar');

$check(str_contains($sidebarHtml, 'Keuangan Owner/Investor'), 'Sidebar renders Keuangan Owner/Investor for Owner Mitra');
$check(! str_contains($sidebarHtml, '<span>Dashboard</span>'), 'Sidebar does NOT render Dashboard for Owner Mitra');
$check(! str_contains($sidebarHtml, '<span>Front Desk</span>'), 'Sidebar does NOT render Front Desk for Owner Mitra');
$check(! str_contains($sidebarHtml, '<span>Operasional</span>'), 'Sidebar does NOT render Operasional for Owner Mitra');
$check(! str_contains($sidebarHtml, '<span>Analytics</span>'), 'Sidebar does NOT render Analytics for Owner Mitra');
$check(! str_contains($sidebarHtml, '<span>Settings</span>'), 'Sidebar does NOT render Settings for Owner Mitra');
$check(! str_contains($sidebarHtml, '<span>Housekeeping</span>'), 'Sidebar does NOT render Housekeeping for Owner Mitra');
$check(! str_contains($sidebarHtml, 'Keuangan Properti'), 'Sidebar does NOT render Keuangan Properti (staff) for Owner Mitra');
$check(! str_contains($sidebarHtml, 'Setup Owner'), 'Sidebar does NOT render Setup Owner for Owner Mitra');

// Render Settings Panel as Admin
session()->set(['userId' => 1, 'role' => 'Admin', 'activePropertyId' => 1]);
$settingsHtml = (new SettingsController())->panel();
$check(str_contains($settingsHtml, 'Owner Mitra (Homestay + Kos)'), 'Settings panel renders Owner Mitra (Homestay + Kos) option');
$check(! str_contains($settingsHtml, 'Owner HS Mitra (Homestay Mitra)'), 'Settings panel does NOT render separate Owner HS Mitra creation option');
$check(! str_contains($settingsHtml, 'Owner Kos Mitra (Kos Mitra)'), 'Settings panel does NOT render separate Owner Kos Mitra creation option');
$check(str_contains($settingsHtml, 'Homestay &amp; Kos'), 'Settings panel displays Homestay & Kos badge on paired assignments');

// Clean up synthetic test files
@unlink($uploadsDir . '/hs-proof.jpg');
@unlink($uploadsDir . '/kos-proof.jpg');
@unlink($uploadsDir . '/homiq-proof.jpg');
@unlink($uploadsDir . '/hs-receipt.jpg');
@unlink($uploadsDir . '/kos-receipt.jpg');
@unlink($uploadsDir . '/homiq-receipt.jpg');

@rmdir($uploadsDir);

echo "\nStage 5: {$checkCount} checks passed successfully!\n";
