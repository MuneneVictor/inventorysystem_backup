<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Allow super_admin, inventory_admin, manager, and sales
if (!in_array($role, ['super_admin', 'inventory_admin', 'manager', 'sales'])) {
    die("Access denied!");
}

// For managers, restrict to their branch if they have one
$user_branch = '';
if ($role === 'manager') {
    $user_stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_branch = $user_data['branch'] ?? '';
}

// Handle search inputs
$search_type = trim($_GET['type'] ?? '');
$search_condition = trim($_GET['condition'] ?? '');
$search_branch = trim($_GET['branch'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

// Build query – all chargers (quantity may be 0, but we show them)
$sql = "SELECT c.*, 
               u1.full_name AS added_by_name,
               u2.full_name AS updated_by_name
        FROM chargers c
        LEFT JOIN users u1 ON c.added_by = u1.id
        LEFT JOIN users u2 ON c.updated_by = u2.id
        WHERE 1=1";
$params = [];

// Manager restriction
if ($role === 'manager' && !empty($user_branch)) {
    $sql .= " AND c.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}

// Search filters
if ($search_type) {
    $sql .= " AND c.charger_type LIKE :type";
    $params['type'] = "%$search_type%";
}
if ($search_condition) {
    $sql .= " AND c.charger_condition LIKE :condition";
    $params['condition'] = "%$search_condition%";
}
if ($search_branch && $role !== 'manager') {
    $sql .= " AND c.branch = :branch";
    $params['branch'] = $search_branch;
}
if ($date_from) {
    $sql .= " AND DATE(c.date_added) >= :date_from";
    $params['date_from'] = $date_from;
}
if ($date_to) {
    $sql .= " AND DATE(c.date_added) <= :date_to";
    $params['date_to'] = $date_to;
}

$sql .= " ORDER BY c.date_added DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$chargers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_items = count($chargers);
$total_quantity = array_sum(array_column($chargers, 'quantity'));
$total_value = array_sum(array_column($chargers, 'total_price'));
$branches = array_unique(array_column($chargers, 'branch'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>In‑Stock Chargers | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Same CSS as hdd_instock.php */
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

        .main-content {
            padding: 2rem 2rem 1rem;
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;
            background: var(--gray-100);
            transition: margin-left 0.3s ease, width 0.3s ease, padding 0.3s ease;
            overflow-x: hidden;
            max-width: 100%;
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
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }

        .breadcrumb {
            color: var(--gray-500);
            font-size: 0.9rem;
        }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.25rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-card .stat-icon { font-size: 1.5rem; color: var(--primary); margin-bottom: 0.5rem; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 600; color: var(--gray-800); }
        .stat-card .stat-label { font-size: 0.85rem; color: var(--gray-500); margin-top: 0.25rem; }

        .search-section {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .search-title {
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .search-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .search-group label { font-size: 0.85rem; font-weight: 500; color: var(--gray-600); }
        .search-group input, .search-group select {
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            background: white;
        }
        .search-group input:focus, .search-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26,75,42,0.1);
        }

        .search-actions {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--font-sans);
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-300); }
        .btn-secondary:hover { background: var(--gray-200); }
        .btn-excel { background: #217346; color: white; }
        .btn-excel:hover { background: #1a5e33; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }

        .table-wrapper {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            min-width: 900px;
        }

        th {
            background: var(--gray-50);
            padding: 1rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-600);
            font-size: 0.85rem;
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }

        td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            vertical-align: middle;
        }

        tr:hover { background: var(--gray-50); }
        tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .branch-kimathi { color: #059669; font-weight: 500; }
        .branch-moi { color: #3b82f6; font-weight: 500; }

        .price {
            font-weight: 600;
            color: #059669;
        }

        .action-links {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .action-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .action-link:hover { text-decoration: underline; }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }

        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

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
            .search-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; justify-content: center; }
            .action-links { flex-direction: column; }
            .table { min-width: 600px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .stats-row { grid-template-columns: 1fr; }
            .page-header h1 { font-size: 1.1rem; }
            .table { min-width: 500px; }
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-bolt"></i> In‑Stock Chargers</h1>
        <div class="breadcrumb">
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'sales'): ?>
                <a href="/inventory_system/dashboard/salesdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>In‑Stock Chargers</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-boxes"></i></div>
            <div class="stat-value"><?= number_format($total_items) ?></div>
            <div class="stat-label">Total Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-cubes"></i></div>
            <div class="stat-value"><?= number_format($total_quantity) ?></div>
            <div class="stat-label">Total Units</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-store"></i></div>
            <div class="stat-value"><?= number_format(count($branches)) ?></div>
            <div class="stat-label">Branches</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-value">KES <?= number_format($total_value, 0) ?></div>
            <div class="stat-label">Total Value</div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-title"><i class="fas fa-filter"></i> Filter Chargers</div>
        <form method="GET" class="search-grid">
            <div class="search-group">
                <label>Charger Type</label>
                <input type="text" name="type" placeholder="e.g., HP Blue Pin" value="<?= htmlspecialchars($search_type) ?>">
            </div>
            <div class="search-group">
                <label>Condition</label>
                <select name="condition">
                    <option value="">-- All --</option>
                    <option value="new" <?= $search_condition == 'new' ? 'selected' : '' ?>>New</option>
                    <option value="ex-uk" <?= $search_condition == 'ex-uk' ? 'selected' : '' ?>>Ex‑UK</option>
                </select>
            </div>
            <?php if ($role !== 'manager'): ?>
            <div class="search-group">
                <label>Branch</label>
                <select name="branch">
                    <option value="">-- All Branches --</option>
                    <option value="KIMATHI" <?= $search_branch == 'KIMATHI' ? 'selected' : '' ?>>KIMATHI</option>
                    <option value="MOI" <?= $search_branch == 'MOI' ? 'selected' : '' ?>>MOI</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="search-group">
                <label>Date Added From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="search-group">
                <label>Date Added To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="search-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                <a href="chargers_instock.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                <?php if (!empty($chargers)): ?>
                    <a href="export_chargers_excel.php?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn btn-excel"><i class="fas fa-file-excel"></i> Export to Excel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Chargers Table -->
    <div class="table-wrapper">
        <div class="table-responsive">
            <?php if (empty($chargers)): ?>
                <div class="empty-state">
                    <i class="fas fa-bolt"></i>
                    <p>No chargers found matching your criteria.</p>
                    <a href="chargers_instock.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-undo"></i> Clear Filters
                    </a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Charger Type</th>
                            <th>Condition</th>
                            <th>Quantity</th>
                            <th>Branch</th>
                            <th>Price (KES)</th>
                            <th>Total Value (KES)</th>
                            <th>Added By</th>
                            <th>Updated By</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($chargers as $c): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($c['charger_type']) ?></strong></td>
                                <td><span class="badge"><?= htmlspecialchars(ucfirst($c['charger_condition'])) ?></span></td>
                                <td><span class="badge"><?= (int)$c['quantity'] ?></span></td>
                                <td>
                                    <span class="<?= $c['branch'] == 'KIMATHI' ? 'branch-kimathi' : 'branch-moi' ?>">
                                        <?= htmlspecialchars($c['branch']) ?>
                                    </span>
                                </td>
                                <td class="price"><?= $c['price'] !== null ? 'KES '.number_format($c['price'], 2) : '-' ?></td>
                                <td class="price"><?= $c['total_price'] !== null ? 'KES '.number_format($c['total_price'], 2) : '-' ?></td>
                                <td><?= htmlspecialchars($c['added_by_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($c['updated_by_name'] ?? 'Not updated yet') ?></td>
                                <td><small><?= date('M j, Y g:i A', strtotime($c['date_added'])) ?></small></td>
                                <td>
                                    <div class="action-links">
                                        <?php if (in_array($role, ['super_admin', 'inventory_admin', 'manager'])): ?>
                                            <?php if ($c['price'] === null): ?>
                                                <a href="add_price_charger.php?id=<?= urlencode($c['id']) ?>" class="action-link">
                                                    <i class="fas fa-plus-circle"></i> Add Price
                                                </a>
                                            <?php else: ?>
                                                <a href="update_price_charger.php?id=<?= urlencode($c['id']) ?>" class="action-link">
                                                    <i class="fas fa-edit"></i> Update Price
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<script>
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