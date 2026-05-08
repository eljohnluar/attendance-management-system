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

// Get student record ID from students table - match by fullname
$student_record = null;
$student_record_id = null;

// First attempt: Get student by fullname matching logged-in user
$student_sql = "SELECT s.id, s.fullname, s.lrn, s.photo, sec.section_name, yl.level_name 
                FROM students s
                LEFT JOIN sections sec ON s.section_id = sec.id
                LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                WHERE s.fullname = ?";
$student_result = safeQuery($student_sql, [$student_name], 's');

if($student_result && mysqli_num_rows($student_result) > 0) {
    $student_record = mysqli_fetch_assoc($student_result);
    $student_record_id = $student_record['id'];
}

// Second attempt: Try by username prefix (e.g., alice.mendoza -> Alice)
if(!$student_record) {
    $name_parts = explode('.', $student_username);
    $first_name = ucfirst($name_parts[0] ?? '');
    
    $student_sql = "SELECT s.id, s.fullname, s.lrn, s.photo, sec.section_name, yl.level_name 
                    FROM students s
                    LEFT JOIN sections sec ON s.section_id = sec.id
                    LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                    WHERE s.fullname LIKE ?";
    $student_result = safeQuery($student_sql, ["$first_name%"], 's');
    
    if($student_result && mysqli_num_rows($student_result) > 0) {
        $student_record = mysqli_fetch_assoc($student_result);
        $student_record_id = $student_record['id'];
    }
}

// Third attempt: Try to get first student (for demo if no match)
if(!$student_record) {
    $student_sql = "SELECT s.id, s.fullname, s.lrn, s.photo, sec.section_name, yl.level_name 
                    FROM students s
                    LEFT JOIN sections sec ON s.section_id = sec.id
                    LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                    LIMIT 1";
    $student_result = safeQuery($student_sql, [], '');
    
    if($student_result && mysqli_num_rows($student_result) > 0) {
        $student_record = mysqli_fetch_assoc($student_result);
        $student_record_id = $student_record['id'];
    }
}

// If still no record, create placeholder
if(!$student_record) {
    $student_record = [
        'id' => 0,
        'fullname' => $student_name,
        'lrn' => 'Not set',
        'photo' => 'default_student.png',
        'section_name' => 'Not assigned',
        'level_name' => ''
    ];
    $student_record_id = 0;
}

// Debug log
error_log("Student Attendance - Student Record ID: " . $student_record_id . " for user: " . $student_username);

// Get filter parameters
$month_filter = isset($_GET['month']) ? sanitize($_GET['month']) : date('Y-m');
$year_filter = isset($_GET['year']) ? sanitize($_GET['year']) : date('Y');
$filter_type = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'monthly';

// Parse month and year
$selected_month = $month_filter;
$selected_year = $year_filter;
$selected_date = $selected_year . '-' . $selected_month . '-01';

// Build query based on filter type
if($filter_type == 'monthly') {
    $sql = "SELECT al.*, 
                   DATE_FORMAT(al.log_date, '%Y-%m-%d') as log_date_formatted,
                   DATE_FORMAT(al.log_date, '%W') as day_name
            FROM attendance_log al
            WHERE al.student_id = ? 
            AND DATE_FORMAT(al.log_date, '%Y-%m') = ?
            ORDER BY al.log_date DESC, al.time_captured DESC";
    $result = safeQuery($sql, [$student_record_id, $month_filter], 'is');
} else {
    // Date range filter
    $start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');
    
    $sql = "SELECT al.*, 
                   DATE_FORMAT(al.log_date, '%Y-%m-%d') as log_date_formatted,
                   DATE_FORMAT(al.log_date, '%W') as day_name
            FROM attendance_log al
            WHERE al.student_id = ? 
            AND al.log_date BETWEEN ? AND ?
            ORDER BY al.log_date DESC, al.time_captured DESC";
    $result = safeQuery($sql, [$student_record_id, $start_date, $end_date], 'iss');
}

// Get attendance records
$attendance_records = [];
if($result && mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $attendance_records[] = $row;
    }
}

// Calculate summary statistics
$total_days = 0;
$present_days = 0;
$late_count = 0;
$on_time_count = 0;
$unique_dates = [];

foreach($attendance_records as $record) {
    if(!in_array($record['log_date'], $unique_dates)) {
        $unique_dates[] = $record['log_date'];
        $total_days++;
    }
    if($record['log_type'] == 'morning_in' || $record['log_type'] == 'afternoon_in') {
        $present_days++;
    }
    if($record['status'] == 'late') {
        $late_count++;
    } else {
        $on_time_count++;
    }
}

$attendance_rate = $total_days > 0 ? round(($present_days / ($total_days * 2)) * 100, 1) : 0;
$punctuality_rate = ($present_days + $late_count) > 0 ? round(($on_time_count / ($present_days + $late_count)) * 100, 1) : 0;

