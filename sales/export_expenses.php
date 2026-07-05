<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// ============================================================
// STRICT ROLE CHECK - Only cashier, super_admin, manager
// ============================================================
if (!in_array($_SESSION['role'], ['cashier', 'super_admin', 'manager'])) {
    die("ACCESS DENIED: You do not have permission to access this page.");
}

$user_role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

// Get user branch - ONLY for cashier
$user_branch = null;
if ($user_role !== 'super_admin' && $user_role !== 'manager') {
    // Only cashier needs branch restriction
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
    if (!$user_branch) {
        die("Your account has no branch assigned. Contact administrator.");
    }
}

// Load PhpSpreadsheet
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// ============================================================
// GET FILTERS (SAME AS expenses_logs.php)
// ============================================================
$filter_expense_name = trim($_GET['expense_name'] ?? '');
$filter_given_to = trim($_GET['given_to'] ?? '');
$filter_payment_method = trim($_GET['payment_method'] ?? '');
$filter_branch = trim($_GET['branch'] ?? '');
$filter_start_date = trim($_GET['start_date'] ?? '');
$filter_end_date = trim($_GET['end_date'] ?? '');

// Default: current month (1st to last day)
if (empty($filter_start_date) && empty($filter_end_date)) {
    $filter_start_date = date('Y-m-01');
    $filter_end_date = date('Y-m-t');
}

// ============================================================
// BUILD QUERY
// ============================================================
$sql = "SELECT e.*, u.full_name AS created_by_name
        FROM expenses e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE 1=1";
$params = [];

// Branch filter - ONLY cashier is restricted to their branch
// Manager and Super Admin see all branches
if ($user_role === 'cashier' && !empty($user_branch)) {
    $sql .= " AND e.branch = ?";
    $params[] = $user_branch;
}

// Filters
if (!empty($filter_expense_name)) {
    $sql .= " AND e.expense_name LIKE ?";
    $params[] = "%$filter_expense_name%";
}

if (!empty($filter_given_to)) {
    $sql .= " AND e.given_to LIKE ?";
    $params[] = "%$filter_given_to%";
}

if (!empty($filter_payment_method)) {
    $sql .= " AND e.payment_method = ?";
    $params[] = $filter_payment_method;
}

// Branch filter from dropdown - available for Super Admin and Manager
if (($user_role === 'super_admin' || $user_role === 'manager') && !empty($filter_branch)) {
    $sql .= " AND e.branch = ?";
    $params[] = $filter_branch;
}

// Date range filter
if (!empty($filter_start_date) && !empty($filter_end_date)) {
    $sql .= " AND DATE(e.expense_date) BETWEEN ? AND ?";
    $params[] = $filter_start_date;
    $params[] = $filter_end_date;
} elseif (!empty($filter_start_date)) {
    $sql .= " AND DATE(e.expense_date) >= ?";
    $params[] = $filter_start_date;
} elseif (!empty($filter_end_date)) {
    $sql .= " AND DATE(e.expense_date) <= ?";
    $params[] = $filter_end_date;
}

$sql .= " ORDER BY e.expense_date DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($expenses)) {
    die("No expense data to export.");
}

// ============================================================
// CREATE SPREADSHEET
// ============================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Expense Logs');

$row = 1;

