<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAttachmentsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `attachments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` TEXT NOT NULL,
  `mime_type` VARCHAR(100) NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `uploaded_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attachments_entity` (`entity_type`,`entity_id`),
  KEY `idx_attachments_uploader` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('attachments', true);
    }
}
