# Attendance Management System

A comprehensive school attendance management system with QR code and RFID support - developed as a school project.

## About The Project

This system was developed as a school project to modernize attendance tracking in educational institutions. It replaces traditional paper-based attendance with a digital solution using QR codes and RFID technology.

## Features

- **Multi-role System** - Admin, Teacher, and Student portals with role-based access
- **QR Code & RFID Scanning** - Fast contactless attendance marking
- **Real-time Monitoring** - Live attendance tracking display for classrooms
- **SMS Broadcasting** - Send instant notifications to parents/guardians
- **Reports & Analytics** - Generate attendance reports with interactive charts
- **Student Management** - Manage student records and optional login accounts
- **Email Notifications** - (Optional) Email alerts for login and verification

## Tech Stack

| Technology | Purpose |
|------------|---------|
| PHP 7.4+ | Backend logic |
| MySQL/MariaDB | Database |
| HTML5/CSS3/JavaScript | Frontend |
| PHPMailer | Email sending (optional) |
| html5-qrcode | QR code scanning |
| Chart.js | Analytics charts |

## Installation

# 1. Clone the repository
git clone https://github.com/eljhn/attendance-management-system.git

# 2. Import database
mysql -u root -p < sql/database.sql

# 3. Configure database in includes/config.php
# Update DB_HOST, DB_USER, DB_PASS, DB_NAME

# 4. Run setup script (then DELETE it!)
http://localhost/attendance-management-system/reset_passwords.php
Default Login Credentials
Role	Username	Password
Admin	admin	password123
Teacher	teacher_jreyes	password123
Student	alice.mendoza	password123
Registration Code: account2026 (for teacher/student registration)

# Project Structure

smart_attendance_system/

├── admin/          # Admin panel (accounts, reports, SMS)

├── teacher/        # Teacher panel (students, attendance, reports)

├── student/        # Student panel (profile, QR, schedule)

├── api/            # AJAX endpoints for scanner and data

├── includes/       # Core configuration and functions

├── assets/         # CSS stylesheets and images

├── sql/            # Database schema

└── uploads/        # User uploaded photos and QR codes

# Key Functionalities by Role
Role	Capabilities
Admin	Full system control, manage users, approve accounts, send broadcasts, view all reports
Teacher	Manage assigned students, take attendance, view class reports, send SMS to parents
Student	View personal attendance, download QR code, update profile, view schedule

# System Preview
Login Page - Role-based login (Admin/Teacher/Student)

Dashboard - Statistics and charts for each role

QR Scanner - Public page for attendance marking via QR/RFID

Monitor Display - Real-time attendance viewing for classrooms

# Future Improvements
Mobile application for students and teachers

Biometric (fingerprint) attendance option

Export reports to PDF/Excel

Parent portal for monitoring child's attendance
