<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReservationCancellationAnalytics extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('reservations', [
            'cancelled_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'updated_at'],
        ]);
        $this->db->query('ALTER TABLE reservations ADD INDEX reservations_analytics_cancellation (status, cancelled_at, room_id)');
        $this->db->query('ALTER TABLE reservations ADD INDEX reservations_analytics_booking_window (check_in_date, created_at, room_id)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE reservations DROP INDEX reservations_analytics_booking_window');
        $this->db->query('ALTER TABLE reservations DROP INDEX reservations_analytics_cancellation');
        $this->forge->dropColumn('reservations', 'cancelled_at');
    }
}
