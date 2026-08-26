<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

// Allowed roles: super_admin, inventory_admin, manager
$userEmail = strtolower(trim($_SESSION['email'] ?? ''));
$allowedEmails = [
    'stephanie@mombasacomputers.co.ke',
   ];
$hasAccess =
    $role === 'super_admin'|| $role === 'manager' ||
    (
        $role === 'inventory_admin' &&
        in_array($userEmail, $allowedEmails, true)
    );

if (!$hasAccess) {
    die('You Don\'t have Permission to view this page.');
}

// Manager branch restriction
$user_branch = '';
if ($role === 'manager') {
    $user_stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_branch = $user_data['branch'] ?? '';
}


// One-time security token for Update Sale Details.
if (empty($_SESSION['instock_sale_csrf'])) {
    $_SESSION['instock_sale_csrf'] = bin2hex(random_bytes(32));
}

// Active salespeople used in the Update Sale Details dialog.
$salesStmt = $conn->prepare("
    SELECT id, full_name
    FROM users
    WHERE role = 'sales'
      AND is_active = 1
    ORDER BY full_name ASC
");
$salesStmt->execute();
$salesPeople = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

// Update sale details directly from In-Stock Devices.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_sale_details'])) {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    $serialPost = trim((string)($_POST['serial_number'] ?? ''));
    $salesPerson = (int)($_POST['sales_person'] ?? 0);
    $sellingPrice = (float)($_POST['selling_price'] ?? 0);
    $paymentStatus = trim((string)($_POST['payment_status'] ?? ''));
    $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
    $saleNotes = trim((string)($_POST['sale_notes'] ?? ''));

    try {
        if (!hash_equals($_SESSION['instock_sale_csrf'], $csrf)) {
            throw new Exception('Security validation failed. Please try again.');
        }

        if ($serialPost === '') {
            throw new Exception('Serial number is required.');
        }

        if ($salesPerson <= 0) {
            throw new Exception('Please select a salesperson.');
        }

        if ($sellingPrice <= 0) {
            throw new Exception('Please enter a valid selling price.');
        }

        if (!in_array($paymentStatus, ['paid', 'unpaid'], true)) {
            throw new Exception('Please select Paid or Unpaid.');
        }

        $allowedPaymentMethods = [
            'cash',
            'mpesa-till',
            'mpesa-pochi',
            'bank-transfer'
        ];

        if ($paymentMethod !== '' && !in_array($paymentMethod, $allowedPaymentMethods, true)) {
            throw new Exception('Invalid payment method selected.');
        }

        $paymentMethodDb = $paymentMethod !== '' ? $paymentMethod : null;

        // Confirm salesperson is still active.
        $salesUserStmt = $conn->prepare("
            SELECT id, full_name
            FROM users
            WHERE id = ?
              AND role = 'sales'
              AND is_active = 1
            LIMIT 1
        ");
        $salesUserStmt->execute([$salesPerson]);
        $salesUser = $salesUserStmt->fetch(PDO::FETCH_ASSOC);

        if (!$salesUser) {
            throw new Exception('Selected salesperson is not available.');
        }

        $conn->beginTransaction();

        $deviceStmt = $conn->prepare("
            SELECT *
            FROM devices
            WHERE serial_number = ?
              AND status = 'In Stock'
            FOR UPDATE
        ");
        $deviceStmt->execute([$serialPost]);
        $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);

        if (!$device) {
            throw new Exception('Device was not found in stock.');
        }

        // Keep manager branch restrictions intact.
        if ($role === 'manager' && $user_branch !== '' && $device['branch'] !== $user_branch) {
            throw new Exception('You cannot sell a device from another branch.');
        }

        $description = trim(
            (($device['manufacturer'] ?? '') . ' ' . ($device['model_name'] ?? ''))
        );

        if ($description === '') {
            $description = $device['model_name'] ?? $serialPost;
        }

        // Notes are optional. Blank notes do not erase an existing owner_notes value.
        $updateDevice = $conn->prepare("
            UPDATE devices
            SET status = 'Sold',
                place = 'sold',
                selling_price = ?,
                sold_at = NOW(),
                sold_by = ?,
                owner_notes = COALESCE(NULLIF(?, ''), owner_notes)
            WHERE serial_number = ?
        ");
        $updateDevice->execute([
            $sellingPrice,
            $salesPerson,
            $saleNotes,
            $serialPost
        ]);

        $saleStmt = $conn->prepare("
            INSERT INTO sales (
                total_amount,
                sale_status,
                completed_at,
                sold_by,
                payment_method,
                payment_status,
                completion_status
            )
            VALUES (?, 'completed', NOW(), ?, ?, ?, 'Completed')
        ");
        $saleStmt->execute([
            $sellingPrice,
            $salesPerson,
            $paymentMethodDb,
            $paymentStatus
        ]);

        $saleId = (int)$conn->lastInsertId();

        $saleItemStmt = $conn->prepare("
            INSERT INTO sale_items (
                sale_id,
                item_type,
                item_id,
                description,
                quantity,
                unit_price,
                sales_person
            )
            VALUES (?, 'device', ?, ?, 1, ?, ?)
        ");
        $saleItemStmt->execute([
            $saleId,
            $serialPost,
            $description,
            $sellingPrice,
            $salesPerson
        ]);

        $methodLabel = $paymentMethodDb ?? 'Not specified';
        $notesLog = $saleNotes !== '' ? "; notes: {$saleNotes}" : '';

        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, details)
            VALUES (?, 'Updated sale details', ?)
        ");
        $logStmt->execute([
            $user_id,
            "Marked device SN: {$serialPost} as sold; salesperson: {$salesUser['full_name']}; " .
            "price: KES " . number_format($sellingPrice, 2) .
            "; payment status: {$paymentStatus}; payment method: {$methodLabel}{$notesLog}; sale #{$saleId}"
        ]);

        $conn->commit();

        $_SESSION['instock_sale_success'] =
            "Sale details updated successfully for {$serialPost}. Sale #{$saleId} created.";
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $_SESSION['instock_sale_error'] = $e->getMessage();
    }

    // Post/Redirect/Get keeps the page from resubmitting the sale.
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: instock.php' . ($queryString !== '' ? '?' . $queryString : ''));
    exit;
}

$flashSuccess = $_SESSION['instock_sale_success'] ?? '';
$flashError = $_SESSION['instock_sale_error'] ?? '';

unset(
    $_SESSION['instock_sale_success'],
    $_SESSION['instock_sale_error']
);

// Helper: build device specifications string (like sales_logs)
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

// Get filter inputs
$filter_serial = trim($_GET['serial'] ?? '');
$filter_model = trim($_GET['model'] ?? '');
$filter_branch = trim($_GET['branch'] ?? '');
$filter_category = trim($_GET['category'] ?? '');
$filter_place = trim($_GET['place'] ?? '');

// Build query
$sql = "SELECT d.*, 
               u.full_name AS added_by_name,
               c.category_name
        FROM devices d
        LEFT JOIN users u ON d.added_by = u.id
        LEFT JOIN categories c ON d.category_id = c.id
        WHERE d.status = 'In Stock'";
$params = [];

// Manager restriction
if ($role === 'manager' && !empty($user_branch)) {
    $sql .= " AND d.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}

// Filters
if ($filter_branch && $role !== 'manager') {
    $sql .= " AND d.branch = :branch";
    $params['branch'] = $filter_branch;
}
if ($filter_category) {
    $sql .= " AND d.category_id = :category_id";
    $params['category_id'] = (int)$filter_category;
}
if ($filter_place) {
    $sql .= " AND d.place = :place";
    $params['place'] = $filter_place;
}
if ($filter_serial) {
    $sql .= " AND d.serial_number LIKE :serial";
    $params['serial'] = "%$filter_serial%";
}
if ($filter_model) {
    $sql .= " AND d.model_name LIKE :model";
    $params['model'] = "%$filter_model%";
}

$sql .= " ORDER BY d.date_added DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_devices = count($devices);

// Get lists for filter dropdowns
$branches_list = [];
$places_list = ['display', 'store', 'warehouse'];
if (in_array($role, ['super_admin', 'inventory_admin'])) {
    $stmt = $conn->query("SELECT DISTINCT branch FROM devices WHERE status = 'In Stock' ORDER BY branch");
    $branches_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$stmt = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>In-Stock Devices | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Same styles as sales_logs.php – keeping consistency */
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
        .stats-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .stat-card { background: white; padding: 1rem 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); flex: 1; min-width: 150px; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--primary); }
        .stat-card .stat-label { font-size: 0.8rem; color: var(--gray-500); }
        .filter-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .filter-title { font-size: 1rem; font-weight: 500; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-size: 0.85rem; font-weight: 500; color: var(--gray-600); }
        .filter-group input, .filter-group select { padding: 0.625rem 0.875rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; width: 100%; }
        .filter-actions { display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap; }
        .btn { padding: 0.625rem 1.25rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-size: 0.9rem; }
        .btn-secondary { background: var(--gray-500); }
        .btn:hover { opacity: 0.9; }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; font-size: 0.85rem; }
        th { background: var(--gray-50); padding: 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        td { padding: 0.8rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: var(--gray-100); }
        .badge-place-display { background: #dbeafe; color: #1e40af; }
        .badge-place-store { background: #d1fae5; color: #065f46; }
        .badge-place-warehouse { background: #fed7aa; color: #92400e; }
        .specs-text { font-size: 0.8rem; color: var(--gray-600); word-wrap: break-word; max-width: 350px; display: inline-block; }
        .text-muted { color: var(--gray-500); }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .btn-view { background: #3b82f6; color: white; border: none; border-radius: var(--radius-sm); padding: 0.3rem 0.6rem; font-size: 0.75rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem; }
        .btn-view:hover { background: #2563eb; }

        .btn-sale {
            background: #166534;
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            padding: 0.4rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
        }

        .btn-sale:hover {
            background: #14532d;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .65rem;
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

        .sale-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.58);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 1rem;
        }

        .sale-modal.open {
            display: flex;
        }

        .sale-modal-dialog {
            width: min(520px, 100%);
            max-height: 92vh;
            overflow-y: auto;
            background: white;
            border-radius: 14px;
            box-shadow: 0 24px 60px rgba(0,0,0,.22);
        }

        .sale-modal-header {
            padding: 1.15rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--gray-200);
        }

        .sale-modal-header h3 {
            margin: 0;
            font-size: 1.05rem;
        }

        .modal-close {
            border: 0;
            background: transparent;
            color: var(--gray-500);
            cursor: pointer;
            font-size: 1.1rem;
        }

        .sale-modal-body {
            padding: 1.25rem;
        }

        .sale-device-label {
            margin-bottom: 1rem;
            padding: .8rem .9rem;
            border-radius: 8px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            font-size: .85rem;
        }

        .sale-form-group {
            margin-bottom: 1rem;
        }

        .sale-form-group label {
            display: block;
            margin-bottom: .4rem;
            font-size: .82rem;
            font-weight: 600;
            color: var(--gray-600);
        }

        .sale-form-group input,
        .sale-form-group select,
        .sale-form-group textarea {
            width: 100%;
            padding: .7rem .8rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            background: white;
            font: inherit;
        }

        .sale-form-group textarea {
            min-height: 90px;
            resize: vertical;
        }

        .modal-actions {
            display: flex;
            gap: .75rem;
            justify-content: flex-end;
            padding-top: .4rem;
        }

        .modal-actions .btn {
            width: auto;
        }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { 
            .filter-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; justify-content: center; }
            .stats-row { flex-direction: column; }
            .filter-actions { flex-direction: column; align-items: stretch; }
            table { font-size: 0.75rem; min-width: 700px; }
            .specs-text { max-width: 150px; }
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-boxes"></i> In-Stock Devices</h1>
        <div class="breadcrumb">
            <?php if ($role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($role === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($role === 'inventory_admin'): ?>
                <a href="../dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>In-Stock Devices</span>
        </div>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= number_format($total_devices) ?></div>
            <div class="stat-label">Total In-Stock Devices</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format(count($branches_list)) ?></div>
            <div class="stat-label">Branches</div>
        </div>
    </div>

    <div class="filter-section">
        <div class="filter-title"><i class="fas fa-filter"></i> Filter Devices</div>
        <form method="GET" class="filter-grid" id="instockFilterForm">
            <div class="filter-group">
                <label>Serial Number</label>
                <input type="text" name="serial" id="instockSerialSearch" placeholder="e.g., 5CG..." value="<?= htmlspecialchars($filter_serial) ?>" autocomplete="off">
            </div>
            <div class="filter-group">
                <label>Model Name</label>
                <input type="text" name="model" placeholder="e.g., ThinkPad..." value="<?= htmlspecialchars($filter_model) ?>">
            </div>
            <?php if ($role !== 'manager'): ?>
            <div class="filter-group">
                <label>Branch</label>
                <select name="branch">
                    <option value="">-- All Branches --</option>
                    <?php foreach ($branches_list as $br): ?>
                        <option value="<?= htmlspecialchars($br) ?>" <?= $filter_branch == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="filter-group">
                <label>Category</label>
                <select name="category">
                    <option value="">-- All Categories --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $filter_category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Place</label>
                <select name="place">
                    <option value="">-- All Places --</option>
                    <?php foreach ($places_list as $p): ?>
                        <option value="<?= $p ?>" <?= $filter_place == $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn"><i class="fas fa-search"></i> Filter</button>
                <a href="instock.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <?php if (empty($devices)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No in-stock devices found matching your criteria.</p>
                <a href="instock.php" class="btn" style="margin-top: 1rem;">
                    <i class="fas fa-undo"></i> Clear Filters
                </a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Serial Number</th>
                        <th>Model</th>
                        <th>Category</th>
                        <th>Specifications</th>
                        <th>Place</th>
                        <th>Added By</th>
                        <th>Branch</th>
                        <th>Price (KES)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($devices as $device): ?>
                        <?php
                        $placeClass = '';
                        if ($device['place'] == 'display') $placeClass = 'badge-place-display';
                        elseif ($device['place'] == 'store') $placeClass = 'badge-place-store';
                        elseif ($device['place'] == 'warehouse') $placeClass = 'badge-place-warehouse';
                        $specs = buildDeviceSpecs($device);
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><code><?= htmlspecialchars($device['serial_number']) ?></code></td>
                            <td><strong><?= htmlspecialchars($device['model_name'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars($device['category_name'] ?? '-') ?></td>
                            <td>
                                <span class="specs-text" title="<?= htmlspecialchars($specs) ?>">
                                    <?= htmlspecialchars($specs ?: '-') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $placeClass ?>">
                                    <?= ucfirst($device['place'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($device['added_by_name'] ?? 'System') ?></td>
                            <td><?= htmlspecialchars($device['branch'] ?? '-') ?></td>
                            <td>
                                <?= $device['price'] !== null ? number_format($device['price'], 2) : '—' ?>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="btn-sale open-sale-modal"
                                    data-serial="<?= htmlspecialchars($device['serial_number'], ENT_QUOTES) ?>"
                                    data-model="<?= htmlspecialchars($device['model_name'] ?? '-', ENT_QUOTES) ?>"
                                >
                                    <i class="fas fa-cash-register"></i> Update Sale Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>


    <div class="sale-modal" id="saleDetailsModal" aria-hidden="true">
        <div class="sale-modal-dialog">
            <div class="sale-modal-header">
                <h3><i class="fas fa-cash-register"></i> Update Sale Details</h3>
                <button type="button" class="modal-close" id="closeSaleModal" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="sale-modal-body">
                <div class="sale-device-label">
                    <strong id="saleDeviceModel">-</strong><br>
                    Serial: <code id="saleDeviceSerial">-</code>
                </div>

                <form method="POST" id="saleDetailsForm">
                    <input type="hidden" name="update_sale_details" value="1">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['instock_sale_csrf']) ?>"
                    >
                    <input type="hidden" name="serial_number" id="saleSerialInput">

                    <div class="sale-form-group">
                        <label for="sales_person">Sales Person</label>
                        <select name="sales_person" id="sales_person" required>
                            <option value="">-- Select Sales Person --</option>
                            <?php foreach ($salesPeople as $salesPersonRow): ?>
                                <option value="<?= (int)$salesPersonRow['id'] ?>">
                                    <?= htmlspecialchars($salesPersonRow['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sale-form-group">
                        <label for="selling_price">Selling Price (KES)</label>
                        <input
                            type="number"
                            name="selling_price"
                            id="selling_price"
                            min="0.01"
                            step="0.01"
                            required
                            placeholder="Enter actual selling price"
                        >
                    </div>

                    <div class="sale-form-group">
                        <label for="payment_status">Payment Status</label>
                        <select name="payment_status" id="payment_status" required>
                            <option value="">-- Select --</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                    </div>

                    <div class="sale-form-group">
                        <label for="payment_method">
                            Payment Method
                            <span style="font-weight:400;color:var(--gray-500);">(Optional)</span>
                        </label>
                        <select name="payment_method" id="payment_method">
                            <option value="">-- Not specified --</option>
                            <option value="cash">Cash</option>
                            <option value="mpesa-till">M-Pesa Till</option>
                            <option value="mpesa-pochi">M-Pesa Pochi</option>
                            <option value="bank-transfer">Bank Transfer</option>
                        </select>
                    </div>

                    <div class="sale-form-group">
                        <label for="sale_notes">
                            Notes
                            <span style="font-weight:400;color:var(--gray-500);">(Optional)</span>
                        </label>
                        <textarea
                            name="sale_notes"
                            id="sale_notes"
                            placeholder="Enter sale notes, reference, customer details, or any other relevant note"
                        ></textarea>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" id="cancelSaleModal">
                            Cancel
                        </button>
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Save Sale Details
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<script>
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

    const saleModal = document.getElementById('saleDetailsModal');
    const saleSerialInput = document.getElementById('saleSerialInput');
    const saleDeviceSerial = document.getElementById('saleDeviceSerial');
    const saleDeviceModel = document.getElementById('saleDeviceModel');
    const closeSaleModal = document.getElementById('closeSaleModal');
    const cancelSaleModal = document.getElementById('cancelSaleModal');
    const saleDetailsForm = document.getElementById('saleDetailsForm');

    function openSaleDetailsModal(serial, model) {
        saleSerialInput.value = serial;
        saleDeviceSerial.textContent = serial;
        saleDeviceModel.textContent = model || '-';
        saleModal.classList.add('open');
        saleModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeSaleDetailsModal() {
        saleModal.classList.remove('open');
        saleModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        saleDetailsForm.reset();
        saleSerialInput.value = '';
    }

    document.querySelectorAll('.open-sale-modal').forEach(button => {
        button.addEventListener('click', function () {
            openSaleDetailsModal(
                this.dataset.serial,
                this.dataset.model
            );
        });
    });

    closeSaleModal.addEventListener('click', closeSaleDetailsModal);
    cancelSaleModal.addEventListener('click', closeSaleDetailsModal);

    saleModal.addEventListener('click', function (event) {
        if (event.target === saleModal) {
            closeSaleDetailsModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && saleModal.classList.contains('open')) {
            closeSaleDetailsModal();
        }
    });
</script>

<?php require_once "../includes/footer.php"; ?>

<script>
(function(){
    const form = document.getElementById('instockFilterForm');
    const serialInput = document.getElementById('instockSerialSearch');
    if (!form || !serialInput) return;

    let timer = null;
    let controller = null;

    async function ajaxFilter() {
        if (controller) controller.abort();
        controller = new AbortController();
        const params = new URLSearchParams(new FormData(form));
        const url = form.action || window.location.pathname;
        try {
            const response = await fetch(url + '?' + params.toString(), {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                signal: controller.signal
            });
            if (!response.ok) throw new Error('Search request failed');
            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const newStats = doc.querySelector('.stats-row');
            const newTable = doc.querySelector('.table-wrapper');
            const stats = document.querySelector('.stats-row');
            const table = document.querySelector('.table-wrapper');
            if (newStats && stats) stats.innerHTML = newStats.innerHTML;
            if (newTable && table) table.innerHTML = newTable.innerHTML;
            history.replaceState(null, '', url + (params.toString() ? '?' + params.toString() : ''));
            bindSaleButtons();
        } catch (e) {
            if (e.name !== 'AbortError') console.error(e);
        }
    }

    serialInput.addEventListener('input', function(){
        clearTimeout(timer);
        timer = setTimeout(ajaxFilter, 300);
    });

    function bindSaleButtons(){
        document.querySelectorAll('.open-sale-modal').forEach(function(btn){
            if (btn.dataset.ajaxBound === '1') return;
            btn.dataset.ajaxBound = '1';
            btn.addEventListener('click', function(){
                if (typeof openSaleDetailsModal === 'function') {
                    openSaleDetailsModal(this.dataset.serial, this.dataset.model);
                }
            });
        });
    }
    bindSaleButtons();
})();
</script>

</body>
</html>