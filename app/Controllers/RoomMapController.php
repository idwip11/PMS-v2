<?php

namespace App\Controllers;

use App\Libraries\PropertyContext;
use DateTimeImmutable;
use DateTimeZone;

class RoomMapController extends BaseController
{
    public function index(): string
    {
        $this->disableCaching();
        $month = $this->validMonth((string) $this->request->getGet('month'));
        $guesthouse = $this->validGuesthouse((string) $this->request->getGet('guesthouse'));

        return view('pages/room_map', [
            'month' => $month,
            'guesthouse' => $guesthouse,
            'pageTitle' => 'Room Map',
        ]);
    }

    public function grid(): string
    {
        // Room Map is a live operational view. Do not let Rumahweb, a proxy, or
        // the browser serve a stale grid after a reservation is cancelled.
        $this->disableCaching();
        $month = $this->validMonth((string) $this->request->getGet('month'));
        $guesthouse = $this->validGuesthouse((string) $this->request->getGet('guesthouse'));
        $firstDay = new DateTimeImmutable($month . '-01');
        $nextMonth = $firstDay->modify('first day of next month');
        $lastDay = $nextMonth->modify('-1 day');
        $db = db_connect();

        if ($this->request->hasHeader('HX-Request')) {
            $query = '?month=' . $month . ($guesthouse !== null ? '&guesthouse=' . $guesthouse : '');
            $this->response->setHeader('HX-Push-Url', site_url('room-map' . $query));
        }

        $paymentTotals = $db->table('payments')
            ->select('reservation_id, SUM(amount) AS paid_amount')
            ->where('status', 'Paid')
            ->groupBy('reservation_id')
            ->getCompiledSelect();

        $reservations = $db->table('reservations')
            ->select('reservations.*, guests.full_name AS guest_name, rooms.room_number, COALESCE(paid.paid_amount, 0) AS paid_amount', false)
            ->join('guests', 'guests.id = reservations.guest_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->join("({$paymentTotals}) paid", 'paid.reservation_id = reservations.id', 'left', false)
            ->where('reservations.property_id', PropertyContext::activeId())
            ->groupStart()
                ->groupStart()
                    ->where('reservations.check_in_date <', $nextMonth->format('Y-m-d'))
                    ->where('reservations.check_out_date >', $firstDay->format('Y-m-d'))
                ->groupEnd()
                ->orGroupStart()
                    ->where('reservations.check_in_date = reservations.check_out_date', null, false)
                    ->where('reservations.check_in_date >=', $firstDay->format('Y-m-d'))
                    ->where('reservations.check_in_date <', $nextMonth->format('Y-m-d'))
                ->groupEnd()
            ->groupEnd()
            ->where('reservations.status !=', 'Cancelled')
            ->where('reservations.status !=', 'No_Show')
            ->orderBy('reservations.check_in_date', 'ASC')
            ->get()->getResultArray();

        $pricing = \App\Libraries\PropertyPricing::active();
        $isWholeHome = $pricing['pricing_mode'] === 'whole_home_nightly';

        $rooms = $db->table('rooms')->where('property_id', PropertyContext::activeId());
        if ($guesthouse !== null) {
            $rooms->groupStart()
                ->where('guesthouse_number', $guesthouse)
                ->orWhere('floor', $guesthouse)
                ->groupEnd();
        }
        $roomsList = $rooms->orderBy('room_number', 'ASC')->get()->getResultArray();

        if ($isWholeHome) {
            // Whole-home property (e.g. Homestay Mitra): rented as an entire house,
            // so display 1 single row rather than separate individual rooms.
            $statuses = array_column($roomsList, 'status');
            $overallStatus = in_array('Maintenance', $statuses, true)
                ? 'Maintenance'
                : (in_array('Occupied', $statuses, true)
                    ? 'Occupied'
                    : (in_array('Dirty', $statuses, true) ? 'Dirty' : 'Available'));

            $singleRoomId = $roomsList !== [] ? (int) $roomsList[0]['id'] : 1;
            $propertyName = (string) (session('activePropertyName') ?? 'Homestay Mitra');

            $roomsList = [
                [
                    'id' => $singleRoomId,
                    'room_number' => $propertyName,
                    'room_type' => '1 Rumah (' . count($roomsList) . ' Kamar)',
                    'status' => $overallStatus,
                ],
            ];

            // Consolidate reservations by booking group into this single row
            $groupedBookings = [];
            foreach ($reservations as $res) {
                $groupKey = trim((string) ($res['booking_group_id'] ?? ''));
                if ($groupKey === '') {
                    $groupKey = trim((string) ($res['order_id'] ?? '')) ?: (string) $res['id'];
                }

                if (! isset($groupedBookings[$groupKey])) {
                    $groupedBookings[$groupKey] = $res;
                    $groupedBookings[$groupKey]['room_id'] = $singleRoomId;
                    $groupedBookings[$groupKey]['order_id'] = ! empty($res['booking_group_id']) ? $res['booking_group_id'] : $res['order_id'];
                    $groupedBookings[$groupKey]['total_amount'] = 0.0;
                    $groupedBookings[$groupKey]['paid_amount'] = 0.0;
                }

                $groupedBookings[$groupKey]['total_amount'] += (float) ($res['total_amount'] ?? 0);
                $groupedBookings[$groupKey]['paid_amount'] += (float) ($res['paid_amount'] ?? 0);

                if ($res['status'] === 'Checked_In') {
                    $groupedBookings[$groupKey]['status'] = 'Checked_In';
                } elseif ($res['status'] === 'Checked_Out' && ($groupedBookings[$groupKey]['status'] ?? '') !== 'Checked_In') {
                    $groupedBookings[$groupKey]['status'] = 'Checked_Out';
                }

                if ((int) ($res['is_reconciled'] ?? 0) === 1) {
                    $groupedBookings[$groupKey]['is_reconciled'] = 1;
                }

                // Prefer the primary / lowest reservation id for the link to details
                if ((int) $res['id'] < (int) $groupedBookings[$groupKey]['id']) {
                    $groupedBookings[$groupKey]['id'] = $res['id'];
                }
            }

            $bookingsByRoom = [
                $singleRoomId => array_values($groupedBookings),
            ];
        } else {
            $bookingsByRoom = [];
            foreach ($reservations as $reservation) {
                $bookingsByRoom[(int) $reservation['room_id']][] = $reservation;
            }
        }

        return view('components/room_map_grid', [
            'rooms' => $roomsList,
            'bookingsByRoom' => $bookingsByRoom,
            'firstDay' => $firstDay,
            'lastDay' => $lastDay,
            'daysInMonth' => (int) $firstDay->format('t'),
            'guesthouse' => $guesthouse,
            'today' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d'),
        ]);
    }

    private function disableCaching(): void
    {
        $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0');
    }

    private function validMonth(string $month): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 ? $month : date('Y-m');
    }

    private function validGuesthouse(string $guesthouse): ?int
    {
        return PropertyContext::activeId() === PropertyContext::HOMIQ_ID && preg_match('/^[1-5]$/', $guesthouse) === 1
            ? (int) $guesthouse : null;
    }
}
