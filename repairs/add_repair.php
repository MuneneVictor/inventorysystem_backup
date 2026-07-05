<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
// Only technicians can add repairs
if ($_SESSION['role'] !== 'technician') {
    die("ACCESS DENIED. Only technicians can add repairs.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Technician';

// Get technician branch
$stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_branch = $stmt->fetchColumn();
if (!$user_branch) {
    die("Your account has no branch assigned. Contact administrator.");
}

$error = "";
$success = "";
$device = null;
$search_sn = trim($_GET['sn'] ?? '');

// Load device by serial
if ($search_sn) {
    $stmt = $conn->prepare("
        SELECT d.*, c.category_name
        FROM devices d
        JOIN categories c ON d.category_id = c.id
        WHERE d.serial_number = ? AND d.status = 'In Stock' AND d.branch = ?
    ");
    $stmt->execute([$search_sn, $user_branch]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        $error = "Device not found, not In Stock, or not in your branch.";
    }
}

// Get users who can give device (inventory_admin or super_admin in same branch)
$stmt = $conn->prepare("
    SELECT id, full_name 
    FROM users
    WHERE role IN ('inventory_admin', 'super_admin') 
      AND (branch = ? OR role = 'super_admin')
    ORDER BY full_name
");
$stmt->execute([$user_branch]);
$givenByUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Save repair
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serial = trim($_POST['serial'] ?? '');
    $problem = trim($_POST['problem_description'] ?? '');
    $given_by = (int) ($_POST['given_by'] ?? 0);

    if (!$serial || !$problem || !$given_by) {
        $error = "All fields are required.";
    } else {
        try {
            $conn->beginTransaction();

            // Insert repair record
            $insert = $conn->prepare("
                INSERT INTO repairs (serial_number, problem_description, added_by, given_by, branch, date_added)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insert->execute([$serial, $problem, $user_id, $given_by, $user_branch]);

            // Update device status
            $update = $conn->prepare("UPDATE devices SET status = 'Under Repair' WHERE serial_number = ?");
            $update->execute([$serial]);

            // Get given_by name for log
            $givenByName = "Unknown";
            foreach ($givenByUsers as $u) {
                if ($u['id'] == $given_by) {
                    $givenByName = $u['full_name'];
                    break;
                }
            }

            // Activity log
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Add Repair', ?)");
            $log->execute([$user_id, "Added repair for device: $serial | Problem: $problem | Given By: $givenByName | Branch: $user_branch"]);

            $conn->commit();
            $success = "Device successfully added to repairs.";
            $device = null;
            $search_sn = '';
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
    <title>Add Repair | Mombasa Computers</title>
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
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .info-item { padding: 0.75rem; background: var(--gray-50); border-radius: var(--radius-lg); }
        .info-label { font-size: 0.7rem; font-weight: 600; color: var(--gray-500); text-transform: uppercase; }
        .info-value { font-size: 0.95rem; font-weight: 500; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: var(--gray-700); }
        .form-group textarea, .form-group select { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        .alert { padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .search-form { flex-direction: column; } .btn { width: 100%; justify-content: center; } .info-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-tools"></i> Add Repair</h1>
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
            <a href="under_repair.php">Under Repair</a>
            <span> / </span>
            <span>Add Repair</span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="search-section">
        <div class="search-form">
            <div class="search-input-group">
                <label><i class="fas fa-qrcode"></i> Serial Number</label>
                <input type="text" id="serial_search" placeholder="Scan or enter serial number" value="<?= htmlspecialchars($search_sn) ?>" autofocus>
            </div>
            <button type="button" class="btn btn-primary" id="searchBtn"><i class="fas fa-search"></i> Load Device</button>
        </div>
    </div>

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

                <form method="POST">
                    <input type="hidden" name="serial" value="<?= htmlspecialchars($device['serial_number']) ?>">
                    <div class="form-group">
                        <label>Problem Description</label>
                        <textarea name="problem_description" rows="4" required placeholder="Describe the issue in detail..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Given By (Who handed over the device?)</label>
                        <select name="given_by" required>
                            <option value="">-- Select Person --</option>
                            <?php foreach ($givenByUsers as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Repair</button>
                </form>
            </div>
        </div>
    <?php elseif ($search_sn !== ''): ?>
        <div class="alert alert-error">Device not loaded. Make sure the serial exists and is In Stock in your branch.</div>
    <?php endif; ?>

    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>

<script>
document.getElementById('searchBtn').addEventListener('click', function() {
    let sn = document.getElementById('serial_search').value.trim();
    if (sn) window.location.href = '?sn=' + encodeURIComponent(sn);
});
document.getElementById('serial_search').addEventListener('keypress', function(e) {
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