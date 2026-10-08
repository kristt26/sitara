<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentTemplates extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `document_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `study_program_id` INT UNSIGNED NOT NULL,
  `activity_type_id` INT UNSIGNED NOT NULL,
  `document_type` VARCHAR(30) NOT NULL,
  `academic_period_id` INT UNSIGNED NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `stored_path` VARCHAR(500) NOT NULL,
  `version_no` INT UNSIGNED NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `uploaded_by` INT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`), KEY `idx_dt_scope` (`study_program_id`,`activity_type_id`,`document_type`,`is_active`)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
        $this->db->query(<<<'SQL'
CREATE TABLE `document_template_fields` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` INT UNSIGNED NOT NULL,
  `field_key` VARCHAR(100) NOT NULL,
  `label` VARCHAR(150) NOT NULL,
  `source_type` VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
  `source_key` VARCHAR(150) NULL,
  `input_type` VARCHAR(20) NOT NULL DEFAULT 'TEXT',
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_template_field` (`template_id`,`field_key`), KEY `idx_template_field_template` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function down(): void
    {
        $this->forge->dropTable('document_template_fields', true);
        $this->forge->dropTable('document_templates', true);
    }
}
