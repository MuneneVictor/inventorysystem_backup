<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// ============================================================
// ALL PHP PROCESSING (before any HTML output)
// ============================================================

// --- Handle AJAX requests to store/clear sales person in session ---
if (isset($_POST['ajax_set_sales_person'])) {
    $salesPersonId = isset($_POST['sales_person']) ? (int)$_POST['sales_person'] : 0;
    if ($salesPersonId > 0) {
        $_SESSION['give_out_sales_person'] = $salesPersonId;
    } else {
        unset($_SESSION['give_out_sales_person']);
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

if (isset($_POST['ajax_clear_sales_person'])) {
    unset($_SESSION['give_out_sales_person']);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Restrict access
if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED. Only Inventory Admin, Manager, or Super Admin can give out devices.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_branch = '';

// Get user's branch (if not super_admin)
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
    if (!$user_branch) die("Your account has no branch assigned.");
}

// For super_admin and manager: show both branches (MOI and KIMATHI)
$branches_list = ['MOI', 'KIMATHI'];
$selected_branch = $user_branch;
if (in_array($user_role, ['super_admin', 'manager'])) {
    if (!empty($user_branch) && in_array($user_branch, $branches_list)) {
        $selected_branch = $user_branch;
    } else {
        $selected_branch = $branches_list[0];
    }
    if (isset($_GET['branch']) && in_array($_GET['branch'], $branches_list)) {
        $selected_branch = $_GET['branch'];
    } elseif (isset($_POST['branch']) && in_array($_POST['branch'], $branches_list)) {
        $selected_branch = $_POST['branch'];
    }
} else {
    $selected_branch = $user_branch;
}

// --- CSRF Token ---
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
$csrf_token = generateCSRFToken();

// --- Helper: build specs string ---
function buildDeviceSpecs($device) {
    $specs = "";
    if (!empty($device['model_name'])) $specs .= $device['model_name'];
    if (!empty($device['processor'])) $specs .= " | " . $device['processor'];
    if (!empty($device['ram'])) $specs .= " | " . $device['ram'] . "GB RAM";
    if (!empty($device['storage_type']) && !empty($device['storage_capacity'])) {
        $specs .= " | " . $device['storage_type'] . " " . $device['storage_capacity'] . "GB";
    }
    if (isset($device['graphics']) && $device['graphics'] !== '' && $device['graphics'] !== 'None') {
        $specs .= " | " . $device['graphics'];
    }
    if (isset($device['touch']) && $device['touch'] !== 'N/A' && $device['touch'] !== '') {
        $specs .= " | " . $device['touch'];
    }
    return trim($specs, " |");
}

$error = "";
$success = "";
$foundDevices = [];
$notFoundSerials = [];
$action = "";
$salesUsers = [];

// Fetch sales users in selected branch (for give-out)
$stmt = $conn->prepare("SELECT id, full_name FROM users WHERE role = 'sales' AND branch = ? ORDER BY full_name");
$stmt->execute([$selected_branch]);
$salesUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- SEARCH ---
if (isset($_POST['search_serial'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token. Please refresh the page.";
    } else {
        $input = trim($_POST['serial_number']);
        $action = trim($_POST['action'] ?? '');
        $branch = trim($_POST['branch'] ?? $selected_branch);
        if (empty($input)) {
            $error = "Please enter serial number(s).";
        } elseif (!in_array($action, ['take_to_display', 'give_out'])) {
            $error = "Please select a valid action.";
        } else {
            $serials = preg_split('/[\s,]+/', $input);
            $serials = array_filter(array_map('trim', $serials));
            if (empty($serials)) {
                $error = "No valid serial numbers found.";
            } else {
                $placeholders = implode(',', array_fill(0, count($serials), '?'));
                $sql = "SELECT d.*, c.category_name 
                        FROM devices d
                        JOIN categories c ON d.category_id = c.id
                        WHERE d.serial_number IN ($placeholders)
                          AND d.status = 'In Stock'
                          AND d.place = 'store'
                          AND d.branch = ?";
                $params = array_merge($serials, [$branch]);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $foundDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $foundSerials = array_column($foundDevices, 'serial_number');
                $notFoundSerials = array_diff($serials, $foundSerials);

                if (empty($foundDevices)) {
                    $error = "No devices found. Ensure they are in stock, in the store, and in the selected branch.";
                }
            }
        }
    }
}

// --- CONFIRM ACTION ---
if (isset($_POST['confirm_action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token. Please refresh the page.";
    } else {
        $selectedSerials = $_POST['selected_serials'] ?? [];
        $action = trim($_POST['action'] ?? '');
        $branch = trim($_POST['branch'] ?? $selected_branch);
        if (empty($selectedSerials)) {
            $error = "No devices selected.";
        } elseif (!in_array($action, ['take_to_display', 'give_out'])) {
            $error = "Invalid action.";
        } else {
            // Get sales person from session (set via AJAX on dropdown change)
            $sales_person_id = $_SESSION['give_out_sales_person'] ?? 0;

            if ($action === 'give_out') {
                if ($sales_person_id <= 0 && !empty($salesUsers)) {
                    $error = "Please select a sales person.";
                } elseif ($sales_person_id <= 0 && empty($salesUsers)) {
                    $error = "No sales users available in this branch.";
                }
            }

            // Fetch salesperson name once if give_out
            $salesperson_name = '';
            if ($action === 'give_out' && $sales_person_id > 0) {
                $stmtName = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
                $stmtName->execute([$sales_person_id]);
                $salesperson_name = $stmtName->fetchColumn();
                if (!$salesperson_name) {
                    $salesperson_name = "ID $sales_person_id"; // fallback
                }
            }

            if (!$error) {
                $conn->beginTransaction();
                try {
                    $processed = 0;
                    foreach ($selectedSerials as $serial) {
                        $stmt = $conn->prepare("SELECT * FROM devices WHERE serial_number = ? AND status = 'In Stock' AND place = 'store' AND branch = ?");
                        $stmt->execute([$serial, $branch]);
                        $device = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!$device) {
                            continue;
                        }

                        // Update place to 'display' for BOTH actions
                        $update = $conn->prepare("UPDATE devices SET place = 'display' WHERE serial_number = ?");
                        $update->execute([$serial]);

                        // Build specs for logging
                        $specs = buildDeviceSpecs($device);

                        $logSql = "INSERT INTO devices_logs 
                                    (serial_number, category_id, model_name, processor, ram, storage_type, storage_capacity, graphics, touch, device_condition, branch, cargo_number, action";
                        $logParams = [
                            $serial,
                            $device['category_id'],
                            $device['model_name'],
                            $device['processor'],
                            $device['ram'],
                            $device['storage_type'],
                            $device['storage_capacity'],
                            $device['graphics'] ?? 'None',
                            $device['touch'] ?? 'N/A',
                            $device['device_condition'] ?? 'Ex-Uk',
                            $device['branch'],
                            $device['cargo_number'] ?? 'NO CARGO',
                            $action
                        ];

                        if ($action === 'take_to_display') {
                            $logSql .= ", taken_by, date_taken) VALUES (" . implode(',', array_fill(0, count($logParams), '?')) . ", ?, NOW())";
                            $logParams[] = $user_id;
                        } else { // give_out
                            $logSql .= ", given_by, given_to, date_given) VALUES (" . implode(',', array_fill(0, count($logParams), '?')) . ", ?, ?, NOW())";
                            $logParams[] = $user_id;
                            $logParams[] = $sales_person_id;
                        }

                        $stmtLog = $conn->prepare($logSql);
                        $stmtLog->execute($logParams);

                        // Activity log - now using salesperson name instead of ID
                        $activityDetails = ($action === 'take_to_display')
                            ? "Took device SN: $serial to display (Branch: $branch)"
                            : "Gave device SN: $serial to salesperson $salesperson_name (Branch: $branch)";
                        $stmtAct = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
                        $stmtAct->execute([$user_id, ($action === 'take_to_display' ? 'Took to display' : 'Give out device'), $activityDetails]);

                        $processed++;
                    }

                    $conn->commit();
                    $success = "Action completed successfully! $processed device(s) processed.";
                    // Clear session sales person after successful give-out
                    unset($_SESSION['give_out_sales_person']);
                    $foundDevices = [];
                    $notFoundSerials = [];
                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = "Error: " . $e->getMessage();
                }
            }
        }
    }
}

// Re-generate CSRF token after processing
$csrf_token = generateCSRFToken();

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
    <title>Give Out Device | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
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
        .form-group textarea, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); background: white; font-family: inherit; }
        .form-group select { appearance: auto; }
        .btn { padding: 0.75rem 1.5rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; justify-content: center; }
        .btn-secondary { background: var(--gray-500); }
        .btn-primary { background: var(--primary); }
        .btn-primary:hover { background: #2a6b3a; }
        .scan-btn { background: #2563eb; color: white; border: none; padding: 0.5rem 1rem; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; width: auto; }
        .scan-btn:hover { background: #1d4ed8; }
        .alert { padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--gray-200); }
        th { background: var(--gray-50); }
        .checkbox-cell { text-align: center; }
        .specs-text { font-size: 0.8rem; color: var(--gray-600); display: block; white-space: normal; overflow: visible; text-overflow: clip; max-width: 100%; word-break: break-word; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-top: 0.5rem; }
        .table-responsive table { min-width: 600px; }
        .radio-group { display: flex; gap: 1.5rem; margin-top: 0.5rem; }
        .radio-group label { display: flex; align-items: center; gap: 0.5rem; font-weight: normal; cursor: pointer; }
        .radio-group input[type="radio"] { accent-color: var(--primary); width: 18px; height: 18px; }
        .scan-btn-wrapper { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .hidden { display: none; }

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

        @media (min-width: 1025px) {
            .scan-btn-wrapper .scan-btn {
                display: none !important;
            }
        }
        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }
        @media (max-width: 768px) {
            .card-body { padding: 1rem; }
            .radio-group { flex-direction: column; gap: 0.75rem; }
            .scanner-box { padding: 1rem; }
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
        <h1><i class="fas fa-exchange-alt"></i> Give Out Device</h1>
        <div class="breadcrumb">
            <a href="/inventory_system/dashboard/<?= $user_role === 'super_admin' ? 'superadmindashboard.php' : ($user_role === 'manager' ? 'managerdashboard.php' : 'inventorydashboard.php') ?>">Dashboard</a>
            <span> / </span>
            <span>Give Out Device</span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><i class="fas fa-cogs"></i> Select Action & Branch</div>
        <div class="card-body">
            <form method="POST" id="actionForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <?php if (in_array($user_role, ['super_admin', 'manager'])): ?>
                <div class="form-group">
                    <label>Branch</label>
                    <select name="branch" id="branchSelect" onchange="this.form.submit()">
                        <?php foreach ($branches_list as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>" <?= $selected_branch == $b ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:var(--gray-500);">Changing branch will reset the search.</small>
                </div>
                <?php else: ?>
                    <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch) ?>">
                    <div style="margin-bottom:1rem; padding:0.5rem 1rem; background:var(--gray-50); border-radius:var(--radius-md); border-left:4px solid var(--primary);">
                        <strong>Branch:</strong> <?= htmlspecialchars($selected_branch) ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Action</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="action" value="take_to_display" required <?= ($action == 'take_to_display' || !$action) ? 'checked' : '' ?> onchange="toggleSalesPerson()">
                            Take to Display
                        </label>
                        <label>
                            <input type="radio" name="action" value="give_out" required <?= $action == 'give_out' ? 'checked' : '' ?> onchange="toggleSalesPerson()">
                            Give Out to Sales Person
                        </label>
                    </div>
                </div>
                <div class="form-group" id="salesPersonGroup" style="<?= ($action == 'give_out') ? '' : 'display:none;' ?>">
                    <label>Sales Person <span class="required">*</span></label>
                    <select name="sales_person" id="sales_person">
                        <option value="">-- Select Sales Person --</option>
                        <?php 
                        $selectedSalesId = $_SESSION['give_out_sales_person'] ?? 0;
                        foreach ($salesUsers as $u): 
                        ?>
                            <option value="<?= $u['id'] ?>" <?= ($selectedSalesId == $u['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($salesUsers)): ?>
                            <option value="" disabled selected>No sales users available in this branch</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($salesUsers)): ?>
                        <div style="margin-top:0.5rem; font-size:0.85rem; color:var(--gray-500);">
                            <i class="fas fa-info-circle"></i> No sales users found in <?= htmlspecialchars($selected_branch) ?> branch. You can still take devices to display.
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="fas fa-search"></i> Search Devices (from Store)</div>
        <div class="card-body">
            <form method="POST" id="searchForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" id="actionHidden" value="<?= htmlspecialchars($action) ?>">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch) ?>">
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
                <button type="submit" name="search_serial" class="btn btn-primary"><i class="fas fa-search"></i> Search Device(s)</button>
                <button type="reset" class="btn btn-secondary" style="margin-left:0.5rem;"><i class="fas fa-undo"></i> Clear</button>
            </form>
            <?php if (!empty($notFoundSerials)): ?>
                <div class="alert alert-error" style="margin-top:1rem;"><strong>Not Found / Not Eligible:</strong> <?= implode(', ', $notFoundSerials) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($foundDevices)): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-list"></i> Found Devices (<?= count($foundDevices) ?>)</div>
            <div class="card-body">
                <form method="POST" id="confirmForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" id="actionConfirm" value="<?= htmlspecialchars($action) ?>">
                    <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch) ?>">
                    <p><input type="checkbox" id="selectAll" onchange="selectAllCheckboxes(this)"> <label for="selectAll">Select All</label></p>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th class="checkbox-cell">Select</th>
                                    <th>#</th>
                                    <th>Serial</th>
                                    <th>Specifications</th>
                                    <th>Condition</th>
                                    <th>Branch</th>
                                    <th>Cargo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; foreach ($foundDevices as $d): ?>
                                <tr>
                                    <td class="checkbox-cell"><input type="checkbox" name="selected_serials[]" value="<?= htmlspecialchars($d['serial_number']) ?>" checked></td>
                                    <td><?= $counter++ ?></td>
                                    <td><code><?= htmlspecialchars($d['serial_number']) ?></code></td>
                                    <td>
                                        <span class="specs-text" title="<?= htmlspecialchars(buildDeviceSpecs($d)) ?>">
                                            <?= htmlspecialchars(buildDeviceSpecs($d)) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($d['device_condition'] ?? 'Ex-Uk') ?></td>
                                    <td><?= htmlspecialchars($d['branch']) ?></td>
                                    <td><?= htmlspecialchars($d['cargo_number'] ?? 'NO CARGO') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" name="confirm_action" class="btn btn-primary" style="margin-top:1rem;">
                        <i class="fas fa-check-circle"></i> Confirm Action
                    </button>
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
        if (audioCtx.state === 'suspended') { audioCtx.resume(); }
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
    } catch (e) { /* ignore */ }
}

function openScanner() {
    const overlay = document.getElementById('scannerOverlay');
    overlay.classList.add('active');
    setTimeout(() => {
        if (!scannerActive) { startScanner(); }
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

// --- AJAX to store sales person in session on dropdown change ---
function updateSalesPersonInSession(salesPersonId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_set_sales_person=1&sales_person=' + encodeURIComponent(salesPersonId)
    }).then(response => response.json()).then(data => {
        if (!data.success) {
            console.warn('Failed to update sales person in session');
        }
    }).catch(error => console.error('Error updating sales person:', error));
}

function clearSalesPersonInSession() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_clear_sales_person=1'
    }).then(response => response.json()).then(data => {
        if (!data.success) {
            console.warn('Failed to clear sales person in session');
        }
    }).catch(error => console.error('Error clearing sales person:', error));
}

