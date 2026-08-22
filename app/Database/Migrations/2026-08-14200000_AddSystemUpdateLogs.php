<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSystemUpdateLogs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'user_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('updated_at');
        $this->forge->createTable('system_update_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('system_update_logs', true);
    }
}
