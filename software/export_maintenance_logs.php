<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Allowed roles: software, super_admin, inventory_admin, manager
if (!in_array($_SESSION['role'], ['software', 'super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED.");
}

$user_role = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];
$params = [];

// Get user's branch if not super_admin
$user_branch = null;
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
}

// Load PhpSpreadsheet
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Helper function to safely escape HTML (for display, not needed in Excel but used for safety)
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Filter inputs (same as software_logs.php)
$filter_serial = trim($_GET['serial'] ?? '');
$filter_start_date = trim($_GET['start_date'] ?? '');
$filter_end_date = trim($_GET['end_date'] ?? '');
$filter_branch = trim($_GET['branch'] ?? '');
$filter_performed_by = trim($_GET['performed_by'] ?? '');

// Build query (same as software_logs.php)
$sql = "SELECT m.*, d.model_name, d.storage_type, d.storage_capacity, 
               c.category_name, u.full_name AS performed_by_name, d.branch
        FROM maintenance m
        LEFT JOIN devices d ON m.device_serial COLLATE utf8mb4_general_ci = d.serial_number
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN users u ON m.performed_by = u.id
        WHERE 1=1";

if ($user_role === 'software') {
    $sql .= " AND m.performed_by = :performed_by";
    $params['performed_by'] = $user_id;
}

