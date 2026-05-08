<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

$sql = "SELECT s.*, sec.section_name, t.fullname as teacher_name 
        FROM attendance_log al
        JOIN students s ON al.student_id = s.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN teachers t ON sec.teacher_id = t.id
        WHERE sec.teacher_id = ?
        ORDER BY al.created_at DESC
        LIMIT 1";

$result = safeQuery($sql, [$teacher_id], 'i');

if($student = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'student' => $student]);
} else {
    echo json_encode(['success' => false, 'message' => 'No attendance records found']);
}
?>