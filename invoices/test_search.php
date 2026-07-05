<?php
session_start();
header('Content-Type: application/json');

// Check session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No session', 'session' => $_SESSION]);
    exit;
}

echo json_encode([
    'session' => [
        'user_id' => $_SESSION['user_id'],
        'role' => $_SESSION['role']
    ],
    'query' => $_GET['q'] ?? 'none',
    'message' => 'Test successful'
]);
exit;
?>