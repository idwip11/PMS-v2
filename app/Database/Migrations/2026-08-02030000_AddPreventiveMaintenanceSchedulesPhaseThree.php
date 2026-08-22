<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPreventiveMaintenanceSchedulesPhaseThree extends Migration
{
    public function up(): void
    {
        $this->createSchedules();

        $this->forge->addColumn('maintenance_tickets', [
            'maintenance_schedule_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'room_asset_id'],
            'schedule_due_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'scheduled_at'],
            'checklist_snapshot' => ['type' => 'TEXT', 'null' => true, 'after' => 'follow_up_notes'],
            'checklist_completed' => ['type' => 'TEXT', 'null' => true, 'after' => 'checklist_snapshot'],
            'next_service_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'completed_at'],
        ]);

        $this->db->query(
            'ALTER TABLE maintenance_tickets ADD CONSTRAINT fk_maintenance_tickets_schedule '
            . 'FOREIGN KEY (maintenance_schedule_id) REFERENCES maintenance_schedules(id) '
            . 'ON DELETE SET NULL ON UPDATE CASCADE'
        );
        $this->db->query(
            'CREATE UNIQUE INDEX uq_maintenance_ticket_schedule_due '
            . 'ON maintenance_tickets (maintenance_schedule_id, schedule_due_at)'
        );
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE maintenance_tickets DROP FOREIGN KEY fk_maintenance_tickets_schedule');
        $this->db->query('DROP INDEX uq_maintenance_ticket_schedule_due ON maintenance_tickets');
        $this->forge->dropColumn('maintenance_tickets', [
            'next_service_at',
            'checklist_completed',
            'checklist_snapshot',
            'schedule_due_at',
            'maintenance_schedule_id',
        ]);
        $this->forge->dropTable('maintenance_schedules', true);
    }

    private function createSchedules(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'room_asset_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 160],
            'checklist' => ['type' => 'TEXT', 'null' => true],
            'frequency_value' => ['type' => 'INT', 'constraint' => 10, 'default' => 3],
            'frequency_unit' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Month'],
            'last_completed_at' => ['type' => 'DATETIME', 'null' => true],
            'next_due_at' => ['type' => 'DATETIME', 'null' => false],
            'reminder_days' => ['type' => 'INT', 'constraint' => 10, 'default' => 7],
            'default_assignee_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'default_vendor' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'blocks_room' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('room_asset_id');
        $this->forge->addKey('next_due_at');
        $this->forge->addKey('is_active');
        $this->forge->addForeignKey('room_asset_id', 'room_assets', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('default_assignee_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('maintenance_schedules');
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
