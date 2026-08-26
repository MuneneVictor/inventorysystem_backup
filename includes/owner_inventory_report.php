<?php
if (!isset($ownerKey,$ownerLabel,$reportMode)) die('Inventory report configuration missing.');
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/auth_check.php';
require_once __DIR__.'/owner_inventory_access.php';

$access = requireOwnerInventoryAccess($conn);
$user_id = (int)$access['user_id'];

$config = $ownerKey === 'imans_hustle'
    ? ['table'=>'iman_hustle_items','type'=>'hustle','folder'=>'imanhus']
    : ['table'=>'iman_inventory_items','type'=>'inventory','folder'=>'imaninv'];

$table = $config['table'];
$type = $config['type'];

function ownDash($v){ $v=trim((string)($v??'')); return $v===''?'-':$v; }
function ownMoney($v){ return $v===null||$v===''?'-':'KES '.number_format((float)$v,2); }
function ownUsd($v){ return $v===null||$v===''?'-':'$'.number_format((float)$v,2); }
function ownProfit($d){
    if(($d['selling_price']??null)!==null && ($d['buying_price']??null)!==null) return (float)$d['selling_price']-(float)$d['buying_price'];
    if(($d['planned_selling_price']??null)!==null && ($d['buying_price']??null)!==null) return (float)$d['planned_selling_price']-(float)$d['buying_price'];
    return null;
}

if(empty($_SESSION['owner_report_csrf'])) $_SESSION['owner_report_csrf']=bin2hex(random_bytes(32));

