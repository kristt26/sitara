<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFeeSettingsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `fee_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_period_id` INT UNSIGNED NOT NULL,
  `study_program_id` INT UNSIGNED NOT NULL,
  `activity_type_id` INT UNSIGNED NOT NULL,
  `version_no` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `effective_start_date` DATE NULL,
  `effective_end_date` DATE NULL,
  `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee_setting_version` (`academic_period_id`,`study_program_id`,`activity_type_id`,`version_no`),
  KEY `idx_fs_program` (`study_program_id`),
  KEY `idx_fs_activity_type` (`activity_type_id`),
  KEY `idx_fs_active` (`is_active`),
  CONSTRAINT `fk_fs_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_fs_program` FOREIGN KEY (`study_program_id`) REFERENCES `study_programs` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_fs_activity_type` FOREIGN KEY (`activity_type_id`) REFERENCES `activity_types` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('fee_settings', true);
    }
}
