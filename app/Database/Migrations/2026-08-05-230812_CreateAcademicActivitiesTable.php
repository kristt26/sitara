<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAcademicActivitiesTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `academic_activities` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_no` VARCHAR(50) NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `academic_period_id` INT UNSIGNED NOT NULL,
  `study_program_id` INT UNSIGNED NOT NULL,
  `activity_type_id` INT UNSIGNED NOT NULL,
  `attempt_no` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `title` VARCHAR(500) NULL,
  `scheduled_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  `notes` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_academic_activity_no` (`activity_no`),
  UNIQUE KEY `uq_student_activity_attempt` (`student_id`,`academic_period_id`,`activity_type_id`,`attempt_no`),
  KEY `idx_aa_period` (`academic_period_id`),
  KEY `idx_aa_program` (`study_program_id`),
  KEY `idx_aa_activity_type` (`activity_type_id`),
  KEY `idx_aa_status` (`status`),
  CONSTRAINT `fk_aa_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_aa_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_aa_program` FOREIGN KEY (`study_program_id`) REFERENCES `study_programs` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_aa_activity_type` FOREIGN KEY (`activity_type_id`) REFERENCES `activity_types` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('academic_activities', true);
    }
}
