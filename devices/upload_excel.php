<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth_check.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!in_array($_SESSION['role'], ['super_admin', 'inventory_admin', 'manager'], true)) {
    die('Access denied! Only administrators can upload devices.');
}

$added_by = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$success = '';
$error = '';
$skippedSerials = [];
$invalidDataErrors = [];

$stmt = $conn->prepare('SELECT branch FROM users WHERE id = ?');
$stmt->execute([$added_by]);
$user_branch = (string)($stmt->fetchColumn() ?: 'KIMATHI');

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
function manufacturerFromModel($model, $provided = null) {
    $m = strtoupper(trim((string)$model));
    if (preg_match('/\bHP\b|HEWLETT[ -]?PACKARD/', $m)) return 'HP';
    if (preg_match('/\bDELL\b/', $m)) return 'Dell';
    if (preg_match('/\bLENOVO\b|THINKPAD|THINKCENTRE|IDEAPAD/', $m)) return 'Lenovo';
    if (preg_match('/\bTOSHIBA\b|DYNABOOK/', $m)) return 'Toshiba';
    if (preg_match('/\bAPPLE\b|MACBOOK|IMAC/', $m)) return 'Apple';
    if (preg_match('/\bASUS\b/', $m)) return 'ASUS';
    if (preg_match('/\bACER\b/', $m)) return 'Acer';
    $provided = trim((string)$provided);
    return $provided !== '' && $provided !== '-' ? $provided : null;
}
function placeForCategory($categoryName) {
    return strtolower((string)$categoryName) === 'laptop' ? 'store' : 'display';
}
function branchFromLocation($location, $fallback, $role, $userBranch) {
    if ($role === 'manager') return $userBranch;
    $loc = strtoupper(trim((string)$location));
    if (strpos($loc, 'KIMATHI') !== false) return 'KIMATHI';
    if (strpos($loc, 'MOI') !== false) return 'MOI';
    if (strpos($loc, 'WAREHOUSE') !== false || strpos($loc, 'WARE HOUSE') !== false) return 'WAREHOUSE';
    return in_array($fallback, ['KIMATHI','MOI','WAREHOUSE'], true) ? $fallback : $userBranch;
}

