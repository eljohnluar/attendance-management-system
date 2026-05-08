<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('admin');

// Handle add section
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $section_name = sanitize($_POST['section_name']);
    $year_level_id = (int)$_POST['year_level_id'];
    $teacher_id = (int)$_POST['teacher_id'];
    
    $sql = "INSERT INTO sections (section_name, year_level_id, teacher_id) VALUES (?, ?, ?)";
    if(safeQuery($sql, [$section_name, $year_level_id, $teacher_id], 'sii')) {
        header('Location: sections_levels.php?added=1');
        exit();
    } else {
        header('Location: sections_levels.php?error=add_failed');
        exit();
    }
}

// Handle delete section
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // First, update students to remove section assignment
    safeQuery("UPDATE students SET section_id = NULL WHERE section_id = ?", [$id], 'i');
    
    // Then delete the section
    $result = safeQuery("DELETE FROM sections WHERE id = ?", [$id], 'i');
    
    if($result) {
        header('Location: sections_levels.php?deleted=1');
        exit();
    } else {
        header('Location: sections_levels.php?error=delete_failed');
        exit();
    }
}

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$year_filter = isset($_GET['year_level']) ? (int)$_GET['year_level'] : 0;

$sql = "SELECT s.*, yl.level_name, u.fullname as teacher_name, 
        (SELECT COUNT(*) FROM students WHERE section_id = s.id AND status = 'active') as student_count
        FROM sections s
        LEFT JOIN year_levels yl ON s.year_level_id = yl.id
        LEFT JOIN teachers t ON s.teacher_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE s.section_name LIKE ?";
$params = ["%$search%"];
$types = "s";

if($year_filter > 0) {
    $sql .= " AND s.year_level_id = ?";
    $params[] = $year_filter;
    $types .= "i";
}

$sql .= " ORDER BY yl.sort_order, s.section_name";
$sections = safeQuery($sql, $params, $types);

$year_levels = safeQuery("SELECT * FROM year_levels ORDER BY sort_order");
$teachers = safeQuery("SELECT t.id, u.fullname FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.role = 'teacher' AND u.status = 'active' ORDER BY u.fullname");

