<?php

namespace App\Controllers;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class AcademicPeriod extends BaseController
{
    private BaseConnection $db;

    public function initController($request, $response, $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->db = db_connect();
    }

    public function index(int $yearId): string
    {
        if ($this->academicYearById($yearId) === null) {
            throw PageNotFoundException::forPageNotFound('Tahun akademik tidak ditemukan.');
        }

        return view('pages/academic/year_periods', [
            'live'         => true,
            'activeMenu'   => 'periods',
            'pageTitle'    => 'Periode Akademik',
            'pageSubtitle' => 'Pengaturan semester untuk tahun akademik yang dipilih',
            'yearId'       => $yearId,
            'csrfHeader'   => config('Security')->headerName,
            'csrfHash'     => csrf_hash(),
        ]);
    }

    public function read(int $yearId): ResponseInterface
    {
        try {
            $year = $this->academicYearById($yearId);
            if ($year === null) {
                return $this->messageResponse('Tahun akademik tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }

            $periods = $this->db->table('academic_periods')
                ->select('id, academic_year_id, semester_code, start_date, end_date, is_active, created_at, updated_at')
                ->where('academic_year_id', $yearId)
                ->orderBy('semester_code', 'ASC')
                ->get()
                ->getResultArray();

            return $this->successResponse([
                'year'    => $year,
                'periods' => $periods,
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function create(int $yearId): ResponseInterface
    {
        try {
            $year = $this->academicYearById($yearId);
            if ($year === null) {
                return $this->messageResponse('Tahun akademik tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }

            $data = $this->payload($yearId);
            if (($error = $this->validatePayload($data)) !== null) {
                throw new RuntimeException($error);
            }
            if ($data['is_active'] === 1 && (int) $year['is_active'] !== 1) {
                throw new RuntimeException('Aktifkan tahun akademik terlebih dahulu sebelum mengaktifkan periodenya.');
            }
            if ($this->periodExists($yearId, $data['semester_code'])) {
                throw new RuntimeException('Semester tersebut sudah terdaftar pada tahun akademik yang dipilih.');
            }

            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            if ($data['is_active'] === 1) {
                $this->deactivateOtherPeriods(0, $now);
            }
            $this->db->table('academic_periods')->insert([
                ...$data,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $periodId = (int) $this->db->insertID();
            $this->writeAudit('ACADEMIC_PERIOD_CREATED', $periodId, null, $this->periodById($periodId));
            $this->db->transComplete();
            $this->assertTransaction('Periode akademik belum dapat disimpan.');

            return $this->successResponse(
                $this->periodById($periodId),
                'Periode akademik berhasil ditambahkan.',
                ResponseInterface::HTTP_CREATED,
            );
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function update(int $yearId, int $id): ResponseInterface
    {
        try {
            $year = $this->academicYearById($yearId);
            if ($year === null) {
                return $this->messageResponse('Tahun akademik tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            $existing = $this->periodByYear($yearId, $id);
            if ($existing === null) {
                return $this->messageResponse('Periode akademik tidak ditemukan pada tahun tersebut.', ResponseInterface::HTTP_NOT_FOUND);
            }

            $data = $this->payload($yearId);
            if (($error = $this->validatePayload($data)) !== null) {
                throw new RuntimeException($error);
            }
            if ($data['is_active'] === 1 && (int) $year['is_active'] !== 1) {
                throw new RuntimeException('Aktifkan tahun akademik terlebih dahulu sebelum mengaktifkan periodenya.');
            }
            if ($this->periodExists($yearId, $data['semester_code'], $id)) {
                throw new RuntimeException('Semester tersebut sudah terdaftar pada tahun akademik yang dipilih.');
            }
            if ($existing['semester_code'] !== $data['semester_code'] && ($usage = $this->firstUsage($id)) !== null) {
                throw new RuntimeException('Semester tidak dapat diubah karena periode sudah digunakan pada ' . $usage . '.');
            }

            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            if ($data['is_active'] === 1) {
                $this->deactivateOtherPeriods($id, $now);
            }
            $this->db->table('academic_periods')->where('id', $id)->where('academic_year_id', $yearId)->update([
                ...$data,
                'updated_at' => $now,
            ]);
            $updated = $this->periodById($id);
            $this->writeAudit('ACADEMIC_PERIOD_UPDATED', $id, $existing, $updated);
            $this->db->transComplete();
            $this->assertTransaction('Perubahan periode akademik belum dapat disimpan.');

            return $this->successResponse($updated, 'Periode akademik berhasil diperbarui.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function activate(int $yearId, int $id): ResponseInterface
    {
        try {
            $year = $this->academicYearById($yearId);
            if ($year === null) {
                return $this->messageResponse('Tahun akademik tidak ditemukan.', ResponseInterface::HTTP_NOT_FOUND);
            }
            if ((int) $year['is_active'] !== 1) {
                throw new RuntimeException('Aktifkan tahun akademik induknya terlebih dahulu.');
            }

            $period = $this->periodByYear($yearId, $id);
            if ($period === null) {
                return $this->messageResponse('Periode akademik tidak ditemukan pada tahun tersebut.', ResponseInterface::HTTP_NOT_FOUND);
            }
            if ((int) $period['is_active'] === 1) {
                return $this->successResponse($period, 'Periode akademik tersebut sudah aktif.');
            }

            $now = date('Y-m-d H:i:s');
            $this->db->transStart();
            $this->deactivateOtherPeriods($id, $now);
            $this->db->table('academic_periods')->where('id', $id)->where('academic_year_id', $yearId)->update([
                'is_active' => 1,
                'updated_at' => $now,
            ]);
            $this->writeAudit('ACADEMIC_PERIOD_ACTIVATED', $id, $period, $this->periodById($id));
            $this->db->transComplete();
            $this->assertTransaction('Status periode akademik belum dapat diubah.');

            return $this->successResponse($this->periodById($id), 'Periode akademik aktif berhasil diperbarui.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function delete(int $yearId, int $id): ResponseInterface
    {
        try {
            $period = $this->periodByYear($yearId, $id);
            if ($period === null) {
                return $this->messageResponse('Periode akademik tidak ditemukan pada tahun tersebut.', ResponseInterface::HTTP_NOT_FOUND);
            }
            if (($usage = $this->firstUsage($id)) !== null) {
                throw new RuntimeException('Periode akademik tidak dapat dihapus karena sudah digunakan pada ' . $usage . '.');
            }

            $this->db->transStart();
            $this->db->table('academic_periods')->where('id', $id)->where('academic_year_id', $yearId)->delete();
            $this->writeAudit('ACADEMIC_PERIOD_DELETED', $id, $period, null);
            $this->db->transComplete();
            $this->assertTransaction('Periode akademik belum dapat dihapus.');

            return $this->successResponse(null, 'Periode akademik berhasil dihapus.');
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    /** @return array{academic_year_id: int, semester_code: string, start_date: ?string, end_date: ?string, is_active: int} */
    private function payload(int $yearId): array
    {
        $json = $this->request->getJSON(true);
        $input = is_array($json) ? $json : $this->request->getPost();
        $startDate = trim((string) ($input['start_date'] ?? ''));
        $endDate = trim((string) ($input['end_date'] ?? ''));

        return [
            'academic_year_id' => $yearId,
            'semester_code'    => strtoupper(trim((string) ($input['semester_code'] ?? ''))),
            'start_date'       => $startDate !== '' ? $startDate : null,
            'end_date'         => $endDate !== '' ? $endDate : null,
            'is_active'        => filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        ];
    }

    private function validatePayload(array $data): ?string
    {
        if (! in_array($data['semester_code'], ['GANJIL', 'GENAP'], true)) {
            return 'Semester harus berupa GANJIL atau GENAP.';
        }
        if (($data['start_date'] !== null && ! $this->isValidDate($data['start_date'])) || ($data['end_date'] !== null && ! $this->isValidDate($data['end_date']))) {
            return 'Tanggal periode tidak valid.';
        }
        if ($data['start_date'] !== null && $data['end_date'] !== null && $data['end_date'] < $data['start_date']) {
            return 'Tanggal akhir periode tidak boleh lebih awal dari tanggal mulai.';
        }

        return null;
    }

    private function isValidDate(string $date): bool
    {
        $parts = explode('-', $date);

        return count($parts) === 3
            && strlen($parts[0]) === 4
            && strlen($parts[1]) === 2
            && strlen($parts[2]) === 2
            && ctype_digit(implode('', $parts))
            && checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    private function periodExists(int $yearId, string $semesterCode, ?int $exceptId = null): bool
    {
        $builder = $this->db->table('academic_periods')->where([
            'academic_year_id' => $yearId,
            'semester_code'    => $semesterCode,
        ]);
        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    private function periodById(int $id): ?array
    {
        return $this->db->table('academic_periods')->where('id', $id)->get()->getRowArray();
    }

    private function periodByYear(int $yearId, int $id): ?array
    {
        return $this->db->table('academic_periods')
            ->where('id', $id)
            ->where('academic_year_id', $yearId)
            ->get()
            ->getRowArray();
    }

    private function academicYearById(int $id): ?array
    {
        return $this->db->table('academic_years')->where('id', $id)->get()->getRowArray();
    }

    private function deactivateOtherPeriods(int $activeId, string $now): void
    {
        $periods = $this->db->table('academic_periods')->where('is_active', 1)->where('id !=', $activeId)->get()->getResultArray();
        foreach ($periods as $period) {
            $this->db->table('academic_periods')->where('id', $period['id'])->update(['is_active' => 0, 'updated_at' => $now]);
            $this->writeAudit('ACADEMIC_PERIOD_DEACTIVATED', (int) $period['id'], $period, [
                ...$period,
                'is_active'  => 0,
                'updated_at' => $now,
            ]);
        }
    }

    private function firstUsage(int $periodId): ?string
    {
        $dependencies = [
            'academic_activities'   => 'kegiatan akademik mahasiswa',
            'activity_rules'        => 'aturan kegiatan',
            'fee_settings'          => 'pengaturan tarif',
            'honor_rate_settings'   => 'tarif honor',
            'student_bills'         => 'tagihan mahasiswa',
            'honor_payment_batches' => 'batch pembayaran honor',
        ];

        foreach ($dependencies as $table => $label) {
            if ($this->db->table($table)->where('academic_period_id', $periodId)->countAllResults() > 0) {
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
            'entity_type' => 'academic_periods',
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
            : 'Terjadi kesalahan saat memproses data periode akademik.';

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
