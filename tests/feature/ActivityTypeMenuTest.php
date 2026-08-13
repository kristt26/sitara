<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/** @internal */
final class ActivityTypeMenuTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp(); $this->db = db_connect(); $forge = Config\Database::forge('tests');
        foreach (['student_bills','academic_activities','honor_rate_settings','fee_settings','activity_rules','audit_logs','activity_types'] as $table) $forge->dropTable($table, true);
        $forge->addField(['id'=>['type'=>'INTEGER','auto_increment'=>true],'code'=>['type'=>'VARCHAR','constraint'=>30],'name'=>['type'=>'VARCHAR','constraint'=>100],'examiner_supported'=>['type'=>'INTEGER','default'=>1],'is_active'=>['type'=>'INTEGER','default'=>1],'created_at'=>['type'=>'DATETIME','null'=>true],'updated_at'=>['type'=>'DATETIME','null'=>true]]); $forge->addKey('id',true); $forge->addUniqueKey('code'); $forge->createTable('activity_types');
        foreach (['activity_rules','fee_settings','honor_rate_settings','academic_activities','student_bills'] as $table) { $forge->addField(['id'=>['type'=>'INTEGER','auto_increment'=>true],'activity_type_id'=>['type'=>'INTEGER']]); $forge->addKey('id',true); $forge->createTable($table); }
        $forge->addField(['id'=>['type'=>'INTEGER','auto_increment'=>true],'user_id'=>['type'=>'INTEGER','null'=>true],'action'=>['type'=>'VARCHAR','constraint'=>100],'entity_type'=>['type'=>'VARCHAR','constraint'=>100],'entity_id'=>['type'=>'INTEGER'],'old_values'=>['type'=>'TEXT','null'=>true],'new_values'=>['type'=>'TEXT','null'=>true],'ip_address'=>['type'=>'VARCHAR','constraint'=>45,'null'=>true],'created_at'=>['type'=>'DATETIME','null'=>true]]); $forge->addKey('id',true); $forge->createTable('audit_logs');
    }

    protected function tearDown(): void { $forge=Config\Database::forge('tests'); foreach (['student_bills','academic_activities','honor_rate_settings','fee_settings','activity_rules','audit_logs','activity_types'] as $table) $forge->dropTable($table,true); parent::tearDown(); }

    public function testGuestIsRedirected(): void { $this->get('/kegiatan')->assertRedirectTo(site_url('login')); }
    public function testAdminCanOpenInlineAngularPage(): void { $result=$this->adminRequest()->get('/kegiatan'); $result->assertStatus(200); $body=$result->getBody(); $this->assertStringContainsString('ng-controller="jenisKegiatanController"',$body); $this->assertStringContainsString('name="activityTypeForm"',$body); $this->assertStringNotContainsString('class="modal', $body); }
    public function testCreateActivityType(): void { $result=$this->mutationRequest()->post('/kegiatan/post',['code'=>'sidang_akhir','name'=>'Sidang Akhir','examiner_supported'=>true,'is_active'=>true]); $result->assertStatus(201); $payload=json_decode((string)$result->getJSON(),true); $this->assertSame('SIDANG_AKHIR',$payload['data']['code']); $this->assertSame(1,$this->db->table('audit_logs')->where('action','ACTIVITY_TYPE_CREATED')->countAllResults()); }
    public function testDeleteRejectsUsedActivityType(): void { $this->db->table('activity_types')->insert(['code'=>'MAGANG','name'=>'Magang','examiner_supported'=>1,'is_active'=>1]); $id=(int)$this->db->insertID(); $this->db->table('fee_settings')->insert(['activity_type_id'=>$id]); $result=$this->mutationRequest()->delete('/kegiatan/delete/'.$id); $result->assertStatus(422); $this->assertStringContainsString('pengaturan tarif',json_decode((string)$result->getJSON(),true)['message']); }

    private function adminRequest(): self { return $this->withSession(['auth'=>['id'=>1,'username'=>'admin','full_name'=>'Test Admin','role'=>'ADMIN']]); }
    private function mutationRequest(): self { helper('form'); return $this->adminRequest()->withHeaders([config('Security')->headerName=>csrf_hash()])->withBodyFormat('json'); }
}
