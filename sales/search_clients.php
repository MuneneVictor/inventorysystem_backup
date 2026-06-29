<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$q = $_GET['q'] ?? '';
$sales_person = isset($_GET['sales_person']) && is_numeric($_GET['sales_person']) ? (int)$_GET['sales_person'] : null;

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

// Build query: search by name or phone, and filter by sales_person (if provided)
$sql = "SELECT id, client_name, client_phone FROM clients WHERE (client_name LIKE ? OR client_phone LIKE ?)";
$params = ["%$q%", "%$q%"];

if ($sales_person !== null) {
    // Show clients belonging to this salesperson OR unassigned (sales_person IS NULL)
    $sql .= " AND (sales_person = ? OR sales_person IS NULL)";
    $params[] = $sales_person;
}

$sql .= " LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($clients);