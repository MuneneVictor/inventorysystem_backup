<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if (!in_array($_SESSION['role'], ['sales', 'super_admin', 'inventory_admin', 'manager', 'cashier'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$results = [];
$like = "%$q%";

// Helper to add item
function addItem(&$results, $type, $id, $name, $branch, $quantity, $price, $specs, $serial = null) {
    $results[] = [
        'type' => $type,
        'id' => $id,
        'name' => $name,
        'branch' => $branch,
        'quantity' => (int)$quantity,
        'price' => $price ? (float)$price : null,
        'specs' => $specs,
        'serial' => $serial
    ];
}

// Devices
$stmt = $conn->prepare("SELECT serial_number, model_name, branch, 1 AS quantity, price, selling_price,
                        processor, ram, storage_type, storage_capacity, graphics, touch
                        FROM devices WHERE status = 'In Stock' AND (serial_number LIKE ? OR model_name LIKE ?)");
$stmt->execute([$like, $like]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $specs = trim(($row['processor'] ?? '') . ' | ' . ($row['ram'] ? $row['ram'] . 'GB RAM' : '') .
        ($row['storage_type'] && $row['storage_capacity'] ? ' | ' . $row['storage_type'] . ' ' . $row['storage_capacity'] . 'GB' : '') .
        ($row['graphics'] ? ' | ' . $row['graphics'] : '') .
        ($row['touch'] && $row['touch'] != 'N/A' ? ' | ' . $row['touch'] : ''));
    if (empty($specs)) $specs = '-';
    addItem($results, 'device', $row['serial_number'], $row['model_name'], $row['branch'], 1, $row['price'] ?? null, $specs, $row['serial_number']);
}

// Monitors
$stmt = $conn->prepare("SELECT serial_number, model_name, branch, 1 AS quantity, price, selling_price, size_inches
                        FROM monitors WHERE status = 'In Stock' AND (serial_number LIKE ? OR model_name LIKE ?)");
$stmt->execute([$like, $like]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $specs = ($row['size_inches'] ?? '') ? $row['size_inches'] . ' inch' : '-';
    addItem($results, 'monitor', $row['serial_number'], $row['model_name'], $row['branch'], 1, $row['price'] ?? null, $specs, $row['serial_number']);
}

// Printers
$stmt = $conn->prepare("SELECT serial_number, model_name, branch, 1 AS quantity, price, selling_price
                        FROM printers WHERE status = 'In Stock' AND (serial_number LIKE ? OR model_name LIKE ?)");
$stmt->execute([$like, $like]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    addItem($results, 'printer', $row['serial_number'], $row['model_name'], $row['branch'], 1, $row['price'] ?? null, 'N/A', $row['serial_number']);
}

// Smartboards
$stmt = $conn->prepare("SELECT serial_number, model, branch, 1 AS quantity, price, selling_price, size_inches
                        FROM smartboards WHERE status = 'instock' AND (serial_number LIKE ? OR model LIKE ?)");
$stmt->execute([$like, $like]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $specs = ($row['model'] ?? '') . ($row['size_inches'] ? ' | ' . $row['size_inches'] . ' inch' : '');
    if (empty($specs)) $specs = '-';
    addItem($results, 'smartboard', $row['serial_number'], $row['model'], $row['branch'], 1, $row['price'] ?? null, $specs, $row['serial_number']);
}

// UPS
$stmt = $conn->prepare("SELECT serial_number, model, branch, 1 AS quantity, price, selling_price, capacity
                        FROM ups WHERE status = 'instock' AND (serial_number LIKE ? OR model LIKE ?)");
$stmt->execute([$like, $like]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $specs = ($row['model'] ?? '') . ($row['capacity'] ? ' | ' . $row['capacity'] . ' VA' : '');
    if (empty($specs)) $specs = '-';
    addItem($results, 'ups', $row['serial_number'], $row['model'], $row['branch'], 1, $row['price'] ?? null, $specs, $row['serial_number']);
}

// Phones
$stmt = $conn->prepare("SELECT serial_number, brand, model, branch, 1 AS quantity, price, selling_price, ram, storage_capacity
                        FROM phones WHERE status = 'instock' AND (serial_number LIKE ? OR brand LIKE ? OR model LIKE ?)");
$stmt->execute([$like, $like, $like]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $name = trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? ''));
    if (empty($name)) $name = 'Phone';
    $specs = ($row['ram'] ? $row['ram'] . 'GB RAM' : '') . ($row['storage_capacity'] ? ' | ' . $row['storage_capacity'] . 'GB' : '');
    if (empty($specs)) $specs = '-';
    addItem($results, 'phone', $row['serial_number'], $name, $row['branch'], 1, $row['price'] ?? null, $specs, $row['serial_number']);
}

// Accessories
$stmt = $conn->prepare("SELECT id, name, branch, quantity, price FROM accessories WHERE status = 'instock' AND (id LIKE ? OR name LIKE ?)");
$stmt->execute([$like, $like]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    addItem($results, 'accessory', $row['id'], $row['name'], $row['branch'], $row['quantity'], $row['price'] ?? null, 'Qty: ' . $row['quantity']);
}

// Graphics Cards
$stmt = $conn->prepare("SELECT id, type AS name, branch, quantity, price, storage_capacity
                        FROM graphic_cards WHERE status = 'instock' AND (id LIKE ? OR type LIKE ?)");
$stmt->execute([$like, $like]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $specs = ($row['storage_capacity'] ?? '') ? $row['storage_capacity'] . 'GB' : '-';
    addItem($results, 'graphic', $row['id'], $row['name'], $row['branch'], $row['quantity'], $row['price'] ?? null, $specs);
}

// HDDs
$stmt = $conn->prepare("SELECT id, type, storage, branch, quantity, price FROM hdds WHERE quantity > 0 AND (id LIKE ? OR type LIKE ? OR storage LIKE ?)");
$stmt->execute([$like, $like, $like]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $name = trim(($row['type'] ?? '') . ' ' . ($row['storage'] ?? ''));
    if (empty($name)) $name = 'HDD';
    $specs = ($row['storage'] ?? '') ? $row['storage'] : '-';
    addItem($results, 'hdd', $row['id'], $name, $row['branch'], $row['quantity'], $row['price'] ?? null, $specs);
}

// RAM/SSD
$stmt = $conn->prepare("SELECT id, category, type, storage, branch, quantity, price
                        FROM rams_ssds WHERE quantity > 0 AND (id LIKE ? OR category LIKE ? OR type LIKE ?)");
$stmt->execute([$like, $like, $like]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $name = trim(($row['category'] ?? '') . ' ' . ($row['type'] ?? '') . ' ' . ($row['storage'] ?? '') . 'GB');
    if (empty($name)) $name = 'RAM/SSD';
    $specs = ($row['storage'] ?? '') ? $row['storage'] . 'GB' : '-';
    addItem($results, 'ram_ssd', $row['id'], $name, $row['branch'], $row['quantity'], $row['price'] ?? null, $specs);
}

// Chargers
$stmt = $conn->prepare("SELECT id, charger_type, branch, quantity FROM chargers WHERE quantity > 0 AND (id LIKE ? OR charger_type LIKE ?)");
$stmt->execute([$like, $like]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $name = trim($row['charger_type'] ?? '');
    if (empty($name)) $name = 'Charger';
    addItem($results, 'charger', $row['id'], $name, $row['branch'], $row['quantity'], null, '-');
}

header('Content-Type: application/json');
echo json_encode($results);