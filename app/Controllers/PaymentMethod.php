<?php

namespace App\Controllers;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class PaymentMethod extends BaseController
{
    private BaseConnection $db;

    public function initController($request, $response, $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->db = db_connect();
    }

    public function index(): string
    {
        return view('pages/finance/payment_methods', [
            'live' => true,
            'activeMenu' => 'payment-methods',
            'pageTitle' => 'Metode Pembayaran',
            'pageSubtitle' => 'Master metode penerimaan dan pembayaran transaksi',
            'csrfHeader' => config('Security')->headerName,
            'csrfHash' => csrf_hash(),
        ]);
    }

    public function read(): ResponseInterface
    {
        try {
            $rows = $this->db->table('payment_methods')
                ->select('id, code, name, is_active, created_at, updated_at')
                ->orderBy('code', 'ASC')->get()->getResultArray();

            return $this->successResponse($rows);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function create(): ResponseInterface
    {
        try {
            $data = $this->payload();
            $this->validateOrFail($data);
            $this->assertUniqueCode($data['code']);
            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            $this->db->table('payment_methods')->insert([...$data, 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $this->db->insertID();
            $this->writeAudit('PAYMENT_METHOD_CREATED', $id, null, $this->byId($id));
            $this->db->transComplete();
            $this->assertTransaction('Metode pembayaran belum dapat disimpan.');

            return $this->successResponse($this->byId($id), 'Metode pembayaran berhasil ditambahkan.', ResponseInterface::HTTP_CREATED);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function update(int $id): ResponseInterface
    {
        try {
            $existing = $this->byId($id);
            if ($existing === null) {
                return $this->messageResponse('Metode pembayaran tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            $data = $this->payload();
            $this->validateOrFail($data);
            $this->assertUniqueCode($data['code'], $id);
            if ($existing['code'] !== $data['code'] && ($usage = $this->firstUsage($id)) !== null) {
                throw new RuntimeException('Kode metode tidak dapat diubah karena sudah digunakan pada ' . $usage . '.');
            }
            $this->db->transStart();
            $this->db->table('payment_methods')->where('id', $id)->update([...$data, 'updated_at' => date('Y-m-d H:i:s')]);
            $updated = $this->byId($id);
            $this->writeAudit('PAYMENT_METHOD_UPDATED', $id, $existing, $updated);
            $this->db->transComplete();
            $this->assertTransaction('Perubahan metode pembayaran belum dapat disimpan.');

            return $this->successResponse($updated, 'Metode pembayaran berhasil diperbarui.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function activate(int $id): ResponseInterface
    {
        try {
            $existing = $this->byId($id);
            if ($existing === null) {
                return $this->messageResponse('Metode pembayaran tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            $this->db->transStart();
            $this->db->table('payment_methods')->where('id', $id)->update(['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            $updated = $this->byId($id);
            $this->writeAudit('PAYMENT_METHOD_ACTIVATED', $id, $existing, $updated);
            $this->db->transComplete();
            $this->assertTransaction('Status metode pembayaran belum dapat diubah.');

            return $this->successResponse($updated, 'Metode pembayaran berhasil diaktifkan.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function delete(int $id): ResponseInterface
    {
        try {
            $existing = $this->byId($id);
            if ($existing === null) {
                return $this->messageResponse('Metode pembayaran tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            if (($usage = $this->firstUsage($id)) !== null) {
                throw new RuntimeException('Metode pembayaran tidak dapat dihapus karena sudah digunakan pada ' . $usage . '. Nonaktifkan untuk menyimpannya sebagai arsip.');
            }
            $this->db->transStart();
            $this->db->table('payment_methods')->where('id', $id)->delete();
            $this->writeAudit('PAYMENT_METHOD_DELETED', $id, $existing, null);
            $this->db->transComplete();
            $this->assertTransaction('Metode pembayaran belum dapat dihapus.');

            return $this->successResponse(null, 'Metode pembayaran berhasil dihapus.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        $input = is_array($json) ? $json : $this->request->getPost();

        return [
            'code' => strtoupper(trim((string) ($input['code'] ?? ''))),
            'name' => trim((string) ($input['name'] ?? '')),
            'is_active' => filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        ];
    }

    private function validateOrFail(array $data): void
    {
        if (! preg_match('/^[A-Z0-9._-]{2,30}$/', $data['code'])) {
            throw new RuntimeException('Kode wajib 2–30 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.');
        }
        $length = function_exists('mb_strlen') ? mb_strlen($data['name']) : strlen($data['name']);
        if ($length < 3 || $length > 100) {
            throw new RuntimeException('Nama metode wajib terdiri dari 3 sampai 100 karakter.');
        }
    }

    private function assertUniqueCode(string $code, ?int $exceptId = null): void
    {
        $builder = $this->db->table('payment_methods')->where('code', $code);
        if ($exceptId !== null) $builder->where('id !=', $exceptId);
        if ($builder->countAllResults() > 0) throw new RuntimeException('Kode metode pembayaran tersebut sudah digunakan.');
    }

    private function firstUsage(int $id): ?string
    {
        foreach (['student_payments' => 'pembayaran mahasiswa', 'honor_payments' => 'pembayaran honor'] as $table => $label) {
            if ($this->db->table($table)->where('payment_method_id', $id)->countAllResults() > 0) return $label;
        }
        return null;
    }

    private function byId(int $id): ?array { return $this->db->table('payment_methods')->where('id', $id)->get()->getRowArray(); }
    private function assertTransaction(string $message): void { if (! $this->db->transStatus()) throw new RuntimeException($message); }
    private function writeAudit(string $action, int $id, ?array $old, ?array $new): void { $auth = session('auth'); $this->db->table('audit_logs')->insert(['user_id' => is_array($auth) ? ($auth['id'] ?? null) : null, 'action' => $action, 'entity_type' => 'payment_methods', 'entity_id' => $id, 'old_values' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE), 'new_values' => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE), 'ip_address' => $this->request->getIPAddress(), 'created_at' => date('Y-m-d H:i:s')]); }
    private function successResponse(mixed $data = null, ?string $message = null, int $status = 200): ResponseInterface { return $this->response->setStatusCode($status)->setJSON(['ok' => true, 'message' => $message, 'data' => $data, 'csrf' => $this->csrfPayload()]); }
    private function messageResponse(string $message, int $status): ResponseInterface { return $this->response->setStatusCode($status)->setJSON(['ok' => false, 'message' => $message, 'csrf' => $this->csrfPayload()]); }
    private function errorResponse(Throwable $exception): ResponseInterface { return $this->messageResponse($exception instanceof RuntimeException ? $exception->getMessage() : 'Terjadi kesalahan saat memproses metode pembayaran.', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY); }
    private function csrfPayload(): array { return ['header' => config('Security')->headerName, 'hash' => csrf_hash()]; }
}
