<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AcademicPeriodMenuTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = db_connect();
        $forge = Config\Database::forge('tests');
        $forge->dropTable('academic_periods', true);
        $forge->dropTable('academic_years', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 20],
            'start_year' => ['type' => 'INTEGER'],
            'end_year'   => ['type' => 'INTEGER'],
            'is_active'  => ['type' => 'INTEGER', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('academic_years');

        $forge->addField([
            'id'               => ['type' => 'INTEGER', 'auto_increment' => true],
            'academic_year_id' => ['type' => 'INTEGER'],
            'semester_code'    => ['type' => 'VARCHAR', 'constraint' => 10],
            'start_date'       => ['type' => 'DATE', 'null' => true],
            'end_date'         => ['type' => 'DATE', 'null' => true],
            'is_active'        => ['type' => 'INTEGER', 'default' => 0],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('academic_periods');
    }

    protected function tearDown(): void
    {
        $forge = Config\Database::forge('tests');
        $forge->dropTable('academic_periods', true);
        $forge->dropTable('academic_years', true);
        parent::tearDown();
    }

    public function testYearPageRedirectsGuestToLogin(): void
    {
        $result = $this->get('/periode');

        $result->assertRedirectTo(site_url('login'));
    }

    public function testAdminCanOpenAngularYearPage(): void
    {
        $result = $this->withSession([
            'auth' => [
                'id'        => 1,
                'username'  => 'test-admin',
                'full_name' => 'Test Admin',
                'role'      => 'ADMIN',
            ],
        ])->get('/periode');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertStringContainsString('ng-controller="tahunPeriodeController"', $body);
        $this->assertStringContainsString('Periode</a>', $body);
        $this->assertStringContainsString('window.SITARA_PERIOD_CONFIG', $body);
    }

    public function testPeriodDetailViewContainsSelectedYearContext(): void
    {
        helper(['form', 'url']);

        $html = view('pages/academic/year_periods', [
            'live'         => true,
            'activeMenu'   => 'periods',
            'pageTitle'    => 'Periode Akademik',
            'pageSubtitle' => 'Pengaturan semester',
            'yearId'       => 27,
            'csrfHeader'   => config('Security')->headerName,
            'csrfHash'     => csrf_hash(),
        ]);

        $this->assertStringContainsString('ng-controller="periodeController"', $html);
        $this->assertStringContainsString('"yearId":27', $html);
        $this->assertStringContainsString('Kembali ke daftar tahun', $html);
    }

    public function testYearReadEndpointReturnsPeriodCount(): void
    {
        $this->seedAcademicYears();

        $result = $this->adminRequest()->get('/periode/read');
        $result->assertStatus(200);
        $payload = json_decode((string) $result->getJSON(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($payload['ok']);
        $this->assertCount(2, $payload['data']);
        $this->assertSame('2026/2027', $payload['data'][0]['code']);
        $this->assertSame('1', (string) $payload['data'][0]['period_count']);
    }

    public function testPeriodReadEndpointOnlyReturnsSelectedYearPeriods(): void
    {
        [$activeYearId] = $this->seedAcademicYears();

        $result = $this->adminRequest()->get('/periode/' . $activeYearId . '/read');
        $result->assertStatus(200);
        $payload = json_decode((string) $result->getJSON(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($payload['ok']);
        $this->assertSame('2026/2027', $payload['data']['year']['code']);
        $this->assertCount(1, $payload['data']['periods']);
        $this->assertSame('GANJIL', $payload['data']['periods'][0]['semester_code']);
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

    /** @return array{int, int} */
    private function seedAcademicYears(): array
    {
        $now = '2026-08-12 00:00:00';
        $this->db->table('academic_years')->insert([
            'code' => '2026/2027', 'start_year' => 2026, 'end_year' => 2027,
            'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $activeYearId = (int) $this->db->insertID();
        $this->db->table('academic_years')->insert([
            'code' => '2025/2026', 'start_year' => 2025, 'end_year' => 2026,
            'is_active' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $archivedYearId = (int) $this->db->insertID();

        $this->db->table('academic_periods')->insert([
            'academic_year_id' => $activeYearId, 'semester_code' => 'GANJIL',
            'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->db->table('academic_periods')->insert([
            'academic_year_id' => $archivedYearId, 'semester_code' => 'GENAP',
            'is_active' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return [$activeYearId, $archivedYearId];
    }
}
