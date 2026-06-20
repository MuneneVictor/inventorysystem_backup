<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

// STRICT ROLE CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sales') {
    die("ACCESS DENIED: You do not have permission to access this page.");
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

function safeQuery($conn, $sql, $params = []) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// FETCH ALL SALES DATA FOR THIS SALESPERSON (separate queries)
// ============================================================
function getMySalesData($conn, $user_id) {
    $allSales = [];

    // Devices
    $stmt = safeQuery($conn, "
        SELECT 
            model_name AS item_name,
            'Device' AS category,
            1 AS quantity,
            selling_price AS price,
            sold_at,
            branch,
            sold_by
        FROM devices
        WHERE status = 'Sold' AND sold_by = :uid
    ", ['uid' => $user_id]);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Monitors
    $stmt = safeQuery($conn, "
        SELECT 
            model_name AS item_name,
            'Monitor' AS category,
            1 AS quantity,
            selling_price AS price,
            sold_at,
            branch,
            sold_by
        FROM monitors
        WHERE status = 'Sold' AND sold_by = :uid
    ", ['uid' => $user_id]);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Printers
    $stmt = safeQuery($conn, "
        SELECT 
            model_name AS item_name,
            'Printer' AS category,
            1 AS quantity,
            selling_price AS price,
            date_sold AS sold_at,
            branch,
            sold_by
        FROM printers
        WHERE status = 'Sold' AND sold_by = :uid
    ", ['uid' => $user_id]);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Smartboards
    $stmt = safeQuery($conn, "
        SELECT 
            model AS item_name,
            'Smartboard' AS category,
            1 AS quantity,
            selling_price AS price,
            sold_at,
            branch,
            sold_by
        FROM smartboards
        WHERE status = 'sold' AND sold_by = :uid
    ", ['uid' => $user_id]);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Sold accessories
    $stmt = safeQuery($conn, "
        SELECT 
            accessory_name AS item_name,
            'Accessory' AS category,
            quantity,
            selling_price AS price,
            date_sold AS sold_at,
            branch,
            sold_by
        FROM sold_accessories
        WHERE sold_by = :uid
    ", ['uid' => $user_id]);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Sold chargers
    $stmt = safeQuery($conn, "
        SELECT 
            charger_type AS item_name,
            'Charger' AS category,
            quantity,
            selling_price AS price,
            date_sold AS sold_at,
            branch,
            sold_by
        FROM sold_chargers
        WHERE sold_by = :uid
    ", ['uid' => $user_id]);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Phones
    $stmt = safeQuery($conn, "
        SELECT 
            CONCAT(COALESCE(brand,''), ' ', COALESCE(model,'')) AS item_name,
            'Phone' AS category,
            1 AS quantity,
            selling_price AS price,
            date_sold AS sold_at,
            branch,
            sold_by
        FROM phones
        WHERE status = 'sold' AND sold_by = :uid
    ", ['uid' => $user_id]);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // UPS
    $stmt = safeQuery($conn, "
        SELECT 
            model AS item_name,
            'UPS' AS category,
            1 AS quantity,
            selling_price AS price,
            date_sold AS sold_at,
            branch,
            sold_by
        FROM ups
        WHERE status = 'sold' AND sold_by = :uid
    ", ['uid' => $user_id]);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Sold RAM/SSD
    $stmt = safeQuery($conn, "
        SELECT 
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage,''), 'GB') AS item_name,
            'RAM/SSD' AS category,
            quantity,
            total_price AS price,
            date_sold AS sold_at,
            branch,
            sold_by
        FROM sold_rams_ssds
        WHERE sold_by = :uid
    ", ['uid' => $user_id]);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Sold HDDs
    $stmt = safeQuery($conn, "
        SELECT 
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage,'')) AS item_name,
            'HDD' AS category,
            quantity,
            total_price AS price,
            date_sold AS sold_at,
            branch,
            sold_by
        FROM sold_hdds
        WHERE sold_by = :uid
    ", ['uid' => $user_id]);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Sold Graphics Cards
    $stmt = safeQuery($conn, "
        SELECT 
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage_capacity,''), 'GB') AS item_name,
            'Graphics Card' AS category,
            quantity,
            total_price AS price,
            date_sold AS sold_at,
            branch,
            sold_by
        FROM sold_graphics_cards
        WHERE sold_by = :uid
    ", ['uid' => $user_id]);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    return $allSales;
}

// ============================================================
// FETCH ALL SALES DATA
// ============================================================
$myAllSales = getMySalesData($conn, $user_id);

$myTotalCount  = 0;
$myTotalRevenue = 0;
$myTodayCount  = 0;
$myTodayRevenue = 0;
$myMonthCount  = 0;
$myMonthRevenue = 0;
$today = date('Y-m-d');
$month = date('Y-m');

foreach ($myAllSales as $s) {
    $qty = (int)($s['quantity'] ?? 0);
    $price = (float)($s['price'] ?? 0);
    $soldAt = strtotime($s['sold_at'] ?? '');
    if (!$soldAt) continue;

    $myTotalCount += $qty;
    $myTotalRevenue += $price;

    if (date('Y-m-d', $soldAt) === $today) {
        $myTodayCount += $qty;
        $myTodayRevenue += $price;
    }
    if (date('Y-m', $soldAt) === $month) {
        $myMonthCount += $qty;
        $myMonthRevenue += $price;
    }
}

// ============================================================
// RANK – based on total revenue across all sales staff
// ============================================================
$rankQuery = "
    SELECT 
        u.id,
        u.full_name,
        COALESCE(SUM(price), 0) AS total_revenue
    FROM users u
    LEFT JOIN (
        SELECT sold_by, selling_price AS price FROM devices WHERE status = 'Sold' UNION ALL
        SELECT sold_by, selling_price FROM monitors WHERE status = 'Sold' UNION ALL
        SELECT sold_by, selling_price FROM printers WHERE status = 'Sold' UNION ALL
        SELECT sold_by, selling_price FROM smartboards WHERE status = 'sold' UNION ALL
        SELECT sold_by, selling_price FROM sold_accessories UNION ALL
        SELECT sold_by, selling_price FROM sold_chargers UNION ALL
        SELECT sold_by, selling_price FROM phones WHERE status = 'sold' UNION ALL
        SELECT sold_by, selling_price FROM ups WHERE status = 'sold' UNION ALL
        SELECT sold_by, total_price AS price FROM sold_rams_ssds UNION ALL
        SELECT sold_by, total_price AS price FROM sold_hdds UNION ALL
        SELECT sold_by, total_price AS price FROM sold_graphics_cards
    ) AS s ON u.id = s.sold_by
    WHERE u.role = 'sales'
    GROUP BY u.id
    ORDER BY total_revenue DESC
";
$rankStmt = safeQuery($conn, $rankQuery);
$allSalesPeople = $rankStmt ? $rankStmt->fetchAll(PDO::FETCH_ASSOC) : [];
$myRank = 0;
$totalStaff = count($allSalesPeople);
foreach ($allSalesPeople as $idx => $person) {
    if ($person['id'] == $user_id) {
        $myRank = $idx + 1;
        break;
    }
}

// ============================================================
// TOP ITEMS (current month, by revenue) – from $myAllSales
// ============================================================
$monthItems = [];
foreach ($myAllSales as $s) {
    $soldAt = strtotime($s['sold_at'] ?? '');
    if (!$soldAt) continue;
    if (date('Y-m', $soldAt) !== $month) continue;
    $key = $s['item_name'] . '|' . $s['category'];
    if (!isset($monthItems[$key])) {
        $monthItems[$key] = [
            'item_name' => $s['item_name'],
            'category' => $s['category'],
            'quantity' => 0,
            'revenue' => 0
        ];
    }
    $monthItems[$key]['quantity'] += (int)($s['quantity'] ?? 0);
    $monthItems[$key]['revenue'] += (float)($s['price'] ?? 0);
}
usort($monthItems, function($a, $b) { return $b['revenue'] - $a['revenue']; });
$topItems = array_slice($monthItems, 0, 5);

// ============================================================
// TOP CATEGORIES (current month, by revenue)
// ============================================================
$monthCats = [];
foreach ($myAllSales as $s) {
    $soldAt = strtotime($s['sold_at'] ?? '');
    if (!$soldAt) continue;
    if (date('Y-m', $soldAt) !== $month) continue;
    $cat = $s['category'];
    if (!isset($monthCats[$cat])) {
        $monthCats[$cat] = [
            'category' => $cat,
            'quantity' => 0,
            'revenue' => 0
        ];
    }
    $monthCats[$cat]['quantity'] += (int)($s['quantity'] ?? 0);
    $monthCats[$cat]['revenue'] += (float)($s['price'] ?? 0);
}
usort($monthCats, function($a, $b) { return $b['revenue'] - $a['revenue']; });
$topCategories = array_slice($monthCats, 0, 5);

// ============================================================
// RECENT SALES (last 6) – from $myAllSales sorted by date
// ============================================================
usort($myAllSales, function($a, $b) {
    return strtotime($b['sold_at'] ?? 0) - strtotime($a['sold_at'] ?? 0);
});
$recentSales = array_slice($myAllSales, 0, 6);

// ============================================================
// RECENT RAM/SSD GIVEN TO THIS SALESPERSON
// ============================================================
$recentRamGiven = [];
$stmt = safeQuery($conn, "
    SELECT 
        l.*,
        r.type,
        r.category,
        r.storage,
        u.full_name AS given_by_name 
    FROM rams_ssds_logs l 
    LEFT JOIN rams_ssds r ON l.ram_ssd_id = r.id 
    LEFT JOIN users u ON l.given_by = u.id 
    WHERE l.given_to = :uid 
    ORDER BY l.date_given DESC 
    LIMIT 6
", ['uid' => $user_id]);
$recentRamGiven = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// ============================================================
// RECENT CHARGERS GIVEN TO THIS SALESPERSON
// ============================================================
$recentChargersGivenToMe = [];
$stmt = safeQuery($conn, "
    SELECT 
        cl.*,
        c.charger_type,
        c.watts,
        c.charger_condition,
        u.full_name AS given_by_name
    FROM charger_logs cl 
    LEFT JOIN chargers c ON cl.charger_id = c.id 
    LEFT JOIN users u ON cl.given_by = u.id 
    WHERE cl.given_to = :uid
    ORDER BY cl.date_given DESC 
    LIMIT 6
", ['uid' => $user_id]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $recentChargersGivenToMe[] = [
            'charger_label' => trim(($row['charger_type'] ?? 'Charger') . ($row['watts'] ? " {$row['watts']}W" : '')),
            'quantity_given' => (int)($row['quantity'] ?? 0),
            'given_by_name'  => $row['given_by_name'] ?? '-',
            'branch'         => $row['branch'] ?? null,
            'date_given'     => $row['date_given'] ?? null,
            'charger_condition' => $row['charger_condition'] ?? '-',
        ];
    }
}

// ============================================================
// PROGRESS CALCULATIONS
// ============================================================
$monthlyTarget = 4000000;   // KES 4M
$dailyTarget   = 100000;    // KES 100K
$monthProgress = min(100, ($myMonthRevenue / $monthlyTarget) * 100);
$dayProgress   = min(100, ($myTodayRevenue / $dailyTarget) * 100);

// ============================================================
// GREETING
// ============================================================
date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';

// For JavaScript toggles
$myMonthRevenueJS = $myMonthRevenue;
$myTodayRevenueJS = $myTodayRevenue;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=yes">
    <title>Sales Dashboard | Mombasa Computers</title>
    <style>
    :root {
        --primary: #1a4b2a;
        --primary-light: #2a6b3a;
        --primary-dark: #0f3a1e;
        --secondary: #1a4f6e;
        --secondary-dark: #0f3a4e;
        --accent: #f59e0b;
        --accent-dark: #d97706;
        --success: #059669;
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
        --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        --radius-sm: 0.375rem;
        --radius-md: 0.5rem;
        --radius-lg: 0.75rem;
        --radius-xl: 1rem;
        --font-sans: 'Inter', system-ui, -apple-system, sans-serif;
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
        position: relative;
    }

    .header-row { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        gap: 1.5rem; 
        margin-bottom: 2rem; 
        background: white;
        padding: 1.25rem 2rem;
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
        flex-wrap: wrap;
    }

    .page-title { 
        font-size: 2rem; 
        color: var(--primary-dark); 
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.2;
    }

    .welcome-text {
        font-size: 0.95rem;
        color: var(--gray-500);
        margin-top: 0.25rem;
    }

    .logo img {
        height: 48px;
        width: auto;
        max-width: 100%;
    }

    .card-row { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.25rem; 
        margin-bottom: 2rem; 
    }

    .card { 
        padding: 1.25rem; 
        border-radius: var(--radius-xl); 
        color: white; 
        box-shadow: var(--shadow-md);
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
        border: none;
        min-width: 0;
    }

    .card:hover { 
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .card h3 { 
        margin: 0 0 0.5rem 0; 
        font-size: 0.85rem; 
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        opacity: 0.9;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .card .big { 
        font-size: 2rem; 
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 0.25rem;
        word-break: break-word;
    }

    .card .small { 
        font-size: 0.8rem; 
        opacity: 0.9;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .toggle-btn {
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.3);
        padding: 0.15rem 0.6rem;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-size: 0.7rem;
        color: white;
        transition: all 0.2s;
    }
    .toggle-btn:hover { background: rgba(255,255,255,0.3); }

    .card.primary { background: linear-gradient(145deg, var(--primary), var(--primary-dark)); }
    .card.secondary { background: linear-gradient(145deg, var(--secondary), var(--secondary-dark)); }
    .card.success { background: linear-gradient(145deg, var(--success), #047857); }
    .card.warning { background: linear-gradient(145deg, var(--accent), var(--accent-dark)); }
    .card.info { background: linear-gradient(145deg, var(--info), #1e40af); }
    .card.light { background: white; color: var(--gray-700); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); }
    .card.light .big { color: var(--primary-dark); }
    .card.light h3 { color: var(--gray-500); }

    .progress-card {
        background: white;
        padding: 1.25rem;
        border-radius: var(--radius-xl);
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow-sm);
        transition: all 0.2s ease;
    }
    .progress-card:hover { box-shadow: var(--shadow-md); border-color: var(--gray-300); }
    .progress-card .label { font-size: 0.85rem; font-weight: 500; color: var(--gray-600); margin-bottom: 0.25rem; }
    .progress-card .value { font-size: 1.25rem; font-weight: 700; color: var(--gray-800); margin-bottom: 0.5rem; }
    .progress-track { width: 100%; height: 6px; background: var(--gray-200); border-radius: 999px; overflow: hidden; }
    .progress-fill { height: 100%; border-radius: 999px; transition: width 0.6s ease; }
    .progress-fill.green { background: var(--success); }
    .progress-fill.blue { background: var(--info); }

    .section { 
        margin-bottom: 2rem; 
        background: white; 
        padding: 1.5rem; 
        border-radius: var(--radius-xl); 
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
        overflow-x: auto;
    }
    .section h4 { 
        margin: 0 0 1.25rem 0; 
        color: var(--gray-800); 
        font-size: 1.15rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .section h4 i { color: var(--primary); font-size: 1.4rem; }
    .section h4::after {
        content: '';
        flex: 1;
        height: 2px;
        background: linear-gradient(90deg, var(--primary-light) 0%, var(--gray-200) 100%);
        margin-left: 0.75rem;
        min-width: 40px;
    }

    .table-responsive { overflow-x: auto; width: 100%; }
    .table { width: 100%; border-collapse: collapse; font-size: 0.9rem; min-width: 550px; }
    .table th { padding: 0.75rem; background: var(--gray-50); color: var(--gray-600); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; border-bottom: 2px solid var(--gray-300); text-align: left; }
    .table td { padding: 0.75rem; border-bottom: 1px solid var(--gray-200); color: var(--gray-700); vertical-align: middle; }
    .table tbody tr:hover { background: var(--gray-50); }
    .table code { background: var(--gray-100); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-family: monospace; font-size: 0.8rem; }

    .badge {
        display: inline-block;
        padding: 0.2rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.8rem;
        font-weight: 500;
        background: var(--gray-100);
        color: var(--gray-700);
        white-space: nowrap;
    }
    .badge-primary { background: var(--primary); color: white; }
    .badge-secondary { background: var(--secondary); color: white; }
    .text-success { color: var(--success); }
    .text-muted { color: var(--gray-400); }

    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    .category-card {
        background: linear-gradient(135deg, var(--gray-50) 0%, white 100%);
        border-radius: var(--radius-lg);
        padding: 1rem;
        text-align: center;
        border: 1px solid var(--gray-200);
        transition: all 0.2s ease;
    }
    .category-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--primary); }
    .category-card .category-count { font-size: 1.8rem; font-weight: 700; color: var(--primary); margin-bottom: 0.25rem; }
    .category-card .category-name { font-size: 0.85rem; color: var(--gray-600); font-weight: 500; }
    .category-card .category-revenue { font-size: 0.7rem; color: var(--gray-400); margin-top: 0.25rem; }

    .link-btn { 
        padding: 0.5rem 1rem; 
        background: var(--primary); 
        color: white !important; 
        border-radius: var(--radius-md); 
        text-decoration: none; 
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
    }
    .link-btn:hover { background: var(--primary-light); transform: translateY(-2px); box-shadow: var(--shadow-md); }

    .two-col-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }

    footer {
        text-align: center;
        padding: 1.5rem 0 0.5rem;
        margin-top: 1.5rem;
        font-size: 0.85rem;
        color: var(--gray-500);
        border-top: 1px solid var(--gray-200);
    }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .card, .section, .header-row { animation: fadeIn 0.4s ease-out forwards; }

    @media (max-width: 1200px) {
        .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        .header-row { flex-direction: column !important; align-items: flex-start !important; gap: 1rem !important; padding: 1.25rem !important; position: relative; padding-right: 70px; }
        .header-row .logo { position: absolute; top: 1.25rem; right: 1.25rem; }
        .page-title { font-size: 1.75rem !important; width: calc(100% - 60px); }
        .welcome-text { width: calc(100% - 60px); font-size: 0.85rem !important; }
        .card-row { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) !important; gap: 1rem !important; }
        .two-col-grid { grid-template-columns: 1fr; gap: 1rem; }
        .section { padding: 1.25rem !important; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
        .page-title { font-size: 1.5rem !important; }
        .logo img { height: 40px !important; }
        .card .big { font-size: 1.75rem !important; }
        .table td, .table th { padding: 0.6rem !important; }
        .table { min-width: 500px; }
        .two-col-grid { grid-template-columns: 1fr; gap: 1rem; }
        .categories-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
        .page-title { font-size: 1.25rem !important; }
        .card-row { grid-template-columns: 1fr !important; gap: 0.75rem !important; }
        .table { min-width: 450px !important; }
        .badge { font-size: 0.7rem !important; padding: 0.15rem 0.4rem !important; }
        .header-row { padding-right: 60px !important; }
        .header-row .logo img { height: 35px !important; }
        .two-col-grid { grid-template-columns: 1fr; gap: 0.75rem; }
        .categories-grid { grid-template-columns: 1fr; }
    }
    </style>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div class="main-content">
    <div class="header-row">
        <div>
            <div class="page-title">Sales Dashboard</div>
            <div class="welcome-text">
                <i class="fas fa-hand-wave" style="color: var(--accent); margin-right: 0.5rem;"></i>
                <?= $greeting ?>, <?= htmlspecialchars(explode(' ', $user_name)[0]) ?> • <?= date('l, F j, Y') ?>
            </div>
        </div>
        <div class="logo">
            <img src="/inventory_system/assets/MC-LOGO.png" alt="Mombasa Computers" onerror="this.style.display='none'">
        </div>
        <div>
            <a href="/inventory_system/dashboard/salesdashboard.php" class="link-btn">
                <i class="fas fa-sync-alt"></i> Refresh
            </a>
        </div>
    </div>

    <!-- Row 1: 4 small cards -->
    <div class="card-row">
        <div class="card secondary">
            <h3><i class="fas fa-calendar-alt"></i> Monthly Revenue</h3>
            <div class="big" id="monthRevenueValue">••••••</div>
            <div class="small">
                <button class="toggle-btn" onclick="toggleMonthRevenue()"><i class="fas fa-eye"></i> Show</button>
                <span style="margin-left: 0.5rem;"><?= number_format($myMonthCount) ?> sales</span>
            </div>
        </div>
        <div class="card success">
            <h3><i class="fas fa-calendar-check"></i> Today's Revenue</h3>
            <div class="big" id="todayRevenueValue">••••••</div>
            <div class="small">
                <button class="toggle-btn" onclick="toggleTodayRevenue()"><i class="fas fa-eye"></i> Show</button>
                <span style="margin-left: 0.5rem;"><?= number_format($myTodayCount) ?> items</span>
            </div>
        </div>
        <div class="card warning">
            <h3><i class="fas fa-medal"></i> Your Rank</h3>
            <div class="big">#<?= number_format($myRank) ?></div>
            <div class="small">of <?= number_format($totalStaff) ?> sales staff</div>
        </div>
        <div class="card info">
            <h3><i class="fas fa-chart-line"></i> Monthly Sales</h3>
            <div class="big"><?= number_format($myMonthCount) ?></div>
            <div class="small">items sold this month</div>
        </div>
    </div>

    <!-- Progress Bars for targets -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 2rem;">
        <div class="progress-card">
            <div class="label"><i class="fas fa-bullseye" style="color: var(--info);"></i> Monthly Target (KES 4M)</div>
            <div class="value">KES <?= number_format($myMonthRevenue, 0) ?> / KES 4,000,000</div>
            <div class="progress-track">
                <div class="progress-fill blue" style="width: <?= min(100, $monthProgress) ?>%;"></div>
            </div>
            <div style="margin-top: 0.4rem; font-size: 0.8rem; color: var(--gray-500);">
                <?= number_format($monthProgress, 1) ?>% complete
            </div>
        </div>
        <div class="progress-card">
            <div class="label"><i class="fas fa-flag-checkered" style="color: var(--success);"></i> Today's Target (KES 100K)</div>
            <div class="value">KES <?= number_format($myTodayRevenue, 0) ?> / KES 100,000</div>
            <div class="progress-track">
                <div class="progress-fill green" style="width: <?= min(100, $dayProgress) ?>%;"></div>
            </div>
            <div style="margin-top: 0.4rem; font-size: 0.8rem; color: var(--gray-500);">
                <?= number_format($dayProgress, 1) ?>% complete
            </div>
        </div>
    </div>

    <!-- Top Categories as Cards -->
    <div class="section">
        <div class="flex-between" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
            <h4 style="margin:0;"><i class="fas fa-layer-group"></i> Your Top Categories (This Month)</h4>
            <span style="font-size: 0.8rem; color: var(--gray-500);">by revenue</span>
        </div>
        <div class="categories-grid">
            <?php if (!empty($topCategories)): ?>
                <?php foreach ($topCategories as $cat): ?>
                    <div class="category-card">
                        <div class="category-count"><?= number_format($cat['quantity']) ?></div>
                        <div class="category-name"><?= htmlspecialchars($cat['category']) ?></div>
                        <div class="category-revenue">KES <?= number_format($cat['revenue'], 0) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-muted" style="grid-column: 1 / -1; text-align: center; padding: 1.5rem;">No sales this month</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Selling Items (table) -->
    <div class="section">
        <h4><i class="fas fa-fire" style="color: var(--accent);"></i> Your Top Selling Items (This Month)</h4>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>#</th><th>Item</th><th>Category</th><th>Qty</th><th>Revenue</th></tr></thead>
                <tbody>
                    <?php if (!empty($topItems)): $i=1; foreach ($topItems as $item): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                            <td><span class="badge"><?= htmlspecialchars($item['category']) ?></span></td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td class="text-success">KES <?= number_format($item['revenue'], 0) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-muted" style="text-align:center; padding:1.5rem;">No sales this month</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recently Sold Items -->
    <div class="section">
        <h4><i class="fas fa-clock"></i> Recently Sold Items</h4>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Item</th><th>Category</th><th>Price</th><th>Branch</th><th>Date Sold</th></tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentSales)): ?>
                        <?php foreach ($recentSales as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['item_name']) ?></strong></td>
                                <td><span class="badge"><?= htmlspecialchars($s['category']) ?></span></td>
                                <td class="text-success">KES <?= number_format($s['price'] ?? 0, 0) ?></td>
                                <td><?= htmlspecialchars($s['branch'] ?? '-') ?></td>
                                <td><?= date('M j, Y g:i A', strtotime($s['sold_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-muted" style="text-align:center; padding:2rem;">No recent sales</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Received RAM/SSD & Chargers -->
    <div class="two-col-grid">
        <div class="section" style="margin-bottom:0;">
            <h4><i class="fas fa-memory"></i> Recently Received RAM/SSD</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Item</th><th>Storage</th><th>Qty</th><th>From</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php if (!empty($recentRamGiven)): ?>
                            <?php foreach (array_slice($recentRamGiven, 0, 5) as $rg): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($rg['type'] ?? '-') ?></strong></td>
                                    <td><?= !empty($rg['storage']) ? htmlspecialchars($rg['storage'] . 'GB') : '-' ?></td>
                                    <td><span class="badge badge-primary"><?= (int)($rg['quantity_given'] ?? $rg['quantity'] ?? 0) ?></span></td>
                                    <td><i class="fas fa-user" style="margin-right:0.25rem;"></i><?= htmlspecialchars($rg['given_by_name'] ?? '-') ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($rg['date_given'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-muted" style="text-align:center; padding:1.5rem;">No recent RAM/SSD</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section" style="margin-bottom:0;">
            <h4><i class="fas fa-bolt"></i> Recently Received Chargers</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Charger</th><th>Condition</th><th>Qty</th><th>From</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php if (!empty($recentChargersGivenToMe)): ?>
                            <?php foreach (array_slice($recentChargersGivenToMe, 0, 5) as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['charger_label']) ?></strong></td>
                                    <td><span class="badge"><?= htmlspecialchars($c['charger_condition'] ?? '-') ?></span></td>
                                    <td><span class="badge badge-secondary"><?= (int)$c['quantity_given'] ?></span></td>
                                    <td><i class="fas fa-user" style="margin-right:0.25rem;"></i><?= htmlspecialchars($c['given_by_name']) ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($c['date_given'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-muted" style="text-align:center; padding:1.5rem;">No recent chargers</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <footer>
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers. All rights reserved.
        <span style="margin: 0 0.5rem;">•</span>
        <span>v2.0.0</span>
    </footer>
</div>

<script>
let monthShown = false, todayShown = false;
const monthRevenueActual = <?= $myMonthRevenueJS ?>;
const todayRevenueActual = <?= $myTodayRevenueJS ?>;

function toggleMonthRevenue() {
    const span = document.getElementById('monthRevenueValue');
    const btn = document.querySelector('.card.secondary .toggle-btn');
    if (!monthShown) {
        span.innerHTML = 'KES ' + monthRevenueActual.toLocaleString();
        btn.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
        monthShown = true;
    } else {
        span.innerHTML = '••••••';
        btn.innerHTML = '<i class="fas fa-eye"></i> Show';
        monthShown = false;
    }
}

function toggleTodayRevenue() {
    const span = document.getElementById('todayRevenueValue');
    const btn = document.querySelector('.card.success .toggle-btn');
    if (!todayShown) {
        span.innerHTML = 'KES ' + todayRevenueActual.toLocaleString();
        btn.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
        todayShown = true;
    } else {
        span.innerHTML = '••••••';
        btn.innerHTML = '<i class="fas fa-eye"></i> Show';
        todayShown = false;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    function adjustDashboardForMobile() {
        const mainContent = document.querySelector('.main-content');
        const sidebar = document.querySelector('.sidebar');
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
});
</script>

<?php require_once "../includes/footer.php"; ?>
</body>
</html>