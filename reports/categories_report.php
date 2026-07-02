<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
$user_id = $_SESSION['user_id'] ?? 0;
if (!isset($user_id)) {
    header("Location: ../login.php");
    exit();
}

// Restrict access – only super_admin and manager can view this report
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['super_admin', 'manager'])) {
    die("ACCESS DENIED: You do not have permission to access this page.");
}

// Helper: secure query
function secureQuery($conn, $sql, $params = []) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("DB Error: " . $e->getMessage());
        return false;
    }
}

// Get filters from GET
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');
$branch_filter = $_GET['branch'] ?? 'all';

// Validate dates
if (!strtotime($date_from)) $date_from = date('Y-m-01');
if (!strtotime($date_to)) $date_to = date('Y-m-t');

// Define the 11 categories
$allCategories = [
    'Device',
    'Monitor',
    'Printer',
    'Smartboard',
    'Accessory',
    'Charger',
    'Phone',
    'UPS',
    'RAM/SSD',
    'HDD',
    'Graphics Card'
];

// Build params for date range
$params = [
    ':date_from' => $date_from . ' 00:00:00',
    ':date_to'   => $date_to . ' 23:59:59'
];

// Branch filter for WHERE clause
$branch_where = '';
if ($branch_filter !== 'all') {
    $branch_where = " AND branch = :branch";
    $params[':branch'] = $branch_filter;
}

// -----------------------------------------------------------------
// 1. FETCH DATA FOR EACH CATEGORY SEPARATELY
// -----------------------------------------------------------------
$categoryData = [];

// 1. Devices
$sql = "SELECT COALESCE(SUM(selling_price), 0) AS total_revenue, COUNT(*) AS count
        FROM devices 
        WHERE status = 'Sold' 
        AND sold_at BETWEEN :date_from AND :date_to
        $branch_where";
$stmt = secureQuery($conn, $sql, $params);
if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $categoryData['Device'] = ['total_revenue' => (float)$row['total_revenue'], 'count' => (int)$row['count']];
} else {
    $categoryData['Device'] = ['total_revenue' => 0, 'count' => 0];
}

// 2. Monitors
$sql = "SELECT COALESCE(SUM(selling_price), 0) AS total_revenue, COUNT(*) AS count
        FROM monitors 
        WHERE status = 'Sold' 
        AND sold_at BETWEEN :date_from AND :date_to
        $branch_where";
$stmt = secureQuery($conn, $sql, $params);
if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $categoryData['Monitor'] = ['total_revenue' => (float)$row['total_revenue'], 'count' => (int)$row['count']];
} else {
    $categoryData['Monitor'] = ['total_revenue' => 0, 'count' => 0];
}

// 3. Printers
$sql = "SELECT COALESCE(SUM(selling_price), 0) AS total_revenue, COUNT(*) AS count
        FROM printers 
        WHERE status = 'Sold' 
        AND date_sold BETWEEN :date_from AND :date_to
        $branch_where";
$stmt = secureQuery($conn, $sql, $params);
if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $categoryData['Printer'] = ['total_revenue' => (float)$row['total_revenue'], 'count' => (int)$row['count']];
} else {
    $categoryData['Printer'] = ['total_revenue' => 0, 'count' => 0];
}

// 4. Smartboards
$sql = "SELECT COALESCE(SUM(selling_price), 0) AS total_revenue, COUNT(*) AS count
        FROM smartboards 
        WHERE status = 'sold' 
        AND sold_at BETWEEN :date_from AND :date_to
        $branch_where";
$stmt = secureQuery($conn, $sql, $params);
if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $categoryData['Smartboard'] = ['total_revenue' => (float)$row['total_revenue'], 'count' => (int)$row['count']];
} else {
    $categoryData['Smartboard'] = ['total_revenue' => 0, 'count' => 0];
}

// 5. Accessories (from sold_accessories)
$sql = "SELECT COALESCE(SUM(total_price), 0) AS total_revenue, SUM(quantity) AS count
        FROM sold_accessories 
        WHERE date_sold BETWEEN :date_from AND :date_to
        $branch_where";
$stmt = secureQuery($conn, $sql, $params);
if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $categoryData['Accessory'] = ['total_revenue' => (float)$row['total_revenue'], 'count' => (int)$row['count']];
} else {
    $categoryData['Accessory'] = ['total_revenue' => 0, 'count' => 0];
}

