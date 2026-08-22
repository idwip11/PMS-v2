<?php

namespace App\Commands;

use App\Libraries\MaintenanceScheduleService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class GenerateDueMaintenance extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'maintenance:generate-due';
    protected $description = 'Membuat Work Order preventive untuk jadwal yang sudah masuk periode pengingat.';

    public function run(array $params)
    {
        $result = (new MaintenanceScheduleService())->generateDueTickets();
        CLI::write(
            'Jadwal due: ' . $result['due'] . ' | Work Order dibuat: ' . $result['created'] . ' | Duplikat dilewati: ' . $result['skipped'],
            'green'
        );
    }
}
