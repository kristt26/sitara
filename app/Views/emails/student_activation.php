<!doctype html>
<html lang="id"><body style="background:#f4f7fb;color:#243b53;font-family:Arial,sans-serif;margin:0;padding:24px">
<div style="background:#fff;border:1px solid #d9e2ec;border-radius:12px;margin:auto;max-width:620px;overflow:hidden">
    <div style="background:#1769aa;color:#fff;padding:24px"><h1 style="font-size:22px;margin:0">Aktivasi Akun Mahasiswa SITARA</h1></div>
    <div style="padding:28px"><p>Halo <?= esc($name) ?>,</p><p>Akun mahasiswa Anda sudah dibuat. Gunakan data berikut untuk membuat password dan mengakses portal mahasiswa.</p>
        <table style="border-collapse:collapse;margin:20px 0;width:100%"><tr><td style="padding:8px 0;width:130px">NIM / Username</td><td style="font-weight:bold"><?= esc($username) ?></td></tr><tr><td style="padding:8px 0">Kode aktivasi</td><td style="font-family:monospace;font-size:20px;font-weight:bold;letter-spacing:1px"><?= esc($code) ?></td></tr></table>
        <p style="margin:24px 0"><a href="<?= esc($activationUrl) ?>" style="background:#1769aa;border-radius:6px;color:#fff;display:inline-block;font-weight:bold;padding:12px 18px;text-decoration:none">Aktifkan akun</a></p>
        <p>Kode ini tidak memiliki batas waktu, tetapi hanya dapat digunakan satu kali. Jangan bagikan kode ini kepada siapa pun.</p>
        <p style="border-top:1px solid #d9e2ec;color:#627d98;font-size:12px;margin-top:26px;padding-top:16px">Email ini dikirim otomatis oleh SITARA. Mohon jangan membalas email ini.</p>
    </div>
</div>
</body></html>
