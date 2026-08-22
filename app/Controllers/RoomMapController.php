<?php

namespace App\Controllers;

use DateTimeImmutable;
use DateTimeZone;

class RoomMapController extends BaseController
{
    public function index(): string
    {
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
            ->where('reservations.status !=', 'Checked_Out')
            ->where('reservations.status !=', 'No_Show')
            ->orderBy('reservations.check_in_date', 'ASC')
            ->get()->getResultArray();

        $bookingsByRoom = [];
        foreach ($reservations as $reservation) {
            $bookingsByRoom[(int) $reservation['room_id']][] = $reservation;
        }

        $rooms = $db->table('rooms');
        if ($guesthouse !== null) {
            $rooms->where('floor', $guesthouse);
        }

        return view('components/room_map_grid', [
            'rooms' => $rooms->orderBy('room_number', 'ASC')->get()->getResultArray(),
            'bookingsByRoom' => $bookingsByRoom,
            'firstDay' => $firstDay,
            'lastDay' => $lastDay,
            'daysInMonth' => (int) $firstDay->format('t'),
            'guesthouse' => $guesthouse,
            'today' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d'),
        ]);
    }

    private function validMonth(string $month): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 ? $month : date('Y-m');
    }

    private function validGuesthouse(string $guesthouse): ?int
    {
        return preg_match('/^[1-5]$/', $guesthouse) === 1 ? (int) $guesthouse : null;
    }
}
