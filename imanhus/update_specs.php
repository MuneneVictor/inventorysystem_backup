<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/owner_inventory_access.php';

$access = requireOwnerInventoryAccess($conn);
$user_id = (int)($access['user_id'] ?? 0);

function safe($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function displayValue($value, string $fallback = '-'): string {
    $value = trim((string)($value ?? ''));
    return $value === '' ? $fallback : $value;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die('Unable to open Update Specs because the selected item ID is missing or invalid.');
}

$stmt = $conn->prepare("SELECT * FROM `iman_hustle_items` WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    http_response_code(404);
    die("The selected Iman\'s Hustle item could not be found. It may have been removed or the link may be outdated.");
}

$error = '';
$success = '';

$currentStatus = trim((string)($item['status'] ?? ''));
$currentSerial = displayValue($item['serial_number'] ?? null, '#' . $id);
$currentModel = displayValue($item['model_name'] ?? null);
$currentItemType = displayValue($item['item_type'] ?? null);
$currentProcessor = displayValue($item['processor'] ?? null);
$currentRam = displayValue($item['ram'] ?? null);
$currentStorage = displayValue($item['storage'] ?? null);
$currentLocation = displayValue($item['location'] ?? null);
$currentPrice = $item['planned_selling_price'] ?? null;

if ($currentStatus !== 'In Stock') {
    $error = 'Specifications cannot be changed because this item is currently marked as ' .
             ($currentStatus !== '' ? $currentStatus : 'Unknown') .
             '. Only items that are currently In Stock can have hardware specifications updated.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $processorRaw = trim((string)($_POST['processor'] ?? ''));
    $ramRaw = trim((string)($_POST['ram'] ?? ''));
    $storageRaw = trim((string)($_POST['storage'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $priceRaw = trim((string)($_POST['planned_selling_price'] ?? ''));
    $oldPrice = $item['planned_selling_price'] ?? null;
    $newPrice = $oldPrice;
    if ($priceRaw !== '') {
        $cleanPrice = str_ireplace(['KES','KSH'], '', $priceRaw);
        $cleanPrice = str_replace(',', '', $cleanPrice);
        if (!is_numeric(trim($cleanPrice))) {
            $error = 'The selling price is invalid. Enter numbers only, for example 45000.';
        } else {
            $newPrice = (float)trim($cleanPrice);
            if ($newPrice < 0) {
                $error = 'The selling price cannot be negative.';
            }
        }
    }

    $oldProcessor = $item['processor'] ?? null;
    $oldRam = $item['ram'] ?? null;
    $oldStorage = $item['storage'] ?? null;

    $newProcessor = $processorRaw === '' ? $oldProcessor : $processorRaw;
    $newRam = $ramRaw === '' ? $oldRam : $ramRaw;
    $newStorage = $storageRaw === '' ? $oldStorage : $storageRaw;

    $specChanged =
        (string)$newProcessor !== (string)$oldProcessor ||
        (string)$newRam !== (string)$oldRam ||
        (string)$newStorage !== (string)$oldStorage;

    $priceChanged = (string)$newPrice !== (string)$oldPrice;

    if ($error !== '') {
        // Validation error already prepared above.
    } elseif (!$specChanged && !$priceChanged && $notes === '') {
        $error = 'No changes were entered. You can update Processor, RAM, Storage, Selling Price, or add a maintenance note before saving.';
    } else {
        try {
            $conn->beginTransaction();

            $maintenance = $conn->prepare("
                INSERT INTO owner_inventory_maintenance (
                    owner_key,
                    item_id,
                    old_processor,
                    new_processor,
                    old_ram,
                    new_ram,
                    old_storage,
                    new_storage,
                    notes,
                    performed_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $maintenance->execute([
                'imans_hustle',
                $id,
                $oldProcessor,
                $newProcessor,
                $oldRam,
                $newRam,
                $oldStorage,
                $newStorage,
                $notes !== '' ? $notes : null,
                $user_id
            ]);

            $update = $conn->prepare("
                UPDATE `iman_hustle_items`
                SET processor = ?,
                    ram = ?,
                    storage = ?,
                    planned_selling_price = ?
                WHERE id = ?
            ");

            $update->execute([
                $newProcessor,
                $newRam,
                $newStorage,
                $newPrice,
                $id
            ]);

            try {
                $changes = [];

                if ((string)$oldProcessor !== (string)$newProcessor) {
                    $changes[] = 'Processor: ' . displayValue($oldProcessor) . ' → ' . displayValue($newProcessor);
                }
                if ((string)$oldRam !== (string)$newRam) {
                    $changes[] = 'RAM: ' . displayValue($oldRam) . ' → ' . displayValue($newRam);
                }
                if ((string)$oldStorage !== (string)$newStorage) {
                    $changes[] = 'Storage: ' . displayValue($oldStorage) . ' → ' . displayValue($newStorage);
                }
                if ((string)$oldPrice !== (string)$newPrice) {
                    $changes[] = 'Selling Price: ' . (($oldPrice === null || $oldPrice === '') ? '-' : 'KES '.number_format((float)$oldPrice,2)) . ' → ' . (($newPrice === null || $newPrice === '') ? '-' : 'KES '.number_format((float)$newPrice,2));
                }

                $details = "Updated Iman\'s Hustle specs for " . $currentSerial . ".";

                if ($changes) {
                    $details .= ' Changes: ' . implode('; ', $changes) . '.';
                }

                if ($notes !== '') {
                    $details .= ' Notes: ' . $notes . '.';
                }

                

                $log = $conn->prepare("
                    INSERT INTO activity_logs (user_id, action, details)
                    VALUES (?, 'Owner Inventory - Update Specs', ?)
                ");
                $log->execute([$user_id, $details]);
            } catch (Throwable $ignored) {}

            $conn->commit();

            $success = 'Your changes were saved successfully. Hardware changes were recorded in maintenance history, and the selling price was updated only if you entered a new price.';

            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC) ?: $item;

            $currentStatus = trim((string)($item['status'] ?? ''));
            $currentSerial = displayValue($item['serial_number'] ?? null, '#' . $id);
            $currentModel = displayValue($item['model_name'] ?? null);
            $currentItemType = displayValue($item['item_type'] ?? null);
            $currentProcessor = displayValue($item['processor'] ?? null);
            $currentRam = displayValue($item['ram'] ?? null);
            $currentStorage = displayValue($item['storage'] ?? null);
            $currentLocation = displayValue($item['location'] ?? null);
            $currentPrice = $item['planned_selling_price'] ?? null;
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error = 'We could not update the specifications. Database message: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Update Specs | <?= safe($currentSerial) ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --primary:#1a4b2a; --primary-light:#2a6b3a;
            --gray-50:#f9fafb; --gray-100:#f3f4f6; --gray-200:#e5e7eb; --gray-300:#d1d5db;
            --gray-400:#9ca3af; --gray-500:#6b7280; --gray-600:#4b5563; --gray-700:#374151;
            --gray-800:#1f2937; --gray-900:#111827;
            --success-bg:#ecfdf5; --success-border:#a7f3d0; --success-text:#065f46;
            --error-bg:#fef2f2; --error-border:#fecaca; --error-text:#991b1b;
            --warning-bg:#fffbeb; --warning-border:#fde68a; --warning-text:#92400e;
            --radius-md:.5rem; --radius-lg:.75rem; --radius-xl:1rem;
            --shadow-sm:0 1px 2px rgb(0 0 0 / .05);
            --shadow-md:0 8px 24px rgb(15 23 42 / .08);
            --font:'Inter',system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
        }

        * { box-sizing:border-box; margin:0; padding:0; }

        body {
            font-family:var(--font);
            background:var(--gray-100);
            color:var(--gray-800);
            line-height:1.5;
            overflow-x:hidden;
        }

        .main-content {
            margin-left:260px;
            width:calc(100% - 260px);
            min-height:100vh;
            padding:2rem;
        }

        .page-header {
            background:#fff;
            border:1px solid var(--gray-200);
            border-radius:var(--radius-xl);
            padding:1.5rem 2rem;
            margin-bottom:1.5rem;
            box-shadow:var(--shadow-sm);
        }

        .page-header h1 {
            display:flex;
            align-items:center;
            gap:.75rem;
            flex-wrap:wrap;
            font-size:1.7rem;
            color:var(--gray-900);
            font-weight:650;
            margin-bottom:.4rem;
        }

        .page-header h1 i { color:var(--primary); }

        .serial-code {
            font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
            font-size:.9rem;
            background:var(--gray-100);
            border:1px solid var(--gray-200);
            padding:.3rem .65rem;
            border-radius:.5rem;
            color:var(--gray-700);
        }

        .breadcrumb { color:var(--gray-500); font-size:.88rem; }
        .breadcrumb a { color:var(--primary); text-decoration:none; }

        .page-description {
            margin-top:.7rem;
            max-width:900px;
            color:var(--gray-500);
            font-size:.88rem;
            line-height:1.6;
        }

        .alert {
            padding:1rem 1.2rem;
            border-radius:var(--radius-lg);
            margin-bottom:1.25rem;
            display:flex;
            align-items:flex-start;
            gap:.75rem;
            box-shadow:var(--shadow-sm);
        }

        .alert-error { background:var(--error-bg); border:1px solid var(--error-border); color:var(--error-text); }
        .alert-success { background:var(--success-bg); border:1px solid var(--success-border); color:var(--success-text); }

        .card {
            background:#fff;
            border:1px solid var(--gray-200);
            border-radius:var(--radius-xl);
            overflow:hidden;
            box-shadow:var(--shadow-sm);
        }

        .card-header {
            padding:1rem 1.5rem;
            border-bottom:1px solid var(--gray-200);
            background:var(--gray-50);
            display:flex;
            align-items:center;
            gap:.6rem;
            font-weight:650;
            color:var(--gray-700);
        }

        .card-header i { color:var(--primary); }

        .status-badge {
            margin-left:auto;
            display:inline-flex;
            align-items:center;
            gap:.35rem;
            border-radius:999px;
            padding:.3rem .7rem;
            font-size:.72rem;
            font-weight:700;
            background:<?= $currentStatus === 'In Stock' ? "'#dcfce7'" : "'#fee2e2'" ?>;
            color:<?= $currentStatus === 'In Stock' ? "'#166534'" : "'#b91c1c'" ?>;
        }

        .card-body { padding:1.5rem; }

        .info-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(185px,1fr));
            gap:.8rem;
            margin-bottom:1.5rem;
        }

        .info-item {
            background:var(--gray-50);
            border:1px solid var(--gray-200);
            border-radius:var(--radius-lg);
            padding:.9rem 1rem;
        }

        .info-label {
            font-size:.65rem;
            font-weight:700;
            color:var(--gray-500);
            text-transform:uppercase;
            letter-spacing:.05em;
            margin-bottom:.2rem;
        }

        .info-value {
            font-size:.92rem;
            font-weight:600;
            color:var(--gray-800);
            word-break:break-word;
        }

        .price-note {
            display:flex;
            align-items:flex-start;
            gap:.65rem;
            background:var(--warning-bg);
            border:1px solid var(--warning-border);
            color:var(--warning-text);
            padding:.85rem 1rem;
            border-radius:var(--radius-lg);
            margin-bottom:1.25rem;
            font-size:.84rem;
        }

        .section-title {
            font-size:.95rem;
            font-weight:700;
            color:var(--gray-800);
            margin-bottom:1rem;
        }

        .form-grid {
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:1rem;
        }

        .form-group {
            display:flex;
            flex-direction:column;
            gap:.4rem;
        }

        .form-group label {
            font-size:.84rem;
            font-weight:600;
            color:var(--gray-700);
        }

        .small {
            font-size:.72rem;
            font-weight:400;
            color:var(--gray-400);
        }

        .form-group input,
        .form-group textarea {
            width:100%;
            padding:.75rem .9rem;
            border:1px solid var(--gray-300);
            border-radius:var(--radius-md);
            background:#fff;
            color:var(--gray-800);
            font:inherit;
        }

        .form-group textarea { min-height:95px; resize:vertical; }

        .form-group input:focus,
        .form-group textarea:focus {
            outline:none;
            border-color:var(--primary);
            box-shadow:0 0 0 3px rgb(26 75 42 / .1);
        }

        .help-text { font-size:.7rem; color:var(--gray-400); }

        .actions {
            display:flex;
            justify-content:flex-end;
            gap:.75rem;
            flex-wrap:wrap;
            margin-top:1.4rem;
            padding-top:1.4rem;
            border-top:1px solid var(--gray-200);
        }

        .btn {
            border:none;
            border-radius:var(--radius-md);
            padding:.75rem 1rem;
            font:inherit;
            font-size:.86rem;
            font-weight:600;
            cursor:pointer;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:.45rem;
        }

        .btn-primary { background:var(--primary); color:#fff; }
        .btn-primary:hover { background:var(--primary-light); }

        .btn-secondary {
            background:#fff;
            color:var(--gray-700);
            border:1px solid var(--gray-300);
        }

        @media (max-width:1200px) {
            .main-content { margin-left:0; width:100%; padding:5rem 1rem 1rem; }
        }

        @media (max-width:760px) {
            .page-header { padding:1.2rem; }
            .page-header h1 { font-size:1.3rem; }
            .card-body { padding:1.1rem; }
            .form-grid { grid-template-columns:1fr; }
            .actions { flex-direction:column-reverse; }
            .btn { width:100%; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1>
            <i class="fas fa-microchip"></i>
            Update Item Specs
            <span class="serial-code"><?= safe($currentSerial) ?></span>
        </h1>

        <div class="breadcrumb">
            <a href="../dashboard/superadmindashboard.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <span> / </span>
            <a href="overview.php">Iman's Hustle</a>
            <span> / </span>
            <span>Update Specs</span>
        </div>

        <div class="page-description">
            Review the item's current details below. Update only what changed. You can change the selling price on its own, update hardware specs, or do both. Any field left blank keeps its current value.
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <strong>Specifications were not updated.</strong>
                <?= safe($error) ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Update completed successfully.</strong>
                <?= safe($success) ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-circle-info"></i>
            Current Item Information

            <span class="status-badge">
                <i class="fas <?= $currentStatus === 'In Stock' ? 'fa-check-circle' : 'fa-circle-xmark' ?>"></i>
                <?= safe($currentStatus !== '' ? $currentStatus : 'Unknown') ?>
            </span>
        </div>

        <div class="card-body">

            <div class="info-grid">
                <div class="info-item"><div class="info-label">Serial Number</div><div class="info-value"><?= safe($currentSerial) ?></div></div>
                <div class="info-item"><div class="info-label">Model</div><div class="info-value"><?= safe($currentModel) ?></div></div>
                <div class="info-item"><div class="info-label">Item Type</div><div class="info-value"><?= safe($currentItemType) ?></div></div>
                <div class="info-item"><div class="info-label">Processor</div><div class="info-value"><?= safe($currentProcessor) ?></div></div>
                <div class="info-item"><div class="info-label">Current RAM</div><div class="info-value"><?= safe($currentRam) ?></div></div>
                <div class="info-item"><div class="info-label">Current Storage</div><div class="info-value"><?= safe($currentStorage) ?></div></div>
                <div class="info-item"><div class="info-label">Location</div><div class="info-value"><?= safe($currentLocation) ?></div></div>
                <div class="info-item">
                    <div class="info-label">Set Selling Price</div>
                    <div class="info-value">
                        <?= ($currentPrice !== null && $currentPrice !== '') ? 'KES ' . number_format((float)$currentPrice, 2) : '-' ?>
                    </div>
                </div>
            </div>

            <?php if ($currentStatus === 'In Stock'): ?>

               

                <div class="section-title">
                    <i class="fas fa-screwdriver-wrench"></i>
                    Enter the New Specifications
                </div>

                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>New Processor / CPU <span class="small">(leave blank to keep current)</span></label>
                            <input type="text" name="processor" placeholder="Current: <?= safe($currentProcessor) ?>">
                            <span class="help-text">Example: Intel Core i7 11th Gen</span>
                        </div>

                        <div class="form-group">
                            <label>New RAM <span class="small">(leave blank to keep current)</span></label>
                            <input type="text" name="ram" placeholder="Current: <?= safe($currentRam) ?>">
                            <span class="help-text">Example: 16GB</span>
                        </div>

                        <div class="form-group">
                            <label>New Storage <span class="small">(leave blank to keep current)</span></label>
                            <input type="text" name="storage" placeholder="Current: <?= safe($currentStorage) ?>">
                            <span class="help-text">Example: 512GB SSD</span>
                        </div>

                        <div class="form-group">
                            <label>Maintenance Notes <span class="small">(optional)</span></label>
                            <textarea name="notes" placeholder="Explain what was upgraded, replaced, repaired or changed"></textarea>
                        </div>
                    </div>

                    <div class="actions">
                        <a href="overview.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Overview
                        </a>

                        <a href="edit_device.php?id=<?= $id ?>" class="btn btn-secondary">
                            <i class="fas fa-edit"></i> Edit Other Details
                        </a>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Specification Update
                        </button>
                    </div>
                </form>

            <?php endif; ?>

        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
