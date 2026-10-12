<?php

namespace App\Controllers;

use App\Libraries\GuestDirectory;
use App\Libraries\ExtraBedService;
use App\Libraries\ImageUploadService;
use App\Libraries\RoleAccess;
use App\Libraries\PropertyContext;
use App\Libraries\PropertyPricing;
use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

class ReservationController extends BaseController
{
    public function index(): string
    {
        $this->disableCaching();
        $db = db_connect();
        $perPage = 10;
        $page = max(1, (int) $this->request->getGet('page'));
        $search = trim((string) $this->request->getGet('q'));
        $guesthouseInput = $this->request->getGet('guesthouse');
        $guesthouse = filter_var($guesthouseInput, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5],
        ]) ?: 0;
        $status = trim((string) $this->request->getGet('status'));
        $source = trim((string) $this->request->getGet('source'));
        $checkInFrom = $this->validDateFilter((string) $this->request->getGet('check_in_from'));
        $checkInTo = $this->validDateFilter((string) $this->request->getGet('check_in_to'));
        $allowedStatuses = ['Confirmed', 'Checked_In', 'Checked_Out', 'Cancelled', 'No_Show'];
        $allowedSources = ['Direct', 'Online', 'Booking.com', 'Agoda', 'Traveloka', 'Airbnb', 'Tiket.com'];

        $pricingMode = PropertyPricing::active()['pricing_mode'];
        $isWholeHome = $pricingMode === 'whole_home_nightly'
            || PropertyContext::activeId() === 2
            || (string) session('activePropertyCode') === 'HOMESTAY_MITRA';

        $paymentTotals = $db->table('payments')
            ->select('reservation_id, SUM(amount) AS paid_amount')
            ->where('status', 'Paid')
            ->groupBy('reservation_id')
            ->getCompiledSelect();

        if ($isWholeHome) {
            $builder = $db->table('reservations')
                ->select("
                    MIN(reservations.id) AS id,
                    COALESCE(NULLIF(reservations.booking_group_id, ''), reservations.order_id) AS order_id,
                    MIN(reservations.booking_group_id) AS booking_group_id,
                    MIN(reservations.guest_id) AS guest_id,
                    MIN(reservations.property_id) AS property_id,
                    MIN(reservations.check_in_date) AS check_in_date,
                    MAX(reservations.check_out_date) AS check_out_date,
                    MAX(reservations.member_id) AS member_id,
                    MAX(reservations.status) AS status,
                    MAX(reservations.source) AS source,
                    MAX(reservations.agent_name) AS agent_name,
                    MAX(reservations.guest_count) AS guest_count,
                    MAX(reservations.special_request) AS special_request,
                    MAX(reservations.important_note) AS important_note,
                    MAX(reservations.is_reconciled) AS is_reconciled,
                    MAX(reservations.has_breakfast) AS has_breakfast,
                    MAX(reservations.has_extrabed) AS has_extrabed,
                    MAX(reservations.has_late_checkout) AS has_late_checkout,
                    MAX(reservations.has_laundry) AS has_laundry,
                    SUM(reservations.breakfast_cost) AS breakfast_cost,
                    SUM(reservations.laundry_cost) AS laundry_cost,
                    SUM(reservations.massage_cost) AS massage_cost,
                    SUM(reservations.total_amount) AS total_amount,
                    MIN(reservations.created_at) AS created_at,
                    MAX(reservations.updated_at) AS updated_at,
                    MAX(reservations.cancelled_at) AS cancelled_at,
                    MAX(guests.full_name) AS guest_name,
                    MAX(guests.phone) AS phone,
                    '1 Rumah' AS room_number,
                    'Homestay' AS room_type,
                    MAX(rooms.floor) AS floor,
                    SUM(COALESCE(paid.paid_amount, 0)) AS paid_amount
                ", false)
                ->join('guests', 'guests.id = reservations.guest_id')
                ->join('rooms', 'rooms.id = reservations.room_id')
                ->join("({$paymentTotals}) paid", 'paid.reservation_id = reservations.id', 'left', false)
                ->where('reservations.property_id', PropertyContext::activeId())
                ->groupBy("COALESCE(NULLIF(reservations.booking_group_id, ''), reservations.order_id)");
        } else {
            $builder = $db->table('reservations')
                ->select('reservations.*, guests.full_name AS guest_name, guests.phone, rooms.room_number, rooms.room_type, rooms.floor, COALESCE(paid.paid_amount, 0) AS paid_amount', false)
                ->join('guests', 'guests.id = reservations.guest_id')
                ->join('rooms', 'rooms.id = reservations.room_id')
                ->join("({$paymentTotals}) paid", 'paid.reservation_id = reservations.id', 'left', false)
                ->where('reservations.property_id', PropertyContext::activeId());
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('reservations.order_id', $search)
                ->orLike('reservations.booking_group_id', $search)
                ->orLike('guests.full_name', $search)
                ->orLike('guests.phone', $search)
                ->orLike('rooms.room_number', $search)
                ->groupEnd();
        }
        if ($guesthouse > 0 && PropertyContext::activeId() === PropertyContext::HOMIQ_ID) {
            $builder->groupStart()
                ->where('rooms.guesthouse_number', $guesthouse)
                ->orWhere('rooms.floor', $guesthouse)
                ->groupEnd();
        }
        if (in_array($status, $allowedStatuses, true)) {
            $builder->where('reservations.status', $status);
        } else {
            $status = '';
        }
        if (in_array($source, $allowedSources, true)) {
            if ($source === 'Direct') {
                $builder->groupStart()
                    ->where('reservations.source', 'Direct')
                    ->orWhere('reservations.source', '')
                    ->orWhere('reservations.source IS NULL', null, false)
                    ->groupEnd();
            } else {
                $builder->where('reservations.source', $source);
            }
        } else {
            $source = '';
        }
        if ($checkInFrom !== null) {
            $builder->where('reservations.check_in_date >=', $checkInFrom);
        }
        if ($checkInTo !== null) {
            $builder->where('reservations.check_in_date <=', $checkInTo);
        }

        $totalReservations = (clone $builder)->countAllResults();
        $totalPages = max(1, (int) ceil($totalReservations / $perPage));
        $page = min($page, $totalPages);
        $orderField = $isWholeHome ? 'MIN(reservations.check_in_date)' : 'reservations.check_in_date';
        $orderIdField = $isWholeHome ? 'MIN(reservations.id)' : 'reservations.id';

        $reservations = $builder
            ->orderBy($orderField, 'DESC')
            ->orderBy($orderIdField, 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return view('pages/reservations', [
            'pageTitle' => 'Reservasi List',
            'pageDescription' => 'Cari dan pantau seluruh reservasi dalam satu daftar.',
            'reservations' => $reservations,
            'isHomiq' => PropertyContext::activeId() === PropertyContext::HOMIQ_ID,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $totalReservations,
                'totalPages' => $totalPages,
            ],
            'filters' => [
                'q' => $search,
                'guesthouse' => $guesthouse,
                'status' => $status,
                'source' => $source,
                'check_in_from' => $checkInFrom ?? '',
                'check_in_to' => $checkInTo ?? '',
            ],
        ]);
    }

    private function disableCaching(): void
    {
        $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0');
    }

    public function export()
    {
        $db = db_connect();
        $search = trim((string) $this->request->getGet('q'));
        $guesthouseInput = $this->request->getGet('guesthouse');
        $guesthouse = filter_var($guesthouseInput, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5],
        ]) ?: 0;
        $status = trim((string) $this->request->getGet('status'));
        $source = trim((string) $this->request->getGet('source'));
        $checkInFrom = $this->validDateFilter((string) $this->request->getGet('check_in_from'));
        $checkInTo = $this->validDateFilter((string) $this->request->getGet('check_in_to'));
        $allowedStatuses = ['Confirmed', 'Checked_In', 'Checked_Out', 'Cancelled', 'No_Show'];
        $allowedSources = ['Direct', 'Online', 'Booking.com', 'Agoda', 'Traveloka', 'Airbnb', 'Tiket.com'];

        $pricingMode = PropertyPricing::active()['pricing_mode'];
        $isHomestay = $pricingMode === 'whole_home_nightly'
            || PropertyContext::activeId() === 2
            || (string) session('activePropertyCode') === 'HOMESTAY_MITRA';

        if ($isHomestay) {
            $builder = $db->table('reservations')
                ->select("
                    MIN(reservations.id) AS id,
                    COALESCE(NULLIF(reservations.booking_group_id, ''), reservations.order_id) AS order_id,
                    MIN(reservations.booking_group_id) AS booking_group_id,
                    MAX(reservations.source) AS source,
                    MAX(reservations.important_note) AS important_note,
                    MAX(reservations.status) AS status,
                    MIN(reservations.check_in_date) AS check_in_date,
                    MAX(reservations.check_out_date) AS check_out_date,
                    SUM(reservations.total_amount) AS total_amount,
                    SUM(reservations.breakfast_cost) AS breakfast_cost,
                    SUM(reservations.laundry_cost) AS laundry_cost,
                    SUM(reservations.massage_cost) AS massage_cost,
                    MAX(guests.full_name) AS guest_name,
                    '1 Rumah' AS room_number,
                    MAX(rooms.floor) AS guesthouse
                ", false)
                ->join('guests', 'guests.id = reservations.guest_id')
                ->join('rooms', 'rooms.id = reservations.room_id')
                ->where('reservations.property_id', PropertyContext::activeId())
                ->groupBy("COALESCE(NULLIF(reservations.booking_group_id, ''), reservations.order_id)");
        } else {
            $builder = $db->table('reservations')
                ->select('reservations.id, reservations.order_id, reservations.source, reservations.important_note, reservations.status, reservations.check_in_date, reservations.check_out_date, reservations.total_amount, reservations.breakfast_cost, reservations.laundry_cost, reservations.massage_cost, guests.full_name AS guest_name, rooms.room_number, rooms.floor AS guesthouse', false)
                ->join('guests', 'guests.id = reservations.guest_id')
                ->join('rooms', 'rooms.id = reservations.room_id')
                ->where('reservations.property_id', PropertyContext::activeId());
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('reservations.order_id', $search)
                ->orLike('reservations.booking_group_id', $search)
                ->orLike('guests.full_name', $search)
                ->orLike('guests.phone', $search)
                ->orLike('rooms.room_number', $search)
                ->groupEnd();
        }
        if ($guesthouse > 0 && PropertyContext::activeId() === PropertyContext::HOMIQ_ID) {
            $builder->groupStart()
                ->where('rooms.guesthouse_number', $guesthouse)
                ->orWhere('rooms.floor', $guesthouse)
                ->groupEnd();
        }
        if (in_array($status, $allowedStatuses, true)) {
            $builder->where('reservations.status', $status);
        }
        if (in_array($source, $allowedSources, true)) {
            if ($source === 'Direct') {
                $builder->groupStart()
                    ->where('reservations.source', 'Direct')
                    ->orWhere('reservations.source', '')
                    ->orWhere('reservations.source IS NULL', null, false)
                    ->groupEnd();
            } else {
                $builder->where('reservations.source', $source);
            }
        }
        if ($checkInFrom !== null) {
            $builder->where('reservations.check_in_date >=', $checkInFrom);
        }
        if ($checkInTo !== null) {
            $builder->where('reservations.check_in_date <=', $checkInTo);
        }

        $orderField = $isHomestay ? 'MIN(reservations.check_in_date)' : 'reservations.check_in_date';
        $orderIdField = $isHomestay ? 'MIN(reservations.id)' : 'reservations.id';

        $reservations = $builder
            ->orderBy($orderField, 'DESC')
            ->orderBy($orderIdField, 'DESC')
            ->get()
            ->getResultArray();

        $serviceItemNames = ['Breakfast', 'Extra Bed', 'Biaya Extra Bed', 'Laundry', 'Massage', 'Sewa Kendaraan'];
        $itemsByGroup = [];
        $itemsByReservation = [];

        if ($isHomestay) {
            $items = $db->table('invoice_items')
                ->select('invoice_items.*, r.booking_group_id, r.order_id')
                ->join('reservations r', 'r.id = invoice_items.reservation_id')
                ->where('r.property_id', PropertyContext::activeId())
                ->orderBy('invoice_items.id', 'ASC')
                ->get()
                ->getResultArray();
            foreach ($items as $item) {
                $gid = $item['booking_group_id'] ?: $item['order_id'];
                $itemsByGroup[$gid][] = $item;
            }
        } else {
            $reservationIds = array_map('intval', array_column($reservations, 'id'));
            $items = $reservationIds === [] ? [] : $db->table('invoice_items')
                ->whereIn('reservation_id', $reservationIds)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            foreach ($items as $item) {
                $itemsByReservation[(int) $item['reservation_id']][] = $item;
            }
        }

        $statusLabels = ['Confirmed' => 'Terkonfirmasi', 'Checked_In' => 'Check-in', 'Checked_Out' => 'Check-out', 'Cancelled' => 'Dibatalkan', 'No_Show' => 'No-Show'];
        $stringCell = static fn ($value, string $style = ''): string => '<Cell' . ($style === '' ? '' : ' ss:StyleID="' . $style . '"') . '><Data ss:Type="String">' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</Data></Cell>';
        $numberCell = static fn ($value): string => '<Cell ss:StyleID="Currency"><Data ss:Type="Number">' . (float) $value . '</Data></Cell>';
        $roomColumnHeader = $isHomestay ? 'Homestay' : 'Kamar';
        $rows = ['<Row>' . implode('', array_map(static fn ($heading) => $stringCell($heading, 'Header'), ['Nama Tamu', 'ID Booking', $roomColumnHeader, 'Properti / GH', 'Sumber', 'Catatan', 'Tanggal Check-in', 'Tanggal Check-out', 'Status', 'Biaya Menginap', 'Biaya Layanan'])) . '</Row>'];
        foreach ($reservations as $reservation) {
            $stayCost = 0.0;
            $serviceCost = 0.0;
            $reservationItems = $isHomestay
                ? ($itemsByGroup[$reservation['order_id']] ?? [])
                : ($itemsByReservation[(int) $reservation['id']] ?? []);

            foreach ($reservationItems as $item) {
                if (str_starts_with((string) $item['item_name'], 'Room ')) {
                    $stayCost += (float) $item['amount'];
                } elseif (in_array((string) $item['item_name'], $serviceItemNames, true)) {
                    $serviceCost += (float) $item['amount'];
                }
            }
            if ($reservationItems === []) {
                $serviceCost = (float) ($reservation['breakfast_cost'] ?? 0) + (float) ($reservation['laundry_cost'] ?? 0) + (float) ($reservation['massage_cost'] ?? 0);
                $stayCost = max(0, (float) $reservation['total_amount'] - $serviceCost);
            }
            $rows[] = '<Row>'
                . $stringCell($reservation['guest_name'])
                . $stringCell($reservation['order_id'])
                . $stringCell($isHomestay ? '1 Rumah' : $reservation['room_number'])
                . $stringCell(PropertyContext::activeId() === PropertyContext::HOMIQ_ID ? 'GH ' . $reservation['guesthouse'] : (string) session('activePropertyName'))
                . $stringCell($reservation['source'] ?: 'Direct')
                . $stringCell($reservation['important_note'] ?: '')
                . $stringCell($reservation['check_in_date'])
                . $stringCell($reservation['check_out_date'])
                . $stringCell($statusLabels[$reservation['status']] ?? $reservation['status'])
                . $numberCell($stayCost)
                . $numberCell($serviceCost)
                . '</Row>';
        }

        $content = '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?>'
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            . '<Styles><Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#2563EB" ss:Pattern="Solid"/></Style><Style ss:ID="Currency"><NumberFormat ss:Format="#,##0"/></Style></Styles>'
            . '<Worksheet ss:Name="Reservations"><Table><Column ss:Width="150"/><Column ss:Width="140"/><Column ss:Width="70"/><Column ss:Width="50"/><Column ss:Width="110"/><Column ss:Width="240"/><Column ss:Width="100"/><Column ss:Width="100"/><Column ss:Width="100"/><Column ss:Width="110"/><Column ss:Width="110"/>'
            . implode('', $rows) . '</Table></Worksheet></Workbook>';
        $suffix = $guesthouse > 0 ? '-gh-' . $guesthouse : '';
        $filePrefix = PropertyContext::activeId() === PropertyContext::HOMIQ_ID
            ? 'homiq'
            : (preg_replace('/[^a-z0-9_-]/', '-', strtolower((string) session('activePropertyCode'))) ?: 'property');
        return $this->response->setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')->download($filePrefix . '-reservations' . $suffix . '.xls', $content);
    }

    public function new(): string
    {
        return view('components/modals/new_booking', [
            'rooms' => db_connect()->table('rooms')->where('property_id', PropertyContext::activeId())->orderBy('room_number', 'ASC')->get()->getResultArray(),
            'pricing' => PropertyPricing::active(),
        ]);
    }

    public function availableRooms()
    {
        $checkIn = $this->validDateFilter((string) $this->request->getGet('check_in_date'));
        $checkOut = $this->validDateFilter((string) $this->request->getGet('check_out_date'));
        if ($checkIn === null || $checkOut === null || $checkOut < $checkIn) {
            return $this->response->setJSON([
                'valid' => false,
                'message' => 'Pilih tanggal check-in dan check-out yang valid terlebih dahulu.',
                'rooms' => [],
                'extra_beds' => ['total_stock' => 0, 'rented' => 0, 'available' => 0],
            ]);
        }

        $pricing = PropertyPricing::active();
        if ($pricing['pricing_mode'] === 'room_monthly' && PropertyPricing::wholeMonths($checkIn, $checkOut) === null) {
            return $this->response->setJSON([
                'valid' => false,
                'message' => 'Kos Mitra hanya dapat dipesan dalam kelipatan bulan sejak tanggal masuk.',
                'rooms' => [],
                'extra_beds' => ['total_stock' => 0, 'rented' => 0, 'available' => 0],
            ]);
        }

        $db = db_connect();
        $stayEnd = $this->stayEndExclusive($checkIn, $checkOut);
        $extraBeds = (new ExtraBedService($db))->availability($checkIn, $stayEnd);
        $bookedRoomIds = array_flip(array_map('intval', array_column(
            $db->table('reservations')
                ->select('room_id')
                ->where('property_id', PropertyContext::activeId())
                ->where('check_in_date <', $stayEnd)
                ->where("CASE WHEN check_out_date <= check_in_date THEN DATE_ADD(check_in_date, INTERVAL 1 DAY) ELSE check_out_date END > " . $db->escape($checkIn), null, false)
                ->where('status !=', 'Cancelled')
                ->where('status !=', 'Checked_Out')
                ->get()
                ->getResultArray(),
            'room_id'
        )));

        $rooms = $db->table('rooms')->where('property_id', PropertyContext::activeId())->orderBy('room_number', 'ASC')->get()->getResultArray();
        $availableCount = 0;
        $wholeHomeBlocked = $pricing['pricing_mode'] === 'whole_home_nightly'
            && (count($bookedRoomIds) > 0 || count(array_filter($rooms, static fn (array $room): bool => $room['status'] === 'Maintenance')) > 0);
        $result = array_map(static function (array $room) use ($bookedRoomIds, &$availableCount, $wholeHomeBlocked): array {
            $maintenance = $room['status'] === 'Maintenance';
            $booked = isset($bookedRoomIds[(int) $room['id']]);
            $available = ! $maintenance && ! $booked && ! $wholeHomeBlocked;
            if ($available) {
                $availableCount++;
            }

            return [
                'id' => (int) $room['id'],
                'available' => $available,
                'reason' => $maintenance ? 'Maintenance' : (($booked || $wholeHomeBlocked) ? 'Rumah sudah dibooking pada tanggal ini' : ''),
            ];
        }, $rooms);

        $isWholeHome = $pricing['pricing_mode'] === 'whole_home_nightly';
        $message = $isWholeHome
            ? ($availableCount === count($rooms) ? 'Unit homestay (1 rumah) tersedia untuk tanggal yang dipilih.' : 'Unit homestay sudah terisi atau maintenance pada tanggal yang dipilih.')
            : ($availableCount . ' kamar tersedia untuk tanggal yang dipilih.');

        return $this->response->setJSON([
            'valid' => true,
            'message' => $message,
            'rooms' => $result,
            'extra_beds' => $extraBeds,
        ]);
    }

    public function create()
    {
        $rules = [
            'guest_name' => 'required|max_length[150]',
            'email' => 'permit_empty|valid_email|max_length[150]',
            'phone' => 'permit_empty|max_length[50]',
            'origin' => 'permit_empty|max_length[150]',
            'check_in_date' => 'required|valid_date[Y-m-d]',
            'check_out_date' => 'required|valid_date[Y-m-d]',
            'guest_count' => 'required|is_natural_no_zero',
            'confirmed_total' => 'permit_empty|decimal',
            'payment_type' => 'permit_empty|in_list[unpaid,dp,full]',
            'payment_amount' => 'permit_empty|decimal',
            'payment_date' => 'permit_empty|valid_date[Y-m-d]',
            'important_note' => 'permit_empty|max_length[2000]',
        ];

        if (! $this->validate($rules)) {
            return $this->newBookingError(implode(' ', $this->validator->getErrors()));
        }

        $checkIn = new DateTimeImmutable((string) $this->request->getPost('check_in_date'));
        $checkOut = new DateTimeImmutable((string) $this->request->getPost('check_out_date'));
        if ($checkOut < $checkIn) {
            return $this->newBookingError('Tanggal check-out tidak boleh sebelum tanggal check-in.');
        }
        $pricing = PropertyPricing::active();
        $months = $pricing['pricing_mode'] === 'room_monthly'
            ? PropertyPricing::wholeMonths($checkIn->format('Y-m-d'), $checkOut->format('Y-m-d')) : null;
        if ($pricing['pricing_mode'] === 'room_monthly' && $months === null) {
            return $this->newBookingError('Kos Mitra hanya dapat dipesan dalam kelipatan bulan sejak tanggal masuk.');
        }
        $stayEnd = $this->stayEndExclusive($checkIn->format('Y-m-d'), $checkOut->format('Y-m-d'));
        $isTransit = $checkIn->format('Y-m-d') === $checkOut->format('Y-m-d');

        $roomIds = $this->postedRoomIds();
        if ($roomIds === []) {
            return $this->newBookingError('Pilih minimal satu kamar.');
        }
        $extraBedQuantities = $this->postedExtraBedQuantities($roomIds);
        $requestedExtraBeds = array_sum($extraBedQuantities);

        $db = db_connect();
        $selectedGuestId = (int) $this->request->getPost('guest_id');
        if ($selectedGuestId > 0 && $db->table('guests')->select('id')->where('id', $selectedGuestId)->where('property_id', PropertyContext::activeId())->get()->getRowArray() === null) {
            return $this->newBookingError('Data tamu yang dipilih tidak ditemukan. Silakan pilih ulang atau isi sebagai tamu baru.');
        }
        $rooms = $db->table('rooms')->where('property_id', PropertyContext::activeId())->whereIn('id', $roomIds)->orderBy('room_number', 'ASC')->get()->getResultArray();
        if (count($rooms) !== count($roomIds)) {
            return $this->newBookingError('Salah satu kamar tidak ditemukan.');
        }
        if ($pricing['pricing_mode'] === 'whole_home_nightly') {
            $allRoomIds = array_map('intval', array_column($db->table('rooms')
                ->select('id')->where('property_id', PropertyContext::activeId())->get()->getResultArray(), 'id'));
            sort($allRoomIds);
            $selectedIds = $roomIds;
            sort($selectedIds);
            if ($allRoomIds === [] || $selectedIds !== $allRoomIds) {
                return $this->newBookingError('Homestay Mitra disewa satu rumah; pilih seluruh 3 kamar sekaligus.');
            }
        }

        $maintenanceRooms = array_column(array_filter($rooms, static fn (array $room): bool => $room['status'] === 'Maintenance'), 'room_number');
        if ($maintenanceRooms !== []) {
            return $this->newBookingError('Kamar ' . implode(', ', $maintenanceRooms) . ' sedang maintenance dan tidak dapat dipesan.');
        }

        $conflictingRooms = $this->findConflictingRooms($db, $roomIds, $checkIn->format('Y-m-d'), $stayEnd);
        if ($conflictingRooms !== []) {
            return $this->newBookingError('Kamar ' . implode(', ', $conflictingRooms) . ' sudah terisi pada rentang tanggal tersebut. Tidak ada booking yang disimpan.');
        }
        if ($requestedExtraBeds > 0) {
            try {
                (new ExtraBedService($db))->assertAvailable($checkIn->format('Y-m-d'), $stayEnd, $requestedExtraBeds);
            } catch (\RuntimeException $error) {
                return $this->newBookingError(str_starts_with($error->getMessage(), 'EXTRA_BED_UNAVAILABLE:')
                    ? substr($error->getMessage(), strlen('EXTRA_BED_UNAVAILABLE:'))
                    : $error->getMessage());
            }
        }

        $nights = max(1, (int) $checkIn->diff($checkOut)->days);
        $extraBedRate = max(0, $this->parseMoney($this->request->getPost('extra_bed_cost')));
        $extraBedUnits = $requestedExtraBeds > 0
            ? $requestedExtraBeds
            : ($this->request->getPost('extra_bed_quantity') === null && $extraBedRate > 0 ? 1 : 0);
        $extraBedCost = $extraBedRate * $extraBedUnits * $nights;
        if ($requestedExtraBeds === 0 && $extraBedUnits > 0 && isset($rooms[0]['id'])) {
            $extraBedQuantities[(int) $rooms[0]['id']] = $extraBedUnits;
            $requestedExtraBeds = $extraBedUnits;
        }
        $services = [
            'Breakfast' => max(0, $this->parseMoney($this->request->getPost('breakfast_cost'))),
            'Extra Bed' => $extraBedCost,
            'Early Check In' => max(0, $this->parseMoney($this->request->getPost('early_checkin_cost'))),
            'Late Checkout' => max(0, $this->parseMoney($this->request->getPost('late_checkout_cost'))),
            'Laundry' => max(0, $this->parseMoney($this->request->getPost('laundry_cost'))),
            'Massage' => max(0, $this->parseMoney($this->request->getPost('massage_cost'))),
            'Sewa Kendaraan' => max(0, $this->parseMoney($this->request->getPost('vehicle_rental_cost'))),
        ];
        $serviceTotal = array_sum($services);
        $roomCharges = [];
        $wholeHomeTotal = round($pricing['base_rate'] * $nights, 2);
        $wholeHomeAllocated = 0.0;
        foreach ($rooms as $index => $room) {
            $roomCharges[(int) $room['id']] = match ($pricing['pricing_mode']) {
                'whole_home_nightly' => $index === count($rooms) - 1
                    ? round($wholeHomeTotal - $wholeHomeAllocated, 2)
                    : round($wholeHomeTotal / count($rooms), 2),
                'room_monthly' => ((float) ($room['price_per_night'] ?? 0) > 0 ? (float) $room['price_per_night'] : (float) $pricing['base_rate']) * $months,
                default => (float) $room['price_per_night'] * $nights,
            };
            if ($pricing['pricing_mode'] === 'whole_home_nightly') {
                $wholeHomeAllocated += $roomCharges[(int) $room['id']];
            }
        }
        $standardTotal = array_sum($roomCharges) + $serviceTotal;
        $confirmedTotalInput = $this->nullablePost('confirmed_total');
        $total = $confirmedTotalInput === null ? $standardTotal : max(0, $this->parseMoney($confirmedTotalInput));
        $baseReservationTotals = [];
        foreach ($rooms as $index => $room) {
            $baseReservationTotals[(int) $room['id']] = $roomCharges[(int) $room['id']] + ($index === 0 ? $serviceTotal : 0);
        }
        $reservationTotals = [];
        $remainingTotal = $total;
        $lastRoomIndex = count($rooms) - 1;
        foreach ($rooms as $index => $room) {
            $roomId = (int) $room['id'];
            if ($index === $lastRoomIndex) {
                $reservationTotals[$roomId] = max(0, $remainingTotal);
                continue;
            }

            $allocated = $standardTotal > 0
                ? round($total * ($baseReservationTotals[$roomId] / $standardTotal), 2)
                : ($index === 0 ? $total : 0);
            $reservationTotals[$roomId] = max(0, $allocated);
            $remainingTotal -= $reservationTotals[$roomId];
        }

        $paymentType = (string) $this->request->getPost('payment_type');
        $paymentType = in_array($paymentType, ['unpaid', 'dp', 'full'], true) ? $paymentType : 'unpaid';
        $paymentAmountInput = $this->nullablePost('payment_amount');
        $paymentAmount = match ($paymentType) {
            'full' => $total,
            'dp' => $paymentAmountInput === null ? round($total * 0.5, 2) : $this->parseMoney($paymentAmountInput),
            default => 0,
        };
        if ($paymentType === 'dp' && ($paymentAmount <= 0 || $paymentAmount > $total)) {
            return $this->newBookingError('Nominal DP harus lebih dari Rp0 dan tidak boleh melebihi total booking.');
        }
        $source = $this->nullablePost('source') ?? 'Direct';
        $isAgentGuest = (string) $this->request->getPost('is_agent_guest') === '1';
        $agentName = $isAgentGuest ? $this->nullablePost('agent_name') : null;
        if ($isAgentGuest && $agentName === null) {
            return $this->newBookingError('Nama agen wajib diisi untuk tamu dari agen.');
        }
        if ($agentName !== null && mb_strlen($agentName) > 150) {
            return $this->newBookingError('Nama agen maksimal 150 karakter.');
        }
        $isOtaSource = in_array($source, ['Booking.com', 'Agoda', 'Traveloka', 'Airbnb', 'Tiket.com'], true);
        $paymentMethod = $isOtaSource ? 'OTA' : ($this->nullablePost('payment_method') ?? 'Cash');
        $paymentDate = $paymentAmount > 0 && ! $isOtaSource && $paymentMethod !== 'OTA'
            ? ($this->validDateFilter((string) $this->request->getPost('payment_date')) ?? date('Y-m-d'))
            : null;
        try {
            $paymentProof = $this->storePaymentProof($paymentMethod, $paymentAmount);
            $ktpPhoto = $this->storeKtpPhoto();
        } catch (\RuntimeException $error) {
            $this->removeStoredImage($paymentProof ?? null);
            return $this->newBookingError($error->getMessage());
        }

        $bookingGroupId = $this->generateBookingGroupId();
        $now = date('Y-m-d H:i:s');

        try {
            $db->transException(true);
            $db->transBegin();

            $lockedRoomIds = implode(',', array_map('intval', $roomIds));
            $db->query("SELECT id FROM rooms WHERE property_id = ? AND id IN ({$lockedRoomIds}) ORDER BY id FOR UPDATE", [PropertyContext::activeId()]);
            $conflictingRooms = $this->findConflictingRooms($db, $roomIds, $checkIn->format('Y-m-d'), $stayEnd);
            if ($conflictingRooms !== []) {
                throw new \RuntimeException('BOOKING_CONFLICT:' . implode(', ', $conflictingRooms));
            }
            if ($requestedExtraBeds > 0) {
                (new ExtraBedService($db))->assertAvailable($checkIn->format('Y-m-d'), $stayEnd, $requestedExtraBeds);
            }

            $guestId = (new GuestDirectory($db))->save([
                'full_name' => trim((string) $this->request->getPost('guest_name')),
                'email' => $this->nullablePost('email'),
                'phone' => $this->nullablePost('phone'),
                'origin' => $this->nullablePost('origin'),
                'notes' => $this->nullablePost('guest_notes'),
                'ktp_photo_path' => $ktpPhoto['relative_path'] ?? null,
                'ktp_photo_original_name' => $ktpPhoto['original_name'] ?? null,
                'ktp_photo_mime_type' => $ktpPhoto['mime_type'] ?? null,
                'ktp_photo_size' => $ktpPhoto['file_size'] ?? null,
            ], $selectedGuestId > 0 ? $selectedGuestId : null);

            $createdReservations = [];
            foreach ($rooms as $index => $room) {
                $isPrimary = $index === 0;
                $orderId = count($rooms) === 1
                    ? $bookingGroupId
                    : $bookingGroupId . '-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                $roomId = (int) $room['id'];
                $reservationTotal = $reservationTotals[$roomId];
                $priceAdjustment = $reservationTotal - $baseReservationTotals[$roomId];

                $db->table('reservations')->insert([
                    'property_id' => PropertyContext::activeId(),
                    'guest_id' => $guestId,
                    'room_id' => (int) $room['id'],
                    'order_id' => $orderId,
                    'booking_group_id' => $bookingGroupId,
                    'check_in_date' => $checkIn->format('Y-m-d'),
                    'check_out_date' => $checkOut->format('Y-m-d'),
                    'status' => 'Confirmed',
                    'source' => $source,
                    'agent_name' => $agentName,
                    'guest_count' => $isPrimary ? (int) $this->request->getPost('guest_count') : 0,
                    'special_request' => $this->nullablePost('special_request'),
                    'important_note' => $this->nullablePost('important_note'),
                    'has_breakfast' => $isPrimary && $services['Breakfast'] > 0 ? 1 : 0,
                    'has_extrabed' => ($extraBedQuantities[$roomId] ?? 0) > 0 ? 1 : 0,
                    'has_late_checkout' => $isPrimary && $services['Late Checkout'] > 0 ? 1 : 0,
                    'has_laundry' => $isPrimary && $services['Laundry'] > 0 ? 1 : 0,
                    'breakfast_cost' => $isPrimary ? $services['Breakfast'] : 0,
                    'laundry_cost' => $isPrimary ? $services['Laundry'] : 0,
                    'massage_cost' => $isPrimary ? $services['Massage'] : 0,
                    'total_amount' => $reservationTotal,
                    'created_at' => $now,
                ]);
                $reservationId = (int) $db->insertID();
                $createdReservations[] = [
                    'id' => $reservationId,
                    'room_id' => $roomId,
                    'order_id' => $orderId,
                    'total_amount' => $reservationTotal,
                    'paid_amount' => 0,
                ];

                $db->table('invoice_items')->insert([
                    'reservation_id' => $reservationId,
                    'order_id' => $orderId,
                    'item_name' => 'Room ' . $room['room_number'] . ($pricing['pricing_mode'] === 'room_monthly' ? " ({$months} bulan)" : ($isTransit ? ' (Transit)' : " ({$nights} malam)")),
                    'amount' => $roomCharges[$roomId],
                    'created_at' => $now,
                ]);

                if ($isPrimary) {
                    foreach ($services as $name => $amount) {
                        if ($amount > 0) {
                            $db->table('invoice_items')->insert([
                                'reservation_id' => $reservationId,
                                'order_id' => $orderId,
                                'item_name' => $name,
                                'amount' => $amount,
                                'created_at' => $now,
                            ]);
                        }
                    }
                }
                if (abs($priceAdjustment) >= 0.01) {
                    $db->table('invoice_items')->insert([
                        'reservation_id' => $reservationId,
                        'order_id' => $orderId,
                        'item_name' => 'Penyesuaian harga khusus',
                        'amount' => $priceAdjustment,
                        'created_at' => $now,
                    ]);
                }
            }

            foreach ($createdReservations as $createdReservation) {
                $quantity = (int) ($extraBedQuantities[(int) $createdReservation['room_id']] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                $db->table('extra_bed_allocations')->insert([
                    'reservation_id' => (int) $createdReservation['id'],
                    'room_id' => (int) $createdReservation['room_id'],
                    'quantity' => $quantity,
                    'check_in_date' => $checkIn->format('Y-m-d'),
                    'check_out_date' => $stayEnd,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($paymentAmount > 0) {
                $this->distributePayment(
                    $db,
                    $createdReservations,
                    min($paymentAmount, $total),
                    $paymentDate,
                    $paymentMethod,
                    null,
                    true,
                    $paymentProof,
                    $paymentType
                );
            }
            $db->transCommit();
        } catch (Throwable $error) {
            $db->transRollback();
            $this->removeStoredImage($paymentProof ?? null);
            $this->removeStoredImage($ktpPhoto ?? null);
            if (str_starts_with($error->getMessage(), 'BOOKING_CONFLICT:')) {
                $numbers = substr($error->getMessage(), strlen('BOOKING_CONFLICT:'));
                return $this->newBookingError('Kamar ' . $numbers . ' baru saja terisi pada rentang tanggal tersebut. Tidak ada booking yang disimpan.');
            }
            if (str_starts_with($error->getMessage(), 'PAYMENT_VALIDATION:')) {
                return $this->newBookingError(substr($error->getMessage(), strlen('PAYMENT_VALIDATION:')));
            }
            if (str_starts_with($error->getMessage(), 'EXTRA_BED_UNAVAILABLE:')) {
                return $this->newBookingError(substr($error->getMessage(), strlen('EXTRA_BED_UNAVAILABLE:')));
            }
            log_message('error', 'Create reservation failed: {message}', ['message' => $error->getMessage()]);
            return $this->newBookingError('Reservasi gagal disimpan. Silakan coba lagi.');
        }

        $guesthouseIds = array_values(array_unique(array_map(
            static fn (array $room): int => (int) $room['floor'],
            $rooms
        )));
        $reservationTrigger = [
            'reservationChanged' => true,
            'closeModal' => true,
        ];
        if (PropertyContext::activeId() !== PropertyContext::HOMIQ_ID || count($guesthouseIds) === 1) {
            $reservationTrigger['reservationCreated'] = [
                'guesthouse' => PropertyContext::activeId() === PropertyContext::HOMIQ_ID ? $guesthouseIds[0] : null,
                'month' => $checkIn->format('Y-m'),
            ];
        }

        return $this->response
            ->setHeader('HX-Trigger', json_encode($reservationTrigger))
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setBody('<div class="alert alert-success mb-0">Booking ' . esc($bookingGroupId) . ' untuk ' . count($rooms) . ' kamar berhasil dibuat.</div>');
    }

    public function show(int $id)
    {
        $this->disableCaching();
        try {
            $data = $this->detailData($id);
            if ($data === null) {
                return $this->response->setStatusCode(404)->setBody('<div class="alert alert-danger">Reservasi tidak ditemukan.</div>');
            }

            return view('components/modals/booking_details', $data);
        } catch (Throwable $error) {
            log_message('error', 'Load reservation detail failed for {id}: {message}', [
                'id' => $id,
                'message' => $error->getMessage(),
            ]);

            return $this->response
                ->setStatusCode(500)
                ->setBody('<div class="alert alert-danger">Detail reservasi belum dapat dimuat. Silakan coba lagi.</div>');
        }
    }

    public function invoice(int $id)
    {
        try {
            $data = $this->detailData($id);
            if ($data === null) {
                return $this->response->setStatusCode(404)->setBody('Reservasi tidak ditemukan.');
            }

            $invoiceData = $this->invoiceData($data, $this->request->getGet('format') === 'ringkas');
            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);

            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('documents/reservation_invoice', $invoiceData), 'UTF-8');
            $pdf->setPaper('A4', 'portrait');
            $pdf->render();

            $filename = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $invoiceData['invoiceNumber']) ?: 'invoice';

            return $this->response
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '.pdf"')
                ->setBody($pdf->output());
        } catch (Throwable $error) {
            log_message('error', 'Generate reservation invoice failed for {id}: {message}', [
                'id' => $id,
                'message' => $error->getMessage(),
            ]);

            return $this->response
                ->setStatusCode(500)
                ->setBody('Invoice PDF belum dapat dibuat. Silakan coba lagi.');
        }
    }

    public function addPayment(int $id)
    {
        $data = $this->detailData($id);
        if ($data === null) {
            return $this->response->setStatusCode(404)->setBody('Reservasi tidak ditemukan.');
        }

        $amount = $this->parseMoney($this->request->getPost('amount'));
        $outstanding = max(0, (float) $data['groupTotal'] - (float) $data['groupPaid']);
        if ($amount <= 0 || $amount > $outstanding) {
            return $this->response->setBody('<div class="alert alert-danger mb-0">Nominal harus lebih dari 0 dan tidak melebihi sisa tagihan.</div>');
        }

        $db = db_connect();
        $paymentMethod = $this->nullablePost('payment_method') ?? 'Cash';
        $paymentDate = $paymentMethod === 'OTA'
            ? null
            : ($this->validDateFilter((string) $this->request->getPost('payment_date')) ?? date('Y-m-d'));
        try {
            $paymentProof = $this->storePaymentProof($paymentMethod, $amount);
        } catch (\RuntimeException $error) {
            return $this->response->setBody('<div class="alert alert-danger mb-0">' . esc($error->getMessage()) . '</div>');
        }

        try {
            $db->transException(true);
            $db->transBegin();
            $this->distributePayment(
                $db,
                $data['groupReservations'],
                $amount,
                $paymentDate,
                $paymentMethod,
                $this->nullablePost('notes'),
                false,
                $paymentProof
            );
            $db->transCommit();
        } catch (Throwable $error) {
            $db->transRollback();
            $this->removeStoredImage($paymentProof ?? null);
            log_message('error', 'Add payment failed: {message}', ['message' => $error->getMessage()]);
            return $this->response->setBody('<div class="alert alert-danger mb-0">Pembayaran gagal disimpan. Silakan coba lagi.</div>');
        }

        $response = $this->show($id);
        return $response instanceof \CodeIgniter\HTTP\ResponseInterface
            ? $response->setHeader('HX-Trigger', json_encode(['reservationChanged' => true]))->setHeader('X-CSRF-TOKEN', csrf_hash())
            : $this->response->setHeader('HX-Trigger', json_encode(['reservationChanged' => true]))->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody($response);
    }

    public function updateRoomPrice(int $id)
    {
        if (PropertyPricing::active()['pricing_mode'] === 'whole_home_nightly') {
            return $this->response->setStatusCode(422)->setBody('<div class="alert alert-warning mb-0">Tarif Homestay berlaku untuk satu rumah; harga per kamar tidak dapat diubah terpisah.</div>');
        }
        $db = db_connect();
        $reservation = $db->table('reservations')
            ->select('reservations.id, reservations.order_id, reservations.total_amount, reservations.status, rooms.room_number')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->where('reservations.property_id', PropertyContext::activeId())
            ->where('reservations.id', $id)
            ->get()
            ->getRowArray();

        if ($reservation === null) {
            return $this->response->setStatusCode(404)->setBody('<div class="alert alert-danger mb-0">Reservasi tidak ditemukan.</div>');
        }
        if (in_array((string) $reservation['status'], ['Cancelled', 'Checked_Out'], true)) {
            return $this->response->setStatusCode(422)->setBody('<div class="alert alert-warning mb-0">Harga kamar yang sudah dibatalkan atau checkout tidak dapat diubah.</div>');
        }

        $rawAmount = (string) $this->request->getPost('total_amount');
        $normalizedAmount = preg_replace('/[^0-9-]/', '', $rawAmount) ?? '';
        $newTotal = (float) $normalizedAmount;
        $paidAmount = (float) ($db->table('payments')
            ->selectSum('amount')
            ->where('reservation_id', $id)
            ->where('status', 'Paid')
            ->get()
            ->getRow('amount') ?? 0);

        if ($newTotal <= 0) {
            return $this->response->setStatusCode(422)->setBody('<div class="alert alert-danger mb-0">Harga kamar harus lebih besar dari Rp0.</div>');
        }
        if ($newTotal < $paidAmount) {
            return $this->response->setStatusCode(422)->setBody('<div class="alert alert-danger mb-0">Harga kamar tidak boleh lebih kecil dari pembayaran yang sudah masuk sebesar Rp' . number_format($paidAmount, 0, ',', '.') . '.</div>');
        }

        $oldTotal = (float) $reservation['total_amount'];
        $difference = $newTotal - $oldTotal;
        if (abs($difference) < 0.01) {
            return $this->show($id);
        }

        $roomPrefix = 'Room ' . $reservation['room_number'];
        $roomItem = null;
        foreach ($db->table('invoice_items')->where('reservation_id', $id)->orderBy('id', 'ASC')->get()->getResultArray() as $item) {
            if (str_starts_with((string) $item['item_name'], $roomPrefix)) {
                $roomItem = $item;
                break;
            }
        }

        try {
            $db->transException(true);
            $db->transBegin();
            $db->table('reservations')->where('id', $id)->update([
                'total_amount' => $newTotal,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if ($roomItem !== null) {
                $db->table('invoice_items')->where('id', (int) $roomItem['id'])->update([
                    'amount' => (float) $roomItem['amount'] + $difference,
                ]);
            } else {
                $db->table('invoice_items')->insert([
                    'reservation_id' => $id,
                    'order_id' => $reservation['order_id'],
                    'item_name' => $roomPrefix,
                    'amount' => $newTotal,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $db->transCommit();
        } catch (Throwable $error) {
            $db->transRollback();
            log_message('error', 'Update room price failed for {id}: {message}', ['id' => $id, 'message' => $error->getMessage()]);
            return $this->response->setStatusCode(500)->setBody('<div class="alert alert-danger mb-0">Harga kamar gagal disimpan. Silakan coba lagi.</div>');
        }

        $response = $this->show($id);
        return $response instanceof \CodeIgniter\HTTP\ResponseInterface
            ? $response->setHeader('HX-Trigger', json_encode(['reservationChanged' => true, 'dashboardChanged' => true]))->setHeader('X-CSRF-TOKEN', csrf_hash())
            : $this->response->setHeader('HX-Trigger', json_encode(['reservationChanged' => true, 'dashboardChanged' => true]))->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody($response);
    }

    public function recordBookingComSettlement(int $id)
    {
        $data = $this->detailData($id);
        if ($data === null) {
            return $this->response->setStatusCode(404)->setBody('Reservasi tidak ditemukan.');
        }
        if (($data['reservation']['source'] ?? '') !== 'Booking.com') {
            return $this->response->setStatusCode(422)->setBody('<div class="alert alert-danger mb-0">Form ini hanya untuk booking dari Booking.com.</div>');
        }

        $total = max(0, $this->parseMoney($this->request->getPost('booking_total')));
        $amount = max(0, $this->parseMoney($this->request->getPost('settlement_amount')));
        $paid = (float) $data['groupPaid'];
        if ($total < $paid) {
            return $this->response->setBody('<div class="alert alert-danger mb-0">Total booking tidak boleh lebih kecil dari pembayaran yang sudah diterima.</div>');
        }
        if ($amount > max(0, $total - $paid)) {
            return $this->response->setBody('<div class="alert alert-danger mb-0">Nominal pencairan tidak boleh melebihi sisa total booking.</div>');
        }

        $activeReservations = array_values(array_filter(
            $data['groupReservations'],
            static fn (array $reservation): bool => (string) $reservation['status'] !== 'Cancelled',
        ));
        if ($activeReservations === []) {
            return $this->response->setBody('<div class="alert alert-warning mb-0">Tidak ada kamar aktif untuk dicatat.</div>');
        }

        $db = db_connect();
        $difference = $total - (float) $data['groupTotal'];
        $primaryReservation = $activeReservations[0];
        try {
            $db->transException(true);
            $db->transBegin();
            if (abs($difference) >= 0.01) {
                $db->table('reservations')->where('id', (int) $primaryReservation['id'])->update([
                    'total_amount' => (float) $primaryReservation['total_amount'] + $difference,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $db->table('invoice_items')->insert([
                    'reservation_id' => (int) $primaryReservation['id'],
                    'order_id' => $primaryReservation['order_id'],
                    'item_name' => 'Penyesuaian nominal Booking.com',
                    'amount' => $difference,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            if ($amount > 0) {
                $this->distributePayment($db, $activeReservations, $amount, null, 'OTA', 'Pencairan Booking.com menunggu tanggal diterima.', false);
            }
            $db->transCommit();
        } catch (Throwable $error) {
            $db->transRollback();
            log_message('error', 'Record Booking.com settlement failed: {message}', ['message' => $error->getMessage()]);
            return $this->response->setBody('<div class="alert alert-danger mb-0">Nominal Booking.com gagal disimpan. Silakan coba lagi.</div>');
        }

        $response = $this->show($id);
        return $response instanceof \CodeIgniter\HTTP\ResponseInterface
            ? $response->setHeader('HX-Trigger', json_encode(['reservationChanged' => true, 'dashboardChanged' => true]))->setHeader('X-CSRF-TOKEN', csrf_hash())
            : $this->response->setHeader('HX-Trigger', json_encode(['reservationChanged' => true, 'dashboardChanged' => true]))->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody($response);
    }

    public function addServices(int $id)
    {
        $data = $this->detailData($id);
        if ($data === null) {
            return $this->response->setStatusCode(404)->setBody('Reservasi tidak ditemukan.');
        }

        $reservation = $data['reservation'];
        if (! in_array((string) ($reservation['status'] ?? ''), ['Confirmed', 'Checked_In'], true)) {
            return $this->response->setBody('<div class="alert alert-warning mb-0">Layanan tambahan hanya dapat ditambahkan untuk booking yang masih aktif.</div>');
        }

        $services = [
            'Breakfast' => max(0, $this->parseMoney($this->request->getPost('breakfast_cost'))),
            'Extra Bed' => max(0, $this->parseMoney($this->request->getPost('extra_bed_cost'))),
            'Early Check In' => max(0, $this->parseMoney($this->request->getPost('early_checkin_cost'))),
            'Late Checkout' => max(0, $this->parseMoney($this->request->getPost('late_checkout_cost'))),
            'Laundry' => max(0, $this->parseMoney($this->request->getPost('laundry_cost'))),
            'Massage' => max(0, $this->parseMoney($this->request->getPost('massage_cost'))),
            'Sewa Kendaraan' => max(0, $this->parseMoney($this->request->getPost('vehicle_rental_cost'))),
        ];
        $serviceTotal = array_sum($services);
        if ($serviceTotal <= 0) {
            return $this->response->setBody('<div class="alert alert-warning mb-0">Masukkan minimal satu nominal layanan tambahan.</div>');
        }

        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        try {
            $db->transException(true);
            $db->transBegin();

            if ($services['Extra Bed'] > 0) {
                $checkInDate = max((string) $reservation['check_in_date'], date('Y-m-d'));
                $checkOutDate = $this->stayEndExclusive($checkInDate, (string) $reservation['check_out_date']);

                $allocation = $db->table('extra_bed_allocations')
                    ->where('reservation_id', $id)
                    ->where('room_id', (int) $reservation['room_id'])
                    ->get()
                    ->getRowArray();
                if ($allocation === null) {
                    (new ExtraBedService($db))->assertAvailable($checkInDate, $checkOutDate, 1);
                    $db->table('extra_bed_allocations')->insert([
                        'reservation_id' => $id,
                        'room_id' => (int) $reservation['room_id'],
                        'quantity' => 1,
                        'check_in_date' => $checkInDate,
                        'check_out_date' => $checkOutDate,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $updates = [
                'total_amount' => (float) $reservation['total_amount'] + $serviceTotal,
                'breakfast_cost' => (float) ($reservation['breakfast_cost'] ?? 0) + $services['Breakfast'],
                'laundry_cost' => (float) ($reservation['laundry_cost'] ?? 0) + $services['Laundry'],
                'massage_cost' => (float) ($reservation['massage_cost'] ?? 0) + $services['Massage'],
                'has_breakfast' => ((int) ($reservation['has_breakfast'] ?? 0) === 1 || $services['Breakfast'] > 0) ? 1 : 0,
                'has_extrabed' => ((int) ($reservation['has_extrabed'] ?? 0) === 1 || $services['Extra Bed'] > 0) ? 1 : 0,
                'has_late_checkout' => ((int) ($reservation['has_late_checkout'] ?? 0) === 1 || $services['Late Checkout'] > 0) ? 1 : 0,
                'updated_at' => $now,
            ];
            $db->table('reservations')->where('id', $id)->update($updates);

            foreach ($services as $name => $amount) {
                if ($amount <= 0) {
                    continue;
                }
                $db->table('invoice_items')->insert([
                    'reservation_id' => $id,
                    'order_id' => $reservation['order_id'],
                    'item_name' => $name,
                    'amount' => $amount,
                    'created_at' => $now,
                ]);
            }
            $db->transCommit();
        } catch (Throwable $error) {
            $db->transRollback();
            log_message('error', 'Add additional services failed: {message}', ['message' => $error->getMessage()]);
            if (str_starts_with($error->getMessage(), 'EXTRA_BED_UNAVAILABLE:')) {
                return $this->response->setBody('<div class="alert alert-warning mb-0">' . esc(substr($error->getMessage(), strlen('EXTRA_BED_UNAVAILABLE:'))) . '</div>');
            }
            return $this->response->setBody('<div class="alert alert-danger mb-0">Layanan tambahan gagal disimpan. Silakan coba lagi.</div>');
        }

        $response = $this->show($id);
        $triggers = json_encode(['reservationChanged' => true, 'dashboardChanged' => true, 'opsChanged' => true]);
        return $response instanceof \CodeIgniter\HTTP\ResponseInterface
            ? $response->setHeader('HX-Trigger', $triggers)->setHeader('X-CSRF-TOKEN', csrf_hash())
            : $this->response->setHeader('HX-Trigger', $triggers)->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody($response);
    }

    public function updateStayDates(int $id)
    {
        $data = $this->detailData($id);
        if ($data === null) {
            return $this->response->setStatusCode(404)->setBody('Reservasi tidak ditemukan.');
        }

        $checkIn = $this->validDateFilter((string) $this->request->getPost('check_in_date'));
        $checkOut = $this->validDateFilter((string) $this->request->getPost('check_out_date'));
        $isMonthly = PropertyPricing::active()['pricing_mode'] === 'room_monthly';
        if ($checkIn === null || $checkOut === null || $checkOut < $checkIn) {
            return $this->response->setBody('<div class="alert alert-danger mb-0">' . ($isMonthly ? 'Tanggal keluar tidak boleh sebelum tanggal masuk.' : 'Tanggal check-out tidak boleh sebelum tanggal check-in.') . '</div>');
        }
        if ($isMonthly && PropertyPricing::wholeMonths($checkIn, $checkOut) === null) {
            return $this->response->setBody('<div class="alert alert-danger mb-0">Kos Mitra hanya dapat dipesan dalam kelipatan bulan sejak tanggal masuk.</div>');
        }

        $db = db_connect();
        $bookingGroupId = trim((string) ($data['reservation']['booking_group_id'] ?? ''));
        $groupBuilder = $db->table('reservations')->select('reservations.id, reservations.room_id, reservations.status, rooms.room_number')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->where('reservations.property_id', PropertyContext::activeId());
        if ($bookingGroupId !== '') {
            $groupBuilder->where('reservations.booking_group_id', $bookingGroupId);
        } else {
            $groupBuilder->where('reservations.order_id', $data['reservation']['order_id']);
        }
        $groupReservations = $groupBuilder->orderBy('reservations.id', 'ASC')->get()->getResultArray();
        $editableReservations = array_values(array_filter(
            $groupReservations,
            static fn (array $reservation): bool => (string) $reservation['status'] === 'Confirmed',
        ));
        if ($editableReservations === [] || count($editableReservations) !== count($groupReservations)) {
            return $this->response->setBody('<div class="alert alert-warning mb-0">Tanggal hanya dapat diubah saat seluruh kamar pada booking masih berstatus Confirmed.</div>');
        }

        $reservationIds = array_map('intval', array_column($editableReservations, 'id'));
        $roomIds = array_map('intval', array_column($editableReservations, 'room_id'));
        $stayEnd = $this->stayEndExclusive($checkIn, $checkOut);
        $nights = max(1, (int) (new DateTimeImmutable($checkIn))->diff(new DateTimeImmutable($checkOut))->days);
        $isTransit = $checkIn === $checkOut;
        $now = date('Y-m-d H:i:s');

        try {
            $db->transException(true);
            $db->transBegin();
            $lockedRoomIds = implode(',', array_map('intval', $roomIds));
            $db->query("SELECT id FROM rooms WHERE property_id = ? AND id IN ({$lockedRoomIds}) ORDER BY id FOR UPDATE", [PropertyContext::activeId()]);

            $conflicts = $this->findConflictingRooms($db, $roomIds, $checkIn, $stayEnd, $reservationIds);
            if ($conflicts !== []) {
                throw new \RuntimeException('DATE_CONFLICT:' . implode(', ', $conflicts));
            }

            $extraBedAllocations = $db->table('extra_bed_allocations')
                ->select('reservation_id, quantity')
                ->whereIn('reservation_id', $reservationIds)
                ->get()->getResultArray();
            $extraBedQuantity = array_sum(array_map(static fn (array $allocation): int => (int) $allocation['quantity'], $extraBedAllocations));
            if ($extraBedQuantity > 0) {
                (new ExtraBedService($db))->assertAvailable($checkIn, $stayEnd, $extraBedQuantity, $reservationIds);
            }

            $db->table('reservations')->whereIn('id', $reservationIds)->update([
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'updated_at' => $now,
            ]);
            if ($extraBedAllocations !== []) {
                $db->table('extra_bed_allocations')->whereIn('reservation_id', $reservationIds)->update([
                    'check_in_date' => $checkIn,
                    'check_out_date' => $stayEnd,
                    'updated_at' => $now,
                ]);
            }
            foreach ($editableReservations as $reservation) {
                $itemName = 'Room ' . $reservation['room_number'] . (PropertyPricing::active()['pricing_mode'] === 'room_monthly'
                    ? ' (' . PropertyPricing::wholeMonths($checkIn, $checkOut) . ' bulan)'
                    : ($isTransit ? ' (Transit)' : " ({$nights} malam)"));
                $db->table('invoice_items')
                    ->where('reservation_id', (int) $reservation['id'])
                    ->like('item_name', 'Room ' . $reservation['room_number'] . ' (', 'after')
                    ->update(['item_name' => $itemName]);
            }
            $db->transCommit();
        } catch (Throwable $error) {
            $db->transRollback();
            if (str_starts_with($error->getMessage(), 'DATE_CONFLICT:')) {
                return $this->response->setBody('<div class="alert alert-danger mb-0">Tanggal tidak dapat diubah. Kamar ' . esc(substr($error->getMessage(), strlen('DATE_CONFLICT:'))) . ' sudah terisi pada periode tersebut.</div>');
            }
            if (str_starts_with($error->getMessage(), 'EXTRA_BED_UNAVAILABLE:')) {
                return $this->response->setBody('<div class="alert alert-danger mb-0">' . esc(substr($error->getMessage(), strlen('EXTRA_BED_UNAVAILABLE:'))) . '</div>');
            }
            log_message('error', 'Update stay dates failed: {message}', ['message' => $error->getMessage()]);
            return $this->response->setBody('<div class="alert alert-danger mb-0">Tanggal booking gagal diubah. Silakan coba lagi.</div>');
        }

        $response = $this->show($id);
        $triggers = json_encode(['reservationChanged' => true, 'opsChanged' => true, 'dashboardChanged' => true]);
        return $response instanceof \CodeIgniter\HTTP\ResponseInterface
            ? $response->setHeader('HX-Trigger', $triggers)->setHeader('X-CSRF-TOKEN', csrf_hash())
            : $this->response->setHeader('HX-Trigger', $triggers)->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody($response);
    }

    public function extendModal(int $id)
    {
        $this->disableCaching();
        $data = $this->detailData($id);
        if ($data === null) {
            return $this->response->setStatusCode(404)->setBody('<div class="alert alert-danger mb-0">Reservasi tidak ditemukan.</div>');
        }

        $pricing = PropertyPricing::active();
        if ($pricing['pricing_mode'] !== 'room_monthly') {
            return $this->response->setStatusCode(400)->setBody('<div class="alert alert-danger mb-0">Fitur perpanjangan sewa hanya tersedia untuk properti sewa bulanan (Kos).</div>');
        }

        $reservation = $data['reservation'];
        if (! in_array($reservation['status'], ['Confirmed', 'Checked_In'], true)) {
            return $this->response->setStatusCode(400)->setBody('<div class="alert alert-warning mb-0">Hanya reservasi berstatus Confirmed atau Checked In yang dapat diperpanjang.</div>');
        }

        $db = db_connect();
        $room = $db->table('rooms')->where('id', (int) $reservation['room_id'])->get()->getRowArray();
        $monthlyRate = (float) (($room['price_per_night'] ?? 0) > 0 ? $room['price_per_night'] : $pricing['base_rate']);

        $startCheckIn = $reservation['check_out_date'];
        $startDate = new DateTimeImmutable($startCheckIn);
        $targetMonth = $startDate->modify('first day of this month')->modify('+1 month');
        $targetDay = min((int) $startDate->format('d'), (int) $targetMonth->format('t'));
        $defaultCheckOut = $targetMonth->setDate((int) $targetMonth->format('Y'), (int) $targetMonth->format('m'), $targetDay)->format('Y-m-d');

        return view('components/modals/extend_booking', [
            'reservation' => $reservation,
            'room' => $room,
            'monthlyRate' => $monthlyRate,
            'startCheckIn' => $startCheckIn,
            'defaultCheckOut' => $defaultCheckOut,
            'pricing' => $pricing,
        ]);
    }

    public function saveExtension(int $id)
    {
        $this->disableCaching();
        $data = $this->detailData($id);
        if ($data === null) {
            return $this->response->setStatusCode(404)->setBody('<div class="alert alert-danger mb-0">Reservasi tidak ditemukan.</div>');
        }

        $pricing = PropertyPricing::active();
        if ($pricing['pricing_mode'] !== 'room_monthly') {
            return $this->response->setStatusCode(400)->setBody('<div class="alert alert-danger mb-0">Fitur perpanjangan sewa hanya tersedia untuk properti sewa bulanan (Kos).</div>');
        }

        $original = $data['reservation'];
        if (! in_array($original['status'], ['Confirmed', 'Checked_In'], true)) {
            return $this->response->setStatusCode(400)->setBody('<div class="alert alert-warning mb-0">Hanya reservasi aktif yang dapat diperpanjang.</div>');
        }

        $durationSelect = (string) $this->request->getPost('duration_select');
        $durationMonths = (int) $this->request->getPost('duration_months');
        $customMonths = (int) $this->request->getPost('custom_duration_months');
        if ($durationSelect === 'custom' && $customMonths > 0) {
            $durationMonths = $customMonths;
        }
        if ($durationMonths < 1 || $durationMonths > 120) {
            $durationMonths = 1;
        }

        $source = trim((string) $this->request->getPost('source')) ?: 'Direct';
        if (! in_array($source, ['Direct', 'Online'], true)) {
            $source = 'Direct';
        }

        $confirmedTotalRaw = str_replace(['.', ','], '', (string) $this->request->getPost('confirmed_total'));
        $confirmedTotal = (float) $confirmedTotalRaw;
        $db = db_connect();
        $room = $db->table('rooms')->where('id', (int) $original['room_id'])->get()->getRowArray();
        $monthlyRate = (float) (($room['price_per_night'] ?? 0) > 0 ? $room['price_per_night'] : $pricing['base_rate']);
        if ($confirmedTotal <= 0) {
            $confirmedTotal = $monthlyRate * $durationMonths;
        }

        $paymentType = (string) $this->request->getPost('payment_type');
        $paymentAmountRaw = str_replace(['.', ','], '', (string) $this->request->getPost('payment_amount'));
        $paymentAmount = (float) $paymentAmountRaw;
        $paymentMethod = (string) $this->request->getPost('payment_method') ?: 'Transfer';
        if (! in_array($paymentMethod, ['Cash', 'Transfer'], true)) {
            $paymentMethod = 'Transfer';
        }
        $paymentDate = $this->validDateFilter((string) $this->request->getPost('payment_date')) ?: date('Y-m-d');
        $importantNote = trim((string) $this->request->getPost('important_note'));
        if ($importantNote === '') {
            $importantNote = 'Perpanjangan sewa dari reservasi #' . $original['order_id'];
        }

        $checkIn = $original['check_out_date'];
        $startDate = new DateTimeImmutable($checkIn);
        $targetMonth = $startDate->modify('first day of this month')->modify('+' . $durationMonths . ' month');
        $targetDay = min((int) $startDate->format('d'), (int) $targetMonth->format('t'));
        $checkOut = $targetMonth->setDate((int) $targetMonth->format('Y'), (int) $targetMonth->format('m'), $targetDay)->format('Y-m-d');

        // Check conflicts (exclude original reservation)
        $conflictingRooms = $this->findConflictingRooms($db, [(int) $original['room_id']], $checkIn, $checkOut, [(int) $original['id']]);
        if ($conflictingRooms !== []) {
            return $this->response->setBody('<div class="alert alert-danger mb-0">Kamar ' . implode(', ', $conflictingRooms) . ' sudah memiliki booking lain pada rentang ' . date('d/m/Y', strtotime($checkIn)) . ' s/d ' . date('d/m/Y', strtotime($checkOut)) . '.</div>');
        }

        $now = date('Y-m-d H:i:s');
        $orderId = $this->generateBookingGroupId();

        try {
            $db->transException(true);
            $db->transStart();

            $db->table('reservations')->insert([
                'property_id' => PropertyContext::activeId(),
                'order_id' => $orderId,
                'booking_group_id' => $orderId,
                'guest_id' => (int) $original['guest_id'],
                'room_id' => (int) $original['room_id'],
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'status' => 'Confirmed',
                'source' => $source,
                'guest_count' => (int) ($original['guest_count'] ?: 1),
                'total_amount' => $confirmedTotal,
                'important_note' => $importantNote,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $newReservationId = (int) $db->insertID();

            // Invoice item
            $db->table('invoice_items')->insert([
                'reservation_id' => $newReservationId,
                'order_id' => $orderId,
                'item_name' => 'Room ' . $original['room_number'] . " ({$durationMonths} bulan - Extend)",
                'amount' => $confirmedTotal,
                'created_at' => $now,
            ]);

            // Payment
            if ($paymentType === 'full' && $confirmedTotal > 0) {
                $db->table('payments')->insert([
                    'reservation_id' => $newReservationId,
                    'order_id' => $orderId,
                    'amount' => $confirmedTotal,
                    'payment_date' => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'notes' => 'Pelunasan perpanjangan sewa (' . $durationMonths . ' bulan)',
                    'type' => 'Completed',
                    'status' => 'Paid',
                    'created_at' => $now,
                ]);
            } elseif ($paymentType === 'dp' && $paymentAmount > 0) {
                $db->table('payments')->insert([
                    'reservation_id' => $newReservationId,
                    'order_id' => $orderId,
                    'amount' => min($paymentAmount, $confirmedTotal),
                    'payment_date' => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'notes' => 'DP perpanjangan sewa (' . $durationMonths . ' bulan)',
                    'type' => 'DP',
                    'status' => 'Paid',
                    'created_at' => $now,
                ]);
            }

            $db->transCommit();
        } catch (Throwable $error) {
            $db->transRollback();
            log_message('error', 'Extend reservation failed: {message}', ['message' => $error->getMessage()]);
            return $this->response->setBody('<div class="alert alert-danger mb-0">Gagal menyimpan perpanjangan sewa: ' . esc($error->getMessage()) . '</div>');
        }

        $reservationTrigger = [
            'reservationChanged' => true,
            'dashboardChanged' => true,
            'closeModal' => true,
        ];

        return $this->response
            ->setHeader('HX-Trigger', json_encode($reservationTrigger))
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setBody('<div class="alert alert-success mb-0">Perpanjangan sewa ' . esc($orderId) . ' (' . $durationMonths . ' bulan) berhasil disimpan!</div>');
    }

    public function updateImportantNote(int $id)
    {
        $importantNote = trim((string) $this->request->getPost('important_note'));
        if (mb_strlen($importantNote) > 2000) {
            return $this->response->setStatusCode(422)->setBody('<div class="alert alert-danger mb-0">Catatan penting maksimal 2.000 karakter.</div>');
        }

        $db = db_connect();
        $reservation = $db->table('reservations')
            ->select('id, order_id, booking_group_id')
            ->where('property_id', PropertyContext::activeId())
            ->where('id', $id)
            ->get()
            ->getRowArray();
        if ($reservation === null) {
            return $this->response->setStatusCode(404)->setBody('<div class="alert alert-danger mb-0">Reservasi tidak ditemukan.</div>');
        }

        $groupBuilder = $db->table('reservations')->select('id')->where('property_id', PropertyContext::activeId());
        $bookingGroupId = trim((string) ($reservation['booking_group_id'] ?? ''));
        if ($bookingGroupId !== '') {
            $groupBuilder->where('booking_group_id', $bookingGroupId);
        } else {
            $groupBuilder->where('order_id', $reservation['order_id']);
        }
        $reservationIds = array_map('intval', array_column($groupBuilder->get()->getResultArray(), 'id'));
        if ($reservationIds === []) {
            $reservationIds = [$id];
        }

        try {
            $db->table('reservations')->whereIn('id', $reservationIds)->update([
                'important_note' => $importantNote === '' ? null : $importantNote,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $error) {
            log_message('error', 'Update important note failed for reservation {id}: {message}', [
                'id' => $id,
                'message' => $error->getMessage(),
            ]);
            return $this->response->setStatusCode(500)->setBody('<div class="alert alert-danger mb-0">Catatan booking gagal disimpan. Silakan coba lagi.</div>');
        }

        $response = $this->show($id);
        $triggers = json_encode(['reservationChanged' => true]);
        return $response instanceof \CodeIgniter\HTTP\ResponseInterface
            ? $response->setHeader('HX-Trigger', $triggers)->setHeader('X-CSRF-TOKEN', csrf_hash())
            : $this->response->setHeader('HX-Trigger', $triggers)->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody($response);
    }

    public function updatePaymentDate(int $id)
    {
        $paymentDate = $this->validDateFilter((string) $this->request->getPost('payment_date'));
        if ($paymentDate === null) {
            return $this->response->setStatusCode(422)->setBody('<div class="alert alert-danger mb-0">Tanggal pembayaran wajib diisi.</div>');
        }

        $db = db_connect();
        $payment = $db->table('payments')->select('payments.*')->join('reservations', 'reservations.id = payments.reservation_id')->where('payments.id', $id)->where('reservations.property_id', PropertyContext::activeId())->get()->getRowArray();
        if ($payment === null) {
            return $this->response->setStatusCode(404)->setBody('Pembayaran tidak ditemukan.');
        }

        $db->table('payments')->where('id', $id)->update([
            'payment_date' => $paymentDate,
            'status' => 'Paid',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->show((int) $payment['reservation_id']);
        return $response instanceof \CodeIgniter\HTTP\ResponseInterface
            ? $response->setHeader('HX-Trigger', json_encode(['reservationChanged' => true]))->setHeader('X-CSRF-TOKEN', csrf_hash())
            : $this->response->setHeader('HX-Trigger', json_encode(['reservationChanged' => true]))->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody($response);
    }

    public function paymentProof(int $id)
    {
        try {
            $proofScope = \App\Libraries\FinanceProofScope::current('payment_proof');
        } catch (\RuntimeException) {
            return $this->response->setStatusCode(404)->setBody('Bukti tidak ditemukan.');
        }
        $propertyIds = $proofScope['propertyIds'];

        $payment = db_connect()->table('payments')
            ->select('payments.*, rooms.floor AS guesthouse')
            ->join('reservations', 'reservations.id = payments.reservation_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->whereIn('reservations.property_id', $propertyIds)
            ->where('payments.id', $id)
            ->get()
            ->getRowArray();
        if ($payment === null || empty($payment['proof_path'])) {
            return $this->response->setStatusCode(404)->setBody('Bukti pembayaran tidak ditemukan.');
        }

        $ownerGuesthouse = $proofScope['ownerGuesthouse'];
        if ($ownerGuesthouse !== null && (int) $payment['guesthouse'] !== $ownerGuesthouse) {
            return $this->response->setStatusCode(403)->setBody('Bukti pembayaran bukan milik guesthouse Anda.');
        }

        $uploadsRoot = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads');
        $absolutePath = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $payment['proof_path']));
        if ($uploadsRoot === false || $absolutePath === false || ! str_starts_with($absolutePath, $uploadsRoot . DIRECTORY_SEPARATOR) || ! is_file($absolutePath)) {
            return $this->response->setStatusCode(404)->setBody('File bukti pembayaran tidak ditemukan.');
        }

        $safeName = str_replace(["\r", "\n", '"'], '', (string) ($payment['proof_original_name'] ?? 'bukti-transfer.jpg'));
        return $this->response
            ->setHeader('Content-Type', (string) ($payment['proof_mime_type'] ?? 'image/jpeg'))
            ->setHeader('Content-Length', (string) filesize($absolutePath))
            ->setHeader('Content-Disposition', 'inline; filename="' . $safeName . '"')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0, must-revalidate')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($absolutePath));
    }

    public function uploadPaymentProof(int $id)
    {
        $db = db_connect();
        $payment = $db->table('payments')
            ->select('payments.id, payments.reservation_id, payments.proof_path, payments.payment_method, payments.amount, rooms.floor AS guesthouse')
            ->join('reservations', 'reservations.id = payments.reservation_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->where('reservations.property_id', PropertyContext::activeId())
            ->where('payments.id', $id)
            ->get()
            ->getRowArray();
        if ($payment === null) {
            return $this->response->setStatusCode(404)->setBody('<div class="alert alert-danger mb-0">Pembayaran tidak ditemukan.</div>');
        }

        $ownerGuesthouse = RoleAccess::ownerGuesthouse((string) session('role'));
        if ($ownerGuesthouse !== null && (int) $payment['guesthouse'] !== $ownerGuesthouse) {
            return $this->response->setStatusCode(403)->setBody('<div class="alert alert-danger mb-0">Pembayaran bukan milik guesthouse Anda.</div>');
        }

        try {
            $proof = $this->storePaymentProofUpload();
            if ($proof === null) {
                return $this->response->setStatusCode(422)->setBody('<div class="alert alert-danger mb-0">Pilih file bukti pembayaran terlebih dahulu.</div>');
            }
        } catch (\RuntimeException $error) {
            return $this->response->setStatusCode(422)->setBody('<div class="alert alert-danger mb-0">' . esc($error->getMessage()) . '</div>');
        }

        try {
            $db->table('payments')->where('id', $id)->update([
                'proof_path' => $proof['relative_path'],
                'proof_original_name' => $proof['original_name'],
                'proof_mime_type' => $proof['mime_type'],
                'proof_size' => $proof['file_size'],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $error) {
            $this->removeStoredImage($proof);
            log_message('error', 'Upload payment proof failed for payment {id}: {message}', [
                'id' => $id,
                'message' => $error->getMessage(),
            ]);
            return $this->response->setStatusCode(500)->setBody('<div class="alert alert-danger mb-0">Bukti pembayaran gagal disimpan. Silakan coba lagi.</div>');
        }

        $this->removeStoredImageByRelativePath((string) ($payment['proof_path'] ?? ''));
        $response = $this->show((int) $payment['reservation_id']);
        $headers = json_encode(['reservationChanged' => true]);
        return $response instanceof \CodeIgniter\HTTP\ResponseInterface
            ? $response->setHeader('HX-Trigger', $headers)->setHeader('X-CSRF-TOKEN', csrf_hash())
            : $this->response->setHeader('HX-Trigger', $headers)->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody($response);
    }

    public function ktpPhoto(int $id)
    {
        $reservation = db_connect()->table('reservations')
            ->select('guests.ktp_photo_path, guests.ktp_photo_original_name, guests.ktp_photo_mime_type')
            ->join('guests', 'guests.id = reservations.guest_id')
            ->where('reservations.property_id', PropertyContext::activeId())
            ->where('reservations.id', $id)
            ->get()
            ->getRowArray();
        if ($reservation === null || empty($reservation['ktp_photo_path'])) {
            return $this->response->setStatusCode(404)->setBody('Foto KTP tidak ditemukan.');
        }

        $uploadsRoot = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads');
        $absolutePath = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $reservation['ktp_photo_path']));
        if ($uploadsRoot === false || $absolutePath === false || ! str_starts_with($absolutePath, $uploadsRoot . DIRECTORY_SEPARATOR) || ! is_file($absolutePath)) {
            return $this->response->setStatusCode(404)->setBody('File foto KTP tidak ditemukan.');
        }

        $safeName = str_replace(["\r", "\n", '"'], '', (string) ($reservation['ktp_photo_original_name'] ?? 'foto-ktp.jpg'));
        return $this->response
            ->setHeader('Content-Type', (string) ($reservation['ktp_photo_mime_type'] ?? 'image/jpeg'))
            ->setHeader('Content-Length', (string) filesize($absolutePath))
            ->setHeader('Content-Disposition', 'inline; filename="' . $safeName . '"')
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($absolutePath));
    }

    public function uploadKtpPhoto(int $id)
    {
        $db = db_connect();
        $reservation = $db->table('reservations')
            ->select('reservations.id, reservations.guest_id, guests.ktp_photo_path')
            ->join('guests', 'guests.id = reservations.guest_id')
            ->where('reservations.property_id', PropertyContext::activeId())
            ->where('reservations.id', $id)
            ->get()
            ->getRowArray();

        if ($reservation === null) {
            return $this->response->setStatusCode(404)->setBody('<div class="alert alert-danger mb-0">Reservasi tidak ditemukan.</div>');
        }

        try {
            $photo = $this->storeKtpPhoto();
            if ($photo === null) {
                return $this->response->setStatusCode(422)->setBody('<div class="alert alert-danger mb-0">Pilih foto KTP terlebih dahulu.</div>');
            }
        } catch (\RuntimeException $error) {
            return $this->response->setStatusCode(422)->setBody('<div class="alert alert-danger mb-0">' . esc($error->getMessage()) . '</div>');
        }

        try {
            $db->table('guests')->where('id', (int) $reservation['guest_id'])->update([
                'ktp_photo_path' => $photo['relative_path'],
                'ktp_photo_original_name' => $photo['original_name'],
                'ktp_photo_mime_type' => $photo['mime_type'],
                'ktp_photo_size' => $photo['file_size'],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $error) {
            $this->removeStoredImage($photo);
            log_message('error', 'Update guest KTP photo failed for reservation {id}: {message}', [
                'id' => $id,
                'message' => $error->getMessage(),
            ]);

            return $this->response->setStatusCode(500)->setBody('<div class="alert alert-danger mb-0">Foto KTP gagal disimpan. Silakan coba lagi.</div>');
        }

        $this->removeStoredImageByRelativePath((string) ($reservation['ktp_photo_path'] ?? ''));
        $response = $this->show($id);

        return $response instanceof \CodeIgniter\HTTP\ResponseInterface
            ? $response->setHeader('X-CSRF-TOKEN', csrf_hash())
            : $this->response->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody($response);
    }

    public function toggleReconciliation(int $id)
    {
        $db = db_connect();
        $reservation = $db->table('reservations')->where('id', $id)->where('property_id', PropertyContext::activeId())->get()->getRowArray();
        if ($reservation === null) {
            return $this->response->setStatusCode(404)->setBody('Reservasi tidak ditemukan.');
        }

        $storedBookingGroupId = trim((string) ($reservation['booking_group_id'] ?? ''));
        $isReconciled = (int) ($reservation['is_reconciled'] ?? 0) !== 1;
        $builder = $db->table('reservations')->where('property_id', PropertyContext::activeId());
        if ($storedBookingGroupId !== '') {
            $builder->where('booking_group_id', $storedBookingGroupId);
        } elseif (! empty($reservation['order_id'])) {
            $builder->where('order_id', $reservation['order_id']);
        } else {
            $builder->where('id', $id);
        }

        $builder->update([
            'is_reconciled' => $isReconciled ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->show($id);
        $triggers = json_encode(['reservationChanged' => true, 'reconciliationChanged' => true]);
        return $response instanceof \CodeIgniter\HTTP\ResponseInterface
            ? $response->setHeader('HX-Trigger', $triggers)->setHeader('X-CSRF-TOKEN', csrf_hash())
            : $this->response->setHeader('HX-Trigger', $triggers)->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody($response);
    }

    public function changeStatus(int $id)
    {
        $status = (string) $this->request->getPost('status');
        $allowed = ['Checked_In', 'Checked_Out', 'Cancelled', 'No_Show'];
        if (! in_array($status, $allowed, true)) {
            return $this->response->setStatusCode(422)->setBody('Status tidak valid.');
        }

        $db = db_connect();
        $reservation = $db->table('reservations')->where('id', $id)->where('property_id', PropertyContext::activeId())->get()->getRowArray();
        if ($reservation === null) {
            return $this->response->setStatusCode(404)->setBody('Reservasi tidak ditemukan.');
        }

        $isWholeHome = PropertyPricing::active()['pricing_mode'] === 'whole_home_nightly';

        if ($status === 'Checked_In') {
            $today = (new DateTimeImmutable('now', new \DateTimeZone(config('App')->appTimezone)))->format('Y-m-d');
            if ((string) $reservation['check_in_date'] > $today) {
                return $this->response
                    ->setStatusCode(422)
                    ->setHeader('X-CSRF-TOKEN', csrf_hash())
                    ->setBody('<div class="alert alert-warning mb-0">Check In baru tersedia mulai tanggal ' . date('d/m/Y', strtotime((string) $reservation['check_in_date'])) . '.</div>');
            }
            if (! in_array((string) $reservation['status'], ['Confirmed', 'Checked_Out'], true)) {
                return $this->response->setStatusCode(422)->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody('<div class="alert alert-warning mb-0">Reservasi ini tidak dapat dikembalikan ke status Check-in dari status saat ini.</div>');
            }
            $conflictingRooms = $this->findConflictingRooms(
                $db,
                [(int) $reservation['room_id']],
                (string) $reservation['check_in_date'],
                $this->stayEndExclusive((string) $reservation['check_in_date'], (string) $reservation['check_out_date']),
                [$id],
            );
            if ($conflictingRooms !== []) {
                return $this->response->setStatusCode(422)->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody('<div class="alert alert-danger mb-0">Room ' . esc(implode(', ', $conflictingRooms)) . ' sudah dipakai reservasi lain pada periode ini.</div>');
            }
        }

        if ($status === 'Checked_Out') {
            $outstanding = $this->bookingOutstanding($db, $reservation);
            if ($outstanding > 0) {
                return $this->response
                    ->setStatusCode(422)
                    ->setHeader('X-CSRF-TOKEN', csrf_hash())
                    ->setBody('<div class="alert alert-danger mb-0">Check Out tidak dapat dilakukan. Booking masih memiliki outstanding sebesar Rp' . number_format($outstanding, 0, ',', '.') . '. Silakan lunasi tagihan terlebih dahulu.</div>');
            }
        }

        if ($status === 'No_Show') {
            if ((string) $reservation['status'] !== 'Confirmed') {
                return $this->response->setStatusCode(422)->setHeader('X-CSRF-TOKEN', csrf_hash())->setBody('<div class="alert alert-warning mb-0">Hanya reservasi berstatus Terkonfirmasi yang dapat ditandai No-Show.</div>');
            }
            $bookingGroupId = trim((string) ($reservation['booking_group_id'] ?? ''));
            $groupBuilder = $db->table('reservations')->select('id, room_id')->where('property_id', PropertyContext::activeId());
            if ($bookingGroupId !== '') {
                $groupBuilder->where('booking_group_id', $bookingGroupId);
            } elseif (! empty($reservation['order_id'])) {
                $groupBuilder->where('order_id', $reservation['order_id']);
            } else {
                $groupBuilder->where('id', $id);
            }
            $groupReservations = $groupBuilder->get()->getResultArray();
            $reservationIds = array_map('intval', array_column($groupReservations, 'id'));
            $roomIds = array_map('intval', array_column($groupReservations, 'room_id'));
            $now = date('Y-m-d H:i:s');
            $db->transStart();
            if ($reservationIds !== []) {
                $db->table('reservations')->whereIn('id', $reservationIds)->update([
                    'status' => 'No_Show',
                    'updated_at' => $now,
                ]);
                $db->table('rooms')->where('property_id', PropertyContext::activeId())->whereIn('id', $roomIds)->update([
                    'status' => 'Available',
                    'updated_at' => $now,
                ]);
            }
            $db->transComplete();
            if (! $db->transStatus()) {
                return $this->response->setStatusCode(500)->setBody('Reservasi gagal ditandai No-Show. Silakan coba lagi.');
            }

            return $this->response
                ->setHeader('HX-Trigger', json_encode(['reservationChanged' => true, 'opsChanged' => true, 'dashboardChanged' => true, 'closeModal' => true]))
                ->setHeader('X-CSRF-TOKEN', csrf_hash())
                ->setBody('<div class="alert alert-success mb-0">Booking berhasil ditandai No-Show. Pembayaran yang sudah masuk tetap menjadi pendapatan.</div>');
        }

        $db->transStart();
        if ($status === 'Cancelled') {
            $cancelScope = $this->request->getPost('cancel_scope') === 'room' ? 'room' : 'booking';
            if ($isWholeHome) {
                $cancelScope = 'booking';
            }
            $bookingGroupId = trim((string) ($reservation['booking_group_id'] ?? ''));
            $groupBuilder = $db->table('reservations')->select('id, room_id, status')->where('property_id', PropertyContext::activeId());
            if ($cancelScope === 'room') {
                $groupBuilder->where('id', $id);
            } elseif ($bookingGroupId !== '') {
                $groupBuilder->where('booking_group_id', $bookingGroupId);
            } elseif (! empty($reservation['order_id'])) {
                $groupBuilder->where('order_id', $reservation['order_id']);
            } else {
                $groupBuilder->where('id', $id);
            }
            $groupReservations = $groupBuilder->get()->getResultArray();
            $reservationIds = array_map('intval', array_column($groupReservations, 'id'));
            $roomIds = array_map('intval', array_column($groupReservations, 'room_id'));

            if ($reservationIds !== []) {
                $db->table('reservations')->whereIn('id', $reservationIds)->update([
                    'status' => 'Cancelled',
                    'cancelled_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $db->table('rooms')->where('property_id', PropertyContext::activeId())->whereIn('id', $roomIds)->update([
                    'status' => 'Available',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $cancelledPayments = $db->table('payments')
                    ->select('id, type, notes')
                    ->whereIn('reservation_id', $reservationIds)
                    ->where('status', 'Paid')
                    ->get()
                    ->getResultArray();
                foreach ($cancelledPayments as $payment) {
                    $existingNotes = trim((string) ($payment['notes'] ?? ''));
                    if (($payment['type'] ?? '') === 'DP') {
                        $forfeitNote = 'DP hangus karena pembatalan reservasi pada ' . date('d/m/Y') . '.';
                        $db->table('payments')->where('id', $payment['id'])->update([
                            'notes' => trim($existingNotes === '' ? $forfeitNote : $existingNotes . ' ' . $forfeitNote),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                        continue;
                    }
                    $refundNote = 'Kredit/refund pending karena pembatalan reservasi pada ' . date('d/m/Y') . '.';
                    $db->table('payments')->where('id', $payment['id'])->update([
                        'status' => 'Refund_Pending',
                        'notes' => trim($existingNotes === '' ? $refundNote : $existingNotes . ' ' . $refundNote),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $db->transComplete();
            if (! $db->transStatus()) {
                return $this->response->setStatusCode(500)->setBody('Reservasi gagal dibatalkan. Silakan coba lagi.');
            }

            $rooms = $db->table('rooms')->select('room_number')->whereIn('id', $roomIds)->orderBy('room_number', 'ASC')->get()->getResultArray();
            $roomNumbers = array_values(array_filter(array_map(static fn (array $room): string => trim((string) $room['room_number']), $rooms)));
            $triggers = json_encode([
                'reservationChanged' => true,
                'opsChanged' => true,
                'dashboardChanged' => true,
                'closeModal' => true,
                'reservationCancelled' => ['rooms' => $roomNumbers, 'scope' => $cancelScope],
            ]);

            return $this->response
                ->setHeader('HX-Trigger', $triggers)
                ->setHeader('X-CSRF-TOKEN', csrf_hash())
                ->setBody('<div class="alert alert-success mb-0">Booking berhasil dibatalkan.</div>');
        }

        $roomStatus = $status === 'Checked_In' ? 'Occupied' : 'Dirty';
        if ($status === 'Checked_In') {
            $this->consumeBookingGroupAmenities($db, $reservation);
        }
        $groupIds = [$id];
        $groupRoomIds = [(int) $reservation['room_id']];
        if ($isWholeHome) {
            $groupRows = $db->table('reservations')->select('id, room_id')
                ->where('property_id', PropertyContext::activeId())
                ->where('booking_group_id', $reservation['booking_group_id'])->get()->getResultArray();
            $groupIds = array_map('intval', array_column($groupRows, 'id'));
            $groupRoomIds = array_map('intval', array_column($groupRows, 'room_id'));
        }
        $db->table('reservations')->where('property_id', PropertyContext::activeId())->whereIn('id', $groupIds)->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        $db->table('rooms')->where('property_id', PropertyContext::activeId())->whereIn('id', $groupRoomIds)->update(['status' => $roomStatus, 'updated_at' => date('Y-m-d H:i:s')]);
        $db->transComplete();

        return $this->response
            ->setHeader('HX-Trigger', json_encode(['reservationChanged' => true, 'opsChanged' => true, 'dashboardChanged' => true, 'closeModal' => true]))
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setBody('<div class="alert alert-success mb-0">Status reservasi diperbarui.</div>');
    }

    /**
     * Consume the standard amenity set once for each reservation room at check-in.
     * The stock-movement reference makes this safe if a check-in request is retried.
     *
     * @param array<string, mixed> $reservation
     */
    private function consumeBookingGroupAmenities($db, array $reservation): void
    {
        $required = ['sabun' => 1, 'shampoo' => 1, 'sikat gigi' => 2];
        $bookingGroupId = trim((string) ($reservation['booking_group_id'] ?? ''));
        $groupReservations = $db->table('reservations')
            ->select('id, order_id')
            ->where('property_id', PropertyContext::activeId())
            ->where($bookingGroupId !== '' ? 'booking_group_id' : 'order_id', $bookingGroupId !== '' ? $bookingGroupId : $reservation['order_id'])
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $reservationIds = array_map('intval', array_column($groupReservations, 'id'));
        $consumedRows = $reservationIds === [] ? [] : $db->table('inventory_stock_movements')
            ->select('reference_id')
            ->where('reference_type', 'ReservationCheckIn')
            ->whereIn('reference_id', $reservationIds)
            ->groupBy('reference_id')
            ->get()->getResultArray();
        $consumedReservationIds = array_flip(array_map('intval', array_column($consumedRows, 'reference_id')));
        $pendingReservations = array_values(array_filter(
            $groupReservations,
            static fn (array $groupReservation): bool => ! isset($consumedReservationIds[(int) $groupReservation['id']])
        ));
        if ($pendingReservations === []) {
            return;
        }

        $items = $db->table('inventory_items')
            ->where('property_id', PropertyContext::activeId())
            ->where('is_active', 1)
            ->where('item_type', 'Amenity')
            ->get()->getResultArray();
        $itemsByName = [];
        foreach ($items as $item) {
            $name = strtolower(trim((string) $item['name']));
            $category = strtolower(trim((string) $item['category']));
            if ($category === 'amenities' && isset($required[$name]) && ! isset($itemsByName[$name])) {
                $itemsByName[$name] = $item;
            }
        }

        $roomCount = count($pendingReservations);
        $now = date('Y-m-d H:i:s');
        foreach ($required as $name => $perRoomQuantity) {
            if (! isset($itemsByName[$name])) {
                log_message('warning', 'Amenity "{item}" tidak ditemukan ketika check-in reservasi {reservation}.', ['item' => $name, 'reservation' => $reservation['id']]);
                continue;
            }

            $item = $itemsByName[$name];
            $currentStock = max(0, (int) $item['current_stock']);
            $requestedQuantity = $perRoomQuantity * $roomCount;
            $consumed = min($requestedQuantity, $currentStock);
            $balance = $currentStock - $consumed;
            $db->table('inventory_items')->where('id', $item['id'])->where('property_id', PropertyContext::activeId())->update([
                'current_stock' => $balance,
                'updated_at' => $now,
            ]);
            $remainingToAllocate = $consumed;
            $runningBalance = $currentStock;
            foreach ($pendingReservations as $groupReservation) {
                $quantityForRoom = min($perRoomQuantity, $remainingToAllocate);
                $remainingToAllocate -= $quantityForRoom;
                $runningBalance -= $quantityForRoom;
                $db->table('inventory_stock_movements')->insert([
                    'inventory_item_id' => $item['id'],
                    'movement_type' => 'CheckInAmenity',
                    'quantity_change' => -$quantityForRoom,
                    'balance_after' => $runningBalance,
                    'reference_type' => 'ReservationCheckIn',
                    'reference_id' => (int) $groupReservation['id'],
                    'notes' => 'Amenity check-in untuk reservasi ' . $groupReservation['order_id'] . ': ' . $name . ' ' . $quantityForRoom . ' ' . $item['unit'],
                    'created_by_user_id' => (int) session('userId') ?: null,
                    'created_at' => $now,
                ]);
            }
        }
    }

    private function detailData(int $id): ?array
    {
        $db = db_connect();
        $paymentTotals = $db->table('payments')->select('reservation_id, SUM(amount) AS paid_amount')->where('status', 'Paid')->groupBy('reservation_id')->getCompiledSelect();
        $reservation = $db->table('reservations')
            ->select('reservations.*, guests.full_name AS guest_name, guests.email, guests.phone, guests.origin, guests.notes AS guest_notes, guests.ktp_photo_path, guests.ktp_photo_original_name, guests.ktp_photo_size, rooms.room_number, rooms.room_type, rooms.floor AS guesthouse, COALESCE(paid.paid_amount, 0) AS paid_amount', false)
            ->join('guests', 'guests.id = reservations.guest_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->join("({$paymentTotals}) paid", 'paid.reservation_id = reservations.id', 'left', false)
            ->where('reservations.property_id', PropertyContext::activeId())
            ->where('reservations.id', $id)->get()->getRowArray();

        if ($reservation === null) {
            return null;
        }

        $bookingGroupId = $reservation['booking_group_id'] ?: $reservation['order_id'];
        $groupReservations = $db->table('reservations')
            ->select('reservations.id, reservations.order_id, reservations.total_amount, reservations.status, reservations.check_in_date, reservations.check_out_date, reservations.source, reservations.guest_count, reservations.created_at, rooms.room_number, rooms.room_type, rooms.floor AS guesthouse, COALESCE(paid.paid_amount, 0) AS paid_amount', false)
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->join("({$paymentTotals}) paid", 'paid.reservation_id = reservations.id', 'left', false)
            ->where('reservations.property_id', PropertyContext::activeId())
            ->where($reservation['booking_group_id'] ? 'reservations.booking_group_id' : 'reservations.order_id', $bookingGroupId)
            ->orderBy('rooms.room_number', 'ASC')
            ->get()
            ->getResultArray();

        if ($groupReservations === []) {
            $groupReservations = [$reservation];
        }
        $groupReservationIds = array_map('intval', array_column($groupReservations, 'id'));
        $activeGroupReservations = array_values(array_filter(
            $groupReservations,
            static fn (array $groupReservation): bool => (string) $groupReservation['status'] !== 'Cancelled',
        ));
        $activeGroupReservationIds = array_map('intval', array_column($activeGroupReservations, 'id'));

        return [
            'reservation' => $reservation,
            'bookingGroupId' => $bookingGroupId,
            'groupReservations' => $groupReservations,
            'groupTotal' => array_sum(array_map(static fn (array $row): float => (float) $row['total_amount'], $activeGroupReservations)),
            'groupPaid' => array_sum(array_map(static fn (array $row): float => (float) $row['paid_amount'], $activeGroupReservations)),
            'payments' => $db->table('payments')->whereIn('reservation_id', $groupReservationIds)->orderBy('payment_date', 'DESC')->orderBy('id', 'DESC')->get()->getResultArray(),
            'items' => $activeGroupReservationIds === [] ? [] : $db->table('invoice_items')->whereIn('reservation_id', $activeGroupReservationIds)->orderBy('reservation_id', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray(),
        ];
    }

    /**
     * Build a print-friendly invoice model from the same live reservation data
     * used by the detail modal.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function invoiceData(array $data, bool $isCompact = false): array
    {
        $reservation = $data['reservation'];
        $activeReservations = array_values(array_filter(
            $data['groupReservations'],
            static fn (array $row): bool => (string) $row['status'] !== 'Cancelled',
        ));

        $roomItems = [];
        $additionalRows = [];
        foreach ($data['items'] as $item) {
            if (str_starts_with((string) $item['item_name'], 'Room ')) {
                $roomItems[(int) $item['reservation_id']] = $item;
                continue;
            }

            // Penyesuaian harga khusus tetap masuk ke total invoice, tetapi
            // tidak dicetak sebagai baris biaya tambahan untuk tamu.
            if ((string) $item['item_name'] === 'Penyesuaian harga khusus') {
                continue;
            }

            $roomNumber = '';
            foreach ($activeReservations as $groupReservation) {
                if ((int) $groupReservation['id'] === (int) $item['reservation_id']) {
                    $roomNumber = 'Room ' . $groupReservation['room_number'];
                    break;
                }
            }

            $additionalRows[] = [
                'date' => substr((string) ($item['created_at'] ?? $reservation['created_at'] ?? ''), 0, 10),
                'type' => (string) $item['item_name'],
                'details' => $roomNumber,
                'amount' => (float) $item['amount'],
            ];
        }

        $pricingMode = PropertyPricing::active()['pricing_mode'];
        $roomRows = [];
        $roomTotal = 0.0;
        foreach ($activeReservations as $groupReservation) {
            $roomItem = $roomItems[(int) $groupReservation['id']] ?? null;
            $roomAmount = (float) ($roomItem['amount'] ?? $groupReservation['total_amount']);
            $roomTotal += $roomAmount;

            $checkIn = new DateTimeImmutable((string) $groupReservation['check_in_date']);
            $checkOut = new DateTimeImmutable((string) $groupReservation['check_out_date']);
            $isTransit = $checkIn->format('Y-m-d') === $checkOut->format('Y-m-d');
            if ($pricingMode === 'room_monthly') {
                $roomRows[] = [
                    'date' => $checkIn->format('Y-m-d'),
                    'room' => 'Room ' . $groupReservation['room_number'] . ' - ' . $groupReservation['room_type']
                        . ' (' . PropertyPricing::wholeMonths($checkIn->format('Y-m-d'), $checkOut->format('Y-m-d')) . ' bulan)',
                    'amount' => $roomAmount,
                ];
                continue;
            }
            $dates = [];
            if ($isTransit) {
                $dates[] = $checkIn;
            } else {
                for ($date = $checkIn; $date < $checkOut; $date = $date->modify('+1 day')) {
                    $dates[] = $date;
                }
            }

            if ($dates === []) {
                $dates[] = $checkIn;
            }

            $dailyAmount = $roomAmount / count($dates);
            foreach ($dates as $index => $date) {
                $amount = $index === count($dates) - 1
                    ? $roomAmount - ($dailyAmount * (count($dates) - 1))
                    : $dailyAmount;
                $roomRows[] = [
                    'date' => $date->format('Y-m-d'),
                    'room' => 'Room ' . $groupReservation['room_number'] . ' - ' . $groupReservation['room_type'] . ($isTransit ? ' (Transit)' : ''),
                    'amount' => $amount,
                ];
            }
        }

        if ($pricingMode === 'whole_home_nightly' && $activeReservations !== []) {
            $firstStay = $activeReservations[0];
            $start = new DateTimeImmutable((string) $firstStay['check_in_date']);
            $end = new DateTimeImmutable((string) $firstStay['check_out_date']);
            $dates = [];
            for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
                $dates[] = $day;
            }
            if ($dates === []) {
                $dates[] = $start;
            }
            $roomRows = [];
            foreach ($dates as $index => $date) {
                $roomRows[] = [
                    'date' => $date->format('Y-m-d'),
                    'room' => 'Homestay Mitra (satu rumah, 3 kamar)',
                    'amount' => $index === count($dates) - 1
                        ? $roomTotal - (round($roomTotal / count($dates), 2) * (count($dates) - 1))
                        : round($roomTotal / count($dates), 2),
                ];
            }
        }

        $logoPath = FCPATH . 'images/homiq-logo.png';
        $logoData = '';
        if (PropertyContext::activeId() === PropertyContext::HOMIQ_ID && is_file($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath));
        }

        $total = (float) $data['groupTotal'];
        $paid = (float) $data['groupPaid'];

        return [
            'invoiceNumber' => (string) $data['bookingGroupId'],
            'invoiceDate' => substr((string) ($reservation['created_at'] ?? ''), 0, 10),
            'reservation' => $reservation,
            'groupReservations' => $activeReservations,
            'roomRows' => $roomRows,
            'roomTotal' => $roomTotal,
            'additionalRows' => $additionalRows,
            'additionalTotal' => array_sum(array_map(
                static fn (array $row): float => (float) $row['amount'],
                $additionalRows,
            )),
            'total' => $total,
            'paid' => $paid,
            'outstanding' => max(0, $total - $paid),
            'logoData' => $logoData,
            'propertyName' => (string) session('activePropertyName'),
            'isHomiq' => PropertyContext::activeId() === PropertyContext::HOMIQ_ID,
            'isMonthly' => $pricingMode === 'room_monthly',
            'isCompact' => $isCompact,
        ];
    }

    /**
     * Return the unpaid balance for every active room in this booking group.
     * A checkout must not bypass a balance that belongs to another room in the
     * same guest booking.
     *
     * @param array<string, mixed> $reservation
     */
    private function bookingOutstanding($db, array $reservation): float
    {
        $bookingGroupId = trim((string) ($reservation['booking_group_id'] ?? ''));
        $groupBuilder = $db->table('reservations')->select('id, total_amount, status')->where('property_id', PropertyContext::activeId());
        if ($bookingGroupId !== '') {
            $groupBuilder->where('booking_group_id', $bookingGroupId);
        } elseif (! empty($reservation['order_id'])) {
            $groupBuilder->where('order_id', $reservation['order_id']);
        } else {
            $groupBuilder->where('id', $reservation['id']);
        }

        $activeReservations = array_values(array_filter(
            $groupBuilder->get()->getResultArray(),
            static fn (array $groupReservation): bool => (string) $groupReservation['status'] !== 'Cancelled',
        ));
        $reservationIds = array_map('intval', array_column($activeReservations, 'id'));
        $total = array_sum(array_map(static fn (array $groupReservation): float => (float) $groupReservation['total_amount'], $activeReservations));
        $paid = $reservationIds === [] ? 0.0 : (float) ($db->table('payments')
            ->selectSum('amount')
            ->whereIn('reservation_id', $reservationIds)
            ->where('status', 'Paid')
            ->get()
            ->getRow('amount') ?? 0);

        return max(0, $total - $paid);
    }

    private function findConflictingRooms($db, array $roomIds, string $checkIn, string $checkOut, array $excludeReservationIds = []): array
    {
        $builder = $db->table('reservations')
            ->select('rooms.room_number')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->where('reservations.property_id', PropertyContext::activeId())
            ->whereIn('reservations.room_id', $roomIds)
            ->where('reservations.check_in_date <', $checkOut)
            ->where("CASE WHEN reservations.check_out_date <= reservations.check_in_date THEN DATE_ADD(reservations.check_in_date, INTERVAL 1 DAY) ELSE reservations.check_out_date END > " . $db->escape($checkIn), null, false)
            ->where('reservations.status !=', 'Cancelled')
            ->where('reservations.status !=', 'Checked_Out')
            ->orderBy('rooms.room_number', 'ASC');
        if ($excludeReservationIds !== []) {
            $builder->whereNotIn('reservations.id', $excludeReservationIds);
        }
        $rows = $builder->get()->getResultArray();

        return array_values(array_unique(array_column($rows, 'room_number')));
    }

    private function stayEndExclusive(string $checkIn, string $checkOut): string
    {
        return $checkOut > $checkIn
            ? $checkOut
            : (new DateTimeImmutable($checkIn))->modify('+1 day')->format('Y-m-d');
    }

    private function generateBookingGroupId(?int $propertyId = null): string
    {
        $propertyId ??= PropertyContext::activeId();
        $prefix = PropertyContext::reservationPrefix($propertyId);

        do {
            $orderId = $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $exists = db_connect()->table('reservations')
                ->groupStart()
                ->where('order_id', $orderId)
                ->orWhere('booking_group_id', $orderId)
                ->groupEnd()
                ->countAllResults() > 0;
        } while ($exists);

        return $orderId;
    }

    private function postedRoomIds(): array
    {
        $posted = $this->request->getPost('room_ids');
        if (! is_array($posted)) {
            $legacyRoomId = (int) $this->request->getPost('room_id');
            $posted = $legacyRoomId > 0 ? [$legacyRoomId] : [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $posted), static fn (int $id): bool => $id > 0)));
    }

    private function postedExtraBedQuantities(array $roomIds): array
    {
        $posted = $this->request->getPost('extra_bed_quantity');
        if (! is_array($posted)) {
            return [];
        }

        $allowed = array_flip(array_map('intval', $roomIds));
        $quantities = [];
        foreach ($posted as $roomId => $quantity) {
            $roomId = (int) $roomId;
            $quantity = max(0, (int) $quantity);
            if ($quantity > 0 && isset($allowed[$roomId])) {
                $quantities[$roomId] = $quantity;
            }
        }

        return $quantities;
    }

    private function distributePayment($db, array $reservations, float $amount, ?string $paymentDate, string $method, ?string $notes, bool $initial, ?array $proof = null, ?string $initialPaymentType = null): void
    {
        $remaining = $amount;
        $now = date('Y-m-d H:i:s');
        $attachProof = $proof !== null;

        foreach ($reservations as $reservation) {
            if ($remaining <= 0) {
                break;
            }

            $outstanding = max(0, (float) $reservation['total_amount'] - (float) $reservation['paid_amount']);
            if ($outstanding <= 0) {
                continue;
            }

            $allocation = min($remaining, $outstanding);
            $db->table('payments')->insert([
                'reservation_id' => (int) $reservation['id'],
                'order_id' => $reservation['order_id'],
                'amount' => $allocation,
                'payment_date' => $paymentDate,
                'payment_method' => $method,
                'notes' => $notes,
                'proof_path' => $attachProof ? $proof['relative_path'] : null,
                'proof_original_name' => $attachProof ? $proof['original_name'] : null,
                'proof_mime_type' => $attachProof ? $proof['mime_type'] : null,
                'proof_size' => $attachProof ? $proof['file_size'] : null,
                'type' => $initial && $initialPaymentType === 'dp'
                    ? 'DP'
                    : ($allocation >= $outstanding ? 'Completed' : ($initial ? 'DP' : 'Additional Charges')),
                'status' => $paymentDate === null ? 'Pending' : 'Paid',
                'created_at' => $now,
            ]);
            $remaining -= $allocation;
            $attachProof = false;
        }
    }

    private function storePaymentProof(string $method, float $amount): ?array
    {
        $file = $this->request->getFile('payment_proof');
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($method !== 'Transfer' || $amount <= 0) {
            throw new \RuntimeException('Bukti pembayaran hanya dapat diunggah untuk pembayaran Transfer yang memiliki nominal.');
        }

        return (new ImageUploadService())->store($file, 'payments/' . date('Y/m'));
    }

    private function storePaymentProofUpload(): ?array
    {
        $file = $this->request->getFile('payment_proof');
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return (new ImageUploadService())->store($file, 'payments/' . date('Y/m'));
    }

    private function storeKtpPhoto(): ?array
    {
        $file = $this->request->getFile('ktp_photo');
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return (new ImageUploadService())->store($file, 'ktp/' . date('Y/m'));
    }

    private function removeStoredImage(?array $file): void
    {
        if (! empty($file['absolute_path']) && is_file($file['absolute_path'])) {
            @unlink($file['absolute_path']);
        }
    }

    private function removeStoredImageByRelativePath(string $relativePath): void
    {
        if ($relativePath === '') {
            return;
        }

        $uploadsRoot = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads');
        $absolutePath = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($uploadsRoot !== false && $absolutePath !== false && str_starts_with($absolutePath, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function nullablePost(string $key): ?string
    {
        $value = trim((string) $this->request->getPost($key));
        return $value === '' ? null : $value;
    }

    private function validDateFilter(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function newBookingError(string $message)
    {
        return $this->response
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setBody('<div class="alert alert-danger mb-0">' . esc($message) . '</div>');
    }
}
