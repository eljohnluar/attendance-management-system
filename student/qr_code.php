<?php
// student/qr_code.php - Updated QR code generation
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

// Get student record from students table - match by user_id first
$student_record = null;
$student_record_id = null;
$qr_code_value = null;
$qr_filepath = null;
$has_real_student_record = false;

// First attempt: Get student by user_id (most reliable)
$student_sql = "SELECT s.id, s.fullname, s.lrn, s.qr_code, s.photo, sec.section_name, yl.level_name 
                FROM students s
                LEFT JOIN sections sec ON s.section_id = sec.id
                LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                WHERE s.user_id = ?";
$student_result = safeQuery($student_sql, [$student_id], 'i');

if($student_result && mysqli_num_rows($student_result) > 0) {
    $student_record = mysqli_fetch_assoc($student_result);
    $student_record_id = $student_record['id'];
    $qr_code_value = $student_record['qr_code'];
    $has_real_student_record = true;
}

// Second attempt: Get student by fullname matching logged-in user
if(!$student_record) {
    $student_sql = "SELECT s.id, s.fullname, s.lrn, s.qr_code, s.photo, sec.section_name, yl.level_name 
                    FROM students s
                    LEFT JOIN sections sec ON s.section_id = sec.id
                    LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                    WHERE s.fullname = ?";
    $student_result = safeQuery($student_sql, [$student_name], 's');
    
    if($student_result && mysqli_num_rows($student_result) > 0) {
        $student_record = mysqli_fetch_assoc($student_result);
        $student_record_id = $student_record['id'];
        $qr_code_value = $student_record['qr_code'];
        $has_real_student_record = true;
    }
}

// Third attempt: Try by username prefix
if(!$student_record) {
    $name_parts = explode('.', $student_username);
    $first_name = ucfirst($name_parts[0] ?? '');
    
    $student_sql = "SELECT s.id, s.fullname, s.lrn, s.qr_code, s.photo, sec.section_name, yl.level_name 
                    FROM students s
                    LEFT JOIN sections sec ON s.section_id = sec.id
                    LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                    WHERE s.fullname LIKE ?";
    $student_result = safeQuery($student_sql, ["$first_name%"], 's');
    
    if($student_result && mysqli_num_rows($student_result) > 0) {
        $student_record = mysqli_fetch_assoc($student_result);
        $student_record_id = $student_record['id'];
        $qr_code_value = $student_record['qr_code'];
        $has_real_student_record = true;
    }
}

// If no student record found, try to get the first student for this user
if(!$student_record) {
    $student_sql = "SELECT s.id, s.fullname, s.lrn, s.qr_code, s.photo, sec.section_name, yl.level_name 
                    FROM students s
                    LEFT JOIN sections sec ON s.section_id = sec.id
                    LEFT JOIN year_levels yl ON sec.year_level_id = yl.id
                    LIMIT 1";
    $student_result = safeQuery($student_sql, [], '');
    
    if($student_result && mysqli_num_rows($student_result) > 0) {
        $student_record = mysqli_fetch_assoc($student_result);
        $student_record_id = $student_record['id'];
        $qr_code_value = $student_record['qr_code'];
        $has_real_student_record = true;
    }
}

// If still no student found, create a basic one
if(!$student_record) {
    $student_record = [
        'id' => 0,
        'fullname' => $student_name,
        'lrn' => 'Not set',
        'qr_code' => null,
        'photo' => 'default_student.png',
        'section_name' => 'Not assigned',
        'level_name' => ''
    ];
    $student_record_id = 0;
}

// Parse existing QR code to get filepath
$qr_filepath_from_db = null;
$qr_data_from_db = null;

