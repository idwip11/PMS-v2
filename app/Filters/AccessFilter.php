<?php

namespace App\Filters;

use App\Libraries\RoleAccess;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class AccessFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $role = (string) session()->get('role');
        $permissions = array_filter((array) $arguments, static fn ($permission): bool => is_string($permission) && $permission !== '');
        $allowed = false;
        foreach ($permissions as $permission) {
            if (RoleAccess::allows($role, $permission)) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            return redirect()->to(site_url(RoleAccess::landingPath($role)))
                ->with('error', 'Anda tidak memiliki akses ke halaman tersebut.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
