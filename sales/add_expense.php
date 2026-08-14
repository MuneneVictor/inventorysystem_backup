<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// ============================================================
// STRICT ROLE CHECK - Only cashier, super_admin, and manager
// ============================================================
if (!in_array($_SESSION['role'], ['cashier', 'super_admin', 'manager'])) {
    die("ACCESS DENIED: You do not have permission to access this page.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Get user branch
$stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_branch = $stmt->fetchColumn();
if (!$user_branch) {
    die("Your account has no branch assigned. Contact administrator.");
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// PROCESS FORM SUBMISSION
// ============================================================
$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $expense_name = trim($_POST['expense_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $total_amount = trim($_POST['total_amount'] ?? '');
    $given_to = trim($_POST['given_to'] ?? '');

    // Validation
    if (empty($expense_name)) {
        $error = "Expense name is required.";
    } elseif (empty($payment_method)) {
        $error = "Payment method is required.";
    } elseif (empty($total_amount) || !is_numeric($total_amount) || $total_amount <= 0) {
        $error = "Please enter a valid amount greater than 0.";
    } elseif (empty($given_to)) {
        $error = "Please enter the name of the person the expense was given to.";
    } else {
        try {
            // Insert expense
            $insert = $conn->prepare("
                INSERT INTO expenses (
                    expense_name, 
                    description, 
                    payment_method, 
                    total_amount, 
                    expense_date, 
                    created_by, 
                    branch,
                    given_to
                ) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)
            ");
            $insert->execute([
                $expense_name,
                $description ?: null,
                $payment_method,
                $total_amount,
                $user_id,
                $user_branch,
                $given_to
            ]);

            $expense_id = $conn->lastInsertId();

            // Activity log
            $log = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, details) 
                VALUES (?, 'Add Expense', ?)
            ");
            $log->execute([
                $user_id,
                "Added expense: $expense_name | Amount: KES " . number_format($total_amount, 2) . " | Method: $payment_method | Given To: $given_to | Branch: $user_branch"
            ]);

            $success = "Expense added successfully!";
            
            // Clear form after success
            $_POST = array();
            
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// ============================================================
// TIME GREETING
// ============================================================
date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';

// Helper function to safely escape HTML
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Add Expense | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
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
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --font-sans: 'Inter', system-ui, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-sans);
            background: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
            overflow-x: hidden;
            min-height: 100vh;
        }

        .main-content {
            padding: 2rem 2rem 1rem;
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;
            background: var(--gray-100);
            transition: all 0.3s ease;
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
            margin-top: 0.5rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .user-info {
            margin-top: 0.5rem;
            color: var(--gray-500);
            font-size: 0.85rem;
        }
        .user-info i {
            color: var(--primary);
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid transparent;
            font-weight: 500;
        }

        .alert-success {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }

        .alert-error {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .alert i {
            font-size: 1.25rem;
        }

        .form-container {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            max-width: 700px;
            margin: 0 auto;
        }

        .form-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }

        .form-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-header h2 i {
            color: var(--primary);
        }

        .form-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.4rem;
        }

        .form-group label .required {
            color: #dc2626;
            margin-left: 0.2rem;
        }

        .form-group label i {
            color: var(--primary);
            margin-right: 0.3rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-family: var(--font-sans);
            background: white;
            transition: border-color 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group .help-text {
            font-size: 0.7rem;
            color: var(--gray-400);
            margin-top: 0.3rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .btn-block {
            width: 100%;
            justify-content: center;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }

        .branch-badge {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 0.15rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

        .footer span {
            color: var(--primary);
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
                padding: 1.25rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .form-container {
                max-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem 0.5rem 0.5rem !important;
                padding-top: 4rem !important;
            }
            .page-header {
                padding: 1rem;
            }
            .page-header h1 {
                font-size: 1.1rem;
            }
            .form-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>
                <i class="fas fa-money-bill-wave"></i>
                Add Expense
            </h1>
            <div class="breadcrumb">
                <?php if ($user_role === 'super_admin'): ?>
                    <a href="../dashboard/superadmindashboard.php">Dashboard</a>
                <?php elseif ($user_role === 'manager'): ?>
                    <a href="../dashboard/managerdashboard.php">Dashboard</a>
                <?php else: ?>
                    <a href="../dashboard/cashierdashboard.php">Dashboard</a>
                <?php endif; ?>
                <span> / </span>
                <span>Add Expense</span>
            </div>
            <div class="user-info">
                <i class="fas fa-store"></i> Branch: <?= safe($user_branch) ?>
                <span class="branch-badge"><?= safe($user_role) ?></span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= safe($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= safe($success) ?></span>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <div class="form-header">
                <h2>
                    <i class="fas fa-receipt"></i>
                    Expense Details
                </h2>
            </div>
            <div class="form-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                    <div class="form-group">
                        <label>
                            <i class="fas fa-tag"></i> Expense Name <span class="required">*</span>
                        </label>
                        <input type="text" name="expense_name" placeholder="e.g. Transport, Office Supplies, Electricity" 
                               value="<?= safe($_POST['expense_name'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-align-left"></i> Description
                        </label>
                        <textarea name="description" rows="3" placeholder="Brief description of the expense..."><?= safe($_POST['description'] ?? '') ?></textarea>
                        <div class="help-text"><i class="fas fa-info-circle"></i> Provide additional details about this expense</div>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-user"></i> Given To <span class="required">*</span>
                        </label>
                        <input type="text" name="given_to" placeholder="Name of the person receiving the expense" 
                               value="<?= safe($_POST['given_to'] ?? '') ?>" required>
                        <div class="help-text"><i class="fas fa-info-circle"></i> Enter the full name of the person the expense was given to</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-money-bill"></i> Payment Method <span class="required">*</span>
                            </label>
                            <select name="payment_method" required>
                                <option value="">-- Select Method --</option>
                                <option value="cash" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'cash') ? 'selected' : '' ?>>Cash</option>
                                <option value="Mpesa" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'Mpesa') ? 'selected' : '' ?>>M-Pesa</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-coins"></i> Amount (KES) <span class="required">*</span>
                            </label>
                            <input type="number" name="total_amount" step="0.01" min="0.01" 
                                   placeholder="0.00" value="<?= safe($_POST['total_amount'] ?? '') ?>" required>
                            <div class="help-text"><i class="fas fa-info-circle"></i> Enter the total expense amount</div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Save Expense
                        </button>
                        <a href="<?= $user_role === 'cashier' ? '../dashboard/cashierdashboard.php' : ($user_role === 'manager' ? '../dashboard/managerdashboard.php' : '../dashboard/superadmindashboard.php') ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="footer">
            <i class="fas fa-copyright"></i> <?= date('Y'); ?> <span>Mombasa Computers</span>. All rights reserved.
            <span style="margin:0 0.5rem;">•</span>
            <span>v2.0.0</span>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function adjustMainContent() {
            var mainContent = document.querySelector('.main-content');
            if (window.innerWidth <= 1200) {
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.width = '100%';
                    mainContent.style.paddingTop = '5rem';
                }
            } else {
                if (mainContent) {
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

    <?php require_once "../includes/footer.php"; ?>
</body>
</html>