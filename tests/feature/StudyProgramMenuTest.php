<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class StudyProgramMenuTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = db_connect();
        $forge = Config\Database::forge('tests');
        $forge->dropTable('students', true);
        $forge->dropTable('audit_logs', true);
        $forge->dropTable('study_programs', true);

        $forge->addField([
            'id'           => ['type' => 'INTEGER', 'auto_increment' => true],
            'code'         => ['type' => 'VARCHAR', 'constraint' => 20],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'degree_level' => ['type' => 'VARCHAR', 'constraint' => 20],
            'is_active'    => ['type' => 'INTEGER', 'default' => 1],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addUniqueKey('code');
        $forge->createTable('study_programs');

        $forge->addField([
            'id'               => ['type' => 'INTEGER', 'auto_increment' => true],
            'study_program_id' => ['type' => 'INTEGER'],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('students');

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
        $forge->dropTable('students', true);
        $forge->dropTable('audit_logs', true);
        $forge->dropTable('study_programs', true);
        parent::tearDown();
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $result = $this->get('/program-studi');

        $result->assertRedirectTo(site_url('login'));
    }

    public function testAdminCanOpenAngularProgramPage(): void
    {
        $result = $this->adminRequest()->get('/program-studi');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertStringContainsString('ng-controller="programStudiController"', $body);
        $this->assertStringContainsString('window.SITARA_PROGRAM_CONFIG', $body);
        $this->assertStringContainsString('Daftar program studi', $body);
    }

    public function testReadEndpointReturnsActiveAndArchivedPrograms(): void
    {
        $this->insertProgram('SI', 'Sistem Informasi', 'S1', 1);
        $this->insertProgram('AK', 'Akuntansi', 'D3', 0);

        $result = $this->adminRequest()->get('/program-studi/read');
        $result->assertStatus(200);
        $payload = json_decode((string) $result->getJSON(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($payload['ok']);
        $this->assertCount(2, $payload['data']);
        $this->assertSame('AK', $payload['data'][0]['code']);
        $this->assertSame('0', (string) $payload['data'][0]['is_active']);
    }

    public function testCreateEndpointAcceptsAngularJsonPayload(): void
    {
        $result = $this->adminMutationRequest()->post('/program-studi/post', [
            'code'         => 'TI',
            'name'         => 'Teknik Informatika',
            'degree_level' => 'S1',
            'is_active'    => true,
        ]);

        $result->assertStatus(201);
        $payload = json_decode((string) $result->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['ok']);
        $this->assertSame('TI', $payload['data']['code']);
        $this->assertSame(1, $this->db->table('study_programs')->where(['code' => 'TI', 'name' => 'Teknik Informatika'])->countAllResults());
        $this->assertSame(1, $this->db->table('audit_logs')->where(['action' => 'STUDY_PROGRAM_CREATED', 'entity_type' => 'study_programs'])->countAllResults());
    }

    public function testDeleteIsRejectedWhenProgramIsUsedByStudent(): void
    {
        $programId = $this->insertProgram('SI', 'Sistem Informasi', 'S1', 1);
        $this->db->table('students')->insert(['study_program_id' => $programId]);

        $result = $this->adminMutationRequest()->delete('/program-studi/delete/' . $programId);

        $result->assertStatus(422);
        $payload = json_decode((string) $result->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($payload['ok']);
        $this->assertStringContainsString('data mahasiswa', $payload['message']);
        $this->assertSame(1, $this->db->table('study_programs')->where('id', $programId)->countAllResults());
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

    private function insertProgram(string $code, string $name, string $degreeLevel, int $isActive): int
    {
        $now = '2026-08-12 00:00:00';
        $this->db->table('study_programs')->insert([
            'code'         => $code,
            'name'         => $name,
            'degree_level' => $degreeLevel,
            'is_active'    => $isActive,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        return (int) $this->db->insertID();
    }
}
