<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the operational roles used to separate academic program work from finance.
 *
 * Academic years and periods intentionally remain global tables. A Prodi user is
 * scoped through the pivot table below and does not own a separate academic
 * calendar.
 */
class AddOperationalRolesAndProgramScopes extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('ADMIN','PRODI','KEUANGAN','MAHASISWA') NOT NULL DEFAULT 'MAHASISWA'");

        $this->db->query(<<<'SQL'
CREATE TABLE `user_study_programs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `study_program_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_study_program` (`user_id`,`study_program_id`),
  KEY `idx_usp_program` (`study_program_id`),
  CONSTRAINT `fk_usp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_usp_program` FOREIGN KEY (`study_program_id`) REFERENCES `study_programs` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

    }

    public function down(): void
    {
        $this->forge->dropTable('user_study_programs', true);
        $this->db->query("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('ADMIN','MAHASISWA') NOT NULL DEFAULT 'MAHASISWA'");
    }
}
