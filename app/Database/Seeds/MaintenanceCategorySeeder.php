<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MaintenanceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $categories = [
            ['AC', 'AC', 'Pemeriksaan dan servis unit pendingin udara.', 3, 'Month', "Bersihkan filter\nPeriksa suhu\nPeriksa kebocoran\nPeriksa suara unit"],
            ['TV', 'TV', 'Pemeriksaan televisi, remote, dan koneksi.', 6, 'Month', "Periksa layar dan suara\nUji remote\nPeriksa kabel dan koneksi"],
            ['Plumbing', 'PLB', 'Pemeriksaan saluran air, wastafel, toilet, dan shower.', 1, 'Month', "Periksa kebocoran\nUji aliran air\nPeriksa drainase"],
            ['Electrical', 'ELC', 'Pemeriksaan lampu, stop kontak, dan instalasi listrik.', 3, 'Month', "Uji lampu\nUji stop kontak\nPeriksa kabel terbuka"],
            ['Water Heater', 'WH', 'Pemeriksaan pemanas air dan instalasinya.', 3, 'Month', "Uji pemanasan\nPeriksa kebocoran\nPeriksa pengaman listrik"],
        ];

        foreach ($categories as [$name, $code, $description, $interval, $unit, $checklist]) {
            $exists = $this->db->table('maintenance_categories')->where('name', $name)->countAllResults() > 0;
            if ($exists) {
                continue;
            }

            $this->db->table('maintenance_categories')->insert([
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'default_interval_value' => $interval,
                'default_interval_unit' => $unit,
                'default_checklist' => $checklist,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
