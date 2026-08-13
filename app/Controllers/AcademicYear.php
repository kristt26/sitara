<?php

namespace App\Controllers;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class AcademicYear extends BaseController
{
    private BaseConnection $db;

    public function initController($request, $response, $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->db = db_connect();
    }

    public function index(): string
    {
        return view('pages/academic/periods', [
            'live'         => true,
            'activeMenu'   => 'periods',
            'pageTitle'    => 'Tahun Akademik',
            'pageSubtitle' => 'Pengaturan tahun akademik dan periode semester',
            'csrfHeader'   => config('Security')->headerName,
            'csrfHash'     => csrf_hash(),
        ]);
    }

    public function read(): ResponseInterface
    {
        try {
            $years = $this->db->table('academic_years ay')
                ->select('ay.id, ay.code, ay.start_year, ay.end_year, ay.is_active, ay.created_at, ay.updated_at, COUNT(ap.id) AS period_count', false)
                ->join('academic_periods ap', 'ap.academic_year_id = ay.id', 'left')
                ->groupBy('ay.id, ay.code, ay.start_year, ay.end_year, ay.is_active, ay.created_at, ay.updated_at')
                ->orderBy('ay.start_year', 'DESC')
                ->get()
                ->getResultArray();

            return $this->successResponse($years);
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
            if ($this->db->table('academic_years')->where('code', $data['code'])->countAllResults() > 0) {
                throw new RuntimeException('Kode tahun akademik tersebut sudah digunakan.');
            }

            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            if ($data['is_active'] === 1) {
                $this->deactivateOtherYears(0, $now);
                $this->deactivatePeriodsOutsideYear(0, $now);
            }
            $this->db->table('academic_years')->insert([
                ...$data,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $yearId = (int) $this->db->insertID();
            $this->writeAudit('ACADEMIC_YEAR_CREATED', $yearId, null, $this->yearById($yearId));
            $this->db->transComplete();
            $this->assertTransaction('Tahun akademik belum dapat disimpan.');

            return $this->successResponse(
                $this->yearWithPeriodCount($yearId),
                'Tahun akademik berhasil ditambahkan.',
                ResponseInterface::HTTP_CREATED,
            );
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function update(int $id): ResponseInterface
    {
        try {
            $existing = $this->yearById($id);
            if ($existing === null) {
                return $this->messageResponse('Tahun akademik tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }

            $data = $this->payload();
            if (($error = $this->validatePayload($data)) !== null) {
                throw new RuntimeException($error);
            }
            if ($this->db->table('academic_years')->where('code', $data['code'])->where('id !=', $id)->countAllResults() > 0) {
                throw new RuntimeException('Kode tahun akademik tersebut sudah digunakan.');
            }
            if ((int) $existing['is_active'] === 1 && $data['is_active'] === 0 && $this->activeYearCount() <= 1) {
                throw new RuntimeException('Setidaknya harus ada satu tahun akademik yang aktif.');
            }

            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            if ($data['is_active'] === 1) {
                $this->deactivateOtherYears($id, $now);
                $this->deactivatePeriodsOutsideYear($id, $now);
            }
            $this->db->table('academic_years')->where('id', $id)->update([
                ...$data,
                'updated_at' => $now,
            ]);
            $updated = $this->yearById($id);
            $this->writeAudit('ACADEMIC_YEAR_UPDATED', $id, $existing, $updated);
            $this->db->transComplete();
            $this->assertTransaction('Perubahan tahun akademik belum dapat disimpan.');

            return $this->successResponse($this->yearWithPeriodCount($id), 'Tahun akademik berhasil diperbarui.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function activate(int $id): ResponseInterface
    {
        try {
            $year = $this->yearById($id);
            if ($year === null) {
                return $this->messageResponse('Tahun akademik tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            if ((int) $year['is_active'] === 1) {
                return $this->successResponse($this->yearWithPeriodCount($id), 'Tahun akademik tersebut sudah aktif.');
            }

            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            $this->deactivateOtherYears($id, $now);
            $this->deactivatePeriodsOutsideYear($id, $now);
            $this->db->table('academic_years')->where('id', $id)->update([
                'is_active' => 1,
                'updated_at' => $now,
            ]);
            $this->writeAudit('ACADEMIC_YEAR_ACTIVATED', $id, $year, $this->yearById($id));
            $this->db->transComplete();
            $this->assertTransaction('Status tahun akademik belum dapat diubah.');

            return $this->successResponse($this->yearWithPeriodCount($id), 'Tahun akademik aktif berhasil diperbarui.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function delete(int $id): ResponseInterface
    {
        try {
            $year = $this->yearById($id);
            if ($year === null) {
                return $this->messageResponse('Tahun akademik tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            if ($this->db->table('academic_periods')->where('academic_year_id', $id)->countAllResults() > 0) {
                throw new RuntimeException('Tahun akademik tidak dapat dihapus karena sudah memiliki periode akademik.');
            }
            if ((int) $year['is_active'] === 1 && $this->activeYearCount() <= 1) {
                throw new RuntimeException('Tahun akademik aktif terakhir tidak dapat dihapus. Aktifkan tahun lain terlebih dahulu.');
            }

            $this->db->transStart();
            $this->db->table('academic_years')->where('id', $id)->delete();
            $this->writeAudit('ACADEMIC_YEAR_DELETED', $id, $year, null);
            $this->db->transComplete();
            $this->assertTransaction('Tahun akademik belum dapat dihapus.');

            return $this->successResponse(null, 'Tahun akademik berhasil dihapus.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    /** @return array{code: string, start_year: int, end_year: int, is_active: int} */
    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        $input = is_array($json) ? $json : $this->request->getPost();

        return [
            'code'       => strtoupper(trim((string) ($input['code'] ?? ''))),
            'start_year' => (int) ($input['start_year'] ?? 0),
            'end_year'   => (int) ($input['end_year'] ?? 0),
            'is_active'  => $this->toBoolean($input['is_active'] ?? false) ? 1 : 0,
        ];
    }

    private function validatePayload(array $data): ?string
    {
        if (! preg_match('/^\d{4}\/\d{4}$/', $data['code'])) {
            return 'Kode tahun akademik harus menggunakan format YYYY/YYYY, misalnya 2026/2027.';
        }
        if ($data['start_year'] < 2000 || $data['end_year'] !== $data['start_year'] + 1) {
            return 'Tahun akhir harus tepat satu tahun setelah tahun awal.';
        }
        if ($data['code'] !== sprintf('%04d/%04d', $data['start_year'], $data['end_year'])) {
            return 'Kode tahun akademik harus sesuai dengan tahun awal dan tahun akhir.';
        }

        return null;
    }

    private function toBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function activeYearCount(): int
    {
        return $this->db->table('academic_years')->where('is_active', 1)->countAllResults();
    }

    private function yearById(int $id): ?array
    {
        return $this->db->table('academic_years')->where('id', $id)->get()->getRowArray();
    }

    private function yearWithPeriodCount(int $id): ?array
    {
        return $this->db->table('academic_years ay')
            ->select('ay.id, ay.code, ay.start_year, ay.end_year, ay.is_active, ay.created_at, ay.updated_at, COUNT(ap.id) AS period_count', false)
            ->join('academic_periods ap', 'ap.academic_year_id = ay.id', 'left')
            ->where('ay.id', $id)
            ->groupBy('ay.id, ay.code, ay.start_year, ay.end_year, ay.is_active, ay.created_at, ay.updated_at')
            ->get()
            ->getRowArray();
    }

    private function deactivateOtherYears(int $activeId, string $now): void
    {
        $years = $this->db->table('academic_years')->where('is_active', 1)->where('id !=', $activeId)->get()->getResultArray();
        foreach ($years as $year) {
            $this->db->table('academic_years')->where('id', $year['id'])->update(['is_active' => 0, 'updated_at' => $now]);
            $this->writeAudit('ACADEMIC_YEAR_DEACTIVATED', (int) $year['id'], $year, [
                ...$year,
                'is_active'  => 0,
                'updated_at' => $now,
            ]);
        }
    }

    private function deactivatePeriodsOutsideYear(int $activeYearId, string $now): void
    {
        $periods = $this->db->table('academic_periods')
            ->where('is_active', 1)
            ->where('academic_year_id !=', $activeYearId)
            ->get()
            ->getResultArray();

        foreach ($periods as $period) {
            $this->db->table('academic_periods')->where('id', $period['id'])->update(['is_active' => 0, 'updated_at' => $now]);
            $this->writeAuditEntry('ACADEMIC_PERIOD_DEACTIVATED', 'academic_periods', (int) $period['id'], $period, [
                ...$period,
                'is_active'  => 0,
                'updated_at' => $now,
            ]);
        }
    }

    private function assertTransaction(string $message): void
    {
        if (! $this->db->transStatus()) {
            throw new RuntimeException($message);
        }
    }

    private function writeAudit(string $action, int $entityId, ?array $oldValues, ?array $newValues): void
    {
        $this->writeAuditEntry($action, 'academic_years', $entityId, $oldValues, $newValues);
    }

    private function writeAuditEntry(string $action, string $entityType, int $entityId, ?array $oldValues, ?array $newValues): void
    {
        $auth = session('auth');
        $this->db->table('audit_logs')->insert([
            'user_id'     => is_array($auth) ? ($auth['id'] ?? null) : null,
            'action'      => $action,
            'entity_type' => $entityType,
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
            : 'Terjadi kesalahan saat memproses data tahun akademik.';

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
