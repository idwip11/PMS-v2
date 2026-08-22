<?php
$money = static fn ($value): string => 'Rp' . number_format((float) $value, 0, ',', '.');
$statusClass = static fn (string $status): string => match ($status) {
    'Resolved', 'Approved', 'Completed' => 'text-bg-success',
    'In_Progress' => 'text-bg-primary',
    'Open', 'Pending' => 'text-bg-warning',
    default => 'text-bg-secondary',
};
?>

<?php if ($tab === 'overview'): ?>
    <?php
    $overviewMoney = static fn ($value): string => 'Rp' . number_format((float) $value, 0, ',', '.');
    $budgetPercent = $overview['budget'] > 0 ? min(100, ($overview['actual'] / $overview['budget']) * 100) : 0;
    ?>
    <form class="d-flex flex-column flex-sm-row justify-content-end gap-2 mb-4" hx-get="<?= site_url('ops/panel') ?>" hx-target="#ops-panel" hx-swap="innerHTML">
        <input type="hidden" name="tab" value="overview">
        <select class="form-select ops-overview-filter" name="guesthouse" aria-label="Filter Guesthouse">
            <option value="0">Semua GH</option>
            <?php for ($guesthouse = 1; $guesthouse <= 5; $guesthouse++): ?><option value="<?= $guesthouse ?>" <?= (int) $overview['guesthouse'] === $guesthouse ? 'selected' : '' ?>>GH <?= $guesthouse ?></option><?php endfor; ?>
        </select>
        <input class="form-control ops-overview-filter" type="month" name="month" value="<?= esc($overview['period']) ?>" aria-label="Pilih bulan">
        <button class="btn btn-primary">Tampilkan</button>
    </form>
    <div class="ops-overview-grid mb-4">
        <div class="card border-0 shadow-sm ops-overview-card ops-opex-card"><div class="card-body"><div class="d-flex justify-content-between"><span class="ops-overview-icon">Rp</span><span class="small"><?= esc($overview['periodLabel']) ?></span></div><small>Monthly Opex<?= (int) $overview['guesthouse'] > 0 ? ' · GH ' . (int) $overview['guesthouse'] : '' ?></small><div class="h3 mb-2"><?= $overviewMoney($overview['actual']) ?></div><div class="d-flex justify-content-between small"><span>Budget semua GH: <?= $overviewMoney($overview['budget']) ?></span><span><?= number_format($budgetPercent, 0) ?>% terpakai</span></div><div class="progress mt-2" style="height:6px"><div class="progress-bar" style="width:<?= $budgetPercent ?>%"></div></div></div></div>
        <div class="card border-0 shadow-sm ops-overview-card ops-stock-card"><div class="card-body"><div class="d-flex justify-content-between"><span class="ops-overview-icon">!</span><span class="small">Perlu perhatian</span></div><small>Inventory Alerts</small><div class="h3 mb-1"><?= count($overview['lowStockItems']) ?></div><span class="small">Item di bawah batas minimum</span></div></div>
        <div class="card border-0 shadow-sm ops-overview-card ops-maintenance-card"><div class="card-body"><div class="d-flex justify-content-between"><span class="ops-overview-icon">⚒</span><span class="small">Aktif</span></div><small>Maintenance</small><div class="h3 mb-1"><?= (int) $overview['maintenanceRooms'] ?></div><span class="small"><?= (int) $overview['urgentTickets'] ?> tiket urgent</span></div></div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-xl-8"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><h3 class="h5 mb-1">Projected vs Actual Operasional</h3><p class="text-secondary small mb-0">Enam bulan hingga <?= esc($overview['periodLabel']) ?><?= (int) $overview['guesthouse'] > 0 ? ' untuk GH ' . (int) $overview['guesthouse'] : '' ?>.</p></div><button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#ops-budget-form">Atur Budget</button></div><div class="collapse mb-3" id="ops-budget-form"><form class="row g-2" hx-post="<?= site_url('ops/budgets') ?>" hx-target="#ops-feedback" hx-swap="innerHTML"><?= csrf_field() ?><div class="col-3"><label class="form-label small">Tahun</label><input class="form-control" type="number" name="year" value="<?= $overview['year'] ?>" min="2001" max="2099" required></div><div class="col-3"><label class="form-label small">Bulan</label><select class="form-select" name="month"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>" <?= $m === $overview['month'] ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option><?php endfor; ?></select></div><div class="col-4"><label class="form-label small">Budget</label><input class="form-control" type="text" inputmode="numeric" data-currency-input name="projected_amount" required></div><div class="col-2 d-flex align-items-end"><button class="btn btn-primary w-100">Simpan</button></div></form></div><div class="ops-budget-chart-wrap"><canvas id="opsBudgetChart" data-labels='<?= esc(json_encode($overview['chart']['labels']), 'attr') ?>' data-budgets='<?= esc(json_encode($overview['chart']['budgets']), 'attr') ?>' data-actuals='<?= esc(json_encode($overview['chart']['actuals']), 'attr') ?>'></canvas></div></div></div></div>
        <div class="col-xl-4"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-3"><h3 class="h5 mb-0">Inventory Alerts</h3><a class="small text-primary text-decoration-none" href="<?= site_url('ops?tab=inventory') ?>">Kelola</a></div><?php if ($overview['lowStockItems'] === []): ?><div class="text-center text-secondary py-4">Semua stok aman.</div><?php else: ?><div class="ops-alert-list"><?php foreach (array_slice($overview['lowStockItems'], 0, 5) as $item): ?><div class="ops-alert-item"><span class="ops-alert-icon">▣</span><div class="min-width-0"><div class="fw-semibold text-truncate"><?= esc($item['name']) ?></div><small class="text-secondary"><?= (int) $item['current_stock'] ?> <?= esc($item['unit']) ?> tersisa · min <?= (int) $item['min_threshold'] ?></small></div></div><?php endforeach; ?></div><?php endif; ?></div></div></div>
    </div>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="mb-3"><h3 class="h5 mb-1">Pengeluaran per Kategori</h3><p class="text-secondary small mb-1">Distribusi expense <?= esc($overview['periodLabel']) ?><?= (int) $overview['guesthouse'] > 0 ? ' · GH ' . (int) $overview['guesthouse'] : ' · semua GH' ?>.</p><p class="text-secondary small mb-0">Catatan: Fee Kerjasama merupakan pengeluaran perusahaan dan tidak masuk perhitungan pengeluaran owner/GH.</p></div>
            <?php if (($overview['expenseCategories'] ?? []) === []): ?>
                <div class="text-center text-secondary py-5">Belum ada pengeluaran pada bulan ini.</div>
            <?php else: ?>
                <?php $categoryDetailUrls = array_map(static fn (array $category): string => site_url('ops?' . http_build_query(['tab' => 'expenses', 'category' => $category['name'], 'guesthouse' => (int) $overview['guesthouse'] > 0 ? (string) $overview['guesthouse'] : 'all', 'month' => $overview['period']])), $overview['expenseCategories']); ?>
                <div class="row align-items-center g-4">
                    <div class="col-lg-5"><div class="ops-category-chart-wrap"><canvas id="opsExpenseCategoryChart" data-labels='<?= esc(json_encode(array_column($overview['expenseCategories'], 'name')), 'attr') ?>' data-values='<?= esc(json_encode(array_column($overview['expenseCategories'], 'amount')), 'attr') ?>' data-detail-urls='<?= esc(json_encode($categoryDetailUrls), 'attr') ?>' aria-label="Klik bagian grafik untuk melihat detail kategori"></canvas></div></div>
                    <div class="col-lg-7"><div class="ops-category-list"><?php foreach ($overview['expenseCategories'] as $index => $category): ?><?php $percent = $overview['actual'] > 0 ? ($category['amount'] / $overview['actual']) * 100 : 0; ?><a class="ops-category-item ops-category-link text-reset text-decoration-none" href="<?= esc($categoryDetailUrls[$index], 'attr') ?>"><span class="ops-category-dot" data-category-color="<?= $index ?>"></span><div class="min-width-0 flex-grow-1"><div class="fw-semibold text-truncate"><?= esc($category['name']) ?></div><small class="text-secondary"><?= $overviewMoney($category['amount']) ?></small></div><strong><?= number_format($percent, 1, ',', '.') ?>%</strong></a><?php endforeach; ?></div></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card border-0 shadow-sm"><div class="card-header bg-white py-3 d-flex justify-content-between align-items-center"><h3 class="h6 mb-0">Recent Operational Expenses</h3><a class="small text-primary text-decoration-none" href="<?= site_url('ops?tab=expenses') ?>">Lihat semua</a></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Tanggal</th><th>Deskripsi</th><th>Kategori</th><th>Guesthouse</th><th>Dicatat oleh</th><th class="text-end">Nominal</th></tr></thead><tbody><?php if ($overview['recentExpenses'] === []): ?><tr><td colspan="6" class="text-center text-secondary py-4">Belum ada expense.</td></tr><?php endif; ?><?php foreach ($overview['recentExpenses'] as $item): ?><tr><td><?= esc($item['date_incurred']) ?></td><td><?= esc($item['description']) ?></td><td><?= esc($item['category']) ?></td><td><?= esc($item['guesthouse_labels'] ?? 'Umum') ?></td><td><?= esc($item['logged_by'] ?? '-') ?></td><td class="text-end fw-semibold text-danger"><?= $overviewMoney($item['amount']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <div class="card border-0 shadow-sm mt-4"><div class="card-header bg-white py-3 d-flex justify-content-between align-items-center"><div><h3 class="h6 mb-1">Room Maintenance Status</h3><small class="text-secondary">Ringkasan kamar Maintenance. Kelola detail pekerjaan dari submenu Maintenance.</small></div><a class="btn btn-sm btn-outline-primary" href="<?= site_url('ops?tab=maintenance&section=tickets') ?>">Buka Maintenance</a></div><div class="table-responsive"><table class="table align-middle mb-0 ops-room-maintenance-table"><thead><tr><th>Kamar</th><th>Issue</th><th>Priority</th><th>Notes</th><th>Status Tiket</th><th class="text-end">Aksi</th></tr></thead><tbody><?php if ($overview['roomMaintenance'] === []): ?><tr><td colspan="6" class="text-center text-secondary py-4">Tidak ada kamar yang sedang maintenance.</td></tr><?php endif; ?><?php foreach ($overview['roomMaintenance'] as $room): ?><?php $maintenanceTicket = $room['maintenance_ticket'] ?? null; ?><tr><td class="fw-semibold">Room <?= esc($room['room_number']) ?></td><td><?= esc($maintenanceTicket['description'] ?? 'Belum ada issue. Lengkapi di Maintenance.') ?></td><td><span class="badge <?= in_array($maintenanceTicket['priority'] ?? '', ['High', 'Urgent'], true) ? 'text-bg-danger' : 'text-bg-secondary' ?>"><?= esc($maintenanceTicket['priority'] ?? 'Medium') ?></span></td><td><?= esc($maintenanceTicket['follow_up_notes'] ?? '-') ?></td><td><?= esc(str_replace('_', ' ', $maintenanceTicket['status'] ?? 'Belum dibuat')) ?></td><td class="text-end"><?php if ($maintenanceTicket !== null): ?><button class="btn btn-sm btn-outline-primary" hx-get="<?= site_url('ops/maintenance/work-orders/' . $maintenanceTicket['id']) ?>" hx-target="#modal-container" hx-swap="innerHTML">Detail</button><?php else: ?><a class="btn btn-sm btn-outline-primary" href="<?= site_url('ops?tab=maintenance&section=tickets') ?>">Buka Maintenance</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>

