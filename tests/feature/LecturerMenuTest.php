<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class LecturerMenuTest extends CIUnitTestCase
{
    use FeatureTestTrait {
        populateGlobals as private populateFeatureGlobals;
    }

    /** @var array<string, mixed> */
    private array $testFiles = [];

    protected function populateGlobals(string $name, \CodeIgniter\HTTP\Request $request, ?array $params = null)
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
        $forge->dropTable('honor_payments', true);
        $forge->dropTable('activity_assignments', true);
        $forge->dropTable('audit_logs', true);
        $forge->dropTable('lecturers', true);

        $forge->addField([
            'id'                  => ['type' => 'INTEGER', 'auto_increment' => true],
            'nidn'                => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'nip'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'full_name'           => ['type' => 'VARCHAR', 'constraint' => 200],
            'email'               => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'phone'               => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'bank_name'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'bank_account_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'bank_account_name'   => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'tax_id'              => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'is_active'           => ['type' => 'INTEGER', 'default' => 1],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addUniqueKey('nidn');
        $forge->addUniqueKey('nip');
        $forge->createTable('lecturers');

        foreach (['activity_assignments', 'honor_payments'] as $table) {
            $forge->addField([
                'id'          => ['type' => 'INTEGER', 'auto_increment' => true],
                'lecturer_id' => ['type' => 'INTEGER'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable($table);
        }

        $forge->addField([
            'id'          => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'     => ['type' => 'INTEGER', 'null' => true],
            'action'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'entity_id'   => ['type' => 'INTEGER'],
            'old_values'  => ['type' => 'TEXT', 'null' => true],
            'new_values'  => ['type' => 'TEXT', 'null' => true],
            'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('audit_logs');
    }

    protected function tearDown(): void
    {
        $forge = Config\Database::forge('tests');
        $forge->dropTable('honor_payments', true);
        $forge->dropTable('activity_assignments', true);
        $forge->dropTable('audit_logs', true);
        $forge->dropTable('lecturers', true);
        parent::tearDown();
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $this->get('/master/dosen')->assertRedirectTo(site_url('login'));
    }

    public function testAdminCanOpenAngularLecturerPage(): void
    {
        $result = $this->adminRequest()->get('/master/dosen');

        $this->assertSame(200, $result->response()->getStatusCode(), $result->getBody());
        $body = $result->getBody();
        $this->assertStringContainsString('ng-controller="dosenController"', $body);
        $this->assertStringContainsString('window.SITARA_LECTURER_CONFIG', $body);
        $this->assertStringContainsString('Daftar dosen', $body);
        $this->assertStringContainsString('id="lecturerModal"', $body);
        $this->assertStringContainsString('Simpan data dosen', $body);
        $this->assertStringContainsString('Import Excel', $body);
        $this->assertStringContainsString('template-import-dosen.xlsx', $body);
    }

    public function testReadEndpointReturnsActiveAndArchivedLecturers(): void
    {
        $this->insertLecturer('0011223344', null, 'Andi Dosen', 1);
        $this->insertLecturer(null, '19800101', 'Budi Dosen', 0);

        $result = $this->adminRequest()->get('/master/dosen/read');
        $result->assertStatus(200);
        $payload = json_decode((string) $result->getJSON(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($payload['ok']);
        $this->assertCount(2, $payload['data']);
        $this->assertSame('Andi Dosen', $payload['data'][0]['full_name']);
        $this->assertSame('0', (string) $payload['data'][1]['is_active']);
    }

    public function testCreateEndpointAcceptsCompleteAngularJsonPayload(): void
    {
        $result = $this->adminMutationRequest()->post('/master/dosen/post', [
            'nidn'                => '0123456789',
            'nip'                 => '',
            'full_name'           => 'Dr. Nabila Putri',
            'email'               => 'NABILA@KAMPUS.AC.ID',
            'phone'               => '0812-3456-7890',
            'bank_name'           => 'Bank Papua',
            'bank_account_number' => '1234567890',
            'bank_account_name'   => 'Nabila Putri',
            'tax_id'              => '12.345.678.9-001.000',
            'is_active'           => true,
        ]);

        $result->assertStatus(201);
        $payload = json_decode((string) $result->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['ok']);
        $this->assertSame('nabila@kampus.ac.id', $payload['data']['email']);
        $this->assertNull($payload['data']['nip']);
        $this->assertSame(1, $this->db->table('audit_logs')->where(['action' => 'LECTURER_CREATED', 'entity_type' => 'lecturers'])->countAllResults());
    }

    public function testCreateRejectsPayloadWithoutNidnAndNip(): void
    {
        $result = $this->adminMutationRequest()->post('/master/dosen/post', [
            'full_name' => 'Dosen Tanpa Identitas',
            'is_active' => true,
        ]);

        $result->assertStatus(422);
        $payload = json_decode((string) $result->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($payload['ok']);
        $this->assertStringContainsString('NIDN atau NIP', $payload['message']);
    }

    public function testImportEndpointAcceptsTemplateAndKeepsNipOptional(): void
    {
        $path = FCPATH . 'templates/template-import-dosen.xlsx';
        $this->testFiles = [
            'file' => [
                'name'     => 'template-import-dosen.xlsx',
                'type'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($path),
            ],
        ];

        $result = $this->adminUploadRequest()->post('/master/dosen/import');
        $this->testFiles = [];

        $this->assertSame(200, $result->response()->getStatusCode(), $result->getBody());
        $payload = json_decode((string) $result->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['ok']);
        $this->assertSame(2, $payload['data']['total']);
        $this->assertSame(2, $payload['data']['created']);
        $this->assertNull($this->db->table('lecturers')->where('nidn', '0123456789')->get()->getRowArray()['nip']);
        $this->assertSame(2, $this->db->table('audit_logs')->where('action', 'LECTURER_IMPORTED')->countAllResults());

        $this->testFiles = [
            'file' => [
                'name'     => 'template-import-dosen.xlsx',
                'type'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($path),
            ],
        ];
        $updateResult = $this->adminUploadRequest()->post('/master/dosen/import');
        $this->testFiles = [];
        $this->assertSame(200, $updateResult->response()->getStatusCode(), $updateResult->getBody());
        $updatePayload = json_decode((string) $updateResult->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(0, $updatePayload['data']['created']);
        $this->assertSame(2, $updatePayload['data']['updated']);
        $this->assertSame(2, $this->db->table('lecturers')->countAllResults());
    }

    public function testDeleteIsRejectedWhenLecturerHasAssignment(): void
    {
        $lecturerId = $this->insertLecturer('0099887766', null, 'Dosen Terpakai', 1);
        $this->db->table('activity_assignments')->insert(['lecturer_id' => $lecturerId]);

        $result = $this->adminMutationRequest()->delete('/master/dosen/delete/' . $lecturerId);

        $result->assertStatus(422);
        $payload = json_decode((string) $result->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($payload['ok']);
        $this->assertStringContainsString('penugasan kegiatan akademik', $payload['message']);
        $this->assertSame(1, $this->db->table('lecturers')->where('id', $lecturerId)->countAllResults());
    }

    private function adminRequest(): self
    {
        return $this->withSession([
            'auth' => [
                'id'        => 1,
                'username'  => 'test-admin',
                'full_name' => 'Test Admin',
                'role'      => 'ADMIN',
            ],
        ]);
    }

    private function adminMutationRequest(): self
    {
        helper('form');

        return $this->adminRequest()
            ->withHeaders([config('Security')->headerName => csrf_hash()])
            ->withBodyFormat('json');
    }

    private function adminUploadRequest(): self
    {
        helper('form');

        return $this->adminRequest()
            ->withHeaders([config('Security')->headerName => csrf_hash()]);
    }

    private function insertLecturer(?string $nidn, ?string $nip, string $fullName, int $isActive): int
    {
        $now = '2026-08-12 00:00:00';
        $this->db->table('lecturers')->insert([
            'nidn'       => $nidn,
            'nip'        => $nip,
            'full_name'  => $fullName,
            'is_active'  => $isActive,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }
}
