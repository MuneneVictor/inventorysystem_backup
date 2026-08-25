<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once "../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'], true)) {
    die("ACCESS DENIED.");
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];

$stmt = $conn->prepare("SELECT branch, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$user_branch = $current_user['branch'] ?? null;
$user_email = strtolower(trim((string)($current_user['email'] ?? '')));

if ($user_role !== 'super_admin' && !$user_branch) {
    die("Your account has no branch assigned.");
}

/**
 * Inventory admins allowed to upload Iman-owned monitor sheets.
 * Add more email addresses here when needed.
 */
$ownerUploadAllowedEmails = [
    'stephanie@mombasacomputers.co.ke',
];

$canUploadOwnerInventory =
    in_array($user_role, ['super_admin', 'manager'], true) ||
    ($user_role === 'inventory_admin' && in_array($user_email, $ownerUploadAllowedEmails, true));

$error = '';
$success = '';
$skippedSerials = [];
$rowErrors = [];

function monCleanHeader($value): string {
    return strtolower(trim(preg_replace('/\s+/', ' ', (string)$value)));
}
function monNumber($value): ?float {
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '-') return null;
    $raw = str_ireplace(['KES','KSH','USD'], '', $raw);
    $raw = str_replace([',','$'], '', $raw);
    $raw = trim($raw);
    return is_numeric($raw) ? (float)$raw : null;
}
function monStatus($value): ?string {
    $raw = strtolower(trim((string)$value));

    // Same behavior as the device bulk uploader:
    // blank or "-" status automatically means In Stock.
    if ($raw === '' || $raw === '-') return 'In Stock';

    $key = preg_replace('/[^a-z]/', '', $raw);

    if ($key === 'sold') return 'Sold';
    if (in_array($key, ['instock', 'stock', 'available'], true)) return 'In Stock';

    return null;
}

function monManufacturer($value, string $model): ?string {
    $value = trim((string)$value);
    if ($value !== '' && $value !== '-') return $value;
    $m = strtolower($model);
    foreach ([
        'hp'=>'HP','dell'=>'Dell','lenovo'=>'Lenovo','acer'=>'Acer','asus'=>'ASUS',
        'samsung'=>'Samsung','lg'=>'LG','viewsonic'=>'ViewSonic','aoc'=>'AOC','benq'=>'BenQ'
    ] as $needle=>$label) {
        if (strpos($m,$needle)!==false) return $label;
    }
    return null;
}
function monOwnerBranch($location, string $userBranch): array {
    $loc = strtoupper(trim((string)$location));
    if ($loc === '' || $loc === '-') return [$userBranch, null];
    if (strpos($loc,'WAREHOUSE')!==false || strpos($loc,'WARE HOUSE')!==false) return ['WAREHOUSE','WAREHOUSE'];
    if (strpos($loc,'KIMATHI')!==false) return ['KIMATHI','KIMATHI'];
    if (strpos($loc,'MOI')!==false) return ['MOI','MOI'];
    return [$userBranch, trim((string)$location) ?: null];
}

$uploadMode = $_POST['upload_mode'] ?? 'normal';
$allowedModes = ['normal'];
if ($canUploadOwnerInventory) {
    $allowedModes[] = 'imans_hustle';
    $allowedModes[] = 'iman_inventory';
}
if (!in_array($uploadMode, $allowedModes, true)) $uploadMode = 'normal';

