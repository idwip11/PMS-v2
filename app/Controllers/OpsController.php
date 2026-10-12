<?php

namespace App\Controllers;

use App\Libraries\ExtraBedService;
use App\Libraries\ImageUploadService;
use App\Libraries\MaintenanceReportService;
use App\Libraries\RoleAccess;
use App\Libraries\PropertyPricing;
use App\Libraries\PropertyContext;

class OpsController extends BaseController
{
    private const TABS = ['overview', 'expenses', 'inventory', 'extra_beds', 'maintenance', 'shifts'];
    private const INVENTORY_SECTIONS = ['amenities', 'spare_parts', 'supplies', 'history'];

    public static function isKosProperty(?int $propertyId = null): bool
    {
        $propertyId ??= PropertyContext::activeId();
        if ($propertyId === 3 || (string) session('activePropertyCode') === 'KOS_MITRA') {
            return true;
        }
        $mode = PropertyPricing::forProperty($propertyId)['pricing_mode'] ?? '';
        return $mode === 'room_monthly';
    }

    public static function expenseCategories(?int $propertyId = null): array
    {
        $isKos = self::isKosProperty($propertyId);
        $operationalLabel = $isKos ? 'Kebutuhan Operasional Kos' : 'Kebutuhan Operasional Guesthouse';

        return [
            $operationalLabel,
            'Listrik',
            'WiFi/Internet/Pulsa',
            'Marketing',
            'Gaji Pegawai',
            'Maintenance',
            'Laundry',
            'Sewa Lobby/Resepsionis',
            'Fee Kerjasama',
            'Lain-lain',
        ];
    }

    public static function allValidCategories(): array
    {
        return [
            'Kebutuhan Operasional Guesthouse',
            'Kebutuhan Operasional Kos',
            'Listrik',
            'WiFi/Internet/Pulsa',
            'Marketing',
            'Gaji Pegawai',
            'Maintenance',
            'Laundry',
            'Sewa Lobby/Resepsionis',
            'Fee Kerjasama',
            'Lain-lain',
        ];
    }

    public function index(): string
    {
        $tab = $this->tab();
        $panelParams = ['tab' => $tab];
        if ($tab === 'expenses') {
            foreach (['category', 'guesthouse', 'month', 'page'] as $key) {
                $value = $this->request->getGet($key);
                if ($value !== null && $value !== '') $panelParams[$key] = $value;
            }
        } elseif ($tab === 'maintenance') {
            $panelParams['section'] = $this->maintenanceSection();
        } elseif ($tab === 'inventory') {
            $panelParams['inventory_section'] = $this->inventorySection();
            if ($this->inventorySection() === 'history') $panelParams['page'] = $this->page();
        }

        return view('pages/ops', [
            'tab' => $tab,
            'inventorySection' => $this->inventorySection(),
            'maintenanceSection' => $this->maintenanceSection(),
            'page' => $this->page(),
            'panelUrl' => site_url('ops/panel?' . http_build_query($panelParams)),
            'pageTitle' => 'Operasional',
            'pageDescription' => 'Kelola pengeluaran, stok amenities, Extra Bed, maintenance, dan jadwal shift.',
        ]);
    }

