<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class StudentAccountService
{
    public const ACTIVATION_HOURS = 72;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return array{created:bool,user_id:int,username:string,activation:?array} */
    public function provision(int $studentId, ?int $actorId = null, ?string $ipAddress = null): array
    {
        $student = $this->db->table('students')->where('id', $studentId)->get()->getRowArray();
        if (! is_array($student)) throw new RuntimeException('Data mahasiswa untuk pembuatan akun tidak ditemukan.');

        if (! empty($student['user_id'])) {
            $user = $this->studentUser((int) $student['user_id']);
            if ($user === null) throw new RuntimeException('Relasi akun mahasiswa tidak valid.');
            $this->syncUser($user, $student);
            return ['created' => false, 'user_id' => (int) $user['id'], 'username' => (string) $user['username'], 'activation' => null];
        }

        $username = strtoupper(trim((string) $student['nim']));
        if ($this->db->table('users')->where('username', $username)->countAllResults() > 0) {
            throw new RuntimeException('Username akun ' . $username . ' sudah digunakan. Akun mahasiswa belum dapat dibuat.');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('users')->insert([
            'username' => $username,
            'email' => null,
            'full_name' => (string) $student['full_name'],
            'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'role' => 'MAHASISWA',
            'is_active' => $student['status'] === 'NONAKTIF' ? 0 : 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $userId = (int) $this->db->insertID();
        $this->db->table('students')->where('id', $studentId)->update(['user_id' => $userId, 'updated_at' => $now]);
        $activation = $this->issueActivation($studentId, $actorId, $ipAddress, false);
        $this->audit('STUDENT_ACCOUNT_CREATED', 'users', $userId, $actorId, $ipAddress, null, ['student_id' => $studentId, 'username' => $username, 'activation_expires_at' => $activation['expires_at']]);

        return ['created' => true, 'user_id' => $userId, 'username' => $username, 'activation' => $activation];
    }

    /** @return array{student_id:int,nim:string,full_name:string,username:string,code:string,expires_at:string} */
    public function issueActivation(int $studentId, ?int $actorId = null, ?string $ipAddress = null, bool $audit = true): array
    {
        $row = $this->db->table('students s')
            ->select('s.id,s.nim,s.full_name,s.user_id,u.username,u.role')
            ->join('users u', 'u.id=s.user_id')
            ->where('s.id', $studentId)->get()->getRowArray();
        if (! is_array($row) || ($row['role'] ?? null) !== 'MAHASISWA') throw new RuntimeException('Akun mahasiswa belum tersedia.');

        $now = date('Y-m-d H:i:s');
        $this->db->table('student_activation_tokens')->where('user_id', $row['user_id'])->where('used_at', null)->where('revoked_at', null)->update(['revoked_at' => $now]);
        $plainCode = $this->generateCode();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::ACTIVATION_HOURS . ' hours'));
        $this->db->table('student_activation_tokens')->insert([
            'user_id' => $row['user_id'],
            'token_hash' => $this->hashCode($plainCode),
            'expires_at' => $expiresAt,
            'used_at' => null,
            'revoked_at' => null,
            'created_by' => $actorId,
            'created_at' => $now,
        ]);
        if ($audit) $this->audit('STUDENT_ACTIVATION_REISSUED', 'users', (int) $row['user_id'], $actorId, $ipAddress, null, ['student_id' => $studentId, 'expires_at' => $expiresAt]);

        return ['student_id' => (int) $row['id'], 'nim' => (string) $row['nim'], 'full_name' => (string) $row['full_name'], 'username' => (string) $row['username'], 'code' => $plainCode, 'expires_at' => $expiresAt];
    }

    public function activate(string $username, string $code, string $password, ?string $ipAddress = null): void
    {
        $normalizedUsername = strtoupper(trim($username));
        $normalizedCode = $this->normalizeCode($code);
        $token = $this->db->table('student_activation_tokens sat')
            ->select('sat.id,sat.user_id,sat.expires_at,u.username,u.role,u.is_active,s.id student_id,s.status student_status')
            ->join('users u', 'u.id=sat.user_id')
            ->join('students s', 's.user_id=u.id')
            ->where('sat.token_hash', hash('sha256', $normalizedCode))
            ->where('sat.used_at', null)->where('sat.revoked_at', null)->get()->getRowArray();
        if (! is_array($token) || strtoupper((string) $token['username']) !== $normalizedUsername || $token['role'] !== 'MAHASISWA' || ! (int) $token['is_active'] || $token['student_status'] === 'NONAKTIF' || strtotime((string) $token['expires_at']) < time()) {
            throw new RuntimeException('Username atau kode aktivasi tidak valid, sudah digunakan, atau telah kedaluwarsa.');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $this->db->table('student_activation_tokens')->where('id', $token['id'])->where('used_at', null)->where('revoked_at', null)->update(['used_at' => $now]);
            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('Username atau kode aktivasi tidak valid, sudah digunakan, atau telah kedaluwarsa.');
            }
            $this->db->table('users')->where('id', $token['user_id'])->update(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => $now]);
            $this->audit('STUDENT_ACCOUNT_ACTIVATED', 'users', (int) $token['user_id'], (int) $token['user_id'], $ipAddress, null, ['student_id' => (int) $token['student_id'], 'activated_at' => $now]);
            if (! $this->db->transStatus()) throw new RuntimeException('Aktivasi akun belum dapat disimpan.');
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function sync(int $studentId): void
    {
        $student = $this->db->table('students')->where('id', $studentId)->get()->getRowArray();
        if (! is_array($student) || empty($student['user_id'])) return;
        $user = $this->studentUser((int) $student['user_id']);
        if ($user !== null) $this->syncUser($user, $student);
    }

    private function studentUser(int $userId): ?array
    {
        return $this->db->table('users')->where(['id' => $userId, 'role' => 'MAHASISWA'])->get()->getRowArray();
    }

    private function syncUser(array $user, array $student): void
    {
        $username = strtoupper(trim((string) $student['nim']));
        $collision = $this->db->table('users')->where('username', $username)->where('id !=', $user['id'])->countAllResults();
        if ($collision > 0) throw new RuntimeException('Username akun ' . $username . ' sudah digunakan.');
        $this->db->table('users')->where('id', $user['id'])->update([
            'username' => $username,
            'full_name' => (string) $student['full_name'],
            'is_active' => $student['status'] === 'NONAKTIF' ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $raw = '';
        for ($i = 0; $i < 16; $i++) $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        return implode('-', str_split($raw, 4));
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', trim($code)));
    }

    private function hashCode(string $code): string
    {
        return hash('sha256', $this->normalizeCode($code));
    }

    private function audit(string $action, string $entity, int $entityId, ?int $actorId, ?string $ipAddress, ?array $old, ?array $new): void
    {
        $this->db->table('audit_logs')->insert(['user_id' => $actorId, 'action' => $action, 'entity_type' => $entity, 'entity_id' => $entityId, 'old_values' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE), 'new_values' => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE), 'ip_address' => $ipAddress, 'created_at' => date('Y-m-d H:i:s')]);
    }
}
