<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

// STRICT ROLE CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    die("ACCESS DENIED: You do not have permission to access this page.");
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');
$user_branch = $_SESSION['branch'] ?? null;

// If branch not in session, get it from database
if (!$user_branch) {
    try {
        $stmt = $conn->prepare("SELECT branch FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $user_id]);
        $user_branch = $stmt->fetchColumn();
        $_SESSION['branch'] = $user_branch;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        $user_branch = null;
    }
}

if (!$user_branch) {
    die("Branch not assigned to your account. Please contact admin.");
}

function safeQuery($conn, $sql, $params = []) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database Error in " . $_SERVER['SCRIPT_NAME'] . ": " . $e->getMessage());
        return false;
    }
}

// ============================================================
// UNIFIED BRANCH SALES DATA
// ============================================================
function getBranchSales($conn, $branch, $dateFilter = '') {
    $sql = "
        SELECT 
            item_name,
            category,
            quantity,
            price,
            sold_at,
            sold_by
        FROM (
            SELECT 
                model_name AS item_name,
                'Device' AS category,
                1 AS quantity,
                selling_price AS price,
                sold_at,
                sold_by
            FROM devices
            WHERE status = 'Sold' AND branch = :branch

            UNION ALL

            SELECT 
                model_name,
                'Monitor',
                1,
                selling_price,
                sold_at,
                sold_by
            FROM monitors
            WHERE status = 'Sold' AND branch = :branch

            UNION ALL

            SELECT 
                model_name,
                'Printer',
                1,
                selling_price,
                date_sold AS sold_at,
                sold_by
            FROM printers
            WHERE status = 'Sold' AND branch = :branch

            UNION ALL

            SELECT 
                model,
                'Smartboard',
                1,
                selling_price,
                sold_at,
                sold_by
            FROM smartboards
            WHERE status = 'sold' AND branch = :branch

            UNION ALL

            SELECT 
                accessory_name,
                'Accessory',
                quantity,
                selling_price,
                date_sold AS sold_at,
                sold_by
            FROM sold_accessories
            WHERE branch = :branch

            UNION ALL

            SELECT 
                charger_type,
                'Charger',
                quantity,
                selling_price,
                date_sold AS sold_at,
                sold_by
            FROM sold_chargers
            WHERE branch = :branch

            UNION ALL

            SELECT 
                CONCAT(COALESCE(brand,''), ' ', COALESCE(model,'')) AS item_name,
                'Phone',
                1,
                selling_price,
                date_sold AS sold_at,
                sold_by
            FROM phones
            WHERE status = 'sold' AND branch = :branch

            UNION ALL

            SELECT 
                model,
                'UPS',
                1,
                selling_price,
                date_sold AS sold_at,
                sold_by
            FROM ups
            WHERE status = 'sold' AND branch = :branch

            UNION ALL

            SELECT 
                CONCAT(COALESCE(type,''), ' ', COALESCE(storage,''), 'GB') AS item_name,
                'RAM/SSD',
                quantity,
                total_price AS price,
                date_sold AS sold_at,
                sold_by
            FROM sold_rams_ssds
            WHERE branch = :branch

            UNION ALL

            SELECT 
                CONCAT(COALESCE(type,''), ' ', COALESCE(storage,'')) AS item_name,
                'HDD',
                quantity,
                total_price AS price,
                date_sold AS sold_at,
                sold_by
            FROM sold_hdds
            WHERE branch = :branch

            UNION ALL

            SELECT 
                CONCAT(COALESCE(type,''), ' ', COALESCE(storage_capacity,''), 'GB') AS item_name,
                'Graphics Card',
                quantity,
                total_price AS price,
                date_sold AS sold_at,
                sold_by
            FROM sold_graphics_cards
            WHERE branch = :branch
        ) AS all_sales
        WHERE 1 " . $dateFilter . "
    ";
    $stmt = safeQuery($conn, $sql, ['branch' => $branch]);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

// ============================================================
// FETCH TODAY, WEEK, MONTH STATS
// ============================================================
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

// Today
$todaySales = getBranchSales($conn, $user_branch, "AND DATE(sold_at) = '$today'");
$todaysCount = 0;
$todaysRevenue = 0;
foreach ($todaySales as $s) {
    $todaysCount += (int)$s['quantity'];
    $todaysRevenue += (float)$s['price'];
}
$todaysAvg = $todaysCount > 0 ? $todaysRevenue / $todaysCount : 0;

// This week
$weekSales = getBranchSales($conn, $user_branch, "AND DATE(sold_at) BETWEEN '$weekStart' AND '$weekEnd'");
$weeklyCount = 0;
$weeklyRevenue = 0;
foreach ($weekSales as $s) {
    $weeklyCount += (int)$s['quantity'];
    $weeklyRevenue += (float)$s['price'];
}

// This month
$monthSales = getBranchSales($conn, $user_branch, "AND DATE(sold_at) BETWEEN '$monthStart' AND '$monthEnd'");
$monthlyCount = 0;
$monthlyRevenue = 0;
foreach ($monthSales as $s) {
    $monthlyCount += (int)$s['quantity'];
    $monthlyRevenue += (float)$s['price'];
}

// ============================================================
// INVENTORY STOCK COUNTS (Branch-specific)
// ============================================================
$inventoryCounts = [];
$tables = [
    'devices' => ['label' => 'Devices', 'status' => "status = 'In Stock'", 'count_col' => 'serial_number'],
    'monitors' => ['label' => 'Monitors', 'status' => "status = 'In Stock'", 'count_col' => 'serial_number'],
    'printers' => ['label' => 'Printers', 'status' => "status = 'In Stock'", 'count_col' => 'serial_number'],
    'smartboards' => ['label' => 'Smartboards', 'status' => "status = 'instock'", 'count_col' => 'serial_number'],
    'phones' => ['label' => 'Phones', 'status' => "status = 'instock'", 'count_col' => 'serial_number'],
    'ups' => ['label' => 'UPS', 'status' => "status = 'instock'", 'count_col' => 'serial_number'],
];
$totalInStock = 0;
foreach ($tables as $table => $config) {
    $sql = "SELECT COUNT({$config['count_col']}) FROM $table WHERE {$config['status']} AND branch = :branch";
    $stmt = safeQuery($conn, $sql, ['branch' => $user_branch]);
    $count = $stmt ? (int)$stmt->fetchColumn() : 0;
    $inventoryCounts[$table] = $count;
    $totalInStock += $count;
}

// ============================================================
// LOW STOCK ITEMS (from all inventory tables)
// ============================================================
$threshold = 5;
$lowStockItems = [];

// RAM/SSD
$stmt = safeQuery($conn, "
    SELECT id, category, type, storage, quantity, branch 
    FROM rams_ssds 
    WHERE quantity < :threshold AND branch = :branch
", ['threshold' => $threshold, 'branch' => $user_branch]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'ram_ssd';
        $lowStockItems[] = $r;
    }
}

