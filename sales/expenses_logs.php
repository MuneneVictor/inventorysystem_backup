<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// ============================================================
// STRICT ROLE CHECK - Only cashier, super_admin, manager
// ============================================================
if (!in_array($_SESSION['role'], ['cashier', 'super_admin', 'manager'])) {
    die("ACCESS DENIED: You do not have permission to access this page.");
}

$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

// Get user branch
$user_branch = null;
if ($user_role !== 'super_admin' && $user_role !== 'manager') {
    // Only cashier needs branch restriction
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
    if (!$user_branch) {
        die("Your account has no branch assigned. Contact administrator.");
    }
}

// ============================================================
// FILTER INPUTS
// ============================================================
$filter_expense_name = trim($_GET['expense_name'] ?? '');
$filter_given_to = trim($_GET['given_to'] ?? '');
$filter_payment_method = trim($_GET['payment_method'] ?? '');
$filter_branch = trim($_GET['branch'] ?? '');
$filter_start_date = trim($_GET['start_date'] ?? '');
$filter_end_date = trim($_GET['end_date'] ?? '');

// Default: current month (1st to last day)
if (empty($filter_start_date) && empty($filter_end_date)) {
    $filter_start_date = date('Y-m-01');
    $filter_end_date = date('Y-m-t');
}

// ============================================================
// BUILD QUERY
// ============================================================
$sql = "SELECT e.*, u.full_name AS created_by_name
        FROM expenses e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE 1=1";
$params = [];

// Branch filter - ONLY cashier is restricted to their branch
// Manager and Super Admin see all branches
if ($user_role === 'cashier' && !empty($user_branch)) {
    $sql .= " AND e.branch = ?";
    $params[] = $user_branch;
}

// Filters
if (!empty($filter_expense_name)) {
    $sql .= " AND e.expense_name LIKE ?";
    $params[] = "%$filter_expense_name%";
}

if (!empty($filter_given_to)) {
    $sql .= " AND e.given_to LIKE ?";
    $params[] = "%$filter_given_to%";
}

if (!empty($filter_payment_method)) {
    $sql .= " AND e.payment_method = ?";
    $params[] = $filter_payment_method;
}

// Branch filter from dropdown - available for Super Admin and Manager
if (($user_role === 'super_admin' || $user_role === 'manager') && !empty($filter_branch)) {
    $sql .= " AND e.branch = ?";
    $params[] = $filter_branch;
}

// Date range filter
if (!empty($filter_start_date) && !empty($filter_end_date)) {
    $sql .= " AND DATE(e.expense_date) BETWEEN ? AND ?";
    $params[] = $filter_start_date;
    $params[] = $filter_end_date;
} elseif (!empty($filter_start_date)) {
    $sql .= " AND DATE(e.expense_date) >= ?";
    $params[] = $filter_start_date;
} elseif (!empty($filter_end_date)) {
    $sql .= " AND DATE(e.expense_date) <= ?";
    $params[] = $filter_end_date;
}

$sql .= " ORDER BY e.expense_date DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// GET BRANCHES FOR FILTER (SUPER ADMIN & MANAGER)
// ============================================================
$branches = [];
if ($user_role === 'super_admin' || $user_role === 'manager') {
    $branchStmt = $conn->query("SELECT DISTINCT branch FROM expenses WHERE branch IS NOT NULL ORDER BY branch");
    $branches = $branchStmt->fetchAll(PDO::FETCH_COLUMN);
}

// ============================================================
// CALCULATE STATISTICS
// ============================================================
$totalExpenses = count($expenses);
$totalAmount = array_sum(array_column($expenses, 'total_amount'));
$cashCount = 0;
$mpesaCount = 0;
$cashTotal = 0;
$mpesaTotal = 0;

foreach ($expenses as $e) {
    if (($e['payment_method'] ?? '') === 'cash') {
        $cashCount++;
        $cashTotal += $e['total_amount'];
    } elseif (($e['payment_method'] ?? '') === 'Mpesa') {
        $mpesaCount++;
        $mpesaTotal += $e['total_amount'];
    }
}

