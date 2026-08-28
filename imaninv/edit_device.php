<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/owner_inventory_access.php';

$access=requireOwnerInventoryAccess($conn);
$user_id=(int)($access['user_id']??0);

function safe($v):string{return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');}
function shown($v,$fallback='-'):string{$v=trim((string)($v??''));return $v===''?$fallback:$v;}
function textValue($v){$v=trim((string)$v);return($v===''||$v==='-')?null:$v;}
function numValue($v){
    $v=trim((string)$v);
    if($v===''||$v==='-')return null;
    $v=str_ireplace(['KES','KSH','USD'],'',$v);
    $v=str_replace([',','$'],'',$v);
    if(!is_numeric(trim($v)))throw new Exception('A price field contains an invalid value: '.$v.'. Enter numbers only.');
    $n=(float)trim($v);
    if($n<0)throw new Exception('Price values cannot be negative.');
    return $n;
}
function dateValue($v){
    $v=trim((string)$v);
    if($v==='')return null;
    $t=strtotime($v);
    if($t===false)throw new Exception('The Sold At date/time is invalid.');
    return date('Y-m-d H:i:s',$t);
}

$id=(int)($_GET['id']??0);
if($id<=0){http_response_code(400);die('Unable to open this item because the selected inventory ID is missing or invalid.');}

/*
 * The owner inventory has existed in more than one column format.
 * Map the user-facing fields to whichever real columns are present in this table.
 */
$fieldAliases=[
    'item_type' => ['item_type','type'],
    'asset_id' => ['asset_id','asset','asset_no','asset_number'],
    'buying_usd' => ['buying_usd','symetic','buy_usd'],
    'selling_usd' => ['selling_usd','dollar_value','sell_usd'],
    'buying_price' => ['buying_price','bp','b_p'],
    'planned_selling_price' => ['planned_selling_price','price','sp','planned_price'],
    'manufacturer' => ['manufacturer','mfg','make'],
    'model_name' => ['model_name','model'],
    'processor' => ['processor','cpu'],
    'ram' => ['ram','memory'],
    'storage' => ['storage','storage_details'],
    'serial_number' => ['serial_number','serial','serial_no','serialnumber'],
    'grade' => ['grade'],
    'touch_screen' => ['touch_screen','touch'],
    'webcam' => ['webcam','camera'],
    'location' => ['location','owner_location','branch'],
    'status' => ['status'],
    'sales_person' => ['sales_person','salesperson','sold_by_name'],
    'selling_price' => ['selling_price','actual_selling_price','sale_price'],
    'payment_status' => ['payment_status','payment'],
    'sold_at' => ['sold_at','date_sold'],
    'notes' => ['notes','owner_notes']
];

$colStmt=$conn->query("SHOW COLUMNS FROM `iman_inventory_items`");
$availableColumns=[];
foreach($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col){
    $availableColumns[strtolower((string)$col['Field'])]=(string)$col['Field'];
}

function actualColumn(array $availableColumns,array $aliases):?string{
    foreach($aliases as $alias){
        $key=strtolower($alias);
        if(isset($availableColumns[$key]))return $availableColumns[$key];
    }
    return null;
}

$columnMap=[];
foreach($fieldAliases as $canonical=>$aliases){
    $columnMap[$canonical]=actualColumn($availableColumns,$aliases);
}

$stmt=$conn->prepare("SELECT * FROM `iman_inventory_items` WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$item=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$item){
    http_response_code(404);
    die("The selected Iman Inventory item could not be found. It may have been removed or the link may be outdated.");
}

function canonicalView(array $item,array $columnMap):array{
    $view=[];
    foreach($columnMap as $canonical=>$actual){
        $view[$canonical]=$actual!==null?($item[$actual]??null):null;
    }
    return $view;
}

$view=canonicalView($item,$columnMap);
$original=$item;
$error='';
$success='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $updates=[];
        $params=[];
        $changes=[];

        foreach($fieldAliases as $canonical=>$aliases){
            $actual=$columnMap[$canonical]??null;
            if($actual===null){
                // This database does not have this particular field; leave it untouched.
                continue;
            }

            $raw=$_POST[$canonical]??'';

            if(in_array($canonical,['buying_usd','selling_usd','buying_price','planned_selling_price','selling_price'],true)){
                $value=numValue($raw);
            }elseif($canonical==='sold_at'){
                $value=dateValue($raw);
            }elseif($canonical==='item_type'){
                $value=in_array($raw,['Device','Monitor'],true)?$raw:null;
            }elseif($canonical==='location'){
                $value=in_array(strtoupper(trim((string)$raw)),['KIMATHI','MOI','WAREHOUSE'],true)?strtoupper(trim((string)$raw)):null;
            }elseif($canonical==='status'){
                $value=in_array($raw,['In Stock','Sold'],true)?$raw:null;
            }elseif($canonical==='payment_status'){
                $value=in_array($raw,['paid','unpaid'],true)?$raw:null;
            }else{
                $value=textValue($raw);
            }

            $updates[]="`$actual`=?";
            $params[]=$value;

            $old=$item[$actual]??null;
            if((string)($old??'')!==(string)($value??'')){
                $changes[]=$canonical.': '.shown($old).' → '.shown($value);
            }
        }

        if(!$updates)throw new Exception('No editable owner-inventory columns were found in this table.');

        $params[]=$id;
        $conn->beginTransaction();

        $sql="UPDATE `iman_inventory_items` SET ".implode(', ',$updates)." WHERE id=?";
        $up=$conn->prepare($sql);
        $up->execute($params);

        try{
            $serialActual=$columnMap['serial_number']??null;
            $serialLabel=$serialActual?shown($original[$serialActual]??null,'#'.$id):'#'.$id;
            $details="Updated Iman Inventory item ".$serialLabel.".";
            if($changes)$details.=" Changes: ".implode('; ',$changes);
            $log=$conn->prepare("INSERT INTO activity_logs(user_id,action,details) VALUES(?,'Edited owner inventory item',?)");
            $log->execute([$user_id,$details]);
        }catch(Throwable $ignored){}

        $conn->commit();

        $success='The item was updated successfully. The form now shows the latest saved details.';

        $stmt->execute([$id]);
        $item=$stmt->fetch(PDO::FETCH_ASSOC)?:$item;
        $original=$item;
        $view=canonicalView($item,$columnMap);
    }catch(Throwable $e){
        if($conn->inTransaction())$conn->rollBack();
        $error='We could not save the item. '.$e->getMessage();
    }
}

