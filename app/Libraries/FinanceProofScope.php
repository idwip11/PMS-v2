<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Read-only proof access; resolves fresh grants without opening operational objects. */
final class FinanceProofScope
{
    public static function current(string $permission, ?BaseConnection $db = null): array
    {
        $db ??= db_connect();
        $property = PropertyContext::activeId();
        $grant = $db->table('user_property_roles r')->select('r.role')
            ->join('properties p', 'p.id = r.property_id')->join('users u', 'u.id = r.user_id')
            ->where('r.user_id', (int) session('userId'))->where('r.property_id', $property)
            ->where('p.is_active', 1)->get()->getRowArray();
        if (! in_array($permission, ['payment_proof', 'expense_receipt'], true)
            || $grant === null || ! RoleAccess::allows($grant['role'], $permission)) {
            throw new RuntimeException('Akses bukti keuangan ditolak.', 403);
        }
        $scope = null;
        if (in_array($grant['role'], RoleAccess::OWNER_ROLES, true)) {
            // Incomplete/legacy owners never fall back to single-property proof access.
            $scope = FinanceReportScope::current(FinanceReportScope::OWNER_REPORT, $db);
        } elseif (in_array($property, [2, 3], true) && RoleAccess::allows($grant['role'], 'finance')) {
            try {
                $scope = FinanceReportScope::current(FinanceReportScope::OWNER_REPORT, $db);
            } catch (RuntimeException $e) {
                if ($e->getCode() !== 403) {
                    throw $e;
                }
                // Staff keep existing local proof permission if they lack the partner grant.
            }
        }
        return ['propertyIds' => $scope?->propertyIds ?? [$property], 'ownerGuesthouse' => $scope?->ownerGuesthouse];
    }
}
