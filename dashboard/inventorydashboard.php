<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
// STRICT ROLE CHECK - DIE IMMEDIATELY
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'inventory_admin') {
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

// ========== INVENTORY SUMMARY COUNTS ==========
$summary = [];

$queries = [
    'devices' => "SELECT COUNT(*) FROM devices WHERE status = 'In Stock'",
    'monitors' => "SELECT COUNT(*) FROM monitors WHERE status = 'In Stock'",
    'printers' => "SELECT COUNT(*) FROM printers WHERE status = 'In Stock'",
    'smartboards' => "SELECT COUNT(*) FROM smartboards WHERE status = 'instock'",
    'phones' => "SELECT COUNT(*) FROM phones WHERE status = 'instock'",
    'ups' => "SELECT COUNT(*) FROM ups WHERE status = 'instock'",
    'ram' => "SELECT COALESCE(SUM(quantity), 0) FROM rams_ssds WHERE category = 'RAM'",
    'ssd' => "SELECT COALESCE(SUM(quantity), 0) FROM rams_ssds WHERE category = 'SSD'",
    'chargers' => "SELECT COALESCE(SUM(quantity), 0) FROM chargers",
    'accessories' => "SELECT COALESCE(SUM(quantity), 0) FROM accessories WHERE status = 'instock'",
    'hdds' => "SELECT COALESCE(SUM(quantity), 0) FROM hdds",
    'graphics' => "SELECT COALESCE(SUM(quantity), 0) FROM graphic_cards WHERE status = 'instock'"
];

foreach ($queries as $key => $sql) {
    $s = safeQuery($conn, $sql);
    $summary[$key] = $s ? (int)$s->fetchColumn() : 0;
}

// ========== TOP SELLING ITEMS (MONTHLY) ==========
$topSellingItems = [];
$stmt = safeQuery($conn, "
   SELECT 
    item_name,
    category,
    COUNT(*) AS quantity_sold,
    COALESCE(SUM(price),0) AS revenue
FROM (
    SELECT 
        model_name COLLATE utf8mb4_general_ci AS item_name,
        'Device' AS category,
        selling_price AS price
    FROM devices
    WHERE status='Sold'
    AND MONTH(sold_at)=MONTH(CURDATE())
    AND YEAR(sold_at)=YEAR(CURDATE())

    UNION ALL

    SELECT 
        model_name COLLATE utf8mb4_general_ci AS item_name,
        'Monitor' AS category,
        selling_price AS price
    FROM monitors
    WHERE status='Sold'
    AND MONTH(sold_at)=MONTH(CURDATE())
    AND YEAR(sold_at)=YEAR(CURDATE())

    UNION ALL

    SELECT 
        model_name COLLATE utf8mb4_general_ci AS item_name,
        'Printer' AS category,
        selling_price AS price
    FROM printers
    WHERE status='Sold'
    AND MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    UNION ALL

    SELECT 
        model COLLATE utf8mb4_general_ci AS item_name,
        'Smartboard' AS category,
        selling_price AS price
    FROM smartboards
    WHERE status='sold'
    AND MONTH(sold_at)=MONTH(CURDATE())
    AND YEAR(sold_at)=YEAR(CURDATE())

    UNION ALL

    SELECT 
        accessory_name COLLATE utf8mb4_general_ci AS item_name,
        'Accessory' AS category,
        total_price AS price
    FROM sold_accessories
    WHERE MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    UNION ALL

    SELECT 
        charger_type COLLATE utf8mb4_general_ci AS item_name,
        'Charger' AS category,
        total_price AS price
    FROM sold_chargers
    WHERE MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    UNION ALL
    SELECT 
        CONCAT(COALESCE(brand,''), ' ', COALESCE(model,'')) COLLATE utf8mb4_general_ci AS item_name,
        'Phone' AS category,
        selling_price AS price
    FROM phones
    WHERE status='sold'
    AND MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    UNION ALL
    SELECT 
        model COLLATE utf8mb4_general_ci AS item_name,
        'UPS' AS category,
        selling_price AS price
    FROM ups
    WHERE status='sold'
    AND MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    UNION ALL
    SELECT 
        CONCAT(COALESCE(type,''), ' ', COALESCE(storage,''), 'GB') COLLATE utf8mb4_general_ci AS item_name,
        category AS category,
        selling_price * quantity AS price
    FROM sold_rams_ssds
    WHERE MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    UNION ALL
    SELECT 
        CONCAT(COALESCE(type,''), ' ', COALESCE(storage,'')) COLLATE utf8mb4_general_ci AS item_name,
        'HDD' AS category,
        selling_price * quantity AS price
    FROM sold_hdds
    WHERE MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    UNION ALL
    SELECT 
        CONCAT(COALESCE(type,''), ' ', COALESCE(storage_capacity,''), 'GB') COLLATE utf8mb4_general_ci AS item_name,
        'Graphics Card' AS category,
        selling_price * quantity AS price
    FROM sold_graphics_cards
    WHERE MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

) AS all_sales_current_month

GROUP BY item_name, category
ORDER BY revenue DESC
LIMIT 5
");
if ($stmt) $topSellingItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== TOP CATEGORIES (MONTHLY) ==========
$topCategories = [];
$stmt = safeQuery($conn, "
    SELECT 
        category_name,
        COUNT(*) as count,
        COALESCE(SUM(price), 0) as revenue
    FROM (
        SELECT c.category_name, d.selling_price AS price
        FROM devices d
        JOIN categories c ON d.category_id = c.id
        WHERE d.status = 'Sold' AND MONTH(d.sold_at) = MONTH(CURDATE()) AND YEAR(d.sold_at) = YEAR(CURDATE())
        UNION ALL
        SELECT 'Monitor' AS category_name, selling_price FROM monitors WHERE status = 'Sold' AND MONTH(sold_at) = MONTH(CURDATE()) AND YEAR(sold_at) = YEAR(CURDATE())
        UNION ALL
        SELECT 'Printer' AS category_name, selling_price FROM printers WHERE status = 'Sold' AND MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        UNION ALL
        SELECT 'Smartboard' AS category_name, selling_price FROM smartboards WHERE status = 'sold' AND MONTH(sold_at) = MONTH(CURDATE()) AND YEAR(sold_at) = YEAR(CURDATE())
        UNION ALL
        SELECT 'Accessory' AS category_name, total_price AS selling_price FROM sold_accessories WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        UNION ALL
        SELECT 'Charger' AS category_name, total_price AS selling_price FROM sold_chargers WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        -- NEW
        UNION ALL
        SELECT 'Phone' AS category_name, selling_price FROM phones WHERE status='sold' AND MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        UNION ALL
        SELECT 'UPS' AS category_name, selling_price FROM ups WHERE status='sold' AND MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        UNION ALL
        SELECT 'RAM/SSD' AS category_name, (selling_price * quantity) AS price FROM sold_rams_ssds WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        UNION ALL
        SELECT 'HDD' AS category_name, (selling_price * quantity) AS price FROM sold_hdds WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        UNION ALL
        SELECT 'Graphics Card' AS category_name, (selling_price * quantity) AS price FROM sold_graphics_cards WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
    ) AS all_categories
    GROUP BY category_name
    ORDER BY revenue DESC
    LIMIT 5
");
if ($stmt) $topCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== RECENTLY ADDED ITEMS (ALL TABLES) ==========
$recentlyAdded = [];

// Devices
$sql = "SELECT serial_number AS id, model_name AS name, 'Device' AS category, date_added, branch FROM devices ORDER BY date_added DESC LIMIT 3";
$stmt = safeQuery($conn, $sql);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'device';
        $recentlyAdded[] = $r;
    }
}

// Monitors
$sql = "SELECT serial_number AS id, model_name AS name, 'Monitor' AS category, date_added, branch FROM monitors ORDER BY date_added DESC LIMIT 3";
$stmt = safeQuery($conn, $sql);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'monitor';
        $recentlyAdded[] = $r;
    }
}

