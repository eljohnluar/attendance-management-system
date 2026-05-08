<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id > 0) {
    $sql = "SELECT t.*, u.fullname, u.username, u.email, u.phone, u.status as user_status, u.created_at as user_created_at
            FROM teachers t 
            JOIN users u ON t.user_id = u.id 
            WHERE t.id = ?";
    $result = safeQuery($sql, [$id], 'i');
    
    if($teacher = mysqli_fetch_assoc($result)) {
        echo json_encode([
            'success' => true,
            'id' => $teacher['id'],
            'user_id' => $teacher['user_id'],
            'teacher_id' => $teacher['teacher_id'],
            'fullname' => $teacher['fullname'],
            'username' => $teacher['username'],
            'email' => $teacher['email'] ?? '',
            'phone' => $teacher['phone'] ?? '',
            'gender' => $teacher['gender'] ?? 'Male',
            'photo' => $teacher['photo'] ?? 'default_avatar.png',
            'status' => $teacher['status'] ?? 'active',
            'user_status' => $teacher['user_status'] ?? 'active',
            'registered_date' => date('M d, Y', strtotime($teacher['registered_date'])),
            'created_at' => date('M d, Y', strtotime($teacher['user_created_at']))
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
}
?>