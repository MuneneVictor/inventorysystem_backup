<?php

$rootPath = dirname(__DIR__);
$envFile = $rootPath . DIRECTORY_SEPARATOR . '.env';
if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES);

    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);

            // Ignore blank lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Ignore invalid lines
            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);

            $name = trim($name);
            $value = trim($value);

            // Only accept valid environment variable names
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $name)) {
                continue;
            }

            // Remove matching surrounding quotes
            if (
                strlen($value) >= 2 &&
                (
                    ($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
                    ($value[0] === "'" && $value[strlen($value) - 1] === "'")
                )
            ) {
                $value = substr($value, 1, -1);
            }

            // Do not overwrite environment variables already set by the server
            if (getenv($name) === false && !array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
                putenv($name . '=' . $value);
            }
        }
    }
}

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: null;
$dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: null;
$username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: null;
$password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');

if ($password === false) {
    $password = '';
}

/**
 * Fail safely if required configuration is missing.
 */
if ($host === null || $dbname === null || $username === null) {
    error_log('Database configuration is incomplete.');

    http_response_code(500);
    exit('Database configuration is missing. Please contact the administrator.');
}

try {
    $conn = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);


    error_log('Database connection failed.');

    exit('Database connection failed. Please contact the administrator.');
}