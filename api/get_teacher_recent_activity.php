<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo '<div class="text-center" style="padding: 40px; color: #999;">Unauthorized</div>';
    exit();
}

$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

$sql = "SELECT al.*, s.fullname, s.photo, sec.section_name 
        FROM attendance_log al
        JOIN students s ON al.student_id = s.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE sec.teacher_id = ?
        ORDER BY al.id DESC, al.time_captured DESC LIMIT 20";

$result = safeQuery($sql, [$teacher_id], 'i');

if(mysqli_num_rows($result) > 0) {
    while($log = mysqli_fetch_assoc($result)) {
        ?>
        <div class="activity-item">
            <div>
                <?php if($log['photo'] && $log['photo'] != 'default_student.png' && file_exists('../uploads/students/' . $log['photo'])): ?>
                    <div class="student-avatar">
                        <img src="../uploads/students/<?php echo $log['photo']; ?>" alt="<?php echo htmlspecialchars($log['fullname']); ?>">
                    </div>
                <?php else: ?>
                    <div class="student-avatar default">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="activity-info">
                <div class="activity-name"><?php echo htmlspecialchars($log['fullname']); ?></div>
                <div class="activity-section"><?php echo htmlspecialchars($log['section_name']); ?></div>
            </div>
            <div class="activity-time">
                <div class="activity-type">
                    <span class="badge badge-<?php echo strpos($log['log_type'], 'in') !== false ? 'success' : 'danger'; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $log['log_type'])); ?>
                    </span>
                </div>
                <div class="time-text"><?php echo date('h:i A', strtotime($log['time_captured'])); ?></div>
            </div>
        </div>
        <?php
    }
} else {
    echo '<div class="text-center" style="padding: 40px; color: #999;">
            <i class="fas fa-clock" style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
            No recent activity
          </div>';
}
?>