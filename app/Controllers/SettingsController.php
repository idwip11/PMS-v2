<?php

namespace App\Controllers;

use App\Models\UserModel;

class SettingsController extends BaseController
{
    private const ROLES = [
        'Admin',
        'Manager',
        'Finance',
        'Marketing',
        'Operational',
        'Housekeeping',
        'Staff',
        'Owner GH 1',
        'Owner GH 2',
        'Owner GH 3',
        'Owner GH 4',
        'Owner GH 5',
    ];

    public function index(): string
    {
        return view('pages/settings', ['pageTitle' => 'Settings', 'pageDescription' => 'Kelola akun staf dan role akses sistem.']);
    }

    public function panel(): string
    {
        return view('components/settings_panel', [
            'users' => (new UserModel())->orderBy('full_name', 'ASC')->findAll(),
            'roles' => self::ROLES,
        ]);
    }

    public function createUser()
    {
        if (! $this->validate([
            'username' => 'required|max_length[100]|is_unique[users.username]',
            'full_name' => 'required|max_length[150]',
            'role' => 'required|in_list[' . implode(',', self::ROLES) . ']',
            'password' => 'required|min_length[8]',
        ])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        (new UserModel())->insert([
            'username' => trim((string) $this->request->getPost('username')),
            'full_name' => trim((string) $this->request->getPost('full_name')),
            'role' => (string) $this->request->getPost('role'),
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
        ]);

        return $this->feedback('success', 'User baru berhasil ditambahkan.');
    }

    public function updateUser(int $id)
    {
        $rules = [
            'full_name' => 'required|max_length[150]',
            'role' => 'required|in_list[' . implode(',', self::ROLES) . ']',
            'password' => 'permit_empty|min_length[8]',
        ];

        if (! $this->validate($rules)) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        $model = new UserModel();
        $user = $model->find($id);
        if ($user === null) {
            return $this->feedback('danger', 'User tidak ditemukan.');
        }

        $payload = [
            'full_name' => trim((string) $this->request->getPost('full_name')),
            'role' => (string) $this->request->getPost('role'),
        ];

        $password = (string) $this->request->getPost('password');
        if ($password !== '') {
            $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $model->update($id, $payload);

        if ((int) session('userId') === $id) {
            session()->set([
                'fullName' => $payload['full_name'],
                'role' => $payload['role'],
            ]);
        }

        return $this->feedback('success', 'User berhasil diperbarui.');
    }

    public function deleteUser(int $id)
    {
        $model = new UserModel();
        $user = $model->find($id);
        if ($user === null) {
            return $this->feedback('danger', 'User tidak ditemukan.');
        }
        if ((int) session('userId') === $id) {
            return $this->feedback('danger', 'Akun yang sedang digunakan tidak dapat dihapus.');
        }
        if ($user['role'] === 'Admin' && $model->where('role', 'Admin')->countAllResults() <= 1) {
            return $this->feedback('danger', 'Admin terakhir tidak dapat dihapus.');
        }

        $model->delete($id);
        return $this->feedback('success', 'User ' . $user['username'] . ' berhasil dihapus.');
    }

    private function feedback(string $type, string $message)
    {
        return $this->response
            ->setHeader('HX-Trigger', json_encode(['settingsChanged' => true]))
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setBody('<div class="alert alert-' . $type . ' mb-0">' . esc($message) . '</div>');
    }
}
