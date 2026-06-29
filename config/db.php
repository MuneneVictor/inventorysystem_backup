<?php
// Database connection settings
$host = "localhost";     // your server
$dbname = "inventory_system";  // your database name
$username = "root";      // your MySQL username
$password = "@MUNENE";          // your MySQL password

try {
    // Create PDO instance
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);

    // Enable errors for debugging (remove or change before production)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Emulate prepares off for better protection
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch (PDOException $e) {
    // Stop execution and show error
    die("Database Connection Failed: " . $e->getMessage());
}
?>
