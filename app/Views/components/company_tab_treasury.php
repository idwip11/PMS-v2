<?php
use App\Libraries\CashAmount;

$sub = trim((string) request()->getGet('sub')) ?: 'reconciliation';
if (! in_array($sub, ['reconciliation', 'close_period', 'overhead', 'operations'], true)) {
    $sub = 'reconciliation';
}
$reconciliation = $reconciliation ?? ['accounts' => [], 'total_accounts' => 0, 'reconciled_count' => 0, 'discrepancy_count' => 0, 'pending_count' => 0, 'all_reconciled' => false, 'total_discrepancy' => '0.00', 'is_period_closed' => false];
$snapshot = $snapshot ?? null;
?>

<div class="row g-3">
    <!-- Sub-navigation Pills -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-2 bg-light rounded d-flex flex-wrap gap-2"
                 hx-boost="true" hx-target="#company-finance-root" hx-select="#company-finance-root"
                 hx-swap="outerHTML show:none" hx-push-url="true" hx-sync="#company-finance-root:replace">
                <a href="<?= site_url('finance/company') ?>?company=<?= (int) $company['id'] ?>&tab=treasury&sub=reconciliation&month=<?= esc($selectedMonth) ?>"
                   class="btn btn-sm treasury-sub-btn <?= $sub === 'reconciliation' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-check2-circle me-1"></i>Rekonsiliasi Bank &amp; Kas
                </a>
                <a href="<?= site_url('finance/company') ?>?company=<?= (int) $company['id'] ?>&tab=treasury&sub=close_period&month=<?= esc($selectedMonth) ?>"
                   class="btn btn-sm treasury-sub-btn <?= $sub === 'close_period' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-lock me-1"></i>Tutup Buku &amp; Penguncian Periode
                </a>
                <a href="<?= site_url('finance/company') ?>?company=<?= (int) $company['id'] ?>&tab=treasury&sub=overhead&month=<?= esc($selectedMonth) ?>"
                   class="btn btn-sm treasury-sub-btn <?= $sub === 'overhead' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-pie-chart me-1"></i>Alokasi Biaya Overhead
                </a>
                <a href="<?= site_url('finance/company') ?>?company=<?= (int) $company['id'] ?>&tab=treasury&sub=operations&month=<?= esc($selectedMonth) ?>"
                   class="btn btn-sm treasury-sub-btn <?= $sub === 'operations' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-cash-stack me-1"></i>Integrasi Kas Masuk PMS
                </a>
            </div>
        </div>
    </div>

    <!-- Subview Content -->
    <div class="col-12">
        <?php if ($sub === 'operations'): ?>
            <!-- Konfirmasi Kas & Treasury PMS -->
            <?= view('components/company_cash_integration', [
                'company' => $company,
                'preview' => $preview,
                'accounts' => $accounts,
                'defaultAccountId' => $defaultAccountId ?? ($accounts[0]['id'] ?? 1),
                'canPost' => $canPost,
                'canApprove' => $canApprove,
            ]) ?>

        <?php elseif ($sub === 'reconciliation'): ?>
            <!-- Rekonsiliasi Bank & Kas (CF-5) -->
            <?php
            $isSinglePool = count($reconciliation['accounts']) <= 1;
            $primaryAcc = $reconciliation['accounts'][0] ?? [
                'account_id' => $defaultAccountId ?? ($accounts[0]['id'] ?? 1),
                'code' => 'KAS_HOLDING',
                'name' => 'Kas & Bank Konsolidasi Holding',
                'ledger_balance' => '0.00',
                'statement_balance' => null,
                'discrepancy' => null,
                'status' => 'pending',
                'statement_date' => date('Y-m-t', strtotime($selectedMonth . '-01')),
                'notes' => '',
                'reference_document' => '',
            ];
            ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-check2-circle text-primary me-2"></i>Rekonsiliasi Kas &amp; Bank Holding — Periode <?= esc($selectedMonth) ?>
                        </h6>
                        <small class="text-secondary">Pencocokan total saldo riil fisik / bank terhadap mutasi buku kas sistem (IAS 7 / CF-5 Gate).</small>
                    </div>
                    <?php if ($reconciliation['is_period_closed']): ?>
                        <span class="badge bg-dark text-white p-2">
                            <i class="bi bi-lock-fill me-1"></i>Periode Ditutup (Read-Only)
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    <?php if ($isSinglePool): ?>
                        <!-- Single Consolidated Pool Display (Pure Total) -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="extra-small text-muted text-uppercase fw-semibold mb-1">Saldo Buku Sistem (MTD)</div>
                                    <div class="holding-num fw-bold fs-4 text-dark"><?= CashAmount::rp($primaryAcc['ledger_balance']) ?></div>
                                    <div class="extra-small text-secondary mt-1">Akumulasi kas riil terverifikasi di sistem</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="extra-small text-muted text-uppercase fw-semibold mb-1">Saldo Riil Kas / Bank (Opname)</div>
                                    <div class="holding-num fw-bold fs-4 text-dark">
                                        <?= $primaryAcc['statement_balance'] !== null ? CashAmount::rp($primaryAcc['statement_balance']) : '<span class="text-secondary fs-5">Belum diinput</span>' ?>
                                    </div>
                                    <div class="extra-small text-secondary mt-1">Posisi saldo riil per akhir <?= esc($selectedMonth) ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="extra-small text-muted text-uppercase fw-semibold mb-1">Status Rekonsiliasi</div>
                                    <div class="mt-1">
                                        <?php if ($primaryAcc['status'] === 'reconciled'): ?>
                                            <span class="badge bg-success text-white px-2 py-1 fs-6">
                                                <i class="bi bi-check-circle me-1"></i>Terekonsiliasi (Matched)
                                            </span>
                                            <div class="extra-small text-success mt-1 fw-semibold">Selisih: Rp0</div>
                                        <?php elseif ($primaryAcc['status'] === 'discrepancy'): ?>
                                            <span class="badge bg-danger text-white px-2 py-1 fs-6">
                                                <i class="bi bi-exclamation-triangle me-1"></i>Terdapat Selisih
                                            </span>
                                            <div class="extra-small text-danger mt-1 fw-bold">Selisih: <?= CashAmount::rp($primaryAcc['discrepancy']) ?></div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary text-white px-2 py-1 fs-6">
                                                <i class="bi bi-clock me-1"></i>Menunggu Input
                                            </span>
                                            <div class="extra-small text-secondary mt-1">Belum diverifikasi saldo riil</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Input Rekonsiliasi Tunggal -->
                        <?php if ($canReconcile && ! $reconciliation['is_period_closed']): ?>
                            <div class="p-3 border rounded bg-light">
                                <h6 class="fw-bold mb-2 text-dark">
                                    <i class="bi bi-file-earmark-check me-1 text-primary"></i>Pencocokan Saldo Kas / Bank Riil Akhir Bulan
                                </h6>
                                <p class="extra-small text-muted mb-3">
                                    Masukkan total saldo kas riil (gabungan rekening koran / kas opname pemilik) per akhir bulan <?= esc($selectedMonth) ?> untuk memastikan mutasi di sistem cocok dengan uang riil.
                                </p>
                                <form method="post" action="<?= site_url('finance/company/cash') ?>?company=<?= (int) $company['id'] ?>" class="row g-2 align-items-end">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="tab" value="treasury">
                                    <input type="hidden" name="sub" value="reconciliation">
                                    <input type="hidden" name="month" value="<?= esc($selectedMonth) ?>">
                                    <input type="hidden" name="period_month" value="<?= esc($selectedMonth) ?>">
                                    <input type="hidden" name="operation" value="reconcile_bank">
                                    <input type="hidden" name="confirmed" value="1">
                                    <input type="hidden" id="rec-account-id" name="account_id" value="<?= (int) $primaryAcc['account_id'] ?>">

                                    <div class="col-md-3 col-sm-6">
                                        <label for="rec-statement-date" class="form-label extra-small text-muted mb-1">Tanggal Saldo Koran / Opname</label>
                                        <input id="rec-statement-date" name="statement_date" type="date" class="form-control form-control-sm"
                                               value="<?= esc($primaryAcc['statement_date'] ?? date('Y-m-t', strtotime($selectedMonth . '-01'))) ?>" required>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <label for="rec-statement-balance" class="form-label extra-small text-muted mb-1">Total Saldo Riil Kas/Bank (IDR)</label>
                                        <input id="rec-statement-balance" name="statement_balance" type="number" step="0.01" class="form-control form-control-sm holding-num"
                                               value="<?= esc($primaryAcc['statement_balance'] ?? '') ?>"
                                               placeholder="Contoh: <?= esc($primaryAcc['ledger_balance']) ?>" required>
                                    </div>
                                    <div class="col-md-4 col-sm-8">
                                        <label for="rec-notes" class="form-label extra-small text-muted mb-1">Catatan / Referensi (Opsional)</label>
                                        <input id="rec-notes" name="notes" type="text" class="form-control form-control-sm"
                                               value="<?= esc($primaryAcc['notes'] ?: $primaryAcc['reference_document']) ?>"
                                               placeholder="Nomor rekening koran / berita acara kas opname" maxlength="500">
                                    </div>
                                    <div class="col-md-2 col-sm-4">
                                        <button type="submit" class="btn btn-primary btn-sm w-100">
                                            <i class="bi bi-check2-circle me-1"></i>Simpan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Multi-Account Strip & Table (Fallback for multi-account) -->
                        <div class="row g-2 mb-4">
                            <div class="col-sm-6 col-lg-3">
                                <div class="p-3 border rounded bg-light">
                                    <div class="extra-small text-muted text-uppercase fw-semibold mb-1">Total Rekening</div>
                                    <div class="holding-num fw-bold fs-5 text-dark"><?= (int) $reconciliation['total_accounts'] ?> Akun</div>
                                    <div class="extra-small text-secondary mt-1">Kas &amp; Bank Aktif</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="p-3 border rounded bg-light">
                                    <div class="extra-small text-muted text-uppercase fw-semibold mb-1">Terekonsiliasi (Matched)</div>
                                    <div class="holding-num fw-bold fs-5 text-success"><?= (int) $reconciliation['reconciled_count'] ?> Akun</div>
                                    <div class="extra-small text-secondary mt-1">Selisih Rp0</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="p-3 border rounded bg-light">
                                    <div class="extra-small text-muted text-uppercase fw-semibold mb-1">Selisih (Discrepancy)</div>
                                    <div class="holding-num fw-bold fs-5 <?= $reconciliation['discrepancy_count'] > 0 ? 'text-danger' : 'text-dark' ?>">
                                        <?= (int) $reconciliation['discrepancy_count'] ?> Akun
                                    </div>
                                    <div class="extra-small text-secondary mt-1">Total: <?= CashAmount::rp($reconciliation['total_discrepancy']) ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="p-3 border rounded bg-light">
                                    <div class="extra-small text-muted text-uppercase fw-semibold mb-1">Menunggu Input</div>
                                    <div class="holding-num fw-bold fs-5 text-secondary"><?= (int) $reconciliation['pending_count'] ?> Akun</div>
                                    <div class="extra-small text-secondary mt-1">Belum diverifikasi</div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr class="extra-small text-secondary">
                                        <th>Kode Akun</th>
                                        <th>Nama Rekening</th>
                                        <th>Jenis</th>
                                        <th class="text-end">Saldo Buku Ledger</th>
                                        <th class="text-end">Saldo Rek. Koran</th>
                                        <th class="text-end">Selisih (Variance)</th>
                                        <th class="text-center">Status</th>
                                        <th>Tanggal Koran</th>
                                        <th>Catatan / Ref</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    <?php foreach ($reconciliation['accounts'] as $acc): ?>
                                        <tr>
                                            <td class="holding-num fw-semibold text-dark"><?= esc($acc['code']) ?></td>
                                            <td><?= esc($acc['name']) ?></td>
                                            <td>
                                                <span class="badge bg-light text-secondary border extra-small">
                                                    <?= esc(strtoupper($acc['account_type'])) ?>
                                                </span>
                                            </td>
                                            <td class="holding-num text-end fw-semibold">
                                                <?= CashAmount::rp($acc['ledger_balance']) ?>
                                            </td>
                                            <td class="holding-num text-end">
                                                <?= $acc['statement_balance'] !== null ? CashAmount::rp($acc['statement_balance']) : '<span class="text-muted">-</span>' ?>
                                            </td>
                                            <td class="holding-num text-end">
                                                <?php if ($acc['discrepancy'] !== null): ?>
                                                    <?php $discVal = (float) $acc['discrepancy']; ?>
                                                    <span class="<?= $discVal === 0.0 ? 'text-success' : 'text-danger fw-bold' ?>">
                                                         <?= CashAmount::rp($acc['discrepancy']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($acc['status'] === 'reconciled'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle extra-small">
                                                        <i class="bi bi-check-circle me-1"></i>TEREKONSILIASI
                                                    </span>
                                                <?php elseif ($acc['status'] === 'discrepancy'): ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle extra-small">
                                                        <i class="bi bi-exclamation-circle me-1"></i>SELISIH
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border extra-small">
                                                        BELUM INPUT
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="extra-small text-muted"><?= esc($acc['statement_date'] ?? '-') ?></td>
                                            <td class="extra-small text-muted" style="max-width: 180px;">
                                                <div class="text-truncate" title="<?= esc($acc['notes'] ?: $acc['reference_document']) ?>">
                                                    <?= esc($acc['notes'] ?: ($acc['reference_document'] ?: '-')) ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($canReconcile && ! $reconciliation['is_period_closed']): ?>
                                                    <button type="button"
                                                            class="btn btn-outline-primary btn-sm extra-small py-1 px-2"
                                                            onclick="document.getElementById('rec-account-id').value = '<?= (int) $acc['account_id'] ?>'; document.getElementById('rec-statement-balance').value = '<?= esc($acc['statement_balance'] ?? $acc['ledger_balance']) ?>'; document.getElementById('rec-form-section').scrollIntoView({behavior: 'smooth'});">
                                                        <i class="bi bi-pencil-square me-1"></i>Cocokkan
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted extra-small">Terkunci</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($canReconcile && ! $reconciliation['is_period_closed']): ?>
                            <div id="rec-form-section" class="p-3 border rounded bg-light">
                                <h6 class="fw-bold mb-2 text-dark">
                                    <i class="bi bi-file-earmark-diff me-1 text-primary"></i>Input / Pembaruan Rekening Koran Bank
                                </h6>
                                <p class="extra-small text-muted mb-3">
                                    Masukkan saldo penutup dari rekening koran bank resmi per akhir bulan. Jika terjadi selisih dengan saldo buku ledger, sertakan catatan tindak lanjut audit.
                                </p>
                                <form method="post" action="<?= site_url('finance/company/cash') ?>?company=<?= (int) $company['id'] ?>" class="row g-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="tab" value="treasury">
                                    <input type="hidden" name="month" value="<?= esc($selectedMonth) ?>">
                                    <input type="hidden" name="period_month" value="<?= esc($selectedMonth) ?>">
                                    <input type="hidden" name="operation" value="reconcile_bank">
                                    <input type="hidden" name="confirmed" value="1">

                                    <div class="col-md-3 col-sm-6">
                                        <label for="rec-account-id" class="form-label extra-small text-muted mb-1">Rekening Kas / Bank</label>
                                        <select id="rec-account-id" name="account_id" class="form-select form-select-sm" required>
                                            <option value="">-- Pilih Rekening --</option>
                                            <?php foreach ($reconciliation['accounts'] as $a): ?>
                                                <option value="<?= (int) $a['account_id'] ?>">
                                                    <?= esc($a['name']) ?> (<?= esc($a['code']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <label for="rec-statement-date" class="form-label extra-small text-muted mb-1">Tanggal Rekening Koran</label>
                                        <input id="rec-statement-date" type="date" name="statement_date" class="form-control form-control-sm"
                                               value="<?= date('Y-m-t', strtotime($selectedMonth . '-01')) ?>" required>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <label for="rec-statement-balance" class="form-label extra-small text-muted mb-1">Saldo Rekening Koran (Rp)</label>
                                        <input id="rec-statement-balance" type="number" step="0.01" name="statement_balance" class="form-control form-control-sm"
                                               placeholder="0.00" required>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <label for="rec-reference" class="form-label extra-small text-muted mb-1">No. Referensi / Berkas</label>
                                        <input id="rec-reference" type="text" name="reference_document" class="form-control form-control-sm"
                                               placeholder="Misal: RK-BCA-202610">
                                    </div>
                                    <div class="col-12">
                                        <label for="rec-notes" class="form-label extra-small text-muted mb-1">Catatan Penjelasan Selisih (Jika ada perbedaan saldo)</label>
                                        <input id="rec-notes" type="text" name="notes" class="form-control form-control-sm"
                                               placeholder="Misal: Biaya administrasi bank Rp15.000 belum dicatat / setoran kliring H-1">
                                    </div>
                                    <div class="col-12 pt-2">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-save me-1"></i>Simpan Rekonsiliasi Bank
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($sub === 'close_period'): ?>
            <!-- Tutup Buku & Penguncian Periode (CF-5 Gate) -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-lock text-primary me-2"></i>Penguncian Periode Akuntansi &amp; Pembekuan Snapshot (CF-5)
                        </h6>
                        <small class="text-secondary">Prosedur tutup buku bulanan membekukan versi laporan (*snapshot freeze*), memverifikasi integritas matematis kas, dan menolak transaksi mundur.</small>
                    </div>
                </div>
                <div class="card-body p-4">
                    <!-- Status Banner Periode Saat Ini -->
                    <?php if ($reconciliation['is_period_closed']): ?>
                        <div class="alert alert-secondary border d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 mb-4">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-dark text-white">TERKUNCI &bull; CLOSED</span>
                                    <span class="badge bg-secondary-subtle text-secondary border">Snapshot v<?= esc($snapshot['version'] ?? 1) ?></span>
                                </div>
                                <strong>Periode <?= esc($selectedMonth) ?> telah resmi ditutup dan dibekukan.</strong>
                                <div class="extra-small text-muted mt-1">
                                    Dibekukan pada <?= esc($snapshot['created_at'] ?? $reconciliation['period_info']['updated_at'] ?? '-') ?> UTC &bull;
                                    Catatan: "<?= esc($reconciliation['period_info']['reason'] ?? '-') ?>"
                                </div>
                                <?php if (! empty($snapshot['snapshot_hash'])): ?>
                                    <div class="extra-small font-monospace text-secondary mt-1">
                                        SHA-256 Hash Integritas: <?= esc(substr((string) $snapshot['snapshot_hash'], 0, 24)) ?>...
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($canClose): ?>
                                <div>
                                    <form method="post" action="<?= site_url('finance/company/cash') ?>?company=<?= (int) $company['id'] ?>" class="d-flex align-items-center gap-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="tab" value="treasury">
                                        <input type="hidden" name="month" value="<?= esc($selectedMonth) ?>">
                                        <input type="hidden" name="period_month" value="<?= esc($selectedMonth) ?>">
                                        <input type="hidden" name="period_status" value="open">
                                        <input type="hidden" name="operation" value="set_period">
                                        <input type="hidden" name="confirmed" value="1">
                                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Alasan audit buka kembali..." required style="min-width: 220px;">
                                        <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap" onclick="return confirm('Apakah Anda yakin ingin membuka kembali periode akuntansi yang telah dikunci ini?');">
                                            <i class="bi bi-unlock me-1"></i>Buka Kembali (Reopen)
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Form Tutup Buku -->
                        <?php if ($canClose): ?>
                            <div class="row g-3 mb-4">
                                <div class="col-lg-6">
                                    <div class="p-3 border rounded bg-light">
                                        <h6 class="fw-bold mb-2 text-dark">Tutup Buku Bulan <?= esc($selectedMonth) ?></h6>
                                        <p class="extra-small text-muted mb-3">
                                            Tutup buku akan memverifikasi integritas matematis seluruh rekening (Saldo Penutup = Saldo Pembuka + Mutasi), membekukan snapshot laporan, dan mengunci transaksi tanggal mundur.
                                        </p>

                                        <?php if ($reconciliation['discrepancy_count'] > 0): ?>
                                            <div class="alert alert-warning border border-warning-subtle small py-2 px-3 mb-3">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                Terdapat <strong><?= (int) $reconciliation['discrepancy_count'] ?> rekening</strong> dengan selisih rekonsiliasi bank yang belum nol. Pastikan alasan audit dicatat secara rinci.
                                            </div>
                                        <?php endif; ?>

                                        <form method="post" action="<?= site_url('finance/company/cash') ?>?company=<?= (int) $company['id'] ?>" class="row g-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="tab" value="treasury">
                                            <input type="hidden" name="month" value="<?= esc($selectedMonth) ?>">
                                            <input type="hidden" name="period_month" value="<?= esc($selectedMonth) ?>">
                                            <input type="hidden" name="period_status" value="closed">
                                            <input type="hidden" name="operation" value="set_period">
                                            <input type="hidden" name="confirmed" value="1">

                                            <div class="col-12">
                                                <label for="close-period-reason" class="form-label extra-small text-muted mb-1">Alasan Penutupan Buku / Catatan Audit</label>
                                                <input id="close-period-reason" type="text" name="reason" class="form-control form-control-sm"
                                                       placeholder="Contoh: Tutup buku bulanan selesai, saldo terverifikasi cocok" required>
                                            </div>
                                            <div class="col-12 pt-2">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menutup dan membekukan periode akuntansi ini?');">
                                                    <i class="bi bi-shield-lock me-1"></i>Tutup Buku &amp; Bekukan Snapshot
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="p-3 border rounded bg-white h-100">
                                        <h6 class="fw-bold mb-2 text-dark">Prasyarat Integritas Kas (CF-5 Gate)</h6>
                                        <ul class="extra-small text-secondary ps-3 mb-0">
                                            <li class="mb-1"><strong>Persamaan Mutasi:</strong> Saldo penutup setiap rekening wajib cocok matematis terhadap saldo pembuka ditambah mutasi masuk dikurangi mutasi keluar.</li>
                                            <li class="mb-1"><strong>Pembekuan Versi:</strong> Snapshot JSON laporan bulanan (jembatan hak holding, kontribusi properti, arus kas IAS 7, dan kewajiban) disimpan permanen dengan hash SHA-256.</li>
                                            <li class="mb-1"><strong>Immutability:</strong> Transaksi kas ke periode tertutup akan ditolak otomatis.</li>
                                            <li class="mb-0"><strong>Kewenangan:</strong> Hanya <em>Company Approver</em> yang dapat menutup atau membuka kembali periode akuntansi holding.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Daftar Periode Terkunci & Riwayat Snapshot -->
                    <h6 class="fw-bold mb-2 text-dark">Daftar Periode Terkunci &amp; Riwayat Audit</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr class="extra-small text-secondary">
                                    <th>Periode Bulan</th>
                                    <th>Status</th>
                                    <th>Alasan Audit</th>
                                    <th>Terakhir Diubah</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php if (empty($auditSummary['closed_periods'])): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">
                                            Belum ada periode akuntansi yang dikunci.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($auditSummary['closed_periods'] as $cp): ?>
                                        <tr>
                                            <td class="holding-num fw-semibold"><?= esc($cp['month']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?= esc(strtoupper($cp['status'])) ?></span>
                                            </td>
                                            <td class="extra-small text-muted"><?= esc($cp['reason'] ?? '-') ?></td>
                                            <td class="holding-num extra-small text-muted"><?= esc($cp['updated_at'] ?? '-') ?> UTC</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($sub === 'overhead'): ?>
            <!-- Alokasi Overhead -->
            <?= view('components/company_overhead_allocations', [
                'company' => $company,
                'properties' => $properties,
                'preview' => $preview,
                'allocations' => $allocations,
                'selectedMonth' => $selectedMonth,
                'canPost' => $canPost,
                'canApprove' => $canApprove,
            ]) ?>
        <?php endif; ?>
    </div>
</div>
