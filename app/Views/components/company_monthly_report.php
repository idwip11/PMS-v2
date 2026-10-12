<?php
use App\Libraries\CashAmount;

/**
 * View Component: Laporan Bulanan Manajemen & Hak Perusahaan (CF-3)
 */
$cards = $monthlyReport['cards'];
$bridge = $monthlyReport['bridge'];
$propReports = $monthlyReport['properties'];
$warnings = $monthlyReport['warnings'];
?>

<div class="mt-4">
    <!-- Warnings / Exceptions Alert Box -->
    <?php if ($warnings['unconfirmed_payments'] !== [] || $warnings['unconfigured_properties'] !== [] || $warnings['estimated_obligations_count'] > 0): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <h6 class="alert-heading fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Peringatan &amp; Pengecualian Laporan</h6>
            <ul class="mb-0 small">
                <?php foreach ($warnings['unconfirmed_payments'] as $ucp): ?>
                    <li><strong><?= esc($ucp['property']) ?>:</strong> Ada <strong><?= (int) $ucp['count'] ?></strong> transaksi pembayaran berstatus Paid di modul operasional yang belum dikonfirmasi ke rekening kas holding.</li>
                <?php endforeach; ?>
                <?php foreach ($warnings['unconfigured_properties'] as $ucprop): ?>
                    <li><strong><?= esc($ucprop) ?>:</strong> Belum memiliki perjanjian hak pengelolaan yang berlaku pada periode ini. Hak pengelola/owner belum dihitung otomatis.</li>
                <?php endforeach; ?>
                <?php if ($warnings['estimated_obligations_count'] > 0): ?>
                    <li>Ada <strong><?= (int) $warnings['estimated_obligations_count'] ?></strong> kewajiban keuangan berstatus <em>estimated</em> yang menunggu finalisasi oleh Company Approver.</li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Tabel Kontribusi per Properti -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title h6 mb-1 fw-bold">Kontribusi Kinerja &amp; Hak Ekonomi per Properti</h5>
                    <p class="small text-muted mb-0">Cakupan periode: <?= esc($monthlyReport['firstDay']) ?> s/d <?= esc($monthlyReport['lastDay']) ?>. Unit dihitung proporsional; Homestay 3 kamar dihitung 1 rumah.</p>
                </div>
                <span class="badge bg-light text-secondary border">Basis Kas CF-3</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Properti</th>
                        <th class="text-center">Aktivitas Unit</th>
                        <th class="text-end">Penerimaan Kas (Net)</th>
                        <th class="text-end">Beban Kas Unit</th>
                        <th class="text-end">Surplus Kas Unit</th>
                        <th class="text-end">Hak Owner</th>
                        <th class="text-end">Fee Pengelola</th>
                        <th class="text-end">Layanan Langsung</th>
                        <th class="text-end">Alokasi Overhead</th>
                        <th class="text-end">Kontribusi Bersih</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php if ($propReports === []): ?>
                        <tr><td colspan="10" class="text-center text-secondary py-3">Belum ada properti dalam cakupan holding.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($propReports as $pr): ?>
                        <tr>
                            <td>
                                <strong class="d-block text-dark"><?= esc($pr['property_name']) ?></strong>
                                <span class="badge bg-secondary-subtle text-secondary border"><?= esc($pr['property_code']) ?></span>
                                <div class="text-muted extra-small mt-1"><?= esc($pr['agreement_note']) ?></div>
                                <?php if (! empty($pr['agreement_breakdown'])): ?>
                                    <div class="text-muted extra-small mt-0.5" style="font-size: 0.68rem; line-height: 1.25;"><i class="bi bi-diagram-3 me-1"></i><?= esc($pr['agreement_breakdown']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div><strong><?= (int) $pr['booking_count'] ?></strong> booking</div>
                                <div class="text-muted extra-small"><?= (int) $pr['room_nights_sold'] ?> <?= $pr['pricing_mode'] === 'whole_home_nightly' ? 'malam rumah' : 'kamar-malam' ?></div>
                            </td>
                            <td class="text-end fw-semibold text-primary">
                                <?= CashAmount::rpPrecise($pr['confirmed_net_cash']) ?>
                                <?php if (CashAmount::cents($pr['withheld_fee']) > 0): ?>
                                    <div class="text-muted extra-small">Pot. OTA: <?= CashAmount::rpPrecise($pr['withheld_fee']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-danger"><?= CashAmount::rpPrecise($pr['paid_expenses']) ?></td>
                            <td class="text-end fw-semibold <?= CashAmount::cents($pr['cash_surplus']) < 0 ? 'text-danger' : 'text-dark' ?>">
                                <?= CashAmount::rpPrecise($pr['cash_surplus']) ?>
                            </td>
                            <td class="text-end text-secondary">
                                <?= $pr['has_agreement'] ? CashAmount::rpPrecise($pr['owner_share']) : '<span class="text-warning">Belum diatur</span>' ?>
                            </td>
                            <td class="text-end text-dark">
                                <?= $pr['has_agreement'] ? CashAmount::rpPrecise($pr['company_fee']) : '-' ?>
                            </td>
                            <td class="text-end text-success">
                                <?php if (CashAmount::cents($pr['direct_services']) > 0): ?>
                                    +<?= CashAmount::rpPrecise($pr['direct_services']) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-danger">
                                <?php if (CashAmount::cents($pr['allocated_overhead']) > 0): ?>
                                    -<?= CashAmount::rpPrecise($pr['allocated_overhead']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Rp0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold text-primary">
                                <?= CashAmount::rpPrecise($pr['net_contribution']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Jembatan Rekonsiliasi Hak Perusahaan (Reconciliation Bridge) -->
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title h6 mb-1 fw-bold">Jembatan Rekonsiliasi Hak Perusahaan (Holding)</h5>
                    <p class="small text-muted mb-0">Menghubungkan kas operasional gabungan dengan pendapatan hak perusahaan.</p>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="ps-3 py-2 text-dark">Total Kas Masuk Tamu Terkonfirmasi (Net Kas)</td>
                                <td class="text-end pe-3 py-2 fw-semibold text-primary"><?= CashAmount::rpPrecise($bridge['total_confirmed_net']) ?></td>
                            </tr>
                            <tr>
                                <td class="ps-3 py-2 text-secondary">(-) Hak Pemilik Properti / Owner (Bagi Hasil)</td>
                                <td class="text-end pe-3 py-2 text-danger">- <?= CashAmount::rpPrecise($bridge['total_owner_share']) ?></td>
                            </tr>
                            <tr>
                                <td class="ps-3 py-2 text-dark">(+) Pendapatan Layanan Langsung Perusahaan (Breakfast, Laundry, Massage, Sewa Kendaraan; Extra Bed khusus HomiQ)</td>
                                <td class="text-end pe-3 py-2 text-success">+ <?= CashAmount::rpPrecise($bridge['total_direct_services']) ?></td>
                            </tr>
                            <tr class="table-light">
                                <td class="ps-3 py-2 fw-semibold text-dark">(=) Total Pendapatan / Hak Perusahaan (Gross Entitlement)</td>
                                <td class="text-end pe-3 py-2 fw-bold text-dark"><?= CashAmount::rpPrecise($bridge['total_company_entitlement']) ?></td>
                            </tr>
                            <tr>
                                <td class="ps-3 py-2 text-secondary">(-) Beban Overhead Kantor yang Dibayar Kas</td>
                                <td class="text-end pe-3 py-2 text-danger">- <?= CashAmount::rpPrecise($bridge['holding_overhead_paid']) ?></td>
                            </tr>
                            <tr class="table-primary fw-bold">
                                <td class="ps-3 py-3 text-primary">(=) Hasil Usaha Bersih Perusahaan (Berbasis Kas)</td>
                                <td class="text-end pe-3 py-3 text-primary fs-6"><?= CashAmount::rpPrecise($bridge['company_net_result']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-light border-0 small text-secondary">
                    <em>Catatan:</em> Beban overhead kantor yang dicatat bulan ini: <?= CashAmount::rpPrecise($bridge['holding_overhead_incurred']) ?>. Alokasi ke unit hanya sebagai analisis manajemen dan tidak menggandakan total beban holding.
                </div>
            </div>
        </div>

        <!-- Rincian Cadangan Kas Bebas -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title h6 mb-1 fw-bold">Komposisi Cadangan &amp; Kas Bebas</h5>
                    <p class="small text-muted mb-0">Uang kas yang dikelola vs kewajiban titipan.</p>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="ps-3 py-2 text-dark">Total Saldo Kas/Bank Saat Ini</td>
                                <td class="text-end pe-3 py-2 fw-bold text-dark"><?= CashAmount::rpPrecise($cards['current_cash_total']) ?></td>
                            </tr>
                            <tr>
                                <td class="ps-3 py-2 text-danger small">Cadangan Hak Owner Belum Disetor</td>
                                <td class="text-end pe-3 py-2 text-danger small">- <?= CashAmount::rpPrecise($cards['reserves_detail']['owner_payable']) ?></td>
                            </tr>
                            <?php if (CashAmount::cents($cards['reserves_detail']['other']) > 0): ?>
                                <tr>
                                    <td class="ps-3 py-2 text-danger small">Cadangan Kewajiban Lainnya</td>
                                    <td class="text-end pe-3 py-2 text-danger small">- <?= CashAmount::rpPrecise($cards['reserves_detail']['other']) ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr class="table-light">
                                <td class="ps-3 py-2 fw-semibold text-secondary">Total Dana yang Harus Dicadangkan</td>
                                <td class="text-end pe-3 py-2 fw-bold text-danger">- <?= CashAmount::rpPrecise($cards['total_reserves']) ?></td>
                            </tr>
                            <tr class="table-<?= CashAmount::cents($cards['estimated_free_cash']) < 0 ? 'danger' : 'success' ?> fw-bold">
                                <td class="ps-3 py-3">Estimasi Kas Bebas Pengelola</td>
                                <td class="text-end pe-3 py-3 fs-6"><?= CashAmount::rpPrecise($cards['estimated_free_cash']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-light border-0 small text-secondary">
                    <em>Perhatian:</em> Kas bebas merupakan estimasi pengendalian kas. Angka ini dapat bernilai negatif jika kewajiban yang telah jatuh tempo melebihi likuiditas kas saat ini. Tidak dianjurkan untuk dibagikan sebagai dividen sebelum rekonsiliasi dan tutup periode CF-5.
                </div>
            </div>
        </div>
    </div>
</div>
