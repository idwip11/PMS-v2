<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHomiqSchema extends Migration
{
    public function up(): void
    {
        $this->createUsers();
        $this->createGuests();
        $this->createRooms();
        $this->createReservations();
        $this->createPayments();
        $this->createInvoiceItems();
        $this->createExpenses();
        $this->createInventoryItems();
        $this->createMaintenanceTickets();
        $this->createShifts();
        $this->createCleaningTasks();
        $this->createTaskAssignees();
        $this->createFinancialTargets();
        $this->createMarketingCampaigns();
        $this->createLoyaltyMembers();
        $this->createOperationalBudgets();
        $this->createMarketingBudgets();
    }

    public function down(): void
    {
        foreach (['marketing_budgets', 'operational_budgets', 'loyalty_members', 'marketing_campaigns', 'financial_targets', 'task_assignees', 'cleaning_tasks', 'shifts', 'inventory_stock_movements', 'maintenance_ticket_items', 'maintenance_tickets', 'inventory_items', 'expenses', 'invoice_items', 'payments', 'reservations', 'rooms', 'guests', 'users'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function id(): array
    {
        return ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true];
    }

    private function timestamps(): array
    {
        return ['created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]];
    }

    private function createUsers(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'username' => ['type' => 'VARCHAR', 'constraint' => 100],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'full_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'role' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'Staff'],
            'avatar_url' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('users');
    }

    private function createGuests(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'full_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'email' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'origin' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->createTable('guests');
    }

    private function createRooms(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'room_number' => ['type' => 'VARCHAR', 'constraint' => 20],
            'room_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'price_per_night' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Available'],
            'floor' => ['type' => 'INT', 'constraint' => 3, 'null' => true],
            'assigned_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('room_number');
        $this->forge->addForeignKey('assigned_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('rooms');
    }

    private function createReservations(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'guest_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'room_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'order_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'check_in_date' => ['type' => 'DATE'],
            'check_out_date' => ['type' => 'DATE'],
            'member_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Confirmed'],
            'source' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'guest_count' => ['type' => 'INT', 'constraint' => 3, 'default' => 1],
            'special_request' => ['type' => 'TEXT', 'null' => true],
            'is_reconciled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'has_breakfast' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'has_extrabed' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'has_late_checkout' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'has_laundry' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'breakfast_cost' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'laundry_cost' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'massage_cost' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('order_id');
        $this->forge->addKey(['room_id', 'check_in_date', 'check_out_date']);
        $this->forge->addForeignKey('guest_id', 'guests', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('room_id', 'rooms', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('reservations');
    }

    private function createPayments(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'reservation_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'order_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'payment_date' => ['type' => 'DATE', 'null' => true],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'type' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'Payment'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Pending'],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey('reservation_id');
        $this->forge->addForeignKey('reservation_id', 'reservations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('payments');
    }

    private function createInvoiceItems(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'reservation_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'order_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('reservation_id', 'reservations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('invoice_items');
    }

    private function createExpenses(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'logged_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255],
            'category' => ['type' => 'VARCHAR', 'constraint' => 100],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'date_incurred' => ['type' => 'DATE'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Pending'],
            'receipt_url' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'guesthouse' => ['type' => 'INT', 'constraint' => 2, 'default' => 0],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('logged_by_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('expenses');
    }

    private function createInventoryItems(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'category' => ['type' => 'VARCHAR', 'constraint' => 100],
            'current_stock' => ['type' => 'INT', 'constraint' => 10, 'default' => 0],
            'min_threshold' => ['type' => 'INT', 'constraint' => 10, 'default' => 10],
            'unit' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pcs'],
            'contact_vendor' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'vendor_whatsapp' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'item_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Amenity'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'sku' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey('item_type');
        $this->forge->addUniqueKey('sku');
        $this->forge->createTable('inventory_items');
    }

    private function createMaintenanceTickets(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'room_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'reported_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'issue_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'TEXT', 'null' => true],
            'priority' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Medium'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Open'],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('room_id', 'rooms', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('reported_by_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('maintenance_tickets');
    }

    private function createShifts(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'shift_date' => ['type' => 'DATE'],
            'start_time' => ['type' => 'TIME'],
            'end_time' => ['type' => 'TIME'],
            'shift_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Scheduled'],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shifts');
    }

    private function createCleaningTasks(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'description' => ['type' => 'VARCHAR', 'constraint' => 255],
            'room_area' => ['type' => 'VARCHAR', 'constraint' => 100],
            'scheduled_date' => ['type' => 'DATE'],
            'priority' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Medium'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Pending'],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->createTable('cleaning_tasks');
    }

    private function createTaskAssignees(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'task_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['task_id', 'user_id']);
        $this->forge->addForeignKey('task_id', 'cleaning_tasks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('task_assignees');
    }

    private function createFinancialTargets(): void
    {
        $this->createMonthlyAmountTable('financial_targets', 'target_amount', '15,2');
    }

    private function createOperationalBudgets(): void
    {
        $this->createMonthlyAmountTable('operational_budgets', 'projected_amount');
    }

    private function createMarketingBudgets(): void
    {
        $this->createMonthlyAmountTable('marketing_budgets', 'budget_amount');
    }

    private function createMonthlyAmountTable(string $table, string $amountField, string $precision = '10,2'): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'year' => ['type' => 'INT', 'constraint' => 4],
            'month' => ['type' => 'INT', 'constraint' => 2],
            $amountField => ['type' => 'DECIMAL', 'constraint' => $precision],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['year', 'month']);
        $this->forge->createTable($table);
    }

    private function createMarketingCampaigns(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'member_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'discount_details' => ['type' => 'TEXT'],
            'target_audience' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'All Guests'],
            'start_date' => ['type' => 'DATE'],
            'end_date' => ['type' => 'DATE'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Active'],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey('code');
        $this->forge->createTable('marketing_campaigns');
    }

    private function createLoyaltyMembers(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'order_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'member_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'points_balance' => ['type' => 'INT', 'constraint' => 10, 'default' => 0],
            'joined_at' => ['type' => 'DATETIME', 'null' => true],
            'last_activity' => ['type' => 'DATETIME', 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('member_id');
        $this->forge->createTable('loyalty_members');
    }
}
