<?php

namespace App\Libraries;

final class PropertyContext
{
    public const HOMIQ_ID = 1;
    public const HOMESTAY_ID = 2;
    public const KOS_ID = 3;

    /** @return list<array<string, mixed>> */
    public static function assignments(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return db_connect()->table('user_property_roles upr')
            ->select('upr.property_id, upr.role, p.code, p.name, p.is_active')
            ->join('properties p', 'p.id = upr.property_id')
            ->where('upr.user_id', $userId)
            ->orderBy('p.id', 'ASC')
            ->get()->getResultArray();
    }

    /** @return array<string, mixed>|null */
    public static function assignment(int $userId, int $propertyId): ?array
    {
        foreach (self::assignments($userId) as $assignment) {
            if ((int) $assignment['property_id'] === $propertyId) {
                return $assignment;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $assignment */
    public static function activate(array $assignment): void
    {
        session()->set([
            'activePropertyId' => (int) $assignment['property_id'],
            'activePropertyCode' => (string) $assignment['code'],
            'activePropertyName' => (string) $assignment['name'],
            'activePropertyEnabled' => (int) $assignment['is_active'] === 1,
            'role' => (string) $assignment['role'],
        ]);
    }

    public static function synchronize(): bool
    {
        $assignments = self::assignments((int) session('userId'));
        if ($assignments === []) {
            return false;
        }

        $selectedId = (int) session('activePropertyId');
        $selected = null;
        foreach ($assignments as $assignment) {
            if ((int) $assignment['property_id'] === $selectedId) {
                $selected = $assignment;
                break;
            }
        }

        if ($selected === null) {
            $selected = $assignments[0];
        }

        self::activate($selected);
        return true;
    }

    public static function legacyModulesReady(): bool
    {
        return (int) session('activePropertyId') === self::HOMIQ_ID
            && (bool) session('activePropertyEnabled');
    }

    public static function activeId(): int
    {
        return (int) session('activePropertyId');
    }

    public static function reservationPrefix(?int $propertyId = null): string
    {
        $propertyId ??= self::activeId();

        if ($propertyId === 2) {
            return 'HMT';
        }

        if ($propertyId === 3) {
            return 'KSM';
        }

        if ($propertyId === self::HOMIQ_ID) {
            return 'HMQ';
        }

        if ($propertyId > 0) {
            $prop = db_connect()->table('properties')->where('id', $propertyId)->get()->getRowArray();
            if ($prop) {
                $code = strtoupper((string) ($prop['code'] ?? ''));
                if (str_contains($code, 'KOS') || str_contains($code, 'KSM')) {
                    return 'KSM';
                }
                if (str_contains($code, 'HOMESTAY') || str_contains($code, 'HMT')) {
                    return 'HMT';
                }
                if (str_contains($code, 'HOMIQ') || str_contains($code, 'HMQ')) {
                    return 'HMQ';
                }
                $clean = preg_replace('/[^A-Z]/', '', $code);
                if (strlen($clean) >= 3) {
                    return substr($clean, 0, 3);
                }
            }
        }

        return 'HMQ';
    }

    public static function frontDeskReady(): bool
    {
        return (bool) session('activePropertyEnabled') && in_array(self::activeId(), [1, 2, 3], true);
    }

    public static function landingPath(): string
    {
        $role = (string) session('role');
        if (in_array($role, RoleAccess::OWNER_ROLES, true)) {
            return 'finance/owner';
        }

        if (self::legacyModulesReady()) {
            return RoleAccess::landingPath($role);
        }

        if (self::frontDeskReady()) {
            if (RoleAccess::allows($role, 'dashboard')) return 'dashboard';
            if (RoleAccess::allows($role, 'front_desk')) return 'reservations';
            if (RoleAccess::allows($role, 'finance')) return 'finance';
            if (RoleAccess::allows($role, 'operations')) return 'ops';
            if (RoleAccess::allows($role, 'housekeeping')) return 'housekeeping';
        }

        return 'property-pending';
    }
}
