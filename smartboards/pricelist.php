<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if (!in_array($role, ['super_admin', 'inventory_admin', 'manager'])) {
    die("Access denied!");
}

$search_sn = trim($_GET['sn'] ?? '');
$search_model = trim($_GET['model'] ?? '');
$search_branch = trim($_GET['branch'] ?? '');

$sql = "SELECT serial_number, model, size_inches, branch, price, date_added, status
        FROM smartboards
        WHERE status = 'instock'";
$params = [];

if ($role === 'manager') {
    $user_stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_branch = $user_stmt->fetchColumn();
    if ($user_branch) {
        $sql .= " AND branch = :user_branch";
        $params['user_branch'] = $user_branch;
    }
}

if ($search_sn) {
    $sql .= " AND serial_number LIKE :sn";
    $params['sn'] = "%$search_sn%";
}
if ($search_model) {
    $sql .= " AND model LIKE :model";
    $params['model'] = "%$search_model%";
}
if ($search_branch && $role !== 'manager') {
    $sql .= " AND branch = :branch";
    $params['branch'] = $search_branch;
}

$sql .= " ORDER BY date_added DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$smartboards = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_count = count($smartboards);
$with_price = array_filter($smartboards, fn($s) => $s['price'] !== null);
$without_price = $total_count - count($with_price);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Smartboard Price List | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* (Copy the exact CSS from your device price_list.php – all styles remain identical) */
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: margin-left 0.3s ease, width 0.3s ease, padding 0.3s ease; overflow-x: hidden; max-width: 100%; }
        .page-header { background: white; padding: 1.5rem 2rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .page-header h1 { font-size: 1.75rem; color: var(--gray-800); font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: white; padding: 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); transition: all 0.2s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-card .stat-icon { font-size: 1.5rem; color: var(--primary); margin-bottom: 0.5rem; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 600; color: var(--gray-800); }
        .stat-card .stat-label { font-size: 0.85rem; color: var(--gray-500); margin-top: 0.25rem; }
        .filter-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .filter-title { font-size: 1rem; font-weight: 500; color: var(--gray-700); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .filter-form { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-size: 0.85rem; font-weight: 500; color: var(--gray-600); }
        .filter-group select, .filter-group input { padding: 0.625rem 0.875rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; font-family: var(--font-sans); min-width: 180px; }
        .filter-group select:focus, .filter-group input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,75,42,0.1); }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow-sm); }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; min-width: 700px; }
        th { background: var(--gray-50); padding: 1rem 1rem; text-align: left; font-weight: 600; color: var(--gray-600); font-size: 0.85rem; border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        td { padding: 0.875rem 1rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); vertical-align: middle; }
        tr:hover { background: var(--gray-50); }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: var(--gray-100); color: var(--gray-600); }
        .serial-code { font-family: 'Courier New', monospace; font-size: 0.85rem; background: var(--gray-50); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); display: inline-block; }
        .price { font-weight: 600; color: #059669; }
        .branch-kimathi { color: #059669; font-weight: 500; }
        .branch-moi { color: #3b82f6; font-weight: 500; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: var(--radius-md); font-size: 0.8rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-family: var(--font-sans); white-space: nowrap; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-add { background: #3b82f6; color: white; }
        .btn-add:hover { background: #2563eb; }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; } .page-header h1 { font-size: 1.25rem; } .page-header { padding: 1rem 1.25rem; } .stats-row { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; } .stat-card { padding: 1rem; } .stat-card .stat-value { font-size: 1.5rem; } .filter-form { flex-direction: column; } .filter-group select, .filter-group input { width: 100%; } .btn { width: 100%; justify-content: center; } }
        @media (max-width: 480px) { .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; } .stats-row { grid-template-columns: 1fr; } .page-header h1 { font-size: 1.1rem; } }
    </style>
    <script>
        function autoApplyFilter() { document.getElementById('filterForm').submit(); }
        function handleEnterKey(e) { if(e.key==='Enter') autoApplyFilter(); }
    </script>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-dollar-sign"></i> Smartboard Price List</h1>
        <div class="breadcrumb">
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Smartboard Price List</span>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chalkboard"></i></div>
            <div class="stat-value"><?= number_format($total_count) ?></div>
            <div class="stat-label">Total In‑Stock</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-tag"></i></div>
            <div class="stat-value"><?= number_format(count($with_price)) ?></div>
            <div class="stat-label">With Price</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
            <div class="stat-value"><?= number_format($without_price) ?></div>
            <div class="stat-label">Missing Price</div>
        </div>
    </div>

    <div class="filter-section">
        <div class="filter-title"><i class="fas fa-filter"></i> Filter Smartboards</div>
        <form method="GET" id="filterForm" class="filter-form">
            <div class="filter-group">
                <label>Serial Number</label>
                <input type="text" name="sn" placeholder="Scan or type serial" value="<?= htmlspecialchars($search_sn) ?>" onkeydown="handleEnterKey(event)">
            </div>
            <div class="filter-group">
                <label>Model</label>
                <input type="text" name="model" placeholder="Search model..." value="<?= htmlspecialchars($search_model) ?>" onkeydown="handleEnterKey(event)">
            </div>
            <?php if ($role !== 'manager'): ?>
                <div class="filter-group">
                    <label>Branch</label>
                    <select name="branch" onchange="autoApplyFilter()">
                        <option value="">-- All --</option>
                        <option value="KIMATHI" <?= $search_branch=='KIMATHI'?'selected':'' ?>>KIMATHI</option>
                        <option value="MOI" <?= $search_branch=='MOI'?'selected':'' ?>>MOI</option>
                    </select>
                </div>
            <?php endif; ?>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                <a href="pricelist.php" class="btn btn-primary" style="background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-300);">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <div class="table-responsive">
            <?php if (empty($smartboards)): ?>
                <div class="empty-state">
                    <i class="fas fa-chalkboard"></i>
                    <p>No in‑stock smartboards found.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Serial</th>
                            <th>Model</th>
                            <th>Size</th>
                            <th>Branch</th>
                            <th>Price (KES)</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i=1; foreach ($smartboards as $s): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><span class="serial-code"><?= htmlspecialchars($s['serial_number']) ?></span></td>
                            <td><strong><?= htmlspecialchars($s['model']) ?></strong></td>
                            <td><?= (int)$s['size_inches'] ?>″</td>
                            <td><span class="<?= $s['branch']=='KIMATHI' ? 'branch-kimathi' : 'branch-moi' ?>"><?= !empty($s['branch']) ? htmlspecialchars($s['branch']) : '-' ?></span></td>
                            <td class="price">
                                <?= $s['price'] !== null ? 'KES '.number_format($s['price'],2) : '-' ?>
                            </td>
                            <td><span class="badge"><?= ucfirst($s['status']) ?></span></td>
                            <td>
                                <?php if ($s['price'] === null): ?>
                                    <a class="btn btn-add" href="add_price.php?sn=<?= urlencode($s['serial_number']) ?>">
                                        <i class="fas fa-plus"></i> Add Price
                                    </a>
                                <?php else: ?>
                                    <a class="btn btn-primary" href="update_price.php?sn=<?= urlencode($s['serial_number']) ?>">
                                        <i class="fas fa-edit"></i> Update Price
                                    </a>
                                <?php endif; ?>
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
            if (mainContent) { mainContent.style.marginLeft = '0'; mainContent.style.width = '100%'; mainContent.style.paddingTop = '5rem'; }
        } else {
            if (mainContent && sidebar) { mainContent.style.marginLeft = '260px'; mainContent.style.width = 'calc(100% - 260px)'; mainContent.style.paddingTop = ''; }
        }
    }
    adjustMainContent();
    window.addEventListener('resize', adjustMainContent);
    window.addEventListener('orientationchange', adjustMainContent);
});
</script>

</body>
</html>