$currentSerial=shown($view['serial_number']??null,'#'.$id);
$currentStatus=shown($view['status']??null);
$currentLocation=shown($view['location']??null);
$currentModel=shown($view['model_name']??null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>Edit Item | <?=safe($currentSerial)?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root{--primary:#1a4b2a;--primary-light:#2a6b3a;--gray-50:#f9fafb;--gray-100:#f3f4f6;--gray-200:#e5e7eb;--gray-300:#d1d5db;--gray-400:#9ca3af;--gray-500:#6b7280;--gray-700:#374151;--gray-800:#1f2937;--radius-md:.5rem;--radius-lg:.75rem;--radius-xl:1rem;--shadow-sm:0 1px 2px rgb(0 0 0/.05);--font:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif}
*{margin:0;padding:0;box-sizing:border-box}body{font-family:var(--font);background:var(--gray-100);color:var(--gray-800);line-height:1.5;overflow-x:hidden}
.main-content{padding:2rem 2rem 1rem;margin-left:260px;width:calc(100% - 260px);min-height:100vh}
.page-header{background:#fff;padding:1.5rem 2rem;border-radius:var(--radius-xl);margin-bottom:1.5rem;box-shadow:var(--shadow-sm);border:1px solid var(--gray-200)}
.page-header h1{font-size:1.75rem;font-weight:600;margin-bottom:.5rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}.page-header h1 i{color:var(--primary)}
.serial-code{font-family:'Courier New',monospace;background:var(--gray-100);padding:.25rem .75rem;border-radius:var(--radius-md);font-size:1rem}
.breadcrumb{color:var(--gray-500);font-size:.9rem}.breadcrumb a{color:var(--primary);text-decoration:none}.page-help{margin-top:.65rem;color:var(--gray-500);font-size:.88rem;max-width:900px}
.alert{padding:1rem 1.25rem;border-radius:var(--radius-md);margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:.75rem}.alert-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.form-container{background:#fff;border-radius:var(--radius-xl);border:1px solid var(--gray-200);overflow:hidden;box-shadow:var(--shadow-sm)}
.form-header{background:var(--gray-50);padding:1rem 1.5rem;border-bottom:1px solid var(--gray-200)}.form-header h3{font-size:1rem;font-weight:600;color:var(--gray-700);display:flex;align-items:center;gap:.5rem}.form-header h3 i{color:var(--primary)}
.form-body{padding:1.5rem}.info-box{background:var(--gray-50);border-radius:var(--radius-lg);padding:1rem 1.25rem;margin-bottom:1.5rem;border:1px solid var(--gray-200)}
.info-box p{margin:.5rem 0;font-size:.9rem}.info-box strong{color:var(--gray-700);width:125px;display:inline-block}.status-badge{display:inline-block;padding:.25rem .75rem;border-radius:9999px;font-size:.75rem;font-weight:600;background:#d1fae5;color:#065f46}
.section-note{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;padding:.8rem 1rem;border-radius:var(--radius-md);margin-bottom:1.25rem;font-size:.82rem}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1.25rem}.form-group{display:flex;flex-direction:column;gap:.5rem}.form-group.full-width{grid-column:span 2}.form-group label{font-size:.875rem;font-weight:500;color:var(--gray-700)}
.form-group input,.form-group select,.form-group textarea{padding:.75rem 1rem;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:.9rem;font-family:var(--font);background:#fff}.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,75,42,.1)}
.form-actions{margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--gray-200);display:flex;gap:1rem;justify-content:flex-end;flex-wrap:wrap}.btn{padding:.75rem 1.5rem;border:none;border-radius:var(--radius-md);font-size:.9rem;font-weight:500;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem}.btn-primary{background:var(--primary);color:#fff}.btn-secondary{background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-300)}
@media(max-width:1200px){.main-content{margin-left:0!important;width:100%!important;padding:5rem 1rem 1rem!important}}@media(max-width:768px){.page-header{padding:1rem 1.25rem}.page-header h1{font-size:1.25rem}.form-grid{grid-template-columns:1fr}.form-group.full-width{grid-column:span 1}.form-body{padding:1.25rem}.info-box strong{width:auto;display:block;margin-bottom:.25rem}.form-actions{flex-direction:column}.btn{width:100%;justify-content:center}}
</style>
<script>function confirmUpdate(){return confirm('Are you sure you want to save these changes?');}</script>
</head>
<body>
<?php include __DIR__.'/../includes/sidebar.php';?>
<div class="main-content">
<div class="page-header">
<h1><i class="fas fa-edit"></i>Edit Item <span class="serial-code"><?=safe($currentSerial)?></span></h1>
<div class="breadcrumb"><a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a><span> / </span><a href="overview.php">Iman Inventory</a><span> / </span><span>Edit Item</span></div>
<div class="page-help">The selected item is loaded with the details already saved in this inventory. You can change any field below, then save.</div>
</div>
<?php if($error):?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><div><strong>Changes were not saved.</strong><br><?=safe($error)?></div></div><?php endif;?>
<?php if($success):?><div class="alert alert-success"><i class="fas fa-check-circle"></i><div><strong>Item updated successfully.</strong><br><?=safe($success)?></div></div><?php endif;?>
<div class="form-container">
<div class="form-header"><h3><i class="fas fa-laptop"></i> Item Information</h3></div>
<div class="form-body">
<div class="info-box">
<p><strong>Inventory:</strong> Iman Inventory</p>
<p><strong>Current Serial:</strong> <?=safe($currentSerial)?></p>
<p><strong>Current Model:</strong> <?=safe($currentModel)?></p>
<p><strong>Current Status:</strong> <span class="status-badge"><?=safe($currentStatus)?></span></p>
<p><strong>Current Location:</strong> <?=safe($currentLocation)?></p>
</div>
<div class="section-note"><i class="fas fa-circle-info"></i> All fields use the values already stored for this item. </div>
<form method="POST" onsubmit="return confirmUpdate()">
<div class="form-grid">

<div class="form-group">
<label>Item Type</label>
<select name="item_type">
<option value="">-- Not Set --</option>
<option value="Device" <?= (($view['item_type']??'')==='Device')?'selected':'' ?>>Device</option>
<option value="Monitor" <?= (($view['item_type']??'')==='Monitor')?'selected':'' ?>>Monitor</option>
</select>
</div>

<div class="form-group">
<label>Asset ID</label>
<input type="text" name="asset_id" value="<?=safe($view['asset_id']??'')?>">
</div>

<div class="form-group">
<label>Buying Price (USD)</label>
<input type="number" step="0.01" name="buying_usd" value="<?=safe($view['buying_usd']??'')?>">
</div>

<div class="form-group">
<label>Selling Price (USD)</label>
<input type="number" step="0.01" name="selling_usd" value="<?=safe($view['selling_usd']??'')?>">
</div>

<div class="form-group">
<label>Buying Price (KES)</label>
<input type="number" step="0.01" name="buying_price" value="<?=safe($view['buying_price']??'')?>">
</div>

<div class="form-group">
<label>Set Selling Price (KES)</label>
<input type="number" step="0.01" name="planned_selling_price" value="<?=safe($view['planned_selling_price']??'')?>">
</div>

<div class="form-group">
<label>Manufacturer</label>
<input type="text" name="manufacturer" value="<?=safe($view['manufacturer']??'')?>">
</div>

<div class="form-group">
<label>Model Name</label>
<input type="text" name="model_name" value="<?=safe($view['model_name']??'')?>">
</div>

<div class="form-group">
<label>Processor / CPU</label>
<input type="text" name="processor" value="<?=safe($view['processor']??'')?>">
</div>

<div class="form-group">
<label>RAM</label>
<input type="text" name="ram" value="<?=safe($view['ram']??'')?>">
</div>

<div class="form-group">
<label>Storage</label>
<input type="text" name="storage" value="<?=safe($view['storage']??'')?>">
</div>

<div class="form-group">
<label>Serial Number</label>
<input type="text" name="serial_number" value="<?=safe($view['serial_number']??'')?>">
</div>

<div class="form-group">
<label>Grade</label>
<input type="text" name="grade" value="<?=safe($view['grade']??'')?>">
</div>

<div class="form-group">
<label>Touch Screen</label>
<input type="text" name="touch_screen" value="<?=safe($view['touch_screen']??'')?>">
</div>

<div class="form-group">
<label>Webcam</label>
<input type="text" name="webcam" value="<?=safe($view['webcam']??'')?>">
</div>

<div class="form-group">
<label>Location</label>
<select name="location">
<option value="">-- Not Set --</option>
<?php foreach(['KIMATHI','MOI','WAREHOUSE'] as $loc): ?>
<option value="<?=$loc?>" <?= (($view['location']??'')===$loc)?'selected':'' ?>><?=$loc?></option>
<?php endforeach; ?>
</select>
</div>

<div class="form-group">
<label>Status</label>
<select name="status">
<option value="">-- Not Set --</option>
<option value="In Stock" <?= (($view['status']??'')==='In Stock')?'selected':'' ?>>In Stock</option>
<option value="Sold" <?= (($view['status']??'')==='Sold')?'selected':'' ?>>Sold</option>
</select>
</div>

<div class="form-group">
<label>Sales Person</label>
<input type="text" name="sales_person" value="<?=safe($view['sales_person']??'')?>">
</div>

<div class="form-group">
<label>Actual Selling Price (KES)</label>
<input type="number" step="0.01" name="selling_price" value="<?=safe($view['selling_price']??'')?>">
</div>

<div class="form-group">
<label>Payment Status</label>
<select name="payment_status">
<option value="">-- Not Set --</option>
<option value="paid" <?= (($view['payment_status']??'')==='paid')?'selected':'' ?>>Paid</option>
<option value="unpaid" <?= (($view['payment_status']??'')==='unpaid')?'selected':'' ?>>Unpaid</option>
</select>
</div>

<div class="form-group">
<label>Sold At</label>
<input type="datetime-local" name="sold_at" value="<?= !empty($view['sold_at']) ? safe(date('Y-m-d\TH:i',strtotime((string)$view['sold_at']))) : '' ?>">
</div>

<div class="form-group full-width">
<label>Notes</label>
<textarea name="notes" rows="4"><?=safe($view['notes']??'')?></textarea>
</div>
</div>
<div class="form-actions">
<a href="overview.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Item</button>
</div>
</form>
</div>
</div>
</div>
<?php require_once __DIR__.'/../includes/footer.php';?>
</body></html>
