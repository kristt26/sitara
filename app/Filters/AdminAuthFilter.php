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
        if (is_array($auth) && ! empty($auth['id'])) {
            $role = (string) ($auth['role'] ?? '');
            if ($role === 'ADMIN' || $this->roleMayAccess($role, trim($request->getUri()->getPath(), '/'))) {
                return null;
            }

            session()->setFlashdata('error', 'Akun Anda tidak memiliki akses ke halaman ini.');
            return redirect()->to(site_url($role === 'MAHASISWA' ? 'portal-mahasiswa' : '/'));
        }

        if (is_array($auth) && ($auth['role'] ?? null) === 'MAHASISWA' && ! empty($auth['id'])) {
            session()->setFlashdata('error', 'Akun mahasiswa tidak memiliki akses ke halaman administrasi.');
            return redirect()->to(site_url('portal-mahasiswa'));
        }

        if (str_starts_with(trim($request->getUri()->getPath(), '/'), 'api/')) {
            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON(['ok' => false, 'message' => 'Silakan login sebagai admin terlebih dahulu.']);
        }

        session()->setFlashdata('error', 'Silakan login terlebih dahulu untuk mengakses administrasi SITARA.');

        return redirect()->to(site_url('login'));
    }

    private function roleMayAccess(string $role, string $path): bool
    {
        if ($path === '') return in_array($role, ['PRODI', 'KEUANGAN'], true);

        $finance = str_starts_with($path, 'keuangan/') || str_starts_with($path, 'honor/');
        $calendar = str_starts_with($path, 'periode');
        $system = str_starts_with($path, 'pengaturan/') || str_starts_with($path, 'audit-log');
        $academic = str_starts_with($path, 'master/')
            || str_starts_with($path, 'kegiatan')
            || str_starts_with($path, 'template-dokumen')
            || str_starts_with($path, 'aturan-kegiatan');

        return match ($role) {
            'KEUANGAN' => ($finance || $calendar) && ! $system,
            'PRODI' => $academic && ! $finance && ! $calendar && ! $system,
            default => false,
        };
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
