<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;

class MaintenanceReportService
{
    public const STATUSES = ['Open', 'Assigned', 'In_Progress', 'Waiting_Part', 'Completed', 'Resolved', 'Verified', 'Closed', 'Cancelled'];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @return array{room_id:int,category_id:int,status:string,date_from:string,date_to:string}
     */
    public function filters(array $input): array
    {
        $status = (string) ($input['status'] ?? '');
        if (! in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        $dateFrom = (string) ($input['date_from'] ?? '');
        $dateTo = (string) ($input['date_to'] ?? '');
        $validDate = static fn (string $value): bool => $value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;

        return [
            'room_id' => max(0, (int) ($input['room_id'] ?? 0)),
            'category_id' => max(0, (int) ($input['category_id'] ?? 0)),
            'status' => $status,
            'date_from' => $validDate($dateFrom) ? $dateFrom : '',
            'date_to' => $validDate($dateTo) ? $dateTo : '',
        ];
    }

    /**
     * @param array{room_id:int,category_id:int,status:string,date_from:string,date_to:string} $filters
     * @return list<array<string, mixed>>
     */
    public function history(array $filters): array
    {
        $builder = $this->historyBuilder();
        $this->applyFilters($builder, $filters);

        return $builder
            ->orderBy('maintenance_tickets.created_at', 'DESC')
            ->get()->getResultArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $timezone = new DateTimeZone(config('App')->appTimezone);
        $now = new DateTimeImmutable('now', $timezone);
        $today = $now->format('Y-m-d');
        $monthStart = $now->modify('first day of this month')->setTime(0, 0)->format('Y-m-d H:i:s');
        $monthEnd = $now->modify('first day of next month')->setTime(0, 0)->format('Y-m-d H:i:s');

        $ticketStats = $this->db->query(
            "SELECT
                SUM(t.status = 'Open') AS open_count,
                SUM(t.status = 'Assigned') AS assigned_count,
                SUM(t.status = 'In_Progress') AS in_progress_count,
                SUM(t.status = 'Waiting_Part') AS waiting_part_count
             FROM maintenance_tickets t JOIN rooms r ON r.id = t.room_id WHERE r.property_id = ?",
            [PropertyContext::activeId()]
        )->getRowArray() ?? [];

        $scheduleStats = $this->db->query(
            "SELECT
                SUM(s.is_active = 1 AND DATE(s.next_due_at) < ?) AS overdue,
                SUM(s.is_active = 1 AND DATE(s.next_due_at) BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)) AS due_7,
                SUM(s.is_active = 1 AND DATE(s.next_due_at) BETWEEN DATE_ADD(?, INTERVAL 8 DAY) AND DATE_ADD(?, INTERVAL 30 DAY)) AS due_30
             FROM maintenance_schedules s JOIN room_assets a ON a.id = s.room_asset_id JOIN rooms r ON r.id = a.room_id WHERE r.property_id = ?",
            [$today, $today, $today, $today, $today, PropertyContext::activeId()]
        )->getRowArray() ?? [];

        $serviceCost = $this->db->query(
            "SELECT COALESCE(SUM(t.labor_cost + t.external_cost), 0) AS total
             FROM maintenance_tickets t JOIN rooms r ON r.id = t.room_id
             WHERE COALESCE(t.completed_at, t.updated_at, t.created_at) >= ?
               AND COALESCE(t.completed_at, t.updated_at, t.created_at) < ? AND r.property_id = ?",
            [$monthStart, $monthEnd, PropertyContext::activeId()]
        )->getRowArray();
        $materialCost = $this->db->query(
            "SELECT COALESCE(SUM((items.quantity_used - items.quantity_returned) * items.unit_cost_snapshot), 0) AS total
             FROM maintenance_ticket_items items
             JOIN maintenance_tickets tickets ON tickets.id = items.maintenance_ticket_id
             JOIN rooms r ON r.id = tickets.room_id
             WHERE items.movement_type = 'Usage'
               AND COALESCE(tickets.completed_at, tickets.updated_at, tickets.created_at) >= ?
               AND COALESCE(tickets.completed_at, tickets.updated_at, tickets.created_at) < ? AND r.property_id = ?",
            [$monthStart, $monthEnd, PropertyContext::activeId()]
        )->getRowArray();
        $activeWork = $this->activeWorkDuration();

        $activeTickets = $this->db->table('maintenance_tickets')
            ->select('maintenance_tickets.id, maintenance_tickets.ticket_number, maintenance_tickets.status, maintenance_tickets.priority, maintenance_tickets.created_at, rooms.room_number, maintenance_categories.name AS category_name, room_assets.asset_name')
            ->join('rooms', 'rooms.id = maintenance_tickets.room_id')
            ->where('rooms.property_id', PropertyContext::activeId())
            ->join('maintenance_categories', 'maintenance_categories.id = maintenance_tickets.maintenance_category_id', 'left')
            ->join('room_assets', 'room_assets.id = maintenance_tickets.room_asset_id', 'left')
            ->whereIn('maintenance_tickets.status', ['Open', 'Assigned', 'In_Progress', 'Waiting_Part', 'Completed'])
            ->orderBy('maintenance_tickets.priority', 'DESC')
            ->orderBy('maintenance_tickets.created_at', 'ASC')
            ->limit(6)->get()->getResultArray();

        $repeatAssets = $this->db->query(
            "SELECT room_assets.asset_name, rooms.room_number, maintenance_categories.name AS category_name, COUNT(*) AS incident_count
             FROM maintenance_tickets
             JOIN room_assets ON room_assets.id = maintenance_tickets.room_asset_id
             JOIN rooms ON rooms.id = maintenance_tickets.room_id
             LEFT JOIN maintenance_categories ON maintenance_categories.id = maintenance_tickets.maintenance_category_id
             WHERE rooms.property_id = ? AND maintenance_tickets.ticket_type = 'Corrective'
               AND maintenance_tickets.created_at >= DATE_SUB(?, INTERVAL 12 MONTH)
             GROUP BY room_assets.id, room_assets.asset_name, rooms.room_number, maintenance_categories.name
             HAVING COUNT(*) >= 2
             ORDER BY incident_count DESC, rooms.room_number ASC
             LIMIT 5",
            [PropertyContext::activeId(), $now->format('Y-m-d H:i:s')]
        )->getResultArray();

        $lowStockParts = $this->db->table('inventory_items')
            ->where('property_id', PropertyContext::activeId())
            ->select('id, name, current_stock, min_threshold, unit')
            ->where('item_type', 'Spare Part')->where('is_active', 1)
            ->where('current_stock <= min_threshold', null, false)
            ->orderBy('current_stock', 'ASC')->limit(5)->get()->getResultArray();
        $lowStockPartCount = $this->db->table('inventory_items')
            ->where('property_id', PropertyContext::activeId())
            ->where('item_type', 'Spare Part')->where('is_active', 1)
            ->where('current_stock <= min_threshold', null, false)
            ->countAllResults();

        return [
            'tickets' => [
                'open' => (int) ($ticketStats['open_count'] ?? 0),
                'assigned' => (int) ($ticketStats['assigned_count'] ?? 0),
                'in_progress' => (int) ($ticketStats['in_progress_count'] ?? 0),
                'waiting_part' => (int) ($ticketStats['waiting_part_count'] ?? 0),
            ],
            'schedules' => [
                'overdue' => (int) ($scheduleStats['overdue'] ?? 0),
                'due_7' => (int) ($scheduleStats['due_7'] ?? 0),
                'due_30' => (int) ($scheduleStats['due_30'] ?? 0),
            ],
            'blocked_rooms' => $this->db->table('rooms')->where('property_id', PropertyContext::activeId())->where('status', 'Maintenance')->countAllResults(),
            'month_cost' => (float) ($serviceCost['total'] ?? 0) + (float) ($materialCost['total'] ?? 0),
            'mttr_minutes' => $activeWork['minutes'],
            'mttr_ticket_count' => $activeWork['ticket_count'],
            'active_tickets' => $activeTickets,
            'repeat_assets' => $repeatAssets,
            'low_stock_parts' => $lowStockParts,
            'low_stock_part_count' => $lowStockPartCount,
        ];
    }

