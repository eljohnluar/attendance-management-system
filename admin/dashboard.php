<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('admin');

function getCountValue($sql, $params = [], $types = '') {
    $result = safeQuery($sql, $params, $types);
    if($result && ($row = mysqli_fetch_assoc($result))) {
        return (int)$row['count'];
    }
    return 0;
}

$active_students = getCountValue("SELECT COUNT(*) as count FROM students WHERE status = 'active'");
$active_teachers = getCountValue("SELECT COUNT(*) as count FROM users WHERE role = 'teacher' AND status = 'active'");
$total_sections = getCountValue("SELECT COUNT(*) as count FROM sections WHERE status = 'active'");
$today_logs = getCountValue("SELECT COUNT(*) as count FROM attendance_log WHERE log_date = CURDATE()");

// Get attendance trend for last 7 days
$trend_data = [];
$trend_labels = [];
for($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $trend_labels[] = date('D', strtotime($date));
    $count = getCountValue("SELECT COUNT(*) as count FROM attendance_log WHERE log_date = ?", [$date], 's');
    $trend_data[] = $count;
}

$recent_logs = safeQuery("SELECT al.*, s.fullname, sec.section_name 
                         FROM attendance_log al 
                         JOIN students s ON al.student_id = s.id 
                         LEFT JOIN sections sec ON s.section_id = sec.id 
                         WHERE al.log_date = CURDATE() 
                         ORDER BY al.time_captured DESC LIMIT 10");

$inactive_students = getCountValue("SELECT COUNT(*) as count FROM students WHERE status = 'inactive'");

// Get top sections by attendance
$top_sections = safeQuery("SELECT sec.section_name, yl.level_name, COUNT(al.id) as attendance_count
                          FROM sections sec
                          JOIN year_levels yl ON sec.year_level_id = yl.id
                          LEFT JOIN students s ON s.section_id = sec.id
                          LEFT JOIN attendance_log al ON al.student_id = s.id AND al.log_date = CURDATE()
                          WHERE sec.status = 'active'
                          GROUP BY sec.id
                          ORDER BY attendance_count DESC
                          LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>Admin Dashboard - Smart Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-container {
            padding: 24px;
            max-width: 100%;
            margin: 0;
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

        .stat-label i {
            font-size: 14px;
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

        .recent-table {
            width: 100%;
        }

        .recent-table th {
            font-size: 12px;
            font-weight: 600;
            color: #888;
            padding: 12px 8px;
            background: var(--white-smoke);
        }

        .recent-table td {
            padding: 12px 8px;
            font-size: 13px;
            border-bottom: 1px solid var(--grey);
        }

        .recent-table tr:last-child td {
            border-bottom: none;
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

        .section-name-text {
            font-weight: 500;
            color: #333;
        }

        .time-text {
            color: #999;
            font-size: 12px;
        }

        .chart-container {
            position: relative;
            height: 250px;
        }

        .top-section-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--grey);
        }

        .top-section-item:last-child {
            border-bottom: none;
        }

        .section-info {
            display: flex;
            flex-direction: column;
        }

        .section-name {
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .section-level {
            font-size: 11px;
            color: #999;
            margin-top: 2px;
        }

        .section-count {
            font-weight: 600;
            color: var(--pink);
            font-size: 18px;
        }

        /* Ensure dashboard fits within sidebar layout */
        .main-content {
            margin-top: var(--navbar-height);
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - var(--navbar-height));
            width: 100%;
            overflow-x: hidden;
        }

        /* Desktop styles - content fits next to sidebar */
        @media (min-width: 1024px) {
            .main-content {
                margin-left: var(--sidebar-width);
                width: calc(100% - var(--sidebar-width));
            }
        }

        /* Mobile styles */
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
            
            .grid-dashboard {
                grid-template-columns: 1fr;
            }
            
            .grid-stats {
                grid-template-columns: repeat(2, 1fr);
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
        <div class="dashboard-container">
            <!-- Stats Grid -->
            <div class="grid-stats" style="margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $active_students; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-user-graduate"></i> Active Students
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $active_teachers; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-chalkboard-teacher"></i> Teachers
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_sections; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-layer-group"></i> Sections
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $today_logs; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-calendar-check"></i> Logs Today
                    </div>
                </div>
            </div>
            
            <!-- Charts Grid -->
            <div class="grid-dashboard" style="margin-bottom: 24px;">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i> Attendance Trend
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-pie"></i> Student Status
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bottom Grid -->
            <div class="grid-dashboard" style="margin-bottom: 24px;">
                <!-- Recent Logs Table -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clock"></i> Recent Activity
                    </div>
                    <div class="card-body">
                        <div class="table-wrapper">
                            <table class="recent-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Section</th>
                                        <th>Type</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($recent_logs) > 0): ?>
                                        <?php while($log = mysqli_fetch_assoc($recent_logs)): ?>
                                        <tr>
                                            <td class="section-name-text"><?php echo htmlspecialchars($log['fullname']); ?></td>
                                            <td><?php echo htmlspecialchars($log['section_name']); ?></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $log['log_type'])); ?></td>
                                            <td class="time-text"><?php echo date('h:i A', strtotime($log['time_captured'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $log['status'] === 'late' ? 'danger' : 'success'; ?>">
                                                    <?php echo ucfirst($log['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center" style="padding: 40px; color: #999;">
                                                <i class="fas fa-calendar-day" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                                                No attendance records today
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Top Sections Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-trophy"></i> Top Sections Today
                    </div>
                    <div class="card-body">
                        <?php if(mysqli_num_rows($top_sections) > 0): ?>
                            <?php while($section = mysqli_fetch_assoc($top_sections)): ?>
                                <div class="top-section-item">
                                    <div class="section-info">
                                        <span class="section-name"><?php echo htmlspecialchars($section['section_name']); ?></span>
                                        <span class="section-level"><?php echo htmlspecialchars($section['level_name']); ?></span>
                                    </div>
                                    <div class="section-count">
                                        <?php echo $section['attendance_count']; ?> <small style="font-size: 11px; color: #999;">logs</small>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center" style="padding: 40px; color: #999;">
                                <i class="fas fa-chart-simple" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                                No data available
                            </div>
                        <?php endif; ?>
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
        
        // Attendance Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trend_labels); ?>,
                datasets: [{
                    label: 'Attendance Count',
                    data: <?php echo json_encode($trend_data); ?>,
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
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { size: 11 }
                        }
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
        
        // Student Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Inactive'],
                datasets: [{
                    data: [<?php echo $active_students; ?>, <?php echo $inactive_students; ?>],
                    backgroundColor: ['#ff69b4', '#e0e0e0'],
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
                        labels: {
                            boxWidth: 12,
                            font: { size: 11 }
                        }
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
    </script>
</body>
</html>