<?php
// callback.php - Handles PayHero callback notifications
// This file receives and logs all callbacks from PayHero

// Set JSON response header
header('Content-Type: application/json');

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Define log file path
define('LOG_FILE', __DIR__ . '/logs/callback.log');

/**
 * Log callback data to file
 */
function logCallback($data)
{
    $logDir = dirname(LOG_FILE);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Prepare log entry with timestamp
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'headers' => getallheaders(),
        'data' => $data
    ];
    
    // Append to log file
    file_put_contents(
        LOG_FILE,
        json_encode($logEntry) . "\n",
        FILE_APPEND | LOCK_EX
    );
    
    return $logEntry;
}

/**
 * Get raw input data
 */
function getRawInput()
{
    $input = file_get_contents('php://input');
    
    if (!empty($input)) {
        // Try to decode JSON
        $jsonData = json_decode($input, true);
        if ($jsonData !== null) {
            return $jsonData;
        }
        return ['raw' => $input];
    }
    
    // If not JSON, try POST or GET
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return $_POST;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return $_GET;
    }
    
    return ['empty' => true];
}

// --- Main execution ---

try {
    // Get the callback data
    $callbackData = getRawInput();
    
    // Log the callback
    $logEntry = logCallback($callbackData);
    
    // Log to PHP error log for debugging
    error_log('Callback received: ' . json_encode($callbackData));
    
    // Check for cancellation specifically
    $isCancelled = false;
    $resultCode = null;
    
    // Check for M-Pesa cancellation (ResultCode 1032)
    if (isset($callbackData['Body']['stkCallback']['ResultCode'])) {
        $resultCode = (int)$callbackData['Body']['stkCallback']['ResultCode'];
        if ($resultCode === 1032) {
            $isCancelled = true;
            error_log('Payment was cancelled by user (ResultCode: 1032)');
        } elseif ($resultCode === 1037) {
            $isCancelled = true;
            error_log('Payment was cancelled by user (ResultCode: 1037)');
        }
    }
    
    // Check for other cancellation indicators
    if (isset($callbackData['status']) && strtolower($callbackData['status']) === 'cancelled') {
        $isCancelled = true;
        error_log('Payment was cancelled by user (status: cancelled)');
    }
    
    if (isset($callbackData['result_code']) && $callbackData['result_code'] === 1032) {
        $isCancelled = true;
        error_log('Payment was cancelled by user (result_code: 1032)');
    }
    
    // Always return success to acknowledge receipt
    echo json_encode([
        'success' => true,
        'cancelled' => $isCancelled,
        'result_code' => $resultCode,
        'message' => $isCancelled ? 'Payment was cancelled by user.' : 'Callback received and logged successfully.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Log any errors
    error_log('Callback error: ' . $e->getMessage());
    
    // Still return success to prevent retries
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'error' => 'Callback received but had errors.',
        'message' => $e->getMessage()
    ]);
}
?>