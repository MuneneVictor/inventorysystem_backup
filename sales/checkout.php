<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Restrict access
if (!in_array($_SESSION['role'], ['sales', 'super_admin', 'inventory_admin', 'manager', 'cashier'])) {
    die("ACCESS DENIED.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Get sale_id from GET or session
$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : ($_SESSION['current_sale_id'] ?? 0);
if (!$sale_id) {
    header("Location: make_sale.php?error=no_sale_selected");
    exit;
}

// Fetch sale details
$stmt = $conn->prepare("SELECT s.*, u.full_name AS salesperson_name FROM sales s LEFT JOIN users u ON s.sold_by = u.id WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale || $sale['sale_status'] !== 'active') {
    header("Location: make_sale.php?error=invalid_sale");
    exit;
}

// Fetch sale items
$stmt = $conn->prepare("SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['total_price'];
}
$grand_total = $subtotal;

// -------------------------------------------------------------------
// Helper: Return item to stock (for cancellation / remove item)
// -------------------------------------------------------------------
function returnItemToStock($conn, $item_type, $item_id, $sale_item_id, $quantity) {
    switch ($item_type) {
        case 'device':
            $stmt = $conn->prepare("UPDATE devices SET status = 'In Stock', sold_at = NULL, sold_by = NULL, selling_price = NULL WHERE serial_number = ?");
            $stmt->execute([$item_id]);
            break;

        case 'monitors':
            $stmt = $conn->prepare("UPDATE monitors SET status = 'In Stock', sold_at = NULL, sold_by = NULL, selling_price = NULL WHERE serial_number = ?");
            $stmt->execute([$item_id]);
            break;

        case 'printers':
            $stmt = $conn->prepare("UPDATE printers SET status = 'In Stock', date_sold = NULL, sold_by = NULL, selling_price = NULL WHERE serial_number = ?");
            $stmt->execute([$item_id]);
            break;

        case 'smartboards':
            $stmt = $conn->prepare("UPDATE smartboards SET status = 'instock', sold_at = NULL, sold_by = NULL, selling_price = NULL WHERE serial_number = ?");
            $stmt->execute([$item_id]);
            break;

        case 'phones':
            $stmt = $conn->prepare("UPDATE phones SET status = 'instock', date_sold = NULL, sold_by = NULL, selling_price = NULL WHERE serial_number = ?");
            $stmt->execute([$item_id]);
            break;

        case 'ups':
            $stmt = $conn->prepare("UPDATE ups SET status = 'instock', date_sold = NULL, sold_by = NULL, selling_price = NULL WHERE serial_number = ?");
            $stmt->execute([$item_id]);
            break;

        case 'ram':
        case 'ssd':
            $stmt = $conn->prepare("UPDATE rams_ssds SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$quantity, $item_id]);
            $stmt = $conn->prepare("DELETE FROM sold_rams_ssds WHERE ram_ssd_id = ? AND sale_item_id = ?");
            $stmt->execute([$item_id, $sale_item_id]);
            break;

        case 'charger':
            $stmt = $conn->prepare("UPDATE chargers SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$quantity, $item_id]);
            $stmt = $conn->prepare("DELETE FROM sold_chargers WHERE charger_id = ? AND sale_item_id = ?");
            $stmt->execute([$item_id, $sale_item_id]);
            break;

        case 'accessory':
            $stmt = $conn->prepare("UPDATE accessories SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$quantity, $item_id]);
            $stmt = $conn->prepare("DELETE FROM sold_accessories WHERE accessory_id = ? AND sale_item_id = ?");
            $stmt->execute([$item_id, $sale_item_id]);
            break;

        case 'hdd':
            $stmt = $conn->prepare("UPDATE hdds SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$quantity, $item_id]);
            $stmt = $conn->prepare("DELETE FROM sold_hdds WHERE hdd_id = ? AND sale_item_id = ?");
            $stmt->execute([$item_id, $sale_item_id]);
            break;

        case 'graphic':
            $stmt = $conn->prepare("UPDATE graphic_cards SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$quantity, $item_id]);
            $stmt = $conn->prepare("DELETE FROM sold_graphics_cards WHERE graphic_card_id = ? AND sale_item_id = ?");
            $stmt->execute([$item_id, $sale_item_id]);
            break;

        default:
            break;
    }
}

// -------------------------------------------------------------------
// Handle POST actions
// -------------------------------------------------------------------
$error = '';
$success_message = '';
$redirect_after = false;
$redirect_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Remove single item ----
    if (isset($_POST['remove_item'])) {
        $sale_item_id = (int)$_POST['item_id'];
        $item_type = $_POST['item_type'];
        $item_id = $_POST['item_id_val'];
        $quantity = (int)$_POST['quantity'];

        $conn->beginTransaction();
        try {
            returnItemToStock($conn, $item_type, $item_id, $sale_item_id, $quantity);
            $stmt = $conn->prepare("DELETE FROM sale_items WHERE id = ? AND sale_id = ?");
            $stmt->execute([$sale_item_id, $sale_id]);
            $stmt = $conn->prepare("SELECT COALESCE(SUM(total_price), 0) FROM sale_items WHERE sale_id = ?");
            $stmt->execute([$sale_id]);
            $new_total = $stmt->fetchColumn();
            $stmt = $conn->prepare("UPDATE sales SET total_amount = ? WHERE id = ?");
            $stmt->execute([$new_total, $sale_id]);

            $conn->commit();
            $_SESSION['success'] = "Item removed and returned to stock.";
            header("Location: checkout.php?sale_id=$sale_id");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Failed to remove item: " . $e->getMessage();
        }
    }

    // ---- Cancel entire sale ----
    if (isset($_POST['cancel_sale'])) {
        $conn->beginTransaction();
        try {
            foreach ($items as $item) {
                returnItemToStock($conn, $item['item_type'], $item['item_id'], $item['id'], $item['quantity']);
            }
            $stmt = $conn->prepare("DELETE FROM sale_items WHERE sale_id = ?");
            $stmt->execute([$sale_id]);
            $stmt = $conn->prepare("UPDATE sales SET sale_status = 'cancelled', completed_at = NOW() WHERE id = ?");
            $stmt->execute([$sale_id]);
            unset($_SESSION['current_sale_id']);
            $conn->commit();

            $success_message = "Sale #$sale_id cancelled. All items returned to stock.";
            $redirect_after = true;
            $redirect_url = "make_sale.php";

        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Failed to cancel sale: " . $e->getMessage();
        }
    }

    // ---- Complete sale (cashier only) with payment details ----
    if (isset($_POST['complete_sale']) && $user_role === 'cashier') {
        $payment_status = $_POST['payment_status'] ?? 'unpaid';
        $payment_method = $_POST['payment_method'] ?? null;
        if ($payment_status === 'paid' && empty($payment_method)) {
            $error = "Please select a payment method for paid status.";
        } else {
            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare("UPDATE sales SET sale_status = 'completed', completion_status = 'Completed', completed_at = NOW(), payment_status = ?, payment_method = ? WHERE id = ?");
                $stmt->execute([$payment_status, $payment_method, $sale_id]);
                unset($_SESSION['current_sale_id']);
                $conn->commit();

                $success_message = "Sale #$sale_id completed successfully.";
                $redirect_after = true;
                $redirect_url = "make_sale.php";

            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Failed to complete sale: " . $e->getMessage();
            }
        }
    }
}

// If we need to redirect, we'll do it via JavaScript after showing the message.
// But we also keep the session success as fallback.
if ($redirect_after) {
    $_SESSION['success'] = $success_message;
    // We will still display the message on this page and auto-redirect.
    // The HTML will include a meta refresh or JS redirect.
}

// Handle success/error messages from session (for remove_item)
if (isset($_SESSION['success']) && !$success_message) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

// ============================================================
// NOW INCLUDE HEADER AND SIDEBAR (HTML OUTPUT)
// ============================================================
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

date_default_timezone_set('Africa/Nairobi');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Checkout #<?= $sale_id ?> | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* (Same CSS as before – unchanged) */
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
            --primary-dark: #0f3a1e;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --info: #2563eb;
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
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: white; padding: 1rem 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); }
        .stat-card .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .stat-card .stat-label { font-size: 0.75rem; color: var(--gray-500); }
        .sale-header { background: var(--gray-50); padding: 1rem 1.25rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem; border-left: 4px solid var(--primary); }
        .sale-header .info { display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: center; }
        .sale-header .info .sale-id { font-weight: 700; color: var(--primary); font-size: 1.1rem; }
        .sale-header .info .label { color: var(--gray-500); font-size: 0.8rem; }
        .table-container { overflow-x: auto; margin: 1.25rem 0; background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th { background: var(--gray-50); padding: 0.875rem 0.75rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); }
        td { padding: 0.75rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        tr:hover { background: var(--gray-50); }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 500; background: var(--gray-100); }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fed7aa; color: #92400e; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); transform: translateY(-2px); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #047857; transform: translateY(-2px); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #b91c1c; transform: translateY(-2px); }
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: #b45309; transform: translateY(-2px); }
        .btn-info { background: var(--info); color: white; }
        .btn-info:hover { background: #1d4ed8; transform: translateY(-2px); }
        .btn-sm { padding: 0.25rem 0.6rem; font-size: 0.7rem; }
        .form-group { margin-bottom: 0.75rem; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.25rem; }
        .form-group select, .form-group input { padding: 0.5rem 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); width: 100%; max-width: 300px; }
        .payment-section { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 1.25rem; border-radius: var(--radius-lg); margin: 1.25rem 0; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; }
        .totals { background: var(--gray-50); padding: 1rem 1.25rem; border-radius: var(--radius-lg); margin: 1.25rem 0; }
        .total-row { display: flex; justify-content: space-between; padding: 0.25rem 0; }
        .grand-total { font-weight: 700; font-size: 1.1rem; border-top: 2px solid var(--primary); padding-top: 0.5rem; margin-top: 0.5rem; color: var(--primary); }
        .message { padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .message-success { background: #d1fae5; color: #065f46; }
        .message-error { background: #fee2e2; color: #991b1b; }
        footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1rem 1.25rem; }
            .stats-row { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
            .sale-header .info { flex-direction: column; gap: 0.5rem; align-items: flex-start; }
            .actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .form-group select, .form-group input { max-width: 100%; }
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-cash-register"></i> Checkout</h1>
        <div class="breadcrumb">
            <a href="<?= $user_role === 'cashier' ? '/inventory_system/dashboard/cashierdashboard.php' : '/inventory_system/dashboard/salesdashboard.php' ?>"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <a href="make_sale.php">Make a Sale</a>
            <span> / </span>
            <span>Sale ID:<?= $sale_id ?></span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="message message-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="message message-success" id="successMessage"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= count($items) ?></div>
            <div class="stat-label">Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">KES <?= number_format($grand_total, 2) ?></div>
            <div class="stat-label">Total Amount</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= htmlspecialchars($sale['salesperson_name'] ?? 'Unknown') ?></div>
            <div class="stat-label">Salesperson</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= date('M j, Y H:i', strtotime($sale['created_at'])) ?></div>
            <div class="stat-label">Started</div>
        </div>
    </div>

    <!-- Sale Header Info -->
    <div class="sale-header">
        <div class="info">
            <span><span class="label">Sale ID:</span> <span class="sale-id">#<?= $sale_id ?></span></span>
            <?php if ($user_role === 'cashier'): ?>
                <span><span class="label">Salesperson:</span> <?= htmlspecialchars($sale['salesperson_name'] ?? 'Unknown') ?></span>
            <?php endif; ?>
            <?php if ($sale['client_name']): ?>
                <span><span class="label">Client:</span> <?= htmlspecialchars($sale['client_name']) ?></span>
            <?php endif; ?>
            <?php if ($sale['client_phone']): ?>
                <span><span class="label">Phone:</span> <?= htmlspecialchars($sale['client_phone']) ?></span>
            <?php endif; ?>
            <span><span class="label">Status:</span> <span class="badge badge-success"><?= ucfirst($sale['sale_status']) ?></span></span>
            <?php if ($sale['completion_status']): ?>
                <span><span class="label">Completion:</span> <span class="badge badge-warning"><?= $sale['completion_status'] ?></span></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items Table -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--gray-400);">No items in this sale.</td></tr>
                <?php else: ?>
                    <?php $counter = 1; foreach ($items as $item): ?>
                        <tr>
                            <td><?= $counter++ ?></td>
                            <td><span class="badge"><?= ucfirst($item['item_type']) ?></span></td>
                            <td><?= htmlspecialchars($item['description'] ?? '') ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>KES <?= number_format($item['unit_price'], 2) ?></td>
                            <td>KES <?= number_format($item['total_price'], 2) ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this item? It will be returned to stock.');">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="item_type" value="<?= $item['item_type'] ?>">
                                    <input type="hidden" name="item_id_val" value="<?= htmlspecialchars($item['item_id']) ?>">
                                    <input type="hidden" name="quantity" value="<?= $item['quantity'] ?>">
                                    <button type="submit" name="remove_item" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Totals -->
    <div class="totals">
        <div class="total-row"><span>Subtotal</span><span>KES <?= number_format($subtotal, 2) ?></span></div>
        <div class="total-row grand-total"><span>TOTAL</span><span>KES <?= number_format($grand_total, 2) ?></span></div>
    </div>

    <!-- Payment Section (Cashier only) -->
    <?php if ($user_role === 'cashier'): ?>
        <div class="payment-section">
            <h4 style="margin-bottom:0.5rem;"><i class="fas fa-credit-card"></i> Payment Details</h4>
            <form method="POST" id="completeSaleForm">
                <div class="form-group">
                    <label for="payment_status">Payment Status</label>
                    <select name="payment_status" id="payment_status" onchange="togglePaymentMethod()">
                        <option value="paid" <?= ($sale['payment_status'] ?? 'paid') === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="unpaid" <?= ($sale['payment_status'] ?? '') === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    </select>
                </div>
                <div class="form-group" id="payment_method_group" style="<?= (($sale['payment_status'] ?? 'paid') === 'paid') ? '' : 'display:none;' ?>">
                    <label for="payment_method">Payment Method</label>
                    <select name="payment_method" id="payment_method">
                        <option value="">-- Select --</option>
                        <option value="cash" <?= ($sale['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="mpesa-till" <?= ($sale['payment_method'] ?? '') === 'mpesa-till' ? 'selected' : '' ?>>M-PESA Till</option>
                        <option value="mpesa-pochi" <?= ($sale['payment_method'] ?? '') === 'mpesa-pochi' ? 'selected' : '' ?>>M-PESA Pochi</option>
                        <option value="bank-transfer" <?= ($sale['payment_method'] ?? '') === 'bank-transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    </select>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="actions">
        <a href="make_sale.php" class="btn btn-info"><i class="fas fa-cart-plus"></i> Add More Items</a>

        <?php if ($user_role === 'cashier' && !empty($items)): ?>
            <button type="submit" form="completeSaleForm" name="complete_sale" class="btn btn-success" onclick="return confirm('Complete this sale? This action cannot be undone.');">
                <i class="fas fa-check-circle"></i> Complete Sale
            </button>
        <?php endif; ?>

        <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this entire sale? ALL items will be returned to stock.');">
            <button type="submit" name="cancel_sale" class="btn btn-danger"><i class="fas fa-times-circle"></i> Cancel Transaction</button>
        </form>
    </div>

    <footer>
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </footer>
</div>

<script>
    function togglePaymentMethod() {
        const status = document.getElementById('payment_status').value;
        const group = document.getElementById('payment_method_group');
        if (status === 'paid') {
            group.style.display = 'block';
        } else {
            group.style.display = 'none';
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        togglePaymentMethod();

        // Auto-redirect after 3 seconds if success message is shown and a redirect is needed
        <?php if ($redirect_after): ?>
            setTimeout(function() {
                window.location.href = "<?= $redirect_url ?>";
            }, 3000);
        <?php endif; ?>
    });
</script>

<?php require_once "../includes/footer.php"; ?>
</body>
</html>