<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class CompanyFinanceAccess
{
    public const ROLES = [
        'Company Viewer' => ['view', 'export'],
        'Company Finance' => ['view', 'export', 'post', 'reconcile'],
        'Company Approver' => ['view', 'export', 'post', 'approve', 'reconcile', 'close', 'manage_access'],
    ];

    public static function allows(int $userId, int $companyId, string $permission, ?BaseConnection $db = null): bool
    {
        $db ??= db_connect();
        if ($userId <= 0 || $companyId <= 0 || ! $db->tableExists('company_user_roles')) return false;
        $grant = $db->table('company_user_roles r')->select('r.role')
            ->join('companies c', 'c.id = r.company_id')->join('users u', 'u.id = r.user_id')
            ->where('r.user_id', $userId)->where('r.company_id', $companyId)->where('c.is_active', 1)
            ->get()->getRowArray();
        if ($grant !== null && in_array($permission, self::ROLES[$grant['role']] ?? [], true)) {
            return true;
        }

        $userRole = (string) (session('role') ?? '');
        if ($userRole === '' && $db->tableExists('users')) {
            $uRow = $db->table('users')->select('role')->where('id', $userId)->get()->getRowArray();
            $userRole = (string) ($uRow['role'] ?? '');
        }
        if ($userRole === 'Manager') {
            return in_array($permission, self::ROLES['Company Approver'], true);
        }
        if ($userRole === 'Finance') {
            return in_array($permission, self::ROLES['Company Finance'], true);
        }

        return false;
    }

    public static function require(int $userId, int $companyId, string $permission, ?BaseConnection $db = null): void
    {
        if (! self::allows($userId, $companyId, $permission, $db)) throw new RuntimeException('Akses holding ditolak.');
    }
}
