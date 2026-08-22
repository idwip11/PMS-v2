<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SyncGuesthouseRooms extends Migration
{
    public function up(): void
    {
        // On a fresh installation the seeder below owns the initial room data.
        if ($this->db->table('rooms')->countAllResults() === 0) {
            return;
        }

        $doubleBedRooms = [
            '103', '104', '105', '201', '204', '205', '206',
            '301', '302', '303', '304', '305', '306',
            '401', '402', '403', '404', '405', '406', '501', '506',
        ];
        $twinBedRooms = [
            '101', '102', '202', '203', '502', '503', '504', '505',
        ];

        $roomTypes = [];
        foreach ($doubleBedRooms as $number) {
            $roomTypes[$number] = 'Double Bed';
        }
        foreach ($twinBedRooms as $number) {
            $roomTypes[$number] = 'Twin Bed';
        }
        ksort($roomTypes, SORT_NATURAL);

        $now = date('Y-m-d H:i:s');
        foreach ($roomTypes as $number => $type) {
            $roomNumber = (string) $number;
            $room = $this->db->table('rooms')
                ->select('id')
                ->where('room_number', $roomNumber)
                ->get()
                ->getRowArray();

            if ($room !== null) {
                $this->db->table('rooms')->where('id', (int) $room['id'])->update([
                    'room_type' => $type,
                    'floor' => (int) $roomNumber[0],
                    'updated_at' => $now,
                ]);
                continue;
            }

            $this->db->table('rooms')->insert([
                'room_number' => $roomNumber,
                'room_type' => $type,
                'price_per_night' => 0,
                'status' => 'Available',
                'floor' => (int) $roomNumber[0],
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Room data may already be referenced by reservations and maintenance records.
        // A rollback intentionally leaves operational data intact.
    }
}
