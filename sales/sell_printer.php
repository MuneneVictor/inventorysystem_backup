<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// ============================================================
// ALL PHP PROCESSING (before any HTML output)
// ============================================================

// Restrict access
if (!in_array($_SESSION['role'], ['sales', 'super_admin', 'inventory_admin', 'manager', 'cashier'])) {
    die("ACCESS DENIED.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get the sale_id from GET or session
$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : ($_SESSION['current_sale_id'] ?? 0);

if (!$sale_id) {
    header("Location: make_sale.php?error=no_sale_selected");
    exit;
}

// Verify sale exists, is active, and get sold_by
$stmt = $conn->prepare("SELECT s.id, s.sold_by, s.sale_status, u.full_name AS salesperson_name FROM sales s LEFT JOIN users u ON s.sold_by = u.id WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale || $sale['sale_status'] !== 'active') {
    header("Location: make_sale.php?error=invalid_sale");
    exit;
}
$sales_person = (int)$sale['sold_by'];
$salesperson_name = $sale['salesperson_name'] ?? 'Unknown';

// Get user's branch (for inventory filtering)
$stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_branch = $stmt->fetchColumn();
if (!$user_branch) die("Your account has no branch assigned.");

$error = "";
$success = "";
$foundPrinters = [];
$notFoundSerials = [];
$singlePrinter = null;

// --- Helper: build specs string (like sales_logs) ---
function buildPrinterSpecs($printer) {
    $specs = "";
    if (!empty($printer['model_name'])) $specs .= $printer['model_name'];
    if (!empty($printer['printer_condition'])) $specs .= " | " . $printer['printer_condition'];
    return trim($specs, " |");
}

// --- Helper: update sales total_amount ---
function updateSaleTotal($conn, $sale_id) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_price), 0) FROM sale_items WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
    $new_total = $stmt->fetchColumn();
    $stmt = $conn->prepare("UPDATE sales SET total_amount = ? WHERE id = ?");
    $stmt->execute([$new_total, $sale_id]);
}

// --- SEARCH ---
if (isset($_POST['search_serial'])) {
    $input = trim($_POST['serial_number']);
    if (empty($input)) {
        $error = "Please enter serial number(s).";
    } else {
        $serials = preg_split('/[\s,]+/', $input);
        $serials = array_filter(array_map('trim', $serials));
        if (empty($serials)) {
            $error = "No valid serial numbers found.";
        } else {
            $placeholders = implode(',', array_fill(0, count($serials), '?'));
            $sql = "SELECT * FROM printers 
                    WHERE serial_number IN ($placeholders)
                      AND status = 'In Stock'
                      AND branch = ?";
            $params = array_merge($serials, [$user_branch]);
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $foundPrinters = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $foundSerials = array_column($foundPrinters, 'serial_number');
            $notFoundSerials = array_diff($serials, $foundSerials);

            if (empty($foundPrinters)) {
                $error = "Printers not found, not in stock, or not in your branch.";
            } elseif (count($serials) === 1 && !empty($foundPrinters)) {
                $singlePrinter = $foundPrinters[0];
                $foundPrinters = [];
            }
        }
    }
}

