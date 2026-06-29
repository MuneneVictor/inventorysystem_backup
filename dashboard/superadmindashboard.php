<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";
$user_id = $_SESSION['user_id'];
if (!isset($user_id)){
    header("Location: ../login.php");
    exit();
}
// STRICT ROLE CHECK - DIE IMMEDIATELY
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    die("ACCESS DENIED: You do not have permission to access this page.");
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// SECURE QUERY FUNCTION
function secureQuery($conn, $sql, $params = []) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

// ========== HELPER: Unify sales data (revenue, counts, etc.) ==========
function getUnifiedSales($conn, $whereClause = '', $params = [], $orderBy = '') {
    $sql = "
        SELECT 
            'Device' as source_table,
            d.model_name as item_name,
            COALESCE(c.category_name, 'Device') as category,
            d.selling_price as price,
            d.sold_at as sold_at,
            d.sold_by,
            d.branch
        FROM devices d
        LEFT JOIN categories c ON d.category_id = c.id
        WHERE d.status = 'Sold'
        
        UNION ALL
        
        SELECT 
            'Monitor',
            m.model_name,
            'Monitor',
            m.selling_price,
            m.sold_at,
            m.sold_by,
            m.branch
        FROM monitors m
        WHERE m.status = 'Sold'
        
        UNION ALL
        
        SELECT 
            'Printer',
            p.model_name,
            'Printer',
            p.selling_price,
            p.date_sold,
            p.sold_by,
            p.branch
        FROM printers p
        WHERE p.status = 'Sold'
        
        UNION ALL
        
        SELECT 
            'Smartboard',
            s.model,
            'Smartboard',
            s.selling_price,
            s.sold_at,
            s.sold_by,
            s.branch
        FROM smartboards s
        WHERE s.status = 'sold'
        
        UNION ALL
        
        SELECT 
            'Accessory',
            sa.accessory_name,
            'Accessory',
            sa.total_price,
            sa.date_sold,
            sa.sold_by,
            sa.branch
        FROM sold_accessories sa
        
        UNION ALL
        
        SELECT 
            'Charger',
            sc.charger_type,
            'Charger',
            sc.total_price,
            sc.date_sold,
            sc.sold_by,
            sc.branch
        FROM sold_chargers sc
        
        -- NEW TABLES
        UNION ALL
        SELECT 
            'Phone',
            CONCAT(COALESCE(brand,''), ' ', COALESCE(model,'')) AS item_name,
            'Phone' AS category,
            selling_price,
            date_sold AS sold_at,
            sold_by,
            branch
        FROM phones
        WHERE status = 'sold'
        
        UNION ALL
        SELECT 
            'UPS',
            model,
            'UPS',
            selling_price,
            date_sold AS sold_at,
            sold_by,
            branch
        FROM ups
        WHERE status = 'sold'
        
        UNION ALL
        SELECT 
            'RAM/SSD',
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage,''), 'GB') AS item_name,
            category,
            total_price AS price,
            date_sold AS sold_at,
            sold_by,
            branch
        FROM sold_rams_ssds
        
        UNION ALL
        SELECT 
            'HDD',
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage,'')) AS item_name,
            'HDD',
            total_price AS price,
            date_sold AS sold_at,
            sold_by,
            branch
        FROM sold_hdds
        
        UNION ALL
        SELECT 
            'Graphics Card',
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage_capacity,''), 'GB') AS item_name,
            'Graphics Card',
            total_price AS price,
            date_sold AS sold_at,
            sold_by,
            branch
        FROM sold_graphics_cards
    ";
    
    if ($whereClause) {
        $fullSql = "SELECT * FROM ($sql) AS unified_sales WHERE " . $whereClause . " " . $orderBy;
    } else {
        $fullSql = "SELECT * FROM ($sql) AS unified_sales " . $orderBy;
    }
    
    $stmt = secureQuery($conn, $fullSql, $params);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

// ========== DEVICE STATISTICS (stock counts) ==========
$stmt = secureQuery($conn, "SELECT COUNT(*) FROM devices");
$totalDevices = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COUNT(*) FROM devices WHERE status = 'In Stock'");
$totalInStock = $stmt ? (int)$stmt->fetchColumn() : 0;

