<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class Auth extends BaseController
{
    public function login(): string|RedirectResponse
    {
        if (($target = $this->authenticatedTarget()) !== null) return redirect()->to($target);

        return view('auth/login', [
            'title' => 'Login Admin',
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function attempt(): RedirectResponse
    {
        $rules = [
            'identity' => 'required|max_length[200]',
            'password' => 'required|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Username/email dan kata sandi wajib diisi.');
        }

        $identity = trim((string) $this->request->getPost('identity'));
        $password = (string) $this->request->getPost('password');
        $user = db_connect()->table('users')
            ->groupStart()
            ->where('username', $identity)
            ->orWhere('email', $identity)
            ->groupEnd()
            ->get()
            ->getRowArray();

        if (
            ! is_array($user)
            || ! (int) ($user['is_active'] ?? 0)
            || ! in_array($user['role'] ?? null, ['ADMIN', 'PRODI', 'KEUANGAN', 'MAHASISWA'], true)
            || ! password_verify($password, (string) ($user['password_hash'] ?? ''))
        ) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Username/email atau kata sandi tidak valid, atau akun sedang nonaktif.');
        }

        if (($user['role'] ?? null) === 'MAHASISWA' && ! $this->hasActiveStudentProfile((int) $user['id'])) {
            return redirect()->back()->withInput()->with('error', 'Profil mahasiswa tidak ditemukan atau sedang nonaktif. Hubungi administrator.');
        }

        session()->regenerate(true);
        session()->set('auth', [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'full_name' => (string) $user['full_name'],
            'role' => (string) $user['role'],
        ]);

        db_connect()->table('users')->where('id', $user['id'])->update([
            'last_login_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $target = $user['role'] === 'MAHASISWA' ? site_url('portal-mahasiswa') : site_url('/');
        return redirect()->to($target)->with('success', 'Selamat datang kembali, ' . $user['full_name'] . '.');
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to(site_url('login'))->with('success', 'Anda telah keluar dari SITARA.');
    }

    private function authenticatedTarget(): ?string
    {
        $auth = session('auth');
        if (! is_array($auth) || empty($auth['id'])) return null;
        return match ($auth['role'] ?? null) {
            'ADMIN', 'PRODI', 'KEUANGAN' => site_url('/'),
            'MAHASISWA' => site_url('portal-mahasiswa'),
            default => null,
        };
    }

    private function hasActiveStudentProfile(int $userId): bool
    {
        return db_connect()->table('students')->where('user_id', $userId)->where('status !=', 'NONAKTIF')->countAllResults() === 1;
    }
}
