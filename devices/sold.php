<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'])) {
    die("Access denied!");
}

// Helper: build device specifications string (like sales_logs)
function buildDeviceSpecs($device) {
    $specs = "";
    if (!empty($device['model_name'])) $specs .= $device['model_name'];
    if (!empty($device['processor'])) $specs .= " | " . $device['processor'];
    if (!empty($device['ram'])) $specs .= " | " . $device['ram'] . "GB RAM";
    if (!empty($device['storage_type']) && !empty($device['storage_capacity'])) {
        $specs .= " | " . $device['storage_type'] . " " . $device['storage_capacity'] . "GB";
    }
    if (isset($device['graphics']) && $device['graphics'] !== '' && $device['graphics'] !== 'None') {
        $specs .= " | " . $device['graphics'];
    }
    if (isset($device['touch']) && $device['touch'] !== 'N/A' && $device['touch'] !== '') {
        $specs .= " | " . $device['touch'];
    }
    return trim($specs, " |");
}

// Get manager's branch from database
if ($role === 'manager') {
    $user_stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_branch = $user_data['branch'] ?? '';
}

// Get filter inputs
$search_serial = trim($_GET['serial_number'] ?? '');
$search_category = trim($_GET['category'] ?? '');
$search_model = trim($_GET['model'] ?? '');
$search_branch = trim($_GET['branch'] ?? '');
$search_place = trim($_GET['place'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

// Fetch categories for dropdown
$cat_stmt = $conn->prepare("SELECT * FROM categories ORDER BY category_name ASC");
$cat_stmt->execute();
$all_categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Base query – only Sold devices, plus join for added_by and sold_by
$sql = "SELECT d.*, c.category_name, 
               u_added.full_name AS added_by_name,
               u_sold.full_name AS sold_by_name
        FROM devices d
        JOIN categories c ON d.category_id = c.id
        LEFT JOIN users u_added ON d.added_by = u_added.id
        LEFT JOIN users u_sold ON d.sold_by = u_sold.id
        WHERE d.status = 'Sold'";
$params = [];

// Manager restriction
if ($role === 'manager' && !empty($user_branch)) {
    $sql .= " AND d.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}

// Category filter
if ($search_category !== '') {
    $sql .= " AND d.category_id = :cat";
    $params['cat'] = $search_category;
}

// Branch filter
if ($search_branch !== '' && $role !== 'manager') {
    $sql .= " AND d.branch = :branch";
    $params['branch'] = $search_branch;
}

// Place filter
if ($search_place !== '') {
    $sql .= " AND d.place = :place";
    $params['place'] = $search_place;
}

// Model filter
if ($search_model !== '') {
    $sql .= " AND d.model_name LIKE :model";
    $params['model'] = "%$search_model%";
}

// Serial filter
if ($search_serial !== '') {
    $sql .= " AND d.serial_number LIKE :sn";
    $params['sn'] = "%$search_serial%";
}

// Date range filter (sold_at)
if ($date_from !== '') {
    $sql .= " AND DATE(d.sold_at) >= :date_from";
    $params['date_from'] = $date_from;
}
if ($date_to !== '') {
    $sql .= " AND DATE(d.sold_at) <= :date_to";
    $params['date_to'] = $date_to;
}

$sql .= " ORDER BY d.sold_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_sold = count($devices);
$total_revenue = array_sum(array_column($devices, 'selling_price'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sold Devices | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Same styles as device_list.php – reused fully */
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        .stat-card .stat-icon { font-size: 1.75rem; color: var(--primary); margin-bottom: 0.5rem; }
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
        .search-group input,
        .search-group select {
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            background: white;
        }
        .search-group input:focus,
        .search-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26,75,42,0.1);
        }

        .search-actions {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
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
            min-width: 900px;
        }

        th {
            background: var(--gray-50);
            padding: 1rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-600);
            font-size: 0.85rem;
            border-bottom: 1px solid var(--gray-200);
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

        .badge-place-display { background: #dbeafe; color: #1e40af; }
        .badge-place-store { background: #d1fae5; color: #065f46; }
        .badge-place-warehouse { background: #fed7aa; color: #92400e; }

        .status-sold { color: var(--gray-500); }

        .serial-code {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: var(--gray-50);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            display: inline-block;
        }

        .branch-kimathi { color: #059669; }
        .branch-moi { color: #3b82f6; }

        .specs-text {
            font-size: 0.8rem;
            color: var(--gray-600);
            word-wrap: break-word;
            max-width: 350px;
            display: inline-block;
        }

        .action-btns { display: flex; gap: 0.5rem; flex-wrap: wrap; }

        .btn-view {
            padding: 0.375rem 0.875rem;
            font-size: 0.8rem;
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: all 0.2s ease;
        }
        .btn-view:hover { background: var(--gray-50); border-color: var(--gray-400); }

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

        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }

        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .stats-row { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .stat-card { padding: 1rem; }
            .stat-card .stat-value { font-size: 1.5rem; }
            .search-section { padding: 1rem; }
            .search-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; justify-content: center; }
            .action-btns { flex-direction: column; }
            .btn-view { width: 100%; justify-content: center; }
            .specs-text { max-width: 150px; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .stats-row { grid-template-columns: 1fr; }
            .page-header h1 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-check-circle"></i>
            Sold Devices
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
            <span>Sold Devices</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="stat-value"><?= number_format($total_sold) ?></div>
            <div class="stat-label">Total Sold</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-value">Ksh <?= number_format($total_revenue, 0) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-tag"></i></div>
            <div class="stat-value"><?= number_format(count($all_categories)) ?></div>
            <div class="stat-label">Categories</div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-title">
            <i class="fas fa-filter"></i> Filter Sold Devices
        </div>
        <form method="GET" class="search-grid">
            <div class="search-group">
                <label>Category</label>
                <select name="category">
                    <option value="">-- All Categories --</option>
                    <?php foreach($all_categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $search_category == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($role !== 'manager'): ?>
            <div class="search-group">
                <label>Branch</label>
                <select name="branch">
                    <option value="">-- All Branches --</option>
                    <option value="KIMATHI" <?= $search_branch == 'KIMATHI' ? 'selected' : '' ?>>KIMATHI</option>
                    <option value="MOI" <?= $search_branch == 'MOI' ? 'selected' : '' ?>>MOI</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="search-group">
                <label>Place</label>
                <select name="place">
                    <option value="">-- All Places --</option>
                    <option value="display" <?= $search_place == 'display' ? 'selected' : '' ?>>Display</option>
                    <option value="store" <?= $search_place == 'store' ? 'selected' : '' ?>>Store</option>
                    <option value="warehouse" <?= $search_place == 'warehouse' ? 'selected' : '' ?>>Warehouse</option>
                </select>
            </div>

            <div class="search-group">
                <label>Model</label>
                <input type="text" name="model" placeholder="Search by model..." value="<?= htmlspecialchars($search_model) ?>">
            </div>

            <div class="search-group">
                <label>Serial Number</label>
                <input type="text" name="serial_number" placeholder="Scan or type serial number" value="<?= htmlspecialchars($search_serial) ?>" autofocus>
            </div>

            <div class="search-group">
                <label>Sold Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" max="<?= date('Y-m-d') ?>">
            </div>

            <div class="search-group">
                <label>Sold Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" max="<?= date('Y-m-d') ?>">
            </div>

            <div class="search-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="sold_devices.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
        <div class="table-responsive">
            <?php if(empty($devices)): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>No sold devices found matching your criteria.</p>
                    <a href="sold_devices.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-undo"></i> Clear Filters
                    </a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Serial Number</th>
                            <th>Category</th>
                            <th>Model</th>
                            <th>Specifications</th>
                            <th>Place</th>
                            <th>Added By</th>
                            <th>Price (KES)</th>
                            <th>Selling Price (KES)</th>
                            <th>Sold By</th>
                            <th>Sold At</th>
                            <th>Branch</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i = 1; foreach ($devices as $d): 
                        $specs = buildDeviceSpecs($d);
                        $placeClass = '';
                        if ($d['place'] == 'display') $placeClass = 'badge-place-display';
                        elseif ($d['place'] == 'store') $placeClass = 'badge-place-store';
                        elseif ($d['place'] == 'warehouse') $placeClass = 'badge-place-warehouse';
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><span class="serial-code"><?= htmlspecialchars($d['serial_number']) ?></span></td>
                            <td><span class="badge"><?= htmlspecialchars($d['category_name']) ?></span></td>
                            <td><strong><?= htmlspecialchars($d['model_name']) ?></strong></td>
                            <td>
                                <span class="specs-text" title="<?= htmlspecialchars($specs) ?>">
                                    <?= htmlspecialchars($specs ?: '-') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $placeClass ?>">
                                    <?= ucfirst($d['place'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($d['added_by_name'] ?? 'System') ?></td>
                            <td><?= $d['price'] !== null ? number_format($d['price'], 2) : '—' ?></td>
                            <td><?= $d['selling_price'] !== null ? number_format($d['selling_price'], 2) : '—' ?></td>
                            <td><?= htmlspecialchars($d['sold_by_name'] ?? 'Unknown') ?></td>
                            <td><small><?= $d['sold_at'] ? date('M j, Y g:i A', strtotime($d['sold_at'])) : '—' ?></small></td>
                            <td>
                                <span class="<?= $d['branch'] == 'KIMATHI' ? 'branch-kimathi' : 'branch-moi' ?>">
                                    <?= htmlspecialchars($d['branch']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a class="btn-view" href="view_device.php?sn=<?= urlencode($d['serial_number']) ?>">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
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

<script>
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