// 6. Chargers (from sold_chargers)
$sql = "SELECT COALESCE(SUM(total_price), 0) AS total_revenue, SUM(quantity) AS count
        FROM sold_chargers 
        WHERE date_sold BETWEEN :date_from AND :date_to
        $branch_where";
$stmt = secureQuery($conn, $sql, $params);
if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $categoryData['Charger'] = ['total_revenue' => (float)$row['total_revenue'], 'count' => (int)$row['count']];
} else {
    $categoryData['Charger'] = ['total_revenue' => 0, 'count' => 0];
}

// 7. Phones
$sql = "SELECT COALESCE(SUM(selling_price), 0) AS total_revenue, COUNT(*) AS count
        FROM phones 
        WHERE status = 'sold' 
        AND date_sold BETWEEN :date_from AND :date_to
        $branch_where";
$stmt = secureQuery($conn, $sql, $params);
if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $categoryData['Phone'] = ['total_revenue' => (float)$row['total_revenue'], 'count' => (int)$row['count']];
} else {
    $categoryData['Phone'] = ['total_revenue' => 0, 'count' => 0];
}

// 8. UPS
$sql = "SELECT COALESCE(SUM(selling_price), 0) AS total_revenue, COUNT(*) AS count
        FROM ups 
        WHERE status = 'sold' 
        AND date_sold BETWEEN :date_from AND :date_to
        $branch_where";
$stmt = secureQuery($conn, $sql, $params);
if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $categoryData['UPS'] = ['total_revenue' => (float)$row['total_revenue'], 'count' => (int)$row['count']];
} else {
    $categoryData['UPS'] = ['total_revenue' => 0, 'count' => 0];
}

// 9. RAM/SSD (from sold_rams_ssds)
$sql = "SELECT COALESCE(SUM(selling_price * quantity), 0) AS total_revenue, SUM(quantity) AS count
        FROM sold_rams_ssds 
        WHERE date_sold BETWEEN :date_from AND :date_to
        $branch_where";
$stmt = secureQuery($conn, $sql, $params);
if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $categoryData['RAM/SSD'] = ['total_revenue' => (float)$row['total_revenue'], 'count' => (int)$row['count']];
} else {
    $categoryData['RAM/SSD'] = ['total_revenue' => 0, 'count' => 0];
}

// 10. HDD (from sold_hdds)
$sql = "SELECT COALESCE(SUM(selling_price * quantity), 0) AS total_revenue, SUM(quantity) AS count
        FROM sold_hdds 
        WHERE date_sold BETWEEN :date_from AND :date_to
        $branch_where";
$stmt = secureQuery($conn, $sql, $params);
if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $categoryData['HDD'] = ['total_revenue' => (float)$row['total_revenue'], 'count' => (int)$row['count']];
} else {
    $categoryData['HDD'] = ['total_revenue' => 0, 'count' => 0];
}

// 11. Graphics Cards (from sold_graphics_cards)
$sql = "SELECT COALESCE(SUM(selling_price * quantity), 0) AS total_revenue, SUM(quantity) AS count
        FROM sold_graphics_cards 
        WHERE date_sold BETWEEN :date_from AND :date_to
        $branch_where";
$stmt = secureQuery($conn, $sql, $params);
if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $categoryData['Graphics Card'] = ['total_revenue' => (float)$row['total_revenue'], 'count' => (int)$row['count']];
} else {
    $categoryData['Graphics Card'] = ['total_revenue' => 0, 'count' => 0];
}

// -----------------------------------------------------------------
// 2. BUILD REPORT (all 11 categories sorted by revenue)
// -----------------------------------------------------------------
$report = [];
foreach ($allCategories as $cat) {
    $report[] = [
        'category_name' => $cat,
        'count' => $categoryData[$cat]['count'] ?? 0,
        'total_revenue' => $categoryData[$cat]['total_revenue'] ?? 0,
    ];
}

// Sort by total_revenue descending (highest first)
usort($report, function($a, $b) {
    return $b['total_revenue'] - $a['total_revenue'];
});

// Calculate totals
$totalRevenue = array_sum(array_column($report, 'total_revenue'));
$totalCount = array_sum(array_column($report, 'count'));

