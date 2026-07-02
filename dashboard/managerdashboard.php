<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// STRICT ROLE CHECK - Die immediately if not manager
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    die("ACCESS DENIED: You do not have permission to access this page.");
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// SECURE QUERY FUNCTION (same as Super Admin)
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

// ========== HELPER: Unified sales data (copied from Super Admin) ==========
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

// ========== RECENT SALES FETCHER – ALL BRANCHES (no branch filter) ==========
function fetchRecentSalesAllBranches($conn, $limit = 10, $debug = false) {
    $allSales = [];

    // 1. Devices
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
    $stmt = secureQuery($conn, $sql, [], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 2. Monitors
    $sql = "SELECT m.model_name AS item_name, 'Monitor' AS category, 
                   m.serial_number AS id, m.selling_price AS price, 
                   m.sold_at, m.branch, m.sold_by, u.full_name AS sold_by_name,
                   CONCAT(m.size_inches, ' inch') AS specs
            FROM monitors m
            LEFT JOIN users u ON m.sold_by = u.id
            WHERE m.status = 'Sold'";
    $stmt = secureQuery($conn, $sql, [], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 3. Printers
    $sql = "SELECT p.model_name AS item_name, 'Printer' AS category, 
                   p.serial_number AS id, p.selling_price AS price, 
                   p.date_sold AS sold_at, p.branch, p.sold_by, u.full_name AS sold_by_name,
                   'N/A' AS specs
            FROM printers p
            LEFT JOIN users u ON p.sold_by = u.id
            WHERE p.status = 'Sold'";
    $stmt = secureQuery($conn, $sql, [], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 4. Smartboards
    $sql = "SELECT s.model AS item_name, 'Smartboard' AS category, 
                   s.serial_number AS id, s.selling_price AS price, 
                   s.sold_at, s.branch, s.sold_by, u.full_name AS sold_by_name,
                   CONCAT(s.model, ' | ', s.size_inches, ' inch') AS specs
            FROM smartboards s
            LEFT JOIN users u ON s.sold_by = u.id
            WHERE s.status = 'sold'";
    $stmt = secureQuery($conn, $sql, [], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 5. UPS
    $sql = "SELECT ups.model AS item_name, 'UPS' AS category, 
                   ups.serial_number AS id, ups.selling_price AS price, 
                   ups.date_sold AS sold_at, ups.branch, ups.sold_by, u.full_name AS sold_by_name,
                   CONCAT(ups.model, ' | ', ups.capacity, ' VA') AS specs
            FROM ups
            LEFT JOIN users u ON ups.sold_by = u.id
            WHERE ups.status = 'sold'";
    $stmt = secureQuery($conn, $sql, [], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 6. Phones
    $sql = "SELECT CONCAT(COALESCE(p.brand,''), ' ', COALESCE(p.model,'')) AS item_name, 'Phone' AS category, 
                   p.serial_number AS id, p.selling_price AS price, 
                   p.date_sold AS sold_at, p.branch, p.sold_by, u.full_name AS sold_by_name,
                   CONCAT(COALESCE(p.brand,''), ' ', COALESCE(p.model,''), ' | ', p.ram, 'GB RAM | ', p.storage_capacity, 'GB') AS specs
            FROM phones p
            LEFT JOIN users u ON p.sold_by = u.id
            WHERE p.status = 'sold'";
    $stmt = secureQuery($conn, $sql, [], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 7. Sold Accessories
    $sql = "SELECT sa.accessory_name AS item_name, 'Accessory' AS category, 
                   NULL AS id, sa.total_price AS price, 
                   sa.date_sold AS sold_at, sa.branch, sa.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sa.quantity, ' x ', sa.selling_price, ' = ', sa.total_price) AS specs
            FROM sold_accessories sa
            LEFT JOIN users u ON sa.sold_by = u.id";
    $stmt = secureQuery($conn, $sql, [], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 8. Sold Chargers
    $sql = "SELECT sc.charger_type AS item_name, 'Charger' AS category, 
                   NULL AS id, sc.total_price AS price, 
                   sc.date_sold AS sold_at, sc.branch, sc.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sc.quantity, ' x ', sc.charger_condition, ' | ', sc.selling_price, ' each') AS specs
            FROM sold_chargers sc
            LEFT JOIN users u ON sc.sold_by = u.id";
    $stmt = secureQuery($conn, $sql, [], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 9. Sold Graphics Cards
    $sql = "SELECT CONCAT(COALESCE(sgc.type,''), ' ', COALESCE(sgc.storage_capacity,''), 'GB') AS item_name, 'Graphics Card' AS category, 
                   NULL AS id, sgc.total_price AS price, 
                   sgc.date_sold AS sold_at, sgc.branch, sgc.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sgc.quantity, ' x ', sgc.type, ' ', sgc.storage_capacity, 'GB') AS specs
            FROM sold_graphics_cards sgc
            LEFT JOIN users u ON sgc.sold_by = u.id";
    $stmt = secureQuery($conn, $sql, [], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 10. Sold HDDs
    $sql = "SELECT CONCAT(COALESCE(sh.type,''), ' ', COALESCE(sh.storage,'')) AS item_name, 'HDD' AS category, 
                   NULL AS id, sh.total_price AS price, 
                   sh.date_sold AS sold_at, sh.branch, sh.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sh.quantity, ' x ', sh.type, ' ', sh.storage) AS specs
            FROM sold_hdds sh
            LEFT JOIN users u ON sh.sold_by = u.id";
    $stmt = secureQuery($conn, $sql, [], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 11. Sold RAM/SSD
    $sql = "SELECT CONCAT(COALESCE(srs.type,''), ' ', COALESCE(srs.storage,''), 'GB') AS item_name, srs.category AS category, 
                   NULL AS id, srs.total_price AS price, 
                   srs.date_sold AS sold_at, srs.branch, srs.sold_by, u.full_name AS sold_by_name,
                   CONCAT(srs.quantity, ' x ', srs.type, ' ', srs.storage, 'GB') AS specs
            FROM sold_rams_ssds srs
            LEFT JOIN users u ON srs.sold_by = u.id";
    $stmt = secureQuery($conn, $sql, [], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Sort by sold_at descending
    usort($allSales, function($a, $b) {
        $ta = $a['sold_at'] ? strtotime($a['sold_at']) : 0;
        $tb = $b['sold_at'] ? strtotime($b['sold_at']) : 0;
        return $tb - $ta;
    });

    return array_slice($allSales, 0, $limit);
}

// ========== STATISTICS (all branches) ==========
// Total devices & in stock
$stmt = secureQuery($conn, "SELECT COUNT(*) FROM devices");
$totalDevices = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COUNT(*) FROM devices WHERE status = 'In Stock'");
$totalInStock = $stmt ? (int)$stmt->fetchColumn() : 0;

// Today's sales count (unified)
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

// Today's expenses
$todaysExpenses = 0;
$stmt = secureQuery($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM expenses WHERE DATE(expense_date) = CURDATE()");
if ($stmt) $todaysExpenses = (float)$stmt->fetchColumn();

// ========== SALES TREND (LAST 7 DAYS) ==========
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

// ========== TOP SELLING ITEMS (CURRENT MONTH) ==========
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
if ($stmt) $topSellingItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== TOP CATEGORIES (CURRENT MONTH) ==========
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
if ($stmt) $topCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== BRANCH SALES (CURRENT MONTH) ==========
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
if ($stmt) $branchSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== RECENT SALES (ALL BRANCHES) ==========
$recentSales = fetchRecentSalesAllBranches($conn, 10, false);

// ========== REPAIR STATISTICS ==========
$stmt = secureQuery($conn, "SELECT COUNT(*) FROM repairs");
$totalRepairs = $stmt ? (int)$stmt->fetchColumn() : 0;

$stmt = secureQuery($conn, "SELECT COUNT(*) FROM repairs WHERE fix_status = 'Not Fixed'");
$pendingRepairs = $stmt ? (int)$stmt->fetchColumn() : 0;

$completedRepairs = $totalRepairs - $pendingRepairs;

// ========== GREETING ==========
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
    <title>Manager Dashboard | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ===== SAME STYLES AS SUPER ADMIN DASHBOARD ===== */
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
            .chart-bars { height: 120px; gap: 0.25rem; }
            .chart-bar-wrapper { min-width: 30px; }
            .chart-value { font-size: 0.55rem; white-space: normal; }
            .chart-label { font-size: 0.55rem; }
            .welcome-text { font-size: 0.9rem; max-width: 70%; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .categories-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .three-column { grid-template-columns: 1fr; gap: 0.75rem; }
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
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="header-row">
        <div>
            <div class="page-title">Manager Dashboard</div>
            <div class="welcome-text"><i class="fas fa-hand-wave" style="color: var(--accent);"></i> <?= $greeting ?>, <?= htmlspecialchars(explode(' ', $user_name)[0]) ?> • <?= date('l, F j, Y') ?></div>
        </div>
        <div class="logo"><img src="/inventory_system/assets/MC-LOGO.png" alt="Mombasa Computers" onerror="this.style.display='none'"></div>
        <div><a href="/inventory_system/dashboard/managerdashboard.php" class="link-btn"><i class="fas fa-sync-alt"></i> Refresh</a></div>
    </div>

    <!-- Stats Cards Row -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-value"><?= number_format($todaysSalesCount) ?></div>
            <div class="stat-label">Today's Sales</div>
            <div class="stat-sub">All branches</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
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
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="stat-value" id="expensesValue">••••••</div>
            <div class="stat-label">Today's Expenses</div>
            <button class="toggle-btn" onclick="toggleExpenses()"><i class="fas fa-eye"></i> Show</button>
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

    <!-- Top Selling Items & Categories & Branch Sales -->
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
            <h4><i class="fas fa-store"></i> Branch Sales (This Month)</h4>
            <div class="table-responsive"><table class="table"><thead><tr><th>Branch</th><th>Sales</th><th>Revenue</th></tr></thead>
            <tbody><?php if(!empty($branchSales)): foreach($branchSales as $branch): ?><tr><td><strong><?= htmlspecialchars($branch['branch']) ?></strong></td><td class="badge badge-info" style="text-align:center"><?= number_format($branch['sales_count']) ?></td><td class="text-success">Ksh <?= number_format($branch['total_revenue'], 0) ?></td></tr><?php endforeach; else: ?><tr><td colspan="3" class="text-muted">No branch data</td></tr><?php endif; ?></tbody></table></div>
        </div>
    </div>

    <!-- Recently Sold Items -->
    <div class="full-width">
        <div class="section">
            <div class="flex-between">
                <h4><i class="fas fa-tags"></i> Recently Sold Items</h4>
                <a href="/inventory_system/sales/sales_logs.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Item</th><th>Category</th><th>Sold By</th><th>Price</th><th>Branch</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php if(!empty($recentSales)): foreach($recentSales as $sale): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($sale['item_name'] ?? '-') ?></strong></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($sale['category'] ?? '-') ?></span></td>
                            <td><i class="fas fa-user"></i> <?= htmlspecialchars($sale['sold_by_name'] ?? '-') ?></td>
                            <td class="text-success">Ksh <?= number_format($sale['price'] ?? 0, 0) ?></td>
                            <td><?= htmlspecialchars($sale['branch'] ?? '-') ?></td>
                            <td><?= date('M j, Y H:i', strtotime($sale['sold_at'] ?? '')) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-muted" style="text-align:center; padding:2rem;">No recent sales found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Stats & Quick Actions -->
    <div class="three-column">
        <div class="section" style="margin-bottom:0">
            <h4><i class="fas fa-chart-simple"></i> Quick Stats</h4>
            <div class="stats-grid" style="grid-template-columns: repeat(2,1fr); gap:0.75rem;">
                <div class="stat-item" style="padding:0.75rem;"><div class="stat-number" style="font-size:1.2rem;"><?= number_format($totalDevices) ?></div><div class="stat-label">Total Devices</div></div>
                <div class="stat-item" style="padding:0.75rem;"><div class="stat-number" style="font-size:1.2rem;"><?= number_format($totalInStock) ?></div><div class="stat-label">In Stock</div></div>
                <div class="stat-item" style="padding:0.75rem;"><div class="stat-number" style="font-size:1.2rem;"><?= number_format($totalRepairs) ?></div><div class="stat-label">Total Repairs</div></div>
                <div class="stat-item" style="padding:0.75rem;"><div class="stat-number" style="font-size:1.2rem;"><?= number_format($pendingRepairs) ?></div><div class="stat-label">Pending</div></div>
            </div>
        </div>
        <div class="section" style="margin-bottom:0">
            <h4><i class="fas fa-bolt"></i> Quick Actions</h4>
            <div style="display:flex; flex-direction:column; gap:0.75rem; margin-top:0.5rem;">
                <a href="/inventory_system/devices/add_device.php" class="link-btn" style="justify-content:center;"><i class="fas fa-plus-circle"></i> Add Device</a>
                <a href="/inventory_system/ram_ssd/add_ram.php" class="link-btn" style="justify-content:center;"><i class="fas fa-memory"></i> Add RAM/SSD</a>
                <a href="/inventory_system/chargers/add_charger.php" class="link-btn" style="justify-content:center;"><i class="fas fa-bolt"></i> Add Charger</a>
                <a href="/inventory_system/sales/sales_logs.php" class="link-btn" style="justify-content:center;"><i class="fas fa-chart-line"></i> Sales Report</a>
            </div>
        </div>
        <div class="section" style="margin-bottom:0">
            <h4><i class="fas fa-info-circle"></i> System Info</h4>
            <div style="margin-top:0.5rem; font-size:0.85rem; color:var(--gray-600);">
                <p><i class="fas fa-calendar-check" style="color:var(--primary);"></i> <?= date('l, F j, Y') ?></p>
                <p><i class="fas fa-clock" style="color:var(--primary);"></i> <?= date('g:i A') ?></p>
                <p><i class="fas fa-user-shield" style="color:var(--primary);"></i> Manager</p>
                <p><i class="fas fa-store-alt" style="color:var(--primary);"></i> All Branches</p>
            </div>
        </div>
    </div>

    <footer>
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers. All rights reserved. 
        <span style="margin:0 0.5rem">•</span> v2.0.0
    </footer>
</div>

<script>
let todayShown = false, monthShown = false, expensesShown = false;
const todayRevenueActual = <?= $todaysRevenue ?>;
const monthRevenueActual = <?= $monthlyRevenue ?>;
const expensesActual = <?= $todaysExpenses ?>;

function toggleTodayRevenue() {
    const span = document.getElementById('todayRevenueValue');
    const btn = document.querySelector('.stat-card:nth-child(2) .toggle-btn');
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
    const btn = document.querySelector('.stat-card:nth-child(3) .toggle-btn');
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

function toggleExpenses() {
    const span = document.getElementById('expensesValue');
    const btn = document.querySelector('.stat-card:nth-child(4) .toggle-btn');
    if (!expensesShown) {
        span.innerHTML = 'Ksh ' + expensesActual.toLocaleString();
        btn.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
        expensesShown = true;
    } else {
        span.innerHTML = '••••••';
        btn.innerHTML = '<i class="fas fa-eye"></i> Show';
        expensesShown = false;
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

<?php require_once "../includes/footer.php"; ?>
</body>
</html>