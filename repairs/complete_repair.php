<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// ============================================================
// STRICT ROLE CHECK - Only technicians
// ============================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    die("ACCESS DENIED: Only technicians can complete repairs.");
}

$user_id = (int) $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? ($_SESSION['full_name'] ?? 'Technician');
$user_role = $_SESSION['role'];

// Get technician branch
$stmt = $conn->prepare("SELECT branch FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_branch = $stmt->fetchColumn();
if (!$user_branch) {
    die("Your account has no branch assigned. Contact administrator.");
}

// Get repair ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    die("Invalid repair ID.");
}

// Fetch repair details
$stmt = $conn->prepare("
    SELECT r.*, d.model_name, d.category_id, 
           c.category_name, u.full_name AS added_by_name
    FROM repairs r
    LEFT JOIN devices d ON r.serial_number COLLATE utf8mb4_general_ci = d.serial_number
    LEFT JOIN categories c ON d.category_id = c.id
    LEFT JOIN users u ON r.added_by = u.id
    WHERE r.id = ? AND r.added_by = ?
");
$stmt->execute([$id, $user_id]);
$repair = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$repair) {
    die("Repair not found or you don't have permission to access it.");
}

if ($repair['fix_status'] === 'Fixed') {
    die("This repair has already been completed.");
}

// Determine if repair cost is required (only for client devices)
$isClientDevice = ($repair['source_device'] === 'client');

// PHPMailer configuration
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once "../PHPMailer-master/src/PHPMailer.php";
require_once "../PHPMailer-master/src/SMTP.php";
require_once "../PHPMailer-master/src/Exception.php";

