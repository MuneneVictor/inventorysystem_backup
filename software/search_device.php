<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only software role and above can access
if (!in_array($_SESSION['role'], ['software', 'super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED. Only Software department can view this page.");
}

$user_role = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];

// Helper function to safely escape HTML
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$search_sn = trim($_GET['sn'] ?? '');
$device = null;
$maintenance_history = [];
$repair_history = [];

if ($search_sn) {
    // Fetch device with branch check (if not super_admin)
    $sql = "SELECT d.*, c.category_name, u.full_name AS added_by_name, d.branch
            FROM devices d
            JOIN categories c ON d.category_id = c.id
            LEFT JOIN users u ON d.added_by = u.id
            WHERE d.serial_number COLLATE utf8mb4_general_ci = ?";
    $params = [$search_sn];

    // Non-super_admin should only see devices in their branch
    if ($user_role !== 'super_admin') {
        $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_branch = $stmt->fetchColumn();
        if ($user_branch) {
            $sql .= " AND d.branch = ?";
            $params[] = $user_branch;
        }
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($device) {
        // Fetch maintenance history for device
        $mstmt = $conn->prepare("
            SELECT m.*, u.full_name AS performed_by_name
            FROM maintenance m
            LEFT JOIN users u ON m.performed_by = u.id
            WHERE m.device_serial COLLATE utf8mb4_general_ci = ?
            ORDER BY m.date_performed DESC
        ");
        $mstmt->execute([$search_sn]);
        $maintenance_history = $mstmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch repair history for device
        $rstmt = $conn->prepare("
            SELECT r.*, u1.full_name AS technician_name, u2.full_name AS given_by_name,
                   c.category_name
            FROM repairs r
            LEFT JOIN users u1 ON r.added_by = u1.id
            LEFT JOIN users u2 ON r.given_by = u2.id
            LEFT JOIN categories c ON r.category_id = c.id
            WHERE r.serial_number COLLATE utf8mb4_general_ci = ?
            ORDER BY r.date_added DESC
        ");
        $rstmt->execute([$search_sn]);
        $repair_history = $rstmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Search Device | Software Department</title>
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
        .breadcrumb a:hover { text-decoration: underline; }
        .user-info { margin-top: 0.5rem; color: var(--gray-500); font-size: 0.85rem; }
        .user-info i { color: var(--primary); }

        .search-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .search-form { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .search-input-group { flex: 1; min-width: 250px; }
        .search-input-group label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--gray-600); margin-bottom: 0.5rem; }
        .search-input-group input { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; transition: border-color 0.2s ease; }
        .search-input-group input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,75,42,0.1); }
        
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); transform: translateY(-1px); box-shadow: var(--shadow-md); }
        
        .result-card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); margin-bottom: 1.5rem; overflow: hidden; box-shadow: var(--shadow-sm); }
        .card-header { background: var(--gray-50); padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
        .card-header h3 { font-size: 1.1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .card-header h3 i { color: var(--primary); }
        .card-header .badge-count { background: var(--primary); color: white; padding: 0.15rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 500; margin-left: 0.5rem; }
        .card-body { padding: 1.5rem; }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; }
        .info-item { padding: 0.75rem 1rem; background: var(--gray-50); border-radius: var(--radius-lg); border: 1px solid var(--gray-200); }
        .info-label { font-size: 0.6rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 0.9rem; font-weight: 500; color: var(--gray-800); margin-top: 0.1rem; }
        .info-value code { background: var(--gray-100); padding: 0.1rem 0.4rem; border-radius: var(--radius-md); font-size: 0.8rem; }
        
        .table-wrapper { overflow-x: auto; margin-top: 0.5rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; min-width: 700px; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--gray-200); }
        th { background: var(--gray-50); font-weight: 600; color: var(--gray-600); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:hover { background: var(--gray-50); }
        
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; background: var(--gray-100); color: var(--gray-600); }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-secondary { background: var(--gray-200); color: var(--gray-600); }
        
        .status-indicator { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 0.4rem; }
        .status-instock { background: #10b981; }
        .status-under-repair { background: #f59e0b; }
        .status-faulty { background: #ef4444; }
        .status-sold { background: #6b7280; }
        .status-disposed { background: #4b5563; }
        
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .empty-state i { font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem; display: block; }
        .empty-state a { color: var(--primary); text-decoration: none; }
        .empty-state a:hover { text-decoration: underline; }
        
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .footer span { color: var(--primary); }
        
        .upgrade-arrow { color: #10b981; font-weight: 700; }
        .no-change { color: var(--gray-400); }
        
        .quick-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-200); }
        .link-btn { padding: 0.4rem 0.8rem; background: var(--primary); color: white !important; border-radius: var(--radius-md); text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 500; font-size: 0.85rem; transition: all 0.2s ease; border: none; cursor: pointer; }
        .link-btn:hover { background: var(--primary-light); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .link-btn-sm { padding: 0.25rem 0.6rem; font-size: 0.75rem; }

        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { 
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1.25rem; }
            .search-form { flex-direction: column; } 
            .btn { width: 100%; justify-content: center; }
            .info-grid { grid-template-columns: 1fr 1fr; }
            table { min-width: 600px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header { padding: 1rem; }
            .page-header h1 { font-size: 1.1rem; }
            .info-grid { grid-template-columns: 1fr; }
            table { min-width: 500px; }
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-search"></i> Search Device</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'inventory_admin'): ?>
                <a href="../dashboard/inventorydashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="../dashboard/softwaredashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Search Device</span>
        </div>
        <div class="user-info">
            <i class="fas fa-user"></i> <?= safe($greeting) ?>, <?= safe(explode(' ', $user_name)[0]) ?>
            <?php if (isset($user_branch) && $user_branch): ?>
                <span style="margin-left:1rem;"><i class="fas fa-store"></i> <?= safe($user_branch) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="search-section">
        <form method="GET" class="search-form" id="searchForm">
            <div class="search-input-group">
                <label><i class="fas fa-qrcode"></i> Serial Number</label>
                <input type="text" name="sn" id="serial_number" placeholder="Scan or type serial number..." value="<?= safe($search_sn) ?>" autofocus>
            </div>
            <button type="submit" class="btn btn-primary" id="searchBtn"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>

    <?php if ($search_sn && !$device): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <p>Device not found or you do not have permission.</p>
            <p style="font-size:0.85rem; margin-top:0.5rem; color:var(--gray-400);">
                <a href="../software/update_specs.php?sn=<?= urlencode($search_sn) ?>" style="color:var(--primary);">
                    <i class="fas fa-plus-circle"></i> Add this device for maintenance?
                </a>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($device): ?>
        <!-- Device Details -->
        <div class="result-card">
            <div class="card-header">
                <h3><i class="fas fa-laptop"></i> Device Details</h3>
                <span class="badge <?= $device['status'] === 'In Stock' ? 'badge-success' : ($device['status'] === 'Under Repair' ? 'badge-warning' : 'badge-secondary') ?>">
                    <span class="status-indicator status-<?= strtolower(str_replace(' ', '-', $device['status'])) ?>"></span>
                    <?= safe($device['status']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Serial Number</div>
                        <div class="info-value"><code><?= safe($device['serial_number']) ?></code></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Category</div>
                        <div class="info-value"><?= safe($device['category_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Model</div>
                        <div class="info-value"><?= safe($device['model_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Processor</div>
                        <div class="info-value"><?= safe($device['processor']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Graphics</div>
                        <div class="info-value"><?= safe($device['graphics'] ?? 'None') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">RAM</div>
                        <div class="info-value"><?= safe($device['ram']) ?> GB</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Storage</div>
                        <div class="info-value"><?= safe($device['storage_type'] . ' ' . $device['storage_capacity'] . ' GB') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Touch</div>
                        <div class="info-value"><?= safe($device['touch'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Branch</div>
                        <div class="info-value"><?= safe($device['branch']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Condition</div>
                        <div class="info-value"><?= safe($device['device_condition'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Added By</div>
                        <div class="info-value"><?= safe($device['added_by_name'] ?? 'Unknown') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date Added</div>
                        <div class="info-value"><?= date('M j, Y H:i', strtotime($device['date_added'])) ?></div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="../software/update_specs.php?sn=<?= urlencode($device['serial_number']) ?>" class="link-btn">
                        <i class="fas fa-microchip"></i> Update Specs
                    </a>
                    <?php if ($device['status'] === 'In Stock'): ?>
                        <a href="../repairs/add_repair.php?mode=instock&sn=<?= urlencode($device['serial_number']) ?>" class="link-btn link-btn-sm" style="background:#f59e0b;">
                            <i class="fas fa-tools"></i> Add to Repair
                        </a>
                    <?php endif; ?>
                    <a href="../devices/view_device.php?serial=<?= urlencode($device['serial_number']) ?>" class="link-btn link-btn-sm">
                        <i class="fas fa-eye"></i> Full Details
                    </a>
                </div>
            </div>
        </div>

        <!-- Maintenance History -->
        <div class="result-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-history"></i> Maintenance History
                    <span class="badge-count"><?= count($maintenance_history) ?></span>
                </h3>
            </div>
            <div class="card-body">
                <?php if (empty($maintenance_history)): ?>
                    <div class="empty-state" style="padding:1.5rem;">
                        <i class="fas fa-clipboard-list" style="font-size:2rem;"></i>
                        <p style="margin-top:0.5rem;">No maintenance records for this device.</p>
                        <p style="font-size:0.85rem; color:var(--gray-400);">
                            <a href="../software/update_specs.php?sn=<?= urlencode($device['serial_number']) ?>" style="color:var(--primary);">
                                <i class="fas fa-plus-circle"></i> Perform first update
                            </a>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>RAM</th>
                                    <th>Storage</th>
                                    <th>Graphics</th>
                                    <th>Performed By</th>
                                    <th>Notes</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($maintenance_history as $m): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <?= safe($m['old_ram']) ?> GB 
                                        <i class="fas fa-arrow-right" style="font-size:0.6rem; color:var(--gray-400);"></i> 
                                        <strong class="<?= $m['new_ram'] > $m['old_ram'] ? 'upgrade-arrow' : '' ?>">
                                            <?= safe($m['new_ram']) ?> GB
                                            <?php if ($m['new_ram'] > $m['old_ram']): ?>↑<?php endif; ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?= safe($m['old_storage']) ?> GB 
                                        <i class="fas fa-arrow-right" style="font-size:0.6rem; color:var(--gray-400);"></i> 
                                        <strong class="<?= $m['new_storage'] > $m['old_storage'] ? 'upgrade-arrow' : '' ?>">
                                            <?= safe($m['new_storage']) ?> GB
                                            <?php if ($m['new_storage'] > $m['old_storage']): ?>↑<?php endif; ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php 
                                        $oldG = $m['old_graphics'] ?? '-';
                                        $newG = $m['new_graphics'] ?? '-';
                                        if ($oldG !== $newG && $oldG !== '-' && $newG !== '-'): 
                                        ?>
                                            <?= safe($oldG) ?> → <?= safe($newG) ?>
                                        <?php else: ?>
                                            <span class="no-change"><?= safe($oldG) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= safe($m['performed_by_name'] ?? '-') ?></td>
                                    <td><?= safe($m['notes'] ?? '-') ?></td>
                                    <td><?= date('M j, Y H:i', strtotime($m['date_performed'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Repair History -->
        <div class="result-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-tools"></i> Repair History
                    <span class="badge-count"><?= count($repair_history) ?></span>
                </h3>
            </div>
            <div class="card-body">
                <?php if (empty($repair_history)): ?>
                    <div class="empty-state" style="padding:1.5rem;">
                        <i class="fas fa-clipboard-list" style="font-size:2rem;"></i>
                        <p style="margin-top:0.5rem;">No repair records for this device.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Problem</th>
                                    <th>Status</th>
                                    <th>Source</th>
                                    <th>Given By</th>
                                    <th>Technician</th>
                                    <th>Cost</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($repair_history as $r): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= safe(substr($r['problem_description'] ?? '-', 0, 40)) . (strlen($r['problem_description'] ?? '') > 40 ? '...' : '') ?></td>
                                    <td>
                                        <span class="badge <?= ($r['fix_status'] ?? '') === 'Fixed' ? 'badge-success' : (($r['fix_status'] ?? '') === 'pending' ? 'badge-warning' : 'badge-secondary') ?>">
                                            <?= safe($r['fix_status'] ?? 'Unknown') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $sourceLabels = ['instock' => 'In Stock', 'return' => 'Return', 'client' => 'Client'];
                                        $source = $sourceLabels[$r['source_device'] ?? ''] ?? 'Unknown';
                                        ?>
                                        <span class="badge <?= ($r['source_device'] ?? '') === 'instock' ? 'badge-success' : (($r['source_device'] ?? '') === 'return' ? 'badge-warning' : 'badge-info') ?>">
                                            <?= safe($source) ?>
                                        </span>
                                    </td>
                                    <td><?= safe($r['given_by_name'] ?? '-') ?></td>
                                    <td><?= safe($r['technician_name'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($r['repair_cost']) && $r['repair_cost'] > 0): ?>
                                            <strong style="color:#065f46;">KES <?= number_format($r['repair_cost'], 2) ?></strong>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($r['date_added'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> <span>Mombasa Computers</span>. All rights reserved.
        <span style="margin:0 0.5rem;">•</span>
        <span>v2.0.0</span>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('serial_number');
    const searchBtn = document.getElementById('searchBtn');
    const searchForm = document.getElementById('searchForm');

    // Enter key support
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchBtn.click();
        }
    });

    // Search button
    searchBtn.addEventListener('click', function() {
        let sn = searchInput.value.trim();
        if (sn) {
            window.location.href = '?sn=' + encodeURIComponent(sn);
        }
    });

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
    adjustMainContent();
});
</script>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>