// ---- Title ----
$sheet->setCellValue('A' . $row, 'Expense Logs Report');
$sheet->mergeCells('A' . $row . ':I' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

// ---- Generated Date ----
$sheet->setCellValue('A' . $row, 'Generated: ' . date('Y-m-d H:i:s'));
$sheet->mergeCells('A' . $row . ':I' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$row++;

// ---- Filter Criteria Note ----
$filterNote = "Filters applied: ";
$criteria = [];
if (!empty($filter_expense_name)) $criteria[] = "Expense: " . $filter_expense_name;
if (!empty($filter_given_to)) $criteria[] = "Given To: " . $filter_given_to;
if (!empty($filter_payment_method)) $criteria[] = "Method: " . ucfirst($filter_payment_method);
if (!empty($filter_branch) && ($user_role === 'super_admin' || $user_role === 'manager')) $criteria[] = "Branch: " . $filter_branch;
if (!empty($filter_start_date) && !empty($filter_end_date)) {
    $criteria[] = "Date: " . date('M d, Y', strtotime($filter_start_date)) . " to " . date('M d, Y', strtotime($filter_end_date));
} elseif (!empty($filter_start_date)) {
    $criteria[] = "From: " . date('M d, Y', strtotime($filter_start_date));
} elseif (!empty($filter_end_date)) {
    $criteria[] = "To: " . date('M d, Y', strtotime($filter_end_date));
}
if ($user_role === 'cashier' && !empty($user_branch)) $criteria[] = "Branch: " . $user_branch;

$filterNote .= !empty($criteria) ? implode(', ', $criteria) : "None (All data)";

$sheet->setCellValue('A' . $row, $filterNote);
$sheet->mergeCells('A' . $row . ':I' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$row++;

// Blank row for spacing
$row++;

// ---- Headers ----
$headers = ['#', 'Expense Name', 'Description', 'Given To', 'Payment Method', 'Amount (KES)', 'Branch', 'Created By', 'Date'];
$headerRow = $row;
foreach ($headers as $idx => $header) {
    $col = chr(65 + $idx);
    $sheet->setCellValue($col . $headerRow, $header);
}

// Style header row
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1A4B2A']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN
        ]
    ]
];
$sheet->getStyle('A' . $headerRow . ':' . chr(64 + count($headers)) . $headerRow)->applyFromArray($headerStyle);

// ---- Data rows ----
$dataRow = $headerRow + 1;
$i = 1;

