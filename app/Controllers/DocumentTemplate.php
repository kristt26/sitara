<?php

namespace App\Controllers;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class DocumentTemplate extends BaseController
{
    private BaseConnection $db;
    private const TYPES = ['NILAI', 'BERITA_ACARA_TUNGGAL', 'BERITA_ACARA_TIM'];
    private const SOURCES = ['MANUAL', 'MAHASISWA', 'KEGIATAN', 'JADWAL', 'DOSEN', 'PEMBIMBING', 'PENGUJI', 'NILAI', 'KEUANGAN', 'TEKS_TETAP'];

    public function initController($request, $response, $logger): void { parent::initController($request, $response, $logger); $this->db = db_connect(); }
    public function index(): string { return view('pages/academic/document_templates', ['live'=>true,'activeMenu'=>'document-templates','pageTitle'=>'Template Dokumen','pageSubtitle'=>'Template dan isian dokumen per jenis kegiatan','csrfHeader'=>config('Security')->headerName,'csrfHash'=>csrf_hash()]); }

    public function read(): ResponseInterface
    {
        try {
            $q = $this->db->table('document_templates dt')->select('dt.*,sp.code program_code,sp.name program_name,at.code activity_code,at.name activity_name')->join('study_programs sp','sp.id=dt.study_program_id')->join('activity_types at','at.id=dt.activity_type_id')->whereIn('dt.study_program_id',$this->programIds())->where('dt.is_active',1)->orderBy('dt.updated_at','DESC');
            $templates = $q->get()->getResultArray();
            $fields = $templates === [] ? [] : $this->db->table('document_template_fields')->whereIn('template_id',array_column($templates,'id'))->orderBy('sort_order')->get()->getResultArray();
            $activityTypes=$this->db->table('activity_types')->select('id,code,name')->where('is_active',1)->orderBy('code')->get()->getResultArray();$sourceByActivity=[];
            foreach($activityTypes as $type){$rule=$this->db->table('activity_rules')->where(['activity_type_id'=>(int)$type['id'],'is_active'=>1])->whereIn('study_program_id',$this->programIds())->orderBy('max_supervisors','DESC')->get()->getRowArray();$sourceByActivity[(string)$type['id']]=$this->sourceAttributes((int)($rule['max_supervisors']??5),(int)($rule['max_examiners']??5));}
            return $this->success(['templates'=>$templates,'fields'=>$fields,'activityTypes'=>$activityTypes,'documentTypes'=>self::TYPES,'sources'=>self::SOURCES,'sourceAttributes'=>$this->sourceAttributes(),'sourceAttributesByActivity'=>$sourceByActivity]);
        } catch (Throwable $e) { return $this->error($e); }
    }

