<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Aktivasi akun mahasiswa SITARA">
    <title>SITARA | Aktivasi Mahasiswa</title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/logo.png') ?>">
    <link rel="stylesheet" href="<?= base_url('css/volt.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/styles.css') ?>">
</head>
<body class="sitara-auth-page">
<main class="sitara-auth-layout">
    <section class="sitara-auth-brand-panel">
        <a class="sitara-brand" href="<?= site_url('login') ?>"><img class="sitara-mark" src="<?= base_url('assets/img/logo.png') ?>" alt="Logo Universitas Sepuluh Nopember Papua"><span class="sitara-brand-name">SITARA<small>Keuangan Akademik</small></span></a>
        <div class="sitara-auth-intro"><span class="sitara-auth-eyebrow">Aktivasi mahasiswa</span><h1>Buat password akun Anda.</h1><p>Gunakan username dan kode aktivasi yang dikirim ke email Anda. Kode tidak memiliki batas waktu, tetapi hanya dapat digunakan satu kali.</p></div>
    </section>
    <section class="sitara-auth-form-panel">
        <div class="sitara-auth-card">
            <div class="mb-4"><span class="sitara-auth-eyebrow text-primary">Akun mahasiswa</span><h2>Aktivasi akun</h2><p>Password minimal 8 karakter.</p></div>
            <?php if (! empty($error)): ?><div class="alert alert-danger" role="alert"><?= esc($error) ?></div><?php endif; ?>
            <form action="<?= site_url('aktivasi-mahasiswa') ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label" for="activation_username">Username/NIM</label><input class="form-control" id="activation_username" name="username" maxlength="50" value="<?= esc(old('username')) ?>" autocomplete="username" required autofocus></div>
                <div class="mb-3"><label class="form-label" for="activation_code">Kode aktivasi</label><input class="form-control text-uppercase" id="activation_code" name="activation_code" maxlength="19" value="<?= esc(old('activation_code')) ?>" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="one-time-code" required></div>
                <div class="mb-3"><label class="form-label" for="activation_password">Password baru</label><input class="form-control" id="activation_password" name="password" type="password" minlength="8" autocomplete="new-password" required></div>
                <div class="mb-4"><label class="form-label" for="activation_confirmation">Konfirmasi password</label><input class="form-control" id="activation_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required></div>
                <button class="btn btn-primary w-100" type="submit">Aktifkan akun</button>
            </form>
            <p class="sitara-auth-help mt-3"><a href="<?= site_url('login') ?>">Kembali ke halaman login</a></p>
        </div>
        <p class="sitara-auth-footer">&copy; <?= date('Y') ?> SITARA &middot; Sistem Informasi Tarif &amp; Pembayaran Akademik</p>
    </section>
</main>
</body>
</html>
