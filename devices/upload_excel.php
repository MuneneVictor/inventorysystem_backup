<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth_check.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$success = '';
$error = '';
$skippedSerials = [];
$invalidCategories = [];
$invalidDataErrors = [];

// Logged-in user's ID
$added_by = $_SESSION['user_id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Only super_admin, inventory_admin, and manager can upload
if (!in_array($role, ['super_admin', 'inventory_admin', 'manager'])) {
    die("Access denied! Only administrators can upload devices.");
}

// Fetch user's branch from database
$stmt = $conn->prepare("SELECT branch FROM users WHERE id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_branch = $user['branch'] ?? 'KIMATHI';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];
    $fileName = $_FILES['excel_file']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExtensions = ['xlsx', 'xls', 'csv'];

    if (!in_array($fileExtension, $allowedExtensions, true)) {
        $error = "Invalid file type. Please upload Excel file (.xlsx, .xls, .csv).";
    } else {
        try {
            $spreadsheet = IOFactory::load($fileTmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (empty($rows)) {
                throw new Exception('The uploaded file is empty.');
            }

            // The simplified upload format has only 3 columns.
            $header = array_map(function ($h) {
                return strtolower(trim((string)($h ?? '')));
            }, $rows[0]);
            unset($rows[0]);

            $requiredColumns = ['serial_number', 'category', 'specs'];
            $missingColumns = array_diff($requiredColumns, $header);

            if (!empty($missingColumns)) {
                $error = "Missing required columns: " . implode(', ', $missingColumns) .
                         ". The file must contain only/at least: serial_number, category, specs.";
            } else {
                $addedCount = 0;
                $duplicateCount = 0;
                $invalidDataCount = 0;

                // Fetch all categories once for performance.
                $catStmt = $conn->prepare("SELECT id, category_name FROM categories");
                $catStmt->execute();
                $categoriesRaw = $catStmt->fetchAll(PDO::FETCH_ASSOC);

                $catMap = [];
                foreach ($categoriesRaw as $cat) {
                    $catMap[strtolower(trim($cat['category_name']))] = [
                        'id' => $cat['id'],
                        'name' => trim($cat['category_name'])
                    ];
                }

                foreach ($rows as $rowIndex => $row) {
                    $rowPadded = array_pad($row, count($header), '');
                    $data = array_combine($header, $rowPadded);
                    $rowNumber = $rowIndex + 2;

                    $serial_number = trim((string)($data['serial_number'] ?? ''));
                    $category_name_raw = trim((string)($data['category'] ?? ''));
                    $specsRaw = trim((string)($data['specs'] ?? ''));
                    $inventoryRaw = trim((string)($data['inventory_owner'] ?? ''));

                    // Skip completely blank rows.
                    if ($serial_number === '' && $category_name_raw === '' && $specsRaw === '' && $inventoryRaw === '') {
                        continue;
                    }

                    $rowErrors = [];

                    if ($serial_number === '') {
                        $rowErrors[] = 'Serial number is empty';
                    }

                    if ($category_name_raw === '') {
                        $rowErrors[] = 'Category is empty';
                    }

                    $categoryKey = strtolower($category_name_raw);
                    $category_id = $catMap[$categoryKey]['id'] ?? null;

                    if ($category_name_raw !== '' && !$category_id) {
                        $rowErrors[] = "Category '$category_name_raw' not found. Available: " .
                                       implode(', ', array_column($categoriesRaw, 'category_name'));
                    }

                    if ($specsRaw === '') {
                        $rowErrors[] = 'Specs are empty';
                    }

                    /*
                     * SPECS FIXED ORDER:
                     * 0 Model | 1 Processor | 2 RAM | 3 Storage | 4 Graphics |
                     * 5 Touch | 6 Condition | 7 Cargo | 8 Branch
                     *
                     * Both | and comma are accepted as separators. Pipe (|) is recommended.
                     * Optional fields can use "-". Trailing optional fields may be omitted.
                     */
                    $parts = preg_split('/\s*[|,]\s*/', $specsRaw);
                    $parts = array_map('trim', $parts ?: []);

                    $model_name      = $parts[0] ?? '';
                    $processor       = $parts[1] ?? '';
                    $ramRaw          = $parts[2] ?? '';
                    $storageRaw      = $parts[3] ?? '';
                    $graphics        = $parts[4] ?? '';
                    $touchRaw        = $parts[5] ?? '';
                    $conditionRaw    = $parts[6] ?? '';
                    $cargo_number    = $parts[7] ?? '';
                    $branchRaw       = $parts[8] ?? '';

                    if ($model_name === '' || $model_name === '-') {
                        $rowErrors[] = 'Model is missing from specs (position 1)';
                    }

                    if ($processor === '' || $processor === '-') {
                        $rowErrors[] = 'Processor is missing from specs (position 2)';
                    }

                    // RAM: accept 8, 8GB, 16 GB, etc.
                    $ram = 0;
                    if (preg_match('/(\d{1,3})/', $ramRaw, $ramMatch)) {
                        $ram = (int)$ramMatch[1];
                    }
                    if ($ram < 1 || $ram > 256) {
                        $rowErrors[] = 'RAM is missing/invalid. Use values such as 8GB, 16GB or 32GB';
                    }

                    // Storage: user writes one value such as 512GB SSD or 1TB HDD.
                    $storageUpper = strtoupper(trim($storageRaw));
                    $storage_type = null;
                    $storage_capacity = 0;

                    // NVMe is an SSD technology, so treat NVME as SSD.
                    if (preg_match('/\b(SSD|NVME)\b/i', $storageUpper)) {
                        $storage_type = 'SSD';
                    } elseif (preg_match('/\bHDD\b/i', $storageUpper)) {
                        $storage_type = 'HDD';
                    }

                    if (preg_match('/(\d+(?:\.\d+)?)\s*TB\b/i', $storageUpper, $storageMatch)) {
                        $storage_capacity = (int)round(((float)$storageMatch[1]) * 1000);
                    } elseif (preg_match('/(\d+(?:\.\d+)?)\s*GB\b/i', $storageUpper, $storageMatch)) {
                        $storage_capacity = (int)round((float)$storageMatch[1]);
                    } elseif (preg_match('/\b(\d{2,4})\b/', $storageUpper, $storageMatch)) {
                        $storage_capacity = (int)$storageMatch[1];
                    }

                    if (!$storage_type) {
                        $rowErrors[] = "Storage '$storageRaw' must include SSD or HDD (e.g. 512GB SSD or 1TB HDD)";
                    }

                    if ($storage_capacity < 1 || $storage_capacity > 4000) {
                        $rowErrors[] = "Storage capacity '$storageRaw' is invalid";
                    }

                    // Graphics is optional.
                    if ($graphics === '' || $graphics === '-') {
                        $graphics = 'None';
                    }

                    // Touch is optional: blank / - defaults to N/A.
                    $touchKey = strtolower(str_replace([' ', '_'], '-', trim($touchRaw)));
                    if ($touchRaw === '' || $touchRaw === '-') {
                        $touch = 'N/A';
                    } elseif (in_array($touchKey, ['touch', 'touchscreen', 'touch-screen'], true)) {
                        $touch = 'Touch';
                    } elseif (in_array($touchKey, ['non-touch', 'nontouch', 'non--touch'], true)) {
                        $touch = 'Non-touch';
                    } elseif (in_array($touchKey, ['n/a', 'na'], true)) {
                        $touch = 'N/A';
                    } else {
                        $touch = 'N/A';
                        $rowErrors[] = "Invalid touch value '$touchRaw'. Use Touch, Non-touch or -";
                    }

                    // Condition is optional. Database default/standard is Ex-Uk.
                    $conditionKey = strtolower(trim($conditionRaw));
                    if ($conditionRaw === '' || $conditionRaw === '-') {
                        $device_condition = 'Ex-Uk';
                    } elseif (in_array($conditionKey, ['ex-uk', 'ex uk', 'exuk'], true)) {
                        $device_condition = 'Ex-Uk';
                    } elseif (in_array($conditionKey, ['refurbished', 'refurb', 'ref'], true)) {
                        $device_condition = 'Refurbished';
                    } elseif ($conditionKey === 'new') {
                        $device_condition = 'New';
                    } else {
                        $device_condition = 'Ex-Uk';
                        $rowErrors[] = "Invalid condition '$conditionRaw'. Use Ex-Uk, Refurbished, New or -";
                    }

                    // Cargo is optional.
                    if ($cargo_number === '' || $cargo_number === '-') {
                        $cargo_number = 'NO CARGO';
                    }

                    // Branch is optional. Blank / - uses logged-in user's branch.
                    if ($branchRaw === '' || $branchRaw === '-') {
                        $branch = $user_branch;
                    } else {
                        $branch = strtoupper(trim($branchRaw));
                        if (!in_array($branch, ['KIMATHI', 'MOI'], true)) {
                            $rowErrors[] = "Invalid branch '$branchRaw'. Use KIMATHI, MOI or -";
                        }
                    }

                    // Inventory ownership is optional. Missing / - defaults to Iman's Hustle.
                    $inventoryKey = strtolower(preg_replace('/[^a-z0-9]/i', '', $inventoryRaw));
                    if ($inventoryRaw === '' || $inventoryRaw === '-') {
                        $inventory_owner = 'imans_hustle';
                    } elseif (in_array($inventoryKey, ['imanhustle', 'imanshustle'], true)) {
                        $inventory_owner = 'imans_hustle';
                    } elseif (in_array($inventoryKey, ['imaninventory', 'imansinventory'], true)) {
                        $inventory_owner = 'iman_inventory';
                    } else {
                        $inventory_owner = 'imans_hustle';
                        $rowErrors[] = "Invalid inventory '$inventoryRaw'. Use Iman Inventory, Iman Hustle or leave blank.";
                    }

                    // Laptop devices go to store by default; all other categories go to display.
                    $place = (strtolower($category_name_raw) === 'laptop') ? 'store' : 'display';
                    $status = 'In Stock';

                    if (!empty($rowErrors)) {
                        $invalidDataCount++;
                        $invalidDataErrors[] = "Row $rowNumber (SN: " . ($serial_number ?: 'N/A') . "): " .
                                               implode('; ', $rowErrors);
                        continue;
                    }

                    // Duplicate serial check.
                    $stmt = $conn->prepare("SELECT serial_number FROM devices WHERE serial_number = :serial LIMIT 1");
                    $stmt->execute(['serial' => $serial_number]);
                    if ($stmt->fetchColumn()) {
                        $duplicateCount++;
                        $skippedSerials[] = $serial_number;
                        continue;
                    }

                    $insert = $conn->prepare("INSERT INTO devices
                        (serial_number, category_id, model_name, processor, graphics, ram, storage_type, storage_capacity, touch, status, device_condition, added_by, branch, cargo_number, place, inventory_owner)
                        VALUES (:serial_number, :category_id, :model_name, :processor, :graphics, :ram, :storage_type, :storage_capacity, :touch, :status, :device_condition, :added_by, :branch, :cargo_number, :place, :inventory_owner)");

                    $insert->execute([
                        'serial_number' => $serial_number,
                        'category_id' => $category_id,
                        'model_name' => $model_name,
                        'processor' => $processor,
                        'graphics' => $graphics,
                        'ram' => $ram,
                        'storage_type' => $storage_type,
                        'storage_capacity' => $storage_capacity,
                        'touch' => $touch,
                        'status' => $status,
                        'device_condition' => $device_condition,
                        'added_by' => $added_by,
                        'branch' => $branch,
                        'cargo_number' => $cargo_number,
                        'place' => $place,
                        'inventory_owner' => $inventory_owner
                    ]);

                    $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details)
                                           VALUES (:user_id, :action, :details)");
                    $log->execute([
                        'user_id' => $added_by,
                        'action' => 'Bulk upload',
                        'details' => "Added device $serial_number ($model_name) via simplified Excel upload to branch: $branch, place: $place, inventory: $inventory_owner"
                    ]);

                    $addedCount++;
                }

                $success = "$addedCount device(s) added successfully.";
                if ($duplicateCount > 0) {
                    $success .= " $duplicateCount duplicate serial(s) were skipped.";
                }
                if ($invalidDataCount > 0) {
                    $success .= " $invalidDataCount row(s) were skipped due to invalid data.";
                }
            }
        } catch (Exception $e) {
            $error = "Error reading Excel file: " . $e->getMessage();
        }
    }
}

// Get all categories for the template example
$catStmt = $conn->prepare("SELECT category_name FROM categories ORDER BY category_name");
$catStmt->execute();
$allCategories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Bulk Upload Devices | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Your existing CSS (unchanged) */
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

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
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
            Bulk Upload Devices
        </h1>
        <div class="breadcrumb">
              <?php if($_SESSION['role'] === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>       
            <?php endif; ?>
            <?php if($_SESSION['role'] === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <?php if($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="../dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="device_list.php">Devices</a>
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
            <!-- Simplified Upload Instructions -->
            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> Simplified Excel Format</h3>
                <p style="font-size:0.9rem; color:var(--gray-600); margin-bottom:1rem;">
                    Your Excel file uses <strong>4 columns</strong>. The first 3 are required; <strong>inventory_owner</strong> is optional and defaults to Iman's Hustle.
                </p>
                <table>
                    <thead>
                        <tr><th>Column</th><th>Required</th><th>What to enter</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>serial_number</strong></td><td class="required">Required</td><td>Unique device serial number</td></tr>
                        <tr><td><strong>category</strong></td><td class="required">Required</td><td><?= htmlspecialchars(implode(', ', $allCategories)) ?></td></tr>
                        <tr><td><strong>specs</strong></td><td class="required">Required</td><td>All device specifications in the fixed order below</td></tr>
                        <tr><td><strong>inventory_owner</strong></td><td class="optional">Optional</td><td>Iman Inventory or Iman Hustle. Blank / - defaults to Iman Hustle.</td></tr>
                    </tbody>
                </table>

                <div style="margin-top:1.25rem; padding:1rem; border:1px solid var(--gray-200); border-radius:var(--radius-md); background:white;">
                    <strong style="display:block; margin-bottom:.5rem;">Specs must follow this order:</strong>
                    <div class="template-example" style="margin-top:0;">
                        MODEL | PROCESSOR | RAM | STORAGE | GRAPHICS | TOUCH | CONDITION | CARGO | BRANCH
                    </div>
                    <p style="font-size:.82rem; color:var(--gray-600); margin-top:.8rem;">
                        Use <strong>|</strong> as the preferred separator. A comma is also accepted. Use <strong>-</strong> for an optional value you do not have.
                    </p>
                </div>
            </div>

            <div class="info-box">
                <h3><i class="fas fa-magic"></i> Automatic Defaults and Storage Detection</h3>
                <table>
                    <tbody>
                        <tr><td><strong>Graphics</strong></td><td>If blank or <strong>-</strong></td><td>None</td></tr>
                        <tr><td><strong>Touch</strong></td><td>If blank or <strong>-</strong></td><td>N/A</td></tr>
                        <tr><td><strong>Condition</strong></td><td>If blank or <strong>-</strong></td><td>Ex-Uk</td></tr>
                        <tr><td><strong>Cargo</strong></td><td>If blank or <strong>-</strong></td><td>NO CARGO</td></tr>
                        <tr><td><strong>Branch</strong></td><td>If blank or <strong>-</strong></td><td>Your assigned branch</td></tr>
                        <tr><td><strong>Inventory Owner</strong></td><td>If blank, <strong>-</strong>, or column omitted</td><td>Iman Hustle</td></tr>
                    </tbody>
                </table>
                <p style="font-size:.82rem; color:var(--gray-600); margin-top:1rem;">
                    For storage, write the capacity and type together, for example <strong>256GB SSD</strong>, <strong>512 SSD</strong>, <strong>1TB HDD</strong>, or <strong>1TB NVMe</strong>. NVMe is stored as SSD. If SSD/HDD is missing, the row is rejected instead of guessing the wrong storage type.
                </p>
            </div>

            <!-- Template Example -->
            <div class="info-box">
                <h3><i class="fas fa-file-alt"></i> Sample Excel Template</h3>
                <div class="template-example">
                    serial_number | category | specs | inventory_owner<br>
                    ----------------------------------------------------------------<br>
                    5CG1234XYZ | Laptop | HP EliteBook 840 G6 | Core i5 8th Gen | 16GB | 512GB SSD | Intel UHD Graphics | Non-touch | Ex-Uk | AC16 | KIMATHI | Iman Inventory<br><br>
                    8CC5678ABC | Desktop | HP EliteDesk 705 G4 | Ryzen 5 PRO 2600 | 16GB | 1TB HDD | AMD Radeon | - | Refurbished | - | MOI | Iman Hustle<br><br>
                    ABC9012DEF | Laptop | Dell Latitude 5420 | Core i5 11th Gen | 8GB | 256GB SSD
                </div>
                <p style="margin-top: 0.75rem; font-size: 0.8rem; color: var(--gray-500);">
                    <i class="fas fa-download"></i>
                    <a href="#" id="downloadTemplate" style="color: var(--primary); text-decoration: none;">Download 4-Column CSV Template</a>
                </p>
                <p style="margin-top:.65rem; font-size:.78rem; color:var(--gray-500);">
                    The third example stops after Storage. Because all remaining fields are optional, Graphics becomes None, Touch becomes N/A, Condition becomes Ex-Uk, Cargo becomes NO CARGO, and Branch becomes your assigned branch.
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
// Mobile responsive adjustments
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

        const csvContent =
            'serial_number,category,specs,inventory_owner\n' +
            '5CG1234XYZ,Laptop,"HP EliteBook 840 G6 | Core i5 8th Gen | 16GB | 512GB SSD | Intel UHD Graphics | Non-touch | Ex-Uk | AC16 | KIMATHI",Iman Inventory\n' +
            '8CC5678ABC,Desktop,"HP EliteDesk 705 G4 | Ryzen 5 PRO 2600 | 16GB | 1TB HDD | AMD Radeon | - | Refurbished | - | MOI",Iman Hustle\n' +
            'ABC9012DEF,Laptop,"Dell Latitude 5420 | Core i5 11th Gen | 8GB | 256GB SSD",';

        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'device_upload_template.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
});
</script>

</body>
</html>