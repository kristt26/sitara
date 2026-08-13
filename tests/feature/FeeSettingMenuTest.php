<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/** @internal */
final class FeeSettingMenuTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = db_connect();
        $forge = Config\Database::forge('tests');
        foreach ($this->tables() as $table) $forge->dropTable($table, true);

        $this->table($forge, 'academic_years', ['code'=>['type'=>'VARCHAR','constraint'=>20], 'name'=>['type'=>'VARCHAR','constraint'=>100], 'is_active'=>['type'=>'INTEGER','default'=>1]]);
        $this->table($forge, 'academic_periods', ['academic_year_id'=>['type'=>'INTEGER'], 'semester_code'=>['type'=>'VARCHAR','constraint'=>20], 'is_active'=>['type'=>'INTEGER','default'=>1]]);
        $this->table($forge, 'study_programs', ['code'=>['type'=>'VARCHAR','constraint'=>20], 'name'=>['type'=>'VARCHAR','constraint'=>150], 'is_active'=>['type'=>'INTEGER','default'=>1]]);
        $this->table($forge, 'activity_types', ['code'=>['type'=>'VARCHAR','constraint'=>30], 'name'=>['type'=>'VARCHAR','constraint'=>150], 'is_active'=>['type'=>'INTEGER','default'=>1]]);
        $this->table($forge, 'exam_paths', ['code'=>['type'=>'VARCHAR','constraint'=>20], 'name'=>['type'=>'VARCHAR','constraint'=>100], 'is_active'=>['type'=>'INTEGER','default'=>1], 'sort_order'=>['type'=>'INTEGER','default'=>1]]);
        $this->table($forge, 'fee_settings', ['academic_period_id'=>['type'=>'INTEGER'], 'study_program_id'=>['type'=>'INTEGER'], 'activity_type_id'=>['type'=>'INTEGER'], 'exam_path_id'=>['type'=>'INTEGER'], 'version_no'=>['type'=>'INTEGER','default'=>1], 'effective_start_date'=>['type'=>'DATE','null'=>true], 'effective_end_date'=>['type'=>'DATE','null'=>true], 'is_active'=>['type'=>'INTEGER','default'=>1], 'notes'=>['type'=>'TEXT','null'=>true], 'created_at'=>['type'=>'DATETIME','null'=>true], 'updated_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->table($forge, 'fee_setting_items', ['fee_setting_id'=>['type'=>'INTEGER'], 'item_code'=>['type'=>'VARCHAR','constraint'=>40], 'item_name'=>['type'=>'VARCHAR','constraint'=>150], 'amount'=>['type'=>'DECIMAL','constraint'=>'15,2'], 'is_required'=>['type'=>'INTEGER','default'=>1], 'sort_order'=>['type'=>'INTEGER','default'=>1], 'created_at'=>['type'=>'DATETIME','null'=>true], 'updated_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->table($forge, 'student_bills', ['fee_setting_id'=>['type'=>'INTEGER','null'=>true], 'activity_id'=>['type'=>'INTEGER','null'=>true]]);
        $this->table($forge, 'audit_logs', ['user_id'=>['type'=>'INTEGER','null'=>true], 'action'=>['type'=>'VARCHAR','constraint'=>100], 'entity_type'=>['type'=>'VARCHAR','constraint'=>100], 'entity_id'=>['type'=>'INTEGER'], 'old_values'=>['type'=>'TEXT','null'=>true], 'new_values'=>['type'=>'TEXT','null'=>true], 'ip_address'=>['type'=>'VARCHAR','constraint'=>45,'null'=>true], 'created_at'=>['type'=>'DATETIME','null'=>true]]);

        $this->db->table('academic_years')->insert(['code'=>'2026/2027','name'=>'2026/2027','is_active'=>1]); $year=(int)$this->db->insertID();
        $this->db->table('academic_periods')->insert(['academic_year_id'=>$year,'semester_code'=>'GANJIL','is_active'=>1]); $this->period=(int)$this->db->insertID();
        $this->db->table('study_programs')->insert(['code'=>'TI','name'=>'Teknik Informatika','is_active'=>1]); $this->program=(int)$this->db->insertID();
        $this->db->table('activity_types')->insert(['code'=>'SEMINAR','name'=>'Seminar Proposal','is_active'=>1]); $this->activityType=(int)$this->db->insertID();
        $this->db->table('exam_paths')->insert(['code'=>'UMUM','name'=>'Umum','is_active'=>1,'sort_order'=>1]); $this->examPath=(int)$this->db->insertID();
    }

    protected function tearDown(): void
    {
        $forge=Config\Database::forge('tests'); foreach ($this->tables() as $table) $forge->dropTable($table,true); parent::tearDown();
    }

    public function testAdminCanOpenAngularModalPage(): void
    {
        $body=$this->adminRequest()->get('/keuangan/tarif')->getBody();
        $this->assertStringContainsString('ng-controller="tarifController"',$body);
        $this->assertStringContainsString('id="feeModal"',$body);
        $this->assertStringContainsString('Tambah komponen',$body);
    }

    public function testSavingSameCombinationCreatesNewVersion(): void
    {
        $first=$this->mutationRequest()->post('/keuangan/tarif/post',$this->payload(100000)); $first->assertStatus(201);
        $second=$this->mutationRequest()->post('/keuangan/tarif/post',$this->payload(125000)); $second->assertStatus(201);

        $rows=$this->db->table('fee_settings')->orderBy('version_no')->get()->getResultArray();
        $this->assertCount(2,$rows); $this->assertSame('0',(string)$rows[0]['is_active']); $this->assertSame('1',(string)$rows[1]['is_active']); $this->assertSame('2',(string)$rows[1]['version_no']);
        $this->assertSame('125000',(string)(int)$this->db->table('fee_setting_items')->where('fee_setting_id',$rows[1]['id'])->get()->getRow('amount'));
    }

    public function testReadOnlyShowsActivePeriodAndActiveVersion(): void
    {
        $this->mutationRequest()->post('/keuangan/tarif/post',$this->payload(100000))->assertStatus(201);
        $this->mutationRequest()->post('/keuangan/tarif/post',$this->payload(125000))->assertStatus(201);
        $payload=json_decode((string)$this->adminRequest()->get('/keuangan/tarif/read')->getJSON(),true);
        $this->assertSame($this->period,(int)$payload['data']['activePeriod']['id']); $this->assertCount(1,$payload['data']['settings']); $this->assertSame(125000,(int)$payload['data']['settings'][0]['total_amount']);
    }

    private function payload(int $amount): array { return ['study_program_id'=>$this->program,'activity_type_id'=>$this->activityType,'exam_path_id'=>$this->examPath,'notes'=>'Tarif pengujian','items'=>[['code'=>'POKOK','name'=>'Tarif Pokok','amount'=>$amount,'is_required'=>true]]]; }
    private function tables(): array { return ['audit_logs','student_bills','fee_setting_items','fee_settings','exam_paths','activity_types','study_programs','academic_periods','academic_years']; }
    private function table($forge,string $name,array $fields): void { $forge->addField(array_merge(['id'=>['type'=>'INTEGER','auto_increment'=>true]],$fields)); $forge->addKey('id',true); $forge->createTable($name); }
    private function adminRequest(): self { return $this->withSession(['auth'=>['id'=>1,'username'=>'admin','full_name'=>'Test Admin','role'=>'ADMIN']]); }
    private function mutationRequest(): self { helper('form'); return $this->adminRequest()->withHeaders([config('Security')->headerName=>csrf_hash()])->withBodyFormat('json'); }
}
