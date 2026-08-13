<?php

namespace App\Controllers;

use App\Libraries\FinancialService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class FeeSetting extends BaseController
{
    private BaseConnection $db;
    public function initController($request,$response,$logger): void { parent::initController($request,$response,$logger); $this->db=db_connect(); }
    public function index(): string { return view('pages/finance/fees',['live'=>true,'activeMenu'=>'fees','pageTitle'=>'Tarif & Komponen','pageSubtitle'=>'Tarif kegiatan dan komponen pembentuk tagihan','csrfHeader'=>config('Security')->headerName,'csrfHash'=>csrf_hash()]); }
    public function read(): ResponseInterface
    {
        try {
            $period=$this->activePeriod(); $settings=[];
            if($period){ $settings=$this->db->table('fee_settings fs')->select('fs.*,sp.code AS program_code,sp.name AS program_name,at.code AS activity_code,at.name AS activity_name,ep.code AS exam_path_code,ep.name AS exam_path_name,COALESCE(SUM(fsi.amount),0) AS total_amount,COUNT(fsi.id) AS item_count')->join('study_programs sp','sp.id=fs.study_program_id')->join('activity_types at','at.id=fs.activity_type_id')->join('exam_paths ep','ep.id=fs.exam_path_id')->join('fee_setting_items fsi','fsi.fee_setting_id=fs.id','left')->where(['fs.academic_period_id'=>$period['id'],'fs.is_active'=>1])->groupBy('fs.id,fs.academic_period_id,fs.study_program_id,fs.activity_type_id,fs.exam_path_id,fs.version_no,fs.effective_start_date,fs.effective_end_date,fs.is_active,fs.notes,fs.created_at,fs.updated_at,sp.code,sp.name,at.code,at.name,ep.code,ep.name,ep.sort_order')->orderBy('sp.code')->orderBy('at.code')->orderBy('ep.sort_order')->get()->getResultArray(); }
            return $this->success(['activePeriod'=>$period,'settings'=>$settings,'programs'=>$this->db->table('study_programs')->select('id,code,name,is_active')->orderBy('code')->get()->getResultArray(),'activityTypes'=>$this->db->table('activity_types')->select('id,code,name,is_active')->orderBy('code')->get()->getResultArray(),'examPaths'=>$this->db->table('exam_paths')->select('id,code,name,is_active')->where('is_active',1)->orderBy('sort_order')->get()->getResultArray()]);
        } catch(Throwable $e){ return $this->error($e); }
    }
    public function detail(int $id): ResponseInterface
    {
        try { $setting=$this->db->table('fee_settings')->where('id',$id)->get()->getRowArray(); if(!$setting)return $this->message('Tarif tidak ditemukan.',404); $setting['items']=$this->db->table('fee_setting_items')->where('fee_setting_id',$id)->orderBy('sort_order')->get()->getResultArray(); return $this->success($setting); } catch(Throwable $e){return $this->error($e);}
    }
    public function store(): ResponseInterface
    {
        try { $payload=$this->payload(); $period=$this->activePeriod(); if(!$period)throw new RuntimeException('Belum ada periode akademik aktif.'); $payload['academic_period_id']=(int)$period['id']; $id=(new FinancialService($this->db))->storeFeeSetting($payload); return $this->success(['id'=>$id],'Tarif dan komponen berhasil disimpan sebagai versi aktif.',201); } catch(Throwable $e){return $this->error($e);}
    }
    public function deactivate(int $id): ResponseInterface { try{(new FinancialService($this->db))->deactivateFeeSetting($id);return $this->success(null,'Tarif berhasil dinonaktifkan.');}catch(Throwable $e){return $this->error($e);} }
    private function activePeriod(): ?array { return $this->db->table('academic_periods ap')->select('ap.id,ap.semester_code,ay.code AS academic_year_code')->join('academic_years ay','ay.id=ap.academic_year_id')->where(['ap.is_active'=>1,'ay.is_active'=>1])->get()->getRowArray(); }
    private function payload(): array { $json=$this->request->getJSON(true);return is_array($json)?$json:$this->request->getPost(); }
    private function success(mixed $data=null,?string $message=null,int $status=200):ResponseInterface{return $this->response->setStatusCode($status)->setJSON(['ok'=>true,'message'=>$message,'data'=>$data,'csrf'=>$this->csrf()]);}
    private function message(string $message,int $status):ResponseInterface{return $this->response->setStatusCode($status)->setJSON(['ok'=>false,'message'=>$message,'csrf'=>$this->csrf()]);}
    private function error(Throwable $e):ResponseInterface{return $this->message($e->getMessage(),422);}
    private function csrf():array{return ['header'=>config('Security')->headerName,'hash'=>csrf_hash()];}
}
