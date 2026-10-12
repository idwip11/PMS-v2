<?php
use App\Libraries\CashAmount;

/**
 * View Component: Perjanjian Pengelolaan Finansial (CF-3)
 */
$action = site_url('finance/company/cash') . '?company=' . (int) $company['id'];
?>

<div class="mt-4">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title h6 mb-1 fw-bold">Perjanjian Pengelolaan Finansial (Effective-Dated)</h5>
                <p class="small text-muted mb-0">Aturan pembagian hak ekonomi perusahaan dan pemilik unit. Riwayat perjanjian lama tidak ditimpa; perhitungan mengikuti periode aktif.</p>
            </div>
            <?php if ($canApprove): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#collapseAddAgreement">
                    <i class="bi bi-plus-lg me-1"></i>Buat Perjanjian Baru
                </button>
            <?php endif; ?>
        </div>

        <?php if ($canApprove): ?>
            <!-- Form Tambah Perjanjian (Collapse) -->
            <div class="collapse p-3 bg-light border-top" id="collapseAddAgreement">
                <form method="post" action="<?= esc($action) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tab" value="agreements">
                    <input type="hidden" name="month" value="<?= esc($selectedMonth) ?>">
                    <input type="hidden" name="operation" value="create_agreement">
                    <input type="hidden" name="confirmed" value="1">

                    <h6 class="fw-bold mb-3">Perjanjian Pengelolaan Baru</h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small">Properti</label>
                            <select name="property_id" class="form-select form-select-sm" required>
                                <?php foreach ($properties as $prop): ?>
                                    <option value="<?= (int) $prop['id'] ?>"><?= esc($prop['name']) ?> (<?= esc($prop['code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Jenis Perjanjian</label>
                            <select name="agreement_type" class="form-select form-select-sm" required>
                                <option value="revenue_share">Revenue Share (Bagi Hasil Pendapatan)</option>
                                <option value="net_profit_share">Net Profit Share (Bagi Hasil Laba Bersih)</option>
                                <option value="fixed_fee">Fixed Management Fee (Biaya Tetap)</option>
                                <option value="full_management">Full Management (Sewa Penuh / Operasi Total)</option>
                                <option value="custom">Custom / Perjanjian Khusus</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Dasar Perhitungan</label>
                            <select name="calculation_base" class="form-select form-select-sm" required>
                                <option value="room_revenue">Pendapatan Sewa Kamar (Room Revenue)</option>
                                <option value="gross_revenue">Pendapatan Bruto Operasional (Gross)</option>
                                <option value="net_revenue">Pendapatan Bersih Kas (Net Cash)</option>
                                <option value="operating_profit">Laba Operasional Unit (Surplus)</option>
                                <option value="none">Tidak Ada (Hanya Biaya Tetap)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Biaya Tetap Bulanan (IDR)</label>
                            <input name="fixed_fee_monthly" class="form-control form-control-sm" value="0.00" inputmode="decimal" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Bagian Perusahaan / Pengelola (%)</label>
                            <input name="company_share_pct" class="form-control form-control-sm" value="30.00" inputmode="decimal" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Bagian Pemilik / Owner (%)</label>
                            <input name="owner_share_pct" class="form-control form-control-sm" value="70.00" inputmode="decimal" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Berlaku Mulai Tanggal</label>
                            <input name="effective_from" type="date" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Berlaku Sampai (Kosongkan bila seterusnya)</label>
                            <input name="effective_to" type="date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small">Catatan / Ketentuan Perjanjian</label>
                            <input name="terms_note" class="form-control form-control-sm" placeholder="Nomor klausul / dasar persetujuan owner..." maxlength="500">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#collapseAddAgreement">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Perjanjian</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Tabel Perjanjian -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>ID</th>
                        <th>Properti</th>
                        <th>Jenis &amp; Dasar Perhitungan</th>
                        <th class="text-center">Hak Pengelola (%)</th>
                        <th class="text-center">Hak Owner (%)</th>
                        <th class="text-end">Biaya Tetap / Bulan</th>
                        <th>Periode Berlaku</th>
                        <th>Ketentuan / Pembuat</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php if ($agreements === []): ?>
                        <tr><td colspan="8" class="text-center text-secondary py-3">Belum ada perjanjian pengelolaan yang terdaftar.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($agreements as $ag): ?>
                        <tr>
                            <td><strong>#<?= (int) $ag['id'] ?></strong></td>
                            <td>
                                <strong class="d-block text-dark"><?= esc($ag['property_name']) ?></strong>
                                <span class="badge bg-secondary-subtle text-secondary border"><?= esc($ag['property_code']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border mb-1">
                                    <?= esc(str_replace('_', ' ', $ag['agreement_type'])) ?>
                                </span>
                                <div class="text-muted extra-small">Basis: <?= esc(str_replace('_', ' ', $ag['calculation_base'])) ?></div>
                            </td>
                            <td class="text-center fw-bold text-primary"><?= esc($ag['company_share_pct']) ?>%</td>
                            <td class="text-center fw-semibold text-secondary"><?= esc($ag['owner_share_pct']) ?>%</td>
                            <td class="text-end text-dark">
                                <?= CashAmount::cents($ag['fixed_fee_monthly']) > 0 ? CashAmount::rp($ag['fixed_fee_monthly']) : '-' ?>
                            </td>
                            <td>
                                <div>Mulai: <strong><?= esc($ag['effective_from']) ?></strong></div>
                                <div class="text-muted extra-small">
                                    Sampai: <?= ! empty($ag['effective_to']) ? esc($ag['effective_to']) : '<em>Seterusnya</em>' ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark"><?= esc($ag['terms_note'] ?: '-') ?></div>
                                <div class="text-muted extra-small">Oleh: <?= esc($ag['creator_username'] ?? '-') ?> pada <?= esc($ag['created_at']) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
