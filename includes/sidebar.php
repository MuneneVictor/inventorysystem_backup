<?php
require_once "../includes/auth_check.php";
// sidebar.php - Green gradient sidebar with collapsible sections
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
</head>
<body>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
/* ===== ORIGINAL GREEN SIDEBAR with collapsible sections ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
    background: #f5f7fa;
}

.sidebar-wrapper {
    position: relative;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    background: linear-gradient(to bottom, #2f7a3f, #1f5a2d);
    color: #ffffff;
    padding: 0;
    overflow-y: auto;
    transition: transform 0.3s ease;
    z-index: 1000;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

/* Logo area */
.sidebar-header {
    padding: 24px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.15);
    margin-bottom: 16px;
    text-align: left;
}

.logo-link {
    display: inline-block;
    text-decoration: none;
}

.logo-img {
    max-width: 160px;
    height: auto;
    display: block;
    filter: brightness(0) invert(1); /* makes logo white if it's dark; remove if logo is already light */
}

/* Menu container */
.sidebar-menu {
    padding: 0 16px 32px 16px; /* extra bottom padding for logout visibility */
}

/* Section header (collapsible) */
.menu-section-wrapper {
    margin-bottom: 4px;
}

.menu-section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 12px;
    border-radius: 12px;
    cursor: pointer;
    transition: background 0.2s;
    color: #ffffff;
    font-weight: 500;
    font-size: 0.9rem;
}

.menu-section-header:hover {
    background: rgba(255,255,255,0.1);
}

.menu-section-header .section-icon {
    width: 24px;
    font-size: 1.1rem;
    color: rgba(255,255,255,0.8);
}

.menu-section-header span {
    flex: 1;
}

.menu-section-header .toggle-icon {
    font-size: 0.8rem;
    transition: transform 0.2s;
    color: rgba(255,255,255,0.7);
}

.menu-section-header.open .toggle-icon {
    transform: rotate(90deg);
}

/* Sub-items container */
.menu-section-items {
    margin-left: 36px;
    margin-bottom: 8px;
    overflow: hidden;
}

/* Menu items (both top-level and sub-items) */
.menu-item {
    display: flex;
    align-items: center;
    gap: 14px;
    color: #ffffff;
    padding: 10px 16px;
    margin: 4px 0;
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.2s ease;
    font-size: 0.9rem;
    font-weight: 500;
    background: transparent;
}

.menu-item i {
    font-size: 1.1rem;
    width: 24px;
    text-align: center;
    color: rgba(255,255,255,0.8);
}

.menu-item:hover {
    background: rgba(255,255,255,0.15);
    transform: translateX(3px);
}
.menu-item:hover i {
    color: #ffffff;
}

/* Sub-item specific */
.sub-item {
    padding-left: 12px;
    font-weight: normal;
    font-size: 0.85rem;
}
.sub-item i {
    font-size: 0.9rem;
}

/* My profile (first item) */
.menu-item:first-of-type {
    margin-bottom: 12px;
}

/* Logout button - ensure visibility on mobile */
.logout-btn {
    margin-top: 32px;
    background: rgba(220,53,69,0.8);
    color: white;
}
.logout-btn i {
    color: white;
}
.logout-btn:hover {
    background: rgba(220,53,69,1);
}

/* Hamburger toggle (unchanged) */
.sidebar-toggle {
    position: fixed;
    top: 15px;
    left: 15px;
    background: #2f7a3f;
    color: white;
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
    z-index: 1001;
    display: none;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    font-size: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.sidebar-toggle:hover {
    background: #1f5a2d;
}
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    display: none;
}

/* Responsive */
@media (max-width: 1024px) {
    .sidebar {
        transform: translateX(-100%);
        width: 260px;
    }
    .sidebar.active {
        transform: translateX(0);
    }
    .sidebar-toggle {
        display: flex;
    }
    .sidebar-overlay.active {
        display: block;
    }
}
@media (max-width: 480px) {
    .sidebar-toggle {
        padding: 8px 14px;
        font-size: 0.9rem;
    }
    .sidebar {
        width: 85%;
        max-width: 280px;
    }
    .logo-img {
        max-width: 140px;
    }
    .sidebar-menu {
        padding-bottom: 40px; /* ensure logout is reachable */
    }
}

/* Scrollbar */
.sidebar::-webkit-scrollbar {
    width: 4px;
}
.sidebar::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.1);
}
.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.3);
    border-radius: 4px;
}

/* Global fix for main content */
@media (max-width: 1024px) {
    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
        padding-top: 5rem !important;
    }
}
</style>

