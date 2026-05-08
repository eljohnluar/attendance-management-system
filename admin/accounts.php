<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('admin');

// Handle approval actions
if(isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    safeQuery("UPDATE users SET status = 'active' WHERE id = ?", [$id], 'i');
    header('Location: accounts.php?approved=1');
    exit();
}

if(isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    safeQuery("UPDATE users SET status = 'rejected' WHERE id = ?", [$id], 'i');
    header('Location: accounts.php?rejected=1');
    exit();
}

if(isset($_GET['activate'])) {
    $id = (int)$_GET['activate'];
    safeQuery("UPDATE users SET status = 'active' WHERE id = ?", [$id], 'i');
    header('Location: accounts.php?activated=1');
    exit();
}

if(isset($_GET['deactivate'])) {
    $id = (int)$_GET['deactivate'];
    safeQuery("UPDATE users SET status = 'inactive' WHERE id = ?", [$id], 'i');
    header('Location: accounts.php?deactivated=1');
    exit();
}

// Handle account delete
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    if($id === (int)$_SESSION['user_id']) {
        header('Location: accounts.php?error=self_delete_not_allowed');
        exit();
    }

    $user_result = safeQuery("SELECT id, role FROM users WHERE id = ?", [$id], 'i');
    if($user = mysqli_fetch_assoc($user_result)) {
        if($user['role'] === 'teacher') {
            $teacher_result = safeQuery("SELECT id, photo FROM teachers WHERE user_id = ?", [$id], 'i');
            if($teacher = mysqli_fetch_assoc($teacher_result)) {
                safeQuery("UPDATE sections SET teacher_id = NULL WHERE teacher_id = ?", [$teacher['id']], 'i');
                if(!empty($teacher['photo']) && $teacher['photo'] !== 'default_avatar.png' && file_exists('../uploads/teachers/' . $teacher['photo'])) {
                    unlink('../uploads/teachers/' . $teacher['photo']);
                }
                safeQuery("DELETE FROM teachers WHERE user_id = ?", [$id], 'i');
            }
        } elseif($user['role'] === 'student') {
            $student_result = safeQuery("SELECT photo FROM students WHERE user_id = ?", [$id], 'i');
            if($student = mysqli_fetch_assoc($student_result)) {
                if(!empty($student['photo']) && $student['photo'] !== 'default_student.png' && file_exists('../uploads/students/' . $student['photo'])) {
                    unlink('../uploads/students/' . $student['photo']);
                }
                safeQuery("DELETE FROM students WHERE user_id = ?", [$id], 'i');
            }
        }

        safeQuery("DELETE FROM users WHERE id = ?", [$id], 'i');
        header('Location: accounts.php?deleted=1');
        exit();
    }

    header('Location: accounts.php?error=account_not_found');
    exit();
}

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize($_GET['role']) : 'all';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';

$sql = "SELECT id, username, role, email, phone, fullname, status, email_verified, created_at
        FROM users
        WHERE (fullname LIKE ? OR username LIKE ? OR email LIKE ?)";
$params = ["%$search%", "%$search%", "%$search%"];
$types = "sss";