// ============================================================
// SEND COMPLETION EMAIL FUNCTION
// ============================================================
function sendCompletionEmail($toEmail, $customerName, $repair, $branch, $parts_used, $repair_cost) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.zoho.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'support@vimarktech.com';
        $mail->Password   = 'Gct8ygb6Htfj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('support@vimarktech.com', 'Mombasa Computers');
        $mail->addAddress($toEmail);

        // Get branch location details
        $branchLocation = ($branch === 'MOI') 
            ? 'Moi Avenue Branch opposite Veteran House next to Bihi Towers'
            : 'Kimathi Street Branch next to Safari.com shop & Cooperative Bank';
        
        $branchPhone = '0111 040 400 | 0792 792 750';

        $mail->isHTML(true);
        $mail->Subject = 'Repair Completed - Mombasa Computers';
        $mail->Body    = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #1a4b2a 0%, #2a7a3a 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .header p { margin: 5px 0 0; opacity: 0.9; font-size: 14px; }
                .content { padding: 30px; }
                .greeting { font-size: 18px; margin-bottom: 20px; color: #333; }
                .repair-details { background: #f8f9fa; border-radius: 10px; padding: 20px; margin: 20px 0; border-left: 4px solid #1a4b2a; }
                .detail-row { display: flex; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #eee; }
                .detail-label { width: 40%; font-weight: bold; color: #555; }
                .detail-value { width: 60%; color: #333; }
                .status-badge { display: inline-block; background: #1a4b2a; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; }
                .message-box { background: #e8f5e9; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center; }
                .message-box i { color: #1a4b2a; font-size: 24px; display: block; margin-bottom: 10px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #888; border-top: 1px solid #eee; }
                .footer a { color: #1a4b2a; text-decoration: none; }
                .shop-info { background: #f0f4f1; padding: 15px; border-radius: 8px; margin: 15px 0; }
                .shop-info p { margin: 5px 0; font-size: 13px; }
                .shop-info strong { color: #1a4b2a; }
                @media (max-width: 480px) { .detail-row { flex-direction: column; } .detail-label, .detail-value { width: 100%; } }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Repair Completed!</h1>
                    <p>Your device is ready for pickup</p>
                </div>
                <div class="content">
                    <div class="greeting">Dear <strong>' . htmlspecialchars($customerName) . '</strong>,</div>
                    <p>We are pleased to inform you that your device repair has been successfully completed.</p>
                    
                    <div class="repair-details">
                        <div class="detail-row">
                            <div class="detail-label">Device:</div>
                            <div class="detail-value">' . htmlspecialchars($repair['category_name'] ?? $repair['model_name'] ?? 'N/A') . ' - ' . htmlspecialchars($repair['model_name'] ?? 'N/A') . '</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Issue Reported:</div>
                            <div class="detail-value">' . nl2br(htmlspecialchars($repair['problem_description'])) . '</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Parts Used:</div>
                            <div class="detail-value">' . htmlspecialchars($parts_used) . '</div>
                        </div>';

        // Only show repair cost if it exists
        if (!empty($repair_cost) && $repair_cost > 0) {
            $mail->Body .= '
                        <div class="detail-row">
                            <div class="detail-label">Repair Cost:</div>
                            <div class="detail-value"><strong style="color: #1a4b2a;">KES ' . number_format($repair_cost, 2) . '</strong></div>
                        </div>';
        }

        $mail->Body .= '
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="detail-value"><span class="status-badge">✓ COMPLETED</span></div>
                        </div>
                    </div>
                    
                    <div class="message-box">
                        <i class="fas fa-store"></i>
                        <p><strong>Ready for Pickup</strong><br>Your device is now ready and waiting for you at our shop.</p>
                    </div>
                    
                    <div class="shop-info">
                        <p><strong>Contact Us Today:</strong></p>
                        <p>Tel: ' . $branchPhone . '</p>
                        <p>Web: <a href="www.mombasacomputers.com">www.mombasacomputers.com</a></p>
                        <p><strong>Pickup Location:</strong></p>
                        <p>' . $branchLocation . '</p>
                    </div>
                </div>
                <div class="footer">
                    <p>Thank you for choosing <strong>Mombasa Computers</strong>!</p>
                    <p>&copy; ' . date('Y') . ' Mombasa Computers. All rights reserved.</p>
                    <p><strong>WE ARE IT.</strong></p>
                </div>
            </div>
        </body>
        </html>
        ';

        $mail->AltBody = "Dear $customerName,\n\nYour device repair has been completed and is ready for pickup.\n\n" .
                         "Device: {$repair['category_name']} - {$repair['model_name']}\n" .
                         "Issue: {$repair['problem_description']}\n" .
                         "Parts Used: $parts_used\n";
        
        if (!empty($repair_cost) && $repair_cost > 0) {
            $mail->AltBody .= "Repair Cost: KES " . number_format($repair_cost, 2) . "\n";
        }
        
        $mail->AltBody .= "\nPlease visit our shop to collect your device.\n\n" .
                         "Pickup Location: $branchLocation\n" .
                         "Phone: $branchPhone\n\n" .
                         "Thank you for choosing Mombasa Computers!\n" .
                         "WE ARE IT.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

$error = "";
$success = "";

// ============================================================
// PROCESS FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parts_used = trim($_POST['parts_used'] ?? '');
    $repair_cost = trim($_POST['repair_cost'] ?? '');

    if (empty($parts_used)) {
        $error = "Please enter parts used.";
    } elseif ($isClientDevice && (empty($repair_cost) || $repair_cost <= 0)) {
        // Only validate repair cost if it's a client device
        $error = "Please enter a valid repair cost for this client device.";
    } else {
        try {
            $conn->beginTransaction();

            // Only set repair_cost if it was provided and device is from client
            $finalRepairCost = ($isClientDevice && !empty($repair_cost) && $repair_cost > 0) ? $repair_cost : NULL;

            // Update repair
            $update = $conn->prepare("
                UPDATE repairs
                SET fix_status = 'Fixed',
                    date_fixed = NOW(),
                    parts_used = ?,
                    repair_cost = ?
                WHERE id = ? AND added_by = ?
            ");
            $update->execute([$parts_used, $finalRepairCost, $id, $user_id]);

            // Update device status
            $stmt = $conn->prepare("SELECT serial_number FROM repairs WHERE id = ?");
            $stmt->execute([$id]);
            $sn = $stmt->fetchColumn();
            
            if ($sn) {
                $updateDevice = $conn->prepare("
                    UPDATE devices 
                    SET status = 'In Stock', 
                        place = NULL 
                    WHERE serial_number COLLATE utf8mb4_general_ci = ?
                ");
                $updateDevice->execute([$sn]);
            }

            // Send email notification if customer email exists
            $emailSent = false;
            $clientEmail = isset($repair['client_email']) ? $repair['client_email'] : null;
            
            if (!empty($clientEmail)) {
                $clientName = isset($repair['client_name']) ? $repair['client_name'] : 'Customer';
                $emailSent = sendCompletionEmail(
                    $clientEmail,
                    $clientName,
                    $repair,
                    $user_branch,
                    $parts_used,
                    $finalRepairCost
                );
            }

            // Activity log
            $log = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, details)
                VALUES (?, 'Repair Completed', ?)
            ");
            
            $modelName = isset($repair['model_name']) ? $repair['model_name'] : 'N/A';
            $clientName = isset($repair['client_name']) ? $repair['client_name'] : 'N/A';
            $logDetails = "Repair completed for {$modelName} ({$clientName})";
            
            if ($emailSent) {
                $logDetails .= " - Email notification sent to {$clientEmail}";
            } else {
                $logDetails .= " - No email sent";
            }
            
            if ($finalRepairCost) {
                $logDetails .= " - Cost: KES " . number_format($finalRepairCost, 2);
            }
            
            $log->execute([$user_id, $logDetails]);

            $conn->commit();

            $successMsg = "Repair completed successfully!";
            if ($emailSent) {
                $successMsg .= " Notification sent to {$clientEmail}.";
            } else {
                $successMsg .= " No email address on file.";
            }
            
            $_SESSION['success_message'] = $successMsg;
            
            header("Location: under_repair.php");
            exit();
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get current time greeting
date_default_timezone_set('Africa/Nairobi');
$hour = date('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Complete Repair | Mombasa Computers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a4b2a;
            --primary-light: #2a6b3a;
            --success: #10b981;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --font-sans: 'Inter', system-ui, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-sans);
            background: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
            overflow-x: hidden;
            min-height: 100vh;
        }

        .main-content {
            padding: 2rem 2rem 1rem;
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;
            background: var(--gray-100);
            transition: all 0.3s ease;
        }

        .page-header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .page-header h1 {
            font-size: 1.75rem;
            color: var(--gray-800);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i {
            color: var(--primary);
            font-size: 1.75rem;
        }

        .breadcrumb {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb a:hover { text-decoration: underline; }
        
        .user-info {
            margin-top: 0.5rem;
            color: var(--gray-500);
            font-size: 0.85rem;
        }
        .user-info i {
            color: var(--primary);
        }

        .form-container {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .form-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }

        .form-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-header h2 i { color: var(--primary); }

        .form-body { padding: 1.5rem; }

        .repair-info {
            background: var(--gray-50);
            padding: 1.25rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-200);
        }

        .repair-info p {
            margin: 6px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 0.9rem;
        }

        .repair-info strong {
            color: var(--gray-700);
            width: 120px;
            font-weight: 600;
        }
        
        .repair-info span { 
            flex: 1; 
            color: var(--gray-700);
        }

        .email-badge {
            display: inline-block;
            background: #d1fae5;
            color: #065f46;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
            font-weight: 500;
        }

        .no-email-badge {
            display: inline-block;
            background: #fee2e2;
            color: #991b1b;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
            font-weight: 500;
        }

        .source-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        .source-badge.instock { background: #dcfce7; color: #065f46; }
        .source-badge.return { background: #fef3c7; color: #92400e; }
        .source-badge.client { background: #dbeafe; color: #1e40af; }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.4rem;
        }

        .form-group label i {
            color: var(--primary);
            margin-right: 0.3rem;
        }

        .form-group label .required {
            color: #dc2626;
            margin-left: 0.2rem;
        }

        .form-group label .optional {
            color: var(--gray-400);
            font-weight: 400;
            font-size: 0.75rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-family: var(--font-sans);
            background: white;
            transition: border-color 0.2s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 75, 42, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .info-text {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .info-text.warning {
            color: #92400e;
        }

        .info-text .highlight {
            color: var(--primary);
            font-weight: 600;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid transparent;
        }

        .alert-error {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .alert-success {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }

        .alert-info {
            background: #dbeafe;
            border-color: #bfdbfe;
            color: #1e40af;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .footer {
            text-align: center;
            padding: 1.5rem 0 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-400);
            border-top: 1px solid var(--gray-200);
        }
        
        .footer span {
            color: var(--primary);
        }

        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 1.5rem 1rem 1rem !important;
                padding-top: 5rem !important;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem 0.75rem 0.75rem !important;
                padding-top: 4.5rem !important;
            }

            .page-header h1 { font-size: 1.25rem; }
            .page-header { padding: 1.25rem; }
            
            .repair-info p { 
                flex-direction: column; 
                gap: 4px; 
            }
            .repair-info strong { 
                width: auto; 
            }
            .form-actions { 
                flex-direction: column; 
            }
            .btn { 
                width: 100%; 
                justify-content: center; 
            }
        }

        @media (max-width: 480px) {
            .main-content { padding: 0.75rem 0.5rem 0.5rem !important; padding-top: 4rem !important; }
            .page-header { padding: 1rem; }
            .page-header h1 { font-size: 1.1rem; }
            .form-body { padding: 1rem; }
        }
    </style>
</head>
<body>

<?php include "../includes/sidebar.php"; ?>

<div class="main-content">
    <div class="page-header">
        <h1>
            <i class="fas fa-check-circle"></i>
            Complete Repair
        </h1>
        <div class="breadcrumb">
            <a href="/inventory_system/dashboard/techniciandashboard.php">Dashboard</a>
            <span> / </span>
            <a href="under_repair.php">Under Repair</a>
            <span> / </span>
            <span>Complete Repair</span>
        </div>
        <div class="user-info">
            <i class="fas fa-store"></i> Branch: <?= htmlspecialchars($user_branch) ?> &nbsp;&nbsp;|&nbsp;&nbsp;
            <i class="fas fa-user"></i> <?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars(explode(' ', $user_name)[0]) ?>
        </div>
    </div>

    <div class="form-container">
        <div class="form-header">
            <h2>
                <i class="fas fa-wrench"></i>
                Repair Details & Completion
            </h2>
        </div>

        <div class="form-body">
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if($isClientDevice): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <span><strong>Client Device:</strong> Repair cost is required for client devices.</span>
            </div>
            <?php endif; ?>

            <div class="repair-info">
                <p>
                    <strong><i class="fas fa-user"></i> Client:</strong> 
                    <span><?= htmlspecialchars($repair['client_name'] ?? 'N/A') ?></span>
                </p>
                <p>
                    <strong><i class="fas fa-phone"></i> Phone:</strong> 
                    <span><?= htmlspecialchars($repair['client_phone'] ?? 'N/A') ?></span>
                </p>
                <p>
                    <strong><i class="fas fa-envelope"></i> Email:</strong> 
                    <span>
                        <?php 
                        $clientEmail = isset($repair['client_email']) ? $repair['client_email'] : null;
                        if (!empty($clientEmail)): 
                        ?>
                            <?= htmlspecialchars($clientEmail) ?>
                            <span class="email-badge"><i class="fas fa-envelope"></i> Notification will be sent</span>
                        <?php else: ?>
                            N/A
                            <span class="no-email-badge"><i class="fas fa-exclamation-triangle"></i> No email - notify manually</span>
                        <?php endif; ?>
                    </span>
                </p>
                <p>
                    <strong><i class="fas fa-laptop"></i> Device:</strong> 
                    <span><?= htmlspecialchars($repair['category_name'] ?? $repair['model_name'] ?? 'N/A') ?> - <?= htmlspecialchars($repair['model_name'] ?? 'N/A') ?></span>
                </p>
                <p>
                    <strong><i class="fas fa-hashtag"></i> Serial:</strong> 
                    <span><code><?= !empty($repair['serial_number']) ? htmlspecialchars($repair['serial_number']) : 'N/A' ?></code></span>
                </p>
                <p>
                    <strong><i class="fas fa-file-alt"></i> Issue:</strong> 
                    <span><?= nl2br(htmlspecialchars($repair['problem_description'])) ?></span>
                </p>
                <p>
                    <strong><i class="fas fa-calendar"></i> Date Received:</strong> 
                    <span><?= date('M j, Y H:i', strtotime($repair['date_added'])) ?></span>
                </p>
                <p>
                    <strong><i class="fas fa-tag"></i> Source:</strong> 
                    <span>
                        <?php 
                        $sourceDisplay = [
                            'instock' => ['label' => 'In Stock', 'class' => 'instock'],
                            'return' => ['label' => 'Return', 'class' => 'return'],
                            'client' => ['label' => 'Client', 'class' => 'client']
                        ];
                        $source = $repair['source_device'] ?? 'unknown';
                        $src = $sourceDisplay[$source] ?? ['label' => 'Unknown', 'class' => ''];
                        ?>
                        <span class="source-badge <?= $src['class'] ?>">
                            <?= $src['label'] ?>
                        </span>
                        <?php if ($isClientDevice): ?>
                            <span style="color: #1e40af; font-size: 0.8rem; margin-left: 0.5rem;">
                                <i class="fas fa-info-circle"></i> Cost required
                            </span>
                        <?php endif; ?>
                    </span>
                </p>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-tools"></i> Parts Used <span class="required">*</span></label>
                    <textarea name="parts_used" rows="4" placeholder="Example: HP Keyboard, Dell Screen, Battery, etc..." required></textarea>
                    <div class="info-text"><i class="fas fa-info-circle"></i> List all replacement parts used for this repair</div>
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-money-bill-wave"></i> Repair Cost (KES)
                        <?php if ($isClientDevice): ?>
                            <span class="required">*</span>
                        <?php else: ?>
                            <span class="optional">(Optional - Only for client devices)</span>
                        <?php endif; ?>
                    </label>
                    <input type="number" name="repair_cost" step="0.01" placeholder="Enter total repair cost" <?= $isClientDevice ? 'required' : '' ?>>
                    <div class="info-text <?= $isClientDevice ? 'warning' : '' ?>">
                        <i class="fas <?= $isClientDevice ? 'fa-exclamation-circle' : 'fa-info-circle' ?>"></i>
                        <?php if ($isClientDevice): ?>
                            <strong>Required:</strong> Enter the total amount the client will pay for this repair.
                        <?php else: ?>
                            <strong>Optional:</strong> Only required for <span class="highlight">Client</span> source devices.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle"></i> Complete Repair
                    </button>
                    <a href="under_repair.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="footer">
        <i class="fas fa-copyright"></i> <?= date('Y'); ?> <span>Mombasa Computers</span>. All rights reserved.
        <span style="margin:0 0.5rem;">•</span>
        <span>v2.0.0</span>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function adjustMainContent() {
        var mainContent = document.querySelector('.main-content');
        if (window.innerWidth <= 1200) {
            if (mainContent) {
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
                mainContent.style.paddingTop = '5rem';
            }
        } else {
            if (mainContent) {
                mainContent.style.marginLeft = '260px';
                mainContent.style.width = 'calc(100% - 260px)';
                mainContent.style.paddingTop = '';
            }
        }
    }

    adjustMainContent();
    window.addEventListener('resize', adjustMainContent);
    window.addEventListener('orientationchange', adjustMainContent);
});
</script>

</body>
</html>