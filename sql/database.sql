-- ============================================
-- SMART ATTENDANCE SYSTEM DATABASE
-- ============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS smart_attendance;
USE smart_attendance;

-- ============================================
-- USERS TABLE (Main authentication)
-- ============================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    fullname VARCHAR(100),
    status ENUM('pending', 'active', 'inactive', 'rejected') DEFAULT 'pending',
    email_verified TINYINT DEFAULT 0,
    email_verification_token VARCHAR(255),
    approval_token VARCHAR(255),
    registration_code VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- TEACHERS TABLE
-- ============================================
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    teacher_id VARCHAR(20) UNIQUE NOT NULL,
    gender ENUM('Male', 'Female', 'Other'),
    photo VARCHAR(255) DEFAULT 'default_avatar.png',
    registered_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- YEAR LEVELS TABLE
-- ============================================
CREATE TABLE year_levels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level_name VARCHAR(50) NOT NULL,
    sort_order INT DEFAULT 0
);

-- ============================================
-- SECTIONS TABLE
-- ============================================
CREATE TABLE sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_name VARCHAR(50) NOT NULL,
    year_level_id INT,
    teacher_id INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (year_level_id) REFERENCES year_levels(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);

-- ============================================
-- STUDENTS TABLE
-- ============================================
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    lrn VARCHAR(20) UNIQUE NOT NULL,
    rfid_uid VARCHAR(50) UNIQUE,
    qr_code VARCHAR(255),
    fullname VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female', 'Other'),
    birth_date DATE,
    parent_name VARCHAR(100),
    parent_contact VARCHAR(20),
    address TEXT,
    photo VARCHAR(255) DEFAULT 'default_student.png',
    section_id INT,
    status ENUM('active', 'inactive', 'graduated') DEFAULT 'active',
    enrolled_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id)
);

-- ============================================
-- ATTENDANCE LOG TABLE
-- ============================================
CREATE TABLE attendance_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    log_date DATE NOT NULL,
    log_type ENUM('morning_in', 'morning_out', 'afternoon_in', 'afternoon_out'),
    time_captured TIME,
    captured_by INT NULL,
    method ENUM('rfid', 'qr', 'manual'),
    status ENUM('on_time', 'late') DEFAULT 'on_time',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (captured_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (student_id, log_date, log_type)
);

-- ============================================
-- PASSWORD RESETS TABLE
-- ============================================
CREATE TABLE password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- REGISTRATION TOKENS TABLE
-- ============================================
CREATE TABLE registration_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    type ENUM('verification', 'approval') DEFAULT 'verification',
    expires_at DATETIME NOT NULL,
    used TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- SMS HISTORY TABLE
-- ============================================
CREATE TABLE sms_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_type ENUM('year_level', 'section', 'individual'),
    recipient_ids TEXT,
    message TEXT,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    sent_by INT,
    sent_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sent_by) REFERENCES users(id)
);

-- ============================================
-- INSERT YEAR LEVELS
-- ============================================
INSERT INTO year_levels (level_name, sort_order) VALUES
('Grade 7', 1), 
('Grade 8', 2), 
('Grade 9', 3), 
('Grade 10', 4),
('Grade 11', 5), 
('Grade 12', 6);

-- ============================================
-- INSERT USERS (Admin, Teachers, Students)
-- Note: Passwords will be hashed by reset_passwords.php
-- Registration code for all non-admin: account2026
-- ============================================

-- Admin User
INSERT INTO users (username, password, role, email, phone, fullname, status, email_verified, registration_code, created_at) VALUES 
('admin', 'temp_password', 'admin', 'admin@smartattendance.com', '09123456789', 'System Administrator', 'active', 1, 'admin2026', NOW());

