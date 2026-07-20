<?php
session_start();
date_default_timezone_set('Africa/Nairobi');
require_once "../config/db.php";


// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$step = 'code'; // 'code' or 'password'
$email = '';
$user_id = 0;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
    } else {
        // Step 1: Verify code
        if (isset($_POST['verify_code'])) {
            $email = trim($_POST['email'] ?? '');
            $code = trim($_POST['reset_code'] ?? '');

            if (empty($email) || empty($code)) {
                $error = "Please provide both email and reset code.";
            } else {
                // Look up user
                $stmt = $conn->prepare("SELECT id, email, full_name, reset_code, code_expiry FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $error = "Invalid email or reset code.";
                } elseif (empty($user['reset_code']) || empty($user['code_expiry'])) {
                    $error = "No active reset code found for this email. Please contact your administrator.";
                } elseif (strtotime($user['code_expiry']) < time()) {
                    $error = "Invalid or expired reset code.";
                } elseif ($code == $user['reset_code']) {
                    // Code is valid, move to password step
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_email'] = $user['email'];
                    $step = 'password';
                    $success = "Code verified. Please set your new password.";
                } else {
                    $error = "Invalid reset code. Please check and try again.";
                }
            }
        }

        // Step 2: Set new password
        if (isset($_POST['set_password'])) {
            if (!isset($_SESSION['reset_user_id'])) {
                $error = "Session expired. Please restart the process.";
            } else {
                $user_id = (int)$_SESSION['reset_user_id'];
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                // Validate password requirements
                $errors = [];
                if (strlen($new_password) < 6) {
                    $errors[] = "Password must be at least 6 characters long.";
                }
                if (!preg_match('/[0-9]/', $new_password)) {
                    $errors[] = "Password must contain at least one number.";
                }
                if (!preg_match('/[^a-zA-Z0-9]/', $new_password)) {
                    $errors[] = "Password must contain at least one symbol (e.g., !@#$%^&*).";
                }
                if ($new_password !== $confirm_password) {
                    $errors[] = "Passwords do not match.";
                }

                if (!empty($errors)) {
                    $error = implode(" ", $errors);
                } else {
                    // Hash the new password
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    try {
                        $update = $conn->prepare("UPDATE users SET password = :pass, reset_code = NULL, code_expiry = NULL WHERE id = :id");
                        $update->execute(['pass' => $hashed, 'id' => $user_id]);

                        // Clear session
                        unset($_SESSION['reset_user_id']);
                        unset($_SESSION['reset_email']);

                        // Also clear the forgot link flag if set
                        unset($_SESSION['show_forgot_link']);
                        unset($_SESSION['forgot_email']);

                        $_SESSION['success_message'] = "Your password has been reset successfully. Please log in with your new password.";
                        header("Location: login.php");
                        exit();
                    } catch (PDOException $e) {
                        $error = "Database error: " . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Inventory System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same styles as login.php */
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
        .error-alert, .success-alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .error-alert {
            background: #fef3f2;
            border-left: 4px solid #dc2626;
        }
        .error-alert i { color: #dc2626; font-size: 1.1rem; }
        .error-alert span { color: #991b1b; font-weight: 500; }
        .success-alert {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
        }
        .success-alert i { color: #10b981; font-size: 1.1rem; }
        .success-alert span { color: #065f46; font-weight: 500; }
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
        .input-wrapper input[readonly] {
            background: #f3f6f4;
            cursor: not-allowed;
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
        .info-text {
            margin: 16px 0;
            padding: 12px 16px;
            background: #f8faf9;
            border-radius: 12px;
            font-size: 0.85rem;
            color: #4a5f54;
            border: 1px solid #e2e8e4;
        }
        .info-text i {
            color: #1f5e3a;
            margin-right: 8px;
        }
        .back-link {
            text-align: center;
            margin-top: 16px;
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
        @media (max-width: 480px) {
            .card-body { padding: 24px 24px 28px 24px; }
            .card-header { padding: 28px 24px 0 24px; }
            .logo-wrapper img { height: 55px; }
            .card-header h1 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="card-header">
        <div class="logo-wrapper">
            <img src="../assets/MC-LOGO.png" alt="Mombasacomputers Logo">
        </div>
        <h1>Reset Password</h1>
        <p>Secure Password Recovery</p>
    </div>

    <div class="card-body">
        <?php if ($error): ?>
            <div class="error-alert">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-alert">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($step === 'code'): ?>
            <!-- Step 1: Enter email and reset code -->
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="verify_code" value="1">

                <div class="info-text">
                    <i class="fas fa-info-circle"></i>
                    Please enter the email address associated with your account and the 6-digit reset code you received from your admin.
                </div>

                <div class="input-group">
                    <label>EMAIL ADDRESS</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="your@email.com" required autofocus value="<?= htmlspecialchars($_SESSION['forgot_email'] ?? '') ?>">
                    </div>
                </div>

                <div class="input-group">
                    <label>RESET CODE</label>
                    <div class="input-wrapper">
                        <i class="fas fa-key"></i>
                        <input type="text" name="reset_code" placeholder="6-digit code" required maxlength="6" pattern="[0-9]{6}" title="Must be 6 digits">
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <span>Verify Code</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="back-link">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>

        <?php else: ?>
            <!-- Step 2: Set new password -->
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="set_password" value="1">

                <div class="info-text">
                    <i class="fas fa-lock"></i>
                    Your code has been verified. Please choose a strong password:
                    <ul style="margin-top: 8px; padding-left: 20px; font-size: 0.8rem; color: #4a5f54;">
                        <li>At least 6 characters long</li>
                        <li>Must contain at least one number</li>
                        <li>Must contain at least one symbol (e.g. !@#$%^&*)</li>
                    </ul>
                </div>

                <div class="input-group">
                    <label>NEW PASSWORD</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>CONFIRM PASSWORD</label>
                    <div class="input-wrapper">
                        <i class="fas fa-check-circle"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="showPassword">
                    <label for="showPassword"><i class="far fa-eye" style="margin-right: 6px;"></i> Show passwords</label>
                </div>

                <button type="submit" class="btn-login">
                    <span>Reset Password</span>
                    <i class="fas fa-check"></i>
                </button>
            </form>

            <div class="back-link">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>&copy; <?= date("Y") ?> Mombasacomputers. All rights reserved.</p>
    </div>
</div>

<script>
    // Toggle password visibility for both fields
    const showCheckbox = document.getElementById('showPassword');
    if (showCheckbox) {
        showCheckbox.addEventListener('change', function() {
            const newPw = document.getElementById('new_password');
            const confirmPw = document.getElementById('confirm_password');
            const type = this.checked ? 'text' : 'password';
            newPw.type = type;
            confirmPw.type = type;
        });
    }
</script>

</body>
</html>