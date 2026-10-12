<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'HomiQ PMS') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= base_url('images/homiq-icon.svg') ?>?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('images/homiq-icon-32.png') ?>?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= base_url('images/homiq-icon-16.png') ?>?v=2">
    <link rel="apple-touch-icon" href="<?= base_url('images/homiq-icon.png') ?>?v=2">
    <link rel="shortcut icon" href="<?= base_url('favicon.ico') ?>?v=2">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= base_url('css/app.css') ?>" rel="stylesheet">
</head>
<body class="auth-page">
    <?= $this->renderSection('content') ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
