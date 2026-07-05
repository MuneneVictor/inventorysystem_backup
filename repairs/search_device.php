<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

// Only technicians and super_admin/inventory_admin/manager can access this search
if (!in_array($user_role, ['technician', 'super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED.");
}

// Get user branch for filtering
$user_branch = null;
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
}

// Search parameters
$search_sn = trim($_GET['serial'] ?? '');
$search_model = trim($_GET['model'] ?? '');
$search_category = trim($_GET['category'] ?? '');
$search_status = trim($_GET['status'] ?? '');
$search_branch = trim($_GET['branch'] ?? '');

$device = null;
$repairs = [];
$search_results = [];

// ============================================================
// SEARCH FOR SINGLE DEVICE (when serial is provided)
// ============================================================
if ($search_sn) {
    $stmt = $conn->prepare("
        SELECT d.*, c.category_name, u.full_name AS added_by_name
        FROM devices d
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN users u ON d.added_by = u.id
        WHERE d.serial_number COLLATE utf8mb4_general_ci = ?
    ");
    $stmt->execute([$search_sn]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($device) {
        // Fetch repair history (all repairs for this device)
        $rstmt = $conn->prepare("
            SELECT r.*, u1.full_name AS technician_name, u2.full_name AS given_by_name,
                   c.category_name
            FROM repairs r
            LEFT JOIN users u1 ON r.added_by = u1.id
            LEFT JOIN users u2 ON r.given_by = u2.id
            LEFT JOIN categories c ON r.category_id = c.id
            WHERE r.serial_number COLLATE utf8mb4_general_ci = ?
            ORDER BY r.date_added DESC
        ");
        $rstmt->execute([$search_sn]);
        $repairs = $rstmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ============================================================
// SEARCH FOR MULTIPLE DEVICES (when model/category/status filters are applied)
// ============================================================
if (!empty($search_model) || !empty($search_category) || !empty($search_status) || !empty($search_branch)) {
    $sql = "SELECT d.*, c.category_name, u.full_name AS added_by_name
            FROM devices d
            LEFT JOIN categories c ON d.category_id = c.id
            LEFT JOIN users u ON d.added_by = u.id
            WHERE 1=1";
    $params = [];

    if (!empty($search_model)) {
        $sql .= " AND d.model_name LIKE ?";
        $params[] = "%$search_model%";
    }
    if (!empty($search_category)) {
        $sql .= " AND c.category_name LIKE ?";
        $params[] = "%$search_category%";
    }
    if (!empty($search_status)) {
        $sql .= " AND d.status = ?";
        $params[] = $search_status;
    }
    if (!empty($search_branch) && $user_role === 'super_admin') {
        $sql .= " AND d.branch = ?";
        $params[] = $search_branch;
    } elseif ($user_role !== 'super_admin' && !empty($user_branch)) {
        $sql .= " AND d.branch = ?";
        $params[] = $user_branch;
    }

    $sql .= " ORDER BY d.date_added DESC LIMIT 50";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// GET CATEGORIES FOR FILTER
// ============================================================
$catStmt = $conn->query("SELECT category_name FROM categories ORDER BY category_name");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// Get branches for super admin
$branches = [];
if ($user_role === 'super_admin') {
    $branchStmt = $conn->query("SELECT DISTINCT branch FROM devices WHERE branch IS NOT NULL ORDER BY branch");
    $branches = $branchStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Status options
$statusOptions = ['In Stock', 'Under Repair', 'Faulty', 'Sold', 'Disposed'];

// Helper function to safely escape HTML
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

$hasFilters = !empty($search_model) || !empty($search_category) || !empty($search_status) || !empty($search_branch);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Search Device | Mombasa Computers</title>
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
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
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
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .user-info { margin-top: 0.5rem; color: var(--gray-500); font-size: 0.85rem; }
        .user-info i { color: var(--primary); }

        /* Search Section */
        .search-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .search-title { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--gray-700); }
        .search-title i { color: var(--primary); }

        .search-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end; }
        .search-group { display: flex; flex-direction: column; gap: 0.4rem; }
        .search-group label { font-size: 0.7rem; font-weight: 600; color: var(--gray-600); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 0.3rem; }
        .search-group label i { color: var(--primary); }
        .search-group input, .search-group select { padding: 0.6rem 0.875rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.875rem; font-family: var(--font-sans); background: white; width: 100%; transition: all 0.2s ease; }
        .search-group input:focus, .search-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1); }
        .search-group input::placeholder { color: var(--gray-400); }
        .search-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

        .btn { padding: 0.6rem 1.25rem; border: none; border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease; font-family: var(--font-sans); text-decoration: none; white-space: nowrap; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-300); }
        .btn-secondary:hover { background: var(--gray-200); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn-sm { padding: 0.35rem 0.8rem; font-size: 0.75rem; }

        /* Active Filters */
        .active-filters { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-200); }
        .filter-tag { display: inline-flex; align-items: center; gap: 0.4rem; background: var(--gray-100); padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; color: var(--gray-700); border: 1px solid var(--gray-200); }
        .filter-tag .remove { color: var(--gray-500); cursor: pointer; text-decoration: none; font-size: 0.7rem; }
        .filter-tag .remove:hover { color: #ef4444; }

        /* Cards */
        .card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; }
        .card-header { background: var(--gray-50); padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200); font-weight: 600; display: flex; align-items: center; gap: 0.75rem; }
        .card-header i { color: var(--primary); }
        .card-body { padding: 1.5rem; }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; }
        .info-item { padding: 0.75rem 1rem; background: var(--gray-50); border-radius: var(--radius-lg); border: 1px solid var(--gray-200); }
        .info-label { font-size: 0.6rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 0.9rem; font-weight: 500; color: var(--gray-800); margin-top: 0.1rem; }
        .info-value code { background: var(--gray-100); padding: 0.1rem 0.4rem; border-radius: var(--radius-md); font-size: 0.8rem; }

        /* Tables */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 0.5rem; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--gray-200); }
        th { background: var(--gray-50); font-weight: 600; color: var(--gray-600); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.3px; }
        tr:hover { background: var(--gray-50); }

        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 500; background: var(--gray-100); }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-secondary { background: var(--gray-200); color: var(--gray-600); }

        .status-indicator { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 0.4rem; }
        .status-instock { background: #10b981; }
        .status-under-repair { background: #f59e0b; }
        .status-faulty { background: #ef4444; }
        .status-sold { background: #6b7280; }
        .status-disposed { background: #4b5563; }

        .empty-state { text-align: center; padding: 2rem; color: var(--gray-500); }
        .empty-state i { font-size: 3rem; color: var(--gray-300); margin-bottom: 0.5rem; display: block; }

        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .footer span { color: var(--primary); }

        .status-badge { display: inline-flex; align-items: center; gap: 0.3rem; }

        .view-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: var(--radius-md); font-size: 0.75rem; font-weight: 600; background: var(--gray-100); color: var(--gray-600); }

        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1.25rem; }
            .search-grid { grid-template-columns: 1fr; }
            .search-actions { flex-direction: column; }
            .search-actions .btn { width: 100%; justify-content: center; }
            .info-grid { grid-template-columns: 1fr 1fr; }
            table { min-width: 700px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header { padding: 1rem; }
            .page-header h1 { font-size: 1.1rem; }
            .info-grid { grid-template-columns: 1fr; }
            table { min-width: 600px; }
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-search"></i> Search Device</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="/inventory_system/dashboard/techniciandashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Search Device</span>
        </div>
        <div class="user-info">
            <i class="fas fa-store"></i> Branch: <?= safe($user_branch ?? 'All Branches') ?> &nbsp;&nbsp;|&nbsp;&nbsp;
            <i class="fas fa-user"></i> <?= safe($greeting) ?>, <?= safe(explode(' ', $user_name)[0]) ?>
        </div>
    </div>

    <!-- ===== SEARCH SECTION ===== -->
    <div class="search-section">
        <div class="search-title">
            <i class="fas fa-filter"></i> Search Devices
            <?php if ($hasFilters || !empty($search_sn)): ?>
                <span class="badge badge-info" style="font-size:0.65rem; padding:0.15rem 0.5rem;">
                    <i class="fas fa-check"></i> Filtered
                </span>
            <?php endif; ?>
        </div>

        <form method="GET" class="search-grid">
            <!-- Serial Number -->
            <div class="search-group">
                <label><i class="fas fa-hashtag"></i> Serial Number</label>
                <input type="text" name="serial" id="serial_number" placeholder="Scan or enter serial..." value="<?= safe($search_sn) ?>" autofocus>
            </div>

            <!-- Model -->
            <div class="search-group">
                <label><i class="fas fa-laptop"></i> Model</label>
                <input type="text" name="model" placeholder="Search by model..." value="<?= safe($search_model) ?>">
            </div>

            <!-- Category -->
            <div class="search-group">
                <label><i class="fas fa-tag"></i> Category</label>
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= safe($cat) ?>" <?= $search_category == $cat ? 'selected' : '' ?>>
                            <?= safe($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div class="search-group">
                <label><i class="fas fa-circle"></i> Status</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ($statusOptions as $status): ?>
                        <option value="<?= safe($status) ?>" <?= $search_status == $status ? 'selected' : '' ?>>
                            <?= safe($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Branch (Super Admin only) -->
            <?php if ($user_role === 'super_admin'): ?>
                <div class="search-group">
                    <label><i class="fas fa-store"></i> Branch</label>
                    <select name="branch">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= safe($b) ?>" <?= $search_branch == $b ? 'selected' : '' ?>>
                                <?= safe($b) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="search-group" style="grid-column: span 1;">
                <div class="search-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if ($hasFilters || !empty($search_sn)): ?>
                        <a href="search_device.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- Active Filters Display -->
        <?php if ($hasFilters || !empty($search_sn)): ?>
            <div class="active-filters">
                <span style="font-size:0.75rem; font-weight:600; color:var(--gray-500);">Active Filters:</span>
                
                <?php if (!empty($search_sn)): ?>
                    <span class="filter-tag">
                        <i class="fas fa-hashtag"></i> Serial: <?= safe($search_sn) ?>
                        <a href="?<?= http_build_query(array_diff_key($_GET, ['serial' => ''])) ?>" class="remove">
                            <i class="fas fa-times-circle"></i>
                        </a>
                    </span>
                <?php endif; ?>

                <?php if (!empty($search_model)): ?>
                    <span class="filter-tag">
                        <i class="fas fa-laptop"></i> Model: <?= safe($search_model) ?>
                        <a href="?<?= http_build_query(array_diff_key($_GET, ['model' => ''])) ?>" class="remove">
                            <i class="fas fa-times-circle"></i>
                        </a>
                    </span>
                <?php endif; ?>

                <?php if (!empty($search_category)): ?>
                    <span class="filter-tag">
                        <i class="fas fa-tag"></i> Category: <?= safe($search_category) ?>
                        <a href="?<?= http_build_query(array_diff_key($_GET, ['category' => ''])) ?>" class="remove">
                            <i class="fas fa-times-circle"></i>
                        </a>
                    </span>
                <?php endif; ?>

                <?php if (!empty($search_status)): ?>
                    <span class="filter-tag">
                        <i class="fas fa-circle"></i> Status: <?= safe($search_status) ?>
                        <a href="?<?= http_build_query(array_diff_key($_GET, ['status' => ''])) ?>" class="remove">
                            <i class="fas fa-times-circle"></i>
                        </a>
                    </span>
                <?php endif; ?>

                <?php if (!empty($search_branch) && $user_role === 'super_admin'): ?>
                    <span class="filter-tag">
                        <i class="fas fa-store"></i> Branch: <?= safe($search_branch) ?>
                        <a href="?<?= http_build_query(array_diff_key($_GET, ['branch' => ''])) ?>" class="remove">
                            <i class="fas fa-times-circle"></i>
                        </a>
                    </span>
                <?php endif; ?>

                <a href="search_device.php" class="filter-tag" style="background:#fee2e2; border-color:#fecaca; color:#991b1b;">
                    <i class="fas fa-undo"></i> Clear All
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== SINGLE DEVICE RESULTS ===== -->
    <?php if ($search_sn && !$device): ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>No device found with serial number: <strong><?= safe($search_sn) ?></strong></p>
                    <p style="font-size:0.85rem; margin-top:0.5rem; color:var(--gray-400);">
                        <a href="add_repair.php?sn=<?= urlencode($search_sn) ?>" style="color:var(--primary); text-decoration:none;">
                            <i class="fas fa-plus-circle"></i> Add this device to repair?
                        </a>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($device): ?>
        <!-- Device Details -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-laptop"></i> Device Details
                <span class="badge <?= $device['status'] === 'In Stock' ? 'badge-success' : ($device['status'] === 'Under Repair' ? 'badge-warning' : 'badge-secondary') ?>" style="margin-left:auto;">
                    <span class="status-indicator status-<?= strtolower(str_replace(' ', '-', $device['status'])) ?>"></span>
                    <?= safe($device['status']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Serial</div>
                        <div class="info-value"><code><?= safe($device['serial_number']) ?></code></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Category</div>
                        <div class="info-value"><?= safe($device['category_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Model</div>
                        <div class="info-value"><?= safe($device['model_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Processor</div>
                        <div class="info-value"><?= safe($device['processor']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">RAM</div>
                        <div class="info-value"><?= safe($device['ram']) ?> GB</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Storage</div>
                        <div class="info-value"><?= safe($device['storage_type'] . ' ' . $device['storage_capacity'] . ' GB') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Graphics</div>
                        <div class="info-value"><?= safe($device['graphics'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Touch</div>
                        <div class="info-value"><?= safe($device['touch'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Branch</div>
                        <div class="info-value"><?= safe($device['branch']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Cargo</div>
                        <div class="info-value"><?= safe($device['cargo_number'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Condition</div>
                        <div class="info-value"><?= safe($device['device_condition'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Added By</div>
                        <div class="info-value"><?= safe($device['added_by_name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date Added</div>
                        <div class="info-value"><?= date('M j, Y H:i', strtotime($device['date_added'])) ?></div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div style="margin-top:1.5rem; display:flex; gap:0.75rem; flex-wrap:wrap; padding-top:1rem; border-top:1px solid var(--gray-200);">
                    <a href="add_repair.php?mode=instock&sn=<?= urlencode($device['serial_number']) ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-tools"></i> Add to Repair
                    </a>
                    <a href="view_device.php?serial=<?= urlencode($device['serial_number']) ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-eye"></i> View Full Details
                    </a>
                    <?php if ($device['status'] === 'In Stock'): ?>
                        <a href="sell_device.php?sale_id=&serial=<?= urlencode($device['serial_number']) ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-money-bill-wave"></i> Sell Device
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Repair History -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history"></i> Repair History
                <span class="badge badge-info" style="margin-left:auto;"><?= count($repairs) ?> records</span>
            </div>
            <div class="card-body">
                <?php if (empty($repairs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <p>No repair records for this device.</p>
                        <p style="font-size:0.85rem; margin-top:0.5rem; color:var(--gray-400);">
                            <a href="add_repair.php?mode=instock&sn=<?= urlencode($device['serial_number']) ?>" style="color:var(--primary); text-decoration:none;">
                                <i class="fas fa-plus-circle"></i> Add first repair
                            </a>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Category</th>
                                    <th>Problem</th>
                                    <th>Status</th>
                                    <th>Source</th>
                                    <th>Given By</th>
                                    <th>Technician</th>
                                    <th>Cost</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($repairs as $r): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= safe($r['category_name'] ?? 'N/A') ?></td>
                                    <td><?= safe(substr($r['problem_description'] ?? '-', 0, 40)) . (strlen($r['problem_description'] ?? '') > 40 ? '...' : '') ?></td>
                                    <td>
                                        <span class="badge <?= ($r['fix_status'] ?? '') === 'Fixed' ? 'badge-success' : (($r['fix_status'] ?? '') === 'pending' ? 'badge-warning' : 'badge-secondary') ?>">
                                            <?= safe($r['fix_status'] ?? 'Unknown') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $sourceLabels = ['instock' => 'In Stock', 'return' => 'Return', 'client' => 'Client'];
                                        $source = $sourceLabels[$r['source_device'] ?? ''] ?? 'Unknown';
                                        ?>
                                        <span class="badge"><?= safe($source) ?></span>
                                    </td>
                                    <td><?= safe($r['given_by_name'] ?? '-') ?></td>
                                    <td><?= safe($r['technician_name'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($r['repair_cost']) && $r['repair_cost'] > 0): ?>
                                            <strong style="color:#065f46;">KES <?= number_format($r['repair_cost'], 2) ?></strong>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y H:i', strtotime($r['date_added'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===== BULK SEARCH RESULTS ===== -->
    <?php if (!empty($search_results) && empty($search_sn)): ?>
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Search Results
                <span class="badge badge-info" style="margin-left:auto;"><?= count($search_results) ?> devices found</span>
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Serial</th>
                                <th>Category</th>
                                <th>Model</th>
                                <th>Status</th>
                                <th>Branch</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i=1; foreach ($search_results as $d): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><code><?= safe($d['serial_number']) ?></code></td>
                                <td><?= safe($d['category_name']) ?></td>
                                <td><?= safe($d['model_name']) ?></td>
                                <td>
                                    <span class="badge <?= $d['status'] === 'In Stock' ? 'badge-success' : ($d['status'] === 'Under Repair' ? 'badge-warning' : 'badge-secondary') ?>">
                                        <span class="status-indicator status-<?= strtolower(str_replace(' ', '-', $d['status'])) ?>"></span>
                                        <?= safe($d['status']) ?>
                                    </span>
                                </td>
                                <td><span class="badge"><?= safe($d['branch']) ?></span></td>
                                <td>
                                    <a href="search_device.php?serial=<?= urlencode($d['serial_number']) ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($d['status'] === 'In Stock'): ?>
                                        <a href="add_repair.php?mode=instock&sn=<?= urlencode($d['serial_number']) ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-tools"></i> Repair
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid var(--gray-200); font-size:0.8rem; color:var(--gray-500);">
                    <i class="fas fa-info-circle"></i> Showing <?= count($search_results) ?> devices. Refine your search for more specific results.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===== INFO WHEN NO SEARCH ===== -->
    <?php if (empty($search_sn) && empty($search_model) && empty($search_category) && empty($search_status) && empty($search_branch) && !$device && empty($search_results)): ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <i class="fas fa-search-plus"></i>
                    <p>Search for devices by serial number, model, category, or status.</p>
                    <p style="font-size:0.85rem; margin-top:0.5rem; color:var(--gray-400);">
                        <i class="fas fa-info-circle"></i> Use the filters above to find devices
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===== FOOTER ===== -->
    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> <span>Mombasa Computers</span>. All rights reserved.
        <span style="margin:0 0.5rem;">•</span>
        <span>v2.0.0</span>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enter key on serial number field
    document.getElementById('serial_number').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.querySelector('button[type="submit"]').click();
        }
    });

    function adjustMainContent() {
        const main = document.querySelector('.main-content');
        if (window.innerWidth <= 1200) {
            main.style.marginLeft = '0';
            main.style.width = '100%';
            main.style.paddingTop = '5rem';
        } else {
            main.style.marginLeft = '260px';
            main.style.width = 'calc(100% - 260px)';
            main.style.paddingTop = '';
        }
    }
    window.addEventListener('resize', adjustMainContent);
    adjustMainContent();
});
</script>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>