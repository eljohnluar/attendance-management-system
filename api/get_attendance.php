<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$today = date('Y-m-d');
$logs = safeQuery("SELECT al.*, s.fullname, s.photo, sec.section_name 
                  FROM attendance_log al 
                  JOIN students s ON al.student_id = s.id 
                  LEFT JOIN sections sec ON s.section_id = sec.id
                  WHERE al.log_date = ? 
                  ORDER BY al.time_captured DESC LIMIT 30", [$today], 's');

if(mysqli_num_rows($logs) > 0) {
    while($log = mysqli_fetch_assoc($logs)) {
        $statusClass = $log['status'] == 'late' ? 'danger' : 'success';
        $statusText = $log['status'] == 'late' ? 'Late' : 'On Time';
        $logType = str_replace('_', ' ', $log['log_type']);
        $logTypeClass = strpos($log['log_type'], 'in') !== false ? 'info' : 'warning';
        ?>
        <div class="attendance-item">
            <div class="attendance-info">
                <div class="attendance-name"><?php echo htmlspecialchars($log['fullname']); ?></div>
                <div class="attendance-meta">
                    <span class="badge badge-<?php echo $logTypeClass; ?>"><?php echo ucfirst($logType); ?></span>
                    <span class="badge badge-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                </div>
            </div>
            <div class="attendance-status">
                <div class="time-text"><?php echo date('h:i A', strtotime($log['time_captured'])); ?></div>
                <div class="attendance-meta"><?php echo strtoupper($log['method']); ?></div>
            </div>
        </div>
        <?php
    }
} else {
    ?>
    <div class="empty-state">
        <i class="fas fa-calendar-day"></i>
        <p>No attendance records today</p>
        <small>Scan a QR code or enter RFID to get started</small>
    </div>
    <?php
}
?>