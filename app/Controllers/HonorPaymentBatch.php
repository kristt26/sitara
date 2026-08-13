<?php

namespace App\Controllers;

use App\Libraries\HonorBatchExporter;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class HonorPaymentBatch extends BaseController
{
    private BaseConnection $db;
    public function initController($request,$response,$logger):void{parent::initController($request,$response,$logger);$this->db=db_connect();}
    public function index():string{return view('pages/honor/payments',['live'=>true,'activeMenu'=>'honor-payments','pageTitle'=>'Batch Pembayaran Honor','pageSubtitle'=>'Pengelompokan dan pencairan hak honor dosen','csrfHeader'=>config('Security')->headerName,'csrfHash'=>csrf_hash()]);}

    public function read():ResponseInterface
    {
        try{$period=$this->period();$batches=[];$entitlements=[];
            if($period){$batches=$this->db->table('honor_payment_batches hpb')->select('hpb.*,COUNT(DISTINCT hp.id) lecturer_count,COALESCE(SUM(hp.gross_total),0) gross_total,COALESCE(SUM(hp.tax_total),0) tax_total,COALESCE(SUM(hp.net_total),0) net_total')->join('honor_payments hp','hp.honor_payment_batch_id=hpb.id','left')->where('hpb.academic_period_id',$period['id'])->groupBy('hpb.id,hpb.batch_no,hpb.academic_period_id,hpb.payment_date,hpb.status,hpb.reference_no,hpb.proof_file_path,hpb.notes,hpb.created_at,hpb.updated_at')->orderBy('hpb.created_at','DESC')->get()->getResultArray();
                $entitlements=$this->db->table('honor_entitlements he')->select('he.id,he.gross_amount_snapshot,he.tax_amount,he.net_amount,l.id lecturer_id,l.full_name,l.nidn,l.nip,aa.activity_no,at.name activity_name,asg.role_type,asg.position_no')->join('activity_assignments asg','asg.id=he.assignment_id')->join('academic_activities aa','aa.id=asg.activity_id')->join('lecturers l','l.id=asg.lecturer_id')->join('activity_types at','at.id=aa.activity_type_id')->join('honor_payment_items hpi','hpi.honor_entitlement_id=he.id','left')->where(['aa.academic_period_id'=>$period['id'],'he.status'=>'DISETUJUI'])->where('hpi.id',null)->orderBy('l.full_name')->get()->getResultArray();}
            return $this->ok(['activePeriod'=>$period,'batches'=>$batches,'entitlements'=>$entitlements,'methods'=>$this->db->table('payment_methods')->select('id,code,name')->where('is_active',1)->orderBy('name')->get()->getResultArray()]);
        }catch(Throwable$e){return$this->err($e);}
    }

    public function create():ResponseInterface
    {
        try{$period=$this->period();if(!$period)throw new RuntimeException('Belum ada periode aktif.');$input=$this->payload();$ids=array_values(array_unique(array_filter(array_map('intval',$input['entitlement_ids']??[]))));if(!$ids)throw new RuntimeException('Pilih minimal satu hak honor.');
            $rows=$this->db->table('honor_entitlements he')->select('he.*,l.id lecturer_id,l.bank_name,l.bank_account_number,l.bank_account_name,aa.id activity_id,aa.academic_period_id,hpi.id payment_item_id')->join('activity_assignments asg','asg.id=he.assignment_id')->join('academic_activities aa','aa.id=asg.activity_id')->join('lecturers l','l.id=asg.lecturer_id')->join('honor_payment_items hpi','hpi.honor_entitlement_id=he.id','left')->whereIn('he.id',$ids)->where('he.status','DISETUJUI')->get()->getResultArray();
            if(count($rows)!==count($ids))throw new RuntimeException('Sebagian hak honor tidak tersedia atau statusnya bukan DISETUJUI.');foreach($rows as$row){if((int)$row['academic_period_id']!==(int)$period['id'])throw new RuntimeException('Hak honor harus berasal dari periode aktif.');if($row['payment_item_id']!==null)throw new RuntimeException('Sebagian hak honor sudah masuk batch pembayaran.');}
            $now=date('Y-m-d H:i:s');$this->db->transBegin();try{$this->db->table('honor_payment_batches')->insert(['batch_no'=>'HNR-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2))),'academic_period_id'=>$period['id'],'payment_date'=>null,'status'=>'DRAFT','notes'=>trim((string)($input['notes']??''))?:null,'created_at'=>$now,'updated_at'=>$now]);$batchId=(int)$this->db->insertID();$groups=[];foreach($rows as$row)$groups[$row['lecturer_id']][]=$row;
                foreach($groups as$lecturer=>$items){$first=$items[0];$this->db->table('honor_payments')->insert(['honor_payment_batch_id'=>$batchId,'lecturer_id'=>$lecturer,'payment_method_id'=>null,'bank_name_snapshot'=>$first['bank_name'],'bank_account_number_snapshot'=>$first['bank_account_number'],'bank_account_name_snapshot'=>$first['bank_account_name'],'gross_total'=>array_sum(array_column($items,'gross_amount_snapshot')),'tax_total'=>array_sum(array_column($items,'tax_amount')),'net_total'=>array_sum(array_column($items,'net_amount')),'status'=>'DRAFT','created_at'=>$now,'updated_at'=>$now]);$paymentId=(int)$this->db->insertID();foreach($items as$item)$this->db->table('honor_payment_items')->insert(['honor_payment_id'=>$paymentId,'honor_entitlement_id'=>$item['id'],'gross_amount'=>$item['gross_amount_snapshot'],'tax_amount'=>$item['tax_amount'],'net_amount'=>$item['net_amount'],'created_at'=>$now]);}
                $this->db->table('honor_entitlements')->whereIn('id',$ids)->update(['status'=>'DIPROSES','updated_at'=>$now]);
                $activityIds=array_values(array_unique(array_map('intval',array_column($rows,'activity_id'))));
                if($activityIds!==[]&&$this->columnExists('academic_activities','status')){$unfinished=$this->db->table('academic_activities')->whereIn('id',$activityIds)->where('status !=','SELESAI')->get()->getResultArray();foreach($unfinished as$activity){$update=['status'=>'SELESAI'];if($this->columnExists('academic_activities','completed_at'))$update['completed_at']=$activity['completed_at']?:$now;if($this->columnExists('academic_activities','updated_at'))$update['updated_at']=$now;$this->db->table('academic_activities')->where('id',$activity['id'])->update($update);$this->auditEntity('ACTIVITY_AUTO_COMPLETED_FROM_HONOR_BATCH','academic_activities',(int)$activity['id'],$activity,$this->db->table('academic_activities')->where('id',$activity['id'])->get()->getRowArray());}}
                $this->audit('HONOR_BATCH_CREATED',$batchId,null,['entitlement_ids'=>$ids,'completed_activity_ids'=>$activityIds]);if(!$this->db->transStatus())throw new RuntimeException('Batch pembayaran gagal dibuat.');$this->db->transCommit();
            }catch(Throwable$e){$this->db->transRollback();throw$e;}return$this->ok(['id'=>$batchId],'Batch pembayaran honor berhasil dibuat.',201);
        }catch(Throwable$e){return$this->err($e);}
    }

    public function detail(int$id):ResponseInterface
    {
        try{$batch=$this->batch($id);if(!$batch)return$this->notFound();$batch['payments']=$this->db->table('honor_payments hp')->select('hp.*,l.full_name,l.nidn,l.nip,pm.name payment_method_name')->join('lecturers l','l.id=hp.lecturer_id')->join('payment_methods pm','pm.id=hp.payment_method_id','left')->where('hp.honor_payment_batch_id',$id)->orderBy('l.full_name')->get()->getResultArray();$batch['items']=$this->detailRows($id);return$this->ok($batch);}catch(Throwable$e){return$this->err($e);}
    }

    public function export(int$id):ResponseInterface
    {
        try{$batch=$this->batchSummary($id);if(!$batch)return$this->notFound();$temporary=(new HonorBatchExporter())->export($batch,$this->detailRows($id));$contents=file_get_contents($temporary);@unlink($temporary);if($contents===false)throw new RuntimeException('Export batch honor belum dapat dibuat.');return$this->response->download('batch-honor-'.$batch['batch_no'].'.xlsx',$contents);}catch(Throwable$e){return$this->err($e);}
    }

    public function pay(int$id):ResponseInterface
    {
        try{$batch=$this->batch($id);if(!$batch||$batch['status']!=='DRAFT')throw new RuntimeException('Hanya batch draft yang dapat dibayarkan.');$input=$this->payload();$method=(int)($input['payment_method_id']??0);if($this->db->table('payment_methods')->where(['id'=>$method,'is_active'=>1])->countAllResults()===0)throw new RuntimeException('Metode pembayaran tidak valid.');$date=trim((string)($input['payment_date']??''))?:date('Y-m-d');if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||strtotime($date)===false)throw new RuntimeException('Tanggal pembayaran tidak valid.');$reference=trim((string)($input['reference_no']??''))?:null;if($reference!==null&&strlen($reference)>100)throw new RuntimeException('Nomor referensi maksimal 100 karakter.');$payments=$this->db->table('honor_payments')->select('id')->where('honor_payment_batch_id',$id)->get()->getResultArray();if(!$payments)throw new RuntimeException('Batch tidak memiliki rincian pembayaran.');$paymentIds=array_column($payments,'id');$items=$this->db->table('honor_payment_items')->select('honor_entitlement_id')->whereIn('honor_payment_id',$paymentIds)->get()->getResultArray();if(!$items)throw new RuntimeException('Batch tidak memiliki hak honor yang dapat dibayarkan.');$now=date('Y-m-d H:i:s');
            $this->db->transBegin();try{$this->db->table('honor_payment_batches')->where('id',$id)->update(['payment_date'=>$date,'reference_no'=>$reference,'status'=>'DIBAYAR','updated_at'=>$now]);$this->db->table('honor_payments')->whereIn('id',$paymentIds)->update(['payment_method_id'=>$method,'status'=>'DIBAYAR','paid_at'=>$now,'updated_at'=>$now]);$this->db->table('honor_entitlements')->whereIn('id',array_column($items,'honor_entitlement_id'))->update(['status'=>'DIBAYAR','updated_at'=>$now]);$this->audit('HONOR_BATCH_PAID',$id,$batch,$this->batch($id));if(!$this->db->transStatus())throw new RuntimeException('Pembayaran batch honor gagal disimpan.');$this->db->transCommit();}catch(Throwable$e){$this->db->transRollback();throw$e;}return$this->ok(null,'Batch honor berhasil ditandai dibayar.');
        }catch(Throwable$e){return$this->err($e);}
    }

    private function detailRows(int $id): array
    {
        $rows = $this->db->table('honor_payment_items hpi')
            ->select('l.id lecturer_id,l.full_name,l.nidn,l.nip,s.full_name student_name,hp.bank_name_snapshot,hp.bank_account_number_snapshot,hp.bank_account_name_snapshot,hp.status payment_status,aa.id activity_id,aa.activity_no,at.name activity_name,asg.role_type,asg.position_no,hpi.gross_amount,hpi.tax_amount,hpi.net_amount')
            ->join('honor_payments hp','hp.id=hpi.honor_payment_id')
            ->join('lecturers l','l.id=hp.lecturer_id')
            ->join('honor_entitlements he','he.id=hpi.honor_entitlement_id')
            ->join('activity_assignments asg','asg.id=he.assignment_id')
            ->join('academic_activities aa','aa.id=asg.activity_id')
            ->join('students s','s.id=aa.student_id')
            ->join('activity_types at','at.id=aa.activity_type_id')
            ->where('hp.honor_payment_batch_id',$id)
            ->orderBy('l.full_name')->orderBy('aa.activity_no')->orderBy('asg.role_type')->orderBy('asg.position_no')
            ->get()->getResultArray();
        $activityIds = array_values(array_unique(array_map('intval', array_column($rows, 'activity_id'))));
        $counts = [];
        if ($activityIds !== []) {
            $roleCounts = $this->db->table('activity_assignments')
                ->select('activity_id,role_type,COUNT(*) role_count')
                ->whereIn('activity_id',$activityIds)->where('status','AKTIF')
                ->groupBy('activity_id,role_type')->get()->getResultArray();
            foreach ($roleCounts as $count) $counts[$count['activity_id'].'|'.$count['role_type']] = (int)$count['role_count'];
        }
        foreach ($rows as &$row) $row['role_count'] = $counts[$row['activity_id'].'|'.$row['role_type']] ?? 1;
        unset($row);
        return $rows;
    }
    private function batchSummary(int$id):?array{return$this->db->table('honor_payment_batches hpb')->select('hpb.*,ap.semester_code,ay.code academic_year_code,COUNT(DISTINCT hp.id) lecturer_count,COALESCE(SUM(hp.net_total),0) net_total')->join('academic_periods ap','ap.id=hpb.academic_period_id')->join('academic_years ay','ay.id=ap.academic_year_id')->join('honor_payments hp','hp.honor_payment_batch_id=hpb.id','left')->where('hpb.id',$id)->groupBy('hpb.id,hpb.batch_no,hpb.academic_period_id,hpb.payment_date,hpb.status,hpb.reference_no,hpb.proof_file_path,hpb.notes,hpb.created_at,hpb.updated_at,ap.semester_code,ay.code')->get()->getRowArray();}
    private function batch(int$id):?array{return$this->db->table('honor_payment_batches')->where('id',$id)->get()->getRowArray();}
    private function period():?array{return$this->db->table('academic_periods ap')->select('ap.id,ap.semester_code,ay.code academic_year_code')->join('academic_years ay','ay.id=ap.academic_year_id')->where(['ap.is_active'=>1,'ay.is_active'=>1])->get()->getRowArray();}
    private function payload():array{$json=$this->request->getJSON(true);return is_array($json)?$json:$this->request->getPost();}
    private function audit($action,$id,$old,$new):void{$user=session('auth');$this->db->table('audit_logs')->insert(['user_id'=>is_array($user)?($user['id']??null):null,'action'=>$action,'entity_type'=>'honor_payment_batches','entity_id'=>$id,'old_values'=>$old?json_encode($old):null,'new_values'=>$new?json_encode($new):null,'ip_address'=>$this->request->getIPAddress(),'created_at'=>date('Y-m-d H:i:s')]);}
    private function auditEntity(string$action,string$entity,int$id,?array$old,?array$new):void{$user=session('auth');$this->db->table('audit_logs')->insert(['user_id'=>is_array($user)?($user['id']??null):null,'action'=>$action,'entity_type'=>$entity,'entity_id'=>$id,'old_values'=>$old?json_encode($old):null,'new_values'=>$new?json_encode($new):null,'ip_address'=>$this->request->getIPAddress(),'created_at'=>date('Y-m-d H:i:s')]);}
    private function columnExists(string$table,string$column):bool{foreach($this->db->getFieldData($table)as$field)if($field->name===$column)return true;return false;}
    private function ok($data=null,$message=null,$status=200):ResponseInterface{return$this->response->setStatusCode($status)->setJSON(['ok'=>true,'message'=>$message,'data'=>$data,'csrf'=>['header'=>config('Security')->headerName,'hash'=>csrf_hash()]]);}
    private function err(Throwable$e):ResponseInterface{return$this->response->setStatusCode(422)->setJSON(['ok'=>false,'message'=>$e->getMessage(),'csrf'=>['header'=>config('Security')->headerName,'hash'=>csrf_hash()]]);}
    private function notFound():ResponseInterface{return$this->response->setStatusCode(404)->setJSON(['ok'=>false,'message'=>'Batch tidak ditemukan.']);}
}
