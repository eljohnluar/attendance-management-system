<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Make $conn available in this scope for static analyzers
global $conn;

// Check if student is logged in
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['fullname'] ?? $_SESSION['username'];
$student_username = $_SESSION['username'];

// First, get the user's email and phone from users table
$user_sql = "SELECT id, username, fullname, email, phone, created_at FROM users WHERE id = ? AND role = 'student'";
$user_result = safeQuery($user_sql, [$student_id], 'i');

$user_data = [];
if($user_result && mysqli_num_rows($user_result) > 0) {
    $user_data = mysqli_fetch_assoc($user_result);
}

// Get student details - Match by fullname from users table
$student = null;
$student_record_id = null;

// First attempt: Try to find student by fullname matching the logged-in user
$student_sql = "SELECT s.*, sec.section_name, yl.level_name, tu.fullname as teacher_name 
                FROM students s
                LEFT JOIN sections sec ON s.section_id = sec.id
                LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                LEFT JOIN teachers t ON sec.teacher_id = t.id
                LEFT JOIN users tu ON t.user_id = tu.id
                WHERE s.fullname = ?";
$student_result = safeQuery($student_sql, [$student_name], 's');

if($student_result && mysqli_num_rows($student_result) > 0) {
    $student = mysqli_fetch_assoc($student_result);
    $student_record_id = $student['id'];
}

// Second attempt: Try by username prefix (e.g., alice.mendoza -> Alice)
if(!$student) {
    $name_parts = explode('.', $student_username);
    $first_name = ucfirst($name_parts[0] ?? '');
    
    $student_sql = "SELECT s.*, sec.section_name, yl.level_name, tu.fullname as teacher_name 
                    FROM students s
                    LEFT JOIN sections sec ON s.section_id = sec.id
                    LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                    LEFT JOIN teachers t ON sec.teacher_id = t.id
                    LEFT JOIN users tu ON t.user_id = tu.id
                    WHERE s.fullname LIKE ?";
    $student_result = safeQuery($student_sql, ["$first_name%"], 's');
    
    if($student_result && mysqli_num_rows($student_result) > 0) {
        $student = mysqli_fetch_assoc($student_result);
        $student_record_id = $student['id'];
    }
}

// Third attempt: Try by LRN or other identifier
if(!$student) {
    $student_sql = "SELECT s.*, sec.section_name, yl.level_name, tu.fullname as teacher_name 
                    FROM students s
                    LEFT JOIN sections sec ON s.section_id = sec.id
                    LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                    LEFT JOIN teachers t ON sec.teacher_id = t.id
                    LEFT JOIN users tu ON t.user_id = tu.id
                    WHERE s.lrn = ? OR s.email = ?";
    $student_result = safeQuery($student_sql, [$student_username, $student_username], 'ss');
    
    if($student_result && mysqli_num_rows($student_result) > 0) {
        $student = mysqli_fetch_assoc($student_result);
        $student_record_id = $student['id'];
    }
}

// If still no student found, create a basic student object
if(!$student) {
    error_log("Student Profile - No student found for user: $student_username / $student_name");
    $student = [
        'id' => 0,
        'fullname' => $student_name,
        'lrn' => 'Not set',
        'section_name' => 'Not assigned',
        'level_name' => '',
        'teacher_name' => 'Not assigned',
        'gender' => 'Not specified',
        'birth_date' => null,
        'address' => '',
        'parent_name' => '',
        'parent_contact' => '',
        'rfid_uid' => '',
        'photo' => 'default_student.png',
        'status' => 'active',
        'enrolled_date' => date('Y-m-d')
    ];
    $student_record_id = 0;
}

// Merge user data into student array
if(!empty($user_data)) {
    $student['email'] = $user_data['email'] ?? '';
    $student['phone'] = $user_data['phone'] ?? '';
    $student['account_created'] = $user_data['created_at'] ?? date('Y-m-d H:i:s');
    $student['username'] = $user_data['username'] ?? $student_username;
} else {
    $student['email'] = $student_username . '@student.edu';
    $student['phone'] = '';
    $student['account_created'] = date('Y-m-d H:i:s');
    $student['username'] = $student_username;
}