if(!empty($qr_code_value)) {
    $raw_qr_value = trim((string)$qr_code_value);

    if(strpos($raw_qr_value, '|') !== false) {
        $parts = explode('|', $raw_qr_value, 2);
        $qr_data_from_db = trim($parts[0] ?? '');
        $qr_filepath_from_db = trim($parts[1] ?? '');
    } elseif(preg_match('/(?:^|\/)qr_student_\d+\.(?:png|jpg|jpeg|gif|webp|txt)$/i', str_replace('\\', '/', $raw_qr_value))) {
        $qr_filepath_from_db = $raw_qr_value;
    } else {
        $qr_data_from_db = $raw_qr_value;
    }

    $qr_filepath_from_db = $qr_filepath_from_db ? normalizeQrFilepath($qr_filepath_from_db) : null;
}

// Determine QR data to display
$display_qr_data = (!empty($qr_data_from_db) && ctype_digit((string)$qr_data_from_db))
    ? (string)$qr_data_from_db
    : buildStudentQrData($student_record_id);
$display_qr_filepath = isScannableQrImagePath($qr_filepath_from_db) ? normalizeQrFilepath($qr_filepath_from_db) : null;
$qr_image_path = null;
$qr_image_exists = false;

// Check if QR file exists in uploads directory
if($display_qr_filepath) {
    // Try multiple possible paths
    $possible_paths = [
        '../uploads/' . $display_qr_filepath,
        '../uploads/qrcodes/' . basename($display_qr_filepath),
        '../uploads/qrcodes/qr_student_' . $student_record_id . '.png'
    ];
    
    foreach($possible_paths as $path) {
        if(file_exists($path) && @getimagesize($path) !== false) {
            $qr_image_exists = true;
            $qr_image_path = $path;
            break;
        }
    }
}

// If QR file doesn't exist, generate a new one
$needs_regeneration = false;
if($has_real_student_record && $student_record_id > 0 && !$qr_image_exists) {
    $needs_regeneration = true;
    
    // Generate new QR code
    $qr_data = buildStudentQrData($student_record_id);
    $qr_filename = "qr_student_" . $student_record_id;
    $qr_filepath_result = generateQRCode($qr_data, $qr_filename);
    
    if($qr_filepath_result) {
        $qr_code_value = buildStudentQrCodeValue($student_record_id, $qr_filepath_result);
        safeQuery("UPDATE students SET qr_code = ? WHERE id = ?", [$qr_code_value, $student_record_id], 'si');
        $display_qr_filepath = isScannableQrImagePath($qr_filepath_result) ? normalizeQrFilepath($qr_filepath_result) : null;
        $display_qr_data = $qr_data;
        
        // Check if the new file exists
        if($display_qr_filepath && file_exists('../uploads/' . $display_qr_filepath) && @getimagesize('../uploads/' . $display_qr_filepath) !== false) {
            $qr_image_exists = true;
            $qr_image_path = '../uploads/' . $display_qr_filepath;
        } else {
            $qr_image_exists = false;
            $qr_image_path = null;
        }
        $needs_regeneration = false;
    }
}

// Handle regenerate QR code
if(isset($_GET['regenerate']) && $has_real_student_record && $student_record_id > 0) {
    // Generate new QR using student ID as data
    $qr_data = buildStudentQrData($student_record_id);
    $qr_filename = "qr_student_" . $student_record_id;
    $qr_filepath_result = generateQRCode($qr_data, $qr_filename);
    
    if($qr_filepath_result && isScannableQrImagePath($qr_filepath_result) && file_exists('../uploads/' . normalizeQrFilepath($qr_filepath_result)) && @getimagesize('../uploads/' . normalizeQrFilepath($qr_filepath_result)) !== false) {
        $qr_code_value = buildStudentQrCodeValue($student_record_id, $qr_filepath_result);
        safeQuery("UPDATE students SET qr_code = ? WHERE id = ?", [$qr_code_value, $student_record_id], 'si');
        $display_qr_filepath = isScannableQrImagePath($qr_filepath_result) ? normalizeQrFilepath($qr_filepath_result) : null;
        $display_qr_data = $qr_data;
        $qr_image_exists = ($display_qr_filepath && file_exists('../uploads/' . $display_qr_filepath) && @getimagesize('../uploads/' . $display_qr_filepath) !== false);
        $qr_image_path = $qr_image_exists ? ('../uploads/' . $display_qr_filepath) : null;
        $needs_regeneration = false;
        // Refresh the page to show new QR
        header('Location: ' . basename(__FILE__) . '?regenerated=1');
        exit();
    }

    // Keep the old QR and show a proper error if regeneration failed.
    header('Location: ' . basename(__FILE__) . '?regenerate_failed=1');
    exit();
}

