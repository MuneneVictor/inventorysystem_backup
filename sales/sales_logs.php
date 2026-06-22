<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

$role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

// Allowed roles: super_admin, cashier, manager
if (!in_array($role, ['super_admin', 'manager', 'cashier'])) {
    die("ACCESS DENIED.");
}

$user_branch = null;
if ($role === 'manager') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
}

// Get filter inputs
$filter_category = $_GET['filter_category'] ?? '';
$filter_search = trim($_GET['filter_search'] ?? '');
$filter_start_date = $_GET['filter_start_date'] ?? '';
$filter_end_date = $_GET['filter_end_date'] ?? '';
$filter_user = $_GET['filter_user'] ?? '';
$filter_branch = $_GET['filter_branch'] ?? '';

// For super_admin, get list of sales users for filter dropdown
$sales_users = [];
if ($role === 'super_admin') {
    $stmt = $conn->query("SELECT id, full_name, branch FROM users WHERE role = 'sales' ORDER BY full_name");
    $sales_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ----------------------------------------------------------------------
// Unified fetch function – returns all sales from all tables with specs
// ----------------------------------------------------------------------
function fetchAllSales($conn, $filters) {
    $allSales = [];

    // 1. Devices
  // 1. Devices – include touch for Laptop, AIO, POS
    $sql = "SELECT d.model_name AS item_name, 'Device' AS category,
                d.serial_number AS id, d.selling_price AS price,
                d.sold_at, d.branch, d.sold_by, u.full_name AS sold_by_name,
                CONCAT(
                    d.processor, ' | ',
                    d.ram, 'GB RAM | ',
                    d.storage_type, ' ', d.storage_capacity, 'GB',
                    IFNULL(CONCAT(' | ', d.graphics), ''),
                    IF(c.category_name IN ('Laptop', 'AIO', 'POS'), CONCAT(' | ', d.touch), '')
                ) AS specs
            FROM devices d
            LEFT JOIN users u ON d.sold_by = u.id
            LEFT JOIN categories c ON d.category_id = c.id
            WHERE d.status = 'Sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 2. Monitors
    $sql = "SELECT m.model_name AS item_name, 'Monitor' AS category, 
                   m.serial_number AS id, m.selling_price AS price, 
                   m.sold_at, m.branch, m.sold_by, u.full_name AS sold_by_name,
                   CONCAT(m.size_inches, ' inch') AS specs
            FROM monitors m
            LEFT JOIN users u ON m.sold_by = u.id
            WHERE m.status = 'Sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 3. Printers
    $sql = "SELECT p.model_name AS item_name, 'Printer' AS category, 
                   p.serial_number AS id, p.selling_price AS price, 
                   p.date_sold AS sold_at, p.branch, p.sold_by, u.full_name AS sold_by_name,
                   'N/A' AS specs
            FROM printers p
            LEFT JOIN users u ON p.sold_by = u.id
            WHERE p.status = 'Sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 4. Smartboards
    $sql = "SELECT s.model AS item_name, 'Smartboard' AS category, 
                   s.serial_number AS id, s.selling_price AS price, 
                   s.sold_at, s.branch, s.sold_by, u.full_name AS sold_by_name,
                   CONCAT(s.model, ' | ', s.size_inches, ' inch') AS specs
            FROM smartboards s
            LEFT JOIN users u ON s.sold_by = u.id
            WHERE s.status = 'sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 5. UPS
    $sql = "SELECT ups.model AS item_name, 'UPS' AS category, 
                   ups.serial_number AS id, ups.selling_price AS price, 
                   ups.date_sold AS sold_at, ups.branch, ups.sold_by, u.full_name AS sold_by_name,
                   CONCAT(ups.model, ' | ', ups.capacity, ' VA') AS specs
            FROM ups
            LEFT JOIN users u ON ups.sold_by = u.id
            WHERE ups.status = 'sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 6. Phones
    $sql = "SELECT CONCAT(COALESCE(p.brand,''), ' ', COALESCE(p.model,'')) AS item_name, 'Phone' AS category, 
                   p.serial_number AS id, p.selling_price AS price, 
                   p.date_sold AS sold_at, p.branch, p.sold_by, u.full_name AS sold_by_name,
                   CONCAT(COALESCE(p.brand,''), ' ', COALESCE(p.model,''), ' | ', p.ram, 'GB RAM | ', p.storage_capacity, 'GB') AS specs
            FROM phones p
            LEFT JOIN users u ON p.sold_by = u.id
            WHERE p.status = 'sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 7. Sold Accessories
    $sql = "SELECT sa.accessory_name AS item_name, 'Accessory' AS category, 
                   NULL AS id, sa.total_price AS price, 
                   sa.date_sold AS sold_at, sa.branch, sa.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sa.quantity, ' x ', sa.selling_price, ' = ', sa.total_price) AS specs
            FROM sold_accessories sa
            LEFT JOIN users u ON sa.sold_by = u.id";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 8. Sold Chargers
    $sql = "SELECT sc.charger_type AS item_name, 'Charger' AS category, 
                   NULL AS id, sc.total_price AS price, 
                   sc.date_sold AS sold_at, sc.branch, sc.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sc.quantity, ' x ', sc.charger_condition, ' | ', sc.selling_price, ' each') AS specs
            FROM sold_chargers sc
            LEFT JOIN users u ON sc.sold_by = u.id";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 9. Sold Graphics Cards
    $sql = "SELECT CONCAT(COALESCE(sgc.type,''), ' ', COALESCE(sgc.storage_capacity,''), 'GB') AS item_name, 'Graphics Card' AS category, 
                   NULL AS id, sgc.total_price AS price, 
                   sgc.date_sold AS sold_at, sgc.branch, sgc.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sgc.quantity, ' x ', sgc.type, ' ', sgc.storage_capacity, 'GB') AS specs
            FROM sold_graphics_cards sgc
            LEFT JOIN users u ON sgc.sold_by = u.id";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 10. Sold HDDs
    $sql = "SELECT CONCAT(COALESCE(sh.type,''), ' ', COALESCE(sh.storage,'')) AS item_name, 'HDD' AS category, 
                   NULL AS id, sh.total_price AS price, 
                   sh.date_sold AS sold_at, sh.branch, sh.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sh.quantity, ' x ', sh.type, ' ', sh.storage) AS specs
            FROM sold_hdds sh
            LEFT JOIN users u ON sh.sold_by = u.id";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 11. Sold RAM/SSD
    $sql = "SELECT CONCAT(COALESCE(srs.type,''), ' ', COALESCE(srs.storage,''), 'GB') AS item_name, srs.category AS category, 
                   NULL AS id, srs.total_price AS price, 
                   srs.date_sold AS sold_at, srs.branch, srs.sold_by, u.full_name AS sold_by_name,
                   CONCAT(srs.quantity, ' x ', srs.type, ' ', srs.storage, 'GB') AS specs
            FROM sold_rams_ssds srs
            LEFT JOIN users u ON srs.sold_by = u.id";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // --- Apply filters ---

    // 1. Date range (if provided)
    $startDate = $filters['start_date'] ?? '';
    $endDate = $filters['end_date'] ?? '';
    if (!empty($startDate) && !empty($endDate)) {
        $start = date('Y-m-d 00:00:00', strtotime($startDate));
        $end   = date('Y-m-d 23:59:59', strtotime($endDate));
        $allSales = array_filter($allSales, function($s) use ($start, $end) {
            if (empty($s['sold_at'])) return false;
            $t = strtotime($s['sold_at']);
            return $t >= strtotime($start) && $t <= strtotime($end);
        });
    }

    // 2. Category filter
    if (!empty($filters['category'])) {
        $allSales = array_filter($allSales, function($s) use ($filters) {
            return strcasecmp($s['category'], $filters['category']) === 0;
        });
    }

    // 3. Search filter (on item_name, id, specs)
    if (!empty($filters['search'])) {
        $search = strtolower($filters['search']);
        $allSales = array_filter($allSales, function($s) use ($search) {
            $item = strtolower($s['item_name'] ?? '');
            $id = strtolower($s['id'] ?? '');
            $spec = strtolower($s['specs'] ?? '');
            return strpos($item, $search) !== false || strpos($id, $search) !== false || strpos($spec, $search) !== false;
        });
    }

    // 4. User filter (only for super_admin)
    if (!empty($filters['user_id'])) {
        $allSales = array_filter($allSales, function($s) use ($filters) {
            return $s['sold_by'] == $filters['user_id'];
        });
    }

    // 5. Branch filter
    if (!empty($filters['branch'])) {
        $allSales = array_filter($allSales, function($s) use ($filters) {
            return strcasecmp($s['branch'], $filters['branch']) === 0;
        });
    }

    // Sort by sold_at descending
    usort($allSales, function($a, $b) {
        $ta = $a['sold_at'] ? strtotime($a['sold_at']) : 0;
        $tb = $b['sold_at'] ? strtotime($b['sold_at']) : 0;
        return $tb - $ta;
    });

    return $allSales;
}

