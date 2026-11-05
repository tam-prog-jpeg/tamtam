-- =====================================================================
-- ZPPSU INTELLECTUAL PROPERTY OFFICE (IPO) MANAGEMENT SYSTEM
-- FULL DATABASE SCHEMA & INITIAL SEED DATA
-- Engine: MySQL 8 / MariaDB (InnoDB, utf8mb4)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `zppsu_ipo`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `zppsu_ipo`;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. ROLES
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `role_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_name`   VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES
    (1, 'Administrator', 'Full system access, audit logs, and configuration'),
    (2, 'Staff',         'Verifies applicant documents and formality screening'),
    (3, 'Evaluator',     'Conducts substantive technical and novelty evaluation'),
    (4, 'Manager',       'Director-level review and final executive approval'),
    (5, 'Applicant',     'Submits statutory disclosures and tracks IP filings');

-- ---------------------------------------------------------------------
-- 2. COLLEGES & INSTITUTIONAL OFFICES (15 Official ZPPSU Entities)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `colleges`;
CREATE TABLE `colleges` (
    `college_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `college_name` VARCHAR(150) NOT NULL,
    `college_code` VARCHAR(20) NOT NULL UNIQUE,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `colleges` (`college_id`, `college_name`, `college_code`) VALUES
    (1,  'College of Engineering and Technology (CET)', 'CET'),
    (2,  'College of Teacher Education (CTE) / College of Technical Vocational Teacher Education', 'CTE'),
    (3,  'School of Business Administration', 'SBA'),
    (4,  'College of Arts, Humanities, and Social Sciences (CAHSS)', 'CAHSS'),
    (5,  'College of Information and Computing Sciences (CICS)', 'CICS'),
    (6,  'College of Physical Education and Sports (CPES)', 'CPES'),
    (7,  'Office of the University President', 'OP'),
    (8,  'Office of the University Registrar', 'OUR'),
    (9,  'Office of Student Affairs and Services (OSAS)', 'OSAS'),
    (10, 'Admission Office', 'ADM'),
    (11, 'Public Information Office (PIO)', 'PIO'),
    (12, 'Learning Commons Center / ZPPSU Library', 'LCC'),
    (13, 'Alumni Affairs Office', 'AAO'),
    (14, 'Professional Continuing Education (PCE)', 'PCE'),
    (15, 'Innovation Hub / Research and Extension Offices', 'IHUB');

-- ---------------------------------------------------------------------
-- 3. DEPARTMENTS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
    `department_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `college_id`      INT UNSIGNED NOT NULL,
    `department_name` VARCHAR(150) NOT NULL,
    `department_code` VARCHAR(20) NOT NULL,
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_dept_college` FOREIGN KEY (`college_id`)
        REFERENCES `colleges` (`college_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `departments` (`department_id`, `college_id`, `department_name`, `department_code`) VALUES
    (1, 1, 'Department of Computer Studies', 'DCS'),
    (2, 1, 'Department of Civil Engineering', 'DCE'),
    (3, 2, 'Department of Technical Vocational Education', 'DTVE');

-- ---------------------------------------------------------------------
-- 4. USERS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `user_id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id`        INT UNSIGNED NOT NULL,
    `username`       VARCHAR(50)  NOT NULL UNIQUE,
    `email`          VARCHAR(150) NOT NULL UNIQUE,
    `password_hash`  VARCHAR(255) NOT NULL,
    `first_name`     VARCHAR(100) NOT NULL,
    `last_name`      VARCHAR(100) NOT NULL,
    `contact_number` VARCHAR(30)  DEFAULT NULL,
    `recovery_email` VARCHAR(150) DEFAULT NULL,
    `status`         ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    `avatar`         VARCHAR(255) DEFAULT NULL,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`)
        REFERENCES `roles` (`role_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. APPLICANTS (Extended Profile)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `applicants`;
CREATE TABLE `applicants` (
    `applicant_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT UNSIGNED NOT NULL UNIQUE,
    `applicant_type`  ENUM('Student','Faculty','Staff','External') NOT NULL DEFAULT 'Faculty',
    `college_id`      INT UNSIGNED DEFAULT NULL,
    `department_id`   INT UNSIGNED DEFAULT NULL,
    `address`         VARCHAR(255) DEFAULT NULL,
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_applicants_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_applicants_college` FOREIGN KEY (`college_id`)
        REFERENCES `colleges` (`college_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_applicants_department` FOREIGN KEY (`department_id`)
        REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. APPLICATION TYPES (Statutory Categories under RA 8293)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `application_types`;
CREATE TABLE `application_types` (
    `type_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `type_name`    VARCHAR(100) NOT NULL UNIQUE,
    `description`  TEXT DEFAULT NULL,
    `requirements` TEXT DEFAULT NULL,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `application_types` (`type_id`, `type_name`, `description`, `requirements`) VALUES
    (1, 'Patent', 'Protection for new, inventive, and industrially applicable inventions (20-year term)', 'Technical description, claims, abstract, formal drawings/figures'),
    (2, 'Utility Model', 'Expedited practical protection for mechanical and tool innovations without inventive step (7-year term)', 'Technical description, assembly drawings, prototype photos'),
    (3, 'Industrial Design', 'Protection for ornamental, aesthetic 2D/3D shapes, lines, or contours under Locarno Classification (5 to 15 years)', '7 standard orthographic perspective views, statement of novelty'),
    (4, 'Trademark', 'Exclusive commercial brand protection for distinctive marks, names, or logos under Nice Classification (10-year term)', 'Clear high-res mark specimen, list of goods/services, DAU declaration'),
    (5, 'Copyright', 'Statutory recording of original literary, artistic, software, and scientific works under Sec. 172 RA 8293 (Life + 50 years)', 'Complete deposit copy/manuscript, co-authorship contribution agreement');

-- ---------------------------------------------------------------------
-- 7. APPLICATIONS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `applications`;
CREATE TABLE `applications` (
    `application_id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tracking_code`         VARCHAR(30) NOT NULL UNIQUE,
    `applicant_id`          INT UNSIGNED NOT NULL,
    `type_id`               INT UNSIGNED NOT NULL,
    `title`                 VARCHAR(255) NOT NULL,
    `description`           TEXT DEFAULT NULL,
    `college_id`            INT UNSIGNED DEFAULT NULL,
    `department_id`         INT UNSIGNED DEFAULT NULL,
    `status`                ENUM('pending','under_review','evaluated','returned','approved','rejected') NOT NULL DEFAULT 'pending',
    `assigned_staff_id`     INT UNSIGNED DEFAULT NULL,
    `assigned_evaluator_id` INT UNSIGNED DEFAULT NULL,
    `submitted_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_app_applicant` FOREIGN KEY (`applicant_id`)
        REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_app_type` FOREIGN KEY (`type_id`)
        REFERENCES `application_types` (`type_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_app_college` FOREIGN KEY (`college_id`)
        REFERENCES `colleges` (`college_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_app_department` FOREIGN KEY (`department_id`)
        REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_app_staff` FOREIGN KEY (`assigned_staff_id`)
        REFERENCES `users` (`user_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_app_evaluator` FOREIGN KEY (`assigned_evaluator_id`)
        REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. APPLICATION STATUS HISTORY (Audit Trail)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `application_status_history`;
CREATE TABLE `application_status_history` (
    `history_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT UNSIGNED NOT NULL,
    `status`         VARCHAR(30) NOT NULL,
    `remarks`        TEXT DEFAULT NULL,
    `changed_by`     INT UNSIGNED DEFAULT NULL,
    `changed_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_hist_application` FOREIGN KEY (`application_id`)
        REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_hist_user` FOREIGN KEY (`changed_by`)
        REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. DOCUMENTS (Supporting Disclosures & Uploaded Files)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
    `document_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT UNSIGNED NOT NULL,
    `uploaded_by`    INT UNSIGNED NOT NULL,
    `file_name`      VARCHAR(255) NOT NULL,
    `stored_name`    VARCHAR(255) NOT NULL,
    `file_path`      VARCHAR(500) NOT NULL,
    `file_type`      VARCHAR(20) NOT NULL,
    `file_size`      INT UNSIGNED DEFAULT NULL,
    `version`        INT UNSIGNED NOT NULL DEFAULT 1,
    `uploaded_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_doc_application` FOREIGN KEY (`application_id`)
        REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_doc_user` FOREIGN KEY (`uploaded_by`)
        REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. EVALUATIONS (Evaluator Technical Decisions)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `evaluations`;
CREATE TABLE `evaluations` (
    `evaluation_id`  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT UNSIGNED NOT NULL,
    `evaluator_id`   INT UNSIGNED NOT NULL,
    `decision`       ENUM('approved','rejected','returned') NOT NULL,
    `comments`       TEXT DEFAULT NULL,
    `evaluated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_eval_application` FOREIGN KEY (`application_id`)
        REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_eval_evaluator` FOREIGN KEY (`evaluator_id`)
        REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. NOTIFICATIONS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `notification_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT UNSIGNED NOT NULL,
    `title`           VARCHAR(150) NOT NULL,
    `message`         TEXT NOT NULL,
    `link`            VARCHAR(255) DEFAULT NULL,
    `is_read`         TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. ANNOUNCEMENTS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
    `announcement_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title`           VARCHAR(200) NOT NULL,
    `content`         TEXT NOT NULL,
    `posted_by`       INT UNSIGNED NOT NULL,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `posted_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_announce_user` FOREIGN KEY (`posted_by`)
        REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. AUDIT LOGS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `log_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED DEFAULT NULL,
    `action`     VARCHAR(150) NOT NULL,
    `details`    TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. SYSTEM SETTINGS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
    `setting_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key`   VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
    ('site_name', 'ZPPSU Intellectual Property Office'),
    ('site_email', 'ipo@zppsu.edu.ph'),
    ('max_upload_mb', '10'),
    ('allowed_file_types', 'pdf,docx,jpg,jpeg,png'),
    ('maintenance_mode', '0');

-- ---------------------------------------------------------------------
-- 15. DEFAULT STARTER ACCOUNTS
-- Password for all starter accounts below: password
-- Bcrypt Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ---------------------------------------------------------------------
INSERT INTO `users` (`user_id`, `role_id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `contact_number`, `status`) VALUES
    (1, 1, 'admin',     'admin@zppsu.edu.ph',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', '09123456789', 'active'),
    (2, 2, 'staff',     'staff@zppsu.edu.ph',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Office', 'Staff',         '09234567890', 'active'),
    (3, 3, 'evaluator', 'evaluator@zppsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tech',   'Evaluator',     '09345678901', 'active'),
    (4, 4, 'manager',   'manager@zppsu.edu.ph',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'IP',     'Director',      '09456789012', 'active'),
    (5, 5, 'applicant', 'applicant@zppsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sample', 'Inventor',      '09567890123', 'active');

INSERT INTO `applicants` (`applicant_id`, `user_id`, `applicant_type`, `college_id`, `department_id`, `address`) VALUES
    (1, 5, 'Faculty', 5, 1, 'ZPPSU Main Campus, R.T. Lim Boulevard, Zamboanga City');

SET FOREIGN_KEY_CHECKS = 1;

