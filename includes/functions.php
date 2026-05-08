<?php
require_once 'config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Input sanitization
function sanitize($data)
{
    global $conn;
    return mysqli_real_escape_string($conn, trim(htmlspecialchars($data)));
}

// Password hashing
function hashPassword($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

// Verify password
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

// Helper function to create references for bind_param
function refValues($arr)
{
    $refs = array();
    foreach ($arr as $key => $value) {
        $refs[$key] = &$arr[$key];
    }
    return $refs;
}

// Safe query with prepared statements
function safeQuery($sql, $params = [], $types = '')
{
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('MySQL prepare failed: ' . mysqli_error($conn) . ' | SQL: ' . $sql);
        return false;
    }

    if (!empty($params)) {
        // Automatically determine types if not provided
        if (empty($types)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param) || is_double($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 's';
                }
            }
        }

        // Ensure types string length matches params count
        if (strlen($types) !== count($params)) {
            error_log('Types string length (' . strlen($types) . ') does not match params count (' . count($params) . ') | SQL: ' . $sql);
            mysqli_stmt_close($stmt);
            return false;
        }

        // Build bind parameters array correctly
        $bind_params = array($types);
        foreach ($params as $key => $value) {
            $bind_params[] = $value;
        }

        // Use call_user_func_array with reference for proper binding
        if (!call_user_func_array(array($stmt, 'bind_param'), refValues($bind_params))) {
            error_log('MySQL bind failed: ' . mysqli_stmt_error($stmt) . ' | SQL: ' . $sql);
            mysqli_stmt_close($stmt);
            return false;
        }
    }

    if (!mysqli_stmt_execute($stmt)) {
        error_log('MySQL execute failed: ' . mysqli_stmt_error($stmt) . ' | SQL: ' . $sql);
        mysqli_stmt_close($stmt);
        return false;
    }

    // For INSERT with AUTO_INCREMENT, get the insert ID
    $insert_id = mysqli_insert_id($conn);

    // SELECT/SHOW style queries return a result object.
    $result = mysqli_stmt_get_result($stmt);

    // Close statement
    mysqli_stmt_close($stmt);

    if ($result !== false) {
        return $result;
    }

    // INSERT/UPDATE/DELETE style queries - return insert ID or true
    if ($insert_id > 0) {
        return $insert_id;
    }
    return true;
}

// Check if user is logged in
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// Check role
function hasRole($role)
{
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Require login
function requireLogin()
{
    if (!isLoggedIn()) {
        // Preserve session_id when redirecting to login
        $session_param = '';
        if (isset($_GET['session_id'])) {
            $session_param = '?session_id=' . urlencode($_GET['session_id']);
        } elseif (isset($_SESSION['session_id'])) {
            $session_param = '?session_id=' . urlencode($_SESSION['session_id']);
        }
        header('Location: ' . SITE_URL . 'index.php' . $session_param);
        exit();
    }
}

// Require specific role
function requireRole($role)
{
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ' . SITE_URL . 'index.php');
        exit();
    }
}

