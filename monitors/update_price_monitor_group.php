<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if (!in_array($_SESSION['role'], ['super_admin', 'manager'])) {
    die("Access denied.");
}

// Get group parameters from GET
$model = trim($_GET['model'] ?? '');
$size = (int) ($_GET['size'] ?? 0);
$condition = trim($_GET['condition'] ?? '');
$current_price = isset($_GET['price']) ? (float) $_GET['price'] : null;

if (empty($model) || $size <= 0 || $current_price === null) {
    die("Invalid group parameters.");
}

// Build condition clause for monitor_condition
$cond_clause = $condition ? '= :condition' : 'IS NULL';

// Count monitors in this group that have this price
$countStmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM monitors 
    WHERE model_name = :model 
      AND size_inches = :size
      AND monitor_condition $cond_clause
      AND price = :price
      AND status = 'In Stock'
");
$params = ['model' => $model, 'size' => $size, 'price' => $current_price];
if ($condition) $params['condition'] = $condition;
$countStmt->execute($params);
$total_count = $countStmt->fetchColumn();

// Fetch one sample monitor to display specs
$sampleStmt = $conn->prepare("
    SELECT * FROM monitors 
    WHERE model_name = :model 
      AND size_inches = :size
      AND monitor_condition $cond_clause
      AND price = :price
      AND status = 'In Stock'
    LIMIT 1
");
$sampleStmt->execute($params);
$sample = $sampleStmt->fetch(PDO::FETCH_ASSOC);

if (!$sample) {
    die("No monitors found matching this group with the current price.");
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_price = trim($_POST['price']);
    $apply_to_all = isset($_POST['apply_to_all']) ? 1 : 0;

    if (!is_numeric($new_price) || $new_price <= 0) {
        $error = "Enter a valid price";
    } else {
        if ($apply_to_all) {
            // Update all monitors in this group that have the current price
            $update = $conn->prepare("
                UPDATE monitors
                SET price = :new_price
                WHERE model_name = :model
                  AND size_inches = :size
                  AND monitor_condition $cond_clause
                  AND price = :old_price
                  AND status = 'In Stock'
            ");
            $updateParams = [
                'new_price' => $new_price,
                'model' => $model,
                'size' => $size,
                'old_price' => $current_price
            ];
            if ($condition) $updateParams['condition'] = $condition;
            $update->execute($updateParams);
            $affected = $update->rowCount();
        } else {
            // Update only the first monitor in this group
            $firstStmt = $conn->prepare("
                SELECT serial_number 
                FROM monitors
                WHERE model_name = :model
                  AND size_inches = :size
                  AND monitor_condition $cond_clause
                  AND price = :price
                  AND status = 'In Stock'
                LIMIT 1
            ");
            $firstStmt->execute($params);
            $serial = $firstStmt->fetchColumn();

            if ($serial) {
                $update = $conn->prepare("
                    UPDATE monitors
                    SET price = :new_price
                    WHERE serial_number = :serial
                ");
                $update->execute(['new_price' => $new_price, 'serial' => $serial]);
                $affected = 1;
            } else {
                $affected = 0;
            }
        }

        // Log activity
        $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (:uid, 'Updated monitor group price', :details)");
        $log->execute([
            'uid' => $_SESSION['user_id'],
            'details' => "Updated price from KES $current_price to KES $new_price for monitor group: $model ($size inch" . ($condition ? ", $condition" : "") . ") – $affected monitors updated"
        ]);

        header("Location: price_list_monitors.php");
        exit();
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Update Monitor Group Price | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ===== SAME CSS AS update_price_smartboard_group.php ===== */
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

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }

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

        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }

        .breadcrumb {
            color: var(--gray-500);
            font-size: 0.9rem;
        }
        .breadcrumb a { color: var(--primary); text-decoration: none; }

        .form-container { max-width: 700px; margin: 0 auto; }
        .card {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
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
        .card-header h2 i { color: var(--primary); }
        .card-body { padding: 1.5rem; }

        .specs-box {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        .specs-box p {
            margin: 0.5rem 0;
            font-size: 0.9rem;
        }
        .specs-box p:first-child { margin-top: 0; }
        .specs-box p:last-child { margin-bottom: 0; }
        .specs-box strong {
            color: var(--primary);
            width: 120px;
            display: inline-block;
        }

        .info-note {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 1rem 1.25rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #1e40af;
        }
        .info-note i { margin-right: 0.5rem; }
        .info-note strong { color: #1e40af; }

        .checkbox-group {
            margin-bottom: 1.5rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }
        .checkbox-group label {
            font-size: 0.9rem;
            color: var(--gray-700);
            cursor: pointer;
            margin: 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
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
            box-shadow: 0 0 0 3px rgba(26,75,42,0.1);
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
            width: 100%;
            justify-content: center;
        }
        .btn-primary:hover { background: var(--primary-light); }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 0.875rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .alert-error i { font-size: 1.1rem; }

        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .card-header { padding: 1rem 1.25rem; }
            .card-body { padding: 1.25rem; }
            .specs-box strong { width: auto; display: block; margin-bottom: 0.25rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header h1 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Update Monitor Group Price</h1>
        <div class="breadcrumb">
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="price_list_monitors.php">Monitor Price List</a>
            <span> / </span>
            <span>Update Price</span>
        </div>
    </div>

    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-dollar-sign"></i> Update Monitor Group Price</h2>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="specs-box">
                        <p><strong>Model:</strong> <?= htmlspecialchars($sample['model_name']) ?></p>
                        <p><strong>Size:</strong> <?= (int)$sample['size_inches'] ?> inch</p>
                        <p><strong>Condition:</strong> <?= htmlspecialchars($sample['monitor_condition'] ?? 'N/A') ?></p>
                        <p><strong>Current Price:</strong> KES <?= number_format($current_price, 2) ?></p>
                    </div>

                    <?php if ($total_count > 1): ?>
                        <div class="info-note">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> There are <strong><?= $total_count ?></strong> monitors in this group that have this price.
                            You can update the price for all of them or just one.
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="apply_to_all" name="apply_to_all" value="1" checked>
                            <label for="apply_to_all">Apply this price to ALL <?= $total_count ?> monitors with same specifications</label>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>New Price (KES)</label>
                        <input type="number" name="price" step="0.01" min="0.01" required value="<?= htmlspecialchars($current_price) ?>" placeholder="Enter new price">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Price
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<script>
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