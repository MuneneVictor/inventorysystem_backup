<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

// Allow same roles as the view page
if (!in_array($role, ['super_admin', 'inventory_admin', 'manager', 'sales', 'cashier'])) {
    die("ACCESS DENIED.");
}

// Load PhpSpreadsheet
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Get filters (same as sold_hdds.php)
$search_type = trim($_GET['type'] ?? '');
$search_storage = trim($_GET['storage'] ?? '');
$search_branch = trim($_GET['branch'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$filter_salesperson = trim($_GET['salesperson'] ?? '');

// Manager branch restriction
$user_branch = '';
if ($role === 'manager') {
    $user_stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_branch = $user_data['branch'] ?? '';
}

// Build query (same as sold_hdds.php)
$sql = "SELECT s.*, u.full_name AS sold_by_name
        FROM sold_hdds s
        LEFT JOIN users u ON s.sold_by = u.id
        WHERE 1=1";
$params = [];

if ($role === 'manager' && !empty($user_branch)) {
    $sql .= " AND s.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}
if ($search_type) {
    $sql .= " AND s.type LIKE :type";
    $params['type'] = "%$search_type%";
}
if ($search_storage) {
    $sql .= " AND s.storage LIKE :storage";
    $params['storage'] = "%$search_storage%";
}
if ($search_branch && $role !== 'manager') {
    $sql .= " AND s.branch = :branch";
    $params['branch'] = $search_branch;
}
if ($date_from) {
    $sql .= " AND DATE(s.date_sold) >= :date_from";
    $params['date_from'] = $date_from;
}
if ($date_to) {
    $sql .= " AND DATE(s.date_sold) <= :date_to";
    $params['date_to'] = $date_to;
}
if (in_array($role, ['super_admin', 'inventory_admin']) && !empty($filter_salesperson)) {
    $sql .= " AND s.sold_by = :salesperson";
    $params['salesperson'] = $filter_salesperson;
}

$sql .= " ORDER BY s.date_sold DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($sales)) {
    die("No sold HDD data to export.");
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sold HDDs');

// Set column widths
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setWidth(15);
}

$row = 1;

// ---- Title ----
$sheet->setCellValue('A' . $row, 'Sold HDDs Report');
$sheet->mergeCells('A' . $row . ':I' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

// ---- Filter Criteria Note ----
$filterNote = "Filters applied: ";
$criteria = [];
if (!empty($search_type)) $criteria[] = "Type: " . $search_type;
if (!empty($search_storage)) $criteria[] = "Storage: " . $search_storage;
if (!empty($search_branch) && $role !== 'manager') $criteria[] = "Branch: " . $search_branch;
if ($role === 'manager' && !empty($user_branch)) $criteria[] = "Branch: " . $user_branch;
if (!empty($date_from) && !empty($date_to)) $criteria[] = "Date: " . $date_from . " to " . $date_to;
if (!empty($filter_salesperson) && in_array($role, ['super_admin', 'inventory_admin'])) {
    $userStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $userStmt->execute([$filter_salesperson]);
    $userName = $userStmt->fetchColumn();
    if ($userName) $criteria[] = "Sold By: " . $userName;
}
$filterNote .= !empty($criteria) ? implode(', ', $criteria) : "None (All data)";

$sheet->setCellValue('A' . $row, $filterNote);
$sheet->mergeCells('A' . $row . ':I' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$row++;

// Blank row for spacing
$row++;

// ---- Headers ----
$headers = ['#', 'Type', 'Storage', 'Quantity', 'Selling Price (KES)', 'Total (KES)', 'Branch', 'Sold By', 'Date Sold'];
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
$sheet->getStyle('A' . $headerRow . ':I' . $headerRow)->applyFromArray($headerStyle);

// ---- Data rows ----
$dataRow = $headerRow + 1;
$i = 1;
foreach ($sales as $sale) {
    $sheet->setCellValue('A' . $dataRow, $i++);
    $sheet->setCellValue('B' . $dataRow, $sale['type']);
    $sheet->setCellValue('C' . $dataRow, $sale['storage']);
    $sheet->setCellValue('D' . $dataRow, $sale['quantity']);
    $sheet->setCellValue('E' . $dataRow, $sale['selling_price']);
    $sheet->setCellValue('F' . $dataRow, $sale['total_price']);
    $sheet->setCellValue('G' . $dataRow, $sale['branch']);
    $sheet->setCellValue('H' . $dataRow, $sale['sold_by_name'] ?? 'Unknown');
    $sheet->setCellValue('I' . $dataRow, date('Y-m-d H:i:s', strtotime($sale['date_sold'])));
    $dataRow++;
}

// ---- Number formatting ----
$sheet->getStyle('E' . ($headerRow+1) . ':F' . ($dataRow-1))->getNumberFormat()->setFormatCode('#,##0.00');

// ---- Alignment ----
$sheet->getStyle('A' . ($headerRow+1) . ':A' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('D' . ($headerRow+1) . ':D' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('E' . ($headerRow+1) . ':F' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// ---- Borders ----
$sheet->getStyle('A' . ($headerRow+1) . ':I' . ($dataRow-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ---- Summary row ----
$summaryRow = $dataRow;
$sheet->setCellValue('A' . $summaryRow, '');
$sheet->setCellValue('B' . $summaryRow, '');
$sheet->setCellValue('C' . $summaryRow, '');
$sheet->setCellValue('D' . $summaryRow, '');
$sheet->setCellValue('E' . $summaryRow, '');
$sheet->setCellValue('F' . $summaryRow, array_sum(array_column($sales, 'total_price')));
$sheet->setCellValue('G' . $summaryRow, '');
$sheet->setCellValue('H' . $summaryRow, '');
$sheet->setCellValue('I' . $summaryRow, '');
$sheet->getStyle('F' . $summaryRow)->getFont()->setBold(true);
$sheet->getStyle('F' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0.00');
// Merge cells for spacing
$sheet->mergeCells('A' . $summaryRow . ':E' . $summaryRow);
$sheet->mergeCells('G' . $summaryRow . ':I' . $summaryRow);

// ---- Output file ----
$filename = 'Sold_HDDs_' . date('Y-m-d') . '.xlsx';

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