<?php
use App\Libraries\CashAmount;
?>
<div class="row g-3">
    <!-- Chart.js 6-Month Trend Card -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-graph-up text-primary me-2"></i>Tren Arus Kas &amp; Hak Holding (6 Bulan)
                    </h6>
                    <small class="text-secondary">Riwayat kas masuk, kas keluar, arus kas bersih, dan hak holding berdasarkan pencatatan ledger riil.</small>
                </div>
                <div>
                    <span class="badge bg-light text-secondary border">Basis Kas Riil</span>
                </div>
            </div>
            <div class="card-body p-3">
                <div style="height: 300px; position: relative;">
                    <canvas id="companyTrendChart"
                            data-labels="<?= esc(json_encode($trend['labels'] ?? [])) ?>"
                            data-cash-in="<?= esc(json_encode($trend['cash_in'] ?? [])) ?>"
                            data-cash-out="<?= esc(json_encode($trend['cash_out'] ?? [])) ?>"
                            data-net-cash="<?= esc(json_encode($trend['net_cash'] ?? [])) ?>"
                            data-company-result="<?= esc(json_encode($trend['company_result'] ?? [])) ?>"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- MoM Analysis Card -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-arrow-left-right text-primary me-2"></i>Perbandingan MoM
                </h6>
                <small class="text-secondary">
                    <?= esc($trend['labels'][4] ?? 'Bulan Lalu') ?> vs <?= esc($trend['labels'][5] ?? 'Bulan Ini') ?>
                </small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="extra-small text-secondary">
                                <th class="ps-3">Metrik</th>
                                <th class="text-end">Bulan Ini</th>
                                <th class="text-end pe-3">Perubahan</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <!-- Kas Masuk -->
                            <?php $inMom = $trend['mom']['cash_in'] ?? []; ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">Kas Masuk</div>
                                    <div class="extra-small text-muted">Inflow operasional</div>
                                </td>
                                <td class="text-end holding-num fw-bold"><?= CashAmount::rpPrecise($inMom['current'] ?? 0) ?></td>
                                <td class="text-end pe-3 holding-num">
                                    <?php if (($inMom['pct'] ?? 0) >= 0): ?>
                                        <span class="text-success fw-semibold"><i class="bi bi-caret-up-fill me-1"></i>+<?= esc($inMom['pct']) ?>%</span>
                                    <?php else: ?>
                                        <span class="text-danger fw-semibold"><i class="bi bi-caret-down-fill me-1"></i><?= esc($inMom['pct']) ?>%</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Kas Keluar -->
                            <?php $outMom = $trend['mom']['cash_out'] ?? []; ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">Kas Keluar</div>
                                    <div class="extra-small text-muted">Outflow &amp; beban</div>
                                </td>
                                <td class="text-end holding-num fw-bold"><?= CashAmount::rpPrecise($outMom['current'] ?? 0) ?></td>
                                <td class="text-end pe-3 holding-num">
                                    <?php if (($outMom['pct'] ?? 0) > 0): ?>
                                        <span class="text-danger fw-semibold"><i class="bi bi-caret-up-fill me-1"></i>+<?= esc($outMom['pct']) ?>%</span>
                                    <?php else: ?>
                                        <span class="text-success fw-semibold"><i class="bi bi-caret-down-fill me-1"></i><?= esc($outMom['pct']) ?>%</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Arus Kas Bersih -->
                            <?php $netMom = $trend['mom']['net_cash'] ?? []; ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">Arus Kas Bersih</div>
                                    <div class="extra-small text-muted">Net cash flow</div>
                                </td>
                                <td class="text-end holding-num fw-bold"><?= CashAmount::rpPrecise($netMom['current'] ?? 0) ?></td>
                                <td class="text-end pe-3 holding-num">
                                    <?php if (($netMom['pct'] ?? 0) >= 0): ?>
                                        <span class="text-success fw-semibold"><i class="bi bi-caret-up-fill me-1"></i>+<?= esc($netMom['pct']) ?>%</span>
                                    <?php else: ?>
                                        <span class="text-danger fw-semibold"><i class="bi bi-caret-down-fill me-1"></i><?= esc($netMom['pct']) ?>%</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Hak Bersih Holding -->
                            <?php $compMom = $trend['mom']['company_result'] ?? []; ?>
                            <tr class="bg-light">
                                <td class="ps-3">
                                    <div class="fw-semibold text-primary">Hak Bersih Holding</div>
                                    <div class="extra-small text-muted">Net entitlement</div>
                                </td>
                                <td class="text-end holding-num fw-bold text-primary"><?= CashAmount::rpPrecise($compMom['current'] ?? 0) ?></td>
                                <td class="text-end pe-3 holding-num">
                                    <?php if (($compMom['pct'] ?? 0) >= 0): ?>
                                        <span class="text-success fw-semibold"><i class="bi bi-caret-up-fill me-1"></i>+<?= esc($compMom['pct']) ?>%</span>
                                    <?php else: ?>
                                        <span class="text-danger fw-semibold"><i class="bi bi-caret-down-fill me-1"></i><?= esc($compMom['pct']) ?>%</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-top py-2 px-3">
                <small class="text-muted extra-small">
                    <i class="bi bi-info-circle me-1"></i>Selisih dihitung terhadap periode <?= esc($trend['labels'][4] ?? 'bulan sebelumnya') ?>.
                </small>
            </div>
        </div>
    </div>

    <!-- Ringkasan Kontribusi Unit Properti Bulan Ini -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-buildings text-primary me-2"></i>Kontribusi Operasional per Unit Properti (<?= esc($selectedMonth) ?>)
                    </h6>
                    <small class="text-secondary">Pemisahan gross kas masuk, beban operasional unit, serta pembagian hak holding dan owner berdasarkan kontrak.</small>
                </div>
                <div>
                    <a href="<?= site_url('finance/company') ?>?company=<?= (int) $company['id'] ?>&tab=monthly&month=<?= esc($selectedMonth) ?><?= $selectedProperty ? '&property_id=' . (int) $selectedProperty : '' ?>"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-receipt me-1"></i>Lihat Jembatan Rekonsiliasi Detail
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="holding-table-wrap">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="extra-small text-secondary">
                                <th class="ps-3">Unit Properti</th>
                                <th>Model Kontrak</th>
                                <th class="text-end">Kas Masuk (Gross)</th>
                                <th class="text-end">Beban Langsung</th>
                                <th class="text-end">Kas Bersih Unit</th>
                                <th class="text-end">Hak Holding</th>
                                <th class="text-end">Hak Owner</th>
                                <th class="text-end pe-3">Cadangan Ditahan</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if (empty($monthlyReport['properties'])): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        Belum ada data kontribusi unit atau properti terdaftar untuk periode ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($monthlyReport['properties'] as $prop): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-semibold text-dark"><?= esc($prop['property_name']) ?></div>
                                            <div class="extra-small text-muted"><code><?= esc($prop['property_code']) ?></code></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-primary border border-primary-subtle fw-medium">
                                                <?= esc($prop['agreement_note'] ?? 'Belum Diatur') ?>
                                            </span>
                                            <?php if (! empty($prop['agreement_breakdown'])): ?>
                                                <div class="text-secondary mt-1" style="font-size: 0.68rem; line-height: 1.25;">
                                                    <i class="bi bi-diagram-3 me-1 text-muted"></i><?= esc($prop['agreement_breakdown']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end holding-num"><?= CashAmount::rpPrecise($prop['confirmed_gross_cash']) ?></td>
                                        <td class="text-end holding-num text-danger"><?= CashAmount::rpPrecise($prop['paid_expenses']) ?></td>
                                        <td class="text-end holding-num fw-semibold"><?= CashAmount::rpPrecise($prop['cash_surplus']) ?></td>
                                        <td class="text-end holding-num fw-bold text-primary"><?= CashAmount::rpPrecise($prop['total_company_entitlement']) ?></td>
                                        <td class="text-end holding-num text-secondary"><?= CashAmount::rpPrecise($prop['owner_share']) ?></td>
                                        <td class="text-end pe-3 holding-num text-dark fw-semibold"><?= CashAmount::rpPrecise($prop['net_contribution']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel Audit, Integritas & Penguncian Periode -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-shield-check text-primary me-2"></i>Status Integritas Pencatatan &amp; Penguncian Periode
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded border">
                            <div class="extra-small text-muted text-uppercase mb-1">Transaksi Terakhir</div>
                            <div class="small fw-semibold holding-num text-dark">
                                <?= esc($auditSummary['last_transaction_at'] ?? 'Belum ada transaksi') ?> UTC
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded border">
                            <div class="extra-small text-muted text-uppercase mb-1">Audit Terakhir</div>
                            <div class="small fw-semibold holding-num text-dark">
                                <?= esc($auditSummary['last_audit_at'] ?? 'Belum ada log audit') ?> UTC
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded border">
                            <div class="extra-small text-muted text-uppercase mb-1">Penerimaan PMS Pending</div>
                            <div class="small fw-semibold text-dark">
                                <?php if (($auditSummary['unconfirmed_payments_count'] ?? 0) > 0): ?>
                                    <span class="text-warning fw-bold">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i><?= (int) $auditSummary['unconfirmed_payments_count'] ?> transaksi
                                    </span>
                                <?php else: ?>
                                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Semua terkonfirmasi</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded border">
                            <div class="extra-small text-muted text-uppercase mb-1">Periode Terkunci</div>
                            <div class="small fw-semibold text-dark">
                                <?php if (! empty($auditSummary['closed_periods'])): ?>
                                    <span class="badge bg-secondary">
                                        <?= count($auditSummary['closed_periods']) ?> bulan terkunci
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-secondary border">Belum ada periode terkunci</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
