<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only software role can access
if (!in_array($_SESSION['role'], ['software', 'super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED. Only Software department can view this page.");
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$search_sn = trim($_GET['sn'] ?? '');
$device = null;
$maintenance_history = [];

if ($search_sn) {
    // Fetch device with branch check (if not super_admin)
    $sql = "SELECT d.*, c.category_name, u.full_name AS added_by_name, d.branch
            FROM devices d
            JOIN categories c ON d.category_id = c.id
            LEFT JOIN users u ON d.added_by = u.id
            WHERE d.serial_number = :sn";
    $params = ['sn' => $search_sn];

    // Non-super_admin should only see devices in their branch
    if ($user_role !== 'super_admin') {
        $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_branch = $stmt->fetchColumn();
        if ($user_branch) {
            $sql .= " AND d.branch = :branch";
            $params['branch'] = $user_branch;
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
            WHERE m.device_serial = :sn
            ORDER BY m.date_performed DESC
        ");
        $mstmt->execute(['sn' => $search_sn]);
        $maintenance_history = $mstmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Search Device | Software</title>
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
        .search-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .search-form { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .search-input-group { flex: 1; min-width: 250px; }
        .search-input-group label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--gray-600); margin-bottom: 0.5rem; }
        .search-input-group input { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }
        .result-card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); margin-bottom: 1.5rem; overflow: hidden; box-shadow: var(--shadow-sm); }
        .card-header { background: var(--gray-50); padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
        .card-header h3 { font-size: 1.1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .card-header h3 i { color: var(--primary); }
        .card-body { padding: 1.5rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
        .info-item { padding: 0.75rem; background: var(--gray-50); border-radius: var(--radius-lg); }
        .info-label { font-size: 0.7rem; font-weight: 600; color: var(--gray-500); text-transform: uppercase; margin-bottom: 0.25rem; }
        .info-value { font-size: 0.95rem; font-weight: 500; color: var(--gray-800); }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--gray-200); }
        th { background: var(--gray-50); font-weight: 600; color: var(--gray-600); }
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: var(--gray-100); }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .search-form { flex-direction: column; } .btn { width: 100%; justify-content: center; } .info-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-search"></i> Search Device (Maintenance)</h1>
        <div class="breadcrumb">
            <a href="/inventory_system/dashboard/<?= $user_role === 'software' ? 'softwaredashboard.php' : ($user_role === 'super_admin' ? 'superadmindashboard.php' : 'inventorydashboard.php') ?>">Dashboard</a>
            <span> / </span>
            <span>Search Device</span>
        </div>
    </div>

    <div class="search-section">
        <div class="search-form">
            <div class="search-input-group">
                <label><i class="fas fa-qrcode"></i> Serial Number</label>
                <input type="text" name="sn" id="serial_number" placeholder="Scan or type serial number..." value="<?= htmlspecialchars($search_sn) ?>" autofocus>
            </div>
            <button type="button" class="btn btn-primary" id="searchBtn"><i class="fas fa-search"></i> Search</button>
        </div>
    </div>

    <?php if ($search_sn && !$device): ?>
        <div class="empty-state"><i class="fas fa-box-open"></i><p>Device not found or you do not have permission.</p></div>
    <?php endif; ?>

    <?php if ($device): ?>
        <div class="result-card">
            <div class="card-header">
                <h3><i class="fas fa-laptop"></i> Device Details</h3>
                <span class="badge"><?= htmlspecialchars($device['status']) ?></span>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item"><div class="info-label">Serial Number</div><div class="info-value"><code><?= htmlspecialchars($device['serial_number']) ?></code></div></div>
                    <div class="info-item"><div class="info-label">Category</div><div class="info-value"><?= htmlspecialchars($device['category_name']) ?></div></div>
                    <div class="info-item"><div class="info-label">Model</div><div class="info-value"><?= htmlspecialchars($device['model_name']) ?></div></div>
                    <div class="info-item"><div class="info-label">Processor</div><div class="info-value"><?= htmlspecialchars($device['processor']) ?></div></div>
                    <div class="info-item"><div class="info-label">Graphics</div><div class="info-value"><?= htmlspecialchars($device['graphics'] ?? 'None') ?></div></div>
                    <div class="info-item"><div class="info-label">RAM</div><div class="info-value"><?= htmlspecialchars($device['ram']) ?> GB</div></div>
                    <div class="info-item"><div class="info-label">Storage</div><div class="info-value"><?= htmlspecialchars($device['storage_type'] . ' ' . $device['storage_capacity'] . ' GB') ?></div></div>
                    <div class="info-item"><div class="info-label">Touch</div><div class="info-value"><?= htmlspecialchars($device['touch'] ?? '-') ?></div></div>
                    <div class="info-item"><div class="info-label">Branch</div><div class="info-value"><?= htmlspecialchars($device['branch']) ?></div></div>
                    <div class="info-item"><div class="info-label">Added By</div><div class="info-value"><?= htmlspecialchars($device['added_by_name'] ?? 'Unknown') ?></div></div>
                    <div class="info-item"><div class="info-label">Date Added</div><div class="info-value"><?= date('M j, Y H:i', strtotime($device['date_added'])) ?></div></div>
                </div>
            </div>
        </div>

        <div class="result-card">
            <div class="card-header"><h3><i class="fas fa-history"></i> Maintenance History</h3></div>
            <div class="card-body">
                <?php if (empty($maintenance_history)): ?>
                    <p class="empty-state" style="padding:1rem;">No maintenance records for this device.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr><th>#</th><th>Old RAM</th><th>New RAM</th><th>Old Storage</th><th>New Storage</th><th>Old Graphics</th><th>New Graphics</th><th>Performed By</th><th>Notes</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($maintenance_history as $m): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= $m['old_ram'] ?> GB</td>
                                    <td><?= $m['new_ram'] ?> GB</td>
                                    <td><?= $m['old_storage'] ?> GB</td>
                                    <td><?= $m['new_storage'] ?> GB</td>
                                    <td><?= htmlspecialchars($m['old_graphics'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($m['new_graphics'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($m['performed_by_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($m['notes'] ?? '-') ?></td>
                                    <td><?= date('M j, Y H:i', strtotime($m['date_performed'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>

<script>
document.getElementById('searchBtn').addEventListener('click', function() {
    let sn = document.getElementById('serial_number').value.trim();
    if (sn) window.location.href = '?sn=' + encodeURIComponent(sn);
});
document.getElementById('serial_number').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') document.getElementById('searchBtn').click();
});
function adjustMainContent() {
    const main = document.querySelector('.main-content');
    if (window.innerWidth <= 1200) main.style.marginLeft = '0';
    else main.style.marginLeft = '260px';
}
window.addEventListener('resize', adjustMainContent);
adjustMainContent();
</script>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>