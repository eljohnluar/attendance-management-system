<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$search = $_POST['search'] ?? '';

if($id > 0) {
    $sql = "SELECT s.*, sec.section_name, yl.level_name
            FROM students s
            LEFT JOIN sections sec ON s.section_id = sec.id
            LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
            WHERE s.id = ?
            LIMIT 1";
    $result = safeQuery($sql, [$id], 'i');
    if($result === false) {
        global $conn;
        echo json_encode(['success' => false, 'error' => 'query_failed', 'db_error' => mysqli_error($conn)]);
        exit();
    }
} else {
    $sql = "SELECT s.*, sec.section_name, yl.level_name
            FROM students s
            LEFT JOIN sections sec ON s.section_id = sec.id
            LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
            WHERE s.fullname LIKE ? OR s.lrn LIKE ? OR s.rfid_uid LIKE ?
            LIMIT 1";
    $result = safeQuery($sql, ["%$search%", "%$search%", "%$search%"], 'sss');
    if($result === false) {
        global $conn;
        echo json_encode(['success' => false, 'error' => 'query_failed', 'db_error' => mysqli_error($conn)]);
        exit();
    }
}

if($result && $student = mysqli_fetch_assoc($result)) {
    $response = [
        'success'       => true,
        'id'            => $student['id'],
        'lrn'           => $student['lrn'],
        'rfid_uid'      => $student['rfid_uid'],
        'qr_code'       => $student['qr_code'],
        'fullname'      => $student['fullname'],
        'gender'        => $student['gender'],
        'birth_date'    => $student['birth_date'],
        'parent_name'   => $student['parent_name'],
        'parent_contact'=> $student['parent_contact'],
        'address'       => $student['address'],
        'photo'         => $student['photo'],
        'section_id'    => $student['section_id'],
        'section_name'  => $student['section_name'],
        'level_name'    => $student['level_name'],
        'status'        => $student['status'],
        'enrolled_date' => $student['enrolled_date'],
        'created_at'    => $student['created_at'],
        // No user_id column in students table — user account fields not available
        'username'      => null,
        'email'         => null,
        'phone'         => null,
        'user_id'       => null,
    ];
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'error' => 'not_found']);
}
?>