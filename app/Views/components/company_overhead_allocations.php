<?php
use App\Libraries\CashAmount;

/**
 * View Component: Alokasi Overhead Kantor ke Properti (CF-3)
 */
$action = site_url('finance/company/cash') . '?company=' . (int) $company['id'];
$approvedOverheads = array_filter($preview['overheads'], static fn ($e) => ($e['status'] ?? '') === 'Approved');
?>

<div class="mt-4">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title h6 mb-1 fw-bold">Alokasi Overhead Kantor ke Properti</h5>
                <p class="small text-muted mb-0">Distribusi beban umum holding ke masing-masing properti untuk pelaporan manajemen. Alokasi ini tidak menambah total biaya holding.</p>
            </div>
            <?php if ($canApprove && $approvedOverheads !== []): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#collapseAddAllocation">
                    <i class="bi bi-plus-lg me-1"></i>Alokasikan Beban
                </button>
            <?php endif; ?>
        </div>

        <?php if ($canApprove && $approvedOverheads !== []): ?>
            <!-- Form Alokasi Overhead (Collapse) -->
            <div class="collapse p-3 bg-light border-top" id="collapseAddAllocation">
                <form method="post" action="<?= esc($action) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tab" value="overhead">
                    <input type="hidden" name="month" value="<?= esc($selectedMonth) ?>">
                    <input type="hidden" name="operation" value="allocate_overhead">
                    <input type="hidden" name="confirmed" value="1">

                    <h6 class="fw-bold mb-3">Distribusi Beban Overhead</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small">Beban Overhead (Approved)</label>
                            <select name="expense_id" class="form-select form-select-sm" required>
                                <?php foreach ($approvedOverheads as $oh): ?>
                                    <option value="<?= (int) $oh['id'] ?>">
                                        #<?= (int) $oh['id'] ?> · <?= esc($oh['description']) ?> (Rp<?= esc($oh['amount']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Properti Tujuan Alokasi</label>
                            <select name="property_id" class="form-select form-select-sm" required>
                                <?php foreach ($properties as $prop): ?>
                                    <option value="<?= (int) $prop['id'] ?>"><?= esc($prop['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Porsi Alokasi (%)</label>
                            <input name="allocation_pct" class="form-control form-control-sm" placeholder="Contoh: 50.00" inputmode="decimal" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Nominal Alokasi (IDR)</label>
                            <input name="amount" class="form-control form-control-sm" placeholder="Contoh: 250000.00" inputmode="decimal" required>
                        </div>
                    </div>
                    <small class="text-secondary d-block mt-2">
                        Total persentase alokasi untuk satu beban overhead tidak boleh melebihi 100% dari nilai beban aslinya.
                    </small>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#collapseAddAllocation">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Alokasi</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Tabel Alokasi Overhead Bulan Terpilih -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>ID</th>
                        <th>Beban Overhead Induk</th>
                        <th>Kategori Beban</th>
                        <th>Properti Penerima</th>
                        <th class="text-center">Porsi (%)</th>
                        <th class="text-end">Nominal Dialokasikan</th>
                        <th>Bulan Periode</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php if ($allocations === []): ?>
                        <tr><td colspan="7" class="text-center text-secondary py-3">Belum ada alokasi overhead untuk bulan <?= esc($selectedMonth) ?>.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($allocations as $al): ?>
                        <tr>
                            <td><strong>#<?= (int) $al['id'] ?></strong></td>
                            <td>
                                <strong class="d-block text-dark"><?= esc($al['expense_description']) ?></strong>
                                <span class="text-muted extra-small">Total Overhead: <?= CashAmount::rp($al['expense_total_amount']) ?></span>
                            </td>
                            <td><span class="badge bg-light text-secondary border"><?= esc($al['expense_category']) ?></span></td>
                            <td>
                                <strong class="text-dark"><?= esc($al['property_name']) ?></strong>
                                <span class="badge bg-secondary-subtle text-secondary border ms-1"><?= esc($al['property_code']) ?></span>
                            </td>
                            <td class="text-center fw-bold text-primary"><?= esc($al['allocation_pct']) ?>%</td>
                            <td class="text-end fw-semibold text-danger"><?= CashAmount::rp($al['amount']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= esc($al['month']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
