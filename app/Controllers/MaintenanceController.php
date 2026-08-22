<?php

namespace App\Controllers;

use App\Libraries\ImageUploadService;
use App\Libraries\MaintenanceScheduleService;
use DateTimeImmutable;

class MaintenanceController extends BaseController
{
    private const ATTACHMENT_TYPES = ['Before', 'After', 'Invoice', 'Other'];
    private const MAX_ATTACHMENT_SIZE = 5242880;
    private const ACTIVE_STATUSES = ['Open', 'Assigned', 'In_Progress', 'Waiting_Part', 'Completed'];
    private const BLOCKING_STATUSES = ['Open', 'Assigned', 'In_Progress', 'Waiting_Part'];
    private const WORK_STATUSES = ['Open', 'Assigned', 'In_Progress', 'Waiting_Part'];
    private const MANAGER_ROLES = ['Atmin', 'Admin', 'Manager'];
    private const CANCELLER_ROLES = ['Atmin', 'Admin', 'Manager', 'Operational'];
    private const WORKFLOW_ROLES = ['Atmin', 'Admin', 'Manager', 'Operational'];
    private const TRANSITIONS = [
        'Open' => ['Cancelled'],
        'Assigned' => ['In_Progress', 'Cancelled'],
        'In_Progress' => ['Waiting_Part', 'Cancelled'],
        'Waiting_Part' => ['In_Progress', 'Cancelled'],
        'Completed' => ['Verified', 'Closed'],
        'Resolved' => ['Verified', 'Closed'],
        'Verified' => ['Closed'],
    ];

