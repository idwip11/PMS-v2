<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMarketingAnalyticsIndexes extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE rooms ADD INDEX rooms_floor_status (floor, status)');
        $this->db->query('ALTER TABLE reservations ADD INDEX reservations_analytics_stay (status, check_in_date, check_out_date, room_id)');
        $this->db->query('ALTER TABLE payments ADD INDEX payments_analytics_revenue (status, payment_date, reservation_id)');
        $this->db->query('ALTER TABLE expenses ADD INDEX expenses_analytics_cost (date_incurred, status)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE expenses DROP INDEX expenses_analytics_cost');
        $this->db->query('ALTER TABLE payments DROP INDEX payments_analytics_revenue');
        $this->db->query('ALTER TABLE reservations DROP INDEX reservations_analytics_stay');
        $this->db->query('ALTER TABLE rooms DROP INDEX rooms_floor_status');
    }
}
