<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if student is logged in
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['fullname'] ?? $_SESSION['username'];
$student_username = $_SESSION['username'];

$error = '';
$success = '';

// Handle password change
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if(empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif(strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long';
    } elseif($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match';
    } else {
        // Get current user's password hash
        $user_sql = "SELECT password FROM users WHERE id = ? AND role = 'student'";
        $user_result = safeQuery($user_sql, [$student_id], 'i');
        
        if($user_result && mysqli_num_rows($user_result) > 0) {
            $user = mysqli_fetch_assoc($user_result);
            
            // Verify current password
            if(verifyPassword($current_password, $user['password'])) {
                // Hash new password
                $new_password_hash = hashPassword($new_password);
                
                // Update password
                $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                $update_result = safeQuery($update_sql, [$new_password_hash, $student_id], 'si');
                
                if($update_result !== false) {
                    $success = 'Password changed successfully! You will be redirected to login page.';
                    
                    // Log out the user and redirect to login after 3 seconds
                    echo '<meta http-equiv="refresh" content="3;url=../logout.php">';
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            } else {
                $error = 'Current password is incorrect';
            }
        } else {
            $error = 'User not found';
        }
    }
}

// Get password security requirements
$has_lowercase = false;
$has_uppercase = false;
$has_number = false;
$has_special = false;
$length_valid = false;

// Password strength checking function
function checkPasswordStrength($password, &$has_lowercase, &$has_uppercase, &$has_number, &$has_special, &$length_valid) {
    $has_lowercase = preg_match('/[a-z]/', $password);
    $has_uppercase = preg_match('/[A-Z]/', $password);
    $has_number = preg_match('/[0-9]/', $password);
    $has_special = preg_match('/[^a-zA-Z0-9]/', $password);
    $length_valid = strlen($password) >= 6;
    
    $score = 0;
    if($has_lowercase) $score++;
    if($has_uppercase) $score++;
    if($has_number) $score++;
    if($has_special) $score++;
    if($length_valid) $score++;
    
    if($score <= 2) return 'weak';
    if($score <= 4) return 'medium';
    return 'strong';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Change Password - Student</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <style>
        .password-container {
            padding: 24px;
            max-width: 600px;
            margin: 0 auto;
        }

        .card {
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--grey);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--grey);
            background: var(--white);
        }

        .card-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 i {
            color: var(--pink);
        }

        .card-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #666;
        }

        .form-label i {
            width: 20px;
            color: var(--pink);
            margin-right: 6px;
        }

        .input-group {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            padding-right: 45px;
            border: 1px solid var(--grey);
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
            background: var(--white-smoke);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--pink);
            background: var(--white);
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            transition: all 0.2s;
        }

        .toggle-password:hover {
            color: var(--pink);
        }

        /* Password Strength Meter */
        .strength-meter {
            margin-top: 8px;
        }

        .strength-bar {
            height: 4px;
            background: var(--grey);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .strength-text {
            font-size: 11px;
            color: #999;
        }

        .strength-text.weak { color: #f44336; }
        .strength-text.medium { color: #ff9800; }
        .strength-text.strong { color: #4caf50; }

        /* Password Requirements */
        .requirements {
            background: var(--white-smoke);
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
        }

        .requirements-title {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #666;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            padding: 6px 0;
            color: #999;
            transition: all 0.2s;
        }

        .requirement i {
            width: 16px;
            font-size: 12px;
        }

        .requirement.valid {
            color: #4caf50;
        }

        .requirement.invalid {
            color: #f44336;
        }

        .requirement.valid i.fa-circle {
            display: none;
        }

        .requirement.valid i.fa-check-circle {
            display: inline-block;
        }

        .requirement.invalid i.fa-check-circle {
            display: none;
        }

        .requirement.invalid i.fa-circle {
            display: inline-block;
        }

        .requirement i.fa-check-circle {
            color: #4caf50;
        }

        .requirement i.fa-circle {
            color: #ddd;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #4caf50;
            border: 1px solid #c8e6c9;
        }

        .alert-danger {
            background: #ffebee;
            color: #f44336;
            border: 1px solid #ffcdd2;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--pink);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .security-tips {
            background: #e3f2fd;
            border-radius: 16px;
            padding: 20px;
            margin-top: 24px;
        }

        .security-tips h4 {
            font-size: 14px;
            font-weight: 600;
            color: #1565c0;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .security-tips ul {
            margin: 0;
            padding-left: 20px;
        }

        .security-tips li {
            font-size: 12px;
            color: #555;
            margin-bottom: 8px;
        }

        .security-tips li:last-child {
            margin-bottom: 0;
        }

        .info-card {
            background: var(--white-smoke);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .info-card i {
            font-size: 24px;
            color: var(--pink);
        }

        .info-card p {
            font-size: 13px;
            color: #666;
            margin: 0;
        }

        .main-content {
            margin-top: var(--navbar-height);
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - var(--navbar-height));
            width: 100%;
            overflow-x: hidden;
        }

        @media (min-width: 1024px) {
            .main-content {
                margin-left: var(--sidebar-width);
                width: calc(100% - var(--sidebar-width));
            }
        }

        @media (max-width: 1023px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .password-container {
                padding: 16px;
            }
            
            .card-header {
                padding: 16px 20px;
            }
            
            .card-header h2 {
                font-size: 18px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .security-tips {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/student_navbar.php'; ?>
    <?php include '../includes/student_sidebar.php'; ?>
    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="main-content">
        <div class="password-container">
            <!-- Info Card -->
            <div class="info-card">
                <i class="fas fa-shield-alt"></i>
                <p>Change your password regularly to keep your account secure. Use a strong password that you haven't used before.</p>
            </div>
            
            <!-- Change Password Card -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-key"></i>
                        Change Password
                    </h2>
                </div>
                <div class="card-body">
                    <?php if($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-lock"></i> Current Password
                            </label>
                            <div class="input-group">
                                <input type="password" name="current_password" id="currentPassword" class="form-control" placeholder="Enter your current password" required>
                                <span class="toggle-password" onclick="togglePassword('currentPassword')">
                                    <i class="far fa-eye"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-key"></i> New Password
                            </label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="newPassword" class="form-control" placeholder="Enter new password" required onkeyup="checkPasswordStrength()">
                                <span class="toggle-password" onclick="togglePassword('newPassword')">
                                    <i class="far fa-eye"></i>
                                </span>
                            </div>
                            
                            <!-- Password Strength Meter -->
                            <div class="strength-meter">
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                                <div class="strength-text" id="strengthText">Enter a new password</div>
                            </div>
                            
                            <!-- Password Requirements -->
                            <div class="requirements">
                                <div class="requirements-title">Password Requirements:</div>
                                <div class="requirement" id="reqLength">
                                    <i class="fas fa-circle"></i>
                                    <i class="fas fa-check-circle" style="display: none;"></i>
                                    <span>At least 6 characters long</span>
                                </div>
                                <div class="requirement" id="reqLowercase">
                                    <i class="fas fa-circle"></i>
                                    <i class="fas fa-check-circle" style="display: none;"></i>
                                    <span>Contains lowercase letter (a-z)</span>
                                </div>
                                <div class="requirement" id="reqUppercase">
                                    <i class="fas fa-circle"></i>
                                    <i class="fas fa-check-circle" style="display: none;"></i>
                                    <span>Contains uppercase letter (A-Z)</span>
                                </div>
                                <div class="requirement" id="reqNumber">
                                    <i class="fas fa-circle"></i>
                                    <i class="fas fa-check-circle" style="display: none;"></i>
                                    <span>Contains number (0-9)</span>
                                </div>
                                <div class="requirement" id="reqSpecial">
                                    <i class="fas fa-circle"></i>
                                    <i class="fas fa-check-circle" style="display: none;"></i>
                                    <span>Contains special character (!@#$%^&*)</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-check-double"></i> Confirm New Password
                            </label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Confirm new password" required onkeyup="checkPasswordMatch()">
                                <span class="toggle-password" onclick="togglePassword('confirmPassword')">
                                    <i class="far fa-eye"></i>
                                </span>
                            </div>
                            <div id="matchMessage" style="font-size: 11px; margin-top: 5px;"></div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Security Tips Card -->
            <div class="security-tips">
                <h4>
                    <i class="fas fa-lightbulb"></i>
                    Security Tips
                </h4>
                <ul>
                    <li><i class="fas fa-check-circle" style="color: #4caf50; margin-right: 8px;"></i> Use a mix of uppercase and lowercase letters</li>
                    <li><i class="fas fa-check-circle" style="color: #4caf50; margin-right: 8px;"></i> Include numbers and special characters</li>
                    <li><i class="fas fa-check-circle" style="color: #4caf50; margin-right: 8px;"></i> Avoid using personal information like your name or birthdate</li>
                    <li><i class="fas fa-check-circle" style="color: #4caf50; margin-right: 8px;"></i> Don't reuse passwords from other accounts</li>
                    <li><i class="fas fa-check-circle" style="color: #4caf50; margin-right: 8px;"></i> Change your password every 3-6 months</li>
                    <li><i class="fas fa-check-circle" style="color: #4caf50; margin-right: 8px;"></i> Never share your password with anyone</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        // Sidebar Toggle
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        function closeSidebar() {
            if(sidebar) sidebar.classList.remove('open');
            if(overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        if(overlay) overlay.addEventListener('click', closeSidebar);
        
        window.addEventListener('resize', function() {
            if(window.innerWidth >= 1024) {
                if(sidebar) sidebar.classList.remove('open');
                if(overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentElement.querySelector('.toggle-password i');
            
            if(field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Check password strength
        function checkPasswordStrength() {
            const password = document.getElementById('newPassword').value;
            
            // Check requirements
            const hasLowercase = /[a-z]/.test(password);
            const hasUppercase = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[^a-zA-Z0-9]/.test(password);
            const lengthValid = password.length >= 6;
            
            // Update requirement indicators
            updateRequirement('reqLength', lengthValid);
            updateRequirement('reqLowercase', hasLowercase);
            updateRequirement('reqUppercase', hasUppercase);
            updateRequirement('reqNumber', hasNumber);
            updateRequirement('reqSpecial', hasSpecial);
            
            // Calculate strength
            let score = 0;
            if(hasLowercase) score++;
            if(hasUppercase) score++;
            if(hasNumber) score++;
            if(hasSpecial) score++;
            if(lengthValid) score++;
            
            // Update strength meter
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            if(password.length === 0) {
                strengthFill.style.width = '0%';
                strengthFill.style.background = '#e0e0e0';
                strengthText.textContent = 'Enter a new password';
                strengthText.className = 'strength-text';
            } else if(score <= 2) {
                strengthFill.style.width = '33%';
                strengthFill.style.background = '#f44336';
                strengthText.textContent = 'Weak password';
                strengthText.className = 'strength-text weak';
            } else if(score <= 4) {
                strengthFill.style.width = '66%';
                strengthFill.style.background = '#ff9800';
                strengthText.textContent = 'Medium password';
                strengthText.className = 'strength-text medium';
            } else {
                strengthFill.style.width = '100%';
                strengthFill.style.background = '#4caf50';
                strengthText.textContent = 'Strong password';
                strengthText.className = 'strength-text strong';
            }
            
            // Check if passwords match
            checkPasswordMatch();
        }
        
        // Update requirement visual
        function updateRequirement(elementId, isValid) {
            const element = document.getElementById(elementId);
            const circleIcon = element.querySelector('.fa-circle');
            const checkIcon = element.querySelector('.fa-check-circle');
            
            if(isValid) {
                element.classList.add('valid');
                element.classList.remove('invalid');
                circleIcon.style.display = 'none';
                checkIcon.style.display = 'inline-block';
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
                circleIcon.style.display = 'inline-block';
                checkIcon.style.display = 'none';
            }
        }
        
        // Check if passwords match
        function checkPasswordMatch() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchMessage = document.getElementById('matchMessage');
            const submitBtn = document.getElementById('submitBtn');
            
            if(confirmPassword.length > 0) {
                if(newPassword === confirmPassword) {
                    matchMessage.innerHTML = '<i class="fas fa-check-circle" style="color: #4caf50;"></i> Passwords match';
                    matchMessage.style.color = '#4caf50';
                } else {
                    matchMessage.innerHTML = '<i class="fas fa-times-circle" style="color: #f44336;"></i> Passwords do not match';
                    matchMessage.style.color = '#f44336';
                }
            } else {
                matchMessage.innerHTML = '';
            }
            
            // Enable/disable submit button based on validation
            const isValid = validateForm();
            submitBtn.disabled = !isValid;
        }
        
        // Validate entire form
        function validateForm() {
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            const hasLowercase = /[a-z]/.test(newPassword);
            const hasUppercase = /[A-Z]/.test(newPassword);
            const hasNumber = /[0-9]/.test(newPassword);
            const hasSpecial = /[^a-zA-Z0-9]/.test(newPassword);
            const lengthValid = newPassword.length >= 6;
            
            return currentPassword.length > 0 &&
                   newPassword.length > 0 &&
                   confirmPassword.length > 0 &&
                   hasLowercase &&
                   hasUppercase &&
                   hasNumber &&
                   hasSpecial &&
                   lengthValid &&
                   newPassword === confirmPassword;
        }
        
        // Add event listeners
        document.getElementById('newPassword').addEventListener('keyup', checkPasswordStrength);
        document.getElementById('confirmPassword').addEventListener('keyup', checkPasswordMatch);
        document.getElementById('currentPassword').addEventListener('keyup', function() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = !validateForm();
        });
        
        // Initial validation
        checkPasswordStrength();
    </script>
</body>
</html>