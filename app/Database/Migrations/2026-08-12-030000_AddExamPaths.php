<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExamPaths extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `exam_paths` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exam_paths_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $now = date('Y-m-d H:i:s');
        $this->db->table('exam_paths')->insertBatch([
            ['code'=>'UMUM','name'=>'Umum','is_active'=>1,'sort_order'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'JURNAL','name'=>'Jurnal','is_active'=>1,'sort_order'=>2,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'HAKI','name'=>'HaKI','is_active'=>1,'sort_order'=>3,'created_at'=>$now,'updated_at'=>$now],
        ]);
        $generalId = (int) $this->db->table('exam_paths')->where('code','UMUM')->get()->getRow('id');

        $this->db->query('ALTER TABLE `fee_settings` ADD COLUMN `exam_path_id` INT UNSIGNED NULL AFTER `activity_type_id`');
        $this->db->table('fee_settings')->update(['exam_path_id'=>$generalId]);
        $this->db->query('ALTER TABLE `fee_settings` MODIFY `exam_path_id` INT UNSIGNED NOT NULL, DROP INDEX `uq_fee_setting_version`, ADD UNIQUE KEY `uq_fee_setting_version` (`academic_period_id`,`study_program_id`,`activity_type_id`,`exam_path_id`,`version_no`), ADD KEY `idx_fs_exam_path` (`exam_path_id`), ADD CONSTRAINT `fk_fs_exam_path` FOREIGN KEY (`exam_path_id`) REFERENCES `exam_paths` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT');

        $this->db->query('ALTER TABLE `academic_activities` ADD COLUMN `exam_path_id` INT UNSIGNED NULL AFTER `activity_type_id`');
        $this->db->table('academic_activities')->update(['exam_path_id'=>$generalId]);
        $this->db->query('ALTER TABLE `academic_activities` MODIFY `exam_path_id` INT UNSIGNED NOT NULL, ADD KEY `idx_aa_exam_path` (`exam_path_id`), ADD CONSTRAINT `fk_aa_exam_path` FOREIGN KEY (`exam_path_id`) REFERENCES `exam_paths` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT');

        $this->db->query('ALTER TABLE `student_bills` ADD COLUMN `exam_path_id` INT UNSIGNED NULL AFTER `activity_type_id`');
        $this->db->query('UPDATE `student_bills` sb JOIN `academic_activities` aa ON aa.id=sb.activity_id SET sb.exam_path_id=aa.exam_path_id');
        $this->db->table('student_bills')->where('exam_path_id',null)->update(['exam_path_id'=>$generalId]);
        $this->db->query('ALTER TABLE `student_bills` MODIFY `exam_path_id` INT UNSIGNED NOT NULL, ADD KEY `idx_sb_exam_path` (`exam_path_id`), ADD CONSTRAINT `fk_sb_exam_path` FOREIGN KEY (`exam_path_id`) REFERENCES `exam_paths` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `student_bills` DROP FOREIGN KEY `fk_sb_exam_path`, DROP INDEX `idx_sb_exam_path`, DROP COLUMN `exam_path_id`');
        $this->db->query('ALTER TABLE `academic_activities` DROP FOREIGN KEY `fk_aa_exam_path`, DROP INDEX `idx_aa_exam_path`, DROP COLUMN `exam_path_id`');
        $this->db->query('ALTER TABLE `fee_settings` DROP FOREIGN KEY `fk_fs_exam_path`, DROP INDEX `idx_fs_exam_path`, DROP INDEX `uq_fee_setting_version`, ADD UNIQUE KEY `uq_fee_setting_version` (`academic_period_id`,`study_program_id`,`activity_type_id`,`version_no`), DROP COLUMN `exam_path_id`');
        $this->forge->dropTable('exam_paths', true);
    }
}
