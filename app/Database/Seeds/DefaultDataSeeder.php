<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DefaultDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $password = getenv('HOMIQ_SEED_ADMIN_PASSWORD') ?: 'ChangeMe123!';

        $this->db->table('users')->insert([
            'username' => 'admin',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => 'HomiQ Administrator',
            'role' => 'Admin',
            'created_at' => $now,
        ]);

        $doubleBedRooms = [
            '103', '104', '105', '201', '204', '205', '206',
            '301', '302', '303', '304', '305', '306',
            '401', '402', '403', '404', '405', '406', '501', '506',
        ];
        $twinBedRooms = [
            '101', '102', '202', '203', '502', '503', '504', '505',
        ];

        $rooms = [];
        foreach ($doubleBedRooms as $number) {
            $rooms[] = [$number, 'Double Bed', 0, (int) $number[0]];
        }
        foreach ($twinBedRooms as $number) {
            $rooms[] = [$number, 'Twin Bed', 0, (int) $number[0]];
        }
        usort($rooms, static fn (array $left, array $right): int => strcmp($left[0], $right[0]));

        foreach ($rooms as [$number, $type, $price, $floor]) {
            $this->db->table('rooms')->insert([
                'room_number' => $number,
                'room_type' => $type,
                'price_per_night' => $price,
                'status' => 'Available',
                'floor' => $floor,
                'created_at' => $now,
            ]);
        }

        $this->call('MaintenanceCategorySeeder');
    }
}
