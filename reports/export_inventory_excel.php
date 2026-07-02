<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$role = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

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

// Get filters (same as overview.php)
$filter_category = $_GET['filter_category'] ?? '';
$filter_search = trim($_GET['filter_search'] ?? '');
$filter_start_date = $_GET['filter_start_date'] ?? '';
$filter_end_date = $_GET['filter_end_date'] ?? '';
$filter_branch = $_GET['filter_branch'] ?? '';
$filter_added_by = $_GET['filter_added_by'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

$filters = [
    'category'   => $filter_category,
    'search'     => $filter_search,
    'start_date' => $filter_start_date,
    'end_date'   => $filter_end_date,
    'branch'     => $filter_branch,
    'added_by'   => $filter_added_by,
    'status'     => $filter_status
];

// ---------- fetchAllInventory (exact copy from overview.php) ----------
function fetchAllInventory($conn, $filters) {
    $allItems = [];

    // 1. Devices
    $sql = "SELECT d.model_name AS item_name, 'Device' AS category,
                   d.branch, d.date_added, d.serial_number AS ref_id,
                   'device' AS source, d.added_by, u.full_name AS added_by_name,
                   d.status,
                   CONCAT(
                       d.processor, ' | ',
                       d.ram, 'GB RAM | ',
                       d.storage_type, ' ', d.storage_capacity, 'GB',
                       IFNULL(CONCAT(' | ', d.graphics), ''),
                       IF(c.category_name IN ('Laptop', 'AIO', 'POS'), CONCAT(' | ', d.touch), '')
                   ) AS specs
            FROM devices d
            LEFT JOIN users u ON d.added_by = u.id
            LEFT JOIN categories c ON d.category_id = c.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 2. Monitors
    $sql = "SELECT m.model_name AS item_name, 'Monitor' AS category,
                   m.branch, m.date_added, m.serial_number AS ref_id,
                   'monitor' AS source, m.added_by, u.full_name AS added_by_name,
                   m.status,
                   CONCAT(m.size_inches, ' inch') AS specs
            FROM monitors m
            LEFT JOIN users u ON m.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 3. Printers
    $sql = "SELECT p.model_name AS item_name, 'Printer' AS category,
                   p.branch, p.date_added, p.serial_number AS ref_id,
                   'printer' AS source, p.added_by, u.full_name AS added_by_name,
                   p.status,
                   'N/A' AS specs
            FROM printers p
            LEFT JOIN users u ON p.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 4. Smartboards
    $sql = "SELECT s.model AS item_name, 'Smartboard' AS category,
                   s.branch, s.date_added, s.serial_number AS ref_id,
                   'smartboard' AS source, s.added_by, u.full_name AS added_by_name,
                   s.status,
                   CONCAT(s.model, ' | ', s.size_inches, ' inch') AS specs
            FROM smartboards s
            LEFT JOIN users u ON s.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 5. Phones
    $sql = "SELECT CONCAT(COALESCE(p.brand,''), ' ', COALESCE(p.model,'')) AS item_name,
                   'Phone' AS category,
                   p.branch, p.date_added, p.serial_number AS ref_id,
                   'phone' AS source, p.added_by, u.full_name AS added_by_name,
                   p.status,
                   CONCAT(COALESCE(p.brand,''), ' ', COALESCE(p.model,''), ' | ',
                          p.ram, 'GB RAM | ', p.storage_capacity, 'GB') AS specs
            FROM phones p
            LEFT JOIN users u ON p.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 6. UPS
    $sql = "SELECT u.model AS item_name, 'UPS' AS category,
                   u.branch, u.date_added, u.serial_number AS ref_id,
                   'ups' AS source, u.added_by, usr.full_name AS added_by_name,
                   u.status,
                   CONCAT(u.model, ' | ', u.capacity, ' VA') AS specs
            FROM ups u
            LEFT JOIN users usr ON u.added_by = usr.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 7. Accessories
    $sql = "SELECT a.name AS item_name, 'Accessory' AS category,
                   a.branch, a.date_added, CAST(a.id AS CHAR) AS ref_id,
                   'accessory' AS source, a.added_by, u.full_name AS added_by_name,
                   a.status,
                   CONCAT('Qty: ', a.quantity, ' | ', COALESCE(a.price, 'No price')) AS specs
            FROM accessories a
            LEFT JOIN users u ON a.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 8. Chargers
    $sql = "SELECT c.charger_type AS item_name, 'Charger' AS category,
                   c.branch, c.date_updated AS date_added, CAST(c.id AS CHAR) AS ref_id,
                   'charger' AS source, c.updated_by AS added_by, u.full_name AS added_by_name,
                   IF(c.quantity > 0, 'In Stock', 'Out of Stock') AS status,
                   CONCAT(c.watts, 'W | ', c.charger_condition, ' | Qty: ', c.quantity) AS specs
            FROM chargers c
            LEFT JOIN users u ON c.updated_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 9. HDDs
    $sql = "SELECT CONCAT(h.type, ' ', h.storage) AS item_name, 'HDD' AS category,
                   h.branch, h.date_added, CAST(h.id AS CHAR) AS ref_id,
                   'hdd' AS source, h.added_by, u.full_name AS added_by_name,
                   IF(h.quantity > 0, 'In Stock', 'Out of Stock') AS status,
                   CONCAT('Qty: ', h.quantity, ' | ', COALESCE(h.price, 'No price')) AS specs
            FROM hdds h
            LEFT JOIN users u ON h.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 10. RAM/SSD
    $sql = "SELECT CONCAT(r.category, ' ', r.type, ' ', r.storage, 'GB') AS item_name,
                   'RAM/SSD' AS category,
                   r.branch, r.date_added, CAST(r.id AS CHAR) AS ref_id,
                   'ram_ssd' AS source, r.added_by, u.full_name AS added_by_name,
                   IF(r.quantity > 0, 'In Stock', 'Out of Stock') AS status,
                   CONCAT('Qty: ', r.quantity, ' | ', COALESCE(r.price, 'No price')) AS specs
            FROM rams_ssds r
            LEFT JOIN users u ON r.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // 11. Graphics Cards
    $sql = "SELECT CONCAT(g.type, ' ', g.storage_capacity, 'GB') AS item_name,
                   'Graphics Card' AS category,
                   g.branch, g.date_added, CAST(g.id AS CHAR) AS ref_id,
                   'graphic' AS source, g.added_by, u.full_name AS added_by_name,
                   g.status,
                   CONCAT('Qty: ', g.quantity, ' | ', COALESCE(g.price, 'No price')) AS specs
            FROM graphic_cards g
            LEFT JOIN users u ON g.added_by = u.id";
    $allItems = array_merge($allItems, $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

    // --- Apply filters ---
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $start = date('Y-m-d 00:00:00', strtotime($filters['start_date']));
        $end   = date('Y-m-d 23:59:59', strtotime($filters['end_date']));
        $allItems = array_filter($allItems, function($item) use ($start, $end) {
            if (empty($item['date_added'])) return false;
            $t = strtotime($item['date_added']);
            return $t >= strtotime($start) && $t <= strtotime($end);
        });
    }

    if (!empty($filters['category'])) {
        $allItems = array_filter($allItems, function($item) use ($filters) {
            return strcasecmp($item['category'], $filters['category']) === 0;
        });
    }

    if (!empty($filters['branch'])) {
        $allItems = array_filter($allItems, function($item) use ($filters) {
            return strcasecmp($item['branch'], $filters['branch']) === 0;
        });
    }

    if (!empty($filters['added_by'])) {
        $allItems = array_filter($allItems, function($item) use ($filters) {
            return $item['added_by'] == $filters['added_by'];
        });
    }

    if (!empty($filters['status'])) {
        $statusFilter = $filters['status'];
        $allItems = array_filter($allItems, function($item) use ($statusFilter) {
            $itemStatus = $item['status'] ?? 'Unknown';
            $normalized = '';
            if (strtolower($itemStatus) === 'in stock' || strtolower($itemStatus) === 'instock') {
                $normalized = 'In Stock';
            } elseif (strtolower($itemStatus) === 'sold') {
                $normalized = 'Sold';
            } elseif (strtolower($itemStatus) === 'out of stock') {
                $normalized = 'Out of Stock';
            } else {
                $normalized = $itemStatus;
            }
            return strcasecmp($normalized, $statusFilter) === 0;
        });
    }

    if (!empty($filters['search'])) {
        $search = strtolower($filters['search']);
        $allItems = array_filter($allItems, function($item) use ($search) {
            $name = strtolower($item['item_name'] ?? '');
            $ref = strtolower($item['ref_id'] ?? '');
            $specs = strtolower($item['specs'] ?? '');
            return strpos($name, $search) !== false ||
                   strpos($ref, $search) !== false ||
                   strpos($specs, $search) !== false;
        });
    }

    usort($allItems, function($a, $b) {
        $ta = $a['date_added'] ? strtotime($a['date_added']) : 0;
        $tb = $b['date_added'] ? strtotime($b['date_added']) : 0;
        return $tb - $ta;
    });

    return $allItems;
}

$items = fetchAllInventory($conn, $filters);

if (empty($items)) {
    die("No data to export.");
}

// --------------------------------------------------------------
// Create spreadsheet
// --------------------------------------------------------------
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Inventory Overview');

// ---- Set column widths ----
// Use auto-size for all columns (especially for specs)
$sheet->getColumnDimension('A')->setWidth(6);   // #
$sheet->getColumnDimension('B')->setWidth(35);  // Item Name
$sheet->getColumnDimension('C')->setWidth(15);  // Category
$sheet->getColumnDimension('D')->setWidth(12);  // Branch
$sheet->getColumnDimension('E')->setWidth(22);  // Added By
$sheet->getColumnDimension('F')->setWidth(12);  // Status
$sheet->getColumnDimension('G')->setWidth(18);  // Date Added
$sheet->getColumnDimension('H')->setWidth(18);  // Reference
// For Specifications, use auto-size to fit the content
$sheet->getColumnDimension('I')->setAutoSize(true);
// Enable wrap text for specs so long strings break into multiple lines
$sheet->getStyle('I')->getAlignment()->setWrapText(true);

$row = 1;

// ---- Title ----
$sheet->setCellValue('A' . $row, 'Inventory Overview');
$sheet->mergeCells('A' . $row . ':I' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

// ---- Filter Criteria Note ----
$filterNote = "Filters applied: ";
$criteria = [];
if (!empty($filter_category)) $criteria[] = "Category: " . $filter_category;
if (!empty($filter_search)) $criteria[] = "Search: '" . $filter_search . "'";
if (!empty($filter_start_date) && !empty($filter_end_date)) $criteria[] = "Date: " . $filter_start_date . " to " . $filter_end_date;
if (!empty($filter_added_by)) {
    $userStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $userStmt->execute([$filter_added_by]);
    $userName = $userStmt->fetchColumn();
    if ($userName) $criteria[] = "Added By: " . $userName;
}
if (!empty($filter_branch)) $criteria[] = "Branch: " . $filter_branch;
if (!empty($filter_status)) $criteria[] = "Status: " . $filter_status;
$filterNote .= !empty($criteria) ? implode(', ', $criteria) : "None (All data)";

$sheet->setCellValue('A' . $row, $filterNote);
$sheet->mergeCells('A' . $row . ':I' . $row);
$sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$row++;

// Blank row
$row++;

// ---- Headers ----
$headers = ['#', 'Item Name', 'Category', 'Branch', 'Added By', 'Status', 'Date Added', 'Reference', 'Specifications'];
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
foreach ($items as $item) {
    $status = $item['status'] ?? 'Unknown';
    if (strtolower($status) === 'in stock' || strtolower($status) === 'instock') $displayStatus = 'In Stock';
    elseif (strtolower($status) === 'sold') $displayStatus = 'Sold';
    elseif (strtolower($status) === 'out of stock') $displayStatus = 'Out of Stock';
    else $displayStatus = $status;

    $sheet->setCellValue('A' . $dataRow, $i++);
    $sheet->setCellValue('B' . $dataRow, $item['item_name']);
    $sheet->setCellValue('C' . $dataRow, $item['category']);
    $sheet->setCellValue('D' . $dataRow, $item['branch'] ?? '-');
    $sheet->setCellValue('E' . $dataRow, $item['added_by_name'] ?? '-');
    $sheet->setCellValue('F' . $dataRow, $displayStatus);
    $sheet->setCellValue('G' . $dataRow, $item['date_added'] ? date('Y-m-d H:i:s', strtotime($item['date_added'])) : '-');
    $sheet->setCellValue('H' . $dataRow, $item['ref_id'] ?? '-');
    $sheet->setCellValue('I' . $dataRow, $item['specs'] ?? '-');
    $dataRow++;
}

// ---- Borders for data ----
$sheet->getStyle('A' . ($headerRow+1) . ':I' . ($dataRow-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ---- Summary row ----
$summaryRow = $dataRow;
$sheet->setCellValue('A' . $summaryRow, '');
$sheet->setCellValue('B' . $summaryRow, '');
$sheet->setCellValue('C' . $summaryRow, '');
$sheet->setCellValue('D' . $summaryRow, '');
$sheet->setCellValue('E' . $summaryRow, 'TOTAL:');
$sheet->setCellValue('F' . $summaryRow, count($items));
$sheet->setCellValue('G' . $summaryRow, '');
$sheet->setCellValue('H' . $summaryRow, '');
$sheet->setCellValue('I' . $summaryRow, '');
$sheet->getStyle('E' . $summaryRow . ':F' . $summaryRow)->getFont()->setBold(true);
$sheet->mergeCells('A' . $summaryRow . ':A' . $summaryRow);
$sheet->mergeCells('B' . $summaryRow . ':B' . $summaryRow);
$sheet->mergeCells('C' . $summaryRow . ':C' . $summaryRow);
$sheet->mergeCells('D' . $summaryRow . ':D' . $summaryRow);
$sheet->mergeCells('G' . $summaryRow . ':I' . $summaryRow);

// ---- Output ----
$filename = 'Inventory_Overview_' . date('Y-m-d') . '.xlsx';

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