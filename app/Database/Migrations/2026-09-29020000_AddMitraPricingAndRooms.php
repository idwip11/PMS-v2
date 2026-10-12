<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** Forward-only: preserves all HomiQ prices, rooms, and reservations. */
class AddMitraPricingAndRooms extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('pricing_mode', 'properties')) {
            $this->forge->addColumn('properties', [
                'pricing_mode' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'room_nightly'],
                'base_rate' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            ]);
        }

        $models = [
            1 => ['HOMIQ', 'room_nightly', 0],
            2 => ['HOMESTAY_MITRA', 'whole_home_nightly', 600000],
            3 => ['KOS_MITRA', 'room_monthly', 500000],
        ];
        foreach ($models as $id => [$code, $mode, $rate]) {
            $property = $this->db->table('properties')->where('id', $id)->get()->getRowArray();
            if ($property === null || $property['code'] !== $code) {
                throw new RuntimeException('Identitas properti Mitra tidak cocok; migrasi dihentikan.');
            }
            $this->db->table('properties')->where('id', $id)->update([
                'pricing_mode' => $mode,
                'base_rate' => $rate,
            ]);
        }

        foreach ([2 => ['01', '02', '03'], 3 => ['k1', 'k2', 'k3', 'k4', 'k5', 'k6', 'k7']] as $propertyId => $numbers) {
            foreach ($numbers as $number) {
                if ($this->db->table('rooms')->where('property_id', $propertyId)->where('room_number', $number)->countAllResults() > 0) {
                    continue;
                }
                $this->db->table('rooms')->insert([
                    'property_id' => $propertyId,
                    'room_number' => $number,
                    'room_type' => $propertyId === 2 ? 'Homestay' : 'Kos',
                    'price_per_night' => 0,
                    'status' => 'Available',
                    'floor' => null,
                    'guesthouse_number' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Migrasi tarif/inventaris Mitra bersifat forward-only; pulihkan dari backup bila perlu.');
    }
}
