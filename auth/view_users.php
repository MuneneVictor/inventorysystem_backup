<?php
session_start();

// ========== HANDLE TOGGLE USER STATUS (Restrict/Activate) ==========
// Must be at the very top, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id']) && isset($_POST['toggle_action'])) {
    require_once "../config/db.php";
    
    $toggle_id = (int)$_POST['toggle_user_id'];
    $toggle_action = $_POST['toggle_action'];
    
    // Prevent self toggle
    if ($toggle_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot change your own status.";
        header("Location: view_users.php");
        exit();
    }
    
    $new_status = ($toggle_action === 'activate') ? 1 : 0;
    
    try {
        $stmt = $conn->prepare("UPDATE users SET is_active = :status WHERE id = :id");
        $stmt->execute(['status' => $new_status, 'id' => $toggle_id]);
        
        // Log activity
        $action_text = ($new_status == 1) ? "activated" : "restricted";
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (:uid, :action, :details)");
        $logStmt->execute([
            'uid' => $_SESSION['user_id'],
            'action' => 'User Status Change',
            'details' => "User ID $toggle_id has been $action_text by " . ($_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Admin')
        ]);
        
        $_SESSION['success'] = "User status updated successfully.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    
    // Preserve filters when redirecting
    $query_params = [];
    if (!empty($_GET['branch'])) $query_params['branch'] = $_GET['branch'];
    if (!empty($_GET['role'])) $query_params['role'] = $_GET['role'];
    if (!empty($_GET['status'])) $query_params['status'] = $_GET['status'];
    $redirect_url = "view_users.php" . (empty($query_params) ? "" : "?" . http_build_query($query_params));
    
    header("Location: " . $redirect_url);
    exit();
}

// ========== NORMAL PAGE LOAD ==========
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";

$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Only super_admin can access user management
if ($role !== 'super_admin') {
    die("Access denied! Only Super Administrators can view users.");
}

// Get filters from GET
$filterBranch = $_GET['branch'] ?? '';
$filterRole = $_GET['role'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Helper function to safely prepare & fetch
function safeQuery($conn, $sql, $params = []) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Build dynamic query with prepared statements
$sql = "SELECT id, full_name, role, email, branch, created_at, last_login, is_active 
        FROM users WHERE 1=1";

$params = [];

if (!empty($filterBranch)) {
    $sql .= " AND branch = :branch";
    $params['branch'] = $filterBranch;
}

if (!empty($filterRole)) {
    $sql .= " AND role = :role";
    $params['role'] = $filterRole;
}

if ($filterStatus !== '') {
    $sql .= " AND is_active = :status";
    $params['status'] = $filterStatus;
}

$sql .= " ORDER BY created_at DESC";

$users = safeQuery($conn, $sql, $params);

// Get distinct branches and roles for filter dropdowns
$branches = safeQuery($conn, "SELECT DISTINCT branch FROM users WHERE branch IS NOT NULL ORDER BY branch ASC");
$roles = safeQuery($conn, "SELECT DISTINCT role FROM users ORDER BY role ASC");

// Get counts for stats
$total_users = count($users);
$active_users = count(array_filter($users, fn($u) => $u['is_active'] == 1));
$inactive_users = $total_users - $active_users;

// Display success/error messages (from session)
$success_msg = $_SESSION['success'] ?? '';
$error_msg = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
require_once "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>View Users | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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

        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

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

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-card .stat-icon {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .stat-card .stat-label {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
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

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-600);
        }

        .filter-group select {
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            background: white;
            font-family: var(--font-sans);
            cursor: pointer;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

        .filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
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
            white-space: nowrap;
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

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-sm {
            padding: 0.375rem 0.875rem;
            font-size: 0.8rem;
        }

        .table-wrapper {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

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

        tr:hover {
            background: var(--gray-50);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-admin {
            background: #dc2626;
            color: white;
        }

        .badge-inventory {
            background: #059669;
            color: white;
        }

        .badge-sales {
            background: #3b82f6;
            color: white;
        }

        .badge-technician {
            background: #f59e0b;
            color: white;
        }

        .badge-software {
            background: #8b5cf6;
            color: white;
        }

        .badge-manager {
            background: #1e40af;
            color: white;
        }

        .badge-active {
            background: #10b981;
            color: white;
        }

        .badge-inactive {
            background: #ef4444;
            color: white;
        }

        .branch-kimathi {
            color: #059669;
            font-weight: 500;
        }

        .branch-moi {
            color: #3b82f6;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
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

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-card .stat-value {
                font-size: 1.5rem;
            }

            .search-section {
                padding: 1rem;
            }

            .filter-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .filter-actions {
                flex-direction: column;
                width: 100%;
            }

            .filter-actions .btn {
                width: 100%;
                justify-content: center;
                white-space: normal;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-sm {
                width: 100%;
                text-align: center;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem 0.5rem 0.5rem !important;
                padding-top: 4rem !important;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1>
            <i class="fas fa-users"></i>
            User Management
        </h1>
        <div class="breadcrumb">
            <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span> / </span>
            <span>View Users</span>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($success_msg) ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($error_msg): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($error_msg) ?></span>
        </div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?= number_format($total_users) ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?= number_format($active_users) ?></div>
            <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-value"><?= number_format($inactive_users) ?></div>
            <div class="stat-label">Inactive Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-store"></i></div>
            <div class="stat-value"><?= number_format(count($branches)) ?></div>
            <div class="stat-label">Branches</div>
        </div>
    </div>

    <div class="search-section">
        <div class="search-title">
            <i class="fas fa-filter"></i> Filter Users
        </div>
        <form method="GET" id="filterForm" class="filter-grid">
            <div class="filter-group">
                <label><i class="fas fa-store"></i> Branch</label>
                <select name="branch" id="filter-branch">
                    <option value="">-- All Branches --</option>
                    <?php foreach($branches as $b): ?>
                        <option value="<?= htmlspecialchars($b['branch']) ?>" <?= ($filterBranch == $b['branch']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['branch']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label><i class="fas fa-user-tag"></i> Role</label>
                <select name="role" id="filter-role">
                    <option value="">-- All Roles --</option>
                    <?php foreach($roles as $r): ?>
                        <option value="<?= htmlspecialchars($r['role']) ?>" <?= ($filterRole == $r['role']) ? 'selected' : '' ?>>
                            <?php 
                                $display = ucfirst(str_replace('_', ' ', $r['role']));
                                if ($r['role'] === 'maintenance') $display = 'Software';
                                if ($r['role'] === 'software') $display = 'Software';
                                if ($r['role'] === 'inventory_admin') $display = 'Inventory Admin';
                                echo htmlspecialchars($display);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label><i class="fas fa-circle"></i> Status</label>
                <select name="status" id="filter-status">
                    <option value="">-- All Status --</option>
                    <option value="1" <?= ($filterStatus === '1') ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= ($filterStatus === '0') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="view_users.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </a>
                <a href="generate_code.php" class="btn btn-secondary">
                    <i class="fas fa-key"></i> Generate Code
                </a>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <div class="table-responsive">
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <p>No users found matching your criteria.</p>
                    <a href="view_users.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-undo"></i> Clear Filters
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" id="toggleForm" style="display: none;">
                    <input type="hidden" name="toggle_user_id" id="toggle_user_id">
                    <input type="hidden" name="toggle_action" id="toggle_action">
                </form>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th>Date Created</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i = 1; foreach($users as $u): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($u['full_name'] ?? 'N/A') ?></strong></td>
                            <td>
                                <?php
                                $role_class = '';
                                $role_display = '';
                                switch($u['role']) {
                                    case 'super_admin':
                                        $role_class = 'badge-admin';
                                        $role_display = 'Super Admin';
                                        break;
                                    case 'inventory_admin':
                                        $role_class = 'badge-inventory';
                                        $role_display = 'Inventory Admin';
                                        break;
                                    case 'sales':
                                        $role_class = 'badge-sales';
                                        $role_display = 'Sales';
                                        break;
                                    case 'technician':
                                        $role_class = 'badge-technician';
                                        $role_display = 'Technician';
                                        break;
                                    case 'maintenance':
                                    case 'software':
                                        $role_class = 'badge-software';
                                        $role_display = 'Software';
                                        break;
                                    case 'manager':
                                        $role_class = 'badge-manager';
                                        $role_display = 'Manager';
                                        break;
                                    default:
                                        $role_class = '';
                                        $role_display = ucfirst($u['role']);
                                }
                                ?>
                                <span class="badge <?= $role_class ?>"><?= $role_display ?></span>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="<?= $u['branch'] == 'KIMATHI' ? 'branch-kimathi' : 'branch-moi' ?>">
                                    <?= htmlspecialchars($u['branch'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['is_active'] == 1): ?>
                                    <span class="badge badge-active"><i class="fas fa-check-circle"></i> Active</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive"><i class="fas fa-ban"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?= date('M j, Y g:i A', strtotime($u['created_at'])) ?></small></td>
                            <td><small><?= $u['last_login'] ? date('M j, Y g:i A', strtotime($u['last_login'])) : 'Never' ?></small></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_user.php?id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <?php if ($u['is_active'] == 1): ?>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="toggleUserStatus(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>', 'restrict')">
                                                <i class="fas fa-ban"></i> Restrict
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-success btn-sm" onclick="toggleUserStatus(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>', 'activate')">
                                                <i class="fas fa-check-circle"></i> Activate
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="confirmReset(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>')">
                                            <i class="fas fa-sync-alt"></i> Reset
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
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
// Auto-submit form when dropdowns change
document.addEventListener('DOMContentLoaded', function() {
    const branchSelect = document.getElementById('filter-branch');
    const roleSelect = document.getElementById('filter-role');
    const statusSelect = document.getElementById('filter-status');

    function autoSubmit() {
        document.getElementById('filterForm').submit();
    }

    if (branchSelect) branchSelect.addEventListener('change', autoSubmit);
    if (roleSelect) roleSelect.addEventListener('change', autoSubmit);
    if (statusSelect) statusSelect.addEventListener('change', autoSubmit);
});

// Confirm password reset
function confirmReset(userId, userName) {
    if (confirm(`Are you sure you want to reset the password for "${userName}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'reset_password.php';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'user_id';
        input.value = userId;
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

// Toggle user status (Restrict/Activate) – submits to same page
function toggleUserStatus(userId, userName, action) {
    let confirmMsg = '';
    if (action === 'restrict') {
        confirmMsg = `Are you sure you want to RESTRICT "${userName}"? They will no longer be able to log in.`;
    } else {
        confirmMsg = `Are you sure you want to ACTIVATE "${userName}"? They will be able to log in again.`;
    }
    
    if (confirm(confirmMsg)) {
        const form = document.getElementById('toggleForm');
        document.getElementById('toggle_user_id').value = userId;
        document.getElementById('toggle_action').value = action;
        form.submit();
    }
}

// Mobile responsive adjustments
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