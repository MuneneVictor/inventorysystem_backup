<?php
session_start();

require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'] ?? '';
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

// Manual account creation is restricted to Super Admin.
if ($role !== 'super_admin') {
    die("Access denied! Only Super Administrators can add users manually.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

$allowedRoles = [
    'super_admin'      => 'Super Admin',
    'inventory_admin'  => 'Inventory Admin',
    'technician'       => 'Technician',
    'maintenance'      => 'Software',
    'sales'            => 'Sales',
    'manager'          => 'Manager',
    'cashier'          => 'Cashier',
    'software'         => 'Software',
];

$allowedBranches = ['KIMATHI', 'MOI'];

$old = [
    'full_name' => '',
    'email' => '',
    'username' => '',
    'role' => '',
    'branch' => '',
    'is_active' => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim((string)($_POST['full_name'] ?? ''));
    $old['email'] = strtolower(trim((string)($_POST['email'] ?? '')));
    $old['username'] = trim((string)($_POST['username'] ?? ''));
    $old['role'] = trim((string)($_POST['role'] ?? ''));
    $old['branch'] = strtoupper(trim((string)($_POST['branch'] ?? '')));
    $old['is_active'] = (string)($_POST['is_active'] ?? '1');

    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
    ) {
        $error = "Security validation failed. Please try again.";
    } elseif ($old['full_name'] === '') {
        $error = "Full name is required.";
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!array_key_exists($old['role'], $allowedRoles)) {
        $error = "Please select a valid role.";
    } elseif (!in_array($old['branch'], $allowedBranches, true)) {
        $error = "Please select a valid branch.";
    } elseif (!in_array($old['is_active'], ['0', '1'], true)) {
        $error = "Please select a valid account status.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $error = "Password must contain at least one symbol.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        try {
            $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkEmail->execute([$old['email']]);

            if ($checkEmail->fetchColumn()) {
                $error = "A user with this email address already exists.";
            } else {
                // Username is optional, but if provided do not allow a duplicate username.
                if ($old['username'] !== '') {
                    $checkUsername = $conn->prepare("
                        SELECT id
                        FROM users
                        WHERE username = ?
                        LIMIT 1
                    ");
                    $checkUsername->execute([$old['username']]);

                    if ($checkUsername->fetchColumn()) {
                        $error = "This username is already in use.";
                    }
                }

                if ($error === '') {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    $conn->beginTransaction();

                    $insert = $conn->prepare("
                        INSERT INTO users (
                            email,
                            username,
                            password,
                            role,
                            is_active,
                            full_name,
                            branch,
                            failed_attempts,
                            account_locked_until
                        )
                        VALUES (
                            :email,
                            :username,
                            :password,
                            :role,
                            :is_active,
                            :full_name,
                            :branch,
                            0,
                            NULL
                        )
                    ");

                    $insert->execute([
                        'email' => $old['email'],
                        'username' => $old['username'] !== '' ? $old['username'] : null,
                        'password' => $hashedPassword,
                        'role' => $old['role'],
                        'is_active' => (int)$old['is_active'],
                        'full_name' => $old['full_name'],
                        'branch' => $old['branch'],
                    ]);

                    $newUserId = (int)$conn->lastInsertId();

                    $log = $conn->prepare("
                        INSERT INTO activity_logs (user_id, action, details)
                        VALUES (?, 'Manual User Creation', ?)
                    ");

                    $statusLabel = $old['is_active'] === '1' ? 'Active' : 'Inactive';

                    $log->execute([
                        $currentUserId,
                        "Created user ID {$newUserId}: {$old['full_name']} ({$old['email']}), " .
                        "role {$old['role']}, branch {$old['branch']}, status {$statusLabel}"
                    ]);

                    $conn->commit();

                    $_SESSION['success'] = "User {$old['full_name']} created successfully.";
                    header("Location: view_users.php");
                    exit();
                }
            }
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            error_log("Manual user creation failed: " . $e->getMessage());
            $error = "Unable to create the user. Please check the details and try again.";
        }
    }
}

require_once "../includes/header.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Add New User | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --danger-text: #991b1b;
            --radius: .75rem;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--gray-100);
            color: var(--gray-800);
        }

        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;
            padding: 2rem;
        }

        .page-header,
        .form-card {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,.05);
        }

        .page-header {
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
        }

        .page-header h1 {
            margin: 0 0 .5rem;
            display: flex;
            align-items: center;
            gap: .65rem;
            font-size: 1.6rem;
        }

        .page-header h1 i {
            color: var(--primary);
        }

        .breadcrumb {
            color: var(--gray-500);
            font-size: .85rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .form-card {
            max-width: 850px;
            margin: 0 auto;
            overflow: hidden;
        }

        .card-header {
            padding: 1.2rem 1.5rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.1rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .alert-error {
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
            padding: .9rem 1rem;
            border-radius: .65rem;
            margin-bottom: 1.25rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: .45rem;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: .82rem;
            font-weight: 700;
            color: var(--gray-700);
        }

        input,
        select {
            width: 100%;
            padding: .75rem .85rem;
            border: 1px solid var(--gray-300);
            border-radius: .6rem;
            background: #fff;
            font: inherit;
            color: var(--gray-800);
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26,75,42,.1);
        }

        .hint {
            font-size: .74rem;
            color: var(--gray-500);
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 3rem;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: .75rem;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: var(--gray-500);
            cursor: pointer;
            padding: .25rem;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: .75rem;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--gray-200);
        }

        .btn {
            border: 0;
            border-radius: .6rem;
            padding: .75rem 1.15rem;
            font-size: .86rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-light);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 5rem 1rem 1rem;
            }
        }

        @media (max-width: 700px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .page-header,
            .card-body {
                padding: 1rem;
            }

            .actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php include "../includes/sidebar.php"; ?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-user-plus"></i> Add New User</h1>
        <div class="breadcrumb">
            <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <a href="view_users.php">Users</a>
            <span> / </span>
            <span>Add New User</span>
        </div>
    </div>

    <div class="form-card">
        <div class="card-header">
            <h2><i class="fas fa-user-shield"></i> Create User Manually</h2>
        </div>

        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="grid">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?= htmlspecialchars($old['full_name']) ?>"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($old['email']) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="username">Username <span class="hint">(Optional)</span></label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            value="<?= htmlspecialchars($old['username']) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="">-- Select Role --</option>
                            <?php foreach ($allowedRoles as $roleValue => $roleLabel): ?>
                                <option
                                    value="<?= htmlspecialchars($roleValue) ?>"
                                    <?= $old['role'] === $roleValue ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($roleLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="branch">Branch</label>
                        <select id="branch" name="branch" required>
                            <option value="">-- Select Branch --</option>
                            <?php foreach ($allowedBranches as $branch): ?>
                                <option
                                    value="<?= htmlspecialchars($branch) ?>"
                                    <?= $old['branch'] === $branch ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($branch) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="is_active">Account Status</label>
                        <select id="is_active" name="is_active" required>
                            <option value="1" <?= $old['is_active'] === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $old['is_active'] === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-wrap">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" data-target="password" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <span class="hint">Minimum 6 characters and at least one symbol.</span>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="password-wrap">
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                required
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <a href="view_users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.password-toggle').forEach(button => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.target);
        const icon = button.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>
</body>
</html>
