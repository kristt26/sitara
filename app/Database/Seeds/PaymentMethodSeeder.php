<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            ['code' => 'TUNAI', 'name' => 'Tunai'],
            ['code' => 'TRANSFER', 'name' => 'Transfer Bank'],
            ['code' => 'VA', 'name' => 'Virtual Account'],
            ['code' => 'LAINNYA', 'name' => 'Lainnya'],
        ];

        $builder = $this->db->table('payment_methods');
        foreach ($data as $row) {
            $row['is_active'] = 1;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            $exists = $builder->where('code', $row['code'])->countAllResults() > 0;
            if (! $exists) {
                $builder->insert($row);
            }
        }
    }
}
