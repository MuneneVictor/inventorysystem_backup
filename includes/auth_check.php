<?php
require_once "../includes/header.php";
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Strict'
    ]);
    session_start();
}


$timeout_duration = 10800; // 15 minutes
$current_url = $_SERVER['REQUEST_URI'];


if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $current_url;
    header("Location: ../auth/login.php"); 
    exit();
}

// ----------------------
// SESSION TIMEOUT CHECK
// ----------------------
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['redirect_after_login'] = $current_url;
    $_SESSION['show_expired_popup'] = true;
    header("Location: /auth/login");  
    exit();
}


$_SESSION['last_activity'] = time();


$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
?>