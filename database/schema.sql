-- =====================================================================
--  Conference Student Registration & Information Management System
--  Database schema (MySQL 5.7+ / MariaDB 10.3+)
--
--  HOW TO USE
--  * Local (MySQL Workbench): create a schema first, e.g.
--        CREATE DATABASE bstm_conference CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--        USE bstm_conference;
--    then run this whole file.
--  * InfinityFree: create the database in the control panel, open
--    phpMyAdmin, select the database and Import this file.
--
--  Default admin login (CHANGE IT AFTER FIRST LOGIN):
--        username: admin      password: Admin@123
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS attendance_records;
DROP TABLE IF EXISTS conference_registrations;
DROP TABLE IF EXISTS qr_codes;
DROP TABLE IF EXISTS admin_conferences;
DROP TABLE IF EXISTS conferences;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS schools;
DROP TABLE IF EXISTS participant_types;
DROP TABLE IF EXISTS academic_years;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS admins;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Administrators
-- ---------------------------------------------------------------------
CREATE TABLE admins (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username             VARCHAR(50)  NOT NULL UNIQUE,
    full_name            VARCHAR(120) NOT NULL,
    email                VARCHAR(150) NULL,
    password_hash        VARCHAR(255) NOT NULL,
    role                 ENUM('super_admin','conference_admin') NOT NULL DEFAULT 'conference_admin',
    is_active            TINYINT(1)   NOT NULL DEFAULT 1,
    must_change_password TINYINT(1)   NOT NULL DEFAULT 0,
    failed_attempts      INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until         DATETIME     NULL,
    last_login_at        DATETIME     NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Lookup tables
-- ---------------------------------------------------------------------
CREATE TABLE academic_years (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(20) NOT NULL UNIQUE,          -- e.g. 2026-2027
    is_current TINYINT(1)  NOT NULL DEFAULT 0,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schools (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(190) NOT NULL UNIQUE,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE courses (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(30)  NOT NULL UNIQUE,
    name       VARCHAR(190) NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE participant_types (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code      VARCHAR(20) NOT NULL UNIQUE,           -- student / faculty / guest
    name      VARCHAR(50) NOT NULL,
    is_active TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Conferences
-- ---------------------------------------------------------------------
CREATE TABLE conferences (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_year_id       INT UNSIGNED NULL,
    title                  VARCHAR(190) NOT NULL,
    description            TEXT NULL,
    venue                  VARCHAR(255) NOT NULL,
    start_datetime         DATETIME NOT NULL,
    end_datetime           DATETIME NULL,
    registration_open      DATETIME NULL,
    registration_close     DATETIME NULL,
    participant_limit      INT UNSIGNED NULL,          -- NULL = unlimited
    allow_student          TINYINT(1) NOT NULL DEFAULT 1,
    allow_faculty          TINYINT(1) NOT NULL DEFAULT 1,
    allow_guest            TINYINT(1) NOT NULL DEFAULT 1,
    attendance_categories  VARCHAR(500) NOT NULL DEFAULT 'Whole Day,Morning Session Only,Afternoon Session Only',
    auto_confirm           TINYINT(1) NOT NULL DEFAULT 0,
    registration_enabled   TINYINT(1) NOT NULL DEFAULT 1,
    is_archived            TINYINT(1) NOT NULL DEFAULT 0,
    created_by             INT UNSIGNED NULL,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_conf_ay      FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL,
    CONSTRAINT fk_conf_creator FOREIGN KEY (created_by)       REFERENCES admins(id)         ON DELETE SET NULL,
    INDEX idx_conf_archived (is_archived),
    INDEX idx_conf_start (start_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conference admins are only allowed to manage the conferences assigned here.
CREATE TABLE admin_conferences (
    admin_id      INT UNSIGNED NOT NULL,
    conference_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (admin_id, conference_id),
    CONSTRAINT fk_ac_admin FOREIGN KEY (admin_id)      REFERENCES admins(id)      ON DELETE CASCADE,
    CONSTRAINT fk_ac_conf  FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Registration QR codes (contain only a random token, never personal data)
-- ---------------------------------------------------------------------
CREATE TABLE qr_codes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conference_id   INT UNSIGNED NOT NULL,
    token           CHAR(32)     NOT NULL UNIQUE,
    label           VARCHAR(100) NOT NULL DEFAULT 'Main Registration QR',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    scan_count      INT UNSIGNED NOT NULL DEFAULT 0,
    last_scanned_at DATETIME     NULL,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_qr_conf    FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE CASCADE,
    CONSTRAINT fk_qr_creator FOREIGN KEY (created_by)    REFERENCES admins(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Participants (one row per person, re-used across conferences)
-- ---------------------------------------------------------------------
CREATE TABLE students (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_number      VARCHAR(30)  NULL UNIQUE,           -- Student / Employee ID (NULL allowed for guests)
    last_name      VARCHAR(80)  NOT NULL,
    first_name     VARCHAR(80)  NOT NULL,
    middle_name    VARCHAR(80)  NULL,
    contact_number VARCHAR(20)  NOT NULL,
    address        VARCHAR(255) NOT NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_students_name (last_name, first_name),
    INDEX idx_students_contact (contact_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Registrations (one row per participant per conference)
-- Academic details are stored per registration because they change
-- between school years.
-- ---------------------------------------------------------------------
CREATE TABLE conference_registrations (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no          VARCHAR(20)  NOT NULL UNIQUE,
    access_key            CHAR(32)     NOT NULL,          -- lets the participant reopen their own confirmation
    attendance_token      CHAR(32)     NOT NULL UNIQUE,   -- content of the participant's attendance QR
    conference_id         INT UNSIGNED NOT NULL,
    student_id            INT UNSIGNED NOT NULL,
    qr_code_id            INT UNSIGNED NULL,
    participant_type_id   INT UNSIGNED NOT NULL,
    school_id             INT UNSIGNED NULL,
    school_other          VARCHAR(190) NULL,
    course_id             INT UNSIGNED NULL,
    course_other          VARCHAR(190) NULL,
    year_level            VARCHAR(20)  NULL,
    section               VARCHAR(50)  NULL,
    attendance_category   VARCHAR(100) NULL,
    special_requirements  VARCHAR(500) NULL,
    status                ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
    is_archived           TINYINT(1)   NOT NULL DEFAULT 0,
    registered_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status_updated_at     DATETIME     NULL,
    status_updated_by     INT UNSIGNED NULL,
    ip_address            VARCHAR(45)  NULL,
    updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_conf_student (conference_id, student_id),
    CONSTRAINT fk_reg_conf    FOREIGN KEY (conference_id)       REFERENCES conferences(id)       ON DELETE CASCADE,
    CONSTRAINT fk_reg_student FOREIGN KEY (student_id)          REFERENCES students(id)          ON DELETE CASCADE,
    CONSTRAINT fk_reg_qr      FOREIGN KEY (qr_code_id)          REFERENCES qr_codes(id)          ON DELETE SET NULL,
    CONSTRAINT fk_reg_ptype   FOREIGN KEY (participant_type_id) REFERENCES participant_types(id),
    CONSTRAINT fk_reg_school  FOREIGN KEY (school_id)           REFERENCES schools(id)           ON DELETE SET NULL,
    CONSTRAINT fk_reg_course  FOREIGN KEY (course_id)           REFERENCES courses(id)           ON DELETE SET NULL,
    CONSTRAINT fk_reg_admin   FOREIGN KEY (status_updated_by)   REFERENCES admins(id)            ON DELETE SET NULL,
    INDEX idx_reg_status (status),
    INDEX idx_reg_date (registered_at),
    INDEX idx_reg_archived (is_archived)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Attendance (one check-in per registration)
-- ---------------------------------------------------------------------
CREATE TABLE attendance_records (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id INT UNSIGNED NOT NULL UNIQUE,
    conference_id   INT UNSIGNED NOT NULL,
    checked_in_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checked_in_by   INT UNSIGNED NULL,
    method          ENUM('qr','manual') NOT NULL DEFAULT 'manual',
    CONSTRAINT fk_att_reg   FOREIGN KEY (registration_id) REFERENCES conference_registrations(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_conf  FOREIGN KEY (conference_id)   REFERENCES conferences(id)              ON DELETE CASCADE,
    CONSTRAINT fk_att_admin FOREIGN KEY (checked_in_by)   REFERENCES admins(id)                   ON DELETE SET NULL,
    INDEX idx_att_conf (conference_id, checked_in_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Activity logs
-- ---------------------------------------------------------------------
CREATE TABLE activity_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT UNSIGNED NULL,
    action      VARCHAR(60)  NOT NULL,
    description VARCHAR(500) NOT NULL,
    ip_address  VARCHAR(45)  NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- System settings (key/value)
-- ---------------------------------------------------------------------
CREATE TABLE settings (
    setting_key   VARCHAR(60) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Default data
-- =====================================================================

-- Default Super Admin  (username: admin / password: Admin@123)
INSERT INTO admins (username, full_name, email, password_hash, role, must_change_password) VALUES
('admin', 'System Administrator', NULL, '$2y$10$4.ewLpxhN0HetaRhGEBHCeQrERleml6rN5491ZpP.SGSlRLqQkKEa', 'super_admin', 1);

INSERT INTO participant_types (code, name, is_active) VALUES
('student', 'Student', 1),
('faculty', 'Faculty', 1),
('guest',   'Guest',   1);

INSERT INTO academic_years (name, is_current) VALUES
('2025-2026', 0),
('2026-2027', 1);

INSERT INTO settings (setting_key, setting_value) VALUES
('system_name',        'Conference Registration System'),
('institution_name',   'Your School / University Name'),
('welcome_message',    'Welcome! Please complete the form below to register for this conference. It only takes about 2 minutes.'),
('public_base_url',    ''),
('student_id_pattern', '^[A-Za-z0-9-]{4,20}$'),
('student_id_hint',    '4 to 20 characters: letters, numbers and dashes only (e.g. 2023-00123)'),
('logo_path',          '');
