<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? $_SESSION['full_name'] ?? 'User';

if (!isset($_GET['sn'])) {
    die("Serial number not provided!");
}

$serial_number = trim($_GET['sn']);

// Fetch device info with added_by and sold_by names
$stmt = $conn->prepare("
    SELECT d.*, c.category_name, 
           u_added.full_name AS added_by_name,
           u_sold.full_name AS sold_by_name
    FROM devices d 
    JOIN categories c ON d.category_id = c.id 
    LEFT JOIN users u_added ON d.added_by = u_added.id
    LEFT JOIN users u_sold ON d.sold_by = u_sold.id
    WHERE d.serial_number = :sn
");
$stmt->execute(['sn' => $serial_number]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    die("Device not found!");
}

// Fetch maintenance logs
$maintenanceStmt = $conn->prepare("SELECT m.*, u.full_name AS performed_by_name
                                   FROM maintenance m
                                   LEFT JOIN users u ON m.performed_by = u.id
                                   WHERE m.device_serial = :sn
                                   ORDER BY m.date_performed DESC");
$maintenanceStmt->execute(['sn' => $serial_number]);
$maintenance_logs = $maintenanceStmt->fetchAll(PDO::FETCH_ASSOC);

// Build sold info directly from device record
$sold_info = null;
if ($device['status'] === 'Sold') {
    $sold_info = [
        'sold_by_name' => $device['sold_by_name'] ?? 'Unknown',
        'sold_at'      => $device['sold_at'],
        'selling_price' => $device['selling_price'],
        'customer_name' => 'Walk-in Customer' // no customer field in devices
    ];
}

$error = "";
$success = "";

// Handle updates by technician/maintenance
if (($role == 'technician' || $role == 'maintenance') && isset($_POST['update_action'])) {
    $update_action = trim($_POST['update_action']);
    $notes = trim($_POST['notes']) ?: null;
    $old_status = $device['status'];

    // Update device table status using prepared statement
    $stmt = $conn->prepare("UPDATE devices SET status = :status WHERE serial_number = :sn");
    $stmt->execute(['status' => $update_action, 'sn' => $serial_number]);

    // Log the update using prepared statement
    $stmt = $conn->prepare("INSERT INTO device_updates (device_serial, updated_by, old_status, new_status, notes) 
                            VALUES (:sn, :uid, :old, :new, :notes)");
    $stmt->execute([
        'sn' => $serial_number,
        'uid' => $user_id,
        'old' => $old_status,
        'new' => $update_action,
        'notes' => $notes
    ]);

    // Activity log using prepared statement
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) 
                            VALUES (:uid, 'Updated device', :details)");
    $stmt->execute([
        'uid' => $user_id,
        'details' => "Updated device SN: $serial_number from $old_status to $update_action"
    ]);

    $success = "Device updated successfully!";
    $device['status'] = $update_action; // refresh for display
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>View Device | <?= htmlspecialchars($device['serial_number']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* (CSS unchanged – same as original) */
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

        /* Result Cards */
        .result-card {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .card-header {
            background: var(--gray-50);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .card-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header h3 i {
            color: var(--primary);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-instock {
            background: #d1fae5;
            color: #065f46;
        }

        .status-sold {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-repair {
            background: #fed7aa;
            color: #92400e;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .info-item {
            padding: 0.875rem;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            transition: all 0.2s ease;
        }

        .info-item:hover {
            background: white;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .info-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--gray-800);
            word-break: break-word;
        }

        .info-value code {
            background: white;
            padding: 0.2rem 0.4rem;
            border-radius: var(--radius-sm);
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
        }

        /* Form Styles */
        .form-card {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            background: white;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        .mini-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .mini-table th,
        .mini-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .mini-table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-600);
        }

        .mini-table tr:last-child td {
            border-bottom: none;
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

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
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

            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .card-body {
                padding: 1.25rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .action-buttons {
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

            .card-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-laptop"></i>
            Device Details
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
            <?php if($_SESSION['role'] === 'sales'): ?>
                <a href="/inventory_system/dashboard/salesdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="device_list.php">Devices</a>
            <span> / </span>
            <span>View Device</span>
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

    <!-- Device Details Card -->
    <div class="result-card">
        <div class="card-header">
            <h3>
                <i class="fas fa-info-circle"></i>
                Device Information
            </h3>
            <span class="status-badge <?= $device['status'] == 'In Stock' ? 'status-instock' : ($device['status'] == 'Sold' ? 'status-sold' : 'status-repair') ?>">
                <i class="fas <?= $device['status'] == 'In Stock' ? 'fa-check-circle' : ($device['status'] == 'Sold' ? 'fa-times-circle' : 'fa-tools') ?>"></i>
                <?= htmlspecialchars($device['status']) ?>
            </span>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Serial Number</div>
                    <div class="info-value"><code><?= htmlspecialchars($device['serial_number']) ?></code></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Category</div>
                    <div class="info-value"><?= htmlspecialchars($device['category_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Model Name</div>
                    <div class="info-value"><strong><?= htmlspecialchars($device['model_name']) ?></strong></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Branch</div>
                    <div class="info-value">
                        <span style="color: <?= $device['branch'] == 'KIMATHI' ? '#059669' : '#3b82f6' ?>">
                            <i class="fas <?= $device['branch'] == 'KIMATHI' ? 'fa-building' : 'fa-store' ?>"></i>
                            <?= htmlspecialchars($device['branch']) ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Processor</div>
                    <div class="info-value"><?= htmlspecialchars($device['processor']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Graphics</div>
                    <div class="info-value"><?= htmlspecialchars($device['graphics'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">RAM</div>
                    <div class="info-value"><?= htmlspecialchars($device['ram']) ?> GB</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Storage</div>
                    <div class="info-value"><?= htmlspecialchars($device['storage_type']) ?> <?= htmlspecialchars($device['storage_capacity']) ?> GB</div>
                </div>
                <?php if(strtolower($device['category_name']) !== 'desktop'): ?>
                <div class="info-item">
                    <div class="info-label">Touch Screen</div>
                    <div class="info-value"><?= htmlspecialchars($device['touch'] ?? 'N/A') ?></div>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="info-label">Cargo Number</div>
                    <div class="info-value"><?= htmlspecialchars($device['cargo_number'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Condition</div>
                    <div class="info-value"><?= htmlspecialchars($device['device_condition'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Added By</div>
                    <div class="info-value"><?= htmlspecialchars($device['added_by_name'] ?? 'Unknown') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date Added</div>
                    <div class="info-value"><?= date('M j, Y H:i', strtotime($device['date_added'])) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sale Information (if sold) -->
    <?php if($sold_info): ?>
    <div class="result-card">
        <div class="card-header">
            <h3>
                <i class="fas fa-receipt"></i>
                Sale Information
            </h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Sold By</div>
                    <div class="info-value"><?= htmlspecialchars($sold_info['sold_by_name'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Sale Date</div>
                    <div class="info-value"><?= !empty($sold_info['sold_at']) ? date('M j, Y H:i', strtotime($sold_info['sold_at'])) : '-' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Price</div>
                    <div class="info-value"><strong style="color: #059669;"><?= !empty($sold_info['selling_price']) ? 'KES ' . number_format($sold_info['selling_price'], 0) : '-' ?></strong></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Customer</div>
                    <div class="info-value"><?= htmlspecialchars($sold_info['customer_name'] ?? 'Walk-in Customer') ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Maintenance Logs -->
    <?php if(!empty($maintenance_logs)): ?>
    <div class="result-card">
        <div class="card-header">
            <h3>
                <i class="fas fa-tools"></i>
                Maintenance History
            </h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Performed By</th>
                            <th>RAM Change</th>
                            <th>Storage Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($maintenance_logs as $log): ?>
                        <tr>
                            <td><small><?= date('M j, Y', strtotime($log['date_performed'])) ?></small></td>
                            <td><?= htmlspecialchars($log['performed_by_name'] ?? 'Unknown') ?></td>
                            <td><?= $log['old_ram'] ?>GB → <?= $log['new_ram'] ?>GB</td>
                            <td><?= $log['old_storage'] ?>GB → <?= $log['new_storage'] ?>GB</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Update Form (Technician/Maintenance only) -->
    <?php if($role == 'technician' || $role == 'maintenance'): ?>
    <div class="form-card">
        <div class="card-header">
            <h3>
                <i class="fas fa-sync-alt"></i>
                Update Device Status
            </h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Status Action</label>
                    <select name="update_action" required>
                        <option value="">-- Select Action --</option>
                        <option value="Under Repair">🔧 Under Repair</option>
                        <option value="Faulty">⚠️ Faulty</option>
                        <option value="Upgraded">⬆️ Upgraded</option>
                        <option value="Downgraded">⬇️ Downgraded</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Notes (optional)</label>
                    <textarea name="notes" rows="3" placeholder="Add any additional notes about the device..."></textarea>
                </div>

                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-save"></i> Update Device
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <?php if($role == 'super_admin' || $role == 'inventory_admin' || $role == 'manager'): ?>
    <div class="action-buttons">
        <a href="device_list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Device List
        </a>
    <?php endif; ?>
        <?php if($role == 'super_admin' || $role == 'inventory_admin' || $role == 'manager'): ?>
            <a href="edit_device.php?sn=<?= urlencode($device['serial_number']) ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit Device
            </a>
        <?php endif; ?>
        <?php if($role == 'super_admin'): ?>
            <button type="button" class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Details
            </button>
        <?php endif; ?>
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