<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireRole('teacher');

function getTeacherSmsCount($sql, $teacher_id) {
    $result = safeQuery($sql, [$teacher_id], 'i');
    if($result && ($row = mysqli_fetch_assoc($result))) {
        if(isset($row['count'])) {
            return (int)$row['count'];
        }
        if(isset($row['total'])) {
            return (int)$row['total'];
        }
    }
    return 0;
}

// Get teacher ID from session
$teacher_sql = "SELECT t.id, u.fullname, u.email, u.phone
                FROM teachers t
                JOIN users u ON t.user_id = u.id
                WHERE t.user_id = ?";
$teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
$teacher = $teacher_result ? mysqli_fetch_assoc($teacher_result) : null;
$teacher_id = $teacher['id'] ?? 0;

// Get statistics for teacher
$sent_today = getTeacherSmsCount(
    "SELECT COUNT(*) as count FROM sms_history 
     WHERE status = 'sent' AND DATE(created_at) = CURDATE() AND sent_by = ?",
    $teacher_id
);

$failed_today = getTeacherSmsCount(
    "SELECT COUNT(*) as count FROM sms_history 
     WHERE status = 'failed' AND DATE(created_at) = CURDATE() AND sent_by = ?",
    $teacher_id
);

$total_sent = getTeacherSmsCount(
    "SELECT SUM(sent_count) as total FROM sms_history WHERE sent_by = ?",
    $teacher_id
);

// Get teacher's sections (only assigned sections)
$sections = $teacher_id > 0 ? safeQuery(
    "SELECT s.*, yl.level_name, 
     (SELECT COUNT(*) FROM students WHERE section_id = s.id AND status = 'active') as student_count
     FROM sections s
     JOIN year_levels yl ON s.year_level_id = yl.id
     WHERE s.teacher_id = ? AND s.status = 'active'
     ORDER BY yl.sort_order, s.section_name",
    [$teacher_id], 'i'
) : false;

// Get students for individual messaging (only teacher's students)
$students = $teacher_id > 0 ? safeQuery(
    "SELECT s.*, sec.section_name 
     FROM students s
     LEFT JOIN sections sec ON s.section_id = sec.id
     WHERE sec.teacher_id = ? AND s.status = 'active'
     ORDER BY s.fullname",
    [$teacher_id], 'i'
) : false;

// Get SMS history for teacher
$sms_history = $teacher_id > 0 ? safeQuery(
    "SELECT * FROM sms_history 
     WHERE sent_by = ? 
     ORDER BY created_at DESC LIMIT 50",
    [$teacher_id], 'i'
) : false;

// Get year levels that have students assigned to this teacher only
$year_levels = $teacher_id > 0 ? safeQuery(
    "SELECT DISTINCT yl.id, yl.level_name, yl.sort_order
     FROM year_levels yl
     JOIN sections sec ON sec.year_level_id = yl.id
     WHERE sec.teacher_id = ? AND sec.status = 'active'
     ORDER BY yl.sort_order",
    [$teacher_id], 'i'
) : false;

