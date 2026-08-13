<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class Auth extends BaseController
{
    public function login(): string|RedirectResponse
    {
        if ($this->isAuthenticatedAdmin()) {
            return redirect()->to(site_url('/'));
        }

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
            || ($user['role'] ?? null) !== 'ADMIN'
            || ! password_verify($password, (string) ($user['password_hash'] ?? ''))
        ) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Kredensial tidak valid atau akun tidak memiliki akses admin.');
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

        return redirect()->to(site_url('/'))->with('success', 'Selamat datang kembali, ' . $user['full_name'] . '.');
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to(site_url('login'))->with('success', 'Anda telah keluar dari SITARA.');
    }

    private function isAuthenticatedAdmin(): bool
    {
        $auth = session('auth');

        return is_array($auth) && ($auth['role'] ?? null) === 'ADMIN' && ! empty($auth['id']);
    }
}
