<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

// Allowed roles: super_admin, inventory_admin, manager
if (!in_array($role, ['super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED.");
}

// Manager branch restriction
$user_branch = '';
if ($role === 'manager') {
    $user_stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_branch = $user_data['branch'] ?? '';
}

// Get filter inputs
$filter_search = trim($_GET['search'] ?? '');
$filter_branch = trim($_GET['branch'] ?? '');
$filter_condition = trim($_GET['condition'] ?? '');

// Build query
$sql = "SELECT u.*, 
               us.full_name AS added_by_name
        FROM ups u
        LEFT JOIN users us ON u.added_by = us.id
        WHERE u.status = 'instock'";
$params = [];

// Manager restriction
if ($role === 'manager' && !empty($user_branch)) {
    $sql .= " AND u.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}

// Filters
if ($filter_branch && $role !== 'manager') {
    $sql .= " AND u.branch = :branch";
    $params['branch'] = $filter_branch;
}
if ($filter_condition) {
    $sql .= " AND u.ups_condition = :condition";
    $params['condition'] = $filter_condition;
}
if ($filter_search) {
    $sql .= " AND (u.serial_number LIKE :search OR u.model LIKE :search)";
    $params['search'] = "%$filter_search%";
}

$sql .= " ORDER BY u.date_added DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$ups_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_items = count($ups_items);
$branches = array_unique(array_column($ups_items, 'branch'));

// Get branch list for filter (only if super_admin or inventory_admin)
$branches_list = [];
if (in_array($role, ['super_admin', 'inventory_admin'])) {
    $stmt = $conn->query("SELECT DISTINCT branch FROM ups WHERE status = 'instock' ORDER BY branch");
    $branches_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Condition list
$conditions = ['New', 'Ex-UK', 'Refurbished'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>In-Stock UPS | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- QR Code Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        /* ===== SAME STYLES AS instock.php ===== */
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
            --primary-dark: #0f3a1e;
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
            --info: #3b82f6;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: all 0.3s ease; }
        .page-header { background: white; padding: 1.5rem 2rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .page-header h1 { font-size: 1.75rem; color: var(--gray-800); font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .stats-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .stat-card { background: white; padding: 1rem 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); flex: 1; min-width: 150px; }
        .stat-card .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--primary); }
        .stat-card .stat-label { font-size: 0.8rem; color: var(--gray-500); }
        .filter-section { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .filter-title { font-size: 1rem; font-weight: 500; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-size: 0.85rem; font-weight: 500; color: var(--gray-600); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; padding-right: 70px; position: relative; }
        .filter-group label .required { color: #dc2626; }
        .filter-group input, .filter-group select { padding: 0.625rem 0.875rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; width: 100%; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1); }
        .filter-actions { display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap; }
        .btn { padding: 0.625rem 1.25rem; background: var(--primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-size: 0.9rem; }
        .btn-secondary { background: var(--gray-500); }
        .btn:hover { opacity: 0.9; }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; font-size: 0.85rem; }
        th { background: var(--gray-50); padding: 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        td { padding: 0.8rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: var(--gray-100); }
        .text-muted { color: var(--gray-500); }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        .btn-view { background: var(--info, #3b82f6); color: white; border: none; border-radius: var(--radius-sm); padding: 0.3rem 0.6rem; font-size: 0.75rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem; }
        .btn-view:hover { background: #2563eb; }

        /* Scan button wrapper */
        .scan-btn-wrapper {
            position: absolute;
            top: -2px;
            right: 0;
            z-index: 5;
        }
        .scan-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            font-weight: 500;
            transition: background 0.2s;
            white-space: nowrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        }
        .scan-btn:hover {
            background: #1d4ed8;
        }
        .scan-btn i {
            font-size: 0.9rem;
        }

        @media (min-width: 1025px) {
            .scan-btn-wrapper {
                display: none !important;
            }
            .filter-group label {
                padding-right: 0;
            }
        }
        @media (max-width: 1024px) {
            .filter-group label {
                padding-right: 70px;
            }
        }

        /* Scanner Overlay */
        .scanner-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .scanner-overlay.active {
            display: flex;
        }
        .scanner-box {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            max-width: 500px;
            width: 95%;
            text-align: center;
            position: relative;
        }
        .scanner-box .close-btn {
            position: absolute;
            top: 10px; right: 15px;
            font-size: 1.8rem;
            cursor: pointer;
            background: none;
            border: none;
            color: var(--gray-500);
        }
        .scanner-box .close-btn:hover {
            color: var(--gray-800);
        }
        .scanner-box #reader {
            width: 100%;
            min-height: 300px;
            margin: 1rem 0;
        }
        .scanner-box p {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        @media (max-width: 480px) {
            .scanner-box {
                max-width: 100%;
                border-radius: 0;
                height: 100vh;
                padding-top: 3rem;
            }
            .scanner-box #reader {
                height: 70vh;
                min-height: 200px;
            }
        }

        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { 
            .filter-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; justify-content: center; }
            .stats-row { flex-direction: column; }
            .filter-actions { flex-direction: column; align-items: stretch; }
            table { font-size: 0.75rem; min-width: 700px; }
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-bolt"></i> In-Stock UPS</h1>
        <div class="breadcrumb">
            <?php if ($role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($role === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php elseif ($role === 'inventory_admin'): ?>
                <a href="../dashboard/inventorydashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <span>In-Stock UPS</span>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= number_format($total_items) ?></div>
            <div class="stat-label">Total In-Stock</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format(count($branches)) ?></div>
            <div class="stat-label">Branches</div>
        </div>
    </div>

    <div class="filter-section">
        <div class="filter-title"><i class="fas fa-filter"></i> Filter UPS</div>
        <form method="GET" class="filter-grid" id="filterForm">
            <!-- Search with Scanner -->
            <div class="filter-group">
                <label>
                    <span>Search (Serial / Model)</span>
                    <span class="scan-btn-wrapper">
                        <button type="button" class="scan-btn" onclick="openScanner()">
                            <i class="fas fa-camera"></i> Scan
                        </button>
                    </span>
                </label>
                <input type="text" name="search" id="serialSearch" placeholder="e.g., UPKNM9JHFH" value="<?= htmlspecialchars($filter_search) ?>">
            </div>

            <?php if ($role !== 'manager'): ?>
            <div class="filter-group">
                <label>Branch</label>
                <select name="branch">
                    <option value="">-- All Branches --</option>
                    <?php foreach ($branches_list as $br): ?>
                        <option value="<?= htmlspecialchars($br) ?>" <?= $filter_branch == $br ? 'selected' : '' ?>><?= htmlspecialchars($br) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="filter-group">
                <label>Condition</label>
                <select name="condition">
                    <option value="">-- All --</option>
                    <?php foreach ($conditions as $cond): ?>
                        <option value="<?= $cond ?>" <?= $filter_condition == $cond ? 'selected' : '' ?>><?= $cond ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn"><i class="fas fa-search"></i> Filter</button>
                <a href="instock_ups.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <?php if (empty($ups_items)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No in-stock UPS units found matching your criteria.</p>
                <a href="instock_ups.php" class="btn" style="margin-top: 1rem;">
                    <i class="fas fa-undo"></i> Clear Filters
                </a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Serial Number</th>
                        <th>Model</th>
                        <th>Capacity (VA)</th>
                        <th>Condition</th>
                        <th>Branch</th>
                        <th>Price (KES)</th>
                        <th>Added By</th>
                        <th>Date Added</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($ups_items as $item): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><code><?= htmlspecialchars($item['serial_number']) ?></code></td>
                            <td><strong><?= htmlspecialchars($item['model'] ?? '-') ?></strong></td>
                            <td><?= (int)$item['capacity'] ?> VA</td>
                            <td><span class="badge"><?= htmlspecialchars($item['ups_condition'] ?? 'New') ?></span></td>
                            <td><?= htmlspecialchars($item['branch'] ?? '-') ?></td>
                            <td><?= $item['price'] !== null ? number_format($item['price'], 2) : '—' ?></td>
                            <td><?= htmlspecialchars($item['added_by_name'] ?? 'System') ?></td>
                            <td><small><?= date('M j, Y', strtotime($item['date_added'])) ?></small></td>
                            <td>
                                <a href="view_ups.php?sn=<?= urlencode($item['serial_number']) ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers
    </div>
</div>

<!-- Scanner Overlay Modal -->
<div class="scanner-overlay" id="scannerOverlay">
    <div class="scanner-box">
        <button class="close-btn" onclick="closeScanner()">&times;</button>
        <h3 style="margin-bottom:0.5rem;"><i class="fas fa-camera"></i> Scan Serial Number</h3>
        <p>Position the barcode/QR code in front of the camera</p>
        <div id="reader"></div>
        <button class="btn btn-secondary" onclick="closeScanner()" style="margin-top:0.5rem;">Cancel</button>
    </div>
</div>

<script>
    // --- Scanner Logic (same as add_ups.php) ---
    let html5QrCode = null;
    let scannerActive = false;

    function playBeep() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            const osc1 = audioCtx.createOscillator();
            const gain1 = audioCtx.createGain();
            osc1.connect(gain1);
            gain1.connect(audioCtx.destination);
            osc1.frequency.value = 1200;
            osc1.type = 'square';
            gain1.gain.setValueAtTime(0.25, audioCtx.currentTime);
            gain1.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.08);
            osc1.start(audioCtx.currentTime);
            osc1.stop(audioCtx.currentTime + 0.08);

            const osc2 = audioCtx.createOscillator();
            const gain2 = audioCtx.createGain();
            osc2.connect(gain2);
            gain2.connect(audioCtx.destination);
            osc2.frequency.value = 1200;
            osc2.type = 'square';
            gain2.gain.setValueAtTime(0.25, audioCtx.currentTime + 0.12);
            gain2.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.2);
            osc2.start(audioCtx.currentTime + 0.12);
            osc2.stop(audioCtx.currentTime + 0.2);
        } catch (e) { /* ignore */ }
    }

    function openScanner() {
        const overlay = document.getElementById('scannerOverlay');
        overlay.classList.add('active');
        setTimeout(() => {
            if (!scannerActive) startScanner();
        }, 500);
    }

    function closeScanner() {
        const overlay = document.getElementById('scannerOverlay');
        overlay.classList.remove('active');
        if (html5QrCode && scannerActive) {
            html5QrCode.stop().then(() => {
                html5QrCode.clear();
                scannerActive = false;
            }).catch(() => { scannerActive = false; });
        }
    }

    function startScanner() {
        const readerElement = document.getElementById('reader');
        if (!readerElement) {
            alert('Scanner error: reader element missing.');
            closeScanner();
            return;
        }
        if (typeof Html5Qrcode === 'undefined') {
            alert('Scanner library not loaded. Please check your internet connection and refresh.');
            closeScanner();
            return;
        }
        readerElement.style.width = '100%';
        readerElement.style.minHeight = '300px';

        html5QrCode = new Html5Qrcode("reader");
        const config = {
            fps: 15,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        };

        html5QrCode.start(
            { facingMode: "environment" },
            config,
            onScanSuccess,
            onScanError
        ).then(() => {
            scannerActive = true;
        }).catch(err => {
            console.error('Scanner start error (environment):', err);
            html5QrCode.start(
                { facingMode: "user" },
                config,
                onScanSuccess,
                onScanError
            ).then(() => {
                scannerActive = true;
            }).catch(err2 => {
                console.error('Scanner start error (user):', err2);
                let msg = 'Unable to access camera. Please ensure camera permissions are granted.';
                if (window.location.protocol === 'http:') {
                    msg += ' Your site is using HTTP; camera access may be blocked. Try using HTTPS.';
                }
                alert(msg);
                closeScanner();
            });
        });
    }

    function onScanSuccess(decodedText, decodedResult) {
        playBeep();
        const serialInput = document.getElementById('serialSearch');
        if (serialInput) {
            serialInput.value = decodedText.trim().toUpperCase();
            serialInput.dispatchEvent(new Event('input'));
            // Optionally auto-submit form
            // document.getElementById('filterForm').submit();
        }
        closeScanner();
    }

    function onScanError(errorMessage) {
        // ignore
    }

    window.addEventListener('beforeunload', function() {
        if (html5QrCode && scannerActive) {
            html5QrCode.stop().catch(() => {});
        }
    });

    // Auto-uppercase serial input
    document.addEventListener('DOMContentLoaded', function() {
        const serialInput = document.getElementById('serialSearch');
        if (serialInput) {
            serialInput.addEventListener('blur', function() {
                this.value = this.value.trim().toUpperCase();
            });
        }
    });

    // Mobile responsive adjustments
    function adjustMainContent() {
        const main = document.querySelector('.main-content');
        if (window.innerWidth <= 1200) {
            main.style.marginLeft = '0';
        } else {
            main.style.marginLeft = '260px';
        }
    }
    window.addEventListener('resize', adjustMainContent);
    adjustMainContent();
</script>

<?php require_once "../includes/footer.php"; ?>
</body>
</html>