<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('teacher');

// Get teacher ID from session
$teacher_sql = "SELECT id FROM teachers WHERE user_id = ?";
$teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
$teacher = $teacher_result ? mysqli_fetch_assoc($teacher_result) : null;
$teacher_id = $teacher['id'] ?? 0;

// Get teacher's sections
$sections_sql = "SELECT s.*, yl.level_name, 
                 (SELECT COUNT(*) FROM students WHERE section_id = s.id AND status = 'active') as student_count
                 FROM sections s
                 JOIN year_levels yl ON s.year_level_id = yl.id
                 WHERE s.teacher_id = ? AND s.status = 'active'";
$sections = $teacher_id > 0 ? safeQuery($sections_sql, [$teacher_id], 'i') : false;

$total_students = 0;
$section_count = 0;
$section_names = [];
$section_attendance = [];

while($sections && ($sec = mysqli_fetch_assoc($sections))) {
    $total_students += $sec['student_count'];
    $section_count++;
    $section_names[] = $sec['level_name'] . ' - ' . $sec['section_name'];
    
    // Get attendance rate for this section (last 7 days)
    $att_sql = "SELECT COUNT(DISTINCT al.student_id) as present,
                (SELECT COUNT(*) FROM students WHERE section_id = ? AND status = 'active') as total
                FROM attendance_log al
                JOIN students s ON al.student_id = s.id
                WHERE s.section_id = ? AND al.log_date = CURDATE()";
    $att_result = safeQuery($att_sql, [$sec['id'], $sec['id']], 'ii');
    $att_data = mysqli_fetch_assoc($att_result);
    $rate = $att_data['total'] > 0 ? round(($att_data['present'] / $att_data['total']) * 100) : 0;
    $section_attendance[] = $rate;
}

// Get present today
$present_today = 0;
if($section_count > 0) {
    $present_sql = "SELECT COUNT(DISTINCT al.student_id) as count 
                    FROM attendance_log al
                    JOIN students s ON al.student_id = s.id
                    JOIN sections sec ON s.section_id = sec.id
                    WHERE sec.teacher_id = ? AND al.log_date = CURDATE()";
    $present_result = safeQuery($present_sql, [$teacher_id], 'i');
    $present_data = $present_result ? mysqli_fetch_assoc($present_result) : null;
    $present_today = $present_data['count'] ?? 0;
}

// Get weekly attendance data (last 7 days)
$weekly_labels = [];
$weekly_data = [];
for($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $weekly_labels[] = date('D', strtotime($date));
    
    $weekly_sql = "SELECT COUNT(DISTINCT al.student_id) as count
                   FROM attendance_log al
                   JOIN students s ON al.student_id = s.id
                   JOIN sections sec ON s.section_id = sec.id
                   WHERE sec.teacher_id = ? AND al.log_date = ?";
    $weekly_result = safeQuery($weekly_sql, [$teacher_id, $date], 'is');
    $weekly_row = $weekly_result ? mysqli_fetch_assoc($weekly_result) : null;
    $weekly_data[] = $weekly_row['count'] ?? 0;
}