    public function create()
    {
        if (! $this->validate([
            'room_id' => 'required|is_natural_no_zero',
            'maintenance_category_id' => 'required|is_natural_no_zero',
            'room_asset_id' => 'permit_empty|is_natural_no_zero',
            'assigned_to_user_id' => 'permit_empty|is_natural_no_zero',
            'description' => 'required|max_length[2000]',
            'priority' => 'required|in_list[Low,Medium,High,Urgent]',
            'vendor_name' => 'permit_empty|max_length[150]',
            'scheduled_at' => 'permit_empty|max_length[20]',
            'blocks_room' => 'permit_empty|in_list[0,1]',
        ])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        $scheduledAt = $this->dateTimePost('scheduled_at');
        if ($scheduledAt === false) {
            return $this->feedback('danger', 'Format jadwal pekerjaan tidak valid.');
        }

        $db = db_connect();
        $roomId = (int) $this->request->getPost('room_id');
        $categoryId = (int) $this->request->getPost('maintenance_category_id');
        $assetId = max(0, (int) $this->request->getPost('room_asset_id'));
        $assigneeId = max(0, (int) $this->request->getPost('assigned_to_user_id'));
        $vendorName = $this->nullablePost('vendor_name');
        $blocksRoom = $this->request->getPost('blocks_room') === '1';
        $room = $db->table('rooms')->where('id', $roomId)->get()->getRowArray();
        $category = $db->table('maintenance_categories')->where('id', $categoryId)->where('is_active', 1)->get()->getRowArray();

        if ($room === null || $category === null) {
            return $this->feedback('danger', 'Kamar atau kategori maintenance tidak valid.');
        }
        if ($assigneeId > 0 && ! $this->userExists($assigneeId)) {
            return $this->feedback('danger', 'Teknisi yang dipilih tidak ditemukan.');
        }
        if ($assetId > 0 && ! $this->assetMatches($assetId, $roomId, $categoryId)) {
            return $this->feedback('danger', 'Aset tidak sesuai dengan kamar dan kategori yang dipilih.');
        }

        $status = ($assigneeId > 0 || $vendorName !== null) ? 'Assigned' : 'Open';
        $previousRoomStatus = null;
        if ($blocksRoom) {
            $previousRoomStatus = $this->previousRoomStatus($roomId, (string) $room['status']);
        }

        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('maintenance_tickets')->insert([
            'room_id' => $roomId,
            'reported_by_user_id' => $this->currentUserId(),
            'assigned_to_user_id' => $assigneeId > 0 ? $assigneeId : null,
            'maintenance_category_id' => $categoryId,
            'room_asset_id' => $assetId > 0 ? $assetId : null,
            'ticket_type' => 'Corrective',
            'issue_type' => $category['name'],
            'description' => trim((string) $this->request->getPost('description')),
            'priority' => (string) $this->request->getPost('priority'),
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'vendor_name' => $vendorName,
            'blocks_room' => $blocksRoom ? 1 : 0,
            'previous_room_status' => $previousRoomStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ticketId = (int) $db->insertID();
        $ticketNumber = sprintf('WO-%s-%05d', date('Ymd'), $ticketId);
        $db->table('maintenance_tickets')->where('id', $ticketId)->update(['ticket_number' => $ticketNumber]);
        $this->logStatus($ticketId, null, $status, 'Work Order dibuat.');

        if ($blocksRoom) {
            $db->table('rooms')->where('id', $roomId)->update(['status' => 'Maintenance', 'updated_at' => $now]);
        }
        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->feedback('danger', 'Work Order gagal disimpan. Silakan coba kembali.');
        }

        $message = 'Work Order ' . $ticketNumber . ' berhasil dibuat.';
        if ($blocksRoom) {
            $message .= ' Kamar diblokir untuk maintenance.';
        }

        return $this->feedback('success', $message);
    }

    public function detail(int $id): string
    {
        $data = $this->detailData($id);
        if ($data === null) {
            return '<div class="alert alert-danger m-3">Work Order tidak ditemukan.</div>';
        }

        return view('components/maintenance/work_order_modal', $data);
    }

    public function assign(int $id)
    {
        if (! $this->validate([
            'assigned_to_user_id' => 'permit_empty|is_natural_no_zero',
            'vendor_name' => 'permit_empty|max_length[150]',
            'scheduled_at' => 'permit_empty|max_length[20]',
        ])) {
            return $this->detailResponse($id, 'danger', implode(' ', $this->validator->getErrors()));
        }

        $ticket = $this->findTicket($id);
        if ($ticket === null) {
            return $this->detailResponse($id, 'danger', 'Work Order tidak ditemukan.');
        }
        if (! in_array($ticket['status'], ['Open', 'Assigned', 'In_Progress', 'Waiting_Part'], true)) {
            return $this->detailResponse($id, 'danger', 'Penugasan tidak dapat diubah pada status ini.');
        }

        $assigneeId = max(0, (int) $this->request->getPost('assigned_to_user_id'));
        $vendorName = $this->nullablePost('vendor_name');
        $scheduledAt = $this->dateTimePost('scheduled_at');
        if ($scheduledAt === false) {
            return $this->detailResponse($id, 'danger', 'Format jadwal pekerjaan tidak valid.');
        }
        if ($assigneeId > 0 && ! $this->userExists($assigneeId)) {
            return $this->detailResponse($id, 'danger', 'Teknisi yang dipilih tidak ditemukan.');
        }
        if ($assigneeId === 0 && $vendorName === null) {
            return $this->detailResponse($id, 'danger', 'Pilih teknisi internal atau isi nama vendor.');
        }

        $newStatus = $ticket['status'] === 'Open' ? 'Assigned' : $ticket['status'];
        $now = date('Y-m-d H:i:s');
        $db = db_connect();
        $db->transStart();
        $db->table('maintenance_tickets')->where('id', $id)->update([
            'assigned_to_user_id' => $assigneeId > 0 ? $assigneeId : null,
            'vendor_name' => $vendorName,
            'scheduled_at' => $scheduledAt,
            'status' => $newStatus,
            'updated_at' => $now,
        ]);
        $this->logStatus($id, $ticket['status'], $newStatus, $newStatus === $ticket['status'] ? 'Penugasan diperbarui.' : 'Work Order ditugaskan.');
        $db->transComplete();

        return $this->detailResponse($id, 'success', 'Penugasan berhasil diperbarui.', true);
    }

    public function updateClassification(int $id)
    {
        if (! $this->validate([
            'maintenance_category_id' => 'required|is_natural_no_zero',
            'room_asset_id' => 'permit_empty|is_natural_no_zero',
        ])) {
            return $this->detailResponse($id, 'danger', implode(' ', $this->validator->getErrors()));
        }

        $ticket = $this->findTicket($id);
        if ($ticket === null) {
            return $this->detailResponse($id, 'danger', 'Work Order tidak ditemukan.');
        }
        if (! in_array($ticket['status'], ['Open', 'Assigned', 'In_Progress', 'Waiting_Part'], true)) {
            return $this->detailResponse($id, 'danger', 'Kategori dan aset tidak dapat diubah pada status ini.');
        }

        $db = db_connect();
        $categoryId = (int) $this->request->getPost('maintenance_category_id');
        $assetId = max(0, (int) $this->request->getPost('room_asset_id'));
        $category = $db->table('maintenance_categories')->where('id', $categoryId)->where('is_active', 1)->get()->getRowArray();
        if ($category === null) {
            return $this->detailResponse($id, 'danger', 'Kategori maintenance tidak valid.');
        }
        if ($assetId > 0 && ! $this->assetMatches($assetId, (int) $ticket['room_id'], $categoryId)) {
            return $this->detailResponse($id, 'danger', 'Aset harus sesuai dengan kamar dan kategori yang dipilih.');
        }

        $db->transStart();
        $db->table('maintenance_tickets')->where('id', $id)->update([
            'maintenance_category_id' => $categoryId,
            'room_asset_id' => $assetId > 0 ? $assetId : null,
            'issue_type' => $category['name'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->logStatus($id, $ticket['status'], $ticket['status'], 'Kategori dan aset kamar diperbarui.');
        $db->transComplete();

        return $this->detailResponse($id, 'success', 'Kategori dan aset kamar berhasil disimpan.', true);
    }

    public function saveWork(int $id)
    {
        $ticket = $this->findTicket($id);
        if ($ticket === null) {
            return $this->detailResponse($id, 'danger', 'Work Order tidak ditemukan.');
        }
        if (! in_array($ticket['status'], self::WORK_STATUSES, true)) {
            return $this->detailResponse($id, 'danger', 'Catatan pekerjaan tidak dapat diubah pada status ini.');
        }
        if (! $this->validate($this->workRules(false))) {
            return $this->detailResponse($id, 'danger', implode(' ', $this->validator->getErrors()));
        }

        $payload = $this->workPayload($ticket);
        $payload['updated_at'] = date('Y-m-d H:i:s');
        $db = db_connect();
        $db->transStart();
        $db->table('maintenance_tickets')->where('id', $id)->update($payload);
        $this->logStatus($id, $ticket['status'], $ticket['status'], 'Catatan pekerjaan dan biaya diperbarui.');
        $db->transComplete();

        return $this->detailResponse($id, 'success', 'Catatan pekerjaan berhasil disimpan.', true);
    }

    public function complete(int $id)
    {
        $ticket = $this->findTicket($id);
        if ($ticket === null) {
            return $this->detailResponse($id, 'danger', 'Work Order tidak ditemukan.');
        }
        if (! in_array($ticket['status'], ['In_Progress', 'Waiting_Part'], true)) {
            return $this->detailResponse($id, 'danger', 'Work Order harus sedang dikerjakan sebelum diselesaikan.');
        }
        if (! $this->validate($this->workRules(true), $this->workValidationMessages())) {
            return $this->detailResponse($id, 'danger', 'Isi Diagnosis dan Tindakan / penyelesaian terlebih dahulu sebelum menandai Work Order selesai.');
        }
        $checklist = $this->completedChecklist($ticket);
        if ($checklist === false) {
            return $this->detailResponse($id, 'danger', 'Checklist preventive harus diselesaikan sebelum Work Order ditandai selesai.');
        }

        $now = date('Y-m-d H:i:s');
        $payload = $this->workPayload($ticket);
        if ($checklist !== null) {
            $payload['checklist_completed'] = json_encode($checklist, JSON_UNESCAPED_UNICODE);
        }
        $payload += ['status' => 'Closed', 'completed_at' => $now, 'updated_at' => $now];
        $db = db_connect();
        $db->transStart();
        $nextServiceAt = $this->syncAutomaticSchedule($ticket, $now);
        if ($nextServiceAt !== null) $payload['next_service_at'] = $nextServiceAt;
        $db->table('maintenance_tickets')->where('id', $id)->update($payload);
        $note = ! empty($payload['next_service_at'])
            ? 'Pekerjaan preventive selesai. Jadwal berikutnya ' . substr((string) $payload['next_service_at'], 0, 16) . '.'
            : 'Pekerjaan selesai dan Work Order ditutup.';
        $this->logStatus($id, $ticket['status'], 'Closed', $note);
        $this->releaseRoom($ticket, 'Available');
        $this->syncMaintenanceExpense(array_merge($ticket, $payload), $id, $now);
        $db->transComplete();

        return $this->detailResponse($id, 'success', 'Pekerjaan selesai. Work Order ditutup dan kamar kembali Available.', true);
    }

    public function addPart(int $id)
    {
        $ticket = $this->findTicket($id);
        if ($ticket === null) {
            return $this->detailResponse($id, 'danger', 'Work Order tidak ditemukan.');
        }
        if (! in_array($ticket['status'], self::WORK_STATUSES, true)) {
            return $this->detailResponse($id, 'danger', 'Spare part hanya dapat dicatat saat Work Order masih aktif.');
        }
        if (! $this->validate([
            'inventory_item_id' => 'required|is_natural_no_zero',
            'quantity_used' => 'required|integer|greater_than[0]',
            'notes' => 'permit_empty|max_length[500]',
        ])) {
            return $this->detailResponse($id, 'danger', implode(' ', $this->validator->getErrors()));
        }

        $itemId = (int) $this->request->getPost('inventory_item_id');
        $quantity = (int) $this->request->getPost('quantity_used');
        $notes = $this->nullablePost('notes');
        $db = db_connect();
        $item = $db->table('inventory_items')->where('id', $itemId)->where('item_type', 'Spare Part')->where('is_active', 1)->get()->getRowArray();
        if ($item === null) {
            return $this->detailResponse($id, 'danger', 'Spare part tidak ditemukan atau tidak aktif.');
        }

        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('inventory_items')
            ->set('current_stock', 'current_stock - ' . $quantity, false)
            ->set('updated_at', $now)
            ->where('id', $itemId)
            ->where('current_stock >=', $quantity)
            ->update();
        if ($db->affectedRows() !== 1) {
            $db->transRollback();
            return $this->detailResponse($id, 'danger', 'Stok spare part tidak mencukupi.');
        }
        $updatedItem = $db->table('inventory_items')->select('current_stock')->where('id', $itemId)->get()->getRowArray();
        $db->table('maintenance_ticket_items')->insert([
            'maintenance_ticket_id' => $id,
            'inventory_item_id' => $itemId,
            'movement_type' => 'Usage',
            'quantity_used' => $quantity,
            'quantity_returned' => 0,
            'unit_cost_snapshot' => (float) $item['unit_cost'],
            'notes' => $notes,
            'created_by_user_id' => $this->currentUserId(),
            'created_at' => $now,
        ]);
        $ticketItemId = (int) $db->insertID();
        $db->table('inventory_stock_movements')->insert([
            'inventory_item_id' => $itemId,
            'maintenance_ticket_item_id' => $ticketItemId,
            'movement_type' => 'Usage',
            'quantity_change' => -$quantity,
            'balance_after' => (int) ($updatedItem['current_stock'] ?? 0),
            'reference_type' => 'MaintenanceWorkOrder',
            'reference_id' => $id,
            'notes' => $notes ?? ('Pemakaian untuk ' . ($ticket['ticket_number'] ?? ('WO-' . $id))),
            'created_by_user_id' => $this->currentUserId(),
            'created_at' => $now,
        ]);
        $this->logStatus($id, $ticket['status'], $ticket['status'], 'Spare part ' . $item['name'] . ' sebanyak ' . $quantity . ' ' . $item['unit'] . ' digunakan.');
        $db->transComplete();

        return $this->detailResponse($id, 'success', 'Spare part digunakan dan stok telah diperbarui.', true);
    }

    public function returnPart(int $id, int $ticketItemId)
    {
        $ticket = $this->findTicket($id);
        if ($ticket === null) {
            return $this->detailResponse($id, 'danger', 'Work Order tidak ditemukan.');
        }
        if (! in_array($ticket['status'], self::WORK_STATUSES, true)) {
            return $this->detailResponse($id, 'danger', 'Pengembalian spare part hanya dapat dicatat saat Work Order masih aktif.');
        }
        if (! $this->validate(['quantity_returned' => 'required|integer|greater_than[0]'])) {
            return $this->detailResponse($id, 'danger', 'Jumlah pengembalian harus lebih dari nol.');
        }

        $quantity = (int) $this->request->getPost('quantity_returned');
        $db = db_connect();
        $ticketItem = $db->table('maintenance_ticket_items')
            ->select('maintenance_ticket_items.*, inventory_items.name, inventory_items.unit')
            ->join('inventory_items', 'inventory_items.id = maintenance_ticket_items.inventory_item_id')
            ->where('maintenance_ticket_items.id', $ticketItemId)
            ->where('maintenance_ticket_items.maintenance_ticket_id', $id)
            ->where('maintenance_ticket_items.movement_type', 'Usage')
            ->get()->getRowArray();
        if ($ticketItem === null || $quantity > ((int) $ticketItem['quantity_used'] - (int) $ticketItem['quantity_returned'])) {
            return $this->detailResponse($id, 'danger', 'Jumlah pengembalian melebihi spare part yang digunakan.');
        }

        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('maintenance_ticket_items')->where('id', $ticketItemId)->set('quantity_returned', 'quantity_returned + ' . $quantity, false)->update();
        $db->table('inventory_items')->where('id', $ticketItem['inventory_item_id'])->set('current_stock', 'current_stock + ' . $quantity, false)->set('updated_at', $now)->update();
        $updatedItem = $db->table('inventory_items')->select('current_stock')->where('id', $ticketItem['inventory_item_id'])->get()->getRowArray();
        $db->table('inventory_stock_movements')->insert([
            'inventory_item_id' => (int) $ticketItem['inventory_item_id'],
            'maintenance_ticket_item_id' => $ticketItemId,
            'movement_type' => 'Return',
            'quantity_change' => $quantity,
            'balance_after' => (int) ($updatedItem['current_stock'] ?? 0),
            'reference_type' => 'MaintenanceWorkOrder',
            'reference_id' => $id,
            'notes' => 'Pengembalian dari ' . ($ticket['ticket_number'] ?? ('WO-' . $id)),
            'created_by_user_id' => $this->currentUserId(),
            'created_at' => $now,
        ]);
        $this->logStatus($id, $ticket['status'], $ticket['status'], 'Spare part ' . $ticketItem['name'] . ' sebanyak ' . $quantity . ' ' . $ticketItem['unit'] . ' dikembalikan ke stok.');
        $db->transComplete();

        return $this->detailResponse($id, 'success', 'Pengembalian spare part berhasil dicatat.', true);
    }

    public function uploadAttachment(int $id)
    {
        $ticket = $this->findTicket($id);
        if ($ticket === null) {
            return $this->detailResponse($id, 'danger', 'Work Order tidak ditemukan.');
        }

        $attachmentType = (string) $this->request->getPost('attachment_type');
        if (! in_array($attachmentType, self::ATTACHMENT_TYPES, true)) {
            return $this->detailResponse($id, 'danger', 'Jenis lampiran tidak valid.');
        }

        $file = $this->request->getFile('attachment');
        if ($file === null || ! $file->isValid() || $file->hasMoved()) {
            return $this->detailResponse($id, 'danger', 'Pilih file JPG, PNG, atau PDF yang valid.');
        }
        $mimeType = strtolower((string) $file->getMimeType());
        $subdirectory = 'maintenance/' . date('Y/m');
        $storedFile = null;
        if ($mimeType === 'application/pdf') {
            $pdfSize = (int) $file->getSize();
            $pdfOriginalName = substr(trim((string) $file->getClientName()), 0, 255) ?: 'lampiran.pdf';
            if ($pdfSize <= 0 || $pdfSize > self::MAX_ATTACHMENT_SIZE) {
                return $this->detailResponse($id, 'danger', 'Ukuran PDF maksimal 5 MB.');
            }
            $directory = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdirectory);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return $this->detailResponse($id, 'danger', 'Folder penyimpanan lampiran tidak dapat dibuat.');
            }
            $storedName = bin2hex(random_bytes(20)) . '.pdf';
            $file->move($directory, $storedName);
            $storedFile = [
                'relative_path' => $subdirectory . '/' . $storedName,
                'absolute_path' => $directory . DIRECTORY_SEPARATOR . $storedName,
                'original_name' => $pdfOriginalName,
                'mime_type' => 'application/pdf',
                'file_size' => $pdfSize,
            ];
        } else {
            try {
                $storedFile = (new ImageUploadService())->store($file, $subdirectory);
            } catch (\RuntimeException $error) {
                return $this->detailResponse($id, 'danger', $error->getMessage());
            }
        }

        $now = date('Y-m-d H:i:s');
        $db = db_connect();
        $db->transStart();
        $db->table('maintenance_attachments')->insert([
            'maintenance_ticket_id' => $id,
            'file_path' => $storedFile['relative_path'],
            'original_name' => $storedFile['original_name'],
            'mime_type' => $storedFile['mime_type'],
            'file_size' => $storedFile['file_size'],
            'attachment_type' => $attachmentType,
            'uploaded_by_user_id' => $this->currentUserId(),
            'created_at' => $now,
        ]);
        // An Invoice attachment is the financial proof for the Maintenance
        // expense generated from this Work Order. Re-sync it immediately so
        // an owner report does not depend on opening the Operations page first.
        if ($attachmentType === 'Invoice' && (string) ($ticket['status'] ?? '') === 'Closed') {
            $this->syncMaintenanceExpense($ticket, $id, $now);
        }
        $this->logStatus($id, $ticket['status'], $ticket['status'], 'Lampiran ' . $this->attachmentTypeLabel($attachmentType) . ' ditambahkan.');
        $db->transComplete();

        if (! $db->transStatus()) {
            @unlink($storedFile['absolute_path']);
            return $this->detailResponse($id, 'danger', 'Lampiran gagal disimpan.');
        }

        return $this->detailResponse($id, 'success', 'Lampiran berhasil ditambahkan.', true);
    }

    public function attachment(int $id)
    {
        $attachment = db_connect()->table('maintenance_attachments')->where('id', $id)->get()->getRowArray();
        if ($attachment === null) {
            return $this->response->setStatusCode(404)->setBody('Lampiran tidak ditemukan.');
        }

        $uploadsRoot = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads');
        $absolutePath = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $attachment['file_path']));
        if ($uploadsRoot === false || $absolutePath === false || ! str_starts_with($absolutePath, $uploadsRoot . DIRECTORY_SEPARATOR) || ! is_file($absolutePath)) {
            return $this->response->setStatusCode(404)->setBody('File lampiran tidak ditemukan.');
        }

        $safeName = str_replace(["\r", "\n", '"'], '', (string) $attachment['original_name']);
        return $this->response
            ->setHeader('Content-Type', (string) $attachment['mime_type'])
            ->setHeader('Content-Length', (string) filesize($absolutePath))
            ->setHeader('Content-Disposition', 'inline; filename="' . $safeName . '"')
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($absolutePath));
    }

