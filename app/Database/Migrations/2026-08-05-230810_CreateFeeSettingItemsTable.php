<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFeeSettingItemsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `fee_setting_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fee_setting_id` INT UNSIGNED NOT NULL,
  `item_code` VARCHAR(40) NOT NULL,
  `item_name` VARCHAR(150) NOT NULL,
  `amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `is_required` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee_item_code` (`fee_setting_id`,`item_code`),
  KEY `idx_fee_items_setting` (`fee_setting_id`),
  CONSTRAINT `fk_fsi_setting` FOREIGN KEY (`fee_setting_id`) REFERENCES `fee_settings` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('fee_setting_items', true);
    }
}
