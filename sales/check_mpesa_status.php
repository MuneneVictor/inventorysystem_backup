<?php
// check_mpesa_status.php - Check sale payment status and completion status
session_start();
require_once "../config/db.php";

header('Content-Type: application/json');

$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
if ($sale_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing sale_id']);
    exit;
}

$stmt = $conn->prepare("SELECT payment_status, mpesa_status, mpesa_receipt, completion_status FROM sales WHERE id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) {
    echo json_encode(['status' => 'error', 'message' => 'Sale not found']);
    exit;
}

$responseStatus = 'pending';
$completionStatus = $sale['completion_status'] ?? 'pending';

if ($sale['payment_status'] === 'paid') {
    $responseStatus = 'success';
    $receipt = $sale['mpesa_receipt'];
} elseif ($sale['mpesa_status'] === 'cancelled') {
    $responseStatus = 'cancelled';
    $receipt = null;
} elseif ($sale['mpesa_status'] === 'failed') {
    $responseStatus = 'failed';
    $receipt = null;
} elseif ($sale['mpesa_status'] === 'success') {
    $responseStatus = 'success';
    $receipt = $sale['mpesa_receipt'];
}

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
        'message' => 'Payment confirmed',
        'completion_status' => $completionStatus
    ]);
} elseif ($status === 'cancelled') {
    echo json_encode([
        'status' => 'cancelled',
        'message' => 'Payment was cancelled by customer',
        'completion_status' => $completionStatus
    ]);
} elseif ($status === 'failed') {
    echo json_encode([
        'status' => 'failed',
        'message' => 'Payment failed',
        'completion_status' => $completionStatus
    ]);
} else {
    echo json_encode([
        'status' => 'pending',
        'message' => 'Waiting for payment confirmation',
        'completion_status' => $completionStatus
    ]);
}
?>