// Branches for filter dropdown
$branches = ['MOI', 'KIMATHI'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Category Report | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ===== Same styles as view_users.php and sales_team.php ===== */
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
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: all 0.3s ease; }
        .page-header { background: white; padding: 1.5rem 2rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .page-header h1 { font-size: 1.75rem; color: var(--gray-800); font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: white; padding: 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); transition: all 0.2s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-card .stat-icon { font-size: 1.5rem; color: var(--primary); margin-bottom: 0.5rem; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 600; color: var(--gray-800); }
        .stat-card .stat-label { font-size: 0.85rem; color: var(--gray-500); margin-top: 0.25rem; }
        .search-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .search-title { font-size: 1rem; font-weight: 500; color: var(--gray-700); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-size: 0.85rem; font-weight: 500; color: var(--gray-600); }
        .filter-group input, .filter-group select { padding: 0.625rem 0.875rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; font-family: var(--font-sans); }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,75,42,0.1); }
        .filter-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; }
        .btn { padding: 0.625rem 1.25rem; border: none; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-family: var(--font-sans); white-space: nowrap; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-300); }
        .btn-secondary:hover { background: var(--gray-200); }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow-sm); }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; min-width: 600px; }
        th { background: var(--gray-50); padding: 1rem 1rem; text-align: left; font-weight: 600; color: var(--gray-600); font-size: 0.85rem; border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        td { padding: 0.875rem 1rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); vertical-align: middle; }
        tr:hover { background: var(--gray-50); }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .text-success { color: #10b981; font-weight: 600; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1rem 1.25rem; }
            .stats-row { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .stat-card { padding: 1rem; }
            .stat-card .stat-value { font-size: 1.5rem; }
            .search-section { padding: 1rem; }
            .filter-grid { grid-template-columns: 1fr; gap: 0.75rem; }
            .filter-actions { flex-direction: column; width: 100%; }
            .filter-actions .btn { width: 100%; justify-content: center; white-space: normal; }
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
    <div class="page-header">
        <h1><i class="fas fa-chart-pie"></i> Category Report</h1>
        <div class="breadcrumb">
            <a href="<?= $role === 'super_admin' ? '/inventory_system/dashboard/superadmindashboard.php' : '/inventory_system/dashboard/managerdashboard.php' ?>"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <span>Category Report</span>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
            <div class="stat-value"><?= count(array_filter($report, fn($c) => $c['total_revenue'] > 0)) ?> / <?= count($report) ?></div>
            <div class="stat-label">Categories with Sales / Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-value">Ksh <?= number_format($totalRevenue, 0) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-value"><?= number_format($totalCount) ?></div>
            <div class="stat-label">Total Items Sold</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="search-section">
        <div class="search-title">
            <i class="fas fa-filter"></i> Filter Report
        </div>
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-store"></i> Branch</label>
                <select name="branch">
                    <option value="all" <?= $branch_filter === 'all' ? 'selected' : '' ?>>All Branches</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b ?>" <?= $branch_filter === $b ? 'selected' : '' ?>><?= $b ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                <a href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-t') ?>&branch=all" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
        <div class="table-responsive">
            <?php if (empty($report) || $totalRevenue == 0): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-pie"></i>
                    <p>No sales data found for the selected filters.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Category</th>
                            <th class="text-center">Items Sold</th>
                            <th class="text-right">Total Revenue (KES)</th>
                            <th class="text-right">Average Price (KES)</th>
                            <th class="text-center">% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($report as $cat): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($cat['category_name']) ?></strong></td>
                                <td class="text-center"><?= number_format($cat['count']) ?></td>
                                <td class="text-success text-right">Ksh <?= number_format($cat['total_revenue'], 0) ?></td>
                                <td class="text-right">Ksh <?= number_format($cat['count'] > 0 ? $cat['total_revenue'] / $cat['count'] : 0, 0) ?></td>
                                <td class="text-center"><?= $totalRevenue > 0 ? round(($cat['total_revenue'] / $totalRevenue) * 100, 1) : 0 ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Summary row -->
                        <tr style="background: #f0fdf4; font-weight: 600;">
                            <td colspan="2" style="text-align:right;">Totals</td>
                            <td class="text-center"><?= number_format($totalCount) ?></td>
                            <td class="text-success text-right">Ksh <?= number_format($totalRevenue, 0) ?></td>
                            <td class="text-right">Ksh <?= number_format($totalCount > 0 ? $totalRevenue / $totalCount : 0, 0) ?></td>
                            <td class="text-center">100%</td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Back to Dashboard -->
    <div style="margin-top:1.5rem; display:flex; gap:0.75rem; flex-wrap:wrap;">
        <a href="<?= $role === 'super_admin' ? '/inventory_system/dashboard/superadmindashboard.php' : '/inventory_system/dashboard/managerdashboard.php' ?>" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<script>
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

<?php require_once "../includes/footer.php"; ?>
</body>
</html>