<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMaintenanceAttachmentsPhaseFive extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'maintenance_ticket_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'file_path' => ['type' => 'VARCHAR', 'constraint' => 500],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'file_size' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'attachment_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Other'],
            'uploaded_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['maintenance_ticket_id', 'created_at']);
        $this->forge->addForeignKey('maintenance_ticket_id', 'maintenance_tickets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('uploaded_by_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('maintenance_attachments');
    }

    public function down(): void
    {
        $this->forge->dropTable('maintenance_attachments', true);
    }
}
