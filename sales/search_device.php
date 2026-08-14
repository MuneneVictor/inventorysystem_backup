<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

if (!in_array($user_role, ['sales', 'super_admin', 'inventory_admin', 'manager', 'cashier'])) {
    die("ACCESS DENIED.");
}

$search_sn = trim($_GET['sn'] ?? '');
$search_model = trim($_GET['model'] ?? '');
$searched = ($search_sn || $search_model);
$ajax = isset($_GET['ajax']);

$allResults = [];

if ($searched) {
    // Helper to add a result item
    function addResult(&$allResults, $type, $id, $name, $branch, $quantity, $price, $specs, $viewLink = null) {
        $allResults[] = [
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'branch' => $branch,
            'quantity' => (int)$quantity,
            'price' => $price,
            'specs' => $specs,
            'view' => $viewLink,
        ];
    }

    // Define allowed types for "View" button
    $viewableTypes = ['Device', 'Smartboard', 'Monitor', 'Printer', 'Phone', 'UPS'];

    // 1. Devices
    $sql = "SELECT d.serial_number, d.model_name, d.branch, 1 AS quantity, d.price, d.selling_price,
                   d.processor, d.ram, d.storage_type, d.storage_capacity, d.graphics, d.touch
            FROM devices d WHERE d.status = 'In Stock'";
    $params = [];
    if ($search_sn) { $sql .= " AND d.serial_number LIKE ?"; $params[] = "%$search_sn%"; }
    if ($search_model) { $sql .= " AND d.model_name LIKE ?"; $params[] = "%$search_model%"; }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $specs = trim(
            ($row['processor'] ?? '') . ' | ' .
            ($row['ram'] ? $row['ram'] . 'GB RAM' : '') .
            ($row['storage_type'] && $row['storage_capacity'] ? ' | ' . $row['storage_type'] . ' ' . $row['storage_capacity'] . 'GB' : '') .
            ($row['graphics'] ? ' | ' . $row['graphics'] : '') .
            ($row['touch'] && $row['touch'] != 'N/A' ? ' | ' . $row['touch'] : '')
        );
        if (empty($specs)) $specs = '-';
        $viewLink = in_array('Device', $viewableTypes) ? '../devices/view_device.php?sn=' . urlencode($row['serial_number']) : null;
        addResult($allResults, 'Device', $row['serial_number'], $row['model_name'], $row['branch'], 1, $row['price'] ?? null, $specs, $viewLink);
    }

    // 2. Monitors
    $sql = "SELECT m.serial_number, m.model_name, m.branch, 1 AS quantity, m.price, m.selling_price, m.size_inches
            FROM monitors m WHERE m.status = 'In Stock'";
    $params = [];
    if ($search_sn) { $sql .= " AND m.serial_number LIKE ?"; $params[] = "%$search_sn%"; }
    if ($search_model) { $sql .= " AND m.model_name LIKE ?"; $params[] = "%$search_model%"; }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $specs = ($row['size_inches'] ?? '') ? $row['size_inches'] . ' inch' : '-';
        $viewLink = in_array('Monitor', $viewableTypes) ? '../monitors/view_monitor.php?sn=' . urlencode($row['serial_number']) : null;
        addResult($allResults, 'Monitor', $row['serial_number'], $row['model_name'], $row['branch'], 1, $row['price'] ?? null, $specs, $viewLink);
    }

    // 3. Printers
    $sql = "SELECT p.serial_number, p.model_name, p.branch, 1 AS quantity, p.price, p.selling_price
            FROM printers p WHERE p.status = 'In Stock'";
    $params = [];
    if ($search_sn) { $sql .= " AND p.serial_number LIKE ?"; $params[] = "%$search_sn%"; }
    if ($search_model) { $sql .= " AND p.model_name LIKE ?"; $params[] = "%$search_model%"; }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $specs = 'N/A';
        $viewLink = in_array('Printer', $viewableTypes) ? '../printers/view_printer.php?sn=' . urlencode($row['serial_number']) : null;
        addResult($allResults, 'Printer', $row['serial_number'], $row['model_name'], $row['branch'], 1, $row['price'] ?? null, $specs, $viewLink);
    }

    // 4. Smartboards
    $sql = "SELECT s.serial_number, s.model, s.branch, 1 AS quantity, s.price, s.selling_price, s.size_inches
            FROM smartboards s WHERE s.status = 'instock'";
    $params = [];
    if ($search_sn) { $sql .= " AND s.serial_number LIKE ?"; $params[] = "%$search_sn%"; }
    if ($search_model) { $sql .= " AND s.model LIKE ?"; $params[] = "%$search_model%"; }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $specs = ($row['model'] ?? '') . ($row['size_inches'] ? ' | ' . $row['size_inches'] . ' inch' : '');
        if (empty($specs)) $specs = '-';
        $viewLink = in_array('Smartboard', $viewableTypes) ? '../smartboards/view_smartboard.php?sn=' . urlencode($row['serial_number']) : null;
        addResult($allResults, 'Smartboard', $row['serial_number'], $row['model'], $row['branch'], 1, $row['price'] ?? null, $specs, $viewLink);
    }

    // 5. UPS
    $sql = "SELECT u.serial_number, u.model, u.branch, 1 AS quantity, u.price, u.selling_price, u.capacity
            FROM ups u WHERE u.status = 'instock'";
    $params = [];
    if ($search_sn) { $sql .= " AND u.serial_number LIKE ?"; $params[] = "%$search_sn%"; }
    if ($search_model) { $sql .= " AND u.model LIKE ?"; $params[] = "%$search_model%"; }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $specs = ($row['model'] ?? '') . ($row['capacity'] ? ' | ' . $row['capacity'] . ' VA' : '');
        if (empty($specs)) $specs = '-';
        $viewLink = in_array('UPS', $viewableTypes) ? '../ups/view_ups.php?sn=' . urlencode($row['serial_number']) : null;
        addResult($allResults, 'UPS', $row['serial_number'], $row['model'], $row['branch'], 1, $row['price'] ?? null, $specs, $viewLink);
    }

    // 6. Phones
    $sql = "SELECT p.serial_number, p.brand, p.model, p.branch, 1 AS quantity, p.price, p.selling_price, p.ram, p.storage_capacity
            FROM phones p WHERE p.status = 'instock'";
    $params = [];
    if ($search_sn) { $sql .= " AND p.serial_number LIKE ?"; $params[] = "%$search_sn%"; }
    if ($search_model) { $sql .= " AND (p.brand LIKE ? OR p.model LIKE ?)"; $params[] = "%$search_model%"; $params[] = "%$search_model%"; }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $name = trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? ''));
        if (empty($name)) $name = 'Phone';
        $specs = ($row['ram'] ? $row['ram'] . 'GB RAM' : '') .
                 ($row['storage_capacity'] ? ' | ' . $row['storage_capacity'] . 'GB' : '');
        if (empty($specs)) $specs = '-';
        $viewLink = in_array('Phone', $viewableTypes) ? '../phones/view_phone.php?sn=' . urlencode($row['serial_number']) : null;
        addResult($allResults, 'Phone', $row['serial_number'], $name, $row['branch'], 1, $row['price'] ?? null, $specs, $viewLink);
    }

    // 7. Accessories
    $sql = "SELECT a.id, a.name, a.branch, a.quantity, a.price
            FROM accessories a WHERE a.status = 'instock'";
    $params = [];
    if ($search_sn) { $sql .= " AND a.id LIKE ?"; $params[] = "%$search_sn%"; }
    if ($search_model) { $sql .= " AND a.name LIKE ?"; $params[] = "%$search_model%"; }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $specs = 'Qty: ' . $row['quantity'];
        $viewLink = null;
        addResult($allResults, 'Accessory', $row['id'], $row['name'], $row['branch'], $row['quantity'], $row['price'] ?? null, $specs, $viewLink);
    }

    // 8. Graphics Cards
    $sql = "SELECT g.id, g.type AS name, g.branch, g.quantity, g.price, g.storage_capacity
            FROM graphic_cards g WHERE g.status = 'instock'";
    $params = [];
    if ($search_sn) { $sql .= " AND g.id LIKE ?"; $params[] = "%$search_sn%"; }
    if ($search_model) { $sql .= " AND g.type LIKE ?"; $params[] = "%$search_model%"; }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $specs = ($row['storage_capacity'] ?? '') ? $row['storage_capacity'] . 'GB' : '-';
        $viewLink = null;
        addResult($allResults, 'Graphics Card', $row['id'], $row['name'], $row['branch'], $row['quantity'], $row['price'] ?? null, $specs, $viewLink);
    }

    // 9. HDDs
    $sql = "SELECT h.id, h.type, h.storage, h.branch, h.quantity, h.price
            FROM hdds h WHERE h.quantity > 0";
    $params = [];
    if ($search_sn) { $sql .= " AND h.id LIKE ?"; $params[] = "%$search_sn%"; }
    if ($search_model) { $sql .= " AND (h.type LIKE ? OR h.storage LIKE ?)"; $params[] = "%$search_model%"; $params[] = "%$search_model%"; }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $name = trim(($row['type'] ?? '') . ' ' . ($row['storage'] ?? ''));
        if (empty($name)) $name = 'HDD';
        $specs = ($row['storage'] ?? '') ? $row['storage'] : '-';
        $viewLink = null;
        addResult($allResults, 'HDD', $row['id'], $name, $row['branch'], $row['quantity'], $row['price'] ?? null, $specs, $viewLink);
    }

    // 10. RAM/SSD
    $sql = "SELECT r.id, r.category, r.type, r.storage, r.branch, r.quantity, r.price
            FROM rams_ssds r WHERE r.quantity > 0";
    $params = [];
    if ($search_sn) { $sql .= " AND r.id LIKE ?"; $params[] = "%$search_sn%"; }
    if ($search_model) { $sql .= " AND (r.category LIKE ? OR r.type LIKE ?)"; $params[] = "%$search_model%"; $params[] = "%$search_model%"; }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $name = trim(($row['category'] ?? '') . ' ' . ($row['type'] ?? '') . ' ' . ($row['storage'] ?? '') . 'GB');
        if (empty($name)) $name = 'RAM/SSD';
        $specs = ($row['storage'] ?? '') ? $row['storage'] . 'GB' : '-';
        $viewLink = null;
        addResult($allResults, 'RAM/SSD', $row['id'], $name, $row['branch'], $row['quantity'], $row['price'] ?? null, $specs, $viewLink);
    }

    // 11. Chargers
    $sql = "SELECT c.id, c.charger_type, c.branch, c.quantity
            FROM chargers c WHERE c.quantity > 0";
    $params = [];
    if ($search_sn) { $sql .= " AND c.id LIKE ?"; $params[] = "%$search_sn%"; }
    if ($search_model) { $sql .= " AND c.charger_type LIKE ?"; $params[] = "%$search_model%"; }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $name = trim(($row['charger_type'] ?? ''));
        if (empty($name)) $name = 'Charger';
        $specs = '-';
        $viewLink = null;
        addResult($allResults, 'Charger', $row['id'], $name, $row['branch'], $row['quantity'], null, $specs, $viewLink);
    }

    // Sort results: group by type, then by name
    usort($allResults, function($a, $b) {
        if ($a['type'] == $b['type']) return strcasecmp($a['name'], $b['name']);
        return strcasecmp($a['type'], $b['type']);
    });
}

