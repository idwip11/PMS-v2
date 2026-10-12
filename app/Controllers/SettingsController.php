<?php

namespace App\Controllers;

use App\Libraries\PropertyContext;
use App\Models\UserModel;

class SettingsController extends BaseController
{
    private const ROLES = [
        'Admin', 'Manager', 'Finance', 'Marketing', 'Operational',
        'Housekeeping', 'Staff', 'Owner GH 1', 'Owner GH 2',
        'Owner GH 3', 'Owner GH 4', 'Owner GH 5',
        'Owner HS Mitra', 'Owner Kos Mitra', 'Owner Mitra', 'Atmin',
    ];

    public function index(): string
    {
        return view('pages/settings', [
            'pageTitle' => 'Settings',
            'pageDescription' => 'Kelola akun staf dan role per properti.',
        ]);
    }

    public function panel(): string
    {
        $db = db_connect();
        $assignments = [];
        foreach ($db->table('user_property_roles')->get()->getResultArray() as $row) {
            $assignments[(int) $row['user_id']][(int) $row['property_id']] = $row;
        }

        return view('components/settings_panel', [
            'users' => (new UserModel())->orderBy('full_name', 'ASC')->findAll(),
            'properties' => $db->table('properties')->orderBy('id', 'ASC')->get()->getResultArray(),
            'assignments' => $assignments,
            'roles' => self::ROLES,
        ]);
    }

