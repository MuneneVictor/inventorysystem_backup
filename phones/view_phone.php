<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

if (!isset($_GET['sn'])) {
    die("Serial number not provided!");
}

$serial_number = trim($_GET['sn']);

// Fetch phone details with added_by and sold_by names
$stmt = $conn->prepare("
    SELECT p.*, 
           u_added.full_name AS added_by_name,
           u_sold.full_name AS sold_by_name
    FROM phones p
    LEFT JOIN users u_added ON p.added_by = u_added.id
    LEFT JOIN users u_sold ON p.sold_by = u_sold.id
    WHERE p.serial_number = :sn
");
$stmt->execute(['sn' => $serial_number]);
$phone = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$phone) {
    die("Phone not found!");
}

// Prepare sold info if status is 'sold'
$sold_info = null;
if ($phone['status'] === 'sold') {
    $sold_info = [
        'sold_by_name' => $phone['sold_by_name'] ?? 'Unknown',
        'sold_at'      => $phone['date_sold'],
        'selling_price' => $phone['selling_price'],
        'customer_name' => 'Walk-in Customer'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>View Phone | <?= htmlspecialchars($phone['serial_number']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ===== SAME CSS AS view_device.php ===== */
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
            flex-wrap: wrap;
        }

        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }
        .page-header h1 .serial-code {
            font-family: 'Courier New', monospace;
            background: var(--gray-100);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 1rem;
        }

        .breadcrumb {
            color: var(--gray-500);
            font-size: 0.9rem;
        }
        .breadcrumb a { color: var(--primary); text-decoration: none; }

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
        .card-header h3 i { color: var(--primary); }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-instock { background: #d1fae5; color: #065f46; }
        .status-sold { background: #fee2e2; color: #991b1b; }

        .card-body { padding: 1.5rem; }

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

        .info-value code {
            background: white;
            padding: 0.2rem 0.4rem;
            border-radius: var(--radius-sm);
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
        }

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

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-300); }
        .btn-secondary:hover { background: var(--gray-200); }

        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }

        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .card-body { padding: 1.25rem; }
            .info-grid { grid-template-columns: 1fr; gap: 0.75rem; }
            .action-buttons { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header h1 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-mobile-alt"></i>
            Phone Details
            <span class="serial-code"><?= htmlspecialchars($phone['serial_number']) ?></span>
        </h1>
        <div class="breadcrumb">
            <?php if ($role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($role === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($role === 'inventory_admin'): ?>
                <a href="../dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="phones_instock.php">Phones</a>
            <span> / </span>
            <span>View Phone</span>
        </div>
    </div>

    <!-- Phone Details Card -->
    <div class="result-card">
        <div class="card-header">
            <h3>
                <i class="fas fa-info-circle"></i>
                Phone Information
            </h3>
            <span class="status-badge <?= $phone['status'] == 'instock' ? 'status-instock' : 'status-sold' ?>">
                <i class="fas <?= $phone['status'] == 'instock' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                <?= $phone['status'] == 'instock' ? 'In Stock' : 'Sold' ?>
            </span>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Serial Number</div>
                    <div class="info-value"><code><?= htmlspecialchars($phone['serial_number']) ?></code></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Brand</div>
                    <div class="info-value"><strong><?= htmlspecialchars($phone['brand']) ?></strong></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Model</div>
                    <div class="info-value"><?= htmlspecialchars($phone['model']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">RAM</div>
                    <div class="info-value"><?= (int)$phone['ram'] ?> GB</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Storage</div>
                    <div class="info-value"><?= (int)$phone['storage_capacity'] ?> GB</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Branch</div>
                    <div class="info-value">
                        <span style="color: <?= $phone['branch'] == 'KIMATHI' ? '#059669' : '#3b82f6' ?>">
                            <i class="fas <?= $phone['branch'] == 'KIMATHI' ? 'fa-building' : 'fa-store' ?>"></i>
                            <?= htmlspecialchars($phone['branch']) ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Condition</div>
                    <div class="info-value"><?= htmlspecialchars($phone['phone_condition'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Price (KES)</div>
                    <div class="info-value">
                        <?= $phone['price'] !== null ? 'KES ' . number_format($phone['price'], 2) : '—' ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Added By</div>
                    <div class="info-value"><?= htmlspecialchars($phone['added_by_name'] ?? 'Unknown') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date Added</div>
                    <div class="info-value"><?= date('M j, Y H:i', strtotime($phone['date_added'])) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sale Information (if sold) -->
    <?php if ($sold_info): ?>
    <div class="result-card">
        <div class="card-header">
            <h3>
                <i class="fas fa-receipt"></i>
                Sale Information
            </h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Sold By</div>
                    <div class="info-value"><?= htmlspecialchars($sold_info['sold_by_name'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Sale Date</div>
                    <div class="info-value"><?= !empty($sold_info['sold_at']) ? date('M j, Y H:i', strtotime($sold_info['sold_at'])) : '-' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Selling Price</div>
                    <div class="info-value"><strong style="color: #059669;"><?= !empty($sold_info['selling_price']) ? 'KES ' . number_format($sold_info['selling_price'], 2) : '-' ?></strong></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Customer</div>
                    <div class="info-value"><?= htmlspecialchars($sold_info['customer_name'] ?? 'Walk-in Customer') ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="phones_instock.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Stock
        </a>
        <?php if ($role == 'super_admin' || $role == 'inventory_admin'): ?>
            <a href="edit_phone.php?sn=<?= urlencode($phone['serial_number']) ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit Phone
            </a>
        <?php endif; ?>
        <?php if ($phone['status'] == 'sold'): ?>
            <a href="sold_phones.php" class="btn btn-secondary">
                <i class="fas fa-list"></i> View All Sold
            </a>
        <?php endif; ?>
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