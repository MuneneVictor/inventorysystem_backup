<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

// Only sales and cashier roles
if (!in_array($_SESSION['role'], ['sales', 'cashier'])) {
    die("ACCESS DENIED. Only sales personnel and cashiers can sell RAM/SSD.");
}

$user_id = (int) $_SESSION['user_id'];
$error = "";
$success = "";

// Handle sale submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sell_ram_ssd'])) {
    $log_id = (int) $_POST['log_id'];
    $selling_price = (float) $_POST['selling_price'];

    if ($selling_price <= 0) {
        $error = "Please enter a valid selling price.";
    } else {
        try {
            $conn->beginTransaction();

            // Fetch the log entry with lock and join with rams_ssds for price
            $logStmt = $conn->prepare("
                SELECT l.*, s.price AS stock_price 
                FROM rams_ssds_logs l
                LEFT JOIN rams_ssds s ON l.ram_ssd_id = s.id
                WHERE l.id = ? AND l.given_to = ? AND l.status = 'pending_sale'
                FOR UPDATE
            ");
            $logStmt->execute([$log_id, $user_id]);
            $log = $logStmt->fetch(PDO::FETCH_ASSOC);

            if (!$log) {
                throw new Exception("RAM/SSD log not found, not assigned to you, or already sold.");
            }

            // Check minimum price (if set)
            if ($log['stock_price'] !== null && $selling_price < $log['stock_price']) {
                throw new Exception("Selling price cannot be lower than the set price of KES " . number_format($log['stock_price'], 2));
            }

            // Insert into sold_rams_ssds
            $total_price = $selling_price * $log['quantity_given'];
            $insert = $conn->prepare("
                INSERT INTO sold_rams_ssds 
                (ram_ssd_id, category, type, storage, branch, quantity, selling_price, date_sold, sold_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $insert->execute([
                $log['ram_ssd_id'],
                $log['category'],
                $log['type'],
                $log['storage'],
                $log['branch'],
                $log['quantity_given'],
                $selling_price,
                $user_id
            ]);

            // Update rams_ssds_logs status to sold
            $update = $conn->prepare("UPDATE rams_ssds_logs SET status = 'sold' WHERE id = ?");
            $update->execute([$log_id]);

            // Activity log
            $sales_name = $_SESSION['full_name'] ?? 'User';
            $activity = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Sold RAM/SSD', ?)");
            $activity->execute([
                $user_id,
                "Sold {$log['category']} ({$log['type']}, {$log['storage']}GB) - Quantity: {$log['quantity_given']} for KES " . number_format($selling_price, 2)
            ]);

            $conn->commit();
            $success = "RAM/SSD sold successfully!";

        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch pending RAM/SSD items assigned to this user, including the stock price
$pendingStmt = $conn->prepare("
    SELECT l.*, s.price AS stock_price
    FROM rams_ssds_logs l
    LEFT JOIN rams_ssds s ON l.ram_ssd_id = s.id
    WHERE l.given_to = ? AND l.status = 'pending_sale'
    ORDER BY l.date_given DESC
");
$pendingStmt->execute([$user_id]);
$pending = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

date_default_timezone_set('Africa/Nairobi');
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sell RAM/SSD | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same base styles as sell_hdd.php */
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
        .page-header h1 i { color: var(--primary); }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .alert { padding: 1rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; box-shadow: var(--shadow-sm); }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; min-width: 800px; }
        th { background: var(--gray-50); padding: 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); }
        td { padding: 0.9rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: var(--gray-100); }
        .price-input { width: 120px; padding: 0.4rem 0.6rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; }
        .price-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(26,75,42,0.1); }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-300); }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .text-muted { color: var(--gray-500); font-size: 0.85rem; }
        .min-price { font-size: 0.75rem; color: var(--gray-400); }

        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1rem 1.25rem; }
            .price-input { width: 90px; }
            table { font-size: 0.8rem; min-width: 600px; }
            th, td { padding: 0.6rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header h1 { font-size: 1.1rem; }
            .price-input { width: 75px; }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1>
            <i class="fas fa-memory"></i>
            Sell RAM / SSD
        </h1>
        <div class="breadcrumb">
            <a href="/inventory_system/dashboard/<?= $_SESSION['role'] === 'sales' ? 'salesdashboard.php' : 'cashierdashboard.php' ?>">Dashboard</a>
            <span> / </span>
            <span>Sell RAM/SSD</span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="table-wrapper">
        <?php if (empty($pending)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No pending RAM/SSD items assigned to you.</p>
                <p class="text-muted" style="margin-top:0.5rem;">You can only sell RAM/SSD that have been given to you by the inventory admin.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Storage (GB)</th>
                        <th>Quantity</th>
                        <th>Branch</th>
                        <th>Set Price (KES)</th>
                        <th>Selling Price (KES)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($pending as $log): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><span class="badge"><?= htmlspecialchars($log['category']) ?></span></td>
                            <td><strong><?= htmlspecialchars($log['type']) ?></strong></td>
                            <td><?= htmlspecialchars($log['storage']) ?></td>
                            <td><span class="badge"><?= (int)$log['quantity_given'] ?></span></td>
                            <td><span class="badge"><?= htmlspecialchars($log['branch']) ?></span></td>
                            <td>
                                <?php if ($log['stock_price'] !== null): ?>
                                    <span class="text-muted"><?= number_format($log['stock_price'], 2) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" class="sell-form" 
                                      data-category="<?= htmlspecialchars($log['category']) ?>"
                                      data-type="<?= htmlspecialchars($log['type']) ?>"
                                      data-storage="<?= htmlspecialchars($log['storage']) ?>"
                                      data-quantity="<?= (int)$log['quantity_given'] ?>"
                                      data-branch="<?= htmlspecialchars($log['branch']) ?>"
                                      data-set-price="<?= $log['stock_price'] !== null ? number_format($log['stock_price'], 2) : 'N/A' ?>"
                                      style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                    <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                    <input type="number" name="selling_price" class="price-input" step="0.01" min="0.01"
                                           value="<?= $log['stock_price'] ?? '' ?>"
                                           placeholder="Price"
                                           required>
                                    <?php if ($log['stock_price'] !== null): ?>
                                        <span class="min-price">Min: <?= number_format($log['stock_price'], 2) ?></span>
                                    <?php endif; ?>
                                    <button type="submit" name="sell_ram_ssd" class="btn btn-primary" 
                                            onclick="return confirmSale(this.form)">
                                        <i class="fas fa-check"></i> Sell
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<script>
function confirmSale(form) {
    // Get data from form attributes
    const category = form.dataset.category || 'N/A';
    const type = form.dataset.type || 'N/A';
    const storage = form.dataset.storage || 'N/A';
    const quantity = form.dataset.quantity || '0';
    const branch = form.dataset.branch || 'N/A';
    const setPrice = form.dataset.setPrice || 'N/A';
    
    // Get entered selling price
    const priceInput = form.querySelector('input[name="selling_price"]');
    const sellingPrice = priceInput ? priceInput.value : '0';
    
    // Calculate total
    const total = parseFloat(sellingPrice) * parseInt(quantity);
    const formattedTotal = total.toFixed(2);
    
    // Build confirmation message
    const message = 
        '📦 Confirm Sale\n\n' +
        'Category: ' + category + '\n' +
        'Type: ' + type + '\n' +
        'Storage: ' + storage + ' GB\n' +
        'Quantity: ' + quantity + '\n' +
        'Branch: ' + branch + '\n' +
        'Set Price: KES ' + setPrice + '\n' +
        'Selling Price: KES ' + sellingPrice + '\n' +
        'Total Amount: KES ' + formattedTotal + '\n\n' +
        'Are you sure you want to sell this item?';
    
    return confirm(message);
}
</script>

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