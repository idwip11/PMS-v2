<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
use App\Libraries\PropertyContext;
$isHomiq = PropertyContext::activeId() === PropertyContext::HOMIQ_ID;
$propertyLabel = $isHomiq ? 'Guesthouse' : 'Properti';
$editing = $editingSetup !== null;
?>
<?php if (! empty($isCombined)): ?>
<?= $this->include('components/mitra_owner_setup') ?>
<?php else: ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3"><h3 class="h5 mb-0"><?= $editing ? 'Ubah Setup Owner' : 'Tambah Setup Owner' ?></h3><?php if ($editing): ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('finance/owner-setup') ?>">Batal Ubah</a><?php endif; ?></div>
        <form class="row g-3" method="post" action="<?= site_url('finance/owner-setup') ?>">
            <?= csrf_field() ?>
            <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editingSetup['id'] ?>"><?php endif; ?>
            <div class="col-md-5"><label class="form-label" for="owner-name">Nama owner</label><input id="owner-name" class="form-control" name="owner_name" maxlength="150" value="<?= esc($editingSetup['owner_name'] ?? '') ?>" required></div>
            <div class="col-md-3">
                <label class="form-label" for="owner-gh">Kepemilikan <?= $propertyLabel ?></label>
                <select id="owner-gh" class="form-select" name="guesthouse" required>
                    <?php if ($isHomiq): ?>
                        <?php for ($gh = 1; $gh <= 5; $gh++): ?>
                            <option value="<?= $gh ?>" <?= $editing && (int) $editingSetup['guesthouse'] === $gh ? 'selected' : '' ?>>GH <?= $gh ?></option>
                        <?php endfor; ?>
                    <?php else: ?>
                        <option value="0" <?= $editing && (int) $editingSetup['guesthouse'] === 0 ? 'selected' : '' ?>><?= esc((string) session('activePropertyName')) ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label" for="owner-fee">Bagi hasil owner (%)</label><input id="owner-fee" class="form-control" type="number" name="cooperation_fee_percentage" min="0" max="100" step="0.01" value="<?= esc($editingSetup['cooperation_fee_percentage'] ?? '') ?>" required></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100"><?= $editing ? 'Simpan Perubahan' : 'Simpan Setup' ?></button></div>
        </form>
        <p class="form-text mb-0 mt-3"><?= $isHomiq ? 'Persentase ini adalah jatah laba bersih untuk owner. Sisa persentasenya otomatis menjadi fee kerja sama pengelola.' : 'Khusus properti mitra, persentase ini adalah jatah dari omset (total pendapatan) untuk owner. Sisa persentasenya otomatis menjadi fee kerja sama pengelola.' ?></p>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3"><h3 class="h5 mb-0">Daftar Setup Owner</h3></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nama Owner</th><th>Kepemilikan <?= $propertyLabel ?></th><th class="text-end">Bagi Hasil Owner</th><th class="text-end">Aksi</th></tr></thead><tbody>
        <?php if ($setups === []): ?><tr><td colspan="4" class="text-center text-secondary py-5">Belum ada setup owner.</td></tr><?php endif; ?>
        <?php foreach ($setups as $setup): ?><tr><td><?= esc($setup['owner_name']) ?></td><td><?= (int) $setup['guesthouse'] === 0 ? esc((string) session('activePropertyName')) : 'GH ' . (int) $setup['guesthouse'] ?></td><td class="text-end fw-semibold"><?= number_format((float) $setup['cooperation_fee_percentage'], 2, ',', '.') ?>%</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('finance/owner-setup?edit=' . $setup['id']) ?>">Ubah</a> <form class="d-inline" method="post" action="<?= site_url('finance/owner-setup/' . $setup['id'] . '/delete') ?>" onsubmit="return confirm('Hapus setup owner untuk <?= (int) $setup['guesthouse'] === 0 ? esc((string) session('activePropertyName'), 'js') : 'GH ' . (int) $setup['guesthouse'] ?>?');"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>
