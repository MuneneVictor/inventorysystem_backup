<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$user_branch = null;
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
    if (!$user_branch) die("Your account has no branch assigned.");
}

$error = "";
$success = "";
$skippedSerials = [];
$rowErrors = []; // detailed errors per row

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== 0) {
        $error = "Please upload a valid file.";
    } else {
        if ($user_role === 'super_admin') {
            $branch = $_POST['branch'] ?? '';
            if (!$branch || !in_array($branch, ['KIMATHI', 'MOI'])) {
                $error = "Please select a valid branch.";
            }
        } else {
            $branch = $user_branch;
        }

        if (!$error) {
            $fileTmp = $_FILES['file']['tmp_name'];
            $fileName = $_FILES['file']['name'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
                $error = "Invalid file type. Please upload .xlsx, .xls, or .csv.";
            } else {
                try {
                    $spreadsheet = IOFactory::load($fileTmp);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray();

                    if (empty($rows)) {
                        $error = "The file is empty.";
                    } else {
                        // Assume first row is header
                        $header = array_map('trim', $rows[0]);
                        $expectedHeader = ['serial_number', 'model_name', 'size_inches'];
                        // Check if header matches expected (case-insensitive)
                        $headerLower = array_map('strtolower', $header);
                        if ($headerLower !== $expectedHeader) {
                            // Allow different order? We'll require exactly these three columns.
                            $error = "Invalid header. Expected columns: " . implode(', ', $expectedHeader);
                        } else {
                            unset($rows[0]); // remove header

                            $added_by = $user_id;
                            $count = 0;
                            $duplicates = 0;
                            $invalidRows = 0;
                            $rowErrors = [];
                            $skippedSerials = [];

                            // Prepare insert statement
                            $insertStmt = $conn->prepare("
                                INSERT INTO monitors (serial_number, model_name, size_inches, branch, added_by, date_added)
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ");

                            foreach ($rows as $rowIndex => $row) {
                                $rowNumber = $rowIndex + 2; // 1-indexed with header
                                // Ensure we have at least 3 columns
                                if (count($row) < 3) {
                                    $invalidRows++;
                                    $rowErrors[] = "Row $rowNumber: Not enough columns (expected 3).";
                                    continue;
                                }

                                $serial = trim($row[0] ?? '');
                                $model = trim($row[1] ?? '');
                                $size = trim($row[2] ?? '');

                                // Validate fields
                                $errors = [];

                                if (empty($serial)) {
                                    $errors[] = "Serial number is required.";
                                } else {
                                    // Check duplicate serial
                                    $check = $conn->prepare("SELECT serial_number FROM monitors WHERE serial_number = ?");
                                    $check->execute([$serial]);
                                    if ($check->rowCount() > 0) {
                                        $errors[] = "Serial number already exists.";
                                        $duplicates++;
                                        $skippedSerials[] = $serial;
                                    }
                                }

                                if (empty($model)) {
                                    $errors[] = "Model name is required.";
                                }

                                if (empty($size)) {
                                    $errors[] = "Size (inches) is required.";
                                } elseif (!is_numeric($size) || (int)$size <= 0 || (int)$size > 100) {
                                    $errors[] = "Size must be a number between 1 and 100.";
                                }

                                if (!empty($errors)) {
                                    $invalidRows++;
                                    $rowErrors[] = "Row $rowNumber (SN: $serial): " . implode(' ', $errors);
                                    continue;
                                }

                                // If duplicate was already flagged, skip insert
                                if (!empty($skippedSerials) && in_array($serial, $skippedSerials)) {
                                    continue;
                                }

                                // Insert
                                try {
                                    $insertStmt->execute([$serial, $model, (int)$size, $branch, $added_by]);
                                    $count++;
                                } catch (PDOException $e) {
                                    $invalidRows++;
                                    $rowErrors[] = "Row $rowNumber (SN: $serial): Database error - " . $e->getMessage();
                                }
                            }

                            if ($count > 0) {
                                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Bulk upload monitors', ?)");
                                $log->execute([$user_id, "Uploaded $count monitors to $branch branch"]);
                            }

                            // Build success/error messages
                            $success = "$count monitor(s) uploaded successfully to $branch branch.";
                            if ($duplicates > 0) {
                                $success .= " $duplicates duplicate serial(s) were skipped.";
                            }
                            if ($invalidRows > 0) {
                                $success .= " $invalidRows row(s) contained errors and were skipped.";
                            }
                        }
                    }
                } catch (Exception $e) {
                    $error = "File processing error: " . $e->getMessage();
                }
            }
        }
    }
}