// Get monthly summary for chart
$monthly_summary = [];
for($i = 5; $i >= 0; $i--) {
    $month_date = date('Y-m-d', strtotime("-$i months"));
    $month_year = date('Y-m', strtotime($month_date));
    $month_name = date('M', strtotime($month_date));
    
    $month_sql = "SELECT COUNT(DISTINCT log_date) as days_present,
                         SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
                  FROM attendance_log 
                  WHERE student_id = ? AND DATE_FORMAT(log_date, '%Y-%m') = ?";
    $month_result = safeQuery($month_sql, [$student_record_id, $month_year], 'is');
    
    if($month_result && mysqli_num_rows($month_result) > 0) {
        $month_data = mysqli_fetch_assoc($month_result);
        $monthly_summary[] = [
            'month' => $month_name,
            'days_present' => (int)$month_data['days_present'],
            'late_count' => (int)$month_data['late_count']
        ];
    } else {
        $monthly_summary[] = [
            'month' => $month_name,
            'days_present' => 0,
            'late_count' => 0
        ];
    }
}

// Get available months for filter
$available_months = [];
$months_sql = "SELECT DISTINCT DATE_FORMAT(log_date, '%Y-%m') as month_year,
               DATE_FORMAT(log_date, '%M %Y') as month_name
               FROM attendance_log 
               WHERE student_id = ?
               ORDER BY log_date DESC";
$months_result = safeQuery($months_sql, [$student_record_id], 'i');

