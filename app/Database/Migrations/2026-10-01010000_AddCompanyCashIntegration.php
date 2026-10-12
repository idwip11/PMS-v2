<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

final class AddCompanyCashIntegration extends Migration
{
    public function up(): void
    {
        $suffix = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
        $posting = 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id INT UNSIGNED NOT NULL, transaction_id INT UNSIGNED NOT NULL UNIQUE, amount DECIMAL(14,2) NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(company_id,transaction_id) REFERENCES cash_transactions(company_id,id), ';
        $tables = [
            'cash_payment_receipts' => $posting . 'payment_id INT UNSIGNED NOT NULL, withheld_fee DECIMAL(14,2) NOT NULL DEFAULT 0, source_amount DECIMAL(14,2) NOT NULL, KEY(company_id,payment_id), FOREIGN KEY(payment_id) REFERENCES payments(id)',
            'cash_payment_opening_links' => 'company_id INT UNSIGNED NOT NULL, payment_id INT UNSIGNED NOT NULL PRIMARY KEY, account_id INT UNSIGNED NOT NULL, amount DECIMAL(14,2) NOT NULL, withheld_fee DECIMAL(14,2) NOT NULL DEFAULT 0, received_at DATETIME NOT NULL, verified_by INT UNSIGNED NOT NULL, reason VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(company_id,account_id) REFERENCES cash_accounts(company_id,id), FOREIGN KEY(payment_id) REFERENCES payments(id), FOREIGN KEY(verified_by) REFERENCES users(id)',
            'cash_payment_refunds' => $posting . 'payment_id INT UNSIGNED NOT NULL, KEY(company_id,payment_id), FOREIGN KEY(payment_id) REFERENCES payments(id)',
            'expense_settlements' => $posting . 'expense_id INT UNSIGNED NOT NULL, KEY(company_id,expense_id), FOREIGN KEY(expense_id) REFERENCES expenses(id)',
            'company_expenses' => "id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id INT UNSIGNED NOT NULL, event_key VARCHAR(120) NOT NULL, description VARCHAR(500) NOT NULL, category VARCHAR(100) NOT NULL, amount DECIMAL(14,2) NOT NULL, date_incurred DATE NOT NULL, status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending', created_by INT UNSIGNED NOT NULL, approved_by INT UNSIGNED NULL, created_at DATETIME NOT NULL, UNIQUE KEY(company_id,event_key), UNIQUE KEY(company_id,id), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(approved_by) REFERENCES users(id)",
            'company_expense_settlements' => $posting . 'expense_id INT UNSIGNED NOT NULL, KEY(company_id,expense_id), FOREIGN KEY(company_id,expense_id) REFERENCES company_expenses(company_id,id)',
        ];
        foreach ($tables as $name => $columns) $this->db->query('CREATE TABLE IF NOT EXISTS ' . $name . ' (' . $columns . ')' . $suffix);
    }

    public function down(): void
    {
        throw new RuntimeException('Integrasi kas tidak boleh dihapus lewat rollback; gunakan feature/source rollback dan backup.');
    }
}
