<?php

namespace App\Filters;

use App\Libraries\PropertyContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Keep legacy unscoped modules on HomiQ until phases 3-5 scope their queries.
 * Also reject direct object URLs outside the active property's boundary.
 */
final class PropertyReadyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $path = trim($request->getUri()->getPath(), '/');
        $isPhaseFour = $path === '' || preg_match('#^(?:dashboard|marketing|reservations|payments|guests|room-map|room-pricing|finance|ops|housekeeping|settings|support)(?:/|$)#', $path) === 1;
        if (! PropertyContext::legacyModulesReady()
            && (! $isPhaseFour || ! PropertyContext::frontDeskReady())) {
            if ($request->hasHeader('HX-Request')) {
                return service('response')->setStatusCode(403)->setBody('Properti ini masih disiapkan.');
            }

            return redirect()->to(site_url('property-pending'));
        }

        if ($propertyId = PropertyContext::activeId()) {
            if ($propertyId !== PropertyContext::HOMIQ_ID
                && preg_match('#^ops/maintenance/categories(?:/|$)#', $path) === 1) {
                return service('response')->setStatusCode(403)->setBody('Fitur ini hanya tersedia untuk HomiQ.');
            }
        }

        $propertyId = PropertyContext::activeId();
        if (! $this->objectBelongsToProperty($path, $propertyId, strtoupper($request->getMethod()) === 'GET')) {
            return service('response')->setStatusCode(404)->setBody('Data tidak ditemukan.');
        }
        if (strtoupper($request->getMethod()) === 'POST'
            && ! $this->postedObjectsBelongToProperty($request, $propertyId)) {
            return service('response')->setStatusCode(404)->setBody('Data tidak ditemukan.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function objectBelongsToProperty(string $path, int $propertyId, bool $readOnly): bool
    {
        $db = db_connect();
        if (in_array($propertyId, [2, 3], true) && preg_match('#^finance/owner-setup/(\d+)(?:/|$)#', $path, $matches) === 1) {
            try {
                $scope = \App\Libraries\FinanceReportScope::current(\App\Libraries\FinanceReportScope::OWNER_SETUP);
                return $db->table('finance_report_group_owner_setups')->where('group_id', $scope->groupId)
                    ->where('id', (int) $matches[1])->countAllResults() > 0;
            } catch (\RuntimeException) {
                return false;
            }
        }
        $patterns = [
            '#^reservations/(\d+)(?:/|$)#' => ['reservations r', 'r.id', 'r.property_id', null],
            '#^payments/(\d+)(?:/|$)#' => ['payments x', 'x.id', 'r.property_id', ['reservations r', 'r.id = x.reservation_id']],
            '#^guests/(\d+)(?:/|$)#' => ['guests x', 'x.id', 'x.property_id', null],
            '#^room-pricing/(\d+)(?:/|$)#' => ['rooms x', 'x.id', 'x.property_id', null],
            '#^(?:housekeeping|ops/maintenance)/rooms/(\d+)(?:/|$)#' => ['rooms x', 'x.id', 'x.property_id', null],
            '#^housekeeping/tasks/(\d+)(?:/|$)#' => ['cleaning_tasks x', 'x.id', 'x.property_id', null],
            '#^ops/expenses/(\d+)(?:/|$)#' => ['expenses x', 'x.id', 'x.property_id', null],
            '#^ops/inventory/(?:amenities/)?(\d+)(?:/|$)#' => ['inventory_items x', 'x.id', 'x.property_id', null],
            '#^ops/maintenance/work-orders/(\d+)(?:/|$)#' => ['maintenance_tickets x', 'x.id', 'rm.property_id', ['rooms rm', 'rm.id = x.room_id']],
            '#^ops/maintenance/attachments/(\d+)(?:/|$)#' => ['maintenance_attachments x', 'x.id', 'rm.property_id', ['maintenance_tickets mt', 'mt.id = x.maintenance_ticket_id', 'rooms rm', 'rm.id = mt.room_id']],
            '#^ops/maintenance/schedules/(\d+)(?:/|$)#' => ['maintenance_schedules x', 'x.id', 'rm.property_id', ['room_assets a', 'a.id = x.room_asset_id', 'rooms rm', 'rm.id = a.room_id']],
            '#^ops/maintenance/assets/(\d+)(?:/|$)#' => ['room_assets x', 'x.id', 'rm.property_id', ['rooms rm', 'rm.id = x.room_id']],
            '#^finance/owner-setup/(\d+)(?:/|$)#' => ['owner_guesthouse_setups x', 'x.id', 'x.property_id', null],
        ];

        foreach ($patterns as $pattern => [$table, $idColumn, $propertyColumn, $joins]) {
            if (preg_match($pattern, $path, $matches) !== 1) {
                continue;
            }

            $targetPropertyIds = [$propertyId];
            if ($readOnly
                && (preg_match('#^payments/\d+/proof(?:/|$)#', $path) === 1 || preg_match('#^ops/expenses/\d+/receipt(?:/|$)#', $path) === 1)) {
                try {
                    $permission = str_starts_with($path, 'payments/') ? 'payment_proof' : 'expense_receipt';
                    $targetPropertyIds = \App\Libraries\FinanceProofScope::current($permission, $db)['propertyIds'];
                } catch (\RuntimeException) {
                    return false;
                }
            }

            $query = $db->table($table)->where($idColumn, (int) $matches[1])->whereIn($propertyColumn, $targetPropertyIds);
            if ($joins !== null) {
                for ($i = 0; $i < count($joins); $i += 2) {
                    $query->join($joins[$i], $joins[$i + 1]);
                }
            }

            return $query->countAllResults() > 0;
        }

        return true;
    }

    private function postedObjectsBelongToProperty(RequestInterface $request, int $propertyId): bool
    {
        if (! method_exists($request, 'getPost')) {
            return true;
        }

        $fields = [
            'room_id' => ['rooms', null],
            'room_ids' => ['rooms', null],
            'guest_id' => ['guests', null],
            'inventory_item_id' => ['inventory_items', null],
            'room_asset_id' => ['room_assets', ['rooms', 'rooms.id = room_assets.room_id']],
        ];
        $db = db_connect();
        foreach ($fields as $field => [$table, $join]) {
            $value = $request->getPost($field);
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $values = is_array($value) ? $value : [$value];
            $ids = [];
            foreach ($values as $raw) {
                if (! is_scalar($raw) || filter_var($raw, FILTER_VALIDATE_INT) === false || (int) $raw <= 0) {
                    return false;
                }
                $ids[] = (int) $raw;
            }
            $ids = array_values(array_unique($ids));
            $query = $db->table($table)->whereIn($table . '.id', $ids);
            if ($join !== null) {
                $query->join($join[0], $join[1]);
            }
            $propertyColumn = $join === null ? $table . '.property_id' : 'rooms.property_id';
            if ($query->where($propertyColumn, $propertyId)->countAllResults() !== count($ids)) {
                return false;
            }
        }

        return true;
    }
}
