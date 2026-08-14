<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin'])) {
    die("ACCESS DENIED. Only Inventory Admin or Super Admin can give out chargers.");
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

// For super_admin: fetch all branches
$all_branches = [];
if ($user_role === 'super_admin') {
    $branch_stmt = $conn->prepare("SELECT DISTINCT branch FROM chargers WHERE branch IS NOT NULL ORDER BY branch");
    $branch_stmt->execute();
    $all_branches = $branch_stmt->fetchAll(PDO::FETCH_COLUMN);
}

$selected_branch = $user_branch;
if ($user_role === 'super_admin' && isset($_GET['branch']) && $_GET['branch']) {
    $selected_branch = $_GET['branch'];
} elseif ($user_role === 'super_admin' && empty($selected_branch) && !empty($all_branches)) {
    $selected_branch = $all_branches[0];
}

$error = "";
$success = "";
$stocks = [];
$sales_users = [];

if ($selected_branch) {
    // Fetch available charger stock (quantity > 0)
    $stockStmt = $conn->prepare("SELECT * FROM chargers WHERE branch = ? AND quantity > 0 ORDER BY charger_type, charger_condition");
    $stockStmt->execute([$selected_branch]);
    $stocks = $stockStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch sales users in that branch
    $userStmt = $conn->prepare("SELECT id, full_name FROM users WHERE role = 'sales' AND branch = ? ORDER BY full_name");
    $userStmt->execute([$selected_branch]);
    $sales_users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $charger_id = (int) ($_POST['charger_id'] ?? 0);
    $quantity_given = (int) ($_POST['quantity'] ?? 0);
    $given_to = (int) ($_POST['given_to'] ?? 0);
    $branch = trim($_POST['branch'] ?? '');

    if (!$charger_id || $quantity_given <= 0 || !$given_to || !$branch) {
        $error = "All fields are required.";
    } else {
        try {
            $conn->beginTransaction();

            // Get current charger item with FOR UPDATE to lock the row
            $itemStmt = $conn->prepare("SELECT * FROM chargers WHERE id = ? AND branch = ? FOR UPDATE");
            $itemStmt->execute([$charger_id, $branch]);
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                throw new Exception("Charger item not found.");
            }
            if ($item['quantity'] < $quantity_given) {
                throw new Exception("Insufficient quantity. Available: {$item['quantity']}");
            }

            // Update charger quantity
            $new_qty = $item['quantity'] - $quantity_given;
            $update = $conn->prepare("UPDATE chargers SET quantity = ?, updated_by = ?, date_updated = NOW() WHERE id = ?");
            $update->execute([$new_qty, $user_id, $charger_id]);

            // Insert into charger_logs
            $log = $conn->prepare("
                INSERT INTO charger_logs 
                (charger_id, charger_type, charger_condition, quantity, given_by, given_to, branch, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_sale')
            ");
            $log->execute([
                $item['id'],
                $item['charger_type'],
                $item['charger_condition'],
                $quantity_given,
                $user_id,
                $given_to,
                $branch
            ]);

            // Activity log
            $sales_name = "User ID $given_to";
            $salesStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
            $salesStmt->execute([$given_to]);
            if ($salesStmt->rowCount()) $sales_name = $salesStmt->fetchColumn();

            $activity = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Give out Charger', ?)");
            $activity->execute([
                $user_id,
                "Gave {$quantity_given} charger(s) ({$item['charger_type']}, {$item['charger_condition']}) to {$sales_name} in {$branch} branch"
            ]);

            $conn->commit();
            $success = "Successfully gave out {$quantity_given} charger unit(s).";

            // Refresh stock list
            $stockStmt = $conn->prepare("SELECT * FROM chargers WHERE branch = ? AND quantity > 0 ORDER BY charger_type, charger_condition");
            $stockStmt->execute([$selected_branch]);
            $stocks = $stockStmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

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
    <title>Give Out Charger | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same base styles as give_hdd.php – consistent with inventory system */
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
        .page-header h1 i { color: var(--primary); }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .form-container { max-width: 700px; margin: 0 auto; }
        .card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow-sm); }
        .card-header { background: var(--gray-50); padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--gray-200); }
        .card-header h2 { font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .card-header h2 i { color: var(--primary); }
        .card-body { padding: 1.5rem; }
        .info-box { background: var(--gray-50); border-radius: var(--radius-lg); padding: 1rem 1.25rem; margin-bottom: 1.5rem; border-left: 4px solid var(--primary); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: var(--gray-700); }
        .form-group select, .form-group input { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: var(--primary); color: white; width: 100%; justify-content: center; }
        .btn-primary:hover { background: var(--primary-light); }
        .alert { padding: 1rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .btn { width: 100%; justify-content: center; } .card-body { padding: 1rem; } }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-bolt"></i> Give Out Charger</h1>
        <div class="breadcrumb">
            <a href="../dashboard/<?= $user_role === 'super_admin' ? 'superadmindashboard.php' : 'inventorydashboard.php' ?>">Dashboard</a>
            <span> / </span>
            <a href="chargers_instock.php">Charger Stock</a>
            <span> / </span>
            <span>Give Out</span>
        </div>
    </div>

    <div class="form-container">
        <div class="card">
            <div class="card-header"><h2><i class="fas fa-exchange-alt"></i> Select Branch & Charger</h2></div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($user_role === 'super_admin'): ?>
                <div class="info-box">
                    <form method="GET" style="margin:0; display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end;">
                        <div style="flex:1;">
                            <label>Select Branch</label>
                            <select name="branch" onchange="this.form.submit()" style="width:100%; padding:0.6rem;">
                                <?php foreach ($all_branches as $b): ?>
                                    <option value="<?= htmlspecialchars($b) ?>" <?= $selected_branch == $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <?php if (!$selected_branch): ?>
                    <div class="alert alert-error">No branch selected or available.</div>
                <?php elseif (empty($stocks)): ?>
                    <div class="alert alert-error">No charger items available in stock for <?= htmlspecialchars($selected_branch) ?> branch.</div>
                <?php elseif (empty($sales_users)): ?>
                    <div class="alert alert-error">No sales users found in <?= htmlspecialchars($selected_branch) ?> branch.</div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch) ?>">
                        <div class="form-group">
                            <label>Select Charger Item</label>
                            <select name="charger_id" required>
                                <option value="">-- Choose Charger --</option>
                                <?php foreach ($stocks as $s): ?>
                                    <option value="<?= $s['id'] ?>">
                                        <?= htmlspecialchars($s['charger_type']) ?> (<?= htmlspecialchars($s['charger_condition']) ?>) - Available: <?= $s['quantity'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" name="quantity" min="1" required placeholder="Number of charger units to give">
                        </div>
                        <div class="form-group">
                            <label>Give To (Salesperson)</label>
                            <select name="given_to" required>
                                <option value="">-- Select Salesperson --</option>
                                <?php foreach ($sales_users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i> Give Out</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function adjustMainContent() {
        const mainContent = document.querySelector('.main-content');
        const sidebar = document.querySelector('.sidebar');
        if (window.innerWidth <= 1200) {
            if (mainContent) mainContent.style.marginLeft = '0';
        } else {
            if (mainContent && sidebar) mainContent.style.marginLeft = '260px';
        }
    }
    adjustMainContent();
    window.addEventListener('resize', adjustMainContent);
});
</script>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>