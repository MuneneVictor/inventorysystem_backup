<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid accessory ID.");
}

$id = (int)$_GET['id'];
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Fetch accessory details with added_by and updated_by names
$sql = "SELECT a.*, 
               u1.full_name AS added_by_name,
               u2.full_name AS updated_by_name
        FROM accessories a
        LEFT JOIN users u1 ON a.added_by = u1.id
        LEFT JOIN users u2 ON a.updated_by = u2.id
        WHERE a.id = :id";
$stmt = $conn->prepare($sql);
$stmt->execute(['id' => $id]);
$accessory = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$accessory) {
    die("Accessory not found.");
}

// Compute total price (if price is set)
$total_price = $accessory['price'] !== null ? $accessory['quantity'] * $accessory['price'] : null;

// Determine back URL (use referer or fallback)
$back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'accessory_instock.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>View Accessory | <?= htmlspecialchars($accessory['name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ===== Same CSS as view_device.php (unchanged) ===== */
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
            flex-wrap: wrap;
        }

        .page-header h1 i {
            color: var(--primary);
            font-size: 1.75rem;
        }

        .page-header h1 .accessory-name {
            background: var(--gray-100);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 1rem;
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
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .info-item {
            padding: 0.875rem;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            transition: all 0.2s ease;
        }

        .info-item:hover {
            background: white;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .info-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--gray-800);
            word-break: break-word;
        }

        .info-value .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .branch-kimathi { color: #059669; font-weight: 600; }
        .branch-moi { color: #3b82f6; font-weight: 600; }
        .price { color: #059669; font-weight: 600; }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
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

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
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

            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .card-body {
                padding: 1.25rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
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

            .card-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
 <?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-plug"></i>
            Accessory Details
            <span class="accessory-name"><?= htmlspecialchars($accessory['name']) ?></span>
        </h1>
        <div class="breadcrumb">
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <a href="../dashboard/managerdashboard"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="../dashboard/inventorydashboard"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'sales'): ?>
                <a href="../dashboard/salesdashboard"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="<?= htmlspecialchars($back_url) ?>">Accessories</a>
            <span> / </span>
            <span>View Accessory</span>
        </div>
    </div>

    <!-- Accessory Details Card -->
    <div class="result-card">
        <div class="card-header">
            <h3>
                <i class="fas fa-info-circle"></i>
                Accessory Information
            </h3>
            <span class="status-badge <?= $accessory['status'] == 'instock' ? 'status-instock' : 'status-sold' ?>">
                <i class="fas <?= $accessory['status'] == 'instock' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                <?= htmlspecialchars(ucfirst($accessory['status'])) ?>
            </span>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Accessory Name</div>
                    <div class="info-value"><strong><?= htmlspecialchars($accessory['name']) ?></strong></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Quantity</div>
                    <div class="info-value"><span class="badge"><?= (int)$accessory['quantity'] ?></span></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Branch</div>
                    <div class="info-value <?= $accessory['branch'] == 'KIMATHI' ? 'branch-kimathi' : 'branch-moi' ?>">
                        <i class="fas <?= $accessory['branch'] == 'KIMATHI' ? 'fa-building' : 'fa-store' ?>"></i>
                        <?= htmlspecialchars($accessory['branch']) ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Place</div>
                    <div class="info-value"><span class="badge"><?= htmlspecialchars(ucfirst($accessory['place'])) ?></span></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Unit Price (KES)</div>
                    <div class="info-value price"><?= $accessory['price'] !== null ? 'KES '.number_format($accessory['price'], 2) : '-' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Total Value (KES)</div>
                    <div class="info-value price"><?= $total_price !== null ? 'KES '.number_format($total_price, 2) : '-' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date Added</div>
                    <div class="info-value"><?= date('M j, Y H:i', strtotime($accessory['date_added'])) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Added By</div>
                    <div class="info-value"><?= htmlspecialchars($accessory['added_by_name'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Last Updated</div>
                    <div class="info-value">
                        <?php if (!empty($accessory['updated_at'])): ?>
                            <?= date('M j, Y H:i', strtotime($accessory['updated_at'])) ?>
                        <?php else: ?>
                            Not updated yet
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Updated By</div>
                    <div class="info-value">
                        <?= !empty($accessory['updated_by_name']) ? htmlspecialchars($accessory['updated_by_name']) : 'Not updated yet' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons (only Back) -->
    <div class="action-buttons">
        <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Accessories
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