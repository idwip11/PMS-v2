<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** CF-1 ledger API. All effective timestamps are UTC; no automatic legacy import. */
final class CashLedgerService
{
    public function __construct(private BaseConnection $db) {}

    private function atomic(int $user, int $company, string $permission, callable $callback): mixed
    {
        // Own the transaction, so an outer rollback cannot make a returned posting look committed.
        if ($this->db->transDepth !== 0) throw new RuntimeException('Ledger harus memiliki batas transaksi sendiri.');
        if (! $this->db->transBegin()) throw new RuntimeException('Tidak dapat membuka transaksi ledger.');
        try {
            $row = $this->db->query('SELECT id FROM companies WHERE id = ? AND is_active = 1 FOR UPDATE', [$company])->getRowArray();
            if ($row === null) throw new RuntimeException('Perusahaan tidak tersedia.');
            $this->db->query('SELECT user_id FROM company_user_roles WHERE company_id = ? AND user_id = ? FOR UPDATE', [$company, $user]);
            CompanyFinanceAccess::require($user, $company, $permission, $this->db);
            $result = $callback();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) throw new RuntimeException('Posting ledger gagal.');
            return $result;
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }

    private function timestamp(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        if (! $date || $date->format('Y-m-d H:i:s') !== $value || (int) $date->format('Y') < 1000) throw new InvalidArgumentException('Tanggal UTC tidak valid.');
        return $value;
    }

