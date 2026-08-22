<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExpenseGuesthouses extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'expense_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'guesthouse' => ['type' => 'INT', 'constraint' => 2, 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['expense_id', 'guesthouse']);
        $this->forge->addKey('guesthouse');
        $this->forge->addForeignKey('expense_id', 'expenses', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('expense_guesthouses');

        $this->db->query('INSERT INTO expense_guesthouses (expense_id, guesthouse) SELECT id, guesthouse FROM expenses WHERE guesthouse BETWEEN 1 AND 5');
    }

    public function down(): void
    {
        $this->forge->dropTable('expense_guesthouses', true);
    }
}
