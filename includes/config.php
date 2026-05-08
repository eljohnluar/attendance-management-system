<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

if (ob_get_level() === 0) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    $xampp_tmp = 'C:\\xampp\\tmp';
    if (is_dir($xampp_tmp) && is_writable($xampp_tmp)) {
        ini_set('session.save_path', $xampp_tmp);
    }

    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smart_attendance');

date_default_timezone_set('Asia/Manila');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Build paths from current project directory
$project_root = dirname(__DIR__);
$project_name = basename($project_root);

define('SITE_URL', 'http://localhost/' . $project_name . '/');
define('UPLOAD_PATH', $project_root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);

// ============================================
// SMTP CONFIGURATION
// ============================================
// Set to true to enable actual email sending via SMTP
// Set to false to just log emails to files (recommended for multi-device deployments)
define('ENABLE_SMTP_EMAIL', true);

// Manual SMTP Configuration (edit these if ENABLE_SMTP_EMAIL is true)
define('SMTP_CONFIG', [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'auth' => true,
    'username' => 'rebanoej@gmail.com',  // Your Gmail/SMTP username
    'password' => 'txoi veds xnsr auhi',  // Your Gmail/SMTP password or App Password
    'from_email' => 'noreply@smartattendance.local',
    'from_name' => 'Smart Attendance System',
    'secure' => PHPMailer::ENCRYPTION_STARTTLS
]);

// Auto-detect local SMTP servers only on localhost (Papercut, MailHog, FakeSMTP)
$use_local_smtp = false;
if (!ENABLE_SMTP_EMAIL && ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1')) {
    $smtp_ports = [25, 1025, 2525, 8025, 587];
    foreach ($smtp_ports as $port) {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.3);
        if ($connection) {
            $use_local_smtp = true;
            define('MAIL_HOST', '127.0.0.1');
            define('MAIL_PORT', $port);
            define('MAIL_USERNAME', '');
            define('MAIL_PASSWORD', '');
            define('MAIL_FROM_EMAIL', 'dev@smartattendance.local');
            define('MAIL_FROM_NAME', 'Smart Attendance (DEV)');
            define('MAIL_SMTP_AUTH', false);
            define('MAIL_SMTP_SECURE', '');
            fclose($connection);
            break;
        }
    }
}

// If not using local SMTP, use the manual configuration or check environment variables
if (!$use_local_smtp) {
    if (ENABLE_SMTP_EMAIL && !empty(SMTP_CONFIG['username'])) {
        // Use manual SMTP configuration
        define('MAIL_HOST', SMTP_CONFIG['host']);
        define('MAIL_PORT', SMTP_CONFIG['port']);
        define('MAIL_USERNAME', SMTP_CONFIG['username']);
        define('MAIL_PASSWORD', SMTP_CONFIG['password']);
        define('MAIL_FROM_EMAIL', SMTP_CONFIG['from_email']);
        define('MAIL_FROM_NAME', SMTP_CONFIG['from_name']);
        define('MAIL_SMTP_AUTH', SMTP_CONFIG['auth']);
        define('MAIL_SMTP_SECURE', SMTP_CONFIG['secure']);
    } elseif (getenv('MAIL_HOST') && getenv('MAIL_USERNAME')) {
        // Use environment variables (production)
        define('MAIL_HOST', getenv('MAIL_HOST'));
        define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 587));
        define('MAIL_USERNAME', getenv('MAIL_USERNAME'));
        define('MAIL_PASSWORD', getenv('MAIL_PASSWORD'));
        define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: getenv('MAIL_USERNAME'));
        define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Smart Attendance');
        define('MAIL_SMTP_AUTH', true);
        define('MAIL_SMTP_SECURE', PHPMailer::ENCRYPTION_STARTTLS);
    } else {
        // Fallback: Log emails to files instead of sending (recommended for portability)
        define('MAIL_HOST', 'localhost');
        define('MAIL_PORT', 25);
        define('MAIL_USERNAME', '');
        define('MAIL_PASSWORD', '');
        define('MAIL_FROM_EMAIL', 'noreply@smartattendance.local');
        define('MAIL_FROM_NAME', 'Smart Attendance System');
        define('MAIL_SMTP_AUTH', false);
        define('MAIL_SMTP_SECURE', '');
    }
}

// Create upload directories if not exists
$directories = ['uploads', 'uploads/students', 'uploads/teachers', 'uploads/qrcodes', 'logs'];
foreach ($directories as $dir) {
    $fullPath = $project_root . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0777, true);
    }
}
?>