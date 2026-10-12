<?php
use App\Libraries\CashAmount;

/**
 * View Component: Kewajiban Keuangan & Pelunasan (CF-3)
 */
$action = site_url('finance/company/cash') . '?company=' . (int) $company['id'];
$accountOptions = static function () use ($accounts): void {
    foreach ($accounts as $a) {
        if ((int) $a['is_active'] === 1 && $a['balance'] !== null) {
            echo '<option value="' . (int) $a['id'] . '">' . esc($a['name']) . ' (Saldo: ' . CashAmount::rp($a['balance']) . ')</option>';
        }
    }
};
?>

<div class="mt-4">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title h6 mb-1 fw-bold">Daftar Kewajiban Keuangan (Hak Owner, Refund, Titipan)</h5>
                <p class="small text-muted mb-0">Kewajiban kas yang dicadangkan dari saldo bank. Pelunasan kewajiban melunasi saldo utang tanpa mencatat beban ganda.</p>
            </div>
            <?php if ($canPost): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#collapseAddObligation">
                    <i class="bi bi-plus-lg me-1"></i>Catat Kewajiban Baru
                </button>
            <?php endif; ?>
        </div>

        <?php if ($canPost): ?>
            <!-- Form Catat Kewajiban Baru (Collapse) -->
            <div class="collapse p-3 bg-light border-top" id="collapseAddObligation">
                <form method="post" action="<?= esc($action) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tab" value="obligations">
                    <input type="hidden" name="month" value="<?= esc($selectedMonth) ?>">
                    <input type="hidden" name="operation" value="create_obligation">
                    <input type="hidden" name="confirmed" value="1">

                    <h6 class="fw-bold mb-3">Form Catat Kewajiban Keuangan</h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small">Jenis Kewajiban</label>
                            <select name="obligation_type" class="form-select form-select-sm" required>
                                <option value="owner_payable">Hak Owner / Bagi Hasil Belum Disetor</option>
                                <option value="refund_deposit">Refund Tamu / Deposit Titipan</option>
                                <option value="tax_withholding">Titipan Pajak / Potongan</option>
                                <option value="other">Kewajiban Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Properti Terkait</label>
                            <select name="property_id" class="form-select form-select-sm">
                                <option value="">Holding / Seluruh Properti</option>
                                <?php foreach ($properties as $prop): ?>
                                    <option value="<?= (int) $prop['id'] ?>"><?= esc($prop['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Periode Bulan (Opsional)</label>
                            <input name="month" type="month" class="form-control form-control-sm" value="<?= esc($selectedMonth) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Status Awal</label>
                            <select name="status" class="form-select form-select-sm" <?= $canApprove ? '' : 'disabled' ?>>
                                <option value="estimated">Estimated (Perlu Approval)</option>
                                <?php if ($canApprove): ?>
                                    <option value="finalized">Finalized (Siap Dibayar Kas)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Jenis Referensi</label>
                            <input name="reference_type" class="form-control form-control-sm" placeholder="Contoh: owner_settlement" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">ID / Kunci Referensi Unik</label>
                            <input name="reference_id" class="form-control form-control-sm" placeholder="Contoh: HOMIQ-GH1-2026-09" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Nominal Kewajiban (IDR)</label>
                            <input name="amount" class="form-control form-control-sm" inputmode="decimal" placeholder="Contoh: 1500000.00" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Jatuh Tempo (Opsional)</label>
                            <input name="due_date" type="date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small">Deskripsi / Catatan Kewajiban</label>
                            <input name="description" class="form-control form-control-sm" placeholder="Keterangan rincian bagi hasil / hak owner / refund..." maxlength="500" required>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#collapseAddObligation">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Kewajiban</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Tabel Daftar Kewajiban -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>ID / Jenis</th>
                        <th>Properti &amp; Referensi</th>
                        <th>Deskripsi</th>
                        <th class="text-end">Nominal</th>
                        <th class="text-end">Sudah Dibayar</th>
                        <th class="text-end">Sisa Kewajiban</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php if ($obligations === []): ?>
                        <tr><td colspan="8" class="text-center text-secondary py-3">Belum ada data kewajiban keuangan.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($obligations as $ob): ?>
                        <tr>
                            <td>
                                <strong>#<?= (int) $ob['id'] ?></strong>
                                <span class="badge bg-secondary-subtle text-secondary border d-block mt-1">
                                    <?= esc(str_replace('_', ' ', $ob['obligation_type'])) ?>
                                </span>
                            </td>
                            <td>
                                <strong class="d-block text-dark"><?= esc($ob['property_name'] ?? 'Holding') ?></strong>
                                <span class="text-muted extra-small"><?= esc($ob['reference_type']) ?>: <?= esc($ob['reference_id']) ?></span>
                                <?php if (! empty($ob['month'])): ?>
                                    <span class="badge bg-light text-secondary border ms-1"><?= esc($ob['month']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-dark"><?= esc($ob['description']) ?></div>
                                <?php if (! empty($ob['due_date'])): ?>
                                    <div class="text-danger extra-small">Jatuh tempo: <?= esc($ob['due_date']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold"><?= CashAmount::rp($ob['amount']) ?></td>
                            <td class="text-end text-success"><?= CashAmount::rp($ob['settled_amount']) ?></td>
                            <td class="text-end fw-bold <?= CashAmount::cents($ob['remaining_balance']) > 0 ? 'text-danger' : 'text-secondary' ?>">
                                <?= CashAmount::rp($ob['remaining_balance']) ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $badgeClass = match ($ob['status']) {
                                    'finalized' => 'bg-warning text-dark',
                                    'settled' => 'bg-success text-white',
                                    'cancelled' => 'bg-secondary text-white',
                                    default => 'bg-info text-dark',
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= esc(ucfirst($ob['status'])) ?></span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <?php if ($ob['status'] === 'estimated' && $canApprove): ?>
                                        <!-- Finalize button -->
                                        <form method="post" action="<?= esc($action) ?>" class="d-inline" onsubmit="return confirm('Finalisasi kewajiban ini agar dapat diproses pelunasan kas?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="tab" value="obligations">
                                            <input type="hidden" name="month" value="<?= esc($selectedMonth) ?>">
                                            <input type="hidden" name="operation" value="finalize_obligation">
                                            <input type="hidden" name="obligation_id" value="<?= (int) $ob['id'] ?>">
                                            <input type="hidden" name="confirmed" value="1">
                                            <button class="btn btn-outline-warning btn-sm py-0 px-2" title="Finalisasi kewajiban">
                                                Finalisasi
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($ob['status'] === 'finalized' && $canPost && CashAmount::cents($ob['remaining_balance']) > 0): ?>
                                        <!-- Settle Modal Trigger -->
                                        <button type="button" class="btn btn-primary btn-sm py-0 px-2" data-bs-toggle="collapse" data-bs-target="#settleForm_<?= (int) $ob['id'] ?>">
                                            Bayar Kas
                                        </button>
                                    <?php endif; ?>

                                    <?php if (in_array($ob['status'], ['estimated', 'finalized'], true) && $canApprove && CashAmount::cents($ob['settled_amount']) === 0): ?>
                                        <!-- Cancel button -->
                                        <form method="post" action="<?= esc($action) ?>" class="d-inline" onsubmit="return confirm('Batalkan kewajiban ini?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="tab" value="obligations">
                                            <input type="hidden" name="month" value="<?= esc($selectedMonth) ?>">
                                            <input type="hidden" name="operation" value="cancel_obligation">
                                            <input type="hidden" name="obligation_id" value="<?= (int) $ob['id'] ?>">
                                            <input type="hidden" name="reason" value="Dibatalkan oleh operator">
                                            <input type="hidden" name="confirmed" value="1">
                                            <button class="btn btn-outline-danger btn-sm py-0 px-1" title="Batalkan kewajiban">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <?php if ($ob['status'] === 'finalized' && $canPost): ?>
                            <!-- Baris Collapse Form Pelunasan Kas -->
                            <tr class="collapse bg-light" id="settleForm_<?= (int) $ob['id'] ?>">
                                <td colspan="8" class="p-3">
                                    <form method="post" action="<?= esc($action) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="tab" value="obligations">
                                        <input type="hidden" name="month" value="<?= esc($selectedMonth) ?>">
                                        <input type="hidden" name="operation" value="settle_obligation">
                                        <input type="hidden" name="obligation_id" value="<?= (int) $ob['id'] ?>">
                                        <input type="hidden" name="event_key" value="<?= esc(bin2hex(random_bytes(24))) ?>">

                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-3">
                                                <label class="form-label extra-small mb-1">Rekening Kas/Bank Keluar</label>
                                                <select name="account_id" class="form-select form-select-sm" required>
                                                    <?php $accountOptions(); ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label extra-small mb-1">Nominal Pelunasan (IDR)</label>
                                                <input name="amount" class="form-control form-control-sm" value="<?= esc($ob['remaining_balance']) ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label extra-small mb-1">Waktu Transaksi (UTC)</label>
                                                <input name="effective_at" type="datetime-local" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label extra-small mb-1">Catatan / Bukti Referensi</label>
                                                <input name="note" class="form-control form-control-sm" placeholder="Nomor bukti transfer / pelunasan..." maxlength="500" required>
                                            </div>
                                            <div class="col-md-1">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="confirmed" value="1" required id="check_<?= (int) $ob['id'] ?>">
                                                </div>
                                                <button type="submit" class="btn btn-success btn-sm w-100">Bayar</button>
                                            </div>
                                        </div>
                                        <small class="text-secondary d-block mt-2">
                                            Pelunasan ini langsung memotong kas rekening dan melunasi kewajiban #<?= (int) $ob['id'] ?> tanpa menambah beban operasional baru di laporan laba rugi.
                                        </small>
                                    </form>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
