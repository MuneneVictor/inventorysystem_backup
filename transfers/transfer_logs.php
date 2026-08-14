<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Load PhpSpreadsheet for export
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Strict role check
if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager', 'cashier'])) {
    die("ACCESS DENIED.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle export request
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Get filters (same as main page)
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $filter_category = $_GET['category'] ?? 'all';
    $filter_branch = $_GET['branch'] ?? 'all';
    $search_query = trim($_GET['search'] ?? '');

    // Build query with filters (same as main page)
    $sql = "SELECT al.*, u.full_name, u.branch as user_branch
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.action LIKE '%transfer%'
            AND al.created_at BETWEEN :start_date AND :end_date";
    $params = [
        'start_date' => $start_date . ' 00:00:00',
        'end_date' => $end_date . ' 23:59:59'
    ];

    // Category filter
    if ($filter_category !== 'all') {
        $catMap = [
            'device' => 'device',
            'monitor' => 'monitor',
            'printer' => 'printer',
            'charger' => 'charger',
            'ram_ssd' => 'ram|ssd|component',
            'smartboard' => 'smartboard',
            'ups' => 'ups',
            'phone' => 'phone',
            'accessory' => 'accessory',
            'graphics' => 'graphic',
            'hdd' => 'hdd'
        ];
        if (isset($catMap[$filter_category])) {
            $sql .= " AND (al.action LIKE :cat_action OR al.details LIKE :cat_details)";
            $params['cat_action'] = "%" . $catMap[$filter_category] . "%";
            $params['cat_details'] = "%" . $catMap[$filter_category] . "%";
        }
    }

    // Branch filter
    if ($filter_branch !== 'all') {
        $sql .= " AND al.details LIKE :branch";
        $params['branch'] = "%$filter_branch%";
    }

    // Search filter
    if (!empty($search_query)) {
        $sql .= " AND (al.details LIKE :search_details OR u.full_name LIKE :search_name)";
        $params['search_details'] = "%$search_query%";
        $params['search_name'] = "%$search_query%";
    }

    $sql .= " ORDER BY al.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($logs)) {
        die("No transfer logs found to export.");
    }

    // Create spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Transfer Logs');

    $row = 1;

    // Title
    $sheet->setCellValue('A' . $row, 'Transfer Logs Report');
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row++;

    // Filter note
    $filterNote = "Filters: Date " . date('M d, Y', strtotime($start_date)) . " – " . date('M d, Y', strtotime($end_date));
    if ($filter_category !== 'all') $filterNote .= " | Category: " . ucfirst(str_replace('_', ' ', $filter_category));
    if ($filter_branch !== 'all') $filterNote .= " | Branch: $filter_branch";
    if (!empty($search_query)) $filterNote .= " | Search: $search_query";
    $sheet->setCellValue('A' . $row, $filterNote);
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
    $row += 2;

    // Headers
    $headers = ['Date', 'Category', 'Action', 'Details', 'User', 'Branch', 'Time'];
    $headerRow = $row;
    foreach ($headers as $idx => $header) {
        $sheet->setCellValue(chr(65 + $idx) . $headerRow, $header);
    }

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A4B2A']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A' . $headerRow . ':G' . $headerRow)->applyFromArray($headerStyle);

    // Data rows
    $dataRow = $headerRow + 1;
    foreach ($logs as $log) {
        $date = date('Y-m-d', strtotime($log['created_at']));
        $time = date('H:i:s', strtotime($log['created_at']));
        // Determine category
        $cat = 'Other';
        $details = $log['details'];
        if (strpos($log['action'], 'device') !== false || stripos($details, 'device') !== false) $cat = 'Device';
        elseif (strpos($log['action'], 'monitor') !== false || stripos($details, 'monitor') !== false) $cat = 'Monitor';
        elseif (strpos($log['action'], 'printer') !== false || stripos($details, 'printer') !== false) $cat = 'Printer';
        elseif (strpos($log['action'], 'charger') !== false || stripos($details, 'charger') !== false) $cat = 'Charger';
        elseif (strpos($log['action'], 'ram') !== false || strpos($log['action'], 'ssd') !== false || stripos($details, 'ram') !== false) $cat = 'RAM/SSD';
        elseif (strpos($log['action'], 'smartboard') !== false || stripos($details, 'smartboard') !== false) $cat = 'Smartboard';
        elseif (strpos($log['action'], 'ups') !== false || stripos($details, 'ups') !== false) $cat = 'UPS';
        elseif (strpos($log['action'], 'phone') !== false || stripos($details, 'phone') !== false) $cat = 'Phone';
        elseif (strpos($log['action'], 'accessory') !== false || stripos($details, 'accessory') !== false) $cat = 'Accessory';
        elseif (strpos($log['action'], 'graphic') !== false || stripos($details, 'graphic') !== false) $cat = 'Graphics';
        elseif (strpos($log['action'], 'hdd') !== false || stripos($details, 'hdd') !== false) $cat = 'HDD';

        $sheet->setCellValue('A' . $dataRow, $date);
        $sheet->setCellValue('B' . $dataRow, $cat);
        $sheet->setCellValue('C' . $dataRow, $log['action']);
        $sheet->setCellValue('D' . $dataRow, $log['details']);
        $sheet->setCellValue('E' . $dataRow, $log['full_name'] ?? 'Unknown');
        $sheet->setCellValue('F' . $dataRow, $log['user_branch'] ?? 'N/A');
        $sheet->setCellValue('G' . $dataRow, $time);
        $dataRow++;
    }

    // Apply borders
    $sheet->getStyle('A' . ($headerRow+1) . ':G' . ($dataRow-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Auto-size columns
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Output file
    $filename = 'Transfer_Logs_' . date('Y-m-d') . '.xlsx';
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Set default date range (last 30 days)
$start_date = date('Y-m-d', strtotime('-30 days'));
$end_date = date('Y-m-d');
$filter_category = 'all';
$filter_branch = 'all';
$search_query = '';

// Get user's current branch
$user_branch = null;
$stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_branch = $stmt->fetchColumn();

// Available branches for filter
$branch_stmt = $conn->query("SELECT DISTINCT branch FROM users WHERE branch IS NOT NULL AND branch != '' ORDER BY branch");
$availableBranches = $branch_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Process filters
if (isset($_GET['filter'])) {
    $start_date = $_GET['start_date'] ?? $start_date;
    $end_date = $_GET['end_date'] ?? $end_date;
    $filter_category = $_GET['category'] ?? 'all';
    $filter_branch = $_GET['branch'] ?? 'all';
    $search_query = trim($_GET['search'] ?? '');
}

// Build query with filters
$sql = "SELECT al.*, u.full_name, u.branch as user_branch
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.action LIKE '%transfer%'
        AND al.created_at BETWEEN :start_date AND :end_date";
$params = [
    'start_date' => $start_date . ' 00:00:00',
    'end_date' => $end_date . ' 23:59:59'
];

// Category filter – expand to all categories
if ($filter_category !== 'all') {
    $catMap = [
        'device' => 'device',
        'monitor' => 'monitor',
        'printer' => 'printer',
        'charger' => 'charger',
        'ram_ssd' => 'ram|ssd|component',
        'smartboard' => 'smartboard',
        'ups' => 'ups',
        'phone' => 'phone',
        'accessory' => 'accessory',
        'graphics' => 'graphic',
        'hdd' => 'hdd'
    ];
    if (isset($catMap[$filter_category])) {
        $sql .= " AND (al.action LIKE :cat_action OR al.details LIKE :cat_details)";
        $params['cat_action'] = "%" . $catMap[$filter_category] . "%";
        $params['cat_details'] = "%" . $catMap[$filter_category] . "%";
    }
}

// Branch filter
if ($filter_branch !== 'all') {
    $sql .= " AND al.details LIKE :branch";
    $params['branch'] = "%$filter_branch%";
}
// Search filter
if (!empty($search_query)) {
    $sql .= " AND (al.details LIKE :search_details OR u.full_name LIKE :search_name)";
    $params['search_details'] = "%$search_query%";
    $params['search_name'] = "%$search_query%";
}
$sql .= " ORDER BY al.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$transferLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics – compute per category
$stats_sql = "SELECT 
    COUNT(*) as total_transfers,
    SUM(CASE WHEN action LIKE '%device%' OR details LIKE '%device%' THEN 1 ELSE 0 END) as device_transfers,
    SUM(CASE WHEN action LIKE '%monitor%' OR details LIKE '%monitor%' THEN 1 ELSE 0 END) as monitor_transfers,
    SUM(CASE WHEN action LIKE '%printer%' OR details LIKE '%printer%' THEN 1 ELSE 0 END) as printer_transfers,
    SUM(CASE WHEN action LIKE '%charger%' OR details LIKE '%charger%' THEN 1 ELSE 0 END) as charger_transfers,
    SUM(CASE WHEN action LIKE '%ram%' OR action LIKE '%ssd%' OR details LIKE '%ram%' OR details LIKE '%ssd%' OR details LIKE '%component%' THEN 1 ELSE 0 END) as ram_ssd_transfers,
    SUM(CASE WHEN action LIKE '%smartboard%' OR details LIKE '%smartboard%' THEN 1 ELSE 0 END) as smartboard_transfers,
    SUM(CASE WHEN action LIKE '%ups%' OR details LIKE '%ups%' THEN 1 ELSE 0 END) as ups_transfers,
    SUM(CASE WHEN action LIKE '%phone%' OR details LIKE '%phone%' THEN 1 ELSE 0 END) as phone_transfers,
    SUM(CASE WHEN action LIKE '%accessory%' OR details LIKE '%accessory%' THEN 1 ELSE 0 END) as accessory_transfers,
    SUM(CASE WHEN action LIKE '%graphic%' OR details LIKE '%graphic%' THEN 1 ELSE 0 END) as graphics_transfers,
    SUM(CASE WHEN action LIKE '%hdd%' OR details LIKE '%hdd%' THEN 1 ELSE 0 END) as hdd_transfers
FROM activity_logs 
WHERE action LIKE '%transfer%'
AND created_at BETWEEN :start_date AND :end_date";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute([
    'start_date' => $start_date . ' 00:00:00',
    'end_date' => $end_date . ' 23:59:59'
]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Greeting
date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Transfer Logs | Mombasa Computers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            --font-sans: 'Inter', system-ui, sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--gray-100); color: var(--gray-800); line-height: 1.5; }
        .main-content { padding: 2rem 2rem 1rem; margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; background: var(--gray-100); transition: all 0.3s ease; }
        .page-header { background: white; padding: 1.5rem 2rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .page-header h1 { font-size: 1.75rem; color: var(--gray-800); font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .page-header h1 i { color: var(--primary); font-size: 1.75rem; }
        .breadcrumb { color: var(--gray-500); font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .filter-container { background: white; padding: 1.5rem; border-radius: var(--radius-xl); margin-bottom: 1.5rem; border: 1px solid var(--gray-200); }
        .filter-row { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .filter-row div { flex: 1; min-width: 180px; }
        .filter-row label { display: block; font-size: 0.8rem; font-weight: 500; color: var(--gray-600); margin-bottom: 0.25rem; }
        input, select, button { width: 100%; padding: 0.6rem 0.8rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: 0.9rem; background: white; }
        button { background: var(--primary); color: white; border: none; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; justify-content: center; }
        button:hover { background: var(--primary-light); }
        .stats-container { display: flex; gap: 0.8rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .stat-card { background: white; padding: 0.75rem 1rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); flex: 1; min-width: 110px; text-align: center; box-shadow: var(--shadow-sm); }
        .stat-card h4 { font-size: 0.7rem; color: var(--gray-500); margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-number { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .stat-card .stat-number.small { font-size: 1.2rem; }
        .action-buttons { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem; }
        .export-btn { background: #28a745; width: auto; color: white; padding: 0.6rem 1.2rem; border-radius: var(--radius-md); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; border: none; cursor: pointer; }
        .export-btn:hover { background: #218838; }
        .table-wrapper { background: white; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; font-size: 0.85rem; }
        th { background: var(--gray-50); padding: 0.8rem 1rem; text-align: left; font-weight: 600; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        td { padding: 0.8rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; white-space: nowrap; }
        .badge-device { background: #4caf50; color: white; }
        .badge-monitor { background: #2196f3; color: white; }
        .badge-printer { background: #ff9800; color: white; }
        .badge-charger { background: #9c27b0; color: white; }
        .badge-ram-ssd { background: #f44336; color: white; }
        .badge-smartboard { background: #e91e63; color: white; }
        .badge-ups { background: #ff5722; color: white; }
        .badge-phone { background: #00bcd4; color: white; }
        .badge-accessory { background: #3f51b5; color: white; }
        .badge-graphics { background: #673ab7; color: white; }
        .badge-hdd { background: #607d8b; color: white; }
        .badge-other { background: #9e9e9e; color: white; }
        .log-details { max-width: 400px; word-wrap: break-word; font-size: 0.8rem; }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-500); }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5; }
        .footer { text-align: center; padding: 1.5rem 0 0.5rem; margin-top: 1.5rem; font-size: 0.85rem; color: var(--gray-400); border-top: 1px solid var(--gray-200); }
        @media (max-width: 1200px) { .main-content { margin-left: 0 !important; width: 100% !important; padding: 1.5rem 1rem 1rem !important; padding-top: 5rem !important; } }
        @media (max-width: 768px) { .filter-row { flex-direction: column; } .stats-container { flex-direction: column; } button { width: 100%; } }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Transfer Logs</h1>
        <div class="breadcrumb">
            <?php if ($user_role === 'super_admin'): ?>
                <a href="../dashboard/superadmindashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'manager'): ?>
                <a href="../dashboard/managerdashboard.php">Dashboard</a>
            <?php else: ?>
                <a href="../dashboard/inventorydashboard.php">Dashboard</a>
            <?php endif; ?>
            <span> / </span>
            <a href="index.php">Transfers</a>
            <span> / </span>
            <span>Transfer Logs</span>
        </div>
    </div>

    <div class="filter-container">
        <form method="GET" id="filterForm">
            <input type="hidden" name="filter" value="1">
            <div class="filter-row">
                <div><label>Start Date</label><input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"></div>
                <div><label>End Date</label><input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"></div>
                <div><label>Category</label>
                    <select name="category">
                        <option value="all" <?= $filter_category == 'all' ? 'selected' : '' ?>>All Categories</option>
                        <option value="device" <?= $filter_category == 'device' ? 'selected' : '' ?>>Devices</option>
                        <option value="monitor" <?= $filter_category == 'monitor' ? 'selected' : '' ?>>Monitors</option>
                        <option value="printer" <?= $filter_category == 'printer' ? 'selected' : '' ?>>Printers</option>
                        <option value="charger" <?= $filter_category == 'charger' ? 'selected' : '' ?>>Chargers</option>
                        <option value="ram_ssd" <?= $filter_category == 'ram_ssd' ? 'selected' : '' ?>>RAM/SSD</option>
                        <option value="smartboard" <?= $filter_category == 'smartboard' ? 'selected' : '' ?>>Smartboards</option>
                        <option value="ups" <?= $filter_category == 'ups' ? 'selected' : '' ?>>UPS</option>
                        <option value="phone" <?= $filter_category == 'phone' ? 'selected' : '' ?>>Phones</option>
                        <option value="accessory" <?= $filter_category == 'accessory' ? 'selected' : '' ?>>Accessories</option>
                        <option value="graphics" <?= $filter_category == 'graphics' ? 'selected' : '' ?>>Graphics Cards</option>
                        <option value="hdd" <?= $filter_category == 'hdd' ? 'selected' : '' ?>>HDDs</option>
                    </select>
                </div>
            </div>
            <div class="filter-row">
                <div><label>Branch</label>
                    <select name="branch">
                        <option value="all" <?= $filter_branch == 'all' ? 'selected' : '' ?>>All Branches</option>
                        <?php foreach ($availableBranches as $branch): ?>
                            <option value="<?= htmlspecialchars($branch) ?>" <?= $filter_branch == $branch ? 'selected' : '' ?>><?= htmlspecialchars($branch) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Search (Details/Name)</label><input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search_query) ?>"></div>
                <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                    <button type="submit"><i class="fas fa-filter"></i> Apply</button>
                    <button type="button" id="autoSubmitBtn" style="background: #17a2b8;"><i class="fas fa-sync-alt"></i> Auto</button>
                    <a href="transfer_logs.php" class="btn-secondary" style="background: var(--gray-500); color: white; padding: 0.6rem 1.2rem; border-radius: var(--radius-md); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500; border: none;"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </div>
        </form>
    </div>

    <div class="stats-container">
        <div class="stat-card"><h4>Total</h4><div class="stat-number"><?= number_format($stats['total_transfers'] ?? 0) ?></div></div>
        <div class="stat-card"><h4>Devices</h4><div class="stat-number small"><?= number_format($stats['device_transfers'] ?? 0) ?></div></div>
        <div class="stat-card"><h4>Monitors</h4><div class="stat-number small"><?= number_format($stats['monitor_transfers'] ?? 0) ?></div></div>
        <div class="stat-card"><h4>Printers</h4><div class="stat-number small"><?= number_format($stats['printer_transfers'] ?? 0) ?></div></div>
        <div class="stat-card"><h4>Chargers</h4><div class="stat-number small"><?= number_format($stats['charger_transfers'] ?? 0) ?></div></div>
        <div class="stat-card"><h4>RAM/SSD</h4><div class="stat-number small"><?= number_format($stats['ram_ssd_transfers'] ?? 0) ?></div></div>
        <div class="stat-card"><h4>Smartboards</h4><div class="stat-number small"><?= number_format($stats['smartboard_transfers'] ?? 0) ?></div></div>
        <div class="stat-card"><h4>UPS</h4><div class="stat-number small"><?= number_format($stats['ups_transfers'] ?? 0) ?></div></div>
        <div class="stat-card"><h4>Phones</h4><div class="stat-number small"><?= number_format($stats['phone_transfers'] ?? 0) ?></div></div>
        <div class="stat-card"><h4>Accessories</h4><div class="stat-number small"><?= number_format($stats['accessory_transfers'] ?? 0) ?></div></div>
        <div class="stat-card"><h4>Graphics</h4><div class="stat-number small"><?= number_format($stats['graphics_transfers'] ?? 0) ?></div></div>
        <div class="stat-card"><h4>HDDs</h4><div class="stat-number small"><?= number_format($stats['hdd_transfers'] ?? 0) ?></div></div>
    </div>

    <div class="action-buttons">
        <div>Showing <?= count($transferLogs) ?> logs from <?= date('M d, Y', strtotime($start_date)) ?> to <?= date('M d, Y', strtotime($end_date)) ?></div>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="export-btn"><i class="fas fa-file-excel"></i> Export to Excel</a>
    </div>

    <div class="table-wrapper">
        <?php if (!empty($transferLogs)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>User</th>
                        <th>Branch</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transferLogs as $log): 
                        $badgeClass = 'badge-other';
                        $category = 'Other';
                        $details = $log['details'];
                        if (strpos($log['action'], 'device') !== false || stripos($details, 'device') !== false) {
                            $badgeClass = 'badge-device'; $category = 'Device';
                        } elseif (strpos($log['action'], 'monitor') !== false || stripos($details, 'monitor') !== false) {
                            $badgeClass = 'badge-monitor'; $category = 'Monitor';
                        } elseif (strpos($log['action'], 'printer') !== false || stripos($details, 'printer') !== false) {
                            $badgeClass = 'badge-printer'; $category = 'Printer';
                        } elseif (strpos($log['action'], 'charger') !== false || stripos($details, 'charger') !== false) {
                            $badgeClass = 'badge-charger'; $category = 'Charger';
                        } elseif (strpos($log['action'], 'ram') !== false || strpos($log['action'], 'ssd') !== false || stripos($details, 'ram') !== false) {
                            $badgeClass = 'badge-ram-ssd'; $category = 'RAM/SSD';
                        } elseif (strpos($log['action'], 'smartboard') !== false || stripos($details, 'smartboard') !== false) {
                            $badgeClass = 'badge-smartboard'; $category = 'Smartboard';
                        } elseif (strpos($log['action'], 'ups') !== false || stripos($details, 'ups') !== false) {
                            $badgeClass = 'badge-ups'; $category = 'UPS';
                        } elseif (strpos($log['action'], 'phone') !== false || stripos($details, 'phone') !== false) {
                            $badgeClass = 'badge-phone'; $category = 'Phone';
                        } elseif (strpos($log['action'], 'accessory') !== false || stripos($details, 'accessory') !== false) {
                            $badgeClass = 'badge-accessory'; $category = 'Accessory';
                        } elseif (strpos($log['action'], 'graphic') !== false || stripos($details, 'graphic') !== false) {
                            $badgeClass = 'badge-graphics'; $category = 'Graphics';
                        } elseif (strpos($log['action'], 'hdd') !== false || stripos($details, 'hdd') !== false) {
                            $badgeClass = 'badge-hdd'; $category = 'HDD';
                        }
                    ?>
                        <tr>
                            <td><?= date('M d, Y H:i', strtotime($log['created_at'])) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $category ?></span></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td class="log-details"><?= nl2br(htmlspecialchars($log['details'])) ?></td>
                            <td><?= htmlspecialchars($log['full_name'] ?? 'Unknown') ?></td>
                            <td><?= htmlspecialchars($log['user_branch'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No transfer logs found for the selected filters.</p>
            </div>
        <?php endif; ?>
    </div>
    <div class="footer"><i class="fas fa-copyright"></i> <?= date('Y'); ?> Mombasa Computers</div>
</div>
<script>
let autoFilterEnabled = false;
let timeout;
function enableAutoFilter() {
    autoFilterEnabled = true;
    document.getElementById('autoSubmitBtn').textContent = 'Auto: ON';
    document.getElementById('autoSubmitBtn').style.background = '#28a745';
    document.querySelectorAll('#filterForm select, #filterForm input').forEach(el => {
        el.addEventListener('change', () => { if (autoFilterEnabled) { clearTimeout(timeout); timeout = setTimeout(() => document.getElementById('filterForm').submit(), 500); } });
        if (el.type === 'text') el.addEventListener('input', () => { if (autoFilterEnabled) { clearTimeout(timeout); timeout = setTimeout(() => document.getElementById('filterForm').submit(), 500); } });
    });
}
function disableAutoFilter() {
    autoFilterEnabled = false;
    document.getElementById('autoSubmitBtn').textContent = 'Auto';
    document.getElementById('autoSubmitBtn').style.background = '#17a2b8';
}
document.getElementById('autoSubmitBtn').addEventListener('click', () => autoFilterEnabled ? disableAutoFilter() : enableAutoFilter());
disableAutoFilter();

// Responsive adjustment
function adjustMainContent() {
    const main = document.querySelector('.main-content');
    if (window.innerWidth <= 1200) main.style.marginLeft = '0';
    else main.style.marginLeft = '260px';
}
window.addEventListener('resize', adjustMainContent);
adjustMainContent();
</script>
<?php require_once "../includes/footer.php"; ?>
</body>
</html>