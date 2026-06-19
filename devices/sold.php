<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin','manager'])) {
    die("Access denied!");
}

// Get manager's branch from database
$user_branch = '';
if ($role === 'manager') {
    $user_stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_branch = $user_data['branch'] ?? '';
}

// Handle search inputs
$search_sn = trim($_GET['sn'] ?? '');
$search_time = trim($_GET['time_period'] ?? '');
$search_salesperson = trim($_GET['salesperson'] ?? '');
$search_branch = trim($_GET['branch'] ?? '');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

// Fetch salespersons for dropdown - using prepared statements
if ($role === 'manager' && !empty($user_branch)) {
    $users_stmt = $conn->prepare("SELECT id, full_name, branch FROM users WHERE role='sales' AND branch = ? ORDER BY full_name ASC");
    $users_stmt->execute([$user_branch]);
} else {
    $users_stmt = $conn->prepare("SELECT id, full_name, branch FROM users WHERE role='sales' ORDER BY full_name ASC");
    $users_stmt->execute();
}
$salespersons = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique branches from salespersons
$branches = [];
foreach($salespersons as $sp) {
    if($sp['branch'] && !in_array($sp['branch'], $branches)) {
        $branches[] = $sp['branch'];
    }
}
sort($branches);

// Base query with prepared statements
$sql = "SELECT sd.*, c.category_name, u.full_name AS sold_by_name, u.branch
        FROM devices sd
        JOIN categories c ON sd.category_id = c.id
        JOIN users u ON sd.sold_by = u.id
        WHERE 1";

$params = [];

