<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

if (!in_array($role, ['super_admin', 'inventory_admin', 'manager'])) {
    die("ACCESS DENIED.");
}

// Load PhpSpreadsheet
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// --- Helper: build device specifications string ---
function buildDeviceSpecs($log) {
    $specs = "";
    if (!empty($log['model_name'])) $specs .= $log['model_name'];
    if (!empty($log['processor'])) $specs .= " | " . $log['processor'];
    if (!empty($log['ram'])) $specs .= " | " . $log['ram'] . "GB RAM";
    if (!empty($log['storage_type']) && !empty($log['storage_capacity'])) {
        $specs .= " | " . $log['storage_type'] . " " . $log['storage_capacity'] . "GB";
    }
    if (isset($log['graphics']) && $log['graphics'] !== '' && $log['graphics'] !== 'None') {
        $specs .= " | " . $log['graphics'];
    }
    if (isset($log['touch']) && $log['touch'] !== 'N/A' && $log['touch'] !== '') {
        $specs .= " | " . $log['touch'];
    }
    return trim($specs, " |");
}

// Get filters (same as device_logs.php)
$filter_branch = trim($_GET['branch'] ?? '');
$filter_action = trim($_GET['action'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$search = trim($_GET['search'] ?? '');

// Manager branch restriction
$user_branch = '';
if ($role === 'manager') {
    $user_stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_branch = $user_data['branch'] ?? '';
}

// Build query (same as device_logs.php)
$sql = "SELECT l.*, 
               c.category_name,
               u_given_by.full_name AS given_by_name,
               u_given_to.full_name AS given_to_name,
               u_taken_by.full_name AS taken_by_name
        FROM devices_logs l
        LEFT JOIN categories c ON l.category_id = c.id
        LEFT JOIN users u_given_by ON l.given_by = u_given_by.id
        LEFT JOIN users u_given_to ON l.given_to = u_given_to.id
        LEFT JOIN users u_taken_by ON l.taken_by = u_taken_by.id
        WHERE 1=1";
$params = [];

// Manager restriction
if ($role === 'manager' && !empty($user_branch)) {
    $sql .= " AND l.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}

// Inventory admin: only see logs they performed
if ($role === 'inventory_admin') {
    $sql .= " AND (l.given_by = :uid OR l.taken_by = :uid)";
    $params['uid'] = $user_id;
}

// Filters
if ($filter_branch && $role !== 'manager') {
    $sql .= " AND l.branch = :branch";
    $params['branch'] = $filter_branch;
}
if ($filter_action) {
    $sql .= " AND l.action = :action";
    $params['action'] = $filter_action;
}
if ($filter_status) {
    $sql .= " AND l.status = :status";
    $params['status'] = $filter_status;
}
if ($date_from) {
    $sql .= " AND COALESCE(l.date_given, l.date_taken) >= :date_from";
    $params['date_from'] = $date_from;
}
if ($date_to) {
    $sql .= " AND COALESCE(l.date_given, l.date_taken) <= :date_to";
    $params['date_to'] = $date_to;
}
if ($search) {
    $sql .= " AND (l.serial_number LIKE :search OR l.model_name LIKE :search OR c.category_name LIKE :search)";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY COALESCE(l.date_given, l.date_taken) DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($logs)) {
    die("No device logs to export.");
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Device Logs');

// Set column widths
$columnWidths = [
    'A' => 6,   // #
    'B' => 20,  // Serial
    'C' => 45,  // Specifications (wider)
    'D' => 18,  // Action
    'E' => 30,  // Person
    'F' => 14,  // Branch
    'G' => 14,  // Status
    'H' => 22   // Date
];
foreach ($columnWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

$row = 1;

// ---- Title ----
$sheet->setCellValue('A' . $row, 'Device Logs Report');
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

// ---- Filter Criteria Note ----
$filterNote = "Filters applied: ";
$criteria = [];
if (!empty($search)) $criteria[] = "Search: " . $search;
if (!empty($filter_branch) && $role !== 'manager') $criteria[] = "Branch: " . $filter_branch;
if ($role === 'manager' && !empty($user_branch)) $criteria[] = "Branch: " . $user_branch;
if ($role === 'inventory_admin') $criteria[] = "Showing only logs performed by you";
if (!empty($filter_action)) $criteria[] = "Action: " . ucfirst(str_replace('_', ' ', $filter_action));
if (!empty($filter_status)) $criteria[] = "Status: " . ucfirst($filter_status);
if (!empty($date_from) && !empty($date_to)) $criteria[] = "Date: " . $date_from . " to " . $date_to;
$filterNote .= !empty($criteria) ? implode(', ', $criteria) : "None (All data)";

$sheet->setCellValue('A' . $row, $filterNote);
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$row++;

// Blank row for spacing
$row++;

// ---- Headers ----
$headers = ['#', 'Serial Number', 'Specifications', 'Action', 'Person', 'Branch', 'Status', 'Date'];
$headerRow = $row;
foreach ($headers as $idx => $header) {
    $sheet->setCellValue(chr(65 + $idx) . $headerRow, $header);
}

// Style header row
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A4B2A']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A' . $headerRow . ':H' . $headerRow)->applyFromArray($headerStyle);

// ---- Data rows ----
$dataRow = $headerRow + 1;
$i = 1;
foreach ($logs as $log) {
    // Determine person and date based on action
    if ($log['action'] == 'take_to_display') {
        $person = $log['taken_by_name'] ?? 'Unknown';
        $date = $log['date_taken'];
    } else { // give_out
        $person = 'Given to: ' . ($log['given_to_name'] ?? 'Unknown') . ' by ' . ($log['given_by_name'] ?? 'Unknown');
        $date = $log['date_given'];
    }
    
    $specs = buildDeviceSpecs($log);
    $status = ucfirst($log['status'] ?? 'instock');
    $action = ucfirst(str_replace('_', ' ', $log['action']));
    
    $sheet->setCellValue('A' . $dataRow, $i++);
    $sheet->setCellValue('B' . $dataRow, $log['serial_number']);
    $sheet->setCellValue('C' . $dataRow, $specs);
    $sheet->setCellValue('D' . $dataRow, $action);
    $sheet->setCellValue('E' . $dataRow, $person);
    $sheet->setCellValue('F' . $dataRow, $log['branch']);
    $sheet->setCellValue('G' . $dataRow, $status);
    $sheet->setCellValue('H' . $dataRow, date('Y-m-d H:i:s', strtotime($date)));
    $dataRow++;
}

// ---- Alignment ----
$sheet->getStyle('A' . ($headerRow+1) . ':A' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('G' . ($headerRow+1) . ':G' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('C' . ($headerRow+1) . ':C' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('C' . ($headerRow+1) . ':C' . ($dataRow-1))->getAlignment()->setWrapText(true);

// ---- Borders ----
$sheet->getStyle('A' . ($headerRow+1) . ':H' . ($dataRow-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ---- Summary row ----
$summaryRow = $dataRow;
$sheet->setCellValue('A' . $summaryRow, '');
$sheet->setCellValue('B' . $summaryRow, '');
$sheet->setCellValue('C' . $summaryRow, '');
$sheet->setCellValue('D' . $summaryRow, '');
$sheet->setCellValue('E' . $summaryRow, '');
$sheet->setCellValue('F' . $summaryRow, '');
$sheet->setCellValue('G' . $summaryRow, 'TOTAL:');
$sheet->setCellValue('H' . $summaryRow, count($logs));
$sheet->getStyle('G' . $summaryRow . ':H' . $summaryRow)->getFont()->setBold(true);
$sheet->getStyle('G' . $summaryRow . ':H' . $summaryRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
// Merge cells for spacing
$sheet->mergeCells('A' . $summaryRow . ':F' . $summaryRow);

// ---- Output file ----
$filename = 'Device_Logs_' . date('Y-m-d') . '.xlsx';

// Clear output buffers
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;