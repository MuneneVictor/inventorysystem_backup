<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

if (!in_array($user_role, ['sales', 'super_admin', 'manager', 'technician'])) {
    die("ACCESS DENIED.");
}

// Get invoice_id from URL or session
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : ($_SESSION['invoice_id'] ?? 0);

if (!$invoice_id) {
    header("Location: write_invoice.php");
    exit;
}

// Get invoice details - check if user has permission
$stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ? AND user_id = ?");
$stmt->execute([$invoice_id, $user_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    // Check if it's a different user's invoice (for admin/super_admin/manager)
    if (in_array($user_role, ['super_admin', 'manager'])) {
        $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$invoice) {
        // Try to get from URL with fallback
        if (isset($_GET['id'])) {
            $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
            $stmt->execute([$invoice_id]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$invoice) {
            unset($_SESSION['invoice_id']);
            header("Location: write_invoice.php?error=not_found");
            exit;
        }
    }
}

// Check if user can view this invoice
// Super admin and manager can view all, others can only view their own
if (!in_array($user_role, ['super_admin', 'manager'])) {
    if ($invoice['user_id'] != $user_id) {
        die("ACCESS DENIED. You do not have permission to view this invoice.");
    }
}

// Store in session for easy access
$_SESSION['invoice_id'] = $invoice_id;

// Get items
$stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    // Redirect to add items if no items found
    header("Location: add_invoice_items.php?invoice_id=" . $invoice_id);
    exit;
}

// Handle send/publish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_invoice'])) {
    if ($invoice['status'] === 'draft') {
        $stmt = $conn->prepare("UPDATE invoices SET status = 'sent' WHERE id = ?");
        $stmt->execute([$invoice_id]);
        $sent = true;
        // Refresh invoice data
        $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Handle cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    if ($invoice['status'] === 'draft') {
        $stmt = $conn->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$invoice_id]);
        
        // Clear session
        unset($_SESSION['invoice_id']);
        
        // Redirect to write_invoice.php with success message
        $_SESSION['invoice_cancelled'] = "Invoice #" . $invoice['invoice_number'] . " has been cancelled.";
        header("Location: write_invoice.php");
        exit;
    }
}

