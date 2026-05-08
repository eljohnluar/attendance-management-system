<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/attendance_debug.log');

// Check if user is logged in, but allow guest scans
$is_logged_in = isset($_SESSION['user_id']);
$captured_by = $is_logged_in ? $_SESSION['user_id'] : null;

$raw_identifier = trim((string)($_POST['identifier'] ?? ''));
$method = $_POST['method'] ?? 'manual';

// Normalize scanned payloads into a stable lookup value.
$identifier = str_replace(["\r", "\n"], '', $raw_identifier);
$identifier = preg_replace('/^\xEF\xBB\xBF/', '', $identifier);
$identifier = trim($identifier);
$normalization_steps = [];

// Support payloads like "35|qrcodes/qr_student_35.png".
if (strpos($identifier, '|') !== false) {
    $parts = explode('|', $identifier, 2);
    $primary_value = trim($parts[0]);
    if ($primary_value !== '') {
        $identifier = $primary_value;
        $normalization_steps[] = 'pipe_prefix';
    }
}

// Support payloads containing only image path, like uploads/qrcodes/qr_student_35.png.
$identifier_path = str_replace('\\', '/', $identifier);
if (preg_match('/(?:^|\/)qr_student_(\d+)\.(?:png|jpg|jpeg|gif|webp|txt)$/i', $identifier_path, $matches)) {
    $identifier = $matches[1];
    $normalization_steps[] = 'filename_student_id';
}

// Support full URL payloads by extracting the path (and student id if present in filename).
if (preg_match('/^https?:\/\//i', $identifier_path)) {
    $url_path = parse_url($identifier_path, PHP_URL_PATH);
    if (is_string($url_path) && $url_path !== '') {
        $url_path = ltrim(str_replace('\\', '/', $url_path), '/');
        if (preg_match('/(?:^|\/)qr_student_(\d+)\.(?:png|jpg|jpeg|gif|webp|txt)$/i', $url_path, $url_matches)) {
            $identifier = $url_matches[1];
            $normalization_steps[] = 'url_filename_student_id';
        } else {
            $identifier = $url_path;
            $normalization_steps[] = 'url_path';
        }
    }
}

// Log received data
error_log("=== ATTENDANCE DEBUG ===");
error_log("Identifier received (raw): '" . $raw_identifier . "'");
error_log("Identifier used (normalized): '" . $identifier . "'");
error_log("Identifier length: " . strlen($raw_identifier));
error_log("Normalization steps: " . (empty($normalization_steps) ? 'none' : implode(', ', $normalization_steps)));
error_log("Method: " . $method);
error_log("Captured by: " . $captured_by);

// First, let's check all students to see what's in the database
$all_students = safeQuery("SELECT id, fullname, lrn, rfid_uid, qr_code FROM students LIMIT 5", [], '');
error_log("=== DATABASE STUDENTS SAMPLE ===");
if($all_students) {
    while($row = mysqli_fetch_assoc($all_students)) {
        error_log("Student ID: {$row['id']}, Name: {$row['fullname']}, QR: " . substr($row['qr_code'] ?? 'NULL', 0, 50));
    }
}

// Search for student with multiple approaches
$student = null;
$search_method = '';

