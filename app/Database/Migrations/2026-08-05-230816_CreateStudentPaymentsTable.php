<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudentPaymentsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `student_payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_no` VARCHAR(50) NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `payment_method_id` INT UNSIGNED NOT NULL,
  `payment_date` DATETIME NOT NULL,
  `amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `reference_no` VARCHAR(100) NULL,
  `proof_file_path` TEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'MENUNGGU',
  `verified_at` DATETIME NULL,
  `verified_by` INT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_payments_no` (`payment_no`),
  KEY `idx_sp_student` (`student_id`),
  KEY `idx_sp_method` (`payment_method_id`),
  KEY `idx_sp_status` (`status`),
  CONSTRAINT `fk_sp_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_sp_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('student_payments', true);
    }
}
