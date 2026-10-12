<?php

namespace App\Filters;

use App\Libraries\PropertyContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error', 'Silakan login untuk melanjutkan.');
        }

        if (! PropertyContext::synchronize()) {
            session()->destroy();
            return redirect()->to('/login')->with('error', 'Akses properti akun ini belum tersedia.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
