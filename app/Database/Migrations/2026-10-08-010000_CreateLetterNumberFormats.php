<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLetterNumberFormats extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE `letter_number_formats` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `study_program_id` INT UNSIGNED NOT NULL,
            `activity_type_id` INT UNSIGNED NOT NULL,
            `format_template` VARCHAR(255) NOT NULL DEFAULT '{nomor}/{kode_surat}/{kode_prodi}/{bulan_romawi}/{tahun}',
            `letter_code` VARCHAR(50) NOT NULL DEFAULT 'BA',
            `next_number` INT UNSIGNED NOT NULL DEFAULT 1,
            `padding` TINYINT UNSIGNED NOT NULL DEFAULT 3,
            `reset_scope` VARCHAR(20) NOT NULL DEFAULT 'TAHUN',
            `reset_key` VARCHAR(30) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NULL, `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uq_letter_format_scope` (`study_program_id`,`activity_type_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$this->db->fieldExists('letter_number', 'academic_activities')) {
            $this->db->query("ALTER TABLE `academic_activities` ADD `letter_number` VARCHAR(255) NULL AFTER `notes`");
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('letter_number', 'academic_activities')) $this->db->query('ALTER TABLE `academic_activities` DROP COLUMN `letter_number`');
        $this->forge->dropTable('letter_number_formats', true);
    }
}
