<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$role = $_SESSION['role'];
$teacher_id = null;

if($role == 'teacher') {
    $teacher_sql = "SELECT id FROM teachers WHERE user_id = ?";
    $teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
    $teacher = mysqli_fetch_assoc($teacher_result);
    $teacher_id = $teacher['id'];
}

$sql = "SELECT al.*, s.fullname, sec.section_name 
        FROM attendance_log al
        JOIN students s ON al.student_id = s.id
        LEFT JOIN sections sec ON s.section_id = sec.id";

if($role == 'teacher' && $teacher_id) {
    $sql .= " WHERE sec.teacher_id = ?";
    $result = safeQuery($sql . " ORDER BY al.id DESC, al.time_captured DESC LIMIT 20", [$teacher_id], 'i');
} else {
    $result = safeQuery($sql . " ORDER BY al.id DESC, al.time_captured DESC LIMIT 20", [], '');
}

while($log = mysqli_fetch_assoc($result)) {
    echo '<div class="activity-item">
        <div>
            <strong>' . $log['fullname'] . '</strong>
            <br>
            <small class="text-muted">' . $log['section_name'] . '</small>
        </div>
        <div>
            <span class="badge badge-' . (strpos($log['log_type'], 'in') !== false ? 'success' : 'danger') . '">
                ' . str_replace('_', ' ', $log['log_type']) . '
            </span>
            <div class="small text-muted">' . date('h:i A', strtotime($log['time_captured'])) . '</div>
        </div>
    </div>';
}
?>