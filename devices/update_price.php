<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if (!in_array($_SESSION['role'], ['super_admin', 'manager'])) {
    die("Access denied.");
}

// Get all parameters from GET (including device_condition)
$cargo = $_GET['cargo'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$model = $_GET['model'] ?? '';
$processor = $_GET['processor'] ?? '';
$ram = $_GET['ram'] ?? '';
$storage_type = $_GET['storage_type'] ?? '';
$storage_capacity = $_GET['storage_capacity'] ?? '';
$graphics = $_GET['graphics'] ?? '';
$touch = $_GET['touch'] ?? '';
$device_condition = $_GET['device_condition'] ?? 'Ex-Uk';

if (!$cargo || !$category_id || !$model) {
    die("Invalid request");
}

// Fetch category name for display
$stmt = $conn->prepare("SELECT category_name FROM categories WHERE id = ?");
$stmt->execute([$category_id]);
$category_name = $stmt->fetchColumn() ?: 'Unknown';

// Get count of devices in this group (including device_condition) that have a price
$stmt_count = $conn->prepare("
    SELECT COUNT(*) as total_count 
    FROM devices 
    WHERE cargo_number = :cargo
      AND category_id = :category_id
      AND model_name = :model_name
      AND processor = :processor
      AND ram = :ram
      AND storage_type = :storage_type
      AND storage_capacity = :storage_capacity
      AND graphics = :graphics
      AND touch = :touch
      AND device_condition = :device_condition
      AND price IS NOT NULL
");
$stmt_count->execute([
    'cargo' => $cargo,
    'category_id' => $category_id,
    'model_name' => $model,
    'processor' => $processor,
    'ram' => $ram,
    'storage_type' => $storage_type,
    'storage_capacity' => $storage_capacity,
    'graphics' => $graphics,
    'touch' => $touch,
    'device_condition' => $device_condition
]);
$total_count = $stmt_count->fetchColumn();

// Get current price (from first device in the group)
$stmt_price = $conn->prepare("
    SELECT price 
    FROM devices 
    WHERE cargo_number = :cargo
      AND category_id = :category_id
      AND model_name = :model_name
      AND processor = :processor
      AND ram = :ram
      AND storage_type = :storage_type
      AND storage_capacity = :storage_capacity
      AND graphics = :graphics
      AND touch = :touch
      AND device_condition = :device_condition
      AND price IS NOT NULL
    LIMIT 1
");
$stmt_price->execute([
    'cargo' => $cargo,
    'category_id' => $category_id,
    'model_name' => $model,
    'processor' => $processor,
    'ram' => $ram,
    'storage_type' => $storage_type,
    'storage_capacity' => $storage_capacity,
    'graphics' => $graphics,
    'touch' => $touch,
    'device_condition' => $device_condition
]);
$current_price = $stmt_price->fetchColumn();

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $price = $_POST['price'];
    $apply_to_all = isset($_POST['apply_to_all']) ? 1 : 0;

    if (!is_numeric($price) || $price <= 0) {
        $error = "Enter a valid price";
    } else {
        if ($apply_to_all) {
            // Update all devices in this group that have a price (same specs and condition)
            $update = $conn->prepare("
                UPDATE devices
                SET price = :price,
                    price_updated_at = NOW()
                WHERE cargo_number = :cargo
                  AND category_id = :category_id
                  AND model_name = :model_name
                  AND processor = :processor
                  AND ram = :ram
                  AND storage_type = :storage_type
                  AND storage_capacity = :storage_capacity
                  AND graphics = :graphics
                  AND touch = :touch
                  AND device_condition = :device_condition
                  AND price IS NOT NULL
            ");

            $update->execute([
                'price' => $price,
                'cargo' => $cargo,
                'category_id' => $category_id,
                'model_name' => $model,
                'processor' => $processor,
                'ram' => $ram,
                'storage_type' => $storage_type,
                'storage_capacity' => $storage_capacity,
                'graphics' => $graphics,
                'touch' => $touch,
                'device_condition' => $device_condition
            ]);
        } else {
            // Update only first device in this group that has a price
            $stmt_first = $conn->prepare("
                SELECT serial_number 
                FROM devices
                WHERE cargo_number = :cargo
                  AND category_id = :category_id
                  AND model_name = :model_name
                  AND processor = :processor
                  AND ram = :ram
                  AND storage_type = :storage_type
                  AND storage_capacity = :storage_capacity
                  AND graphics = :graphics
                  AND touch = :touch
                  AND device_condition = :device_condition
                  AND price IS NOT NULL
                LIMIT 1
            ");
            $stmt_first->execute([
                'cargo' => $cargo,
                'category_id' => $category_id,
                'model_name' => $model,
                'processor' => $processor,
                'ram' => $ram,
                'storage_type' => $storage_type,
                'storage_capacity' => $storage_capacity,
                'graphics' => $graphics,
                'touch' => $touch,
                'device_condition' => $device_condition
            ]);
            $serial = $stmt_first->fetchColumn();

            if ($serial) {
                $update = $conn->prepare("
                    UPDATE devices
                    SET price = :price,
                        price_updated_at = NOW()
                    WHERE serial_number = :serial
                ");
                $update->execute([
                    'price' => $price,
                    'serial' => $serial
                ]);
            }
        }

        header("Location: price_list.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Update Price | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Same CSS as add_price.php */
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
        .breadcrumb a:hover { text-decoration: underline; }

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
            width: 100px;
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
            .page-header { padding: 1rem 1.25rem; }
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
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-edit"></i>
            Update Price
        </h1>
        <div class="breadcrumb">
              <?php if($_SESSION['role'] === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>       
            <?php endif; ?>
            <?php if($_SESSION['role'] === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="price_list.php">Price List</a>
            <span> / </span>
            <span>Update Price</span>
        </div>
    </div>

    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-dollar-sign"></i>
                    Update Device Price
                </h2>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <!-- Device Information -->
                    <div class="specs-box">
                        <p><strong>Cargo Number:</strong> <?= htmlspecialchars($cargo) ?></p>
                        <p><strong>Category:</strong> <?= htmlspecialchars($category_name) ?></p>
                        <p><strong>Model:</strong> <?= htmlspecialchars($model) ?></p>
                        <p><strong>Processor:</strong> <?= htmlspecialchars($processor) ?></p>
                        <p><strong>RAM:</strong> <?= htmlspecialchars($ram) ?> GB</p>
                        <p><strong>Storage:</strong> <?= htmlspecialchars($storage_type . ' ' . $storage_capacity . 'GB') ?></p>
                        <p><strong>Graphics:</strong> <?= htmlspecialchars($graphics ?: 'Integrated') ?></p>
                        <p><strong>Touch:</strong> <?= htmlspecialchars($touch ?: 'N/A') ?></p>
                        <p><strong>Condition:</strong> <?= htmlspecialchars($device_condition) ?></p>
                    </div>

                    <?php if ($total_count > 1): ?>
                        <div class="info-note">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> There are <strong><?= $total_count ?></strong> devices in this group that have a price (same specs and condition).
                            You can update the price for all matching devices or just one.
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="apply_to_all" name="apply_to_all" value="1" checked>
                            <label for="apply_to_all">Apply this price to ALL <?= $total_count ?> devices with same specifications and condition</label>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>New Price (KES)</label>
                        <input type="number" name="price" step="0.01" min="0.01" value="<?= htmlspecialchars($current_price) ?>" required placeholder="Enter price in Kenyan Shillings">
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