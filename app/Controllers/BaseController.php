<?php

namespace App\Controllers;

use App\Libraries\PropertyContext;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        if (session('isLoggedIn')) {
            try {
                $db = db_connect();
                $lastDataUpdate = $db->tableExists('system_update_logs')
                    ? $db->table('system_update_logs')->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')->get(1)->getRowArray()
                    : null;

                service('renderer')->setVar('lastDataUpdate', $lastDataUpdate);
            } catch (\Throwable $exception) {
                log_message('error', 'Unable to load last data update: {message}', ['message' => $exception->getMessage()]);
            }
        }
    }

    /**
     * Parse currency / nominal input that may contain dots or currency symbols.
     */
    protected function parseMoney(mixed $value, float $default = 0.0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $clean = str_replace(['Rp', 'rp', ' ', '.'], '', (string) $value);
        $clean = str_replace(',', '.', $clean);
        return is_numeric($clean) ? (float) $clean : $default;
    }
}