    public function upload(): ResponseInterface
    {
        try {
            $activityTypeId=(int)$this->request->getPost('activity_type_id'); $documentType=strtoupper(trim((string)$this->request->getPost('document_type')));
            if (!in_array($documentType,self::TYPES,true)) throw new RuntimeException('Jenis dokumen tidak valid.');
            if ($this->db->table('activity_types')->where(['id'=>$activityTypeId,'is_active'=>1])->countAllResults()===0) throw new RuntimeException('Jenis kegiatan tidak ditemukan.');
            $file=$this->request->getFile('file');
            if (!$file) throw new RuntimeException('File template belum diterima server.');
            if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new RuntimeException('Ukuran file melebihi batas upload server. Batas aktif: ' . (ini_get('upload_max_filesize') ?: 'tidak diketahui') . '.');
            if (!$file->isValid() || strtolower(pathinfo($file->getClientName(), PATHINFO_EXTENSION)) !== 'docx') throw new RuntimeException('Pilih file DOCX yang valid.');
            if($file->getSize()>8*1024*1024) throw new RuntimeException('Ukuran template maksimal 8 MB.');
            $programId=$this->programIds()[0]??0; if($programId<1) throw new RuntimeException('Prodi pengguna belum terhubung.');
            $dir=WRITEPATH.'uploads/document-templates'; if(!is_dir($dir)) mkdir($dir,0750,true);
            $name=bin2hex(random_bytes(16)).'.docx'; $file->move($dir,$name);
            $now=date('Y-m-d H:i:s');
            $scope=['study_program_id'=>$programId,'activity_type_id'=>$activityTypeId,'document_type'=>$documentType];
            $previous=$this->db->table('document_templates')->where($scope)->orderBy('version_no','DESC')->orderBy('id','DESC')->get()->getRowArray();
            $version=((int)($previous['version_no']??0))+1;
            $this->db->table('document_templates')->where($scope)->update(['is_active'=>0,'updated_at'=>$now]);
            $auth=session('auth'); $this->db->table('document_templates')->insert(array_merge($scope,['file_name'=>$file->getClientName(),'stored_path'=>'uploads/document-templates/'.$name,'version_no'=>$version,'is_active'=>1,'uploaded_by'=>is_array($auth)?($auth['id']??null):null,'created_at'=>$now,'updated_at'=>$now]));
            $newId=(int)$this->db->insertID();
            if($previous && $newId>0){
                $oldFields=$this->db->table('document_template_fields')->where('template_id',(int)$previous['id'])->get()->getResultArray();
                if($oldFields!==[]){
                    foreach($oldFields as &$field){unset($field['id']);$field['template_id']=$newId;$field['created_at']=$now;$field['updated_at']=$now;}
                    unset($field);$this->db->table('document_template_fields')->insertBatch($oldFields);
                }
            }
            return $this->success(null,'Template berhasil diunggah.');
        } catch(Throwable $e) { return $this->error($e); }
    }

