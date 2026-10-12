<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class AddUserPropertyRoles extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('properties')) {
            throw new RuntimeException('Jalankan migrasi fondasi properti sebelum migrasi role.');
        }

        if (! $this->db->tableExists('user_property_roles')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'property_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'role' => ['type' => 'VARCHAR', 'constraint' => 50],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['user_id', 'property_id']);
            $this->forge->addKey('property_id');
            $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('property_id', 'properties', 'id', 'RESTRICT', 'RESTRICT');
            $this->forge->createTable('user_property_roles');
        }

        // Preserve each legacy user's HomiQ role. Never grant Mitra implicitly.
        $this->db->query(
            'INSERT INTO user_property_roles (user_id, property_id, role, created_at, updated_at) '
            . 'SELECT u.id, 1, u.role, NOW(), NOW() FROM users u '
            . 'LEFT JOIN user_property_roles upr ON upr.user_id = u.id AND upr.property_id = 1 '
            . 'WHERE upr.id IS NULL'
        );
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Role per properti tidak dapat di-rollback tanpa kehilangan assignment baru. Gunakan backup database.'
        );
    }
}
