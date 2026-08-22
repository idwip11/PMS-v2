<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMaintenanceWorkOrdersPhaseTwo extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('maintenance_tickets', [
            'ticket_number' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'id'],
            'assigned_to_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'reported_by_user_id'],
            'scheduled_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'status'],
            'started_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'scheduled_at'],
            'completed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'started_at'],
            'verified_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'completed_at'],
            'verified_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'verified_at'],
            'diagnosis' => ['type' => 'TEXT', 'null' => true, 'after' => 'description'],
            'resolution' => ['type' => 'TEXT', 'null' => true, 'after' => 'diagnosis'],
            'follow_up_notes' => ['type' => 'TEXT', 'null' => true, 'after' => 'resolution'],
            'vendor_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'follow_up_notes'],
            'labor_cost' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'vendor_name'],
            'external_cost' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'labor_cost'],
            'blocks_room' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'external_cost'],
            'previous_room_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'blocks_room'],
        ]);

        $this->db->query(
            'ALTER TABLE maintenance_tickets '
            . 'ADD CONSTRAINT fk_maintenance_tickets_assignee '
            . 'FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) '
            . 'ON DELETE SET NULL ON UPDATE CASCADE'
        );
        $this->db->query(
            'ALTER TABLE maintenance_tickets '
            . 'ADD CONSTRAINT fk_maintenance_tickets_verifier '
            . 'FOREIGN KEY (verified_by_user_id) REFERENCES users(id) '
            . 'ON DELETE SET NULL ON UPDATE CASCADE'
        );

        $this->db->query(
            "UPDATE maintenance_tickets SET ticket_number = CONCAT('WO-', DATE_FORMAT(COALESCE(created_at, NOW()), '%Y%m%d'), '-', LPAD(id, 5, '0')) WHERE ticket_number IS NULL"
        );
        $this->db->query(
            "UPDATE maintenance_tickets SET blocks_room = 1, previous_room_status = 'Available' WHERE status IN ('Open', 'In_Progress', 'Resolved')"
        );
        $this->db->query('CREATE UNIQUE INDEX uq_maintenance_ticket_number ON maintenance_tickets (ticket_number)');

        $this->createStatusLogs();
        $this->db->query(
            "INSERT INTO maintenance_status_logs (maintenance_ticket_id, from_status, to_status, changed_by_user_id, notes, created_at) "
            . "SELECT id, NULL, status, reported_by_user_id, 'Status awal dimigrasikan dari tiket lama.', COALESCE(created_at, NOW()) FROM maintenance_tickets"
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('maintenance_status_logs', true);
        $this->db->query('ALTER TABLE maintenance_tickets DROP FOREIGN KEY fk_maintenance_tickets_verifier');
        $this->db->query('ALTER TABLE maintenance_tickets DROP FOREIGN KEY fk_maintenance_tickets_assignee');
        $this->db->query('DROP INDEX uq_maintenance_ticket_number ON maintenance_tickets');
        $this->forge->dropColumn('maintenance_tickets', [
            'previous_room_status',
            'blocks_room',
            'external_cost',
            'labor_cost',
            'vendor_name',
            'follow_up_notes',
            'resolution',
            'diagnosis',
            'verified_by_user_id',
            'verified_at',
            'completed_at',
            'started_at',
            'scheduled_at',
            'assigned_to_user_id',
            'ticket_number',
        ]);
    }

    private function createStatusLogs(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'maintenance_ticket_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'from_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'to_status' => ['type' => 'VARCHAR', 'constraint' => 30],
            'changed_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['maintenance_ticket_id', 'created_at']);
        $this->forge->addForeignKey('maintenance_ticket_id', 'maintenance_tickets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('changed_by_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('maintenance_status_logs');
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
