<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

if (!in_array($user_role, ['super_admin', 'inventory_admin', 'manager', 'technician'])) {
    die("ACCESS DENIED.");
}

// Get user branch for non-super_admin
$user_branch = null;
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
}

// Filter inputs
$filter_serial = trim($_GET['serial'] ?? '');
$filter_branch = trim($_GET['branch'] ?? '');
$filter_start = trim($_GET['start_date'] ?? '');
$filter_end = trim($_GET['end_date'] ?? '');
$filter_source = trim($_GET['source'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_has_cost = isset($_GET['has_cost']) ? (int)$_GET['has_cost'] : '';

// Build query with all filters
$sql = "SELECT r.*, d.model_name, d.processor, d.ram, d.storage_type, d.storage_capacity, d.touch, d.graphics,
               c.category_name, u1.full_name AS fixed_by_name, u2.full_name AS given_by_name,
               r.source_device
        FROM repairs r
        LEFT JOIN devices d ON r.serial_number COLLATE utf8mb4_general_ci = d.serial_number
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN users u1 ON r.added_by = u1.id
        LEFT JOIN users u2 ON r.given_by = u2.id
        WHERE 1=1";
$params = [];

// Role-based filtering
if ($user_role === 'technician') {
    $sql .= " AND r.added_by = ?";
    $params[] = $user_id;
} elseif (in_array($user_role, ['inventory_admin', 'manager'])) {
    if ($user_branch) {
        $sql .= " AND r.branch = ?";
        $params[] = $user_branch;
    } else {
        $sql .= " AND 1=0";
    }
}

// Status filter
if (!empty($filter_status)) {
    if ($filter_status === 'pending') {
        $sql .= " AND r.fix_status = 'pending'";
    } elseif ($filter_status === 'fixed') {
        $sql .= " AND r.fix_status = 'Fixed'";
    } elseif ($filter_status === 'all') {
        // Show all statuses
    }
} else {
    // Default: show all except 'Not Fixed' (legacy)
    $sql .= " AND r.fix_status IN ('pending', 'Fixed')";
}

// Serial filter
if (!empty($filter_serial)) {
    $sql .= " AND r.serial_number LIKE ?";
    $params[] = "%$filter_serial%";
}

// Branch filter (super admin only)
if ($user_role === 'super_admin' && !empty($filter_branch)) {
    $sql .= " AND r.branch = ?";
    $params[] = $filter_branch;
}

// Source filter
if (!empty($filter_source)) {
    $sql .= " AND r.source_device = ?";
    $params[] = $filter_source;
}

// Cost filter
if ($filter_has_cost === '1') {
    $sql .= " AND r.repair_cost IS NOT NULL AND r.repair_cost > 0";
} elseif ($filter_has_cost === '0') {
    $sql .= " AND (r.repair_cost IS NULL OR r.repair_cost = 0)";
}

// Date range filters
if (!empty($filter_start) && !empty($filter_end)) {
    $sql .= " AND DATE(r.date_added) BETWEEN ? AND ?";
    $params[] = $filter_start;
    $params[] = $filter_end;
} elseif (!empty($filter_start)) {
    $sql .= " AND DATE(r.date_added) >= ?";
    $params[] = $filter_start;
} elseif (!empty($filter_end)) {
    $sql .= " AND DATE(r.date_added) <= ?";
    $params[] = $filter_end;
}

$sql .= " ORDER BY r.date_added DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get branches for super_admin filter
$branches = [];
if ($user_role === 'super_admin') {
    $branchStmt = $conn->query("SELECT DISTINCT branch FROM repairs WHERE branch IS NOT NULL ORDER BY branch");
    $branches = $branchStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Source options for filter
$sourceOptions = ['instock' => 'In Stock', 'return' => 'Return', 'client' => 'Client'];

// Status options for filter
$statusOptions = [
    'all' => 'All Statuses',
    'pending' => 'Pending',
    'fixed' => 'Fixed'
];

date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Helper function to get source display
function getSourceDisplay($source) {
    $sources = [
        'instock' => ['label' => 'In Stock', 'class' => 'badge-instock', 'icon' => 'fa-warehouse'],
        'return' => ['label' => 'Return', 'class' => 'badge-return', 'icon' => 'fa-undo-alt'],
        'client' => ['label' => 'Client', 'class' => 'badge-client', 'icon' => 'fa-user']
    ];
    return $sources[$source] ?? ['label' => 'Unknown', 'class' => 'badge-unknown', 'icon' => 'fa-question-circle'];
}

// Helper function to get status display
function getStatusDisplay($status) {
    $statuses = [
        'Fixed' => ['label' => 'Fixed', 'class' => 'badge-success'],
        'pending' => ['label' => 'Pending', 'class' => 'badge-warning'],
        'Not Fixed' => ['label' => 'Not Fixed', 'class' => 'badge-danger']
    ];
    return $statuses[$status] ?? ['label' => $status, 'class' => 'badge-secondary'];
}

// Helper function to safely escape HTML
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Calculate statistics
$totalRepairs = count($repairs);
$pendingCount = 0;
$fixedCount = 0;
$withCost = 0;
$withoutCost = 0;

foreach ($repairs as $r) {
    if (($r['fix_status'] ?? '') === 'pending') {
        $pendingCount++;
    } elseif (($r['fix_status'] ?? '') === 'Fixed') {
        $fixedCount++;
    }
    
    if (!empty($r['repair_cost']) && $r['repair_cost'] > 0) {
        $withCost++;
    } else {
        $withoutCost++;
    }
}

// Build query string for export
$query_string = http_build_query([
    'serial' => $filter_serial,
    'branch' => $filter_branch,
    'start_date' => $filter_start,
    'end_date' => $filter_end,
    'source' => $filter_source,
    'status' => $filter_status,
    'has_cost' => $filter_has_cost
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Repair Logs | Mombasa Computers</title>
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
        
        .stats-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .stat-card { background: white; padding: 1rem 1.5rem; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); flex: 1; min-width: 120px; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--primary); }
        .stat-card .stat-label { font-size: 0.75rem; color: var(--gray-500); }
        .stat-card .stat-icon { font-size: 1.25rem; margin-right: 0.5rem; }
        
        .filter-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .filter-title { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--gray-700); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-size: 0.75rem; font-weight: 600; color: var(--gray-600); text-transform: uppercase; letter-spacing: 0.3px; }
        .filter-group input, .filter-group select { padding: 0.625rem 0.875rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.875rem; background: white; font-family: var(--font-sans); }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1); }
        .filter-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        
        .btn { padding: 0.625rem 1.25rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-size: 0.875rem; font-weight: 600; transition: all 0.2s ease; font-family: var(--font-sans); }
        .btn:hover { background: var(--primary-light); transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn-secondary { background: var(--gray-500); }
        .btn-secondary:hover { background: var(--gray-600); }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .btn-export { background: #2563eb; }
        .btn-export:hover { background: #1d4ed8; }
        
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; box-shadow: var(--shadow-sm); }
        table { width: 100%; border-collapse: collapse; min-width: 1400px; }
        th { background: var(--gray-50); padding: 0.875rem 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 2px solid var(--gray-200); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; font-size: 0.85rem; }
        tr:hover { background: var(--gray-50); }
        
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; background: var(--gray-100); color: var(--gray-600); }
        .badge-instock { background: #dcfce7; color: #065f46; }
        .badge-return { background: #fef3c7; color: #92400e; }
        .badge-client { background: #dbeafe; color: #1e40af; }
        .badge-unknown { background: var(--gray-200); color: var(--gray-600); }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: var(--gray-200); color: var(--gray-600); }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        .source-icon { margin-right: 0.3rem; }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .empty-state i { font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .footer span { color: var(--primary); }
        .cost-positive { color: #065f46; font-weight: 600; }
        .cost-none { color: var(--gray-400); }
        
        .status-indicator { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 0.4rem; }
        .status-fixed { background: #10b981; }
        .status-pending { background: #f59e0b; }
        
        .text-success { color: #065f46; }
        .text-warning { color: #92400e; }
        .text-danger { color: #991b1b; }
        
        .view-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--gray-100);
            color: var(--gray-600);
        }
        
        .actions-row {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        .link-btn {
            padding: 0.4rem 0.8rem;
            background: var(--primary);
            color: white !important;
            border-radius: var(--radius-md);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        .link-btn:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .link-btn-sm {
            padding: 0.25rem 0.6rem;
            font-size: 0.75rem;
        }
        
        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1.25rem; }
            .filter-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; justify-content: center; }
            .stats-row { flex-direction: column; }
            table { min-width: 1000px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header { padding: 1rem; }
            .page-header h1 { font-size: 1.1rem; }
            table { min-width: 800px; }
            .stats-row .stat-card { min-width: 100%; }
        }
    </style>
</head>
<body>
    <?php require_once "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Repair Logs</h1>
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
            <span>Repair Logs</span>
        </div>
        <div class="user-info" style="margin-top:0.5rem; color:var(--gray-500); font-size:0.85rem;">
            <i class="fas fa-eye"></i> View: 
            <span class="view-badge">
                <?php 
                if ($user_role === 'technician') echo 'My Repairs';
                elseif ($user_role === 'inventory_admin') echo 'Given By Me';
                elseif ($user_role === 'manager') echo 'Branch: ' . safe($user_branch);
                else echo 'All Repairs';
                ?>
            </span>
        </div>
    </div>

    <!-- Statistics Row -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= $totalRepairs ?></div>
            <div class="stat-label"><i class="fas fa-clipboard-list stat-icon"></i> Total Repairs</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #10b981;"><?= $fixedCount ?></div>
            <div class="stat-label"><i class="fas fa-check-circle stat-icon" style="color: #10b981;"></i> Fixed</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #f59e0b;"><?= $pendingCount ?></div>
            <div class="stat-label"><i class="fas fa-clock stat-icon" style="color: #f59e0b;"></i> Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #065f46;"><?= $withCost ?></div>
            <div class="stat-label"><i class="fas fa-money-bill-wave stat-icon" style="color: #065f46;"></i> With Cost</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: var(--gray-500);"><?= $withoutCost ?></div>
            <div class="stat-label"><i class="fas fa-times-circle stat-icon" style="color: var(--gray-500);"></i> No Cost</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <div class="filter-title"><i class="fas fa-filter"></i> Filter Logs</div>
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label><i class="fas fa-hashtag"></i> Serial Number</label>
                <input type="text" name="serial" placeholder="Search by serial..." value="<?= safe($filter_serial) ?>">
            </div>
            
            <?php if ($user_role === 'super_admin'): ?>
                <div class="filter-group">
                    <label><i class="fas fa-store"></i> Branch</label>
                    <select name="branch">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= safe($b) ?>" <?= $filter_branch == $b ? 'selected' : '' ?>><?= safe($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="filter-group">
                <label><i class="fas fa-tag"></i> Source</label>
                <select name="source">
                    <option value="">All Sources</option>
                    <?php foreach ($sourceOptions as $key => $label): ?>
                        <option value="<?= safe($key) ?>" <?= $filter_source == $key ? 'selected' : '' ?>><?= safe($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-circle"></i> Status</label>
                <select name="status">
                    <?php foreach ($statusOptions as $key => $label): ?>
                        <option value="<?= safe($key) ?>" <?= $filter_status == $key ? 'selected' : '' ?>><?= safe($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-money-bill-wave"></i> Has Cost</label>
                <select name="has_cost">
                    <option value="">All</option>
                    <option value="1" <?= $filter_has_cost === '1' ? 'selected' : '' ?>>With Cost</option>
                    <option value="0" <?= $filter_has_cost === '0' ? 'selected' : '' ?>>No Cost</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> From Date</label>
                <input type="date" name="start_date" value="<?= safe($filter_start) ?>">
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> To Date</label>
                <input type="date" name="end_date" value="<?= safe($filter_end) ?>">
            </div>
            
            <div class="filter-group">
                <div class="filter-actions">
                    <button type="submit" class="btn"><i class="fas fa-search"></i> Filter</button>
                    <a href="repair_logs.php" class="btn btn-secondary">Reset</a>
                    <?php if (!empty($repairs)): ?>
                        <a href="export_repair_logs.php?<?= $query_string ?>" class="btn btn-export">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
        <?php if (empty($repairs)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <p>No repair logs found.</p>
                <p style="font-size:0.85rem; margin-top:0.5rem; color:var(--gray-400);">
                    <a href="add_repair.php" style="color:var(--primary); text-decoration:none;">
                        <i class="fas fa-plus-circle"></i> Add a new repair
                    </a>
                </p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Serial</th>
                        <th>Category</th>
                        <th>Model</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Problem</th>
                        <th>Client</th>
                        <th>Given By</th>
                        <th>Fixed By</th>
                        <th>Branch</th>
                        <th>Cost (KES)</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; foreach ($repairs as $r): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><code><?= safe($r['serial_number'] ?? '') ?></code></td>
                        <td><span class="badge"><?= safe($r['category_name'] ?? 'N/A') ?></span></td>
                        <td><?= safe($r['model_name'] ?? 'N/A') ?></td>
                        <td>
                            <?php 
                            $sourceInfo = getSourceDisplay($r['source_device'] ?? '');
                            ?>
                            <span class="badge <?= $sourceInfo['class'] ?>">
                                <i class="fas <?= $sourceInfo['icon'] ?> source-icon"></i>
                                <?= $sourceInfo['label'] ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $statusInfo = getStatusDisplay($r['fix_status'] ?? '');
                            ?>
                            <span class="badge <?= $statusInfo['class'] ?>">
                                <?php if (($r['fix_status'] ?? '') === 'Fixed'): ?>
                                    <span class="status-indicator status-fixed"></span>
                                <?php elseif (($r['fix_status'] ?? '') === 'pending'): ?>
                                    <span class="status-indicator status-pending"></span>
                                <?php endif; ?>
                                <?= $statusInfo['label'] ?>
                            </span>
                        </td>
                        <td><?= safe(substr($r['problem_description'] ?? '', 0, 40)) . (strlen($r['problem_description'] ?? '') > 40 ? '...' : '') ?></td>
                        <td><?= safe($r['client_name'] ?? 'N/A') ?></td>
                        <td><?= safe($r['given_by_name'] ?? 'N/A') ?></td>
                        <td><?= safe($r['fixed_by_name'] ?? 'Unknown') ?></td>
                        <td><span class="badge"><?= safe($r['branch'] ?? 'N/A') ?></span></td>
                        <td>
                            <?php if (!empty($r['repair_cost']) && $r['repair_cost'] > 0): ?>
                                <span class="cost-positive"><?= number_format($r['repair_cost'], 2) ?></span>
                            <?php else: ?>
                                <span class="cost-none">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($r['date_added']) ? date('M j, Y H:i', strtotime($r['date_added'])) : 'N/A' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Quick Action Buttons -->
    <div class="actions-row">
        <a href="add_repair.php" class="link-btn"><i class="fas fa-plus-circle"></i> Add New Repair</a>
        <a href="under_repair.php" class="link-btn link-btn-sm"><i class="fas fa-tools"></i> Under Repair</a>
        <a href="repair_logs.php?status=fixed" class="link-btn link-btn-sm"><i class="fas fa-check-circle"></i> Fixed Only</a>
        <a href="repair_logs.php?status=pending" class="link-btn link-btn-sm"><i class="fas fa-clock"></i> Pending Only</a>
    </div>

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