<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGuestKtpPhotoFields extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('guests', [
            'ktp_photo_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'notes'],
            'ktp_photo_original_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'ktp_photo_path'],
            'ktp_photo_mime_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'ktp_photo_original_name'],
            'ktp_photo_size' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'ktp_photo_mime_type'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('guests', [
            'ktp_photo_path',
            'ktp_photo_original_name',
            'ktp_photo_mime_type',
            'ktp_photo_size',
        ]);
    }
}
