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
$availablePlaces = ['warehouse', 'store', 'display'];
$error = $success = "";
$foundAccessories = [];

if (isset($_POST['search_accessories'])) $_SESSION['delivered_by'] = trim($_POST['delivered_by'] ?? '');

if (isset($_POST['search_accessories'])) {
    $from_branch = $_POST['from_branch'] ?? null;
    $from_place = $_POST['from_place'] ?? '';
    $to_branch = $_POST['to_branch'] ?? null;
    $to_place = $_POST['to_place'] ?? '';
    $delivered_by = trim($_POST['delivered_by'] ?? '');
    if (!$is_super_admin) $from_branch = $user_branch;

    if (empty($from_branch) || empty($to_branch)) $error = "Please select both source and destination branches.";
    elseif ($from_branch === $to_branch && $from_place === $to_place) $error = "Source and destination cannot be exactly the same location (same branch and place).";
    elseif (empty($delivered_by)) $error = "Please enter the name of the person delivering the accessories.";
    elseif (!$is_super_admin && $from_branch !== $user_branch) $error = "You can only transfer accessories from your own branch.";
    else {
        $sql = "SELECT * FROM accessories WHERE branch = ? AND quantity > 0 AND status = 'instock'";
        $params = [$from_branch];
        if (!empty($from_place)) {
            $sql .= " AND place = ?";
            $params[] = $from_place;
        }
        $sql .= " ORDER BY name, place";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $foundAccessories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($foundAccessories)) $error = "No accessories found in selected location.";
        else {
            $_SESSION['transfer_from_branch'] = $from_branch;
            $_SESSION['from_place'] = $from_place;
            $_SESSION['transfer_to_branch'] = $to_branch;
            $_SESSION['to_place'] = $to_place;
            $_SESSION['delivered_by'] = $delivered_by;
        }
    }
}

