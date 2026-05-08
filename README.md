# Smart Attendance System

A comprehensive attendance management system with QR code scanning, RFID support, and SMS notifications.

## Features

- **QR Code & RFID Scanning** - Fast contactless attendance tracking
- **Real-time Monitoring** - Live attendance display
- **SMS Notifications** - Direct parent communication
- **Comprehensive Reports** - Exportable attendance reports
- **Role-based Access** - Admin and Teacher panels
- **Mobile Responsive** - Works on all devices

## Tech Stack

- PHP 7.4+
- MySQL
- HTML5/CSS3
- JavaScript/jQuery
- Bootstrap

## Installation

1. Clone the repository to your web server:

```bash
git clone https://github.com/yourusername/smart-attendance.git
Import the database:

Open phpMyAdmin

Create database smart_attendance

Import sql/database.sql

Configure database connection:

Edit includes/config.php

Update database credentials

Set up upload directories:

bash
mkdir uploads/students uploads/teachers uploads/qrcodes
chmod 755 uploads
Run password reset script:

Access http://localhost/smart-attendance/reset_passwords.php

Default Login Credentials
Role	Username	Password	Auth Code
Admin	admin	password123	001690
Teacher	teacher_jreyes	password123	001690
Folder Structure
text
smart-attendance/
├── admin/          # Admin panel pages
├── teacher/        # Teacher panel pages
├── api/           # API endpoints
├── includes/      # Core functions
├── assets/        # CSS, JS, images
├── uploads/       # Student/teacher photos, QR codes
└── sql/           # Database setup
Quick Start
Login as Admin: http://localhost/smart-attendance/

Add students and generate QR codes

Scan QR codes using the scanner page

View attendance reports

Requirements
XAMPP/WAMP/LAMP

PHP GD Library (for QR codes)

allow_url_fopen enabled (for Google Charts API)

License
MIT
```
