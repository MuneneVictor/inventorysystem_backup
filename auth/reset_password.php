<?php
session_start();
date_default_timezone_set('Africa/Nairobi');
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// CSRF token generation (if not set)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Only super_admin can access
if ($_SESSION['role'] !== 'super_admin') {
    die("Access denied! Only Super Administrators can reset user passwords.");
}

// Include PHPMailer
require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';
require_once '../PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$user_id = 0;
$user_email = '';
$user_name = '';
$error = '';
$success = '';

// ----------------------------------------------
// 1. Handle initial POST from view_users.php (confirmReset)
// ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && !isset($_POST['send_code'])) {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security validation failed. Please try again.";
        header("Location: view_users.php");
        exit();
    }

    $uid = (int)$_POST['user_id'];
    if ($uid <= 0) {
        $_SESSION['error'] = "Invalid user ID.";
        header("Location: view_users.php");
        exit();
    }

    // Verify user exists
    $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE id = :id");
    $stmt->execute(['id' => $uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $_SESSION['error'] = "User not found.";
        header("Location: view_users.php");
        exit();
    }

    // Store user ID in session and redirect to GET version of this page
    $_SESSION['reset_user_id'] = $uid;
    header("Location: reset_password.php");
    exit();
}

// ----------------------------------------------
// 2. GET request (display form) OR POST request for sending code
// ----------------------------------------------

// Determine which user we are working with
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = (int)$_GET['id'];
} elseif (isset($_SESSION['reset_user_id'])) {
    $user_id = (int)$_SESSION['reset_user_id'];
} elseif (isset($_POST['user_id']) && isset($_POST['send_code'])) {
    $user_id = (int)$_POST['user_id'];
} else {
    // No user specified, redirect to view_users
    $_SESSION['error'] = "No user specified.";
    header("Location: view_users.php");
    exit();
}

// Fetch user details
$stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: view_users.php");
    exit();
}
$user_email = $user['email'];
$user_name = $user['full_name'];

