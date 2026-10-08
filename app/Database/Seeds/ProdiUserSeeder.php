<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/** Creates the scoped operator account for Sistem Informasi. */
class ProdiUserSeeder extends Seeder
{
    public function run(): void
    {
        $username = trim((string) env('SITARA_PRODI_SI_USERNAME', 'prodi.si'));
        $password = (string) env('SITARA_PRODI_SI_PASSWORD', '');
        $fullName = trim((string) env('SITARA_PRODI_SI_NAME', 'Operator Prodi Sistem Informasi'));
        $email = trim((string) env('SITARA_PRODI_SI_EMAIL', ''));
        $programCode = trim((string) env('SITARA_PRODI_SI_CODE', '57201'));

        if ($username === '' || $password === '') {
            throw new RuntimeException('SITARA_PRODI_SI_USERNAME dan SITARA_PRODI_SI_PASSWORD wajib diisi sebelum menjalankan ProdiUserSeeder.');
        }
        if (! $this->db->tableExists('user_study_programs')) {
            throw new RuntimeException('Migration role Prodi belum dijalankan. Jalankan php spark migrate terlebih dahulu.');
        }

        $program = $this->db->table('study_programs')->where('code', $programCode)->where('is_active', 1)->get()->getRowArray();
        if (! is_array($program)) {
            throw new RuntimeException('Program studi Sistem Informasi dengan kode ' . $programCode . ' tidak ditemukan atau tidak aktif.');
        }

        $now = date('Y-m-d H:i:s');
        $users = $this->db->table('users');
        $existing = $users->where('username', $username)->get()->getRowArray();
        $data = [
            'username' => $username,
            'email' => $email !== '' ? $email : null,
            'full_name' => $fullName !== '' ? $fullName : 'Operator Prodi Sistem Informasi',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'PRODI',
            'is_active' => 1,
            'updated_at' => $now,
        ];

        $this->db->transStart();
        if (is_array($existing)) {
            $users->where('id', (int) $existing['id'])->update($data);
            $userId = (int) $existing['id'];
        } else {
            $users->insert($data + ['created_at' => $now]);
            $userId = (int) $this->db->insertID();
        }

        $this->db->table('user_study_programs')->where('user_id', $userId)->delete();
        $this->db->table('user_study_programs')->insert([
            'user_id' => $userId,
            'study_program_id' => (int) $program['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('Akun Prodi Sistem Informasi belum dapat dibuat atau diperbarui.');
        }
    }
}
