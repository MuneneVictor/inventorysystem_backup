<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

if (!in_array($role, ['super_admin', 'manager', 'cashier'])) {
    die("ACCESS DENIED.");
}

// Load PhpSpreadsheet
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Get filters
$filter_category = $_GET['filter_category'] ?? '';
$filter_search = trim($_GET['filter_search'] ?? '');
$filter_start_date = $_GET['filter_start_date'] ?? '';
$filter_end_date = $_GET['filter_end_date'] ?? '';
$filter_user = $_GET['filter_user'] ?? '';
$filter_branch = $_GET['filter_branch'] ?? '';

// Manager branch restriction
$user_branch = null;
if ($role === 'manager') {
    $stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_branch = $stmt->fetchColumn();
}

$filters = [
    'category'   => $filter_category,
    'search'     => $filter_search,
    'start_date' => $filter_start_date,
    'end_date'   => $filter_end_date,
    'user_id'    => ($role === 'super_admin' && !empty($filter_user)) ? (int)$filter_user : null,
    'branch'     => ($role === 'super_admin' && !empty($filter_branch)) ? $filter_branch : ($role === 'manager' ? $user_branch : null)
];

// ---------- fetchAllSales function (unchanged – same as sales_logs.php) ----------
function fetchAllSales($conn, $filters) {
    $allSales = [];

    // 1. Devices – include touch for Laptop, AIO, POS
    $sql = "SELECT d.model_name AS item_name, 'Device' AS category,
                d.serial_number AS id, d.selling_price AS price,
                d.sold_at, d.branch, d.sold_by, u.full_name AS sold_by_name,
                CONCAT(
                    d.processor, ' | ',
                    d.ram, 'GB RAM | ',
                    d.storage_type, ' ', d.storage_capacity, 'GB',
                    IFNULL(CONCAT(' | ', d.graphics), ''),
                    IF(c.category_name IN ('Laptop', 'AIO', 'POS'), CONCAT(' | ', d.touch), '')
                ) AS specs
            FROM devices d
            LEFT JOIN users u ON d.sold_by = u.id
            LEFT JOIN categories c ON d.category_id = c.id
            WHERE d.status = 'Sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Monitors
    $sql = "SELECT m.model_name AS item_name, 'Monitor' AS category, 
                   m.serial_number AS id, m.selling_price AS price, 
                   m.sold_at, m.branch, m.sold_by, u.full_name AS sold_by_name,
                   CONCAT(m.size_inches, ' inch') AS specs
            FROM monitors m
            LEFT JOIN users u ON m.sold_by = u.id
            WHERE m.status = 'Sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Printers
    $sql = "SELECT p.model_name AS item_name, 'Printer' AS category, 
                   p.serial_number AS id, p.selling_price AS price, 
                   p.date_sold AS sold_at, p.branch, p.sold_by, u.full_name AS sold_by_name,
                   'N/A' AS specs
            FROM printers p
            LEFT JOIN users u ON p.sold_by = u.id
            WHERE p.status = 'Sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Smartboards
    $sql = "SELECT s.model AS item_name, 'Smartboard' AS category, 
                   s.serial_number AS id, s.selling_price AS price, 
                   s.sold_at, s.branch, s.sold_by, u.full_name AS sold_by_name,
                   CONCAT(s.model, ' | ', s.size_inches, ' inch') AS specs
            FROM smartboards s
            LEFT JOIN users u ON s.sold_by = u.id
            WHERE s.status = 'sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // UPS
    $sql = "SELECT ups.model AS item_name, 'UPS' AS category, 
                   ups.serial_number AS id, ups.selling_price AS price, 
                   ups.date_sold AS sold_at, ups.branch, ups.sold_by, u.full_name AS sold_by_name,
                   CONCAT(ups.model, ' | ', ups.capacity, ' VA') AS specs
            FROM ups
            LEFT JOIN users u ON ups.sold_by = u.id
            WHERE ups.status = 'sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Phones
    $sql = "SELECT CONCAT(COALESCE(p.brand,''), ' ', COALESCE(p.model,'')) AS item_name, 'Phone' AS category, 
                   p.serial_number AS id, p.selling_price AS price, 
                   p.date_sold AS sold_at, p.branch, p.sold_by, u.full_name AS sold_by_name,
                   CONCAT(COALESCE(p.brand,''), ' ', COALESCE(p.model,''), ' | ', p.ram, 'GB RAM | ', p.storage_capacity, 'GB') AS specs
            FROM phones p
            LEFT JOIN users u ON p.sold_by = u.id
            WHERE p.status = 'sold'";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Sold Accessories
    $sql = "SELECT sa.accessory_name AS item_name, 'Accessory' AS category, 
                   NULL AS id, sa.total_price AS price, 
                   sa.date_sold AS sold_at, sa.branch, sa.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sa.quantity, ' x ', sa.selling_price, ' = ', sa.total_price) AS specs
            FROM sold_accessories sa
            LEFT JOIN users u ON sa.sold_by = u.id";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Sold Chargers
    $sql = "SELECT sc.charger_type AS item_name, 'Charger' AS category, 
                   NULL AS id, sc.total_price AS price, 
                   sc.date_sold AS sold_at, sc.branch, sc.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sc.quantity, ' x ', sc.charger_condition, ' | ', sc.selling_price, ' each') AS specs
            FROM sold_chargers sc
            LEFT JOIN users u ON sc.sold_by = u.id";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Sold Graphics Cards
    $sql = "SELECT CONCAT(COALESCE(sgc.type,''), ' ', COALESCE(sgc.storage_capacity,''), 'GB') AS item_name, 'Graphics Card' AS category, 
                   NULL AS id, sgc.total_price AS price, 
                   sgc.date_sold AS sold_at, sgc.branch, sgc.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sgc.quantity, ' x ', sgc.type, ' ', sgc.storage_capacity, 'GB') AS specs
            FROM sold_graphics_cards sgc
            LEFT JOIN users u ON sgc.sold_by = u.id";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Sold HDDs
    $sql = "SELECT CONCAT(COALESCE(sh.type,''), ' ', COALESCE(sh.storage,'')) AS item_name, 'HDD' AS category, 
                   NULL AS id, sh.total_price AS price, 
                   sh.date_sold AS sold_at, sh.branch, sh.sold_by, u.full_name AS sold_by_name,
                   CONCAT(sh.quantity, ' x ', sh.type, ' ', sh.storage) AS specs
            FROM sold_hdds sh
            LEFT JOIN users u ON sh.sold_by = u.id";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // Sold RAM/SSD
    $sql = "SELECT CONCAT(COALESCE(srs.type,''), ' ', COALESCE(srs.storage,''), 'GB') AS item_name, srs.category AS category, 
                   NULL AS id, srs.total_price AS price, 
                   srs.date_sold AS sold_at, srs.branch, srs.sold_by, u.full_name AS sold_by_name,
                   CONCAT(srs.quantity, ' x ', srs.type, ' ', srs.storage, 'GB') AS specs
            FROM sold_rams_ssds srs
            LEFT JOIN users u ON srs.sold_by = u.id";
    $allSales = array_merge($allSales, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // --- Apply filters ---
    $startDate = $filters['start_date'] ?? '';
    $endDate = $filters['end_date'] ?? '';
    if (!empty($startDate) && !empty($endDate)) {
        $start = date('Y-m-d 00:00:00', strtotime($startDate));
        $end   = date('Y-m-d 23:59:59', strtotime($endDate));
        $allSales = array_filter($allSales, function($s) use ($start, $end) {
            if (empty($s['sold_at'])) return false;
            $t = strtotime($s['sold_at']);
            return $t >= strtotime($start) && $t <= strtotime($end);
        });
    }

    if (!empty($filters['category'])) {
        $allSales = array_filter($allSales, function($s) use ($filters) {
            return strcasecmp($s['category'], $filters['category']) === 0;
        });
    }

    if (!empty($filters['search'])) {
        $search = strtolower($filters['search']);
        $allSales = array_filter($allSales, function($s) use ($search) {
            $item = strtolower($s['item_name'] ?? '');
            $id = strtolower($s['id'] ?? '');
            $spec = strtolower($s['specs'] ?? '');
            return strpos($item, $search) !== false || strpos($id, $search) !== false || strpos($spec, $search) !== false;
        });
    }

    if (!empty($filters['user_id'])) {
        $allSales = array_filter($allSales, function($s) use ($filters) {
            return $s['sold_by'] == $filters['user_id'];
        });
    }

    if (!empty($filters['branch'])) {
        $allSales = array_filter($allSales, function($s) use ($filters) {
            return strcasecmp($s['branch'], $filters['branch']) === 0;
        });
    }

    usort($allSales, function($a, $b) {
        $ta = $a['sold_at'] ? strtotime($a['sold_at']) : 0;
        $tb = $b['sold_at'] ? strtotime($b['sold_at']) : 0;
        return $tb - $ta;
    });

    return $allSales;
}