if (isset($_POST['transfer_accessories'])) {
    $selected = [];
    foreach ($_POST['accessories'] ?? [] as $id => $data) {
        if (isset($data['selected']) && $data['selected'] == '1' && isset($data['quantity']) && (int)$data['quantity'] > 0) {
            $selected[$id] = (int)$data['quantity'];
        }
    }
    $from_branch = $_SESSION['transfer_from_branch'] ?? null;
    $from_place = $_SESSION['from_place'] ?? null;
    $to_branch = $_SESSION['transfer_to_branch'] ?? null;
    $to_place = $_SESSION['to_place'] ?? null;
    $delivered_by = $_SESSION['delivered_by'] ?? '';
    if (empty($selected)) $error = "No accessories selected.";
    elseif (!$from_branch || !$to_branch) $error = "Branch information missing.";
    elseif (empty($delivered_by)) $error = "Delivery information missing.";
    elseif (!$is_super_admin && $from_branch !== $user_branch) $error = "You can only transfer accessories from your own branch.";
    else {
        $transferredItems = 0;
        $transferDetails = [];
        $conn->beginTransaction();
        try {
            foreach ($selected as $id => $qty) {
                // Fetch source accessory with lock (optional, but we'll check quantity again)
                $stmt = $conn->prepare("SELECT * FROM accessories WHERE id = ? AND branch = ? AND quantity >= ? AND status = 'instock'");
                $stmt->execute([$id, $from_branch, $qty]);
                $acc = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($acc) {
                    // Decrease from source
                    $update = $conn->prepare("UPDATE accessories SET quantity = quantity - ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND branch = ?");
                    $update->execute([$qty, $user_id, $id, $from_branch]);

                    // Check if destination already has same accessory (same name, place, branch)
                    $dest = $conn->prepare("SELECT id FROM accessories WHERE name = ? AND place = ? AND branch = ? AND status = 'instock'");
                    $dest->execute([$acc['name'], $to_place, $to_branch]);
                    $existing = $dest->fetch(PDO::FETCH_ASSOC);
                    if ($existing) {
                        $upd = $conn->prepare("UPDATE accessories SET quantity = quantity + ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                        $upd->execute([$qty, $user_id, $existing['id']]);
                    } else {
                        // Insert new record with destination place and branch
                        $ins = $conn->prepare("INSERT INTO accessories (name, quantity, added_by, place, branch, price, date_added, status, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'instock', ?, NOW())");
                        $ins->execute([$acc['name'], $qty, $user_id, $to_place, $to_branch, $acc['price'], $user_id]);
                    }
                    $transferredItems++;
                    $transferDetails[] = "{$acc['name']} x{$qty}";
                }
            }
            $conn->commit();
            if ($transferredItems > 0) {
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Transfer accessories', ?)");
                $log->execute([$user_id, "Transferred $transferredItems accessory type(s) (" . array_sum($selected) . " total items) from $from_branch ($from_place) to $to_branch ($to_place): " . implode(', ', $transferDetails) . " (Delivered by: $delivered_by)"]);
                $success = "$transferredItems accessory type(s) transferred successfully!";
                $foundAccessories = [];
                unset($_SESSION['transfer_from_branch'], $_SESSION['from_place'], $_SESSION['transfer_to_branch'], $_SESSION['to_place'], $_SESSION['delivered_by']);
            } else $error = "No accessories could be transferred.";
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Transfer failed: " . $e->getMessage();
        }
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
    <title>Transfer Accessories | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same CSS as transfer_charger.php – reuse */
        :root { --primary: #1a4b2a; --primary-light: #2a6b3a; --primary-dark: #0f3a1e; --info: #2563eb; --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb; --gray-300: #d1d5db; --gray-400: #9ca3af; --gray-500: #6b7280; --gray-600: #4b5563; --gray-700: #374151; --gray-800: #1f2937; --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05); --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1); --radius-sm: 0.375rem; --radius-md: 0.5rem; --radius-lg: 0.75rem; --radius-xl: 1rem; --font-sans: 'Inter', system-ui, sans-serif; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: all 0.3s ease; }
        .page-header { background: white; padding: 1.5rem 2rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .page-header h1 { font-size: 1.75rem; color: var(--gray-800); font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .form-container { max-width: 900px; margin: 0 auto; }
        .card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; }
        .card-header { background: var(--gray-50); padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200); }
        .card-header h2 { font-size: 1.25rem; font-weight: 600; color: var(--gray-800); display: flex; align-items: center; gap: 0.5rem; }
        .card-body { padding: 1.5rem; }
        .branch-selector { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .branch-selector div { flex: 1; min-width: 150px; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--gray-700); margin-bottom: 0.25rem; }
        input, select { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        button { background: var(--primary); color: white; border: none; border-radius: var(--radius-md); padding: 0.6rem 1.2rem; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; }
        button:hover { background: var(--primary-light); }
        .error { background: #fee2e2; border-left: 4px solid #dc2626; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; color: #991b1b; }
        .success { background: #d1fae5; border-left: 4px solid #10b981; padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; color: #065f46; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid var(--gray-200); padding: 0.5rem; text-align: left; }
        th { background: var(--gray-50); }
        .checkbox-cell { text-align: center; }
        .quantity-input { width: 80px; text-align: center; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .branch-selector { flex-direction: column; } button { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-headset"></i> Transfer Accessories</h1>
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
            <span>Transfer Accessories</span>
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
                            <label>Source Branch:</label>
                            <select name="from_branch" <?= !$is_super_admin ? 'disabled' : '' ?> required>
                                <option value="">Select Source Branch</option>
                                <?php foreach ($availableBranches as $branch): ?>
                                    <?php if ($is_super_admin || $branch == $user_branch): ?>
                                        <option value="<?= $branch ?>" <?= (!$is_super_admin && $branch == $user_branch) ? 'selected' : '' ?>><?= $branch ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$is_super_admin): ?>
                                <input type="hidden" name="from_branch" value="<?= htmlspecialchars($user_branch) ?>">
                                <small>You can only transfer from your branch: <strong><?= htmlspecialchars($user_branch) ?></strong></small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label>Source Place:</label>
                            <select name="from_place">
                                <option value="">All Places</option>
                                <?php foreach ($availablePlaces as $place): ?>
                                    <option value="<?= $place ?>"><?= ucfirst($place) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Destination Branch:</label>
                            <select name="to_branch" required>
                                <option value="">Select Destination Branch</option>
                                <?php foreach ($availableBranches as $branch): ?>
                                    <?php if ($is_super_admin || $branch != $user_branch): ?>
                                        <option value="<?= $branch ?>"><?= $branch ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Destination Place:</label>
                            <select name="to_place" required>
                                <option value="">Select Place</option>
                                <?php foreach ($availablePlaces as $place): ?>
                                    <option value="<?= $place ?>"><?= ucfirst($place) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Delivered By (Person's Name):</label>
                        <input type="text" name="delivered_by" required placeholder="Enter name" value="<?= htmlspecialchars($_SESSION['delivered_by'] ?? '') ?>">
                    </div>
                    <button type="submit" name="search_accessories"><i class="fas fa-search"></i> Search Accessories</button>
                </div>
            </div>
        </form>

        <?php if (!empty($foundAccessories)): ?>
            <form method="POST">
                <div class="card">
                    <div class="card-header"><h2><i class="fas fa-list"></i> Available Accessories</h2></div>
                    <div class="card-body">
                        <p><strong>Transferring from:</strong> <?= htmlspecialchars($_SESSION['transfer_from_branch'] ?? '') ?> (<?= htmlspecialchars($_SESSION['from_place'] ?? 'All') ?>)</p>
                        <p><strong>Transferring to:</strong> <?= htmlspecialchars($_SESSION['transfer_to_branch'] ?? '') ?> (<?= htmlspecialchars($_SESSION['to_place'] ?? '') ?>)</p>
                        <p><strong>Delivered by:</strong> <?= htmlspecialchars($_SESSION['delivered_by'] ?? '') ?></p>
                        <p><label><input type="checkbox" id="selectAll" onclick="selectAllCheckboxes(this)"> Select All</label></p>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Select</th><th>Name</th><th>Available</th><th>Place</th><th>Branch</th><th>Qty to Transfer</th></tr></thead>
                                <tbody>
                                <?php foreach ($foundAccessories as $acc): ?>
                                    <tr>
                                        <td class="checkbox-cell"><input type="checkbox" name="accessories[<?= $acc['id'] ?>][selected]" value="1" onchange="toggleQuantityInput(this)"></td>
                                        <td><?= htmlspecialchars($acc['name']) ?></td>
                                        <td><?= $acc['quantity'] ?></td>
                                        <td><?= htmlspecialchars($acc['place']) ?></td>
                                        <td><?= htmlspecialchars($acc['branch']) ?></td>
                                        <td>
                                            <input type="hidden" name="accessories[<?= $acc['id'] ?>][max]" value="<?= $acc['quantity'] ?>">
                                            <input type="number" name="accessories[<?= $acc['id'] ?>][quantity]" class="quantity-input" min="1" max="<?= $acc['quantity'] ?>" value="0" disabled onchange="validateQuantity(this)">
                                            <small>Max: <?= $acc['quantity'] ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" name="transfer_accessories" style="margin-top:1rem"><i class="fas fa-arrow-right"></i> Transfer Selected</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>
<script>
function selectAllCheckboxes(source) {
    document.querySelectorAll('input[name^="accessories["][type="checkbox"]').forEach(cb => {
        cb.checked = source.checked;
        const row = cb.closest('tr');
        const qty = row.querySelector('.quantity-input');
        if (qty) {
            qty.disabled = !cb.checked;
            if (cb.checked && qty.value === '0') qty.value = '1';
            else if (!cb.checked) qty.value = '0';
        }
    });
}
function toggleQuantityInput(checkbox) {
    const row = checkbox.closest('tr');
    const qty = row.querySelector('.quantity-input');
    if (qty) {
        qty.disabled = !checkbox.checked;
        if (checkbox.checked && qty.value === '0') qty.value = '1';
        else if (!checkbox.checked) qty.value = '0';
    }
}
function validateQuantity(input) {
    const row = input.closest('tr');
    const max = parseInt(row.querySelector('input[name$="[max]"]').value);
    let val = parseInt(input.value);
    if (isNaN(val) || val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}
</script>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>