    public function transition(int $id)
    {
        $target = (string) $this->request->getPost('status');
        $note = $this->nullablePost('transition_note');
        $ticket = $this->findTicket($id);
        if ($ticket === null) {
            return $this->detailResponse($id, 'danger', 'Work Order tidak ditemukan.');
        }

        $allowed = self::TRANSITIONS[$ticket['status']] ?? [];
        if (! in_array($target, $allowed, true)) {
            return $this->detailResponse($id, 'danger', 'Perpindahan status tidak valid.');
        }
        if (in_array($target, ['Verified', 'Closed'], true) && ! $this->canProcessCompletedWork()) {
            return $this->detailResponse($id, 'danger', 'Verifikasi atau penutupan Work Order hanya dapat diproses oleh Operational, Admin, atau Manager.');
        }
        if ($target === 'Cancelled' && ! $this->canCancel()) {
            return $this->detailResponse($id, 'danger', 'Pembatalan Work Order hanya dapat diproses oleh Operational, Admin, atau Manager.');
        }

        $now = date('Y-m-d H:i:s');
        $payload = ['status' => $target, 'updated_at' => $now];
        if ($target === 'In_Progress' && empty($ticket['started_at'])) {
            $payload['started_at'] = $now;
        } elseif ($target === 'Verified') {
            $payload['verified_at'] = $now;
            $payload['verified_by_user_id'] = $this->currentUserId();
        } elseif ($target === 'Cancelled') {
            $payload['completed_at'] = $now;
        }

        $db = db_connect();
        $db->transStart();
        $db->table('maintenance_tickets')->where('id', $id)->update($payload);
        $this->logStatus($id, $ticket['status'], $target, $note ?? $this->defaultTransitionNote($target));
        if ($target === 'In_Progress') {
            $this->blockRoomForWork($ticket, $now);
        } elseif ($target === 'Verified' || $target === 'Closed') {
            $this->releaseRoom($ticket, 'Available');
        } elseif ($target === 'Cancelled') {
            $this->releaseRoom($ticket, $this->restoredRoomStatus($ticket));
        }
        if ($target === 'Closed') {
            $nextServiceAt = $this->syncAutomaticSchedule($ticket, $now);
            if ($nextServiceAt !== null) {
                $db->table('maintenance_tickets')->where('id', $id)->update(['next_service_at' => $nextServiceAt]);
            }
            $this->syncMaintenanceExpense($ticket, $id, $now);
        }
        $db->transComplete();

        return $this->detailResponse($id, 'success', 'Status Work Order diperbarui menjadi ' . $this->statusLabel($target) . '.', true);
    }