// ========== UNIFIED SALES STATISTICS ==========
// Total sales count (all sold items)
$totalSalesCount = 0;
$stmt = secureQuery($conn, "
    SELECT COUNT(*) FROM (
        SELECT 1 FROM devices WHERE status = 'Sold' UNION ALL
        SELECT 1 FROM monitors WHERE status = 'Sold' UNION ALL
        SELECT 1 FROM printers WHERE status = 'Sold' UNION ALL
        SELECT 1 FROM smartboards WHERE status = 'sold' UNION ALL
        SELECT 1 FROM sold_accessories UNION ALL
        SELECT 1 FROM sold_chargers UNION ALL
        SELECT 1 FROM phones WHERE status = 'sold' UNION ALL
        SELECT 1 FROM ups WHERE status = 'sold' UNION ALL
        SELECT 1 FROM sold_rams_ssds UNION ALL
        SELECT 1 FROM sold_hdds UNION ALL
        SELECT 1 FROM sold_graphics_cards
    ) AS all_sales
");
if ($stmt) $totalSalesCount = (int)$stmt->fetchColumn();

// Today's sales count
$todaysSalesCount = 0;
$stmt = secureQuery($conn, "
    SELECT COUNT(*) FROM (
        SELECT 1 FROM devices WHERE status = 'Sold' AND DATE(sold_at) = CURDATE() UNION ALL
        SELECT 1 FROM monitors WHERE status = 'Sold' AND DATE(sold_at) = CURDATE() UNION ALL
        SELECT 1 FROM printers WHERE status = 'Sold' AND DATE(date_sold) = CURDATE() UNION ALL
        SELECT 1 FROM smartboards WHERE status = 'sold' AND DATE(sold_at) = CURDATE() UNION ALL
        SELECT 1 FROM sold_accessories WHERE DATE(date_sold) = CURDATE() UNION ALL
        SELECT 1 FROM sold_chargers WHERE DATE(date_sold) = CURDATE() UNION ALL
        SELECT 1 FROM phones WHERE status = 'sold' AND DATE(date_sold) = CURDATE() UNION ALL
        SELECT 1 FROM ups WHERE status = 'sold' AND DATE(date_sold) = CURDATE() UNION ALL
        SELECT 1 FROM sold_rams_ssds WHERE DATE(date_sold) = CURDATE() UNION ALL
        SELECT 1 FROM sold_hdds WHERE DATE(date_sold) = CURDATE() UNION ALL
        SELECT 1 FROM sold_graphics_cards WHERE DATE(date_sold) = CURDATE()
    ) AS todays_sales
");
if ($stmt) $todaysSalesCount = (int)$stmt->fetchColumn();

// Total revenue (all time)
$totalRevenue = 0;
$stmt = secureQuery($conn, "
    SELECT COALESCE(SUM(price), 0) FROM (
        SELECT selling_price AS price FROM devices WHERE status = 'Sold' UNION ALL
        SELECT selling_price AS price FROM monitors WHERE status = 'Sold' UNION ALL
        SELECT selling_price AS price FROM printers WHERE status = 'Sold' UNION ALL
        SELECT selling_price AS price FROM smartboards WHERE status = 'sold' UNION ALL
        SELECT total_price AS price FROM sold_accessories UNION ALL
        SELECT total_price AS price FROM sold_chargers UNION ALL
        SELECT selling_price AS price FROM phones WHERE status = 'sold' UNION ALL
        SELECT selling_price AS price FROM ups WHERE status = 'sold' UNION ALL
        SELECT (selling_price * quantity) AS price FROM sold_rams_ssds UNION ALL
        SELECT (selling_price * quantity) AS price FROM sold_hdds UNION ALL
        SELECT (selling_price * quantity) AS price FROM sold_graphics_cards
    ) AS all_prices
");
if ($stmt) $totalRevenue = (float)$stmt->fetchColumn();

// Today's revenue
$todaysRevenue = 0;
$stmt = secureQuery($conn, "
    SELECT COALESCE(SUM(price), 0) FROM (
        SELECT selling_price AS price, sold_at FROM devices WHERE status = 'Sold' UNION ALL
        SELECT selling_price AS price, sold_at FROM monitors WHERE status = 'Sold' UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM printers WHERE status = 'Sold' UNION ALL
        SELECT selling_price AS price, sold_at FROM smartboards WHERE status = 'sold' UNION ALL
        SELECT total_price AS price, date_sold AS sold_at FROM sold_accessories UNION ALL
        SELECT total_price AS price, date_sold AS sold_at FROM sold_chargers UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM phones WHERE status = 'sold' UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM ups WHERE status = 'sold' UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_rams_ssds UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_hdds UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_graphics_cards
    ) AS today_prices
    WHERE DATE(sold_at) = CURDATE()
");
if ($stmt) $todaysRevenue = (float)$stmt->fetchColumn();

// Monthly revenue
$monthlyRevenue = 0;
$stmt = secureQuery($conn, "
    SELECT COALESCE(SUM(price), 0) FROM (
        SELECT selling_price AS price, sold_at FROM devices WHERE status = 'Sold' UNION ALL
        SELECT selling_price AS price, sold_at FROM monitors WHERE status = 'Sold' UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM printers WHERE status = 'Sold' UNION ALL
        SELECT selling_price AS price, sold_at FROM smartboards WHERE status = 'sold' UNION ALL
        SELECT total_price AS price, date_sold AS sold_at FROM sold_accessories UNION ALL
        SELECT total_price AS price, date_sold AS sold_at FROM sold_chargers UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM phones WHERE status = 'sold' UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM ups WHERE status = 'sold' UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_rams_ssds UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_hdds UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_graphics_cards
    ) AS month_prices
    WHERE MONTH(sold_at) = MONTH(CURDATE()) AND YEAR(sold_at) = YEAR(CURDATE())
");
if ($stmt) $monthlyRevenue = (float)$stmt->fetchColumn();

// Monthly sales count
$monthlySales = 0;
$stmt = secureQuery($conn, "
    SELECT COUNT(*) FROM (
        SELECT 1 FROM devices WHERE status = 'Sold' AND MONTH(sold_at) = MONTH(CURDATE()) AND YEAR(sold_at) = YEAR(CURDATE()) UNION ALL
        SELECT 1 FROM monitors WHERE status = 'Sold' AND MONTH(sold_at) = MONTH(CURDATE()) AND YEAR(sold_at) = YEAR(CURDATE()) UNION ALL
        SELECT 1 FROM printers WHERE status = 'Sold' AND MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE()) UNION ALL
        SELECT 1 FROM smartboards WHERE status = 'sold' AND MONTH(sold_at) = MONTH(CURDATE()) AND YEAR(sold_at) = YEAR(CURDATE()) UNION ALL
        SELECT 1 FROM sold_accessories WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE()) UNION ALL
        SELECT 1 FROM sold_chargers WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE()) UNION ALL
        SELECT 1 FROM phones WHERE status = 'sold' AND MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE()) UNION ALL
        SELECT 1 FROM ups WHERE status = 'sold' AND MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE()) UNION ALL
        SELECT 1 FROM sold_rams_ssds WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE()) UNION ALL
        SELECT 1 FROM sold_hdds WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE()) UNION ALL
        SELECT 1 FROM sold_graphics_cards WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
    ) AS monthly_sales
");
if ($stmt) $monthlySales = (int)$stmt->fetchColumn();

// ========== INVENTORY SUMMARY (NEW TABLES ADDED) ==========
$stmt = secureQuery($conn, "SELECT COUNT(*) FROM devices WHERE status = 'In Stock'");
$inventoryDevices = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COUNT(*) FROM monitors WHERE status = 'In Stock'");
$inventoryMonitors = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COUNT(*) FROM printers WHERE status = 'In Stock'");
$inventoryPrinters = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COALESCE(SUM(quantity), 0) FROM rams_ssds WHERE category = 'RAM'");
$inventoryRams = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COALESCE(SUM(quantity), 0) FROM rams_ssds WHERE category = 'SSD'");
$inventorySsds = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COALESCE(SUM(quantity), 0) FROM chargers");
$inventoryChargers = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COALESCE(SUM(quantity), 0) FROM accessories");
$inventoryAccessories = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COUNT(*) FROM smartboards WHERE status = 'instock'");
$inventorySmartboards = $stmt ? (int)$stmt->fetchColumn() : 0;

