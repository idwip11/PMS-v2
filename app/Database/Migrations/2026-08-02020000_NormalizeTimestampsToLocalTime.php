<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeTimestampsToLocalTime extends Migration
{
    private const LOCAL_OFFSET_HOURS = 8;

    public function up(): void
    {
        $this->shiftDateTimeColumns(self::LOCAL_OFFSET_HOURS);
    }

    public function down(): void
    {
        $this->shiftDateTimeColumns(-self::LOCAL_OFFSET_HOURS);
    }

    private function shiftDateTimeColumns(int $hours): void
    {
        $columns = $this->db->query(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ('datetime', 'timestamp')"
        )->getResultArray();

        foreach ($columns as $column) {
            $table = str_replace('`', '``', (string) $column['TABLE_NAME']);
            $name = str_replace('`', '``', (string) $column['COLUMN_NAME']);
            $this->db->query(
                "UPDATE `{$table}` SET `{$name}` = DATE_ADD(`{$name}`, INTERVAL {$hours} HOUR) WHERE `{$name}` IS NOT NULL"
            );
        }
    }
}
