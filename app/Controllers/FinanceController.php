<?php

namespace App\Controllers;

use App\Libraries\RoleAccess;
use DateTimeImmutable;

class FinanceController extends BaseController
{
    public function index(): string
    {
        return view('pages/finance', ['month' => $this->month(), 'pageTitle' => 'Finance', 'pageDescription' => 'Arus kas, laba bersih, dan outstanding.']);
    }

    public function owner(): string
    {
        return view('pages/finance_owner', array_merge(
            ['pageTitle' => 'Keuangan Owner/Investor', 'pageDescription' => 'Laporan keuangan untuk owner dan investor.'],
            $this->ownerReportData()
        ));
    }

    public function ownerExport()
    {
        $data = $this->ownerReportData();
        $stringCell = static function ($value, string $style = '', ?string $href = null): string {
            $attributes = $style === '' ? '' : ' ss:StyleID="' . $style . '"';
            if ($href !== null) {
                $attributes .= ' ss:HRef="' . htmlspecialchars($href, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"';
            }
            return '<Cell' . $attributes . '><Data ss:Type="String">' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</Data></Cell>';
        };
        $numberCell = static fn ($value): string => '<Cell ss:StyleID="Currency"><Data ss:Type="Number">' . (float) $value . '</Data></Cell>';
        $rows = [
            '<Row>' . $stringCell('Keuangan Owner/Investor - GH ' . $data['guesthouse'], 'Title') . $stringCell($data['month'], 'Title') . '</Row>',
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
        $rows = array_merge($rows, [
            '<Row>' . $stringCell('Total Pengeluaran Operasional', 'Total') . $numberCell($data['summary']['totalExpense']) . '</Row>',
            '<Row>' . $stringCell('Laba/Rugi Bersih', 'Total') . $numberCell($data['summary']['netProfit']) . '</Row>',
            '<Row>' . $stringCell('Fee Kerja Sama Pengelola (' . $data['summary']['managerFeePercentage'] . '%)', 'Share') . $numberCell($data['summary']['managerShare']) . '</Row>',
            '<Row>' . $stringCell('Bagi Hasil ' . ($data['summary']['ownerName'] ?? 'Owner/Investor') . ' (' . $data['summary']['ownerSharePercentage'] . '%)', 'Share') . $numberCell($data['summary']['investorShare']) . '</Row>',
            '<Row/>',
            '<Row>' . implode('', array_map(static fn ($heading) => $stringCell($heading, 'Header'), ['Tanggal', 'Kategori', 'Keterangan', 'Arus', 'Total Nominal', 'Bukti'])) . '</Row>',
        ]);
        foreach ($data['allTransactions'] as $transaction) {
            $rows[] = '<Row>'
                . $stringCell($transaction['date'])
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
            . '<Worksheet ss:Name="Owner GH ' . $data['guesthouse'] . '"><Table>'
            . '<Column ss:Width="90"/><Column ss:Width="180"/><Column ss:Width="200"/><Column ss:Width="90"/><Column ss:Width="110"/><Column ss:Width="180"/>'
            . implode('', $rows)
            . '</Table></Worksheet></Workbook>';

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->download('homiq-owner-gh-' . $data['guesthouse'] . '-' . $data['month'] . '.xls', $content);
    }

    public function ownerSetup(): string
    {
        $editId = (int) $this->request->getGet('edit');
        $db = db_connect();
        return view('pages/owner_setup', [
            'pageTitle' => 'Setup Owner',
            'pageDescription' => 'Atur pemilik guesthouse dan persentase bagi hasil owner.',
            'setups' => $db->table('owner_guesthouse_setups')->orderBy('guesthouse', 'ASC')->get()->getResultArray(),
            'editingSetup' => $editId > 0 ? $db->table('owner_guesthouse_setups')->where('id', $editId)->get()->getRowArray() : null,
        ]);
    }

    public function saveOwnerSetup()
    {
        if (! $this->validate([
            'owner_name' => 'required|max_length[150]',
            'guesthouse' => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[5]',
            'cooperation_fee_percentage' => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
        ])) {
            return redirect()->to(site_url('finance/owner-setup'))->with('error', implode(' ', $this->validator->getErrors()));
        }

        $guesthouse = (int) $this->request->getPost('guesthouse');
        $setupId = (int) $this->request->getPost('id');
        $payload = [
            'owner_name' => trim((string) $this->request->getPost('owner_name')),
            'guesthouse' => $guesthouse,
            'cooperation_fee_percentage' => (float) $this->request->getPost('cooperation_fee_percentage'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db = db_connect();
        $existing = $db->table('owner_guesthouse_setups')->select('id')->where('guesthouse', $guesthouse)->get()->getRowArray();
        if ($setupId > 0) {
            $editedSetup = $db->table('owner_guesthouse_setups')->select('id')->where('id', $setupId)->get()->getRowArray();
            if ($editedSetup === null) {
                return redirect()->to(site_url('finance/owner-setup'))->with('error', 'Setup owner tidak ditemukan.');
            }
            if ($existing !== null && (int) $existing['id'] !== $setupId) {
                return redirect()->to(site_url('finance/owner-setup?edit=' . $setupId))->with('error', 'GH tersebut sudah memiliki setup owner.');
            }
            $db->table('owner_guesthouse_setups')->where('id', $setupId)->update($payload);
            return redirect()->to(site_url('finance/owner-setup'))->with('success', 'Setup owner berhasil diperbarui.');
        }
        if ($existing === null) {
            $payload['created_at'] = date('Y-m-d H:i:s');
            $db->table('owner_guesthouse_setups')->insert($payload);
            $message = 'Setup owner berhasil ditambahkan.';
        } else {
            $db->table('owner_guesthouse_setups')->where('id', (int) $existing['id'])->update($payload);
            $message = 'Setup owner untuk GH ' . $guesthouse . ' berhasil diperbarui.';
        }

        return redirect()->to(site_url('finance/owner-setup'))->with('success', $message);
    }

    public function deleteOwnerSetup(int $id)
    {
        $db = db_connect();
        $setup = $db->table('owner_guesthouse_setups')->select('owner_name, guesthouse')->where('id', $id)->get()->getRowArray();
        if ($setup === null) {
            return redirect()->to(site_url('finance/owner-setup'))->with('error', 'Setup owner tidak ditemukan.');
        }
        $db->table('owner_guesthouse_setups')->where('id', $id)->delete();
        return redirect()->to(site_url('finance/owner-setup'))->with('success', 'Setup owner ' . $setup['owner_name'] . ' untuk GH ' . $setup['guesthouse'] . ' berhasil dihapus.');
    }

    public function panel(): string
    {
        return view('components/finance_panel', $this->reportData());
    }

    public function export()
    {
        $data = $this->reportData();
        $stringCell = static fn ($value, string $style = ''): string => '<Cell' . ($style === '' ? '' : ' ss:StyleID="' . $style . '"') . '><Data ss:Type="String">' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</Data></Cell>';
        $numberCell = static fn ($value): string => '<Cell ss:StyleID="Currency"><Data ss:Type="Number">' . (float) $value . '</Data></Cell>';
        $integerCell = static fn ($value): string => '<Cell ss:StyleID="Integer"><Data ss:Type="Number">' . (int) $value . '</Data></Cell>';
        $rows = [
            '<Row>' . $stringCell('HomiQ Finance Report', 'Title') . $stringCell($data['month'], 'Title') . '</Row>',
            '<Row/>',
            '<Row>' . $stringCell('Revenue') . $numberCell($data['summary']['revenue']) . '</Row>',
            '<Row>' . $stringCell('Expenses') . $numberCell($data['summary']['expenses']) . '</Row>',
            '<Row>' . $stringCell('Net Profit') . $numberCell($data['summary']['netProfit']) . '</Row>',
            '<Row>' . $stringCell('Outstanding') . $numberCell($data['summary']['outstanding']) . '</Row>',
            '<Row/>',
            '<Row>' . implode('', array_map(static fn ($heading) => $stringCell($heading, 'Header'), ['Date', 'Type', 'Payment Type', 'Reference', 'Description', 'Method/Category', 'Amount', 'Room', 'GH', 'Check-in', 'Check-out', 'Berapa Malam', 'Berapa Kamar'])) . '</Row>',
        ];
        foreach ($data['allTransactions'] as $transaction) {
            $isIncome = $transaction['type'] === 'Income';
            $rows[] = '<Row>'
                . $stringCell($transaction['date'])
                . $stringCell($transaction['type'])
                . $stringCell($transaction['payment_type'])
                . $stringCell($transaction['reference'])
                . $stringCell($transaction['description'])
                . $stringCell($transaction['method'])
                . $numberCell($transaction['amount'])
                . $stringCell($isIncome ? $transaction['room'] : '')
                . $stringCell($isIncome ? $transaction['guesthouse'] : '')
                . $stringCell($isIncome ? $transaction['check_in'] : '')
                . $stringCell($isIncome ? $transaction['check_out'] : '')
                . ($isIncome ? $integerCell($transaction['nights']) : $stringCell(''))
                . ($isIncome ? $integerCell($transaction['room_count']) : $stringCell(''))
                . '</Row>';
        }
        $content = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<?mso-application progid="Excel.Sheet"?>'
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            . '<Styles><Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14"/></Style><Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#2563EB" ss:Pattern="Solid"/></Style><Style ss:ID="Currency"><NumberFormat ss:Format="#,##0"/></Style><Style ss:ID="Integer"><NumberFormat ss:Format="0"/></Style></Styles>'
            . '<Worksheet ss:Name="Finance ' . $data['month'] . '"><Table>'
            . '<Column ss:Width="80"/><Column ss:Width="75"/><Column ss:Width="125"/><Column ss:Width="100"/><Column ss:Width="180"/><Column ss:Width="120"/><Column ss:Width="100"/><Column ss:Width="70"/><Column ss:Width="55"/><Column ss:Width="80"/><Column ss:Width="80"/><Column ss:Width="90"/><Column ss:Width="90"/>'
            . implode('', $rows)
            . '</Table></Worksheet></Workbook>';

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->download('homiq-finance-' . $data['month'] . '.xls', $content);
    }

    private function reportData(): array
    {
        $month = $this->month();
        $first = new DateTimeImmutable($month . '-01');
        $last = $first->modify('last day of this month');
        $db = db_connect();
        $payments = $db->table('payments')->select(
            'payments.*, guests.full_name AS guest_name, reservations.check_in_date, reservations.check_out_date, '
            . 'rooms.room_number, rooms.floor AS guesthouse, '
            . '(SELECT COUNT(*) FROM reservations AS grouped_reservations '
            . 'WHERE COALESCE(grouped_reservations.booking_group_id, grouped_reservations.order_id) '
            . '= COALESCE(reservations.booking_group_id, reservations.order_id)) AS room_count',
            false
        )
            ->join('reservations', 'reservations.id = payments.reservation_id')
            ->join('guests', 'guests.id = reservations.guest_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->where('payments.status', 'Paid')->where('payment_date >=', $first->format('Y-m-d'))->where('payment_date <=', $last->format('Y-m-d'))->get()->getResultArray();
        $expenses = $db->table('expenses')->where('status !=', 'Rejected')->where('date_incurred >=', $first->format('Y-m-d'))->where('date_incurred <=', $last->format('Y-m-d'))->get()->getResultArray();
        $transactions = [];
        foreach ($payments as $payment) {
            $checkIn = new DateTimeImmutable((string) $payment['check_in_date']);
            $checkOut = new DateTimeImmutable((string) $payment['check_out_date']);
            $transactions[] = [
                'date' => $payment['payment_date'],
                'type' => 'Income',
                'payment_type' => $this->paymentTypeLabel($payment['type'] ?? null),
                'reference' => $payment['order_id'] ?? '-',
                'description' => $payment['guest_name'],
                'method' => $payment['payment_method'] ?? '-',
                'amount' => (float) $payment['amount'],
                'room' => 'Room ' . $payment['room_number'],
                'guesthouse' => 'GH ' . (int) $payment['guesthouse'],
                'check_in' => $payment['check_in_date'],
                'check_out' => $payment['check_out_date'],
                'nights' => max(1, (int) $checkIn->diff($checkOut)->days),
                'room_count' => max(1, (int) $payment['room_count']),
            ];
        }
        foreach ($expenses as $expense) {
            $transactions[] = [
                'date' => $expense['date_incurred'],
                'type' => 'Expense',
                'payment_type' => '-',
                'reference' => 'EXP-' . $expense['id'],
                'description' => $expense['description'],
                'method' => $expense['category'],
                'amount' => -(float) $expense['amount'],
                'room' => '',
                'guesthouse' => '',
                'check_in' => '',
                'check_out' => '',
                'nights' => '',
                'room_count' => '',
            ];
        }
        usort($transactions, static fn ($a, $b) => strcmp($b['date'], $a['date']));
        $perPage = 10;
        $totalTransactions = count($transactions);
        $totalPages = max(1, (int) ceil($totalTransactions / $perPage));
        $page = min($this->page(), $totalPages);
        $pagedTransactions = array_slice($transactions, ($page - 1) * $perPage, $perPage);
        $revenue = array_sum(array_map(static fn ($row) => (float) $row['amount'], $payments));
        $expenseTotal = array_sum(array_map(static fn ($row) => (float) $row['amount'], $expenses));
        $reservationTotal = (float) ($db->table('reservations')->selectSum('total_amount')->where('status !=', 'Cancelled')->get()->getRowArray()['total_amount'] ?? 0);
        $paidTotal = (float) ($db->table('payments')->selectSum('amount')->where('status', 'Paid')->get()->getRowArray()['amount'] ?? 0);

        // Count paid guest payments by the four payment methods offered in the
        // booking form. Percentages intentionally use payment count, not value.
        $methodCounts = ['Cash' => 0, 'Transfer' => 0, 'Credit Card' => 0, 'OTA' => 0];
        foreach ($payments as $payment) {
            $method = strtolower(trim((string) ($payment['payment_method'] ?? '')));
            $methodName = match ($method) {
                'cash' => 'Cash',
                'transfer', 'bank transfer' => 'Transfer',
                'credit card', 'creditcard' => 'Credit Card',
                'ota', 'online travel agency' => 'OTA',
                default => null,
            };
            if ($methodName !== null) $methodCounts[$methodName]++;
        }
        $paymentCount = array_sum($methodCounts);
        $paymentMethods = [];
        foreach ($methodCounts as $name => $count) {
            $paymentMethods[] = ['name' => $name, 'count' => $count, 'percentage' => $paymentCount === 0 ? 0 : (int) round(($count / $paymentCount) * 100)];
        }

        // Cash flow always ends at the selected reporting period, so changing the
        // month selector also gives the chart a meaningful six-month comparison.
        $cashFlow = ['labels' => [], 'income' => [], 'expenses' => []];
        for ($offset = 5; $offset >= 0; $offset--) {
            $period = $first->modify('-' . $offset . ' months');
            $periodStart = $period->modify('first day of this month')->format('Y-m-d');
            $periodEnd = $period->modify('last day of this month')->format('Y-m-d');
            $incomeRow = $db->table('payments')->selectSum('amount')
                ->where('status', 'Paid')->where('payment_date >=', $periodStart)->where('payment_date <=', $periodEnd)
                ->get()->getRowArray();
            $expenseRow = $db->table('expenses')->selectSum('amount')
                ->where('status !=', 'Rejected')->where('date_incurred >=', $periodStart)->where('date_incurred <=', $periodEnd)
                ->get()->getRowArray();
            $cashFlow['labels'][] = $period->format('M Y');
            $cashFlow['income'][] = (float) ($incomeRow['amount'] ?? 0);
            $cashFlow['expenses'][] = (float) ($expenseRow['amount'] ?? 0);
        }

        return [
            'month' => $month,
            'transactions' => $pagedTransactions,
            'allTransactions' => $transactions,
            'pagination' => ['page' => $page, 'perPage' => $perPage, 'totalItems' => $totalTransactions, 'totalPages' => $totalPages],
            'cashFlow' => $cashFlow,
            'paymentMethods' => $paymentMethods,
            'summary' => ['revenue' => $revenue, 'expenses' => $expenseTotal, 'netProfit' => $revenue - $expenseTotal, 'outstanding' => max(0, $reservationTotal - $paidTotal)],
        ];
    }

    private function paymentTypeLabel(?string $type): string
    {
        return match ($type) {
            'DP' => 'DP',
            'Completed' => 'Completed / Pelunasan',
            'Additional Charges' => 'Tambahan',
            default => $type ?: '-',
        };
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
        $ownerGuesthouse = RoleAccess::ownerGuesthouse((string) session('role'));
        if ($ownerGuesthouse !== null) {
            return $ownerGuesthouse;
        }

        $guesthouse = (int) $this->request->getGet('guesthouse');
        return $guesthouse >= 1 && $guesthouse <= 5 ? $guesthouse : 1;
    }

    private function ownerReportData(): array
    {
        $month = $this->month();
        $guesthouse = $this->ownerGuesthouse();
        $first = new DateTimeImmutable($month . '-01');
        $last = $first->modify('last day of this month');
        $db = db_connect();

        $payments = $db->table('payments')
            ->select('payments.id, payments.reservation_id, payments.payment_date, payments.type, payments.amount, payments.proof_path, reservations.total_amount, guests.full_name AS guest_name')
            ->join('reservations', 'reservations.id = payments.reservation_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->join('guests', 'guests.id = reservations.guest_id')
            ->where('payments.status', 'Paid')
            ->where('rooms.floor', $guesthouse)
            ->where('payments.payment_date >=', $first->format('Y-m-d'))
            ->where('payments.payment_date <=', $last->format('Y-m-d'))
            ->get()->getResultArray();

        // Revenue belonging to an Owner GH excludes company-only services.
        // A payment (including a DP) is split proportionally using the invoice
        // composition so Owner and company revenue are not recognized twice.
        $reservationIds = array_values(array_unique(array_map('intval', array_column($payments, 'reservation_id'))));
        $invoiceComposition = [];
        if ($reservationIds !== []) {
            $companyOnlyServices = ['Breakfast', 'Extra Bed', 'Laundry', 'Massage', 'Sewa Kendaraan'];
            foreach ($db->table('invoice_items')
                ->select('reservation_id, item_name, amount')
                ->whereIn('reservation_id', $reservationIds)
                ->get()
                ->getResultArray() as $item) {
                $reservationId = (int) $item['reservation_id'];
                $amount = (float) $item['amount'];
                $itemName = trim((string) $item['item_name']);
                $invoiceComposition[$reservationId] ??= ['rental' => 0.0, 'guest_charge' => 0.0, 'company' => 0.0];

                if (in_array($itemName, $companyOnlyServices, true)) {
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
            $composition = $invoiceComposition[$reservationId] ?? ['rental' => (float) $payment['total_amount'], 'guest_charge' => 0.0, 'company' => 0.0];
            $invoiceTotal = (float) $composition['rental'] + (float) $composition['guest_charge'] + (float) $composition['company'];
            if ($invoiceTotal <= 0) {
                $invoiceTotal = max(0.0, (float) $payment['total_amount']);
            }
            $paymentAmount = (float) $payment['amount'];
            $rentalAmount = $invoiceTotal > 0 ? $paymentAmount * ((float) $composition['rental'] / $invoiceTotal) : $paymentAmount;
            $guestChargeAmount = $invoiceTotal > 0 ? $paymentAmount * ((float) $composition['guest_charge'] / $invoiceTotal) : 0.0;
            $payment['owner_amount'] = max(0.0, $rentalAmount + $guestChargeAmount);
            $payment['owner_rental_amount'] = max(0.0, $rentalAmount);
            $payment['owner_guest_charge_amount'] = max(0.0, $guestChargeAmount);
            $ownerPayments[] = $payment;
        }

        $expenseBuilder = $db->table('expenses')
            ->select('expenses.id, expenses.date_incurred, expenses.category, expenses.description, expenses.amount, expenses.receipt_url')
            ->where('expenses.category !=', 'Fee Kerjasama')
            ->where('expenses.status !=', 'Rejected')
            ->where('expenses.date_incurred >=', $first->format('Y-m-d'))
            ->where('expenses.date_incurred <=', $last->format('Y-m-d'));
        $expenseBuilder->where(
            "(EXISTS (SELECT 1 FROM expense_guesthouses eg WHERE eg.expense_id = expenses.id AND eg.guesthouse = {$guesthouse})"
            . " OR (NOT EXISTS (SELECT 1 FROM expense_guesthouses eg2 WHERE eg2.expense_id = expenses.id) AND expenses.guesthouse = {$guesthouse}))",
            null,
            false
        );
        $expenses = $expenseBuilder->get()->getResultArray();

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
            $transactions[] = [
                'date' => (string) $expense['date_incurred'],
                'category' => (string) $expense['category'],
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
        $ownerSetup = $db->table('owner_guesthouse_setups')->where('guesthouse', $guesthouse)->get()->getRowArray();
        // The setup percentage belongs to the owner. The manager's cooperation
        // fee is the remaining percentage of distributable profit.
        $ownerSharePercentage = $ownerSetup === null ? 70.0 : (float) $ownerSetup['cooperation_fee_percentage'];
        $managerFeePercentage = 100 - $ownerSharePercentage;

        $perPage = 10;
        $totalItems = count($transactions);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = min($this->page(), $totalPages);

        return [
            'month' => $month,
            'guesthouse' => $guesthouse,
            'ownerGuesthouseLocked' => RoleAccess::ownerGuesthouse((string) session('role')) !== null,
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
                'managerShare' => $distributableProfit * ($managerFeePercentage / 100),
                'investorShare' => $distributableProfit * ($ownerSharePercentage / 100),
            ],
            'pagination' => ['page' => $page, 'perPage' => $perPage, 'totalItems' => $totalItems, 'totalPages' => $totalPages],
        ];
    }
}
