<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";

$role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');
$user_branch = $_SESSION['branch'] ?? null;

// Allowed roles
if (!in_array($role, ['sales', 'super_admin', 'inventory_admin', 'manager', 'cashier'])) {
    die("ACCESS DENIED.");
}

// --- Handle reset (clear session sale_id) ---
if (isset($_GET['reset_sale']) && $_GET['reset_sale'] == '1') {
    unset($_SESSION['current_sale_id']);
    header("Location: make_sale.php");
    exit;
}

// --- Handle sale selection (store in session) ---
if (isset($_GET['sale_id']) && is_numeric($_GET['sale_id'])) {
    $new_sale_id = (int)$_GET['sale_id'];
    $stmt = $conn->prepare("SELECT id, sale_status, sold_by FROM sales WHERE id = ?");
    $stmt->execute([$new_sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sale && $sale['sale_status'] === 'active') {
        if ($role !== 'cashier' && $sale['sold_by'] != $user_id) {
            die("You do not have permission to access this sale.");
        }
        $_SESSION['current_sale_id'] = $new_sale_id;
        header("Location: make_sale.php");
        exit;
    } else {
        unset($_SESSION['current_sale_id']);
        header("Location: make_sale.php?error=invalid_sale");
        exit;
    }
}

// --- Check if we have a valid sale in session ---
$current_sale_id = $_SESSION['current_sale_id'] ?? 0;
$sale_valid = false;
if ($current_sale_id) {
    $stmt = $conn->prepare("SELECT id, sale_status, sold_by FROM sales WHERE id = ?");
    $stmt->execute([$current_sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sale && $sale['sale_status'] === 'active') {
        $sale_valid = true;
        if ($role !== 'cashier' && $sale['sold_by'] != $user_id) {
            $sale_valid = false;
        }
    }
    if (!$sale_valid) {
        unset($_SESSION['current_sale_id']);
        $current_sale_id = 0;
    }
}

date_default_timezone_set('Africa/Nairobi');
$greeting = '';
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';



function secureQuery($conn, $sql, $params = []) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("DB Error: " . $e->getMessage());
        return false;
    }
}

