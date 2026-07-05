<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// ============================================================
// STRICT ROLE CHECK - Only technicians
// ============================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    die("ACCESS DENIED: Only technicians can add repairs.");
}

$user_id = (int) $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'Technician');
$user_role = $_SESSION['role'];

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Get technician branch
$stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_branch = $stmt->fetchColumn();
if (!$user_branch) {
    die("Your account has no branch assigned. Contact administrator.");
}

// ============================================================
// FETCH CATEGORIES FOR CLIENT MODE
// ============================================================
$catStmt = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// FETCH USERS FOR DROPDOWNS
// ============================================================

// Get users who can give device (inventory_admin, sales, manager, super_admin in same branch)
$stmt = $conn->prepare("
    SELECT id, full_name, role 
    FROM users
    WHERE is_active = 1
      AND role IN ('inventory_admin', 'sales', 'manager', 'super_admin')
      AND (branch = ? OR role = 'super_admin')
    ORDER BY full_name
");
$stmt->execute([$user_branch]);
$givenByUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sales people for return devices
$stmt = $conn->prepare("
    SELECT id, full_name 
    FROM users
    WHERE is_active = 1
      AND role IN ('sales', 'super_admin', 'manager')
      AND (branch = ? OR role = 'super_admin')
    ORDER BY full_name
");
$stmt->execute([$user_branch]);
$salesPeople = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// PROCESS FORM SUBMISSION
// ============================================================

$error = "";
$success = "";
$device = null;
$returnDevice = null;
$clientDevice = null;
$search_sn = trim($_GET['sn'] ?? '');
$mode = $_GET['mode'] ?? $_POST['mode'] ?? 'instock';

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
}

// ============================================================
// LOAD DEVICE FOR INSTOCK MODE
// ============================================================
if ($mode === 'instock' && $search_sn) {
    $stmt = $conn->prepare("
        SELECT d.*, c.category_name
        FROM devices d
        JOIN categories c ON d.category_id = c.id
        WHERE d.serial_number COLLATE utf8mb4_general_ci = ? 
          AND d.status = 'In Stock' 
          AND d.branch = ?
    ");
    $stmt->execute([$search_sn, $user_branch]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        $error = "Device not found, not In Stock, or not in your branch.";
    }
}

// ============================================================
// LOAD DEVICE FOR RETURN MODE
// ============================================================
if ($mode === 'return' && $search_sn) {
    $stmt = $conn->prepare("
        SELECT d.*, c.category_name, 
               s.id as sale_id, s.client_name, s.client_phone, s.sold_by,
               u.full_name as sold_by_name
        FROM devices d
        JOIN categories c ON d.category_id = c.id
        LEFT JOIN sale_items si ON si.item_id COLLATE utf8mb4_general_ci = d.serial_number AND si.item_type = 'device'
        LEFT JOIN sales s ON s.id = si.sale_id
        LEFT JOIN users u ON u.id = s.sold_by
        WHERE d.serial_number COLLATE utf8mb4_general_ci = ? 
          AND d.status = 'Sold'
          AND d.branch = ?
        LIMIT 1
    ");
    $stmt->execute([$search_sn, $user_branch]);
    $returnDevice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$returnDevice) {
        $error = "Device not found, not Sold, or not in your branch.";
    }
}

// ============================================================
// LOAD DEVICE FOR CLIENT MODE (optional serial)
// ============================================================
if ($mode === 'client' && $search_sn) {
    $stmt = $conn->prepare("
        SELECT d.*, c.category_name
        FROM devices d
        JOIN categories c ON d.category_id = c.id
        WHERE d.serial_number COLLATE utf8mb4_general_ci = ? 
          AND d.branch = ?
    ");
    $stmt->execute([$search_sn, $user_branch]);
    $clientDevice = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ============================================================
// SAVE REPAIR - INSTOCK MODE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_repair_instock'])) {
    $serial = trim($_POST['serial'] ?? '');
    $problem = trim($_POST['problem_description'] ?? '');
    $given_by = (int) ($_POST['given_by'] ?? 0);

    if (!$serial || !$problem || !$given_by) {
        $error = "All fields are required.";
    } else {
        try {
            $conn->beginTransaction();

            // Get device details for category and model
            $stmt = $conn->prepare("SELECT category_id, model_name FROM devices WHERE serial_number COLLATE utf8mb4_general_ci = ?");
            $stmt->execute([$serial]);
            $dev = $stmt->fetch(PDO::FETCH_ASSOC);

            // Insert repair record with fix_status = 'pending' and source_device = 'instock'
            $insert = $conn->prepare("
                INSERT INTO repairs (
                    serial_number, problem_description, added_by, given_by, branch, 
                    fix_status, date_added, date_fixed, category_id, model_name,
                    client_name, client_phone, client_email, sales_person, parts_used, repair_cost,
                    source_device
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NULL, ?, ?, NULL, NULL, NULL, NULL, NULL, NULL, 'instock')
            ");
            $insert->execute([
                $serial, $problem, $user_id, $given_by, $user_branch,
                $dev['category_id'] ?? null, $dev['model_name'] ?? null
            ]);

            // Update device place to 'under_repair'
            $update = $conn->prepare("UPDATE devices SET place = 'under_repair' WHERE serial_number COLLATE utf8mb4_general_ci = ?");
            $update->execute([$serial]);

            // Get given_by name for log
            $givenByName = "Unknown";
            foreach ($givenByUsers as $u) {
                if ($u['id'] == $given_by) {
                    $givenByName = $u['full_name'];
                    break;
                }
            }

            // Activity log
            $log = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, details) 
                VALUES (?, 'Add Repair', ?)
            ");
            $log->execute([
                $user_id,
                "Added repair for device: $serial | Problem: $problem | Given By: $givenByName | Branch: $user_branch | Source: In Stock | Status: Pending"
            ]);

            $conn->commit();
            $success = "Device successfully added to repairs with Pending status.";
            $device = null;
            $search_sn = '';
            $_GET['sn'] = '';
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// ============================================================
// SAVE REPAIR - RETURN MODE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_repair_return'])) {
    $serial = trim($_POST['serial'] ?? '');
    $problem = trim($_POST['problem_description'] ?? '');
    // sales_person will be taken from the device's sold_by

    if (!$serial || !$problem) {
        $error = "Serial number and problem description are required.";
    } else {
        try {
            $conn->beginTransaction();

            // Get device details with sold_by
            $stmt = $conn->prepare("
                SELECT d.category_id, d.model_name, 
                       s.id as sale_id, s.client_name, s.client_phone, s.sold_by
                FROM devices d
                LEFT JOIN sale_items si ON si.item_id COLLATE utf8mb4_general_ci = d.serial_number AND si.item_type = 'device'
                LEFT JOIN sales s ON s.id = si.sale_id
                WHERE d.serial_number COLLATE utf8mb4_general_ci = ?
                LIMIT 1
            ");
            $stmt->execute([$serial]);
            $dev = $stmt->fetch(PDO::FETCH_ASSOC);

            // Insert repair record - fix_status = 'pending', source_device = 'return'
            $insert = $conn->prepare("
                INSERT INTO repairs (
                    serial_number, problem_description, added_by, given_by, branch, 
                    fix_status, date_added, date_fixed, category_id, model_name,
                    client_name, client_phone, client_email, sales_person, parts_used, repair_cost,
                    source_device
                ) VALUES (?, ?, ?, NULL, ?, 'pending', NOW(), NULL, ?, ?, ?, ?, NULL, ?, NULL, NULL, 'return')
            ");
            $insert->execute([
                $serial, $problem, $user_id, $user_branch,
                $dev['category_id'] ?? null, $dev['model_name'] ?? null,
                $dev['client_name'] ?? null, $dev['client_phone'] ?? null,
                $dev['sold_by'] ?? null
            ]);

            // Update device place to 'under_repair'
            $update = $conn->prepare("UPDATE devices SET place = 'under_repair' WHERE serial_number COLLATE utf8mb4_general_ci = ?");
            $update->execute([$serial]);

            // Activity log
            $log = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, details) 
                VALUES (?, 'Add Repair', ?)
            ");
            $log->execute([
                $user_id,
                "Added repair for returned device: $serial | Problem: $problem | Branch: $user_branch | Source: Return | Status: Pending"
            ]);

            $conn->commit();
            $success = "Returned device successfully added to repairs with Pending status.";
            $returnDevice = null;
            $search_sn = '';
            $_GET['sn'] = '';
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// ============================================================
// SAVE REPAIR - CLIENT MODE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_repair_client'])) {
    $serial = trim($_POST['serial'] ?? '');
    $model = trim($_POST['model_name'] ?? '');
    $category_id = (int) ($_POST['category_id'] ?? 0);
    $client_name = trim($_POST['client_name'] ?? '');
    $client_phone = trim($_POST['client_phone'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $problem = trim($_POST['problem_description'] ?? '');
    // No given_by and no sales_person for client mode

    if (!$model || !$category_id || !$client_name || !$client_phone || !$problem) {
        $error = "Model, category, client name, phone, and problem description are required.";
    } else {
        try {
            $conn->beginTransaction();

            // If serial provided, check if it exists and update place
            if ($serial) {
                $stmt = $conn->prepare("SELECT category_id FROM devices WHERE serial_number COLLATE utf8mb4_general_ci = ?");
                $stmt->execute([$serial]);
                $dev = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Update device place if exists
                if ($dev) {
                    $update = $conn->prepare("UPDATE devices SET place = 'under_repair' WHERE serial_number COLLATE utf8mb4_general_ci = ?");
                    $update->execute([$serial]);
                }
            }

            // Insert repair record - fix_status = 'pending', source_device = 'client'
            $insert = $conn->prepare("
                INSERT INTO repairs (
                    serial_number, problem_description, added_by, given_by, branch, 
                    fix_status, date_added, date_fixed, category_id, model_name,
                    client_name, client_phone, client_email, sales_person, parts_used, repair_cost,
                    source_device
                ) VALUES (?, ?, ?, NULL, ?, 'pending', NOW(), NULL, ?, ?, ?, ?, ?, NULL, NULL, NULL, 'client')
            ");
            $insert->execute([
                $serial ?: null, $problem, $user_id, $user_branch,
                $category_id, $model,
                $client_name, $client_phone, $client_email ?: null
            ]);

            // Activity log
            $log = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, details) 
                VALUES (?, 'Add Repair', ?)
            ");
            $log->execute([
                $user_id,
                "Added repair for client: $client_name | Device: $model | Category ID: $category_id | Problem: $problem | Branch: $user_branch | Source: Client | Status: Pending"
            ]);

            $conn->commit();
            $success = "Client device successfully added to repairs with Pending status.";
            $clientDevice = null;
            $search_sn = '';
            $_GET['sn'] = '';
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// ============================================================
// TIME GREETING
// ============================================================
date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';

// Helper function to build specs
function buildDeviceSpecs($device) {
    $specs = [];
    if (!empty($device['model_name'])) $specs[] = $device['model_name'];
    if (!empty($device['processor'])) $specs[] = $device['processor'];
    if (!empty($device['ram'])) $specs[] = $device['ram'] . 'GB RAM';
    if (!empty($device['storage_type']) && !empty($device['storage_capacity'])) {
        $specs[] = $device['storage_type'] . ' ' . $device['storage_capacity'] . 'GB';
    }
    if (!empty($device['graphics']) && $device['graphics'] !== 'None') {
        $specs[] = $device['graphics'];
    }
    return implode(' | ', $specs);
}

// Helper function to safely escape HTML
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=yes">
    <title>Add Repair | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- QR Code Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a7a3a;
            --primary-dark: #0f3a1e;
            --secondary: #1a4f6e;
            --secondary-light: #2563eb;
            --accent: #f59e0b;
            --accent-light: #fbbf24;
            --success: #10b981;
            --success-light: #34d399;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --purple: #8b5cf6;
            --pink: #ec4899;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -1px rgb(0 0 0 / 0.06);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -2px rgb(0 0 0 / 0.05);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 10px 10px -5px rgb(0 0 0 / 0.04);
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            --font-sans: 'Inter', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: var(--font-sans); 
            background: var(--gray-100);
            color: var(--gray-800); 
            line-height: 1.5; 
            overflow-x: hidden; 
            min-height: 100vh;
        }
        .main-content { 
            padding: 2rem 2rem 1rem; 
            margin-left: 260px; 
            width: calc(100% - 260px); 
            min-height: 100vh; 
            background: transparent;
            transition: all 0.3s ease; 
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
        .page-header .breadcrumb { 
            color: var(--gray-500); 
            font-size: 0.9rem;
        }
        .page-header .breadcrumb a { 
            color: var(--primary); 
            text-decoration: none;
        }
        .page-header .breadcrumb a:hover {
            text-decoration: underline;
        }
        .page-header .user-info {
            margin-top: 0.5rem;
            color: var(--gray-500);
            font-size: 0.85rem;
        }
        .page-header .user-info i {
            color: var(--primary);
        }
        
        .alert { 
            padding: 1rem 1.5rem; 
            border-radius: var(--radius-lg); 
            margin-bottom: 1.25rem; 
            display: flex; 
            align-items: center; 
            gap: 0.75rem; 
            font-weight: 500;
            box-shadow: var(--shadow-sm);
            border: 1px solid transparent;
        }
        .alert-success { 
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }
        .alert-error { 
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }
        .alert i { font-size: 1.25rem; }
        
        .card { 
            background: white; 
            border-radius: var(--radius-xl); 
            border: 1px solid var(--gray-200); 
            overflow: hidden; 
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            transition: box-shadow 0.2s ease;
        }
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        .card-header { 
            background: var(--gray-50);
            padding: 1rem 1.5rem; 
            border-bottom: 1px solid var(--gray-200);
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 0.75rem;
            font-size: 1rem;
            color: var(--gray-700);
        }
        .card-header i { 
            color: var(--primary);
            font-size: 1.1rem;
        }
        .card-body { padding: 1.5rem; }
        
        .mode-selector { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 1rem; 
            margin-bottom: 0.5rem; 
        }
        .mode-btn { 
            padding: 1.25rem 1rem; 
            border: 2px solid var(--gray-200); 
            border-radius: var(--radius-xl); 
            background: white; 
            cursor: pointer; 
            text-align: center; 
            transition: all 0.3s ease;
            font-family: var(--font-sans);
            font-weight: 500;
            color: var(--gray-600);
            text-decoration: none !important;
            display: block;
        }
        .mode-btn:hover { 
            border-color: var(--primary-light); 
            background: var(--gray-50);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .mode-btn.active { 
            border-color: var(--primary); 
            background: #dcfce7;
            color: var(--primary-dark);
            box-shadow: 0 0 0 4px rgba(26, 75, 42, 0.1);
        }
        .mode-btn i { 
            display: block; 
            font-size: 2rem; 
            margin-bottom: 0.4rem;
            transition: transform 0.3s ease;
        }
        .mode-btn.active i {
            transform: scale(1.1);
        }
        .mode-btn .mode-label { 
            font-size: 0.9rem; 
            font-weight: 600;
        }
        .mode-btn .mode-desc { 
            font-size: 0.65rem; 
            color: var(--gray-400); 
            font-weight: 400;
            margin-top: 0.1rem;
        }
        .mode-btn.active .mode-desc {
            color: var(--gray-500);
        }
        
        /* Color coding for mode buttons */
        .mode-btn[href*="instock"] i { color: var(--success); }
        .mode-btn[href*="return"] i { color: var(--warning); }
        .mode-btn[href*="client"] i { color: var(--info); }
        .mode-btn.active[href*="instock"] { border-color: var(--success); background: #dcfce7; }
        .mode-btn.active[href*="return"] { border-color: var(--warning); background: #fef3c7; }
        .mode-btn.active[href*="client"] { border-color: var(--info); background: #dbeafe; }
        
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { 
            display: block; 
            font-size: 0.85rem; 
            font-weight: 600; 
            margin-bottom: 0.4rem; 
            color: var(--gray-700);
        }
        .form-group label .required { 
            color: var(--danger); 
            margin-left: 0.2rem; 
        }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 0.75rem 1rem; 
            border: 1px solid var(--gray-300); 
            border-radius: var(--radius-md); 
            font-size: 0.9rem; 
            font-family: var(--font-sans);
            background: white;
            transition: border-color 0.2s ease;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }
        .form-group textarea { 
            resize: vertical; 
            min-height: 90px; 
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        
        .scan-input-wrapper { 
            display: flex; 
            gap: 0.75rem; 
            align-items: center; 
            flex-wrap: wrap; 
        }
        .scan-input-wrapper input { 
            flex: 1; 
            min-width: 200px; 
        }
        .scan-btn-mobile { 
            display: none;
            padding: 0.7rem 1.25rem; 
            background: var(--info);
            color: white; 
            border: none; 
            border-radius: var(--radius-md); 
            cursor: pointer; 
            font-family: var(--font-sans);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .scan-btn-mobile:hover { 
            background: #2563eb;
        }
        .scan-btn-mobile i { margin-right: 0.4rem; }
        
        .btn { 
            padding: 0.75rem 1.5rem; 
            border: none; 
            border-radius: var(--radius-md); 
            font-size: 0.9rem; 
            font-weight: 600; 
            cursor: pointer; 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem; 
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            text-decoration: none;
        }
        .btn-primary { 
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover { 
            background: var(--primary-light);
        }
        .btn-success { 
            background: var(--success);
            color: white;
        }
        .btn-success:hover { 
            background: #059669;
        }
        .btn-warning { 
            background: var(--warning);
            color: white;
        }
        .btn-warning:hover { 
            background: #d97706;
        }
        .btn-danger { 
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover { 
            background: #dc2626;
        }
        .btn-secondary { 
            background: var(--gray-500);
            color: white;
        }
        .btn-secondary:hover { 
            background: var(--gray-600);
        }
        .btn-sm { padding: 0.4rem 1rem; font-size: 0.8rem; }
        .btn-block { width: 100%; justify-content: center; }
        
        .info-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 0.75rem; 
            margin-bottom: 1.25rem; 
        }
        .info-item { 
            background: var(--gray-50);
            padding: 0.75rem 1rem; 
            border-radius: var(--radius-lg); 
            border: 1px solid var(--gray-200);
        }
        .info-item .label { 
            font-size: 0.6rem; 
            font-weight: 700; 
            color: var(--gray-500); 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }
        .info-item .value { 
            font-size: 0.9rem; 
            font-weight: 500; 
            color: var(--gray-800); 
            margin-top: 0.1rem;
        }
        .info-item .value code {
            background: var(--gray-100);
            padding: 0.1rem 0.4rem;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
        }
        
        .found-device-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            background: #dcfce7;
            color: #065f46;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
        .found-device-badge i {
            color: var(--success);
        }
        
        .footer { 
            text-align: center; 
            padding: 1.5rem 0 0.5rem; 
            margin-top: 1.5rem; 
            font-size: 0.85rem; 
            color: var(--gray-400); 
            border-top: 1px solid var(--gray-200);
        }
        .footer span {
            color: var(--primary);
        }
        
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
            padding: 2rem;
            max-width: 500px;
            width: 95%;
            text-align: center;
            position: relative;
            box-shadow: var(--shadow-xl);
        }
        .scanner-box .close-btn {
            position: absolute;
            top: 10px; right: 15px;
            font-size: 1.8rem;
            cursor: pointer;
            background: none;
            border: none;
            color: var(--gray-400);
            transition: color 0.2s ease;
        }
        .scanner-box .close-btn:hover { 
            color: var(--gray-800);
        }
        .scanner-box h3 {
            color: var(--gray-800);
            font-weight: 700;
        }
        .scanner-box #reader {
            width: 100%;
            min-height: 250px;
            margin: 0.75rem 0;
        }
        .scanner-box p { 
            color: var(--gray-500); 
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }
        
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header { padding: 1.25rem 1.25rem; }
            .page-header h1 { font-size: 1.4rem; }
            .mode-selector { grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem; }
            .mode-btn { padding: 0.75rem 0.5rem; }
            .mode-btn i { font-size: 1.5rem; }
            .mode-btn .mode-label { font-size: 0.7rem; }
            .mode-btn .mode-desc { font-size: 0.55rem; }
            .form-row { grid-template-columns: 1fr; gap: 0.75rem; }
            .card-body { padding: 1.25rem; }
            .info-grid { grid-template-columns: 1fr 1fr; }
            .scan-btn-mobile { display: inline-flex; font-size: 0.8rem; padding: 0.6rem 1rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header { padding: 1rem 1rem; }
            .page-header h1 { font-size: 1.2rem; }
            .mode-selector { grid-template-columns: 1fr; gap: 0.4rem; }
            .mode-btn { 
                padding: 0.7rem 0.75rem; 
                display: flex; 
                align-items: center; 
                gap: 0.75rem; 
                text-align: left;
            }
            .mode-btn i { 
                display: inline-block; 
                font-size: 1.2rem; 
                margin-bottom: 0;
                width: 30px;
                text-align: center;
            }
            .mode-btn .mode-label { font-size: 0.8rem; }
            .mode-btn .mode-desc { font-size: 0.6rem; }
            .info-grid { grid-template-columns: 1fr; }
            .scanner-box { max-width: 100%; border-radius: 0; height: 100vh; padding: 2rem 1.25rem; }
            .scanner-box #reader { height: 60vh; min-height: 200px; }
            .scan-btn-mobile { display: inline-flex; width: auto; font-size: 0.75rem; padding: 0.5rem 0.8rem; }
        }
        
        @media (min-width: 769px) {
            .scan-btn-mobile { display: none !important; }
        }
        
        /* Simple animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card, .alert { animation: fadeIn 0.3s ease-out forwards; }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-tools"></i> Add Repair</h1>
            <div class="breadcrumb">
                <a href="/inventory_system/dashboard/techniciandashboard.php">Dashboard</a>
                <span> / </span>
                <a href="repair_logs.php">Repairs</a>
                <span> / </span>
                <span>Add Repair</span>
            </div>
            <div class="user-info">
                <i class="fas fa-store"></i> Branch: <?= safe($user_branch) ?> &nbsp;&nbsp;|&nbsp;&nbsp;
                <i class="fas fa-user"></i> <?= safe($greeting) ?>, <?= safe(explode(' ', $user_name)[0]) ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= safe($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= safe($success) ?></div>
        <?php endif; ?>

        <!-- Mode Selector -->
        <div class="card">
            <div class="card-header"><i class="fas fa-route"></i> Select Repair Source</div>
            <div class="card-body">
                <div class="mode-selector">
                    <a href="?mode=instock" class="mode-btn <?= $mode === 'instock' ? 'active' : '' ?>">
                        <i class="fas fa-warehouse"></i>
                        <div>
                            <div class="mode-label">In Stock Device</div>
                            <div class="mode-desc">From inventory</div>
                        </div>
                    </a>
                    <a href="?mode=return" class="mode-btn <?= $mode === 'return' ? 'active' : '' ?>">
                        <i class="fas fa-undo-alt"></i>
                        <div>
                            <div class="mode-label">Return Device</div>
                            <div class="mode-desc">Sold &amp; returned</div>
                        </div>
                    </a>
                    <a href="?mode=client" class="mode-btn <?= $mode === 'client' ? 'active' : '' ?>">
                        <i class="fas fa-user"></i>
                        <div>
                            <div class="mode-label">Client Device</div>
                            <div class="mode-desc">Walk-in client</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODE: INSTOCK -->
        <!-- ============================================================ -->
        <?php if ($mode === 'instock'): ?>
        
        <div class="card">
            <div class="card-header"><i class="fas fa-search"></i> Find Device</div>
            <div class="card-body">
                <form method="GET" style="margin-bottom:0;">
                    <input type="hidden" name="mode" value="instock">
                    <div class="form-group">
                        <label>Serial Number <span class="required">*</span></label>
                        <div class="scan-input-wrapper">
                            <input type="text" id="serial_search_instock" name="sn" placeholder="Scan or enter serial number" value="<?= safe($search_sn) ?>" autofocus>
                            <button type="button" class="scan-btn-mobile" onclick="openScanner('serial_search_instock')">
                                <i class="fas fa-camera"></i> Scan
                            </button>
                        </div>
                        <small style="color:var(--gray-400); font-size:0.75rem;"><i class="fas fa-info-circle"></i> Scan QR/barcode using your phone camera</small>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Load Device</button>
                </form>
            </div>
        </div>

        <?php if ($device): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-check-circle" style="color:var(--success);"></i> Device Found - In Stock</div>
            <div class="card-body">
                <div class="found-device-badge"><i class="fas fa-check-circle"></i> Device is available in inventory</div>
                
                <div class="info-grid">
                    <div class="info-item"><div class="label">Serial</div><div class="value"><code><?= safe($device['serial_number']) ?></code></div></div>
                    <div class="info-item"><div class="label">Category</div><div class="value"><?= safe($device['category_name']) ?></div></div>
                    <div class="info-item"><div class="label">Model</div><div class="value"><?= safe($device['model_name']) ?></div></div>
                    <div class="info-item"><div class="label">Specs</div><div class="value" style="font-size:0.8rem;"><?= safe(buildDeviceSpecs($device)) ?></div></div>
                    <div class="info-item"><div class="label">Processor</div><div class="value"><?= safe($device['processor']) ?></div></div>
                    <div class="info-item"><div class="label">RAM</div><div class="value"><?= safe($device['ram']) ?> GB</div></div>
                    <div class="info-item"><div class="label">Storage</div><div class="value"><?= safe($device['storage_type'] . ' ' . $device['storage_capacity'] . 'GB') ?></div></div>
                    <div class="info-item"><div class="label">Graphics</div><div class="value"><?= safe($device['graphics'] ?? 'N/A') ?></div></div>
                    <div class="info-item"><div class="label">Touch</div><div class="value"><?= safe($device['touch'] ?? 'N/A') ?></div></div>
                    <div class="info-item"><div class="label">Branch</div><div class="value"><?= safe($device['branch']) ?></div></div>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="mode" value="instock">
                    <input type="hidden" name="serial" value="<?= safe($device['serial_number']) ?>">
                    
                    <div class="form-group">
                        <label>Given By (Who handed over the device?) <span class="required">*</span></label>
                        <select name="given_by" required>
                            <option value="">-- Select Person --</option>
                            <?php foreach ($givenByUsers as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= safe($u['full_name']) ?> (<?= safe($u['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Problem Description <span class="required">*</span></label>
                        <textarea name="problem_description" rows="4" required placeholder="Describe the issue in detail..."></textarea>
                    </div>
                    
                    <button type="submit" name="save_repair_instock" class="btn btn-success btn-block">
                        <i class="fas fa-save"></i> Add to Repairs (Pending)
                    </button>
                </form>
            </div>
        </div>
        <?php elseif ($search_sn !== ''): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Device not found. Make sure the serial exists and is In Stock in your branch.</div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- MODE: RETURN -->
        <!-- ============================================================ -->
        <?php elseif ($mode === 'return'): ?>
        
        <div class="card">
            <div class="card-header"><i class="fas fa-search"></i> Find Returned Device</div>
            <div class="card-body">
                <form method="GET" style="margin-bottom:0;">
                    <input type="hidden" name="mode" value="return">
                    <div class="form-group">
                        <label>Serial Number <span class="required">*</span></label>
                        <div class="scan-input-wrapper">
                            <input type="text" id="serial_search_return" name="sn" placeholder="Scan or enter serial number" value="<?= safe($search_sn) ?>" autofocus>
                            <button type="button" class="scan-btn-mobile" onclick="openScanner('serial_search_return')">
                                <i class="fas fa-camera"></i> Scan
                            </button>
                        </div>
                        <small style="color:var(--gray-400); font-size:0.75rem;"><i class="fas fa-info-circle"></i> Search for devices that were sold and returned</small>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Load Device</button>
                </form>
            </div>
        </div>

        <?php if ($returnDevice): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-undo-alt" style="color:var(--warning);"></i> Returned Device Found</div>
            <div class="card-body">
                <div class="found-device-badge" style="background:#fef3c7; color:#92400e;"><i class="fas fa-undo-alt"></i> Device was previously sold</div>
                
                <div class="info-grid">
                    <div class="info-item"><div class="label">Serial</div><div class="value"><code><?= safe($returnDevice['serial_number']) ?></code></div></div>
                    <div class="info-item"><div class="label">Category</div><div class="value"><?= safe($returnDevice['category_name']) ?></div></div>
                    <div class="info-item"><div class="label">Model</div><div class="value"><?= safe($returnDevice['model_name']) ?></div></div>
                    <div class="info-item"><div class="label">Specs</div><div class="value" style="font-size:0.8rem;"><?= safe(buildDeviceSpecs($returnDevice)) ?></div></div>
                    <div class="info-item"><div class="label">Client</div><div class="value"><?= safe($returnDevice['client_name'] ?? 'N/A') ?></div></div>
                    <div class="info-item"><div class="label">Client Phone</div><div class="value"><?= safe($returnDevice['client_phone'] ?? 'N/A') ?></div></div>
                    <div class="info-item"><div class="label">Sale ID</div><div class="value">#<?= safe($returnDevice['sale_id'] ?? 'N/A') ?></div></div>
                    <div class="info-item"><div class="label">Sold By</div><div class="value">
                        <?php if (!empty($returnDevice['sold_by_name'])): ?>
                            <strong><?= safe($returnDevice['sold_by_name']) ?></strong>
                        <?php else: ?>
                            <?= safe($returnDevice['sold_by'] ?? 'N/A') ?>
                        <?php endif; ?>
                    </div></div>
                    <div class="info-item"><div class="label">Branch</div><div class="value"><?= safe($returnDevice['branch']) ?></div></div>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="mode" value="return">
                    <input type="hidden" name="serial" value="<?= safe($returnDevice['serial_number']) ?>">
                    
                    <div style="margin-bottom:1rem; padding:0.75rem 1rem; background:#fef3c7; border-radius:var(--radius-lg); border:1px solid #fde68a;">
                        <p style="font-weight:600; color:#92400e;"><i class="fas fa-user"></i> Sales Person: <strong><?= !empty($returnDevice['sold_by_name']) ? safe($returnDevice['sold_by_name']) : 'Unknown' ?></strong></p>
                        <p style="font-size:0.8rem; color:#92400e; margin-top:0.25rem;"><i class="fas fa-info-circle"></i> This person will be recorded as the sales person for this repair</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Problem Description <span class="required">*</span></label>
                        <textarea name="problem_description" rows="4" required placeholder="Describe the issue in detail..."></textarea>
                    </div>
                    
                    <button type="submit" name="save_repair_return" class="btn btn-warning btn-block">
                        <i class="fas fa-save"></i> Add Returned Device to Repairs (Pending)
                    </button>
                </form>
            </div>
        </div>
        <?php elseif ($search_sn !== ''): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Device not found. Make sure the serial exists, is Sold, and in your branch.</div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- MODE: CLIENT -->
        <!-- ============================================================ -->
        <?php elseif ($mode === 'client'): ?>
        
        <div class="card">
            <div class="card-header"><i class="fas fa-user"></i> Client Device Details</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="mode" value="client">
                    
                    <div class="form-group">
                        <label>Serial Number <span style="color:var(--gray-400); font-weight:400;">(optional)</span></label>
                        <div class="scan-input-wrapper">
                            <input type="text" id="serial_search_client" name="serial" placeholder="Scan or enter serial number (optional)" value="<?= safe($_POST['serial'] ?? $search_sn) ?>">
                            <button type="button" class="scan-btn-mobile" onclick="openScanner('serial_search_client')">
                                <i class="fas fa-camera"></i> Scan
                            </button>
                        </div>
                        <small style="color:var(--gray-400); font-size:0.75rem;"><i class="fas fa-info-circle"></i> If the device exists in our system, its specs will be auto-filled</small>
                    </div>
                    
                    <?php if ($clientDevice): ?>
                    <div style="margin-bottom:1.25rem; padding:1rem 1.25rem; background:#ecfdf5; border-radius:var(--radius-xl); border:1px solid #a7f3d0;">
                        <p style="font-weight:600; color:#065f46;"><i class="fas fa-check-circle"></i> Device found in system</p>
                        <div class="info-grid" style="margin-top:0.5rem;">
                            <div class="info-item"><div class="label">Category</div><div class="value"><?= safe($clientDevice['category_name']) ?></div></div>
                            <div class="info-item"><div class="label">Model</div><div class="value"><?= safe($clientDevice['model_name']) ?></div></div>
                            <div class="info-item"><div class="label">Specs</div><div class="value" style="font-size:0.8rem;"><?= safe(buildDeviceSpecs($clientDevice)) ?></div></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Device Category <span class="required">*</span></label>
                        <select name="category_id" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                    <?= safe($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Device Model <span class="required">*</span></label>
                        <input type="text" name="model_name" required placeholder="e.g. HP EliteBook 840 G6" value="<?= safe($clientDevice['model_name'] ?? $_POST['model_name'] ?? '') ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Client Name <span class="required">*</span></label>
                            <input type="text" name="client_name" required placeholder="Full name" value="<?= safe($_POST['client_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Client Phone <span class="required">*</span></label>
                            <input type="tel" name="client_phone" required placeholder="Phone number" value="<?= safe($_POST['client_phone'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Client Email <span style="color:var(--gray-400); font-weight:400;">(optional)</span></label>
                        <input type="email" name="client_email" placeholder="email@example.com" value="<?= safe($_POST['client_email'] ?? '') ?>">
                        <small style="color:var(--gray-400); font-size:0.75rem;"><i class="fas fa-info-circle"></i> Used to notify client when repair is completed</small>
                    </div>
                    
                    <div style="margin-bottom:1.25rem; padding:0.75rem 1rem; background:#dbeafe; border-radius:var(--radius-lg); border:1px solid #bfdbfe;">
                        <p style="font-weight:600; color:#1e40af;"><i class="fas fa-info-circle"></i> Client device - No sales person or giver recorded</p>
                        <p style="font-size:0.8rem; color:#1e40af; margin-top:0.25rem;">The device belongs to a walk-in client</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Problem Description <span class="required">*</span></label>
                        <textarea name="problem_description" rows="4" required placeholder="Describe the issue in detail..."><?= safe($_POST['problem_description'] ?? '') ?></textarea>
                    </div>
                    
                    <button type="submit" name="save_repair_client" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Add Client Device to Repairs (Pending)
                    </button>
                </form>
            </div>
        </div>

        <?php endif; ?>

        <div class="footer">
            <i class="fas fa-copyright"></i> <?= date('Y'); ?> <span>Mombasa Computers</span>. All rights reserved.
            <span style="margin:0 0.5rem;">•</span>
            <span>v2.0.0</span>
        </div>
    </div>

    <!-- Scanner Overlay Modal -->
    <div class="scanner-overlay" id="scannerOverlay">
        <div class="scanner-box">
            <button class="close-btn" onclick="closeScanner()">&times;</button>
            <h3><i class="fas fa-camera" style="color:var(--primary);"></i> Scan Serial Number</h3>
            <p>Position the barcode/QR code in front of the camera</p>
            <div id="reader"></div>
            <button class="btn btn-secondary btn-block" onclick="closeScanner()" style="margin-top:0.75rem;">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>

    <script>
        let html5QrCode = null;
        let scannerActive = false;
        let currentTargetInput = null;

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
            } catch (e) { /* Beep not supported */ }
        }

        function openScanner(inputId) {
            currentTargetInput = inputId;
            const overlay = document.getElementById('scannerOverlay');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            setTimeout(() => {
                if (!scannerActive) {
                    startScanner();
                }
            }, 500);
        }

        function closeScanner() {
            const overlay = document.getElementById('scannerOverlay');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            if (html5QrCode && scannerActive) {
                html5QrCode.stop().then(() => {
                    html5QrCode.clear();
                    scannerActive = false;
                }).catch(() => { scannerActive = false; });
            }
            currentTargetInput = null;
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
            readerElement.style.minHeight = '250px';

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
            if (currentTargetInput) {
                const input = document.getElementById(currentTargetInput);
                if (input) {
                    input.value = decodedText;
                    input.dispatchEvent(new Event('input'));
                    // Auto-submit the form for instock/return modes
                    const form = input.closest('form');
                    if (form && !form.querySelector('input[name="save_repair_client"]')) {
                        setTimeout(() => form.submit(), 400);
                    }
                }
            }
            closeScanner();
        }

        function onScanError(errorMessage) { /* Ignore errors during scanning */ }

        // Clean up scanner on page unload
        window.addEventListener('beforeunload', function() {
            if (html5QrCode && scannerActive) {
                html5QrCode.stop().catch(() => {});
            }
        });

        // Mobile sidebar adjustment
        function adjustMainContent() {
            const main = document.querySelector('.main-content');
            if (window.innerWidth <= 1200) {
                main.style.marginLeft = '0';
                main.style.width = '100%';
                main.style.paddingTop = '5rem';
            } else {
                main.style.marginLeft = '260px';
                main.style.width = 'calc(100% - 260px)';
                main.style.paddingTop = '';
            }
        }
        window.addEventListener('resize', adjustMainContent);
        window.addEventListener('sidebarToggled', adjustMainContent);
        adjustMainContent();
    </script>

    <?php require_once "../includes/footer.php"; ?>
</body>
</html>