    public function saveFields(int $id): ResponseInterface
    {
        try {
            $template=$this->template($id); if(!$template) return $this->message('Template tidak ditemukan.',404);
            $input=$this->request->getJSON(true); $fields=$input['fields']??[]; if(!is_array($fields)) throw new RuntimeException('Format field tidak valid.');
            $rule=$this->db->table('activity_rules')->where(['activity_type_id'=>(int)$template['activity_type_id'],'study_program_id'=>(int)$template['study_program_id'],'is_active'=>1])->get()->getRowArray();$available=$this->sourceAttributes((int)($rule['max_supervisors']??5),(int)($rule['max_examiners']??5));
            $clean=[]; foreach($fields as $i=>$field){$key=trim((string)($field['field_key']??''));$label=trim((string)($field['label']??''));$source=strtoupper(trim((string)($field['source_type']??'MANUAL')));$sourceKey=trim((string)($field['source_key']??''));if(!preg_match('/^[a-z][a-z0-9_]{1,99}$/',$key))throw new RuntimeException('Key field harus berupa huruf kecil, angka, dan underscore.');if($label==='')throw new RuntimeException('Label field wajib diisi.');if(!in_array($source,self::SOURCES,true))throw new RuntimeException('Sumber field tidak valid.');if(in_array($source,['MANUAL','TEKS_TETAP'],true))$sourceKey=null;elseif(!isset($available[$source][$sourceKey]))throw new RuntimeException('Atribut tidak sesuai aturan jenis kegiatan yang dipilih.');$clean[]=['template_id'=>$id,'field_key'=>$key,'label'=>$label,'source_type'=>$source,'source_key'=>$sourceKey,'input_type'=>strtoupper(trim((string)($field['input_type']??'TEXT'))),'is_required'=>!empty($field['is_required'])?1:0,'sort_order'=>$i+1,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')];}
            $this->db->transStart();$this->db->table('document_template_fields')->where('template_id',$id)->delete();if($clean!==[])$this->db->table('document_template_fields')->insertBatch($clean);$this->db->table('document_templates')->where('id',$id)->update(['updated_at'=>date('Y-m-d H:i:s')]);$this->db->transComplete();if(!$this->db->transStatus())throw new RuntimeException('Field template belum dapat disimpan.');return $this->success(null,'Pengaturan isian template berhasil disimpan.');
        } catch(Throwable $e){return $this->error($e);}
    }

    public function download(int $id): ResponseInterface
    { try{$template=$this->template($id);if(!$template)return $this->message('Template tidak ditemukan.',404);$path=WRITEPATH.$template['stored_path'];if(!is_file($path))return $this->message('File template tidak tersedia.',404);return $this->response->download($template['file_name'],file_get_contents($path));}catch(Throwable $e){return $this->error($e);} }
    private function template(int $id):?array{$q=$this->db->table('document_templates')->where('id',$id);$q->whereIn('study_program_id',$this->programIds());return$q->get()->getRowArray();}
    private function sourceAttributes(int $maxSupervisors=5,int $maxExaminers=5):array{$role=function(string $prefix,string $label,int $max):array{$out=[];for($i=1;$i<=max(0,min(10,$max));$i++){ $out[$prefix.'_'.$i.'_name']=$label.' '.$i.' — Nama';$out[$prefix.'_'.$i.'_nidn']=$label.' '.$i.' — NIDN';$out[$prefix.'_'.$i.'_nip']=$label.' '.$i.' — NIP';}return$out;};return['MAHASISWA'=>['nim'=>'NPM','full_name'=>'Nama lengkap','email'=>'Email','phone'=>'Nomor telepon','status'=>'Status'],'KEGIATAN'=>['activity_no'=>'Nomor kegiatan','title'=>'Judul','status'=>'Status kegiatan','attempt_no'=>'Percobaan','activity_name'=>'Jenis kegiatan','exam_path_name'=>'Jalur ujian'],'JADWAL'=>['scheduled_date'=>'Tanggal ujian','scheduled_time'=>'Waktu ujian','location'=>'Lokasi ujian'],'DOSEN'=>['full_name'=>'Nama dosen','nidn'=>'NIDN','nip'=>'NIP','role_type'=>'Peran','position_no'=>'Urutan'],'PEMBIMBING'=>$role('pembimbing','Pembimbing',$maxSupervisors),'PENGUJI'=>$role('penguji','Penguji',$maxExaminers),'NILAI'=>['nilai_isi_proposal'=>'Nilai isi proposal','nilai_penguasaan_materi'=>'Nilai penguasaan materi','nilai_etika'=>'Nilai etika','jumlah_nilai'=>'Jumlah nilai','nilai_huruf'=>'Nilai huruf'],'KEUANGAN'=>['bill_no'=>'Nomor tagihan','total_amount'=>'Total tagihan','status'=>'Status pembayaran'],'MANUAL'=>[],'TEKS_TETAP'=>[]];}
    private function programIds():array{$auth=session('auth');if(!is_array($auth)||($auth['role']??null)!=='PRODI')return array_map('intval',array_column($this->db->table('study_programs')->select('id')->where('is_active',1)->get()->getResultArray(),'id'));$ids=array_column($this->db->table('user_study_programs')->select('study_program_id')->where('user_id',(int)($auth['id']??0))->get()->getResultArray(),'study_program_id');return $ids===[]?[0]:array_map('intval',$ids);}
    private function success(mixed $data=null,?string $message=null):ResponseInterface{return$this->response->setJSON(['ok'=>true,'message'=>$message,'data'=>$data,'csrf'=>$this->csrf()]);}private function message(string $m,int $s):ResponseInterface{return$this->response->setStatusCode($s)->setJSON(['ok'=>false,'message'=>$m,'csrf'=>$this->csrf()]);}private function error(Throwable $e):ResponseInterface{return$this->message($e->getMessage(),422);}private function csrf():array{return['header'=>config('Security')->headerName,'hash'=>csrf_hash()];}
}
