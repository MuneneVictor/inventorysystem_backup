<?php
date_default_timezone_set('Africa/Nairobi');
session_start();
require_once "config/db.php";
require_once "includes/header.php";

require_once 'PHPMailer-master/src/PHPMailer.php';
require_once 'PHPMailer-master/src/SMTP.php';
require_once 'PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$show_verification = false;
$verification_sent = false;

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function getDeviceFingerprint() {
    $fingerprint = [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
    ];
    return hash('sha256', json_encode($fingerprint));
}

function getDeviceName($user_agent) {
    if (strpos($user_agent, 'Windows') !== false) return 'Windows PC';
    if (strpos($user_agent, 'Mac') !== false) return 'Mac Computer';
    if (strpos($user_agent, 'iPhone') !== false) return 'iPhone';
    if (strpos($user_agent, 'iPad') !== false) return 'iPad';
    if (strpos($user_agent, 'Android') !== false) return 'Android Device';
    if (strpos($user_agent, 'Linux') !== false) return 'Linux Computer';
    return 'Unknown Device';
}

function getBrowserName($user_agent) {
    if (strpos($user_agent, 'Chrome') !== false) return 'Google Chrome';
    if (strpos($user_agent, 'Firefox') !== false) return 'Mozilla Firefox';
    if (strpos($user_agent, 'Safari') !== false) return 'Safari';
    if (strpos($user_agent, 'Edge') !== false) return 'Microsoft Edge';
    if (strpos($user_agent, 'Opera') !== false) return 'Opera';
    return 'Unknown Browser';
}

function getOS($user_agent) {
    if (strpos($user_agent, 'Windows 10') !== false) return 'Windows 10';
    if (strpos($user_agent, 'Windows 11') !== false) return 'Windows 11';
    if (strpos($user_agent, 'Mac OS X') !== false) return 'macOS';
    if (strpos($user_agent, 'iPhone') !== false) return 'iOS';
    if (strpos($user_agent, 'Android') !== false) return 'Android';
    if (strpos($user_agent, 'Linux') !== false) return 'Linux';
    return 'Unknown OS';
}

function getUserIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    return $ip;
}

