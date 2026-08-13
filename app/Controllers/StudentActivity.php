<?php

namespace App\Controllers;

use App\Libraries\StudentActivitySpreadsheetImporter;
use App\Libraries\StudentActivityTemplateExporter;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class StudentActivity extends BaseController
{
    private const STATUSES = ['DRAFT', 'TERJADWAL', 'SELESAI', 'CANCELLED'];
    private const ROLES = ['PEMBIMBING', 'PENGUJI'];
    private BaseConnection $db;

    public function initController($request, $response, $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->db = db_connect();
    }

    public function index(): string
    {
        return view('pages/academic/student_activities', ['live'=>true,'activeMenu'=>'student-activities','pageTitle'=>'Kegiatan Mahasiswa','pageSubtitle'=>'Kegiatan, pembimbing, dan penguji pada periode aktif','statuses'=>self::STATUSES,'csrfHeader'=>config('Security')->headerName,'csrfHash'=>csrf_hash()]);
    }

    public function read(): ResponseInterface
    {
        try {
            $period = $this->activePeriod(); $rows = []; $rules = [];
            if ($period) {
                $rows = $this->db->table('academic_activities aa')
                    ->select('aa.*,s.nim,s.full_name,sp.code AS program_code,sp.name AS program_name,at.code AS activity_code,at.name AS activity_name,ep.code AS exam_path_code,ep.name AS exam_path_name,COUNT(DISTINCT CASE WHEN asg.role_type=\'PEMBIMBING\' THEN asg.id END) AS supervisor_count,COUNT(DISTINCT CASE WHEN asg.role_type=\'PENGUJI\' THEN asg.id END) AS examiner_count')
                    ->join('students s','s.id=aa.student_id')->join('study_programs sp','sp.id=aa.study_program_id')->join('activity_types at','at.id=aa.activity_type_id')->join('exam_paths ep','ep.id=aa.exam_path_id')->join('activity_assignments asg','asg.activity_id=aa.id AND asg.status=\'AKTIF\'','left')
                    ->where('aa.academic_period_id',$period['id'])
                    ->groupBy('aa.id,aa.activity_no,aa.student_id,aa.academic_period_id,aa.study_program_id,aa.activity_type_id,aa.exam_path_id,aa.attempt_no,aa.title,aa.scheduled_at,aa.completed_at,aa.status,aa.notes,aa.created_at,aa.updated_at,s.nim,s.full_name,sp.code,sp.name,at.code,at.name,ep.code,ep.name')
                    ->orderBy('aa.created_at','DESC')->get()->getResultArray();
                $rules = $this->db->table('activity_rules')->where(['academic_period_id'=>$period['id'],'is_active'=>1])->get()->getResultArray();
            }
            return $this->success(['activePeriod'=>$period,'activities'=>$rows,'rules'=>$rules,
                'activityHistory'=>$this->db->table('academic_activities')->select('student_id,activity_type_id,attempt_no,status')->orderBy('attempt_no','ASC')->get()->getResultArray(),
                'students'=>$this->db->table('students')->select('id,nim,full_name,study_program_id,status')->where('status','AKTIF')->orderBy('nim')->get()->getResultArray(),
                'activityTypes'=>$this->db->table('activity_types')->select('id,code,name,is_active')->where('is_active',1)->orderBy('code')->get()->getResultArray(),
                'examPaths'=>$this->db->table('exam_paths')->select('id,code,name')->where('is_active',1)->orderBy('sort_order')->get()->getResultArray(),
                'lecturers'=>$this->db->table('lecturers')->select('id,nidn,nip,full_name')->where('is_active',1)->orderBy('full_name')->get()->getResultArray()]);
        } catch (Throwable $e) { return $this->error($e); }
    }

    public function detail(int $id): ResponseInterface
    {
        try {
            $activity = $this->byId($id); if (!$activity) return $this->message('Kegiatan mahasiswa tidak ditemukan.',404);
            $activity['assignments'] = $this->db->table('activity_assignments asg')->select('asg.*,l.full_name,l.nidn,l.nip')->join('lecturers l','l.id=asg.lecturer_id')->where(['asg.activity_id'=>$id,'asg.status'=>'AKTIF'])->orderBy('asg.role_type')->orderBy('asg.position_no')->get()->getResultArray();
            $activity['rule'] = $this->ruleFor($activity);
            $activity['locked'] = $this->locked($id) || $this->terminalStatus($activity['status']);
            return $this->success($activity);
        } catch (Throwable $e) { return $this->error($e); }
    }

    public function create(): ResponseInterface
    {
        try {
            $period=$this->activePeriod(); if(!$period) throw new RuntimeException('Belum ada periode akademik aktif.');
            $input=$this->input(); $data=$this->activityPayload($input,(int)$period['id']); $data['attempt_no']=$this->nextAttempt($data['student_id'],$data['activity_type_id']); $rule=$this->validateActivity($data); $assignments=$this->validateAssignments($input['assignments']??[],$rule,$data['status']);
            $now=date('Y-m-d H:i:s'); $data['activity_no']='KGT-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));
            $this->db->transStart(); $this->db->table('academic_activities')->insert([...$data,'created_at'=>$now,'updated_at'=>$now]); $id=(int)$this->db->insertID(); $this->storeAssignments($id,$assignments,$now); $new=$this->snapshot($id); $this->audit('ACADEMIC_ACTIVITY_CREATED',$id,null,$new); $this->db->transComplete();
            if(!$this->db->transStatus()) throw new RuntimeException('Kegiatan mahasiswa belum dapat disimpan.');
            return $this->success($new,'Kegiatan dan penugasan dosen berhasil ditambahkan.',201);
        } catch(Throwable $e) { return $this->error($e); }
    }

    public function template(): ResponseInterface
    {
        try {
            $period = $this->activePeriod();
            if (!$period) throw new RuntimeException('Belum ada periode akademik aktif.');
            $students = $this->db->table('students s')->select('s.nim,s.full_name,sp.code program_code,sp.name program_name')->join('study_programs sp','sp.id=s.study_program_id')->where('s.status','AKTIF')->orderBy('s.nim')->get()->getResultArray();
            $types = $this->db->table('activity_types')->select('code,name')->where('is_active',1)->orderBy('code')->get()->getResultArray();
            $paths = $this->db->table('exam_paths')->select('code,name')->where('is_active',1)->orderBy('sort_order')->get()->getResultArray();
            $lecturers = $this->db->table('lecturers')->select('nidn,nip,full_name')->where('is_active',1)->orderBy('full_name')->get()->getResultArray();
            $lecturers = array_values(array_filter(array_map(function($x){$x['identifier']=trim((string)($x['nidn']?:$x['nip']));return$x;},$lecturers),fn($x)=>$x['identifier']!==''));
            $rules = $this->db->table('activity_rules ar')->select('sp.code program_code,at.code activity_code,ar.min_supervisors,ar.max_supervisors,ar.min_examiners,ar.max_examiners')->join('study_programs sp','sp.id=ar.study_program_id')->join('activity_types at','at.id=ar.activity_type_id')->where(['ar.academic_period_id'=>$period['id'],'ar.is_active'=>1])->orderBy('sp.code')->orderBy('at.code')->get()->getResultArray();
            $temporary = (new StudentActivityTemplateExporter())->export($students,$types,$paths,$lecturers,$rules);
            $contents = file_get_contents($temporary); @unlink($temporary);
            if($contents===false)throw new RuntimeException('Template kegiatan mahasiswa belum dapat dibuat.');
            return $this->response->download('template-import-kegiatan-mahasiswa.xlsx',$contents);
        } catch(Throwable $e) { return $this->error($e); }
    }

    public function import(): ResponseInterface
    {
        try {
            $period=$this->activePeriod();if(!$period)throw new RuntimeException('Belum ada periode akademik aktif.');
            $file=$this->request->getFile('file');if($file===null||$file->getError()!==UPLOAD_ERR_OK||!is_file($file->getTempName()))throw new RuntimeException('Pilih berkas Excel .xlsx yang akan diimpor.');
            if(strtolower($file->getClientExtension())!=='xlsx')throw new RuntimeException('Format berkas harus .xlsx. Gunakan template yang disediakan.');
            if($file->getSize()>4*1024*1024)throw new RuntimeException('Ukuran berkas Excel maksimal 4 MB.');
            $rows=(new StudentActivitySpreadsheetImporter())->read($file->getTempName());
            $students=[];foreach($this->db->table('students')->select('id,nim,study_program_id')->where('status','AKTIF')->get()->getResultArray()as$x)$students[strtoupper($x['nim'])]=$x;
            $types=[];foreach($this->db->table('activity_types')->select('id,code')->where('is_active',1)->get()->getResultArray()as$x)$types[strtoupper($x['code'])]=(int)$x['id'];
            $paths=[];foreach($this->db->table('exam_paths')->select('id,code')->where('is_active',1)->get()->getResultArray()as$x)$paths[strtoupper($x['code'])]=(int)$x['id'];
            $lecturers=[];foreach($this->db->table('lecturers')->select('id,nidn,nip')->where('is_active',1)->get()->getResultArray()as$x)foreach(['nidn','nip']as$key)if(trim((string)$x[$key])!=='')$lecturers[strtoupper(trim($x[$key]))]=(int)$x['id'];
            $created=0;$this->db->transBegin();
            try{foreach($rows as$row){$number=(int)$row['_row'];try{$nim=strtoupper(trim($row['nim']));$typeCode=strtoupper(trim($row['activity_code']));$pathCode=strtoupper(trim($row['exam_path_code']));if(!isset($students[$nim]))throw new RuntimeException('NIM tidak ditemukan atau tidak aktif.');if(!isset($types[$typeCode]))throw new RuntimeException('Kode jenis kegiatan tidak ditemukan atau tidak aktif.');if(!isset($paths[$pathCode]))throw new RuntimeException('Kode jalur ujian tidak ditemukan atau tidak aktif.');$student=$students[$nim];$activityTypeId=$types[$typeCode];$data=['student_id'=>(int)$student['id'],'academic_period_id'=>(int)$period['id'],'study_program_id'=>(int)$student['study_program_id'],'activity_type_id'=>$activityTypeId,'exam_path_id'=>$paths[$pathCode],'attempt_no'=>$this->nextAttempt((int)$student['id'],$activityTypeId),'title'=>trim($row['title'])?:null,'scheduled_at'=>$this->importedDateTime($row['scheduled_at']),'completed_at'=>null,'status'=>strtoupper(trim($row['status']?:'DRAFT')),'notes'=>trim($row['notes'])?:null];$rule=$this->validateActivity($data);$assignments=[];foreach($row['assignments']as$a){$identifier=strtoupper(trim($a['lecturer_identifier']));if(!isset($lecturers[$identifier]))throw new RuntimeException('Dosen '.$identifier.' pada sheet Penugasan Dosen tidak ditemukan atau tidak aktif.');$assignments[]=['lecturer_id'=>$lecturers[$identifier],'role_type'=>strtoupper(trim($a['role_type'])),'position_no'=>(int)$a['position_no']];}$assignments=$this->validateAssignments($assignments,$rule,$data['status']);$now=date('Y-m-d H:i:s');$data['activity_no']='KGT-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));$this->db->table('academic_activities')->insert([...$data,'created_at'=>$now,'updated_at'=>$now]);$id=(int)$this->db->insertID();$this->storeAssignments($id,$assignments,$now);$this->audit('ACADEMIC_ACTIVITY_IMPORTED',$id,null,$this->snapshot($id));$created++;}catch(Throwable$e){throw new RuntimeException('Baris '.$number.': '.$e->getMessage());}}if(!$this->db->transStatus())throw new RuntimeException('Data kegiatan mahasiswa belum dapat disimpan.');$this->db->transCommit();}catch(Throwable$e){$this->db->transRollback();throw$e;}
            return $this->success(['total'=>$created,'created'=>$created],$created.' kegiatan mahasiswa berhasil diimpor.',201);
        } catch(Throwable $e) { return $this->error($e); }
    }

    public function update(int $id): ResponseInterface
    {
        try {
            $old=$this->snapshot($id); if(!$old) return $this->message('Kegiatan mahasiswa tidak ditemukan.',404); if($this->terminalStatus($old['status'])) throw new RuntimeException('Kegiatan yang sudah selesai atau dibatalkan tidak dapat diubah.'); if($this->locked($id)) throw new RuntimeException('Kegiatan yang sudah memiliki tagihan atau hak honor tidak dapat diubah.');
            $period=$this->activePeriod(); if(!$period||(int)$old['academic_period_id']!==(int)$period['id']) throw new RuntimeException('Hanya kegiatan pada periode aktif yang dapat diubah.');
            $input=$this->input(); $data=$this->activityPayload($input,(int)$period['id']); $data['attempt_no']=(int)$old['attempt_no']; $rule=$this->validateActivity($data,$id); $assignments=$this->validateAssignments($input['assignments']??[],$rule,$data['status']); $now=date('Y-m-d H:i:s');
            $this->db->transStart(); $this->db->table('activity_assignments')->where('activity_id',$id)->delete(); $this->db->table('academic_activities')->where('id',$id)->update([...$data,'updated_at'=>$now]); $this->storeAssignments($id,$assignments,$now); $new=$this->snapshot($id); $this->audit('ACADEMIC_ACTIVITY_UPDATED',$id,$old,$new); $this->db->transComplete();
            if(!$this->db->transStatus()) throw new RuntimeException('Kegiatan mahasiswa belum dapat diperbarui.');
            return $this->success($new,'Kegiatan dan penugasan dosen berhasil diperbarui.');
        } catch(Throwable $e) { return $this->error($e); }
    }

    public function delete(int $id): ResponseInterface
    {
        try {
            $old=$this->snapshot($id); if(!$old) return $this->message('Kegiatan mahasiswa tidak ditemukan.',404); if($this->locked($id)) throw new RuntimeException('Kegiatan tidak dapat dihapus karena sudah memiliki tagihan atau hak honor.');
            $this->db->transStart(); $this->db->table('activity_assignments')->where('activity_id',$id)->delete(); $this->db->table('academic_activities')->where('id',$id)->delete(); $this->audit('ACADEMIC_ACTIVITY_DELETED',$id,$old,null); $this->db->transComplete();
            return $this->success(null,'Kegiatan mahasiswa dan penugasannya berhasil dihapus.');
        } catch(Throwable $e) { return $this->error($e); }
    }

    private function activityPayload(array $i,int $periodId):array
    {
        $studentId=(int)($i['student_id']??0); $student=$this->db->table('students')->where('id',$studentId)->get()->getRowArray(); $scheduled=trim((string)($i['scheduled_at']??''));
        return ['student_id'=>$studentId,'academic_period_id'=>$periodId,'study_program_id'=>(int)($student['study_program_id']??0),'activity_type_id'=>(int)($i['activity_type_id']??0),'exam_path_id'=>(int)($i['exam_path_id']??0),'attempt_no'=>1,'title'=>($v=trim((string)($i['title']??'')))===''?null:$v,'scheduled_at'=>$scheduled===''?null:str_replace('T',' ',$scheduled).(strlen($scheduled)===16?':00':''),'completed_at'=>null,'status'=>strtoupper(trim((string)($i['status']??'DRAFT'))),'notes'=>($v=trim((string)($i['notes']??'')))===''?null:$v];
    }

    private function importedDateTime(mixed $value): ?string
    {
        $value=trim((string)$value);if($value==='')return null;
        if(is_numeric($value)){$serial=(float)$value;$seconds=(int)round(($serial-floor($serial))*86400);return date('Y-m-d H:i:s',strtotime('1899-12-30 +'.(int)floor($serial).' days')+$seconds);}
        $timestamp=strtotime(str_replace('T',' ',$value));if($timestamp===false)throw new RuntimeException('Format jadwal harus YYYY-MM-DD HH:MM.');return date('Y-m-d H:i:s',$timestamp);
    }

    private function validateActivity(array $d,?int $except=null):array
    {
        if($this->db->table('students')->where(['id'=>$d['student_id'],'status'=>'AKTIF'])->countAllResults()===0) throw new RuntimeException('Mahasiswa tidak ditemukan atau tidak aktif.');
        if($this->db->table('activity_types')->where(['id'=>$d['activity_type_id'],'is_active'=>1])->countAllResults()===0) throw new RuntimeException('Jenis kegiatan wajib dipilih dan harus aktif.');
        if(!in_array($d['status'],self::STATUSES,true)) throw new RuntimeException('Status kegiatan tidak valid.');
        if($d['attempt_no']<1||$d['attempt_no']>99) throw new RuntimeException('Percobaan harus antara 1 dan 99.');
        if($this->db->table('exam_paths')->where(['id'=>$d['exam_path_id'],'is_active'=>1])->countAllResults()===0) throw new RuntimeException('Jalur ujian wajib dipilih dan harus aktif.');
        $rule=$this->ruleFor($d); if(!$rule) throw new RuntimeException('Aturan kegiatan aktif untuk kombinasi mahasiswa dan jenis kegiatan belum tersedia.');
        $completed=$this->db->table('academic_activities')->where(['student_id'=>$d['student_id'],'activity_type_id'=>$d['activity_type_id'],'status'=>'SELESAI']);if($except)$completed->where('id !=',$except);if($completed->countAllResults()>0)throw new RuntimeException('Mahasiswa sudah menyelesaikan jenis kegiatan tersebut dan tidak dapat didaftarkan kembali.');
        $q=$this->db->table('academic_activities')->where(['student_id'=>$d['student_id'],'academic_period_id'=>$d['academic_period_id'],'activity_type_id'=>$d['activity_type_id'],'attempt_no'=>$d['attempt_no']]); if($except)$q->where('id !=',$except); if($q->countAllResults()>0) throw new RuntimeException('Percobaan kegiatan tersebut sudah terdaftar.');
        if($d['title']!==null&&strlen($d['title'])>500) throw new RuntimeException('Judul maksimal 500 karakter.');
        if(in_array($d['status'],['TERJADWAL','SELESAI'],true)&&$d['scheduled_at']===null) throw new RuntimeException('Jadwal wajib diisi untuk kegiatan terjadwal atau selesai.');
        return $rule;
    }

    private function nextAttempt(int $studentId,int $activityTypeId):int
    {
        if($studentId<1||$activityTypeId<1)return 1;
        $history=$this->db->table('academic_activities')->select('attempt_no,status')->where(['student_id'=>$studentId,'activity_type_id'=>$activityTypeId])->orderBy('attempt_no','DESC')->orderBy('id','DESC')->get()->getResultArray();
        if($history===[])return 1;
        foreach($history as$row){if($row['status']==='SELESAI')throw new RuntimeException('Mahasiswa sudah menyelesaikan jenis kegiatan tersebut dan tidak dapat didaftarkan kembali.');if(in_array($row['status'],['DRAFT','TERJADWAL'],true))throw new RuntimeException('Mahasiswa masih memiliki kegiatan sejenis berstatus Draft atau Terjadwal. Batalkan kegiatan sebelumnya sebelum membuat percobaan baru.');}
        $attempt=max(array_map(fn($row)=>(int)$row['attempt_no'],$history))+1;
        if($attempt>99)throw new RuntimeException('Percobaan kegiatan sudah mencapai batas maksimum 99.');
        return $attempt;
    }

    private function validateAssignments(mixed $input,array $rule,string $status):array
    {
        if(!is_array($input)) throw new RuntimeException('Format penugasan dosen tidak valid.'); $clean=[]; $lecturers=[]; $counts=['PEMBIMBING'=>0,'PENGUJI'=>0];
        foreach($input as $row){$role=strtoupper(trim((string)($row['role_type']??'')));$position=(int)($row['position_no']??0);$lecturer=(int)($row['lecturer_id']??0);if($lecturer<1)continue;if(!in_array($role,self::ROLES,true)||$position<1)throw new RuntimeException('Peran atau posisi dosen tidak valid.');$max=$role==='PEMBIMBING'?(int)$rule['max_supervisors']:(int)$rule['max_examiners'];if($position>$max)throw new RuntimeException('Posisi '.$role.' melebihi batas aturan kegiatan.');if(isset($lecturers[$lecturer]))throw new RuntimeException('Dosen yang sama tidak dapat dipilih lebih dari satu kali pada kegiatan yang sama.');if($this->db->table('lecturers')->where(['id'=>$lecturer,'is_active'=>1])->countAllResults()===0)throw new RuntimeException('Dosen penugasan tidak ditemukan atau tidak aktif.');$lecturers[$lecturer]=true;$counts[$role]++;$clean[]=['lecturer_id'=>$lecturer,'role_type'=>$role,'position_no'=>$position];}
        if(in_array($status,['TERJADWAL','SELESAI'],true)){if($counts['PEMBIMBING']<(int)$rule['min_supervisors'])throw new RuntimeException('Minimal '.(int)$rule['min_supervisors'].' pembimbing wajib dipilih.');if($counts['PENGUJI']<(int)$rule['min_examiners'])throw new RuntimeException('Minimal '.(int)$rule['min_examiners'].' penguji wajib dipilih.');}
        return $clean;
    }

    private function storeAssignments(int $activityId,array $assignments,string $now):void{foreach($assignments as $a)$this->db->table('activity_assignments')->insert([...$a,'activity_id'=>$activityId,'assigned_date'=>date('Y-m-d'),'status'=>'AKTIF','created_at'=>$now,'updated_at'=>$now]);}
    private function ruleFor(array $d):?array{return$this->db->table('activity_rules')->where(['academic_period_id'=>$d['academic_period_id'],'study_program_id'=>$d['study_program_id'],'activity_type_id'=>$d['activity_type_id'],'is_active'=>1])->get()->getRowArray();}
    private function activePeriod():?array{return$this->db->table('academic_periods ap')->select('ap.id,ap.semester_code,ay.code AS academic_year_code')->join('academic_years ay','ay.id=ap.academic_year_id')->where(['ap.is_active'=>1,'ay.is_active'=>1])->get()->getRowArray();}
    private function byId(int $id):?array{return$this->db->table('academic_activities')->where('id',$id)->get()->getRowArray();}
    private function snapshot(int $id):?array{$a=$this->byId($id);if(!$a)return null;$a['assignments']=$this->db->table('activity_assignments')->where('activity_id',$id)->orderBy('role_type')->orderBy('position_no')->get()->getResultArray();return$a;}
    private function locked(int $id):bool{if($this->db->table('student_bills')->where('activity_id',$id)->countAllResults()>0)return true;$assignmentIds=array_column($this->db->table('activity_assignments')->select('id')->where('activity_id',$id)->get()->getResultArray(),'id');return$assignmentIds!==[]&&$this->db->table('honor_entitlements')->whereIn('assignment_id',$assignmentIds)->countAllResults()>0;}
    private function terminalStatus(string $status):bool{return in_array($status,['SELESAI','CANCELLED'],true);}
    private function input():array{$json=$this->request->getJSON(true);return is_array($json)?$json:$this->request->getPost();}
    private function audit(string $action,int $id,?array $old,?array $new):void{$auth=session('auth');$this->db->table('audit_logs')->insert(['user_id'=>is_array($auth)?($auth['id']??null):null,'action'=>$action,'entity_type'=>'academic_activities','entity_id'=>$id,'old_values'=>$old?json_encode($old,JSON_UNESCAPED_UNICODE):null,'new_values'=>$new?json_encode($new,JSON_UNESCAPED_UNICODE):null,'ip_address'=>$this->request->getIPAddress(),'created_at'=>date('Y-m-d H:i:s')]);}
    private function success(mixed $data=null,?string $message=null,int $status=200):ResponseInterface{return$this->response->setStatusCode($status)->setJSON(['ok'=>true,'message'=>$message,'data'=>$data,'csrf'=>$this->csrf()]);}private function message(string $m,int $s):ResponseInterface{return$this->response->setStatusCode($s)->setJSON(['ok'=>false,'message'=>$m,'csrf'=>$this->csrf()]);}private function error(Throwable $e):ResponseInterface{return$this->message($e->getMessage(),422);}private function csrf():array{return['header'=>config('Security')->headerName,'hash'=>csrf_hash()];}
}
