<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddImportantNoteToReservations extends Migration
{
    public function up()
    {
        $this->forge->addColumn('reservations', [
            'important_note' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'special_request',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('reservations', 'important_note');
    }
}
