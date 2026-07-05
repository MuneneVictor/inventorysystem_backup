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

// Get filter inputs
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build query - ONLY show quotations belonging to the logged-in user
$sql = "SELECT q.*, u.full_name AS created_by_name 
        FROM quotations q
        LEFT JOIN users u ON q.user_id = u.id
        WHERE q.user_id = ?";
$params = [$user_id];

if (!empty($search)) {
    $sql .= " AND (q.client_name LIKE ? OR q.quotation_number LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
}

if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND DATE(q.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

if (!empty($status_filter)) {
    $sql .= " AND q.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY q.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_count = count($quotations);
$total_amount = array_sum(array_column($quotations, 'grand_total'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Quotations | Mombasa Computers</title>
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
        .stats-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .stat-card { background: white; padding: 1rem 1.5rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); flex: 1; min-width: 150px; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 700; color: #1a4b2a; }
        .stat-card .stat-label { font-size: 0.8rem; color: #6b7280; }
        .filter-section { background: white; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 1.5rem; border: 1px solid #e5e7eb; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-size: 0.85rem; font-weight: 500; color: #374151; }
        .filter-group input, .filter-group select { padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9rem; width: 100%; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: #1a4b2a; box-shadow: 0 0 0 3px rgba(26,75,42,0.1); }
        .filter-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .btn { padding: 0.6rem 1.2rem; background: #1a4b2a; color: white; border: none; border-radius: 0.5rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-size: 0.9rem; transition: all 0.2s; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-secondary { background: #6b7280; }
        .btn-success { background: #16a34a; }
        .btn-sm { padding: 0.25rem 0.75rem; font-size: 0.8rem; }
        .table-wrapper { background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 850px; font-size: 0.9rem; }
        th { background: #f9fafb; padding: 0.75rem 0.5rem; text-align: left; font-weight: 600; border-bottom: 2px solid #e5e7eb; }
        td { padding: 0.75rem 0.5rem; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
        .badge-draft { background: #e5e7eb; color: #374151; }
        .badge-sent { background: #dbeafe; color: #1e40af; }
        .badge-cancelled { background: #fee2e2; color: #dc2626; }
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: #9ca3af; border-top: 1px solid #e5e7eb; }
        .search-results { 
            position: absolute; 
            background: white; 
            border: 1px solid #d1d5db; 
            border-radius: 0.5rem; 
            max-height: 250px; 
            overflow-y: auto; 
            z-index: 1000; 
            width: 100%; 
            display: none; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        }
        .search-results .result-item {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
        }
        .search-results .result-item:hover {
            background: #f3f4f6;
        }
        .search-results .result-item strong {
            display: inline-block;
            color: #1a4b2a;
        }
        .search-results .result-item small {
            color: #6b7280;
        }
        .position-relative {
            position: relative;
        }
        @media (max-width: 1200px) { 
            .main-content { margin-left: 0 !important; padding: 1rem !important; padding-top: 5rem !important; } 
        }
        @media (max-width: 768px) { 
            .filter-grid { grid-template-columns: 1fr; } 
            .btn { width: 100%; justify-content: center; } 
            .stats-row { flex-direction: column; } 
            .filter-actions { flex-direction: column; align-items: stretch; }
            table { font-size: 0.75rem; min-width: 650px; }
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice"></i> My Quotations</h1>
        <div class="breadcrumb">
            <?php if($user_role === 'sales'): ?>
                <a href="../dashboard/salesdashboard.php">Dashboard</a>
            <?php elseif($user_role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif($user_role === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php">Dashboard</a>
            <?php elseif($user_role === 'technician'): ?>
                <a href="../dashboard/techniciandashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="../dashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>Quotations</span>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= number_format($total_count) ?></div>
            <div class="stat-label">Total Quotations</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">KES <?= number_format($total_amount, 0) ?></div>
            <div class="stat-label">Total Amount</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                <?php 
                $draft_count = count(array_filter($quotations, function($q) { return $q['status'] === 'draft'; }));
                echo number_format($draft_count);
                ?>
            </div>
            <div class="stat-label">Draft</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                <?php 
                $sent_count = count(array_filter($quotations, function($q) { return $q['status'] === 'sent'; }));
                echo number_format($sent_count);
                ?>
            </div>
            <div class="stat-label">Sent</div>
        </div>
    </div>

    <div class="filter-section">
        <!-- Search Bar -->
        <div class="form-group position-relative" style="margin-bottom: 1rem;">
            <label>Search Quotations</label>
            <input type="text" id="quotation_search" placeholder="Search by quotation number, client name, or phone..." autocomplete="off">
            <div id="searchResults" class="search-results"></div>
        </div>

        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="filter-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="sent" <?= $status_filter === 'sent' ? 'selected' : '' ?>>Sent</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn"><i class="fas fa-search"></i> Filter</button>
                <a href="quotations_list.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                <a href="write_quotation.php" class="btn btn-success"><i class="fas fa-plus"></i> New Quotation</a>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <?php if (empty($quotations)): ?>
            <div class="empty-state">
                <i class="fas fa-file-invoice" style="font-size:2rem; display:block; margin-bottom:1rem; color:#d1d5db;"></i>
                <p>No quotations found.</p>
                <a href="write_quotation.php" class="btn btn-success" style="margin-top:1rem;">Create First Quotation</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Quotation #</th>
                        <th>Client</th>
                        <th>Phone</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotations as $q): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($q['quotation_number']) ?></strong></td>
                            <td><?= htmlspecialchars($q['client_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($q['client_phone'] ?? '—') ?></td>
                            <td>KES <?= number_format($q['grand_total'], 2) ?></td>
                            <td>
                                <span class="badge badge-<?= $q['status'] ?>">
                                    <?= ucfirst($q['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($q['created_at'])) ?></td>
                            <td style="text-align:right;">
                                <a href="review_quotation.php?id=<?= $q['id'] ?>" class="btn btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quotation search with live results
    const searchInput = document.getElementById('quotation_search');
    const searchResults = document.getElementById('searchResults');
    
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch(`ajax_search_quotations.php?q=${encodeURIComponent(query)}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        searchResults.innerHTML = '';
                        searchResults.style.display = 'block';
                        
                        if (data.error) {
                            searchResults.innerHTML = `<div class="result-item" style="color:#dc2626;">Error: ${data.error}</div>`;
                            return;
                        }
                        
                        if (data.length === 0) {
                            searchResults.innerHTML = '<div class="result-item" style="color:#6b7280;">No quotations found</div>';
                            return;
                        }
                        
                        data.forEach(quotation => {
                            const div = document.createElement('div');
                            div.className = 'result-item';
                            
                            let statusColor = '';
                            let statusText = quotation.status || 'draft';
                            if (quotation.status === 'sent') statusColor = '#2563eb';
                            else if (quotation.status === 'draft') statusColor = '#6b7280';
                            else if (quotation.status === 'cancelled') statusColor = '#dc2626';
                            
                            div.innerHTML = `
                                <strong>${quotation.quotation_number}</strong> 
                                <small>${quotation.client_name || 'No client'}</small>
                                <br>
                                <small>Amount: KES ${Number(quotation.grand_total || 0).toLocaleString()}</small>
                                <br>
                                <small style="color:${statusColor}; font-weight:600;">${statusText.toUpperCase()}</small>
                            `;
                            
                            div.addEventListener('click', function() {
                                window.location.href = `review_quotation.php?id=${quotation.id}`;
                            });
                            
                            searchResults.appendChild(div);
                        });
                    })
                    .catch(err => {
                        console.error('Search error:', err);
                        searchResults.innerHTML = '<div class="result-item" style="color:#dc2626;">Error loading quotations</div>';
                        searchResults.style.display = 'block';
                    });
            }, 300);
        });
        
        // Hide results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#quotation_search') && !e.target.closest('#searchResults')) {
                searchResults.style.display = 'none';
            }
        });
    }
});
</script>
</body>
</html>