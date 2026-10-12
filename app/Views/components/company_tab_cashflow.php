<?php
use App\Libraries\CashAmount;
?>
<div class="row g-3">
    <!-- Rekap Saldo Kas/Bank Per Rekening -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-wallet2 text-primary me-2"></i>Saldo Rekening Kas &amp; Bank
                    </h6>
                    <small class="text-secondary">Posisi kas terverifikasi per cutover dan mutasi ledger hingga saat ini (As-Of).</small>
                </div>
                <div>
                    <span class="badge bg-light text-secondary border"><?= count($accounts) > 1 ? count($accounts) . ' Rekening' : 'Kas Konsolidasi' ?></span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="extra-small text-secondary">
                                <th class="ps-3">Kode / Rekening</th>
                                <th>Jenis</th>
                                <th>Cutover UTC</th>
                                <th class="text-end pe-3">Saldo Ledger</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if (empty($accounts)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">
                                        Belum ada rekening kas/bank terdaftar.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $totalBal = 0; ?>
                                <?php foreach ($accounts as $acc): ?>
                                    <?php $bal = $acc['balance'] !== null ? (float) $acc['balance'] : null; ?>
                                    <?php if ($bal !== null) $totalBal += $bal; ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-semibold text-dark"><?= esc($acc['name']) ?></div>
                                            <div class="extra-small text-muted"><code><?= esc($acc['code']) ?></code> &bull; <?= esc($acc['currency']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border">
                                                <?= esc(strtoupper($acc['account_type'] ?? 'bank')) ?>
                                            </span>
                                        </td>
                                        <td class="holding-num text-muted extra-small">
                                            <?= esc($acc['cutover_at'] ?? 'Belum cutover') ?>
                                        </td>
                                        <td class="text-end pe-3 holding-num fw-bold">
                                            <?= $bal !== null ? CashAmount::rp($bal) : '<span class="text-muted fw-normal">Belum cutover</span>' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-light fw-bold">
                                    <td colspan="3" class="ps-3 text-dark">TOTAL SALDO TERVERIFIKASI</td>
                                    <td class="text-end pe-3 holding-num text-primary"><?= CashAmount::rp($totalBal) ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Laporan Arus Kas Format IAS 7 -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-file-earmark-spreadsheet text-primary me-2"></i>Laporan Arus Kas Eksternal (IAS 7)
                </h6>
                <small class="text-secondary">Arus kas masuk dan keluar bulan <?= esc($selectedMonth) ?> diklasifikasikan menurut standar IAS 7.</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="extra-small text-secondary">
                                <th class="ps-3">Aktivitas Arus Kas</th>
                                <th class="text-end">Kas Masuk</th>
                                <th class="text-end">Kas Keluar</th>
                                <th class="text-end pe-3">Arus Kas Bersih</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php
                            $flowsByKey = [];
                            foreach ($externalFlow as $ef) {
                                $flowsByKey[$ef['classification']] = $ef;
                            }
                            $categories = [
                                'operating' => ['title' => 'Aktivitas Operasi (Operating)', 'sub' => 'Penerimaan tamu & beban operasional'],
                                'investing' => ['title' => 'Aktivitas Investasi (Investing)', 'sub' => 'Perolehan aset tetap & peralatan'],
                                'financing' => ['title' => 'Aktivitas Pendanaan (Financing)', 'sub' => 'Modal, dividen, & pinjaman'],
                            ];
                            $totIn = 0; $totOut = 0; $totNet = 0;
                            ?>
                            <?php foreach ($categories as $catKey => $catInfo): ?>
                                <?php
                                $cIn = (float) ($flowsByKey[$catKey]['cash_in'] ?? 0);
                                $cOut = (float) ($flowsByKey[$catKey]['cash_out'] ?? 0);
                                $cNet = (float) ($flowsByKey[$catKey]['net'] ?? 0);
                                $totIn += $cIn; $totOut += $cOut; $totNet += $cNet;
                                ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark"><?= esc($catInfo['title']) ?></div>
                                        <div class="extra-small text-muted"><?= esc($catInfo['sub']) ?></div>
                                    </td>
                                    <td class="text-end holding-num"><?= CashAmount::rp($cIn) ?></td>
                                    <td class="text-end holding-num text-danger"><?= CashAmount::rp($cOut) ?></td>
                                    <td class="text-end pe-3 holding-num fw-semibold <?= $cNet >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= CashAmount::rp($cNet) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td class="ps-3 text-dark">KENAIKAN / (PENURUNAN) KAS BERSIH</td>
                                <td class="text-end holding-num"><?= CashAmount::rp($totIn) ?></td>
                                <td class="text-end holding-num text-danger"><?= CashAmount::rp($totOut) ?></td>
                                <td class="text-end pe-3 holding-num text-primary"><?= CashAmount::rp($totNet) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Mutasi Ledger Kas Riil (Server-Side Paginated) -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-card-list text-primary me-2"></i>Buku Mutasi Ledger Kas Riil
                        </h6>
                        <small class="text-secondary">Daftar mutasi kas yang telah terverifikasi dan diposting ke rekening ledger.</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (! empty($canApprove)): ?>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalManualCashflow">
                                <i class="bi bi-plus-circle me-1"></i>Catat Mutasi Kas Holding
                            </button>
                        <?php endif; ?>
                        <span class="badge bg-light text-secondary border">
                            Total: <?= (int) ($transactionsData['total'] ?? 0) ?> Transaksi
                        </span>
                    </div>
                </div>

                <!-- Filter Controls Form -->
                <form method="get" action="<?= site_url('finance/company') ?>" class="row g-2 align-items-end">
                    <input type="hidden" name="company" value="<?= (int) $company['id'] ?>">
                    <input type="hidden" name="tab" value="cashflow">
                    <input type="hidden" name="month" value="<?= esc($selectedMonth) ?>">
                    <?php if ($selectedProperty): ?>
                        <input type="hidden" name="property_id" value="<?= (int) $selectedProperty ?>">
                    <?php endif; ?>

                    <div class="col-md-3 col-sm-6">
                        <label for="filter-account" class="form-label extra-small text-muted mb-1">Rekening</label>
                        <select id="filter-account" name="ledger_account" class="form-select form-select-sm">
                            <option value="">Semua Rekening</option>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= (int) $acc['id'] ?>" <?= ((int) ($ledgerFilters['account_id'] ?? 0) === (int) $acc['id']) ? 'selected' : '' ?>>
                                    <?= esc($acc['code']) ?> — <?= esc($acc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-6">
                        <label for="filter-kind" class="form-label extra-small text-muted mb-1">Jenis Mutasi</label>
                        <select id="filter-kind" name="ledger_kind" class="form-select form-select-sm">
                            <option value="">Semua Jenis</option>
                            <option value="receipt" <?= ($ledgerFilters['kind'] ?? '') === 'receipt' ? 'selected' : '' ?>>Penerimaan (Receipt)</option>
                            <option value="disbursement" <?= ($ledgerFilters['kind'] ?? '') === 'disbursement' ? 'selected' : '' ?>>Pengeluaran (Disbursement)</option>
                            <option value="opening" <?= ($ledgerFilters['kind'] ?? '') === 'opening' ? 'selected' : '' ?>>Saldo Awal (Opening)</option>
                            <option value="reversal" <?= ($ledgerFilters['kind'] ?? '') === 'reversal' ? 'selected' : '' ?>>Reversal / Pembalik</option>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-6">
                        <label for="filter-classification" class="form-label extra-small text-muted mb-1">Klasifikasi IAS 7</label>
                        <select id="filter-classification" name="ledger_classification" class="form-select form-select-sm">
                            <option value="">Semua Klasifikasi</option>
                            <option value="operating" <?= ($ledgerFilters['classification'] ?? '') === 'operating' ? 'selected' : '' ?>>Operating</option>
                            <option value="investing" <?= ($ledgerFilters['classification'] ?? '') === 'investing' ? 'selected' : '' ?>>Investing</option>
                            <option value="financing" <?= ($ledgerFilters['classification'] ?? '') === 'financing' ? 'selected' : '' ?>>Financing</option>
                        </select>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <label for="filter-search" class="form-label extra-small text-muted mb-1">Pencarian</label>
                        <input id="filter-search" name="ledger_search" type="text" class="form-control form-control-sm"
                               placeholder="Cari deskripsi, referensi, key..."
                               value="<?= esc($ledgerFilters['search'] ?? '') ?>">
                    </div>

                    <div class="col-md-2 col-sm-12 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                            <i class="bi bi-filter me-1"></i>Filter
                        </button>
                        <a href="<?= site_url('finance/company') ?>?company=<?= (int) $company['id'] ?>&tab=cashflow&month=<?= esc($selectedMonth) ?><?= $selectedProperty ? '&property_id=' . (int) $selectedProperty : '' ?>"
                           class="btn btn-outline-secondary btn-sm" title="Reset filter">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Ledger Table -->
            <div class="card-body p-0">
                <div class="holding-table-wrap">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="extra-small text-secondary">
                                <th class="ps-3">Tanggal (UTC)</th>
                                <th>No.</th>
                                <th>Keterangan Transaksi</th>
                                <th>Properti</th>
                                <th>Klasifikasi</th>
                                <th>Jenis</th>
                                <th>Rekening Terlibat</th>
                                <th class="text-end">Kas Masuk</th>
                                <th class="text-end">Kas Keluar</th>
                                <th class="pe-3">Oleh</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if (empty($transactionsData['items'])): ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        Tidak ada mutasi ledger yang sesuai dengan filter yang dipilih.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactionsData['items'] as $tx): ?>
                                    <tr>
                                        <td class="ps-3 holding-num extra-small text-secondary" style="white-space: nowrap;">
                                            <?= esc($tx['effective_at']) ?>
                                        </td>
                                        <td class="holding-num extra-small text-muted">
                                            #<?= (int) $tx['id'] ?>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= esc($tx['description']) ?></div>
                                            <div class="extra-small text-muted">Ref: <code><?= esc($tx['event_key']) ?></code></div>
                                        </td>
                                        <td>
                                            <?php if (! empty($tx['property_name'])): ?>
                                                <span class="badge bg-light text-secondary border">
                                                    <?= esc($tx['property_code']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">Holding</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border">
                                                <?= esc(ucfirst($tx['classification'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= esc(strtoupper($tx['kind'])) ?>
                                            </span>
                                        </td>
                                        <td class="extra-small">
                                            <?php foreach ($tx['entries'] as $entry): ?>
                                                <div>
                                                    <span class="text-secondary"><?= esc($entry['account_code']) ?>:</span>
                                                    <span class="holding-num <?= ((float) $entry['amount'] > 0) ? 'text-success' : 'text-danger' ?>">
                                                        <?= ((float) $entry['amount'] > 0 ? '+' : '') . CashAmount::rp($entry['amount'], false) ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>
                                        <td class="text-end holding-num text-success fw-semibold">
                                            <?= ((float) $tx['cash_in'] > 0) ? CashAmount::rp($tx['cash_in']) : '-' ?>
                                        </td>
                                        <td class="text-end holding-num text-danger fw-semibold">
                                            <?= ((float) $tx['cash_out'] > 0) ? CashAmount::rp($tx['cash_out']) : '-' ?>
                                        </td>
                                        <td class="pe-3 extra-small text-muted">
                                            <?= esc($tx['posted_by_username']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Server-Side Pagination Footer -->
            <?php if (! empty($transactionsData['total']) && $transactionsData['total'] > 0): ?>
                <?php
                $curPage = (int) ($transactionsData['page'] ?? 1);
                $totPages = (int) ($transactionsData['totalPages'] ?? 1);
                $perPage = (int) ($transactionsData['perPage'] ?? 10);
                $total = (int) ($transactionsData['total'] ?? 0);
                $fromItem = min($total, ($curPage - 1) * $perPage + 1);
                $toItem = min($total, $curPage * $perPage);
                $buildPageUrl = static function (int $p) use ($company, $selectedMonth, $selectedProperty, $ledgerFilters, $perPage): string {
                    $params = [
                        'company' => $company['id'],
                        'tab' => 'cashflow',
                        'month' => $selectedMonth,
                        'ledger_page' => $p,
                    ];
                    if ($perPage !== 10) $params['ledger_per_page'] = $perPage;
                    if ($selectedProperty) $params['property_id'] = $selectedProperty;
                    if (! empty($ledgerFilters['account_id'])) $params['ledger_account'] = $ledgerFilters['account_id'];
                    if (! empty($ledgerFilters['kind'])) $params['ledger_kind'] = $ledgerFilters['kind'];
                    if (! empty($ledgerFilters['classification'])) $params['ledger_classification'] = $ledgerFilters['classification'];
                    if (! empty($ledgerFilters['search'])) $params['ledger_search'] = $ledgerFilters['search'];
                    return site_url('finance/company') . '?' . http_build_query($params);
                };
                ?>
                <div class="card-footer bg-white border-top py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="extra-small text-muted">
                        Menampilkan <strong><?= $fromItem ?>–<?= $toItem ?></strong> dari <strong><?= $total ?></strong> transaksi (Halaman <?= $curPage ?> dari <?= $totPages ?>)
                    </div>
                    <?php if ($totPages > 1): ?>
                    <nav aria-label="Navigasi halaman mutasi kas"
                         hx-boost="true" hx-target="#company-finance-root" hx-select="#company-finance-root"
                         hx-swap="outerHTML show:none" hx-indicator="#company-tab-loading">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Prev -->
                            <li class="page-item <?= $curPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $curPage > 1 ? esc($buildPageUrl($curPage - 1)) : '#' ?>">Sebelumnya</a>
                            </li>

                            <!-- Page Numbers (max 5 around current) -->
                            <?php
                            $startP = max(1, $curPage - 2);
                            $endP = min($totPages, $curPage + 2);
                            ?>
                            <?php for ($p = $startP; $p <= $endP; $p++): ?>
                                <li class="page-item <?= $p === $curPage ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= esc($buildPageUrl($p)) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next -->
                            <li class="page-item <?= $curPage >= $totPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $curPage < $totPages ? esc($buildPageUrl($curPage + 1)) : '#' ?>">Berikutnya</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (! empty($canApprove)): ?>
<!-- Modal Catat Mutasi Kas Holding (Investasi, Pendanaan, Transfer) -->
<div class="modal fade" id="modalManualCashflow" tabindex="-1" aria-labelledby="modalManualCashflowLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 1000px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom py-3 px-4">
                <div>
                    <h5 class="modal-title fw-bold text-dark fs-5 mb-1" id="modalManualCashflowLabel">
                        <i class="bi bi-journal-plus text-primary me-2"></i>Catat Mutasi Kas Holding
                    </h5>
                    <p class="text-muted mb-0 small">Pencatatan kas tingkat korporat/holding (Aktivitas Investasi dan Pendanaan).</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form method="post" action="<?= site_url('finance/company/cash') ?>" id="formManualCashflow">
                <?= csrf_field() ?>
                <input type="hidden" name="company" value="<?= (int) $company['id'] ?>">
                <input type="hidden" name="tab" value="cashflow">
                <input type="hidden" name="month" value="<?= esc($selectedMonth) ?>">
                <input type="hidden" name="confirmed" value="1">
                <input type="hidden" name="event_key" id="manualEventKey" value="">

                <div class="modal-body p-4">
                    <!-- Penjelasan Isolasi Owner -->
                    <div class="alert alert-info border-0 p-3 mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-shield-check text-info fs-3 flex-shrink-0"></i>
                            <div>
                                <strong class="d-block text-dark">Kas Murni Holding Perusahaan</strong>
                                <span class="text-secondary small">Transaksi ini <strong>tidak mempengaruhi</strong> omzet kamar, pengeluaran unit, maupun hak bagi hasil Owner Guest House.</span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Jenis Transaksi -->
                        <div class="col-md-6">
                            <label for="manualOperation" class="form-label fw-semibold">Jenis Transaksi Kas</label>
                            <select class="form-select" name="operation" id="manualOperation" required>
                                <optgroup label="Aktivitas Investasi (Investing)">
                                    <option value="asset_purchase" selected>Pembelian Aset Tetap / Renovasi (Kas Keluar)</option>
                                </optgroup>
                                <optgroup label="Aktivitas Pendanaan (Financing)">
                                    <option value="capital">Setoran Modal Pemilik / Tambahan Modal (Kas Masuk)</option>
                                    <option value="loan_received">Penerimaan Pinjaman Bank / Kredit (Kas Masuk)</option>
                                    <option value="loan_principal">Pembayaran Pokok Pinjaman Bank (Kas Keluar)</option>
                                </optgroup>
                                <optgroup label="Aktivitas Operasi (Operating)">
                                    <option value="management_salary">Gaji Manajemen (Kas Keluar)</option>
                                    <option value="bank_fee">Biaya Administrasi Bank / Pajak Bunga (Kas Keluar)</option>
                                </optgroup>
                            </select>
                            <div id="operationHelp" class="form-text small text-muted mt-1">
                                Akan dicatat pada <strong>Aktivitas Investasi (IAS 7)</strong>.
                            </div>
                        </div>

                        <!-- Rekening Kas/Bank Sumber -->
                        <div class="col-md-6">
                            <label for="manualAccount" class="form-label fw-semibold">Rekening Kas / Bank</label>
                            <select class="form-select" name="account_id" id="manualAccount" required>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= (int) $acc['id'] ?>">
                                        <?= esc($acc['code']) ?> — <?= esc($acc['name']) ?> (Saldo: <?= CashAmount::rp($acc['balance'] ?? 0) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Nominal -->
                        <div class="col-md-6">
                            <label for="manualAmount" class="form-label fw-semibold">Nominal Transaksi (Rp)</label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold">Rp</span>
                                <input type="text" class="form-control" name="amount" id="manualAmount" inputmode="numeric" placeholder="Contoh: 15.000.000" required>
                            </div>
                        </div>

                        <!-- Waktu Aktual Kas -->
                        <div class="col-md-6">
                            <label for="manualEffectiveAt" class="form-label fw-semibold">Waktu Kas Riil</label>
                            <input type="datetime-local" class="form-control" name="effective_at" id="manualEffectiveAt" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>

                        <!-- Keterangan / Referensi -->
                        <div class="col-12">
                            <label for="manualNote" class="form-label fw-semibold">Keterangan / Referensi Bukti</label>
                            <textarea class="form-control" name="note" id="manualNote" rows="3" maxlength="500" placeholder="Contoh: Pembelian genset 15 kVA untuk cadangan listrik / Setor modal awal pemilik" required></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-3 px-4 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="btnSubmitManualCashflow">
                        <i class="bi bi-check-circle me-1"></i>Simpan Mutasi Kas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const opSelect = document.getElementById('manualOperation');
    const opHelp = document.getElementById('operationHelp');
    const keyInput = document.getElementById('manualEventKey');
    const modalEl = document.getElementById('modalManualCashflow');

    if (!opSelect) return;

    function generateKey() {
        const arr = new Uint8Array(16);
        window.crypto.getRandomValues(arr);
        return Array.from(arr, b => b.toString(16).padStart(2, '0')).join('');
    }

    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function () {
            keyInput.value = generateKey();
        });
    }

    const helpTexts = {
        'asset_purchase': 'Akan dicatat pada <strong>Aktivitas Investasi (IAS 7)</strong> sebagai arus kas keluar belanja modal/aset.',
        'capital': 'Akan dicatat pada <strong>Aktivitas Pendanaan (IAS 7)</strong> sebagai arus kas masuk permodalan.',
        'loan_received': 'Akan dicatat pada <strong>Aktivitas Pendanaan (IAS 7)</strong> sebagai arus kas masuk pinjaman.',
        'loan_principal': 'Akan dicatat pada <strong>Aktivitas Pendanaan (IAS 7)</strong> sebagai arus kas keluar cicilan hutang.',
        'management_salary': 'Akan dicatat pada <strong>Aktivitas Operasi (IAS 7)</strong> sebagai beban gaji manajemen/operasional holding.',
        'bank_fee': 'Akan dicatat pada <strong>Aktivitas Operasi (IAS 7)</strong> sebagai beban administrasi bank kas holding.'
    };

    function updateFormState() {
        const val = opSelect.value;
        opHelp.innerHTML = helpTexts[val] || '';
    }

    opSelect.addEventListener('change', updateFormState);
    updateFormState();
});
</script>
<?php endif; ?>
