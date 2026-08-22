<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReservationAgent extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('reservations', [
            'agent_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'source'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('reservations', 'agent_name');
    }
}
