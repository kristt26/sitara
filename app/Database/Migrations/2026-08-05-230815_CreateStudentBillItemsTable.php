<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudentBillItemsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `student_bill_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_bill_id` INT UNSIGNED NOT NULL,
  `fee_setting_item_id` INT UNSIGNED NULL,
  `item_code_snapshot` VARCHAR(40) NOT NULL,
  `item_name_snapshot` VARCHAR(150) NOT NULL,
  `amount_snapshot` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bill_item_code` (`student_bill_id`,`item_code_snapshot`),
  KEY `idx_sbi_fee_item` (`fee_setting_item_id`),
  CONSTRAINT `fk_sbi_bill` FOREIGN KEY (`student_bill_id`) REFERENCES `student_bills` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_sbi_fee_item` FOREIGN KEY (`fee_setting_item_id`) REFERENCES `fee_setting_items` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('student_bill_items', true);
    }
}
