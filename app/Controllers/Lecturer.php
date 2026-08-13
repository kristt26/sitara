<?php

namespace App\Controllers;

use App\Libraries\LecturerSpreadsheetImporter;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class Lecturer extends BaseController
{
    private BaseConnection $db;

    public function initController($request, $response, $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->db = db_connect();
    }

    public function index(): string
    {
        return view('pages/academic/lecturers', [
            'live'         => true,
            'activeMenu'   => 'lecturers',
            'pageTitle'    => 'Dosen',
            'pageSubtitle' => 'Master data dosen untuk penugasan dan pembayaran honor',
            'csrfHeader'   => config('Security')->headerName,
            'csrfHash'     => csrf_hash(),
        ]);
    }

    public function read(): ResponseInterface
    {
        try {
            $lecturers = $this->db->table('lecturers')
                ->select('id, nidn, nip, full_name, email, phone, bank_name, bank_account_number, bank_account_name, tax_id, is_active, created_at, updated_at')
                ->orderBy('full_name', 'ASC')
                ->get()
                ->getResultArray();

            return $this->successResponse($lecturers);
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
            $this->assertUniqueIdentifiers($data);

            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            $this->db->table('lecturers')->insert([
                ...$data,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $lecturerId = (int) $this->db->insertID();
            $this->writeAudit('LECTURER_CREATED', $lecturerId, null, $this->lecturerById($lecturerId));
            $this->db->transComplete();
            $this->assertTransaction('Data dosen belum dapat disimpan.');

            return $this->successResponse(
                $this->lecturerById($lecturerId),
                'Data dosen berhasil ditambahkan.',
                ResponseInterface::HTTP_CREATED,
            );
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

            $rows = (new LecturerSpreadsheetImporter())->read($file->getTempName());
            $created = 0;
            $updated = 0;
            $seenNidn = [];
            $seenNip = [];

            $this->db->transBegin();
            try {
                foreach ($rows as $row) {
                    $rowNumber = (int) $row['_row'];
                    $data = $this->importedPayload($row);
                    if (($error = $this->validatePayload($data)) !== null) {
                        throw new RuntimeException('Baris ' . $rowNumber . ': ' . $error);
                    }

                    if ($data['nidn'] !== null && isset($seenNidn[$data['nidn']])) {
                        throw new RuntimeException('NIDN pada baris ' . $rowNumber . ' juga digunakan pada baris ' . $seenNidn[$data['nidn']] . '.');
                    }
                    if ($data['nip'] !== null && isset($seenNip[$data['nip']])) {
                        throw new RuntimeException('NIP pada baris ' . $rowNumber . ' juga digunakan pada baris ' . $seenNip[$data['nip']] . '.');
                    }
                    if ($data['nidn'] !== null) {
                        $seenNidn[$data['nidn']] = $rowNumber;
                    }
                    if ($data['nip'] !== null) {
                        $seenNip[$data['nip']] = $rowNumber;
                    }

                    $existing = $this->findImportedLecturer($data, $rowNumber);
                    $this->assertUniqueIdentifiers($data, $existing === null ? null : (int) $existing['id']);
                    $now = date('Y-m-d H:i:s');

                    if ($existing === null) {
                        $this->db->table('lecturers')->insert([
                            ...$data,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $lecturerId = (int) $this->db->insertID();
                        $this->writeAudit('LECTURER_IMPORTED', $lecturerId, null, $this->lecturerById($lecturerId));
                        $created++;
                        continue;
                    }

                    $lecturerId = (int) $existing['id'];
                    $this->db->table('lecturers')->where('id', $lecturerId)->update([
                        ...$data,
                        'updated_at' => $now,
                    ]);
                    $this->writeAudit('LECTURER_IMPORT_UPDATED', $lecturerId, $existing, $this->lecturerById($lecturerId));
                    $updated++;
                }

                if (! $this->db->transStatus()) {
                    throw new RuntimeException('Data dosen dari Excel belum dapat disimpan.');
                }
                $this->db->transCommit();
            } catch (Throwable $exception) {
                $this->db->transRollback();
                throw $exception;
            }

            return $this->successResponse([
                'total'   => count($rows),
                'created' => $created,
                'updated' => $updated,
            ], sprintf(
                'Impor selesai: %d dosen baru dan %d dosen diperbarui.',
                $created,
                $updated,
            ));
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function update(int $id): ResponseInterface
    {
        try {
            $existing = $this->lecturerById($id);
            if ($existing === null) {
                return $this->messageResponse('Data dosen tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }

            $data = $this->payload();
            if (($error = $this->validatePayload($data)) !== null) {
                throw new RuntimeException($error);
            }
            $this->assertUniqueIdentifiers($data, $id);

            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            $this->db->table('lecturers')->where('id', $id)->update([
                ...$data,
                'updated_at' => $now,
            ]);
            $updated = $this->lecturerById($id);
            $this->writeAudit('LECTURER_UPDATED', $id, $existing, $updated);
            $this->db->transComplete();
            $this->assertTransaction('Perubahan data dosen belum dapat disimpan.');

            return $this->successResponse($updated, 'Data dosen berhasil diperbarui.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function activate(int $id): ResponseInterface
    {
        try {
            $lecturer = $this->lecturerById($id);
            if ($lecturer === null) {
                return $this->messageResponse('Data dosen tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            if ((int) $lecturer['is_active'] === 1) {
                return $this->successResponse($lecturer, 'Dosen tersebut sudah aktif.');
            }

            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            $this->db->table('lecturers')->where('id', $id)->update([
                'is_active' => 1,
                'updated_at' => $now,
            ]);
            $updated = $this->lecturerById($id);
            $this->writeAudit('LECTURER_ACTIVATED', $id, $lecturer, $updated);
            $this->db->transComplete();
            $this->assertTransaction('Status dosen belum dapat diubah.');

            return $this->successResponse($updated, 'Dosen berhasil diaktifkan.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function delete(int $id): ResponseInterface
    {
        try {
            $lecturer = $this->lecturerById($id);
            if ($lecturer === null) {
                return $this->messageResponse('Data dosen tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            if (($usage = $this->firstUsage($id)) !== null) {
                throw new RuntimeException('Data dosen tidak dapat dihapus karena sudah digunakan pada ' . $usage . '. Nonaktifkan dosen untuk menyimpannya sebagai arsip.');
            }

            $this->db->transStart();
            $this->db->table('lecturers')->where('id', $id)->delete();
            $this->writeAudit('LECTURER_DELETED', $id, $lecturer, null);
            $this->db->transComplete();
            $this->assertTransaction('Data dosen belum dapat dihapus.');

            return $this->successResponse(null, 'Data dosen berhasil dihapus.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    /** @return array<string, string|int|null> */
    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        $input = is_array($json) ? $json : $this->request->getPost();

        return [
            'nidn'                => $this->nullableString($input['nidn'] ?? null),
            'nip'                 => $this->nullableString($input['nip'] ?? null),
            'full_name'           => trim((string) ($input['full_name'] ?? '')),
            'email'               => $this->nullableLowercaseString($input['email'] ?? null),
            'phone'               => $this->nullableString($input['phone'] ?? null),
            'bank_name'           => $this->nullableString($input['bank_name'] ?? null),
            'bank_account_number' => $this->nullableString($input['bank_account_number'] ?? null),
            'bank_account_name'   => $this->nullableString($input['bank_account_name'] ?? null),
            'tax_id'              => $this->nullableString($input['tax_id'] ?? null),
            'is_active'           => filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        ];
    }

    private function validatePayload(array $data): ?string
    {
        if ($data['nidn'] === null && $data['nip'] === null) {
            return 'NIDN atau NIP wajib diisi.';
        }
        if ($data['nidn'] !== null && ! preg_match('/^[A-Za-z0-9._\/-]{3,30}$/', $data['nidn'])) {
            return 'Format NIDN tidak valid.';
        }
        if ($data['nip'] !== null && ! preg_match('/^[A-Za-z0-9._\/-]{3,50}$/', $data['nip'])) {
            return 'Format NIP tidak valid.';
        }
        $nameLength = function_exists('mb_strlen') ? mb_strlen($data['full_name']) : strlen($data['full_name']);
        if ($nameLength < 3 || $nameLength > 200) {
            return 'Nama lengkap dosen wajib terdiri dari 3 sampai 200 karakter.';
        }
        if ($data['email'] !== null && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            return 'Alamat email dosen tidak valid.';
        }
        if ($data['phone'] !== null && ! preg_match('/^[0-9+() .-]{6,30}$/', $data['phone'])) {
            return 'Nomor telepon dosen tidak valid.';
        }
        if (($data['bank_account_number'] === null) !== ($data['bank_name'] === null)) {
            return 'Nama bank dan nomor rekening harus diisi bersamaan.';
        }
        if ($data['bank_account_number'] !== null && $data['bank_account_name'] === null) {
            return 'Nama pemilik rekening wajib diisi ketika data rekening tersedia.';
        }

        return null;
    }

    /** @param array<string, string|int> $row */
    private function importedPayload(array $row): array
    {
        $status = strtoupper(trim((string) ($row['status'] ?? '')));
        if (in_array($status, ['', 'AKTIF', 'ACTIVE', '1', 'YA'], true)) {
            $isActive = 1;
        } elseif (in_array($status, ['NONAKTIF', 'NON-AKTIF', 'INACTIVE', 'ARSIP', '0', 'TIDAK'], true)) {
            $isActive = 0;
        } else {
            throw new RuntimeException('Baris ' . (int) $row['_row'] . ': status harus AKTIF atau NONAKTIF.');
        }

        return [
            'nidn'                => $this->nullableString($row['nidn'] ?? null),
            'nip'                 => $this->nullableString($row['nip'] ?? null),
            'full_name'           => trim((string) ($row['full_name'] ?? '')),
            'email'               => $this->nullableLowercaseString($row['email'] ?? null),
            'phone'               => $this->nullableString($row['phone'] ?? null),
            'bank_name'           => $this->nullableString($row['bank_name'] ?? null),
            'bank_account_number' => $this->nullableString($row['bank_account_number'] ?? null),
            'bank_account_name'   => $this->nullableString($row['bank_account_name'] ?? null),
            'tax_id'              => $this->nullableString($row['tax_id'] ?? null),
            'is_active'           => $isActive,
        ];
    }

    private function findImportedLecturer(array $data, int $rowNumber): ?array
    {
        $matches = [];
        foreach (['nidn', 'nip'] as $field) {
            if ($data[$field] === null) {
                continue;
            }
            $match = $this->db->table('lecturers')->where($field, $data[$field])->get()->getRowArray();
            if ($match !== null) {
                $matches[(int) $match['id']] = $match;
            }
        }

        if (count($matches) > 1) {
            throw new RuntimeException('Baris ' . $rowNumber . ': NIDN dan NIP terhubung ke dua dosen yang berbeda.');
        }

        return $matches === [] ? null : reset($matches);
    }

    private function assertUniqueIdentifiers(array $data, ?int $exceptId = null): void
    {
        foreach (['nidn' => 'NIDN', 'nip' => 'NIP'] as $field => $label) {
            if ($data[$field] === null) {
                continue;
            }
            $builder = $this->db->table('lecturers')->where($field, $data[$field]);
            if ($exceptId !== null) {
                $builder->where('id !=', $exceptId);
            }
            if ($builder->countAllResults() > 0) {
                throw new RuntimeException($label . ' tersebut sudah digunakan oleh dosen lain.');
            }
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function nullableLowercaseString(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        return $value === null ? null : strtolower($value);
    }

    private function lecturerById(int $id): ?array
    {
        return $this->db->table('lecturers')->where('id', $id)->get()->getRowArray();
    }

    private function firstUsage(int $lecturerId): ?string
    {
        $dependencies = [
            'activity_assignments' => 'penugasan kegiatan akademik',
            'honor_payments'       => 'pembayaran honor',
        ];

        foreach ($dependencies as $table => $label) {
            if ($this->db->table($table)->where('lecturer_id', $lecturerId)->countAllResults() > 0) {
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
            'entity_type' => 'lecturers',
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
            : 'Terjadi kesalahan saat memproses data dosen.';

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
