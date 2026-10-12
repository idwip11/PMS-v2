<?php

/** Run with PHP; defaults to isolated SQLite memory. MySQL mode accepts a guarded local clone only. */
ob_start();
$root = dirname(__DIR__, 2);
define('ENVIRONMENT', 'development');
define('FCPATH', $root . '/public/');
chdir($root);
require $root . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
require $root . '/app/Database/Migrations/2026-10-12000000_AddMitraFinanceReportGroups.php';

use App\Database\Migrations\AddMitraFinanceReportGroups;
use App\Libraries\FinanceReportScope as Scope;
use App\Libraries\PropertyContext;
use App\Libraries\RoleAccess;
use CodeIgniter\Database\Exceptions\DatabaseException;

$mysql = in_array('--mysql-clone', $argv, true);
$db = $mysql ? db_connect() : Config\Database::connect([
    'DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => '',
    'DBDebug' => true, 'foreignKeys' => true,
], false);
if ($mysql && ($db->DBDriver !== 'MySQLi'
    || ! in_array($db->hostname, ['localhost', '127.0.0.1'], true)
    || preg_match('/^homiq_pms_mitra_stage2_test_[0-9]{8}_[a-z0-9]+$/D', $db->database) !== 1)) {
    throw new RuntimeException('MySQL checks require an isolated local stage-2 clone; main/live databases are refused.');
}
if (! $mysql) {
    $db->query('CREATE TABLE properties (id INTEGER PRIMARY KEY, code VARCHAR(40) UNIQUE NOT NULL, name VARCHAR(150) NOT NULL, is_active INTEGER NOT NULL)');
    $db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, username VARCHAR(100) UNIQUE NOT NULL, full_name VARCHAR(150), role VARCHAR(50), password_hash VARCHAR(255), created_at DATETIME, updated_at DATETIME)');
    $db->query('CREATE TABLE user_property_roles (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, property_id INTEGER NOT NULL, role VARCHAR(50) NOT NULL, UNIQUE(user_id, property_id), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY(property_id) REFERENCES properties(id))');
    $db->query('CREATE TABLE owner_guesthouse_setups (id INTEGER PRIMARY KEY, property_id INTEGER NOT NULL, guesthouse INTEGER NOT NULL, owner_name VARCHAR(150) NOT NULL, cooperation_fee_percentage DECIMAL(5,2), FOREIGN KEY(property_id) REFERENCES properties(id))');
}
foreach ([1 => ['HOMIQ', 'HomiQ'], 2 => ['HOMESTAY_MITRA', 'Homestay Mitra'], 3 => ['KOS_MITRA', 'Kos Mitra']] as $id => [$code, $name]) {
    if ($db->table('properties')->where('id', $id)->countAllResults() === 0) {
        $db->table('properties')->insert(['id' => $id, 'code' => $code, 'name' => $name, 'is_active' => 1]);
    }
}
$roles = [
    1001 => [1 => 'Manager'],
    1002 => [1 => 'Owner GH 3'],
    1003 => [2 => 'Manager', 3 => 'Finance'],
    1004 => [2 => 'Manager'],
    1005 => [2 => 'Finance', 3 => 'Marketing'],
    1006 => [2 => 'Owner Mitra', 3 => 'Owner Mitra'],
    1007 => [2 => 'Owner HS Mitra', 3 => 'Owner Kos Mitra'],
    1008 => [2 => 'Owner Mitra'],
    1009 => [2 => 'Owner Mitra', 3 => 'Finance'],
    1010 => [1 => 'Owner Mitra'],
];
foreach ($roles as $id => $grants) {
    $db->table('users')->insert(['id' => $id, 'username' => 'stage2_' . $id, 'full_name' => 'Stage 2 fixture', 'role' => 'Staff', 'password_hash' => 'not-a-login-account']);
    foreach ($grants as $propertyId => $role) {
        $db->table('user_property_roles')->insert(['user_id' => $id, 'property_id' => $propertyId, 'role' => $role]);
    }
}
$db->table('owner_guesthouse_setups')->insert(['property_id' => 1, 'guesthouse' => 3, 'owner_name' => 'HomiQ fixture', 'cooperation_fee_percentage' => 65]);

$count = 0;
$check = static function (bool $ok, string $name) use (&$count): void {
    if (! $ok) throw new RuntimeException('FAIL: ' . $name);
    ++$count;
    echo '[PASS] ' . $name . "\n";
};
$reject = static function (callable $fn, string $name, ?int $code = null, ?string $class = null) use ($check): void {
    try { $fn(); } catch (Throwable $e) {
        if ($class !== null && ! ($e instanceof $class)) echo 'Unexpected exception: ' . get_class($e) . ' ' . $e->getMessage() . "\n";
        $check(($code === null || $e->getCode() === $code) && ($class === null || $e instanceof $class), $name);
        return;
    }
    $check(false, $name);
};
$fingerprint = static function () use ($db): string {
    $rows = [];
    foreach (['properties', 'users', 'user_property_roles', 'owner_guesthouse_setups'] as $table) {
        $rows[$table] = $db->table($table)->orderBy('id')->get()->getResultArray();
    }
    return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
};
$before = $fingerprint();
$migration = new AddMitraFinanceReportGroups(Config\Database::forge($db));