// Check for success messages
$show_sent = isset($_GET['sent']);
$show_failed = isset($_GET['failed']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo SITE_URL; ?>">
    <title>SMS Center - Teacher</title>
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

        .template-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }

        .template-btn {
            background: var(--grey-light);
            border: 1px solid var(--grey);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .template-btn:hover {
            background: var(--pink-light);
            border-color: var(--pink);
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

        /* Confirmation Modal */
        .confirm-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1002;
        }

        .confirm-modal.show {
            display: flex;
        }

        .confirm-content {
            background: white;
            border-radius: 16px;
            padding: 24px;
            max-width: 450px;
            width: 90%;
            text-align: center;
        }

        .confirm-content i {
            font-size: 48px;
            color: #ff9800;
            margin-bottom: 16px;
        }

        .confirm-content h3 {
            margin-bottom: 8px;
        }

        .confirm-content p {
            color: #666;
            margin-bottom: 20px;
        }

        .confirm-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .main-content {
            margin-top: var(--navbar-height);
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - var(--navbar-height));
            width: 100%;
            overflow-x: hidden;
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
    <?php include '../includes/teacher_navbar.php'; ?>
    <?php include '../includes/teacher_sidebar.php'; ?>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="main-content">
        <div class="sms-container">
            <!-- Toast Notifications -->
            <?php if($show_sent): ?>
                <div class="toast-notification toast-success" id="sentToast">
                    <i class="fas fa-check-circle"></i> SMS sent successfully!
                </div>
                <script>
                    setTimeout(() => {
                        const toast = document.getElementById('sentToast');
                        if(toast) {
                            toast.style.animation = 'slideOut 0.3s ease';
                            setTimeout(() => toast.remove(), 300);
                        }
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <?php if($show_failed): ?>
                <div class="toast-notification toast-error" id="failedToast">
                    <i class="fas fa-exclamation-circle"></i> Failed to send SMS. Please try again.
                </div>
                <script>
                    setTimeout(() => {
                        const toast = document.getElementById('failedToast');
                        if(toast) {
                            toast.style.animation = 'slideOut 0.3s ease';
                            setTimeout(() => toast.remove(), 300);
                        }
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <!-- Info Note -->
            <div class="info-note">
                <i class="fas fa-info-circle"></i> Send messages to parents of students assigned to your sections.
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number" id="sentToday"><?php echo $sent_today; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-check-circle"></i> Sent Today
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="failedToday"><?php echo $failed_today; ?></div>
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
                        <!-- Message Templates -->
                        <div class="template-buttons">
                            <button class="template-btn" onclick="applyTemplate('attendance')">
                                <i class="fas fa-calendar-check"></i> Attendance
                            </button>
                            <button class="template-btn" onclick="applyTemplate('meeting')">
                                <i class="fas fa-users"></i> Meeting
                            </button>
                            <button class="template-btn" onclick="applyTemplate('payment')">
                                <i class="fas fa-money-bill"></i> Payment
                            </button>
                            <button class="template-btn" onclick="applyTemplate('event')">
                                <i class="fas fa-calendar-alt"></i> Event
                            </button>
                            <button class="template-btn" onclick="applyTemplate('announcement')">
                                <i class="fas fa-bullhorn"></i> Announcement
                            </button>
                        </div>
                        
                        <form id="smsForm">
                            <!-- Recipient Type Selection -->
                            <div class="form-group">
                                <label class="form-label">Recipient Type</label>
                                <select id="recipientType" class="form-control" onchange="toggleRecipients()">
                                    <option value="grade_checklist">By Year Level</option>
                                    <option value="section">By Section</option>
                                    <option value="individual">Individual Student</option>
                                </select>
                            </div>
                            
                            <!-- Grade Level Checklist (Only teacher's year levels) -->
                            <div id="gradeChecklistGroup" class="recipient-group">
                                <label class="form-label">Select Year Levels (Multiple)</label>
                                <?php if($year_levels && mysqli_num_rows($year_levels) > 0): ?>
                                    <div class="checkbox-grid">
                                        <?php while($yl = mysqli_fetch_assoc($year_levels)): ?>
                                            <label>
                                                <input type="checkbox" name="year_levels[]" value="<?php echo $yl['id']; ?>" onchange="updateRecipientCount()">
                                                <?php echo $yl['level_name']; ?>
                                                <small class="text-muted" id="count_<?php echo $yl['id']; ?>">(0)</small>
                                            </label>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted" style="padding: 20px; text-align: center;">
                                        <i class="fas fa-info-circle"></i> No year levels with assigned students
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Specific Section (Only teacher's sections) -->
                            <div id="sectionGroup" class="recipient-group" style="display: none;">
                                <label class="form-label">Select Section</label>
                                <?php if($sections && mysqli_num_rows($sections) > 0): ?>
                                    <select id="sectionSelect" class="form-control" onchange="updateRecipientCount()">
                                        <option value="">-- Select a section --</option>
                                        <?php while($sec = mysqli_fetch_assoc($sections)): ?>
                                            <option value="<?php echo $sec['id']; ?>" data-count="<?php echo $sec['student_count']; ?>">
                                                <?php echo $sec['level_name'] . ' - ' . $sec['section_name']; ?> 
                                                (<?php echo $sec['student_count']; ?> students)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                <?php else: ?>
                                    <div class="text-muted" style="padding: 20px; text-align: center;">
                                        <i class="fas fa-info-circle"></i> No sections assigned
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Individual Student (Only teacher's students) -->
                            <div id="individualGroup" class="recipient-group" style="display: none;">
                                <label class="form-label">Select Student</label>
                                <?php if($students && mysqli_num_rows($students) > 0): ?>
                                    <select id="studentSelect" class="form-control" onchange="updateRecipientCount()">
                                        <option value="">-- Select a student --</option>
                                        <?php while($stu = mysqli_fetch_assoc($students)): ?>
                                            <option value="<?php echo $stu['id']; ?>">
                                                <?php echo htmlspecialchars($stu['fullname']); ?> - <?php echo htmlspecialchars($stu['section_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                <?php else: ?>
                                    <div class="text-muted" style="padding: 20px; text-align: center;">
                                        <i class="fas fa-info-circle"></i> No students assigned
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Message Input -->
                            <div class="form-group">
                                <label class="form-label">Message</label>
                                <textarea id="message" class="form-control" rows="5" placeholder="Type your message here..." oninput="updateCharCount()" required></textarea>
                                <div class="char-counter" id="charCounter">0 / 160 characters</div>
                            </div>
                            
                            <!-- Recipient Summary -->
                            <div id="recipientSummary" class="alert alert-info" style="display: none;">
                                <i class="fas fa-info-circle"></i>
                                <span id="recipientCount">0</span> parent(s) will receive this message
                            </div>
                            
                            <!-- Send Button -->
                            <button type="button" class="btn btn-primary w-100" onclick="showConfirmModal()" id="sendBtn">
                                <i class="fas fa-paper-plane"></i> Send Message
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
                        <?php if($sms_history && mysqli_num_rows($sms_history) > 0): ?>
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
    
    <!-- Confirmation Modal -->
    <div id="confirmModal" class="confirm-modal">
        <div class="confirm-content">
            <i class="fas fa-paper-plane"></i>
            <h3>Confirm Send</h3>
            <p id="confirmMessage">Are you sure you want to send this message?</p>
            <div class="confirm-buttons">
                <button class="btn btn-secondary" onclick="closeConfirmModal()">Cancel</button>
                <button class="btn btn-primary" id="confirmSendBtn">Send Message</button>
            </div>
        </div>
    </div>
    
    <script>
        let pendingSMSData = null;
        
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
        
        // Initialize
        $(document).ready(function() {
            loadStudentCounts();
            updateCharCount();
        });
        
        // Show confirmation modal
        function showConfirmModal() {
            let type = document.getElementById('recipientType').value;
            let message = document.getElementById('message').value;
            let recipients = [];
            
            if(!message.trim()) {
                alert('Please enter a message');
                return;
            }
            
            if(type == 'grade_checklist') {
                $('input[name="year_levels[]"]:checked').each(function() {
                    recipients.push($(this).val());
                });
                if(recipients.length == 0) {
                    alert('Please select at least one year level');
                    return;
                }
            } else if(type == 'section') {
                let sectionId = document.getElementById('sectionSelect').value;
                if(!sectionId) {
                    alert('Please select a section');
                    return;
                }
                recipients = [sectionId];
            } else {
                let studentId = document.getElementById('studentSelect').value;
                if(!studentId) {
                    alert('Please select a student');
                    return;
                }
                recipients = [studentId];
            }
            
            let count = updateRecipientCount();
            if(count == 0) {
                alert('No recipients selected');
                return;
            }
            
            // Store data for sending
            pendingSMSData = {
                type: type,
                recipients: recipients,
                message: message,
                count: count
            };
            
            // Show confirmation modal
            document.getElementById('confirmMessage').innerHTML = `Send SMS to <strong>${count}</strong> parent(s)?<br><br>Message: "${message.substring(0, 100)}${message.length > 100 ? '...' : ''}"`;
            document.getElementById('confirmModal').classList.add('show');
        }
        
        function closeConfirmModal() {
            pendingSMSData = null;
            document.getElementById('confirmModal').classList.remove('show');
        }
        
        // Send SMS after confirmation (FIXED - No redirect, just show toast)
        document.getElementById('confirmSendBtn').addEventListener('click', function() {
            if(!pendingSMSData) return;
            
            const sendBtn = document.getElementById('confirmSendBtn');
            const originalText = sendBtn.innerHTML;
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            $.ajax({
                url: '../api/send_teacher_sms.php',
                method: 'POST',
                data: JSON.stringify({
                    type: pendingSMSData.type,
                    recipient_type: pendingSMSData.type,
                    recipients: pendingSMSData.recipients,
                    message: pendingSMSData.message,
                    teacher_id: <?php echo $teacher_id; ?>
                }),
                contentType: 'application/json',
                dataType: 'json',
                timeout: 30000,
                success: function(response) {
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = originalText;
                    closeConfirmModal();
                    
                    if(response.success) {
                        // Show success toast without reloading
                        showToast('SMS sent successfully!', 'success');
                        
                        // Update statistics
                        let sentToday = parseInt($('#sentToday').text()) + response.sent;
                        $('#sentToday').text(sentToday);
                        
                        // Clear form
                        document.getElementById('message').value = '';
                        updateCharCount();
                        $('input[name="year_levels[]"]').prop('checked', false);
                        document.getElementById('sectionSelect').value = '';
                        document.getElementById('studentSelect').value = '';
                        document.getElementById('recipientSummary').style.display = 'none';
                        
                        // Refresh history
                        refreshHistory();
                        loadStudentCounts();
                        pendingSMSData = null;
                    } else {
                        showToast(response.message || 'Failed to send SMS', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = originalText;
                    closeConfirmModal();
                    showToast('Network error. Please try again.', 'error');
                    console.error('SMS Send Error:', {status: status, error: error});
                }
            });
        });
        
        // Show toast notification
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Load student counts for each grade level (only teacher's students)
        function loadStudentCounts() {
            $.ajax({
                url: '../api/get_teacher_student_counts.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    for(let levelId in data) {
                        $('#count_' + levelId).text('(' + data[levelId] + ')');
                    }
                }
            });
        }
        
        // Toggle recipient groups
        function toggleRecipients() {
            let type = document.getElementById('recipientType').value;
            document.getElementById('gradeChecklistGroup').style.display = type == 'grade_checklist' ? 'block' : 'none';
            document.getElementById('sectionGroup').style.display = type == 'section' ? 'block' : 'none';
            document.getElementById('individualGroup').style.display = type == 'individual' ? 'block' : 'none';
            updateRecipientCount();
        }
        
        // Update recipient count and show summary
        function updateRecipientCount() {
            let type = document.getElementById('recipientType').value;
            let count = 0;
            
            if(type == 'grade_checklist') {
                let selectedLevels = [];
                $('input[name="year_levels[]"]:checked').each(function() {
                    selectedLevels.push($(this).val());
                });
                selectedLevels.forEach(levelId => {
                    let countText = $('#count_' + levelId).text();
                    let match = countText.match(/\((\d+)\)/);
                    if(match) {
                        count += parseInt(match[1]);
                    }
                });
            } else if(type == 'section') {
                let sectionId = document.getElementById('sectionSelect').value;
                if(sectionId) {
                    let option = document.getElementById('sectionSelect').options[document.getElementById('sectionSelect').selectedIndex];
                    count = parseInt(option.getAttribute('data-count')) || 0;
                }
            } else if(type == 'individual') {
                if(document.getElementById('studentSelect').value) {
                    count = 1;
                }
            }
            
            if(count > 0) {
                document.getElementById('recipientSummary').style.display = 'block';
                document.getElementById('recipientCount').innerHTML = count;
            } else {
                document.getElementById('recipientSummary').style.display = 'none';
            }
            
            return count;
        }
        
        // Update character counter
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
        
        // Apply message template
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
        
        // Toggle history view
        function toggleHistory() {
            let historyCard = document.getElementById('historyCard');
            if(historyCard.style.display == 'none') {
                historyCard.style.display = 'block';
                refreshHistory();
            } else {
                historyCard.style.display = 'none';
            }
        }
        
        // Refresh SMS history
        function refreshHistory() {
            $.ajax({
                url: '../api/get_teacher_sms_history.php',
                method: 'GET',
                success: function(data) {
                    document.getElementById('historyList').innerHTML = data;
                }
            });
        }
        
        // Auto-refresh statistics every 30 seconds
        setInterval(function() {
            $.ajax({
                url: '../api/get_teacher_sms_stats.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    $('#sentToday').text(data.sent_today);
                    $('#failedToday').text(data.failed_today);
                }
            });
        }, 30000);
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const confirmModal = document.getElementById('confirmModal');
            if (event.target === confirmModal) {
                closeConfirmModal();
            }
        }
    </script>
</body>
</html>