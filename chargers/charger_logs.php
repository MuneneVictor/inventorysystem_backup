<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Allow super_admin, inventory_admin, manager
if (!in_array($role, ['super_admin', 'inventory_admin', 'manager'])) {
    die("Access denied!");
}

// Manager branch restriction
$user_branch = '';
if ($role === 'manager') {
    $user_stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_branch = $user_data['branch'] ?? '';
}

// --- Handle Return Action with File Upload (REQUIRED) ---
$return_error = '';
$return_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_charger'])) {
    $log_id = (int) $_POST['log_id'];
    $return_qty = (int) $_POST['return_qty'];

    if ($return_qty <= 0) {
        $return_error = "Quantity must be greater than zero.";
    } else {
        // Check if file was uploaded
        if (!isset($_FILES['return_proof']) || $_FILES['return_proof']['error'] !== UPLOAD_ERR_OK) {
            $return_error = "Please take or upload a photo as proof of return.";
        } else {
            $file = $_FILES['return_proof'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heif', 'heic'];

            if (!in_array($ext, $allowed_exts)) {
                $return_error = "Invalid file type. Please upload an image (JPEG, PNG, GIF, WEBP, HEIF/HEIC).";
            } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB max
                $return_error = "File size exceeds 5MB limit.";
            } else {
                // For standard image types, verify MIME type (skip for HEIC/HEIF)
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

                // If it's HEIC/HEIF, we skip MIME check (some servers don't recognise them)
                if (!in_array($ext, ['heif', 'heic'])) {
                    if (!in_array($mime_type, $allowed_mimes)) {
                        $return_error = "Invalid image MIME type. Please upload a valid image.";
                    }
                }
                // For HEIC/HEIF, we trust the extension

                if (!$return_error) {
                    // Create uploads directory if not exists (with full permissions)
                    $upload_dir = __DIR__ . '/../uploads/charger_returns/';
                    if (!is_dir($upload_dir)) {
                        if (!mkdir($upload_dir, 0777, true)) {
                            $return_error = "Failed to create upload directory. Please check permissions.";
                        }
                    }
                    // Also ensure the directory is writable
                    if (!$return_error && !is_writable($upload_dir)) {
                        $return_error = "Upload directory is not writable. Please set permissions to 0777.";
                    }

                    if (!$return_error) {
                        $filename = 'charger_return_' . $log_id . '_' . time() . '.' . $ext;
                        $destination = $upload_dir . $filename;
                        // Try to move the uploaded file
                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            // Set file permissions (optional)
                            chmod($destination, 0644);
                            $uploaded_file = '../uploads/charger_returns/' . $filename;
                        } else {
                            // Capture detailed error
                            $error = error_get_last();
                            $return_error = "Failed to upload file: " . ($error ? $error['message'] : 'Unknown error. Please try again.');
                        }
                    }
                }
            }
        }

        if (!$return_error && isset($uploaded_file)) {
            try {
                $conn->beginTransaction();

                // Lock the log row and fetch current data
                $logStmt = $conn->prepare("SELECT * FROM charger_logs WHERE id = ? AND status = 'pending_sale' FOR UPDATE");
                $logStmt->execute([$log_id]);
                $log = $logStmt->fetch(PDO::FETCH_ASSOC);
                if (!$log) {
                    throw new Exception("Log not found or not pending.");
                }
                if ($return_qty > $log['quantity']) {
                    throw new Exception("Return quantity exceeds the given quantity.");
                }

                // Lock the corresponding charger row
                $chargerStmt = $conn->prepare("SELECT * FROM chargers WHERE id = ? FOR UPDATE");
                $chargerStmt->execute([$log['charger_id']]);
                $charger = $chargerStmt->fetch(PDO::FETCH_ASSOC);
                if (!$charger) {
                    throw new Exception("Charger record not found.");
                }

                // Update charger quantity
                $new_charger_qty = $charger['quantity'] + $return_qty;
                $updateCharger = $conn->prepare("UPDATE chargers SET quantity = ?, updated_by = ?, date_updated = NOW() WHERE id = ?");
                $updateCharger->execute([$new_charger_qty, $user_id, $charger['id']]);

                // Update log: reduce quantity or change status
                if ($return_qty == $log['quantity']) {
                    // Full return – set status to 'returned'
                    $updateLog = $conn->prepare("UPDATE charger_logs SET status = 'returned' WHERE id = ?");
                    $updateLog->execute([$log_id]);
                    $new_log_qty = $log['quantity'];
                } else {
                    // Partial return – reduce quantity, keep status 'pending_sale'
                    $new_log_qty = $log['quantity'] - $return_qty;
                    $updateLog = $conn->prepare("UPDATE charger_logs SET quantity = ? WHERE id = ?");
                    $updateLog->execute([$new_log_qty, $log_id]);
                }

                // Activity log with proof file info
                $action = ($return_qty == $log['quantity']) ? "Returned Charger (full)" : "Returned Charger (partial)";
                $details = "Returned {$return_qty} charger(s) ({$log['charger_type']}, {$log['charger_condition']}) from log ID {$log_id}. " .
                           ($return_qty == $log['quantity'] ? "Status set to returned." : "Remaining quantity: {$new_log_qty}.");
                $details .= " <a href='{$uploaded_file}' target='_blank'>View Photo</a>";
                $activity = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
                $activity->execute([$user_id, $action, $details]);

                $conn->commit();
                $return_success = "Charger returned successfully! Photo uploaded.";

                // Clear output buffer and redirect
                ob_end_clean();
                header("Location: charger_logs.php?success=1");
                exit;

            } catch (Exception $e) {
                $conn->rollBack();
                $return_error = "Error: " . $e->getMessage();
            }
        }
    }
}

