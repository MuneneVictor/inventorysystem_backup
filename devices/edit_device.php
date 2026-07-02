<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only super_admin, inventory_admin, and manager can access
if(!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'])){
    die("Access denied!");
}

$serial_number = $_GET['sn'] ?? null;
if(!$serial_number){
    die("Serial number not provided!");
}

// Fetch device using prepared statement
$stmt = $conn->prepare("SELECT * FROM devices WHERE serial_number = :sn");
$stmt->execute(['sn' => $serial_number]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$device){
    die("Device not found!");
}

// Store original device data for comparison
$original_device = $device;

// Fetch categories using prepared statement
$categoriesStmt = $conn->prepare("SELECT * FROM categories ORDER BY category_name ASC");
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$error = "";
$success = "";

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $category_id = $_POST['category_id'];
    $model_name = trim($_POST['model_name']);
    $processor = trim($_POST['processor']);
    $graphics = trim($_POST['graphics']) ?: 'None';
    $ram = $_POST['ram'];
    $storage_type = $_POST['storage_type'];
    $storage_capacity = $_POST['storage_capacity'];
    $touch = ($_POST['touch'] ?? null);
    $device_condition = $_POST['device_condition'];
    
    // Status and branch cannot be edited
    $status = $device['status'];
    $branch = $device['branch'];

    // Track changes for logging
    $changes = [];
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update device table using prepared statement
        $stmt = $conn->prepare("UPDATE devices SET
            category_id = :category_id,
            model_name = :model_name,
            processor = :processor,
            graphics = :graphics,
            ram = :ram,
            storage_type = :storage_type,
            storage_capacity = :storage_capacity,
            touch = :touch,
            device_condition = :device_condition
            WHERE serial_number = :sn
        ");
        $stmt->execute([
            'category_id' => $category_id,
            'model_name' => $model_name,
            'processor' => $processor,
            'graphics' => $graphics,
            'ram' => $ram,
            'storage_type' => $storage_type,
            'storage_capacity' => $storage_capacity,
            'touch' => $touch,
            'device_condition' => $device_condition,
            'sn' => $serial_number
        ]);

        // Check for changes and log them
        if($original_device['category_id'] != $category_id) {
            $old_cat_stmt = $conn->prepare("SELECT category_name FROM categories WHERE id = :id");
            $old_cat_stmt->execute(['id' => $original_device['category_id']]);
            $old_category = $old_cat_stmt->fetchColumn();
            
            $new_cat_stmt = $conn->prepare("SELECT category_name FROM categories WHERE id = :id");
            $new_cat_stmt->execute(['id' => $category_id]);
            $new_category = $new_cat_stmt->fetchColumn();
            
            $changes[] = "Category: $old_category → $new_category";
        }
        
        if($original_device['model_name'] != $model_name) {
            $changes[] = "Model: {$original_device['model_name']} → $model_name";
        }
        
        if($original_device['processor'] != $processor) {
            $changes[] = "Processor: {$original_device['processor']} → $processor";
        }
        
        if($original_device['graphics'] != $graphics) {
            $old_graphics = $original_device['graphics'] ?: 'None';
            $new_graphics = $graphics ?: 'None';
            $changes[] = "Graphics: $old_graphics → $new_graphics";
        }
        
        if($original_device['ram'] != $ram) {
            $changes[] = "RAM: {$original_device['ram']}GB → {$ram}GB";
        }
        
        if($original_device['storage_type'] != $storage_type || $original_device['storage_capacity'] != $storage_capacity) {
            $old_storage = $original_device['storage_type'] . ' ' . $original_device['storage_capacity'] . 'GB';
            $new_storage = $storage_type . ' ' . $storage_capacity . 'GB';
            $changes[] = "Storage: $old_storage → $new_storage";
        }
        
        if($original_device['touch'] != $touch) {
            $old_touch = $original_device['touch'] ?? 'Not set';
            $new_touch = $touch ?? 'Not set';
            $changes[] = "Touch: $old_touch → $new_touch";
        }
        
        if($original_device['device_condition'] != $device_condition) {
            $changes[] = "Condition: {$original_device['device_condition']} → $device_condition";
        }

        // Activity log with detailed changes using prepared statement
        if(!empty($changes)) {
            $user_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Unknown';
            $details = "Device: $serial_number | Updated by: $user_name | Changes: " . implode(', ', $changes);
            
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) 
                VALUES (:uid, 'Edited device', :details, NOW())");
            $logStmt->execute([
                'uid' => $_SESSION['user_id'],
                'details' => $details
            ]);
        }

        $conn->commit();
        $success = "Device updated successfully!";
        
        // Refresh device info
        $stmt = $conn->prepare("SELECT * FROM devices WHERE serial_number = :sn");
        $stmt->execute(['sn' => $serial_number]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error updating device: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Edit Device | <?= htmlspecialchars($device['serial_number']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
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
            flex-wrap: wrap;
        }

        .page-header h1 i {
            color: var(--primary);
            font-size: 1.75rem;
        }

        .page-header h1 .serial-code {
            font-family: 'Courier New', monospace;
            background: var(--gray-100);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 1rem;
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

        /* Form Container */
        .form-container {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .form-header {
            background: var(--gray-50);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .form-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-header h3 i {
            color: var(--primary);
        }

        .form-body {
            padding: 1.5rem;
        }

        /* Readonly Info Box */
        .info-box {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-200);
        }

        .info-box p {
            margin: 0.5rem 0;
            font-size: 0.9rem;
        }

        .info-box p:first-child {
            margin-top: 0;
        }

        .info-box p:last-child {
            margin-bottom: 0;
        }

        .info-box strong {
            color: var(--gray-700);
            width: 100px;
            display: inline-block;
        }

        .info-box .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #d1fae5;
            color: #065f46;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .form-group label .required {
            color: #dc2626;
            margin-left: 0.25rem;
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
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

        .form-group input[type="number"] {
            -moz-appearance: textfield;
        }

        .form-group input[type="number"]::-webkit-inner-spin-button,
        .form-group input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Buttons */
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
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .form-actions {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
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

            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .form-group.full-width {
                grid-column: span 1;
            }

            .form-body {
                padding: 1.25rem;
            }

            .info-box strong {
                width: auto;
                display: block;
                margin-bottom: 0.25rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem 0.5rem 0.5rem !important;
                padding-top: 4rem !important;
            }

            .page-header h1 {
                font-size: 1.1rem;
            }

            .form-body {
                padding: 1rem;
            }
        }
    </style>
    <script>
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
        
        function confirmUpdate() {
            return confirm('Are you sure you want to update this device?');
        }
        
        window.onload = function() {
            toggleTouch();
        };
    </script>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-edit"></i>
            Edit Device
            <span class="serial-code"><?= htmlspecialchars($device['serial_number']) ?></span>
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
            <a href="view_device.php?sn=<?= urlencode($device['serial_number']) ?>">View Device</a>
            <span> / </span>
            <span>Edit Device</span>
        </div>
    </div>

    <!-- Alert Messages -->
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

    <!-- Form Container -->
    <div class="form-container">
        <div class="form-header">
            <h3>
                <i class="fas fa-laptop"></i>
                Device Information
            </h3>
        </div>
        <div class="form-body">
            <!-- Readonly Info -->
            <div class="info-box">
                <p><strong>Serial Number:</strong> <?= htmlspecialchars($device['serial_number']) ?></p>
                <p><strong>Status:</strong> <span class="status-badge"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($device['status']) ?></span> (Cannot be changed)</p>
                <p><strong>Branch:</strong> 
                    <span style="color: <?= $device['branch'] == 'KIMATHI' ? '#059669' : '#3b82f6' ?>">
                        <i class="fas <?= $device['branch'] == 'KIMATHI' ? 'fa-building' : 'fa-store' ?>"></i>
                        <?= htmlspecialchars($device['branch']) ?>
                    </span> (Cannot be changed)
                </p>
            </div>

            <form method="POST" onsubmit="return confirmUpdate()">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Category <span class="required">*</span></label>
                        <select name="category_id" id="category_id" onchange="toggleTouch()" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($device['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>Model Name <span class="required">*</span></label>
                        <input type="text" name="model_name" value="<?= htmlspecialchars($device['model_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Processor <span class="required">*</span></label>
                        <input type="text" name="processor" value="<?= htmlspecialchars($device['processor']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Graphics</label>
                        <input type="text" name="graphics" value="<?= htmlspecialchars($device['graphics']) ?>">
                    </div>

                    <div class="form-group">
                        <label>RAM (GB) <span class="required">*</span></label>
                        <input type="number" name="ram" min="1" max="256" value="<?= $device['ram'] ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Storage Type <span class="required">*</span></label>
                        <select name="storage_type" required>
                            <option value="SSD" <?= ($device['storage_type'] == 'SSD') ? 'selected' : '' ?>>SSD</option>
                            <option value="HDD" <?= ($device['storage_type'] == 'HDD') ? 'selected' : '' ?>>HDD</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Storage Capacity (GB) <span class="required">*</span></label>
                        <input type="number" name="storage_capacity" min="1" max="4000" value="<?= $device['storage_capacity'] ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Device Condition <span class="required">*</span></label>
                        <select name="device_condition" required>
                            <option value="New" <?= ($device['device_condition'] == 'New') ? 'selected' : '' ?>>New</option>
                            <option value="Refurbished" <?= ($device['device_condition'] == 'Refurbished') ? 'selected' : '' ?>>Refurbished</option>
                        </select>
                    </div>

                    <!-- Touch Field - Dynamically shown/hidden -->
                    <div id="touch_div" class="form-group" style="display: none;">
                        <label>Touch Screen</label>
                        <select name="touch">
                            <option value="">-- Select Option --</option>
                            <option value="Touch" <?= ($device['touch'] == 'Touch') ? 'selected' : '' ?>>Touch</option>
                            <option value="Non-touch" <?= ($device['touch'] == 'Non-touch') ? 'selected' : '' ?>>Non-touch</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="view_device.php?sn=<?= urlencode($device['serial_number']) ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Device
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<script>
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