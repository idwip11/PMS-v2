<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

final class AddCompanyEntitlementsAndObligations extends Migration
{
    public function up(): void
    {
        $suffix = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';

        $tables = [
            'property_finance_agreements' => "id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id INT UNSIGNED NOT NULL,
                property_id INT UNSIGNED NOT NULL,
                agreement_type ENUM('revenue_share','net_profit_share','fixed_fee','full_management','custom') NOT NULL,
                calculation_base ENUM('net_revenue','gross_revenue','room_revenue','operating_profit','none') NOT NULL DEFAULT 'room_revenue',
                company_share_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                owner_share_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                fixed_fee_monthly DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                effective_from DATE NOT NULL,
                effective_to DATE NULL,
                terms_note VARCHAR(500) NOT NULL DEFAULT '',
                created_by INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY (company_id, property_id, effective_from),
                KEY (company_id, property_id, effective_to),
                FOREIGN KEY (company_id) REFERENCES companies(id),
                FOREIGN KEY (company_id, property_id) REFERENCES company_properties(company_id, property_id),
                FOREIGN KEY (created_by) REFERENCES users(id)",

            'financial_obligations' => "id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id INT UNSIGNED NOT NULL,
                property_id INT UNSIGNED NULL,
                obligation_type ENUM('owner_payable','refund_deposit','tax_withholding','other') NOT NULL,
                reference_type VARCHAR(40) NOT NULL,
                reference_id VARCHAR(120) NOT NULL,
                month CHAR(7) NULL,
                description VARCHAR(500) NOT NULL,
                amount DECIMAL(14,2) NOT NULL,
                status ENUM('estimated','finalized','settled','cancelled') NOT NULL DEFAULT 'estimated',
                due_date DATE NULL,
                created_by INT UNSIGNED NOT NULL,
                approved_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY (company_id, reference_type, reference_id),
                KEY (company_id, obligation_type, status),
                KEY (company_id, month),
                FOREIGN KEY (company_id) REFERENCES companies(id),
                FOREIGN KEY (company_id, property_id) REFERENCES company_properties(company_id, property_id),
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (approved_by) REFERENCES users(id)",

            'obligation_settlements' => "id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id INT UNSIGNED NOT NULL,
                transaction_id INT UNSIGNED NOT NULL UNIQUE,
                obligation_id INT UNSIGNED NOT NULL,
                amount DECIMAL(14,2) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY (company_id, obligation_id),
                FOREIGN KEY (company_id, transaction_id) REFERENCES cash_transactions(company_id, id),
                FOREIGN KEY (obligation_id) REFERENCES financial_obligations(id)",

            'company_expense_allocations' => "id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id INT UNSIGNED NOT NULL,
                expense_id INT UNSIGNED NOT NULL,
                property_id INT UNSIGNED NOT NULL,
                allocation_pct DECIMAL(5,2) NOT NULL,
                amount DECIMAL(14,2) NOT NULL,
                month CHAR(7) NOT NULL,
                created_by INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY (company_id, expense_id, property_id),
                KEY (company_id, month),
                FOREIGN KEY (company_id, expense_id) REFERENCES company_expenses(company_id, id),
                FOREIGN KEY (company_id, property_id) REFERENCES company_properties(company_id, property_id),
                FOREIGN KEY (created_by) REFERENCES users(id)",
        ];

        foreach ($tables as $name => $columns) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $name . ' (' . $columns . ')' . $suffix);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Tabel hak perusahaan dan kewajiban tidak boleh dihapus lewat rollback; gunakan feature flag/source rollback dan backup.');
    }
}