-- Teacher Users (10 teachers)
INSERT INTO users (username, password, role, email, phone, fullname, status, email_verified, registration_code, created_at) VALUES 
('teacher_jreyes', 'temp_password', 'teacher', 'juan.reyes@school.edu', '09123456789', 'Juan Reyes', 'active', 1, 'account2026', '2024-01-15 08:00:00'),
('teacher_mcruz', 'temp_password', 'teacher', 'maria.cruz@school.edu', '09123456790', 'Maria Cruz', 'active', 1, 'account2026', '2024-01-20 09:30:00'),
('teacher_rsantos', 'temp_password', 'teacher', 'ramon.santos@school.edu', '09123456791', 'Ramon Santos', 'active', 1, 'account2026', '2024-02-01 10:15:00'),
('teacher_agonzales', 'temp_password', 'teacher', 'ana.gonzales@school.edu', '09123456792', 'Ana Gonzales', 'active', 1, 'account2026', '2024-02-10 11:00:00'),
('teacher_mramos', 'temp_password', 'teacher', 'michael.ramos@school.edu', '09123456793', 'Michael Ramos', 'active', 1, 'account2026', '2024-02-15 08:45:00'),
('teacher_cdelacruz', 'temp_password', 'teacher', 'catherine.delacruz@school.edu', '09123456794', 'Catherine Dela Cruz', 'active', 1, 'account2026', '2024-03-01 13:20:00'),
('teacher_mfernandez', 'temp_password', 'teacher', 'mark.fernandez@school.edu', '09123456795', 'Mark Fernandez', 'active', 1, 'account2026', '2024-03-05 14:10:00'),
('teacher_rrivera', 'temp_password', 'teacher', 'rose.rivera@school.edu', '09123456796', 'Rose Rivera', 'active', 1, 'account2026', '2024-03-10 15:30:00'),
('teacher_mvillar', 'temp_password', 'teacher', 'mario.villar@school.edu', '09123456797', 'Mario Villar', 'active', 1, 'account2026', '2024-03-15 09:00:00'),
('teacher_jdizon', 'temp_password', 'teacher', 'jennifer.dizon@school.edu', '09123456798', 'Jennifer Dizon', 'active', 1, 'account2026', '2024-03-20 11:30:00');

-- Student Users (15 student accounts)
INSERT INTO users (username, password, role, email, phone, fullname, status, email_verified, registration_code, created_at) VALUES 
('alice.mendoza', 'temp_password', 'student', 'alice.mendoza@student.edu', '09123456001', 'Alice Mendoza', 'active', 1, 'account2026', '2024-06-10 08:00:00'),
('benjamin.torres', 'temp_password', 'student', 'benjamin.torres@student.edu', '09123456002', 'Benjamin Torres', 'active', 1, 'account2026', '2024-06-10 08:30:00'),
('catherine.flores', 'temp_password', 'student', 'catherine.flores@student.edu', '09123456003', 'Catherine Flores', 'active', 1, 'account2026', '2024-06-10 09:00:00'),
('daniel.santos', 'temp_password', 'student', 'daniel.santos@student.edu', '09123456004', 'Daniel Santos', 'active', 1, 'account2026', '2024-06-10 09:30:00'),
('elena.villanueva', 'temp_password', 'student', 'elena.villanueva@student.edu', '09123456005', 'Elena Villanueva', 'active', 1, 'account2026', '2024-06-10 10:00:00'),
('fernando.lopez', 'temp_password', 'student', 'fernando.lopez@student.edu', '09123456006', 'Fernando Lopez', 'active', 1, 'account2026', '2024-06-10 10:30:00'),
('grace.reyes', 'temp_password', 'student', 'grace.reyes@student.edu', '09123456007', 'Grace Reyes', 'active', 1, 'account2026', '2024-06-10 11:00:00'),
('henry.garcia', 'temp_password', 'student', 'henry.garcia@student.edu', '09123456008', 'Henry Garcia', 'active', 1, 'account2026', '2024-06-10 11:30:00'),
('isabel.reyes', 'temp_password', 'student', 'isabel.reyes@student.edu', '09123456009', 'Isabel Reyes', 'active', 1, 'account2026', '2024-06-10 12:00:00'),
('james.lopez', 'temp_password', 'student', 'james.lopez@student.edu', '09123456010', 'James Lopez', 'active', 1, 'account2026', '2024-06-10 12:30:00'),
('kelly.mercado', 'temp_password', 'student', 'kelly.mercado@student.edu', '09123456011', 'Kelly Mercado', 'active', 1, 'account2026', '2024-06-10 13:00:00'),
('nathan.cruz', 'temp_password', 'student', 'nathan.cruz@student.edu', '09123456014', 'Nathan Cruz', 'active', 1, 'account2026', '2024-06-10 14:00:00'),
('timothy.ramos', 'temp_password', 'student', 'timothy.ramos@student.edu', '09123456020', 'Timothy Ramos', 'active', 1, 'account2026', '2024-06-10 15:00:00'),
('yvonne.delacruz', 'temp_password', 'student', 'yvonne.delacruz@student.edu', '09123456025', 'Yvonne Dela Cruz', 'active', 1, 'account2026', '2024-06-10 16:00:00'),
('david.torres', 'temp_password', 'student', 'david.torres@student.edu', '09123456030', 'David Torres', 'active', 1, 'account2026', '2024-06-10 17:00:00');