// Approach 1: By ID
if(!$student && is_numeric($identifier)) {
    $result = safeQuery("SELECT s.*, sec.section_name, yl.level_name 
                        FROM students s 
                        LEFT JOIN sections sec ON s.section_id = sec.id
                        LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                        WHERE s.id = ?", [$identifier], 'i');
    if($result && mysqli_num_rows($result) > 0) {
        $student = mysqli_fetch_assoc($result);
        $search_method = 'by_id';
        error_log("Found by ID: " . $student['fullname']);
    }
}

// Approach 2: By RFID
if(!$student) {
    $result = safeQuery("SELECT s.*, sec.section_name, yl.level_name 
                        FROM students s 
                        LEFT JOIN sections sec ON s.section_id = sec.id
                        LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                        WHERE s.rfid_uid = ?", [$identifier], 's');
    if($result && mysqli_num_rows($result) > 0) {
        $student = mysqli_fetch_assoc($result);
        $search_method = 'by_rfid';
        error_log("Found by RFID: " . $student['fullname']);
    }
}

// Approach 3: By LRN
if(!$student) {
    $result = safeQuery("SELECT s.*, sec.section_name, yl.level_name 
                        FROM students s 
                        LEFT JOIN sections sec ON s.section_id = sec.id
                        LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                        WHERE s.lrn = ?", [$identifier], 's');
    if($result && mysqli_num_rows($result) > 0) {
        $student = mysqli_fetch_assoc($result);
        $search_method = 'by_lrn';
        error_log("Found by LRN: " . $student['fullname']);
    }
}

// Approach 4: By QR code exact match
if(!$student) {
    $result = safeQuery("SELECT s.*, sec.section_name, yl.level_name 
                        FROM students s 
                        LEFT JOIN sections sec ON s.section_id = sec.id
                        LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                        WHERE s.qr_code = ?", [$identifier], 's');
    if($result && mysqli_num_rows($result) > 0) {
        $student = mysqli_fetch_assoc($result);
        $search_method = 'by_qr_exact';
        error_log("Found by QR exact: " . $student['fullname']);
    }
}

// Approach 5: By QR code LIKE (data|path)
if(!$student) {
    $result = safeQuery("SELECT s.*, sec.section_name, yl.level_name 
                        FROM students s 
                        LEFT JOIN sections sec ON s.section_id = sec.id
                        LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                        WHERE s.qr_code LIKE CONCAT(?, '|%')", [$identifier], 's');
    if($result && mysqli_num_rows($result) > 0) {
        $student = mysqli_fetch_assoc($result);
        $search_method = 'by_qr_like';
        error_log("Found by QR LIKE: " . $student['fullname']);
    }
}

// Approach 6: By QR code SUBSTRING_INDEX
if(!$student) {
    $result = safeQuery("SELECT s.*, sec.section_name, yl.level_name 
                        FROM students s 
                        LEFT JOIN sections sec ON s.section_id = sec.id
                        LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                        WHERE SUBSTRING_INDEX(s.qr_code, '|', 1) = ?", [$identifier], 's');
    if($result && mysqli_num_rows($result) > 0) {
        $student = mysqli_fetch_assoc($result);
        $search_method = 'by_qr_substring';
        error_log("Found by QR SUBSTRING: " . $student['fullname']);
    }
}

if($student) {
    error_log("Student found via {$search_method}: ID={$student['id']}, Name={$student['fullname']}");
    
    $current_time = date('H:i:s');
    $current_date = date('Y-m-d');
    $period = '';
    
    error_log("Current time: $current_time, Date: $current_date");
    
    // Determine log period based on time
    if($current_time >= '06:00:00' && $current_time < '12:00:00') {
        $period = 'morning_in';
    } elseif($current_time >= '12:00:00' && $current_time < '13:00:00') {
        $period = 'morning_out';
    } elseif($current_time >= '13:00:00' && $current_time < '17:00:00') {
        $period = 'afternoon_in';
    } elseif($current_time >= '17:00:00' && $current_time < '23:00:00') {
        $period = 'afternoon_out';
    }
    
    error_log("Determined period: " . ($period ?: 'none'));
    
    if($period) {
        // Check if already recorded for this period
        $check = safeQuery("SELECT id FROM attendance_log WHERE student_id = ? AND log_date = ? AND log_type = ?", 
                          [$student['id'], $current_date, $period], 'iss');
        
        $check_result = $check ? mysqli_num_rows($check) : 0;
        error_log("Already recorded for this period? " . ($check_result ? "YES" : "NO"));
        
        if($check_result == 0) {
            // Determine if late (morning in after 7:30 AM)
            $status = ($period === 'morning_in' && $current_time > '07:30:00') ? 'late' : 'on_time';
            error_log("Status: $status");
            
            // Insert attendance record
            $insert = safeQuery("INSERT INTO attendance_log (student_id, log_date, log_type, time_captured, captured_by, method, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)",
                               [$student['id'], $current_date, $period, $current_time, $captured_by, $method, $status], 
                               'isssiss');
            
            if($insert) {
                error_log("SUCCESS: Attendance inserted successfully!");
                $guest_msg = $is_logged_in ? '' : ' (Guest scan)';
                echo json_encode([
                    'success' => true, 
                    'message' => "✓ Recorded: {$student['fullname']} - " . ucfirst(str_replace('_', ' ', $period)) . $guest_msg
                ]);
                exit();
            } else {
                error_log("ERROR: Failed to insert attendance record");
                echo json_encode(['success' => false, 'message' => 'Failed to record attendance']);
                exit();
            }
        } else {
            error_log("ERROR: Already recorded for this period");
            echo json_encode(['success' => false, 'message' => 'Already recorded for this period']);
            exit();
        }
    } else {
        error_log("ERROR: Outside attendance hours");
        echo json_encode(['success' => false, 'message' => 'Outside attendance hours (6:00 AM - 11:00 PM)']);
        exit();
    }
} else {
    error_log("ERROR: Student not found for identifier: '$identifier'");
    echo json_encode(['success' => false, 'message' => 'Student not found. Please check your QR code or RFID.']);
    exit();
}
?>