// ============================================================
// TIME GREETING
// ============================================================
date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Helper function to safely escape HTML
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Expense Logs | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* All existing styles remain the same */
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
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --font-sans: 'Inter', system-ui, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-sans);
            background: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
            overflow-x: hidden;
            min-height: 100vh;
        }

        .main-content {
            padding: 2rem 2rem 1rem;
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;
            background: var(--gray-100);
            transition: all 0.3s ease;
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
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i {
            color: var(--primary);
            font-size: 1.75rem;
        }

        .breadcrumb {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .user-info {
            margin-top: 0.5rem;
            color: var(--gray-500);
            font-size: 0.85rem;
        }
        .user-info i {
            color: var(--primary);
        }

        .date-range-badge {
            display: inline-block;
            background: var(--gray-100);
            color: var(--gray-600);
            padding: 0.2rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-card .stat-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .stat-card .stat-icon {
            font-size: 1.25rem;
            color: var(--gray-400);
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .filter-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .filter-group label i {
            color: var(--primary);
        }

        .filter-group input,
        .filter-group select {
            padding: 0.6rem 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-family: var(--font-sans);
            background: white;
            width: 100%;
            transition: all 0.2s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .btn {
            padding: 0.6rem 1.25rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .btn-export {
            background: #2563eb;
            color: white;
        }

        .btn-export:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Table */
        .table-wrapper {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            overflow-x: auto;
            box-shadow: var(--shadow-sm);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th {
            background: var(--gray-50);
            padding: 0.875rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-600);
            border-bottom: 2px solid var(--gray-200);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
            font-size: 0.85rem;
        }

        tr:hover {
            background: var(--gray-50);
        }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .badge-cash {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-mpesa {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-branch {
            background: #fef3c7;
            color: #92400e;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
            display: block;
        }

        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

        .footer span {
            color: var(--primary);
        }

        .view-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .amount-positive {
            font-weight: 600;
            color: var(--primary);
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
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 1.5rem 1rem 1rem !important;
                padding-top: 5rem !important;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem 0.75rem 0.75rem !important;
                padding-top: 4.5rem !important;
            }

            .page-header h1 {
                font-size: 1.25rem;
            }
            .page-header {
                padding: 1.25rem;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                grid-column: span 1;
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            table {
                min-width: 700px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem 0.5rem 0.5rem !important;
                padding-top: 4rem !important;
            }
            .page-header {
                padding: 1rem;
            }
            .page-header h1 {
                font-size: 1.1rem;
            }
            .stats-row {
                grid-template-columns: 1fr;
            }
            table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>
                <i class="fas fa-receipt"></i>
                Expense Logs
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
                <span>Expense Logs</span>
            </div>
            <div class="user-info">
                <i class="fas fa-eye"></i> View: 
                <span class="view-badge">
                    <?php 
                    if ($user_role === 'cashier') {
                        echo 'Branch: ' . safe($user_branch);
                    } elseif ($user_role === 'manager') {
                        echo 'All Branches (Manager)';
                    } else {
                        echo 'All Branches';
                    }
                    ?>
                </span>
                <span class="date-range-badge">
                    <i class="fas fa-calendar-alt"></i> 
                    <?= date('M d, Y', strtotime($filter_start_date)) ?> - <?= date('M d, Y', strtotime($filter_end_date)) ?>
                </span>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($totalExpenses) ?></div>
                <div class="stat-label"><i class="fas fa-clipboard-list stat-icon"></i> Total Expenses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">KES <?= number_format($totalAmount, 2) ?></div>
                <div class="stat-label"><i class="fas fa-money-bill-wave stat-icon"></i> Total Amount</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($cashCount) ?></div>
                <div class="stat-label"><i class="fas fa-money-bill-alt stat-icon" style="color:#065f46;"></i> Cash (KES <?= number_format($cashTotal, 2) ?>)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($mpesaCount) ?></div>
                <div class="stat-label"><i class="fas fa-mobile-alt stat-icon" style="color:#1e40af;"></i> M-Pesa (KES <?= number_format($mpesaTotal, 2) ?>)</div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-title">
                <i class="fas fa-filter"></i> Filter Expenses
            </div>
            <form method="GET" class="filter-grid">
                <div class="filter-group">
                    <label><i class="fas fa-tag"></i> Expense Name</label>
                    <input type="text" name="expense_name" placeholder="Search by name..." value="<?= safe($filter_expense_name) ?>">
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-user"></i> Given To</label>
                    <input type="text" name="given_to" placeholder="Search by person..." value="<?= safe($filter_given_to) ?>">
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-money-bill"></i> Payment Method</label>
                    <select name="payment_method">
                        <option value="">All Methods</option>
                        <option value="cash" <?= $filter_payment_method === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="Mpesa" <?= $filter_payment_method === 'Mpesa' ? 'selected' : '' ?>>M-Pesa</option>
                    </select>
                </div>

                <!-- Branch filter - visible to Super Admin and Manager -->
                <?php if ($user_role === 'super_admin' || $user_role === 'manager'): ?>
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
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="expenses_logs.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                        <?php if (!empty($expenses)): ?>
                            <a href="export_expenses.php?<?= http_build_query($_GET) ?>" class="btn btn-export">
                                <i class="fas fa-file-excel"></i> Export
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="table-wrapper">
            <?php if (empty($expenses)): ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>No expenses found for the selected filters.</p>
                    <p style="font-size:0.85rem; margin-top:0.5rem; color:var(--gray-400);">
                        <a href="add_expense.php" style="color:var(--primary); text-decoration:none;">
                            <i class="fas fa-plus-circle"></i> Add your first expense
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Expense Name</th>
                            <th>Description</th>
                            <th>Given To</th>
                            <th>Method</th>
                            <th>Amount (KES)</th>
                            <th>Branch</th>
                            <th>Created By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($expenses as $e): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= safe($e['expense_name']) ?></strong></td>
                            <td><?= safe($e['description'] ?? '-') ?></td>
                            <td><?= safe($e['given_to'] ?? '-') ?></td>
                            <td>
                                <span class="badge <?= ($e['payment_method'] ?? '') === 'cash' ? 'badge-cash' : 'badge-mpesa' ?>">
                                    <?= safe($e['payment_method'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="amount-positive">KES <?= number_format($e['total_amount'], 2) ?></td>
                            <td>
                                <span class="badge badge-branch"><?= safe($e['branch']) ?></span>
                            </td>
                            <td><?= safe($e['created_by_name'] ?? 'Unknown') ?></td>
                            <td><?= date('M j, Y H:i', strtotime($e['expense_date'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="actions-row">
            <a href="add_expense.php" class="link-btn">
                <i class="fas fa-plus-circle"></i> Add New Expense
            </a>
            <a href="expenses_logs.php" class="link-btn link-btn-sm">
                <i class="fas fa-sync-alt"></i> Refresh
            </a>
            <?php if (!empty($expenses)): ?>
                <a href="export_expenses.php?<?= http_build_query($_GET) ?>" class="link-btn link-btn-sm" style="background:#2563eb;">
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