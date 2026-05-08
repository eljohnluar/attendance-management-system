<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('admin');

// Handle delete teacher
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get user_id and photo first
    $teacher = safeQuery("SELECT user_id, photo FROM teachers WHERE id = ?", [$id], 'i');
    if($t = mysqli_fetch_assoc($teacher)) {
        $user_id = $t['user_id'];
        $photo = $t['photo'];
        
        // Delete photo file if exists
        if($photo && $photo != 'default_avatar.png' && file_exists('../uploads/teachers/' . $photo)) {
            unlink('../uploads/teachers/' . $photo);
        }
        
        // First, update sections to remove teacher assignment
        safeQuery("UPDATE sections SET teacher_id = NULL WHERE teacher_id = ?", [$id], 'i');
        
        // Delete from teachers table
        $delete_teacher = safeQuery("DELETE FROM teachers WHERE id = ?", [$id], 'i');
        
        // Delete from users table
        $delete_user = safeQuery("DELETE FROM users WHERE id = ?", [$user_id], 'i');
        
        if($delete_teacher && $delete_user) {
            header('Location: teachers.php?deleted=1');
            exit();
        } else {
            header('Location: teachers.php?error=delete_failed');
            exit();
        }
    } else {
        header('Location: teachers.php?error=not_found');
        exit();
    }
}

// Handle update teacher status (approve/reject)
if(isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    safeQuery("UPDATE users SET status = 'active' WHERE id = ? AND role = 'teacher'", [$id], 'i');
    header('Location: teachers.php?approved=1');
    exit();
}

if(isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    safeQuery("UPDATE users SET status = 'rejected' WHERE id = ? AND role = 'teacher'", [$id], 'i');
    header('Location: teachers.php?rejected=1');
    exit();
}

// Handle add teacher (admin created - auto approved)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $fullname = sanitize($_POST['fullname']);
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $gender = sanitize($_POST['gender']);
    
    // Handle photo upload
    $photo = 'default_avatar.png';
    if(isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadFile($_FILES['photo'], 'teachers', ['jpg', 'jpeg', 'png', 'gif']);
        if($upload_result) {
            $photo = basename($upload_result);
        }
    }
    
    // Check if username exists
    $check = safeQuery("SELECT id FROM users WHERE username = ?", [$username], 's');
    if(mysqli_num_rows($check) > 0) {
        $error = 'Username already exists';
    } else {
        $hashed = hashPassword($password);
        
        $sql = "INSERT INTO users (username, password, role, email, phone, fullname, status, email_verified, registration_code, created_at) 
                VALUES (?, ?, 'teacher', ?, ?, ?, 'active', 1, 'admin_created', NOW())";
        
        $user_id = safeQuery($sql, [$username, $hashed, $email, $phone, $fullname], 'sssss');
        if($user_id > 0) {
            $teacher_id = 'TCH' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
            safeQuery("INSERT INTO teachers (user_id, teacher_id, gender, photo, registered_date) VALUES (?, ?, ?, ?, CURDATE())", 
                     [$user_id, $teacher_id, $gender, $photo], 'isss');
            header('Location: teachers.php?added=1');
            exit();
        } else {
            $error = 'Failed to add teacher';
        }
    }
}

