<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if (!in_array($_SESSION['role'], ['sales', 'super_admin', 'inventory_admin', 'manager', 'cashier'])) {
    die("ACCESS DENIED.");
}

$user_id = (int) $_SESSION['user_id'];
$quotation_id = (int) $_GET['id'] ?? 0;
if (!$quotation_id) die("Invalid quotation.");

$stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ? AND user_id = ?");
$stmt->execute([$quotation_id, $user_id]);
$quotation = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$quotation) die("Quotation not found.");

$stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$stmt->execute([$quotation_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);

$html = '
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
    .header .logo img { max-height: 70px; }
    .header .company { text-align: right; }
    .header .company h2 { font-size: 1.8rem; font-weight: 700; color: #1a4b2a; letter-spacing: 2px; }
    .header .company p { font-size: 0.9rem; line-height: 1.5; color: #4b5563; }
    .details { margin: 15px 0; }
    .details table { width: 100%; border-collapse: collapse; }
    .details td { padding: 4px 0; vertical-align: top; }
    .details .label { font-weight: 600; color: #4b5563; }
    .items-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    .items-table th { background: #f3f4f6; padding: 8px; text-align: left; border-bottom: 1px solid #d1d5db; font-weight: 600; font-size: 0.85rem; }
    .items-table td { padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 0.9rem; vertical-align: middle; }
    .text-right { text-align: right; }
    .specs { font-size: 0.8rem; color: #6b7280; }
    .totals { text-align: right; margin-top: 15px; padding-top: 5px; border-top: 2px solid #e5e7eb; }
    .totals p { margin: 4px 0; }
    .grand { font-size: 1.2rem; font-weight: 700; color: #1a4b2a; }
    .notes { margin-top: 15px; padding: 10px; background: #f9fafb; border-left: 3px solid #1a4b2a; font-size: 0.9rem; color: #4b5563; }
    .footer-note { margin-top: 20px; text-align: center; font-size: 0.85rem; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 10px; }
</style>
<div class="header">
    <div class="logo"><img src="../assets/MC-LOGO.png" alt="Mombasa Computers"></div>
    <div class="company">
        <h2>QUOTATION</h2>
        <p><strong>Mombasa Computers</strong><br>Moi Avenue Opp Credible Sounds<br>P.O Box 37940 Nairobi, Nairobi Area 00100 Kenya</p>
        <p>Phone: 0792792750<br>Mobile: 0111040400<br>www.mombasacomputers.com</p>
    </div>
</div>
<div class="details">
    <table>
        <tr><td><span class="label">BILL TO</span></td><td style="text-align:right;"><span class="label">Quotation Number:</span> '.htmlspecialchars($quotation['quotation_number']).'</td></tr>
        <tr><td><strong>'.htmlspecialchars($quotation['client_name']).'</strong><br>'.(!empty($quotation['client_box']) ? htmlspecialchars($quotation['client_box']).'<br>' : '').(!empty($quotation['client_phone']) ? 'Phone: '.htmlspecialchars($quotation['client_phone']).'<br>' : '').(!empty($quotation['client_email']) ? 'Email: '.htmlspecialchars($quotation['client_email']) : '').'</td>
        <td style="text-align:right;"><span class="label">Quotation Date:</span> '.htmlspecialchars($quotation['quotation_date']).'<br><span class="label">Payment Due:</span> '.htmlspecialchars($quotation['payment_due_date']).'</td></tr>
    </table>
</div>
<table class="items-table">
    <thead><tr><th>Items</th><th class="text-right">Quantity</th><th class="text-right">Price</th><th class="text-right">Amount</th></tr></thead>
    <tbody>';

$subtotal = 0; $totalVat = 0; $grandTotal = 0;
foreach ($items as $it) {
    $total = $it['quantity'] * $it['unit_price'];
    $vat = $it['vat_amount'];
    $totalWithVat = $it['total_with_vat'];
    $subtotal += $total;
    $totalVat += $vat;
    $grandTotal += $totalWithVat;
    $html .= '<tr>
        <td><strong>'.htmlspecialchars($it['description']).'</strong>'.(!empty($it['specs']) ? '<br><span class="specs">'.htmlspecialchars($it['specs']).'</span>' : '').'</td>
        <td class="text-right">'.$it['quantity'].'</td>
        <td class="text-right">'.number_format($it['unit_price'], 2).'</td>
        <td class="text-right">'.number_format($total, 2).'</td>
    </tr>';
}

$html .= '</tbody></table>
<div class="totals">
    <p><strong>Subtotal:</strong> '.number_format($subtotal, 2).'</p>';
if ($totalVat > 0) {
    $html .= '<p><strong>v.a.t 16%:</strong> '.number_format($totalVat, 2).'</p>';
}
$html .= '<p class="grand"><strong>Amount Due (KES):</strong> '.number_format($grandTotal, 2).'</p>
</div>';

if (!empty($quotation['notes'])) {
    $html .= '<div class="notes"><strong>Notes:</strong> '.nl2br(htmlspecialchars($quotation['notes'])).'</div>';
}
$html .= '<div class="footer-note">Thank you for shopping with us</div>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Quotation_".$quotation['quotation_number'].".pdf", array("Attachment" => 1));
exit;