    public function createUser()
    {
        if (! $this->validate([
            'username' => 'required|max_length[100]|is_unique[users.username]',
            'full_name' => 'required|max_length[150]',
            'property_id' => 'required|is_natural_no_zero',
            'role' => 'required|in_list[' . implode(',', self::ROLES) . ']',
            'password' => 'required|min_length[8]',
        ])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        $propertyId = (int) $this->request->getPost('property_id');
        $role = (string) $this->request->getPost('role');
        if (! $this->propertyRoleValid($propertyId, $role)) {
            return $this->feedback('danger', 'Properti atau role tidak valid. Role Owner GH hanya berlaku untuk HomiQ, dan Owner Mitra untuk Homestay & Kos Mitra.');
        }

        $db = db_connect();
        $db->transStart();
        $userId = (new UserModel())->insert([
            'username' => trim((string) $this->request->getPost('username')),
            'full_name' => trim((string) $this->request->getPost('full_name')),
            'role' => $role,
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
        ]);
        if ($userId !== false) {
            if ($role === 'Owner Mitra') {
                $now = date('Y-m-d H:i:s');
                $db->table('user_property_roles')->insertBatch([
                    ['user_id' => (int) $userId, 'property_id' => 2, 'role' => 'Owner Mitra', 'created_at' => $now, 'updated_at' => $now],
                    ['user_id' => (int) $userId, 'property_id' => 3, 'role' => 'Owner Mitra', 'created_at' => $now, 'updated_at' => $now],
                ]);
            } else {
                $db->table('user_property_roles')->insert([
                    'user_id' => (int) $userId,
                    'property_id' => $propertyId,
                    'role' => $role,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        $db->transComplete();

        return $userId !== false && $db->transStatus()
            ? $this->feedback('success', 'User baru berhasil ditambahkan.' . ($role === 'Owner Mitra' ? ' Akses diberikan berpasangan untuk Homestay Mitra & Kos Mitra.' : ''))
            : $this->feedback('danger', 'User gagal disimpan.');
    }

    public function updateUser(int $id)
    {
        if (! $this->validate([
            'full_name' => 'required|max_length[150]',
            'password' => 'permit_empty|min_length[8]',
        ])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        $model = new UserModel();
        $user = $model->find($id);
        if ($user === null) {
            return $this->feedback('danger', 'User tidak ditemukan.');
        }

        if ($this->isManagerProtected($id)) {
            return $this->feedback('danger', 'Akun dengan role Manager tidak dapat diedit oleh Admin.');
        }

        $payload = ['full_name' => trim((string) $this->request->getPost('full_name'))];
        $password = (string) $this->request->getPost('password');
        if ($password !== '') {
            $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $model->update($id, $payload);
        if ((int) session('userId') === $id) {
            session()->set('fullName', $payload['full_name']);
        }

        return $this->feedback('success', 'Data akun berhasil diperbarui.');
    }

    public function savePropertyRole(int $id)
    {
        if (! $this->validate([
            'property_id' => 'required|is_natural_no_zero',
            'role' => 'required|in_list[' . implode(',', self::ROLES) . ']',
        ])) {
            return $this->feedback('danger', implode(' ', $this->validator->getErrors()));
        }

        if ($this->isManagerProtected($id)) {
            return $this->feedback('danger', 'Role properti akun Manager tidak dapat diubah oleh Admin.');
        }

        $propertyId = (int) $this->request->getPost('property_id');
        $role = (string) $this->request->getPost('role');
        $db = db_connect();
        $current = $db->table('user_property_roles')
            ->where(['user_id' => $id, 'property_id' => $propertyId])->get()->getRowArray();
        if (! $this->propertyRoleValid($propertyId, $role, ($current['role'] ?? null) === $role)) {
            return $this->feedback('danger', 'Properti atau role tidak valid. Role Owner GH hanya berlaku untuk HomiQ, dan Owner Mitra untuk Homestay & Kos Mitra.');
        }

        if ($db->table('users')->where('id', $id)->countAllResults() === 0) {
            return $this->feedback('danger', 'User tidak ditemukan.');
        }

        if ($this->removesLastHomiqAdmin($propertyId, $current['role'] ?? null, $role)) {
            return $this->feedback('danger', 'Admin HomiQ terakhir tidak dapat diturunkan rolenya.');
        }

        $db->transStart();
        if ($role === 'Owner Mitra') {
            $now = date('Y-m-d H:i:s');
            foreach ([2, 3] as $pairedPropId) {
                $existing = $db->table('user_property_roles')
                    ->where(['user_id' => $id, 'property_id' => $pairedPropId])->get()->getRowArray();
                if ($existing === null) {
                    $db->table('user_property_roles')->insert([
                        'user_id' => $id,
                        'property_id' => $pairedPropId,
                        'role' => 'Owner Mitra',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $db->table('user_property_roles')->where('id', (int) $existing['id'])->update([
                        'role' => 'Owner Mitra',
                        'updated_at' => $now,
                    ]);
                }
            }
        } else {
            if (in_array($propertyId, [2, 3], true)
                && $db->table('user_property_roles')->where('user_id', $id)->whereIn('property_id', [2, 3])
                    ->where('role', 'Owner Mitra')->countAllResults() > 0) {
                // Replacing the package removes its owner grants on both sources, then
                // gives the selected staff role only to the explicitly selected property.
                $db->table('user_property_roles')->where('user_id', $id)->whereIn('property_id', [2, 3])
                    ->where('role', 'Owner Mitra')->delete();
                $current = $db->table('user_property_roles')->where(['user_id' => $id, 'property_id' => $propertyId])->get()->getRowArray();
            }
            if ($current === null) {
                $db->table('user_property_roles')->insert([
                    'user_id' => $id,
                    'property_id' => $propertyId,
                    'role' => $role,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $db->table('user_property_roles')->where('id', (int) $current['id'])->update([
                    'role' => $role,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            if ($propertyId === PropertyContext::HOMIQ_ID) {
                $db->table('users')->where('id', $id)->update(['role' => $role]);
            }
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->feedback('danger', 'Role gagal disimpan.');
        }
        if ((int) session('userId') === $id) {
            PropertyContext::synchronize();
        }

        return $this->feedback('success', 'Role properti berhasil disimpan.' . ($role === 'Owner Mitra' ? ' Akses Owner Mitra diperbarui untuk Homestay & Kos Mitra.' : ''));
    }

    public function deletePropertyRole(int $id, int $propertyId)
    {
        if ($this->isManagerProtected($id)) {
            return $this->feedback('danger', 'Akses properti akun Manager tidak dapat dicabut oleh Admin.');
        }

        $db = db_connect();
        $assignment = $db->table('user_property_roles')
            ->where(['user_id' => $id, 'property_id' => $propertyId])->get()->getRowArray();
        if ($assignment === null) {
            return $this->feedback('danger', 'Assignment tidak ditemukan.');
        }

        $isOwnerMitra = $assignment['role'] === 'Owner Mitra';
        if ($isOwnerMitra) {
            if ((int) session('userId') === $id && in_array((int) session('activePropertyId'), [2, 3], true)) {
                return $this->feedback('danger', 'Role properti yang sedang Anda pakai tidak dapat dihapus.');
            }
            $remainingCount = $db->table('user_property_roles')
                ->where('user_id', $id)
                ->whereNotIn('property_id', [2, 3])
                ->countAllResults();
            if ($remainingCount < 1) {
                return $this->feedback('danger', 'User harus memiliki setidaknya satu properti selain akses yang dicabut.');
            }
            $db->transStart();
            $db->table('user_property_roles')
                ->where('user_id', $id)
                ->whereIn('property_id', [2, 3])
                ->where('role', 'Owner Mitra')
                ->delete();
            $db->transComplete();

            if (! $db->transStatus()) {
                return $this->feedback('danger', 'Akses properti gagal dihapus.');
            }
            if ((int) session('userId') === $id) {
                PropertyContext::synchronize();
            }

            return $this->feedback('success', 'Akses Owner Mitra berhasil dicabut (Homestay Mitra & Kos Mitra).');
        }

        if ((int) session('userId') === $id && (int) session('activePropertyId') === $propertyId) {
            return $this->feedback('danger', 'Role properti yang sedang Anda pakai tidak dapat dihapus.');
        }
        if ($db->table('user_property_roles')->where('user_id', $id)->countAllResults() <= 1) {
            return $this->feedback('danger', 'User harus memiliki setidaknya satu properti.');
        }
        if ($this->removesLastHomiqAdmin($propertyId, (string) $assignment['role'], null)) {
            return $this->feedback('danger', 'Admin HomiQ terakhir tidak dapat dihapus.');
        }

        $db->transStart();
        $db->table('user_property_roles')->where('id', (int) $assignment['id'])->delete();
        if ($propertyId === PropertyContext::HOMIQ_ID) {
            $db->table('users')->where('id', $id)->update(['role' => 'Staff']);
        }
        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->feedback('danger', 'Akses properti gagal dihapus.');
        }
        if ((int) session('userId') === $id) {
            PropertyContext::synchronize();
        }

        return $this->feedback('success', 'Akses properti berhasil dihapus.');
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
        if ($this->isManagerProtected($id)) {
            return $this->feedback('danger', 'Akun dengan role Manager tidak dapat dihapus oleh Admin.');
        }

        $homiqAssignment = db_connect()->table('user_property_roles')
            ->where(['user_id' => $id, 'property_id' => PropertyContext::HOMIQ_ID])
            ->get()->getRowArray();
        if ($this->removesLastHomiqAdmin(PropertyContext::HOMIQ_ID, $homiqAssignment['role'] ?? null, null)) {
            return $this->feedback('danger', 'Admin HomiQ terakhir tidak dapat dihapus.');
        }

        $model->delete($id);
        return $this->feedback('success', 'User ' . $user['username'] . ' berhasil dihapus.');
    }

    private function propertyRoleValid(int $propertyId, string $role, bool $keepExisting = false): bool
    {
        if ($propertyId <= 0 || ! in_array($role, self::ROLES, true)) {
            return false;
        }
        if ($propertyId !== PropertyContext::HOMIQ_ID && str_starts_with($role, 'Owner GH ')) {
            return false;
        }
        if ($role === 'Owner HS Mitra' && $propertyId !== 2) {
            return false;
        }
        if ($role === 'Owner Kos Mitra' && $propertyId !== 3) {
            return false;
        }
        if (in_array($role, ['Owner HS Mitra', 'Owner Kos Mitra'], true) && ! $keepExisting) {
            return false;
        }
        if ($role === 'Owner Mitra') {
            if (! in_array($propertyId, [2, 3], true)) {
                return false;
            }
            try {
                (new \App\Libraries\FinanceOwnerSetupService(db_connect()))->group();
            } catch (\RuntimeException) {
                return false;
            }
        }
        if ($propertyId === PropertyContext::HOMIQ_ID && in_array($role, ['Owner HS Mitra', 'Owner Kos Mitra', 'Owner Mitra'], true)) {
            return false;
        }

        return db_connect()->table('properties')->where('id', $propertyId)->countAllResults() === 1;
    }

    /**
     * Check if the target user has a Manager role on any property
     * and the current session user is Admin (not Manager/Atmin).
     * Admin can CREATE Manager users but cannot edit/delete them.
     */
    private function isManagerProtected(int $targetUserId): bool
    {
        $currentRole = (string) session('role');
        if (in_array($currentRole, ['Manager', 'Atmin'], true)) {
            return false;
        }

        return db_connect()->table('user_property_roles')
            ->where('user_id', $targetUserId)
            ->where('role', 'Manager')
            ->countAllResults() > 0;
    }

    private function removesLastHomiqAdmin(int $propertyId, ?string $oldRole, ?string $newRole): bool
    {
        if ($propertyId !== PropertyContext::HOMIQ_ID
            || ! in_array($oldRole, ['Admin', 'Atmin'], true)
            || in_array($newRole, ['Admin', 'Atmin'], true)) {
            return false;
        }

        return db_connect()->table('user_property_roles')
            ->where('property_id', PropertyContext::HOMIQ_ID)
            ->whereIn('role', ['Admin', 'Atmin'])
            ->countAllResults() <= 1;
    }

    private function feedback(string $type, string $message)
    {
        $icon = match ($type) {
            'success' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-2 flex-shrink-0"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>',
            'danger' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-2 flex-shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
            default => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-2 flex-shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        };

        return $this->response
            ->setHeader('HX-Trigger', json_encode(['settingsChanged' => true]))
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setBody('<div class="alert alert-' . $type . ' alert-dismissible fade show d-flex align-items-center mb-0 shadow-sm" role="alert">' . $icon . '<div>' . esc($message) . '</div><button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button></div>');
    }
}