// Attach change event to sales_person dropdown
document.addEventListener('DOMContentLoaded', function() {
    const salesSelect = document.getElementById('sales_person');
    if (salesSelect) {
        salesSelect.addEventListener('change', function() {
            const selectedValue = this.value;
            updateSalesPersonInSession(selectedValue);
        });
    }

    // When action radio changes, if not 'give_out', clear the session sales person
    const actionRadios = document.querySelectorAll('input[name="action"]');
    actionRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value !== 'give_out') {
                clearSalesPersonInSession();
            }
        });
    });

    // Also when the form (search) is submitted, ensure the actionHidden is set
    document.getElementById('searchForm').addEventListener('submit', function(e) {
        const actionRadios = document.querySelectorAll('input[name="action"]');
        let selectedAction = '';
        actionRadios.forEach(r => { if (r.checked) selectedAction = r.value; });
        document.getElementById('actionHidden').value = selectedAction;
    });

    // Initial toggle for sales person group
    toggleSalesPerson();
});

function toggleSalesPerson() {
    const actionRadios = document.querySelectorAll('input[name="action"]');
    let selectedAction = '';
    actionRadios.forEach(r => { if (r.checked) selectedAction = r.value; });
    const group = document.getElementById('salesPersonGroup');
    const actionHidden = document.getElementById('actionHidden');
    const actionConfirm = document.getElementById('actionConfirm');
    if (selectedAction === 'give_out') {
        group.style.display = 'block';
        document.getElementById('sales_person').required = true;
    } else {
        group.style.display = 'none';
        document.getElementById('sales_person').required = false;
    }
    if (actionHidden) actionHidden.value = selectedAction;
    if (actionConfirm) actionConfirm.value = selectedAction;
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