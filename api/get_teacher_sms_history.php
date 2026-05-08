<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if(!isset($_SESSION['user_id'])) {
    echo 'Not logged in';
    exit();
}

// Get teacher ID from session
$teacher_sql = "SELECT id FROM teachers WHERE user_id = ?";
$teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
$teacher = mysqli_fetch_assoc($teacher_result);

if(!$teacher) {
    echo 'Teacher not found';
    exit();
}

$teacher_id = $teacher['id'];

// Get SMS history for teacher
$sms_history = safeQuery(
    "SELECT * FROM sms_history 
     WHERE sent_by = ? 
     ORDER BY created_at DESC LIMIT 50",
    [$teacher_id], 'i'
);

if(mysqli_num_rows($sms_history) > 0):
    while($sms = mysqli_fetch_assoc($sms_history)):
        ?>
        <div class="history-item">
            <div class="history-header">
                <span class="history-date">
                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($sms['created_at'])); ?>
                </span>
                <span class="badge badge-<?php echo htmlspecialchars($sms['status']); ?>">
                    <?php echo strtoupper(htmlspecialchars($sms['status'])); ?>
                </span>
            </div>
            <div class="history-message">
                <?php echo nl2br(htmlspecialchars(substr($sms['message'], 0, 200))); ?>
                <?php if(strlen($sms['message']) > 200): ?>...<?php endif; ?>
            </div>
            <div class="history-stats">
                <span><i class="fas fa-paper-plane"></i> Sent: <?php echo intval($sms['sent_count']); ?></span>
                <span><i class="fas fa-exclamation-triangle"></i> Failed: <?php echo intval($sms['failed_count']); ?></span>
            </div>
        </div>
        <?php
    endwhile;
else:
    ?>
    <div class="text-center" style="padding: 40px; color: #999;">
        <i class="fas fa-envelope-open" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i>
        No SMS history yet
        <br>
        <small>Send your first message to see it here</small>
    </div>
    <?php
endif;
?>
