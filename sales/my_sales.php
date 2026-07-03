<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";


// Only sales role can access
if ($_SESSION['role'] !== 'sales') {
    die("ACCESS DENIED. Only sales personnel can view their sales.");
}

$user_id = (int) $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Get filter inputs
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================
// FETCH ALL SALES FOR THIS SALESPERSON FROM ALL TABLES
// with specs (including condition if available)
// ============================================================
function fetchUnifiedSales($conn, $user_id, $start_date, $end_date, $search = '') {
    $allSales = [];

    // Helper to build specs with optional condition
    // In SQL we'll build it directly using CONCAT and IF

    // 1. Devices
    $sql = "SELECT 
                model_name AS item_name,
                'Device' AS category,
                serial_number AS id,
                selling_price AS price,
                sold_at,
                branch,
                TRIM(CONCAT(
                    COALESCE(processor, ''),
                    IF(processor IS NOT NULL, ' | ', ''),
                    COALESCE(ram, ''), IF(ram IS NOT NULL, 'GB RAM', ''),
                    IF(storage_type IS NOT NULL AND storage_capacity IS NOT NULL, CONCAT(' | ', storage_type, ' ', storage_capacity, 'GB'), ''),
                    IF(graphics IS NOT NULL AND graphics != '', CONCAT(' | ', graphics), ''),
                    IF(touch IS NOT NULL AND touch != 'N/A', CONCAT(' | ', touch), ''),
                    IF(device_condition IS NOT NULL AND device_condition != '', CONCAT(' | ', device_condition), '')
                )) AS specs
            FROM devices
            WHERE status = 'Sold' AND sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 2. Monitors
    $sql = "SELECT 
                model_name AS item_name,
                'Monitor' AS category,
                serial_number AS id,
                selling_price AS price,
                sold_at,
                branch,
                TRIM(CONCAT(
                    size_inches, ' inch',
                    IF(monitor_condition IS NOT NULL AND monitor_condition != '', CONCAT(' | ', monitor_condition), '')
                )) AS specs
            FROM monitors
            WHERE status = 'Sold' AND sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 3. Printers
    $sql = "SELECT 
                model_name AS item_name,
                'Printer' AS category,
                serial_number AS id,
                selling_price AS price,
                date_sold AS sold_at,
                branch,
                TRIM(CONCAT(
                    'N/A',
                    IF(printer_condition IS NOT NULL AND printer_condition != '', CONCAT(' | ', printer_condition), '')
                )) AS specs
            FROM printers
            WHERE status = 'Sold' AND sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 4. Smartboards (no condition field)
    $sql = "SELECT 
                model AS item_name,
                'Smartboard' AS category,
                serial_number AS id,
                selling_price AS price,
                sold_at,
                branch,
                CONCAT(model, ' | ', size_inches, ' inch') AS specs
            FROM smartboards
            WHERE status = 'sold' AND sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 5. Sold Accessories (no condition)
    $sql = "SELECT 
                accessory_name AS item_name,
                'Accessory' AS category,
                CONCAT('-') AS id,
                total_price AS price,
                date_sold AS sold_at,
                branch,
                CONCAT(quantity, ' x ', selling_price, ' = ', total_price) AS specs
            FROM sold_accessories
            WHERE sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 6. Sold Chargers (no condition)
    $sql = "SELECT 
                charger_type AS item_name,
                'Charger' AS category,
                CONCAT('-') AS id,
                total_price AS price,
                date_sold AS sold_at,
                branch,
                CONCAT(quantity, ' x ', charger_condition, ' | ', selling_price, ' each') AS specs
            FROM sold_chargers
            WHERE sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 7. Phones
    $sql = "SELECT 
                CONCAT(COALESCE(brand,''), ' ', COALESCE(model,'')) AS item_name,
                'Phone' AS category,
                serial_number AS id,
                selling_price AS price,
                date_sold AS sold_at,
                branch,
                TRIM(CONCAT(
                    COALESCE(brand,''), ' ', COALESCE(model,''), ' | ',
                    ram, 'GB RAM | ',
                    storage_capacity, 'GB',
                    IF(phone_condition IS NOT NULL AND phone_condition != '', CONCAT(' | ', phone_condition), '')
                )) AS specs
            FROM phones
            WHERE status = 'sold' AND sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 8. UPS
    $sql = "SELECT 
                model AS item_name,
                'UPS' AS category,
                serial_number AS id,
                selling_price AS price,
                date_sold AS sold_at,
                branch,
                TRIM(CONCAT(
                    model, ' | ', capacity, ' VA',
                    IF(ups_condition IS NOT NULL AND ups_condition != '', CONCAT(' | ', ups_condition), '')
                )) AS specs
            FROM ups
            WHERE status = 'sold' AND sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 9. Sold RAM/SSD (no condition)
    $sql = "SELECT 
                CONCAT(COALESCE(type,''), ' ', COALESCE(storage,''), 'GB') AS item_name,
                category AS category,
                CONCAT('-') AS id,
                total_price AS price,
                date_sold AS sold_at,
                branch,
                CONCAT(quantity, ' x ', type, ' ', storage, 'GB') AS specs
            FROM sold_rams_ssds
            WHERE sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 10. Sold HDDs (no condition)
    $sql = "SELECT 
                CONCAT(COALESCE(type,''), ' ', COALESCE(storage,'')) AS item_name,
                'HDD' AS category,
                CONCAT('-') AS id,
                total_price AS price,
                date_sold AS sold_at,
                branch,
                CONCAT(quantity, ' x ', type, ' ', storage) AS specs
            FROM sold_hdds
            WHERE sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 11. Sold Graphics Cards (no condition)
    $sql = "SELECT 
                CONCAT(COALESCE(type,''), ' ', COALESCE(storage_capacity,''), 'GB') AS item_name,
                'Graphics Card' AS category,
                CONCAT('-') AS id,
                total_price AS price,
                date_sold AS sold_at,
                branch,
                CONCAT(quantity, ' x ', type, ' ', storage_capacity, 'GB') AS specs
            FROM sold_graphics_cards
            WHERE sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // Date range filter
    if (!empty($start_date) && !empty($end_date)) {
        $allSales = array_filter($allSales, function($sale) use ($start_date, $end_date) {
            $sold_at = strtotime($sale['sold_at']);
            return $sold_at >= strtotime($start_date) && $sold_at <= strtotime($end_date . ' 23:59:59');
        });
    }

    // Search filter (item_name or id)
    if (!empty($search)) {
        $searchLower = strtolower($search);
        $allSales = array_filter($allSales, function($sale) use ($searchLower) {
            return stripos($sale['item_name'], $searchLower) !== false ||
                   stripos($sale['id'], $searchLower) !== false ||
                   stripos($sale['specs'] ?? '', $searchLower) !== false;
        });
    }

    // Sort by sold_at descending
    usort($allSales, function($a, $b) {
        return strtotime($b['sold_at']) - strtotime($a['sold_at']);
    });

    return $allSales;
}

