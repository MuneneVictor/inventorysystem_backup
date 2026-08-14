<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

if (!isset($_GET['sn'])) die("Serial number not provided!");

$serial_number = trim($_GET['sn']);

$stmt = $conn->prepare("
    SELECT m.*, u.full_name AS added_by_name 
    FROM monitors m 
    LEFT JOIN users u ON m.added_by = u.id 
    WHERE m.serial_number = ?
");
$stmt->execute([$serial_number]);
$monitor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$monitor) die("Monitor not found!");

$user_role = $_SESSION['role'];
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Monitor Details | <?= htmlspecialchars($monitor['serial_number']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            --font-sans: 'Inter', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: all 0.3s ease; }
        .page-header { background: white; padding: 1.5rem 2rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .page-header h1 { font-size: 1.75rem; color: var(--gray-800); font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }
        .page-header h1 .serial-code { font-family: monospace; background: var(--gray-100); padding: 0.25rem 0.75rem; border-radius: var(--radius-md); font-size: 1rem; }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .result-card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); margin-bottom: 1.5rem; overflow: hidden; box-shadow: var(--shadow-sm); }
        .card-header { background: var(--gray-50); padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
        .card-header h3 { font-size: 1.1rem; font-weight: 600; color: var(--gray-800); display: flex; align-items: center; gap: 0.5rem; }
        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #d1fae5; color: #065f46; }
        .status-sold { background: #fee2e2; color: #991b1b; }
        .card-body { padding: 1.5rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
        .info-item { padding: 0.875rem; background: var(--gray-50); border-radius: var(--radius-lg); }
        .info-label { font-size: 0.7rem; font-weight: 600; color: var(--gray-500); text-transform: uppercase; margin-bottom: 0.5rem; }
        .info-value { font-size: 0.95rem; font-weight: 500; color: var(--gray-800); }
        .btn { padding: 0.6rem 1.2rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; }
        .btn-secondary { background: var(--gray-500); }
        .action-buttons { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .info-grid { grid-template-columns: 1fr; } .action-buttons { flex-direction: column; } .btn { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-desktop"></i> Monitor Details <span class="serial-code"><?= htmlspecialchars($monitor['serial_number']) ?></span></h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="../dashboard/inventorydashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="monitors_instock.php">In‑Stock Monitors</a>
            <span> / </span>
            <span>View Monitor</span>
        </div>
    </div>

    <div class="result-card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Monitor Information</h3>
            <span class="status-badge <?= $monitor['status'] === 'Sold' ? 'status-sold' : '' ?>">
                <i class="fas <?= $monitor['status'] === 'In Stock' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                <?= $monitor['status'] ?>
            </span>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Serial Number</div><div class="info-value"><code><?= htmlspecialchars($monitor['serial_number']) ?></code></div></div>
                <div class="info-item"><div class="info-label">Model Name</div><div class="info-value"><?= htmlspecialchars($monitor['model_name']) ?></div></div>
                <div class="info-item"><div class="info-label">Size (inches)</div><div class="info-value"><?= $monitor['size_inches'] ?>\"</div></div>
                <div class="info-item"><div class="info-label">Branch</div><div class="info-value"><?= htmlspecialchars($monitor['branch']) ?></div></div>
                <div class="info-item"><div class="info-label">Added By</div><div class="info-value"><?= htmlspecialchars($monitor['added_by_name'] ?? 'Unknown') ?></div></div>
                <div class="info-item"><div class="info-label">Date Added</div><div class="info-value"><?= date('M j, Y H:i', strtotime($monitor['date_added'])) ?></div></div>
                <?php if ($monitor['status'] === 'Sold'): ?>
                    <div class="info-item"><div class="info-label">Date Sold</div><div class="info-value"><?= $monitor['sold_at'] ? date('M j, Y H:i', strtotime($monitor['sold_at'])) : '-' ?></div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="action-buttons">
        <a href="monitors_instock.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Inventory</a>
        <?php if ($user_role === 'super_admin' || $user_role === 'inventory_admin'): ?>
            <a href="edit_monitor.php?sn=<?= urlencode($monitor['serial_number']) ?>" class="btn"><i class="fas fa-edit"></i> Edit Monitor</a>
        <?php endif; ?>
    </div>

    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>