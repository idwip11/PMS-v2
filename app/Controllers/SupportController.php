<?php

namespace App\Controllers;

class SupportController extends BaseController
{
    public function index(): string
    {
        $db = db_connect();

        return view('pages/support', [
            'pageTitle' => 'Support',
            'pageDescription' => 'Panduan singkat penggunaan aplikasi, ketentuan kerja, dan akses setiap role.',
            'status' => [
                'environment' => ENVIRONMENT,
                'phpVersion' => PHP_VERSION,
                'database' => $db->database,
                'baseUrl' => base_url(),
                'users' => $db->table('users')->countAllResults(),
                'rooms' => $db->table('rooms')->countAllResults(),
                'reservations' => $db->table('reservations')->countAllResults(),
            ],
        ]);
    }
}