// Printers
$sql = "SELECT serial_number AS id, model_name AS name, 'Printer' AS category, date_added, branch FROM printers ORDER BY date_added DESC LIMIT 3";
$stmt = safeQuery($conn, $sql);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'printer';
        $recentlyAdded[] = $r;
    }
}

// Smartboards
$sql = "SELECT serial_number AS id, model AS name, 'Smartboard' AS category, date_added, branch FROM smartboards ORDER BY date_added DESC LIMIT 3";
$stmt = safeQuery($conn, $sql);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'smartboard';
        $recentlyAdded[] = $r;
    }
}

// Phones
$sql = "SELECT serial_number AS id, CONCAT(brand, ' ', model) AS name, 'Phone' AS category, date_added, branch FROM phones ORDER BY date_added DESC LIMIT 3";
$stmt = safeQuery($conn, $sql);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'phone';
        $recentlyAdded[] = $r;
    }
}

// UPS
$sql = "SELECT serial_number AS id, model AS name, 'UPS' AS category, date_added, branch FROM ups ORDER BY date_added DESC LIMIT 3";
$stmt = safeQuery($conn, $sql);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'ups';
        $recentlyAdded[] = $r;
    }
}

// Accessories
$sql = "SELECT id, name, 'Accessory' AS category, date_added, branch FROM accessories WHERE status='instock' ORDER BY date_added DESC LIMIT 3";
$stmt = safeQuery($conn, $sql);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'accessory';
        $recentlyAdded[] = $r;
    }
}

