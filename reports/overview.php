<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Fetch all inventory items with added_by, specs, and status
function fetchAllInventory($conn, $filters) {
    $allItems = [];

    // 1. Devices
    $sql = "SELECT d.model_name AS item_name, 'Device' AS category,
                   d.branch, d.date_added, d.serial_number AS ref_id,
                   'device' AS source, d.added_by, u.full_name AS added_by_name,
                   d.status,
                   CONCAT(
                       d.processor, ' | ',
                       d.ram, 'GB RAM | ',
                       d.storage_type, ' ', d.storage_capacity, 'GB',
                       IFNULL(CONCAT(' | ', d.graphics), ''),
                       IF(c.category_name IN ('Laptop', 'AIO', 'POS'), CONCAT(' | ', d.touch), '')
                   ) AS specs
            FROM devices d
            LEFT JOIN users u ON d.added_by = u.id
            LEFT JOIN categories c ON d.category_id = c.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 2. Monitors
    $sql = "SELECT m.model_name AS item_name, 'Monitor' AS category,
                   m.branch, m.date_added, m.serial_number AS ref_id,
                   'monitor' AS source, m.added_by, u.full_name AS added_by_name,
                   m.status,
                   CONCAT(m.size_inches, ' inch') AS specs
            FROM monitors m
            LEFT JOIN users u ON m.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 3. Printers
    $sql = "SELECT p.model_name AS item_name, 'Printer' AS category,
                   p.branch, p.date_added, p.serial_number AS ref_id,
                   'printer' AS source, p.added_by, u.full_name AS added_by_name,
                   p.status,
                   'N/A' AS specs
            FROM printers p
            LEFT JOIN users u ON p.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 4. Smartboards
    $sql = "SELECT s.model AS item_name, 'Smartboard' AS category,
                   s.branch, s.date_added, s.serial_number AS ref_id,
                   'smartboard' AS source, s.added_by, u.full_name AS added_by_name,
                   s.status,
                   CONCAT(s.model, ' | ', s.size_inches, ' inch') AS specs
            FROM smartboards s
            LEFT JOIN users u ON s.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 5. Phones
    $sql = "SELECT CONCAT(COALESCE(p.brand,''), ' ', COALESCE(p.model,'')) AS item_name,
                   'Phone' AS category,
                   p.branch, p.date_added, p.serial_number AS ref_id,
                   'phone' AS source, p.added_by, u.full_name AS added_by_name,
                   p.status,
                   CONCAT(COALESCE(p.brand,''), ' ', COALESCE(p.model,''), ' | ',
                          p.ram, 'GB RAM | ', p.storage_capacity, 'GB') AS specs
            FROM phones p
            LEFT JOIN users u ON p.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 6. UPS
    $sql = "SELECT u.model AS item_name, 'UPS' AS category,
                   u.branch, u.date_added, u.serial_number AS ref_id,
                   'ups' AS source, u.added_by, usr.full_name AS added_by_name,
                   u.status,
                   CONCAT(u.model, ' | ', u.capacity, ' VA') AS specs
            FROM ups u
            LEFT JOIN users usr ON u.added_by = usr.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 7. Accessories
    $sql = "SELECT a.name AS item_name, 'Accessory' AS category,
                   a.branch, a.date_added, CAST(a.id AS CHAR) AS ref_id,
                   'accessory' AS source, a.added_by, u.full_name AS added_by_name,
                   a.status,
                   CONCAT('Qty: ', a.quantity, ' | ', COALESCE(a.price, 'No price')) AS specs
            FROM accessories a
            LEFT JOIN users u ON a.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 8. Chargers (no status column; derive from quantity)
    $sql = "SELECT c.charger_type AS item_name, 'Charger' AS category,
                   c.branch, c.date_updated AS date_added, CAST(c.id AS CHAR) AS ref_id,
                   'charger' AS source, c.updated_by AS added_by, u.full_name AS added_by_name,
                   IF(c.quantity > 0, 'In Stock', 'Out of Stock') AS status,
                   CONCAT(c.charger_condition, ' | Qty: ', c.quantity) AS specs
            FROM chargers c
            LEFT JOIN users u ON c.updated_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 9. HDDs (no status column; derive from quantity)
    $sql = "SELECT CONCAT(h.type, ' ', h.storage) AS item_name, 'HDD' AS category,
                   h.branch, h.date_added, CAST(h.id AS CHAR) AS ref_id,
                   'hdd' AS source, h.added_by, u.full_name AS added_by_name,
                   IF(h.quantity > 0, 'In Stock', 'Out of Stock') AS status,
                   CONCAT('Qty: ', h.quantity, ' | ', COALESCE(h.price, 'No price')) AS specs
            FROM hdds h
            LEFT JOIN users u ON h.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 10. RAM/SSD (no status column; derive from quantity)
    $sql = "SELECT CONCAT(r.category, ' ', r.type, ' ', r.storage, 'GB') AS item_name,
                   'RAM/SSD' AS category,
                   r.branch, r.date_added, CAST(r.id AS CHAR) AS ref_id,
                   'ram_ssd' AS source, r.added_by, u.full_name AS added_by_name,
                   IF(r.quantity > 0, 'In Stock', 'Out of Stock') AS status,
                   CONCAT('Qty: ', r.quantity, ' | ', COALESCE(r.price, 'No price')) AS specs
            FROM rams_ssds r
            LEFT JOIN users u ON r.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 11. Graphics Cards
    $sql = "SELECT CONCAT(g.type, ' ', g.storage_capacity, 'GB') AS item_name,
                   'Graphics Card' AS category,
                   g.branch, g.date_added, CAST(g.id AS CHAR) AS ref_id,
                   'graphic' AS source, g.added_by, u.full_name AS added_by_name,
                   g.status,
                   CONCAT('Qty: ', g.quantity, ' | ', COALESCE(g.price, 'No price')) AS specs
            FROM graphic_cards g
            LEFT JOIN users u ON g.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // --- Apply filters ---

    // 1. Date range
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $start = date('Y-m-d 00:00:00', strtotime($filters['start_date']));
        $end   = date('Y-m-d 23:59:59', strtotime($filters['end_date']));
        $allItems = array_filter($allItems, function($item) use ($start, $end) {
            if (empty($item['date_added'])) return false;
            $t = strtotime($item['date_added']);
            return $t >= strtotime($start) && $t <= strtotime($end);
        });
    }

    // 2. Category filter
    if (!empty($filters['category'])) {
        $allItems = array_filter($allItems, function($item) use ($filters) {
            return strcasecmp($item['category'], $filters['category']) === 0;
        });
    }

    // 3. Branch filter
    if (!empty($filters['branch'])) {
        $allItems = array_filter($allItems, function($item) use ($filters) {
            return strcasecmp($item['branch'], $filters['branch']) === 0;
        });
    }

    // 4. Added By filter
    if (!empty($filters['added_by'])) {
        $allItems = array_filter($allItems, function($item) use ($filters) {
            return $item['added_by'] == $filters['added_by'];
        });
    }

    // 5. Status filter
    if (!empty($filters['status'])) {
        $statusFilter = $filters['status'];
        $allItems = array_filter($allItems, function($item) use ($statusFilter) {
            $itemStatus = $item['status'] ?? 'Unknown';
            // Normalize status values for comparison
            $normalized = '';
            if (strtolower($itemStatus) === 'in stock' || strtolower($itemStatus) === 'instock') {
                $normalized = 'In Stock';
            } elseif (strtolower($itemStatus) === 'sold') {
                $normalized = 'Sold';
            } elseif (strtolower($itemStatus) === 'out of stock') {
                $normalized = 'Out of Stock';
            } else {
                $normalized = $itemStatus;
            }
            return strcasecmp($normalized, $statusFilter) === 0;
        });
    }

    // 6. Search filter (item_name, ref_id, specs)
    if (!empty($filters['search'])) {
        $search = strtolower($filters['search']);
        $allItems = array_filter($allItems, function($item) use ($search) {
            $name = strtolower($item['item_name'] ?? '');
            $ref = strtolower($item['ref_id'] ?? '');
            $specs = strtolower($item['specs'] ?? '');
            return strpos($name, $search) !== false ||
                   strpos($ref, $search) !== false ||
                   strpos($specs, $search) !== false;
        });
    }

    // Sort by date_added descending
    usort($allItems, function($a, $b) {
        $ta = $a['date_added'] ? strtotime($a['date_added']) : 0;
        $tb = $b['date_added'] ? strtotime($b['date_added']) : 0;
        return $tb - $ta;
    });

    return $allItems;
}

