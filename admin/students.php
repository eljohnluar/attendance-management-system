<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('admin');
// Ensure $conn from config is available to this script and static analyzers
global $conn;

// Handle add student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $lrn = sanitize($_POST['lrn']);
    $fullname = sanitize($_POST['fullname']);
    $username = isset($_POST['username']) ? sanitize($_POST['username']) : null;
    $gender = sanitize($_POST['gender']);
    $birth_date = !empty($_POST['birth_date']) ? sanitize($_POST['birth_date']) : null;
    $parent_name = sanitize($_POST['parent_name']);
    $parent_contact = sanitize($_POST['parent_contact']);
    $address = sanitize($_POST['address']);
    $section_id = (int) $_POST['section_id'];
    $enrolled_date = !empty($_POST['enrolled_date']) ? sanitize($_POST['enrolled_date']) : null;
    $rfid_uid = !empty($_POST['rfid_uid']) ? sanitize($_POST['rfid_uid']) : null;
    $email = !empty($_POST['email']) ? sanitize($_POST['email']) : null;
    $phone = !empty($_POST['phone']) ? sanitize($_POST['phone']) : null;
    $password = !empty($_POST['password']) ? $_POST['password'] : null;
    $create_account = isset($_POST['create_account']) ? 1 : 0;

    // Handle photo upload
    $photo = 'default_student.png';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadFile($_FILES['photo'], 'students', ['jpg', 'jpeg', 'png', 'gif']);
        if ($upload_result) {
            $photo = basename($upload_result);
        }
    }

    // Check for duplicate LRN
    $existing_lrn = safeQuery("SELECT id FROM students WHERE lrn = ? LIMIT 1", [$lrn], 's');
    if ($existing_lrn && mysqli_num_rows($existing_lrn) > 0) {
        header('Location: students.php?error=duplicate_lrn');
        exit();
    }

    // Insert without QR code first
    $sql = "INSERT INTO students (lrn, rfid_uid, fullname, gender, birth_date, parent_name, parent_contact, address, section_id, enrolled_date, qr_code, photo, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";

    $insert_result = safeQuery($sql, [$lrn, $rfid_uid, $fullname, $gender, $birth_date, $parent_name, $parent_contact, $address, $section_id, $enrolled_date, null, $photo], 'ssssssssisss');

    if ($insert_result !== false) {
        $student_id = mysqli_insert_id($conn);

        // Generate QR code using a canonical student payload
        $qr_data = buildStudentQrData($student_id);
        $qr_filename = "qr_student_" . $student_id;
        $qr_filepath = generateQRCode($qr_data, $qr_filename);
        $qr_code_value = buildStudentQrCodeValue($student_id, $qr_filepath);

        // Update student record with QR code
        safeQuery("UPDATE students SET qr_code = ? WHERE id = ?", [$qr_code_value, $student_id], 'si');

        // Create student account if requested
        if ($create_account && $email) {
            // Use provided username or generate from fullname
            $final_username = $username;
            if (empty($final_username)) {
                $final_username = strtolower(str_replace(' ', '.', $fullname));
                $base_username = $final_username;
                $counter = 1;
                $check_user = safeQuery("SELECT id FROM users WHERE username = ?", [$final_username], 's');
                while ($check_user && mysqli_num_rows($check_user) > 0) {
                    $final_username = $base_username . $counter;
                    $counter++;
                    $check_user = safeQuery("SELECT id FROM users WHERE username = ?", [$final_username], 's');
                }
            }

            // Use provided password if available, otherwise default to student ID
            $password_to_hash = $password ? $password : ($student_id . '@student');
            $hashed_password = hashPassword($password_to_hash);

            $sql_account = "INSERT INTO users (username, password, role, email, phone, fullname, status, email_verified, registration_code, created_at) 
                           VALUES (?, ?, 'student', ?, ?, ?, 'active', 1, 'admin_created', NOW())";
            if (safeQuery($sql_account, [$final_username, $hashed_password, $email, $phone, $fullname], 'ssssss')) {
                $user_id = mysqli_insert_id($conn);
                // Link student to user account
                safeQuery("UPDATE students SET user_id = ? WHERE id = ?", [$user_id, $student_id], 'ii');
            }
        }

        header('Location: students.php?added=1');
        exit();
    } else {
        header('Location: students.php?error=add_failed');
        exit();
    }
}

