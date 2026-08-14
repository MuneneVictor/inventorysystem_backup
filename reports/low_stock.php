<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Restrict access: only roles with inventory oversight
if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager', 'cashier'])) {
    die("ACCESS DENIED.");
}

$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Get user branch (for cashier)
$user_branch = null;
if ($user_role === 'cashier') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
    if (!$user_branch) {
        die("Your account has no branch assigned.");
    }
}

// Filter parameters
$filter_branch = $_GET['filter_branch'] ?? '';
$filter_table = $_GET['filter_table'] ?? '';

// Fetch low stock items from each table
$lowStockItems = [];
$threshold = 10;

// 1. Accessories
$sql = "SELECT id, name AS item_name, quantity, branch, 'accessories' AS source_table FROM accessories WHERE quantity <= ? AND status = 'instock'";
$params = [$threshold];
if ($user_role === 'cashier') {
    $sql .= " AND branch = ?";
    $params[] = $user_branch;
} elseif (!empty($filter_branch)) {
    $sql .= " AND branch = ?";
    $params[] = $filter_branch;
}
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$lowStockItems = array_merge($lowStockItems, $stmt->fetchAll(PDO::FETCH_ASSOC));

// 2. Chargers
$sql = "SELECT id, charger_type AS item_name, quantity, branch, 'chargers' AS source_table FROM chargers WHERE quantity <= ?";
$params = [$threshold];
if ($user_role === 'cashier') {
    $sql .= " AND branch = ?";
    $params[] = $user_branch;
} elseif (!empty($filter_branch)) {
    $sql .= " AND branch = ?";
    $params[] = $filter_branch;
}
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$lowStockItems = array_merge($lowStockItems, $stmt->fetchAll(PDO::FETCH_ASSOC));

// 3. Graphic Cards
$sql = "SELECT id, CONCAT(type, ' ', storage_capacity, 'GB') AS item_name, quantity, branch, 'graphic_cards' AS source_table FROM graphic_cards WHERE quantity <= ? AND status = 'instock'";
$params = [$threshold];
if ($user_role === 'cashier') {
    $sql .= " AND branch = ?";
    $params[] = $user_branch;
} elseif (!empty($filter_branch)) {
    $sql .= " AND branch = ?";
    $params[] = $filter_branch;
}
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$lowStockItems = array_merge($lowStockItems, $stmt->fetchAll(PDO::FETCH_ASSOC));

// 4. HDDs
$sql = "SELECT id, CONCAT(type, ' ', storage) AS item_name, quantity, branch, 'hdds' AS source_table FROM hdds WHERE quantity <= ?";
$params = [$threshold];
if ($user_role === 'cashier') {
    $sql .= " AND branch = ?";
    $params[] = $user_branch;
} elseif (!empty($filter_branch)) {
    $sql .= " AND branch = ?";
    $params[] = $filter_branch;
}
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$lowStockItems = array_merge($lowStockItems, $stmt->fetchAll(PDO::FETCH_ASSOC));

// 5. RAM/SSD
$sql = "SELECT id, CONCAT(category, ' ', type, ' ', storage, 'GB') AS item_name, quantity, branch, 'rams_ssds' AS source_table FROM rams_ssds WHERE quantity <= ?";
$params = [$threshold];
if ($user_role === 'cashier') {
    $sql .= " AND branch = ?";
    $params[] = $user_branch;
} elseif (!empty($filter_branch)) {
    $sql .= " AND branch = ?";
    $params[] = $filter_branch;
}
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$lowStockItems = array_merge($lowStockItems, $stmt->fetchAll(PDO::FETCH_ASSOC));

// Apply table filter
if (!empty($filter_table)) {
    $lowStockItems = array_filter($lowStockItems, function($item) use ($filter_table) {
        return $item['source_table'] === $filter_table;
    });
}

// Sort by quantity ascending
usort($lowStockItems, function($a, $b) {
    return $a['quantity'] - $b['quantity'];
});

