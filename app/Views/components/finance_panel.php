<div class="row g-4 mb-4">
    <?php foreach ([
        ['Revenue', $summary['revenue'], 'text-success', 'Total pembayaran yang sudah diterima pada periode ini.'],
        ['Expenses', $summary['expenses'], 'text-danger', 'Total pengeluaran operasional, termasuk maintenance.'],
        ['Net Profit', $summary['netProfit'], $summary['netProfit'] < 0 ? 'text-danger' : 'text-primary', 'Revenue dikurangi seluruh expenses pada periode ini.'],
        ['Outstanding', $summary['outstanding'], 'text-warning', 'Sisa tagihan reservasi yang belum dibayar oleh tamu.'],
    ] as [$label, $value, $class, $description]): ?>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-secondary"><?= esc($label) ?></small><div class="h3 mt-2 mb-1 <?= $class ?>">Rp<?= number_format((float) $value, 0, ',', '.') ?></div><div class="small text-secondary"><?= esc($description) ?></div></div></div></div>
    <?php endforeach; ?>
</div>
<div class="row g-4 align-items-start">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3"><h3 class="h6 mb-0">Transactions — <?= esc($month) ?></h3><a class="btn btn-sm btn-success" href="<?= site_url('finance/export?month=' . $month) ?>">Export Excel</a></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Date</th><th>Type</th><th>Tipe Pembayaran</th><th>Reference</th><th>Description</th><th>Method/Category</th><th class="text-end">Amount</th></tr></thead><tbody>
            <?php if ($transactions === []): ?><tr><td colspan="7" class="text-center text-secondary py-5">Belum ada transaksi pada periode ini.</td></tr><?php endif; ?>
            <?php foreach ($transactions as $transaction): ?><tr><td><?= esc($transaction['date']) ?></td><td><span class="badge <?= $transaction['type'] === 'Income' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= esc($transaction['type']) ?></span></td><td><?= esc($transaction['payment_type']) ?></td><td><?= esc($transaction['reference']) ?></td><td><?= esc($transaction['description']) ?></td><td><?= esc($transaction['method']) ?></td><td class="text-end fw-semibold <?= $transaction['amount'] < 0 ? 'text-danger' : 'text-success' ?>">Rp<?= number_format((float) $transaction['amount'], 0, ',', '.') ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php if ($pagination['totalItems'] > 0): ?>
                <div class="card-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 py-3">
                    <small class="text-secondary">Menampilkan <?= (($pagination['page'] - 1) * $pagination['perPage']) + 1 ?>–<?= min($pagination['page'] * $pagination['perPage'], $pagination['totalItems']) ?> dari <?= $pagination['totalItems'] ?> transaksi</small>
                    <?php if ($pagination['totalPages'] > 1): ?>
                        <nav aria-label="Halaman transaksi"><ul class="pagination pagination-sm mb-0">
                            <?php $panelUrl = static fn (int $page): string => site_url('finance/panel?month=' . rawurlencode($month) . '&page=' . $page); ?>
                            <li class="page-item <?= $pagination['page'] === 1 ? 'disabled' : '' ?>"><button class="page-link" type="button" <?= $pagination['page'] === 1 ? 'disabled' : '' ?> hx-get="<?= esc($panelUrl($pagination['page'] - 1), 'attr') ?>" hx-target="#finance-panel" hx-swap="innerHTML">Sebelumnya</button></li>
                            <?php for ($pageNumber = 1; $pageNumber <= $pagination['totalPages']; $pageNumber++): ?>
                                <li class="page-item <?= $pageNumber === $pagination['page'] ? 'active' : '' ?>"><button class="page-link" type="button" hx-get="<?= esc($panelUrl($pageNumber), 'attr') ?>" hx-target="#finance-panel" hx-swap="innerHTML"><?= $pageNumber ?></button></li>
                            <?php endfor; ?>
                            <li class="page-item <?= $pagination['page'] === $pagination['totalPages'] ? 'disabled' : '' ?>"><button class="page-link" type="button" <?= $pagination['page'] === $pagination['totalPages'] ? 'disabled' : '' ?> hx-get="<?= esc($panelUrl($pagination['page'] + 1), 'attr') ?>" hx-target="#finance-panel" hx-swap="innerHTML">Berikutnya</button></li>
                        </ul></nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm finance-cashflow-card">
            <div class="card-body p-4">
                <h3 class="h5 mb-1">Cash Flow</h3>
                <p class="text-secondary small mb-3">Pemasukan vs pengeluaran (6 bulan terakhir).</p>
                <div class="finance-cashflow-chart-wrap">
                    <canvas id="financeCashFlowChart" data-labels='<?= esc(json_encode($cashFlow['labels']), 'attr') ?>' data-income='<?= esc(json_encode($cashFlow['income']), 'attr') ?>' data-expenses='<?= esc(json_encode($cashFlow['expenses']), 'attr') ?>'></canvas>
                </div>
            </div>
        </div>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <h3 class="h5 mb-4">Payment Methods</h3>
                <div class="finance-payment-methods">
                    <?php $methodMeta = [
                        'Cash' => ['cash', 'Pembayaran langsung'],
                        'Transfer' => ['transfer', 'Transfer bank'],
                        'Credit Card' => ['credit-card', 'Visa, Mastercard'],
                        'OTA' => ['ota', 'Online Travel Agency'],
                    ]; ?>
                    <?php foreach ($paymentMethods as $paymentMethod): ?>
                        <?php [$class, $description] = $methodMeta[$paymentMethod['name']]; ?>
                        <div class="finance-payment-method">
                            <span class="finance-payment-method-mark <?= esc($class) ?>"></span>
                            <div class="min-width-0 flex-grow-1"><div class="fw-semibold"><?= esc($paymentMethod['name']) ?></div><small class="text-secondary"><?= esc($description) ?> · <?= $paymentMethod['count'] ?> pembayaran</small></div>
                            <strong><?= $paymentMethod['percentage'] ?>%</strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
