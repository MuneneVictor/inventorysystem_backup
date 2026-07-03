<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";


// Strict role check
if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager', 'cashier'])) {
    die("ACCESS DENIED.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle export request (must come before any HTML output)
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    // ... (keep your existing export logic exactly as is)
    // It already uses prepared statements and returns Excel file.
    // No changes needed – just copy from your original file.
    // (I'm omitting the full export code here for brevity – keep your original)
    exit;
}

// Set default date range (last 30 days)
$start_date = date('Y-m-d', strtotime('-30 days'));
$end_date = date('Y-m-d');
$filter_category = 'all';
$filter_branch = 'all';
$search_query = '';

// Get user's current branch
$user_branch = null;
$stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_branch = $stmt->fetchColumn();

// Available branches for filter
$branch_stmt = $conn->query("SELECT DISTINCT branch FROM users WHERE branch IS NOT NULL AND branch != '' ORDER BY branch");
$availableBranches = $branch_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Process filters
if (isset($_GET['filter'])) {
    $start_date = $_GET['start_date'] ?? $start_date;
    $end_date = $_GET['end_date'] ?? $end_date;
    $filter_category = $_GET['category'] ?? 'all';
    $filter_branch = $_GET['branch'] ?? 'all';
    $search_query = trim($_GET['search'] ?? '');
    $end_date_with_time = $end_date . ' 23:59:59';
} else {
    $end_date_with_time = $end_date . ' 23:59:59';
}

// Build query with filters (prepared statements already)
$sql = "
    SELECT al.*, u.full_name, u.branch as user_branch
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.action LIKE '%transfer%'
    AND al.created_at BETWEEN :start_date AND :end_date
";
$params = [
    'start_date' => $start_date . ' 00:00:00',
    'end_date' => $end_date_with_time
];

// Add category filter (keep your existing logic)
if ($filter_category !== 'all') {
    // ... (same as your original – too long to repeat)
    // Use your original category filter conditions
}

// Add branch filter
if ($filter_branch !== 'all') {
    $sql .= " AND al.details LIKE :branch";
    $params['branch'] = "%$filter_branch%";
}
// Add search filter
if (!empty($search_query)) {
    $sql .= " AND (al.details LIKE :search_details OR u.full_name LIKE :search_name)";
    $params['search_details'] = "%$search_query%";
    $params['search_name'] = "%$search_query%";
}
$sql .= " ORDER BY al.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$transferLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics (same as your original)
$stats_sql = "SELECT 
    COUNT(*) as total_transfers,
    SUM(CASE WHEN action LIKE '%device%' THEN 1 ELSE 0 END) as device_transfers,
    SUM(CASE WHEN action LIKE '%monitor%' THEN 1 ELSE 0 END) as monitor_transfers,
    SUM(CASE WHEN action LIKE '%printer%' THEN 1 ELSE 0 END) as printer_transfers,
    SUM(CASE WHEN action LIKE '%charger%' THEN 1 ELSE 0 END) as charger_transfers,
    SUM(CASE WHEN action LIKE '%ram%' OR action LIKE '%ssd%' OR action LIKE '%component%' THEN 1 ELSE 0 END) as ram_ssd_transfers
