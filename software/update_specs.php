<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Access: software and inventory_admin (and optionally manager)
if (!in_array($_SESSION['role'], ['software', 'inventory_admin', 'manager', 'super_admin'])) {
    die("ACCESS DENIED. Only Software and Inventory Admin can update specs.");
}

$user_role = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'User';

$error = "";
$success = "";
$device = null;
$serial_search = trim($_GET['sn'] ?? '');

// Helper function to safely escape HTML
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Get user branch
$user_branch = null;
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
    if (!$user_branch) {
        $error = "Your account has no branch assigned.";
    }
}

// ============================================================
// FETCH DEVICE FOR GET REQUEST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $serial_search) {
    $sql = "SELECT d.*, c.category_name, u.full_name AS added_by_name
            FROM devices d
            JOIN categories c ON d.category_id = c.id
            LEFT JOIN users u ON d.added_by = u.id
            WHERE d.serial_number COLLATE utf8mb4_general_ci = ? AND d.status = 'In Stock'";
    
    $params = [$serial_search];
    
    if ($user_role !== 'super_admin' && $user_branch) {
        $sql .= " AND d.branch = ?";
        $params[] = $user_branch;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device && empty($error)) {
        $error = "Device not found, not In Stock, or you don't have permission.";
    }
}

// ============================================================
// HANDLE POST UPDATE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serial = trim($_POST['serial_number']);
    
    // Fetch device with permission check
    $sql = "SELECT * FROM devices WHERE serial_number COLLATE utf8mb4_general_ci = ? AND status = 'In Stock'";
    $params = [$serial];
    
    if ($user_role !== 'super_admin' && $user_branch) {
        $sql .= " AND branch = ?";
        $params[] = $user_branch;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $deviceRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deviceRow) {
        $error = "Device not found, not In Stock, or you don't have permission.";
    } else {
        // Old values
        $old_ram = (int)$deviceRow['ram'];
        $old_storage_capacity = (int)$deviceRow['storage_capacity'];
        $old_storage_type = $deviceRow['storage_type'] ?? '';
        $old_graphics = $deviceRow['graphics'] ?? null;
        
        // New values
        $new_ram_raw = trim($_POST['new_ram'] ?? '');
        $new_storage_capacity_raw = trim($_POST['new_storage_capacity'] ?? '');
        $new_storage_type = trim($_POST['new_storage_type'] ?? '');
        $new_graphics_raw = trim($_POST['new_graphics'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        $new_ram = ($new_ram_raw === "" ? $old_ram : (int)$new_ram_raw);
        $new_storage_capacity = ($new_storage_capacity_raw === "" ? $old_storage_capacity : (int)$new_storage_capacity_raw);
        if ($new_storage_type === "same" || $new_storage_type === "") {
            $new_storage_type = $old_storage_type;
        }
        
        $graphics_changed = false;
        if ($new_graphics_raw !== "") {
            if ($new_graphics_raw !== $old_graphics) $graphics_changed = true;
            $new_graphics = $new_graphics_raw;
        } else {
            $new_graphics = $old_graphics;
        }
        
        try {
            $conn->beginTransaction();
            
            // Insert maintenance record
            $ins = $conn->prepare("
                INSERT INTO maintenance
                (device_serial, old_ram, new_ram, old_storage, new_storage, old_graphics, new_graphics, performed_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $serial, $old_ram, $new_ram, $old_storage_capacity, $new_storage_capacity,
                $old_graphics, $new_graphics, $user_id, $notes ?: null
            ]);
            
            // Build device update fields
            $updateFields = [];
            $updateParams = [];
            
            if ($new_ram !== $old_ram) {
                $updateFields[] = "ram = ?";
                $updateParams[] = $new_ram;
            }
            if ($new_storage_capacity !== $old_storage_capacity || $new_storage_type !== $old_storage_type) {
                $updateFields[] = "storage_capacity = ?";
                $updateFields[] = "storage_type = ?";
                $updateParams[] = $new_storage_capacity;
                $updateParams[] = $new_storage_type;
            }
            if ($graphics_changed) {
                $updateFields[] = "graphics = ?";
                $updateParams[] = $new_graphics;
            }
            
            // ALWAYS set price to NULL when specs are updated
            $updateFields[] = "price = ?";
            $updateParams[] = NULL;
            
            // Add serial_number at the end
            $updateParams[] = $serial;
            
            if (!empty($updateFields)) {
                $updateSql = "UPDATE devices SET " . implode(", ", $updateFields) . " WHERE serial_number COLLATE utf8mb4_general_ci = ?";
                $upd = $conn->prepare($updateSql);
                $upd->execute($updateParams);
            }
            
            // Log activity
            $log_details = "Updated specs for $serial by $user_name.";
            if ($new_ram !== $old_ram) $log_details .= " RAM: {$old_ram}GB → {$new_ram}GB.";
            if ($new_storage_capacity !== $old_storage_capacity || $new_storage_type !== $old_storage_type) {
                $log_details .= " Storage: {$old_storage_type} {$old_storage_capacity}GB → {$new_storage_type} {$new_storage_capacity}GB.";
            }
            if ($graphics_changed) $log_details .= " Graphics: " . ($old_graphics ?: 'None') . " → " . ($new_graphics ?: 'None') . ".";
            $log_details .= " Price set to NULL.";
            
            $alog = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Maintenance - Update Specs', ?)");
            $alog->execute([$user_id, $log_details]);
            
            $conn->commit();
            $success = "Maintenance recorded successfully. Price has been reset to NULL.";
            
            // Reload device with fresh data using COLLATE
            $reloadSql = "SELECT d.*, c.category_name, u.full_name AS added_by_name
                          FROM devices d
                          JOIN categories c ON d.category_id = c.id
                          LEFT JOIN users u ON d.added_by = u.id
                          WHERE d.serial_number COLLATE utf8mb4_general_ci = ? AND d.status = 'In Stock'";
            $reloadParams = [$serial];
            
            if ($user_role !== 'super_admin' && $user_branch) {
                $reloadSql .= " AND d.branch = ?";
                $reloadParams[] = $user_branch;
            }
            
            $stmt = $conn->prepare($reloadSql);
            $stmt->execute($reloadParams);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = "Error: " . $e->getMessage();
        }
    }
}

date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Update Device Specs | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --font-sans: 'Inter', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: all 0.3s ease; }
        .page-header { background: white; padding: 1.5rem 2rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .page-header h1 { font-size: 1.75rem; color: var(--gray-800); font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }
        .page-header .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .page-header .breadcrumb a { color: var(--primary); text-decoration: none; }
        .page-header .breadcrumb a:hover { text-decoration: underline; }
        .page-header .user-info { margin-top: 0.5rem; color: var(--gray-500); font-size: 0.85rem; }
        .page-header .user-info i { color: var(--primary); }

        .search-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .search-form { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .search-input-group { flex: 1; min-width: 200px; }
        .search-input-group label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--gray-600); margin-bottom: 0.5rem; }
        .search-input-group input { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .btn-block { width: 100%; justify-content: center; }
        
        .card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; }
        .card-header { background: var(--gray-50); padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200); font-weight: 600; display: flex; align-items: center; gap: 0.75rem; }
        .card-header i { color: var(--primary); }
        .card-body { padding: 1.5rem; }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
        .info-item { padding: 0.75rem 1rem; background: var(--gray-50); border-radius: var(--radius-lg); border: 1px solid var(--gray-200); }
        .info-label { font-size: 0.6rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 0.9rem; font-weight: 500; color: var(--gray-800); margin-top: 0.1rem; }
        .info-value code { background: var(--gray-100); padding: 0.1rem 0.4rem; border-radius: var(--radius-md); font-size: 0.8rem; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
        .form-group label { font-size: 0.85rem; font-weight: 600; color: var(--gray-700); }
        .form-group label .small { font-weight: 400; color: var(--gray-400); font-size: 0.75rem; }
        .form-group input, .form-group select, .form-group textarea { padding: 0.75rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; font-family: var(--font-sans); transition: border-color 0.2s ease; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1); }
        .form-group textarea { resize: vertical; min-height: 70px; }
        .form-group .help-text { font-size: 0.7rem; color: var(--gray-400); margin-top: 0.2rem; }
        
        .alert { padding: 1rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem; border: 1px solid transparent; }
        .alert-success { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        .alert-error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .alert i { font-size: 1.25rem; }
        
        .price-note {
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }
        
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .footer span { color: var(--primary); }
        
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { 
            .search-form { flex-direction: column; } 
            .form-row { grid-template-columns: 1fr; } 
            .btn { width: 100%; justify-content: center; }
            .info-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header { padding: 1rem; }
            .page-header h1 { font-size: 1.2rem; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-microchip"></i> Update Device Specs</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'software'): ?>
                <a href="/inventory_system/dashboard/softwaredashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Update Specs</span>
        </div>
        <div class="user-info">
            <i class="fas fa-user"></i> <?= safe($greeting) ?>, <?= safe(explode(' ', $user_name)[0]) ?>
        </div>
    </div>

    <div class="search-section">
        <form method="GET" class="search-form">
            <div class="search-input-group">
                <label><i class="fas fa-qrcode"></i> Serial Number</label>
                <input type="text" name="sn" placeholder="Scan or enter serial number" value="<?= safe($serial_search) ?>" autofocus>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Load Device</button>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= safe($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= safe($success) ?></div>
    <?php endif; ?>

    <?php if ($device): ?>
    <div class="card">
        <div class="card-header">
            <i class="fas fa-info-circle"></i> Current Device Information
            <span style="margin-left:auto; background:#dcfce7; color:#065f46; padding:0.25rem 0.75rem; border-radius:9999px; font-size:0.75rem;">
                <i class="fas fa-check-circle"></i> In Stock
            </span>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Serial</div><div class="info-value"><code><?= safe($device['serial_number']) ?></code></div></div>
                <div class="info-item"><div class="info-label">Category</div><div class="info-value"><?= safe($device['category_name']) ?></div></div>
                <div class="info-item"><div class="info-label">Model</div><div class="info-value"><?= safe($device['model_name']) ?></div></div>
                <div class="info-item"><div class="info-label">Processor</div><div class="info-value"><?= safe($device['processor']) ?></div></div>
                <div class="info-item"><div class="info-label">Current RAM</div><div class="info-value"><?= safe($device['ram']) ?> GB</div></div>
                <div class="info-item"><div class="info-label">Current Storage</div><div class="info-value"><?= safe($device['storage_type'] . ' ' . $device['storage_capacity'] . ' GB') ?></div></div>
                <div class="info-item"><div class="info-label">Current Graphics</div><div class="info-value"><?= safe($device['graphics'] ?? 'None') ?></div></div>
                <div class="info-item"><div class="info-label">Branch</div><div class="info-value"><?= safe($device['branch']) ?></div></div>
                <?php if (!empty($device['price'])): ?>
                <div class="info-item" style="background:#fef3c7; border-color:#fde68a;">
                    <div class="info-label">Current Price</div>
                    <div class="info-value" style="color:#92400e;">KES <?= number_format($device['price'], 2) ?></div>
                </div>
                <?php endif; ?>
            </div>

         

            <form method="POST">
                <input type="hidden" name="serial_number" value="<?= safe($device['serial_number']) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>New RAM (GB) <span class="small">(leave blank to keep current)</span></label>
                        <input type="number" name="new_ram" min="1" placeholder="e.g. 16">
                    </div>
                    <div class="form-group">
                        <label>New Storage Capacity (GB)</label>
                        <input type="number" name="new_storage_capacity" min="1" placeholder="e.g. 512">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>New Storage Type</label>
                        <select name="new_storage_type">
                            <option value="same">Keep current (<?= safe($device['storage_type'] ?? 'Not set') ?>)</option>
                            <option value="SSD">SSD</option>
                            <option value="HDD">HDD</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>New Graphics Card</label>
                        <input type="text" name="new_graphics" placeholder="e.g. NVIDIA GTX 1650">
                        <span class="help-text">Leave blank to keep current</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes (optional)</label>
                    <textarea name="notes" rows="3" placeholder="Reason for upgrade/downgrade..."></textarea>
                </div>

                <button type="submit" class="btn btn-success btn-block" style="margin-top:1rem;">
                    <i class="fas fa-save"></i> Update
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> <span>Mombasa Computers</span>. All rights reserved.
        <span style="margin:0 0.5rem;">•</span>
        <span>v2.0.0</span>
    </div>
</div>

<script>
function adjustMainContent() {
    const main = document.querySelector('.main-content');
    if (window.innerWidth <= 1200) main.style.marginLeft = '0';
    else main.style.marginLeft = '260px';
}
window.addEventListener('resize', adjustMainContent);
adjustMainContent();
</script>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>