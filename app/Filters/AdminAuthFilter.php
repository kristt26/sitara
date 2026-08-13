<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = session('auth');
        if (is_array($auth) && ($auth['role'] ?? null) === 'ADMIN' && ! empty($auth['id'])) {
            return null;
        }

        if (str_starts_with(trim($request->getUri()->getPath(), '/'), 'api/')) {
            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON(['ok' => false, 'message' => 'Silakan login sebagai admin terlebih dahulu.']);
        }

        session()->setFlashdata('error', 'Silakan login terlebih dahulu untuk mengakses administrasi SITARA.');

        return redirect()->to(site_url('login'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
