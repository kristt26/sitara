<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHonorPaymentsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `honor_payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `honor_payment_batch_id` INT UNSIGNED NOT NULL,
  `lecturer_id` INT UNSIGNED NOT NULL,
  `payment_method_id` INT UNSIGNED NULL,
  `bank_name_snapshot` VARCHAR(100) NULL,
  `bank_account_number_snapshot` VARCHAR(100) NULL,
  `bank_account_name_snapshot` VARCHAR(200) NULL,
  `gross_total` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `tax_total` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `net_total` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `status` VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  `paid_at` DATETIME NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_honor_payment_lecturer` (`honor_payment_batch_id`,`lecturer_id`),
  KEY `idx_hp_lecturer` (`lecturer_id`),
  KEY `idx_hp_method` (`payment_method_id`),
  KEY `idx_hp_status` (`status`),
  CONSTRAINT `fk_hp_batch` FOREIGN KEY (`honor_payment_batch_id`) REFERENCES `honor_payment_batches` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_hp_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_hp_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('honor_payments', true);
    }
}
