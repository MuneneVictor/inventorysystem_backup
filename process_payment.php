<?php
// process_payment.php - Handles payment processing via AJAX

// ============================================
// ERROR HANDLING - CATCH ALL ERRORS
// ============================================
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Exception: ' . $e->getMessage()
    ]);
    exit;
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "Error [$errno]: $errstr in $errfile on line $errline"
    ]);
    exit;
});

// Disable display errors, but log them
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set JSON response header
header('Content-Type: application/json');

// ============================================
// CONFIGURATION - UPDATE THESE VALUES
// ============================================
//$username = "Rce66miWcVEnzfjLbSKp";
    //$password = "npe9cWNlJOz0GZhkWr5Bz5xVpuJPfHF5tOPLMs2B";
    //$channel_id = "10427";
$username = "Rce66miWcVEnzfjLbSKp";        // REPLACE with your username
$password = "npe9cWNlJOz0GZhkWr5Bz5xVpuJPfHF5tOPLMs2B";        // REPLACE with your password
$channel_id = "10427";
$api_url = "https://backend.payhero.co.ke/api/v2/payments";
$callback_url = "https://vimarktech.com/inventory_system/callback.php";

// ============================================
// HELPER FUNCTIONS
// ============================================

function formatPhoneNumber($phone)
{
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Remove leading zero if present
    if (substr($phone, 0, 1) === '0') {
        $phone = substr($phone, 1);
    }
    
    // Ensure it starts with 254
    if (substr($phone, 0, 3) !== '254') {
        $phone = '254' . $phone;
    }
    
    return $phone;
}

function validateInput($data)
{
    $errors = [];
    
    if (empty($data['phone'])) {
        $errors[] = 'Phone number is required.';
    } else {
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);
        if (strlen($phone) < 9 || strlen($phone) > 10) {
            $errors[] = 'Phone number must be 9-10 digits.';
        } elseif (!in_array(substr($phone, 0, 2), ['07', '01'])) {
            $errors[] = 'Phone number must start with 07 or 01.';
        }
    }
    
    if (empty($data['amount'])) {
        $errors[] = 'Amount is required.';
    } else {
        $amount = (int)$data['amount'];
        if ($amount < 1) {
            $errors[] = 'Amount must be at least 1 KES.';
        } elseif ($amount > 150000) {
            $errors[] = 'Amount exceeds maximum limit of 150,000 KES.';
        }
    }
    
    return $errors;
}

// ============================================
// MAIN EXECUTION
// ============================================

try {
    // Debug: Log request
    error_log('=== process_payment.php called ===');
    
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
        exit;
    }
    
    // Get raw input
    $rawInput = file_get_contents('php://input');
    error_log('Raw input: ' . $rawInput);
    
    if (empty($rawInput)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No input data received.']);
        exit;
    }
    
    // Parse JSON
    $input = json_decode($rawInput, true);
    if ($input === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data.']);
        exit;
    }
    
    // Validate input
    $validationErrors = validateInput($input);
    if (!empty($validationErrors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $validationErrors[0], 'errors' => $validationErrors]);
        exit;
    }
    
    $phone = trim($input['phone']);
    $amount = (int)$input['amount'];
    
    // Format phone number
    $formattedPhone = formatPhoneNumber($phone);
    
    // Check credentials
    if (empty($username) || $username === 'YOUR_USERNAME') {
        echo json_encode(['success' => false, 'error' => 'Payment gateway username not configured.']);
        exit;
    }
    
    if (empty($password) || $password === 'YOUR_PASSWORD') {
        echo json_encode(['success' => false, 'error' => 'Payment gateway password not configured.']);
        exit;
    }
    
    // Prepare authentication
    $auth = base64_encode($username . ':' . $password);
    
    // Prepare payment data
    $data = [
        'amount' => $amount,
        'phone_number' => $formattedPhone,
        'channel_id' => $channel_id,
        'provider' => 'm-pesa',
        'external_reference' => 'PAY_' . time() . '_' . rand(1000, 9999),
        'callback_url' => $callback_url
    ];
    
    error_log('Sending request to PayHero: ' . json_encode($data));
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log('Response HTTP Code: ' . $httpCode);
    error_log('Response: ' . $response);
    
    if ($curlError) {
        echo json_encode(['success' => false, 'error' => 'Network error: ' . $curlError]);
        exit;
    }
    
    if (empty($response)) {
        echo json_encode(['success' => false, 'error' => 'Empty response from payment gateway.']);
        exit;
    }
    
    $decodedResponse = json_decode($response, true);
    if ($decodedResponse === null) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON response: ' . substr($response, 0, 200)]);
        exit;
    }
    
    // Check if API returned an error
    if (isset($decodedResponse['error']) || (isset($decodedResponse['status']) && $decodedResponse['status'] === 'error')) {
        $errorMessage = $decodedResponse['message'] ?? $decodedResponse['error'] ?? 'Payment gateway returned an error.';
        echo json_encode(['success' => false, 'error' => $errorMessage, 'details' => $decodedResponse]);
        exit;
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'data' => [
            'reference' => $decodedResponse['reference'] ?? $decodedResponse['external_reference'] ?? 'N/A',
            'status' => $decodedResponse['status'] ?? 'pending',
            'checkout_request_id' => $decodedResponse['checkout_request_id'] ?? $decodedResponse['id'] ?? 'N/A',
            'phone_number' => $formattedPhone
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Error in process_payment.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>