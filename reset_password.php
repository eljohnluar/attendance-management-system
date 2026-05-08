<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$token = isset($_GET['token']) ? sanitize($_GET['token']) : '';

if($token) {
    $sql = "SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0";
    $result = safeQuery($sql, [$token], 's');
    
    if($reset = mysqli_fetch_assoc($result)) {
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            if($password !== $confirm_password) {
                $error = 'Passwords do not match';
            } elseif(strlen($password) < 6) {
                $error = 'Password must be at least 6 characters';
            } else {
                $hashed = hashPassword($password);
                $email = $reset['email'];
                
                $user = safeQuery("SELECT id FROM users WHERE email = ?", [$email], 's');
                if($u = mysqli_fetch_assoc($user)) {
                    safeQuery("UPDATE users SET password = ? WHERE id = ?", [$hashed, $u['id']], 'si');
                    safeQuery("UPDATE password_resets SET used = 1 WHERE token = ?", [$token], 's');
                    $success = 'Password reset successfully! Redirecting to login...';
                    header("refresh:3;url=index.php");
                } else {
                    $error = 'User not found';
                }
            }
        }
    } else {
        $error = 'Invalid or expired reset link';
    }
} else {
    $error = 'No reset token provided';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Reset Password</title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background: linear-gradient(135deg, var(--pink-light), var(--grey-light)); min-height: 100vh; display: flex; align-items: center; justify-content: center;">
    <div style="max-width: 450px; width: 100%; margin: 20px;">
        <div class="card">
            <div class="card-header"><i class="fas fa-lock"></i> Reset Password</div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if(!$success && $token && !$error): ?>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                    </form>
                <?php endif; ?>
                <div class="text-center mt-3"><a href="index.php">Back to Login</a></div>
            </div>
        </div>
    </div>
</body>
</html>