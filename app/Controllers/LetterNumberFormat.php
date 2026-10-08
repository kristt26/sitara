<?php

namespace App\Controllers;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class LetterNumberFormat extends BaseController
{
    private BaseConnection $db;
    public function initController($request, $response, $logger): void { parent::initController($request, $response, $logger); $this->db=db_connect(); }
    public function index(): string { return view('pages/academic/letter_number_formats',['live'=>true,'activeMenu'=>'letter-number-formats','pageTitle'=>'Format Nomor Surat','pageSubtitle'=>'Pengaturan nomor surat per jenis kegiatan','csrfHeader'=>config('Security')->headerName,'csrfHash'=>csrf_hash()]); }
    public function read(): ResponseInterface
    {
        try { $ids=$this->programIds(); $formats=$this->db->table('letter_number_formats lnf')->select('lnf.*,sp.code program_code,sp.name program_name,at.code activity_code,at.name activity_name')->join('study_programs sp','sp.id=lnf.study_program_id')->join('activity_types at','at.id=lnf.activity_type_id')->whereIn('lnf.study_program_id',$ids)->orderBy('sp.code')->orderBy('at.code')->get()->getResultArray(); $types=$this->db->table('activity_types')->select('id,code,name')->where('is_active',1)->orderBy('code')->get()->getResultArray(); return $this->success(['formats'=>$formats,'activityTypes'=>$types]); } catch(Throwable $e) { return $this->error($e); }
    }
    public function save(): ResponseInterface
    {
        try { $in=$this->request->getJSON(true)??[]; $activity=(int)($in['activity_type_id']??0); $template=trim((string)($in['format_template']??'')); $code=trim((string)($in['letter_code']??'BA')); $padding=max(1,min(6,(int)($in['padding']??3))); $scope=strtoupper(trim((string)($in['reset_scope']??'TAHUN'))); if($activity<1||$template==='')throw new RuntimeException('Jenis kegiatan dan format wajib diisi.'); if(!in_array($scope,['TAHUN','PERIODE'],true))throw new RuntimeException('Reset nomor tidak valid.'); $program=$this->programIds()[0]??0; if($program<1)throw new RuntimeException('Prodi pengguna belum terhubung.'); $now=date('Y-m-d H:i:s'); $existing=$this->db->table('letter_number_formats')->where(['study_program_id'=>$program,'activity_type_id'=>$activity])->get()->getRowArray(); $data=['study_program_id'=>$program,'activity_type_id'=>$activity,'format_template'=>$template,'letter_code'=>$code?:'BA','padding'=>$padding,'reset_scope'=>$scope,'is_active'=>1,'updated_at'=>$now]; if($existing){$this->db->table('letter_number_formats')->where('id',(int)$existing['id'])->update($data);}else{$data['next_number']=1;$data['created_at']=$now;$this->db->table('letter_number_formats')->insert($data);} return $this->success(null,'Format nomor surat berhasil disimpan.'); } catch(Throwable $e) { return $this->error($e); }
    }
    private function programIds():array { $auth=session('auth'); if(!is_array($auth)||($auth['role']??null)!=='PRODI')return array_map('intval',array_column($this->db->table('study_programs')->select('id')->where('is_active',1)->get()->getResultArray(),'id')); $ids=array_column($this->db->table('user_study_programs')->select('study_program_id')->where('user_id',(int)($auth['id']??0))->get()->getResultArray(),'study_program_id'); return $ids===[]?[0]:array_map('intval',$ids); }
    private function success(mixed $data=null,?string $message=null):ResponseInterface{return $this->response->setJSON(['ok'=>true,'message'=>$message,'data'=>$data,'csrf'=>$this->csrf()]);}
    private function error(Throwable $e):ResponseInterface{return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'message'=>$e->getMessage(),'csrf'=>$this->csrf()]);}
    private function csrf():array{return ['header'=>config('Security')->headerName,'hash'=>csrf_hash()];}
}
