<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudentPaymentAllocationsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `student_payment_allocations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_payment_id` INT UNSIGNED NOT NULL,
  `student_bill_id` INT UNSIGNED NOT NULL,
  `allocated_amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_bill_allocation` (`student_payment_id`,`student_bill_id`),
  KEY `idx_spa_bill` (`student_bill_id`),
  CONSTRAINT `fk_spa_payment` FOREIGN KEY (`student_payment_id`) REFERENCES `student_payments` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_spa_bill` FOREIGN KEY (`student_bill_id`) REFERENCES `student_bills` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('student_payment_allocations', true);
    }
}
