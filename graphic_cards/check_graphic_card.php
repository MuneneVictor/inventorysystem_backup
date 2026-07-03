<?php
session_start();
require_once "../config/db.php";

// Only allow AJAX requests from logged-in users
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type             = trim($_POST['type'] ?? '');
    $storage_capacity = isset($_POST['storage_capacity']) ? (int) $_POST['storage_capacity'] : 0;
    $branch           = trim($_POST['branch'] ?? '');

    if (empty($type) || $storage_capacity <= 0 || empty($branch)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing or invalid parameters']);
        exit;
    }

    try {
        $stmt = $conn->prepare("
            SELECT id, quantity 
            FROM graphic_cards 
            WHERE type = :type AND storage_capacity = :storage_capacity AND branch = :branch
        ");
        $stmt->execute([
            'type'              => $type,
            'storage_capacity'  => $storage_capacity,
            'branch'            => $branch
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo json_encode([
                'exists'   => true,
                'id'       => (int) $result['id'],
                'quantity' => (int) $result['quantity']
            ]);
        } else {
            echo json_encode(['exists' => false]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}