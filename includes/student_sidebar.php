<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo-area">
            <i class="fas fa-school"></i>
            <div class="logo-text">
                <span class="school-name">School</span>
                <span class="system-name">Attendance Management System</span>
            </div>
        </div>
    </div>
    
    <?php
    // Get student name from session
    $student_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Student');
    ?>
    <div class="user-info">
        <div class="user-name"><?php echo htmlspecialchars($student_name); ?></div>
        <div class="user-role">Student</div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="<?php echo SITE_URL; ?>student/dashboard.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?php echo SITE_URL; ?>student/profile.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i>
            <span>My Profile</span>
        </a>
        <a href="<?php echo SITE_URL; ?>student/attendance.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>My Attendance</span>
        </a>
        <a href="<?php echo SITE_URL; ?>student/qr_code.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'qr_code.php' ? 'active' : ''; ?>">
            <i class="fas fa-qrcode"></i>
            <span>My QR Code</span>
        </a>
        <a href="<?php echo SITE_URL; ?>student/schedule.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>My Schedule</span>
        </a>
        <a href="<?php echo SITE_URL; ?>student/change_password.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'change_password.php' ? 'active' : ''; ?>">
            <i class="fas fa-key"></i>
            <span>Change Password</span>
        </a>
    </nav>
</div>

<style>
:root {
    --sidebar-width: 260px;
}

.sidebar {
    background: #1a1a1a;
    width: var(--sidebar-width);
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    z-index: 100;
    overflow-y: auto;
    box-shadow: 2px 0 8px rgba(0,0,0,0.2);
    border-right: 1px solid #2d2d2d;
}

/* Desktop - sidebar always visible */
@media (min-width: 1024px) {
    .sidebar {
        transform: translateX(0);
    }
}

/* Mobile - sidebar hidden by default, slides in */
@media (max-width: 1023px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
}

/* Custom scrollbar for sidebar */
.sidebar::-webkit-scrollbar {
    width: 4px;
}

.sidebar::-webkit-scrollbar-track {
    background: #2d2d2d;
}

.sidebar::-webkit-scrollbar-thumb {
    background: var(--pink);
    border-radius: 4px;
}

/* Sidebar Header with Logo */
.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid #2d2d2d;
    margin-bottom: 16px;
    background: #1a1a1a;
}

.logo-area {
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo-area i {
    font-size: 28px;
    color: var(--pink);
}

.logo-text {
    display: flex;
    flex-direction: column;
}

.school-name {
    font-weight: 700;
    font-size: 16px;
    color: white;
    letter-spacing: 0.5px;
}

.system-name {
    font-size: 10px !important;
    color: #777;
    margin-top: 2px;
}

/* User Info Section */
.user-info {
    padding: 16px 20px;
    margin-bottom: 8px;
    border-bottom: 1px solid #2d2d2d;
    background: #1a1a1a;
    text-align: center;
}

.user-name {
    font-weight: 600;
    font-size: 14px;
    color: white;
    margin-bottom: 4px;
}

.user-role {
    font-size: 11px;
    color: #888;
}

.sidebar-nav {
    padding: 8px 0;
    background: #1a1a1a;
}

.sidebar-item {
    padding: 10px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #aaa;
    text-decoration: none;
    transition: all 0.2s;
    margin: 2px 12px;
    border-radius: 10px;
    font-size: 14px;
}

.sidebar-item i {
    width: 20px;
    font-size: 16px;
    color: #888;
    transition: all 0.2s;
}

.sidebar-item span {
    flex: 1;
}

.sidebar-item:hover {
    background: #2d2d2d;
    color: var(--pink);
}

.sidebar-item:hover i {
    color: var(--pink);
}

.sidebar-item.active {
    background: var(--pink);
    color: white;
}

.sidebar-item.active i {
    color: white;
}

/* Mobile overlay when sidebar is open */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 98;
}

.sidebar-overlay.active {
    display: block;
}

@media (max-width: 1023px) {
    .sidebar-item {
        padding: 12px 20px;
        margin: 4px 12px;
    }
    
    .sidebar-header {
        padding: 16px 20px;
    }
    
    .user-info {
        padding: 12px 20px;
    }
}
</style>