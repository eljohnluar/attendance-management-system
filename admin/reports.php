<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('admin');

$report_type = isset($_GET['type']) ? $_GET['type'] : 'daily';
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');
$section_id = isset($_GET['section']) ? (int)$_GET['section'] : 0;

// Adjust dates based on report type
if($report_type == 'weekly') {
    $start_date = date('Y-m-d', strtotime('-7 days'));
    $end_date = date('Y-m-d');
} elseif($report_type == 'monthly') {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
}

// Get sections for filter
$sections = safeQuery("SELECT s.*, yl.level_name FROM sections s 
                       JOIN year_levels yl ON s.year_level_id = yl.id 
                       WHERE s.status = 'active' ORDER BY yl.sort_order");

// Generate report data
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
WHERE s.status = 'active'";

$params = [$start_date, $end_date];
$types = "ss";

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

// Calculate summary statistics
$total_students = 0;
$total_present = 0;
$total_absent = 0;
$attendance_rate = 0;

// Better calculation: count daily attendance
$student_present_days = [];
$student_total_days = [];

if(!empty($report_rows)) {
    $unique_students = [];
    $date_range_days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
    
    foreach($report_rows as $row) {
        $student_id = $row['id'];
        
        if(!in_array($student_id, $unique_students)) {
            $unique_students[] = $student_id;
            $total_students++;
            $student_total_days[$student_id] = $date_range_days;
            $student_present_days[$student_id] = 0;
        }
        
        if($row['status'] == 'Present') {
            $student_present_days[$student_id]++;
        }
    }
    
    // Count total present (sum of present days across all students)
    $total_present = array_sum($student_present_days);
    $total_absent = ($total_students * $date_range_days) - $total_present;
    $attendance_rate = ($total_students * $date_range_days) > 0 ? round(($total_present / ($total_students * $date_range_days)) * 100, 1) : 0;
}

// Check for export success
$show_export = isset($_GET['exported']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Reports - Admin</title>
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
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--grey);
            transition: all 0.2s;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
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
            padding: 10px 18px;
            border-radius: 8px;
            color: white;
            font-size: 13px;
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
    <?php include '../includes/admin_navbar.php'; ?>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="main-content">
        <div class="reports-container">
            <!-- Toast Notifications -->
            <?php if($show_export): ?>
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
                    <div class="summary-number"><?php echo $total_present; ?></div>
                    <div class="summary-label">
                        <i class="fas fa-check-circle"></i> Present Days
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?php echo $total_absent; ?></div>
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
                            <label class="form-label">Report Type</label>
                            <select id="reportType" class="form-control" onchange="filterReport()">
                                <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo $report_type == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="form-label">Start Date</label>
                            <input type="date" id="startDate" class="form-control" value="<?php echo $start_date; ?>" onchange="filterReport()">
                        </div>
                        <div class="filter-group">
                            <label class="form-label">End Date</label>
                            <input type="date" id="endDate" class="form-control" value="<?php echo $end_date; ?>" onchange="filterReport()">
                        </div>
                        <div class="filter-group">
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
                        <div class="filter-group">
                            <label class="form-label">&nbsp;</label>
                            <button class="btn btn-secondary" onclick="resetFilters()" style="width: 100%;">
                                <i class="fas fa-undo-alt"></i> Reset
                            </button>
                        </div>
                    </div>
                    
                    <!-- Report Table -->
                    <div class="table-wrapper">
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
                                </tr>
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
                                        </td>
                                        <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                        <td><?php echo htmlspecialchars($row['lrn']); ?></td>
                                        <td><?php echo htmlspecialchars($row['level_name'] . ' - ' . $row['section_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['log_date'])); ?></td>
                                        <td><?php echo $row['morning_in'] ? date('h:i A', strtotime($row['morning_in'])) : '-'; ?></td>
                                        <td><?php echo $row['morning_out'] ? date('h:i A', strtotime($row['morning_out'])) : '-'; ?></td>
                                        <td><?php echo $row['afternoon_in'] ? date('h:i A', strtotime($row['afternoon_in'])) : '-'; ?></td>
                                        <td><?php echo $row['afternoon_out'] ? date('h:i A', strtotime($row['afternoon_out'])) : '-'; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $row['status'] == 'Present' ? 'success' : 'danger'; ?>">
                                                <?php echo $row['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center" style="padding: 40px; color: #999;">
                                            <i class="fas fa-chart-line" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                                            No data found for selected criteria
                                            <br>
                                            <small>Try adjusting the date range or section filter</small>
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
            let type = document.getElementById('reportType').value;
            let start = document.getElementById('startDate').value;
            let end = document.getElementById('endDate').value;
            let section = document.getElementById('sectionFilter').value;
            window.location.href = `?type=${type}&start_date=${start}&end_date=${end}&section=${section}`;
        }
        
        function resetFilters() {
            document.getElementById('reportType').value = 'daily';
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            document.getElementById('sectionFilter').value = '0';
            window.location.href = `?type=daily&start_date=&end_date=&section=0`;
        }
        
        function printReport() {
            window.print();
        }
        
        function exportData() {
            let table = document.querySelector('.recent-table').cloneNode(true);
            let dateRange = document.getElementById('startDate').value || document.getElementById('startDate').placeholder;
            let endDate = document.getElementById('endDate').value || document.getElementById('endDate').placeholder;
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
                        <strong>Period:</strong> ${dateRange} to ${endDate}<br>
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
        
        // Auto-adjust dates based on report type
        document.getElementById('reportType')?.addEventListener('change', function() {
            let type = this.value;
            let today = new Date();
            let startDate = new Date();
            
            if(type === 'weekly') {
                startDate.setDate(today.getDate() - 7);
            } else if(type === 'monthly') {
                startDate.setDate(today.getDate() - 30);
            } else {
                startDate = today;
                startDate.setDate(today.getDate());
            }
            
            document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
            document.getElementById('endDate').value = today.toISOString().split('T')[0];
            filterReport();
        });
    </script>
</body>
</html>