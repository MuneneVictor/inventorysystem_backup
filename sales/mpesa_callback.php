<?php
// mpesa_callback.php - Sets payment_status, mpesa_status, and completion_status

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$logFile = __DIR__ . '/callback_debug.log';

function writeLog($msg) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $msg . "\n", FILE_APPEND | LOCK_EX);
    error_log($msg);
}

writeLog('=== CALLBACK RECEIVED ===');

require_once "../config/db.php";

$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
$rawInput = file_get_contents('php://input');
$callbackData = json_decode($rawInput, true);
if ($callbackData === null) {
    $callbackData = ['raw' => $rawInput];
}

writeLog('Raw: ' . $rawInput);
writeLog('Parsed: ' . json_encode($callbackData));

$resultCode = null;
$receipt = null;
$mpesaStatus = null;
$paymentStatus = null;

// -------- Extract ResultCode from various formats --------
if (isset($callbackData['response']['ResultCode'])) {
    $resultCode = (int)$callbackData['response']['ResultCode'];
    $receipt = $callbackData['response']['MpesaReceiptNumber'] ?? null;
    writeLog('PayHero format. ResultCode: ' . $resultCode);
}
elseif (isset($callbackData['Body']['stkCallback']['ResultCode'])) {
    $resultCode = (int)$callbackData['Body']['stkCallback']['ResultCode'];
    if (isset($callbackData['Body']['stkCallback']['CallbackMetadata']['Item'])) {
        foreach ($callbackData['Body']['stkCallback']['CallbackMetadata']['Item'] as $item) {
            if ($item['Name'] === 'MpesaReceiptNumber') {
                $receipt = $item['Value'] ?? null;
            }
        }
    }
    writeLog('M-Pesa format. ResultCode: ' . $resultCode);
}
elseif (isset($callbackData['ResultCode'])) {
    $resultCode = (int)$callbackData['ResultCode'];
    $receipt = $callbackData['MpesaReceiptNumber'] ?? null;
    writeLog('Root format. ResultCode: ' . $resultCode);
}
elseif (isset($callbackData['status'])) {
    if ($callbackData['status'] === true || strtolower($callbackData['status']) === 'success') {
        $resultCode = 0;
        $receipt = $callbackData['response']['MpesaReceiptNumber'] ?? null;
    } elseif (strtolower($callbackData['status']) === 'failed') {
        $resultCode = 1;
    }
    writeLog('Status field. ResultCode: ' . $resultCode);
}

// -------- Determine new statuses --------
if ($resultCode === 0) {
    $mpesaStatus = 'success';
    $paymentStatus = 'paid';
    writeLog('✅ SUCCESS! Sale #' . $sale_id . ' Receipt: ' . $receipt);
} elseif ($resultCode === 1032 || $resultCode === 1037) {
    $mpesaStatus = 'cancelled';
    $paymentStatus = 'unpaid';
    writeLog('❌ CANCELLED by user. Sale #' . $sale_id);
} elseif ($resultCode === 17) {
    $mpesaStatus = 'failed';
    $paymentStatus = 'unpaid';
    writeLog('❌ FAILED - Rule limited. Sale #' . $sale_id);
} else {
    $mpesaStatus = 'failed';
    $paymentStatus = 'unpaid';
    writeLog('❌ FAILED - ResultCode: ' . $resultCode . '. Sale #' . $sale_id);
}

// -------- Update the sale --------
if ($sale_id > 0 && $mpesaStatus !== null) {
    try {
        if ($mpesaStatus === 'success') {
            // Successful: set payment_status = paid, mpesa_status = success, completion_status = Completed
            $stmt = $conn->prepare("UPDATE sales SET payment_status = ?, mpesa_status = ?, mpesa_receipt = ?, completion_status = 'Completed' WHERE id = ?");
            $stmt->execute([$paymentStatus, $mpesaStatus, $receipt, $sale_id]);
            writeLog("✅ Sale #$sale_id updated: payment_status=paid, mpesa_status=success, completion_status=Completed");
        } else {
            // Cancelled or failed: only update mpesa_status and payment_status (keep completion_status as pending)
            $stmt = $conn->prepare("UPDATE sales SET payment_status = ?, mpesa_status = ?, mpesa_receipt = ? WHERE id = ?");
            $stmt->execute([$paymentStatus, $mpesaStatus, $receipt, $sale_id]);
            writeLog("✅ Sale #$sale_id updated: payment_status=$paymentStatus, mpesa_status=$mpesaStatus");
        }
    } catch (Exception $e) {
        writeLog("❌ ERROR updating sale: " . $e->getMessage());
    }
} else {
    writeLog("⚠️ No update performed. Sale ID: $sale_id, Status: $mpesaStatus");
}

// -------- Return response --------
echo json_encode([
    'success' => true,
    'result_code' => $resultCode,
    'payment_status' => $paymentStatus,
    'mpesa_status' => $mpesaStatus,
    'receipt' => $receipt,
    'sale_id' => $sale_id,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>