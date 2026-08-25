<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth_check.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'], true)) {
    die('Access denied! Only administrators can upload devices.');
}

$added_by = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$success = '';
$error = '';
$skippedSerials = [];
$invalidDataErrors = [];
$skippedMonitors = [];
$skippedMonitorCount = 0;

$stmt = $conn->prepare('SELECT branch, email FROM users WHERE id = ?');
$stmt->execute([$added_by]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$user_branch = (string)($currentUser['branch'] ?? 'KIMATHI');
$user_email = strtolower(trim((string)($currentUser['email'] ?? '')));

/**
 * Inventory-admin emails allowed to upload Iman Inventory / Iman's Hustle.
 * Add more email addresses to this array when you want to grant permission.
 */
$ownerUploadAllowedEmails = [
    'stephanie@mombasacomputers.co.ke',
];

$canUploadOwnerInventory =
    in_array($role, ['super_admin', 'manager'], true) ||
    ($role === 'inventory_admin' && in_array($user_email, $ownerUploadAllowedEmails, true));

$catStmt = $conn->query('SELECT id, category_name FROM categories ORDER BY category_name');
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
$catMap = [];
foreach ($categories as $cat) {
    $catMap[strtolower(trim($cat['category_name']))] = ['id'=>(int)$cat['id'], 'name'=>$cat['category_name']];
}

function cleanHeader($v) {
    return strtolower(trim(preg_replace('/\s+/', ' ', (string)$v)));
}
function numericValue($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '-') return null;
    $v = str_replace([',', 'KES', 'Ksh', 'KSH', '$'], '', $v);
    return is_numeric(trim($v)) ? (float)trim($v) : null;
}
function ramValue($v) {
    return preg_match('/(\d{1,3})/', (string)$v, $m) ? (int)$m[1] : 0;
}
function storageValue($v, $defaultType='HDD') {
    $raw = strtoupper(trim((string)$v));
    $type = preg_match('/\b(SSD|NVME)\b/', $raw) ? 'SSD' : (preg_match('/\bHDD\b/', $raw) ? 'HDD' : $defaultType);
    $cap = 0;
    if (preg_match('/(\d+(?:\.\d+)?)\s*TB\b/', $raw, $m)) $cap = (int)round((float)$m[1] * 1000);
    elseif (preg_match('/(\d+(?:\.\d+)?)\s*GB\b/', $raw, $m)) $cap = (int)round((float)$m[1]);
    elseif (preg_match('/\b(\d{2,4})\b/', $raw, $m)) $cap = (int)$m[1];
    return [$type, $cap];
}
function touchValue($v) {
    $k = strtolower(trim((string)$v));
    if ($k === '' || $k === '-' || $k === 'n/a' || $k === 'na') return 'N/A';
    if (in_array($k, ['yes','y','touch','touchscreen','touch screen'], true)) return 'Touch';
    if (in_array($k, ['no','n','non-touch','non touch','nontouch'], true)) return 'Non-touch';
    return 'N/A';
}
function placeForCategory($categoryName) {
    return strtolower((string)$categoryName) === 'laptop' ? 'store' : 'display';
}

/**
 * Resolve an owner-sheet Form Factor directly to an existing devices category.
 * No user-selected default category is required.
 */
function categoryFromFormFactor($formFactor, array $catMap) {
    $raw = strtolower(trim((string)$formFactor));
    if ($raw === '' || $raw === '-') return null;

    // First allow an exact category name from the database.
    if (isset($catMap[$raw])) return $catMap[$raw];

    $normalized = preg_replace('/[^a-z0-9]+/', ' ', $raw);
    $normalized = trim(preg_replace('/\s+/', ' ', $normalized));

    $wanted = null;

    if (preg_match('/\b(all in one|allinone|aio)\b/', $normalized)) {
        $wanted = 'aio';
    } elseif (preg_match('/\b(workstation)\b/', $normalized)) {
        $wanted = 'workstation';
    } elseif (preg_match('/\b(pos)\b/', $normalized)) {
        $wanted = 'pos';
    } elseif (preg_match('/\b(mini pc|mini|tiny|micro pc|micro)\b/', $normalized)) {
        $wanted = 'mini pc';
    } elseif (preg_match('/\b(desktop|tower|sff|small form factor|mt|microtower)\b/', $normalized)) {
        $wanted = 'desktop';
    } elseif (preg_match('/\b(laptop|notebook|ultrabook)\b/', $normalized)) {
        $wanted = 'laptop';
    }

    return ($wanted !== null && isset($catMap[$wanted])) ? $catMap[$wanted] : null;
}

/**
 * Iman Inventory does not have a Form Factor column in its existing workbook.
 * Infer the category from recognizable model-family text without asking the user
 * to select a default category.
 */
