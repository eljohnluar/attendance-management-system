<?php
// api/get_student_by_rfid.php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$rfid = $_POST['rfid'] ?? '';

if(empty($rfid)) {
    echo json_encode(['success' => false, 'message' => 'No RFID provided']);
    exit();
}

$sql = "SELECT id, fullname, section_id FROM students WHERE rfid_uid = ? AND status = 'active'";
$result = safeQuery($sql, [$rfid], 's');

if($student = mysqli_fetch_assoc($result)) {
    echo json_encode([
        'success' => true,
        'id' => $student['id'],
        'fullname' => $student['fullname']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
}
?>