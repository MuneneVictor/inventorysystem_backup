<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

if (!in_array($_SESSION['role'], ['super_admin', 'manager', 'inventory_admin'])) {
    die("ACCESS DENIED.");
}

$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

// For managers: restrict to their branch
$user_branch = null;
if ($user_role === 'manager') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
}

$filter_time = $_GET['filter_time'] ?? '';
$filter_user = $_GET['filter_user'] ?? '';
$filter_branch = $_GET['filter_branch'] ?? '';

// Get sales users for filter (super_admin only)
$sales_users = [];
if ($user_role === 'super_admin') {
    $stmt = $conn->query("SELECT id, full_name, branch FROM users WHERE role = 'sales' ORDER BY full_name");
    $sales_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$sql = "SELECT sd.*, c.category_name, u.full_name AS sold_by_name, u.branch
        FROM devices sd
        JOIN categories c ON sd.category_id = c.id
        JOIN users u ON sd.sold_by = u.id
        WHERE 1";
$params = [];

if ($user_role === 'manager' && !empty($user_branch)) {
    $sql .= " AND u.branch = :branch";
    $params['branch'] = $user_branch;
}
if ($user_role === 'super_admin' && !empty($filter_user)) {
    $sql .= " AND sd.sold_by = :salesid";
    $params['salesid'] = $filter_user;
}
if ($user_role === 'super_admin' && !empty($filter_branch)) {
    $sql .= " AND u.branch = :branch";
    $params['branch'] = $filter_branch;
}
if ($filter_time === "today") {
    $sql .= " AND DATE(sd.sold_at) = CURDATE()";
} elseif ($filter_time === "week") {
    $sql .= " AND YEARWEEK(sd.sold_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter_time === "month") {
    $sql .= " AND YEAR(sd.sold_at) = YEAR(CURDATE()) AND MONTH(sd.sold_at) = MONTH(CURDATE())";
} elseif ($filter_time === "year") {
    $sql .= " AND YEAR(sd.sold_at) = YEAR(CURDATE())";
}

$sql .= " ORDER BY sd.sold_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_revenue = array_sum(array_column($sales, 'price'));

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
    <title>Sales Logs | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same base styles as my_sales.php */
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
        .filter-title { font-size: 1rem; font-weight: 500; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-size: 0.85rem; font-weight: 500; color: var(--gray-600); }
        .filter-group select, .filter-group input { padding: 0.625rem 0.875rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        .filter-actions { display: flex; gap: 0.75rem; align-items: flex-end; }
        .btn { padding: 0.625rem 1.25rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .btn-secondary { background: var(--gray-500); }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
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
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> Sales Logs</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Sales Logs</span>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= count($sales) ?></div><div class="stat-label">Total Sales</div></div>
        <div class="stat-card"><div class="stat-value">KES <?= number_format($total_revenue, 0) ?></div><div class="stat-label">Total Revenue</div></div>
    </div>

    <div class="filter-section">
        <div class="filter-title"><i class="fas fa-filter"></i> Filter Sales</div>
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label>Time Period</label>
                <select name="filter_time">
                    <option value="">All Time</option>
                    <option value="today" <?= $filter_time == 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="week" <?= $filter_time == 'week' ? 'selected' : '' ?>>This Week</option>
                    <option value="month" <?= $filter_time == 'month' ? 'selected' : '' ?>>This Month</option>
                    <option value="year" <?= $filter_time == 'year' ? 'selected' : '' ?>>This Year</option>
                </select>
            </div>
            <?php if ($user_role === 'super_admin'): ?>
                <div class="filter-group">
                    <label>Salesperson</label>
                    <select name="filter_user">
                        <option value="">All Salespersons</option>
                        <?php foreach ($sales_users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Branch</label>
                    <select name="filter_branch">
                        <option value="">All Branches</option>
                        <?php
                        $branches = [];
                        foreach ($sales_users as $u) {
                            if ($u['branch'] && !in_array($u['branch'], $branches)) $branches[] = $u['branch'];
                        }
                        sort($branches);
                        foreach ($branches as $br): ?>
                            <option value="<?= htmlspecialchars($br) ?>" <?= $filter_branch == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="filter-actions">
                <button type="submit" class="btn"><i class="fas fa-search"></i> Filter</button>
                <a href="sales_logs.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <?php if (empty($sales)): ?>
            <div class="empty-state"><i class="fas fa-chart-line"></i><p>No sales found.</p></div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Serial</th><th>Category</th><th>Model</th><th>Processor</th><th>RAM</th><th>Storage</th><th>Price</th><th>Sold By</th><th>Branch</th><th>Date Sold</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($sales as $sale): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><code><?= htmlspecialchars($sale['serial_number']) ?></code></td>
                        <td><span class="badge"><?= htmlspecialchars($sale['category_name'] ?? '-') ?></span></td>
                        <td><?= htmlspecialchars($sale['model_name']) ?></td>
                        <td><?= htmlspecialchars($sale['processor']) ?></td>
                        <td><?= $sale['ram'] ?> GB</span></td>
                        <td><?= htmlspecialchars($sale['storage_type'] . ' ' . $sale['storage_capacity'] . 'GB') ?></td>
                        <td><?= number_format($sale['price'], 0) ?></td>
                        <td><?= htmlspecialchars($sale['sold_by_name']) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($sale['branch']) ?></span></td>
                        <td><?= date('M j, Y H:i', strtotime($sale['sold_at'])) ?></td>
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