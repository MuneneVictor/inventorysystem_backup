<?php
session_start();
date_default_timezone_set('Africa/Nairobi');
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Include PHPMailer
require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';
require_once '../PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$user_id = $_SESSION['user_id'];
if (!$user_id) {
    die("user not authenticated!");
}
// Only super_admin can access
if($_SESSION['role'] !== 'super_admin'){
    die("Access denied!");
}

$error = $success = "";

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $token = random_int(100000, 999999);
    $expire_time = date('Y-m-d H:i:s', strtotime('+20 minutes'));

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format! Please enter a valid email address.";
    } else {
        // Check if email already has a code using prepared statement
        $check = $conn->prepare("SELECT * FROM registration_codes WHERE email = :email AND is_used = 0 AND expiry > NOW()");
        $check->execute(['email'=>$email]);
        if($check->rowCount() > 0){
            $error = "A registration code for this email already exists!";
        } else {
            // Send email first using PHPMailer
            try {
                $mail = new PHPMailer(true);
                
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'victormunene207@gmail.com';
                $mail->Password   = 'trda huax aazp idjv';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                
                // Recipients
                $mail->setFrom('victormunene207@gmail.com', 'Mombasa Computers');
                $mail->addAddress($email);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Your Registration Code - Mombasa Computers';
                $mail->Body    = '
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
                            .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow:0 4px 15px rgba(0,0,0,0.1); }
                            .header { background-color: #1a4b2a; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                            .content { padding: 20px; }
                            .code { font-size: 32px; font-weight: bold; color: #1a4b2a; text-align: center; padding: 15px; background-color: #f3f4f6; border-radius: 8px; margin: 20px 0; letter-spacing: 5px; }
                            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px; text-align: center; }
                            .role-badge { display: inline-block; background: #e5e7eb; padding: 4px 12px; border-radius: 20px; font-size: 14px; }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="header">
                                <h2>Mombasa Computers Inventory System</h2>
                            </div>
                            <div class="content">
                                <p>Hello,</p>
                                <p>You have been invited to register for the <strong>Mombasa Computers Inventory System</strong> with the role:</p>
                                <p style="text-align: center;"><span class="role-badge">' . ucfirst(str_replace("_", " ", $role)) . '</span></p>
                                <p>Your registration code is:</p>
                                <div class="code">' . $token . '</div>
                                <p>Please use this code to complete your registration at:</p>
                                <p><a href="https://yourdomain.com../auth/register.php">https://yourdomain.com../auth/register.php</a></p>
                                <p>This code is valid for <strong>one-time use only</strong> and expires after 20 minutes.</p>
                                <p>If you did not request this registration, please ignore this email.</p>
                            </div>
                            <div class="footer">
                                <p>&copy; ' . date('Y') . ' Mombasa Computers. All rights reserved.</p>
                                <p>This is an automated message, please do not reply.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ';
                
                $mail->AltBody = "Your registration code for Mombasa Computers Inventory System is: $token\nRole: " . ucfirst(str_replace("_", " ", $role)) . "\n\nUse this code to complete your registration.\n\nThis code is valid for one-time use only.";
                
                // Try to send email
                if ($mail->send()) {
                    // Email sent successfully, now insert into database using prepared statement
                    $stmt = $conn->prepare("INSERT INTO registration_codes (email, code, role, created_by, expiry) VALUES (:email, :code, :role, :created_by, :expiry)");
                    $stmt->execute([
                        'email'=>$email,
                        'code'=>$token,
                        'role'=>$role,
                        'created_by'=>$_SESSION['user_id'],
                        'expiry'=>$expire_time
                    ]);
                    
                    $success = "Registration code has been generated and sent to $email successfully!";
                } else {
                    $error = "Failed to send email to $email. Code was not generated. Please check your SMTP settings.";
                }
                
            } catch (Exception $e) {
                $error = "Failed to send email: " . $e->getMessage() . ". Code was not generated.";
            }
        }
    }
}

$roles = ['manager','inventory_admin','technician','software','sales', 'cashier'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Generate Registration Code | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
            overflow-x: hidden;
        }

        /* Main Content Area */
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

        /* Page Header */
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

        .page-header h1 i {
            color: var(--primary);
            font-size: 1.75rem;
        }

        .breadcrumb {
            color: var(--gray-500);
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Form Container */
        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }

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

        .card-header h2 i {
            color: var(--primary);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .form-group label .required {
            color: #dc2626;
            margin-left: 0.25rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

        .form-group .help-text {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.5rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Buttons */
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

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
        }

        /* Info Box */
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

        .info-box ul {
            margin-left: 1.5rem;
            color: var(--gray-600);
            font-size: 0.85rem;
        }

        .info-box li {
            margin-bottom: 0.5rem;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 1.5rem 1rem 1rem !important;
                padding-top: 5rem !important;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem 0.75rem 0.75rem !important;
                padding-top: 4.5rem !important;
            }

            .page-header h1 {
                font-size: 1.25rem;
            }

            .page-header {
                padding: 1rem 1.25rem;
            }

            .card-header {
                padding: 1rem 1.25rem;
            }

            .card-header h2 {
                font-size: 1.1rem;
            }

            .card-body {
                padding: 1.25rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem 0.5rem 0.5rem !important;
                padding-top: 4rem !important;
            }

            .page-header h1 {
                font-size: 1.1rem;
            }

            .card-body {
                padding: 1rem;
            }
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
            Generate Registration Code
        </h1>
        <div class="breadcrumb">
            <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <a href="view_users.php">Users</a>
            <span> / </span>
            <span>Generate Code</span>
        </div>
    </div>

    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-envelope"></i>
                    Send Registration Invitation
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
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" placeholder="user@example.com" required>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> A valid email address is required to send the registration code.
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Role <span class="required">*</span></label>
                        <select name="role" required>
                            <option value="">-- Select Role --</option>
                            <?php foreach($roles as $r): ?>
                                <option value="<?= $r ?>">
                                    <?php 
                                        $display_name = ucfirst(str_replace("_", " ", $r));
                                        if ($r === 'inventory_admin') $display_name = 'Inventory Administrator';
                                        if ($r === 'super_admin') $display_name = 'Super Administrator';
                                        echo $display_name;
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> This determines what permissions the user will have.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Generate & Send Code
                    </button>
                </form>

                <!-- Info Box -->
                <div class="info-box">
                    <h3>
                        <i class="fas fa-lightbulb"></i>
                        How it works:
                    </h3>
                    <ul>
                        <li>A 6-digit registration code will be generated</li>
                        <li>The code is sent to the provided email address</li>
                        <li>User must use this code within 20 minutes to register</li>
                        <li>Each code can only be used once</li>
                        <li>The code expires after 20 minutes if not used</li>
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
// Mobile responsive adjustments
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