// Check for success messages
$show_added = isset($_GET['added']);
$show_deleted = isset($_GET['deleted']);
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Sections & Levels - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <style>
        .btn-primary .fa-plus {
            color: white;
        }
        .sections-container {
            padding: 24px;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
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

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-info {
            background: #e3f2fd;
            color: #2196f3;
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

        /* Fix navbar overlapping content */
        .main-content {
            margin-top: 60px;
            padding: 0;
            min-height: calc(100vh - 60px);
        }

        /* Desktop styles */
        @media (min-width: 1024px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        /* Mobile styles */
        @media (max-width: 1023px) {
            .main-content {
                margin-left: 0;
            }
            
            .sections-container {
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
        <div class="sections-container">
            <!-- Toast Notifications -->
            <?php if($show_added): ?>
                <div class="toast-notification toast-success" id="successToast">
                    <i class="fas fa-check-circle"></i> Section added successfully!
                </div>
                <script>
                    setTimeout(() => {
                        const toast = document.getElementById('successToast');
                        if(toast) {
                            toast.style.animation = 'slideOut 0.3s ease';
                            setTimeout(() => toast.remove(), 300);
                        }
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <?php if($show_deleted): ?>
                <div class="toast-notification toast-success" id="deleteToast">
                    <i class="fas fa-check-circle"></i> Section deleted successfully!
                </div>
                <script>
                    setTimeout(() => {
                        const toast = document.getElementById('deleteToast');
                        if(toast) {
                            toast.style.animation = 'slideOut 0.3s ease';
                            setTimeout(() => toast.remove(), 300);
                        }
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <?php if($error_msg == 'add_failed'): ?>
                <div class="toast-notification toast-error" id="errorToast">
                    <i class="fas fa-exclamation-circle"></i> Failed to add section. Please try again.
                </div>
                <script>
                    setTimeout(() => {
                        const toast = document.getElementById('errorToast');
                        if(toast) {
                            toast.style.animation = 'slideOut 0.3s ease';
                            setTimeout(() => toast.remove(), 300);
                        }
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <?php if($error_msg == 'delete_failed'): ?>
                <div class="toast-notification toast-error" id="errorToast">
                    <i class="fas fa-exclamation-circle"></i> Failed to delete section. Please try again.
                </div>
                <script>
                    setTimeout(() => {
                        const toast = document.getElementById('errorToast');
                        if(toast) {
                            toast.style.animation = 'slideOut 0.3s ease';
                            setTimeout(() => toast.remove(), 300);
                        }
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-layer-group"></i>
                    <span>Sections Management</span>
                    <button onclick="showAddModal()" class="btn btn-primary" style="margin-left: auto;">
                        <i class="fas fa-plus"></i>
                        <span>Add Section</span>
                    </button>
                </div>
                <div class="card-body">
                    <!-- Search Bar - Live search, no page reload -->
                    <div class="search-bar">
                        <div class="filter-group">
                            <div class="form-group" style="margin-bottom: 0;">
                                <input type="text" id="searchInput" class="form-control" placeholder="Search sections..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="filter-group">
                            <div class="form-group" style="margin-bottom: 0;">
                                <select id="yearFilter" class="form-control">
                                    <option value="0">All Levels</option>
                                    <?php while($yl = mysqli_fetch_assoc($year_levels)): ?>
                                        <option value="<?php echo $yl['id']; ?>" <?php echo $year_filter == $yl['id'] ? 'selected' : ''; ?>>
                                            <?php echo $yl['level_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sections Table -->
                    <div class="table-wrapper">
                        <table id="sectionsTable">
                            <thead>
                                <tr>
                                    <th>Section Name</th>
                                    <th>Year Level</th>
                                    <th>Assigned Teacher</th>
                                    <th>Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sectionsTableBody">
                                <?php if($sections && mysqli_num_rows($sections) > 0): ?>
                                    <?php while($section = mysqli_fetch_assoc($sections)): ?>
                                    <tr data-name="<?php echo strtolower($section['section_name']); ?>" data-level="<?php echo $section['year_level_id']; ?>">
                                        <td><?php echo htmlspecialchars($section['section_name']); ?></td>
                                        <td><?php echo htmlspecialchars($section['level_name']); ?></td>
                                        <td><?php echo htmlspecialchars($section['teacher_name']) ?: 'Not Assigned'; ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <i class="fas fa-users"></i> <?php echo $section['student_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-icon danger" onclick="showDeleteConfirm(<?php echo $section['id']; ?>, '<?php echo addslashes($section['section_name']); ?>')" title="Delete Section">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr id="noResultsRow">
                                        <td colspan="5" class="text-center" style="padding: 40px; color: #999;">
                                            <i class="fas fa-layer-group" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                                            No sections found
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
            <h3>Delete Section</h3>
            <p id="deleteMessage">Are you sure you want to delete this section? This will affect student records and cannot be undone.</p>
            <div class="confirm-buttons">
                <button class="btn btn-secondary" onclick="closeDeleteConfirm()">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn" style="background: #f44336;">Delete</button>
            </div>
        </div>
    </div>
    
    <!-- Add Section Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Section</h3>
                <button class="close" onclick="closeModal('addModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addSectionForm" method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label class="form-label">Section Name <span class="text-muted">*</span></label>
                        <input type="text" name="section_name" class="form-control" placeholder="e.g., 7 - Wisdom, 8 - Excellence" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Year Level <span class="text-muted">*</span></label>
                        <select name="year_level_id" class="form-control" required>
                            <option value="">Select Year Level</option>
                            <?php 
                            $yls = safeQuery("SELECT * FROM year_levels ORDER BY sort_order");
                            while($yl = mysqli_fetch_assoc($yls)): ?>
                                <option value="<?php echo $yl['id']; ?>"><?php echo $yl['level_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Assigned Teacher</label>
                        <select name="teacher_id" class="form-control">
                            <option value="">Select Teacher (Optional)</option>
                            <?php 
                            $teach = safeQuery("SELECT t.id, u.fullname FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.role = 'teacher' AND u.status = 'active' ORDER BY u.fullname");
                            while($t = mysqli_fetch_assoc($teach)): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['fullname']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button class="btn btn-primary" onclick="document.getElementById('addSectionForm').submit()">
                    <i class="fas fa-save"></i> Save Section
                </button>
            </div>
        </div>
    </div>
    
    <script>
        let deleteSectionId = null;
        
        // Sidebar Toggle with Overlay
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        function closeSidebar() {
            if (sidebar) {
                sidebar.classList.remove('open');
            }
            if (overlay) {
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
        
        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                if (sidebar) sidebar.classList.remove('open');
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        
        // Live search - no page reload
        function filterTable() {
            let searchValue = document.getElementById('searchInput').value.toLowerCase();
            let yearValue = document.getElementById('yearFilter').value;
            let rows = document.querySelectorAll('#sectionsTableBody tr');
            let hasResults = false;
            
            rows.forEach(row => {
                // Skip the no results row
                if(row.id === 'noResultsRow') return;
                
                let name = row.getAttribute('data-name') || '';
                let level = row.getAttribute('data-level') || '';
                
                let matchesSearch = name.includes(searchValue);
                let matchesYear = (yearValue === '0' || level === yearValue);
                
                if(matchesSearch && matchesYear) {
                    row.style.display = '';
                    hasResults = true;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show no results message if needed
            let noResultsRow = document.getElementById('noResultsRow');
            if(!hasResults && rows.length > 0) {
                if(!noResultsRow) {
                    const tbody = document.getElementById('sectionsTableBody');
                    const tr = document.createElement('tr');
                    tr.id = 'noResultsRow';
                    tr.innerHTML = '<td colspan="5" class="text-center" style="padding: 40px; color: #999;"><i class="fas fa-search" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>No sections found</td>';
                    tbody.appendChild(tr);
                } else {
                    noResultsRow.style.display = '';
                }
            } else if(noResultsRow) {
                noResultsRow.style.display = 'none';
            }
        }
        
        // Add event listeners for live search
        document.getElementById('searchInput').addEventListener('keyup', filterTable);
        document.getElementById('yearFilter').addEventListener('change', filterTable);
        
        function showAddModal() {
            document.getElementById('addModal').classList.add('show');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }
        
        function showDeleteConfirm(id, name) {
            deleteSectionId = id;
            document.getElementById('deleteMessage').innerHTML = `Are you sure you want to delete <strong>${escapeHtml(name)}</strong>? This will affect student records and cannot be undone.`;
            document.getElementById('deleteConfirmModal').classList.add('show');
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function closeDeleteConfirm() {
            deleteSectionId = null;
            document.getElementById('deleteConfirmModal').classList.remove('show');
        }
        
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if(deleteSectionId) {
                window.location.href = `?delete=${deleteSectionId}`;
            }
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
            
            const confirmModal = document.getElementById('deleteConfirmModal');
            if (event.target === confirmModal) {
                closeDeleteConfirm();
            }
        }
    </script>
</body>
</html>