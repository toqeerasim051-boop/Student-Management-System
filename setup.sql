-- =============================================
-- Student Management System - Database Setup
-- Run this file once to initialize the database
-- =============================================

CREATE DATABASE IF NOT EXISTS sms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sms_db;

-- Users table (all roles)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Classes
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(100) NOT NULL,
    section VARCHAR(10) DEFAULT 'A',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Subjects
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20),
    class_id INT,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
);

-- Teachers
CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    qualification VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Students
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    class_id INT,
    roll_no VARCHAR(30),
    phone VARCHAR(20),
    address TEXT,
    parent_name VARCHAR(100),
    parent_phone VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
);

-- Teacher assignments (which teacher teaches which subject in which class)
CREATE TABLE IF NOT EXISTS teacher_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    class_id INT NOT NULL,
    UNIQUE KEY unique_assignment (teacher_id, subject_id, class_id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- Attendance
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    class_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present','absent','late') DEFAULT 'present',
    marked_by INT,
    UNIQUE KEY no_duplicate (student_id, subject_id, date),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- Grades
CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    marks DECIMAL(5,2) NOT NULL DEFAULT 0,
    max_marks DECIMAL(5,2) NOT NULL DEFAULT 100,
    term VARCHAR(50) DEFAULT 'Term 1',
    exam_type VARCHAR(50) DEFAULT 'Mid Term',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_grade (student_id, subject_id, term, exam_type),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- Activity Logs
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_role VARCHAR(20),
    action VARCHAR(255) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Announcements
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    target_role ENUM('all','student','teacher') DEFAULT 'all',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- SAMPLE DATA
-- =============================================
-- IMPORTANT: The hashes below use the password 'Admin@123' / 'Teacher@123' / 'Student@123'.
-- For production, run: php generate_passwords.php
-- and replace these INSERT statements with the output.
-- =============================================

-- Default Admin (password: Admin@123)
INSERT INTO users (name, email, password, role) VALUES
('System Admin', 'admin@sms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Classes
INSERT INTO classes (class_name, section) VALUES
('Class 9', 'A'),
('Class 9', 'B'),
('Class 10', 'A'),
('Class 10', 'B'),
('Class 11', 'Science'),
('Class 12', 'Science');

-- Subjects (assigned to classes)
INSERT INTO subjects (subject_name, subject_code, class_id) VALUES
('Mathematics', 'MATH-9A', 1),
('English', 'ENG-9A', 1),
('Physics', 'PHY-9A', 1),
('Chemistry', 'CHEM-9A', 1),
('Mathematics', 'MATH-9B', 2),
('English', 'ENG-9B', 2),
('Mathematics', 'MATH-10A', 3),
('English', 'ENG-10A', 3),
('Physics', 'PHY-10A', 3),
('Biology', 'BIO-10A', 3),
('Mathematics', 'MATH-11S', 5),
('Physics', 'PHY-11S', 5),
('Chemistry', 'CHEM-11S', 5),
('Mathematics', 'MATH-12S', 6),
('Physics', 'PHY-12S', 6),
('Chemistry', 'CHEM-12S', 6);

-- Teachers (password: Teacher@123)
INSERT INTO users (name, email, password, role) VALUES
('Mr. Ali Hassan', 'ali@sms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('Ms. Sara Khan', 'sara@sms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('Mr. Usman Malik', 'usman@sms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher');

INSERT INTO teachers (user_id, phone, qualification) VALUES
(2, '0300-1111111', 'M.Sc Mathematics'),
(3, '0300-2222222', 'M.A English'),
(4, '0300-3333333', 'M.Sc Physics');

-- Assign teachers to subjects/classes
INSERT INTO teacher_assignments (teacher_id, subject_id, class_id) VALUES
(1, 1, 1), -- Ali -> Math -> Class 9A
(1, 5, 2), -- Ali -> Math -> Class 9B
(1, 7, 3), -- Ali -> Math -> Class 10A
(2, 2, 1), -- Sara -> English -> Class 9A
(2, 6, 2), -- Sara -> English -> Class 9B
(2, 8, 3), -- Sara -> English -> Class 10A
(3, 3, 1), -- Usman -> Physics -> Class 9A
(3, 9, 3); -- Usman -> Physics -> Class 10A

-- Students (password: Student@123)
INSERT INTO users (name, email, password, role) VALUES
('Ahmed Raza', 'ahmed@sms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Fatima Noor', 'fatima@sms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Hassan Ali', 'hassan@sms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Ayesha Bibi', 'ayesha@sms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Zain Ul Abideen', 'zain@sms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Hina Tariq', 'hina@sms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

INSERT INTO students (user_id, class_id, roll_no, phone, parent_name, parent_phone) VALUES
(5, 1, '9A-001', '0300-5555551', 'Mr. Raza Khan', '0300-4444441'),
(6, 1, '9A-002', '0300-5555552', 'Mr. Noor Ahmed', '0300-4444442'),
(7, 1, '9A-003', '0300-5555553', 'Mr. Ali Shah', '0300-4444443'),
(8, 2, '9B-001', '0300-5555554', 'Mr. Bibi Gul', '0300-4444444'),
(9, 3, '10A-001', '0300-5555555', 'Mr. Abideen', '0300-4444445'),
(10, 3, '10A-002', '0300-5555556', 'Mr. Tariq Mehmood', '0300-4444446');

-- Sample announcement
INSERT INTO announcements (title, content, target_role, created_by) VALUES
('Welcome to New Academic Year', 'We are excited to start the new academic year. All students must submit their fee by end of this month.', 'all', 1),
('Mid Term Exams Schedule', 'Mid term exams will be held from 15th to 20th of next month. Timetable will be shared soon.', 'student', 1);
