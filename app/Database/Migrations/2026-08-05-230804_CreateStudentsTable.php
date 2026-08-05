<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudentsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `students` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nim` VARCHAR(30) NOT NULL,
  `full_name` VARCHAR(200) NOT NULL,
  `study_program_id` INT UNSIGNED NOT NULL,
  `cohort_year` SMALLINT UNSIGNED NULL,
  `email` VARCHAR(200) NULL,
  `phone` VARCHAR(30) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'AKTIF',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_nim` (`nim`),
  KEY `idx_students_program` (`study_program_id`),
  KEY `idx_students_status` (`status`),
  CONSTRAINT `fk_students_program` FOREIGN KEY (`study_program_id`) REFERENCES `study_programs` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('students', true);
    }
}
