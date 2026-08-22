<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h3 class="h5 mb-3">Tambah User</h3>
        <form class="row g-3" hx-post="<?= site_url('settings/users') ?>" hx-target="#settings-feedback" hx-swap="innerHTML">
            <?= csrf_field() ?>
            <div class="col-md-3"><label class="form-label">Username</label><input class="form-control" name="username" required maxlength="100" autocomplete="off"></div>
            <div class="col-md-3"><label class="form-label">Nama lengkap</label><input class="form-control" name="full_name" required maxlength="150"></div>
            <div class="col-md-2"><label class="form-label">Role</label><select class="form-select" name="role" required><?php foreach ($roles as $role): ?><option value="<?= $role ?>"><?= esc($role) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Password awal</label><input class="form-control" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Tambah User</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3"><h3 class="h6 mb-0">Daftar User</h3></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Username</th><th style="min-width:220px">Nama</th><th style="width:190px">Role</th><th style="width:230px">Password Baru</th><th class="text-end" style="width:230px">Aksi</th></tr></thead><tbody>
        <?php if ($users === []): ?><tr><td colspan="5" class="text-center text-secondary py-5">Belum ada user.</td></tr><?php endif; ?>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><span class="fw-semibold"><?= esc($user['username']) ?></span><?php if ((int) session('userId') === (int) $user['id']): ?><span class="badge text-bg-primary ms-2">Anda</span><?php endif; ?></td>
                <td colspan="4">
                    <form class="row g-2 align-items-center" hx-post="<?= site_url('settings/users/' . $user['id']) ?>" hx-target="#settings-feedback">
                        <?= csrf_field() ?>
                        <div class="col-md-4"><input class="form-control form-control-sm" name="full_name" value="<?= esc($user['full_name']) ?>" required maxlength="150"></div>
                        <div class="col-md-3"><select class="form-select form-select-sm" name="role" required><?php foreach ($roles as $role): ?><option value="<?= $role ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= esc($role) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><input class="form-control form-control-sm" type="password" name="password" placeholder="Kosongkan jika tetap" minlength="8" autocomplete="new-password"></div>
                        <div class="col-md-2 d-flex justify-content-end gap-2"><button class="btn btn-sm btn-outline-primary">Update</button><button class="btn btn-sm btn-outline-danger" type="submit" form="delete-user-<?= $user['id'] ?>" <?= (int) session('userId') === (int) $user['id'] ? 'disabled title="Akun yang sedang digunakan tidak dapat dihapus"' : '' ?> onclick="return confirm('Hapus akun <?= esc($user['username'], 'js') ?>? Tindakan ini tidak dapat dibatalkan.')">Hapus</button></div>
                    </form>
                    <form id="delete-user-<?= $user['id'] ?>" hx-post="<?= site_url('settings/users/' . $user['id'] . '/delete') ?>" hx-target="#settings-feedback" hx-swap="innerHTML"><?= csrf_field() ?></form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody></table></div>
</div>
