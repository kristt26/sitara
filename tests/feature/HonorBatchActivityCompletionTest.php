<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/** @internal */
final class HonorBatchActivityCompletionTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = db_connect();
        $forge = Config\Database::forge('tests');
        foreach ($this->tables() as $table) $forge->dropTable($table, true);
        $this->table($forge,'academic_years',['code'=>['type'=>'VARCHAR','constraint'=>20],'is_active'=>['type'=>'INTEGER']]);
        $this->table($forge,'academic_periods',['academic_year_id'=>['type'=>'INTEGER'],'semester_code'=>['type'=>'VARCHAR','constraint'=>20],'is_active'=>['type'=>'INTEGER']]);
        $this->table($forge,'academic_activities',['academic_period_id'=>['type'=>'INTEGER'],'status'=>['type'=>'VARCHAR','constraint'=>20],'completed_at'=>['type'=>'DATETIME','null'=>true],'updated_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->table($forge,'lecturers',['full_name'=>['type'=>'VARCHAR','constraint'=>200],'bank_name'=>['type'=>'VARCHAR','constraint'=>100,'null'=>true],'bank_account_number'=>['type'=>'VARCHAR','constraint'=>100,'null'=>true],'bank_account_name'=>['type'=>'VARCHAR','constraint'=>200,'null'=>true]]);
        $this->table($forge,'activity_assignments',['activity_id'=>['type'=>'INTEGER'],'lecturer_id'=>['type'=>'INTEGER']]);
        $this->table($forge,'honor_entitlements',['assignment_id'=>['type'=>'INTEGER'],'gross_amount_snapshot'=>['type'=>'DECIMAL','constraint'=>'15,2'],'tax_amount'=>['type'=>'DECIMAL','constraint'=>'15,2'],'net_amount'=>['type'=>'DECIMAL','constraint'=>'15,2'],'status'=>['type'=>'VARCHAR','constraint'=>25],'updated_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->table($forge,'honor_payment_batches',['batch_no'=>['type'=>'VARCHAR','constraint'=>50],'academic_period_id'=>['type'=>'INTEGER'],'payment_date'=>['type'=>'DATE','null'=>true],'status'=>['type'=>'VARCHAR','constraint'=>20],'reference_no'=>['type'=>'VARCHAR','constraint'=>100,'null'=>true],'proof_file_path'=>['type'=>'TEXT','null'=>true],'notes'=>['type'=>'TEXT','null'=>true],'created_at'=>['type'=>'DATETIME','null'=>true],'updated_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->table($forge,'honor_payments',['honor_payment_batch_id'=>['type'=>'INTEGER'],'lecturer_id'=>['type'=>'INTEGER'],'payment_method_id'=>['type'=>'INTEGER','null'=>true],'bank_name_snapshot'=>['type'=>'VARCHAR','constraint'=>100,'null'=>true],'bank_account_number_snapshot'=>['type'=>'VARCHAR','constraint'=>100,'null'=>true],'bank_account_name_snapshot'=>['type'=>'VARCHAR','constraint'=>200,'null'=>true],'gross_total'=>['type'=>'DECIMAL','constraint'=>'15,2'],'tax_total'=>['type'=>'DECIMAL','constraint'=>'15,2'],'net_total'=>['type'=>'DECIMAL','constraint'=>'15,2'],'status'=>['type'=>'VARCHAR','constraint'=>20],'created_at'=>['type'=>'DATETIME','null'=>true],'updated_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->table($forge,'honor_payment_items',['honor_payment_id'=>['type'=>'INTEGER'],'honor_entitlement_id'=>['type'=>'INTEGER'],'gross_amount'=>['type'=>'DECIMAL','constraint'=>'15,2'],'tax_amount'=>['type'=>'DECIMAL','constraint'=>'15,2'],'net_amount'=>['type'=>'DECIMAL','constraint'=>'15,2'],'created_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->table($forge,'payment_methods',['code'=>['type'=>'VARCHAR','constraint'=>20],'name'=>['type'=>'VARCHAR','constraint'=>100],'is_active'=>['type'=>'INTEGER']]);
        $this->table($forge,'audit_logs',['user_id'=>['type'=>'INTEGER','null'=>true],'action'=>['type'=>'VARCHAR','constraint'=>100],'entity_type'=>['type'=>'VARCHAR','constraint'=>100],'entity_id'=>['type'=>'INTEGER','null'=>true],'old_values'=>['type'=>'TEXT','null'=>true],'new_values'=>['type'=>'TEXT','null'=>true],'ip_address'=>['type'=>'VARCHAR','constraint'=>45,'null'=>true],'created_at'=>['type'=>'DATETIME','null'=>true]]);

        $this->db->table('academic_years')->insert(['code'=>'2026/2027','is_active'=>1]); $year=(int)$this->db->insertID();
        $this->db->table('academic_periods')->insert(['academic_year_id'=>$year,'semester_code'=>'GANJIL','is_active'=>1]); $this->period=(int)$this->db->insertID();
        $this->db->table('academic_activities')->insert(['academic_period_id'=>$this->period,'status'=>'TERJADWAL']); $this->activity=(int)$this->db->insertID();
        $this->db->table('lecturers')->insert(['full_name'=>'Dosen Uji','bank_name'=>'Bank','bank_account_number'=>'123','bank_account_name'=>'Dosen Uji']); $lecturer=(int)$this->db->insertID();
        $this->db->table('activity_assignments')->insert(['activity_id'=>$this->activity,'lecturer_id'=>$lecturer]); $assignment=(int)$this->db->insertID();
        $this->db->table('honor_entitlements')->insert(['assignment_id'=>$assignment,'gross_amount_snapshot'=>1000000,'tax_amount'=>50000,'net_amount'=>950000,'status'=>'DISETUJUI']); $this->entitlement=(int)$this->db->insertID();
    }

    protected function tearDown(): void
    {
        $forge=Config\Database::forge('tests'); foreach($this->tables() as$table)$forge->dropTable($table,true); parent::tearDown();
    }

    public function testCreatingBatchAutomaticallyCompletesActivity(): void
    {
        helper('form');
        $response=$this->withSession(['auth'=>['id'=>1,'role'=>'ADMIN','username'=>'admin','full_name'=>'Admin']])->withHeaders([config('Security')->headerName=>csrf_hash()])->withBodyFormat('json')->post('/honor/pembayaran/post',['entitlement_ids'=>[$this->entitlement]]);
        $response->assertStatus(201);
        $activity=$this->db->table('academic_activities')->where('id',$this->activity)->get()->getRowArray();
        $this->assertSame('SELESAI',$activity['status']);
        $this->assertNotEmpty($activity['completed_at']);
        $this->assertSame(1,$this->db->table('audit_logs')->where('action','ACTIVITY_AUTO_COMPLETED_FROM_HONOR_BATCH')->countAllResults());
    }

    private function tables(): array { return ['audit_logs','payment_methods','honor_payment_items','honor_payments','honor_payment_batches','honor_entitlements','activity_assignments','lecturers','academic_activities','academic_periods','academic_years']; }
    private function table($forge,string$name,array$fields):void{$forge->addField(array_merge(['id'=>['type'=>'INTEGER','auto_increment'=>true]],$fields));$forge->addKey('id',true);$forge->createTable($name);}
}
