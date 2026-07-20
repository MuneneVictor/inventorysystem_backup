<?php
require_once __DIR__ . "/header.php";
// ============================================================
// SECURE SESSION CONFIGURATION
// ============================================================
$timeout_duration = 7200; // 2 hours

// Start session with secure settings if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Strict'
    ]);
    session_start();
}

$current_url = $_SERVER['REQUEST_URI'];

// ============================================================
// CHECK IF USER IS LOGGED IN
// ============================================================
if (!isset($_SESSION['user_id'])) {
    // Store the current URL before redirecting
    $_SESSION['redirect_after_login'] = $current_url;
    header("Location: ../auth/login.php");
    exit();
}

// ============================================================
// SESSION TIMEOUT CHECK
// ============================================================
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    // Store the URL they were trying to access BEFORE destroying session
    $redirect_url = $current_url;
    
    // Clear all session data
    $_SESSION = array();
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    session_destroy();
    
    // Start new session for redirect
    session_start();
    $_SESSION['redirect_after_login'] = $redirect_url;
    $_SESSION['show_expired_popup'] = true;
    header("Location: ../auth/login.php?expired=1");
    exit();
}

// ============================================================
// UPDATE LAST ACTIVITY
// ============================================================
$_SESSION['last_activity'] = time();

// ============================================================
// REGENERATE SESSION ID PERIODICALLY (every 5 minutes)
// ============================================================
if (!isset($_SESSION['session_regenerated']) || (time() - $_SESSION['session_regenerated'] > 300)) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated'] = time();
}

// ============================================================
// VALIDATE USER EXISTS IN DATABASE
// ============================================================
require_once __DIR__ . "/../config/db.php";

$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, role, full_name, is_active, branch FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['is_active'] != 1) {
    // User no longer exists or is inactive
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// ============================================================
// STORE USER INFO FOR EASY ACCESS
// ============================================================
$role = $user['role'];
$user_name = $user['full_name'] ?? 'User';
$user_branch = $user['branch'] ?? null;

// ============================================================
// FUNCTION TO GET DEVICE FINGERPRINT (if needed)
// ============================================================
function getDeviceFingerprint() {
    $fingerprint = [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
    ];
    return hash('sha256', json_encode($fingerprint));
}

// ============================================================
// SESSION FIXATION PROTECTION - Validate IP and User Agent
// ============================================================
$current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Store initial values on first login
if (!isset($_SESSION['login_ip'])) {
    $_SESSION['login_ip'] = $current_ip;
    $_SESSION['login_ua'] = $current_ua;
}

// Check if IP or User Agent has changed significantly
// Note: IP can change for mobile users, so we only check if it's completely different
// This is a basic check - you can adjust the strictness as needed
if ($_SESSION['login_ip'] !== $current_ip && !empty($_SESSION['login_ip']) && !empty($current_ip)) {
    // IP changed - this could be a session hijacking attempt
    // Log the event but don't force logout immediately for mobile users
    error_log("Session IP changed for user {$user_id}: {$_SESSION['login_ip']} -> {$current_ip}");
    
    // Optional: Force logout on IP change (enable for high security)
    // session_destroy();
    // header("Location: ../auth/login.php");
    // exit();
}

// ============================================================
// CHECK FOR REDIRECT AFTER LOGIN (if coming from timeout)
// ============================================================
if (isset($_SESSION['redirect_after_login'])) {
    $redirect = $_SESSION['redirect_after_login'];
    unset($_SESSION['redirect_after_login']);
    // You can redirect here if needed
}

// ============================================================
// SECURITY HEADERS (Optional - can be added to all pages)
// ============================================================
// These are typically set in .htaccess or server config,
// but can also be set here for additional security.
// header("X-Frame-Options: DENY");
// header("X-Content-Type-Options: nosniff");
// header("Referrer-Policy: strict-origin-when-cross-origin");
?>