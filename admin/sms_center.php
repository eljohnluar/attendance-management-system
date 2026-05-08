<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('admin');

// Get statistics
$sent_today = mysqli_fetch_assoc(safeQuery("SELECT COUNT(*) as count FROM sms_history WHERE status = 'sent' AND DATE(created_at) = CURDATE()"))['count'];
$failed_today = mysqli_fetch_assoc(safeQuery("SELECT COUNT(*) as count FROM sms_history WHERE status = 'failed' AND DATE(created_at) = CURDATE()"))['count'];
$total_sent = mysqli_fetch_assoc(safeQuery("SELECT SUM(sent_count) as total FROM sms_history"))['total'];

// Get year levels for checklist
$year_levels = safeQuery("SELECT * FROM year_levels ORDER BY sort_order");
$sections = safeQuery("SELECT s.*, yl.level_name FROM sections s JOIN year_levels yl ON s.year_level_id = yl.id WHERE s.status = 'active'");
$sms_history = safeQuery("SELECT * FROM sms_history ORDER BY created_at DESC LIMIT 50");

// Check for success/error messages
$show_sent = isset($_GET['sent']);
$show_failed = isset($_GET['failed']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>SMS Center - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .sms-container {
            padding: 24px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 20px;
            padding: 24px 20px;
            text-align: center;
            border: 1px solid var(--grey);
            transition: all 0.2s;
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
        }

        .stat-label {
            font-size: 13px;
            color: var(--grey-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .recipient-group {
            background: var(--white-smoke);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
            padding: 8px;
        }

        .checkbox-grid label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            cursor: pointer;
            padding: 6px 8px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .checkbox-grid label:hover {
            background: var(--pink-light);
        }

        .checkbox-grid input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--pink);
        }

        .char-counter {
            text-align: right;
            font-size: 11px;
            margin-top: 4px;
        }

        .char-counter.warning {
            color: #ff9800;
        }

        .char-counter.danger {
            color: #f44336;
        }

        .history-item {
            background: var(--white);
            border: 1px solid var(--grey);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }

        .history-item:hover {
            border-color: var(--pink);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .history-date {
            font-size: 11px;
            color: #999;
        }

        .history-message {
            font-size: 13px;
            color: #444;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .history-stats {
            font-size: 11px;
            color: #999;
            display: flex;
            gap: 16px;
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

        .toast-error {
            background: #f44336;
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
            .sms-container {
                padding: 16px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .checkbox-grid {
                grid-template-columns: 1fr;
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
        <div class="sms-container">
            <!-- Toast Notifications -->
            <?php if($show_sent): ?>
                <div class="toast-notification toast-success" id="successToast">
                    <i class="fas fa-check-circle"></i> SMS sent successfully!
                </div>
                <script>
                    setTimeout(() => {
                        const toast = document.getElementById('successToast');
                        if(toast) {
                            toast.style.animation = 'slideOut 0.3s ease';
                            setTimeout(() => toast.remove(), 300);
                        }
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <?php if($show_failed): ?>
                <div class="toast-notification toast-error" id="errorToast">
                    <i class="fas fa-exclamation-circle"></i> Failed to send SMS. Please try again.
                </div>
                <script>
                    setTimeout(() => {
                        const toast = document.getElementById('errorToast');
                        if(toast) {
                            toast.style.animation = 'slideOut 0.3s ease';
                            setTimeout(() => toast.remove(), 300);
                        }
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $sent_today; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-check-circle"></i> Sent Today
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $failed_today; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-exclamation-circle"></i> Failed Today
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_sent ?: 0; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-envelope"></i> Total Sent
                    </div>
                </div>
            </div>
            
            <!-- Main Grid -->
            <div class="grid-dashboard">
                <!-- New Broadcast Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bullhorn"></i>
                        <span>New Broadcast</span>
                        <button class="btn btn-outline" style="margin-left: auto;" onclick="toggleHistory()">
                            <i class="fas fa-history"></i>
                            <span>History</span>
                        </button>
                    </div>
                    <div class="card-body">
                        <form id="smsForm">
                            <div class="form-group">
                                <label class="form-label">Recipient Type</label>
                                <select id="recipientType" class="form-control" onchange="toggleRecipients()">
                                    <option value="year_level">By Year Level</option>
                                    <option value="section">By Section</option>
                                    <option value="individual">Individual Student</option>
                                </select>
                            </div>
                            
                            <!-- Year Level Selection -->
                            <div id="yearLevelGroup" class="recipient-group">
                                <label class="form-label">Select Year Levels (Multiple)</label>
                                <div class="checkbox-grid">
                                    <?php while($yl = mysqli_fetch_assoc($year_levels)): ?>
                                        <label>
                                            <input type="checkbox" name="year_levels[]" value="<?php echo $yl['id']; ?>">
                                            <?php echo $yl['level_name']; ?>
                                        </label>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                            
                            <!-- Section Selection -->
                            <div id="sectionGroup" class="recipient-group" style="display: none;">
                                <label class="form-label">Select Sections (Multiple)</label>
                                <div class="checkbox-grid">
                                    <?php while($sec = mysqli_fetch_assoc($sections)): ?>
                                        <label>
                                            <input type="checkbox" name="sections[]" value="<?php echo $sec['id']; ?>">
                                            <?php echo $sec['level_name'] . ' - ' . $sec['section_name']; ?>
                                        </label>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                            
                            <!-- Individual Student Selection -->
                            <div id="individualGroup" class="recipient-group" style="display: none;">
                                <label class="form-label">Select Student</label>
                                <select name="student_id" class="form-control">
                                    <option value="">-- Select Student --</option>
                                    <?php 
                                    $students = safeQuery("SELECT s.*, sec.section_name FROM students s LEFT JOIN sections sec ON s.section_id = sec.id WHERE s.status = 'active' ORDER BY s.fullname");
                                    while($stu = mysqli_fetch_assoc($students)): ?>
                                        <option value="<?php echo $stu['id']; ?>"><?php echo htmlspecialchars($stu['fullname'] . ' - ' . $stu['section_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <!-- Message Input -->
                            <div class="form-group">
                                <label class="form-label">Message</label>
                                <textarea id="message" class="form-control" rows="5" placeholder="Type your message here..." required></textarea>
                                <div class="char-counter" id="charCounter">0 / 160 characters</div>
                            </div>
                            
                            <!-- Template Buttons -->
                            <div class="template-buttons" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;">
                                <button type="button" class="btn btn-outline" onclick="applyTemplate('attendance')" style="font-size: 12px; padding: 6px 12px;">
                                    <i class="fas fa-calendar-check"></i> Attendance
                                </button>
                                <button type="button" class="btn btn-outline" onclick="applyTemplate('meeting')" style="font-size: 12px; padding: 6px 12px;">
                                    <i class="fas fa-users"></i> Meeting
                                </button>
                                <button type="button" class="btn btn-outline" onclick="applyTemplate('payment')" style="font-size: 12px; padding: 6px 12px;">
                                    <i class="fas fa-money-bill"></i> Payment
                                </button>
                                <button type="button" class="btn btn-outline" onclick="applyTemplate('event')" style="font-size: 12px; padding: 6px 12px;">
                                    <i class="fas fa-calendar-alt"></i> Event
                                </button>
                                <button type="button" class="btn btn-outline" onclick="applyTemplate('announcement')" style="font-size: 12px; padding: 6px 12px;">
                                    <i class="fas fa-bullhorn"></i> Announcement
                                </button>
                            </div>
                            
                            <button type="button" class="btn btn-primary w-100" onclick="sendSMS()">
                                <i class="fas fa-paper-plane"></i> Send Broadcast
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- SMS History Card -->
                <div class="card" id="historyCard" style="display: none;">
                    <div class="card-header">
                        <i class="fas fa-history"></i>
                        <span>SMS History</span>
                        <button class="btn btn-outline" style="margin-left: auto;" onclick="refreshHistory()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;" id="historyList">
                        <?php if(mysqli_num_rows($sms_history) > 0): ?>
                            <?php while($sms = mysqli_fetch_assoc($sms_history)): ?>
                                <div class="history-item">
                                    <div class="history-header">
                                        <span class="history-date">
                                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($sms['created_at'])); ?>
                                        </span>
                                        <span class="badge badge-<?php echo $sms['status']; ?>">
                                            <?php echo strtoupper($sms['status']); ?>
                                        </span>
                                    </div>
                                    <div class="history-message">
                                        <?php echo nl2br(htmlspecialchars(substr($sms['message'], 0, 200))); ?>
                                        <?php if(strlen($sms['message']) > 200): ?>...<?php endif; ?>
                                    </div>
                                    <div class="history-stats">
                                        <span><i class="fas fa-paper-plane"></i> Sent: <?php echo $sms['sent_count']; ?></span>
                                        <span><i class="fas fa-exclamation-triangle"></i> Failed: <?php echo $sms['failed_count']; ?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center" style="padding: 40px; color: #999;">
                                <i class="fas fa-envelope-open" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                                No SMS history yet
                                <br>
                                <small>Send your first message to see it here</small>
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
        
        function toggleRecipients() {
            let type = document.getElementById('recipientType').value;
            document.getElementById('yearLevelGroup').style.display = type == 'year_level' ? 'block' : 'none';
            document.getElementById('sectionGroup').style.display = type == 'section' ? 'block' : 'none';
            document.getElementById('individualGroup').style.display = type == 'individual' ? 'block' : 'none';
        }
        
        function toggleHistory() {
            let history = document.getElementById('historyCard');
            history.style.display = history.style.display == 'none' ? 'block' : 'none';
        }
        
        function refreshHistory() {
            location.reload();
        }
        
        function applyTemplate(type) {
            let message = '';
            let currentDate = new Date().toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            switch(type) {
                case 'attendance':
                    message = `Dear Parent/Guardian,\n\nThis is a reminder about your child's attendance. Regular attendance is crucial for academic success. Please ensure they come to school on time.\n\nThank you for your cooperation.`;
                    break;
                case 'meeting':
                    message = `Dear Parent/Guardian,\n\nYou are invited to a Parent-Teacher Conference on ${currentDate} at 9:00 AM. Your attendance is important to discuss your child's progress.\n\nThank you.`;
                    break;
                case 'payment':
                    message = `Dear Parent/Guardian,\n\nThis is a friendly reminder about upcoming tuition fees. Please settle your account by the end of the month to avoid penalties.\n\nThank you for your prompt action.`;
                    break;
                case 'event':
                    message = `Dear Parent/Guardian,\n\nWe are pleased to invite you to our upcoming School Event. Join us in celebrating our students' achievements.\n\nSee you there!`;
                    break;
                case 'announcement':
                    message = `Dear Parent/Guardian,\n\nImportant Announcement: Please be advised that there will be no classes on the upcoming Friday due to a school event.\n\nThank you for your understanding.`;
                    break;
            }
            
            document.getElementById('message').value = message;
            updateCharCount();
        }
        
        function updateCharCount() {
            let message = document.getElementById('message').value;
            let length = message.length;
            let counter = document.getElementById('charCounter');
            
            if(length <= 160) {
                counter.innerHTML = length + ' / 160 characters';
                counter.className = 'char-counter';
            } else if(length <= 320) {
                counter.innerHTML = length + ' / 320 characters (2 SMS)';
                counter.className = 'char-counter warning';
            } else {
                let smsCount = Math.ceil(length / 153);
                counter.innerHTML = length + ' characters (' + smsCount + ' SMS)';
                counter.className = 'char-counter danger';
            }
        }
        
        function sendSMS() {
            let type = document.getElementById('recipientType').value;
            let message = document.getElementById('message').value;
            
            if(!message.trim()) {
                alert('Please enter a message');
                return;
            }
            
            let data = { type: type, message: message };
            
            if(type == 'year_level') {
                let levels = [];
                document.querySelectorAll('input[name="year_levels[]"]:checked').forEach(cb => levels.push(cb.value));
                if(levels.length == 0) {
                    alert('Select at least one year level');
                    return;
                }
                data.recipients = levels;
            } else if(type == 'section') {
                let sections = [];
                document.querySelectorAll('input[name="sections[]"]:checked').forEach(cb => sections.push(cb.value));
                if(sections.length == 0) {
                    alert('Select at least one section');
                    return;
                }
                data.recipients = sections;
            } else {
                let student = document.querySelector('select[name="student_id"]').value;
                if(!student) {
                    alert('Select a student');
                    return;
                }
                data.recipients = [student];
            }
            
            // Show loading state
            const sendBtn = event.target;
            const originalText = sendBtn.innerHTML;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            sendBtn.disabled = true;
            
            $.ajax({
                url: '../api/send_sms.php',
                method: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        window.location.href = '?sent=1';
                    } else {
                        sendBtn.innerHTML = originalText;
                        sendBtn.disabled = false;
                        window.location.href = '?failed=1';
                    }
                },
                error: function() {
                    sendBtn.innerHTML = originalText;
                    sendBtn.disabled = false;
                    window.location.href = '?failed=1';
                }
            });
        }
        
        // Character counter
        document.getElementById('message').addEventListener('input', updateCharCount);
        
        // Initialize
        updateCharCount();
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        }
    </script>
</body>
</html>