-- ============================================
-- INSERT TEACHER PROFILES
-- ============================================
INSERT INTO teachers (user_id, teacher_id, gender, photo, registered_date) VALUES
(2, 'TCH20240001', 'Male', 'default_avatar.png', '2024-01-15'),
(3, 'TCH20240002', 'Female', 'default_avatar.png', '2024-01-20'),
(4, 'TCH20240003', 'Male', 'default_avatar.png', '2024-02-01'),
(5, 'TCH20240004', 'Female', 'default_avatar.png', '2024-02-10'),
(6, 'TCH20240005', 'Male', 'default_avatar.png', '2024-02-15'),
(7, 'TCH20240006', 'Female', 'default_avatar.png', '2024-03-01'),
(8, 'TCH20240007', 'Male', 'default_avatar.png', '2024-03-05'),
(9, 'TCH20240008', 'Female', 'default_avatar.png', '2024-03-10'),
(10, 'TCH20240009', 'Male', 'default_avatar.png', '2024-03-15'),
(11, 'TCH20240010', 'Female', 'default_avatar.png', '2024-03-20');

-- ============================================
-- INSERT SECTIONS
-- ============================================
INSERT INTO sections (section_name, year_level_id, teacher_id, status, created_at) VALUES
-- Grade 7 Sections
('7 - Wisdom', 1, 2, 'active', '2024-06-01 08:00:00'),
('7 - Excellence', 1, 3, 'active', '2024-06-01 08:00:00'),
('7 - Integrity', 1, 4, 'active', '2024-06-01 08:00:00'),
-- Grade 8 Sections
('8 - Respect', 2, 5, 'active', '2024-06-01 08:00:00'),
('8 - Honesty', 2, 6, 'active', '2024-06-01 08:00:00'),
-- Grade 9 Sections
('9 - Diligence', 3, 7, 'active', '2024-06-01 08:00:00'),
('9 - Patience', 3, 8, 'active', '2024-06-01 08:00:00'),
-- Grade 10 Sections
('10 - Leadership', 4, 9, 'active', '2024-06-01 08:00:00'),
('10 - Service', 4, 10, 'active', '2024-06-01 08:00:00'),
-- Grade 11 Sections
('11 - STEM A', 5, 2, 'active', '2024-06-01 08:00:00'),
('11 - ABM B', 5, 3, 'active', '2024-06-01 08:00:00'),
-- Grade 12 Sections
('12 - STEM A', 6, 4, 'active', '2024-06-01 08:00:00'),
('12 - HUMSS B', 6, 5, 'active', '2024-06-01 08:00:00');