<?php elseif ($tab === 'expenses'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h3 class="h5 mb-3">Catat Expense</h3>
            <form class="row g-3" data-expense-form data-image-upload-form enctype="multipart/form-data" hx-encoding="multipart/form-data" hx-post="<?= site_url('ops/expenses') ?>" hx-target="#ops-feedback" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <div class="col-md-4"><label class="form-label">Deskripsi</label><input class="form-control" name="description" required maxlength="255"></div>
                <div class="col-md-2"><label class="form-label">Kategori</label><select class="form-select" name="category" required data-expense-category><option value="" selected disabled>Pilih kategori</option><?php foreach ($expenseCategories as $category): ?><option value="<?= esc($category) ?>"><?= esc($category) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Nominal</label><input class="form-control" type="text" inputmode="numeric" data-currency-input name="amount" required></div>
                <div class="col-md-2"><label class="form-label">Tanggal</label><input class="form-control" type="date" name="date_incurred" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-md-2"><label class="form-label">Guesthouse <span class="text-secondary">(opsional)</span></label><select class="form-select" name="guesthouses[]" multiple size="6" aria-label="Pilih satu atau beberapa Guesthouse" data-expense-guesthouses><?php for ($guesthouse = 1; $guesthouse <= 5; $guesthouse++): ?><option value="<?= $guesthouse ?>">GH <?= $guesthouse ?></option><?php endfor; ?><option value="0">Perusahaan</option></select><div class="form-text" data-expense-guesthouse-help>Pilih lebih dari satu dengan Cmd/Ctrl + klik.</div></div>
                <div class="col-md-10"><label class="form-label">Catatan</label><input class="form-control" name="notes" placeholder="Opsional"></div>
                <div class="col-md-10"><label class="form-label">Bukti invoice <span class="text-secondary">(opsional)</span></label><input class="form-control" type="file" name="receipt" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" data-image-file-input><div class="form-text" data-image-compression-status>Foto JPG, PNG, atau WEBP akan dikompres maksimal 200 KB.</div></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Simpan</button></div>
            </form>
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3"><h3 class="h6 mb-3">Riwayat Expense</h3><form class="row g-2 align-items-end" method="get" action="<?= site_url('ops') ?>"><input type="hidden" name="tab" value="expenses"><div class="col-md-4"><label class="form-label small mb-1">Kategori</label><select class="form-select" name="category"><option value="">Semua kategori</option><?php foreach ($expenseCategories as $category): ?><option value="<?= esc($category) ?>" <?= ($expenseHistoryFilters['category'] ?? '') === $category ? 'selected' : '' ?>><?= esc($category) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label small mb-1">Guesthouse</label><select class="form-select" name="guesthouse"><option value="all" <?= ($expenseHistoryFilters['guesthouse'] ?? 'all') === 'all' ? 'selected' : '' ?>>Semua guesthouse</option><?php for ($guesthouse = 1; $guesthouse <= 5; $guesthouse++): ?><option value="<?= $guesthouse ?>" <?= ($expenseHistoryFilters['guesthouse'] ?? '') === (string) $guesthouse ? 'selected' : '' ?>>GH <?= $guesthouse ?></option><?php endfor; ?><option value="company" <?= ($expenseHistoryFilters['guesthouse'] ?? '') === 'company' ? 'selected' : '' ?>>Perusahaan</option></select></div><div class="col-md-3"><label class="form-label small mb-1">Periode</label><input class="form-control" type="month" name="month" value="<?= esc($expenseHistoryFilters['month'] ?? '') ?>"></div><div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1">Tampilkan</button><a class="btn btn-outline-secondary" href="<?= site_url('ops?tab=expenses') ?>">Reset</a></div></form></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Tanggal</th><th>Deskripsi</th><th>Kategori</th><th>Guesthouse</th><th>Dicatat oleh</th><th>Bukti</th><th>Status</th><th class="text-end">Nominal</th><th class="text-end">Aksi</th></tr></thead><tbody>
            <?php if ($items === []): ?><tr><td colspan="9" class="text-center text-secondary py-5">Belum ada expense.</td></tr><?php endif; ?>
            <?php foreach ($items as $item): ?><?php $isMaintenanceExpense = str_contains((string) ($item['notes'] ?? ''), '[Maintenance Work Order:'); ?><tr><td><?= esc($item['date_incurred']) ?></td><td><?= esc($item['description']) ?></td><td><?= esc($item['category']) ?></td><td><?= esc($item['guesthouse_labels'] ?? 'Umum') ?></td><td><?= esc($item['logged_by'] ?? '-') ?></td><td><?php if (! empty($item['receipt_url'])): ?><a class="btn btn-sm btn-outline-primary" href="<?= site_url('ops/expenses/' . $item['id'] . '/receipt') ?>" target="_blank" rel="noopener">Lihat bukti</a><?php else: ?><span class="text-secondary small">-</span><?php endif; ?></td><td><span class="badge <?= $statusClass($item['status']) ?>"><?= esc($item['status']) ?></span></td><td class="text-end fw-semibold text-danger"><?= $money($item['amount']) ?></td><td class="text-end"><?php if ($isMaintenanceExpense): ?><span class="small text-secondary">Dikelola dari Maintenance</span><?php else: ?><div class="d-inline-flex gap-2"><button class="btn btn-sm btn-outline-primary" hx-get="<?= site_url('ops/expenses/' . $item['id'] . '/edit') ?>" hx-target="#modal-container" hx-swap="innerHTML">Edit</button><form hx-post="<?= site_url('ops/expenses/' . $item['id'] . '/delete') ?>" hx-target="#ops-feedback" hx-swap="innerHTML" hx-confirm="Hapus expense ini secara permanen? Tindakan ini tidak dapat dibatalkan."><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form></div><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if ($pagination['totalItems'] > 0): ?>
            <div class="card-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 py-3">
                <small class="text-secondary">Menampilkan <?= (($pagination['page'] - 1) * $pagination['perPage']) + 1 ?>–<?= min($pagination['page'] * $pagination['perPage'], $pagination['totalItems']) ?> dari <?= $pagination['totalItems'] ?> expense</small>
                <?php if ($pagination['totalPages'] > 1): ?>
                    <?php $expensePageUrl = static fn (int $page): string => site_url('ops/panel?' . http_build_query(['tab' => 'expenses', 'page' => $page, 'category' => $expenseHistoryFilters['category'] ?? '', 'guesthouse' => $expenseHistoryFilters['guesthouse'] ?? 'all', 'month' => $expenseHistoryFilters['month'] ?? ''])); ?>
                    <?php $expensePageHref = static fn (int $page): string => site_url('ops?' . http_build_query(['tab' => 'expenses', 'page' => $page, 'category' => $expenseHistoryFilters['category'] ?? '', 'guesthouse' => $expenseHistoryFilters['guesthouse'] ?? 'all', 'month' => $expenseHistoryFilters['month'] ?? ''])); ?>
                    <nav aria-label="Halaman riwayat expense"><ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $pagination['page'] === 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= esc($expensePageHref($pagination['page'] - 1), 'attr') ?>" <?= $pagination['page'] === 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?> hx-get="<?= esc($expensePageUrl($pagination['page'] - 1), 'attr') ?>" hx-target="#ops-panel" hx-swap="innerHTML" hx-push-url="<?= esc($expensePageHref($pagination['page'] - 1), 'attr') ?>">Sebelumnya</a></li>
                        <?php for ($pageNumber = 1; $pageNumber <= $pagination['totalPages']; $pageNumber++): ?>
                            <li class="page-item <?= $pageNumber === $pagination['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= esc($expensePageHref($pageNumber), 'attr') ?>" hx-get="<?= esc($expensePageUrl($pageNumber), 'attr') ?>" hx-target="#ops-panel" hx-swap="innerHTML" hx-push-url="<?= esc($expensePageHref($pageNumber), 'attr') ?>"><?= $pageNumber ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $pagination['page'] === $pagination['totalPages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= esc($expensePageHref($pagination['page'] + 1), 'attr') ?>" <?= $pagination['page'] === $pagination['totalPages'] ? 'tabindex="-1" aria-disabled="true"' : '' ?> hx-get="<?= esc($expensePageUrl($pagination['page'] + 1), 'attr') ?>" hx-target="#ops-panel" hx-swap="innerHTML" hx-push-url="<?= esc($expensePageHref($pagination['page'] + 1), 'attr') ?>">Berikutnya</a></li>
                    </ul></nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'inventory'): ?>
    <?= view('components/inventory_panel', [
        'items' => $items,
        'inventorySection' => $inventorySection,
        'lowStockVendors' => $lowStockVendors ?? [],
        'pagination' => $pagination,
    ]) ?>

<?php elseif ($tab === 'extra_beds'): ?>
    <?= view('components/extra_bed_panel', [
        'extraBedDate' => $extraBedDate,
        'extraBedAvailability' => $extraBedAvailability,
        'extraBedSettings' => $extraBedSettings,
        'extraBedAllocations' => $extraBedAllocations,
        'extraBedUpcoming' => $extraBedUpcoming,
    ]) ?>

<?php elseif ($tab === 'maintenance'): ?>
    <?= view('components/maintenance/workspace', [
        'section' => $maintenanceSection,
        'items' => $items,
        'rooms' => $rooms,
        'categories' => $categories,
        'roomAssets' => $roomAssets,
        'users' => $users,
        'historyFilters' => $historyFilters,
        'maintenanceSummary' => $maintenanceSummary,
        'scheduleStats' => $scheduleStats,
        'selectedRoomId' => $selectedRoomId ?? 0,
    ]) ?>

<?php else: ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h3 class="h5 mb-3">Jadwalkan Shift</h3>
            <form class="row g-3" hx-post="<?= site_url('ops/shifts') ?>" hx-target="#ops-feedback" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <div class="col-md-3"><label class="form-label">Staf</label><select class="form-select" name="user_id" required><option value="">Pilih staf</option><?php foreach ($users as $user): ?><option value="<?= $user['id'] ?>"><?= esc($user['full_name'] . ' · ' . $user['role']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Tanggal</label><input class="form-control" type="date" name="shift_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-md-2"><label class="form-label">Mulai</label><input class="form-control" type="time" name="start_time" value="08:00" required></div>
                <div class="col-md-2"><label class="form-label">Selesai</label><input class="form-control" type="time" name="end_time" value="16:00" required></div>
                <div class="col-md-2"><label class="form-label">Tipe</label><select class="form-select" name="shift_type"><option>Morning</option><option>Afternoon</option><option>Night</option></select></div>
                <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100">Simpan</button></div>
            </form>
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3"><h3 class="h6 mb-0">Jadwal Shift</h3></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Tanggal</th><th>Staf</th><th>Tipe</th><th>Jam</th><th>Status</th></tr></thead><tbody>
            <?php if ($items === []): ?><tr><td colspan="5" class="text-center text-secondary py-5">Belum ada jadwal shift.</td></tr><?php endif; ?>
            <?php foreach ($items as $item): ?><tr><td><?= esc($item['shift_date']) ?></td><td class="fw-semibold"><?= esc($item['full_name']) ?></td><td><?= esc($item['shift_type']) ?></td><td><?= esc(substr($item['start_time'], 0, 5) . '–' . substr($item['end_time'], 0, 5)) ?></td><td><span class="badge text-bg-primary"><?= esc($item['status']) ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
<?php endif; ?>
