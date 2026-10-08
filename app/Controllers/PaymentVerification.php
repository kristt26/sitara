<?php

namespace App\Controllers;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class PaymentVerification extends BaseController
{
    private const PAYMENT_STATUSES = ['MENUNGGU', 'DITERIMA', 'DITOLAK'];
    private BaseConnection $db;

    public function initController($request, $response, $logger): void { parent::initController($request,$response,$logger); $this->db=db_connect(); }

    public function index(): string
    {
        return view('pages/finance/verification',['live'=>true,'activeMenu'=>'verification','pageTitle'=>'Verifikasi Pembayaran','pageSubtitle'=>'Pencatatan dan pemeriksaan pembayaran mahasiswa','csrfHeader'=>config('Security')->headerName,'csrfHash'=>csrf_hash()]);
    }

    public function read(): ResponseInterface
    {
        try {
            $period=$this->activePeriod(); $payments=[]; $bills=[];
            if ($period) {
                $payments=$this->db->table('student_payments sp')->select('sp.*,s.nim,s.full_name,pm.code AS method_code,pm.name AS method_name,GROUP_CONCAT(sb.bill_no) AS bill_numbers,SUM(spa.allocated_amount) AS allocated_amount')
                    ->join('students s','s.id=sp.student_id')->join('payment_methods pm','pm.id=sp.payment_method_id')->join('student_payment_allocations spa','spa.student_payment_id=sp.id')->join('student_bills sb','sb.id=spa.student_bill_id')
                    ->where('sb.academic_period_id',$period['id'])
                    ->groupBy('sp.id,sp.payment_no,sp.student_id,sp.payment_method_id,sp.payment_date,sp.amount,sp.reference_no,sp.proof_file_path,sp.status,sp.verified_at,sp.verified_by,sp.notes,sp.created_at,sp.updated_at,s.nim,s.full_name,pm.code,pm.name')
                    ->orderBy("CASE WHEN sp.status='MENUNGGU' THEN 0 ELSE 1 END",'',false)->orderBy('sp.created_at','DESC')->get()->getResultArray();
                $bills=$this->db->table('student_bills sb')->select('sb.id,sb.bill_no,sb.student_id,sb.total_amount,s.nim,s.full_name,at.name AS activity_name,COALESCE(SUM(CASE WHEN pay.status=\'DITERIMA\' THEN spa.allocated_amount ELSE 0 END),0) AS paid_amount')
                    ->join('students s','s.id=sb.student_id')->join('activity_types at','at.id=sb.activity_type_id')->join('student_payment_allocations spa','spa.student_bill_id=sb.id','left')->join('student_payments pay','pay.id=spa.student_payment_id','left')
                    ->where('sb.academic_period_id',$period['id'])->where('sb.status !=','LUNAS')
                    ->groupBy('sb.id,sb.bill_no,sb.student_id,sb.total_amount,s.nim,s.full_name,at.name')->orderBy('s.nim')->get()->getResultArray();
            }
            $methods=$this->db->table('payment_methods')->select('id,code,name')->where('is_active',1)->orderBy('name')->get()->getResultArray();
            return $this->success(['activePeriod'=>$period,'payments'=>$payments,'bills'=>$bills,'methods'=>$methods]);
        } catch(Throwable $e) { return $this->error($e); }
    }

