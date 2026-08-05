<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            [
                'code'               => 'MAGANG',
                'name'               => 'Magang',
                'examiner_supported' => 1,
                'is_active'          => 1,
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'code'               => 'PROPOSAL',
                'name'               => 'Seminar Proposal',
                'examiner_supported' => 1,
                'is_active'          => 1,
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'code'               => 'TUGAS_AKHIR',
                'name'               => 'Tugas Akhir',
                'examiner_supported' => 1,
                'is_active'          => 1,
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
        ];

        $builder = $this->db->table('activity_types');
        foreach ($data as $row) {
            $exists = $builder->where('code', $row['code'])->countAllResults() > 0;
            if (! $exists) {
                $builder->insert($row);
            }
        }
    }
}
