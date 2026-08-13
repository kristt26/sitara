<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LinkUsersToOperationalData extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
ALTER TABLE `students`
  ADD COLUMN `user_id` INT UNSIGNED NULL AFTER `id`,
  ADD UNIQUE KEY `uq_students_user` (`user_id`),
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL;
SQL
        );

        $this->db->query(<<<'SQL'
ALTER TABLE `student_payments`
  ADD KEY `idx_sp_verified_by` (`verified_by`),
  ADD CONSTRAINT `fk_sp_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL;
SQL
        );

        $this->db->query(<<<'SQL'
ALTER TABLE `attachments`
  ADD CONSTRAINT `fk_attachments_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL;
SQL
        );

        $this->db->query(<<<'SQL'
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL;
SQL
        );
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `audit_logs` DROP FOREIGN KEY `fk_audit_user`');
        $this->db->query('ALTER TABLE `attachments` DROP FOREIGN KEY `fk_attachments_uploader`');
        $this->db->query('ALTER TABLE `student_payments` DROP FOREIGN KEY `fk_sp_verifier`, DROP INDEX `idx_sp_verified_by`');
        $this->db->query('ALTER TABLE `students` DROP FOREIGN KEY `fk_students_user`, DROP INDEX `uq_students_user`, DROP COLUMN `user_id`');
    }
}
