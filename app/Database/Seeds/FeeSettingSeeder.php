<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class FeeSettingSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $periodId = (int) $this->db->table('academic_periods ap')
            ->select('ap.id')->join('academic_years ay', 'ay.id = ap.academic_year_id')
            ->where(['ay.code' => '2026/2027', 'ap.semester_code' => 'GANJIL'])->get()->getRow('id');
        if ($periodId < 1) {
            return;
        }

        $programs = [
            ['code' => 'SI', 'activity' => 'PROPOSAL', 'items' => [['ADM', 'Pendaftaran', 250000], ['SEM', 'Seminar proposal', 750000], ['REG', 'Administrasi', 250000]]],
            ['code' => 'SI', 'activity' => 'MAGANG', 'items' => [['ADM', 'Administrasi magang', 250000], ['MAG', 'Pelaksanaan magang', 650000]]],
            ['code' => 'SI', 'activity' => 'TUGAS_AKHIR', 'items' => [['ADM', 'Administrasi tugas akhir', 250000], ['SID', 'Sidang tugas akhir', 2250000]]],
            ['code' => 'TI', 'activity' => 'PROPOSAL', 'items' => [['ADM', 'Pendaftaran', 250000], ['SEM', 'Seminar proposal', 850000], ['REG', 'Administrasi', 250000]]],
        ];

        foreach ($programs as $configuration) {
            $programId = (int) $this->db->table('study_programs')->where('code', $configuration['code'])->get()->getRow('id');
            $activityId = (int) $this->db->table('activity_types')->where('code', $configuration['activity'])->get()->getRow('id');
            if ($programId < 1 || $activityId < 1) {
                continue;
            }
            $exists = $this->db->table('fee_settings')->where([
                'academic_period_id' => $periodId,
                'study_program_id' => $programId,
                'activity_type_id' => $activityId,
                'version_no' => 1,
            ])->countAllResults();
            if ($exists > 0) {
                continue;
            }

            $this->db->table('fee_settings')->insert([
                'academic_period_id' => $periodId,
                'study_program_id' => $programId,
                'activity_type_id' => $activityId,
                'version_no' => 1,
                'effective_start_date' => '2026-08-01',
                'effective_end_date' => '2027-01-31',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $settingId = (int) $this->db->insertID();
            foreach ($configuration['items'] as $order => [$code, $name, $amount]) {
                $this->db->table('fee_setting_items')->insert([
                    'fee_setting_id' => $settingId,
                    'item_code' => $code,
                    'item_name' => $name,
                    'amount' => $amount,
                    'is_required' => 1,
                    'sort_order' => $order + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
