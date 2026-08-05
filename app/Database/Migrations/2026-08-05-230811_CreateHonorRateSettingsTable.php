<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHonorRateSettingsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `honor_rate_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_period_id` INT UNSIGNED NOT NULL,
  `study_program_id` INT UNSIGNED NULL,
  `activity_type_id` INT UNSIGNED NOT NULL,
  `role_type` VARCHAR(20) NOT NULL,
  `position_no` TINYINT UNSIGNED NOT NULL,
  `gross_amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `tax_rate_percent` DECIMAL(7,4) UNSIGNED NOT NULL DEFAULT 0.0000,
  `version_no` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `effective_start_date` DATE NULL,
  `effective_end_date` DATE NULL,
  `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_honor_rate_version` (`academic_period_id`,`study_program_id`,`activity_type_id`,`role_type`,`position_no`,`version_no`),
  KEY `idx_hrs_program` (`study_program_id`),
  KEY `idx_hrs_activity_type` (`activity_type_id`),
  KEY `idx_hrs_lookup` (`academic_period_id`,`activity_type_id`,`role_type`,`position_no`,`is_active`),
  CONSTRAINT `fk_hrs_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_hrs_program` FOREIGN KEY (`study_program_id`) REFERENCES `study_programs` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_hrs_activity_type` FOREIGN KEY (`activity_type_id`) REFERENCES `activity_types` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('honor_rate_settings', true);
    }
}