    /**
     * @return array<string, string>
     */
    private function workRules(bool $required): array
    {
        return [
            'diagnosis' => ($required ? 'required' : 'permit_empty') . '|max_length[4000]',
            'resolution' => ($required ? 'required' : 'permit_empty') . '|max_length[4000]',
            'follow_up_notes' => 'permit_empty|max_length[4000]',
            'labor_cost' => 'permit_empty|integer|greater_than_equal_to[0]',
            'external_cost' => 'permit_empty|integer|greater_than_equal_to[0]',
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function workValidationMessages(): array
    {
        return [
            'diagnosis' => [
                'required' => 'Diagnosis wajib diisi.',
                'max_length' => 'Diagnosis maksimal 4.000 karakter.',
            ],
            'resolution' => [
                'required' => 'Tindakan / penyelesaian wajib diisi.',
                'max_length' => 'Tindakan / penyelesaian maksimal 4.000 karakter.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function workPayload(?array $ticket = null): array
    {
        $payload = [
            'diagnosis' => $this->nullablePost('diagnosis'),
            'resolution' => $this->nullablePost('resolution'),
            'follow_up_notes' => $this->nullablePost('follow_up_notes'),
            'labor_cost' => max(0, (int) $this->request->getPost('labor_cost')),
            'external_cost' => max(0, (int) $this->request->getPost('external_cost')),
        ];
        if ($ticket !== null && $this->checklistItems($ticket) !== []) {
            $completed = $this->completedChecklist($ticket, false);
            if (is_array($completed)) {
                $payload['checklist_completed'] = json_encode($completed, JSON_UNESCAPED_UNICODE);
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function detailData(int $id, ?string $feedbackType = null, ?string $feedbackMessage = null): ?array
    {
        $db = db_connect();
        $ticket = $db->table('maintenance_tickets')
            ->select('maintenance_tickets.*, rooms.room_number, rooms.status AS room_status, maintenance_categories.name AS category_name, room_assets.asset_name, maintenance_schedules.title AS schedule_title, reporter.full_name AS reported_by_name, assignee.full_name AS assigned_to_name, verifier.full_name AS verified_by_name')
            ->join('rooms', 'rooms.id = maintenance_tickets.room_id')
            ->join('maintenance_categories', 'maintenance_categories.id = maintenance_tickets.maintenance_category_id', 'left')
            ->join('room_assets', 'room_assets.id = maintenance_tickets.room_asset_id', 'left')
            ->join('maintenance_schedules', 'maintenance_schedules.id = maintenance_tickets.maintenance_schedule_id', 'left')
            ->join('users reporter', 'reporter.id = maintenance_tickets.reported_by_user_id', 'left')
            ->join('users assignee', 'assignee.id = maintenance_tickets.assigned_to_user_id', 'left')
            ->join('users verifier', 'verifier.id = maintenance_tickets.verified_by_user_id', 'left')
            ->where('maintenance_tickets.id', $id)
            ->get()->getRowArray();
        if ($ticket === null) {
            return null;
        }
        $materialCostRow = $db->table('maintenance_ticket_items')
            ->select('COALESCE(SUM((quantity_used - quantity_returned) * unit_cost_snapshot), 0) AS material_cost', false)
            ->where('maintenance_ticket_id', $id)
            ->where('movement_type', 'Usage')
            ->get()->getRowArray();

        return [
            'ticket' => $ticket,
            'users' => $db->table('users')->orderBy('full_name', 'ASC')->get()->getResultArray(),
            'categories' => $db->table('maintenance_categories')->where('is_active', 1)->orderBy('name', 'ASC')->get()->getResultArray(),
            'roomAssets' => $db->table('room_assets')
                ->where('room_id', (int) $ticket['room_id'])
                ->where('is_active', 1)
                ->orderBy('asset_name', 'ASC')
                ->get()->getResultArray(),
            'logs' => $db->table('maintenance_status_logs')
                ->select('maintenance_status_logs.*, users.full_name AS changed_by_name')
                ->join('users', 'users.id = maintenance_status_logs.changed_by_user_id', 'left')
                ->where('maintenance_ticket_id', $id)
                ->orderBy('maintenance_status_logs.created_at', 'ASC')
                ->orderBy('maintenance_status_logs.id', 'ASC')
                ->get()->getResultArray(),
            'ticketParts' => $db->table('maintenance_ticket_items')
                ->select('maintenance_ticket_items.*, inventory_items.name AS inventory_item_name, inventory_items.unit')
                ->join('inventory_items', 'inventory_items.id = maintenance_ticket_items.inventory_item_id')
                ->where('maintenance_ticket_id', $id)
                ->where('movement_type', 'Usage')
                ->orderBy('maintenance_ticket_items.id', 'ASC')
                ->get()->getResultArray(),
            'spareParts' => $db->table('inventory_items')
                ->where('item_type', 'Spare Part')
                ->where('is_active', 1)
                ->where('current_stock >', 0)
                ->orderBy('name', 'ASC')
                ->get()->getResultArray(),
            'attachments' => $db->table('maintenance_attachments')
                ->select('maintenance_attachments.*, users.full_name AS uploaded_by_name')
                ->join('users', 'users.id = maintenance_attachments.uploaded_by_user_id', 'left')
                ->where('maintenance_ticket_id', $id)
                ->orderBy('maintenance_attachments.created_at', 'DESC')
                ->orderBy('maintenance_attachments.id', 'DESC')
                ->get()->getResultArray(),
            'materialCost' => (float) ($materialCostRow['material_cost'] ?? 0),
            'feedbackType' => $feedbackType,
            'feedbackMessage' => $feedbackMessage,
        ];
    }

    private function detailResponse(int $id, string $type, string $message, bool $changed = false)
    {
        $data = $this->detailData($id, $type, $message);
        $body = $data === null
            ? '<div class="alert alert-danger m-3">Work Order tidak ditemukan.</div>'
            : view('components/maintenance/work_order_content', $data);

        $response = $this->response->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody($body);
        if ($changed) {
            $response->setHeader('HX-Trigger', json_encode(['opsChanged' => true, 'dashboardChanged' => true]));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findTicket(int $id): ?array
    {
        return db_connect()->table('maintenance_tickets')->where('id', $id)->get()->getRowArray();
    }

    private function userExists(int $id): bool
    {
        return db_connect()->table('users')->where('id', $id)->countAllResults() > 0;
    }

    private function assetMatches(int $assetId, int $roomId, int $categoryId): bool
    {
        return db_connect()->table('room_assets')
            ->where('id', $assetId)
            ->where('room_id', $roomId)
            ->where('maintenance_category_id', $categoryId)
            ->where('is_active', 1)
            ->countAllResults() > 0;
    }

    private function previousRoomStatus(int $roomId, string $currentStatus): string
    {
        $existing = db_connect()->table('maintenance_tickets')
            ->select('previous_room_status')
            ->where('room_id', $roomId)
            ->where('blocks_room', 1)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where('previous_room_status IS NOT NULL', null, false)
            ->orderBy('id', 'ASC')
            ->get()->getRowArray();

        if ($existing !== null && ! empty($existing['previous_room_status'])) {
            return (string) $existing['previous_room_status'];
        }

        return $currentStatus === 'Maintenance' ? 'Available' : $currentStatus;
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function releaseRoom(array $ticket, string $targetStatus): void
    {
        if ((int) ($ticket['blocks_room'] ?? 0) !== 1) {
            return;
        }

        $otherBlocking = db_connect()->table('maintenance_tickets')
            ->where('room_id', (int) $ticket['room_id'])
            ->where('blocks_room', 1)
            ->where('id !=', (int) $ticket['id'])
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->countAllResults();
        if ($otherBlocking > 0) {
            return;
        }

        db_connect()->table('rooms')->where('id', (int) $ticket['room_id'])->update([
            'status' => $targetStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Menjadikan biaya Work Order sebagai satu expense operasional.
     * Penanda pada notes mencegah satu Work Order tercatat dua kali.
     *
     * @param array<string, mixed> $ticket
     */
    private function syncMaintenanceExpense(array $ticket, int $ticketId, string $incurredAt): void
    {
        $db = db_connect();
        $materialRow = $db->table('maintenance_ticket_items')
            ->select('COALESCE(SUM((quantity_used - quantity_returned) * unit_cost_snapshot), 0) AS material_cost', false)
            ->where('maintenance_ticket_id', $ticketId)
            ->get()->getRowArray();
        $sparePartCost = (float) ($materialRow['material_cost'] ?? 0);
        $manualMaterialCost = (float) ($ticket['external_cost'] ?? 0);
        $laborCost = (float) ($ticket['labor_cost'] ?? 0);
        $total = max(0, $sparePartCost + $manualMaterialCost + $laborCost);
        if ($total <= 0) {
            return;
        }

        $room = $db->table('rooms')->select('room_number, floor')->where('id', (int) ($ticket['room_id'] ?? 0))->get()->getRowArray();
        $ticketNumber = (string) ($ticket['ticket_number'] ?? ('WO-' . str_pad((string) $ticketId, 5, '0', STR_PAD_LEFT)));
        $marker = '[Maintenance Work Order: ' . $ticketNumber . ']';
        $description = 'Maintenance ' . $ticketNumber . ' · Room ' . (string) ($room['room_number'] ?? '-');
        $notes = $marker . ' Material spare part: Rp' . number_format($sparePartCost, 0, ',', '.')
            . '; material: Rp' . number_format($manualMaterialCost, 0, ',', '.')
            . '; tenaga: Rp' . number_format($laborCost, 0, ',', '.') . '.';
        $existing = $db->table('expenses')->select('id')->like('notes', $marker)->get()->getRowArray();
        $payload = [
            'logged_by_user_id' => (int) session('userId'),
            'description' => $description,
            'category' => 'Maintenance',
            'amount' => $total,
            'date_incurred' => substr($incurredAt, 0, 10),
            'notes' => $notes,
            'status' => 'Approved',
            'guesthouse' => max(0, (int) ($room['floor'] ?? 0)),
            'updated_at' => $incurredAt,
        ];
        $invoice = $db->table('maintenance_attachments')
            ->select('file_path')
            ->where('maintenance_ticket_id', $ticketId)
            ->where('attachment_type', 'Invoice')
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->get()->getRowArray();
        if (! empty($invoice['file_path'])) {
            $payload['receipt_url'] = (string) $invoice['file_path'];
        }
        if ($existing !== null) {
            $db->table('expenses')->where('id', (int) $existing['id'])->update($payload);
            return;
        }
        $payload['created_at'] = $incurredAt;
        $db->table('expenses')->insert($payload);
    }

    /** @param array<string, mixed> $ticket */
    private function syncAutomaticSchedule(array $ticket, string $completedAt): ?string
    {
        $schedules = new MaintenanceScheduleService();
        if (! empty($ticket['maintenance_schedule_id'])) {
            return $schedules->completeSchedule((int) $ticket['maintenance_schedule_id'], $completedAt);
        }
        if (($ticket['ticket_type'] ?? 'Corrective') !== 'Corrective') {
            return null;
        }

        $result = $schedules->syncFromCorrectiveTicket($ticket, $completedAt);
        return $result['next_due_at'];
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function blockRoomForWork(array $ticket, string $now): void
    {
        if ((int) ($ticket['blocks_room'] ?? 0) !== 1) {
            return;
        }

        $db = db_connect();
        $room = $db->table('rooms')->where('id', (int) $ticket['room_id'])->get()->getRowArray();
        if ($room === null || $room['status'] === 'Maintenance') {
            return;
        }
        $db->table('maintenance_tickets')->where('id', (int) $ticket['id'])->update([
            'previous_room_status' => $room['status'],
            'updated_at' => $now,
        ]);
        $db->table('rooms')->where('id', (int) $ticket['room_id'])->update([
            'status' => 'Maintenance',
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $ticket
     * @return list<string>
     */
    private function checklistItems(array $ticket): array
    {
        return (new MaintenanceScheduleService())->checklistItems($ticket['checklist_snapshot'] ?? null);
    }

    /**
     * @param array<string, mixed> $ticket
     * @return list<array{item:string, completed_at:string}>|false|null
     */
    private function completedChecklist(array $ticket, bool $requireAll = true): array|false|null
    {
        $items = $this->checklistItems($ticket);
        if ($items === []) {
            return null;
        }
        $selected = array_map('intval', (array) $this->request->getPost('checklist_completed'));
        $selected = array_values(array_unique(array_filter($selected, static fn (int $index): bool => $index >= 0 && $index < count($items))));
        if ($requireAll && count($selected) !== count($items)) {
            return false;
        }

        $existing = json_decode((string) ($ticket['checklist_completed'] ?? ''), true);
        $existingByItem = [];
        if (is_array($existing)) {
            foreach ($existing as $row) {
                if (is_array($row) && isset($row['item'], $row['completed_at'])) {
                    $existingByItem[(string) $row['item']] = (string) $row['completed_at'];
                }
            }
        }

        $now = date('Y-m-d H:i:s');
        $result = [];
        foreach ($selected as $index) {
            $item = $items[$index];
            $result[] = ['item' => $item, 'completed_at' => $existingByItem[$item] ?? $now];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function restoredRoomStatus(array $ticket): string
    {
        $status = (string) ($ticket['previous_room_status'] ?? 'Available');
        return $status === '' || $status === 'Maintenance' ? 'Available' : $status;
    }

    private function logStatus(int $ticketId, ?string $from, string $to, ?string $notes): void
    {
        db_connect()->table('maintenance_status_logs')->insert([
            'maintenance_ticket_id' => $ticketId,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by_user_id' => $this->currentUserId(),
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function currentUserId(): ?int
    {
        $id = (int) session('userId');
        return $id > 0 ? $id : null;
    }

    private function isManager(): bool
    {
        return in_array((string) session('role'), self::MANAGER_ROLES, true);
    }

    private function canCancel(): bool
    {
        return in_array((string) session('role'), self::CANCELLER_ROLES, true);
    }

    private function canProcessCompletedWork(): bool
    {
        return in_array((string) session('role'), self::WORKFLOW_ROLES, true);
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

    private function defaultTransitionNote(string $status): string
    {
        return match ($status) {
            'In_Progress' => 'Pekerjaan dimulai atau dilanjutkan.',
            'Waiting_Part' => 'Pekerjaan menunggu spare part.',
            'Verified' => 'Hasil pekerjaan telah diverifikasi.',
            'Closed' => 'Work Order ditutup.',
            'Cancelled' => 'Work Order dibatalkan.',
            default => 'Status diperbarui.',
        };
    }

    private function statusLabel(string $status): string
    {
        return str_replace('_', ' ', $status);
    }

    private function attachmentTypeLabel(string $type): string
    {
        return match ($type) {
            'Before' => 'foto sebelum',
            'After' => 'foto sesudah',
            'Invoice' => 'invoice',
            default => 'lainnya',
        };
    }

    private function feedback(string $type, string $message)
    {
        return $this->response
            ->setHeader('HX-Trigger', json_encode(['opsChanged' => true, 'dashboardChanged' => true]))
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setBody('<div class="alert alert-' . $type . ' mb-0">' . esc($message) . '</div>');
    }
}