// Chargers
$stmt = safeQuery($conn, "
    SELECT id, charger_type AS type, watts AS storage, quantity, branch 
    FROM chargers 
    WHERE quantity < :threshold AND branch = :branch
", ['threshold' => $threshold, 'branch' => $user_branch]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['category'] = 'Charger';
        $r['source'] = 'charger';
        $lowStockItems[] = $r;
    }
}

// HDDs
$stmt = safeQuery($conn, "
    SELECT id, type, storage, quantity, branch 
    FROM hdds 
    WHERE quantity < :threshold AND branch = :branch
", ['threshold' => $threshold, 'branch' => $user_branch]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['category'] = 'HDD';
        $r['source'] = 'hdd';
        $lowStockItems[] = $r;
    }
}

// Graphics Cards
$stmt = safeQuery($conn, "
    SELECT id, type, storage_capacity AS storage, quantity, branch 
    FROM graphic_cards 
    WHERE status = 'instock' AND quantity < :threshold AND branch = :branch
", ['threshold' => $threshold, 'branch' => $user_branch]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['category'] = 'Graphics Card';
        $r['source'] = 'graphic_card';
        $lowStockItems[] = $r;
    }
}

// Accessories
$stmt = safeQuery($conn, "
    SELECT id, name AS type, quantity, branch 
    FROM accessories 
    WHERE quantity < :threshold AND branch = :branch
", ['threshold' => $threshold, 'branch' => $user_branch]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['category'] = 'Accessory';
        $r['source'] = 'accessory';
        $r['storage'] = null;
        $lowStockItems[] = $r;
    }
}

