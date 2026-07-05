<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";


$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

// Only technicians and super_admin/inventory_admin can access this search
if (!in_array($user_role, ['technician', 'super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED.");
}

$search_sn = trim($_GET['serial'] ?? '');
$device = null;
$repairs = [];

if ($search_sn) {
    // Search only In Stock devices (can be added to repair)
    $stmt = $conn->prepare("
        SELECT d.*, c.category_name, u.full_name AS added_by_name
        FROM devices d
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN users u ON d.added_by = u.id
        WHERE d.serial_number = ? AND d.status = 'In Stock'
    ");
    $stmt->execute([$search_sn]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($device) {
        // Fetch repair history (all, including fixed)
        $rstmt = $conn->prepare("
            SELECT r.*, u1.full_name AS technician_name, u2.full_name AS given_by_name
            FROM repairs r
            LEFT JOIN users u1 ON r.added_by = u1.id
            LEFT JOIN users u2 ON r.given_by = u2.id
            WHERE r.serial_number = ?
            ORDER BY r.date_added DESC
        ");
        $rstmt->execute([$search_sn]);
        $repairs = $rstmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Search Device (Repair) | Mombasa Computers</title>
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
        .search-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .search-form { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .search-input-group { flex: 1; min-width: 250px; }
        .search-input-group label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--gray-600); margin-bottom: 0.5rem; }
        .search-input-group input { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }
        .card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; }
        .card-header { background: var(--gray-50); padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200); font-weight: 600; }
        .card-body { padding: 1.5rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .info-item { padding: 0.75rem; background: var(--gray-50); border-radius: var(--radius-lg); }
        .info-label { font-size: 0.7rem; font-weight: 600; color: var(--gray-500); text-transform: uppercase; }
        .info-value { font-size: 0.95rem; font-weight: 500; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--gray-200); }
        th { background: var(--gray-50); font-weight: 600; color: var(--gray-600); }
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: var(--gray-100); }
        .empty-state { text-align: center; padding: 2rem; color: var(--gray-500); }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .search-form { flex-direction: column; } .btn { width: 100%; justify-content: center; } .info-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-search"></i> Search Device (Repair)</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="/inventory_system/dashboard/techniciandashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Search Device</span>
        </div>
    </div>

    <div class="search-section">
        <div class="search-form">
            <div class="search-input-group">
                <label><i class="fas fa-qrcode"></i> Serial Number</label>
                <input type="text" name="serial" id="serial_number" placeholder="Scan or enter serial number" value="<?= htmlspecialchars($search_sn) ?>" autofocus>
            </div>
            <button type="button" class="btn btn-primary" id="searchBtn"><i class="fas fa-search"></i> Search</button>
        </div>
    </div>

    <?php if ($search_sn && !$device): ?>
        <div class="empty-state"><i class="fas fa-box-open"></i><p>No In Stock device found with that serial number.</p></div>
    <?php endif; ?>

    <?php if ($device): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-laptop"></i> Device Details</div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item"><div class="info-label">Serial</div><div class="info-value"><?= htmlspecialchars($device['serial_number']) ?></div></div>
                    <div class="info-item"><div class="info-label">Category</div><div class="info-value"><?= htmlspecialchars($device['category_name']) ?></div></div>
                    <div class="info-item"><div class="info-label">Model</div><div class="info-value"><?= htmlspecialchars($device['model_name']) ?></div></div>
                    <div class="info-item"><div class="info-label">Processor</div><div class="info-value"><?= htmlspecialchars($device['processor']) ?></div></div>
                    <div class="info-item"><div class="info-label">RAM</div><div class="info-value"><?= $device['ram'] ?> GB</div></div>
                    <div class="info-item"><div class="info-label">Storage</div><div class="info-value"><?= htmlspecialchars($device['storage_type'] . ' ' . $device['storage_capacity'] . ' GB') ?></div></div>
                    <div class="info-item"><div class="info-label">Graphics</div><div class="info-value"><?= htmlspecialchars($device['graphics'] ?? 'N/A') ?></div></div>
                    <div class="info-item"><div class="info-label">Branch</div><div class="info-value"><?= htmlspecialchars($device['branch']) ?></div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-history"></i> Repair History</div>
            <div class="card-body">
                <?php if (empty($repairs)): ?>
                    <div class="empty-state">No repair records for this device.</div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr><th>#</th><th>Problem</th><th>Status</th><th>Given By</th><th>Technician</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach ($repairs as $r): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($r['problem_description'] ?? '-') ?></td>
                                    <td><span class="badge"><?= htmlspecialchars($r['fix_status']) ?></span></td>
                                    <td><?= htmlspecialchars($r['given_by_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($r['technician_name'] ?? '-') ?></td>
                                    <td><?= date('M j, Y H:i', strtotime($r['date_added'])) ?></td>
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
    if (sn) window.location.href = '?serial=' + encodeURIComponent(sn);
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