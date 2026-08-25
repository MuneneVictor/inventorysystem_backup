<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];
$user_branch = $_SESSION['branch'] ?? 'KIMATHI';

if (!in_array($user_role, ['sales', 'super_admin', 'manager', 'technician'])) {
    die("ACCESS DENIED.");
}

// Generate quotation number
function generateQuotationNumber($conn) {
    $stmt = $conn->query("SELECT quotation_number FROM quotations ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch(PDO::FETCH_COLUMN);
    if ($last) {
        $num = (int) substr($last, 2) + 1;
    } else {
        $num = 1;
    }
    return 'MC' . str_pad($num, 2, '0', STR_PAD_LEFT);
}

// Handle reset
if (isset($_GET['reset']) && $_GET['reset'] == '1') {
    unset($_SESSION['quotation_id']);
    header("Location: write_quotation.php");
    exit;
}

// Handle POST - Create quotation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quotation'])) {
    $client_name = trim($_POST['client_name'] ?? '');
    $client_phone = trim($_POST['client_phone'] ?? '');
    $client_box = trim($_POST['client_box'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $payment_due = $_POST['payment_due'] ?? date('Y-m-d', strtotime('+7 days'));
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($client_name)) {
        $error = "Client name is required";
    } else {
        try {
            // Check if client exists by BOTH name AND phone
            // If phone is empty, check by name only
            if (!empty($client_phone)) {
                $stmt = $conn->prepare("SELECT id FROM clients WHERE client_name = ? AND client_phone = ?");
                $stmt->execute([$client_name, $client_phone]);
            } else {
                $stmt = $conn->prepare("SELECT id FROM clients WHERE client_name = ?");
                $stmt->execute([$client_name]);
            }
            $existing = $stmt->fetch();
            
            if ($existing) {
                $client_id = $existing['id'];
                // Update existing client with any new info
                $stmt = $conn->prepare("UPDATE clients SET client_box = ?, client_email = ? WHERE id = ?");
                $stmt->execute([$client_box, $client_email, $client_id]);
            } else {
                // Insert new client
                $stmt = $conn->prepare("INSERT INTO clients (client_name, client_phone, client_box, client_email, sales_person, branch) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$client_name, $client_phone, $client_box, $client_email, $user_id, $user_branch]);
                $client_id = $conn->lastInsertId();
            }
            
            // Create quotation
            $qnum = generateQuotationNumber($conn);
            $quotation_date = date('Y-m-d');
            
            $stmt = $conn->prepare("INSERT INTO quotations (quotation_number, client_name, client_phone, client_box, client_email, quotation_date, payment_due_date, notes, user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");
            $stmt->execute([$qnum, $client_name, $client_phone, $client_box, $client_email, $quotation_date, $payment_due, $notes, $user_id]);
            $quotation_id = $conn->lastInsertId();
            
            $_SESSION['quotation_id'] = $quotation_id;
            header("Location: add_quotation_items.php");
            exit;
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Check if we have a quotation in session
$quotation_id = $_SESSION['quotation_id'] ?? 0;
if ($quotation_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ? AND user_id = ?");
    $stmt->execute([$quotation_id, $user_id]);
    $quotation = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($quotation) {
        header("Location: add_quotation_items.php");
        exit;
    } else {
        unset($_SESSION['quotation_id']);
    }
}

// Get active quotations for this user
$stmt = $conn->prepare("SELECT * FROM quotations WHERE user_id = ? AND status = 'draft' ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$activeQuotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write Quotation - Step 1: Client</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; color: #1f2937; line-height: 1.5; }
        .main-content { padding: 2rem; margin-left: 260px; min-height: 100vh; background: #f3f4f6; }
        .page-header { background: white; padding: 1.5rem 2rem; border-radius: 0.75rem; margin-bottom: 1.5rem; border: 1px solid #e5e7eb; }
        .page-header h1 { font-size: 1.75rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; }
        .page-header h1 i { color: #1a4b2a; }
        .breadcrumb { color: #6b7280; font-size: 0.9rem; }
        .breadcrumb a { color: #1a4b2a; text-decoration: none; }
        .step-indicator { display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: center; flex-wrap: wrap; }
        .step { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; background: #e5e7eb; color: #6b7280; }
        .step.active { background: #1a4b2a; color: white; }
        .step .number { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; background: rgba(255,255,255,0.2); }
        .section { background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; padding: 1.5rem; margin-bottom: 1.5rem; }
        .section-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.25rem; position: relative; }
        .form-group label { font-size: 0.85rem; font-weight: 500; color: #374151; }
        .form-group input, .form-group textarea { padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9rem; width: 100%; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #1a4b2a; box-shadow: 0 0 0 3px rgba(26,75,42,0.1); }
        .required { color: #dc2626; }
        .btn { padding: 0.6rem 1.2rem; background: #1a4b2a; color: white; border: none; border-radius: 0.5rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; font-size: 0.9rem; transition: all 0.2s; text-decoration: none; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-success { background: #16a34a; }
        .btn-secondary { background: #6b7280; }
        .btn-sm { padding: 0.25rem 0.75rem; font-size: 0.8rem; }
        .table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .table th { background: #f9fafb; padding: 0.75rem 0.5rem; text-align: left; font-weight: 600; border-bottom: 2px solid #e5e7eb; }
        .table td { padding: 0.75rem 0.5rem; border-bottom: 1px solid #f3f4f6; }
        .table tr:hover { background: #f9fafb; }
        .text-muted { color: #9ca3af; }
        .search-results { position: absolute; background: white; border: 1px solid #d1d5db; border-radius: 0.5rem; max-height: 200px; overflow-y: auto; z-index: 1000; width: 100%; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .search-results .result-item { padding: 0.5rem 0.75rem; cursor: pointer; border-bottom: 1px solid #f3f4f6; }
        .search-results .result-item:hover { background: #f3f4f6; }
        .search-results .result-item strong { display: block; }
        .search-results .result-item small { color: #6b7280; font-size: 0.8rem; }
        .client-info { background: #f9fafb; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #e5e7eb; margin-top: 0.5rem; display: none; }
        .notification { padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .notification.error { background: #fee2e2; border: 1px solid #dc2626; color: #991b1b; }
        .notification.success { background: #dcfce7; border: 1px solid #16a34a; color: #14532d; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: #9ca3af; border-top: 1px solid #e5e7eb; }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; padding: 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .btn { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice"></i> Write Quotation</h1>
        <div class="breadcrumb">
            <?php if($user_role === 'sales'): ?>
                <a href="../dashboard/salesdashboard.php">Dashboard</a> /
            <?php endif; ?>
            <?php if($user_role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php">Dashboard</a> /
            <?php endif; ?>
            <?php if($user_role === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php">Dashboard</a> /
            <?php endif; ?>
            <?php if($user_role === 'technician'): ?>
                <a href="../dashboard/techniciandashboard.php">Dashboard</a> /
            <?php endif; ?> Write Quotation
        </div>
    </div>

    <!-- Step Indicator -->
    <div class="step-indicator">
        <div class="step active"><span class="number">1</span> Client Details</div>
        <div class="step"><span class="number">2</span> Add Items</div>
        <div class="step"><span class="number">3</span> Review & Export</div>
    </div>

    <?php if (isset($error)): ?>
        <div class="notification error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($success)): ?>
        <div class="notification success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Active Quotations -->
    <div class="section">
        <div class="section-title">
            <i class="fas fa-clock"></i> Draft Quotations
            <?php if (!empty($activeQuotations)): ?>
                <span style="background:#e5e7eb; padding:0.25rem 0.75rem; border-radius:9999px; font-size:0.8rem; margin-left:0.5rem;"><?= count($activeQuotations) ?></span>
            <?php endif; ?>
        </div>
        <?php if (empty($activeQuotations)): ?>
            <p class="text-muted">No draft quotations found. Start a new quotation below.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client</th>
                            <th>Phone</th>
                            <th>Date</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeQuotations as $q): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($q['quotation_number']) ?></strong></td>
                                <td><?= htmlspecialchars($q['client_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($q['client_phone'] ?? '—') ?></td>
                                <td><?= date('M j, Y', strtotime($q['created_at'])) ?></td>
                                <td style="text-align:right;">
                                    <a href="add_quotation_items.php?quotation_id=<?= $q['id'] ?>" class="btn btn-sm">
                                        <i class="fas fa-arrow-right"></i> Continue
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- New Quotation Form -->
    <div class="section">
        <div class="section-title"><i class="fas fa-plus-circle"></i> Start New Quotation</div>
        <form method="POST" action="">
            <div class="form-group" style="position:relative;">
                <label for="client_search">Search Client (optional)</label>
                <input type="text" id="client_search" placeholder="Type client name to search..." autocomplete="off">
                <input type="hidden" name="client_id" id="client_id" value="">
                <div id="clientResults" class="search-results"></div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Client Name <span class="required">*</span></label>
                    <input type="text" name="client_name" id="client_name" placeholder="Enter client name" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="client_phone" id="client_phone" placeholder="Phone number">
                </div>
                <div class="form-group">
                    <label>Address / Box</label>
                    <input type="text" name="client_box" id="client_box" placeholder="P.O. Box">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="client_email" id="client_email" placeholder="Email address">
                </div>
                <div class="form-group">
                    <label>Payment Due Date</label>
                    <input type="date" name="payment_due" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                </div>
            </div>
            <div class="form-group" style="margin-top:1rem;">
                <label>Notes (Optional)</label>
                <textarea name="notes" rows="2" placeholder="Any additional notes..."></textarea>
            </div>
            <div style="margin-top:1.5rem;">
                <button type="submit" name="create_quotation" class="btn btn-success"><i class="fas fa-save"></i> Save & Continue</button>
            </div>
        </form>
    </div>

    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Client search
    const searchInput = document.getElementById('client_search');
    const clientIdInput = document.getElementById('client_id');
    const clientNameInput = document.getElementById('client_name');
    const clientPhoneInput = document.getElementById('client_phone');
    const clientBoxInput = document.getElementById('client_box');
    const clientEmailInput = document.getElementById('client_email');
    const resultsContainer = document.getElementById('clientResults');
    let searchTimeout;

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            if (query.length < 2) {
                resultsContainer.style.display = 'none';
                return;
            }
            searchTimeout = setTimeout(() => {
                fetch(`ajax_search_clients.php?q=${encodeURIComponent(query)}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        resultsContainer.innerHTML = '';
                        resultsContainer.style.display = 'block';
                        
                        if (data.error) {
                            resultsContainer.innerHTML = `<div class="result-item" style="color:#dc2626;">Error: ${data.error}</div>`;
                            return;
                        }
                        
                        if (data.length === 0) {
                            resultsContainer.innerHTML = '<div class="result-item" style="color:#6b7280;">No clients found</div>';
                            return;
                        }
                        
                        data.forEach(client => {
                            const div = document.createElement('div');
                            div.className = 'result-item';
                            div.innerHTML = `<strong>${client.client_name}</strong> <small>${client.client_phone || 'No phone'}</small>`;
                            div.dataset.id = client.id;
                            div.dataset.name = client.client_name;
                            div.dataset.phone = client.client_phone || '';
                            div.dataset.box = client.client_box || '';
                            div.dataset.email = client.client_email || '';
                            div.addEventListener('click', function() {
                                searchInput.value = this.dataset.name;
                                clientIdInput.value = this.dataset.id;
                                clientNameInput.value = this.dataset.name;
                                clientPhoneInput.value = this.dataset.phone;
                                clientBoxInput.value = this.dataset.box;
                                clientEmailInput.value = this.dataset.email;
                                resultsContainer.style.display = 'none';
                            });
                            resultsContainer.appendChild(div);
                        });
                    })
                    .catch(err => {
                        console.error('Search error:', err);
                        resultsContainer.innerHTML = '<div class="result-item" style="color:#dc2626;">Error loading clients</div>';
                        resultsContainer.style.display = 'block';
                    });
            }, 300);
        });

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
    }
});
</script>
</body>
</html>