<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Restrict access: only sales and cashier roles
if (!in_array($_SESSION['role'], ['sales', 'cashier'])) {
    die("ACCESS DENIED. Only sales personnel and cashiers can add clients.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Get user's branch
$stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_branch = $stmt->fetchColumn();
if (!$user_branch) {
    die("Your account has no branch assigned.");
}

$error = '';
$success = '';

// For cashier, we need to fetch salespersons for dropdown
$salespersons = [];
if ($user_role === 'cashier') {
    $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE role = 'sales' ORDER BY full_name");
    $stmt->execute();
    $salespersons = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = trim($_POST['client_name'] ?? '');
    $client_phone = trim($_POST['client_phone'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $client_box = trim($_POST['client_box'] ?? '');
    $sales_person = ($user_role === 'cashier') ? (int)$_POST['sales_person'] : $user_id;

    if (empty($client_name)) {
        $error = "Client name is required.";
    } elseif ($user_role === 'cashier' && empty($sales_person)) {
        $error = "Please select a salesperson.";
    } else {
        // Check for duplicate client (same name and phone)
        $stmt = $conn->prepare("SELECT COUNT(*) FROM clients WHERE client_name = ? AND client_phone = ?");
        $stmt->execute([$client_name, $client_phone]);
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            $error = "A client with the same name and phone number already exists.";
        } else {
            try {
                // Insert with user's branch
                $stmt = $conn->prepare("INSERT INTO clients (client_name, client_phone, client_email, client_box, sales_person, branch, date_added) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $client_name,
                    $client_phone ?: null,
                    $client_email ?: null,
                    $client_box ?: null,
                    $sales_person,
                    $user_branch
                ]);
                $success = "Client added successfully!";
                // Optionally reset form fields? We'll keep them filled.
            } catch (PDOException $e) {
                $error = "Failed to add client: " . $e->getMessage();
            }
        }
    }
}

require_once "../includes/header.php";
require_once "../includes/sidebar.php";

date_default_timezone_set('Africa/Nairobi');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Add Client | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same base styles as other pages */
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
        .form-card { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); padding: 1.5rem; box-shadow: var(--shadow-sm); max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.25rem; color: var(--gray-700); }
        .form-group .required { color: #dc2626; }
        .form-group input, .form-group select { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(26,75,42,0.1); }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); transform: translateY(-2px); }
        .btn-secondary { background: #2563eb; color: white; }
        .btn-secondary:hover { background: #1d4ed8; transform: translateY(-2px); }
        .message { padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .message-success { background: #d1fae5; color: #065f46; }
        .message-error { background: #fee2e2; color: #991b1b; }
        .actions { margin-top: 1.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
        footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1rem 1.25rem; }
            .form-card { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-user-plus"></i> Add Client</h1>
        <div class="breadcrumb">
            <a href="/inventory_system/dashboard/<?= $user_role === 'cashier' ? 'cashierdashboard.php' : 'salesdashboard.php' ?>">Dashboard</a>
            <span> / </span>
            <a href="view_clients.php">Clients</a>
            <span> / </span>
            <span>Add Client</span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="message message-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="message message-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST">
            <?php if ($user_role === 'cashier'): ?>
                <div class="form-group">
                    <label for="sales_person">Salesperson <span class="required">*</span></label>
                    <select name="sales_person" id="sales_person" required>
                        <option value="">— Select Salesperson —</option>
                        <?php foreach ($salespersons as $sp): ?>
                            <option value="<?= $sp['id'] ?>"><?= htmlspecialchars($sp['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="client_name">Client Name / Organization <span class="required">*</span></label>
                <input type="text" name="client_name" id="client_name" placeholder="e.g., John Doe or ABC Ltd" value="<?= isset($_POST['client_name']) ? htmlspecialchars($_POST['client_name']) : '' ?>" required>
            </div>
            <div class="form-group">
                <label for="client_phone">Phone Number</label>
                <input type="text" name="client_phone" id="client_phone" placeholder="e.g., 0712345678" value="<?= isset($_POST['client_phone']) ? htmlspecialchars($_POST['client_phone']) : '' ?>">
            </div>
            <div class="form-group">
                <label for="client_email">Email Address</label>
                <input type="email" name="client_email" id="client_email" placeholder="e.g., client@example.com" value="<?= isset($_POST['client_email']) ? htmlspecialchars($_POST['client_email']) : '' ?>">
            </div>
            <div class="form-group">
                <label for="client_box">P.O. Box</label>
                <input type="text" name="client_box" id="client_box" placeholder="e.g., P.O. BOX 25-90500" value="<?= isset($_POST['client_box']) ? htmlspecialchars($_POST['client_box']) : '' ?>">
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Client</button>
            </div>
        </form>
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