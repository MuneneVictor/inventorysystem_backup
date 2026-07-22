<?php
// check_mpesa_status.php - Check if sale payment is confirmed
session_start();
require_once "../config/db.php";

header('Content-Type: application/json');

$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
if ($sale_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing sale_id']);
    exit;
}

// Check sale
$stmt = $conn->prepare("SELECT payment_status, mpesa_receipt FROM sales WHERE id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) {
    echo json_encode(['status' => 'error', 'message' => 'Sale not found']);
    exit;
}

// Map payment status to response
$statusMap = [
    'paid' => 'success',
    'cancelled' => 'cancelled',
    'failed' => 'failed',
    'unpaid' => 'pending'
];

$status = $statusMap[$sale['payment_status']] ?? 'pending';

if ($status === 'success') {
    echo json_encode([
        'status' => 'success',
        'receipt' => $sale['mpesa_receipt'] ?? null,
        'message' => 'Payment confirmed'
    ]);
} elseif ($status === 'cancelled') {
    echo json_encode([
        'status' => 'cancelled',
        'message' => 'Payment was cancelled by customer'
    ]);
} elseif ($status === 'failed') {
    echo json_encode([
        'status' => 'failed',
        'message' => 'Payment failed'
    ]);
} else {
    echo json_encode([
        'status' => 'pending',
        'message' => 'Waiting for payment confirmation'
    ]);
}
?>