<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// ============================================================
// ROLE-BASED ACCESS CONTROL
// ============================================================
$user_role = $_SESSION['role'] ?? '';
$user_id = (int) ($_SESSION['user_id'] ?? 0);

// Allowed roles: technician, super_admin, inventory_admin, manager
if (!in_array($user_role, ['technician', 'super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED.");
}

// Get user branch for filtering
$user_branch = null;
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
    if (!$user_branch) {
        die("Your account has no branch assigned. Contact administrator.");
    }
}

// ============================================================
// FILTER INPUTS
// ============================================================
$filter_serial = trim($_GET['serial'] ?? '');
$filter_client = trim($_GET['client'] ?? '');
$filter_source = trim($_GET['source'] ?? '');
$filter_branch = trim($_GET['branch'] ?? '');
$filter_start = trim($_GET['start_date'] ?? '');
$filter_end = trim($_GET['end_date'] ?? '');

// ============================================================
// BUILD QUERY BASED ON ROLE AND FILTERS
// ============================================================
$sql = "SELECT r.*, 
               COALESCE(d.model_name, r.model_name) AS model_name,
               c.category_name,
               d.processor, d.ram, d.storage_type, d.storage_capacity, 
               d.touch, d.graphics,
               u1.full_name AS added_by_name, 
               u2.full_name AS given_by_name,
               u3.full_name AS sales_person_name
        FROM repairs r
        LEFT JOIN devices d ON r.serial_number COLLATE utf8mb4_general_ci = d.serial_number
        LEFT JOIN categories c ON COALESCE(d.category_id, r.category_id) = c.id
        LEFT JOIN users u1 ON r.added_by = u1.id
        LEFT JOIN users u2 ON r.given_by = u2.id
        LEFT JOIN users u3 ON r.sales_person = u3.id
        WHERE r.fix_status = 'pending'";

$params = [];

// Role-based filtering
if ($user_role === 'technician') {
    $sql .= " AND r.added_by = ?";
    $params[] = $user_id;
} elseif ($user_role === 'inventory_admin') {
    $sql .= " AND r.given_by = ?";
    $params[] = $user_id;
} elseif ($user_role === 'manager') {
    if ($user_branch) {
        $sql .= " AND r.branch = ?";
        $params[] = $user_branch;
    }
} elseif ($user_role === 'super_admin') {
    // Super admin: exclude client repairs
    $sql .= " AND (r.source_device IS NULL OR r.source_device != 'client')";
}

// Apply filters
if (!empty($filter_serial)) {
    $sql .= " AND r.serial_number LIKE ?";
    $params[] = "%$filter_serial%";
}

if (!empty($filter_client)) {
    $sql .= " AND r.client_name LIKE ?";
    $params[] = "%$filter_client%";
}

if (!empty($filter_source)) {
    $sql .= " AND r.source_device = ?";
    $params[] = $filter_source;
}

if (!empty($filter_branch) && $user_role === 'super_admin') {
    $sql .= " AND r.branch = ?";
    $params[] = $filter_branch;
}

if (!empty($filter_start) && !empty($filter_end)) {
    $sql .= " AND DATE(r.date_added) BETWEEN ? AND ?";
    $params[] = $filter_start;
    $params[] = $filter_end;
} elseif (!empty($filter_start)) {
    $sql .= " AND DATE(r.date_added) >= ?";
    $params[] = $filter_start;
} elseif (!empty($filter_end)) {
    $sql .= " AND DATE(r.date_added) <= ?";
    $params[] = $filter_end;
}

$sql .= " ORDER BY r.date_added DESC";

// ============================================================
// EXECUTE QUERY
// ============================================================
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// GET UNIQUE VALUES FOR FILTER DROPDOWNS
// ============================================================
// Get unique sources from repairs
$sourceStmt = $conn->query("SELECT DISTINCT source_device FROM repairs WHERE source_device IS NOT NULL");
$sources = $sourceStmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique branches (for super admin)
$branches = [];
if ($user_role === 'super_admin') {
    $branchStmt = $conn->query("SELECT DISTINCT branch FROM repairs WHERE branch IS NOT NULL ORDER BY branch");
    $branches = $branchStmt->fetchAll(PDO::FETCH_COLUMN);
}

// ============================================================
// HANDLE SUCCESS MESSAGE
// ============================================================
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// ============================================================
// TIME GREETING
// ============================================================
date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// ============================================================
// HELPER FUNCTION FOR SOURCE DISPLAY
// ============================================================
function getSourceDisplay($source) {
    $sources = [
        'instock' => ['label' => 'In Stock', 'class' => 'badge-instock', 'icon' => 'fa-warehouse'],
        'return' => ['label' => 'Return', 'class' => 'badge-return', 'icon' => 'fa-undo-alt'],
        'client' => ['label' => 'Client', 'class' => 'badge-client', 'icon' => 'fa-user']
    ];
    return $sources[$source] ?? ['label' => 'Unknown', 'class' => 'badge-unknown', 'icon' => 'fa-question-circle'];
}

// Helper function to safely escape HTML
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Source options for filter
$sourceOptions = [
    'instock' => 'In Stock',
    'return' => 'Return',
    'client' => 'Client'
];

// Check if filters are applied
$hasFilters = !empty($filter_serial) || !empty($filter_client) || !empty($filter_source) || 
              !empty($filter_branch) || !empty($filter_start) || !empty($filter_end);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Under Repair | Mombasa Computers</title>
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
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
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
            background: var(--gray-100);
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
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i {
            color: var(--primary);
            font-size: 1.75rem;
        }

        .breadcrumb {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb a:hover { text-decoration: underline; }

        .user-info {
            margin-top: 0.5rem;
            color: var(--gray-500);
            font-size: 0.85rem;
        }
        .user-info i {
            color: var(--primary);
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* ===== FILTER SECTION ===== */
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .filter-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-title i {
            color: var(--primary);
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: var(--primary);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .filter-group label i {
            color: var(--primary);
            font-size: 0.7rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.6rem 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-family: var(--font-sans);
            background: white;
            transition: all 0.2s ease;
            width: 100%;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

        .filter-group input::placeholder {
            color: var(--gray-400);
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.6rem 1.25rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 0.35rem 0.8rem;
            font-size: 0.75rem;
        }

        /* Active filters display */
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--gray-100);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }

        .filter-tag .remove {
            color: var(--gray-500);
            cursor: pointer;
            text-decoration: none;
            font-size: 0.7rem;
        }

        .filter-tag .remove:hover {
            color: #ef4444;
        }

        /* Stats Row */
        .stats-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .stat-card {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            flex: 1;
            min-width: 140px;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-card .stat-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Table */
        .table-wrapper {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            overflow-x: auto;
            box-shadow: var(--shadow-sm);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            background: var(--gray-50);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
            font-size: 0.9rem;
        }

        tr:hover {
            background: var(--gray-50);
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--gray-100);
        }

        .badge-instock {
            background: #dcfce7;
            color: #065f46;
        }

        .badge-return {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-client {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-unknown {
            background: var(--gray-200);
            color: var(--gray-600);
        }

        .source-icon {
            margin-right: 0.3rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }

        .empty-state a {
            color: var(--primary);
            text-decoration: none;
        }

        .empty-state a:hover {
            text-decoration: underline;
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

        .view-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .role-view-only {
            background: #fef3c7;
            color: #92400e;
            padding: 0.3rem 0.8rem;
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-sm {
            padding: 0.25rem 0.6rem;
            font-size: 0.7rem;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 1.5rem 1rem 1rem !important;
                padding-top: 5rem !important;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem 0.75rem 0.75rem !important;
                padding-top: 4.5rem !important;
            }

            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1.25rem; }

            .filter-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.75rem;
            }

            .filter-actions {
                flex-direction: column;
                width: 100%;
            }

            .filter-actions .btn {
                width: 100%;
                justify-content: center;
            }

            .stats-row {
                flex-direction: column;
            }

            table {
                min-width: 800px;
            }

            .active-filters {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem 0.5rem 0.5rem !important;
                padding-top: 4rem !important;
            }
            .page-header { padding: 1rem; }
            .page-header h1 { font-size: 1.1rem; }
            .filter-grid {
                grid-template-columns: 1fr;
            }
            table { min-width: 700px; }
        }
    </style>
</head>
<body>
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-tools"></i> Devices Under Repair</h1>
            <div class="breadcrumb">
                <?php if ($user_role === 'super_admin'): ?>
                    <a href="../dashboard/superadmindashboard.php">Dashboard</a>
                <?php elseif ($user_role === 'manager'): ?>
                    <a href="../dashboard/managerdashboard.php">Dashboard</a>
                <?php elseif ($user_role === 'inventory_admin'): ?>
                    <a href="../dashboard/inventorydashboard.php">Dashboard</a>
                <?php else: ?>
                    <a href="../dashboard/techniciandashboard.php">Dashboard</a>
                <?php endif; ?>
                <span> / </span>
                <span>Under Repair</span>
            </div>
            <div class="user-info">
                <i class="fas fa-store"></i> Branch: <?= safe($user_branch ?? 'All Branches') ?> &nbsp;&nbsp;|&nbsp;&nbsp;
                <i class="fas fa-user"></i> <?= safe($greeting) ?>, <?= safe(explode(' ', $user_name)[0]) ?> &nbsp;&nbsp;|&nbsp;&nbsp;
                <i class="fas fa-eye"></i> View: 
                <span class="view-badge">
                    <?php 
                    if ($user_role === 'technician') echo 'My Repairs';
                    elseif ($user_role === 'inventory_admin') echo 'Given By Me';
                    elseif ($user_role === 'manager') echo 'Branch: ' . safe($user_branch);
                    elseif ($user_role === 'super_admin') echo 'All';
                    else echo 'All Repairs';
                    ?>
                </span>
                <?php if ($user_role === 'super_admin'): ?>
                    <span class="role-view-only"><i class="fas fa-eye"></i> View Only</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= safe($success_message) ?></span>
            </div>
        <?php endif; ?>

        <!-- ===== FILTER SECTION ===== -->
        <div class="filter-section">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="fas fa-filter"></i> Filter Repairs
                    <?php if ($hasFilters): ?>
                        <span class="filter-badge">
                            <i class="fas fa-check"></i> Filtered
                        </span>
                    <?php endif; ?>
                </div>
                <div>
                    <span style="font-size:0.75rem; color:var(--gray-500);">
                        <i class="fas fa-"></i> <?= count($repairs) ?> results
                    </span>
                </div>
            </div>

            <form method="GET" class="filter-grid">
                <!-- Serial Number -->
                <div class="filter-group">
                    <label><i class="fas fa-hashtag"></i> Serial Number</label>
                    <input type="text" name="serial" placeholder="Search serial..." value="<?= safe($filter_serial) ?>">
                </div>

                <!-- Client Name -->
                <div class="filter-group">
                    <label><i class="fas fa-user"></i> Client Name</label>
                    <input type="text" name="client" placeholder="Search client..." value="<?= safe($filter_client) ?>">
                </div>

                <!-- Source -->
                <div class="filter-group">
                    <label><i class="fas fa-tag"></i> Source</label>
                    <select name="source">
                        <option value="">All Sources</option>
                        <?php foreach ($sourceOptions as $key => $label): ?>
                            <option value="<?= safe($key) ?>" <?= $filter_source == $key ? 'selected' : '' ?>>
                                <?= safe($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Branch (Super Admin only) -->
                <?php if ($user_role === 'super_admin'): ?>
                    <div class="filter-group">
                        <label><i class="fas fa-store"></i> Branch</label>
                        <select name="branch">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= safe($b) ?>" <?= $filter_branch == $b ? 'selected' : '' ?>>
                                    <?= safe($b) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Date Range -->
                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt"></i> From Date</label>
                    <input type="date" name="start_date" value="<?= safe($filter_start) ?>">
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt"></i> To Date</label>
                    <input type="date" name="end_date" value="<?= safe($filter_end) ?>">
                </div>

                <!-- Actions -->
                <div class="filter-group" style="grid-column: span 1;">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <?php if ($hasFilters): ?>
                            <a href="under_repair.php" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Active Filters Display -->
            <?php if ($hasFilters): ?>
                <div class="active-filters">
                    <span style="font-size:0.75rem; font-weight:600; color:var(--gray-500);">Active Filters:</span>
                    
                    <?php if (!empty($filter_serial)): ?>
                        <span class="filter-tag">
                            <i class="fas fa-hashtag"></i> Serial: <?= safe($filter_serial) ?>
                            <a href="?<?= http_build_query(array_diff_key($_GET, ['serial' => ''])) ?>" class="remove">
                                <i class="fas fa-times-circle"></i>
                            </a>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($filter_client)): ?>
                        <span class="filter-tag">
                            <i class="fas fa-user"></i> Client: <?= safe($filter_client) ?>
                            <a href="?<?= http_build_query(array_diff_key($_GET, ['client' => ''])) ?>" class="remove">
                                <i class="fas fa-times-circle"></i>
                            </a>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($filter_source)): ?>
                        <span class="filter-tag">
                            <i class="fas fa-tag"></i> Source: <?= safe($sourceOptions[$filter_source] ?? $filter_source) ?>
                            <a href="?<?= http_build_query(array_diff_key($_GET, ['source' => ''])) ?>" class="remove">
                                <i class="fas fa-times-circle"></i>
                            </a>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($filter_branch) && $user_role === 'super_admin'): ?>
                        <span class="filter-tag">
                            <i class="fas fa-store"></i> Branch: <?= safe($filter_branch) ?>
                            <a href="?<?= http_build_query(array_diff_key($_GET, ['branch' => ''])) ?>" class="remove">
                                <i class="fas fa-times-circle"></i>
                            </a>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($filter_start) || !empty($filter_end)): ?>
                        <span class="filter-tag">
                            <i class="fas fa-calendar-alt"></i> 
                            <?php if (!empty($filter_start) && !empty($filter_end)): ?>
                                <?= safe($filter_start) ?> → <?= safe($filter_end) ?>
                            <?php elseif (!empty($filter_start)): ?>
                                From: <?= safe($filter_start) ?>
                            <?php else: ?>
                                To: <?= safe($filter_end) ?>
                            <?php endif; ?>
                            <a href="?<?= http_build_query(array_diff_key($_GET, ['start_date' => '', 'end_date' => ''])) ?>" class="remove">
                                <i class="fas fa-times-circle"></i>
                            </a>
                        </span>
                    <?php endif; ?>

                    <a href="under_repair.php" class="filter-tag" style="background:#fee2e2; border-color:#fecaca; color:#991b1b;">
                        <i class="fas fa-undo"></i> Clear All
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== STATS ROW ===== -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?= count($repairs) ?></div>
                <div class="stat-label"><i class="fas fa-tools"></i> Total Pending</div>
            </div>
            <?php 
            $sourceCounts = [];
            foreach ($repairs as $r) {
                $source = $r['source_device'] ?? 'unknown';
                $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;
            }
            ?>
            <?php foreach ($sourceCounts as $source => $count): 
                $sourceInfo = getSourceDisplay($source);
            ?>
            <div class="stat-card">
                <div class="stat-value" style="font-size:1.2rem;">
                    <span class="badge <?= $sourceInfo['class'] ?>" style="font-size:1rem; padding:0.3rem 0.8rem;">
                        <i class="fas <?= $sourceInfo['icon'] ?>"></i> <?= $count ?>
                    </span>
                </div>
                <div class="stat-label"><?= $sourceInfo['label'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ===== TABLE ===== -->
        <div class="table-wrapper">
            <?php if (empty($repairs)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>No devices currently under repair.</p>
                    <?php if ($user_role === 'technician'): ?>
                        <p style="font-size:0.85rem; margin-top:0.5rem; color:var(--gray-400);">
                            <a href="add_repair.php">
                                <i class="fas fa-plus-circle"></i> Add a new repair
                            </a>
                        </p>
                    <?php endif; ?>
                    <?php if ($hasFilters): ?>
                        <p style="font-size:0.85rem; margin-top:0.5rem; color:var(--gray-400);">
                            <a href="under_repair.php" style="color:var(--primary);">
                                <i class="fas fa-undo"></i> Clear filters to see all
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Serial</th>
                            <th>Category</th>
                            <th>Model</th>
                            <th>Source</th>
                            <th>Client</th>
                            <th>Problem</th>
                            <th>Given By</th>
                            <?php if (in_array($user_role, ['super_admin', 'manager'])): ?>
                                <th>Added By</th>
                                <th>Branch</th>
                            <?php endif; ?>
                            <?php if ($user_role === 'super_admin'): ?>
                                <th>Sales Person</th>
                            <?php endif; ?>
                            <th>Date Added</th>
                            <?php if ($user_role === 'technician'): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($repairs as $r): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><code><?= !empty($r['serial_number']) ? safe($r['serial_number']) : '-' ?></code></td>
                            <td><span class="badge"><?= safe($r['category_name'] ?? 'N/A') ?></span></td>
                            <td><?= safe($r['model_name'] ?? 'N/A') ?></td>
                            <td>
                                <?php 
                                $sourceInfo = getSourceDisplay($r['source_device'] ?? '');
                                ?>
                                <span class="badge <?= $sourceInfo['class'] ?>">
                                    <i class="fas <?= $sourceInfo['icon'] ?> source-icon"></i>
                                    <?= $sourceInfo['label'] ?>
                                </span>
                            </td>
                            <td><?= safe($r['client_name'] ?? 'N/A') ?></td>
                            <td><?= safe(substr($r['problem_description'] ?? '', 0, 40)) . (strlen($r['problem_description'] ?? '') > 40 ? '...' : '') ?></td>
                            <td><?= safe($r['given_by_name'] ?? 'N/A') ?></td>
                            <?php if (in_array($user_role, ['super_admin', 'manager'])): ?>
                                <td><?= safe($r['added_by_name'] ?? 'Unknown') ?></td>
                                <td><span class="badge"><?= safe($r['branch'] ?? 'N/A') ?></span></td>
                            <?php endif; ?>
                            <?php if ($user_role === 'super_admin'): ?>
                                <td><?= safe($r['sales_person_name'] ?? 'N/A') ?></td>
                            <?php endif; ?>
                            <td><?= date('M j, Y H:i', strtotime($r['date_added'])) ?></td>
                            <?php if ($user_role === 'technician'): ?>
                                <td>
                                    <a href="complete_repair.php?id=<?= $r['id'] ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Complete
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- ===== INFO BOXES ===== -->
        <?php if ($user_role === 'super_admin' && !empty($repairs)): ?>
       
        <?php endif; ?>

        <?php if (!empty($repairs) && $user_role === 'technician'): ?>
        <div style="margin-top:1rem; padding:0.75rem 1rem; background:#fef3c7; border-radius:var(--radius-lg); border:1px solid #fde68a; display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
            <i class="fas fa-info-circle" style="color:#92400e;"></i>
            <span style="color:#92400e; font-size:0.85rem;">
                <strong>Tip:</strong> Click the "Complete" button to complete a repair and mark it as fixed.
            </span>
        </div>
        <?php endif; ?>

        <!-- ===== FOOTER ===== -->
        <div class="footer">
            <i class="fas fa-copyright"></i> <?= date('Y'); ?> <span>Mombasa Computers</span>. All rights reserved.
            <span style="margin:0 0.5rem;">•</span>
            <span>v2.0.0</span>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function adjustMainContent() {
            var mainContent = document.querySelector('.main-content');
            if (window.innerWidth <= 1200) {
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.width = '100%';
                    mainContent.style.paddingTop = '5rem';
                }
            } else {
                if (mainContent) {
                    mainContent.style.marginLeft = '260px';
                    mainContent.style.width = 'calc(100% - 260px)';
                    mainContent.style.paddingTop = '';
                }
            }
        }

        adjustMainContent();
        window.addEventListener('resize', adjustMainContent);
        window.addEventListener('orientationchange', adjustMainContent);
    });
    </script>
</body>
</html>