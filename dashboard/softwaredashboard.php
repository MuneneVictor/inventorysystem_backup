<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// STRICT ROLE CHECK - Only software role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'software') {
    die("ACCESS DENIED: You do not have permission to access this page.");
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Get user branch
$user_branch = null;
$stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_branch = $stmt->fetchColumn();

function safeQuery($conn, $sql, $params = []) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

// Helper function to safely escape HTML
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// ============================================================
// MAINTENANCE/SOFTWARE STATISTICS
// ============================================================

// 1. Total updates performed by this user (current month)
$s = safeQuery($conn, "SELECT COUNT(*) FROM maintenance WHERE performed_by = ? AND MONTH(date_performed) = MONTH(CURDATE()) AND YEAR(date_performed) = YEAR(CURDATE())", [$user_id]);
$myTotalUpdates = $s ? (int)$s->fetchColumn() : 0;

// 2. Today's updates
$s = safeQuery($conn, "SELECT COUNT(*) FROM maintenance WHERE performed_by = ? AND DATE(date_performed) = CURDATE()", [$user_id]);
$myTodayUpdates = $s ? (int)$s->fetchColumn() : 0;

// 3. This week's updates
$s = safeQuery($conn, "SELECT COUNT(*) FROM maintenance WHERE performed_by = ? AND YEARWEEK(date_performed) = YEARWEEK(CURDATE())", [$user_id]);
$thisWeekUpdates = $s ? (int)$s->fetchColumn() : 0;

// 4. Total system maintenance tasks (current month)
$s = safeQuery($conn, "SELECT COUNT(*) FROM maintenance WHERE MONTH(date_performed) = MONTH(CURDATE()) AND YEAR(date_performed) = YEAR(CURDATE())");
$totalSystemUpdates = $s ? (int)$s->fetchColumn() : 0;