$reject(fn () => Scope::forUser(1003, 2, Scope::REPORT, $db), 'Missing group schema fails closed', 503);
$db->table('properties')->where('id', 3)->update(['code' => 'WRONG_KOS']);
$reject(fn () => $migration->up(), 'Migration refuses conflicting property identity', null, RuntimeException::class);
$check(! $db->tableExists('finance_report_groups'), 'Identity check runs before creating tables');
$db->table('properties')->where('id', 3)->update(['code' => 'KOS_MITRA']);
$migration->up();
$check($before === $fingerprint(), 'Migration preserves properties, users, grants and old owner setup');
$check($db->table('finance_report_groups')->countAllResults() === 1, 'One Mitra reporting group seeded');
$check($db->table('finance_report_group_properties')->countAllResults() === 2, 'Exactly two property memberships seeded');
$check($db->table('finance_report_group_owner_setups')->countAllResults() === 0, 'No guessed owner or percentage seeded');
$migration->up();
$check($db->table('finance_report_groups')->countAllResults() === 1 && $db->table('finance_report_group_properties')->countAllResults() === 2, 'Migration retry is idempotent');

$homiq = Scope::forUser(1001, 1, Scope::REPORT, $db);
$check($homiq->propertyIds === [1] && ! $homiq->isCombined() && $homiq->groupId === null, 'HomiQ remains a single-property scope');
$ownerGh = Scope::forUser(1002, 1, Scope::OWNER_REPORT, $db);
$check($ownerGh->ownerGuesthouse === 3 && $ownerGh->permissions === [Scope::OWNER_REPORT], 'Owner GH stays locked to the assigned GH');
$reject(fn () => Scope::forUser(1002, 1, Scope::OWNER_SETUP, $db), 'Owner GH cannot edit setup', 403);
$reject(fn () => Scope::forUser(1010, 1, Scope::OWNER_REPORT, $db), 'Owner Mitra role cannot read HomiQ owner data', 403);

$fromHs = Scope::forUser(1003, 2, Scope::REPORT, $db);
$fromKos = Scope::forUser(1003, 3, Scope::REPORT, $db);
$check($fromHs->propertyIds === [2, 3] && $fromHs->isCombined(), 'Staff report covers Homestay and Kos only');
$check($fromHs->propertyIds === $fromKos->propertyIds && $fromHs->groupId === $fromKos->groupId && $fromHs->label === $fromKos->label, 'Either Mitra context resolves the same group');
$check(Scope::forUser(1003, 3, Scope::OWNER_SETUP, $db)->allows(Scope::OWNER_SETUP), 'Finance-authorized staff can resolve shared setup');
$reject(fn () => Scope::forUser(1004, 2, Scope::REPORT, $db), 'One property grant cannot access combined report', 403);
$reject(fn () => Scope::forUser(1005, 3, Scope::REPORT, $db), 'Finance plus Marketing grant cannot access combined report', 403);
$reject(fn () => Scope::forUser(1007, 2, Scope::OWNER_REPORT, $db), 'Two legacy owner roles do not silently become group access', 403);
$reject(fn () => Scope::forUser(1008, 2, Scope::OWNER_REPORT, $db), 'One Owner Mitra assignment is insufficient', 403);
$reject(fn () => Scope::forUser(1009, 2, Scope::OWNER_REPORT, $db), 'Mixed owner and staff assignments are not a complete owner grant', 403);
$owner = Scope::forUser(1006, 3, Scope::OWNER_REPORT, $db);
$check($owner->permissions === [Scope::OWNER_REPORT], 'Owner Mitra has owner-report permission only');
$reject(fn () => Scope::forUser(1006, 2, Scope::REPORT, $db), 'Owner Mitra cannot read staff Finance report', 403);
$reject(fn () => Scope::forUser(1006, 2, Scope::OWNER_SETUP, $db), 'Owner Mitra cannot edit shared setup', 403);
$check(! RoleAccess::allows('Owner Mitra', 'front_desk'), 'Scope foundation does not grant operational permissions');
$reject(fn () => Scope::forUser(1001, 2, Scope::REPORT, $db), 'HomiQ staff grant does not imply Mitra access', 403);
$reject(fn () => Scope::forUser(0, 2, Scope::REPORT, $db), 'Anonymous user is refused', 403);
$reject(fn () => Scope::forUser(1003, 4, Scope::REPORT, $db), 'Unknown active property is refused', 403);
$reject(fn () => Scope::forUser(1003, 2, 'front_desk', $db), 'Resolver refuses non-Finance permission', null, InvalidArgumentException::class);

