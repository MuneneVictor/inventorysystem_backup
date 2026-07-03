<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";


$serial = $_GET['serial'] ?? '';
$model  = $_GET['model'] ?? '';
$smartboard = null;

$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED!");
}

if ($serial || $model) {
    // Base query
    $sql = "
        SELECT s.*, 
               u_added.full_name AS added_by_name,
               u_sold.full_name AS sold_by_name
        FROM smartboards s
        LEFT JOIN users u_added ON s.added_by = u_added.id
        LEFT JOIN users u_sold ON s.sold_by = u_sold.id
        WHERE 1
    ";
    $params = [];
    $conditions = [];

    if (!empty($serial)) {
        $conditions[] = "s.serial_number LIKE :sn";
        $params[':sn'] = '%' . $serial . '%';
    }

    if (!empty($model)) {
        $conditions[] = "s.model LIKE :model";
        $params[':model'] = '%' . $model . '%';
    }

    if (count($conditions) > 0) {
        $sql .= " AND (" . implode(" OR ", $conditions) . ")";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $smartboard = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Search Smartboard | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ===== EXACT SAME CSS AS SEARCH.PHP ===== */
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
            overflow-x: hidden;
        }

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

        .search-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .search-input-group {
            flex: 1;
            min-width: 200px;
        }

        .search-input-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .search-input-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            background: white;
        }

        .search-input-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
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

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .result-card {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .card-header {
            background: var(--gray-50);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .card-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header h3 i {
            color: var(--primary);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-instock {
            background: #d1fae5;
            color: #065f46;
        }

        .status-sold {
            background: #fee2e2;
            color: #991b1b;
        }

        .card-body {
            padding: 1.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .info-item {
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--gray-800);
            word-break: break-word;
        }

        .info-value code {
            background: white;
            padding: 0.2rem 0.4rem;
            border-radius: var(--radius-sm);
            font-family: monospace;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
            .page-header h1 {
                font-size: 1.25rem;
            }
            .page-header {
                padding: 1rem 1.25rem;
            }
            .search-section {
                padding: 1rem;
            }
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            .search-input-group {
                min-width: 100%;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem 0.5rem 0.5rem !important;
                padding-top: 4rem !important;
            }
            .page-header h1 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1>
            <i class="fas fa-search"></i>
            Search Smartboard
        </h1>
        <div class="breadcrumb">
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Search Smartboard</span>
        </div>
    </div>

    <div class="search-section">
        <div class="search-title">
            <i class="fas fa-qrcode"></i> Search by Serial Number or Model
        </div>
        <form method="GET" class="search-form">
            <div class="search-input-group">
                <label>Serial Number</label>
                <input type="text" 
                       name="serial" 
                       id="serial_number"
                       placeholder="Scan or type serial..." 
                       value="<?= htmlspecialchars($serial) ?>">
            </div>
            <div class="search-input-group">
                <label>Model</label>
                <input type="text" 
                       name="model" 
                       id="model_name"
                       placeholder="Search by model..." 
                       value="<?= htmlspecialchars($model) ?>">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
    </div>

    <?php if (($serial || $model) && !$smartboard): ?>
        <div class="empty-state">
            <i class="fas fa-chalkboard"></i>
            <p>No smartboard found matching your search.</p>
            <p style="margin-top: 0.5rem; font-size: 0.85rem;">Try adjusting your criteria.</p>
        </div>
    <?php elseif ($smartboard): ?>
        
        <div class="result-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-chalkboard"></i>
                    Smartboard Information
                </h3>
                <span class="status-badge <?= $smartboard['status'] == 'instock' ? 'status-instock' : 'status-sold' ?>">
                    <i class="fas <?= $smartboard['status'] == 'instock' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                    <?= ucfirst($smartboard['status']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Serial Number</div>
                        <div class="info-value"><code><?= htmlspecialchars($smartboard['serial_number']) ?></code></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Model</div>
                        <div class="info-value"><strong><?= htmlspecialchars($smartboard['model']) ?></strong></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Size</div>
                        <div class="info-value"><?= (int)$smartboard['size_inches'] ?> inches</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Place</div>
                        <div class="info-value"><?= htmlspecialchars($smartboard['place']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Branch</div>
                        <div class="info-value">
                            <span style="color: <?= $smartboard['branch'] == 'KIMATHI' ? '#059669' : '#3b82f6' ?>">
                                <i class="fas <?= $smartboard['branch'] == 'KIMATHI' ? 'fa-building' : 'fa-store' ?>"></i>
                                <?= htmlspecialchars($smartboard['branch']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Purchase Price</div>
                        <div class="info-value"><?= $smartboard['price'] !== null ? 'KES '.number_format($smartboard['price'],2) : 'Not set' ?></div>
                    </div>
                    <?php if ($smartboard['status'] === 'sold'): ?>
                    <div class="info-item">
                        <div class="info-label">Selling Price</div>
                        <div class="info-value" style="color:#059669; font-weight:600;">KES <?= number_format($smartboard['selling_price'],2) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Sold By</div>
                        <div class="info-value"><?= htmlspecialchars($smartboard['sold_by_name'] ?? 'Unknown') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Sold At</div>
                        <div class="info-value"><?= date('M j, Y H:i', strtotime($smartboard['sold_at'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <div class="info-label">Added By</div>
                        <div class="info-value"><?= htmlspecialchars($smartboard['added_by_name'] ?? 'Unknown') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date Added</div>
                        <div class="info-value"><?= date('M j, Y H:i', strtotime($smartboard['date_added'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 0.5rem;">
            <a href="view_smartboard.php?sn=<?= urlencode($smartboard['serial_number']) ?>" class="btn btn-primary">
                <i class="fas fa-eye"></i> View Full Details
            </a>
            <?php if (in_array($role, ['super_admin', 'inventory_admin', 'manager'])): ?>
                <a href="edit_smartboard.php?sn=<?= urlencode($smartboard['serial_number']) ?>" class="btn btn-secondary">
                    <i class="fas fa-edit"></i> Edit Smartboard
                </a>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('#serial_number, #model_name');
    inputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.form.submit();
            }
        });
    });

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