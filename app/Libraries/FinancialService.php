<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

class FinancialService
{
    private const ACTIVE_BILL_STATUSES = ['BELUM_DIBAYAR', 'MENUNGGU', 'SEBAGIAN'];

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function dashboardData(): array
    {
        $this->ensureSchema();

        return [
            'academicYears' => $this->academicYears(),
            'academicPeriods' => $this->academicPeriods(),
            'programs' => $this->programs(),
            'activities' => $this->activities(),
            'students' => $this->students(),
            'feeSettings' => $this->feeSettings(),
            'stats' => $this->stats(),
            'auditLogs' => $this->auditLogs(),
        ];
    }

    public function storeFeeSetting(array $payload): int
    {
        $this->ensureSchema();
        $periodId = (int) ($payload['academic_period_id'] ?? 0);
        $programId = (int) ($payload['study_program_id'] ?? 0);
        $activityTypeId = (int) ($payload['activity_type_id'] ?? 0);
        $examPathId = (int) ($payload['exam_path_id'] ?? 0);
        $items = $payload['items'] ?? [];

        if ($periodId < 1 || $programId < 1 || $activityTypeId < 1 || $examPathId < 1 || ! is_array($items)) {
            throw new RuntimeException('Periode, program studi, jenis kegiatan, jalur ujian, dan komponen tagihan wajib diisi.');
        }

        $period = $this->db->table('academic_periods ap')
            ->select('ap.id, ap.semester_code, ap.is_active, ay.code AS academic_year_code, ay.is_active AS academic_year_active')
            ->join('academic_years ay', 'ay.id = ap.academic_year_id')
            ->where('ap.id', $periodId)
            ->get()->getRowArray();
        if (! $period || ! (int) $period['is_active'] || ! (int) $period['academic_year_active']) {
            throw new RuntimeException('Tahun akademik atau semester tidak tersedia atau tidak aktif.');
        }

        if (! $this->exists('study_programs', $programId, true)) {
            throw new RuntimeException('Program studi tidak tersedia atau tidak aktif.');
        }
        if (! $this->exists('activity_types', $activityTypeId, true)) {
            throw new RuntimeException('Jenis kegiatan tidak tersedia atau tidak aktif.');
        }
        if (! $this->exists('exam_paths', $examPathId, true)) {
            throw new RuntimeException('Jalur ujian tidak tersedia atau tidak aktif.');
        }

        $cleanItems = [];
        foreach ($items as $item) {
            $code = trim((string) ($item['code'] ?? ''));
            $name = trim((string) ($item['name'] ?? ''));
            $amount = (float) ($item['amount'] ?? 0);
            if ($code === '' || $name === '' || $amount <= 0) {
                throw new RuntimeException('Setiap komponen tagihan harus memiliki kode, nama, dan nominal lebih besar dari nol.');
            }
            $cleanItems[] = [
                'item_code' => $code,
                'item_name' => $name,
                'amount' => $amount,
                'is_required' => (int) ($item['is_required'] ?? 1),
                'sort_order' => (int) ($item['sort_order'] ?? count($cleanItems) + 1),
            ];
        }

        if ($cleanItems === [] || array_sum(array_column($cleanItems, 'amount')) <= 0) {
            throw new RuntimeException('Nominal tarif harus lebih besar dari nol.');
        }

        $builder = $this->db->table('fee_settings');
        $latest = $builder->selectMax('version_no')->where([
            'academic_period_id' => $periodId,
            'study_program_id' => $programId,
            'activity_type_id' => $activityTypeId,
            'exam_path_id' => $examPathId,
        ])->get()->getRowArray();
        $version = ((int) ($latest['version_no'] ?? 0)) + 1;
        $now = date('Y-m-d H:i:s');

        $this->db->transStart();
        $previousSettings = $builder->where([
            'academic_period_id' => $periodId,
            'study_program_id' => $programId,
            'activity_type_id' => $activityTypeId,
            'exam_path_id' => $examPathId,
            'is_active' => 1,
        ])->get()->getResultArray();
        if ($previousSettings !== []) {
            $builder->where([
                'academic_period_id' => $periodId,
                'study_program_id' => $programId,
                'activity_type_id' => $activityTypeId,
                'exam_path_id' => $examPathId,
                'is_active' => 1,
            ])->update(['is_active' => 0, 'updated_at' => $now]);
            foreach ($previousSettings as $previousSetting) {
                $this->writeAudit('DEACTIVATE', 'fee_settings', (int) $previousSetting['id'], $previousSetting, ['is_active' => 0, 'reason' => 'new_version']);
            }
        }
        $builder->insert([
            'academic_period_id' => $periodId,
            'study_program_id' => $programId,
            'activity_type_id' => $activityTypeId,
            'exam_path_id' => $examPathId,
            'version_no' => $version,
            'effective_start_date' => $payload['effective_start_date'] ?? null,
            'effective_end_date' => $payload['effective_end_date'] ?? null,
            'is_active' => 1,
            'notes' => $payload['notes'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $settingId = (int) $this->db->insertID();

        foreach ($cleanItems as $item) {
            $this->db->table('fee_setting_items')->insert(array_merge($item, [
                'fee_setting_id' => $settingId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
        $this->writeAudit('CREATE', 'fee_settings', $settingId, null, ['version_no' => $version, 'items' => $cleanItems]);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('Pengaturan tarif gagal disimpan.');
        }

        return $settingId;
    }

    public function copyFeeSetting(int $sourceId, array $payload): int
    {
        $this->ensureSchema();
        $source = $this->db->table('fee_settings')->where('id', $sourceId)->get()->getRowArray();
        if (! $source) {
            throw new RuntimeException('Sumber pengaturan tarif tidak ditemukan.');
        }
        $items = $this->db->table('fee_setting_items')->where('fee_setting_id', $sourceId)->orderBy('sort_order')->get()->getResultArray();
        return $this->storeFeeSetting(array_merge(['exam_path_id'=>$source['exam_path_id']], $payload, [
            'items' => array_map(static fn (array $item): array => [
                'code' => $item['item_code'],
                'name' => $item['item_name'],
                'amount' => $item['amount'],
                'is_required' => $item['is_required'],
                'sort_order' => $item['sort_order'],
            ], $items),
        ]));
    }

    public function deactivateFeeSetting(int $settingId): void
    {
        $this->ensureSchema();
        $setting = $this->db->table('fee_settings')->where('id', $settingId)->get()->getRowArray();
        if (! $setting) {
            throw new RuntimeException('Pengaturan tarif tidak ditemukan.');
        }

        $used = $this->db->table('student_bills')->where('fee_setting_id', $settingId)->countAllResults();
        if ($used > 0) {
            $this->db->table('fee_settings')->where('id', $settingId)->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->writeAudit('DEACTIVATE', 'fee_settings', $settingId, $setting, ['is_active' => 0, 'reason' => 'used_in_transactions']);
            return;
        }

        $this->db->table('fee_settings')->where('id', $settingId)->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->writeAudit('DEACTIVATE', 'fee_settings', $settingId, $setting, ['is_active' => 0]);
    }

    public function createBill(int $activityId = 0, ?string $dueDate = null, array $context = []): array
    {
        $this->ensureSchema();
        if ($activityId < 1) {
            $activityId = $this->findActivityId($context);
        }
        $activity = $this->db->table('academic_activities aa')
            ->select('aa.*, s.status AS student_status, s.full_name, ap.semester_code, ay.code AS academic_year_code, ay.is_active AS academic_year_active, ap.is_active AS period_active')
            ->join('students s', 's.id = aa.student_id')
            ->join('academic_periods ap', 'ap.id = aa.academic_period_id')
            ->join('academic_years ay', 'ay.id = ap.academic_year_id')
            ->where('aa.id', $activityId)
            ->get()->getRowArray();

        if (! $activity || ($activity['status'] ?? '') === 'CANCELLED') {
            throw new RuntimeException('Kegiatan akademik mahasiswa tidak ditemukan.');
        }
        if (($activity['student_status'] ?? '') !== 'AKTIF') {
            throw new RuntimeException('Mahasiswa tidak aktif.');
        }
        if (! (int) $activity['academic_year_active'] || ! (int) $activity['period_active'] || ! $activity['semester_code']) {
            throw new RuntimeException('Tahun akademik atau semester kegiatan belum tersedia.');
        }

        $existing = $this->db->table('student_bills')->where('activity_id', $activityId)->countAllResults();
        if ($existing > 0) {
            throw new RuntimeException('Mahasiswa sudah memiliki tagihan aktif untuk kegiatan yang sama.');
        }

        $setting = $this->findActiveFeeSetting(
            (int) $activity['academic_period_id'],
            (int) $activity['study_program_id'],
            (int) $activity['activity_type_id'],
            (int) $activity['exam_path_id']
        );
        if (! $setting) {
            throw new RuntimeException('Tarif untuk periode, program studi, jenis kegiatan, dan jalur ujian tersebut belum tersedia. Silakan lengkapi Tarif & Komponen terlebih dahulu.');
        }

        $availableItems = $this->db->table('fee_setting_items')->where('fee_setting_id', $setting['id'])->orderBy('sort_order', 'ASC')->get()->getResultArray();
        $selectedItemIds = $context['selected_item_ids'] ?? null;
        if ($selectedItemIds === null) {
            $items = $availableItems;
        } else {
            if (! is_array($selectedItemIds)) {
                throw new RuntimeException('Pilihan komponen tagihan tidak valid.');
            }
            $selectedItemIds = array_values(array_unique(array_map('intval', $selectedItemIds)));
            $availableById = [];
            foreach ($availableItems as $item) $availableById[(int) $item['id']] = $item;
            foreach ($selectedItemIds as $selectedId) {
                if (! isset($availableById[$selectedId])) throw new RuntimeException('Komponen yang dipilih bukan bagian dari tarif kegiatan.');
            }
            foreach ($availableItems as $item) {
                if ((int) $item['is_required'] === 1 && ! in_array((int) $item['id'], $selectedItemIds, true)) {
                    throw new RuntimeException('Komponen wajib tidak dapat dihilangkan dari tagihan.');
                }
            }
            $items = array_values(array_filter($availableItems, static fn(array $item): bool => in_array((int) $item['id'], $selectedItemIds, true)));
        }
        $total = array_sum(array_map(static fn (array $item): float => (float) $item['amount'], $items));
        if ($total <= 0) {
            throw new RuntimeException('Nominal tagihan harus lebih besar dari nol.');
        }

        $billNumber = 'INV-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $pendingEntitlements = $this->prepareHonorEntitlements($activity, $billNumber);
        $now = date('Y-m-d H:i:s');
        $this->db->transStart();
        $this->db->table('student_bills')->insert([
            'bill_no' => $billNumber,
            'student_id' => $activity['student_id'],
            'activity_id' => $activityId,
            'fee_setting_id' => $setting['id'],
            'academic_period_id' => $activity['academic_period_id'],
            'study_program_id' => $activity['study_program_id'],
            'activity_type_id' => $activity['activity_type_id'],
            'exam_path_id' => $activity['exam_path_id'],
            'bill_date' => date('Y-m-d'),
            'due_date' => $dueDate,
            'subtotal_amount' => $total,
            'total_amount' => $total,
            'status' => 'BELUM_DIBAYAR',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $billId = (int) $this->db->insertID();
        foreach ($items as $item) {
            $this->db->table('student_bill_items')->insert([
                'student_bill_id' => $billId,
                'fee_setting_item_id' => $item['id'],
                'item_code_snapshot' => $item['item_code'],
                'item_name_snapshot' => $item['item_name'],
                'amount_snapshot' => $item['amount'],
                'total_amount' => $item['amount'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        foreach ($pendingEntitlements as $entitlement) {
            $this->db->table('honor_entitlements')->insert([
                'assignment_id' => $entitlement['assignment_id'],
                'honor_rate_setting_id' => $entitlement['honor_rate_setting_id'],
                'gross_amount_snapshot' => $entitlement['gross_amount_snapshot'],
                'tax_rate_snapshot' => $entitlement['tax_rate_snapshot'],
                'tax_amount' => $entitlement['tax_amount'],
                'net_amount' => $entitlement['net_amount'],
                'status' => 'DIAJUKAN',
                'notes' => 'Dibentuk otomatis saat tagihan ' . $billNumber . ' diterbitkan.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $entitlementId = (int) $this->db->insertID();
            $this->writeAudit('AUTO_CREATE', 'honor_entitlements', $entitlementId, null, [
                'assignment_id' => $entitlement['assignment_id'],
                'bill_id' => $billId,
                'status' => 'DIAJUKAN',
            ]);
        }
        $this->writeAudit('CREATE', 'student_bills', $billId, null, ['bill_no' => $billNumber, 'fee_setting_id' => $setting['id'], 'total_amount' => $total]);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('Tagihan gagal dibuat.');
        }

        return ['id' => $billId, 'bill_no' => $billNumber, 'total_amount' => $total, 'student_name' => $activity['full_name'], 'honor_entitlement_count' => count($pendingEntitlements)];
    }

    public function exportFeeSettings(): array
    {
        $this->ensureSchema();
        return $this->feeSettings();
    }

    private function academicYears(): array
    {
        return $this->db->table('academic_years')->select('code AS id, code AS label, is_active AS active')->orderBy('start_year', 'DESC')->get()->getResultArray();
    }

    private function programs(): array
    {
        return $this->db->table('study_programs')->select('id, code, name')->where('is_active', 1)->orderBy('name')->get()->getResultArray();
    }

    private function academicPeriods(): array
    {
        return $this->db->table('academic_periods ap')
            ->select('ap.id, ay.code AS year, ap.semester_code AS semester')
            ->join('academic_years ay', 'ay.id = ap.academic_year_id')
            ->where('ap.is_active', 1)
            ->where('ay.is_active', 1)
            ->orderBy('ay.start_year', 'DESC')
            ->orderBy('ap.semester_code', 'ASC')
            ->get()->getResultArray();
    }

    private function activities(): array
    {
        return $this->db->table('activity_types')->select('id, code, name')->where('is_active', 1)->orderBy('name')->get()->getResultArray();
    }

    private function students(): array
    {
        return $this->db->table('students s')
            ->select('s.id, s.nim, s.full_name, s.study_program_id')
            ->where('s.status', 'AKTIF')
            ->orderBy('s.nim')
            ->limit(100)
            ->get()->getResultArray();
    }

    private function feeSettings(): array
    {
        return $this->db->table('fee_settings fs')
            ->select('fs.id, ay.code AS year, ap.semester_code AS semester, sp.name AS program, sp.id AS programId, at.name AS activity, at.id AS activityId, ep.name AS examPath, COALESCE(SUM(fsi.amount), 0) AS amount, DATE_FORMAT(fs.updated_at, "%d %b %Y") AS updated')
            ->join('academic_periods ap', 'ap.id = fs.academic_period_id')
            ->join('academic_years ay', 'ay.id = ap.academic_year_id')
            ->join('study_programs sp', 'sp.id = fs.study_program_id')
            ->join('activity_types at', 'at.id = fs.activity_type_id')
            ->join('exam_paths ep', 'ep.id = fs.exam_path_id')
            ->join('fee_setting_items fsi', 'fsi.fee_setting_id = fs.id', 'left')
            ->where('fs.is_active', 1)
            ->groupBy('fs.id,ay.code,ap.semester_code,sp.name,sp.id,at.name,at.id,ep.name,fs.updated_at')
            ->orderBy('ay.start_year', 'DESC')
            ->orderBy('sp.name', 'ASC')
            ->get()->getResultArray();
    }

    private function stats(): array
    {
        $activeSettings = (int) $this->db->table('fee_settings')->where('is_active', 1)->countAllResults();
        $runningBills = $this->db->table('student_bills')->whereIn('status', self::ACTIVE_BILL_STATUSES)->selectSum('total_amount')->selectCount('id', 'bill_count')->get()->getRowArray();
        $pendingPayments = $this->db->table('student_payments')->where('status', 'MENUNGGU')->selectSum('amount')->selectCount('id', 'payment_count')->get()->getRowArray();

        return [
            ['label' => 'Tarif aktif', 'value' => number_format($activeSettings), 'meta' => 'Data database', 'tone' => 'blue', 'icon' => 'ti-adjustments-horizontal'],
            ['label' => 'Tagihan berjalan', 'value' => number_format((int) ($runningBills['bill_count'] ?? 0)), 'meta' => 'Rp ' . number_format((float) ($runningBills['total_amount'] ?? 0), 0, ',', '.'), 'tone' => 'indigo', 'icon' => 'ti-receipt'],
            ['label' => 'Menunggu verifikasi', 'value' => number_format((int) ($pendingPayments['payment_count'] ?? 0)), 'meta' => 'Rp ' . number_format((float) ($pendingPayments['amount'] ?? 0), 0, ',', '.'), 'tone' => 'orange', 'icon' => 'ti-clock-hour-4'],
            ['label' => 'Audit tercatat', 'value' => number_format((int) $this->db->table('audit_logs')->countAllResults()), 'meta' => 'Riwayat terlindungi', 'tone' => 'green', 'icon' => 'ti-shield-check'],
        ];
    }

    private function auditLogs(): array
    {
        return $this->db->table('audit_logs')->select('action, entity_type, entity_id, created_at')->orderBy('created_at', 'DESC')->limit(3)->get()->getResultArray();
    }

    private function findActiveFeeSetting(int $periodId, int $programId, int $activityTypeId, int $examPathId): ?array
    {
        $setting = $this->db->table('fee_settings fs')
            ->select('fs.id, fs.is_active, COALESCE(SUM(fsi.amount), 0) AS amount')
            ->join('fee_setting_items fsi', 'fsi.fee_setting_id = fs.id', 'left')
            ->where('fs.academic_period_id', $periodId)
            ->where('fs.study_program_id', $programId)
            ->where('fs.activity_type_id', $activityTypeId)
            ->where('fs.exam_path_id', $examPathId)
            ->where('fs.is_active', 1)
            ->groupBy('fs.id')
            ->orderBy('fs.version_no', 'DESC')
            ->get()->getRowArray();

        return $setting ?: null;
    }

    private function prepareHonorEntitlements(array $activity, string $billNumber): array
    {
        $assignments = $this->db->table('activity_assignments asg')
            ->select('asg.id,asg.role_type,asg.position_no,asg.lecturer_id,l.full_name')
            ->join('lecturers l', 'l.id=asg.lecturer_id')
            ->where(['asg.activity_id'=>$activity['id'],'asg.status'=>'AKTIF','l.is_active'=>1])
            ->orderBy('asg.role_type')->orderBy('asg.position_no')->get()->getResultArray();
        $result = [];
        foreach ($assignments as $assignment) {
            if ($this->db->table('honor_entitlements')->where('assignment_id',$assignment['id'])->countAllResults() > 0) continue;
            $rate = $this->findHonorRate(
                (int) $activity['academic_period_id'],
                (int) $activity['study_program_id'],
                (int) $activity['activity_type_id'],
                (string) $assignment['role_type'],
                (int) $assignment['position_no']
            );
            if (! $rate) {
                throw new RuntimeException('Tarif honor untuk ' . $assignment['full_name'] . ' sebagai ' . strtolower((string) $assignment['role_type']) . ' posisi ' . $assignment['position_no'] . ' belum tersedia. Lengkapi Tarif Honor sebelum menerbitkan tagihan.');
            }
            $gross = (float) $rate['gross_amount'];
            $taxRate = (float) $rate['tax_rate_percent'];
            $tax = round($gross * $taxRate / 100, 2);
            $result[] = [
                'assignment_id'=>(int)$assignment['id'],
                'honor_rate_setting_id'=>(int)$rate['id'],
                'gross_amount_snapshot'=>$gross,
                'tax_rate_snapshot'=>$taxRate,
                'tax_amount'=>$tax,
                'net_amount'=>$gross-$tax,
                'source_bill_no'=>$billNumber,
            ];
        }
        return $result;
    }

    private function findHonorRate(int $periodId,int $programId,int $activityTypeId,string $roleType,int $positionNo): ?array
    {
        $base=['academic_period_id'=>$periodId,'activity_type_id'=>$activityTypeId,'role_type'=>$roleType,'position_no'=>$positionNo,'is_active'=>1];
        $rate=$this->db->table('honor_rate_settings')->where($base)->where('study_program_id',$programId)->orderBy('version_no','DESC')->get()->getRowArray();
        if ($rate) return $rate;
        $rate=$this->db->table('honor_rate_settings')->where($base)->where('study_program_id',null)->orderBy('version_no','DESC')->get()->getRowArray();
        return $rate ?: null;
    }

    private function findActivityId(array $context): int
    {
        $studentId = (int) ($context['student_id'] ?? 0);
        $activityTypeId = (int) ($context['activity_type_id'] ?? 0);
        $yearCode = trim((string) ($context['academic_year_code'] ?? ''));
        $semesterCode = strtoupper(trim((string) ($context['semester_code'] ?? '')));
        if ($studentId < 1 || $activityTypeId < 1 || $yearCode === '' || $semesterCode === '') {
            throw new RuntimeException('Mahasiswa, periode, dan jenis kegiatan wajib dipilih.');
        }

        $activity = $this->db->table('academic_activities aa')
            ->select('aa.id')
            ->join('academic_periods ap', 'ap.id = aa.academic_period_id')
            ->join('academic_years ay', 'ay.id = ap.academic_year_id')
            ->where('aa.student_id', $studentId)
            ->where('aa.activity_type_id', $activityTypeId)
            ->where('ay.code', $yearCode)
            ->where('ap.semester_code', $semesterCode)
            ->whereNotIn('aa.status', ['CANCELLED', 'DRAFT'])
            ->orderBy('aa.attempt_no', 'DESC')
            ->get()->getRowArray();
        if (! $activity) {
            throw new RuntimeException('Kegiatan akademik mahasiswa belum terdaftar pada periode tersebut.');
        }

        return (int) $activity['id'];
    }

    private function exists(string $table, int $id, bool $active): bool
    {
        $builder = $this->db->table($table)->where('id', $id);
        if ($active) {
            $builder->where('is_active', 1);
        }
        return $builder->countAllResults() > 0;
    }

    private function writeAudit(string $action, string $entityType, int $entityId, ?array $oldValues, ?array $newValues): void
    {
        $this->db->table('audit_logs')->insert([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE),
            'new_values' => $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function ensureSchema(): void
    {
        try {
            if (! $this->db->tableExists('fee_settings') || ! $this->db->tableExists('student_bills')) {
                throw new RuntimeException('Migrasi database belum dijalankan.');
            }
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('Database SITARA belum dapat diakses.', 0, $exception);
        }
    }
}
