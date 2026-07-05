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

$sql = "SELECT r.*, d.model_name, d.processor, d.ram, d.storage_type, d.storage_capacity, d.touch, d.graphics,
               c.category_name, u1.full_name AS fixed_by_name, u2.full_name AS given_by_name
        FROM repairs r
        JOIN devices d ON r.serial_number = d.serial_number
        JOIN categories c ON d.category_id = c.id
        LEFT JOIN users u1 ON r.added_by = u1.id
        LEFT JOIN users u2 ON r.given_by = u2.id
        WHERE r.fix_status = 'Fixed'";
$params = [];

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

if (!empty($filter_serial)) {
    $sql .= " AND r.serial_number LIKE ?";
    $params[] = "%$filter_serial%";
}
if ($user_role === 'super_admin' && !empty($filter_branch)) {
    $sql .= " AND r.branch = ?";
    $params[] = $filter_branch;
}
if (!empty($filter_start) && !empty($filter_end)) {
    $sql .= " AND DATE(r.date_fixed) BETWEEN ? AND ?";
    $params[] = $filter_start;
    $params[] = $filter_end;
} elseif (!empty($filter_start)) {
    $sql .= " AND DATE(r.date_fixed) >= ?";
    $params[] = $filter_start;
} elseif (!empty($filter_end)) {
    $sql .= " AND DATE(r.date_fixed) <= ?";
    $params[] = $filter_end;
}

$sql .= " ORDER BY r.date_fixed DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get branches for super_admin filter
$branches = [];
if ($user_role === 'super_admin') {
    $branchStmt = $conn->query("SELECT DISTINCT branch FROM repairs WHERE branch IS NOT NULL ORDER BY branch");
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
    <title>Repair Logs | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1a4b2a;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --radius-md: 0.5rem;
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
        .stat-card { background: white; padding: 1rem 1.5rem; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); flex: 1; min-width: 150px; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--primary); }
        .stat-card .stat-label { font-size: 0.8rem; color: var(--gray-500); }
        .filter-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .filter-title { font-size: 1rem; font-weight: 500; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-size: 0.85rem; font-weight: 500; color: var(--gray-600); }
        .filter-group input, .filter-group select { padding: 0.625rem 0.875rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        .btn { padding: 0.625rem 1.25rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .btn-secondary { background: var(--gray-500); }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        th { background: var(--gray-50); padding: 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); }
        td { padding: 0.9rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: var(--gray-100); }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .filter-grid { grid-template-columns: 1fr; } .btn { width: 100%; justify-content: center; } .stats-row { flex-direction: column; } }
    </style>
</head>
<body>
    <?php require_once "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Repair Logs (Fixed Devices)</h1>
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
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= count($repairs) ?></div><div class="stat-label">Fixed Devices</div></div>
    </div>

    <div class="filter-section">
        <div class="filter-title"><i class="fas fa-filter"></i> Filter Logs</div>
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label>Serial Number</label>
                <input type="text" name="serial" placeholder="Search by serial..." value="<?= htmlspecialchars($filter_serial) ?>">
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
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($filter_start) ?>">
            </div>
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($filter_end) ?>">
            </div>
            <div class="filter-group">
                <button type="submit" class="btn"><i class="fas fa-search"></i> Filter</button>
                <a href="repair_logs.php" class="btn btn-secondary" style="margin-left:0.5rem;">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <?php if (empty($repairs)): ?>
            <div class="empty-state"><i class="fas fa-clipboard-list"></i><p>No repair logs found.</p></div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Serial</th>
                        <th>Category</th>
                        <th>Model</th>
                        <th>Problem</th>
                        <th>Given By</th>
                        <th>Fixed By</th>
                        <th>Branch</th>
                        <th>Date Fixed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; foreach ($repairs as $r): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><code><?= htmlspecialchars($r['serial_number']) ?></code></td>
                        <td><span class="badge"><?= htmlspecialchars($r['category_name']) ?></span></td>
                        <td><?= htmlspecialchars($r['model_name']) ?></td>
                        <td><?= htmlspecialchars($r['problem_description']) ?></td>
                        <td><?= htmlspecialchars($r['given_by_name'] ?? 'Unknown') ?></td>
                        <td><?= htmlspecialchars($r['fixed_by_name'] ?? 'Unknown') ?></td>
                        <td><span class="badge"><?= htmlspecialchars($r['branch']) ?></span></td>
                        <td><?= date('M j, Y H:i', strtotime($r['date_fixed'])) ?></td>
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