// Get monthly progress data (last 4 weeks)
$monthly_labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
$monthly_data = [];
for($i = 3; $i >= 0; $i--) {
    $start_date = date('Y-m-d', strtotime("-" . ($i + 1) . " weeks"));
    $end_date = date('Y-m-d', strtotime("-{$i} weeks -1 day"));
    
    $monthly_sql = "SELECT COUNT(DISTINCT al.student_id) as count
                    FROM attendance_log al
                    JOIN students s ON al.student_id = s.id
                    JOIN sections sec ON s.section_id = sec.id
                    WHERE sec.teacher_id = ? AND al.log_date BETWEEN ? AND ?";
    $monthly_result = safeQuery($monthly_sql, [$teacher_id, $start_date, $end_date], 'iss');
    $monthly_data[] = mysqli_fetch_assoc($monthly_result)['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-container {
            padding: 24px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 20px;
            padding: 24px 20px;
            transition: all 0.2s;
            border: 1px solid var(--grey);
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: var(--pink);
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 13px;
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
            transition: all 0.2s;
            height: 100%;
        }

        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--grey);
            font-weight: 600;
            font-size: 15px;
            color: #444;
            background: var(--white);
        }

        .card-header i {
            color: var(--pink);
            width: 24px;
        }

        .card-body {
            padding: 20px 24px;
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
            
            .stat-number {
                font-size: 28px;
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
    <?php include '../includes/teacher_navbar.php'; ?>
    <?php include '../includes/teacher_sidebar.php'; ?>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="main-content">
        <div class="dashboard-container">
            <!-- Stats Grid -->
            <div class="grid-stats" style="margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $section_count; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-layer-group"></i> My Sections
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-user-graduate"></i> Active Students
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $present_today; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-calendar-check"></i> Present Today
                    </div>
                </div>
            </div>
            
            <!-- Charts Grid -->
            <div class="grid-dashboard" style="margin-bottom: 24px;">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar"></i> Weekly Attendance Trend
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-pie"></i> Today's Attendance
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="todayChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section Attendance Cards -->
            <?php if($section_count > 0): ?>
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <i class="fas fa-chart-simple"></i> Section Attendance Today
                </div>
                <div class="card-body">
                    <div class="grid-dashboard" style="gap: 16px;">
                        <?php 
                        $idx = 0;
                        $sec_result = safeQuery($sections_sql, [$teacher_id], 'i');
                        while($sec = mysqli_fetch_assoc($sec_result)): 
                            // Get attendance for this section today
                            $sec_att_sql = "SELECT 
                                COUNT(DISTINCT CASE WHEN al.log_type IN ('morning_in', 'afternoon_in') THEN al.student_id END) as present,
                                (SELECT COUNT(*) FROM students WHERE section_id = ? AND status = 'active') as total
                                FROM attendance_log al
                                JOIN students s ON al.student_id = s.id
                                WHERE s.section_id = ? AND al.log_date = CURDATE()";
                            $sec_att_result = safeQuery($sec_att_sql, [$sec['id'], $sec['id']], 'ii');
                            $sec_att = mysqli_fetch_assoc($sec_att_result);
                            $present_count = $sec_att['present'] ?? 0;
                            $total_count = $sec_att['total'] ?? 0;
                            $percentage = $total_count > 0 ? round(($present_count / $total_count) * 100) : 0;
                        ?>
                            <div class="stat-card" style="text-align: left; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div class="stat-label" style="justify-content: flex-start; margin-bottom: 8px;">
                                        <?php echo $sec['level_name'] . ' - ' . $sec['section_name']; ?>
                                    </div>
                                    <div>
                                        <span style="font-size: 24px; font-weight: 700; color: var(--pink);"><?php echo $present_count; ?></span>
                                        <span style="color: #999;"> / <?php echo $total_count; ?> students</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="stat-number" style="font-size: 28px;"><?php echo $percentage; ?>%</div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Progress Chart -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i> Attendance Progress (Last 4 Weeks)
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="progressChart"></canvas>
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
        
        // Weekly attendance chart
        new Chart(document.getElementById('weeklyChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($weekly_labels); ?>,
                datasets: [{
                    label: 'Students Present',
                    data: <?php echo json_encode($weekly_data); ?>,
                    borderColor: '#ff69b4',
                    backgroundColor: 'rgba(255, 105, 180, 0.05)',
                    borderWidth: 2,
                    pointBackgroundColor: '#ff69b4',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' },
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
        
        // Today's attendance pie chart
        new Chart(document.getElementById('todayChart'), {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent'],
                datasets: [{
                    data: [<?php echo $present_today; ?>, <?php echo $total_students - $present_today; ?>],
                    backgroundColor: ['#ff69b4', '#e0e0e0'],
                    borderWidth: 0,
                    hoverOffset: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' },
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
        
        // Progress chart (last 4 weeks)
        new Chart(document.getElementById('progressChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: 'Students Present',
                    data: <?php echo json_encode($monthly_data); ?>,
                    borderColor: '#ff69b4',
                    backgroundColor: 'rgba(255, 105, 180, 0.05)',
                    borderWidth: 2,
                    pointBackgroundColor: '#ff69b4',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' },
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
    </script>
</body>
</html>