<?php

namespace App\Controllers;

use App\Libraries\StudentSpreadsheetImporter;
use App\Libraries\StudentTemplateExporter;
use App\Libraries\StudentAccountService;
use App\Libraries\StudentActivationMailer;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class Student extends BaseController
{
    private const STATUSES = ['AKTIF', 'CUTI', 'LULUS', 'NONAKTIF'];
    private BaseConnection $db;

    public function initController($request, $response, $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->db = db_connect();
    }

    public function index(): string
    {
        return view('pages/academic/students', [
            'live' => true, 'activeMenu' => 'students', 'pageTitle' => 'Mahasiswa',
            'pageSubtitle' => 'Master data mahasiswa untuk kegiatan dan transaksi akademik',
            'statuses' => self::STATUSES, 'csrfHeader' => config('Security')->headerName, 'csrfHash' => csrf_hash(),
        ]);
    }

    public function read(): ResponseInterface
    {
        try {
            $studentQuery = $this->db->table('students s')
                ->select('s.id, s.user_id, s.nim, s.full_name, s.study_program_id, s.cohort_year, s.email, s.phone, s.status, s.created_at, s.updated_at, sp.code AS program_code, sp.name AS program_name, sp.degree_level, u.username AS account_username, u.is_active AS account_is_active')
                ->join('study_programs sp', 'sp.id = s.study_program_id')
                ->join('users u', 'u.id = s.user_id', 'left');
            $this->applyProgramScope($studentQuery, 's.study_program_id');
            $students = $studentQuery->orderBy('s.full_name', 'ASC')->get()->getResultArray();
            $programQuery = $this->db->table('study_programs')->select('id, code, name, degree_level, is_active');
            $this->applyProgramScope($programQuery, 'id');
            $programs = $programQuery->orderBy('code')->get()->getResultArray();

            return $this->successResponse(['students' => $students, 'programs' => $programs]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function create(): ResponseInterface
    {
        try {
            $data = $this->payload();
            $this->validateOrFail($data);
            if ($this->nimExists($data['nim'])) {
                throw new RuntimeException('NIM tersebut sudah digunakan oleh mahasiswa lain.');
            }
            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            $this->db->table('students')->insert([...$data, 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $this->db->insertID();
            $account = (new StudentAccountService($this->db))->provision($id, $this->actorId(), $this->request->getIPAddress());
            $this->writeAudit('STUDENT_CREATED', $id, null, $this->studentById($id));
            $this->db->transComplete();
            $this->assertTransaction('Data mahasiswa belum dapat disimpan.');

            $delivery = $this->sendActivationEmails([$account['activation']]);
            return $this->successResponse([...$this->studentById($id), 'activation_email' => $delivery], $this->deliveryMessage('Data mahasiswa dan akun berhasil ditambahkan.', $delivery), ResponseInterface::HTTP_CREATED);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function update(int $id): ResponseInterface
    {
        try {
            $existing = $this->studentById($id);
            if ($existing === null) {
                return $this->messageResponse('Data mahasiswa tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            $data = $this->payload();
            $this->validateOrFail($data);
            if ($this->nimExists($data['nim'], $id)) {
                throw new RuntimeException('NIM tersebut sudah digunakan oleh mahasiswa lain.');
            }
            $this->db->transStart();
            $this->db->table('students')->where('id', $id)->update([...$data, 'updated_at' => date('Y-m-d H:i:s')]);
            $account = (new StudentAccountService($this->db))->provision($id, $this->actorId(), $this->request->getIPAddress());
            $updated = $this->studentById($id);
            $this->writeAudit('STUDENT_UPDATED', $id, $existing, $updated);
            $this->db->transComplete();
            $this->assertTransaction('Perubahan data mahasiswa belum dapat disimpan.');

            $delivery = $account['activation'] === null ? null : $this->sendActivationEmails([$account['activation']]);
            $response = $delivery === null ? $updated : [...$updated, 'activation_email' => $delivery];
            return $this->successResponse($response, $account['created'] ? $this->deliveryMessage('Data mahasiswa diperbarui dan akun berhasil dibuat.', $delivery) : 'Data mahasiswa berhasil diperbarui.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function activate(int $id): ResponseInterface
    {
        try {
            $student = $this->studentById($id);
            if ($student === null) {
                return $this->messageResponse('Data mahasiswa tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            $this->db->transStart();
            $this->db->table('students')->where('id', $id)->update(['status' => 'AKTIF', 'updated_at' => date('Y-m-d H:i:s')]);
            (new StudentAccountService($this->db))->sync($id);
            $updated = $this->studentById($id);
            $this->writeAudit('STUDENT_ACTIVATED', $id, $student, $updated);
            $this->db->transComplete();
            $this->assertTransaction('Status mahasiswa belum dapat diubah.');

            return $this->successResponse($updated, 'Mahasiswa berhasil diaktifkan.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function delete(int $id): ResponseInterface
    {
        try {
            $student = $this->studentById($id);
            if ($student === null) {
                return $this->messageResponse('Data mahasiswa tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            if (($usage = $this->firstUsage($id)) !== null) {
                throw new RuntimeException('Data mahasiswa tidak dapat dihapus karena sudah digunakan pada ' . $usage . '. Ubah status menjadi NONAKTIF untuk menyimpannya sebagai arsip.');
            }
            $userId = (int) ($student['user_id'] ?? 0);
            $this->db->transStart();
            $this->db->table('students')->where('id', $id)->delete();
            $this->writeAudit('STUDENT_DELETED', $id, $student, null);
            if ($userId > 0) $this->db->table('users')->where(['id' => $userId, 'role' => 'MAHASISWA'])->delete();
            $this->db->transComplete();
            $this->assertTransaction('Data mahasiswa belum dapat dihapus.');

            return $this->successResponse(null, 'Data mahasiswa berhasil dihapus.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function import(): ResponseInterface
    {
        try {
            $file = $this->request->getFile('file');
            if ($file === null || $file->getError() !== UPLOAD_ERR_OK || ! is_file($file->getTempName())) {
                throw new RuntimeException('Pilih berkas Excel .xlsx yang akan diimpor.');
            }
            if (strtolower($file->getClientExtension()) !== 'xlsx') {
                throw new RuntimeException('Format berkas harus .xlsx. Gunakan template Excel yang disediakan.');
            }
            if ($file->getSize() > 2 * 1024 * 1024) {
                throw new RuntimeException('Ukuran berkas Excel maksimal 2 MB.');
            }

            $rows = (new StudentSpreadsheetImporter())->read($file->getTempName());
            $programs = $this->programMap();
            $seen = [];
            $created = 0;
            $updated = 0;
            $accountsCreated = 0;
            $accountsExisting = 0;
            $activations = [];
            $this->db->transBegin();
            try {
                foreach ($rows as $row) {
                    $rowNumber = (int) $row['_row'];
                    $code = strtoupper(trim((string) $row['program_code']));
                    if (! isset($programs[$code])) {
                        throw new RuntimeException('Baris ' . $rowNumber . ': kode program studi ' . ($code ?: '(kosong)') . ' tidak ditemukan atau tidak aktif.');
                    }
                    $data = $this->importedPayload($row, $programs[$code]);
                    $this->validateOrFail($data, $rowNumber);
                    if (isset($seen[$data['nim']])) {
                        throw new RuntimeException('NIM pada baris ' . $rowNumber . ' juga digunakan pada baris ' . $seen[$data['nim']] . '.');
                    }
                    $seen[$data['nim']] = $rowNumber;
                    $existing = $this->db->table('students')->where('nim', $data['nim'])->get()->getRowArray();
                    $now = date('Y-m-d H:i:s');
                    if ($existing === null) {
                        $this->db->table('students')->insert([...$data, 'created_at' => $now, 'updated_at' => $now]);
                        $id = (int) $this->db->insertID();
                        $account = (new StudentAccountService($this->db))->provision($id, $this->actorId(), $this->request->getIPAddress());
                        $accountsCreated++;
                        if ($account['activation'] !== null) $activations[] = $account['activation'];
                        $this->writeAudit('STUDENT_IMPORTED', $id, null, $this->studentById($id));
                        $created++;
                    } else {
                        $id = (int) $existing['id'];
                        $old = $this->studentById($id);
                        $this->db->table('students')->where('id', $id)->update([...$data, 'updated_at' => $now]);
                        $account = (new StudentAccountService($this->db))->provision($id, $this->actorId(), $this->request->getIPAddress());
                        if ($account['created']) {
                            $accountsCreated++;
                            if ($account['activation'] !== null) $activations[] = $account['activation'];
                        } else {
                            $accountsExisting++;
                        }
                        $this->writeAudit('STUDENT_IMPORT_UPDATED', $id, $old, $this->studentById($id));
                        $updated++;
                    }
                }
                if (! $this->db->transStatus()) {
                    throw new RuntimeException('Data mahasiswa dari Excel belum dapat disimpan.');
                }
                $this->db->transCommit();
            } catch (Throwable $exception) {
                $this->db->transRollback();
                throw $exception;
            }

            $delivery = $this->sendActivationEmails($activations);
            return $this->successResponse(['total' => count($rows), 'created' => $created, 'updated' => $updated, 'accounts_created' => $accountsCreated, 'accounts_existing' => $accountsExisting, 'activation_email' => $delivery], $this->deliveryMessage(sprintf('Impor selesai: %d mahasiswa baru, %d diperbarui, dan %d akun dibuat.', $created, $updated, $accountsCreated), $delivery));
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function template(): ResponseInterface
    {
        try {
            $programQuery = $this->db->table('study_programs')->select('code, name, degree_level, is_active'); $this->applyProgramScope($programQuery, 'id');
            $programs = $programQuery->orderBy('code', 'ASC')->get()->getResultArray();
            $temporary = (new StudentTemplateExporter())->export($programs);
            $contents = file_get_contents($temporary);
            @unlink($temporary);
            if ($contents === false) {
                throw new RuntimeException('Template impor mahasiswa belum dapat diunduh.');
            }

            return $this->response->download('template-import-mahasiswa.xlsx', $contents);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function activation(int $id): ResponseInterface
    {
        try {
            $student = $this->studentById($id);
            if ($student === null) return $this->messageResponse('Data mahasiswa tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            $this->db->transBegin();
            try {
                $service = new StudentAccountService($this->db);
                $account = $service->provision($id, $this->actorId(), $this->request->getIPAddress());
                $activation = $account['activation'] ?? $service->issueActivation($id, $this->actorId(), $this->request->getIPAddress());
                if (! $this->db->transStatus()) throw new RuntimeException('Kode aktivasi belum dapat dibuat.');
                $this->db->transCommit();
            } catch (Throwable $exception) {
                $this->db->transRollback();
                throw $exception;
            }
            $delivery = $this->sendActivationEmails([$activation]);
            return $this->successResponse(['email' => $activation['email'], 'activation_email' => $delivery], $this->deliveryMessage('Kode aktivasi baru dibuat.', $delivery));
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        $input = is_array($json) ? $json : $this->request->getPost();

        return [
            'nim' => strtoupper(trim((string) ($input['nim'] ?? ''))),
            'full_name' => trim((string) ($input['full_name'] ?? '')),
            'study_program_id' => (int) ($input['study_program_id'] ?? 0),
            'cohort_year' => $this->nullableInteger($input['cohort_year'] ?? null),
            'email' => $this->nullableLowercaseString($input['email'] ?? null),
            'phone' => $this->nullableString($input['phone'] ?? null),
            'status' => strtoupper(trim((string) ($input['status'] ?? 'AKTIF'))),
        ];
    }

    private function importedPayload(array $row, int $programId): array
    {
        return [
            'nim' => strtoupper(trim((string) $row['nim'])), 'full_name' => trim((string) $row['full_name']),
            'study_program_id' => $programId, 'cohort_year' => $this->nullableInteger($row['cohort_year'] ?? null),
            'email' => $this->nullableLowercaseString($row['email'] ?? null), 'phone' => $this->nullableString($row['phone'] ?? null),
            'status' => strtoupper(trim((string) ($row['status'] ?: 'AKTIF'))),
        ];
    }

    private function validateOrFail(array $data, ?int $row = null): void
    {
        $prefix = $row === null ? '' : 'Baris ' . $row . ': ';
        if (! preg_match('/^[A-Z0-9._\/-]{3,30}$/', $data['nim'])) {
            throw new RuntimeException($prefix . 'NIM wajib diisi dan formatnya tidak valid.');
        }
        $length = function_exists('mb_strlen') ? mb_strlen($data['full_name']) : strlen($data['full_name']);
        if ($length < 3 || $length > 200) {
            throw new RuntimeException($prefix . 'nama lengkap wajib terdiri dari 3 sampai 200 karakter.');
        }
        if (! $this->activeProgramExists((int) $data['study_program_id'])) {
            throw new RuntimeException($prefix . 'program studi tidak ditemukan atau sedang nonaktif.');
        }
        $year = $data['cohort_year'];
        if ($year !== null && ($year < 1900 || $year > ((int) date('Y') + 1))) {
            throw new RuntimeException($prefix . 'tahun angkatan tidak valid.');
        }
        if ($data['email'] === null || filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException($prefix . 'alamat email wajib diisi dan harus valid untuk pengiriman kode aktivasi.');
        }
        if ($data['phone'] !== null && ! preg_match('/^[0-9+() .-]{6,30}$/', $data['phone'])) {
            throw new RuntimeException($prefix . 'nomor telepon tidak valid.');
        }
        if (! in_array($data['status'], self::STATUSES, true)) {
            throw new RuntimeException($prefix . 'status harus AKTIF, CUTI, LULUS, atau NONAKTIF.');
        }
    }

    private function programMap(): array
    {
        $map = [];
        $query = $this->db->table('study_programs')->select('id, code')->where('is_active', 1); $this->applyProgramScope($query, 'id');
        foreach ($query->get()->getResultArray() as $program) {
            $map[strtoupper($program['code'])] = (int) $program['id'];
        }
        return $map;
    }

    private function activeProgramExists(int $id): bool { $query = $this->db->table('study_programs')->where(['id' => $id, 'is_active' => 1]); $this->applyProgramScope($query, 'id'); return $id > 0 && $query->countAllResults() > 0; }
    private function nimExists(string $nim, ?int $exceptId = null): bool { $builder = $this->db->table('students')->where('nim', $nim); if ($exceptId !== null) $builder->where('id !=', $exceptId); return $builder->countAllResults() > 0; }
    private function nullableString(mixed $value): ?string { $value = trim((string) $value); return $value === '' ? null : $value; }
    private function nullableLowercaseString(mixed $value): ?string { $value = $this->nullableString($value); return $value === null ? null : strtolower($value); }
    private function nullableInteger(mixed $value): ?int { $value = trim((string) $value); return $value === '' ? null : (filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : -1); }

    private function studentById(int $id): ?array
    {
        $query = $this->db->table('students s')->select('s.*, sp.code AS program_code, sp.name AS program_name, sp.degree_level')->join('study_programs sp', 'sp.id = s.study_program_id')->where('s.id', $id); $this->applyProgramScope($query, 's.study_program_id'); return $query->get()->getRowArray();
    }

    private function firstUsage(int $id): ?string
    {
        foreach (['academic_activities' => 'kegiatan akademik', 'student_bills' => 'tagihan mahasiswa', 'student_payments' => 'pembayaran mahasiswa'] as $table => $label) {
            if ($this->db->table($table)->where('student_id', $id)->countAllResults() > 0) return $label;
        }
        return null;
    }

    private function applyProgramScope($query, string $column): void
    {
        $auth = session('auth');
        if (! is_array($auth) || ($auth['role'] ?? null) !== 'PRODI') return;
        if (! $this->db->tableExists('user_study_programs')) { $query->where($column, 0); return; }
        $ids = array_column($this->db->table('user_study_programs')->select('study_program_id')->where('user_id', (int) ($auth['id'] ?? 0))->get()->getResultArray(), 'study_program_id');
        $query->whereIn($column, $ids === [] ? [0] : array_map('intval', $ids));
    }

    private function assertTransaction(string $message): void { if (! $this->db->transStatus()) throw new RuntimeException($message); }
    /** @param list<array|null> $activations */
    private function sendActivationEmails(array $activations): array
    {
        $sent = [];
        $failed = [];
        $mailer = new StudentActivationMailer();
        foreach ($activations as $activation) {
            if (! is_array($activation)) continue;
            try {
                $mailer->send($activation);
                $sent[] = $activation['email'];
            } catch (Throwable $exception) {
                log_message('error', 'Pengiriman aktivasi mahasiswa gagal untuk {email}: {message}', ['email' => $activation['email'] ?? '-', 'message' => $exception->getMessage()]);
                $failed[] = $activation['email'] ?? '-';
            }
        }
        return ['sent' => count($sent), 'failed' => count($failed), 'sent_to' => $sent, 'failed_to' => $failed];
    }
    private function deliveryMessage(string $prefix, array $delivery): string
    {
        if ($delivery['sent'] === 0 && $delivery['failed'] === 0) return $prefix;
        if ($delivery['failed'] === 0) return $prefix . ' Email kode aktivasi berhasil dikirim ke ' . $delivery['sent'] . ' mahasiswa.';
        if ($delivery['sent'] === 0) return $prefix . ' Kode dibuat, tetapi email belum terkirim. Periksa SMTP lalu kirim ulang kode aktivasi.';
        return $prefix . ' Email terkirim ke ' . $delivery['sent'] . ' mahasiswa; ' . $delivery['failed'] . ' email belum terkirim.';
    }
    private function actorId(): ?int { $auth = session('auth'); return is_array($auth) ? (int) ($auth['id'] ?? 0) ?: null : null; }
    private function writeAudit(string $action, int $id, ?array $old, ?array $new): void
    {
        $auth = session('auth');
        $this->db->table('audit_logs')->insert(['user_id' => is_array($auth) ? ($auth['id'] ?? null) : null, 'action' => $action, 'entity_type' => 'students', 'entity_id' => $id, 'old_values' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE), 'new_values' => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE), 'ip_address' => $this->request->getIPAddress(), 'created_at' => date('Y-m-d H:i:s')]);
    }

    private function successResponse(mixed $data = null, ?string $message = null, int $status = 200): ResponseInterface { return $this->response->setStatusCode($status)->setJSON(['ok' => true, 'message' => $message, 'data' => $data, 'csrf' => $this->csrfPayload()]); }
    private function messageResponse(string $message, int $status): ResponseInterface { return $this->response->setStatusCode($status)->setJSON(['ok' => false, 'message' => $message, 'csrf' => $this->csrfPayload()]); }
    private function errorResponse(Throwable $exception): ResponseInterface { return $this->messageResponse($exception instanceof RuntimeException ? $exception->getMessage() : 'Terjadi kesalahan saat memproses data mahasiswa.', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY); }
    private function csrfPayload(): array { return ['header' => config('Security')->headerName, 'hash' => csrf_hash()]; }
}
