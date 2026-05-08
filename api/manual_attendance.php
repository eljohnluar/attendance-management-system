<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$student_id = (int)$_POST['student_id'];
$log_type = $_POST['log_type'];
$time = $_POST['time'];
$date = $_POST['date'];

// Check if already exists
$check = safeQuery("SELECT id FROM attendance_log WHERE student_id = ? AND log_date = ? AND log_type = ?", 
                  [$student_id, $date, $log_type], 'iss');

if(mysqli_num_rows($check) > 0) {
    echo json_encode(['success' => false, 'message' => 'Attendance already recorded for this period']);
    exit();
}

$sql = "INSERT INTO attendance_log (student_id, log_date, log_type, time_captured, captured_by, method, status) 
        VALUES (?, ?, ?, ?, ?, 'manual', 'on_time')";
$result = safeQuery($sql, [$student_id, $date, $log_type, $time, $_SESSION['user_id']], 'isssi');

if($result) {
    echo json_encode(['success' => true, 'message' => 'Attendance recorded manually']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record attendance']);
}
?>