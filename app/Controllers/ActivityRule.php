<?php

namespace App\Controllers;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class ActivityRule extends BaseController
{
    private BaseConnection $db;

    public function initController($request, $response, $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->db = db_connect();
    }

    public function index(): string
    {
        return view('pages/academic/activity_rules', [
            'live' => true, 'activeMenu' => 'activity-rules', 'pageTitle' => 'Aturan Kegiatan',
            'pageSubtitle' => 'Batas pembimbing dan penguji setiap kegiatan akademik',
            'csrfHeader' => config('Security')->headerName, 'csrfHash' => csrf_hash(),
        ]);
    }

    public function read(): ResponseInterface
    {
        try {
            $activePeriod = $this->activePeriod();
            $rules = [];
            if ($activePeriod !== null) {
                $rules = $this->db->table('activity_rules ar')
                ->select('ar.*, ap.semester_code, ay.code AS academic_year_code, ay.is_active AS academic_year_active, ap.is_active AS academic_period_active, sp.code AS program_code, sp.name AS program_name, at.code AS activity_code, at.name AS activity_name, at.examiner_supported')
                ->join('academic_periods ap', 'ap.id = ar.academic_period_id')->join('academic_years ay', 'ay.id = ap.academic_year_id')->join('study_programs sp', 'sp.id = ar.study_program_id')->join('activity_types at', 'at.id = ar.activity_type_id')->where('ar.academic_period_id', $activePeriod['id']);
                $this->applyProgramScope($rules, 'ar.study_program_id');
                $rules = $rules
                ->orderBy('sp.code')->orderBy('at.code')
                ->get()->getResultArray();
            }

            return $this->successResponse([
                'rules' => $rules,
                'activePeriod' => $activePeriod,
                'periods' => $this->periodOptions(),
                'previousPeriods' => $activePeriod === null ? [] : $this->previousPeriodOptions($activePeriod),
                'programs' => $this->scopedPrograms(),
                'activityTypes' => $this->db->table('activity_types')->select('id, code, name, examiner_supported, is_active')->orderBy('code')->get()->getResultArray(),
            ]);
        } catch (Throwable $exception) { return $this->errorResponse($exception); }
    }

