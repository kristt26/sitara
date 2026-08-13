<?php
$stats = $stats ?? [];
$feeSettings = $feeSettings ?? [];
$auditLogs = $auditLogs ?? [];
$formatRupiah = static fn (float|int|string $amount): string => 'Rp ' . number_format((float) $amount, 0, ',', '.');
$statTones = ['blue', 'indigo', 'orange', 'green'];
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="sitara-hero" aria-labelledby="dashboard-title">
    <div class="position-relative" style="z-index: 1;">
        <span class="badge bg-white text-primary mb-3">Periode aktif</span>
        <h1 class="h3 fw-bolder mb-2" id="dashboard-title">Kelola keuangan akademik dengan lebih terarah.</h1>
        <p class="mb-4">Pantau tarif kegiatan, tagihan mahasiswa, proses verifikasi, dan pembayaran honor dalam satu ruang kerja yang rapi.</p>
        <a class="btn btn-light btn-sm" href="<?= site_url('keuangan/tagihan') ?>">Kelola tagihan</a>
    </div>
</section>

<section class="row g-3 mt-1" aria-label="Ringkasan keuangan">
    <?php foreach ($stats as $index => $stat): ?>
        <?php $tone = $statTones[$index] ?? 'blue'; ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <article class="sitara-metric sitara-tone-<?= esc($tone) ?>">
                <div class="d-flex align-items-start justify-content-between">
                    <span class="sitara-metric-label"><?= esc((string) ($stat['label'] ?? 'Ringkasan')) ?></span>
                    <span class="sitara-metric-icon"><span class="fw-bold"><?= esc((string) ($index + 1)) ?></span></span>
                </div>
                <div class="sitara-metric-value"><?= esc((string) ($stat['value'] ?? '0')) ?></div>
                <div class="sitara-metric-meta"><?= esc((string) ($stat['meta'] ?? 'Belum ada data')) ?></div>
            </article>
        </div>
    <?php endforeach; ?>
</section>

<section class="row g-3 mt-1">
    <div class="col-12 col-xl-8">
        <article class="card sitara-card h-100">
            <header class="card-header d-flex align-items-center justify-content-between gap-3">
                <div>
                    <h2 class="sitara-card-title">Tarif kegiatan aktif</h2>
                    <p class="sitara-card-subtitle">Tarif yang menjadi dasar pembentukan tagihan mahasiswa.</p>
                </div>
                <a href="<?= site_url('keuangan/tarif') ?>" class="btn btn-outline-primary btn-sm">Lihat tarif</a>
            </header>
            <div class="table-responsive">
                <table class="table sitara-table mb-0">
                    <thead><tr><th>Program studi</th><th>Kegiatan</th><th>Periode</th><th class="text-end">Nominal</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($feeSettings !== []): ?>
                        <?php foreach (array_slice($feeSettings, 0, 5) as $setting): ?>
                            <tr>
                                <td class="table-main"><?= esc((string) ($setting['program'] ?? '-')) ?></td>
                                <td><?= esc((string) ($setting['activity'] ?? '-')) ?><small class="d-block text-gray-500"><?= esc((string) ($setting['examPath'] ?? 'Umum')) ?></small></td>
                                <td><?= esc((string) (($setting['year'] ?? '-') . ' / ' . ($setting['semester'] ?? '-'))) ?></td>
                                <td class="text-end table-main"><?= esc($formatRupiah($setting['amount'] ?? 0)) ?></td>
                                <td><span class="sitara-status">Aktif</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="sitara-empty">Belum ada tarif aktif untuk ditampilkan.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
    <div class="col-12 col-xl-4">
        <article class="card sitara-card h-100">
            <header class="card-header">
                <h2 class="sitara-card-title">Alur kerja hari ini</h2>
                <p class="sitara-card-subtitle">Urutan proses agar transaksi tetap konsisten.</p>
            </header>
            <div class="card-body">
                <?php foreach ([
                    ['Pastikan tarif aktif', 'Periode, prodi, dan jenis kegiatan harus tersedia.'],
                    ['Bentuk tagihan mahasiswa', 'Nominal dikunci dari komponen tarif yang dipilih.'],
                    ['Verifikasi pembayaran', 'Periksa bukti bayar dan metode pembayaran.'],
                    ['Proses honor dosen', 'Susun batch dari hak honor yang disetujui.'],
                ] as $index => [$title, $description]): ?>
                    <div class="sitara-queue-item">
                        <span class="sitara-queue-dot"><?= esc((string) ($index + 1)) ?></span>
                        <div><strong><?= esc($title) ?></strong><span><?= esc($description) ?></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </div>
</section>

<section class="row g-3 mt-1">
    <div class="col-12">
        <article class="card sitara-card">
            <header class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h2 class="sitara-card-title">Aktivitas terbaru</h2>
                    <p class="sitara-card-subtitle">Jejak perubahan untuk akuntabilitas transaksi.</p>
                </div>
                <a href="<?= site_url('audit-log') ?>" class="btn btn-link btn-sm">Buka audit log</a>
            </header>
            <div class="card-body">
                <?php if ($auditLogs !== []): ?>
                    <?php foreach ($auditLogs as $log): ?>
                        <div class="sitara-audit-item">
                            <span class="sitara-audit-icon"><?= esc(strtoupper(substr((string) ($log['action'] ?? 'UP'), 0, 2))) ?></span>
                            <div>
                                <p><strong><?= esc(ucfirst(strtolower((string) ($log['action'] ?? 'Memperbarui')))) ?></strong> <?= esc(str_replace('_', ' ', (string) ($log['entity_type'] ?? 'data'))) ?> #<?= esc((string) ($log['entity_id'] ?? '-')) ?>.</p>
                                <small><?= esc((string) ($log['created_at'] ?? 'Baru saja')) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="sitara-empty">Aktivitas audit akan muncul setelah perubahan transaksi dilakukan.</div>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>
<?= $this->endSection() ?>
