<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

$user_id = $_SESSION['user_id'] ?? 0;
if (!isset($user_id)) {
    header("Location: ../login.php");
    exit();
}
// Strict super_admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
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

// ---------- Determine the latest month with sales data ----------
$latestMonth = '';
$stmt = secureQuery($conn, "
    SELECT MAX(sale_date) AS latest_date FROM (
        SELECT sold_at AS sale_date FROM devices WHERE status = 'Sold'
        UNION ALL
        SELECT sold_at FROM monitors WHERE status = 'Sold'
        UNION ALL
        SELECT date_sold FROM printers WHERE status = 'Sold'
        UNION ALL
        SELECT sold_at FROM smartboards WHERE status = 'sold'
        UNION ALL
        SELECT date_sold FROM sold_accessories
        UNION ALL
        SELECT date_sold FROM sold_chargers
        UNION ALL
        SELECT date_sold FROM phones WHERE status = 'sold'
        UNION ALL
        SELECT date_sold FROM ups WHERE status = 'sold'
        UNION ALL
        SELECT date_sold FROM sold_rams_ssds
        UNION ALL
        SELECT date_sold FROM sold_hdds
        UNION ALL
        SELECT date_sold FROM sold_graphics_cards
    ) AS all_dates
");
if ($stmt) {
    $latest = $stmt->fetchColumn();
    if ($latest) {
        $latestMonth = date('Y-m', strtotime($latest));
    }
}
// Fallback to current month if no data
if (empty($latestMonth)) $latestMonth = date('Y-m');

// Get filter values from GET – use the latest month as default
$branch_filter = $_GET['branch'] ?? 'all';
$salesperson_filter = $_GET['salesperson'] ?? 'all';

// *** FIX: Correctly compute the first and last day of the month ***
$default_from = $latestMonth . '-01';
$default_to   = date('Y-m-t', strtotime($default_from));

$date_from = $_GET['date_from'] ?? $default_from;
$date_to   = $_GET['date_to'] ?? $default_to;

$commission_percent = isset($_GET['commission']) ? (float)$_GET['commission'] : 0;

// Validate dates
if (!strtotime($date_from)) $date_from = $default_from;
if (!strtotime($date_to))   $date_to   = $default_to;

// -------------------------------------------------------------------
// 1. FETCH ALL SALES FROM ALL TABLES (same as sales_logs.php)
// -------------------------------------------------------------------
function fetchAllSalesUnified($conn) {
    $allSales = [];

    // Devices
    $sql = "SELECT selling_price AS price, sold_at AS sale_date, branch, sold_by
            FROM devices WHERE status = 'Sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Monitors
    $sql = "SELECT selling_price AS price, sold_at AS sale_date, branch, sold_by
            FROM monitors WHERE status = 'Sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Printers
    $sql = "SELECT selling_price AS price, date_sold AS sale_date, branch, sold_by
            FROM printers WHERE status = 'Sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Smartboards
    $sql = "SELECT selling_price AS price, sold_at AS sale_date, branch, sold_by
            FROM smartboards WHERE status = 'sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Sold Accessories
    $sql = "SELECT total_price AS price, date_sold AS sale_date, branch, sold_by
            FROM sold_accessories";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Sold Chargers
    $sql = "SELECT total_price AS price, date_sold AS sale_date, branch, sold_by
            FROM sold_chargers";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Phones
    $sql = "SELECT selling_price AS price, date_sold AS sale_date, branch, sold_by
            FROM phones WHERE status = 'sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // UPS
    $sql = "SELECT selling_price AS price, date_sold AS sale_date, branch, sold_by
            FROM ups WHERE status = 'sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Sold RAM/SSD
    $sql = "SELECT (selling_price * quantity) AS price, date_sold AS sale_date, branch, sold_by
            FROM sold_rams_ssds";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Sold HDDs
    $sql = "SELECT (selling_price * quantity) AS price, date_sold AS sale_date, branch, sold_by
            FROM sold_hdds";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Sold Graphics Cards
    $sql = "SELECT (selling_price * quantity) AS price, date_sold AS sale_date, branch, sold_by
            FROM sold_graphics_cards";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    return $allSales;
}

// Fetch all raw sales
$allSales = fetchAllSalesUnified($conn);

// -------------------------------------------------------------------
// 2. APPLY FILTERS (date, branch, salesperson) in PHP
// -------------------------------------------------------------------
$filteredSales = [];
foreach ($allSales as $sale) {
    if (empty($sale['sale_date'])) continue;

    $sale_time = strtotime($sale['sale_date']);
    $from_time = strtotime($date_from . ' 00:00:00');
    $to_time   = strtotime($date_to . ' 23:59:59');
    if ($sale_time < $from_time || $sale_time > $to_time) continue;

    if ($branch_filter !== 'all' && strcasecmp($sale['branch'], $branch_filter) !== 0) continue;

    if ($salesperson_filter !== 'all' && (int)$sale['sold_by'] !== (int)$salesperson_filter) continue;

    $filteredSales[] = $sale;
}

// -------------------------------------------------------------------
// 3. GROUP BY salesperson (sold_by)
// -------------------------------------------------------------------
$salesData = [];
foreach ($filteredSales as $sale) {
    $sold_by = (int)$sale['sold_by'];
    if (!isset($salesData[$sold_by])) {
        $salesData[$sold_by] = [
            'total_sales_count' => 0,
            'total_revenue'     => 0,
        ];
    }
    $salesData[$sold_by]['total_sales_count']++;
    $salesData[$sold_by]['total_revenue'] += (float)$sale['price'];
}

// Fetch user details for each salesperson
$userIds = array_keys($salesData);
$userInfo = [];
if (!empty($userIds)) {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = secureQuery($conn, "SELECT id, full_name, branch FROM users WHERE id IN ($placeholders)", $userIds);
    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userInfo[$row['id']] = $row;
        }
    }
}

