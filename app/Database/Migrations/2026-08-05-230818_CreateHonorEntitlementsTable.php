<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHonorEntitlementsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `honor_entitlements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` INT UNSIGNED NOT NULL,
  `honor_rate_setting_id` INT UNSIGNED NOT NULL,
  `gross_amount_snapshot` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `tax_rate_snapshot` DECIMAL(7,4) UNSIGNED NOT NULL DEFAULT 0.0000,
  `tax_amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `net_amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `status` VARCHAR(25) NOT NULL DEFAULT 'BELUM_DIAJUKAN',
  `notes` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_entitlement_assignment` (`assignment_id`),
  KEY `idx_he_rate` (`honor_rate_setting_id`),
  KEY `idx_he_status` (`status`),
  CONSTRAINT `fk_he_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `activity_assignments` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_he_rate` FOREIGN KEY (`honor_rate_setting_id`) REFERENCES `honor_rate_settings` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('honor_entitlements', true);
    }
}
