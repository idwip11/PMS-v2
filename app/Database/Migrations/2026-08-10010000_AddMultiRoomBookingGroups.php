<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMultiRoomBookingGroups extends Migration
{
    public function up(): void
    {
        $this->db->query(
            'ALTER TABLE reservations '
            . 'ADD booking_group_id VARCHAR(100) NULL AFTER order_id, '
            . 'ADD INDEX reservations_booking_group_id (booking_group_id)'
        );

        $this->db->table('reservations')
            ->where('booking_group_id', null)
            ->set('booking_group_id', 'order_id', false)
            ->update();
    }

    public function down(): void
    {
        $this->db->query(
            'ALTER TABLE reservations '
            . 'DROP INDEX reservations_booking_group_id, '
            . 'DROP COLUMN booking_group_id'
        );
    }
}
