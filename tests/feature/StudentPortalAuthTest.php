<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use CodeIgniter\HTTP\Request;

/** @internal */
final class StudentPortalAuthTest extends CIUnitTestCase
{
    use FeatureTestTrait { populateGlobals as private populateFeatureGlobals; }

    private int $studentUserId;
    private int $studentId;
    private array $testFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = db_connect();
        $forge = Config\Database::forge('tests');
        foreach ($this->tables() as $table) $forge->dropTable($table, true);

        $this->table($forge, 'users', ['username'=>['type'=>'VARCHAR','constraint'=>100], 'email'=>['type'=>'VARCHAR','constraint'=>200,'null'=>true], 'full_name'=>['type'=>'VARCHAR','constraint'=>200], 'password_hash'=>['type'=>'VARCHAR','constraint'=>255], 'role'=>['type'=>'VARCHAR','constraint'=>20], 'is_active'=>['type'=>'INTEGER'], 'last_login_at'=>['type'=>'DATETIME','null'=>true], 'created_at'=>['type'=>'DATETIME','null'=>true], 'updated_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->table($forge, 'study_programs', ['code'=>['type'=>'VARCHAR','constraint'=>20], 'name'=>['type'=>'VARCHAR','constraint'=>150], 'degree_level'=>['type'=>'VARCHAR','constraint'=>20]]);
        $this->table($forge, 'students', ['user_id'=>['type'=>'INTEGER','null'=>true], 'nim'=>['type'=>'VARCHAR','constraint'=>30], 'full_name'=>['type'=>'VARCHAR','constraint'=>200], 'study_program_id'=>['type'=>'INTEGER'], 'cohort_year'=>['type'=>'INTEGER','null'=>true], 'email'=>['type'=>'VARCHAR','constraint'=>200,'null'=>true], 'phone'=>['type'=>'VARCHAR','constraint'=>30,'null'=>true], 'status'=>['type'=>'VARCHAR','constraint'=>20]]);
        $this->table($forge, 'activity_types', ['name'=>['type'=>'VARCHAR','constraint'=>100]]);
        $this->table($forge, 'exam_paths', ['name'=>['type'=>'VARCHAR','constraint'=>100]]);
        $this->table($forge, 'academic_years', ['code'=>['type'=>'VARCHAR','constraint'=>20]]);
        $this->table($forge, 'academic_periods', ['academic_year_id'=>['type'=>'INTEGER'], 'semester_code'=>['type'=>'VARCHAR','constraint'=>20]]);
        $this->table($forge, 'academic_activities', ['activity_no'=>['type'=>'VARCHAR','constraint'=>50], 'student_id'=>['type'=>'INTEGER'], 'academic_period_id'=>['type'=>'INTEGER'], 'activity_type_id'=>['type'=>'INTEGER'], 'exam_path_id'=>['type'=>'INTEGER'], 'attempt_no'=>['type'=>'INTEGER'], 'title'=>['type'=>'VARCHAR','constraint'=>500,'null'=>true], 'scheduled_at'=>['type'=>'DATETIME','null'=>true], 'completed_at'=>['type'=>'DATETIME','null'=>true], 'status'=>['type'=>'VARCHAR','constraint'=>20], 'created_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->table($forge, 'student_bills', ['bill_no'=>['type'=>'VARCHAR','constraint'=>50], 'student_id'=>['type'=>'INTEGER'], 'activity_type_id'=>['type'=>'INTEGER'], 'exam_path_id'=>['type'=>'INTEGER'], 'academic_period_id'=>['type'=>'INTEGER'], 'bill_date'=>['type'=>'DATE'], 'due_date'=>['type'=>'DATE','null'=>true], 'total_amount'=>['type'=>'DECIMAL','constraint'=>'15,2'], 'status'=>['type'=>'VARCHAR','constraint'=>25], 'updated_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->table($forge, 'payment_methods', ['code'=>['type'=>'VARCHAR','constraint'=>30], 'name'=>['type'=>'VARCHAR','constraint'=>100], 'is_active'=>['type'=>'INTEGER','default'=>1]]);
        $this->table($forge, 'student_payments', ['payment_no'=>['type'=>'VARCHAR','constraint'=>50], 'student_id'=>['type'=>'INTEGER'], 'payment_method_id'=>['type'=>'INTEGER'], 'payment_date'=>['type'=>'DATETIME'], 'amount'=>['type'=>'DECIMAL','constraint'=>'15,2'], 'reference_no'=>['type'=>'VARCHAR','constraint'=>100,'null'=>true], 'proof_file_path'=>['type'=>'TEXT','null'=>true], 'status'=>['type'=>'VARCHAR','constraint'=>20], 'notes'=>['type'=>'TEXT','null'=>true], 'created_at'=>['type'=>'DATETIME','null'=>true], 'updated_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->table($forge, 'student_payment_allocations', ['student_payment_id'=>['type'=>'INTEGER'], 'student_bill_id'=>['type'=>'INTEGER'], 'allocated_amount'=>['type'=>'DECIMAL','constraint'=>'15,2'], 'created_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->table($forge, 'audit_logs', ['user_id'=>['type'=>'INTEGER','null'=>true], 'action'=>['type'=>'VARCHAR','constraint'=>100], 'entity_type'=>['type'=>'VARCHAR','constraint'=>100], 'entity_id'=>['type'=>'INTEGER','null'=>true], 'old_values'=>['type'=>'TEXT','null'=>true], 'new_values'=>['type'=>'TEXT','null'=>true], 'ip_address'=>['type'=>'VARCHAR','constraint'=>45,'null'=>true], 'created_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->seed();
    }

    protected function tearDown(): void
    {
        $forge = Config\Database::forge('tests');
        foreach ($this->tables() as $table) $forge->dropTable($table, true);
        parent::tearDown();
    }

    public function testStudentLoginRedirectsToPortal(): void
    {
        $result = $this->login('2026001', 'Mahasiswa123!');
        $result->assertRedirectTo(site_url('portal-mahasiswa'));
        $this->assertNotNull($this->db->table('users')->where('id', $this->studentUserId)->get()->getRow('last_login_at'));
    }

    public function testAdminLoginStillRedirectsToAdminDashboard(): void
    {
        $this->login('admin', 'Admin123!')->assertRedirectTo(site_url('/'));
    }

    public function testGuestAndWrongRolesAreRedirected(): void
    {
        $this->get('/portal-mahasiswa')->assertRedirectTo(site_url('login'));
        $this->studentRequest()->get('/')->assertRedirectTo(site_url('portal-mahasiswa'));
        $this->withSession(['auth'=>['id'=>1,'username'=>'admin','full_name'=>'Admin','role'=>'ADMIN']])->get('/portal-mahasiswa')->assertRedirectTo(site_url('/'));
    }

    public function testPortalOnlyDisplaysAuthenticatedStudentsData(): void
    {
        $result = $this->studentRequest()->get('/portal-mahasiswa');
        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertStringContainsString('Ani Portal', $body);
        $this->assertStringContainsString('KGT-ANI', $body);
        $this->assertStringContainsString('INV-ANI', $body);
        $this->assertStringContainsString('PAY-ANI', $body);
        $this->assertStringContainsString('Rp 750.000', $body);
        $this->assertStringNotContainsString('Budi Rahasia', $body);
        $this->assertStringNotContainsString('KGT-BUDI', $body);
        $this->assertStringNotContainsString('INV-BUDI', $body);
    }

    public function testInactiveStudentCannotLogin(): void
    {
        $this->db->table('students')->where('id', $this->studentId)->update(['status'=>'NONAKTIF']);
        $this->login('2026001', 'Mahasiswa123!')->assertRedirect();
        $this->assertNull($this->db->table('users')->where('id', $this->studentUserId)->get()->getRow('last_login_at'));
    }

    public function testStudentCanSubmitPaymentProofAndAdminCanViewIt(): void
    {
        $proof = FCPATH . 'assets/img/logo.png';
        $this->testFiles = ['proof_file' => ['name'=>'proof.png', 'type'=>'image/png', 'tmp_name'=>$proof, 'error'=>UPLOAD_ERR_OK, 'size'=>filesize($proof)]];
        $result = $this->studentRequest()->withHeaders([config('Security')->headerName=>csrf_hash()])->post('/portal-mahasiswa/pembayaran', [
            'student_bill_id' => 1,
            'payment_method_id' => 1,
            'amount' => 500000,
            'reference_no' => 'TEST-001',
            'notes' => 'Bukti pembayaran pengujian',
        ]);
        $this->testFiles = [];
        $result->assertRedirectTo(site_url('portal-mahasiswa'));
        $payment = $this->db->table('student_payments')->where('status','MENUNGGU')->orderBy('id','DESC')->get()->getRowArray();
        $this->assertNotEmpty($payment, 'redirect=' . $result->getRedirectUrl() . ' error=' . (string) session('error'));
        $this->assertNotEmpty($payment['proof_file_path'], 'payment=' . json_encode($payment));
        $this->assertStringStartsWith('payment-proofs/', (string)$payment['proof_file_path']);
        $stored = WRITEPATH . 'uploads/' . $payment['proof_file_path'];
        $this->assertFileExists($stored);
        $this->assertSame(1, $this->db->table('audit_logs')->where('action','STUDENT_PAYMENT_SUBMITTED')->countAllResults());

        $admin = $this->withSession(['auth'=>['id'=>1,'username'=>'admin','full_name'=>'Admin','role'=>'ADMIN']])->get('/keuangan/verifikasi/bukti/' . $payment['id']);
        $admin->assertStatus(200);
        $this->assertNotEmpty($admin->getBody());
        @unlink($stored);
    }

    private function seed(): void
    {
        $now = '2026-08-14 10:00:00';
        $this->db->table('users')->insert(['username'=>'admin','email'=>'admin@test.local','full_name'=>'Admin','password_hash'=>password_hash('Admin123!', PASSWORD_DEFAULT),'role'=>'ADMIN','is_active'=>1,'created_at'=>$now,'updated_at'=>$now]);
        $this->db->table('users')->insert(['username'=>'2026001','full_name'=>'Ani Portal','password_hash'=>password_hash('Mahasiswa123!', PASSWORD_DEFAULT),'role'=>'MAHASISWA','is_active'=>1,'created_at'=>$now,'updated_at'=>$now]); $this->studentUserId=(int)$this->db->insertID();
        $this->db->table('users')->insert(['username'=>'2026002','full_name'=>'Budi Rahasia','password_hash'=>password_hash('Mahasiswa123!', PASSWORD_DEFAULT),'role'=>'MAHASISWA','is_active'=>1,'created_at'=>$now,'updated_at'=>$now]); $otherUser=(int)$this->db->insertID();
        $this->db->table('study_programs')->insert(['code'=>'TI','name'=>'Teknik Informatika','degree_level'=>'S1']); $program=(int)$this->db->insertID();
        $this->db->table('students')->insert(['user_id'=>$this->studentUserId,'nim'=>'2026001','full_name'=>'Ani Portal','study_program_id'=>$program,'cohort_year'=>2026,'email'=>'ani@test.local','status'=>'AKTIF']); $this->studentId=(int)$this->db->insertID();
        $this->db->table('students')->insert(['user_id'=>$otherUser,'nim'=>'2026002','full_name'=>'Budi Rahasia','study_program_id'=>$program,'cohort_year'=>2026,'status'=>'AKTIF']); $otherStudent=(int)$this->db->insertID();
        $this->db->table('activity_types')->insert(['name'=>'Seminar']); $type=(int)$this->db->insertID();
        $this->db->table('exam_paths')->insert(['name'=>'Umum']); $path=(int)$this->db->insertID();
        $this->db->table('academic_years')->insert(['code'=>'2026/2027']); $year=(int)$this->db->insertID();
        $this->db->table('academic_periods')->insert(['academic_year_id'=>$year,'semester_code'=>'GANJIL']); $period=(int)$this->db->insertID();
        $this->db->table('academic_activities')->insert(['activity_no'=>'KGT-ANI','student_id'=>$this->studentId,'academic_period_id'=>$period,'activity_type_id'=>$type,'exam_path_id'=>$path,'attempt_no'=>1,'title'=>'Kegiatan Ani','scheduled_at'=>'2026-09-01 09:00:00','status'=>'TERJADWAL','created_at'=>$now]);
        $this->db->table('academic_activities')->insert(['activity_no'=>'KGT-BUDI','student_id'=>$otherStudent,'academic_period_id'=>$period,'activity_type_id'=>$type,'exam_path_id'=>$path,'attempt_no'=>1,'title'=>'Kegiatan Budi','status'=>'DRAFT','created_at'=>$now]);
        $this->db->table('student_bills')->insert(['bill_no'=>'INV-ANI','student_id'=>$this->studentId,'activity_type_id'=>$type,'exam_path_id'=>$path,'academic_period_id'=>$period,'bill_date'=>'2026-08-14','due_date'=>'2026-08-31','total_amount'=>1000000,'status'=>'SEBAGIAN']); $bill=(int)$this->db->insertID();
        $this->db->table('student_bills')->insert(['bill_no'=>'INV-BUDI','student_id'=>$otherStudent,'activity_type_id'=>$type,'exam_path_id'=>$path,'academic_period_id'=>$period,'bill_date'=>'2026-08-14','total_amount'=>2000000,'status'=>'BELUM_DIBAYAR']);
        $this->db->table('payment_methods')->insert(['code'=>'TRF','name'=>'Transfer Bank','is_active'=>1]); $method=(int)$this->db->insertID();
        $this->db->table('student_payments')->insert(['payment_no'=>'PAY-ANI','student_id'=>$this->studentId,'payment_method_id'=>$method,'payment_date'=>'2026-08-15 10:00:00','amount'=>250000,'reference_no'=>'REF-ANI','status'=>'DITERIMA']); $payment=(int)$this->db->insertID();
        $this->db->table('student_payment_allocations')->insert(['student_payment_id'=>$payment,'student_bill_id'=>$bill,'allocated_amount'=>250000]);
    }

    private function table($forge, string $name, array $fields): void
    {
        $forge->addField(array_merge(['id'=>['type'=>'INTEGER','auto_increment'=>true]], $fields));
        $forge->addKey('id', true);
        $forge->createTable($name);
    }

    private function tables(): array
    {
        return ['audit_logs','student_payment_allocations','student_payments','payment_methods','student_bills','academic_activities','academic_periods','academic_years','exam_paths','activity_types','students','study_programs','users'];
    }

    private function login(string $identity, string $password): TestResponse
    {
        helper('form');
        return $this->withHeaders([config('Security')->headerName=>csrf_hash()])->post('/login', ['identity'=>$identity,'password'=>$password]);
    }

    private function studentRequest(): self
    {
        return $this->withSession(['auth'=>['id'=>$this->studentUserId,'username'=>'2026001','full_name'=>'Ani Portal','role'=>'MAHASISWA']]);
    }

    protected function populateGlobals(string $name, Request $request, ?array $params = null): Request
    {
        $request = $this->populateFeatureGlobals($name, $request, $params);
        service('superglobals')->setFilesArray($this->testFiles);
        return $request;
    }
}
