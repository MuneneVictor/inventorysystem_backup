<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Allowed roles: software, super_admin, inventory_admin, manager
if (!in_array($_SESSION['role'], ['software', 'super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED.");
}

$user_role = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];
$params = [];

// Get user's branch if not super_admin
$user_branch = null;
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
}

// Helper function to safely escape HTML
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Filter inputs
$filter_serial = trim($_GET['serial'] ?? '');
$filter_start_date = trim($_GET['start_date'] ?? '');
$filter_end_date = trim($_GET['end_date'] ?? '');
$filter_branch = trim($_GET['branch'] ?? '');
$filter_performed_by = trim($_GET['performed_by'] ?? '');

// Get list of software users for filter
$softwareUsers = [];
if ($user_role === 'super_admin') {
    $userStmt = $conn->query("SELECT id, full_name FROM users WHERE role = 'software' ORDER BY full_name");
    $softwareUsers = $userStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Build query with COLLATE for collation compatibility
$sql = "SELECT m.*, d.model_name, d.storage_type, d.storage_capacity, 
               c.category_name, u.full_name AS performed_by_name, d.branch
        FROM maintenance m
        LEFT JOIN devices d ON m.device_serial COLLATE utf8mb4_general_ci = d.serial_number
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN users u ON m.performed_by = u.id
        WHERE 1=1";

if ($user_role === 'software') {
    $sql .= " AND m.performed_by = :performed_by";
    $params['performed_by'] = $user_id;
}

if (in_array($user_role, ['manager', 'inventory_admin']) && !empty($user_branch)) {
    $sql .= " AND d.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}

// Serial number filter
if (!empty($filter_serial)) {
    $sql .= " AND m.device_serial LIKE :serial";
    $params['serial'] = "%$filter_serial%";
}

// Performed by filter (only for super_admin)
if ($user_role === 'super_admin' && !empty($filter_performed_by)) {
    $sql .= " AND m.performed_by = :performed_by_id";
    $params['performed_by_id'] = $filter_performed_by;
}

// Branch filter (only for super_admin)
if ($user_role === 'super_admin' && !empty($filter_branch)) {
    $sql .= " AND d.branch = :branch";
    $params['branch'] = $filter_branch;
}

// Date range filter
if (!empty($filter_start_date) && !empty($filter_end_date)) {
    $sql .= " AND DATE(m.date_performed) BETWEEN :start_date AND :end_date";
    $params['start_date'] = $filter_start_date;
    $params['end_date'] = $filter_end_date;
} elseif (!empty($filter_start_date)) {
    $sql .= " AND DATE(m.date_performed) >= :start_date";
    $params['start_date'] = $filter_start_date;
} elseif (!empty($filter_end_date)) {
    $sql .= " AND DATE(m.date_performed) <= :end_date";
    $params['end_date'] = $filter_end_date;
}

$sql .= " ORDER BY m.date_performed DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get branches for super_admin filter
$branches = [];
if ($user_role === 'super_admin') {
    $branchStmt = $conn->query("SELECT DISTINCT branch FROM devices WHERE branch IS NOT NULL ORDER BY branch");
    $branches = $branchStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Calculate statistics
$totalRecords = count($logs);
$totalRamUpgraded = array_sum(array_column($logs, 'new_ram'));
$totalStorageUpgraded = array_sum(array_column($logs, 'new_storage'));
$uniqueDevices = count(array_unique(array_column($logs, 'device_serial')));

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
    <title>Maintenance Logs | Mombasa Computers</title>
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

        .stats-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .stat-card { background: white; padding: 1rem 1.5rem; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); flex: 1; min-width: 150px; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--primary); }
        .stat-card .stat-label { font-size: 0.75rem; color: var(--gray-500); }
        .stat-card .stat-icon { font-size: 1.25rem; margin-right: 0.5rem; color: var(--primary); }

        .filter-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .filter-title { font-size: 1rem; font-weight: 600; color: var(--gray-700); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.4rem; }
        .filter-group label { font-size: 0.7rem; font-weight: 600; color: var(--gray-600); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 0.3rem; }
        .filter-group label i { color: var(--primary); }
        .filter-group input, .filter-group select { padding: 0.6rem 0.875rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.875rem; background: white; font-family: var(--font-sans); width: 100%; transition: all 0.2s ease; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,75,42,0.1); }
        .filter-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: flex-end; }
        
        .btn { padding: 0.6rem 1.25rem; border: none; border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; transition: all 0.2s ease; font-family: var(--font-sans); }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-300); }
        .btn-secondary:hover { background: var(--gray-200); }
        .btn-export { background: #2563eb; color: white; }
        .btn-export:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: var(--shadow-md); }

        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; box-shadow: var(--shadow-sm); }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        th { background: var(--gray-50); padding: 0.875rem 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 2px solid var(--gray-200); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; font-size: 0.85rem; }
        tr:hover { background: var(--gray-50); }
        
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; background: var(--gray-100); color: var(--gray-600); }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .empty-state i { font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem; display: block; }
        
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .footer span { color: var(--primary); }
        
        .view-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: var(--radius-md); font-size: 0.7rem; font-weight: 600; background: var(--gray-100); color: var(--gray-600); }
        .upgrade-arrow { color: var(--success, #10b981); font-weight: 700; }

        .actions-row { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 0.5rem; }
        .link-btn { padding: 0.4rem 0.8rem; background: var(--primary); color: white !important; border-radius: var(--radius-md); text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 500; font-size: 0.85rem; transition: all 0.2s ease; border: none; cursor: pointer; }
        .link-btn:hover { background: var(--primary-light); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .link-btn-sm { padding: 0.25rem 0.6rem; font-size: 0.75rem; }

        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { 
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1.25rem; }
            .filter-grid { grid-template-columns: 1fr; }
            .filter-actions { grid-column: span 1; flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .stats-row { flex-direction: column; }
            table { min-width: 800px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header { padding: 1rem; }
            .page-header h1 { font-size: 1.1rem; }
            table { min-width: 700px; }
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Maintenance Logs</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="/inventory_system/dashboard/softwaredashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Maintenance Logs</span>
        </div>
        <div class="user-info">
            <i class="fas fa-eye"></i> View: 
            <span class="view-badge">
                <?php 
                if ($user_role === 'software') echo 'My Logs';
                elseif ($user_role === 'manager') echo 'Branch: ' . safe($user_branch);
                elseif ($user_role === 'inventory_admin') echo 'Branch: ' . safe($user_branch);
                else echo 'All Logs';
                ?>
            </span>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= number_format($totalRecords) ?></div>
            <div class="stat-label"><i class="fas fa-clipboard-list stat-icon"></i> Total Records</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($totalRamUpgraded) ?></div>
            <div class="stat-label"><i class="fas fa-microchip stat-icon"></i> RAM Upgraded (GB)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($totalStorageUpgraded) ?></div>
            <div class="stat-label"><i class="fas fa-hdd stat-icon"></i> Storage Upgraded (GB)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($uniqueDevices) ?></div>
            <div class="stat-label"><i class="fas fa-laptop stat-icon"></i> Unique Devices</div>
        </div>
    </div>

    <div class="filter-section">
        <div class="filter-title"><i class="fas fa-filter"></i> Filter Logs</div>
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label><i class="fas fa-hashtag"></i> Serial Number</label>
                <input type="text" name="serial" placeholder="Search by serial..." value="<?= safe($filter_serial) ?>">
            </div>
            
            <?php if ($user_role === 'super_admin'): ?>
                <div class="filter-group">
                    <label><i class="fas fa-user-cog"></i> Performed By</label>
                    <select name="performed_by">
                        <option value="">All Users</option>
                        <?php foreach ($softwareUsers as $u): ?>
                            <option value="<?= safe($u['id']) ?>" <?= $filter_performed_by == $u['id'] ? 'selected' : '' ?>>
                                <?= safe($u['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label><i class="fas fa-store"></i> Branch</label>
                    <select name="branch">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= safe($b) ?>" <?= $filter_branch == $b ? 'selected' : '' ?>>
                                <?= safe($b) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> From Date</label>
                <input type="date" name="start_date" value="<?= safe($filter_start_date) ?>">
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> To Date</label>
                <input type="date" name="end_date" value="<?= safe($filter_end_date) ?>">
            </div>
            
            <div class="filter-group">
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                    <a href="software_logs.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <p>No maintenance records found matching your criteria.</p>
                <?php if ($user_role === 'software'): ?>
                    <p style="font-size:0.85rem; margin-top:0.5rem; color:var(--gray-400);">
                        <a href="/inventory_system/software/update_specs.php" style="color:var(--primary); text-decoration:none;">
                            <i class="fas fa-plus-circle"></i> Perform your first update
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Serial</th>
                        <th>Category</th>
                        <th>Model</th>
                        <th>Branch</th>
                        <th>Old RAM</th>
                        <th>New RAM</th>
                        <th>Old Storage</th>
                        <th>New Storage</th>
                        <th>Old Graphics</th>
                        <th>New Graphics</th>
                        <th>Performed By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><code><?= safe($log['device_serial']) ?></code></td>
                        <td><span class="badge"><?= safe($log['category_name'] ?? '-') ?></span></td>
                        <td><?= safe($log['model_name'] ?? '-') ?></td>
                        <td><span class="badge"><?= safe($log['branch'] ?? '-') ?></span></td>
                        <td><?= safe($log['old_ram']) ?> GB</td>
                        <td><strong class="upgrade-arrow"><?= safe($log['new_ram']) ?> GB <?= ($log['new_ram'] > $log['old_ram']) ? '↑' : '' ?></strong></td>
                        <td><?= safe($log['old_storage']) ?> GB</td>
                        <td><strong class="upgrade-arrow"><?= safe($log['new_storage']) ?> GB <?= ($log['new_storage'] > $log['old_storage']) ? '↑' : '' ?></strong></td>
                        <td><?= safe($log['old_graphics'] ?? '-') ?></td>
                        <td><?= safe($log['new_graphics'] ?? '-') ?></td>
                        <td><?= safe($log['performed_by_name'] ?? '-') ?></td>
                        <td><?= date('M j, Y H:i', strtotime($log['date_performed'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Quick Action Buttons -->
    <div class="actions-row">
        <a href="/inventory_system/software/update_specs.php" class="link-btn">
            <i class="fas fa-microchip"></i> Update Specs
        </a>
        <a href="/inventory_system/search/search_device.php" class="link-btn link-btn-sm">
            <i class="fas fa-search"></i> Search Device
        </a>
        <?php if (!empty($logs)): ?>
            <a href="export_maintenance_logs.php?<?= http_build_query($_GET) ?>" class="link-btn link-btn-sm" style="background:#2563eb;">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
        <?php endif; ?>
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