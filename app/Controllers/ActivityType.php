<?php

namespace App\Controllers;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class ActivityType extends BaseController
{
    private BaseConnection $db;

    public function initController($request, $response, $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->db = db_connect();
    }

    public function index(): string
    {
        return view('pages/academic/activities', [
            'live' => true, 'activeMenu' => 'activities', 'pageTitle' => 'Jenis Kegiatan',
            'pageSubtitle' => 'Master kegiatan untuk tarif, tagihan, dan honor',
            'csrfHeader' => config('Security')->headerName, 'csrfHash' => csrf_hash(),
        ]);
    }

    public function read(): ResponseInterface
    {
        try {
            return $this->successResponse($this->db->table('activity_types')->select('id, code, name, examiner_supported, is_active, created_at, updated_at')->orderBy('code')->get()->getResultArray());
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function create(): ResponseInterface
    {
        try {
            $this->assertGlobalMasterWriteAccess();
            $data = $this->payload(); $this->validateOrFail($data); $this->assertUniqueCode($data['code']);
            $now = date('Y-m-d H:i:s'); $this->db->transStart();
            $this->db->table('activity_types')->insert([...$data, 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $this->db->insertID(); $this->writeAudit('ACTIVITY_TYPE_CREATED', $id, null, $this->byId($id));
            $this->db->transComplete(); $this->assertTransaction('Jenis kegiatan belum dapat disimpan.');
            return $this->successResponse($this->byId($id), 'Jenis kegiatan berhasil ditambahkan.', ResponseInterface::HTTP_CREATED);
        } catch (Throwable $exception) { return $this->errorResponse($exception); }
    }

    public function update(int $id): ResponseInterface
    {
        try {
            $this->assertGlobalMasterWriteAccess();
            $existing = $this->byId($id); if ($existing === null) return $this->messageResponse('Jenis kegiatan tidak ditemukan.', 404);
            $data = $this->payload(); $this->validateOrFail($data); $this->assertUniqueCode($data['code'], $id);
            $this->db->transStart(); $this->db->table('activity_types')->where('id', $id)->update([...$data, 'updated_at' => date('Y-m-d H:i:s')]);
            $updated = $this->byId($id); $this->writeAudit('ACTIVITY_TYPE_UPDATED', $id, $existing, $updated);
            $this->db->transComplete(); $this->assertTransaction('Perubahan jenis kegiatan belum dapat disimpan.');
            return $this->successResponse($updated, 'Jenis kegiatan berhasil diperbarui.');
        } catch (Throwable $exception) { return $this->errorResponse($exception); }
    }

    public function activate(int $id): ResponseInterface
    {
        try {
            $this->assertGlobalMasterWriteAccess();
            $existing = $this->byId($id); if ($existing === null) return $this->messageResponse('Jenis kegiatan tidak ditemukan.', 404);
            $this->db->transStart(); $this->db->table('activity_types')->where('id', $id)->update(['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            $updated = $this->byId($id); $this->writeAudit('ACTIVITY_TYPE_ACTIVATED', $id, $existing, $updated);
            $this->db->transComplete(); $this->assertTransaction('Status jenis kegiatan belum dapat diubah.');
            return $this->successResponse($updated, 'Jenis kegiatan berhasil diaktifkan.');
        } catch (Throwable $exception) { return $this->errorResponse($exception); }
    }

    public function delete(int $id): ResponseInterface
    {
        try {
            $this->assertGlobalMasterWriteAccess();
            $existing = $this->byId($id); if ($existing === null) return $this->messageResponse('Jenis kegiatan tidak ditemukan.', 404);
            if (($usage = $this->firstUsage($id)) !== null) throw new RuntimeException('Jenis kegiatan tidak dapat dihapus karena sudah digunakan pada ' . $usage . '. Nonaktifkan untuk menyimpannya sebagai arsip.');
            $this->db->transStart(); $this->db->table('activity_types')->where('id', $id)->delete(); $this->writeAudit('ACTIVITY_TYPE_DELETED', $id, $existing, null);
            $this->db->transComplete(); $this->assertTransaction('Jenis kegiatan belum dapat dihapus.');
            return $this->successResponse(null, 'Jenis kegiatan berhasil dihapus.');
        } catch (Throwable $exception) { return $this->errorResponse($exception); }
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true); $input = is_array($json) ? $json : $this->request->getPost();
        return ['code' => strtoupper(trim((string) ($input['code'] ?? ''))), 'name' => trim((string) ($input['name'] ?? '')), 'examiner_supported' => filter_var($input['examiner_supported'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, 'is_active' => filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0];
    }

    private function validateOrFail(array $data): void
    {
        if (! preg_match('/^[A-Z0-9._-]{2,30}$/', $data['code'])) throw new RuntimeException('Kode wajib 2–30 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.');
        $length = function_exists('mb_strlen') ? mb_strlen($data['name']) : strlen($data['name']);
        if ($length < 3 || $length > 100) throw new RuntimeException('Nama kegiatan wajib terdiri dari 3 sampai 100 karakter.');
    }

    private function assertUniqueCode(string $code, ?int $exceptId = null): void { $builder = $this->db->table('activity_types')->where('code', $code); if ($exceptId !== null) $builder->where('id !=', $exceptId); if ($builder->countAllResults() > 0) throw new RuntimeException('Kode jenis kegiatan tersebut sudah digunakan.'); }
    private function assertGlobalMasterWriteAccess(): void { $auth = session('auth'); if (! is_array($auth) || ($auth['role'] ?? null) !== 'ADMIN') throw new RuntimeException('Master jenis kegiatan hanya dapat diubah oleh administrator pusat.'); }
    private function byId(int $id): ?array { return $this->db->table('activity_types')->where('id', $id)->get()->getRowArray(); }
    private function firstUsage(int $id): ?string { foreach (['activity_rules' => 'aturan kegiatan', 'fee_settings' => 'pengaturan tarif', 'honor_rate_settings' => 'tarif honor', 'academic_activities' => 'kegiatan mahasiswa', 'student_bills' => 'tagihan mahasiswa'] as $table => $label) if ($this->db->table($table)->where('activity_type_id', $id)->countAllResults() > 0) return $label; return null; }
    private function assertTransaction(string $message): void { if (! $this->db->transStatus()) throw new RuntimeException($message); }
    private function writeAudit(string $action, int $id, ?array $old, ?array $new): void { $auth = session('auth'); $this->db->table('audit_logs')->insert(['user_id' => is_array($auth) ? ($auth['id'] ?? null) : null, 'action' => $action, 'entity_type' => 'activity_types', 'entity_id' => $id, 'old_values' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE), 'new_values' => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE), 'ip_address' => $this->request->getIPAddress(), 'created_at' => date('Y-m-d H:i:s')]); }
    private function successResponse(mixed $data = null, ?string $message = null, int $status = 200): ResponseInterface { return $this->response->setStatusCode($status)->setJSON(['ok' => true, 'message' => $message, 'data' => $data, 'csrf' => $this->csrfPayload()]); }
    private function messageResponse(string $message, int $status): ResponseInterface { return $this->response->setStatusCode($status)->setJSON(['ok' => false, 'message' => $message, 'csrf' => $this->csrfPayload()]); }
    private function errorResponse(Throwable $exception): ResponseInterface { return $this->messageResponse($exception instanceof RuntimeException ? $exception->getMessage() : 'Terjadi kesalahan saat memproses jenis kegiatan.', 422); }
    private function csrfPayload(): array { return ['header' => config('Security')->headerName, 'hash' => csrf_hash()]; }
}
