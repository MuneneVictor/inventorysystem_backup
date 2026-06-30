<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only allow the same roles as add_ram.php
if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$category = trim($_POST['category'] ?? '');
$type = trim($_POST['type'] ?? '');
$storage = (int) ($_POST['storage'] ?? 0);
$branch = trim($_POST['branch'] ?? '');

if (empty($category) || empty($type) || $storage <= 0 || empty($branch)) {
    echo json_encode(['exists' => false, 'error' => 'Missing parameters']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT id, quantity 
        FROM rams_ssds 
        WHERE category = :category 
          AND type = :type 
          AND storage = :storage 
          AND branch = :branch
    ");
    $stmt->execute([
        'category' => $category,
        'type' => $type,
        'storage' => $storage,
        'branch' => $branch
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            'exists' => true,
            'id' => (int)$result['id'],
            'quantity' => (int)$result['quantity']
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
} catch (PDOException $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}