// ----------------------------------------------
// 3. Handle "Send Code" action
// ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_code'])) {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
    } else {
        // Generate a 6-digit random code
        $reset_code = random_int(100000, 999999);
        $expiry_time = date('Y-m-d H:i:s', strtotime('+20 minutes'));

        try {
            // Update user record with reset code and expiry using exact column names
            $stmt = $conn->prepare("
                UPDATE users 
                SET reset_code = :code, code_expiry = :expiry 
                WHERE id = :id
            ");
            $stmt->execute([
                'code'   => $reset_code,
                'expiry' => $expiry_time,
                'id'     => $user_id
            ]);

            // Send email using PHPMailer
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'victormunene207@gmail.com';
            $mail->Password   = 'trda huax aazp idjv';  // Use environment variables in production!
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('victormunene207@gmail.com', 'Mombasa Computers');
            $mail->addAddress($user_email, $user_name);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Code - Mombasa Computers';

            // Professional email content (no emojis/icons)
            $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f7fa; padding: 20px; margin: 0; }
                        .container { max-width: 550px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
                        .header { background: #1a4b2a; padding: 28px 20px; text-align: center; }
                        .header h1 { color: #ffffff; font-size: 22px; font-weight: 600; margin: 0; letter-spacing: 0.5px; }
                        .content { padding: 30px 30px 20px; }
                        .greeting { font-size: 16px; color: #1f2937; margin-bottom: 12px; }
                        .greeting strong { color: #1a4b2a; }
                        .message { font-size: 15px; line-height: 1.6; color: #374151; margin-bottom: 20px; }
                        .code-box { background: #f3f4f6; border-radius: 8px; padding: 16px 20px; text-align: center; margin: 20px 0; border-left: 4px solid #1a4b2a; }
                        .code-box .code { font-size: 32px; font-weight: 700; color: #1a4b2a; letter-spacing: 6px; font-family: 'Courier New', monospace; }
                        .note { font-size: 14px; color: #6b7280; margin-top: 12px; }
                        .note strong { color: #1f2937; }
                        .footer { padding: 16px 30px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 13px; color: #9ca3af; }
                        .footer a { color: #1a4b2a; text-decoration: none; }
                        .footer a:hover { text-decoration: underline; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Mombasa Computers</h1>
                        </div>
                        <div class='content'>
                            <p class='greeting'>Dear <strong>" . htmlspecialchars($user_name) . "</strong>,</p>
                            <p class='message'>
                                A password reset request has been initiated for your account on the 
                                <strong>Mombasa Computers Inventory System</strong>.
                                To reset your password, please use the following code:
                            </p>
                            <div class='code-box'>
                                <span class='code'>" . $reset_code . "</span>
                            </div>
                            <p class='message'>
                                This code is valid for <strong>20 minutes</strong> and can only be used once.
                                Enter it on the password reset page to set a new password.
                            </p>
                            <p class='note'>
                                If you did not request this reset, please ignore this email. 
                                Your password will remain unchanged.
                            </p>
                        </div>
                        <div class='footer'>
                            &copy; " . date('Y') . " Mombasa Computers &bull; All rights reserved.
                            <br>This is an automated message, please do not reply.
                        </div>
                    </div>
                </body>
                </html>
            ";

            $mail->AltBody = "Password Reset Code for Mombasa Computers Inventory System\n\n"
                            . "Dear " . $user_name . ",\n\n"
                            . "A password reset request has been initiated for your account.\n"
                            . "Your reset code is: " . $reset_code . "\n"
                            . "This code is valid for 20 minutes and can only be used once.\n\n"
                            . "If you did not request this reset, please ignore this email.\n\n"
                            . "Mombasa Computers";

            $mail->send();

            $success = "A password reset code has been sent to <strong>" . htmlspecialchars($user_email) . "</strong> successfully!";
        } catch (Exception $e) {
            $error = "Failed to send email: " . $e->getMessage();
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Clear session reset user ID after use (so it doesn't persist)
unset($_SESSION['reset_user_id']);

// ----------------------------------------------
// Output the page
// ----------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Reset Password | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ----- CSS (identical style to generate_code.php) ----- */
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
            --primary-dark: #0f3a1e;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --font-sans: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        @import url('https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-sans);
            background: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
            overflow-x: hidden;
        }
        .main-content {
            padding: 2rem 2rem 1rem;
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;
            background: var(--gray-100);
            transition: margin-left 0.3s ease, width 0.3s ease, padding 0.3s ease;
            overflow-x: hidden;
            max-width: 100%;
        }
        .page-header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        .page-header h1 {
            font-size: 1.75rem;
            color: var(--gray-800);
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .form-container { max-width: 600px; margin: 0 auto; }
        .card {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .card-header {
            background: var(--gray-50);
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header h2 i { color: var(--primary); }
        .card-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        .form-group label .required { color: #dc2626; margin-left: 0.25rem; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            background: white;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }
        .form-group .help-text {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.5rem;
        }
        .form-group input[readonly] {
            background: var(--gray-50);
            cursor: not-allowed;
        }
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert i { font-size: 1.25rem; }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--font-sans);
            width: 100%;
            justify-content: center;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); transform: translateY(-1px); }
        .btn-secondary { background: var(--gray-200); color: var(--gray-700); }
        .btn-secondary:hover { background: var(--gray-300); }
        .info-box {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            margin-top: 1.5rem;
            border: 1px solid var(--gray-200);
        }
        .info-box h3 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .info-box ul { margin-left: 1.5rem; color: var(--gray-600); font-size: 0.85rem; }
        .info-box li { margin-bottom: 0.5rem; }
        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }
        .btn-group {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }
        .btn-group .btn { width: auto; flex: 1; }
        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1rem 1.25rem; }
            .card-header { padding: 1rem 1.25rem; }
            .card-header h2 { font-size: 1.1rem; }
            .card-body { padding: 1.25rem; }
            .btn-group { flex-direction: column; }
            .btn-group .btn { width: 100%; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header h1 { font-size: 1.1rem; }
            .card-body { padding: 1rem; }
        }
    </style>
</head>
<body>

<?php include "../includes/sidebar.php"; ?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-key"></i>
            Reset User Password
        </h1>
        <div class="breadcrumb">
            <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <a href="view_users.php">Users</a>
            <span> / </span>
            <span>Reset Password</span>
        </div>
    </div>

    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-envelope"></i>
                    Send Password Reset Code
                </h2>
            </div>
            <div class="card-body">
                <!-- Alert Messages -->
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?= $success ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="user_id" value="<?= $user_id ?>">
                    <input type="hidden" name="send_code" value="1">

                    <div class="form-group">
                        <label>User <span class="required">*</span></label>
                        <input type="text" value="<?= htmlspecialchars($user_name) ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user_email) ?>" readonly>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i>
                            A 6‑digit reset code will be sent to this email address.
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Send a password reset code to <?= htmlspecialchars($user_email) ?>?')">
                            <i class="fas fa-paper-plane"></i> Send Reset Code
                        </button>
                        <a href="view_users.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                    </div>
                </form>

                <div class="info-box">
                    <h3>
                        <i class="fas fa-lightbulb"></i>
                        How it works:
                    </h3>
                    <ul>
                        <li>A 6‑digit reset code will be generated and sent to the user's email.</li>
                        <li>The code is valid for <strong>20 minutes</strong> and can be used only once.</li>
                        <li>The user will enter this code on the password reset page to set a new password.</li>
                        <li>If the user doesn't receive the email, you can resend by clicking again.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<script>
// Mobile responsive adjustments (same as other pages)
document.addEventListener('DOMContentLoaded', function() {
    function adjustMainContent() {
        const mainContent = document.querySelector('.main-content');
        const sidebar = document.querySelector('.sidebar');
        if (window.innerWidth <= 1200) {
            if (mainContent) {
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
                mainContent.style.paddingTop = '5rem';
            }
        } else {
            if (mainContent && sidebar) {
                mainContent.style.marginLeft = '260px';
                mainContent.style.width = 'calc(100% - 260px)';
                mainContent.style.paddingTop = '';
            }
        }
    }
    adjustMainContent();
    window.addEventListener('resize', adjustMainContent);
    window.addEventListener('orientationchange', adjustMainContent);
});
</script>

</body>
</html>