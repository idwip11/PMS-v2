<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Add property identity without replacing any existing HomiQ records.
 *
 * Legacy writes continue to use property 1 until the application is made
 * property-aware in the following phases. This migration is forward-only:
 * removing property identity after other properties contain data is unsafe.
 */
class AddPropertiesFoundation extends Migration
{
    private const HOMIQ_ID = 1;

    /** @var list<string> */
    private const PROPERTY_TABLES = [
        'guests',
        'rooms',
        'reservations',
        'expenses',
        'inventory_items',
        'extra_bed_settings',
        'shifts',
        'cleaning_tasks',
        'financial_targets',
        'operational_budgets',
        'marketing_budgets',
        'owner_guesthouse_setups',
    ];

    public function up(): void
    {
        $this->createProperties();
        $this->seedProperties();

        foreach (self::PROPERTY_TABLES as $table) {
            if (! $this->db->fieldExists('property_id', $table)) {
                $this->forge->addColumn($table, [
                    'property_id' => [
                        'type' => 'INT',
                        'constraint' => 10,
                        'unsigned' => true,
                        'default' => self::HOMIQ_ID,
                    ],
                ]);
            }
        }

        // HomiQ's historical floor value doubles as its GH number. Keep the
        // old column intact for existing screens and copy that meaning here.
        if (! $this->db->fieldExists('guesthouse_number', 'rooms')) {
            $this->forge->addColumn('rooms', [
                'guesthouse_number' => [
                    'type' => 'TINYINT',
                    'constraint' => 3,
                    'unsigned' => true,
                    'null' => true,
                ],
            ]);
        }
        $this->db->query(
            'UPDATE rooms SET guesthouse_number = floor '
            . 'WHERE property_id = 1 AND guesthouse_number IS NULL AND floor BETWEEN 1 AND 5'
        );

        // Make room identifiers reusable across properties. Add the replacement
        // constraint first, then remove only the exact legacy unique index.
        $this->replaceLegacyUnique(
            'rooms',
            ['room_number'],
            'uq_mp_rooms_property_number',
            ['property_id', 'room_number']
        );
        $this->replaceLegacyUnique(
            'inventory_items',
            ['sku'],
            'uq_mp_inventory_property_sku',
            ['property_id', 'sku']
        );
        $this->replaceLegacyUnique(
            'owner_guesthouse_setups',
            ['guesthouse'],
            'uq_mp_owner_property_guesthouse',
            ['property_id', 'guesthouse']
        );
        foreach (['financial_targets', 'operational_budgets', 'marketing_budgets'] as $table) {
            $this->replaceLegacyUnique(
                $table,
                ['year', 'month'],
                'uq_mp_' . $table . '_property_period',
                ['property_id', 'year', 'month']
            );
        }
        $this->addUniqueIfMissing('extra_bed_settings', 'uq_mp_extra_bed_property', ['property_id']);

        foreach (self::PROPERTY_TABLES as $table) {
            $this->addPropertyForeignKey($table);
        }

        // A reservation's property must agree with both its room and guest.
        // Payments, invoices, KTP and proof files inherit that scope through
        // their existing reservation/guest relationships.
        $this->addUniqueIfMissing('rooms', 'uq_mp_rooms_id_property', ['id', 'property_id']);
        $this->addUniqueIfMissing('guests', 'uq_mp_guests_id_property', ['id', 'property_id']);
        $this->addCompositeReservationForeignKey(
            'fk_mp_reservation_room_property',
            'room_id',
            'rooms'
        );
        $this->addCompositeReservationForeignKey(
            'fk_mp_reservation_guest_property',
            'guest_id',
            'guests'
        );
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Migrasi multi properti bersifat forward-only. Gunakan backup database untuk pemulihan.'
        );
    }

    private function createProperties(): void
    {
        if ($this->db->tableExists('properties')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('properties');
    }

    private function seedProperties(): void
    {
        $now = date('Y-m-d H:i:s');
        $properties = [
            [self::HOMIQ_ID, 'HOMIQ', 'Adiputra Guesthouse by HomiQ', 1],
            [2, 'HOMESTAY_MITRA', 'Homestay Mitra', 0],
            [3, 'KOS_MITRA', 'Kos Mitra', 0],
        ];

        foreach ($properties as [$id, $code, $name, $active]) {
            $byId = $this->db->table('properties')->where('id', $id)->get()->getRowArray();
            $byCode = $this->db->table('properties')->where('code', $code)->get()->getRowArray();
            if ($byId !== null || $byCode !== null) {
                if ($byId === null || $byCode === null || (int) $byCode['id'] !== $id || $byId['code'] !== $code) {
                    throw new RuntimeException('Bentrok identitas properti ' . $code . ' (ID ' . $id . ').');
                }
                continue;
            }

            $this->db->table('properties')->insert([
                'id' => $id,
                'code' => $code,
                'name' => $name,
                'is_active' => $active,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @param list<string> $oldColumns @param list<string> $newColumns */
    private function replaceLegacyUnique(string $table, array $oldColumns, string $newName, array $newColumns): void
    {
        $this->addUniqueIfMissing($table, $newName, $newColumns);

        foreach ($this->uniqueIndexes($table) as $name => $columns) {
            if ($name !== 'PRIMARY' && $name !== $newName && $columns === $oldColumns) {
                $this->db->query('ALTER TABLE `' . $table . '` DROP INDEX `' . $name . '`');
            }
        }
    }

    /** @param list<string> $columns */
    private function addUniqueIfMissing(string $table, string $name, array $columns): void
    {
        $indexes = $this->uniqueIndexes($table);
        if (isset($indexes[$name])) {
            if ($indexes[$name] !== $columns) {
                throw new RuntimeException('Index ' . $name . ' sudah ada dengan kolom berbeda.');
            }
            return;
        }

        $this->db->query(
            'ALTER TABLE `' . $table . '` ADD UNIQUE KEY `' . $name . '` ('
            . implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns))
            . ')'
        );
    }

    /** @return array<string, list<string>> */
    private function uniqueIndexes(string $table): array
    {
        $rows = $this->db->query(
            'SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0 '
            . 'ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$table]
        )->getResultArray();
        $indexes = [];
        foreach ($rows as $row) {
            $indexes[$row['INDEX_NAME']][] = $row['COLUMN_NAME'];
        }
        return $indexes;
    }

    private function addPropertyForeignKey(string $table): void
    {
        $name = 'fk_mp_' . $table . '_property';
        if ($this->foreignKeyExists($table, $name)) {
            return;
        }
        $this->db->query(
            'ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $name . '` '
            . 'FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) '
            . 'ON DELETE RESTRICT ON UPDATE RESTRICT'
        );
    }

    private function addCompositeReservationForeignKey(string $name, string $idColumn, string $parent): void
    {
        if ($this->foreignKeyExists('reservations', $name)) {
            return;
        }
        $this->db->query(
            'ALTER TABLE `reservations` ADD CONSTRAINT `' . $name . '` '
            . 'FOREIGN KEY (`' . $idColumn . '`, `property_id`) '
            . 'REFERENCES `' . $parent . '` (`id`, `property_id`) '
            . 'ON DELETE RESTRICT ON UPDATE RESTRICT'
        );
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return $this->db->query(
            'SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? LIMIT 1',
            [$table, $name]
        )->getRowArray() !== null;
    }
}
