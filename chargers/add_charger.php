<?php
session_start();
require_once "../includes/auth_check.php";

// Optional: prevent direct access if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loading...</title>
</head>
<body>

<div id="page-content">
    Loading...
</div>

<script src="loader.js"></script>

</body>
</html>