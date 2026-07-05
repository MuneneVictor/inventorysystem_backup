<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

if (!in_array($user_role, ['sales', 'super_admin', 'manager', 'technician'])) {
    die("ACCESS DENIED.");
}

// Get quotation_id from URL or session
$quotation_id = isset($_GET['id']) ? (int)$_GET['id'] : ($_SESSION['quotation_id'] ?? 0);

if (!$quotation_id) {
    header("Location: write_quotation.php");
    exit;
}

// Get quotation details - check if user has permission
$stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ? AND user_id = ?");
$stmt->execute([$quotation_id, $user_id]);
$quotation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quotation) {
    // Check if it's a different user's quotation (for admin/super_admin)
    if (in_array($user_role, ['super_admin', 'manager'])) {
        $stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ?");
        $stmt->execute([$quotation_id]);
        $quotation = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$quotation) {
        // Try to get from URL with fallback
        if (isset($_GET['id'])) {
            $stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ?");
            $stmt->execute([$quotation_id]);
            $quotation = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$quotation) {
            unset($_SESSION['quotation_id']);
            header("Location: write_quotation.php?error=not_found");
            exit;
        }
    }
}

// Check if user can view this quotation
// Super admin and manager can view all, others can only view their own
if (!in_array($user_role, ['super_admin', 'manager'])) {
    if ($quotation['user_id'] != $user_id) {
        die("ACCESS DENIED. You do not have permission to view this quotation.");
    }
}

// Store in session for easy access
$_SESSION['quotation_id'] = $quotation_id;

