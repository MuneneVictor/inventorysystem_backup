<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];
$user_branch = $_SESSION['branch'] ?? 'KIMATHI';

if (!in_array($user_role, ['sales', 'super_admin', 'inventory_admin', 'manager', 'cashier'])) {
    die("ACCESS DENIED.");
}

// Helper: generate quotation number
function generateQuotationNumber($conn) {
    $stmt = $conn->query("SELECT quotation_number FROM quotations ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch(PDO::FETCH_COLUMN);
    if ($last) {
        $num = (int) substr($last, 2);
        $num++;
    } else {
        $num = 1;
    }
    return 'MC' . str_pad($num, 2, '0', STR_PAD_LEFT);
}

// AJAX handlers
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Search clients
    if ($action === 'search_client') {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 1) {
            echo json_encode([]);
            exit;
        }
        $stmt = $conn->prepare("SELECT id, client_name, client_phone, client_box, client_email FROM clients WHERE client_name LIKE ? OR client_phone LIKE ?");
        $like = "%$q%";
        $stmt->execute([$like, $like]);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($clients);
        exit;
    }
    
    // Add client
    if ($action === 'add_client') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $box = trim($_POST['box'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (empty($name)) {
            echo json_encode(['error' => 'Client name is required']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO clients (client_name, client_phone, client_box, client_email, sales_person, branch) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $box, $email, $user_id, $user_branch]);
        $id = $conn->lastInsertId();
        echo json_encode(['id' => $id, 'name' => $name, 'phone' => $phone, 'box' => $box, 'email' => $email]);
        exit;
    }
    
    // Add item to session
    if ($action === 'add_item') {
        $item = [
            'type' => $_POST['item_type'] ?? 'manual',
            'item_id' => $_POST['item_id'] ?? null,
            'description' => trim($_POST['description']),
            'specs' => trim($_POST['specs'] ?? ''),
            'quantity' => (int)$_POST['quantity'],
            'unit_price' => (float)$_POST['unit_price'],
            'vat_rate' => (float)$_POST['vat_rate'],
        ];
        if ($item['quantity'] < 1 || $item['unit_price'] < 0 || empty($item['description'])) {
            echo json_encode(['error' => 'Invalid item data']);
            exit;
        }
        $_SESSION['quotation_items'][] = $item;
        echo json_encode(['success' => true, 'items' => $_SESSION['quotation_items']]);
        exit;
    }
    
    // Remove item
    if ($action === 'remove_item') {
        $index = (int)$_GET['index'];
        if (isset($_SESSION['quotation_items'][$index])) {
            unset($_SESSION['quotation_items'][$index]);
            $_SESSION['quotation_items'] = array_values($_SESSION['quotation_items']);
        }
        echo json_encode(['success' => true, 'items' => $_SESSION['quotation_items']]);
        exit;
    }
    
    // Clear all items
    if ($action === 'clear_items') {
        $_SESSION['quotation_items'] = [];
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }
}

