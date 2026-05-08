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

// Get student record and section info - match by fullname
$student_record = null;
$student_record_id = null;
$section_id = null;
$section_name = null;
$level_name = null;
$adviser_name = null;
$room = null;

// First attempt: Get student by fullname matching logged-in user
$student_sql = "SELECT s.id, s.fullname, s.lrn, sec.id as section_id, sec.section_name, 
                       yl.level_name, u.fullname as adviser_name, s.room
                FROM students s
                LEFT JOIN sections sec ON s.section_id = sec.id
                LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                LEFT JOIN teachers t ON sec.teacher_id = t.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE s.fullname = ?";
$student_result = safeQuery($student_sql, [$student_name], 's');

if($student_result && mysqli_num_rows($student_result) > 0) {
    $student_record = mysqli_fetch_assoc($student_result);
    $student_record_id = $student_record['id'];
    $section_id = $student_record['section_id'];
    $section_name = $student_record['section_name'];
    $level_name = $student_record['level_name'];
    $adviser_name = $student_record['adviser_name'];
    $room = $student_record['room'];
}

// Second attempt: Try by username prefix (e.g., alice.mendoza -> Alice)
if(!$student_record) {
    $name_parts = explode('.', $student_username);
    $first_name = ucfirst($name_parts[0] ?? '');
    
    $student_sql = "SELECT s.id, s.fullname, s.lrn, sec.id as section_id, sec.section_name, 
                           yl.level_name, u.fullname as adviser_name, s.room
                    FROM students s
                    LEFT JOIN sections sec ON s.section_id = sec.id
                    LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                    LEFT JOIN teachers t ON sec.teacher_id = t.id
                    LEFT JOIN users u ON t.user_id = u.id
                    WHERE s.fullname LIKE ?";
    $student_result = safeQuery($student_sql, ["$first_name%"], 's');
    
    if($student_result && mysqli_num_rows($student_result) > 0) {
        $student_record = mysqli_fetch_assoc($student_result);
        $student_record_id = $student_record['id'];
        $section_id = $student_record['section_id'];
        $section_name = $student_record['section_name'];
        $level_name = $student_record['level_name'];
        $adviser_name = $student_record['adviser_name'];
        $room = $student_record['room'];
    }
}

// Third attempt: Try by LRN or get first student
if(!$student_record) {
    $student_sql = "SELECT s.id, s.fullname, s.lrn, sec.id as section_id, sec.section_name, 
                           yl.level_name, u.fullname as adviser_name, s.room
                    FROM students s
                    LEFT JOIN sections sec ON s.section_id = sec.id
                    LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                    LEFT JOIN teachers t ON sec.teacher_id = t.id
                    LEFT JOIN users u ON t.user_id = u.id
                    LIMIT 1";
    $student_result = safeQuery($student_sql, [], '');
    
    if($student_result && mysqli_num_rows($student_result) > 0) {
        $student_record = mysqli_fetch_assoc($student_result);
        $student_record_id = $student_record['id'];
        $section_id = $student_record['section_id'];
        $section_name = $student_record['section_name'];
        $level_name = $student_record['level_name'];
        $adviser_name = $student_record['adviser_name'];
        $room = $student_record['room'];
    }
}

// Debug log
error_log("Student Schedule - Student Record ID: " . $student_record_id . " for user: " . $student_username);

// If no student record found
if(!$student_record) {
    $student_record = [
        'id' => 0,
        'fullname' => $student_name,
        'lrn' => 'Not set',
        'section_id' => null,
        'section_name' => 'Not assigned',
        'level_name' => '',
        'adviser_name' => 'Not assigned',
        'room' => ''
    ];
}