// Handle update teacher
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = (int)$_POST['teacher_id'];
    $fullname = sanitize($_POST['fullname']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $gender = sanitize($_POST['gender']);
    $status = sanitize($_POST['status']);
    $user_status = sanitize($_POST['user_status']);
    
    // Handle photo upload
    $photo = $_POST['existing_photo'];
    if(isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadFile($_FILES['photo'], 'teachers', ['jpg', 'jpeg', 'png', 'gif']);
        if($upload_result) {
            if($photo && $photo != 'default_avatar.png' && file_exists('../uploads/teachers/' . $photo)) {
                unlink('../uploads/teachers/' . $photo);
            }
            $photo = basename($upload_result);
        }
    }
    
    // Get user_id
    $teacher_info = safeQuery("SELECT user_id FROM teachers WHERE id = ?", [$id], 'i');
    if($t = mysqli_fetch_assoc($teacher_info)) {
        $user_id = $t['user_id'];
        safeQuery("UPDATE users SET status = ?, email = ?, phone = ?, fullname = ? WHERE id = ?", 
                 [$user_status, $email, $phone, $fullname, $user_id], 'ssssi');
    }
    
    safeQuery("UPDATE teachers SET gender = ?, photo = ? WHERE id = ?", 
             [$gender, $photo, $id], 'ssi');
    header('Location: teachers.php?updated=1');
    exit();
}

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$sql = "SELECT t.*, u.fullname, u.username, u.email, u.phone, u.status as user_status, u.created_at as user_created_at
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE (u.fullname LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
$params = ["%$search%", "%$search%", "%$search%"];
$types = "sss";

if($status_filter && $status_filter != 'all') {
    $sql .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY t.id DESC";
$teachers = safeQuery($sql, $params, $types);

// Check for success messages
$show_added = isset($_GET['added']);
$show_updated = isset($_GET['updated']);
$show_deleted = isset($_GET['deleted']);
$show_approved = isset($_GET['approved']);
$show_rejected = isset($_GET['rejected']);
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Teachers - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <style>
        .teachers-container {
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

        .teacher-avatar {
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

        .teacher-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .teacher-avatar.default {
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

        /* Status Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-active {
            background: #e8f5e9;
            color: #4caf50;
        }

        .badge-pending {
            background: #fff3e0;
            color: #ff9800;
        }

        .badge-inactive {
            background: #ffebee;
            color: #f44336;
        }

        .badge-rejected {
            background: #f44336;
            color: white;
        }

        /* Toast Notification */
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .toast-success {
            background: #4caf50;
        }

        .toast-error {
            background: #f44336;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        /* Delete Confirmation Modal */
        .confirm-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
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

        /* Status Filter */
        .status-filter {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 6px 14px;
            border-radius: 20px;
            background: var(--grey-light);
            border: 1px solid var(--grey);
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }

        .filter-btn.active {
            background: var(--pink);
            color: white;
            border-color: var(--pink);
        }

        @media (max-width: 768px) {
            .teachers-container {
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
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="main-content">
        <div class="teachers-container">
            <!-- Toast Notifications -->
            <?php if($show_added): ?>
                <div class="toast-notification toast-success" id="successToast">
                    <i class="fas fa-check-circle"></i> Teacher added successfully!
                </div>
                <script>setTimeout(() => { const t = document.getElementById('successToast'); if(t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 3000);</script>
            <?php endif; ?>
            
            <?php if($show_updated): ?>
                <div class="toast-notification toast-success" id="updateToast">
                    <i class="fas fa-check-circle"></i> Teacher updated successfully!
                </div>
                <script>setTimeout(() => { const t = document.getElementById('updateToast'); if(t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 3000);</script>
            <?php endif; ?>
            
            <?php if($show_deleted): ?>
                <div class="toast-notification toast-success" id="deleteToast">
                    <i class="fas fa-check-circle"></i> Teacher deleted successfully!
                </div>
                <script>setTimeout(() => { const t = document.getElementById('deleteToast'); if(t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 3000);</script>
            <?php endif; ?>
            
            <?php if($show_approved): ?>
                <div class="toast-notification toast-success" id="approveToast">
                    <i class="fas fa-check-circle"></i> Teacher approved successfully!
                </div>
                <script>setTimeout(() => { const t = document.getElementById('approveToast'); if(t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 3000);</script>
            <?php endif; ?>
            
            <?php if($show_rejected): ?>
                <div class="toast-notification toast-success" id="rejectToast">
                    <i class="fas fa-check-circle"></i> Teacher rejected successfully!
                </div>
                <script>setTimeout(() => { const t = document.getElementById('rejectToast'); if(t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 3000);</script>
            <?php endif; ?>
            
            <?php if($error_msg): ?>
                <div class="toast-notification toast-error" id="errorToast">
                    <i class="fas fa-exclamation-circle"></i> <?php echo ucfirst(str_replace('_', ' ', $error_msg)); ?>
                </div>
                <script>setTimeout(() => { const t = document.getElementById('errorToast'); if(t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 3000);</script>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teacher Management</span>
                    <button onclick="showAddModal()" class="btn btn-primary" style="margin-left: auto;">
                        <i class="fas fa-plus"></i>
                        <span>Add Teacher</span>
                    </button>
                </div>
                <div class="card-body">
                    <!-- Search and Filter Bar -->
                    <div class="search-bar">
                        <div class="filter-group">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search by name or username..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <select id="statusFilter" class="form-control" onchange="filterByStatus()">
                                <option value="all" <?php echo $status_filter == 'all' || !$status_filter ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Teachers Table -->
                    <div class="table-wrapper">
                        <table id="teachersTable">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Teacher ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Username</th>
                                    <th>Registered Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="teachersTableBody">
                                <?php if($teachers && mysqli_num_rows($teachers) > 0): ?>
                                    <?php while($teacher = mysqli_fetch_assoc($teachers)): ?>
                                    <tr data-name="<?php echo strtolower($teacher['fullname'] . ' ' . $teacher['username']); ?>" data-status="<?php echo $teacher['user_status']; ?>">
                                        <td>
                                            <?php if($teacher['photo'] && $teacher['photo'] != 'default_avatar.png' && file_exists('../uploads/teachers/' . $teacher['photo'])): ?>
                                                <div class="teacher-avatar">
                                                    <img src="uploads/teachers/<?php echo $teacher['photo']; ?>" alt="<?php echo htmlspecialchars($teacher['fullname']); ?>">
                                                </div>
                                            <?php else: ?>
                                                <div class="teacher-avatar default">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <td><?php echo htmlspecialchars($teacher['teacher_id']); ?></div>
                                        <td><?php echo htmlspecialchars($teacher['fullname']); ?></div>
                                        <td><?php echo htmlspecialchars($teacher['email']); ?></div>
                                        <td><?php echo htmlspecialchars($teacher['phone']); ?></div>
                                        <td><?php echo htmlspecialchars($teacher['username']); ?></div>
                                        <td><?php echo date('M d, Y', strtotime($teacher['registered_date'])); ?></div>
                                        <td>
                                            <span class="badge badge-<?php echo $teacher['user_status']; ?>">
                                                <?php echo ucfirst($teacher['user_status']); ?>
                                            </span>
                                        </div>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if($teacher['user_status'] == 'pending'): ?>
                                                    <button class="btn-icon success" onclick="approveTeacher(<?php echo $teacher['user_id']; ?>)" title="Approve">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                    <button class="btn-icon danger" onclick="rejectTeacher(<?php echo $teacher['user_id']; ?>)" title="Reject">
                                                        <i class="fas fa-times-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn-icon" onclick="editTeacherModal(<?php echo $teacher['id']; ?>)" title="Edit Teacher">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-icon danger" onclick="showDeleteConfirm(<?php echo $teacher['id']; ?>, '<?php echo addslashes($teacher['fullname']); ?>')" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr id="noResultsRow">
                                        <td colspan="9" class="text-center" style="padding: 40px; color: #999;">
                                            <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                                            No teachers found
                                        </div>
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
            <h3>Delete Teacher</h3>
            <p id="deleteMessage">Are you sure you want to delete this teacher? This action cannot be undone.</p>
            <div class="confirm-buttons">
                <button class="btn btn-secondary" onclick="closeDeleteConfirm()">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn" style="background: #f44336;">Delete</button>
            </div>
        </div>
    </div>
    
    <!-- Add Teacher Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Teacher</h3>
                <button class="close" onclick="closeModal('addModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addTeacherForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="photo-upload">
                        <div class="photo-preview" id="photoPreview">
                            <i class="fas fa-user" style="font-size: 48px; color: var(--grey-dark);"></i>
                        </div>
                        <div class="form-group" style="width: 100%;">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="photo" id="photoInput" class="form-control" accept="image/*" onchange="previewPhoto(this)">
                            <small class="text-muted">JPG, PNG, GIF (Max 2MB)</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="text-muted">*</span></label>
                        <input type="text" name="fullname" class="form-control" placeholder="Enter full name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username <span class="text-muted">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="Choose a username" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password <span class="text-muted">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span class="text-muted">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="teacher@school.edu" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="Contact number">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-control">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button class="btn btn-primary" onclick="document.getElementById('addTeacherForm').submit()">
                    <i class="fas fa-save"></i> Save Teacher
                </button>
            </div>
        </div>
    </div>
    
    <!-- Edit Teacher Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Teacher</h3>
                <button class="close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editTeacherForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="teacher_id" id="edit_teacher_id">
                    <input type="hidden" name="existing_photo" id="edit_existing_photo">
                    
                    <div class="photo-upload">
                        <div class="photo-preview" id="editPhotoPreview">
                            <i class="fas fa-user" style="font-size: 48px; color: var(--grey-dark);"></i>
                        </div>
                        <div class="form-group" style="width: 100%;">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="photo" id="editPhotoInput" class="form-control" accept="image/*" onchange="previewEditPhoto(this)">
                            <small class="text-muted">Leave empty to keep current photo</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="text-muted">*</span></label>
                        <input type="text" name="fullname" id="edit_fullname" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span class="text-muted">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" id="edit_gender" class="form-control">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Teacher Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select name="user_status" id="edit_user_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button class="btn btn-primary" onclick="document.getElementById('editTeacherForm').submit()">
                    <i class="fas fa-save"></i> Update Teacher
                </button>
            </div>
        </div>
    </div>
    
    <script>
        let deleteTeacherId = null;
        
        // Sidebar Toggle
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        if (overlay) overlay.addEventListener('click', closeSidebar);
        
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                if (sidebar) sidebar.classList.remove('open');
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        
        // Filter by status
        function filterByStatus() {
            let status = document.getElementById('statusFilter').value;
            let search = document.getElementById('searchInput').value;
            window.location.search = `?search=${encodeURIComponent(search)}&status=${status}`;
        }
        
        // Live search
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            let statusValue = document.getElementById('statusFilter').value;
            let rows = document.querySelectorAll('#teachersTableBody tr');
            let hasResults = false;
            
            rows.forEach(row => {
                if(row.id === 'noResultsRow') return;
                
                let name = row.getAttribute('data-name') || '';
                let status = row.getAttribute('data-status') || '';
                
                let matchesSearch = name.includes(searchValue);
                let matchesStatus = (statusValue === 'all' || status === statusValue);
                
                if(matchesSearch && matchesStatus) {
                    row.style.display = '';
                    hasResults = true;
                } else {
                    row.style.display = 'none';
                }
            });
            
            let noResultsRow = document.getElementById('noResultsRow');
            if(!hasResults && rows.length > 0) {
                if(!noResultsRow) {
                    const tbody = document.getElementById('teachersTableBody');
                    const tr = document.createElement('tr');
                    tr.id = 'noResultsRow';
                    tr.innerHTML = '<td colspan="9" class="text-center" style="padding: 40px;"><i class="fas fa-search" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>No teachers found</div>';
                    tbody.appendChild(tr);
                } else {
                    noResultsRow.style.display = '';
                }
            } else if(noResultsRow) {
                noResultsRow.style.display = 'none';
            }
        });
        
        function showAddModal() {
            document.getElementById('addModal').classList.add('show');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }
        
        function approveTeacher(id) {
            if(confirm('Approve this teacher? They will be able to login.')) {
                window.location.search = `?approve=${id}`;
            }
        }
        
        function rejectTeacher(id) {
            if(confirm('Reject this teacher? They will not be able to login.')) {
                window.location.search = `?reject=${id}`;
            }
        }
        
        function showDeleteConfirm(id, name) {
            deleteTeacherId = id;
            document.getElementById('deleteMessage').innerHTML = `Are you sure you want to delete <strong>${escapeHtml(name)}</strong>? This action cannot be undone.`;
            document.getElementById('deleteConfirmModal').classList.add('show');
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function closeDeleteConfirm() {
            deleteTeacherId = null;
            document.getElementById('deleteConfirmModal').classList.remove('show');
        }
        
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if(deleteTeacherId) {
                window.location.search = `?delete=${deleteTeacherId}`;
            }
        });
        
        function previewPhoto(input) {
            const preview = document.getElementById('photoPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">`;
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.innerHTML = `<i class="fas fa-user" style="font-size: 48px; color: var(--grey-dark);"></i>`;
            }
        }
        
        function previewEditPhoto(input) {
            const preview = document.getElementById('editPhotoPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function editTeacherModal(id) {
            fetch(`api/get_teacher.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        document.getElementById('edit_teacher_id').value = id;
                        document.getElementById('edit_existing_photo').value = data.photo || 'default_avatar.png';
                        document.getElementById('edit_fullname').value = data.fullname;
                        document.getElementById('edit_email').value = data.email || '';
                        document.getElementById('edit_phone').value = data.phone || '';
                        document.getElementById('edit_gender').value = data.gender || 'Male';
                        document.getElementById('edit_status').value = data.status || 'active';
                        document.getElementById('edit_user_status').value = data.user_status || 'active';
                        
                        const preview = document.getElementById('editPhotoPreview');
                        if(data.photo && data.photo !== 'default_avatar.png') {
                            preview.innerHTML = `<img src="uploads/teachers/${data.photo}" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">`;
                        } else {
                            preview.innerHTML = `<i class="fas fa-user" style="font-size: 48px; color: var(--grey-dark);"></i>`;
                        }
                        
                        document.getElementById('editModal').classList.add('show');
                    } else {
                        alert('Failed to load teacher details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load teacher details');
                });
        }
        
        window.onclick = function(event) {
            document.querySelectorAll('.modal').forEach(modal => {
                if (event.target === modal) modal.classList.remove('show');
            });
            if (event.target === document.getElementById('deleteConfirmModal')) closeDeleteConfirm();
        }
    </script>
</body>
</html>