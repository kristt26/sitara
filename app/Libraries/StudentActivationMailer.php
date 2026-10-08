<?php

namespace App\Libraries;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class StudentActivationMailer
{
    /** @param array{nim:string,full_name:string,username:string,email:string,code:string} $activation */
    public function send(array $activation): void
    {
        if (ENVIRONMENT === 'testing') return;

        $recipient = strtolower(trim((string) ($activation['email'] ?? '')));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Alamat email mahasiswa belum tersedia atau tidak valid.');
        }

        $config = config('Email');
        if ($config->protocol !== 'smtp' || trim($config->SMTPHost) === '' || trim($config->SMTPUser) === '' || trim($config->SMTPPass) === '' || trim($config->fromEmail) === '') {
            throw new RuntimeException('Pengaturan SMTP SITARA belum lengkap. Kode aktivasi belum dapat dikirim ke email.');
        }

        $html = view('emails/student_activation', [
            'name' => $activation['full_name'],
            'nim' => $activation['nim'],
            'username' => $activation['username'],
            'code' => $activation['code'],
            'activationUrl' => site_url('aktivasi-mahasiswa'),
        ]);

        try {
            $email = new PHPMailer(true);
            $email->isSMTP();
            $email->Host = $config->SMTPHost;
            $email->SMTPAuth = true;
            $email->Username = $config->SMTPUser;
            $email->Password = $config->SMTPPass;
            $email->SMTPSecure = $this->encryption($config->SMTPCrypto, $config->SMTPPort);
            $email->Port = $config->SMTPPort;
            $email->Timeout = $config->SMTPTimeout;
            $email->SMTPKeepAlive = false;
            $email->CharSet = PHPMailer::CHARSET_UTF8;
            $email->isHTML(true);
            $email->setFrom($config->fromEmail, $config->fromName ?: 'SITARA - Jangan Balas');
            $email->addAddress($recipient, $activation['full_name']);
            $email->addCustomHeader('Auto-Submitted', 'auto-generated');
            $email->addCustomHeader('X-Auto-Response-Suppress', 'All');
            $email->addCustomHeader('Precedence', 'bulk');
            $email->Subject = 'Kode Aktivasi Akun Mahasiswa SITARA';
            $email->Body = $html;
            $email->AltBody = "Halo {$activation['full_name']},\n\nUsername/NIM: {$activation['username']}\nKode aktivasi: {$activation['code']}\nAktifkan akun: " . site_url('aktivasi-mahasiswa') . "\n\nKode tidak memiliki batas waktu, tetapi hanya dapat digunakan satu kali. Mohon jangan membalas email ini.";
            $email->send();
        } catch (PHPMailerException $exception) {
            log_message('error', 'Pengiriman kode aktivasi mahasiswa PHPMailer gagal untuk user {username}: {message}', ['username' => $activation['username'], 'message' => $exception->getMessage()]);
            throw new RuntimeException('Email kode aktivasi belum dapat dikirim. Periksa pengaturan SMTP atau coba kirim ulang.');
        }
    }

    private function encryption(string $configured, int $port): string
    {
        if ($port === 465 || strtolower($configured) === 'ssl') return PHPMailer::ENCRYPTION_SMTPS;
        if (strtolower($configured) === 'tls') return PHPMailer::ENCRYPTION_STARTTLS;
        return '';
    }
}
