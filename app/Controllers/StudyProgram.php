<?php

namespace App\Controllers;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class StudyProgram extends BaseController
{
    private const DEGREE_LEVELS = ['D3', 'D4', 'S1', 'S2', 'S3', 'PROFESI'];

    private BaseConnection $db;

    public function initController($request, $response, $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->db = db_connect();
    }

    public function index(): string
    {
        return view('pages/academic/programs', [
            'live'         => true,
            'activeMenu'   => 'programs',
            'pageTitle'    => 'Program Studi',
            'pageSubtitle' => 'Master program studi untuk kegiatan dan transaksi akademik',
            'degreeLevels' => self::DEGREE_LEVELS,
            'csrfHeader'   => config('Security')->headerName,
            'csrfHash'     => csrf_hash(),
        ]);
    }

    public function read(): ResponseInterface
    {
        try {
            $programs = $this->db->table('study_programs')
                ->select('id, code, name, degree_level, is_active, created_at, updated_at')
                ->orderBy('code', 'ASC')
                ->get()
                ->getResultArray();

            return $this->successResponse($programs);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function create(): ResponseInterface
    {
        try {
            $data = $this->payload();
            if (($error = $this->validatePayload($data)) !== null) {
                throw new RuntimeException($error);
            }
            if ($this->codeExists($data['code'])) {
                throw new RuntimeException('Kode program studi tersebut sudah digunakan.');
            }

            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            $this->db->table('study_programs')->insert([
                ...$data,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $programId = (int) $this->db->insertID();
            $this->writeAudit('STUDY_PROGRAM_CREATED', $programId, null, $this->programById($programId));
            $this->db->transComplete();
            $this->assertTransaction('Program studi belum dapat disimpan.');

            return $this->successResponse(
                $this->programById($programId),
                'Program studi berhasil ditambahkan.',
                ResponseInterface::HTTP_CREATED,
            );
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function update(int $id): ResponseInterface
    {
        try {
            $existing = $this->programById($id);
            if ($existing === null) {
                return $this->messageResponse('Program studi tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }

            $data = $this->payload();
            if (($error = $this->validatePayload($data)) !== null) {
                throw new RuntimeException($error);
            }
            if ($this->codeExists($data['code'], $id)) {
                throw new RuntimeException('Kode program studi tersebut sudah digunakan.');
            }

            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            $this->db->table('study_programs')->where('id', $id)->update([
                ...$data,
                'updated_at' => $now,
            ]);
            $updated = $this->programById($id);
            $this->writeAudit('STUDY_PROGRAM_UPDATED', $id, $existing, $updated);
            $this->db->transComplete();
            $this->assertTransaction('Perubahan program studi belum dapat disimpan.');

            return $this->successResponse($updated, 'Program studi berhasil diperbarui.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function activate(int $id): ResponseInterface
    {
        try {
            $program = $this->programById($id);
            if ($program === null) {
                return $this->messageResponse('Program studi tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            if ((int) $program['is_active'] === 1) {
                return $this->successResponse($program, 'Program studi tersebut sudah aktif.');
            }

            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            $this->db->table('study_programs')->where('id', $id)->update([
                'is_active' => 1,
                'updated_at' => $now,
            ]);
            $updated = $this->programById($id);
            $this->writeAudit('STUDY_PROGRAM_ACTIVATED', $id, $program, $updated);
            $this->db->transComplete();
            $this->assertTransaction('Status program studi belum dapat diubah.');

            return $this->successResponse($updated, 'Program studi berhasil diaktifkan.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function delete(int $id): ResponseInterface
    {
        try {
            $program = $this->programById($id);
            if ($program === null) {
                return $this->messageResponse('Program studi tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            if (($usage = $this->firstUsage($id)) !== null) {
                throw new RuntimeException('Program studi tidak dapat dihapus karena sudah digunakan pada ' . $usage . '. Nonaktifkan program studi untuk menyimpannya sebagai arsip.');
            }

            $this->db->transStart();
            $this->db->table('study_programs')->where('id', $id)->delete();
            $this->writeAudit('STUDY_PROGRAM_DELETED', $id, $program, null);
            $this->db->transComplete();
            $this->assertTransaction('Program studi belum dapat dihapus.');

            return $this->successResponse(null, 'Program studi berhasil dihapus.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    /** @return array{code: string, name: string, degree_level: string, is_active: int} */
    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        $input = is_array($json) ? $json : $this->request->getPost();

        return [
            'code'         => strtoupper(trim((string) ($input['code'] ?? ''))),
            'name'         => trim((string) ($input['name'] ?? '')),
            'degree_level' => strtoupper(trim((string) ($input['degree_level'] ?? ''))),
            'is_active'    => filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        ];
    }

    private function validatePayload(array $data): ?string
    {
        if (! preg_match('/^[A-Z0-9._-]{1,20}$/', $data['code'])) {
            return 'Kode program studi wajib diisi dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.';
        }
        $nameLength = function_exists('mb_strlen') ? mb_strlen($data['name']) : strlen($data['name']);
        if ($nameLength < 3 || $nameLength > 150) {
            return 'Nama program studi wajib terdiri dari 3 sampai 150 karakter.';
        }
        if (! in_array($data['degree_level'], self::DEGREE_LEVELS, true)) {
            return 'Jenjang program studi tidak valid.';
        }

        return null;
    }

    private function codeExists(string $code, ?int $exceptId = null): bool
    {
        $builder = $this->db->table('study_programs')->where('code', $code);
        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    private function programById(int $id): ?array
    {
        return $this->db->table('study_programs')->where('id', $id)->get()->getRowArray();
    }

    private function firstUsage(int $programId): ?string
    {
        $dependencies = [
            'students'            => 'data mahasiswa',
            'activity_rules'      => 'aturan kegiatan',
            'fee_settings'        => 'pengaturan tarif',
            'honor_rate_settings' => 'tarif honor',
            'academic_activities' => 'kegiatan akademik mahasiswa',
            'student_bills'       => 'tagihan mahasiswa',
        ];

        foreach ($dependencies as $table => $label) {
            if ($this->db->table($table)->where('study_program_id', $programId)->countAllResults() > 0) {
                return $label;
            }
        }

        return null;
    }

    private function assertTransaction(string $message): void
    {
        if (! $this->db->transStatus()) {
            throw new RuntimeException($message);
        }
    }

    private function writeAudit(string $action, int $entityId, ?array $oldValues, ?array $newValues): void
    {
        $auth = session('auth');
        $this->db->table('audit_logs')->insert([
            'user_id'     => is_array($auth) ? ($auth['id'] ?? null) : null,
            'action'      => $action,
            'entity_type' => 'study_programs',
            'entity_id'   => $entityId,
            'old_values'  => $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE),
            'new_values'  => $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE),
            'ip_address'  => $this->request->getIPAddress(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function successResponse(mixed $data = null, ?string $message = null, int $status = ResponseInterface::HTTP_OK): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'ok'      => true,
            'message' => $message,
            'data'    => $data,
            'csrf'    => $this->csrfPayload(),
        ]);
    }

    private function messageResponse(string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'ok'      => false,
            'message' => $message,
            'csrf'    => $this->csrfPayload(),
        ]);
    }

    private function errorResponse(Throwable $exception): ResponseInterface
    {
        $message = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Terjadi kesalahan saat memproses data program studi.';

        return $this->messageResponse($message, ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @return array{header: string, hash: string} */
    private function csrfPayload(): array
    {
        return [
            'header' => config('Security')->headerName,
            'hash'   => csrf_hash(),
        ];
    }
}
