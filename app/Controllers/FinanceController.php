<?php

namespace App\Controllers;

use App\Libraries\FinanceReportScope;
use App\Libraries\FinanceReportService;
use App\Libraries\FinanceOwnerSetupService;
use App\Libraries\MitraOwnerFinanceService;
use App\Libraries\PropertyContext;
use DateTimeImmutable;
use RuntimeException;
use InvalidArgumentException;

class FinanceController extends BaseController
{
    public function index()
    {
        try {
            $scope = FinanceReportScope::current(FinanceReportScope::REPORT);
        } catch (RuntimeException $e) {
            return $this->handleScopeException($e);
        }

        $isCombined = $scope->isCombined();
        $pageTitle = $isCombined ? 'Keuangan Mitra — Homestay & Kos' : 'Keuangan Properti';
        $pageDescription = $isCombined
            ? 'Laporan keuangan gabungan Homestay Mitra dan Kos Mitra.'
            : 'Arus kas, laba bersih, dan outstanding.';

        return view('pages/finance', [
            'month' => $this->month(),
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'scope' => $scope,
            'isCombined' => $isCombined,
        ]);
    }

    public function company(): string
    {
        return view('pages/finance_company', [
            'title' => 'Keuangan Perusahaan - HomiQ PMS',
            'pageTitle' => 'Keuangan Perusahaan',
            'pageDescription' => 'Laporan keuangan konsolidasi dan entitas perusahaan.',
        ]);
    }

    public function owner()
    {
        try {
            $data = $this->ownerReportData();
        } catch (RuntimeException $e) {
            return $this->handleScopeException($e);
        }
        return view('pages/finance_owner', array_merge(
            ['pageTitle' => ! empty($data['isCombined']) ? 'Keuangan Owner Mitra — Homestay & Kos' : 'Keuangan Owner/Investor',
                'pageDescription' => ! empty($data['isCombined']) ? 'Satu laporan dan bagi hasil untuk Homestay Mitra dan Kos Mitra.' : 'Laporan keuangan untuk owner dan investor.'],
            $data
        ));
    }