$sales = fetchUnifiedSales($conn, $user_id, $start_date, $end_date, $search);

$total_count = count($sales);
$total_revenue = array_sum(array_column($sales, 'price'));
$avg_price = $total_count > 0 ? $total_revenue / $total_count : 0;

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
    <title>My Sales | Mombasa Computers</title>
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
        .filter-group input, .filter-group select { padding: 0.625rem 0.875rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        .filter-group input[type="date"] { padding: 0.625rem 0.875rem; }
        .filter-actions { display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap; }
        .btn { padding: 0.625rem 1.25rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-size: 0.9rem; }
        .btn-secondary { background: var(--gray-500); }
        .btn-excel { background: #217346; }
        .btn:hover { opacity: 0.9; }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 850px; font-size: 0.9rem; }
        th { background: var(--gray-50); padding: 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); }
        td { padding: 0.9rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: var(--gray-100); }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .specs-text { font-size: 0.8rem; color: var(--gray-600); word-wrap: break-word; max-width: 350px; display: inline-block; }
        @media (max-width: 1200px) { 
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } 
        }
        @media (max-width: 768px) { 
            .filter-grid { grid-template-columns: 1fr; } 
            .btn { width: 100%; justify-content: center; } 
            .stats-row { flex-direction: column; } 
            .filter-actions { flex-direction: column; align-items: stretch; }
            table { font-size: 0.75rem; min-width: 650px; }
            .specs-text { max-width: 150px; }
        }
        .text-muted { color: var(--gray-500); }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> My Sales</h1>
        <div class="breadcrumb">
            <a href="/inventory_system/dashboard/salesdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <span>My Sales</span>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= number_format($total_count) ?></div><div class="stat-label">Total Items Sold</div></div>
        <div class="stat-card"><div class="stat-value">KES <?= number_format($total_revenue, 0) ?></div><div class="stat-label">Total Revenue</div></div>
        <div class="stat-card"><div class="stat-value">KES <?= number_format($avg_price, 0) ?></div><div class="stat-label">Average Sale</div></div>
    </div>

    <div class="filter-section">
        <div class="filter-title"><i class="fas fa-filter"></i> Filter My Sales</div>
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label>Keyword (item name or ID/serial)</label>
                <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="filter-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="filter-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn"><i class="fas fa-search"></i> Filter</button>
                <a href="my_sales.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                <?php if (!empty($sales)): ?>
                    <a href="export_sales_excel.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&search=<?= urlencode($search) ?>" class="btn btn-excel"><i class="fas fa-file-excel"></i> Export to Excel</a>
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
                        <th>Serial</th>
                        <th>Specifications</th>
                        <th>Price (KES)</th>
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
                        <td><?= htmlspecialchars($sale['branch'] ?? '-') ?></td>
                        <td><?= date('M j, Y g:i A', strtotime($sale['sold_at'])) ?></td>
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