// Handle download QR
if(isset($_GET['download']) && $has_real_student_record && $student_record_id > 0) {
    if($qr_image_exists && $qr_image_path && file_exists($qr_image_path)) {
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="student_qr_' . $student_record_id . '.png"');
        readfile($qr_image_path);
        exit();
    } else {
        // Generate on the fly using student ID
        $temp_qr_data = buildStudentQrData($student_record_id);
        $temp_qr = generateQRCode($temp_qr_data, 'temp_' . $student_record_id);
        if($temp_qr && isScannableQrImagePath($temp_qr) && file_exists('../uploads/' . normalizeQrFilepath($temp_qr))) {
            header('Content-Type: image/png');
            header('Content-Disposition: attachment; filename="student_qr_' . $student_record_id . '.png"');
            readfile('../uploads/' . normalizeQrFilepath($temp_qr));
            exit();
        }
    }
}

// For display in img src, use relative path from web root
$qr_image_url = null;
if($qr_image_exists && $qr_image_path) {
    // Convert filesystem path to URL
    $qr_image_url = str_replace('../', '', $qr_image_path);
    $qr_image_url = str_replace('\\', '/', $qr_image_url);
}

// Get scan count
$scan_count = 0;
if($has_real_student_record && $student_record_id > 0) {
    $scan_stats = safeQuery("SELECT COUNT(*) as count FROM attendance_log WHERE student_id = ? AND method = 'qr'", [$student_record_id], 'i');
    if($scan_stats && mysqli_num_rows($scan_stats) > 0) {
        $scan_count = mysqli_fetch_assoc($scan_stats)['count'];
    }
}