    public function panel(): string
    {
        $db = db_connect();
        $this->syncClosedMaintenanceExpenses($db);
        $tab = $this->tab();
        $data = [
            'tab' => $tab,
            'maintenanceSection' => $this->maintenanceSection(),
            'inventorySection' => $this->inventorySection(),
            'rooms' => [],
            'users' => [],
            'categories' => [],
            'roomAssets' => [],
            'historyFilters' => [],
            'maintenanceSummary' => [],
            'scheduleStats' => ['overdue' => 0, 'due_today' => 0, 'due_soon' => 0, 'active' => 0],
            'extraBedDate' => date('Y-m-d'),
            'extraBedAvailability' => ['total_stock' => 7, 'rented' => 0, 'available' => 7],
            'extraBedSettings' => ['total_stock' => 7],
            'extraBedAllocations' => [],
            'extraBedUpcoming' => [],
            'overview' => null,
            'pagination' => null,
        ];
        if ($tab === 'overview') {
            $data['overview'] = $this->overviewData($db);
            $data['items'] = [];
        } elseif ($tab === 'expenses') {
            $data['expenseHistoryFilters'] = $this->expenseHistoryFilters();
            $perPage = 10;
            $totalBuilder = $db->table('expenses')->where('expenses.property_id', PropertyContext::activeId());
            $this->applyExpenseHistoryFilters($totalBuilder, $data['expenseHistoryFilters']);
            $totalItems = $totalBuilder->countAllResults();
            $totalPages = max(1, (int) ceil($totalItems / $perPage));
            $page = min($this->page(), $totalPages);
            $itemsBuilder = $db->table('expenses')->select('expenses.*, users.full_name AS logged_by')->join('users', 'users.id = expenses.logged_by_user_id', 'left')->where('expenses.property_id', PropertyContext::activeId());
            $this->applyExpenseHistoryFilters($itemsBuilder, $data['expenseHistoryFilters']);
            $data['items'] = $itemsBuilder->orderBy('date_incurred', 'DESC')->orderBy('expenses.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
            $data['items'] = $this->attachExpenseGuesthouses($db, $data['items']);
            $data['expenseCategories'] = self::expenseCategories();
            $data['pagination'] = ['page' => $page, 'perPage' => $perPage, 'totalItems' => $totalItems, 'totalPages' => $totalPages];
        } elseif ($tab === 'inventory') {
            if ($data['inventorySection'] === 'history') {
                $perPage = 15;
                $totalItems = $db->table('inventory_stock_movements')->join('inventory_items', 'inventory_items.id = inventory_stock_movements.inventory_item_id')->where('inventory_items.property_id', PropertyContext::activeId())->countAllResults();
                $totalPages = max(1, (int) ceil($totalItems / $perPage));
                $page = min($this->page(), $totalPages);
                $data['items'] = $db->table('inventory_stock_movements')
                    ->select('inventory_stock_movements.*, inventory_items.name AS item_name, inventory_items.unit, maintenance_tickets.ticket_number, rooms.room_number, users.full_name AS created_by_name')
                    ->join('inventory_items', 'inventory_items.id = inventory_stock_movements.inventory_item_id')
                    ->where('inventory_items.property_id', PropertyContext::activeId())
                    ->join('maintenance_tickets', "maintenance_tickets.id = inventory_stock_movements.reference_id AND inventory_stock_movements.reference_type = 'MaintenanceWorkOrder'", 'left')
                    ->join('rooms', 'rooms.id = maintenance_tickets.room_id', 'left')
                    ->join('users', 'users.id = inventory_stock_movements.created_by_user_id', 'left')
                    ->orderBy('inventory_stock_movements.created_at', 'DESC')
                    ->orderBy('inventory_stock_movements.id', 'DESC')
                    ->limit($perPage, ($page - 1) * $perPage)
                    ->get()->getResultArray();
                $data['pagination'] = ['page' => $page, 'perPage' => $perPage, 'totalItems' => $totalItems, 'totalPages' => $totalPages];
            } else {
                $itemType = $this->inventoryItemType($data['inventorySection']);
                if ($data['inventorySection'] === 'amenities') {
                    $this->ensureDefaultAmenities($db);
                }
                $data['items'] = $db->table('inventory_items')->where('property_id', PropertyContext::activeId())->where('item_type', $itemType)->where('is_active', 1)->orderBy('name', 'ASC')->get()->getResultArray();
                $data['lowStockVendors'] = $data['inventorySection'] === 'amenities' ? $this->lowStockVendors($data['items']) : [];
            }
        } elseif ($tab === 'extra_beds') {
            $selectedDate = $this->validDate((string) $this->request->getGet('date')) ?? date('Y-m-d');
            $nextDate = (new \DateTimeImmutable($selectedDate))->modify('+1 day')->format('Y-m-d');
            $extraBeds = new ExtraBedService($db);
            $data['extraBedDate'] = $selectedDate;
            $data['extraBedSettings'] = $extraBeds->settings();
            $data['extraBedAvailability'] = $extraBeds->availability($selectedDate, $nextDate);
            $data['extraBedAllocations'] = $extraBeds->allocationsForDate($selectedDate);
            $data['extraBedUpcoming'] = $extraBeds->upcomingAllocations();
            $data['items'] = [];
        } elseif ($tab === 'maintenance') {
            $data['rooms'] = $db->table('rooms')->where('property_id', PropertyContext::activeId())->orderBy('room_number', 'ASC')->get()->getResultArray();
            $data['categories'] = $db->table('maintenance_categories')->where('is_active', 1)->orderBy('name', 'ASC')->get()->getResultArray();

            if ($data['maintenanceSection'] === 'summary') {
                $data['maintenanceSummary'] = (new MaintenanceReportService($db))->summary();
                $data['items'] = [];
            } elseif ($data['maintenanceSection'] === 'categories') {
                $data['items'] = $db->table('maintenance_categories')->orderBy('is_active', 'DESC')->orderBy('name', 'ASC')->get()->getResultArray();
            } elseif ($data['maintenanceSection'] === 'assets') {
                $data['categories'] = $db->table('maintenance_categories')->orderBy('is_active', 'DESC')->orderBy('name', 'ASC')->get()->getResultArray();
                $selectedRoomId = max(0, (int) $this->request->getGet('room_id'));
                $assets = $db->table('room_assets')
                    ->select('room_assets.*, rooms.room_number, maintenance_categories.name AS category_name')
                    ->join('rooms', 'rooms.id = room_assets.room_id')
                    ->where('rooms.property_id', PropertyContext::activeId())
                    ->join('maintenance_categories', 'maintenance_categories.id = room_assets.maintenance_category_id')
                    ->orderBy('rooms.room_number', 'ASC')
                    ->orderBy('room_assets.asset_name', 'ASC');
                if ($selectedRoomId > 0) {
                    $assets->where('room_assets.room_id', $selectedRoomId);
                }
                $data['selectedRoomId'] = $selectedRoomId;
                $data['items'] = $assets->get()->getResultArray();
            } elseif ($data['maintenanceSection'] === 'schedules') {
                $data['roomAssets'] = $db->table('room_assets')
                    ->select('room_assets.*, rooms.room_number, maintenance_categories.name AS category_name')
                    ->join('rooms', 'rooms.id = room_assets.room_id')
                    ->where('rooms.property_id', PropertyContext::activeId())
                    ->join('maintenance_categories', 'maintenance_categories.id = room_assets.maintenance_category_id')
                    ->where('room_assets.is_active', 1)
                    ->orderBy('rooms.room_number', 'ASC')
                    ->orderBy('room_assets.asset_name', 'ASC')
                    ->get()->getResultArray();
                $data['users'] = $this->propertyUsers($db);
                $data['items'] = $db->table('maintenance_schedules')
                    ->select('maintenance_schedules.*, room_assets.asset_name, rooms.room_number, maintenance_categories.name AS category_name, users.full_name AS assignee_name')
                    ->join('room_assets', 'room_assets.id = maintenance_schedules.room_asset_id')
                    ->join('rooms', 'rooms.id = room_assets.room_id')
                    ->where('rooms.property_id', PropertyContext::activeId())
                    ->join('maintenance_categories', 'maintenance_categories.id = room_assets.maintenance_category_id')
                    ->join('users', 'users.id = maintenance_schedules.default_assignee_user_id', 'left')
                    ->orderBy('maintenance_schedules.is_active', 'DESC')
                    ->orderBy('maintenance_schedules.next_due_at', 'ASC')
                    ->get()->getResultArray();
                $data['scheduleStats'] = $this->scheduleStats($data['items']);
            } elseif ($data['maintenanceSection'] === 'history') {
                $data['categories'] = $db->table('maintenance_categories')->orderBy('name', 'ASC')->get()->getResultArray();
                $service = new MaintenanceReportService($db);
                $filters = $service->filters($this->request->getGet());
                $data['historyFilters'] = $filters;
                $data['items'] = $service->history($filters);
            } else {
                $data['items'] = $db->table('maintenance_tickets')
                    ->select('maintenance_tickets.*, rooms.room_number, maintenance_categories.name AS category_name, room_assets.asset_name, assignee.full_name AS assigned_to_name')
                    ->join('rooms', 'rooms.id = maintenance_tickets.room_id')
                    ->where('rooms.property_id', PropertyContext::activeId())
                    ->join('maintenance_categories', 'maintenance_categories.id = maintenance_tickets.maintenance_category_id', 'left')
                    ->join('room_assets', 'room_assets.id = maintenance_tickets.room_asset_id', 'left')
                    ->join('users assignee', 'assignee.id = maintenance_tickets.assigned_to_user_id', 'left')
                    ->whereIn('maintenance_tickets.status', ['Open', 'Assigned', 'In_Progress', 'Waiting_Part', 'Completed'])
                    ->orderBy('maintenance_tickets.created_at', 'DESC')
                    ->get()->getResultArray();
                $data['users'] = $this->propertyUsers($db);
                $data['roomAssets'] = $db->table('room_assets')
                    ->select('room_assets.*, rooms.room_number, maintenance_categories.name AS category_name')
                    ->join('rooms', 'rooms.id = room_assets.room_id')
                    ->where('rooms.property_id', PropertyContext::activeId())
                    ->join('maintenance_categories', 'maintenance_categories.id = room_assets.maintenance_category_id')
                    ->where('room_assets.is_active', 1)
                    ->orderBy('rooms.room_number', 'ASC')
                    ->orderBy('room_assets.asset_name', 'ASC')
                    ->get()->getResultArray();
            }
        } else {
            $data['items'] = $db->table('shifts')->select('shifts.*, users.full_name')->join('users', 'users.id = shifts.user_id')->where('shifts.property_id', PropertyContext::activeId())->orderBy('shift_date', 'DESC')->get()->getResultArray();
            $data['users'] = $this->propertyUsers($db);
        }

        return view('components/ops_panel', $data);
    }

    public function saveOperationalBudget()
    {
        if (! $this->validate(['year' => 'required|integer|greater_than[2000]|less_than[2100]', 'month' => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[12]', 'projected_amount' => 'required|decimal|greater_than_equal_to[0]'])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        $db = db_connect();
        $year = (int) $this->request->getPost('year');
        $month = (int) $this->request->getPost('month');
        $amount = max(0, (float) $this->request->getPost('projected_amount'));
        $existing = $db->table('operational_budgets')->where(['property_id' => PropertyContext::activeId(), 'year' => $year, 'month' => $month])->get()->getRowArray();
        if ($existing !== null) {
            $db->table('operational_budgets')->where('id', $existing['id'])->update(['projected_amount' => $amount, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            $now = date('Y-m-d H:i:s');
            $db->table('operational_budgets')->insert(['property_id' => PropertyContext::activeId(), 'year' => $year, 'month' => $month, 'projected_amount' => $amount, 'created_at' => $now, 'updated_at' => $now]);
        }

        return $this->feedback('success', 'Budget operasional berhasil disimpan.');
    }

    public function updateRoomMaintenance(int $roomId)
    {
        if (! $this->validate([
            'description' => 'required|max_length[2000]',
            'priority' => 'required|in_list[Low,Medium,High,Urgent]',
            'notes' => 'permit_empty|max_length[4000]',
            'checklist' => 'permit_empty|max_length[4000]',
            'mark_done' => 'permit_empty|in_list[1]',
        ])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        $db = db_connect();
        $room = $db->table('rooms')->where('property_id', PropertyContext::activeId())->where('id', $roomId)->get()->getRowArray();
        if ($room === null) {
            return $this->feedback('danger', 'Kamar maintenance tidak ditemukan.');
        }

        $now = date('Y-m-d H:i:s');
        $checklistItems = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $this->request->getPost('checklist')) ?: [])));
        $checklistCompleted = $this->request->getPost('mark_done') === '1';
        $ticket = $db->table('maintenance_tickets')
            ->where('room_id', $roomId)
            ->where('blocks_room', 1)
            ->whereIn('status', ['Open', 'Assigned', 'In_Progress', 'Waiting_Part', 'Completed'])
            ->orderBy('id', 'DESC')->get()->getRowArray();

        $payload = [
            'description' => trim((string) $this->request->getPost('description')),
            'priority' => (string) $this->request->getPost('priority'),
            'follow_up_notes' => $this->nullablePost('notes'),
            'checklist_snapshot' => $checklistItems === [] ? null : json_encode($checklistItems, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ];

        $db->transStart();
        if ($ticket === null) {
            if ($checklistCompleted) {
                $payload['checklist_completed'] = json_encode(array_map(static fn (string $item): array => ['item' => $item, 'completed_at' => $now], $checklistItems), JSON_UNESCAPED_UNICODE);
            }
            $db->table('maintenance_tickets')->insert($payload + [
                'room_id' => $roomId,
                'reported_by_user_id' => (int) session('userId') ?: null,
                'ticket_type' => 'Corrective',
                'issue_type' => 'Room Maintenance',
                'status' => $checklistCompleted ? 'Closed' : 'In_Progress',
                'blocks_room' => 1,
                'previous_room_status' => $room['status'] === 'Maintenance' ? 'Available' : $room['status'],
                'completed_at' => $checklistCompleted ? $now : null,
                'created_at' => $now,
            ]);
            $ticketId = (int) $db->insertID();
        } else {
            $ticketId = (int) $ticket['id'];
            if ($checklistCompleted) {
                $payload['status'] = 'Closed';
                $payload['completed_at'] = $now;
                $payload['checklist_completed'] = json_encode(array_map(static fn (string $item): array => ['item' => $item, 'completed_at' => $now], $checklistItems), JSON_UNESCAPED_UNICODE);
            }
            $db->table('maintenance_tickets')->where('id', $ticketId)->update($payload);
        }

        if ($checklistCompleted) {
            $db->table('rooms')->where('id', $roomId)->update(['status' => 'Available', 'updated_at' => $now]);
        } else {
            $db->table('rooms')->where('id', $roomId)->update(['status' => 'Maintenance', 'updated_at' => $now]);
        }
        $db->transComplete();

        return $this->feedback('success', $checklistCompleted ? 'Maintenance selesai. Status kamar dikembalikan menjadi Available.' : 'Status maintenance kamar diperbarui.');
    }

    public function createExpense()
    {
        if (! $this->validate(['description' => 'required|max_length[255]', 'category' => 'required|in_list[' . implode(',', self::allValidCategories()) . ']', 'amount' => 'required', 'date_incurred' => 'required|valid_date[Y-m-d]'])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }
        $receipt = null;
        $file = $this->request->getFile('receipt');
        if ($file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            try {
                $receipt = (new ImageUploadService())->store($file, 'expenses/' . date('Y/m'));
            } catch (\RuntimeException $exception) {
                return $this->feedback('danger', $exception->getMessage());
            }
        }

        $guesthouses = $this->guesthousesPost();
        if ($guesthouses === false) {
            return $this->feedback('danger', self::isKosProperty() ? 'Pilihan Kamar tidak valid.' : 'Pilihan Guesthouse tidak valid.');
        }
        $category = trim((string) $this->request->getPost('category'));
        $isCompanyExpense = $guesthouses === [0];
        if ($category === 'Fee Kerjasama' && ! $isCompanyExpense) {
            return $this->feedback('danger', 'Kategori Fee Kerjasama wajib dialokasikan ke Perusahaan.');
        }
        $allocatedGuesthouses = $isCompanyExpense ? [] : $guesthouses;

        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('expenses')->insert([
            'property_id' => PropertyContext::activeId(),
            'logged_by_user_id' => (int) session('userId'),
            'description' => trim((string) $this->request->getPost('description')),
            'category' => $category,
            'amount' => max(0, $this->parseMoney($this->request->getPost('amount'))),
            'date_incurred' => $this->request->getPost('date_incurred'),
            'notes' => $this->nullablePost('notes'),
            'status' => 'Approved',
            'guesthouse' => $allocatedGuesthouses[0] ?? 0,
            'receipt_url' => $receipt['relative_path'] ?? null,
            'created_at' => $now,
        ]);
        $expenseId = (int) $db->insertID();
        foreach ($allocatedGuesthouses as $guesthouse) {
            $db->table('expense_guesthouses')->insert(['expense_id' => $expenseId, 'guesthouse' => $guesthouse]);
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->feedback('danger', 'Expense gagal disimpan. Silakan coba kembali.');
        }
        return $this->feedback('success', 'Expense berhasil dicatat.');
    }

    public function receipt(int $id)
    {
        try {
            $proofScope = \App\Libraries\FinanceProofScope::current('expense_receipt');
        } catch (\RuntimeException) {
            return $this->response->setStatusCode(404)->setBody('Bukti tidak ditemukan.');
        }
        $propertyIds = $proofScope['propertyIds'];

        $db = db_connect();
        $expenseQuery = $db->table('expenses')->select('receipt_url, description')->whereIn('property_id', $propertyIds)->where('id', $id);
        $ownerGuesthouse = $proofScope['ownerGuesthouse'];
        if ($ownerGuesthouse !== null) {
            $expenseQuery->where(
                "(EXISTS (SELECT 1 FROM expense_guesthouses eg WHERE eg.expense_id = expenses.id AND eg.guesthouse = {$ownerGuesthouse})"
                . " OR (NOT EXISTS (SELECT 1 FROM expense_guesthouses eg2 WHERE eg2.expense_id = expenses.id) AND expenses.guesthouse = {$ownerGuesthouse}))",
                null,
                false,
            );
        }
        $expense = $expenseQuery->get()->getRowArray();
        if ($expense === null || empty($expense['receipt_url'])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bukti invoice tidak ditemukan.');
        }

        $uploadsRoot = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads');
        $absolutePath = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $expense['receipt_url']));
        if ($uploadsRoot === false || $absolutePath === false || ! str_starts_with($absolutePath, $uploadsRoot . DIRECTORY_SEPARATOR) || ! is_file($absolutePath)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bukti invoice tidak ditemukan.');
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $expense['description']) ?: 'bukti-expense';
        $extension = strtolower((string) pathinfo((string) $expense['receipt_url'], PATHINFO_EXTENSION));
        $mimeType = $extension === 'pdf' ? 'application/pdf' : 'image/jpeg';
        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Content-Length', (string) filesize($absolutePath))
            ->setHeader('Content-Disposition', 'inline; filename="' . $safeName . '.' . ($extension === 'pdf' ? 'pdf' : 'jpg') . '"')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0, must-revalidate')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($absolutePath));
    }

    public function editExpense(int $id): string
    {
        $db = db_connect();
        $expense = $db->table('expenses')->where('property_id', PropertyContext::activeId())->where('id', $id)->get()->getRowArray();
        if ($expense === null || $this->isMaintenanceGeneratedExpense($expense)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Expense ini tidak dapat diedit dari riwayat.');
        }
        $guesthouses = $db->table('expense_guesthouses')->select('guesthouse')->where('expense_id', $id)->orderBy('guesthouse', 'ASC')->get()->getResultArray();
        $auditLogs = $db->table('expense_audit_logs')->select('expense_audit_logs.*, users.full_name AS changed_by_name')->join('users', 'users.id = expense_audit_logs.changed_by_user_id', 'left')->where('expense_id', $id)->orderBy('expense_audit_logs.id', 'DESC')->limit(10)->get()->getResultArray();
        return view('components/modals/expense_edit', ['expense' => $expense, 'guesthouses' => array_map('intval', array_column($guesthouses, 'guesthouse')), 'auditLogs' => $auditLogs, 'expenseCategories' => self::expenseCategories((int) $expense['property_id'])]);
    }

    public function updateExpense(int $id)
    {
        if (! $this->validate(['description' => 'required|max_length[255]', 'category' => 'required|in_list[' . implode(',', self::allValidCategories()) . ']', 'amount' => 'required', 'date_incurred' => 'required|valid_date[Y-m-d]'])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }
        $guesthouses = $this->guesthousesPost();
        if ($guesthouses === false) return $this->feedback('danger', self::isKosProperty() ? 'Pilihan Kamar tidak valid.' : 'Pilihan Guesthouse tidak valid.');
        $isCompanyExpense = $guesthouses === [0];
        $category = trim((string) $this->request->getPost('category'));
        if ($category === 'Fee Kerjasama' && ! $isCompanyExpense) return $this->feedback('danger', 'Kategori Fee Kerjasama wajib dialokasikan ke Perusahaan.');
        $allocatedGuesthouses = $isCompanyExpense ? [] : $guesthouses;
        $db = db_connect();
        $expense = $db->table('expenses')->where('property_id', PropertyContext::activeId())->where('id', $id)->get()->getRowArray();
        if ($expense === null) return $this->feedback('danger', 'Expense tidak ditemukan.');
        if ($this->isMaintenanceGeneratedExpense($expense)) return $this->feedback('danger', 'Expense dari Maintenance hanya dapat diubah melalui work order Maintenance.');
        if (\App\Libraries\CashSourceGuard::expenseLinked($db, $id)) return $this->feedback('danger', 'Expense sudah terhubung pembayaran kas; sumber tidak boleh ditimpa.');
        $receiptPath = $expense['receipt_url'];
        $file = $this->request->getFile('receipt');
        if ($file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            try { $receiptPath = (new ImageUploadService())->store($file, 'expenses/' . date('Y/m'))['relative_path']; } catch (\RuntimeException $exception) { return $this->feedback('danger', $exception->getMessage()); }
        }
        $currentGuesthouses = $db->table('expense_guesthouses')->select('guesthouse')->where('expense_id', $id)->orderBy('guesthouse', 'ASC')->get()->getResultArray();
        $before = ['description' => $expense['description'], 'category' => $expense['category'], 'amount' => (float) $expense['amount'], 'date_incurred' => $expense['date_incurred'], 'notes' => $expense['notes'], 'guesthouses' => array_map('intval', array_column($currentGuesthouses, 'guesthouse')), 'receipt_url' => $expense['receipt_url']];
        $after = ['description' => trim((string) $this->request->getPost('description')), 'category' => $category, 'amount' => max(0, $this->parseMoney($this->request->getPost('amount'))), 'date_incurred' => (string) $this->request->getPost('date_incurred'), 'notes' => $this->nullablePost('notes'), 'guesthouses' => $allocatedGuesthouses, 'receipt_url' => $receiptPath];
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->query('SELECT id FROM expenses WHERE id=? FOR UPDATE', [$id]);
        if (\App\Libraries\CashSourceGuard::expenseLinked($db, $id)) {
            $db->transRollback();
            return $this->feedback('danger', 'Expense sudah terhubung pembayaran kas; sumber tidak boleh ditimpa.');
        }
        $db->table('expenses')->where('id', $id)->update(['description' => $after['description'], 'category' => $after['category'], 'amount' => $after['amount'], 'date_incurred' => $after['date_incurred'], 'notes' => $after['notes'], 'guesthouse' => $allocatedGuesthouses[0] ?? 0, 'receipt_url' => $receiptPath, 'updated_at' => $now]);
        $db->table('expense_guesthouses')->where('expense_id', $id)->delete();
        foreach ($allocatedGuesthouses as $guesthouse) $db->table('expense_guesthouses')->insert(['expense_id' => $id, 'guesthouse' => $guesthouse]);
        $db->table('expense_audit_logs')->insert(['expense_id' => $id, 'changed_by_user_id' => (int) session('userId') ?: null, 'action' => 'Updated', 'before_data' => json_encode($before, JSON_UNESCAPED_UNICODE), 'after_data' => json_encode($after, JSON_UNESCAPED_UNICODE), 'created_at' => $now]);
        $db->transComplete();
        return $db->transStatus() ? $this->feedback('success', 'Expense diperbarui dan perubahan tercatat di audit.') : $this->feedback('danger', 'Perubahan expense gagal disimpan.');
    }

    public function deleteExpense(int $id)
    {
        $db = db_connect();
        $expense = $db->table('expenses')->where('property_id', PropertyContext::activeId())->where('id', $id)->get()->getRowArray();
        if ($expense === null) {
            return $this->feedback('danger', 'Expense tidak ditemukan.');
        }
        if ($this->isMaintenanceGeneratedExpense($expense)) {
            return $this->feedback('danger', 'Expense dari Maintenance hanya dapat dihapus melalui work order Maintenance.');
        }

        $db->transStart();
        // Explicit deletes keep this safe even on installations without the
        // optional CASCADE foreign keys from the newer expense migrations.
        $db->query('SELECT id FROM expenses WHERE id=? FOR UPDATE', [$id]);
        if (\App\Libraries\CashSourceGuard::expenseLinked($db, $id)) {
            $db->transRollback();
            return $this->feedback('danger', 'Expense yang memiliki pembayaran kas tidak boleh dihapus.');
        }
        $db->table('expense_guesthouses')->where('expense_id', $id)->delete();
        $db->table('expense_audit_logs')->where('expense_id', $id)->delete();
        $db->table('expenses')->where('id', $id)->delete();
        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->feedback('danger', 'Expense gagal dihapus. Silakan coba kembali.');
        }

        $receiptPath = (string) ($expense['receipt_url'] ?? '');
        if ($receiptPath !== '') {
            $uploadsRoot = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads');
            $absolutePath = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $receiptPath));
            if ($uploadsRoot !== false && $absolutePath !== false && str_starts_with($absolutePath, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        return $this->feedback('success', 'Expense berhasil dihapus permanen.');
    }

    /** @param array<string, mixed> $expense */
    private function isMaintenanceGeneratedExpense(array $expense): bool
    {
        return str_contains((string) ($expense['notes'] ?? ''), '[Maintenance Work Order:');
    }

    public function createInventory()
    {
        if (! $this->validate(['name' => 'required|max_length[150]', 'category' => 'required|max_length[100]', 'current_stock' => 'required|integer', 'min_threshold' => 'required|integer', 'vendor_whatsapp' => 'permit_empty|max_length[20]', 'item_type' => 'required|in_list[Amenity,Spare Part,Operational Supply]', 'unit_cost' => 'permit_empty|decimal|greater_than_equal_to[0]', 'sku' => 'permit_empty|max_length[100]'])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }
        $vendorWhatsapp = $this->whatsappNumber($this->nullablePost('vendor_whatsapp'));
        if ($vendorWhatsapp === false) {
            return $this->feedback('danger', 'Nomor WhatsApp vendor tidak valid. Gunakan format 08xx atau 62xx.');
        }
        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $openingStock = max(0, (int) $this->request->getPost('current_stock'));
        $db->transStart();
        $db->table('inventory_items')->insert([
            'property_id' => PropertyContext::activeId(),
            'name' => trim((string) $this->request->getPost('name')),
            'category' => trim((string) $this->request->getPost('category')),
            'current_stock' => $openingStock,
            'min_threshold' => max(0, (int) $this->request->getPost('min_threshold')),
            'unit' => $this->nullablePost('unit') ?? 'pcs',
            'contact_vendor' => $this->nullablePost('contact_vendor'),
            'vendor_whatsapp' => $vendorWhatsapp,
            'item_type' => (string) $this->request->getPost('item_type'),
            'unit_cost' => max(0, (float) $this->request->getPost('unit_cost')),
            'sku' => $this->nullablePost('sku'),
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $itemId = (int) $db->insertID();
        if ($openingStock > 0) {
            $db->table('inventory_stock_movements')->insert([
                'inventory_item_id' => $itemId,
                'movement_type' => 'Opening',
                'quantity_change' => $openingStock,
                'balance_after' => $openingStock,
                'reference_type' => 'InventoryOpening',
                'reference_id' => $itemId,
                'notes' => 'Stok awal item.',
                'created_by_user_id' => (int) session('userId') ?: null,
                'created_at' => $now,
            ]);
        }
        $db->transComplete();
        return $this->feedback('success', 'Amenities berhasil ditambahkan.');
    }

    public function updateStock(int $id)
    {
        $stock = max(0, (int) $this->request->getPost('current_stock'));
        $db = db_connect();
        $item = $db->table('inventory_items')->where('property_id', PropertyContext::activeId())->where('id', $id)->get()->getRowArray();
        if ($item === null) {
            return $this->feedback('danger', 'Item stok tidak ditemukan.');
        }
        $delta = $stock - (int) $item['current_stock'];
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('inventory_items')->where('id', $id)->update(['current_stock' => $stock, 'updated_at' => $now]);
        if ($delta !== 0) {
            $db->table('inventory_stock_movements')->insert([
                'inventory_item_id' => $id,
                'movement_type' => 'Adjustment',
                'quantity_change' => $delta,
                'balance_after' => $stock,
                'reference_type' => 'InventoryAdjustment',
                'reference_id' => $id,
                'notes' => 'Penyesuaian stok manual.',
                'created_by_user_id' => (int) session('userId') ?: null,
                'created_at' => $now,
            ]);
        }
        $db->transComplete();
        return $this->feedback('success', 'Stok diperbarui.');
    }

    public function updateAmenity(int $id)
    {
        if (! $this->validate([
            'contact_vendor' => 'permit_empty|max_length[150]',
            'vendor_whatsapp' => 'permit_empty|max_length[20]',
            'current_stock' => 'required|integer',
            'min_threshold' => 'required|integer',
        ])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        $vendorWhatsapp = $this->whatsappNumber($this->nullablePost('vendor_whatsapp'));
        if ($vendorWhatsapp === false) {
            return $this->feedback('danger', 'Kontak WhatsApp vendor tidak valid. Gunakan format 08xx atau 62xx.');
        }

        $db = db_connect();
        $item = $db->table('inventory_items')->where('property_id', PropertyContext::activeId())->where('id', $id)->where('item_type', 'Amenity')->get()->getRowArray();
        if ($item === null) {
            return $this->feedback('danger', 'Item amenities tidak ditemukan.');
        }

        $stock = max(0, (int) $this->request->getPost('current_stock'));
        $threshold = max(0, (int) $this->request->getPost('min_threshold'));
        $delta = $stock - (int) $item['current_stock'];
        $now = date('Y-m-d H:i:s');

        $db->transStart();
        $db->table('inventory_items')->where('id', $id)->update([
            'contact_vendor' => $this->nullablePost('contact_vendor'),
            'vendor_whatsapp' => $vendorWhatsapp,
            'current_stock' => $stock,
            'min_threshold' => $threshold,
            'updated_at' => $now,
        ]);
        if ($delta !== 0) {
            $db->table('inventory_stock_movements')->insert([
                'inventory_item_id' => $id,
                'movement_type' => 'Adjustment',
                'quantity_change' => $delta,
                'balance_after' => $stock,
                'reference_type' => 'InventoryAdjustment',
                'reference_id' => $id,
                'notes' => 'Penyesuaian stok amenities.',
                'created_by_user_id' => (int) session('userId') ?: null,
                'created_at' => $now,
            ]);
        }
        $db->transComplete();

        return $this->feedback($db->transStatus() ? 'success' : 'danger', $db->transStatus() ? 'Data amenities diperbarui.' : 'Data amenities gagal diperbarui.');
    }

    public function updateVendorWhatsapp(int $id)
    {
        if (! $this->validate(['vendor_whatsapp' => 'permit_empty|max_length[20]'])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }
        $vendorWhatsapp = $this->whatsappNumber($this->nullablePost('vendor_whatsapp'));
        if ($vendorWhatsapp === false) {
            return $this->feedback('danger', 'Nomor WhatsApp vendor tidak valid. Gunakan format 08xx atau 62xx.');
        }
        db_connect()->table('inventory_items')->where('property_id', PropertyContext::activeId())->where('id', $id)->update([
            'vendor_whatsapp' => $vendorWhatsapp,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->feedback('success', $vendorWhatsapp === null ? 'Nomor WhatsApp vendor dihapus.' : 'Nomor WhatsApp vendor disimpan.');
    }

    public function createShift()
    {
        if (! $this->validate(['user_id' => 'required|is_natural_no_zero', 'shift_date' => 'required|valid_date[Y-m-d]', 'start_time' => 'required', 'end_time' => 'required', 'shift_type' => 'required'])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }
        if (db_connect()->table('user_property_roles')->where('user_id', (int) $this->request->getPost('user_id'))->where('property_id', PropertyContext::activeId())->countAllResults() === 0) {
            return $this->feedback('danger', 'Staf tidak ditugaskan pada properti aktif.');
        }
        db_connect()->table('shifts')->insert([
            'property_id' => PropertyContext::activeId(),
            'user_id' => (int) $this->request->getPost('user_id'),
            'shift_date' => $this->request->getPost('shift_date'),
            'start_time' => $this->request->getPost('start_time'),
            'end_time' => $this->request->getPost('end_time'),
            'shift_type' => $this->request->getPost('shift_type'),
            'status' => 'Scheduled',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->feedback('success', 'Shift berhasil dijadwalkan.');
    }

    public function updateExtraBedSettings()
    {
        $totalStock = (int) $this->request->getPost('total_stock');
        if ($totalStock < 0) {
            return $this->feedback('danger', 'Stok total tidak boleh negatif.');
        }

        $service = new ExtraBedService(db_connect());
        $peakUsage = $service->peakUsage();
        if ($totalStock < $peakUsage) {
            return $this->feedback('danger', "Stok total tidak dapat kurang dari alokasi aktif tertinggi ({$peakUsage} Extra Bed).");
        }

        $service->updateStock($totalStock);
        return $this->feedback('success', 'Stok Extra Bed berhasil diperbarui.');
    }

    private function tab(): string
    {
        $tab = (string) $this->request->getGet('tab');
        return in_array($tab, self::TABS, true) ? $tab : 'overview';
    }

    private function page(): int
    {
        return max(1, (int) $this->request->getGet('page'));
    }

    /** @return list<int>|false */
    private function guesthousesPost(): array|false
    {
        $values = $this->request->getPost('guesthouses');
        $isHomiq = PropertyContext::activeId() === PropertyContext::HOMIQ_ID;
        $isKos = self::isKosProperty();
        if (! $isHomiq && ! $isKos) {
            // Other properties (e.g. Homestay Mitra) has one owner-wide expense ledger per property
            return $values === null || $values === '' || $values === [] || $values === ['0'] ? [0] : false;
        }
        if ($values === null || $values === '') return [];
        if (! is_array($values)) return false;
        $guesthouses = array_values(array_unique(array_map('intval', $values)));
        if (in_array(0, $guesthouses, true)) {
            return count($guesthouses) === 1 ? [0] : false;
        }
        $max = $isKos ? 7 : 5;
        foreach ($guesthouses as $guesthouse) {
            if ($guesthouse < 1 || $guesthouse > $max) return false;
        }
        sort($guesthouses);
        return $guesthouses;
    }

    /** @param list<array<string, mixed>> $expenses */
    private function attachExpenseGuesthouses($db, array $expenses): array
    {
        if ($expenses === []) return $expenses;
        $ids = array_map(static fn (array $expense): int => (int) $expense['id'], $expenses);
        $rows = $db->table('expense_guesthouses')->whereIn('expense_id', $ids)->orderBy('guesthouse', 'ASC')->get()->getResultArray();
        $groups = [];
        foreach ($rows as $row) $groups[(int) $row['expense_id']][] = (int) $row['guesthouse'];
        $isKos = self::isKosProperty();
        $isHomiq = PropertyContext::activeId() === PropertyContext::HOMIQ_ID;

        foreach ($expenses as &$expense) {
            $guesthouses = $groups[(int) $expense['id']] ?? [];
            if ($guesthouses === [] && (int) ($expense['guesthouse'] ?? 0) > 0) $guesthouses[] = (int) $expense['guesthouse'];

            if ($isKos) {
                if ((string) ($expense['category'] ?? '') === 'Fee Kerjasama') {
                    $expense['guesthouse_labels'] = 'Perusahaan';
                } elseif ($guesthouses !== []) {
                    $expense['guesthouse_labels'] = implode(', ', array_map(static fn (int $kamar): string => 'Kamar ' . $kamar, $guesthouses));
                } else {
                    $expense['guesthouse_labels'] = 'Umum';
                }
            } elseif ($isHomiq) {
                $expense['guesthouse_labels'] = ((string) ($expense['category'] ?? '') === 'Fee Kerjasama')
                    ? 'Perusahaan'
                    : ($guesthouses === [] ? 'Umum' : implode(', ', array_map(static fn (int $guesthouse): string => 'GH ' . $guesthouse, $guesthouses)));
            } else {
                $expense['guesthouse_labels'] = (string) session('activePropertyName');
            }
        }
        unset($expense);
        return $expenses;
    }

    /**
     * Backfill expense records for closed maintenance tickets created before
     * maintenance costs were integrated with the expense history.
     */
    private function syncClosedMaintenanceExpenses($db): void
    {
        $tickets = $db->table('maintenance_tickets')
            ->select('maintenance_tickets.id, maintenance_tickets.ticket_number, maintenance_tickets.room_id, maintenance_tickets.labor_cost, maintenance_tickets.external_cost, maintenance_tickets.completed_at, rooms.room_number, rooms.floor')
            ->join('rooms', 'rooms.id = maintenance_tickets.room_id')
            ->where('rooms.property_id', PropertyContext::activeId())
            ->where('maintenance_tickets.status', 'Closed')
            ->get()->getResultArray();
        foreach ($tickets as $ticket) {
            $materialRow = $db->table('maintenance_ticket_items')
                ->select('COALESCE(SUM((quantity_used - quantity_returned) * unit_cost_snapshot), 0) AS material_cost', false)
                ->where('maintenance_ticket_id', (int) $ticket['id'])
                ->get()->getRowArray();
            $sparePartCost = (float) ($materialRow['material_cost'] ?? 0);
            $manualMaterialCost = (float) ($ticket['external_cost'] ?? 0);
            $laborCost = (float) ($ticket['labor_cost'] ?? 0);
            $total = max(0, $sparePartCost + $manualMaterialCost + $laborCost);
            if ($total <= 0) continue;

            $ticketNumber = (string) ($ticket['ticket_number'] ?? ('WO-' . str_pad((string) $ticket['id'], 5, '0', STR_PAD_LEFT)));
            $marker = '[Maintenance Work Order: ' . $ticketNumber . ']';
            $existing = $db->table('expenses')->select('id')->where('property_id', PropertyContext::activeId())->like('notes', $marker)->get()->getRowArray();
            $now = date('Y-m-d H:i:s');
            $payload = [
                'property_id' => PropertyContext::activeId(),
                'logged_by_user_id' => (int) session('userId'),
                'description' => 'Maintenance ' . $ticketNumber . ' · Room ' . (string) $ticket['room_number'],
                'category' => 'Maintenance',
                'amount' => $total,
                'date_incurred' => substr((string) ($ticket['completed_at'] ?? $now), 0, 10),
                'notes' => $marker . ' Material spare part: Rp' . number_format($sparePartCost, 0, ',', '.') . '; material: Rp' . number_format($manualMaterialCost, 0, ',', '.') . '; tenaga: Rp' . number_format($laborCost, 0, ',', '.') . '.',
                'status' => 'Approved',
                'guesthouse' => $guesthouseNum = ((int) ($ticket['floor'] ?? 0) > 0 ? (int) $ticket['floor'] : (preg_match('/\d+/', (string) ($ticket['room_number'] ?? ''), $m) ? (int) $m[0] : 0)),
                'updated_at' => $now,
            ];
            $invoice = $db->table('maintenance_attachments')
                ->select('file_path')
                ->where('maintenance_ticket_id', (int) $ticket['id'])
                ->where('attachment_type', 'Invoice')
                ->orderBy('created_at', 'DESC')
                ->orderBy('id', 'DESC')
                ->get()->getRowArray();
            if (! empty($invoice['file_path'])) {
                $payload['receipt_url'] = (string) $invoice['file_path'];
            }
            if ($existing !== null) {
                $mExpenseId = (int) $existing['id'];
                $db->transStart();
                $db->query('SELECT id FROM expenses WHERE id=? FOR UPDATE', [$mExpenseId]);
                if (\App\Libraries\CashSourceGuard::expenseLinked($db, $mExpenseId)) {
                    $db->transComplete();
                    continue;
                }
                $db->table('expenses')->where('id', $mExpenseId)->update($payload);
                $db->transComplete();
            } else {
                $payload['created_at'] = $now;
                $db->table('expenses')->insert($payload);
                $mExpenseId = (int) $db->insertID();
            }
            if ($guesthouseNum > 0 && $mExpenseId > 0) {
                $hasEg = $db->table('expense_guesthouses')->where('expense_id', $mExpenseId)->where('guesthouse', $guesthouseNum)->countAllResults() > 0;
                if (! $hasEg) {
                    $db->table('expense_guesthouses')->insert(['expense_id' => $mExpenseId, 'guesthouse' => $guesthouseNum]);
                }
            }
        }
    }

    private function overviewData($db): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone(config('App')->appTimezone));
        $requestedPeriod = (string) $this->request->getGet('month');
        $period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requestedPeriod) === 1
            ? new \DateTimeImmutable($requestedPeriod . '-01', new \DateTimeZone(config('App')->appTimezone))
            : $now->modify('first day of this month');
        $guesthouse = (int) $this->request->getGet('guesthouse');
        if (PropertyContext::activeId() !== PropertyContext::HOMIQ_ID || $guesthouse < 1 || $guesthouse > 5) $guesthouse = 0;
        $year = (int) $period->format('Y');
        $month = (int) $period->format('m');
        $firstDay = $period->modify('first day of this month')->format('Y-m-d');
        $lastDay = $period->modify('last day of this month')->format('Y-m-d');
        $actualBuilder = $db->table('expenses')->selectSum('amount')->where('property_id', PropertyContext::activeId())->where('status !=', 'Rejected')->where('date_incurred >=', $firstDay)->where('date_incurred <=', $lastDay);
        $this->applyExpenseGuesthouseFilter($actualBuilder, $guesthouse);
        $actualRow = $actualBuilder->get()->getRowArray();
        $actual = (float) ($actualRow['amount'] ?? 0);
        $expenseCategoryBuilder = $db->table('expenses')
            ->select('category, SUM(amount) AS total')
            ->where('property_id', PropertyContext::activeId())
            ->where('status !=', 'Rejected')->where('date_incurred >=', $firstDay)->where('date_incurred <=', $lastDay);
        $this->applyExpenseGuesthouseFilter($expenseCategoryBuilder, $guesthouse);
        $expenseCategoryRows = $expenseCategoryBuilder->groupBy('category')->orderBy('total', 'DESC')->get()->getResultArray();
        $isKos = self::isKosProperty();
        $expenseCategories = array_map(static function (array $row) use ($isKos): array {
            $name = trim((string) ($row['category'] ?? '')) ?: 'Tanpa kategori';
            if ($isKos && $name === 'Kebutuhan Operasional Guesthouse') {
                $name = 'Kebutuhan Operasional Kos';
            }
            return [
                'name' => $name,
                'amount' => (float) ($row['total'] ?? 0),
            ];
        }, $expenseCategoryRows);
        $budgetRow = $db->table('operational_budgets')->where(['property_id' => PropertyContext::activeId(), 'year' => $year, 'month' => $month])->get()->getRowArray();
        $budget = (float) ($budgetRow['projected_amount'] ?? 0);
        $lowStockItems = $db->table('inventory_items')->where('property_id', PropertyContext::activeId())->where('is_active', 1)->where('current_stock <= min_threshold', null, false)->orderBy('current_stock', 'ASC')->orderBy('name', 'ASC')->get()->getResultArray();
        $maintenanceRooms = $db->table('rooms')->where('property_id', PropertyContext::activeId())->where('status', 'Maintenance')->countAllResults();
        $urgentTickets = $db->table('maintenance_tickets')->join('rooms', 'rooms.id = maintenance_tickets.room_id')->where('rooms.property_id', PropertyContext::activeId())->whereIn('maintenance_tickets.status', ['Open', 'Assigned', 'In_Progress', 'Waiting_Part'])->whereIn('maintenance_tickets.priority', ['Urgent', 'Critical'])->countAllResults();
        $roomMaintenance = [];
        $roomsInMaintenance = $db->table('rooms')->where('property_id', PropertyContext::activeId())->where('status', 'Maintenance')->orderBy('room_number', 'ASC')->get()->getResultArray();
        foreach ($roomsInMaintenance as $room) {
            $ticket = $db->table('maintenance_tickets')
                ->where('room_id', $room['id'])->where('blocks_room', 1)
                ->whereIn('status', ['Open', 'Assigned', 'In_Progress', 'Waiting_Part', 'Completed'])
                ->orderBy('id', 'DESC')->get()->getRowArray();
            $room['maintenance_ticket'] = $ticket;
            $roomMaintenance[] = $room;
        }
        $labels = $budgets = $actuals = [];
        for ($offset = 5; $offset >= 0; $offset--) {
            $chartPeriod = $period->modify('-' . $offset . ' months');
            $periodYear = (int) $chartPeriod->format('Y');
            $periodMonth = (int) $chartPeriod->format('m');
            $periodStart = $chartPeriod->modify('first day of this month')->format('Y-m-d');
            $periodEnd = $chartPeriod->modify('last day of this month')->format('Y-m-d');
            $periodBudget = $db->table('operational_budgets')->where(['property_id' => PropertyContext::activeId(), 'year' => $periodYear, 'month' => $periodMonth])->get()->getRowArray();
            $periodActualBuilder = $db->table('expenses')->selectSum('amount')->where('property_id', PropertyContext::activeId())->where('status !=', 'Rejected')->where('date_incurred >=', $periodStart)->where('date_incurred <=', $periodEnd);
            $this->applyExpenseGuesthouseFilter($periodActualBuilder, $guesthouse);
            $periodActualRow = $periodActualBuilder->get()->getRowArray();
            $labels[] = $chartPeriod->format('M Y');
            $budgets[] = (float) ($periodBudget['projected_amount'] ?? 0);
            $actuals[] = (float) ($periodActualRow['amount'] ?? 0);
        }
        $recentExpenseBuilder = $db->table('expenses')->select('expenses.*, users.full_name AS logged_by')->join('users', 'users.id = expenses.logged_by_user_id', 'left')->where('expenses.property_id', PropertyContext::activeId())->where('expenses.status !=', 'Rejected')->where('expenses.date_incurred >=', $firstDay)->where('expenses.date_incurred <=', $lastDay);
        $this->applyExpenseGuesthouseFilter($recentExpenseBuilder, $guesthouse);
        $recentExpenses = $recentExpenseBuilder->orderBy('date_incurred', 'DESC')->orderBy('expenses.id', 'DESC')->limit(5)->get()->getResultArray();
        return [
            'year' => $year, 'month' => $month, 'actual' => $actual, 'budget' => $budget,
            'period' => $period->format('Y-m'), 'periodLabel' => $period->format('F Y'), 'guesthouse' => $guesthouse,
            'lowStockItems' => $lowStockItems, 'maintenanceRooms' => $maintenanceRooms, 'urgentTickets' => $urgentTickets,
            'roomMaintenance' => $roomMaintenance,
            'chart' => ['labels' => $labels, 'budgets' => $budgets, 'actuals' => $actuals],
            'expenseCategories' => $expenseCategories,
            'recentExpenses' => $this->attachExpenseGuesthouses($db, $recentExpenses),
        ];
    }

    private function applyExpenseGuesthouseFilter($builder, int $guesthouse): void
    {
        if ($guesthouse === 0) return;
        $builder->where(
            "(EXISTS (SELECT 1 FROM expense_guesthouses eg WHERE eg.expense_id = expenses.id AND eg.guesthouse = {$guesthouse})"
            . " OR (NOT EXISTS (SELECT 1 FROM expense_guesthouses eg2 WHERE eg2.expense_id = expenses.id) AND expenses.guesthouse = {$guesthouse}))",
            null,
            false
        );
    }

    /** @return array{category: string, guesthouse: string, month: string} */
    private function expenseHistoryFilters(): array
    {
        $category = trim((string) $this->request->getGet('category'));
        if (! in_array($category, self::allValidCategories(), true)) $category = '';

        $guesthouse = (string) $this->request->getGet('guesthouse');
        $isHomiq = PropertyContext::activeId() === PropertyContext::HOMIQ_ID;
        $isKos = self::isKosProperty();
        if (! $isHomiq && ! $isKos) $guesthouse = 'all';

        $validOptions = $isKos
            ? ['1', '2', '3', '4', '5', '6', '7']
            : ['1', '2', '3', '4', '5'];

        if ($guesthouse !== 'company' && ! in_array($guesthouse, $validOptions, true)) {
            $guesthouse = 'all';
        }

        $month = (string) $this->request->getGet('month');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) $month = '';

        return compact('category', 'guesthouse', 'month');
    }

    /** @param array{category: string, guesthouse: string, month: string} $filters */
    private function applyExpenseHistoryFilters($builder, array $filters): void
    {
        if ($filters['category'] !== '') {
            if ($filters['category'] === 'Kebutuhan Operasional Kos' || $filters['category'] === 'Kebutuhan Operasional Guesthouse') {
                if (self::isKosProperty()) {
                    $builder->whereIn('expenses.category', ['Kebutuhan Operasional Kos', 'Kebutuhan Operasional Guesthouse']);
                } else {
                    $builder->where('expenses.category', $filters['category']);
                }
            } else {
                $builder->where('expenses.category', $filters['category']);
            }
        }

        if ($filters['month'] !== '') {
            $period = new \DateTimeImmutable($filters['month'] . '-01');
            $builder->where('expenses.date_incurred >=', $period->modify('first day of this month')->format('Y-m-d'))
                ->where('expenses.date_incurred <=', $period->modify('last day of this month')->format('Y-m-d'));
        }

        if ($filters['guesthouse'] === 'company') {
            $builder->where(
                '(NOT EXISTS (SELECT 1 FROM expense_guesthouses eg WHERE eg.expense_id = expenses.id) AND expenses.guesthouse = 0)',
                null,
                false
            );
        } elseif ($filters['guesthouse'] !== 'all') {
            $this->applyExpenseGuesthouseFilter($builder, (int) $filters['guesthouse']);
        }
    }

    private function maintenanceSection(): string
    {
        $section = (string) $this->request->getGet('section');
        return in_array($section, ['summary', 'tickets', 'schedules', 'assets', 'categories', 'history'], true) ? $section : 'summary';
    }

    private function inventorySection(): string
    {
        $section = (string) $this->request->getGet('inventory_section');
        return in_array($section, self::INVENTORY_SECTIONS, true) ? $section : 'amenities';
    }

    private function inventoryItemType(string $section): string
    {
        return match ($section) {
            'spare_parts' => 'Spare Part',
            'supplies' => 'Operational Supply',
            default => 'Amenity',
        };
    }

    private function ensureDefaultAmenities($db): void
    {
        $defaultAmenities = ['Sikat Gigi', 'Sabun', 'Shampoo'];
        $existing = $db->table('inventory_items')
            ->select('name')
            ->where('property_id', PropertyContext::activeId())
            ->where('item_type', 'Amenity')
            ->get()
            ->getResultArray();
        $existingNames = array_flip(array_map(static fn (array $item): string => strtolower(trim((string) $item['name'])), $existing));
        $now = date('Y-m-d H:i:s');
        foreach ($defaultAmenities as $name) {
            if (isset($existingNames[strtolower($name)])) {
                continue;
            }
            $db->table('inventory_items')->insert([
                'property_id' => PropertyContext::activeId(),
                'name' => $name,
                'category' => 'Amenities',
                'current_stock' => 0,
                'min_threshold' => 10,
                'unit' => 'pcs',
                'item_type' => 'Amenity',
                'unit_cost' => 0,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>> $schedules
     * @return array{overdue:int, due_today:int, due_soon:int, active:int}
     */
    private function scheduleStats(array $schedules): array
    {
        $stats = ['overdue' => 0, 'due_today' => 0, 'due_soon' => 0, 'active' => 0];
        $now = new \DateTimeImmutable('now', new \DateTimeZone(config('App')->appTimezone));
        $today = $now->format('Y-m-d');
        foreach ($schedules as $schedule) {
            if ((int) $schedule['is_active'] !== 1) {
                continue;
            }
            ++$stats['active'];
            $due = new \DateTimeImmutable((string) $schedule['next_due_at'], new \DateTimeZone(config('App')->appTimezone));
            if ($due->format('Y-m-d') < $today) {
                ++$stats['overdue'];
            } elseif ($due->format('Y-m-d') === $today) {
                ++$stats['due_today'];
            } elseif ($due <= $now->modify('+' . max(0, (int) $schedule['reminder_days']) . ' days')) {
                ++$stats['due_soon'];
            }
        }

        return $stats;
    }

    private function nullablePost(string $key): ?string
    {
        $value = trim((string) $this->request->getPost($key));
        return $value === '' ? null : $value;
    }

    private function whatsappNumber(?string $value): string|false|null
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        return preg_match('/^62\d{8,13}$/', $digits) === 1 ? $digits : false;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array{vendor_name:string, whatsapp:?string, items:list<array<string, mixed>>}>
     */
    private function lowStockVendors(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            if ((int) $item['current_stock'] > (int) $item['min_threshold']) {
                continue;
            }
            $vendorName = trim((string) ($item['contact_vendor'] ?? '')) ?: 'Vendor belum diisi';
            $whatsapp = trim((string) ($item['vendor_whatsapp'] ?? '')) ?: null;
            $key = $whatsapp ?? ('missing-' . $item['id']);
            if (! isset($groups[$key])) {
                $groups[$key] = ['vendor_name' => $vendorName, 'whatsapp' => $whatsapp, 'items' => []];
            }
            $groups[$key]['items'][] = $item;
        }

        return array_values($groups);
    }

    private function feedback(string $type, string $message)
    {
        return $this->response
            ->setHeader('HX-Trigger', json_encode(['opsChanged' => true, 'dashboardChanged' => true, 'housekeepingChanged' => true]))
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setBody('<div class="alert alert-' . $type . ' mb-0">' . esc($message) . '</div>');
    }

    private function validDate(string $date): ?string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date ? $date : null;
    }

    private function propertyUsers($db): array
    {
        return $db->table('users')->select('users.*, upr.role AS property_role')
            ->join('user_property_roles upr', 'upr.user_id = users.id')
            ->where('upr.property_id', PropertyContext::activeId())
            ->orderBy('users.full_name', 'ASC')->get()->getResultArray();
    }
}
