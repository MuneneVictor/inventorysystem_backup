<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if (!in_array($role, ['super_admin', 'inventory_admin', 'manager', 'sales'])) {
    die("ACCESS DENIED.");
}

// Load PhpSpreadsheet
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Get filters (same as rams_instock.php)
$search_category = trim($_GET['category'] ?? '');
$search_type = trim($_GET['type'] ?? '');
$search_storage = trim($_GET['storage'] ?? '');
$search_branch = trim($_GET['branch'] ?? '');

// Manager branch restriction
$user_branch = '';
if ($role === 'manager') {
    $user_stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_branch = $user_data['branch'] ?? '';
}

// Build query
$sql = "SELECT r.*, 
               u1.full_name AS added_by_name,
               u2.full_name AS updated_by_name
        FROM rams_ssds r
        LEFT JOIN users u1 ON r.added_by = u1.id
        LEFT JOIN users u2 ON r.updated_by = u2.id
        WHERE 1=1";
$params = [];

if ($role === 'manager' && !empty($user_branch)) {
    $sql .= " AND r.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}
if ($search_category) {
    $sql .= " AND r.category = :category";
    $params['category'] = $search_category;
}
if ($search_type) {
    $sql .= " AND r.type LIKE :type";
    $params['type'] = "%$search_type%";
}
if ($search_storage) {
    $sql .= " AND r.storage = :storage";
    $params['storage'] = $search_storage;
}
if ($search_branch && $role !== 'manager') {
    $sql .= " AND r.branch = :branch";
    $params['branch'] = $search_branch;
}

$sql .= " ORDER BY r.date_added DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    die("No RAM/SSD data to export.");
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('In-Stock RAM_SSD'); // Fixed: removed slash

// Set column widths
foreach (range('A', 'K') as $col) {
    $sheet->getColumnDimension($col)->setWidth(18);
}

$row = 1;

// ---- Title ----
$sheet->setCellValue('A' . $row, 'In-Stock RAM/SSD Report');
$sheet->mergeCells('A' . $row . ':K' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

// ---- Filter Criteria Note ----
$filterNote = "Filters applied: ";
$criteria = [];
if (!empty($search_category)) $criteria[] = "Category: " . $search_category;
if (!empty($search_type)) $criteria[] = "Type: " . $search_type;
if (!empty($search_storage)) $criteria[] = "Storage: " . $search_storage . "GB";
if (!empty($search_branch) && $role !== 'manager') $criteria[] = "Branch: " . $search_branch;
if ($role === 'manager' && !empty($user_branch)) $criteria[] = "Branch: " . $user_branch;
$filterNote .= !empty($criteria) ? implode(', ', $criteria) : "None (All data)";

$sheet->setCellValue('A' . $row, $filterNote);
$sheet->mergeCells('A' . $row . ':K' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$row++;

// Blank row
$row++;

// ---- Headers ----
$headers = ['#', 'Category', 'Type', 'Storage (GB)', 'Quantity', 'Branch', 'Price (KES)', 'Total Value (KES)', 'Added By', 'Updated By', 'Date Added'];
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
$sheet->getStyle('A' . $headerRow . ':K' . $headerRow)->applyFromArray($headerStyle);

// ---- Data rows ----
$dataRow = $headerRow + 1;
$i = 1;
foreach ($items as $item) {
    $sheet->setCellValue('A' . $dataRow, $i++);
    $sheet->setCellValue('B' . $dataRow, $item['category']);
    $sheet->setCellValue('C' . $dataRow, $item['type']);
    $sheet->setCellValue('D' . $dataRow, $item['storage']);
    $sheet->setCellValue('E' . $dataRow, $item['quantity']);
    $sheet->setCellValue('F' . $dataRow, $item['branch']);
    $sheet->setCellValue('G' . $dataRow, $item['price'] ?? '');
    $sheet->setCellValue('H' . $dataRow, $item['total_price'] ?? '');
    $sheet->setCellValue('I' . $dataRow, $item['added_by_name'] ?? 'N/A');
    $sheet->setCellValue('J' . $dataRow, $item['updated_by_name'] ?? 'Not updated yet');
    $sheet->setCellValue('K' . $dataRow, date('Y-m-d H:i:s', strtotime($item['date_added'])));
    $dataRow++;
}

// ---- Number formatting ----
$sheet->getStyle('G' . ($headerRow+1) . ':H' . ($dataRow-1))->getNumberFormat()->setFormatCode('#,##0.00');

// ---- Alignment ----
$sheet->getStyle('A' . ($headerRow+1) . ':A' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('E' . ($headerRow+1) . ':E' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('G' . ($headerRow+1) . ':H' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// ---- Borders ----
$sheet->getStyle('A' . ($headerRow+1) . ':K' . ($dataRow-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ---- Summary row ----
$summaryRow = $dataRow;
$sheet->setCellValue('A' . $summaryRow, '');
$sheet->setCellValue('B' . $summaryRow, '');
$sheet->setCellValue('C' . $summaryRow, '');
$sheet->setCellValue('D' . $summaryRow, '');
$sheet->setCellValue('E' . $summaryRow, '');
$sheet->setCellValue('F' . $summaryRow, '');
$sheet->setCellValue('G' . $summaryRow, '');
$sheet->setCellValue('H' . $summaryRow, array_sum(array_column($items, 'total_price')));
$sheet->setCellValue('I' . $summaryRow, '');
$sheet->setCellValue('J' . $summaryRow, '');
$sheet->setCellValue('K' . $summaryRow, '');
$sheet->getStyle('H' . $summaryRow)->getFont()->setBold(true);
$sheet->getStyle('H' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0.00');
// Merge cells for spacing
$sheet->mergeCells('A' . $summaryRow . ':G' . $summaryRow);
$sheet->mergeCells('I' . $summaryRow . ':K' . $summaryRow);

// ---- Output ----
$filename = 'In-Stock_RAM_SSD_' . date('Y-m-d') . '.xlsx';

if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;