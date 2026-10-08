<?php

use CodeIgniter\HTTP\Request;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/** @internal */
final class StudentMenuTest extends CIUnitTestCase
{
    use FeatureTestTrait { populateGlobals as private populateFeatureGlobals; }

    private array $testFiles = [];

    protected function populateGlobals(string $name, Request $request, ?array $params = null)
    {
        $request = $this->populateFeatureGlobals($name, $request, $params);
        service('superglobals')->setFilesArray($this->testFiles);
        return $request;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = db_connect();
        $forge = Config\Database::forge('tests');
        foreach (['student_payments', 'student_bills', 'academic_activities', 'student_activation_tokens', 'students', 'audit_logs', 'users', 'study_programs'] as $table) $forge->dropTable($table, true);

        $forge->addField(['id' => ['type' => 'INTEGER', 'auto_increment' => true], 'code' => ['type' => 'VARCHAR', 'constraint' => 20], 'name' => ['type' => 'VARCHAR', 'constraint' => 150], 'degree_level' => ['type' => 'VARCHAR', 'constraint' => 20], 'is_active' => ['type' => 'INTEGER', 'default' => 1], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $forge->addKey('id', true); $forge->addUniqueKey('code'); $forge->createTable('study_programs');
        $forge->addField(['id' => ['type' => 'INTEGER', 'auto_increment' => true], 'username' => ['type' => 'VARCHAR', 'constraint' => 100], 'email' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true], 'full_name' => ['type' => 'VARCHAR', 'constraint' => 200], 'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255], 'role' => ['type' => 'VARCHAR', 'constraint' => 20], 'is_active' => ['type' => 'INTEGER', 'default' => 1], 'last_login_at' => ['type' => 'DATETIME', 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $forge->addKey('id', true); $forge->addUniqueKey('username'); $forge->createTable('users');
        $forge->addField(['id' => ['type' => 'INTEGER', 'auto_increment' => true], 'user_id' => ['type' => 'INTEGER', 'null' => true], 'nim' => ['type' => 'VARCHAR', 'constraint' => 30], 'full_name' => ['type' => 'VARCHAR', 'constraint' => 200], 'study_program_id' => ['type' => 'INTEGER'], 'cohort_year' => ['type' => 'INTEGER', 'null' => true], 'email' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true], 'phone' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true], 'status' => ['type' => 'VARCHAR', 'constraint' => 20], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $forge->addKey('id', true); $forge->addUniqueKey('nim'); $forge->createTable('students');
        $forge->addField(['id' => ['type' => 'INTEGER', 'auto_increment' => true], 'user_id' => ['type' => 'INTEGER'], 'token_hash' => ['type' => 'VARCHAR', 'constraint' => 64], 'expires_at' => ['type' => 'DATETIME', 'null' => true], 'used_at' => ['type' => 'DATETIME', 'null' => true], 'revoked_at' => ['type' => 'DATETIME', 'null' => true], 'created_by' => ['type' => 'INTEGER', 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true]]);
        $forge->addKey('id', true); $forge->addUniqueKey('token_hash'); $forge->createTable('student_activation_tokens');
        foreach (['academic_activities', 'student_bills', 'student_payments'] as $table) { $forge->addField(['id' => ['type' => 'INTEGER', 'auto_increment' => true], 'student_id' => ['type' => 'INTEGER']]); $forge->addKey('id', true); $forge->createTable($table); }
        $forge->addField(['id' => ['type' => 'INTEGER', 'auto_increment' => true], 'user_id' => ['type' => 'INTEGER', 'null' => true], 'action' => ['type' => 'VARCHAR', 'constraint' => 100], 'entity_type' => ['type' => 'VARCHAR', 'constraint' => 100], 'entity_id' => ['type' => 'INTEGER'], 'old_values' => ['type' => 'TEXT', 'null' => true], 'new_values' => ['type' => 'TEXT', 'null' => true], 'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true]]);
        $forge->addKey('id', true); $forge->createTable('audit_logs');
        $now = '2026-08-12 00:00:00';
        $this->db->table('users')->insert(['username' => 'admin', 'full_name' => 'Test Admin', 'password_hash' => password_hash('admin', PASSWORD_DEFAULT), 'role' => 'ADMIN', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->db->table('study_programs')->insertBatch([['code' => 'SI', 'name' => 'Sistem Informasi', 'degree_level' => 'S1', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now], ['code' => 'TI', 'name' => 'Teknik Informatika', 'degree_level' => 'S1', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]]);
    }

    protected function tearDown(): void
    {
        $forge = Config\Database::forge('tests');
        foreach (['student_payments', 'student_bills', 'academic_activities', 'student_activation_tokens', 'students', 'audit_logs', 'users', 'study_programs'] as $table) $forge->dropTable($table, true);
        parent::tearDown();
    }

    public function testGuestIsRedirected(): void { $this->get('/master/mahasiswa')->assertRedirectTo(site_url('login')); }

    public function testAdminCanOpenAngularStudentPage(): void
    {
        $result = $this->adminRequest()->get('/master/mahasiswa'); $result->assertStatus(200); $body = $result->getBody();
        $this->assertStringContainsString('ng-controller="mahasiswaController"', $body);
        $this->assertStringContainsString('id="studentModal"', $body);
        $this->assertStringContainsString('Import Excel', $body);
        $this->assertStringContainsString('master/mahasiswa/template', $body);
    }

    public function testCreateStudent(): void
    {
        $programId = (int) $this->db->table('study_programs')->where('code', 'SI')->get()->getRow('id');
        $result = $this->mutationRequest()->post('/master/mahasiswa/post', ['nim' => '202501001', 'full_name' => 'Ani Mahasiswa', 'study_program_id' => $programId, 'cohort_year' => 2025, 'email' => 'ANI@STUDENT.AC.ID', 'phone' => '0812-3456-7890', 'status' => 'AKTIF']);
        $result->assertStatus(201); $payload = json_decode((string) $result->getJSON(), true);
        $this->assertSame('ani@student.ac.id', $payload['data']['email']);
        $this->assertSame('SI', $payload['data']['program_code']);
        $this->assertArrayNotHasKey('activation', $payload['data']);
        $this->assertSame(1, $payload['data']['activation_email']['sent']);
        $account = $this->db->table('users')->where('username', '202501001')->get()->getRowArray();
        $this->assertSame('MAHASISWA', $account['role']);
        $this->assertSame((int) $account['id'], (int) $this->db->table('students')->where('nim', '202501001')->get()->getRow('user_id'));
        $token = $this->db->table('student_activation_tokens')->where('user_id', $account['id'])->get()->getRowArray();
        $this->assertNull($token['expires_at']);
        $this->assertSame(1, $this->db->table('audit_logs')->where('action', 'STUDENT_CREATED')->countAllResults());
    }

    public function testImportCreatesThenUpdatesStudents(): void
    {
        $path = FCPATH . 'templates/template-import-mahasiswa.xlsx';
        $this->setUpload($path); $first = $this->uploadRequest()->post('/master/mahasiswa/import'); $this->testFiles = [];
        $this->assertSame(200, $first->response()->getStatusCode(), $first->getBody());
        $payload = json_decode((string) $first->getJSON(), true); $this->assertSame(2, $payload['data']['created']); $this->assertSame(2, $payload['data']['accounts_created']); $this->assertSame(2, $payload['data']['activation_email']['sent']);
        $passwordHashes = array_column($this->db->table('users')->where('role', 'MAHASISWA')->orderBy('id')->get()->getResultArray(), 'password_hash');
        $this->setUpload($path); $second = $this->uploadRequest()->post('/master/mahasiswa/import'); $this->testFiles = [];
        $this->assertSame(200, $second->response()->getStatusCode(), $second->getBody());
        $payload = json_decode((string) $second->getJSON(), true); $this->assertSame(0, $payload['data']['created']); $this->assertSame(2, $payload['data']['updated']);
        $this->assertSame(0, $payload['data']['accounts_created']); $this->assertSame(0, $payload['data']['activation_email']['sent']);
        $this->assertSame($passwordHashes, array_column($this->db->table('users')->where('role', 'MAHASISWA')->orderBy('id')->get()->getResultArray(), 'password_hash'));
        $this->assertSame(2, $this->db->table('students')->countAllResults());
    }

    public function testActivationCodeCanBeUsedOnlyOnce(): void
    {
        $programId = (int) $this->db->table('study_programs')->where('code', 'SI')->get()->getRow('id');
        $result = $this->mutationRequest()->post('/master/mahasiswa/post', ['nim' => '202501002', 'full_name' => 'Budi Mahasiswa', 'study_program_id' => $programId, 'cohort_year' => 2025, 'email' => 'budi@student.ac.id', 'phone' => '', 'status' => 'AKTIF']);
        $studentId = (int) json_decode((string) $result->getJSON(), true)['data']['id'];
        $service = new App\Libraries\StudentAccountService($this->db);
        $activation = $service->issueActivation($studentId);
        $service->activate($activation['username'], $activation['code'], 'RahasiaBaru123!', '127.0.0.1');
        $user = $this->db->table('users')->where('username', $activation['username'])->get()->getRowArray();
        $this->assertTrue(password_verify('RahasiaBaru123!', $user['password_hash']));
        $this->expectException(RuntimeException::class);
        $service->activate($activation['username'], $activation['code'], 'PasswordLain123!', '127.0.0.1');
    }

    public function testActivationCodeDoesNotExpire(): void
    {
        $programId = (int) $this->db->table('study_programs')->where('code', 'SI')->get()->getRow('id');
        $result = $this->mutationRequest()->post('/master/mahasiswa/post', ['nim' => '202501003', 'full_name' => 'Cici Mahasiswa', 'study_program_id' => $programId, 'cohort_year' => 2025, 'email' => 'cici@student.ac.id', 'phone' => '', 'status' => 'AKTIF']);
        $studentId = (int) json_decode((string) $result->getJSON(), true)['data']['id'];
        $activation = (new App\Libraries\StudentAccountService($this->db))->issueActivation($studentId);
        $this->db->table('student_activation_tokens')->where('token_hash', hash('sha256', str_replace('-', '', $activation['code'])))->update(['expires_at' => '2020-01-01 00:00:00']);
        (new App\Libraries\StudentAccountService($this->db))->activate($activation['username'], $activation['code'], 'RahasiaBaru123!', '127.0.0.1');
        $user = $this->db->table('users')->where('username', $activation['username'])->get()->getRowArray();
        $this->assertTrue(password_verify('RahasiaBaru123!', $user['password_hash']));
    }

    public function testTemplateContainsProgramsFromDatabase(): void
    {
        $result = $this->adminRequest()->get('/master/mahasiswa/template');
        $result->assertStatus(200);
        $programs = $this->db->table('study_programs')->select('code, name, degree_level, is_active')->orderBy('code')->get()->getResultArray();
        $temporary = (new App\Libraries\StudentTemplateExporter())->export($programs);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($temporary) === true);
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $relationships = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $targets = [];
        foreach ($relationships->xpath('//*[local-name()="Relationship"]') as $relationship) {
            $targets[(string) $relationship['Id']] = (string) $relationship['Target'];
        }
        $sheetPath = null;
        foreach ($workbook->xpath('//*[local-name()="sheet"]') as $sheet) {
            if ((string) $sheet['name'] === 'Daftar Prodi') {
                $id = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                $target = ltrim($targets[$id], '/');
                $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }
        $xml = $zip->getFromName($sheetPath);
        $zip->close();
        @unlink($temporary);
        $this->assertStringContainsString('Sistem Informasi', $xml);
        $this->assertStringContainsString('Teknik Informatika', $xml);
    }

    public function testDeleteRejectsUsedStudent(): void
    {
        $programId = (int) $this->db->table('study_programs')->where('code', 'SI')->get()->getRow('id');
        $this->db->table('students')->insert(['nim' => '202400099', 'full_name' => 'Mahasiswa Terpakai', 'study_program_id' => $programId, 'status' => 'AKTIF']); $id = (int) $this->db->insertID();
        $this->db->table('academic_activities')->insert(['student_id' => $id]);
        $result = $this->mutationRequest()->delete('/master/mahasiswa/delete/' . $id); $result->assertStatus(422);
        $this->assertStringContainsString('kegiatan akademik', json_decode((string) $result->getJSON(), true)['message']);
    }

    private function setUpload(string $path): void { $this->testFiles = ['file' => ['name' => 'template-import-mahasiswa.xlsx', 'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)]]; }
    private function adminRequest(): self { return $this->withSession(['auth' => ['id' => 1, 'username' => 'admin', 'full_name' => 'Test Admin', 'role' => 'ADMIN']]); }
    private function mutationRequest(): self { helper('form'); return $this->adminRequest()->withHeaders([config('Security')->headerName => csrf_hash()])->withBodyFormat('json'); }
    private function uploadRequest(): self { helper('form'); return $this->adminRequest()->withHeaders([config('Security')->headerName => csrf_hash()]); }
}
