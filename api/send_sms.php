<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'];
$message = $input['message'];
$recipients = $input['recipients'];

// Get parent contacts based on recipient type
$contacts = [];
if($type == 'year_level') {
    $placeholders = implode(',', array_fill(0, count($recipients), '?'));
    $sql = "SELECT DISTINCT parent_contact FROM students s 
            JOIN sections sec ON s.section_id = sec.id 
            WHERE sec.year_level_id IN ($placeholders) AND parent_contact IS NOT NULL AND parent_contact != ''";
    $result = safeQuery($sql, $recipients, str_repeat('i', count($recipients)));
    while($row = mysqli_fetch_assoc($result)) {
        $contacts[] = $row['parent_contact'];
    }
} elseif($type == 'section') {
    $placeholders = implode(',', array_fill(0, count($recipients), '?'));
    $sql = "SELECT DISTINCT parent_contact FROM students 
            WHERE section_id IN ($placeholders) AND parent_contact IS NOT NULL AND parent_contact != ''";
    $result = safeQuery($sql, $recipients, str_repeat('i', count($recipients)));
    while($row = mysqli_fetch_assoc($result)) {
        $contacts[] = $row['parent_contact'];
    }
} else {
    // Individual students
    $sql = "SELECT parent_contact FROM students WHERE id = ?";
    $result = safeQuery($sql, [$recipients[0]], 'i');
    $row = mysqli_fetch_assoc($result);
    if($row && $row['parent_contact']) {
        $contacts[] = $row['parent_contact'];
    }
}

// Simulate SMS sending (in production, integrate with actual SMS gateway)
$sent_count = count($contacts);
$failed_count = 0;

// Save to history
$recipient_ids = implode(',', $recipients);
$sql = "INSERT INTO sms_history (recipient_type, recipient_ids, message, status, sent_by, sent_count, failed_count) 
        VALUES (?, ?, ?, 'sent', ?, ?, ?)";
safeQuery($sql, [$type, $recipient_ids, $message, $_SESSION['user_id'], $sent_count, $failed_count], 'sssiii');

echo json_encode([
    'success' => true, 
    'message' => "SMS sent to $sent_count recipients",
    'sent' => $sent_count,
    'failed' => $failed_count
]);
?>