// ---- NEW INVENTORY COUNTS ----
$stmt = secureQuery($conn, "SELECT COUNT(*) FROM phones WHERE status = 'instock'");
$inventoryPhones = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COUNT(*) FROM ups WHERE status = 'instock'");
$inventoryUps = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COALESCE(SUM(quantity), 0) FROM hdds");
$inventoryHdds = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COALESCE(SUM(quantity), 0) FROM graphic_cards WHERE status = 'instock'");
$inventoryGraphics = $stmt ? (int)$stmt->fetchColumn() : 0;

// ========== ACCESSORIES GIVEN (unchanged) ==========
$stmt = secureQuery($conn, "SELECT COALESCE(SUM(quantity), 0) FROM rams_ssds_logs");
$totalRamGiven = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COALESCE(SUM(quantity), 0) FROM charger_logs");
$totalChargersGiven = $stmt ? (int)$stmt->fetchColumn() : 0;
$totalAccessoriesGiven = $totalRamGiven + $totalChargersGiven;

// ========== REPAIR STATISTICS (unchanged) ==========
$stmt = secureQuery($conn, "SELECT COUNT(*) FROM repairs");
$totalRepairs = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COUNT(*) FROM repairs WHERE fix_status = 'Not Fixed'");
$pendingRepairs = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COUNT(*) FROM repairs WHERE fix_status = 'Fixed'");
$completedRepairs = $stmt ? (int)$stmt->fetchColumn() : 0;

// ========== LOW STOCK ITEMS (UNIFIED FROM ALL INVENTORY TABLES) ==========
$lowStockItems = [];
$threshold = 10;

// 1. RAM/SSD
$stmt = secureQuery($conn, "SELECT id, category, type, storage, quantity, branch FROM rams_ssds WHERE quantity < :threshold ORDER BY quantity ASC", ['threshold' => $threshold]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['source'] = 'ram_ssd';
        $lowStockItems[] = $r;
    }
}

// 2. Chargers
$stmt = secureQuery($conn, "SELECT id, charger_type AS type, watts AS storage, quantity, branch FROM chargers WHERE quantity < :threshold ORDER BY quantity ASC", ['threshold' => $threshold]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['category'] = 'Charger';
        $r['source'] = 'charger';
        $lowStockItems[] = $r;
    }
}

// 3. HDDs
$stmt = secureQuery($conn, "SELECT id, type, storage, quantity, branch FROM hdds WHERE quantity < :threshold ORDER BY quantity ASC", ['threshold' => $threshold]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['category'] = 'HDD';
        $r['source'] = 'hdd';
        $lowStockItems[] = $r;
    }
}

// 4. Graphics Cards
$stmt = secureQuery($conn, "SELECT id, type, storage_capacity AS storage, quantity, branch FROM graphic_cards WHERE status = 'instock' AND quantity < :threshold ORDER BY quantity ASC", ['threshold' => $threshold]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['category'] = 'Graphics Card';
        $r['source'] = 'graphic_card';
        $lowStockItems[] = $r;
    }
}

// 5. Accessories
$stmt = secureQuery($conn, "SELECT id, name AS type, NULL AS storage, quantity, branch FROM accessories WHERE quantity < :threshold ORDER BY quantity ASC", ['threshold' => $threshold]);
if ($stmt) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $r['category'] = 'Accessory';
        $r['source'] = 'accessory';
        $lowStockItems[] = $r;
    }
}

// Sort by quantity ascending and limit to 5
usort($lowStockItems, function($a, $b) {
    return $a['quantity'] - $b['quantity'];
});
$lowStockItems = array_slice($lowStockItems, 0, 5);

