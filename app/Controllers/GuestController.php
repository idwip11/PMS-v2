<?php

namespace App\Controllers;

use App\Libraries\GuestDirectory;

class GuestController extends BaseController
{
    public function suggestions()
    {
        $search = trim((string) $this->request->getGet('q'));
        $builder = db_connect()->table('guests')
            ->select('id, full_name, email, phone, origin, notes');

        if ($search !== '') {
            $builder->groupStart()
                ->like('full_name', $search)
                ->orLike('email', $search)
                ->orLike('phone', $search)
                ->groupEnd();
        }

        return $this->response->setJSON([
            'guests' => $builder
                ->orderBy('full_name', 'ASC')
                ->limit(8)
                ->get()
                ->getResultArray(),
        ]);
    }

    public function index(): string
    {
        $db = db_connect();
        $search = trim((string) $this->request->getGet('q'));
        $staySummary = $db->table('reservations')
            ->select('guest_id, COUNT(DISTINCT booking_group_id) AS reservation_count, MAX(check_out_date) AS last_stay', false)
            ->groupBy('guest_id')
            ->getCompiledSelect();

        $builder = $db->table('guests')
            ->select('guests.*, COALESCE(stays.reservation_count, 0) AS reservation_count, stays.last_stay', false)
            ->join("({$staySummary}) stays", 'stays.guest_id = guests.id', 'left', false);

        if ($search !== '') {
            $builder->groupStart()
                ->like('guests.full_name', $search)
                ->orLike('guests.email', $search)
                ->orLike('guests.phone', $search)
                ->orLike('guests.origin', $search)
                ->groupEnd();
        }

        return view('pages/guests', [
            'pageTitle' => 'Data Tamu',
            'pageDescription' => 'Direktori tamu dari input manual dan reservasi baru.',
            'guests' => $builder->orderBy('guests.full_name', 'ASC')->limit(200)->get()->getResultArray(),
            'search' => $search,
        ]);
    }

    public function create()
    {
        if (! $this->validateGuestInput()) {
            return redirect()->back()->withInput()->with('error', 'Nama tamu wajib diisi dan format email harus valid.');
        }

        $db = db_connect();
        $guestId = (new GuestDirectory($db))->save([
            'full_name' => trim((string) $this->request->getPost('full_name')),
            'email' => $this->nullablePost('email'),
            'phone' => $this->nullablePost('phone'),
            'origin' => $this->nullablePost('origin'),
            'notes' => $this->nullablePost('notes'),
        ]);

        return redirect()->to('/guests')->with('success', 'Data tamu berhasil disimpan (ID #' . $guestId . ').');
    }

    public function update(int $id)
    {
        if (! $this->validateGuestInput()) {
            return redirect()->back()->withInput()->with('error', 'Nama tamu wajib diisi dan format email harus valid.');
        }

        $db = db_connect();
        $guest = $db->table('guests')->select('id')->where('id', $id)->get()->getRowArray();
        if ($guest === null) {
            return redirect()->to('/guests')->with('error', 'Data tamu tidak ditemukan.');
        }

        helper('marketing_analytics');
        $origin = normalize_guest_origin($this->request->getPost('origin'));
        $db->table('guests')->where('id', $id)->update([
            'full_name' => trim((string) $this->request->getPost('full_name')),
            'email' => $this->nullablePost('email'),
            'phone' => $this->nullablePost('phone'),
            'origin' => $origin === 'Tidak diketahui' ? null : $origin,
            'notes' => $this->nullablePost('notes'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/guests')->with('success', 'Data tamu berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $db = db_connect();
        $guest = $db->table('guests')->select('id, full_name')->where('id', $id)->get()->getRowArray();
        if ($guest === null) {
            return redirect()->to('/guests')->with('error', 'Data tamu tidak ditemukan.');
        }

        $reservationCount = $db->table('reservations')->where('guest_id', $id)->countAllResults();
        if ($reservationCount > 0) {
            return redirect()->to('/guests')->with('error', 'Tamu tidak dapat dihapus karena sudah memiliki histori reservasi.');
        }

        $db->table('guests')->where('id', $id)->delete();

        return redirect()->to('/guests')->with('success', 'Data tamu ' . $guest['full_name'] . ' berhasil dihapus.');
    }

    private function validateGuestInput(): bool
    {
        return $this->validate([
            'full_name' => 'required|max_length[150]',
            'email' => 'permit_empty|valid_email|max_length[150]',
            'phone' => 'permit_empty|max_length[50]',
            'origin' => 'permit_empty|max_length[150]',
            'notes' => 'permit_empty',
        ]);
    }

    private function nullablePost(string $key): ?string
    {
        $value = trim((string) $this->request->getPost($key));

        return $value === '' ? null : $value;
    }
}
