<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$active_form = 'login';
$selected_role = 'teacher';

// Handle Login - Direct login without authentication code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $role = sanitize($_POST['login_role']);

    $sql = "SELECT * FROM users WHERE username = ? AND role = ? AND status = 'active'";
    $result = safeQuery($sql, [$username, $role], 'ss');

    if ($user = mysqli_fetch_assoc($result)) {
        if (verifyPassword($password, $user['password'])) {
            // Direct login - no authentication code needed
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['fullname'] = $user['fullname'];

            if ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } elseif ($user['role'] === 'teacher') {
                header('Location: teacher/dashboard.php');
            } else {
                header('Location: student/dashboard.php');
            }
            exit();
        } else {
            $error = 'Invalid password';
        }
    } else {
        $error = 'Invalid credentials or account not approved';
    }
}

// Handle Admin Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_admin') {
    $admin_reg_code = strtolower(trim($_POST['admin_reg_code']));
    $fullname = sanitize($_POST['fullname']);
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($admin_reg_code !== 'admin2026') {
        $error = 'Invalid registration code for admin. Use: admin2026';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $check = safeQuery("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email], 'ss');
        if (mysqli_num_rows($check) > 0) {
            $error = 'Username or email already exists';
        } else {
            $hashed = hashPassword($password);

            $sql = "INSERT INTO users (username, password, role, email, phone, fullname, status, email_verified, registration_code, created_at) 
                    VALUES (?, ?, 'admin', ?, ?, ?, 'active', 1, 'admin2026', NOW())";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssss', $username, $hashed, $email, $phone, $fullname);
            $result = mysqli_stmt_execute($stmt);

            if ($result) {
                $success = "Registration successful! You can now login.";
                $active_form = 'login';
            } else {
                $error = 'Registration failed. Please try again.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Handle Teacher & Student Registration (No admin approval needed - direct active)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $registration_code = strtolower(trim($_POST['registration_code']));
    $role = sanitize($_POST['role']);
    $fullname = sanitize($_POST['fullname']);
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($registration_code !== 'account2026') {
        $error = 'Invalid registration code for teacher/student. Use: account2026';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($role !== 'teacher' && $role !== 'student') {
        $error = 'Invalid account type';
    } else {
        $check = safeQuery("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email], 'ss');
        if (mysqli_num_rows($check) > 0) {
            $error = 'Username or email already exists';
        } else {
            $hashed = hashPassword($password);

            // No admin approval needed - account is active immediately
            $email_verified = 1;
            $status = 'active';

            $sql = "INSERT INTO users (username, password, role, email, phone, fullname, status, email_verified, registration_code, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssssssis', $username, $hashed, $role, $email, $phone, $fullname, $status, $email_verified, $registration_code);
            $result = mysqli_stmt_execute($stmt);

            if ($result) {
                $user_id = mysqli_insert_id($conn);

                if ($role === 'teacher') {
                    $teacher_id = 'TCH' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                    safeQuery("INSERT INTO teachers (user_id, teacher_id, registered_date) VALUES (?, ?, CURDATE())", [$user_id, $teacher_id], 'is');
                } else {
                    $student_code = 'STU' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                    
                    // Insert student with the user_id link
                    safeQuery("INSERT INTO students (user_id, lrn, fullname, enrolled_date) VALUES (?, ?, ?, CURDATE())", [$user_id, $student_code, $fullname], 'iss');
                    
                    // Get the inserted student ID
                    $student_id = mysqli_insert_id($conn);

                    if ($student_id) {
                        // Generate QR code using a canonical student payload
                        $qr_data = buildStudentQrData($student_id);
                        $qr_filename = "qr_student_" . $student_id;
                        $qr_filepath = generateQRCode($qr_data, $qr_filename);
                        $qr_code_value = buildStudentQrCodeValue($student_id, $qr_filepath);
                        safeQuery("UPDATE students SET qr_code = ? WHERE id = ?", [$qr_code_value, $student_id], 'si');
                    }
                }

                $success = "Registration successful! You can now login with your credentials.";
                $active_form = 'login';
            } else {
                $error = 'Registration failed. Please try again.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Handle Forgot Password Request (Demo mode - Just show message)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'forgot') {
    $email = sanitize($_POST['email']);

    $sql = "SELECT id, username, fullname FROM users WHERE email = ?";
    $result = safeQuery($sql, [$email], 's');

    if ($user = mysqli_fetch_assoc($result)) {
        $success = "Password reset would be sent to: $email<br>
                    <small>Use default password: password123</small>";
    } else {
        $success = "If your email is registered, you would receive a password reset link.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Smart Attendance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Define CSS variables directly in the page */
        :root {
            --pink: #ff69b4;
            --pink-light: #ffb6c1;
            --pink-dark: #ff1493;
            --grey-light: #f5f5f5;
            --grey: #e0e0e0;
            --grey-dark: #9e9e9e;
            --white: #ffffff;
            --white-smoke: #fafafa;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            --radius: 12px;
            --sidebar-width: 260px;
            --navbar-height: 60px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: var(--grey-light);
            display: block;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        .split-left {
            background: linear-gradient(135deg, var(--pink) 0%, var(--pink-dark) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 50%;
            overflow: hidden;
            z-index: 1;
        }

        .split-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
            background-size: 40px 40px;
            animation: moveBackground 60s linear infinite;
            pointer-events: none;
        }

        @keyframes moveBackground {
            from {
                transform: translate(0, 0);
            }

            to {
                transform: translate(40px, 40px);
            }
        }

        .left-content {
            position: relative;
            z-index: 2;
            max-width: 380px;
            width: 100%;
            text-align: center;
        }

        .logo-img {
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
            border-radius: 50%;
            object-fit: cover;
        }

        .logo-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            color: white;
        }

        .logo-subtitle {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 40px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 20px;
            color: white;
            text-align: left;
        }

        .feature-icon {
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .feature-text {
            font-size: 14px;
            font-weight: 500;
        }

        .feature-desc {
            font-size: 11px;
            opacity: 0.65;
            margin-top: 2px;
        }

        .split-right {
            background: var(--white);
            margin-left: 50%;
            width: 50%;
            min-height: 100vh;
            overflow-y: auto;
            position: relative;
            flex-shrink: 0;
        }

        .split-right::-webkit-scrollbar {
            width: 4px;
        }

        .split-right::-webkit-scrollbar-track {
            background: var(--grey-light);
        }

        .split-right::-webkit-scrollbar-thumb {
            background: var(--pink);
            border-radius: 4px;
        }

        .form-wrapper {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 100vh;
            padding: 60px 40px;
        }

        .form-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .form-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 32px;
            text-align: center;
            color: #333;
        }

        .form-panel {
            display: none;
            animation: fadeIn 0.25s ease;
        }

        .form-panel.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 6px;
            color: #888;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
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

        .role-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 28px;
            background: var(--grey-light);
            padding: 5px;
            border-radius: 40px;
        }

        .role-option {
            flex: 1;
            text-align: center;
            padding: 10px;
            cursor: pointer;
            border-radius: 36px;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s;
            color: #888;
        }

        .role-option i {
            margin-right: 6px;
            font-size: 12px;
            transition: color 0.2s;
        }

        .role-option.active {
            background: var(--pink);
            color: white;
        }

        .role-option.active i {
            color: white !important;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
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

        .form-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--grey);
        }

        .form-footer p {
            font-size: 13px;
            color: #888;
        }

        .form-footer a {
            color: var(--pink);
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 10px 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 12px;
        }

        .alert-danger {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .toast-notification {
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            z-index: 1000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .toast-success {
            background: #4caf50;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .scanner-link {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--white);
            padding: 10px 18px;
            border-radius: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            text-decoration: none;
            color: var(--pink);
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            z-index: 100;
            border: 1px solid var(--grey);
        }

        .scanner-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            border-color: var(--pink);
        }

        .role-option[data-role="admin"] i {
            color: #9c27b0;
        }

        .role-option[data-role="admin"].active {
            background: #9c27b0;
        }

        .role-option[data-role="teacher"] i {
            color: #4caf50;
        }

        .role-option[data-role="teacher"]:not(.active) i {
            color: #4caf50;
        }

        .role-option[data-role="teacher"].active {
            background: #4caf50;
        }

        .role-option[data-role="student"] i {
            color: #ff9800;
        }

        .role-option[data-role="student"].active {
            background: #ff9800;
        }

        .role-option[data-type="admin"] i {
            color: #9c27b0;
        }

        .role-option[data-type="admin"].active {
            background: #9c27b0;
        }

        .role-option[data-type="teacher"] i {
            color: #4caf50;
        }

        .role-option[data-type="teacher"]:not(.active) i {
            color: #4caf50;
        }

        .role-option[data-type="teacher"].active {
            background: #4caf50;
        }

        .role-option[data-type="student"] i {
            color: #ff9800;
        }

        .role-option[data-type="student"].active {
            background: #ff9800;
        }

        @media (max-width: 900px) {
            .split-left {
                display: none;
            }

            .split-right {
                margin-left: 0;
                width: 100%;
            }

            .form-wrapper {
                padding: 40px 20px;
            }
        }
    </style>
</head>

<body>
    <div class="split-left">
        <div class="left-content">
            <img src="assets/images/logo/logo.jpg" alt="Smart Attendance" class="logo-img"
                onerror="this.src='https://placehold.co/120x120/ff69b4/white?text=SA'">
            <div class="logo-title">Attendance of IT department</div>
            <div class="logo-subtitle">Track. Monitor. Manage.</div>

            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-qrcode"></i></div>
                <div>
                    <div class="feature-text">QR Code & RFID</div>
                    <div class="feature-desc">Fast contactless scanning</div>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <div>
                    <div class="feature-text">Real-time Tracking</div>
                    <div class="feature-desc">Live attendance monitoring</div>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-envelope"></i></div>
                <div>
                    <div class="feature-text">Email Notifications</div>
                    <div class="feature-desc">Instant alerts</div>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-chart-pie"></i></div>
                <div>
                    <div class="feature-text">Analytics</div>
                    <div class="feature-desc">Comprehensive reports</div>
                </div>
            </div>
        </div>
    </div>

    <div class="split-right">
        <div class="form-wrapper">
            <div class="form-container">
                <!-- Login Panel -->
                <div id="loginPanel" class="form-panel <?php echo $active_form === 'login' ? 'active' : ''; ?>">
                    <div class="form-title">Welcome Back</div>
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success && $active_form === 'login'): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <div class="role-toggle" id="loginRoleToggle">
                        <div class="role-option" data-role="teacher" onclick="setLoginRole('teacher', this)"><i
                                class="fas fa-chalkboard-teacher"></i> Teacher</div>
                        <div class="role-option" data-role="admin" onclick="setLoginRole('admin', this)"><i
                                class="fas fa-user-shield"></i> Admin</div>
                        <div class="role-option" data-role="student" onclick="setLoginRole('student', this)"><i
                                class="fas fa-user-graduate"></i> Student</div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="login_role" id="loginRole" value="teacher">
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="Enter username"
                                required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Enter password"
                                required>
                        </div>
                        <button type="submit" class="btn btn-primary" id="loginBtn"><i class="fas fa-sign-in-alt"></i>
                            Login</button>
                    </form>

                    <div class="form-footer">
                        <p><a onclick="showForgotPassword()">Forgot password?</a></p>
                        <p style="margin-top: 10px;">Don't have an account? <a onclick="switchForm('register')">Register
                                here</a></p>
                    </div>
                </div>

                <!-- Forgot Password Panel -->
                <div id="forgotPanel" class="form-panel">
                    <div class="form-title">Reset Password</div>
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="forgot">
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control"
                                placeholder="Enter your registered email" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Reset
                            Link</button>
                    </form>
                    <div class="form-footer">
                        <p><a onclick="switchForm('login')">← Back to Login</a></p>
                    </div>
                </div>

                <!-- Register Panel -->
                <div id="registerPanel" class="form-panel <?php echo $active_form === 'register' ? 'active' : ''; ?>">
                    <div class="form-title">Create Account</div>
                    <?php if ($error && $active_form === 'register'): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <div class="role-toggle" id="registerTypeToggle">
                        <div class="role-option" data-type="admin" onclick="setRegisterType('admin', this)"><i
                                class="fas fa-user-shield"></i> Admin</div>
                        <div class="role-option" data-type="teacher" onclick="setRegisterType('teacher', this)"><i
                                class="fas fa-chalkboard-teacher"></i> Teacher</div>
                        <div class="role-option" data-type="student" onclick="setRegisterType('student', this)"><i
                                class="fas fa-user-graduate"></i> Student</div>
                    </div>

                    <div id="adminRegisterForm" class="register-type-form" style="display: none;">
                        <form method="POST">
                            <input type="hidden" name="action" value="register_admin">
                            <div class="form-group">
                                <label class="form-label">Registration Code <span class="text-muted">*</span></label>
                                <input type="password" name="admin_reg_code" class="form-control"
                                    placeholder="Enter registration code" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Full Name <span class="text-muted">*</span></label>
                                <input type="text" name="fullname" class="form-control"
                                    placeholder="Enter your full name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Username <span class="text-muted">*</span></label>
                                <input type="text" name="username" class="form-control" placeholder="Choose a username"
                                    required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email <span class="text-muted">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="your@email.com"
                                    required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" placeholder="Contact number">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password <span class="text-muted">*</span></label>
                                <input type="password" name="password" class="form-control"
                                    placeholder="Minimum 6 characters" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm Password <span class="text-muted">*</span></label>
                                <input type="password" name="confirm_password" class="form-control"
                                    placeholder="Re-enter password" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-user-shield"></i> Register as
                                Admin</button>
                        </form>
                    </div>

                    <div id="regularRegisterForm" class="register-type-form">
                        <form method="POST">
                            <input type="hidden" name="action" value="register">
                            <input type="hidden" name="role" id="registerRole" value="teacher">

                            <div class="form-group">
                                <label class="form-label">Registration Code <span class="text-muted">*</span></label>
                                <input type="password" name="registration_code" class="form-control"
                                    placeholder="Enter registration code" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Full Name <span class="text-muted">*</span></label>
                                <input type="text" name="fullname" class="form-control"
                                    placeholder="Enter your full name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Username <span class="text-muted">*</span></label>
                                <input type="text" name="username" class="form-control" placeholder="Choose a username"
                                    required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email <span class="text-muted">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="your@email.com"
                                    required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" placeholder="Contact number">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password <span class="text-muted">*</span></label>
                                <input type="password" name="password" class="form-control"
                                    placeholder="Minimum 6 characters" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm Password <span class="text-muted">*</span></label>
                                <input type="password" name="confirm_password" class="form-control"
                                    placeholder="Re-enter password" required>
                            </div>
                            <button type="submit" class="btn btn-primary" id="registerBtn"><i
                                    class="fas fa-user-plus"></i> Register</button>
                        </form>
                    </div>

                    <div class="form-footer">
                        <p>Already have an account? <a onclick="switchForm('login')">Login here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <a href="qr_scanner.php" class="scanner-link"><i class="fas fa-qrcode"></i> Quick Scan</a>

    <script>
        function switchForm(form) {
            document.querySelectorAll('.form-panel').forEach(panel => panel.classList.remove('active'));
            document.getElementById(form + 'Panel').classList.add('active');
            document.querySelectorAll('.alert').forEach(alert => alert.remove());
        }

        function showForgotPassword() { switchForm('forgot'); }

        function setLoginRole(role, element) {
            document.querySelectorAll('#loginRoleToggle .role-option').forEach(opt => opt.classList.remove('active'));
            element.classList.add('active');
            document.getElementById('loginRole').value = role;
            const loginBtn = document.getElementById('loginBtn');
            if (role === 'admin') {
                loginBtn.style.background = '#9c27b0';
            } else if (role === 'teacher') {
                loginBtn.style.background = '#4caf50';
            } else if (role === 'student') {
                loginBtn.style.background = '#ff9800';
            } else {
                loginBtn.style.background = '#ff69b4';
            }
        }

        function setRegisterType(type, element) {
            document.querySelectorAll('#registerTypeToggle .role-option').forEach(opt => opt.classList.remove('active'));
            element.classList.add('active');

            const registerBtn = document.getElementById('registerBtn');
            if (type === 'admin') {
                document.getElementById('adminRegisterForm').style.display = 'block';
                document.getElementById('regularRegisterForm').style.display = 'none';
                document.getElementById('registerRole').value = 'admin';
                if (registerBtn) registerBtn.style.background = '#9c27b0';
            } else if (type === 'teacher') {
                document.getElementById('adminRegisterForm').style.display = 'none';
                document.getElementById('regularRegisterForm').style.display = 'block';
                document.getElementById('registerRole').value = 'teacher';
                if (registerBtn) registerBtn.style.background = '#4caf50';
            } else {
                document.getElementById('adminRegisterForm').style.display = 'none';
                document.getElementById('regularRegisterForm').style.display = 'block';
                document.getElementById('registerRole').value = 'student';
                if (registerBtn) registerBtn.style.background = '#ff9800';
            }
        }

        const loginRoleToggle = document.querySelector('#loginRoleToggle');
        if (loginRoleToggle) {
            const defaultRole = document.querySelector('#loginRoleToggle .role-option[data-role="teacher"]');
            if (defaultRole) {
                defaultRole.classList.add('active');
                const icon = defaultRole.querySelector('i');
                if (icon) icon.style.color = 'white';
            }
        }
        const registerTypeToggle = document.querySelector('#registerTypeToggle');
        if (registerTypeToggle) {
            const defaultType = document.querySelector('#registerTypeToggle .role-option[data-type="teacher"]');
            if (defaultType) {
                defaultType.classList.add('active');
                const icon = defaultType.querySelector('i');
                if (icon) icon.style.color = 'white';
            }
        }
        const regularRegisterForm = document.getElementById('regularRegisterForm');
        if (regularRegisterForm) regularRegisterForm.style.display = 'block';
        const adminRegisterForm = document.getElementById('adminRegisterForm');
        if (adminRegisterForm) adminRegisterForm.style.display = 'none';
        const registerRole = document.getElementById('registerRole');
        if (registerRole) registerRole.value = 'teacher';
    </script>
</body>

</html>
