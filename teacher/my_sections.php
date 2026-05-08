<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('teacher');

$teacher_sql = "SELECT id FROM teachers WHERE user_id = ?";
$teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$sql = "SELECT s.*, yl.level_name, 
        (SELECT COUNT(*) FROM students WHERE section_id = s.id AND status = 'active') as student_count,
        (SELECT COUNT(*) FROM attendance_log al 
         JOIN students st ON al.student_id = st.id 
         WHERE st.section_id = s.id AND al.log_date = CURDATE() 
         AND al.log_type IN ('morning_in', 'afternoon_in')) as present_today
        FROM sections s
        JOIN year_levels yl ON s.year_level_id = yl.id
        WHERE s.teacher_id = ? AND s.status = 'active'";
$params = [$teacher_id];
$types = "i";

if($search) {
    $sql .= " AND (s.section_name LIKE ? OR yl.level_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

$sql .= " ORDER BY yl.sort_order, s.section_name";
$sections = safeQuery($sql, $params, $types);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>My Sections - Teacher</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <style>
        .sections-container {
            padding: 24px;
        }

        .section-card {
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--grey);
            transition: all 0.2s;
            overflow: hidden;
        }

        .section-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        .section-header {
            padding: 20px;
            border-bottom: 1px solid var(--grey);
            background: var(--white-smoke);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--pink);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-stats {
            display: flex;
            justify-content: space-between;
            padding: 16px 20px;
            background: var(--white);
            border-bottom: 1px solid var(--grey);
        }

        .stat-item {
            text-align: center;
            flex: 1;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--pink);
        }

        .stat-label {
            font-size: 11px;
            color: var(--grey-dark);
            margin-top: 4px;
        }

        .section-actions {
            padding: 16px 20px;
            display: flex;
            gap: 12px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-info {
            background: #e3f2fd;
            color: #2196f3;
        }

        .badge-success {
            background: #e8f5e9;
            color: #4caf50;
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
            .sections-container {
                padding: 16px;
            }
            
            .section-actions {
                flex-direction: column;
            }
            
            .section-stats {
                flex-direction: column;
                gap: 12px;
            }
            
            .stat-item {
                text-align: left;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .stat-label {
                margin-top: 0;
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
        <div class="sections-container">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-layer-group"></i>
                    <span>My Sections</span>
                </div>
                <div class="card-body">
                    <!-- Search Bar -->
                    <div class="search-bar">
                        <div class="filter-group">
                            <div class="form-group" style="margin-bottom: 0;">
                                <input type="text" id="searchInput" class="form-control" placeholder="Search sections..." onkeyup="searchSection()" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sections Grid -->
                    <?php if(mysqli_num_rows($sections) > 0): ?>
                        <div class="grid-dashboard">
                            <?php while($section = mysqli_fetch_assoc($sections)): 
                                $attendance_rate = $section['student_count'] > 0 ? round(($section['present_today'] / $section['student_count']) * 100) : 0;
                            ?>
                                <div class="section-card">
                                    <div class="section-header">
                                        <div class="section-title">
                                            <i class="fas fa-users"></i>
                                            <?php echo htmlspecialchars($section['level_name'] . ' - ' . $section['section_name']); ?>
                                        </div>
                                    </div>
                                    <div class="section-stats">
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo $section['student_count']; ?></div>
                                            <div class="stat-label">Total Students</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo $section['present_today']; ?></div>
                                            <div class="stat-label">Present Today</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                                            <div class="stat-label">Attendance Rate</div>
                                        </div>
                                    </div>
                                    <div class="section-actions">
                                        <button class="btn btn-primary" onclick="viewStudents(<?php echo $section['id']; ?>)">
                                            <i class="fas fa-user-graduate"></i>
                                            <span>View Students</span>
                                        </button>
                                        <button class="btn btn-outline" onclick="viewAttendance(<?php echo $section['id']; ?>)">
                                            <i class="fas fa-calendar-check"></i>
                                            <span>Attendance Log</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <p>No sections assigned yet</p>
                            <small>Contact administrator to assign sections to you</small>
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
        
        function searchSection() {
            let input = document.getElementById('searchInput').value.toLowerCase();
            let cards = document.querySelectorAll('.grid-dashboard .section-card');
            cards.forEach(card => {
                let text = card.innerText.toLowerCase();
                card.style.display = text.includes(input) ? '' : 'none';
            });
        }
        
        function viewStudents(sectionId) {
            window.location.href = `students.php?section=${sectionId}`;
        }
        
        function viewAttendance(sectionId) {
            window.location.href = `attendance_log.php?section=${sectionId}`;
        }
        
        // Trigger search on Enter key
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchSection();
            }
        });
    </script>
</body>
</html>