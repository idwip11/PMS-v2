<?php

namespace App\Controllers;

use App\Libraries\MaintenanceScheduleService;
use DateTimeImmutable;

class MaintenanceScheduleController extends BaseController
{
    public function create()
    {
        if (! $this->validate($this->rules())) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        $payload = $this->payload();
        if ($payload === false) {
            return $this->feedback('danger', 'Tanggal jadwal tidak valid.');
        }
        if (! $this->assetExists((int) $payload['room_asset_id'])) {
            return $this->feedback('danger', 'Aset kamar tidak ditemukan atau sudah tidak aktif.');
        }
        if (! empty($payload['default_assignee_user_id']) && ! $this->userExists((int) $payload['default_assignee_user_id'])) {
            return $this->feedback('danger', 'Teknisi yang dipilih tidak ditemukan.');
        }

        $now = date('Y-m-d H:i:s');
        db_connect()->table('maintenance_schedules')->insert($payload + ['created_at' => $now, 'updated_at' => $now]);

        return $this->feedback('success', 'Jadwal preventive maintenance berhasil ditambahkan.');
    }

    public function detail(int $id): string
    {
        $data = $this->detailData($id);
        if ($data === null) {
            return '<div class="alert alert-danger m-3">Jadwal rutin tidak ditemukan.</div>';
        }

        return view('components/maintenance/schedule_modal', $data);
    }