// Get distinct categories from all tables
function getCategories($conn) {
    $cats = [];
    $tables = ['devices' => 'Device', 'monitors' => 'Monitor', 'printers' => 'Printer',
               'smartboards' => 'Smartboard', 'phones' => 'Phone', 'ups' => 'UPS',
               'accessories' => 'Accessory', 'chargers' => 'Charger',
               'hdds' => 'HDD', 'rams_ssds' => 'RAM/SSD', 'graphic_cards' => 'Graphics Card'];
    foreach ($tables as $table => $cat) {
        $sql = "SELECT DISTINCT '$cat' AS category FROM $table";
        $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (!in_array($row['category'], $cats)) $cats[] = $row['category'];
        }
    }
    sort($cats);
    return $cats;
}

// Get distinct branches from all tables
function getBranches($conn) {
    $branches = [];
    $tables = ['devices', 'monitors', 'printers', 'smartboards', 'phones', 'ups',
               'accessories', 'chargers', 'hdds', 'rams_ssds', 'graphic_cards'];
    foreach ($tables as $table) {
        $sql = "SELECT DISTINCT branch FROM $table WHERE branch IS NOT NULL AND branch != ''";
        $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (!in_array($row['branch'], $branches)) $branches[] = $row['branch'];
        }
    }
    sort($branches);
    return $branches;
}

