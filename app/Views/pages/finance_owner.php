<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <form class="d-flex flex-column flex-sm-row gap-2" method="get" action="<?= site_url('finance/owner') ?>">
        <?php if ($ownerGuesthouseLocked): ?>
            <input type="hidden" name="guesthouse" value="<?= (int) $guesthouse ?>">
            <div class="form-control bg-body-secondary" aria-label="Guesthouse yang diakses">GH <?= (int) $guesthouse ?></div>
        <?php else: ?>
            <select class="form-select" name="guesthouse" aria-label="Pilih Guesthouse">
                <?php for ($gh = 1; $gh <= 5; $gh++): ?>
                    <option value="<?= $gh ?>" <?= $guesthouse === $gh ? 'selected' : '' ?>>GH <?= $gh ?></option>
                <?php endfor; ?>
            </select>
        <?php endif; ?>
        <input class="form-control" type="month" name="month" value="<?= esc($month) ?>" aria-label="Pilih bulan">
        <button class="btn btn-primary text-nowrap">Tampilkan</button>
    </form>
</div>

<?php $ownerMoney = static fn ($amount): string => 'Rp' . number_format((float) $amount, 0, ',', '.'); ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-primary text-white text-center py-3">
        <h3 class="h5 mb-1">Laba/Rugi GH <?= (int) $guesthouse ?></h3>
        <p class="small mb-0">Periode <?= esc(date('F Y', strtotime($month . '-01'))) ?></p>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <tbody>
                <tr class="table-warning"><th colspan="2">Pendapatan</th></tr>
                <?php foreach ($summary['incomeCategories'] as $category => $amount): ?>
                    <tr><td class="ps-4"><?= esc($category) ?><?= $category === 'Charge Tamu' ? ' <span class="text-secondary small">(Early Check In + Late Checkout)</span>' : '' ?></td><td class="text-end"><?= $ownerMoney($amount) ?></td></tr>
                <?php endforeach; ?>
                <tr class="table-info"><th>Total Pendapatan</th><th class="text-end"><?= $ownerMoney($summary['totalIncome']) ?></th></tr>
                <tr class="table-warning"><th colspan="2">Pengeluaran Operasional</th></tr>
                <?php if ($summary['expenseCategories'] === []): ?>
                    <tr><td class="ps-4 text-secondary">Belum ada pengeluaran</td><td class="text-end">Rp0</td></tr>
                <?php endif; ?>
                <?php foreach ($summary['expenseCategories'] as $category => $amount): ?>
                    <tr><td class="ps-4"><?= esc($category) ?></td><td class="text-end"><?= $ownerMoney($amount) ?></td></tr>
                <?php endforeach; ?>
                <tr class="table-info"><th>Total Pengeluaran Operasional</th><th class="text-end"><?= $ownerMoney($summary['totalExpense']) ?></th></tr>
                <tr class="table-info"><th>Laba/Rugi Bersih</th><th class="text-end <?= $summary['netProfit'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $ownerMoney($summary['netProfit']) ?></th></tr>
                <tr class="table-primary"><th>Fee Kerja Sama Pengelola <span class="fw-normal">(<?= number_format((float) $summary['managerFeePercentage'], 2, ',', '.') ?>%)</span></th><th class="text-end"><?= $ownerMoney($summary['managerShare']) ?></th></tr>
                <tr class="table-primary"><th>Bagi Hasil <?= $summary['ownerName'] === null ? 'Owner/Investor' : esc($summary['ownerName']) ?> <span class="fw-normal">(<?= number_format((float) $summary['ownerSharePercentage'], 2, ',', '.') ?>%)</span></th><th class="text-end"><?= $ownerMoney($summary['investorShare']) ?></th></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h3 class="h5 mb-1">Pengeluaran per Kategori</h3>
            <p class="text-secondary small mb-0">Distribusi pengeluaran <?= esc(date('F Y', strtotime($month . '-01'))) ?> · GH <?= (int) $guesthouse ?>.</p>
        </div>
        <?php if ($summary['expenseCategories'] === []): ?>
            <div class="text-center text-secondary py-5">Belum ada pengeluaran pada bulan ini.</div>
        <?php else: ?>
            <div class="row align-items-center g-4">
                <div class="col-lg-5">
                    <div class="ops-category-chart-wrap">
                        <canvas id="ownerExpenseCategoryChart" data-labels='<?= esc(json_encode(array_keys($summary['expenseCategories'])), 'attr') ?>' data-values='<?= esc(json_encode(array_values($summary['expenseCategories'])), 'attr') ?>'></canvas>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="ops-category-list">
                        <?php foreach ($summary['expenseCategories'] as $index => $categoryAmount): ?>
                            <?php $categoryPercent = $summary['totalExpense'] > 0 ? ($categoryAmount / $summary['totalExpense']) * 100 : 0; ?>
                            <div class="ops-category-item">
                                <span class="ops-category-dot" data-owner-category-color="<?= $index ?>"></span>
                                <div class="min-width-0 flex-grow-1">
                                    <div class="fw-semibold text-truncate"><?= esc((string) $index) ?></div>
                                    <small class="text-secondary"><?= $ownerMoney($categoryAmount) ?></small>
                                </div>
                                <strong><?= number_format($categoryPercent, 1, ',', '.') ?>%</strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
        <div>
            <h3 class="h5 mb-1">Transaksi GH <?= (int) $guesthouse ?></h3>
            <p class="text-secondary small mb-0">Periode <?= esc(date('F Y', strtotime($month . '-01'))) ?></p>
        </div>
        <a class="btn btn-success" href="<?= site_url('finance/owner/export?' . http_build_query(['guesthouse' => $guesthouse, 'month' => $month])) ?>">Export Excel</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th>Arus</th><th class="text-end">Total Nominal</th><th>Bukti</th></tr>
            </thead>
            <tbody>
                <?php if ($transactions === []): ?>
                    <tr><td colspan="6" class="text-center text-secondary py-5">Belum ada transaksi untuk GH dan bulan yang dipilih.</td></tr>
                <?php endif; ?>
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?= esc($transaction['date']) ?></td>
                        <td><?= esc($transaction['category']) ?></td>
                        <td><?= esc($transaction['description']) ?></td>
                        <td><span class="badge <?= $transaction['flow'] === 'Pemasukan' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= esc($transaction['flow']) ?></span></td>
                        <td class="text-end fw-semibold <?= $transaction['flow'] === 'Pemasukan' ? 'text-success' : 'text-danger' ?>">Rp<?= number_format((float) $transaction['amount'], 0, ',', '.') ?></td>
                        <td><?php if ($transaction['proofUrl'] !== null): ?><a class="btn btn-sm btn-outline-primary text-nowrap" href="<?= esc($transaction['proofUrl'], 'attr') ?>" target="_blank" rel="noopener">Lihat bukti</a><?php else: ?><span class="text-secondary">—</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagination['totalItems'] > 0): ?>
        <div class="card-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 py-3">
            <small class="text-secondary">Menampilkan <?= (($pagination['page'] - 1) * $pagination['perPage']) + 1 ?>–<?= min($pagination['page'] * $pagination['perPage'], $pagination['totalItems']) ?> dari <?= $pagination['totalItems'] ?> transaksi</small>
            <?php if ($pagination['totalPages'] > 1): ?>
                <?php $pageUrl = static fn (int $page): string => site_url('finance/owner?' . http_build_query(['guesthouse' => $guesthouse, 'month' => $month, 'page' => $page])); ?>
                <nav aria-label="Halaman transaksi owner"><ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $pagination['page'] === 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= esc($pageUrl($pagination['page'] - 1), 'attr') ?>">Sebelumnya</a></li>
                    <?php for ($pageNumber = 1; $pageNumber <= $pagination['totalPages']; $pageNumber++): ?>
                        <li class="page-item <?= $pageNumber === $pagination['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= esc($pageUrl($pageNumber), 'attr') ?>"><?= $pageNumber ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pagination['page'] === $pagination['totalPages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= esc($pageUrl($pagination['page'] + 1), 'attr') ?>">Berikutnya</a></li>
                </ul></nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
