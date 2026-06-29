<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Restrict access: sales, cashier, manager, super_admin
if (!in_array($_SESSION['role'], ['sales', 'cashier', 'manager', 'super_admin'])) {
    die("ACCESS DENIED.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Get user's branch (for cashier)
$user_branch = null;
if ($user_role === 'cashier') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
    if (!$user_branch) {
        die("Your account has no branch assigned.");
    }
}

// Search/filter parameters
$search = trim($_GET['search'] ?? '');
$filter_branch = $_GET['filter_branch'] ?? '';
$filter_salesperson = $_GET['filter_salesperson'] ?? '';

// Build query
$sql = "SELECT c.*, u.full_name AS salesperson_name 
        FROM clients c
        LEFT JOIN users u ON c.sales_person = u.id
        WHERE 1=1";
$params = [];

// Role-based filtering
if ($user_role === 'sales') {
    $sql .= " AND c.sales_person = ?";
    $params[] = $user_id;
} elseif ($user_role === 'cashier') {
    $sql .= " AND c.branch = ?";
    $params[] = $user_branch;
}

// Additional filters (for manager/super_admin)
if ($user_role === 'manager' || $user_role === 'super_admin') {
    if (!empty($filter_branch)) {
        $sql .= " AND c.branch = ?";
        $params[] = $filter_branch;
    }
    if (!empty($filter_salesperson)) {
        $sql .= " AND c.sales_person = ?";
        $params[] = $filter_salesperson;
    }
}

// Search by name, phone, email
if (!empty($search)) {
    $sql .= " AND (c.client_name LIKE ? OR c.client_phone LIKE ? OR c.client_email LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY c.client_name ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For manager/super_admin, fetch lists for dropdown filters
$branches = [];
$salespersons = [];
if ($user_role === 'manager' || $user_role === 'super_admin') {
    $stmt = $conn->query("SELECT DISTINCT branch FROM clients WHERE branch IS NOT NULL ORDER BY branch");
    $branches = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $conn->query("SELECT id, full_name FROM users WHERE role = 'sales' ORDER BY full_name");
    $salespersons = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>View Clients | Mombasa Computers</title>
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
        .filter-section { background: white; padding: 1rem 1.25rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; border: 1px solid var(--gray-200); }
        .filter-grid { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; min-width: 150px; }
        .filter-group label { font-size: 0.8rem; font-weight: 600; color: var(--gray-600); }
        .filter-group input, .filter-group select { padding: 0.4rem 0.6rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.85rem; width: 100%; }
        .btn { padding: 0.4rem 1rem; border: none; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); transform: translateY(-2px); }
        .btn-secondary { background: #2563eb; color: white; }
        .btn-secondary:hover { background: #1d4ed8; transform: translateY(-2px); }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; box-shadow: var(--shadow-sm); }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; min-width: 800px; }
        th { background: var(--gray-50); padding: 0.875rem 0.75rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); }
        td { padding: 0.75rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        tr:hover { background: var(--gray-50); }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 500; background: var(--gray-100); }
        .empty-state { text-align: center; padding: 2.5rem; color: var(--gray-500); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        .actions-row { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem; }
        footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1rem 1.25rem; }
            .filter-grid { flex-direction: column; }
            .filter-group { min-width: 100%; }
            table { font-size: 0.8rem; min-width: 600px; }
            th, td { padding: 0.5rem; }
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> View Clients</h1>
        <div class="breadcrumb">
            <a href="/inventory_system/dashboard/<?= $user_role === 'cashier' ? 'cashierdashboard.php' : ($user_role === 'sales' ? 'salesdashboard.php' : 'superadmindashboard.php') ?>">Dashboard</a>
            <span> / </span>
            <span>Clients</span>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" placeholder="Name, phone, email" value="<?= htmlspecialchars($search) ?>">
            </div>
            <?php if ($user_role === 'manager' || $user_role === 'super_admin'): ?>
                <div class="filter-group">
                    <label for="filter_branch">Branch</label>
                    <select name="filter_branch" id="filter_branch">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= htmlspecialchars($br) ?>" <?= $filter_branch == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_salesperson">Salesperson</label>
                    <select name="filter_salesperson" id="filter_salesperson">
                        <option value="">All Salespersons</option>
                        <?php foreach ($salespersons as $sp): ?>
                            <option value="<?= $sp['id'] ?>" <?= $filter_salesperson == $sp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sp['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="filter-group" style="flex-direction: row; align-items: flex-end; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                <a href="view_clients.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Actions -->
    <div class="actions-row">
        <a href="add_client.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add Client</a>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
        <?php if (empty($clients)): ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <p>No clients found matching your criteria.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>P.O. Box</th>
                        <?php if ($role === 'super_admin' || $role === 'manager'): ?>
                            <th>Salesperson</th>
                        <?php endif; ?>
                        <th>Branch</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($clients as $client): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($client['client_name']) ?></strong></td>
                            <td><?= htmlspecialchars($client['client_phone'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($client['client_email'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($client['client_box'] ?? '—') ?></td>
                            <?php if ($role === 'super_admin' || $role === 'manager'): ?>
                                <td><?= htmlspecialchars($client['salesperson_name'] ?? 'Unassigned') ?></td>
                            <?php endif; ?>
                            <td><span class="badge"><?= htmlspecialchars($client['branch'] ?? '—') ?></span></td>
                            <td><?= date('M j, Y g:i A', strtotime($client['date_added'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <footer>
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </footer>
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