// Get distinct users who added items
function getAddedByUsers($conn) {
    $users = [];
    $sql = "SELECT id, full_name FROM users WHERE id IN (
                SELECT DISTINCT added_by FROM devices WHERE added_by IS NOT NULL
                UNION
                SELECT DISTINCT added_by FROM monitors WHERE added_by IS NOT NULL
                UNION
                SELECT DISTINCT added_by FROM printers WHERE added_by IS NOT NULL
                UNION
                SELECT DISTINCT added_by FROM smartboards WHERE added_by IS NOT NULL
                UNION
                SELECT DISTINCT added_by FROM phones WHERE added_by IS NOT NULL
                UNION
                SELECT DISTINCT added_by FROM ups WHERE added_by IS NOT NULL
                UNION
                SELECT DISTINCT added_by FROM accessories WHERE added_by IS NOT NULL
                UNION
                SELECT DISTINCT updated_by FROM chargers WHERE updated_by IS NOT NULL
                UNION
                SELECT DISTINCT added_by FROM hdds WHERE added_by IS NOT NULL
                UNION
                SELECT DISTINCT added_by FROM rams_ssds WHERE added_by IS NOT NULL
                UNION
                SELECT DISTINCT added_by FROM graphic_cards WHERE added_by IS NOT NULL
            )
            ORDER BY full_name";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get filters from GET
