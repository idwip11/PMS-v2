<?php
$sections = [
    'amenities' => ['label' => 'Amenities', 'itemType' => 'Amenity', 'description' => 'Daftar standar kebutuhan tamu: Sikat Gigi, Sabun, dan Shampoo. Nama item dan kategori ditetapkan sistem.'],
    'spare_parts' => ['label' => 'Spare Part', 'itemType' => 'Spare Part', 'description' => 'Komponen pengganti untuk maintenance, misalnya lampu, remote, filter AC, baterai, atau part plumbing.'],
    'supplies' => ['label' => 'Supply Operasional (opsional)', 'itemType' => 'Operational Supply', 'description' => 'Barang pendukung kerja operasional hotel seperti alat kebersihan, ATK, chemical, dan perlengkapan back office.'],
    'history' => ['label' => 'Riwayat Stok', 'itemType' => null, 'description' => 'Audit semua pergerakan stok, termasuk stok awal, update manual, pemakaian spare part, dan pengembalian.'],
];
$current = $sections[$inventorySection] ?? $sections['amenities'];
$lowStockVendors = $lowStockVendors ?? [];
$pagination = $pagination ?? null;
$money = static fn ($value): string => 'Rp' . number_format((float) $value, 0, ',', '.');
?>
<nav class="nav nav-pills page-tabs gap-2 mb-4 flex-nowrap" aria-label="Sub-menu inventory" hx-disinherit="hx-vals">
    <?php foreach ($sections as $key => $section): ?>
        <a class="nav-link flex-fill text-nowrap <?= $inventorySection === $key ? 'active' : 'bg-white' ?>"
           href="<?= site_url('ops?tab=inventory&inventory_section=' . $key) ?>"
           hx-get="<?= site_url('ops/panel?tab=inventory&inventory_section=' . $key) ?>"
           hx-target="#ops-panel"
           hx-sync="#ops-panel:replace"
           hx-push-url="<?= site_url('ops?tab=inventory&inventory_section=' . $key) ?>"
           data-tab-link><?= esc($section['label']) ?></a>
    <?php endforeach; ?>
</nav>