-- ============================================
-- INSERT STUDENTS (35 Students)
-- ============================================
INSERT INTO students (lrn, rfid_uid, qr_code, fullname, gender, birth_date, parent_name, parent_contact, address, section_id, status, enrolled_date) VALUES
-- Grade 7 Students (Section 1,2,3)
('123456789001', 'RFID:1001', 'STU_STU001', 'Alice Mendoza', 'Female', '2011-05-15', 'Roberto Mendoza', '09123456001', '123 Mabini St., Manila', 1, 'active', '2024-06-10'),
('123456789002', 'RFID:1002', 'STU_STU002', 'Benjamin Torres', 'Male', '2011-08-22', 'Luz Torres', '09123456002', '456 Rizal Ave., Quezon City', 1, 'active', '2024-06-10'),
('123456789003', 'RFID:1003', 'STU_STU003', 'Catherine Flores', 'Female', '2011-03-10', 'Ramon Flores', '09123456003', '789 Luna St., Caloocan', 2, 'active', '2024-06-10'),
('123456789004', 'RFID:1004', 'STU_STU004', 'Daniel Santos', 'Male', '2011-11-30', 'Susan Santos', '09123456004', '321 Bonifacio St., Pasay', 2, 'active', '2024-06-10'),
('123456789005', 'RFID:1005', 'STU_STU005', 'Elena Villanueva', 'Female', '2011-07-18', 'Felipe Villanueva', '09123456005', '654 Aguinaldo St., Mandaluyong', 3, 'active', '2024-06-10'),
('123456789006', 'RFID:1006', 'STU_STU006', 'Fernando Lopez', 'Male', '2011-09-12', 'Gloria Lopez', '09123456006', '987 Rizal St., Manila', 3, 'active', '2024-06-10'),
('123456789007', 'RFID:1007', 'STU_STU007', 'Grace Reyes', 'Female', '2011-04-25', 'Ronald Reyes', '09123456007', '147 Quezon Ave., QC', 1, 'active', '2024-06-10'),

-- Grade 8 Students (Section 4,5)
('123456789008', 'RFID:1008', 'STU_STU008', 'Henry Garcia', 'Male', '2010-02-14', 'Maria Garcia', '09123456008', '987 Laurel St., Marikina', 4, 'active', '2024-06-10'),
('123456789009', 'RFID:1009', 'STU_STU009', 'Isabel Reyes', 'Female', '2010-09-25', 'Jose Reyes', '09123456009', '147 Quezon St., Pasig', 4, 'active', '2024-06-10'),
('123456789010', 'RFID:1010', 'STU_STU010', 'James Lopez', 'Male', '2010-12-03', 'Teresa Lopez', '09123456010', '258 Rizal St., Taguig', 5, 'active', '2024-06-10'),
('123456789011', 'RFID:1011', 'STU_STU011', 'Kelly Mercado', 'Female', '2010-04-20', 'Ricardo Mercado', '09123456011', '369 Magsaysay St., Paranaque', 5, 'active', '2024-06-10'),
('123456789012', 'RFID:1012', 'STU_STU012', 'Luis Fernandez', 'Male', '2010-06-15', 'Leticia Fernandez', '09123456012', '741 Mabini St., Manila', 4, 'active', '2024-06-10'),
('123456789013', 'RFID:1013', 'STU_STU013', 'Megan Cruz', 'Female', '2010-10-08', 'Ramon Cruz', '09123456013', '852 Luna St., Caloocan', 5, 'active', '2024-06-10'),

