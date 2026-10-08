<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveStudentActivationExpiry extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE `student_activation_tokens` MODIFY `expires_at` DATETIME NULL');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `student_activation_tokens` MODIFY `expires_at` DATETIME NOT NULL');
    }
}
