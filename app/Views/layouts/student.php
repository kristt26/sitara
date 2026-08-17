<?php
$currentUser = session('auth');
$currentUser = is_array($currentUser) ? $currentUser : [];
$currentUserName = (string) ($currentUser['full_name'] ?? 'Mahasiswa');
$parts = preg_split('/\s+/', trim($currentUserName)) ?: [];
$initials = implode('', array_map(static fn (string $part): string => strtoupper(substr($part, 0, 1)), array_slice($parts, 0, 2)));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal mahasiswa SITARA">
    <title>SITARA | <?= esc($pageTitle ?? 'Portal Mahasiswa') ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/logo.png') ?>">
    <link rel="stylesheet" href="<?= base_url('css/volt.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/styles.css') ?>">
    <?= $this->renderSection('styles') ?>
</head>
<body class="student-portal-body">
    <header class="student-portal-header">
        <div class="container-fluid student-portal-container d-flex align-items-center justify-content-between gap-3">
            <a class="sitara-brand" href="<?= site_url('portal-mahasiswa') ?>">
                <img class="sitara-mark" src="<?= base_url('assets/img/logo.png') ?>" alt="Logo Universitas Sepuluh Nopember Papua">
                <span class="sitara-brand-name">SITARA<small>Portal Mahasiswa</small></span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-sm-block text-end"><strong class="d-block small text-white"><?= esc($currentUserName) ?></strong><small class="student-portal-role">Mahasiswa</small></div>
                <span class="avatar rounded-circle bg-white text-primary"><?= esc($initials !== '' ? $initials : 'MH') ?></span>
                <form action="<?= site_url('logout') ?>" method="post" class="m-0"><?= csrf_field() ?><button class="btn btn-sm btn-outline-light" type="submit">Keluar</button></form>
            </div>
        </div>
    </header>
    <main class="student-portal-main">
        <div class="container-fluid student-portal-container">
            <?php if ($message = session()->getFlashdata('success')): ?><div class="alert alert-success" role="alert"><?= esc($message) ?></div><?php endif; ?>
            <?php if ($message = session()->getFlashdata('error')): ?><div class="alert alert-danger" role="alert"><?= esc($message) ?></div><?php endif; ?>
            <?= $this->renderSection('content') ?>
        </div>
    </main>
    <footer class="student-portal-footer"><div class="container-fluid student-portal-container d-flex flex-column flex-sm-row justify-content-between gap-1"><span>&copy; <?= date('Y') ?> SITARA</span><span>Portal informasi akademik dan pembayaran mahasiswa</span></div></footer>
    <script src="<?= base_url('vendor/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
