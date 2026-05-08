<?php
// This file is for teacher only
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('teacher');

// Get teacher ID
$teacher_sql = "SELECT id FROM teachers WHERE user_id = ?";
$teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];

// Get teacher's section IDs for filtering
$section_ids = [];
$sections_result = safeQuery("SELECT id FROM sections WHERE teacher_id = ? AND status = 'active'", [$teacher_id], 'i');
while ($sec = mysqli_fetch_assoc($sections_result)) {
    $section_ids[] = $sec['id'];
}

// Get statistics - only for teacher's students
$time_in_count = 0;
$time_out_count = 0;
$students_today = 0;
$total_enrolled = 0;

if (!empty($section_ids)) {
    $section_placeholders = implode(',', array_fill(0, count($section_ids), '?'));

    // Time In count (morning_in and afternoon_in)
    $time_in_sql = "SELECT COUNT(*) as count FROM attendance_log al 
                    JOIN students s ON al.student_id = s.id 
                    WHERE al.log_date = CURDATE() 
                    AND al.log_type IN ('morning_in', 'afternoon_in')
                    AND s.section_id IN ($section_placeholders)";
    $time_in_count = mysqli_fetch_assoc(safeQuery($time_in_sql, $section_ids, str_repeat('i', count($section_ids))))['count'];

    // Time Out count (morning_out and afternoon_out)
    $time_out_sql = "SELECT COUNT(*) as count FROM attendance_log al 
                     JOIN students s ON al.student_id = s.id 
                     WHERE al.log_date = CURDATE() 
                     AND al.log_type IN ('morning_out', 'afternoon_out')
                     AND s.section_id IN ($section_placeholders)";
    $time_out_count = mysqli_fetch_assoc(safeQuery($time_out_sql, $section_ids, str_repeat('i', count($section_ids))))['count'];

    // Students today (distinct)
    $students_today_sql = "SELECT COUNT(DISTINCT al.student_id) as count FROM attendance_log al 
                           JOIN students s ON al.student_id = s.id 
                           WHERE al.log_date = CURDATE() 
                           AND s.section_id IN ($section_placeholders)";
    $students_today = mysqli_fetch_assoc(safeQuery($students_today_sql, $section_ids, str_repeat('i', count($section_ids))))['count'];

    // Total enrolled students for this teacher
    $total_enrolled_sql = "SELECT COUNT(*) as count FROM students 
                           WHERE section_id IN ($section_placeholders) AND status = 'active'";
    $total_enrolled = mysqli_fetch_assoc(safeQuery($total_enrolled_sql, $section_ids, str_repeat('i', count($section_ids))))['count'];
}

// Get recent activity - only for teacher's students
$recent_sql = "SELECT al.*, s.fullname, s.photo, sec.section_name 
               FROM attendance_log al
               JOIN students s ON al.student_id = s.id
               LEFT JOIN sections sec ON s.section_id = sec.id
               WHERE sec.teacher_id = ?
               ORDER BY al.id DESC, al.time_captured DESC LIMIT 20";
$recent_logs = safeQuery($recent_sql, [$teacher_id], 'i');

// Get teacher's students for current status
$teacher_students_sql = "SELECT s.id, s.fullname, s.photo, sec.section_name 
                         FROM students s
                         LEFT JOIN sections sec ON s.section_id = sec.id
                         WHERE sec.teacher_id = ? AND s.status = 'active'
                         ORDER BY s.fullname";
$teacher_students = safeQuery($teacher_students_sql, [$teacher_id], 'i');