// Get items
$stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id ASC");
$stmt->execute([$quotation_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle publish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish'])) {
    if ($quotation['status'] === 'draft') {
        $stmt = $conn->prepare("UPDATE quotations SET status = 'sent' WHERE id = ?");
        $stmt->execute([$quotation_id]);
        $published = true;
        // Refresh quotation data
        $stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ?");
        $stmt->execute([$quotation_id]);
        $quotation = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Handle cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    if ($quotation['status'] === 'draft') {
        $stmt = $conn->prepare("UPDATE quotations SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$quotation_id]);
        
        // Clear session
        unset($_SESSION['quotation_id']);
        
        // Redirect to write_quotation.php with success message
        $_SESSION['quotation_cancelled'] = "Quotation #" . $quotation['quotation_number'] . " has been cancelled.";
        header("Location: write_quotation.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Quotation - <?= htmlspecialchars($quotation['quotation_number']) ?></title>
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
        .items-table .text-center {
            text-align: center;
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
        .notes { margin-top: 1.5rem; padding: 1rem; background: #f9fafb; border-left: 3px solid #1a4b2a; font-size: 0.9rem; color: #4b5563; }
        .footer-note { margin-top: 2rem; text-align: center; font-size: 0.85rem; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 1rem; }
        .btn { padding: 0.6rem 1.2rem; background: #1a4b2a; color: white; border: none; border-radius: 0.5rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; font-size: 0.9rem; text-decoration: none; transition: all 0.2s; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-success { background: #16a34a; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-secondary { background: #6b7280; }
        .actions { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem; }
        .notification { padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .notification.success { background: #dcfce7; border: 1px solid #16a34a; color: #14532d; }
        .notification.info { background: #dbeafe; border: 1px solid #2563eb; color: #1e3a8a; }
        .notification.error { background: #fee2e2; border: 1px solid #dc2626; color: #991b1b; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: #9ca3af; border-top: 1px solid #e5e7eb; }
        .published-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; background: #16a34a; color: white; font-size: 0.75rem; font-weight: 600; }
        .back-link { display: inline-flex; align-items: center; gap: 0.5rem; color: #1a4b2a; text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; padding: 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { 
            .header { flex-direction: column; align-items: flex-start; } 
            .header .company { text-align: left; width: 100%; } 
            .details td { display: block; width: 100%; text-align: left !important; } 
            .btn { width: 100%; justify-content: center; }
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
        <h1><i class="fas fa-file-invoice"></i> Review Quotation</h1>
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
            <?php if($user_role === 'technician'): ?>
                <a href="../dashboard/techniciandashboard.php">Dashboard</a> /
            <?php endif; ?>
            <a href="quotations_list.php">Quotations</a> /
            <span><?= htmlspecialchars($quotation['quotation_number']) ?></span>
        </div>
    </div>

    <!-- Step Indicator -->
    <div class="step-indicator">
        <div class="step completed"><span class="number">✓</span> Client Details</div>
        <div class="step completed"><span class="number">✓</span> Add Items</div>
        <?php if ($quotation['status'] === 'cancelled'): ?>
            <div class="step cancelled"><span class="number">✗</span> Cancelled</div>
        <?php else: ?>
            <div class="step active"><span class="number">3</span> Review & Export</div>
        <?php endif; ?>
    </div>

    <?php if (isset($published)): ?>
        <div class="notification success">
            <i class="fas fa-check-circle"></i> 
            <strong>Success!</strong> Quotation #<?= htmlspecialchars($quotation['quotation_number']) ?> has been published successfully!
        </div>
    <?php endif; ?>

    <?php if ($quotation['status'] === 'sent'): ?>
        <div class="notification info">
            <i class="fas fa-info-circle"></i> 
            This quotation has been published. <span class="published-badge">Published</span>
        </div>
    <?php endif; ?>

    <?php if ($quotation['status'] === 'cancelled'): ?>
        <div class="notification error">
            <i class="fas fa-times-circle"></i> 
            This quotation has been <strong>cancelled</strong>. <span class="cancelled-badge">Cancelled</span>
        </div>
    <?php endif; ?>

    <!-- Quotation Display -->
    <div class="quotation-box">
        <div class="header">
            <div class="logo"><img src="../assets/MC-LOGO.png" alt="Mombasa Computers"></div>
            <div class="company">
                <h2>QUOTATION</h2>
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
                            <strong><?= htmlspecialchars($quotation['client_name']) ?></strong><br>
                            <?= !empty($quotation['client_box']) ? htmlspecialchars($quotation['client_box']) . '<br>' : '' ?>
                            <?= !empty($quotation['client_phone']) ? 'Phone: ' . htmlspecialchars($quotation['client_phone']) . '<br>' : '' ?>
                            <?= !empty($quotation['client_email']) ? 'Email: ' . htmlspecialchars($quotation['client_email']) : '' ?>
                        </div>
                    </td>
                    <td style="text-align:right;">
                        <span class="label">Quotation Number:</span> <?= htmlspecialchars($quotation['quotation_number']) ?><br>
                        <span class="label">Quotation Date:</span> <?= htmlspecialchars($quotation['quotation_date']) ?><br>
                        <span class="label">Payment Due:</span> <?= htmlspecialchars($quotation['payment_due_date']) ?><br>
                        <?php if ($quotation['status'] === 'cancelled'): ?>
                            <span class="label">Status:</span> <span class="cancelled-badge" style="margin-top:3px; display:inline-block;">CANCELLED</span>
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
            <p><strong>Subtotal:</strong> <?= number_format($quotation['subtotal'], 2) ?></p>
            <?php if ($quotation['vat'] > 0): ?><p><strong>v.a.t 16%:</strong> <?= number_format($quotation['vat'], 2) ?></p><?php endif; ?>
            <p class="grand"><strong>Amount Due (KES):</strong> <?= number_format($quotation['grand_total'], 2) ?></p>
        </div>
        <?php if (!empty($quotation['notes'])): ?>
            <div class="notes"><strong>Notes:</strong> <?= nl2br(htmlspecialchars($quotation['notes'])) ?></div>
        <?php endif; ?>
        <div class="footer-note">Thank you for shopping with us!</div>
    </div>

    <!-- Actions -->
    <div class="actions">
        <?php if ($quotation['status'] === 'draft'): ?>
            <form method="POST" style="display:inline;">
                <button type="submit" name="publish" class="btn btn-success" onclick="return confirm('Publish this quotation? You will not be able to edit it later.');">
                    <i class="fas fa-check-circle"></i> Publish Quotation
                </button>
            </form>
            
            <a href="add_quotation_items.php" class="btn btn-secondary">
                <i class="fas fa-edit"></i> Edit Items
            </a>
            
            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this quotation? This action cannot be undone.');">
                <button type="submit" name="cancel" class="btn btn-danger">
                    <i class="fas fa-times-circle"></i> Cancel Quotation
                </button>
            </form>
        <?php endif; ?>
        
        <?php if ($quotation['status'] !== 'cancelled'): ?>
            <a href="download_quotation_pdf.php?id=<?= $quotation_id ?>" class="btn" target="_blank">
                <i class="fas fa-file-pdf"></i> Download PDF
            </a>
        <?php endif; ?>
        
        <a href="quotations_list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Quotations
        </a>
        
        <a href="write_quotation.php?reset=1" class="btn btn-secondary">
            <i class="fas fa-plus"></i> New Quotation
        </a>
    </div>

    <?php if ($quotation['status'] === 'sent'): ?>
        <div style="margin-top:1rem; text-align:center; padding:1rem; background:#f0fdf4; border-radius:0.5rem; border:1px solid #bbf7d0;">
            <i class="fas fa-check-circle" style="color:#16a34a;"></i>
            <strong style="color:#16a34a;">Quotation #<?= htmlspecialchars($quotation['quotation_number']) ?> has been published!</strong>
            <p style="color:#6b7280; margin-top:0.25rem;">You can download the PDF or start a new quotation.</p>
        </div>
    <?php endif; ?>

    <?php if ($quotation['status'] === 'cancelled'): ?>
        <div style="margin-top:1rem; text-align:center; padding:1rem; background:#fee2e2; border-radius:0.5rem; border:1px solid #fecaca;">
            <i class="fas fa-times-circle" style="color:#dc2626;"></i>
            <strong style="color:#dc2626;">Quotation #<?= htmlspecialchars($quotation['quotation_number']) ?> has been cancelled.</strong>
            <p style="color:#6b7280; margin-top:0.25rem;">You can start a new quotation by clicking the button above.</p>
        </div>
    <?php endif; ?>

    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>
</body>
</html>