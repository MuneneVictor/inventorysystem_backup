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
    // If sale is already completed or cancelled, redirect to make_sale
    if ($sale && $sale['sale_status'] === 'completed') {
        // We'll handle this below
    } else {
        header("Location: make_sale.php?error=invalid_sale");
        exit;
    }
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
// If the sale is already completed, show success and redirect
// -------------------------------------------------------------------
$error = '';
$success_message = '';
$redirect_after = false;
$redirect_url = '';

if ($sale['sale_status'] === 'completed') {
    $success_message = "Sale #$sale_id has been completed successfully.";
    $redirect_after = true;
    $redirect_url = "make_sale.php";
}

// -------------------------------------------------------------------
// Helper: Return item to stock (for cancellation / remove item)
// -------------------------------------------------------------------
function returnItemToStock($conn, $item_type, $item_id, $sale_item_id, $quantity) {
    switch ($item_type) {
        case 'device':
            $stmt = $conn->prepare("UPDATE devices SET status = 'In Stock', place = 'display', sold_at = NULL, sold_by = NULL, selling_price = NULL WHERE serial_number = ?");
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
            $stmt = $conn->prepare("DELETE FROM sold_rams_ssds WHERE ram_ssd_id = ? AND sale_item_id = ?");
            $stmt->execute([$item_id, $sale_item_id]);
            $stmt = $conn->prepare("UPDATE rams_ssds_logs SET status = 'pending_sale', sale_item_id = NULL WHERE sale_item_id = ?");
            $stmt->execute([$sale_item_id]);
            break;
        case 'charger':
            $stmt = $conn->prepare("DELETE FROM sold_chargers WHERE charger_id = ? AND sale_item_id = ?");
            $stmt->execute([$item_id, $sale_item_id]);
            $stmt = $conn->prepare("UPDATE charger_logs SET status = 'pending_sale', sale_item_id = NULL WHERE sale_item_id = ?");
            $stmt->execute([$sale_item_id]);
            break;
        case 'accessory':
            $stmt = $conn->prepare("SELECT id FROM accessories_logs WHERE sale_item_id = ?");
            $stmt->execute([$sale_item_id]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($log) {
                $stmt = $conn->prepare("DELETE FROM sold_accessories WHERE accessory_id = ? AND sale_item_id = ?");
                $stmt->execute([$item_id, $sale_item_id]);
                $stmt = $conn->prepare("UPDATE accessories_logs SET status = 'pending_sale', sale_item_id = NULL WHERE sale_item_id = ?");
                $stmt->execute([$sale_item_id]);
            } else {
                $stmt = $conn->prepare("UPDATE accessories SET quantity = quantity + ? WHERE id = ?");
                $stmt->execute([$quantity, $item_id]);
                $stmt = $conn->prepare("DELETE FROM sold_accessories WHERE accessory_id = ? AND sale_item_id = ?");
                $stmt->execute([$item_id, $sale_item_id]);
            }
            break;
        case 'hdd':
            $stmt = $conn->prepare("DELETE FROM sold_hdds WHERE hdd_id = ? AND sale_item_id = ?");
            $stmt->execute([$item_id, $sale_item_id]);
            $stmt = $conn->prepare("UPDATE hdd_logs SET status = 'pending_sale', sale_item_id = NULL WHERE sale_item_id = ?");
            $stmt->execute([$sale_item_id]);
            break;
        case 'graphic':
            $stmt = $conn->prepare("DELETE FROM sold_graphics_cards WHERE graphic_card_id = ? AND sale_item_id = ?");
            $stmt->execute([$item_id, $sale_item_id]);
            $stmt = $conn->prepare("UPDATE graphic_cards_logs SET status = 'pending_sale', sale_item_id = NULL WHERE sale_item_id = ?");
            $stmt->execute([$sale_item_id]);
            break;
        default:
            break;
    }
}

// -------------------------------------------------------------------
// Handle POST actions
// -------------------------------------------------------------------
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

            $_SESSION['flash_success'] = "Sale #$sale_id cancelled. All items returned to stock.";
            header("Location: make_sale.php");
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Failed to cancel sale: " . $e->getMessage();
        }
    }

    // ---- Complete sale (cashier only) with payment details ----
    if (isset($_POST['complete_sale']) && $user_role === 'cashier') {
        $payment_status = $_POST['payment_status'] ?? 'unpaid';
        $payment_method = $_POST['payment_method'] ?? null;
        if ($payment_status !== 'paid' || empty($payment_method)) {
            $payment_method = null; 
        }

        if ($payment_status === 'paid' && empty($payment_method)) {
            $error = "Please select a payment method for paid status.";
        } else {
            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare("UPDATE sales SET sale_status = 'completed', completion_status = 'Completed', completed_at = NOW(), payment_status = ?, payment_method = ? WHERE id = ?");
                $stmt->execute([$payment_status, $payment_method, $sale_id]);
                unset($_SESSION['current_sale_id']);
                $conn->commit();

                $_SESSION['flash_success'] = "Sale #$sale_id completed successfully.";
                header("Location: make_sale.php");
                exit;

            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Failed to complete sale: " . $e->getMessage();
            }
        }
    }
}

