<div class="alert alert-info">Cakupan: <strong>Homestay Mitra &amp; Kos Mitra</strong>. Setup yang disimpan berlaku untuk kedua properti.</div>
<?php if ($legacySetups !== []): ?>
    <div class="alert alert-warning">
        <strong>Konfigurasi lama masih disimpan sebagai referensi.</strong> Laporan gabungan memakai setup bersama di bawah ini.
        <?php foreach ($legacySetups as $legacy): ?>
            <div class="small mt-1"><?= (int) $legacy['property_id'] === 2 ? 'Homestay Mitra' : 'Kos Mitra' ?>: <?= esc($legacy['owner_name']) ?> · <?= number_format((float) $legacy['cooperation_fee_percentage'], 2, ',', '.') ?>%</div>
        <?php endforeach; ?>
        <?php if ($setups === []): ?><div class="mt-2">Isi nama dan persentase gabungan sesuai kesepakatan. Nilai lama tidak dipilih otomatis.</div><?php endif; ?>
    </div>
<?php endif; ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h3 class="h5 mb-3"><?= $editingSetup === null ? 'Setup Owner Mitra' : 'Ubah Setup Owner Mitra' ?></h3>
        <form class="row g-3" method="post" action="<?= site_url('finance/owner-setup') ?>">
            <?= csrf_field() ?>
            <?php if ($editingSetup !== null): ?><input type="hidden" name="id" value="<?= (int) $editingSetup['id'] ?>"><?php endif; ?>
            <div class="col-md-5"><label class="form-label" for="owner-name">Nama owner bersama</label><input id="owner-name" class="form-control" name="owner_name" maxlength="150" value="<?= esc(old('owner_name', $editingSetup['owner_name'] ?? '', false), 'attr') ?>" required></div>
            <div class="col-md-4"><div class="form-label">Kepemilikan</div><div class="form-control bg-body-secondary text-wrap h-auto" aria-label="Kepemilikan">Homestay Mitra &amp; Kos Mitra</div></div>
            <div class="col-md-3"><label class="form-label" for="owner-fee">Bagi hasil owner (%)</label><input id="owner-fee" class="form-control" type="number" name="cooperation_fee_percentage" min="0" max="100" step="0.01" value="<?= esc(old('cooperation_fee_percentage', $editingSetup['owner_share_percentage'] ?? '', false), 'attr') ?>" required></div>
            <div class="col-12"><button class="btn btn-primary"><?= $editingSetup === null ? 'Simpan Setup Bersama' : 'Simpan Perubahan' ?></button></div>
        </form>
        <p class="form-text mb-0 mt-3">Bagi hasil dihitung dari omzet gabungan yang menjadi hak owner. Extra Bed Homestay dan Kos termasuk; layanan langsung pengelola dikecualikan. Biaya operasional tidak mengurangi dasar pembagian. Persentase pengelola = 100% − persentase owner.</p>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3"><h3 class="h5 mb-0">Setup Owner Bersama</h3></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nama Owner</th><th>Cakupan</th><th class="text-end">Owner</th><th class="text-end">Pengelola</th><th class="text-end">Aksi</th></tr></thead><tbody>
        <?php if ($setups === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">Setup bersama belum diisi. Bagi hasil belum dapat dihitung.</td></tr><?php endif; ?>
        <?php foreach ($setups as $setup): ?>
            <tr><td><?= esc($setup['owner_name']) ?></td><td>Homestay Mitra &amp; Kos Mitra</td><td class="text-end text-nowrap"><?= number_format((float) $setup['owner_share_percentage'], 2, ',', '.') ?>%</td><td class="text-end text-nowrap"><?= number_format(100 - (float) $setup['owner_share_percentage'], 2, ',', '.') ?>%</td><td class="text-end"><form method="post" action="<?= site_url('finance/owner-setup/' . (int) $setup['id'] . '/delete') ?>" onsubmit="return confirm('Hapus setup bersama Homestay dan Kos Mitra? Bagi hasil memerlukan setup baru.');"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
</div>