// 5. Most common update type (current month)
$s = safeQuery($conn, "SELECT 
    CASE 
        WHEN new_ram > old_ram AND new_storage > old_storage THEN 'RAM + Storage'
        WHEN new_ram > old_ram THEN 'RAM Upgrade'
        WHEN new_storage > old_storage THEN 'Storage Upgrade'
        WHEN new_graphics != old_graphics AND old_graphics IS NOT NULL AND new_graphics IS NOT NULL THEN 'Graphics Upgrade'
        ELSE 'Other'
    END as update_type, 
    COUNT(*) as count 
    FROM maintenance 
    WHERE performed_by = ? AND MONTH(date_performed) = MONTH(CURDATE()) AND YEAR(date_performed) = YEAR(CURDATE())
    GROUP BY update_type 
    ORDER BY count DESC LIMIT 1", [$user_id]);
$mostCommonUpdate = 'N/A';
$mostCommonCount = 0;
if ($s && $row = $s->fetch(PDO::FETCH_ASSOC)) {
    $mostCommonUpdate = $row['update_type'];
    $mostCommonCount = (int)$row['count'];
}

// 6. Device with most updates (by this user, current month)
$s = safeQuery($conn, "SELECT device_serial, COUNT(*) as count FROM maintenance WHERE performed_by = ? AND MONTH(date_performed) = MONTH(CURDATE()) AND YEAR(date_performed) = YEAR(CURDATE()) GROUP BY device_serial ORDER BY count DESC LIMIT 1", [$user_id]);
$mostUpdatedDevice = '';
$mostUpdatedDeviceCount = 0;
if ($s && $row = $s->fetch(PDO::FETCH_ASSOC)) {
    $mostUpdatedDevice = $row['device_serial'];
    $mostUpdatedDeviceCount = (int)$row['count'];
}

// 7. Average updates per day (current month)
$s = safeQuery($conn, "SELECT COUNT(*) / DAY(LAST_DAY(CURDATE())) as avg_per_day FROM maintenance WHERE performed_by = ? AND MONTH(date_performed) = MONTH(CURDATE()) AND YEAR(date_performed) = YEAR(CURDATE())", [$user_id]);
$avgUpdatesPerDay = 0;
if ($s && $row = $s->fetch(PDO::FETCH_ASSOC)) {
    $avgUpdatesPerDay = round($row['avg_per_day'] ?? 0, 1);
}

// ============================================================
// RECENT UPDATES (LIMIT 10)
// ============================================================
$s = safeQuery($conn, "SELECT m.*, d.model_name, d.branch 
    FROM maintenance m 
    LEFT JOIN devices d ON m.device_serial = d.serial_number 
    WHERE m.performed_by = ? 
    ORDER BY m.date_performed DESC 
    LIMIT 10", [$user_id]);
$myRecentUpdates = $s ? $s->fetchAll(PDO::FETCH_ASSOC) : [];

// ============================================================
// TIME GREETING
// ============================================================
date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';

// Format numbers
$myTotalUpdatesFormatted = number_format($myTotalUpdates);
$myTodayUpdatesFormatted = number_format($myTodayUpdates);
$thisWeekUpdatesFormatted = number_format($thisWeekUpdates);
$totalSystemUpdatesFormatted = number_format($totalSystemUpdates);
$avgUpdatesPerDayFormatted = number_format($avgUpdatesPerDay, 1);

// Current month name
$currentMonth = date('F Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=yes">
    <title>Software Dashboard | Mombasa Computers</title>
    <style>
    :root {
        --primary: #1a4b2a;
        --primary-light: #2a6b3a;
        --primary-dark: #0f3a1e;
        --secondary: #1a4f6e;
        --secondary-light: #2a6f94;
        --secondary-dark: #0f3a4e;
        --accent: #f59e0b;
        --accent-light: #fbbf24;
        --accent-dark: #d97706;
        --success: #059669;
        --warning: #d97706;
        --danger: #dc2626;
        --info: #2563eb;
        
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
        --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        
        --radius-sm: 0.375rem;
        --radius-md: 0.5rem;
        --radius-lg: 0.75rem;
        --radius-xl: 1rem;
        
        --font-sans: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
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
        position: relative;
    }

    .header-row { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        gap: 1.5rem; 
        margin-bottom: 2rem; 
        background: white;
        padding: 1.25rem 2rem;
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
        flex-wrap: wrap;
    }

    .page-title { 
        font-size: 2rem; 
        color: var(--primary-dark); 
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.2;
    }

    .welcome-text {
        font-size: 0.95rem;
        color: var(--gray-500);
        margin-top: 0.25rem;
    }

    .month-badge {
        display: inline-block;
        background: var(--primary);
        color: white;
        padding: 0.15rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        margin-left: 0.5rem;
    }

    .logo img {
        height: 48px;
        width: auto;
        filter: brightness(0.95);
        max-width: 100%;
    }

    .card-row { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem; 
        margin-bottom: 1.5rem; 
    }

    .card { 
        padding: 1.25rem 1.5rem; 
        border-radius: var(--radius-xl); 
        color: white; 
        box-shadow: var(--shadow-md);
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
        border: none;
        backdrop-filter: blur(10px);
        min-width: 0;
    }

    .card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
        pointer-events: none;
    }

    .card:hover { 
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }

    .card h3 { 
        margin: 0 0 0.5rem 0; 
        font-size: 0.75rem; 
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        opacity: 0.85;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        flex-wrap: wrap;
    }

    .card .big { 
        font-size: 1.75rem; 
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 0.25rem;
        word-break: break-word;
    }

    .card .small { 
        font-size: 0.75rem; 
        opacity: 0.85;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .card.primary { 
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
    }

    .card.secondary { 
        background: linear-gradient(145deg, var(--secondary), var(--secondary-dark));
    }

    .card.success { 
        background: linear-gradient(145deg, var(--success), #047857);
    }

    .card.warning { 
        background: linear-gradient(145deg, var(--accent), var(--accent-dark));
    }

    .card.info { 
        background: linear-gradient(145deg, var(--info), #1e40af);
    }

    .card.danger { 
        background: linear-gradient(145deg, var(--danger), #b91c1c);
    }

    .card.light { 
        background: white; 
        color: var(--gray-700); 
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow-sm);
    }

    .card.light .big {
        color: var(--primary-dark);
    }

    .card.light h3 {
        color: var(--gray-500);
    }

    .section { 
        margin-bottom: 1.5rem; 
        background: white; 
        padding: 1.5rem; 
        border-radius: var(--radius-xl); 
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
        transition: all 0.2s ease;
        overflow-x: auto;
    }

    .section:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--gray-300);
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .section h4 { 
        margin: 0; 
        color: var(--gray-800); 
        font-size: 1.1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        letter-spacing: -0.01em;
        flex-wrap: wrap;
    }

    .section h4 i {
        color: var(--primary);
        font-size: 1.2rem;
    }

    .section h4 .badge-count {
        background: var(--primary);
        color: white;
        padding: 0.15rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 500;
        margin-left: 0.5rem;
    }

    .view-all-link {
        color: var(--primary);
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.8rem;
        border-radius: var(--radius-md);
        transition: all 0.2s ease;
    }

    .view-all-link:hover {
        background: var(--gray-50);
        text-decoration: underline;
    }

    .view-all-link i {
        font-size: 0.75rem;
    }

    .table-responsive {
        overflow-x: auto;
        border-radius: var(--radius-lg);
        -webkit-overflow-scrolling: touch;
        width: 100%;
    }

    .table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 0.9rem;
        min-width: 700px;
    }

    .table th { 
        padding: 0.7rem 0.8rem; 
        background: var(--gray-50); 
        color: var(--gray-600); 
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--gray-300);
        text-align: left;
        white-space: nowrap;
    }

    .table td { 
        padding: 0.7rem 0.8rem; 
        border-bottom: 1px solid var(--gray-200); 
        color: var(--gray-700);
        vertical-align: middle;
        word-break: break-word;
        font-size: 0.85rem;
    }

    .table tbody tr:hover {
        background-color: var(--gray-50);
    }

    .table code {
        background: var(--gray-100);
        padding: 0.15rem 0.4rem;
        border-radius: var(--radius-sm);
        font-family: monospace;
        font-size: 0.8rem;
        color: var(--primary-dark);
        word-break: break-all;
    }

    .badge {
        display: inline-block;
        padding: 0.2rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 600;
        background: var(--gray-100);
        color: var(--gray-700);
        white-space: nowrap;
    }

    .badge-primary {
        background: var(--primary);
        color: white;
    }

    .badge-success {
        background: var(--success);
        color: white;
    }

    .badge-info {
        background: var(--info);
        color: white;
    }

    .badge-warning {
        background: var(--warning);
        color: white;
    }

    .badge-danger {
        background: var(--danger);
        color: white;
    }

    .trend-up { 
        color: var(--success);
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .link-btn { 
        padding: 0.5rem 1rem; 
        background: var(--primary); 
        color: white !important; 
        border-radius: var(--radius-md); 
        text-decoration: none; 
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-weight: 500;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
    }

    .link-btn:hover {
        background: var(--primary-light);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .link-btn-sm {
        padding: 0.35rem 0.7rem;
        font-size: 0.75rem;
    }

    .empty-state {
        text-align: center;
        padding: 2rem;
        color: var(--gray-500);
    }

    .empty-state i {
        font-size: 2.5rem;
        color: var(--gray-300);
        margin-bottom: 0.5rem;
        display: block;
    }

    footer {
        text-align: center;
        padding: 1.5rem 0 0.5rem;
        margin-top: 1.5rem;
        font-size: 0.85rem;
        color: var(--gray-400);
        border-top: 1px solid var(--gray-200);
    }

    footer span {
        color: var(--primary);
    }

    @keyframes fadeIn {
        from { 
            opacity: 0; 
            transform: translateY(10px); 
        }
        to { 
            opacity: 1; 
            transform: translateY(0); 
        }
    }

    .card, .section, .header-row {
        animation: fadeIn 0.4s ease-out forwards;
    }

    @media (max-width: 1200px) {
        .main-content {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 1.5rem 1rem 1rem !important;
            padding-top: 5rem !important;
        }
        
        .header-row {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 1rem !important;
            padding: 1.25rem !important;
            position: relative;
            padding-right: 70px;
        }
        
        .header-row .logo {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
        }
        
        .page-title {
            font-size: 1.75rem !important;
            width: calc(100% - 60px);
        }
        
        .welcome-text {
            width: calc(100% - 60px);
            font-size: 0.85rem !important;
        }
        
        .card-row {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
            gap: 0.75rem !important;
        }
        
        .section {
            padding: 1.25rem !important;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 1rem 0.75rem 0.75rem !important;
            padding-top: 4.5rem !important;
        }
        
        .page-title {
            font-size: 1.5rem !important;
        }
        
        .logo img {
            height: 40px !important;
        }
        
        .card .big {
            font-size: 1.5rem !important;
        }
        
        .card-row {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        
        .table td,
        .table th {
            padding: 0.5rem !important;
        }
        
        .table {
            min-width: 600px;
        }
        
        .section-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 0.75rem 0.5rem 0.5rem !important;
            padding-top: 4rem !important;
        }
        
        .page-title {
            font-size: 1.25rem !important;
        }
        
        .card-row {
            grid-template-columns: 1fr 1fr !important;
            gap: 0.5rem !important;
        }
        
        .card {
            padding: 0.75rem 1rem !important;
        }
        
        .card .big {
            font-size: 1.25rem !important;
        }
        
        .card h3 {
            font-size: 0.6rem !important;
        }
        
        .card .small {
            font-size: 0.6rem !important;
        }
        
        .table {
            min-width: 500px !important;
        }
        
        .badge {
            font-size: 0.6rem !important;
            padding: 0.15rem 0.4rem !important;
        }
        
        .header-row {
            padding-right: 60px !important;
        }
        
        .header-row .logo img {
            height: 35px !important;
        }
        
        .view-all-link {
            font-size: 0.75rem;
        }
    }

    .text-success { color: var(--success); }
    .text-muted { color: var(--gray-400); }
    .text-warning { color: var(--warning); }
    </style>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include "../includes/sidebar.php" ?>
<div class="main-content">
    <div class="header-row">
        <div>
            <div class="page-title">Software Dashboard</div>
            <div class="welcome-text">
                <i class="fas fa-hand-wave" style="color: var(--accent); margin-right: 0.5rem;"></i>
                <?= safe($greeting) ?>, <?= safe(explode(' ', $user_name)[0]) ?> • <?= date('l, F j, Y') ?>
                <?php if ($user_branch): ?>
                    <span style="margin-left: 1rem;">
                        <i class="fas fa-store"></i> <?= safe($user_branch) ?>
                    </span>
                <?php endif; ?>
                <span class="month-badge">
                    <i class="fas fa-calendar-alt"></i> <?= $currentMonth ?>
                </span>
            </div>
        </div>
        <div class="logo">
            <img src="../assets/MC-LOGO.png" alt="Mombasa Computers" onerror="this.style.display='none'">
        </div>
        <div>
            <a href="../dashboard/softwaredashboard.php" class="link-btn">
                <i class="fas fa-sync-alt"></i> Refresh
            </a>
        </div>
    </div>

    <!-- Software Metrics Cards -->
    <div class="card-row">
        <div class="card primary">
            <h3><i class="fas fa-tasks"></i> This Month</h3>
            <div class="big"><?= $myTotalUpdatesFormatted ?></div>
            <div class="small">Updates in <?= $currentMonth ?></div>
        </div>
        <div class="card success">
            <h3><i class="fas fa-calendar-day"></i> Today</h3>
            <div class="big"><?= $myTodayUpdatesFormatted ?></div>
            <div class="small">
                <span class="trend-up">
                    <i class="fas fa-arrow-up"></i> <?= $myTodayUpdatesFormatted ?> today
                </span>
            </div>
        </div>
        <div class="card info">
            <h3><i class="fas fa-calendar-week"></i> This Week</h3>
            <div class="big"><?= $thisWeekUpdatesFormatted ?></div>
            <div class="small">Updates this week</div>
        </div>
        <div class="card warning">
            <h3><i class="fas fa-chart-bar"></i> Most Common</h3>
            <div class="big" style="font-size: 1.2rem;">
                <?php if ($mostCommonUpdate !== 'N/A'): ?>
                    <?= safe($mostCommonUpdate) ?>
                    <span style="font-size: 0.7rem; opacity: 0.7;">(<?= $mostCommonCount ?>)</span>
                <?php else: ?>
                    N/A
                <?php endif; ?>
            </div>
            <div class="small">Most frequent update type</div>
        </div>
        <div class="card secondary">
            <h3><i class="fas fa-database"></i> System Total</h3>
            <div class="big"><?= $totalSystemUpdatesFormatted ?></div>
            <div class="small">All tasks this month</div>
        </div>
        <div class="card light">
            <h3><i class="fas fa-calculator"></i> Avg / Day</h3>
            <div class="big" style="color: var(--primary);"><?= $avgUpdatesPerDayFormatted ?></div>
            <div class="small">Daily average this month</div>
        </div>
    </div>

    <!-- Most Updated Device -->
    <div class="section" style="padding: 1rem 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <span style="font-size: 0.8rem; color: var(--gray-500); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fas fa-medal" style="color: var(--accent);"></i> Most Updated Device (<?= $currentMonth ?>)
                </span>
                <div style="font-weight: 600; font-size: 1.1rem; margin-top: 0.2rem;">
                    <?php if ($mostUpdatedDevice): ?>
                        <code><?= safe($mostUpdatedDevice) ?></code>
                        <span class="badge badge-primary" style="margin-left: 0.5rem;"><?= $mostUpdatedDeviceCount ?> updates</span>
                    <?php else: ?>
                        <span class="text-muted">No updates this month</span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="../software/update_specs.php" class="link-btn link-btn-sm">
                <i class="fas fa-plus"></i> New Update
            </a>
        </div>
    </div>

    <!-- Recent Updates -->
    <div class="section">
        <div class="section-header">
            <h4>
                <i class="fas fa-history"></i>
                Recent Updates
                <span class="badge-count"><?= count($myRecentUpdates) ?></span>
            </h4>
            <a href="../software/software_logs.php" class="view-all-link">
                <i class="fas fa-arrow-right"></i> View All
            </a>
        </div>
        <div class="table-responsive">
            <?php if(!empty($myRecentUpdates)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Serial</th>
                            <th>Model</th>
                            <th>Branch</th>
                            <th>Changes</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach($myRecentUpdates as $u): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><code><?= safe($u['device_serial']) ?></code></td>
                                <td><strong><?= safe($u['model_name'] ?? '-') ?></strong></td>
                                <td><span class="badge"><?= safe($u['branch'] ?? 'N/A') ?></span></td>
                                <td>
                                    <?php if (($u['new_ram'] ?? 0) > ($u['old_ram'] ?? 0)): ?>
                                        <span class="badge badge-success">RAM: <?= safe($u['old_ram'] ?? 'N/A') ?>GB → <?= safe($u['new_ram'] ?? 'N/A') ?>GB</span>
                                    <?php endif; ?>
                                    <?php if (($u['new_storage'] ?? 0) > ($u['old_storage'] ?? 0)): ?>
                                        <span class="badge badge-info" style="margin-top: 0.2rem;">Storage: <?= safe($u['old_storage'] ?? 'N/A') ?>GB → <?= safe($u['new_storage'] ?? 'N/A') ?>GB</span>
                                    <?php endif; ?>
                                    <?php if (!empty($u['notes'])): ?>
                                        <span class="badge badge-warning" style="margin-top: 0.2rem;">
                                            <i class="fas fa-sticky-note"></i> <?= safe(substr($u['notes'], 0, 30)) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (($u['new_ram'] ?? 0) <= ($u['old_ram'] ?? 0) && ($u['new_storage'] ?? 0) <= ($u['old_storage'] ?? 0) && empty($u['notes'])): ?>
                                        <span class="badge">No significant changes</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M j, Y H:i', strtotime($u['date_performed'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No updates performed yet.</p>
                    <p style="font-size: 0.85rem; margin-top: 0.5rem; color: var(--gray-400);">
                        <a href="../software/update_specs.php" style="color: var(--primary); text-decoration: none;">
                            <i class="fas fa-plus-circle"></i> Perform your first update
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Action Links -->
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 0.5rem;">
        <a href="../software/update_specs.php" class="link-btn">
            <i class="fas fa-microchip"></i> Update Device Specs
        </a>
        <a href="../software/software_logs.php" class="link-btn link-btn-sm">
            <i class="fas fa-history"></i> View All History
        </a>
        <a href="../search/search_device.php" class="link-btn link-btn-sm">
            <i class="fas fa-search"></i> Search Device
        </a>
    </div>

    <footer>
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> <span>Mombasa Computers</span>. All rights reserved. 
        <span style="margin: 0 0.5rem;">•</span> 
        <span>v2.0.0</span>
    </footer>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function adjustDashboardForMobile() {
        const mainContent = document.querySelector('.main-content');
        const sidebar = document.querySelector('.sidebar');
        
        if (window.innerWidth <= 1200) {
            if (mainContent) {
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
                mainContent.style.paddingTop = '5rem';
                mainContent.style.overflowX = 'hidden';
            }
            if (sidebar && !sidebar.classList.contains('active')) {
                document.body.style.overflow = 'auto';
            }
        } else {
            if (mainContent && sidebar) {
                mainContent.style.marginLeft = '260px';
                mainContent.style.width = 'calc(100% - 260px)';
                mainContent.style.paddingTop = '';
                mainContent.style.overflowX = '';
            }
        }
    }
    
    adjustDashboardForMobile();
    window.addEventListener('resize', adjustDashboardForMobile);
    window.addEventListener('orientationchange', function() {
        setTimeout(adjustDashboardForMobile, 100);
    });
    
    window.addEventListener('sidebarToggled', adjustDashboardForMobile);
    
    const originalToggle = window.toggleSidebar;
    if (originalToggle) {
        window.toggleSidebar = function() {
            originalToggle();
            setTimeout(() => {
                window.dispatchEvent(new Event('sidebarToggled'));
            }, 300);
        };
    }
});
</script>

<?php require_once "../includes/footer.php"; ?>
</body>
</html>