<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $username = trim((string) env('SITARA_ADMIN_USERNAME', 'admin'));
        $password = (string) env('SITARA_ADMIN_PASSWORD', '');
        $fullName = trim((string) env('SITARA_ADMIN_NAME', ''));
        $email = trim((string) env('SITARA_ADMIN_EMAIL', ''));

        if ($username === '' || $password === '') {
            throw new RuntimeException('SITARA_ADMIN_USERNAME dan SITARA_ADMIN_PASSWORD wajib diisi sebelum menjalankan AdminUserSeeder.');
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'ADMIN',
            'is_active' => 1,
            'updated_at' => $now,
        ];
        $builder = $this->db->table('users');
        $existing = $builder->where('username', $username)->get()->getRowArray();

        if ($existing) {
            if ($fullName !== '') $data['full_name'] = $fullName;
            if ($email !== '') $data['email'] = $email;
            $builder->where('id', $existing['id'])->update($data);
            return;
        }

        $builder->insert($data + [
            'email' => $email !== '' ? $email : null,
            'full_name' => $fullName !== '' ? $fullName : 'Administrator SITARA',
            'created_at' => $now,
        ]);
    }
}
