<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExtraBedManagement extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'total_stock' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 7],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('extra_bed_settings');

        $now = date('Y-m-d H:i:s');
        $this->db->table('extra_bed_settings')->insert([
            'total_stock' => 7,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'reservation_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'room_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'quantity' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'check_in_date' => ['type' => 'DATE'],
            'check_out_date' => ['type' => 'DATE'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('reservation_id');
        $this->forge->addKey('room_id');
        $this->forge->addKey(['check_in_date', 'check_out_date']);
        $this->forge->addForeignKey('reservation_id', 'reservations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('room_id', 'rooms', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('extra_bed_allocations');
    }

    public function down(): void
    {
        $this->forge->dropTable('extra_bed_allocations', true);
        $this->forge->dropTable('extra_bed_settings', true);
    }
}
