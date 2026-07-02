<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";


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
$foundChargers = [];

if (isset($_POST['search_chargers'])) $_SESSION['delivered_by'] = trim($_POST['delivered_by'] ?? '');

if (isset($_POST['search_chargers'])) {
    $from_branch = $_POST['from_branch'] ?? null;
    $to_branch = $_POST['to_branch'] ?? null;
    $delivered_by = trim($_POST['delivered_by'] ?? '');
    if (!$is_super_admin) $from_branch = $user_branch;

    if (empty($from_branch) || empty($to_branch)) $error = "Please select both source and destination branches.";
    elseif ($from_branch === $to_branch) $error = "Source and destination branches cannot be the same.";
    elseif (empty($delivered_by)) $error = "Please enter the name of the person delivering the chargers.";
    elseif (!$is_super_admin && $from_branch !== $user_branch) $error = "You can only transfer chargers from your own branch.";
    else {
        $stmt = $conn->prepare("SELECT * FROM chargers WHERE branch = ? AND quantity > 0 ORDER BY charger_type, watts, charger_condition");
        $stmt->execute([$from_branch]);
        $foundChargers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($foundChargers)) $error = "No chargers found in selected branch.";
        else {
            $_SESSION['transfer_from_branch'] = $from_branch;
            $_SESSION['transfer_to_branch'] = $to_branch;
            $_SESSION['delivered_by'] = $delivered_by;
        }
    }
}

