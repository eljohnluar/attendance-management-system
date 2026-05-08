<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$token = isset($_GET['token']) ? sanitize($_GET['token']) : '';
$message = '';
$type = '';

if($token) {
    $sql = "SELECT id, email FROM users WHERE email_verification_token = ? AND email_verified = 0";
    $result = safeQuery($sql, [$token], 's');
    
    if($user = mysqli_fetch_assoc($result)) {
        safeQuery("UPDATE users SET email_verified = 1, email_verification_token = NULL WHERE id = ?", [$user['id']], 'i');
        $message = "Email verified successfully! Your account is now pending admin approval.";
        $type = 'success';
    } else {
        $message = "Invalid or expired verification link.";
        $type = 'danger';
    }
} else {
    $message = "No verification token provided.";
    $type = 'danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Email Verification</title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background: linear-gradient(135deg, var(--pink-light), var(--grey-light)); min-height: 100vh; display: flex; align-items: center; justify-content: center;">
    <div style="max-width: 500px; width: 100%; margin: 20px;">
        <div class="card">
            <div class="card-header"><i class="fas fa-envelope"></i> Email Verification</div>
            <div class="card-body">
                <div class="alert alert-<?php echo $type; ?>"><?php echo $message; ?></div>
                <div class="text-center mt-3"><a href="index.php" class="btn btn-primary">Go to Login</a></div>
            </div>
        </div>
    </div>
</body>
</html>