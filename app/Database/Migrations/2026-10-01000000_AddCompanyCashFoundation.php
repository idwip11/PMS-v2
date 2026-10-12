<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

final class AddCompanyCashFoundation extends Migration
{
    public function up(): void
    {
        // Empty foundation only: no implicit holding, access grant, or bank balance.
        $suffix = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
        $definitions = [
            'companies' => 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(40) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL, is_active TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL',
            'company_properties' => 'company_id INT UNSIGNED NOT NULL, property_id INT UNSIGNED NOT NULL, PRIMARY KEY(company_id,property_id), UNIQUE KEY(property_id), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(property_id) REFERENCES properties(id)',
            'company_user_roles' => "company_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, role VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(company_id,user_id), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(user_id) REFERENCES users(id)",
            'cash_accounts' => "id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id INT UNSIGNED NOT NULL, property_id INT UNSIGNED NULL, code VARCHAR(40) NOT NULL, name VARCHAR(150) NOT NULL, account_type ENUM('cash','bank') NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'IDR', is_active TINYINT NOT NULL DEFAULT 1, created_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, UNIQUE KEY(company_id,code), UNIQUE KEY(company_id,id), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(company_id,property_id) REFERENCES company_properties(company_id,property_id), FOREIGN KEY(created_by) REFERENCES users(id)",
            'cash_opening_balances' => 'company_id INT UNSIGNED NOT NULL, account_id INT UNSIGNED NOT NULL, effective_at DATETIME NOT NULL, amount DECIMAL(14,2) NOT NULL, verified_by INT UNSIGNED NOT NULL, reason VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(account_id), FOREIGN KEY(company_id,account_id) REFERENCES cash_accounts(company_id,id), FOREIGN KEY(verified_by) REFERENCES users(id)',
            'company_finance_periods' => "company_id INT UNSIGNED NOT NULL, month CHAR(7) NOT NULL, status ENUM('open','closed') NOT NULL DEFAULT 'open', changed_by INT UNSIGNED NOT NULL, reason VARCHAR(500) NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(company_id,month), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(changed_by) REFERENCES users(id)",
            'cash_transactions' => "id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id INT UNSIGNED NOT NULL, property_id INT UNSIGNED NULL, event_key VARCHAR(120) NOT NULL, payload_hash CHAR(64) NOT NULL, kind ENUM('receipt','disbursement','transfer','reversal') NOT NULL, classification ENUM('operating','investing','financing','internal') NOT NULL, effective_at DATETIME NOT NULL, description VARCHAR(500) NOT NULL, source_type VARCHAR(40) NOT NULL, source_reference VARCHAR(120) NOT NULL, reverses_id INT UNSIGNED NULL, posted_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, UNIQUE KEY(company_id,event_key), UNIQUE KEY(company_id,id), UNIQUE KEY(reverses_id), KEY(company_id,effective_at), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(company_id,property_id) REFERENCES company_properties(company_id,property_id), FOREIGN KEY(company_id,reverses_id) REFERENCES cash_transactions(company_id,id), FOREIGN KEY(posted_by) REFERENCES users(id)",
            'cash_entries' => "id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id INT UNSIGNED NOT NULL, transaction_id INT UNSIGNED NOT NULL, account_id INT UNSIGNED NOT NULL, amount DECIMAL(14,2) NOT NULL, UNIQUE KEY(transaction_id,account_id), KEY(company_id,account_id), FOREIGN KEY(company_id,transaction_id) REFERENCES cash_transactions(company_id,id), FOREIGN KEY(company_id,account_id) REFERENCES cash_accounts(company_id,id)",
            'company_finance_audit' => 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, action VARCHAR(40) NOT NULL, reference VARCHAR(120) NOT NULL, details TEXT NOT NULL, created_at DATETIME NOT NULL, KEY(company_id,created_at), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(user_id) REFERENCES users(id)',
        ];
        foreach ($definitions as $name => $columns) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $name . ' (' . $columns . ')' . $suffix);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Ledger holding tidak boleh dihapus melalui rollback. Nonaktifkan fitur atau pulihkan backup terverifikasi.');
    }
}
