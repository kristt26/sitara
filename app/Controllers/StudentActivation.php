<?php

namespace App\Controllers;

use App\Libraries\StudentAccountService;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

class StudentActivation extends BaseController
{
    public function index(): string
    {
        return view('auth/student_activation', ['error' => session()->getFlashdata('error')]);
    }

    public function activate(): RedirectResponse
    {
        $username = trim((string) $this->request->getPost('username'));
        $code = trim((string) $this->request->getPost('activation_code'));
        $password = (string) $this->request->getPost('password');
        $confirmation = (string) $this->request->getPost('password_confirmation');
        if ($username === '' || $code === '' || strlen($password) < 8 || $password !== $confirmation) {
            return redirect()->back()->withInput()->with('error', 'Lengkapi data aktivasi. Password minimal 8 karakter dan konfirmasinya harus sama.');
        }

        try {
            (new StudentAccountService(db_connect()))->activate($username, $code, $password, $this->request->getIPAddress());
            return redirect()->to(site_url('login'))->with('success', 'Akun mahasiswa berhasil diaktifkan. Silakan login menggunakan NIM dan password baru Anda.');
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        } catch (Throwable) {
            return redirect()->back()->withInput()->with('error', 'Aktivasi akun belum dapat diproses. Silakan hubungi administrator.');
        }
    }
}
