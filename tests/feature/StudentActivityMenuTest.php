<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\HTTP\Request;

/** @internal */
final class StudentActivityMenuTest extends CIUnitTestCase
{
    use FeatureTestTrait { populateGlobals as private populateFeatureGlobals; }
    private array $testFiles=[];
    protected function populateGlobals(string $name,Request $request,?array $params=null){$request=$this->populateFeatureGlobals($name,$request,$params);service('superglobals')->setFilesArray($this->testFiles);return$request;}

    protected function setUp(): void
    {
        parent::setUp(); $this->db=db_connect(); $forge=Config\Database::forge('tests'); foreach($this->tables() as $table)$forge->dropTable($table,true);
        $this->table($forge,'academic_years',['code'=>['type'=>'VARCHAR','constraint'=>20],'name'=>['type'=>'VARCHAR','constraint'=>100],'is_active'=>['type'=>'INTEGER','default'=>1]]);
        $this->table($forge,'academic_periods',['academic_year_id'=>['type'=>'INTEGER'],'semester_code'=>['type'=>'VARCHAR','constraint'=>20],'is_active'=>['type'=>'INTEGER','default'=>1]]);
        $this->table($forge,'study_programs',['code'=>['type'=>'VARCHAR','constraint'=>20],'name'=>['type'=>'VARCHAR','constraint'=>150],'is_active'=>['type'=>'INTEGER','default'=>1]]);
        $this->table($forge,'students',['nim'=>['type'=>'VARCHAR','constraint'=>30],'full_name'=>['type'=>'VARCHAR','constraint'=>150],'study_program_id'=>['type'=>'INTEGER'],'status'=>['type'=>'VARCHAR','constraint'=>20]]);
        $this->table($forge,'activity_types',['code'=>['type'=>'VARCHAR','constraint'=>30],'name'=>['type'=>'VARCHAR','constraint'=>150],'is_active'=>['type'=>'INTEGER','default'=>1]]);
        $this->table($forge,'exam_paths',['code'=>['type'=>'VARCHAR','constraint'=>20],'name'=>['type'=>'VARCHAR','constraint'=>100],'is_active'=>['type'=>'INTEGER','default'=>1],'sort_order'=>['type'=>'INTEGER','default'=>1]]);
        $this->table($forge,'activity_rules',['academic_period_id'=>['type'=>'INTEGER'],'study_program_id'=>['type'=>'INTEGER'],'activity_type_id'=>['type'=>'INTEGER'],'min_supervisors'=>['type'=>'INTEGER','default'=>1],'max_supervisors'=>['type'=>'INTEGER','default'=>1],'min_examiners'=>['type'=>'INTEGER','default'=>0],'max_examiners'=>['type'=>'INTEGER','default'=>0],'examiner_optional'=>['type'=>'INTEGER','default'=>0],'is_active'=>['type'=>'INTEGER','default'=>1]]);
        $this->table($forge,'lecturers',['nidn'=>['type'=>'VARCHAR','constraint'=>30,'null'=>true],'nip'=>['type'=>'VARCHAR','constraint'=>50,'null'=>true],'full_name'=>['type'=>'VARCHAR','constraint'=>150],'is_active'=>['type'=>'INTEGER','default'=>1]]);
        $this->table($forge,'academic_activities',['activity_no'=>['type'=>'VARCHAR','constraint'=>50],'student_id'=>['type'=>'INTEGER'],'academic_period_id'=>['type'=>'INTEGER'],'study_program_id'=>['type'=>'INTEGER'],'activity_type_id'=>['type'=>'INTEGER'],'exam_path_id'=>['type'=>'INTEGER'],'attempt_no'=>['type'=>'INTEGER'],'title'=>['type'=>'VARCHAR','constraint'=>500,'null'=>true],'scheduled_at'=>['type'=>'DATETIME','null'=>true],'completed_at'=>['type'=>'DATETIME','null'=>true],'status'=>['type'=>'VARCHAR','constraint'=>20],'notes'=>['type'=>'TEXT','null'=>true],'created_at'=>['type'=>'DATETIME','null'=>true],'updated_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->table($forge,'activity_assignments',['activity_id'=>['type'=>'INTEGER'],'lecturer_id'=>['type'=>'INTEGER'],'role_type'=>['type'=>'VARCHAR','constraint'=>20],'position_no'=>['type'=>'INTEGER'],'assigned_date'=>['type'=>'DATE','null'=>true],'status'=>['type'=>'VARCHAR','constraint'=>20],'created_at'=>['type'=>'DATETIME','null'=>true],'updated_at'=>['type'=>'DATETIME','null'=>true]]); $this->table($forge,'honor_entitlements',['assignment_id'=>['type'=>'INTEGER']]); $this->table($forge,'student_bills',['activity_id'=>['type'=>'INTEGER'],'fee_setting_id'=>['type'=>'INTEGER','null'=>true]]);
        $this->table($forge,'audit_logs',['user_id'=>['type'=>'INTEGER','null'=>true],'action'=>['type'=>'VARCHAR','constraint'=>100],'entity_type'=>['type'=>'VARCHAR','constraint'=>100],'entity_id'=>['type'=>'INTEGER'],'old_values'=>['type'=>'TEXT','null'=>true],'new_values'=>['type'=>'TEXT','null'=>true],'ip_address'=>['type'=>'VARCHAR','constraint'=>45,'null'=>true],'created_at'=>['type'=>'DATETIME','null'=>true]]);

        $this->db->table('academic_years')->insert(['code'=>'2026/2027','name'=>'2026/2027','is_active'=>1]);$year=(int)$this->db->insertID();
        $this->db->table('academic_periods')->insert(['academic_year_id'=>$year,'semester_code'=>'GANJIL','is_active'=>1]);$this->period=(int)$this->db->insertID();
        $this->db->table('study_programs')->insert(['code'=>'TI','name'=>'Teknik Informatika','is_active'=>1]);$this->program=(int)$this->db->insertID();
        $this->db->table('students')->insert(['nim'=>'2026001','full_name'=>'Mahasiswa Uji','study_program_id'=>$this->program,'status'=>'AKTIF']);$this->student=(int)$this->db->insertID();
        $this->db->table('activity_types')->insert(['code'=>'SEMINAR','name'=>'Seminar Proposal','is_active'=>1]);$this->activityType=(int)$this->db->insertID();
        $this->db->table('exam_paths')->insert(['code'=>'UMUM','name'=>'Umum','is_active'=>1,'sort_order'=>1]);$this->examPath=(int)$this->db->insertID();
        $this->db->table('activity_rules')->insert(['academic_period_id'=>$this->period,'study_program_id'=>$this->program,'activity_type_id'=>$this->activityType,'min_supervisors'=>1,'max_supervisors'=>2,'min_examiners'=>1,'max_examiners'=>2,'examiner_optional'=>0,'is_active'=>1]);
        $this->db->table('lecturers')->insert(['nidn'=>'001','full_name'=>'Pembimbing Uji','is_active'=>1]);$this->supervisor=(int)$this->db->insertID();
        $this->db->table('lecturers')->insert(['nidn'=>'002','full_name'=>'Penguji Uji','is_active'=>1]);$this->examiner=(int)$this->db->insertID();
    }

    protected function tearDown():void{$forge=Config\Database::forge('tests');foreach($this->tables() as $table)$forge->dropTable($table,true);parent::tearDown();}

    public function testAdminCanOpenAngularModalPage():void{$body=$this->adminRequest()->get('/kegiatan-mahasiswa')->getBody();$this->assertStringContainsString('ng-controller="kegiatanMahasiswaController"',$body);$this->assertStringContainsString('id="studentActivityModal"',$body);$this->assertStringContainsString('Simpan kegiatan',$body);$this->assertStringContainsString('Import Excel',$body);$this->assertStringContainsString('kegiatan-mahasiswa/template',$body);$this->assertStringContainsString('Terjadwal &amp; Draft',$body);$this->assertStringContainsString("setStatusTab('SELESAI')",$body);$this->assertStringContainsString("setStatusTab('CANCELLED')",$body);$this->assertStringContainsString('class="required-mark">*</span>',$body);$this->assertStringContainsString('Terisi otomatis dari riwayat.',$body);$this->assertStringContainsString('readonly',$body);}

    public function testTemplateContainsDynamicReferenceSheets():void{$result=$this->adminRequest()->get('/kegiatan-mahasiswa/template');$result->assertStatus(200);$temporary=(new App\Libraries\StudentActivityTemplateExporter())->export([['nim'=>'2026001','full_name'=>'Mahasiswa Uji','program_code'=>'TI','program_name'=>'Teknik Informatika']],[['code'=>'SEMINAR','name'=>'Seminar Proposal']],[['code'=>'UMUM','name'=>'Umum']],[['identifier'=>'001','nidn'=>'001','nip'=>'','full_name'=>'Pembimbing Uji']],[['program_code'=>'TI','activity_code'=>'SEMINAR','min_supervisors'=>1,'max_supervisors'=>2,'min_examiners'=>1,'max_examiners'=>2]]);$zip=new ZipArchive();$this->assertTrue($zip->open($temporary)===true);$combined='';foreach($zip->statIndex(0)!==false?range(0,$zip->numFiles-1):[]as$i){$name=$zip->getNameIndex($i);if(str_starts_with($name,'xl/worksheets/'))$combined.=$zip->getFromIndex($i);}$zip->close();@unlink($temporary);$this->assertStringContainsString('Mahasiswa Uji',$combined);$this->assertStringContainsString('Pembimbing Uji',$combined);$this->assertStringContainsString('SEMINAR',$combined);}

    public function testImportCreatesActivityAndLecturerAssignments():void{$writer=new App\Libraries\XlsxTemplateWriter(FCPATH.'templates/template-import-kegiatan-mahasiswa.xlsx','activity-import-test-');$writer->replaceRowsAfter('Data Kegiatan',4,[['KGT001','2026001','SEMINAR','UMUM',1,'Judul Impor','2026-09-01 09:30','TERJADWAL','Dari Excel']],5);$writer->replaceRowsAfter('Penugasan Dosen',4,[['KGT001','001','PEMBIMBING',1],['KGT001','002','PENGUJI',1]],5);$path=$writer->path();$writer->close();$this->testFiles=['file'=>['name'=>'kegiatan.xlsx','type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','tmp_name'=>$path,'error'=>UPLOAD_ERR_OK,'size'=>filesize($path)]];helper('form');$result=$this->adminRequest()->withHeaders([config('Security')->headerName=>csrf_hash()])->post('/kegiatan-mahasiswa/import');$this->testFiles=[];@unlink($path);$result->assertStatus(201);$this->assertSame(1,$this->db->table('academic_activities')->countAllResults());$this->assertSame(2,$this->db->table('activity_assignments')->countAllResults());$this->assertSame(1,$this->db->table('audit_logs')->where('action','ACADEMIC_ACTIVITY_IMPORTED')->countAllResults());}

    public function testCreateUsesActivePeriodAndStudentProgram():void
    {
        $result=$this->mutationRequest()->post('/kegiatan-mahasiswa/post',$this->payload());$result->assertStatus(201);$row=$this->db->table('academic_activities')->get()->getRowArray();
        $this->assertSame($this->period,(int)$row['academic_period_id']);$this->assertSame($this->program,(int)$row['study_program_id']);$this->assertStringStartsWith('KGT-',$row['activity_no']);$this->assertSame(2,$this->db->table('activity_assignments')->where('activity_id',$row['id'])->countAllResults());$this->assertSame(1,$this->db->table('audit_logs')->where('action','ACADEMIC_ACTIVITY_CREATED')->countAllResults());
    }

    public function testCreateIsRejectedWithoutActiveRule():void
    {
        $this->db->table('activity_rules')->update(['is_active'=>0]);$result=$this->mutationRequest()->post('/kegiatan-mahasiswa/post',$this->payload());$result->assertStatus(422);$this->assertStringContainsString('Aturan kegiatan aktif',json_decode((string)$result->getJSON(),true)['message']);
    }

    public function testUsedActivityCannotBeDeleted():void
    {
        $created=$this->mutationRequest()->post('/kegiatan-mahasiswa/post',$this->payload());$created->assertStatus(201);$id=(int)json_decode((string)$created->getJSON(),true)['data']['id'];$this->db->table('student_bills')->insert(['activity_id'=>$id]);
        $result=$this->mutationRequest()->delete('/kegiatan-mahasiswa/delete/'.$id);$result->assertStatus(422);$this->assertStringContainsString('tagihan atau hak honor',json_decode((string)$result->getJSON(),true)['message']);
    }

    public function testScheduledActivityRejectsIncompleteRequiredTeam():void{$payload=$this->payload();$payload['assignments']=array_slice($payload['assignments'],0,1);$result=$this->mutationRequest()->post('/kegiatan-mahasiswa/post',$payload);$result->assertStatus(422);$this->assertStringContainsString('penguji wajib dipilih',json_decode((string)$result->getJSON(),true)['message']);}
    public function testDraftMaySaveIncompleteTeam():void{$payload=$this->payload();$payload['status']='DRAFT';$payload['assignments']=[];$this->mutationRequest()->post('/kegiatan-mahasiswa/post',$payload)->assertStatus(201);$this->assertSame(0,$this->db->table('activity_assignments')->countAllResults());}
    public function testCompletedActivityTypeCannotBeRegisteredAgain():void{$payload=$this->payload();$payload['status']='SELESAI';$this->mutationRequest()->post('/kegiatan-mahasiswa/post',$payload)->assertStatus(201);$payload['attempt_no']=2;$result=$this->mutationRequest()->post('/kegiatan-mahasiswa/post',$payload);$result->assertStatus(422);$this->assertStringContainsString('sudah menyelesaikan jenis kegiatan',json_decode((string)$result->getJSON(),true)['message']);}
    public function testActiveActivityBlocksNewAttempt():void{$this->mutationRequest()->post('/kegiatan-mahasiswa/post',$this->payload())->assertStatus(201);$payload=$this->payload();$payload['attempt_no']=77;$result=$this->mutationRequest()->post('/kegiatan-mahasiswa/post',$payload);$result->assertStatus(422);$this->assertStringContainsString('masih memiliki kegiatan sejenis',json_decode((string)$result->getJSON(),true)['message']);$this->assertSame(1,$this->db->table('academic_activities')->countAllResults());}
    public function testCancelledActivityAllowsAutomaticallyNumberedNextAttempt():void{$cancelled=$this->payload();$cancelled['status']='CANCELLED';$cancelled['scheduled_at']=null;$cancelled['attempt_no']=45;$this->mutationRequest()->post('/kegiatan-mahasiswa/post',$cancelled)->assertStatus(201);$next=$this->payload();$next['attempt_no']=99;$this->mutationRequest()->post('/kegiatan-mahasiswa/post',$next)->assertStatus(201);$attempts=array_map('intval',array_column($this->db->table('academic_activities')->orderBy('id')->get()->getResultArray(),'attempt_no'));$this->assertSame([1,2],$attempts);}
    public function testCompletedOrCancelledActivityCannotBeEdited():void{foreach(['SELESAI','CANCELLED']as$status){$payload=$this->payload();$payload['attempt_no']=$status==='SELESAI'?1:2;$payload['status']=$status;$created=$this->mutationRequest()->post('/kegiatan-mahasiswa/post',$payload);$created->assertStatus(201);$id=(int)json_decode((string)$created->getJSON(),true)['data']['id'];$result=$this->mutationRequest()->put('/kegiatan-mahasiswa/put/'.$id,$payload);$result->assertStatus(422);$this->assertStringContainsString('selesai atau dibatalkan',json_decode((string)$result->getJSON(),true)['message']);$this->db->table('activity_assignments')->where('activity_id',$id)->delete();$this->db->table('academic_activities')->where('id',$id)->delete();}}
    private function payload():array{return['student_id'=>$this->student,'activity_type_id'=>$this->activityType,'exam_path_id'=>$this->examPath,'attempt_no'=>1,'title'=>'Pengujian Sistem','scheduled_at'=>'2026-09-01T09:30','status'=>'TERJADWAL','notes'=>'Catatan uji','assignments'=>[['lecturer_id'=>$this->supervisor,'role_type'=>'PEMBIMBING','position_no'=>1],['lecturer_id'=>$this->examiner,'role_type'=>'PENGUJI','position_no'=>1]]];}
    private function tables():array{return['audit_logs','student_bills','honor_entitlements','activity_assignments','academic_activities','lecturers','activity_rules','exam_paths','activity_types','students','study_programs','academic_periods','academic_years'];}
    private function table($forge,string $name,array $fields):void{$forge->addField(array_merge(['id'=>['type'=>'INTEGER','auto_increment'=>true]],$fields));$forge->addKey('id',true);$forge->createTable($name);}
    private function adminRequest():self{return$this->withSession(['auth'=>['id'=>1,'username'=>'admin','full_name'=>'Test Admin','role'=>'ADMIN']]);}
    private function mutationRequest():self{helper('form');return$this->adminRequest()->withHeaders([config('Security')->headerName=>csrf_hash()])->withBodyFormat('json');}
}
