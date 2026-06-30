<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../includes/header.php";
require_once "../includes/sidebar.php";

// Only super_admin, inventory_admin, and manager can access
if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'])) {
    die("Access denied!");
}

$error = "";
$success = "";
$existing_id = null;
$current_qty = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $charger_type   = trim($_POST['charger_type']);
    $condition      = $_POST['condition'];
    $branch         = $_POST['branch'];
    $quantity       = (int)$_POST['quantity'];
    $price          = !empty($_POST['price']) ? (float)$_POST['price'] : null;
    $added_by       = (int)$_SESSION['user_id'];
    $existing_id    = !empty($_POST['existing_id']) ? (int)$_POST['existing_id'] : null;

    if (empty($charger_type) || $quantity <= 0) {
        $error = "Charger type and a positive quantity are required.";
    } else {
        try {
            if ($existing_id) {
                // Update existing charger: add to quantity
                $stmt = $conn->prepare("
                    UPDATE chargers 
                    SET quantity = quantity + :qty, 
                        price = :price, 
                        updated_by = :updated_by, 
                        date_updated = NOW() 
                    WHERE id = :id AND branch = :branch
                ");
                $stmt->execute([
                    'qty'         => $quantity,
                    'price'       => $price,
                    'updated_by'  => $added_by,
                    'id'          => $existing_id,
                    'branch'      => $branch
                ]);

                // Log activity
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (:uid, 'Updated Charger', :details)");
                $log->execute([
                    'uid'     => $_SESSION['user_id'],
                    'details' => "Added $quantity more units to charger: $charger_type ($condition) in $branch branch"
                ]);

                $success = "Charger quantity updated successfully! Added $quantity units.";
            } else {
                // Insert new charger
                $stmt = $conn->prepare("
                    INSERT INTO chargers 
                    (charger_type, charger_condition, quantity, branch, added_by, price)
                    VALUES (:type, :condition, :quantity, :branch, :added_by, :price)
                ");
                $stmt->execute([
                    'type'       => $charger_type,
                    'condition'  => $condition,
                    'quantity'   => $quantity,
                    'branch'     => $branch,
                    'added_by'   => $added_by,
                    'price'      => $price
                ]);

                // Log activity
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (:uid, 'Added Charger', :details)");
                $log->execute([
                    'uid'     => $_SESSION['user_id'],
                    'details' => "Added new charger: $charger_type ($condition) Qty: $quantity to $branch branch"
                ]);

                $success = "New charger added successfully!";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Add Charger | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Same CSS as add_hdd.php – kept for consistency */
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
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
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

        .form-container {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .form-header {
            background: var(--gray-50);
            padding: 1.25rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .form-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-header h2 i {
            color: var(--primary);
        }

        .form-body {
            padding: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .form-group label .required {
            color: #dc2626;
            margin-left: 0.25rem;
        }

        .form-group label .optional {
            color: var(--gray-400);
            font-weight: normal;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }

        .form-group input,
        .form-group select {
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: var(--font-sans);
            background: white;
            width: 100%;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

        .form-group input::placeholder {
            color: var(--gray-400);
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

        .alert i {
            font-size: 1.25rem;
        }

        .note-box {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid var(--gray-200);
        }

        .note-box i {
            color: var(--primary);
            font-size: 1.25rem;
        }

        .note-box p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .form-actions {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
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
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-50);
        }

        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }

        /* AJAX feedback */
        .check-feedback {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            margin-top: 0.5rem;
            font-size: 0.9rem;
            display: none;
        }
        .check-feedback.info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
            display: block;
        }
        .check-feedback.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            display: block;
        }
        .check-feedback.warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            display: block;
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
            .form-body {
                padding: 1.5rem;
            }
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .form-group.full-width {
                grid-column: span 1;
            }
            .form-actions {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            .form-header {
                padding: 1rem 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem 0.5rem 0.5rem !important;
                padding-top: 4rem !important;
            }
            .form-body {
                padding: 1rem;
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
        <h1><i class="fas fa-bolt"></i> Add Charger</h1>
        <div class="breadcrumb">
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="/inventory_system/dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <a href="/inventory_system/dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'inventory_admin'): ?>
                <a href="/inventory_system/dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="chargers_instock.php">Chargers</a>
            <span> / </span>
            <span>Add Charger</span>
        </div>
    </div>

    <div class="form-container">
        <div class="form-header">
            <h2><i class="fas fa-plus-circle"></i> Charger Information</h2>
        </div>
        <div class="form-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="chargerForm">
                <input type="hidden" name="existing_id" id="existing_id" value="">

                <div class="form-grid">
                    <!-- Charger Type -->
                    <div class="form-group">
                        <label>Charger Type <span class="required">*</span></label>
                        <input type="text" name="charger_type" id="chargerType" required placeholder="e.g., HP Blue Pin 45W" autofocus>
                    </div>

                    <!-- Condition -->
                    <div class="form-group">
                        <label>Condition <span class="required">*</span></label>
                        <select name="condition" id="conditionSelect" required>
                            <option value="new" selected>New</option>
                            <option value="ex-uk">Ex‑UK</option>
                        </select>
                    </div>

                    <!-- Branch -->
                    <div class="form-group">
                        <label>Branch <span class="required">*</span></label>
                        <select name="branch" id="branchSelect" required>
                            <option value="MOI" selected>MOI</option>
                            <option value="KIMATHI">KIMATHI</option>
                        </select>
                    </div>

                    <!-- Quantity -->
                    <div class="form-group">
                        <label id="qtyLabel">Quantity <span class="required">*</span></label>
                        <input type="number" name="quantity" id="quantity" min="1" required placeholder="Enter quantity">
                    </div>

                    <!-- Price (optional) -->
                    <div class="form-group full-width">
                        <label>Price (KES) <span class="optional">(optional)</span></label>
                        <input type="number" name="price" step="0.01" min="0" placeholder="Price per unit if known">
                    </div>
                </div>

                <!-- Feedback area -->
                <div id="checkFeedback" class="check-feedback"></div>

                <div class="form-actions">
                    <a href="chargers_instock.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add / Update Charger</button>
                </div>
            </form>

            <div class="note-box">
                <i class="fas fa-info-circle"></i>
                <p><strong>Note:</strong> If a charger with the same <strong>type, condition, and branch</strong> already exists, you will be able to add more quantity to it. Otherwise, a new charger will be created.</p>
            </div>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeInput = document.getElementById('chargerType');
    const conditionSelect = document.getElementById('conditionSelect');
    const branchSelect = document.getElementById('branchSelect');
    const feedbackDiv = document.getElementById('checkFeedback');
    const qtyLabel = document.getElementById('qtyLabel');
    const qtyInput = document.getElementById('quantity');
    const existingIdInput = document.getElementById('existing_id');

    let checkTimeout = null;

    function checkCharger() {
        const type = typeInput.value.trim();
        const condition = conditionSelect.value;
        const branch = branchSelect.value;

        if (type.length < 2) {
            feedbackDiv.className = 'check-feedback';
            feedbackDiv.textContent = '';
            qtyLabel.textContent = 'Quantity *';
            existingIdInput.value = '';
            return;
        }

        feedbackDiv.className = 'check-feedback info';
        feedbackDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking availability...';

        fetch('check_charger.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'type=' + encodeURIComponent(type) + '&condition=' + encodeURIComponent(condition) + '&branch=' + encodeURIComponent(branch)
        })
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                feedbackDiv.className = 'check-feedback success';
                feedbackDiv.innerHTML = '<i class="fas fa-check-circle"></i> Charger already exists in <strong>' + branch + '</strong> branch. Current quantity: <strong>' + data.quantity + '</strong> units.';
                qtyLabel.textContent = 'Quantity to Add *';
                qtyInput.placeholder = 'Enter additional quantity';
                existingIdInput.value = data.id;
            } else {
                feedbackDiv.className = 'check-feedback warning';
                feedbackDiv.innerHTML = '<i class="fas fa-info-circle"></i> This charger is new for <strong>' + branch + '</strong> branch. You will create it with the quantity you enter.';
                qtyLabel.textContent = 'Quantity *';
                qtyInput.placeholder = 'Enter initial quantity';
                existingIdInput.value = '';
            }
        })
        .catch(error => {
            feedbackDiv.className = 'check-feedback warning';
            feedbackDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Could not check availability. Please try again.';
            console.error('Error checking charger:', error);
        });
    }

    function triggerCheck() {
        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(checkCharger, 400);
    }

    typeInput.addEventListener('input', triggerCheck);
    conditionSelect.addEventListener('change', checkCharger);
    branchSelect.addEventListener('change', checkCharger);

    // Initial check if type has a value
    if (typeInput.value.trim().length >= 2) {
        setTimeout(checkCharger, 300);
    }

    // --- Mobile responsive adjustments ---
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