// Manager restriction
if ($role === 'manager' && !empty($user_branch)) {
    $sql .= " AND u.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}

// Sales role: only show devices they sold
if($role === 'sales'){
    $sql .= " AND sd.sold_by = :uid";
    $params['uid'] = $user_id;
}

// Search by serial number
if($search_sn){
    $sql .= " AND sd.serial_number LIKE :sn";
    $params['sn'] = "%$search_sn%";
}

// Filter by salesperson
if($search_salesperson){
    $sql .= " AND sd.sold_by = :salesperson";
    $params['salesperson'] = $search_salesperson;
}

// Filter by branch - only for non-managers
if($search_branch && $role !== 'manager'){
    $sql .= " AND u.branch = :branch";
    $params['branch'] = $search_branch;
}

// Filter by time period
if($search_time){
    switch($search_time){
        case 'today':
            $sql .= " AND DATE(sd.sold_at) = CURDATE()";
            break;
        case 'this_week':
            $sql .= " AND YEARWEEK(sd.sold_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'this_month':
            $sql .= " AND YEAR(sd.sold_at) = YEAR(CURDATE()) AND MONTH(sd.sold_at) = MONTH(CURDATE())";
            break;
        case 'last_month':
            $sql .= " AND YEAR(sd.sold_at) = YEAR(CURDATE() - INTERVAL 1 MONTH) 
                      AND MONTH(sd.sold_at) = MONTH(CURDATE() - INTERVAL 1 MONTH)";
            break;
        case 'this_year':
            $sql .= " AND YEAR(sd.sold_at) = YEAR(CURDATE())";
            break;
        case 'custom':
            if ($start_date && $end_date) {
                $sql .= " AND DATE(sd.sold_at) BETWEEN :start_date AND :end_date";
                $params['start_date'] = $start_date;
                $params['end_date'] = $end_date;
            }
            break;
    }
}

$sql .= " ORDER BY sd.sold_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_sales = count($devices);
$total_revenue = array_sum(array_column($devices, 'price'));
$avg_price = $total_sales > 0 ? $total_revenue / $total_sales : 0;

// Determine if download button should be shown
$showDownloadButton = ($search_time && !$search_salesperson) || ($search_branch && !$search_salesperson);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sold Devices | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --font-sans: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
            overflow-x: hidden;
        }

        /* Main Content Area */
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

        /* Page Header */
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

        .breadcrumb {
            color: var(--gray-500);
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-card .stat-icon {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .stat-card .stat-label {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        /* Search Section */
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
        }

        .search-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-600);
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
        }

        .search-group input:focus,
        .search-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

        .date-range-group {
            grid-column: span 2;
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }

        .date-range-group .search-group {
            flex: 1;
        }

        .search-actions {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        /* Buttons */
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

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .btn-download {
            background: #2563eb;
            color: white;
        }

        .btn-download:hover {
            background: #1d4ed8;
        }

        /* Table Styles */
        .table-wrapper {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

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
            white-space: nowrap;
        }

        td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            vertical-align: middle;
        }

        tr:hover {
            background: var(--gray-50);
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--gray-100);
            color: var(--gray-600);
        }

        /* Serial number */
        .serial-code {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: var(--gray-50);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            display: inline-block;
        }

        /* Price */
        .price {
            font-weight: 600;
            color: #059669;
        }

        /* Branch colors */
        .branch-kimathi {
            color: #059669;
            font-weight: 500;
        }

        .branch-moi {
            color: #3b82f6;
            font-weight: 500;
        }

        /* Action links */
        .action-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .action-link:hover {
            text-decoration: underline;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Download bar */
        .download-bar {
            background: var(--gray-50);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: flex-end;
            border: 1px solid var(--gray-200);
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

        /* Hide date range initially */
        .date-range-group {
            display: none;
        }

        .date-range-group.active {
            display: flex;
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

            .page-header h1 {
                font-size: 1.25rem;
            }

            .page-header {
                padding: 1rem 1.25rem;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-card .stat-value {
                font-size: 1.5rem;
            }

            .search-section {
                padding: 1rem;
            }

            .search-grid {
                grid-template-columns: 1fr;
            }

            .date-range-group {
                flex-direction: column;
                gap: 0.5rem;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .download-bar {
                flex-direction: column;
                gap: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem 0.5rem 0.5rem !important;
                padding-top: 4rem !important;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 1.1rem;
            }
        }
    </style>
    <script>
        function autoApplyFilter() {
            document.getElementById('filterForm').submit();
        }

        function toggleDateRange() {
            const timePeriod = document.getElementById('time_period').value;
            const dateRangeDiv = document.getElementById('dateRangeGroup');
            
            if (timePeriod === 'custom') {
                dateRangeDiv.classList.add('active');
            } else {
                dateRangeDiv.classList.remove('active');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleDateRange();
        });
    </script>
</head>
<body>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-money-bill-wave"></i>
            Sold Devices
        </h1>
        <div class="breadcrumb">
              <?php if($_SESSION['role'] === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>       
            <?php endif; ?>
            <?php if($_SESSION['role'] === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <?php if($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Sold Devices</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-value"><?= number_format($total_sales) ?></div>
            <div class="stat-label">Total Sales</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-value">KES <?= number_format($total_revenue, 0) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-simple"></i></div>
            <div class="stat-value">KES <?= number_format($avg_price, 0) ?></div>
            <div class="stat-label">Average Price</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?= number_format(count($salespersons)) ?></div>
            <div class="stat-label">Sales Staff</div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-title">
            <i class="fas fa-filter"></i> Filter Sales
        </div>
        <form method="GET" id="filterForm" class="search-grid">
            <div class="search-group">
                <label>Serial Number</label>
                <input type="text" name="sn" placeholder="Scan or type serial number" 
                       value="<?= htmlspecialchars($search_sn) ?>" autofocus>
            </div>

            <div class="search-group">
                <label>Time Period</label>
                <select name="time_period" id="time_period" onchange="toggleDateRange(); autoApplyFilter();">
                    <option value="">-- All Periods --</option>
                    <option value="today" <?= $search_time==='today'?'selected':'' ?>>Today</option>
                    <option value="this_week" <?= $search_time==='this_week'?'selected':'' ?>>This Week</option>
                    <option value="this_month" <?= $search_time==='this_month'?'selected':'' ?>>This Month</option>
                    <option value="last_month" <?= $search_time==='last_month'?'selected':'' ?>>Last Month</option>
                    <option value="this_year" <?= $search_time==='this_year'?'selected':'' ?>>This Year</option>
                    <option value="custom" <?= $search_time==='custom'?'selected':'' ?>>Custom Range</option>
                </select>
            </div>

            <div id="dateRangeGroup" class="date-range-group <?= $search_time==='custom'?'active':'' ?>">
                <div class="search-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" onchange="autoApplyFilter()">
                </div>
                <div class="search-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" onchange="autoApplyFilter()">
                </div>
            </div>

            <div class="search-group">
                <label>Salesperson</label>
                <select name="salesperson" onchange="autoApplyFilter()">
                    <option value="">-- All Salespersons --</option>
                    <?php foreach($salespersons as $sp): ?>
                        <option value="<?= $sp['id'] ?>" <?= $search_salesperson==$sp['id']?'selected':'' ?>>
                            <?= htmlspecialchars($sp['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($role !== 'manager'): ?>
            <div class="search-group">
                <label>Branch</label>
                <select name="branch" onchange="autoApplyFilter()">
                    <option value="">-- All Branches --</option>
                    <?php foreach($branches as $branch): ?>
                        <option value="<?= htmlspecialchars($branch) ?>" <?= $search_branch==$branch?'selected':'' ?>>
                            <?= htmlspecialchars($branch) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="search-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="sold_devices.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Download Button -->
    <?php if($showDownloadButton && !empty($devices)): ?>
    <div class="download-bar">
        <form method="POST" action="download_sales_report.php" style="margin:0">
            <input type="hidden" name="time_period" value="<?= htmlspecialchars($search_time) ?>">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            <input type="hidden" name="salesperson" value="<?= htmlspecialchars($search_salesperson) ?>">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($search_branch) ?>">
            <input type="hidden" name="sn" value="<?= htmlspecialchars($search_sn) ?>">
            <button type="submit" class="btn btn-download">
                <i class="fas fa-download"></i> Download PDF Report
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Sales Table -->
    <div class="table-wrapper">
        <div class="table-responsive">
            <?php if(empty($devices)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <p>No sold devices found matching your criteria.</p>
                    <a href="sold_devices.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-undo"></i> Clear Filters
                    </a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Serial Number</th>
                            <th>Category</th>
                            <th>Model</th>
                            <th>Processor</th>
                            <th>RAM</th>
                            <th>Storage</th>
                            <th>Price</th>
                            <th>Sold By</th>
                            <th>Branch</th>
                            <th>Date Sold</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i = 1; foreach($devices as $d): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><span class="serial-code"><?= htmlspecialchars($d['serial_number']) ?></span></td>
                            <td><span class="badge"><?= htmlspecialchars($d['category_name']) ?></span></td>
                            <td><strong><?= htmlspecialchars($d['model_name']) ?></strong></td>
                            <td><small><?= htmlspecialchars($d['processor']) ?></small></td>
                            <td><span class="badge"><?= htmlspecialchars($d['ram']) ?>GB</span></td>
                            <td><span class="badge"><?= htmlspecialchars($d['storage_type']) ?> <?= htmlspecialchars($d['storage_capacity']) ?>GB</span></td>
                            <td><span class="price">KES <?= number_format($d['price'], 0) ?></span></td>
                            <td><i class="fas fa-user" style="color: var(--gray-400); width: 14px;"></i> <?= htmlspecialchars($d['sold_by_name']) ?></td>
                            <td>
                                <span class="<?= $d['branch'] == 'KIMATHI' ? 'branch-kimathi' : 'branch-moi' ?>">
                                    <?= htmlspecialchars($d['branch']) ?>
                                </span>
                            </td>
                            <td><small><?= date('M j, Y H:i', strtotime($d['sold_at'])) ?></small></td>
                            <td>
                                <a href="view_device.php?sn=<?= urlencode($d['serial_number']) ?>" class="action-link">
                                    <i class="fas fa-eye"></i> View
                                </a>
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

<script>
// Mobile responsive adjustments
document.addEventListener('DOMContentLoaded', function() {
    function adjustMainContent() {
        const mainContent = document.querySelector('.main-content');
        const sidebar = document.querySelector('.sidebar');
        
        if (window.innerWidth <= 1200) {
            if (mainContent) {
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
                mainContent.style.paddingTop = '5rem';
            }
        } else {
            if (mainContent && sidebar) {
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

// Auto-submit on input changes
document.querySelectorAll('#filterForm input, #filterForm select').forEach(el => {
    el.addEventListener('change', function() {
        if (this.name !== 'start_date' && this.name !== 'end_date') {
            document.getElementById('filterForm').submit();
        }
    });
});
</script>

</body>
</html>