<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if student is logged in
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['fullname'] ?? $_SESSION['username'];
$student_username = $_SESSION['username'];

function fetchSingleRow($sql, $params = [], $types = '') {
    $result = safeQuery($sql, $params, $types);
    if($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

// Get student record - IMPORTANT: Match by user_id from users table
// The students table does NOT have user_id, so we need to match by username or fullname
$student = null;
$student_record_id = null;

// First attempt: Try to find student by fullname matching the logged-in user's fullname
$student_sql = "SELECT s.*, sec.section_name, yl.level_name, u.fullname as teacher_name
                FROM students s
                LEFT JOIN sections sec ON s.section_id = sec.id
                LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                LEFT JOIN teachers t ON sec.teacher_id = t.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE s.fullname = ?";
$student = fetchSingleRow($student_sql, [$student_name], 's');

if($student) {
    $student_record_id = $student['id'];
}

// Second attempt: Try by username match with student's email prefix
if(!$student) {
    $student_sql = "SELECT s.*, sec.section_name, yl.level_name, u.fullname as teacher_name
                    FROM students s
                    LEFT JOIN sections sec ON s.section_id = sec.id
                    LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                    LEFT JOIN teachers t ON sec.teacher_id = t.id
                    LEFT JOIN users u ON t.user_id = u.id
                    WHERE s.lrn = ? OR s.fullname LIKE ?";
    $student = fetchSingleRow($student_sql, [$student_username, "%$student_name%"], 'ss');
    
    if($student) {
        $student_record_id = $student['id'];
    }
}

// Third attempt: Get the student that matches the logged-in user's username in the users table
// The users table has username like 'alice.mendoza' and students table has fullname 'Alice Mendoza'
if(!$student) {
    // Extract first name from username (e.g., alice.mendoza -> Alice)
    $name_parts = explode('.', $student_username);
    $first_name = ucfirst($name_parts[0] ?? '');
    
    $student_sql = "SELECT s.*, sec.section_name, yl.level_name, u.fullname as teacher_name
                    FROM students s
                    LEFT JOIN sections sec ON s.section_id = sec.id
                    LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                    LEFT JOIN teachers t ON sec.teacher_id = t.id
                    LEFT JOIN users u ON t.user_id = u.id
                    WHERE s.fullname LIKE ?";
    $student = fetchSingleRow($student_sql, ["$first_name%"], 's');
    
    if($student) {
        $student_record_id = $student['id'];
    }
}

// If still no student found, show error message
if(!$student) {
    // Display error in browser logs
    error_log("Student Dashboard - No student found for user: $student_username / $student_name");
    
    // Create minimal student object to prevent errors
    $student = [
        'id' => 0,
        'fullname' => $student_name,
        'lrn' => 'Not found',
        'section_name' => 'Not Assigned',
        'level_name' => '',
        'teacher_name' => 'Not Assigned',
        'parent_name' => 'Not provided'
    ];
    $student_record_id = 0;
}

// Debug: Log the student ID being used
error_log("Student Dashboard - Student Record ID: " . $student_record_id . " for user: " . $student_username);

// Get attendance statistics for the logged-in student
$attendance_stats = safeQuery("
    SELECT 
        COUNT(CASE WHEN status = 'on_time' THEN 1 END) as on_time,
        COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
        COUNT(*) as total,
        COUNT(DISTINCT log_date) as days_present
    FROM attendance_log 
    WHERE student_id = ?
", [$student_record_id], 'i');

$stats = $attendance_stats ? mysqli_fetch_assoc($attendance_stats) : null;
$stats = $stats ?: ['on_time' => 0, 'late' => 0, 'total' => 0, 'days_present' => 0];

// Get weekly attendance data (last 7 days)
$weekly_attendance = [];
for($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('D', strtotime($date));
    
    $day_sql = "SELECT COUNT(*) as count, 
                       MAX(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as was_late
                FROM attendance_log 
                WHERE student_id = ? AND log_date = ? AND log_type IN ('morning_in', 'afternoon_in')";
    $day_result = safeQuery($day_sql, [$student_record_id, $date], 'is');
    $day_data = $day_result ? mysqli_fetch_assoc($day_result) : null;
    $day_data = $day_data ?: ['count' => 0, 'was_late' => 0];
    
    $weekly_attendance[] = [
        'day' => $day_name,
        'date' => $date,
        'present' => $day_data['count'] > 0,
        'late' => $day_data['was_late'] > 0
    ];
}

// Get recent attendance records
$recent_attendance = safeQuery("
    SELECT log_date, log_type, time_captured, status, method
    FROM attendance_log 
    WHERE student_id = ? 
    ORDER BY log_date DESC, time_captured DESC 
    LIMIT 10
", [$student_record_id], 'i');

// Get monthly summary (last 4 weeks)
$monthly_summary = [];
for($i = 3; $i >= 0; $i--) {
    $week_start = date('Y-m-d', strtotime("-" . ($i + 1) . " weeks"));
    $week_end = date('Y-m-d', strtotime("-{$i} weeks -1 day"));
    
    $week_sql = "SELECT COUNT(*) as present_count
                 FROM attendance_log 
                 WHERE student_id = ? AND log_date BETWEEN ? AND ? 
                 AND log_type IN ('morning_in', 'afternoon_in')";
    $week_result = safeQuery($week_sql, [$student_record_id, $week_start, $week_end], 'iss');
    $week_data = $week_result ? mysqli_fetch_assoc($week_result) : null;
    $week_data = $week_data ?: ['present_count' => 0];
    
    $monthly_summary[] = [
        'week' => 'Week ' . (4 - $i),
        'present' => $week_data['present_count']
    ];
}

// Calculate attendance rate
$attendance_rate = 0;
if($stats['total'] > 0) {
    $attendance_rate = round(($stats['on_time'] / $stats['total']) * 100, 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Student Dashboard - Smart Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-container {
            padding: 24px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title i {
            color: var(--pink);
            font-size: 28px;
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

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .info-item {
            background: var(--white-smoke);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .info-label {
            font-size: 11px;
            color: var(--grey-dark);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: #333;
        }

        .card {
            border-radius: 20px;
            border: 1px solid var(--grey);
            background: var(--white);
            margin-bottom: 24px;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--grey);
            font-weight: 600;
            font-size: 15px;
            color: #444;
            background: var(--white);
        }

        .card-header i {
            color: var(--pink);
            margin-right: 8px;
        }

        .card-body {
            padding: 20px;
        }

        .week-grid {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 16px;
        }

        .week-day {
            flex: 1;
            text-align: center;
            padding: 12px 8px;
            background: var(--white-smoke);
            border-radius: 12px;
            transition: all 0.2s;
        }

        .week-day.present {
            background: #e8f5e9;
        }

        .week-day.late {
            background: #fff3e0;
        }

        .week-day.absent {
            background: #ffebee;
        }

        .day-name {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #666;
        }

        .day-date {
            font-size: 10px;
            color: #999;
        }

        .day-status {
            font-size: 20px;
            margin-top: 6px;
        }

        .day-status.present { color: #4caf50; }
        .day-status.late { color: #ff9800; }
        .day-status.absent { color: #f44336; }

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

        .badge-info {
            background: #e3f2fd;
            color: #2196f3;
        }

        .badge-warning {
            background: #fff3e0;
            color: #ff9800;
        }

        .chart-container {
            position: relative;
            height: 250px;
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
            .dashboard-container {
                padding: 16px;
            }
            
            .page-title {
                font-size: 20px;
            }
            
            .stat-number {
                font-size: 28px;
            }
            
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .week-grid {
                gap: 8px;
            }
            
            .week-day {
                padding: 8px 4px;
            }
            
            .day-name {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/student_navbar.php'; ?>
    <?php include '../includes/student_sidebar.php'; ?>
    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="main-content">
        <div class="dashboard-container">
            <!-- Page Title -->
            <div class="page-title">
                <i class="fas fa-chalkboard-user"></i>
                <span>Student Dashboard</span>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid-stats" style="margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['days_present'] ?? 0; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-calendar-check"></i> Days Present
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['on_time'] ?? 0; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-clock"></i> On Time
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['late'] ?? 0; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-exclamation-triangle"></i> Late
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                    <div class="stat-label">
                        <i class="fas fa-chart-line"></i> Punctuality Rate
                    </div>
                </div>
            </div>
            
            <!-- Student Information -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-circle"></i> My Information
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['fullname'] ?? $student_name); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Username</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_username); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">LRN</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['lrn'] ?? 'Not set'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Section</div>
                            <div class="info-value"><?php echo htmlspecialchars(($student['level_name'] ? $student['level_name'] . ' - ' : '') . ($student['section_name'] ?? 'Not assigned')); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Adviser</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['teacher_name'] ?? 'Not assigned'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Parent/Guardian</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['parent_name'] ?? 'Not provided'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Weekly Attendance -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-calendar-week"></i> This Week's Attendance
                </div>
                <div class="card-body">
                    <?php if(array_filter($weekly_attendance, function($day) { return $day['present']; })): ?>
                        <div class="week-grid">
                            <?php foreach($weekly_attendance as $day): ?>
                                <div class="week-day <?php 
                                    echo $day['present'] ? ($day['late'] ? 'late' : 'present') : 'absent'; 
                                ?>">
                                    <div class="day-name"><?php echo $day['day']; ?></div>
                                    <div class="day-date"><?php echo date('m/d', strtotime($day['date'])); ?></div>
                                    <div class="day-status <?php 
                                        echo $day['present'] ? ($day['late'] ? 'late' : 'present') : 'absent'; 
                                    ?>">
                                        <i class="fas fa-<?php 
                                            echo $day['present'] ? ($day['late'] ? 'clock' : 'check-circle') : 'times-circle'; 
                                        ?>"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center" style="padding: 20px; color: #999;">
                            <i class="fas fa-calendar-week"></i> No attendance records for this week
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="grid-dashboard" style="margin-bottom: 24px;">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar"></i> Monthly Attendance Trend
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-pie"></i> Attendance Distribution
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="distributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Attendance Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history"></i> Recent Attendance Records
                </div>
                <div class="card-body">
                    <div class="table-wrapper">
                        <?php if($recent_attendance && mysqli_num_rows($recent_attendance) > 0): ?>
                            <table class="recent-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Log Type</th>
                                        <th>Time</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($log = mysqli_fetch_assoc($recent_attendance)): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($log['log_date'])); ?></div>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $log['log_type'])); ?></div>
                                        <td><?php echo date('h:i A', strtotime($log['time_captured'])); ?></div>
                                        <td><span class="badge badge-info"><?php echo strtoupper($log['method']); ?></span></div>
                                        <td>
                                            <span class="badge badge-<?php echo $log['status'] == 'on_time' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($log['status']); ?>
                                            </span>
                                        </div>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state" style="text-align: center; padding: 40px; color: #999;">
                                <i class="fas fa-calendar-day" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                                <p>No attendance records found</p>
                                <small>Scan your QR code at the scanner to record attendance</small>
                            </div>
                        <?php endif; ?>
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
        
        // Monthly Trend Chart
        const trendCtx = document.getElementById('trendChart')?.getContext('2d');
        if(trendCtx) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($monthly_summary, 'week')); ?>,
                    datasets: [{
                        label: 'Days Present',
                        data: <?php echo json_encode(array_column($monthly_summary, 'present')); ?>,
                        borderColor: '#ff69b4',
                        backgroundColor: 'rgba(255, 105, 180, 0.05)',
                        borderWidth: 2,
                        pointBackgroundColor: '#ff69b4',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 12, font: { size: 11 } }
                        },
                        tooltip: {
                            backgroundColor: '#fff',
                            titleColor: '#333',
                            bodyColor: '#666',
                            borderColor: '#e0e0e0',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f0f0f0' },
                            ticks: { stepSize: 1, font: { size: 11 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11 } }
                        }
                    }
                }
            });
        }
        
        // Distribution Chart
        const distCtx = document.getElementById('distributionChart')?.getContext('2d');
        if(distCtx) {
            new Chart(distCtx, {
                type: 'doughnut',
                data: {
                    labels: ['On Time', 'Late'],
                    datasets: [{
                        data: [<?php echo $stats['on_time'] ?? 0; ?>, <?php echo $stats['late'] ?? 0; ?>],
                        backgroundColor: ['#ff69b4', '#ffb6c1'],
                        borderWidth: 0,
                        hoverOffset: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 12, font: { size: 11 } }
                        },
                        tooltip: {
                            backgroundColor: '#fff',
                            titleColor: '#333',
                            bodyColor: '#666',
                            borderColor: '#e0e0e0',
                            borderWidth: 1,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percent = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percent}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }
    </script>
</body>
</html>