$filter_category = $_GET['filter_category'] ?? '';
$filter_search = trim($_GET['filter_search'] ?? '');
$filter_start_date = $_GET['filter_start_date'] ?? '';
$filter_end_date = $_GET['filter_end_date'] ?? '';
$filter_branch = $_GET['filter_branch'] ?? '';
$filter_added_by = $_GET['filter_added_by'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

$filters = [
    'category'   => $filter_category,
    'search'     => $filter_search,
    'start_date' => $filter_start_date,
    'end_date'   => $filter_end_date,
    'branch'     => $filter_branch,
    'added_by'   => $filter_added_by,
    'status'     => $filter_status
];

// Renamed variable to avoid conflict with sidebar's $items
$inventoryItems = fetchAllInventory($conn, $filters);
$categories = getCategories($conn);
$branches = getBranches($conn);
$users = getAddedByUsers($conn);

$total_count = count($inventoryItems);
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Inventory Overview | Mombasa Computers</title>
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
        .filter-group select, .filter-group input { padding: 0.625rem 0.875rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; width: 100%; }
        .filter-actions { display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap; }
        .btn { padding: 0.625rem 1.25rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-size: 0.9rem; }
        .btn-secondary { background: var(--gray-500); }
        .btn-excel { background: #217346; }
        .btn:hover { opacity: 0.9; }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1200px; font-size: 0.85rem; }
        th { background: var(--gray-50); padding: 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        td { padding: 0.8rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: var(--gray-100); }
        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .status-instock { background: #d1fae5; color: #065f46; }
        .status-sold { background: #fee2e2; color: #991b1b; }
        .status-outofstock { background: #fef3c7; color: #92400e; }
        .specs-text { font-size: 0.8rem; color: var(--gray-600); word-wrap: break-word; max-width: 350px; display: inline-block; }
        .serial-code { font-family: 'Courier New', monospace; font-size: 0.85rem; background: var(--gray-50); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); display: inline-block; }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .filter-grid { grid-template-columns: 1fr; } .btn { width: 100%; justify-content: center; } .stats-row { flex-direction: column; } .filter-actions { flex-direction: column; align-items: stretch; } table { font-size: 0.75rem; } .specs-text { max-width: 200px; } }
    </style>
</head>
<body>
    <?php require_once "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-list-ul"></i> Inventory Overview</h1>
        <div class="breadcrumb">
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php">Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php">Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'sales'): ?>
                <a href="/inventory_system/dashboard/salesdashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="/inventory_system/index.php">Home</a>
            <?php endif; ?>
            <span> / </span>
            <span>Overview</span>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= number_format($total_count) ?></div><div class="stat-label">Total Items</div></div>
        <div class="stat-card"><div class="stat-value"><?= number_format(count($categories)) ?></div><div class="stat-label">Categories</div></div>
        <div class="stat-card"><div class="stat-value"><?= number_format(count($branches)) ?></div><div class="stat-label">Branches</div></div>
        <?php if (!empty($inventoryItems)): ?>
            <div class="stat-card"><div class="stat-value"><?= date('Y-m-d', strtotime($inventoryItems[0]['date_added'])) ?></div><div class="stat-label">Newest Added</div></div>
        <?php endif; ?>
    </div>

    <div class="filter-section">
        <div class="filter-title"><i class="fas fa-filter"></i> Filter Inventory</div>
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label>Category</label>
                <select name="filter_category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $filter_category == $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Search (name, ref, specs)</label>
                <input type="text" name="filter_search" placeholder="Search..." value="<?= htmlspecialchars($filter_search) ?>">
            </div>
            <div class="filter-group">
                <label>Start Date</label>
                <input type="date" name="filter_start_date" value="<?= htmlspecialchars($filter_start_date) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="filter-group">
                <label>End Date</label>
                <input type="date" name="filter_end_date" value="<?= htmlspecialchars($filter_end_date) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="filter-group">
                <label>Branch</label>
                <select name="filter_branch">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= htmlspecialchars($br) ?>" <?= $filter_branch == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Added By</label>
                <select name="filter_added_by">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filter_added_by == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="filter_status">
                    <option value="">All Statuses</option>
                    <option value="In Stock" <?= $filter_status == 'In Stock' ? 'selected' : '' ?>>In Stock</option>
                    <option value="Sold" <?= $filter_status == 'Sold' ? 'selected' : '' ?>>Sold</option>
                    <option value="Out of Stock" <?= $filter_status == 'Out of Stock' ? 'selected' : '' ?>>Out of Stock</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn"><i class="fas fa-search"></i> Filter</button>
                <a href="overview.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                <?php if (!empty($inventoryItems)): ?>
                    <a href="export_inventory_excel.php?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn btn-excel"><i class="fas fa-file-excel"></i> Export to Excel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <?php if (empty($inventoryItems)): ?>
            <div class="empty-state"><i class="fas fa-box-open" style="font-size:2rem; display:block; margin-bottom:1rem;"></i><p>No items found matching your criteria.</p></div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Branch</th>
                        <th>Added By</th>
                        <th>Status</th>
                        <th>Date Added</th>
                        <th>Reference</th>
                        <th>Specifications</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($inventoryItems as $item): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                        <td><span class="badge"><?= htmlspecialchars($item['category']) ?></span></td>
                        <td><?= htmlspecialchars($item['branch'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($item['added_by_name'] ?? '-') ?></td>
                        <td>
                            <?php
                            $status = $item['status'] ?? 'Unknown';
                            $statusClass = '';
                            if (strtolower($status) === 'in stock' || strtolower($status) === 'instock') {
                                $statusClass = 'status-instock';
                                $displayStatus = 'In Stock';
                            } elseif (strtolower($status) === 'sold') {
                                $statusClass = 'status-sold';
                                $displayStatus = 'Sold';
                            } elseif (strtolower($status) === 'out of stock') {
                                $statusClass = 'status-outofstock';
                                $displayStatus = 'Out of Stock';
                            } else {
                                $displayStatus = $status;
                            }
                            ?>
                            <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($displayStatus) ?></span>
                        </td>
                        <td><?= $item['date_added'] ? date('Y-m-d H:i', strtotime($item['date_added'])) : '-' ?></td>
                        <td><span class="serial-code"><?= htmlspecialchars($item['ref_id'] ?? '-') ?></span></td>
                        <td><span class="specs-text" title="<?= htmlspecialchars($item['specs'] ?? '') ?>"><?= htmlspecialchars($item['specs'] ?? '-') ?></span></td>
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