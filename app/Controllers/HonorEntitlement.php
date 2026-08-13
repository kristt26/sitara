<?php

namespace App\Controllers;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class HonorEntitlement extends BaseController
{
    private BaseConnection $db;

    public function initController($request, $response, $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->db = db_connect();
    }

    public function index(): string
    {
        return view('pages/honor/entitlements', [
            'live' => true,
            'activeMenu' => 'honor-entitlements',
            'pageTitle' => 'Hak Honor',
            'pageSubtitle' => 'Pemeriksaan dan persetujuan hak honor dosen otomatis',
            'csrfHeader' => config('Security')->headerName,
            'csrfHash' => csrf_hash(),
        ]);
    }

    public function read(): ResponseInterface
    {
        try {
            $period = $this->period();
            $rows = [];

            if ($period) {
                $rows = $this->db->table('honor_entitlements he')
                    ->select('he.*,aa.activity_no,aa.title,l.nidn,l.nip,l.full_name,asg.role_type,asg.position_no,at.name activity_name,sp.code program_code,sb.bill_no,sb.status bill_status')
                    ->join('activity_assignments asg', 'asg.id=he.assignment_id')
                    ->join('academic_activities aa', 'aa.id=asg.activity_id')
                    ->join('lecturers l', 'l.id=asg.lecturer_id')
                    ->join('activity_types at', 'at.id=aa.activity_type_id')
                    ->join('study_programs sp', 'sp.id=aa.study_program_id')
                    ->join('student_bills sb', 'sb.activity_id=aa.id', 'left')
                    ->where('aa.academic_period_id', $period['id'])
                    ->orderBy('he.created_at', 'DESC')
                    ->get()
                    ->getResultArray();
            }

            return $this->ok([
                'activePeriod' => $period,
                'entitlements' => $rows,
            ]);
        } catch (Throwable $exception) {
            return $this->err($exception);
        }
    }

    public function approve(int $id): ResponseInterface
    {
        try {
            $old = $this->byId($id);
            if (!$old) {
                throw new RuntimeException('Hak honor tidak ditemukan.');
            }
            if ($old['status'] !== 'DIAJUKAN') {
                throw new RuntimeException('Hanya hak honor yang diajukan dapat disetujui.');
            }

            $bill = $this->sourceBill($id);
            if (!$bill || $bill['status'] !== 'LUNAS') {
                throw new RuntimeException('Hak honor hanya dapat disetujui setelah tagihan mahasiswa berstatus LUNAS.');
            }

            $this->db->table('honor_entitlements')->where('id', $id)->update([
                'status' => 'DISETUJUI',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $new = $this->byId($id);
            $this->audit('HONOR_ENTITLEMENT_APPROVED', $id, $old, $new);

            return $this->ok($new, 'Hak honor disetujui.');
        } catch (Throwable $exception) {
            return $this->err($exception);
        }
    }

    private function period(): ?array
    {
        return $this->db->table('academic_periods ap')
            ->select('ap.id,ap.semester_code,ay.code academic_year_code')
            ->join('academic_years ay', 'ay.id=ap.academic_year_id')
            ->where(['ap.is_active' => 1, 'ay.is_active' => 1])
            ->get()
            ->getRowArray();
    }

    private function byId(int $id): ?array
    {
        return $this->db->table('honor_entitlements')->where('id', $id)->get()->getRowArray();
    }

    private function sourceBill(int $entitlementId): ?array
    {
        return $this->db->table('honor_entitlements he')
            ->select('sb.id,sb.bill_no,sb.status')
            ->join('activity_assignments asg', 'asg.id=he.assignment_id')
            ->join('student_bills sb', 'sb.activity_id=asg.activity_id')
            ->where('he.id', $entitlementId)
            ->get()
            ->getRowArray();
    }

    private function audit(string $action, int $id, ?array $old, ?array $new): void
    {
        $user = session('auth');
        $this->db->table('audit_logs')->insert([
            'user_id' => is_array($user) ? ($user['id'] ?? null) : null,
            'action' => $action,
            'entity_type' => 'honor_entitlements',
            'entity_id' => $id,
            'old_values' => $old ? json_encode($old) : null,
            'new_values' => $new ? json_encode($new) : null,
            'ip_address' => $this->request->getIPAddress(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function ok(mixed $data = null, ?string $message = null, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'ok' => true,
            'message' => $message,
            'data' => $data,
            'csrf' => ['header' => config('Security')->headerName, 'hash' => csrf_hash()],
        ]);
    }

    private function err(Throwable $exception): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON([
            'ok' => false,
            'message' => $exception->getMessage(),
            'csrf' => ['header' => config('Security')->headerName, 'hash' => csrf_hash()],
        ]);
    }
}
