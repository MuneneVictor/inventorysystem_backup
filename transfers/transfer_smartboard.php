<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager', 'cashier'])) die("ACCESS DENIED.");

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

$user_branch = null;
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
    if (!$user_branch) die("Your account has no branch assigned.");
}

$is_super_admin = ($user_role === 'super_admin');
$availableBranches = ['KIMATHI', 'MOI'];
$error = $success = "";
$foundSmartboards = [];
$notFoundSerials = [];
$singleSmartboard = null;

// Store session data
if (isset($_POST['search_serial'])) {
    $_SESSION['delivered_by'] = trim($_POST['delivered_by'] ?? '');
    $_SESSION['from_location_type'] = $_POST['from_location_type'] ?? 'branch';
    $_SESSION['from_branch'] = $_POST['from_branch'] ?? null;
}

// Process search
if (isset($_POST['search_serial'])) {
    $input = trim($_POST['serial_number']);
    $from_location_type = $_POST['from_location_type'] ?? 'branch';
    $from_branch = $_POST['from_branch'] ?? null;
    $to_branch = $_POST['to_branch'] ?? null;
    $delivered_by = trim($_POST['delivered_by'] ?? '');

    if (!$is_super_admin && $from_location_type === 'branch') {
        $from_branch = $user_branch;
    }

    if (empty($input)) $error = "Please enter serial number(s).";
    elseif (empty($to_branch)) $error = "Please select destination branch.";
    elseif ($from_location_type === 'branch' && empty($from_branch)) $error = "Please select source branch.";
    elseif ($from_location_type === 'branch' && $from_branch === $to_branch) $error = "Source and destination branches cannot be the same.";
    elseif (empty($delivered_by)) $error = "Please enter the name of the person delivering the smartboards.";
    elseif (!$is_super_admin && $from_location_type === 'branch' && $from_branch !== $user_branch) $error = "You can only transfer from your own branch.";
    else {
        $serials = preg_split('/[\s,]+/', $input);
        $serials = array_filter(array_map('trim', $serials));
        if (empty($serials)) $error = "No valid serial numbers found.";
        else {
            // Build query based on source type
            if ($from_location_type === 'warehouse') {
                $sql = "SELECT * FROM smartboards WHERE serial_number IN (" . implode(',', array_fill(0, count($serials), '?')) . ") AND status = 'instock' AND place = 'warehouse'";
                $params = $serials;
            } else {
                $sql = "SELECT * FROM smartboards WHERE serial_number IN (" . implode(',', array_fill(0, count($serials), '?')) . ") AND status = 'instock' AND branch = ?";
                $params = $serials;
                $params[] = $from_branch;
            }
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $foundSmartboards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $foundSerials = array_column($foundSmartboards, 'serial_number');
            $notFoundSerials = array_diff($serials, $foundSerials);

            if (empty($foundSmartboards)) {
                if ($from_location_type === 'warehouse') $error = "No smartboards found in warehouse or not in stock.";
                else $error = "Smartboards not found in selected branch or not in stock.";
            } elseif (count($serials) === 1 && !empty($foundSmartboards)) {
                $singleSmartboard = $foundSmartboards[0];
                $foundSmartboards = [];
            }

            $_SESSION['transfer_to_branch'] = $to_branch;
            $_SESSION['from_location_type'] = $from_location_type;
            $_SESSION['from_branch'] = $from_branch;
            $_SESSION['delivered_by'] = $delivered_by;
        }
    }
}

