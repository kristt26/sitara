<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class StudentAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = session('auth');
        if (is_array($auth) && ($auth['role'] ?? null) === 'MAHASISWA' && ! empty($auth['id'])) {
            return null;
        }

        if (is_array($auth) && ($auth['role'] ?? null) === 'ADMIN' && ! empty($auth['id'])) {
            session()->setFlashdata('error', 'Portal mahasiswa hanya dapat diakses menggunakan akun mahasiswa.');
            return redirect()->to(site_url('/'));
        }

        session()->setFlashdata('error', 'Silakan login menggunakan akun mahasiswa.');
        return redirect()->to(site_url('login'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