if (isset($_POST['transfer_chargers'])) {
    $chargers_to_transfer = $_POST['chargers'] ?? [];
    $from_branch = $_SESSION['transfer_from_branch'] ?? null;
    $to_branch = $_SESSION['transfer_to_branch'] ?? null;
    $delivered_by = $_SESSION['delivered_by'] ?? '';
    $selectedChargers = [];
    foreach ($chargers_to_transfer as $id => $data) {
        if (isset($data['selected']) && $data['selected'] == '1' && isset($data['quantity']) && (int)$data['quantity'] > 0) {
            $selectedChargers[$id] = (int)$data['quantity'];
        }
    }
    if (empty($selectedChargers)) $error = "No chargers selected for transfer.";
    elseif (!$from_branch || !$to_branch) $error = "Branch information missing.";
    elseif (empty($delivered_by)) $error = "Delivery information missing.";
    elseif (!$is_super_admin && $from_branch !== $user_branch) $error = "You can only transfer chargers from your own branch.";
    else {
        $transferredItems = 0;
        $transferDetails = [];
        $conn->beginTransaction();
        try {
            foreach ($selectedChargers as $charger_id => $qty) {
                $stmt = $conn->prepare("SELECT * FROM chargers WHERE id = ? AND branch = ? AND quantity >= ?");
                $stmt->execute([$charger_id, $from_branch, $qty]);
                $charger = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($charger) {
                    $update = $conn->prepare("UPDATE chargers SET quantity = quantity - ?, updated_by = ?, date_updated = NOW() WHERE id = ? AND branch = ?");
                    $update->execute([$qty, $user_id, $charger_id, $from_branch]);

                    $dest = $conn->prepare("SELECT id FROM chargers WHERE charger_type = ? AND watts = ? AND charger_condition = ? AND branch = ?");
                    $dest->execute([$charger['charger_type'], $charger['watts'], $charger['charger_condition'], $to_branch]);
                    $existing = $dest->fetch(PDO::FETCH_ASSOC);
                    if ($existing) {
                        $upd = $conn->prepare("UPDATE chargers SET quantity = quantity + ?, updated_by = ?, date_updated = NOW() WHERE id = ?");
                        $upd->execute([$qty, $user_id, $existing['id']]);
                    } else {
                        $ins = $conn->prepare("INSERT INTO chargers (charger_type, watts, charger_condition, quantity, branch, updated_by, date_updated) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        $ins->execute([$charger['charger_type'], $charger['watts'], $charger['charger_condition'], $qty, $to_branch, $user_id]);
                    }
                    $transferredItems++;
                    $transferDetails[] = "{$charger['charger_type']} {$charger['watts']}W ({$charger['charger_condition']}) x{$qty}";
                }
            }
            $conn->commit();
            if ($transferredItems > 0) {
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Transfer chargers', ?)");
                $log->execute([$user_id, "Transferred $transferredItems charger type(s) (" . array_sum($selectedChargers) . " total items) from $from_branch to $to_branch: " . implode(', ', $transferDetails) . " (Delivered by: $delivered_by)"]);
                $success = "$transferredItems charger type(s) transferred successfully! (Delivered by: $delivered_by)";
                $foundChargers = [];
                unset($_SESSION['transfer_from_branch'], $_SESSION['transfer_to_branch'], $_SESSION['delivered_by']);
            } else $error = "No chargers could be transferred.";
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Transfer failed: " . $e->getMessage();
        }
    }
}
require_once "../includes/sidebar.php";
// HTML output – similar structure to other transfer files but with charger‑specific table
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Transfer Chargers | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same CSS as transfer_device.php – reuse */
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
        .branch-selector div { flex: 1; min-width: 180px; }
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
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-bolt"></i> Transfer Chargers</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="index.php">Transfers</a>
            <span> / </span>
            <span>Transfer Chargers</span>
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
                            <label>Transfer From:</label>
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
                            <label>Transfer To:</label>
                            <select name="to_branch" required>
                                <option value="">Select Destination Branch</option>
                                <?php foreach ($availableBranches as $branch): ?>
                                    <?php if ($is_super_admin || $branch != $user_branch): ?>
                                        <option value="<?= $branch ?>"><?= $branch ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Delivered By (Person's Name):</label>
                        <input type="text" name="delivered_by" required placeholder="Enter name" value="<?= htmlspecialchars($_SESSION['delivered_by'] ?? '') ?>">
                    </div>
                    <button type="submit" name="search_chargers"><i class="fas fa-search"></i> Search Chargers</button>
                </div>
            </div>
        </form>

        <?php if (!empty($foundChargers)): ?>
            <form method="POST" id="transferForm">
                <div class="card">
                    <div class="card-header"><h2><i class="fas fa-list"></i> Available Chargers in <?= htmlspecialchars($_SESSION['transfer_from_branch'] ?? '') ?></h2></div>
                    <div class="card-body">
                        <p><strong>Transferring to:</strong> <?= htmlspecialchars($_SESSION['transfer_to_branch'] ?? '') ?></p>
                        <p><strong>Delivered by:</strong> <?= htmlspecialchars($_SESSION['delivered_by'] ?? '') ?></p>
                        <p><label><input type="checkbox" id="selectAll" onclick="selectAllCheckboxes(this)"> Select All</label></p>
                        <table>
                            <thead><tr><th class="checkbox-cell">Select</th><th>Type</th><th>Watts</th><th>Condition</th><th>Available</th><th>Qty to Transfer</th></tr></thead>
                            <tbody>
                            <?php foreach ($foundChargers as $charger): ?>
                                <tr>
                                    <td class="checkbox-cell"><input type="checkbox" name="chargers[<?= $charger['id'] ?>][selected]" value="1" onchange="toggleQuantityInput(this)" id="ch_<?= $charger['id'] ?>"></td>
                                    <td><label for="ch_<?= $charger['id'] ?>"><?= htmlspecialchars($charger['charger_type']) ?></label></td>
                                    <td><?= $charger['watts'] ?>W</td>
                                    <td><?= htmlspecialchars($charger['charger_condition']) ?></td>
                                    <td><?= $charger['quantity'] ?></td>
                                    <td>
                                        <input type="hidden" name="chargers[<?= $charger['id'] ?>][max]" value="<?= $charger['quantity'] ?>">
                                        <input type="number" name="chargers[<?= $charger['id'] ?>][quantity]" class="quantity-input" min="1" max="<?= $charger['quantity'] ?>" value="0" disabled onchange="validateQuantity(this)">
                                        <small>Max: <?= $charger['quantity'] ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="submit" name="transfer_chargers"><i class="fas fa-arrow-right"></i> Transfer Selected</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>
<script>
function selectAllCheckboxes(source) {
    document.querySelectorAll('input[name^="chargers["][type="checkbox"]').forEach(cb => {
        cb.checked = source.checked;
        const row = cb.closest('tr');
        const qtyInput = row.querySelector('.quantity-input');
        if (qtyInput) {
            qtyInput.disabled = !cb.checked;
            if (cb.checked && qtyInput.value === '0') qtyInput.value = '1';
            else if (!cb.checked) qtyInput.value = '0';
        }
    });
}
function toggleQuantityInput(checkbox) {
    const row = checkbox.closest('tr');
    const qtyInput = row.querySelector('.quantity-input');
    if (qtyInput) {
        qtyInput.disabled = !checkbox.checked;
        if (checkbox.checked && qtyInput.value === '0') qtyInput.value = '1';
        else if (!checkbox.checked) qtyInput.value = '0';
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