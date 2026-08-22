<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveMarketingCampaignAndLoyalty extends Migration
{
    public function up(): void
    {
        $this->forge->dropTable('loyalty_members', true);
        $this->forge->dropTable('marketing_campaigns', true);
    }

    public function down(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'member_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'discount_details' => ['type' => 'TEXT'],
            'target_audience' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'All Guests'],
            'start_date' => ['type' => 'DATE'],
            'end_date' => ['type' => 'DATE'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('code');
        $this->forge->createTable('marketing_campaigns');

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'order_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'member_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'points_balance' => ['type' => 'INT', 'constraint' => 10, 'default' => 0],
            'joined_at' => ['type' => 'DATETIME', 'null' => true],
            'last_activity' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('member_id');
        $this->forge->createTable('loyalty_members');
    }
}