if($role_filter !== 'all') {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if($status_filter !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY created_at DESC";
$accounts = safeQuery($sql, $params, $types);

$total_accounts = mysqli_fetch_assoc(safeQuery("SELECT COUNT(*) AS count FROM users"))['count'];
$pending_accounts = mysqli_fetch_assoc(safeQuery("SELECT COUNT(*) AS count FROM users WHERE status = 'pending'"))['count'];
$active_accounts = mysqli_fetch_assoc(safeQuery("SELECT COUNT(*) AS count FROM users WHERE status = 'active'"))['count'];

$show_approved = isset($_GET['approved']);
$show_rejected = isset($_GET['rejected']);
$show_activated = isset($_GET['activated']);
$show_deactivated = isset($_GET['deactivated']);
$show_deleted = isset($_GET['deleted']);
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Accounts - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <style>
        .accounts-container { padding: 24px; }
        .stats-row { margin-bottom: 24px; }
        .stat-card {
            background: var(--white);
            border: 1px solid var(--grey);
            border-radius: 16px;
            padding: 18px;
        }
        .stat-title { font-size: 12px; color: #888; margin-bottom: 8px; }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--pink); }

        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn-icon {
            padding: 6px 10px;
            background: transparent;
            border: 1px solid var(--grey);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            color: #666;
        }
        .btn-icon:hover { background: var(--pink-light); border-color: var(--pink); color: var(--pink); }
        .btn-icon.danger:hover { background: #ffebee; border-color: #f44336; color: #f44336; }
        .btn-icon.success:hover { background: #e8f5e9; border-color: #4caf50; color: #4caf50; }
        .btn-icon.warning:hover { background: #fff3e0; border-color: #ff9800; color: #ff9800; }

        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-active { background: #e8f5e9; color: #4caf50; }
        .badge-pending { background: #fff3e0; color: #ff9800; }
        .badge-inactive { background: #eceff1; color: #607d8b; }
        .badge-rejected { background: #ffebee; color: #f44336; }
        .badge-verified { background: #e3f2fd; color: #1565c0; }
        .badge-unverified { background: #f5f5f5; color: #757575; }

        .role-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .role-admin { background: #f3e5f5; color: #8e24aa; }
        .role-teacher { background: #e8f5e9; color: #2e7d32; }
        .role-student { background: #e3f2fd; color: #1565c0; }

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
        .toast-success { background: #4caf50; }
        .toast-error { background: #f44336; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }

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
        .confirm-modal.show { display: flex; }
        .confirm-content {
            background: white;
            border-radius: 16px;
            padding: 24px;
            max-width: 420px;
            width: 90%;
            text-align: center;
        }
        .confirm-content i { font-size: 48px; color: #f44336; margin-bottom: 16px; }
        .confirm-content p { color: #666; margin-bottom: 20px; }
        .confirm-buttons { display: flex; gap: 12px; justify-content: center; }

        @media (max-width: 1023px) { .accounts-container { padding: 16px; } }
    </style>
</head>
<body>
    <?php include '../includes/admin_navbar.php'; ?>
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-content">
        <div class="accounts-container">
            <?php if($show_approved): ?>
                <div class="toast-notification toast-success" id="approveToast"><i class="fas fa-check-circle"></i> Account approved successfully!</div>
                <script>setTimeout(() => { const t = document.getElementById('approveToast'); if(t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 2800);</script>
            <?php endif; ?>
            <?php if($show_rejected): ?>
                <div class="toast-notification toast-success" id="rejectToast"><i class="fas fa-check-circle"></i> Account rejected successfully!</div>
                <script>setTimeout(() => { const t = document.getElementById('rejectToast'); if(t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 2800);</script>
            <?php endif; ?>
            <?php if($show_activated): ?>
                <div class="toast-notification toast-success" id="activateToast"><i class="fas fa-check-circle"></i> Account activated successfully!</div>
                <script>setTimeout(() => { const t = document.getElementById('activateToast'); if(t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 2800);</script>
            <?php endif; ?>
            <?php if($show_deactivated): ?>
                <div class="toast-notification toast-success" id="deactivateToast"><i class="fas fa-check-circle"></i> Account deactivated successfully!</div>
                <script>setTimeout(() => { const t = document.getElementById('deactivateToast'); if(t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 2800);</script>
            <?php endif; ?>
            <?php if($show_deleted): ?>
                <div class="toast-notification toast-success" id="deleteToast"><i class="fas fa-check-circle"></i> Account deleted successfully!</div>
                <script>setTimeout(() => { const t = document.getElementById('deleteToast'); if(t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 2800);</script>
            <?php endif; ?>
            <?php if($error_msg): ?>
                <div class="toast-notification toast-error" id="errorToast"><i class="fas fa-exclamation-circle"></i> <?php echo ucfirst(str_replace('_', ' ', $error_msg)); ?></div>
                <script>setTimeout(() => { const t = document.getElementById('errorToast'); if(t) { t.style.animation = 'slideOut 0.3s ease'; setTimeout(() => t.remove(), 300); } }, 3200);</script>
            <?php endif; ?>

            <div class="grid-stats stats-row">
                <div class="stat-card"><div class="stat-title">Total Accounts</div><div class="stat-value"><?php echo (int)$total_accounts; ?></div></div>
                <div class="stat-card"><div class="stat-title">Pending Approval</div><div class="stat-value"><?php echo (int)$pending_accounts; ?></div></div>
                <div class="stat-card"><div class="stat-title">Active Accounts</div><div class="stat-value"><?php echo (int)$active_accounts; ?></div></div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-check"></i>
                    <span>Accounts Approval & Management</span>
                </div>
                <div class="card-body">
                    <div class="search-bar">
                        <div class="filter-group">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search by name, username, or email..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <select id="roleFilter" class="form-control">
                                <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="teacher" <?php echo $role_filter === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <select id="statusFilter" class="form-control">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Email Status</th>
                                    <th>Account Status</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="accountsTableBody">
                                <?php if($accounts && mysqli_num_rows($accounts) > 0): ?>
                                    <?php while($account = mysqli_fetch_assoc($accounts)): ?>
                                    <tr data-name="<?php echo strtolower($account['fullname'] . ' ' . $account['username'] . ' ' . $account['email']); ?>" data-role="<?php echo $account['role']; ?>" data-status="<?php echo $account['status']; ?>">
                                        <td><?php echo htmlspecialchars($account['fullname']); ?></td>
                                        <td><?php echo htmlspecialchars($account['username']); ?></td>
                                        <td><span class="role-pill role-<?php echo $account['role']; ?>"><?php echo htmlspecialchars($account['role']); ?></span></td>
                                        <td><?php echo htmlspecialchars($account['email']); ?></td>
                                        <td><?php echo htmlspecialchars($account['phone'] ?: '-'); ?></td>
                                        <td>
                                            <span class="badge <?php echo (int)$account['email_verified'] === 1 ? 'badge-verified' : 'badge-unverified'; ?>">
                                                <?php echo (int)$account['email_verified'] === 1 ? 'Verified' : 'Not Verified'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo htmlspecialchars($account['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($account['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($account['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if($account['status'] === 'pending'): ?>
                                                    <button class="btn-icon success" title="Approve" onclick="approveAccount(<?php echo (int)$account['id']; ?>)"><i class="fas fa-check-circle"></i></button>
                                                    <button class="btn-icon warning" title="Reject" onclick="rejectAccount(<?php echo (int)$account['id']; ?>)"><i class="fas fa-times-circle"></i></button>
                                                <?php endif; ?>
                                                <?php if($account['status'] === 'active'): ?>
                                                    <button class="btn-icon warning" title="Deactivate" onclick="deactivateAccount(<?php echo (int)$account['id']; ?>)"><i class="fas fa-user-slash"></i></button>
                                                <?php elseif($account['status'] !== 'pending'): ?>
                                                    <button class="btn-icon success" title="Activate" onclick="activateAccount(<?php echo (int)$account['id']; ?>)"><i class="fas fa-user-check"></i></button>
                                                <?php endif; ?>
                                                <button class="btn-icon danger" title="Delete Account" onclick="showDeleteConfirm(<?php echo (int)$account['id']; ?>, '<?php echo addslashes($account['fullname']); ?>')"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr id="noResultsRow">
                                        <td colspan="9" class="text-center" style="padding: 40px; color: #999;">
                                            <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                                            No accounts found
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

    <div id="deleteConfirmModal" class="confirm-modal">
        <div class="confirm-content">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Delete Account</h3>
            <p id="deleteMessage">Are you sure you want to delete this account? This action cannot be undone.</p>
            <div class="confirm-buttons">
                <button class="btn btn-secondary" onclick="closeDeleteConfirm()">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn" style="background: #f44336;">Delete</button>
            </div>
        </div>
    </div>

    <script>
        let deleteAccountId = null;

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

        function applyFiltersToUrl() {
            const search = document.getElementById('searchInput').value.trim();
            const role = document.getElementById('roleFilter').value;
            const status = document.getElementById('statusFilter').value;
            window.location.search = `?search=${encodeURIComponent(search)}&role=${encodeURIComponent(role)}&status=${encodeURIComponent(status)}`;
        }

        function filterTableClient() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const role = document.getElementById('roleFilter').value;
            const status = document.getElementById('statusFilter').value;

            const rows = document.querySelectorAll('#accountsTableBody tr');
            let hasResults = false;

            rows.forEach(row => {
                if(row.id === 'noResultsRow') return;
                const name = row.getAttribute('data-name') || '';
                const rowRole = row.getAttribute('data-role') || '';
                const rowStatus = row.getAttribute('data-status') || '';

                const matchesSearch = name.includes(search);
                const matchesRole = role === 'all' || rowRole === role;
                const matchesStatus = status === 'all' || rowStatus === status;

                if(matchesSearch && matchesRole && matchesStatus) {
                    row.style.display = '';
                    hasResults = true;
                } else {
                    row.style.display = 'none';
                }
            });

            let noResultsRow = document.getElementById('noResultsRow');
            if(!hasResults && rows.length > 0) {
                if(!noResultsRow) {
                    const tbody = document.getElementById('accountsTableBody');
                    const tr = document.createElement('tr');
                    tr.id = 'noResultsRow';
                    tr.innerHTML = '<td colspan="9" class="text-center" style="padding: 40px;"><i class="fas fa-search" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>No accounts found</td>';
                    tbody.appendChild(tr);
                } else {
                    noResultsRow.style.display = '';
                }
            } else if(noResultsRow) {
                noResultsRow.style.display = 'none';
            }
        }

        document.getElementById('searchInput').addEventListener('keyup', filterTableClient);
        document.getElementById('roleFilter').addEventListener('change', applyFiltersToUrl);
        document.getElementById('statusFilter').addEventListener('change', applyFiltersToUrl);

        function approveAccount(id) {
            if(confirm('Approve this account? The user will be allowed to login.')) {
                window.location.search = `?approve=${id}`;
            }
        }

        function rejectAccount(id) {
            if(confirm('Reject this account? The user will not be able to login.')) {
                window.location.search = `?reject=${id}`;
            }
        }

        function activateAccount(id) {
            if(confirm('Activate this account?')) {
                window.location.search = `?activate=${id}`;
            }
        }

        function deactivateAccount(id) {
            if(confirm('Deactivate this account?')) {
                window.location.search = `?deactivate=${id}`;
            }
        }

        function showDeleteConfirm(id, fullname) {
            deleteAccountId = id;
            document.getElementById('deleteMessage').innerHTML = `Are you sure you want to delete <strong>${escapeHtml(fullname)}</strong>? This action cannot be undone.`;
            document.getElementById('deleteConfirmModal').classList.add('show');
        }

        function closeDeleteConfirm() {
            deleteAccountId = null;
            document.getElementById('deleteConfirmModal').classList.remove('show');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if(deleteAccountId) {
                window.location.search = `?delete=${deleteAccountId}`;
            }
        });

        window.onclick = function(event) {
            if (event.target === document.getElementById('deleteConfirmModal')) {
                closeDeleteConfirm();
            }
        };
    </script>
</body>
</html>