// If success message from redirect, show it
if (isset($_GET['success'])) {
    $return_success = "Charger returned successfully!";
}



// Get filter inputs
$filter_branch = trim($_GET['branch'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$search = trim($_GET['search'] ?? '');

// Build query – join with users for given_to and given_by names
$sql = "SELECT l.*, 
               u_given_to.full_name AS given_to_name,
               u_given_by.full_name AS given_by_name
        FROM charger_logs l
        LEFT JOIN users u_given_to ON l.given_to = u_given_to.id
        LEFT JOIN users u_given_by ON l.given_by = u_given_by.id
        WHERE 1=1";
$params = [];

// Manager restriction
if ($role === 'manager' && !empty($user_branch)) {
    $sql .= " AND l.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}

// Filters
if ($filter_branch && $role !== 'manager') {
    $sql .= " AND l.branch = :branch";
    $params['branch'] = $filter_branch;
}
if ($filter_status) {
    $sql .= " AND l.status = :status";
    $params['status'] = $filter_status;
}
if ($date_from) {
    $sql .= " AND DATE(l.date_given) >= :date_from";
    $params['date_from'] = $date_from;
}
if ($date_to) {
    $sql .= " AND DATE(l.date_given) <= :date_to";
    $params['date_to'] = $date_to;
}
if ($search) {
    $sql .= " AND (l.charger_type LIKE :search OR l.charger_condition LIKE :search OR u_given_to.full_name LIKE :search OR u_given_by.full_name LIKE :search)";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY l.date_given DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_logs = count($logs);
$total_quantity = array_sum(array_column($logs, 'quantity'));
$branches = array_unique(array_column($logs, 'branch'));
$statuses = array_unique(array_column($logs, 'status'));

// Get branch list for filter (only if super_admin or inventory_admin)
$branches_list = [];
if (in_array($role, ['super_admin', 'inventory_admin'])) {
    $stmt = $conn->query("SELECT DISTINCT branch FROM charger_logs ORDER BY branch");
    $branches_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Charger Logs | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Same CSS as hdd_logs.php – keeping consistency */
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

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }

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

        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }

        .breadcrumb {
            color: var(--gray-500);
            font-size: 0.9rem;
        }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.25rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-card .stat-icon { font-size: 1.5rem; color: var(--primary); margin-bottom: 0.5rem; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 600; color: var(--gray-800); }
        .stat-card .stat-label { font-size: 0.85rem; color: var(--gray-500); margin-top: 0.25rem; }

        .search-section {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .search-title {
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .search-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .search-group label { font-size: 0.85rem; font-weight: 500; color: var(--gray-600); }
        .search-group input, .search-group select {
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            background: white;
        }
        .search-group input:focus, .search-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26,75,42,0.1);
        }

        .search-actions {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.625rem 1.25rem;
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

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-300); }
        .btn-secondary:hover { background: var(--gray-200); }
        .btn-excel { background: #217346; color: white; }
        .btn-excel:hover { background: #1a5e33; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }

        .table-wrapper {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            min-width: 700px;
        }

        th {
            background: var(--gray-50);
            padding: 1rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-600);
            font-size: 0.85rem;
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }

        td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            vertical-align: middle;
        }

        tr:hover { background: var(--gray-50); }
        tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-sold { background: #fee2e2; color: #991b1b; }
        .badge-returned { background: #d1fae5; color: #065f46; }

        .branch-kimathi { color: #059669; font-weight: 500; }
        .branch-moi { color: #3b82f6; font-weight: 500; }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }

        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            max-width: 500px;
            width: 95%;
            box-shadow: var(--shadow-lg);
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-box h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .modal-box .form-group {
            margin-bottom: 1.25rem;
        }
        .modal-box .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        .modal-box .form-group input[type="number"],
        .modal-box .form-group input[type="file"] {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
        }
        .modal-box .form-group input:focus {
            outline: none;
            border-color: var(--primary);
        }
        .modal-box .file-hint {
            font-size: 0.8rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        .preview-container {
            margin-top: 0.5rem;
            text-align: center;
        }
        .preview-container img {
            max-width: 100%;
            max-height: 200px;
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
            display: none;
        }
        .modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }
        .modal-actions .btn { padding: 0.5rem 1.25rem; }

        /* Alert styles */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1rem 1.25rem; }
            .stats-row { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .stat-card { padding: 1rem; }
            .stat-card .stat-value { font-size: 1.5rem; }
            .search-section { padding: 1rem; }
            .search-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; justify-content: center; }
            .table { min-width: 600px; }
            .modal-box { padding: 1.5rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .stats-row { grid-template-columns: 1fr; }
            .page-header h1 { font-size: 1.1rem; }
            .table { min-width: 500px; }
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Charger Logs</h1>
        <div class="breadcrumb">
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="../dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Charger Logs</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-list"></i></div>
            <div class="stat-value"><?= number_format($total_logs) ?></div>
            <div class="stat-label">Total Logs</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-cubes"></i></div>
            <div class="stat-value"><?= number_format($total_quantity) ?></div>
            <div class="stat-label">Total Chargers Given</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-store"></i></div>
            <div class="stat-value"><?= number_format(count($branches)) ?></div>
            <div class="stat-label">Branches</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
            <div class="stat-value"><?= number_format(count($statuses)) ?></div>
            <div class="stat-label">Statuses</div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($return_error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($return_error) ?></div>
    <?php endif; ?>
    <?php if ($return_success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($return_success) ?></div>
    <?php endif; ?>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-title"><i class="fas fa-filter"></i> Filter Logs</div>
        <form method="GET" class="search-grid">
            <div class="search-group">
                <label>Search</label>
                <input type="text" name="search" placeholder="Type, condition, salesperson..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <?php if ($role !== 'manager'): ?>
            <div class="search-group">
                <label>Branch</label>
                <select name="branch">
                    <option value="">-- All Branches --</option>
                    <?php foreach ($branches_list as $b): ?>
                        <option value="<?= htmlspecialchars($b) ?>" <?= $filter_branch == $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="search-group">
                <label>Status</label>
                <select name="status">
                    <option value="">-- All Statuses --</option>
                    <option value="pending_sale" <?= $filter_status == 'pending_sale' ? 'selected' : '' ?>>Pending Sale</option>
                    <option value="sold" <?= $filter_status == 'sold' ? 'selected' : '' ?>>Sold</option>
                    <option value="returned" <?= $filter_status == 'returned' ? 'selected' : '' ?>>Returned</option>
                </select>
            </div>
            <div class="search-group">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="search-group">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="search-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                <a href="charger_logs.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                <?php if (!empty($logs)): ?>
                    <a href="export_charger_logs_excel.php?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn btn-excel"><i class="fas fa-file-excel"></i> Export to Excel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="table-wrapper">
        <div class="table-responsive">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>No charger logs found matching your criteria.</p>
                    <a href="charger_logs.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-undo"></i> Clear Filters
                    </a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Charger Type</th>
                            <th>Condition</th>
                            <th>Qty</th>
                            <th>Given To</th>
                            <th>Given By</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th>Date Given</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($logs as $log): ?>
                            <?php
                            $statusClass = '';
                            $statusLabel = ucfirst(str_replace('_', ' ', $log['status']));
                            if ($log['status'] == 'pending_sale') $statusClass = 'badge-pending';
                            elseif ($log['status'] == 'sold') $statusClass = 'badge-sold';
                            elseif ($log['status'] == 'returned') $statusClass = 'badge-returned';
                            ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($log['charger_type']) ?></strong></td>
                                <td><?= htmlspecialchars(ucfirst($log['charger_condition'])) ?></td>
                                <td><span class="badge"><?= (int)$log['quantity'] ?></span></td>
                                <td><?= htmlspecialchars($log['given_to_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($log['given_by_name'] ?? 'Unknown') ?></td>
                                <td>
                                    <span class="<?= $log['branch'] == 'KIMATHI' ? 'branch-kimathi' : 'branch-moi' ?>">
                                        <?= htmlspecialchars($log['branch']) ?>
                                    </span>
                                </td>
                                <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                <td><small><?= date('M j, Y g:i A', strtotime($log['date_given'])) ?></small></td>
                                <td>
                                    <?php if ($log['status'] == 'pending_sale'): ?>
                                        <button class="btn btn-warning" style="padding:0.3rem 0.7rem; font-size:0.8rem;" 
                                                onclick="openReturnModal(<?= $log['id'] ?>, <?= $log['quantity'] ?>)">
                                            <i class="fas fa-undo"></i> Return
                                        </button>
                                    <?php else: ?>
                                        <span class="badge">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<!-- Return Modal with File Upload and Preview -->
<div class="modal-overlay" id="returnModal">
    <div class="modal-box">
        <h3><i class="fas fa-undo-alt"></i> Return Charger</h3>
        <p style="margin-bottom:1rem; color:var(--gray-500);">Enter the quantity to return. You can return up to <strong id="maxQtyDisplay">0</strong> units.</p>
        <form method="POST" id="returnForm" enctype="multipart/form-data">
            <input type="hidden" name="log_id" id="returnLogId" value="">
            <div class="form-group">
                <label for="returnQty">Quantity to Return</label>
                <input type="number" name="return_qty" id="returnQty" min="1" required>
            </div>
            <div class="form-group">
                <label for="returnProof">Proof of Return (Photo) <span style="color:#dc2626;">*</span></label>
                <input type="file" name="return_proof" id="returnProof" accept="image/*,.heif,.heic" required>
                <div class="file-hint"><i class="fas fa-camera"></i> Take a photo (mobile) or upload an image (JPEG, PNG, GIF, WEBP, HEIF/HEIC, max 5MB).</div>
                <div class="preview-container">
                    <img id="imagePreview" src="#" alt="Preview">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeReturnModal()">Cancel</button>
                <button type="submit" name="return_charger" class="btn btn-primary" id="returnSubmitBtn">Return</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Preview image when file is selected
    document.getElementById('returnProof').addEventListener('change', function(e) {
        const preview = document.getElementById('imagePreview');
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                preview.src = event.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
            preview.src = '#';
        }
    });

    function openReturnModal(logId, maxQty) {
        document.getElementById('returnLogId').value = logId;
        document.getElementById('returnQty').value = maxQty;
        document.getElementById('returnQty').max = maxQty;
        document.getElementById('maxQtyDisplay').textContent = maxQty;
        // Reset preview and file input
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('imagePreview').src = '#';
        document.getElementById('returnProof').value = '';
        document.getElementById('returnModal').classList.add('active');
    }

    function closeReturnModal() {
        document.getElementById('returnModal').classList.remove('active');
    }

    // Close modal on outside click
    document.getElementById('returnModal').addEventListener('click', function(e) {
        if (e.target === this) closeReturnModal();
    });

    // Ensure file is selected before submitting (client-side double-check)
    document.getElementById('returnForm').addEventListener('submit', function(e) {
        const fileInput = document.getElementById('returnProof');
        if (!fileInput.files || fileInput.files.length === 0) {
            e.preventDefault();
            alert('Please take or upload a photo as proof of return.');
            return false;
        }
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
<?php
// End output buffering and flush
ob_end_flush();
?>