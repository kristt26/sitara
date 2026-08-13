<?php
$activeMenu = $activeMenu ?? 'dashboard';
$navGroups = [
    [
        'id' => 'dashboard',
        'title' => null,
        'items' => [
            ['key' => 'dashboard', 'route' => '', 'label' => 'Ringkasan'],
        ],
    ],
    [
        'id' => 'data-master',
        'title' => 'Data Master',
        'items' => [
            ['key' => 'periods', 'route' => 'periode', 'label' => 'Tahun & Periode'],
            ['key' => 'programs', 'route' => 'program-studi', 'label' => 'Program Studi'],
            ['key' => 'students', 'route' => 'master/mahasiswa', 'label' => 'Mahasiswa'],
            ['key' => 'lecturers', 'route' => 'master/dosen', 'label' => 'Dosen'],
            ['key' => 'activities', 'route' => 'kegiatan', 'label' => 'Jenis Kegiatan'],
            ['key' => 'activity-rules', 'route' => 'aturan-kegiatan', 'label' => 'Aturan Kegiatan'],
            ['key' => 'payment-methods', 'route' => 'keuangan/metode-pembayaran', 'label' => 'Metode Pembayaran'],
        ],
    ],
    [
        'id' => 'keuangan-mahasiswa',
        'title' => 'Keuangan Mahasiswa',
        'items' => [
            ['key' => 'student-activities', 'route' => 'kegiatan-mahasiswa', 'label' => 'Kegiatan Mahasiswa'],
            ['key' => 'fees', 'route' => 'keuangan/tarif', 'label' => 'Tarif & Komponen'],
            ['key' => 'bills', 'route' => 'keuangan/tagihan', 'label' => 'Tagihan Mahasiswa'],
            ['key' => 'verification', 'route' => 'keuangan/verifikasi', 'label' => 'Verifikasi Pembayaran'],
        ],
    ],
    [
        'id' => 'honor-dosen',
        'title' => 'Honor Dosen',
        'items' => [
            ['key' => 'honor-rates', 'route' => 'honor/tarif', 'label' => 'Tarif Honor'],
            ['key' => 'honor-entitlements', 'route' => 'honor/hak', 'label' => 'Hak Honor'],
            ['key' => 'honor-payments', 'route' => 'honor/pembayaran', 'label' => 'Batch Pembayaran'],
        ],
    ],
    [
        'id' => 'sistem',
        'title' => 'Sistem',
        'items' => [
            ['key' => 'admin-users', 'route' => 'pengaturan/admin', 'label' => 'Pengguna Admin'],
            ['key' => 'audit', 'route' => 'audit-log', 'label' => 'Audit Log'],
        ],
    ],
];
?>
<nav id="sidebarMenu" class="sidebar d-lg-block text-white collapse" data-simplebar>
    <div class="sidebar-inner px-3 pt-4">
        <a class="sitara-brand px-2 mb-4 d-none d-lg-flex" href="<?= site_url('/') ?>">
            <img class="sitara-mark" src="<?= base_url('assets/img/logo.png') ?>" alt="Logo Universitas Sepuluh Nopember Papua">
            <span class="sitara-brand-name">SITARA<small>Keuangan Akademik</small></span>
        </a>

        <div class="d-lg-none d-flex align-items-center justify-content-between border-bottom border-gray-700 pb-3 mb-3">
            <span class="small text-gray-300">Menu administrasi</span>
            <a href="#sidebarMenu" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-label="Tutup navigasi" class="text-white">
                <svg class="icon icon-xs" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"></path></svg>
            </a>
        </div>

        <div id="sitara-sidebar-groups">
            <?php foreach ($navGroups as $group): ?>
                <?php $groupIsActive = in_array($activeMenu, array_column($group['items'], 'key'), true); ?>

                <?php if ($group['title'] === null): ?>
                    <ul class="nav flex-column">
                        <?php foreach ($group['items'] as $item): ?>
                            <li class="nav-item">
                                <a href="<?= site_url($item['route']) ?>" class="nav-link<?= $activeMenu === $item['key'] ? ' active' : '' ?>">
                                    <span class="sidebar-icon"><span class="sitara-nav-bullet"><?= esc(strtoupper(substr($item['label'], 0, 1))) ?></span></span>
                                    <span class="sidebar-text"><?= esc($item['label']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="sitara-nav-group">
                        <button class="nav-link sitara-nav-toggle<?= $groupIsActive ? ' active' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#submenu-<?= esc($group['id']) ?>" aria-controls="submenu-<?= esc($group['id']) ?>" aria-expanded="<?= $groupIsActive ? 'true' : 'false' ?>">
                            <span class="sidebar-icon"><span class="sitara-nav-bullet"><?= esc(strtoupper(substr($group['title'], 0, 1))) ?></span></span>
                            <span class="sidebar-text"><?= esc($group['title']) ?></span>
                            <svg class="sitara-nav-arrow" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.09 1.04l-4.25 4.5a.75.75 0 0 1-1.09 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"></path></svg>
                        </button>
                        <div class="collapse<?= $groupIsActive ? ' show' : '' ?>" id="submenu-<?= esc($group['id']) ?>" data-bs-parent="#sitara-sidebar-groups">
                            <ul class="nav flex-column sitara-nav-submenu">
                                <?php foreach ($group['items'] as $item): ?>
                                    <li class="nav-item">
                                        <a href="<?= site_url($item['route']) ?>" class="nav-link<?= $activeMenu === $item['key'] ? ' active' : '' ?>">
                                            <span class="sidebar-text"><?= esc($item['label']) ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <form action="<?= site_url('logout') ?>" method="post" class="sitara-logout-form">
            <?= csrf_field() ?>
            <button class="nav-link sitara-logout-button" type="submit">
                <span class="sidebar-icon"><span class="sitara-nav-bullet">K</span></span>
                <span class="sidebar-text">Keluar</span>
            </button>
        </form>
    </div>
</nav>
