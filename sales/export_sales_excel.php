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
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'User');

// Get filters (same as my_sales.php)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================
// FETCH ALL SALES FOR THIS SALESPERSON (with specs & condition)
// ============================================================
function fetchUnifiedSales($conn, $user_id, $start_date, $end_date, $search = '') {
    $allSales = [];

    // 1. Devices
    $sql = "SELECT 
                model_name AS item_name,
                'Device' AS category,
                serial_number AS id,
                selling_price AS price,
                sold_at,
                branch,
                TRIM(CONCAT(
                    COALESCE(processor, ''),
                    IF(processor IS NOT NULL, ' | ', ''),
                    COALESCE(ram, ''), IF(ram IS NOT NULL, 'GB RAM', ''),
                    IF(storage_type IS NOT NULL AND storage_capacity IS NOT NULL, CONCAT(' | ', storage_type, ' ', storage_capacity, 'GB'), ''),
                    IF(graphics IS NOT NULL AND graphics != '', CONCAT(' | ', graphics), ''),
                    IF(touch IS NOT NULL AND touch != 'N/A', CONCAT(' | ', touch), ''),
                    IF(device_condition IS NOT NULL AND device_condition != '', CONCAT(' | ', device_condition), '')
                )) AS specs
            FROM devices
            WHERE status = 'Sold' AND sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 2. Monitors
    $sql = "SELECT 
                model_name AS item_name,
                'Monitor' AS category,
                serial_number AS id,
                selling_price AS price,
                sold_at,
                branch,
                TRIM(CONCAT(
                    size_inches, ' inch',
                    IF(monitor_condition IS NOT NULL AND monitor_condition != '', CONCAT(' | ', monitor_condition), '')
                )) AS specs
            FROM monitors
            WHERE status = 'Sold' AND sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 3. Printers
    $sql = "SELECT 
                model_name AS item_name,
                'Printer' AS category,
                serial_number AS id,
                selling_price AS price,
                date_sold AS sold_at,
                branch,
                TRIM(CONCAT(
                    'N/A',
                    IF(printer_condition IS NOT NULL AND printer_condition != '', CONCAT(' | ', printer_condition), '')
                )) AS specs
            FROM printers
            WHERE status = 'Sold' AND sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 4. Smartboards (no condition field)
    $sql = "SELECT 
                model AS item_name,
                'Smartboard' AS category,
                serial_number AS id,
                selling_price AS price,
                sold_at,
                branch,
                CONCAT(model, ' | ', size_inches, ' inch') AS specs
            FROM smartboards
            WHERE status = 'sold' AND sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 5. Sold Accessories
    $sql = "SELECT 
                accessory_name AS item_name,
                'Accessory' AS category,
                CAST(accessory_id AS CHAR) AS id,
                total_price AS price,
                date_sold AS sold_at,
                branch,
                CONCAT(quantity, ' x ', selling_price, ' = ', total_price) AS specs
            FROM sold_accessories
            WHERE sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 6. Sold Chargers
    $sql = "SELECT 
                charger_type AS item_name,
                'Charger' AS category,
                CAST(charger_id AS CHAR) AS id,
                total_price AS price,
                date_sold AS sold_at,
                branch,
                CONCAT(quantity, ' x ', charger_condition, ' | ', selling_price, ' each') AS specs
            FROM sold_chargers
            WHERE sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 7. Phones
    $sql = "SELECT 
                CONCAT(COALESCE(brand,''), ' ', COALESCE(model,'')) AS item_name,
                'Phone' AS category,
                serial_number AS id,
                selling_price AS price,
                date_sold AS sold_at,
                branch,
                TRIM(CONCAT(
                    COALESCE(brand,''), ' ', COALESCE(model,''), ' | ',
                    ram, 'GB RAM | ',
                    storage_capacity, 'GB',
                    IF(phone_condition IS NOT NULL AND phone_condition != '', CONCAT(' | ', phone_condition), '')
                )) AS specs
            FROM phones
            WHERE status = 'sold' AND sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 8. UPS
    $sql = "SELECT 
                model AS item_name,
                'UPS' AS category,
                serial_number AS id,
                selling_price AS price,
                date_sold AS sold_at,
                branch,
                TRIM(CONCAT(
                    model, ' | ', capacity, ' VA',
                    IF(ups_condition IS NOT NULL AND ups_condition != '', CONCAT(' | ', ups_condition), '')
                )) AS specs
            FROM ups
            WHERE status = 'sold' AND sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 9. Sold RAM/SSD
    $sql = "SELECT 
                CONCAT(COALESCE(type,''), ' ', COALESCE(storage,''), 'GB') AS item_name,
                category AS category,
                CONCAT('ID:', ram_ssd_id) AS id,
                total_price AS price,
                date_sold AS sold_at,
                branch,
                CONCAT(quantity, ' x ', type, ' ', storage, 'GB') AS specs
            FROM sold_rams_ssds
            WHERE sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 10. Sold HDDs
    $sql = "SELECT 
                CONCAT(COALESCE(type,''), ' ', COALESCE(storage,'')) AS item_name,
                'HDD' AS category,
                CONCAT('ID:', hdd_id) AS id,
                total_price AS price,
                date_sold AS sold_at,
                branch,
                CONCAT(quantity, ' x ', type, ' ', storage) AS specs
            FROM sold_hdds
            WHERE sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // 11. Sold Graphics Cards
    $sql = "SELECT 
                CONCAT(COALESCE(type,''), ' ', COALESCE(storage_capacity,''), 'GB') AS item_name,
                'Graphics Card' AS category,
                CONCAT('ID:', graphic_card_id) AS id,
                total_price AS price,
                date_sold AS sold_at,
                branch,
                CONCAT(quantity, ' x ', type, ' ', storage_capacity, 'GB') AS specs
            FROM sold_graphics_cards
            WHERE sold_by = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSales = array_merge($allSales, $rows);

    // Date range filter
    if (!empty($start_date) && !empty($end_date)) {
        $allSales = array_filter($allSales, function($sale) use ($start_date, $end_date) {
            $sold_at = strtotime($sale['sold_at']);
            return $sold_at >= strtotime($start_date) && $sold_at <= strtotime($end_date . ' 23:59:59');
        });
    }

    // Search filter
    if (!empty($search)) {
        $searchLower = strtolower($search);
        $allSales = array_filter($allSales, function($sale) use ($searchLower) {
            return stripos($sale['item_name'], $searchLower) !== false ||
                   stripos($sale['id'], $searchLower) !== false ||
                   stripos($sale['specs'] ?? '', $searchLower) !== false;
        });
    }

    // Sort by sold_at descending
    usort($allSales, function($a, $b) {
        return strtotime($b['sold_at']) - strtotime($a['sold_at']);
    });

    return $allSales;
}