// Default schedule data structure with Filipino subject names
// This is a sample schedule - in production, this would come from a database table
$default_schedule = [
    'Monday' => [
        ['time' => '07:00 - 08:00', 'subject' => 'Mathematics', 'teacher' => 'Mr. Juan Reyes', 'room' => 'RM 101'],
        ['time' => '08:00 - 09:00', 'subject' => 'Science', 'teacher' => 'Ms. Maria Cruz', 'room' => 'Lab 1'],
        ['time' => '09:00 - 10:00', 'subject' => 'English', 'teacher' => 'Ms. Ana Gonzales', 'room' => 'RM 102'],
        ['time' => '10:00 - 10:30', 'subject' => 'Recess', 'teacher' => '-', 'room' => 'Canteen', 'is_break' => true],
        ['time' => '10:30 - 11:30', 'subject' => 'Filipino', 'teacher' => 'Mr. Ramon Santos', 'room' => 'RM 103'],
        ['time' => '11:30 - 13:00', 'subject' => 'Lunch Break', 'teacher' => '-', 'room' => 'Canteen', 'is_break' => true],
        ['time' => '13:00 - 14:00', 'subject' => 'Araling Panlipunan', 'teacher' => 'Ms. Rose Rivera', 'room' => 'RM 104'],
        ['time' => '14:00 - 15:00', 'subject' => 'MAPEH', 'teacher' => 'Mr. Michael Ramos', 'room' => 'Gym'],
        ['time' => '15:00 - 16:00', 'subject' => 'Values Education', 'teacher' => 'Ms. Catherine Dela Cruz', 'room' => 'RM 105']
    ],
    'Tuesday' => [
        ['time' => '07:00 - 08:00', 'subject' => 'Mathematics', 'teacher' => 'Mr. Juan Reyes', 'room' => 'RM 101'],
        ['time' => '08:00 - 09:00', 'subject' => 'Science', 'teacher' => 'Ms. Maria Cruz', 'room' => 'Lab 1'],
        ['time' => '09:00 - 10:00', 'subject' => 'TLE/ICT', 'teacher' => 'Mr. Mark Fernandez', 'room' => 'Computer Lab'],
        ['time' => '10:00 - 10:30', 'subject' => 'Recess', 'teacher' => '-', 'room' => 'Canteen', 'is_break' => true],
        ['time' => '10:30 - 11:30', 'subject' => 'Filipino', 'teacher' => 'Mr. Ramon Santos', 'room' => 'RM 103'],
        ['time' => '11:30 - 13:00', 'subject' => 'Lunch Break', 'teacher' => '-', 'room' => 'Canteen', 'is_break' => true],
        ['time' => '13:00 - 14:00', 'subject' => 'Araling Panlipunan', 'teacher' => 'Ms. Rose Rivera', 'room' => 'RM 104'],
        ['time' => '14:00 - 15:00', 'subject' => 'ESP', 'teacher' => 'Ms. Jennifer Dizon', 'room' => 'RM 105'],
        ['time' => '15:00 - 16:00', 'subject' => 'Homeroom Guidance', 'teacher' => $adviser_name ?: 'Adviser', 'room' => $room ?: 'RM 106']
    ],
    'Wednesday' => [
        ['time' => '07:00 - 08:00', 'subject' => 'Mathematics', 'teacher' => 'Mr. Juan Reyes', 'room' => 'RM 101'],
        ['time' => '08:00 - 09:00', 'subject' => 'Science', 'teacher' => 'Ms. Maria Cruz', 'room' => 'Lab 1'],
        ['time' => '09:00 - 10:00', 'subject' => 'English', 'teacher' => 'Ms. Ana Gonzales', 'room' => 'RM 102'],
        ['time' => '10:00 - 10:30', 'subject' => 'Recess', 'teacher' => '-', 'room' => 'Canteen', 'is_break' => true],
        ['time' => '10:30 - 11:30', 'subject' => 'Filipino', 'teacher' => 'Mr. Ramon Santos', 'room' => 'RM 103'],
        ['time' => '11:30 - 13:00', 'subject' => 'Lunch Break', 'teacher' => '-', 'room' => 'Canteen', 'is_break' => true],
        ['time' => '13:00 - 14:00', 'subject' => 'Araling Panlipunan', 'teacher' => 'Ms. Rose Rivera', 'room' => 'RM 104'],
        ['time' => '14:00 - 15:00', 'subject' => 'MAPEH', 'teacher' => 'Mr. Michael Ramos', 'room' => 'Gym'],
        ['time' => '15:00 - 16:00', 'subject' => 'Values Education', 'teacher' => 'Ms. Catherine Dela Cruz', 'room' => 'RM 105']
    ],
    'Thursday' => [
        ['time' => '07:00 - 08:00', 'subject' => 'Mathematics', 'teacher' => 'Mr. Juan Reyes', 'room' => 'RM 101'],
        ['time' => '08:00 - 09:00', 'subject' => 'Science', 'teacher' => 'Ms. Maria Cruz', 'room' => 'Lab 1'],
        ['time' => '09:00 - 10:00', 'subject' => 'TLE/ICT', 'teacher' => 'Mr. Mark Fernandez', 'room' => 'Computer Lab'],
        ['time' => '10:00 - 10:30', 'subject' => 'Recess', 'teacher' => '-', 'room' => 'Canteen', 'is_break' => true],
        ['time' => '10:30 - 11:30', 'subject' => 'Filipino', 'teacher' => 'Mr. Ramon Santos', 'room' => 'RM 103'],
        ['time' => '11:30 - 13:00', 'subject' => 'Lunch Break', 'teacher' => '-', 'room' => 'Canteen', 'is_break' => true],
        ['time' => '13:00 - 14:00', 'subject' => 'Araling Panlipunan', 'teacher' => 'Ms. Rose Rivera', 'room' => 'RM 104'],
        ['time' => '14:00 - 15:00', 'subject' => 'ESP', 'teacher' => 'Ms. Jennifer Dizon', 'room' => 'RM 105'],
        ['time' => '15:00 - 16:00', 'subject' => 'Homeroom Guidance', 'teacher' => $adviser_name ?: 'Adviser', 'room' => $room ?: 'RM 106']
    ],
    'Friday' => [
        ['time' => '07:00 - 08:00', 'subject' => 'Mathematics', 'teacher' => 'Mr. Juan Reyes', 'room' => 'RM 101'],
        ['time' => '08:00 - 09:00', 'subject' => 'Science', 'teacher' => 'Ms. Maria Cruz', 'room' => 'Lab 1'],
        ['time' => '09:00 - 10:00', 'subject' => 'English', 'teacher' => 'Ms. Ana Gonzales', 'room' => 'RM 102'],
        ['time' => '10:00 - 10:30', 'subject' => 'Recess', 'teacher' => '-', 'room' => 'Canteen', 'is_break' => true],
        ['time' => '10:30 - 11:30', 'subject' => 'Filipino', 'teacher' => 'Mr. Ramon Santos', 'room' => 'RM 103'],
        ['time' => '11:30 - 13:00', 'subject' => 'Lunch Break', 'teacher' => '-', 'room' => 'Canteen', 'is_break' => true],
        ['time' => '13:00 - 14:00', 'subject' => 'Araling Panlipunan', 'teacher' => 'Ms. Rose Rivera', 'room' => 'RM 104'],
        ['time' => '14:00 - 15:00', 'subject' => 'MAPEH', 'teacher' => 'Mr. Michael Ramos', 'room' => 'Gym'],
        ['time' => '15:00 - 16:00', 'subject' => 'Values Education', 'teacher' => 'Ms. Catherine Dela Cruz', 'room' => 'RM 105']
    ],
    'Saturday' => [
        ['time' => '07:00 - 12:00', 'subject' => 'Weekend / No Classes', 'teacher' => '-', 'room' => '-', 'is_break' => true],
    ],
    'Sunday' => [
        ['time' => 'All Day', 'subject' => 'Weekend / No Classes', 'teacher' => '-', 'room' => '-', 'is_break' => true],
    ]
];