// Get distinct branches and tables for filters
$branches = [];
$tables = ['accessories', 'chargers', 'graphic_cards', 'hdds', 'rams_ssds'];
foreach ($lowStockItems as $item) {
    if (!empty($item['branch']) && !in_array($item['branch'], $branches)) {
        $branches[] = $item['branch'];
    }
}
sort($branches);

date_default_timezone_set('Africa/Nairobi');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Low Stock Items | Mombasa Computers</title>
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
            --gray-800: #1f2937;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --font-sans: 'Inter', system-ui, sans-serif;
            --danger: #dc2626;
            --warning: #d97706;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: all 0.3s ease; }
        .page-header { background: white; padding: 1.5rem 2rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .page-header h1 { font-size: 1.75rem; color: var(--gray-800); font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .filter-section { background: white; padding: 1rem 1.25rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; border: 1px solid var(--gray-200); }
        .filter-grid { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; min-width: 150px; }
        .filter-group label { font-size: 0.8rem; font-weight: 600; color: var(--gray-600); }
        .filter-group select, .filter-group input { padding: 0.4rem 0.6rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.85rem; width: 100%; background: white; }
        .btn { padding: 0.4rem 1rem; border: none; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); transform: translateY(-2px); }
        .btn-secondary { background: #2563eb; color: white; }
        .btn-secondary:hover { background: #1d4ed8; transform: translateY(-2px); }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; box-shadow: var(--shadow-sm); }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; min-width: 700px; }
        th { background: var(--gray-50); padding: 0.875rem 0.75rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); }
        td { padding: 0.75rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        tr:hover { background: var(--gray-50); }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 500; background: var(--gray-100); }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fed7aa; color: #92400e; }
        .empty-state { text-align: center; padding: 2.5rem; color: var(--gray-500); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .filter-grid { flex-direction: column; }
            .filter-group { min-width: 100%; }
            table { font-size: 0.8rem; min-width: 600px; }
            th, td { padding: 0.5rem; }
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Low Stock Items</h1>
        <div class="breadcrumb">
            <a href="../dashboard/<?= $user_role === 'cashier' ? 'cashierdashboard.php' : ($user_role === 'inventory_admin' ? 'inventorydashboard.php' : 'superadmindashboard.php') ?>">Dashboard</a>
            <span> / </span>
            <span>Low Stock</span>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label for="filter_branch">Branch</label>
                <select name="filter_branch" id="filter_branch">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= htmlspecialchars($br) ?>" <?= $filter_branch == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter_table">Categories</label>
                <select name="filter_table" id="filter_table">
                    <option value="">All Categories</option>
                    <option value="accessories" <?= $filter_table == 'accessories' ? 'selected' : '' ?>>Accessories</option>
                    <option value="chargers" <?= $filter_table == 'chargers' ? 'selected' : '' ?>>Chargers</option>
                    <option value="graphic_cards" <?= $filter_table == 'graphic_cards' ? 'selected' : '' ?>>Graphics Cards</option>
                    <option value="hdds" <?= $filter_table == 'hdds' ? 'selected' : '' ?>>HDDs</option>
                    <option value="rams_ssds" <?= $filter_table == 'rams_ssds' ? 'selected' : '' ?>>RAM/SSD</option>
                </select>
            </div>
            <div class="filter-group" style="flex-direction: row; align-items: flex-end; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                <a href="low_stock_items.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
        <?php if (empty($lowStockItems)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                <p>All stock levels are good! No items below <?= $threshold ?> units.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Branch</th>
                        <th>Table</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($lowStockItems as $item): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong></td>
                            <td>
                                <span class="badge <?= $item['quantity'] <= 2 ? 'badge-danger' : 'badge-warning' ?>">
                                    <?= (int)$item['quantity'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($item['branch'] ?? '—') ?></td>
                            <td><span class="badge"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['source_table']))) ?></span></td>
                            <td>
                                <?php if ($item['quantity'] <= 2): ?>
                                    <span class="badge badge-danger"><i class="fas fa-exclamation-circle"></i> Critical</span>
                                <?php else: ?>
                                    <span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Low</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <footer>
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </footer>
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
<?php require_once "../includes/footer.php"; ?>
</body>
</html>