<?php
session_start();
date_default_timezone_set('Africa/Nairobi');
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// ============================================================
// ROLE-BASED ACCESS CONTROL
// ============================================================
$user_role = $_SESSION['role'] ?? '';
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Allowed roles: cashier, manager, super_admin
if (!in_array($user_role, ['cashier', 'manager', 'super_admin'])) {
    die("ACCESS DENIED: You do not have permission to access this page.");
}

// Get user branch
$user_branch = null;
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
    if (!$user_branch) {
        die("Your account has no branch assigned. Contact administrator.");
    }
}

// ============================================================
// FILTERS - Single day selection only
// ============================================================
$filter_date = trim($_GET['report_date'] ?? '');
if (empty($filter_date)) {
    $filter_date = date('Y-m-d');
}

// Get selected branch for filtering (Manager & Super Admin)
$filter_branch = trim($_GET['branch'] ?? '');

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatCurrency($amount) {
    return 'KES ' . number_format($amount, 2);
}

function getStatusBadge($status) {
    $badges = [
        'paid' => '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Paid</span>',
        'unpaid' => '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Unpaid</span>',
        'partial' => '<span class="badge badge-warning"><i class="fas fa-clock"></i> Partial</span>'
    ];
    return $badges[$status] ?? '<span class="badge badge-secondary">Unknown</span>';
}

// ============================================================
// DETERMINE BRANCH FILTER CONDITION
// ============================================================
$hasBranchFilter = false;
$branchValue = '';

if ($user_role === 'cashier' && !empty($user_branch)) {
    $hasBranchFilter = true;
    $branchValue = $user_branch;
} elseif (($user_role === 'manager' || $user_role === 'super_admin') && !empty($filter_branch)) {
    $hasBranchFilter = true;
    $branchValue = $filter_branch;
}