-- Grade 9 Students (Section 6,7)
('123456789014', 'RFID:1014', 'STU_STU014', 'Nathan Cruz', 'Male', '2009-06-08', 'Luzviminda Cruz', '09123456014', '741 Mabuhay St., Las Pinas', 6, 'active', '2024-06-10'),
('123456789015', 'RFID:1015', 'STU_STU015', 'Olivia Bautista', 'Female', '2009-10-17', 'Gregorio Bautista', '09123456015', '852 Kalayaan St., Makati', 6, 'active', '2024-06-10'),
('123456789016', 'RFID:1016', 'STU_STU016', 'Paul Rivera', 'Male', '2009-03-29', 'Patricia Rivera', '09123456016', '963 Silang St., Muntinlupa', 7, 'active', '2024-06-10'),
('123456789017', 'RFID:1017', 'STU_STU017', 'Queenie Gonzales', 'Female', '2009-08-12', 'Rogelio Gonzales', '09123456017', '159 Del Pilar St., Valenzuela', 7, 'active', '2024-06-10'),
('123456789018', 'RFID:1018', 'STU_STU018', 'Ramon Santos', 'Male', '2009-01-20', 'Susan Santos', '09123456018', '753 Bonifacio St., Pasay', 6, 'active', '2024-06-10'),
('123456789019', 'RFID:1019', 'STU_STU019', 'Sarah Villanueva', 'Female', '2009-11-05', 'Felipe Villanueva', '09123456019', '864 Aguinaldo St., Mandaluyong', 7, 'active', '2024-06-10'),

-- Grade 10 Students (Section 8,9)
('123456789020', 'RFID:1020', 'STU_STU020', 'Timothy Ramos', 'Male', '2008-01-05', 'Carmen Ramos', '09123456020', '753 Bagong Lipunan St., Navotas', 8, 'active', '2024-06-10'),
('123456789021', 'RFID:1021', 'STU_STU021', 'Ursula Pascual', 'Female', '2008-07-21', 'Dante Pascual', '09123456021', '951 Pag-asa St., Malabon', 8, 'active', '2024-06-10'),
('123456789022', 'RFID:1022', 'STU_STU022', 'Victor Fernandez', 'Male', '2008-11-14', 'Leticia Fernandez', '09123456022', '357 Diwa St., San Juan', 9, 'active', '2024-06-10'),
('123456789023', 'RFID:1023', 'STU_STU023', 'Wendy Garcia', 'Female', '2008-03-18', 'Ramon Garcia', '09123456023', '258 Tagumpay St., Pateros', 9, 'active', '2024-06-10'),
('123456789024', 'RFID:1024', 'STU_STU024', 'Xavier Mendoza', 'Male', '2008-09-30', 'Gloria Mendoza', '09123456024', '147 Kalikasan St., QC', 8, 'active', '2024-06-10'),

-- Grade 11 Students (Section 10,11)
('123456789025', 'RFID:1025', 'STU_STU025', 'Yvonne Dela Cruz', 'Female', '2007-04-28', 'Manuel Dela Cruz', '09123456025', '246 Tagumpay St., Pateros', 10, 'active', '2024-06-10'),
('123456789026', 'RFID:1026', 'STU_STU026', 'Zachary Villanueva', 'Male', '2007-09-09', 'Lourdes Villanueva', '09123456026', '135 Kalikasan St., Quezon City', 10, 'active', '2024-06-10'),
('123456789027', 'RFID:1027', 'STU_STU027', 'Andrea Aquino', 'Female', '2007-12-25', 'Benigno Aquino', '09123456027', '864 Kapayapaan St., Manila', 11, 'active', '2024-06-10'),
('123456789028', 'RFID:1028', 'STU_STU028', 'Brian Jimenez', 'Male', '2007-06-14', 'Gloria Jimenez', '09123456028', '579 Kaunlaran St., Pasig', 11, 'active', '2024-06-10'),
('123456789029', 'RFID:1029', 'STU_STU029', 'Catherine Gomez', 'Female', '2007-08-03', 'Rogelio Gomez', '09123456029', '753 Pagkakaisa St., Mandaluyong', 10, 'active', '2024-06-10'),

