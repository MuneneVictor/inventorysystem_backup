<?php
session_start();

// Check authentication manually – no auth_check.php needed
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Include database connection
require_once "../config/db.php";

// Get POST parameters
$name = trim($_POST['name'] ?? '');
$branch = trim($_POST['branch'] ?? '');
$place = trim($_POST['place'] ?? '');

if (empty($name) || empty($branch) || empty($place)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

try {
    // Prepare and execute query
    $stmt = $conn->prepare("SELECT id, quantity FROM accessories WHERE name = :name AND branch = :branch AND place = :place");
    $stmt->execute(['name' => $name, 'branch' => $branch, 'place' => $place]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode([
            'success' => true,
            'exists' => true,
            'id' => (int)$row['id'],
            'quantity' => (int)$row['quantity']
        ]);
    } else {
        echo json_encode(['success' => true, 'exists' => false]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}