// Process single transfer
if (isset($_POST['transfer_smartboard'])) {
    $serial = $_POST['serial_number'];
    $from_location_type = $_SESSION['from_location_type'] ?? null;
    $from_branch = $_SESSION['from_branch'] ?? null;
    $to_branch = $_SESSION['transfer_to_branch'] ?? null;
    $delivered_by = $_SESSION['delivered_by'] ?? '';

    if (empty($to_branch) || empty($delivered_by)) $error = "Missing transfer details.";
    elseif (!$is_super_admin && $from_location_type === 'branch' && $from_branch !== $user_branch) $error = "You can only transfer from your own branch.";
    else {
        // Fetch smartboard
        if ($from_location_type === 'warehouse') {
            $stmt = $conn->prepare("SELECT * FROM smartboards WHERE serial_number = ? AND status = 'instock' AND place = 'warehouse'");
            $stmt->execute([$serial]);
        } else {
            $stmt = $conn->prepare("SELECT * FROM smartboards WHERE serial_number = ? AND status = 'instock' AND branch = ?");
            $stmt->execute([$serial, $from_branch]);
        }
        $smartboard = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($smartboard) {
            // Update branch and place if coming from warehouse
            if ($from_location_type === 'warehouse') {
                $update = $conn->prepare("UPDATE smartboards SET branch = ?, place = 'store' WHERE serial_number = ?");
                $update->execute([$to_branch, $serial]);
            } else {
                $update = $conn->prepare("UPDATE smartboards SET branch = ? WHERE serial_number = ?");
                $update->execute([$to_branch, $serial]);
            }
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Transfer smartboard', ?)");
            $log->execute([$user_id, "Transferred smartboard SN: {$smartboard['serial_number']} from " . ($from_location_type === 'warehouse' ? 'Warehouse' : $from_branch) . " to $to_branch (Delivered by: $delivered_by)"]);
            $success = "Smartboard transferred successfully!";
            $singleSmartboard = null;
            unset($_SESSION['transfer_to_branch'], $_SESSION['from_location_type'], $_SESSION['from_branch'], $_SESSION['delivered_by']);
        } else $error = "Smartboard not found or not available for transfer.";
    }
}

// Process bulk transfer
if (isset($_POST['transfer_bulk_smartboards'])) {
    $selectedSerials = $_POST['selected_serials'] ?? [];
    $from_location_type = $_SESSION['from_location_type'] ?? null;
    $from_branch = $_SESSION['from_branch'] ?? null;
    $to_branch = $_SESSION['transfer_to_branch'] ?? null;
    $delivered_by = $_SESSION['delivered_by'] ?? '';

    if (empty($selectedSerials)) $error = "No smartboards selected.";
    elseif (empty($to_branch) || empty($delivered_by)) $error = "Missing transfer details.";
    elseif (!$is_super_admin && $from_location_type === 'branch' && $from_branch !== $user_branch) $error = "You can only transfer from your own branch.";
    else {
        $transferredCount = 0;
        $transferredSerials = [];
        foreach ($selectedSerials as $serial) {
            if ($from_location_type === 'warehouse') {
                $stmt = $conn->prepare("SELECT * FROM smartboards WHERE serial_number = ? AND status = 'instock' AND place = 'warehouse'");
                $stmt->execute([$serial]);
            } else {
                $stmt = $conn->prepare("SELECT * FROM smartboards WHERE serial_number = ? AND status = 'instock' AND branch = ?");
                $stmt->execute([$serial, $from_branch]);
            }
            if ($stmt->fetch()) {
                if ($from_location_type === 'warehouse') {
                    $update = $conn->prepare("UPDATE smartboards SET branch = ?, place = 'store' WHERE serial_number = ?");
                    $update->execute([$to_branch, $serial]);
                } else {
                    $update = $conn->prepare("UPDATE smartboards SET branch = ? WHERE serial_number = ?");
                    $update->execute([$to_branch, $serial]);
                }
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Transfer smartboard', ?)");
                $log->execute([$user_id, "Transferred smartboard SN: $serial from " . ($from_location_type === 'warehouse' ? 'Warehouse' : $from_branch) . " to $to_branch (Delivered by: $delivered_by)"]);
                $transferredCount++;
                $transferredSerials[] = $serial;
            }
        }
        if ($transferredCount > 0) {
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Bulk transfer summary', ?)");
            $log->execute([$user_id, "Bulk transfer: $transferredCount smartboard(s) [SN: " . implode(', ', $transferredSerials) . "] from " . ($from_location_type === 'warehouse' ? 'Warehouse' : $from_branch) . " to $to_branch (Delivered by: $delivered_by)"]);
            $success = "$transferredCount smartboard(s) transferred successfully!";
            $foundSmartboards = [];
            unset($_SESSION['transfer_to_branch'], $_SESSION['from_location_type'], $_SESSION['from_branch'], $_SESSION['delivered_by']);
        } else $error = "No smartboards could be transferred.";
    }
}

