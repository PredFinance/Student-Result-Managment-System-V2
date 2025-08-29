-- =====================================================
-- LUFEM Student Results Management System
-- Complete Database Schema
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Create Database
CREATE DATABASE IF NOT EXISTS `lufem_school` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lufem_school`;

-- =====================================================
-- 1. INSTITUTIONS TABLE (Multi-tenancy Support)
-- =====================================================
CREATE TABLE `institutions` (
  `institution_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_name` varchar(255) NOT NULL,
  `institution_code` varchar(20) NOT NULL,
  `address` text,
  `phone` varchar(20),
  `email` varchar(100),
  `website` varchar(100),
  `logo_path` varchar(255),
  `primary_color` varchar(7) DEFAULT '#00A651',
  `secondary_color` varchar(7) DEFAULT '#FFFFFF',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`institution_id`),
  UNIQUE KEY `institution_code` (`institution_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. USERS TABLE (Admin, Students, etc.)
-- =====================================================
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100),
  `full_name` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','student') NOT NULL DEFAULT 'student',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `institution_id` (`institution_id`),
  FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. ACADEMIC STRUCTURE TABLES
-- =====================================================

-- Departments Table
CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `department_code` varchar(20) NOT NULL,
  `hod_name` varchar(255),
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `dept_code_institution` (`department_code`, `institution_id`),
  KEY `institution_id` (`institution_id`),
  FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Academic Levels Table
CREATE TABLE `levels` (
  `level_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `level_name` varchar(50) NOT NULL,
  `level_order` int(11) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`level_id`),
  KEY `institution_id` (`institution_id`),
  FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Academic Sessions Table
-- Sessions Table (formerly Academic Sessions)
CREATE TABLE `sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `session_name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`session_id`),
  KEY `institution_id` (`institution_id`),
  FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alternative Sessions Table (for compatibility)

-- Semesters Table
CREATE TABLE `semesters` (
  `semester_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `semester_name` varchar(50) NOT NULL,
  `semester_order` int(11) NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`semester_id`),
  KEY `institution_id` (`institution_id`),
  FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. STUDENTS TABLE
-- =====================================================
CREATE TABLE `students` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `user_id` int(11),
  `matric_number` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100),
  `email` varchar(100),
  `phone` varchar(20),
  `date_of_birth` date,
  `gender` enum('Male','Female') NOT NULL,
  `address` text,
  `department_id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL,
  `admission_session` varchar(20),
  `admission_date` date,
  `graduation_date` date,
  `status` enum('Active','Graduated','Suspended','Withdrawn') DEFAULT 'Active',
  `profile_picture` varchar(255),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `matric_number` (`matric_number`),
  KEY `institution_id` (`institution_id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `level_id` (`level_id`),
  FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`level_id`) REFERENCES `levels` (`level_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. COURSES TABLE
-- =====================================================
CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_title` varchar(255) NOT NULL,
  `credit_units` int(11) NOT NULL DEFAULT 1,
  `course_description` text,
  `prerequisites` varchar(255),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`course_id`),
  UNIQUE KEY `course_code_institution` (`course_code`, `institution_id`),
  KEY `institution_id` (`institution_id`),
  KEY `department_id` (`department_id`),
  FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. COURSE STRUCTURE TABLE (What courses are offered when)