// AJAX search suggestions for serial/model.
if(isset($_GET['ajax_search'])){
    header('Content-Type: application/json; charset=utf-8');
    try{
        $field=$_GET['field']??'';
        $term=trim((string)($_GET['term']??''));
        if(!in_array($field,['serial_number','model_name'],true)) throw new Exception('Invalid search field.');
        if($term===''){echo json_encode(['ok'=>true,'results'=>[]]);exit;}
        $st=$conn->prepare("SELECT id,serial_number,model_name,item_type,status,location FROM `$table` WHERE `$field` LIKE :term ORDER BY date_added DESC,id DESC LIMIT 12");
        $st->execute(['term'=>'%'.$term.'%']);
        echo json_encode(['ok'=>true,'results'=>$st->fetchAll(PDO::FETCH_ASSOC)],JSON_UNESCAPED_UNICODE);
    }catch(Throwable$e){
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'Search failed: '.$e->getMessage(),'results'=>[]],JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Update sale details: typed sales person, price, paid/unpaid only.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_sale_details'])){
    try{
        if(!hash_equals($_SESSION['owner_report_csrf'],(string)($_POST['csrf_token']??''))) throw new Exception('Security validation failed.');
        $itemId=(int)($_POST['item_id']??0);
        $salesPerson=trim((string)($_POST['sales_person']??''));
        $sellingPrice=(float)($_POST['selling_price']??0);
        $paymentStatus=strtolower(trim((string)($_POST['payment_status']??'')));
        if($itemId<=0) throw new Exception('Invalid item.');
        if($salesPerson==='') throw new Exception('Please enter the sales person.');
        if($sellingPrice<=0) throw new Exception('Please enter a valid selling price.');
        if(!in_array($paymentStatus,['paid','unpaid'],true)) throw new Exception('Please select Paid or Unpaid.');

        $conn->beginTransaction();
        $st=$conn->prepare("SELECT * FROM `$table` WHERE id=? FOR UPDATE");
        $st->execute([$itemId]);
        $item=$st->fetch(PDO::FETCH_ASSOC);
        if(!$item) throw new Exception('Item was not found.');

        $up=$conn->prepare("UPDATE `$table`
                            SET status='Sold',
                                sales_person=?,
                                selling_price=?,
                                payment_status=?,
                                sold_at=COALESCE(sold_at,NOW())
                            WHERE id=?");
        $up->execute([$salesPerson,$sellingPrice,$paymentStatus,$itemId]);

        $serialLabel=trim((string)($item['serial_number']??'')) ?: '#'.$itemId;
        try{
            $log=$conn->prepare("INSERT INTO activity_logs (user_id,action,details) VALUES (?,'Updated owner sale details',?)");
            $log->execute([$user_id,"{$ownerLabel} item {$serialLabel} marked Sold; sales person: {$salesPerson}; price: KES ".number_format($sellingPrice,2)."; payment: {$paymentStatus}"]);
        }catch(Throwable $e){}

        $conn->commit();
        $_SESSION['owner_report_success']="Sale details updated successfully for {$serialLabel}.";
    }catch(Throwable $e){
        if($conn->inTransaction())$conn->rollBack();
        $_SESSION['owner_report_error']=$e->getMessage();
    }
    $qs=$_SERVER['QUERY_STRING']??'';
    header('Location: '.basename($_SERVER['PHP_SELF']).($qs!==''?'?'.$qs:'')); exit;
}

$flashSuccess=$_SESSION['owner_report_success']??'';
$flashError=$_SESSION['owner_report_error']??'';
unset($_SESSION['owner_report_success'],$_SESSION['owner_report_error']);

$serial=trim((string)($_GET['serial']??''));
$model=trim((string)($_GET['model']??''));
$location=strtoupper(trim((string)($_GET['location']??'')));
$status=trim((string)($_GET['status']??''));
$date_from=trim((string)($_GET['date_from']??''));
$date_to=trim((string)($_GET['date_to']??''));

if($reportMode==='overview'){
    if($date_from==='')$date_from=date('Y-m-01');
    if($date_to==='')$date_to=date('Y-m-d');
}

$sql="SELECT * FROM `$table` WHERE 1=1"; $params=[];
if($reportMode==='instock')$sql.=" AND status='In Stock'";
elseif($reportMode==='sold')$sql.=" AND status='Sold'";
elseif($status!==''){$sql.=" AND status=:status";$params['status']=$status;}
if($serial!==''){$sql.=" AND serial_number LIKE :serial";$params['serial']="%$serial%";}
if($model!==''){$sql.=" AND model_name LIKE :model";$params['model']="%$model%";}
if($location!==''&&in_array($location,['KIMATHI','MOI','WAREHOUSE'],true)){$sql.=" AND location=:location";$params['location']=$location;}
if($reportMode==='overview'){
    if($date_from!==''){$sql.=" AND DATE(date_added)>=:df";$params['df']=$date_from;}
    if($date_to!==''){$sql.=" AND DATE(date_added)<=:dt";$params['dt']=$date_to;}
}
if($reportMode==='sold'){
    if($date_from!==''){$sql.=" AND DATE(sold_at)>=:df";$params['df']=$date_from;}
    if($date_to!==''){$sql.=" AND DATE(sold_at)<=:dt";$params['dt']=$date_to;}
}
$sql.=' ORDER BY '.($reportMode==='sold'?'sold_at':'date_added').' DESC,id DESC';
$st=$conn->prepare($sql);$st->execute($params);$items=$st->fetchAll(PDO::FETCH_ASSOC);

// Maintenance map only for Overview.
$maintenance=[];
if($reportMode==='overview' && $items){
    $ids=array_column($items,'id');
    $ph=implode(',',array_fill(0,count($ids),'?'));
    try{
        $m=$conn->prepare("SELECT om.*,u.full_name performed_by_name
                           FROM owner_inventory_maintenance om
                           LEFT JOIN users u ON u.id=om.performed_by
                           WHERE om.owner_key=? AND om.item_id IN ($ph)
                           ORDER BY om.date_performed ASC");
        $m->execute(array_merge([$ownerKey],$ids));
        foreach($m->fetchAll(PDO::FETCH_ASSOC) as $row)$maintenance[(int)$row['item_id']][]=$row;
    }catch(Throwable $e){}
}
function ownMaintenanceText($rows){
    if(!$rows)return '-';
    $out=[];
    foreach($rows as$r){
        $changes=[];
        if(($r['old_processor']??'')!==($r['new_processor']??''))$changes[]='CPU: '.ownDash($r['old_processor']).' → '.ownDash($r['new_processor']);
        if(($r['old_ram']??'')!==($r['new_ram']??''))$changes[]='RAM: '.ownDash($r['old_ram']).' → '.ownDash($r['new_ram']);
        if(($r['old_storage']??'')!==($r['new_storage']??''))$changes[]='Storage: '.ownDash($r['old_storage']).' → '.ownDash($r['new_storage']);
        if(!empty($r['notes']))$changes[]='Notes: '.$r['notes'];
        $out[]=date('d M Y H:i',strtotime($r['date_performed'])).' — '.implode('; ',$changes);
    }
    return implode("\n",$out);
}

$total=count($items);
$inStock=count(array_filter($items,fn($d)=>($d['status']??'')==='In Stock'));
$sold=count(array_filter($items,fn($d)=>($d['status']??'')==='Sold'));
$buyingValue=array_sum(array_map(fn($d)=>(float)($d['buying_price']??0),$items));
$plannedValue=array_sum(array_map(fn($d)=>(float)($d['planned_selling_price']??0),$items));
$revenue=array_sum(array_map(fn($d)=>(float)($d['selling_price']??0),$items));
$realizedProfit=array_sum(array_map(function($d){return (($d['selling_price']??null)!==null&&($d['buying_price']??null)!==null)?((float)$d['selling_price']-(float)$d['buying_price']):0;},$items));
$pageTitle=$reportMode==='overview'?'Overview':($reportMode==='instock'?'In Stock':'Sold');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?=htmlspecialchars($ownerLabel.' '.$pageTitle)?> | Mombasa Computers</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root{--p:#1a4b2a;--pl:#2a6b3a;--bg:#f3f4f6;--b:#e5e7eb;--t:#1f2937;--m:#6b7280;--gold:#d7b729}
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;background:var(--bg);color:var(--t)}
.main{margin-left:260px;padding:2rem;min-height:100vh}.head,.filters,.card,.tablebox{background:#fff;border:1px solid var(--b);border-radius:14px;box-shadow:0 1px 2px #0000000d}
.head{padding:1.4rem 1.6rem}.headrow{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap}.head h1{margin:0;font-size:1.65rem}
.breadcrumb{margin-top:.45rem;color:var(--m);font-size:.86rem}.breadcrumb a{color:var(--p);text-decoration:none}.summary{margin-top:.65rem;color:var(--m);font-size:.88rem;max-width:900px;line-height:1.55}
.tabs,.quick{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem}.tabs a,.btn{padding:.58rem .8rem;border-radius:8px;text-decoration:none;border:0;cursor:pointer;font-weight:650;display:inline-flex;align-items:center;gap:.35rem}
.tabs a{color:var(--p);border:1px solid var(--b);background:#fff}.tabs a.active,.btn{background:var(--p);color:#fff}.btn:hover{background:var(--pl)}.secondary{background:#6b7280}.excel{background:#217346}.edit{background:#2563eb}.spec{background:#7c3aed}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin:1rem 0}.card{padding:1rem}.n{font-size:1.45rem;font-weight:800;color:var(--p)}.l{font-size:.72rem;color:var(--m);text-transform:uppercase;letter-spacing:.04em}
.filters{padding:1rem;margin-bottom:1rem}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem}.g{display:flex;flex-direction:column;gap:.3rem;position:relative}.g label{font-size:.78rem;color:var(--m);font-weight:650}.g input,.g select,.g textarea{padding:.62rem;border:1px solid #d1d5db;border-radius:8px;font:inherit}.filter-actions{display:flex;gap:.5rem;align-items:end;flex-wrap:wrap}
.suggestions{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:8px;box-shadow:0 8px 24px #0002;z-index:30;max-height:260px;overflow:auto;display:none}.suggestions.open{display:block}.suggestion{padding:.6rem .7rem;border-bottom:1px solid #eee;cursor:pointer;font-size:.78rem}.suggestion:hover{background:#f3f4f6}.suggestion small{display:block;color:var(--m);margin-top:.15rem}
.tablebox{overflow:hidden}.scroll{overflow:auto}table{border-collapse:separate;border-spacing:0;width:100%;min-width:1700px;font-size:.78rem}th,td{padding:.72rem .78rem;border-bottom:1px solid #edf0f2;text-align:left;vertical-align:middle;white-space:nowrap}th{position:sticky;top:0;z-index:2;background:var(--gold);color:#171717;font-weight:800}tbody tr:nth-child(even){background:#fafbfc}tbody tr:hover{background:#f5f8f6}
.badge{display:inline-block;padding:.22rem .52rem;background:#dcfce7;color:#166534;border-radius:999px;font-weight:750}.badge.sold{background:#fee2e2;color:#b91c1c}.type{font-size:.65rem;padding:.12rem .35rem;border-radius:999px;background:#e0e7ff;color:#3730a3;margin-left:.3rem}.notes,.maint{white-space:pre-line!important;max-width:300px;line-height:1.4}.action-cell{display:flex;gap:.35rem;align-items:center}.mini{padding:.4rem .55rem;font-size:.7rem}
.flash{padding:.9rem 1rem;border-radius:10px;margin:1rem 0}.ok{background:#ecfdf5;color:#065f46}.err{background:#fef2f2;color:#991b1b}.empty{padding:3rem;text-align:center;color:var(--m)}
.modal-bg{display:none;position:fixed;inset:0;background:#0008;z-index:5000;align-items:center;justify-content:center;padding:1rem}.modal-bg.open{display:flex}.modal{background:#fff;border-radius:14px;width:min(480px,100%);box-shadow:0 20px 60px #0005}.modal-head{padding:1rem 1.2rem;border-bottom:1px solid var(--b);display:flex;justify-content:space-between;align-items:center}.modal-body{padding:1.2rem}.modal-body .g{margin-bottom:1rem}.close{background:none;border:0;font-size:1.4rem;cursor:pointer}.modal-actions{display:flex;justify-content:flex-end;gap:.6rem}
@media(max-width:1200px){.main{margin-left:0;padding:5rem 1rem 1rem}}@media(max-width:700px){.main{padding:4.5rem .75rem .75rem}.quick .btn{flex:1;justify-content:center}}
</style>
</head>
<body>
<?php include __DIR__.'/sidebar.php'; ?>
<main class="main">
<section class="head">
  <div class="headrow">
    <div>
      <h1><i class="fas fa-boxes-stacked"></i> <?=htmlspecialchars($ownerLabel)?> — <?=htmlspecialchars($pageTitle)?></h1>
      <div class="breadcrumb">
        <a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <span> / </span><a href="overview.php"><?=htmlspecialchars($ownerLabel)?></a>
        <span> / </span><span><?=htmlspecialchars($pageTitle)?></span>
      </div>
      <div class="summary">
        Use this page to review, search and manage the items in this inventory.
        You can add stock, upload an Excel file, edit item details, record hardware changes, update sale details and export reports from here.
      </div>
    </div>
    <div class="quick">
      <a class="btn" href="add_device.php"><i class="fas fa-plus"></i> Add Item</a>
      <a class="btn secondary" href="bulk_upload.php"><i class="fas fa-file-excel"></i> Bulk Upload</a>
      <a class="btn excel" href="export_excel.php?report=<?=urlencode($reportMode)?>&<?=htmlspecialchars(http_build_query(array_filter(['serial'=>$serial,'model'=>$model,'location'=>$location,'status'=>$status,'date_from'=>$date_from,'date_to'=>$date_to],fn($v)=>$v!=='')))?>"><i class="fas fa-download"></i> Export Excel</a>
    </div>
  </div>
  <div class="tabs">
    <a class="<?=$reportMode==='overview'?'active':''?>" href="overview.php">Overview</a>
    <a class="<?=$reportMode==='instock'?'active':''?>" href="instock.php">In Stock</a>
    <a class="<?=$reportMode==='sold'?'active':''?>" href="sold.php">Sold</a>
  </div>
</section>

<?php if($flashSuccess):?><div class="flash ok"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($flashSuccess)?></div><?php endif;?>
<?php if($flashError):?><div class="flash err"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($flashError)?></div><?php endif;?>

<section class="stats">
  <div class="card"><div class="n"><?=$total?></div><div class="l"><?= $reportMode==='overview'?'Total items':'Matching items' ?></div></div>
  <?php if($reportMode==='overview'):?>
  <div class="card"><div class="n"><?=$inStock?></div><div class="l">In stock</div></div>
  <div class="card"><div class="n"><?=$sold?></div><div class="l">Sold</div></div>
  <?php endif;?>
  <div class="card"><div class="n">KES <?=number_format($buyingValue,2)?></div><div class="l">Buying value</div></div>
  <div class="card"><div class="n">KES <?=number_format($plannedValue,2)?></div><div class="l">Planned selling value</div></div>
  <?php if($reportMode==='sold'):?>
  <div class="card"><div class="n">KES <?=number_format($revenue,2)?></div><div class="l">Actual sales</div></div>
  <div class="card"><div class="n">KES <?=number_format($realizedProfit,2)?></div><div class="l">Realized profit</div></div>
  <?php endif;?>
</section>

<section class="filters">
<form class="grid" method="get">
  <div class="g"><label>Serial Number</label><input id="serialSearch" name="serial" autocomplete="off" value="<?=htmlspecialchars($serial)?>" placeholder="Type serial..."><div id="serialSuggestions" class="suggestions"></div></div>
  <div class="g"><label>Model</label><input id="modelSearch" name="model" autocomplete="off" value="<?=htmlspecialchars($model)?>" placeholder="Type model..."><div id="modelSuggestions" class="suggestions"></div></div>
  <div class="g"><label>Location</label><select name="location"><option value="">All locations</option><?php foreach(['KIMATHI','MOI','WAREHOUSE'] as$l):?><option <?=$location===$l?'selected':''?>><?=$l?></option><?php endforeach;?></select></div>
  <?php if($reportMode==='overview'):?><div class="g"><label>Status</label><select name="status"><option value="">All</option><option <?=$status==='In Stock'?'selected':''?>>In Stock</option><option <?=$status==='Sold'?'selected':''?>>Sold</option></select></div><?php endif;?>
  <?php if($reportMode==='overview'||$reportMode==='sold'):?>
    <div class="g"><label><?=$reportMode==='sold'?'Sold From':'Added From'?></label><input type="date" name="date_from" value="<?=htmlspecialchars($date_from)?>"></div>
    <div class="g"><label><?=$reportMode==='sold'?'Sold To':'Added To'?></label><input type="date" name="date_to" value="<?=htmlspecialchars($date_to)?>"></div>
  <?php endif;?>
  <div class="filter-actions"><button class="btn" type="submit"><i class="fas fa-filter"></i> Filter</button><a class="btn secondary" href="<?=basename($_SERVER['PHP_SELF'])?>">Reset</a></div>
</form>
</section>

<section class="tablebox"><div class="scroll">
<?php if(!$items):?><div class="empty"><i class="fas fa-box-open fa-2x"></i><p>No matching items found.</p></div><?php else:?>
<table>
<thead><tr>
<th>#</th>
<?php if($type==='hustle'):?>
<th>Asset ID</th><th>MFG</th><th>Model</th><th>Type</th><th>Form Factor</th><th>CPU</th><th>RAM</th><th>Storage</th><th>Serial</th><th>Grade</th><th>B.P</th><th>S.P</th><th>Profit</th><th>Notes</th><th>Location</th>
<?php else:?>
<th>Asset ID</th><th>Buying $</th><th>Selling $</th><th>BP</th><th>SP</th><th>Profit</th><th>MFG</th><th>Model</th><th>Type</th><th>CPU</th><th>RAM</th><th>Storage</th><th>Serial #</th><th>Grade</th><th>Touch</th><th>Webcam</th><th>Notes</th><th>Location</th>
<?php endif;?>
<th>Status</th>
<?php if($reportMode==='overview'):?><th>Maintenance / Changes</th><?php endif;?>
<?php if($reportMode==='sold'):?><th>Sales Person</th><th>Sold At</th><th>Actual Selling Price</th><th>Payment</th><?php endif;?>
<th>Actions</th>
</tr></thead>
<tbody>
<?php foreach($items as$i=>$d): $p=ownProfit($d);?>
<tr>
<td><?=($i+1)?></td>
<?php if($type==='hustle'):?>
<td><?=htmlspecialchars(ownDash($d['asset_id']))?></td><td><?=htmlspecialchars(ownDash($d['manufacturer']))?></td>
<td><?=htmlspecialchars(ownDash($d['model_name']))?></td><td><?=htmlspecialchars(ownDash($d['item_type']))?></td>
<td><?=htmlspecialchars(ownDash($d['form_factor']))?></td><td><?=htmlspecialchars(ownDash($d['processor']))?></td>
<td><?=htmlspecialchars(ownDash($d['ram']))?></td><td><?=htmlspecialchars(ownDash($d['storage']))?></td>
<td><?=htmlspecialchars(ownDash($d['serial_number']))?></td><td><?=htmlspecialchars(ownDash($d['grade']))?></td>
<td><?=htmlspecialchars(ownMoney($d['buying_price']))?></td><td><?=htmlspecialchars(ownMoney($d['planned_selling_price']))?></td>
<td><?=htmlspecialchars($p===null?'-':ownMoney($p))?></td><td class="notes"><?=htmlspecialchars(ownDash($d['notes']))?></td><td><?=htmlspecialchars(ownDash($d['location']))?></td>
<?php else:?>
<td><?=htmlspecialchars(ownDash($d['asset_id']))?></td><td><?=htmlspecialchars(ownUsd($d['buying_usd']))?></td><td><?=htmlspecialchars(ownUsd($d['selling_usd']))?></td>
<td><?=htmlspecialchars(ownMoney($d['buying_price']))?></td><td><?=htmlspecialchars(ownMoney($d['planned_selling_price']))?></td>
<td><?=htmlspecialchars($p===null?'-':ownMoney($p))?></td><td><?=htmlspecialchars(ownDash($d['manufacturer']))?></td><td><?=htmlspecialchars(ownDash($d['model_name']))?></td>
<td><?=htmlspecialchars(ownDash($d['item_type']))?></td><td><?=htmlspecialchars(ownDash($d['processor']))?></td><td><?=htmlspecialchars(ownDash($d['ram']))?></td>
<td><?=htmlspecialchars(ownDash($d['storage']))?></td><td><?=htmlspecialchars(ownDash($d['serial_number']))?></td><td><?=htmlspecialchars(ownDash($d['grade']))?></td>
<td><?=htmlspecialchars(ownDash($d['touch_screen']))?></td><td><?=htmlspecialchars(ownDash($d['webcam']))?></td><td class="notes"><?=htmlspecialchars(ownDash($d['notes']))?></td><td><?=htmlspecialchars(ownDash($d['location']))?></td>
<?php endif;?>
<td><span class="badge <?=$d['status']==='Sold'?'sold':''?>"><?=htmlspecialchars($d['status'])?></span></td>
<?php if($reportMode==='overview'):?><td class="maint"><?=htmlspecialchars(ownMaintenanceText($maintenance[(int)$d['id']]??[]))?></td><?php endif;?>
<?php if($reportMode==='sold'):?><td><?=htmlspecialchars(ownDash($d['sales_person']))?></td><td><?=htmlspecialchars(ownDash($d['sold_at']))?></td><td><?=htmlspecialchars(ownMoney($d['selling_price']))?></td><td><?=htmlspecialchars(ownDash($d['payment_status']))?></td><?php endif;?>
<td class="action-cell">
  <a class="btn mini edit" href="edit_device.php?id=<?=(int)$d['id']?>"><i class="fas fa-edit"></i> Edit</a>
  <?php if(($d['status']??'')==='In Stock' && strtolower((string)($d['item_type']??''))!=='monitor'):?>
  <a class="btn mini spec" href="update_specs.php?id=<?=(int)$d['id']?>"><i class="fas fa-microchip"></i> Specs</a>
  <?php endif;?>
  <button type="button" class="btn mini saleBtn" data-id="<?=(int)$d['id']?>" data-label="<?=htmlspecialchars(ownDash($d['serial_number']).' — '.ownDash($d['model_name']),ENT_QUOTES)?>"><i class="fas fa-cash-register"></i> Sale Details</button>
</td>
</tr>
<?php endforeach;?>
</tbody></table>
<?php endif;?>
</div></section>

<div id="saleModal" class="modal-bg">
 <div class="modal">
  <div class="modal-head"><strong>Update Sale Details</strong><button type="button" class="close" id="closeModal">&times;</button></div>
  <div class="modal-body">
   <div id="saleLabel" style="margin-bottom:1rem;color:var(--m)"></div>
   <form method="post">
    <input type="hidden" name="update_sale_details" value="1">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['owner_report_csrf'])?>">
    <input type="hidden" name="item_id" id="saleItemId">
    <div class="g"><label>Sales Person</label><input name="sales_person" required placeholder="Type salesperson name"></div>
    <div class="g"><label>Selling Price (KES)</label><input type="number" step="0.01" min="0.01" name="selling_price" required></div>
    <div class="g"><label>Payment Status</label><select name="payment_status" required><option value="">-- Select --</option><option value="paid">Paid</option><option value="unpaid">Unpaid</option></select></div>
    <div class="modal-actions"><button type="button" class="btn secondary" id="cancelModal">Cancel</button><button class="btn" type="submit">Save Sale Details</button></div>
   </form>
  </div>
 </div>
</div>

<script>
const modal=document.getElementById('saleModal'), closeModal=()=>modal.classList.remove('open');
document.querySelectorAll('.saleBtn').forEach(b=>b.addEventListener('click',()=>{document.getElementById('saleItemId').value=b.dataset.id;document.getElementById('saleLabel').textContent=b.dataset.label;modal.classList.add('open')}));
document.getElementById('closeModal').onclick=closeModal;document.getElementById('cancelModal').onclick=closeModal;
modal.addEventListener('click',e=>{if(e.target===modal)closeModal()});

function setupAjaxSuggest(inputId,boxId,field){
 const input=document.getElementById(inputId),box=document.getElementById(boxId);
 if(!input||!box)return;
 let timer,controller;
 input.addEventListener('input',()=>{
   clearTimeout(timer);
   if(controller)controller.abort();
   const term=input.value.trim();
   if(!term){box.innerHTML='';box.classList.remove('open');return;}
   timer=setTimeout(async()=>{
     controller=new AbortController();
     try{
       const params=new URLSearchParams({ajax_search:'1',field:field,term:term});
       const response=await fetch(window.location.pathname+'?'+params.toString(),{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},cache:'no-store',signal:controller.signal});
       const raw=await response.text();
       let data;
       try{data=JSON.parse(raw);}catch(e){throw new Error('The server returned an invalid search response.');}
       if(!response.ok||data.ok===false)throw new Error(data.error||'Search request failed.');
       box.innerHTML='';
       const results=data.results||[];
       if(!results.length){
         box.innerHTML='<div class="suggestion"><strong>No matches found</strong><small>Keep typing or check the spelling.</small></div>';
         box.classList.add('open');return;
       }
       results.forEach(r=>{
         const d=document.createElement('div');d.className='suggestion';
         const main=field==='serial_number'?(r.serial_number||''):(r.model_name||'');
         d.innerHTML='<strong>'+escapeHtml(main||'-')+'</strong><small>'+escapeHtml((r.serial_number||'-')+' • '+(r.model_name||'-')+' • '+(r.status||'-'))+'</small>';
         d.onclick=()=>{input.value=main;box.classList.remove('open');input.form.requestSubmit();};
         box.appendChild(d);
       });
       box.classList.add('open');
     }catch(e){
       if(e.name==='AbortError')return;
       box.innerHTML='<div class="suggestion" style="color:#991b1b;background:#fef2f2"><strong>Search error</strong><small>'+escapeHtml(e.message)+'</small></div>';
       box.classList.add('open');
     }
   },180);
 });
 document.addEventListener('click',e=>{if(!box.contains(e.target)&&e.target!==input)box.classList.remove('open');});
}
function escapeHtml(s){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
setupAjaxSuggest('serialSearch','serialSuggestions','serial_number');
setupAjaxSuggest('modelSearch','modelSuggestions','model_name');
</script>
<?php require_once __DIR__.'/footer.php'; ?>
</body></html>