-- Grade 12 Students (Section 12,13)
('123456789030', 'RFID:1030', 'STU_STU030', 'David Torres', 'Male', '2006-05-15', 'Luz Torres', '09123456030', '456 Rizal Ave., QC', 12, 'active', '2024-06-10'),
('123456789031', 'RFID:1031', 'STU_STU031', 'Erika Flores', 'Female', '2006-10-22', 'Ramon Flores', '09123456031', '789 Luna St., Caloocan', 12, 'active', '2024-06-10'),
('123456789032', 'RFID:1032', 'STU_STU032', 'Francisco Santos', 'Male', '2006-03-10', 'Susan Santos', '09123456032', '321 Bonifacio St., Pasay', 13, 'active', '2024-06-10'),
('123456789033', 'RFID:1033', 'STU_STU033', 'Gina Villanueva', 'Female', '2006-07-18', 'Felipe Villanueva', '09123456033', '654 Aguinaldo St., Mandaluyong', 13, 'active', '2024-06-10'),
('123456789034', 'RFID:1034', 'STU_STU034', 'Henry Lopez', 'Male', '2006-12-03', 'Teresa Lopez', '09123456034', '258 Rizal St., Taguig', 12, 'active', '2024-06-10'),
('123456789035', 'RFID:1035', 'STU_STU035', 'Irene Mercado', 'Female', '2006-04-20', 'Ricardo Mercado', '09123456035', '369 Magsaysay St., Paranaque', 13, 'active', '2024-06-10');

-- ============================================
-- INSERT ATTENDANCE LOGS (Last 14 days)
-- ============================================
INSERT INTO attendance_log (student_id, log_date, log_type, time_captured, captured_by, method, status) VALUES
-- Student 1 (Alice Mendoza) - Consistent attendance
(1, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'morning_in', '07:10:00', 1, 'rfid', 'on_time'),
(1, DATE_SUB(CURDATE(), INTERVAL 13 DAY), 'morning_in', '07:20:00', 1, 'rfid', 'on_time'),
(1, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'morning_in', '07:15:00', 1, 'rfid', 'on_time'),
(1, DATE_SUB(CURDATE(), INTERVAL 11 DAY), 'morning_in', '07:25:00', 1, 'rfid', 'on_time'),
(1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'morning_in', '07:05:00', 1, 'rfid', 'on_time'),
(1, DATE_SUB(CURDATE(), INTERVAL 9 DAY), 'morning_in', '07:30:00', 1, 'rfid', 'on_time'),
(1, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'morning_in', '07:45:00', 1, 'rfid', 'late'),
(1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'morning_in', '07:08:00', 1, 'rfid', 'on_time'),
(1, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'morning_in', '07:12:00', 1, 'rfid', 'on_time'),
(1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'morning_in', '07:18:00', 1, 'rfid', 'on_time'),
(1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'morning_in', '07:22:00', 1, 'rfid', 'on_time'),
(1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'morning_in', '07:30:00', 1, 'rfid', 'on_time'),
(1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'morning_in', '07:15:00', 1, 'rfid', 'on_time'),
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'morning_in', '07:20:00', 1, 'rfid', 'on_time'),

-- Student 14 (Nathan Cruz) - Mixed attendance
(14, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'morning_in', '07:30:00', 1, 'qr', 'on_time'),
(14, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'morning_out', '12:10:00', 1, 'qr', 'on_time'),
(14, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'afternoon_in', '13:20:00', 1, 'qr', 'on_time'),
(14, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'afternoon_out', '17:15:00', 1, 'qr', 'on_time'),
(14, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'morning_in', '07:45:00', 1, 'qr', 'late'),
(14, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'morning_in', '07:20:00', 1, 'qr', 'on_time'),
(14, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'morning_in', '07:35:00', 1, 'qr', 'on_time'),
(14, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'morning_in', '08:00:00', 1, 'qr', 'late'),
(14, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'morning_in', '07:25:00', 1, 'qr', 'on_time'),
(14, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'morning_in', '07:15:00', 1, 'qr', 'on_time'),

