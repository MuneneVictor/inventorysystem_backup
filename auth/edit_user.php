<?php
session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ========== REQUIRE AUTHENTICATION ==========
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only super_admin can access
if ($_SESSION['role'] !== 'super_admin') {
    die("Access denied!");
}

$error = '';
$success = '';
$user_id = 0;
$user_data = null;

// ========== HANDLE POST REQUEST (Update) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
    } else {
        $user_id = (int)$_POST['user_id'];
        
        // Get form data
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $branch = $_POST['branch'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validate
        $errors = [];
        
        if (empty($full_name)) {
            $errors[] = "Full name is required.";
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email address is required.";
        }
        
        if (empty($role)) {
            $errors[] = "Role is required.";
        }
        
        if (empty($branch)) {
            $errors[] = "Branch is required.";
        }
        
        // Check if email already exists for another user
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $stmt->execute(['email' => $email, 'id' => $user_id]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Email address is already used by another user.";
            }
        }
        
        // Prevent self role demotion or status change
        if ($user_id == $_SESSION['user_id']) {
            $errors[] = "To edit your own account, go to your profile settings.";
        }
        
        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET full_name = :full_name, 
                        email = :email, 
                        role = :role, 
                        branch = :branch, 
                        is_active = :is_active
                    WHERE id = :id
                ");
                $stmt->execute([
                    'full_name' => $full_name,
                    'email' => $email,
                    'role' => $role,
                    'branch' => $branch,
                    'is_active' => $is_active,
                    'id' => $user_id
                ]);
                
                // Log activity
                $logStmt = $conn->prepare("
                    INSERT INTO activity_logs (user_id, action, details) 
                    VALUES (:uid, :action, :details)
                ");
                $logStmt->execute([
                    'uid' => $_SESSION['user_id'],
                    'action' => 'User Edited',
                    'details' => "User ID $user_id was edited by " . ($_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Admin')
                ]);
                
                $success = "User information updated successfully!";
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                $stmt->execute(['id' => $user_id]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } else {
            $error = implode(" ", $errors);
        }
    }
}

// ========== GET USER ID FROM URL ==========
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = (int)$_GET['id'];
} elseif (isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
} else {
    $_SESSION['error'] = "No user specified.";
    header("Location: view_users.php");
    exit();
}

// ========== FETCH USER DATA ==========
if ($user_id > 0 && !$user_data) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$user_data) {
    $_SESSION['error'] = "User not found.";
    header("Location: view_users.php");
    exit();
}

// Prevent editing own account
if ($user_id == $_SESSION['user_id']) {
    $_SESSION['error'] = "To edit your own account, go to your profile settings.";
    header("Location: view_users.php");
    exit();
}

// ========== GET ROLE OPTIONS ==========
$roles = ['super_admin', 'inventory_admin', 'technician', 'software', 'sales', 'manager', 'cashier'];

// ========== GET BRANCH OPTIONS ==========
$branches = ['KIMATHI', 'MOI'];