    public function copyPrevious(): ResponseInterface
    {
        try {
            $activePeriod = $this->activePeriod();
            if ($activePeriod === null) {
                throw new RuntimeException('Belum ada periode akademik aktif sebagai tujuan penyalinan.');
            }
            $json = $this->request->getJSON(true);
            $sourcePeriodId = (int) ((is_array($json) ? $json : $this->request->getPost())['source_period_id'] ?? 0);
            $allowedSources = array_column($this->previousPeriodOptions($activePeriod), null, 'id');
            if (! isset($allowedSources[$sourcePeriodId])) {
                throw new RuntimeException('Periode sumber tidak valid atau bukan periode sebelumnya.');
            }

            $sourceRules = $this->db->table('activity_rules ar')
                ->select('ar.study_program_id, ar.activity_type_id, ar.min_supervisors, ar.max_supervisors, ar.min_examiners, ar.max_examiners, ar.examiner_optional, ar.is_active, ar.notes')
                ->join('study_programs sp', 'sp.id = ar.study_program_id AND sp.is_active = 1')
                ->join('activity_types at', 'at.id = ar.activity_type_id AND at.is_active = 1')
                ->where('ar.academic_period_id', $sourcePeriodId);
            $this->applyProgramScope($sourceRules, 'ar.study_program_id');
            $sourceRules = $sourceRules->get()->getResultArray();
            if ($sourceRules === []) {
                throw new RuntimeException('Periode sumber belum memiliki aturan yang dapat disalin.');
            }

            $created = 0; $skipped = 0; $now = date('Y-m-d H:i:s');
            $this->db->transBegin();
            try {
                foreach ($sourceRules as $source) {
                    $exists = $this->db->table('activity_rules')->where([
                        'academic_period_id' => $activePeriod['id'],
                        'study_program_id' => $source['study_program_id'],
                        'activity_type_id' => $source['activity_type_id'],
                    ])->countAllResults() > 0;
                    if ($exists) { $skipped++; continue; }
                    $this->db->table('activity_rules')->insert([
                        ...$source, 'academic_period_id' => (int) $activePeriod['id'],
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $id = (int) $this->db->insertID();
                    $this->writeAudit('ACTIVITY_RULE_COPIED', $id, null, $this->byId($id));
                    $created++;
                }
                if (! $this->db->transStatus()) throw new RuntimeException('Aturan periode sebelumnya belum dapat disalin.');
                $this->db->transCommit();
            } catch (Throwable $exception) { $this->db->transRollback(); throw $exception; }

            return $this->successResponse(['created' => $created, 'skipped' => $skipped], sprintf('Penyalinan selesai: %d aturan ditambahkan dan %d aturan dilewati karena sudah tersedia.', $created, $skipped));
        } catch (Throwable $exception) { return $this->errorResponse($exception); }
    }

    public function create(): ResponseInterface
    {
        try {
            $data = $this->payload(); $this->validateOrFail($data); $this->assertUniqueCombination($data);
            $now = date('Y-m-d H:i:s'); $this->db->transStart();
            $this->db->table('activity_rules')->insert([...$data, 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $this->db->insertID(); $this->writeAudit('ACTIVITY_RULE_CREATED', $id, null, $this->byId($id));
            $this->db->transComplete(); $this->assertTransaction('Aturan kegiatan belum dapat disimpan.');
            return $this->successResponse($this->byId($id), 'Aturan kegiatan berhasil ditambahkan.', ResponseInterface::HTTP_CREATED);
        } catch (Throwable $exception) { return $this->errorResponse($exception); }
    }

    public function update(int $id): ResponseInterface
    {
        try {
            $existing = $this->byId($id); if ($existing === null) return $this->messageResponse('Aturan kegiatan tidak ditemukan.', 404);
            $data = $this->payload(); $this->validateOrFail($data); $this->assertUniqueCombination($data, $id);
            $combinationChanged = (int) $existing['academic_period_id'] !== $data['academic_period_id'] || (int) $existing['study_program_id'] !== $data['study_program_id'] || (int) $existing['activity_type_id'] !== $data['activity_type_id'];
            if ($combinationChanged && $this->hasAcademicActivity($existing)) throw new RuntimeException('Periode, program studi, dan jenis kegiatan tidak dapat diubah karena aturan sudah digunakan pada kegiatan mahasiswa.');
            $this->db->transStart(); $this->db->table('activity_rules')->where('id', $id)->update([...$data, 'updated_at' => date('Y-m-d H:i:s')]);
            $updated = $this->byId($id); $this->writeAudit('ACTIVITY_RULE_UPDATED', $id, $existing, $updated);
            $this->db->transComplete(); $this->assertTransaction('Perubahan aturan kegiatan belum dapat disimpan.');
            return $this->successResponse($updated, 'Aturan kegiatan berhasil diperbarui.');
        } catch (Throwable $exception) { return $this->errorResponse($exception); }
    }

    public function activate(int $id): ResponseInterface
    {
        try {
            $existing = $this->byId($id); if ($existing === null) return $this->messageResponse('Aturan kegiatan tidak ditemukan.', 404);
            $this->assertReferencesActive($existing);
            $this->db->transStart(); $this->db->table('activity_rules')->where('id', $id)->update(['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            $updated = $this->byId($id); $this->writeAudit('ACTIVITY_RULE_ACTIVATED', $id, $existing, $updated);
            $this->db->transComplete(); $this->assertTransaction('Status aturan kegiatan belum dapat diubah.');
            return $this->successResponse($updated, 'Aturan kegiatan berhasil diaktifkan.');
        } catch (Throwable $exception) { return $this->errorResponse($exception); }
    }

    public function delete(int $id): ResponseInterface
    {
        try {
            $existing = $this->byId($id); if ($existing === null) return $this->messageResponse('Aturan kegiatan tidak ditemukan.', 404);
            if ($this->hasAcademicActivity($existing)) throw new RuntimeException('Aturan kegiatan tidak dapat dihapus karena sudah digunakan pada kegiatan mahasiswa. Nonaktifkan aturan untuk menyimpannya sebagai arsip.');
            $this->db->transStart(); $this->db->table('activity_rules')->where('id', $id)->delete(); $this->writeAudit('ACTIVITY_RULE_DELETED', $id, $existing, null);
            $this->db->transComplete(); $this->assertTransaction('Aturan kegiatan belum dapat dihapus.');
            return $this->successResponse(null, 'Aturan kegiatan berhasil dihapus.');
        } catch (Throwable $exception) { return $this->errorResponse($exception); }
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true); $input = is_array($json) ? $json : $this->request->getPost();
        return [
            'academic_period_id' => (int) ($input['academic_period_id'] ?? 0), 'study_program_id' => (int) ($input['study_program_id'] ?? 0), 'activity_type_id' => (int) ($input['activity_type_id'] ?? 0),
            'min_supervisors' => (int) ($input['min_supervisors'] ?? 0), 'max_supervisors' => (int) ($input['max_supervisors'] ?? 0), 'min_examiners' => (int) ($input['min_examiners'] ?? 0), 'max_examiners' => (int) ($input['max_examiners'] ?? 0),
            'examiner_optional' => filter_var($input['examiner_optional'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, 'is_active' => filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'notes' => ($notes = trim((string) ($input['notes'] ?? ''))) === '' ? null : $notes,
        ];
    }

    private function validateOrFail(array $data): void
    {
        $this->assertReferencesActive($data);
        if ($data['min_supervisors'] < 1 || $data['max_supervisors'] > 10 || $data['max_supervisors'] < $data['min_supervisors']) throw new RuntimeException('Batas pembimbing harus 1–10 dan nilai maksimum tidak boleh lebih kecil dari minimum.');
        if ($data['min_examiners'] < 0 || $data['max_examiners'] > 10 || $data['max_examiners'] < $data['min_examiners']) throw new RuntimeException('Batas penguji harus 0–10 dan nilai maksimum tidak boleh lebih kecil dari minimum.');
        $activityType = $this->db->table('activity_types')->where('id', $data['activity_type_id'])->get()->getRowArray();
        if ((int) $activityType['examiner_supported'] !== 1 && ($data['min_examiners'] !== 0 || $data['max_examiners'] !== 0 || $data['examiner_optional'] !== 0)) throw new RuntimeException('Jenis kegiatan tersebut tidak mendukung dosen penguji. Atur jumlah penguji menjadi 0.');
        if ($data['notes'] !== null && strlen($data['notes']) > 2000) throw new RuntimeException('Catatan maksimal 2.000 karakter.');
    }

    private function assertReferencesActive(array $data): void
    {
        $period = $this->db->table('academic_periods ap')->select('ap.is_active, ay.is_active AS year_active')->join('academic_years ay', 'ay.id = ap.academic_year_id')->where('ap.id', (int) $data['academic_period_id'])->get()->getRowArray();
        if ($period === null || (int) $period['is_active'] !== 1 || (int) $period['year_active'] !== 1) throw new RuntimeException('Periode akademik tidak ditemukan atau sedang nonaktif.');
        $programQuery = $this->db->table('study_programs')->where(['id' => (int) $data['study_program_id'], 'is_active' => 1]); $this->applyProgramScope($programQuery, 'id');
        if ($programQuery->countAllResults() === 0) throw new RuntimeException('Program studi tidak ditemukan atau sedang nonaktif.');
        if ($this->db->table('activity_types')->where(['id' => (int) $data['activity_type_id'], 'is_active' => 1])->countAllResults() === 0) throw new RuntimeException('Jenis kegiatan tidak ditemukan atau sedang nonaktif.');
    }

    private function assertUniqueCombination(array $data, ?int $exceptId = null): void { $builder = $this->db->table('activity_rules')->where(['academic_period_id' => $data['academic_period_id'], 'study_program_id' => $data['study_program_id'], 'activity_type_id' => $data['activity_type_id']]); if ($exceptId !== null) $builder->where('id !=', $exceptId); if ($builder->countAllResults() > 0) throw new RuntimeException('Aturan untuk kombinasi periode, program studi, dan jenis kegiatan tersebut sudah tersedia.'); }
    private function periodOptions(): array { return $this->db->table('academic_periods ap')->select('ap.id, ap.semester_code, ap.start_date, ap.end_date, ap.is_active, ay.code AS academic_year_code, ay.is_active AS academic_year_active')->join('academic_years ay', 'ay.id = ap.academic_year_id')->orderBy('ay.start_year', 'DESC')->orderBy('ap.semester_code')->get()->getResultArray(); }
    private function activePeriod(): ?array { return $this->db->table('academic_periods ap')->select('ap.id, ap.academic_year_id, ap.semester_code, ap.start_date, ap.end_date, ay.code AS academic_year_code, ay.start_year')->join('academic_years ay', 'ay.id = ap.academic_year_id')->where(['ap.is_active' => 1, 'ay.is_active' => 1])->orderBy('ay.start_year', 'DESC')->get()->getRowArray(); }
    private function previousPeriodOptions(array $activePeriod): array { return $this->db->table('academic_periods ap')->select('ap.id, ap.semester_code, ap.start_date, ap.end_date, ay.code AS academic_year_code, ay.start_year, COUNT(ar.id) AS rule_count')->join('academic_years ay', 'ay.id = ap.academic_year_id')->join('activity_rules ar', 'ar.academic_period_id = ap.id', 'left')->groupBy('ap.id, ap.semester_code, ap.start_date, ap.end_date, ay.code, ay.start_year')->having('COUNT(ar.id) >', 0)->groupStart()->where('ay.start_year <', (int) $activePeriod['start_year'])->orGroupStart()->where('ay.start_year', (int) $activePeriod['start_year'])->where('ap.id <', (int) $activePeriod['id'])->groupEnd()->groupEnd()->orderBy('ay.start_year', 'DESC')->orderBy('ap.id', 'DESC')->get()->getResultArray(); }
    private function byId(int $id): ?array { $query = $this->db->table('activity_rules')->where('id', $id); $this->applyProgramScope($query, 'study_program_id'); return $query->get()->getRowArray(); }
    private function hasAcademicActivity(array $rule): bool { return $this->db->table('academic_activities')->where(['academic_period_id' => $rule['academic_period_id'], 'study_program_id' => $rule['study_program_id'], 'activity_type_id' => $rule['activity_type_id']])->countAllResults() > 0; }
    private function scopedPrograms(): array { $query = $this->db->table('study_programs')->select('id, code, name, degree_level, is_active'); $this->applyProgramScope($query, 'id'); return $query->orderBy('code')->get()->getResultArray(); }
    private function programIds(): array { $auth = session('auth'); if (! is_array($auth) || ($auth['role'] ?? null) !== 'PRODI') return array_column($this->db->table('study_programs')->select('id')->where('is_active', 1)->get()->getResultArray(), 'id'); if (! $this->db->tableExists('user_study_programs')) return [0]; $ids = array_column($this->db->table('user_study_programs')->select('study_program_id')->where('user_id', (int) ($auth['id'] ?? 0))->get()->getResultArray(), 'study_program_id'); return $ids === [] ? [0] : array_map('intval', $ids); }
    private function applyProgramScope($query, string $column): void { $auth = session('auth'); if (is_array($auth) && ($auth['role'] ?? null) === 'PRODI') $query->whereIn($column, $this->programIds()); }
    private function assertTransaction(string $message): void { if (! $this->db->transStatus()) throw new RuntimeException($message); }
    private function writeAudit(string $action, int $id, ?array $old, ?array $new): void { $auth = session('auth'); $this->db->table('audit_logs')->insert(['user_id' => is_array($auth) ? ($auth['id'] ?? null) : null, 'action' => $action, 'entity_type' => 'activity_rules', 'entity_id' => $id, 'old_values' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE), 'new_values' => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE), 'ip_address' => $this->request->getIPAddress(), 'created_at' => date('Y-m-d H:i:s')]); }
    private function successResponse(mixed $data = null, ?string $message = null, int $status = 200): ResponseInterface { return $this->response->setStatusCode($status)->setJSON(['ok' => true, 'message' => $message, 'data' => $data, 'csrf' => $this->csrfPayload()]); }
    private function messageResponse(string $message, int $status): ResponseInterface { return $this->response->setStatusCode($status)->setJSON(['ok' => false, 'message' => $message, 'csrf' => $this->csrfPayload()]); }
    private function errorResponse(Throwable $exception): ResponseInterface { return $this->messageResponse($exception instanceof RuntimeException ? $exception->getMessage() : 'Terjadi kesalahan saat memproses aturan kegiatan.', 422); }
    private function csrfPayload(): array { return ['header' => config('Security')->headerName, 'hash' => csrf_hash()]; }
}
