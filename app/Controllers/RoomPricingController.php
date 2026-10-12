<?php

namespace App\Controllers;

use App\Libraries\PropertyContext;
use App\Libraries\PropertyPricing;

class RoomPricingController extends BaseController
{
    public function index(): string
    {
        $pricing = PropertyPricing::active();
        return view('pages/room_pricing', [
            'pageTitle' => 'Harga Kamar',
            'pageDescription' => $pricing['pricing_mode'] === 'whole_home_nightly'
                ? 'Atur tarif sewa satu rumah utuh per malam untuk reservasi baru.'
                : ($pricing['pricing_mode'] === 'room_monthly' ? 'Atur tarif per kamar per bulan.' : 'Atur harga per malam untuk reservasi baru.'),
            'pricing' => $pricing,
            'rooms' => db_connect()->table('rooms')
                ->where('property_id', PropertyContext::activeId())
                ->orderBy('room_type', 'ASC')
                ->orderBy('room_number', 'ASC')
                ->get()
                ->getResultArray(),
        ]);
    }

    public function edit(int $id): string
    {
        $db = db_connect();
        $room = $db->table('rooms')
            ->where('id', $id)
            ->where('property_id', PropertyContext::activeId())
            ->get()
            ->getRowArray();

        if ($room === null) {
            return '<div class="alert alert-danger m-3">Kamar tidak ditemukan.</div>';
        }

        return view('components/modals/room_edit', [
            'room' => $room,
            'pricing' => PropertyPricing::active(),
            'propertyName' => session('activePropertyName') ?? 'Properti',
        ]);
    }

    public function update(int $id)
    {
        $db = db_connect();
        $room = $db->table('rooms')
            ->where('id', $id)
            ->where('property_id', PropertyContext::activeId())
            ->get()
            ->getRowArray();

        if ($room === null) {
            return redirect()->back()->with('error', 'Kamar tidak ditemukan.');
        }

        $rawPrice = trim((string) $this->request->getPost('price_per_night'));
        $price = $this->parsePrice($rawPrice);
        if ($price === null) {
            return redirect()->back()->with('error', 'Harga kamar harus berupa angka 0 atau lebih.');
        }

        $roomNumber = trim((string) $this->request->getPost('room_number'));
        if ($roomNumber === '') {
            $roomNumber = $room['room_number'];
        }

        // Pastikan tidak ada duplikasi nomor/nama kamar pada properti yang sama
        $duplicate = $db->table('rooms')
            ->where('property_id', PropertyContext::activeId())
            ->where('room_number', $roomNumber)
            ->where('id !=', $id)
            ->countAllResults();

        if ($duplicate > 0) {
            return redirect()->back()->with('error', "Nomor atau nama kamar '{$roomNumber}' sudah digunakan pada properti ini.");
        }

        $roomType = trim((string) $this->request->getPost('room_type'));
        if ($roomType === '') {
            $roomType = $room['room_type'];
        }

        $db->table('rooms')
            ->where('id', $id)
            ->where('property_id', PropertyContext::activeId())
            ->update([
                'room_number' => $roomNumber,
                'room_type' => $roomType,
                'price_per_night' => $price,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return redirect()->to('/room-pricing')->with('success', 'Data kamar ' . $roomNumber . ' berhasil diperbarui.');
    }

    public function updateAll()
    {
        $price = $this->parsePrice(trim((string) $this->request->getPost('bulk_price_per_night')));
        if ($price === null) return redirect()->back()->with('error', 'Harga semua kamar harus berupa angka 0 atau lebih.');

        $db = db_connect();
        if (PropertyPricing::active()['pricing_mode'] !== 'room_nightly') {
            $db->table('properties')->where('id', PropertyContext::activeId())->update([
                'base_rate' => $price,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $db->table('rooms')->where('property_id', PropertyContext::activeId())->update([
                'price_per_night' => $price,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $successMsg = PropertyPricing::active()['pricing_mode'] === 'whole_home_nightly'
                ? 'Tarif sewa homestay berhasil diperbarui.'
                : 'Tarif properti dan semua kamar berhasil diperbarui.';
            return redirect()->to('/room-pricing')->with('success', $successMsg);
        }
        $db->transStart();
        $db->table('rooms')->where('property_id', PropertyContext::activeId())->update([
            'price_per_night' => $price,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $db->transComplete();

        if (! $db->transStatus()) return redirect()->back()->with('error', 'Harga semua kamar gagal diperbarui.');

        return redirect()->to('/room-pricing')->with('success', 'Harga semua kamar berhasil diperbarui.');
    }

    private function parsePrice(string $value): ?float
    {
        $clean = str_replace(['Rp', 'rp', ' ', '.'], '', $value);
        $clean = str_replace(',', '.', $clean);
        if ($clean === '' || ! is_numeric($clean) || (float) $clean < 0) return null;

        return round((float) $clean, 2);
    }
}