foreach ($expenses as $e) {
    $col = 'A';
    $sheet->setCellValue($col++ . $dataRow, $i++);
    $sheet->setCellValue($col++ . $dataRow, $e['expense_name'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $e['description'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $e['given_to'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $e['payment_method'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $e['total_amount'] ?? 0);
    $sheet->setCellValue($col++ . $dataRow, $e['branch'] ?? '');
    $sheet->setCellValue($col++ . $dataRow, $e['created_by_name'] ?? 'Unknown');
    $sheet->setCellValue($col++ . $dataRow, !empty($e['expense_date']) ? date('Y-m-d H:i:s', strtotime($e['expense_date'])) : '');
    $dataRow++;
}

// ---- Apply wrap text for description column (C) ----
$sheet->getStyle('C' . ($headerRow+1) . ':C' . ($dataRow-1))->getAlignment()->setWrapText(true);
$sheet->getStyle('B' . ($headerRow+1) . ':B' . ($dataRow-1))->getAlignment()->setWrapText(true);

// ---- Number formatting for amount column ----
$lastCol = chr(64 + count($headers));
$sheet->getStyle('F' . ($headerRow+1) . ':F' . ($dataRow-1))->getNumberFormat()->setFormatCode('#,##0.00');

// ---- Alignment ----
$sheet->getStyle('A' . ($headerRow+1) . ':A' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('E' . ($headerRow+1) . ':E' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('G' . ($headerRow+1) . ':G' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('F' . ($headerRow+1) . ':F' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// ---- Borders ----
$sheet->getStyle('A' . ($headerRow+1) . ':' . $lastCol . ($dataRow-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ---- Summary Row ----
$summaryRow = $dataRow + 1;

// Total summary
$totalAmount = array_sum(array_column($expenses, 'total_amount'));
$cashTotal = 0;
$mpesaTotal = 0;

foreach ($expenses as $e) {
    if (($e['payment_method'] ?? '') === 'cash') {
        $cashTotal += $e['total_amount'];
    } elseif (($e['payment_method'] ?? '') === 'Mpesa') {
        $mpesaTotal += $e['total_amount'];
    }
}

// Summary labels
$sheet->setCellValue('A' . $summaryRow, '');
$sheet->setCellValue('B' . $summaryRow, '');
$sheet->setCellValue('C' . $summaryRow, '');
$sheet->setCellValue('D' . $summaryRow, '');
$sheet->setCellValue('E' . $summaryRow, 'TOTAL:');
$sheet->setCellValue('F' . $summaryRow, $totalAmount);
$sheet->setCellValue('G' . $summaryRow, '');
$sheet->setCellValue('H' . $summaryRow, '');
$sheet->setCellValue('I' . $summaryRow, '');

$sheet->getStyle('E' . $summaryRow . ':F' . $summaryRow)->getFont()->setBold(true);
$sheet->getStyle('F' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0.00');

// ---- Payment Method Summary ----
$summaryRow2 = $summaryRow + 1;
$sheet->setCellValue('A' . $summaryRow2, '');
$sheet->setCellValue('B' . $summaryRow2, '');
$sheet->setCellValue('C' . $summaryRow2, '');
$sheet->setCellValue('D' . $summaryRow2, '');
$sheet->setCellValue('E' . $summaryRow2, 'Cash Total:');
$sheet->setCellValue('F' . $summaryRow2, $cashTotal);
$sheet->setCellValue('G' . $summaryRow2, '');
$sheet->setCellValue('H' . $summaryRow2, '');
$sheet->setCellValue('I' . $summaryRow2, '');

$summaryRow3 = $summaryRow2 + 1;
$sheet->setCellValue('A' . $summaryRow3, '');
$sheet->setCellValue('B' . $summaryRow3, '');
$sheet->setCellValue('C' . $summaryRow3, '');
$sheet->setCellValue('D' . $summaryRow3, '');
$sheet->setCellValue('E' . $summaryRow3, 'M-Pesa Total:');
$sheet->setCellValue('F' . $summaryRow3, $mpesaTotal);
$sheet->setCellValue('G' . $summaryRow3, '');
$sheet->setCellValue('H' . $summaryRow3, '');
$sheet->setCellValue('I' . $summaryRow3, '');

$sheet->getStyle('E' . $summaryRow2 . ':F' . $summaryRow2)->getFont()->setBold(true);
$sheet->getStyle('E' . $summaryRow3 . ':F' . $summaryRow3)->getFont()->setBold(true);
$sheet->getStyle('F' . $summaryRow2 . ':F' . $summaryRow3)->getNumberFormat()->setFormatCode('#,##0.00');

// ---- Merge summary cells for better appearance ----
$sheet->mergeCells('A' . $summaryRow . ':D' . $summaryRow);
$sheet->mergeCells('G' . $summaryRow . ':I' . $summaryRow);
$sheet->mergeCells('A' . $summaryRow2 . ':D' . $summaryRow2);
$sheet->mergeCells('G' . $summaryRow2 . ':I' . $summaryRow2);
$sheet->mergeCells('A' . $summaryRow3 . ':D' . $summaryRow3);
$sheet->mergeCells('G' . $summaryRow3 . ':I' . $summaryRow3);

// ---- Auto-size columns with width limits for printing ----
$columns = range('A', $lastCol);
foreach ($columns as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ---- Set column widths for better printing (wrap text columns) ----
$sheet->getColumnDimension('B')->setWidth(25);  // Expense Name
$sheet->getColumnDimension('C')->setWidth(35);  // Description - wider for wrap text
$sheet->getColumnDimension('D')->setWidth(20);  // Given To
$sheet->getColumnDimension('F')->setWidth(15);  // Amount
$sheet->getColumnDimension('I')->setWidth(18);  // Date

// ---- Set row height for better readability ----
for ($row = $headerRow + 1; $row < $dataRow; $row++) {
    $sheet->getRowDimension($row)->setRowHeight(-1); // Auto height
}

// ---- Set page orientation and margins for printing ----
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
$sheet->getPageMargins()->setTop(0.75);
$sheet->getPageMargins()->setBottom(0.75);
$sheet->getPageMargins()->setLeft(0.7);
$sheet->getPageMargins()->setRight(0.7);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

// ---- Freeze header row ----
$sheet->freezePane('A' . ($headerRow + 1));

// ---- Output file ----
$filename = 'Expense_Logs_' . date('Y-m-d') . '.xlsx';

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