// Greeting
date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Transfer Smartboard | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same CSS as transfer_device.php – reuse */
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
            --primary-dark: #0f3a1e;
            --info: #2563eb;
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
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .form-container { max-width: 900px; margin: 0 auto; }
        .card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; }
        .card-header { background: var(--gray-50); padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
        .card-header h2 { font-size: 1.25rem; font-weight: 600; color: var(--gray-800); display: flex; align-items: center; gap: 0.5rem; }
        .card-body { padding: 1.5rem; }
        .branch-selector { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .branch-selector div { flex: 1; min-width: 180px; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--gray-700); margin-bottom: 0.25rem; }
        input, select, textarea { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        button { background: var(--primary); color: white; border: none; border-radius: var(--radius-md); padding: 0.6rem 1.2rem; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; }
        button:hover { background: var(--primary-light); }
        .error { background: #fee2e2; border-left: 4px solid #dc2626; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; color: #991b1b; }
        .success { background: #d1fae5; border-left: 4px solid #10b981; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; color: #065f46; }
        .warning-box { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid var(--gray-200); padding: 0.5rem; text-align: left; }
        th { background: var(--gray-50); }
        .checkbox-cell { text-align: center; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .branch-selector { flex-direction: column; } button { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-chalkboard-teacher"></i> Transfer Smartboard</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="../dashboard/inventorydashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="index.php">Transfers</a>
            <span> / </span>
            <span>Transfer Smartboard</span>
        </div>
    </div>

    <div class="form-container">
        <?php if ($error): ?><div class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

        <form method="POST">
            <div class="card">
                <div class="card-header"><h2><i class="fas fa-info-circle"></i> Transfer Details</h2></div>
                <div class="card-body">
                    <div class="branch-selector">
                        <div>
                            <label>Source Location:</label>
                            <select name="from_location_type" id="fromLocationType" onchange="toggleSourceBranch()" required>
                                <option value="branch" <?= (isset($_SESSION['from_location_type']) && $_SESSION['from_location_type'] === 'branch') ? 'selected' : '' ?>>Branch</option>
                                <option value="warehouse" <?= (isset($_SESSION['from_location_type']) && $_SESSION['from_location_type'] === 'warehouse') ? 'selected' : '' ?>>Warehouse</option>
                            </select>
                        </div>
                        <div id="sourceBranchDiv">
                            <label>Source Branch:</label>
                            <select name="from_branch" <?= !$is_super_admin ? 'disabled' : '' ?>>
                                <option value="">Select Source Branch</option>
                                <?php foreach ($availableBranches as $branch): ?>
                                    <?php if ($is_super_admin || $branch == $user_branch): ?>
                                        <option value="<?= $branch ?>" <?= (isset($_SESSION['from_branch']) && $_SESSION['from_branch'] == $branch) ? 'selected' : '' ?>><?= $branch ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$is_super_admin): ?>
                                <input type="hidden" name="from_branch" value="<?= htmlspecialchars($user_branch) ?>">
                                <small>You can only transfer from your branch: <strong><?= htmlspecialchars($user_branch) ?></strong></small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label>Destination Branch:</label>
                            <select name="to_branch" required>
                                <option value="">Select Destination Branch</option>
                                <?php foreach ($availableBranches as $branch): ?>
                                    <option value="<?= $branch ?>" <?= (isset($_SESSION['transfer_to_branch']) && $_SESSION['transfer_to_branch'] == $branch) ? 'selected' : '' ?>><?= $branch ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Delivered By (Person's Name):</label>
                        <input type="text" name="delivered_by" required placeholder="Enter name" value="<?= htmlspecialchars($_SESSION['delivered_by'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Serial Numbers (one per line or comma separated):</label>
                        <textarea name="serial_number" rows="5" required placeholder="Type or scan serial numbers..."></textarea>
                    </div>
                    <button type="submit" name="search_serial"><i class="fas fa-search"></i> Search Smartboards</button>
                </div>
            </div>
        </form>

        <?php if (!empty($notFoundSerials)): ?>
            <div class="warning-box"><strong>Not Found:</strong> <?= implode(', ', $notFoundSerials) ?></div>
        <?php endif; ?>

        <?php if ($singleSmartboard): ?>
            <form method="POST">
                <div class="card">
                    <div class="card-header"><h2><i class="fas fa-chalkboard-teacher"></i> Smartboard Details</h2></div>
                    <div class="card-body">
                        <table>
                            <tr><th>Serial Number</th><td><?= htmlspecialchars($singleSmartboard['serial_number']) ?></td></tr>
                            <tr><th>Model</th><td><?= htmlspecialchars($singleSmartboard['model']) ?></td></tr>
                            <tr><th>Size</th><td><?= $singleSmartboard['size_inches'] ?> inches</td></tr>
                            <tr><th>Current Location</th><td><?= ($singleSmartboard['place'] === 'warehouse') ? 'Warehouse' : htmlspecialchars($singleSmartboard['branch']) ?></td></tr>
                            <tr><th>Transfer To</th><td><?= htmlspecialchars($_SESSION['transfer_to_branch'] ?? '') ?></td></tr>
                            <tr><th>Delivered By</th><td><?= htmlspecialchars($_SESSION['delivered_by'] ?? '') ?></td></tr>
                        </table>
                        <input type="hidden" name="serial_number" value="<?= htmlspecialchars($singleSmartboard['serial_number']) ?>">
                        <button type="submit" name="transfer_smartboard"><i class="fas fa-arrow-right"></i> Confirm Transfer</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <?php if (!empty($foundSmartboards)): ?>
            <form method="POST">
                <div class="card">
                    <div class="card-header"><h2><i class="fas fa-list"></i> Found Smartboards (<?= count($foundSmartboards) ?>)</h2></div>
                    <div class="card-body">
                        <p><strong>Transferring to:</strong> <?= htmlspecialchars($_SESSION['transfer_to_branch'] ?? '') ?></p>
                        <p><strong>Delivered by:</strong> <?= htmlspecialchars($_SESSION['delivered_by'] ?? '') ?></p>
                        <p><label><input type="checkbox" id="selectAll" onclick="selectAllCheckboxes(this)"> Select All</label></p>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th class="checkbox-cell">#</th><th class="checkbox-cell">Transfer</th><th>Serial</th><th>Model</th><th>Size</th><th>Current Location</th></tr></thead>
                                <tbody>
                                <?php foreach ($foundSmartboards as $idx => $s): ?>
                                    <tr>
                                        <td class="checkbox-cell"><?= $idx+1 ?></td>
                                        <td class="checkbox-cell"><input type="checkbox" name="selected_serials[]" value="<?= htmlspecialchars($s['serial_number']) ?>" checked></td>
                                        <td><?= htmlspecialchars($s['serial_number']) ?></td>
                                        <td><?= htmlspecialchars($s['model']) ?></td>
                                        <td><?= $s['size_inches'] ?>\"</td>
                                        <td><?= ($s['place'] === 'warehouse') ? 'Warehouse' : htmlspecialchars($s['branch']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" name="transfer_bulk_smartboards" style="margin-top:1rem"><i class="fas fa-arrow-right"></i> Transfer Selected (<?= count($foundSmartboards) ?>)</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>
<script>
function toggleSourceBranch() {
    const type = document.getElementById('fromLocationType').value;
    const branchDiv = document.getElementById('sourceBranchDiv');
    if (type === 'warehouse') {
        branchDiv.style.display = 'none';
        // Remove required attribute from select
        const select = branchDiv.querySelector('select');
        if (select) select.removeAttribute('required');
    } else {
        branchDiv.style.display = 'block';
        const select = branchDiv.querySelector('select');
        if (select) select.setAttribute('required', 'required');
    }
}
function selectAllCheckboxes(source) {
    document.querySelectorAll('input[name="selected_serials[]"]').forEach(cb => cb.checked = source.checked);
}
// Initial toggle on load
document.addEventListener('DOMContentLoaded', function() {
    toggleSourceBranch();
});
</script>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>