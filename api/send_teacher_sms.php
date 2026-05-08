<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['recipient_type'] ?? $input['type'];
$message = $input['message'];
$recipients = $input['recipients'];
$teacher_id = $input['teacher_id'];

// Verify teacher ID matches current user
$teacher_sql = "SELECT id FROM teachers WHERE user_id = ?";
$teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
$current_teacher = mysqli_fetch_assoc($teacher_result);

if(!$current_teacher || $current_teacher['id'] != $teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get parent contacts based on recipient type (only for teacher's students)
$contacts = [];

if($type == 'grade_checklist') {
    // Get students from specified year levels that are assigned to this teacher
    $placeholders = implode(',', array_fill(0, count($recipients), '?'));
    $sql = "SELECT DISTINCT s.parent_contact FROM students s 
            JOIN sections sec ON s.section_id = sec.id 
            WHERE sec.year_level_id IN ($placeholders) 
            AND sec.teacher_id = ? 
            AND s.parent_contact IS NOT NULL 
            AND s.parent_contact != ''
            AND s.status = 'active'";
    
    $params = array_merge($recipients, [$teacher_id]);
    $types = str_repeat('i', count($recipients)) . 'i';
    $result = safeQuery($sql, $params, $types);
    
    while($row = mysqli_fetch_assoc($result)) {
        $contacts[] = $row['parent_contact'];
    }
} elseif($type == 'section') {
    // Get students from specified section (verify section belongs to teacher)
    $section_id = $recipients[0];
    
    // Verify section belongs to this teacher
    $verify_sql = "SELECT id FROM sections WHERE id = ? AND teacher_id = ? AND status = 'active'";
    $verify_result = safeQuery($verify_sql, [$section_id, $teacher_id], 'ii');
    
    if(mysqli_num_rows($verify_result) == 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access to section']);
        exit();
    }
    
    $sql = "SELECT DISTINCT parent_contact FROM students 
            WHERE section_id = ? 
            AND parent_contact IS NOT NULL 
            AND parent_contact != ''
            AND status = 'active'";
    
    $result = safeQuery($sql, [$section_id], 'i');
    
    while($row = mysqli_fetch_assoc($result)) {
        $contacts[] = $row['parent_contact'];
    }
} else {
    // Individual student (verify student is assigned to this teacher)
    $student_id = $recipients[0];
    
    // Verify student is in a section assigned to this teacher
    $verify_sql = "SELECT s.id FROM students s 
                   JOIN sections sec ON s.section_id = sec.id 
                   WHERE s.id = ? AND sec.teacher_id = ? AND s.status = 'active'";
    $verify_result = safeQuery($verify_sql, [$student_id, $teacher_id], 'ii');
    
    if(mysqli_num_rows($verify_result) == 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access to student']);
        exit();
    }
    
    $sql = "SELECT parent_contact FROM students WHERE id = ?";
    $result = safeQuery($sql, [$student_id], 'i');
    $row = mysqli_fetch_assoc($result);
    
    if($row && $row['parent_contact']) {
        $contacts[] = $row['parent_contact'];
    }
}

// Remove duplicates
$contacts = array_unique($contacts);

// Simulate SMS sending (in production, integrate with actual SMS gateway)
$sent_count = count($contacts);
$failed_count = 0;

// Save to history
$recipient_ids = implode(',', $recipients);
$sql = "INSERT INTO sms_history (recipient_type, recipient_ids, message, status, sent_by, sent_count, failed_count) 
        VALUES (?, ?, ?, 'sent', ?, ?, ?)";
safeQuery($sql, [$type, $recipient_ids, $message, $teacher_id, $sent_count, $failed_count], 'sssiii');

echo json_encode([
    'success' => true, 
    'message' => "SMS sent to $sent_count recipients",
    'sent' => $sent_count,
    'failed' => $failed_count
]);
?>
