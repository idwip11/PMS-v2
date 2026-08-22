<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInventoryVendorWhatsapp extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('vendor_whatsapp', 'inventory_items')) {
            return;
        }

        $this->forge->addColumn('inventory_items', [
            'vendor_whatsapp' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
                'after' => 'contact_vendor',
            ],
        ]);
    }

    public function down(): void
    {
        if (! $this->db->fieldExists('vendor_whatsapp', 'inventory_items')) {
            return;
        }

        $this->forge->dropColumn('inventory_items', 'vendor_whatsapp');
    }
}
