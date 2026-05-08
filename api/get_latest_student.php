<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$sql = "SELECT s.*, sec.section_name, t.fullname as teacher_name 
        FROM attendance_log al
        JOIN students s ON al.student_id = s.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN teachers t ON sec.teacher_id = t.id
        ORDER BY al.created_at DESC
        LIMIT 1";

$result = safeQuery($sql, [], '');

if($student = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'student' => $student]);
} else {
    echo json_encode(['success' => false, 'message' => 'No attendance records found']);
}
?>