// --- SINGLE SALE ---
if (isset($_POST['sell_printer'])) {
    $serial = trim($_POST['serial_number']);
    $selling_price = trim($_POST['selling_price']);

    if ($selling_price === '' || !is_numeric($selling_price) || $selling_price <= 0) {
        $error = "Please enter a valid selling price.";
    } else {
        $conn->beginTransaction();
        try {
            // Get printer details
            $stmt = $conn->prepare("SELECT * FROM printers WHERE serial_number = ? AND status = 'In Stock' AND branch = ?");
            $stmt->execute([$serial, $user_branch]);
            $printer = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$printer) {
                throw new Exception("Printer not found in your branch or already sold.");
            }

            // Update printer status
            $update = $conn->prepare("
                UPDATE printers 
                SET status = 'Sold', 
                    selling_price = ?, 
                    date_sold = NOW(), 
                    sold_by = ? 
                WHERE serial_number = ?
            ");
            $update->execute([$selling_price, $sales_person, $serial]);

            // Build specs for description
            $specs = buildPrinterSpecs($printer);

            // Insert into sale_items
            $insert = $conn->prepare("
                INSERT INTO sale_items 
                (sale_id, item_type, item_id, description, quantity, unit_price, sales_person)
                VALUES (?, 'printers', ?, ?, 1, ?, ?)
            ");
            $insert->execute([$sale_id, $serial, $specs, $selling_price, $sales_person]);

            // Update sale completion_status
            $updateSale = $conn->prepare("UPDATE sales SET completion_status = 'pending' WHERE id = ?");
            $updateSale->execute([$sale_id]);

            // Update total_amount in sales
            updateSaleTotal($conn, $sale_id);

            // Log activity
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Sold printer', ?)");
            $log->execute([$user_id, "Sold printer SN: $serial for KES " . number_format($selling_price, 2) . " in sale #$sale_id"]);

            $conn->commit();
            header("Location: checkout.php?sale_id=$sale_id&success=printer_sold");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// --- BULK SALE ---
if (isset($_POST['sell_bulk_printers'])) {
    $selectedSerials = $_POST['selected_serials'] ?? [];
    if (empty($selectedSerials)) {
        $error = "No printers selected.";
    } else {
        $prices = [];
        foreach ($selectedSerials as $serial) {
            $priceField = 'selling_price_' . $serial;
            $price = trim($_POST[$priceField] ?? '');
            if ($price === '' || !is_numeric($price) || $price <= 0) {
                $error = "Please enter a valid selling price for all selected printers.";
                break;
            }
            $prices[$serial] = $price;
        }

        if (!$error) {
            $soldCount = 0;
            $failedSerials = [];
            $conn->beginTransaction();
            try {
                foreach ($prices as $serial => $price) {
                    // Get printer details
                    $stmt = $conn->prepare("SELECT * FROM printers WHERE serial_number = ? AND status = 'In Stock' AND branch = ?");
                    $stmt->execute([$serial, $user_branch]);
                    $printer = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$printer) {
                        $failedSerials[] = $serial;
                        continue;
                    }

                    // Update printer
                    $update = $conn->prepare("
                        UPDATE printers 
                        SET status = 'Sold', 
                            selling_price = ?, 
                            date_sold = NOW(), 
                            sold_by = ? 
                        WHERE serial_number = ?
                    ");
                    $update->execute([$price, $sales_person, $serial]);

                    // Build specs
                    $specs = buildPrinterSpecs($printer);

                    // Insert into sale_items
                    $insert = $conn->prepare("
                        INSERT INTO sale_items 
                        (sale_id, item_type, item_id, description, quantity, unit_price, sales_person)
                        VALUES (?, 'printers', ?, ?, 1, ?, ?)
                    ");
                    $insert->execute([$sale_id, $serial, $specs, $price, $sales_person]);

                    $soldCount++;
                }

                // Update sale completion_status if any sold
                if ($soldCount > 0) {
                    $updateSale = $conn->prepare("UPDATE sales SET completion_status = 'pending' WHERE id = ?");
                    $updateSale->execute([$sale_id]);

                    // Update total_amount in sales
                    updateSaleTotal($conn, $sale_id);
                }

                $conn->commit();

                if ($soldCount > 0) {
                    header("Location: checkout.php?sale_id=$sale_id&success=bulk_printers_sold");
                    exit;
                } else {
                    $error = "No printers could be sold.";
                }
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// ============================================================
// NOW WE INCLUDE HEADER AND SIDEBAR (HTML OUTPUT STARTS)
// ============================================================


date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sell Printer | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- QR Code Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        /* ===== ORIGINAL STYLES + FIXES (copied from sell_device.php) ===== */
        :root {
            --primary: #1a4b2a;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
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
        .card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; }
        .card-header { background: var(--gray-50); padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200); font-weight: 600; }
        .card-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: var(--gray-700); }
        .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); background: white; font-family: inherit; }
        .price-input { width: 100%; padding: 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        .btn { padding: 0.75rem 1.5rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; width: 100%; justify-content: center; }
        .btn-secondary { background: var(--gray-500); }
        .btn-checkout { background: #2563eb; color: white; border: none; padding: 0.5rem 1rem; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; width: auto; text-decoration: none; }
        .btn-checkout:hover { background: #1d4ed8; transform: translateY(-2px); }
        .scan-btn { background: #2563eb; color: white; border: none; padding: 0.5rem 1rem; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; width: auto; }
        .scan-btn:hover { background: #1d4ed8; }
        .alert { padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--gray-200); }
        th { background: var(--gray-50); }
        .checkbox-cell { text-align: center; }
        .price-cell input { width: 140px; padding: 0.4rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); }
        .suggested { font-size: 0.65rem; color: var(--gray-500); display: block; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        
        /* FIX: specs text - full display, no truncation */
        .specs-text {
            font-size: 0.8rem;
            color: var(--gray-600);
            display: block;
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
            max-width: 100%;
            word-break: break-word;
        }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-top: 0.5rem; }
        .table-responsive table { min-width: 600px; }

        /* Scanner Modal */
        .scanner-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .scanner-overlay.active { display: flex; }
        .scanner-box {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            max-width: 500px;
            width: 95%;
            text-align: center;
            position: relative;
        }
        .scanner-box .close-btn {
            position: absolute;
            top: 10px; right: 15px;
            font-size: 1.8rem;
            cursor: pointer;
            background: none;
            border: none;
            color: var(--gray-500);
        }
        .scanner-box .close-btn:hover { color: var(--gray-800); }
        .scanner-box #reader {
            width: 100%;
            min-height: 300px;
            margin: 1rem 0;
        }
        .scanner-box p { color: var(--gray-500); font-size: 0.9rem; margin-bottom: 0.5rem; }
        .scan-btn-wrapper { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }

        @media (min-width: 1025px) {
            .scan-btn-wrapper .scan-btn { display: none; }
            .scan-btn-wrapper span { display: none; }
        }
        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }
        @media (max-width: 768px) {
            .card-body { padding: 1rem; }
            .price-cell input { width: 100%; }
            .scanner-box { padding: 1rem; }
            .specs-text { 
                max-width: 100%; 
                white-space: normal;
                word-break: break-word;
            }
            .table-responsive table { min-width: 500px; }
        }
        @media (max-width: 480px) {
            .scanner-box { max-width: 100%; border-radius: 0; height: 100vh; padding-top: 3rem; }
            .scanner-box #reader { height: 70vh; min-height: 200px; }
            .table-responsive table { min-width: 400px; }
        }
    </style>
</head>
<body>
     <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-print"></i> Sell Printer</h1>
        <div class="breadcrumb">
            <a href="../dashboard/salesdashboard.php">Dashboard</a>
            <span> / </span>
            <span>Sell Printer</span>
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
                <a href="checkout.php?sale_id=<?= $sale_id ?>" class="btn-checkout">
                    <i class="fas fa-arrow-right"></i> Go to Checkout
                </a>
                <a href="make_sale.php" style="font-size:0.8rem; color:var(--primary); text-decoration:none;">
                    <i class="fas fa-undo"></i> Change Sale
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><i class="fas fa-search"></i> Search Printers</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="sale_id" value="<?= $sale_id ?>">
                <div class="form-group">
                    <label>Serial Numbers (one per line or comma separated)</label>
                    <div class="scan-btn-wrapper">
                        <button type="button" class="scan-btn" onclick="openScanner()">
                            <i class="fas fa-camera"></i> Scan Serial Number
                        </button>
                        <span style="font-size:0.8rem; color:var(--gray-500);">(click to use camera on your device)</span>
                    </div>
                    <textarea name="serial_number" id="serialInput" rows="4" placeholder="Type or scan serial numbers..." required autofocus><?= isset($_POST['serial_number']) ? htmlspecialchars($_POST['serial_number']) : '' ?></textarea>
                </div>
                <button type="submit" name="search_serial" class="btn"><i class="fas fa-search"></i> Search Printer(s)</button>
            </form>
            <?php if (!empty($notFoundSerials)): ?>
                <div class="alert alert-error" style="margin-top:1rem;"><strong>Not Found:</strong> <?= implode(', ', $notFoundSerials) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($singlePrinter): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle"></i> Printer Details</div>
            <div class="card-body">
                <table>
                    <tr><th>Serial</th><td><?= htmlspecialchars($singlePrinter['serial_number']) ?></td></tr>
                    <tr><th>Model</th><td><?= htmlspecialchars($singlePrinter['model_name']) ?></td></tr>
                    <?php if (!empty($singlePrinter['printer_condition'])): ?>
                        <tr><th>Condition</th><td><?= htmlspecialchars($singlePrinter['printer_condition']) ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Branch</th><td><?= htmlspecialchars($singlePrinter['branch']) ?></td></tr>
                    <tr>
                        <th>Selling Price (KES)</th>
                        <td>
                            <input type="number" name="selling_price" form="singleSaleForm" step="0.01" min="0.01"
                                   value="<?= $singlePrinter['price'] ?? '' ?>"
                                   placeholder="Enter selling price" required style="width:200px; padding:0.5rem; border:1px solid var(--gray-300); border-radius:var(--radius-md);">
                            <?php if ($singlePrinter['price']): ?>
                                <span style="font-size:0.8rem; color:var(--gray-500);"> (suggested: <?= number_format($singlePrinter['price'], 2) ?>)</span>
                            <?php else: ?>
                                <span style="font-size:0.8rem; color:var(--gray-500);"> (no suggested price)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <form method="POST" id="singleSaleForm">
                    <input type="hidden" name="serial_number" value="<?= htmlspecialchars($singlePrinter['serial_number']) ?>">
                    <input type="hidden" name="sale_id" value="<?= $sale_id ?>">
                    <button type="submit" name="sell_printer" class="btn">Confirm Sale</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($foundPrinters)): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-list"></i> Found Printers (<?= count($foundPrinters) ?>)</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="sale_id" value="<?= $sale_id ?>">
                    <p><input type="checkbox" id="selectAll" onchange="selectAllCheckboxes(this)"> <label for="selectAll">Select All</label></p>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th class="checkbox-cell">Sell</th>
                                    <th>#</th>
                                    <th>Serial</th>
                                    <th>Specifications</th>
                                    <th style="min-width:140px;">Selling Price (KES)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; foreach ($foundPrinters as $p): ?>
                                <tr>
                                    <td class="checkbox-cell"><input type="checkbox" name="selected_serials[]" value="<?= htmlspecialchars($p['serial_number']) ?>" checked></td>
                                    <td><?= $counter++ ?></td>
                                    <td><code><?= htmlspecialchars($p['serial_number']) ?></code></td>
                                    <td>
                                        <span class="specs-text" title="<?= htmlspecialchars(buildPrinterSpecs($p)) ?>">
                                            <?= htmlspecialchars(buildPrinterSpecs($p)) ?>
                                        </span>
                                    </td>
                                    <td class="price-cell">
                                        <input type="number" name="selling_price_<?= htmlspecialchars($p['serial_number']) ?>" step="0.01" min="0.01"
                                               value="<?= $p['price'] ?? '' ?>"
                                               placeholder="Enter price" required>
                                        <?php if ($p['price']): ?>
                                            <span class="suggested">suggested: <?= number_format($p['price'], 2) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" name="sell_bulk_printers" class="btn" style="margin-top:1rem;">Sell Selected</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>

<!-- Scanner Overlay Modal -->
<div class="scanner-overlay" id="scannerOverlay">
    <div class="scanner-box">
        <button class="close-btn" onclick="closeScanner()">&times;</button>
        <h3 style="margin-bottom:0.5rem;"><i class="fas fa-camera"></i> Scan Serial Number</h3>
        <p>Position the barcode/QR code in front of the camera</p>
        <div id="reader"></div>
        <button class="btn btn-secondary" onclick="closeScanner()" style="margin-top:0.5rem;">Cancel</button>
    </div>
</div>

<script>
let html5QrCode = null;
let scannerActive = false;

function playBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        const osc1 = audioCtx.createOscillator();
        const gain1 = audioCtx.createGain();
        osc1.connect(gain1);
        gain1.connect(audioCtx.destination);
        osc1.frequency.value = 1200;
        osc1.type = 'square';
        gain1.gain.setValueAtTime(0.25, audioCtx.currentTime);
        gain1.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.08);
        osc1.start(audioCtx.currentTime);
        osc1.stop(audioCtx.currentTime + 0.08);

        const osc2 = audioCtx.createOscillator();
        const gain2 = audioCtx.createGain();
        osc2.connect(gain2);
        gain2.connect(audioCtx.destination);
        osc2.frequency.value = 1200;
        osc2.type = 'square';
        gain2.gain.setValueAtTime(0.25, audioCtx.currentTime + 0.12);
        gain2.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.2);
        osc2.start(audioCtx.currentTime + 0.12);
        osc2.stop(audioCtx.currentTime + 0.2);
    } catch (e) { console.log('Beep not supported'); }
}

function openScanner() {
    const overlay = document.getElementById('scannerOverlay');
    overlay.classList.add('active');
    setTimeout(() => {
        if (!scannerActive) {
            startScanner();
        }
    }, 500);
}

function closeScanner() {
    const overlay = document.getElementById('scannerOverlay');
    overlay.classList.remove('active');
    if (html5QrCode && scannerActive) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
            scannerActive = false;
        }).catch(() => { scannerActive = false; });
    }
}