function categoryFromModel($model, array $catMap) {
    $m = strtolower(trim((string)$model));

    if ($m === '') return null;

    $wanted = 'laptop'; // Most rows in this sheet are portable computers.

    if (preg_match('/\b(aio|all[ -]?in[ -]?one|eliteone|proone)\b/', $m)) {
        $wanted = 'aio';
    } elseif (preg_match('/\b(workstation|thinkstation|precision tower|z[2468][ -]?g\d)\b/', $m)) {
        $wanted = 'workstation';
    } elseif (preg_match('/\b(mini pc|tiny|mini|micro)\b/', $m)) {
        $wanted = 'mini pc';
    } elseif (preg_match('/\b(desktop|elitedesk|prodesk|thinkcentre|optiplex|tower|sff)\b/', $m)) {
        $wanted = 'desktop';
    } elseif (preg_match('/\b(pos)\b/', $m)) {
        $wanted = 'pos';
    }

    return $catMap[$wanted] ?? null;
}

/**
 * LOCATION rules for owner inventory:
 * - blank => user's assigned branch, place=store
 * - WAREHOUSE => branch=WAREHOUSE, place=warehouse
 * - MOI/KIMATHI => that branch, place=store
 */
function ownerLocationValues($location, $userBranch) {
    $loc = strtoupper(trim((string)$location));

    if ($loc === '' || $loc === '-') {
        return [$userBranch, 'store'];
    }

    if (strpos($loc, 'WAREHOUSE') !== false || strpos($loc, 'WARE HOUSE') !== false) {
        return ['WAREHOUSE', 'warehouse'];
    }

    if (strpos($loc, 'KIMATHI') !== false) {
        return ['KIMATHI', 'store'];
    }

    if (strpos($loc, 'MOI') !== false) {
        return ['MOI', 'store'];
    }

    // Unknown location: do not guess a new branch; safely fall back to user's branch.
    return [$userBranch, 'store'];
}

function normalBranchFromSpecs($branchRaw, $userBranch) {
    $branch = strtoupper(trim((string)$branchRaw));
    if ($branch === '' || $branch === '-') return $userBranch;
    return in_array($branch, ['KIMATHI','MOI','WAREHOUSE'], true) ? $branch : $userBranch;
}

function ownerUploadStatus($v, array &$rowErrors) {
    $raw = strtolower(trim((string)$v));
    if ($raw === '' || $raw === '-') return 'In Stock';
    if ($raw === 'sold') return 'Sold';
    if (in_array($raw, ['instock','in stock','in-stock'], true)) return 'In Stock';
    $rowErrors[] = "Status must be Sold or In Stock";
    return 'In Stock';
}
function isMonitorRow($mode, $category, $model, $formFactor, $processor, $ramRaw, $storageRaw) {
    $category = strtolower(trim((string)$category));
    $model = strtolower(trim((string)$model));
    $formFactor = strtolower(trim((string)$formFactor));
    if ($mode === 'normal') {
        return $category === 'monitor' || $category === 'monitors' || strpos($category, 'monitor') !== false;
    }
    if (strpos($formFactor, 'monitor') !== false || strpos($formFactor, 'display') !== false || strpos($formFactor, 'screen') !== false || strpos($model, ' monitor') !== false || strpos($model, 'monitor ') === 0) {
        return true;
    }
    $cpuBlank = trim((string)$processor) === '' || trim((string)$processor) === '-';
    $ramBlank = trim((string)$ramRaw) === '' || trim((string)$ramRaw) === '-';
    $storageBlank = trim((string)$storageRaw) === '' || trim((string)$storageRaw) === '-';
    return $cpuBlank && $ramBlank && $storageBlank && trim($model) !== '';
}


/**
 * Enrich an existing owner-inventory device without overwriting anything.
 *
 * Only columns whose CURRENT database value is SQL NULL are eligible.
 * Incoming blank / "-" / null values are ignored.
 * Column names are hardcoded in this whitelist, so they never come from user input.
 *
 * Returns the list of columns actually updated.
 */
function fillOnlyNullDeviceColumns(PDO $conn, array $existing, array $incoming, string $serial): array {
    $allowedColumns = [
        'category_id',
        'model_name',
        'processor',
        'ram',
        'storage_type',
        'storage_capacity',
        'touch',
        'inventory_owner',
        'branch',
        'place',
        'asset_id',
        'manufacturer',
        'form_factor',
        'grade',
        'buying_price',
        'price',
        'owner_profit',
        'owner_notes',
        'symetic',
        'dollar_value',
        'webcam',
        'owner_location'
    ];

    $sets = [];
    $params = [];
    $updatedColumns = [];

    foreach ($allowedColumns as $column) {
        // Strict rule requested: update ONLY when the current DB value is SQL NULL.
        if (!array_key_exists($column, $existing) || $existing[$column] !== null) {
            continue;
        }

        if (!array_key_exists($column, $incoming)) {
            continue;
        }

        $value = $incoming[$column];

        // Do not fill a NULL database field with another missing spreadsheet value.
        if ($value === null) {
            continue;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || $value === '-') {
                continue;
            }
        }

        $placeholder = ':fill_' . $column;
        $sets[] = "`$column` = $placeholder";
        $params[$placeholder] = $value;
        $updatedColumns[] = $column;
    }

    if (!$sets) {
        return [];
    }

    $params[':fill_serial'] = $serial;

    $sql = "UPDATE devices
            SET " . implode(', ', $sets) . "
            WHERE serial_number = :fill_serial";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return $updatedColumns;
}