// Build final array with user names and branches
$finalData = [];
foreach ($salesData as $uid => $data) {
    if (isset($userInfo[$uid])) {
        $finalData[] = [
            'user_id'           => $uid,
            'full_name'         => $userInfo[$uid]['full_name'],
            'user_branch'       => $userInfo[$uid]['branch'],
            'total_sales_count' => $data['total_sales_count'],
            'total_revenue'     => $data['total_revenue'],
            'commission'        => $data['total_revenue'] * ($commission_percent / 100),
        ];
    }
}

// Sort by revenue descending
usort($finalData, function($a, $b) {
    return $b['total_revenue'] - $a['total_revenue'];
});

// Calculate totals
$totalRevenueAll = array_sum(array_column($finalData, 'total_revenue'));
$totalSalesCountAll = array_sum(array_column($finalData, 'total_sales_count'));

// Get list of all salespeople for dropdown (users with role 'sales')
$salespeople = [];
$stmt = secureQuery($conn, "SELECT id, full_name, branch FROM users WHERE role = 'sales' ORDER BY full_name");
if ($stmt) {
    $salespeople = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$branches = ['MOI', 'KIMATHI'];

// For the reset link we need the correct default last day
$reset_date_to = date('Y-m-t', strtotime($default_from));
?>

<!-- ========== HTML (unchanged except the reset link) ========== -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sales Team Report | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* (your entire existing CSS – unchanged) */
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
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: margin-left 0.3s ease, width 0.3s ease, padding 0.3s ease; overflow-x: hidden; max-width: 100%; }
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
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1); }
        .filter-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; }
        .btn { padding: 0.625rem 1.25rem; border: none; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-family: var(--font-sans); white-space: nowrap; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-300); }
        .btn-secondary:hover { background: var(--gray-200); }
        .commission-group { background: #f0fdf4; border: 1px solid #86efac; border-radius: var(--radius-md); padding: 0.25rem 1rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; height: 100%; }
        .commission-group label { font-weight: 600; color: #065f46; font-size: 0.85rem; }
        .commission-group input { width: 80px; padding: 0.4rem; border: 1px solid #86efac; border-radius: var(--radius-sm); font-size: 0.9rem; }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow-sm); }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; min-width: 700px; }
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
        .summary-row { background: #f0fdf4; font-weight: 600; }
        .summary-row td { border-top: 2px solid #10b981; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
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
            .filter-grid { grid-template-columns: 1fr; gap: 0.75rem; }
            .filter-actions { flex-direction: column; width: 100%; }
            .filter-actions .btn { width: 100%; justify-content: center; white-space: normal; }
            .commission-group { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .stats-row { grid-template-columns: 1fr; }
            .page-header h1 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Sales Team Report</h1>
        <div class="breadcrumb">
            <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <span>Sales Team</span>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-value">Ksh <?= number_format($totalRevenueAll, 0) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-value"><?= number_format($totalSalesCountAll) ?></div>
            <div class="stat-label">Total Sales</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-percent"></i></div>
            <div class="stat-value"><?= $commission_percent ?>%</div>
            <div class="stat-label">Commission Rate</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="search-section">
        <div class="search-title">
            <i class="fas fa-filter"></i> Filter Sales Data
        </div>
        <form method="GET" id="filterForm" class="filter-grid">
            <div class="filter-group">
                <label><i class="fas fa-store"></i> Branch</label>
                <select name="branch">
                    <option value="all" <?= $branch_filter === 'all' ? 'selected' : '' ?>>All Branches</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b ?>" <?= $branch_filter === $b ? 'selected' : '' ?>><?= $b ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-user"></i> Salesperson</label>
                <select name="salesperson">
                    <option value="all" <?= $salesperson_filter === 'all' ? 'selected' : '' ?>>All Salespeople</option>
                    <?php foreach ($salespeople as $sp): ?>
                        <option value="<?= $sp['id'] ?>" <?= $salesperson_filter == $sp['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sp['full_name']) ?> (<?= $sp['branch'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> Date From</label>
                <input type="date" name="date_from" value="<?= $date_from ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> Date To</label>
                <input type="date" name="date_to" value="<?= $date_to ?>">
            </div>
            <div class="filter-group" style="flex-direction:row; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                <div class="commission-group">
                    <label for="commission">Commission %</label>
                    <input type="number" name="commission" id="commission" step="0.01" min="0" value="<?= $commission_percent ?>">
                    <span style="font-size:0.85rem;">%</span>
                </div>
                <div class="filter-actions" style="display:flex; gap:0.75rem;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <a href="?branch=all&salesperson=all&date_from=<?= $default_from ?>&date_to=<?= $reset_date_to ?>&commission=0" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
        <div class="table-responsive">
            <?php if (empty($finalData)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-pie"></i>
                    <p>No sales data found for the selected filters.</p>
                    <a href="?branch=all&salesperson=all&date_from=<?= $default_from ?>&date_to=<?= $reset_date_to ?>&commission=0" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-undo"></i> Reset Filters
                    </a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Salesperson</th>
                            <th>Branch</th>
                            <th class="text-center">Total Sales</th>
                            <th class="text-right">Total Revenue (KES)</th>
                            <th class="text-right">Commission (KES)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($finalData as $row): ?>
                            <tr>
                                <td><?= $rank++ ?></td>
                                <td><strong><?= htmlspecialchars($row['full_name']) ?></strong></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($row['user_branch']) ?></span></td>
                                <td class="text-center"><?= number_format($row['total_sales_count']) ?></td>
                                <td class="text-success text-right">Ksh <?= number_format($row['total_revenue'], 0) ?></td>
                                <td class="text-right">Ksh <?= number_format($row['commission'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Summary row -->
                        <tr class="summary-row">
                            <td colspan="3" style="text-align:right;">Totals</td>
                            <td class="text-center"><?= number_format($totalSalesCountAll) ?></td>
                            <td class="text-success text-right">Ksh <?= number_format($totalRevenueAll, 0) ?></td>
                            <td class="text-right">Ksh <?= number_format($totalRevenueAll * ($commission_percent / 100), 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Back to Dashboard -->
    <div style="margin-top:1.5rem; display:flex; gap:0.75rem; flex-wrap:wrap;">
        <a href="../dashboard/superadmindashboard.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
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

</body>
</html>