// Debug log
error_log("Student Profile - Student Record ID: " . $student_record_id . " for user: " . $student_username);

// Handle profile update
$update_success = '';
$update_error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fullname = sanitize($_POST['fullname']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $parent_name = sanitize($_POST['parent_name']);
    $parent_contact = sanitize($_POST['parent_contact']);
    
    // Handle photo upload
    $photo = $student['photo'] ?? 'default_student.png';
    if(isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadFile($_FILES['photo'], 'students', ['jpg', 'jpeg', 'png', 'gif']);
        if($upload_result) {
            // Delete old photo if not default
            if($photo && $photo !== 'default_student.png' && file_exists('../uploads/students/' . $photo)) {
                unlink('../uploads/students/' . $photo);
            }
            $photo = basename($upload_result);
        }
    }
    
    $update_success_flag = false;
    
    // Update students table
    if($student_record_id > 0) {
        $update_sql = "UPDATE students SET fullname = ?, address = ?, parent_name = ?, parent_contact = ?, photo = ? WHERE id = ?";
        $update_result = safeQuery($update_sql, [$fullname, $address, $parent_name, $parent_contact, $photo, $student_record_id], 'sssssi');
        
        if($update_result !== false && $update_result !== null) {
            $update_success_flag = true;
        }
    }
    
    if($update_success_flag) {
        // Update users table phone and fullname
        $update_user_sql = "UPDATE users SET phone = ?, fullname = ? WHERE id = ?";
        safeQuery($update_user_sql, [$phone, $fullname, $student_id], 'ssi');
        
        // Update session fullname
        $_SESSION['fullname'] = $fullname;
        $student['fullname'] = $fullname;
        $student['phone'] = $phone;
        $student['address'] = $address;
        $student['parent_name'] = $parent_name;
        $student['parent_contact'] = $parent_contact;
        $student['photo'] = $photo;
        
        $update_success = "Profile updated successfully!";
        
        // Refresh the page to show updated data
        echo '<meta http-equiv="refresh" content="1">';
    } else {
        $update_error = "Failed to update profile. Please try again.";
    }
}

// Get attendance statistics for the logged-in student
$total_present_result = safeQuery(
    "SELECT COUNT(*) as count FROM attendance_log 
     WHERE student_id = ? AND log_type IN ('morning_in', 'afternoon_in')",
    [$student_record_id], 'i'
);
$total_present = ($total_present_result && mysqli_num_rows($total_present_result) > 0) ? mysqli_fetch_assoc($total_present_result)['count'] : 0;

$total_late_result = safeQuery(
    "SELECT COUNT(*) as count FROM attendance_log 
     WHERE student_id = ? AND status = 'late'",
    [$student_record_id], 'i'
);
$total_late = ($total_late_result && mysqli_num_rows($total_late_result) > 0) ? mysqli_fetch_assoc($total_late_result)['count'] : 0;

$total_attendance_result = safeQuery(
    "SELECT COUNT(DISTINCT log_date) as count FROM attendance_log 
     WHERE student_id = ?",
    [$student_record_id], 'i'
);
$total_attendance_days = ($total_attendance_result && mysqli_num_rows($total_attendance_result) > 0) ? mysqli_fetch_assoc($total_attendance_result)['count'] : 0;

// Calculate punctuality rate
$punctuality_rate = 0;
if($total_present > 0) {
    $punctuality_rate = round((($total_present - $total_late) / $total_present) * 100);
}