    public function proof(int $id): ResponseInterface
    {
        try {
            $payment = $this->db->table('student_payments')->select('proof_file_path')->where('id', $id)->get()->getRowArray();
            $relative = is_array($payment) ? str_replace('\\', '/', (string) ($payment['proof_file_path'] ?? '')) : '';
            if ($relative === '' || str_contains($relative, '..') || ! str_starts_with($relative, 'payment-proofs/')) return $this->response->setStatusCode(404);
            $base = realpath(WRITEPATH . 'uploads/payment-proofs');
            $path = realpath(WRITEPATH . 'uploads/' . $relative);
            if ($base === false || $path === false || ! str_starts_with($path, $base . DIRECTORY_SEPARATOR) || ! is_file($path)) return $this->response->setStatusCode(404);
            $mime = mime_content_type($path) ?: 'application/octet-stream';
            return $this->response->setHeader('Content-Type', $mime)->setHeader('Content-Disposition', 'inline; filename="bukti-pembayaran-' . $id . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"')->setBody((string) file_get_contents($path));
        } catch (Throwable) {
            return $this->response->setStatusCode(404);
        }
    }

    public function create(): ResponseInterface
    {
        try {
            $i=$this->payload(); $billId=(int)($i['student_bill_id']??0); $methodId=(int)($i['payment_method_id']??0); $amount=(float)($i['amount']??0);
            $bill=$this->db->table('student_bills')->where('id',$billId)->get()->getRowArray();
            if(!$bill||$bill['status']==='LUNAS') throw new RuntimeException('Tagihan tidak ditemukan atau sudah lunas.');
            if($this->db->table('payment_methods')->where(['id'=>$methodId,'is_active'=>1])->countAllResults()===0) throw new RuntimeException('Metode pembayaran tidak tersedia atau tidak aktif.');
            $accepted=$this->acceptedAmount($billId); $outstanding=(float)$bill['total_amount']-$accepted;
            if($amount<=0||$amount>$outstanding) throw new RuntimeException('Nominal pembayaran harus lebih dari nol dan tidak melebihi sisa tagihan.');
            $paymentDate=trim((string)($i['payment_date']??'')); if($paymentDate==='')$paymentDate=date('Y-m-d H:i:s'); else {$paymentDate=str_replace('T',' ',$paymentDate);if(strlen($paymentDate)===16)$paymentDate.=':00';}
            $now=date('Y-m-d H:i:s');$reference=trim((string)($i['reference_no']??''))?:null;$notes=trim((string)($i['notes']??''))?:null;
            $this->db->transStart();
            $this->db->table('student_payments')->insert(['payment_no'=>'PAY-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2))),'student_id'=>$bill['student_id'],'payment_method_id'=>$methodId,'payment_date'=>$paymentDate,'amount'=>$amount,'reference_no'=>$reference,'proof_file_path'=>null,'status'=>'MENUNGGU','notes'=>$notes,'created_at'=>$now,'updated_at'=>$now]);
            $id=(int)$this->db->insertID();$this->db->table('student_payment_allocations')->insert(['student_payment_id'=>$id,'student_bill_id'=>$billId,'allocated_amount'=>$amount,'created_at'=>$now]);
            $this->db->table('student_bills')->where('id',$billId)->update(['status'=>'MENUNGGU','updated_at'=>$now]);$this->audit('STUDENT_PAYMENT_RECORDED',$id,null,$this->paymentById($id));$this->db->transComplete();
            if(!$this->db->transStatus())throw new RuntimeException('Pembayaran belum dapat disimpan.');
            return $this->success($this->paymentById($id),'Pembayaran dicatat dan menunggu verifikasi.',201);
        } catch(Throwable $e) { return $this->error($e); }
    }

    public function verify(int $id): ResponseInterface { return $this->decide($id,true); }
    public function reject(int $id): ResponseInterface { return $this->decide($id,false); }

    private function decide(int $id,bool $accepted): ResponseInterface
    {
        try {
            $old=$this->paymentById($id);if(!$old)return $this->message('Pembayaran tidak ditemukan.',404);if($old['status']!=='MENUNGGU')throw new RuntimeException('Pembayaran tersebut sudah diverifikasi.');
            $i=$accepted?[]:$this->payload();$notes=trim((string)($i['notes']??''))?:($old['notes']??null);if(!$accepted&&(!$notes||trim($notes)===''))throw new RuntimeException('Alasan penolakan wajib diisi.');
            $allocations=$this->db->table('student_payment_allocations')->where('student_payment_id',$id)->get()->getResultArray();if(!$allocations)throw new RuntimeException('Alokasi tagihan pembayaran tidak ditemukan.');
            foreach($allocations as $allocation){$bill=$this->db->table('student_bills')->where('id',$allocation['student_bill_id'])->get()->getRowArray();$already=$this->acceptedAmount((int)$allocation['student_bill_id']);if($accepted&&$already+(float)$allocation['allocated_amount']>(float)$bill['total_amount'])throw new RuntimeException('Pembayaran melebihi sisa tagihan dan tidak dapat diterima.');}
            $auth=session('auth');$now=date('Y-m-d H:i:s');$this->db->transStart();$this->db->table('student_payments')->where('id',$id)->update(['status'=>$accepted?'DITERIMA':'DITOLAK','verified_at'=>$now,'verified_by'=>is_array($auth)?($auth['id']??null):null,'notes'=>$notes,'updated_at'=>$now]);
            foreach($allocations as $allocation)$this->refreshBill((int)$allocation['student_bill_id']);$new=$this->paymentById($id);$this->audit($accepted?'STUDENT_PAYMENT_ACCEPTED':'STUDENT_PAYMENT_REJECTED',$id,$old,$new);$this->db->transComplete();if(!$this->db->transStatus())throw new RuntimeException('Verifikasi pembayaran gagal disimpan.');
            return $this->success($new,$accepted?'Pembayaran diterima dan status tagihan diperbarui.':'Pembayaran ditolak.');
        } catch(Throwable $e) { return $this->error($e); }
    }

    private function refreshBill(int $billId):void{$bill=$this->db->table('student_bills')->where('id',$billId)->get()->getRowArray();if(!$bill)return;$paid=$this->acceptedAmount($billId);$pending=$this->db->table('student_payment_allocations spa')->join('student_payments sp','sp.id=spa.student_payment_id')->where('spa.student_bill_id',$billId)->where('sp.status','MENUNGGU')->countAllResults();$status=$paid>=(float)$bill['total_amount']?'LUNAS':($pending>0?'MENUNGGU':($paid>0?'SEBAGIAN':'BELUM_DIBAYAR'));$this->db->table('student_bills')->where('id',$billId)->update(['status'=>$status,'updated_at'=>date('Y-m-d H:i:s')]);}
    private function acceptedAmount(int $billId):float{$row=$this->db->table('student_payment_allocations spa')->selectSum('spa.allocated_amount','paid')->join('student_payments sp','sp.id=spa.student_payment_id')->where('spa.student_bill_id',$billId)->where('sp.status','DITERIMA')->get()->getRowArray();return(float)($row['paid']??0);}
    private function paymentById(int $id):?array{return$this->db->table('student_payments')->where('id',$id)->get()->getRowArray();}
    private function activePeriod():?array{return$this->db->table('academic_periods ap')->select('ap.id,ap.semester_code,ay.code AS academic_year_code')->join('academic_years ay','ay.id=ap.academic_year_id')->where(['ap.is_active'=>1,'ay.is_active'=>1])->get()->getRowArray();}
    private function payload():array{$json=$this->request->getJSON(true);return is_array($json)?$json:$this->request->getPost();}
    private function audit(string $action,int $id,?array $old,?array $new):void{$auth=session('auth');$this->db->table('audit_logs')->insert(['user_id'=>is_array($auth)?($auth['id']??null):null,'action'=>$action,'entity_type'=>'student_payments','entity_id'=>$id,'old_values'=>$old?json_encode($old,JSON_UNESCAPED_UNICODE):null,'new_values'=>$new?json_encode($new,JSON_UNESCAPED_UNICODE):null,'ip_address'=>$this->request->getIPAddress(),'created_at'=>date('Y-m-d H:i:s')]);}
    private function success(mixed $data=null,?string $message=null,int $status=200):ResponseInterface{return$this->response->setStatusCode($status)->setJSON(['ok'=>true,'message'=>$message,'data'=>$data,'csrf'=>$this->csrf()]);}private function message(string $m,int $s):ResponseInterface{return$this->response->setStatusCode($s)->setJSON(['ok'=>false,'message'=>$m,'csrf'=>$this->csrf()]);}private function error(Throwable $e):ResponseInterface{return$this->message($e->getMessage(),422);}private function csrf():array{return['header'=>config('Security')->headerName,'hash'=>csrf_hash()];}
}