    public function ownerExport()
    {
        try {
            $data = $this->ownerReportData();
        } catch (RuntimeException $e) {
            return $this->handleScopeException($e);
        }
        $isCombined = ! empty($data['isCombined']);
        $stringCell = static function ($value, string $style = '', ?string $href = null): string {
            $attributes = $style === '' ? '' : ' ss:StyleID="' . $style . '"';
            if ($href !== null) {
                $attributes .= ' ss:HRef="' . htmlspecialchars($href, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"';
            }
            return '<Cell' . $attributes . '><Data ss:Type="String">' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</Data></Cell>';
        };
        $numberCell = static fn ($value): string => '<Cell ss:StyleID="Currency"><Data ss:Type="Number">' . (float) $value . '</Data></Cell>';
        $rows = [
            '<Row>' . $stringCell('Keuangan Owner/Investor - ' . ($isCombined ? 'Homestay Mitra & Kos Mitra' : 'GH ' . $data['guesthouse']), 'Title') . $stringCell($data['month'], 'Title') . '</Row>',
            '<Row/>',
            '<Row>' . $stringCell('LABA/RUGI', 'Header') . '</Row>',
            '<Row>' . $stringCell('Pendapatan', 'Section') . '</Row>',
        ];
        foreach ($data['summary']['incomeCategories'] as $category => $amount) {
            $rows[] = '<Row>' . $stringCell($category) . $numberCell($amount) . '</Row>';
        }
        $rows = array_merge($rows, [
            '<Row>' . $stringCell('Total Pendapatan', 'Total') . $numberCell($data['summary']['totalIncome']) . '</Row>',
            '<Row>' . $stringCell('Pengeluaran Operasional', 'Section') . '</Row>',
        ]);
        foreach ($data['summary']['expenseCategories'] as $category => $amount) {
            $rows[] = '<Row>' . $stringCell($category) . $numberCell($amount) . '</Row>';
        }
        $shareRows = [
            '<Row>' . $stringCell('Fee Kerja Sama Pengelola (' . $data['summary']['managerFeePercentage'] . '%)', 'Share') . $numberCell($data['summary']['managerShare']) . '</Row>',
            '<Row>' . $stringCell('Bagi Hasil ' . ($data['summary']['ownerName'] ?? 'Owner/Investor') . ' (' . $data['summary']['ownerSharePercentage'] . '%)', 'Share') . $numberCell($data['summary']['investorShare']) . '</Row>',
        ];
        if (! empty($data['summary']['setupRequired'])) {
            $shareRows = ['<Row>' . $stringCell('Setup owner Mitra diperlukan; bagi hasil belum dihitung.') . '</Row>'];
        }
        if ($isCombined) {
            $shareRows[] = '<Row>' . $stringCell('Dasar bagi hasil gabungan', 'Total') . $numberCell($data['summary']['shareBase']) . '</Row>';
            $shareRows[] = '<Row>' . $stringCell('Dasar Homestay Mitra') . $numberCell($data['summary']['sourceIncome'][2]) . '</Row>';
            $shareRows[] = '<Row>' . $stringCell('Dasar Kos Mitra') . $numberCell($data['summary']['sourceIncome'][3]) . '</Row>';
        }
        if (! empty($data['summary']['isMitraProperty'])) {
            $shareRows[] = '<Row>' . $stringCell('* Dasar owner adalah omzet yang memenuhi hak owner, termasuk Extra Bed Homestay, tanpa layanan langsung pengelola dan tanpa pengurangan biaya operasional.') . '</Row>';
        }
        $rows = array_merge($rows, [
            '<Row>' . $stringCell('Total Pengeluaran Operasional', 'Total') . $numberCell($data['summary']['totalExpense']) . '</Row>',
            '<Row>' . $stringCell('Laba/Rugi Bersih', 'Total') . $numberCell($data['summary']['netProfit']) . '</Row>',
        ], $shareRows, [
            '<Row/>',
            '<Row>' . implode('', array_map(static fn ($heading) => $stringCell($heading, 'Header'), $isCombined ? ['Tanggal', 'Asal Properti', 'Kategori', 'Keterangan', 'Arus', 'Total Nominal', 'Bukti'] : ['Tanggal', 'Kategori', 'Keterangan', 'Arus', 'Total Nominal', 'Bukti'])) . '</Row>',
        ]);
        foreach ($data['allTransactions'] as $transaction) {
            $rows[] = '<Row>'
                . $stringCell($transaction['date'])
                . ($isCombined ? $stringCell($transaction['property_name']) : '')
                . $stringCell($transaction['category'])
                . $stringCell($transaction['description'])
                . $stringCell($transaction['flow'])
                . $numberCell($transaction['amount'])
                . $stringCell($transaction['proofUrl'] === null ? '-' : 'Lihat bukti', '', $transaction['proofUrl'])
                . '</Row>';
        }
        $content = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<?mso-application progid="Excel.Sheet"?>'
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            . '<Styles><Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14"/></Style><Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#155A91" ss:Pattern="Solid"/></Style><Style ss:ID="Section"><Font ss:Bold="1"/><Interior ss:Color="#F3F8B3" ss:Pattern="Solid"/></Style><Style ss:ID="Total"><Font ss:Bold="1"/><Interior ss:Color="#B8E1EE" ss:Pattern="Solid"/></Style><Style ss:ID="Share"><Font ss:Bold="1"/><Interior ss:Color="#A7D5E5" ss:Pattern="Solid"/></Style><Style ss:ID="Currency"><NumberFormat ss:Format="#,##0"/></Style></Styles>'
            . '<Worksheet ss:Name="' . ($isCombined ? 'Owner Mitra' : 'Owner ' . (int) PropertyContext::activeId()) . '"><Table>'
            . ($isCombined ? '<Column ss:Width="160"/>' : '')
            . '<Column ss:Width="90"/><Column ss:Width="180"/><Column ss:Width="200"/><Column ss:Width="90"/><Column ss:Width="110"/><Column ss:Width="180"/>'
            . implode('', $rows)
            . '</Table></Worksheet></Workbook>';

        if ($isCombined) {
            $content = str_replace('ss:Format="#,##0"', 'ss:Format="#,##0.00"', $content);
        }

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->download($isCombined ? 'owner-mitra-combined-' . $data['month'] . '.xls' : 'owner-property-' . PropertyContext::activeId() . '-gh-' . $data['guesthouse'] . '-' . $data['month'] . '.xls', $content);
    }

    public function ownerSetup()
    {
        try {
            $scope = FinanceReportScope::current(FinanceReportScope::OWNER_SETUP);
            if ($scope->isCombined()) {
                $service = new FinanceOwnerSetupService(db_connect());
                $setup = $service->read();
                $editId = (int) $this->request->getGet('edit');
                if ($editId > 0 && ($setup === null || $editId !== (int) $setup['id'])) {
                    throw new RuntimeException('Setup owner tidak ditemukan dalam cakupan Mitra.', 404);
                }
                return view('pages/owner_setup', [
                    'pageTitle' => 'Setup Owner Mitra', 'pageDescription' => 'Satu owner dan persentase untuk Homestay Mitra dan Kos Mitra.',
                    'isCombined' => true, 'scope' => $scope, 'setups' => $setup === null ? [] : [$setup],
                    'editingSetup' => $setup, 'legacySetups' => $service->legacy(),
                ]);
            }
        } catch (RuntimeException $e) {
            return $this->handleScopeException($e);
        }
        $editId = (int) $this->request->getGet('edit');
        $db = db_connect();
        return view('pages/owner_setup', [
            'pageTitle' => 'Setup Owner',
            'pageDescription' => 'Atur pemilik guesthouse dan persentase bagi hasil owner.',
            'isCombined' => false,
            'setups' => $db->table('owner_guesthouse_setups')->where('property_id', PropertyContext::activeId())->orderBy('guesthouse', 'ASC')->get()->getResultArray(),
            'editingSetup' => $editId > 0 ? $db->table('owner_guesthouse_setups')->where('property_id', PropertyContext::activeId())->where('id', $editId)->get()->getRowArray() : null,
        ]);
    }

    public function saveOwnerSetup()
    {
        try {
            $scope = FinanceReportScope::current(FinanceReportScope::OWNER_SETUP);
            if ($scope->isCombined()) {
                (new FinanceOwnerSetupService(db_connect()))->save($scope,
                    trim((string) $this->request->getPost('owner_name')),
                    trim((string) $this->request->getPost('cooperation_fee_percentage')),
                    (int) $this->request->getPost('id'));
                return redirect()->to(site_url('finance/owner-setup'))->with('success', 'Setup owner gabungan Homestay dan Kos Mitra berhasil disimpan.');
            }
        } catch (InvalidArgumentException $e) {
            return redirect()->to(site_url('finance/owner-setup'))->withInput()->with('error', $e->getMessage());
        } catch (RuntimeException $e) {
            return $this->handleScopeException($e);
        }
        if (! $this->validate([
            'owner_name' => 'required|max_length[150]',
            'guesthouse' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[5]',
            'cooperation_fee_percentage' => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
        ])) {
            return redirect()->to(site_url('finance/owner-setup'))->with('error', implode(' ', $this->validator->getErrors()));
        }

        $guesthouse = (int) $this->request->getPost('guesthouse');
        if (PropertyContext::activeId() === PropertyContext::HOMIQ_ID ? $guesthouse === 0 : $guesthouse !== 0) {
            return redirect()->to(site_url('finance/owner-setup'))->with('error', 'Pilihan kepemilikan tidak sesuai properti aktif.');
        }
        $setupId = (int) $this->request->getPost('id');
        $payload = [
            'property_id' => PropertyContext::activeId(),
            'owner_name' => trim((string) $this->request->getPost('owner_name')),
            'guesthouse' => $guesthouse,
            'cooperation_fee_percentage' => (float) $this->request->getPost('cooperation_fee_percentage'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db = db_connect();
        $existing = $db->table('owner_guesthouse_setups')->select('id')->where('property_id', PropertyContext::activeId())->where('guesthouse', $guesthouse)->get()->getRowArray();
        if ($setupId > 0) {
            $editedSetup = $db->table('owner_guesthouse_setups')->select('id')->where('property_id', PropertyContext::activeId())->where('id', $setupId)->get()->getRowArray();
            if ($editedSetup === null) {
                return redirect()->to(site_url('finance/owner-setup'))->with('error', 'Setup owner tidak ditemukan.');
            }
            if ($existing !== null && (int) $existing['id'] !== $setupId) {
                return redirect()->to(site_url('finance/owner-setup?edit=' . $setupId))->with('error', 'GH tersebut sudah memiliki setup owner.');
            }
            $db->table('owner_guesthouse_setups')->where('property_id', PropertyContext::activeId())->where('id', $setupId)->update($payload);
            return redirect()->to(site_url('finance/owner-setup'))->with('success', 'Setup owner berhasil diperbarui.');
        }
        if ($existing === null) {
            $payload['created_at'] = date('Y-m-d H:i:s');
            $db->table('owner_guesthouse_setups')->insert($payload);
            $message = 'Setup owner berhasil ditambahkan.';
        } else {
            $db->table('owner_guesthouse_setups')->where('property_id', PropertyContext::activeId())->where('id', (int) $existing['id'])->update($payload);
            $message = 'Setup owner untuk GH ' . $guesthouse . ' berhasil diperbarui.';
        }

        return redirect()->to(site_url('finance/owner-setup'))->with('success', $message);
    }

    public function deleteOwnerSetup(int $id)
    {
        try {
            $scope = FinanceReportScope::current(FinanceReportScope::OWNER_SETUP);
            if ($scope->isCombined()) {
                (new FinanceOwnerSetupService(db_connect()))->delete($scope, $id);
                return redirect()->to(site_url('finance/owner-setup'))->with('success', 'Setup owner gabungan dihapus. Bagi hasil Mitra memerlukan setup baru.');
            }
        } catch (InvalidArgumentException $e) {
            return redirect()->to(site_url('finance/owner-setup'))->with('error', $e->getMessage());
        } catch (RuntimeException $e) {
            return $this->handleScopeException($e);
        }
        $db = db_connect();
        $setup = $db->table('owner_guesthouse_setups')->select('owner_name, guesthouse')->where('property_id', PropertyContext::activeId())->where('id', $id)->get()->getRowArray();
        if ($setup === null) {
            return redirect()->to(site_url('finance/owner-setup'))->with('error', 'Setup owner tidak ditemukan.');
        }
        $db->table('owner_guesthouse_setups')->where('property_id', PropertyContext::activeId())->where('id', $id)->delete();
        return redirect()->to(site_url('finance/owner-setup'))->with('success', 'Setup owner ' . $setup['owner_name'] . ' untuk GH ' . $setup['guesthouse'] . ' berhasil dihapus.');
    }

    public function panel()
    {
        try {
            $scope = FinanceReportScope::current(FinanceReportScope::REPORT);
        } catch (RuntimeException $e) {
            $statusCode = $e->getCode() ?: 403;
            return $this->response->setStatusCode($statusCode)
                ->setBody('<div class="alert alert-danger my-4 d-flex align-items-center gap-2">'
                    . '<svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/></svg>'
                    . '<span>' . esc($e->getMessage()) . '</span></div>');
        }

        return view('components/finance_panel', FinanceReportService::getPropertyReportData($scope, $this->month(), $this->page()));
    }

    public function export()
    {
        try {
            $scope = FinanceReportScope::current(FinanceReportScope::REPORT);
        } catch (RuntimeException $e) {
            $statusCode = $e->getCode() ?: 403;
            return $this->response->setStatusCode($statusCode)->setBody(esc($e->getMessage()));
        }

        $data = FinanceReportService::getPropertyReportData($scope, $this->month(), 1);
        $stringCell = static fn ($value, string $style = ''): string => '<Cell' . ($style === '' ? '' : ' ss:StyleID="' . $style . '"') . '><Data ss:Type="String">' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</Data></Cell>';
        $numberCell = static fn ($value): string => '<Cell ss:StyleID="Currency"><Data ss:Type="Number">' . (float) $value . '</Data></Cell>';
        $integerCell = static fn ($value): string => '<Cell ss:StyleID="Integer"><Data ss:Type="Number">' . (int) $value . '</Data></Cell>';

        $isCombined = $scope->isCombined();
        $reportTitle = $isCombined
            ? 'Keuangan Mitra — Homestay & Kos'
            : (string) session('activePropertyName') . ' Finance Report';
        $downloadName = $isCombined
            ? 'finance-mitra-combined-' . $data['month'] . '.xls'
            : 'finance-property-' . PropertyContext::activeId() . '-' . $data['month'] . '.xls';

        $headers = $isCombined
            ? ['Date', 'Properti', 'Type', 'Payment Type', 'Reference', 'Description', 'Method/Category', 'Amount', 'Room', 'Check-in', 'Check-out', 'Durasi', 'Berapa Kamar']
            : ['Date', 'Type', 'Payment Type', 'Reference', 'Description', 'Method/Category', 'Amount', 'Room', 'GH', 'Check-in', 'Check-out', 'Berapa Malam', 'Berapa Kamar'];

        $rows = [
            '<Row>' . $stringCell($reportTitle, 'Title') . $stringCell($data['month'], 'Title') . '</Row>',
            '<Row/>',
            '<Row>' . $stringCell('Revenue') . $numberCell($data['summary']['revenue']) . '</Row>',
            '<Row>' . $stringCell('Expenses') . $numberCell($data['summary']['expenses']) . '</Row>',
            '<Row>' . $stringCell('Net Profit') . $numberCell($data['summary']['netProfit']) . '</Row>',
            '<Row>' . $stringCell('Outstanding') . $numberCell($data['summary']['outstanding']) . '</Row>',
            '<Row/>',
            '<Row>' . implode('', array_map(static fn ($heading) => $stringCell($heading, 'Header'), $headers)) . '</Row>',
        ];

        foreach ($data['allTransactions'] as $transaction) {
            $isIncome = $transaction['type'] === 'Income';
            $isHomestay = ($transaction['property_name'] ?? '') === 'Homestay Mitra'
                || (int) ($transaction['property_id'] ?? 0) === PropertyContext::HOMESTAY_ID
                || (! $isCombined && (PropertyContext::activeId() === PropertyContext::HOMESTAY_ID || (string) session('activePropertyCode') === 'HOMESTAY_MITRA'));
            $roomCount = $isIncome ? ($isHomestay ? 3 : (int) ($transaction['room_count'] ?: 1)) : '';
            $amount = abs((float) $transaction['amount']);

            if ($isCombined) {
                $rows[] = '<Row>'
                    . $stringCell($transaction['date'])
                    . $stringCell($transaction['property_name'])
                    . $stringCell($transaction['type'])
                    . $stringCell($transaction['payment_type'])
                    . $stringCell($transaction['reference'])
                    . $stringCell($transaction['description'])
                    . $stringCell($transaction['method'])
                    . $numberCell($amount)
                    . $stringCell($isIncome ? $transaction['room'] : '')
                    . $stringCell($isIncome ? $transaction['check_in'] : '')
                    . $stringCell($isIncome ? $transaction['check_out'] : '')
                    . ($isIncome ? $stringCell($transaction['duration']) : $stringCell(''))
                    . ($isIncome ? $integerCell($roomCount) : $stringCell(''))
                    . '</Row>';
            } else {
                $rows[] = '<Row>'
                    . $stringCell($transaction['date'])
                    . $stringCell($transaction['type'])
                    . $stringCell($transaction['payment_type'])
                    . $stringCell($transaction['reference'])
                    . $stringCell($transaction['description'])
                    . $stringCell($transaction['method'])
                    . $numberCell($amount)
                    . $stringCell($isIncome ? $transaction['room'] : '')
                    . $stringCell($isIncome ? $transaction['guesthouse'] : '')
                    . $stringCell($isIncome ? $transaction['check_in'] : '')
                    . $stringCell($isIncome ? $transaction['check_out'] : '')
                    . ($isIncome ? $integerCell($transaction['nights']) : $stringCell(''))
                    . ($isIncome ? $integerCell($roomCount) : $stringCell(''))
                    . '</Row>';
            }
        }

        $columnWidths = $isCombined
            ? '<Column ss:Width="80"/><Column ss:Width="110"/><Column ss:Width="75"/><Column ss:Width="125"/><Column ss:Width="100"/><Column ss:Width="180"/><Column ss:Width="120"/><Column ss:Width="100"/><Column ss:Width="80"/><Column ss:Width="80"/><Column ss:Width="80"/><Column ss:Width="90"/><Column ss:Width="90"/>'
            : '<Column ss:Width="80"/><Column ss:Width="75"/><Column ss:Width="125"/><Column ss:Width="100"/><Column ss:Width="180"/><Column ss:Width="120"/><Column ss:Width="100"/><Column ss:Width="70"/><Column ss:Width="55"/><Column ss:Width="80"/><Column ss:Width="80"/><Column ss:Width="90"/><Column ss:Width="90"/>';

        $content = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<?mso-application progid="Excel.Sheet"?>'
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            . '<Styles><Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14"/></Style><Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#2563EB" ss:Pattern="Solid"/></Style><Style ss:ID="Currency"><NumberFormat ss:Format="#,##0"/></Style><Style ss:ID="Integer"><NumberFormat ss:Format="0"/></Style></Styles>'
            . '<Worksheet ss:Name="Finance ' . $data['month'] . '"><Table>'
            . $columnWidths
            . implode('', $rows)
            . '</Table></Worksheet></Workbook>';

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->download($downloadName, $content);
    }

    private function handleScopeException(RuntimeException $e)
    {
        $statusCode = $e->getCode() ?: 403;
        return $this->response->setStatusCode($statusCode)->setBody(
            view('pages/finance_denied', [
                'pageTitle' => 'Keuangan Properti',
                'pageDescription' => 'Akses Laporan Dibatasi',
                'errorMessage' => $e->getMessage(),
                'statusCode' => $statusCode,
            ])
        );
    }

    private function paymentTypeLabel(?string $type): string
    {
        return FinanceReportService::paymentTypeLabel($type);
    }

    private function month(): string
    {
        $month = (string) $this->request->getGet('month');
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 ? $month : date('Y-m');
    }

    private function page(): int
    {
        return max(1, (int) $this->request->getGet('page'));
    }

    private function ownerGuesthouse(): int
    {
        if (PropertyContext::activeId() !== PropertyContext::HOMIQ_ID) {
            return 0;
        }
        $guesthouse = (int) $this->request->getGet('guesthouse');
        return $guesthouse >= 1 && $guesthouse <= 5 ? $guesthouse : 1;
    }

    private function ownerReportData(): array
    {
        $scope = FinanceReportScope::current(FinanceReportScope::OWNER_REPORT);
        if ($scope->isCombined()) {
            return (new MitraOwnerFinanceService(db_connect()))->report($scope, $this->month(), $this->page());
        }
        $month = $this->month();
        $guesthouse = $scope->ownerGuesthouse ?? $this->ownerGuesthouse();
        $first = new DateTimeImmutable($month . '-01');
        $last = $first->modify('last day of this month');
        $db = db_connect();
        $occupancy = $this->occupancyForOwnerGuesthouse($db, $guesthouse, $first);

        $paymentsBuilder = $db->table('payments')
            ->select('payments.id, payments.reservation_id, payments.payment_date, payments.type, payments.amount, payments.proof_path, reservations.total_amount, guests.full_name AS guest_name')
            ->join('reservations', 'reservations.id = payments.reservation_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->join('guests', 'guests.id = reservations.guest_id')
            ->where('reservations.property_id', PropertyContext::activeId())
            ->where('payments.status', 'Paid')
            ->where('payments.payment_date >=', $first->format('Y-m-d'))
            ->where('payments.payment_date <=', $last->format('Y-m-d'));
        if (PropertyContext::activeId() === PropertyContext::HOMIQ_ID) {
            $paymentsBuilder->where('rooms.floor', $guesthouse);
        }
        $payments = $paymentsBuilder->get()->getResultArray();

        // Revenue belonging to an Owner GH excludes company-only services.
        // A payment (including a DP) is split proportionally using the invoice
        // composition so Owner and company revenue are not recognized twice.
        $reservationIds = array_values(array_unique(array_map('intval', array_column($payments, 'reservation_id'))));
        $invoiceComposition = [];

        if ($reservationIds !== []) {
            $companyOnlyServices = ['Breakfast', 'Extra Bed', 'Biaya Extra Bed', 'Laundry', 'Massage', 'Sewa Kendaraan'];
            foreach ($db->table('invoice_items')
                ->select('reservation_id, item_name, amount')
                ->whereIn('reservation_id', $reservationIds)
                ->get()
                ->getResultArray() as $item) {
                $reservationId = (int) $item['reservation_id'];
                $amount = (float) $item['amount'];
                $itemName = trim((string) $item['item_name']);
                $invoiceComposition[$reservationId] ??= ['rental' => 0.0, 'extra_bed' => 0.0, 'guest_charge' => 0.0, 'company' => 0.0];

                if (in_array($itemName, $companyOnlyServices, true) || str_starts_with($itemName, 'Extra Bed') || str_starts_with($itemName, 'Biaya Extra Bed')) {
                    $invoiceComposition[$reservationId]['company'] += $amount;
                } elseif (in_array($itemName, ['Early Check In', 'Late Checkout'], true)) {
                    $invoiceComposition[$reservationId]['guest_charge'] += $amount;
                } else {
                    // Room charges and special price adjustments belong to the GH.
                    $invoiceComposition[$reservationId]['rental'] += $amount;
                }
            }
        }

        $ownerPayments = [];
        foreach ($payments as $payment) {
            $reservationId = (int) $payment['reservation_id'];
            $composition = $invoiceComposition[$reservationId] ?? ['rental' => (float) $payment['total_amount'], 'extra_bed' => 0.0, 'guest_charge' => 0.0, 'company' => 0.0];
            $invoiceTotal = (float) $composition['rental'] + (float) $composition['extra_bed'] + (float) $composition['guest_charge'] + (float) $composition['company'];
            if ($invoiceTotal <= 0) {
                $invoiceTotal = max(0.0, (float) $payment['total_amount']);
            }
            $paymentAmount = (float) $payment['amount'];
            $rentalAmount = $invoiceTotal > 0 ? $paymentAmount * ((float) $composition['rental'] / $invoiceTotal) : $paymentAmount;
            $extraBedAmount = $invoiceTotal > 0 ? $paymentAmount * ((float) $composition['extra_bed'] / $invoiceTotal) : 0.0;
            $guestChargeAmount = $invoiceTotal > 0 ? $paymentAmount * ((float) $composition['guest_charge'] / $invoiceTotal) : 0.0;
            $payment['owner_amount'] = max(0.0, $rentalAmount + $extraBedAmount + $guestChargeAmount);
            $payment['owner_rental_amount'] = max(0.0, $rentalAmount);
            $payment['owner_extra_bed_amount'] = max(0.0, $extraBedAmount);
            $payment['owner_guest_charge_amount'] = max(0.0, $guestChargeAmount);
            $ownerPayments[] = $payment;
        }

        $expenseBuilder = $db->table('expenses')
            ->select('expenses.id, expenses.date_incurred, expenses.category, expenses.description, expenses.amount, expenses.receipt_url, (SELECT COUNT(*) FROM expense_guesthouses allocation WHERE allocation.expense_id = expenses.id) AS allocation_count', false)
            ->where('expenses.property_id', PropertyContext::activeId())
            ->where('expenses.category !=', 'Fee Kerjasama')
            ->where('expenses.status !=', 'Rejected')
            ->where('expenses.date_incurred >=', $first->format('Y-m-d'))
            ->where('expenses.date_incurred <=', $last->format('Y-m-d'));
        if (PropertyContext::activeId() === PropertyContext::HOMIQ_ID) {
            $expenseBuilder->where(
                "(EXISTS (SELECT 1 FROM expense_guesthouses eg WHERE eg.expense_id = expenses.id AND eg.guesthouse = {$guesthouse})"
                . " OR (NOT EXISTS (SELECT 1 FROM expense_guesthouses eg2 WHERE eg2.expense_id = expenses.id) AND expenses.guesthouse = {$guesthouse}))",
                null,
                false
            );
        }
        $expenses = $expenseBuilder->get()->getResultArray();
        foreach ($expenses as &$expense) {
            // Legacy HomiQ expense shared across GHs is allocated equally,
            // so it is never reported at full value for every owner.
            $expense['amount'] = (float) $expense['amount'] / max(1, (int) $expense['allocation_count']);
        }
        unset($expense);

        $transactions = [];
        foreach ($ownerPayments as $payment) {
            if ((float) $payment['owner_amount'] <= 0) {
                continue;
            }
            $transactions[] = [
                'date' => (string) $payment['payment_date'],
                'category' => $this->paymentTypeLabel($payment['type'] ?? null),
                'description' => (string) ($payment['guest_name'] ?? '-'),
                'flow' => 'Pemasukan',
                'amount' => (float) $payment['owner_amount'],
                'proofUrl' => empty($payment['proof_path']) ? null : site_url('payments/' . $payment['id'] . '/proof'),
                'sortId' => (int) $payment['id'],
            ];
        }
        foreach ($expenses as $expense) {
            $cat = (string) $expense['category'];
            $transactions[] = [
                'date' => (string) $expense['date_incurred'],
                'category' => $cat,
                'description' => (string) ($expense['description'] ?? '-'),
                'flow' => 'Pengeluaran',
                'amount' => (float) $expense['amount'],
                'proofUrl' => empty($expense['receipt_url']) ? null : site_url('ops/expenses/' . $expense['id'] . '/receipt'),
                'sortId' => (int) $expense['id'],
            ];
        }
        usort($transactions, static fn (array $a, array $b): int => [$b['date'], $b['sortId']] <=> [$a['date'], $a['sortId']]);

        $incomeCategories = ['Penyewaan' => 0.0, 'Charge Tamu' => 0.0, 'Lain-lain' => 0.0];
        foreach ($ownerPayments as $payment) {
            $incomeCategories['Penyewaan'] += (float) $payment['owner_rental_amount'];
            $incomeCategories['Charge Tamu'] += (float) $payment['owner_guest_charge_amount'];
        }
        $expenseCategories = [];
        foreach ($expenses as $expense) {
            $category = trim((string) ($expense['category'] ?? '')) ?: 'Lain-lain';
            $expenseCategories[$category] = ($expenseCategories[$category] ?? 0.0) + (float) $expense['amount'];
        }
        ksort($expenseCategories, SORT_NATURAL | SORT_FLAG_CASE);
        $totalIncome = array_sum($incomeCategories);
        $totalExpense = array_sum($expenseCategories);
        $netProfit = $totalIncome - $totalExpense;
        $distributableProfit = max(0.0, $netProfit);

        // The remaining branch is HomiQ only; its existing positive-net-profit base is unchanged.
        $shareBase = $distributableProfit;

        $ownerSetupBuilder = $db->table('owner_guesthouse_setups')->where('property_id', PropertyContext::activeId());
        if (PropertyContext::activeId() === PropertyContext::HOMIQ_ID) {
            $ownerSetupBuilder->where('guesthouse', $guesthouse);
        }
        $ownerSetup = $ownerSetupBuilder->get()->getRowArray();
        // The setup percentage belongs to the owner. The manager's cooperation
        // fee is the remaining percentage of the calculation base.
        $ownerSharePercentage = $ownerSetup === null ? 70.0 : (float) $ownerSetup['cooperation_fee_percentage'];
        $managerFeePercentage = 100 - $ownerSharePercentage;

        $perPage = 10;
        $totalItems = count($transactions);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = min($this->page(), $totalPages);

        return [
            'month' => $month,
            'isCombined' => false,
            'scope' => $scope,
            'guesthouse' => $guesthouse,
            'ownerGuesthouseLocked' => $scope->ownerGuesthouse !== null,
            'occupancy' => $occupancy,
            'transactions' => array_slice($transactions, ($page - 1) * $perPage, $perPage),
            'allTransactions' => $transactions,
            'summary' => [
                'incomeCategories' => $incomeCategories,
                'expenseCategories' => $expenseCategories,
                'totalIncome' => $totalIncome,
                'totalExpense' => $totalExpense,
                'netProfit' => $netProfit,
                'ownerName' => $ownerSetup['owner_name'] ?? null,
                'managerFeePercentage' => $managerFeePercentage,
                'ownerSharePercentage' => $ownerSharePercentage,
                'shareBase' => $shareBase,
                'isMitraProperty' => false,
                'managerShare' => $shareBase * ($managerFeePercentage / 100),
                'investorShare' => $shareBase * ($ownerSharePercentage / 100),
            ],
            'pagination' => ['page' => $page, 'perPage' => $perPage, 'totalItems' => $totalItems, 'totalPages' => $totalPages],
        ];
    }

    /** @return array{occupancy:float,roomNightsSold:int,roomNightsAvailable:int,roomCount:int,periodDays:int} */
    private function occupancyForOwnerGuesthouse($db, int $guesthouse, DateTimeImmutable $first): array
    {
        $next = $first->modify('first day of next month');
        $days = (int) $first->format('t');
        $roomsBuilder = $db->table('rooms')->where('property_id', PropertyContext::activeId());
        if (PropertyContext::activeId() === PropertyContext::HOMIQ_ID) {
            $roomsBuilder->where('floor', $guesthouse);
        }
        $roomCount = $roomsBuilder->countAllResults();

        $resBuilder = $db->table('reservations')
            ->select('reservations.check_in_date, reservations.check_out_date')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->where('reservations.property_id', PropertyContext::activeId());
        if (PropertyContext::activeId() === PropertyContext::HOMIQ_ID) {
            $resBuilder->where('rooms.floor', $guesthouse);
        }
        $reservations = $resBuilder
            ->groupStart()
                ->groupStart()
                    ->where('reservations.check_in_date <', $next->format('Y-m-d'))
                    ->where('reservations.check_out_date >', $first->format('Y-m-d'))
                ->groupEnd()
                ->orGroupStart()
                    ->where('reservations.check_in_date = reservations.check_out_date', null, false)
                    ->where('reservations.check_in_date >=', $first->format('Y-m-d'))
                    ->where('reservations.check_in_date <', $next->format('Y-m-d'))
                ->groupEnd()
            ->groupEnd()
            ->whereIn('reservations.status', ['Confirmed', 'Checked_In', 'Checked_Out'])
            ->get()->getResultArray();

        $roomNightsSold = 0;
        foreach ($reservations as $reservation) {
            $checkIn = (string) $reservation['check_in_date'];
            $checkOut = (string) $reservation['check_out_date'];
            $effectiveCheckOut = $checkOut > $checkIn
                ? $checkOut
                : (new DateTimeImmutable($checkIn))->modify('+1 day')->format('Y-m-d');
            $arrival = max($checkIn, $first->format('Y-m-d'));
            $departure = min($effectiveCheckOut, $next->format('Y-m-d'));
            $roomNightsSold += max(0, (int) (new DateTimeImmutable($arrival))->diff(new DateTimeImmutable($departure))->days);
        }

        $ticketsBuilder = $db->table('maintenance_tickets')
            ->select('maintenance_tickets.room_id, maintenance_tickets.created_at, maintenance_tickets.completed_at')
            ->join('rooms', 'rooms.id = maintenance_tickets.room_id')
            ->where('rooms.property_id', PropertyContext::activeId())
            ->where('maintenance_tickets.blocks_room', 1)
            ->where('maintenance_tickets.created_at <', $next->format('Y-m-d'));
        if (PropertyContext::activeId() === PropertyContext::HOMIQ_ID) {
            $ticketsBuilder->where('rooms.floor', $guesthouse);
        }
        $maintenanceTickets = $ticketsBuilder->get()->getResultArray();
        $blockedRoomNights = [];
        foreach ($maintenanceTickets as $ticket) {
            $blockStart = max(substr((string) $ticket['created_at'], 0, 10), $first->format('Y-m-d'));
            $blockEnd = min(substr((string) ($ticket['completed_at'] ?? $next->format('Y-m-d')), 0, 10), $next->format('Y-m-d'));
            if ($blockEnd <= $blockStart) {
                continue;
            }
            for ($date = new DateTimeImmutable($blockStart); $date < new DateTimeImmutable($blockEnd); $date = $date->modify('+1 day')) {
                $blockedRoomNights[(int) $ticket['room_id'] . '|' . $date->format('Y-m-d')] = true;
            }
        }

        $roomNightsAvailable = max(0, ($roomCount * $days) - count($blockedRoomNights));

        return [
            'occupancy' => $roomNightsAvailable > 0 ? ($roomNightsSold / $roomNightsAvailable) * 100 : 0.0,
            'roomNightsSold' => $roomNightsSold,
            'roomNightsAvailable' => $roomNightsAvailable,
            'roomCount' => $roomCount,
            'periodDays' => $days,
        ];
    }
}
