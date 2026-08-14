<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth_check.php';
require_once "../includes/header.php";

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

            // Required: type, storage, quantity
            $requiredColumns = ['type', 'storage', 'quantity'];
            $missingColumns = array_diff($requiredColumns, $header);
            if (!empty($missingColumns)) {
                $error = "Missing required columns: " . implode(', ', $missingColumns);
            } else {
                $hasBranchColumn = in_array('branch', $header);
                $hasPriceColumn = in_array('price', $header);

                $insertedCount = 0;
                $updatedCount = 0;
                $skippedCount = 0;
                $skippedItems = [];

                foreach ($rows as $rowIndex => $row) {
                    $rowPadded = array_pad($row, count($header), null);
                    $data = array_combine($header, $rowPadded);
                    $rowNumber = $rowIndex + 2;

                    $type = trim($data['type'] ?? '');
                    $storage = trim($data['storage'] ?? '');
                    $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 0;

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
                    if (empty($type)) {
                        $rowErrors[] = "HDD type is required.";
                    }
                    if (empty($storage)) {
                        $rowErrors[] = "Storage is required.";
                    }
                    if ($quantity <= 0) {
                        $rowErrors[] = "Quantity must be a positive integer.";
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
                        $skippedItems[] = $type ?: "(row $rowNumber)";
                        continue;
                    }

                    // Check if HDD with same type, storage, and branch exists
                    $checkStmt = $conn->prepare("
                        SELECT id, quantity FROM hdds 
                        WHERE type = :type AND storage = :storage AND branch = :branch
                    ");
                    $checkStmt->execute([
                        'type' => $type,
                        'storage' => $storage,
                        'branch' => $branch
                    ]);
                    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        // Update: increase quantity
                        $newQuantity = $existing['quantity'] + $quantity;
                        $updateStmt = $conn->prepare("
                            UPDATE hdds 
                            SET quantity = :qty, price = :price, updated_by = :updated_by, date_updated = NOW() 
                            WHERE id = :id
                        ");
                        $updateStmt->execute([
                            'qty' => $newQuantity,
                            'price' => $price,
                            'updated_by' => $added_by,
                            'id' => $existing['id']
                        ]);
                        $updatedCount++;
                        $actionLog[] = "Updated HDD '$type $storage' (branch $branch): quantity increased from {$existing['quantity']} to $newQuantity.";
                    } else {
                        // Insert new HDD
                        $insertStmt = $conn->prepare("
                            INSERT INTO hdds 
                            (type, storage, quantity, branch, price, added_by, date_added)
                            VALUES (:type, :storage, :qty, :branch, :price, :added_by, NOW())
                        ");
                        $insertStmt->execute([
                            'type' => $type,
                            'storage' => $storage,
                            'qty' => $quantity,
                            'branch' => $branch,
                            'price' => $price,
                            'added_by' => $added_by
                        ]);
                        $insertedCount++;
                        $actionLog[] = "Inserted new HDD '$type $storage' (branch $branch) with quantity $quantity.";
                    }

                    // Log activity in activity_logs
                    $logStmt = $conn->prepare("
                        INSERT INTO activity_logs (user_id, action, details)
                        VALUES (:uid, 'Bulk upload HDD', :details)
                    ");
                    $logStmt->execute([
                        'uid' => $added_by,
                        'details' => $existing ? "Updated HDD '$type $storage' (branch $branch) – quantity increased by $quantity." : "Added HDD '$type $storage' (branch $branch) – quantity $quantity, price " . ($price ?? 'NULL')
                    ]);
                }

                // Build success message
                $successParts = [];
                if ($insertedCount > 0) $successParts[] = "$insertedCount new HDD(s) added";
                if ($updatedCount > 0) $successParts[] = "$updatedCount existing HDD(s) quantity increased";
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
require_once "../includes/sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Bulk Upload HDDs | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Same CSS as accessories bulk upload – unchanged */
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
        <h1><i class="fas fa-hdd"></i> Bulk Upload HDDs</h1>
        <div class="breadcrumb">
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="../dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="hdd_instock.php">HDDs</a>
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
                        <tr><td>type</td><td class="required">Required</td><td>HDD interface type</td><td>e.g., SATA, SAS, NVMe</td></tr>
                        <tr><td>storage</td><td class="required">Required</td><td>Storage capacity</td><td>e.g., 1TB, 2TB, 4TB</td></tr>
                        <tr><td>quantity</td><td class="required">Required</td><td>Number of units</td><td>Positive integer</td></tr>
                        <tr><td>branch</td><td class="optional">Optional (default: MOI)</td><td>Branch</td><td>KIMATHI, MOI</td></tr>
                        <tr><td>price</td><td class="optional">Optional (default: NULL)</td><td>Unit price (KES)</td><td>Decimal ≥ 0</td></tr>
                    </tbody>
                </table>
                <p style="margin-top: 0.75rem; font-size: 0.85rem; color: var(--gray-600);">
                    <i class="fas fa-sync-alt"></i> If an HDD with the same <strong>type</strong>, <strong>storage</strong>, and <strong>branch</strong> already exists, the quantity will be <strong>increased</strong> instead of creating a duplicate.
                </p>
            </div>

            <div class="info-box">
                <h3><i class="fas fa-file-alt"></i> Sample Excel Template</h3>
                <div class="template-example">
                    type | storage | quantity | branch | price<br>
                    -----------------------------------------------<br>
                    SATA | 2TB | 10 | MOI | 15000<br>
                    SATA | 500GB | 5 | KIMATHI | 8000<br>
                    NVMe | 1TB | 3 | (empty) | 12000
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
        const csvContent = "type,storage,quantity,branch,price\n" +
                           "SATA,2TB,10,MOI,15000\n" +
                           "SATA,500GB,5,KIMATHI,8000\n" +
                           "NVMe,1TB,3,,12000";
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'hdd_upload_template.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
});
</script>
</body>
</html>