// Display success/error messages from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Edit User | Mombasa Computers</title>
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

        .form-container {
            max-width: 700px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .form-group input[readonly] {
            background: var(--gray-50);
            cursor: not-allowed;
        }

        .form-group .help-text {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.5rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-top: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .checkbox-group label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-700);
            cursor: pointer;
            user-select: none;
        }

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
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-group {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .btn-group .btn {
            flex: 1;
            justify-content: center;
        }

        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

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
                flex-direction: column;
                gap: 0.75rem;
                align-items: flex-start;
            }

            .card-header h2 {
                font-size: 1.1rem;
            }

            .card-body {
                padding: 1.25rem;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn-group .btn {
                width: 100%;
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
<?php require_once "../includes/sidebar.php"; ?>
<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-user-edit"></i>
            Edit User
        </h1>
        <div class="breadcrumb">
            <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <a href="view_users.php">Users</a>
            <span> / </span>
            <span>Edit User</span>
        </div>
    </div>

    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-pen"></i>
                    Edit User Information
                </h2>
                <span style="font-size:0.85rem; color: var(--gray-500);">
                    <i class="fas fa-id-card"></i> ID: <?= htmlspecialchars($user_data['id']) ?>
                </span>
            </div>
            <div class="card-body">
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

                <form method="POST" action="" id="editUserForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($user_data['id']) ?>">
                    <input type="hidden" name="update_user" value="1">

                    <div class="form-group">
                        <label>
                            <i class="fas fa-user"></i> Full Name <span class="required">*</span>
                        </label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($user_data['full_name'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-envelope"></i> Email Address <span class="required">*</span>
                        </label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user_data['email']) ?>" required>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> Email must be unique in the system.
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-user-tag"></i> Role <span class="required">*</span>
                        </label>
                        <select name="role" required>
                            <option value="">-- Select Role --</option>
                            <?php foreach ($roles as $r): ?>
                                <?php
                                    $display_name = ucfirst(str_replace('_', ' ', $r));
                                    if ($r === 'super_admin') $display_name = 'Super Administrator';
                                    if ($r === 'inventory_admin') $display_name = 'Inventory Administrator';
                                    if ($r === 'software') $display_name = 'Software';
                                ?>
                                <option value="<?= htmlspecialchars($r) ?>" <?= ($user_data['role'] === $r) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($display_name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> Determines what permissions the user will have.
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-store"></i> Branch <span class="required">*</span>
                        </label>
                        <select name="branch" required>
                            <option value="">-- Select Branch --</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= ($user_data['branch'] === $b) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="is_active" <?= ($user_data['is_active'] == 1) ? 'checked' : '' ?>>
                            <label for="is_active">
                                <i class="fas <?= ($user_data['is_active'] == 1) ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' ?>" style="color: <?= ($user_data['is_active'] == 1) ? '#10b981' : '#ef4444' ?>;"></i>
                                User is active (can log in)
                            </label>
                        </div>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> Inactive users cannot log in to the system.
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary" onclick="return confirmUpdate()">
                            <i class="fas fa-save"></i> Update User
                        </button>
                        <a href="view_users.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="button" class="btn btn-danger" onclick="if(confirm('Are you sure you want to delete this user? This action cannot be undone!')) { window.location.href='delete_user.php?id=<?= $user_data['id'] ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>'; }">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </form>

                <!-- User Info Summary -->
                <div style="margin-top: 2rem; padding: 1rem; background: var(--gray-50); border-radius: var(--radius-lg); border: 1px solid var(--gray-200);">
                    <h4 style="font-size:0.85rem; color: var(--gray-600); margin-bottom: 0.75rem;">
                        <i class="fas fa-info-circle"></i> Account Information
                    </h4>
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 0.5rem 1.5rem; font-size:0.85rem;">
                        <span style="color: var(--gray-500);">Username:</span>
                        <span><strong><?= htmlspecialchars($user_data['username'] ?? 'N/A') ?></strong></span>
                        <span style="color: var(--gray-500);">Created:</span>
                        <span><?= date('M j, Y g:i A', strtotime($user_data['created_at'])) ?></span>
                        <span style="color: var(--gray-500);">Last Login:</span>
                        <span><?= $user_data['last_login'] ? date('M j, Y g:i A', strtotime($user_data['last_login'])) : 'Never' ?></span>
                        <span style="color: var(--gray-500);">Failed Attempts:</span>
                        <span><?= (int)($user_data['failed_attempts'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<script>
// Confirm update with showing the changes
function confirmUpdate() {
    // Get current values
    const fullName = document.querySelector('input[name="full_name"]').value;
    const email = document.querySelector('input[name="email"]').value;
    const role = document.querySelector('select[name="role"]');
    const roleText = role.options[role.selectedIndex].text;
    const branch = document.querySelector('select[name="branch"]');
    const branchText = branch.options[branch.selectedIndex].text;
    const isActive = document.querySelector('input[name="is_active"]').checked;
    const statusText = isActive ? 'Active' : 'Inactive';
    
    // Build confirmation message
    const message = 
        'Are you sure you want to update this user?\n\n' +
        'User: ' + fullName + '\n' +
        'Email: ' + email + '\n' +
        'Role: ' + roleText + '\n' +
        'Branch: ' + branchText + '\n' +
        'Status: ' + statusText + '\n\n' +
        'Click OK to confirm or Cancel to abort.';
    
    return confirm(message);
}

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