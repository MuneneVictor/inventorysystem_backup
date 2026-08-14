<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if (!in_array($_SESSION['role'], ['super_admin', 'manager'])) {
   die("Access denied.");
}

// Get filter values from GET parameters
$filter_cargo = $_GET['cargo'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_model = $_GET['model'] ?? '';

// Fetch distinct cargo numbers for dropdown
$cargoStmt = $conn->query("SELECT DISTINCT cargo_number FROM devices WHERE cargo_number IS NOT NULL AND status = 'In Stock' ORDER BY cargo_number DESC");
$cargoNumbers = $cargoStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch distinct categories for dropdown
$categoryStmt = $conn->query("SELECT DISTINCT c.id, c.category_name FROM devices d JOIN categories c ON d.category_id = c.id WHERE d.cargo_number IS NOT NULL AND d.status = 'In Stock' ORDER BY c.category_name");
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Build SQL query with filters – now grouping by device_condition as well
$sql = "
SELECT 
    d.cargo_number,
    c.category_name,
    d.category_id,
    d.model_name,
    d.processor,
    d.ram,
    d.storage_type,
    d.storage_capacity,
    d.graphics,
    d.touch,
    d.device_condition,
    d.price,
    COUNT(*) AS quantity,
    MAX(d.price_updated_at) AS price_updated_at
FROM devices d
JOIN categories c ON d.category_id = c.id
WHERE d.cargo_number IS NOT NULL
AND d.status = 'In Stock'
";

$params = [];

// Apply filters
if ($filter_cargo) {
    $sql .= " AND d.cargo_number = :cargo";
    $params['cargo'] = $filter_cargo;
}

if ($filter_category) {
    $sql .= " AND d.category_id = :category";
    $params['category'] = $filter_category;
}

if ($filter_model) {
    $sql .= " AND d.model_name LIKE :model";
    $params['model'] = "%$filter_model%";
}

$sql .= "
GROUP BY
    d.cargo_number,
    d.category_id,
    d.model_name,
    d.processor,
    d.ram,
    d.storage_type,
    d.storage_capacity,
    d.graphics,
    d.touch,
    d.device_condition,
    d.price
ORDER BY d.cargo_number DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Price List | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* (styles unchanged – same as before) */
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
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-card .stat-icon { font-size: 1.5rem; color: var(--primary); margin-bottom: 0.5rem; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 600; color: var(--gray-800); }
        .stat-card .stat-label { font-size: 0.85rem; color: var(--gray-500); margin-top: 0.25rem; }

        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .filter-title {
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-600);
        }

        .filter-group select,
        .filter-group input {
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            background: white;
            font-family: var(--font-sans);
            min-width: 180px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

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
            min-width: 1300px;
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

        tr:hover { background: var(--gray-50); }
        tr:last-child td { border-bottom: none; }

        .wrap-model {
            max-width: 180px;
            word-wrap: break-word;
            white-space: normal;
        }

        .wrap-processor {
            max-width: 150px;
            word-wrap: break-word;
            white-space: normal;
        }

        .wrap-graphics {
            max-width: 150px;
            word-wrap: break-word;
            white-space: normal;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .price {
            font-weight: 600;
            color: #059669;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--font-sans);
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover { background: var(--primary-light); }

        .btn-add {
            background: #3b82f6;
            color: white;
        }
        .btn-add:hover { background: #2563eb; }

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

        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1rem 1.25rem; }
            .stats-row { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .stat-card { padding: 1rem; }
            .stat-card .stat-value { font-size: 1.5rem; }
            .filter-form { flex-direction: column; }
            .filter-group select, .filter-group input { width: 100%; }
            .btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .stats-row { grid-template-columns: 1fr; }
            .page-header h1 { font-size: 1.1rem; }
        }
    </style>
    <script>
        function autoApplyFilter() {
            document.getElementById('filterForm').submit();
        }
        function handleEnterKey(event) {
            if (event.key === 'Enter') {
                autoApplyFilter();
            }
        }
    </script>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1>
            <i class="fas fa-dollar-sign"></i>
            Price List
        </h1>
        <div class="breadcrumb">
             <?php if($_SESSION['role'] === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>       
            <?php endif; ?>
            <?php if($_SESSION['role'] === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Price List</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-boxes"></i></div>
            <div class="stat-value"><?= number_format(count($devices)) ?></div>
            <div class="stat-label">Unique Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
            <div class="stat-value"><?= number_format(count($cargoNumbers)) ?></div>
            <div class="stat-label">Cargo Batches</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-value"><?= number_format(count($categories)) ?></div>
            <div class="stat-label">Categories</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calculator"></i></div>
            <div class="stat-value"><?= number_format(array_sum(array_column($devices, 'quantity'))) ?></div>
            <div class="stat-label">Total Units</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <div class="filter-title">
            <i class="fas fa-filter"></i> Filter Devices
        </div>
        <form method="GET" id="filterForm" class="filter-form">
            <div class="filter-group">
                <label>Cargo Number</label>
                <select name="cargo" onchange="autoApplyFilter()">
                    <option value="">-- All Cargo --</option>
                    <?php foreach($cargoNumbers as $cargo): ?>
                        <option value="<?= htmlspecialchars($cargo) ?>" <?= $filter_cargo == $cargo ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cargo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Category</label>
                <select name="category" onchange="autoApplyFilter()">
                    <option value="">-- All Categories --</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $filter_category == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Model Name</label>
                <input type="text" 
                       name="model" 
                       placeholder="Search model..." 
                       value="<?= htmlspecialchars($filter_model) ?>"
                       onkeydown="handleEnterKey(event)">
            </div>
        </form>
    </div>

    <!-- Price List Table -->
    <div class="table-wrapper">
        <div class="table-responsive">
            <?php if($devices): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cargo No</th>
                            <th>Category</th>
                            <th>Model</th>
                            <th>Processor</th>
                            <th>RAM</th>
                            <th>Storage</th>
                            <th>Graphics</th>
                            <th>Touch?</th>
                            <th>Condition</th>
                            <th>Price (KES)</th>
                            <th>Qty</th>
                            <th>Action</th>
                            <th>Date Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i = 1; foreach($devices as $d): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($d['cargo_number']) ?></strong></td>
                            <td><span class="badge"><?= htmlspecialchars($d['category_name']) ?></span></td>
                            <td class="wrap-model"><?= htmlspecialchars($d['model_name']) ?></td>
                            <td class="wrap-processor"><?= htmlspecialchars($d['processor']) ?></td>
                            <td><span class="badge"><?= $d['ram'] ?> GB</span></td>
                            <td><span class="badge"><?= $d['storage_type'] . ' ' . $d['storage_capacity'] . 'GB' ?></span></td>
                            <td class="wrap-graphics"><?= htmlspecialchars($d['graphics']) ?></td>
                            <td><span class="badge"><?= htmlspecialchars($d['touch']) ?></span></td>
                            <td><span class="badge"><?= htmlspecialchars($d['device_condition'] ?? 'Ex-Uk') ?></span></td>
                            <td class="price">
                                <?= $d['price'] !== null ? 'KES ' . number_format($d['price'], 2) : '-' ?>
                            </td>
                            <td><span class="badge"><?= (int)$d['quantity'] ?></span></td>
                            <td>
                                <?php if($d['price'] === null): ?>
                                    <a class="btn btn-add" 
                                       href="add_price.php?cargo=<?= urlencode($d['cargo_number']) ?>&category_id=<?= $d['category_id'] ?>&model=<?= urlencode($d['model_name']) ?>&processor=<?= urlencode($d['processor']) ?>&ram=<?= $d['ram'] ?>&storage_type=<?= urlencode($d['storage_type']) ?>&storage_capacity=<?= $d['storage_capacity'] ?>&graphics=<?= urlencode($d['graphics']) ?>&touch=<?= urlencode($d['touch']) ?>&device_condition=<?= urlencode($d['device_condition'] ?? 'Ex-Uk') ?>">
                                        <i class="fas fa-plus"></i> Add Price
                                    </a>
                                <?php else: ?>
                                    <a class="btn btn-primary" 
                                       href="update_price.php?cargo=<?= urlencode($d['cargo_number']) ?>&category_id=<?= $d['category_id'] ?>&model=<?= urlencode($d['model_name']) ?>&processor=<?= urlencode($d['processor']) ?>&ram=<?= $d['ram'] ?>&storage_type=<?= urlencode($d['storage_type']) ?>&storage_capacity=<?= $d['storage_capacity'] ?>&graphics=<?= urlencode($d['graphics']) ?>&touch=<?= urlencode($d['touch']) ?>&device_condition=<?= urlencode($d['device_condition'] ?? 'Ex-Uk') ?>">
                                        <i class="fas fa-edit"></i> Update Price
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $d['price_updated_at'] ? date('Y-m-d H:i', strtotime($d['price_updated_at'])) : '-' ?>
                             </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>No devices found matching your criteria.</p>
                </div>
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
</script>

</body>
</html>