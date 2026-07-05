<?php
// Start session and set JSON header FIRST
session_start();
header('Content-Type: application/json');

// Simple auth check - no includes
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Check role
$allowed_roles = ['sales', 'super_admin', 'manager', 'technician'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Get search query
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

try {
    // Database connection
    require_once "../config/db.php";
    
    // Search clients by name or phone
    $stmt = $conn->prepare("SELECT id, client_name, client_phone, client_box, client_email FROM clients WHERE client_name LIKE ? OR client_phone LIKE ? ORDER BY client_name LIMIT 20");
    $like = "%$q%";
    $stmt->execute([$like, $like]);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return results
    echo json_encode($clients);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
exit;
?>