// Preview after save (full page)
if (isset($_GET['preview'])) {
    $quotation_id = (int)$_GET['preview'];
    $stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ? AND user_id = ?");
    $stmt->execute([$quotation_id, $user_id]);
    $quotation = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quotation) {
        die("Quotation not found.");
    }
    $stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
    $stmt->execute([$quotation_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Quotation Preview</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family: 'Inter', sans-serif; background: #f9fafb; display: flex; justify-content: center; padding: 2rem; }
            .quotation { max-width: 800px; width: 100%; background: white; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 8px; }
            .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
            .header .logo img { max-height: 70px; }
            .header .company { text-align: right; }
            .header .company h2 { font-size: 1.8rem; font-weight: 700; color: #1a4b2a; letter-spacing: 2px; }
            .header .company p { font-size: 0.9rem; line-height: 1.5; color: #4b5563; }
            .details { margin: 1.5rem 0; }
            .details table { width: 100%; border-collapse: collapse; }
            .details td { padding: 4px 0; vertical-align: top; }
            .details .label { font-weight: 600; color: #4b5563; min-width: 120px; }
            .items-table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; }
            .items-table th { background: #f3f4f6; padding: 8px; text-align: left; border-bottom: 1px solid #d1d5db; font-weight: 600; font-size: 0.85rem; }
            .items-table td { padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 0.9rem; vertical-align: middle; }
            .items-table .text-right { text-align: right; }
            .items-table .specs { font-size: 0.8rem; color: #6b7280; }
            .totals { text-align: right; margin-top: 1.5rem; padding-top: 0.5rem; border-top: 2px solid #e5e7eb; }
            .totals p { margin: 4px 0; }
            .totals .grand { font-size: 1.2rem; font-weight: 700; color: #1a4b2a; }
            .notes { margin-top: 1.5rem; padding: 1rem; background: #f9fafb; border-left: 3px solid #1a4b2a; font-size: 0.9rem; color: #4b5563; }
            .footer-note { margin-top: 2rem; text-align: center; font-size: 0.85rem; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 1rem; }
            .actions { margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap; }
            .btn { padding: 0.6rem 1.2rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
            .btn-success { background: #16a34a; color: white; }
            .btn-secondary { background: #6b7280; color: white; }
            .btn-primary { background: #1a4b2a; color: white; }
            @media (max-width: 600px) {
                body { padding: 1rem; }
                .quotation { padding: 1rem; }
                .header { flex-direction: column; align-items: flex-start; }
                .header .company { text-align: left; width: 100%; }
                .details td { display: block; width: 100%; text-align: left !important; }
            }
        </style>
    </head>
    <body>
    <div class="quotation">
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
                <tr><td><span class="label">BILL TO</span></td><td style="text-align:right;"><span class="label">Quotation Number:</span> <?= htmlspecialchars($quotation['quotation_number']) ?></td></tr>
                <tr><td><strong><?= htmlspecialchars($quotation['client_name']) ?></strong><br><?= !empty($quotation['client_box']) ? htmlspecialchars($quotation['client_box']) . '<br>' : '' ?><?= !empty($quotation['client_phone']) ? 'Phone: ' . htmlspecialchars($quotation['client_phone']) . '<br>' : '' ?><?= !empty($quotation['client_email']) ? 'Email: ' . htmlspecialchars($quotation['client_email']) : '' ?></td>
                <td style="text-align:right;"><span class="label">Quotation Date:</span> <?= htmlspecialchars($quotation['quotation_date']) ?><br><span class="label">Payment Due:</span> <?= htmlspecialchars($quotation['payment_due_date']) ?></td></tr>
            </table>
        </div>
        <table class="items-table">
            <thead><tr><th>Items</th><th class="text-right">Quantity</th><th class="text-right">Price</th><th class="text-right">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($it['description']) ?></strong><?= !empty($it['specs']) ? '<br><span class="specs">'.htmlspecialchars($it['specs']).'</span>' : '' ?></td>
                    <td class="text-right"><?= $it['quantity'] ?></td>
                    <td class="text-right"><?= number_format($it['unit_price'], 2) ?></td>
                    <td class="text-right"><?= number_format($it['total_price'], 2) ?></td>
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
        <div class="footer-note">Thank you for shopping with us</div>
        <div class="actions">
            <a href="download_quotation_pdf.php?id=<?= $quotation_id ?>" class="btn btn-success" target="_blank"><i class="fas fa-file-pdf"></i> Download PDF</a>
            <a href="write_quotation.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Quotation</a>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ---- MAIN FORM ----
if (!isset($_SESSION['quotation_items'])) {
    $_SESSION['quotation_items'] = [];
}

// Handle AJAX save (for quotation submission via AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quotation_ajax'])) {
    $client_name = trim($_POST['client_name']);
    $client_phone = trim($_POST['client_phone'] ?? '');
    $client_box = trim($_POST['client_box'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $quotation_date = $_POST['quotation_date'] ?? date('Y-m-d');
    $payment_due = $_POST['payment_due'] ?? date('Y-m-d', strtotime('+7 days'));
    $notes = trim($_POST['notes'] ?? '');
    $items = $_SESSION['quotation_items'] ?? [];
    
    if (empty($client_name) || empty($items)) {
        echo json_encode(['error' => 'Client name and at least one item are required.']);
        exit;
    }
    
    // Calculate totals
    $subtotal = 0; $total_vat = 0; $grand_total = 0;
    foreach ($items as &$it) {
        $total = $it['quantity'] * $it['unit_price'];
        $vat = $total * ($it['vat_rate'] / 100);
        $it['total_price'] = $total;
        $it['vat_amount'] = $vat;
        $it['total_with_vat'] = $total + $vat;
        $subtotal += $total;
        $total_vat += $vat;
        $grand_total += $total + $vat;
    }
    unset($it);
    
    $qnum = generateQuotationNumber($conn);
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("INSERT INTO quotations (quotation_number, client_name, client_phone, client_box, client_email, quotation_date, payment_due_date, subtotal, vat, grand_total, notes, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$qnum, $client_name, $client_phone, $client_box, $client_email, $quotation_date, $payment_due, $subtotal, $total_vat, $grand_total, $notes, $user_id]);
        $quotation_id = $conn->lastInsertId();
        foreach ($items as $it) {
            $stmt = $conn->prepare("INSERT INTO quotation_items (quotation_id, item_type, item_id, description, specs, quantity, unit_price, total_price, vat_rate, vat_amount, total_with_vat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$quotation_id, $it['type'], $it['item_id'], $it['description'], $it['specs'], $it['quantity'], $it['unit_price'], $it['total_price'], $it['vat_rate'], $it['vat_amount'], $it['total_with_vat']]);
        }
        $conn->commit();
        $_SESSION['quotation_items'] = [];
        echo json_encode(['success' => true, 'quotation_id' => $quotation_id]);
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['error' => 'Failed to save quotation: ' . $e->getMessage()]);
        exit;
    }
}

// Non-AJAX fallback save (same as before, but we keep it for safety)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quotation'])) {
    // ... (same as previous version, just redirect)
    // This is a fallback; the AJAX version above is used.
}

$items = $_SESSION['quotation_items'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write Quotation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1a4b2a;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --font-sans: 'Inter', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; }
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: all 0.3s ease; }
        .page-header { background: white; padding: 1.5rem 2rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .page-header h1 { font-size: 1.75rem; color: var(--gray-800); font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .page-header h1 i { color: var(--primary); }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .form-card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.25rem; position: relative; }
        .form-group label { font-size: 0.85rem; font-weight: 500; color: var(--gray-700); }
        .form-group input, .form-group select { padding: 0.6rem 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; width: 100%; }
        .form-group input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,75,42,0.1); }
        .btn { padding: 0.6rem 1.2rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; font-size: 0.9rem; }
        .btn-secondary { background: var(--gray-500); }
        .btn-success { background: #16a34a; }
        .btn-danger { background: #dc2626; }
        .btn-sm { padding: 0.3rem 0.6rem; font-size: 0.75rem; }
        .required { color: #dc2626; }
        .alert-error { background: #fee2e2; border-left: 4px solid #dc2626; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; color: #991b1b; }
        .client-search-results, .item-search-results { position: absolute; background: white; border: 1px solid var(--gray-300); border-radius: var(--radius-md); max-height: 250px; overflow-y: auto; z-index: 1000; width: 100%; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .client-search-results div, .item-search-results .item-result { padding: 0.5rem 0.75rem; cursor: pointer; border-bottom: 1px solid var(--gray-100); }
        .client-search-results div:hover, .item-search-results .item-result:hover { background: var(--gray-50); }
        .item-search-results .item-result strong { display: block; }
        .item-search-results .item-result small { color: var(--gray-500); font-size: 0.8rem; }
        .client-info { background: var(--gray-50); padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--gray-200); margin-top: 0.5rem; display: none; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .items-table th { background: var(--gray-50); padding: 0.5rem; text-align: left; border-bottom: 1px solid var(--gray-200); font-weight: 600; }
        .items-table td { padding: 0.5rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .items-table .text-right { text-align: right; }
        .item-totals { text-align: right; margin-top: 1rem; }
        .item-totals p { margin: 0.25rem 0; }
        .item-totals .grand { font-size: 1.1rem; font-weight: 700; color: var(--primary); }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .position-relative { position: relative; }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .btn { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice"></i> Write Quotation</h1>
        <div class="breadcrumb">
            <a href="/inventory_system/dashboard/<?= $user_role === 'super_admin' ? 'superadmindashboard.php' : ($user_role === 'manager' ? 'managerdashboard.php' : ($user_role === 'sales' ? 'salesdashboard.php' : 'inventorydashboard.php')) ?>">Dashboard</a>
            <span> / </span>
            <span>Write Quotation</span>
        </div>
    </div>

    <!-- Client Section -->
    <div class="form-card">
        <h3 style="margin-bottom:1rem;"><i class="fas fa-user"></i> Client Details</h3>
        <div class="form-grid">
            <div class="form-group position-relative">
                <label>Client Name <span class="required">*</span></label>
                <input type="text" id="clientSearch" placeholder="Type to search client..." autocomplete="off">
                <div id="clientSearchResults" class="client-search-results"></div>
                <input type="hidden" id="clientId" value="">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" id="clientPhone" placeholder="Phone number">
            </div>
            <div class="form-group">
                <label>Address / Box</label>
                <input type="text" id="clientBox" placeholder="P.O. Box">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="clientEmail" placeholder="Email address">
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-success" id="addClientBtn"><i class="fas fa-user-plus"></i> Create Client</button>
        <div id="clientInfo" class="client-info"></div>
    </div>

    <!-- Quotation Details -->
    <div class="form-card">
        <h3 style="margin-bottom:1rem;"><i class="fas fa-cog"></i> Quotation Details</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>Quotation Number</label>
                <input type="text" id="quotationNumber" value="<?= generateQuotationNumber($conn) ?>" disabled style="background:var(--gray-100);">
            </div>
            <div class="form-group">
                <label>Quotation Date</label>
                <input type="date" id="quotationDate" value="<?= date('Y-m-d') ?>" disabled style="background:var(--gray-100);">
            </div>
            <div class="form-group">
                <label>Payment Due Date</label>
                <input type="date" id="paymentDue" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
            </div>
        </div>
    </div>

    <!-- Items Section -->
    <div class="form-card">
        <h3 style="margin-bottom:1rem;"><i class="fas fa-boxes"></i> Add Items</h3>
        <!-- Item Search -->
        <div class="form-group position-relative">
            <label>Search Item (model or name)</label>
            <input type="text" id="itemSearch" placeholder="Type to search item...">
            <div id="itemSearchResults" class="item-search-results"></div>
        </div>

        <!-- Manual Entry -->
        <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.5rem;">
            <div class="form-group" style="flex:2; min-width:200px;">
                <label>Item Description <span class="required">*</span></label>
                <input type="text" id="itemDescription" placeholder="e.g., HP EliteBook 840 G6">
            </div>
            <div class="form-group" style="flex:1;">
                <label>Specifications</label>
                <input type="text" id="itemSpecs" placeholder="e.g., 8GB RAM, 256GB SSD">
            </div>
        </div>
        <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.5rem;">
            <div class="form-group" style="flex:0 0 100px;">
                <label>Quantity</label>
                <input type="number" id="itemQuantity" value="1" min="1">
            </div>
            <div class="form-group" style="flex:1;">
                <label>Unit Price (KES)</label>
                <input type="number" id="itemPrice" step="0.01" min="0" placeholder="0.00">
            </div>
            <div class="form-group" style="flex:1;">
                <label>VAT</label>
                <select id="itemVat">
                    <option value="0">No Tax</option>
                    <option value="16" selected>VAT 16%</option>
                </select>
            </div>
            <div class="form-group" style="flex:0 0 auto; align-self:flex-end;">
                <button type="button" class="btn btn-success" id="addItemBtn"><i class="fas fa-plus"></i> Add</button>
            </div>
        </div>
        <div id="itemMessage" style="margin-top:0.5rem; color:#dc2626; font-size:0.85rem;"></div>

        <!-- Items List -->
        <div id="itemsList">
            <table class="items-table">
                <thead><tr><th>#</th><th>Description</th><th>Specs</th><th>Qty</th><th>Unit Price</th><th>VAT</th><th>Total</th><th style="text-align:center;">Action</th></tr></thead>
                <tbody id="itemsTableBody">
                    <!-- will be rendered by JS -->
                </tbody>
            </table>
            <div class="item-totals">
                <p><strong>Subtotal:</strong> KES <span id="subtotalDisplay">0.00</span></p>
                <p><strong>VAT:</strong> KES <span id="vatDisplay">0.00</span></p>
                <p class="grand"><strong>Grand Total:</strong> KES <span id="grandTotalDisplay">0.00</span></p>
            </div>
        </div>
        <div style="margin-top:1rem;">
            <button type="button" class="btn btn-danger btn-sm" id="clearItemsBtn"><i class="fas fa-undo"></i> Clear All</button>
        </div>
    </div>

    <!-- Notes Section -->
    <div class="form-card">
        <h3 style="margin-bottom:1rem;"><i class="fas fa-pencil-alt"></i> Notes (Optional)</h3>
        <div class="form-group">
            <textarea id="notes" rows="2" placeholder="Any additional notes... (appears in quotation)" style="width:100%; padding:0.6rem; border:1px solid var(--gray-300); border-radius:var(--radius-md); font-family:inherit;"></textarea>
        </div>
    </div>

    <!-- Action Buttons -->
    <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-top:1rem;">
        <button type="button" class="btn btn-success" id="previewBtn"><i class="fas fa-eye"></i> Preview Quotation</button>
        <button type="button" class="btn" id="saveBtn"><i class="fas fa-save"></i> Save Quotation</button>
        <a href="quotations_list.php" class="btn btn-secondary"><i class="fas fa-list"></i> All Quotations</a>
    </div>

    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // ---------- Client Search ----------
    let clientTimeout;
    $('#clientSearch').on('input', function() {
        clearTimeout(clientTimeout);
        const q = $(this).val().trim();
        if (q.length < 1) {
            $('#clientSearchResults').hide();
            return;
        }
        clientTimeout = setTimeout(function() {
            $.ajax({
                url: 'write_quotation.php?action=search_client',
                method: 'GET',
                data: { q: q },
                dataType: 'json',
                success: function(data) {
                    const container = $('#clientSearchResults');
                    container.empty().show();
                    if (data.length === 0) {
                        container.append('<div style="padding:0.5rem;color:var(--gray-500);">No clients found. Type name and click "Create Client"</div>');
                        return;
                    }
                    data.forEach(function(client) {
                        container.append('<div data-id="'+client.id+'" data-name="'+client.client_name+'" data-phone="'+(client.client_phone||'')+'" data-box="'+(client.client_box||'')+'" data-email="'+(client.client_email||'')+'">'+client.client_name+' ('+(client.client_phone||'No phone')+')</div>');
                    });
                }
            });
        }, 300);
    });

    $('#clientSearchResults').on('click', 'div', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const phone = $(this).data('phone');
        const box = $(this).data('box');
        const email = $(this).data('email');
        $('#clientSearch').val(name);
        $('#clientPhone').val(phone);
        $('#clientBox').val(box);
        $('#clientEmail').val(email);
        $('#clientId').val(id);
        $('#clientSearchResults').hide();
        $('#clientInfo').show().html('<strong>'+name+'</strong> ' + (phone ? '| Phone: '+phone : '') + (box ? '| Box: '+box : '') + (email ? '| Email: '+email : ''));
    });

    // Create client (instant)
    $('#addClientBtn').on('click', function() {
        const name = $('#clientSearch').val().trim();
        if (!name) { alert('Please enter client name.'); return; }
        const phone = $('#clientPhone').val().trim();
        const box = $('#clientBox').val().trim();
        const email = $('#clientEmail').val().trim();
        $.ajax({
            url: 'write_quotation.php?action=add_client',
            method: 'POST',
            data: { name: name, phone: phone, box: box, email: email },
            dataType: 'json',
            success: function(res) {
                if (res.error) { alert(res.error); return; }
                $('#clientId').val(res.id);
                $('#clientSearch').val(res.name);
                $('#clientPhone').val(res.phone);
                $('#clientBox').val(res.box);
                $('#clientEmail').val(res.email);
                $('#clientInfo').show().html('<strong>'+res.name+'</strong> ' + (res.phone ? '| Phone: '+res.phone : '') + (res.box ? '| Box: '+res.box : '') + (res.email ? '| Email: '+res.email : ''));
                $('#clientSearchResults').hide();
            }
        });
    });

    // ---------- Item Search ----------
    let itemTimeout;
    $('#itemSearch').on('input', function() {
        clearTimeout(itemTimeout);
        const q = $(this).val().trim();
        if (q.length < 1) {
            $('#itemSearchResults').hide();
            return;
        }
        itemTimeout = setTimeout(function() {
            $.ajax({
                url: 'ajax_search_items.php',
                method: 'GET',
                data: { q: q },
                dataType: 'json',
                success: function(data) {
                    const container = $('#itemSearchResults');
                    container.empty().show();
                    if (data.length === 0) {
                        container.append('<div style="padding:0.5rem;color:var(--gray-500);">No matching items. You can manually enter below.</div>');
                        return;
                    }
                    data.forEach(function(item) {
                        container.append('<div class="item-result" data-type="'+item.type+'" data-id="'+item.id+'" data-name="'+item.name+'" data-specs="'+item.specs+'" data-price="'+(item.price||0)+'"><strong>'+item.name+'</strong> <small>('+item.type+')</small><br><small>'+item.specs+'</small><br><small>Branch: '+item.branch+' | Price: '+(item.price ? 'KES '+Number(item.price).toLocaleString() : 'N/A')+'</small></div>');
                    });
                }
            });
        }, 300);
    });

    $('#itemSearchResults').on('click', '.item-result', function() {
        const name = $(this).data('name');
        const specs = $(this).data('specs');
        const price = $(this).data('price');
        $('#itemDescription').val(name);
        $('#itemSpecs').val(specs);
        $('#itemPrice').val(price);
        $('#itemSearchResults').hide();
        $('#itemSearch').val('');
    });

    // ---------- Add Item (instant) ----------
    $('#addItemBtn').on('click', function() {
        const desc = $('#itemDescription').val().trim();
        if (!desc) { $('#itemMessage').text('Please enter item description.'); return; }
        const specs = $('#itemSpecs').val().trim();
        const qty = parseInt($('#itemQuantity').val()) || 1;
        if (qty < 1) { $('#itemMessage').text('Quantity must be at least 1.'); return; }
        const price = parseFloat($('#itemPrice').val());
        if (isNaN(price) || price < 0) { $('#itemMessage').text('Please enter a valid price.'); return; }
        const vat = parseFloat($('#itemVat').val()) || 0;

        $.ajax({
            url: 'write_quotation.php?action=add_item',
            method: 'POST',
            data: {
                item_type: 'manual',
                item_id: '',
                description: desc,
                specs: specs,
                quantity: qty,
                unit_price: price,
                vat_rate: vat
            },
            dataType: 'json',
            success: function(res) {
                if (res.error) { $('#itemMessage').text(res.error); return; }
                $('#itemMessage').text('');
                $('#itemDescription').val('');
                $('#itemSpecs').val('');
                $('#itemPrice').val('');
                $('#itemQuantity').val('1');
                renderItems(res.items);
            }
        });
    });

    // ---------- Remove Item (instant) ----------
    $(document).on('click', '.remove-item', function() {
        const index = $(this).data('index');
        if (!confirm('Remove this item?')) return;
        $.ajax({
            url: 'write_quotation.php?action=remove_item',
            method: 'GET',
            data: { index: index },
            dataType: 'json',
            success: function(res) {
                renderItems(res.items);
            }
        });
    });

    // ---------- Clear Items (instant) ----------
    $('#clearItemsBtn').on('click', function() {
        if (!confirm('Clear all items?')) return;
        $.ajax({
            url: 'write_quotation.php?action=clear_items',
            method: 'GET',
            dataType: 'json',
            success: function(res) {
                renderItems(res.items);
            }
        });
    });

    // ---------- Render Items ----------
    function renderItems(items) {
        const tbody = $('#itemsTableBody');
        tbody.empty();
        if (!items || items.length === 0) {
            tbody.append('<tr><td colspan="8" style="text-align:center; color:var(--gray-500); padding:1rem;">No items added yet.</td></tr>');
            updateTotals(items);
            return;
        }
        items.forEach(function(it, idx) {
            const total = it.quantity * it.unit_price * (1 + it.vat_rate/100);
            tbody.append(`
                <tr data-index="${idx}">
                    <td>${idx+1}</td>
                    <td>${it.description}</td>
                    <td>${it.specs || '-'}</td>
                    <td>${it.quantity}</td>
                    <td>${Number(it.unit_price).toFixed(2)}</td>
                    <td>${it.vat_rate}%</td>
                    <td>${total.toFixed(2)}</td>
                    <td style="text-align:center;"><button class="btn btn-danger btn-sm remove-item" data-index="${idx}"><i class="fas fa-trash"></i></button></td>
                </tr>
            `);
        });
        updateTotals(items);
    }

    function updateTotals(items) {
        let subtotal = 0, vatTotal = 0, grandTotal = 0;
        if (items) {
            items.forEach(function(it) {
                const total = it.quantity * it.unit_price;
                const vat = total * (it.vat_rate / 100);
                subtotal += total;
                vatTotal += vat;
                grandTotal += total + vat;
            });
        }
        $('#subtotalDisplay').text(subtotal.toFixed(2));
        $('#vatDisplay').text(vatTotal.toFixed(2));
        $('#grandTotalDisplay').text(grandTotal.toFixed(2));
    }

    // Initial load
    <?php
    $items = $_SESSION['quotation_items'] ?? [];
    ?>
    renderItems(<?= json_encode($items) ?>);

    // ---------- Preview (opens in new window) ----------
    $('#previewBtn').on('click', function() {
        const clientName = $('#clientSearch').val().trim();
        if (!clientName) { alert('Please enter client name.'); return; }
        const clientPhone = $('#clientPhone').val().trim();
        const clientBox = $('#clientBox').val().trim();
        const clientEmail = $('#clientEmail').val().trim();
        const paymentDue = $('#paymentDue').val();
        const notes = $('#notes').val().trim();
        const items = getCurrentItems();
        if (items.length === 0) { alert('Add at least one item.'); return; }

        const html = generateQuotationHTML(clientName, clientPhone, clientBox, clientEmail, paymentDue, notes, items);
        const win = window.open('', '_blank');
        win.document.write('<!DOCTYPE html><html><head><title>Quotation Preview</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"><style>'+getPreviewStyles()+'</style></head><body><div class="quotation">'+html+'</div></body></html>');
        win.document.close();
    });

    function getCurrentItems() {
        const items = [];
        $('#itemsTableBody tr').each(function() {
            const desc = $(this).find('td:eq(1)').text();
            const specs = $(this).find('td:eq(2)').text();
            const qty = parseInt($(this).find('td:eq(3)').text());
            const price = parseFloat($(this).find('td:eq(4)').text());
            const vatRate = parseFloat($(this).find('td:eq(5)').text());
            items.push({ description: desc, specs: specs, quantity: qty, unit_price: price, vat_rate: vatRate });
        });
        return items;
    }

    function getPreviewStyles() {
        return `
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family: 'Inter', sans-serif; background: #f9fafb; display: flex; justify-content: center; padding: 2rem; }
            .quotation { max-width: 800px; width: 100%; background: white; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 8px; }
            .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
            .header .logo img { max-height: 70px; }
            .header .company { text-align: right; }
            .header .company h2 { font-size: 1.8rem; font-weight: 700; color: #1a4b2a; letter-spacing: 2px; }
            .header .company p { font-size: 0.9rem; line-height: 1.5; color: #4b5563; }
            .details { margin: 1.5rem 0; }
            .details table { width: 100%; border-collapse: collapse; }
            .details td { padding: 4px 0; vertical-align: top; }
            .details .label { font-weight: 600; color: #4b5563; min-width: 120px; }
            .items-table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; }
            .items-table th { background: #f3f4f6; padding: 8px; text-align: left; border-bottom: 1px solid #d1d5db; font-weight: 600; font-size: 0.85rem; }
            .items-table td { padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 0.9rem; vertical-align: middle; }
            .items-table .text-right { text-align: right; }
            .items-table .specs { font-size: 0.8rem; color: #6b7280; }
            .totals { text-align: right; margin-top: 1.5rem; padding-top: 0.5rem; border-top: 2px solid #e5e7eb; }
            .totals p { margin: 4px 0; }
            .totals .grand { font-size: 1.2rem; font-weight: 700; color: #1a4b2a; }
            .notes { margin-top: 1.5rem; padding: 1rem; background: #f9fafb; border-left: 3px solid #1a4b2a; font-size: 0.9rem; color: #4b5563; }
            .footer-note { margin-top: 2rem; text-align: center; font-size: 0.85rem; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 1rem; }
            @media (max-width: 600px) {
                body { padding: 1rem; }
                .quotation { padding: 1rem; }
                .header { flex-direction: column; align-items: flex-start; }
                .header .company { text-align: left; width: 100%; }
                .details td { display: block; width: 100%; text-align: left !important; }
            }
        `;
    }

    function generateQuotationHTML(clientName, clientPhone, clientBox, clientEmail, paymentDue, notes, items) {
        let subtotal = 0, totalVat = 0, grandTotal = 0;
        let rows = '';
        items.forEach((it, idx) => {
            const total = it.quantity * it.unit_price;
            const vat = total * (it.vat_rate / 100);
            const totalWithVat = total + vat;
            subtotal += total;
            totalVat += vat;
            grandTotal += totalWithVat;
            rows += `<tr>
                <td><strong>${it.description}</strong>${it.specs ? '<br><span class="specs">'+it.specs+'</span>' : ''}</td>
                <td class="text-right">${it.quantity}</td>
                <td class="text-right">${Number(it.unit_price).toFixed(2)}</td>
                <td class="text-right">${Number(total).toFixed(2)}</td>
            </tr>`;
        });

        let notesHtml = notes ? `<div class="notes"><strong>Notes:</strong> ${notes.replace(/\n/g, '<br>')}</div>` : '';

        return `
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
                <tr><td><span class="label">BILL TO</span></td><td style="text-align:right;"><span class="label">Quotation Number:</span> ${$('#quotationNumber').val()}</td></tr>
                <tr><td><strong>${clientName}</strong><br>${clientBox ? clientBox+'<br>' : ''}${clientPhone ? 'Phone: '+clientPhone+'<br>' : ''}${clientEmail ? 'Email: '+clientEmail : ''}</td>
                <td style="text-align:right;"><span class="label">Quotation Date:</span> ${$('#quotationDate').val()}<br><span class="label">Payment Due:</span> ${paymentDue}</td></tr>
            </table>
        </div>
        <table class="items-table">
            <thead><tr><th>Items</th><th class="text-right">Quantity</th><th class="text-right">Price</th><th class="text-right">Amount</th></tr></thead>
            <tbody>${rows}</tbody>
        </table>
        <div class="totals">
            <p><strong>Subtotal:</strong> ${Number(subtotal).toFixed(2)}</p>
            ${totalVat > 0 ? `<p><strong>v.a.t 16%:</strong> ${Number(totalVat).toFixed(2)}</p>` : ''}
            <p class="grand"><strong>Amount Due (KES):</strong> ${Number(grandTotal).toFixed(2)}</p>
        </div>
        ${notesHtml}
        <div class="footer-note">Thank you for shopping with us</div>
        `;
    }

    // ---------- Save Quotation (AJAX) ----------
    $('#saveBtn').on('click', function() {
        const clientName = $('#clientSearch').val().trim();
        if (!clientName) { alert('Please enter client name.'); return; }
        const data = {
            save_quotation_ajax: 1,
            client_name: clientName,
            client_phone: $('#clientPhone').val().trim(),
            client_box: $('#clientBox').val().trim(),
            client_email: $('#clientEmail').val().trim(),
            quotation_date: $('#quotationDate').val(),
            payment_due: $('#paymentDue').val(),
            notes: $('#notes').val().trim()
        };
        $.ajax({
            url: 'write_quotation.php',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function(res) {
                if (res.error) { alert(res.error); return; }
                if (res.success && res.quotation_id) {
                    window.location.href = 'write_quotation.php?preview=' + res.quotation_id;
                }
            },
            error: function() {
                alert('An error occurred while saving. Please try again.');
            }
        });
    });
});
</script>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>