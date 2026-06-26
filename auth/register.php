<?php
session_start();
require_once "../config/db.php";
require_once "../includes/header.php";

if(!isset($_SESSION['reg_email'], $_SESSION['reg_role'], $_SESSION['reg_code_id'])){
    header("Location: verify_code.php");
    exit();
}

$error = "";

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = $_SESSION['reg_email'];
    $role = $_SESSION['reg_role'];
    $code_id = $_SESSION['reg_code_id'];

    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $branch = $_POST['branch'];

    if(strlen($password) < 6){
        $error = "Password must be at least 6 characters long!";
    } elseif(!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)){
        $error = "Password must contain at least one symbol (e.g., ! @ # $ % ^ & * )";
    } elseif($password !== $confirm_password){
        $error = "Passwords do not match!";
    } else {
        $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $checkEmail->execute(['email' => $email]);

        if($checkEmail->rowCount() > 0){
            $error = "Email already exists! please login.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (email, username, full_name, password, role, branch) 
                                    VALUES (:email, :username, :full_name, :password, :role, :branch)");
            $stmt->execute([
                'email'=>$email,
                'username'=>$username,
                'full_name'=>$full_name,
                'password'=>$hashedPassword,
                'role'=>$role,
                'branch'=>$branch
            ]);

            $user_id = $conn->lastInsertId();

            $updateCode = $conn->prepare("UPDATE registration_codes 
                                          SET is_used=1, used_by=:uid, used_at=NOW() 
                                          WHERE id=:id");
            $updateCode->execute(['uid'=>$user_id, 'id'=>$code_id]);

            unset($_SESSION['reg_email'], $_SESSION['reg_role'], $_SESSION['reg_code_id']);

            header("Location: login.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Inventory System</title>
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

        .register-card {
            max-width: 500px;
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

        .input-wrapper input,
        .input-wrapper select {
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

        .input-wrapper select {
            cursor: pointer;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%239aa8a0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>');
            background-repeat: no-repeat;
            background-position: right 14px center;
        }

        .input-wrapper input:focus,
        .input-wrapper select:focus {
            border-color: #2b6e46;
            box-shadow: 0 0 0 3px rgba(43, 110, 70, 0.1);
        }

        .input-wrapper input::placeholder {
            color: #bcc9c2;
            font-weight: 400;
        }

        .password-hint {
            font-size: 0.7rem;
            color: #8ba094;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .password-hint i {
            font-size: 0.7rem;
            color: #8ba094;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 8px 0 4px 0;
        }

        .checkbox-group input {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #2b6e46;
        }

        .checkbox-group label {
            font-size: 0.8rem;
            color: #4a5f54;
            cursor: pointer;
            user-select: none;
            font-weight: 500;
            text-transform: none;
            letter-spacing: normal;
        }

        .btn-register {
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

        .btn-register i {
            font-size: 1rem;
            transition: transform 0.2s;
        }

        .btn-register:hover {
            background: #154d2e;
        }

        .btn-register:hover i {
            transform: translateX(3px);
        }

        .back-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eef2ef;
        }

        .back-link a {
            color: #6b7c72;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .back-link a i {
            margin-right: 6px;
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
            .card-body {
                padding: 24px 24px 28px 24px;
            }
            .card-header {
                padding: 28px 24px 0 24px;
            }
            .card-header h1 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>

<div class="register-card">
    <div class="card-header">
        <h1>Create Account</h1>
        <p>Complete your registration</p>
    </div>

    <div class="card-body">
        <?php if($error): ?>
            <div class="error-alert">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>FULL NAME</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" name="full_name" placeholder="Enter your full name" required>
                </div>
            </div>

            <div class="input-group">
                <label>USERNAME</label>
                <div class="input-wrapper">
                    <i class="fas fa-at"></i>
                    <input type="text" name="username" placeholder="Enter your username">
                </div>
            </div>

            <div class="input-group">
                <label>BRANCH</label>
                <div class="input-wrapper">
                    <i class="fas fa-store"></i>
                    <select name="branch" required>
                        <option value="">Select Branch</option>
                        <option value="KIMATHI">KIMATHI</option>
                        <option value="MOI">MOI</option>
                    </select>
                </div>
            </div>

            <div class="input-group">
                <label>PASSWORD</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" required>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="showPassword">
                    <label for="showPassword"><i class="far fa-eye" style="margin-right: 6px;"></i> Show password</label>
                </div>
                <div class="password-hint">
                    <i class="fas fa-info-circle"></i>
                    <span>Minimum 6 characters with at least one symbol (!@#$%^&*)</span>
                </div>
            </div>

            <div class="input-group">
                <label>CONFIRM PASSWORD</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="showConfirmPassword">
                    <label for="showConfirmPassword"><i class="far fa-eye" style="margin-right: 6px;"></i> Show confirm password</label>
                </div>
            </div>

            <button type="submit" class="btn-register">
                <span>Register Account</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="back-link">
            <a href="login.php"><i class="fas fa-chevron-left"></i> Back to Login</a>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date("Y"); ?> Mombasacomputers. All rights reserved.</p>
    </div>
</div>

<script>
    const passwordField = document.getElementById('password');
    const showPasswordCheckbox = document.getElementById('showPassword');
    
    showPasswordCheckbox.addEventListener('change', function() {
        passwordField.type = this.checked ? 'text' : 'password';
    });

    const confirmPasswordField = document.getElementById('confirm_password');
    const showConfirmCheckbox = document.getElementById('showConfirmPassword');
    
    showConfirmCheckbox.addEventListener('change', function() {
        confirmPasswordField.type = this.checked ? 'text' : 'password';
    });
</script>

</body>
</html>