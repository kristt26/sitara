<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Login SITARA untuk admin dan mahasiswa">
    <title>SITARA | Login</title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/logo.png') ?>">
    <link rel="stylesheet" href="<?= base_url('css/volt.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/styles.css') ?>">
</head>
<body class="sitara-auth-page">
    <main class="sitara-auth-layout">
        <section class="sitara-auth-brand-panel">
            <a class="sitara-brand" href="<?= site_url('login') ?>">
                <img class="sitara-mark" src="<?= base_url('assets/img/logo.png') ?>" alt="Logo Universitas Sepuluh Nopember Papua">
                <span class="sitara-brand-name">SITARA<small>Keuangan Akademik</small></span>
            </a>
            <div class="sitara-auth-intro">
                <span class="sitara-auth-eyebrow">Satu pintu akses</span>
                <h1>Informasi akademik dan pembayaran yang tertib.</h1>
                <p>Admin mengelola transaksi akademik, sementara mahasiswa dapat memantau kegiatan, tagihan, dan status pembayarannya.</p>
            </div>
            <div class="sitara-auth-feature-list">
                <div><span>1</span> Data tarif dan komponen terkendali</div>
                <div><span>2</span> Verifikasi pembayaran tercatat</div>
                <div><span>3</span> Riwayat aktivitas dapat ditelusuri</div>
            </div>
        </section>

        <section class="sitara-auth-form-panel">
            <div class="sitara-auth-card">
                <div class="mb-4">
                    <span class="sitara-auth-eyebrow text-primary">Admin &amp; mahasiswa</span>
                    <h2>Masuk ke SITARA</h2>
                    <p>Admin menggunakan username/email. Mahasiswa menggunakan NIM dan password yang telah diaktivasi.</p>
                </div>

                <?php if ($message = session()->getFlashdata('success')): ?>
                    <div class="alert alert-success" role="alert"><?= esc($message) ?></div>
                <?php endif; ?>
                <?php if (! empty($error)): ?>
                    <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
                <?php endif; ?>

                <form action="<?= site_url('login') ?>" method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="identity">Username atau email</label>
                        <input class="form-control" id="identity" name="identity" type="text" value="<?= esc(old('identity')) ?>" autocomplete="username" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">Kata sandi</label>
                        <div class="sitara-password-field">
                            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                            <button class="sitara-password-toggle" type="button" aria-label="Tampilkan kata sandi" aria-pressed="false" data-password-toggle>
                                <svg class="sitara-eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                                <svg class="sitara-eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-3.05 3.75M6.1 6.1C3.84 7.77 2.5 10.1 2.5 12c0 0 3.5 6 9.5 6 1.26 0 2.4-.26 3.4-.7M9.35 9.35A3.75 3.75 0 0 0 14.65 14.65"/></svg>
                            </button>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Masuk ke SITARA</button>
                </form>
                <p class="sitara-auth-help">Mahasiswa belum memiliki password? <a href="<?= site_url('aktivasi-mahasiswa') ?>">Aktifkan akun dengan kode aktivasi</a>.</p>
            </div>
            <p class="sitara-auth-footer">&copy; <?= date('Y') ?> SITARA &middot; Sistem Informasi Tarif &amp; Pembayaran Akademik</p>
        </section>
    </main>
    <script>
        document.querySelector('[data-password-toggle]')?.addEventListener('click', function () {
            const password = document.getElementById('password');
            const isVisible = password.type === 'text';
            password.type = isVisible ? 'password' : 'text';
            this.setAttribute('aria-pressed', String(!isVisible));
            this.setAttribute('aria-label', isVisible ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
            this.classList.toggle('is-visible', !isVisible);
        });
    </script>
</body>
</html>
