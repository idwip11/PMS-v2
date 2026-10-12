<?php

namespace App\Controllers;

use App\Libraries\PropertyContext;

class HousekeepingController extends BaseController
{
    public function index(): string
    {
        return view('pages/housekeeping', [
            'guesthouse' => $this->validGuesthouse((string) $this->request->getGet('guesthouse')),
            'status' => $this->validRoomStatus((string) $this->request->getGet('status')),
            'pageTitle' => 'Housekeeping',
            'pageDescription' => 'Pantau kesiapan kamar dan distribusikan cleaning task.',
        ]);
    }

    public function panel(): string
    {
        $db = db_connect();
        $guesthouse = $this->validGuesthouse((string) $this->request->getGet('guesthouse'));
        $status = $this->validRoomStatus((string) $this->request->getGet('status'));
        $tasks = $db->table('cleaning_tasks')
            ->select("cleaning_tasks.*, GROUP_CONCAT(users.full_name SEPARATOR ', ') AS assignees", false)
            ->join('task_assignees', 'task_assignees.task_id = cleaning_tasks.id', 'left')
            ->join('users', 'users.id = task_assignees.user_id', 'left')
            ->where('cleaning_tasks.property_id', PropertyContext::activeId())
            ->groupBy('cleaning_tasks.id')->orderBy('scheduled_date', 'ASC')->get()->getResultArray();

        $rooms = $db->table('rooms')->where('property_id', PropertyContext::activeId());
        if ($guesthouse !== null) {
            $rooms->where('floor', $guesthouse);
        }
        if ($status !== null) {
            $rooms->where('status', $status);
        }

        if ($this->request->hasHeader('HX-Request')) {
            $query = http_build_query(array_filter(['guesthouse' => $guesthouse, 'status' => $status], static fn ($value): bool => $value !== null && $value !== ''));
            $query = $query !== '' ? '?' . $query : '';
            $this->response->setHeader('HX-Push-Url', site_url('housekeeping' . $query));
        }

        return view('components/housekeeping_panel', [
            'rooms' => $rooms->orderBy('room_number', 'ASC')->get()->getResultArray(),
            'guesthouse' => $guesthouse,
            'status' => $status,
            'tasks' => $tasks,
            'users' => $db->table('users')->select('users.*')->join('user_property_roles upr', 'upr.user_id = users.id')->where('upr.property_id', PropertyContext::activeId())->orderBy('users.full_name', 'ASC')->get()->getResultArray(),
        ]);
    }

    public function updateRoomStatus(int $id)
    {
        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['Available', 'Dirty', 'Maintenance', 'Occupied'], true)) {
            return $this->feedback('danger', 'Status kamar tidak valid.');
        }
        $db = db_connect();
        $room = $db->table('rooms')->where('property_id', PropertyContext::activeId())->where('id', $id)->get()->getRowArray();
        if ($room === null) {
            return $this->feedback('danger', 'Kamar tidak ditemukan.');
        }

        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('rooms')->where('id', $id)->update(['status' => $status, 'updated_at' => $now]);
        if ($status === 'Maintenance' && $room['status'] !== 'Maintenance') {
            $hasActiveTicket = $db->table('maintenance_tickets')
                ->where('room_id', $id)
                ->where('blocks_room', 1)
                ->whereIn('status', ['Open', 'Assigned', 'In_Progress', 'Waiting_Part', 'Completed'])
                ->countAllResults() > 0;
            if (! $hasActiveTicket) {
                $db->table('maintenance_tickets')->insert([
                    'room_id' => $id,
                    'reported_by_user_id' => (int) session('userId') ?: null,
                    'ticket_type' => 'Corrective',
                    'issue_type' => 'Room Maintenance',
                    'description' => 'Kamar ditandai maintenance dari Housekeeping. Lengkapi detail pekerjaan di menu Operasional → Maintenance.',
                    'priority' => 'Medium',
                    'status' => 'Open',
                    'blocks_room' => 1,
                    'previous_room_status' => $room['status'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $ticketId = (int) $db->insertID();
                $db->table('maintenance_tickets')->where('id', $ticketId)->update([
                    'ticket_number' => sprintf('WO-%s-%05d', date('Ymd'), $ticketId),
                ]);
            }
        }
        $db->transComplete();
        return $this->feedback('success', 'Status kamar diperbarui.');
    }

    public function createTask()
    {
        if (! $this->validate(['description' => 'required|max_length[255]', 'room_area' => 'required|max_length[100]', 'scheduled_date' => 'required|valid_date[Y-m-d]', 'priority' => 'required'])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }
        $db = db_connect();
        $userId = (int) $this->request->getPost('user_id');
        if ($userId > 0 && $db->table('user_property_roles')->where('user_id', $userId)->where('property_id', PropertyContext::activeId())->countAllResults() === 0) {
            return $this->feedback('danger', 'Petugas tidak ditugaskan pada properti aktif.');
        }
        $db->transStart();
        $db->table('cleaning_tasks')->insert([
            'property_id' => PropertyContext::activeId(),
            'description' => trim((string) $this->request->getPost('description')),
            'room_area' => trim((string) $this->request->getPost('room_area')),
            'scheduled_date' => $this->request->getPost('scheduled_date'),
            'priority' => $this->request->getPost('priority'),
            'status' => 'Pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $taskId = (int) $db->insertID();
        if ($userId > 0) $db->table('task_assignees')->insert(['task_id' => $taskId, 'user_id' => $userId]);
        $db->transComplete();
        return $this->feedback('success', 'Cleaning task berhasil dibuat.');
    }

    public function updateTaskStatus(int $id)
    {
        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['Pending', 'In_Progress', 'Completed'], true)) {
            return $this->feedback('danger', 'Status task tidak valid.');
        }
        db_connect()->table('cleaning_tasks')->where('property_id', PropertyContext::activeId())->where('id', $id)->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        return $this->feedback('success', 'Status cleaning task diperbarui.');
    }

    public function deleteTask(int $id)
    {
        $db = db_connect();
        $task = $db->table('cleaning_tasks')->select('id')->where('property_id', PropertyContext::activeId())->where('id', $id)->get()->getRowArray();
        if ($task === null) {
            return $this->feedback('danger', 'Cleaning task tidak ditemukan.');
        }

        $db->table('cleaning_tasks')->where('id', $id)->delete();
        return $this->feedback('success', 'Cleaning task berhasil dihapus.');
    }

    private function feedback(string $type, string $message)
    {
        return $this->response
            ->setHeader('HX-Trigger', json_encode(['housekeepingChanged' => true, 'opsChanged' => true, 'dashboardChanged' => true]))
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setBody('<div class="alert alert-' . $type . ' mb-0">' . esc($message) . '</div>');
    }

    private function validGuesthouse(string $guesthouse): ?int
    {
        if (PropertyContext::activeId() !== PropertyContext::HOMIQ_ID) return null;
        return preg_match('/^[1-5]$/', $guesthouse) === 1 ? (int) $guesthouse : null;
    }

    private function validRoomStatus(string $status): ?string
    {
        return in_array($status, ['Available', 'Occupied', 'Dirty', 'Maintenance'], true) ? $status : null;
    }
}
