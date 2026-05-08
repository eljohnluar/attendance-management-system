<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('admin');

$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$section_filter = isset($_GET['section']) ? (int)$_GET['section'] : 0;
$view_type = isset($_GET['view']) ? $_GET['view'] : 'live';

// Get sections for filter
$sections = safeQuery("SELECT s.*, yl.level_name FROM sections s 
                       JOIN year_levels yl ON s.year_level_id = yl.id 
                       WHERE s.status = 'active' ORDER BY yl.sort_order");

// Live attendance for selected date
$live_sql = "SELECT al.*, s.fullname, s.photo, sec.section_name 
             FROM attendance_log al
             JOIN students s ON al.student_id = s.id
             LEFT JOIN sections sec ON s.section_id = sec.id
             WHERE al.log_date = ?";
$params = [$date_filter];
$types = "s";

if($section_filter > 0) {
    $live_sql .= " AND s.section_id = ?";
    $params[] = $section_filter;
    $types .= "i";
}
$live_sql .= " ORDER BY al.time_captured DESC";
$attendance = safeQuery($live_sql, $params, $types);

// Check for success messages
$show_saved = isset($_GET['saved']);
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Attendance Log - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .btn-primary .fa-clock {
            color: white;
        }
        .attendance-container {
            padding: 24px;
        }

        /* Fix table layout */
        .table-wrapper {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 700px;
        }

        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid var(--grey);
            vertical-align: middle;
        }

        th {
            font-weight: 600;
            color: #666;
            background: var(--white-smoke);
            font-size: 12px;
        }

        .student-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            object-fit: cover;
        }

        .student-avatar img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        .student-avatar.default {
            background: var(--pink-light);
            color: var(--pink);
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
            white-space: nowrap;
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

        /* Confirmation Modal */
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
            color: #ff9800;
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

        /* Minimalist RFID Scanner Card */
        .rfid-scanner-card {
            background: var(--white);
            border-radius: 16px;
            border: 1px solid var(--grey);
            margin-bottom: 24px;
        }

        .rfid-scanner-card .card-header {
            background: var(--white);
            border-bottom: 1px solid var(--grey);
            padding: 14px 20px;
            font-weight: 500;
            font-size: 14px;
            color: #666;
        }

        .rfid-scanner-card .card-header i {
            color: var(--pink);
            margin-right: 6px;
        }

        .rfid-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .rfid-input-group .form-control {
            flex: 1;
            font-family: monospace;
            font-size: 14px;
            padding: 10px 14px;
        }

        .scanner-status {
            margin-top: 10px;
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 6px;
        }

        .scanner-status.success {
            background: #e8f5e9;
            color: #4caf50;
        }

        .scanner-status.error {
            background: #ffebee;
            color: #f44336;
        }

        /* Filter Bar */
        .search-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        /* Fix navbar overlapping content */
        .main-content {
            margin-top: 60px;
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
            
            .attendance-container {
                padding: 16px;
            }
            
            .rfid-input-group {
                flex-direction: column;
            }
            
            .rfid-input-group .btn {
                width: 100%;
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
        <div class="attendance-container">
            <!-- Toast Notifications -->
            <?php if($show_saved): ?>
                <div class="toast-notification toast-success" id="successToast">
                    <i class="fas fa-check-circle"></i> Attendance entry saved!
                </div>
                <script>
                    setTimeout(() => {
                        const toast = document.getElementById('successToast');
                        if(toast) toast.remove();
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <?php if($error_msg == 'save_failed'): ?>
                <div class="toast-notification toast-error" id="errorToast">
                    <i class="fas fa-exclamation-circle"></i> Failed to save entry
                </div>
                <script>
                    setTimeout(() => {
                        const toast = document.getElementById('errorToast');
                        if(toast) toast.remove();
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <!-- Minimalist RFID Scanner Card -->
            <div class="rfid-scanner-card">
                <div class="card-header">
                    <i class="fas fa-rss"></i> RFID Scanner
                </div>
                <div class="card-body">
                    <div class="rfid-input-group">
                        <input type="text" id="rfid_scan" class="form-control" placeholder="Scan or enter RFID" autofocus>
                        <button class="btn btn-primary" onclick="processRFID()" style="white-space: nowrap;">
                            <i class="fas fa-check"></i> Submit
                        </button>
                    </div>
                    <div id="scannerStatus" class="scanner-status" style="display: none;"></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance Log</span>
                    <div style="margin-left: auto; display: flex; gap: 8px;">
                        <button class="btn <?php echo $view_type == 'live' ? 'btn-primary' : 'btn-outline'; ?>" onclick="changeView('live')">
                            <i class="fas fa-clock"></i> Live
                        </button>
                        <button class="btn <?php echo $view_type == 'summary' ? 'btn-primary' : 'btn-outline'; ?>" onclick="changeView('summary')">
                            <i class="fas fa-chart-bar"></i> Summary
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filter Bar -->
                    <div class="search-bar">
                        <div class="filter-group">
                            <label class="form-label">Date</label>
                            <input type="date" id="dateFilter" class="form-control" value="<?php echo $date_filter; ?>" onchange="filterData()">
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Section</label>
                            <select id="sectionFilter" class="form-control" onchange="filterData()">
                                <option value="0">All Sections</option>
                                <?php while($sec = mysqli_fetch_assoc($sections)): ?>
                                    <option value="<?php echo $sec['id']; ?>" <?php echo $section_filter == $sec['id'] ? 'selected' : ''; ?>>
                                        <?php echo $sec['level_name'] . ' - ' . $sec['section_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="form-label">&nbsp;</label>
                            <button class="btn btn-primary btn-block" onclick="manualEntry()" style="width: 100%;">
                                <i class="fas fa-pen"></i> Manual Entry
                            </button>
                        </div>
                        <div class="filter-group">
                            <label class="form-label">&nbsp;</label>
                            <button class="btn btn-secondary btn-block" onclick="showResetConfirm()" style="width: 100%;">
                                <i class="fas fa-undo-alt"></i> Reset
                            </button>
                        </div>
                    </div>
                    
                    <?php if($view_type == 'live'): ?>
                        <!-- Live View -->
                        <div class="table-wrapper">
                            <h4 style="margin-bottom: 16px; font-size: 14px;">
                                <i class="fas fa-clock"></i> Live Attendance 
                                <small><?php echo date('F d, Y', strtotime($date_filter)); ?></small>
                            </h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Student</th>
                                        <th>Section</th>
                                        <th>Type</th>
                                        <th>Time</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($attendance) > 0): ?>
                                        <?php while($log = mysqli_fetch_assoc($attendance)): ?>
                                        <tr>
                                            <td>
                                                <?php if($log['photo'] && $log['photo'] != 'default_student.png' && file_exists('../uploads/students/' . $log['photo'])): ?>
                                                    <div class="student-avatar">
                                                        <img src="../uploads/students/<?php echo $log['photo']; ?>" alt="<?php echo htmlspecialchars($log['fullname']); ?>">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="student-avatar default">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['fullname']); ?></td>
                                            <td><?php echo htmlspecialchars($log['section_name']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo strpos($log['log_type'], 'in') !== false ? 'info' : 'warning'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $log['log_type'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('h:i A', strtotime($log['time_captured'])); ?></td>
                                            <td><span class="badge badge-info"><?php echo strtoupper($log['method']); ?></span></td>
                                            <td>
                                                <span class="badge badge-<?php echo $log['status'] == 'late' ? 'danger' : 'success'; ?>">
                                                    <?php echo ucfirst($log['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center" style="padding: 40px; color: #999;">
                                                <i class="fas fa-calendar-day" style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                                                No attendance records
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- Summary View -->
                        <div class="table-wrapper">
                            <h4 style="margin-bottom: 16px; font-size: 14px;">
                                <i class="fas fa-chart-bar"></i> Summary Attendance 
                                <small><?php echo date('F d, Y', strtotime($date_filter)); ?></small>
                            </h4>
                            <?php
                            $summary_sql = "SELECT 
                                s.id, s.fullname, s.photo, sec.section_name,
                                MAX(CASE WHEN al.log_type = 'morning_in' THEN TIME(al.time_captured) END) as morning_in,
                                MAX(CASE WHEN al.log_type = 'morning_out' THEN TIME(al.time_captured) END) as morning_out,
                                MAX(CASE WHEN al.log_type = 'afternoon_in' THEN TIME(al.time_captured) END) as afternoon_in,
                                MAX(CASE WHEN al.log_type = 'afternoon_out' THEN TIME(al.time_captured) END) as afternoon_out,
                                CASE 
                                    WHEN MAX(CASE WHEN al.log_type IN ('morning_in', 'afternoon_in') THEN 1 ELSE 0 END) = 1 THEN 'Present'
                                    ELSE 'Absent'
                                END as presence
                            FROM students s
                            LEFT JOIN sections sec ON s.section_id = sec.id
                            LEFT JOIN attendance_log al ON s.id = al.student_id AND al.log_date = ?
                            WHERE s.status = 'active'";
                            
                            $summary_params = [$date_filter];
                            $summary_types = "s";
                            
                            if($section_filter > 0) {
                                $summary_sql .= " AND s.section_id = ?";
                                $summary_params[] = $section_filter;
                                $summary_types .= "i";
                            }
                            
                            $summary_sql .= " GROUP BY s.id ORDER BY s.fullname";
                            $summary = safeQuery($summary_sql, $summary_params, $summary_types);
                            ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Student</th>
                                        <th>Section</th>
                                        <th>Morning In</th>
                                        <th>Morning Out</th>
                                        <th>Afternoon In</th>
                                        <th>Afternoon Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($summary) > 0): ?>
                                        <?php while($row = mysqli_fetch_assoc($summary)): ?>
                                        <tr>
                                            <td>
                                                <?php if($row['photo'] && $row['photo'] != 'default_student.png' && file_exists('../uploads/students/' . $row['photo'])): ?>
                                                    <div class="student-avatar">
                                                        <img src="../uploads/students/<?php echo $row['photo']; ?>" alt="<?php echo htmlspecialchars($row['fullname']); ?>">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="student-avatar default">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                            <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                                            <td><?php echo $row['morning_in'] ? date('h:i A', strtotime($row['morning_in'])) : '-'; ?></td>
                                            <td><?php echo $row['morning_out'] ? date('h:i A', strtotime($row['morning_out'])) : '-'; ?></td>
                                            <td><?php echo $row['afternoon_in'] ? date('h:i A', strtotime($row['afternoon_in'])) : '-'; ?></td>
                                            <td><?php echo $row['afternoon_out'] ? date('h:i A', strtotime($row['afternoon_out'])) : '-'; ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $row['presence'] == 'Present' ? 'success' : 'danger'; ?>">
                                                    <?php echo $row['presence']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center" style="padding: 40px; color: #999;">
                                                <i class="fas fa-users" style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                                                No students found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reset Confirmation Modal -->
    <div id="resetConfirmModal" class="confirm-modal">
        <div class="confirm-content">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Reset Filters</h3>
            <p>Reset date to today and show all sections?</p>
            <div class="confirm-buttons">
                <button class="btn btn-secondary" onclick="closeResetConfirm()">Cancel</button>
                <button class="btn btn-primary" id="confirmResetBtn">Reset</button>
            </div>
        </div>
    </div>
    
    <!-- Manual Entry Modal -->
    <div id="manualModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-pen"></i> Manual Entry</h3>
                <button class="close" onclick="closeModal('manualModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Student *</label>
                    <select id="student_id" class="form-control" required>
                        <option value="">Select Student</option>
                        <?php 
                        $students = safeQuery("SELECT s.*, sec.section_name FROM students s LEFT JOIN sections sec ON s.section_id = sec.id WHERE s.status = 'active' ORDER BY s.fullname");
                        while($stu = mysqli_fetch_assoc($students)): ?>
                            <option value="<?php echo $stu['id']; ?>"><?php echo htmlspecialchars($stu['fullname'] . ' - ' . $stu['section_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Log Type *</label>
                    <select id="log_type" class="form-control" required>
                        <option value="morning_in">Morning In (6:00-12:00)</option>
                        <option value="morning_out">Morning Out (12:00-13:00)</option>
                        <option value="afternoon_in">Afternoon In (13:00-17:00)</option>
                        <option value="afternoon_out">Afternoon Out (17:00-18:00)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Time *</label>
                    <input type="time" id="time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="text" class="form-control" value="<?php echo date('F d, Y', strtotime($date_filter)); ?>" disabled>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('manualModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveManualEntry()">Save</button>
            </div>
        </div>
    </div>
    
    <script>
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
        
        // RFID Scanner
        function showScannerStatus(message, type) {
            const statusDiv = document.getElementById('scannerStatus');
            statusDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            statusDiv.className = `scanner-status ${type}`;
            statusDiv.style.display = 'block';
            setTimeout(() => statusDiv.style.display = 'none', 3000);
        }
        
        function processRFID() {
            const rfid = document.getElementById('rfid_scan').value.trim();
            if(!rfid) {
                showScannerStatus('Enter RFID number', 'error');
                return;
            }
            
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            
            $.ajax({
                url: '../api/mark_attendance.php',
                method: 'POST',
                data: { identifier: rfid, method: 'rfid' },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        showScannerStatus(response.message, 'success');
                        document.getElementById('rfid_scan').value = '';
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showScannerStatus(response.message, 'error');
                    }
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    document.getElementById('rfid_scan').focus();
                },
                error: function() {
                    showScannerStatus('Network error', 'error');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            });
        }
        
        document.getElementById('rfid_scan').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') processRFID();
        });
        
        function changeView(view) {
            let date = document.getElementById('dateFilter').value;
            let section = document.getElementById('sectionFilter').value;
            window.location.href = `?date=${date}&section=${section}&view=${view}`;
        }
        
        function filterData() {
            let date = document.getElementById('dateFilter').value;
            let section = document.getElementById('sectionFilter').value;
            let view = '<?php echo $view_type; ?>';
            window.location.href = `?date=${date}&section=${section}&view=${view}`;
        }
        
        function resetFilters() {
            window.location.href = `?date=<?php echo date('Y-m-d'); ?>&section=0&view=<?php echo $view_type; ?>`;
        }
        
        function showResetConfirm() {
            document.getElementById('resetConfirmModal').classList.add('show');
        }
        
        function closeResetConfirm() {
            document.getElementById('resetConfirmModal').classList.remove('show');
        }
        
        document.getElementById('confirmResetBtn').addEventListener('click', function() {
            closeResetConfirm();
            resetFilters();
        });
        
        function manualEntry() {
            document.getElementById('manualModal').classList.add('show');
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            document.getElementById('time').value = `${hours}:${minutes}`;
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }
        
        function saveManualEntry() {
            let student_id = document.getElementById('student_id').value;
            let log_type = document.getElementById('log_type').value;
            let time = document.getElementById('time').value;
            let date = document.getElementById('dateFilter').value;
            
            if(!student_id || !log_type || !time) {
                alert('Fill all required fields');
                return;
            }
            
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            
            $.ajax({
                url: '../api/manual_attendance.php',
                method: 'POST',
                data: { student_id, log_type, time, date },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        window.location.href = `?date=${date}&section=${document.getElementById('sectionFilter').value}&view=<?php echo $view_type; ?>&saved=1`;
                    } else {
                        alert(response.message);
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                },
                error: function() {
                    alert('Network error');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            });
        }
        
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) modal.classList.remove('show');
            });
            if (event.target === document.getElementById('resetConfirmModal')) closeResetConfirm();
        }
    </script>
</body>
</html>