<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class LastDataUpdateFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (! in_array(strtoupper($request->getMethod()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || $response->getStatusCode() >= 400
            || session()->has('error')) {
            return $response;
        }

        $userId = (int) session('userId');
        $userName = trim((string) session('fullName'));

        if ($userId <= 0 || $userName === '') {
            return $response;
        }

        try {
            $db = db_connect();
            if (! $db->tableExists('system_update_logs')) {
                return $response;
            }

            $db->table('system_update_logs')->insert([
                'user_id' => $userId,
                'user_name' => $userName,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'Unable to record last data update: {message}', ['message' => $exception->getMessage()]);
        }

        return $response;
    }
}