// Handle record payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? '';
    $reference = trim($_POST['reference'] ?? '');
    $payment_notes = trim($_POST['payment_notes'] ?? '');
    
    if ($amount <= 0) {
        $payment_error = "Please enter a valid amount";
    } elseif (empty($payment_method)) {
        $payment_error = "Please select a payment method";
    } else {
        try {
            // Record payment
            $stmt = $conn->prepare("INSERT INTO invoice_payments (invoice_id, amount, payment_method, reference_number, notes, payment_date, created_by) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([$invoice_id, $amount, $payment_method, $reference, $payment_notes, $user_id]);
            
            // Update invoice
            $stmt = $conn->prepare("SELECT SUM(amount) as total_paid FROM invoice_payments WHERE invoice_id = ?");
            $stmt->execute([$invoice_id]);
            $paid = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_paid = $paid['total_paid'] ?? 0;
            
            $balance = $invoice['grand_total'] - $total_paid;
            
            $payment_status = 'unpaid';
            if ($balance <= 0) {
                $payment_status = 'paid';
            } elseif ($total_paid > 0) {
                $payment_status = 'partial';
            }
            
            $stmt = $conn->prepare("UPDATE invoices SET amount_paid = ?, balance_due = ?, payment_status = ? WHERE id = ?");
            $stmt->execute([$total_paid, $balance, $payment_status, $invoice_id]);
            
            // Refresh invoice data
            $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
            $stmt->execute([$invoice_id]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $payment_success = "Payment recorded successfully!";
        } catch (Exception $e) {
            $payment_error = "Database error: " . $e->getMessage();
        }
    }
}

// Get payment history
$stmt = $conn->prepare("SELECT * FROM invoice_payments WHERE invoice_id = ? ORDER BY payment_date DESC");
$stmt->execute([$invoice_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Invoice - <?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; color: #1f2937; line-height: 1.5; }
        .main-content { padding: 2rem; margin-left: 260px; min-height: 100vh; background: #f3f4f6; }
        .page-header { background: white; padding: 1.5rem 2rem; border-radius: 0.75rem; margin-bottom: 1.5rem; border: 1px solid #e5e7eb; }
        .page-header h1 { font-size: 1.75rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; }
        .page-header h1 i { color: #1a4b2a; }
        .breadcrumb { color: #6b7280; font-size: 0.9rem; }
        .breadcrumb a { color: #1a4b2a; text-decoration: none; }
        .step-indicator { display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: center; flex-wrap: wrap; }
        .step { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; background: #e5e7eb; color: #6b7280; }
        .step.active { background: #1a4b2a; color: white; }
        .step.completed { background: #16a34a; color: white; }
        .step.cancelled { background: #dc2626; color: white; }
        .step .number { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; background: rgba(255,255,255,0.2); }
        .quotation-box { background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; padding: 2rem; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .header .logo img { max-height: 70px; }
        .header .company { text-align: right; }
        .header .company h2 { font-size: 1.8rem; font-weight: 700; color: #1a4b2a; letter-spacing: 2px; }
        .header .company p { font-size: 0.9rem; line-height: 1.5; color: #4b5563; }
        .details { margin: 1.5rem 0; }
        .details table { width: 100%; border-collapse: collapse; }
        .details td { padding: 4px 0; vertical-align: top; }
        .details .label { font-weight: 600; color: #4b5563; min-width: 120px; }
        .cancelled-badge { 
            display: inline-block; 
            padding: 0.25rem 0.75rem; 
            border-radius: 9999px; 
            background: #dc2626; 
            color: white; 
            font-size: 0.75rem; 
            font-weight: 600; 
        }
        .items-table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; }
        .items-table th { 
            background: #f3f4f6; 
            padding: 10px 8px; 
            text-align: left; 
            border-bottom: 2px solid #d1d5db; 
            font-weight: 600; 
            font-size: 0.85rem; 
        }
        .items-table td { 
            padding: 10px 8px; 
            border-bottom: 1px solid #e5e7eb; 
            font-size: 0.9rem; 
            vertical-align: middle; 
        }
        .items-table .text-right { 
            text-align: right; 
        }
        .items-table .col-items { 
            width: 45%; 
        }
        .items-table .col-qty { 
            width: 12%; 
            text-align: center;
        }
        .items-table .col-price { 
            width: 18%; 
            text-align: right; 
        }
        .items-table .col-amount { 
            width: 25%; 
            text-align: right; 
        }
        .specs { font-size: 0.8rem; color: #6b7280; }
        .totals { text-align: right; margin-top: 1.5rem; padding-top: 0.5rem; border-top: 2px solid #e5e7eb; }
        .totals p { margin: 4px 0; }
        .totals .grand { font-size: 1.2rem; font-weight: 700; color: #1a4b2a; }
        .payment-summary { background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0; }
        .payment-summary .paid { color: #16a34a; font-weight: 600; }
        .payment-summary .balance { color: #dc2626; font-weight: 600; }
        .payment-status { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .payment-status.paid { background: #dcfce7; color: #16a34a; }
        .payment-status.partial { background: #fed7aa; color: #c2410c; }
        .payment-status.unpaid { background: #fee2e2; color: #dc2626; }
        .notes { margin-top: 1.5rem; padding: 1rem; background: #f9fafb; border-left: 3px solid #1a4b2a; font-size: 0.9rem; color: #4b5563; }
        .footer-note { margin-top: 2rem; text-align: center; font-size: 0.85rem; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 1rem; }
        .btn { padding: 0.6rem 1.2rem; background: #1a4b2a; color: white; border: none; border-radius: 0.5rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; font-size: 0.9rem; text-decoration: none; transition: all 0.2s; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-success { background: #16a34a; }
        .btn-secondary { background: #6b7280; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-sm { padding: 0.25rem 0.75rem; font-size: 0.8rem; }
        .actions { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem; }
        .notification { padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .notification.success { background: #dcfce7; border: 1px solid #16a34a; color: #14532d; }
        .notification.error { background: #fee2e2; border: 1px solid #dc2626; color: #991b1b; }
        .notification.info { background: #dbeafe; border: 1px solid #2563eb; color: #1e3a8a; }
        .payment-form { background: #f9fafb; padding: 1.5rem; border-radius: 0.75rem; margin-top: 1.5rem; border: 1px solid #e5e7eb; }
        .payment-form .form-row { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .payment-form .form-group { flex: 1; min-width: 150px; }
        .payment-form .form-group label { display: block; font-size: 0.8rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem; }
        .payment-form .form-group input, .payment-form .form-group select { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9rem; }
        .payment-form .form-group input:focus, .payment-form .form-group select:focus { outline: none; border-color: #1a4b2a; box-shadow: 0 0 0 3px rgba(26,75,42,0.1); }
        .published-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; background: #16a34a; color: white; font-size: 0.75rem; font-weight: 600; }
        .back-link { display: inline-flex; align-items: center; gap: 0.5rem; color: #1a4b2a; text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: #9ca3af; border-top: 1px solid #e5e7eb; }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; padding: 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { 
            .header { flex-direction: column; align-items: flex-start; } 
            .header .company { text-align: left; width: 100%; } 
            .details td { display: block; width: 100%; text-align: left !important; } 
            .btn { width: 100%; justify-content: center; }
            .payment-form .form-row { flex-direction: column; }
            .items-table .col-items { width: 30%; }
            .items-table .col-qty { width: 15%; }
            .items-table .col-price { width: 20%; }
            .items-table .col-amount { width: 35%; }
            .actions { flex-direction: column; }
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice"></i> Review Invoice</h1>
        <div class="breadcrumb">
            <?php if($user_role === 'sales'): ?>
                <a href="../dashboard/salesdashboard.php">Dashboard</a> /
            <?php endif; ?>
            <?php if($user_role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php">Dashboard</a> /
            <?php endif; ?>
            <?php if($user_role === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php">Dashboard</a> /
            <?php endif; ?>
            <?php if($user_role === 'cashier'): ?>
                <a href="../dashboard/cashierdashboard.php">Dashboard</a> /
            <?php endif; ?>
            <a href="invoices_list.php">Invoices</a> /
            <span><?= htmlspecialchars($invoice['invoice_number']) ?></span>
        </div>
    </div>

    <!-- Step Indicator -->
    <div class="step-indicator">
        <div class="step completed"><span class="number">✓</span> Client Details</div>
        <div class="step completed"><span class="number">✓</span> Add Items</div>
        <?php if ($invoice['status'] === 'cancelled'): ?>
            <div class="step cancelled"><span class="number">✗</span> Cancelled</div>
        <?php else: ?>
            <div class="step active"><span class="number">3</span> Review & Export</div>
        <?php endif; ?>
    </div>

    <?php if (isset($sent)): ?>
        <div class="notification success">
            <i class="fas fa-check-circle"></i> 
            <strong>Success!</strong> Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?> has been sent successfully!
        </div>
    <?php endif; ?>

    <?php if (isset($payment_success)): ?>
        <div class="notification success">
            <i class="fas fa-check-circle"></i> 
            <strong>Success!</strong> <?= htmlspecialchars($payment_success) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($payment_error)): ?>
        <div class="notification error">
            <i class="fas fa-exclamation-circle"></i> 
            <?= htmlspecialchars($payment_error) ?>
        </div>
    <?php endif; ?>

    <?php if ($invoice['status'] === 'sent' || $invoice['status'] === 'paid'): ?>
        <div class="notification info">
            <i class="fas fa-info-circle"></i> 
            This invoice has been <?= $invoice['status'] === 'paid' ? 'paid' : 'sent' ?>. 
            <span class="published-badge"><?= ucfirst($invoice['status']) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($invoice['status'] === 'cancelled'): ?>
        <div class="notification error">
            <i class="fas fa-times-circle"></i> 
            This invoice has been <strong>cancelled</strong>. <span class="cancelled-badge">Cancelled</span>
        </div>
    <?php endif; ?>

    <!-- Invoice Display -->
    <div class="quotation-box">
        <div class="header">
            <div class="logo"><img src="../assets/MC-LOGO.png" alt="Mombasa Computers"></div>
            <div class="company">
                <h2>INVOICE</h2>
                <p><strong>Mombasa Computers</strong><br>Moi Avenue Opp Credible Sounds<br>P.O Box 37940 Nairobi, Nairobi Area 00100 Kenya</p>
                <p>Phone: 0792792750<br>Mobile: 0111040400<br>www.mombasacomputers.com</p>
            </div>
        </div>
        <div class="details">
            <table>
                <tr>
                    <td>
                        <span class="label">BILL TO</span>
                        <div style="margin-top:5px;">
                            <strong><?= htmlspecialchars($invoice['client_name']) ?></strong><br>
                            <?= !empty($invoice['client_box']) ? htmlspecialchars($invoice['client_box']) . '<br>' : '' ?>
                            <?= !empty($invoice['client_phone']) ? 'Phone: ' . htmlspecialchars($invoice['client_phone']) . '<br>' : '' ?>
                            <?= !empty($invoice['client_email']) ? 'Email: ' . htmlspecialchars($invoice['client_email']) : '' ?>
                        </div>
                    </td>
                    <td style="text-align:right;">
                        <span class="label">Invoice Number:</span> <?= htmlspecialchars($invoice['invoice_number']) ?><br>
                        <span class="label">Invoice Date:</span> <?= htmlspecialchars($invoice['invoice_date']) ?><br>
                        <span class="label">Payment Due:</span> <?= htmlspecialchars($invoice['payment_due_date']) ?><br>
                        <span class="label">Status:</span> 
                        <span class="payment-status <?= $invoice['payment_status'] ?>">
                            <?= ucfirst($invoice['payment_status']) ?>
                        </span>
                        <?php if ($invoice['status'] === 'cancelled'): ?>
                            <br><span class="cancelled-badge" style="margin-top:3px; display:inline-block;">CANCELLED</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-items">Items</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-price">Price</th>
                    <th class="col-amount">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td class="col-items">
                        <strong><?= htmlspecialchars($item['description']) ?></strong>
                        <?= !empty($item['specs']) ? '<br><span class="specs">'.htmlspecialchars($item['specs']).'</span>' : '' ?>
                    </td>
                    <td class="col-qty"><?= $item['quantity'] ?></td>
                    <td class="col-price">KES <?= number_format($item['unit_price'], 2) ?></td>
                    <td class="col-amount">KES <?= number_format($item['total_price'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="totals">
            <p><strong>Subtotal:</strong> <?= number_format($invoice['subtotal'], 2) ?></p>
            <?php if ($invoice['vat'] > 0): ?><p><strong>v.a.t 16%:</strong> <?= number_format($invoice['vat'], 2) ?></p><?php endif; ?>
            <p class="grand"><strong>Amount Due (KES):</strong> <?= number_format($invoice['grand_total'], 2) ?></p>
        </div>

        <?php if ($invoice['amount_paid'] > 0): ?>
        <div class="payment-summary">
            <p><strong>Payment Summary</strong></p>
            <p>Amount Paid: <span class="paid">KES <?= number_format($invoice['amount_paid'], 2) ?></span></p>
            <p>Balance Due: <span class="balance">KES <?= number_format($invoice['balance_due'], 2) ?></span></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($invoice['notes'])): ?>
            <div class="notes"><strong>Notes:</strong> <?= nl2br(htmlspecialchars($invoice['notes'])) ?></div>
        <?php endif; ?>
        <div class="footer-note">Thank you for your business!</div>
    </div>

    <!-- Payment Form -->
    <?php if ($invoice['status'] !== 'paid' && $invoice['status'] !== 'cancelled' && $invoice['balance_due'] > 0): ?>
    <div class="payment-form">
        <h4 style="margin-bottom:0.5rem;"><i class="fas fa-credit-card"></i> Record Payment</h4>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Amount (KES)</label>
                    <input type="number" name="amount" step="0.01" min="0.01" max="<?= $invoice['balance_due'] ?>" value="<?= $invoice['balance_due'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" required>
                        <option value="">Select Method</option>
                        <option value="cash">Cash</option>
                        <option value="mpesa-till">M-PESA Till</option>
                        <option value="mpesa-pochi">M-PESA Pochi</option>
                        <option value="bank-transfer">Bank Transfer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reference Number</label>
                    <input type="text" name="reference" placeholder="Transaction ref...">
                </div>
                <div class="form-group" style="flex:0 0 auto; align-self:flex-end;">
                    <button type="submit" name="record_payment" class="btn btn-success">
                        <i class="fas fa-check"></i> Record Payment
                    </button>
                </div>
            </div>
            <div class="form-group" style="margin-top:0.5rem;">
                <label>Payment Notes</label>
                <input type="text" name="payment_notes" placeholder="Optional notes...">
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Payment History -->
    <?php if (!empty($payments)): ?>
    <div style="background:white; border-radius:0.75rem; border:1px solid #e5e7eb; padding:1.5rem; margin-top:1.5rem;">
        <h4 style="margin-bottom:1rem;"><i class="fas fa-history"></i> Payment History</h4>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                <thead>
                    <tr style="background:#f9fafb; border-bottom:2px solid #e5e7eb;">
                        <th style="padding:0.5rem; text-align:left;">Date</th>
                        <th style="padding:0.5rem; text-align:right;">Amount</th>
                        <th style="padding:0.5rem; text-align:left;">Method</th>
                        <th style="padding:0.5rem; text-align:left;">Reference</th>
                        <th style="padding:0.5rem; text-align:left;">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:0.5rem;"><?= date('M d, Y H:i', strtotime($payment['payment_date'])) ?></td>
                        <td style="padding:0.5rem; text-align:right;">KES <?= number_format($payment['amount'], 2) ?></td>
                        <td style="padding:0.5rem;"><?= ucfirst(str_replace('-', ' ', $payment['payment_method'])) ?></td>
                        <td style="padding:0.5rem;"><?= htmlspecialchars($payment['reference_number'] ?? '-') ?></td>
                        <td style="padding:0.5rem;"><?= htmlspecialchars($payment['notes'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="actions">
        <?php if ($invoice['status'] === 'draft'): ?>
            <form method="POST" style="display:inline;">
                <button type="submit" name="send_invoice" class="btn btn-success" onclick="return confirm('Send this invoice? You will not be able to edit it later.');">
                    <i class="fas fa-paper-plane"></i> Send Invoice
                </button>
            </form>
        <?php endif; ?>
        
        <?php if ($invoice['status'] !== 'cancelled'): ?>
            <a href="download_invoice_pdf.php?id=<?= $invoice_id ?>" class="btn" target="_blank">
                <i class="fas fa-file-pdf"></i> Download PDF
            </a>
        <?php endif; ?>
        
        <?php if ($invoice['status'] === 'draft'): ?>
            <a href="add_invoice_items.php?invoice_id=<?= $invoice_id ?>" class="btn btn-secondary">
                <i class="fas fa-edit"></i> Edit Items
            </a>
            
            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this invoice? This action cannot be undone.');">
                <button type="submit" name="cancel" class="btn btn-danger">
                    <i class="fas fa-times-circle"></i> Cancel Invoice
                </button>
            </form>
        <?php endif; ?>
        
        <a href="invoices_list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Invoices
        </a>
        
        <a href="write_invoice.php?reset=1" class="btn btn-secondary">
            <i class="fas fa-plus"></i> New Invoice
        </a>
    </div>

    <?php if ($invoice['status'] === 'sent' || $invoice['status'] === 'paid'): ?>
        <div style="margin-top:1rem; text-align:center; padding:1rem; background:#f0fdf4; border-radius:0.5rem; border:1px solid #bbf7d0;">
            <i class="fas fa-check-circle" style="color:#16a34a;"></i>
            <strong style="color:#16a34a;">Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?> has been <?= $invoice['status'] === 'paid' ? 'paid' : 'sent' ?>!</strong>
            <p style="color:#6b7280; margin-top:0.25rem;">You can download the PDF or start a new invoice.</p>
        </div>
    <?php endif; ?>

    <?php if ($invoice['status'] === 'cancelled'): ?>
        <div style="margin-top:1rem; text-align:center; padding:1rem; background:#fee2e2; border-radius:0.5rem; border:1px solid #fecaca;">
            <i class="fas fa-times-circle" style="color:#dc2626;"></i>
            <strong style="color:#dc2626;">Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?> has been cancelled.</strong>
            <p style="color:#6b7280; margin-top:0.25rem;">You can start a new invoice by clicking the button above.</p>
        </div>
    <?php endif; ?>

    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>
</body>
</html>