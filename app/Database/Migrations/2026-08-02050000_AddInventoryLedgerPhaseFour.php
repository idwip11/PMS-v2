<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInventoryLedgerPhaseFour extends Migration
{
    public function up(): void
    {
        $columns = [
            'item_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Amenity', 'after' => 'vendor_whatsapp'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'item_type'],
            'sku' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'unit_cost'],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'sku'],
        ];
        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, 'inventory_items')) {
                $this->forge->addColumn('inventory_items', [$name => $definition]);
            }
        }
        $this->db->table('inventory_items')->update(['item_type' => 'Amenity', 'is_active' => 1]);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'maintenance_ticket_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'inventory_item_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'movement_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'quantity_used' => ['type' => 'INT', 'constraint' => 10],
            'quantity_returned' => ['type' => 'INT', 'constraint' => 10, 'default' => 0],
            'unit_cost_snapshot' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('maintenance_ticket_id');
        $this->forge->addKey('inventory_item_id');
        $this->forge->addForeignKey('maintenance_ticket_id', 'maintenance_tickets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('inventory_item_id', 'inventory_items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('maintenance_ticket_items', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'inventory_item_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'maintenance_ticket_item_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'movement_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'quantity_change' => ['type' => 'INT', 'constraint' => 11],
            'balance_after' => ['type' => 'INT', 'constraint' => 10],
            'reference_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'reference_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('inventory_item_id');
        $this->forge->addKey(['reference_type', 'reference_id']);
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('inventory_item_id', 'inventory_items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('maintenance_ticket_item_id', 'maintenance_ticket_items', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('inventory_stock_movements', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_stock_movements', true);
        $this->forge->dropTable('maintenance_ticket_items', true);
        $this->forge->dropColumn('inventory_items', ['is_active', 'sku', 'unit_cost', 'item_type']);
    }
}