// Get current day to highlight
$current_day = date('l');
$current_time = date('H:i');
$current_period = null;

// Find current period
if(isset($default_schedule[$current_day])) {
    foreach($default_schedule[$current_day] as $index => $period) {
        if(!isset($period['is_break']) || !$period['is_break']) {
            $time_range = explode(' - ', $period['time']);
            $start_time = $time_range[0];
            $end_time = $time_range[1] ?? '23:59';
            
            if($current_time >= $start_time && $current_time <= $end_time) {
                $current_period = $index;
                break;
            }
        }
    }
}

// Get week days for tabs
$week_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$active_day = isset($_GET['day']) ? $_GET['day'] : $current_day;

// If selected day is not in week days, default to Monday
if(!in_array($active_day, $week_days)) {
    $active_day = 'Monday';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>My Schedule - Student</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <style>
        .schedule-container {
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header Cards */
        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .info-card {
            background: var(--white);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--grey);
            transition: all 0.2s;
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        .info-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .info-card-header i {
            font-size: 24px;
            color: var(--pink);
        }

        .info-card-header h3 {
            font-size: 14px;
            font-weight: 500;
            color: #666;
        }

        .info-card-value {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            padding-left: 36px;
        }

        .info-card-value small {
            font-size: 12px;
            font-weight: normal;
            color: #999;
        }

        /* Current Time Card */
        .current-time-card {
            background: linear-gradient(135deg, var(--pink) 0%, var(--pink-dark) 100%);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .current-date {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .current-date i {
            font-size: 32px;
            opacity: 0.9;
        }

        .date-info h4 {
            font-size: 14px;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 4px;
        }

        .date-info .date {
            font-size: 20px;
            font-weight: 600;
        }

        .current-class {
            text-align: right;
        }

        .current-class-label {
            font-size: 12px;
            opacity: 0.8;
            margin-bottom: 4px;
        }

        .current-class-name {
            font-size: 18px;
            font-weight: 600;
        }

        /* Day Tabs */
        .day-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            overflow-x: auto;
            padding-bottom: 8px;
            scrollbar-width: thin;
        }

        .day-tabs::-webkit-scrollbar {
            height: 4px;
        }

        .day-tabs::-webkit-scrollbar-track {
            background: var(--grey-light);
            border-radius: 4px;
        }

        .day-tabs::-webkit-scrollbar-thumb {
            background: var(--pink);
            border-radius: 4px;
        }

        .day-tab {
            padding: 10px 20px;
            background: var(--white-smoke);
            border: 1px solid var(--grey);
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            font-size: 13px;
            font-weight: 500;
            color: #666;
        }

        .day-tab.active {
            background: var(--pink);
            border-color: var(--pink);
            color: white;
        }

        .day-tab.active .day-badge {
            background: rgba(255,255,255,0.3);
        }

        .day-tab:hover:not(.active) {
            background: var(--pink-light);
            border-color: var(--pink);
            color: var(--pink-dark);
        }

        .day-badge {
            display: inline-block;
            background: var(--grey);
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 10px;
            margin-left: 8px;
        }

        /* Schedule Table */
        .schedule-card {
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--grey);
            overflow: hidden;
        }

        .schedule-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--grey);
            background: var(--white);
        }

        .schedule-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .schedule-header h3 i {
            color: var(--pink);
        }

        .schedule-header h3 span {
            color: var(--pink);
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
            padding: 14px 12px;
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

        tr:hover {
            background: var(--white-smoke);
        }

        .break-row {
            background: #fff8e1;
        }

        .break-row td {
            color: #ff9800;
        }

        .break-row:hover {
            background: #fff3e0;
        }

        .current-period {
            background: #e8f5e9;
            border-left: 3px solid #4caf50;
        }

        .current-period td:first-child {
            border-left: 3px solid #4caf50;
        }

        .subject-name {
            font-weight: 600;
            color: #333;
        }

        .teacher-name {
            font-size: 12px;
            color: #888;
        }

        .room-badge {
            display: inline-block;
            background: var(--pink-light);
            color: var(--pink-dark);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .time-badge {
            font-family: monospace;
            font-size: 12px;
            color: #666;
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
            .schedule-container {
                padding: 16px;
            }
            
            .info-cards {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .current-time-card {
                flex-direction: column;
                text-align: center;
            }
            
            .current-class {
                text-align: center;
            }
            
            .day-tabs {
                gap: 6px;
            }
            
            .day-tab {
                padding: 8px 14px;
                font-size: 12px;
            }
            
            .day-badge {
                display: none;
            }
            
            .schedule-header {
                padding: 14px 18px;
            }
            
            th, td {
                padding: 10px 8px;
            }
        }

        @media print {
            .sidebar, .navbar, .day-tabs, .info-cards, .current-time-card {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .schedule-container {
                padding: 0 !important;
            }
            
            .schedule-card {
                border: 1px solid #ddd !important;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/student_navbar.php'; ?>
    <?php include '../includes/student_sidebar.php'; ?>
    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="main-content">
        <div class="schedule-container">
            <!-- Information Cards -->
            <div class="info-cards">
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-layer-group"></i>
                        <h3>Grade Level & Section</h3>
                    </div>
                    <div class="info-card-value">
                        <?php echo htmlspecialchars($level_name ?: 'Not assigned'); ?> - 
                        <?php echo htmlspecialchars($section_name ?: 'Not assigned'); ?>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <h3>Class Adviser</h3>
                    </div>
                    <div class="info-card-value">
                        <?php echo htmlspecialchars($adviser_name ?: 'Not assigned'); ?>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-door-open"></i>
                        <h3>Homeroom</h3>
                    </div>
                    <div class="info-card-value">
                        <?php echo htmlspecialchars($room ?: 'RM 101'); ?>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>School Year</h3>
                    </div>
                    <div class="info-card-value">
                        <?php echo date('Y') . ' - ' . (date('Y') + 1); ?>
                        <small>SY</small>
                    </div>
                </div>
            </div>
            
            <!-- Current Time and Class -->
            <div class="current-time-card">
                <div class="current-date">
                    <i class="fas fa-calendar-day"></i>
                    <div class="date-info">
                        <h4><?php echo $current_day; ?></h4>
                        <div class="date"><?php echo date('F d, Y'); ?></div>
                    </div>
                </div>
                <div class="current-class">
                    <div class="current-class-label">
                        <i class="fas fa-clock"></i> Current Time: <?php echo date('h:i A'); ?>
                    </div>
                    <div class="current-class-name">
                        <?php 
                        if($current_period !== null && isset($default_schedule[$current_day][$current_period])) {
                            $current_subject = $default_schedule[$current_day][$current_period];
                            if(isset($current_subject['is_break']) && $current_subject['is_break']) {
                                echo '<i class="fas fa-coffee"></i> ' . $current_subject['subject'];
                            } else {
                                echo '<i class="fas fa-book"></i> ' . $current_subject['subject'];
                            }
                        } else {
                            echo '<i class="fas fa-home"></i> No ongoing class';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Day Tabs -->
            <div class="day-tabs">
                <?php foreach($week_days as $day): ?>
                    <div class="day-tab <?php echo $active_day == $day ? 'active' : ''; ?>" 
                         onclick="window.location.href='?day=<?php echo $day; ?>'">
                        <?php echo substr($day, 0, 3); ?>
                        <?php if($day == $current_day): ?>
                            <span class="day-badge">Today</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Schedule Table -->
            <div class="schedule-card">
                <div class="schedule-header">
                    <h3>
                        <i class="fas fa-calendar-week"></i>
                        Schedule for <span><?php echo $active_day; ?></span>
                    </h3>
                </div>
                <div class="table-wrapper">
                    <?php if(isset($default_schedule[$active_day]) && count($default_schedule[$active_day]) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Room</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($default_schedule[$active_day] as $index => $period): 
                                    $is_break = isset($period['is_break']) && $period['is_break'];
                                    $is_current = ($active_day == $current_day && $index === $current_period);
                                    $row_class = '';
                                    if($is_break) $row_class = 'break-row';
                                    if($is_current) $row_class = 'current-period';
                                ?>
                                    <tr class="<?php echo $row_class; ?>">
                                        <td class="time-badge">
                                            <i class="far fa-clock"></i> <?php echo $period['time']; ?>
                                        </td>
                                        <td>
                                            <div class="subject-name">
                                                <?php if($is_break): ?>
                                                    <i class="fas fa-utensils"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-book-open"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($period['subject']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="teacher-name">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($period['teacher']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="room-badge">
                                                <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($period['room']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>No schedule available for <?php echo $active_day; ?></p>
                            <small>Please check back later or contact your adviser</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Notes Section -->
            <div style="margin-top: 20px; padding: 16px; background: var(--white-smoke); border-radius: 12px;">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="room-badge" style="background: #e8f5e9; color: #4caf50;">
                            <i class="fas fa-leaf"></i> Current Class
                        </span>
                        <span>Currently ongoing class</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="room-badge" style="background: #fff3e0; color: #ff9800;">
                            <i class="fas fa-coffee"></i> Break Time
                        </span>
                        <span>Recess or lunch break</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-info-circle" style="color: var(--pink);"></i>
                        <span style="font-size: 12px; color: #666;">
                            Schedule is subject to change. Please check with your adviser for updates.
                        </span>
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
        
        // Auto-refresh current time display every minute
        function updateCurrentTime() {
            const timeElement = document.querySelector('.current-class-label');
            if(timeElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: true 
                });
                timeElement.innerHTML = `<i class="fas fa-clock"></i> Current Time: ${timeString}`;
            }
        }
        
        setInterval(updateCurrentTime, 60000);
    </script>
</body>
</html>