-- Student 20 (Timothy Ramos) - Perfect attendance
(20, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'morning_in', '07:05:00', 1, 'rfid', 'on_time'),
(20, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'morning_out', '12:05:00', 1, 'rfid', 'on_time'),
(20, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'afternoon_in', '13:10:00', 1, 'rfid', 'on_time'),
(20, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'afternoon_out', '17:05:00', 1, 'rfid', 'on_time'),
(20, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'morning_in', '07:08:00', 1, 'rfid', 'on_time'),
(20, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'afternoon_in', '13:12:00', 1, 'rfid', 'on_time'),
(20, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'morning_in', '07:10:00', 1, 'rfid', 'on_time'),
(20, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'afternoon_in', '13:15:00', 1, 'rfid', 'on_time'),
(20, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'morning_in', '07:05:00', 1, 'rfid', 'on_time'),
(20, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'morning_in', '07:12:00', 1, 'rfid', 'on_time'),
(20, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'morning_in', '07:08:00', 1, 'rfid', 'on_time'),
(20, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'morning_in', '07:15:00', 1, 'rfid', 'on_time'),

-- Student 30 (David Torres) - Often late
(30, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'morning_in', '08:15:00', 1, 'rfid', 'late'),
(30, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'morning_in', '07:50:00', 1, 'rfid', 'late'),
(30, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'morning_in', '08:30:00', 1, 'rfid', 'late'),
(30, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'morning_in', '07:40:00', 1, 'rfid', 'late'),
(30, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'morning_in', '08:10:00', 1, 'rfid', 'late'),
(30, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'morning_in', '07:55:00', 1, 'rfid', 'late'),
(30, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'morning_in', '07:35:00', 1, 'rfid', 'on_time'),

-- Today's attendance records
(1, CURDATE(), 'morning_in', '07:08:00', 1, 'rfid', 'on_time'),
(2, CURDATE(), 'morning_in', '07:15:00', 1, 'rfid', 'on_time'),
(3, CURDATE(), 'morning_in', '07:32:00', 1, 'qr', 'on_time'),
(4, CURDATE(), 'morning_in', '07:25:00', 1, 'manual', 'on_time'),
(5, CURDATE(), 'morning_in', '07:45:00', 1, 'rfid', 'late'),
(6, CURDATE(), 'morning_in', '07:20:00', 1, 'rfid', 'on_time'),
(7, CURDATE(), 'morning_in', '07:38:00', 1, 'rfid', 'late'),
(8, CURDATE(), 'morning_in', '07:12:00', 1, 'rfid', 'on_time'),
(9, CURDATE(), 'morning_in', '07:28:00', 1, 'manual', 'on_time'),
(10, CURDATE(), 'morning_in', '07:40:00', 1, 'rfid', 'late'),
(1, CURDATE(), 'morning_out', '12:05:00', 1, 'rfid', 'on_time'),
(2, CURDATE(), 'morning_out', '12:10:00', 1, 'rfid', 'on_time'),
(1, CURDATE(), 'afternoon_in', '13:15:00', 1, 'rfid', 'on_time'),
(2, CURDATE(), 'afternoon_in', '13:20:00', 1, 'rfid', 'on_time'),
(1, CURDATE(), 'afternoon_out', '17:05:00', 1, 'rfid', 'on_time'),
(2, CURDATE(), 'afternoon_out', '17:10:00', 1, 'rfid', 'on_time');