// Limit to 10 items for display
$lowStockItems = array_slice($lowStockItems, 0, 10);

// ============================================================
// TOP SELLING PRODUCTS TODAY (by revenue)
// ============================================================
$topProductsToday = [];
if (!empty($todaySales)) {
    $productSales = [];
    foreach ($todaySales as $s) {
        $key = $s['item_name'] . '|' . $s['category'];
        if (!isset($productSales[$key])) {
            $productSales[$key] = [
                'item_name' => $s['item_name'],
                'category' => $s['category'],
                'quantity' => 0,
                'revenue' => 0
            ];
        }
        $productSales[$key]['quantity'] += (int)$s['quantity'];
        $productSales[$key]['revenue'] += (float)$s['price'];
    }
    usort($productSales, function($a, $b) {
        return $b['revenue'] - $a['revenue'];
    });
    $topProductsToday = array_slice($productSales, 0, 5);
}

// ============================================================
// SALES BY HOUR TODAY
// ============================================================
$salesByHour = [];
if (!empty($todaySales)) {
    $hourData = [];
    foreach ($todaySales as $s) {
        $hour = date('H', strtotime($s['sold_at']));
        if (!isset($hourData[$hour])) {
            $hourData[$hour] = ['hour' => $hour, 'count' => 0, 'revenue' => 0];
        }
        $hourData[$hour]['count'] += (int)$s['quantity'];
        $hourData[$hour]['revenue'] += (float)$s['price'];
    }
    ksort($hourData);
    $salesByHour = array_values($hourData);
}

// ============================================================
// RECENT SALES (last 10 from all tables)
// ============================================================
$recentSales = [];
$sql = "
    SELECT 
        item_name,
        category,
        quantity,
        price,
        sold_at,
        sold_by
    FROM (
        SELECT 
            model_name AS item_name,
            'Device' AS category,
            1 AS quantity,
            selling_price AS price,
            sold_at,
            sold_by
        FROM devices
        WHERE status = 'Sold' AND branch = :branch

        UNION ALL

        SELECT 
            model_name,
            'Monitor',
            1,
            selling_price,
            sold_at,
            sold_by
        FROM monitors
        WHERE status = 'Sold' AND branch = :branch

        UNION ALL

        SELECT 
            model_name,
            'Printer',
            1,
            selling_price,
            date_sold AS sold_at,
            sold_by
        FROM printers
        WHERE status = 'Sold' AND branch = :branch

        UNION ALL

        SELECT 
            model,
            'Smartboard',
            1,
            selling_price,
            sold_at,
            sold_by
        FROM smartboards
        WHERE status = 'sold' AND branch = :branch

        UNION ALL

        SELECT 
            accessory_name,
            'Accessory',
            quantity,
            selling_price,
            date_sold AS sold_at,
            sold_by
        FROM sold_accessories
        WHERE branch = :branch

        UNION ALL

        SELECT 
            charger_type,
            'Charger',
            quantity,
            selling_price,
            date_sold AS sold_at,
            sold_by
        FROM sold_chargers
        WHERE branch = :branch

        UNION ALL

        SELECT 
            CONCAT(COALESCE(brand,''), ' ', COALESCE(model,'')) AS item_name,
            'Phone',
            1,
            selling_price,
            date_sold AS sold_at,
            sold_by
        FROM phones
        WHERE status = 'sold' AND branch = :branch

        UNION ALL

        SELECT 
            model,
            'UPS',
            1,
            selling_price,
            date_sold AS sold_at,
            sold_by
        FROM ups
        WHERE status = 'sold' AND branch = :branch

        UNION ALL

        SELECT 
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage,''), 'GB') AS item_name,
            'RAM/SSD',
            quantity,
            total_price AS price,
            date_sold AS sold_at,
            sold_by
        FROM sold_rams_ssds
        WHERE branch = :branch

        UNION ALL

        SELECT 
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage,'')) AS item_name,
            'HDD',
            quantity,
            total_price AS price,
            date_sold AS sold_at,
            sold_by
        FROM sold_hdds
        WHERE branch = :branch

        UNION ALL

        SELECT 
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage_capacity,''), 'GB') AS item_name,
            'Graphics Card',
            quantity,
            total_price AS price,
            date_sold AS sold_at,
            sold_by
        FROM sold_graphics_cards
        WHERE branch = :branch
    ) AS all_sales
    ORDER BY sold_at DESC
    LIMIT 10
