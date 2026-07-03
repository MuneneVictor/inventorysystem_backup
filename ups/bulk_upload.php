<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth_check.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$success = '';
$error = '';
$skippedSerials = [];
$invalidDataErrors = [];

// Logged-in user's ID
$added_by = $_SESSION['user_id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Only super_admin, inventory_admin, and manager can upload
if (!in_array($role, ['super_admin', 'inventory_admin', 'manager'])) {
    die("Access denied! Only administrators can upload UPS.");
}

// Fetch user's branch from database
$stmt = $conn->prepare("SELECT branch FROM users WHERE id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_branch = $user['branch'] ?? 'KIMATHI';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];
    $fileName = $_FILES['excel_file']['name'];
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

    $allowedExtensions = ['xlsx', 'xls', 'csv'];

    if (!in_array($fileExtension, $allowedExtensions)) {
        $error = "Invalid file type. Please upload Excel file (.xlsx, .xls, .csv).";
    } else {
        try {
            $spreadsheet = IOFactory::load($fileTmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Assume first row is header
            $header = array_map(function($h) {
                return strtolower(trim($h ?? ''));
            }, $rows[0]);
            unset($rows[0]);

            // Check required columns
            $requiredColumns = ['serial_number', 'model', 'capacity'];
            $missingColumns = array_diff($requiredColumns, $header);
            
            if (!empty($missingColumns)) {
                $error = "Missing required columns: " . implode(', ', $missingColumns);
            } else {
                // Optional columns
                $hasBranchColumn = in_array('branch', $header);
                $hasPriceColumn = in_array('price', $header);
                $hasConditionColumn = in_array('ups_condition', $header);

                $addedCount = 0;
                $duplicateCount = 0;
                $invalidDataCount = 0;

                foreach ($rows as $rowIndex => $row) {
                    $rowPadded = array_pad($row, count($header), '');
                    $data = array_combine($header, $rowPadded);
                    $rowNumber = $rowIndex + 2;

                    // Validate required fields
                    $serial_number = trim($data['serial_number']);
                    $model = trim($data['model']);
                    $capacity = (int) $data['capacity'];
                    
                    $rowErrors = [];
                    
                    if (empty($serial_number)) {
                        $rowErrors[] = "Serial number is empty";
                    }
                    if (empty($model)) {
                        $rowErrors[] = "Model is empty";
                    }
                    if ($capacity < 1) {
                        $rowErrors[] = "Capacity must be a positive integer (VA)";
                    }
                    
                    // Optional fields with defaults
                    $price = $hasPriceColumn && is_numeric($data['price']) && (float)$data['price'] > 0 ? (float)$data['price'] : null;
                    $ups_condition = $hasConditionColumn && !empty(trim($data['ups_condition'])) 
                        ? trim($data['ups_condition']) 
                        : 'New';
                    
                    // Validate condition
                    $validConditions = ['New', 'Ex-UK', 'Refurbished'];
                    if (!in_array($ups_condition, $validConditions)) {
                        $ups_condition = 'New';
                    }
                    
                    // Get branch
                    if ($hasBranchColumn && !empty(trim($data['branch']))) {
                        $branch = strtoupper(trim($data['branch']));
                        if (!in_array($branch, ['KIMATHI', 'MOI'])) {
                            $branch = $user_branch;
                        }
                    } else {
                        $branch = $user_branch;
                    }
                    
                    if (!empty($rowErrors)) {
                        $invalidDataCount++;
                        $invalidDataErrors[] = "Row $rowNumber (SN: $serial_number): " . implode(', ', $rowErrors);
                        continue;
                    }

                    // Check for duplicate serial number
                    $stmt = $conn->prepare("SELECT serial_number FROM ups WHERE serial_number = :serial");
                    $stmt->execute(['serial' => $serial_number]);
                    if ($stmt->rowCount() > 0) {
                        $duplicateCount++;
                        $skippedSerials[] = $serial_number;
                        continue;
                    }

                    // Insert UPS
                    $insert = $conn->prepare("
                        INSERT INTO ups 
                        (serial_number, model, capacity, status, added_by, price, branch, ups_condition) 
                        VALUES 
                        (:serial_number, :model, :capacity, 'instock', :added_by, :price, :branch, :ups_condition)
                    ");

                    $insert->execute([
                        'serial_number' => $serial_number,
                        'model'         => $model,
                        'capacity'      => $capacity,
                        'added_by'      => $added_by,
                        'price'         => $price,
                        'branch'        => $branch,
                        'ups_condition' => $ups_condition
                    ]);

                    // Log activity
                    $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) 
                                           VALUES (:user_id, 'Bulk upload UPS', :details)");
                    $log->execute([
                        'user_id' => $added_by,
                        'details' => "Added UPS SN: $serial_number (Model: $model, Capacity: {$capacity}VA) via Excel upload to branch: $branch"
                    ]);

                    $addedCount++;
                }

                $success = "$addedCount UPS unit(s) added successfully.";
                if ($duplicateCount > 0) {
                    $success .= " $duplicateCount duplicate serial(s) were skipped.";
                }
                if ($invalidDataCount > 0) {
                    $success .= " $invalidDataCount row(s) skipped due to invalid data.";
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
    <title>Bulk Upload UPS | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ===== SAME CSS AS upload_excel.php ===== */
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
            --primary-dark: #0f3a1e;
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
            overflow-x: hidden;
        }

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

        .page-header h1 i {
            color: var(--primary);
            font-size: 1.75rem;
        }

        .breadcrumb {
            color: var(--gray-500);
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert i {
            font-size: 1.25rem;
        }

        .skipped-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }

        .skipped-box strong {
            color: #d97706;
            display: block;
            margin-bottom: 0.5rem;
        }

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

        .card-header h2 i {
            color: var(--primary);
        }

        .card-body {
            padding: 1.5rem;
        }

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

        .btn-primary {
            background: var(--primary);
            color: white;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            background: var(--primary-light);
        }

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

        .info-box h3 i {
            color: var(--primary);
        }

        .info-box table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .info-box th,
        .info-box td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .info-box th {
            background: var(--gray-100);
            font-weight: 600;
            color: var(--gray-600);
        }

        .info-box .required {
            color: #dc2626;
        }

        .info-box .optional {
            color: var(--gray-400);
            font-weight: normal;
        }

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
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 1.5rem 1rem 1rem !important;
                padding-top: 5rem !important;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem 0.75rem 0.75rem !important;
                padding-top: 4.5rem !important;
            }

            .page-header h1 {
                font-size: 1.25rem;
            }

            .page-header {
                padding: 1rem 1.25rem;
            }

            .card-header {
                padding: 1rem 1.25rem;
            }

            .card-body {
                padding: 1.25rem;
            }

            .info-box {
                padding: 1rem;
            }

            .info-box table {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem 0.5rem 0.5rem !important;
                padding-top: 4rem !important;
            }

            .page-header h1 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1>
            <i class="fas fa-file-upload"></i>
            Bulk Upload UPS
        </h1>
        <div class="breadcrumb">
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="ups_instock.php">UPS Stock</a>
            <span> / </span>
            <span>Bulk Upload</span>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($skippedSerials)): ?>
        <div class="skipped-box">
            <strong><i class="fas fa-ban"></i> Skipped Serial Numbers:</strong>
            <?= implode(', ', array_unique($skippedSerials)) ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($invalidDataErrors)): ?>
        <div class="skipped-box">
            <strong><i class="fas fa-exclamation-triangle"></i> Data Validation Errors:</strong>
            <ul style="margin-top: 0.5rem; margin-left: 1.5rem;">
                <?php foreach ($invalidDataErrors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <div class="card-header">
            <h2>
                <i class="fas fa-table"></i>
                Upload Excel File
            </h2>
        </div>
        <div class="card-body">
            <!-- Instructions Box -->
            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> Excel File Requirements</h3>
                <table>
                    <thead>
                        <tr><th>Column Name</th><th>Required</th><th>Description</th><th>Valid Values / Format</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>serial_number</td><td class="required">Required</td><td>Unique UPS serial number</td><td>Alphanumeric, max 100 chars</td></tr>
                        <tr><td>model</td><td class="required">Required</td><td>UPS model name</td><td>e.g., MECCER UPS, APC Back-UPS</td></tr>
                        <tr><td>capacity</td><td class="required">Required</td><td>UPS capacity in VA</td><td>Positive integer (e.g., 2600)</td></tr>
                        <tr><td>branch</td><td class="optional">Optional</td><td>Assigned branch</td><td>KIMATHI or MOI (defaults to your branch)</td></tr>
                        <tr><td>price</td><td class="optional">Optional</td><td>Price per unit</td><td>Decimal number (e.g., 15000.00)</td></tr>
                        <tr><td>ups_condition</td><td class="optional">Optional</td><td>UPS condition</td><td>New, Ex-UK, Refurbished (default: New)</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Template Example -->
            <div class="info-box">
                <h3><i class="fas fa-file-alt"></i> Sample Excel Template</h3>
                <div class="template-example">
                    serial_number | model | capacity | branch | price | ups_condition<br>
                    ------------------------------------------------------------<br>
                    UPKNM9JHFH | MECCER UPS | 2600 | KIMATHI | 20000.00 | New<br>
                    APC-001 | APC Back-UPS | 1500 | MOI | 12000.00 | Ex-UK<br>
                    UPS-003 | DELTA UPS | 3000 | KIMATHI | 35000.00 | Refurbished
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
                    <p style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.5rem;">
                        Maximum file size: 10MB
                    </p>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload & Process
                </button>
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
    
    // Download template functionality
    document.getElementById('downloadTemplate').addEventListener('click', function(e) {
        e.preventDefault();
        
        const csvContent = "serial_number,model,capacity,branch,price,ups_condition\n" +
            "UPKNM9JHFH,MECCER UPS,2600,KIMATHI,20000.00,New\n" +
            "APC-001,APC Back-UPS,1500,MOI,12000.00,Ex-UK\n" +
            "UPS-003,DELTA UPS,3000,KIMATHI,35000.00,Refurbished";
        
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'ups_upload_template.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
});
</script>

</body>
</html>