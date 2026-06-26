<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only allow the same roles as the view page
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

// Get filters (same as hdd_instock.php)
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

// Build query (same as hdd_instock.php)
$sql = "SELECT h.*, 
               u1.full_name AS added_by_name,
               u2.full_name AS updated_by_name
        FROM hdds h
        LEFT JOIN users u1 ON h.added_by = u1.id
        LEFT JOIN users u2 ON h.updated_by = u2.id
        WHERE 1=1";
$params = [];

if ($role === 'manager' && !empty($user_branch)) {
    $sql .= " AND h.branch = :user_branch";
    $params['user_branch'] = $user_branch;
}
if ($search_type) {
    $sql .= " AND h.type LIKE :type";
    $params['type'] = "%$search_type%";
}
if ($search_storage) {
    $sql .= " AND h.storage LIKE :storage";
    $params['storage'] = "%$search_storage%";
}
if ($search_branch && $role !== 'manager') {
    $sql .= " AND h.branch = :branch";
    $params['branch'] = $search_branch;
}

$sql .= " ORDER BY h.date_added DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$hdds = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($hdds)) {
    die("No HDD data to export.");
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('In-Stock HDDs');

// Set column widths
foreach (range('A', 'J') as $col) {
    $sheet->getColumnDimension($col)->setWidth(15);
}

$row = 1;

// ---- Title ----
$sheet->setCellValue('A' . $row, 'In-Stock HDDs Report');
$sheet->mergeCells('A' . $row . ':J' . $row);
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
$filterNote .= !empty($criteria) ? implode(', ', $criteria) : "None (All data)";

$sheet->setCellValue('A' . $row, $filterNote);
$sheet->mergeCells('A' . $row . ':J' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$row++;

// Blank row for spacing
$row++;

// ---- Headers ----
$headers = ['#', 'Type', 'Quantity', 'Storage', 'Branch', 'Price (KES)', 'Total Value (KES)', 'Added By', 'Updated By', 'Date Added'];
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
$sheet->getStyle('A' . $headerRow . ':J' . $headerRow)->applyFromArray($headerStyle);

// ---- Data rows ----
$dataRow = $headerRow + 1;
$i = 1;
foreach ($hdds as $hdd) {
    $sheet->setCellValue('A' . $dataRow, $i++);
    $sheet->setCellValue('B' . $dataRow, $hdd['type']);
    $sheet->setCellValue('C' . $dataRow, $hdd['quantity']);
    $sheet->setCellValue('D' . $dataRow, $hdd['storage']);
    $sheet->setCellValue('E' . $dataRow, $hdd['branch']);
    $sheet->setCellValue('F' . $dataRow, $hdd['price'] ?? '');
    $sheet->setCellValue('G' . $dataRow, $hdd['total_price'] ?? '');
    $sheet->setCellValue('H' . $dataRow, $hdd['added_by_name'] ?? 'N/A');
    $sheet->setCellValue('I' . $dataRow, $hdd['updated_by_name'] ?? 'Not updated yet');
    $sheet->setCellValue('J' . $dataRow, date('Y-m-d H:i:s', strtotime($hdd['date_added'])));
    $dataRow++;
}

// ---- Number formatting ----
$sheet->getStyle('F' . ($headerRow+1) . ':G' . ($dataRow-1))->getNumberFormat()->setFormatCode('#,##0.00');

// ---- Alignment ----
$sheet->getStyle('A' . ($headerRow+1) . ':A' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('C' . ($headerRow+1) . ':C' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('F' . ($headerRow+1) . ':G' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// ---- Borders ----
$sheet->getStyle('A' . ($headerRow+1) . ':J' . ($dataRow-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ---- Summary row ----
$summaryRow = $dataRow;
$sheet->setCellValue('A' . $summaryRow, '');
$sheet->setCellValue('B' . $summaryRow, '');
$sheet->setCellValue('C' . $summaryRow, 'TOTAL:');
$sheet->setCellValue('D' . $summaryRow, '');
$sheet->setCellValue('E' . $summaryRow, '');
$sheet->setCellValue('F' . $summaryRow, '');
$sheet->setCellValue('G' . $summaryRow, array_sum(array_column($hdds, 'total_price')));
$sheet->setCellValue('H' . $summaryRow, '');
$sheet->setCellValue('I' . $summaryRow, '');
$sheet->setCellValue('J' . $summaryRow, '');
$sheet->getStyle('C' . $summaryRow . ':G' . $summaryRow)->getFont()->setBold(true);
$sheet->getStyle('G' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0.00');
// Merge cells for spacing
$sheet->mergeCells('A' . $summaryRow . ':A' . $summaryRow);
$sheet->mergeCells('B' . $summaryRow . ':B' . $summaryRow);
$sheet->mergeCells('D' . $summaryRow . ':F' . $summaryRow);
$sheet->mergeCells('H' . $summaryRow . ':J' . $summaryRow);

// ---- Output file ----
$filename = 'In-Stock_HDDs_' . date('Y-m-d') . '.xlsx';

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