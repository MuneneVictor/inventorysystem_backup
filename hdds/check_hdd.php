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
    $type = trim($_POST['type'] ?? '');
    $storage = trim($_POST['storage'] ?? '');
    $branch = trim($_POST['branch'] ?? '');

    if (empty($type) || empty($storage) || empty($branch)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT id, quantity FROM hdds WHERE type = :type AND storage = :storage AND branch = :branch");
        $stmt->execute([
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
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}