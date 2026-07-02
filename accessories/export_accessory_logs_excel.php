<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

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

// Get filters (same as accessory_logs.php)
$filter_branch = trim($_GET['branch'] ?? '');
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

// Build query (same as accessory_logs.php)
$sql = "SELECT l.*, 
               u_given_to.full_name AS given_to_name,
               u_given_by.full_name AS given_by_name
        FROM accessories_logs l
        LEFT JOIN users u_given_to ON l.given_to = u_given_to.id
        LEFT JOIN users u_given_by ON l.given_by = u_given_by.id
        WHERE 1=1";
$params = [];

if ($role === 'manager' && !empty($user_branch)) {
    $sql .= " AND l.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}
if ($filter_branch && $role !== 'manager') {
    $sql .= " AND l.branch = :branch";
    $params['branch'] = $filter_branch;
}
if ($filter_status) {
    $sql .= " AND l.status = :status";
    $params['status'] = $filter_status;
}
if ($date_from) {
    $sql .= " AND DATE(l.date_given) >= :date_from";
    $params['date_from'] = $date_from;
}
if ($date_to) {
    $sql .= " AND DATE(l.date_given) <= :date_to";
    $params['date_to'] = $date_to;
}
if ($search) {
    $sql .= " AND (l.accessory_name LIKE :search OR u_given_to.full_name LIKE :search OR u_given_by.full_name LIKE :search)";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY l.date_given DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($logs)) {
    die("No accessory logs to export.");
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Accessory Logs');

// Set column widths
$sheet->getColumnDimension('A')->setWidth(6);   // #
$sheet->getColumnDimension('B')->setWidth(30);  // Accessory Name
$sheet->getColumnDimension('C')->setWidth(10);  // Qty
$sheet->getColumnDimension('D')->setWidth(20);  // Given To
$sheet->getColumnDimension('E')->setWidth(20);  // Given By
$sheet->getColumnDimension('F')->setWidth(15);  // Branch
$sheet->getColumnDimension('G')->setWidth(18);  // Status
$sheet->getColumnDimension('H')->setWidth(20);  // Date Given

$row = 1;

// ---- Title ----
$sheet->setCellValue('A' . $row, 'Accessory Logs Report');
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
if (!empty($filter_status)) $criteria[] = "Status: " . ucfirst(str_replace('_', ' ', $filter_status));
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
$headers = ['#', 'Accessory Name', 'Qty', 'Given To', 'Given By', 'Branch', 'Status', 'Date Given'];
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
    $sheet->setCellValue('A' . $dataRow, $i++);
    $sheet->setCellValue('B' . $dataRow, $log['accessory_name']);
    $sheet->setCellValue('C' . $dataRow, $log['quantity']);
    $sheet->setCellValue('D' . $dataRow, $log['given_to_name'] ?? 'Unknown');
    $sheet->setCellValue('E' . $dataRow, $log['given_by_name'] ?? 'Unknown');
    $sheet->setCellValue('F' . $dataRow, $log['branch']);
    $sheet->setCellValue('G' . $dataRow, ucfirst(str_replace('_', ' ', $log['status'])));
    $sheet->setCellValue('H' . $dataRow, date('Y-m-d H:i:s', strtotime($log['date_given'])));
    $dataRow++;
}

// ---- Alignment ----
$sheet->getStyle('A' . ($headerRow+1) . ':A' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('C' . ($headerRow+1) . ':C' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('G' . ($headerRow+1) . ':G' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// ---- Borders ----
$sheet->getStyle('A' . ($headerRow+1) . ':H' . ($dataRow-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ---- Summary row ----
$summaryRow = $dataRow;
$sheet->setCellValue('A' . $summaryRow, '');
$sheet->setCellValue('B' . $summaryRow, 'TOTAL:');
$sheet->setCellValue('C' . $summaryRow, array_sum(array_column($logs, 'quantity')));
$sheet->setCellValue('D' . $summaryRow, '');
$sheet->setCellValue('E' . $summaryRow, '');
$sheet->setCellValue('F' . $summaryRow, '');
$sheet->setCellValue('G' . $summaryRow, '');
$sheet->setCellValue('H' . $summaryRow, '');
$sheet->getStyle('B' . $summaryRow . ':C' . $summaryRow)->getFont()->setBold(true);
// Merge cells for spacing
$sheet->mergeCells('A' . $summaryRow . ':A' . $summaryRow);
$sheet->mergeCells('D' . $summaryRow . ':H' . $summaryRow);

// ---- Output file ----
$filename = 'Accessory_Logs_' . date('Y-m-d') . '.xlsx';

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
?>