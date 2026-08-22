<?php

namespace App\Controllers;

final class AccessPendingController extends BaseController
{
    public function index(): string
    {
        return view('pages/access_pending', ['pageTitle' => 'Akses Menunggu Pengaturan']);
    }
}
