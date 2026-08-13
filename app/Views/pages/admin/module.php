<?php
$pageDescription = $description ?? $pageSubtitle ?? '';
$fields = $fields ?? [];
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?= $this->include('pages/partials/page_heading') ?>
<section class="row g-3">
    <div class="col-12 col-xl-7">
        <article class="card sitara-card h-100">
            <header class="card-header">
                <h2 class="sitara-card-title">Data yang dikelola admin</h2>
                <p class="sitara-card-subtitle">Siapkan seluruh informasi berikut sebelum data dimasukkan ke sistem.</p>
            </header>
            <div class="card-body">
                <div class="row g-2">
                    <?php foreach ($fields as $index => $field): ?>
                        <div class="col-12 col-md-6">
                            <div class="sitara-input-field">
                                <span class="sitara-queue-dot"><?= esc((string) ($index + 1)) ?></span>
                                <span><?= esc($field) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </article>
    </div>
    <div class="col-12 col-xl-5">
        <article class="card sitara-card h-100">
            <header class="card-header">
                <h2 class="sitara-card-title">Status modul</h2>
                <p class="sitara-card-subtitle">Ruang kerja admin untuk pengelolaan data.</p>
            </header>
            <div class="card-body">
                <span class="sitara-status sitara-status-info">Siap dikembangkan</span>
                <p class="small text-gray-600 mt-3 mb-0">Struktur menu dan skema database sudah disiapkan. Form input dan aksi simpan dapat ditambahkan tanpa mengubah struktur navigasi.</p>
            </div>
        </article>
    </div>
</section>
<?= $this->endSection() ?>