// Generate QR Code using Google Charts API (No GD Library needed)
function generateQRCode($data, $filename)
{
    $path = UPLOAD_PATH . 'qrcodes/';

    // Create directory if it doesn't exist
    if (!is_dir($path)) {
        if (!mkdir($path, 0777, true)) {
            error_log("Failed to create QR code directory: " . $path);
            return 'qrcodes/default_qr.png';
        }
    }

    $filepath = $path . $filename . '.png';

    // Encode the data for URL
    $encodedData = urlencode($data);

    // Helper: accept only valid image bytes (prevents saving HTML/error responses as .png)
    $isValidImageContent = function ($content) {
        if (!is_string($content) || $content === '') {
            return false;
        }
        return @getimagesizefromstring($content) !== false;
    };

    $downloadFromUrl = function ($url) use ($isValidImageContent) {
        // 1) Basic file_get_contents.
        if (ini_get('allow_url_fopen')) {
            $qrContent = @file_get_contents($url);
            if ($isValidImageContent($qrContent)) {
                return $qrContent;
            }
        }

        // 2) file_get_contents with SSL verification disabled (common local XAMPP issue).
        if (ini_get('allow_url_fopen')) {
            $streamContext = stream_context_create([
                'http' => [
                    'timeout' => 20,
                    'header' => "User-Agent: SmartAttendance/1.0\r\n"
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            $qrContent = @file_get_contents($url, false, $streamContext);
            if ($isValidImageContent($qrContent)) {
                return $qrContent;
            }
        }

        // 3) cURL default.
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'SmartAttendance/1.0');
            $qrContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 200 && $isValidImageContent($qrContent)) {
                return $qrContent;
            }
        }

        // 4) cURL with SSL verification disabled.
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'SmartAttendance/1.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $qrContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 200 && $isValidImageContent($qrContent)) {
                return $qrContent;
            }
        }

        return false;
    };

    // Try multiple providers for better reliability.
    $qrSources = [
        "https://quickchart.io/qr?text={$encodedData}&size=300",
        "https://quickchart.io/chart?cht=qr&chs=300x300&chl={$encodedData}",
        "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encodedData}",
        "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl={$encodedData}&choe=UTF-8",
    ];

    foreach ($qrSources as $sourceUrl) {
        $qrContent = $downloadFromUrl($sourceUrl);
        if ($qrContent !== false) {
            file_put_contents($filepath, $qrContent);
            return 'qrcodes/' . $filename . '.png';
        }
    }

    // If all QR generation methods fail, create a text-based placeholder
    error_log("All QR code generation methods failed for data: " . $data);
    return createTextQRPlaceholder($filename, $data);
}

// Create a text fallback when QR generation fails.
// We intentionally avoid generating a fake QR-looking PNG because it is not machine scannable.
function createTextQRPlaceholder($filename, $data)
{
    $path = UPLOAD_PATH . 'qrcodes/';
    $txtPath = $path . $filename . '.txt';
    file_put_contents($txtPath, $data);
    return 'qrcodes/' . $filename . '.txt';
}

function normalizeQrFilepath($qrPath)
{
    $qrPath = trim((string)$qrPath);
    if ($qrPath === '') {
        return '';
    }

    $qrPath = str_replace('\\', '/', $qrPath);
    $qrPath = ltrim($qrPath, '/');

    if (str_starts_with(strtolower($qrPath), 'uploads/')) {
        $qrPath = substr($qrPath, 8);
    }

    if (!str_starts_with(strtolower($qrPath), 'qrcodes/')) {
        $qrPath = 'qrcodes/' . basename($qrPath);
    }

    return $qrPath;
}

function isScannableQrImagePath($qrPath)
{
    $normalizedPath = normalizeQrFilepath($qrPath);
    if ($normalizedPath === '') {
        return false;
    }

    return preg_match('/^qrcodes\/.+\.(png|jpg|jpeg|gif|webp)$/i', $normalizedPath) === 1;
}

function buildStudentQrData($studentId)
{
    return (string)((int)$studentId);
}

function buildStudentQrCodeValue($studentId, $qrPath = '')
{
    $qrData = buildStudentQrData($studentId);

    if (isScannableQrImagePath($qrPath)) {
        $normalizedPath = normalizeQrFilepath($qrPath);
        return $qrData . '|' . $normalizedPath;
    }

    // Fallback to data-only value so frontends can still render a scannable QR dynamically.
    return $qrData;
}

