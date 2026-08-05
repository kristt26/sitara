<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHonorPaymentBatchesTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `honor_payment_batches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_no` VARCHAR(50) NOT NULL,
  `academic_period_id` INT UNSIGNED NOT NULL,
  `payment_date` DATE NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  `reference_no` VARCHAR(100) NULL,
  `proof_file_path` TEXT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_honor_batches_no` (`batch_no`),
  KEY `idx_hpb_period` (`academic_period_id`),
  KEY `idx_hpb_status` (`status`),
  CONSTRAINT `fk_hpb_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('honor_payment_batches', true);
    }
}
