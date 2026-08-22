<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPaymentProofFields extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('payments', [
            'proof_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'notes'],
            'proof_original_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'proof_path'],
            'proof_mime_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'proof_original_name'],
            'proof_size' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'proof_mime_type'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('payments', ['proof_path', 'proof_original_name', 'proof_mime_type', 'proof_size']);
    }
}