// Chargers
$sql = "SELECT id, charger_type AS name, 'Charger' AS category, date_updated AS date_added, branch FROM chargers ORDER BY date_updated DESC LIMIT 3";
$stmt = safeQuery($conn, $sql);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'charger';
        $recentlyAdded[] = $r;
    }
}

// HDDs
$sql = "SELECT id, CONCAT(type, ' ', storage) AS name, 'HDD' AS category, date_added, branch FROM hdds ORDER BY date_added DESC LIMIT 3";
$stmt = safeQuery($conn, $sql);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'hdd';
        $recentlyAdded[] = $r;
    }
}

// RAM/SSD
$sql = "SELECT id, CONCAT(category, ' ', type, ' ', storage, 'GB') AS name, 'RAM/SSD' AS category, date_added, branch FROM rams_ssds ORDER BY date_added DESC LIMIT 3";
$stmt = safeQuery($conn, $sql);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'ram_ssd';
        $recentlyAdded[] = $r;
    }
}

// Graphics Cards
$sql = "SELECT id, CONCAT(type, ' ', storage_capacity, 'GB') AS name, 'Graphics Card' AS category, date_added, branch FROM graphic_cards ORDER BY date_added DESC LIMIT 3";
$stmt = safeQuery($conn, $sql);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'graphic';
        $recentlyAdded[] = $r;
    }
}

// Sort by date_added descending and limit to 8
usort($recentlyAdded, function($a, $b) {
    return strtotime($b['date_added']) - strtotime($a['date_added']);
});
$recentlyAdded = array_slice($recentlyAdded, 0, 8);

// ========== RECENTLY GIVEN RAM/SSD & HDDs (by current user) ==========
$recentlyGivenRam = [];
$sql = "SELECT l.*, r.category, r.type, r.storage, u.full_name AS given_to_name 
        FROM rams_ssds_logs l
        LEFT JOIN rams_ssds r ON l.ram_ssd_id = r.id
        LEFT JOIN users u ON l.given_to = u.id
        WHERE l.given_by = :uid
        ORDER BY l.date_given DESC
        LIMIT 5";
$stmt = safeQuery($conn, $sql, ['uid' => $user_id]);
if ($stmt) $recentlyGivenRam = $stmt->fetchAll(PDO::FETCH_ASSOC);

$recentlyGivenHdd = [];
$sql = "SELECT l.*, u.full_name AS given_to_name 
        FROM hdd_logs l
        LEFT JOIN users u ON l.given_to = u.id
        WHERE l.given_by = :uid
        ORDER BY l.date_given DESC
        LIMIT 5";
