<?php

namespace App\Controllers;

use App\Libraries\RoleAccess;
use App\Models\UserModel;

class AuthController extends BaseController
{
    public function index()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to(site_url(RoleAccess::landingPath((string) session()->get('role'))));
        }

        return view('pages/login');
    }

    public function login()
    {
        $rules = [
            'username' => 'required|max_length[100]',
            'password' => 'required',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', 'Username dan password wajib diisi.');
        }

        $user = (new UserModel())->where('username', (string) $this->request->getPost('username'))->first();

        if ($user === null || ! password_verify((string) $this->request->getPost('password'), $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Username atau password tidak valid.');
        }

        session()->regenerate();
        session()->set([
            'isLoggedIn' => true,
            'userId'     => (int) $user['id'],
            'username'   => $user['username'],
            'fullName'   => $user['full_name'],
            'role'       => $user['role'],
        ]);

        return redirect()->to(site_url(RoleAccess::landingPath((string) $user['role'])))->with('success', 'Selamat datang, ' . esc($user['full_name']) . '.');
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to('/login')->with('success', 'Anda telah logout.');
    }
}
