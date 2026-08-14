<?php
// test_callback.php - Simulate a callback for testing

$sale_id = $_GET['sale_id'] ?? 1;
$type = $_GET['type'] ?? 'success';

$callbackUrl = 'https://inventory.vimarktech.com../sales/mpesa_callback.php?sale_id=' . $sale_id;

if ($type === 'success') {
    $data = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => 'TEST-12345',
                'CheckoutRequestID' => 'TEST-67890',
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 100],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'TEST001'],
                        ['Name' => 'TransactionDate', 'Value' => '20260118123456'],
                        ['Name' => 'PhoneNumber', 'Value' => '254712345678']
                    ]
                ]
            ]
        ]
    ];
    $label = 'SUCCESS';
} elseif ($type === 'cancelled') {
    $data = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => 'TEST-12345',
                'CheckoutRequestID' => 'TEST-67890',
                'ResultCode' => 1032,
                'ResultDesc' => 'Request cancelled by user'
            ]
        ]
    ];
    $label = 'CANCELLED';
} else {
    $data = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => 'TEST-12345',
                'CheckoutRequestID' => 'TEST-67890',
                'ResultCode' => 1,
                'ResultDesc' => 'Insufficient funds'
            ]
        ]
    ];
    $label = 'FAILED';
}

echo "<h2>Testing Callback: $label</h2>";
echo "<p>Callback URL: <code>$callbackUrl</code></p>";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $callbackUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<h3>Response:</h3>";
echo "HTTP Code: " . $httpCode . "<br>";
if ($curlError) echo "cURL Error: " . $curlError . "<br>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Check debug log
$logFile = __DIR__ . '/callback_debug.log';
if (file_exists($logFile)) {
    echo "<h3>Last 5 log entries:</h3>";
    $logs = file($logFile);
    $lastLogs = array_slice($logs, -5);
    echo "<pre>" . htmlspecialchars(implode('', $lastLogs)) . "</pre>";
}
?>