$sales = fetchAllSales($conn, $filters);

if (empty($sales)) {
    die("No sales data to export.");
}

// --------------------------------------------------------------
// Create spreadsheet and set up layout
// --------------------------------------------------------------
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sales Logs');

// --- Set up header rows (logo, title, filter note) ---
$row = 1;

// 1. Logo (merged across A:I, centered)
$logoPath = __DIR__ . '/../assets/MC-LOGO.png';
if (file_exists($logoPath)) {
    $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
    $drawing->setName('Company Logo');
    $drawing->setDescription('Mombasa Computers Logo');
    $drawing->setPath($logoPath);
    $drawing->setHeight(80);
    // Place in cell A1
    $drawing->setCoordinates('A' . $row);
    $drawing->setWorksheet($sheet);
    // Merge cells A:I for the logo row to center it visually
    $sheet->mergeCells('A' . $row . ':I' . $row);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension($row)->setRowHeight(90); // make room for logo
    $row++;
}

// 2. Title: "Sales Logs"
$sheet->setCellValue('A' . $row, 'Sales Logs');
$sheet->mergeCells('A' . $row . ':I' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

// 3. Filter criteria note
$filterNote = "Filters applied: ";
$criteria = [];
if (!empty($filter_category)) $criteria[] = "Category: " . $filter_category;
if (!empty($filter_search)) $criteria[] = "Search: '" . $filter_search . "'";
if (!empty($filter_start_date) && !empty($filter_end_date)) $criteria[] = "Date: " . $filter_start_date . " to " . $filter_end_date;
if (!empty($filter_user) && $role === 'super_admin') {
    $userStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $userStmt->execute([$filter_user]);
    $userName = $userStmt->fetchColumn();
    if ($userName) $criteria[] = "Salesperson: " . $userName;
}
if (!empty($filter_branch) && $role === 'super_admin') $criteria[] = "Branch: " . $filter_branch;
if ($role === 'manager' && !empty($user_branch)) $criteria[] = "Branch: " . $user_branch;
$filterNote .= !empty($criteria) ? implode(', ', $criteria) : "None (All data)";

$sheet->setCellValue('A' . $row, $filterNote);
$sheet->mergeCells('A' . $row . ':I' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$row++;

// Add a blank row for spacing
$row++;

// Now $row points to the first row of the header (for table)
$headerRow = $row;

// --- Headers ---
$headers = ['#', 'Item Name', 'Category', 'ID / Serial', 'Specifications', 'Price (KES)', 'Sold By', 'Branch', 'Date Sold'];
$col = 'A';
foreach ($headers as $idx => $header) {
    $sheet->setCellValue(chr(65 + $idx) . $headerRow, $header);
}

// --- Data rows ---
$dataRow = $headerRow + 1;
$i = 1;
foreach ($sales as $sale) {
    $sheet->setCellValue('A' . $dataRow, $i++);
    $sheet->setCellValue('B' . $dataRow, $sale['item_name']);
    $sheet->setCellValue('C' . $dataRow, $sale['category']);
    $sheet->setCellValue('D' . $dataRow, $sale['id'] ?? '-');
    $sheet->setCellValue('E' . $dataRow, $sale['specs'] ?? '-');
    $sheet->setCellValue('F' . $dataRow, $sale['price'] ?? 0);
    $sheet->setCellValue('G' . $dataRow, $sale['sold_by_name'] ?? 'Unknown');
    $sheet->setCellValue('H' . $dataRow, $sale['branch'] ?? '-');
    $sheet->setCellValue('I' . $dataRow, $sale['sold_at'] ? date('Y-m-d H:i:s', strtotime($sale['sold_at'])) : '-');
    $dataRow++;
}

// --- Style header row ---
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A4B2A']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A' . $headerRow . ':I' . $headerRow)->applyFromArray($headerStyle);

// --- Auto-size columns ---
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// --- Number format for price column (F) ---
$sheet->getStyle('F' . ($headerRow+1) . ':F' . ($dataRow-1))->getNumberFormat()->setFormatCode('#,##0.00');

// --- Alignment ---
$sheet->getStyle('A' . ($headerRow+1) . ':A' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('C' . ($headerRow+1) . ':C' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('F' . ($headerRow+1) . ':F' . ($dataRow-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// --- Borders for data ---
$sheet->getStyle('A' . ($headerRow+1) . ':I' . ($dataRow-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// --- Summary row ---
$summaryRow = $dataRow;
$sheet->setCellValue('A' . $summaryRow, '');
$sheet->setCellValue('B' . $summaryRow, '');
$sheet->setCellValue('C' . $summaryRow, '');
$sheet->setCellValue('D' . $summaryRow, '');
$sheet->setCellValue('E' . $summaryRow, 'TOTAL:');
$sheet->setCellValue('F' . $summaryRow, array_sum(array_column($sales, 'price')));
$sheet->setCellValue('G' . $summaryRow, '');
$sheet->setCellValue('H' . $summaryRow, '');
$sheet->setCellValue('I' . $summaryRow, '');
$sheet->getStyle('E' . $summaryRow . ':F' . $summaryRow)->getFont()->setBold(true);
$sheet->getStyle('F' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0.00');
// Merge cells for alignment
$sheet->mergeCells('A' . $summaryRow . ':A' . $summaryRow);
$sheet->mergeCells('B' . $summaryRow . ':B' . $summaryRow);
$sheet->mergeCells('C' . $summaryRow . ':C' . $summaryRow);
$sheet->mergeCells('D' . $summaryRow . ':D' . $summaryRow);
$sheet->mergeCells('G' . $summaryRow . ':G' . $summaryRow);
$sheet->mergeCells('H' . $summaryRow . ':H' . $summaryRow);
$sheet->mergeCells('I' . $summaryRow . ':I' . $summaryRow);

// --- Output file ---
$filename = 'Sales_Logs_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;