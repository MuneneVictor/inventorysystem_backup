<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/owner_inventory_access.php';
$access=requireOwnerInventoryAccess($conn); $user_id=(int)$access['user_id'];

function safe($v){return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');}
function nullv($v){$v=trim((string)$v);return($v===''||$v==='-')?null:$v;}
function numv($v){
    $v=trim((string)$v);
    if($v===''||$v==='-')return null;
    $v=str_ireplace(['KES','KSH','USD'],'',$v);
    $v=str_replace([',','$'],'',$v);
    if(!is_numeric(trim($v))) throw new Exception('A price field contains an invalid number: '.$v);
    return(float)trim($v);
}
function locv($v){
    $v=strtoupper(trim((string)$v));
    if($v==='')return null;
    if(!in_array($v,['KIMATHI','MOI','WAREHOUSE'],true)) throw new Exception('Location must be KIMATHI, MOI or WAREHOUSE.');
    return$v;
}

$id=(int)($_GET['id']??0);
if($id<=0){http_response_code(400);die('Unable to open this item because its inventory ID is missing or invalid.');}
$stmt=$conn->prepare("SELECT * FROM `iman_inventory_items` WHERE id=? LIMIT 1");$stmt->execute([$id]);$item=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$item){http_response_code(404);die('This Iman Inventory item could not be found. It may have been removed or the link may be outdated.');}

