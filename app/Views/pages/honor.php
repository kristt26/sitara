<?php $pageDescription = 'Kelola hak honor dosen, batch pembayaran, dan pencatatan pembayaran honor.'; ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?= $this->include('pages/partials/page_heading') ?>
<section class="row g-3">
    <div class="col-12 col-xl-8">
        <article class="card sitara-card h-100">
            <header class="card-header"><h2 class="sitara-card-title">Batch pembayaran honor</h2><p class="sitara-card-subtitle">Kelompokkan hak honor yang sudah disetujui menurut periode pembayaran.</p></header>
            <div class="sitara-empty">Belum ada batch pembayaran honor yang ditampilkan.</div>
        </article>
    </div>
    <div class="col-12 col-xl-4">
        <article class="card sitara-card h-100">
            <header class="card-header"><h2 class="sitara-card-title">Alur pembayaran honor</h2></header>
            <div class="card-body">
                <div class="sitara-queue-item"><span class="sitara-queue-dot">1</span><div><strong>Tetapkan tarif honor</strong><span>Tarif mengikuti peran dan jenis kegiatan akademik.</span></div></div>
                <div class="sitara-queue-item"><span class="sitara-queue-dot">2</span><div><strong>Validasi hak honor</strong><span>Hak terbentuk dari penugasan dosen yang aktif.</span></div></div>
                <div class="sitara-queue-item"><span class="sitara-queue-dot">3</span><div><strong>Buat batch pembayaran</strong><span>Simpan nominal bruto, pajak, dan nominal bersih.</span></div></div>
            </div>
        </article>
    </div>
</section>
<?= $this->endSection() ?>