<div class="sidebar-wrapper">
    <div class="sidebar-toggle" onclick="toggleSidebar()">
        <span>☰</span> Menu
    </div>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar">
        <div class="sidebar-header">
            <a href="<?php
                $role = $_SESSION['role'] ?? '';
                switch ($role) {
                    case 'super_admin': $dashboard_url = '/inventory_system/dashboard/superadmindashboard.php'; break;
                    case 'manager': $dashboard_url = '/inventory_system/dashboard/managerdashboard.php'; break;
                    case 'inventory_admin': $dashboard_url = '/inventory_system/dashboard/inventorydashboard.php'; break;
                    case 'sales': $dashboard_url = '/inventory_system/dashboard/salesdashboard.php'; break;
                    default: $dashboard_url = '/inventory_system/dashboard/index.php';
                }
                echo $dashboard_url;
            ?>" class="logo-link">
                <img src="/inventory_system/assets/MC-LOGO.png" alt="Mombasa Computers" class="logo-img">
            </a>
        </div>

        <div class="sidebar-menu">
            <?php $role = $_SESSION['role']; ?>

            <!-- My Profile (always visible) -->
            <a href="/inventory_system/auth/myaccount.php" class="menu-item">
                <i class="fas fa-user"></i> MY PROFILE
            </a>

            <?php
            // Helper to generate collapsible sections
            $sections = [];

            // Devices section
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $items = [
                    ['Add Device', '/inventory_system/devices/add_device.php', 'fas fa-plus'],
                    ['Bulk Upload', '/inventory_system/devices/upload_excel.php', 'fas fa-file-upload'],
                    ['Device List', '/inventory_system/devices/device_list.php', 'fas fa-list'],
                    ['In Stock', '/inventory_system/devices/instock.php', 'fas fa-box'],
                    ['Sold', '/inventory_system/devices/sold.php', 'fas fa-money-bill-wave'],
                    ['Search Device', '/inventory_system/devices/search.php', 'fas fa-search'],
                ];
                if ($role === 'super_admin') {
                    $items[] = ['Price list', '/inventory_system/devices/price_list.php', 'fas fa-dollar-sign'];
                }
                $sections['DEVICES'] = ['icon' => 'fas fa-laptop', 'items' => $items];
            }
            if (in_array($role, ['super_admin', 'inventory_admin'])){
                $items = [
                    ['Add Smartboard', '/inventory_system/smartboards/add_smartboard.php', 'fas fa-plus'],
                    ['Bulk upload', '/inventory_system/smartboards/bulk_upload.php', 'fas fa-file-upload'],
                    ['View stock', '/inventory_system/smartboards/smartboard_list.php', 'fas fa-box'],
                    ['Sold', '/inventory_system/smartboards/sold_smartboards.php', 'fas fa-money-bill-wave'],                      
                ];
                if ($role === 'super_admin') {
                    $items[] = ['Price list', '/inventory_system/smartboards/pricelist.php', 'fas fa-dollar-sign'];
                }
                $sections['SMARTBOARDS'] = ['icon' => 'fas fa-chalkboard','items' => $items];
            }

            // Monitors
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $sections['MONITORS'] = [
                    'icon' => 'fas fa-desktop',
                    'items' => [
                        ['Add Monitor', '/inventory_system/monitors/add_monitor.php', 'fas fa-plus'],
                        ['Bulk upload', '/inventory_system/monitors/bulkupload.php', 'fas fa-file-upload'],
                        ['View stock', '/inventory_system/monitors/monitors_instock.php', 'fas fa-box'],
                        ['Sold', '/inventory_system/monitors/sold_monitors.php', 'fas fa-money-bill-wave'],
                    ]
                ];
            }

            // Printers
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $sections['PRINTERS'] = [
                    'icon' => 'fas fa-print',
                    'items' => [
                        ['Add Printer', '/inventory_system/printers/add_printer.php', 'fas fa-plus'],
                        ['Bulk upload', '/inventory_system/printers/bulkupload.php', 'fas fa-file-upload'],
                        ['View stock', '/inventory_system/printers/printers_instock.php', 'fas fa-box'],
                        ['Sold', '/inventory_system/printers/soldprinters.php', 'fas fa-money-bill-wave'],
                    ]
                ];
            }
            if (in_array($role, ['super_admin','manager','inventory_admin'])){
                $items = [
                    ['Add Accessory', '/inventory_system/accessories/add_accessory.php', 'fas fa-plus'],
                    ['Bulk upload', '/inventory_system/accessories/bulkupload.php', 'fas fa-file-upload'],
                    ['View stock', '/inventory_system/accessories/accessory_instock.php', 'fas fa-box'],
                    ['Sold', '/inventory_system/accessories/sold_accessories.php', 'fas fa-money-bill-wave'],
                ];
                $sections['ACCESSORIES'] = ['icon' => 'fas fa-headphones', 'items' => $items];
            }

            // RAMs & SSDs
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $sections['RAMs & SSDs'] = [
                    'icon' => 'fas fa-microchip',
                    'items' => [
                        ['Add RAM/SSD', '/inventory_system/ram_ssd/add_ram.php', 'fas fa-plus'],
                        ['View Stock', '/inventory_system/ram_ssd/rams_instocks.php', 'fas fa-box'],
                        ['Give Out RAM/SSD', '/inventory_system/ram_ssd/give_ram.php', 'fas fa-gift'],
                        ['RAM/SSD Logs', '/inventory_system/ram_ssd/ram_ssd_logs.php', 'fas fa-clipboard-list'],
                        ['Sold RAMs/SSDs', '/inventory_system/ram_ssd/sold_rams_ssds.php', 'fas fa-shopping-cart'],
                    ]
                ];
            }

            // HDDs
            if (in_array($role, ['super_admin','inventory_admin','manager'])) {
                $sections['HDDs'] = [
                    'icon' => 'fas fa-database',
                    'items' => [
                        ['Add HDD', '/inventory_system/hdds/add_hdd.php', 'fas fa-plus'],
                        ['View Stock', '/inventory_system/hdds/hdds_instock.php', 'fas fa-box'],
                        ['Give Out HDD', '/inventory_system/hdds/give_hdd.php', 'fas fa-gift'],
                        ['HDDs Logs', '/inventory_system/hdds/hdd_logs.php', 'fas fa-clipboard-list'],
                        ['Sold HDDs', '/inventory_system/hdds/sold_hdds.php', 'fas fa-shopping-cart'],
                    ]
                ];
            }

            // Chargers
            if (in_array($role, ['super_admin','manager','inventory_admin','software'])) {
                $charger_items = [];
                if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                    $charger_items[] = ['Add Charger', '/inventory_system/chargers/add_charger.php', 'fas fa-plus'];
                    $charger_items[] = ['Chargers In Stock', '/inventory_system/chargers/chargers_instocks.php', 'fas fa-box'];
                }
                if (in_array($role, ['inventory_admin','software'])) {
                    $charger_items[] = ['Give Out Charger', '/inventory_system/chargers/give_charger.php', 'fas fa-gift'];
                }
                $charger_items[] = ['Charger Logs', '/inventory_system/chargers/charger_logs.php', 'fas fa-clipboard-list'];
                if (!empty($charger_items)) {
                    $sections['CHARGERS'] = ['icon' => 'fas fa-bolt', 'items' => $charger_items];
                }
            }

            // Software Dep
            if (in_array($role, ['super_admin','manager','inventory_admin','software'])) {
                $sw_items = [];
                if ($role === 'software') {
                    $sw_items[] = ['Search Device', '/inventory_system/software/search_device.php', 'fas fa-search'];
                }
                if (in_array($role, ['software','inventory_admin'])) {
                    $sw_items[] = ['Upgrade/Downgrade', '/inventory_system/software/update_specs.php', 'fas fa-cog'];
                }
                if (in_array($role, ['super_admin','manager','inventory_admin','software'])) {
                    $sw_items[] = ['Software Logs', '/inventory_system/software/software_logs.php', 'fas fa-clipboard-list'];
                }
                if (!empty($sw_items)) {
                    $sections['SOFTWARE DEP'] = ['icon' => 'fas fa-code-branch', 'items' => $sw_items];
                }
            }

            // Repairs
            if (in_array($role, ['super_admin','manager','technician','inventory_admin'])) {
                $repair_items = [];
                if ($role === 'technician') {
                    $repair_items[] = ['Search Device', '/inventory_system/repairs/search_device.php', 'fas fa-search'];
                    $repair_items[] = ['Add Repair', '/inventory_system/repairs/add_repair.php', 'fas fa-plus'];
                }
                $repair_items[] = ['Under Repair', '/inventory_system/repairs/under_repair.php', 'fas fa-tools'];
                $repair_items[] = ['Repair Logs', '/inventory_system/repairs/repair_logs.php', 'fas fa-clipboard-list'];
                $sections['REPAIRS'] = ['icon' => 'fas fa-wrench', 'items' => $repair_items];
            }

            // Sales
            if ($role === 'sales') {
                $sections['SALES'] = [
                    'icon' => 'fas fa-chart-line',
                    'items' => [
                        ['Make a Sale', '/inventory_system/sales/make_sale.php', 'fas fa-cash-register'],
                        ['My Sales', '/inventory_system/sales/my_sales.php', 'fas fa-chart-bar'],
                        ['Search Device', '/inventory_system/sales/search_device.php', 'fas fa-search'],
                    ]
                ];
            }

            if ($role === 'sales') {
                $sections['QUOTATIONS'] = [
                    'icon' => 'fas fa-file-invoice',
                    'items' => [
                        ['Make a Quotation', '/inventory_system/sales/make_quotation.php', 'fas fa-file-invoice'],
                        ['My Quotations', '/inventory_system/sales/my_quotations.php', 'fas fa-chart-bar'],
                    ]
                ];
            }

              if ($role === 'sales') {
                $sections['INVOICES'] = [
                    'icon' => 'fas fa-file-invoice-dollar',
                    'items' => [
                        ['Make an Invoice', '/inventory_system/sales/make_invoice.php', 'fas fa-file-invoice-dollar'],
                        ['My Invoices', '/inventory_system/sales/my_invoices.php', 'fas fa-chart-bar'],
                    ]
                ];
            }

            // Transfers
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $sections['TRANSFERS'] = [
                    'icon' => 'fas fa-exchange-alt',
                    'items' => [
                        ['Make Transfer', '/inventory_system/transfers/index.php', 'fas fa-exchange-alt'],
                        ['Transfer Logs', '/inventory_system/transfers/transfer_logs.php', 'fas fa-clipboard-list'],
                    ]
                ];
            }

            // Logs
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $log_items = [];
                if (in_array($role, ['super_admin','inventory_admin','manager'])) {
                    $log_items[] = ['Sales Logs', '/inventory_system/sales/sales_logs.php', 'fas fa-chart-line'];
                }
                if ($role === 'super_admin') {
                    $log_items[] = ['Activity Logs', '/inventory_system/logs/activity.php', 'fas fa-chart-bar'];
                }
                if (!empty($log_items)) {
                    $sections['LOGS'] = ['icon' => 'fas fa-history', 'items' => $log_items];
                }
            }

            // Admin (super_admin only)
            if ($role === 'super_admin') {
                $sections['ADMIN'] = [
                    'icon' => 'fas fa-cogs',
                    'items' => [
                        ['Add New user', '/inventory_system/auth/generate_code.php', 'fas fa-key'],
                        ['View Users', '/inventory_system/auth/view_users.php', 'fas fa-users'],
                    ]
                ];
            }

            // Output collapsible sections
            foreach ($sections as $title => $section) {
                echo '<div class="menu-section-wrapper">';
                echo '<div class="menu-section-header" data-collapsible="collapsible-' . md5($title) . '">';
                echo '<i class="' . $section['icon'] . ' section-icon"></i>';
                echo '<span>' . $title . '</span>';
                echo '<i class="fas fa-chevron-right toggle-icon"></i>';
                echo '</div>';
                echo '<div class="menu-section-items" id="collapsible-' . md5($title) . '" style="display: none;">';
                foreach ($section['items'] as $item) {
                    echo '<a href="' . $item[1] . '" class="menu-item sub-item">';
                    echo '<i class="' . $item[2] . '"></i> ' . htmlspecialchars($item[0]);
                    echo '</a>';
                }
                echo '</div>';
                echo '</div>';
            }
            ?>
            <!-- Logout button -->
            <a href="/inventory_system/auth/logout.php" class="menu-item logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</div>



