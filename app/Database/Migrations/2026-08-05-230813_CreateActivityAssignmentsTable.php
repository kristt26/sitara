<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateActivityAssignmentsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `activity_assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_id` INT UNSIGNED NOT NULL,
  `lecturer_id` INT UNSIGNED NOT NULL,
  `role_type` VARCHAR(20) NOT NULL,
  `position_no` TINYINT UNSIGNED NOT NULL,
  `assigned_date` DATE NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'AKTIF',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assignment_position` (`activity_id`,`role_type`,`position_no`),
  UNIQUE KEY `uq_assignment_lecturer_role` (`activity_id`,`lecturer_id`,`role_type`),
  KEY `idx_assignments_lecturer` (`lecturer_id`),
  KEY `idx_assignments_status` (`status`),
  CONSTRAINT `fk_asg_activity` FOREIGN KEY (`activity_id`) REFERENCES `academic_activities` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_asg_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('activity_assignments', true);
    }
}
