<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

if (!in_array($user_role, ['super_admin', 'inventory_admin', 'manager', 'technician'])) {
    die("ACCESS DENIED.");
}

// Load PhpSpreadsheet
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Get user branch for non-super_admin
$user_branch = null;
if ($user_role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
}

// Get filters (same as repair_logs.php)
$filter_serial = trim($_GET['serial'] ?? '');
$filter_branch = trim($_GET['branch'] ?? '');
$filter_start = trim($_GET['start_date'] ?? '');
$filter_end = trim($_GET['end_date'] ?? '');
$filter_source = trim($_GET['source'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_has_cost = isset($_GET['has_cost']) ? (int)$_GET['has_cost'] : '';

// Build query (same as repair_logs.php)
$sql = "SELECT r.*, d.model_name, d.processor, d.ram, d.storage_type, d.storage_capacity, d.touch, d.graphics,
               c.category_name, u1.full_name AS fixed_by_name, u2.full_name AS given_by_name,
               r.source_device
        FROM repairs r
        LEFT JOIN devices d ON r.serial_number COLLATE utf8mb4_general_ci = d.serial_number
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN users u1 ON r.added_by = u1.id
        LEFT JOIN users u2 ON r.given_by = u2.id
        WHERE 1=1";
$params = [];

// Role-based filtering
if ($user_role === 'technician') {
    $sql .= " AND r.added_by = ?";
    $params[] = $user_id;
} elseif (in_array($user_role, ['inventory_admin', 'manager'])) {
    if ($user_branch) {
        $sql .= " AND r.branch = ?";
        $params[] = $user_branch;
    } else {
        $sql .= " AND 1=0";
    }
}

// Status filter
if (!empty($filter_status)) {
    if ($filter_status === 'pending') {
        $sql .= " AND r.fix_status = 'pending'";
    } elseif ($filter_status === 'fixed') {
        $sql .= " AND r.fix_status = 'Fixed'";
    }
} else {
    $sql .= " AND r.fix_status IN ('pending', 'Fixed')";
}

// Serial filter
if (!empty($filter_serial)) {
    $sql .= " AND r.serial_number LIKE ?";
    $params[] = "%$filter_serial%";
}

// Branch filter (super admin only)
if ($user_role === 'super_admin' && !empty($filter_branch)) {
    $sql .= " AND r.branch = ?";
    $params[] = $filter_branch;
}

// Source filter
if (!empty($filter_source)) {
    $sql .= " AND r.source_device = ?";
    $params[] = $filter_source;
}

// Cost filter
if ($filter_has_cost === '1') {
    $sql .= " AND r.repair_cost IS NOT NULL AND r.repair_cost > 0";
} elseif ($filter_has_cost === '0') {
    $sql .= " AND (r.repair_cost IS NULL OR r.repair_cost = 0)";
}

// Date range filters
if (!empty($filter_start) && !empty($filter_end)) {
    $sql .= " AND DATE(r.date_added) BETWEEN ? AND ?";
    $params[] = $filter_start;
    $params[] = $filter_end;
} elseif (!empty($filter_start)) {
    $sql .= " AND DATE(r.date_added) >= ?";
    $params[] = $filter_start;
} elseif (!empty($filter_end)) {
    $sql .= " AND DATE(r.date_added) <= ?";
    $params[] = $filter_end;
}

$sql .= " ORDER BY r.date_added DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($repairs)) {
    die("No repair data to export.");
}

// Helper function to safely escape
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Helper function to get source display
function getSourceLabel($source) {
    $sources = [
        'instock' => 'In Stock',
        'return' => 'Return',
        'client' => 'Client'
    ];
    return $sources[$source] ?? 'Unknown';
}

// Helper function to get status display
function getStatusLabel($status) {
    $statuses = [
        'Fixed' => 'Fixed',
        'pending' => 'Pending',
        'Not Fixed' => 'Not Fixed'
    ];
    return $statuses[$status] ?? $status;
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Repair Logs');

$row = 1;

// ---- Title ----
$sheet->setCellValue('A' . $row, 'Repair Logs Report');
$sheet->mergeCells('A' . $row . ':N' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

// ---- Generated Date ----
$sheet->setCellValue('A' . $row, 'Generated: ' . date('Y-m-d H:i:s'));
$sheet->mergeCells('A' . $row . ':N' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$row++;

// ---- Filter Criteria Note ----
$filterNote = "Filters applied: ";
$criteria = [];
if (!empty($filter_serial)) $criteria[] = "Serial: " . $filter_serial;
if (!empty($filter_source)) $criteria[] = "Source: " . getSourceLabel($filter_source);
if (!empty($filter_status)) $criteria[] = "Status: " . getStatusLabel($filter_status);
if ($filter_has_cost === '1') $criteria[] = "Has Cost: Yes";
elseif ($filter_has_cost === '0') $criteria[] = "Has Cost: No";
if (!empty($filter_start) && !empty($filter_end)) $criteria[] = "Date: " . $filter_start . " to " . $filter_end;
elseif (!empty($filter_start)) $criteria[] = "Date from: " . $filter_start;
elseif (!empty($filter_end)) $criteria[] = "Date to: " . $filter_end;
if ($user_role === 'super_admin' && !empty($filter_branch)) $criteria[] = "Branch: " . $filter_branch;
if ($user_role === 'technician') $criteria[] = "Technician: My Repairs";
if ($user_role === 'manager' && !empty($user_branch)) $criteria[] = "Branch: " . $user_branch;

$filterNote .= !empty($criteria) ? implode(', ', $criteria) : "None (All data)";

$sheet->setCellValue('A' . $row, $filterNote);
$sheet->mergeCells('A' . $row . ':N' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$row++;

// Blank row for spacing
$row++;

// ---- Headers ----
$headers = [
    '#', 'Serial', 'Category', 'Model', 'Source', 'Status', 
    'Problem', 'Client', 'Client Phone', 'Client Email', 
    'Given By', 'Fixed By', 'Branch', 'Cost (KES)', 'Parts Used', 'Date Added', 'Date Fixed'
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
foreach ($repairs as $r) {
    $col = 'A';
    $sheet->setCellValue($col++ . $dataRow, $i++);
    $sheet->setCellValue($col++ . $dataRow, $r['serial_number'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $r['category_name'] ?? 'N/A');
    $sheet->setCellValue($col++ . $dataRow, $r['model_name'] ?? 'N/A');
    $sheet->setCellValue($col++ . $dataRow, getSourceLabel($r['source_device'] ?? ''));
    $sheet->setCellValue($col++ . $dataRow, getStatusLabel($r['fix_status'] ?? ''));
    $sheet->setCellValue($col++ . $dataRow, $r['problem_description'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $r['client_name'] ?? 'N/A');
    $sheet->setCellValue($col++ . $dataRow, $r['client_phone'] ?? 'N/A');
    $sheet->setCellValue($col++ . $dataRow, $r['client_email'] ?? 'N/A');
    $sheet->setCellValue($col++ . $dataRow, $r['given_by_name'] ?? 'N/A');
    $sheet->setCellValue($col++ . $dataRow, $r['fixed_by_name'] ?? 'Unknown');
    $sheet->setCellValue($col++ . $dataRow, $r['branch'] ?? 'N/A');
    $sheet->setCellValue($col++ . $dataRow, $r['repair_cost'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $r['parts_used'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, !empty($r['date_added']) ? date('Y-m-d H:i:s', strtotime($r['date_added'])) : '');
    $sheet->setCellValue($col++ . $dataRow, !empty($r['date_fixed']) ? date('Y-m-d H:i:s', strtotime($r['date_fixed'])) : '');
    $dataRow++;
}

// ---- Number formatting for cost ----
$lastCol = chr(64 + count($headers));
$sheet->getStyle('N' . ($headerRow+1) . ':N' . ($dataRow-1))->getNumberFormat()->setFormatCode('#,##0.00');

// ---- Alignment ----
$sheet->getStyle('A' . ($headerRow+1) . ':A' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('E' . ($headerRow+1) . ':F' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('M' . ($headerRow+1) . ':M' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('N' . ($headerRow+1) . ':N' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// ---- Borders ----
$sheet->getStyle('A' . ($headerRow+1) . ':' . $lastCol . ($dataRow-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ---- Wrap text for problem description ----
$sheet->getStyle('G' . ($headerRow+1) . ':G' . ($dataRow-1))->getAlignment()->setWrapText(true);

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
$sheet->setCellValue('M' . $summaryRow, 'TOTAL:');
$sheet->setCellValue('N' . $summaryRow, array_sum(array_column($repairs, 'repair_cost')));
$sheet->setCellValue('O' . $summaryRow, '');
$sheet->setCellValue('P' . $summaryRow, '');
$sheet->setCellValue('Q' . $summaryRow, '');

$sheet->getStyle('M' . $summaryRow . ':N' . $summaryRow)->getFont()->setBold(true);
$sheet->getStyle('N' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0.00');

// ---- Auto-size columns ----
$columns = range('A', $lastCol);
foreach ($columns as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ---- Output file ----
$filename = 'Repair_Logs_' . date('Y-m-d') . '.xlsx';

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