date_default_timezone_set('Africa/Nairobi');
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// ---- AJAX response: output only the results container ----
if ($ajax) {
    // Only output the results part
    ?>
    <div id="results-container">
        <?php if ($searched): ?>
            <?php
            $total = count($allResults);
            echo '<div style="margin-bottom:1rem;"><strong>Found ' . $total . ' item(s)</strong>';
            if ($search_sn) echo ' • Serial/ID: "' . htmlspecialchars($search_sn) . '"';
            if ($search_model) echo ' • Name/Model: "' . htmlspecialchars($search_model) . '"';
            echo '</div>';
            ?>

            <?php if ($total === 0): ?>
                <div class="empty-state"><i class="fas fa-box-open"></i><p>No matching in‑stock items found.</p></div>
            <?php else: ?>
                <?php
                $grouped = [];
                foreach ($allResults as $item) {
                    $grouped[$item['type']][] = $item;
                }
                foreach ($grouped as $type => $items): ?>
                    <div class="section-title">
                        <span><i class="fas fa-<?= strtolower($type) == 'device' ? 'laptop' : (strtolower($type) == 'monitor' ? 'desktop' : (strtolower($type) == 'printer' ? 'print' : (strtolower($type) == 'smartboard' ? 'chalkboard' : (strtolower($type) == 'ups' ? 'bolt' : (strtolower($type) == 'phone' ? 'mobile-alt' : 'box'))))) ?>"></i> <?= $type ?> (<?= count($items) ?>)</span>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>ID / Serial</th>
                                    <th>Name / Model</th>
                                    <th>Branch</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Specifications</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($items as $item): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><code><?= htmlspecialchars($item['id']) ?></code></td>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><span class="badge"><?= htmlspecialchars($item['branch']) ?></span></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td><?= $item['price'] !== null ? 'KES ' . number_format($item['price'], 0) : '-' ?></td>
                                    <td class="specs-cell"><?= htmlspecialchars($item['specs'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($item['view'])): ?>
                                            <a href="<?= $item['view'] ?>" class="view-btn">View</a>
                                        <?php else: ?>
                                            <span style="color: var(--gray-500);">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-search"></i><p>Type something to start searching...</p></div>
        <?php endif; ?>
    </div>
    <?php
    exit; // stop further output
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Search Inventory | Mombasa Computers</title>
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
        .page-header h1 i { color: var(--primary); }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .search-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .search-form { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .search-group { flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: 0.5rem; }
        .search-group label { font-size: 0.85rem; font-weight: 500; color: var(--gray-600); }
        .search-group input { padding: 0.75rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; width: 100%; }
        .btn { padding: 0.75rem 1.5rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .section-title { background: var(--gray-50); padding: 0.75rem 1rem; border-radius: var(--radius-lg); margin-top: 1.5rem; margin-bottom: 1rem; border-left: 4px solid var(--primary); font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; min-width: 800px; }
        th { background: var(--gray-50); padding: 0.75rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); }
        td { padding: 0.75rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 500; background: var(--gray-100); }
        .view-btn { padding: 0.3rem 0.7rem; background: var(--primary); color: white; border-radius: 4px; text-decoration: none; font-size: 0.75rem; }
        .view-btn:hover { background: #2a6b3a; }
        .empty-state { text-align: center; padding: 2rem; color: var(--gray-500); }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .specs-cell { font-size: 0.8rem; color: var(--gray-600); max-width: 300px; word-break: break-word; }

        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1rem 1.25rem; }
            .search-section { padding: 1rem; }
            .search-form { flex-direction: column; gap: 0.75rem; }
            .search-group { min-width: unset; width: 100%; }
            .search-group input { font-size: 1rem; padding: 0.75rem; }
            .btn { width: 100%; justify-content: center; }
            .section-title { font-size: 0.9rem; }
            table { font-size: 0.75rem; min-width: 600px; }
            th, td { padding: 0.5rem; }
            .view-btn { font-size: 0.65rem; padding: 0.2rem 0.5rem; }
            .specs-cell { max-width: 150px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header h1 { font-size: 1rem; }
            .search-group input { font-size: 0.9rem; padding: 0.6rem; }
            table { font-size: 0.65rem; min-width: 480px; }
            th, td { padding: 0.3rem; }
            .specs-cell { max-width: 100px; }
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-search"></i> Search Inventory (In Stock)</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'sales'): ?>
                <a href="../dashboard/salesdashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'inventory_admin'): ?>
                <a href="../dashboard/inventorydashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'cashier'): ?>
                <a href="../dashboard/cashierdashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Search Inventory</span>
        </div>
    </div>

    <div class="search-section">
        <form method="GET" class="search-form" id="searchForm">
            <div class="search-group">
                <label><i class="fas fa-tag"></i> Model / Name / Type</label>
                <input type="text" name="model" id="searchModel" placeholder="Search by model, name, or type..." value="<?= htmlspecialchars($search_model) ?>">
            </div>
            <div class="search-group">
                <label><i class="fas fa-qrcode"></i> Serial / ID</label>
                <input type="text" name="sn" id="searchSn" placeholder="Scan or type serial/ID..." value="<?= htmlspecialchars($search_sn) ?>" autofocus>
            </div>
            <div class="search-group" style="flex: 0 0 auto; min-width: auto;">
                <button type="submit" class="btn"><i class="fas fa-search"></i> Search</button>
            </div>
        </form>
    </div>

    <div id="results-container">
        <?php if ($searched): ?>
            <?php
            $total = count($allResults);
            echo '<div style="margin-bottom:1rem;"><strong>Found ' . $total . ' item(s)</strong>';
            if ($search_sn) echo ' • Serial/ID: "' . htmlspecialchars($search_sn) . '"';
            if ($search_model) echo ' • Name/Model: "' . htmlspecialchars($search_model) . '"';
            echo '</div>';
            ?>

            <?php if ($total === 0): ?>
                <div class="empty-state"><i class="fas fa-box-open"></i><p>No matching in‑stock items found.</p></div>
            <?php else: ?>
                <?php
                $grouped = [];
                foreach ($allResults as $item) {
                    $grouped[$item['type']][] = $item;
                }
                foreach ($grouped as $type => $items): ?>
                    <div class="section-title">
                        <span><i class="fas fa-<?= strtolower($type) == 'device' ? 'laptop' : (strtolower($type) == 'monitor' ? 'desktop' : (strtolower($type) == 'printer' ? 'print' : (strtolower($type) == 'smartboard' ? 'chalkboard' : (strtolower($type) == 'ups' ? 'bolt' : (strtolower($type) == 'phone' ? 'mobile-alt' : 'box'))))) ?>"></i> <?= $type ?> (<?= count($items) ?>)</span>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>ID / Serial</th>
                                    <th>Name / Model</th>
                                    <th>Branch</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Specifications</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($items as $item): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><code><?= htmlspecialchars($item['id']) ?></code></td>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><span class="badge"><?= htmlspecialchars($item['branch']) ?></span></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td><?= $item['price'] !== null ? 'KES ' . number_format($item['price'], 0) : '-' ?></td>
                                    <td class="specs-cell"><?= htmlspecialchars($item['specs'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($item['view'])): ?>
                                            <a href="<?= $item['view'] ?>" class="view-btn">View</a>
                                        <?php else: ?>
                                            <span style="color: var(--gray-500);">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-search"></i><p>Type something to start searching...</p></div>
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

// ---- Live Search ----
(function() {
    const searchModel = document.getElementById('searchModel');
    const searchSn = document.getElementById('searchSn');
    const resultsContainer = document.getElementById('results-container');
    const form = document.getElementById('searchForm');
    let debounceTimer = null;

    function performSearch() {
        const model = searchModel.value.trim();
        const sn = searchSn.value.trim();

        // If both empty, clear results and show placeholder
        if (model === '' && sn === '') {
            resultsContainer.innerHTML = `<div class="empty-state"><i class="fas fa-search"></i><p>Type something to start searching...</p></div>`;
            return;
        }

        // Build URL with current parameters
        const params = new URLSearchParams();
        if (model) params.append('model', model);
        if (sn) params.append('sn', sn);
        params.append('ajax', '1');

        fetch(window.location.pathname + '?' + params.toString())
            .then(response => response.text())
            .then(html => {
                resultsContainer.innerHTML = html;
            })
            .catch(error => {
                console.error('Live search error:', error);
            });
    }

    function debouncedSearch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(performSearch, 300);
    }

    // Attach input events
    searchModel.addEventListener('input', debouncedSearch);
    searchSn.addEventListener('input', debouncedSearch);

    // Intercept form submit to avoid page reload (but still allow normal submission for non-JS)
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        performSearch();
    });

    // Initial search if there are already values (page loaded with GET params)
    if (searchModel.value || searchSn.value) {
        performSearch();
    }
})();
</script>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>