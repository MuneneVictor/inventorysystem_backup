<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only sales role can export
if ($_SESSION['role'] !== 'sales') {
    die("ACCESS DENIED.");
}

// Load PhpSpreadsheet
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$user_id = (int) $_SESSION['user_id'];

// Get filters (same as my_sales.php)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';

// Reuse the fetch function from my_sales.php (copy it here or include it)
// For simplicity, we'll include the same function.
function fetchUnifiedSales($conn, $user_id, $start_date, $end_date, $search = '') {
    $allSales = [];

    $queries = [
        "SELECT 
            model_name AS item_name,
            'Device' AS category,
            serial_number AS id,
            selling_price AS price,
            sold_at,
            branch
        FROM devices
        WHERE status = 'Sold' AND sold_by = :uid",
        "SELECT 
            model_name AS item_name,
            'Monitor' AS category,
            serial_number AS id,
            selling_price AS price,
            sold_at,
            branch
        FROM monitors
        WHERE status = 'Sold' AND sold_by = :uid",
        "SELECT 
            model_name AS item_name,
            'Printer' AS category,
            serial_number AS id,
            selling_price AS price,
            date_sold AS sold_at,
            branch
        FROM printers
        WHERE status = 'Sold' AND sold_by = :uid",
        "SELECT 
            model AS item_name,
            'Smartboard' AS category,
            serial_number AS id,
            selling_price AS price,
            sold_at,
            branch
        FROM smartboards
        WHERE status = 'sold' AND sold_by = :uid",
        "SELECT 
            accessory_name AS item_name,
            'Accessory' AS category,
            CAST(accessory_id AS CHAR) AS id,
            selling_price AS price,
            date_sold AS sold_at,
            branch
        FROM sold_accessories
        WHERE sold_by = :uid",
        "SELECT 
            charger_type AS item_name,
            'Charger' AS category,
            CAST(charger_id AS CHAR) AS id,
            selling_price AS price,
            date_sold AS sold_at,
            branch
        FROM sold_chargers
        WHERE sold_by = :uid",
        "SELECT 
            CONCAT(COALESCE(brand,''), ' ', COALESCE(model,'')) AS item_name,
            'Phone' AS category,
            serial_number AS id,
            selling_price AS price,
            date_sold AS sold_at,
            branch
        FROM phones
        WHERE status = 'sold' AND sold_by = :uid",
        "SELECT 
            model AS item_name,
            'UPS' AS category,
            serial_number AS id,
            selling_price AS price,
            date_sold AS sold_at,
            branch
        FROM ups
        WHERE status = 'sold' AND sold_by = :uid",
        "SELECT 
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage,''), 'GB') AS item_name,
            category AS category,
            CONCAT('ID:', ram_ssd_id) AS id,
            total_price AS price,
            date_sold AS sold_at,
            branch
        FROM sold_rams_ssds
        WHERE sold_by = :uid",
        "SELECT 
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage,'')) AS item_name,
            'HDD' AS category,
            CONCAT('ID:', hdd_id) AS id,
            total_price AS price,
            date_sold AS sold_at,
            branch
        FROM sold_hdds
        WHERE sold_by = :uid",
        "SELECT 
            CONCAT(COALESCE(type,''), ' ', COALESCE(storage_capacity,''), 'GB') AS item_name,
            'Graphics Card' AS category,
            CONCAT('ID:', graphic_card_id) AS id,
            total_price AS price,
            date_sold AS sold_at,
            branch
        FROM sold_graphics_cards
        WHERE sold_by = :uid"
    ];

    foreach ($queries as $sql) {
        $stmt = $conn->prepare($sql);
        $stmt->execute(['uid' => $user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $allSales = array_merge($allSales, $rows);
    }

    if (!empty($start_date) && !empty($end_date)) {
        $allSales = array_filter($allSales, function($sale) use ($start_date, $end_date) {
            $sold_at = strtotime($sale['sold_at']);
            return $sold_at >= strtotime($start_date) && $sold_at <= strtotime($end_date . ' 23:59:59');
        });
    }

    if (!empty($search)) {
        $searchLower = strtolower($search);
        $allSales = array_filter($allSales, function($sale) use ($searchLower) {
            return stripos($sale['item_name'], $searchLower) !== false ||
                   stripos($sale['id'], $searchLower) !== false;
        });
    }

    usort($allSales, function($a, $b) {
        return strtotime($b['sold_at']) - strtotime($a['sold_at']);
    });

    return $allSales;
}

$sales = fetchUnifiedSales($conn, $user_id, $start_date, $end_date, $search);

if (empty($sales)) {
    die("No sales data to export.");
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set title
$sheet->setTitle('My Sales');

// Headers
$headers = ['#', 'Item Name', 'Category', 'ID / Serial', 'Price (KES)', 'Branch', 'Date Sold'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $col++;
}

// Data
$row = 2;
$i = 1;
foreach ($sales as $sale) {
    $sheet->setCellValue('A' . $row, $i++);
    $sheet->setCellValue('B' . $row, $sale['item_name']);
    $sheet->setCellValue('C' . $row, $sale['category']);
    $sheet->setCellValue('D' . $row, $sale['id'] ?? '-');
    $sheet->setCellValue('E' . $row, $sale['price'] ?? 0);
    $sheet->setCellValue('F' . $row, $sale['branch'] ?? '-');
    $sheet->setCellValue('G' . $row, date('Y-m-d H:i:s', strtotime($sale['sold_at'])));
    $row++;
}

// Style header row
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A4B2A']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

// Auto-size columns (this prevents shrinking)
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Set number format for price column (E)
$sheet->getStyle('E2:E' . ($row-1))->getNumberFormat()->setFormatCode('#,##0.00');

// Center align numeric columns
$sheet->getStyle('A2:A' . ($row-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('E2:E' . ($row-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// Borders for data
$sheet->getStyle('A2:G' . ($row-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Add summary row below data
$summaryRow = $row;
$sheet->setCellValue('A' . $summaryRow, '');
$sheet->setCellValue('B' . $summaryRow, '');
$sheet->setCellValue('C' . $summaryRow, '');
$sheet->setCellValue('D' . $summaryRow, 'TOTAL:');
$sheet->setCellValue('E' . $summaryRow, array_sum(array_column($sales, 'price')));
$sheet->setCellValue('F' . $summaryRow, '');
$sheet->setCellValue('G' . $summaryRow, '');
$sheet->getStyle('D' . $summaryRow . ':E' . $summaryRow)->getFont()->setBold(true);
$sheet->getStyle('E' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0.00');

// Merge cells for total label
$sheet->mergeCells('D' . $summaryRow . ':D' . $summaryRow);
$sheet->mergeCells('F' . $summaryRow . ':F' . $summaryRow);
$sheet->mergeCells('G' . $summaryRow . ':G' . $summaryRow);

// Set filename
$filename = 'My_Sales_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;