$stmt = safeQuery($conn, $sql, ['uid' => $user_id]);
if ($stmt) $recentlyGivenHdd = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== LOW STOCK ITEMS (from all tables) ==========
$lowStockItems = [];
$threshold = 10;

// RAM/SSD
$stmt = safeQuery($conn, "SELECT id, category, type, storage, quantity, branch FROM rams_ssds WHERE quantity < :threshold ORDER BY quantity ASC", ['threshold' => $threshold]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'ram_ssd';
        $lowStockItems[] = $r;
    }
}

// Chargers
$stmt = safeQuery($conn, "SELECT id, charger_type AS type, watts AS storage, quantity, branch FROM chargers WHERE quantity < :threshold ORDER BY quantity ASC", ['threshold' => $threshold]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['category'] = 'Charger';
        $r['source'] = 'charger';
        $lowStockItems[] = $r;
    }
}

// HDDs
$stmt = safeQuery($conn, "SELECT id, type, storage, quantity, branch FROM hdds WHERE quantity < :threshold ORDER BY quantity ASC", ['threshold' => $threshold]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['category'] = 'HDD';
        $r['source'] = 'hdd';
        $lowStockItems[] = $r;
    }
}

// Graphics Cards
$stmt = safeQuery($conn, "SELECT id, type, storage_capacity AS storage, quantity, branch FROM graphic_cards WHERE status = 'instock' AND quantity < :threshold ORDER BY quantity ASC", ['threshold' => $threshold]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['category'] = 'Graphics Card';
        $r['source'] = 'graphic_card';
        $lowStockItems[] = $r;
    }
}

// Accessories
$stmt = safeQuery($conn, "SELECT id, name AS type, NULL AS storage, quantity, branch FROM accessories WHERE status = 'instock' AND quantity < :threshold ORDER BY quantity ASC", ['threshold' => $threshold]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['category'] = 'Accessory';
        $r['source'] = 'accessory';
        $lowStockItems[] = $r;
    }
}

// Sort by quantity and limit to 5
usort($lowStockItems, function($a, $b) {
    return $a['quantity'] - $b['quantity'];
});
$lowStockItems = array_slice($lowStockItems, 0, 5);

// ========== QUICK STATS ==========
$totalRepairs = 0;
$pendingRepairs = 0;
$completedRepairs = 0;
$softwareLogsCount = 0;

$s = safeQuery($conn, "SELECT COUNT(*) FROM repairs");
if ($s) $totalRepairs = (int)$s->fetchColumn();

$s = safeQuery($conn, "SELECT COUNT(*) FROM repairs WHERE fix_status = 'Not Fixed'");
if ($s) $pendingRepairs = (int)$s->fetchColumn();

$s = safeQuery($conn, "SELECT COUNT(*) FROM repairs WHERE fix_status = 'Fixed'");
if ($s) $completedRepairs = (int)$s->fetchColumn();

// Check if software_logs table exists
try {
    $s = $conn->query("SELECT COUNT(*) FROM software_logs");
    if ($s) $softwareLogsCount = (int)$s->fetchColumn();
} catch (PDOException $e) {
    $softwareLogsCount = 0;
}

