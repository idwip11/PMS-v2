<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveDentalKitaAmenity extends Migration
{
    public function up()
    {
        $this->db->table('inventory_items')
            ->where('item_type', 'Amenity')
            ->where('LOWER(name) = ' . $this->db->escape('dental kita'), null, false)
            ->update([
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function down()
    {
        $this->db->table('inventory_items')
            ->where('item_type', 'Amenity')
            ->where('LOWER(name) = ' . $this->db->escape('dental kita'), null, false)
            ->update([
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