// Handle success/error messages from session (for remove_item)
if (isset($_SESSION['success']) && !$success_message) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

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
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; }
        .form-group { margin-bottom: 0.75rem; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.25rem; }
        .form-group select, .form-group input { padding: 0.5rem 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); width: 100%; max-width: 300px; }
        .payment-section { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 1.25rem; border-radius: var(--radius-lg); margin: 1.25rem 0; }
        .stk-section { background: #f0f9ff; border: 1px solid #bae6fd; padding: 1.25rem; border-radius: var(--radius-lg); margin: 1.25rem 0; display: none; }
        .stk-section.show { display: block; }
        .stk-row { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; }
        .stk-row .form-group { flex: 1; min-width: 200px; margin-bottom: 0; }
        .stk-row .form-group input { max-width: 100%; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; }
        .totals { background: var(--gray-50); padding: 1rem 1.25rem; border-radius: var(--radius-lg); margin: 1.25rem 0; }
        .total-row { display: flex; justify-content: space-between; padding: 0.25rem 0; }
        .grand-total { font-weight: 700; font-size: 1.1rem; border-top: 2px solid var(--primary); padding-top: 0.5rem; margin-top: 0.5rem; color: var(--primary); }
        .message { padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .message-success { background: #d1fae5; color: #065f46; }
        .message-error { background: #fee2e2; color: #991b1b; }
        .sales-note { background: #dbeafe; border: 1px solid #93c5fd; padding: 0.75rem 1.25rem; border-radius: var(--radius-md); margin: 1.25rem 0; color: #1e40af; text-align: center; font-size: 0.95rem; }
        .sales-note i { margin-right: 0.5rem; }
        footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }

        /* ---- STK Modal styles ---- */
        .stk-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 1rem;
        }
        .stk-modal-overlay.active { display: flex; }
        .stk-modal {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalIn 0.3s ease;
            position: relative;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        .stk-modal .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .stk-modal .spinner {
            display: inline-block;
            width: 4rem;
            height: 4rem;
            border: 4px solid #e5e7eb;
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .stk-modal h3 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--gray-800); }
        .stk-modal p { color: var(--gray-500); margin-bottom: 1.5rem; }
        .stk-modal .detail-row { display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid var(--gray-100); font-size: 0.9rem; }
        .stk-modal .detail-row .label { color: var(--gray-500); }
        .stk-modal .detail-row .value { font-weight: 500; color: var(--gray-700); }
        .stk-modal .modal-actions { display: flex; gap: 0.75rem; justify-content: center; margin-top: 1rem; flex-wrap: wrap; }
        .stk-modal .modal-actions .btn { min-width: 120px; justify-content: center; }

        /* Cancel button style */
        .btn-cancel-stk { background: var(--gray-200); color: var(--gray-700); }
        .btn-cancel-stk:hover { background: var(--gray-300); transform: translateY(-2px); }
        .btn-danger-stk { background: var(--danger); color: white; }
        .btn-danger-stk:hover { background: #b91c1c; transform: translateY(-2px); }

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
            .stk-row { flex-direction: column; align-items: stretch; }
            .stk-row .form-group { margin-bottom: 0.75rem; }
            .stk-modal .modal-actions { flex-direction: column; }
            .stk-modal .modal-actions .btn { width: 100%; }
        }
    </style>
</head>
<body>
     <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-cash-register"></i> Checkout</h1>
        <div class="breadcrumb">
            <a href="<?= $user_role === 'cashier' ? '../dashboard/cashierdashboard.php' : '../dashboard/salesdashboard.php' ?>"><i class="fas fa-home"></i> Dashboard</a>
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
                    <select name="payment_status" id="payment_status" onchange="togglePaymentMethod(); toggleStkSection();">
                        <option value="paid" <?= ($sale['payment_status'] ?? 'paid') === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="unpaid" <?= ($sale['payment_status'] ?? '') === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    </select>
                </div>
                <div class="form-group" id="payment_method_group" style="<?= (($sale['payment_status'] ?? 'paid') === 'paid') ? '' : 'display:none;' ?>">
                    <label for="payment_method">Payment Method</label>
                    <select name="payment_method" id="payment_method" onchange="toggleStkSection();">
                        <option value="">-- Select --</option>
                        <option value="cash" <?= ($sale['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="mpesa-till" <?= ($sale['payment_method'] ?? '') === 'mpesa-till' ? 'selected' : '' ?>>M-PESA Till</option>
                        <option value="mpesa-pochi" <?= ($sale['payment_method'] ?? '') === 'mpesa-pochi' ? 'selected' : '' ?>>M-PESA Pochi</option>
                        <option value="bank-transfer" <?= ($sale['payment_method'] ?? '') === 'bank-transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- STK Push Section (only for M-PESA Till) -->
        <div class="stk-section" id="stkSection">
            <h4 style="margin-bottom:0.75rem;"><i class="fas fa-mobile-screen"></i> M-PESA STK Push</h4>
            <p style="color: var(--gray-500); font-size: 0.9rem; margin-bottom: 1rem;">
                Send a payment prompt to the customer's phone. Total amount: <strong>KES <?= number_format($grand_total, 2) ?></strong>
            </p>
            <div class="stk-row">
                <div class="form-group">
                    <label for="stk_phone">Customer Phone Number</label>
                    <input type="tel" id="stk_phone" placeholder="0712345678" maxlength="10" value="">
                    <small style="color: var(--gray-400); font-size: 0.7rem;">Format: 0712345678 (without +254)</small>
                </div>
                <div class="form-group" style="flex: 0 0 auto;">
                    <button type="button" class="btn btn-primary" id="sendStkBtn" onclick="sendStkPush();">
                        <i class="fas fa-paper-plane"></i> Send M-Pesa Prompt
                    </button>
                </div>
            </div>
            <div id="stkMessage" style="margin-top: 0.75rem; font-size: 0.9rem;"></div>
        </div>
    <?php endif; ?>

    <!-- Friendly note for sales role -->
    <?php if ($user_role === 'sales'): ?>
        <div class="sales-note">
            <i class="fas fa-info-circle"></i>
            <strong>Please contact the cashier to complete your sale.</strong> They will process the payment and finalise the transaction.
        </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="actions">
        <a href="make_sale.php" class="btn btn-info"><i class="fas fa-cart-plus"></i> Add More Items</a>

        <?php if ($user_role === 'cashier' && !empty($items) && $sale['sale_status'] !== 'completed'): ?>
            <button type="submit" form="completeSaleForm" name="complete_sale" class="btn btn-success" onclick="return confirm('Complete this sale? This action cannot be undone.');">
                <i class="fas fa-check-circle"></i> Complete Sale
            </button>
        <?php endif; ?>

        <?php if ($sale['sale_status'] !== 'completed'): ?>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this entire sale? ALL items will be returned to stock.');">
            <button type="submit" name="cancel_sale" class="btn btn-danger"><i class="fas fa-times-circle"></i> Cancel Transaction</button>
        </form>
        <?php endif; ?>
    </div>

    <footer>
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </footer>
</div>

<!-- STK Modal -->
<div class="stk-modal-overlay" id="stkModal">
    <div class="stk-modal">
        <div id="stkModalIcon" class="icon"><div class="spinner"></div></div>
        <h3 id="stkModalTitle">Sending M-Pesa Prompt...</h3>
        <p id="stkModalMessage">Please wait while we initiate the payment request.</p>
        <div id="stkModalDetails" style="display:none; text-align:left; margin-top:1rem; border-top:1px solid var(--gray-200); padding-top:1rem;">
            <div class="detail-row"><span class="label">Reference</span><span class="value" id="stkRef">-</span></div>
            <div class="detail-row"><span class="label">Checkout ID</span><span class="value" id="stkCheckoutId">-</span></div>
            <div class="detail-row"><span class="label">Status</span><span class="value" id="stkStatus">Pending</span></div>
        </div>
        <div class="modal-actions" id="stkModalActions">
            <button class="btn btn-primary" id="stkModalBtn" style="display:none;" onclick="closeStkModal();">Continue</button>
        </div>
    </div>
</div>

<script>
    // ---------- Toggle payment method and STK section ----------
    function togglePaymentMethod() {
        const status = document.getElementById('payment_status').value;
        const group = document.getElementById('payment_method_group');
        if (status === 'paid') {
            group.style.display = 'block';
        } else {
            group.style.display = 'none';
        }
        toggleStkSection();
    }

    function toggleStkSection() {
        const method = document.getElementById('payment_method').value;
        const stkSection = document.getElementById('stkSection');
        if (document.getElementById('payment_status').value === 'paid' && method === 'mpesa-till') {
            stkSection.classList.add('show');
        } else {
            stkSection.classList.remove('show');
        }
    }

    // ---------- STK Push functions ----------
    let stkPollingInterval = null;
    let stkPollAttempts = 0;
    let isStkCancelled = false;
    const MAX_POLL_ATTEMPTS = 90; // 3 minutes

    function sendStkPush() {
        const phoneInput = document.getElementById('stk_phone');
        const phone = phoneInput.value.trim();
        if (!phone) {
            showStkMessage('Please enter a phone number.', 'error');
            return;
        }
        const digits = phone.replace(/\D/g, '');
        if (digits.length < 9 || digits.length > 10) {
            showStkMessage('Phone number must be 9-10 digits.', 'error');
            return;
        }
        if (!digits.startsWith('07') && !digits.startsWith('01')) {
            showStkMessage('Phone number must start with 07 or 01.', 'error');
            return;
        }

        isStkCancelled = false;
        const btn = document.getElementById('sendStkBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        showStkModal('loading', 'Sending M-Pesa Prompt...', 'Please wait while we initiate the payment request.');

        const data = {
            sale_id: <?= $sale_id ?>,
            phone: digits,
            amount: <?= $grand_total ?>
        };

        fetch('process_mpesa_stk.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                updateStkModal('waiting', 'STK Push Sent!', 'We have sent a payment request to the customer\'s phone. Please ask them to enter their M-Pesa PIN.', result.data);
                startPolling();
            } else {
                updateStkModal('error', 'Unable to Send', result.error || 'An error occurred.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send M-Pesa Prompt';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            updateStkModal('error', 'Network Error', 'Could not connect to server. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send M-Pesa Prompt';
        });
    }

    function showStkMessage(msg, type) {
        const div = document.getElementById('stkMessage');
        div.innerHTML = msg;
        div.style.color = type === 'error' ? 'var(--danger)' : 'var(--success)';
    }

    function showStkModal(type, title, message, data) {
        const modal = document.getElementById('stkModal');
        const icon = document.getElementById('stkModalIcon');
        const titleEl = document.getElementById('stkModalTitle');
        const msgEl = document.getElementById('stkModalMessage');
        const details = document.getElementById('stkModalDetails');
        const actions = document.getElementById('stkModalActions');

        modal.classList.add('active');
        actions.innerHTML = '';

        if (type === 'loading') {
            icon.innerHTML = '<div class="spinner"></div>';
            titleEl.textContent = title;
            msgEl.textContent = message;
            details.style.display = 'none';
            actions.innerHTML = `
                <button class="btn btn-cancel-stk" onclick="cancelStkPush();">
                    <i class="fas fa-times"></i> Cancel
                </button>
            `;
        } else if (type === 'waiting') {
            icon.innerHTML = '<div class="spinner"></div>';
            titleEl.textContent = title;
            msgEl.textContent = message;
            if (data) {
                document.getElementById('stkRef').textContent = data.reference || 'N/A';
                document.getElementById('stkCheckoutId').textContent = data.checkout_request_id || 'N/A';
                document.getElementById('stkStatus').textContent = 'Waiting for PIN entry...';
                details.style.display = 'block';
            } else {
                details.style.display = 'none';
            }
            actions.innerHTML = `
                <button class="btn btn-cancel-stk" onclick="cancelStkPush();">
                    <i class="fas fa-times"></i> Cancel Payment
                </button>
            `;
        } else if (type === 'success') {
            icon.innerHTML = '<i class="fas fa-check-circle" style="color: var(--success);"></i>';
            titleEl.textContent = title;
            msgEl.textContent = message;
            if (data) {
                document.getElementById('stkRef').textContent = data.reference || 'N/A';
                document.getElementById('stkCheckoutId').textContent = data.checkout_request_id || 'N/A';
                document.getElementById('stkStatus').textContent = 'Completed ✓';
                details.style.display = 'block';
            } else {
                details.style.display = 'none';
            }
            actions.innerHTML = `
                <button class="btn btn-primary" onclick="closeStkModal(); location.reload();">
                    <i class="fas fa-check"></i> Continue
                </button>
            `;
        } else if (type === 'error') {
            icon.innerHTML = '<i class="fas fa-times-circle" style="color: var(--danger);"></i>';
            titleEl.textContent = title;
            msgEl.textContent = message;
            details.style.display = 'none';
            actions.innerHTML = `
                <button class="btn btn-primary" onclick="closeStkModal();">
                    <i class="fas fa-times"></i> Close
                </button>
                <button class="btn btn-primary" onclick="closeStkModal(); document.getElementById('sendStkBtn').disabled = false; document.getElementById('sendStkBtn').innerHTML = '<i class=\'fas fa-paper-plane\'></i> Send M-Pesa Prompt';">
                    <i class="fas fa-redo"></i> Try Again
                </button>
            `;
        } else if (type === 'cancelled') {
            icon.innerHTML = '<i class="fas fa-times-circle" style="color: var(--warning);"></i>';
            titleEl.textContent = 'Payment Cancelled';
            msgEl.textContent = message || 'The payment request was cancelled. You can try again or choose another payment method.';
            details.style.display = 'none';
            actions.innerHTML = `
                <button class="btn btn-primary" onclick="closeStkModal();">
                    <i class="fas fa-check"></i> OK
                </button>
                <button class="btn btn-primary" onclick="closeStkModal(); document.getElementById('sendStkBtn').disabled = false; document.getElementById('sendStkBtn').innerHTML = '<i class=\'fas fa-paper-plane\'></i> Send M-Pesa Prompt';">
                    <i class="fas fa-redo"></i> Try Again
                </button>
            `;
        }
    }

    function updateStkModal(type, title, message, data) {
        const icon = document.getElementById('stkModalIcon');
        const titleEl = document.getElementById('stkModalTitle');
        const msgEl = document.getElementById('stkModalMessage');
        const details = document.getElementById('stkModalDetails');
        const actions = document.getElementById('stkModalActions');

        if (type === 'waiting') {
            icon.innerHTML = '<div class="spinner"></div>';
            titleEl.textContent = title;
            msgEl.textContent = message;
            if (data) {
                document.getElementById('stkRef').textContent = data.reference || 'N/A';
                document.getElementById('stkCheckoutId').textContent = data.checkout_request_id || 'N/A';
                document.getElementById('stkStatus').textContent = 'Waiting for PIN entry...';
                details.style.display = 'block';
            }
            if (!document.querySelector('.btn-cancel-stk')) {
                actions.innerHTML = `
                    <button class="btn btn-cancel-stk" onclick="cancelStkPush();">
                        <i class="fas fa-times"></i> Cancel Payment
                    </button>
                `;
            }
        } else if (type === 'success') {
            icon.innerHTML = '<i class="fas fa-check-circle" style="color: var(--success);"></i>';
            titleEl.textContent = title;
            msgEl.textContent = message;
            if (data) {
                document.getElementById('stkRef').textContent = data.reference || 'N/A';
                document.getElementById('stkCheckoutId').textContent = data.checkout_request_id || 'N/A';
                document.getElementById('stkStatus').textContent = 'Completed ✓';
                details.style.display = 'block';
            }
            actions.innerHTML = `
                <button class="btn btn-primary" onclick="closeStkModal(); location.reload();">
                    <i class="fas fa-check"></i> Continue
                </button>
            `;
        } else if (type === 'cancelled') {
            icon.innerHTML = '<i class="fas fa-times-circle" style="color: var(--warning);"></i>';
            titleEl.textContent = 'Payment Cancelled by Customer';
            msgEl.textContent = message || 'The customer cancelled the payment on their phone.';
            details.style.display = 'none';
            actions.innerHTML = `
                <button class="btn btn-primary" onclick="closeStkModal();">
                    <i class="fas fa-check"></i> OK
                </button>
                <button class="btn btn-primary" onclick="closeStkModal(); document.getElementById('sendStkBtn').disabled = false; document.getElementById('sendStkBtn').innerHTML = '<i class=\'fas fa-paper-plane\'></i> Send M-Pesa Prompt';">
                    <i class="fas fa-redo"></i> Try Again
                </button>
            `;
        } else if (type === 'error') {
            icon.innerHTML = '<i class="fas fa-times-circle" style="color: var(--danger);"></i>';
            titleEl.textContent = title;
            msgEl.textContent = message;
            details.style.display = 'none';
            actions.innerHTML = `
                <button class="btn btn-primary" onclick="closeStkModal();">
                    <i class="fas fa-times"></i> Close
                </button>
                <button class="btn btn-primary" onclick="closeStkModal(); document.getElementById('sendStkBtn').disabled = false; document.getElementById('sendStkBtn').innerHTML = '<i class=\'fas fa-paper-plane\'></i> Send M-Pesa Prompt';">
                    <i class="fas fa-redo"></i> Try Again
                </button>
            `;
        }
    }

    function cancelStkPush() {
        if (confirm('Are you sure you want to cancel this payment request?')) {
            isStkCancelled = true;
            stopPolling();
            updateStkModal('cancelled', 'Payment Cancelled', 'You have cancelled the payment request. You can try again or use another payment method.');
            document.getElementById('sendStkBtn').disabled = false;
            document.getElementById('sendStkBtn').innerHTML = '<i class="fas fa-paper-plane"></i> Send M-Pesa Prompt';
        }
    }

    function closeStkModal() {
        document.getElementById('stkModal').classList.remove('active');
        stopPolling();
        const btn = document.getElementById('sendStkBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send M-Pesa Prompt';
        isStkCancelled = false;
    }

    // ---------- Polling ----------
    function startPolling() {
        stkPollAttempts = 0;
        isStkCancelled = false;
        if (stkPollingInterval) clearInterval(stkPollingInterval);
        stkPollingInterval = setInterval(checkStatus, 2000);
        updateStkModal('waiting', 'STK Push Sent!', 'Please ask the customer to enter their M-Pesa PIN on their phone.');
    }

    function stopPolling() {
        if (stkPollingInterval) {
            clearInterval(stkPollingInterval);
            stkPollingInterval = null;
        }
        stkPollAttempts = 0;
    }

    function checkStatus() {
        if (isStkCancelled) {
            stopPolling();
            return;
        }

        stkPollAttempts++;
        const saleId = <?= $sale_id ?>;
        fetch('check_mpesa_status.php?sale_id=' + saleId + '&t=' + Date.now())
            .then(res => res.json())
            .then(data => {
                if (isStkCancelled) return;

                if (data.status === 'success') {
                    stopPolling();
                    updateStkModal('success', 'Payment Successful!', 'The customer has successfully completed the M-Pesa payment.', { 
                        reference: data.receipt || 'N/A',
                        completion_status: data.completion_status || 'Completed'
                    });
                } else if (data.status === 'cancelled') {
                    stopPolling();
                    updateStkModal('cancelled', 'Payment Cancelled', 'The customer cancelled the payment on their phone. They can try again.');
                } else if (data.status === 'failed') {
                    stopPolling();
                    updateStkModal('error', 'Payment Failed', 'The payment failed. Please ask the customer to try again.');
                } else if (data.status === 'pending') {
                    if (stkPollAttempts % 5 === 0) {
                        const seconds = stkPollAttempts * 2;
                        document.getElementById('stkModalMessage').textContent = 'Waiting for PIN entry... (' + seconds + 's)';
                    }
                } else if (data.status === 'error') {
                    stopPolling();
                    updateStkModal('error', 'Error', data.message || 'Could not verify payment status.');
                }
                
                if (stkPollAttempts >= MAX_POLL_ATTEMPTS && !isStkCancelled) {
                    stopPolling();
                    updateStkModal('error', 'Timeout', 'Payment confirmation timed out. Please check if the customer completed the transaction.');
                }
            })
            .catch(error => {
                console.error('Polling error:', error);
            });
    }

    // ---------- Initialize ----------
    document.addEventListener('DOMContentLoaded', function() {
        togglePaymentMethod();
        <?php if ($sale['payment_status'] === 'paid'): ?>
            document.getElementById('stkSection').classList.remove('show');
        <?php endif; ?>
    });

</script>

<?php require_once "../includes/footer.php"; ?>
</body>
</html>