// ============================================================
// 1. TOTAL SALES & REVENUE (Use sale_status = 'completed' OR completion_status = 'Completed')
// ============================================================
if ($hasBranchFilter) {
    $sql = "SELECT 
                COUNT(*) as total_sales,
                COALESCE(SUM(s.total_amount), 0) as total_revenue
            FROM sales s
            LEFT JOIN users u ON s.sold_by = u.id
            WHERE DATE(s.created_at) = ? 
            AND (s.sale_status = 'completed' OR s.completion_status = 'Completed')
            AND u.branch = ?";
    $params = [$filter_date, $branchValue];
} else {
    $sql = "SELECT 
                COUNT(*) as total_sales,
                COALESCE(SUM(total_amount), 0) as total_revenue
            FROM sales 
            WHERE DATE(created_at) = ? 
            AND (sale_status = 'completed' OR completion_status = 'Completed')";
    $params = [$filter_date];
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$salesStats = $stmt->fetch(PDO::FETCH_ASSOC);

$totalSales = $salesStats['total_sales'] ?? 0;
$totalRevenue = $salesStats['total_revenue'] ?? 0;

// ============================================================
// 2. PAYMENT METHODS BREAKDOWN
// ============================================================
if ($hasBranchFilter) {
    $sql = "SELECT 
                s.payment_method,
                COUNT(*) as count,
                COALESCE(SUM(s.total_amount), 0) as total
            FROM sales s
            LEFT JOIN users u ON s.sold_by = u.id
            WHERE DATE(s.created_at) = ? 
            AND (s.sale_status = 'completed' OR s.completion_status = 'Completed')
            AND s.payment_method IS NOT NULL
            AND u.branch = ?
            GROUP BY s.payment_method";
    $params = [$filter_date, $branchValue];
} else {
    $sql = "SELECT 
                payment_method,
                COUNT(*) as count,
                COALESCE(SUM(total_amount), 0) as total
            FROM sales 
            WHERE DATE(created_at) = ? 
            AND (sale_status = 'completed' OR completion_status = 'Completed')
            AND payment_method IS NOT NULL
            GROUP BY payment_method";
    $params = [$filter_date];
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

$paymentBreakdown = [
    'cash' => ['count' => 0, 'total' => 0],
    'mpesa-till' => ['count' => 0, 'total' => 0],
    'mpesa-pochi' => ['count' => 0, 'total' => 0],
    'bank-transfer' => ['count' => 0, 'total' => 0]
];

foreach ($paymentMethods as $pm) {
    $method = $pm['payment_method'];
    if (isset($paymentBreakdown[$method])) {
        $paymentBreakdown[$method]['count'] = (int)$pm['count'];
        $paymentBreakdown[$method]['total'] = (float)$pm['total'];
    }
}

// ============================================================
// 3. PAID VS UNPAID / DEBT
// ============================================================
if ($hasBranchFilter) {
    $sql = "SELECT 
                s.payment_status,
                COUNT(*) as count,
                COALESCE(SUM(s.total_amount), 0) as total
            FROM sales s
            LEFT JOIN users u ON s.sold_by = u.id
            WHERE DATE(s.created_at) = ? 
            AND (s.sale_status = 'completed' OR s.completion_status = 'Completed')
            AND u.branch = ?
            GROUP BY s.payment_status";
    $params = [$filter_date, $branchValue];
} else {
    $sql = "SELECT 
                payment_status,
                COUNT(*) as count,
                COALESCE(SUM(total_amount), 0) as total
            FROM sales 
            WHERE DATE(created_at) = ? 
            AND (sale_status = 'completed' OR completion_status = 'Completed')
            GROUP BY payment_status";
    $params = [$filter_date];
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$paymentStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$paidTotal = 0;
$unpaidTotal = 0;
$partialTotal = 0;
$paidCount = 0;
$unpaidCount = 0;
$partialCount = 0;

foreach ($paymentStatuses as $ps) {
    if ($ps['payment_status'] === 'paid') {
        $paidTotal = (float)$ps['total'];
        $paidCount = (int)$ps['count'];
    } elseif ($ps['payment_status'] === 'unpaid') {
        $unpaidTotal = (float)$ps['total'];
        $unpaidCount = (int)$ps['count'];
    } elseif ($ps['payment_status'] === 'partial') {
        $partialTotal = (float)$ps['total'];
        $partialCount = (int)$ps['count'];
    }
}

$debtTotal = $unpaidTotal + $partialTotal;

// ============================================================
// 4. TODAY'S EXPENSES
// ============================================================
if ($hasBranchFilter) {
    $sql = "SELECT 
                COUNT(*) as expense_count,
                COALESCE(SUM(total_amount), 0) as expense_total
            FROM expenses 
            WHERE DATE(expense_date) = ?
            AND branch = ?";
    $params = [$filter_date, $branchValue];
} else {
    $sql = "SELECT 
                COUNT(*) as expense_count,
                COALESCE(SUM(total_amount), 0) as expense_total
            FROM expenses 
            WHERE DATE(expense_date) = ?";
    $params = [$filter_date];
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$expenseStats = $stmt->fetch(PDO::FETCH_ASSOC);

$expenseCount = $expenseStats['expense_count'] ?? 0;
$expenseTotal = $expenseStats['expense_total'] ?? 0;

// ============================================================
// 5. NET INCOME (Revenue - Expenses)
// ============================================================
$netIncome = $totalRevenue - $expenseTotal;

// ============================================================
// 6. TOP SELLING ITEMS
// ============================================================
if ($hasBranchFilter) {
    $sql = "SELECT 
                si.item_type,
                si.description,
                SUM(si.quantity) as total_quantity,
                COALESCE(SUM(si.total_price), 0) as total_revenue
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            LEFT JOIN users u ON s.sold_by = u.id
            WHERE DATE(s.created_at) = ? 
            AND (s.sale_status = 'completed' OR s.completion_status = 'Completed')
            AND u.branch = ?
            GROUP BY si.item_type, si.description
            ORDER BY total_revenue DESC
            LIMIT 10";
    $params = [$filter_date, $branchValue];
} else {
    $sql = "SELECT 
                si.item_type,
                si.description,
                SUM(si.quantity) as total_quantity,
                COALESCE(SUM(si.total_price), 0) as total_revenue
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE DATE(s.created_at) = ? 
            AND (s.sale_status = 'completed' OR s.completion_status = 'Completed')
            GROUP BY si.item_type, si.description
            ORDER BY total_revenue DESC
            LIMIT 10";
    $params = [$filter_date];
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// 7. TOP SALES PEOPLE (Admin only)
// ============================================================
$topSalesPeople = [];
if ($user_role === 'super_admin' || $user_role === 'manager') {
    if ($hasBranchFilter) {
        $sql = "SELECT 
                    s.sold_by,
                    u.full_name as sales_person_name,
                    u.branch,
                    COUNT(*) as sales_count,
                    COALESCE(SUM(s.total_amount), 0) as total_revenue
                FROM sales s
                LEFT JOIN users u ON s.sold_by = u.id
                WHERE DATE(s.created_at) = ? 
                AND (s.sale_status = 'completed' OR s.completion_status = 'Completed')
                AND s.sold_by IS NOT NULL
                AND u.branch = ?
                GROUP BY s.sold_by, u.full_name, u.branch
                ORDER BY total_revenue DESC
                LIMIT 5";
        $params = [$filter_date, $branchValue];
    } else {
        $sql = "SELECT 
                    s.sold_by,
                    u.full_name as sales_person_name,
                    u.branch,
                    COUNT(*) as sales_count,
                    COALESCE(SUM(s.total_amount), 0) as total_revenue
                FROM sales s
                LEFT JOIN users u ON s.sold_by = u.id
                WHERE DATE(s.created_at) = ? 
                AND (s.sale_status = 'completed' OR s.completion_status = 'Completed')
                AND s.sold_by IS NOT NULL
                GROUP BY s.sold_by, u.full_name, u.branch
                ORDER BY total_revenue DESC
                LIMIT 5";
        $params = [$filter_date];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $topSalesPeople = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// 8. RECENT SALES TRANSACTIONS
// ============================================================
if ($hasBranchFilter) {
    $sql = "SELECT 
                s.id,
                s.client_name,
                s.client_phone,
                s.total_amount,
                s.payment_method,
                s.payment_status,
                s.created_at,
                u.full_name as sold_by_name
            FROM sales s
            LEFT JOIN users u ON s.sold_by = u.id
            WHERE DATE(s.created_at) = ? 
            AND (s.sale_status = 'completed' OR s.completion_status = 'Completed')
            AND u.branch = ?
            ORDER BY s.created_at DESC 
            LIMIT 50";
    $params = [$filter_date, $branchValue];
} else {
    $sql = "SELECT 
                s.id,
                s.client_name,
                s.client_phone,
                s.total_amount,
                s.payment_method,
                s.payment_status,
                s.created_at,
                u.full_name as sold_by_name
            FROM sales s
            LEFT JOIN users u ON s.sold_by = u.id
            WHERE DATE(s.created_at) = ? 
            AND (s.sale_status = 'completed' OR s.completion_status = 'Completed')
            ORDER BY s.created_at DESC 
            LIMIT 50";
    $params = [$filter_date];
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// 9. GET BRANCHES FOR FILTER (from users table)
// ============================================================
$branches = [];
if ($user_role === 'manager' || $user_role === 'super_admin') {
    $branchStmt = $conn->query("SELECT DISTINCT branch FROM users WHERE branch IS NOT NULL ORDER BY branch");
    $branches = $branchStmt->fetchAll(PDO::FETCH_COLUMN);
}

// ============================================================
// TIME GREETING
// ============================================================
date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Daily Report | Mombasa Computers</title>
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

        /* Filter Section */
        .filter-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .filter-title { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--gray-700); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.4rem; }
        .filter-group label { font-size: 0.7rem; font-weight: 600; color: var(--gray-600); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 0.3rem; }
        .filter-group label i { color: var(--primary); }
        .filter-group input, .filter-group select { padding: 0.6rem 0.875rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.875rem; font-family: var(--font-sans); background: white; width: 100%; transition: all 0.2s ease; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,75,42,0.1); }
        .filter-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: flex-end; }
        .btn { padding: 0.6rem 1.25rem; border: none; border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease; font-family: var(--font-sans); text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-300); }
        .btn-secondary:hover { background: var(--gray-200); }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: white; padding: 1.25rem; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); transition: all 0.2s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-card .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .stat-card .stat-label { font-size: 0.75rem; color: var(--gray-500); display: flex; align-items: center; gap: 0.3rem; margin-top: 0.2rem; }
        .stat-card .stat-icon { font-size: 1.25rem; color: var(--gray-400); }
        .stat-card .stat-sub { font-size: 0.65rem; color: var(--gray-400); margin-top: 0.2rem; }
        .stat-card.positive .stat-value { color: #065f46; }
        .stat-card.negative .stat-value { color: #991b1b; }
        .stat-card.debt .stat-value { color: #dc2626; }
        .stat-card.net .stat-value { color: #2563eb; }

        /* Payment Methods */
        .payment-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .payment-card { background: white; padding: 1rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); text-align: center; transition: all 0.2s ease; }
        .payment-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .payment-card .icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .payment-card .amount { font-size: 1.2rem; font-weight: 700; color: var(--primary); }
        .payment-card .label { font-size: 0.7rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.5px; }

        /* Section */
        .section { margin-bottom: 1.5rem; background: white; padding: 1.25rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); overflow-x: auto; }
        .section h4 { margin: 0 0 1rem 0; color: var(--gray-800); font-size: 1.1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
        .section h4 i { color: var(--primary); font-size: 1.2rem; }
        .section h4 .badge-count { background: var(--primary); color: white; padding: 0.15rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 500; margin-left: 0.5rem; }

        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; min-width: 600px; }
        th { background: var(--gray-50); padding: 0.7rem 0.8rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 2px solid var(--gray-200); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        td { padding: 0.6rem 0.8rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; font-size: 0.85rem; }
        tr:hover { background: var(--gray-50); }

        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; background: var(--gray-100); color: var(--gray-600); }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: var(--primary); color: white; }

        .view-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: var(--radius-md); font-size: 0.7rem; font-weight: 600; background: var(--gray-100); color: var(--gray-600); }

        .empty-state { text-align: center; padding: 2rem; color: var(--gray-500); }
        .empty-state i { font-size: 2.5rem; color: var(--gray-300); margin-bottom: 0.5rem; display: block; }

        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .footer span { color: var(--primary); }

        .rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; font-weight: 700; font-size: 0.75rem; }
        .rank-1 { background: #fbbf24; color: #78350f; }
        .rank-2 { background: #d1d5db; color: #374151; }
        .rank-3 { background: #fb923c; color: #7c2d12; }

        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { 
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1.25rem; }
            .filter-grid { grid-template-columns: 1fr; }
            .filter-actions { grid-column: span 1; flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .payment-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            table { min-width: 500px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header { padding: 1rem; }
            .page-header h1 { font-size: 1.1rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.5rem; }
            .stat-card { padding: 0.75rem; }
            .stat-card .stat-value { font-size: 1.2rem; }
            .payment-grid { grid-template-columns: 1fr 1fr; gap: 0.5rem; }
            table { min-width: 400px; }
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>
                <i class="fas fa-chart-bar"></i>
                Daily Report
            </h1>
            <div class="breadcrumb">
                <?php if ($user_role === 'super_admin'): ?>
                    <a href="/inventory_system/dashboard/superadmindashboard.php">Dashboard</a>
                <?php elseif ($user_role === 'manager'): ?>
                    <a href="/inventory_system/dashboard/managerdashboard.php">Dashboard</a>
                <?php else: ?>
                    <a href="/inventory_system/dashboard/cashierdashboard.php">Dashboard</a>
                <?php endif; ?>
                <span> / </span>
                <span>Daily Report</span>
            </div>
            <div class="user-info">
                <i class="fas fa-eye"></i> View: 
                <span class="view-badge">
                    <?php 
                    if ($user_role === 'cashier') echo 'Branch: ' . safe($user_branch);
                    elseif ($user_role === 'manager') echo 'Manager View';
                    else echo 'All Branches';
                    ?>
                </span>
                <span style="margin-left:1rem;">
                    <i class="fas fa-calendar-day"></i> <?= date('l, F j, Y', strtotime($filter_date)) ?>
                </span>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-title">
                <i class="fas fa-filter"></i> Select Date
            </div>
            <form method="GET" class="filter-grid">
                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt"></i> Report Date</label>
                    <input type="date" name="report_date" value="<?= safe($filter_date) ?>">
                </div>

                <?php if ($user_role === 'manager' || $user_role === 'super_admin'): ?>
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
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> View Report
                        </button>
                        <a href="daily_report.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Today
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($totalSales) ?></div>
                <div class="stat-label"><i class="fas fa-shopping-cart stat-icon"></i> Total Sales</div>
                <div class="stat-sub">Transactions completed</div>
            </div>
            <div class="stat-card positive">
                <div class="stat-value"><?= formatCurrency($totalRevenue) ?></div>
                <div class="stat-label"><i class="fas fa-money-bill-wave stat-icon"></i> Total Revenue</div>
                <div class="stat-sub">Gross revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= formatCurrency($paidTotal) ?></div>
                <div class="stat-label"><i class="fas fa-check-circle stat-icon" style="color:#065f46;"></i> Amount Paid</div>
                <div class="stat-sub"><?= $paidCount ?> transactions</div>
            </div>
            <div class="stat-card debt">
                <div class="stat-value"><?= formatCurrency($debtTotal) ?></div>
                <div class="stat-label"><i class="fas fa-exclamation-triangle stat-icon" style="color:#dc2626;"></i> Outstanding Debt</div>
                <div class="stat-sub"><?= ($unpaidCount + $partialCount) ?> transactions</div>
            </div>
            <div class="stat-card negative">
                <div class="stat-value"><?= formatCurrency($expenseTotal) ?></div>
                <div class="stat-label"><i class="fas fa-receipt stat-icon" style="color:#991b1b;"></i> Today's Expenses</div>
                <div class="stat-sub"><?= $expenseCount ?> expense(s)</div>
            </div>
            <div class="stat-card net">
                <div class="stat-value"><?= formatCurrency($netIncome) ?></div>
                <div class="stat-label"><i class="fas fa-coins stat-icon" style="color:#2563eb;"></i> Net Income</div>
                <div class="stat-sub">Revenue - Expenses</div>
            </div>
        </div>

        <!-- Payment Methods Breakdown -->
        <div class="payment-grid">
            <div class="payment-card">
                <div class="icon"><i class="fas fa-money-bill" style="color:#065f46;"></i></div>
                <div class="amount"><?= formatCurrency($paymentBreakdown['cash']['total']) ?></div>
                <div class="label">Cash (<?= $paymentBreakdown['cash']['count'] ?> transactions)</div>
            </div>
            <div class="payment-card">
                <div class="icon"><i class="fas fa-mobile-alt" style="color:#2563eb;"></i></div>
                <div class="amount"><?= formatCurrency($paymentBreakdown['mpesa-till']['total']) ?></div>
                <div class="label">M-Pesa Till (<?= $paymentBreakdown['mpesa-till']['count'] ?> transactions)</div>
            </div>
            <div class="payment-card">
                <div class="icon"><i class="fas fa-mobile-alt" style="color:#8b5cf6;"></i></div>
                <div class="amount"><?= formatCurrency($paymentBreakdown['mpesa-pochi']['total']) ?></div>
                <div class="label">M-Pesa Pochi (<?= $paymentBreakdown['mpesa-pochi']['count'] ?> transactions)</div>
            </div>
            <div class="payment-card">
                <div class="icon"><i class="fas fa-university" style="color:#f59e0b;"></i></div>
                <div class="amount"><?= formatCurrency($paymentBreakdown['bank-transfer']['total']) ?></div>
                <div class="label">Bank Transfer (<?= $paymentBreakdown['bank-transfer']['count'] ?> transactions)</div>
            </div>
        </div>

        <!-- Top Selling Items -->
        <div class="section">
            <h4>
                <i class="fas fa-fire" style="color:var(--accent, #f59e0b);"></i>
                Top Selling Items
                <span class="badge-count"><?= count($topItems) ?></span>
            </h4>
            <?php if (empty($topItems)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>No items sold on this day.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($topItems as $item): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= safe($item['description']) ?></strong></td>
                                <td><span class="badge badge-info"><?= safe(ucfirst($item['item_type'])) ?></span></td>
                                <td><?= (int)$item['total_quantity'] ?></td>
                                <td class="amount-positive"><?= formatCurrency($item['total_revenue']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Sales People (Admin Only) -->
        <?php if (($user_role === 'super_admin' || $user_role === 'manager') && !empty($topSalesPeople)): ?>
        <div class="section">
            <h4>
                <i class="fas fa-trophy" style="color:var(--accent, #f59e0b);"></i>
                Top Sales People
                <span class="badge-count"><?= count($topSalesPeople) ?></span>
            </h4>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sales Person</th>
                            <th>Branch</th>
                            <th>Sales Count</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($topSalesPeople as $person): ?>
                        <tr>
                            <td>
                                <?php if ($i === 1): ?>
                                    <span class="rank-badge rank-1">1</span>
                                <?php elseif ($i === 2): ?>
                                    <span class="rank-badge rank-2">2</span>
                                <?php elseif ($i === 3): ?>
                                    <span class="rank-badge rank-3">3</span>
                                <?php else: ?>
                                    <?= $i ?>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= safe($person['sales_person_name'] ?? 'Unknown') ?></strong></td>
                            <td><span class="badge"><?= safe($person['branch'] ?? 'N/A') ?></span></td>
                            <td><?= (int)$person['sales_count'] ?></td>
                            <td class="amount-positive"><?= formatCurrency($person['total_revenue']) ?></td>
                        </tr>
                        <?php $i++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Sales Transactions -->
        <div class="section">
            <div class="flex-between" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
                <h4 style="margin:0;">
                    <i class="fas fa-history"></i>
                    Recent Sales Transactions
                    <span class="badge-count"><?= count($recentSales) ?></span>
                </h4>
            </div>
            <?php if (empty($recentSales)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No sales transactions on this day.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Client</th>
                                <th>Phone</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Payment Status</th>
                                <th>Sold By</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= safe($sale['client_name'] ?? 'N/A') ?></strong></td>
                                <td><?= safe($sale['client_phone'] ?? 'N/A') ?></td>
                                <td class="amount-positive"><?= formatCurrency($sale['total_amount'] ?? 0) ?></td>
                                <td>
                                    <?php 
                                    $method = $sale['payment_method'] ?? '';
                                    $methodLabels = [
                                        'cash' => 'Cash',
                                        'mpesa-till' => 'M-Pesa Till',
                                        'mpesa-pochi' => 'M-Pesa Pochi',
                                        'bank-transfer' => 'Bank Transfer'
                                    ];
                                    ?>
                                    <span class="badge badge-info"><?= safe($methodLabels[$method] ?? $method) ?></span>
                                </td>
                                <td><?= getStatusBadge($sale['payment_status'] ?? '') ?></td>
                                <td><?= safe($sale['sold_by_name'] ?? 'Unknown') ?></td>
                                <td><?= date('H:i', strtotime($sale['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <i class="fas fa-copyright"></i> <?= date('Y'); ?> <span>Mombasa Computers</span>. All rights reserved.
            <span style="margin:0 0.5rem;">•</span>
            <span>v2.0.0</span>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function adjustMainContent() {
            var mainContent = document.querySelector('.main-content');
            if (window.innerWidth <= 1200) {
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.width = '100%';
                    mainContent.style.paddingTop = '5rem';
                }
            } else {
                if (mainContent) {
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