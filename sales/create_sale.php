<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'] ?? '';
$user_id = (int) $_SESSION['user_id'];

if (!in_array($role, ['sales', 'super_admin', 'inventory_admin', 'manager', 'cashier'])) {
    die("ACCESS DENIED.");
}

$salesperson_id = $_POST['salesperson_id'] ?? 0;
$client_id = $_POST['client_id'] ?? 0;
$client_name = trim($_POST['client_name'] ?? '');
$client_phone = trim($_POST['client_phone'] ?? '');

// For non-cashiers, force salesperson_id to current user
if ($role !== 'cashier') {
    $salesperson_id = $user_id;
}

if (!$salesperson_id) {
    die("Salesperson not selected.");
}

// If client_id is provided, fetch client details
if ($client_id > 0) {
    $stmt = $conn->prepare("SELECT client_name, client_phone FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($client) {
        $client_name = $client['client_name'];
        $client_phone = $client['client_phone'];
    }
}

// Insert new sale
$stmt = $conn->prepare("INSERT INTO sales (client_name, client_phone, sale_status, sold_by, created_at) VALUES (?, ?, 'active', ?, NOW())");
if ($stmt->execute([$client_name ?: null, $client_phone ?: null, $salesperson_id])) {
    $sale_id = $conn->lastInsertId();
    // Store in session and redirect to make_sale.php
    $_SESSION['current_sale_id'] = $sale_id;
    header("Location: make_sale.php");
    exit;
} else {
    die("Failed to create sale.");
}