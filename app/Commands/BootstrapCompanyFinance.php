<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;
use Throwable;

/** Server operator bootstrap only; never called by web/property Settings. */
final class BootstrapCompanyFinance extends BaseCommand
{
    protected $group = 'Finance';
    protected $name = 'company:bootstrap';
    protected $description = 'Buat holding baru dan grant pertama secara eksplisit; tidak mengisi rekening/saldo.';
    protected $usage = 'company:bootstrap --code CODE --name "Nama" --user USERNAME --properties 1,2,3 --confirm CODE';
    protected $options = ['--code' => 'Kode unik perusahaan.', '--name' => 'Nama holding yang disetujui.', '--user' => 'Username existing penerima Company Approver pertama.', '--properties' => 'ID properti yang eksplisit dicakup.', '--confirm' => 'Ulang kode perusahaan untuk konfirmasi operator.'];

    public function run(array $params)
    {
        $code = (string) CLI::getOption('code'); $name = trim((string) CLI::getOption('name'));
        if (preg_match('/^[A-Z0-9_]{2,40}$/D', $code) !== 1 || $name === '' || mb_strlen($name) > 150 || CLI::getOption('confirm') !== $code) throw new RuntimeException('Kode/nama/konfirmasi eksplisit wajib.');
        $raw = (string) CLI::getOption('properties');
        if (preg_match('/^[1-9][0-9]*(,[1-9][0-9]*)*$/D', $raw) !== 1) throw new RuntimeException('Daftar ID properti wajib eksplisit.');
        $properties = array_values(array_unique(array_map('intval', explode(',', $raw))));
        $db = db_connect();
        $user = $db->table('users')->where('username', (string) CLI::getOption('user'))->get()->getRowArray();
        if (! $user || $db->table('properties')->whereIn('id', $properties)->countAllResults() !== count($properties)) throw new RuntimeException('Username/properti tidak ditemukan.');
        if (! $db->transBegin()) throw new RuntimeException('Tidak dapat membuka transaksi.');
        try {
            if ($db->table('companies')->where('code', $code)->countAllResults() !== 0) throw new RuntimeException('Holding sudah ada; bootstrap tidak boleh mengganti grant/mapping lama.');
            if ($db->table('company_properties')->whereIn('property_id', $properties)->countAllResults() > 0) throw new RuntimeException('Properti sudah dipetakan ke holding lain.');
            $db->table('companies')->insert(['code' => $code, 'name' => $name, 'is_active' => 1, 'created_at' => gmdate('Y-m-d H:i:s')]);
            $company = (int) $db->insertID();
            foreach ($properties as $id) $db->table('company_properties')->insert(['company_id' => $company, 'property_id' => $id]);
            $db->table('company_user_roles')->insert(['company_id' => $company, 'user_id' => $user['id'], 'role' => 'Company Approver', 'created_at' => gmdate('Y-m-d H:i:s')]);
            $db->table('company_finance_audit')->insert(['company_id' => $company, 'user_id' => $user['id'], 'action' => 'operator_bootstrap', 'reference' => $code, 'details' => json_encode(['properties' => $properties, 'initial_role' => 'Company Approver', 'method' => 'server_cli'], JSON_THROW_ON_ERROR), 'created_at' => gmdate('Y-m-d H:i:s')]);
            if (! $db->transStatus() || ! $db->transCommit()) throw new RuntimeException('Bootstrap gagal.');
            CLI::write('Holding dibuat: ID ' . $company . '. Grant pertama eksplisit kepada ' . $user['username'] . '. Tidak ada saldo/rekening fiktif.', 'green');
        } catch (Throwable $e) {
            $db->transRollback(); $db->resetTransStatus(); throw $e;
        }
    }
}
