<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only allow AJAX requests from logged-in users with appropriate roles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get parameters
$name = trim($_POST['name'] ?? '');
$branch = trim($_POST['branch'] ?? '');
$place = trim($_POST['place'] ?? '');

if (empty($name) || empty($branch) || empty($place)) {
    echo json_encode(['exists' => false, 'error' => 'Missing parameters']);
    exit;
}

// Check if accessory exists with this exact name, branch, and place
$stmt = $conn->prepare("SELECT id, quantity FROM accessories WHERE name = :name AND branch = :branch AND place = :place");
$stmt->execute(['name' => $name, 'branch' => $branch, 'place' => $place]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo json_encode([
        'exists' => true,
        'id' => (int)$row['id'],
        'quantity' => (int)$row['quantity']
    ]);
} else {
    echo json_encode(['exists' => false]);
}