$uploadMode = $_POST['upload_mode'] ?? 'normal';
if (!in_array($uploadMode, ['normal','imans_hustle','iman_inventory'], true)) $uploadMode = 'normal';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $fallbackCategory = (int)($_POST['default_category_id'] ?? 0);
    $fallbackBranch = strtoupper(trim($_POST['default_branch'] ?? $user_branch));
    if ($role === 'manager') $fallbackBranch = $user_branch;

    if ($uploadMode !== 'normal' && !$fallbackCategory) {
        $error = 'Select the default category for this owner spreadsheet before uploading.';
    } elseif (!in_array(strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION)), ['xlsx','xls','csv'], true)) {
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

            $fallbackCat = null;
            foreach ($categories as $c) if ((int)$c['id'] === $fallbackCategory) $fallbackCat = $c;

            $insert = $conn->prepare("INSERT INTO devices
                (serial_number, category_id, model_name, processor, graphics, ram, storage_type, storage_capacity, touch, status,
                 inventory_owner, device_condition, added_by, branch, cargo_number, place, asset_id, manufacturer, form_factor, grade,
                 buying_price, price, owner_profit, owner_notes, symetic, dollar_value, webcam, owner_location)
                VALUES
                (:serial_number,:category_id,:model_name,:processor,:graphics,:ram,:storage_type,:storage_capacity,:touch,'In Stock',
                 :inventory_owner,'Ex-Uk',:added_by,:branch,'NO CARGO',:place,:asset_id,:manufacturer,:form_factor,:grade,
                 :buying_price,:price,:owner_profit,:owner_notes,:symetic,:dollar_value,:webcam,:owner_location)");
            $dup = $conn->prepare('SELECT 1 FROM devices WHERE serial_number = ? LIMIT 1');
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Bulk upload', ?)");

            $added = 0; $duplicates = 0; $invalid = 0;
            foreach ($rows as $ri=>$row) {
                $rowNumber = $ri + 2;
                if (!array_filter($row, fn($v)=>trim((string)$v) !== '')) continue;

                $d = [
                    'asset_id'=>null,'manufacturer'=>null,'form_factor'=>null,'grade'=>null,'buying_price'=>null,'price'=>null,
                    'owner_profit'=>null,'owner_notes'=>null,'symetic'=>null,'dollar_value'=>null,'webcam'=>null,'owner_location'=>null,
                    'graphics'=>'None','touch'=>'N/A','inventory_owner'=>null
                ];
                $rowErrors = [];

                if ($uploadMode === 'normal') {
                    $serial = trim((string)($row[$normalIndex['serial_number']] ?? ''));
                    $catName = trim((string)($row[$normalIndex['category']] ?? ''));
                    $specs = trim((string)($row[$normalIndex['specs']] ?? ''));
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
                    $branch = branchFromLocation($parts[8]??'', $parts[8]??$user_branch, $role, $user_branch);
                    $d['category_id']=$cat['id']??0; $d['category_name']=$cat['name']??$catName;
                    $d['device_condition']=$deviceCondition; $d['cargo_number']=$cargo;
                } elseif ($uploadMode === 'imans_hustle') {
                    // Exact Iman Hustle sheet order:
                    // Asset ID | MFG | Model | Form Factor | CPU | Ram | HDD | Serial | Grade | B.P | S.P | PROFIT | NOTES
                    $d['asset_id']=trim((string)($row[0]??'')) ?: null;
                    $providedMfg=trim((string)($row[1]??''));
                    $d['model_name']=trim((string)($row[2]??''));
                    $d['manufacturer']=manufacturerFromModel($d['model_name'], $providedMfg);
                    $d['form_factor']=trim((string)($row[3]??'')) ?: null;
                    $d['processor']=trim((string)($row[4]??''));
                    $d['ram']=ramValue($row[5]??'');
                    [$d['storage_type'],$d['storage_capacity']]=storageValue($row[6]??'', 'HDD');
                    $serial=trim((string)($row[7]??''));
                    $d['grade']=trim((string)($row[8]??'')) ?: null;
                    $d['buying_price']=numericValue($row[9]??'');
                    $d['price']=numericValue($row[10]??'');
                    $d['owner_profit']=numericValue($row[11]??'');
                    if ($d['owner_profit']===null && $d['buying_price']!==null && $d['price']!==null) $d['owner_profit']=$d['price']-$d['buying_price'];
                    $d['owner_notes']=trim((string)($row[12]??'')) ?: null;
                    $d['inventory_owner']='imans_hustle';
                    $cat = $fallbackCat;
                    $ffKey = strtolower(trim((string)$d['form_factor']));
                    if ($ffKey && isset($catMap[$ffKey])) $cat = $catMap[$ffKey];
                    $d['category_id']=(int)($cat['id']??0); $d['category_name']=$cat['name']??'';
                    $d['device_condition']='Ex-Uk'; $d['cargo_number']='NO CARGO';
                    $branch=branchFromLocation('', $fallbackBranch, $role, $user_branch);
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
                    $providedMfg=trim((string)($row[6]??''));
                    $d['model_name']=trim((string)($row[7]??''));
                    $d['manufacturer']=manufacturerFromModel($d['model_name'], $providedMfg);
                    $d['processor']=trim((string)($row[8]??''));
                    $d['ram']=ramValue($row[9]??'');
                    [$d['storage_type'],$d['storage_capacity']]=storageValue($row[10]??'', 'HDD');
                    $serial=trim((string)($row[11]??''));
                    $d['grade']=trim((string)($row[12]??'')) ?: null;
                    $d['touch']=touchValue($row[13]??'');
                    $d['webcam']=trim((string)($row[14]??'')) ?: null;
                    $d['owner_notes']=trim((string)($row[15]??'')) ?: null;
                    $d['owner_location']=trim((string)($row[16]??'')) ?: null;
                    $d['inventory_owner']='iman_inventory';
                    $d['category_id']=(int)($fallbackCat['id']??0); $d['category_name']=$fallbackCat['name']??'';
                    $d['device_condition']='Ex-Uk'; $d['cargo_number']='NO CARGO';
                    $branch=branchFromLocation($d['owner_location'], $fallbackBranch, $role, $user_branch);
                }

                if ($serial==='') $rowErrors[]='Serial number is missing';
                if (empty($d['category_id'])) $rowErrors[]='Category could not be determined';
                if (trim((string)$d['model_name'])==='') $rowErrors[]='Model is missing';
                if (trim((string)$d['processor'])==='') $rowErrors[]='CPU/processor is missing';
                if ((int)$d['ram'] < 1) $rowErrors[]='RAM is missing or invalid';
                if ((int)$d['storage_capacity'] < 1) $rowErrors[]='HDD/storage is missing or invalid';
                if ($rowErrors) { $invalid++; $invalidDataErrors[]="Row $rowNumber: ".implode('; ',$rowErrors); continue; }

                $dup->execute([$serial]);
                if ($dup->fetchColumn()) { $duplicates++; $skippedSerials[]=$serial; continue; }

                $insert->execute([
                    'serial_number'=>$serial,'category_id'=>$d['category_id'],'model_name'=>$d['model_name'],'processor'=>$d['processor'],
                    'graphics'=>$d['graphics'],'ram'=>$d['ram'],'storage_type'=>$d['storage_type'],'storage_capacity'=>$d['storage_capacity'],
                    'touch'=>$d['touch'],'inventory_owner'=>$d['inventory_owner'],'added_by'=>$added_by,'branch'=>$branch,
                    'place'=>placeForCategory($d['category_name']),'asset_id'=>$d['asset_id'],'manufacturer'=>$d['manufacturer'],
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
            $success="$added device(s) added successfully.";
            if($duplicates) $success.=" $duplicates duplicate serial(s) skipped.";
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
:root{--p:#1a4b2a;--bg:#f3f4f6;--b:#e5e7eb;--t:#1f2937;--m:#6b7280}*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:var(--bg);color:var(--t)}.main{margin-left:260px;padding:2rem;min-height:100vh}.box{background:#fff;border:1px solid var(--b);border-radius:14px;padding:1.4rem;margin-bottom:1rem}.box h1,.box h2{margin-top:0}.alert{padding:1rem;border-radius:9px;margin-bottom:1rem}.ok{background:#ecfdf5;color:#065f46}.err{background:#fef2f2;color:#991b1b}.warn{background:#fffbeb;color:#92400e}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:1rem}.g{display:flex;flex-direction:column;gap:.35rem}.g label{font-weight:650;font-size:.85rem}.g select,.g input{padding:.7rem;border:1px solid #d1d5db;border-radius:8px}.btn{width:100%;padding:.8rem;border:0;border-radius:8px;background:var(--p);color:#fff;font-weight:700;cursor:pointer}.format{overflow:auto;border:1px solid var(--b);border-radius:9px;margin-top:1rem}.format table{border-collapse:collapse;width:100%;min-width:800px}.format th,.format td{padding:.65rem;border-right:1px solid var(--b);white-space:nowrap}.format th{background:#eabf30;color:#111;font-size:.78rem}.help{font-size:.85rem;color:var(--m);line-height:1.55}.hidden{display:none!important}@media(max-width:1200px){.main{margin-left:0;padding:5rem 1rem 1rem}}
</style></head><body>
<?php include '../includes/sidebar.php'; ?>
<main class="main">
<section class="box"><h1><i class="fas fa-file-excel"></i> Bulk Upload Devices</h1><div class="help">Choose the inventory type first. The uploader then reads that spreadsheet in its existing layout; you do not need to rearrange Iman Hustle or Iman Inventory files.</div></section>
<?php if($success):?><div class="alert ok"><?=htmlspecialchars($success)?></div><?php endif;?>
<?php if($error):?><div class="alert err"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($skippedSerials):?><div class="alert warn"><strong>Duplicates skipped:</strong> <?=htmlspecialchars(implode(', ',array_unique($skippedSerials)))?></div><?php endif;?>
<?php if($invalidDataErrors):?><div class="alert warn"><strong>Rows not uploaded:</strong><ul><?php foreach($invalidDataErrors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="box">
<form method="post" enctype="multipart/form-data">
<div class="grid">
<div class="g"><label>Upload Type</label><select name="upload_mode" id="upload_mode" required><option value="normal" <?=$uploadMode==='normal'?'selected':''?>>Normal Inventory</option><option value="imans_hustle" <?=$uploadMode==='imans_hustle'?'selected':''?>>Iman's Hustle</option><option value="iman_inventory" <?=$uploadMode==='iman_inventory'?'selected':''?>>Iman Inventory</option></select></div>
<div class="g owner-option"><label>Default Category</label><select name="default_category_id"><option value="">-- Select Category --</option><?php foreach($categories as $c):?><option value="<?=$c['id']?>" <?=((int)($_POST['default_category_id']??0)===(int)$c['id'])?'selected':''?>><?=htmlspecialchars($c['category_name'])?></option><?php endforeach;?></select></div>
<?php if($role==='manager'):?><input type="hidden" name="default_branch" value="<?=htmlspecialchars($user_branch)?>"><?php else:?><div class="g owner-option"><label>Default Branch</label><select name="default_branch"><option value="KIMATHI" <?=($_POST['default_branch']??$user_branch)==='KIMATHI'?'selected':''?>>KIMATHI</option><option value="MOI" <?=($_POST['default_branch']??$user_branch)==='MOI'?'selected':''?>>MOI</option><option value="WAREHOUSE" <?=($_POST['default_branch']??$user_branch)==='WAREHOUSE'?'selected':''?>>WAREHOUSE</option></select></div><?php endif;?>
<div class="g"><label>Excel File</label><input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required></div>
</div>

<div id="normalFormat" class="format-panel"><h3>Normal Inventory Format</h3><div class="help">Uses the existing simplified layout. Normal mode stores <strong>inventory_owner = NULL</strong>.</div><div class="format"><table><tr><th>serial_number</th><th>category</th><th>specs</th></tr><tr><td>5CG123</td><td>Laptop</td><td>HP EliteBook 840 G8 | Core i5 11th | 16GB | 512GB SSD | Intel Iris | Non-touch | Ex-Uk | AC16 | KIMATHI</td></tr></table></div></div>
<div id="hustleFormat" class="format-panel hidden"><h3>Iman's Hustle — Upload Exactly As Existing Sheet</h3><div class="format"><table><tr><?php foreach(['Asset ID','MFG','Model','Form Factor','CPU','Ram','HDD','Serial','Grade','B.P','S.P','PROFIT','NOTES'] as $h):?><th><?=htmlspecialchars($h)?></th><?php endforeach;?></tr></table></div><p class="help">No category or branch column is required in the Excel file. Select the defaults above once for the batch. If Form Factor matches a system category, that category is used automatically.</p></div>
<div id="inventoryFormat" class="format-panel hidden"><h3>Iman Inventory — Upload Exactly As Existing Sheet</h3><div class="format"><table><tr><?php foreach(['Asset ID','Symetic','$','BP','SP','PROFIT','MFG','Model','CPU','RAM','HDD','Serial #','Grade','Touch Screen','Webcam','Notes','LOCATION'] as $h):?><th><?=htmlspecialchars($h)?></th><?php endforeach;?></tr></table></div><p class="help">The LOCATION value is preserved. If it contains KIMATHI, MOI or WAREHOUSE it also determines the branch; otherwise the selected default branch is used.</p></div>
<button class="btn" type="submit" style="margin-top:1.25rem"><i class="fas fa-upload"></i> Upload and Process</button>
</form></section>
</main>
<script>
const mode=document.getElementById('upload_mode');
function updateMode(){const v=mode.value;document.querySelectorAll('.format-panel').forEach(x=>x.classList.add('hidden'));document.getElementById(v==='normal'?'normalFormat':(v==='imans_hustle'?'hustleFormat':'inventoryFormat')).classList.remove('hidden');document.querySelectorAll('.owner-option').forEach(x=>x.classList.toggle('hidden',v==='normal'));}
mode.addEventListener('change',updateMode);updateMode();
</script>
<?php require_once '../includes/footer.php'; ?>
</body></html>
