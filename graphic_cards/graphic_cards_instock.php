<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Allow super_admin, inventory_admin, manager, and sales
if (!in_array($role, ['super_admin', 'inventory_admin', 'manager', 'sales'])) {
    die("Access denied!");
}

// For managers, restrict to their branch if they have one
$user_branch = '';
if ($role === 'manager') {
    $user_stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_branch = $user_data['branch'] ?? '';
}

// Handle search inputs
$search_type = trim($_GET['type'] ?? '');
$search_storage = trim($_GET['storage'] ?? '');
$search_branch = trim($_GET['branch'] ?? '');

// Build query – only show graphic cards with status = 'instock'
$sql = "SELECT g.*, 
               u.full_name AS added_by_name
        FROM graphic_cards g
        LEFT JOIN users u ON g.added_by = u.id
        WHERE g.status = 'instock'";
$params = [];

// Manager restriction
if ($role === 'manager' && !empty($user_branch)) {
    $sql .= " AND g.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}

// Search filters
if ($search_type) {
    $sql .= " AND g.type LIKE :type";
    $params['type'] = "%$search_type%";
}
if ($search_storage) {
    if (is_numeric($search_storage)) {
        $sql .= " AND g.storage_capacity = :storage";
        $params['storage'] = (int)$search_storage;
    } else {
        $sql .= " AND CAST(g.storage_capacity AS CHAR) LIKE :storage";
        $params['storage'] = "%$search_storage%";
    }
}
if ($search_branch && $role !== 'manager') {
    $sql .= " AND g.branch = :branch";
    $params['branch'] = $search_branch;
}

$sql .= " ORDER BY g.date_added DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_items = count($cards);
$total_quantity = array_sum(array_column($cards, 'quantity'));
$total_value = array_sum(array_column($cards, 'total_price'));
$branches = array_unique(array_column($cards, 'branch'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>In‑Stock Graphic Cards | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Same CSS as before – unchanged except for modal button improvements */
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
            --font-sans: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap');

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

        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }

        .breadcrumb {
            color: var(--gray-500);
            font-size: 0.9rem;
        }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.25rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-card .stat-icon { font-size: 1.5rem; color: var(--primary); margin-bottom: 0.5rem; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 600; color: var(--gray-800); }
        .stat-card .stat-label { font-size: 0.85rem; color: var(--gray-500); margin-top: 0.25rem; }

        .search-section {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .search-title {
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .search-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .search-group label { font-size: 0.85rem; font-weight: 500; color: var(--gray-600); }
        .search-group input, .search-group select {
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            background: white;
        }
        .search-group input:focus, .search-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26,75,42,0.1);
        }

        .search-actions {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--font-sans);
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); transform: translateY(-2px); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-300); }
        .btn-secondary:hover { background: var(--gray-200); }
        .btn-excel { background: #217346; color: white; }
        .btn-excel:hover { background: #1a5e33; }
        .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.75rem; }
        .btn-success { background: #059669; color: white; }
        .btn-success:hover { background: #047857; transform: translateY(-2px); }

        .table-wrapper {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            min-width: 700px;
        }

        th {
            background: var(--gray-50);
            padding: 1rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-600);
            font-size: 0.85rem;
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }

        td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            vertical-align: middle;
        }

        tr:hover { background: var(--gray-50); }
        tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .branch-kimathi { color: #059669; font-weight: 500; }
        .branch-moi { color: #3b82f6; font-weight: 500; }

        .price {
            font-weight: 600;
            color: #059669;
        }

        .action-links {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .action-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            cursor: pointer;
            border: none;
            background: none;
            padding: 0;
        }
        .action-link:hover { text-decoration: underline; }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }

        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            max-width: 450px;
            width: 95%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-box h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .modal-box h3 i { color: var(--primary); }
        .modal-box .modal-sub {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin-bottom: 1.5rem;
        }
        .modal-box .form-group {
            margin-bottom: 1.25rem;
        }
        .modal-box .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        .modal-box .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }
        .modal-box .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26,75,42,0.1);
        }
        .modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        .modal-actions .btn {
            padding: 0.5rem 1.25rem;
            min-width: 80px;
            justify-content: center;
        }
        .modal-actions .btn-secondary {
            background: var(--gray-200);
            border: none;
        }
        .modal-actions .btn-secondary:hover {
            background: var(--gray-300);
        }
        .modal-actions .btn-primary {
            background: var(--primary);
            color: white;
        }
        .modal-actions .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
        }
        .modal-actions .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1rem 1.25rem; }
            .stats-row { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .stat-card { padding: 1rem; }
            .stat-card .stat-value { font-size: 1.5rem; }
            .search-section { padding: 1rem; }
            .search-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; justify-content: center; }
            .action-links { flex-direction: column; }
            .table { min-width: 600px; }
            .modal-box { padding: 1.5rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .stats-row { grid-template-columns: 1fr; }
            .page-header h1 { font-size: 1.1rem; }
            .table { min-width: 500px; }
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-microchip"></i> In‑Stock Graphic Cards</h1>
        <div class="breadcrumb">
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'sales'): ?>
                <a href="/inventory_system/dashboard/salesdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>In‑Stock Graphic Cards</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-boxes"></i></div>
            <div class="stat-value"><?= number_format($total_items) ?></div>
            <div class="stat-label">Total Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-cubes"></i></div>
            <div class="stat-value"><?= number_format($total_quantity) ?></div>
            <div class="stat-label">Total Units</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-store"></i></div>
            <div class="stat-value"><?= number_format(count($branches)) ?></div>
            <div class="stat-label">Branches</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-value">KES <?= number_format($total_value, 0) ?></div>
            <div class="stat-label">Total Value</div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-title"><i class="fas fa-filter"></i> Filter Graphic Cards</div>
        <form method="GET" class="search-grid">
            <div class="search-group">
                <label>Type</label>
                <input type="text" name="type" placeholder="e.g., NVIDIA" value="<?= htmlspecialchars($search_type) ?>">
            </div>
            <div class="search-group">
                <label>Storage (GB)</label>
                <input type="text" name="storage" placeholder="e.g., 4, 8, 16" value="<?= htmlspecialchars($search_storage) ?>">
            </div>
            <?php if ($role !== 'manager'): ?>
            <div class="search-group">
                <label>Branch</label>
                <select name="branch">
                    <option value="">-- All Branches --</option>
                    <option value="KIMATHI" <?= $search_branch == 'KIMATHI' ? 'selected' : '' ?>>KIMATHI</option>
                    <option value="MOI" <?= $search_branch == 'MOI' ? 'selected' : '' ?>>MOI</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="search-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                <a href="graphic_cards_instock.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                <?php if (!empty($cards)): ?>
                    <a href="export_graphic_cards_excel.php?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn btn-excel"><i class="fas fa-file-excel"></i> Export to Excel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Graphic Cards Table -->
    <div class="table-wrapper">
        <div class="table-responsive">
            <?php if (empty($cards)): ?>
                <div class="empty-state">
                    <i class="fas fa-microchip"></i>
                    <p>No graphic cards found matching your criteria.</p>
                    <a href="graphic_cards_instock.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-undo"></i> Clear Filters
                    </a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Storage (GB)</th>
                            <th>Quantity</th>
                            <th>Branch</th>
                            <th>Price (KES)</th>
                            <th>Total Value (KES)</th>
                            <th>Added By</th>
                            <th>Date Added</th>
                            <?php if (in_array($role, ['super_admin', 'inventory_admin', 'manager'])): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($cards as $c): ?>
                            <tr id="card-row-<?= $c['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($c['type']) ?></strong></td>
                                <td><?= (int)$c['storage_capacity'] ?> GB</td>
                                <td><span class="badge"><?= (int)$c['quantity'] ?></span></td>
                                <td>
                                    <span class="<?= $c['branch'] == 'KIMATHI' ? 'branch-kimathi' : 'branch-moi' ?>">
                                        <?= htmlspecialchars($c['branch']) ?>
                                    </span>
                                </td>
                                <td class="price" id="price-<?= $c['id'] ?>">
                                    <?= $c['price'] !== null ? 'KES '.number_format($c['price'], 2) : '-' ?>
                                </td>
                                <td class="price" id="total-<?= $c['id'] ?>">
                                    <?= $c['total_price'] !== null ? 'KES '.number_format($c['total_price'], 2) : '-' ?>
                                </td>
                                <td><?= htmlspecialchars($c['added_by_name'] ?? 'N/A') ?></td>
                                <td><small><?= date('M j, Y g:i A', strtotime($c['date_added'])) ?></small></td>
                                <?php if (in_array($role, ['super_admin', 'inventory_admin', 'manager'])): ?>
                                    <td>
                                        <td>
                                            <div class="action-links">
                                                <?php if ($c['price'] === null): ?>
                                                    <a href="add_price_graphic_card.php?id=<?= urlencode($c['id']) ?>" class="action-link">
                                                        <i class="fas fa-tag"></i> Add Price
                                                    </a>
                                                <?php else: ?>
                                                    <a href="update_price_graphic_card.php?id=<?= urlencode($c['id']) ?>" class="action-link">
                                                        <i class="fas fa-edit"></i> Update Price
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // ---- Price Modal ----
    const modal = document.getElementById('priceModal');
    const modalCardId = document.getElementById('modalCardId');
    const modalPrice = document.getElementById('modalPrice');
    const priceForm = document.getElementById('priceForm');

    // Open modal when price button clicked
    document.querySelectorAll('.price-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const currentPrice = this.dataset.price;
            modalCardId.value = id;
            modalPrice.value = currentPrice !== '' && currentPrice !== 'null' ? currentPrice : '';
            modal.classList.add('active');
        });
    });

    // Close modal
    window.closePriceModal = function() {
        modal.classList.remove('active');
        modalPrice.value = '';
    };

    // Close on outside click
    modal.addEventListener('click', function(e) {
        if (e.target === this) closePriceModal();
    });

    // Handle form submit via AJAX
    priceForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const cardId = modalCardId.value;
        const price = modalPrice.value.trim() !== '' ? parseFloat(modalPrice.value) : null;

        if (price !== null && (isNaN(price) || price < 0)) {
            alert('Please enter a valid price (0 or greater).');
            return;
        }

        const submitBtn = document.getElementById('savePriceBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch('update_graphic_card_price.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'card_id=' + encodeURIComponent(cardId) + '&price=' + encodeURIComponent(price !== null ? price : '')
        })
        .then(response => {
            // If the response is not OK, read as text to show the error
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error('Server error: ' + text.substring(0, 200));
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update the row cells
                const priceCell = document.getElementById('price-' + cardId);
                const totalCell = document.getElementById('total-' + cardId);
                if (priceCell) priceCell.textContent = data.formatted_price;
                if (totalCell) totalCell.textContent = data.formatted_total;

                // Update the button text
                const row = document.getElementById('card-row-' + cardId);
                if (row) {
                    const btn = row.querySelector('.price-btn');
                    if (btn) {
                        btn.dataset.price = data.price !== null ? data.price : '';
                        btn.innerHTML = '<i class="fas fa-tag"></i> ' + (data.price !== null ? 'Update Price' : 'Add Price');
                    }
                }

                // Reload page to refresh stats (optional)
                setTimeout(() => location.reload(), 800);
                closePriceModal();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Error updating price: ' + error.message);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Save Price';
        });
    });

    // ---- Mobile responsive adjustments ----
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