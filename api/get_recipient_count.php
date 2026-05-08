<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$type = $_POST['type'] ?? '';
$recipients = $_POST['recipients'] ?? [];

$count = 0;

if($type == 'grade_checklist' && !empty($recipients)) {
    $placeholders = implode(',', array_fill(0, count($recipients), '?'));
    $sql = "SELECT COUNT(DISTINCT s.id) as count 
            FROM students s
            JOIN sections sec ON s.section_id = sec.id
            WHERE sec.year_level_id IN ($placeholders) 
            AND s.status = 'active'
            AND s.parent_contact IS NOT NULL 
            AND s.parent_contact != ''";
    $result = safeQuery($sql, $recipients, str_repeat('i', count($recipients)));
    $row = mysqli_fetch_assoc($result);
    $count = $row['count'];
}

echo json_encode(['count' => $count]);
?>