<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// No login required - public access for scanning
$is_logged_in = isLoggedIn();
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>QR/RFID Scanner - Smart Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--grey-light);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        /* Full Width Navbar */
        .navbar {
            background: var(--white);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--grey);
            position: sticky;
            top: 0;
            z-index: 100;
            width: 100%;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .nav-back {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: var(--pink);
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
            text-decoration: none;
        }

        .nav-back:hover {
            background: var(--grey-light);
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

        /* Scan Notice */
        .scan-notice {
            background: linear-gradient(135deg, var(--pink), var(--pink-dark));
            color: white;
            padding: 12px 24px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            position: relative;
        }

        .scan-notice i {
            margin-right: 8px;
        }

        /* Container */
        .scanner-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        /* Grid Layout */
        .scanner-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            overflow: hidden;
            border: 1px solid var(--grey);
        }

        .card-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--grey);
            font-weight: 500;
            font-size: 15px;
            color: #666;
            background: var(--white);
        }

        .card-header i {
            color: var(--pink);
            margin-right: 8px;
            width: 20px;
        }

        .card-body {
            padding: 20px;
        }

        /* Scanner Reader */
        #reader {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            min-height: 300px;
        }

        #reader video {
            border-radius: 16px;
            width: 100%;
            height: auto;
        }

        #reader__dashboard_section_csr {
            display: none;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #888;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--grey);
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
            background: var(--white-smoke);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--pink);
            background: var(--white);
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--pink);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Attendance List */
        .attendance-list {
            max-height: 450px;
            overflow-y: auto;
        }

        .attendance-list::-webkit-scrollbar {
            width: 4px;
        }

        .attendance-list::-webkit-scrollbar-track {
            background: var(--grey-light);
            border-radius: 4px;
        }

        .attendance-list::-webkit-scrollbar-thumb {
            background: var(--pink);
            border-radius: 4px;
        }

        .attendance-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--grey);
        }

        .attendance-item:last-child {
            border-bottom: none;
        }

        .attendance-info {
            flex: 1;
        }

        .attendance-name {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 2px;
        }

        .attendance-meta {
            font-size: 11px;
            color: #999;
        }

        .attendance-status {
            text-align: right;
        }

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

        .badge-warning {
            background: #fff3e0;
            color: #ff9800;
        }

        .badge-info {
            background: #e3f2fd;
            color: #2196f3;
        }

        .time-text {
            font-size: 11px;
            color: #999;
            margin-top: 2px;
        }

        /* Toast Message */
        .toast-message {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 24px;
            border-radius: 48px;
            font-size: 13px;
            font-weight: 500;
            z-index: 1000;
            animation: slideUp 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .toast-success {
            background: #4caf50;
            color: white;
        }

        .toast-danger {
            background: #f44336;
            color: white;
        }

        .toast-info {
            background: #2196f3;
            color: white;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Scanner Result */
        .scanner-result {
            margin-top: 16px;
            text-align: center;
        }

        /* Camera placeholder */
        .camera-placeholder {
            text-align: center;
            padding: 60px 20px;
            background: var(--grey-light);
            border-radius: 16px;
            color: #999;
        }

        .camera-placeholder i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        @media (max-width: 768px) {
            .scanner-container {
                padding: 16px;
            }
            
            .scanner-grid {
                gap: 16px;
            }
            
            .navbar {
                padding: 12px 16px;
            }
            
            .nav-title {
                font-size: 14px;
            }
            
            .scan-notice {
                padding: 10px 16px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Full Width Navbar -->
    <div class="navbar">
        <div class="nav-left">
            <a href="index.php" class="nav-back">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="nav-title">
                <i class="fas fa-qrcode"></i> Attendance Scanner
            </div>
        </div>
    </div>

    <!-- Scan Notice Banner -->
    <div class="scan-notice">
        <i class="fas fa-info-circle"></i>
        Please scan your QR code or enter RFID number to record attendance
    </div>

    <div class="scanner-container">
        <div class="scanner-grid">
            <!-- QR Scanner Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-camera"></i> Scan QR Code
                </div>
                <div class="card-body">
                    <div id="reader">
                        <div class="camera-placeholder">
                            <i class="fas fa-camera"></i>
                            <p>Initializing camera...</p>
                            <small>Please allow camera access when prompted</small>
                        </div>
                    </div>
                    <div id="scannerResult" class="scanner-result"></div>
                </div>
            </div>

            <!-- RFID Entry Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-rss"></i> Manual RFID Entry
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">RFID UID</label>
                        <input type="text" id="rfidInput" class="form-control" placeholder="Scan or type RFID number" autofocus>
                    </div>
                    <button onclick="submitRFID()" class="btn btn-primary">
                        <i class="fas fa-check"></i> Submit Attendance
                    </button>
                </div>
            </div>

            <!-- Today's Attendance Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> Today's Attendance
                </div>
                <div class="card-body attendance-list" id="attendanceList">
                    <div class="loading">
                        <i class="fas fa-spinner"></i> Loading...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let html5QrCode;
        let isScanning = true;
        let activeCameraId = null;

        function normalizeScannedValue(value) {
            const cleaned = String(value || '')
                .replace(/\uFEFF/g, '')
                .replace(/\r?\n/g, '')
                .trim();

            if (!cleaned) {
                return '';
            }

            // Support QR payloads stored as "data|qrcodes/file.png".
            if (cleaned.includes('|')) {
                const primaryValue = cleaned.split('|')[0].trim();
                if (primaryValue) {
                    return primaryValue;
                }
            }

            // Support payloads that contain only a QR image path.
            const normalizedPath = cleaned.replace(/\\/g, '/');
            const qrFileMatch = normalizedPath.match(/(?:^|\/)qr_student_(\d+)\.(?:png|jpg|jpeg|gif|webp|txt)$/i);
            if (qrFileMatch) {
                return qrFileMatch[1];
            }

            return cleaned;
        }

        async function stopScanner() {
            if (!html5QrCode) return;
            try {
                await html5QrCode.stop();
            } catch (error) {
                console.warn('Unable to stop scanner cleanly:', error);
            }
        }

        // Function to start QR scanner
        async function startScanner() {
            if (!isScanning) return;
            
            // Check if Html5Qrcode is available
            if (typeof Html5Qrcode === 'undefined') {
                console.error('Html5Qrcode library not loaded');
                document.getElementById('reader').innerHTML = `
                    <div class="camera-placeholder">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Scanner library failed to load</p>
                        <small>Please refresh the page</small>
                    </div>
                `;
                return;
            }
            
            try {
                if (!html5QrCode) {
                    html5QrCode = new Html5Qrcode("reader");
                }

                const cameras = await Html5Qrcode.getCameras();
                if (!cameras || cameras.length === 0) {
                    throw new Error('No cameras found on this device');
                }

                const preferredCamera = cameras.find(camera =>
                    /back|rear|environment/i.test(camera.label || '')
                ) || cameras[0];

                activeCameraId = preferredCamera.id;

                const config = { 
                    fps: 15,
                    qrbox: function(viewfinderWidth, viewfinderHeight) {
                        const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                        const boxSize = Math.floor(minEdge * 0.7);
                        return {
                            width: boxSize,
                            height: boxSize
                        };
                    },
                    aspectRatio: 1.0,
                    disableFlip: false,
                    rememberLastUsedCamera: true,
                    formatsToSupport: [ Html5QrcodeSupportedFormats.QR_CODE ]
                };
                
                const successCallback = async (decodedText, decodedResult) => {
                    const normalizedText = normalizeScannedValue(decodedText);
                    if (!normalizedText) {
                        return;
                    }

                    // Stop scanner on successful scan
                    isScanning = false;
                    await stopScanner();
                    
                    // Process attendance
                    const success = await processAttendance(normalizedText, 'qr');
                    
                    if (success) {
                        document.getElementById('scannerResult').innerHTML = `
                            <div class="toast-message toast-success" style="position: relative; bottom: auto; left: auto; transform: none; margin-top: 16px;">
                                <i class="fas fa-check-circle"></i> QR Scanned Successfully!
                            </div>
                        `;
                        
                        // Reload attendance list
                        loadLiveAttendance();
                    } else {
                        document.getElementById('scannerResult').innerHTML = `
                            <div class="toast-message toast-danger" style="position: relative; bottom: auto; left: auto; transform: none; margin-top: 16px;">
                                <i class="fas fa-exclamation-circle"></i> Failed to record attendance!
                            </div>
                        `;
                    }
                    
                    // Restart scanner after 3 seconds
                    setTimeout(() => {
                        document.getElementById('scannerResult').innerHTML = '';
                        isScanning = true;
                        startScanner();
                    }, 3000);
                };
                
                const errorCallback = (error) => {
                    // Silent error handling for scanning failures
                    if (error && !String(error).includes('NotFoundException')) {
                        console.log("Scan error:", error);
                    }
                };
                
                await html5QrCode.start(
                    activeCameraId,
                    config, 
                    successCallback,
                    errorCallback
                );
                
                console.log("Scanner started successfully");
                
            } catch (err) {
                console.error("Scanner error:", err);
                document.getElementById('reader').innerHTML = `
                    <div class="camera-placeholder">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Camera access denied or not available</p>
                        <small>Please ensure camera permission is allowed and try using HTTPS or localhost</small>
                        <button onclick="location.reload()" class="btn btn-primary" style="margin-top: 16px;">Retry</button>
                    </div>
                `;
            }
        }

        // Start scanner when DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Small delay to ensure DOM is ready
            setTimeout(startScanner, 500);
        });

        // Submit RFID
        function submitRFID() {
            const rfid = document.getElementById('rfidInput').value.trim();
            if (rfid) {
                processAttendance(rfid, 'rfid');
                document.getElementById('rfidInput').value = '';
                document.getElementById('rfidInput').focus();
            } else {
                showToast('Please enter RFID UID', 'danger');
            }
        }

        // Enter key for RFID input
        document.getElementById('rfidInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                submitRFID();
            }
        });

        // Process attendance
        async function processAttendance(identifier, method) {
            const normalizedIdentifier = normalizeScannedValue(identifier);
            if (!normalizedIdentifier) {
                showToast('Invalid QR or RFID value.', 'danger');
                return false;
            }

            return new Promise((resolve) => {
                $.ajax({
                    url: 'api/mark_attendance.php',
                    method: 'POST',
                    data: { 
                        identifier: normalizedIdentifier, 
                        method: method
                    },
                    dataType: 'json',
                    timeout: 10000,
                    success: function(response) {
                        showToast(response.message, response.success ? 'success' : 'danger');
                        if (response.success) {
                            loadLiveAttendance();
                            resolve(true);
                        } else {
                            resolve(false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", error);
                        showToast('Network error. Please try again.', 'danger');
                        resolve(false);
                    }
                });
            });
        }

        // Load live attendance
        function loadLiveAttendance() {
            $.ajax({
                url: 'api/get_attendance.php',
                method: 'GET',
                timeout: 10000,
                success: function(data) {
                    $('#attendanceList').html(data);
                },
                error: function() {
                    $('#attendanceList').html(`
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Failed to load attendance</p>
                        </div>
                    `);
                }
            });
        }

        // Show toast message
        function showToast(message, type) {
            // Remove existing toasts
            $('.toast-message').remove();
            
            const toast = $(`<div class="toast-message toast-${type}">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}
            </div>`);
            $('body').append(toast);
            setTimeout(() => {
                toast.fadeOut(300, function() { $(this).remove(); });
            }, 3000);
        }

        // Initial load
        loadLiveAttendance();
        
        // Refresh every 10 seconds
        setInterval(loadLiveAttendance, 10000);
    </script>
</body>
</html>
