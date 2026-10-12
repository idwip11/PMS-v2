<?php

namespace App\Controllers;

use App\Libraries\PropertyContext;

final class PropertyController extends BaseController
{
    public function select()
    {
        $propertyId = filter_var($this->request->getPost('property_id'), FILTER_VALIDATE_INT);
        $assignment = $propertyId !== false
            ? PropertyContext::assignment((int) session('userId'), (int) $propertyId)
            : null;

        if ($assignment === null) {
            return redirect()->to(site_url(PropertyContext::landingPath()))
                ->with('error', 'Anda tidak memiliki akses ke properti tersebut.');
        }

        session()->regenerate(true);
        PropertyContext::activate($assignment);

        $redirectUrl = $this->determineRedirectUrl((string) $this->request->getPost('redirect_url'));

        return redirect()->to($redirectUrl);
    }

    private function determineRedirectUrl(string $target): string
    {
        $fallback = site_url(PropertyContext::landingPath());

        $target = trim($target);
        if ($target === '') {
            return $fallback;
        }

        if (preg_match('/^(?:javascript|data|vbscript):/i', $target) === 1) {
            return $fallback;
        }

        if (preg_match('#^https?://#i', $target)) {
            $parsedHost = parse_url($target, PHP_URL_HOST);
            $siteHost = parse_url(site_url(), PHP_URL_HOST);
            if ($parsedHost !== null && $siteHost !== null && strcasecmp($parsedHost, $siteHost) !== 0) {
                return $fallback;
            }
        } elseif (str_starts_with($target, '//')) {
            return $fallback;
        }

        $parsed = parse_url($target);
        $path = trim($parsed['path'] ?? '', '/');
        $query = $parsed['query'] ?? '';

        $basePath = trim(parse_url(site_url(), PHP_URL_PATH) ?? '', '/');
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = trim(substr($path, strlen($basePath)), '/');
        }

        if (str_starts_with($path, 'index.php')) {
            $path = trim(substr($path, strlen('index.php')), '/');
        }

        if ($path === '' || $path === 'login' || $path === 'logout' || $path === 'properties/select' || $path === 'property-pending') {
            return $fallback;
        }

        $panelMap = [
            'ops/panel' => 'ops',
            'finance/panel' => 'finance',
            'settings/panel' => 'settings',
            'housekeeping/panel' => 'housekeeping',
            'dashboard/metrics' => 'dashboard',
            'room-map/grid' => 'room-map',
            'guests/suggestions' => 'guests',
            'reservations/available-rooms' => 'reservations',
        ];
        if (isset($panelMap[$path])) {
            $path = $panelMap[$path];
        }

        $objectNormalizers = [
            '#^reservations/\d+#' => 'reservations',
            '#^payments/\d+#' => 'reservations',
            '#^guests/\d+#' => 'guests',
            '#^room-pricing/\d+#' => 'room-pricing',
            '#^finance/owner-setup/\d+#' => 'finance/owner-setup',
            '#^ops/maintenance/work-orders/\d+#' => 'ops',
            '#^ops/maintenance/schedules/\d+#' => 'ops',
            '#^ops/maintenance/assets/\d+#' => 'ops',
            '#^ops/expenses/\d+#' => 'ops',
            '#^housekeeping/tasks/\d+#' => 'housekeeping',
        ];
        foreach ($objectNormalizers as $pattern => $normalized) {
            if (preg_match($pattern, $path) === 1) {
                $path = $normalized;
                break;
            }
        }

        if (! PropertyContext::legacyModulesReady() && ! PropertyContext::frontDeskReady()) {
            return $fallback;
        }

        $role = (string) session('role');
        $permissionMap = [
            'dashboard' => ['dashboard'],
            'housekeeping' => ['housekeeping'],
            'room-map' => ['front_desk'],
            'reservations' => ['front_desk'],
            'guests' => ['front_desk'],
            'room-pricing' => ['front_desk'],
            'finance/company' => ['finance'],
            'finance/owner' => ['finance', 'owner_finance'],
            'finance/owner-setup' => ['finance'],
            'finance' => ['finance'],
            'marketing' => ['analytics'],
            'ops' => ['operations'],
            'settings' => ['settings'],
            'support' => ['support'],
        ];

        foreach ($permissionMap as $routePrefix => $requiredPermissions) {
            if ($path === $routePrefix || str_starts_with($path, $routePrefix . '/')) {
                $hasAccess = false;
                foreach ($requiredPermissions as $permission) {
                    if (\App\Libraries\RoleAccess::allows($role, $permission)) {
                        $hasAccess = true;
                        break;
                    }
                }
                if (! $hasAccess) {
                    return $fallback;
                }
                break;
            }
        }

        $destination = site_url($path);
        if ($query !== '') {
            $destination .= '?' . $query;
        }

        return $destination;
    }

    public function pending()
    {
        if (PropertyContext::legacyModulesReady()) {
            return redirect()->to(site_url(PropertyContext::landingPath()));
        }

        return view('pages/property_pending', [
            'pageTitle' => (string) session('activePropertyName'),
            'pageDescription' => 'Properti sedang disiapkan',
        ]);
    }
}