// Handle edit student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = (int) $_POST['student_id'];
    $fullname = sanitize($_POST['fullname']);
    $username = isset($_POST['username']) ? sanitize($_POST['username']) : null;
    $gender = sanitize($_POST['gender']);
    $birth_date = !empty($_POST['birth_date']) ? sanitize($_POST['birth_date']) : null;
    $parent_name = sanitize($_POST['parent_name']);
    $parent_contact = sanitize($_POST['parent_contact']);
    $address = sanitize($_POST['address']);
    $section_id = (int) $_POST['section_id'];
    $status = sanitize($_POST['status']);
    $password = !empty($_POST['password']) ? $_POST['password'] : null;
    $email = !empty($_POST['email']) ? sanitize($_POST['email']) : null;
    $phone = !empty($_POST['phone']) ? sanitize($_POST['phone']) : null;

    // Handle photo upload
    $photo = $_POST['existing_photo'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadFile($_FILES['photo'], 'students', ['jpg', 'jpeg', 'png', 'gif']);
        if ($upload_result) {
            if ($photo && $photo != 'default_student.png' && file_exists('../uploads/students/' . $photo)) {
                unlink('../uploads/students/' . $photo);
            }
            $photo = basename($upload_result);
        }
    }

    $sql = "UPDATE students SET fullname = ?, gender = ?, birth_date = ?, parent_name = ?, parent_contact = ?, address = ?, section_id = ?, photo = ?, status = ? WHERE id = ?";
    $update_result = safeQuery($sql, [$fullname, $gender, $birth_date, $parent_name, $parent_contact, $address, $section_id, $photo, $status, $id], 'ssssssissi');

    if ($update_result !== false) {
        // Update user account if student has a linked user_id
        $student_data = safeQuery("SELECT user_id FROM students WHERE id = ?", [$id], 'i');
        if ($student_data && mysqli_num_rows($student_data) > 0) {
            $stu = mysqli_fetch_assoc($student_data);
            if ($stu['user_id']) {
                // Update user account
                $update_fields = [];
                $update_params = [];

                if (!empty($username)) {
                    $update_fields[] = "username = ?";
                    $update_params[] = $username;
                }
                if (!empty($email)) {
                    $update_fields[] = "email = ?";
                    $update_params[] = $email;
                }
                if (!empty($phone)) {
                    $update_fields[] = "phone = ?";
                    $update_params[] = $phone;
                }
                if (!empty($fullname)) {
                    $update_fields[] = "fullname = ?";
                    $update_params[] = $fullname;
                }
                if (!empty($password)) {
                    $update_fields[] = "password = ?";
                    $update_params[] = hashPassword($password);
                }

                if (!empty($update_fields)) {
                    $update_params[] = $stu['user_id'];
                    $update_user_sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
                    safeQuery($update_user_sql, $update_params, str_repeat('s', count($update_fields)) . 'i');
                }
            }
        }
        header('Location: students.php?updated=1');
        exit();
    } else {
        header('Location: students.php?error=update_failed');
        exit();
    }
}

// Handle delete student
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    safeQuery("DELETE FROM attendance_log WHERE student_id = ?", [$id], 'i');

    $student = safeQuery("SELECT photo, user_id FROM students WHERE id = ?", [$id], 'i');
    if ($student && $stu = mysqli_fetch_assoc($student)) {
        if ($stu['photo'] && $stu['photo'] != 'default_student.png' && file_exists('../uploads/students/' . $stu['photo'])) {
            unlink('../uploads/students/' . $stu['photo']);
        }
        // Delete associated user account if exists
        if ($stu['user_id']) {
            safeQuery("DELETE FROM users WHERE id = ?", [$stu['user_id']], 'i');
        }
    }

    $result = safeQuery("DELETE FROM students WHERE id = ?", [$id], 'i');

    if ($result) {
        header('Location: students.php?deleted=1');
        exit();
    } else {
        header('Location: students.php?error=delete_failed');
        exit();
    }
}

// Handle update RFID
if (isset($_GET['update_rfid'])) {
    $id = (int) $_GET['update_rfid'];
    $rfid_uid = sanitize($_GET['rfid_uid']);
    safeQuery("UPDATE students SET rfid_uid = ? WHERE id = ?", [$rfid_uid, $id], 'si');
    header('Location: students.php?rfid_updated=1');
    exit();
}

// Handle regenerate QR
if (isset($_GET['regenerate_qr'])) {
    $id = (int) $_GET['regenerate_qr'];
    if ($id <= 0) {
        header('Location: students.php?qr_regenerate_failed=1');
        exit();
    }

    // Use student ID as QR data for easy scanning
    $new_qr_data = buildStudentQrData($id);
    $new_qr_filename = "qr_student_" . $id;
    $new_qr_filepath = generateQRCode($new_qr_data, $new_qr_filename);
    $normalized_qr_path = normalizeQrFilepath($new_qr_filepath);
    $full_qr_path = '../uploads/' . $normalized_qr_path;

    if (isScannableQrImagePath($new_qr_filepath) && file_exists($full_qr_path) && @getimagesize($full_qr_path) !== false) {
        $new_qr_value = buildStudentQrCodeValue($id, $new_qr_filepath);
        safeQuery("UPDATE students SET qr_code = ? WHERE id = ?", [$new_qr_value, $id], 'si');
        header('Location: students.php?qr_regenerated=1');
        exit();
    }

    header('Location: students.php?qr_regenerate_failed=1');
    exit();
}

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$section_filter = isset($_GET['section']) ? (int) $_GET['section'] : 0;