    private function text(string $value, int $max): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) throw new InvalidArgumentException('Referensi/nama/alasan tidak valid.');
        return $value;
    }

    private function property(int $company, ?int $property): void
    {
        if ($property !== null && $this->db->table('company_properties')->where('company_id', $company)->where('property_id', $property)->countAllResults() !== 1) {
            throw new RuntimeException('Properti di luar cakupan holding.');
        }
    }

    private function audit(int $user, int $company, string $action, string $reference, array $details): void
    {
        if (! $this->db->table('company_finance_audit')->insert([
            'company_id' => $company, 'user_id' => $user, 'action' => $action, 'reference' => $reference,
            'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => gmdate('Y-m-d H:i:s'),
        ])) throw new RuntimeException('Audit ledger gagal.');
    }

    private function period(int $company, string $at): void
    {
        // Company row is held across post/close, including months without a row yet.
        $period = $this->db->table('company_finance_periods')->where('company_id', $company)->where('month', substr($at, 0, 7))->get()->getRowArray();
        if (($period['status'] ?? 'open') === 'closed') throw new RuntimeException('Periode sudah ditutup.');
    }

    public function createAccount(int $user, int $company, string $code, string $name, string $type, ?int $property = null): int
    {
        $code = $this->text($code, 40); $name = $this->text($name, 150);
        if (! in_array($type, ['cash', 'bank'], true)) throw new InvalidArgumentException('Jenis rekening tidak valid.');
        return $this->atomic($user, $company, 'approve', function () use ($user, $company, $code, $name, $type, $property) {
            $this->property($company, $property);
            $this->db->table('cash_accounts')->insert(['company_id' => $company, 'property_id' => $property, 'code' => $code, 'name' => $name, 'account_type' => $type, 'currency' => 'IDR', 'created_by' => $user, 'created_at' => gmdate('Y-m-d H:i:s')]);
            $id = (int) $this->db->insertID();
            $this->audit($user, $company, 'account_created', (string) $id, ['code' => $code, 'property_id' => $property]);
            return $id;
        });
    }

    public function opening(int $user, int $company, int $account, string $at, string $amount, string $reason): void
    {
        $at = $this->timestamp($at); $amount = CashAmount::format(CashAmount::cents($amount)); $reason = $this->text($reason, 500);
        $this->atomic($user, $company, 'approve', function () use ($user, $company, $account, $at, $amount, $reason) {
            $this->period($company, $at);
            $this->account($company, $account);
            if ($this->db->table('cash_opening_balances')->where('account_id', $account)->countAllResults() > 0
                || $this->db->table('cash_entries')->where('account_id', $account)->countAllResults() > 0) throw new RuntimeException('Saldo awal sudah ada atau rekening sudah memiliki mutasi.');
            $this->db->table('cash_opening_balances')->insert(['company_id' => $company, 'account_id' => $account, 'effective_at' => $at, 'amount' => $amount, 'verified_by' => $user, 'reason' => $reason, 'created_at' => gmdate('Y-m-d H:i:s')]);
            $this->audit($user, $company, 'opening_verified', (string) $account, ['amount' => $amount, 'effective_at' => $at, 'reason' => $reason]);
        });
    }

    private function account(int $company, int $id): array
    {
        $row = $this->db->table('cash_accounts')->where('company_id', $company)->where('id', $id)->where('is_active', 1)->get()->getRowArray();
        if (! $row || $row['currency'] !== 'IDR') throw new RuntimeException('Rekening di luar cakupan atau tidak aktif.');
        return $row;
    }

    /** Entries signed: receipt +, disbursement -, transfer exactly two balanced legs. */
    public function post(int $user, int $company, string $key, string $kind, string $classification, string $at, string $description, array $entries, ?int $property = null): int
    {
        if (! in_array($kind, ['receipt', 'disbursement', 'transfer'], true)) throw new InvalidArgumentException('Jenis mutasi tidak valid.');
        $payload = $this->payload($key, $kind, $classification, $at, $description, $entries, $property);
        return $this->atomic($user, $company, 'post', fn () => $this->insertPosting($user, $company, $payload));
    }

    /** Own one transaction for source settlement + ledger. Callback/writer are server code only. */
    public function integrate(int $user, int $company, string $permission, callable $callback): mixed
    {
        return $this->atomic($user, $company, $permission, function () use ($user, $company, $callback) {
            $write = function (string $key, string $kind, string $classification, string $at, string $description, array $entries, ?int $property, string $sourceType, string $sourceReference) use ($user, $company): int {
                if (! in_array($kind, ['receipt', 'disbursement', 'transfer'], true)) throw new InvalidArgumentException('Jenis integrasi tidak valid.');
                $payload = $this->payload($key, $kind, $classification, $at, $description, $entries, $property);
                $payload['source_type'] = $this->text($sourceType, 40);
                $payload['source_reference'] = $this->text($sourceReference, 120);
                return $this->insertPosting($user, $company, $payload);
            };
            return $callback($write);
        });
    }

    private function payload(string $key, string $kind, string $classification, string $at, string $description, array $entries, ?int $property, ?int $reverses = null): array
    {
        $key = $this->text($key, 120); $description = $this->text($description, 500); $at = $this->timestamp($at);
        if (! in_array($classification, ['operating', 'investing', 'financing', 'internal'], true)) throw new InvalidArgumentException('Klasifikasi tidak valid.');
        if (count($entries) < 1 || count($entries) > 2) throw new InvalidArgumentException('Mutasi harus memiliki satu atau dua kaki.');
        $normalized = []; $sum = 0;
        foreach ($entries as $entry) {
            if (! is_array($entry) || ! isset($entry['account_id'], $entry['amount']) || ! is_string($entry['amount'])) throw new InvalidArgumentException('Nominal harus string desimal.');
            $id = filter_var($entry['account_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $amount = CashAmount::cents($entry['amount']);
            if ($id === false || $amount === 0 || isset($normalized[$id])) throw new InvalidArgumentException('Kaki mutasi tidak valid/duplikat.');
            $normalized[$id] = ['account_id' => $id, 'amount' => CashAmount::format($amount)];
            $sum += $amount;
        }
        ksort($normalized);
        if ($kind === 'transfer' && (count($normalized) !== 2 || $sum !== 0 || $classification !== 'internal')) throw new InvalidArgumentException('Transfer harus dua rekening, seimbang, dan internal.');
        if (in_array($kind, ['receipt', 'disbursement'], true) && (count($normalized) !== 1 || $classification === 'internal' || ($kind === 'receipt' ? $sum <= 0 : $sum >= 0))) throw new InvalidArgumentException('Arah mutasi salah.');
        return ['event_key' => $key, 'kind' => $kind, 'classification' => $classification, 'effective_at' => $at, 'description' => $description, 'entries' => array_values($normalized), 'property_id' => $property, 'reverses_id' => $reverses];
    }

    private function insertPosting(int $user, int $company, array $payload): int
    {
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $old = $this->db->table('cash_transactions')->where('company_id', $company)->where('event_key', $payload['event_key'])->get()->getRowArray();
        if ($old) {
            if (! hash_equals($old['payload_hash'], $hash)) throw new RuntimeException('Idempotency key dipakai untuk payload berbeda.');
            return (int) $old['id'];
        }
        $this->property($company, $payload['property_id']);
        $this->period($company, $payload['effective_at']);
        foreach ($payload['entries'] as $entry) {
            $this->account($company, $entry['account_id']);
            $opening = $this->db->table('cash_opening_balances')->where('company_id', $company)->where('account_id', $entry['account_id'])->get()->getRowArray();
            if (! $opening || $payload['effective_at'] < $opening['effective_at']) throw new RuntimeException('Saldo awal belum diverifikasi atau mutasi sebelum cutover.');
        }
        $header = $payload; unset($header['entries']);
        $header += ['company_id' => $company, 'payload_hash' => $hash, 'source_type' => 'manual_cf1', 'source_reference' => $payload['event_key'], 'posted_by' => $user, 'created_at' => gmdate('Y-m-d H:i:s')];
        if (! $this->db->table('cash_transactions')->insert($header)) throw new RuntimeException('Header mutasi gagal.');
        $id = (int) $this->db->insertID();
        foreach ($payload['entries'] as $entry) {
            if (! $this->db->table('cash_entries')->insert($entry + ['company_id' => $company, 'transaction_id' => $id])) throw new RuntimeException('Kaki mutasi gagal.');
        }
        $this->audit($user, $company, 'cash_posted', (string) $id, $payload);
        return $id;
    }

    public function reverse(int $user, int $company, int $original, string $key, string $at, string $reason): int
    {
        return $this->atomic($user, $company, 'approve', function () use ($user, $company, $original, $key, $at, $reason) {
            $old = $this->db->table('cash_transactions')->where('company_id', $company)->where('id', $original)->get()->getRowArray();
            if (! $old || $old['kind'] === 'reversal' || $at < $old['effective_at']) throw new RuntimeException('Transaksi pembalik tidak valid.');
            $entries = $this->db->table('cash_entries')->where('company_id', $company)->where('transaction_id', $original)->get()->getResultArray();
            $entries = array_map(static fn ($e) => ['account_id' => (int) $e['account_id'], 'amount' => CashAmount::format(-CashAmount::cents($e['amount']))], $entries);
            $payload = $this->payload($key, 'reversal', $old['classification'], $at, $reason, $entries, $old['property_id'] === null ? null : (int) $old['property_id'], $original);
            $existing = $this->db->table('cash_transactions')->where('company_id', $company)->where('reverses_id', $original)->get()->getRowArray();
            if ($existing && $existing['event_key'] !== $key) throw new RuntimeException('Transaksi sudah dibalik.');
            return $this->insertPosting($user, $company, $payload);
        });
    }

    public function setPeriod(int $user, int $company, string $month, bool $closed, string $reason): void
    {
        if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $month) !== 1) throw new InvalidArgumentException('Bulan tidak valid.');
        $reason = $this->text($reason, 500);
        $this->atomic($user, $company, 'close', function () use ($user, $company, $month, $closed, $reason) {
            $this->db->query('INSERT INTO company_finance_periods (company_id,month,status,changed_by,reason,updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), changed_by=VALUES(changed_by), reason=VALUES(reason), updated_at=VALUES(updated_at)', [$company, $month, $closed ? 'closed' : 'open', $user, $reason, gmdate('Y-m-d H:i:s')]);
            $this->audit($user, $company, $closed ? 'period_locked' : 'period_reopened', $month, ['reason' => $reason]);
        });
    }

    public function balances(int $user, int $company, ?string $asOf = null): array
    {
        CompanyFinanceAccess::require($user, $company, 'view', $this->db);
        $asOf = $this->timestamp($asOf ?? gmdate('Y-m-d H:i:s'));
        // A single SQL snapshot keeps opening and movements consistent while posting.
        return $this->db->query('SELECT a.id,a.code,a.name,a.property_id,a.is_active,a.currency,o.effective_at AS cutover_at, CASE WHEN o.account_id IS NULL OR o.effective_at > ? THEN NULL ELSE o.amount + COALESCE((SELECT SUM(e.amount) FROM cash_entries e JOIN cash_transactions t ON t.id=e.transaction_id AND t.company_id=e.company_id WHERE e.company_id=a.company_id AND e.account_id=a.id AND t.effective_at >= o.effective_at AND t.effective_at <= ?),0) END AS balance FROM cash_accounts a LEFT JOIN cash_opening_balances o ON o.account_id=a.id AND o.company_id=a.company_id WHERE a.company_id=? ORDER BY a.id', [$asOf, $asOf, $company])->getResultArray();
    }

    public function externalFlow(int $user, int $company, string $from, string $to): array
    {
        CompanyFinanceAccess::require($user, $company, 'view', $this->db);
        $from = $this->timestamp($from); $to = $this->timestamp($to);
        if ($from >= $to) throw new InvalidArgumentException('Rentang waktu tidak valid.');
        return $this->db->query("SELECT t.classification, COALESCE(SUM(CASE WHEN e.amount>0 THEN e.amount ELSE 0 END),0) AS cash_in, COALESCE(SUM(CASE WHEN e.amount<0 THEN -e.amount ELSE 0 END),0) AS cash_out, COALESCE(SUM(e.amount),0) AS net FROM cash_transactions t JOIN cash_entries e ON e.transaction_id=t.id AND e.company_id=t.company_id WHERE t.company_id=? AND t.classification!='internal' AND t.effective_at>=? AND t.effective_at<? GROUP BY t.classification", [$company, $from, $to])->getResultArray();
    }

    public function transactions(int $user, int $company, array $filters = [], int $page = 1, int $perPage = 10): array
    {
        CompanyFinanceAccess::require($user, $company, 'view', $this->db);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $b = $this->db->table('cash_transactions t')
            ->select('t.*, p.name AS property_name, p.code AS property_code, u.username AS posted_by_username')
            ->join('users u', 'u.id = t.posted_by')
            ->join('properties p', 'p.id = t.property_id', 'left')
            ->where('t.company_id', $company);

        if (! empty($filters['account_id'])) {
            $accId = (int) $filters['account_id'];
            $b->where("EXISTS (SELECT 1 FROM cash_entries ce WHERE ce.transaction_id = t.id AND ce.company_id = t.company_id AND ce.account_id = {$accId})", null, false);
        }
        if (! empty($filters['kind'])) {
            $b->where('t.kind', $filters['kind']);
        }
        if (! empty($filters['classification'])) {
            $b->where('t.classification', $filters['classification']);
        }
        if (! empty($filters['property_id'])) {
            $b->where('t.property_id', (int) $filters['property_id']);
        }
        if (! empty($filters['month'])) {
            $m = trim((string) $filters['month']);
            if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $m) === 1) {
                $start = $m . '-01 00:00:00';
                $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d') . ' 23:59:59';
                $b->where('t.effective_at >=', $start)->where('t.effective_at <=', $end);
            }
        }
        if (! empty($filters['search'])) {
            $s = trim((string) $filters['search']);
            $b->groupStart()
                ->like('t.description', $s)
                ->orLike('t.source_reference', $s)
                ->orLike('t.event_key', $s)
            ->groupEnd();
        }

        $totalItems = $b->countAllResults(false);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = $b->orderBy('t.effective_at', 'DESC')->orderBy('t.id', 'DESC')->limit($perPage, $offset)->get()->getResultArray();

        if ($rows !== []) {
            $txIds = array_column($rows, 'id');
            $entries = $this->db->table('cash_entries e')
                ->select('e.*, a.name AS account_name, a.code AS account_code, a.account_type')
                ->join('cash_accounts a', 'a.id = e.account_id AND a.company_id = e.company_id')
                ->where('e.company_id', $company)
                ->whereIn('e.transaction_id', $txIds)
                ->get()
                ->getResultArray();

            $entriesByTx = [];
            foreach ($entries as $entry) {
                $entriesByTx[(int) $entry['transaction_id']][] = $entry;
            }

            foreach ($rows as &$row) {
                $row['entries'] = $entriesByTx[(int) $row['id']] ?? [];
                $inCents = 0;
                $outCents = 0;
                foreach ($row['entries'] as $e) {
                    $c = CashAmount::cents((string) $e['amount']);
                    if ($c > 0) $inCents += $c;
                    if ($c < 0) $outCents += abs($c);
                }
                $row['cash_in'] = CashAmount::format($inCents);
                $row['cash_out'] = CashAmount::format($outCents);
            }
            unset($row);
        }

        return [
            'items' => $rows,
            'total' => $totalItems,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
        ];
    }
}
