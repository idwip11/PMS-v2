<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;
use Throwable;

/** Add reporting metadata only; do not migrate owners or operational records. */
final class AddMitraFinanceReportGroups extends Migration
{
    public function up(): void
    {
        foreach ([2 => 'HOMESTAY_MITRA', 3 => 'KOS_MITRA'] as $id => $code) {
            $property = $this->db->table('properties')->where('id', $id)->get()->getRowArray();
            if ($property === null || $property['code'] !== $code) {
                throw new RuntimeException('Identitas Homestay/Kos Mitra tidak sesuai; migrasi Finance dihentikan.');
            }
        }

        $attributes = $this->db->DBDriver === 'MySQLi' ? ['ENGINE' => 'InnoDB'] : [];
        $timestamps = [
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ];

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'is_active' => ['type' => 'TINYINT', 'default' => 1],
        ] + $timestamps);
        $this->forge->addField('CONSTRAINT chk_finance_group_active CHECK (is_active IN (0, 1))');
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('finance_report_groups', true, $attributes);

        $this->forge->addField([
            'group_id' => ['type' => 'INT', 'unsigned' => true],
            'property_id' => ['type' => 'INT', 'unsigned' => true],
        ]);
        // HomiQ remains a single-property scope, never a member of this group.
        $this->forge->addField('CONSTRAINT chk_finance_mitra_property CHECK (property_id IN (2, 3))');
        $this->forge->addKey(['group_id', 'property_id'], true);
        $this->forge->addUniqueKey('property_id');
        $this->forge->addForeignKey('group_id', 'finance_report_groups', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('property_id', 'properties', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('finance_report_group_properties', true, $attributes);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'group_id' => ['type' => 'INT', 'unsigned' => true],
            'owner_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            // Signed + CHECK also rejects negative inputs without coercing to 0.
            'owner_share_percentage' => ['type' => 'DECIMAL', 'constraint' => '5,2'],
        ] + $timestamps);
        $this->forge->addField('CONSTRAINT chk_finance_owner_percentage CHECK (owner_share_percentage >= 0 AND owner_share_percentage <= 100), '
            . 'CONSTRAINT chk_finance_owner_name CHECK (LENGTH(TRIM(owner_name)) > 0)');
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('group_id');
        $this->forge->addForeignKey('group_id', 'finance_report_groups', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('finance_report_group_owner_setups', true, $attributes);

        $this->db->transBegin();
        try {
            $group = $this->db->table('finance_report_groups')->where('code', 'MITRA_COMBINED')->get()->getRowArray();
            if ($group === null) {
                $now = gmdate('Y-m-d H:i:s');
                $this->db->table('finance_report_groups')->insert([
                    'code' => 'MITRA_COMBINED',
                    'name' => 'Mitra — Homestay & Kos',
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $groupId = (int) $this->db->insertID();
            } else {
                $groupId = (int) $group['id'];
            }

            foreach ([2, 3] as $propertyId) {
                $membership = $this->db->table('finance_report_group_properties')
                    ->where('property_id', $propertyId)->get()->getRowArray();
                if ($membership !== null && (int) $membership['group_id'] !== $groupId) {
                    throw new RuntimeException('Properti Mitra sudah terhubung ke kelompok Finance lain.');
                }
                if ($membership === null) {
                    $this->db->table('finance_report_group_properties')->insert([
                        'group_id' => $groupId,
                        'property_id' => $propertyId,
                    ]);
                }
            }

            if (! $this->db->transStatus()) {
                throw new RuntimeException('Pembuatan kelompok Finance Mitra gagal.');
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('finance_report_group_owner_setups')
            && $this->db->table('finance_report_group_owner_setups')->countAllResults() > 0) {
            throw new RuntimeException('Setup owner gabungan sudah terisi; pulihkan konfigurasi dari backup sebelum rollback skema.');
        }
        $this->forge->dropTable('finance_report_group_owner_setups', true);
        $this->forge->dropTable('finance_report_group_properties', true);
        $this->forge->dropTable('finance_report_groups', true);
    }
}