$sql = "SELECT s.*, sec.section_name, yl.level_name 
        FROM students s
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
        WHERE (s.fullname LIKE ? OR s.lrn LIKE ? OR s.rfid_uid LIKE ?)";
$params = ["%$search%", "%$search%", "%$search%"];
$types = "sss";

if ($section_filter > 0) {
    $sql .= " AND s.section_id = ?";
    $params[] = $section_filter;
    $types .= "i";
}

$sql .= " ORDER BY s.id DESC";
$students_result = safeQuery($sql, $params, $types);

// Initialize $students as an empty array if query fails
$students = ($students_result !== false) ? $students_result : [];

$sections_result = safeQuery("SELECT s.*, yl.level_name FROM sections s 
                       JOIN year_levels yl ON s.year_level_id = yl.id 
                       WHERE s.status = 'active' ORDER BY yl.sort_order, s.section_name");

// Initialize $sections as an empty array if query fails
$sections = ($sections_result !== false) ? $sections_result : [];

// Check for success messages
$show_added = isset($_GET['added']);
$show_updated = isset($_GET['updated']);
$show_deleted = isset($_GET['deleted']);
$show_rfid_updated = isset($_GET['rfid_updated']);
$show_qr_regenerated = isset($_GET['qr_regenerated']);
$show_qr_regenerate_failed = isset($_GET['qr_regenerate_failed']);
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Students - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <style>
        .btn-primary .fa-plus {
            color: white;
        }

        .students-container {
            padding: 24px;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-icon {
            padding: 6px 10px;
            background: transparent;
            border: 1px solid var(--grey);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            color: #666;
        }

        .btn-icon:hover {
            background: var(--pink-light);
            border-color: var(--pink);
            color: var(--pink);
        }

        .btn-icon.danger:hover {
            background: #ffebee;
            border-color: #f44336;
            color: #f44336;
        }

        .btn-icon.success:hover {
            background: #e8f5e9;
            border-color: #4caf50;
            color: #4caf50;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            object-fit: cover;
            overflow: hidden;
        }

        .student-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .student-avatar.default {
            background: var(--pink-light);
            color: var(--pink);
        }

        .photo-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            border: 2px solid var(--grey);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 16px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-success {
            background: #e8f5e9;
            color: #4caf50;
        }

        .badge-danger {
            background: #ffebee;
            color: #f44336;
        }

        .badge-warning {
            background: #fff3e0;
            color: #ff9800;
        }

        .badge-info {
            background: #e3f2fd;
            color: #2196f3;
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

        .toast-error {
            background: #f44336;
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

        .confirm-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1002;
        }

        .confirm-modal.show {
            display: flex;
        }

        .confirm-content {
            background: white;
            border-radius: 16px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }

        .confirm-content i {
            font-size: 48px;
            color: #f44336;
            margin-bottom: 16px;
        }

        .confirm-content h3 {
            margin-bottom: 8px;
        }

        .confirm-content p {
            color: #666;
            margin-bottom: 20px;
        }

        .confirm-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .main-content {
            margin-top: 60px;
            padding: 0;
            min-height: calc(100vh - 60px);
        }

        @media (min-width: 1024px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        @media (max-width: 1023px) {
            .main-content {
                margin-left: 0;
            }

            .students-container {
                padding: 16px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/admin_navbar.php'; ?>
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-content">
        <div class="students-container">
            <!-- Toast Notifications -->
            <?php if ($show_added): ?>
                <div class="toast-notification toast-success" id="successToast"><i class="fas fa-check-circle"></i> Student
                    added successfully!</div>
                <script>setTimeout(() => { const t = document.getElementById('successToast'); if (t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 3000);</script>
            <?php endif; ?>
            <?php if ($show_updated): ?>
                <div class="toast-notification toast-success" id="updateToast"><i class="fas fa-check-circle"></i> Student
                    updated successfully!</div>
                <script>setTimeout(() => { const t = document.getElementById('updateToast'); if (t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 3000);</script>
            <?php endif; ?>
            <?php if ($show_deleted): ?>
                <div class="toast-notification toast-success" id="deleteToast"><i class="fas fa-check-circle"></i> Student
                    deleted successfully!</div>
                <script>setTimeout(() => { const t = document.getElementById('deleteToast'); if (t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 3000);</script>
            <?php endif; ?>
            <?php if ($show_rfid_updated): ?>
                <div class="toast-notification toast-success" id="rfidToast"><i class="fas fa-check-circle"></i> RFID
                    updated successfully!</div>
                <script>setTimeout(() => { const t = document.getElementById('rfidToast'); if (t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 3000);</script>
            <?php endif; ?>
            <?php if ($show_qr_regenerated): ?>
                <div class="toast-notification toast-success" id="qrToast"><i class="fas fa-check-circle"></i> QR Code
                    regenerated successfully!</div>
                <script>setTimeout(() => { const t = document.getElementById('qrToast'); if (t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 3000);</script>
            <?php endif; ?>
            <?php if ($show_qr_regenerate_failed): ?>
                <div class="toast-notification toast-error" id="qrFailToast"><i class="fas fa-exclamation-circle"></i> QR regeneration failed. Please check internet/server connectivity and try again.</div>
                <script>setTimeout(() => { const t = document.getElementById('qrFailToast'); if (t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 4500);</script>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="toast-notification toast-error" id="errorToast"><i class="fas fa-exclamation-circle"></i>
                    <?php echo ucfirst(str_replace('_', ' ', $error_msg)); ?>
                </div>
                <script>setTimeout(() => { const t = document.getElementById('errorToast'); if (t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 3000);</script>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-graduate"></i>
                    <span>Student Management</span>
                    <button onclick="showAddModal()" class="btn btn-primary" style="margin-left: auto;">
                        <i class="fas fa-plus"></i> <span>Add Student</span>
                    </button>
                </div>
                <div class="card-body">
                    <div class="search-bar">
                        <div class="filter-group">
                            <input type="text" id="searchInput" class="form-control"
                                placeholder="Search by name, LRN, or RFID..."
                                value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <select id="sectionFilter" class="form-control">
                                <option value="0">All Sections</option>
                                <?php if ($sections !== false && mysqli_num_rows($sections) > 0): ?>
                                    <?php while ($sec = mysqli_fetch_assoc($sections)): ?>
                                        <option value="<?php echo $sec['id']; ?>" <?php echo $section_filter == $sec['id'] ? 'selected' : ''; ?>>
                                            <?php echo $sec['level_name'] . ' - ' . $sec['section_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Student Name</th>
                                    <th>LRN</th>
                                    <th>RFID UID</th>
                                    <th>Year & Section</th>
                                    <th>Gender</th>
                                    <th>Parent Name</th>
                                    <th>Parent Contact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="studentsTableBody">
                                <?php if ($students !== false && mysqli_num_rows($students) > 0): ?>
                                    <?php while ($student = mysqli_fetch_assoc($students)): ?>
                                        <tr data-name="<?php echo strtolower($student['fullname'] . ' ' . $student['lrn']); ?>"
                                            data-section="<?php echo $student['section_id']; ?>">
                                            <td>
                                                <?php if ($student['photo'] && $student['photo'] != 'default_student.png' && file_exists('../uploads/students/' . $student['photo'])): ?>
                                                    <div class="student-avatar"><img
                                                            src="uploads/students/<?php echo $student['photo']; ?>"
                                                            alt="<?php echo htmlspecialchars($student['fullname']); ?>"></div>
                                                <?php else: ?>
                                                    <div class="student-avatar default"><i class="fas fa-user"></i></div>
                                                <?php endif; ?>
                            </div>
                            <td>
                                <?php echo htmlspecialchars($student['fullname']); ?>
                        </div>
                        <td>
                            <?php echo htmlspecialchars($student['lrn']); ?>
                    </div>
                    <td>
                        <?php if ($student['rfid_uid']): ?>
                            <?php echo htmlspecialchars($student['rfid_uid']); ?>
                            <button class="btn-icon" onclick="editRFID(<?php echo $student['id']; ?>)" title="Edit RFID"
                                style="margin-left: 5px;"><i class="fas fa-edit"></i></button>
                        <?php else: ?>
                            <span class="text-muted">Not set</span>
                            <button class="btn-icon" onclick="editRFID(<?php echo $student['id']; ?>)" title="Set RFID"><i
                                    class="fas fa-plus"></i></button>
                        <?php endif; ?>
                </div>
                <td>
                    <?php echo htmlspecialchars($student['level_name'] . ' - ' . $student['section_name']); ?>
            </div>
            <td>
                <?php echo htmlspecialchars($student['gender']); ?>
                </div>
            <td>
                <?php echo htmlspecialchars($student['parent_name']); ?>
                </div>
            <td>
                <?php echo htmlspecialchars($student['parent_contact']); ?>
                </div>
            <td><span class="badge badge-<?php echo $student['status'] == 'active' ? 'success' : 'danger'; ?>">
                    <?php echo ucfirst($student['status']); ?>
                </span>
                </div>
            <td>
                <div class="action-buttons">
                    <button class="btn-icon"
                        data-qr="<?php echo htmlspecialchars((string) ($student['qr_code'] ?? ''), ENT_QUOTES); ?>"
                        onclick="viewQR(<?php echo (int) $student['id']; ?>, this.dataset.qr)" title="View QR Code"><i
                            class="fas fa-qrcode"></i></button>
                    <button class="btn-icon" onclick="editStudent(<?php echo $student['id']; ?>)" title="Edit Student"><i
                            class="fas fa-edit"></i></button>
                    <button class="btn-icon danger"
                        onclick="showDeleteConfirm(<?php echo $student['id']; ?>, '<?php echo addslashes($student['fullname']); ?>')"
                        title="Delete"><i class="fas fa-trash"></i></button>
                </div>
                </div>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr id="noResultsRow">
                <td colspan="10" class="text-center" style="padding: 40px;"><i class="fas fa-user-graduate"
                        style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>No students found
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
        </table>
        </div>
        </div>
        </div>
        </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteConfirmModal" class="confirm-modal">
            <div class="confirm-content">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Delete Student</h3>
                <p id="deleteMessage">Are you sure you want to delete this student? This will remove all attendance
                    records and cannot be undone.</p>
                <div class="confirm-buttons">
                    <button class="btn btn-secondary" onclick="closeDeleteConfirm()">Cancel</button>
                    <button class="btn btn-danger" id="confirmDeleteBtn" style="background: #f44336;">Delete</button>
                </div>
            </div>
        </div>

        <!-- Add Student Modal -->
        <div id="addModal" class="modal">
            <div class="modal-content" style="max-width: 650px;">
                <div class="modal-header">
                    <h3><i class="fas fa-user-plus"></i> Add New Student</h3>
                    <button class="close" onclick="closeModal('addModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addStudentForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add">

                        <div class="photo-upload">
                            <div class="photo-preview" id="photoPreview"><i class="fas fa-user"
                                    style="font-size: 48px; color: var(--grey-dark);"></i></div>
                            <div class="form-group" style="width: 100%;">
                                <label class="form-label">Student Photo</label>
                                <input type="file" name="photo" id="photoInput" class="form-control" accept="image/*"
                                    onchange="previewPhoto(this)">
                                <small class="text-muted">JPG, PNG, GIF (Max 2MB)</small>
                            </div>
                        </div>

                        <div class="grid-dashboard" style="gap: 12px;">
                            <div class="form-group"><label class="form-label">LRN <span
                                        class="text-muted">*</span></label><input type="text" name="lrn"
                                    class="form-control" placeholder="12-digit LRN" required></div>
                            <div class="form-group"><label class="form-label">Full Name <span
                                        class="text-muted">*</span></label><input type="text" name="fullname"
                                    class="form-control" placeholder="Full name" required></div>
                            <div class="form-group"><label class="form-label">RFID UID</label><input type="text"
                                    name="rfid_uid" class="form-control" placeholder="Optional"></div>
                            <div class="form-group"><label class="form-label">Gender</label><select name="gender"
                                    class="form-control">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select></div>
                            <div class="form-group"><label class="form-label">Birth Date</label><input type="date"
                                    name="birth_date" class="form-control"></div>
                            <div class="form-group"><label class="form-label">Parent Name</label><input type="text"
                                    name="parent_name" class="form-control" placeholder="Parent/Guardian name"></div>
                            <div class="form-group"><label class="form-label">Parent Contact</label><input type="text"
                                    name="parent_contact" class="form-control" placeholder="Phone number"></div>
                            <div class="form-group"><label class="form-label">Address</label><textarea name="address"
                                    class="form-control" rows="2" placeholder="Home address"></textarea></div>
                            <div class="form-group"><label class="form-label">Section <span
                                        class="text-muted">*</span></label>
                                <select name="section_id" class="form-control" required>
                                    <option value="">Select Section</option>
                                    <?php
                                    $secs_result = safeQuery("SELECT s.*, yl.level_name FROM sections s JOIN year_levels yl ON s.year_level_id = yl.id WHERE s.status = 'active' ORDER BY yl.sort_order, s.section_name");
                                    if ($secs_result !== false):
                                        while ($sec = mysqli_fetch_assoc($secs_result)): ?>
                                            <option value="<?php echo $sec['id']; ?>">
                                                <?php echo $sec['level_name'] . ' - ' . $sec['section_name']; ?>
                                            </option>
                                        <?php endwhile;
                                    endif; ?>
                                </select>
                            </div>
                            <div class="form-group"><label class="form-label">Enrolled Date</label><input type="date"
                                    name="enrolled_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <hr style="margin: 16px 0;">
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" name="create_account" value="1" checked> Create student login
                                account
                            </label>
                        </div>
                        <div id="accountFields">
                            <div class="grid-dashboard" style="gap: 12px;">
                                <div class="form-group"><label class="form-label">Username <span
                                            class="text-muted">(optional)</span></label>
                                    <input type="text" name="username" class="form-control"
                                        placeholder="Leave empty to auto-generate">
                                    <small class="text-muted">Auto-generated username will be:
                                        firstname.lastname</small>
                                </div>
                                <div class="form-group"><label class="form-label">Email <span
                                            class="text-muted">*</span></label><input type="email" name="email"
                                        class="form-control" placeholder="student@email.com" required></div>
                                <div class="form-group"><label class="form-label">Phone</label><input type="text"
                                        name="phone" class="form-control" placeholder="Contact number"></div>
                                <div class="form-group"><label class="form-label">Password <span
                                            class="text-muted">(optional)</span></label>
                                    <input type="password" name="password" class="form-control"
                                        placeholder="Enter password (leave empty for auto-generated)">
                                    <small class="text-muted">Auto-generated password will be: [Student
                                        ID]@student</small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button class="btn btn-primary" onclick="document.getElementById('addStudentForm').submit()"><i
                            class="fas fa-save"></i> Save Student</button>
                </div>
            </div>
        </div>

        <!-- Edit Student Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3><i class="fas fa-edit"></i> Edit Student</h3>
                    <button class="close" onclick="closeModal('editModal')">&times;</button>
                </div>
                <div class="modal-body" id="editModalBody"></div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button class="btn btn-primary" onclick="document.getElementById('editStudentForm')?.submit()"><i
                            class="fas fa-save"></i> Update Student</button>
                </div>
            </div>
        </div>

        <!-- Edit RFID Modal -->
        <div id="rfidModal" class="modal">
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h3><i class="fas fa-rss"></i> Set RFID UID</h3><button class="close"
                        onclick="closeModal('rfidModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label class="form-label">RFID UID</label><input type="text" id="rfidUid"
                            class="form-control" placeholder="Scan or enter RFID number" autofocus><small
                            class="text-muted">Scan the RFID card or enter the UID manually</small></div>
                    <input type="hidden" id="rfidStudentId">
                </div>
                <div class="modal-footer"><button class="btn btn-secondary"
                        onclick="closeModal('rfidModal')">Cancel</button><button class="btn btn-primary"
                        onclick="saveRFID()">Save RFID</button></div>
            </div>
        </div>

        <!-- QR Code Modal -->
        <div id="qrModal" class="modal">
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h3><i class="fas fa-qrcode"></i> Student QR Code</h3><button class="close"
                        onclick="closeModal('qrModal')">&times;</button>
                </div>
                <div class="modal-body text-center">
                    <div id="qrImage"></div>
                    <div style="margin-top: 16px;"><button class="btn btn-primary" onclick="printQR()"><i
                                class="fas fa-print"></i> Print</button><button class="btn btn-outline"
                            onclick="regenerateQR()"><i class="fas fa-sync-alt"></i> Regenerate</button></div>
                </div>
            </div>
        </div>

        <script>
            let deleteStudentId = null, currentStudentId = null, currentQRData = null;

            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            function closeSidebar() { if (sidebar) sidebar.classList.remove('open'); if (overlay) overlay.classList.remove('active'); document.body.style.overflow = ''; }
            if (overlay) overlay.addEventListener('click', closeSidebar);
            window.addEventListener('resize', function () { if (window.innerWidth >= 1024) { if (sidebar) sidebar.classList.remove('open'); if (overlay) overlay.classList.remove('active'); document.body.style.overflow = ''; } });

            // Account creation is always on for admin (checkbox is checked by default)
            document.getElementById('accountFields').style.display = 'block';

            function filterTable() {
                let searchValue = document.getElementById('searchInput').value.toLowerCase();
                let sectionValue = document.getElementById('sectionFilter').value;
                let rows = document.querySelectorAll('#studentsTableBody tr');
                let hasResults = false;
                rows.forEach(row => {
                    if (row.id === 'noResultsRow') return;
                    let name = row.getAttribute('data-name') || '';
                    let section = row.getAttribute('data-section') || '';
                    let matchesSearch = name.includes(searchValue);
                    let matchesSection = (sectionValue === '0' || section === sectionValue);
                    if (matchesSearch && matchesSection) { row.style.display = ''; hasResults = true; }
                    else { row.style.display = 'none'; }
                });
                let noResultsRow = document.getElementById('noResultsRow');
                if (!hasResults && rows.length > 0) {
                    if (!noResultsRow) {
                        const tbody = document.getElementById('studentsTableBody');
                        const tr = document.createElement('tr'); tr.id = 'noResultsRow';
                        tr.innerHTML = '<td colspan="10" class="text-center" style="padding: 40px;"><i class="fas fa-search" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>No students found</div>';
                        tbody.appendChild(tr);
                    } else { noResultsRow.style.display = ''; }
                } else if (noResultsRow) { noResultsRow.style.display = 'none'; }
            }
            document.getElementById('searchInput').addEventListener('keyup', filterTable);
            document.getElementById('sectionFilter').addEventListener('change', filterTable);

            function showAddModal() { document.getElementById('addModal').classList.add('show'); }
            function closeModal(id) { document.getElementById(id).classList.remove('show'); }

            function previewPhoto(input) {
                const preview = document.getElementById('photoPreview');
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) { preview.innerHTML = `<img src="${e.target.result}" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">`; }
                    reader.readAsDataURL(input.files[0]);
                } else { preview.innerHTML = `<i class="fas fa-user" style="font-size: 48px; color: var(--grey-dark);"></i>`; }
            }

            function viewQR(id, qrCode) {
                currentStudentId = id;
                currentQRData = qrCode;
                const rawQr = String(qrCode || '').trim();
                if (!rawQr) {
                    document.getElementById('qrImage').innerHTML = '<p class="text-muted">QR code not available</p>';
                    document.getElementById('qrModal').classList.add('show');
                    return;
                }
                const parts = rawQr.split('|');
                let qrData = parts[0] || '';
                let qrFilepath = parts.length > 1 ? parts.slice(1).join('|') : '';
                if (!qrFilepath && /^(qrcodes\/|uploads\/qrcodes\/).+\.(png|jpg|jpeg|gif|webp|txt)$/i.test(qrData)) {
                    qrFilepath = qrData;
                    qrData = '';
                }
                if (!qrData && Number.isFinite(Number(id)) && Number(id) > 0) {
                    qrData = String(id);
                }
                if (qrFilepath) qrFilepath = qrFilepath.replace(/^\/+/, '').replace(/^uploads\//i, '');
                const qrFileUrl = qrFilepath ? `uploads/${qrFilepath}` : '';
                if (qrFileUrl) {
                    const fileImg = new Image();
                    fileImg.onload = function () { document.getElementById('qrImage').innerHTML = `<img src="${qrFileUrl}" style="max-width: 300px;">`; document.getElementById('qrModal').classList.add('show'); };
                    fileImg.onerror = function () {
                        const qrUrl = qrData ? `https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=${encodeURIComponent(qrData)}&choe=UTF-8` : '';
                        document.getElementById('qrImage').innerHTML = qrUrl ? `<img src="${qrUrl}" style="max-width: 300px;">` : '<p class="text-muted">QR image file not found</p>';
                        document.getElementById('qrModal').classList.add('show');
                    };
                    fileImg.src = qrFileUrl;
                } else {
                    const qrUrl = `https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=${encodeURIComponent(qrData)}&choe=UTF-8`;
                    document.getElementById('qrImage').innerHTML = `<img src="${qrUrl}" style="max-width: 300px;">`;
                    document.getElementById('qrModal').classList.add('show');
                }
            }

            function printQR() { let printContents = document.getElementById('qrImage').innerHTML; let originalContents = document.body.innerHTML; document.body.innerHTML = printContents; window.print(); document.body.innerHTML = originalContents; location.reload(); }
            function regenerateQR() { if (currentStudentId) window.location.search = `?regenerate_qr=${currentStudentId}`; }

            function editRFID(id) { document.getElementById('rfidStudentId').value = id; document.getElementById('rfidUid').value = ''; document.getElementById('rfidModal').classList.add('show'); document.getElementById('rfidUid').focus(); }
            function saveRFID() { const studentId = document.getElementById('rfidStudentId').value; const rfidUid = document.getElementById('rfidUid').value.trim(); if (!rfidUid) { alert('Please enter RFID UID'); return; } window.location.search = `?update_rfid=${studentId}&rfid_uid=${encodeURIComponent(rfidUid)}`; }

            function showDeleteConfirm(id, name) { deleteStudentId = id; document.getElementById('deleteMessage').innerHTML = `Are you sure you want to delete <strong>${escapeHtml(name)}</strong>? This will remove all attendance records and cannot be undone.`; document.getElementById('deleteConfirmModal').classList.add('show'); }
            function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
            function closeDeleteConfirm() { deleteStudentId = null; document.getElementById('deleteConfirmModal').classList.remove('show'); }
            document.getElementById('confirmDeleteBtn').addEventListener('click', function () { if (deleteStudentId) window.location.search = `?delete=${deleteStudentId}`; });

            function editStudent(id) {
                fetch(`api/get_student.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const sections = <?php
                            $sections_json = [];
                            $secs_result = safeQuery("SELECT s.*, yl.level_name FROM sections s JOIN year_levels yl ON s.year_level_id = yl.id WHERE s.status = 'active' ORDER BY yl.sort_order, s.section_name");
                            if ($secs_result !== false) {
                                while ($sec = mysqli_fetch_assoc($secs_result)) {
                                    $sections_json[] = $sec;
                                }
                            }
                            echo json_encode($sections_json);
                            ?>;
                            let sectionOptions = '';
                            sections.forEach(sec => { sectionOptions += `<option value="${sec.id}" ${sec.id == data.section_id ? 'selected' : ''}>${sec.level_name} - ${sec.section_name}</option>`; });
                            const modalBody = document.getElementById('editModalBody');
                            modalBody.innerHTML = `
                            <form id="editStudentForm" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="student_id" value="${data.id}">
                                <input type="hidden" name="existing_photo" value="${data.photo || 'default_student.png'}">
                                <div class="photo-upload">
                                    <div class="photo-preview" id="editPhotoPreview">${data.photo && data.photo !== 'default_student.png' ? `<img src="uploads/students/${data.photo}" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">` : `<i class="fas fa-user" style="font-size: 48px; color: var(--grey-dark);"></i>`}</div>
                                    <div class="form-group"><label class="form-label">Student Photo</label><input type="file" name="photo" id="editPhotoInput" class="form-control" accept="image/*" onchange="previewEditPhoto(this)"><small class="text-muted">Leave empty to keep current photo</small></div>
                                </div>
                                <div class="grid-dashboard" style="gap: 12px;">
                                    <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="fullname" class="form-control" value="${escapeHtml(data.fullname)}" required></div>
                                    <div class="form-group"><label class="form-label">Username <span class="text-muted">(for login)</span></label><input type="text" name="username" class="form-control" value="${escapeHtml(data.username || '')}" placeholder="Student username"></div>
                                    <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="${escapeHtml(data.email || '')}" placeholder="student@email.com"></div>
                                    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="${escapeHtml(data.phone || '')}" placeholder="Contact number"></div>
                                    <div class="form-group"><label class="form-label">Gender</label><select name="gender" class="form-control"><option value="Male" ${data.gender == 'Male' ? 'selected' : ''}>Male</option><option value="Female" ${data.gender == 'Female' ? 'selected' : ''}>Female</option></select></div>
                                    <div class="form-group"><label class="form-label">Birth Date</label><input type="date" name="birth_date" class="form-control" value="${data.birth_date || ''}"></div>
                                    <div class="form-group"><label class="form-label">Parent Name</label><input type="text" name="parent_name" class="form-control" value="${escapeHtml(data.parent_name || '')}"></div>
                                    <div class="form-group"><label class="form-label">Parent Contact</label><input type="text" name="parent_contact" class="form-control" value="${escapeHtml(data.parent_contact || '')}"></div>
                                    <div class="form-group"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2">${escapeHtml(data.address || '')}</textarea></div>
                                    <div class="form-group"><label class="form-label">Section *</label><select name="section_id" class="form-control" required><option value="">Select Section</option>${sectionOptions}</select></div>
                                    <div class="form-group"><label class="form-label">Status</label><select name="status" class="form-control"><option value="active" ${data.status == 'active' ? 'selected' : ''}>Active</option><option value="inactive" ${data.status == 'inactive' ? 'selected' : ''}>Inactive</option><option value="graduated" ${data.status == 'graduated' ? 'selected' : ''}>Graduated</option></select></div>
                                    <div class="form-group"><label class="form-label">Password <span class="text-muted">(leave empty to keep current)</span></label><input type="password" name="password" class="form-control" placeholder="New password"></div>
                                </div>
                            </form>
                        `;
                            document.getElementById('editModal').classList.add('show');
                        } else {
                            console.error('API error:', data.error || data, data.db_error || '');
                            const msg = data.error ? (data.error + (data.db_error ? ': ' + data.db_error : '')) : 'Failed to load student details';
                            alert(msg);
                        }
                    }).catch(error => { console.error('Error:', error); alert('Failed to load student details'); });
            }

            function previewEditPhoto(input) {
                const preview = document.getElementById('editPhotoPreview');
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) { preview.innerHTML = `<img src="${e.target.result}" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">`; }
                    reader.readAsDataURL(input.files[0]);
                }
            }

            document.getElementById('rfidUid')?.addEventListener('keypress', function (e) { if (e.key === 'Enter') saveRFID(); });
            window.onclick = function (event) {
                document.querySelectorAll('.modal').forEach(modal => { if (event.target === modal) modal.classList.remove('show'); });
                if (event.target === document.getElementById('deleteConfirmModal')) closeDeleteConfirm();
            }
        </script>
</body>

</html>
