<?php
date_default_timezone_set('Africa/Nairobi');
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Include TCPDF library (make sure tcpdf_min folder is in your project)
require_once "../tcpdf_min/tcpdf.php";

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$time_period = $_POST['time_period'] ?? '';
$branch = $_POST['branch'] ?? '';
$serial_number = $_POST['sn'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';

// Check if at least time period or branch filter is applied
if (!$time_period && !$branch) {
    die("Please select either time period or branch filter to generate report.");
}

// Build SQL for filtered devices
$sql = "SELECT sd.*, c.category_name, u.full_name AS sold_by_name, u.branch
        FROM sold_devices sd
        JOIN categories c ON sd.category_id = c.id
        JOIN users u ON sd.sold_by = u.id
        WHERE 1";

$params = [];

// Sales role restriction - only show devices they sold
if($role === 'sales'){
    $sql .= " AND sd.sold_by = :uid";
    $params['uid'] = $user_id;
}

// Branch filter
if($branch){
    $sql .= " AND u.branch = :branch";
    $params['branch'] = $branch;
}

// Serial number search
if($serial_number){
    $sql .= " AND sd.serial_number LIKE :sn";
    $params['sn'] = "%$serial_number%";
}

// Time filter
if($time_period){
    switch($time_period){
        case 'today':
            $sql .= " AND DATE(sd.sold_at) = CURDATE()";
            break;
        case 'this_week':
            $sql .= " AND YEARWEEK(sd.sold_at,1) = YEARWEEK(CURDATE(),1)";
            break;
        case 'this_month':
            $sql .= " AND YEAR(sd.sold_at)=YEAR(CURDATE()) AND MONTH(sd.sold_at)=MONTH(CURDATE())";
            break;
        case 'last_month':
            $sql .= " AND YEAR(sd.sold_at) = YEAR(CURDATE() - INTERVAL 1 MONTH) 
                      AND MONTH(sd.sold_at) = MONTH(CURDATE() - INTERVAL 1 MONTH)";
            break;
        case 'this_year':
            $sql .= " AND YEAR(sd.sold_at)=YEAR(CURDATE())";
            break;
        case 'custom':
            if ($start_date && $end_date) {
                $sql .= " AND DATE(sd.sold_at) BETWEEN :start_date AND :end_date";
                $params['start_date'] = $start_date;
                $params['end_date'] = $end_date;
            }
            break;
    }
}

$sql .= " ORDER BY sd.sold_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get branch display name for title
$branch_display = $branch ?: "All Branches";

// Format period display with date range
$period_display = "";
if ($time_period) {
    switch($time_period) {
        case 'today':
            $today = date('Y-m-d');
            $period_display = "Today (" . date('F j, Y', strtotime($today)) . ")";
            break;
        case 'this_week':
            $week_start = date('Y-m-d', strtotime('monday this week'));
            $week_end = date('Y-m-d', strtotime('sunday this week'));
            $period_display = "This Week (" . date('F j, Y', strtotime($week_start)) . " to " . date('F j, Y', strtotime($week_end)) . ")";
            break;
        case 'this_month':
            $month_start = date('Y-m-01');
            $month_end = date('Y-m-t');
            $period_display = "This Month (" . date('F j, Y', strtotime($month_start)) . " to " . date('F j, Y', strtotime($month_end)) . ")";
            break;
        case 'last_month':
            $last_month_start = date('Y-m-01', strtotime('first day of last month'));
            $last_month_end = date('Y-m-t', strtotime('last day of last month'));
            $period_display = "Last Month (" . date('F j, Y', strtotime($last_month_start)) . " to " . date('F j, Y', strtotime($last_month_end)) . ")";
            break;
        case 'this_year':
            $year_start = date('Y-01-01');
            $year_end = date('Y-12-31');
            $period_display = "This Year (" . date('F j, Y', strtotime($year_start)) . " to " . date('F j, Y', strtotime($year_end)) . ")";
            break;
        case 'custom':
            if ($start_date && $end_date) {
                $period_display = "Custom Range (" . date('F j, Y', strtotime($start_date)) . " to " . date('F j, Y', strtotime($end_date)) . ")";
            } else {
                $period_display = "Custom Range (No dates specified)";
            }
            break;
        default:
            $period_display = "All Time";
    }
} else {
    $period_display = "All Time";
}

// Create new PDF
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Inventory System');
$pdf->SetTitle('Sold Devices Report');
$pdf->SetMargins(10, 15, 10);
$pdf->SetAutoPageBreak(TRUE, 10);
$pdf->AddPage();

// Title
// Add company logo
$logo_file = $_SERVER['DOCUMENT_ROOT'] . '../assets/MC-LOGO.png';
if (file_exists($logo_file)) {
    $pdf->Image($logo_file, 15, 10, 50, 0, 'PNG'); // X=15, Y=10, Width=50, auto height
}
$pdf->Ln(20); // space after logo

// Title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, "Sold Devices Report", 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);

// Report header information
$pdf->Cell(0, 8, "Branch: " . $branch_display, 0, 1);
$pdf->Cell(0, 8, "Period: " . $period_display, 0, 1);
$pdf->Cell(0, 8, "Date Downloaded: " . date('Y-m-d H:i:s'), 0, 1);
$pdf->Cell(0, 8, "Total Devices: " . count($devices), 0, 1);
$pdf->Ln(5); // Add some space

// Table header - matching sold devices table exactly
$html = '<table border="1" cellpadding="4" style="border-collapse:collapse;">
<tr style="background-color:#2f7a3f;color:#fff;font-weight:bold;">
<th width="4%">#</th>
<th width="10%">Serial Number</th>
<th width="8%">Category</th>
<th width="10%">Model</th>
<th width="12%">Processor</th>
<th width="10%">Graphics</th>
<th width="6%">RAM</th>
<th width="10%">Storage</th>
<th width="6%">Touch</th>
<th width="8%">Sold By</th>
<th width="8%">Branch</th>
<th width="10%">Date Sold</th>
</tr>';

$i = 1;
foreach($devices as $d){
    $html .= '<tr>';
    $html .= '<td>'.$i++.'</td>';
    $html .= '<td>'.htmlspecialchars($d['serial_number']).'</td>';
    $html .= '<td>'.htmlspecialchars($d['category_name']).'</td>';
    $html .= '<td>'.htmlspecialchars($d['model_name']).'</td>';
    $html .= '<td>'.htmlspecialchars($d['processor']).'</td>';
    $html .= '<td>'.htmlspecialchars($d['graphics']).'</td>';
    $html .= '<td>'.htmlspecialchars($d['ram']).' GB</td>';
    $html .= '<td>'.htmlspecialchars($d['storage_type'].' '.$d['storage_capacity'].'GB').'</td>';
    $html .= '<td>'.(strtolower($d['category_name'])==='desktop'?'N/A':htmlspecialchars($d['touch']??'N/A')).'</td>';
    $html .= '<td>'.htmlspecialchars($d['sold_by_name']).'</td>';
    
    // Branch with color coding
    $branch_color = $d['branch'] == 'KIMATHI' ? '#2f7a3f' : '#007bff';
    $html .= '<td style="color:'.$branch_color.';">'.htmlspecialchars($d['branch']).'</td>';
    
    $html .= '<td>'.htmlspecialchars($d['sold_at']).'</td>';
    $html .= '</tr>';
}
$html .= '</table>';

// Output HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Summary statistics if we have data
if (count($devices) > 0) {
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, "Summary Statistics:", 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    
    // Count devices per branch
    $branch_counts = [];
    foreach ($devices as $d) {
        $branch_counts[$d['branch']] = ($branch_counts[$d['branch']] ?? 0) + 1;
    }
    
    foreach ($branch_counts as $br_name => $count) {
        $pdf->Cell(0, 6, $br_name . ": " . $count . " device(s)", 0, 1);
    }
    
    // Add total
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, "Total: " . count($devices) . " device(s)", 0, 1);
}

// Force download
$filename = 'Sold_Devices_Report_' . 
            ($branch ? $branch . '_' : 'All_Branches_') . 
            ($time_period ? $time_period . '_' : 'All_Time_') . 
            date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'D');