function startScanner() {
    const readerElement = document.getElementById('reader');
    if (!readerElement) {
        alert('Scanner error: reader element missing.');
        closeScanner();
        return;
    }
    if (typeof Html5Qrcode === 'undefined') {
        alert('Scanner library not loaded. Please check your internet connection and refresh.');
        closeScanner();
        return;
    }
    readerElement.style.width = '100%';
    readerElement.style.minHeight = '300px';

    html5QrCode = new Html5Qrcode("reader");
    const config = {
        fps: 15,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
    };

    html5QrCode.start(
        { facingMode: "environment" },
        config,
        onScanSuccess,
        onScanError
    ).then(() => {
        scannerActive = true;
    }).catch(err => {
        console.error('Scanner start error (environment):', err);
        html5QrCode.start(
            { facingMode: "user" },
            config,
            onScanSuccess,
            onScanError
        ).then(() => {
            scannerActive = true;
        }).catch(err2 => {
            console.error('Scanner start error (user):', err2);
            let msg = 'Unable to access camera. Please ensure camera permissions are granted.';
            if (window.location.protocol === 'http:') {
                msg += ' Your site is using HTTP; camera access may be blocked. Try using HTTPS.';
            }
            alert(msg);
            closeScanner();
        });
    });
}

function onScanSuccess(decodedText, decodedResult) {
    playBeep();
    const textarea = document.getElementById('serialInput');
    if (textarea) {
        if (textarea.value.trim() !== '') {
            textarea.value += '\n' + decodedText;
        } else {
            textarea.value = decodedText;
        }
        textarea.dispatchEvent(new Event('input'));
    }
    closeScanner();
}

function onScanError(errorMessage) { }

window.addEventListener('beforeunload', function() {
    if (html5QrCode && scannerActive) {
        html5QrCode.stop().catch(() => {});
    }
});

function selectAllCheckboxes(source) {
    document.querySelectorAll('input[name="selected_serials[]"]').forEach(cb => cb.checked = source.checked);
}

function adjustMainContent() {
    const main = document.querySelector('.main-content');
    if (window.innerWidth <= 1200) main.style.marginLeft = '0';
    else main.style.marginLeft = '260px';
}
window.addEventListener('resize', adjustMainContent);
adjustMainContent();
</script>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>