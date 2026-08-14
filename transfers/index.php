<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only super_admin, inventory_admin, manager, and cashier can access
if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager', 'cashier'])) {
    die("ACCESS DENIED.");
}

$user_role = $_SESSION['role'];
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Get user's branch for display (if not super_admin)
$user_branch = null;
if ($user_role !== 'super_admin') {
    $user_id = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
}

// Get current time greeting
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
    <title>Stock Transfers | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
            --primary-dark: #0f3a1e;
            --secondary: #1a4f6e;
            --accent: #f59e0b;
            --info: #2563eb;
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
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
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
        .branch-info { background: var(--gray-50); padding: 0.75rem 1.25rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem; border-left: 4px solid var(--info); }
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow: hidden; transition: all 0.3s ease; box-shadow: var(--shadow-sm); }
        .card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
        .card-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid var(--gray-200); }
        .card-header i { font-size: 2.5rem; }
        .card-header.device i { color: #3b82f6; }
        .card-header.monitor i { color: #10b981; }
        .card-header.printer i { color: #f59e0b; }
        .card-header.charger i { color: #ef4444; }
        .card-header.ramssd i { color: #8b5cf6; }
        .card-header.smartboard i { color: #ec4899; }
        .card-header.ups i { color: #f97316; }
        .card-header.phone i { color: #14b8a6; }
        .card-header.accessory i { color: #6366f1; }
        .card-header.graphics i { color: #d946ef; }
        .card-header.hdd i { color: #6b7280; }
        .card-header h3 { font-size: 1.25rem; margin-top: 0.75rem; color: var(--gray-800); }
        .card-body { padding: 1.25rem; text-align: center; }
        .card-body p { color: var(--gray-500); font-size: 0.9rem; margin-bottom: 1rem; }
        .btn { padding: 0.6rem 1.2rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; transition: background 0.2s; }
        .btn:hover { background: var(--primary-light); }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .cards-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 480px) { .cards-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-exchange-alt"></i> Stock Transfers</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="../dashboard/inventorydashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Transfers</span>
        </div>
    </div>

    <div class="branch-info">
        <i class="fas fa-store"></i> 
        <?php if ($user_role === 'super_admin'): ?>
            You can transfer stock between <strong>KIMATHI</strong> and <strong>MOI</strong> branches.
        <?php else: ?>
            Your branch: <strong><?= htmlspecialchars($user_branch) ?></strong> – You can only transfer items <strong>out of your branch</strong>.
        <?php endif; ?>
    </div>

    <div class="cards-grid">
        <!-- Transfer Devices -->
        <div class="card">
            <div class="card-header device">
                <i class="fas fa-laptop"></i>
                <h3>Devices</h3>
            </div>
            <div class="card-body">
                <p>Transfer laptops, desktops, workstations between branches.</p>
                <a href="transfer_device.php" class="btn"><i class="fas fa-arrow-right"></i> Transfer Device</a>
            </div>
        </div>

        <!-- Transfer Monitors -->
        <div class="card">
            <div class="card-header monitor">
                <i class="fas fa-desktop"></i>
                <h3>Monitors</h3>
            </div>
            <div class="card-body">
                <p>Transfer monitors, TVs, smartboards between branches.</p>
                <a href="transfer_monitor.php" class="btn"><i class="fas fa-arrow-right"></i> Transfer Monitor</a>
            </div>
        </div>

        <!-- Transfer Printers -->
        <div class="card">
            <div class="card-header printer">
                <i class="fas fa-print"></i>
                <h3>Printers</h3>
            </div>
            <div class="card-body">
                <p>Transfer printers and other peripherals between branches.</p>
                <a href="transfer_printer.php" class="btn"><i class="fas fa-arrow-right"></i> Transfer Printer</a>
            </div>
        </div>

        <!-- Transfer Chargers -->
        <div class="card">
            <div class="card-header charger">
                <i class="fas fa-bolt"></i>
                <h3>Chargers</h3>
            </div>
            <div class="card-body">
                <p>Transfer charger stock between branches.</p>
                <a href="transfer_charger.php" class="btn"><i class="fas fa-arrow-right"></i> Transfer Charger</a>
            </div>
        </div>

        <!-- Transfer RAM / SSD -->
        <div class="card">
            <div class="card-header ramssd">
                <i class="fas fa-memory"></i>
                <h3>RAM / SSD</h3>
            </div>
            <div class="card-body">
                <p>Transfer RAM and SSD components between branches.</p>
                <a href="transfer_ram_ssd.php" class="btn"><i class="fas fa-arrow-right"></i> Transfer RAM/SSD</a>
            </div>
        </div>

        <!-- Transfer Smartboards -->
        <div class="card">
            <div class="card-header smartboard">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>Smartboards</h3>
            </div>
            <div class="card-body">
                <p>Transfer interactive smartboards between branches.</p>
                <a href="transfer_smartboard.php" class="btn"><i class="fas fa-arrow-right"></i> Transfer Smartboard</a>
            </div>
        </div>

        <!-- Transfer UPS -->
        <div class="card">
            <div class="card-header ups">
                <i class="fas fa-battery-three-quarters"></i>
                <h3>UPS Units</h3>
            </div>
            <div class="card-body">
                <p>Transfer UPS units between branches.</p>
                <a href="transfer_ups.php" class="btn"><i class="fas fa-arrow-right"></i> Transfer UPS</a>
            </div>
        </div>

        <!-- Transfer Phones -->
        <div class="card">
            <div class="card-header phone">
                <i class="fas fa-mobile-alt"></i>
                <h3>Phones</h3>
            </div>
            <div class="card-body">
                <p>Transfer phones between branches.</p>
                <a href="transfer_phone.php" class="btn"><i class="fas fa-arrow-right"></i> Transfer Phone</a>
            </div>
        </div>

        <!-- Transfer Accessories -->
        <div class="card">
            <div class="card-header accessory">
                <i class="fas fa-headset"></i>
                <h3>Accessories</h3>
            </div>
            <div class="card-body">
                <p>Transfer accessories (mice, keyboards, cables, etc.) between branches.</p>
                <a href="transfer_accessory.php" class="btn"><i class="fas fa-arrow-right"></i> Transfer Accessory</a>
            </div>
        </div>

        <!-- Transfer Graphics Cards -->
        <div class="card">
            <div class="card-header graphics">
                <i class="fas fa-video"></i>
                <h3>Graphics Cards</h3>
            </div>
            <div class="card-body">
                <p>Transfer graphics cards between branches.</p>
                <a href="transfer_graphics.php" class="btn"><i class="fas fa-arrow-right"></i> Transfer Graphics</a>
            </div>
        </div>

        <!-- Transfer HDDs -->
        <div class="card">
            <div class="card-header hdd">
                <i class="fas fa-hdd"></i>
                <h3>HDDs</h3>
            </div>
            <div class="card-body">
                <p>Transfer internal/external hard drives between branches.</p>
                <a href="transfer_hdd.php" class="btn"><i class="fas fa-arrow-right"></i> Transfer HDD</a>
            </div>
        </div>
    </div>

    <!-- Optional: View Transfer Logs -->
    <div style="text-align: center; margin-top: 1rem;">
        <a href="transfer_logs.php" class="btn" style="background: var(--info);"><i class="fas fa-history"></i> View Transfer Logs</a>
    </div>

    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>