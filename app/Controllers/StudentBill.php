<?php

namespace App\Controllers;

use App\Libraries\FinancialService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class StudentBill extends BaseController
{
    private BaseConnection $db;

    public function initController($request, $response, $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->db = db_connect();
    }

    public function index(): string
    {
        return view('pages/finance/bills', [
            'live' => true,
            'activeMenu' => 'bills',
            'pageTitle' => 'Tagihan Mahasiswa',
            'pageSubtitle' => 'Pembuatan dan pemantauan tagihan periode aktif',
            'csrfHeader' => config('Security')->headerName,
            'csrfHash' => csrf_hash(),
        ]);
    }

    public function read(): ResponseInterface
    {
        try {
            $period = $this->activePeriod();
            $bills = [];
            $activities = [];
            if ($period) {
                $bills = $this->db->table('student_bills sb')
                    ->select('sb.*,s.nim,s.full_name,sp.code AS program_code,at.code AS activity_code,at.name AS activity_name,ep.code AS exam_path_code,ep.name AS exam_path_name,COALESCE(SUM(CASE WHEN pay.status = \'DITERIMA\' THEN spa.allocated_amount ELSE 0 END),0) AS paid_amount')
                    ->join('students s', 's.id=sb.student_id')
                    ->join('study_programs sp', 'sp.id=sb.study_program_id')
                    ->join('activity_types at', 'at.id=sb.activity_type_id')
                    ->join('exam_paths ep', 'ep.id=sb.exam_path_id')
                    ->join('student_payment_allocations spa', 'spa.student_bill_id=sb.id', 'left')
                    ->join('student_payments pay', 'pay.id=spa.student_payment_id', 'left')
                    ->where('sb.academic_period_id', $period['id'])
                    ->groupBy('sb.id,sb.bill_no,sb.student_id,sb.activity_id,sb.fee_setting_id,sb.academic_period_id,sb.study_program_id,sb.activity_type_id,sb.exam_path_id,sb.bill_date,sb.due_date,sb.subtotal_amount,sb.discount_amount,sb.penalty_amount,sb.total_amount,sb.status,sb.notes,sb.created_at,sb.updated_at,s.nim,s.full_name,sp.code,at.code,at.name,ep.code,ep.name')
                    ->orderBy('sb.created_at', 'DESC')->get()->getResultArray();

                $activities = $this->db->table('academic_activities aa')
                    ->select('aa.id,aa.activity_no,aa.title,aa.scheduled_at,aa.exam_path_id,s.nim,s.full_name,sp.code AS program_code,at.name AS activity_name,ep.name AS exam_path_name,fs.id AS fee_setting_id,COALESCE(SUM(fsi.amount),0) AS fee_amount')
                    ->join('students s', 's.id=aa.student_id')
                    ->join('study_programs sp', 'sp.id=aa.study_program_id')
                    ->join('activity_types at', 'at.id=aa.activity_type_id')
                    ->join('exam_paths ep', 'ep.id=aa.exam_path_id')
                    ->join('fee_settings fs', 'fs.academic_period_id=aa.academic_period_id AND fs.study_program_id=aa.study_program_id AND fs.activity_type_id=aa.activity_type_id AND fs.exam_path_id=aa.exam_path_id AND fs.is_active=1')
                    ->join('fee_setting_items fsi', 'fsi.fee_setting_id=fs.id')
                    ->join('student_bills existing_bill', 'existing_bill.activity_id=aa.id', 'left')
                    ->where('aa.academic_period_id', $period['id'])->where('aa.status !=', 'CANCELLED')->where('s.status', 'AKTIF')->where('existing_bill.id', null)
                    ->groupBy('aa.id,aa.activity_no,aa.title,aa.scheduled_at,aa.exam_path_id,s.nim,s.full_name,sp.code,at.name,ep.name,fs.id')
                    ->orderBy('s.nim')->get()->getResultArray();
            }
            return $this->success(['activePeriod' => $period, 'bills' => $bills, 'activities' => $activities]);
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    public function detail(int $id): ResponseInterface
    {
        try {
            $bill = $this->db->table('student_bills sb')->select('sb.*,s.nim,s.full_name,sp.code AS program_code,at.name AS activity_name,ep.name AS exam_path_name')
                ->join('students s', 's.id=sb.student_id')->join('study_programs sp', 'sp.id=sb.study_program_id')->join('activity_types at', 'at.id=sb.activity_type_id')->join('exam_paths ep','ep.id=sb.exam_path_id')->where('sb.id', $id)->get()->getRowArray();
            if (! $bill) return $this->message('Tagihan tidak ditemukan.', 404);
            $bill['items'] = $this->db->table('student_bill_items')->where('student_bill_id', $id)->orderBy('id')->get()->getResultArray();
            $bill['payments'] = $this->db->table('student_payment_allocations spa')->select('sp.payment_no,sp.payment_date,sp.status,sp.reference_no,spa.allocated_amount,pm.name AS method_name')
                ->join('student_payments sp', 'sp.id=spa.student_payment_id')->join('payment_methods pm', 'pm.id=sp.payment_method_id')->where('spa.student_bill_id', $id)->orderBy('sp.payment_date', 'DESC')->get()->getResultArray();
            return $this->success($bill);
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    public function preview(int $activityId): ResponseInterface
    {
        try {
            $activity = $this->db->table('academic_activities aa')
                ->select('aa.id,aa.activity_no,aa.scheduled_at,aa.academic_period_id,aa.study_program_id,aa.activity_type_id,aa.exam_path_id,s.nim,s.full_name,at.name AS activity_name,ep.name AS exam_path_name')
                ->join('students s','s.id=aa.student_id')->join('activity_types at','at.id=aa.activity_type_id')->join('exam_paths ep','ep.id=aa.exam_path_id')
                ->where('aa.id',$activityId)->get()->getRowArray();
            if (!$activity) return $this->message('Kegiatan mahasiswa tidak ditemukan.',404);
            if ($this->db->table('student_bills')->where('activity_id',$activityId)->countAllResults()>0) throw new RuntimeException('Kegiatan tersebut sudah memiliki tagihan.');
            $setting = $this->db->table('fee_settings')->where(['academic_period_id'=>$activity['academic_period_id'],'study_program_id'=>$activity['study_program_id'],'activity_type_id'=>$activity['activity_type_id'],'exam_path_id'=>$activity['exam_path_id'],'is_active'=>1])->orderBy('version_no','DESC')->get()->getRowArray();
            if (!$setting) throw new RuntimeException('Tarif aktif untuk kegiatan tersebut belum tersedia.');
            $items = $this->db->table('fee_setting_items')->select('id,item_code,item_name,amount,is_required,sort_order')->where('fee_setting_id',$setting['id'])->orderBy('sort_order')->get()->getResultArray();
            foreach ($items as &$item) { $item['id']=(int)$item['id']; $item['amount']=(float)$item['amount']; $item['is_required']=(bool)$item['is_required']; $item['selected']=true; } unset($item);
            return $this->success(['activity'=>$activity,'feeSettingId'=>(int)$setting['id'],'items'=>$items]);
        } catch (Throwable $e) { return $this->error($e); }
    }

    public function create(): ResponseInterface
    {
        try {
            $input = $this->payload();
            $activityId = (int) ($input['activity_id'] ?? 0);
            if ($activityId < 1) throw new RuntimeException('Kegiatan mahasiswa wajib dipilih.');
            $dueDate = trim((string) ($input['due_date'] ?? '')) ?: null;
            if ($dueDate === null) {
                $scheduledAt = $this->db->table('academic_activities')->select('scheduled_at')->where('id', $activityId)->get()->getRow('scheduled_at');
                if ($scheduledAt) $dueDate = date('Y-m-d', strtotime((string) $scheduledAt . ' -1 day'));
            }
            if ($dueDate !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) throw new RuntimeException('Format jatuh tempo tidak valid.');
            $selectedItemIds = array_key_exists('selected_item_ids',$input) && is_array($input['selected_item_ids']) ? array_map('intval',$input['selected_item_ids']) : null;
            $bill = (new FinancialService($this->db))->createBill($activityId, $dueDate, ['selected_item_ids'=>$selectedItemIds]);
            $honorCount=(int)($bill['honor_entitlement_count']??0);
            $message='Tagihan berhasil diterbitkan dan nominalnya telah dikunci.' . ($honorCount>0?' '.$honorCount.' hak honor dosen otomatis diajukan.':'');
            return $this->success($bill, $message, 201);
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    private function activePeriod(): ?array { return $this->db->table('academic_periods ap')->select('ap.id,ap.semester_code,ay.code AS academic_year_code')->join('academic_years ay','ay.id=ap.academic_year_id')->where(['ap.is_active'=>1,'ay.is_active'=>1])->get()->getRowArray(); }
    private function payload(): array { $json=$this->request->getJSON(true); return is_array($json)?$json:$this->request->getPost(); }
    private function success(mixed $data=null,?string $message=null,int $status=200):ResponseInterface{return $this->response->setStatusCode($status)->setJSON(['ok'=>true,'message'=>$message,'data'=>$data,'csrf'=>$this->csrf()]);}
    private function message(string $message,int $status):ResponseInterface{return $this->response->setStatusCode($status)->setJSON(['ok'=>false,'message'=>$message,'csrf'=>$this->csrf()]);}
    private function error(Throwable $e):ResponseInterface{return $this->message($e->getMessage(),422);}
    private function csrf():array{return ['header'=>config('Security')->headerName,'hash'=>csrf_hash()];}
}
