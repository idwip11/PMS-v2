<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExpenseAuditLogs extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('expense_audit_logs')) return;
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'expense_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'changed_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 30],
            'before_data' => ['type' => 'TEXT', 'null' => true],
            'after_data' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('expense_id');
        $this->forge->addForeignKey('expense_id', 'expenses', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('changed_by_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('expense_audit_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('expense_audit_logs', true);
    }
}