function generateVerificationCode() {
    return strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

// ============================================================
// SEND VERIFICATION CODE EMAIL - PROFESSIONAL STYLING
// ============================================================
function sendVerificationEmail($toEmail, $full_name, $device_info, $ip, $code) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'victormunene207@gmail.com';
        $mail->Password   = 'trda huax aazp idjv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('victormunene207@gmail.com', 'Mombasa Computers');
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Login - Mombasa Computers';
        $mail->Body    = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    background-color: #f0f4f8;
                    margin: 0;
                    padding: 0;
                    line-height: 1.6;
                }
                .email-container {
                    max-width: 580px;
                    margin: 40px auto;
                    background: #ffffff;
                    border-radius: 16px;
                    overflow: hidden;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.08);
                }
                .email-header {
                    background: linear-gradient(135deg, #1a4b2a 0%, #2a7a3a 100%);
                    padding: 40px 35px 35px;
                    text-align: center;
                }
                .email-header .icon {
                    font-size: 48px;
                    color: white;
                    margin-bottom: 10px;
                    display: block;
                }
                .email-header h1 {
                    color: #ffffff;
                    font-size: 24px;
                    font-weight: 700;
                    margin: 0 0 6px 0;
                    letter-spacing: -0.5px;
                }
                .email-header p {
                    color: rgba(255,255,255,0.85);
                    font-size: 15px;
                    margin: 0;
                    font-weight: 400;
                }
                .email-body {
                    padding: 35px 35px 30px;
                }
                .greeting {
                    font-size: 16px;
                    color: #1e293b;
                    margin-bottom: 16px;
                }
                .greeting strong {
                    color: #1a4b2a;
                }
                .message-text {
                    color: #475569;
                    font-size: 15px;
                    margin-bottom: 24px;
                    line-height: 1.7;
                }
                .code-box {
                    background: #f0fdf4;
                    border: 2px dashed #2a7a3a;
                    border-radius: 12px;
                    padding: 28px 20px;
                    text-align: center;
                    margin: 24px 0;
                }
                .code-box .code {
                    font-size: 42px;
                    font-weight: 700;
                    color: #1a4b2a;
                    letter-spacing: 10px;
                    font-family: "SF Mono", "Courier New", monospace;
                    line-height: 1.2;
                }
                .code-box .label {
                    font-size: 13px;
                    color: #6b7280;
                    margin-top: 10px;
                }
                .expiry-note {
                    text-align: center;
                    font-size: 13px;
                    color: #6b7280;
                    margin: 12px 0 24px;
                    padding: 10px;
                    background: #f8fafc;
                    border-radius: 8px;
                }
                .expiry-note strong {
                    color: #dc2626;
                }
                .device-details {
                    background: #f8fafc;
                    border-radius: 12px;
                    padding: 20px 24px;
                    margin: 20px 0 24px;
                    border: 1px solid #e2e8f0;
                }
                .device-details .detail-title {
                    font-size: 13px;
                    font-weight: 600;
                    color: #64748b;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 14px;
                    border-bottom: 1px solid #e2e8f0;
                    padding-bottom: 10px;
                }
                .device-details .detail-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 0;
                    border-bottom: 1px solid #f1f5f9;
                }
                .device-details .detail-row:last-child {
                    border-bottom: none;
                }
                .device-details .detail-label {
                    color: #64748b;
                    font-size: 14px;
                    font-weight: 500;
                }
                .device-details .detail-value {
                    color: #0f172a;
                    font-size: 14px;
                    font-weight: 600;
                    text-align: right;
                }
                .security-notice {
                    background: #f1f5f9;
                    border-radius: 8px;
                    padding: 14px 18px;
                    margin: 20px 0 0;
                    font-size: 13px;
                    color: #475569;
                    display: flex;
                    align-items: flex-start;
                    gap: 10px;
                }
                .security-notice i {
                    color: #1a4b2a;
                    font-size: 18px;
                    margin-top: 2px;
                }
                .email-footer {
                    background: #f8fafc;
                    padding: 24px 35px;
                    text-align: center;
                    border-top: 1px solid #e2e8f0;
                }
                .email-footer p {
                    font-size: 12px;
                    color: #94a3b8;
                    margin: 4px 0;
                }
                .email-footer .brand {
                    color: #1a4b2a;
                    font-weight: 600;
                }
                @media (max-width: 480px) {
                    .email-container { margin: 20px auto; border-radius: 12px; }
                    .email-header { padding: 30px 20px 25px; }
                    .email-body { padding: 25px 20px 20px; }
                    .code-box .code { font-size: 32px; letter-spacing: 6px; }
                    .device-details { padding: 16px 18px; }
                    .device-details .detail-row { flex-direction: column; padding: 6px 0; }
                    .device-details .detail-value { text-align: left; margin-top: 2px; }
                    .email-footer { padding: 20px; }
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <!-- Header -->
                <div class="email-header">
                    <span class="icon"></span>
                    <h1>Verify Your Login</h1>
                    <p>Security Verification Required</p>
                </div>
                
                <!-- Body -->
                <div class="email-body">
                    <div class="greeting">
                        Dear <strong>' . htmlspecialchars($full_name) . '</strong>,
                    </div>
                    
                    <div class="message-text">
                        We detected a login attempt to your account from a new device. For your security, please enter the verification code below to complete your login.
                    </div>
                    
                    <!-- Verification Code -->
                    <div class="code-box">
                        <div class="code">' . htmlspecialchars($code) . '</div>
                        <div class="label">Enter this code to verify your device</div>
                    </div>
                    
                    <div class="expiry-note">
                        This code will expire in <strong>10 minutes</strong>
                    </div>
                    
                    <!-- Device Details -->
                    <div class="device-details">
                        <div class="detail-title"> Device Information</div>
                        <div class="detail-row">
                            <span class="detail-label">Device Type</span>
                            <span class="detail-value">' . htmlspecialchars($device_info['device_name']) . '</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Browser</span>
                            <span class="detail-value">' . htmlspecialchars($device_info['browser']) . '</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Operating System</span>
                            <span class="detail-value">' . htmlspecialchars($device_info['os']) . '</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">IP Address</span>
                            <span class="detail-value">' . htmlspecialchars($ip) . '</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Date &amp; Time</span>
                            <span class="detail-value">' . date('F j, Y g:i A') . '</span>
                        </div>
                    </div>
                    
                    <!-- Security Notice -->
                    <div class="security-notice">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <strong style="color: #0f172a;">If you did not attempt this login</strong>
                            <br>Please ignore this email. Your account remains secure.
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="email-footer">
                    <p>This is an automated security notification from <span class="brand">Mombasa Computers</span>.</p>
                    <p>© ' . date('Y') . ' Mombasa Computers. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';

        $mail->AltBody = "Verify Your Login\n\n" .
                         "Dear $full_name,\n\n" .
                         "We detected a login from a new device. Use this code to verify:\n\n" .
                         "Verification Code: $code\n" .
                         "Expires in: 10 minutes\n\n" .
                         "Device Information:\n" .
                         "Device: {$device_info['device_name']}\n" .
                         "Browser: {$device_info['browser']}\n" .
                         "OS: {$device_info['os']}\n" .
                         "IP: $ip\n" .
                         "Time: " . date('F j, Y g:i A') . "\n\n" .
                         "If this wasn't you, please ignore this email.\n\n" .
                         "Thank you for choosing Mombasa Computers!";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

// ============================================================
// SEND LOCK NOTIFICATION EMAIL
// ============================================================
function sendLockEmail($to, $name, $lock_type = 'device') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'victormunene207@gmail.com';
        $mail->Password   = 'trda huax aazp idjv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('victormunene207@gmail.com', 'Mombasa Computers');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = 'Security Alert - Mombasa Computers';
        $mail->Body    = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f0f4f8; margin: 0; padding: 0; }
                .container { max-width: 520px; margin: 40px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.08); }
                .header { background: linear-gradient(135deg, #1a4b2a 0%, #2a7a3a 100%); color: white; padding: 30px 25px; text-align: center; }
                .header h1 { margin: 0; font-size: 20px; }
                .content { padding: 30px 25px; }
                .content p { color: #475569; line-height: 1.7; }
                .alert-box { background: #fef2f2; border-left: 4px solid #dc2626; padding: 14px 18px; border-radius: 8px; margin: 16px 0; }
                .alert-box strong { color: #991b1b; }
                .footer { background: #f8fafc; padding: 20px 25px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
                .footer .brand { color: #1a4b2a; font-weight: 600; }
                @media (max-width: 480px) { .container { margin: 20px; border-radius: 12px; } .header { padding: 25px 20px; } .content { padding: 25px 20px; } }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1> Security Alert</h1>
                </div>
                <div class="content">
                    <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                    <p>Your ' . ($lock_type === 'device' ? 'device has been temporarily locked for <strong>30 minutes</strong>' : 'account has been temporarily locked for <strong>10 minutes</strong>') . ' due to multiple failed attempts.</p>
                    <div class="alert-box">
                        <strong> Security Action Taken</strong><br>
                        For your protection, we have locked ' . ($lock_type === 'device' ? 'this device' : 'your account') . ' temporarily.
                    </div>
                    <p>If this was you, please wait and try again. If you believe this was a mistake, please contact support.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' <span class="brand">Mombasa Computers</span>. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';

        $mail->AltBody = "Security Alert\n\n" .
                         "Dear $name,\n\n" .
                         "Your " . ($lock_type === 'device' ? 'device has been locked for 30 minutes' : 'account has been locked for 10 minutes') . " due to multiple failed attempts.\n\n" .
                         "Please wait and try again.\n\n" .
                         "Thank you for choosing Mombasa Computers!";

        $mail->send();
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
    }
}

// ============================================================
// PROCESS LOGIN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Something went wrong. Please try again.";
    }
    
    // Check if this is a verification code submission
    elseif (isset($_POST['verify_code']) && isset($_SESSION['pending_verification_user_id'])) {
        $entered_code = trim($_POST['verification_code'] ?? '');
        $user_id = (int)$_SESSION['pending_verification_user_id'];
        $device_id = $_SESSION['pending_verification_device_id'] ?? '';
        
        if (empty($entered_code)) {
            $error = "Something went wrong. Please try again.";
        } else {
            $stmt = $conn->prepare("SELECT * FROM user_devices WHERE user_id = ? AND device_id = ?");
            $stmt->execute([$user_id, $device_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) {
                $error = "Something went wrong. Please try again.";
                unset($_SESSION['pending_verification_user_id']);
                unset($_SESSION['pending_verification_device_id']);
            } elseif (!empty($device['locked_until']) && strtotime($device['locked_until']) > time()) {
                $error = "Something went wrong. Please try again later.";
            } elseif (strtotime($device['code_expires_at']) < time()) {
                $error = "Something went wrong. Please try again.";
                unset($_SESSION['pending_verification_user_id']);
                unset($_SESSION['pending_verification_device_id']);
            } elseif ($entered_code === $device['verification_code']) {
                $update = $conn->prepare("UPDATE user_devices SET is_verified = 1, failed_attempts = 0, locked_until = NULL WHERE id = ?");
                $update->execute([$device['id']]);
                
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['branch'] = $user['branch'];
                    $_SESSION['last_activity'] = time();
                    
                    $update = $conn->prepare("UPDATE users SET failed_attempts = 0, account_locked_until = NULL, last_login = NOW() WHERE id = ?");
                    $update->execute([$user['id']]);
                    
                    unset($_SESSION['pending_verification_user_id']);
                    unset($_SESSION['pending_verification_device_id']);
                    
                    // Check if there's a redirect URL from session timeout
                    if (isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
                        $redirect_url = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']);
                        header("Location: " . $redirect_url);
                        exit();
                    }
                    
                    // Redirect based on role
                    if ($_SESSION['role'] === 'super_admin') {
                        header("Location: dashboard/superadmindashboard.php");
                    } elseif ($_SESSION['role'] === 'manager') {
                        header("Location: dashboard/managerdashboard.php");
                    } elseif ($_SESSION['role'] === 'inventory_admin') {
                        header("Location: dashboard/inventorydashboard.php");
                    } elseif ($_SESSION['role'] === 'sales'){
                        header("Location: dashboard/salesdashboard.php");
                    } elseif ($_SESSION['role'] === 'software'){
                        header("Location: dashboard/softwaredashboard.php");
                    } elseif ($_SESSION['role'] === 'technician'){
                        header("Location: dashboard/techniciandashboard.php");
                    } elseif ($_SESSION['role'] === 'cashier'){
                        header("Location: dashboard/cashierdashboard.php");
                    }
                    exit();
                }
            } else {
                $failed = ($device['failed_attempts'] ?? 0) + 1;
                
                if ($failed >= 3) {
                    $lock_time = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    $update = $conn->prepare("UPDATE user_devices SET failed_attempts = ?, locked_until = ? WHERE id = ?");
                    $update->execute([$failed, $lock_time, $device['id']]);
                    
                    $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        sendLockEmail($user['email'], $user['full_name'], 'device');
                    }
                    
                    $error = "Something went wrong. Please try again later.";
                    unset($_SESSION['pending_verification_user_id']);
                    unset($_SESSION['pending_verification_device_id']);
                } else {
                    $update = $conn->prepare("UPDATE user_devices SET failed_attempts = ? WHERE id = ?");
                    $update->execute([$failed, $device['id']]);
                    $error = "Something went wrong. Please try again.";
                }
            }
        }
    }
    
    // Regular login attempt
    elseif (isset($_POST['email']) && !isset($_POST['verify_code'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $device_fingerprint = $_POST['device_fingerprint'] ?? '';
        $server_fingerprint = getDeviceFingerprint();
        $combined_fingerprint = hash('sha256', $device_fingerprint . $server_fingerprint);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Something went wrong. Please try again.";
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $error = "Something went wrong. Please try again.";
            } elseif ($user['is_active'] != 1) {
                $error = "Something went wrong. Please try again.";
            } elseif (!empty($user['account_locked_until']) && strtotime($user['account_locked_until']) > time()) {
                $error = "Something went wrong. Please try again later.";
            } elseif (password_verify($password, $user['password'])) {
                // Check if user has any verified devices
                $stmt = $conn->prepare("SELECT COUNT(*) FROM user_devices WHERE user_id = ? AND is_verified = 1");
                $stmt->execute([$user['id']]);
                $verifiedDeviceCount = $stmt->fetchColumn();
                
                // If no verified devices, first time login - allow without 2FA
                if ($verifiedDeviceCount == 0) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['branch'] = $user['branch'];
                    $_SESSION['last_activity'] = time();
                    
                    $user_ip = getUserIP();
                    $device_name = getDeviceName($_SERVER['HTTP_USER_AGENT']);
                    $browser_name = getBrowserName($_SERVER['HTTP_USER_AGENT']);
                    
                    $insert = $conn->prepare("INSERT INTO user_devices (user_id, device_id, device_name, browser_info, ip_address, first_seen, last_seen, times_seen, is_verified) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 1, 1)");
                    $insert->execute([
                        $user['id'],
                        $combined_fingerprint,
                        $device_name . ' - ' . $browser_name,
                        $_SERVER['HTTP_USER_AGENT'],
                        $user_ip
                    ]);
                    
                    $update = $conn->prepare("UPDATE users SET failed_attempts = 0, account_locked_until = NULL, last_login = NOW() WHERE id = ?");
                    $update->execute([$user['id']]);
                    
                    // Check if there's a redirect URL from session timeout
                    if (isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
                        $redirect_url = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']);
                        header("Location: " . $redirect_url);
                        exit();
                    }
                    
                    // Default redirect based on role
                    if ($_SESSION['role'] === 'super_admin') {
                        header("Location: dashboard/superadmindashboard.php");
                    } elseif ($_SESSION['role'] === 'manager') {
                        header("Location: dashboard/managerdashboard.php");
                    } elseif ($_SESSION['role'] === 'inventory_admin') {
                        header("Location: dashboard/inventorydashboard.php");
                    } elseif ($_SESSION['role'] === 'sales'){
                        header("Location: dashboard/salesdashboard.php");
                    } elseif ($_SESSION['role'] === 'software'){
                        header("Location: dashboard/softwaredashboard.php");
                    } elseif ($_SESSION['role'] === 'technician'){
                        header("Location: dashboard/techniciandashboard.php");
                    } elseif ($_SESSION['role'] === 'cashier'){
                        header("Location: dashboard/cashierdashboard.php");
                    }
                    exit();
                }
                
                // Check if this device is already verified
                $stmt = $conn->prepare("SELECT * FROM user_devices WHERE user_id = ? AND device_id = ?");
                $stmt->execute([$user['id'], $combined_fingerprint]);
                $device = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($device && $device['is_verified'] == 1) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['branch'] = $user['branch'];
                    $_SESSION['last_activity'] = time();
                    
                    $update = $conn->prepare("UPDATE user_devices SET last_seen = NOW(), times_seen = times_seen + 1, ip_address = ? WHERE id = ?");
                    $update->execute([getUserIP(), $device['id']]);
                    
                    $update = $conn->prepare("UPDATE users SET failed_attempts = 0, account_locked_until = NULL, last_login = NOW() WHERE id = ?");
                    $update->execute([$user['id']]);
                    
                    // Check if there's a redirect URL from session timeout
                    if (isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
                        $redirect_url = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']);
                        header("Location: " . $redirect_url);
                        exit();
                    }
                    
                    // Default redirect based on role
                    if ($_SESSION['role'] === 'super_admin') {
                        header("Location: dashboard/superadmindashboard.php");
                    } elseif ($_SESSION['role'] === 'manager') {
                        header("Location: dashboard/managerdashboard.php");
                    } elseif ($_SESSION['role'] === 'inventory_admin') {
                        header("Location: dashboard/inventorydashboard.php");
                    } elseif ($_SESSION['role'] === 'sales'){
                        header("Location: dashboard/salesdashboard.php");
                    } elseif ($_SESSION['role'] === 'software'){
                        header("Location: dashboard/softwaredashboard.php");
                    } elseif ($_SESSION['role'] === 'technician'){
                        header("Location: dashboard/techniciandashboard.php");
                    } elseif ($_SESSION['role'] === 'cashier'){
                        header("Location: dashboard/cashierdashboard.php");
                    }
                    exit();
                } elseif ($device && $device['is_verified'] == 0) {
                    if (!empty($device['locked_until']) && strtotime($device['locked_until']) > time()) {
                        $error = "Something went wrong. Please try again later.";
                    } else {
                        $code = generateVerificationCode();
                        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        
                        $update = $conn->prepare("UPDATE user_devices SET verification_code = ?, code_expires_at = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?");
                        $update->execute([$code, $expires, $device['id']]);
                        
                        $device_info = [
                            'device_name' => getDeviceName($_SERVER['HTTP_USER_AGENT']),
                            'browser' => getBrowserName($_SERVER['HTTP_USER_AGENT']),
                            'os' => getOS($_SERVER['HTTP_USER_AGENT'])
                        ];
                        
                        sendVerificationEmail($user['email'], $user['full_name'], $device_info, getUserIP(), $code);
                        
                        $_SESSION['pending_verification_user_id'] = $user['id'];
                        $_SESSION['pending_verification_device_id'] = $device['device_id'];
                        $show_verification = true;
                        $verification_sent = true;
                    }
                } else {
                    // New device
                    $user_ip = getUserIP();
                    $device_name = getDeviceName($_SERVER['HTTP_USER_AGENT']);
                    $browser_name = getBrowserName($_SERVER['HTTP_USER_AGENT']);
                    $os_name = getOS($_SERVER['HTTP_USER_AGENT']);
                    
                    $code = generateVerificationCode();
                    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    $insert = $conn->prepare("INSERT INTO user_devices (user_id, device_id, device_name, browser_info, ip_address, first_seen, last_seen, times_seen, is_verified, verification_code, code_expires_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 1, 0, ?, ?)");
                    $insert->execute([
                        $user['id'],
                        $combined_fingerprint,
                        $device_name . ' - ' . $browser_name,
                        $_SERVER['HTTP_USER_AGENT'],
                        $user_ip,
                        $code,
                        $expires
                    ]);
                    
                    $device_info = [
                        'device_name' => $device_name,
                        'browser' => $browser_name,
                        'os' => $os_name
                    ];
                    
                    sendVerificationEmail($user['email'], $user['full_name'], $device_info, $user_ip, $code);
                    
                    $_SESSION['pending_verification_user_id'] = $user['id'];
                    $_SESSION['pending_verification_device_id'] = $combined_fingerprint;
                    $show_verification = true;
                    $verification_sent = true;
                }
            } else {
                // Wrong password - track failed attempts
                $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $failed_attempts = ($user['failed_attempts'] ?? 0) + 1;
                    
                    if ($failed_attempts >= 5) {
                        $lock_time = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        $update = $conn->prepare("UPDATE users SET failed_attempts = ?, account_locked_until = ? WHERE id = ?");
                        $update->execute([$failed_attempts, $lock_time, $user['id']]);
                        
                        sendLockEmail($user['email'], $user['full_name'], 'account');
                        
                        $error = "Something went wrong. Please try again later.";
                    } else {
                        $update = $conn->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
                        $update->execute([$failed_attempts, $user['id']]);
                        $error = "Invalid email or password.";
                    }
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        }
    }
}

// ============================================================
// CLEAN UP EXPIRED VERIFICATION CODES
// ============================================================
$cleanup = $conn->prepare("UPDATE user_devices SET verification_code = NULL, code_expires_at = NULL WHERE code_expires_at < NOW()");
$cleanup->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Inventory Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #e8f0ec 0%, #d4e2da 100%);
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            max-width: 440px;
            width: 100%;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.06), 0 5px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .card-header {
            padding: 32px 32px 0 32px;
            text-align: center;
        }

        .logo-wrapper {
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
        }

        .logo-wrapper img {
            height: 65px;
            width: auto;
            object-fit: contain;
        }

        .card-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a4d2e;
            letter-spacing: -0.3px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .card-header p {
            font-size: 0.8rem;
            color: #6b7c72;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .card-body {
            padding: 28px 32px 32px 32px;
        }

        .error-alert {
            background: #fef3f2;
            border-left: 4px solid #dc2626;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .error-alert i {
            color: #dc2626;
            font-size: 1.1rem;
        }

        .error-alert span {
            font-size: 0.85rem;
            color: #991b1b;
            font-weight: 500;
            line-height: 1.4;
        }
        
        .success-alert {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .success-alert i {
            color: #10b981;
            font-size: 1.1rem;
        }

        .success-alert span {
            font-size: 0.85rem;
            color: #065f46;
            font-weight: 500;
            line-height: 1.4;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #2d3e35;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa8a0;
            font-size: 1rem;
            pointer-events: none;
        }

        .input-wrapper input {
            width: 100%;
            padding: 12px 12px 12px 42px;
            font-size: 0.9rem;
            border: 1.5px solid #e2e8e4;
            border-radius: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s ease;
            outline: none;
            background: #fefefe;
            color: #1f2c26;
        }

        .input-wrapper input:focus {
            border-color: #2b6e46;
            box-shadow: 0 0 0 3px rgba(43, 110, 70, 0.1);
        }

        .input-wrapper input::placeholder {
            color: #bcc9c2;
            font-weight: 400;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .checkbox-group input {
            width: 17px;
            height: 17px;
            cursor: pointer;
            accent-color: #2b6e46;
        }

        .checkbox-group label {
            font-size: 0.85rem;
            color: #4a5f54;
            cursor: pointer;
            user-select: none;
            font-weight: 500;
        }

        .btn-login {
            width: 100%;
            background: #1f5e3a;
            border: none;
            padding: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            border-radius: 40px;
            color: white;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login i {
            font-size: 1rem;
            transition: transform 0.2s;
        }

        .btn-login:hover {
            background: #154d2e;
        }

        .btn-login:hover i {
            transform: translateX(3px);
        }

        .btn-verify {
            width: 100%;
            background: #1f5e3a;
            border: none;
            padding: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            border-radius: 40px;
            color: white;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 16px;
        }

        .btn-verify:hover {
            background: #154d2e;
        }

        .register-section {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eef2ef;
        }

        .register-section p {
            font-size: 0.85rem;
            color: #5a6e64;
        }

        .register-section a {
            color: #1f5e3a;
            text-decoration: none;
            font-weight: 700;
            margin-left: 5px;
            transition: color 0.2s;
        }

        .register-section a:hover {
            color: #0e3a23;
            text-decoration: underline;
        }

        .verification-section {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .verification-section .icon {
            text-align: center;
            font-size: 2rem;
            color: #1a4b2a;
            margin-bottom: 8px;
        }

        .verification-section h3 {
            text-align: center;
            font-size: 1rem;
            color: #065f46;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .verification-section p {
            text-align: center;
            font-size: 0.85rem;
            color: #4b5e54;
            margin-bottom: 16px;
        }

        .verification-section .code-input {
            text-align: center;
            font-size: 1.3rem;
            letter-spacing: 12px;
            font-weight: 700;
            padding: 10px;
            border: 2px solid #2a7a3a;
            border-radius: 10px;
            width: 100%;
            max-width: 200px;
            margin: 0 auto;
            display: block;
            font-family: 'Courier New', monospace;
        }

        .verification-section .code-input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.2);
        }

        .verification-section .resend-link {
            text-align: center;
            margin-top: 12px;
            font-size: 0.8rem;
        }

        .verification-section .resend-link a {
            color: #1a4b2a;
            text-decoration: none;
            font-weight: 600;
        }

        .verification-section .resend-link a:hover {
            text-decoration: underline;
        }

        .back-link {
            text-align: center;
            margin-top: 12px;
        }

        .back-link a {
            color: #6b7c72;
            text-decoration: none;
            font-size: 0.85rem;
        }

        .back-link a:hover {
            color: #1f5e3a;
        }

        .footer {
            background: #fafcfb;
            padding: 16px 32px;
            text-align: center;
            border-top: 1px solid #eef2ef;
        }

        .footer p {
            font-size: 0.7rem;
            color: #8ba094;
        }

        .footer a {
            color: #8ba094;
            text-decoration: none;
        }

        .footer a:hover {
            color: #1f5e3a;
        }

        @media (max-width: 480px) {
            .card-body {
                padding: 24px 24px 28px 24px;
            }
            .card-header {
                padding: 28px 24px 0 24px;
            }
            .logo-wrapper img {
                height: 55px;
            }
            .card-header h1 {
                font-size: 1.3rem;
            }
            .verification-section .code-input {
                font-size: 1.1rem;
                letter-spacing: 8px;
                max-width: 160px;
            }
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="card-header">
        <div class="logo-wrapper">
            <img src="/inventory_system/assets/MC-LOGO.png" alt="Mombasacomputers Logo">
        </div>
        <h1>Inventory System</h1>
        <p>Secure Access Portal</p>
    </div>

    <div class="card-body">
        <?php if($error): ?>
            <div class="error-alert">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if($verification_sent): ?>
            <div class="success-alert">
                <i class="fas fa-check-circle"></i>
                <span>Verification code sent to your email. Please check your inbox.</span>
            </div>
        <?php endif; ?>

        <?php if ($show_verification): ?>
            <!-- Verification Section -->
            <div class="verification-section">
                <div class="icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Device Verification Required</h3>
                <p>Enter the 6-digit code sent to your email</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="verify_code" value="1">
                    
                    <input type="text" name="verification_code" class="code-input" placeholder="••••••" maxlength="6" required autofocus>
                    
                    <button type="submit" class="btn-verify">
                        <i class="fas fa-check"></i> Verify Device
                    </button>
                </form>
            </div>
            
            <div class="back-link">
                <a href="auth/login.php"><i class="fas fa-arrow-left"></i> Back to login</a>
            </div>
            
        <?php else: ?>
            <!-- Login Form -->
            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="device_fingerprint" id="device_fingerprint" value="">
                
                <div class="input-group">
                    <label>EMAIL ADDRESS</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="your@email.com" required autofocus>
                    </div>
                </div>

                <div class="input-group">
                    <label>PASSWORD</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="showPassword">
                    <label for="showPassword"><i class="far fa-eye" style="margin-right: 6px;"></i> Show password</label>
                </div>

                <button type="submit" class="btn-login">
                    <span>Sign In</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="register-section">
                <p>Don't have an account? <a href="auth/register.php">Register</a></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date("Y"); ?> Mombasacomputers. All rights reserved.</p>
    </div>
</div>

<script>
    // Password visibility toggle
    const passwordField = document.getElementById('password');
    const showCheckbox = document.getElementById('showPassword');
    
    showCheckbox.addEventListener('change', function() {
        passwordField.type = this.checked ? 'text' : 'password';
    });
    
    // Generate device fingerprint
    function generateDeviceFingerprint() {
        const fingerprint = {
            userAgent: navigator.userAgent,
            language: navigator.language,
            platform: navigator.platform,
            screenResolution: screen.width + 'x' + screen.height,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            hardwareConcurrency: navigator.hardwareConcurrency || 'unknown',
            deviceMemory: navigator.deviceMemory || 'unknown'
        };
        
        let fingerprintStr = JSON.stringify(fingerprint);
        let hash = 0;
        for (let i = 0; i < fingerprintStr.length; i++) {
            const char = fingerprintStr.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        
        return Math.abs(hash).toString(16);
    }
    
    // Set fingerprint in hidden field when form is submitted
    document.getElementById('loginForm').addEventListener('submit', function() {
        document.getElementById('device_fingerprint').value = generateDeviceFingerprint();
    });
    
    // Auto-focus verification code if visible
    document.addEventListener('DOMContentLoaded', function() {
        const codeInput = document.querySelector('.code-input');
        if (codeInput) {
            codeInput.focus();
        }
    });
</script>

</body>
</html>