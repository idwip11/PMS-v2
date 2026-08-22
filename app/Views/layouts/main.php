<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-token-name" content="<?= csrf_token() ?>">
    <meta name="csrf-cookie-name" content="<?= config('Security')->cookieName ?>">
    <title><?= esc($title ?? 'HomiQ PMS') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= base_url('css/app.css') ?>?v=<?= filemtime(FCPATH . 'css/app.css') ?>" rel="stylesheet">
</head>
<body class="app-page">
    <!-- Sidebar overlay for mobile/tablet -->
    <div id="sidebar-overlay" class="sidebar-overlay" aria-hidden="true"></div>
    <div class="app-shell d-flex min-vh-100">
        <?= $this->include('components/sidebar') ?>
        <div class="flex-grow-1 min-width-0 d-flex flex-column">
            <?php if (! ($hideHeader ?? false)): ?>
                <?= $this->include('components/header') ?>
            <?php endif; ?>
            <main class="container-fluid p-4 p-lg-5 flex-grow-1">
                <?php if (session('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert"><?= esc(session('success')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if (session('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= esc(session('error')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?= $this->renderSection('content') ?>
            </main>
            <footer class="border-top bg-white px-4 px-lg-5 py-3 text-center text-secondary small">
                © HomiQ PMS by <a class="link-primary text-decoration-none" href="https://github.com/idwip11" target="_blank" rel="noopener noreferrer">idwip11</a>
            </footer>
        </div>
    </div>
    <div id="modal-container"></div>
    <script src="https://unpkg.com/htmx.org@2.0.4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= base_url('js/app.js') ?>?v=<?= filemtime(FCPATH . 'js/app.js') ?>"></script>
</body>
</html>
