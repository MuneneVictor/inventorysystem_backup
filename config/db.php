<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
$username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
$password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';

try {
    $conn = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4",$username,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>False
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Database connection failed: '.$e->getMessage());
    exit('Database connection failed. Please contact the administrator.');
}