// Display QR code (handles both PNG and TXT files)
function displayQRCode($qrPath)
{
    $fullPath = UPLOAD_PATH . $qrPath;

    if (!file_exists($fullPath)) {
        return '<div class="text-center text-muted">QR Code not available</div>';
    }

    $ext = pathinfo($fullPath, PATHINFO_EXTENSION);

    if ($ext == 'png') {
        return '<img src="../uploads/' . $qrPath . '" alt="QR Code" style="max-width: 200px;">';
    } elseif ($ext == 'txt') {
        $data = file_get_contents($fullPath);
        // Generate on-the-fly QR using API
        $qrUrl = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=" . urlencode($data);
        return '<img src="' . $qrUrl . '" alt="QR Code">';
    }

    return '<div class="text-center text-muted">QR Code not available</div>';
}

// Upload file
function uploadFile($file, $targetDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'])
{
    // Create directory if it doesn't exist
    $fullPath = UPLOAD_PATH . $targetDir . '/';
    if (!is_dir($fullPath)) {
        if (!mkdir($fullPath, 0777, true)) {
            error_log("Failed to create directory: " . $fullPath);
            return false;
        }
    }

    $fileName = time() . '_' . basename($file['name']);
    $targetPath = $fullPath . $fileName;

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        return false;
    }

    // Check file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $targetDir . '/' . $fileName;
    }

    error_log("Failed to move uploaded file. Source: " . $file['tmp_name'] . " Target: " . $targetPath);
    return false;
}

// Get user name by ID
function getUserName($user_id)
{
    $result = safeQuery("SELECT username FROM users WHERE id = ?", [$user_id], 'i');
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['username'];
    }
    return 'Unknown';
}

// Format time for display
function formatTime($time)
{
    if ($time) {
        return date('h:i A', strtotime($time));
    }
    return '-';
}

// Function to check if email exists
function emailExists($email)
{
    $result = safeQuery("SELECT id FROM users WHERE email = ?", [$email], 's');
    return ($result && mysqli_num_rows($result) > 0);
}

// Function to check if username exists
function usernameExists($username)
{
    $result = safeQuery("SELECT id FROM users WHERE username = ?", [$username], 's');
    return ($result && mysqli_num_rows($result) > 0);
}

// ============================================
// SMART EMAIL FUNCTIONS
// ============================================

/**
 * Send email with automatic fallback to logging if SMTP fails
 * This works on any device regardless of mail configuration
 */