if($months_result && mysqli_num_rows($months_result) > 0) {
    while($row = mysqli_fetch_assoc($months_result)) {
        $available_months[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>My Attendance - Student</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .attendance-container {
            padding: 24px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
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

        .card {
            border-radius: 20px;
            border: 1px solid var(--grey);
            background: var(--white);
            margin-bottom: 24px;
        }

        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--grey);
            font-weight: 600;
            font-size: 15px;
            color: #444;
            background: var(--white);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-header i {
            color: var(--pink);
            width: 24px;
        }

        .card-header span {
            flex: 1;
        }

        .card-body {
            padding: 24px;
        }

        .filter-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 24px;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        .filter-group label {
            display: block;
            font-size: 11px;
            font-weight: 500;
            margin-bottom: 4px;
            color: #888;
        }

        .filter-group select, .filter-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--grey);
            border-radius: 12px;
            font-size: 13px;
            background: var(--white-smoke);
        }

        .filter-group select:focus, .filter-group input:focus {
            outline: none;
            border-color: var(--pink);
        }

        .btn-filter {
            background: var(--pink);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-filter:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-reset {
            background: var(--grey);
            color: #666;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-reset:hover {
            background: var(--grey-dark);
            color: white;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 600px;
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

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
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

        .badge-secondary {
            background: #f5f5f5;
            color: #757575;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .summary-info {
            background: var(--white-smoke);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .summary-item {
            text-align: center;
        }

        .summary-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--pink);
        }

        .summary-label {
            font-size: 11px;
            color: #999;
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .stat-number {
                font-size: 24px;
            }
            
            .filter-bar {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .btn-filter, .btn-reset {
                width: 100%;
            }
            
            .summary-info {
                flex-direction: column;
                text-align: center;
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
    <?php include '../includes/student_navbar.php'; ?>
    <?php include '../includes/student_sidebar.php'; ?>
    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="main-content">
        <div class="attendance-container">
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_days; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-calendar-alt"></i> Total Days
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $present_days; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-check-circle"></i> Total Logs
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $late_count; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-exclamation-triangle"></i> Late
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $punctuality_rate; ?>%</div>
                    <div class="stat-label">
                        <i class="fas fa-chart-line"></i> Punctuality
                    </div>
                </div>
            </div>
            
            <!-- Monthly Trends Chart -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i>
                    <span>Monthly Attendance Trends</span>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Records Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i>
                    <span>Attendance Records</span>
                </div>
                <div class="card-body">
                    <!-- Filter Bar -->
                    <div class="filter-bar">
                        <div class="filter-group">
                            <label><i class="fas fa-calendar"></i> Filter Type</label>
                            <select id="filterType" onchange="toggleFilterType()">
                                <option value="monthly" <?php echo $filter_type == 'monthly' ? 'selected' : ''; ?>>Monthly View</option>
                                <option value="range" <?php echo $filter_type == 'range' ? 'selected' : ''; ?>>Date Range</option>
                            </select>
                        </div>
                        
                        <div id="monthlyFilter" class="filter-group" <?php echo $filter_type == 'range' ? 'style="display:none"' : ''; ?>>
                            <label><i class="fas fa-calendar-month"></i> Select Month</label>
                            <select id="monthSelect">
                                <?php for($m = 1; $m <= 12; $m++): 
                                    $month_value = str_pad($m, 2, '0', STR_PAD_LEFT);
                                    $month_name = date('F', mktime(0, 0, 0, $m, 1));
                                ?>
                                    <option value="<?php echo $month_value; ?>" <?php echo $selected_month == $month_value ? 'selected' : ''; ?>>
                                        <?php echo $month_name; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div id="yearFilter" class="filter-group" <?php echo $filter_type == 'range' ? 'style="display:none"' : ''; ?>>
                            <label><i class="fas fa-calendar-year"></i> Select Year</label>
                            <select id="yearSelect">
                                <?php for($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div id="rangeFilter" class="filter-group" <?php echo $filter_type == 'monthly' ? 'style="display:none"' : ''; ?>>
                            <label><i class="fas fa-calendar-start"></i> Start Date</label>
                            <input type="date" id="startDate" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days')); ?>">
                        </div>
                        
                        <div id="rangeFilterEnd" class="filter-group" <?php echo $filter_type == 'monthly' ? 'style="display:none"' : ''; ?>>
                            <label><i class="fas fa-calendar-end"></i> End Date</label>
                            <input type="date" id="endDate" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button class="btn-filter" onclick="applyFilter()"><i class="fas fa-search"></i> Filter</button>
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button class="btn-reset" onclick="resetFilter()"><i class="fas fa-undo-alt"></i> Reset</button>
                        </div>
                    </div>
                    
                    <!-- Summary Info -->
                    <div class="summary-info">
                        <div class="summary-item">
                            <div class="summary-value"><?php echo count($attendance_records); ?></div>
                            <div class="summary-label">Total Records</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value"><?php echo $total_days; ?></div>
                            <div class="summary-label">Days with Records</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value"><?php echo $attendance_rate; ?>%</div>
                            <div class="summary-label">Attendance Rate</div>
                        </div>
                    </div>
                    
                    <!-- Attendance Table -->
                    <div class="table-wrapper">
                        <?php if(count($attendance_records) > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>Log Type</th>
                                        <th>Time</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($attendance_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($record['log_date'])); ?></td>
                                        <td><?php echo $record['day_name']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strpos($record['log_type'], 'in') !== false ? 'info' : 'warning'; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $record['log_type'])); ?>
                                            </span>
                                        </div>
                                        <td><?php echo date('h:i A', strtotime($record['time_captured'])); ?></div>
                                        <td>
                                            <span class="badge badge-secondary">
                                                <?php echo strtoupper($record['method']); ?>
                                            </span>
                                        </div>
                                        <td>
                                            <span class="badge badge-<?php echo $record['status'] == 'on_time' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </div>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-day"></i>
                                <p>No attendance records found</p>
                                <small>Try selecting a different date range or month</small>
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
        
        // Filter functions
        function toggleFilterType() {
            const filterType = document.getElementById('filterType').value;
            const monthlyFilter = document.getElementById('monthlyFilter');
            const yearFilter = document.getElementById('yearFilter');
            const rangeFilter = document.getElementById('rangeFilter');
            const rangeFilterEnd = document.getElementById('rangeFilterEnd');
            
            if(filterType === 'monthly') {
                monthlyFilter.style.display = 'block';
                yearFilter.style.display = 'block';
                rangeFilter.style.display = 'none';
                rangeFilterEnd.style.display = 'none';
            } else {
                monthlyFilter.style.display = 'none';
                yearFilter.style.display = 'none';
                rangeFilter.style.display = 'block';
                rangeFilterEnd.style.display = 'block';
            }
        }
        
        function applyFilter() {
            const filterType = document.getElementById('filterType').value;
            
            if(filterType === 'monthly') {
                const month = document.getElementById('monthSelect').value;
                const year = document.getElementById('yearSelect').value;
                window.location.href = `?filter=monthly&month=${month}&year=${year}`;
            } else {
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                window.location.href = `?filter=range&start_date=${startDate}&end_date=${endDate}`;
            }
        }
        
        function resetFilter() {
            window.location.href = `?filter=monthly&month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>`;
        }
        
        // Monthly Trends Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        if(trendCtx) {
            new Chart(trendCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($monthly_summary, 'month')); ?>,
                    datasets: [
                        {
                            label: 'Days Present',
                            data: <?php echo json_encode(array_column($monthly_summary, 'days_present')); ?>,
                            backgroundColor: 'rgba(255, 105, 180, 0.7)',
                            borderColor: '#ff69b4',
                            borderWidth: 1,
                            borderRadius: 8
                        },
                        {
                            label: 'Times Late',
                            data: <?php echo json_encode(array_column($monthly_summary, 'late_count')); ?>,
                            backgroundColor: 'rgba(255, 152, 0, 0.7)',
                            borderColor: '#ff9800',
                            borderWidth: 1,
                            borderRadius: 8
                        }
                    ]
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
        }
    </script>
</body>
</html>