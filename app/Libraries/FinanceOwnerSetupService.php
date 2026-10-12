<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

/** Shared Mitra configuration. Legacy property setups remain untouched for recovery. */
final class FinanceOwnerSetupService
{
    public function __construct(private BaseConnection $db)
    {
    }

    /** Trusted metadata reader for Finance and the independently authorized holding service. */
    public function group(): array
    {
        foreach (['finance_report_groups', 'finance_report_group_properties', 'finance_report_group_owner_setups'] as $table) {
            if (! $this->db->tableExists($table)) {
                throw new RuntimeException('Skema setup owner Mitra belum tersedia.', 503);
            }
        }
        $group = $this->db->table('finance_report_groups')->where('code', FinanceReportScope::GROUP_CODE)->get()->getRowArray();
        if ($group === null || (int) $group['is_active'] !== 1 || trim((string) $group['name']) === '') {
            throw new RuntimeException('Kelompok Finance Mitra belum aktif.', 503);
        }
        $members = $this->db->table('finance_report_group_properties gp')->select('p.id, p.code, p.is_active')
            ->join('properties p', 'p.id = gp.property_id')->where('gp.group_id', $group['id'])->orderBy('p.id')->get()->getResultArray();
        if (array_map('intval', array_column($members, 'id')) !== [2, 3]
            || array_column($members, 'code') !== ['HOMESTAY_MITRA', 'KOS_MITRA']
            || array_map('intval', array_column($members, 'is_active')) !== [1, 1]) {
            throw new RuntimeException('Anggota Finance Mitra tidak valid.', 503);
        }
        return $group;
    }

    public function read(): ?array
    {
        $group = $this->group();
        $setup = $this->db->table('finance_report_group_owner_setups')->where('group_id', $group['id'])->get()->getRowArray();
        if ($setup !== null) {
            try {
                self::validate($setup['owner_name'], (string) $setup['owner_share_percentage']);
            } catch (InvalidArgumentException $e) {
                throw new RuntimeException('Konfigurasi owner Mitra tidak valid.', 503, $e);
            }
        }
        return $setup;
    }

    public function legacy(): array
    {
        return $this->db->table('owner_guesthouse_setups')->whereIn('property_id', [2, 3])->orderBy('property_id')->get()->getResultArray();
    }

    public static function validate(string $name, string $percentage): void
    {
        if (trim($name) === '' || mb_strlen(trim($name)) > 150) {
            throw new InvalidArgumentException('Nama owner wajib diisi, maksimal 150 karakter.');
        }
        // Reject excess precision instead of silently letting DECIMAL round the agreed percentage.
        if (preg_match('/^(?:0|[1-9][0-9]?|100)(?:\.[0-9]{1,2})?$/D', $percentage) !== 1
            || (float) $percentage > 100) {
            throw new InvalidArgumentException('Persentase owner harus 0–100 dengan maksimal dua angka pecahan.');
        }
    }

    private function requireSetup(FinanceReportScope $scope): void
    {
        if (! $scope->isCombined() || ! $scope->allows(FinanceReportScope::OWNER_SETUP)
            || $scope->groupId !== (int) $this->group()['id']) {
            throw new RuntimeException('Akses setup owner gabungan ditolak.', 403);
        }
    }

    public function save(FinanceReportScope $scope, string $name, string $percentage, int $id = 0): void
    {
        $this->requireSetup($scope);
        self::validate($name, $percentage);
        $existing = $this->read();
        if ($id > 0 && ($existing === null || (int) $existing['id'] !== $id)) {
            throw new InvalidArgumentException('Setup owner tidak ditemukan dalam cakupan Mitra.');
        }
        $payload = ['owner_name' => trim($name), 'owner_share_percentage' => $percentage, 'updated_at' => date('Y-m-d H:i:s')];
        if ($existing === null) {
            $ok = $this->db->table('finance_report_group_owner_setups')->insert($payload + ['group_id' => $scope->groupId, 'created_at' => date('Y-m-d H:i:s')]);
        } else {
            $ok = $this->db->table('finance_report_group_owner_setups')->where('group_id', $scope->groupId)->where('id', $existing['id'])->update($payload);
        }
        if (! $ok) {
            throw new RuntimeException('Setup owner gagal disimpan.', 503);
        }
    }

    public function delete(FinanceReportScope $scope, int $id): void
    {
        $this->requireSetup($scope);
        $existing = $this->read();
        if ($existing === null || (int) $existing['id'] !== $id) {
            throw new InvalidArgumentException('Setup owner tidak ditemukan dalam cakupan Mitra.');
        }
        if (! $this->db->table('finance_report_group_owner_setups')->where('group_id', $scope->groupId)->where('id', $id)->delete()) {
            throw new RuntimeException('Setup owner gagal dihapus.', 503);
        }
    }
}