// Get greeting
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
    <title>Bulk Upload Monitors | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
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
        .form-container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; }
        .card-header { background: var(--gray-50); padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--gray-200); }
        .card-header h2 { font-size: 1.25rem; font-weight: 600; color: var(--gray-800); display: flex; align-items: center; gap: 0.5rem; }
        .card-header h2 i { color: var(--primary); }
        .card-body { padding: 1.5rem; }
        .info-box { background: var(--gray-50); border-radius: var(--radius-lg); padding: 1rem 1.25rem; margin-bottom: 1.5rem; border-left: 4px solid var(--primary); }
        .info-box ul { margin-top: 0.5rem; padding-left: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--gray-700); margin-bottom: 0.5rem; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; font-family: var(--font-sans); }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,75,42,0.1); }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-family: var(--font-sans); }
        .btn-primary { background: var(--primary); color: white; width: 100%; justify-content: center; }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-secondary { background: var(--gray-200); color: var(--gray-700); }
        .alert { padding: 1rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .skipped-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-lg); padding: 1rem 1.25rem; margin-bottom: 1.5rem; font-size: 0.85rem; }
        .error-list { background: #fee2e2; border: 1px solid #fecaca; border-radius: var(--radius-lg); padding: 1rem 1.25rem; margin-bottom: 1.5rem; font-size: 0.85rem; max-height: 200px; overflow-y: auto; }
        .error-list strong { color: #991b1b; display: block; margin-bottom: 0.5rem; }
        .error-list ul { padding-left: 1.5rem; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .template-download { margin-top: 1rem; }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .page-header h1 { font-size: 1.25rem; } .card-body { padding: 1rem; } .btn { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-upload"></i> Bulk Upload Monitors</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="monitors_instock.php">Monitors</a>
            <span> / </span>
            <span>Bulk Upload</span>
        </div>
    </div>

    <div class="form-container">
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($skippedSerials)): ?>
            <div class="skipped-box">
                <strong><i class="fas fa-ban"></i> Skipped Serial Numbers (duplicates):</strong><br>
                <?= implode(', ', array_unique($skippedSerials)) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($rowErrors)): ?>
            <div class="error-list">
                <strong><i class="fas fa-exclamation-triangle"></i> Row Errors:</strong>
                <ul>
                    <?php foreach ($rowErrors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h2><i class="fas fa-table"></i> Upload Excel / CSV File</h2></div>
            <div class="card-body">
                <div class="info-box">
                    <?php if ($user_role === 'super_admin'): ?>
                        <strong>You can upload monitors to any branch.</strong>
                    <?php else: ?>
                        <strong>Your branch: <?= htmlspecialchars($user_branch) ?></strong>
                    <?php endif; ?>
                    <br><br>
                    <strong>File Format Requirements:</strong>
                    <ul>
                        <li>First row must be the header: <code>serial_number, model_name, size_inches</code></li>
                        <li>Serial number: required, unique</li>
                        <li>Model name: required</li>
                        <li>Size (inches): required, numeric between 1 and 100</li>
                    </ul>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <?php if ($user_role === 'super_admin'): ?>
                        <div class="form-group">
                            <label>Branch</label>
                            <select name="branch" required>
                                <option value="">-- Select Branch --</option>
                                <option value="KIMATHI">KIMATHI</option>
                                <option value="MOI">MOI</option>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="branch" value="<?= htmlspecialchars($user_branch) ?>">
                        <div class="info-box" style="margin-bottom:1rem;">Branch: <strong><?= htmlspecialchars($user_branch) ?></strong></div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Excel/CSV File (.xlsx, .xls, .csv)</label>
                        <input type="file" name="file" accept=".csv,.xlsx,.xls" required>
                        <p style="font-size:0.8rem; color:var(--gray-500); margin-top:0.25rem;">Maximum file size: 10MB</p>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload & Process</button>
                </form>

                <div class="template-download">
                    <p><i class="fas fa-download"></i> <a href="#" id="downloadTemplate" style="color: var(--primary); text-decoration: none;">Download CSV Template</a></p>
                </div>
            </div>
        </div>
    </div>
    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function adjustMainContent() {
        const main = document.querySelector('.main-content');
        if (window.innerWidth <= 1200) {
            main.style.marginLeft = '0';
        } else {
            main.style.marginLeft = '260px';
        }
    }
    window.addEventListener('resize', adjustMainContent);
    adjustMainContent();

    // Download template
    document.getElementById('downloadTemplate').addEventListener('click', function(e) {
        e.preventDefault();
        const csv = "serial_number,model_name,size_inches\nSN001,Dell P2419H,24\nSN002,HP E243,23.8\nSN003,Lenovo ThinkVision,27";
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'monitor_upload_template.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
});
</script>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>