<?php

namespace App\Controllers;

use App\Libraries\StudentActivitySpreadsheetImporter;
use App\Libraries\StudentActivityTemplateExporter;
use App\Libraries\AcademicDocumentExporter;
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
                $rowsQuery = $this->db->table('academic_activities aa')
                    ->select('aa.*,s.nim,s.full_name,sp.code AS program_code,sp.name AS program_name,at.code AS activity_code,at.name AS activity_name,ep.code AS exam_path_code,ep.name AS exam_path_name,COUNT(DISTINCT CASE WHEN asg.role_type=\'PEMBIMBING\' THEN asg.id END) AS supervisor_count,COUNT(DISTINCT CASE WHEN asg.role_type=\'PENGUJI\' THEN asg.id END) AS examiner_count')
                    ->join('students s','s.id=aa.student_id')->join('study_programs sp','sp.id=aa.study_program_id')->join('activity_types at','at.id=aa.activity_type_id')->join('exam_paths ep','ep.id=aa.exam_path_id')->join('activity_assignments asg','asg.activity_id=aa.id AND asg.status=\'AKTIF\'','left')
                    ->where('aa.academic_period_id',$period['id']);
                $this->applyProgramScope($rowsQuery, 'aa.study_program_id');
                $rows = $rowsQuery
                    ->groupBy('aa.id,aa.activity_no,aa.student_id,aa.academic_period_id,aa.study_program_id,aa.activity_type_id,aa.exam_path_id,aa.attempt_no,aa.title,aa.scheduled_at,aa.completed_at,aa.status,aa.notes,aa.created_at,aa.updated_at,s.nim,s.full_name,sp.code,sp.name,at.code,at.name,ep.code,ep.name')
                    ->orderBy('aa.created_at','DESC')->get()->getResultArray();
                $rulesQuery = $this->db->table('activity_rules')->where(['academic_period_id'=>$period['id'],'is_active'=>1]); $this->applyProgramScope($rulesQuery, 'study_program_id'); $rules = $rulesQuery->get()->getResultArray();
            }
            return $this->success(['activePeriod'=>$period,'activities'=>$rows,'rules'=>$rules,
                'activityHistory'=>$this->scopedActivityHistory(),
                'students'=>$this->scopedStudents(),
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
            $studentsQuery = $this->db->table('students s')->select('s.nim,s.full_name,sp.code program_code,sp.name program_name')->join('study_programs sp','sp.id=s.study_program_id')->where('s.status','AKTIF'); $this->applyProgramScope($studentsQuery, 's.study_program_id'); $students = $studentsQuery->orderBy('s.nim')->get()->getResultArray();
            $types = $this->db->table('activity_types')->select('code,name')->where('is_active',1)->orderBy('code')->get()->getResultArray();
            $paths = $this->db->table('exam_paths')->select('code,name')->where('is_active',1)->orderBy('sort_order')->get()->getResultArray();
            $lecturers = $this->db->table('lecturers')->select('nidn,nip,full_name')->where('is_active',1)->orderBy('full_name')->get()->getResultArray();
            $lecturers = array_values(array_filter(array_map(function($x){$x['identifier']=trim((string)($x['nidn']?:$x['nip']));return$x;},$lecturers),fn($x)=>$x['identifier']!==''));
            $rulesQuery = $this->db->table('activity_rules ar')->select('sp.code program_code,at.code activity_code,ar.min_supervisors,ar.max_supervisors,ar.min_examiners,ar.max_examiners')->join('study_programs sp','sp.id=ar.study_program_id')->join('activity_types at','at.id=ar.activity_type_id')->where(['ar.academic_period_id'=>$period['id'],'ar.is_active'=>1]); $this->applyProgramScope($rulesQuery, 'ar.study_program_id'); $rules = $rulesQuery->orderBy('sp.code')->orderBy('at.code')->get()->getResultArray();
            $temporary = (new StudentActivityTemplateExporter())->export($students,$types,$paths,$lecturers,$rules);
            $contents = file_get_contents($temporary); @unlink($temporary);
            if($contents===false)throw new RuntimeException('Template kegiatan mahasiswa belum dapat dibuat.');
            return $this->response->download('template-import-kegiatan-mahasiswa.xlsx',$contents);
        } catch(Throwable $e) { return $this->error($e); }
    }

    public function document(int $id, string $kind = 'tunggal'): ResponseInterface
    {
        try {
            $activities = $this->documentActivities([$id]);
            if ($activities === []) throw new RuntimeException('Kegiatan mahasiswa tidak ditemukan.');
            $activities[0]['nomor_surat'] = $this->letterNumber($activities[0]);
            $kind = in_array($kind, ['nilai', 'tunggal'], true) ? $kind : 'tunggal';
            $template = $this->templatePath($activities[0], $kind);
            $file = (new AcademicDocumentExporter())->export($template, $activities, $kind, $this->templateFields($activities[0], $kind));
            $name = ($kind === 'nilai' ? 'nilai-' : 'berita-acara-') . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$activities[0]['nim']) . '.docx';
            $contents = file_get_contents($file); @unlink($file);
            if ($contents === false) throw new RuntimeException('Dokumen belum dapat dibuat.');
            return $this->response->download($name, $contents);
        } catch (Throwable $e) { return $this->error($e); }
    }

    public function documentTeam(): ResponseInterface
    {
        try {
            $input = $this->input();
            $ids = array_values(array_unique(array_map('intval', is_array($input['ids'] ?? null) ? $input['ids'] : [])));
            if (count($ids) < 2) throw new RuntimeException('Pilih minimal dua mahasiswa untuk berita acara tim.');
            $activities = $this->documentActivities($ids);
            if (count($activities) !== count($ids)) throw new RuntimeException('Sebagian kegiatan tidak ditemukan atau berada di luar akses Prodi.');
            $first = $activities[0];
            foreach ($activities as $activity) {
                if (!$activity['scheduled_at']) throw new RuntimeException('Semua kegiatan yang dipilih harus sudah memiliki jadwal.');
                foreach (['academic_period_id','activity_type_id','exam_path_id','scheduled_at'] as $field) {
                    if ((string)$activity[$field] !== (string)$first[$field]) throw new RuntimeException('Berita acara tim hanya dapat menggabungkan kegiatan dengan periode, kegiatan, jalur, dan jadwal yang sama.');
                }
            }
            $activities[0]['nomor_surat'] = $this->letterNumber($first);
            $template = $this->templatePath($first, 'tim');
            $file = (new AcademicDocumentExporter())->export($template, $activities, 'tim', $this->templateFields($first, 'tim'));
            $contents = file_get_contents($file); @unlink($file);
            if ($contents === false) throw new RuntimeException('Dokumen berita acara tim belum dapat dibuat.');
            return $this->response->download('berita-acara-tim.docx', $contents);
        } catch (Throwable $e) { return $this->error($e); }
    }

    private function documentActivities(array $ids): array
    {
        $query = $this->db->table('academic_activities aa')
            ->select('aa.*,s.nim,s.full_name,sp.code AS program_code,sp.name AS program_name,at.name AS activity_name,ep.name AS exam_path_name')
            ->join('students s','s.id=aa.student_id')->join('study_programs sp','sp.id=aa.study_program_id')
            ->join('activity_types at','at.id=aa.activity_type_id')->join('exam_paths ep','ep.id=aa.exam_path_id')
            ->select('aa.*,s.nim,s.full_name,sp.code AS program_code,sp.name AS program_name,at.code AS activity_code,at.name AS activity_name,ep.name AS exam_path_name')
            ->whereIn('aa.id', $ids)->whereIn('aa.status', ['TERJADWAL','SELESAI']);
        $this->applyProgramScope($query, 'aa.study_program_id');
        $rows = $query->get()->getResultArray();
        usort($rows, static fn(array $a, array $b): int => array_search((int)$a['id'], $ids, true) <=> array_search((int)$b['id'], $ids, true));
        foreach ($rows as &$row) {
            $row['assignments'] = $this->db->table('activity_assignments asg')->select('asg.*,l.full_name,l.nidn,l.nip')->join('lecturers l','l.id=asg.lecturer_id')->where(['asg.activity_id'=>(int)$row['id'],'asg.status'=>'AKTIF'])->orderBy('asg.role_type')->orderBy('asg.position_no')->get()->getResultArray();
        }
        return $rows;
    }

    private function letterNumber(array $activity): string
    {
        if (trim((string)($activity['letter_number'] ?? '')) !== '') return (string)$activity['letter_number'];
        $month = (int)date('n', strtotime((string)($activity['scheduled_at'] ?? 'now')));
        $roman = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month] ?? 'I';
        $year = date('Y', strtotime((string)($activity['scheduled_at'] ?? 'now')));
        $format = $this->db->table('letter_number_formats')->where(['study_program_id'=>(int)$activity['study_program_id'],'activity_type_id'=>(int)$activity['activity_type_id'],'is_active'=>1])->get()->getRowArray();
        if (!$format) return str_pad((string)((int)($activity['id'] ?? 0)), 3, '0', STR_PAD_LEFT) . '/INT.PRODI ' . ($activity['program_code'] ?? '') . '/SEPNOP/' . $roman . '/' . $year;
        $resetKey = strtoupper((string)$format['reset_scope']) === 'PERIODE' ? 'PERIODE-' . (int)$activity['academic_period_id'] : 'TAHUN-' . $year;
        $number = (int)$format['next_number'];
        if ((string)($format['reset_key'] ?? '') !== $resetKey) $number = 1;
        $tokens=['nomor'=>str_pad((string)$number,'0' + (int)$format['padding'],'0',STR_PAD_LEFT),'kode_surat'=>(string)$format['letter_code'],'kode_prodi'=>(string)($activity['program_code']??''),'nama_prodi'=>(string)($activity['program_name']??''),'kode_kegiatan'=>(string)($activity['activity_code']??''),'nama_kegiatan'=>(string)($activity['activity_name']??''),'bulan'=>(string)$month,'bulan_romawi'=>$roman,'tahun'=>$year,'periode'=>(string)($activity['academic_period_id']??'')];
        $numberString=preg_replace_callback('/\{([a-z0-9_]+)\}/i',static fn(array $m):string=>(string)($tokens[strtolower($m[1])]??$m[0]),(string)$format['format_template']);
        $this->db->table('letter_number_formats')->where('id',(int)$format['id'])->update(['next_number'=>$number+1,'reset_key'=>$resetKey,'updated_at'=>date('Y-m-d H:i:s')]);
        $this->db->table('academic_activities')->where('id',(int)$activity['id'])->update(['letter_number'=>$numberString,'updated_at'=>date('Y-m-d H:i:s')]);
        return $numberString;
    }

    private function templatePath(array $activity, string $kind): string
    {
        $documentType = $kind === 'nilai' ? 'NILAI' : ($kind === 'tim' ? 'BERITA_ACARA_TIM' : 'BERITA_ACARA_TUNGGAL');
        if ($this->db->tableExists('document_templates')) {
            $query = $this->db->table('document_templates')->where(['study_program_id'=>(int)$activity['study_program_id'],'activity_type_id'=>(int)$activity['activity_type_id'],'document_type'=>$documentType,'is_active'=>1])->orderBy('version_no','DESC')->orderBy('id','DESC')->get()->getRowArray();
            if ($query && is_file(WRITEPATH . $query['stored_path'])) return WRITEPATH . $query['stored_path'];
        }
        return ROOTPATH . 'public/templates/' . ($kind === 'nilai' ? 'nilai.docx' : ($kind === 'tim' ? 'berita-acara-tim.docx' : 'berita-acara-tunggal.docx'));
    }

    private function templateFields(array $activity, string $kind): array
    {
        if (!$this->db->tableExists('document_templates')) return [];
        $documentType = $kind === 'nilai' ? 'NILAI' : ($kind === 'tim' ? 'BERITA_ACARA_TIM' : 'BERITA_ACARA_TUNGGAL');
        $template = $this->db->table('document_templates')->where(['study_program_id'=>(int)$activity['study_program_id'],'activity_type_id'=>(int)$activity['activity_type_id'],'document_type'=>$documentType,'is_active'=>1])->orderBy('version_no','DESC')->orderBy('id','DESC')->get()->getRowArray();
        if (!$template) return [];
        return $this->db->table('document_template_fields')->where('template_id',(int)$template['id'])->orderBy('sort_order')->get()->getResultArray();
    }

    public function import(): ResponseInterface
    {
        try {
            $period=$this->activePeriod();if(!$period)throw new RuntimeException('Belum ada periode akademik aktif.');
            $file=$this->request->getFile('file');if($file===null||$file->getError()!==UPLOAD_ERR_OK||!is_file($file->getTempName()))throw new RuntimeException('Pilih berkas Excel .xlsx yang akan diimpor.');
            if(strtolower($file->getClientExtension())!=='xlsx')throw new RuntimeException('Format berkas harus .xlsx. Gunakan template yang disediakan.');
            if($file->getSize()>4*1024*1024)throw new RuntimeException('Ukuran berkas Excel maksimal 4 MB.');
            $rows=(new StudentActivitySpreadsheetImporter())->read($file->getTempName());
            $students=[];foreach($this->scopedStudents(['id','nim','study_program_id']) as$x)$students[strtoupper($x['nim'])]=$x;
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

    private function scopedStudents(array $columns = ['id','nim','full_name','study_program_id','status']): array
    {
        $query = $this->db->table('students')->select(implode(',', $columns))->where('status','AKTIF'); $this->applyProgramScope($query, 'study_program_id'); return $query->orderBy('nim')->get()->getResultArray();
    }

    private function scopedActivityHistory(): array
    {
        $query = $this->db->table('academic_activities aa')->select('aa.student_id,aa.activity_type_id,aa.attempt_no,aa.status')->join('students s','s.id=aa.student_id'); $this->applyProgramScope($query, 'aa.study_program_id'); return $query->orderBy('aa.attempt_no','ASC')->get()->getResultArray();
    }

    private function programIds(): array
    {
        $auth = session('auth'); if (!is_array($auth) || ($auth['role'] ?? null) !== 'PRODI') return array_column($this->db->table('study_programs')->select('id')->where('is_active',1)->get()->getResultArray(),'id'); if (!$this->db->tableExists('user_study_programs')) return [0]; $ids = array_column($this->db->table('user_study_programs')->select('study_program_id')->where('user_id',(int)($auth['id'] ?? 0))->get()->getResultArray(),'study_program_id'); return $ids === [] ? [0] : array_map('intval',$ids);
    }

    private function applyProgramScope($query, string $column): void { $auth=session('auth'); if(is_array($auth)&&($auth['role']??null)==='PRODI') $query->whereIn($column,$this->programIds()); }

    private function importedDateTime(mixed $value): ?string
    {
        $value=trim((string)$value);if($value==='')return null;
        if(is_numeric($value)){$serial=(float)$value;$seconds=(int)round(($serial-floor($serial))*86400);return date('Y-m-d H:i:s',strtotime('1899-12-30 +'.(int)floor($serial).' days')+$seconds);}
        $timestamp=strtotime(str_replace('T',' ',$value));if($timestamp===false)throw new RuntimeException('Format jadwal harus YYYY-MM-DD HH:MM.');return date('Y-m-d H:i:s',$timestamp);
    }

    private function validateActivity(array $d,?int $except=null):array
    {
        $studentQuery=$this->db->table('students')->where(['id'=>$d['student_id'],'status'=>'AKTIF']); $this->applyProgramScope($studentQuery,'study_program_id'); if($studentQuery->countAllResults()===0) throw new RuntimeException('Mahasiswa tidak ditemukan, tidak aktif, atau berada di luar scope Prodi.');
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
    private function ruleFor(array $d):?array{$query=$this->db->table('activity_rules')->where(['academic_period_id'=>$d['academic_period_id'],'study_program_id'=>$d['study_program_id'],'activity_type_id'=>$d['activity_type_id'],'is_active'=>1]);$this->applyProgramScope($query,'study_program_id');return$query->get()->getRowArray();}
    private function activePeriod():?array{return$this->db->table('academic_periods ap')->select('ap.id,ap.semester_code,ay.code AS academic_year_code')->join('academic_years ay','ay.id=ap.academic_year_id')->where(['ap.is_active'=>1,'ay.is_active'=>1])->get()->getRowArray();}
    private function byId(int $id):?array{$query=$this->db->table('academic_activities')->where('id',$id);$this->applyProgramScope($query,'study_program_id');return$query->get()->getRowArray();}
    private function snapshot(int $id):?array{$a=$this->byId($id);if(!$a)return null;$a['assignments']=$this->db->table('activity_assignments')->where('activity_id',$id)->orderBy('role_type')->orderBy('position_no')->get()->getResultArray();return$a;}
    private function locked(int $id):bool{if($this->db->table('student_bills')->where('activity_id',$id)->countAllResults()>0)return true;$assignmentIds=array_column($this->db->table('activity_assignments')->select('id')->where('activity_id',$id)->get()->getResultArray(),'id');return$assignmentIds!==[]&&$this->db->table('honor_entitlements')->whereIn('assignment_id',$assignmentIds)->countAllResults()>0;}
    private function terminalStatus(string $status):bool{return in_array($status,['SELESAI','CANCELLED'],true);}
    private function input():array{$json=$this->request->getJSON(true);return is_array($json)?$json:$this->request->getPost();}
    private function audit(string $action,int $id,?array $old,?array $new):void{$auth=session('auth');$this->db->table('audit_logs')->insert(['user_id'=>is_array($auth)?($auth['id']??null):null,'action'=>$action,'entity_type'=>'academic_activities','entity_id'=>$id,'old_values'=>$old?json_encode($old,JSON_UNESCAPED_UNICODE):null,'new_values'=>$new?json_encode($new,JSON_UNESCAPED_UNICODE):null,'ip_address'=>$this->request->getIPAddress(),'created_at'=>date('Y-m-d H:i:s')]);}
    private function success(mixed $data=null,?string $message=null,int $status=200):ResponseInterface{return$this->response->setStatusCode($status)->setJSON(['ok'=>true,'message'=>$message,'data'=>$data,'csrf'=>$this->csrf()]);}private function message(string $m,int $s):ResponseInterface{return$this->response->setStatusCode($s)->setJSON(['ok'=>false,'message'=>$m,'csrf'=>$this->csrf()]);}private function error(Throwable $e):ResponseInterface{return$this->message($e->getMessage(),422);}private function csrf():array{return['header'=>config('Security')->headerName,'hash'=>csrf_hash()];}
}
