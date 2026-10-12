<?php
namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use RuntimeException;

/** Explicit treasury confirmation; legacy booking/expense records are not cash by inference. */
final class CompanyCashIntegration
{
    private CashLedgerService $ledger;
    public function __construct(private BaseConnection $db) { $this->ledger = new CashLedgerService($db); }

    private function positive(string $amount): int
    {
        $value = CashAmount::cents($amount);
        if ($value <= 0) throw new RuntimeException('Nominal wajib lebih dari nol.');
        return $value;
    }

    private function actual(string $at, string $note): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $at, new \DateTimeZone('UTC'));
        if (! $date || $date->format('Y-m-d H:i:s') !== $at || $at < '1000-01-01 00:00:00' || $at > gmdate('Y-m-d H:i:s')) throw new RuntimeException('Waktu kas aktual harus valid, dalam UTC dan tidak di masa depan.');
        if (trim($note) === '' || mb_strlen($note) > 500) throw new RuntimeException('Catatan konfirmasi/referensi wajib (maksimal 500 karakter).');
    }

    private function source(int $company, string $type, int $id): array
    {
        $query = match ($type) {
            'payment' => 'SELECT p.*,r.property_id FROM payments p JOIN reservations r ON r.id=p.reservation_id JOIN company_properties cp ON cp.property_id=r.property_id WHERE cp.company_id=? AND p.id=? FOR UPDATE',
            'expense' => 'SELECT e.* FROM expenses e JOIN company_properties cp ON cp.property_id=e.property_id WHERE cp.company_id=? AND e.id=? FOR UPDATE',
            'overhead' => 'SELECT * FROM company_expenses WHERE company_id=? AND id=? FOR UPDATE',
        };
        $row = $this->db->query($query, [$company, $id])->getRowArray();
        if (! $row) throw new RuntimeException('Sumber transaksi di luar cakupan atau tidak ditemukan.');
        return $row;
    }

    private function prior(int $company, string $key): int
    {
        return (int) ($this->db->table('cash_transactions')->where('company_id', $company)->where('event_key', $key)->get()->getRowArray()['id'] ?? 0);
    }

    private function sum(string $table, string $column, int $company, int $source, int $exclude = 0, bool $gross = false, ?string $before = null): int
    {
        // Table/column arguments are constants from server code, never request values.
        $expr = $gross ? '(s.amount+s.withheld_fee)' : 's.amount';
        $sql = "SELECT COALESCE(SUM({$expr}),0) AS amount FROM {$table} s JOIN cash_transactions t ON t.id=s.transaction_id AND t.company_id=s.company_id WHERE s.company_id=? AND s.{$column}=? AND s.transaction_id!=? AND NOT EXISTS(SELECT 1 FROM cash_transactions rev WHERE rev.reverses_id=t.id)";
        $args = [$company, $source, $exclude];
        if ($before !== null) { $sql .= ' AND t.effective_at<=?'; $args[] = $before; }
        return CashAmount::cents((string) $this->db->query($sql, $args)->getRowArray()['amount']);
    }

    private function link(string $table, int $company, int $transaction, array $fields): void
    {
        $old = $this->db->table($table)->where('transaction_id', $transaction)->get()->getRowArray();
        if ($old) {
            foreach ($fields as $field => $value) if ((string) $old[$field] !== (string) $value) throw new RuntimeException('Retry sumber memakai payload berbeda.');
            return;
        }
        if (! $this->db->table($table)->insert($fields + ['company_id' => $company, 'transaction_id' => $transaction, 'created_at' => gmdate('Y-m-d H:i:s')])) throw new RuntimeException('Relasi sumber kas gagal disimpan.');
    }

    public function receipt(int $user, int $company, int $payment, int $account, string $amount, string $fee, string $at, string $key, string $note): int
    {
        $net = $this->positive($amount); $withheld = CashAmount::cents($fee); $this->actual($at, $note);
        if ($withheld < 0) throw new RuntimeException('Potongan OTA tidak boleh negatif.');
        return $this->ledger->integrate($user, $company, 'post', function ($write) use ($company, $payment, $account, $amount, $fee, $at, $key, $note, $net, $withheld) {
            $source = $this->source($company, 'payment', $payment);
            if (! in_array($source['status'], ['Paid', 'Refund_Pending'], true)) throw new RuntimeException('Pembayaran sumber belum berstatus Paid/refund pending.');
            if ($withheld > 0 && $source['payment_method'] !== 'OTA') throw new RuntimeException('Potongan hanya untuk pencairan OTA.');
            $opening = $this->db->table('cash_payment_opening_links')->where('payment_id', $payment)->get()->getRowArray();
            $used = $this->sum('cash_payment_receipts', 'payment_id', $company, $payment, $this->prior($company, $key), true)
                + ($opening ? CashAmount::cents($opening['amount']) + CashAmount::cents($opening['withheld_fee']) : 0);
            if ($used + $net + $withheld > CashAmount::cents($source['amount'])) throw new RuntimeException('Penerimaan + potongan melebihi nominal sumber; periksa mapping.');
            $tx = $write($key, 'receipt', 'operating', $at, $note, [['account_id' => $account, 'amount' => $amount]], (int) $source['property_id'], 'payment_receipt', (string) $payment);
            $this->link('cash_payment_receipts', $company, $tx, ['payment_id' => $payment, 'amount' => CashAmount::format($net), 'withheld_fee' => CashAmount::format($withheld), 'source_amount' => $source['amount']]);
            // Fee is noncash withholding metadata, not a fictitious bank disbursement.
            return $tx;
        });
    }

    public function linkOpeningPayment(int $user, int $company, int $payment, int $account, string $amount, string $fee, string $receivedAt, string $note): void
    {
        $net = $this->positive($amount); $withheld = CashAmount::cents($fee); $this->actual($receivedAt, $note);
        if ($withheld < 0) throw new RuntimeException('Potongan tidak boleh negatif.');
        $this->ledger->integrate($user, $company, 'approve', function () use ($user, $company, $payment, $account, $net, $withheld, $receivedAt, $note) {
            $source = $this->source($company, 'payment', $payment);
            $opening = $this->db->table('cash_opening_balances')->where('company_id', $company)->where('account_id', $account)->get()->getRowArray();
            if (! $opening || $receivedAt >= $opening['effective_at']) throw new RuntimeException('Mapping saldo awal hanya untuk penerimaan sebelum cutover rekening.');
            if (! in_array($source['status'], ['Paid', 'Refund_Pending'], true) || ($withheld > 0 && $source['payment_method'] !== 'OTA')) throw new RuntimeException('Sumber mapping tidak valid.');
            if ($net + $withheld + $this->sum('cash_payment_receipts', 'payment_id', $company, $payment, 0, true) > CashAmount::cents($source['amount'])) throw new RuntimeException('Mapping melebihi nominal sumber.');
            $fields = ['company_id' => $company, 'payment_id' => $payment, 'account_id' => $account, 'amount' => CashAmount::format($net), 'withheld_fee' => CashAmount::format($withheld), 'received_at' => $receivedAt, 'verified_by' => $user, 'reason' => $note, 'created_at' => gmdate('Y-m-d H:i:s')];
            $old = $this->db->table('cash_payment_opening_links')->where('payment_id', $payment)->get()->getRowArray();
            if ($old) {
                foreach (['company_id','account_id','amount','withheld_fee','received_at','reason'] as $field) if ((string) $old[$field] !== (string) $fields[$field]) throw new RuntimeException('Mapping saldo awal sudah ada dan tidak dapat ditimpa.');
                return;
            }
            if (! $this->db->table('cash_payment_opening_links')->insert($fields)) throw new RuntimeException('Mapping gagal.');
            $this->db->table('company_finance_audit')->insert(['company_id' => $company, 'user_id' => $user, 'action' => 'opening_payment_link', 'reference' => (string) $payment, 'details' => json_encode($fields + ['received_at' => $receivedAt], JSON_THROW_ON_ERROR), 'created_at' => gmdate('Y-m-d H:i:s')]);
        });
    }

    public function refund(int $user, int $company, int $payment, int $account, string $amount, string $at, string $key, string $note): int
    {
        $net = $this->positive($amount); $this->actual($at, $note);
        return $this->ledger->integrate($user, $company, 'approve', function ($write) use ($company, $payment, $account, $amount, $net, $at, $key, $note) {
            $source = $this->source($company, 'payment', $payment);
            if ($source['status'] !== 'Refund_Pending') throw new RuntimeException('Refund harus berasal dari pembayaran refund pending.');
            $opening = $this->db->table('cash_payment_opening_links')->where('payment_id', $payment)->get()->getRowArray();
            $received = $this->sum('cash_payment_receipts', 'payment_id', $company, $payment, 0, false, $at) + ($opening && $opening['received_at'] <= $at ? CashAmount::cents($opening['amount']) : 0);
            $paid = $this->sum('cash_payment_refunds', 'payment_id', $company, $payment, $this->prior($company, $key));
            if ($paid + $net > $received) throw new RuntimeException('Refund melebihi penerimaan kas terkonfirmasi; petakan riwayat terlebih dahulu.');
            $tx = $write($key, 'disbursement', 'operating', $at, $note, [['account_id' => $account, 'amount' => CashAmount::format(-$net)]], (int) $source['property_id'], 'payment_refund', (string) $payment);
            $this->link('cash_payment_refunds', $company, $tx, ['payment_id' => $payment, 'amount' => CashAmount::format($net)]);
            // Legacy status/history is preserved; actual refunds are separately auditable.
            return $tx;
        });
    }

    public function settleExpense(int $user, int $company, int $expense, int $account, string $amount, string $at, string $key, string $note, bool $overhead = false): int
    {
        $net = $this->positive($amount); $this->actual($at, $note);
        return $this->ledger->integrate($user, $company, 'post', function ($write) use ($company, $expense, $account, $amount, $net, $at, $key, $note, $overhead) {
            $source = $this->source($company, $overhead ? 'overhead' : 'expense', $expense);
            if ($source['status'] !== 'Approved') throw new RuntimeException('Expense harus disetujui sebelum dibayar.');
            if (substr($at, 0, 10) < $source['date_incurred']) throw new RuntimeException('Pembayaran mendahului tanggal expense; alur uang muka belum didukung.');
            $table = $overhead ? 'company_expense_settlements' : 'expense_settlements';
            $used = $this->sum($table, 'expense_id', $company, $expense, $this->prior($company, $key));
            if ($used + $net > CashAmount::cents($source['amount'])) throw new RuntimeException('Pembayaran melebihi sisa expense.');
            $tx = $write($key, 'disbursement', 'operating', $at, $note, [['account_id' => $account, 'amount' => CashAmount::format(-$net)]], $overhead ? null : (int) $source['property_id'], $overhead ? 'company_expense' : 'property_expense', (string) $expense);
            $this->link($table, $company, $tx, ['expense_id' => $expense, 'amount' => CashAmount::format($net)]);
            return $tx;
        });
    }

    public function createOverhead(int $user, int $company, string $key, string $description, string $category, string $amount, string $date): int
    {
        $this->positive($amount);
        if ($description === '' || mb_strlen($description) > 500 || $category === '' || mb_strlen($category) > 100 || $key === '' || mb_strlen($key) > 120 || ! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) throw new RuntimeException('Data overhead tidak valid.');
        return $this->ledger->integrate($user, $company, 'post', function () use ($user, $company, $key, $description, $category, $amount, $date) {
            $fields = ['company_id' => $company, 'event_key' => $key, 'description' => $description, 'category' => $category, 'amount' => CashAmount::format(CashAmount::cents($amount)), 'date_incurred' => $date];
            $old = $this->db->table('company_expenses')->where('company_id', $company)->where('event_key', $key)->get()->getRowArray();
            if ($old) { foreach ($fields as $field => $value) if ((string) $old[$field] !== (string) $value) throw new RuntimeException('Event overhead dipakai untuk data berbeda.'); return (int) $old['id']; }
            $this->db->table('company_expenses')->insert($fields + ['created_by' => $user, 'created_at' => gmdate('Y-m-d H:i:s')]);
            $id = (int) $this->db->insertID(); $this->audit($user, $company, 'overhead_created', $id, $fields); return $id;
        });
    }

    private function audit(int $user, int $company, string $action, int $id, array $details): void
    {
        if (! $this->db->table('company_finance_audit')->insert(['company_id' => $company, 'user_id' => $user, 'action' => $action, 'reference' => (string) $id, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => gmdate('Y-m-d H:i:s')])) throw new RuntimeException('Audit gagal.');
    }

    public function approveOverhead(int $user, int $company, int $expense): void
    {
        $this->ledger->integrate($user, $company, 'approve', function () use ($user, $company, $expense) {
            $row = $this->source($company, 'overhead', $expense);
            if ((int) $row['created_by'] === $user) throw new RuntimeException('Pembuat tidak boleh menyetujui overhead sendiri.');
            if ($row['status'] === 'Approved') return;
            if ($row['status'] !== 'Pending') throw new RuntimeException('Status overhead tidak dapat disetujui.');
            $this->db->table('company_expenses')->where('id', $expense)->update(['status' => 'Approved', 'approved_by' => $user]);
            $this->audit($user, $company, 'overhead_approved', $expense, []);
        });
    }

    public function manual(int $user, int $company, string $operation, int $account, ?int $destination, string $amount, string $at, string $key, string $note): int
    {
        $net = $this->positive($amount); $this->actual($at, $note);
        if (! in_array($operation, ['transfer', 'capital', 'loan_received', 'loan_principal', 'bank_fee', 'management_salary', 'asset_purchase'], true)) throw new RuntimeException('Jenis transaksi manual tidak valid.');
        $kind = in_array($operation, ['capital','loan_received'], true) ? 'receipt' : 'disbursement';
        $classification = $operation === 'asset_purchase' ? 'investing' : (in_array($operation, ['bank_fee', 'management_salary'], true) ? 'operating' : 'financing');
        $entries = [['account_id' => $account, 'amount' => CashAmount::format($kind === 'receipt' ? $net : -$net)]];
        if ($operation === 'transfer') { $kind = 'transfer'; $classification = 'internal'; $entries[] = ['account_id' => $destination, 'amount' => CashAmount::format($net)]; }
        return $this->ledger->integrate($user, $company, 'approve', fn ($write) => $write($key, $kind, $classification, $at, $note, $entries, null, $operation, $key));
    }

    public function preview(int $user, int $company): array
    {
        CompanyFinanceAccess::require($user, $company, 'view', $this->db);
        // Preview only, bounded to latest 100 per source; nothing is imported automatically.
        $payments = $this->db->query("SELECT p.id,p.amount,p.payment_date,p.payment_method,p.status,r.order_id,r.property_id,COALESCE((SELECT SUM(s.amount+s.withheld_fee) FROM cash_payment_receipts s WHERE s.payment_id=p.id),0)+COALESCE((SELECT SUM(o.amount+o.withheld_fee) FROM cash_payment_opening_links o WHERE o.payment_id=p.id),0) AS mapped, COALESCE((SELECT SUM(f.amount) FROM cash_payment_refunds f WHERE f.payment_id=p.id),0) AS refunded FROM payments p JOIN reservations r ON r.id=p.reservation_id JOIN company_properties cp ON cp.property_id=r.property_id WHERE cp.company_id=? AND p.status IN ('Paid','Refund_Pending') ORDER BY p.id DESC LIMIT 100", [$company])->getResultArray();
        $expenses = $this->db->query('SELECT e.id,e.property_id,e.description,e.amount,e.status,e.date_incurred,COALESCE((SELECT SUM(s.amount) FROM expense_settlements s WHERE s.expense_id=e.id),0) AS settled FROM expenses e JOIN company_properties cp ON cp.property_id=e.property_id WHERE cp.company_id=? ORDER BY e.id DESC LIMIT 100', [$company])->getResultArray();
        $overheads = $this->db->query('SELECT e.*,COALESCE((SELECT SUM(s.amount) FROM company_expense_settlements s WHERE s.expense_id=e.id),0) AS settled FROM company_expenses e WHERE company_id=? ORDER BY e.id DESC LIMIT 100', [$company])->getResultArray();
        // Linked correction reversals remain in history but no longer consume source limits.
        foreach ($payments as &$row) {
            $opening = $this->db->table('cash_payment_opening_links')->where('payment_id', $row['id'])->get()->getRowArray();
            $row['mapped'] = CashAmount::format($this->sum('cash_payment_receipts', 'payment_id', $company, (int) $row['id'], 0, true) + ($opening ? CashAmount::cents($opening['amount']) + CashAmount::cents($opening['withheld_fee']) : 0));
            $row['refunded'] = CashAmount::format($this->sum('cash_payment_refunds', 'payment_id', $company, (int) $row['id']));
            $snapshots = $this->db->table('cash_payment_receipts')->where('company_id', $company)->where('payment_id', $row['id'])->get()->getResultArray();
            $changed = false;
            foreach ($snapshots as $snapshot) if (CashAmount::cents($snapshot['source_amount']) !== CashAmount::cents($row['amount'])) $changed = true;
            $row['review'] = $changed ? 'Nominal sumber berubah; perlu review' : (CashAmount::cents($row['mapped']) < CashAmount::cents($row['amount']) ? 'Belum/parsial dipetakan; kas belum terverifikasi penuh' : 'Terpetakan');
        }
        unset($row);
        foreach ($expenses as &$row) $row['settled'] = CashAmount::format($this->sum('expense_settlements', 'expense_id', $company, (int) $row['id']));
        unset($row);
        foreach ($overheads as &$row) $row['settled'] = CashAmount::format($this->sum('company_expense_settlements', 'expense_id', $company, (int) $row['id']));
        unset($row);
        return ['payments' => $payments, 'expenses' => $expenses, 'overheads' => $overheads];
    }
}
