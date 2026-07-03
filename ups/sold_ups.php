<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

// Allowed roles: super_admin, inventory_admin, manager
if (!in_array($role, ['super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED.");
}

// Manager branch restriction
$user_branch = '';
if ($role === 'manager') {
    $user_stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_branch = $user_data['branch'] ?? '';
}

// Get filter inputs
$search_serial = trim($_GET['serial_number'] ?? '');
$search_model = trim($_GET['model'] ?? '');
$search_branch = trim($_GET['branch'] ?? '');
$search_condition = trim($_GET['condition'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

// Build query – only sold UPS
$sql = "SELECT u.*, 
               u_added.full_name AS added_by_name,
               u_sold.full_name AS sold_by_name
        FROM ups u
        LEFT JOIN users u_added ON u.added_by = u_added.id
        LEFT JOIN users u_sold ON u.sold_by = u_sold.id
        WHERE u.status = 'sold'";
$params = [];

// Manager restriction
if ($role === 'manager' && !empty($user_branch)) {
    $sql .= " AND u.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}

// Filters
if ($search_serial !== '') {
    $sql .= " AND u.serial_number LIKE :sn";
    $params['sn'] = "%$search_serial%";
}
if ($search_model !== '') {
    $sql .= " AND u.model LIKE :model";
    $params['model'] = "%$search_model%";
}
if ($search_branch !== '' && $role !== 'manager') {
    $sql .= " AND u.branch = :branch";
    $params['branch'] = $search_branch;
}
if ($search_condition !== '') {
    $sql .= " AND u.ups_condition = :condition";
    $params['condition'] = $search_condition;
}
if ($date_from !== '') {
    $sql .= " AND DATE(u.date_sold) >= :date_from";
    $params['date_from'] = $date_from;
}
if ($date_to !== '') {
    $sql .= " AND DATE(u.date_sold) <= :date_to";
    $params['date_to'] = $date_to;
}

$sql .= " ORDER BY u.date_sold DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$ups_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_sold = count($ups_items);
$total_revenue = array_sum(array_column($ups_items, 'selling_price'));

// Get unique conditions for filter
$conditions = ['New', 'Ex-UK', 'Refurbished'];

// Branch list for filter (only if super_admin/inventory_admin)
$branches_list = [];
if (in_array($role, ['super_admin', 'inventory_admin'])) {
    $stmt = $conn->query("SELECT DISTINCT branch FROM ups WHERE status = 'sold' ORDER BY branch");
    $branches_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sold UPS | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- QR Code Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        /* ===== SAME STYLES AS sold_devices.php ===== */
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
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --font-sans: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }

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

        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }

        .breadcrumb {
            color: var(--gray-500);
            font-size: 0.9rem;
        }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.25rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-card .stat-icon { font-size: 1.75rem; color: var(--primary); margin-bottom: 0.5rem; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 600; color: var(--gray-800); }
        .stat-card .stat-label { font-size: 0.85rem; color: var(--gray-500); margin-top: 0.25rem; }

        .search-section {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .search-title {
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .search-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            position: relative;
        }

        .search-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            padding-right: 70px;
        }

        .search-group input,
        .search-group select {
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            background: white;
            width: 100%;
        }
        .search-group input:focus,
        .search-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26,75,42,0.1);
        }

        /* Scan button wrapper */
        .scan-btn-wrapper {
            position: absolute;
            top: -2px;
            right: 0;
            z-index: 5;
        }
        .scan-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            font-weight: 500;
            transition: background 0.2s;
            white-space: nowrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        }
        .scan-btn:hover {
            background: #1d4ed8;
        }
        .scan-btn i {
            font-size: 0.9rem;
        }

        @media (min-width: 1025px) {
            .scan-btn-wrapper {
                display: none !important;
            }
            .search-group label {
                padding-right: 0;
            }
        }
        @media (max-width: 1024px) {
            .search-group label {
                padding-right: 70px;
            }
        }

        .search-actions {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--font-sans);
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-300); }
        .btn-secondary:hover { background: var(--gray-200); }

        .table-wrapper {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            min-width: 900px;
        }

        th {
            background: var(--gray-50);
            padding: 1rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-600);
            font-size: 0.85rem;
            border-bottom: 1px solid var(--gray-200);
        }

        td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            vertical-align: middle;
        }

        tr:hover { background: var(--gray-50); }
        tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .serial-code {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: var(--gray-50);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            display: inline-block;
        }

        .branch-kimathi { color: #059669; font-weight: 500; }
        .branch-moi { color: #3b82f6; font-weight: 500; }

        .action-btns { display: flex; gap: 0.5rem; flex-wrap: wrap; }

        .btn-view {
            padding: 0.375rem 0.875rem;
            font-size: 0.8rem;
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: all 0.2s ease;
        }
        .btn-view:hover { background: var(--gray-50); border-color: var(--gray-400); }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }

        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

        /* Scanner overlay */
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
        .scanner-box #reader { width: 100%; min-height: 300px; margin: 1rem 0; }
        .scanner-box p { color: var(--gray-500); font-size: 0.9rem; margin-bottom: 0.5rem; }
        @media (max-width: 480px) {
            .scanner-box { max-width: 100%; border-radius: 0; height: 100vh; padding-top: 3rem; }
            .scanner-box #reader { height: 70vh; min-height: 200px; }
        }

        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .stats-row { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .stat-card { padding: 1rem; }
            .stat-card .stat-value { font-size: 1.5rem; }
            .search-section { padding: 1rem; }
            .search-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; justify-content: center; }
            .action-btns { flex-direction: column; }
            .btn-view { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .stats-row { grid-template-columns: 1fr; }
            .page-header h1 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-check-circle"></i>
            Sold UPS
        </h1>
        <div class="breadcrumb">
            <?php if ($role === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($role === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($role === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Sold UPS</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="stat-value"><?= number_format($total_sold) ?></div>
            <div class="stat-label">Total Sold</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-value">Ksh <?= number_format($total_revenue, 0) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-tag"></i></div>
            <div class="stat-value"><?= number_format(count($conditions)) ?></div>
            <div class="stat-label">Conditions</div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-title">
            <i class="fas fa-filter"></i> Filter Sold UPS
        </div>
        <form method="GET" class="search-grid">
            <!-- Serial Number with Scanner -->
            <div class="search-group">
                <label>
                    <span>Serial Number</span>
                    <span class="scan-btn-wrapper">
                        <button type="button" class="scan-btn" onclick="openScanner()">
                            <i class="fas fa-camera"></i> Scan
                        </button>
                    </span>
                </label>
                <input type="text" name="serial_number" id="serialSearch" placeholder="Scan or type serial number" value="<?= htmlspecialchars($search_serial) ?>">
            </div>

            <div class="search-group">
                <label>Model</label>
                <input type="text" name="model" placeholder="Search by model..." value="<?= htmlspecialchars($search_model) ?>">
            </div>

            <?php if ($role !== 'manager'): ?>
            <div class="search-group">
                <label>Branch</label>
                <select name="branch">
                    <option value="">-- All Branches --</option>
                    <?php foreach ($branches_list as $br): ?>
                        <option value="<?= htmlspecialchars($br) ?>" <?= $search_branch == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="search-group">
                <label>Condition</label>
                <select name="condition">
                    <option value="">-- All --</option>
                    <?php foreach ($conditions as $cond): ?>
                        <option value="<?= $cond ?>" <?= $search_condition == $cond ? 'selected' : '' ?>><?= $cond ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="search-group">
                <label>Sold Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" max="<?= date('Y-m-d') ?>">
            </div>

            <div class="search-group">
                <label>Sold Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" max="<?= date('Y-m-d') ?>">
            </div>

            <div class="search-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="sold_ups.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
        <div class="table-responsive">
            <?php if (empty($ups_items)): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>No sold UPS units found matching your criteria.</p>
                    <a href="sold_ups.php" class="btn btn-primary" style="margin-top: 1rem;">
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
                            <th>Capacity (VA)</th>
                            <th>Condition</th>
                            <th>Price (KES)</th>
                            <th>Selling Price (KES)</th>
                            <th>Added By</th>
                            <th>Sold By</th>
                            <th>Date Sold</th>
                            <th>Branch</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i = 1; foreach ($ups_items as $u): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><span class="serial-code"><?= htmlspecialchars($u['serial_number']) ?></span></td>
                            <td><strong><?= htmlspecialchars($u['model'] ?? '-') ?></strong></td>
                            <td><?= (int)$u['capacity'] ?> VA</td>
                            <td><span class="badge"><?= htmlspecialchars($u['ups_condition'] ?? 'New') ?></span></td>
                            <td><?= $u['price'] !== null ? number_format($u['price'], 2) : '—' ?></td>
                            <td><?= $u['selling_price'] !== null ? number_format($u['selling_price'], 2) : '—' ?></td>
                            <td><?= htmlspecialchars($u['added_by_name'] ?? 'System') ?></td>
                            <td><?= htmlspecialchars($u['sold_by_name'] ?? 'Unknown') ?></td>
                            <td><small><?= $u['date_sold'] ? date('M j, Y g:i A', strtotime($u['date_sold'])) : '—' ?></small></td>
                            <td>
                                <span class="<?= $u['branch'] == 'KIMATHI' ? 'branch-kimathi' : 'branch-moi' ?>">
                                    <?= htmlspecialchars($u['branch']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a class="btn-view" href="view_ups.php?sn=<?= urlencode($u['serial_number']) ?>">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<!-- Scanner Overlay -->
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
    // --- Scanner Logic (same as instock_ups) ---
    let html5QrCode = null;
    let scannerActive = false;

    function playBeep() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
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
            if (!scannerActive) startScanner();
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
        const serialInput = document.getElementById('serialSearch');
        if (serialInput) {
            serialInput.value = decodedText.trim().toUpperCase();
            serialInput.dispatchEvent(new Event('input'));
        }
        closeScanner();
    }

    function onScanError(errorMessage) { /* ignore */ }

    window.addEventListener('beforeunload', function() {
        if (html5QrCode && scannerActive) {
            html5QrCode.stop().catch(() => {});
        }
    });

    // Auto-uppercase serial input
    document.addEventListener('DOMContentLoaded', function() {
        const serialInput = document.getElementById('serialSearch');
        if (serialInput) {
            serialInput.addEventListener('blur', function() {
                this.value = this.value.trim().toUpperCase();
            });
        }
    });

    // Mobile responsive adjustments
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
</script>

</body>
</html>