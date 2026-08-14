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
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label>Serial Number</label>
                <input type="text" name="serial" placeholder="e.g., 5CG..." value="<?= htmlspecialchars($filter_serial) ?>">
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
                                <a href="view_device.php?sn=<?= urlencode($device['serial_number']) ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
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
</script>

<?php require_once "../includes/footer.php"; ?>
</body>
</html>