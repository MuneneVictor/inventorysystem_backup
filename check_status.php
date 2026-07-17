<?php
// check_status.php - Polls for payment status updates
// This file reads the callback log to determine payment status

// Set JSON response header
header('Content-Type: application/json');

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Define log file path
define('LOG_FILE', __DIR__ . '/logs/callback.log');

/**
 * Check if payment callback has been received
 */
function checkPaymentStatus()
{
    // Check if log file exists
    if (!file_exists(LOG_FILE)) {
        return [
            'status' => 'waiting',
            'message' => 'No callback received yet. Waiting for M-Pesa response.',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // Read the log file
    $logs = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    if (empty($logs)) {
        return [
            'status' => 'waiting',
            'message' => 'No callback received yet. Waiting for M-Pesa response.',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // Get the last log entry (most recent callback)
    $lastLine = end($logs);
    $lastLog = json_decode($lastLine, true);
    
    if ($lastLog === null) {
        return [
            'status' => 'error',
            'message' => 'Invalid log data found.',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // Check if the callback contains payment data
    $callbackData = $lastLog['data'] ?? [];
    
    // Determine payment status
    $status = determinePaymentStatus($callbackData);
    
    return [
        'status' => $status,
        'data' => $callbackData,
        'timestamp' => $lastLog['timestamp'] ?? date('Y-m-d H:i:s'),
        'message' => getStatusMessage($status)
    ];
}

/**
 * Get human readable status message
 */
function getStatusMessage($status)
{
    $messages = [
        'success' => 'Payment completed successfully!',
        'failed' => 'Payment failed. Please try again.',
        'cancelled' => 'Payment was cancelled by you on your phone.',
        'pending' => 'Payment is still processing...',
        'waiting' => 'Waiting for M-Pesa response...',
        'received' => 'Callback received, processing...',
        'error' => 'An error occurred during payment.'
    ];
    
    return $messages[$status] ?? 'Unknown status';
}

/**
 * Determine payment status from callback data
 */
function determinePaymentStatus($data)
{
    // Check for M-Pesa status codes (STK Push callback format)
    if (isset($data['Body']['stkCallback']['ResultCode'])) {
        $resultCode = (int)$data['Body']['stkCallback']['ResultCode'];
        
        if ($resultCode === 0) {
            return 'success';
        } elseif ($resultCode === 1032) {
            return 'cancelled';  // User cancelled on phone
        } elseif ($resultCode === 1037) {
            return 'cancelled';  // User cancelled on phone
        } else {
            return 'failed';
        }
    }
    
    // Check for PayHero status
    if (isset($data['status'])) {
        $status = strtolower($data['status']);
        if ($status === 'success' || $status === 'completed' || $status === 'paid') {
            return 'success';
        } elseif ($status === 'failed' || $status === 'error') {
            return 'failed';
        } elseif ($status === 'cancelled') {
            return 'cancelled';
        } elseif ($status === 'pending' || $status === 'processing') {
            return 'pending';
        }
    }
    
    // Check for result_code
    if (isset($data['result_code'])) {
        $resultCode = (int)$data['result_code'];
        if ($resultCode === 0) {
            return 'success';
        } elseif ($resultCode === 1032) {
            return 'cancelled';
        } elseif ($resultCode === 1037) {
            return 'cancelled';
        } else {
            return 'failed';
        }
    }
    
    // Check for ResponseCode
    if (isset($data['ResponseCode'])) {
        $responseCode = (int)$data['ResponseCode'];
        if ($responseCode === 0) {
            return 'success';
        } elseif ($responseCode === 1032) {
            return 'cancelled';
        } elseif ($responseCode === 1037) {
            return 'cancelled';
        } else {
            return 'failed';
        }
    }
    
    // Check for success flag
    if (isset($data['success'])) {
        if ($data['success'] === true || $data['success'] === 'true') {
            return 'success';
        }
    }
    
    // If we have any data, check if it contains successful transaction info
    if (!empty($data)) {
        // Check for transaction details
        if (isset($data['transaction_id']) || isset($data['transactionId']) || 
            isset($data['mpesa_receipt']) || isset($data['receipt_number'])) {
            return 'success';
        }
        
        // Check for amount and phone - likely a successful transaction
        if (isset($data['amount']) && isset($data['phone_number'])) {
            return 'success';
        }
    }
    
    // If we have data but can't determine, assume it's a callback
    return 'received';
}

// --- Main execution ---

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Method not allowed. Use GET or POST.'
        ]);
        exit;
    }
    
    // Get payment status
    $status = checkPaymentStatus();
    
    // Return response
    echo json_encode($status);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>