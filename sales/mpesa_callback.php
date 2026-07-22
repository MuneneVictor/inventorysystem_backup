<?php
// mpesa_callback.php - DEBUG VERSION with full error reporting

// Enable ALL error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set JSON response
header('Content-Type: application/json');

// Log to a simple file - this will work even if logs folder doesn't exist
$logFile = __DIR__ . '/callback_debug.log';
$debugData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'get' => $_GET,
    'post' => $_POST,
    'raw_input' => file_get_contents('php://input'),
    'headers' => getallheaders()
];
file_put_contents($logFile, json_encode($debugData) . "\n", FILE_APPEND | LOCK_EX);

// Include database
require_once "../config/db.php";

// Get sale_id
$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;

// Get raw input
$rawInput = file_get_contents('php://input');
$callbackData = json_decode($rawInput, true);

if ($callbackData === null) {
    $callbackData = ['raw' => $rawInput];
}

// Log to file
file_put_contents($logFile, "CALLBACK DATA: " . json_encode($callbackData) . "\n", FILE_APPEND | LOCK_EX);

// Check for M-Pesa status
$isSuccess = false;
$isCancelled = false;
$isFailed = false;
$resultCode = null;
$receipt = null;

if (isset($callbackData['Body']['stkCallback']['ResultCode'])) {
    $resultCode = (int)$callbackData['Body']['stkCallback']['ResultCode'];
    file_put_contents($logFile, "ResultCode: " . $resultCode . "\n", FILE_APPEND | LOCK_EX);
    
    if ($resultCode === 0) {
        $isSuccess = true;
        // Get receipt
        if (isset($callbackData['Body']['stkCallback']['CallbackMetadata']['Item'])) {
            foreach ($callbackData['Body']['stkCallback']['CallbackMetadata']['Item'] as $item) {
                if ($item['Name'] === 'MpesaReceiptNumber') {
                    $receipt = $item['Value'] ?? null;
                }
            }
        }
        file_put_contents($logFile, "SUCCESS! Receipt: " . $receipt . "\n", FILE_APPEND | LOCK_EX);
    } elseif ($resultCode === 1032 || $resultCode === 1037) {
        $isCancelled = true;
        file_put_contents($logFile, "CANCELLED by user\n", FILE_APPEND | LOCK_EX);
    } else {
        $isFailed = true;
        file_put_contents($logFile, "FAILED - ResultCode: " . $resultCode . "\n", FILE_APPEND | LOCK_EX);
    }
}

// Update the sale if we have a sale_id
if ($sale_id > 0) {
    try {
        file_put_contents($logFile, "Updating sale #" . $sale_id . "\n", FILE_APPEND | LOCK_EX);
        
        if ($isSuccess) {
            $stmt = $conn->prepare("UPDATE sales SET payment_status = 'paid', payment_method = 'mpesa-till', mpesa_receipt = ? WHERE id = ?");
            $stmt->execute([$receipt, $sale_id]);
            file_put_contents($logFile, "Sale #" . $sale_id . " marked as PAID\n", FILE_APPEND | LOCK_EX);
        } elseif ($isCancelled) {
            $stmt = $conn->prepare("UPDATE sales SET payment_status = 'cancelled' WHERE id = ?");
            $stmt->execute([$sale_id]);
            file_put_contents($logFile, "Sale #" . $sale_id . " marked as CANCELLED\n", FILE_APPEND | LOCK_EX);
        } elseif ($isFailed) {
            $stmt = $conn->prepare("UPDATE sales SET payment_status = 'failed' WHERE id = ?");
            $stmt->execute([$sale_id]);
            file_put_contents($logFile, "Sale #" . $sale_id . " marked as FAILED\n", FILE_APPEND | LOCK_EX);
        }
    } catch (Exception $e) {
        file_put_contents($logFile, "ERROR updating sale: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    }
}

// Always return success
echo json_encode([
    'success' => true,
    'result_code' => $resultCode,
    'is_success' => $isSuccess,
    'is_cancelled' => $isCancelled,
    'is_failed' => $isFailed,
    'receipt' => $receipt,
    'sale_id' => $sale_id,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>