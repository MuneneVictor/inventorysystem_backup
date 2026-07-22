<?php
// mpesa_callback.php - Handles M-Pesa callback for checkout STK Push
// Location: /inventory_system/sales/mpesa_callback.php
// Callback URL: https://inventory.vimarktech.com/inventory_system/sales/mpesa_callback.php

// Set JSON response
header('Content-Type: application/json');

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Include database - adjust path as needed
require_once "../config/db.php";

// Define log file
define('LOG_FILE', __DIR__ . '/logs/mpesa_callback.log');

// Get sale_id from query string
$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;

// Get raw input
$rawInput = file_get_contents('php://input');
$callbackData = json_decode($rawInput, true);
if ($callbackData === null) {
    $callbackData = ['raw' => $rawInput];
}

// Log the callback
$logDir = dirname(LOG_FILE);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'sale_id' => $sale_id,
    'data' => $callbackData,
    'raw' => $rawInput
];
file_put_contents(LOG_FILE, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);

// Log to PHP error log for debugging
error_log('=== M-PESA CALLBACK RECEIVED ===');
error_log('Sale ID: ' . $sale_id);
error_log('Raw input: ' . $rawInput);
error_log('Decoded data: ' . json_encode($callbackData));
error_log('==========================');

// Determine payment status
$isSuccess = false;
$isCancelled = false;
$isFailed = false;
$resultCode = null;
$receipt = null;
$resultDesc = '';

// Check for M-Pesa STK Push callback format
if (isset($callbackData['Body']['stkCallback'])) {
    $stkCallback = $callbackData['Body']['stkCallback'];
    $resultCode = isset($stkCallback['ResultCode']) ? (int)$stkCallback['ResultCode'] : null;
    $resultDesc = $stkCallback['ResultDesc'] ?? '';
    
    if ($resultCode === 0) {
        $isSuccess = true;
        // Get receipt number
        if (isset($stkCallback['CallbackMetadata']['Item'])) {
            foreach ($stkCallback['CallbackMetadata']['Item'] as $item) {
                if ($item['Name'] === 'MpesaReceiptNumber') {
                    $receipt = $item['Value'] ?? null;
                    break;
                }
            }
        }
        error_log('Payment SUCCESS! Sale #' . $sale_id . ' Receipt: ' . $receipt);
    } elseif ($resultCode === 1032 || $resultCode === 1037) {
        $isCancelled = true;
        error_log('Payment CANCELLED by user. Sale #' . $sale_id . ' ResultCode: ' . $resultCode);
    } else {
        $isFailed = true;
        error_log('Payment FAILED. Sale #' . $sale_id . ' ResultCode: ' . $resultCode . ' Desc: ' . $resultDesc);
    }
}

// Check for other formats
if (isset($callbackData['status'])) {
    $status = strtolower($callbackData['status']);
    if ($status === 'success' || $status === 'completed') {
        $isSuccess = true;
        $receipt = $callbackData['receipt'] ?? $callbackData['mpesa_receipt'] ?? null;
        error_log('Payment SUCCESS via status field. Sale #' . $sale_id);
    } elseif ($status === 'cancelled') {
        $isCancelled = true;
        error_log('Payment CANCELLED via status field. Sale #' . $sale_id);
    } elseif ($status === 'failed' || $status === 'error') {
        $isFailed = true;
        error_log('Payment FAILED via status field. Sale #' . $sale_id);
    }
}

if (isset($callbackData['result_code'])) {
    $code = (int)$callbackData['result_code'];
    if ($code === 0) {
        $isSuccess = true;
        $receipt = $callbackData['receipt'] ?? null;
    } elseif ($code === 1032 || $code === 1037) {
        $isCancelled = true;
    } else {
        $isFailed = true;
    }
}

// Update the sale based on the result
if ($sale_id > 0) {
    try {
        $conn->beginTransaction();
        
        // Check if sale exists
        $stmt = $conn->prepare("SELECT id, payment_status FROM sales WHERE id = ? AND sale_status = 'active'");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sale) {
            if ($isSuccess && $sale['payment_status'] !== 'paid') {
                // Update to paid
                $stmt = $conn->prepare("UPDATE sales SET payment_status = 'paid', payment_method = 'mpesa-till', mpesa_receipt = ? WHERE id = ?");
                $stmt->execute([$receipt, $sale_id]);
                error_log("Sale #$sale_id marked as PAID via M-Pesa callback. Receipt: $receipt");
            } elseif ($isCancelled) {
                // Update to cancelled - keep as unpaid but mark that STK was cancelled
                $stmt = $conn->prepare("UPDATE sales SET payment_status = 'cancelled' WHERE id = ?");
                $stmt->execute([$sale_id]);
                error_log("Sale #$sale_id marked as CANCELLED via M-Pesa callback.");
            } elseif ($isFailed) {
                // Update to failed
                $stmt = $conn->prepare("UPDATE sales SET payment_status = 'failed' WHERE id = ?");
                $stmt->execute([$sale_id]);
                error_log("Sale #$sale_id marked as FAILED via M-Pesa callback.");
            }
        } else {
            error_log("Sale #$sale_id not found or not active");
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error updating sale #$sale_id from callback: " . $e->getMessage());
    }
}

// Always return success to acknowledge receipt
echo json_encode([
    'success' => true,
    'result_code' => $resultCode,
    'is_success' => $isSuccess,
    'is_cancelled' => $isCancelled,
    'is_failed' => $isFailed,
    'receipt' => $receipt,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>