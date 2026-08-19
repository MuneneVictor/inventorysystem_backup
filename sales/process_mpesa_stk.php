<?php
// process_mpesa_stk.php - Initiate STK Push for checkout
session_start();
require_once "../config/db.php";

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in and has cashier role (or any allowed)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['cashier', 'super_admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$sale_id = (int)($input['sale_id'] ?? 0);
$phone = trim($input['phone'] ?? '');
$amount = (float)($input['amount'] ?? 0);

// Validate
if ($sale_id <= 0 || empty($phone) || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Format phone: remove leading 0, add 254
$phone_clean = preg_replace('/[^0-9]/', '', $phone);
if (substr($phone_clean, 0, 1) === '0') {
    $phone_clean = substr($phone_clean, 1);
}
if (substr($phone_clean, 0, 3) !== '254') {
    $phone_clean = '254' . $phone_clean;
}
if (strlen($phone_clean) !== 12) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Phone number must be 9-10 digits after 0']);
    exit;
}

// Check sale exists and is active
$stmt = $conn->prepare("SELECT id, total_amount FROM sales WHERE id = ? AND sale_status = 'active'");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Sale not found or not active']);
    exit;
}

// Ensure amount matches (optional, but we'll use sale total)
$amount = (float)$sale['total_amount'];
if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sale total is zero, cannot initiate payment']);
    exit;
}

// ============================================
// PAYHERO CREDENTIALS - UPDATE THESE
// ============================================

$username = '';       // Replace with your PayHero username
$password = '';       // Replace with your PayHero password
$channel_id = '';             // Your channel ID
$api_url = 'https://backend.payhero.co.ke/api/v2/payments';
$callback_url = 'https://imanmombasaco.com/sales/mpesa_callback.php?sale_id=' . $sale_id;

// Basic Auth
$auth = base64_encode($username . ':' . $password);

// Prepare data
$data = [
    'amount' => (int)$amount,
    'phone_number' => $phone_clean,
    'channel_id' => $channel_id,
    'provider' => 'm-pesa',
    'external_reference' => 'SALE_' . $sale_id . '_' . time(),
    'callback_url' => $callback_url
];

// Log request
error_log('PayHero STK Request for Sale #' . $sale_id . ': ' . json_encode($data));

// cURL
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

// Log response
error_log('PayHero STK Response for Sale #' . $sale_id . ': HTTP ' . $httpCode . ' - ' . substr($response, 0, 500));

if ($curlError) {
    echo json_encode(['success' => false, 'error' => 'Network error: ' . $curlError]);
    exit;
}

if (empty($response)) {
    echo json_encode(['success' => false, 'error' => 'Empty response from payment gateway']);
    exit;
}

$decoded = json_decode($response, true);
if ($decoded === null) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON response from gateway']);
    exit;
}

// Check for errors from PayHero
if (isset($decoded['error']) || (isset($decoded['status']) && $decoded['status'] === 'error')) {
    $errorMsg = $decoded['message'] ?? $decoded['error'] ?? 'Payment gateway error';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

// Success
echo json_encode([
    'success' => true,
    'data' => [
        'reference' => $decoded['reference'] ?? $decoded['external_reference'] ?? 'N/A',
        'checkout_request_id' => $decoded['checkout_request_id'] ?? $decoded['id'] ?? 'N/A',
        'phone' => $phone_clean
    ]
]);
?>
