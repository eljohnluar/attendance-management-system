<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$sql = "SELECT 
    s.id, s.fullname, sec.section_name,
    MAX(CASE WHEN al.log_type = 'morning_in' THEN TIME(al.time_captured) END) as morning_in,
    MAX(CASE WHEN al.log_type = 'morning_out' THEN TIME(al.time_captured) END) as morning_out,
    MAX(CASE WHEN al.log_type = 'afternoon_in' THEN TIME(al.time_captured) END) as afternoon_in,
    MAX(CASE WHEN al.log_type = 'afternoon_out' THEN TIME(al.time_captured) END) as afternoon_out,
    CASE 
        WHEN MAX(CASE WHEN al.log_type IN ('morning_in', 'afternoon_in') THEN 1 ELSE 0 END) = 1 THEN 1 
        ELSE 0 
    END as present
FROM students s
LEFT JOIN sections sec ON s.section_id = sec.id
LEFT JOIN attendance_log al ON s.id = al.student_id AND al.log_date = CURDATE()
WHERE s.status = 'active'
GROUP BY s.id
ORDER BY s.fullname";

$result = safeQuery($sql, [], '');
$data = [];
while($row = mysqli_fetch_assoc($result)) {
    $row['morning_in'] = $row['morning_in'] ? date('h:i A', strtotime($row['morning_in'])) : null;
    $row['morning_out'] = $row['morning_out'] ? date('h:i A', strtotime($row['morning_out'])) : null;
    $row['afternoon_in'] = $row['afternoon_in'] ? date('h:i A', strtotime($row['afternoon_in'])) : null;
    $row['afternoon_out'] = $row['afternoon_out'] ? date('h:i A', strtotime($row['afternoon_out'])) : null;
    $data[] = $row;
}

echo json_encode($data);
?>