<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Allowed roles: software, super_admin, inventory_admin, manager
if (!in_array($_SESSION['role'], ['software', 'super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED.");
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$params = [];

// Get user's branch if not super_admin
$user_branch = null;
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
}

// Filter inputs
$filter_serial = trim($_GET['serial'] ?? '');
$filter_start_date = trim($_GET['start_date'] ?? '');
$filter_end_date = trim($_GET['end_date'] ?? '');
$filter_branch = trim($_GET['branch'] ?? '');

$sql = "SELECT m.*, d.model_name, d.storage_type, d.storage_capacity, c.category_name, 
               u.full_name AS performed_by_name, d.branch
        FROM maintenance m
        LEFT JOIN devices d ON m.device_serial = d.serial_number
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN users u ON m.performed_by = u.id
        WHERE 1";

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
        .stats-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .stat-card { background: white; padding: 1rem 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); flex: 1; min-width: 150px; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--primary); }
        .stat-card .stat-label { font-size: 0.8rem; color: var(--gray-500); }
        .filter-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .filter-title { font-size: 1rem; font-weight: 500; color: var(--gray-700); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-size: 0.85rem; font-weight: 500; color: var(--gray-600); }
        .filter-group input, .filter-group select { padding: 0.625rem 0.875rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; font-family: var(--font-sans); }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,75,42,0.1); }
        .filter-actions { display: flex; gap: 0.75rem; align-items: flex-end; }
        .btn { padding: 0.625rem 1.25rem; border: none; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-300); }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th { background: var(--gray-50); padding: 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        td { padding: 0.9rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: var(--gray-100); }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .filter-grid { grid-template-columns: 1fr; } .filter-actions { grid-column: span 1; } .btn { width: 100%; justify-content: center; } .stats-row { flex-direction: column; } }
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
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= count($logs) ?></div><div class="stat-label">Total Records</div></div>
        <div class="stat-card"><div class="stat-value"><?= array_sum(array_column($logs, 'new_ram')) ?></div><div class="stat-label">RAM Upgrades (GB)</div></div>
        <div class="stat-card"><div class="stat-value"><?= array_sum(array_column($logs, 'new_storage')) ?></div><div class="stat-label">Storage Upgrades (GB)</div></div>
    </div>

    <div class="filter-section">
        <div class="filter-title"><i class="fas fa-filter"></i> Filter Logs</div>
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label>Serial Number</label>
                <input type="text" name="serial" placeholder="Search by serial number..." value="<?= htmlspecialchars($filter_serial) ?>">
            </div>
            <div class="filter-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($filter_start_date) ?>">
            </div>
            <div class="filter-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($filter_end_date) ?>">
            </div>
            <?php if ($user_role === 'super_admin'): ?>
                <div class="filter-group">
                    <label>Branch</label>
                    <select name="branch">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>" <?= $filter_branch == $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                <a href="software_logs.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <?php if (empty($logs)): ?>
            <div class="empty-state"><i class="fas fa-clipboard-list"></i><p>No maintenance records found matching your criteria.</p></div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Serial Number</th>
                        <th>Category</th>
                        <th>Model</th>
                        <th>Old RAM</th>
                        <th>New RAM</th>
                        <th>Old Storage</th>
                        <th>New Storage</th>
                        <th>Old Graphics</th>
                        <th>New Graphics</th>
                        <th>Performed By</th>
                        <th>Branch</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><code><?= htmlspecialchars($log['device_serial']) ?></code></td>
                        <td><span class="badge"><?= htmlspecialchars($log['category_name'] ?? '-') ?></span></td>
                        <td><?= htmlspecialchars($log['model_name'] ?? '-') ?></td>
                        <td><?= $log['old_ram'] ?> GB</td>
                        <td><strong><?= $log['new_ram'] ?> GB</strong></td>
                        <td><?= $log['old_storage'] ?> GB</td>
                        <td><strong><?= $log['new_storage'] ?> GB</strong></td>
                        <td><?= htmlspecialchars($log['old_graphics'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($log['new_graphics'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($log['performed_by_name'] ?? '-') ?></td>
                        <td><span class="badge"><?= htmlspecialchars($log['branch'] ?? '-') ?></span></td>
                        <td><?= date('M j, Y H:i', strtotime($log['date_performed'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
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