<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHonorPaymentItemsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `honor_payment_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `honor_payment_id` INT UNSIGNED NOT NULL,
  `honor_entitlement_id` INT UNSIGNED NOT NULL,
  `gross_amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `net_amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_honor_payment_item` (`honor_payment_id`,`honor_entitlement_id`),
  KEY `idx_hpi_entitlement` (`honor_entitlement_id`),
  CONSTRAINT `fk_hpi_payment` FOREIGN KEY (`honor_payment_id`) REFERENCES `honor_payments` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_hpi_entitlement` FOREIGN KEY (`honor_entitlement_id`) REFERENCES `honor_entitlements` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('honor_payment_items', true);
    }
}
