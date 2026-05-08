<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('teacher');

$teacher_sql = "SELECT id FROM teachers WHERE user_id = ?";
$teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];

$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');
$section_id = isset($_GET['section']) ? (int)$_GET['section'] : 0;

// Get sections for this teacher only
$sections = safeQuery("SELECT s.*, yl.level_name FROM sections s 
                       JOIN year_levels yl ON s.year_level_id = yl.id 
                       WHERE s.teacher_id = ? AND s.status = 'active' 
                       ORDER BY yl.sort_order", [$teacher_id], 'i');

// First, get all students for this teacher (for statistics)
$students_sql = "SELECT s.id, s.fullname, s.lrn, s.photo, sec.section_name, yl.level_name 
                 FROM students s
                 LEFT JOIN sections sec ON s.section_id = sec.id
                 LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                 WHERE sec.teacher_id = ? AND s.status = 'active'";
$students_params = [$teacher_id];
$students_types = "i";

if($section_id > 0) {
    $students_sql .= " AND s.section_id = ?";
    $students_params[] = $section_id;
    $students_types .= "i";
}

$students_sql .= " ORDER BY s.fullname";
$all_students = safeQuery($students_sql, $students_params, $students_types);

// Get total students count
$total_students = mysqli_num_rows($all_students);

// Get report data (attendance records)
$report_sql = "SELECT 
    s.id, s.fullname, s.lrn, s.photo,
    sec.section_name, yl.level_name,
    al.log_date,
    MAX(CASE WHEN al.log_type = 'morning_in' THEN TIME(al.time_captured) END) as morning_in,
    MAX(CASE WHEN al.log_type = 'morning_out' THEN TIME(al.time_captured) END) as morning_out,
    MAX(CASE WHEN al.log_type = 'afternoon_in' THEN TIME(al.time_captured) END) as afternoon_in,
    MAX(CASE WHEN al.log_type = 'afternoon_out' THEN TIME(al.time_captured) END) as afternoon_out,
    CASE 
        WHEN MAX(CASE WHEN al.log_type IN ('morning_in', 'afternoon_in') THEN 1 ELSE 0 END) = 1 THEN 'Present'
        ELSE 'Absent'
    END as status
FROM students s
LEFT JOIN sections sec ON s.section_id = sec.id
LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
LEFT JOIN attendance_log al ON s.id = al.student_id AND al.log_date BETWEEN ? AND ?
WHERE sec.teacher_id = ? AND s.status = 'active'";

$params = [$start_date, $end_date, $teacher_id];
$types = "ssi";

if($section_id > 0) {
    $report_sql .= " AND s.section_id = ?";
    $params[] = $section_id;
    $types .= "i";
}

$report_sql .= " GROUP BY s.id, al.log_date ORDER BY s.fullname, al.log_date DESC";
$report_data = safeQuery($report_sql, $params, $types);
$report_rows = [];
while($row = mysqli_fetch_assoc($report_data)) {
    $report_rows[] = $row;
}

// Calculate summary statistics based on ALL students (including those with no attendance)
$total_present_days = 0;
$total_possible_days = 0;
$date_range_days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;

// Reset the student list pointer
mysqli_data_seek($all_students, 0);

while($student = mysqli_fetch_assoc($all_students)) {
    $student_id = $student['id'];
    $total_possible_days += $date_range_days;
    
    // Count present days for this student from report data
    $present_count = 0;
    foreach($report_rows as $row) {
        if($row['id'] == $student_id && $row['status'] == 'Present') {
            $present_count++;
        }
    }
    $total_present_days += $present_count;
}

$total_absent_days = $total_possible_days - $total_present_days;
$attendance_rate = $total_possible_days > 0 ? round(($total_present_days / $total_possible_days) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Reports - Teacher</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <style>
        .btn-primary .fa-print {
            color: white;
        }
        
        .reports-container {
            padding: 24px;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .summary-card {
            background: var(--white);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--grey);
            transition: all 0.2s;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        .summary-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--pink);
            margin-bottom: 8px;
        }

        .summary-label {
            font-size: 13px;
            color: var(--grey-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
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
            .reports-container {
                padding: 16px;
            }
            
            .summary-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
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
        <div class="reports-container">
            <!-- Toast Notifications -->
            <?php if(isset($_GET['exported'])): ?>
                <div class="toast-notification toast-success" id="exportToast">
                    <i class="fas fa-check-circle"></i> Report exported successfully!
                </div>
                <script>
                    setTimeout(() => {
                        const toast = document.getElementById('exportToast');
                        if(toast) {
                            toast.style.animation = 'slideOut 0.3s ease';
                            setTimeout(() => toast.remove(), 300);
                        }
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <!-- Summary Statistics -->
            <div class="summary-stats">
                <div class="summary-card">
                    <div class="summary-number"><?php echo $total_students; ?></div>
                    <div class="summary-label">
                        <i class="fas fa-user-graduate"></i> Total Students
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?php echo $total_present_days; ?></div>
                    <div class="summary-label">
                        <i class="fas fa-check-circle"></i> Present Days
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?php echo $total_absent_days; ?></div>
                    <div class="summary-label">
                        <i class="fas fa-times-circle"></i> Absent Days
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?php echo $attendance_rate; ?>%</div>
                    <div class="summary-label">
                        <i class="fas fa-chart-line"></i> Attendance Rate
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i>
                    <span>Attendance Reports</span>
                    <div style="margin-left: auto; display: flex; gap: 8px;">
                        <button class="btn btn-primary" onclick="printReport()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button class="btn btn-outline" onclick="exportData()">
                            <i class="fas fa-file-excel"></i> Export
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filter Bar -->
                    <div class="search-bar">
                        <div class="filter-group">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Start Date</label>
                                <input type="date" id="startDate" class="form-control" value="<?php echo $start_date; ?>" onchange="filterReport()">
                            </div>
                        </div>
                        <div class="filter-group">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">End Date</label>
                                <input type="date" id="endDate" class="form-control" value="<?php echo $end_date; ?>" onchange="filterReport()">
                            </div>
                        </div>
                        <div class="filter-group">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Section</label>
                                <select id="sectionFilter" class="form-control" onchange="filterReport()">
                                    <option value="0">All Sections</option>
                                    <?php while($sec = mysqli_fetch_assoc($sections)): ?>
                                        <option value="<?php echo $sec['id']; ?>" <?php echo $section_id == $sec['id'] ? 'selected' : ''; ?>>
                                            <?php echo $sec['level_name'] . ' - ' . $sec['section_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="filter-group">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">&nbsp;</label>
                                <button class="btn btn-secondary" onclick="resetFilters()" style="width: 100%;">
                                    <i class="fas fa-undo-alt"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Report Table -->
                    <div class="table-wrapper" id="reportTable">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Student Name</th>
                                    <th>LRN</th>
                                    <th>Section</th>
                                    <th>Date</th>
                                    <th>Morning In</th>
                                    <th>Morning Out</th>
                                    <th>Afternoon In</th>
                                    <th>Afternoon Out</th>
                                    <th>Status</th>
                                </td>
                            </thead>
                            <tbody>
                                <?php if(!empty($report_rows)): ?>
                                    <?php foreach($report_rows as $row): ?>
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
                                        </div>
                                        <td><?php echo htmlspecialchars($row['fullname']); ?></div>
                                        <td><?php echo htmlspecialchars($row['lrn']); ?></div>
                                        <td><?php echo htmlspecialchars($row['level_name'] . ' - ' . $row['section_name']); ?></div>
                                        <td><?php echo date('M d, Y', strtotime($row['log_date'])); ?></div>
                                        <td><?php echo $row['morning_in'] ? date('h:i A', strtotime($row['morning_in'])) : '-'; ?></div>
                                        <td><?php echo $row['morning_out'] ? date('h:i A', strtotime($row['morning_out'])) : '-'; ?></div>
                                        <td><?php echo $row['afternoon_in'] ? date('h:i A', strtotime($row['afternoon_in'])) : '-'; ?></div>
                                        <td><?php echo $row['afternoon_out'] ? date('h:i A', strtotime($row['afternoon_out'])) : '-'; ?></div>
                                        <td>
                                            <span class="badge badge-<?php echo $row['status'] == 'Present' ? 'success' : 'danger'; ?>">
                                                <?php echo $row['status']; ?>
                                            </span>
                                        </div>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center" style="padding: 40px; color: #999;">
                                            <i class="fas fa-chart-line" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                                            No data found for selected criteria
                                            <br>
                                            <small>Try adjusting the date range or section filter</small>
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
        
        function filterReport() {
            let start = document.getElementById('startDate').value;
            let end = document.getElementById('endDate').value;
            let section = document.getElementById('sectionFilter').value;
            window.location.href = `?start_date=${start}&end_date=${end}&section=${section}`;
        }
        
        function resetFilters() {
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            document.getElementById('sectionFilter').value = '0';
            filterReport();
        }
        
        function printReport() {
            window.print();
        }
        
        function exportData() {
            let table = document.querySelector('.recent-table').cloneNode(true);
            let startDate = document.getElementById('startDate').value;
            let endDate = document.getElementById('endDate').value;
            let sectionName = document.getElementById('sectionFilter').options[document.getElementById('sectionFilter').selectedIndex]?.text || 'All Sections';
            
            let exportHtml = `
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Attendance Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h2 { color: #ff69b4; margin-bottom: 10px; }
                        .info { margin-bottom: 20px; color: #666; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f5f5f5; }
                        tr:nth-child(even) { background-color: #f9f9f9; }
                    </style>
                </head>
                <body>
                    <h2>Attendance Report</h2>
                    <div class="info">
                        <strong>Period:</strong> ${startDate} to ${endDate}<br>
                        <strong>Section:</strong> ${sectionName}<br>
                        <strong>Generated:</strong> ${new Date().toLocaleString()}
                    </div>
                    ${table.outerHTML}
                </body>
                </html>
            `;
            
            let filename = `attendance_report_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.xls`;
            let downloadLink = document.createElement("a");
            let blob = new Blob([exportHtml], { type: 'application/vnd.ms-excel' });
            let url = URL.createObjectURL(blob);
            downloadLink.href = url;
            downloadLink.download = filename;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            URL.revokeObjectURL(url);
            
            // Show success notification
            showExportNotification();
        }
        
        function showExportNotification() {
            const toast = document.createElement('div');
            toast.className = 'toast-notification toast-success';
            toast.innerHTML = '<i class="fas fa-check-circle"></i> Report exported successfully!';
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>