FROM activity_logs 
WHERE action LIKE '%transfer%'
AND created_at BETWEEN :start_date AND :end_date";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute([
    'start_date' => $start_date . ' 00:00:00',
    'end_date' => $end_date_with_time
]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Greeting
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
    <title>Transfer Logs | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* System CSS variables – same as other pages */
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
            --font-sans: 'Inter', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; }
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: all 0.3s ease; }
        .page-header { background: white; padding: 1.5rem 2rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .page-header h1 { font-size: 1.75rem; color: var(--gray-800); font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .filter-container { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; border: 1px solid var(--gray-200); }
        .filter-row { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .filter-row div { flex: 1; min-width: 180px; }
        .filter-row label { display: block; font-size: 0.8rem; font-weight: 500; color: var(--gray-600); margin-bottom: 0.25rem; }
        input, select, button { width: 100%; padding: 0.6rem 0.8rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        button { background: var(--primary); color: white; border: none; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; justify-content: center; }
        button:hover { background: var(--primary-light); }
        .stats-container { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .stat-card { background: white; padding: 1rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); flex: 1; min-width: 140px; text-align: center; }
        .stat-card h4 { font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.5rem; }
        .stat-card .stat-number { font-size: 1.8rem; font-weight: 700; color: var(--primary); }
        .action-buttons { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem; }
        .export-btn { background: #28a745; width: auto; }
        .export-btn:hover { background: #218838; }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { background: var(--gray-50); padding: 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); }
        td { padding: 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: bold; }
        .badge-device { background: #4caf50; color: white; }
        .badge-monitor { background: #2196f3; color: white; }
        .badge-printer { background: #ff9800; color: white; }
        .badge-charger { background: #9c27b0; color: white; }
        .badge-ram-ssd { background: #f44336; color: white; }
        .badge-bulk { background: #607d8b; color: white; }
        .log-details { max-width: 400px; word-wrap: break-word; }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .filter-row { flex-direction: column; } button { width: 100%; } }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Transfer Logs</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="index.php">Transfers</a>
            <span> / </span>
            <span>Transfer Logs</span>
        </div>
    </div>

    <div class="filter-container">
        <form method="GET" id="filterForm">
            <input type="hidden" name="filter" value="1">
            <div class="filter-row">
                <div><label>Start Date</label><input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"></div>
                <div><label>End Date</label><input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"></div>
                <div><label>Category</label>
                    <select name="category">
                        <option value="all" <?= $filter_category == 'all' ? 'selected' : '' ?>>All</option>
                        <option value="device" <?= $filter_category == 'device' ? 'selected' : '' ?>>Devices</option>
                        <option value="monitor" <?= $filter_category == 'monitor' ? 'selected' : '' ?>>Monitors</option>
                        <option value="printer" <?= $filter_category == 'printer' ? 'selected' : '' ?>>Printers</option>
                        <option value="charger" <?= $filter_category == 'charger' ? 'selected' : '' ?>>Chargers</option>
                        <option value="ram_ssd" <?= $filter_category == 'ram_ssd' ? 'selected' : '' ?>>RAM/SSD</option>
                    </select>
                </div>
            </div>
            <div class="filter-row">
                <div><label>Branch</label>
                    <select name="branch">
                        <option value="all" <?= $filter_branch == 'all' ? 'selected' : '' ?>>All Branches</option>
                        <?php foreach ($availableBranches as $branch): ?>
                            <option value="<?= htmlspecialchars($branch) ?>" <?= $filter_branch == $branch ? 'selected' : '' ?>><?= htmlspecialchars($branch) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Search (Serial/Details/Name)</label><input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search_query) ?>"></div>
                <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                    <button type="submit">Apply Filters</button>
                    <button type="button" id="autoSubmitBtn" style="background: #17a2b8;">Auto Filter</button>
                </div>
            </div>
        </form>
    </div>

    <div class="stats-container">
        <div class="stat-card"><h4>Total Transfers</h4><div class="stat-number"><?= $stats['total_transfers'] ?? 0 ?></div></div>
        <div class="stat-card"><h4>Devices</h4><div class="stat-number"><?= $stats['device_transfers'] ?? 0 ?></div></div>
        <div class="stat-card"><h4>Monitors</h4><div class="stat-number"><?= $stats['monitor_transfers'] ?? 0 ?></div></div>
        <div class="stat-card"><h4>Printers</h4><div class="stat-number"><?= $stats['printer_transfers'] ?? 0 ?></div></div>
        <div class="stat-card"><h4>Chargers</h4><div class="stat-number"><?= $stats['charger_transfers'] ?? 0 ?></div></div>
        <div class="stat-card"><h4>RAM/SSD</h4><div class="stat-number"><?= $stats['ram_ssd_transfers'] ?? 0 ?></div></div>
    </div>

    <div class="action-buttons">
        <div>Showing transfers from <?= date('M d, Y', strtotime($start_date)) ?> to <?= date('M d, Y', strtotime($end_date)) ?></div>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="export-btn" style="background:#28a745; color:white; padding:0.6rem 1.2rem; border-radius:var(--radius-md); text-decoration:none; display:inline-flex; align-items:center; gap:0.5rem;">Export to Excel</a>
    </div>

    <div class="table-wrapper">
        <table>
            <thead><tr><th>Date & Time</th><th>Category</th><th>Action</th><th>Details</th><th>User</th><th>Branch</th></tr></thead>
            <tbody>
                <?php if (!empty($transferLogs)): ?>
                    <?php foreach ($transferLogs as $log): 
                        $badgeClass = '';
                        if (strpos($log['action'], 'device') !== false || stripos($log['details'], 'device') !== false) $badgeClass = 'badge-device';
                        elseif (strpos($log['action'], 'monitor') !== false || stripos($log['details'], 'monitor') !== false) $badgeClass = 'badge-monitor';
                        elseif (strpos($log['action'], 'printer') !== false || stripos($log['details'], 'printer') !== false) $badgeClass = 'badge-printer';
                        elseif (strpos($log['action'], 'charger') !== false || stripos($log['details'], 'charger') !== false) $badgeClass = 'badge-charger';
                        elseif (strpos($log['action'], 'ram') !== false || strpos($log['action'], 'ssd') !== false || stripos($log['details'], 'ram') !== false) $badgeClass = 'badge-ram-ssd';
                        else $badgeClass = 'badge-bulk';
                    ?>
                        <tr>
                            <td><?= date('M d, Y H:i', strtotime($log['created_at'])) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= ucfirst(str_replace('badge-', '', $badgeClass)) ?></span></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td class="log-details"><?= nl2br(htmlspecialchars($log['details'])) ?></td>
                            <td><?= htmlspecialchars($log['full_name'] ?? 'Unknown') ?></td>
                            <td><?= htmlspecialchars($log['user_branch'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="empty-state">No transfer logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>
<script>
let autoFilterEnabled = false;
let timeout;
function enableAutoFilter() {
    autoFilterEnabled = true;
    document.getElementById('autoSubmitBtn').textContent = 'Auto Filter: ON';
    document.getElementById('autoSubmitBtn').style.background = '#28a745';
    document.querySelectorAll('#filterForm select, #filterForm input').forEach(el => {
        el.addEventListener('change', () => { if (autoFilterEnabled) { clearTimeout(timeout); timeout = setTimeout(() => document.getElementById('filterForm').submit(), 500); } });
        if (el.type === 'text') el.addEventListener('input', () => { if (autoFilterEnabled) { clearTimeout(timeout); timeout = setTimeout(() => document.getElementById('filterForm').submit(), 500); } });
    });
}
function disableAutoFilter() {
    autoFilterEnabled = false;
    document.getElementById('autoSubmitBtn').textContent = 'Auto Filter';
    document.getElementById('autoSubmitBtn').style.background = '#17a2b8';
}
document.getElementById('autoSubmitBtn').addEventListener('click', () => autoFilterEnabled ? disableAutoFilter() : enableAutoFilter());
disableAutoFilter();
</script>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>