// Get current time greeting
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
    <title>Inventory Admin Dashboard | Mombasa Computers</title>
    <style>
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

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: var(--font-sans);
        background: var(--gray-100);
        color: var(--gray-800);
        line-height: 1.5;
        overflow-x: hidden;
    }

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
        filter: brightness(0.95);
        max-width: 100%;
    }

    .section { 
        margin-bottom: 1.5rem; 
        background: white; 
        padding: 1.25rem; 
        border-radius: var(--radius-xl); 
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
        transition: all 0.2s ease;
        overflow-x: auto;
    }

    .section:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--gray-300);
    }

    .section h4 { 
        margin: 0 0 1rem 0; 
        color: var(--gray-800); 
        font-size: 1.1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        letter-spacing: -0.01em;
        flex-wrap: wrap;
    }

    .section h4 i {
        color: var(--primary);
        font-size: 1.3rem;
    }

    .section h4 .badge-count {
        font-size: 0.75rem;
        background: var(--gray-100);
        padding: 0.15rem 0.6rem;
        border-radius: 9999px;
        font-weight: 500;
        color: var(--gray-600);
        margin-left: auto;
    }

    .table-responsive {
        overflow-x: auto;
        border-radius: var(--radius-lg);
        -webkit-overflow-scrolling: touch;
        width: 100%;
    }

    .table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 0.85rem;
        min-width: 500px;
    }

    .table th { 
        padding: 0.75rem 0.75rem; 
        background: var(--gray-50); 
        color: var(--gray-600); 
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--gray-300);
        text-align: left;
        white-space: nowrap;
    }

    .table td { 
        padding: 0.75rem; 
        border-bottom: 1px solid var(--gray-200); 
        color: var(--gray-700);
        vertical-align: middle;
        word-break: break-word;
    }

    .table tbody tr:hover {
        background-color: var(--gray-50);
    }

    .badge {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 500;
        background: var(--gray-100);
        color: var(--gray-700);
        white-space: nowrap;
    }

    .badge-primary {
        background: var(--primary);
        color: white;
    }

    .badge-secondary {
        background: var(--secondary);
        color: white;
    }

    .badge-warning {
        background: var(--warning);
        color: white;
    }

    .badge-success {
        background: var(--success);
        color: white;
    }

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
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
    }

    .link-btn:hover {
        background: var(--primary-light);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .link-btn-sm {
        padding: 0.3rem 0.7rem;
        font-size: 0.75rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: 0.75rem;
        margin-top: 0.5rem;
    }

    .stat-item {
        background: var(--gray-50);
        border-radius: var(--radius-lg);
        padding: 0.75rem;
        text-align: center;
        border: 1px solid var(--gray-200);
        transition: all 0.2s ease;
    }
    .stat-item:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary-light);
    }
    .stat-item .stat-number {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }
    .stat-item .stat-label {
        font-size: 0.65rem;
        color: var(--gray-500);
        font-weight: 500;
    }

    .stat-item.devices .stat-number { color: var(--success); }
    .stat-item.monitors .stat-number { color: var(--info); }
    .stat-item.printers .stat-number { color: var(--warning); }
    .stat-item.rams .stat-number { color: #8b5cf6; }
    .stat-item.ssds .stat-number { color: #ec4899; }
    .stat-item.chargers .stat-number { color: var(--accent); }
    .stat-item.accessories .stat-number { color: #14b8a6; }
    .stat-item.smartboards .stat-number { color: #f43f5e; }
    .stat-item.phones .stat-number { color: #8b5cf6; }
    .stat-item.ups .stat-number { color: #f59e0b; }
    .stat-item.hdds .stat-number { color: #3b82f6; }
    .stat-item.graphics .stat-number { color: #ec4899; }

    .flex-between {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .flex-between { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
    .link-btn { padding: 0.5rem 1rem; background: var(--info); color: white !important; border-radius: var(--radius-md); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; font-size: 0.85rem; transition: all 0.2s ease; }
    .link-btn:hover { background: #2563eb; transform: translateY(-2px); }
    .view-all-link {
        color: var(--primary);
        text-decoration: none;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    .view-all-link:hover {
        text-decoration: underline;
    }

    .text-muted { color: var(--gray-500); }

    /* Two‑column grid for desktop, full width on mobile */
    .dashboard-grid-2col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    footer {
        text-align: center;
        padding: 1.5rem 0 0.5rem;
        margin-top: 1.5rem;
        font-size: 0.85rem;
        color: var(--gray-500);
        border-top: 1px solid var(--gray-200);
    }

    @media (max-width: 1200px) {
        .main-content {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 1.5rem 1rem 1rem !important;
            padding-top: 5rem !important;
        }
        
        .header-row {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 1rem !important;
            padding: 1rem !important;
            position: relative;
            padding-right: 70px;
        }
        
        .header-row .logo {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
        
        .page-title {
            font-size: 1.75rem !important;
            width: calc(100% - 60px);
        }
        
        .welcome-text {
            width: calc(100% - 60px);
            font-size: 0.85rem !important;
        }
        
        .section {
            padding: 1rem !important;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 1rem 0.75rem 0.75rem !important;
            padding-top: 4.5rem !important;
        }
        
        .page-title {
            font-size: 1.5rem !important;
        }
        
        .logo img {
            height: 40px !important;
        }
        
        .table td,
        .table th {
            padding: 0.5rem !important;
        }
        
        .table {
            min-width: 450px;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .dashboard-grid-2col {
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 0.75rem 0.5rem 0.5rem !important;
            padding-top: 4rem !important;
        }
        
        .page-title {
            font-size: 1.25rem !important;
        }
        
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        
        .table {
            min-width: 380px !important;
        }
        
        .badge {
            font-size: 0.65rem !important;
            padding: 0.15rem 0.4rem !important;
        }
        
        .header-row {
            padding-right: 60px !important;
        }
        
        .header-row .logo img {
            height: 32px !important;
        }

        .dashboard-grid-2col {
            grid-template-columns: 1fr !important;
            gap: 0.75rem !important;
        }
    }
    </style>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="header-row">
        <div>
            <div class="page-title">Inventory Admin Dashboard</div>
            <div class="welcome-text">
                <i class="fas fa-hand-wave" style="color: var(--accent); margin-right: 0.5rem;"></i>
                <?= $greeting ?>, <?= htmlspecialchars(explode(' ', $user_name)[0]) ?> • <?= date('l, F j, Y') ?>
            </div>
        </div>
        <div class="logo">
            <img src="../assets/MC-LOGO.png" alt="Mombasa Computers" onerror="this.style.display='none'">
        </div>
        <div>
            <a href="../dashboard/inventorydashboard.php" class="link-btn">
                <i class="fas fa-sync-alt"></i> Refresh
            </a>
        </div>
    </div>

    <!-- Inventory Summary Cards -->
    <div class="section">
         <div class="flex-between">
            <h4><i class="fas fa-warehouse"></i> Inventory Summary (Instock)</h4>
            <a href="../reports/overview.php" class="link-btn"><i class="fas fa-boxes"></i> Inventory Overview</a>
        </div>
        <div class="stats-grid">
            <div class="stat-item devices"><div class="stat-number"><?= number_format($summary['devices']) ?></div><div class="stat-label"><i class="fas fa-laptop"></i> Devices</div></div>
            <div class="stat-item monitors"><div class="stat-number"><?= number_format($summary['monitors']) ?></div><div class="stat-label"><i class="fas fa-desktop"></i> Monitors</div></div>
            <div class="stat-item printers"><div class="stat-number"><?= number_format($summary['printers']) ?></div><div class="stat-label"><i class="fas fa-print"></i> Printers</div></div>
            <div class="stat-item smartboards"><div class="stat-number"><?= number_format($summary['smartboards']) ?></div><div class="stat-label"><i class="fas fa-chalkboard"></i> Smartboards</div></div>
            <div class="stat-item phones"><div class="stat-number"><?= number_format($summary['phones']) ?></div><div class="stat-label"><i class="fas fa-mobile-alt"></i> Phones</div></div>
            <div class="stat-item ups"><div class="stat-number"><?= number_format($summary['ups']) ?></div><div class="stat-label"><i class="fas fa-battery-half"></i> UPS</div></div>
            <div class="stat-item rams"><div class="stat-number"><?= number_format($summary['ram']) ?></div><div class="stat-label"><i class="fas fa-memory"></i> RAM</div></div>
            <div class="stat-item ssds"><div class="stat-number"><?= number_format($summary['ssd']) ?></div><div class="stat-label"><i class="fas fa-hdd"></i> SSD</div></div>
            <div class="stat-item chargers"><div class="stat-number"><?= number_format($summary['chargers']) ?></div><div class="stat-label"><i class="fas fa-bolt"></i> Chargers</div></div>
            <div class="stat-item accessories"><div class="stat-number"><?= number_format($summary['accessories']) ?></div><div class="stat-label"><i class="fas fa-plug"></i> Accessories</div></div>
            <div class="stat-item hdds"><div class="stat-number"><?= number_format($summary['hdds']) ?></div><div class="stat-label"><i class="fas fa-database"></i> HDDs</div></div>
            <div class="stat-item graphics"><div class="stat-number"><?= number_format($summary['graphics']) ?></div><div class="stat-label"><i class="fas fa-microchip"></i> Graphics</div></div>
        </div>
    </div>

    <!-- Top Selling Items & Categories (two‑column grid) -->
    <div class="dashboard-grid-2col">
        <div class="section" style="margin-bottom: 0;">
            <div class="flex-between">
                <h4><i class="fas fa-fire" style="color: var(--accent);"></i> Top Selling Items</h4>
                <a href="../reports/top_items.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>#</th><th>Item Name</th><th>Category</th><th>Qty</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <?php if(!empty($topSellingItems)): $i=1; foreach($topSellingItems as $item): ?>
                        <tr>
                            <td style="text-align:center; width:35px;"><?= $i++ ?></td>
                            <td><?= htmlspecialchars(substr($item['item_name'], 0, 30)) ?></td>
                            <td><?= htmlspecialchars($item['category']) ?></td>
                            <td class="badge badge-info" style="text-align:center"><?= number_format($item['quantity_sold']) ?></td>
                            <td class="text-success">Ksh <?= number_format($item['revenue'], 0) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-muted">No sales data this month</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="section" style="margin-bottom: 0;">
            <div class="flex-between">
                <h4><i class="fas fa-chart-pie"></i> Top Categories</h4>
                <a href="../reports/category_report.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.75rem;">
                <?php if(!empty($topCategories)): foreach($topCategories as $cat): ?>
                <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 0.75rem; text-align: center; border: 1px solid var(--gray-200);">
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary);"><?= number_format($cat['count']) ?></div>
                    <div style="font-size: 0.75rem; color: var(--gray-600); font-weight: 500;"><?= htmlspecialchars($cat['category_name']) ?></div>
                    <div style="font-size: 0.65rem; color: var(--gray-400);">Ksh <?= number_format($cat['revenue'], 0) ?></div>
                </div>
                <?php endforeach; else: ?>
                <div class="text-muted" style="text-align:center; padding:1rem;">No category data</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recently Added (full width) -->
    <div class="section">
        <div class="flex-between">
            <h4><i class="fas fa-plus-circle"></i> Recently Added</h4>
            <a href="../reports/overview.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>#</th><th>Item</th><th>Category</th><th>Branch</th><th>Added</th></tr></thead>
                <tbody>
                    <?php if(!empty($recentlyAdded)): $i=1; foreach($recentlyAdded as $item): ?>
                    <tr>
                        <td style="text-align:center; width:35px;"><?= $i++ ?></td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($item['category']) ?></span></td>
                        <td><?= htmlspecialchars($item['branch'] ?? '-') ?></td>
                        <td><?= date('M j, Y g:i A', strtotime($item['date_added'])) ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" class="text-muted">No recent additions</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recently Given RAM/SSD & HDDs (two‑column grid) -->
    <div class="dashboard-grid-2col">
        <div class="section" style="margin-bottom: 0;">
            <div class="flex-between">
                <h4><i class="fas fa-memory"></i> Recently Given RAM/SSD</h4>
                <a href="../ram_ssd/ram_ssd_logs.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Given To</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php if(!empty($recentlyGivenRam)): $i=1; foreach($recentlyGivenRam as $r): ?>
                        <tr>
                            <td style="text-align:center; width:35px;"><?= $i++ ?></td>
                            <td><?= htmlspecialchars($r['category'] . ' ' . $r['type'] . ' ' . $r['storage'] . 'GB') ?></td>
                            <td><span class="badge badge-primary"><?= (int)$r['quantity_given'] ?></span></td>
                            <td><?= htmlspecialchars($r['given_to_name'] ?? '-') ?></td>
                            <td><?= date('M j, Y g:i A', strtotime($r['date_given'])) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-muted">No RAM/SSD given by you recently</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="section" style="margin-bottom: 0;">
            <div class="flex-between">
                <h4><i class="fas fa-hdd"></i> Recently Given HDDs</h4>
                <a href="../hdds/hdd_logs.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Given To</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php if(!empty($recentlyGivenHdd)): $i=1; foreach($recentlyGivenHdd as $h): ?>
                        <tr>
                            <td style="text-align:center; width:35px;"><?= $i++ ?></td>
                            <td><?= htmlspecialchars($h['type'] . ' ' . $h['storage']) ?></td>
                            <td><span class="badge badge-secondary"><?= (int)$h['quantity_given'] ?></span></td>
                            <td><?= htmlspecialchars($h['given_to_name'] ?? '-') ?></td>
                            <td><?= date('M j, Y g:i A', strtotime($h['date_given'])) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-muted">No HDDs given by you recently</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Low Stock Items & Quick Stats (two‑column grid) -->
    <div class="dashboard-grid-2col">
        <div class="section" style="margin-bottom: 0;">
            <div class="flex-between">
                <h4><i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Low Stock Items</h4>
                <a href="../reports/low_stock.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Branch</th></tr></thead>
                    <tbody>
                        <?php if(!empty($lowStockItems)): $i=1; foreach($lowStockItems as $item): ?>
                        <tr>
                            <td style="text-align:center; width:35px;"><?= $i++ ?></td>
                            <td>
                                <?php
                                if($item['source'] === 'charger'){
                                    echo htmlspecialchars(($item['type'] ?? 'Charger') . ($item['storage'] ? " {$item['storage']}W" : ''));
                                } elseif($item['source'] === 'hdd'){
                                    echo htmlspecialchars(($item['type'] ?? 'HDD') . ' ' . ($item['storage'] ?? ''));
                                } elseif($item['source'] === 'graphic_card'){
                                    echo htmlspecialchars(($item['type'] ?? 'Graphics') . ' ' . ($item['storage'] ?? '') . 'GB');
                                } elseif($item['source'] === 'accessory'){
                                    echo htmlspecialchars($item['type'] ?? 'Accessory');
                                } else {
                                    echo htmlspecialchars(($item['category'] ?? '') . ' ' . ($item['type'] ?? '-')) . (!empty($item['storage']) ? ' ' . $item['storage'] . 'GB' : '');
                                }
                                ?>
                            </td>
                            <td><span class="badge badge-danger"><?= (int)$item['quantity'] ?> left</span></td>
                            <td><?= htmlspecialchars($item['branch'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-muted">All stock levels are good</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="section" style="margin-bottom: 0;">
            <h4><i class="fas fa-chart-simple"></i> Quick Stats</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 0.75rem; text-align: center; border: 1px solid var(--gray-200);">
                    <div style="font-size: 1.3rem; font-weight: 700; color: var(--primary);"><?= number_format($totalRepairs) ?></div>
                    <div style="font-size: 0.65rem; color: var(--gray-500);">Total Repairs</div>
                </div>
                <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 0.75rem; text-align: center; border: 1px solid var(--gray-200);">
                    <div style="font-size: 1.3rem; font-weight: 700; color: var(--success);"><?= number_format($completedRepairs) ?></div>
                    <div style="font-size: 0.65rem; color: var(--gray-500);">Completed</div>
                </div>
                <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 0.75rem; text-align: center; border: 1px solid var(--gray-200);">
                    <div style="font-size: 1.3rem; font-weight: 700; color: var(--warning);"><?= number_format($pendingRepairs) ?></div>
                    <div style="font-size: 0.65rem; color: var(--gray-500);">Pending</div>
                </div>
                <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 0.75rem; text-align: center; border: 1px solid var(--gray-200);">
                    <div style="font-size: 1.3rem; font-weight: 700; color: var(--info);"><?= number_format($softwareLogsCount) ?></div>
                    <div style="font-size: 0.65rem; color: var(--gray-500);">Software Logs</div>
                    <a href="../software/software_logs.php" class="link-btn link-btn-sm" style="margin-top:0.25rem;">View</a>
                </div>
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