// Determine active sales based on role
if ($role === 'cashier') {
    $stmt = secureQuery($conn, "
        SELECT s.*, u.full_name AS salesperson_name, u.branch AS salesperson_branch
        FROM sales s
        LEFT JOIN users u ON s.sold_by = u.id
        WHERE s.sale_status = 'active'
        ORDER BY s.created_at DESC
    ");
    $activeSales = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $stmt = secureQuery($conn, "SELECT id, full_name, branch FROM users WHERE role = 'sales' ORDER BY full_name");
    $salespersons = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} else {
    $stmt = secureQuery($conn, "
        SELECT s.*, u.full_name AS salesperson_name, u.branch AS salesperson_branch
        FROM sales s
        LEFT JOIN users u ON s.sold_by = u.id
        WHERE s.sale_status = 'active' AND s.sold_by = ?
        ORDER BY s.created_at DESC
    ", [$user_id]);
    $activeSales = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $salespersons = [];
}

$show_cards = $sale_valid && $current_sale_id > 0;
require_once "../includes/sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Process Sale | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* (All existing CSS unchanged) */
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

        .section {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .table th {
            background: var(--gray-50);
            padding: 0.75rem 0.5rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid var(--gray-200);
        }
        .table td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid var(--gray-100);
        }
        .table tr:hover {
            background: var(--gray-50);
        }
        .btn {
            padding: 0.5rem 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        .btn:hover {
            background: var(--primary-light);
        }
        .btn-secondary {
            background: var(--gray-500);
        }
        .btn-secondary:hover {
            background: var(--gray-600);
        }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-300);
            color: var(--gray-700);
        }
        .btn-outline:hover {
            background: var(--gray-100);
        }
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: var(--gray-700);
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .client-search-results {
            background: white;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            max-height: 200px;
            overflow-y: auto;
            display: none;
            position: absolute;
            width: 100%;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
        }
        .client-search-results .result-item {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid var(--gray-100);
        }
        .client-search-results .result-item:hover {
            background: var(--gray-50);
        }

        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

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

        .text-muted {
            color: var(--gray-400);
        }

        footer {
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
            .form-row {
                grid-template-columns: 1fr;
            }
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
            Process Sale
        </h1>
        <div class="breadcrumb">
            <a href="<?php
                if ($role === 'super_admin') echo '/inventory_system/dashboard/superadmindashboard.php';
                elseif ($role === 'manager') echo '/inventory_system/dashboard/managerdashboard.php';
                elseif ($role === 'inventory_admin') echo '/inventory_system/dashboard/inventorydashboard.php';
                elseif ($role === 'sales') echo '/inventory_system/dashboard/salesdashboard.php';
                elseif ($role === 'cashier') echo '/inventory_system/dashboard/cashierdashboard.php';
                else echo '#';
            ?>"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <span>Process Sale</span>
        </div>
        <div class="greeting"><?= $show_cards ? "Adding items to sale ID: $current_sale_id" : "Select an active sale or start a new one." ?></div>
    </div>

    <?php if (!$show_cards): ?>
        <!-- ============================================================ -->
        <!--  ACTIVE SALES LIST & NEW SALE FORM                            -->
        <!-- ============================================================ -->

        <!-- Active Sales -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-clock"></i> Active Sales
                <?php if (!empty($activeSales)): ?>
                    <span style="background:var(--gray-200); padding:0.25rem 0.75rem; border-radius:9999px; font-size:0.8rem; margin-left:0.5rem;"><?= count($activeSales) ?></span>
                <?php endif; ?>
            </div>
            <?php if (empty($activeSales)): ?>
                <p class="text-muted">No active sales found. Start a new sale below.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Phone</th>
                                <th>Salesperson</th>
                                <th>Date</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeSales as $sale): ?>
                                <tr>
                                    <td><strong>#<?= $sale['id'] ?></strong></td>
                                    <td><?= htmlspecialchars($sale['client_name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($sale['client_phone'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($sale['salesperson_name'] ?? 'Unknown') ?></td>
                                    <td><?= date('M j, Y H:i', strtotime($sale['created_at'])) ?></td>
                                    <td style="text-align:right;">
                                        <a href="make_sale.php?sale_id=<?= $sale['id'] ?>" class="btn btn-sm">
                                            <i class="fas fa-arrow-right"></i> Select
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- New Sale Form -->
        <div class="section">
            <div class="section-title"><i class="fas fa-plus-circle"></i> Start New Sale</div>
            <form id="newSaleForm" action="create_sale.php" method="POST">
                <?php if ($role === 'cashier'): ?>
                    <div class="form-group">
                        <label for="salesperson_id">Assign to Salesperson <span style="color:red;">*</span></label>
                        <select name="salesperson_id" id="salesperson_id" required>
                            <option value="">— Select Salesperson —</option>
                            <?php foreach ($salespersons as $sp): ?>
                                <option value="<?= $sp['id'] ?>"><?= htmlspecialchars($sp['full_name']) ?> (<?= htmlspecialchars($sp['branch']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="salesperson_id" value="<?= $user_id ?>">
                <?php endif; ?>

                <!-- Client search & selection -->
                <div class="form-group" style="position:relative;">
                    <label for="client_search">Client (optional)</label>
                    <input type="text" id="client_search" placeholder="Type client name to search..." autocomplete="off">
                    <input type="hidden" name="client_id" id="client_id" value="">
                    <div id="clientResults" class="client-search-results"></div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="client_name">Client Name / Organization</label>
                        <input type="text" name="client_name" id="client_name" placeholder="e.g., John Doe or ABC Ltd">
                    </div>
                    <div class="form-group">
                        <label for="client_phone">Phone Number</label>
                        <input type="text" name="client_phone" id="client_phone" placeholder="e.g., 0712345678">
                    </div>
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <button type="submit" class="btn"><i class="fas fa-cart-plus"></i> Create Sale</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- ============================================================ -->
        <!--  ITEM SELECTION CARDS (SALE ALREADY SELECTED)                 -->
        <!-- ============================================================ -->
        <div style="margin-bottom:1rem;">
            <a href="make_sale.php?reset_sale=1" class="btn"><i class="fas fa-undo"></i> Change Sale</a>
            <span style="margin-left:1rem; font-weight:500;">Current Sale ID: <?= $current_sale_id ?></span>
        </div>

        <div class="card-grid">
            <!-- Device -->
            <a href="sell_device.php?sale_id=<?= $current_sale_id ?>" class="sale-card">
                <div class="icon"><i class="fas fa-laptop"></i></div>
                <div class="label">Device</div>
                <div class="sub-label">Laptops, Desktops, etc.</div>
            </a>

            <!-- Monitor -->
            <a href="sell_monitor.php?sale_id=<?= $current_sale_id ?>" class="sale-card">
                <div class="icon"><i class="fas fa-desktop"></i></div>
                <div class="label">Monitor</div>
                <div class="sub-label">Displays</div>
            </a>

            <!-- Printer -->
            <a href="sell_printer.php?sale_id=<?= $current_sale_id ?>" class="sale-card">
                <div class="icon"><i class="fas fa-print"></i></div>
                <div class="label">Printer</div>
                <div class="sub-label">Printers</div>
            </a>

            <!-- Smartboard -->
            <a href="sell_smartboard.php?sale_id=<?= $current_sale_id ?>" class="sale-card">
                <div class="icon"><i class="fas fa-chalkboard"></i></div>
                <div class="label">Smartboard</div>
                <div class="sub-label">Interactive Boards</div>
            </a>

            <!-- UPS -->
            <a href="sell_ups.php?sale_id=<?= $current_sale_id ?>" class="sale-card">
                <div class="icon"><i class="fas fa-bolt"></i></div>
                <div class="label">UPS</div>
                <div class="sub-label">Power Backup</div>
            </a>

            <!-- Phone -->
            <a href="sell_phone.php?sale_id=<?= $current_sale_id ?>" class="sale-card">
                <div class="icon"><i class="fas fa-mobile-alt"></i></div>
                <div class="label">Phone</div>
                <div class="sub-label">Smartphones</div>
            </a>

            <!-- Accessory -->
            <a href="sell_accessory.php?sale_id=<?= $current_sale_id ?>" class="sale-card">
                <div class="icon"><i class="fas fa-plug"></i></div>
                <div class="label">Accessory</div>
                <div class="sub-label">Cables, Mice, etc.</div>
            </a>

            <!-- Charger -->
            <a href="sell_charger.php?sale_id=<?= $current_sale_id ?>" class="sale-card">
                <div class="icon"><i class="fas fa-battery-three-quarters"></i></div>
                <div class="label">Charger</div>
                <div class="sub-label">Laptop Chargers</div>
            </a>

            <!-- Graphics Card -->
            <a href="sell_graphics_card.php?sale_id=<?= $current_sale_id ?>" class="sale-card">
                <div class="icon"><i class="fas fa-microchip"></i></div>
                <div class="label">Graphics Card</div>
                <div class="sub-label">GPUs</div>
            </a>

            <!-- HDD -->
            <a href="sell_hdd.php?sale_id=<?= $current_sale_id ?>" class="sale-card">
                <div class="icon"><i class="fas fa-hdd"></i></div>
                <div class="label">HDD</div>
                <div class="sub-label">Hard Drives</div>
            </a>

            <!-- RAM/SSD -->
            <a href="sell_ram_ssd.php?sale_id=<?= $current_sale_id ?>" class="sale-card">
                <div class="icon"><i class="fas fa-memory"></i></div>
                <div class="label">RAM / SSD</div>
                <div class="sub-label">Memory & Storage</div>
            </a>
        </div>

        <div style="margin-top:1rem;">
            <a href="checkout.php?sale_id=<?= $current_sale_id ?>" class="btn"><i class="fas fa-shopping-cart"></i> Go to Checkout</a>
        </div>
    <?php endif; ?>

    <footer>
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </footer>
</div>

<script>
    // Client search AJAX (only needed when the form is visible)
    <?php if (!$show_cards): ?>
        const searchInput = document.getElementById('client_search');
        const clientIdInput = document.getElementById('client_id');
        const clientNameInput = document.getElementById('client_name');
        const clientPhoneInput = document.getElementById('client_phone');
        const resultsContainer = document.getElementById('clientResults');
        let searchTimeout;

        // Determine the sales_person to send
        function getSalesPersonId() {
            <?php if ($role === 'cashier'): ?>
                const select = document.getElementById('salesperson_id');
                return select ? select.value : '';
            <?php else: ?>
                return <?= $user_id ?>;
            <?php endif; ?>
        }

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                if (query.length < 2) {
                    resultsContainer.style.display = 'none';
                    return;
                }
                const salesPerson = getSalesPersonId();
                if (!salesPerson) {
                    // If no salesperson selected (cashier), don't search
                    resultsContainer.style.display = 'none';
                    return;
                }
                searchTimeout = setTimeout(() => {
                    fetch(`search_clients.php?q=${encodeURIComponent(query)}&sales_person=${encodeURIComponent(salesPerson)}`)
                        .then(response => response.json())
                        .then(data => {
                            resultsContainer.innerHTML = '';
                            if (data.length === 0) {
                                resultsContainer.innerHTML = '<div class="result-item">No clients found</div>';
                            } else {
                                data.forEach(client => {
                                    const div = document.createElement('div');
                                    div.className = 'result-item';
                                    div.textContent = `${client.client_name} (${client.client_phone || 'No phone'})`;
                                    div.dataset.id = client.id;
                                    div.dataset.name = client.client_name;
                                    div.dataset.phone = client.client_phone || '';
                                    div.addEventListener('click', function() {
                                        searchInput.value = this.dataset.name;
                                        clientIdInput.value = this.dataset.id;
                                        clientNameInput.value = this.dataset.name;
                                        clientPhoneInput.value = this.dataset.phone;
                                        resultsContainer.style.display = 'none';
                                    });
                                    resultsContainer.appendChild(div);
                                });
                            }
                            resultsContainer.style.display = 'block';
                        })
                        .catch(err => {
                            console.error('Search error:', err);
                            resultsContainer.style.display = 'none';
                        });
                }, 300);
            });

            // Hide results when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#client_search') && !e.target.closest('#clientResults')) {
                    resultsContainer.style.display = 'none';
                }
            });

            // When user manually types name, clear the hidden client_id
            clientNameInput.addEventListener('input', function() {
                if (this.value !== searchInput.value) {
                    clientIdInput.value = '';
                }
            });

            // For cashier: re-trigger search when salesperson selection changes
            <?php if ($role === 'cashier'): ?>
                document.getElementById('salesperson_id').addEventListener('change', function() {
                    if (searchInput.value.length >= 2) {
                        searchInput.dispatchEvent(new Event('input'));
                    }
                });
            <?php endif; ?>
        }
    <?php endif; ?>
</script>

</body>
</html>