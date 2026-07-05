<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

if (!in_array($user_role, ['sales', 'super_admin', 'manager', 'technician'])) {
    die("ACCESS DENIED.");
}

// Get invoice_id from session or URL
$invoice_id = $_GET['invoice_id'] ?? $_SESSION['invoice_id'] ?? 0;

if (!$invoice_id) {
    header("Location: write_invoice.php");
    exit;
}

// Store in session
$_SESSION['invoice_id'] = $invoice_id;

// Get invoice details
$stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ? AND user_id = ?");
$stmt->execute([$invoice_id, $user_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$invoice) {
    unset($_SESSION['invoice_id']);
    header("Location: write_invoice.php");
    exit;
}

// Handle Add Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $description = trim($_POST['description'] ?? '');
    $specs = trim($_POST['specs'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $vat_rate = (float)($_POST['vat_rate'] ?? 0);
    
    if (empty($description) || $quantity < 1 || $unit_price <= 0) {
        $error = "Please fill all required fields";
    } else {
        try {
            $total_price = $quantity * $unit_price;
            $vat_amount = $total_price * ($vat_rate / 100);
            $total_with_vat = $total_price + $vat_amount;
            
            $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_type, item_id, description, specs, quantity, unit_price, total_price, vat_rate, vat_amount, total_with_vat) VALUES (?, 'manual', '', ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$invoice_id, $description, $specs, $quantity, $unit_price, $total_price, $vat_rate, $vat_amount, $total_with_vat]);
            
            // Update invoice totals
            $stmt = $conn->prepare("SELECT SUM(total_price) as subtotal, SUM(vat_amount) as vat, SUM(total_with_vat) as grand_total FROM invoice_items WHERE invoice_id = ?");
            $stmt->execute([$invoice_id]);
            $totals = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $conn->prepare("UPDATE invoices SET subtotal = ?, vat = ?, grand_total = ?, balance_due = ? WHERE id = ?");
            $stmt->execute([$totals['subtotal'] ?? 0, $totals['vat'] ?? 0, $totals['grand_total'] ?? 0, $totals['grand_total'] ?? 0, $invoice_id]);
            
            $success = "Item added successfully!";
            
            // Clear form fields
            $_POST['description'] = '';
            $_POST['specs'] = '';
            $_POST['quantity'] = 1;
            $_POST['unit_price'] = '';
            
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle Remove Item
if (isset($_GET['remove_item'])) {
    $item_id = (int)$_GET['remove_item'];
    $stmt = $conn->prepare("DELETE FROM invoice_items WHERE id = ? AND invoice_id = ?");
    $stmt->execute([$item_id, $invoice_id]);
    
    // Update totals
    $stmt = $conn->prepare("SELECT SUM(total_price) as subtotal, SUM(vat_amount) as vat, SUM(total_with_vat) as grand_total FROM invoice_items WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("UPDATE invoices SET subtotal = ?, vat = ?, grand_total = ?, balance_due = ? WHERE id = ?");
    $stmt->execute([$totals['subtotal'] ?? 0, $totals['vat'] ?? 0, $totals['grand_total'] ?? 0, $totals['grand_total'] ?? 0, $invoice_id]);
    
    header("Location: add_invoice_items.php");
    exit;
}

// Handle Clear All
if (isset($_GET['clear_all'])) {
    $stmt = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);
    $stmt = $conn->prepare("UPDATE invoices SET subtotal = 0, vat = 0, grand_total = 0, balance_due = 0 WHERE id = ?");
    $stmt->execute([$invoice_id]);
    header("Location: add_invoice_items.php");
    exit;
}

// Get items
$stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT subtotal, vat, grand_total FROM invoices WHERE id = ?");
$stmt->execute([$invoice_id]);
$totals = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Invoice Items - Step 2</title>
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
        .step .number { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; background: rgba(255,255,255,0.2); }
        .section { background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; padding: 1.5rem; margin-bottom: 1.5rem; }
        .section-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.25rem; position: relative; }
        .form-group label { font-size: 0.85rem; font-weight: 500; color: #374151; }
        .form-group input, .form-group select { padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9rem; width: 100%; font-family: inherit; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #1a4b2a; box-shadow: 0 0 0 3px rgba(26,75,42,0.1); }
        .required { color: #dc2626; }
        .btn { padding: 0.6rem 1.2rem; background: #1a4b2a; color: white; border: none; border-radius: 0.5rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; font-size: 0.9rem; transition: all 0.2s; text-decoration: none; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-success { background: #16a34a; }
        .btn-danger { background: #dc2626; }
        .btn-secondary { background: #6b7280; }
        .btn-sm { padding: 0.3rem 0.6rem; font-size: 0.75rem; }
        .flex-row { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .flex-row .form-group { flex: 1; min-width: 150px; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .items-table th { background: #f9fafb; padding: 0.5rem; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600; }
        .items-table td { padding: 0.5rem; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .items-table .text-right { text-align: right; }
        .item-totals { text-align: right; margin-top: 1rem; }
        .item-totals p { margin: 0.25rem 0; }
        .item-totals .grand { font-size: 1.1rem; font-weight: 700; color: #1a4b2a; }
        .notification { padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .notification.success { background: #dcfce7; border: 1px solid #16a34a; color: #14532d; }
        .notification.error { background: #fee2e2; border: 1px solid #dc2626; color: #991b1b; }
        .search-results { position: absolute; background: white; border: 1px solid #d1d5db; border-radius: 0.5rem; max-height: 200px; overflow-y: auto; z-index: 1000; width: 100%; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .search-results .result-item { padding: 0.5rem 0.75rem; cursor: pointer; border-bottom: 1px solid #f3f4f6; }
        .search-results .result-item:hover { background: #f3f4f6; }
        .search-results .result-item strong { display: block; }
        .search-results .result-item small { color: #6b7280; font-size: 0.8rem; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: #9ca3af; border-top: 1px solid #e5e7eb; }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; padding: 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .flex-row { flex-direction: column; } .btn { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-boxes"></i> Add Items</h1>
        <div class="breadcrumb">
            <a href="write_invoice.php">Write Invoice</a> / Add Items
        </div>
    </div>

    <!-- Step Indicator -->
    <div class="step-indicator">
        <div class="step completed"><span class="number">✓</span> Client Details</div>
        <div class="step active"><span class="number">2</span> Add Items</div>
        <div class="step"><span class="number">3</span> Review & Export</div>
    </div>

    <?php if (isset($success)): ?>
        <div class="notification success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="notification error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Invoice Info -->
    <div class="section" style="background:#f9fafb;">
        <div style="display:flex; flex-wrap:wrap; gap:1.5rem; align-items:center;">
            <span><strong>Invoice #:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></span>
            <span><strong>Client:</strong> <?= htmlspecialchars($invoice['client_name']) ?></span>
            <span><strong>Date:</strong> <?= htmlspecialchars($invoice['invoice_date']) ?></span>
            <a href="write_invoice.php?reset=1" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Change Client</a>
        </div>
    </div>

    <!-- Add Item Section -->
    <div class="section">
        <div class="section-title"><i class="fas fa-plus-circle"></i> Add Item</div>
        
        <form method="POST" action="">
            <!-- Search -->
            <div class="form-group position-relative">
                <label>Search Item</label>
                <input type="text" id="itemSearch" placeholder="Type to search item..." autocomplete="off">
                <div id="itemResults" class="search-results"></div>
            </div>

            <!-- Manual Entry -->
            <div class="flex-row" style="margin-top:0.5rem;">
                <div class="form-group" style="flex:2;">
                    <label>Item Description <span class="required">*</span></label>
                    <input type="text" name="description" id="itemDescription" placeholder="e.g., HP EliteBook 840 G6" value="<?= htmlspecialchars($_POST['description'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Specifications</label>
                    <input type="text" name="specs" id="itemSpecs" placeholder="e.g., 8GB RAM, 256GB SSD" value="<?= htmlspecialchars($_POST['specs'] ?? '') ?>">
                </div>
            </div>
            <div class="flex-row">
                <div class="form-group" style="flex:0 0 100px;">
                    <label>Quantity</label>
                    <input type="number" name="quantity" id="itemQuantity" value="<?= $_POST['quantity'] ?? 1 ?>" min="1" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Unit Price (KES)</label>
                    <input type="number" name="unit_price" id="itemPrice" step="0.01" min="0" placeholder="0.00" value="<?= htmlspecialchars($_POST['unit_price'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>VAT</label>
                    <select name="vat_rate" id="itemVat">
                        <option value="0">No Tax</option>
                        <option value="16" selected>VAT 16%</option>
                    </select>
                </div>
                <div class="form-group" style="flex:0 0 auto; align-self:flex-end;">
                    <button type="submit" name="add_item" class="btn btn-success"><i class="fas fa-plus"></i> Add Item</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Items List -->
    <div class="section">
        <div class="section-title">
            <i class="fas fa-list"></i> Items in Invoice
            <?php if (!empty($items)): ?>
                <span style="background:#e5e7eb; padding:0.25rem 0.75rem; border-radius:9999px; font-size:0.8rem; margin-left:0.5rem;"><?= count($items) ?></span>
            <?php endif; ?>
        </div>
        
        <?php if (empty($items)): ?>
            <p class="text-muted">No items added yet.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Description</th>
                            <th>Specs</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>VAT</th>
                            <th>Total</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($items as $item): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td><strong><?= htmlspecialchars($item['description']) ?></strong></td>
                                <td><?= htmlspecialchars($item['specs'] ?? '-') ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= number_format($item['unit_price'], 2) ?></td>
                                <td><?= $item['vat_rate'] ?>%</td>
                                <td><?= number_format($item['quantity'] * $item['unit_price'] * (1 + $item['vat_rate'] / 100), 2) ?></td>
                                <td style="text-align:center;">
                                    <a href="add_invoice_items.php?remove_item=<?= $item['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Remove this item?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="item-totals">
                <p><strong>Subtotal:</strong> KES <?= number_format($totals['subtotal'] ?? 0, 2) ?></p>
                <p><strong>VAT:</strong> KES <?= number_format($totals['vat'] ?? 0, 2) ?></p>
                <p class="grand"><strong>Grand Total:</strong> KES <?= number_format($totals['grand_total'] ?? 0, 2) ?></p>
            </div>
            
            <div style="margin-top:1rem; display:flex; gap:1rem; flex-wrap:wrap;">
                <a href="add_invoice_items.php?clear_all=1" class="btn btn-danger btn-sm" onclick="return confirm('Clear all items?')">
                    <i class="fas fa-trash"></i> Clear All
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-top:1rem;">
        <?php if (!empty($items)): ?>
            <a href="review_invoice.php" class="btn btn-success"><i class="fas fa-eye"></i> Review Invoice</a>
        <?php endif; ?>
        <a href="write_invoice.php?reset=1" class="btn btn-secondary"><i class="fas fa-undo"></i> Back to Client</a>
    </div>

    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Item Search
    const itemSearch = document.getElementById('itemSearch');
    const itemResults = document.getElementById('itemResults');
    let searchTimeout;

    if (itemSearch) {
        itemSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            if (query.length < 2) {
                itemResults.style.display = 'none';
                return;
            }
            searchTimeout = setTimeout(() => {
                fetch(`ajax_search_items.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        itemResults.innerHTML = '';
                        itemResults.style.display = 'block';
                        
                        if (data.error) {
                            itemResults.innerHTML = `<div class="result-item" style="color:#dc2626;">Error: ${data.error}</div>`;
                            return;
                        }
                        
                        if (data.length === 0) {
                            itemResults.innerHTML = '<div class="result-item" style="color:#6b7280;">No items found</div>';
                            return;
                        }
                        
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'result-item';
                            div.innerHTML = `<strong>${item.name}</strong> <small>(${item.type})</small><br><small>${item.specs || ''}</small><br><small>Price: ${item.price ? 'KES ' + Number(item.price).toLocaleString() : 'N/A'}</small>`;
                            div.addEventListener('click', function() {
                                document.getElementById('itemDescription').value = item.description || item.name;
                                document.getElementById('itemSpecs').value = item.specs || '';
                                if (item.price) {
                                    document.getElementById('itemPrice').value = item.price;
                                }
                                itemResults.style.display = 'none';
                                itemSearch.value = '';
                            });
                            itemResults.appendChild(div);
                        });
                    })
                    .catch(err => {
                        console.error('Search error:', err);
                        itemResults.innerHTML = '<div class="result-item" style="color:#dc2626;">Error loading items</div>';
                        itemResults.style.display = 'block';
                    });
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#itemSearch') && !e.target.closest('#itemResults')) {
                itemResults.style.display = 'none';
            }
        });
    }
});
</script>
</body>
</html>