<div class="inventory-help-grid mb-4">
    <?php foreach ($sections as $key => $section): ?>
        <div class="inventory-help-item <?= $inventorySection === $key ? 'is-active' : '' ?>">
            <div class="fw-semibold mb-1"><?= esc($section['label']) ?></div>
            <p class="small text-secondary mb-0"><?= esc($section['description']) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($inventorySection === 'history'): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center"><h3 class="h6 mb-0">Riwayat Pergerakan Stok</h3><span class="badge text-bg-light border text-secondary"><?= (int) ($pagination['totalItems'] ?? count($items)) ?> mutasi</span></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Waktu</th><th>Item</th><th>Jenis</th><th class="text-end">Perubahan</th><th class="text-end">Saldo</th><th>Referensi</th><th>Dicatat oleh</th></tr></thead><tbody>
            <?php if ($items === []): ?><tr><td colspan="7" class="text-center text-secondary py-5">Belum ada pergerakan stok.</td></tr><?php endif; ?>
            <?php foreach ($items as $movement): ?>
                <tr>
                    <td class="text-nowrap"><?= esc(substr((string) $movement['created_at'], 0, 16)) ?></td>
                    <td class="fw-semibold"><?= esc($movement['item_name']) ?><small class="d-block text-secondary"><?= esc($movement['unit']) ?></small></td>
                    <td><span class="badge <?= $movement['quantity_change'] < 0 ? 'text-bg-warning' : 'text-bg-success' ?>"><?= esc($movement['movement_type']) ?></span></td>
                    <td class="text-end fw-semibold <?= $movement['quantity_change'] < 0 ? 'text-danger' : 'text-success' ?>"><?= esc(($movement['quantity_change'] > 0 ? '+' : '') . $movement['quantity_change']) ?></td>
                    <td class="text-end"><?= esc($movement['balance_after']) ?></td>
                    <td><?php if (! empty($movement['ticket_number'])): ?><button class="btn btn-sm btn-outline-primary text-nowrap" hx-get="<?= site_url('ops/maintenance/work-orders/' . $movement['reference_id']) ?>" hx-target="#modal-container" hx-swap="innerHTML"><?= esc($movement['ticket_number'] . ' · Kamar ' . $movement['room_number']) ?></button><?php else: ?><?= esc($movement['reference_type'] ?? '-') ?><?php endif; ?></td>
                    <td><?= esc($movement['created_by_name'] ?? 'Sistem') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
        <?php if ($pagination !== null && $pagination['totalItems'] > 0): ?>
            <?php
            $historyPageUrl = static fn (int $page): string => site_url('ops?tab=inventory&inventory_section=history&page=' . $page);
            $historyPanelUrl = static fn (int $page): string => site_url('ops/panel?tab=inventory&inventory_section=history&page=' . $page);
            ?>
            <div class="card-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 py-3">
                <small class="text-secondary">Menampilkan <?= (($pagination['page'] - 1) * $pagination['perPage']) + 1 ?>–<?= min($pagination['page'] * $pagination['perPage'], $pagination['totalItems']) ?> dari <?= $pagination['totalItems'] ?> mutasi</small>
                <?php if ($pagination['totalPages'] > 1): ?>
                    <nav aria-label="Halaman riwayat stok"><ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $pagination['page'] === 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= esc($historyPageUrl($pagination['page'] - 1), 'attr') ?>" <?= $pagination['page'] === 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?> hx-get="<?= esc($historyPanelUrl($pagination['page'] - 1), 'attr') ?>" hx-target="#ops-panel" hx-swap="innerHTML" hx-push-url="<?= esc($historyPageUrl($pagination['page'] - 1), 'attr') ?>">Sebelumnya</a></li>
                        <?php for ($pageNumber = 1; $pageNumber <= $pagination['totalPages']; $pageNumber++): ?>
                            <li class="page-item <?= $pageNumber === $pagination['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= esc($historyPageUrl($pageNumber), 'attr') ?>" hx-get="<?= esc($historyPanelUrl($pageNumber), 'attr') ?>" hx-target="#ops-panel" hx-swap="innerHTML" hx-push-url="<?= esc($historyPageUrl($pageNumber), 'attr') ?>"><?= $pageNumber ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $pagination['page'] === $pagination['totalPages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= esc($historyPageUrl($pagination['page'] + 1), 'attr') ?>" <?= $pagination['page'] === $pagination['totalPages'] ? 'tabindex="-1" aria-disabled="true"' : '' ?> hx-get="<?= esc($historyPanelUrl($pagination['page'] + 1), 'attr') ?>" hx-target="#ops-panel" hx-swap="innerHTML" hx-push-url="<?= esc($historyPageUrl($pagination['page'] + 1), 'attr') ?>">Berikutnya</a></li>
                    </ul></nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if ($inventorySection === 'amenities' && $lowStockVendors !== []): ?>
        <div class="card border-warning shadow-sm mb-4"><div class="card-body p-4"><div class="d-flex flex-column flex-lg-row justify-content-between gap-3"><div><h3 class="h5 mb-1">Stok amenities perlu dipesan</h3><p class="text-secondary mb-0">Item dikelompokkan per vendor agar cukup satu pesan WhatsApp untuk beberapa produk.</p></div><span class="badge text-bg-warning align-self-lg-start"><?= count($lowStockVendors) ?> vendor</span></div><div class="row g-3 mt-1"><?php foreach ($lowStockVendors as $vendorGroup): ?><?php $stockLines = array_map(static fn (array $item): string => '- ' . $item['name'] . ': ' . $item['current_stock'] . ' ' . $item['unit'] . ' (minimum ' . $item['min_threshold'] . ' ' . $item['unit'] . ')', $vendorGroup['items']); $message = "Halo " . $vendorGroup['vendor_name'] . ",\n\nKami dari HomiQ PMS ingin melakukan pemesanan ulang karena stok amenities berikut sudah mencapai batas minimum:\n" . implode("\n", $stockLines) . "\n\nMohon info ketersediaan, harga, dan estimasi pengiriman. Terima kasih."; $waLink = $vendorGroup['whatsapp'] === null ? null : 'https://wa.me/' . $vendorGroup['whatsapp'] . '?text=' . rawurlencode($message); ?><div class="col-lg-6"><div class="border rounded-3 p-3 h-100 bg-light-subtle"><div class="fw-semibold mb-2"><?= esc($vendorGroup['vendor_name']) ?></div><ul class="small mb-3 ps-3"><?php foreach ($vendorGroup['items'] as $item): ?><li><?= esc($item['name'] . ' — ' . $item['current_stock'] . ' ' . $item['unit'] . ' / min. ' . $item['min_threshold'] . ' ' . $item['unit']) ?></li><?php endforeach; ?></ul><?php if ($waLink !== null): ?><a class="btn btn-success btn-sm" href="<?= esc($waLink) ?>" target="_blank" rel="noopener noreferrer">Hubungi via WhatsApp</a><?php else: ?><span class="small text-danger">Nomor WhatsApp belum diisi. Tambahkan pada baris item di bawah.</span><?php endif; ?></div></div><?php endforeach; ?></div></div></div>
    <?php endif; ?>

    <?php if ($inventorySection !== 'amenities'): ?>
    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
        <h3 class="h5 mb-3">Tambah <?= esc($current['label']) ?></h3>
        <form class="row g-3" hx-post="<?= site_url('ops/inventory') ?>" hx-target="#ops-feedback" hx-swap="innerHTML">
            <?= csrf_field() ?><input type="hidden" name="item_type" value="<?= esc((string) $current['itemType']) ?>">
            <div class="col-lg-3"><label class="form-label">Nama item</label><input class="form-control" name="name" required maxlength="150"></div>
            <div class="col-lg-2"><label class="form-label">Kategori</label><input class="form-control" name="category" required maxlength="100"></div>
            <div class="col-lg-2"><label class="form-label">Stok awal</label><input class="form-control" type="number" name="current_stock" min="0" value="0" required></div>
            <div class="col-lg-2"><label class="form-label">Batas minimum</label><input class="form-control" type="number" name="min_threshold" min="0" value="10" required></div>
            <div class="col-lg-1"><label class="form-label">Unit</label><input class="form-control" name="unit" value="pcs" maxlength="30"></div>
            <div class="col-lg-2"><label class="form-label">Harga/unit</label><input class="form-control" type="text" inputmode="numeric" data-currency-input name="unit_cost" value="0"></div>
            <div class="col-lg-3"><label class="form-label">Vendor</label><input class="form-control" name="contact_vendor" maxlength="150"></div>
            <div class="col-lg-3"><label class="form-label">WhatsApp vendor <span class="text-secondary">(opsional)</span></label><input class="form-control" type="tel" name="vendor_whatsapp" inputmode="numeric" maxlength="20" placeholder="0812xxxx atau 62812xxxx"></div>
            <div class="col-lg-3"><label class="form-label">SKU <span class="text-secondary">(opsional)</span></label><input class="form-control" name="sku" maxlength="100"></div>
            <div class="col-lg-3 d-flex align-items-end justify-content-end"><button class="btn btn-primary text-nowrap">Tambah <?= esc($current['label']) ?></button></div>
        </form>
    </div></div>
    <?php else: ?>
    <div class="alert alert-info border-0 shadow-sm mb-4">Daftar amenities sudah disediakan oleh sistem. Anda cukup memperbarui vendor, kontak WhatsApp, jumlah stok, dan batas minimum pada setiap item.</div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm"><div class="card-header bg-white py-3 d-flex justify-content-between align-items-center"><h3 class="h6 mb-0">Daftar <?= esc($current['label']) ?></h3><span class="badge text-bg-light border text-secondary"><?= count($items) ?> item</span></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><?php if ($inventorySection === 'amenities'): ?><tr><th>Item</th><th>Vendor</th><th>Kontak WhatsApp</th><th style="min-width:130px">Jumlah stok</th><th style="min-width:150px">Batas minimum</th><th>Status</th><th>Aksi</th></tr><?php else: ?><tr><th>Item</th><th>Kategori</th><th>Vendor</th><th class="text-end">Harga/unit</th><th>Batas minimum</th><th>Status</th><th style="min-width:220px">Stok</th></tr><?php endif; ?></thead><tbody>
        <?php if ($items === []): ?><tr><td colspan="7" class="text-center text-secondary py-5">Belum ada <?= esc(strtolower($current['label'])) ?>.</td></tr><?php endif; ?>
        <?php foreach ($items as $item): ?><?php $lowStock = (int) $item['current_stock'] <= (int) $item['min_threshold']; ?><?php if ($inventorySection === 'amenities'): ?><?php $formId = 'amenity-form-' . $item['id']; ?><tr><td class="fw-semibold"><?= esc($item['name']) ?><small class="d-block text-secondary">Kategori: Amenities</small></td><td><input form="<?= esc($formId) ?>" class="form-control form-control-sm" name="contact_vendor" maxlength="150" value="<?= esc($item['contact_vendor'] ?? '') ?>" placeholder="Nama vendor"></td><td><input form="<?= esc($formId) ?>" class="form-control form-control-sm" type="tel" name="vendor_whatsapp" inputmode="numeric" maxlength="20" value="<?= esc($item['vendor_whatsapp'] ?? '') ?>" placeholder="0812xxxx"></td><td><div class="input-group input-group-sm"><input form="<?= esc($formId) ?>" class="form-control" type="number" name="current_stock" min="0" value="<?= esc($item['current_stock']) ?>"><span class="input-group-text"><?= esc($item['unit']) ?></span></div></td><td><div class="input-group input-group-sm"><input form="<?= esc($formId) ?>" class="form-control" type="number" name="min_threshold" min="0" value="<?= esc($item['min_threshold']) ?>"><span class="input-group-text"><?= esc($item['unit']) ?></span></div></td><td><span class="badge <?= $lowStock ? 'text-bg-danger' : 'text-bg-success' ?>"><?= $lowStock ? 'Low Stock' : 'Aman' ?></span></td><td><form id="<?= esc($formId) ?>" hx-post="<?= site_url('ops/inventory/amenities/' . $item['id']) ?>" hx-target="#ops-feedback"><?= csrf_field() ?><button class="btn btn-sm btn-primary text-nowrap">Simpan</button></form></td></tr><?php else: ?><tr><td class="fw-semibold"><?= esc($item['name']) ?><?php if (! empty($item['sku'])): ?><small class="d-block text-secondary">SKU: <?= esc($item['sku']) ?></small><?php endif; ?></td><td><?= esc($item['category']) ?></td><td><div><?= esc($item['contact_vendor'] ?? 'Vendor belum diisi') ?></div></td><td class="text-end"><?= $money($item['unit_cost']) ?></td><td><?= esc($item['min_threshold'] . ' ' . $item['unit']) ?></td><td><span class="badge <?= $lowStock ? 'text-bg-danger' : 'text-bg-success' ?>"><?= $lowStock ? 'Low Stock' : 'Aman' ?></span></td><td><form class="d-flex gap-2" hx-post="<?= site_url('ops/inventory/' . $item['id'] . '/stock') ?>" hx-target="#ops-feedback"><?= csrf_field() ?><input class="form-control form-control-sm" type="number" name="current_stock" min="0" value="<?= esc($item['current_stock']) ?>"><span class="align-self-center small text-secondary"><?= esc($item['unit']) ?></span><button class="btn btn-sm btn-outline-primary">Update</button></form></td></tr><?php endif; ?><?php endforeach; ?>
    </tbody></table></div></div>
<?php endif; ?>
