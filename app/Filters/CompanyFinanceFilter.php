<?php

namespace App\Filters;

use App\Libraries\CompanyFinanceAccess;
use App\Libraries\CompanyFinanceContext;
use App\Libraries\RoleAccess;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class CompanyFinanceFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session('isLoggedIn')) return redirect()->to(site_url('login'));
        $role = (string) session('role');
        if (! RoleAccess::allows($role, 'company_finance')) {
            return service('response')->setStatusCode(403)->setHeader('Cache-Control', 'no-store')->setBody('Akses keuangan perusahaan hanya untuk role Manager dan Finance.');
        }
        $company = CompanyFinanceContext::resolve((int) session('userId'), $request->getGet('company'));
        if ($company === null || ! CompanyFinanceAccess::allows((int) session('userId'), $company, $arguments[0] ?? 'view')) {
            return service('response')->setStatusCode(403)->setHeader('Cache-Control', 'no-store')->setBody('Akses holding belum diberikan kepada akun ini.');
        }
        // No property activation or company selection is cached in session.
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('Cache-Control', 'private, no-store');
        return null;
    }
}
