<?php

namespace App\Controllers;

class MaintenanceCategoryController extends BaseController
{
    private const INTERVAL_UNITS = ['Day', 'Week', 'Month', 'Year'];

    public function create()
    {
        if (! $this->validate($this->rules())) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($this->nameExists($name)) {
            return $this->feedback('danger', 'Nama kategori sudah digunakan.');
        }

        $now = date('Y-m-d H:i:s');
        db_connect()->table('maintenance_categories')->insert($this->payload($now) + ['created_at' => $now]);

        return $this->feedback('success', 'Kategori maintenance berhasil ditambahkan.');
    }

    public function update(int $id)
    {
        if (! $this->validate($this->rules())) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        $db = db_connect();
        $category = $db->table('maintenance_categories')->where('id', $id)->get()->getRowArray();
        if ($category === null) {
            return $this->feedback('danger', 'Kategori maintenance tidak ditemukan.');
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($this->nameExists($name, $id)) {
            return $this->feedback('danger', 'Nama kategori sudah digunakan.');
        }

        $db->table('maintenance_categories')->where('id', $id)->update($this->payload(date('Y-m-d H:i:s')));

        return $this->feedback('success', 'Kategori maintenance berhasil diperbarui.');
    }

    /**
     * @return array<string, string>
     */
    private function rules(): array
    {
        return [
            'name' => 'required|max_length[100]',
            'code' => 'permit_empty|max_length[30]',
            'description' => 'permit_empty|max_length[1000]',
            'default_interval_value' => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[3650]',
            'default_interval_unit' => 'required|in_list[' . implode(',', self::INTERVAL_UNITS) . ']',
            'default_checklist' => 'permit_empty|max_length[4000]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $updatedAt): array
    {
        return [
            'name' => trim((string) $this->request->getPost('name')),
            'code' => $this->nullablePost('code', true),
            'description' => $this->nullablePost('description'),
            'default_interval_value' => (int) $this->request->getPost('default_interval_value'),
            'default_interval_unit' => (string) $this->request->getPost('default_interval_unit'),
            'default_checklist' => $this->nullablePost('default_checklist'),
            'is_active' => $this->request->getPost('is_active') === '1' ? 1 : 0,
            'updated_at' => $updatedAt,
        ];
    }

    private function nameExists(string $name, ?int $exceptId = null): bool
    {
        $builder = db_connect()->table('maintenance_categories')->where('name', $name);
        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    private function nullablePost(string $key, bool $uppercase = false): ?string
    {
        $value = trim((string) $this->request->getPost($key));
        if ($value === '') {
            return null;
        }

        return $uppercase ? strtoupper($value) : $value;
    }

    private function feedback(string $type, string $message)
    {
        return $this->response
            ->setHeader('HX-Trigger', json_encode(['opsChanged' => true]))
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setBody('<div class="alert alert-' . $type . ' mb-0">' . esc($message) . '</div>');
    }
}
