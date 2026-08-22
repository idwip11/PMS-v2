<?php

namespace App\Controllers;

class RoomPricingController extends BaseController
{
    public function index(): string
    {
        return view('pages/room_pricing', [
            'pageTitle' => 'Harga Kamar',
            'pageDescription' => 'Atur harga per malam yang akan digunakan saat membuat reservasi baru.',
            'rooms' => db_connect()->table('rooms')
                ->orderBy('room_type', 'ASC')
                ->orderBy('room_number', 'ASC')
                ->get()
                ->getResultArray(),
        ]);
    }

    public function update(int $id)
    {
        $rawPrice = trim((string) $this->request->getPost('price_per_night'));
        $price = $this->parsePrice($rawPrice);
        if ($price === null) return redirect()->back()->with('error', 'Harga kamar harus berupa angka 0 atau lebih.');

        $db = db_connect();
        $room = $db->table('rooms')->select('room_number')->where('id', $id)->get()->getRowArray();
        if ($room === null) {
            return redirect()->back()->with('error', 'Kamar tidak ditemukan.');
        }

        $db->table('rooms')->where('id', $id)->update([
            'price_per_night' => $price,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/room-pricing')->with('success', 'Harga kamar ' . $room['room_number'] . ' berhasil diperbarui.');
    }

    public function updateAll()
    {
        $price = $this->parsePrice(trim((string) $this->request->getPost('bulk_price_per_night')));
        if ($price === null) return redirect()->back()->with('error', 'Harga semua kamar harus berupa angka 0 atau lebih.');

        $db = db_connect();
        $db->transStart();
        $db->table('rooms')->where('id >', 0)->update([
            'price_per_night' => $price,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $db->transComplete();

        if (! $db->transStatus()) return redirect()->back()->with('error', 'Harga semua kamar gagal diperbarui.');

        return redirect()->to('/room-pricing')->with('success', 'Harga semua kamar berhasil diperbarui.');
    }

    private function parsePrice(string $value): ?float
    {
        if ($value === '' || ! is_numeric($value) || (float) $value < 0) return null;

        return round((float) $value, 2);
    }
}
