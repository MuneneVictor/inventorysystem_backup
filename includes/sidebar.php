
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
</head>
<body>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/sidebar.css">
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
                    case 'cashier': $dashboard_url = '/inventory_system/dashboard/cashierdashboard.php'; break;
                    case 'software': $dashboard_url = '/inventory_system/dashboard/softwaredashboard.php'; break;
                    case 'technician': $dashboard_url = '/inventory_system/dashboard/techniciandashboard.php'; break;

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


             if ($role === 'super_admin'){
                $sections['REPORTS'] = [
                    'icon' => 'fas fa-chart-line',
                    'items' => [
                        ['Daily Report', '/inventory_system/reports/daily_report.php', 'fas fa-calendar-day'],
                        ['Sales Report', '/inventory_system/sales/sales_logs.php', 'fas fa-chart-line'],
                        ['Sales Team', '/inventory_system/reports/sales_team.php', 'fas fa-users'],
                        ['Inventory Overview', '/inventory_system/reports/overview.php', 'fas fa-warehouse'],
                        ['Categories Report', '/inventory_system/reports/categories_report.php', 'fas fa-list-alt'],
                        ['Low Stock Report', '/inventory_system/reports/low_stock.php', 'fas fa-exclamation-triangle']
                    ]
                ];
            }
            // Devices section
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $deviceItems = [
                    ['Add Device', '/inventory_system/devices/add_device.php', 'fas fa-plus'],
                    ['Bulk Upload', '/inventory_system/devices/upload_excel.php', 'fas fa-file-upload'],
                    ['Device List', '/inventory_system/devices/device_list.php', 'fas fa-list'],
                    ['In Stock', '/inventory_system/devices/instock.php', 'fas fa-box'],
                    ['Sold', '/inventory_system/devices/sold.php', 'fas fa-money-bill-wave'],
                    ['Search Device', '/inventory_system/devices/search.php', 'fas fa-search'],
                    ['Give out Device', '/inventory_system/devices/give_device.php', 'fas fa-gift'],
                    ['Device Logs', '/inventory_system/devices/device_logs.php', 'fas fa-clipboard-list'],
                ];
                if ($role === 'super_admin' || $role === 'manager') {
                    $deviceItems[] = ['Price list', '/inventory_system/devices/price_list.php', 'fas fa-dollar-sign'];
                }
                $sections['DEVICES'] = ['icon' => 'fas fa-laptop', 'items' => $deviceItems];
            }
            if (in_array($role, ['super_admin', 'inventory_admin'])){
                $smartboardItems = [
                    ['Add Smartboard', '/inventory_system/smartboards/add_smartboard.php', 'fas fa-plus'],
                    ['Bulk upload', '/inventory_system/smartboards/bulk_upload.php', 'fas fa-file-upload'],
                    ['View stock', '/inventory_system/smartboards/smartboard_list.php', 'fas fa-box'],
                    ['Sold', '/inventory_system/smartboards/sold_smartboards.php', 'fas fa-money-bill-wave'],                      
                ];
                if ($role === 'super_admin') {
                    $smartboardItems[] = ['Price list', '/inventory_system/smartboards/pricelist.php', 'fas fa-dollar-sign'];
                }
                $sections['SMARTBOARDS'] = ['icon' => 'fas fa-chalkboard','items' => $smartboardItems];
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
                if ($role === 'super_admin' || $role === 'manager') {
                    $sections['MONITORS']['items'][] = ['Price list', '/inventory_system/monitors/price_list_monitors.php', 'fas fa-dollar-sign'];
                }
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
                if ($role === 'super_admin' || $role === 'manager') {
                    $sections['PRINTERS']['items'][] = ['Price list', '/inventory_system/printers/price_list_printers.php', 'fas fa-dollar-sign'];
                }
            }

            // UPS
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $sections['UPS'] = [
                    'icon' => 'fas fa-battery-full',
                    'items' => [
                        ['Add UPS', '/inventory_system/ups/add_ups.php', 'fas fa-plus'],
                        ['Bulk upload', '/inventory_system/ups/bulk_upload.php', 'fas fa-file-upload'],
                        ['View Stock', '/inventory_system/ups/ups_instock.php', 'fas fa-box'],
                        ['Sold UPS', '/inventory_system/ups/sold_ups.php', 'fas fa-money-bill-wave'],
                    ]
                ];
                if ($role === 'super_admin') {
                    $sections['UPS']['items'][] = ['Price list', '/inventory_system/ups/price_list_ups.php', 'fas fa-dollar-sign'];
                }
            }

          // PHONES
                if (in_array($role, ['super_admin', 'manager', 'inventory_admin'])) {
                    $phoneItems = [
                        ['Add Phone', '/inventory_system/phones/add_phone.php', 'fas fa-plus'],
                        ['Bulk upload', '/inventory_system/phones/bulk_upload.php', 'fas fa-file-upload'],
                        ['View stock', '/inventory_system/phones/phones_instock.php', 'fas fa-box'],
                        ['Sold Phones', '/inventory_system/phones/sold_phones.php', 'fas fa-money-bill-wave'],
                    ];
                    // Only super_admin gets the Price list
                    if ($role === 'super_admin') {
                        $phoneItems[] = ['Price list', '/inventory_system/phones/price_list_phones.php', 'fas fa-dollar-sign'];
                    }
                    $sections['PHONES'] = [
                        'icon' => 'fas fa-mobile-alt',
                        'items' => $phoneItems
                    ];
                }

            // Accessories
            if (in_array($role, ['super_admin','manager','inventory_admin'])){
                $accessoryItems = [
                    ['Add Accessory', '/inventory_system/accessories/add_accessory.php', 'fas fa-plus'],
                    ['Bulk upload', '/inventory_system/accessories/bulkupload.php', 'fas fa-file-upload'],
                    ['View stock', '/inventory_system/accessories/accessory_instock.php', 'fas fa-box'],
                    ['Give Out Accessory', '/inventory_system/accessories/give_accessory.php', 'fas fa-gift'],
                    ['Accessory Logs', '/inventory_system/accessories/accessory_logs.php', 'fas fa-clipboard-list'],
                    ['Sold', '/inventory_system/accessories/sold_accessories.php', 'fas fa-money-bill-wave'],
                ];
                $sections['ACCESSORIES'] = ['icon' => 'fas fa-headphones', 'items' => $accessoryItems];
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
                        ['Bulk Upload', '/inventory_system/hdds/bulkupload_hdd.php', 'fas fa-file-upload'],
                        ['View Stock', '/inventory_system/hdds/hdds_instock.php', 'fas fa-box'],
                        ['Give Out HDD', '/inventory_system/hdds/give_hdd.php', 'fas fa-gift'],
                        ['HDDs Logs', '/inventory_system/hdds/hdd_logs.php', 'fas fa-clipboard-list'],
                        ['Sold HDDs', '/inventory_system/hdds/sold_hdds.php', 'fas fa-shopping-cart'],
                    ]
                ];
            }

            // Chargers
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $charger_items = [];
                if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                    $charger_items[] = ['Add Charger', '/inventory_system/chargers/add_charger.php', 'fas fa-plus'];
                    $charger_items[] = ['Bulk Upload', '/inventory_system/chargers/bulkupload_charger.php', 'fas fa-file-upload'];
                    $charger_items[] = ['View Stock', '/inventory_system/chargers/chargers_instock.php', 'fas fa-box'];
                }
                if (in_array($role, ['inventory_admin', 'super_admin'])) {
                    $charger_items[] = ['Give Out Charger', '/inventory_system/chargers/give_charger.php', 'fas fa-gift'];
                }
                $charger_items[] = ['Charger Logs', '/inventory_system/chargers/charger_logs.php', 'fas fa-clipboard-list'];
                $charger_items[] = ['Sold Chargers', '/inventory_system/chargers/sold_chargers.php', 'fas fa-shopping-cart'];
                if (!empty($charger_items)) {
                    $sections['CHARGERS'] = ['icon' => 'fas fa-bolt', 'items' => $charger_items];
                }
            }
            

               if (in_array($role, ['super_admin','inventory_admin','manager'])) {
                $sections['GRAPHICS CARD'] = [
                    'icon' => 'fas fa-video',
                    'items' => [
                        ['Add Graphics Card', '/inventory_system/graphic_cards/add_graphic_card.php', 'fas fa-plus'],
                        ['View Stock', '/inventory_system/graphic_cards/graphic_cards_instock.php', 'fas fa-box'],
                        ['Give Out Graphics Card', '/inventory_system/graphic_cards/give_graphics_card.php', 'fas fa-gift'],
                        ['Graphics Cards Logs', '/inventory_system/graphic_cards/graphics_card_logs.php', 'fas fa-clipboard-list'],
                        ['Sold Graphics Cards', '/inventory_system/graphic_cards/sold_graphic_cards.php', 'fas fa-shopping-cart'],
                    ]
                ];
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
                        ['Search', '/inventory_system/sales/search_device.php', 'fas fa-search'],
                    ]
                ];
            }
                 if (in_array($role, ['sales', 'super_admin', 'manager', 'technician'])) {
                $sections['QUOTATIONS'] = [
                    'icon' => 'fas fa-file-invoice',
                    'items' => [
                        ['Write Quotation', '/inventory_system/quotations/write_quotation.php', 'fas fa-file-invoice'],
                        ['My Quotations', '/inventory_system/quotations/quotations_list.php', 'fas fa-chart-bar'],
                    ]
                ];
            }

              if (in_array($role, ['sales', 'super_admin', 'manager', 'technician'])) {
                $sections['INVOICES'] = [
                    'icon' => 'fas fa-file-invoice-dollar',
                    'items' => [
                        ['Write Invoice', '/inventory_system/invoices/write_invoice.php', 'fas fa-file-invoice-dollar'],
                        ['My Invoices', '/inventory_system/invoices/invoices_list.php', 'fas fa-chart-bar'],
                    ]
                ];
            }

            if ($role === 'sales') {
                $sections['CLIENTS'] = [
                    'icon' => 'fas fa-users',
                    'items' => [
                        ['Add Client', '/inventory_system/sales/add_client.php', 'fas fa-user-plus'],
                        ['My Clients', '/inventory_system/sales/view_clients.php', 'fas fa-users'],
                    ]
                ];
            }

            // cashier
            if ($role === 'cashier') {
                $sections['SALES'] = [
                    'icon' => 'fas fa-chart-line',
                    'items' => [
                        ['Process Sale', '/inventory_system/sales/make_sale.php', 'fas fa-cash-register'],
                        ['Active Sales', '/inventory_system/sales/active_sales.php', 'fas fa-shopping-cart'],
                        ['Sales Logs', '/inventory_system/sales/sales_logs.php', 'fas fa-chart-bar'],
                        ['Search', '/inventory_system/sales/search_device.php', 'fas fa-search'],
                    ]
                ];
            }
              if (in_array($role, ['cashier', 'super_admin', 'manager'])) {
                $sections['EXPENSES'] = [
                    'icon' => 'fas fa-money-bill-wave',
                    'items' => [
                        ['Add Expense', '/inventory_system/sales/add_expense.php', 'fas fa-money-bill-wave'],
                        ['Expense Logs', '/inventory_system/sales/expenses_logs.php', 'fas fa-chart-bar'],
                    ]
                ];
            }

            if ($role === 'cashier') {
                $sections['CLIENTS'] = [
                    'icon' => 'fas fa-users',
                    'items' => [
                        ['Add Client', '/inventory_system/sales/add_client.php', 'fas fa-user-plus'],
                        ['View Clients', '/inventory_system/sales/view_clients.php', 'fas fa-users'],
                    ]
                ];
            }


       
            // Transfers
            if (in_array($role, ['super_admin','manager','inventory_admin', 'cashier'])) {
                $sections['TRANSFERS'] = [
                    'icon' => 'fas fa-exchange-alt',
                    'items' => [
                        ['Make Transfer', '/inventory_system/transfers/index.php', 'fas fa-exchange-alt'],
                        ['Transfer Logs', '/inventory_system/transfers/transfer_logs.php', 'fas fa-clipboard-list'],
                    ]
                ];
            }

            // Logs
            if (in_array($role, ['super_admin','manager'])) {
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