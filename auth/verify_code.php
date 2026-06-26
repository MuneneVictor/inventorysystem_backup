<?php
session_start();
date_default_timezone_set('Africa/Nairobi');
require_once "../config/db.php";
require_once "../includes/header.php";

$error = "";

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = trim($_POST['email']);
    $code = trim($_POST['code']);

    $stmt = $conn->prepare("SELECT * FROM registration_codes WHERE email = :email AND code = :code AND is_used = 0");
    $stmt->execute(['email'=>$email, 'code'=>$code]);
    $regCode = $stmt->fetch(PDO::FETCH_ASSOC);
    $expiry = $regCode['expiry'];
    
    if (!$regCode || strtotime($expiry) < time()){
        $error = "Invalid or already used code for this email.";
    } else{
        $_SESSION['reg_email'] = $email;
        $_SESSION['reg_role'] = $regCode['role'];
        $_SESSION['reg_code_id'] = $regCode['id'];
        header("Location: register.php");
        exit();
};
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code | Inventory System</title>
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

        .verify-card {
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

        .info-message {
            background: #eef6f2;
            border-left: 4px solid #1f5e3a;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .info-message i {
            color: #1f5e3a;
            font-size: 1.1rem;
        }

        .info-message span {
            font-size: 0.8rem;
            color: #2d4a3a;
            font-weight: 500;
            line-height: 1.4;
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
            font-size: 0.75rem;
            font-weight: 700;
            color: #2d3e35;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
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
            margin-top: 8px;
        }

        .btn-verify i {
            font-size: 1rem;
            transition: transform 0.2s;
        }

        .btn-verify:hover {
            background: #154d2e;
        }

        .btn-verify:hover i {
            transform: translateX(3px);
        }

        .back-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eef2ef;
        }
        .back-link p {
            font-size: 0.85rem;
            color: #5a6e64;
        }

        .back-link a {
            color: #1f5e3a;
            text-decoration: none;
            font-weight: 700;
            margin-left: 5px;
            transition: color 0.2s;
        }

        .back-link a:hover {
            color: #1f5e3a;
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

<div class="verify-card">
    <div class="card-header">
        <div class="logo-wrapper">
            <img src="/inventory_system/assets/MC-LOGO.png" alt="Mombasacomputers Logo">
        </div>
        <h1>Verify Registration</h1>
        <p>Enter your unique access code</p>
    </div>

    <div class="card-body">
        <div class="info-message">
            <i class="fas fa-info-circle"></i>
            <span>A registration code has been sent to your email address. Enter it below to continue.</span>
        </div>

        <?php if($error): ?>
            <div class="error-alert">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>EMAIL ADDRESS</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="your@email.com" required autofocus>
                </div>
            </div>

            <div class="input-group">
                <label>VERIFICATION CODE</label>
                <div class="input-wrapper">
                    <i class="fas fa-key"></i>
                    <input type="text" name="code" placeholder="000000" required autocomplete="off" >
                </div>
            </div>

            <button type="submit" class="btn-verify">
                <span>Verify Code</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="back-link">
            <p>Already have an account? <a href="login.php">Login</a>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date("Y"); ?> Mombasacomputers. All rights reserved.</p>
    </div>
</div>

</body>
</html>