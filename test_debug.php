<?php
// test_debug.php - Debug script to test PayHero API

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ============================================
// CONFIGURATION - UPDATE THESE VALUES
// ============================================
$username = "YOUR_USERNAME";        // REPLACE with your username
$password = "YOUR_PASSWORD";        // REPLACE with your password
$channel_id = "10427";

// Test data
$phone = "254712345678"; // Replace with a real phone number for testing
$amount = 10;

// Basic Authentication
$auth = base64_encode($username . ":" . $password);

$data = [
    "amount" => $amount,
    "phone_number" => $phone,
    "channel_id" => $channel_id,
    "provider" => "m-pesa",
    "external_reference" => "TEST_" . time(),
    "callback_url" => "https://vimarktech.com/inventory_system/callback.php"
];

echo "<h2>PayHero STK Push Debug Test</h2>";
echo "<pre>";
echo "Username: " . $username . "\n";
echo "Channel ID: " . $channel_id . "\n";
echo "Auth: " . $auth . "\n\n";
echo "Request Data:\n";
print_r($data);
echo "\n\n";

// Initialize cURL
$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://backend.payhero.co.ke/api/v2/payments",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        "Authorization: Basic " . $auth,
        "Content-Type: application/json"
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true
]);

// Execute request
$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
$curlInfo = curl_getinfo($curl);

curl_close($curl);

echo "HTTP Status Code: " . $httpCode . "\n";
echo "cURL Error: " . ($curlError ?: "None") . "\n\n";
echo "Response:\n";
print_r($response);
echo "\n\n";

if ($response) {
    $decoded = json_decode($response, true);
    if ($decoded !== null) {
        echo "Decoded JSON:\n";
        print_r($decoded);
    } else {
        echo "Failed to decode JSON response.\n";
    }
}

echo "</pre>";
?>