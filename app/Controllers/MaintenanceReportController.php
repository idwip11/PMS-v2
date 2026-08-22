<?php

namespace App\Controllers;

use App\Libraries\MaintenanceReportService;

class MaintenanceReportController extends BaseController
{
    public function exportCsv()
    {
        $service = new MaintenanceReportService();
        $filters = $service->filters($this->request->getGet());
        $rows = $service->history($filters);

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            return redirect()->back()->with('error', 'Laporan gagal dibuat.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Work Order', 'Kamar', 'Tipe', 'Kategori', 'Aset', 'Masalah', 'Prioritas', 'Penanggung Jawab', 'Vendor', 'Dibuat', 'Mulai', 'Selesai', 'Status', 'Diagnosis', 'Penyelesaian', 'Biaya Material', 'Biaya Tenaga', 'Biaya Eksternal', 'Total Biaya']);
        foreach ($rows as $row) {
            $material = (float) ($row['material_cost'] ?? 0);
            $labor = (float) ($row['labor_cost'] ?? 0);
            $external = (float) ($row['external_cost'] ?? 0);
            fputcsv($stream, [
                $row['ticket_number'] ?? ('WO-' . $row['id']),
                $row['room_number'],
                $row['ticket_type'] ?? 'Corrective',
                $row['category_name'] ?? $row['issue_type'],
                $row['asset_name'] ?? '',
                $row['description'],
                $row['priority'],
                $row['assigned_to_name'] ?? '',
                $row['vendor_name'] ?? '',
                $row['created_at'],
                $row['started_at'] ?? '',
                $row['completed_at'] ?? '',
                str_replace('_', ' ', (string) $row['status']),
                $row['diagnosis'] ?? '',
                $row['resolution'] ?? '',
                number_format($material, 2, '.', ''),
                number_format($labor, 2, '.', ''),
                number_format($external, 2, '.', ''),
                number_format($material + $labor + $external, 2, '.', ''),
            ]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="laporan-maintenance-' . date('Ymd-His') . '.csv"')
            ->setBody($csv === false ? '' : $csv);
    }
}