";
$stmt = safeQuery($conn, $sql, ['branch' => $user_branch]);
$recentSales = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Fetch seller names for recent sales (we have sold_by id)
// We'll do a separate query to get names, or we can join in the query above, but to keep it simple we'll fetch names later.
// Since we have sold_by id, we can map them.
$sellerIds = array_unique(array_column($recentSales, 'sold_by'));
$sellerMap = [];
if (!empty($sellerIds)) {
    $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));
    $stmt = safeQuery($conn, "SELECT id, full_name FROM users WHERE id IN ($placeholders)", $sellerIds);
    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sellerMap[$row['id']] = $row['full_name'];
        }
    }
}
foreach ($recentSales as &$sale) {
    $sale['sold_by_name'] = $sellerMap[$sale['sold_by']] ?? 'Unknown';
}
unset($sale);

// ============================================================
// GREETING
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=yes">
    <title>Cashier Dashboard | Mombasa Computers</title>
    <style>
    /* ==== EXACT SAME CSS AS ORIGINAL – NO CHANGES ==== */
    :root {
        --primary: #1a4b2a;
        --primary-light: #2a6b3a;
        --primary-dark: #0f3a1e;
        --secondary: #1a4f6e;
        --secondary-light: #2a6f94;
        --secondary-dark: #0f3a4e;
        --accent: #f59e0b;
        --accent-light: #fbbf24;
        --accent-dark: #d97706;
        --success: #059669;
        --warning: #d97706;
        --danger: #dc2626;
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
        --gray-900: #111827;
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        --radius-sm: 0.375rem;
        --radius-md: 0.5rem;
        --radius-lg: 0.75rem;
        --radius-xl: 1rem;
        --font-sans: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
    }

    @import url('https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap');

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }

    .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: margin-left 0.3s ease, width 0.3s ease, padding 0.3s ease; overflow-x: hidden; max-width: 100%; position: relative; }

    .header-row { display: flex; justify-content: space-between; align-items: center; gap: 1.5rem; margin-bottom: 2rem; background: white; padding: 1.25rem 2rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); flex-wrap: wrap; }

    .page-title { font-size: 2rem; color: var(--primary-dark); font-weight: 700; letter-spacing: -0.02em; line-height: 1.2; }
    .welcome-text { font-size: 0.95rem; color: var(--gray-500); margin-top: 0.25rem; }
    .logo img { height: 48px; width: auto; filter: brightness(0.95); max-width: 100%; }

    .branch-badge { background: var(--primary); color: white; padding: 0.25rem 1rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; }

    .card-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }

    .card { padding: 1.5rem; border-radius: var(--radius-xl); color: white; box-shadow: var(--shadow-md); transition: all 0.2s ease; position: relative; overflow: hidden; border: none; backdrop-filter: blur(10px); min-width: 0; }
    .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%); pointer-events: none; }
    .card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
    .card h3 { margin: 0 0 0.75rem 0; font-size: 0.9rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
    .card .big { font-size: 2.25rem; font-weight: 700; line-height: 1.2; margin-bottom: 0.5rem; word-break: break-word; }
    .card .small { font-size: 0.85rem; opacity: 0.9; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }

    .card.primary { background: linear-gradient(145deg, var(--primary), var(--primary-dark)); }
    .card.secondary { background: linear-gradient(145deg, var(--secondary), var(--secondary-dark)); }
    .card.success { background: linear-gradient(145deg, var(--success), #047857); }
    .card.warning { background: linear-gradient(145deg, var(--accent), var(--accent-dark)); }
    .card.info { background: linear-gradient(145deg, var(--info), #1e40af); }
    .card.light { background: white; color: var(--gray-700); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); }
    .card.light .big { color: var(--primary-dark); }
    .card.light h3 { color: var(--gray-500); }

    .banner { background: #fffbeb; border-left: 4px solid var(--warning); padding: 1.5rem 2rem; border-radius: var(--radius-lg); margin-bottom: 2rem; display: flex; gap: 1.5rem; align-items: flex-start; box-shadow: var(--shadow-sm); border: 1px solid #fef3c7; flex-wrap: wrap; }
    .banner .title { font-weight: 600; color: var(--warning); font-size: 1.1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
    .banner .title i { font-size: 1.25rem; }
    .banner .small { color: var(--gray-600); line-height: 1.6; }
    .banner ol { margin: 0.75rem 0 0 1.5rem; color: var(--gray-700); word-break: break-word; }
    .banner ol li { margin-bottom: 0.5rem; }
    .banner .link-btn { background: var(--primary) !important; color: white !important; border: none; padding: 0.625rem 1.25rem; border-radius: var(--radius-md); font-size: 0.95rem; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease; }
    .banner .link-btn:hover { background: var(--primary-light) !important; transform: translateY(-2px); }

    .section { margin-bottom: 2rem; background: white; padding: 1.75rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); transition: all 0.2s ease; overflow-x: auto; }
    .section:hover { box-shadow: var(--shadow-md); border-color: var(--gray-300); }
    .section h4 { margin: 0 0 1.5rem 0; color: var(--gray-800); font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; letter-spacing: -0.01em; flex-wrap: wrap; }
    .section h4 i { color: var(--primary); font-size: 1.5rem; }
    .section h4::after { content: ''; flex: 1; height: 2px; background: linear-gradient(90deg, var(--primary-light) 0%, var(--gray-200) 100%); margin-left: 1rem; min-width: 50px; }

    .table-responsive { overflow-x: auto; border-radius: var(--radius-lg); -webkit-overflow-scrolling: touch; width: 100%; }
    .table { width: 100%; border-collapse: collapse; font-size: 0.95rem; min-width: 600px; }
    .table th { padding: 1rem 1rem; background: var(--gray-50); color: var(--gray-600); font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid var(--gray-300); text-align: left; white-space: nowrap; }
    .table td { padding: 1rem; border-bottom: 1px solid var(--gray-200); color: var(--gray-700); vertical-align: middle; word-break: break-word; }
    .table tbody tr:hover { background-color: var(--gray-50); }
    .table tbody tr:last-child td { border-bottom: none; }
    .table code { background: var(--gray-100); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-family: monospace; font-size: 0.9rem; color: var(--primary-dark); word-break: break-all; }

    .status-indicator { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 0.5rem; }
    .status-instock { background: var(--success); box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.2); }

    .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 500; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
    .badge-primary { background: var(--primary); color: white; }
    .badge-success { background: var(--success); color: white; }
    .badge-warning { background: var(--warning); color: white; }
    .badge-info { background: var(--info); color: white; }

    .trend-up { color: var(--success); display: inline-flex; align-items: center; gap: 0.25rem; }
    .trend-down { color: var(--danger); display: inline-flex; align-items: center; gap: 0.25rem; }

    .link-btn { padding: 0.625rem 1.25rem; background: var(--primary); color: white !important; border-radius: var(--radius-md); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; font-size: 0.95rem; border: none; cursor: pointer; transition: all 0.2s ease; box-shadow: var(--shadow-sm); }
    .link-btn:hover { background: var(--primary-light); transform: translateY(-2px); box-shadow: var(--shadow-md); }
    .btn-outline { background: transparent; border: 2px solid var(--primary); color: var(--primary) !important; }
    .btn-outline:hover { background: var(--primary); color: white !important; }

    footer { text-align: center; padding: 2rem 0 0.5rem; margin-top: 2rem; font-size: 0.9rem; color: var(--gray-500); border-top: 1px solid var(--gray-200); }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .card, .section, .banner, .header-row { animation: fadeIn 0.4s ease-out forwards; }

    .hour-chart { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem; }
    .hour-bar { flex: 1; min-width: 60px; text-align: center; }
    .bar { background: var(--primary); height: 100px; width: 100%; border-radius: var(--radius-md); transition: height 0.3s ease; position: relative; min-height: 20px; }
    .bar-value { position: absolute; top: -25px; left: 50%; transform: translateX(-50%); font-size: 0.75rem; font-weight: 600; color: var(--gray-600); white-space: nowrap; }
    .hour-label { margin-top: 0.5rem; font-size: 0.75rem; color: var(--gray-500); }

    @media (max-width: 1200px) {
        .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        .header-row { flex-direction: column !important; align-items: flex-start !important; gap: 1rem !important; padding: 1.25rem !important; position: relative; padding-right: 70px; }
        .header-row .logo { position: absolute; top: 1.25rem; right: 1.25rem; }
        .page-title { font-size: 1.75rem !important; width: calc(100% - 60px); }
        .welcome-text { width: calc(100% - 60px); font-size: 0.85rem !important; }
        .card-row { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important; gap: 1rem !important; }
        .section { padding: 1.5rem !important; }
        .banner { flex-direction: column !important; padding: 1.25rem !important; gap: 1rem !important; }
        .banner .link-btn { width: 100%; justify-content: center; }
        .hour-chart { flex-direction: column; }
        .hour-bar { display: flex; align-items: center; gap: 1rem; }
        .bar { height: 30px; width: auto; flex: 1; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
        .page-title { font-size: 1.5rem !important; }
        .logo img { height: 40px !important; }
        .card .big { font-size: 1.75rem !important; }
        .table td, .table th { padding: 0.75rem !important; font-size: 0.9rem !important; }
        .table { min-width: 550px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
        .header-row { padding: 1rem !important; }
        .page-title { font-size: 1.25rem !important; }
        .card .big { font-size: 1.5rem !important; }
        .card .small { font-size: 0.8rem !important; }
        .card-row { grid-template-columns: 1fr !important; gap: 0.75rem !important; }
        .table { min-width: 500px !important; }
        .table td, .table th { padding: 0.625rem !important; font-size: 0.85rem !important; }
        .banner .title { font-size: 1rem !important; }
        .banner .small { font-size: 0.85rem !important; }
        footer { font-size: 0.75rem !important; padding: 1.5rem 0 0.5rem !important; }
        .badge { font-size: 0.75rem !important; padding: 0.2rem 0.5rem !important; }
        .header-row { padding-right: 60px !important; }
        .page-title { font-size: 1.25rem !important; width: calc(100% - 50px) !important; }
        .welcome-text { width: calc(100% - 50px) !important; font-size: 0.75rem !important; }
        .header-row .logo img { height: 35px !important; }
    }

    @media (min-width: 1201px) and (max-width: 1400px) {
        .card-row { grid-template-columns: repeat(2, 1fr) !important; }
    }

    .text-success { color: var(--success); }
    .text-warning { color: var(--warning); }
    .text-danger { color: var(--danger); }
    .text-primary { color: var(--primary); }
    .text-secondary { color: var(--secondary); }
    .text-muted { color: var(--gray-400); }
    </style>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div class="main-content">
    <div class="header-row">
        <div>
            <div class="page-title">Cashier Dashboard</div>
            <div class="welcome-text">
                <i class="fas fa-hand-wave" style="color: var(--accent); margin-right: 0.5rem;"></i>
                <?= $greeting ?>, <?= htmlspecialchars(explode(' ', $user_name)[0]) ?> • <?= date('l, F j, Y') ?>
            </div>
            <div class="branch-badge">
                <i class="fas fa-store"></i> <?= htmlspecialchars($user_branch) ?> Branch
            </div>
        </div>
        <div class="logo">
            <img src="/inventory_system/assets/MC-LOGO.png" alt="Mombasa Computers" onerror="this.style.display='none'">
        </div>
        <div>
            <a href="/inventory_system/dashboard/cashierdashboard.php" class="link-btn">
                <i class="fas fa-sync-alt"></i> Refresh
            </a>
        </div>
    </div>

    <!-- Low Stock Warning Banner -->
    <?php if (!empty($lowStockItems)): ?>
        <div class="banner" role="alert">
            <div style="flex:1">
                <div class="title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Low Stock Alert - Inform Inventory Manager
                </div>
                <div class="small">
                    The following items are running low (below 5 units). Please inform the inventory manager:
                    <ol>
                        <?php foreach($lowStockItems as $item): ?>
                            <li>
                                <?php
                                $label = '';
                                if ($item['source'] === 'charger') {
                                    $label = ($item['type'] ?? 'Charger') . ($item['storage'] ? " {$item['storage']}W" : '');
                                } elseif ($item['source'] === 'hdd') {
                                    $label = ($item['type'] ?? 'HDD') . ' ' . ($item['storage'] ?? '');
                                } elseif ($item['source'] === 'graphic_card') {
                                    $label = ($item['type'] ?? 'Graphics') . ' ' . ($item['storage'] ?? '') . 'GB';
                                } elseif ($item['source'] === 'accessory') {
                                    $label = $item['type'] ?? 'Accessory';
                                } else {
                                    $label = ($item['category'] ?? '') . ' ' . ($item['type'] ?? '-') . (!empty($item['storage']) ? ' ' . $item['storage'] . 'GB' : '');
                                }
                                ?>
                                <strong><?= htmlspecialchars($label) ?></strong>
                                <span class="badge badge-warning"><?= (int)$item['quantity'] ?> left</span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- First Row - Today's Metrics -->
    <div class="card-row">
        <div class="card success">
            <h3><i class="fas fa-shopping-cart"></i> Today's Sales</h3>
            <div class="big"><?= number_format($todaysCount) ?></div>
            <div class="small">
                <span class="trend-up">
                    <i class="fas fa-chart-line"></i> Items sold today
                </span>
            </div>
        </div>

        <div class="card primary">
            <h3><i class="fas fa-money-bill-wave"></i> Today's Revenue</h3>
            <div class="big">KES <?= number_format($todaysRevenue, 0) ?></div>
            <div class="small">Avg: KES <?= number_format($todaysAvg, 0) ?> per item</div>
        </div>

        <div class="card info">
            <h3><i class="fas fa-calendar-week"></i> This Week</h3>
            <div class="big">KES <?= number_format($weeklyRevenue, 0) ?></div>
            <div class="small"><?= number_format($weeklyCount) ?> items sold</div>
        </div>

        <div class="card warning">
            <h3><i class="fas fa-calendar-alt"></i> This Month</h3>
            <div class="big">KES <?= number_format($monthlyRevenue, 0) ?></div>
            <div class="small"><?= number_format($monthlyCount) ?> items sold</div>
        </div>
    </div>

    <!-- Second Row - Inventory Awareness -->
    <div class="card-row">
        <div class="card light">
            <h3><i class="fas fa-boxes"></i> Available Stock</h3>
            <div class="big"><?= number_format($totalInStock) ?></div>
            <div class="small">
                <span class="status-indicator status-instock"></span>
                Total items ready for sale
                <span style="margin-left: 0.5rem; font-size:0.8rem; color:var(--gray-400);">
                    (<?php foreach($inventoryCounts as $table => $count): ?>
                        <?= ucfirst($table) ?>: <?= $count ?> &nbsp;
                    <?php endforeach; ?>)
                </span>
            </div>
        </div>
    </div>

    <!-- Top Selling Products Today -->
    <?php if (!empty($topProductsToday)): ?>
    <div class="section">
        <h4>
            <i class="fas fa-fire" style="color: var(--accent);"></i>
            Top Selling Items Today
        </h4>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($topProductsToday as $item): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                            <td><span class="badge"><?= htmlspecialchars($item['category']) ?></span></td>
                            <td><span class="badge badge-primary"><?= (int)$item['quantity'] ?></span></td>
                            <td><span class="text-success">KES <?= number_format($item['revenue'], 0) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sales by Hour Chart -->
    <?php if (!empty($salesByHour)): ?>
    <div class="section">
        <h4>
            <i class="fas fa-chart-line" style="color: var(--info);"></i>
            Sales by Hour (Today)
        </h4>
        <div class="hour-chart">
            <?php 
            $maxCount = max(array_column($salesByHour, 'count'));
            foreach($salesByHour as $hourData): 
                $heightPercent = $maxCount > 0 ? ($hourData['count'] / $maxCount) * 100 : 0;
            ?>
                <div class="hour-bar">
                    <div class="bar" style="height: <?= max(20, $heightPercent) ?>px; background: linear-gradient(180deg, var(--primary-light) 0%, var(--primary) 100%);">
                        <div class="bar-value"><?= (int)$hourData['count'] ?></div>
                    </div>
                    <div class="hour-label"><?= date('g A', strtotime($hourData['hour'] . ':00')) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Sales Transactions -->
    <div class="section">
        <h4>
            <i class="fas fa-clock"></i>
            Recent Sales Transactions
        </h4>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Sold By</th>
                        <th>Price</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($recentSales)): ?>
                        <?php $counter = 1; foreach($recentSales as $sale): ?>
                            <tr>
                                <td><span class="badge"><?= $counter++ ?></span></td>
                                <td><strong><?= htmlspecialchars($sale['item_name'] ?? '-') ?></strong></td>
                                <td><span class="badge"><?= htmlspecialchars($sale['category'] ?? '-') ?></span></td>
                                <td><i class="fas fa-user" style="margin-right: 0.25rem; color: var(--gray-400);"></i><?= htmlspecialchars($sale['sold_by_name'] ?? '-') ?></td>
                                <td><strong class="text-success">KES <?= number_format($sale['price'] ?? 0, 0) ?></strong></td>
                                <td><?= htmlspecialchars(date('M j, Y H:i', strtotime($sale['sold_at'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-muted" style="text-align:center; padding: 2.5rem;">No recent sales found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Action Buttons -->
    <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem;">
        <a href="/inventory_system/sales/process_sale.php" class="link-btn">
            <i class="fas fa-cash-register"></i> Process New Sale
        </a>
        <a href="/inventory_system/sales/search_customer.php" class="link-btn btn-outline">
            <i class="fas fa-search"></i> Find Customer
        </a>
        <a href="/inventory_system/reports/daily_report.php" class="link-btn btn-outline">
            <i class="fas fa-print"></i> Print Daily Report
        </a>
    </div>

    <footer>
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers. All rights reserved. 
        <span style="margin: 0 0.5rem;">•</span> 
        <span>Cashier Terminal v2.0</span>
    </footer>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function adjustDashboardForMobile() {
        const mainContent = document.querySelector('.main-content');
        const sidebar = document.getElementById('sidebar');
        if (window.innerWidth <= 1200) {
            if (mainContent) {
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
                mainContent.style.paddingTop = '5rem';
                mainContent.style.overflowX = 'hidden';
            }
            if (sidebar && !sidebar.classList.contains('active')) {
                document.body.style.overflow = 'auto';
            }
        } else {
            if (mainContent && sidebar) {
                mainContent.style.marginLeft = '260px';
                mainContent.style.width = 'calc(100% - 260px)';
                mainContent.style.paddingTop = '';
                mainContent.style.overflowX = '';
            }
        }
    }
    adjustDashboardForMobile();
    window.addEventListener('resize', adjustDashboardForMobile);
    window.addEventListener('orientationchange', function() {
        setTimeout(adjustDashboardForMobile, 100);
    });
    window.addEventListener('sidebarToggled', adjustDashboardForMobile);
    const originalToggle = window.toggleSidebar;
    if (originalToggle) {
        window.toggleSidebar = function() {
            originalToggle();
            setTimeout(() => {
                window.dispatchEvent(new Event('sidebarToggled'));
            }, 300);
        };
    }
    // Check table scroll
    const tables = document.querySelectorAll('.table-responsive');
    tables.forEach(table => {
        if (table.scrollWidth > table.clientWidth) {
            table.style.overflowX = 'auto';
        }
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>
</body>
</html>