// ========== TOP SELLING ITEMS (UNIFIED, by revenue, top 8) ==========
$topSellingItems = [];
$stmt = secureQuery($conn, "
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

    -- NEW TABLES
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
LIMIT 8
");
if ($stmt) {
    $topSellingItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ========== TOP CATEGORIES (UNIFIED) ==========
$topCategories = [];
$stmt = secureQuery($conn, "
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
    LIMIT 6
");
if ($stmt) {
    $topCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ========== SALES TREND (LAST 7 DAYS) – UNIFIED ==========
$chartLabels = [];
$chartData = [];
$maxChartValue = 1;

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('D', strtotime($date));

    $stmt = secureQuery($conn, "
        SELECT COALESCE(SUM(price), 0) FROM (
            SELECT selling_price AS price, sold_at FROM devices WHERE status = 'Sold' UNION ALL
            SELECT selling_price AS price, sold_at FROM monitors WHERE status = 'Sold' UNION ALL
            SELECT selling_price AS price, date_sold AS sold_at FROM printers WHERE status = 'Sold' UNION ALL
            SELECT selling_price AS price, sold_at FROM smartboards WHERE status = 'sold' UNION ALL
            SELECT total_price AS price, date_sold AS sold_at FROM sold_accessories UNION ALL
            SELECT total_price AS price, date_sold AS sold_at FROM sold_chargers UNION ALL
            SELECT selling_price AS price, date_sold AS sold_at FROM phones WHERE status='sold' UNION ALL
            SELECT selling_price AS price, date_sold AS sold_at FROM ups WHERE status='sold' UNION ALL
            SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_rams_ssds UNION ALL
            SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_hdds UNION ALL
            SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_graphics_cards
        ) AS daily_prices
        WHERE DATE(sold_at) = :date
    ", ['date' => $date]);
    $dailyTotal = $stmt ? (float)$stmt->fetchColumn() : 0;
    $chartData[] = $dailyTotal;

    if ($dailyTotal > $maxChartValue) $maxChartValue = $dailyTotal;
}
if ($maxChartValue == 0) $maxChartValue = 1;

// ========== RECENT ACTIVITIES (unchanged) ==========
$stmt = secureQuery($conn, "SELECT a.*, u.full_name AS done_by_name FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 6");
$recentActivities = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// ========== RECENTLY ADDED ITEMS (ONLY DEVICES) ==========
$recentAddedItems = [];
$stmt = secureQuery($conn, "
    SELECT 
        d.serial_number AS id,
        d.model_name AS item_name,
        'Device' AS category,
        d.date_added,
        d.branch,
        d.ram,
        d.storage_type,
        d.storage_capacity,
        d.status
    FROM devices d
    ORDER BY d.date_added DESC
    LIMIT 6
");
if ($stmt) {
    $recentAddedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ========== RECENTLY SOLD ITEMS (ONLY DEVICES) ==========
$recentSoldItems = [];
$stmt = secureQuery($conn, "
    SELECT 
        d.serial_number AS id,
        d.model_name AS item_name,
        COALESCE(c.category_name, 'Device') AS category,
        d.selling_price AS price,
        d.sold_at,
        d.branch,
        d.ram,
        d.storage_type,
        d.storage_capacity
    FROM devices d
    LEFT JOIN categories c ON d.category_id = c.id
    WHERE d.status = 'Sold'
    ORDER BY d.sold_at DESC
    LIMIT 6
");
if ($stmt) {
    $recentSoldItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ========== BRANCH SALES (UNIFIED) ==========
$branchSales = [];
$stmt = secureQuery($conn, "
   SELECT 
    branch,
    COUNT(*) AS sales_count,
    COALESCE(SUM(price), 0) AS total_revenue
FROM (
    SELECT branch COLLATE utf8mb4_general_ci AS branch, selling_price AS price
    FROM devices
    WHERE status='Sold'
    AND MONTH(sold_at)=MONTH(CURDATE())
    AND YEAR(sold_at)=YEAR(CURDATE())

    UNION ALL

    SELECT branch COLLATE utf8mb4_general_ci, selling_price AS price
    FROM monitors
    WHERE status='Sold'
    AND MONTH(sold_at)=MONTH(CURDATE())
    AND YEAR(sold_at)=YEAR(CURDATE())

    UNION ALL

    SELECT branch COLLATE utf8mb4_general_ci, selling_price AS price
    FROM printers
    WHERE status='Sold'
    AND MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    UNION ALL

    SELECT branch COLLATE utf8mb4_general_ci, selling_price AS price
    FROM smartboards
    WHERE status='sold'
    AND MONTH(sold_at)=MONTH(CURDATE())
    AND YEAR(sold_at)=YEAR(CURDATE())

    UNION ALL

    SELECT branch COLLATE utf8mb4_general_ci, total_price AS price
    FROM sold_accessories
    WHERE MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    UNION ALL

    SELECT branch COLLATE utf8mb4_general_ci, total_price AS price
    FROM sold_chargers
    WHERE MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    -- NEW
    UNION ALL
    SELECT branch COLLATE utf8mb4_general_ci, selling_price AS price
    FROM phones
    WHERE status='sold'
    AND MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    UNION ALL
    SELECT branch COLLATE utf8mb4_general_ci, selling_price AS price
    FROM ups
    WHERE status='sold'
    AND MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    UNION ALL
    SELECT branch COLLATE utf8mb4_general_ci, (selling_price * quantity) AS price
    FROM sold_rams_ssds
    WHERE MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    UNION ALL
    SELECT branch COLLATE utf8mb4_general_ci, (selling_price * quantity) AS price
    FROM sold_hdds
    WHERE MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())

    UNION ALL
    SELECT branch COLLATE utf8mb4_general_ci, (selling_price * quantity) AS price
    FROM sold_graphics_cards
    WHERE MONTH(date_sold)=MONTH(CURDATE())
    AND YEAR(date_sold)=YEAR(CURDATE())
) AS branch_sales
GROUP BY branch
ORDER BY total_revenue DESC
");
if ($stmt) {
    $branchSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ========== TOP SALES PEOPLE (UNIFIED, BY REVENUE) ==========
$topSalesPeople = [];
$stmt = secureQuery($conn, "
    SELECT 
        u.full_name,
        u.branch,
        COUNT(*) as sales_count,
        COALESCE(SUM(s.price), 0) as total_revenue
    FROM (
        SELECT sold_by, selling_price AS price FROM devices WHERE status = 'Sold' AND MONTH(sold_at) = MONTH(CURDATE()) AND YEAR(sold_at) = YEAR(CURDATE())
        UNION ALL
        SELECT sold_by, selling_price FROM monitors WHERE status = 'Sold' AND MONTH(sold_at) = MONTH(CURDATE()) AND YEAR(sold_at) = YEAR(CURDATE())
        UNION ALL
        SELECT sold_by, selling_price FROM printers WHERE status = 'Sold' AND MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        UNION ALL
        SELECT sold_by, selling_price FROM smartboards WHERE status = 'sold' AND MONTH(sold_at) = MONTH(CURDATE()) AND YEAR(sold_at) = YEAR(CURDATE())
        UNION ALL
        SELECT sold_by, total_price AS selling_price FROM sold_accessories WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        UNION ALL
        SELECT sold_by, total_price AS selling_price FROM sold_chargers WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        -- NEW
        UNION ALL
        SELECT sold_by, selling_price FROM phones WHERE status='sold' AND MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        UNION ALL
        SELECT sold_by, selling_price FROM ups WHERE status='sold' AND MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        UNION ALL
        SELECT sold_by, (selling_price * quantity) AS price FROM sold_rams_ssds WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        UNION ALL
        SELECT sold_by, (selling_price * quantity) AS price FROM sold_hdds WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
        UNION ALL
        SELECT sold_by, (selling_price * quantity) AS price FROM sold_graphics_cards WHERE MONTH(date_sold) = MONTH(CURDATE()) AND YEAR(date_sold) = YEAR(CURDATE())
    ) AS s
    JOIN users u ON s.sold_by = u.id
    GROUP BY u.id
    ORDER BY total_revenue DESC
    LIMIT 5
");
if ($stmt) {
    $topSalesPeople = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Store values for JavaScript toggle
$todaysRevenueFormatted = number_format($todaysRevenue, 0);
$monthlyRevenueFormatted = number_format($monthlyRevenue, 0);

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
    <title>Super Admin Dashboard | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* (All existing CSS unchanged – place the same styles here) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
            --primary-dark: #0f3a1e;
            --secondary: #1a4f6e;
            --accent: #f59e0b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --purple: #8b5cf6;
            --pink: #ec4899;
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
            --font-sans: 'Inter', system-ui, sans-serif;
        }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; min-height: 100vh; display: flex; flex-direction: column; }
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: all 0.3s ease; flex: 1; }
        .header-row { display: flex; justify-content: space-between; align-items: center; gap: 1.5rem; margin-bottom: 2rem; background: white; padding: 1.25rem 2rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); flex-wrap: wrap; }
        .page-title { font-size: 2rem; color: var(--primary-dark); font-weight: 700; }
        .welcome-text { font-size: 0.95rem; color: var(--gray-500); margin-top: 0.25rem; }
        .logo img { height: 48px; width: auto; max-width: 100%; }
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: var(--radius-xl); padding: 1.25rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); transition: all 0.3s ease; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        .stat-card:nth-child(1)::before { background: linear-gradient(90deg, var(--success), #34d399); }
        .stat-card:nth-child(2)::before { background: linear-gradient(90deg, var(--info), #60a5fa); }
        .stat-card:nth-child(3)::before { background: linear-gradient(90deg, var(--accent), #fbbf24); }
        .stat-card:nth-child(4)::before { background: linear-gradient(90deg, var(--purple), #a78bfa); }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        .stat-card .stat-icon { font-size: 2rem; margin-bottom: 0.75rem; }
        .stat-card .stat-value { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.25rem; }
        .stat-card .stat-label { font-size: 0.8rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-sub { font-size: 0.7rem; color: var(--gray-400); margin-top: 0.25rem; }
        .stat-card:nth-child(1) .stat-icon, .stat-card:nth-child(1) .stat-value { color: var(--success); }
        .stat-card:nth-child(2) .stat-icon, .stat-card:nth-child(2) .stat-value { color: var(--info); }
        .stat-card:nth-child(3) .stat-icon, .stat-card:nth-child(3) .stat-value { color: var(--accent); }
        .stat-card:nth-child(4) .stat-icon, .stat-card:nth-child(4) .stat-value { color: var(--purple); }
        .toggle-btn { background: none; border: 1px solid var(--gray-300); padding: 0.25rem 0.6rem; border-radius: var(--radius-sm); cursor: pointer; font-size: 0.7rem; color: var(--gray-500); margin-top: 0.5rem; transition: all 0.2s; }
        .toggle-btn:hover { background: var(--gray-100); border-color: var(--primary); color: var(--primary); }
        .section { margin-bottom: 2rem; background: white; padding: 1.5rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); overflow-x: auto; }
        .section h4 { margin: 0 0 1rem 0; color: var(--gray-800); font-size: 1.1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; border-left: 3px solid var(--primary); padding-left: 0.75rem; }
        .section h4 i { color: var(--primary); font-size: 1.2rem; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
        .view-all-link { color: var(--primary); text-decoration: none; font-size: 0.75rem; font-weight: 500; display: inline-flex; align-items: center; gap: 0.25rem; }
        .view-all-link:hover { text-decoration: underline; }
        .table-responsive { overflow-x: auto; width: 100%; }
        .table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .table th { padding: 0.75rem 0.5rem; background: var(--gray-50); color: var(--gray-600); font-weight: 600; font-size: 0.7rem; text-transform: uppercase; border-bottom: 1px solid var(--gray-200); text-align: left; }
        .table td { padding: 0.75rem 0.5rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); vertical-align: middle; }
        .table code { background: var(--gray-100); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-family: monospace; font-size: 0.75rem; }
        .badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 500; white-space: nowrap; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fed7aa; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-primary { background: #dcfce7; color: #166534; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .btn-view { background: var(--info); color: white; border: none; border-radius: var(--radius-sm); padding: 0.25rem 0.6rem; font-size: 0.7rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem; transition: background 0.2s; }
        .btn-view:hover { background: #2563eb; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .stat-item { background: var(--gray-50); border-radius: var(--radius-lg); padding: 1rem; text-align: center; border: 1px solid var(--gray-200); transition: all 0.2s ease; }
        .stat-item:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: var(--primary-light); }
        .stat-item .stat-number { font-size: 1.6rem; font-weight: 700; margin-bottom: 0.25rem; }
        .stat-item .stat-label { font-size: 0.75rem; color: var(--gray-500); font-weight: 500; }
        .stat-item.devices .stat-number { color: var(--success); }
        .stat-item.monitors .stat-number { color: var(--info); }
        .stat-item.printers .stat-number { color: var(--warning); }
        .stat-item.rams .stat-number { color: var(--purple); }
        .stat-item.ssds .stat-number { color: var(--pink); }
        .stat-item.chargers .stat-number { color: var(--accent); }
        .stat-item.accessories .stat-number { color: #14b8a6; }
        .stat-item.smartboards .stat-number { color: #f43f5e; }
        /* New colors for added items */
        .stat-item.phones .stat-number { color: #8b5cf6; }
        .stat-item.ups .stat-number { color: #f59e0b; }
        .stat-item.hdds .stat-number { color: #3b82f6; }
        .stat-item.graphics .stat-number { color: #ec4899; }
        .categories-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .category-card { background: linear-gradient(135deg, var(--gray-50) 0%, white 100%); border-radius: var(--radius-lg); padding: 1rem; text-align: center; border: 1px solid var(--gray-200); transition: all 0.2s ease; }
        .category-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--primary); }
        .category-card .category-count { font-size: 1.8rem; font-weight: 700; color: var(--primary); margin-bottom: 0.25rem; }
        .category-card .category-name { font-size: 0.85rem; color: var(--gray-600); font-weight: 500; }
        .category-card .category-revenue { font-size: 0.7rem; color: var(--gray-400); margin-top: 0.25rem; }
        .chart-container { margin-top: 1rem; }
        .chart-bars { display: flex; align-items: flex-end; justify-content: space-between; gap: 0.5rem; height: 150px; margin: 1rem 0; flex-wrap: nowrap; }
        .chart-bar-wrapper { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.3rem; min-width: 40px; }
        .chart-bar { width: 100%; max-width: 50px; background: linear-gradient(180deg, var(--primary-light) 0%, var(--primary) 100%); border-radius: var(--radius-sm) var(--radius-sm) 0 0; transition: height 0.3s ease; min-height: 5px; margin: 0 auto; }
        .chart-label { font-size: 0.65rem; color: var(--gray-500); text-align: center; }
        .chart-value { font-size: 0.65rem; font-weight: 600; color: var(--primary-dark); text-align: center; white-space: nowrap; }
        .three-column { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .full-width { width: 100%; margin-bottom: 2rem; }
        .link-btn { padding: 0.5rem 1rem; background: var(--info); color: white !important; border-radius: var(--radius-md); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; font-size: 0.85rem; transition: all 0.2s ease; }
        .link-btn:hover { background: #2563eb; transform: translateY(-2px); }
        footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 2rem; font-size: 0.8rem; color: var(--gray-500); border-top: 1px solid var(--gray-200); }
        .text-success { color: var(--success); }
        .text-muted { color: var(--gray-400); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .stats-row, .section, .header-row { animation: fadeIn 0.4s ease-out forwards; }
        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
            .header-row { flex-direction: column; align-items: flex-start; position: relative; padding-right: 70px !important; }
            .header-row .logo { position: absolute; top: 1.25rem; right: 1.25rem; }
            .page-title { font-size: 1.75rem !important; width: calc(100% - 60px); }
            .stats-row { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
            .three-column { grid-template-columns: 1fr; gap: 1rem; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-title { font-size: 1.5rem !important; }
            .logo img { height: 40px !important; bottom: 1.25rem !important; }
            .stats-row { grid-template-columns: 1fr; gap: 0.75rem; }
            .stat-card .stat-value { font-size: 1.4rem; }
            .section { padding: 1rem; }
            .table th, .table td { padding: 0.5rem 0.25rem; font-size: 0.7rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .categories-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .chart-bars { height: 120px; gap: 0.25rem; }
            .chart-bar-wrapper { min-width: 30px; }
            .chart-value { font-size: 0.55rem; white-space: normal; }
            .chart-label { font-size: 0.55rem; }
            .welcome-text { font-size: 0.9rem; max-width: 70%; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .header-row { padding: 0.75rem !important; padding-right: 60px !important; }
            .page-title { font-size: 1.25rem !important; width: calc(100% - 50px) !important; }
            .stats-grid { grid-template-columns: 1fr; }
            .categories-grid { grid-template-columns: 1fr; }
            .table { min-width: 400px; }
            .chart-bars { height: 100px; gap: 0.2rem; }
            .chart-bar-wrapper { min-width: 25px; }
            .chart-value { font-size: 0.5rem; }
            .welcome-text { font-size: 0.8rem; max-width: 60%; }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="header-row">
        <div>
            <div class="page-title">Super Admin Dashboard</div>
            <div class="welcome-text"><i class="fas fa-hand-wave" style="color: var(--accent);"></i> <?= $greeting ?>, <?= htmlspecialchars(explode(' ', $user_name)[0]) ?> • <?= date('l, F j, Y') ?></div>
        </div>
        <div class="logo"><img src="/inventory_system/assets/MC-LOGO.png" alt="Mombasa Computers" onerror="this.style.display='none'"></div>
        <div><a href="/inventory_system/dashboard/superadmindashboard.php" class="link-btn"><i class="fas fa-sync-alt"></i> Refresh</a></div>
    </div>

    <!-- Stats Cards Row - 4 Cards -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-value"><?= number_format($totalInStock) ?></div>
            <div class="stat-label">In Stock Devices</div>
            <div class="stat-sub">of <?= number_format($totalDevices) ?> total devices</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-value"><?= number_format($todaysSalesCount) ?></div>
            <div class="stat-label">Today's Sales</div>
            <div class="stat-sub">All product types</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-value" id="todayRevenueValue">••••••</div>
            <div class="stat-label">Today's Revenue</div>
            <button class="toggle-btn" onclick="toggleTodayRevenue()"><i class="fas fa-eye"></i> Show</button>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-value" id="monthRevenueValue">••••••</div>
            <div class="stat-label">Monthly Revenue</div>
            <button class="toggle-btn" onclick="toggleMonthRevenue()"><i class="fas fa-eye"></i> Show</button>
        </div>
    </div>

    <!-- Sales Trend Chart -->
    <div class="section">
        <div class="flex-between">
            <h4><i class="fas fa-chart-line"></i> Sales Trend (Last 7 Days)</h4>
            <span style="font-size: 0.7rem; color: var(--gray-500);">Daily revenue in KES</span>
        </div>
        <div class="chart-container">
            <div class="chart-bars">
                <?php foreach ($chartData as $index => $value): ?>
                <div class="chart-bar-wrapper">
                    <div class="chart-value">Ksh <?= number_format($value, 0) ?></div>
                    <div class="chart-bar" style="height: <?= max(15, ($value / $maxChartValue) * 100) ?>px;"></div>
                    <div class="chart-label"><?= $chartLabels[$index] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Inventory Summary (includes new tables) -->
    <div class="section">
        <h4><i class="fas fa-warehouse"></i> Inventory Summary (Instock)</h4>
        <div class="stats-grid">
            <div class="stat-item devices"><div class="stat-number"><?= number_format($inventoryDevices) ?></div><div class="stat-label"><i class="fas fa-laptop"></i> Devices</div></div>
            <div class="stat-item monitors"><div class="stat-number"><?= number_format($inventoryMonitors) ?></div><div class="stat-label"><i class="fas fa-desktop"></i> Monitors</div></div>
            <div class="stat-item printers"><div class="stat-number"><?= number_format($inventoryPrinters) ?></div><div class="stat-label"><i class="fas fa-print"></i> Printers</div></div>
            <div class="stat-item rams"><div class="stat-number"><?= number_format($inventoryRams) ?></div><div class="stat-label"><i class="fas fa-memory"></i> RAMs</div></div>
            <div class="stat-item ssds"><div class="stat-number"><?= number_format($inventorySsds) ?></div><div class="stat-label"><i class="fas fa-hdd"></i> SSDs</div></div>
            <div class="stat-item chargers"><div class="stat-number"><?= number_format($inventoryChargers) ?></div><div class="stat-label"><i class="fas fa-bolt"></i> Chargers</div></div>
            <div class="stat-item accessories"><div class="stat-number"><?= number_format($inventoryAccessories) ?></div><div class="stat-label"><i class="fas fa-plug"></i> Accessories</div></div>
            <div class="stat-item smartboards"><div class="stat-number"><?= number_format($inventorySmartboards) ?></div><div class="stat-label"><i class="fas fa-chalkboard"></i> Smartboards</div></div>
            <!-- NEW ITEMS -->
            <div class="stat-item phones"><div class="stat-number"><?= number_format($inventoryPhones) ?></div><div class="stat-label"><i class="fas fa-mobile-alt"></i> Phones</div></div>
            <div class="stat-item ups"><div class="stat-number"><?= number_format($inventoryUps) ?></div><div class="stat-label"><i class="fas fa-battery-half"></i> UPS</div></div>
            <div class="stat-item hdds"><div class="stat-number"><?= number_format($inventoryHdds) ?></div><div class="stat-label"><i class="fas fa-database"></i> HDDs</div></div>
            <div class="stat-item graphics"><div class="stat-number"><?= number_format($inventoryGraphics) ?></div><div class="stat-label"><i class="fas fa-microchip"></i> Graphics</div></div>
        </div>
    </div>

    <!-- Top Selling Items & Categories -->
    <div class="three-column">
        <div class="section" style="margin-bottom:0">
            <div class="flex-between"><h4><i class="fas fa-fire" style="color: var(--accent);"></i> Top Selling Items</h4><a href="/inventory_system/reports/top_items.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a></div>
            <div class="table-responsive"><table class="table"><thead><tr><th>#</th><th>Item Name</th><th>Category</th><th>Qty</th><th>Revenue</th></tr></thead>
            <tbody><?php if(!empty($topSellingItems)): $i=1; foreach($topSellingItems as $item): ?><tr><td class="badge badge-primary" style="text-align:center; width:35px"><?= $i++ ?></td><td><?= htmlspecialchars(substr($item['item_name'], 0, 30)) ?></td><td><?= htmlspecialchars($item['category']) ?></td><td class="badge badge-info" style="text-align:center"><?= number_format($item['quantity_sold']) ?></td><td class="text-success">Ksh <?= number_format($item['revenue'], 0) ?></td></tr><?php endforeach; else: ?><tr><td colspan="5" class="text-muted">No sales data this month</td></tr><?php endif; ?></tbody></table></div>
        </div>
        <div class="section" style="margin-bottom:0">
            <div class="flex-between"><h4><i class="fas fa-chart-pie"></i> Top Categories</h4><a href="/inventory_system/reports/category_report.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a></div>
            <div class="categories-grid"><?php if(!empty($topCategories)): foreach($topCategories as $cat): ?><div class="category-card"><div class="category-count"><?= number_format($cat['count']) ?></div><div class="category-name"><?= htmlspecialchars($cat['category_name']) ?></div><div class="category-revenue">Ksh <?= number_format($cat['revenue'], 0) ?></div></div><?php endforeach; else: ?><div class="text-muted" style="text-align:center; padding:1rem;">No category data</div><?php endif; ?></div>
        </div>
        <div class="section" style="margin-bottom:0">
            <div class="flex-between"><h4><i class="fas fa-trophy" style="color: var(--accent);"></i> Top Sales People</h4><a href="/inventory_system/users/sales_team.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a></div>
            <div class="table-responsive"><table class="table"><thead><tr><th>#</th><th>Sales Person</th><th>Branch</th><th>Revenue</th></tr></thead>
            <tbody><?php if(!empty($topSalesPeople)): $i=1; foreach($topSalesPeople as $p): ?><tr><td class="badge badge-primary" style="text-align:center; width:35px"><?= $i++ ?></td><td><i class="fas fa-user"></i> <?= htmlspecialchars(substr($p['full_name'], 0, 15)) ?></td><td><?= htmlspecialchars($p['branch']) ?></td><td class="text-success">Ksh <?= number_format($p['total_revenue'], 0) ?></td></tr><?php endforeach; else: ?><tr><td colspan="4" class="text-muted">No sales data</td></tr><?php endif; ?></tbody></table></div>
        </div>
    </div>

    <!-- Recently Added Devices (only devices) -->
    <div class="full-width">
        <div class="section">
            <div class="flex-between"><h4><i class="fas fa-plus-circle"></i> Recently Added Devices</h4><a href="/inventory_system/devices/device_list.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a></div>
            <div class="table-responsive"><table class="table"><thead><tr><th>Serial</th><th>Model</th><th>Specs (RAM + Storage)</th><th>Status</th><th>Action</th></tr></thead>
            <tbody><?php if(!empty($recentAddedItems)): foreach($recentAddedItems as $d): ?><tr>
                <td><code><?= htmlspecialchars(substr($d['id'], 0, 12)) ?></code></td>
                <td><?= htmlspecialchars($d['item_name']) ?></td>
                <td><?= htmlspecialchars($d['ram'] ?? '-') ?>GB RAM, <?= htmlspecialchars($d['storage_type'] ?? '') ?> <?= htmlspecialchars($d['storage_capacity'] ?? '-') ?>GB</td>
                <td><?= $d['status'] == 'In Stock' ? '<span class="badge badge-success">In Stock</span>' : '<span class="badge badge-warning">Sold</span>' ?></td>
                <td><a href="/inventory_system/devices/view_device.php?sn=<?= urlencode($d['id']) ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
            </tr><?php endforeach; else: ?><tr><td colspan="5" class="text-muted">No recent devices</td></tr><?php endif; ?></tbody></table></div>
        </div>
    </div>

    <!-- Recently Sold Devices (only devices) -->
    <div class="full-width">
        <div class="section">
            <div class="flex-between">
                <h4><i class="fas fa-tags"></i> Recently Sold Devices</h4>
                <a href="/inventory_system/sales/sales_logs.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Serial</th>
                            <th>Model</th>
                            <th>Specs (RAM + Storage)</th>
                            <th>Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($recentSoldItems)): ?>
                            <?php foreach($recentSoldItems as $sold): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars(substr($sold['id'], 0, 12)) ?></code></td>
                                    <td><?= htmlspecialchars($sold['item_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($sold['ram'] ?? '-') ?>GB RAM, <?= htmlspecialchars($sold['storage_type'] ?? '') ?> <?= htmlspecialchars($sold['storage_capacity'] ?? '-') ?>GB</td>
                                    <td><span class="badge badge-success">Ksh <?= number_format($sold['price'] ?? 0, 0) ?></span></td>
                                    <td><a href="/inventory_system/devices/view_device.php?sn=<?= urlencode($sold['id']) ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-muted" style="text-align:center; padding: 2rem;">No recent sales</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Branch Sales & Low Stock -->
    <div class="three-column">
        <div class="section" style="margin-bottom:0">
            <h4><i class="fas fa-store"></i> Branch Sales (This Month)</h4>
            <div class="table-responsive"><table class="table"><thead><tr><th>Branch</th><th>Sales</th><th>Revenue</th></tr></thead>
            <tbody><?php if(!empty($branchSales)): foreach($branchSales as $branch): ?><tr><td><strong><?= htmlspecialchars($branch['branch']) ?></strong></td><td class="badge badge-info" style="text-align:center"><?= number_format($branch['sales_count']) ?></td><td class="text-success">Ksh <?= number_format($branch['total_revenue'], 0) ?></td></tr><?php endforeach; else: ?><tr><td colspan="3" class="text-muted">No branch data</td></tr><?php endif; ?></tbody></table></div>
        </div>
       <div class="section" style="margin-bottom: 0;">
    <div class="flex-between">
        <h4><i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Low Stock Items</h4>
        <a href="/inventory_system/reports/low_stock.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Branch</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($lowStockItems)): ?>
                    <?php foreach($lowStockItems as $item): ?>
                        <tr>
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
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-muted" style="text-align: center; padding: 1.5rem;">All stock levels are good</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
        <div class="section" style="margin-bottom:0">
            <h4><i class="fas fa-chart-simple"></i> Quick Stats</h4>
            <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                <div class="stat-item" style="padding: 0.75rem;"><div class="stat-number" style="font-size: 1.2rem;"><?= number_format($totalRepairs) ?></div><div class="stat-label">Total Repairs</div></div>
                <div class="stat-item" style="padding: 0.75rem;"><div class="stat-number" style="font-size: 1.2rem;"><?= number_format($completedRepairs) ?></div><div class="stat-label">Completed</div></div>
                <div class="stat-item" style="padding: 0.75rem;"><div class="stat-number" style="font-size: 1.2rem;"><?= number_format($pendingRepairs) ?></div><div class="stat-label">Pending</div></div>
                <div class="stat-item" style="padding: 0.75rem;"><div class="stat-number" style="font-size: 1.2rem;"><?= number_format($totalAccessoriesGiven) ?></div><div class="stat-label">Accessories Given</div></div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Logs -->
    <div class="section">
        <div class="flex-between"><h4><i class="fas fa-history"></i> Recent Activity Logs</h4><a href="/inventory_system/logs/activity.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>User</th><th>Action</th><th>Time</th></tr></thead>
        <tbody><?php foreach($recentActivities as $a): ?><tr><td><strong><?= htmlspecialchars($a['done_by_name'] ?? 'System') ?></strong></td><td><span class="badge badge-info"><?= htmlspecialchars($a['action'] ?? '') ?></span> <?= htmlspecialchars(substr($a['details'] ?? '', 0, 60)) ?></td><td><?= date('M j, H:i', strtotime($a['created_at'] ?? '')) ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>

    <!-- Bottom Buttons (All Blue) -->
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem;">
        <a href="/inventory_system/ram_ssd/add_ram.php" class="link-btn"><i class="fas fa-memory"></i> Add RAM/SSD</a>
        <a href="/inventory_system/chargers/add_charger.php" class="link-btn"><i class="fas fa-bolt"></i> Add Charger</a>
        <a href="/inventory_system/devices/add_device.php" class="link-btn"><i class="fas fa-plus-circle"></i> Add Device</a>
        <a href="/inventory_system/sales/sales_logs.php" class="link-btn"><i class="fas fa-chart-line"></i> Sales Report</a>
        <a href="/inventory_system/software/software_logs.php" class="link-btn"><i class="fas fa-code"></i> Software Report</a>
        <a href="/inventory_system/repairs/repair_logs.php" class="link-btn"><i class="fas fa-tools"></i> Repair Report</a>
    </div>

    <footer><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers. All rights reserved. <span style="margin:0 0.5rem">•</span> v2.0.0</footer>
</div>

<script>
let todayShown = false, monthShown = false;
const todayRevenueActual = <?= $todaysRevenue ?>;
const monthRevenueActual = <?= $monthlyRevenue ?>;

function toggleTodayRevenue() {
    const span = document.getElementById('todayRevenueValue');
    const btn = document.querySelector('.stat-card:nth-child(3) .toggle-btn');
    if (!todayShown) {
        span.innerHTML = 'Ksh ' + todayRevenueActual.toLocaleString();
        btn.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
        todayShown = true;
    } else {
        span.innerHTML = '••••••';
        btn.innerHTML = '<i class="fas fa-eye"></i> Show';
        todayShown = false;
    }
}

function toggleMonthRevenue() {
    const span = document.getElementById('monthRevenueValue');
    const btn = document.querySelector('.stat-card:nth-child(4) .toggle-btn');
    if (!monthShown) {
        span.innerHTML = 'Ksh ' + monthRevenueActual.toLocaleString();
        btn.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
        monthShown = true;
    } else {
        span.innerHTML = '••••••';
        btn.innerHTML = '<i class="fas fa-eye"></i> Show';
        monthShown = false;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    function adjustMobile() {
        const main = document.querySelector('.main-content');
        const sidebar = document.querySelector('.sidebar');
        if (window.innerWidth <= 1200) {
            if (main) { main.style.marginLeft = '0'; main.style.width = '100%'; main.style.paddingTop = '5rem'; }
        } else {
            if (main && sidebar) { main.style.marginLeft = '260px'; main.style.width = 'calc(100% - 260px)'; main.style.paddingTop = ''; }
        }
    }
    adjustMobile();
    window.addEventListener('resize', adjustMobile);
    window.addEventListener('sidebarToggled', adjustMobile);
});
</script>
</body>
</html>