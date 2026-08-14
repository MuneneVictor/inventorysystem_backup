<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only super_admin, inventory_admin, manager can access
if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED: You do not have permission to access this page.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get user's branch (if not super_admin)
$user_branch = null;
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
    if (!$user_branch) {
        die("Your account has no branch assigned. Contact administrator.");
    }
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serial = trim($_POST['serial_number'] ?? '');
    $model = trim($_POST['model_name'] ?? '');
    $size = (int) ($_POST['size_inches'] ?? 0);

    // Branch determination
    if ($user_role === 'super_admin') {
        $branch = $_POST['branch'] ?? '';
        if (!$branch) $error = "Please select a branch.";
    } else {
        $branch = $user_branch;
    }

    if (!$error && ($serial === '' || $model === '' || $size <= 0)) {
        $error = "All fields are required.";
    }

    if (!$error) {
        // Check duplicate serial
        $check = $conn->prepare("SELECT serial_number FROM monitors WHERE serial_number = ?");
        $check->execute([$serial]);
        if ($check->rowCount() > 0) {
            $error = "Monitor with this serial number already exists.";
        } else {
            $insert = $conn->prepare("
                INSERT INTO monitors (serial_number, model_name, size_inches, status, branch, added_by)
                VALUES (?, ?, ?, 'In Stock', ?, ?)
            ");
            $insert->execute([$serial, $model, $size, $branch, $user_id]);

            // Log activity
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Added monitor', ?)");
            $log->execute([$user_id, "Added monitor SN: $serial ($model) to $branch branch"]);

            $success = "Monitor added successfully to $branch branch!";
        }
    }
}

// Get current time greeting
date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Add Monitor | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            --font-sans: 'Inter', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: all 0.3s ease; }
        .page-header { background: white; padding: 1.5rem 2rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .page-header h1 { font-size: 1.75rem; color: var(--gray-800); font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .form-container { max-width: 700px; margin: 0 auto; }
        .card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow-sm); }
        .card-header { background: var(--gray-50); padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--gray-200); }
        .card-header h2 { font-size: 1.25rem; font-weight: 600; color: var(--gray-800); display: flex; align-items: center; gap: 0.5rem; }
        .card-header h2 i { color: var(--primary); }
        .card-body { padding: 1.5rem; }
        .info-box { background: var(--gray-50); border-radius: var(--radius-lg); padding: 1rem 1.25rem; margin-bottom: 1.5rem; border-left: 4px solid var(--primary); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--gray-700); margin-bottom: 0.5rem; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; font-family: var(--font-sans); }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,75,42,0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-family: var(--font-sans); }
        .btn-primary { background: var(--primary); color: white; width: 100%; justify-content: center; }
        .btn-primary:hover { background: var(--primary-light); }
        .alert { padding: 1rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .page-header h1 { font-size: 1.25rem; } .card-body { padding: 1rem; } .btn { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-desktop"></i> Add Monitor</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="../dashboard/inventorydashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="monitors_instock.php">Monitors</a>
            <span> / </span>
            <span>Add Monitor</span>
        </div>
    </div>

    <div class="form-container">
        <div class="card">
            <div class="card-header"><h2><i class="fas fa-plus-circle"></i> Monitor Information</h2></div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <div class="info-box">
                    <?php if ($user_role === 'super_admin'): ?>
                        <strong><i class="fas fa-store"></i> You can add monitors to any branch.</strong>
                    <?php else: ?>
                        <strong><i class="fas fa-store"></i> Your branch: <?= htmlspecialchars($user_branch) ?></strong>
                    <?php endif; ?>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_number" required autofocus placeholder="Scan or type serial number">
                    </div>
                    <div class="form-group">
                        <label>Model Name</label>
                        <input type="text" name="model_name" required placeholder="e.g., Dell P2419H">
                    </div>
                    <div class="form-group">
                        <label>Size (inches)</label>
                        <input type="number" name="size_inches" required min="10" placeholder="e.g., 24">
                    </div>
                    <?php if ($user_role === 'super_admin'): ?>
                        <div class="form-group">
                            <label>Branch</label>
                            <select name="branch" required>
                                <option value="">-- Select Branch --</option>
                                <option value="KIMATHI">KIMATHI</option>
                                <option value="MOI">MOI</option>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="branch" value="<?= htmlspecialchars($user_branch) ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Monitor</button>
                </form>
            </div>
        </div>
    </div>
    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>