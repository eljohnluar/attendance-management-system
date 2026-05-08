<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get teacher ID from session
$teacher_sql = "SELECT id FROM teachers WHERE user_id = ?";
$teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
$teacher = mysqli_fetch_assoc($teacher_result);

if(!$teacher) {
    echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    exit();
}

$teacher_id = $teacher['id'];

// Get statistics for teacher
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

$total_sent = mysqli_fetch_assoc(safeQuery(
    "SELECT SUM(sent_count) as total FROM sms_history WHERE sent_by = ?",
    [$teacher_id], 'i'
))['total'];

echo json_encode([
    'sent_today' => intval($sent_today),
    'failed_today' => intval($failed_today),
    'total_sent' => intval($total_sent) ?: 0
]);
?>
