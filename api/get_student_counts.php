<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

// Get teacher ID
$teacher_sql = "SELECT id FROM teachers WHERE user_id = ?";
$teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];

// Get student counts by year level for this teacher
$sql = "SELECT yl.id as level_id, COUNT(DISTINCT s.id) as student_count
        FROM year_levels yl
        LEFT JOIN sections sec ON sec.year_level_id = yl.id
        LEFT JOIN students s ON s.section_id = sec.id AND s.status = 'active'
        WHERE sec.teacher_id = ?
        GROUP BY yl.id";

$result = safeQuery($sql, [$teacher_id], 'i');
$counts = [];
while($row = mysqli_fetch_assoc($result)) {
    $counts[$row['level_id']] = $row['student_count'];
}

echo json_encode($counts);
?>