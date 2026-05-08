<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if(!isset($_SESSION['user_id'])) {
    echo '<div class="text-center text-muted">Please login to view history</div>';
    exit();
}

// Get teacher ID
$teacher_sql = "SELECT id FROM teachers WHERE user_id = ?";
$teacher_result = safeQuery($teacher_sql, [$_SESSION['user_id']], 'i');
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_id = $teacher['id'];

$sql = "SELECT * FROM sms_history 
        WHERE sent_by = ? 
        ORDER BY created_at DESC LIMIT 50";

$result = safeQuery($sql, [$teacher_id], 'i');

if(mysqli_num_rows($result) > 0) {
    while($sms = mysqli_fetch_assoc($result)) {
        ?>
        <div class="card" style="margin-bottom: 12px;">
            <div class="card-body">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <small class="text-muted">
                        <i class="fas fa-calendar"></i> 
                        <?php echo date('M d, Y h:i A', strtotime($sms['created_at'])); ?>
                    </small>
                    <span class="badge badge-<?php echo $sms['status']; ?>">
                        <?php echo strtoupper($sms['status']); ?>
                    </span>
                </div>
                <div style="font-size: 13px; margin-bottom: 8px;">
                    <?php 
                    $display_type = '';
                    switch($sms['recipient_type']) {
                        case 'grade_checklist':
                            $display_type = 'Grade Level Checklist';
                            break;
                        case 'section':
                            $display_type = 'Specific Section';
                            break;
                        case 'individual':
                            $display_type = 'Individual Student';
                            break;
                        default:
                            $display_type = $sms['recipient_type'];
                    }
                    ?>
                    <small class="text-muted">
                        <i class="fas fa-tag"></i> <?php echo $display_type; ?>
                    </small>
                    <div style="margin-top: 8px;"><?php echo nl2br(htmlspecialchars($sms['message'])); ?></div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="fas fa-users"></i> 
                        Sent: <?php echo $sms['sent_count']; ?> | 
                        Failed: <?php echo $sms['failed_count']; ?>
                    </small>
                </div>
            </div>
        </div>
        <?php
    }
} else {
    echo '<div class="text-center text-muted" style="padding: 40px;">
            <i class="fas fa-envelope-open" style="font-size: 48px; margin-bottom: 16px;"></i>
            <p>No SMS history yet</p>
            <small>Send your first message to see it here</small>
          </div>';
}
?>