// Real XLSX templates for each mode.
$templateMode = $_GET['download_template'] ?? '';
if ($templateMode !== '' && !in_array($templateMode, $allowedModes, true)) {
    http_response_code(403);
    exit('You do not have permission to download this template.');
}
if (in_array($templateMode, $allowedModes, true)) {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    if ($templateMode === 'normal') {
        $sheet->setTitle('Normal Monitors');
        $headers = ['serial_number','model_name','size_inches','status'];
        $sample = ['SN001','Dell P2419H',24,'In Stock'];
        $filename = 'monitor_normal_upload_template.xlsx';
    } elseif ($templateMode === 'imans_hustle') {
        $sheet->setTitle("Iman Hustle Monitors");
        $headers = ['Asset ID','MFG','Model','Form Factor','Size','Serial','Grade','B.P','S.P','PROFIT','NOTES','Status'];
        $sample = ['IH-M001','Dell','P2419H','Monitor','24','MON001','A','5000','8500','3500','','In Stock'];
        $filename = 'iman_hustle_monitor_upload_template.xlsx';
    } else {
        $sheet->setTitle('Iman Inventory Monitors');
        $headers = ['Asset ID','Symetic','$','BP','SP','PROFIT','MFG','Model','Size','Serial #','Grade','Notes','LOCATION','Status'];
        $sample = ['II-M001','40','65','5200','8500','3300','Dell','P2419H','24','MON001','A','','WAREHOUSE','In Stock'];
        $filename = 'iman_inventory_monitor_upload_template.xlsx';
    }
    foreach ($headers as $i=>$h) {
        $col=Coordinate::stringFromColumnIndex($i+1);
        $sheet->setCellValue($col.'1',$h);
        $sheet->setCellValue($col.'2',$sample[$i] ?? '');
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $last=Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle('A1:'.$last.'1')->applyFromArray([
        'font'=>['bold'=>true,'color'=>['rgb'=>'111827']],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'EABF30']],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);
    $sheet->freezePane('A2');
    $sheet->setAutoFilter('A1:'.$last.'1');
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: max-age=0, no-store, no-cache, must-revalidate');
    (new Xlsx($book))->save('php://output');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid file.';
    } else {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx','xls','csv'], true)) {
            $error = 'Invalid file type. Please upload .xlsx, .xls or .csv.';
        }
    }

    // Normal monitor upload keeps explicit branch selection for super_admin/inventory_admin.
    $batchBranch = $user_branch;
    if (!$error && $uploadMode === 'normal' && in_array($user_role, ['super_admin','inventory_admin'], true)) {
        $batchBranch = strtoupper(trim((string)($_POST['branch'] ?? '')));
        if (!in_array($batchBranch, ['KIMATHI','MOI'], true)) $error = 'Please select a valid branch.';
    }

    if (!$error) {
        try {
            $book = IOFactory::load($_FILES['file']['tmp_name']);
            $rows = $book->getActiveSheet()->toArray(null,true,true,false);
            if (!$rows) throw new Exception('The uploaded spreadsheet is empty.');
            $headers = array_map('monCleanHeader', array_shift($rows));

            if ($uploadMode === 'normal') {
                if ($headers !== ['serial_number','model_name','size_inches','status']) {
                    throw new Exception('Normal Monitor header must be: serial_number, model_name, size_inches, status');
                }
            } elseif ($uploadMode === 'imans_hustle') {
                if (count($headers) < 12) throw new Exception("Iman's Hustle monitor sheet must contain 12 columns from Asset ID through Status.");
            } else {
                if (count($headers) < 14) throw new Exception('Iman Inventory monitor sheet must contain 14 columns from Asset ID through Status.');
            }

            $insert = $conn->prepare("INSERT INTO monitors
                (serial_number,model_name,size_inches,status,branch,added_by,date_added,inventory_owner,
                 asset_id,manufacturer,form_factor,grade,buying_price,price,owner_profit,owner_notes,
                 symetic,dollar_value,owner_location,selling_price,sold_at)
                VALUES (?,?,?,?,?,?,NOW(),?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $check = $conn->prepare('SELECT serial_number FROM monitors WHERE serial_number=? LIMIT 1');
            $count=0;$duplicates=0;$invalid=0;
            foreach ($rows as $i=>$row) {
                $rowNo=$i+2;
                if (!array_filter($row,fn($v)=>trim((string)$v)!=='')) continue;

                $serial='';$model='';$size='';$owner=null;$branch=$batchBranch;
                $status='In Stock';$statusRaw='';$actualSellingPrice=null;$soldAt=null;
                $asset=null;$mfg=null;$form=null;$grade=null;$bp=null;$sp=null;$profit=null;$notes=null;$symetic=null;$dollar=null;$ownerLocation=null;

                if ($uploadMode==='normal') {
                    $serial=trim((string)($row[0]??''));
                    $model=trim((string)($row[1]??''));
                    $size=trim((string)($row[2]??''));
                    $statusRaw=trim((string)($row[3]??''));
                } elseif ($uploadMode==='imans_hustle') {
                    // Asset ID | MFG | Model | Form Factor | Size | Serial | Grade | B.P | S.P | PROFIT | NOTES
                    $asset=trim((string)($row[0]??'')) ?: null;
                    $mfg=trim((string)($row[1]??'')) ?: null;
                    $model=trim((string)($row[2]??''));
                    $form=trim((string)($row[3]??'')) ?: 'Monitor';
                    $size=trim((string)($row[4]??''));
                    $serial=trim((string)($row[5]??''));
                    $grade=trim((string)($row[6]??'')) ?: null;
                    $bp=monNumber($row[7]??'');
                    $sp=monNumber($row[8]??'');
                    $profit=monNumber($row[9]??'');
                    if ($profit===null && $bp!==null && $sp!==null) $profit=$sp-$bp;
                    $notes=trim((string)($row[10]??'')) ?: null;
                    $statusRaw=trim((string)($row[11]??''));
                    $owner='imans_hustle';
                    $branch=$user_branch ?: 'KIMATHI';
                    $mfg=monManufacturer($mfg,$model);
                } else {
                    // Asset ID | Symetic | $ | BP | SP | PROFIT | MFG | Model | Size | Serial # | Grade | Notes | LOCATION
                    $asset=trim((string)($row[0]??'')) ?: null;
                    $symetic=trim((string)($row[1]??'')) ?: null;
                    $dollar=trim((string)($row[2]??'')) ?: null;
                    $bp=monNumber($row[3]??'');
                    $sp=monNumber($row[4]??'');
                    $profit=monNumber($row[5]??'');
                    if ($profit===null && $bp!==null && $sp!==null) $profit=$sp-$bp;
                    $mfg=trim((string)($row[6]??'')) ?: null;
                    $model=trim((string)($row[7]??''));
                    $size=trim((string)($row[8]??''));
                    $serial=trim((string)($row[9]??''));
                    $grade=trim((string)($row[10]??'')) ?: null;
                    $notes=trim((string)($row[11]??'')) ?: null;
                    $ownerLocation=trim((string)($row[12]??'')) ?: null;
                    $statusRaw=trim((string)($row[13]??''));
                    $owner='iman_inventory';
                    [$branch,$normalizedLocation]=monOwnerBranch($ownerLocation,$user_branch ?: 'KIMATHI');
                    if ($ownerLocation===null && $normalizedLocation!==null) $ownerLocation=$normalizedLocation;
                    $mfg=monManufacturer($mfg,$model);
                }

                $errs=[];

                $parsedStatus=monStatus($statusRaw);
                if ($parsedStatus===null) {
                    $errs[]='Status must be Sold or In Stock.';
                } else {
                    $status=$parsedStatus;
                }

                if ($status==='Sold') {
                    if ($uploadMode!=='normal') {
                        if ($sp===null || $sp<=0) {
                            $errs[]='Sold monitor requires a valid SP (selling price).';
                        } else {
                            $actualSellingPrice=$sp;
                            $soldAt=date('Y-m-d H:i:s');
                        }
                    }
                } else {
                    $actualSellingPrice=null;
                    $soldAt=null;
                }

                if ($serial==='') $errs[]='Serial number is required.';
                if ($model==='') $errs[]='Model name is required.';
                if ($size==='' || !is_numeric($size) || (float)$size<=0 || (float)$size>100) $errs[]='Size must be numeric between 1 and 100.';
                if ($serial!=='') {
                    $check->execute([$serial]);
                    if ($check->fetchColumn()) {$duplicates++;$skippedSerials[]=$serial;continue;}
                }
                if ($errs) {$invalid++;$rowErrors[]="Row $rowNo (SN: ".($serial?:'N/A').'): '.implode(' ',$errs);continue;}

                $insert->execute([
                    $serial,
                    $model,
                    (float)$size,
                    $status,
                    $branch,
                    $user_id,
                    $owner,
                    $asset,
                    $mfg,
                    $form,
                    $grade,
                    $bp,
                    $sp,
                    $profit,
                    $notes,
                    $symetic,
                    $dollar,
                    $ownerLocation,
                    $actualSellingPrice,
                    $soldAt
                ]);
                $count++;
            }
            if ($count>0) {
                $log=$conn->prepare("INSERT INTO activity_logs (user_id,action,details) VALUES (?,'Bulk upload monitors',?)");
                $log->execute([$user_id,"Uploaded $count monitors via $uploadMode"]);
            }
            $success="$count monitor(s) uploaded successfully.";
            if ($duplicates) $success.=" $duplicates duplicate serial(s) skipped.";
            if ($invalid) $success.=" $invalid invalid row(s) skipped.";
        } catch (Throwable $e) {
            $error='File processing error: '.$e->getMessage();
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Bulk Upload Monitors | Mombasa Computers</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"><style>
:root{--p:#1a4b2a;--bg:#f3f4f6;--b:#e5e7eb;--t:#1f2937;--m:#6b7280}*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:var(--bg);color:var(--t)}.main{margin-left:260px;padding:2rem;min-height:100vh}.box{background:#fff;border:1px solid var(--b);border-radius:14px;padding:1.4rem;margin-bottom:1rem}.alert{padding:1rem;border-radius:9px;margin-bottom:1rem}.ok{background:#ecfdf5;color:#065f46}.err{background:#fef2f2;color:#991b1b}.warn{background:#fffbeb;color:#92400e}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:1rem}.g{display:flex;flex-direction:column;gap:.35rem}.g label{font-weight:650;font-size:.85rem}.g select,.g input{padding:.7rem;border:1px solid #d1d5db;border-radius:8px}.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.7rem .9rem;border:0;border-radius:8px;background:var(--p);color:#fff;font-weight:700;cursor:pointer;text-decoration:none}.full{width:100%;justify-content:center}.panel{margin-top:1.2rem;padding:1rem;border:1px solid var(--b);border-radius:10px}.hidden{display:none}.format{overflow:auto;border:1px solid var(--b);border-radius:8px;margin:.8rem 0}.format table{border-collapse:collapse;min-width:900px;width:100%}.format th,.format td{padding:.58rem;border-bottom:1px solid var(--b);white-space:nowrap;font-size:.78rem}.format th{background:#eabf30;color:#111827}.help{font-size:.83rem;line-height:1.55;color:var(--m)}@media(max-width:1200px){.main{margin-left:0;padding:5rem 1rem 1rem}}
</style></head><body><?php include "../includes/sidebar.php";?><main class="main"><section class="box"><h1><i class="fas fa-desktop"></i> Bulk Upload Monitors</h1><div class="help">Choose the spreadsheet type. Owner spreadsheets keep their existing business layout but omit CPU, RAM and Storage because monitors do not use those fields.</div></section>
<?php if($success):?><div class="alert ok"><?=htmlspecialchars($success)?></div><?php endif;?><?php if($error):?><div class="alert err"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($skippedSerials):?><div class="alert warn"><strong>Duplicate serials:</strong> <?=htmlspecialchars(implode(', ',array_unique($skippedSerials)))?></div><?php endif;?>
<?php if($rowErrors):?><div class="alert warn"><strong>Rows not uploaded:</strong><ul><?php foreach($rowErrors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="box"><form method="post" enctype="multipart/form-data"><div class="grid"><div class="g"><label>Upload Type</label><select name="upload_mode" id="upload_mode"><option value="normal" <?=$uploadMode==='normal'?'selected':''?>>Normal Monitors</option><?php if($canUploadOwnerInventory):?><option value="imans_hustle" <?=$uploadMode==='imans_hustle'?'selected':''?>>Iman's Hustle</option><option value="iman_inventory" <?=$uploadMode==='iman_inventory'?'selected':''?>>Iman Inventory</option><?php endif;?></select></div>
<div class="g" id="normalBranch"><label>Branch</label><?php if(in_array($user_role,['super_admin','inventory_admin'],true)):?><select name="branch"><option value="">-- Select Branch --</option><option>KIMATHI</option><option>MOI</option></select><?php else:?><input value="<?=htmlspecialchars($user_branch)?>" disabled><?php endif;?></div><div class="g"><label>Excel File</label><input type="file" name="file" accept=".xlsx,.xls,.csv" required></div></div>
<div id="normalPanel" class="panel"><div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap"><strong>Normal Monitor Format</strong><a class="btn" href="?download_template=normal"><i class="fas fa-download"></i> Download .xlsx</a></div><div class="format"><table><tr><th>serial_number</th><th>model_name</th><th>size_inches</th><th>status</th></tr><tr><td>SN001</td><td>Dell P2419H</td><td>24</td><td>In Stock</td></tr></table></div></div>
<?php if($canUploadOwnerInventory):?><div id="hustlePanel" class="panel hidden"><div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap"><strong>Iman's Hustle Monitor Format</strong><a class="btn" href="?download_template=imans_hustle"><i class="fas fa-download"></i> Download .xlsx</a></div><div class="format"><table><tr><?php foreach(['Asset ID','MFG','Model','Form Factor','Size','Serial','Grade','B.P','S.P','PROFIT','NOTES','Status'] as $h):?><th><?=$h?></th><?php endforeach;?></tr></table></div><p class="help">No Storage column is used. Status may be Sold or In Stock. If Status is blank, it automatically becomes In Stock. Sold rows use S.P as the actual selling price; In Stock rows remain available. These rows are assigned to Iman's Hustle. The logged-in user's assigned branch is used.</p></div>
<div id="inventoryPanel" class="panel hidden"><div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap"><strong>Iman Inventory Monitor Format</strong><a class="btn" href="?download_template=iman_inventory"><i class="fas fa-download"></i> Download .xlsx</a></div><div class="format"><table><tr><?php foreach(['Asset ID','Symetic','$','BP','SP','PROFIT','MFG','Model','Size','Serial #','Grade','Notes','LOCATION','Status'] as $h):?><th><?=$h?></th><?php endforeach;?></tr></table></div><p class="help">LOCATION may be WAREHOUSE, MOI or KIMATHI. Blank LOCATION uses your assigned branch. Status may be Sold or In Stock. If Status is blank, it automatically becomes In Stock. Sold rows use SP as the actual selling price; In Stock rows remain available. No Storage column is used.</p></div><?php endif;?><button class="btn full" style="margin-top:1rem"><i class="fas fa-upload"></i> Upload and Process</button></form></section></main>
<script>const mode=document.getElementById('upload_mode');function changeMode(){const v=mode.value;['normalPanel','hustlePanel','inventoryPanel'].forEach(id=>{const e=document.getElementById(id);if(e)e.classList.add('hidden')});const id=v==='normal'?'normalPanel':(v==='imans_hustle'?'hustlePanel':'inventoryPanel');const target=document.getElementById(id);if(target)target.classList.remove('hidden');document.getElementById('normalBranch').style.display=v==='normal'?'flex':'none';}mode.addEventListener('change',changeMode);changeMode();</script><?php require_once "../includes/footer.php";?></body></html>
