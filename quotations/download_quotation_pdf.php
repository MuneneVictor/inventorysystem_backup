<?php
// Clear all output buffers and prevent any output
while (ob_get_level()) {
    ob_end_clean();
}

// Start session
session_start();

// Disable error display for this file
error_reporting(0);

require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    die("ACCESS DENIED.");
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Check role
$allowed_roles = ['sales', 'super_admin', 'manager', 'technician'];
if (!in_array($user_role, $allowed_roles)) {
    die("ACCESS DENIED.");
}

$quotation_id = (int) ($_GET['id'] ?? 0);
if (!$quotation_id) die("Invalid quotation.");

$stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ? AND user_id = ?");
$stmt->execute([$quotation_id, $user_id]);
$quotation = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$quotation) die("Quotation not found.");

$stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$stmt->execute([$quotation_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load Dompdf
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;

$dompdf = new Dompdf();
$dompdf->setPaper('A4', 'portrait');

// Get logo as base64
$logo_path = '../assets/MC-LOGO.png';
$logo_data = '';
if (file_exists($logo_path)) {
    $logo_data = base64_encode(file_get_contents($logo_path));
}

// Calculate totals
$subtotal = 0; 
$totalVat = 0; 
$grandTotal = 0;
foreach ($items as $it) {
    $total = $it['quantity'] * $it['unit_price'];
    $vat = $it['vat_amount'];
    $totalWithVat = $it['total_with_vat'];
    $subtotal += $total;
    $totalVat += $vat;
    $grandTotal += $totalWithVat;
}

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { 
            margin: 15mm 15mm 15mm 15mm; 
        }
        body { 
            font-family: Arial, Helvetica, sans-serif; 
            font-size: 14px; 
            color: #000000; 
            line-height: 1.6;
        }
        .header { 
            width: 100%;
            border-bottom: 2px solid #1a4b2a; 
            padding-bottom: 12px; 
            margin-bottom: 18px;
            overflow: hidden;
        }
        .header .logo { 
            float: left;
            width: 40%;
        }
        .header .logo img { 
            max-height: 80px; 
            width: auto; 
        }
        .header .company { 
            float: right;
            text-align: right;
            width: 55%;
        }
        .header .company h1 { 
            font-size: 32px; 
            font-weight: 700; 
            color: #1a4b2a; 
            letter-spacing: 3px; 
            margin: 0 0 3px 0;
            padding: 0;
        }
        .header .company p { 
            font-size: 13px; 
            line-height: 1.5; 
            color: #000000; 
            margin: 2px 0; 
            padding: 0;
        }
        .header .company .contact { 
            font-size: 13px; 
            color: #000000; 
            margin-top: 3px; 
        }
        .clearfix {
            clear: both;
        }
        .details { 
            margin: 12px 0 18px 0; 
        }
        .details table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        .details td { 
            padding: 4px 0; 
            vertical-align: top; 
        }
        .details .label { 
            font-weight: 700; 
            color: #000000; 
            font-size: 12px; 
            text-transform: uppercase; 
        }
        .details .client-name { 
            font-size: 18px; 
            font-weight: 700; 
            color: #000000; 
            margin-top: 3px;
        }
        .details .client-detail { 
            font-size: 14px; 
            color: #000000; 
        }
        .details .quotation-number { 
            font-weight: 700; 
            color: #000000; 
            font-size: 14px; 
        }
        .details .date-value { 
            font-weight: 600;
            color: #000000;
            font-size: 14px;
        }
        .details .payment-due { 
            color: #dc2626; 
            font-weight: 700; 
            font-size: 14px;
        }
        .items-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 12px 0; 
        }
        .items-table th { 
            background: #f3f4f6; 
            padding: 10px 8px; 
            text-align: left; 
            border-bottom: 2px solid #000000; 
            font-weight: 700; 
            font-size: 13px; 
            text-transform: uppercase; 
            color: #000000; 
        }
        .items-table td { 
            padding: 10px 8px; 
            border-bottom: 1px solid #e5e7eb; 
            font-size: 14px; 
            vertical-align: middle; 
            color: #000000; 
        }
        .items-table tr:last-child td { 
            border-bottom: none; 
        }
        .items-table .text-right { 
            text-align: right; 
        }
        .items-table .description-cell { 
            font-weight: 700; 
            color: #000000; 
        }
        .items-table .specs-cell { 
            font-size: 13px; 
            color: #000000; 
            font-weight: normal;
        }
        .totals { 
            text-align: right; 
            margin-top: 12px; 
            padding-top: 12px; 
            border-top: 2px solid #000000; 
        }
        .totals p { 
            margin: 4px 0; 
            font-size: 15px; 
            color: #000000; 
        }
        .totals .vat-line {
            font-size: 14px;
            color: #000000;
        }
        .totals .grand { 
            font-size: 20px; 
            font-weight: 700; 
            color: #1a4b2a; 
            margin-top: 6px; 
            padding-top: 6px; 
            border-top: 1px solid #000000; 
        }
        .totals .grand span { 
            font-size: 22px; 
        }
        .notes { 
            margin-top: 15px; 
            padding: 12px 15px; 
            background: #f9fafb; 
            border-left: 3px solid #1a4b2a; 
            font-size: 14px; 
            color: #000000; 
        }
        .footer-note { 
            margin-top: 20px; 
            text-align: center; 
            font-size: 13px; 
            color: #000000; 
            border-top: 1px solid #e5e7eb; 
            padding-top: 12px; 
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">' . ($logo_data ? '<img src="data:image/png;base64,' . $logo_data . '" alt="Mombasa Computers">' : '') . '</div>
        <div class="company">
            <h1>QUOTATION</h1>
            <p><strong>Mombasa Computers</strong></p>
            <p>Moi Avenue Opp Credible Sounds</p>
            <p>P.O Box 37940 Nairobi, Nairobi Area 00100 Kenya</p>
            <div class="contact">Phone: 0792792750 | Mobile: 0111040400</div>
            <div class="contact">www.mombasacomputers.com</div>
        </div>
        <div class="clearfix"></div>
    </div>
    
    <div class="details">
        <table>
            <tr>
                <td style="width:55%;">
                    <div class="label">BILL TO</div>
                    <div class="client-name">' . htmlspecialchars($quotation['client_name']) . '</div>
                    ' . (!empty($quotation['client_box']) ? '<div class="client-detail">' . htmlspecialchars($quotation['client_box']) . '</div>' : '') . '
                    ' . (!empty($quotation['client_phone']) ? '<div class="client-detail">Phone: ' . htmlspecialchars($quotation['client_phone']) . '</div>' : '') . '
                    ' . (!empty($quotation['client_email']) ? '<div class="client-detail">Email: ' . htmlspecialchars($quotation['client_email']) . '</div>' : '') . '
                </td>
                <td style="width:45%; text-align:right;">
                    <div><span class="label">QUOTATION NUMBER:</span> <span class="quotation-number">' . htmlspecialchars($quotation['quotation_number']) . '</span></div>
                    <div style="margin-top:3px;"><span class="label">QUOTATION DATE:</span> <span class="date-value">' . date('M d, Y', strtotime($quotation['quotation_date'])) . '</span></div>
                    <div style="margin-top:3px;"><span class="label">PAYMENT DUE:</span> <span class="payment-due">' . date('M d, Y', strtotime($quotation['payment_due_date'])) . '</span></div>
                </td>
            </tr>
        </table>
    </div>
    
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:45%;">ITEMS</th>
                <th class="text-right" style="width:12%;">QUANTITY</th>
                <th class="text-right" style="width:18%;">UNIT PRICE</th>
                <th class="text-right" style="width:25%;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>';

foreach ($items as $it) {
    $total = $it['quantity'] * $it['unit_price'];
    $html .= '<tr>
        <td>
            <div class="description-cell">' . htmlspecialchars($it['description']) . '</div>
            ' . (!empty($it['specs']) ? '<div class="specs-cell">' . htmlspecialchars($it['specs']) . '</div>' : '') . '
        </td>
        <td class="text-right">' . $it['quantity'] . '</td>
        <td class="text-right">KES ' . number_format($it['unit_price'], 2) . '</td>
        <td class="text-right">KES ' . number_format($total, 2) . '</td>
    </tr>';
}

$html .= '</tbody></table>
    
    <div class="totals">
        <p><strong>Subtotal:</strong> KES ' . number_format($subtotal, 2) . '</p>';

if ($totalVat > 0) {
    $html .= '<p class="vat-line"><strong>VAT (16%):</strong> KES ' . number_format($totalVat, 2) . '</p>';
} else {
    $html .= '<p class="vat-line">0% VAT: KES 0.00</p>';
}

$html .= '<p class="grand"><strong>Amount Due (KES):</strong> <span>KES ' . number_format($grandTotal, 2) . '</span></p>
    </div>';

if (!empty($quotation['notes'])) {
    $html .= '<div class="notes"><strong>Notes:</strong> ' . nl2br(htmlspecialchars($quotation['notes'])) . '</div>';
}

$html .= '<div class="footer-note">Thank you for shopping with us</div>
</body>
</html>';

// Clear any remaining output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Load and render
$dompdf->loadHtml($html);
$dompdf->render();

// Get output
$output = $dompdf->output();
$filename = "Quotation_" . $quotation['quotation_number'] . ".pdf";

// Send headers
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($output));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $output;
exit;
?>