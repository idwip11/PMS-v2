<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Authorized Finance reporting scope. Never changes the active property/session.
 * RuntimeException codes: 403 = access denied, 503 = invalid/unavailable group.
 */
final class FinanceReportScope
{
    public const GROUP_CODE = 'MITRA_COMBINED';
    public const OWNER_ROLE = 'Owner Mitra';
    public const REPORT = 'finance';
    public const OWNER_REPORT = 'owner_finance';
    public const OWNER_SETUP = 'owner_setup';

    private const PROPERTY_CODES = [1 => 'HOMIQ', 2 => 'HOMESTAY_MITRA', 3 => 'KOS_MITRA'];

    /**
     * @param list<int> $propertyIds
     * @param list<string> $permissions
     */
    private function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly array $propertyIds,
        public readonly ?int $groupId,
        public readonly array $permissions,
        public readonly ?int $ownerGuesthouse,
    ) {
    }

    public function isCombined(): bool
    {
        return $this->type === 'group';
    }

    public function allows(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public static function current(string $permission = self::REPORT, ?BaseConnection $db = null): self
    {
        return self::forUser((int) session('userId'), PropertyContext::activeId(), $permission, $db);
    }

    /**
     * Only pass the authenticated user ID and authorized active property here.
     * Scope members come from trusted metadata, not a request-supplied ID list.
     */
    public static function forUser(
        int $userId,
        int $activePropertyId,
        string $permission = self::REPORT,
        ?BaseConnection $db = null,
    ): self {
        if (! in_array($permission, [self::REPORT, self::OWNER_REPORT, self::OWNER_SETUP], true)) {
            throw new InvalidArgumentException('Izin laporan Finance tidak dikenali.');
        }
        if ($userId <= 0 || ! isset(self::PROPERTY_CODES[$activePropertyId])) {
            throw new RuntimeException('Akses laporan Finance ditolak.', 403);
        }
        $db ??= db_connect();

        // Read grants again on every resolution: revoked access must not survive in session.
        $assignments = $db->table('user_property_roles upr')
            ->select('upr.property_id, upr.role, p.code, p.name, p.is_active')
            ->join('properties p', 'p.id = upr.property_id')
            ->join('users u', 'u.id = upr.user_id')
            ->where('upr.user_id', $userId)->get()->getResultArray();
        $grants = [];
        foreach ($assignments as $assignment) {
            $grants[(int) $assignment['property_id']] = $assignment;
        }

        if ($activePropertyId === PropertyContext::HOMIQ_ID) {
            $grant = $grants[PropertyContext::HOMIQ_ID] ?? null;
            if ($grant === null || (int) $grant['is_active'] !== 1 || $grant['code'] !== 'HOMIQ') {
                throw new RuntimeException('Akses laporan HomiQ ditolak.', 403);
            }
            $role = (string) $grant['role'];
            $ownerGuesthouse = RoleAccess::ownerGuesthouse($role);
            $permissions = RoleAccess::allows($role, 'finance')
                ? [self::REPORT, self::OWNER_REPORT, self::OWNER_SETUP]
                : ($ownerGuesthouse !== null ? [self::OWNER_REPORT] : []);
            if (! in_array($permission, $permissions, true)) {
                throw new RuntimeException('Akses laporan HomiQ ditolak.', 403);
            }

            return new self('property', (string) $grant['name'], [1], null, $permissions, $ownerGuesthouse);
        }

        // Missing membership/permission must never degrade to a partial Mitra report.
        foreach ([2, 3] as $propertyId) {
            $grant = $grants[$propertyId] ?? null;
            if ($grant === null || (int) $grant['is_active'] !== 1
                || $grant['code'] !== self::PROPERTY_CODES[$propertyId]) {
                throw new RuntimeException('Laporan gabungan memerlukan akses ke Homestay dan Kos Mitra.', 403);
            }
        }
        $staff = RoleAccess::allows((string) $grants[2]['role'], 'finance')
            && RoleAccess::allows((string) $grants[3]['role'], 'finance');
        // Legacy single-property owner roles are deliberately not group-owner aliases.
        $owner = $grants[2]['role'] === self::OWNER_ROLE && $grants[3]['role'] === self::OWNER_ROLE;
        $permissions = $staff ? [self::REPORT, self::OWNER_REPORT, self::OWNER_SETUP]
            : ($owner ? [self::OWNER_REPORT] : []);
        if (! in_array($permission, $permissions, true)) {
            throw new RuntimeException('Izin kedua properti belum mencakup laporan gabungan yang diminta.', 403);
        }

        foreach (['finance_report_groups', 'finance_report_group_properties', 'finance_report_group_owner_setups'] as $table) {
            if (! $db->tableExists($table)) {
                throw new RuntimeException('Skema kelompok Finance Mitra belum tersedia.', 503);
            }
        }
        $group = $db->table('finance_report_groups')->where('code', self::GROUP_CODE)->get()->getRowArray();
        if ($group === null || (int) $group['is_active'] !== 1 || trim((string) $group['name']) === '') {
            throw new RuntimeException('Kelompok Finance Mitra belum aktif atau tidak valid.', 503);
        }
        $memberIds = array_map('intval', array_column($db->table('finance_report_group_properties')
            ->where('group_id', $group['id'])->orderBy('property_id')->get()->getResultArray(), 'property_id'));
        if ($memberIds !== [2, 3]) {
            throw new RuntimeException('Anggota kelompok Finance harus tepat Homestay dan Kos Mitra.', 503);
        }

        return new self('group', (string) $group['name'], $memberIds, (int) $group['id'], $permissions, null);
    }
}
