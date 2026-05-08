<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['sent_today' => 0, 'failed_today' => 0]);
    exit();
}

// Get teacher ID
$teacher_sql = "SELECT id FROM teachers WHERE user_id = ?";
$teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];

$sent_today = mysqli_fetch_assoc(safeQuery(
    "SELECT COUNT(*) as count FROM sms_history 
     WHERE status = 'sent' AND DATE(created_at) = CURDATE() AND sent_by = ?",
    [$teacher_id], 'i'
))['count'];

$failed_today = mysqli_fetch_assoc(safeQuery(
    "SELECT COUNT(*) as count FROM sms_history 
     WHERE status = 'failed' AND DATE(created_at) = CURDATE() AND sent_by = ?",
    [$teacher_id], 'i'
))['count'];

echo json_encode([
    'sent_today' => $sent_today,
    'failed_today' => $failed_today
]);
?>