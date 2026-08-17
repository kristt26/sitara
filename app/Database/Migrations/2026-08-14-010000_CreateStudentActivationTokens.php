<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudentActivationTokens extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `student_activation_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_activation_hash` (`token_hash`),
  KEY `idx_student_activation_user` (`user_id`,`used_at`,`revoked_at`,`expires_at`),
  KEY `idx_student_activation_creator` (`created_by`),
  CONSTRAINT `fk_student_activation_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_student_activation_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('student_activation_tokens', true);
    }
}
