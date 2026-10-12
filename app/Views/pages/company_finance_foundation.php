<?php if (! ($partial ?? false)): ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php endif; ?>

<?php
use App\Libraries\CashAmount;

$refreshUrl = site_url('finance/company') . '?' . http_build_query(array_filter([
    'company' => (int) $company['id'],
    'tab' => $activeTab,
    'sub' => $treasurySub ?? null,
    'month' => $selectedMonth,
    'property_id' => $selectedProperty,
    'ledger_page' => $ledgerPage > 1 ? $ledgerPage : null,
    'ledger_account' => $ledgerFilters['account_id'] ?? null,
    'ledger_kind' => $ledgerFilters['kind'] ?? null,
    'ledger_classification' => $ledgerFilters['classification'] ?? null,
    'ledger_search' => $ledgerFilters['search'] ?? null,
]));

$totalVerifiedCash = 0;
foreach ($accounts as $acc) {
    if ($acc['balance'] !== null) {
        $totalVerifiedCash += (float) $acc['balance'];
    }
}
?>

<div id="company-finance-root"
     hx-get="<?= esc($refreshUrl) ?>"
     hx-trigger="refresh from:body"
     hx-select="#company-finance-root"
     hx-target="#company-finance-root"
     hx-swap="outerHTML show:none">

    <!-- Top Entity Header & Actions -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-light text-secondary border d-inline-flex align-items-center gap-1">
                        <span class="live-pulse"></span>
                        Live &bull; Diperbarui <?= (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))->format('H:i:s') ?> WIB
                    </span>
                    <?php if ($reconciliation['is_period_closed'] ?? false): ?>
                        <span class="badge bg-dark text-white d-inline-flex align-items-center gap-1">
                            <i class="bi bi-lock-fill"></i>Periode <?= esc($selectedMonth) ?> Terkunci (Closed)
                        </span>
                    <?php endif; ?>
                    <?php if (isset($snapshot['version'])): ?>
                        <span class="badge bg-secondary-subtle text-secondary border extra-small">
                            Snapshot v<?= (int) $snapshot['version'] ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h2 class="h4 mb-1 fw-bold text-dark"><?= esc($company['name']) ?></h2>
                <p class="text-secondary small mb-0">
                    Konsolidasi arus kas multi-unit, pemisahan hak holding &amp; owner, serta mutasi ledger terverifikasi.
                </p>
            </div>

            <!-- Filter Bar: Periode Bulan & Actions -->
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 pt-3 mt-2 border-top">
                <form method="get" action="<?= site_url('finance/company') ?>" class="d-flex flex-wrap align-items-end gap-2 mb-0">
                    <input type="hidden" name="tab" value="<?= esc($activeTab) ?>">
                    <input type="hidden" name="company" value="<?= (int) $company['id'] ?>">

                    <div style="min-width: 170px;">
                        <label for="holding-month" class="form-label extra-small text-muted mb-1">Periode Laporan</label>
                        <input id="holding-month" name="month" type="month" class="form-control form-control-sm"
                               value="<?= esc($selectedMonth) ?>" onchange="this.form.submit()">
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary btn-sm px-3">
                            <i class="bi bi-filter me-1"></i>Terapkan
                        </button>
                    </div>
                </form>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            onclick="htmx.trigger('#company-finance-root', 'refresh')"
                            title="Perbarui data sekarang">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                    <?php if ($canExport): ?>
                        <a href="<?= site_url('finance/company/export') ?>?company=<?= (int) $company['id'] ?>&month=<?= esc($selectedMonth) ?><?= $selectedProperty ? '&property_id=' . (int) $selectedProperty : '' ?>"
                           class="btn btn-sm btn-success text-white shadow-sm"
                           title="Unduh laporan dalam format SpreadsheetML XML (.xls)">
                            <i class="bi bi-file-earmark-excel me-1"></i>Ekspor .xls
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Banner: Penerimaan PMS Pending -->
    <?php if (($auditSummary['unconfirmed_payments_count'] ?? 0) > 0): ?>
        <div class="alert alert-warning border border-warning-subtle shadow-sm py-2 px-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2 small">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                <div>
                    <strong>Pemberitahuan Integritas Kas:</strong> Terdapat <strong><?= (int) $auditSummary['unconfirmed_payments_count'] ?> penerimaan pembayaran tamu di PMS</strong> yang belum dikonfirmasi ke rekening bank holding.
                </div>
            </div>
            <div>
                <a href="<?= site_url('finance/company') ?>?company=<?= (int) $company['id'] ?>&tab=treasury&sub=operations&month=<?= esc($selectedMonth) ?>"
                   class="btn btn-sm btn-dark">
                    Konfirmasi Sekarang <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- 6 Executive KPI Metric Cards (High-Density, Tabular, Professional) -->
    <div class="row g-2 mb-3">
        <!-- 1. Saldo Kas & Bank As-Of -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="holding-kpi-card">
                <div>
                    <div class="extra-small text-muted text-uppercase fw-semibold mb-1">
                        Kas &amp; Bank (As-Of)
                    </div>
                    <div class="holding-num fw-bold fs-5 text-dark">
                        <?= CashAmount::rp($totalVerifiedCash) ?>
                    </div>
                </div>
                <div class="mt-2 extra-small text-secondary pt-2 border-top">
                    <i class="bi bi-wallet2 me-1"></i>Kas Terverifikasi Sistem
                </div>
            </div>
        </div>

        <!-- 2. Kas Masuk Periode Ini -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="holding-kpi-card">
                <div>
                    <div class="extra-small text-muted text-uppercase fw-semibold mb-1">
                        Kas Masuk Bulan Ini
                    </div>
                    <div class="holding-num fw-bold fs-5 text-dark">
                        <?= CashAmount::rp($monthlyReport['cards']['cash_in_month']) ?>
                    </div>
                </div>
                <div class="mt-2 extra-small pt-2 border-top holding-num">
                    <?php $inPct = $trend['mom']['cash_in']['pct'] ?? 0; ?>
                    <?php if ($inPct >= 0): ?>
                        <span class="text-success fw-semibold"><i class="bi bi-caret-up-fill me-1"></i>+<?= esc($inPct) ?>% MoM</span>
                    <?php else: ?>
                        <span class="text-danger fw-semibold"><i class="bi bi-caret-down-fill me-1"></i><?= esc($inPct) ?>% MoM</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 3. Kas Keluar Periode Ini -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="holding-kpi-card">
                <div>
                    <div class="extra-small text-muted text-uppercase fw-semibold mb-1">
                        Kas Keluar Bulan Ini
                    </div>
                    <div class="holding-num fw-bold fs-5 text-dark">
                        <?= CashAmount::rp($monthlyReport['cards']['cash_out_month']) ?>
                    </div>
                </div>
                <div class="mt-2 extra-small pt-2 border-top holding-num">
                    <?php $outPct = $trend['mom']['cash_out']['pct'] ?? 0; ?>
                    <?php if ($outPct > 0): ?>
                        <span class="text-danger fw-semibold"><i class="bi bi-caret-up-fill me-1"></i>+<?= esc($outPct) ?>% MoM</span>
                    <?php else: ?>
                        <span class="text-success fw-semibold"><i class="bi bi-caret-down-fill me-1"></i><?= esc($outPct) ?>% MoM</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 4. Arus Kas Bersih -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="holding-kpi-card">
                <div>
                    <div class="extra-small text-muted text-uppercase fw-semibold mb-1">
                        Arus Kas Bersih (Net)
                    </div>
                    <div class="holding-num fw-bold fs-5 <?= ((float) $monthlyReport['cards']['net_cash_flow'] >= 0) ? 'text-success' : 'text-danger' ?>">
                        <?= CashAmount::rp($monthlyReport['cards']['net_cash_flow']) ?>
                    </div>
                </div>
                <div class="mt-2 extra-small pt-2 border-top holding-num">
                    <?php $netPct = $trend['mom']['net_cash']['pct'] ?? 0; ?>
                    <?php if ($netPct >= 0): ?>
                        <span class="text-success fw-semibold"><i class="bi bi-caret-up-fill me-1"></i>+<?= esc($netPct) ?>% MoM</span>
                    <?php else: ?>
                        <span class="text-danger fw-semibold"><i class="bi bi-caret-down-fill me-1"></i><?= esc($netPct) ?>% MoM</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 5. Hak Bersih Holding -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="holding-kpi-card" style="background-color: #f8fafc; border-color: #cbd5e1;">
                <div>
                    <div class="extra-small text-primary text-uppercase fw-bold mb-1">
                        Hak Bersih Holding
                    </div>
                    <div class="holding-num fw-bold fs-5 text-primary">
                        <?= CashAmount::rp($monthlyReport['cards']['company_net_result']) ?>
                    </div>
                </div>
                <div class="mt-2 extra-small pt-2 border-top text-secondary">
                    Net Share Pengelola
                </div>
            </div>
        </div>

        <!-- 6. Kewajiban Belum Lunas -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="holding-kpi-card">
                <div>
                    <div class="extra-small text-muted text-uppercase fw-semibold mb-1">
                        Kewajiban Belum Lunas
                    </div>
                    <div class="holding-num fw-bold fs-5 <?= ((float) $monthlyReport['cards']['total_reserves'] > 0) ? 'text-warning-emphasis' : 'text-dark' ?>">
                        <?= CashAmount::rp($monthlyReport['cards']['total_reserves']) ?>
                    </div>
                </div>
                <div class="mt-2 extra-small pt-2 border-top text-secondary">
                    Hak Owner Belum Disetor
                </div>
            </div>
        </div>
    </div>

    <!-- 4 Core Navigation Tabs -->
    <div class="card border-0 shadow-sm mb-3">
        <nav class="card-header bg-white border-0 p-2 d-flex flex-wrap align-items-center justify-content-between position-relative" aria-label="Menu laporan keuangan perusahaan"
             hx-boost="true" hx-target="#company-finance-root" hx-select="#company-finance-root"
             hx-swap="outerHTML show:none" hx-push-url="true" hx-sync="#company-finance-root:replace"
             hx-indicator="#company-tab-loading">
            <ul class="nav nav-tabs company-finance-tabs border-0 flex-grow-1 mb-0">
                <!-- Tab 1: Summary -->
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'summary' ? 'active' : '' ?>"
                       <?= $activeTab === 'summary' ? 'aria-current="page"' : '' ?>
                       href="<?= site_url('finance/company') ?>?company=<?= (int) $company['id'] ?>&tab=summary&month=<?= esc($selectedMonth) ?><?= $selectedProperty ? '&property_id=' . (int) $selectedProperty : '' ?>">
                        <i class="bi bi-speedometer2 me-1"></i>Ringkasan &amp; Tren
                    </a>
                </li>

                <!-- Tab 2: Monthly Report & Entitlements -->
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'monthly' ? 'active' : '' ?>"
                       <?= $activeTab === 'monthly' ? 'aria-current="page"' : '' ?>
                       href="<?= site_url('finance/company') ?>?company=<?= (int) $company['id'] ?>&tab=monthly&month=<?= esc($selectedMonth) ?><?= $selectedProperty ? '&property_id=' . (int) $selectedProperty : '' ?>">
                        <i class="bi bi-graph-up-arrow me-1"></i>Laporan Bulanan &amp; Hak Holding
                    </a>
                </li>

                <!-- Tab 3: Cashflow & Ledger Mutations -->
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'cashflow' ? 'active' : '' ?>"
                       <?= $activeTab === 'cashflow' ? 'aria-current="page"' : '' ?>
                       href="<?= site_url('finance/company') ?>?company=<?= (int) $company['id'] ?>&tab=cashflow&month=<?= esc($selectedMonth) ?><?= $selectedProperty ? '&property_id=' . (int) $selectedProperty : '' ?>">
                        <i class="bi bi-journal-text me-1"></i>Arus Kas &amp; Mutasi Ledger
                    </a>
                </li>

                <!-- Tab 4: Treasury & Reconciliations -->
                <li class="nav-item">
                    <a class="nav-link <?= in_array($activeTab, ['treasury', 'cash', 'agreements', 'overhead'], true) ? 'active' : '' ?>"
                       <?= in_array($activeTab, ['treasury', 'cash', 'agreements', 'overhead'], true) ? 'aria-current="page"' : '' ?>
                       href="<?= site_url('finance/company') ?>?company=<?= (int) $company['id'] ?>&tab=treasury&month=<?= esc($selectedMonth) ?><?= $selectedProperty ? '&property_id=' . (int) $selectedProperty : '' ?>">
                        <i class="bi bi-bank me-1"></i>Rekonsiliasi &amp; Operasi Kas
                    </a>
                </li>
            </ul>
            <div id="company-tab-loading" class="htmx-indicator small text-primary px-2" role="status" aria-live="polite">
                <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                <span class="d-none d-md-inline">Memuat laporan…</span>
            </div>
        </nav>
    </div>

    <!-- Active Tab Component Body -->
    <?php if ($activeTab === 'summary'): ?>
        <?= view('components/company_tab_summary', [
            'company' => $company,
            'properties' => $properties,
            'accounts' => $accounts,
            'monthlyReport' => $monthlyReport,
            'trend' => $trend,
            'auditSummary' => $auditSummary,
            'selectedMonth' => $selectedMonth,
            'selectedProperty' => $selectedProperty,
            'canPost' => $canPost,
            'canApprove' => $canApprove,
        ]) ?>

    <?php elseif ($activeTab === 'monthly'): ?>
        <?= view('components/company_monthly_report', [
            'company' => $company,
            'properties' => $properties,
            'monthlyReport' => $monthlyReport,
            'selectedMonth' => $selectedMonth,
            'canPost' => $canPost,
            'canApprove' => $canApprove,
        ]) ?>

    <?php elseif ($activeTab === 'cashflow'): ?>
        <?= view('components/company_tab_cashflow', [
            'company' => $company,
            'properties' => $properties,
            'accounts' => $accounts,
            'externalFlow' => $externalFlow,
            'transactionsData' => $transactionsData,
            'ledgerFilters' => $ledgerFilters,
            'selectedMonth' => $selectedMonth,
            'selectedProperty' => $selectedProperty,
            'canPost' => $canPost,
            'canApprove' => $canApprove,
        ]) ?>

    <?php elseif (in_array($activeTab, ['treasury', 'cash', 'overhead'], true)): ?>
        <?= view('components/company_tab_treasury', [
            'company' => $company,
            'properties' => $properties,
            'accounts' => $accounts,
            'defaultAccountId' => $defaultAccountId ?? ($accounts[0]['id'] ?? 1),
            'preview' => $preview,
            'allocations' => $allocations,
            'auditSummary' => $auditSummary,
            'selectedMonth' => $selectedMonth,
            'reconciliation' => $reconciliation ?? [],
            'snapshot' => $snapshot ?? null,
            'canPost' => $canPost,
            'canApprove' => $canApprove,
            'canClose' => $canClose,
            'canReconcile' => $canReconcile ?? false,
        ]) ?>
    <?php endif; ?>

</div>

<?php if (! ($partial ?? false)): ?>
<?= $this->endSection() ?>
<?php endif; ?>