// Store teacher's student IDs for filtering
$teacher_student_ids = [];
while ($stu = mysqli_fetch_assoc($teacher_students)) {
    $teacher_student_ids[] = $stu['id'];
}
// Reset pointer
mysqli_data_seek($teacher_students, 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Display - Teacher</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <meta http-equiv="refresh" content="15">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --pink: #ff69b4;
            --pink-light: #ffb6c1;
            --pink-dark: #ff1493;
            --grey-light: #f5f5f5;
            --grey: #e0e0e0;
            --grey-dark: #9e9e9e;
            --white: #ffffff;
            --white-smoke: #fafafa;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            --radius: 12px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--grey-light);
            color: #333;
            line-height: 1.5;
            font-size: 14px;
        }

        /* Monitor Container */
        .monitor-container {
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Back Link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            color: var(--pink);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .back-link:hover {
            transform: translateX(-4px);
            color: var(--pink-dark);
        }

        /* Statistics Cards - White Background */
        .monitor-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .monitor-stat {
            background: var(--white);
            color: #333;
            padding: 20px 16px;
            border-radius: 16px;
            text-align: center;
            transition: all 0.2s;
            border: 1px solid var(--grey);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }

        .monitor-stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        .monitor-stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--pink);
            margin-bottom: 8px;
        }

        .monitor-stat-label {
            font-size: 12px;
            color: var(--grey-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        /* Grid Layout */
        .grid-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--grey);
        }

        .card-header {
            background: var(--white);
            border-bottom: 1px solid var(--grey);
            padding: 16px 20px;
            font-weight: 600;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #444;
        }

        .card-header i {
            color: var(--pink);
            width: 20px;
        }

        .card-body {
            padding: 20px;
        }

        /* Student Avatar */
        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            object-fit: cover;
            overflow: hidden;
        }

        .student-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .student-avatar.default {
            background: var(--pink-light);
            color: var(--pink);
        }

        /* Activity List */
        .live-activity {
            max-height: 450px;
            overflow-y: auto;
        }

        .live-activity::-webkit-scrollbar {
            width: 4px;
        }

        .live-activity::-webkit-scrollbar-track {
            background: var(--grey-light);
        }

        .live-activity::-webkit-scrollbar-thumb {
            background: var(--pink);
            border-radius: 4px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-bottom: 1px solid var(--grey);
            transition: background 0.2s;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-info {
            flex: 1;
        }

        .activity-name {
            font-weight: 500;
            font-size: 14px;
            color: #333;
        }

        .activity-section {
            font-size: 11px;
            color: #999;
            margin-top: 2px;
        }

        .activity-time {
            text-align: right;
        }

        .activity-type {
            font-size: 12px;
            font-weight: 500;
        }

        .time-text {
            font-size: 11px;
            color: #999;
            margin-top: 2px;
        }

        /* Badges */
        .badge {
            display: inline-block;
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

        /* Student Detail Card */
        .student-detail-card {
            background: var(--white-smoke);
            border-radius: 16px;
            padding: 20px;
            margin-top: 0;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .student-detail-card h4 {
            color: var(--pink);
            margin-bottom: 16px;
            font-size: 18px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .detail-label {
            width: 100px;
            font-weight: 500;
            color: #666;
        }

        .detail-value {
            flex: 1;
            color: #333;
        }

        .empty-details {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-details i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Table */
        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 700px;
        }

        th,
        td {
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

        /* Navbar - Full width, not sticky */
        .navbar {
            background: var(--white);
            padding: 12px 24px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--grey);
            width: 100%;
            position: relative;
        }

        .nav-title {
            font-size: 16px;
            font-weight: 500;
            color: #333;
        }

        .nav-title i {
            color: var(--pink);
            margin-right: 8px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .datetime {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: var(--grey-light);
            border-radius: 40px;
            font-size: 13px;
            color: #666;
            font-family: 'Courier New', monospace;
        }

        .datetime i {
            color: var(--pink);
        }

        .nav-logout {
            background: none;
            border: 1px solid var(--grey);
            padding: 6px 16px;
            border-radius: 40px;
            font-size: 13px;
            cursor: pointer;
            color: #666;
            text-decoration: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav-logout:hover {
            background: var(--grey-light);
            border-color: var(--pink);
            color: var(--pink);
        }

        /* Info Note */
        .info-note {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #1565c0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .monitor-container {
                padding: 16px;
            }

            .monitor-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .monitor-stat-number {
                font-size: 28px;
            }

            .grid-dashboard {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .detail-row {
                flex-direction: column;
            }

            .detail-label {
                width: 100%;
                margin-bottom: 4px;
            }

            .datetime span {
                display: none;
            }

            .nav-logout span {
                display: none;
            }

            .navbar {
                padding: 10px 16px;
            }
        }
    </style>
</head>

<body>
    <!-- Custom Navbar - Full width, not sticky -->
    <nav class="navbar">
        <div class="nav-title">
            <i class="fas fa-tv"></i> Monitor Display
        </div>
        <div class="nav-right">
            <div class="datetime">
                <i class="fas fa-clock"></i>
                <span id="currentTime"></span>
            </div>
            <a href="../logout.php" class="nav-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <div class="monitor-container">
        <!-- Back Link -->
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <!-- Info Note -->
        <div class="info-note">
            <i class="fas fa-info-circle"></i> Showing data only for students assigned to your sections.
        </div>

        <!-- Statistics Row -->
        <div class="monitor-stats">
            <div class="monitor-stat">
                <div class="monitor-stat-number">
                    <?php echo $time_in_count; ?>
                </div>
                <div class="monitor-stat-label">
                    <i class="fas fa-sign-in-alt"></i> Time In
                </div>
            </div>
            <div class="monitor-stat">
                <div class="monitor-stat-number">
                    <?php echo $time_out_count; ?>
                </div>
                <div class="monitor-stat-label">
                    <i class="fas fa-sign-out-alt"></i> Time Out
                </div>
            </div>
            <div class="monitor-stat">
                <div class="monitor-stat-number">
                    <?php echo $students_today; ?>
                </div>
                <div class="monitor-stat-label">
                    <i class="fas fa-user-check"></i> Students Today
                </div>
            </div>
            <div class="monitor-stat">
                <div class="monitor-stat-number">
                    <?php echo $total_enrolled; ?>
                </div>
                <div class="monitor-stat-label">
                    <i class="fas fa-users"></i> Total Enrolled
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid-dashboard">
            <!-- Left Column - Student Details (Auto-populates from latest attendance) -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-graduate"></i>
                    <span>Latest Student Information</span>
                </div>
                <div class="card-body" id="studentDetailsContainer">
                    <div id="studentDetails">
                        <div class="empty-details">
                            <i class="fas fa-user-circle"></i>
                            <p>Waiting for attendance entry...</p>
                            <small>Student information will appear here automatically</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Live Activity -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-clock"></i>
                    <span>Live Recent Activity</span>
                    <span style="margin-left: auto; font-size: 11px; color: #999;">
                        <i class="fas fa-sync-alt"></i> Auto-refresh: 15s
                    </span>
                </div>
                <div class="card-body live-activity" id="recentActivity">
                    <?php if (mysqli_num_rows($recent_logs) > 0): ?>
                        <?php while ($log = mysqli_fetch_assoc($recent_logs)): ?>
                            <div class="activity-item">
                                <div>
                                    <?php if ($log['photo'] && $log['photo'] != 'default_student.png' && file_exists('../uploads/students/' . $log['photo'])): ?>
                                        <div class="student-avatar">
                                            <img src="../uploads/students/<?php echo $log['photo']; ?>"
                                                alt="<?php echo htmlspecialchars($log['fullname']); ?>">
                                        </div>
                                    <?php else: ?>
                                        <div class="student-avatar default">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-info">
                                    <div class="activity-name">
                                        <?php echo htmlspecialchars($log['fullname']); ?>
                                    </div>
                                    <div class="activity-section">
                                        <?php echo htmlspecialchars($log['section_name']); ?>
                                    </div>
                                </div>
                                <div class="activity-time">
                                    <div class="activity-type">
                                        <span
                                            class="badge badge-<?php echo strpos($log['log_type'], 'in') !== false ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $log['log_type'])); ?>
                                        </span>
                                    </div>
                                    <div class="time-text">
                                        <?php echo date('h:i A', strtotime($log['time_captured'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center" style="padding: 40px; color: #999;">
                            <i class="fas fa-clock"
                                style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                            No recent activity
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Current Status Table -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-table"></i>
                <span>Current Status</span>
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table id="statusTable">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Student Name</th>
                                <th>Section</th>
                                <th>Morning In</th>
                                <th>Morning Out</th>
                                <th>Afternoon In</th>
                                <th>Afternoon Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="statusTableBody">
                            <?php if (mysqli_num_rows($teacher_students) > 0): ?>
                                <?php while ($student = mysqli_fetch_assoc($teacher_students)): ?>
                                    <tr data-student-id="<?php echo $student['id']; ?>">
                                        <td>
                                            <?php if ($student['photo'] && $student['photo'] != 'default_student.png' && file_exists('../uploads/students/' . $student['photo'])): ?>
                                                <div class="student-avatar">
                                                    <img src="../uploads/students/<?php echo $student['photo']; ?>"
                                                        alt="<?php echo htmlspecialchars($student['fullname']); ?>">
                                                </div>
                                            <?php else: ?>
                                                <div class="student-avatar default">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                        </div>
                        <td>
                            <?php echo htmlspecialchars($student['fullname']); ?>
                    </div>
                    <td>
                        <?php echo htmlspecialchars($student['section_name']); ?>
                </div>
                <td class="morning-in">-
            </div>
            <td class="morning-out">-</div>
            <td class="afternoon-in">-</div>
            <td class="afternoon-out">-</div>
            <td class="status"><span class="badge badge-danger">Absent</span></div>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" class="text-center" style="padding: 40px; color: #999;">
                    <i class="fas fa-users" style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                    No students assigned
                    </div>
            </tr>
        <?php endif; ?>
        </tbody>
        </table>
        </div>
        </div>
        </div>
        </div>

        <script>
            let teacherStudentIds = <?php echo json_encode($teacher_student_ids); ?>;

            // Update time in 24-hour format
            function updateTime() {
                const now = new Date();
                const hours = now.getHours().toString().padStart(2, '0');
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');
                const timeString = `${hours}:${minutes}:${seconds}`;
                const timeElement = document.getElementById('currentTime');
                if (timeElement) {
                    timeElement.textContent = timeString;
                }
            }

            updateTime();
            setInterval(updateTime, 1000);

            // Escape HTML function
            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Load the most recent student's details (auto-populate)
            function loadLatestStudentDetails() {
                $.ajax({
                    url: '../api/get_latest_teacher_student.php',
                    method: 'GET',
                    data: { teacher_id: <?php echo $teacher_id; ?> },
                    dataType: 'json',
                    success: function (data) {
                        if (data.success && data.student) {
                            displayStudentDetails(data.student);
                        } else {
                            document.getElementById('studentDetails').innerHTML = `
                            <div class="empty-details">
                                <i class="fas fa-user-circle"></i>
                                <p>Waiting for attendance entry...</p>
                                <small>Student information will appear here automatically</small>
                            </div>
                        `;
                        }
                    },
                    error: function () {
                        console.log('Failed to load latest student');
                    }
            });
        }

            function displayStudentDetails(student) {
                let photoHtml = '';
                if (student.photo && student.photo !== 'default_student.png') {
                    photoHtml = `<div style="text-align: center; margin-bottom: 16px;">
                    <img src="../uploads/students/${student.photo}" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
                </div>`;
                } else {
                    photoHtml = `<div style="text-align: center; margin-bottom: 16px;">
                    <div style="width: 80px; height: 80px; background: var(--pink-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="fas fa-user" style="font-size: 40px; color: var(--pink);"></i>
                    </div>
                </div>`;
                }

                let html = `
                <div class="student-detail-card">
                    ${photoHtml}
                    <h4 style="text-align: center;">${escapeHtml(student.fullname)}</h4>
                    <div class="detail-row">
                        <div class="detail-label">LRN:</div>
                        <div class="detail-value">${escapeHtml(student.lrn)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Section:</div>
                        <div class="detail-value">${escapeHtml(student.section_name) || 'Not assigned'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Gender:</div>
                        <div class="detail-value">${escapeHtml(student.gender) || 'Not specified'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Parent:</div>
                        <div class="detail-value">${escapeHtml(student.parent_name) || 'Not provided'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Contact:</div>
                        <div class="detail-value">${escapeHtml(student.parent_contact) || 'Not provided'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value">
                            <span class="badge badge-${student.status === 'active' ? 'success' : 'danger'}">
                                ${student.status === 'active' ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                    </div>
                </div>
            `;
                document.getElementById('studentDetails').innerHTML = html;
            }

            // Load current status for teacher's students only
            function loadCurrentStatus() {
                if (teacherStudentIds.length === 0) return;

                $.ajax({
                    url: '../api/get_teacher_current_status.php',
                    method: 'POST',
                    data: { student_ids: teacherStudentIds },
                    dataType: 'json',
                    success: function (data) {
                        if (data.length > 0) {
                            data.forEach(student => {
                                let row = document.querySelector(`tr[data-student-id="${student.id}"]`);
                                if (row) {
                                    row.querySelector('.morning-in').textContent = student.morning_in || '-';
                                    row.querySelector('.morning-out').textContent = student.morning_out || '-';
                                    row.querySelector('.afternoon-in').textContent = student.afternoon_in || '-';
                                    row.querySelector('.afternoon-out').textContent = student.afternoon_out || '-';

                                    let statusSpan = row.querySelector('.status span');
                                    if (student.present) {
                                        statusSpan.textContent = 'Present';
                                        statusSpan.className = 'badge badge-success';
                                    } else {
                                        statusSpan.textContent = 'Absent';
                                        statusSpan.className = 'badge badge-danger';
                                    }
                                }
                            });
                        }
                    }
                });
            }

            function loadRecentActivity() {
                $.ajax({
                    url: '../api/get_teacher_recent_activity.php',
                    method: 'GET',
                    data: { teacher_id: <?php echo $teacher_id; ?> },
                    success: function (data) {
                        if (data) {
                            document.getElementById('recentActivity').innerHTML = data;
                            // After updating activity, load the latest student details
                            loadLatestStudentDetails();
                        }
                    }
            });
        }

            // Initial load
            loadCurrentStatus();
            loadLatestStudentDetails();

            // Auto-refresh every 15 seconds
            setInterval(loadCurrentStatus, 15000);
            setInterval(loadRecentActivity, 15000);
        </script>
</body>

</html>