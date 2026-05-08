<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('teacher');

$teacher_sql = "SELECT id FROM teachers WHERE user_id = ?";
$teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];

$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$section_filter = isset($_GET['section']) ? (int)$_GET['section'] : 0;
$view_type = isset($_GET['view']) ? $_GET['view'] : 'live';

$sections = safeQuery("SELECT s.*, yl.level_name FROM sections s 
                       JOIN year_levels yl ON s.year_level_id = yl.id 
                       WHERE s.teacher_id = ? AND s.status = 'active' 
                       ORDER BY yl.sort_order", [$teacher_id], 'i');

$live_sql = "SELECT al.*, s.fullname, s.photo, sec.section_name 
             FROM attendance_log al
             JOIN students s ON al.student_id = s.id
             LEFT JOIN sections sec ON s.section_id = sec.id
             WHERE sec.teacher_id = ? AND al.log_date = ?";
$params = [$teacher_id, $date_filter];
$types = "is";

if($section_filter > 0) {
    $live_sql .= " AND s.section_id = ?";
    $params[] = $section_filter;
    $types .= "i";
}
$live_sql .= " ORDER BY al.time_captured DESC";
$attendance = safeQuery($live_sql, $params, $types);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Attendance Log - Teacher</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <style>
        .btn-primary .fa-clock {
            color: white;
        }
        
        .attendance-container {
            padding: 24px;
        }

        .student-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            object-fit: cover;
        }

        .student-avatar img {
            width: 35px;
            height: 35px;
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
            .attendance-container {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/teacher_navbar.php'; ?>
    <?php include '../includes/teacher_sidebar.php'; ?>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="main-content">
        <div class="attendance-container">
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
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Date</label>
                                <input type="date" id="dateFilter" class="form-control" value="<?php echo $date_filter; ?>" onchange="filterData()">
                            </div>
                        </div>
                        <div class="filter-group">
                            <div class="form-group" style="margin-bottom: 0;">
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
                        </div>
                    </div>
                    
                    <?php if($view_type == 'live'): ?>
                        <!-- Live View -->
                        <div class="table-wrapper">
                            <h4 style="margin-bottom: 16px;">
                                <i class="fas fa-clock"></i> Live Attendance 
                                <small class="text-muted">for <?php echo date('F d, Y', strtotime($date_filter)); ?></small>
                            </h4>
                            <table class="recent-table">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Student Name</th>
                                        <th>Section</th>
                                        <th>Log Type</th>
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
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['fullname']); ?></td>
                                        <td><?php echo htmlspecialchars($log['section_name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strpos($log['log_type'], 'in') !== false ? 'info' : 'warning'; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $log['log_type'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('h:i A', strtotime($log['time_captured'])); ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo strtoupper($log['method']); ?></span>
                                        </td>
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
                                                <i class="fas fa-calendar-day" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                                                No attendance records for this date
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- Summary View -->
                        <div class="table-wrapper">
                            <h4 style="margin-bottom: 16px;">
                                <i class="fas fa-chart-bar"></i> Summary Attendance 
                                <small class="text-muted">for <?php echo date('F d, Y', strtotime($date_filter)); ?></small>
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
                            WHERE sec.teacher_id = ? AND s.status = 'active'";
                            
                            $summary_params = [$date_filter, $teacher_id];
                            $summary_types = "si";
                            
                            if($section_filter > 0) {
                                $summary_sql .= " AND s.section_id = ?";
                                $summary_params[] = $section_filter;
                                $summary_types .= "i";
                            }
                            
                            $summary_sql .= " GROUP BY s.id ORDER BY s.fullname";
                            $summary = safeQuery($summary_sql, $summary_params, $summary_types);
                            ?>
                            <table class="recent-table">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Student Name</th>
                                        <th>Section</th>
                                        <th>Morning In</th>
                                        <th>Morning Out</th>
                                        <th>Afternoon In</th>
                                        <th>Afternoon Out</th>
                                        <th>Presence</th>
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
                                                <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                                                No students found for this section
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
    
    <script>
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
    </script>
</body>
</html>