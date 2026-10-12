<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<main class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
    <div class="card shadow-lg border-0 auth-card w-100">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <img src="<?= base_url('images/homiq-icon.svg') ?>" alt="HomiQ Logo" width="68" height="68" class="mb-3 d-inline-block">
                <h1 class="h3 mb-1">HomiQ PMS</h1>
                <p class="text-secondary mb-0">Property Management System</p>
            </div>
            <?php if (session('error')): ?>
                <div class="alert alert-danger"><?= esc(session('error')) ?></div>
            <?php endif; ?>
            <?php if (session('success')): ?>
                <div class="alert alert-success"><?= esc(session('success')) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= site_url('login') ?>" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control form-control-lg" id="username" name="username" value="<?= esc(old('username')) ?>" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control form-control-lg" id="password" name="password" type="password" required>
                </div>
                <button class="btn btn-primary btn-lg w-100" type="submit">Login to Dashboard</button>
            </form>
            <div class="text-center text-secondary small mt-4">
                © HomiQ PMS by <a class="link-secondary" href="https://github.com/idwip11" target="_blank" rel="noopener noreferrer">idwip11</a>
            </div>
        </div>
    </div>
</main>
<?= $this->endSection() ?>