$uploadMode = $_POST['upload_mode'] ?? 'normal';

$allowedUploadModes = ['normal'];
if ($canUploadOwnerInventory) {
    $allowedUploadModes[] = 'imans_hustle';
    $allowedUploadModes[] = 'iman_inventory';
}

if (!in_array($uploadMode, $allowedUploadModes, true)) {
    $uploadMode = 'normal';
}


// Download a real .xlsx template for the selected upload mode.
$templateMode = $_GET['download_template'] ?? '';
if ($templateMode !== '' && !in_array($templateMode, $allowedUploadModes, true)) {
    http_response_code(403);
    exit('You do not have permission to download this upload template.');
}

if (in_array($templateMode, $allowedUploadModes, true)) {
    $template = new Spreadsheet();
    $sheet = $template->getActiveSheet();

    if ($templateMode === 'normal') {
        $sheet->setTitle('Normal Inventory');
        $headers = ['serial_number','category','specs'];
        $sample = ['5CG1234XYZ','Laptop','HP EliteBook 840 G8 | Core i5 11th Gen | 16GB | 512GB SSD | Intel Iris Xe | Non-touch | Ex-Uk | AC16 | KIMATHI'];
        $filename = 'normal_inventory_upload_template.xlsx';
    } elseif ($templateMode === 'imans_hustle') {
        $sheet->setTitle("Iman Hustle");
        $headers = ['Asset ID','MFG','Model','Form Factor','CPU','Ram','Storage','Serial','Grade','B.P','S.P','PROFIT','NOTES','Status'];
        $sample = ['IH-001','HP','EliteBook 840 G8','Laptop','Core i5 11th Gen','16GB','512GB SSD','5CG1234XYZ','A','250','350','100','','In Stock'];
        $filename = 'iman_hustle_upload_template.xlsx';
    } else {
        $sheet->setTitle('Iman Inventory');
        $headers = ['Asset ID','Symetic','$','BP','SP','PROFIT','MFG','Model','CPU','RAM','Storage','Serial #','Grade','Touch Screen','Webcam','Notes','LOCATION','Status'];
        $sample = ['II-001','250','350','32000','45000','13000','HP','EliteBook 840 G8','Core i5 11th Gen','16GB','512GB SSD','5CG1234XYZ','A','Non-touch','Yes','','WAREHOUSE','In Stock'];
        $filename = 'iman_inventory_upload_template.xlsx';
    }

    foreach ($headers as $i => $headerText) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue($col.'1', $headerText);
        $sheet->setCellValue($col.'2', $sample[$i] ?? '');
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $lastCol = Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray([
        'font'=>['bold'=>true,'color'=>['rgb'=>'111827']],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'EABF30']],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);
    $sheet->getStyle('A2:'.$lastCol.'2')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->freezePane('A2');
    $sheet->setAutoFilter('A1:'.$lastCol.'1');

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: max-age=0, no-store, no-cache, must-revalidate');
    (new Xlsx($template))->save('php://output');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    if (!in_array(strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION)), ['xlsx','xls','csv'], true)) {
        $error = 'Invalid file type. Please upload .xlsx, .xls or .csv.';
    } else {
        try {
            $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            if (!$rows) throw new Exception('The uploaded spreadsheet is empty.');

            $headers = array_map('cleanHeader', array_shift($rows));
            $normalIndex = [];
            if ($uploadMode === 'normal') {
                foreach ($headers as $i=>$h) $normalIndex[$h] = $i;
                foreach (['serial_number','category','specs'] as $required) {
                    if (!array_key_exists($required, $normalIndex)) throw new Exception("Normal upload is missing required column: $required");
                }
            }

            $insert = $conn->prepare("INSERT INTO devices
                (serial_number, category_id, model_name, processor, graphics, ram, storage_type, storage_capacity, touch, status,
                 inventory_owner, device_condition, added_by, branch, cargo_number, place, asset_id, manufacturer, form_factor, grade,
                 buying_price, price, owner_profit, owner_notes, symetic, dollar_value, webcam, owner_location)
                VALUES
                (:serial_number,:category_id,:model_name,:processor,:graphics,:ram,:storage_type,:storage_capacity,:touch,:status,
                 :inventory_owner,'Ex-Uk',:added_by,:branch,'NO CARGO',:place,:asset_id,:manufacturer,:form_factor,:grade,
                 :buying_price,:price,:owner_profit,:owner_notes,:symetic,:dollar_value,:webcam,:owner_location)");
            $dup = $conn->prepare('SELECT * FROM devices WHERE serial_number = ? LIMIT 1');
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Bulk upload', ?)");

            $added = 0; $duplicates = 0; $ownerDuplicatesUpdated = 0; $ownerDuplicatesNoChange = 0; $invalid = 0;
            foreach ($rows as $ri=>$row) {
                $rowNumber = $ri + 2;
                if (!array_filter($row, fn($v)=>trim((string)$v) !== '')) continue;

                $d = [
                    'asset_id'=>null,'manufacturer'=>null,'form_factor'=>null,'grade'=>null,'buying_price'=>null,'price'=>null,
                    'owner_profit'=>null,'owner_notes'=>null,'symetic'=>null,'dollar_value'=>null,'webcam'=>null,'owner_location'=>null,
                    'graphics'=>'None','touch'=>'N/A','inventory_owner'=>null,'status'=>'In Stock'
                ];
                $rowErrors = [];
                $place = 'store';

                if ($uploadMode === 'normal') {
                    $serial = trim((string)($row[$normalIndex['serial_number']] ?? ''));
                    $catName = trim((string)($row[$normalIndex['category']] ?? ''));
                    $specs = trim((string)($row[$normalIndex['specs']] ?? ''));
                    if (isMonitorRow('normal', $catName, '', '', '', '', '')) {
                        $skippedMonitorCount++;
                        $skippedMonitors[] = $serial ?: "Row $rowNumber";
                        continue;
                    }
                    $ck = strtolower($catName);
                    $cat = $catMap[$ck] ?? null;
                    if (!$cat) $rowErrors[] = "Category '$catName' was not found";
                    $parts = array_map('trim', preg_split('/\s*[|,]\s*/', $specs) ?: []);
                    $d['model_name']=$parts[0]??''; $d['processor']=$parts[1]??'';
                    $d['ram']=ramValue($parts[2]??''); [$d['storage_type'],$d['storage_capacity']]=storageValue($parts[3]??'', 'SSD');
                    $d['graphics']=(!empty($parts[4]) && $parts[4] !== '-') ? $parts[4] : 'None';
                    $d['touch']=touchValue($parts[5]??'');
                    $condition = strtolower(trim($parts[6]??''));
                    $deviceCondition = in_array($condition,['refurbished','refurb','ref'],true) ? 'Refurbished' : ($condition==='new' ? 'New' : 'Ex-Uk');
                    $cargo = trim($parts[7]??''); if ($cargo===''||$cargo==='-') $cargo='NO CARGO';
                    $branch = normalBranchFromSpecs($parts[8]??'', $user_branch);
                    $d['category_id']=$cat['id']??0; $d['category_name']=$cat['name']??$catName;
                    $d['device_condition']=$deviceCondition; $d['cargo_number']=$cargo;
                    $place = placeForCategory($d['category_name']);
                } elseif ($uploadMode === 'imans_hustle') {
                    // Exact Iman Hustle sheet order:
                    // Asset ID | MFG | Model | Form Factor | CPU | Ram | HDD | Serial | Grade | B.P | S.P | PROFIT | NOTES
                    $d['asset_id']=trim((string)($row[0]??'')) ?: null;
                    $d['manufacturer']=trim((string)($row[1]??'')) ?: null;
                    $d['model_name']=trim((string)($row[2]??''));
                    $d['form_factor']=trim((string)($row[3]??'')) ?: null;
                    $d['processor']=trim((string)($row[4]??''));
                    $ramRaw = $row[5]??'';
                    $storageRaw = $row[6]??'';
                    $serial=trim((string)($row[7]??''));
                    if (isMonitorRow('imans_hustle', '', $d['model_name'], (string)$d['form_factor'], $d['processor'], $ramRaw, $storageRaw)) {
                        $skippedMonitorCount++;
                        $skippedMonitors[] = $serial ?: ($d['model_name'] ?: "Row $rowNumber");
                        continue;
                    }
                    $d['ram']=ramValue($ramRaw);
                    [$d['storage_type'],$d['storage_capacity']]=storageValue($storageRaw, 'HDD');
                    $d['grade']=trim((string)($row[8]??'')) ?: null;
                    $d['buying_price']=numericValue($row[9]??'');
                    $d['price']=numericValue($row[10]??'');
                    $d['owner_profit']=numericValue($row[11]??'');
                    if ($d['owner_profit']===null && $d['buying_price']!==null && $d['price']!==null) $d['owner_profit']=$d['price']-$d['buying_price'];
                    $d['owner_notes']=trim((string)($row[12]??'')) ?: null;
                    $d['status']=ownerUploadStatus($row[13]??'', $rowErrors);
                    if ($d['status']==='Sold' && $d['price']===null) {
                        $rowErrors[]='Selling price (S.P) is required when Status is Sold';
                    }
                    $d['inventory_owner']='imans_hustle';
                    $cat = categoryFromFormFactor($d['form_factor'], $catMap);
                    $d['category_id']=(int)($cat['id']??0);
                    $d['category_name']=$cat['name']??'';
                    if (!$cat) {
                        $rowErrors[] = "Form Factor '" . ($d['form_factor'] ?? '') . "' could not be matched to Laptop, AIO, Desktop, Mini Pc, Workstation or POS";
                    }
                    $d['device_condition']='Ex-Uk';
                    $d['cargo_number']='NO CARGO';
                    $branch=$user_branch;
                    $place='store';
                } else {
                    // Exact Iman Inventory sheet order:
                    // Asset ID | Symetic | $ | BP | SP | PROFIT | MFG | Model | CPU | RAM | HDD | Serial # | Grade | Touch Screen | Webcam | Notes | LOCATION
                    $d['asset_id']=trim((string)($row[0]??'')) ?: null;
                    $d['symetic']=trim((string)($row[1]??'')) ?: null;
                    $d['dollar_value']=trim((string)($row[2]??'')) ?: null;
                    $d['buying_price']=numericValue($row[3]??'');
                    $d['price']=numericValue($row[4]??'');
                    $d['owner_profit']=numericValue($row[5]??'');
                    if ($d['owner_profit']===null && $d['buying_price']!==null && $d['price']!==null) $d['owner_profit']=$d['price']-$d['buying_price'];
                    $d['manufacturer']=trim((string)($row[6]??'')) ?: null;
                    $d['model_name']=trim((string)($row[7]??''));
                    $d['processor']=trim((string)($row[8]??''));
                    $ramRaw = $row[9]??'';
                    $storageRaw = $row[10]??'';
                    $serial=trim((string)($row[11]??''));
                    if (isMonitorRow('iman_inventory', '', $d['model_name'], '', $d['processor'], $ramRaw, $storageRaw)) {
                        $skippedMonitorCount++;
                        $skippedMonitors[] = $serial ?: ($d['model_name'] ?: "Row $rowNumber");
                        continue;
                    }
                    $d['ram']=ramValue($ramRaw);
                    [$d['storage_type'],$d['storage_capacity']]=storageValue($storageRaw, 'HDD');
                    $d['grade']=trim((string)($row[12]??'')) ?: null;
                    $d['touch']=touchValue($row[13]??'');
                    $d['webcam']=trim((string)($row[14]??'')) ?: null;
                    $d['owner_notes']=trim((string)($row[15]??'')) ?: null;
                    $d['owner_location']=trim((string)($row[16]??'')) ?: null;
                    $d['status']=ownerUploadStatus($row[17]??'', $rowErrors);
                    if ($d['status']==='Sold' && $d['price']===null) {
                        $rowErrors[]='Selling price (SP) is required when Status is Sold';
                    }
                    $d['inventory_owner']='iman_inventory';
                    $cat = categoryFromModel($d['model_name'], $catMap);
                    $d['category_id']=(int)($cat['id']??0);
                    $d['category_name']=$cat['name']??'';
                    if (!$cat) {
                        $rowErrors[] = "Category could not be inferred from model '" . $d['model_name'] . "'";
                    }
                    $d['device_condition']='Ex-Uk';
                    $d['cargo_number']='NO CARGO';
                    [$branch, $place] = ownerLocationValues($d['owner_location'], $user_branch);
                }

                if ($serial==='') {
                    $rowErrors[]='Serial number is missing';
                }

                /*
                 * DUPLICATE HANDLING
                 * ------------------
                 * Normal Inventory:
                 *   preserve the original behavior and skip duplicate serials.
                 *
                 * Iman Inventory / Iman's Hustle:
                 *   do NOT overwrite the existing record. Fill only columns that
                 *   are currently SQL NULL, using meaningful values from this row.
                 */
                $existingDevice = null;
                if ($serial !== '') {
                    $dup->execute([$serial]);
                    $existingDevice = $dup->fetch(PDO::FETCH_ASSOC) ?: null;
                }

                if ($existingDevice && $uploadMode === 'normal') {
                    $duplicates++;
                    $skippedSerials[] = $serial;
                    continue;
                }

                if ($existingDevice && in_array($uploadMode, ['imans_hustle','iman_inventory'], true)) {
                    /*
                     * Do not run "new device" required-field validation here.
                     * A duplicate row is allowed to contain only the information
                     * that was previously missing from the database.
                     */
                    $incomingForNullFill = [
                        'category_id' => !empty($d['category_id']) ? (int)$d['category_id'] : null,
                        'model_name' => trim((string)($d['model_name'] ?? '')) ?: null,
                        'processor' => trim((string)($d['processor'] ?? '')) ?: null,
                        'ram' => ((int)($d['ram'] ?? 0) > 0) ? (int)$d['ram'] : null,
                        'storage_type' => ((int)($d['storage_capacity'] ?? 0) > 0)
                            ? ($d['storage_type'] ?? null)
                            : null,
                        'storage_capacity' => ((int)($d['storage_capacity'] ?? 0) > 0)
                            ? (int)$d['storage_capacity']
                            : null,

                        // Iman's Hustle sheet has no Touch column, so do not
                        // manufacture N/A for a duplicate. Iman Inventory does.
                        'touch' => (
                            $uploadMode === 'iman_inventory' &&
                            isset($row[13]) &&
                            trim((string)$row[13]) !== '' &&
                            trim((string)$row[13]) !== '-'
                        ) ? $d['touch'] : null,

                        'inventory_owner' => $d['inventory_owner'] ?? null,

                        // Branch/place follow the owner-upload rules already in this file.
                        'branch' => $branch ?: null,
                        'place' => $place ?: null,

                        'asset_id' => $d['asset_id'] ?? null,
                        'manufacturer' => $d['manufacturer'] ?? null,
                        'form_factor' => $d['form_factor'] ?? null,
                        'grade' => $d['grade'] ?? null,
                        'buying_price' => $d['buying_price'] ?? null,
                        'price' => $d['price'] ?? null,
                        'owner_profit' => $d['owner_profit'] ?? null,
                        'owner_notes' => $d['owner_notes'] ?? null,
                        'symetic' => $d['symetic'] ?? null,
                        'dollar_value' => $d['dollar_value'] ?? null,
                        'webcam' => $d['webcam'] ?? null,
                        'owner_location' => $d['owner_location'] ?? null,
                    ];

                    $filledColumns = fillOnlyNullDeviceColumns(
                        $conn,
                        $existingDevice,
                        $incomingForNullFill,
                        $serial
                    );

                    if ($filledColumns) {
                        $ownerDuplicatesUpdated++;

                        $log->execute([
                            $added_by,
                            "Enriched existing device $serial via $uploadMode Excel upload. Filled NULL columns only: " .
                            implode(', ', $filledColumns)
                        ]);
                    } else {
                        $ownerDuplicatesNoChange++;
                    }

                    continue;
                }

                // Required-field validation remains unchanged for genuinely NEW rows.
                if (empty($d['category_id'])) $rowErrors[]='Category could not be determined';
                if (trim((string)$d['model_name'])==='') $rowErrors[]='Model is missing';
                if (trim((string)$d['processor'])==='') $rowErrors[]='CPU/processor is missing';
                if ((int)$d['ram'] < 1) $rowErrors[]='RAM is missing or invalid';
                if ((int)$d['storage_capacity'] < 1) $rowErrors[]='Storage is missing or invalid';
                if ($rowErrors) { $invalid++; $invalidDataErrors[]="Row $rowNumber: ".implode('; ',$rowErrors); continue; }

                $insert->execute([
                    'serial_number'=>$serial,'category_id'=>$d['category_id'],'model_name'=>$d['model_name'],'processor'=>$d['processor'],
                    'graphics'=>$d['graphics'],'ram'=>$d['ram'],'storage_type'=>$d['storage_type'],'storage_capacity'=>$d['storage_capacity'],
                    'touch'=>$d['touch'],'status'=>$d['status'],'inventory_owner'=>$d['inventory_owner'],'added_by'=>$added_by,'branch'=>$branch,
                    'place'=>$place,'asset_id'=>$d['asset_id'],'manufacturer'=>$d['manufacturer'],
                    'form_factor'=>$d['form_factor'],'grade'=>$d['grade'],'buying_price'=>$d['buying_price'],'price'=>$d['price'],
                    'owner_profit'=>$d['owner_profit'],'owner_notes'=>$d['owner_notes'],'symetic'=>$d['symetic'],
                    'dollar_value'=>$d['dollar_value'],'webcam'=>$d['webcam'],'owner_location'=>$d['owner_location']
                ]);
                // Preserve condition/cargo for normal mode without changing owner-sheet defaults.
                if ($uploadMode==='normal') {
                    $q=$conn->prepare('UPDATE devices SET device_condition=?, cargo_number=? WHERE serial_number=?');
                    $q->execute([$d['device_condition'],$d['cargo_number'],$serial]);
                }
                $log->execute([$added_by, "Added device $serial via $uploadMode Excel upload"]);
                $added++;
            }
            $success="$added new device(s) added successfully.";
            if($duplicates) {
                $success.=" $duplicates Normal Inventory duplicate serial(s) skipped.";
            }
            if($ownerDuplicatesUpdated) {
                $success.=" $ownerDuplicatesUpdated existing Iman Inventory/Hustle device(s) enriched by filling NULL columns only.";
            }
            if($ownerDuplicatesNoChange) {
                $success.=" $ownerDuplicatesNoChange existing owner device(s) already had values in the applicable columns, so nothing was overwritten.";
            }
            if($skippedMonitorCount) $success.=" $skippedMonitorCount monitor row(s) skipped. Upload them through Monitor Bulk Upload.";
            if($invalid) $success.=" $invalid invalid row(s) skipped.";
        } catch (Throwable $e) {
            $error = 'Excel upload failed: '.$e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Bulk Upload Devices | Mombasa Computers</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root{--p:#1a4b2a;--bg:#f3f4f6;--b:#e5e7eb;--t:#1f2937;--m:#6b7280}*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:var(--bg);color:var(--t)}.main{margin-left:260px;padding:2rem;min-height:100vh}.box{background:#fff;border:1px solid var(--b);border-radius:14px;padding:1.4rem;margin-bottom:1rem}.box h1,.box h2{margin-top:0}.alert{padding:1rem;border-radius:9px;margin-bottom:1rem}.ok{background:#ecfdf5;color:#065f46}.err{background:#fef2f2;color:#991b1b}.warn{background:#fffbeb;color:#92400e}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:1rem}.g{display:flex;flex-direction:column;gap:.35rem}.g label{font-weight:650;font-size:.85rem}.g select,.g input{padding:.7rem;border:1px solid #d1d5db;border-radius:8px}.btn{width:100%;padding:.8rem;border:0;border-radius:8px;background:var(--p);color:#fff;font-weight:700;cursor:pointer}.format{overflow:auto;border:1px solid var(--b);border-radius:9px;margin-top:1rem}.format table{border-collapse:collapse;width:100%;min-width:800px}.format th,.format td{padding:.65rem;border-right:1px solid var(--b);white-space:nowrap}.format th{background:#eabf30;color:#111;font-size:.78rem}.help{font-size:.85rem;color:var(--m);line-height:1.55}.template-btn{display:inline-flex;align-items:center;gap:.4rem;background:#166534;color:#fff;text-decoration:none;padding:.55rem .8rem;border-radius:7px;font-size:.8rem;font-weight:700;margin:.65rem 0}.rules{font-size:.82rem;color:var(--m);line-height:1.6;margin:.75rem 0 0;padding-left:1.2rem}.monitor-note{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;border-radius:8px;padding:.75rem;margin-top:.8rem;font-size:.82rem;line-height:1.5}.hidden{display:none!important}@media(max-width:1200px){.main{margin-left:0;padding:5rem 1rem 1rem}}
</style></head><body>
<?php include '../includes/sidebar.php'; ?>
<main class="main">
<section class="box"><h1><i class="fas fa-file-excel"></i> Bulk Upload Devices</h1><div class="help">Choose the inventory type first. The uploader then reads that spreadsheet in its existing layout; you do not need to rearrange Iman Hustle or Iman Inventory files.</div></section>
<?php if($success):?><div class="alert ok"><?=htmlspecialchars($success)?></div><?php endif;?>
<?php if($error):?><div class="alert err"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($skippedSerials):?><div class="alert warn"><strong>Duplicates skipped:</strong> <?=htmlspecialchars(implode(', ',array_unique($skippedSerials)))?></div><?php endif;?>
<?php if($skippedMonitors):?><div class="alert warn"><strong>Monitor rows skipped:</strong> <?=htmlspecialchars(implode(', ',array_unique($skippedMonitors)))?><br><span class="help">These were intentionally not inserted into the devices table. Use the Monitor Bulk Upload page for them.</span></div><?php endif;?>
<?php if($invalidDataErrors):?><div class="alert warn"><strong>Rows not uploaded:</strong><ul><?php foreach($invalidDataErrors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="box">
<form method="post" enctype="multipart/form-data">
<div class="grid">
<div class="g"><label>Upload Type</label><select name="upload_mode" id="upload_mode" required><option value="normal" <?=$uploadMode==='normal'?'selected':''?>>Normal Inventory</option><?php if($canUploadOwnerInventory):?><option value="imans_hustle" <?=$uploadMode==='imans_hustle'?'selected':''?>>Iman's Hustle</option><option value="iman_inventory" <?=$uploadMode==='iman_inventory'?'selected':''?>>Iman Inventory</option><?php endif;?></select></div>
<div class="g"><label>Excel File</label><input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required></div>
</div>

<div id="normalFormat" class="format-panel">
<h3>Normal Inventory Format</h3>
<div class="help">Use the simplified 3-column format. Normal mode stores <strong>inventory_owner = NULL</strong>.</div>
<a class="template-btn" href="?download_template=normal"><i class="fas fa-file-excel"></i> Download Normal Inventory .xlsx Template</a>
<div class="format"><table><tr><th>serial_number</th><th>category</th><th>specs</th></tr><tr><td>5CG123</td><td>Laptop</td><td>HP EliteBook 840 G8 | Core i5 11th | 16GB | 512GB SSD | Intel Iris | Non-touch | Ex-Uk | AC16 | KIMATHI</td></tr></table></div>
<ul class="rules"><li>Specs order: Model | Processor | RAM | Storage | Graphics | Touch | Condition | Cargo | Branch.</li><li>Use <strong>|</strong> as the recommended separator; commas are also accepted.</li><li>Use <strong>-</strong> for optional values. Condition defaults to Ex-Uk and Cargo to NO CARGO.</li><li>Storage should include capacity and type, for example 512GB SSD or 1TB HDD.</li></ul>
<div class="monitor-note"><strong>Monitor rule:</strong> rows whose category is Monitor/Monitors are skipped and must be uploaded with Monitor Bulk Upload.</div>
</div>

<?php if($canUploadOwnerInventory):?>
<div id="hustleFormat" class="format-panel hidden">
<h3>Iman's Hustle — Upload Exactly As Existing Sheet</h3>
<div class="help">Do not rearrange the workbook. The system reads the existing columns with the new Status column appended at the end.</div>
<a class="template-btn" href="?download_template=imans_hustle"><i class="fas fa-file-excel"></i> Download Iman's Hustle .xlsx Template</a>
<div class="format"><table><tr><?php foreach(['Asset ID','MFG','Model','Form Factor','CPU','Ram','Storage','Serial','Grade','B.P','S.P','PROFIT','NOTES','Status'] as $h):?><th><?=htmlspecialchars($h)?></th><?php endforeach;?></tr><tr><td>IH-001</td><td>HP</td><td>EliteBook 840 G8</td><td>Laptop</td><td>Core i5 11th Gen</td><td>16GB</td><td>512GB SSD</td><td>5CG1234XYZ</td><td>A</td><td>250</td><td>350</td><td>100</td><td>-</td><td>In Stock</td></tr></table></div>
<ul class="rules"><li>Select a default device category once for the batch.</li><li>If Form Factor matches an existing system category, that category is used automatically.</li><li>Select a default branch/location once for the batch.</li><li>The uploaded computer devices are automatically assigned to Iman's Hustle.</li><li><strong>Status:</strong> use Sold or In Stock. Blank automatically becomes In Stock.</li><li>If Status is <strong>Sold</strong>, S.P is required and is stored as the device selling price. No sale details are inserted.</li></ul>
<div class="monitor-note"><strong>Monitor rule:</strong> monitor/display/screen rows are skipped. Rows with a model but no CPU, RAM and Storage are also treated as monitors and are not inserted into devices.</div>
</div>

<div id="inventoryFormat" class="format-panel hidden">
<h3>Iman Inventory — Upload Exactly As Existing Sheet</h3>
<div class="help">Do not rearrange the workbook. The system reads the existing columns with the new Status column appended at the end.</div>
<a class="template-btn" href="?download_template=iman_inventory"><i class="fas fa-file-excel"></i> Download Iman Inventory .xlsx Template</a>
<div class="format"><table><tr><?php foreach(['Asset ID','Symetic','$','BP','SP','PROFIT','MFG','Model','CPU','RAM','Storage','Serial #','Grade','Touch Screen','Webcam','Notes','LOCATION','Status'] as $h):?><th><?=htmlspecialchars($h)?></th><?php endforeach;?></tr><tr><td>II-001</td><td>250</td><td>350</td><td>32000</td><td>45000</td><td>13000</td><td>HP</td><td>EliteBook 840 G8</td><td>Core i5 11th Gen</td><td>16GB</td><td>512GB SSD</td><td>5CG1234XYZ</td><td>A</td><td>Non-touch</td><td>Yes</td><td>-</td><td>WAREHOUSE</td><td>In Stock</td></tr></table></div>
<ul class="rules"><li><strong>Symetic</strong> = buying price in dollars and <strong>$</strong> = selling price in dollars.</li><li>LOCATION can be WAREHOUSE, MOI or KIMATHI.</li><li>Select a default category because this workbook has no category column.</li><li>The uploaded computer devices are automatically assigned to Iman Inventory.</li><li><strong>Status:</strong> use Sold or In Stock. Blank automatically becomes In Stock.</li><li>If Status is <strong>Sold</strong>, SP is required and is stored as the device selling price. Only the devices table is inserted; no sale details are created.</li></ul>
<div class="monitor-note"><strong>Monitor rule:</strong> monitor rows are skipped. A row with model/serial but no CPU, RAM and Storage is treated as a monitor and must be uploaded through Monitor Bulk Upload.</div>
</div>
<?php endif;?>
<button class="btn" type="submit" style="margin-top:1.25rem"><i class="fas fa-upload"></i> Upload and Process</button>
</form></section>
</main>
<script>
const mode=document.getElementById('upload_mode');
function updateMode(){
    const v=mode.value;
    document.querySelectorAll('.format-panel').forEach(x=>x.classList.add('hidden'));
    const targetId=v==='normal'?'normalFormat':(v==='imans_hustle'?'hustleFormat':'inventoryFormat');
    const target=document.getElementById(targetId);
    if(target) target.classList.remove('hidden');
}
mode.addEventListener('change',updateMode);updateMode();
</script>
<?php require_once '../includes/footer.php'; ?>
</body></html>
