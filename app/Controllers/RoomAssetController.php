<?php

namespace App\Controllers;

use App\Libraries\PropertyContext;

class RoomAssetController extends BaseController
{
    private const CONDITIONS = ['Good', 'Attention', 'Broken', 'Retired'];

    public function create()
    {
        if (! $this->validate($this->rules())) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        if (($message = $this->referenceError(true)) !== null) {
            return $this->feedback('danger', $message);
        }

        $now = date('Y-m-d H:i:s');
        db_connect()->table('room_assets')->insert($this->payload($now) + ['created_at' => $now]);

        return $this->feedback('success', 'Aset kamar berhasil ditambahkan.');
    }

    public function update(int $id)
    {
        if (! $this->validate($this->rules())) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        $db = db_connect();
        $asset = $db->table('room_assets')->select('room_assets.*')->join('rooms', 'rooms.id = room_assets.room_id')->where('rooms.property_id', PropertyContext::activeId())->where('room_assets.id', $id)->get()->getRowArray();
        if ($asset === null) {
            return $this->feedback('danger', 'Aset kamar tidak ditemukan.');
        }

        if (($message = $this->referenceError(false)) !== null) {
            return $this->feedback('danger', $message);
        }

        $db->table('room_assets')->where('id', $id)->update($this->payload(date('Y-m-d H:i:s')));

        return $this->feedback('success', 'Aset kamar berhasil diperbarui.');
    }

    /**
     * @return array<string, string>
     */
    private function rules(): array
    {
        return [
            'room_id' => 'required|is_natural_no_zero',
            'maintenance_category_id' => 'required|is_natural_no_zero',
            'asset_name' => 'required|max_length[150]',
            'brand' => 'permit_empty|max_length[100]',
            'purchase_date' => 'permit_empty|valid_date[Y-m-d]',
            'installed_at' => 'permit_empty|valid_date[Y-m-d]',
            'warranty_until' => 'permit_empty|valid_date[Y-m-d]',
            'condition_status' => 'required|in_list[' . implode(',', self::CONDITIONS) . ']',
            'notes' => 'permit_empty|max_length[2000]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $updatedAt): array
    {
        return [
            'room_id' => (int) $this->request->getPost('room_id'),
            'maintenance_category_id' => (int) $this->request->getPost('maintenance_category_id'),
            'asset_name' => trim((string) $this->request->getPost('asset_name')),
            'brand' => $this->nullablePost('brand'),
            'purchase_date' => $this->nullablePost('purchase_date'),
            'installed_at' => $this->nullablePost('installed_at'),
            'warranty_until' => $this->nullablePost('warranty_until'),
            'condition_status' => (string) $this->request->getPost('condition_status'),
            'notes' => $this->nullablePost('notes'),
            'is_active' => $this->request->getPost('is_active') === '1' ? 1 : 0,
            'updated_at' => $updatedAt,
        ];
    }

    private function referenceError(bool $requireActiveCategory): ?string
    {
        $db = db_connect();
        if ($db->table('rooms')->where('property_id', PropertyContext::activeId())->where('id', (int) $this->request->getPost('room_id'))->countAllResults() === 0) {
            return 'Kamar tidak ditemukan.';
        }

        $category = $db->table('maintenance_categories')->where('id', (int) $this->request->getPost('maintenance_category_id'));
        if ($requireActiveCategory) {
            $category->where('is_active', 1);
        }
        if ($category->countAllResults() === 0) {
            return 'Kategori maintenance tidak ditemukan.';
        }

        return null;
    }

    private function nullablePost(string $key): ?string
    {
        $value = trim((string) $this->request->getPost($key));
        return $value === '' ? null : $value;
    }

    private function feedback(string $type, string $message)
    {
        return $this->response
            ->setHeader('HX-Trigger', json_encode(['opsChanged' => true]))
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setBody('<div class="alert alert-' . $type . ' mb-0">' . esc($message) . '</div>');
    }
}
