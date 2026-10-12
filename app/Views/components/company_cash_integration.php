<?php
use App\Libraries\CashAmount;

$action = site_url('finance/company/cash') . '?company=' . (int) $company['id'];
?>
<?php
$unmappedPayments = array_filter($preview['payments'] ?? [], static fn($p) => ((float)$p['amount'] - (float)($p['mapped'] ?? 0)) > 0);
$unmappedCount = count($unmappedPayments);
$totalUnmapped = array_reduce($unmappedPayments, static fn($c, $p) => $c + ((float)$p['amount'] - (float)($p['mapped'] ?? 0)), 0.0);
?>
<div class="card border-0 shadow-sm mt-4"><div class="card-body p-4">
<h3 class="h5">Preview sumber &amp; konfirmasi kas</h3>
<p class="text-secondary">Maksimal 100 sumber terbaru per kelompok. Belum dipetakan bukan berarti saldo bank nol. Preview tidak mengimpor riwayat otomatis; kas diposting hanya setelah dikonfirmasi. Pembayaran booking lama tetap utuh.</p>
<?php foreach (['payments' => 'Pembayaran tamu / OTA', 'expenses' => 'Expense properti', 'overheads' => 'Overhead holding'] as $type => $label): ?>
<details class="mb-3" open>
    <summary class="fw-semibold d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span><?= esc($label) ?></span>
        <?php if ($type === 'payments' && $unmappedCount > 0 && $canPost): ?>
            <form method="post" action="<?= esc($action) ?>" class="d-inline mb-0" onsubmit="return confirm('Konfirmasi sekaligus seluruh <?= $unmappedCount ?> pembayaran tamu (Total <?= CashAmount::rp($totalUnmapped) ?>) ke kas konsolidasi holding?');">
                <?= csrf_field() ?>
                <input type="hidden" name="operation" value="receipt_all">
                <input type="hidden" name="confirmed" value="1">
                <input type="hidden" name="tab" value="treasury">
                <input type="hidden" name="sub" value="operations">
                <input type="hidden" name="month" value="<?= esc($selectedMonth ?? date('Y-m')) ?>">
                <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2 extra-small">
                    <i class="bi bi-check-all me-1"></i>Konfirmasi Semua (<?= $unmappedCount ?>)
                </button>
            </form>
        <?php endif; ?>
    </summary>
    <div class="table-responsive mt-2">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Sumber</th>
                    <th>Nominal</th>
                    <th>Status</th>
                    <th><?= $type === 'payments' ? 'Terpetakan' : 'Sudah dibayar' ?></th>
                    <th>Refund</th>
                    <?php if ($type === 'payments' && $canPost): ?>
                        <th class="text-end">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($preview[$type] as $row): ?>
                <tr>
                    <td>
                        <?= (int) $row['id'] ?> &bull; <?= esc($row['description'] ?? $row['order_id'] ?? '-') ?>
                        <?php if (isset($row['review'])): ?>
                            <div class="small text-secondary"><?= esc($row['review']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="fw-semibold"><?= CashAmount::rp($row['amount']) ?></td>
                    <td><span class="badge bg-light text-dark border"><?= esc($row['status']) ?></span></td>
                    <td><?= CashAmount::rp($row['mapped'] ?? $row['settled'] ?? 0) ?></td>
                    <td><?= isset($row['refunded']) && (float)$row['refunded'] > 0 ? CashAmount::rp($row['refunded']) : '-' ?></td>
                    <?php if ($type === 'payments' && $canPost): ?>
                        <td class="text-end">
                            <?php
                            $unmapped = (float) $row['amount'] - (float) ($row['mapped'] ?? 0);
                            if ($unmapped > 0):
                            ?>
                                <form method="post" action="<?= esc($action) ?>" class="d-inline" onsubmit="return confirm('Konfirmasi penerimaan kas senilai <?= CashAmount::rp($unmapped) ?> ke kas holding?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="operation" value="receipt">
                                    <input type="hidden" name="source_id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="account_id" value="<?= (int) ($defaultAccountId ?? ($accounts[0]['id'] ?? 1)) ?>">
                                    <input type="hidden" name="amount" value="<?= esc((string) $unmapped) ?>">
                                    <input type="hidden" name="fee" value="0.00">
                                    <input type="hidden" name="effective_at" value="<?= esc($row['payment_date'] ?: date('Y-m-d H:i:s')) ?>">
                                    <input type="hidden" name="note" value="Konfirmasi penerimaan booking #<?= esc($row['order_id']) ?>">
                                    <input type="hidden" name="confirmed" value="1">
                                    <input type="hidden" name="event_key" value="<?= esc(bin2hex(random_bytes(24))) ?>">
                                    <button type="submit" class="btn btn-sm btn-primary py-0 px-2 extra-small">
                                        <i class="bi bi-check-circle me-1"></i>Konfirmasi
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border"><i class="bi bi-check-all me-1"></i>Tuntas</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($preview[$type] === []): ?>
                <tr><td colspan="<?= ($type === 'payments' && $canPost) ? '6' : '5' ?>" class="text-secondary py-3 text-center">Belum ada data transaksi sumber.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($type === 'payments' && $unmappedCount > 0 && $canPost): ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center bg-light p-3 rounded mt-3 border">
            <div>
                <div class="fw-bold text-dark"><i class="bi bi-layers-fill text-primary me-1"></i>Konfirmasi Sekaligus</div>
                <div class="small text-secondary">Terdapat <strong><?= $unmappedCount ?> pembayaran tamu</strong> yang belum dipetakan senilai total <strong><?= CashAmount::rp($totalUnmapped) ?></strong>.</div>
            </div>
            <form method="post" action="<?= esc($action) ?>" class="mt-2 mt-sm-0" onsubmit="return confirm('Konfirmasi sekaligus seluruh <?= $unmappedCount ?> pembayaran tamu (Total <?= CashAmount::rp($totalUnmapped) ?>) ke kas konsolidasi holding?');">
                <?= csrf_field() ?>
                <input type="hidden" name="operation" value="receipt_all">
                <input type="hidden" name="confirmed" value="1">
                <input type="hidden" name="tab" value="treasury">
                <input type="hidden" name="sub" value="operations">
                <input type="hidden" name="month" value="<?= esc($selectedMonth ?? date('Y-m')) ?>">
                <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm">
                    <i class="bi bi-check-all me-1"></i>Konfirmasi Semua (<?= $unmappedCount ?> Transaksi)
                </button>
            </form>
        </div>
    <?php endif; ?>
</details>
<?php endforeach; ?>
</div></div>