    public function update(int $id)
    {
        if (! $this->validate($this->rules())) {
            return $this->detailResponse($id, 'danger', implode(' ', $this->validator->getErrors()));
        }
        if ($this->findSchedule($id) === null) {
            return $this->detailResponse($id, 'danger', 'Jadwal rutin tidak ditemukan.');
        }

        $payload = $this->payload();
        if ($payload === false) {
            return $this->detailResponse($id, 'danger', 'Tanggal jadwal tidak valid.');
        }
        if (! $this->assetExists((int) $payload['room_asset_id'])) {
            return $this->detailResponse($id, 'danger', 'Aset kamar tidak ditemukan atau sudah tidak aktif.');
        }
        if (! empty($payload['default_assignee_user_id']) && ! $this->userExists((int) $payload['default_assignee_user_id'])) {
            return $this->detailResponse($id, 'danger', 'Teknisi yang dipilih tidak ditemukan.');
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');
        db_connect()->table('maintenance_schedules')->where('id', $id)->update($payload);

        return $this->detailResponse($id, 'success', 'Jadwal rutin berhasil diperbarui.', true);
    }

    public function generateDue()
    {
        $result = (new MaintenanceScheduleService())->generateDueTickets($this->currentUserId());
        $message = $result['created'] . ' Work Order preventive dibuat';
        if ($result['skipped'] > 0) {
            $message .= ', ' . $result['skipped'] . ' jadwal sudah memiliki Work Order aktif';
        }
        if ($result['due'] === 0) {
            $message = 'Belum ada jadwal yang memasuki waktu pengingat.';
        }

        return $this->feedback('success', $message . '.');
    }

    /**
     * @return array<string, string>
     */
    private function rules(): array
    {
        return [
            'room_asset_id' => 'required|is_natural_no_zero',
            'title' => 'required|max_length[160]',
            'checklist' => 'permit_empty|max_length[8000]',
            'frequency_value' => 'required|integer|greater_than[0]',
            'frequency_unit' => 'required|in_list[Day,Week,Month,Year]',
            'last_completed_at' => 'permit_empty|max_length[20]',
            'next_due_at' => 'permit_empty|max_length[20]',
            'reminder_days' => 'required|integer|greater_than_equal_to[0]',
            'default_assignee_user_id' => 'permit_empty|is_natural_no_zero',
            'default_vendor' => 'permit_empty|max_length[150]',
            'blocks_room' => 'permit_empty|in_list[0,1]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];
    }

    /**
     * @return array<string, mixed>|false
     */
    private function payload(): array|false
    {
        $lastCompletedAt = $this->dateTimePost('last_completed_at');
        $nextDueAt = $this->dateTimePost('next_due_at');
        if ($lastCompletedAt === false || $nextDueAt === false) {
            return false;
        }

        $frequencyValue = max(1, (int) $this->request->getPost('frequency_value'));
        $frequencyUnit = (string) $this->request->getPost('frequency_unit');
        $now = date('Y-m-d H:i:s');
        if ($nextDueAt === null) {
            $nextDueAt = (new MaintenanceScheduleService())->calculateNextDue($lastCompletedAt ?? $now, $frequencyValue, $frequencyUnit);
        }

        return [
            'room_asset_id' => (int) $this->request->getPost('room_asset_id'),
            'title' => trim((string) $this->request->getPost('title')),
            'checklist' => (new MaintenanceScheduleService())->checklistJson($this->nullablePost('checklist')),
            'frequency_value' => $frequencyValue,
            'frequency_unit' => $frequencyUnit,
            'last_completed_at' => $lastCompletedAt,
            'next_due_at' => $nextDueAt,
            'reminder_days' => max(0, (int) $this->request->getPost('reminder_days')),
            'default_assignee_user_id' => max(0, (int) $this->request->getPost('default_assignee_user_id')) ?: null,
            'default_vendor' => $this->nullablePost('default_vendor'),
            'blocks_room' => $this->request->getPost('blocks_room') === '1' ? 1 : 0,
            'is_active' => $this->request->getPost('is_active') === '0' ? 0 : 1,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function detailData(int $id, ?string $feedbackType = null, ?string $feedbackMessage = null): ?array
    {
        $db = db_connect();
        $schedule = $db->table('maintenance_schedules')
            ->select('maintenance_schedules.*, room_assets.asset_name, rooms.room_number, maintenance_categories.name AS category_name')
            ->join('room_assets', 'room_assets.id = maintenance_schedules.room_asset_id')
            ->join('rooms', 'rooms.id = room_assets.room_id')
            ->join('maintenance_categories', 'maintenance_categories.id = room_assets.maintenance_category_id')
            ->where('maintenance_schedules.id', $id)
            ->get()->getRowArray();
        if ($schedule === null) {
            return null;
        }

        return [
            'schedule' => $schedule,
            'roomAssets' => $db->table('room_assets')
                ->select('room_assets.*, rooms.room_number, maintenance_categories.name AS category_name')
                ->join('rooms', 'rooms.id = room_assets.room_id')
                ->join('maintenance_categories', 'maintenance_categories.id = room_assets.maintenance_category_id')
                ->where('room_assets.is_active', 1)
                ->orderBy('rooms.room_number', 'ASC')
                ->orderBy('room_assets.asset_name', 'ASC')
                ->get()->getResultArray(),
            'users' => $db->table('users')->orderBy('full_name', 'ASC')->get()->getResultArray(),
            'feedbackType' => $feedbackType,
            'feedbackMessage' => $feedbackMessage,
        ];
    }

    private function detailResponse(int $id, string $type, string $message, bool $changed = false)
    {
        $data = $this->detailData($id, $type, $message);
        $body = $data === null
            ? '<div class="alert alert-danger m-3">Jadwal rutin tidak ditemukan.</div>'
            : view('components/maintenance/schedule_content', $data);
        $response = $this->response->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody($body);
        if ($changed) {
            $response->setHeader('HX-Trigger', json_encode(['opsChanged' => true, 'dashboardChanged' => true]));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findSchedule(int $id): ?array
    {
        return db_connect()->table('maintenance_schedules')->where('id', $id)->get()->getRowArray();
    }

    private function assetExists(int $id): bool
    {
        return db_connect()->table('room_assets')->where('id', $id)->where('is_active', 1)->countAllResults() > 0;
    }

    private function userExists(int $id): bool
    {
        return db_connect()->table('users')->where('id', $id)->countAllResults() > 0;
    }

    private function currentUserId(): ?int
    {
        $id = (int) session('userId');
        return $id > 0 ? $id : null;
    }

    private function nullablePost(string $key): ?string
    {
        $value = trim((string) $this->request->getPost($key));
        return $value === '' ? null : $value;
    }

    private function dateTimePost(string $key): string|false|null
    {
        $value = trim((string) $this->request->getPost($key));
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
        return $date !== false && $date->format('Y-m-d\TH:i') === $value ? $date->format('Y-m-d H:i:s') : false;
    }

    private function feedback(string $type, string $message)
    {
        return $this->response
            ->setHeader('HX-Trigger', json_encode(['opsChanged' => true, 'dashboardChanged' => true]))
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setBody('<div class="alert alert-' . $type . ' mb-0">' . esc($message) . '</div>');
    }
}
