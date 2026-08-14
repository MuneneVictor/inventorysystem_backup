<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only inventory_admin, super_admin, and manager can access
if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'])) {
    die("Access denied!");
}

$error = "";
$success = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serial_number = trim($_POST['serial_number']);
    $model         = trim($_POST['model']);
    $capacity      = (int) $_POST['capacity'];
    $branch        = $_POST['branch'];
    $price         = !empty($_POST['price']) ? (float) $_POST['price'] : null;
    $ups_condition = $_POST['ups_condition'] ?? 'New';
    $added_by      = (int) $_SESSION['user_id'];
    $status        = 'instock'; // default

    // Validation
    if (empty($serial_number) || empty($model) || $capacity <= 0 || empty($branch)) {
        $error = "Serial Number, Model, Capacity (positive integer), and Branch are required.";
    } else {
        // Check for duplicate serial number
        $stmt = $conn->prepare("SELECT COUNT(*) FROM ups WHERE serial_number = :sn");
        $stmt->execute(['sn' => $serial_number]);
        if ($stmt->fetchColumn() > 0) {
            $error = "A UPS with this serial number already exists!";
        } else {
            try {
                $insert = $conn->prepare("
                    INSERT INTO ups 
                    (serial_number, model, capacity, status, added_by, price, branch, ups_condition)
                    VALUES (:serial_number, :model, :capacity, :status, :added_by, :price, :branch, :ups_condition)
                ");
                $insert->execute([
                    'serial_number' => $serial_number,
                    'model'         => $model,
                    'capacity'      => $capacity,
                    'status'        => $status,
                    'added_by'      => $added_by,
                    'price'         => $price,
                    'branch'        => $branch,
                    'ups_condition' => $ups_condition
                ]);

                // Log activity
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (:uid, 'Added UPS', :details)");
                $log->execute([
                    'uid'     => $_SESSION['user_id'],
                    'details' => "Added UPS SN: $serial_number (Model: $model, Capacity: {$capacity}VA, Branch: $branch)"
                ]);

                $success = "UPS added successfully!";
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Add UPS | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- QR Code Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        /* ===== EXACT SAME CSS AS add_device.php ===== */
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
            --primary-dark: #0f3a1e;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --font-sans: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
            overflow-x: hidden;
        }

        .main-content {
            padding: 2rem 2rem 1rem;
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;
            background: var(--gray-100);
            transition: margin-left 0.3s ease, width 0.3s ease, padding 0.3s ease;
            overflow-x: hidden;
            max-width: 100%;
        }

        .page-header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .page-header h1 {
            font-size: 1.75rem;
            color: var(--gray-800);
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i {
            color: var(--primary);
            font-size: 1.75rem;
        }

        .breadcrumb {
            color: var(--gray-500);
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .form-container {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .form-header {
            background: var(--gray-50);
            padding: 1.25rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .form-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-header h2 i {
            color: var(--primary);
        }

        .form-body {
            padding: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: span 2;
            position: relative;
        }

        .form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            padding-right: 70px;
        }

        .form-group label .required {
            color: #dc2626;
            margin-left: 0.25rem;
        }

        .form-group label .optional {
            color: var(--gray-400);
            font-weight: normal;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }

        .form-group input,
        .form-group select {
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            background: white;
            width: 100%;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

        .form-group input::placeholder {
            color: var(--gray-400);
        }

        .serial-input {
            font-family: 'Courier New', monospace;
            font-size: 1rem !important;
            letter-spacing: 0.5px;
            background: var(--gray-50) !important;
        }

        .scan-btn-wrapper {
            position: absolute;
            top: -2px;
            right: 0;
            z-index: 5;
        }

        .scan-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            font-weight: 500;
            transition: background 0.2s;
            white-space: nowrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        }

        .scan-btn:hover {
            background: #1d4ed8;
        }

        .scan-btn i {
            font-size: 0.9rem;
        }

        @media (min-width: 1025px) {
            .scan-btn-wrapper {
                display: none !important;
            }
            .form-group label {
                padding-right: 0;
            }
        }

        @media (max-width: 1024px) {
            .form-group label {
                padding-right: 70px;
            }
        }

        .scanner-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .scanner-overlay.active {
            display: flex;
        }

        .scanner-box {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            max-width: 500px;
            width: 95%;
            text-align: center;
            position: relative;
        }

        .scanner-box .close-btn {
            position: absolute;
            top: 10px; right: 15px;
            font-size: 1.8rem;
            cursor: pointer;
            background: none;
            border: none;
            color: var(--gray-500);
        }

        .scanner-box .close-btn:hover {
            color: var(--gray-800);
        }

        .scanner-box #reader {
            width: 100%;
            min-height: 300px;
            margin: 1rem 0;
        }

        .scanner-box p {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 480px) {
            .scanner-box {
                max-width: 100%;
                border-radius: 0;
                height: 100vh;
                padding-top: 3rem;
            }
            .scanner-box #reader {
                height: 70vh;
                min-height: 200px;
            }
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert i {
            font-size: 1.25rem;
        }

        .note-box {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid var(--gray-200);
        }

        .note-box i {
            color: var(--primary);
            font-size: 1.25rem;
        }

        .note-box p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .form-actions {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--font-sans);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-50);
        }

        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 1.5rem 1rem 1rem !important;
                padding-top: 5rem !important;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem 0.75rem 0.75rem !important;
                padding-top: 4.5rem !important;
            }
            .page-header h1 {
                font-size: 1.25rem;
            }
            .page-header {
                padding: 1rem 1.25rem;
            }
            .form-body {
                padding: 1.5rem;
            }
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .form-group.full-width {
                grid-column: span 1;
            }
            .form-actions {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            .form-header {
                padding: 1rem 1.5rem;
            }
            .scan-btn {
                font-size: 0.7rem;
                padding: 0.3rem 0.6rem;
            }
            .form-group label {
                padding-right: 60px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem 0.5rem 0.5rem !important;
                padding-top: 4rem !important;
            }
            .form-body {
                padding: 1rem;
            }
            .page-header h1 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-plus-circle"></i>
            Add UPS
        </h1>
        <div class="breadcrumb">
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="../dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="ups_instock.php">UPS Stock</a>
            <span> / </span>
            <span>Add UPS</span>
        </div>
    </div>

    <!-- Form Container -->
    <div class="form-container">
        <div class="form-header">
            <h2>
                <i class="fas fa-bolt"></i>
                UPS Information
            </h2>
        </div>

        <div class="form-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="upsForm">
                <div class="form-grid">
                    <!-- Serial Number -->
                    <div class="form-group full-width">
                        <label>
                            <span>
                                Serial Number <span class="required">*</span>
                                <span class="optional">(Scan or type)</span>
                            </span>
                        </label>
                        <div class="scan-btn-wrapper">
                            <button type="button" class="scan-btn" onclick="openScanner()">
                                <i class="fas fa-camera"></i> Scan
                            </button>
                        </div>
                        <input type="text"
                               name="serial_number"
                               id="serial_number"
                               class="serial-input"
                               required
                               placeholder="Scan barcode or type serial number"
                               autocomplete="off"
                               autocapitalize="characters">
                    </div>

                    <!-- Model -->
                    <div class="form-group">
                        <label>Model <span class="required">*</span></label>
                        <input type="text" name="model" required placeholder="e.g., MECCER UPS, APC Back-UPS">
                    </div>

                    <!-- Capacity (VA) -->
                    <div class="form-group">
                        <label>Capacity (VA) <span class="required">*</span></label>
                        <input type="number" name="capacity" min="1" required placeholder="e.g., 2600">
                    </div>

                    <!-- Branch -->
                    <div class="form-group">
                        <label>Branch <span class="required">*</span></label>
                        <select name="branch" required>
                            <option value="">-- Select Branch --</option>
                            <option value="KIMATHI">KIMATHI</option>
                            <option value="MOI">MOI</option>
                        </select>
                    </div>

                    <!-- UPS Condition -->
                    <div class="form-group">
                        <label>UPS Condition</label>
                        <select name="ups_condition">
                            <option value="New" selected>New</option>
                            <option value="Ex-UK">Ex-UK</option>
                            <option value="Refurbished">Refurbished</option>
                        </select>
                    </div>

                    <!-- Price (optional) -->
                    <div class="form-group">
                        <label>Price (KES) <span class="optional">(optional)</span></label>
                        <input type="number" name="price" step="0.01" min="0" placeholder="Enter price if known">
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="ups_instock.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add UPS
                    </button>
                </div>
            </form>

            <!-- Note Box -->
            <div class="note-box">
                <i class="fas fa-info-circle"></i>
                <p><strong>Note:</strong> All newly added UPS units are automatically set to <strong>"In Stock"</strong> status.</p>
            </div>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<!-- Scanner Overlay Modal -->
<div class="scanner-overlay" id="scannerOverlay">
    <div class="scanner-box">
        <button class="close-btn" onclick="closeScanner()">&times;</button>
        <h3 style="margin-bottom:0.5rem;"><i class="fas fa-camera"></i> Scan Serial Number</h3>
        <p>Position the barcode/QR code in front of the camera</p>
        <div id="reader"></div>
        <button class="btn btn-secondary" onclick="closeScanner()" style="margin-top:0.5rem;">Cancel</button>
    </div>
</div>

<script>
    let html5QrCode = null;
    let scannerActive = false;

    // --- Beep function (same as add_device) ---
    function playBeep() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            const osc1 = audioCtx.createOscillator();
            const gain1 = audioCtx.createGain();
            osc1.connect(gain1);
            gain1.connect(audioCtx.destination);
            osc1.frequency.value = 1200;
            osc1.type = 'square';
            gain1.gain.setValueAtTime(0.25, audioCtx.currentTime);
            gain1.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.08);
            osc1.start(audioCtx.currentTime);
            osc1.stop(audioCtx.currentTime + 0.08);

            const osc2 = audioCtx.createOscillator();
            const gain2 = audioCtx.createGain();
            osc2.connect(gain2);
            gain2.connect(audioCtx.destination);
            osc2.frequency.value = 1200;
            osc2.type = 'square';
            gain2.gain.setValueAtTime(0.25, audioCtx.currentTime + 0.12);
            gain2.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.2);
            osc2.start(audioCtx.currentTime + 0.12);
            osc2.stop(audioCtx.currentTime + 0.2);
        } catch (e) {
            console.log('Beep not supported');
        }
    }

    function openScanner() {
        const overlay = document.getElementById('scannerOverlay');
        overlay.classList.add('active');
        setTimeout(() => {
            if (!scannerActive) {
                startScanner();
            }
        }, 500);
    }

    function closeScanner() {
        const overlay = document.getElementById('scannerOverlay');
        overlay.classList.remove('active');
        if (html5QrCode && scannerActive) {
            html5QrCode.stop().then(() => {
                html5QrCode.clear();
                scannerActive = false;
            }).catch(() => { scannerActive = false; });
        }
    }

    function startScanner() {
        const readerElement = document.getElementById('reader');
        if (!readerElement) {
            alert('Scanner error: reader element missing.');
            closeScanner();
            return;
        }

        if (typeof Html5Qrcode === 'undefined') {
            alert('Scanner library not loaded. Please check your internet connection and refresh.');
            closeScanner();
            return;
        }

        readerElement.style.width = '100%';
        readerElement.style.minHeight = '300px';

        html5QrCode = new Html5Qrcode("reader");
        const config = {
            fps: 15,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        };

        html5QrCode.start(
            { facingMode: "environment" },
            config,
            onScanSuccess,
            onScanError
        ).then(() => {
            scannerActive = true;
        }).catch(err => {
            console.error('Scanner start error (environment):', err);
            html5QrCode.start(
                { facingMode: "user" },
                config,
                onScanSuccess,
                onScanError
            ).then(() => {
                scannerActive = true;
            }).catch(err2 => {
                console.error('Scanner start error (user):', err2);
                let msg = 'Unable to access camera. Please ensure camera permissions are granted.';
                if (window.location.protocol === 'http:') {
                    msg += ' Your site is using HTTP; camera access may be blocked. Try using HTTPS.';
                }
                alert(msg);
                closeScanner();
            });
        });
    }

    function onScanSuccess(decodedText, decodedResult) {
        playBeep();
        const serialInput = document.getElementById('serial_number');
        if (serialInput) {
            serialInput.value = decodedText.trim().toUpperCase();
            serialInput.dispatchEvent(new Event('input'));
        }
        closeScanner();
    }

    function onScanError(errorMessage) {
        // ignore
    }

    window.addEventListener('beforeunload', function() {
        if (html5QrCode && scannerActive) {
            html5QrCode.stop().catch(() => {});
        }
    });

    // --- Utility functions ---
    function formatSerialNumber() {
        var serialInput = document.getElementById('serial_number');
        serialInput.value = serialInput.value.trim().toUpperCase();
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('serial_number').focus();

        document.getElementById('serial_number').addEventListener('blur', formatSerialNumber);

        // Auto-uppercase for serial
        const serialInput = document.getElementById('serial_number');
        let isTyping = false;
        let typingTimer;

        serialInput.addEventListener('keydown', function(e) {
            clearTimeout(typingTimer);
            isTyping = true;
            typingTimer = setTimeout(function() {
                isTyping = false;
            }, 500);
        });

        serialInput.addEventListener('input', function() {
            if (!isTyping) {
                setTimeout(function() {
                    serialInput.value = serialInput.value.trim().toUpperCase();
                }, 50);
            }
        });
    });

    // Mobile responsive adjustments
    document.addEventListener('DOMContentLoaded', function() {
        function adjustMainContent() {
            const mainContent = document.querySelector('.main-content');
            const sidebar = document.querySelector('.sidebar');

            if (window.innerWidth <= 1200) {
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.width = '100%';
                    mainContent.style.paddingTop = '5rem';
                }
            } else {
                if (mainContent && sidebar) {
                    mainContent.style.marginLeft = '260px';
                    mainContent.style.width = 'calc(100% - 260px)';
                    mainContent.style.paddingTop = '';
                }
            }
        }

        adjustMainContent();
        window.addEventListener('resize', adjustMainContent);
        window.addEventListener('orientationchange', adjustMainContent);
    });
</script>

</body>
</html>