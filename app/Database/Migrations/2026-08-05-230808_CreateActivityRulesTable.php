<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateActivityRulesTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `activity_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_period_id` INT UNSIGNED NOT NULL,
  `study_program_id` INT UNSIGNED NOT NULL,
  `activity_type_id` INT UNSIGNED NOT NULL,
  `min_supervisors` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `max_supervisors` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `min_examiners` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_examiners` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `examiner_optional` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_activity_rule` (`academic_period_id`,`study_program_id`,`activity_type_id`),
  KEY `idx_ar_program` (`study_program_id`),
  KEY `idx_ar_activity_type` (`activity_type_id`),
  CONSTRAINT `fk_ar_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ar_program` FOREIGN KEY (`study_program_id`) REFERENCES `study_programs` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ar_activity_type` FOREIGN KEY (`activity_type_id`) REFERENCES `activity_types` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('activity_rules', true);
    }
}