-- ============================================
-- INSERT SMS HISTORY SAMPLES
-- ============================================
INSERT INTO sms_history (recipient_type, recipient_ids, message, status, sent_by, sent_count, failed_count, created_at) VALUES
('section', '1,2,3', 'Parent-Teacher Conference on Saturday at 9:00 AM. Please attend.', 'sent', 1, 45, 0, DATE_SUB(NOW(), INTERVAL 5 DAY)),
('year_level', '1,2,3,4', 'Reminder: No classes on Monday due to school event.', 'sent', 1, 120, 2, DATE_SUB(NOW(), INTERVAL 7 DAY)),
('section', '10,11', 'STEM students: Bring your project materials tomorrow.', 'sent', 1, 35, 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
('individual', '25', 'Congratulations on your academic achievement!', 'sent', 1, 1, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('section', '12,13', 'Graduation practice at the auditorium at 3:00 PM.', 'sent', 1, 28, 0, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- ============================================
-- CREATE INDEXES FOR PERFORMANCE
-- ============================================
CREATE INDEX idx_attendance_date ON attendance_log(log_date);
CREATE INDEX idx_attendance_student ON attendance_log(student_id);
CREATE INDEX idx_student_section ON students(section_id);
CREATE INDEX idx_section_teacher ON sections(teacher_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_password_resets_token ON password_resets(token);
CREATE INDEX idx_registration_tokens_token ON registration_tokens(token);

-- ============================================
-- CREATE VIEWS FOR REPORTS
-- ============================================

-- View for daily attendance summary
CREATE OR REPLACE VIEW v_daily_attendance AS
SELECT 
    s.id as student_id,
    s.fullname,
    s.lrn,
    sec.section_name,
    yl.level_name,
    al.log_date,
    MAX(CASE WHEN al.log_type = 'morning_in' THEN al.time_captured END) as morning_in,
    MAX(CASE WHEN al.log_type = 'morning_out' THEN al.time_captured END) as morning_out,
    MAX(CASE WHEN al.log_type = 'afternoon_in' THEN al.time_captured END) as afternoon_in,
    MAX(CASE WHEN al.log_type = 'afternoon_out' THEN al.time_captured END) as afternoon_out,
    CASE 
        WHEN MAX(CASE WHEN al.log_type IN ('morning_in', 'afternoon_in') THEN 1 ELSE 0 END) = 1 THEN 'Present'
        ELSE 'Absent'
    END as attendance_status
FROM students s
LEFT JOIN sections sec ON s.section_id = sec.id
LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
LEFT JOIN attendance_log al ON s.id = al.student_id
GROUP BY s.id, al.log_date;

-- View for student attendance percentage
CREATE OR REPLACE VIEW v_student_attendance_percentage AS
SELECT 
    s.id,
    s.fullname,
    s.lrn,
    sec.section_name,
    COUNT(DISTINCT al.log_date) as days_present,
    (SELECT COUNT(DISTINCT log_date) FROM attendance_log) as total_days,
    ROUND((COUNT(DISTINCT al.log_date) / NULLIF((SELECT COUNT(DISTINCT log_date) FROM attendance_log), 0)) * 100, 1) as attendance_percentage
FROM students s
LEFT JOIN sections sec ON s.section_id = sec.id
LEFT JOIN attendance_log al ON s.id = al.student_id
GROUP BY s.id;

-- ============================================
-- DISPLAY SUMMARY
-- ============================================
SELECT '=== DATABASE SETUP COMPLETE ===' as Status;
SELECT CONCAT('Total Users: ', COUNT(*)) as Info FROM users;
SELECT CONCAT('Total Teachers: ', COUNT(*)) as Info FROM teachers;
SELECT CONCAT('Total Students: ', COUNT(*)) as Info FROM students;
SELECT CONCAT('Total Sections: ', COUNT(*)) as Info FROM sections;
SELECT CONCAT('Total Attendance Records: ', COUNT(*)) as Info FROM attendance_log;
SELECT CONCAT('Total SMS Records: ', COUNT(*)) as Info FROM sms_history;

SELECT '===================================' as '';
SELECT 'Registration code for all users: account2026' as RegistrationCodeInfo;
SELECT 'Admin will approve new teacher/student registrations' as AccountApprovalInfo;
SELECT 'Default admin login: admin / password123 (after reset_passwords.php)' as AdminLoginInfo;
SELECT 'Run reset_passwords.php to hash all passwords' as NextStep;