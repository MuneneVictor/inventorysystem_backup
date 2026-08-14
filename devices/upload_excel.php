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
            $requiredColumns = ['serial_number', 'category', 'model_name', 'processor', 'ram', 'storage_type', 'storage_capacity'];
            $missingColumns = array_diff($requiredColumns, $header);
            
            if (!empty($missingColumns)) {
                $error = "Missing required columns: " . implode(', ', $missingColumns);
            } else {
                // Check optional columns
                $hasBranchColumn = in_array('branch', $header);
                $hasCargoNumberColumn = in_array('cargo_number', $header);
                $hasGraphicsColumn = in_array('graphics', $header);
                $hasTouchColumn = in_array('touch', $header);
                $hasConditionColumn = in_array('device_condition', $header);

                $addedCount = 0;
                $duplicateCount = 0;
                $invalidCategoryCount = 0;
                $invalidDataCount = 0;

                // Fetch all categories once for performance
                $catStmt = $conn->prepare("SELECT id, category_name FROM categories");
                $catStmt->execute();
                $categoriesRaw = $catStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Build mapping: original name -> id AND lowercase name -> id
                $catMap = [];
                $catLowerMap = [];
                foreach ($categoriesRaw as $cat) {
                    $original = trim($cat['category_name']);
                    $lower = strtolower($original);
                    $catMap[$original] = $cat['id'];
                    $catLowerMap[$lower] = $cat['id'];
                }

                foreach ($rows as $rowIndex => $row) {
                    // Ensure row has same number of columns as header
                    $rowPadded = array_pad($row, count($header), '');
                    $data = array_combine($header, $rowPadded);
                    $rowNumber = $rowIndex + 2; // For error reporting (1-indexed, +1 for header)

                    // Validate required fields
                    $serial_number = trim($data['serial_number']);
                    $category_name_raw = trim($data['category']);
                    $model_name = trim($data['model_name']);
                    $processor = trim($data['processor']);
                    $ram = (int)$data['ram'];
                    $storage_type = strtoupper(trim($data['storage_type']));
                    $storage_capacity = (int)$data['storage_capacity'];
                    
                    // Normalize category name: remove extra spaces, handle case‑insensitivity
                    $category_name_normalized = trim($category_name_raw);
                    $category_lower = strtolower($category_name_normalized);
                    
                    // Try to find category ID: first exact match, then case‑insensitive
                    $category_id = null;
                    if (isset($catMap[$category_name_normalized])) {
                        $category_id = $catMap[$category_name_normalized];
                    } elseif (isset($catLowerMap[$category_lower])) {
                        $category_id = $catLowerMap[$category_lower];
                        // Optionally log that we used case‑insensitive match
                        error_log("Category '$category_name_raw' matched to '" . array_search($category_id, $catMap) . "' via case‑insensitive lookup.");
                    }
                    
                    // Validate data types and ranges
                    $rowErrors = [];
                    
                    if (empty($serial_number)) {
                        $rowErrors[] = "Serial number is empty";
                    }
                    
                    if (empty($category_name_raw)) {
                        $rowErrors[] = "Category is empty";
                    } elseif (!$category_id) {
                        $rowErrors[] = "Category '$category_name_raw' not found in database. Available: " . implode(', ', array_keys($catMap));
                    }
                    
                    if (empty($model_name)) {
                        $rowErrors[] = "Model name is empty";
                    }
                    
                    if ($ram < 1 || $ram > 256) {
                        $rowErrors[] = "RAM must be between 1 and 256 GB";
                    }
                    
                    if (!in_array($storage_type, ['SSD', 'HDD'])) {
                        $rowErrors[] = "Storage type must be SSD or HDD";
                    }
                    
                    if ($storage_capacity < 1 || $storage_capacity > 4000) {
                        $rowErrors[] = "Storage capacity must be between 1 and 4000 GB";
                    }
                    
                    // Optional fields with defaults
                    $graphics = $hasGraphicsColumn && !empty($data['graphics']) ? trim($data['graphics']) : 'None';
                    $touch = $hasTouchColumn && !empty($data['touch']) ? trim($data['touch']) : null;
                    $device_condition = $hasConditionColumn && !empty($data['device_condition']) ? trim($data['device_condition']) : 'Refurbished';
                    $cargo_number = $hasCargoNumberColumn && !empty(trim($data['cargo_number'])) ? trim($data['cargo_number']) : null;
                    
                    // Validate device condition
                    if (!in_array($device_condition, ['New', 'Refurbished'])) {
                        $device_condition = 'Refurbished';
                    }
                    
                    // Validate touch field (only for laptops/AIO, but we'll accept any value)
                    if ($touch && !in_array($touch, ['Touch', 'Non-touch', 'N/A'])) {
                        $touch = 'N/A';
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
                    
                    // --- NEW: Determine 'place' based on category ---
                    // If category is 'Laptop' (case-insensitive) then place = 'store', else 'display'
                    $place = 'display'; // default
                    if (strtolower($category_name_raw) === 'laptop') {
                        $place = 'store';
                    }
                    
                    $status = 'In Stock';
                    
                    if (!empty($rowErrors)) {
                        $invalidDataCount++;
                        $invalidDataErrors[] = "Row $rowNumber (SN: $serial_number): " . implode(', ', $rowErrors);
                        continue;
                    }

                    // Check for duplicate serial number
                    $stmt = $conn->prepare("SELECT serial_number FROM devices WHERE serial_number = :serial");
                    $stmt->execute(['serial' => $serial_number]);
                    if ($stmt->rowCount() > 0) {
                        $duplicateCount++;
                        $skippedSerials[] = $serial_number;
                        continue;
                    }

                    // Insert device – now including 'place'
                    $insert = $conn->prepare("INSERT INTO devices 
                        (serial_number, category_id, model_name, processor, graphics, ram, storage_type, storage_capacity, touch, status, device_condition, added_by, branch, cargo_number, place) 
                        VALUES (:serial_number, :category_id, :model_name, :processor, :graphics, :ram, :storage_type, :storage_capacity, :touch, :status, :device_condition, :added_by, :branch, :cargo_number, :place)");

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
                        'place' => $place
                    ]);

                    // Log activity
                    $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) 
                                           VALUES (:user_id, :action, :details)");
                    $log->execute([
                        'user_id' => $added_by,
                        'action'  => 'Bulk upload',
                        'details' => "Added device $serial_number ($model_name) via Excel upload to branch: $branch, place: $place"
                    ]);

                    $addedCount++;
                }

                $success = "$addedCount device(s) added successfully.";
                if ($duplicateCount > 0) {
                    $success .= " $duplicateCount duplicate serial(s) were skipped.";
                }
                if ($invalidCategoryCount > 0) {
                    $success .= " $invalidCategoryCount device(s) skipped due to invalid category.";
                }
                if ($invalidDataCount > 0) {
                    $success .= " $invalidDataCount device(s) skipped due to invalid data.";
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
            <!-- Instructions Box -->
            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> Excel File Requirements</h3>
                <table>
                    <thead>
                        <tr><th>Column Name</th><th>Required</th><th>Description</th><th>Valid Values / Format</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>serial_number</td><td class="required">Required</td><td>Unique device serial number</td><td>Alphanumeric, max 255 chars</td></tr>
                        <tr><td>category</td><td class="required">Required</td><td>Device category name</td><td><?= implode(', ', $allCategories) ?></td></tr>
                        <tr><td>model_name</td><td class="required">Required</td><td>Device model name</td><td>e.g., HP EliteBook 840 G6</td></tr>
                        <tr><td>processor</td><td class="required">Required</td><td>CPU model</td><td>e.g., Intel Core i5-8250U</td></tr>
                        <tr><td>ram</td><td class="required">Required</td><td>RAM size in GB</td><td>Number between 1-256</td></tr>
                        <tr><td>storage_type</td><td class="required">Required</td><td>Storage type</td><td>SSD or HDD</td></tr>
                        <tr><td>storage_capacity</td><td class="required">Required</td><td>Storage size in GB</td><td>Number between 1-4000</td></tr>
                        <tr><td>graphics</td><td class="optional">Optional</td><td>Graphics card</td><td>e.g., Intel UHD Graphics (default: None)</td></tr>
                        <tr><td>touch</td><td class="optional">Optional</td><td>Touch screen</td><td>Touch, Non-touch, N/A (default: N/A)</td></tr>
                        <tr><td>device_condition</td><td class="optional">Optional</td><td>Device condition</td><td>New or Refurbished (default: Refurbished)</td></tr>
                        <tr><td>cargo_number</td><td class="optional">Optional</td><td>Cargo shipment number</td><td>e.g., AC16, CX37 (max 50 chars)</td></tr>
                        <tr><td>branch</td><td class="optional">Optional</td><td>Assigned branch</td><td>KIMATHI or MOI (defaults to your branch)</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Template Example -->
            <div class="info-box">
                <h3><i class="fas fa-file-alt"></i> Sample Excel Template</h3>
                <div class="template-example">
                    serial_number | category | model_name | processor | ram | storage_type | storage_capacity | graphics | touch | device_condition | cargo_number | branch<br>
                    ----------------------------------------------------------------<br>
                    5CG1234XYZ | Laptop | HP EliteBook 840 G6 | Intel Core i5-8250U | 8 | SSD | 256 | Intel UHD Graphics | Non-touch | Refurbished | AC16 | KIMATHI<br>
                    8CC5678ABC | Desktop | HP EliteDesk 705 G4 | AMD Ryzen 5 PRO 2600 | 16 | SSD | 512 | AMD Radeon | N/A | New | CX37 | MOI<br>
                    ABC9012DEF | AIO | HP ProOne 400 G5 | Intel Core i7-8700T | 32 | SSD | 1000 | Intel UHD | Touch | Refurbished | AC20 | KIMATHI
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
        
        const csvContent = "serial_number,category,model_name,processor,ram,storage_type,storage_capacity,graphics,touch,device_condition,cargo_number,branch\n" +
            "5CG1234XYZ,Laptop,HP EliteBook 840 G6,Intel Core i5-8250U,8,SSD,256,Intel UHD Graphics,Non-touch,Refurbished,AC16,KIMATHI\n" +
            "8CC5678ABC,Desktop,HP EliteDesk 705 G4,AMD Ryzen 5 PRO 2600,16,SSD,512,AMD Radeon,N/A,New,CX37,MOI\n" +
            "ABC9012DEF,AIO,HP ProOne 400 G5,Intel Core i7-8700T,32,SSD,1000,Intel UHD,Touch,Refurbished,AC20,KIMATHI";
        
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