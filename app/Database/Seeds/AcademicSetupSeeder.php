<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AcademicSetupSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $years = [
            ['code' => '2026/2027', 'start_year' => 2026, 'end_year' => 2027, 'is_active' => 1],
            ['code' => '2025/2026', 'start_year' => 2025, 'end_year' => 2026, 'is_active' => 0],
        ];
        foreach ($years as $year) {
            $builder = $this->db->table('academic_years');
            if ($builder->where('code', $year['code'])->countAllResults() === 0) {
                $builder->insert($year + ['created_at' => $now, 'updated_at' => $now]);
            }
        }

        $yearId = (int) $this->db->table('academic_years')->where('code', '2026/2027')->get()->getRow('id');
        if ($yearId > 0) {
            foreach ([['code' => 'GANJIL', 'is_active' => 1], ['code' => 'GENAP', 'is_active' => 0]] as $semester) {
                $builder = $this->db->table('academic_periods');
                if ($builder->where(['academic_year_id' => $yearId, 'semester_code' => $semester['code']])->countAllResults() === 0) {
                    $builder->insert($semester + ['academic_year_id' => $yearId, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }

        $programs = [
            ['code' => 'SI', 'name' => 'Sistem Informasi', 'degree_level' => 'S1'],
            ['code' => 'TI', 'name' => 'Teknik Informatika', 'degree_level' => 'S1'],
            ['code' => 'MNJ', 'name' => 'Manajemen', 'degree_level' => 'S1'],
        ];
        foreach ($programs as $program) {
            $builder = $this->db->table('study_programs');
            if ($builder->where('code', $program['code'])->countAllResults() === 0) {
                $builder->insert($program + ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $siId = (int) $this->db->table('study_programs')->where('code', 'SI')->get()->getRow('id');
        $tiId = (int) $this->db->table('study_programs')->where('code', 'TI')->get()->getRow('id');
        $students = [
            ['nim' => '202401001', 'full_name' => 'Nabila Putri', 'study_program_id' => $siId],
            ['nim' => '202401017', 'full_name' => 'Fajar Ramadhan', 'study_program_id' => $siId],
            ['nim' => '202401029', 'full_name' => 'Rizky Akbar', 'study_program_id' => $tiId],
        ];
        foreach ($students as $student) {
            $builder = $this->db->table('students');
            if ($student['study_program_id'] > 0 && $builder->where('nim', $student['nim'])->countAllResults() === 0) {
                $builder->insert($student + ['status' => 'AKTIF', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
}