$show_regenerated = isset($_GET['regenerated']);
$show_regenerate_failed = isset($_GET['regenerate_failed']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My QR Code - Student</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .qr-container {
            padding: 24px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .qr-card {
            background: var(--white);
            border-radius: 24px;
            border: 1px solid var(--grey);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .qr-header {
            background: linear-gradient(135deg, var(--pink) 0%, var(--pink-dark) 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .qr-header h2 {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .qr-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .qr-body {
            padding: 40px;
            text-align: center;
        }

        .qr-code-wrapper {
            display: inline-block;
            padding: 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }

        .qr-code {
            width: 250px;
            height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 16px;
        }

        .qr-code img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .qr-code-placeholder {
            width: 250px;
            height: 250px;
            background: var(--grey-light);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            color: #999;
            font-size: 48px;
        }

        .student-info {
            margin-top: 24px;
            padding: 20px;
            background: var(--white-smoke);
            border-radius: 16px;
            text-align: left;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid var(--grey);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            width: 100px;
            font-weight: 500;
            color: #666;
            font-size: 13px;
        }

        .info-value {
            flex: 1;
            color: #333;
            font-size: 13px;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--pink);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--grey);
            color: #666;
        }

        .btn-secondary:hover {
            background: var(--grey-dark);
            color: white;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #ffebee;
            color: #f44336;
            border: 1px solid #ffcdd2;
        }

        .btn-danger:hover {
            background: #f44336;
            color: white;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #e8f5e9;
            color: #4caf50;
            border: 1px solid #c8e6c9;
        }

        .btn-success:hover {
            background: #4caf50;
            color: white;
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--grey);
            transition: all 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--pink);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--grey-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .instruction-card {
            background: var(--white-smoke);
            border-radius: 16px;
            padding: 24px;
            margin-top: 24px;
        }

        .instruction-title {
            font-weight: 600;
            margin-bottom: 16px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .instruction-title i {
            color: var(--pink);
        }

        .instruction-list {
            list-style: none;
            padding: 0;
        }

        .instruction-list li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #666;
            font-size: 13px;
            border-bottom: 1px solid var(--grey);
        }

        .instruction-list li:last-child {
            border-bottom: none;
        }

        .instruction-list li i {
            width: 24px;
            color: var(--pink);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #4caf50;
            border: 1px solid #c8e6c9;
        }

        .alert-warning {
            background: #fff3e0;
            color: #ff9800;
            border: 1px solid #ffe0b2;
        }

        .alert-danger {
            background: #ffebee;
            color: #f44336;
            border: 1px solid #ffcdd2;
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
            .qr-container {
                padding: 16px;
            }
            
            .qr-header {
                padding: 20px;
            }
            
            .qr-header h2 {
                font-size: 18px;
            }
            
            .qr-body {
                padding: 24px;
            }
            
            .qr-code {
                width: 180px;
                height: 180px;
            }
            
            .qr-code-placeholder {
                width: 180px;
                height: 180px;
                font-size: 32px;
            }
            
            .qr-code-wrapper {
                padding: 12px;
            }
            
            .info-row {
                flex-direction: column;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 4px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
        }

        @media print {
            .sidebar, .navbar, .action-buttons, .stats-grid, .instruction-card, .qr-header .back-link {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .qr-container {
                padding: 0 !important;
            }
            
            .qr-card {
                border: none !important;
            }
            
            .qr-header {
                background: white !important;
                color: black !important;
            }
            
            .qr-code-wrapper {
                box-shadow: none !important;
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
        <div class="qr-container">
            <!-- Success Message -->
            <?php if($show_regenerated): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> QR Code has been regenerated successfully!
                </div>
            <?php endif; ?>
            <?php if($show_regenerate_failed): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> QR regeneration failed. Please check internet/server connectivity and try again.
                </div>
            <?php endif; ?>
            
            <!-- Warning if QR needs regeneration -->
            <?php if($needs_regeneration): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $has_real_student_record ? 'Your QR code file is missing. Please regenerate it.' : 'Your student record is not linked yet, so a QR code cannot be generated.'; ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $scan_count; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-qrcode"></i> Times Scanned
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="qrSize">250x250</div>
                    <div class="stat-label">
                        <i class="fas fa-expand"></i> QR Size
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-check-circle" style="font-size: 24px;"></i>
                    </div>
                    <div class="stat-label">
                        <i class="fas fa-clock"></i> Valid for Whole School Year
                    </div>
                </div>
            </div>
            
            <!-- QR Code Card -->
            <div class="qr-card">
                <div class="qr-header">
                    <h2><i class="fas fa-qrcode"></i> My QR Code</h2>
                    <p>Show this QR code to the scanner to mark your attendance</p>
                </div>
                <div class="qr-body">
                    <div class="qr-code-wrapper">
                        <div class="qr-code">
                            <?php if($qr_image_exists && $qr_image_url): ?>
                                <img src="../<?php echo $qr_image_url; ?>" alt="Student QR Code" id="qrImage">
                            <?php elseif($display_qr_data): ?>
                                <img src="https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=<?php echo urlencode($display_qr_data); ?>&choe=UTF-8" alt="Student QR Code" id="qrImage">
                            <?php else: ?>
                                <div class="qr-code-placeholder">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Student Information -->
                    <div class="student-info">
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-user"></i> Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_record['fullname']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-id-card"></i> LRN</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_record['lrn']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-layer-group"></i> Grade & Section</div>
                            <div class="info-value"><?php echo htmlspecialchars($student_record['level_name'] . ' - ' . $student_record['section_name']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-barcode"></i> QR ID</div>
                            <div class="info-value"><code><?php echo htmlspecialchars($display_qr_data); ?></code></div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print"></i> Print QR Code
                        </button>
                        <a href="?download=1" class="btn btn-success" style="text-decoration: none; <?php echo !$has_real_student_record ? 'pointer-events:none; opacity:0.6;' : ''; ?>">
                            <i class="fas fa-download"></i> Download PNG
                        </a>
                        <button class="btn btn-danger" onclick="confirmRegenerate()" <?php echo !$has_real_student_record ? 'disabled' : ''; ?>>
                            <i class="fas fa-sync-alt"></i> Regenerate QR
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- How QR Code Works -->
            <div class="instruction-card" style="background: #e3f2fd;">
                <div class="instruction-title">
                    <i class="fas fa-info-circle"></i>
                    <span>How QR Code Scanning Works</span>
                </div>
                <ul class="instruction-list">
                    <li>
                        <i class="fas fa-qrcode"></i>
                        <span>Your QR code contains your unique Student ID number</span>
                    </li>
                    <li>
                        <i class="fas fa-camera"></i>
                        <span>When scanned, the system uses your Student ID to verify your identity</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>The scanner will automatically record your attendance for the current time period</span>
                    </li>
                    <li>
                        <i class="fas fa-shield-alt"></i>
                        <span>Your QR code is unique to you and cannot be used by others</span>
                    </li>
                </ul>
            </div>
            
            <!-- Instructions Card -->
            <div class="instruction-card">
                <div class="instruction-title">
                    <i class="fas fa-info-circle"></i>
                    <span>How to Use Your QR Code</span>
                </div>
                <ul class="instruction-list">
                    <li>
                        <i class="fas fa-mobile-alt"></i>
                        <span>Open your QR code on your phone or print it out</span>
                    </li>
                    <li>
                        <i class="fas fa-qrcode"></i>
                        <span>Go to any attendance scanner station</span>
                    </li>
                    <li>
                        <i class="fas fa-camera"></i>
                        <span>Hold your QR code in front of the scanner</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Wait for confirmation that attendance has been recorded</span>
                    </li>
                    <li>
                        <i class="fas fa-chart-line"></i>
                        <span>Check your attendance log to verify</span>
                    </li>
                </ul>
            </div>
            
            <!-- Security Note Card -->
            <div class="instruction-card" style="margin-top: 16px; background: #e8f5e9;">
                <div class="instruction-title">
                    <i class="fas fa-shield-alt"></i>
                    <span>Security Note</span>
                </div>
                <ul class="instruction-list">
                    <li>
                        <i class="fas fa-lock"></i>
                        <span>Your QR code is unique and contains your Student ID</span>
                    </li>
                    <li>
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Do not share your QR code with others</span>
                    </li>
                    <li>
                        <i class="fas fa-sync-alt"></i>
                        <span>If you suspect your QR code is compromised, regenerate it immediately</span>
                    </li>
                    <li>
                        <i class="fas fa-clock"></i>
                        <span>Your QR code remains valid throughout the school year</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Regenerate Confirmation Modal -->
    <div id="regenerateModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Regenerate QR Code?</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to regenerate your QR code?</p>
                <p class="text-muted" style="font-size: 12px; margin-top: 8px;">
                    <i class="fas fa-info-circle"></i> This will refresh your saved QR image file. Use the latest QR shown on this page for attendance.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <a href="?regenerate=1" class="btn btn-danger" style="text-decoration: none;">Yes, Regenerate</a>
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
        
        // Modal functions
        function confirmRegenerate() {
            document.getElementById('regenerateModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('regenerateModal').classList.remove('show');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('regenerateModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Update QR size display (dynamic)
        function updateQRSize() {
            const qrImage = document.getElementById('qrImage');
            if(qrImage) {
                const size = qrImage.offsetWidth;
                document.getElementById('qrSize').innerHTML = size + 'x' + size;
            } else {
                setTimeout(updateQRSize, 100);
            }
        }
        
        setTimeout(updateQRSize, 100);
        window.addEventListener('resize', updateQRSize);
    </script>
</body>
</html>
