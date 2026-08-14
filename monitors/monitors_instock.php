<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$user_branch = null;
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
    if (!$user_branch) die("Your account has no branch assigned.");
}

$filter_serial = $_GET['serial'] ?? '';
$filter_branch = $_GET['branch'] ?? '';

$sql = "SELECT m.serial_number, m.model_name, m.size_inches, m.branch, m.date_added, u.full_name AS added_by
        FROM monitors m
        JOIN users u ON m.added_by = u.id
        WHERE m.status = 'In Stock'";
$params = [];

if ($user_role !== 'super_admin') {
    $sql .= " AND m.branch = ?";
    $params[] = $user_branch;
}
if (!empty($filter_serial)) {
    $sql .= " AND m.serial_number LIKE ?";
    $params[] = "%$filter_serial%";
}
if ($user_role === 'super_admin' && !empty($filter_branch)) {
    $sql .= " AND m.branch = ?";
    $params[] = $filter_branch;
}
$sql .= " ORDER BY m.date_added DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$monitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>In‑Stock Monitors | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
            --primary-dark: #0f3a1e;
            --info: #2563eb;
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
        .filter-form { background: white; padding: 1.25rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; border: 1px solid var(--gray-200); display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group label { display: block; font-size: 0.75rem; font-weight: 500; color: var(--gray-600); margin-bottom: 0.25rem; }
        .filter-group input, .filter-group select { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.85rem; }
        .btn { padding: 0.6rem 1.2rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; text-decoration: none; }
        .btn-secondary { background: var(--gray-500); }
        .btn-view { background: var(--info); color: white; padding: 0.4rem 1rem; border-radius: var(--radius-md); font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; transition: background 0.2s; }
        .btn-view:hover { background: #1d4ed8; }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th { background: var(--gray-50); padding: 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        td { padding: 0.9rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 500; background: var(--gray-100); }
        .branch-kimathi { color: #059669; font-weight: 500; }
        .branch-moi { color: #3b82f6; font-weight: 500; }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .filter-form { flex-direction: column; } .filter-group { min-width: auto; } .btn, .btn-view { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-box"></i> In‑Stock Monitors</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="../dashboard/inventorydashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Monitors In Stock</span>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= count($monitors) ?></div><div class="stat-label">Total In Stock</div></div>
        <div class="stat-card"><div class="stat-value"><?= ($user_role === 'super_admin' ? '2' : '1') ?></div><div class="stat-label">Branch(es)</div></div>
    </div>

    <form method="GET" class="filter-form" id="filterForm">
        <div class="filter-group">
            <label>Serial Number</label>
            <input type="text" name="serial" placeholder="Scan or type..." value="<?= htmlspecialchars($filter_serial) ?>" autofocus>
        </div>
        <?php if ($user_role === 'super_admin'): ?>
            <div class="filter-group">
                <label>Branch</label>
                <select name="branch">
                    <option value="">All Branches</option>
                    <option value="KIMATHI" <?= $filter_branch === 'KIMATHI' ? 'selected' : '' ?>>KIMATHI</option>
                    <option value="MOI" <?= $filter_branch === 'MOI' ? 'selected' : '' ?>>MOI</option>
                </select>
            </div>
        <?php endif; ?>
        <div class="filter-group">
            <button type="submit" class="btn"><i class="fas fa-search"></i> Search</button>
            <a href="monitors_instock.php" class="btn btn-secondary" style="background:var(--gray-500); margin-left:0.5rem;">Reset</a>
        </div>
    </form>

    <div class="table-wrapper">
        <div class="table-responsive">
            <?php if ($monitors): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Serial</th>
                            <th>Model</th>
                            <th>Size</th>
                            <th>Branch</th>
                            <th>Added By</th>
                            <th>Date Added</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i=1; foreach ($monitors as $m): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><code><?= htmlspecialchars($m['serial_number']) ?></code></td>
                            <td><?= htmlspecialchars($m['model_name']) ?></td>
                            <td><?= $m['size_inches'] ?>\"</td>
                            <td class="<?= $m['branch'] === 'KIMATHI' ? 'branch-kimathi' : 'branch-moi' ?>"><?= htmlspecialchars($m['branch']) ?></td>
                            <td><?= htmlspecialchars($m['added_by']) ?></td>
                            <td><?= date('M j, Y', strtotime($m['date_added'])) ?></td>
                            <td><a href="view_monitor.php?sn=<?= urlencode($m['serial_number']) ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-box-open"></i><p>No monitors in stock.</p></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>