<script>
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    if (sidebar.classList.contains('active') && window.innerWidth <= 1024) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

// Collapsible sections
document.addEventListener('DOMContentLoaded', function() {
    const headers = document.querySelectorAll('.menu-section-header');
    headers.forEach(header => {
        const targetId = header.getAttribute('data-collapsible');
        const itemsDiv = document.getElementById(targetId);
        if (itemsDiv) {
            // Start collapsed
            header.classList.remove('open');
            itemsDiv.style.display = 'none';

            header.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = header.classList.toggle('open');
                itemsDiv.style.display = isOpen ? 'block' : 'none';
            });
        }
    });

    // Close sidebar on link click (mobile)
    document.querySelectorAll('.menu-item, .menu-section-header').forEach(link => {
        link.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                // Don't close if clicking on a section header because we want toggle
                if (!this.classList.contains('menu-section-header')) {
                    setTimeout(() => {
                        const sidebar = document.querySelector('.sidebar');
                        const overlay = document.querySelector('.sidebar-overlay');
                        if (sidebar.classList.contains('active')) {
                            sidebar.classList.remove('active');
                            overlay.classList.remove('active');
                            document.body.style.overflow = '';
                        }
                    }, 100);
                }
            }
        });
    });

    // Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && window.innerWidth <= 1024) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        }
    });
});
</script>

</body>
</html>