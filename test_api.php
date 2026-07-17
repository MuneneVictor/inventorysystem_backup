<?php
// test_api.php - Test PayHero API directly

// ============================================
// CONFIGURATION - UPDATE THESE VALUES
// ============================================
$username = "YOUR_USERNAME";        // REPLACE with your username
$password = "YOUR_PASSWORD";        // REPLACE with your password
$channel_id = "10427";

// Test data
$phone = "254712345678"; // Replace with a real phone number
$amount = 10;

// ============================================
// TEST START
// ============================================

echo "<!DOCTYPE html>
<html>
<head>
    <title>PayHero API Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow: auto; }
        .box { border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 8px; }
    </style>
</head>
<body>
    <h1>PayHero API Test</h1>";

// Check credentials
if (empty($username) || $username === 'YOUR_USERNAME') {
    echo '<div class="box error"><strong>Error:</strong> Please set your username in test_api.php</div>';
    exit;
}

if (empty($password) || $password === 'YOUR_PASSWORD') {
    echo '<div class="box error"><strong>Error:</strong> Please set your password in test_api.php</div>';
    exit;
}

echo "<div class='box'>";
echo "<strong>Testing with:</strong><br>";
echo "Username: " . htmlspecialchars($username) . "<br>";
echo "Channel ID: " . htmlspecialchars($channel_id) . "<br>";
echo "Phone: " . htmlspecialchars($phone) . "<br>";
echo "Amount: " . htmlspecialchars($amount) . "<br>";
echo "</div>";

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

echo "<div class='box'>";
echo "<strong>Request Data:</strong><br>";
echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
echo "</div>";

// Initialize cURL
$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://backend.payhero.co.ke/api/v2/payments",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        "Authorization: Basic " . $auth,
        "Content-Type: application/json",
        "Accept: application/json"
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HEADER => true // Include headers in output
]);

// Execute request
$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
$headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

curl_close($curl);

// Split headers and body
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

echo "<div class='box'>";
echo "<strong>Response:</strong><br>";
echo "HTTP Status Code: <strong>" . $httpCode . "</strong><br>";
echo "cURL Error: " . ($curlError ?: "None") . "<br>";
echo "</div>";

if ($curlError) {
    echo '<div class="box error"><strong>cURL Error:</strong> ' . htmlspecialchars($curlError) . '</div>';
} else {
    echo "<div class='box'>";
    echo "<strong>Headers:</strong><br>";
    echo "<pre>" . htmlspecialchars($headers) . "</pre>";
    echo "</div>";
    
    echo "<div class='box'>";
    echo "<strong>Body:</strong><br>";
    echo "<pre>" . htmlspecialchars($body) . "</pre>";
    echo "</div>";
    
    // Try to decode JSON
    $decoded = json_decode($body, true);
    if ($decoded !== null) {
        echo "<div class='box'>";
        echo "<strong>Decoded JSON:</strong><br>";
        echo "<pre>" . print_r($decoded, true) . "</pre>";
        echo "</div>";
        
        if (isset($decoded['status']) && $decoded['status'] === 'success') {
            echo '<div class="box success"><strong>SUCCESS!</strong> STK Push sent successfully.</div>';
        } elseif (isset($decoded['error'])) {
            echo '<div class="box error"><strong>API Error:</strong> ' . htmlspecialchars($decoded['error']) . '</div>';
        } elseif (isset($decoded['message'])) {
            echo '<div class="box error"><strong>Message:</strong> ' . htmlspecialchars($decoded['message']) . '</div>';
        } else {
            echo '<div class="box"><strong>Response:</strong> Unknown response format.</div>';
        }
    } else {
        echo '<div class="box error"><strong>Error:</strong> Failed to decode JSON response.</div>';
    }
}

echo "
</body>
</html>";
?>