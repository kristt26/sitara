<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLecturersTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `lecturers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nidn` VARCHAR(30) NULL,
  `nip` VARCHAR(50) NULL,
  `full_name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(200) NULL,
  `phone` VARCHAR(30) NULL,
  `bank_name` VARCHAR(100) NULL,
  `bank_account_number` VARCHAR(100) NULL,
  `bank_account_name` VARCHAR(200) NULL,
  `tax_id` VARCHAR(50) NULL,
  `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lecturers_nidn` (`nidn`),
  UNIQUE KEY `uq_lecturers_nip` (`nip`),
  KEY `idx_lecturers_name` (`full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('lecturers', true);
    }
}
