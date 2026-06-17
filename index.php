<?php
date_default_timezone_set('Africa/Nairobi');
session_start();
require_once "config/db.php";

require_once 'PHPMailer-master/src/PHPMailer.php';
require_once 'PHPMailer-master/src/SMTP.php';
require_once 'PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = "";

function sendLockEmail($to, $name) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'victormunene207@gmail.com';
        $mail->Password   = 'trda huax aazp idjv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('victormunene207@gmail.com', 'Inventory System');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = 'Account Locked - Inventory System';
        $mail->Body    = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow:0 4px 15px rgba(0,0,0,0.1); }
                .header { background-color: #1a5f3e; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { padding: 20px; line-height: 1.5; color: #333; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px; text-align: center; }
                .highlight { font-weight: bold; color: #d32f2f; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Inventory System Alert</h2>
                </div>
                <div class="content">
                    <p>Hi '.$name.',</p>
                    <p>Your account has been <span class="highlight">temporarily locked</span> due to <span class="highlight">3 failed login attempts</span>.</p>
                    <p>If this was not you, please contact the administrator immediately to reset your password and secure your account.</p>
                    <p>Otherwise, you can try logging in after 10 minutes.</p>
                    <p>Thank you for using Mombasacomputers Inventory System.</p>
                </div>
                <div class="footer">
                    <p>This is an automated message from Mombasacomputers Inventory System.</p>
                </div>
            </div>
        </body>
        </html>
        ';

        $mail->AltBody = "Hi $name,\nYour account has been temporarily locked due to 3 failed login attempts.\nIf this wasn't you, contact admin.";
        $mail->send();
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email AND is_active = 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (!empty($user['account_locked_until']) && strtotime($user['account_locked_until']) > time()) {
            $remaining = strtotime($user['account_locked_until']) - time();
            $minutes = floor($remaining / 60);
            $seconds = $remaining % 60;
            $error = "Soemthing went wrong. Please try again later.";
        } else {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];

                $update = $conn->prepare("UPDATE users SET failed_attempts = 0, account_locked_until = NULL, last_login = NOW() WHERE id = :id");
                $update->execute(['id' => $user['id']]);
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
            } else {
                $failed_attempts = $user['failed_attempts'] + 1;

                if ($failed_attempts >= 3) {
                    $lock_time = date("Y-m-d H:i:s", strtotime("+10 minutes"));
                    $update = $conn->prepare("UPDATE users SET failed_attempts = 0, account_locked_until = :lock_time WHERE id = :id");
                    $update->execute(['lock_time' => $lock_time, 'id' => $user['id']]);
                    sendLockEmail($user['email'], $user['full_name']);
                    $error = "Something went wrong. Please try again later.";
                } else {
                    $update = $conn->prepare("UPDATE users SET failed_attempts = :failed_attempts WHERE id = :id");
                    $update->execute(['failed_attempts' => $failed_attempts, 'id' => $user['id']]);
                    $error = "Invalid email or password.";
                }
            }
        }
    } else {
        $error = "Invalid email or password.";
    }
}
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
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="card-header">
        <div class="logo-wrapper">
            <img src="assets/MC-LOGO.png" alt="Mombasacomputers Logo">
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

        <form method="POST" action="">
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
            <p>Don't have an account? <a href="inventory_system/auth/register.php">Register</a></p>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date("Y"); ?> Mombasacomputers. All rights reserved.</p>
    </div>
</div>

<script>
    const passwordField = document.getElementById('password');
    const showCheckbox = document.getElementById('showPassword');
    
    showCheckbox.addEventListener('change', function() {
        passwordField.type = this.checked ? 'text' : 'password';
    });
</script>

</body>
</html>