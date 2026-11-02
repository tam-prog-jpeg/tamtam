-- =====================================================================
-- ZPPSU Intellectual Property Office Management System
-- Database Schema
-- Engine: MySQL 8 / MariaDB (InnoDB, utf8mb4)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS zppsu_ipo
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE zppsu_ipo;

-- ---------------------------------------------------------------------
-- ROLES
-- Fixed lookup table for the 5 system roles.
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    role_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name   VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO roles (role_id, role_name, description) VALUES
    (1, 'Administrator', 'Full system access and configuration'),
    (2, 'Staff',         'Verifies applications and manages documents'),
    (3, 'Evaluator',     'Evaluates and decides on IP applications'),
    (4, 'Manager',       'Reviews reports and gives final approval'),
    (5, 'Applicant',     'Submits and tracks IP applications');

-- ---------------------------------------------------------------------
-- COLLEGES
-- ---------------------------------------------------------------------
CREATE TABLE colleges (
    college_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    college_name VARCHAR(150) NOT NULL,
    college_code VARCHAR(20) NOT NULL UNIQUE,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- DEPARTMENTS
-- ---------------------------------------------------------------------
CREATE TABLE departments (
    department_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    college_id       INT UNSIGNED NOT NULL,
    department_name  VARCHAR(150) NOT NULL,
    department_code  VARCHAR(20) NOT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dept_college FOREIGN KEY (college_id)
        REFERENCES colleges(college_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- USERS
-- Holds login credentials for every role (admin, staff, evaluator,
-- manager, applicant). Role-specific extra fields live in `applicants`.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id        INT UNSIGNED NOT NULL,
    username       VARCHAR(50)  NOT NULL UNIQUE,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    first_name     VARCHAR(100) NOT NULL,
    last_name      VARCHAR(100) NOT NULL,
    contact_number VARCHAR(30)  DEFAULT NULL,
    recovery_email VARCHAR(150) DEFAULT NULL,
    status         ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    avatar         VARCHAR(255) DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id)
        REFERENCES roles(role_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- APPLICANTS
-- Extended profile for users whose role is "Applicant".
-- ---------------------------------------------------------------------
CREATE TABLE applicants (
    applicant_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    applicant_type  ENUM('Student','Faculty','Staff','External') NOT NULL DEFAULT 'External',
    college_id      INT UNSIGNED DEFAULT NULL,
    department_id   INT UNSIGNED DEFAULT NULL,
    address         VARCHAR(255) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_applicants_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_applicants_college FOREIGN KEY (college_id)
        REFERENCES colleges(college_id) ON DELETE SET NULL,
    CONSTRAINT fk_applicants_department FOREIGN KEY (department_id)
        REFERENCES departments(department_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- APPLICATION TYPES
-- e.g. Patent, Utility Model, Industrial Design, Trademark, Copyright
-- ---------------------------------------------------------------------
CREATE TABLE application_types (
    type_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_name    VARCHAR(100) NOT NULL UNIQUE,
    description  TEXT DEFAULT NULL,
    requirements TEXT DEFAULT NULL,
    is_active    TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO application_types (type_name, description, requirements) VALUES
    ('Patent', 'Protection for new inventions', 'Technical description, claims, drawings'),
    ('Utility Model', 'Protection for minor inventions', 'Technical description, claims, drawings'),
    ('Industrial Design', 'Protection for ornamental designs', 'Drawings/photos of the design'),
    ('Trademark', 'Protection for brand names/logos', 'Specimen of mark, list of goods/services'),
    ('Copyright', 'Protection for original works', 'Copy of the work, proof of authorship');

-- ---------------------------------------------------------------------
-- APPLICATIONS
-- Core record for every IP application submitted.
-- ---------------------------------------------------------------------
CREATE TABLE applications (
    application_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_code        VARCHAR(30) NOT NULL UNIQUE,
    applicant_id          INT UNSIGNED NOT NULL,
    type_id                INT UNSIGNED NOT NULL,
    title                   VARCHAR(255) NOT NULL,
    description             TEXT DEFAULT NULL,
    college_id              INT UNSIGNED DEFAULT NULL,
    department_id           INT UNSIGNED DEFAULT NULL,
    status                  ENUM('pending','under_review','evaluated','returned','approved','rejected')
                             NOT NULL DEFAULT 'pending',
    assigned_staff_id       INT UNSIGNED DEFAULT NULL,
    assigned_evaluator_id   INT UNSIGNED DEFAULT NULL,
    submitted_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_applicant FOREIGN KEY (applicant_id)
        REFERENCES applicants(applicant_id) ON DELETE CASCADE,
    CONSTRAINT fk_app_type FOREIGN KEY (type_id)
        REFERENCES application_types(type_id) ON DELETE RESTRICT,
    CONSTRAINT fk_app_college FOREIGN KEY (college_id)
        REFERENCES colleges(college_id) ON DELETE SET NULL,
    CONSTRAINT fk_app_department FOREIGN KEY (department_id)
        REFERENCES departments(department_id) ON DELETE SET NULL,
    CONSTRAINT fk_app_staff FOREIGN KEY (assigned_staff_id)
        REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_app_evaluator FOREIGN KEY (assigned_evaluator_id)
        REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- APPLICATION STATUS HISTORY
-- Audit trail of every status change for an application.
-- ---------------------------------------------------------------------
CREATE TABLE application_status_history (
    history_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id  INT UNSIGNED NOT NULL,
    status           VARCHAR(30) NOT NULL,
    remarks          TEXT DEFAULT NULL,
    changed_by       INT UNSIGNED DEFAULT NULL,
    changed_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_application FOREIGN KEY (application_id)
        REFERENCES applications(application_id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_user FOREIGN KEY (changed_by)
        REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- DOCUMENTS
-- Uploaded files linked to an application, with simple versioning.
-- ---------------------------------------------------------------------
CREATE TABLE documents (
    document_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id   INT UNSIGNED NOT NULL,
    uploaded_by       INT UNSIGNED NOT NULL,
    file_name          VARCHAR(255) NOT NULL,
    stored_name        VARCHAR(255) NOT NULL,
    file_path          VARCHAR(500) NOT NULL,
    file_type           VARCHAR(20) NOT NULL,
    file_size            INT UNSIGNED DEFAULT NULL,
    version               INT UNSIGNED NOT NULL DEFAULT 1,
    uploaded_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doc_application FOREIGN KEY (application_id)
        REFERENCES applications(application_id) ON DELETE CASCADE,
    CONSTRAINT fk_doc_user FOREIGN KEY (uploaded_by)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- EVALUATIONS
-- Evaluator decisions on an application.
-- ---------------------------------------------------------------------
CREATE TABLE evaluations (
    evaluation_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id    INT UNSIGNED NOT NULL,
    evaluator_id        INT UNSIGNED NOT NULL,
    decision              ENUM('approved','rejected','returned') NOT NULL,
    comments               TEXT DEFAULT NULL,
    evaluated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_eval_application FOREIGN KEY (application_id)
        REFERENCES applications(application_id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_evaluator FOREIGN KEY (evaluator_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- NOTIFICATIONS
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    notification_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED NOT NULL,
    title               VARCHAR(150) NOT NULL,
    message              TEXT NOT NULL,
    link                  VARCHAR(255) DEFAULT NULL,
    is_read                TINYINT(1) NOT NULL DEFAULT 0,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ANNOUNCEMENTS
-- ---------------------------------------------------------------------
CREATE TABLE announcements (
    announcement_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title              VARCHAR(200) NOT NULL,
    content              TEXT NOT NULL,
    posted_by             INT UNSIGNED NOT NULL,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    posted_at                TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_announce_user FOREIGN KEY (posted_by)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- AUDIT LOGS
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
    log_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED DEFAULT NULL,
    action          VARCHAR(150) NOT NULL,
    details           TEXT DEFAULT NULL,
    ip_address          VARCHAR(45) DEFAULT NULL,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- SYSTEM SETTINGS
-- Simple key/value config store used by admin/settings.php
-- ---------------------------------------------------------------------
CREATE TABLE system_settings (
    setting_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key      VARCHAR(100) NOT NULL UNIQUE,
    setting_value      TEXT DEFAULT NULL,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO system_settings (setting_key, setting_value) VALUES
    ('site_name', 'ZPPSU Intellectual Property Office'),
    ('site_email', 'ipo@zppsu.edu.ph'),
    ('max_upload_mb', '10'),
    ('allowed_file_types', 'pdf,docx,jpg,jpeg,png'),
    ('maintenance_mode', '0');

-- ---------------------------------------------------------------------
-- NOTE: The default administrator account is NOT inserted here because
-- a bcrypt hash cannot be safely hand-written into a .sql file.
-- Run database/seed_admin.php ONCE in the browser after importing this
-- schema -- it creates the admin account with a properly generated
-- password_hash(), then tells you to delete itself.
-- ---------------------------------------------------------------------

-- Sample college/department so dropdowns are not empty on first run
INSERT INTO colleges (college_name, college_code) VALUES
    ('College of Engineering and Information Technology', 'CEIT'),
    ('College of Agriculture', 'CA');

INSERT INTO departments (college_id, department_name, department_code) VALUES
    (1, 'Department of Computer Studies', 'DCS'),
    (1, 'Department of Civil Engineering', 'DCE'),
    (2, 'Department of Agronomy', 'DA');
