<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";


$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$business_role = $_SESSION['business_role'] ?? '';

$success_message = '';
$error_message = '';

// Fetch current user data using prepared statement
$stmt = $conn->prepare("SELECT id, email, username, full_name, role, branch, created_at, last_login FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    
    $errors = [];
    
    // Validation
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    
    // Check if email exists for another user
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_stmt->execute([$email, $user_id]);
    if ($check_stmt->fetch()) {
        $errors[] = "Email already used by another account.";
    }
    
    // Check if username exists for another user
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check_stmt->execute([$username, $user_id]);
    if ($check_stmt->fetch()) {
        $errors[] = "Username already taken.";
    }
    
    if (empty($errors)) {
        $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, username = ? WHERE id = ?");
        if ($update_stmt->execute([$full_name, $email, $username, $user_id])) {
            $success_message = "Profile updated successfully!";
            // Refresh user data
            $user['full_name'] = $full_name;
            $user['email'] = $email;
            $user['username'] = $username;
            // Update session
            $_SESSION['name'] = $full_name;
        } else {
            $error_message = "Failed to update profile.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Verify current password
    $pass_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $pass_stmt->execute([$user_id]);
    $user_data = $pass_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!password_verify($current_password, $user_data['password'])) {
        $errors[] = "Current password is incorrect.";
    }
    
    if (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters.";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($update_stmt->execute([$hashed_password, $user_id])) {
            $success_message = "Password changed successfully!";
            // Clear password fields
            $_POST['current_password'] = '';
            $_POST['new_password'] = '';
            $_POST['confirm_password'] = '';
        } else {
            $error_message = "Failed to change password.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>My Profile | Mombasa Computers</title>
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

        /* Profile Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        /* Cards */
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

        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            background: white;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

        .form-group input:disabled {
            background: var(--gray-50);
            color: var(--gray-500);
            cursor: not-allowed;
        }

        /* Info Row */
        .info-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .info-label {
            width: 120px;
            font-weight: 500;
            color: var(--gray-600);
        }

        .info-value {
            flex: 1;
            color: var(--gray-800);
        }

        .info-value .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .info-value .badge-admin {
            background: #dc2626;
            color: white;
        }

        .info-value .badge-inventory {
            background: #059669;
            color: white;
        }

        .info-value .badge-sales {
            background: #3b82f6;
            color: white;
        }

        .info-value .badge-technician {
            background: #f59e0b;
            color: white;
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
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .form-actions {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
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

            .profile-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
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

            .info-row {
                flex-direction: column;
                gap: 0.25rem;
            }

            .info-label {
                width: 100%;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
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
    <?php include_once "../includes/sidebar.php"; ?>
<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-user-circle"></i>
            My Profile
        </h1>
        <div class="breadcrumb">
            <?php if($_SESSION['role'] === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>       
            <?php endif; ?>
            <?php if($_SESSION['role'] === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <?php if($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="../dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <?php if($_SESSION['role'] === 'sales'): ?>
                <a href="../dashboard/salesdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
             <?php if($_SESSION['role'] === 'software'): ?>
                <a href="../dashboard/softwaredashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
             <?php if($_SESSION['role'] === 'technician'): ?>
                <a href="../dashboard/techniciandashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
             <?php if($_SESSION['role'] === 'cashier'): ?>
                <a href="../dashboard/cashierdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>My Profile</span>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($success_message) ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= $error_message ?></span>
        </div>
    <?php endif; ?>

    <div class="profile-grid">
        <!-- Profile Information Card -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-user"></i>
                    Profile Information
                </h2>
            </div>
            <div class="card-body">
                <form method="POST" id="profileForm">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_profile" class="btn btn-primary" onclick="return confirmProfileUpdate()">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password Card -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-key"></i>
                    Change Password
                </h2>
            </div>
            <div class="card-body">
                <form method="POST" id="passwordForm">
                    <div class="form-group">
                        <label>Current Password <span class="required">*</span></label>
                        <input type="password" name="current_password" placeholder="Enter your current password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password <span class="required">*</span></label>
                        <input type="password" name="new_password" placeholder="Minimum 6 characters" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" placeholder="Confirm your new password" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn btn-primary" onclick="return confirmPasswordChange()">
                            <i class="fas fa-lock"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Account Details Card -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-info-circle"></i>
                    Account Details
                </h2>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Role:</div>
                    <div class="info-value">
                        <?php
                        $role_class = '';
                        $role_display = '';
                        switch($user['role']) {
                            case 'super_admin':
                                $role_class = 'badge-admin';
                                $role_display = 'Super Administrator';
                                break;
                            case 'inventory_admin':
                                $role_class = 'badge-inventory';
                                $role_display = 'Inventory Administrator';
                                break;
                            case 'sales':
                                $role_class = 'badge-sales';
                                $role_display = 'Sales Personnel';
                                break;
                            case 'technician':
                                $role_class = 'badge-technician';
                                $role_display = 'Technician';
                                break;
                            case 'maintenance':
                                $role_class = 'badge-technician';
                                $role_display = 'Software';
                                break;
                            case 'manager':
                                $role_class = 'badge-inventory';
                                $role_display = 'Manager';
                                break;
                            default:
                                $role_class = '';
                                $role_display = ucfirst(str_replace('_', ' ', $user['role']));
                        }
                        ?>
                        <span class="badge <?= $role_class ?>"><?= htmlspecialchars($role_display) ?></span>
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Branch:</div>
                    <div class="info-value">
                        <?php if ($user['branch']): ?>
                            <span class="badge" style="background: <?= $user['branch'] == 'KIMATHI' ? '#059669' : '#3b82f6' ?>; color: white;">
                                <i class="fas <?= $user['branch'] == 'KIMATHI' ? 'fa-building' : 'fa-store' ?>"></i>
                                <?= htmlspecialchars($user['branch']) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge">Not Assigned</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Account Created:</div>
                    <div class="info-value">
                        <?= date('F j, Y \a\t g:i A', strtotime($user['created_at'])) ?>
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Last Login:</div>
                    <div class="info-value">
                        <?= $user['last_login'] ? date('F j, Y \a\t g:i A', strtotime($user['last_login'])) : 'First login' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-bolt"></i>
                    Quick Actions
                </h2>
            </div>
            <div class="card-body">
                <div class="form-actions" style="flex-direction: column;">
                    <?php if ($user['role'] === 'super_admin'): ?>
                        <a href="generate_code.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add New User
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($user['role'] === 'super_admin'): ?>
                        <a href="view_users.php" class="btn btn-secondary">
                            <i class="fas fa-users"></i> View All Users
                        </a>
                    <?php endif; ?>
                     <?php if($_SESSION['role'] === 'super_admin'): ?>
                    <a href="../dashboard/superadmindashboard.php" class="btn btn-secondary"><i class="fas fa-tachometer-alt"></i> Dashboard</a>       
                    <?php endif; ?>
                    <?php if($_SESSION['role'] === 'manager'): ?>
                        <a href="../dashboard/managerdashboard.php" class="btn btn-secondary"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <?php endif; ?>
                    <?php if($_SESSION['role'] === 'inventory_admin'): ?>
                        <a href="../dashboard/inventorydashboard.php" class="btn btn-secondary"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <?php endif; ?>
                    <?php if($_SESSION['role'] === 'sales'): ?>
                        <a href="../dashboard/salesdashboard.php" class="btn btn-secondary"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <?php endif; ?>
                    <?php if($_SESSION['role'] === 'software'): ?>
                        <a href="../dashboard/softwaredashboard.php" class="btn btn-secondary"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <?php endif; ?>
                    <?php if($_SESSION['role'] === 'technician'): ?>
                        <a href="../dashboard/techniciandashboard.php" class="btn btn-secondary"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <?php endif; ?>
                    <?php if($_SESSION['role'] === 'cashier'): ?>
                        <a href="../dashboard/cashierdashboard.php" class="btn btn-secondary"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <?php endif; ?>
                    <a href="../auth/logout.php" class="btn btn-danger" onclick="return confirm('Are you sure you want to logout?')">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
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

// Confirm Profile Update
function confirmProfileUpdate() {
    return confirm("Are you sure you want to update your profile? This action cannot be undone.");
}

// Confirm Password Change
function confirmPasswordChange() {
    // Get password values
    var currentPassword = document.querySelector('input[name="current_password"]').value;
    var newPassword = document.querySelector('input[name="new_password"]').value;
    var confirmPassword = document.querySelector('input[name="confirm_password"]').value;
    
    // Validate if passwords are filled
    if (!currentPassword || !newPassword || !confirmPassword) {
        alert("Please fill in all password fields.");
        return false;
    }
    
    // Validate password length
    if (newPassword.length < 6) {
        alert("New password must be at least 6 characters long.");
        return false;
    }
    
    // Validate password match
    if (newPassword !== confirmPassword) {
        alert("New passwords do not match. Please try again.");
        return false;
    }
    
    // Check if current password is same as new password
    if (currentPassword === newPassword) {
        alert("New password cannot be the same as your current password.");
        return false;
    }
    
    return confirm("Are you sure you want to change your password? This action cannot be undone.");
}
</script>

</body>
</html>