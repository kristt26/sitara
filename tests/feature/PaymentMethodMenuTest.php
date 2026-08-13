<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/** @internal */
final class PaymentMethodMenuTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp(); $this->db = db_connect(); $forge = Config\Database::forge('tests');
        foreach (['honor_payments', 'student_payments', 'audit_logs', 'payment_methods'] as $table) $forge->dropTable($table, true);
        $forge->addField(['id'=>['type'=>'INTEGER','auto_increment'=>true],'code'=>['type'=>'VARCHAR','constraint'=>30],'name'=>['type'=>'VARCHAR','constraint'=>100],'is_active'=>['type'=>'INTEGER','default'=>1],'created_at'=>['type'=>'DATETIME','null'=>true],'updated_at'=>['type'=>'DATETIME','null'=>true]]); $forge->addKey('id',true); $forge->addUniqueKey('code'); $forge->createTable('payment_methods');
        foreach (['student_payments','honor_payments'] as $table) { $forge->addField(['id'=>['type'=>'INTEGER','auto_increment'=>true],'payment_method_id'=>['type'=>'INTEGER','null'=>true]]); $forge->addKey('id',true); $forge->createTable($table); }
        $forge->addField(['id'=>['type'=>'INTEGER','auto_increment'=>true],'user_id'=>['type'=>'INTEGER','null'=>true],'action'=>['type'=>'VARCHAR','constraint'=>100],'entity_type'=>['type'=>'VARCHAR','constraint'=>100],'entity_id'=>['type'=>'INTEGER'],'old_values'=>['type'=>'TEXT','null'=>true],'new_values'=>['type'=>'TEXT','null'=>true],'ip_address'=>['type'=>'VARCHAR','constraint'=>45,'null'=>true],'created_at'=>['type'=>'DATETIME','null'=>true]]); $forge->addKey('id',true); $forge->createTable('audit_logs');
    }

    protected function tearDown(): void { $forge=Config\Database::forge('tests'); foreach (['honor_payments','student_payments','audit_logs','payment_methods'] as $table) $forge->dropTable($table,true); parent::tearDown(); }

    public function testGuestIsRedirected(): void { $this->get('/keuangan/metode-pembayaran')->assertRedirectTo(site_url('login')); }

    public function testAdminCanOpenInlineAngularPage(): void
    {
        $result=$this->adminRequest()->get('/keuangan/metode-pembayaran'); $result->assertStatus(200); $body=$result->getBody();
        $this->assertStringContainsString('ng-controller="metodePembayaranController"',$body);
        $this->assertStringContainsString('name="paymentMethodForm"',$body);
        $this->assertStringNotContainsString('class="modal',$body);
    }

    public function testCreatePaymentMethod(): void
    {
        $result=$this->mutationRequest()->post('/keuangan/metode-pembayaran/post',['code'=>'qris','name'=>'Pembayaran QRIS','is_active'=>true]);
        $result->assertStatus(201); $payload=json_decode((string)$result->getJSON(),true);
        $this->assertSame('QRIS',$payload['data']['code']);
        $this->assertSame(1,$this->db->table('audit_logs')->where('action','PAYMENT_METHOD_CREATED')->countAllResults());
    }

    public function testDuplicateCodeIsRejected(): void
    {
        $this->mutationRequest()->post('/keuangan/metode-pembayaran/post',['code'=>'VA','name'=>'Virtual Account','is_active'=>true])->assertStatus(201);
        $result=$this->mutationRequest()->post('/keuangan/metode-pembayaran/post',['code'=>'va','name'=>'VA Bank','is_active'=>true]);
        $result->assertStatus(422); $this->assertStringContainsString('sudah digunakan',json_decode((string)$result->getJSON(),true)['message']);
    }

    public function testDeleteAndCodeChangeAreRejectedWhenUsed(): void
    {
        $this->db->table('payment_methods')->insert(['code'=>'TRANSFER','name'=>'Transfer Bank','is_active'=>1]); $id=(int)$this->db->insertID();
        $this->db->table('student_payments')->insert(['payment_method_id'=>$id]);
        $delete=$this->mutationRequest()->delete('/keuangan/metode-pembayaran/delete/'.$id); $delete->assertStatus(422); $this->assertStringContainsString('pembayaran mahasiswa',json_decode((string)$delete->getJSON(),true)['message']);
        $update=$this->mutationRequest()->put('/keuangan/metode-pembayaran/put/'.$id,['code'=>'BANK','name'=>'Transfer Bank','is_active'=>true]); $update->assertStatus(422); $this->assertStringContainsString('Kode metode tidak dapat diubah',json_decode((string)$update->getJSON(),true)['message']);
    }

    private function adminRequest(): self { return $this->withSession(['auth'=>['id'=>1,'username'=>'admin','full_name'=>'Test Admin','role'=>'ADMIN']]); }
    private function mutationRequest(): self { helper('form'); return $this->adminRequest()->withHeaders([config('Security')->headerName=>csrf_hash()])->withBodyFormat('json'); }
}
