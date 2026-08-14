<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if (!in_array($_SESSION['role'], ['sales', 'cashier'])) {
    die("ACCESS DENIED. Only sales personnel and cashiers can sell accessories.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : ($_SESSION['current_sale_id'] ?? 0);
if (!$sale_id) {
    header("Location: make_sale.php?error=no_sale_selected");
    exit;
}

$stmt = $conn->prepare("SELECT s.id, s.sold_by, s.sale_status, u.full_name AS salesperson_name FROM sales s LEFT JOIN users u ON s.sold_by = u.id WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale || $sale['sale_status'] !== 'active') {
    header("Location: make_sale.php?error=invalid_sale");
    exit;
}
$sales_person = (int)$sale['sold_by'];
$salesperson_name = $sale['salesperson_name'] ?? 'Unknown';

// Get user's branch
$stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_branch = $stmt->fetchColumn();
if (!$user_branch) die("Your account has no branch assigned.");

$error = "";
$success = "";
$mode = $_GET['mode'] ?? 'store'; // 'store' or 'display'

function buildAccessorySpecs($log) {
    $specs = "";
    if (!empty($log['accessory_name'])) $specs .= $log['accessory_name'];
    if (!empty($log['branch'])) $specs .= " | " . $log['branch'];
    if (!empty($log['quantity'])) $specs .= " | Qty: " . $log['quantity'];
    return trim($specs, " |");
}

function updateSaleTotal($conn, $sale_id) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_price), 0) FROM sale_items WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
    $new_total = $stmt->fetchColumn();
    $stmt = $conn->prepare("UPDATE sales SET total_amount = ? WHERE id = ?");
    $stmt->execute([$new_total, $sale_id]);
}

