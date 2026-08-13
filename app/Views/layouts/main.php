<?php
$currentUser = session('auth');
$currentUser = is_array($currentUser) ? $currentUser : [];
$currentUserName = (string) ($currentUser['full_name'] ?? 'Administrator Keuangan');
$initials = preg_split('/\s+/', trim($currentUserName)) ?: [];
$initials = implode('', array_map(static fn(string $part): string => strtoupper(substr($part, 0, 1)), array_slice($initials, 0, 2)));
?>
<!doctype html>
<html lang="id" ng-app="apps" ng-controller="indexController">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SITARA - Sistem Informasi Tarif dan Pembayaran Akademik">
    <title>SITARA | <?= esc($pageTitle ?? 'Keuangan Akademik') ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/logo.png') ?>">
    <link rel="stylesheet" href="<?= base_url('vendor/notyf/notyf.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/volt.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/styles.css') ?>">
    <script src="<?= base_url() ?>libs/angular/angular.min.js"></script>
    <script src="<?= base_url() ?>libs/angular/angular-sanitize.min.js"></script>
    <script src="<?= base_url() ?>libs/angular-ui-router/release/angular-ui-router.min.js"></script>
    <script src="<?= base_url() ?>libs/angular/angular-animate.min.js"></script>
    <?= $this->renderSection('styles') ?>
</head>

<body>
    <nav class="navbar navbar-dark sitara-mobile-nav px-3 col-12 d-lg-none">
        <a class="sitara-brand" href="<?= site_url('/') ?>">
            <img class="sitara-mark" src="<?= base_url('assets/img/logo.png') ?>" alt="Logo Universitas Sepuluh Nopember Papua">
            <span class="sitara-brand-name">SITARA<small>Keuangan Akademik</small></span>
        </a>
        <button class="navbar-toggler collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Buka navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>
    </nav>

    <?= $this->include('layouts/menu') ?>

    <main class="content">
        <div class="container-fluid px-0">
            <header class="sitara-topbar">
                <div>
                    <div class="sitara-kicker">Sistem Informasi Tarif &amp; Pembayaran Akademik</div>
                    <div class="small text-gray-600 mt-1"><?= esc($pageSubtitle ?? 'Dashboard operasional keuangan') ?></div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="sitara-live"><?= ($live ?? false) ? 'Data tersambung' : 'Mode pratinjau' ?></span>
                    <div class="d-none d-sm-block text-end">
                        <div class="small fw-bold text-gray-700"><?= esc($currentUserName) ?></div>
                        <div class="small text-gray-500">Admin keuangan</div>
                    </div>
                    <span class="avatar rounded-circle bg-primary text-white"><?= esc($initials !== '' ? $initials : 'AD') ?></span>
                </div>
            </header>

            <?php if ($message = session()->getFlashdata('success')): ?>
                <div class="alert alert-success sitara-flash-message" role="alert"><?= esc($message) ?></div>
            <?php endif; ?>
            <?php if ($message = session()->getFlashdata('error')): ?>
                <div class="alert alert-danger sitara-flash-message" role="alert"><?= esc($message) ?></div>
            <?php endif; ?>

            <?= $this->renderSection('content') ?>

            <footer class="sitara-footer d-flex flex-column flex-sm-row gap-2 justify-content-between">
                <span>&copy; <?= date('Y') ?> SITARA - Sistem Informasi Tarif &amp; Pembayaran Akademik</span>
                <span>Dashboard staf keuangan</span>
            </footer>
        </div>
    </main>

    <script src="<?= base_url('vendor/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= base_url('vendor/simplebar/dist/simplebar.min.js') ?>"></script>
    <script src="<?= base_url() ?>js/apps.js"></script>
    <script src="<?= base_url() ?>js/services/helper.services.js"></script>
    <script src="<?= base_url() ?>js/services/admin.services.js"></script>
    <script src="<?= base_url() ?>js/services/pesan.services.js"></script>
    <script src="<?= base_url() ?>js/controllers/admin.controllers.js"></script>
    <?= $this->renderSection('scripts') ?>
</body>

</html>