$original=$item;$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $item_type=in_array($_POST['item_type']??'',['Device','Monitor'],true)?$_POST['item_type']:'Device';$asset_id=nullv($_POST['asset_id']??'');$buying_usd=numv($_POST['buying_usd']??'');$selling_usd=numv($_POST['selling_usd']??'');$buying_price=numv($_POST['buying_price']??'');$planned_selling_price=numv($_POST['planned_selling_price']??'');$manufacturer=nullv($_POST['manufacturer']??'');$model_name=nullv($_POST['model_name']??'');$processor=nullv($_POST['processor']??'');$ram=nullv($_POST['ram']??'');$storage=nullv($_POST['storage']??'');$serial_number=nullv($_POST['serial_number']??'');$grade=nullv($_POST['grade']??'');$touch_screen=nullv($_POST['touch_screen']??'');$webcam=nullv($_POST['webcam']??'');$location=locv($_POST['location']??'');$notes=nullv($_POST['notes']??'');
        $conn->beginTransaction();
        $update=$conn->prepare("UPDATE `iman_inventory_items` SET `item_type`=:item_type,`asset_id`=:asset_id,`buying_usd`=:buying_usd,`selling_usd`=:selling_usd,`buying_price`=:buying_price,`planned_selling_price`=:planned_selling_price,`manufacturer`=:manufacturer,`model_name`=:model_name,`processor`=:processor,`ram`=:ram,`storage`=:storage,`serial_number`=:serial_number,`grade`=:grade,`touch_screen`=:touch_screen,`webcam`=:webcam,`location`=:location,`notes`=:notes WHERE id=:id");
        $update->execute(['item_type'=>$item_type,'asset_id'=>$asset_id,'buying_usd'=>$buying_usd,'selling_usd'=>$selling_usd,'buying_price'=>$buying_price,'planned_selling_price'=>$planned_selling_price,'manufacturer'=>$manufacturer,'model_name'=>$model_name,'processor'=>$processor,'ram'=>$ram,'storage'=>$storage,'serial_number'=>$serial_number,'grade'=>$grade,'touch_screen'=>$touch_screen,'webcam'=>$webcam,'location'=>$location,'notes'=>$notes,'id'=>$id]);

        try{
            $log=$conn->prepare("INSERT INTO activity_logs(user_id,action,details) VALUES(?,'Edited owner inventory item',?)");
            $serialLabel=trim((string)($original['serial_number']??'')) ?: '#'.$id;
            $log->execute([$user_id,'Updated Iman Inventory item '.$serialLabel.'.']);
        }catch(Throwable $ignored){}

        $conn->commit();
        $success='The item details were updated successfully. The latest saved values are shown below.';
        $stmt->execute([$id]);$item=$stmt->fetch(PDO::FETCH_ASSOC);$original=$item;
    }catch(Throwable $e){
        if($conn->inTransaction())$conn->rollBack();
        $error='We could not save the changes. '.$e->getMessage();
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Edit Item | <?=safe($item['serial_number'] ?: '#'.$id)?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root{--primary:#1a4b2a;--primary-light:#2a6b3a;--gray-50:#f9fafb;--gray-100:#f3f4f6;--gray-200:#e5e7eb;--gray-300:#d1d5db;--gray-500:#6b7280;--gray-700:#374151;--gray-800:#1f2937;--radius-md:.5rem;--radius-lg:.75rem;--radius-xl:1rem;--shadow-sm:0 1px 2px rgb(0 0 0 /.05)}
*{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;background:var(--gray-100);color:var(--gray-800);line-height:1.5}.main-content{padding:2rem;margin-left:260px;width:calc(100% - 260px);min-height:100vh}.page-header,.form-container{background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-xl);box-shadow:var(--shadow-sm)}.page-header{padding:1.5rem 2rem;margin-bottom:1.5rem}.page-header h1{font-size:1.65rem;font-weight:650;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}.page-header h1 i{color:var(--primary)}.serial-code{font-family:monospace;background:var(--gray-100);padding:.25rem .7rem;border-radius:var(--radius-md);font-size:.95rem}.breadcrumb{color:var(--gray-500);font-size:.88rem;margin-top:.45rem}.breadcrumb a{color:var(--primary);text-decoration:none}.page-help{margin-top:.65rem;color:var(--gray-500);font-size:.88rem;max-width:900px}.alert{padding:1rem 1.2rem;border-radius:var(--radius-md);margin-bottom:1.25rem;display:flex;gap:.65rem;align-items:flex-start}.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}.alert-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}.form-container{overflow:hidden}.form-header{background:var(--gray-50);padding:1rem 1.5rem;border-bottom:1px solid var(--gray-200);font-weight:650}.form-body{padding:1.5rem}.info-box{background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--radius-lg);padding:1rem 1.25rem;margin-bottom:1.5rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem}.info-item small{display:block;color:var(--gray-500);font-size:.7rem;text-transform:uppercase;font-weight:700}.info-item strong{display:block;margin-top:.2rem;font-size:.9rem}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1.15rem}.form-group{display:flex;flex-direction:column;gap:.45rem}.form-group.full-width{grid-column:span 2}.form-group label{font-size:.85rem;font-weight:600;color:var(--gray-700)}.form-group input,.form-group select,.form-group textarea{padding:.75rem 1rem;border:1px solid var(--gray-300);border-radius:var(--radius-md);font:inherit;background:#fff}.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,75,42,.1)}.form-actions{margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--gray-200);display:flex;gap:.75rem;justify-content:flex-end;flex-wrap:wrap}.btn{padding:.75rem 1.2rem;border:0;border-radius:var(--radius-md);font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.45rem;cursor:pointer}.btn-primary{background:var(--primary);color:#fff}.btn-secondary{background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-300)}
@media(max-width:1200px){.main-content{margin-left:0;width:100%;padding:5rem 1rem 1rem}}@media(max-width:768px){.form-grid{grid-template-columns:1fr}.form-group.full-width{grid-column:auto}.form-actions{flex-direction:column-reverse}.btn{width:100%;justify-content:center}}
</style></head><body>
<?php include __DIR__.'/../includes/sidebar.php'; ?>
<div class="main-content">
<div class="page-header">
<h1><i class="fas fa-edit"></i> Edit Item <span class="serial-code"><?=safe($item['serial_number'] ?: '#'.$id)?></span></h1>
<div class="breadcrumb"><a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a> / <a href="overview.php">Iman Inventory</a> / Edit Item</div>
<div class="page-help">The item you selected is already loaded below. Review the current information, change only what needs correcting, then save your changes.</div>
</div>

<?php if($error):?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><div><strong>Changes were not saved.</strong><br><?=safe($error)?></div></div><?php endif;?>
<?php if($success):?><div class="alert alert-success"><i class="fas fa-check-circle"></i><div><strong>Saved successfully.</strong><br><?=safe($success)?></div></div><?php endif;?>

<div class="form-container"><div class="form-header"><i class="fas fa-box"></i> Current Item Details</div><div class="form-body">
<div class="info-box">
<div class="info-item"><small>Inventory</small><strong>Iman Inventory</strong></div>
<div class="info-item"><small>Status</small><strong><?=safe($item['status'] ?: '-')?></strong></div>
<div class="info-item"><small>Serial Number</small><strong><?=safe($item['serial_number'] ?: '-')?></strong></div>
<div class="info-item"><small>Model</small><strong><?=safe($item['model_name'] ?: '-')?></strong></div>
<div class="info-item"><small>Location</small><strong><?=safe($item['location'] ?: '-')?></strong></div>
</div>
<form method="post"><div class="form-grid"><div class="form-group"><label>Item Type</label><select name="item_type"><option value="Device" <?= (($item['item_type']??'Device')==='Device')?'selected':'' ?>>Device</option><option value="Monitor" <?= (($item['item_type']??'')==='Monitor')?'selected':'' ?>>Monitor</option></select></div><div class="form-group"><label>Asset ID</label><input type="text" name="asset_id" value="<?=htmlspecialchars((string)($item['asset_id']??''))?>"></div><div class="form-group"><label>Buying Price (USD)</label><input type="number" step="0.01" name="buying_usd" value="<?=htmlspecialchars((string)($item['buying_usd']??''))?>"></div><div class="form-group"><label>Selling Price (USD)</label><input type="number" step="0.01" name="selling_usd" value="<?=htmlspecialchars((string)($item['selling_usd']??''))?>"></div><div class="form-group"><label>Buying Price (KES)</label><input type="number" step="0.01" name="buying_price" value="<?=htmlspecialchars((string)($item['buying_price']??''))?>"></div><div class="form-group"><label>Planned Selling Price (KES)</label><input type="number" step="0.01" name="planned_selling_price" value="<?=htmlspecialchars((string)($item['planned_selling_price']??''))?>"></div><div class="form-group"><label>Manufacturer</label><input type="text" name="manufacturer" value="<?=htmlspecialchars((string)($item['manufacturer']??''))?>"></div><div class="form-group"><label>Model Name</label><input type="text" name="model_name" value="<?=htmlspecialchars((string)($item['model_name']??''))?>"></div><div class="form-group"><label>Processor / CPU</label><input type="text" name="processor" value="<?=htmlspecialchars((string)($item['processor']??''))?>"></div><div class="form-group"><label>RAM</label><input type="text" name="ram" value="<?=htmlspecialchars((string)($item['ram']??''))?>"></div><div class="form-group"><label>Storage</label><input type="text" name="storage" value="<?=htmlspecialchars((string)($item['storage']??''))?>"></div><div class="form-group"><label>Serial Number</label><input type="text" name="serial_number" value="<?=htmlspecialchars((string)($item['serial_number']??''))?>"></div><div class="form-group"><label>Grade</label><input type="text" name="grade" value="<?=htmlspecialchars((string)($item['grade']??''))?>"></div><div class="form-group"><label>Touch Screen</label><input type="text" name="touch_screen" value="<?=htmlspecialchars((string)($item['touch_screen']??''))?>"></div><div class="form-group"><label>Webcam</label><input type="text" name="webcam" value="<?=htmlspecialchars((string)($item['webcam']??''))?>"></div><div class="form-group"><label>Location</label><select name="location"><option value="">-- Not Set --</option><?php foreach(['KIMATHI','MOI','WAREHOUSE'] as $loc): ?><option value="<?=$loc?>" <?= (($item['location']??'')===$loc)?'selected':'' ?>><?=$loc?></option><?php endforeach; ?></select></div><div class="form-group full-width"><label>Notes</label><textarea name="notes" rows="4"><?=htmlspecialchars((string)($item['notes']??''))?></textarea></div></div>
<div class="form-actions">
<a href="overview.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Overview</a>
<?php if(($item['status']??'')==='In Stock' && strtolower((string)($item['item_type']??''))!=='monitor'):?><a href="update_specs.php?id=<?=$id?>" class="btn btn-secondary"><i class="fas fa-microchip"></i> Update Specs</a><?php endif;?>
<button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Changes</button>
</div></form></div></div>
</div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
</body></html>