    private function historyBuilder(): BaseBuilder
    {
        return $this->db->table('maintenance_tickets')
            ->select("maintenance_tickets.*, rooms.room_number, maintenance_categories.name AS category_name, room_assets.asset_name, assignee.full_name AS assigned_to_name, (SELECT COALESCE(SUM((report_items.quantity_used - report_items.quantity_returned) * report_items.unit_cost_snapshot), 0) FROM maintenance_ticket_items report_items WHERE report_items.maintenance_ticket_id = maintenance_tickets.id AND report_items.movement_type = 'Usage') AS material_cost", false)
            ->join('rooms', 'rooms.id = maintenance_tickets.room_id')
            ->where('rooms.property_id', PropertyContext::activeId())
            ->join('maintenance_categories', 'maintenance_categories.id = maintenance_tickets.maintenance_category_id', 'left')
            ->join('room_assets', 'room_assets.id = maintenance_tickets.room_asset_id', 'left')
            ->join('users assignee', 'assignee.id = maintenance_tickets.assigned_to_user_id', 'left');
    }

    /**
     * Calculate actual active work from status logs, not elapsed calendar time.
     * Time in Open, Assigned, and Waiting_Part is deliberately excluded.
     *
     * @return array{minutes:int,ticket_count:int}
     */
    private function activeWorkDuration(): array
    {
        $tickets = $this->db->table('maintenance_tickets')
            ->select('maintenance_tickets.id, maintenance_tickets.completed_at')
            ->join('rooms', 'rooms.id = maintenance_tickets.room_id')
            ->where('rooms.property_id', PropertyContext::activeId())
            ->where('maintenance_tickets.status', 'Closed')
            ->where('maintenance_tickets.completed_at IS NOT NULL', null, false)
            ->get()->getResultArray();
        if ($tickets === []) {
            return ['minutes' => 0, 'ticket_count' => 0];
        }

        $completedAt = [];
        foreach ($tickets as $ticket) {
            $completedAt[(int) $ticket['id']] = strtotime((string) $ticket['completed_at']);
        }
        $logs = $this->db->table('maintenance_status_logs')
            ->select('maintenance_ticket_id, to_status, created_at')
            ->whereIn('maintenance_ticket_id', array_keys($completedAt))
            ->orderBy('maintenance_ticket_id', 'ASC')->orderBy('created_at', 'ASC')->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $logsByTicket = [];
        foreach ($logs as $log) {
            $logsByTicket[(int) $log['maintenance_ticket_id']][] = $log;
        }

        $durations = [];
        foreach ($logsByTicket as $ticketId => $ticketLogs) {
            $activeMinutes = 0;
            foreach ($ticketLogs as $index => $log) {
                if (($log['to_status'] ?? '') !== 'In_Progress') {
                    continue;
                }
                $startedAt = strtotime((string) $log['created_at']);
                $nextLog = $ticketLogs[$index + 1] ?? null;
                $endedAt = $nextLog === null ? ($completedAt[$ticketId] ?? false) : strtotime((string) $nextLog['created_at']);
                if ($startedAt !== false && $endedAt !== false && $endedAt >= $startedAt) {
                    $activeMinutes += (int) floor(($endedAt - $startedAt) / 60);
                }
            }
            // Exclude legacy records without an In Progress status log: their
            // active duration cannot be determined accurately.
            if ($activeMinutes > 0) {
                $durations[] = $activeMinutes;
            }
        }

        return [
            'minutes' => $durations === [] ? 0 : (int) round(array_sum($durations) / count($durations)),
            'ticket_count' => count($durations),
        ];
    }

    /**
     * @param array{room_id:int,category_id:int,status:string,date_from:string,date_to:string} $filters
     */
    private function applyFilters(BaseBuilder $builder, array $filters): void
    {
        if ($filters['room_id'] > 0) {
            $builder->where('maintenance_tickets.room_id', $filters['room_id']);
        }
        if ($filters['category_id'] > 0) {
            $builder->where('maintenance_tickets.maintenance_category_id', $filters['category_id']);
        }
        if ($filters['status'] !== '') {
            $builder->where('maintenance_tickets.status', $filters['status']);
        }
        if ($filters['date_from'] !== '') {
            $builder->where('maintenance_tickets.created_at >=', $filters['date_from'] . ' 00:00:00');
        }
        if ($filters['date_to'] !== '') {
            $builder->where('maintenance_tickets.created_at <=', $filters['date_to'] . ' 23:59:59');
        }
    }
}
