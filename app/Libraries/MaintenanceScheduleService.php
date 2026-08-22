<?php

namespace App\Libraries;

use DateTimeImmutable;
use DateTimeZone;

class MaintenanceScheduleService
{
    public const UNITS = ['Day', 'Week', 'Month', 'Year'];

    /**
     * @return array{created:int, skipped:int, due:int}
     */
    public function generateDueTickets(?int $changedByUserId = null): array
    {
        $db = db_connect();
        $now = new DateTimeImmutable('now', new DateTimeZone(config('App')->appTimezone));
        $schedules = $db->table('maintenance_schedules')
            ->select('maintenance_schedules.*, room_assets.room_id, room_assets.asset_name, maintenance_categories.id AS category_id, maintenance_categories.name AS category_name, rooms.status AS room_status')
            ->join('room_assets', 'room_assets.id = maintenance_schedules.room_asset_id')
            ->join('maintenance_categories', 'maintenance_categories.id = room_assets.maintenance_category_id')
            ->join('rooms', 'rooms.id = room_assets.room_id')
            ->where('maintenance_schedules.is_active', 1)
            ->where('room_assets.is_active', 1)
            ->get()->getResultArray();

        $result = ['created' => 0, 'skipped' => 0, 'due' => 0];
        foreach ($schedules as $schedule) {
            $dueAt = $this->date($schedule['next_due_at']);
            $reminderAt = $now->modify('+' . max(0, (int) $schedule['reminder_days']) . ' days');
            if ($dueAt > $reminderAt) {
                continue;
            }
            ++$result['due'];

            $duplicate = $db->table('maintenance_tickets')
                ->where('maintenance_schedule_id', (int) $schedule['id'])
                ->where('schedule_due_at', $dueAt->format('Y-m-d H:i:s'))
                ->countAllResults();
            if ($duplicate > 0) {
                ++$result['skipped'];
                continue;
            }

            $status = ! empty($schedule['default_assignee_user_id']) || ! empty($schedule['default_vendor']) ? 'Assigned' : 'Open';
            $timestamp = $now->format('Y-m-d H:i:s');
            $description = 'Preventive maintenance: ' . $schedule['title'] . '. Jadwal jatuh tempo ' . $dueAt->format('d/m/Y H:i') . '.';

            $db->transStart();
            $db->table('maintenance_tickets')->insert([
                'room_id' => (int) $schedule['room_id'],
                'reported_by_user_id' => $changedByUserId,
                'assigned_to_user_id' => ! empty($schedule['default_assignee_user_id']) ? (int) $schedule['default_assignee_user_id'] : null,
                'maintenance_category_id' => (int) $schedule['category_id'],
                'room_asset_id' => (int) $schedule['room_asset_id'],
                'maintenance_schedule_id' => (int) $schedule['id'],
                'ticket_type' => 'Preventive',
                'issue_type' => $schedule['category_name'],
                'description' => $description,
                'priority' => 'Medium',
                'status' => $status,
                'scheduled_at' => $dueAt->format('Y-m-d H:i:s'),
                'schedule_due_at' => $dueAt->format('Y-m-d H:i:s'),
                'checklist_snapshot' => $schedule['checklist'],
                'vendor_name' => $schedule['default_vendor'] ?: null,
                'blocks_room' => (int) $schedule['blocks_room'],
                'previous_room_status' => (int) $schedule['blocks_room'] === 1 ? $schedule['room_status'] : null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $ticketId = (int) $db->insertID();
            $ticketNumber = sprintf('WO-%s-%05d', $now->format('Ymd'), $ticketId);
            $db->table('maintenance_tickets')->where('id', $ticketId)->update(['ticket_number' => $ticketNumber]);
            $db->table('maintenance_status_logs')->insert([
                'maintenance_ticket_id' => $ticketId,
                'from_status' => null,
                'to_status' => $status,
                'changed_by_user_id' => $changedByUserId,
                'notes' => 'Work Order preventive dibuat dari jadwal rutin ' . $schedule['title'] . '.',
                'created_at' => $timestamp,
            ]);
            $db->transComplete();

            if ($db->transStatus()) {
                ++$result['created'];
            }
        }

        return $result;
    }

    public function calculateNextDue(string $from, int $value, string $unit): string
    {
        $base = $this->date($from);
        $amount = max(1, $value);
        $modifier = match ($unit) {
            'Day' => '+' . $amount . ' days',
            'Week' => '+' . $amount . ' weeks',
            'Year' => '+' . $amount . ' years',
            default => '+' . $amount . ' months',
        };

        return $base->modify($modifier)->format('Y-m-d H:i:s');
    }

    public function completeSchedule(int $scheduleId, string $completedAt): ?string
    {
        $db = db_connect();
        $schedule = $db->table('maintenance_schedules')->where('id', $scheduleId)->get()->getRowArray();
        if ($schedule === null) {
            return null;
        }

        $nextDueAt = $this->calculateNextDue($completedAt, (int) $schedule['frequency_value'], (string) $schedule['frequency_unit']);
        $db->table('maintenance_schedules')->where('id', $scheduleId)->update([
            'last_completed_at' => $completedAt,
            'next_due_at' => $nextDueAt,
            'updated_at' => $completedAt,
        ]);

        return $nextDueAt;
    }

    /**
     * Create or reset the preventive schedule after a corrective work order is
     * closed. The category owns the interval and checklist; users do not need
     * to create a schedule manually.
     *
     * @param array<string, mixed> $ticket
     * @return array{next_due_at:?string,status:string}
     */
    public function syncFromCorrectiveTicket(array $ticket, string $completedAt): array
    {
        $categoryId = (int) ($ticket['maintenance_category_id'] ?? 0);
        $roomId = (int) ($ticket['room_id'] ?? 0);
        if ($categoryId <= 0 || $roomId <= 0) {
            return ['next_due_at' => null, 'status' => 'missing_category'];
        }

        $db = db_connect();
        $category = $db->table('maintenance_categories')->where('id', $categoryId)->where('is_active', 1)->get()->getRowArray();
        if ($category === null) {
            return ['next_due_at' => null, 'status' => 'missing_category'];
        }

        $assetId = (int) ($ticket['room_asset_id'] ?? 0);
        if ($assetId <= 0) {
            $assets = $db->table('room_assets')->where('room_id', $roomId)->where('maintenance_category_id', $categoryId)->where('is_active', 1)->limit(2)->get()->getResultArray();
            if (count($assets) !== 1) {
                return ['next_due_at' => null, 'status' => 'missing_asset'];
            }
            $assetId = (int) $assets[0]['id'];
            $db->table('maintenance_tickets')->where('id', (int) $ticket['id'])->update(['room_asset_id' => $assetId]);
        }
        $asset = $db->table('room_assets')->where('id', $assetId)->where('room_id', $roomId)->where('maintenance_category_id', $categoryId)->where('is_active', 1)->get()->getRowArray();
        if ($asset === null) {
            return ['next_due_at' => null, 'status' => 'missing_asset'];
        }

        $nextDueAt = $this->calculateNextDue($completedAt, (int) $category['default_interval_value'], (string) $category['default_interval_unit']);
        $payload = [
            'title' => 'Perawatan rutin ' . $category['name'] . ' · ' . $asset['asset_name'],
            'checklist' => $this->checklistJson($category['default_checklist'] ?? null),
            'frequency_value' => (int) $category['default_interval_value'],
            'frequency_unit' => (string) $category['default_interval_unit'],
            'last_completed_at' => $completedAt,
            'next_due_at' => $nextDueAt,
            'is_active' => 1,
            'updated_at' => $completedAt,
        ];
        $schedule = $db->table('maintenance_schedules')->where('room_asset_id', $assetId)->where('is_active', 1)->orderBy('id', 'DESC')->get()->getRowArray();
        if ($schedule === null) {
            $db->table('maintenance_schedules')->insert($payload + [
                'room_asset_id' => $assetId,
                'reminder_days' => 7,
                'default_assignee_user_id' => null,
                'default_vendor' => null,
                'blocks_room' => 0,
                'created_at' => $completedAt,
            ]);
            return ['next_due_at' => $nextDueAt, 'status' => 'created'];
        }

        $db->table('maintenance_schedules')->where('id', (int) $schedule['id'])->update($payload);
        return ['next_due_at' => $nextDueAt, 'status' => 'updated'];
    }

    public function checklistJson(?string $value): ?string
    {
        $items = $this->checklistItems($value);
        return $items === [] ? null : json_encode($items, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<string>
     */
    public function checklistItems(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $decoded), static fn (string $item): bool => $item !== ''));
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R/', $value) ?: []), static fn (string $item): bool => $item !== ''));
    }

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone(config('App')->appTimezone));
    }
}
