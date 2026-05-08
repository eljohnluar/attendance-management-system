<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['count' => 0]);
    exit();
}

// Get teacher ID
$teacher_sql = "SELECT id FROM teachers WHERE user_id = ?";
$teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];

$type = $_POST['type'] ?? '';
$recipients = $_POST['recipients'] ?? [];

$count = 0;

if($type == 'grade_checklist' && !empty($recipients)) {
    $placeholders = implode(',', array_fill(0, count($recipients), '?'));
    $sql = "SELECT COUNT(DISTINCT s.id) as count 
            FROM students s
            JOIN sections sec ON s.section_id = sec.id
            WHERE sec.year_level_id IN ($placeholders) 
            AND sec.teacher_id = ?
            AND s.status = 'active'
            AND s.parent_contact IS NOT NULL 
            AND s.parent_contact != ''";
    $params = array_merge($recipients, [$teacher_id]);
    $result = safeQuery($sql, $params, str_repeat('i', count($recipients)) . 'i');
    $row = mysqli_fetch_assoc($result);
    $count = $row['count'];
}

echo json_encode(['count' => $count]);
?>