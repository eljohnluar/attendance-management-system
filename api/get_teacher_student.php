<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$search = $_POST['search'] ?? '';
$teacher_id = (int)$_POST['teacher_id'] ?? 0;

$sql = "SELECT s.*, sec.section_name 
        FROM students s
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE sec.teacher_id = ? 
        AND (s.fullname LIKE ? OR s.lrn LIKE ? OR s.rfid_uid LIKE ?)
        AND s.status = 'active'
        LIMIT 1";

$result = safeQuery($sql, [$teacher_id, "%$search%", "%$search%", "%$search%"], 'isss');

if($student = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true] + $student);
} else {
    echo json_encode(['success' => false]);
}
?>