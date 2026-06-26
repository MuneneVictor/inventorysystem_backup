<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

// Only inventory_admin and super_admin can access
if(!in_array($_SESSION['role'], ['super_admin', 'inventory_admin','manager'])){
    die("Access denied!");
}

$error = "";
$success = "";

// Fetch categories for dropdown
$categoriesStmt = $conn->query("SELECT * FROM categories");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $serial_number = trim($_POST['serial_number']);
    $category_id = $_POST['category_id'];
    $model_name = trim($_POST['model_name']);
    $processor = trim($_POST['processor']);
    $graphics = trim($_POST['graphics']) ?: 'None';
    $ram = $_POST['ram'];
    $storage_type = $_POST['storage_type'];
    $storage_capacity = $_POST['storage_capacity'];
    $status = "In Stock"; // Automatically set to In Stock when adding a new device
    $branch = $_POST['branch'] ?: null;
    
    // Get the selected category name to check if it's Laptop or AIO
    $category_name = "";
    foreach($categories as $cat) {
        if($cat['id'] == $category_id) {
            $category_name = $cat['category_name'];
            break;
        }
    }
    
    // Set touch value: if category is Laptop or AIO, use the selected value, otherwise use "N/A"
    if($category_name === 'Laptop' || $category_name === 'AIO') {
        $touch = $_POST['touch'] ?? null;
    } else {
        $touch = 'N/A';
    }
    
    // NEW: Get cargo number and condition
    $cargo_number = trim($_POST['cargo_number']) ?: null;
    $device_condition = trim($_POST['device_condition']) ?: null;
    
    // Get logged-in user ID for added_by
    $added_by = $_SESSION['user_id'];

    // Check for duplicate serial number
    $stmt = $conn->prepare("SELECT COUNT(*) FROM devices WHERE serial_number = :sn");
    $stmt->execute(['sn' => $serial_number]);
    if($stmt->fetchColumn() > 0){
        $error = "A device with this serial number already exists!";
    } else {
        // Insert device including added_by, cargo_number, and device_condition
        $insert = $conn->prepare("
            INSERT INTO devices
            (serial_number, category_id, model_name, processor, graphics, ram, storage_type, storage_capacity, touch, status, added_by, cargo_number, device_condition, branch)
            VALUES
            (:serial_number, :category_id, :model_name, :processor, :graphics, :ram, :storage_type, :storage_capacity, :touch, :status, :added_by, :cargo_number, :device_condition, :branch)
        ");
        $insert->execute([
            'serial_number' => $serial_number,
            'category_id' => $category_id,
            'model_name' => $model_name,
            'processor' => $processor,
            'graphics' => $graphics,
            'ram' => $ram,
            'storage_type' => $storage_type,
            'storage_capacity' => $storage_capacity,
            'touch' => $touch,
            'status' => $status,
            'added_by' => $added_by,
            'cargo_number' => $cargo_number,
            'device_condition' => $device_condition,
            'branch' => $branch
        ]);

        // Log activity
        $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (:uid, 'Added device', :details)");
        $log->execute(['uid'=>$_SESSION['user_id'], 'details'=>"Added device SN: $serial_number" . ($cargo_number ? ", Cargo: $cargo_number" : "")]);

        $success = "Device added successfully!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Add Device | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- QR Code Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
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

        /* Main Content Area */
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

        /* Page Header */
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

        /* Form Container */
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

        /* Form Grid */
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
            position: relative; /* For absolute positioning of scan button */
        }

        .form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            padding-right: 70px; /* Make room for the scan button on mobile */
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

        /* Serial Input Special */
        .serial-input {
            font-family: 'Courier New', monospace;
            font-size: 1rem !important;
            letter-spacing: 0.5px;
            background: var(--gray-50) !important;
        }

        /* Scan Button – visible only on mobile/tablet */
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

        /* Hide scan button on screens wider than 1024px (desktops) */
        @media (min-width: 1025px) {
            .scan-btn-wrapper {
                display: none !important;
            }
            .form-group label {
                padding-right: 0; /* reset padding on desktop */
            }
        }

        /* On mobile, adjust label padding to accommodate the button */
        @media (max-width: 1024px) {
            .form-group label {
                padding-right: 70px;
            }
        }

        /* Scanner Overlay */
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

        /* Alert Messages */
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

        /* Note Box */
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

        /* Submit Button */
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

        /* Footer */
        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

        /* Responsive */
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
                padding-right: 60px; /* adjust for smaller screens */
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

<div class="main-content">
    <!-- Page Header (unchanged) -->
    <div class="page-header">
        <h1>
            <i class="fas fa-plus-circle"></i>
            Add Device
        </h1>
        <div class="breadcrumb">
            <?php if($_SESSION['role'] === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>       
            <?php endif; ?>
            <?php if($_SESSION['role'] === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <?php if($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="device_list.php">Devices</a>
            <span> / </span>
            <span>Add Device</span>
        </div>
    </div>

    <!-- Form Container -->
    <div class="form-container">
        <div class="form-header">
            <h2>
                <i class="fas fa-laptop"></i>
                Device Information
            </h2>
        </div>
        
        <div class="form-body">
            <!-- Alerts -->
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="deviceForm">
                <div class="form-grid">
                    <!-- Serial Number -->
                    <div class="form-group full-width">
                        <label>
                            <span>
                                Serial Number <span class="required">*</span>
                                <span class="optional">(Scan or type)</span>
                            </span>
                        </label>
                        <!-- Scan button wrapper – visible only on mobile/tablet -->
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

                    <!-- Cargo Number -->
                    <div class="form-group">
                        <label>Cargo Number</label>
                        <input type="text" 
                               name="cargo_number" 
                               id="cargo_number" 
                               placeholder="e.g., CX37"
                               autocomplete="off"
                               autocapitalize="characters">
                    </div>

                    <!-- Device Condition -->
                    <div class="form-group">
                        <label>Device Condition</label>
                        <select name="device_condition">
                            <option value="">-- Select Condition --</option>
                            <option value="New">New</option>
                            <option value="Refurbished">Refurbished</option>
                        </select>
                    </div>

                    <!-- Category -->
                    <div class="form-group">
                        <label>Category <span class="required">*</span></label>
                        <select name="category_id" id="category_id" onchange="toggleTouch()" required>
                            <option value="">Select Category</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
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

                    <!-- Model Name -->
                    <div class="form-group">
                        <label>Model Name <span class="required">*</span></label>
                        <input type="text" name="model_name" required>
                    </div>

                    <!-- Processor -->
                    <div class="form-group">
                        <label>Processor <span class="required">*</span></label>
                        <input type="text" name="processor" required placeholder="e.g., Intel Core i5-1135G7">
                    </div>

                    <!-- Graphics -->
                    <div class="form-group">
                        <label>Graphics</label>
                        <input type="text" name="graphics" placeholder="e.g., Intel Iris Xe">
                    </div>

                    <!-- RAM -->
                    <div class="form-group">
                        <label>RAM (GB) <span class="required">*</span></label>
                        <input type="number" name="ram" min="1" required>
                    </div>

                    <!-- Storage Type -->
                    <div class="form-group">
                        <label>Storage Type <span class="required">*</span></label>
                        <select name="storage_type" required>
                            <option value="SSD">SSD</option>
                            <option value="HDD">HDD</option>
                        </select>
                    </div>

                    <!-- Storage Capacity -->
                    <div class="form-group">
                        <label>Storage Capacity (GB) <span class="required">*</span></label>
                        <input type="number" name="storage_capacity" min="1" required>
                    </div>

                    <!-- Touch Screen (Hidden by default) -->
                    <div id="touch_div" class="form-group" style="display:none;">
                        <label>Touch Screen</label>
                        <select name="touch">
                            <option value="Touch">Touch</option>
                            <option value="Non-touch">Non-touch</option>
                        </select>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="device_list.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Device
                    </button>
                </div>
            </form>

            <!-- Note Box -->
            <div class="note-box">
                <i class="fas fa-info-circle"></i>
                <p><strong>Note:</strong> All newly added devices are automatically set to <strong>"In Stock"</strong> status.</p>
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

    // --- SCANNER-STYLE DOUBLE BEEP (identical to sell_device.php) ---
    function playBeep() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }

            // First beep
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

            // Second beep after short pause
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
            // Silently fail if audio not supported
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
        // Play scanner beep
        playBeep();

        // Fill the serial number input
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

    // Clean up
    window.addEventListener('beforeunload', function() {
        if (html5QrCode && scannerActive) {
            html5QrCode.stop().catch(() => {});
        }
    });

    // --- Existing functions from original file ---
    function toggleTouch() {
        var categorySelect = document.getElementById('category_id');
        var touchDiv = document.getElementById('touch_div');
        var selectedCategory = categorySelect.options[categorySelect.selectedIndex].text;
        if(selectedCategory === 'Laptop' || selectedCategory === 'AIO'){
            touchDiv.style.display = 'block';
        } else {
            touchDiv.style.display = 'none';
        }
    }
    
    function formatSerialNumber() {
        var serialInput = document.getElementById('serial_number');
        serialInput.value = serialInput.value.trim().toUpperCase();
    }
    
    function formatCargoNumber() {
        var cargoInput = document.getElementById('cargo_number');
        cargoInput.value = cargoInput.value.trim().toUpperCase();
    }
    
    // Focus on serial number input when page loads
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('serial_number').focus();
        
        // Add blur event handlers
        document.getElementById('serial_number').addEventListener('blur', formatSerialNumber);
        document.getElementById('cargo_number').addEventListener('blur', formatCargoNumber);
    });
    
    // Scanner detection (original)
    (function() {
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
            if(!isTyping) {
                setTimeout(function() {
                    serialInput.value = serialInput.value.trim().toUpperCase();
                }, 50);
            }
        });
    })();
    
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