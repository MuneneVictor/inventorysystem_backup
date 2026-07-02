<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";


// Enable debug mode to see SQL errors (set to false in production)
$debug = true;

// STRICT ROLE CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    die("ACCESS DENIED: You do not have permission to access this page.");
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');
$user_branch = $_SESSION['branch'] ?? null;

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

// ============================================================
// SECURE QUERY FUNCTION with positional placeholders
// ============================================================
function secureQuery($conn, $sql, $params = [], $debug = false) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        if ($debug) {
            echo "<pre style='color:red;'>SQL Error: " . $e->getMessage() . "\nSQL: $sql\nParams: " . print_r($params, true) . "</pre>";
        }
        error_log("Database Error: " . $e->getMessage() . " SQL: $sql");
        return false;
    }
}

// ============================================================
// TODAY'S SALES COUNT (positional placeholders)
// ============================================================
$todaysCount = 0;
$stmt = secureQuery($conn, "
    SELECT COUNT(*) FROM (
        SELECT 1 FROM devices WHERE status = 'Sold' AND DATE(sold_at) = CURDATE() AND branch = ? UNION ALL
        SELECT 1 FROM monitors WHERE status = 'Sold' AND DATE(sold_at) = CURDATE() AND branch = ? UNION ALL
        SELECT 1 FROM printers WHERE status = 'Sold' AND DATE(date_sold) = CURDATE() AND branch = ? UNION ALL
        SELECT 1 FROM smartboards WHERE status = 'sold' AND DATE(sold_at) = CURDATE() AND branch = ? UNION ALL
        SELECT 1 FROM sold_accessories WHERE DATE(date_sold) = CURDATE() AND branch = ? UNION ALL
        SELECT 1 FROM sold_chargers WHERE DATE(date_sold) = CURDATE() AND branch = ? UNION ALL
        SELECT 1 FROM phones WHERE status = 'sold' AND DATE(date_sold) = CURDATE() AND branch = ? UNION ALL
        SELECT 1 FROM ups WHERE status = 'sold' AND DATE(date_sold) = CURDATE() AND branch = ? UNION ALL
        SELECT 1 FROM sold_rams_ssds WHERE DATE(date_sold) = CURDATE() AND branch = ? UNION ALL
        SELECT 1 FROM sold_hdds WHERE DATE(date_sold) = CURDATE() AND branch = ? UNION ALL
        SELECT 1 FROM sold_graphics_cards WHERE DATE(date_sold) = CURDATE() AND branch = ?
    ) AS todays_sales
", array_fill(0, 11, $user_branch), $debug);
if ($stmt) $todaysCount = (int)$stmt->fetchColumn();

// ============================================================
// TODAY'S REVENUE
// ============================================================
$todaysRevenue = 0;
$stmt = secureQuery($conn, "
    SELECT COALESCE(SUM(price), 0) FROM (
        SELECT selling_price AS price, sold_at FROM devices WHERE status = 'Sold' AND branch = ? UNION ALL
        SELECT selling_price AS price, sold_at FROM monitors WHERE status = 'Sold' AND branch = ? UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM printers WHERE status = 'Sold' AND branch = ? UNION ALL
        SELECT selling_price AS price, sold_at FROM smartboards WHERE status = 'sold' AND branch = ? UNION ALL
        SELECT total_price AS price, date_sold AS sold_at FROM sold_accessories WHERE branch = ? UNION ALL
        SELECT total_price AS price, date_sold AS sold_at FROM sold_chargers WHERE branch = ? UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM phones WHERE status = 'sold' AND branch = ? UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM ups WHERE status = 'sold' AND branch = ? UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_rams_ssds WHERE branch = ? UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_hdds WHERE branch = ? UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_graphics_cards WHERE branch = ?
    ) AS today_prices
    WHERE DATE(sold_at) = CURDATE()
", array_fill(0, 11, $user_branch), $debug);
if ($stmt) $todaysRevenue = (float)$stmt->fetchColumn();

// ============================================================
// THIS WEEK REVENUE
// ============================================================
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));
$weeklyRevenue = 0;
$stmt = secureQuery($conn, "
    SELECT COALESCE(SUM(price), 0) FROM (
        SELECT selling_price AS price, sold_at FROM devices WHERE status = 'Sold' AND branch = ? UNION ALL
        SELECT selling_price AS price, sold_at FROM monitors WHERE status = 'Sold' AND branch = ? UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM printers WHERE status = 'Sold' AND branch = ? UNION ALL
        SELECT selling_price AS price, sold_at FROM smartboards WHERE status = 'sold' AND branch = ? UNION ALL
        SELECT total_price AS price, date_sold AS sold_at FROM sold_accessories WHERE branch = ? UNION ALL
        SELECT total_price AS price, date_sold AS sold_at FROM sold_chargers WHERE branch = ? UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM phones WHERE status = 'sold' AND branch = ? UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM ups WHERE status = 'sold' AND branch = ? UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_rams_ssds WHERE branch = ? UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_hdds WHERE branch = ? UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_graphics_cards WHERE branch = ?
    ) AS week_prices
    WHERE DATE(sold_at) BETWEEN ? AND ?
", array_merge(array_fill(0, 11, $user_branch), [$weekStart, $weekEnd]), $debug);
if ($stmt) $weeklyRevenue = (float)$stmt->fetchColumn();

// ============================================================
// MONTHLY REVENUE
// ============================================================
$monthlyRevenue = 0;
$stmt = secureQuery($conn, "
    SELECT COALESCE(SUM(price), 0) FROM (
        SELECT selling_price AS price, sold_at FROM devices WHERE status = 'Sold' AND branch = ? UNION ALL
        SELECT selling_price AS price, sold_at FROM monitors WHERE status = 'Sold' AND branch = ? UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM printers WHERE status = 'Sold' AND branch = ? UNION ALL
        SELECT selling_price AS price, sold_at FROM smartboards WHERE status = 'sold' AND branch = ? UNION ALL
        SELECT total_price AS price, date_sold AS sold_at FROM sold_accessories WHERE branch = ? UNION ALL
        SELECT total_price AS price, date_sold AS sold_at FROM sold_chargers WHERE branch = ? UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM phones WHERE status = 'sold' AND branch = ? UNION ALL
        SELECT selling_price AS price, date_sold AS sold_at FROM ups WHERE status = 'sold' AND branch = ? UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_rams_ssds WHERE branch = ? UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_hdds WHERE branch = ? UNION ALL
        SELECT (selling_price * quantity) AS price, date_sold AS sold_at FROM sold_graphics_cards WHERE branch = ?
    ) AS month_prices
    WHERE MONTH(sold_at) = MONTH(CURDATE()) AND YEAR(sold_at) = YEAR(CURDATE())
", array_fill(0, 11, $user_branch), $debug);
if ($stmt) $monthlyRevenue = (float)$stmt->fetchColumn();

// ============================================================
// TOP SELLING ITEMS TODAY
// ============================================================
$topProductsToday = [];
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
        AND DATE(sold_at) = CURDATE()
        AND branch = ?

        UNION ALL

        SELECT 
            model_name COLLATE utf8mb4_general_ci AS item_name,
            'Monitor' AS category,
            selling_price AS price
        FROM monitors
        WHERE status='Sold'
        AND DATE(sold_at) = CURDATE()
        AND branch = ?

        UNION ALL

        SELECT 
            model_name COLLATE utf8mb4_general_ci AS item_name,
            'Printer' AS category,
            selling_price AS price
        FROM printers
        WHERE status='Sold'
        AND DATE(date_sold) = CURDATE()
        AND branch = ?

        UNION ALL

        SELECT 
            model COLLATE utf8mb4_general_ci AS item_name,
            'Smartboard' AS category,
            selling_price AS price
        FROM smartboards
        WHERE status='sold'
        AND DATE(sold_at) = CURDATE()
        AND branch = ?

        UNION ALL

        SELECT 
            accessory_name COLLATE utf8mb4_general_ci AS item_name,
            'Accessory' AS category,
            total_price AS price
        FROM sold_accessories
        WHERE DATE(date_sold) = CURDATE()
        AND branch = ?

        UNION ALL

        SELECT 
            charger_type COLLATE utf8mb4_general_ci AS item_name,
            'Charger' AS category,
            total_price AS price
        FROM sold_chargers
        WHERE DATE(date_sold) = CURDATE()
        AND branch = ?

        UNION ALL
        SELECT 
            CONCAT(COALESCE(brand,''), ' ', COALESCE(model,'')) COLLATE utf8mb4_general_ci AS item_name,
            'Phone' AS category,
            selling_price AS price
        FROM phones
        WHERE status='sold'
        AND DATE(date_sold) = CURDATE()
        AND branch = ?

        UNION ALL
        SELECT 
            model COLLATE utf8mb4_general_ci AS item_name,
            'UPS' AS category,
            selling_price AS price
        FROM ups
        WHERE status='sold'
        AND DATE(date_sold) = CURDATE()
        AND branch = ?

        UNION ALL
        SELECT 
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage,''), 'GB') COLLATE utf8mb4_general_ci AS item_name,
            category AS category,
            selling_price * quantity AS price
        FROM sold_rams_ssds
        WHERE DATE(date_sold) = CURDATE()
        AND branch = ?

        UNION ALL
        SELECT 
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage,'')) COLLATE utf8mb4_general_ci AS item_name,
            'HDD' AS category,
            selling_price * quantity AS price
        FROM sold_hdds
        WHERE DATE(date_sold) = CURDATE()
        AND branch = ?

        UNION ALL
        SELECT 
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage_capacity,''), 'GB') COLLATE utf8mb4_general_ci AS item_name,
            'Graphics Card' AS category,
            selling_price * quantity AS price
        FROM sold_graphics_cards
        WHERE DATE(date_sold) = CURDATE()
        AND branch = ?

    ) AS all_sales_today
    GROUP BY item_name, category
    ORDER BY revenue DESC
    LIMIT 5
