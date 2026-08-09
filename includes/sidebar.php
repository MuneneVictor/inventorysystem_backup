
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
                    case 'super_admin': $dashboard_url = '../dashboard/superadmindashboard'; break;
                    case 'manager': $dashboard_url = '../dashboard/managerdashboard'; break;
                    case 'inventory_admin': $dashboard_url = '../dashboard/inventorydashboard'; break;
                    case 'sales': $dashboard_url = '../dashboard/salesdashboard'; break;
                    case 'cashier': $dashboard_url = '../dashboard/cashierdashboard'; break;
                    case 'software': $dashboard_url = '../dashboard/softwaredashboard'; break;
                    case 'technician': $dashboard_url = '../dashboard/techniciandashboard'; break;

                }
                echo $dashboard_url;
            ?>" class="logo-link">
                <img src="../assets/MC-LOGO.png" alt="Mombasa Computers" class="logo-img">
            </a>
            
        </div>

        <div class="sidebar-menu">
            <?php $role = $_SESSION['role']; ?>

            <!-- My Profile (always visible) -->
            <a href="../auth/myaccount" class="menu-item">
                <i class="fas fa-user"></i> MY PROFILE
            </a>

            <?php
            // Helper to generate collapsible sections
            $sections = [];


             if ($role === 'super_admin'){
                $sections['REPORTS'] = [
                    'icon' => 'fas fa-chart-line',
                    'items' => [
                        ['Daily Report', '../reports/daily_report', 'fas fa-calendar-day'],
                        ['Sales Report', '../sales/sales_logs', 'fas fa-chart-line'],
                        ['Sales Team', '../reports/sales_team', 'fas fa-users'],
                        ['Inventory Overview', '../reports/overview', 'fas fa-warehouse'],
                        ['Categories Report', '../reports/categories_report', 'fas fa-list-alt'],
                        ['Low Stock Report', '../reports/low_stock', 'fas fa-exclamation-triangle']
                    ]
                ];
            }
            // Devices section
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $deviceItems = [
                    ['Add Device', '../devices/add_device', 'fas fa-plus'],
                    ['Bulk Upload', '../devices/upload_excel', 'fas fa-file-upload'],
                    ['Device List', '../devices/device_list', 'fas fa-list'],
                    ['In Stock', '../devices/instock', 'fas fa-box'],
                    ['Sold', '../devices/sold', 'fas fa-money-bill-wave'],
                    ['Search Device', '../devices/search', 'fas fa-search'],
                    ['Give out Device', '../devices/give_device', 'fas fa-gift'],
                    ['Device Logs', '../devices/device_logs', 'fas fa-clipboard-list'],
                ];
                if ($role === 'super_admin' || $role === 'manager') {
                    $deviceItems[] = ['Price list', '../devices/price_list', 'fas fa-dollar-sign'];
                }
                $sections['DEVICES'] = ['icon' => 'fas fa-laptop', 'items' => $deviceItems];
            }
            if (in_array($role, ['super_admin', 'inventory_admin'])){
                $smartboardItems = [
                    ['Add Smartboard', '../smartboards/add_smartboard', 'fas fa-plus'],
                    ['Bulk upload', '../smartboards/bulk_upload', 'fas fa-file-upload'],
                    ['View stock', '../smartboards/smartboard_list', 'fas fa-box'],
                    ['Sold', '../smartboards/sold_smartboards', 'fas fa-money-bill-wave'],                      
                ];
                if ($role === 'super_admin') {
                    $smartboardItems[] = ['Price list', '../smartboards/pricelist', 'fas fa-dollar-sign'];
                }
                $sections['SMARTBOARDS'] = ['icon' => 'fas fa-chalkboard','items' => $smartboardItems];
            }

            // Monitors
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $sections['MONITORS'] = [
                    'icon' => 'fas fa-desktop',
                    'items' => [
                        ['Add Monitor', '../monitors/add_monitor', 'fas fa-plus'],
                        ['Bulk upload', '../monitors/bulkupload', 'fas fa-file-upload'],
                        ['View stock', '../monitors/monitors_instock', 'fas fa-box'],
                        ['Sold', '../monitors/sold_monitors', 'fas fa-money-bill-wave'],
                    ]
                ];
                if ($role === 'super_admin' || $role === 'manager') {
                    $sections['MONITORS']['items'][] = ['Price list', '../monitors/price_list_monitors', 'fas fa-dollar-sign'];
                }
            }

            // Printers
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $sections['PRINTERS'] = [
                    'icon' => 'fas fa-print',
                    'items' => [
                        ['Add Printer', '../printers/add_printer', 'fas fa-plus'],
                        ['Bulk upload', '../printers/bulkupload', 'fas fa-file-upload'],
                        ['View stock', '../printers/printers_instock', 'fas fa-box'],
                        ['Sold', '../printers/soldprinters', 'fas fa-money-bill-wave'],
                    ]
                ];
                if ($role === 'super_admin' || $role === 'manager') {
                    $sections['PRINTERS']['items'][] = ['Price list', '../printers/price_list_printers', 'fas fa-dollar-sign'];
                }
            }

            // UPS
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $sections['UPS'] = [
                    'icon' => 'fas fa-battery-full',
                    'items' => [
                        ['Add UPS', '../ups/add_ups', 'fas fa-plus'],
                        ['Bulk upload', '../ups/bulk_upload', 'fas fa-file-upload'],
                        ['View Stock', '../ups/ups_instock', 'fas fa-box'],
                        ['Sold UPS', '../ups/sold_ups', 'fas fa-money-bill-wave'],
                    ]
                ];
                if ($role === 'super_admin') {
                    $sections['UPS']['items'][] = ['Price list', '../ups/price_list_ups', 'fas fa-dollar-sign'];
                }
            }

          // PHONES
                if (in_array($role, ['super_admin', 'manager', 'inventory_admin'])) {
                    $phoneItems = [
                        ['Add Phone', '../phones/add_phone', 'fas fa-plus'],
                        ['Bulk upload', '../phones/bulk_upload', 'fas fa-file-upload'],
                        ['View stock', '../phones/phones_instock', 'fas fa-box'],
                        ['Sold Phones', '../phones/sold_phones', 'fas fa-money-bill-wave'],
                    ];
                    // Only super_admin gets the Price list
                    if ($role === 'super_admin') {
                        $phoneItems[] = ['Price list', '../phones/price_list_phones', 'fas fa-dollar-sign'];
                    }
                    $sections['PHONES'] = [
                        'icon' => 'fas fa-mobile-alt',
                        'items' => $phoneItems
                    ];
                }

            // Accessories
            if (in_array($role, ['super_admin','manager','inventory_admin'])){
                $accessoryItems = [
                    ['Add Accessory', '../accessories/add_accessory', 'fas fa-plus'],
                    ['Bulk upload', '../accessories/bulkupload', 'fas fa-file-upload'],
                    ['View stock', '../accessories/accessory_instock', 'fas fa-box'],
                    ['Give Out Accessory', '../accessories/give_accessory', 'fas fa-gift'],
                    ['Accessory Logs', '../accessories/accessory_logs', 'fas fa-clipboard-list'],
                    ['Sold', '../accessories/sold_accessories', 'fas fa-money-bill-wave'],
                ];
                $sections['ACCESSORIES'] = ['icon' => 'fas fa-headphones', 'items' => $accessoryItems];
            }

            // RAMs & SSDs
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $sections['RAMs & SSDs'] = [
                    'icon' => 'fas fa-microchip',
                    'items' => [
                        ['Add RAM/SSD', '../ram_ssd/add_ram', 'fas fa-plus'],
                        ['View Stock', '../ram_ssd/rams_instocks', 'fas fa-box'],
                        ['Give Out RAM/SSD', '../ram_ssd/give_ram', 'fas fa-gift'],
                        ['RAM/SSD Logs', '../ram_ssd/ram_ssd_logs', 'fas fa-clipboard-list'],
                        ['Sold RAMs/SSDs', '../ram_ssd/sold_rams_ssds', 'fas fa-shopping-cart'],
                    ]
                ];
            }

            // HDDs
            if (in_array($role, ['super_admin','inventory_admin','manager'])) {
                $sections['HDDs'] = [
                    'icon' => 'fas fa-database',
                    'items' => [
                        ['Add HDD', '../hdds/add_hdd', 'fas fa-plus'],
                        ['Bulk Upload', '../hdds/bulkupload_hdd', 'fas fa-file-upload'],
                        ['View Stock', '../hdds/hdds_instock', 'fas fa-box'],
                        ['Give Out HDD', '../hdds/give_hdd', 'fas fa-gift'],
                        ['HDDs Logs', '../hdds/hdd_logs', 'fas fa-clipboard-list'],
                        ['Sold HDDs', '../hdds/sold_hdds', 'fas fa-shopping-cart'],
                    ]
                ];
            }

            // Chargers
            if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                $charger_items = [];
                if (in_array($role, ['super_admin','manager','inventory_admin'])) {
                    $charger_items[] = ['Add Charger', '../chargers/add_charger', 'fas fa-plus'];
                    $charger_items[] = ['Bulk Upload', '../chargers/bulkupload_charger', 'fas fa-file-upload'];
                    $charger_items[] = ['View Stock', '../chargers/chargers_instock', 'fas fa-box'];
                }
                if (in_array($role, ['inventory_admin', 'super_admin'])) {
                    $charger_items[] = ['Give Out Charger', '../chargers/give_charger', 'fas fa-gift'];
                }
                $charger_items[] = ['Charger Logs', '../chargers/charger_logs', 'fas fa-clipboard-list'];
                $charger_items[] = ['Sold Chargers', '../chargers/sold_chargers', 'fas fa-shopping-cart'];
                if (!empty($charger_items)) {
                    $sections['CHARGERS'] = ['icon' => 'fas fa-bolt', 'items' => $charger_items];
                }
            }
            

               if (in_array($role, ['super_admin','inventory_admin','manager'])) {
                $sections['GRAPHICS CARD'] = [
                    'icon' => 'fas fa-video',
                    'items' => [
                        ['Add Graphics Card', '../graphic_cards/add_graphic_card', 'fas fa-plus'],
                        ['View Stock', '../graphic_cards/graphic_cards_instock', 'fas fa-box'],
                        ['Give Out Graphics Card', '../graphic_cards/give_graphics_card', 'fas fa-gift'],
                        ['Graphics Cards Logs', '../graphic_cards/graphics_card_logs', 'fas fa-clipboard-list'],
                        ['Sold Graphics Cards', '../graphic_cards/sold_graphic_cards', 'fas fa-shopping-cart'],
                    ]
                ];
            }
            // Software Dep
            if (in_array($role, ['super_admin','manager','inventory_admin','software'])) {
                $sw_items = [];
                if ($role === 'software') {
                    $sw_items[] = ['Search Device', '../software/search_device', 'fas fa-search'];
                }
                if (in_array($role, ['software','inventory_admin'])) {
                    $sw_items[] = ['Upgrade/Downgrade', '../software/update_specs', 'fas fa-cog'];
                }
                if (in_array($role, ['super_admin','manager','inventory_admin','software'])) {
                    $sw_items[] = ['Software Logs', '../software/software_logs', 'fas fa-clipboard-list'];
                }
                if (!empty($sw_items)) {
                    $sections['SOFTWARE DEP'] = ['icon' => 'fas fa-code-branch', 'items' => $sw_items];
                }
            }

            // Repairs
            if (in_array($role, ['super_admin','manager','technician','inventory_admin'])) {
                $repair_items = [];
                if ($role === 'technician') {
                    $repair_items[] = ['Search Device', '../repairs/search_device', 'fas fa-search'];
                    $repair_items[] = ['Add Repair', '../repairs/add_repair', 'fas fa-plus'];
                }
                $repair_items[] = ['Under Repair', '../repairs/under_repair', 'fas fa-tools'];
                $repair_items[] = ['Repair Logs', '../repairs/repair_logs', 'fas fa-clipboard-list'];
                $sections['REPAIRS'] = ['icon' => 'fas fa-wrench', 'items' => $repair_items];
            }

            // Sales
            if ($role === 'sales') {
                $sections['SALES'] = [
                    'icon' => 'fas fa-chart-line',
                    'items' => [
                        ['Make a Sale', '../sales/make_sale', 'fas fa-cash-register'],
                        ['My Sales', '../sales/my_sales', 'fas fa-chart-bar'],
                        ['Search', '../sales/search_device', 'fas fa-search'],
                    ]
                ];
            }
                 if (in_array($role, ['sales', 'super_admin', 'manager', 'technician'])) {
                $sections['QUOTATIONS'] = [
                    'icon' => 'fas fa-file-invoice',
                    'items' => [
                        ['Write Quotation', '../quotations/write_quotation', 'fas fa-file-invoice'],
                        ['My Quotations', '../quotations/quotations_list', 'fas fa-chart-bar'],
                    ]
                ];
            }

              if (in_array($role, ['sales', 'super_admin', 'manager', 'technician'])) {
                $sections['INVOICES'] = [
                    'icon' => 'fas fa-file-invoice-dollar',
                    'items' => [
                        ['Write Invoice', '../invoices/write_invoice', 'fas fa-file-invoice-dollar'],
                        ['My Invoices', '../invoices/invoices_list', 'fas fa-chart-bar'],
                    ]
                ];
            }

            if ($role === 'sales') {
                $sections['CLIENTS'] = [
                    'icon' => 'fas fa-users',
                    'items' => [
                        ['Add Client', '../sales/add_client', 'fas fa-user-plus'],
                        ['My Clients', '../sales/view_clients', 'fas fa-users'],
                    ]
                ];
            }

            // cashier
            if ($role === 'cashier') {
                $sections['SALES'] = [
                    'icon' => 'fas fa-chart-line',
                    'items' => [
                        ['Process Sale', '../sales/make_sale', 'fas fa-cash-register'],
                        ['Active Sales', '../sales/active_sales', 'fas fa-shopping-cart'],
                        ['View Daily Report', '../reports/daily_report', 'fas fa-calendar-day'],
                        ['Sales Logs', '../sales/sales_logs', 'fas fa-chart-bar'],
                        ['Search', '../sales/search_device', 'fas fa-search'],
                    ]
                ];
            }
              if (in_array($role, ['cashier', 'super_admin', 'manager'])) {
                $sections['EXPENSES'] = [
                    'icon' => 'fas fa-money-bill-wave',
                    'items' => [
                        ['Add Expense', '../sales/add_expense', 'fas fa-money-bill-wave'],
                        ['Expense Logs', '../sales/expenses_logs', 'fas fa-chart-bar'],
                    ]
                ];
            }

            if ($role === 'cashier') {
                $sections['CLIENTS'] = [
                    'icon' => 'fas fa-users',
                    'items' => [
                        ['Add Client', '../sales/add_client', 'fas fa-user-plus'],
                        ['View Clients', '../sales/view_clients', 'fas fa-users'],
                    ]
                ];
            }


       
            // Transfers
            if (in_array($role, ['super_admin','manager','inventory_admin', 'cashier'])) {
                $sections['TRANSFERS'] = [
                    'icon' => 'fas fa-exchange-alt',
                    'items' => [
                        ['Make Transfer', '../transfers/index', 'fas fa-exchange-alt'],
                        ['Transfer Logs', '../transfers/transfer_logs', 'fas fa-clipboard-list'],
                    ]
                ];
            }

            // Logs
            if (in_array($role, ['super_admin','manager'])) {
                $log_items = [];
                if (in_array($role, ['super_admin','inventory_admin','manager'])) {
                    $log_items[] = ['Sales Logs', '../sales/sales_logs', 'fas fa-chart-line'];
                }
                if ($role === 'super_admin') {
                    $log_items[] = ['Activity Logs', '../logs/activity', 'fas fa-chart-bar'];
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
                        ['Add New user', '../auth/generate_code', 'fas fa-key'],
                        ['View Users', '../auth/view_users', 'fas fa-users'],
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
            <a href="../auth/logout" class="menu-item logout-btn">
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