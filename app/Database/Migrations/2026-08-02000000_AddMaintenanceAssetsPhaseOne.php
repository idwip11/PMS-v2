<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMaintenanceAssetsPhaseOne extends Migration
{
    public function up(): void
    {
        $this->createMaintenanceCategories();
        $this->createRoomAssets();

        $this->forge->addColumn('maintenance_tickets', [
            'maintenance_category_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'after' => 'reported_by_user_id',
            ],
            'room_asset_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'after' => 'maintenance_category_id',
            ],
            'ticket_type' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'Corrective',
                'after' => 'room_asset_id',
            ],
        ]);

        $this->db->query(
            'ALTER TABLE maintenance_tickets '
            . 'ADD CONSTRAINT fk_maintenance_tickets_category '
            . 'FOREIGN KEY (maintenance_category_id) REFERENCES maintenance_categories(id) '
            . 'ON DELETE SET NULL ON UPDATE CASCADE'
        );
        $this->db->query(
            'ALTER TABLE maintenance_tickets '
            . 'ADD CONSTRAINT fk_maintenance_tickets_room_asset '
            . 'FOREIGN KEY (room_asset_id) REFERENCES room_assets(id) '
            . 'ON DELETE SET NULL ON UPDATE CASCADE'
        );
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE maintenance_tickets DROP FOREIGN KEY fk_maintenance_tickets_room_asset');
        $this->db->query('ALTER TABLE maintenance_tickets DROP FOREIGN KEY fk_maintenance_tickets_category');
        $this->forge->dropColumn('maintenance_tickets', ['ticket_type', 'room_asset_id', 'maintenance_category_id']);
        $this->forge->dropTable('room_assets', true);
        $this->forge->dropTable('maintenance_categories', true);
    }

    private function createMaintenanceCategories(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'default_interval_value' => ['type' => 'INT', 'constraint' => 10, 'default' => 3],
            'default_interval_unit' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Month'],
            'default_checklist' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->addKey('is_active');
        $this->forge->createTable('maintenance_categories');
    }

    private function createRoomAssets(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'room_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'maintenance_category_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'asset_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'brand' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'model' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'serial_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'purchase_date' => ['type' => 'DATE', 'null' => true],
            'installed_at' => ['type' => 'DATE', 'null' => true],
            'warranty_until' => ['type' => 'DATE', 'null' => true],
            'condition_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Good'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('room_id');
        $this->forge->addKey('maintenance_category_id');
        $this->forge->addUniqueKey('serial_number');
        $this->forge->addForeignKey('room_id', 'rooms', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('maintenance_category_id', 'maintenance_categories', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('room_assets');
    }

    /**
     * @return array<string, mixed>
     */
    private function id(): array
    {
        return [
            'type' => 'INT',
            'constraint' => 10,
            'unsigned' => true,
            'auto_increment' => true,
        ];
    }
}