// ============================================================
// MODE 1: STORE ACCESSORIES (follows charger/HDD pattern)
// ============================================================
$pendingStore = [];
if ($mode === 'store') {
    $pendingStmt = $conn->prepare("
        SELECT l.*, a.price AS stock_price, a.name AS accessory_name
        FROM accessories_logs l
        LEFT JOIN accessories a ON l.accessory_id = a.id
        WHERE l.given_to = ? AND l.status = 'pending_sale'
        ORDER BY l.date_given DESC
    ");
    $pendingStmt->execute([$sales_person]);
    $pendingStore = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// MODE 2: DISPLAY ACCESSORIES (direct inventory)
// ============================================================
$displayAccessories = [];
if ($mode === 'display') {
    $stmt = $conn->prepare("
        SELECT * FROM accessories 
        WHERE status = 'instock' AND place = 'display' AND branch = ?
        ORDER BY name
    ");
    $stmt->execute([$user_branch]);
    $displayAccessories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// HANDLE STORE ACCESSORY SALE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sell_store_accessory'])) {
    $log_id = (int) $_POST['log_id'];
    $selling_price = (float) $_POST['selling_price'];

    if ($selling_price <= 0) {
        $error = "Please enter a valid selling price.";
    } else {
        try {
            $conn->beginTransaction();

            $logStmt = $conn->prepare("
                SELECT l.*, a.price AS stock_price, a.name AS accessory_name
                FROM accessories_logs l
                LEFT JOIN accessories a ON l.accessory_id = a.id
                WHERE l.id = ? AND l.given_to = ? AND l.status = 'pending_sale'
                FOR UPDATE
            ");
            $logStmt->execute([$log_id, $sales_person]);
            $log = $logStmt->fetch(PDO::FETCH_ASSOC);

            if (!$log) {
                throw new Exception("Accessory log not found, not assigned to the salesperson, or already sold.");
            }

            if ($log['stock_price'] !== null && $selling_price < $log['stock_price']) {
                throw new Exception("Selling price cannot be lower than the set price of KES " . number_format($log['stock_price'], 2));
            }

            $specs = buildAccessorySpecs($log);

            $insert = $conn->prepare("
                INSERT INTO sale_items 
                (sale_id, item_type, item_id, description, quantity, unit_price, sales_person)
                VALUES (?, 'accessory', ?, ?, ?, ?, ?)
            ");
            $insert->execute([
                $sale_id,
                $log['accessory_id'],
                $specs,
                $log['quantity'],
                $selling_price,
                $sales_person
            ]);
            $sale_item_id = $conn->lastInsertId();

            // Insert into sold_accessories
            $soldInsert = $conn->prepare("
                INSERT INTO sold_accessories 
                (accessory_id, accessory_name, quantity, selling_price, branch, sold_by, date_sold, sale_item_id) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $soldInsert->execute([
                $log['accessory_id'],
                $log['accessory_name'],
                $log['quantity'],
                $selling_price,
                $log['branch'],
                $user_id,
                $sale_item_id
            ]);

            $update = $conn->prepare("UPDATE accessories_logs SET status = 'sold', sale_item_id = ? WHERE id = ?");
            $update->execute([$sale_item_id, $log_id]);

            $updateSale = $conn->prepare("UPDATE sales SET completion_status = 'pending' WHERE id = ?");
            $updateSale->execute([$sale_id]);

            updateSaleTotal($conn, $sale_id);

            $activity = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Sold Accessory (Store)', ?)");
            $activity->execute([
                $user_id,
                "Sold Accessory ({$log['accessory_name']}) - Quantity: {$log['quantity']} for KES " . number_format($selling_price, 2) . " in sale #$sale_id"
            ]);

            $conn->commit();
            header("Location: checkout.php?sale_id=$sale_id&success=accessory_sold");
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// ============================================================
// HANDLE DISPLAY ACCESSORY SALE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sell_display_accessory'])) {
    $accessory_id = (int) $_POST['accessory_id'];
    $quantity_sold = (int) $_POST['quantity_sold'];
    $selling_price = (float) $_POST['selling_price'];

    if ($selling_price <= 0 || $quantity_sold <= 0) {
        $error = "Please enter valid quantity and selling price.";
    } else {
        try {
            $conn->beginTransaction();

            // Get accessory details with lock
            $stmt = $conn->prepare("SELECT * FROM accessories WHERE id = ? AND status = 'instock' AND place = 'display' AND branch = ? FOR UPDATE");
            $stmt->execute([$accessory_id, $user_branch]);
            $accessory = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$accessory) {
                throw new Exception("Accessory not found, not in stock, or not available in your branch.");
            }

            if ($accessory['quantity'] < $quantity_sold) {
                throw new Exception("Insufficient quantity. Available: " . $accessory['quantity']);
            }

            // Update accessory quantity
            $new_quantity = $accessory['quantity'] - $quantity_sold;
            $new_status = ($new_quantity == 0) ? 'sold' : 'instock';

            $update = $conn->prepare("UPDATE accessories SET quantity = ?, status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
            $update->execute([$new_quantity, $new_status, $user_id, $accessory_id]);

            // Build specs
            $specs = $accessory['name'] . " | " . $accessory['branch'] . " | " . $accessory['place'];

            // Insert into sale_items
            $insert = $conn->prepare("
                INSERT INTO sale_items 
                (sale_id, item_type, item_id, description, quantity, unit_price, sales_person)
                VALUES (?, 'accessory', ?, ?, ?, ?, ?)
            ");
            $insert->execute([
                $sale_id,
                $accessory_id,
                $specs,
                $quantity_sold,
                $selling_price,
                $sales_person
            ]);
            $sale_item_id = $conn->lastInsertId();

            // Insert into sold_accessories
            $soldInsert = $conn->prepare("
                INSERT INTO sold_accessories 
                (accessory_id, accessory_name, quantity, selling_price, branch, sold_by, date_sold, sale_item_id) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $soldInsert->execute([
                $accessory_id,
                $accessory['name'],
                $quantity_sold,
                $selling_price,
                $accessory['branch'],
                $user_id,
                $sale_item_id
            ]);

            $updateSale = $conn->prepare("UPDATE sales SET completion_status = 'pending' WHERE id = ?");
            $updateSale->execute([$sale_id]);

            updateSaleTotal($conn, $sale_id);

            $activity = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Sold Accessory (Display)', ?)");
            $activity->execute([
                $user_id,
                "Sold Accessory ({$accessory['name']}) - Quantity: {$quantity_sold} for KES " . number_format($selling_price, 2) . " in sale #$sale_id"
            ]);

            $conn->commit();
            header("Location: checkout.php?sale_id=$sale_id&success=accessory_sold");
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// ============================================================
// INCLUDE HEADER & SIDEBAR
// ============================================================


date_default_timezone_set('Africa/Nairobi');
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sell Accessory | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same styles as sell_charger.php + mode toggle */
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
        .btn-checkout { background: #2563eb; color: white; border: none; padding: 0.5rem 1rem; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; width: auto; text-decoration: none; }
        .btn-checkout:hover { background: #1d4ed8; transform: translateY(-2px); }
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
        .qty-input { width: 70px; padding: 0.4rem 0.6rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8rem; }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .text-muted { color: var(--gray-500); font-size: 0.85rem; }
        .min-price { font-size: 0.75rem; color: var(--gray-400); }
        .mode-toggle { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; background: white; padding: 0.5rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); display: inline-flex; }
        .mode-toggle .btn-mode { padding: 0.5rem 1.5rem; border: none; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s; background: transparent; color: var(--gray-600); text-decoration: none; }
        .mode-toggle .btn-mode.active { background: var(--primary); color: white; }
        .mode-toggle .btn-mode:hover:not(.active) { background: var(--gray-100); }
        .section-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }

        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1rem 1.25rem; }
            .price-input { width: 90px; }
            .qty-input { width: 60px; }
            table { font-size: 0.8rem; min-width: 600px; }
            th, td { padding: 0.6rem; }
            .mode-toggle { flex-wrap: wrap; }
            .mode-toggle .btn-mode { padding: 0.4rem 1rem; font-size: 0.8rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header h1 { font-size: 1.1rem; }
            .price-input { width: 75px; }
            .qty-input { width: 55px; }
        }
    </style>
</head>
<body>
     <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-plug"></i> Sell Accessory</h1>
        <div class="breadcrumb">
            <a href="../dashboard/<?= $_SESSION['role'] === 'sales' ? 'salesdashboard.php' : 'cashierdashboard.php' ?>">Dashboard</a>
            <span> / </span>
            <a href="make_sale.php">Make a Sale</a>
            <span> / </span>
            <span>Sell Accessory</span>
        </div>
        <?php if ($sale_id): ?>
            <div style="margin-top:0.5rem; display:flex; flex-wrap:wrap; align-items:center; gap:1rem;">
                <div style="font-weight:500; color:var(--gray-600);">
                    <i class="fas fa-shopping-cart"></i> Sale: #<?= $sale_id ?>
                    <?php if ($user_role === 'cashier'): ?>
                        <span style="margin-left:0.75rem; font-size:0.85rem; color:var(--gray-500);">
                            <i class="fas fa-user"></i> Salesperson: <?= htmlspecialchars($salesperson_name) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <a href="checkout.php?sale_id=<?= $sale_id ?>" class="btn-checkout"><i class="fas fa-arrow-right"></i> Go to Checkout</a>
                <a href="make_sale.php" style="font-size:0.8rem; color:var(--primary); text-decoration:none;"><i class="fas fa-undo"></i> Change Sale</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Mode Toggle -->
    <div class="mode-toggle">
        <a href="?sale_id=<?= $sale_id ?>&mode=store" class="btn-mode <?= $mode === 'store' ? 'active' : '' ?>">
            <i class="fas fa-warehouse"></i> Store Accessories
        </a>
        <a href="?sale_id=<?= $sale_id ?>&mode=display" class="btn-mode <?= $mode === 'display' ? 'active' : '' ?>">
            <i class="fas fa-desktop"></i> Display Accessories
        </a>
    </div>

    <?php if ($mode === 'store'): ?>
        <!-- ========================================================== -->
        <!-- STORE ACCESSORIES MODE (follows charger/HDD pattern)        -->
        <!-- ========================================================== -->
        <div class="section-title"><i class="fas fa-boxes"></i> Store Accessories (Pending)</div>
        <div class="table-wrapper">
            <?php if (empty($pendingStore)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <?php if ($user_role === 'cashier'): ?>
                        <p>No pending store accessories assigned to this salesperson.</p>
                    <?php else: ?>
                        <p>No pending store accessories assigned to you.</p>
                        <p class="text-muted" style="margin-top:0.5rem;">Store accessories must be given to you by the inventory admin before they can be sold.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Accessory Name</th>
                            <th>Quantity</th>
                            <th>Branch</th>
                            <th>Set Price (KES)</th>
                            <th>Selling Price (KES)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($pendingStore as $log): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($log['accessory_name']) ?></strong></td>
                                <td><span class="badge"><?= (int)$log['quantity'] ?></span></td>
                                <td><span class="badge"><?= htmlspecialchars($log['branch']) ?></span></td>
                                <td>
                                    <?php if ($log['stock_price'] !== null): ?>
                                        <span class="text-muted"><?= number_format($log['stock_price'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                        <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                        <input type="number" name="selling_price" class="price-input" step="0.01" min="0.01"
                                               value="<?= $log['stock_price'] ?? '' ?>"
                                               placeholder="Price" required>
                                        <?php if ($log['stock_price'] !== null): ?>
                                            <span class="min-price">Min: <?= number_format($log['stock_price'], 2) ?></span>
                                        <?php endif; ?>
                                        <button type="submit" name="sell_store_accessory" class="btn btn-primary"><i class="fas fa-check"></i> Sell</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- ========================================================== -->
        <!-- DISPLAY ACCESSORIES MODE (direct inventory)                 -->
        <!-- ========================================================== -->
        <div class="section-title"><i class="fas fa-desktop"></i> Display Accessories (In Stock)</div>
        <div class="table-wrapper">
            <?php if (empty($displayAccessories)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>No display accessories in stock.</p>
                    <p class="text-muted" style="margin-top:0.5rem;">Display accessories are items available on the shelf for direct sale.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Accessory Name</th>
                            <th>Available Qty</th>
                            <th>Unit Price (KES)</th>
                            <th colspan="4" style="text-align:center;">Sell</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($displayAccessories as $accessory): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($accessory['name']) ?></strong></td>
                                <td><span class="badge"><?= (int)$accessory['quantity'] ?></span></td>
                                <td>
                                    <?php if ($accessory['price'] !== null): ?>
                                        <span class="text-muted"><?= number_format($accessory['price'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td colspan="4">
                                    <form method="POST" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                        <input type="hidden" name="accessory_id" value="<?= $accessory['id'] ?>">
                                        <input type="number" name="quantity_sold" class="qty-input" step="1" min="1" max="<?= $accessory['quantity'] ?>" value="1" required>
                                        <input type="number" name="selling_price" class="price-input" step="0.01" min="0.01"
                                               value="<?= $accessory['price'] ?? '' ?>"
                                               placeholder="Price" required>
                                        <?php if ($accessory['price'] !== null): ?>
                                            <span class="min-price">Suggested: <?= number_format($accessory['price'], 2) ?></span>
                                        <?php endif; ?>
                                        <button type="submit" name="sell_display_accessory" class="btn btn-primary"><i class="fas fa-cart-plus"></i> Add to Sale</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
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

<?php require_once "../includes/footer.php"; ?>
</body>
</html>