// Build filters array
$filters = [
    'category'   => $filter_category,
    'search'     => $filter_search,
    'start_date' => $filter_start_date,
    'end_date'   => $filter_end_date,
    'user_id'    => ($role === 'super_admin' && !empty($filter_user)) ? (int)$filter_user : null,
    'branch'     => ($role === 'super_admin' && !empty($filter_branch)) ? $filter_branch : ($role === 'manager' ? $user_branch : null)
];

$sales = fetchAllSales($conn, $filters);

$total_count = count($sales);
$total_revenue = array_sum(array_column($sales, 'price'));

date_default_timezone_set('Africa/Nairobi');
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
        table { width: 100%; border-collapse: collapse; min-width: 1100px; font-size: 0.85rem; }
        th { background: var(--gray-50); padding: 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        td { padding: 0.8rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: var(--gray-100); }
        .specs-text { font-size: 0.8rem; color: var(--gray-600); word-wrap: break-word; max-width: 350px; display: inline-block; }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .filter-grid { grid-template-columns: 1fr; } .btn { width: 100%; justify-content: center; } .stats-row { flex-direction: column; } .filter-actions { flex-direction: column; align-items: stretch; } table { font-size: 0.75rem; } .specs-text { max-width: 200px; } }
    </style>
</head>
<body>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> Sales Logs</h1>
        <div class="breadcrumb">
            <?php if ($role === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($role === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="/inventory_system/dashboard/cashierdashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Sales Logs</span>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= number_format($total_count) ?></div><div class="stat-label">Total Sales</div></div>
        <div class="stat-card"><div class="stat-value">KES <?= number_format($total_revenue, 0) ?></div><div class="stat-label">Total Revenue</div></div>
    </div>

    <div class="filter-section">
        <div class="filter-title"><i class="fas fa-filter"></i> Filter Sales</div>
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label>Category</label>
                <select name="filter_category">
                    <option value="">All Categories</option>
                    <option value="Device" <?= $filter_category == 'Device' ? 'selected' : '' ?>>Device</option>
                    <option value="Monitor" <?= $filter_category == 'Monitor' ? 'selected' : '' ?>>Monitor</option>
                    <option value="Printer" <?= $filter_category == 'Printer' ? 'selected' : '' ?>>Printer</option>
                    <option value="Smartboard" <?= $filter_category == 'Smartboard' ? 'selected' : '' ?>>Smartboard</option>
                    <option value="UPS" <?= $filter_category == 'UPS' ? 'selected' : '' ?>>UPS</option>
                    <option value="Phone" <?= $filter_category == 'Phone' ? 'selected' : '' ?>>Phone</option>
                    <option value="Accessory" <?= $filter_category == 'Accessory' ? 'selected' : '' ?>>Accessory</option>
                    <option value="Charger" <?= $filter_category == 'Charger' ? 'selected' : '' ?>>Charger</option>
                    <option value="Graphics Card" <?= $filter_category == 'Graphics Card' ? 'selected' : '' ?>>Graphics Card</option>
                    <option value="HDD" <?= $filter_category == 'HDD' ? 'selected' : '' ?>>HDD</option>
                    <option value="RAM/SSD" <?= $filter_category == 'RAM/SSD' ? 'selected' : '' ?>>RAM/SSD</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Search (name, serial, specs)</label>
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
            <?php if ($role === 'super_admin'): ?>
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
                <a href="sales_logs.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                <?php if (!empty($sales)): ?>
                    <a href="export_sales_excel_all.php?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn btn-excel"><i class="fas fa-file-excel"></i> Export to Excel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <?php if (empty($sales)): ?>
            <div class="empty-state"><i class="fas fa-chart-line" style="font-size:2rem; display:block; margin-bottom:1rem;"></i><p>No sales found matching your criteria.</p></div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>ID / Serial</th>
                        <th>Specifications</th>
                        <th>Price (KES)</th>
                        <th>Sold By</th>
                        <th>Branch</th>
                        <th>Date Sold</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($sales as $sale): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($sale['item_name']) ?></strong></td>
                        <td><span class="badge"><?= htmlspecialchars($sale['category']) ?></span></td>
                        <td><code><?= htmlspecialchars($sale['id'] ?? '-') ?></code></td>
                        <td><span class="specs-text" title="<?= htmlspecialchars($sale['specs'] ?? '') ?>"><?= htmlspecialchars($sale['specs'] ?? '-') ?></span></td>
                        <td><span class="text-muted">KES <?= number_format($sale['price'] ?? 0, 0) ?></span></td>
                        <td><?= htmlspecialchars($sale['sold_by_name'] ?? 'Unknown') ?></td>
                        <td><?= htmlspecialchars($sale['branch'] ?? '-') ?></td>
                        <td><?= $sale['sold_at'] ? date('M j, Y g:i A', strtotime($sale['sold_at'])) : '-' ?></td>
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