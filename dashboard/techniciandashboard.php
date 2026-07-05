<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// STRICT ROLE CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    die("ACCESS DENIED: You do not have permission to access this page.");
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function secureQuery($conn, $sql, $params = []) {
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

// ========== TECHNICIAN STATISTICS (CURRENT MONTH) ==========

// 1. Total completed repairs this month (by this technician)
$s = secureQuery($conn, "SELECT COUNT(*) FROM repairs WHERE added_by = ? AND fix_status = 'Fixed' AND MONTH(date_fixed) = MONTH(CURDATE()) AND YEAR(date_fixed) = YEAR(CURDATE())", [$user_id]);
$techTotalRepairs = $s ? (int)$s->fetchColumn() : 0;

// 2. Today's completed repairs
$s = secureQuery($conn, "SELECT COUNT(*) FROM repairs WHERE added_by = ? AND fix_status = 'Fixed' AND DATE(date_fixed) = CURDATE()", [$user_id]);
$techTodayRepairs = $s ? (int)$s->fetchColumn() : 0;

// 3. Pending repairs (by this technician) - Using 'pending' status
$s = secureQuery($conn, "SELECT COUNT(*) FROM repairs WHERE added_by = ? AND fix_status = 'pending'", [$user_id]);
$techPendingRepairs = $s ? (int)$s->fetchColumn() : 0;

// 4. Success rate (lifetime)
$s = secureQuery($conn, "SELECT 
    COUNT(CASE WHEN fix_status = 'Fixed' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as success_rate 
    FROM repairs WHERE added_by = ?", [$user_id]);
$myRepairSuccessRate = 0;
if ($s && $row = $s->fetch(PDO::FETCH_ASSOC)) {
    $myRepairSuccessRate = round($row['success_rate'] ?? 0, 1);
}

// 5. Average repair time (in hours)
$s = secureQuery($conn, "SELECT AVG(TIMESTAMPDIFF(HOUR, date_added, date_fixed)) as avg_hours 
    FROM repairs WHERE added_by = ? AND fix_status = 'Fixed' AND date_fixed IS NOT NULL", [$user_id]);
$avgRepairTime = 0;
if ($s && $row = $s->fetch(PDO::FETCH_ASSOC)) {
    $avgRepairTime = round($row['avg_hours'] ?? 0, 1);
}

// 6. Most common issue
$s = secureQuery($conn, "SELECT problem_description, COUNT(*) as count FROM repairs WHERE added_by = ? GROUP BY problem_description ORDER BY count DESC LIMIT 1", [$user_id]);
$mostCommonIssue = '';
$mostCommonIssueCount = 0;
if ($s && $row = $s->fetch(PDO::FETCH_ASSOC)) {
    $mostCommonIssue = $row['problem_description'] ?? '';
    $mostCommonIssueCount = (int)($row['count'] ?? 0);
}

// 7. This month's repairs (fixed this month)
$s = secureQuery($conn, "SELECT COUNT(*) FROM repairs WHERE added_by = ? AND fix_status = 'Fixed' AND MONTH(date_fixed) = MONTH(CURDATE()) AND YEAR(date_fixed) = YEAR(CURDATE())", [$user_id]);
$thisMonthRepairs = $s ? (int)$s->fetchColumn() : 0;

// 8. Total system repairs this month
$s = secureQuery($conn, "SELECT COUNT(*) FROM repairs WHERE fix_status = 'Fixed' AND MONTH(date_fixed) = MONTH(CURDATE()) AND YEAR(date_fixed) = YEAR(CURDATE())");
$totalSystemRepairs = $s ? (int)$s->fetchColumn() : 0;

// 9. System pending repairs - Using 'pending' status
$s = secureQuery($conn, "SELECT COUNT(*) FROM repairs WHERE fix_status = 'pending'");
$systemPendingRepairs = $s ? (int)$s->fetchColumn() : 0;

// 10. Most common issue (system-wide)
$s = secureQuery($conn, "SELECT problem_description, COUNT(*) as count FROM repairs GROUP BY problem_description ORDER BY count DESC LIMIT 1");
$systemMostCommonIssue = '';
if ($s && $row = $s->fetch(PDO::FETCH_ASSOC)) {
    $systemMostCommonIssue = $row['problem_description'] ?? '';
}

// 11. Device with most repairs (by this technician)
$s = secureQuery($conn, "SELECT serial_number, COUNT(*) as count FROM repairs WHERE added_by = ? GROUP BY serial_number ORDER BY count DESC LIMIT 1", [$user_id]);
$mostRepairedDevice = '';
$mostRepairedDeviceCount = 0;
if ($s && $row = $s->fetch(PDO::FETCH_ASSOC)) {
    $mostRepairedDevice = $row['serial_number'] ?? '';
    $mostRepairedDeviceCount = (int)($row['count'] ?? 0);
}

// ========== RECENT REPAIRS (THIS MONTH) ==========
$s = secureQuery($conn, "SELECT r.*, d.model_name 
    FROM repairs r 
    LEFT JOIN devices d ON r.serial_number COLLATE utf8mb4_general_ci = d.serial_number 
    WHERE r.added_by = ? 
    AND MONTH(r.date_added) = MONTH(CURDATE()) 
    AND YEAR(r.date_added) = YEAR(CURDATE())
    ORDER BY r.date_added DESC 
    LIMIT 10", [$user_id]);
$recentRepairs = $s ? $s->fetchAll(PDO::FETCH_ASSOC) : [];

// ========== PENDING REPAIRS ==========
$s = secureQuery($conn, "SELECT r.*, d.model_name 
    FROM repairs r 
    LEFT JOIN devices d ON r.serial_number COLLATE utf8mb4_general_ci = d.serial_number 
    WHERE r.added_by = ? 
    AND r.fix_status = 'pending' 
    ORDER BY r.date_added ASC 
    LIMIT 10", [$user_id]);
$pendingRepairs = $s ? $s->fetchAll(PDO::FETCH_ASSOC) : [];

// ========== WEEKLY REPAIR TREND (LAST 7 DAYS) ==========
$chartLabels = [];
$chartData = [];
$maxChartValue = 1;

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('D', strtotime($date));
    
    $s = secureQuery($conn, "SELECT COUNT(*) FROM repairs WHERE added_by = ? AND fix_status = 'Fixed' AND DATE(date_fixed) = ?", [$user_id, $date]);
    $dailyCount = $s ? (int)$s->fetchColumn() : 0;
    $chartData[] = $dailyCount;
    if ($dailyCount > $maxChartValue) $maxChartValue = $dailyCount;
}
if ($maxChartValue == 0) $maxChartValue = 1;

// Get current time greeting
date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';

// Format numbers for display
$techTotalRepairsFormatted = number_format($techTotalRepairs);
$techTodayRepairsFormatted = number_format($techTodayRepairs);
$techPendingRepairsFormatted = number_format($techPendingRepairs);
$myRepairSuccessRateFormatted = number_format($myRepairSuccessRate, 1);
$avgRepairTimeFormatted = number_format($avgRepairTime, 1);
$thisMonthRepairsFormatted = number_format($thisMonthRepairs);
$totalSystemRepairsFormatted = number_format($totalSystemRepairs);
$systemPendingRepairsFormatted = number_format($systemPendingRepairs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=yes">
    <title>Technician Dashboard | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
            --primary-dark: #0f3a1e;
            --secondary: #1a4f6e;
            --accent: #f59e0b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --purple: #8b5cf6;
            --pink: #ec4899;
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
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --font-sans: 'Inter', system-ui, sans-serif;
        }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; min-height: 100vh; display: flex; flex-direction: column; }
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: all 0.3s ease; flex: 1; }
        .header-row { display: flex; justify-content: space-between; align-items: center; gap: 1.5rem; margin-bottom: 2rem; background: white; padding: 1.25rem 2rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); flex-wrap: wrap; }
        .page-title { font-size: 2rem; color: var(--primary-dark); font-weight: 700; }
        .welcome-text { font-size: 0.95rem; color: var(--gray-500); margin-top: 0.25rem; }
        .logo img { height: 48px; width: auto; max-width: 100%; }
        
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: white; border-radius: var(--radius-lg); padding: 1rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); transition: all 0.3s ease; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .stat-card:nth-child(1)::before { background: linear-gradient(90deg, var(--success), #34d399); }
        .stat-card:nth-child(2)::before { background: linear-gradient(90deg, var(--info), #60a5fa); }
        .stat-card:nth-child(3)::before { background: linear-gradient(90deg, var(--warning), #fbbf24); }
        .stat-card:nth-child(4)::before { background: linear-gradient(90deg, var(--purple), #a78bfa); }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-card .stat-icon { font-size: 1.5rem; margin-bottom: 0.25rem; }
        .stat-card .stat-value { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.1rem; }
        .stat-card .stat-label { font-size: 0.7rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.3px; }
        .stat-card .stat-sub { font-size: 0.65rem; color: var(--gray-400); margin-top: 0.15rem; }
        .stat-card:nth-child(1) .stat-icon, .stat-card:nth-child(1) .stat-value { color: var(--success); }
        .stat-card:nth-child(2) .stat-icon, .stat-card:nth-child(2) .stat-value { color: var(--info); }
        .stat-card:nth-child(3) .stat-icon, .stat-card:nth-child(3) .stat-value { color: var(--warning); }
        .stat-card:nth-child(4) .stat-icon, .stat-card:nth-child(4) .stat-value { color: var(--purple); }

        .stats-row-extra { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card-extra { background: white; border-radius: var(--radius-lg); padding: 0.75rem 1rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); display: flex; align-items: center; gap: 0.75rem; transition: all 0.3s ease; }
        .stat-card-extra:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-card-extra .icon { font-size: 1.25rem; color: var(--primary); width: 36px; text-align: center; }
        .stat-card-extra .info { flex: 1; }
        .stat-card-extra .info .label { font-size: 0.65rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.3px; }
        .stat-card-extra .info .value { font-size: 1rem; font-weight: 600; color: var(--gray-800); }

        .section { margin-bottom: 1.5rem; background: white; padding: 1.25rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); overflow-x: auto; }
        .section h4 { margin: 0 0 1rem 0; color: var(--gray-800); font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; border-left: 3px solid var(--primary); padding-left: 0.75rem; }
        .section h4 i { color: var(--primary); font-size: 1.1rem; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; }
        .view-all-link { color: var(--primary); text-decoration: none; font-size: 0.75rem; font-weight: 500; display: inline-flex; align-items: center; gap: 0.25rem; }
        .view-all-link:hover { text-decoration: underline; }
        .table-responsive { overflow-x: auto; width: 100%; }
        .table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .table th { padding: 0.5rem 0.5rem; background: var(--gray-50); color: var(--gray-600); font-weight: 600; font-size: 0.65rem; text-transform: uppercase; border-bottom: 1px solid var(--gray-200); text-align: left; }
        .table td { padding: 0.5rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); vertical-align: middle; font-size: 0.85rem; }
        .table code { background: var(--gray-100); padding: 0.15rem 0.4rem; border-radius: var(--radius-sm); font-family: monospace; font-size: 0.75rem; }
        .badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 500; white-space: nowrap; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fed7aa; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: #dcfce7; color: #166534; }
        .link-btn { padding: 0.4rem 0.8rem; background: var(--primary); color: white !important; border-radius: var(--radius-md); text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 500; font-size: 0.85rem; transition: all 0.2s ease; border: none; cursor: pointer; }
        .link-btn:hover { background: var(--primary-light); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .link-btn-sm { padding: 0.25rem 0.6rem; font-size: 0.75rem; }
        .text-success { color: var(--success); }
        .text-warning { color: var(--warning); }
        .text-danger { color: var(--danger); }
        .text-muted { color: var(--gray-400); }
        .status-indicator { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 0.4rem; }
        .status-pending { background: var(--warning); }
        .status-fixed { background: var(--success); }
        .chart-container { margin-top: 0.5rem; }
        .chart-bars { display: flex; align-items: flex-end; justify-content: space-between; gap: 0.5rem; height: 100px; margin: 0.5rem 0; flex-wrap: nowrap; }
        .chart-bar-wrapper { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.2rem; min-width: 30px; }
        .chart-bar { width: 100%; max-width: 40px; background: linear-gradient(180deg, var(--primary-light) 0%, var(--primary) 100%); border-radius: var(--radius-sm) var(--radius-sm) 0 0; transition: height 0.3s ease; min-height: 5px; margin: 0 auto; }
        .chart-label { font-size: 0.6rem; color: var(--gray-500); text-align: center; }
        .chart-value { font-size: 0.6rem; font-weight: 600; color: var(--primary-dark); text-align: center; white-space: nowrap; }
        .two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
        footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.8rem; color: var(--gray-500); border-top: 1px solid var(--gray-200); }
        .actions-row { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 0.5rem; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .stats-row, .stats-row-extra, .section, .header-row { animation: fadeIn 0.4s ease-out forwards; }

        @media (max-width: 1200px) {
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; }
            .header-row { flex-direction: column; align-items: flex-start; position: relative; padding-right: 70px !important; }
            .header-row .logo { position: absolute; top: 1.25rem; right: 1.25rem; }
            .page-title { font-size: 1.75rem !important; width: calc(100% - 60px); }
            .stats-row { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .stats-row-extra { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .two-column { grid-template-columns: 1fr; gap: 1rem; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem 0.75rem 0.75rem !important; padding-top: 4.5rem !important; }
            .page-title { font-size: 1.5rem !important; }
            .logo img { height: 40px !important; }
            .stats-row { grid-template-columns: 1fr 1fr; gap: 0.5rem; }
            .stats-row-extra { grid-template-columns: 1fr 1fr; gap: 0.5rem; }
            .stat-card { padding: 0.75rem; }
            .stat-card .stat-value { font-size: 1.2rem; }
            .stat-card-extra { padding: 0.5rem 0.75rem; }
            .table th, .table td { padding: 0.35rem 0.35rem; font-size: 0.75rem; }
            .chart-bars { height: 80px; }
            .two-column { grid-template-columns: 1fr; gap: 0.75rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .header-row { padding: 0.75rem !important; padding-right: 60px !important; }
            .page-title { font-size: 1.25rem !important; width: calc(100% - 50px) !important; }
            .stats-row { grid-template-columns: 1fr 1fr; gap: 0.4rem; }
            .stats-row-extra { grid-template-columns: 1fr; gap: 0.4rem; }
            .stat-card .stat-value { font-size: 1rem; }
            .table { min-width: 400px; }
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="header-row">
        <div>
            <div class="page-title">Technician Dashboard</div>
            <div class="welcome-text"><i class="fas fa-hand-wave" style="color: var(--accent);"></i> <?= safe($greeting) ?>, <?= safe(explode(' ', $user_name)[0]) ?> • <?= date('l, F j, Y') ?></div>
        </div>
        <div class="logo"><img src="/inventory_system/assets/MC-LOGO.png" alt="Mombasa Computers" onerror="this.style.display='none'"></div>
        <div><a href="/inventory_system/dashboard/techniciandashboard.php" class="link-btn"><i class="fas fa-sync-alt"></i> Refresh</a></div>
    </div>

    <!-- Main Stats Row - 4 Cards -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?= $techTotalRepairsFormatted ?></div>
            <div class="stat-label">This Month Repairs</div>
            <div class="stat-sub">Completed this month</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-value"><?= $techTodayRepairsFormatted ?></div>
            <div class="stat-label">Today's Repairs</div>
            <div class="stat-sub">Fixed today</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-value"><?= $techPendingRepairsFormatted ?></div>
            <div class="stat-label">Pending Repairs</div>
            <div class="stat-sub">Awaiting completion</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-percent"></i></div>
            <div class="stat-value"><?= $myRepairSuccessRateFormatted ?>%</div>
            <div class="stat-label">Success Rate</div>
            <div class="stat-sub"><?= $avgRepairTimeFormatted ?> hrs avg time</div>
        </div>
    </div>

    <!-- Extra Stats Row - 4 Cards -->
    <div class="stats-row-extra">
        <div class="stat-card-extra">
            <div class="icon"><i class="fas fa-tools" style="color: var(--primary);"></i></div>
            <div class="info">
                <div class="label">Your Contribution</div>
                <div class="value"><?= $totalSystemRepairs > 0 ? round(($techTotalRepairs / $totalSystemRepairs) * 100, 1) : 0 ?>% of repairs</div>
            </div>
        </div>
        <div class="stat-card-extra">
            <div class="icon"><i class="fas fa-wrench" style="color: var(--warning);"></i></div>
            <div class="info">
                <div class="label">Most Repaired Device</div>
                <div class="value"><?= !empty($mostRepairedDevice) ? safe($mostRepairedDevice) . ' (' . $mostRepairedDeviceCount . 'x)' : 'None yet' ?></div>
            </div>
        </div>
        <div class="stat-card-extra">
            <div class="icon"><i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i></div>
            <div class="info">
                <div class="label">Most Common Issue</div>
                <div class="value"><?= !empty($mostCommonIssue) ? safe(substr($mostCommonIssue, 0, 30)) . ($mostCommonIssueCount > 1 ? ' (' . $mostCommonIssueCount . 'x)' : '') : 'None yet' ?></div>
            </div>
        </div>
        <div class="stat-card-extra">
            <div class="icon"><i class="fas fa-globe" style="color: var(--info);"></i></div>
            <div class="info">
                <div class="label">System Total (Month)</div>
                <div class="value"><?= $totalSystemRepairsFormatted ?> repairs</div>
            </div>
        </div>
    </div>

    <!-- Weekly Repair Trend Chart -->
    <div class="section">
        <h4><i class="fas fa-chart-bar"></i> Weekly Repair Trend (Last 7 Days)</h4>
        <div class="chart-container">
            <div class="chart-bars">
                <?php foreach ($chartData as $index => $value): ?>
                <div class="chart-bar-wrapper">
                    <div class="chart-value"><?= $value ?></div>
                    <div class="chart-bar" style="height: <?= max(10, ($value / $maxChartValue) * 80) ?>px;"></div>
                    <div class="chart-label"><?= safe($chartLabels[$index] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Two Column: Pending Repairs & Recent Repairs -->
    <div class="two-column">
        <div class="section" style="margin-bottom:0">
            <div class="flex-between">
                <h4><i class="fas fa-clock" style="color: var(--warning);"></i> Pending Repairs</h4>
                <?php if (!empty($pendingRepairs)): ?>
                <span class="badge badge-warning"><?= count($pendingRepairs) ?></span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Serial</th>
                            <th>Model</th>
                            <th>Client</th>
                            <th>Issue</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($pendingRepairs)): ?>
                            <?php foreach($pendingRepairs as $r): ?>
                            <tr>
                                <td><code><?= safe($r['serial_number'] ?? '') ?></code></td>
                                <td><?= safe($r['model_name'] ?? '-') ?></td>
                                <td><?= safe($r['client_name'] ?? 'N/A') ?></td>
                                <td><?= safe(substr($r['problem_description'] ?? '-', 0, 50)) ?></td>
                                <td><?= date('M j, Y', strtotime($r['date_added'] ?? 'now')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-muted" style="text-align:center; padding:1.5rem;">No pending repairs</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:0.5rem;">
                <a href="/inventory_system/repairs/under_repair.php" class="view-all-link">View All Pending <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="section" style="margin-bottom:0">
            <div class="flex-between">
                <h4><i class="fas fa-history"></i> Recent Repairs (This Month)</h4>
                <?php if (!empty($recentRepairs)): ?>
                <span class="badge badge-info"><?= count($recentRepairs) ?></span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Serial</th>
                            <th>Model</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($recentRepairs)): ?>
                            <?php foreach($recentRepairs as $r): ?>
                            <tr>
                                <td><code><?= safe($r['serial_number'] ?? '') ?></code></td>
                                <td><?= safe($r['model_name'] ?? '-') ?></td>
                                <td><?= safe($r['client_name'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (($r['fix_status'] ?? '') === 'Fixed'): ?>
                                        <span class="status-indicator status-fixed"></span>
                                        <span class="text-success">Fixed</span>
                                    <?php elseif (($r['fix_status'] ?? '') === 'pending'): ?>
                                        <span class="status-indicator status-pending"></span>
                                        <span class="text-warning">Pending</span>
                                    <?php else: ?>
                                        <span class="status-indicator status-pending"></span>
                                        <span class="text-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M j, Y', strtotime($r['date_added'] ?? 'now')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-muted" style="text-align:center; padding:1.5rem;">No recent repairs</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:0.5rem;">
                <a href="/inventory_system/repairs/repair_logs.php" class="view-all-link">View All Repairs <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <!-- System Stats -->
    <div class="section">
        <h4><i class="fas fa-globe"></i> System Overview (This Month)</h4>
        <div class="stats-row-extra" style="grid-template-columns: repeat(4, 1fr); margin-bottom:0;">
            <div class="stat-card-extra">
                <div class="icon"><i class="fas fa-tools" style="color: var(--success);"></i></div>
                <div class="info">
                    <div class="label">Total System Repairs</div>
                    <div class="value"><?= $totalSystemRepairsFormatted ?></div>
                </div>
            </div>
            <div class="stat-card-extra">
                <div class="icon"><i class="fas fa-clock" style="color: var(--warning);"></i></div>
                <div class="info">
                    <div class="label">System Pending</div>
                    <div class="value"><?= $systemPendingRepairsFormatted ?></div>
                </div>
            </div>
            <div class="stat-card-extra">
                <div class="icon"><i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i></div>
                <div class="info">
                    <div class="label">System Common Issue</div>
                    <div class="value"><?= !empty($systemMostCommonIssue) ? safe(substr($systemMostCommonIssue, 0, 30)) : 'None' ?></div>
                </div>
            </div>
            <div class="stat-card-extra">
                <div class="icon"><i class="fas fa-user" style="color: var(--primary);"></i></div>
                <div class="info">
                    <div class="label">Your Contribution</div>
                    <div class="value"><?= $totalSystemRepairs > 0 ? round(($techTotalRepairs / $totalSystemRepairs) * 100, 1) : 0 ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action Buttons -->
    <div class="actions-row">
        <a href="/inventory_system/repairs/add_repair.php" class="link-btn"><i class="fas fa-plus-circle"></i> Add New Repair</a>
        <a href="/inventory_system/repairs/repair_logs.php" class="link-btn"><i class="fas fa-list"></i> View All Repairs</a>
        <a href="/inventory_system/repairs/under_repair.php" class="link-btn link-btn-sm"><i class="fas fa-user-cog"></i> My Pending Repairs</a>
    </div>

    <footer>
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers. All rights reserved. 
        <span style="margin:0 0.5rem">•</span> 
        <span>v2.0.0</span>
    </footer>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function adjustMobile() {
        const main = document.querySelector('.main-content');
        const sidebar = document.querySelector('.sidebar');
        if (window.innerWidth <= 1200) {
            if (main) { main.style.marginLeft = '0'; main.style.width = '100%'; main.style.paddingTop = '5rem'; }
        } else {
            if (main && sidebar) { main.style.marginLeft = '260px'; main.style.width = 'calc(100% - 260px)'; main.style.paddingTop = ''; }
        }
    }
    adjustMobile();
    window.addEventListener('resize', adjustMobile);
    window.addEventListener('sidebarToggled', adjustMobile);
});
</script>

<?php require_once "../includes/footer.php"; ?>
</body>
</html>