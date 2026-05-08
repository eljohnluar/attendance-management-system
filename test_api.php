<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$id = 1;
$sql = "SELECT t.*, u.username, u.email, u.phone, u.status as user_status, u.created_at as user_created_at
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.id = ?";
$result = safeQuery($sql, [$id], 'i');

if ($result === false) {
    echo "Query failed: " . mysqli_error($conn) . "\n";
} else {
    $teacher = mysqli_fetch_assoc($result);
    var_dump($teacher);
}
?>
