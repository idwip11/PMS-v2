<?php

namespace App\Libraries;

final class RoleAccess
{
    /** @var list<string> */
    public const OWNER_ROLES = ['Owner GH 1', 'Owner GH 2', 'Owner GH 3', 'Owner GH 4', 'Owner GH 5'];

    /** @var array<string,list<string>> */
    private const PERMISSIONS = [
        'pending' => [],
        'owner_finance' => self::OWNER_ROLES,
        'dashboard' => ['Atmin', 'Admin', 'Manager', 'Finance', 'Marketing', 'Operational', 'Staff'],
        'finance' => ['Atmin', 'Manager', 'Finance', 'Operational'],
        'analytics' => ['Atmin', 'Admin', 'Manager', 'Finance', 'Marketing', 'Operational', 'Staff'],
        'operations' => ['Atmin', 'Admin', 'Manager', 'Finance', 'Operational'],
        'front_desk' => ['Atmin', 'Admin', 'Manager', 'Finance', 'Marketing', 'Operational', 'Staff'],
        'payment_proof' => ['Atmin', 'Admin', 'Manager', 'Finance', 'Marketing', 'Operational', 'Staff', 'Owner GH 1', 'Owner GH 2', 'Owner GH 3', 'Owner GH 4', 'Owner GH 5'],
        'expense_receipt' => ['Atmin', 'Admin', 'Manager', 'Finance', 'Operational', 'Owner GH 1', 'Owner GH 2', 'Owner GH 3', 'Owner GH 4', 'Owner GH 5'],
        'housekeeping' => ['Atmin', 'Admin', 'Manager', 'Finance', 'Marketing', 'Operational', 'Housekeeping', 'Staff'],
        'settings' => ['Atmin', 'Admin'],
        'support' => ['Atmin', 'Admin', 'Manager', 'Finance', 'Marketing', 'Operational', 'Staff'],
    ];

    public static function allows(string $role, string $permission): bool
    {
        return in_array($role, self::PERMISSIONS[$permission] ?? [], true);
    }

    public static function landingPath(string $role): string
    {
        if (in_array($role, self::OWNER_ROLES, true)) {
            return 'finance/owner';
        }

        return $role === 'Housekeeping' ? 'housekeeping' : 'dashboard';
    }

    public static function masksDashboardFinancials(string $role): bool
    {
        return in_array($role, ['Admin', 'Staff'], true);
    }

    public static function ownerGuesthouse(string $role): ?int
    {
        if (preg_match('/^Owner GH ([1-5])$/', $role, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