function sendSmartEmail($to, $subject, $body, $altBody = '')
{
    
    $mail = new PHPMailer(true);
    $logDir = dirname(__DIR__) . '/logs';
    
    // Create logs directory if not exists
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    // If SMTP is disabled, just log the email (recommended for multi-device deployments)
    if (!defined('ENABLE_SMTP_EMAIL') || !ENABLE_SMTP_EMAIL) {
        $logMessage = "========================================\n";
        $logMessage .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "To: $to\n";
        $logMessage .= "Subject: $subject\n";
        $logMessage .= "Body:\n$body\n";
        $logMessage .= "========================================\n\n";
        
        $logFile = $logDir . '/emails.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        // Also save as HTML file for easy viewing
        $htmlFile = $logDir . '/email_' . date('Ymd_His') . '.html';
        file_put_contents($htmlFile, $body);
        
        error_log("Email logged (SMTP disabled): $to - Subject: $subject");
        
        return [
            'success' => true, 
            'message' => 'Email logged successfully', 
            'method' => 'logged',
            'logged' => true,
            'log_file' => 'logs/email_' . date('Ymd_His') . '.html'
        ];
    }
    
    // Otherwise, try to send via SMTP
    try {
        // Configure based on environment
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->Port = MAIL_PORT;
        $mail->Timeout = 30;
        
        // Configure authentication based on settings
        if (defined('MAIL_SMTP_AUTH') && MAIL_SMTP_AUTH === true && !empty(MAIL_USERNAME)) {
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            
            if (defined('MAIL_SMTP_SECURE') && !empty(MAIL_SMTP_SECURE)) {
                $mail->SMTPSecure = MAIL_SMTP_SECURE;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
        } else {
            $mail->SMTPAuth = false;
            // For local SMTP servers like Papercut, disable TLS
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = false;
        }
        
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        if (!empty($altBody)) {
            $mail->AltBody = $altBody;
        }
        
        $mail->send();
        
        // Log successful email
        error_log("Email sent successfully to: $to - Subject: $subject");
        
        return ['success' => true, 'message' => 'Email sent', 'method' => 'smtp'];
        
    } catch (Exception $e) {
        // If email fails, save to log file instead (fallback)
        $logMessage = "========================================\n";
        $logMessage .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "To: $to\n";
        $logMessage .= "Subject: $subject\n";
        $logMessage .= "Error: " . $mail->ErrorInfo . "\n";
        $logMessage .= "SMTP Host: " . MAIL_HOST . "\n";
        $logMessage .= "SMTP Port: " . MAIL_PORT . "\n";
        $logMessage .= "Body:\n$body\n";
        $logMessage .= "========================================\n\n";
        
        $logFile = $logDir . '/email_failures.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        // Also save as HTML file for easy viewing
        $htmlFile = $logDir . '/email_' . date('Ymd_His') . '.html';
        file_put_contents($htmlFile, $body);
        
        error_log("Email failed to: $to - Error: " . $mail->ErrorInfo . " - Saved to logs");
        
        return [
            'success' => true,
            'message' => 'Email logged (SMTP failed, fallback logging active)', 
            'error' => $mail->ErrorInfo,
            'method' => 'fallback',
            'logged' => true,
            'log_file' => 'logs/email_' . date('Ymd_His') . '.html'
        ];
    }
}

/**
 * Test SMTP connection and return status
 */
function testSMTPConnection()
{
    $result = [
        'success' => false,
        'message' => '',
        'details' => []
    ];
    
    // Test 1: Check if we can connect to SMTP port
    $connection = @fsockopen(MAIL_HOST, MAIL_PORT, $errno, $errstr, 2);
    if ($connection) {
        $result['details'][] = "✅ Port " . MAIL_PORT . " is open on " . MAIL_HOST;
        fclose($connection);
        $result['success'] = true;
        $result['message'] = "SMTP server is reachable";
    } else {
        $result['details'][] = "❌ Port " . MAIL_PORT . " is NOT reachable on " . MAIL_HOST . " - Error: $errstr";
        $result['message'] = "SMTP server is NOT reachable. Using fallback logging mode.";
    }
    
    // Test 2: Show current configuration
    $result['details'][] = "MAIL_HOST: " . MAIL_HOST;
    $result['details'][] = "MAIL_PORT: " . MAIL_PORT;
    $result['details'][] = "MAIL_SMTP_AUTH: " . (defined('MAIL_SMTP_AUTH') ? (MAIL_SMTP_AUTH ? 'Yes' : 'No') : 'Not set');
    $result['details'][] = "MAIL_USERNAME: " . (defined('MAIL_USERNAME') && !empty(MAIL_USERNAME) ? substr(MAIL_USERNAME, 0, 5) . '***' : 'Not set');
    
    return $result;
}

/**
 * Get email logs for admin viewing
 */
function getEmailLogs($limit = 50)
{
    $logDir = dirname(__DIR__) . '/logs';
    $logFile = $logDir . '/email_failures.log';
    $logs = [];
    
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $entries = explode('========================================', $content);
        $entries = array_reverse($entries); // Show newest first
        
        $count = 0;
        foreach ($entries as $entry) {
            if ($count >= $limit) break;
            if (trim($entry) != '') {
                $logs[] = $entry;
                $count++;
            }
        }
    }
    
    return $logs;
}

/**
 * Clear email logs
 */
function clearEmailLogs()
{
    $logDir = dirname(__DIR__) . '/logs';
    $logFile = $logDir . '/email_failures.log';
    
    if (file_exists($logFile)) {
        unlink($logFile);
        return true;
    }
    return false;
}
?>
