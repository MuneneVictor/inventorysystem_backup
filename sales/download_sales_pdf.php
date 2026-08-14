<?php
date_default_timezone_set('Africa/Nairobi');
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

require_once "../tcpdf_min/tcpdf.php"; // Ensure tcpdf_min folder exists

if($_SESSION['role'] !== 'sales'){
    die("Access denied!");
}

$user_id = $_SESSION['user_id'];
$filter_time = $_GET['filter_time'] ?? '';
$search_sn = trim($_GET['sn'] ?? '');

// Fetch sales data
$sql = "
    SELECT sd.*, c.category_name, u.full_name AS sold_by_name
    FROM sold_devices sd
    LEFT JOIN categories c ON sd.category_id = c.id
    LEFT JOIN users u ON sd.sold_by = u.id
    WHERE sd.sold_by = :uid
";
$params = ['uid' => $user_id];

if ($filter_time === "today") {
    $sql .= " AND DATE(sd.sold_at) = CURDATE()";
} elseif ($filter_time === "week") {
    $sql .= " AND YEARWEEK(sd.sold_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter_time === "month") {
    $sql .= " AND YEAR(sd.sold_at) = YEAR(CURDATE()) AND MONTH(sd.sold_at) = MONTH(CURDATE())";
} elseif ($filter_time === "year") {
    $sql .= " AND YEAR(sd.sold_at) = YEAR(CURDATE())";
}

if ($search_sn) {
    $sql .= " AND sd.serial_number LIKE :sn";
    $params['sn'] = "%$search_sn%";
}

$sql .= " ORDER BY sd.sold_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get salesperson name
$salesperson = htmlspecialchars($sales[0]['sold_by_name'] ?? $_SESSION['full_name'] ?? 'Unknown');

// --- TCPDF ---
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Inventory System');
$pdf->SetTitle('Sales Report');
$pdf->SetHeaderData('', 0, 'Sales Report', '');
$pdf->setHeaderFont(['helvetica','',12]);
$pdf->setFooterFont(['helvetica','',10]);
$pdf->SetMargins(15, 25, 15);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage();

// --- Header Details ---
$logo_file = $_SERVER['DOCUMENT_ROOT'] . '../assets/MC-LOGO.png';
if (file_exists($logo_file)) {
    $pdf->Image($logo_file, 15, 10, 50, 0, 'PNG'); // X=15, Y=10, Width=50, auto height
}
$pdf->Ln(20);
$period = $filter_time ?: "All Time";
$downloaded = date('Y-m-d H:i:s');
$html .= '<div style="margin-bottom:15px; line-height:1;">
    <div><strong>Salesperson:</strong> '.$salesperson.'</div>
    <div><strong>Period:</strong> '.ucfirst($period).'</div>
    <div><strong>Date Downloaded:</strong>'.$downloaded.'</div>
</div>';
// --- Table with updated column widths including Graphics ---
$html .= '<table border="1" cellpadding="4">
<tr style="background-color:#2f7a3f;color:#fff;text-align:center;">
<th width="5%">#</th>
<th width="15%">Serial Number</th>
<th width="18%">Model</th>
<th width="12%">Category</th>
<th width="9%">RAM</th>
<th width="13%">Storage</th>
<th width="12%">Graphics</th>
<th width="9%">Touch</th>
<th width="14%">Sold on</th>
</tr>';

$i=1;
foreach($sales as $row){
    $touch = strtolower($row['category_name'] ?? '')==="desktop" ? "N/A" : htmlspecialchars($row['touch']??'N/A');

    $html .= '<tr>
    <td width="5%" align="center">'.$i++.'</td>
    <td width="15%">'.htmlspecialchars($row['serial_number']).'</td>
    <td width="18%">'.htmlspecialchars($row['model_name']).'</td>
    <td width="12%">'.htmlspecialchars($row['category_name'] ?? '-').'</td>
    <td width="9%" align="center">'.htmlspecialchars($row['ram']).' GB</td>
    <td width="13%">'.htmlspecialchars($row['storage_type'].' '.$row['storage_capacity'].'GB').'</td>
    <td width="12%">'.htmlspecialchars($row['graphics'] ?? 'N/A').'</td>
    <td width="9%" align="center">'.$touch.'</td>
    <td width="14%" align="center">'.htmlspecialchars($row['sold_at']).'</td>
    </tr>';
}

$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');

// --- Filename ---
$filename = str_replace(' ', '_', $salesperson).'_sales_report.pdf';
$pdf->Output($filename, 'D'); // Force download
