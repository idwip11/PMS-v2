<?php

namespace App\Controllers;

use DateTimeImmutable;
use DateTimeZone;

class DashboardController extends BaseController
{
    public function index(): string
    {
        return view('pages/dashboard', [
            'month' => $this->month(),
            'guesthouse' => $this->guesthouse(),
            'pageTitle' => 'Dashboard',
            'pageDescription' => 'Kinerja properti berdasarkan periode.',
        ]);
    }

    public function metrics(): string
    {
        $month = $this->month();
        $guesthouse = $this->guesthouse();
        if ($this->request->hasHeader('HX-Request')) {
            $query = http_build_query(array_filter(
                ['month' => $month, 'guesthouse' => $guesthouse],
                static fn ($value): bool => $value !== null && $value !== ''
            ));
            $this->response->setHeader('HX-Push-Url', site_url('dashboard?' . $query));
        }
        $first = new DateTimeImmutable($month . '-01');
        $next = $first->modify('first day of next month');
        $last = $next->modify('-1 day');
        $db = db_connect();
        $roomBuilder = $db->table('rooms');
        if ($guesthouse !== null) {
            $roomBuilder->where('floor', $guesthouse);
        }
        $roomCount = $roomBuilder->countAllResults();

        $reservationBuilder = $db->table('reservations')
            ->select('reservations.*')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->where('check_in_date <', $next->format('Y-m-d'))
            ->where('check_out_date >', $first->format('Y-m-d'))
            ->whereIn('reservations.status', ['Confirmed', 'Checked_In', 'Checked_Out']);
        if ($guesthouse !== null) {
            $reservationBuilder->where('rooms.floor', $guesthouse);
        }
        $reservations = $reservationBuilder->get()->getResultArray();

        $occupiedNights = 0;
        $stayNights = [];
        foreach ($reservations as $reservation) {
            $arrival = max($reservation['check_in_date'], $first->format('Y-m-d'));
            $departure = min($reservation['check_out_date'], $next->format('Y-m-d'));
            $nights = (int) (new DateTimeImmutable($arrival))->diff(new DateTimeImmutable($departure))->days;
            $occupiedNights += max(0, $nights);
            $stayNights[] = (int) (new DateTimeImmutable($reservation['check_in_date']))->diff(new DateTimeImmutable($reservation['check_out_date']))->days;
        }

        $days = (int) $first->format('t');
        $maintenanceBuilder = $db->table('maintenance_tickets')
            ->select('maintenance_tickets.room_id, maintenance_tickets.created_at, maintenance_tickets.completed_at')
            ->join('rooms', 'rooms.id = maintenance_tickets.room_id')
            ->where('maintenance_tickets.blocks_room', 1)
            ->where('maintenance_tickets.created_at <', $next->format('Y-m-d'));
        if ($guesthouse !== null) {
            $maintenanceBuilder->where('rooms.floor', $guesthouse);
        }
        $maintenanceTicketsForOccupancy = $maintenanceBuilder->get()->getResultArray();
        $blockedRoomNights = [];
        foreach ($maintenanceTicketsForOccupancy as $ticket) {
            $blockStart = max(substr((string) $ticket['created_at'], 0, 10), $first->format('Y-m-d'));
            $blockEnd = min(substr((string) ($ticket['completed_at'] ?? $next->format('Y-m-d')), 0, 10), $next->format('Y-m-d'));
            if ($blockEnd <= $blockStart) {
                continue;
            }
            for ($date = new DateTimeImmutable($blockStart); $date < new DateTimeImmutable($blockEnd); $date = $date->modify('+1 day')) {
                $blockedRoomNights[(int) $ticket['room_id'] . '|' . $date->format('Y-m-d')] = true;
            }
        }
        $availableRoomNights = max(0, ($roomCount * $days) - count($blockedRoomNights));

        $paymentBuilder = $db->table('payments')
            ->select('payments.*')
            ->join('reservations', 'reservations.id = payments.reservation_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->where('payments.status', 'Paid')
            ->where('payments.payment_date >=', $first->format('Y-m-d'))
            ->where('payments.payment_date <=', $last->format('Y-m-d'));
        if ($guesthouse !== null) {
            $paymentBuilder->where('rooms.floor', $guesthouse);
        }
        $payments = $paymentBuilder->get()->getResultArray();

        $expenseBuilder = $db->table('expenses')
            ->where('status !=', 'Rejected')
            ->where('date_incurred >=', $first->format('Y-m-d'))
            ->where('date_incurred <=', $last->format('Y-m-d'));
        if ($guesthouse !== null) {
            $expenseBuilder->where("expenses.guesthouse = {$guesthouse} OR EXISTS (SELECT 1 FROM expense_guesthouses WHERE expense_guesthouses.expense_id = expenses.id AND expense_guesthouses.guesthouse = {$guesthouse})", null, false);
        }
        $expenses = $expenseBuilder->get()->getResultArray();
        $revenue = array_sum(array_map(static fn ($row) => (float) $row['amount'], $payments));
        $expenseTotal = array_sum(array_map(static fn ($row) => (float) $row['amount'], $expenses));
        $labels = $dailyRevenue = $dailyExpenses = [];
        for ($day = 1; $day <= $days; $day++) {
            $date = $first->setDate((int) $first->format('Y'), (int) $first->format('m'), $day)->format('Y-m-d');
            $labels[] = (string) $day;
            $dailyRevenue[] = array_sum(array_map(static fn ($row) => $row['payment_date'] === $date ? (float) $row['amount'] : 0, $payments));
            $dailyExpenses[] = array_sum(array_map(static fn ($row) => $row['date_incurred'] === $date ? (float) $row['amount'] : 0, $expenses));
        }

        $activityData = $this->todayActivities($guesthouse);
        $today = $activityData['today'];
        $checkIns = $activityData['checkIns'];
        $checkOuts = $activityData['checkOuts'];
        $recentBuilder = $db->table('reservations')
            ->select('reservations.id, reservations.order_id, reservations.source, reservations.status, reservations.check_in_date, guests.full_name AS guest_name, rooms.room_number', false)
            ->join('guests', 'guests.id = reservations.guest_id')
            ->join('rooms', 'rooms.id = reservations.room_id');
        if ($guesthouse !== null) {
            $recentBuilder->where('rooms.floor', $guesthouse);
        }
        $recentReservations = $recentBuilder->orderBy('reservations.created_at', 'DESC')
            ->limit(5)
            ->get()->getResultArray();

        $dirtyRoomBuilder = $db->table('rooms')->where('status', 'Dirty');
        $maintenanceBuilder = $db->table('maintenance_tickets')
            ->join('rooms', 'rooms.id = maintenance_tickets.room_id')
            ->whereIn('maintenance_tickets.status', ['Open', 'In_Progress']);
        if ($guesthouse !== null) {
            $dirtyRoomBuilder->where('floor', $guesthouse);
            $maintenanceBuilder->where('rooms.floor', $guesthouse);
        }

        $todayCleaningTasks = $db->table('cleaning_tasks')
            ->select('cleaning_tasks.id, cleaning_tasks.room_area, cleaning_tasks.description, cleaning_tasks.priority, cleaning_tasks.status, GROUP_CONCAT(users.full_name SEPARATOR ", ") AS assignees', false)
            ->join('task_assignees', 'task_assignees.task_id = cleaning_tasks.id', 'left')
            ->join('users', 'users.id = task_assignees.user_id', 'left')
            ->where('cleaning_tasks.scheduled_date', $today)
            ->groupBy('cleaning_tasks.id')
            ->orderBy('cleaning_tasks.priority', 'DESC')
            ->get()->getResultArray();

        $tomorrow = (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');
        $serviceAlertBuilder = static function (string $serviceName, string $dateColumn, string $date, string $type, bool $includeActiveStay = false) use ($db, $guesthouse, $today) {
            $builder = $db->table('reservations')
                ->select('reservations.id, reservations.order_id, reservations.booking_group_id, reservations.status, reservations.check_in_date, reservations.check_out_date, guests.full_name AS guest_name, rooms.room_number', false)
                ->join('invoice_items', 'invoice_items.reservation_id = reservations.id')
                ->join('guests', 'guests.id = reservations.guest_id')
                ->join('rooms', 'rooms.id = reservations.room_id')
                ->where('invoice_items.item_name', $serviceName);
            if ($includeActiveStay) {
                $builder->groupStart()
                    ->groupStart()
                        ->where('reservations.status', 'Confirmed')
                        ->where('reservations.' . $dateColumn, $date)
                    ->groupEnd()
                    ->orGroupStart()
                        ->where('reservations.status', 'Checked_In')
                        ->where('reservations.check_in_date <=', $today)
                        ->where('reservations.check_out_date >', $today)
                    ->groupEnd()
                ->groupEnd();
            } else {
                $builder->where('reservations.' . $dateColumn, $date)
                    ->whereIn('reservations.status', ['Confirmed', 'Checked_In']);
            }
            if ($guesthouse !== null) {
                $builder->where('rooms.floor', $guesthouse);
            }

            return array_map(static function (array $alert) use ($type): array {
                $alert['type'] = $type;
                return $alert;
            }, $builder->distinct()->orderBy('rooms.room_number', 'ASC')->get()->getResultArray());
        };
        $serviceAlerts = array_merge(
            $serviceAlertBuilder('Early Check In', 'check_in_date', $tomorrow, 'early_checkin', true),
            $serviceAlertBuilder('Late Checkout', 'check_out_date', $today, 'late_checkout')
        );

        // Ringkas dan murah untuk dashboard: gunakan flag layanan pada reservasi
        // (tanpa agregasi invoice), lalu batasi ke tujuh hari check-in berikutnya.
        $breakfastAlertBuilder = $db->table('reservations')
            ->select('reservations.id, reservations.status, reservations.check_in_date, guests.full_name AS guest_name, rooms.room_number', false)
            ->join('guests', 'guests.id = reservations.guest_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->where('reservations.has_breakfast', 1)
            ->groupStart()
                ->groupStart()
                    ->where('reservations.status', 'Confirmed')
                    ->where('reservations.check_in_date >=', $tomorrow)
                    ->where('reservations.check_in_date <=', (new DateTimeImmutable($today))->modify('+7 days')->format('Y-m-d'))
                ->groupEnd()
                ->orGroupStart()
                    ->where('reservations.status', 'Checked_In')
                    ->where('reservations.check_in_date <=', $today)
                    ->where('reservations.check_out_date >=', $today)
                ->groupEnd()
            ->groupEnd();
        if ($guesthouse !== null) {
            $breakfastAlertBuilder->where('rooms.floor', $guesthouse);
        }
        $breakfastAlerts = $breakfastAlertBuilder
            ->orderBy('reservations.check_in_date', 'ASC')
            ->orderBy('rooms.room_number', 'ASC')
            ->limit(12)
            ->get()
            ->getResultArray();

        return view('components/dashboard_metrics', [
            'month' => $month,
            'guesthouse' => $guesthouse,
            'stats' => [
                'occupancy' => $availableRoomNights > 0 ? ($occupiedNights / $availableRoomNights) * 100 : 0,
                'revenue' => $revenue,
                'expenses' => $expenseTotal,
                'netProfit' => $revenue - $expenseTotal,
                'averageStay' => $stayNights === [] ? 0 : array_sum($stayNights) / count($stayNights),
                'roomsRented' => $occupiedNights,
                'dirtyRooms' => $dirtyRoomBuilder->countAllResults(),
                'openMaintenance' => $maintenanceBuilder->countAllResults(),
                'lowStock' => $db->table('inventory_items')->where('current_stock <= min_threshold', null, false)->countAllResults(),
            ],
            'chart' => ['labels' => $labels, 'revenue' => $dailyRevenue, 'expenses' => $dailyExpenses],
            'today' => $today,
            'activities' => [
                'checkInCount' => count($checkIns),
                'checkOutCount' => count($checkOuts),
            ],
            'recentReservations' => $recentReservations,
            'todayCleaningTasks' => $todayCleaningTasks,
            'serviceAlerts' => $serviceAlerts,
            'breakfastAlerts' => $breakfastAlerts,
        ]);
    }

    public function exportRecentReservations()
    {
        $guesthouse = $this->guesthouse();
        $db = db_connect();
        $builder = $db->table('reservations')
            ->select('reservations.id, reservations.order_id, reservations.source, reservations.status, reservations.check_in_date, reservations.check_out_date, reservations.total_amount, reservations.breakfast_cost, reservations.laundry_cost, reservations.massage_cost, guests.full_name AS guest_name, rooms.room_number, rooms.floor AS guesthouse', false)
            ->join('guests', 'guests.id = reservations.guest_id')
            ->join('rooms', 'rooms.id = reservations.room_id');
        if ($guesthouse !== null) {
            $builder->where('rooms.floor', $guesthouse);
        }
        $reservations = $builder
            ->orderBy('reservations.created_at', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();

        $reservationIds = array_map('intval', array_column($reservations, 'id'));
        $items = $reservationIds === [] ? [] : $db->table('invoice_items')
            ->whereIn('reservation_id', $reservationIds)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
        $itemsByReservation = [];
        foreach ($items as $item) {
            $itemsByReservation[(int) $item['reservation_id']][] = $item;
        }

        $statusLabels = [
            'Confirmed' => 'Terkonfirmasi',
            'Checked_In' => 'Check-in',
            'Checked_Out' => 'Check-out',
            'Cancelled' => 'Dibatalkan',
            'No_Show' => 'No-Show',
        ];
        $serviceItemNames = ['Breakfast', 'Extra Bed', 'Biaya Extra Bed', 'Laundry', 'Massage', 'Sewa Kendaraan'];
        $stringCell = static fn ($value, string $style = ''): string => '<Cell' . ($style === '' ? '' : ' ss:StyleID="' . $style . '"') . '><Data ss:Type="String">' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</Data></Cell>';
        $numberCell = static fn ($value): string => '<Cell ss:StyleID="Currency"><Data ss:Type="Number">' . (float) $value . '</Data></Cell>';
        $rows = [
            '<Row>' . implode('', array_map(static fn ($heading) => $stringCell($heading, 'Header'), ['Nama Tamu', 'ID Booking', 'Kamar', 'GH', 'Sumber', 'Tanggal Check-in', 'Tanggal Check-out', 'Status', 'Biaya Menginap', 'Biaya Layanan'])) . '</Row>',
        ];

        foreach ($reservations as $reservation) {
            $stayCost = 0.0;
            $serviceCost = 0.0;
            foreach ($itemsByReservation[(int) $reservation['id']] ?? [] as $item) {
                if (str_starts_with((string) $item['item_name'], 'Room ')) {
                    $stayCost += (float) $item['amount'];
                } elseif (in_array((string) $item['item_name'], $serviceItemNames, true)) {
                    $serviceCost += (float) $item['amount'];
                }
            }
            if (($itemsByReservation[(int) $reservation['id']] ?? []) === []) {
                $serviceCost = (float) ($reservation['breakfast_cost'] ?? 0)
                    + (float) ($reservation['laundry_cost'] ?? 0)
                    + (float) ($reservation['massage_cost'] ?? 0);
                $stayCost = max(0, (float) $reservation['total_amount'] - $serviceCost);
            }

            $rows[] = '<Row>'
                . $stringCell($reservation['guest_name'])
                . $stringCell($reservation['order_id'])
                . $stringCell($reservation['room_number'])
                . $stringCell('GH ' . $reservation['guesthouse'])
                . $stringCell($reservation['source'] ?: 'Direct')
                . $stringCell($reservation['check_in_date'])
                . $stringCell($reservation['check_out_date'])
                . $stringCell($statusLabels[$reservation['status']] ?? $reservation['status'])
                . $numberCell($stayCost)
                . $numberCell($serviceCost)
                . '</Row>';
        }

        $content = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<?mso-application progid="Excel.Sheet"?>'
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            . '<Styles><Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#2563EB" ss:Pattern="Solid"/></Style><Style ss:ID="Currency"><NumberFormat ss:Format="#,##0"/></Style></Styles>'
            . '<Worksheet ss:Name="Recent Reservations"><Table>'
            . '<Column ss:Width="150"/><Column ss:Width="140"/><Column ss:Width="70"/><Column ss:Width="50"/><Column ss:Width="110"/><Column ss:Width="100"/><Column ss:Width="100"/><Column ss:Width="100"/><Column ss:Width="110"/><Column ss:Width="110"/>'
            . implode('', $rows)
            . '</Table></Worksheet></Workbook>';

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->download('homiq-recent-reservations.xls', $content);
    }

    public function activity(string $type)
    {
        if (! in_array($type, ['check-in', 'check-out'], true)) {
            return $this->response->setStatusCode(404)->setBody('Aktivitas tidak ditemukan.');
        }

        $guesthouse = $this->guesthouse();
        $activityData = $this->todayActivities($guesthouse);

        return view('components/modals/dashboard_activity', [
            'type' => $type,
            'today' => $activityData['today'],
            'items' => $type === 'check-in' ? $activityData['checkIns'] : $activityData['checkOuts'],
        ]);
    }

    public function placeholder(string $page): string
    {
        return view('pages/placeholder', ['page' => ucwords(str_replace('-', ' ', $page))]);
    }

    private function todayActivities(?int $guesthouse = null): array
    {
        $db = db_connect();
        $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
        $paymentTotals = $db->table('payments')
            ->select('reservation_id, SUM(amount) AS paid_amount')
            ->where('status', 'Paid')
            ->groupBy('reservation_id')
            ->getCompiledSelect();
        $activityBuilder = static function () use ($db, $paymentTotals, $guesthouse) {
            $builder = $db->table('reservations')
            ->select('reservations.id, reservations.order_id, reservations.booking_group_id, reservations.source, reservations.status, reservations.total_amount, guests.full_name AS guest_name, rooms.room_number, COALESCE(paid.paid_amount, 0) AS paid_amount', false)
            ->join('guests', 'guests.id = reservations.guest_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->join("({$paymentTotals}) paid", 'paid.reservation_id = reservations.id', 'left', false);
            if ($guesthouse !== null) {
                $builder->where('rooms.floor', $guesthouse);
            }
            return $builder;
        };
        $decorate = static function (array $item, string $type): array {
            $item['activity_type'] = $type;
            $item['outstanding'] = max(0, (float) $item['total_amount'] - (float) $item['paid_amount']);
            return $item;
        };
        $checkIns = $activityBuilder()
            ->where('reservations.check_in_date', $today)
            ->whereIn('reservations.status', ['Confirmed', 'Checked_In'])
            ->orderBy('rooms.room_number', 'ASC')
            ->get()->getResultArray();
        $checkOuts = $activityBuilder()
            ->where('reservations.check_out_date', $today)
            ->where('reservations.status !=', 'Cancelled')
            ->where('reservations.status !=', 'No_Show')
            ->orderBy('rooms.room_number', 'ASC')
            ->get()->getResultArray();

        return [
            'today' => $today,
            'checkIns' => $this->groupActivityItems(array_map(static fn ($item) => $decorate($item, 'check-in'), $checkIns)),
            'checkOuts' => $this->groupActivityItems(array_map(static fn ($item) => $decorate($item, 'check-out'), $checkOuts)),
        ];
    }

    /**
     * A multi-room booking is stored as one reservation row per room. The
     * dashboard should show that booking as one guest activity instead.
     */
    private function groupActivityItems(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $groupId = trim((string) ($item['booking_group_id'] ?? ''));
            $key = $groupId !== '' ? 'group:' . $groupId : 'reservation:' . (string) $item['id'];

            if (! isset($groups[$key])) {
                $groups[$key] = $item;
                $groups[$key]['room_numbers'] = [];
                $groups[$key]['total_amount'] = 0;
                $groups[$key]['paid_amount'] = 0;
            }

            $groups[$key]['room_numbers'][] = (string) $item['room_number'];
            $groups[$key]['total_amount'] += (float) $item['total_amount'];
            $groups[$key]['paid_amount'] += (float) $item['paid_amount'];
        }

        foreach ($groups as &$group) {
            $group['room_numbers'] = array_values(array_unique($group['room_numbers']));
            usort($group['room_numbers'], static fn (string $left, string $right): int => strnatcasecmp($left, $right));
            $group['room_label'] = implode(', ', array_map(static fn (string $room): string => 'Kamar ' . $room, $group['room_numbers']));
            $group['room_number'] = $group['room_numbers'][0] ?? '';
            $group['room_count'] = count($group['room_numbers']);
            $group['outstanding'] = max(0, (float) $group['total_amount'] - (float) $group['paid_amount']);
        }
        unset($group);

        return array_values($groups);
    }

    private function month(): string
    {
        $month = (string) $this->request->getGet('month');
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 ? $month : date('Y-m');
    }

    private function guesthouse(): ?int
    {
        $guesthouse = (string) $this->request->getGet('guesthouse');
        return preg_match('/^[1-5]$/', $guesthouse) === 1 ? (int) $guesthouse : null;
    }
}
