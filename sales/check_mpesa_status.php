<?php
// check_mpesa_status.php - Check sale payment status
session_start();
require_once "../config/db.php";

header('Content-Type: application/json');

$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
if ($sale_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing sale_id']);
    exit;
}

$stmt = $conn->prepare("SELECT payment_status, mpesa_status, mpesa_receipt FROM sales WHERE id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) {
    echo json_encode(['status' => 'error', 'message' => 'Sale not found']);
    exit;
}

// Determine overall status for the UI
$responseStatus = 'pending';

if ($sale['payment_status'] === 'paid') {
    // If payment is paid, it's definitely a success
    $responseStatus = 'success';
    $receipt = $sale['mpesa_receipt'];
} elseif ($sale['mpesa_status'] === 'cancelled') {
    $responseStatus = 'cancelled';
    $receipt = null;
} elseif ($sale['mpesa_status'] === 'failed') {
    $responseStatus = 'failed';
    $receipt = null;
} elseif ($sale['mpesa_status'] === 'success') {
    // Should not happen if payment_status is not paid, but just in case
    $responseStatus = 'success';
    $receipt = $sale['mpesa_receipt'];
}

// Map to the frontend expected statuses
$statusMap = [
    'success' => 'success',
    'cancelled' => 'cancelled',
    'failed' => 'failed',
    'pending' => 'pending'
];

$status = $statusMap[$responseStatus] ?? 'pending';

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