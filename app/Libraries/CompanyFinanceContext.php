<?php

namespace App\Libraries;

final class CompanyFinanceContext
{
    public static function ensureDefaultCompany(): ?int
    {
        $db = db_connect();
        if (! $db->tableExists('companies')) return null;
        $company = $db->table('companies')->where('is_active', 1)->orderBy('id', 'ASC')->get()->getRowArray();
        if ($company) {
            $companyId = (int) $company['id'];
            if ($db->tableExists('properties') && $db->tableExists('company_properties')) {
                $mappedCount = $db->table('company_properties')->where('company_id', $companyId)->countAllResults();
                if ($mappedCount === 0) {
                    $props = $db->table('properties')->select('id')->get()->getResultArray();
                    foreach ($props as $p) {
                        $db->table('company_properties')->ignore(true)->insert([
                            'company_id' => $companyId,
                            'property_id' => (int) $p['id'],
                        ]);
                    }
                }
            }
            return $companyId;
        }

        $db->table('companies')->insert([
            'code' => 'HOMIQ_HOLDING',
            'name' => 'HomiQ Group',
            'is_active' => 1,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $companyId = (int) $db->insertID();
        if ($db->tableExists('properties') && $db->tableExists('company_properties')) {
            $props = $db->table('properties')->select('id')->get()->getResultArray();
            foreach ($props as $p) {
                $db->table('company_properties')->ignore(true)->insert([
                    'company_id' => $companyId,
                    'property_id' => (int) $p['id'],
                ]);
            }
        }
        return $companyId;
    }

    public static function assignments(int $userId): array
    {
        $db = db_connect();
        if ($userId <= 0 || ! $db->tableExists('company_user_roles')) return [];

        $companyId = self::ensureDefaultCompany();

        // Auto-assign holding access for Manager and Finance system roles if not yet assigned
        $userRole = (string) (session('role') ?? '');
        if ($userRole === '' && $db->tableExists('users')) {
            $uRow = $db->table('users')->select('role')->where('id', $userId)->get()->getRowArray();
            $userRole = (string) ($uRow['role'] ?? '');
        }

        if ($companyId !== null && ($userRole === 'Manager' || $userRole === 'Finance')) {
            $exists = $db->table('company_user_roles')->where('company_id', $companyId)->where('user_id', $userId)->countAllResults();
            if ($exists === 0) {
                $holdingRole = $userRole === 'Manager' ? 'Company Approver' : 'Company Finance';
                $db->table('company_user_roles')->insert([
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'role' => $holdingRole,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }
        }

        $rows = $db->table('company_user_roles r')->select('c.id, c.name, c.code, r.role')
            ->join('companies c', 'c.id = r.company_id')->where('r.user_id', $userId)
            ->where('c.is_active', 1)->orderBy('c.id')->get()->getResultArray();
        return array_values(array_filter($rows, static fn ($row) => isset(CompanyFinanceAccess::ROLES[$row['role']])));
    }

    public static function resolve(int $userId, mixed $requested = null): ?int
    {
        $rows = self::assignments($userId);
        if ($requested !== null) {
            $id = filter_var($requested, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) return null;
            foreach ($rows as $row) if ((int) $row['id'] === $id) return $id;
            return null;
        }
        return isset($rows[0]) ? (int) $rows[0]['id'] : null;
    }

    public static function properties(int $userId, int $companyId): array
    {
        CompanyFinanceAccess::require($userId, $companyId, 'view');
        return db_connect()->table('company_properties cp')->select('p.id,p.name,p.code,p.is_active')
            ->join('properties p', 'p.id = cp.property_id')->where('cp.company_id', $companyId)
            ->orderBy('p.id')->get()->getResultArray();
    }
}
