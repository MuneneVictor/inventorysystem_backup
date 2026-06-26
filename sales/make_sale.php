<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";


$role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

// Allow roles that can make sales
if (!in_array($role, ['sales', 'super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED.");
}

date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');
require_once "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Make a Sale | Mombasa Computers</title>
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
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --font-sans: 'Inter', system-ui, -apple-system, sans-serif;
        }

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

        .greeting {
            font-size: 0.9rem;
            color: var(--gray-600);
            margin-top: 0.25rem;
        }

        /* Card Grid */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .sale-card {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem 1rem;
            text-align: center;
            transition: all 0.25s ease;
            text-decoration: none;
            color: var(--gray-700);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }

        .sale-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .sale-card .icon {
            font-size: 2.5rem;
            color: var(--primary);
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-50);
            border-radius: 50%;
            transition: 0.2s;
        }

        .sale-card:hover .icon {
            background: var(--primary);
            color: white;
        }

        .sale-card .label {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--gray-800);
        }

        .sale-card .sub-label {
            font-size: 0.75rem;
            color: var(--gray-400);
        }

        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

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
            .page-header { padding: 1rem 1.25rem; }
            .card-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            .sale-card {
                padding: 1rem 0.5rem;
            }
            .sale-card .icon {
                font-size: 2rem;
                width: 54px;
                height: 54px;
            }
            .sale-card .label {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem 0.5rem 0.5rem !important;
                padding-top: 4rem !important;
            }
            .page-header h1 { font-size: 1.1rem; }
            .card-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.75rem;
            }
            .sale-card .icon {
                font-size: 1.6rem;
                width: 44px;
                height: 44px;
            }
            .sale-card .label {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1>
            <i class="fas fa-cash-register"></i>
            Make a Sale
        </h1>
        <div class="breadcrumb">
            <?php if ($role === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($role === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($role === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($role === 'sales'): ?>
                <a href="/inventory_system/dashboard/salesdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Make a Sale</span>
        </div>
        <div class="greeting"><?= $greeting ?>, <?= htmlspecialchars($user_name) ?>! Select the product category to begin.</div>
    </div>

    <div class="card-grid">
        <!-- Device -->
        <a href="sell_device.php" class="sale-card">
            <div class="icon"><i class="fas fa-laptop"></i></div>
            <div class="label">Device</div>
            <div class="sub-label">Laptops, Desktops, etc.</div>
        </a>

        <!-- Monitor -->
        <a href="sell_monitor.php" class="sale-card">
            <div class="icon"><i class="fas fa-desktop"></i></div>
            <div class="label">Monitor</div>
            <div class="sub-label">Displays</div>
        </a>

        <!-- Printer -->
        <a href="sell_printer.php" class="sale-card">
            <div class="icon"><i class="fas fa-print"></i></div>
            <div class="label">Printer</div>
            <div class="sub-label">Printers</div>
        </a>

        <!-- Smartboard -->
        <a href="sell_smartboard.php" class="sale-card">
            <div class="icon"><i class="fas fa-chalkboard"></i></div>
            <div class="label">Smartboard</div>
            <div class="sub-label">Interactive Boards</div>
        </a>

        <!-- UPS -->
        <a href="sell_ups.php" class="sale-card">
            <div class="icon"><i class="fas fa-bolt"></i></div>
            <div class="label">UPS</div>
            <div class="sub-label">Power Backup</div>
        </a>

        <!-- Phone -->
        <a href="sell_phone.php" class="sale-card">
            <div class="icon"><i class="fas fa-mobile-alt"></i></div>
            <div class="label">Phone</div>
            <div class="sub-label">Smartphones</div>
        </a>

        <!-- Accessory -->
        <a href="sell_accessory.php" class="sale-card">
            <div class="icon"><i class="fas fa-plug"></i></div>
            <div class="label">Accessory</div>
            <div class="sub-label">Cables, Mice, etc.</div>
        </a>

        <!-- Charger -->
        <a href="sell_charger.php" class="sale-card">
            <div class="icon"><i class="fas fa-battery-three-quarters"></i></div>
            <div class="label">Charger</div>
            <div class="sub-label">Laptop Chargers</div>
        </a>

        <!-- Graphics Card -->
        <a href="sell_graphics_card.php" class="sale-card">
            <div class="icon"><i class="fas fa-microchip"></i></div>
            <div class="label">Graphics Card</div>
            <div class="sub-label">GPUs</div>
        </a>

        <!-- HDD -->
        <a href="sell_hdd.php" class="sale-card">
            <div class="icon"><i class="fas fa-hdd"></i></div>
            <div class="label">HDD</div>
            <div class="sub-label">Hard Drives</div>
        </a>

        <!-- RAM/SSD -->
        <a href="sell_ram_ssd.php" class="sale-card">
            <div class="icon"><i class="fas fa-memory"></i></div>
            <div class="label">RAM / SSD</div>
            <div class="sub-label">Memory & Storage</div>
        </a>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<script>
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