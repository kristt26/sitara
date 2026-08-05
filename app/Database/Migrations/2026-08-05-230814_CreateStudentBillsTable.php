<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudentBillsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `student_bills` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bill_no` VARCHAR(50) NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `activity_id` INT UNSIGNED NOT NULL,
  `fee_setting_id` INT UNSIGNED NOT NULL,
  `academic_period_id` INT UNSIGNED NOT NULL,
  `study_program_id` INT UNSIGNED NOT NULL,
  `activity_type_id` INT UNSIGNED NOT NULL,
  `bill_date` DATE NOT NULL,
  `due_date` DATE NULL,
  `subtotal_amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `penalty_amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `status` VARCHAR(25) NOT NULL DEFAULT 'BELUM_DIBAYAR',
  `notes` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_bills_no` (`bill_no`),
  UNIQUE KEY `uq_student_bill_activity` (`activity_id`),
  KEY `idx_sb_student` (`student_id`),
  KEY `idx_sb_fee_setting` (`fee_setting_id`),
  KEY `idx_sb_period` (`academic_period_id`),
  KEY `idx_sb_program` (`study_program_id`),
  KEY `idx_sb_activity_type` (`activity_type_id`),
  KEY `idx_sb_status` (`status`),
  CONSTRAINT `fk_sb_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_sb_activity` FOREIGN KEY (`activity_id`) REFERENCES `academic_activities` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_sb_fee_setting` FOREIGN KEY (`fee_setting_id`) REFERENCES `fee_settings` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_sb_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_sb_program` FOREIGN KEY (`study_program_id`) REFERENCES `study_programs` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_sb_activity_type` FOREIGN KEY (`activity_type_id`) REFERENCES `activity_types` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('student_bills', true);
    }
}
