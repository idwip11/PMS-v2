<?php

/** Read-only schema/data summary; no migration, seeder or business service is called. */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
ob_start();
$root = dirname(__DIR__, 2);
define('ENVIRONMENT', getenv('CI_ENVIRONMENT') ?: 'production');
define('FCPATH', $root . '/public/');
chdir($root);
require $root . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
$db = db_connect();
if ($db->DBDriver !== 'MySQLi') {
    throw new RuntimeException('Audit ini membutuhkan MySQL/MariaDB.');
}
$db->query('START TRANSACTION READ ONLY');
try {
    $tables = $db->listTables();
    sort($tables);
    $schema = [];
    foreach ($tables as $table) {
        $safeTable = '`' . str_replace('`', '``', $table) . '`';
        $schema[$table] = [
            'rows' => (int) $db->query('SELECT COUNT(*) AS total FROM ' . $safeTable)->getRow('total'),
            'columns' => $db->query('SHOW COLUMNS FROM ' . $safeTable)->getResultArray(),
            'indexes' => $db->query('SHOW INDEX FROM ' . $safeTable)->getResultArray(),
            'ddl' => array_values($db->query('SHOW CREATE TABLE ' . $safeTable)->getRowArray())[1],
        ];
    }
    $result = [
        'audited_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Makassar')))->format(DATE_ATOM),
        'read_only' => true,
        'server_version' => $db->query('SELECT VERSION() AS version')->getRow('version'),
        'table_count' => count($tables),
        'schema' => $schema,
        'migrations' => in_array('migrations', $tables, true)
            ? $db->table('migrations')->orderBy('version')->get()->getResultArray() : [],
    ];
    if (in_array('properties', $tables, true)) {
        $result['properties'] = $db->table('properties')->get()->getResultArray();
    }
    if (in_array('finance_report_groups', $tables, true)) {
        $result['report_groups'] = $db->table('finance_report_groups')->get()->getResultArray();
    }
    if (in_array('finance_report_group_properties', $tables, true)) {
        $result['report_group_members'] = $db->table('finance_report_group_properties')->get()->getResultArray();
    }
    if (in_array('finance_report_group_owner_setups', $tables, true)) {
        $result['shared_owner_setups'] = $db->query('SELECT id, group_id, owner_share_percentage, '
            . '(LENGTH(TRIM(owner_name)) > 0) AS has_owner_name FROM finance_report_group_owner_setups')->getResultArray();
    }
    if (in_array('owner_guesthouse_setups', $tables, true)) {
        $fields = $db->fieldExists('property_id', 'owner_guesthouse_setups') ? 'property_id, ' : '';
        $result['legacy_owner_setups'] = $db->query('SELECT id, ' . $fields . 'guesthouse, cooperation_fee_percentage, '
            . '(LENGTH(TRIM(owner_name)) > 0) AS has_owner_name FROM owner_guesthouse_setups')->getResultArray();
    }
    if (in_array('user_property_roles', $tables, true)) {
        $result['role_counts'] = $db->query('SELECT property_id, role, COUNT(*) AS total FROM user_property_roles '
            . 'GROUP BY property_id, role ORDER BY property_id, role')->getResultArray();
        $result['mitra_owner_grants'] = $db->query("SELECT user_id, property_id, role FROM user_property_roles "
            . "WHERE role IN ('Owner Mitra','Owner HS Mitra','Owner Kos Mitra') ORDER BY user_id, property_id")->getResultArray();
    }
    if (in_array('extra_bed_settings', $tables, true)) {
        $result['extra_bed_settings'] = $db->table('extra_bed_settings')->get()->getResultArray();
    }
    $db->query('ROLLBACK');
    ob_end_clean();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) {
    $db->query('ROLLBACK');
    throw $e;
}