$sales = fetchUnifiedSales($conn, $user_id, $start_date, $end_date, $search);

if (empty($sales)) {
    die("No sales data to export.");
}

// ============================================================
// Create spreadsheet
// ============================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('My Sales');

// ---- Set column widths ----
$sheet->getColumnDimension('A')->setWidth(6);   // #
$sheet->getColumnDimension('B')->setWidth(30);  // Item Name
$sheet->getColumnDimension('C')->setWidth(15);  // Category
$sheet->getColumnDimension('D')->setWidth(18);  // ID / Serial
$sheet->getColumnDimension('E')->setAutoSize(true); // Specifications
$sheet->getColumnDimension('F')->setWidth(15);  // Price (KES)
$sheet->getColumnDimension('G')->setWidth(12);  // Branch
$sheet->getColumnDimension('H')->setWidth(20);  // Date Sold
// Enable wrap text for specs so long content wraps
$sheet->getStyle('E')->getAlignment()->setWrapText(true);

$row = 1;

// ---- Title ----
$sheet->setCellValue('A' . $row, 'My Sales');
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

// ---- Filter Criteria Note ----
$filterNote = "Filters applied: ";
$criteria = [];
if (!empty($start_date) && !empty($end_date)) $criteria[] = "Date: " . $start_date . " to " . $end_date;
if (!empty($search)) $criteria[] = "Search: '" . $search . "'";
$filterNote .= !empty($criteria) ? implode(', ', $criteria) : "None (All data)";

$sheet->setCellValue('A' . $row, $filterNote);
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$row++;

// Blank row
$row++;

// ---- Headers ----
$headers = ['#', 'Item Name', 'Category', 'ID / Serial', 'Specifications', 'Price (KES)', 'Branch', 'Date Sold'];
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
foreach ($sales as $sale) {
    $sheet->setCellValue('A' . $dataRow, $i++);
    $sheet->setCellValue('B' . $dataRow, $sale['item_name']);
    $sheet->setCellValue('C' . $dataRow, $sale['category']);
    $sheet->setCellValue('D' . $dataRow, $sale['id'] ?? '-');
    $sheet->setCellValue('E' . $dataRow, $sale['specs'] ?? '-');
    $sheet->setCellValue('F' . $dataRow, $sale['price'] ?? 0);
    $sheet->setCellValue('G' . $dataRow, $sale['branch'] ?? '-');
    $sheet->setCellValue('H' . $dataRow, date('Y-m-d H:i:s', strtotime($sale['sold_at'])));
    $dataRow++;
}

// ---- Borders for data ----
$sheet->getStyle('A' . ($headerRow+1) . ':H' . ($dataRow-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ---- Number format for Price ----
$sheet->getStyle('F' . ($headerRow+1) . ':F' . ($dataRow-1))->getNumberFormat()->setFormatCode('#,##0.00');

// ---- Summary row ----
$summaryRow = $dataRow;
$sheet->setCellValue('A' . $summaryRow, '');
$sheet->setCellValue('B' . $summaryRow, '');
$sheet->setCellValue('C' . $summaryRow, '');
$sheet->setCellValue('D' . $summaryRow, 'TOTAL:');
$sheet->setCellValue('E' . $summaryRow, count($sales) . ' items');
$sheet->setCellValue('F' . $summaryRow, array_sum(array_column($sales, 'price')));
$sheet->setCellValue('G' . $summaryRow, '');
$sheet->setCellValue('H' . $summaryRow, '');
$sheet->getStyle('D' . $summaryRow . ':F' . $summaryRow)->getFont()->setBold(true);
$sheet->getStyle('F' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0.00');
// Merge empty cells for alignment
$sheet->mergeCells('A' . $summaryRow . ':A' . $summaryRow);
$sheet->mergeCells('B' . $summaryRow . ':B' . $summaryRow);
$sheet->mergeCells('C' . $summaryRow . ':C' . $summaryRow);
$sheet->mergeCells('G' . $summaryRow . ':H' . $summaryRow);

// ---- Output ----
$filename = 'My_Sales_' . date('Y-m-d') . '.xlsx';

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