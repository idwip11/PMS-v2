<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOwnerGuesthouseSetups extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'owner_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'guesthouse' => ['type' => 'TINYINT', 'constraint' => 2, 'unsigned' => true],
            'cooperation_fee_percentage' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('guesthouse');
        $this->forge->createTable('owner_guesthouse_setups');
    }

    public function down(): void
    {
        $this->forge->dropTable('owner_guesthouse_setups', true);
    }
}
