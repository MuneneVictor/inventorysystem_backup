<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

$role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Restrict access: cashier, manager, super_admin
if (!in_array($role, ['cashier', 'manager', 'super_admin'])) {
    die("ACCESS DENIED.");
}

// Determine branch filter
$user_branch = null;
if ($role === 'cashier') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
    if (!$user_branch) {
        die("Your account has no branch assigned.");
    }
}

// For salesperson, show only their own sales
$salesperson_filter = ($role === 'sales') ? $user_id : null;

// Build query
$sql = "
    SELECT s.id, s.client_name, s.client_phone, s.total_amount, s.created_at, s.sold_by,
           u.full_name AS salesperson_name, u.branch AS salesperson_branch
    FROM sales s
    LEFT JOIN users u ON s.sold_by = u.id
    WHERE s.sale_status = 'active'
";
$params = [];

if ($role === 'cashier' && $user_branch) {
    $sql .= " AND u.branch = ?";
    $params[] = $user_branch;
} elseif ($role === 'sales' && $salesperson_filter) {
    $sql .= " AND s.sold_by = ?";
    $params[] = $salesperson_filter;
}

$sql .= " ORDER BY s.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$activeSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

date_default_timezone_set('Africa/Nairobi');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Active Sales | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1a4b2a;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
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
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; box-shadow: var(--shadow-sm); }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; min-width: 800px; }
        th { background: var(--gray-50); padding: 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); }
        td { padding: 0.9rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: var(--gray-100); }
        .badge-active { background: #dbeafe; color: #1e40af; }
        .btn { padding: 0.4rem 0.8rem; border: none; border-radius: var(--radius-md); font-size: 0.8rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #2a6b3a; }
        .btn-success { background: #059669; color: white; }
        .btn-success:hover { background: #047857; }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .text-muted { color: var(--gray-500); }
        .actions { margin-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }

        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1rem 1.25rem; }
            table { font-size: 0.8rem; min-width: 600px; }
            th, td { padding: 0.6rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header h1 { font-size: 1.1rem; }
            table { min-width: 500px; }
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="page-header">
        <h1>
            <i class="fas fa-receipt"></i>
            Active Sales
        </h1>
        <div class="breadcrumb">
            <a href="<?= $role === 'cashier' ? '/inventory_system/dashboard/cashierdashboard.php' : '/inventory_system/dashboard/salesdashboard.php' ?>">Dashboard</a>
            <span> / </span>
            <span>Active Sales</span>
        </div>
        <?php if ($role === 'cashier' && $user_branch): ?>
            <div style="margin-top:0.5rem; font-size:0.9rem; color:var(--gray-600);">
                <i class="fas fa-store"></i> Branch: <?= htmlspecialchars($user_branch) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-wrapper">
        <?php if (empty($activeSales)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <p>No active sales found.</p>
                <?php if ($role === 'cashier' && $user_branch): ?>
                    <p class="text-muted" style="margin-top:0.5rem;">There are no active sales in your branch.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Phone</th>
                        <th>Amount (KES)</th>
                        <th>Sales Person</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($activeSales as $sale): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($sale['client_name'] ?? '—') ?></strong></td>
                            <td><?= htmlspecialchars($sale['client_phone'] ?? '—') ?></td>
                            <td><?= $sale['total_amount'] !== null ? number_format($sale['total_amount'], 2) : '—' ?></td>
                            <td><?= htmlspecialchars($sale['salesperson_name'] ?? 'Unknown') ?></td>
                            <td><?= date('M j, Y H:i', strtotime($sale['created_at'])) ?></td>
                            <td><span class="badge badge-active"><i class="fas fa-circle" style="color:#2563eb; font-size:0.5rem; margin-right:0.25rem;"></i> Active</span></td>
                            <td>
                                <a href="checkout.php?sale_id=<?= $sale['id'] ?>" class="btn btn-success">
                                    <i class="fas fa-arrow-right"></i> Checkout
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="actions">
        <a href="make_sale.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i> New Sale</a>
        <?php if ($role === 'cashier'): ?>
            <a href="../dashboard/cashierdashboard.php" class="btn btn-primary" style="background:#2563eb;">Back to Dashboard</a>
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
<?php require_once "../includes/footer.php"; ?>
</body>
</html>