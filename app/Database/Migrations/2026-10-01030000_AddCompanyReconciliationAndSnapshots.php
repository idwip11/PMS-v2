<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

final class AddCompanyReconciliationAndSnapshots extends Migration
{
    public function up(): void
    {
        $suffix = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';

        $tables = [
            'company_bank_reconciliations' => "id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id INT UNSIGNED NOT NULL,
                account_id INT UNSIGNED NOT NULL,
                month CHAR(7) NOT NULL,
                statement_date DATE NOT NULL,
                statement_balance DECIMAL(14,2) NOT NULL,
                ledger_balance DECIMAL(14,2) NOT NULL,
                discrepancy DECIMAL(14,2) NOT NULL,
                status ENUM('reconciled','discrepancy') NOT NULL,
                notes VARCHAR(500) NOT NULL DEFAULT '',
                reference_document VARCHAR(150) NOT NULL DEFAULT '',
                reconciled_by INT UNSIGNED NOT NULL,
                reconciled_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY (company_id, account_id, month),
                KEY (company_id, month),
                FOREIGN KEY (company_id) REFERENCES companies(id),
                FOREIGN KEY (company_id, account_id) REFERENCES cash_accounts(company_id, id),
                FOREIGN KEY (reconciled_by) REFERENCES users(id)",

            'company_finance_snapshots' => "id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id INT UNSIGNED NOT NULL,
                month CHAR(7) NOT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                snapshot_data LONGTEXT NOT NULL,
                snapshot_hash CHAR(64) NOT NULL,
                closed_by INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                KEY (company_id, month),
                FOREIGN KEY (company_id) REFERENCES companies(id),
                FOREIGN KEY (closed_by) REFERENCES users(id)",
        ];

        foreach ($tables as $name => $columns) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $name . ' (' . $columns . ')' . $suffix);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Tabel rekonsiliasi dan snapshot laporan holding tidak boleh dihapus lewat rollback; gunakan feature flag/source rollback dan backup.');
    }
}
