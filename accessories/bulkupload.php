<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth_check.php';
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

use PhpOffice\PhpSpreadsheet\IOFactory;

$success = '';
$error = '';
$validationErrors = [];
$warningMessages = [];
$actionLog = [];

$added_by = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Allow only super_admin, inventory_admin, and manager
if (!in_array($role, ['super_admin', 'inventory_admin', 'manager'])) {
    die("Access denied! Only administrators and managers can perform bulk upload.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];
    $fileName = $_FILES['excel_file']['name'];
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

    if (!in_array($fileExtension, ['xlsx', 'xls', 'csv'])) {
        $error = "Invalid file type. Please upload an Excel file (.xlsx, .xls, or .csv).";
    } else {
        try {
            $spreadsheet = IOFactory::load($fileTmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Skip header row
            $header = array_map(function($h) {
                return strtolower(trim($h ?? ''));
            }, $rows[0]);
            unset($rows[0]);

            // Required: accessory_name, quantity
            $requiredColumns = ['accessory_name', 'quantity'];
            $missingColumns = array_diff($requiredColumns, $header);
            if (!empty($missingColumns)) {
                $error = "Missing required columns: " . implode(', ', $missingColumns);
            } else {
                $hasPlaceColumn = in_array('place', $header);
                $hasBranchColumn = in_array('branch', $header);
                $hasPriceColumn = in_array('price', $header);

                $insertedCount = 0;
                $updatedCount = 0;
                $skippedCount = 0;
                $skippedItems = [];

                foreach ($rows as $rowIndex => $row) {
                    // Pad row to match header count
                    $rowPadded = array_pad($row, count($header), null);
                    $data = array_combine($header, $rowPadded);
                    $rowNumber = $rowIndex + 2; // because header is row 1

                    // Extract values
                    $accessory_name = trim($data['accessory_name'] ?? '');
                    $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 0;

                    // Place: default 'display'
                    $place = 'display';
                    if ($hasPlaceColumn && isset($data['place']) && trim($data['place']) !== '') {
                        $place = strtolower(trim($data['place']));
                    }

                    // Branch: default 'MOI'
                    $branch = 'MOI';
                    if ($hasBranchColumn && isset($data['branch']) && trim($data['branch']) !== '') {
                        $branch = strtoupper(trim($data['branch']));
                    }

                    // Price: optional, default NULL
                    $price = null;
                    if ($hasPriceColumn && isset($data['price']) && trim($data['price']) !== '') {
                        $price = (float)$data['price'];
                    }

                    // Validation
                    $rowErrors = [];

                    if (empty($accessory_name)) {
                        $rowErrors[] = "Accessory name is required.";
                    }
                    if ($quantity <= 0) {
                        $rowErrors[] = "Quantity must be a positive integer.";
                    }
                    if (!in_array($place, ['display', 'store', 'warehouse'])) {
                        $rowErrors[] = "Place must be 'display', 'store', or 'warehouse'.";
                    }
                    if (!in_array($branch, ['KIMATHI', 'MOI'])) {
                        $rowErrors[] = "Branch must be 'KIMATHI' or 'MOI'.";
                    }
                    if ($price !== null && $price < 0) {
                        $rowErrors[] = "Price cannot be negative.";
                    }

                    if (!empty($rowErrors)) {
                        $validationErrors[] = "Row $rowNumber: " . implode(' ', $rowErrors);
                        $skippedCount++;
                        $skippedItems[] = $accessory_name ?: "(row $rowNumber)";
                        continue;
                    }

                    // Check if accessory with same name, branch, and place already exists (status = instock)
                    $checkStmt = $conn->prepare("
                        SELECT id, quantity FROM accessories 
                        WHERE name = :name AND branch = :branch AND place = :place AND status = 'instock'
                    ");
                    $checkStmt->execute([
                        'name' => $accessory_name,
                        'branch' => $branch,
                        'place' => $place
                    ]);
                    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        // Update: increase quantity
                        $newQuantity = $existing['quantity'] + $quantity;
                        $updateStmt = $conn->prepare("
                            UPDATE accessories 
                            SET quantity = :qty, updated_by = :updated_by, updated_at = NOW() 
                            WHERE id = :id
                        ");
                        $updateStmt->execute([
                            'qty' => $newQuantity,
                            'updated_by' => $added_by,
                            'id' => $existing['id']
                        ]);
                        $updatedCount++;
                        $actionLog[] = "Updated '$accessory_name' (branch $branch, place $place): quantity increased from {$existing['quantity']} to $newQuantity.";
                    } else {
                        // Insert new accessory
                        $insertStmt = $conn->prepare("
                            INSERT INTO accessories 
                            (name, quantity, place, branch, price, added_by, status, date_added, updated_by, updated_at)
                            VALUES (:name, :qty, :place, :branch, :price, :added_by, 'instock', NOW(), :updated_by, NOW())
                        ");
                        $insertStmt->execute([
                            'name' => $accessory_name,
                            'qty' => $quantity,
                            'place' => $place,
                            'branch' => $branch,
                            'price' => $price,
                            'added_by' => $added_by,
                            'updated_by' => $added_by
                        ]);
                        $insertedCount++;
                        $actionLog[] = "Inserted new accessory '$accessory_name' (branch $branch, place $place) with quantity $quantity.";
                    }

                    // Log activity in activity_logs (one log per row)
                    $logStmt = $conn->prepare("
                        INSERT INTO activity_logs (user_id, action, details)
                        VALUES (:uid, 'Bulk upload accessory', :details)
                    ");
                    $logStmt->execute([
                        'uid' => $added_by,
                        'details' => $existing ? "Updated accessory '$accessory_name' (branch $branch, place $place) – quantity increased by $quantity." : "Added accessory '$accessory_name' (branch $branch, place $place) – quantity $quantity, price " . ($price ?? 'NULL')
                    ]);
                }

                // Build success message
                $successParts = [];
                if ($insertedCount > 0) $successParts[] = "$insertedCount new accessory(ies) added";
                if ($updatedCount > 0) $successParts[] = "$updatedCount existing accessory(ies) quantity increased";
                if ($skippedCount > 0) $successParts[] = "$skippedCount row(s) skipped due to errors";
                $success = implode('. ', $successParts) . '.';

                if (!empty($validationErrors)) {
                    $error = "Some rows had errors:<br>" . implode('<br>', $validationErrors);
                }
                if (!empty($warningMessages)) {
                    $error .= ($error ? '<br>' : '') . implode('<br>', $warningMessages);
                }
            }
        } catch (Exception $e) {
            $error = "Error reading Excel file: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Bulk Upload Accessories | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Same CSS as before – keep unchanged */
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
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert i { font-size: 1.25rem; }
        .skipped-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        .skipped-box strong { color: #d97706; display: block; margin-bottom: 0.5rem; }
        .form-container {
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
        .form-group input[type="file"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            background: white;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--font-sans);
        }
        .btn-primary { background: var(--primary); color: white; width: 100%; justify-content: center; }
        .btn-primary:hover { background: var(--primary-light); }
        .info-box {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-200);
        }
        .info-box h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .info-box h3 i { color: var(--primary); }
        .info-box table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .info-box th, .info-box td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        .info-box th { background: var(--gray-100); font-weight: 600; color: var(--gray-600); }
        .info-box .required { color: #dc2626; }
        .info-box .optional { color: var(--gray-400); font-weight: normal; }
        .template-example {
            background: #1e293b;
            color: #e2e8f0;
            padding: 1rem;
            border-radius: var(--radius-md);
            font-family: monospace;
            font-size: 0.8rem;
            overflow-x: auto;
            margin-top: 1rem;
        }
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
            .info-box { padding: 1rem; }
            .info-box table { font-size: 0.75rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header h1 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-file-upload"></i> Bulk Upload Accessories</h1>
        <div class="breadcrumb">
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="accessory_instock.php">Accessories</a>
            <span> / </span>
            <span>Bulk Upload</span>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= htmlspecialchars($success) ?></span></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($skippedItems)): ?>
        <div class="skipped-box">
            <strong><i class="fas fa-ban"></i> Skipped Rows (errors):</strong>
            <?= implode(', ', array_unique($skippedItems)) ?>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <div class="card-header">
            <h2><i class="fas fa-table"></i> Upload Excel File</h2>
        </div>
        <div class="card-body">
            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> Excel File Requirements</h3>
                <table>
                    <thead>
                        <tr><th>Column Name</th><th>Required</th><th>Description</th><th>Valid Values</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>accessory_name</td><td class="required">Required</td><td>Name of the accessory</td><td>Text</td></tr>
                        <tr><td>quantity</td><td class="required">Required</td><td>Number of units</td><td>Positive integer</td></tr>
                        <tr><td>place</td><td class="optional">Optional (default: display)</td><td>Location</td><td>display, store, warehouse</td></tr>
                        <tr><td>branch</td><td class="optional">Optional (default: MOI)</td><td>Branch</td><td>KIMATHI, MOI</td></tr>
                        <tr><td>price</td><td class="optional">Optional (default: NULL)</td><td>Unit price (KES)</td><td>Decimal number ≥ 0</td></tr>
                    </tbody>
                </table>
                <p style="margin-top: 0.75rem; font-size: 0.85rem; color: var(--gray-600);">
                    <i class="fas fa-sync-alt"></i> If an accessory with the same <strong>name</strong>, <strong>branch</strong>, and <strong>place</strong> already exists and is <strong>in stock</strong>, the quantity will be <strong>increased</strong> instead of creating a duplicate.
                </p>
            </div>

            <div class="info-box">
                <h3><i class="fas fa-file-alt"></i> Sample Excel Template</h3>
                <div class="template-example">
                    accessory_name | quantity | place | branch | price<br>
                    ---------------------------------------------------------<br>
                    DELL optical mouse | 10 | display | MOI | 1000<br>
                    HP Keyboard | 5 | store | KIMATHI | 800<br>
                    USB-C Adapter | 3 | warehouse | (empty) | 1200
                </div>
                <p style="margin-top: 0.75rem; font-size: 0.8rem; color: var(--gray-500);">
                    <i class="fas fa-download"></i> 
                    <a href="#" id="downloadTemplate" style="color: var(--primary); text-decoration: none;">Download CSV Template</a>
                </p>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label><i class="fas fa-file-excel"></i> Select Excel File (.xlsx, .xls, .csv)</label>
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                    <p style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.5rem;">Maximum file size: 10MB</p>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload & Process</button>
            </form>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function adjustMainContent() {
        const mainContent = document.querySelector('.main-content');
        const sidebar = document.querySelector('.sidebar');
        if (window.innerWidth <= 1200) {
            if (mainContent) { mainContent.style.marginLeft = '0'; mainContent.style.width = '100%'; mainContent.style.paddingTop = '5rem'; }
        } else {
            if (mainContent && sidebar) { mainContent.style.marginLeft = '260px'; mainContent.style.width = 'calc(100% - 260px)'; mainContent.style.paddingTop = ''; }
        }
    }
    adjustMainContent();
    window.addEventListener('resize', adjustMainContent);
    window.addEventListener('orientationchange', adjustMainContent);

    // Download template
    document.getElementById('downloadTemplate').addEventListener('click', function(e) {
        e.preventDefault();
        const csvContent = "accessory_name,quantity,place,branch,price\n" +
                           "DELL optical mouse,10,display,MOI,1000\n" +
                           "HP Keyboard,5,store,KIMATHI,800\n" +
                           "USB-C Adapter,3,warehouse,,1200";
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'accessory_upload_template.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
});
</script>
</body>
</html>