if (in_array($user_role, ['manager', 'inventory_admin']) && !empty($user_branch)) {
    $sql .= " AND d.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}

// Serial number filter
if (!empty($filter_serial)) {
    $sql .= " AND m.device_serial LIKE :serial";
    $params['serial'] = "%$filter_serial%";
}

// Performed by filter (only for super_admin)
if ($user_role === 'super_admin' && !empty($filter_performed_by)) {
    $sql .= " AND m.performed_by = :performed_by_id";
    $params['performed_by_id'] = $filter_performed_by;
}

// Branch filter (only for super_admin)
if ($user_role === 'super_admin' && !empty($filter_branch)) {
    $sql .= " AND d.branch = :branch";
    $params['branch'] = $filter_branch;
}

// Date range filter
if (!empty($filter_start_date) && !empty($filter_end_date)) {
    $sql .= " AND DATE(m.date_performed) BETWEEN :start_date AND :end_date";
    $params['start_date'] = $filter_start_date;
    $params['end_date'] = $filter_end_date;
} elseif (!empty($filter_start_date)) {
    $sql .= " AND DATE(m.date_performed) >= :start_date";
    $params['start_date'] = $filter_start_date;
} elseif (!empty($filter_end_date)) {
    $sql .= " AND DATE(m.date_performed) <= :end_date";
    $params['end_date'] = $filter_end_date;
}

$sql .= " ORDER BY m.date_performed DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($logs)) {
    die("No maintenance logs to export.");
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Maintenance Logs');

$row = 1;

// ---- Title ----
$sheet->setCellValue('A' . $row, 'Maintenance Logs Report');
$sheet->mergeCells('A' . $row . ':M' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

// ---- Generated Date ----
$sheet->setCellValue('A' . $row, 'Generated: ' . date('Y-m-d H:i:s'));
$sheet->mergeCells('A' . $row . ':M' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$row++;

// ---- Filter Criteria Note ----
$filterNote = "Filters applied: ";
$criteria = [];
if (!empty($filter_serial)) $criteria[] = "Serial: " . $filter_serial;
if (!empty($filter_performed_by) && $user_role === 'super_admin') {
    $userNameStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $userNameStmt->execute([$filter_performed_by]);
    $userName = $userNameStmt->fetchColumn();
    $criteria[] = "Performed By: " . ($userName ?: $filter_performed_by);
}
if (!empty($filter_branch) && $user_role === 'super_admin') $criteria[] = "Branch: " . $filter_branch;
if (!empty($filter_start_date) && !empty($filter_end_date)) $criteria[] = "Date: " . $filter_start_date . " to " . $filter_end_date;
elseif (!empty($filter_start_date)) $criteria[] = "Date from: " . $filter_start_date;
elseif (!empty($filter_end_date)) $criteria[] = "Date to: " . $filter_end_date;
if ($user_role === 'software') $criteria[] = "My Logs";
if (in_array($user_role, ['manager', 'inventory_admin']) && !empty($user_branch)) $criteria[] = "Branch: " . $user_branch;

$filterNote .= !empty($criteria) ? implode(', ', $criteria) : "None (All data)";

$sheet->setCellValue('A' . $row, $filterNote);
$sheet->mergeCells('A' . $row . ':M' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$row++;

// Blank row for spacing
$row++;

// ---- Headers ----
$headers = [
    '#', 'Serial', 'Category', 'Model', 'Branch',
    'Old RAM (GB)', 'New RAM (GB)', 'Old Storage (GB)', 'New Storage (GB)',
    'Old Graphics', 'New Graphics', 'Performed By', 'Date'
];
$headerRow = $row;
foreach ($headers as $idx => $header) {
    $col = chr(65 + $idx);
    $sheet->setCellValue($col . $headerRow, $header);
}

// Style header row
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A4B2A']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A' . $headerRow . ':' . chr(64 + count($headers)) . $headerRow)->applyFromArray($headerStyle);

// ---- Data rows ----
$dataRow = $headerRow + 1;
$i = 1;
foreach ($logs as $log) {
    $col = 'A';
    $sheet->setCellValue($col++ . $dataRow, $i++);
    $sheet->setCellValue($col++ . $dataRow, $log['device_serial'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $log['category_name'] ?? 'N/A');
    $sheet->setCellValue($col++ . $dataRow, $log['model_name'] ?? 'N/A');
    $sheet->setCellValue($col++ . $dataRow, $log['branch'] ?? 'N/A');
    $sheet->setCellValue($col++ . $dataRow, $log['old_ram'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $log['new_ram'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $log['old_storage'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $log['new_storage'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $log['old_graphics'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $log['new_graphics'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $log['performed_by_name'] ?? 'Unknown');
    $sheet->setCellValue($col++ . $dataRow, !empty($log['date_performed']) ? date('Y-m-d H:i:s', strtotime($log['date_performed'])) : '');
    $dataRow++;
}

// ---- Alignment ----
$sheet->getStyle('A' . ($headerRow+1) . ':A' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('F' . ($headerRow+1) . ':I' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('M' . ($headerRow+1) . ':M' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// ---- Borders ----
$lastCol = chr(64 + count($headers));
$sheet->getStyle('A' . ($headerRow+1) . ':' . $lastCol . ($dataRow-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ---- Summary row ----
$summaryRow = $dataRow;
$sheet->setCellValue('A' . $summaryRow, '');
$sheet->setCellValue('B' . $summaryRow, '');
$sheet->setCellValue('C' . $summaryRow, '');
$sheet->setCellValue('D' . $summaryRow, '');
$sheet->setCellValue('E' . $summaryRow, '');
$sheet->setCellValue('F' . $summaryRow, '');
$sheet->setCellValue('G' . $summaryRow, '');
$sheet->setCellValue('H' . $summaryRow, '');
$sheet->setCellValue('I' . $summaryRow, '');
$sheet->setCellValue('J' . $summaryRow, '');
$sheet->setCellValue('K' . $summaryRow, '');
$sheet->setCellValue('L' . $summaryRow, '');
$sheet->setCellValue('M' . $summaryRow, '');

// Total records row
$totalRow = $summaryRow + 1;
$sheet->setCellValue('A' . $totalRow, 'TOTAL RECORDS: ' . count($logs));
$sheet->mergeCells('A' . $totalRow . ':M' . $totalRow);
$sheet->getStyle('A' . $totalRow)->getFont()->setBold(true);
$sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

// ---- Auto-size columns ----
$columns = range('A', $lastCol);
foreach ($columns as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ---- Output file ----
$filename = 'Maintenance_Logs_' . date('Y-m-d') . '.xlsx';

// Clear output buffers to avoid corruption
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>