$originalGrant = $db->table('user_property_roles')->where('user_id', 1003)->where('property_id', 3)->get()->getRowArray();
$db->table('user_property_roles')->where('user_id', 1003)->where('property_id', 3)->delete();
$reject(fn () => Scope::forUser(1003, 2, Scope::REPORT, $db), 'Revoked grant is observed without session refresh', 403);
$db->table('user_property_roles')->insert($originalGrant);
$db->table('user_property_roles')->where('user_id', 1003)->where('property_id', 3)->update(['role' => 'Staff']);
$reject(fn () => Scope::forUser(1003, 2, Scope::REPORT, $db), 'Role downgrade invalidates combined access', 403);
$db->table('user_property_roles')->where('user_id', 1003)->where('property_id', 3)->update(['role' => 'Finance']);
$db->table('properties')->where('id', 3)->update(['is_active' => 0]);
$reject(fn () => Scope::forUser(1003, 2, Scope::REPORT, $db), 'Inactive member cannot produce a partial combined report', 403);
$db->table('properties')->where('id', 3)->update(['is_active' => 1]);

$groupId = $fromHs->groupId;
$db->table('finance_report_groups')->where('id', $groupId)->update(['is_active' => 0]);
$reject(fn () => Scope::forUser(1003, 2, Scope::REPORT, $db), 'Inactive group fails closed', 503);
$db->table('finance_report_groups')->where('id', $groupId)->update(['is_active' => 1]);
$db->table('finance_report_group_properties')->where('group_id', $groupId)->where('property_id', 3)->delete();
$reject(fn () => Scope::forUser(1003, 2, Scope::REPORT, $db), 'Missing group membership fails closed', 503);
$db->table('finance_report_group_properties')->insert(['group_id' => $groupId, 'property_id' => 3]);
$reject(fn () => $db->table('finance_report_group_properties')->insert(['group_id' => $groupId, 'property_id' => 1]), 'Database rejects HomiQ membership', null, DatabaseException::class);
$reject(fn () => $db->table('finance_report_group_properties')->insert(['group_id' => $groupId, 'property_id' => 2]), 'Database rejects duplicate membership', null, DatabaseException::class);

$now = gmdate('Y-m-d H:i:s');
$setup = ['group_id' => $groupId, 'owner_name' => 'Owner fixture', 'owner_share_percentage' => 0, 'created_at' => $now, 'updated_at' => $now];
$reject(fn () => $db->table('finance_report_group_owner_setups')->insert(array_replace($setup, ['owner_share_percentage' => -1])), 'Database rejects negative owner percentage', null, DatabaseException::class);
$reject(fn () => $db->table('finance_report_group_owner_setups')->insert(array_replace($setup, ['owner_share_percentage' => 100.01])), 'Database rejects percentage above 100', null, DatabaseException::class);
$reject(fn () => $db->table('finance_report_group_owner_setups')->insert(array_replace($setup, ['owner_name' => '   '])), 'Database rejects empty owner name', null, DatabaseException::class);
$reject(fn () => $db->table('finance_report_group_owner_setups')->insert(array_replace($setup, ['group_id' => 99999])), 'Database rejects nonexistent owner group', null, DatabaseException::class);
$db->table('finance_report_group_owner_setups')->insert($setup);
$check((float) $db->table('finance_report_group_owner_setups')->get()->getRowArray()['owner_share_percentage'] === 0.0, 'Explicit 0 percent is valid');
$reject(fn () => $db->table('finance_report_group_owner_setups')->insert($setup), 'Database allows only one owner setup per group', null, DatabaseException::class);
$db->table('finance_report_group_owner_setups')->where('group_id', $groupId)->update(['owner_share_percentage' => 100]);
$check((float) $db->table('finance_report_group_owner_setups')->get()->getRowArray()['owner_share_percentage'] === 100.0, 'Explicit 100 percent is valid');
$db->table('finance_report_group_owner_setups')->where('group_id', $groupId)->update(['owner_share_percentage' => 33.33]);
$check((float) $db->table('finance_report_group_owner_setups')->get()->getRowArray()['owner_share_percentage'] === 33.33, 'Fractional owner percentage is valid');
$reject(fn () => $migration->down(), 'Rollback refuses to discard configured owner data', null, RuntimeException::class);
$db->table('finance_report_group_owner_setups')->where('group_id', $groupId)->delete();
$migration->down();
$check(! $db->tableExists('finance_report_groups') && $before === $fingerprint(), 'Empty-schema rollback preserves existing records');
$migration->up();
$check($before === $fingerprint(), 'Reapplying migration still preserves existing records');

$session = session();
$session->set(['userId' => 1003, 'activePropertyId' => 2, 'role' => 'Manager']);
$scope = Scope::current(Scope::REPORT, $db);
$check($scope->propertyIds === [2, 3] && PropertyContext::activeId() === 2 && session('role') === 'Manager', 'Resolving group does not change operational session context');

echo sprintf("PASS: %d stage-2 checks (%s).\n", $count, $mysql ? 'isolated MySQL clone' : 'SQLite memory');