-- =====================================================
CREATE TABLE `course_structures` (
  `structure_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `is_compulsory` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`structure_id`),
  UNIQUE KEY `course_structure_unique` (`course_id`, `department_id`, `level_id`, `session_id`, `semester_id`),
  KEY `institution_id` (`institution_id`),
  KEY `course_id` (`course_id`),
  KEY `department_id` (`department_id`),
  KEY `level_id` (`level_id`),
  KEY `session_id` (`session_id`),
  KEY `semester_id` (`semester_id`),
  FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`level_id`) REFERENCES `levels` (`level_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`session_id`) REFERENCES `sessions` (`session_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`semester_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. COURSE REGISTRATIONS TABLE
-- =====================================================
CREATE TABLE `course_registrations` (
  `registration_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('Registered','Dropped','Completed') DEFAULT 'Registered',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`registration_id`),
  UNIQUE KEY `student_course_session_semester` (`student_id`, `course_id`, `session_id`, `semester_id`),
  KEY `institution_id` (`institution_id`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  KEY `session_id` (`session_id`),
  KEY `semester_id` (`semester_id`),
  FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  FOREIGN KEY (`session_id`) REFERENCES `sessions` (`session_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`semester_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. RESULTS TABLE
-- =====================================================
CREATE TABLE `results` (
  `result_id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `ca_score` decimal(5,2) DEFAULT 0.00,
  `exam_score` decimal(5,2) DEFAULT 0.00,
  `total_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `grade` varchar(2) NOT NULL,
  `grade_point` decimal(3,2) NOT NULL DEFAULT 0.00,
  `remark` varchar(50),
  `entered_by` int(11),
  `approved_by` int(11),
  `is_approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`result_id`),
  UNIQUE KEY `registration_id` (`registration_id`),
  KEY `entered_by` (`entered_by`),
  KEY `approved_by` (`approved_by`),
  FOREIGN KEY (`registration_id`) REFERENCES `course_registrations` (`registration_id`) ON DELETE CASCADE,
  FOREIGN KEY (`entered_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. GPA TRACKING TABLES
-- =====================================================

-- Semester GPAs
CREATE TABLE `semester_gpas` (
  `gpa_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `gpa` decimal(4,2) NOT NULL DEFAULT 0.00,
  `credit_units` int(11) NOT NULL DEFAULT 0,
  `grade_points` decimal(8,2) NOT NULL DEFAULT 0.00,
  `courses_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`gpa_id`),
  UNIQUE KEY `student_session_semester` (`student_id`, `session_id`, `semester_id`),
  KEY `institution_id` (`institution_id`),
  KEY `session_id` (`session_id`),
  KEY `semester_id` (`semester_id`),
  FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`session_id`) REFERENCES `sessions` (`session_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`semester_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cumulative GPAs
CREATE TABLE `cumulative_gpas` (
  `cgpa_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `cgpa` decimal(4,2) NOT NULL DEFAULT 0.00,
  `total_credit_units` int(11) NOT NULL DEFAULT 0,
  `total_grade_points` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_courses` int(11) NOT NULL DEFAULT 0,
  `classification` varchar(50),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cgpa_id`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `institution_id` (`institution_id`),
  FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. GRADE SYSTEM TABLE
-- =====================================================
CREATE TABLE `grade_systems` (
  `grade_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `grade` varchar(2) NOT NULL,
  `min_score` decimal(5,2) NOT NULL,
  `max_score` decimal(5,2) NOT NULL,
  `grade_point` decimal(3,2) NOT NULL,
  `remark` varchar(50),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`grade_id`),
  KEY `institution_id` (`institution_id`),
  FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. SYSTEM SETTINGS TABLE
-- =====================================================
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `institution_setting` (`institution_id`, `setting_key`),
  FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12. INSERT DEFAULT DATA
-- =====================================================

-- Insert Default Institution
INSERT INTO `institutions` (`institution_id`, `institution_name`, `institution_code`, `address`, `phone`, `email`, `primary_color`, `secondary_color`) VALUES
(1, 'LUFEM School', 'LUFEM', 'Lagos, Nigeria', '+234-XXX-XXXX-XXX', 'info@lufemschool.edu.ng', '#00A651', '#FFFFFF');

-- Insert Default Admin User
INSERT INTO `users` (`institution_id`, `username`, `password`, `email`, `full_name`, `role`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@lufemschool.edu.ng', 'System Administrator', 'super_admin');
-- Password: password

-- Insert Default Departments
INSERT INTO `departments` (`institution_id`, `department_name`, `department_code`, `hod_name`) VALUES
(1, 'Computer Science', 'CSC', 'Dr. John Smith'),
(1, 'Mathematics', 'MTH', 'Prof. Jane Doe'),
(1, 'Physics', 'PHY', 'Dr. Mike Johnson'),
(1, 'Chemistry', 'CHM', 'Prof. Sarah Wilson'),
(1, 'Biology', 'BIO', 'Dr. David Brown');

-- Insert Academic Levels
INSERT INTO `levels` (`institution_id`, `level_name`, `level_order`) VALUES
(1, '100 Level', 1),
(1, '200 Level', 2),
(1, '300 Level', 3),
(1, '400 Level', 4);

-- Insert Academic Sessions
-- Insert Sessions
INSERT INTO `sessions` (`institution_id`, `session_name`, `start_date`, `end_date`, `is_current`) VALUES
(1, '2023/2024', '2023-09-01', '2024-08-31', 1),
(1, '2024/2025', '2024-09-01', '2025-08-31', 0);

-- Insert Sessions (for compatibility)

-- Insert Semesters
INSERT INTO `semesters` (`institution_id`, `semester_name`, `semester_order`, `is_current`) VALUES
(1, 'First Semester', 1, 1),
(1, 'Second Semester', 2, 0);

-- Insert Default Grade System
INSERT INTO `grade_systems` (`institution_id`, `grade`, `min_score`, `max_score`, `grade_point`, `remark`) VALUES
(1, 'A', 70.00, 100.00, 5.00, 'Excellent'),
(1, 'B', 60.00, 69.99, 4.00, 'Very Good'),
(1, 'C', 50.00, 59.99, 3.00, 'Good'),
(1, 'D', 45.00, 49.99, 2.00, 'Fair'),
(1, 'E', 40.00, 44.99, 1.00, 'Pass'),
(1, 'F', 0.00, 39.99, 0.00, 'Fail');

-- Insert Sample Courses
INSERT INTO `courses` (`institution_id`, `department_id`, `course_code`, `course_title`, `credit_units`) VALUES
(1, 1, 'CSC101', 'Introduction to Computer Science', 3),
(1, 1, 'CSC102', 'Computer Programming I', 3),
(1, 1, 'CSC201', 'Data Structures and Algorithms', 3),
(1, 1, 'CSC202', 'Computer Programming II', 3),
(1, 2, 'MTH101', 'Elementary Mathematics I', 3),
(1, 2, 'MTH102', 'Elementary Mathematics II', 3),
(1, 2, 'MTH201', 'Linear Algebra', 3),
(1, 3, 'PHY101', 'General Physics I', 3),
(1, 3, 'PHY102', 'General Physics II', 3);

-- Insert Course Structures (What courses are available for each level/semester)
INSERT INTO `course_structures` (`institution_id`, `course_id`, `department_id`, `level_id`, `session_id`, `semester_id`, `is_compulsory`) VALUES
-- 100 Level CSC First Semester
(1, 1, 1, 1, 1, 1, 1), -- CSC101
(1, 5, 1, 1, 1, 1, 1), -- MTH101
(1, 8, 1, 1, 1, 1, 1), -- PHY101
-- 100 Level CSC Second Semester
(1, 2, 1, 1, 1, 2, 1), -- CSC102
(1, 6, 1, 1, 1, 2, 1), -- MTH102
(1, 9, 1, 1, 1, 2, 1), -- PHY102
-- 200 Level CSC First Semester
(1, 3, 1, 2, 1, 1, 1), -- CSC201
(1, 7, 1, 2, 1, 1, 1), -- MTH201
-- 200 Level CSC Second Semester
(1, 4, 1, 2, 1, 2, 1); -- CSC202

-- Insert Sample Student
INSERT INTO `students` (`institution_id`, `matric_number`, `first_name`, `last_name`, `email`, `gender`, `department_id`, `level_id`, `admission_session`, `status`) VALUES
(1, 'LUFEM/CSC/2023/001', 'John', 'Doe', 'john.doe@student.lufemschool.edu.ng', 'Male', 1, 1, '2023/2024', 'Active');

-- Create student user account
INSERT INTO `users` (`institution_id`, `username`, `password`, `email`, `full_name`, `role`) VALUES
(1, 'LUFEM/CSC/2023/001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'john.doe@student.lufemschool.edu.ng', 'John Doe', 'student');
-- Password: password

-- Link student to user account
UPDATE `students` SET `user_id` = LAST_INSERT_ID() WHERE `matric_number` = 'LUFEM/CSC/2023/001';

-- Insert System Settings
INSERT INTO `system_settings` (`institution_id`, `setting_key`, `setting_value`, `setting_type`, `description`) VALUES
(1, 'ca_max_score', '30', 'number', 'Maximum CA score'),
(1, 'exam_max_score', '70', 'number', 'Maximum exam score'),
(1, 'pass_mark', '40', 'number', 'Minimum pass mark'),
(1, 'max_credit_units_per_semester', '24', 'number', 'Maximum credit units per semester'),
(1, 'enable_course_registration', '1', 'boolean', 'Enable student course registration'),
(1, 'enable_result_viewing', '1', 'boolean', 'Enable student result viewing');

COMMIT;

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_students_matric ON students(matric_number);
CREATE INDEX idx_students_department ON students(department_id);
CREATE INDEX idx_courses_code ON courses(course_code);
CREATE INDEX idx_courses_department ON courses(department_id);
CREATE INDEX idx_registrations_student ON course_registrations(student_id);
CREATE INDEX idx_registrations_course ON course_registrations(course_id);
CREATE INDEX idx_registrations_session_semester ON course_registrations(session_id, semester_id);
CREATE INDEX idx_results_registration ON results(registration_id);
CREATE INDEX idx_results_grade ON results(grade);

-- =====================================================
-- VIEWS FOR EASY DATA ACCESS
-- =====================================================

-- Student Results View
CREATE VIEW `student_results_view` AS
SELECT 
    s.student_id,
    s.matric_number,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    d.department_name,
    c.course_code,
    c.course_title,
    c.credit_units,
    sess.session_name,
    sem.semester_name,
    r.ca_score,
    r.exam_score,
    r.total_score,
    r.grade,
    r.grade_point,
    r.created_at as result_date
FROM students s
JOIN departments d ON s.department_id = d.department_id
JOIN course_registrations cr ON s.student_id = cr.student_id
JOIN courses c ON cr.course_id = c.course_id
JOIN sessions sess ON cr.session_id = sess.session_id
JOIN semesters sem ON cr.semester_id = sem.semester_id
JOIN results r ON cr.registration_id = r.registration_id;

-- Course Registration Summary View
CREATE VIEW `course_registration_summary` AS
SELECT 
    c.course_id,
    c.course_code,
    c.course_title,
    d.department_name,
    sess.session_name,
    sem.semester_name,
    COUNT(cr.registration_id) as total_registrations,
    COUNT(r.result_id) as completed_results,
    AVG(r.total_score) as average_score
FROM courses c
JOIN departments d ON c.department_id = d.department_id
LEFT JOIN course_registrations cr ON c.course_id = cr.course_id
LEFT JOIN sessions sess ON cr.session_id = sess.session_id
LEFT JOIN semesters sem ON cr.semester_id = sem.semester_id
LEFT JOIN results r ON cr.registration_id = r.registration_id
GROUP BY c.course_id, sess.session_id, sem.semester_id;

-- =====================================================
-- STORED PROCEDURES FOR COMMON OPERATIONS
-- =====================================================

DELIMITER //

-- Procedure to calculate and update semester GPA
CREATE PROCEDURE `UpdateSemesterGPA`(
    IN p_student_id INT,
    IN p_session_id INT,
    IN p_semester_id INT
)
BEGIN
    DECLARE v_gpa DECIMAL(4,2) DEFAULT 0.00;
    DECLARE v_total_points DECIMAL(8,2) DEFAULT 0.00;
    DECLARE v_total_units INT DEFAULT 0;
    DECLARE v_course_count INT DEFAULT 0;
    
    -- Calculate semester GPA
    SELECT 
        COALESCE(SUM(r.grade_point * c.credit_units), 0),
        COALESCE(SUM(c.credit_units), 0),
        COUNT(*)
    INTO v_total_points, v_total_units, v_course_count
    FROM course_registrations cr
    JOIN courses c ON cr.course_id = c.course_id
    JOIN results r ON cr.registration_id = r.registration_id
    WHERE cr.student_id = p_student_id 
    AND cr.session_id = p_session_id 
    AND cr.semester_id = p_semester_id;
    
    -- Calculate GPA
    IF v_total_units > 0 THEN
        SET v_gpa = v_total_points / v_total_units;
    END IF;
    
    -- Insert or update semester GPA
    INSERT INTO semester_gpas (institution_id, student_id, session_id, semester_id, gpa, credit_units, grade_points, courses_count)
    SELECT s.institution_id, p_student_id, p_session_id, p_semester_id, v_gpa, v_total_units, v_total_points, v_course_count
    FROM students s WHERE s.student_id = p_student_id
    ON DUPLICATE KEY UPDATE
        gpa = v_gpa,
        credit_units = v_total_units,
        grade_points = v_total_points,
        courses_count = v_course_count,
        updated_at = NOW();
END //

-- Procedure to calculate and update cumulative GPA
CREATE PROCEDURE `UpdateCumulativeGPA`(
    IN p_student_id INT
)
BEGIN
    DECLARE v_cgpa DECIMAL(4,2) DEFAULT 0.00;
    DECLARE v_total_points DECIMAL(10,2) DEFAULT 0.00;
    DECLARE v_total_units INT DEFAULT 0;
    DECLARE v_total_courses INT DEFAULT 0;
    DECLARE v_classification VARCHAR(50) DEFAULT 'Pass';
    
    -- Calculate cumulative GPA
    SELECT 
        COALESCE(SUM(r.grade_point * c.credit_units), 0),
        COALESCE(SUM(c.credit_units), 0),
        COUNT(*)
    INTO v_total_points, v_total_units, v_total_courses
    FROM course_registrations cr
    JOIN courses c ON cr.course_id = c.course_id
    JOIN results r ON cr.registration_id = r.registration_id
    WHERE cr.student_id = p_student_id;
    
    -- Calculate CGPA
    IF v_total_units > 0 THEN
        SET v_cgpa = v_total_points / v_total_units;
    END IF;
    
    -- Determine classification
    IF v_cgpa >= 4.50 THEN
        SET v_classification = 'First Class';
    ELSEIF v_cgpa >= 3.50 THEN
        SET v_classification = 'Second Class Upper';
    ELSEIF v_cgpa >= 2.40 THEN
        SET v_classification = 'Second Class Lower';
    ELSEIF v_cgpa >= 1.50 THEN
        SET v_classification = 'Third Class';
    ELSE
        SET v_classification = 'Pass';
    END IF;
    
    -- Insert or update cumulative GPA
    INSERT INTO cumulative_gpas (institution_id, student_id, cgpa, total_credit_units, total_grade_points, total_courses, classification)
    SELECT s.institution_id, p_student_id, v_cgpa, v_total_units, v_total_points, v_total_courses, v_classification
    FROM students s WHERE s.student_id = p_student_id
    ON DUPLICATE KEY UPDATE
        cgpa = v_cgpa,
        total_credit_units = v_total_units,
        total_grade_points = v_total_points,
        total_courses = v_total_courses,
        classification = v_classification,
        updated_at = NOW();
END //

DELIMITER ;

-- =====================================================
-- TRIGGERS FOR AUTOMATIC GPA UPDATES
-- =====================================================

DELIMITER //

-- Trigger to update GPAs when results are inserted
CREATE TRIGGER `after_result_insert` AFTER INSERT ON `results`
FOR EACH ROW
BEGIN
    DECLARE v_student_id INT;
    DECLARE v_session_id INT;
    DECLARE v_semester_id INT;
    
    -- Get student and session info
    SELECT cr.student_id, cr.session_id, cr.semester_id
    INTO v_student_id, v_session_id, v_semester_id
    FROM course_registrations cr
    WHERE cr.registration_id = NEW.registration_id;
    
    -- Update semester GPA
    CALL UpdateSemesterGPA(v_student_id, v_session_id, v_semester_id);
    
    -- Update cumulative GPA
    CALL UpdateCumulativeGPA(v_student_id);
END //

-- Trigger to update GPAs when results are updated
CREATE TRIGGER `after_result_update` AFTER UPDATE ON `results`
FOR EACH ROW
BEGIN
    DECLARE v_student_id INT;
    DECLARE v_session_id INT;
    DECLARE v_semester_id INT;
    
    -- Get student and session info
    SELECT cr.student_id, cr.session_id, cr.semester_id
    INTO v_student_id, v_session_id, v_semester_id
    FROM course_registrations cr
    WHERE cr.registration_id = NEW.registration_id;
    
    -- Update semester GPA
    CALL UpdateSemesterGPA(v_student_id, v_session_id, v_semester_id);
    
    -- Update cumulative GPA
    CALL UpdateCumulativeGPA(v_student_id);
END //

DELIMITER ;

-- =====================================================
-- END OF DATABASE SCHEMA
-- =====================================================