// Debug log for stats
error_log("Student Profile Stats - Student ID: $student_record_id, Present: $total_present, Late: $total_late, Days: $total_attendance_days");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>My Profile - Smart Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <style>
        .profile-container {
            padding: 24px;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--pink) 0%, var(--pink-dark) 100%);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 24px;
            color: white;
            text-align: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 20px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar.default {
            background: var(--white-smoke);
            color: var(--pink-dark);
            font-size: 48px;
        }

        .profile-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .profile-role {
            font-size: 14px;
            opacity: 0.9;
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 4px 16px;
            border-radius: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--grey);
            transition: all 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--pink);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--grey-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .card {
            border-radius: 20px;
            border: 1px solid var(--grey);
            background: var(--white);
            margin-bottom: 24px;
        }

        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--grey);
            font-weight: 600;
            font-size: 15px;
            color: #444;
            background: var(--white);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-header i {
            color: var(--pink);
            width: 24px;
        }

        .card-header span {
            flex: 1;
        }

        .card-body {
            padding: 24px;
        }

        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid var(--grey);
        }

        .info-label {
            width: 140px;
            font-weight: 500;
            color: #666;
            font-size: 13px;
        }

        .info-value {
            flex: 1;
            color: #333;
            font-size: 14px;
        }

        .info-value a {
            color: var(--pink);
            text-decoration: none;
        }

        .info-value a:hover {
            text-decoration: underline;
        }

        .photo-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 24px;
        }

        .photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 12px;
            border: 3px solid var(--pink);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: var(--grey-light);
            cursor: pointer;
            transition: all 0.2s;
        }

        .photo-preview:hover {
            opacity: 0.8;
            transform: scale(1.02);
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-preview.default {
            font-size: 48px;
            color: var(--pink);
        }

        .btn-edit {
            background: var(--pink);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-edit:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-save {
            background: #4caf50;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }

        .btn-save:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-cancel {
            background: #ccc;
            color: #666;
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }

        .btn-cancel:hover {
            background: #bbb;
        }

        .edit-mode {
            display: none;
        }

        .view-mode {
            display: block;
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

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .text-muted {
            color: #999;
            font-size: 11px;
        }

        .main-content {
            margin-top: var(--navbar-height);
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - var(--navbar-height));
            width: 100%;
            overflow-x: hidden;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
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
            .profile-container {
                padding: 16px;
            }
            
            .profile-header {
                padding: 24px;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
            }
            
            .profile-name {
                font-size: 18px;
            }
            
            .info-row {
                flex-direction: column;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 4px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .card-header {
                padding: 14px 18px;
            }
            
            .card-body {
                padding: 16px 18px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/student_navbar.php'; ?>
    <?php include '../includes/student_sidebar.php'; ?>
    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="main-content">
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar <?php echo (!empty($student['photo']) && $student['photo'] != 'default_student.png' && file_exists('../uploads/students/' . $student['photo'])) ? '' : 'default'; ?>">
                    <?php if(!empty($student['photo']) && $student['photo'] != 'default_student.png' && file_exists('../uploads/students/' . $student['photo'])): ?>
                        <img src="../uploads/students/<?php echo $student['photo']; ?>" alt="<?php echo htmlspecialchars($student['fullname']); ?>">
                    <?php else: ?>
                        <i class="fas fa-user-graduate"></i>
                    <?php endif; ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($student['fullname'] ?? $student_name); ?></div>
                <div class="profile-role">Student</div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_attendance_days; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-calendar-check"></i> Days Present
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_present; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-clock"></i> Total Logs
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_late; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-exclamation-triangle"></i> Times Late
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $punctuality_rate; ?>%</div>
                    <div class="stat-label">
                        <i class="fas fa-chart-line"></i> Punctuality
                    </div>
                </div>
            </div>
            
            <!-- Profile Information Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile Information</span>
                    <button class="btn-edit" id="editBtn">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                </div>
                <div class="card-body">
                    <!-- Success/Error Messages -->
                    <?php if($update_success): ?>
                        <div class="alert alert-success"><?php echo $update_success; ?></div>
                    <?php endif; ?>
                    <?php if($update_error): ?>
                        <div class="alert alert-danger"><?php echo $update_error; ?></div>
                    <?php endif; ?>
                    
                    <!-- View Mode -->
                    <div id="viewMode" class="view-mode">
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-user"></i> Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['fullname'] ?? $student_name); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-id-card"></i> LRN</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['lrn'] ?? 'Not set'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['email'] ?? ''); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-phone"></i> Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['phone'] ?? 'Not provided'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-layer-group"></i> Section</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['section_name'] ?? 'Not assigned'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-chalkboard-teacher"></i> Adviser</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['teacher_name'] ?? 'Not assigned'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-venus-mars"></i> Gender</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['gender'] ?? 'Not specified'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-calendar"></i> Birth Date</div>
                            <div class="info-value"><?php echo $student['birth_date'] ? date('F d, Y', strtotime($student['birth_date'])) : 'Not specified'; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-home"></i> Address</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($student['address'] ?? 'Not provided')); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-users"></i> Parent/Guardian</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['parent_name'] ?? 'Not provided'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-phone-alt"></i> Parent Contact</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['parent_contact'] ?? 'Not provided'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-qrcode"></i> RFID UID</div>
                            <div class="info-value">
                                <?php if(!empty($student['rfid_uid'])): ?>
                                    <code><?php echo htmlspecialchars($student['rfid_uid']); ?></code>
                                <?php else: ?>
                                    <span class="text-muted">Not assigned</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-calendar-alt"></i> Enrolled Date</div>
                            <div class="info-value"><?php echo $student['enrolled_date'] ? date('F d, Y', strtotime($student['enrolled_date'])) : 'Not specified'; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-user"></i> Username</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['username'] ?? $student_username); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-calendar-alt"></i> Member Since</div>
                            <div class="info-value"><?php echo isset($student['account_created']) ? date('F d, Y', strtotime($student['account_created'])) : date('F d, Y'); ?></div>
                        </div>
                    </div>
                    
                    <!-- Edit Mode -->
                    <div id="editMode" class="edit-mode">
                        <form method="POST" enctype="multipart/form-data" id="profileForm">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="photo-upload">
                                <div class="photo-preview" id="photoPreview" onclick="document.getElementById('photoInput').click()">
                                    <?php if(!empty($student['photo']) && $student['photo'] != 'default_student.png' && file_exists('../uploads/students/' . $student['photo'])): ?>
                                        <img src="../uploads/students/<?php echo $student['photo']; ?>" alt="Profile Photo">
                                    <?php else: ?>
                                        <i class="fas fa-user-graduate"></i>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="photo" id="photoInput" style="display: none;" accept="image/*" onchange="previewPhoto(this)">
                                <small class="text-muted">Click the image to change profile photo (JPG, PNG, GIF, Max 2MB)</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($student['fullname'] ?? $student_name); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" placeholder="Contact number">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3" placeholder="Home address"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Parent/Guardian Name</label>
                                <input type="text" name="parent_name" class="form-control" value="<?php echo htmlspecialchars($student['parent_name'] ?? ''); ?>" placeholder="Parent/Guardian name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Parent/Guardian Contact</label>
                                <input type="text" name="parent_contact" class="form-control" value="<?php echo htmlspecialchars($student['parent_contact'] ?? ''); ?>" placeholder="Parent contact number">
                            </div>
                            
                            <div class="action-buttons">
                                <button type="button" class="btn-cancel" onclick="cancelEdit()">Cancel</button>
                                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
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
        
        // Edit mode toggle
        const editBtn = document.getElementById('editBtn');
        const viewMode = document.getElementById('viewMode');
        const editMode = document.getElementById('editMode');
        
        function cancelEdit() {
            viewMode.style.display = 'block';
            editMode.style.display = 'none';
            editBtn.style.display = 'inline-flex';
        }
        
        if(editBtn) {
            editBtn.addEventListener('click', function() {
                viewMode.style.display = 'none';
                editMode.style.display = 'block';
                editBtn.style.display = 'none';
            });
        }
        
        // Preview photo before upload
        function previewPhoto(input) {
            const preview = document.getElementById('photoPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>