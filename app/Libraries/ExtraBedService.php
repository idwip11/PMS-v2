<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use RuntimeException;

final class ExtraBedService
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function settings(): array
    {
        $setting = $this->db->table('extra_bed_settings')->orderBy('id', 'ASC')->get()->getRowArray();
        if ($setting !== null) {
            return $setting;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('extra_bed_settings')->insert([
            'total_stock' => 7,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'id' => (int) $this->db->insertID(),
            'total_stock' => 7,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    public function availability(string $checkIn, string $checkOut, array $excludeReservationIds = []): array
    {
        $setting = $this->settings();
        $allocationBuilder = $this->db->table('extra_bed_allocations')
            ->select('extra_bed_allocations.quantity, extra_bed_allocations.check_in_date, extra_bed_allocations.check_out_date')
            ->join('reservations', 'reservations.id = extra_bed_allocations.reservation_id')
            ->where('extra_bed_allocations.check_in_date <', $checkOut)
            ->where('extra_bed_allocations.check_out_date >', $checkIn)
            ->where('reservations.status !=', 'Cancelled');
        if ($excludeReservationIds !== []) {
            $allocationBuilder->whereNotIn('extra_bed_allocations.reservation_id', $excludeReservationIds);
        }
        $allocations = $allocationBuilder->get()->getResultArray();

        $total = max(0, (int) $setting['total_stock']);
        $rented = 0;
        $date = new DateTimeImmutable($checkIn);
        $checkout = new DateTimeImmutable($checkOut);
        while ($date < $checkout) {
            $used = 0;
            $key = $date->format('Y-m-d');
            foreach ($allocations as $allocation) {
                if ($allocation['check_in_date'] <= $key && $allocation['check_out_date'] > $key) {
                    $used += (int) $allocation['quantity'];
                }
            }
            $rented = max($rented, $used);
            $date = $date->modify('+1 day');
        }

        return ['total_stock' => $total, 'rented' => $rented, 'available' => max(0, $total - $rented)];
    }

    public function assertAvailable(string $checkIn, string $checkOut, int $quantity, array $excludeReservationIds = []): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->settings();
        $this->db->query('SELECT id FROM extra_bed_settings ORDER BY id ASC LIMIT 1 FOR UPDATE');
        $availability = $this->availability($checkIn, $checkOut, $excludeReservationIds);
        if ($quantity > $availability['available']) {
            throw new RuntimeException('EXTRA_BED_UNAVAILABLE:Hanya tersedia ' . $availability['available'] . ' Extra Bed untuk tanggal yang dipilih.');
        }
    }

    public function updateStock(int $totalStock): void
    {
        $setting = $this->settings();
        $this->db->table('extra_bed_settings')->where('id', (int) $setting['id'])->update([
            'total_stock' => max(0, $totalStock),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function peakUsage(): int
    {
        $allocations = $this->db->table('extra_bed_allocations')
            ->select('extra_bed_allocations.check_in_date, extra_bed_allocations.check_out_date, extra_bed_allocations.quantity')
            ->join('reservations', 'reservations.id = extra_bed_allocations.reservation_id')
            ->where('reservations.status !=', 'Cancelled')
            ->get()
            ->getResultArray();
        $dailyUsage = [];

        foreach ($allocations as $allocation) {
            $date = new DateTimeImmutable($allocation['check_in_date']);
            $checkout = new DateTimeImmutable($allocation['check_out_date']);
            while ($date < $checkout) {
                $key = $date->format('Y-m-d');
                $dailyUsage[$key] = ($dailyUsage[$key] ?? 0) + (int) $allocation['quantity'];
                $date = $date->modify('+1 day');
            }
        }

        return $dailyUsage === [] ? 0 : max($dailyUsage);
    }

    public function allocationsForDate(string $date): array
    {
        return $this->allocationQuery()
            ->where('extra_bed_allocations.check_in_date <=', $date)
            ->where('extra_bed_allocations.check_out_date >', $date)
            ->orderBy('rooms.room_number', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function upcomingAllocations(): array
    {
        return $this->allocationQuery()
            ->where('extra_bed_allocations.check_out_date >', date('Y-m-d'))
            ->orderBy('extra_bed_allocations.check_in_date', 'ASC')
            ->orderBy('rooms.room_number', 'ASC')
            ->limit(100)
            ->get()
            ->getResultArray();
    }

    private function allocationQuery()
    {
        return $this->db->table('extra_bed_allocations')
            ->select('extra_bed_allocations.*, rooms.room_number, rooms.room_type, guests.full_name AS guest_name, reservations.order_id, reservations.source')
            ->join('reservations', 'reservations.id = extra_bed_allocations.reservation_id')
            ->join('rooms', 'rooms.id = extra_bed_allocations.room_id')
            ->join('guests', 'guests.id = reservations.guest_id')
            ->where('reservations.status !=', 'Cancelled');
    }
}