", array_fill(0, 11, $user_branch), $debug);
if ($stmt) {
    $topProductsToday = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// RECENT SALES FETCHER (updated to use ? placeholders)
// ============================================================
function fetchRecentSales($conn, $branch, $limit = 10, $debug = false) {
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
            WHERE d.status = 'Sold' AND d.branch = ?";
    $stmt = secureQuery($conn, $sql, [$branch], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 2. Monitors
    $sql = "SELECT m.model_name AS item_name, 'Monitor' AS category, 
                   m.serial_number AS id, m.selling_price AS price, 
                   m.sold_at, m.branch, m.sold_by, u.full_name AS sold_by_name,
                   CONCAT(m.size_inches, ' inch') AS specs
            FROM monitors m
            LEFT JOIN users u ON m.sold_by = u.id
            WHERE m.status = 'Sold' AND m.branch = ?";
    $stmt = secureQuery($conn, $sql, [$branch], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 3. Printers
    $sql = "SELECT p.model_name AS item_name, 'Printer' AS category, 
                   p.serial_number AS id, p.selling_price AS price, 
                   p.date_sold AS sold_at, p.branch, p.sold_by, u.full_name AS sold_by_name,
                   'N/A' AS specs
            FROM printers p
            LEFT JOIN users u ON p.sold_by = u.id
            WHERE p.status = 'Sold' AND p.branch = ?";
    $stmt = secureQuery($conn, $sql, [$branch], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 4. Smartboards
    $sql = "SELECT s.model AS item_name, 'Smartboard' AS category, 
                   s.serial_number AS id, s.selling_price AS price, 
                   s.sold_at, s.branch, s.sold_by, u.full_name AS sold_by_name,
                   CONCAT(s.model, ' | ', s.size_inches, ' inch') AS specs
            FROM smartboards s
            LEFT JOIN users u ON s.sold_by = u.id
            WHERE s.status = 'sold' AND s.branch = ?";
    $stmt = secureQuery($conn, $sql, [$branch], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 5. UPS
    $sql = "SELECT ups.model AS item_name, 'UPS' AS category, 
                   ups.serial_number AS id, ups.selling_price AS price, 
                   ups.date_sold AS sold_at, ups.branch, ups.sold_by, u.full_name AS sold_by_name,
                   CONCAT(ups.model, ' | ', ups.capacity, ' VA') AS specs
            FROM ups
            LEFT JOIN users u ON ups.sold_by = u.id
            WHERE ups.status = 'sold' AND ups.branch = ?";
    $stmt = secureQuery($conn, $sql, [$branch], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 6. Phones
    $sql = "SELECT CONCAT(COALESCE(p.brand,''), ' ', COALESCE(p.model,'')) AS item_name, 'Phone' AS category, 
                   p.serial_number AS id, p.selling_price AS price, 
                   p.date_sold AS sold_at, p.branch, p.sold_by, u.full_name AS sold_by_name,
                   CONCAT(COALESCE(p.brand,''), ' ', COALESCE(p.model,''), ' | ', p.ram, 'GB RAM | ', p.storage_capacity, 'GB') AS specs
            FROM phones p
            LEFT JOIN users u ON p.sold_by = u.id
            WHERE p.status = 'sold' AND p.branch = ?";
    $stmt = secureQuery($conn, $sql, [$branch], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 7. Sold Accessories
    $sql = "SELECT sa.accessory_name AS item_name, 'Accessory' AS category, 
                   NULL AS id, sa.total_price AS price, 
                   sa.date_sold AS sold_at, sa.branch, sa.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sa.quantity, ' x ', sa.selling_price, ' = ', sa.total_price) AS specs
            FROM sold_accessories sa
            LEFT JOIN users u ON sa.sold_by = u.id
            WHERE sa.branch = ?";
    $stmt = secureQuery($conn, $sql, [$branch], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 8. Sold Chargers
    $sql = "SELECT sc.charger_type AS item_name, 'Charger' AS category, 
                   NULL AS id, sc.total_price AS price, 
                   sc.date_sold AS sold_at, sc.branch, sc.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sc.quantity, ' x ', sc.charger_condition, ' | ', sc.selling_price, ' each') AS specs
            FROM sold_chargers sc
            LEFT JOIN users u ON sc.sold_by = u.id
            WHERE sc.branch = ?";
    $stmt = secureQuery($conn, $sql, [$branch], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 9. Sold Graphics Cards
    $sql = "SELECT CONCAT(COALESCE(sgc.type,''), ' ', COALESCE(sgc.storage_capacity,''), 'GB') AS item_name, 'Graphics Card' AS category, 
                   NULL AS id, sgc.total_price AS price, 
                   sgc.date_sold AS sold_at, sgc.branch, sgc.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sgc.quantity, ' x ', sgc.type, ' ', sgc.storage_capacity, 'GB') AS specs
            FROM sold_graphics_cards sgc
            LEFT JOIN users u ON sgc.sold_by = u.id
            WHERE sgc.branch = ?";
    $stmt = secureQuery($conn, $sql, [$branch], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 10. Sold HDDs
    $sql = "SELECT CONCAT(COALESCE(sh.type,''), ' ', COALESCE(sh.storage,'')) AS item_name, 'HDD' AS category, 
                   NULL AS id, sh.total_price AS price, 
                   sh.date_sold AS sold_at, sh.branch, sh.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sh.quantity, ' x ', sh.type, ' ', sh.storage) AS specs
            FROM sold_hdds sh
            LEFT JOIN users u ON sh.sold_by = u.id
            WHERE sh.branch = ?";
    $stmt = secureQuery($conn, $sql, [$branch], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // 11. Sold RAM/SSD
    $sql = "SELECT CONCAT(COALESCE(srs.type,''), ' ', COALESCE(srs.storage,''), 'GB') AS item_name, srs.category AS category, 
                   NULL AS id, srs.total_price AS price, 
                   srs.date_sold AS sold_at, srs.branch, srs.sold_by, u.full_name AS sold_by_name,
                   CONCAT(srs.quantity, ' x ', srs.type, ' ', srs.storage, 'GB') AS specs
            FROM sold_rams_ssds srs
            LEFT JOIN users u ON srs.sold_by = u.id
            WHERE srs.branch = ?";
    $stmt = secureQuery($conn, $sql, [$branch], $debug);
    if ($stmt) $allSales = array_merge($allSales, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Sort by sold_at descending
    usort($allSales, function($a, $b) {
        $ta = $a['sold_at'] ? strtotime($a['sold_at']) : 0;
        $tb = $b['sold_at'] ? strtotime($b['sold_at']) : 0;
        return $tb - $ta;
    });

    return array_slice($allSales, 0, $limit);
}

// ============================================================
// RECENT SALES
// ============================================================
$recentSales = fetchRecentSales($conn, $user_branch, 10, $debug);

// ============================================================
// ACTIVE SALES
// ============================================================
$activeSales = [];
$stmt = secureQuery($conn, "
    SELECT 
        s.id,
        s.client_name,
        s.client_phone,
        s.total_amount,
        s.created_at,
        s.sold_by,
        u.full_name AS sold_by_name
    FROM sales s
    LEFT JOIN users u ON s.sold_by = u.id
    WHERE s.sale_status = 'active'
    AND u.branch = ?
    ORDER BY s.created_at DESC
    LIMIT 20
", [$user_branch], $debug);
if ($stmt) {
    $activeSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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
    /* (CSS unchanged – keep your existing styles) */
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

    .branch-badge {
        background: var(--primary);
        color: white;
        padding: 0.25rem 1rem;
        border-radius: 9999px;
        font-size: 0.85rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    /* Compact stat cards row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: var(--radius-xl);
        padding: 1.25rem;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }
    .stat-card:nth-child(1)::before { background: linear-gradient(90deg, var(--success), #34d399); }
    .stat-card:nth-child(2)::before { background: linear-gradient(90deg, var(--info), #60a5fa); }
    .stat-card:nth-child(3)::before { background: linear-gradient(90deg, var(--accent), #fbbf24); }
    .stat-card:nth-child(4)::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }
    .stat-card .stat-icon {
        font-size: 2rem;
        margin-bottom: 0.75rem;
    }
    .stat-card .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }
    .stat-card .stat-label {
        font-size: 0.8rem;
        color: var(--gray-500);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .stat-card .stat-sub {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 0.25rem;
    }
    .stat-card:nth-child(1) .stat-icon,
    .stat-card:nth-child(1) .stat-value { color: var(--success); }
    .stat-card:nth-child(2) .stat-icon,
    .stat-card:nth-child(2) .stat-value { color: var(--info); }
    .stat-card:nth-child(3) .stat-icon,
    .stat-card:nth-child(3) .stat-value { color: var(--accent); }
    .stat-card:nth-child(4) .stat-icon,
    .stat-card:nth-child(4) .stat-value { color: #8b5cf6; }

    .toggle-btn {
        background: none;
        border: 1px solid var(--gray-300);
        padding: 0.25rem 0.6rem;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 0.5rem;
        transition: all 0.2s;
    }
    .toggle-btn:hover {
        background: var(--gray-100);
        border-color: var(--primary);
        color: var(--primary);
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
    .badge-info {
        background: #dbeafe;
        color: #1e40af;
    }
    .badge-active {
        background: #dbeafe;
        color: #1e40af;
    }
    .badge-success {
        background: #d1fae5;
        color: #065f46;
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
    .btn-outline {
        background: transparent;
        border: 2px solid var(--primary);
        color: var(--primary) !important;
    }
    .btn-outline:hover {
        background: var(--primary);
        color: white !important;
    }

    .flex-between {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
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
    .text-success { color: var(--success); }

    /* Two‑column grid for desktop */
    .dashboard-grid-2col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .actions-row {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
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
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        .dashboard-grid-2col {
            grid-template-columns: 1fr;
            gap: 1rem;
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
        .stats-row {
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }
        .stat-card .stat-value {
            font-size: 1.4rem;
        }
        .table td,
        .table th {
            padding: 0.5rem !important;
        }
        .table {
            min-width: 450px;
        }
        .dashboard-grid-2col {
            grid-template-columns: 1fr;
            gap: 0.75rem;
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
    }
    </style>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
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

    <!-- Stats Cards Row -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-value"><?= number_format($todaysCount) ?></div>
            <div class="stat-label">Today's Sales</div>
            <div class="stat-sub">Items sold today</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-value" id="todayRevenueValue">••••••</div>
            <div class="stat-label">Today's Revenue</div>
            <button class="toggle-btn" onclick="toggleTodayRevenue()"><i class="fas fa-eye"></i> Show</button>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
            <div class="stat-value" id="weekRevenueValue">••••••</div>
            <div class="stat-label">This Week</div>
            <button class="toggle-btn" onclick="toggleWeekRevenue()"><i class="fas fa-eye"></i> Show</button>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-value" id="monthRevenueValue">••••••</div>
            <div class="stat-label">Monthly Revenue</div>
            <button class="toggle-btn" onclick="toggleMonthRevenue()"><i class="fas fa-eye"></i> Show</button>
        </div>
    </div>

    <!-- Top Selling Items Today & Active Sales (two‑column grid) -->
    <div class="dashboard-grid-2col">
        <div class="section" style="margin-bottom: 0;">
            <div class="flex-between">
                <h4><i class="fas fa-fire" style="color: var(--accent);"></i> Top Selling Items Today</h4>
                <a href="/inventory_system/reports/top_items.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>#</th><th>Item</th><th>Category</th><th>Units</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <?php if(!empty($topProductsToday)): $i=1; foreach($topProductsToday as $item): ?>
                        <tr>
                            <td style="text-align:center; width:35px;"><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($item['category']) ?></span></td>
                            <td><span class="badge badge-primary"><?= (int)$item['quantity_sold'] ?></span></td>
                            <td class="text-success">KES <?= number_format($item['revenue'], 0) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-muted">No sales today</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section" style="margin-bottom: 0;">
            <div class="flex-between">
                <h4><i class="fas fa-clock" style="color: var(--warning);"></i> Active Sales</h4>
                <a href="/inventory_system/sales/active_sales.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>#</th><th>Client</th><th>Phone</th><th>Amount</th><th>Sales Person</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php if(!empty($activeSales)): $i=1; foreach($activeSales as $sale): ?>
                        <tr>
                            <td style="text-align:center; width:35px;"><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($sale['client_name'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars($sale['client_phone'] ?? '-') ?></td>
                            <td class="text-success">KES <?= number_format($sale['total_amount'] ?? 0, 0) ?></td>
                            <td><i class="fas fa-user" style="margin-right: 0.25rem; color: var(--gray-400);"></i><?= htmlspecialchars($sale['sold_by_name'] ?? '-') ?></td>
                            <td><?= date('M j, Y H:i', strtotime($sale['created_at'] ?? '')) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-muted">No active sales</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Sales Transactions (full width) -->
    <div class="section">
        <div class="flex-between">
            <h4><i class="fas fa-history"></i> Recent Sales Transactions</h4>
            <a href="/inventory_system/sales/sales_logs.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>#</th><th>Item</th><th>Category</th><th>Sold By</th><th>Price</th><th>Date & Time</th></tr></thead>
                <tbody>
                    <?php if(!empty($recentSales)): $i=1; foreach($recentSales as $sale): ?>
                    <tr>
                        <td style="text-align:center; width:35px;"><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($sale['item_name'] ?? '-') ?></strong></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($sale['category'] ?? '-') ?></span></td>
                        <td><i class="fas fa-user" style="margin-right: 0.25rem; color: var(--gray-400);"></i><?= htmlspecialchars($sale['sold_by_name'] ?? '-') ?></td>
                        <td class="text-success">KES <?= number_format($sale['price'] ?? 0, 0) ?></td>
                        <td><?= date('M j, Y H:i', strtotime($sale['sold_at'] ?? '')) ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6" class="text-muted">No recent sales found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Action Buttons -->
    <div class="actions-row">
        <a href="/inventory_system/sales/make_sale.php" class="link-btn">
            <i class="fas fa-cash-register"></i> Process New Sale
        </a>
        <a href="/inventory_system/sales/view_clients.php?search=" class="link-btn btn-outline">
            <i class="fas fa-search"></i> Find Client
        </a>
        <a href="/inventory_system/reports/daily_report.php" class="link-btn btn-outline">
            <i class="fas fa-chart-bar"></i> View Daily Report
        </a>
        <a href="/inventory_system/sales/sales_logs.php" class="link-btn btn-outline">
            <i class="fas fa-chart-line"></i> Sales Logs
        </a>
    </div>

    <footer>
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers. All rights reserved. 
        <span style="margin: 0 0.5rem;">•</span> 
        <span>v2.0.0</span>
    </footer>
</div>

<script>
let todayShown = false, weekShown = false, monthShown = false;
const todayRevenueActual = <?= $todaysRevenue ?>;
const weekRevenueActual = <?= $weeklyRevenue ?>;
const monthRevenueActual = <?= $monthlyRevenue ?>;

function toggleTodayRevenue() {
    const span = document.getElementById('todayRevenueValue');
    const btn = document.querySelector('.stat-card:nth-child(2) .toggle-btn');
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

function toggleWeekRevenue() {
    const span = document.getElementById('weekRevenueValue');
    const btn = document.querySelector('.stat-card:nth-child(3) .toggle-btn');
    if (!weekShown) {
        span.innerHTML = 'KES ' + weekRevenueActual.toLocaleString();
        btn.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
        weekShown = true;
    } else {
        span.innerHTML = '••••••';
        btn.innerHTML = '<i class="fas fa-eye"></i> Show';
        weekShown = false;
    }
}

function toggleMonthRevenue() {
    const span = document.getElementById('monthRevenueValue');
    const btn = document.querySelector('.stat-card:nth-child(4) .toggle-btn');
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

document.addEventListener('DOMContentLoaded', function() {
    function adjustMobile() {
        const main = document.querySelector('.main-content');
        const sidebar = document.querySelector('.sidebar');
        if (window.innerWidth <= 1200) {
            if (main) {
                main.style.marginLeft = '0';
                main.style.width = '100%';
                main.style.paddingTop = '5rem';
            }
            if (sidebar && !sidebar.classList.contains('active')) {
                document.body.style.overflow = 'auto';
            }
        } else {
            if (main && sidebar) {
                main.style.marginLeft = '260px';
                main.style.width = 'calc(100% - 260px)';
                main.style.paddingTop = '';
            }
        }
    }
    adjustMobile();
    window.addEventListener('resize', adjustMobile);
    window.addEventListener('orientationchange', function() {
        setTimeout(adjustMobile, 100);
    });
    window.addEventListener('sidebarToggled', adjustMobile);
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