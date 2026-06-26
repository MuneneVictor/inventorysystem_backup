<?php
require_once "header.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /inventory_system/auth/login.php");
    exit();
}

// Role-based protection (simple but effective)
function allow($roles = []) {
    if (!in_array($_SESSION['role'], $roles)) {
        die("<h3 style='color:red;text-align:center;margin-top:50px;'>ACCESS DENIED</h3>");
    }
}
?>
