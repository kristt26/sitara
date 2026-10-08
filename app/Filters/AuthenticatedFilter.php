<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthenticatedFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = session('auth');
        if (is_array($auth) && ! empty($auth['id']) && in_array($auth['role'] ?? null, ['ADMIN', 'PRODI', 'KEUANGAN', 'MAHASISWA'], true)) {
            return null;
        }

        session()->setFlashdata('error', 'Silakan login terlebih dahulu.');
        return redirect()->to(site_url('login'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
