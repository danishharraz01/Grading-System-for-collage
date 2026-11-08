-- ALIAS Kolej Grading System - database setup
-- Import this file in phpMyAdmin (Import tab) or run:  mysql -u root < database.sql
-- WARNING: re-importing drops and recreates the tables (all existing data is lost).

CREATE DATABASE IF NOT EXISTS alias_grading
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE alias_grading;

DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;

-- ---------------------------------------------------------------------------
-- Staff accounts. Passwords are stored as password_hash() (bcrypt) hashes.
-- ---------------------------------------------------------------------------
CREATE TABLE users (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    username    VARCHAR(50)   NOT NULL,
    full_name   VARCHAR(100)  NOT NULL,
    password    VARCHAR(255)  NOT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Student results. total = test1 + test2 + final_exam; grade derived from total:
--   0-29 F, 30-39 D, 40-59 C, 60-79 B, 80-100 A
-- ---------------------------------------------------------------------------
CREATE TABLE students (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    student_name  VARCHAR(100)  NOT NULL,
    matric_no     VARCHAR(20)   NOT NULL,
    test1         DECIMAL(5,2)  NOT NULL,
    test2         DECIMAL(5,2)  NOT NULL,
    final_exam    DECIMAL(5,2)  NOT NULL,
    total         DECIMAL(5,2)  NOT NULL,
    grade         CHAR(1)       NOT NULL,
    created_by    INT UNSIGNED  NOT NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_matric_no (matric_no),
    KEY idx_students_grade (grade),
    KEY idx_students_created_by (created_by),
    CONSTRAINT fk_students_created_by FOREIGN KEY (created_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_test1      CHECK (test1 BETWEEN 0 AND 20),
    CONSTRAINT chk_test2      CHECK (test2 BETWEEN 0 AND 30),
    CONSTRAINT chk_final_exam CHECK (final_exam BETWEEN 0 AND 50),
    CONSTRAINT chk_total      CHECK (total BETWEEN 0 AND 100),
    CONSTRAINT chk_grade      CHECK (grade IN ('A', 'B', 'C', 'D', 'F'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Default administrator account
--   username: admin
--   password: admin123   (change it after your first login: Change Password page)
-- ---------------------------------------------------------------------------
INSERT INTO users (id, username, full_name, password) VALUES
(1, 'admin', 'Administrator', '$2y$10$yyIvzRnAsNCGRuYdFVxUcOuolCI2zE3P.851ZQ4u4CCX5/HSrVLI6');

-- ---------------------------------------------------------------------------
-- Sample results (optional - delete these rows from the app if not needed)
-- ---------------------------------------------------------------------------
INSERT INTO students (student_name, matric_no, test1, test2, final_exam, total, grade, created_by) VALUES
('Ahmad Faiz bin Rahman',     'AK2024001', 18.00, 26.00, 44.00, 88.00, 'A', 1),
('Nur Aisyah binti Kamal',    'AK2024002', 15.00, 22.00, 35.00, 72.00, 'B', 1),
('Lim Wei Jie',               'AK2024003', 12.00, 18.00, 25.00, 55.00, 'C', 1),
('Priya a/p Suresh',          'AK2024004', 10.00, 12.00, 14.00, 36.00, 'D', 1),
('Muhammad Hafiz bin Ismail', 'AK2024005',  6.00,  9.00, 10.00, 25.00, 'F', 1),
('Tan Mei Ling',              'AK2024006', 17.00, 25.00, 38.00, 80.00, 'A', 1);
