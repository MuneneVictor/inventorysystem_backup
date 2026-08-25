<?php
if (!isset($ownerKey,$ownerLabel,$reportMode)) die('Inventory report configuration missing.');
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/auth_check.php';

$role=$_SESSION['role'];
$user_id=(int)$_SESSION['user_id'];
$userEmail = strtolower(trim($_SESSION['email'] ?? ''));
$allowedEmails = [
    'stephanie@mombasacomputers.co.ke',
   ];
$hasAccess =
    $role === 'super_admin' ||
    (
        $role === 'inventory_admin' &&
        in_array($userEmail, $allowedEmails, true)
    );

if (!$hasAccess) {
    die('You Don\'t have Permission to view this page.');
}

$user_branch='';
if($role==='manager'){
    $q=$conn->prepare('SELECT branch FROM users WHERE id=?');
    $q->execute([$user_id]);
    $user_branch=(string)($q->fetchColumn()?:'');
}

if (empty($_SESSION['owner_report_csrf'])) {
    $_SESSION['owner_report_csrf']=bin2hex(random_bytes(32));
}

// -----------------------------------------------------------------------------
// Update sale details from owner report
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_sale_details'])) {
    $csrf=(string)($_POST['csrf_token']??'');
    if (!hash_equals($_SESSION['owner_report_csrf'],$csrf)) {
        $_SESSION['owner_report_error']='Security validation failed. Please try again.';
    } else {
        $itemType=(string)($_POST['item_type']??'');
        $serialPost=trim((string)($_POST['serial_number']??''));
        $salesPerson=(int)($_POST['sales_person']??0);
        $sellingPrice=(float)($_POST['selling_price']??0);
        $paymentStatus=(string)($_POST['payment_status']??'');
        $paymentMethod=trim((string)($_POST['payment_method']??''));
        $saleNotes=trim((string)($_POST['sale_notes']??''));

        try {
            if (!in_array($itemType,['device','monitor'],true)) throw new Exception('Invalid inventory item.');
            if ($serialPost==='') throw new Exception('Serial number is required.');
            if ($salesPerson<=0) throw new Exception('Please select a salesperson.');
            if ($sellingPrice<=0) throw new Exception('Please enter a valid selling price.');
            if (!in_array($paymentStatus,['paid','unpaid'],true)) throw new Exception('Please select paid or unpaid.');

            $allowedPaymentMethods=['cash','mpesa-till','mpesa-pochi','bank-transfer'];
            if ($paymentMethod!=='' && !in_array($paymentMethod,$allowedPaymentMethods,true)) {
                throw new Exception('Invalid payment method selected.');
            }

            // Empty payment method is intentionally allowed and stored as NULL.
            $paymentMethodDb=$paymentMethod!==''?$paymentMethod:null;

            $u=$conn->prepare("SELECT id,full_name FROM users WHERE id=? AND role='sales' AND is_active=1 LIMIT 1");
            $u->execute([$salesPerson]);
            $salesUser=$u->fetch(PDO::FETCH_ASSOC);
            if(!$salesUser) throw new Exception('Selected salesperson is not available.');

            $conn->beginTransaction();
            if ($itemType==='device') {
                $q=$conn->prepare("SELECT * FROM devices WHERE serial_number=? AND inventory_owner=? AND status='In Stock' FOR UPDATE");
                $q->execute([$serialPost,$ownerKey]);
                $item=$q->fetch(PDO::FETCH_ASSOC);
                if(!$item) throw new Exception('Device was not found in stock for this inventory owner.');
                $description=trim(($item['manufacturer']??'').' '.($item['model_name']??''));
                $upd=$conn->prepare("
                    UPDATE devices
                    SET status='Sold',
                        place='sold',
                        selling_price=?,
                        sold_at=NOW(),
                        sold_by=?,
                        owner_notes=COALESCE(NULLIF(?, ''), owner_notes)
                    WHERE serial_number=?
                ");
                $upd->execute([$sellingPrice,$salesPerson,$saleNotes,$serialPost]);
                $saleItemType='device';
            } else {
                $q=$conn->prepare("SELECT * FROM monitors WHERE serial_number=? AND inventory_owner=? AND status='In Stock' FOR UPDATE");
                $q->execute([$serialPost,$ownerKey]);
                $item=$q->fetch(PDO::FETCH_ASSOC);
                if(!$item) throw new Exception('Monitor was not found in stock for this inventory owner.');
                $description=trim(($item['manufacturer']??'').' '.($item['model_name']??'').' '.($item['size_inches']??'').' inch Monitor');
                $upd=$conn->prepare("
                    UPDATE monitors
                    SET status='Sold',
                        selling_price=?,
                        sold_at=NOW(),
                        sold_by=?,
                        owner_notes=COALESCE(NULLIF(?, ''), owner_notes)
                    WHERE serial_number=?
                ");
                $upd->execute([$sellingPrice,$salesPerson,$saleNotes,$serialPost]);
                $saleItemType='monitors';
            }

            $sale=$conn->prepare("
                INSERT INTO sales (
                    total_amount,
                    sale_status,
                    completed_at,
                    sold_by,
                    payment_method,
                    payment_status,
                    completion_status
                )
                VALUES (?,'completed',NOW(),?,?,?,'Completed')
            ");
            $sale->execute([$sellingPrice,$salesPerson,$paymentMethodDb,$paymentStatus]);
            $saleId=(int)$conn->lastInsertId();

            $si=$conn->prepare("INSERT INTO sale_items (sale_id,item_type,item_id,description,quantity,unit_price,sales_person) VALUES (?,?,?,?,1,?,?)");
            $si->execute([$saleId,$saleItemType,$serialPost,$description,$sellingPrice,$salesPerson]);

            $log=$conn->prepare("INSERT INTO activity_logs (user_id,action,details) VALUES (?,'Updated owner sale details',?)");
            $methodLabel=$paymentMethodDb??'Not specified';
            $notesLog=$saleNotes!==''?"; notes: $saleNotes":'';
            $log->execute([
                $user_id,
                "Marked $itemType SN: $serialPost as sold under $ownerLabel; salesperson: {$salesUser['full_name']}; price: KES ".
                number_format($sellingPrice,2).
                "; payment status: $paymentStatus; payment method: $methodLabel$notesLog; sale #$saleId"
            ]);

            $conn->commit();
            $_SESSION['owner_report_success']="Sale details updated successfully for $serialPost. Sale #$saleId created.";
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $_SESSION['owner_report_error']=$e->getMessage();
        }
    }
    $query=$_SERVER['QUERY_STRING']??'';
    $target=basename($_SERVER['PHP_SELF']).($query!==''?'?'.$query:'');
    header('Location: '.$target);
    exit;
}

$flashSuccess=$_SESSION['owner_report_success']??'';
$flashError=$_SESSION['owner_report_error']??'';
unset($_SESSION['owner_report_success'],$_SESSION['owner_report_error']);

$serial=trim($_GET['serial']??'');
$model=trim($_GET['model']??'');
$branch=trim($_GET['branch']??'');
$category=trim($_GET['category']??'');
$status=trim($_GET['status']??'');
$date_from=trim($_GET['date_from']??'');
$date_to=trim($_GET['date_to']??'');
if($reportMode==='overview'){
    if($date_from==='')$date_from=date('Y-m-01');
    if($date_to==='')$date_to=date('Y-m-d');
}

// Device records.
$sql="SELECT d.*,c.category_name,ua.full_name added_by_name,us.full_name sold_by_name FROM devices d LEFT JOIN categories c ON c.id=d.category_id LEFT JOIN users ua ON ua.id=d.added_by LEFT JOIN users us ON us.id=d.sold_by WHERE d.inventory_owner=:owner";
$params=['owner'=>$ownerKey];
if($reportMode==='instock')$sql.=" AND d.status='In Stock'";
elseif($reportMode==='sold')$sql.=" AND d.status='Sold'";
elseif($status!==''){$sql.=' AND d.status=:status';$params['status']=$status;}
if($role==='manager'&&$user_branch!==''){$sql.=' AND d.branch=:mb';$params['mb']=$user_branch;}
elseif($branch!==''){$sql.=' AND d.branch=:branch';$params['branch']=$branch;}
if($serial!==''){$sql.=' AND d.serial_number LIKE :serial';$params['serial']="%$serial%";}
if($model!==''){$sql.=' AND d.model_name LIKE :model';$params['model']="%$model%";}
if($category!=='' && $category!=='monitor'){$sql.=' AND d.category_id=:cat';$params['cat']=(int)$category;}
if($category==='monitor'){$sql.=' AND 1=0';}
if($reportMode==='overview'){
    if($date_from!==''){$sql.=' AND DATE(d.date_added)>=:df';$params['df']=$date_from;}
    if($date_to!==''){$sql.=' AND DATE(d.date_added)<=:dt';$params['dt']=$date_to;}
}
if($reportMode==='sold'){
    if($date_from!==''){$sql.=' AND DATE(d.sold_at)>=:df';$params['df']=$date_from;}
    if($date_to!==''){$sql.=' AND DATE(d.sold_at)<=:dt';$params['dt']=$date_to;}
}
$stmt=$conn->prepare($sql);$stmt->execute($params);$deviceRows=$stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($deviceRows as &$r){$r['_item_type']='device';$r['_sort_date']=$reportMode==='sold'?($r['sold_at']??''):($r['date_added']??'');}
unset($r);

// Monitor records. Category filter includes monitors only when All or Monitor is selected.
$monitorRows=[];
if($category==='' || $category==='monitor'){
    $msql="SELECT m.*,ua.full_name added_by_name,us.full_name sold_by_name FROM monitors m LEFT JOIN users ua ON ua.id=m.added_by LEFT JOIN users us ON us.id=m.sold_by WHERE m.inventory_owner=:owner";
    $mp=['owner'=>$ownerKey];
    if($reportMode==='instock')$msql.=" AND m.status='In Stock'";
    elseif($reportMode==='sold')$msql.=" AND m.status='Sold'";
    elseif($status!==''){$msql.=' AND m.status=:status';$mp['status']=$status;}
    if($role==='manager'&&$user_branch!==''){$msql.=' AND m.branch=:mb';$mp['mb']=$user_branch;}
    elseif($branch!==''){$msql.=' AND m.branch=:branch';$mp['branch']=$branch;}
    if($serial!==''){$msql.=' AND m.serial_number LIKE :serial';$mp['serial']="%$serial%";}
    if($model!==''){$msql.=' AND m.model_name LIKE :model';$mp['model']="%$model%";}
    if($reportMode==='overview'){
        if($date_from!==''){$msql.=' AND DATE(m.date_added)>=:df';$mp['df']=$date_from;}
        if($date_to!==''){$msql.=' AND DATE(m.date_added)<=:dt';$mp['dt']=$date_to;}
    }
    if($reportMode==='sold'){
        if($date_from!==''){$msql.=' AND DATE(m.sold_at)>=:df';$mp['df']=$date_from;}
        if($date_to!==''){$msql.=' AND DATE(m.sold_at)<=:dt';$mp['dt']=$date_to;}
    }
    $mst=$conn->prepare($msql);$mst->execute($mp);$monitorRows=$mst->fetchAll(PDO::FETCH_ASSOC);
    foreach($monitorRows as &$r){
        $r['_item_type']='monitor';
        $r['_sort_date']=$reportMode==='sold'?($r['sold_at']??''):($r['date_added']??'');
        $r['category_name']='Monitor';
        $r['processor']=null;$r['ram']=null;$r['storage_type']=null;$r['storage_capacity']=null;
        $r['touch']=null;$r['webcam']=null;
        if(empty($r['form_factor']))$r['form_factor']='Monitor'.(!empty($r['size_inches'])?' - '.$r['size_inches'].'"':'');
    }
    unset($r);
}

$devices=array_merge($deviceRows,$monitorRows);
usort($devices,function($a,$b){return strcmp((string)($b['_sort_date']??''),(string)($a['_sort_date']??''));});

$cats=$conn->query('SELECT id,category_name FROM categories ORDER BY category_name')->fetchAll(PDO::FETCH_ASSOC);
$salesPeople=$conn->query("SELECT id,full_name FROM users WHERE role='sales' AND is_active=1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

$maintenance=[];
if($reportMode==='overview'&&$deviceRows){
    $sns=array_column($deviceRows,'serial_number');
    $ph=implode(',',array_fill(0,count($sns),'?'));
    $m=$conn->prepare("SELECT m.*,u.full_name performed_by_name FROM maintenance m LEFT JOIN users u ON u.id=m.performed_by WHERE m.device_serial IN ($ph) ORDER BY m.date_performed ASC");
    $m->execute($sns);
    foreach($m->fetchAll(PDO::FETCH_ASSOC) as $r)$maintenance[$r['device_serial']][]=$r;
}
function ownerMaint($rows){if(!$rows)return 'No maintenance records';$out=[];foreach($rows as $r){$c=[];if($r['old_ram']!==null||$r['new_ram']!==null)$c[]='RAM: '.($r['old_ram']??'?').'GB → '.($r['new_ram']??'?').'GB';if($r['old_storage']!==null||$r['new_storage']!==null)$c[]='Storage: '.($r['old_storage']??'?').'GB → '.($r['new_storage']??'?').'GB';if(($r['old_graphics']??'')!==''||($r['new_graphics']??'')!=='')$c[]='Graphics: '.($r['old_graphics']?:'?').' → '.($r['new_graphics']?:'?');if(!empty($r['notes']))$c[]='Notes: '.$r['notes'];$out[]=date('d M Y H:i',strtotime($r['date_performed'])).' — '.implode('; ',$c).' — '.($r['performed_by_name']??'Unknown');}return implode("\n",$out);}
function ownerStorage($d){return !empty($d['storage_capacity'])?$d['storage_capacity'].'GB '.($d['storage_type']??''):'';}
function moneyCell($v){return $v===null||$v===''?'-':'KES '.number_format((float)$v,2);}
function dashCell($v){$v=trim((string)($v??''));return $v===''?'-':$v;}
function dollarCell($v){if($v===null||trim((string)$v)==='')return '-';$raw=str_replace([',','$'],'',trim((string)$v));return is_numeric($raw)?'$'.number_format((float)$raw,2):'$'.trim((string)$v);}
function ownerManufacturer($d){$stored=trim((string)($d['manufacturer']??''));if($stored!=='')return $stored;$model=strtoupper(trim((string)($d['model_name']??'')));if(preg_match('/\bHP\b|HEWLETT[ -]?PACKARD/',$model))return 'HP';if(preg_match('/\bDELL\b/',$model))return 'Dell';if(preg_match('/\bLENOVO\b|THINKPAD|THINKCENTRE|IDEAPAD/',$model))return 'Lenovo';if(preg_match('/\bTOSHIBA\b|DYNABOOK/',$model))return 'Toshiba';if(preg_match('/\bAPPLE\b|MACBOOK|IMAC/',$model))return 'Apple';if(preg_match('/\bASUS\b/',$model))return 'ASUS';if(preg_match('/\bACER\b/',$model))return 'Acer';return '-';}
function actualProfit($d){if(($d['selling_price']??null)===null||($d['selling_price']??'')===''||($d['buying_price']??null)===null||($d['buying_price']??'')==='')return null;return (float)$d['selling_price']-(float)$d['buying_price'];}
function imanSp($d){return ($d['selling_price']??null)!==null&&($d['selling_price']??'')!==''?$d['selling_price']:($d['price']??null);}
function ownerModelDisplay($d){$m=dashCell($d['model_name']??'');if(($d['_item_type']??'')==='monitor'&&!empty($d['size_inches']))$m.=' ('.rtrim(rtrim((string)$d['size_inches'],'0'),'.').'\")';return $m;}

$total=count($devices);$inStock=count(array_filter($devices,fn($d)=>$d['status']==='In Stock'));$sold=count(array_filter($devices,fn($d)=>$d['status']==='Sold'));$revenue=array_sum(array_map(fn($d)=>(float)($d['selling_price']??0),$devices));$pageTitle=$reportMode==='overview'?'Overview':($reportMode==='instock'?'In Stock':'Sold');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?=htmlspecialchars($ownerLabel.' '.$pageTitle)?> | Mombasa Computers</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"><style>
:root{--p:#1a4b2a;--bg:#f3f4f6;--b:#e5e7eb;--t:#1f2937;--m:#6b7280}*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:var(--bg);color:var(--t)}.main{margin-left:260px;padding:2rem;min-height:100vh}.head,.filters,.card,.tablebox{background:#fff;border:1px solid var(--b);border-radius:14px;box-shadow:0 1px 2px #0000000d}.head{padding:1.4rem 1.6rem;margin-bottom:1rem}.head h1{margin:0 0 .35rem;font-size:1.65rem}.tabs{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem}.tabs a{padding:.55rem .9rem;border-radius:8px;text-decoration:none;color:var(--p);border:1px solid var(--b);font-weight:600}.tabs a.active{background:var(--p);color:#fff}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin:1rem 0}.card{padding:1rem}.n{font-size:1.55rem;font-weight:800;color:var(--p)}.l{font-size:.78rem;color:var(--m)}.filters{padding:1rem;margin-bottom:1rem}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem}.g{display:flex;flex-direction:column;gap:.3rem}.g label{font-size:.78rem;color:var(--m);font-weight:600}.g input,.g select,.g textarea{padding:.62rem;border:1px solid #d1d5db;border-radius:8px;font:inherit}.g textarea{resize:vertical;min-height:90px}.filter-actions{display:flex;gap:.5rem;align-items:end;flex-wrap:wrap}.btn{padding:.58rem .78rem;border:0;border-radius:8px;background:var(--p);color:#fff;text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap;font-weight:650}.btn:disabled,.btn.disabled{opacity:.55;cursor:not-allowed}.secondary{background:#6b7280}.excel{background:#217346}.tablebox{overflow:hidden}.scroll{overflow:auto}table{border-collapse:separate;border-spacing:0;width:100%;min-width:1750px;font-size:.79rem}th,td{padding:.72rem .78rem;border-bottom:1px solid #edf0f2;text-align:left;vertical-align:middle;white-space:nowrap}th{position:sticky;top:0;z-index:2;background:#d7b729;color:#171717;font-weight:800;letter-spacing:.015em;border-bottom:2px solid #b99d1e}tbody tr:nth-child(even){background:#fafbfc}tbody tr:hover{background:#f5f8f6}.sn{font-family:monospace;font-weight:700}.badge{display:inline-block;padding:.22rem .52rem;background:#dcfce7;color:#166534;border-radius:999px;font-size:.72rem;font-weight:750}.badge.sold{background:#fee2e2;color:#b91c1c}.typebadge{display:inline-block;margin-left:.35rem;padding:.12rem .38rem;background:#e0e7ff;color:#3730a3;border-radius:999px;font-size:.62rem}.maint{white-space:pre-line!important;min-width:320px;max-width:420px;line-height:1.45}.notes{white-space:normal!important;min-width:180px;max-width:260px}.action-cell{min-width:165px;text-align:center}.num{text-align:center;color:#6b7280;font-weight:700}.money{font-variant-numeric:tabular-nums;font-weight:650}.profit-positive{color:#047857}.profit-negative{color:#b91c1c}.empty{padding:3rem;text-align:center;color:var(--m)}.flash{padding:.9rem 1rem;border-radius:10px;margin-bottom:1rem}.flash.ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}.flash.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}.modal-bg{display:none;position:fixed;inset:0;background:#0008;z-index:5000;align-items:center;justify-content:center;padding:1rem}.modal-bg.open{display:flex}.modal{background:#fff;border-radius:14px;width:min(520px,100%);box-shadow:0 20px 60px #0005}.modal-head{padding:1rem 1.2rem;border-bottom:1px solid var(--b);display:flex;justify-content:space-between;align-items:center}.modal-body{padding:1.2rem}.modal-body .g{margin-bottom:1rem}.close{border:0;background:none;font-size:1.4rem;cursor:pointer}.modal-actions{display:flex;justify-content:flex-end;gap:.6rem;margin-top:1rem}@media(max-width:1200px){.main{margin-left:0;padding:5rem 1rem 1rem}}
</style></head><body><?php include __DIR__.'/sidebar.php';?><main class="main"><section class="head"><h1><i class="fas fa-boxes-stacked"></i> <?=htmlspecialchars($ownerLabel)?> — <?=htmlspecialchars($pageTitle)?></h1><div style="color:var(--m)">Owner-specific inventory, including devices and monitors.</div><div class="tabs"><a class="<?=$reportMode==='overview'?'active':''?>" href="overview.php">Overview</a><a class="<?=$reportMode==='instock'?'active':''?>" href="instock.php">In Stock</a><a class="<?=$reportMode==='sold'?'active':''?>" href="sold.php">Sold</a></div></section>
<?php if($flashSuccess):?><div class="flash ok"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($flashSuccess)?></div><?php endif;?><?php if($flashError):?><div class="flash err"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($flashError)?></div><?php endif;?>
<section class="stats"><div class="card"><div class="n"><?=$total?></div><div class="l"><?= $reportMode==='overview'?'TOTAL ITEMS':'MATCHING ITEMS' ?></div></div><?php if($reportMode==='overview'):?><div class="card"><div class="n"><?=$inStock?></div><div class="l">IN STOCK</div></div><div class="card"><div class="n"><?=$sold?></div><div class="l">SOLD</div></div><?php endif;?><?php if($reportMode==='sold'):?><div class="card"><div class="n">KES <?=number_format($revenue,2)?></div><div class="l">ACTUAL SALES VALUE</div></div><?php endif;?></section>
<section class="filters"><form class="grid" method="get"><div class="g"><label>Serial</label><input name="serial" value="<?=htmlspecialchars($serial)?>"></div><div class="g"><label>Model</label><input name="model" value="<?=htmlspecialchars($model)?>"></div><div class="g"><label>Category</label><select name="category"><option value="">All</option><option value="monitor" <?=$category==='monitor'?'selected':''?>>Monitor</option><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=$category===(string)$c['id']?'selected':''?>><?=htmlspecialchars($c['category_name'])?></option><?php endforeach;?></select></div><?php if($role!=='manager'):?><div class="g"><label>Branch</label><select name="branch"><option value="">All</option><option <?=$branch==='KIMATHI'?'selected':''?>>KIMATHI</option><option <?=$branch==='MOI'?'selected':''?>>MOI</option><option <?=$branch==='WAREHOUSE'?'selected':''?>>WAREHOUSE</option></select></div><?php endif;?><?php if($reportMode==='overview'):?><div class="g"><label>Status</label><select name="status"><option value="">All</option><option <?=$status==='In Stock'?'selected':''?>>In Stock</option><option <?=$status==='Sold'?'selected':''?>>Sold</option></select></div><?php endif;?><?php if($reportMode==='overview'||$reportMode==='sold'):?><div class="g"><label><?=$reportMode==='sold'?'Sold From':'Added From'?></label><input type="date" name="date_from" value="<?=htmlspecialchars($date_from)?>"></div><div class="g"><label><?=$reportMode==='sold'?'Sold To':'Added To'?></label><input type="date" name="date_to" value="<?=htmlspecialchars($date_to)?>"></div><?php endif;?><div class="filter-actions"><button class="btn"><i class="fas fa-search"></i> Filter</button><a class="btn secondary" href="<?=$reportMode?>.php">Reset</a><a class="btn excel" href="export_excel.php?<?=htmlspecialchars(http_build_query(array_merge($_GET,['report'=>$reportMode])))?>"><i class="fas fa-file-excel"></i> Export Excel</a></div></form></section>
<section class="tablebox"><div class="scroll"><?php if(!$devices):?><div class="empty">No records found.</div><?php else:?><table><thead><tr>
<?php if($ownerKey==='imans_hustle'):?><?php foreach(['Asset ID','MFG','Model','Form Factor','CPU','RAM','Storage','Serial','Grade','B.P','S.P','PROFIT','NOTES'] as $h):?><th><?=$h?></th><?php endforeach;?><?php else:?><th>#</th><?php foreach(['Asset ID','Buying $','Selling $','BP','SP','PROFIT','MFG','Model','CPU','RAM','Storage','Serial #','Grade','Touch Screen','Webcam','Notes','LOCATION'] as $h):?><th><?=$h?></th><?php endforeach;?><?php endif;?><th>Status</th><?php if($reportMode==='overview'):?><th>Maintenance / Changes</th><?php endif;?><?php if($reportMode==='sold'):?><th>Sold By</th><th>Sold At</th><th>Actual Selling Price</th><?php endif;?><th>Action</th></tr></thead><tbody>
<?php foreach($devices as $rowIndex=>$d):?><tr>
<?php if($ownerKey==='imans_hustle'):?><td><?=htmlspecialchars(dashCell($d['asset_id']??''))?></td><td><?=htmlspecialchars(ownerManufacturer($d))?></td><td><strong><?=htmlspecialchars(ownerModelDisplay($d))?></strong><?php if(($d['_item_type']??'')==='monitor'):?><span class="typebadge">MONITOR</span><?php endif;?></td><td><?=htmlspecialchars(dashCell($d['form_factor']??''))?></td><td><?=htmlspecialchars(dashCell($d['processor']??''))?></td><td><?=!empty($d['ram'])?htmlspecialchars((string)$d['ram']).' GB':'-'?></td><td><?=htmlspecialchars(ownerStorage($d)?:'-')?></td><td class="sn"><?=htmlspecialchars($d['serial_number'])?></td><td><?=htmlspecialchars(dashCell($d['grade']??''))?></td><td><?=moneyCell($d['buying_price']??null)?></td><td><?=moneyCell(imanSp($d))?></td><?php $hp=actualProfit($d);?><td class="money <?=$hp!==null&&$hp<0?'profit-negative':'profit-positive'?>"><?=moneyCell($hp!==null?$hp:($d['owner_profit']??null))?></td><td class="notes"><?=htmlspecialchars(dashCell($d['owner_notes']??''))?></td>
<?php else:?><?php $profit=actualProfit($d);?><td class="num"><?=($rowIndex+1)?></td><td><?=htmlspecialchars(dashCell($d['asset_id']??''))?></td><td class="money"><?=htmlspecialchars(dollarCell($d['symetic']??null))?></td><td class="money"><?=htmlspecialchars(dollarCell($d['dollar_value']??null))?></td><td class="money"><?=moneyCell($d['buying_price']??null)?></td><td class="money"><?=moneyCell(imanSp($d))?></td><td class="money <?=$profit!==null&&$profit<0?'profit-negative':'profit-positive'?>"><?=moneyCell($profit)?></td><td><?=htmlspecialchars(ownerManufacturer($d))?></td><td><strong><?=htmlspecialchars(ownerModelDisplay($d))?></strong><?php if(($d['_item_type']??'')==='monitor'):?><span class="typebadge">MONITOR</span><?php endif;?></td><td><?=htmlspecialchars(dashCell($d['processor']??''))?></td><td><?=!empty($d['ram'])?htmlspecialchars((string)$d['ram']).' GB':'-'?></td><td><?=htmlspecialchars(ownerStorage($d)?:'-')?></td><td class="sn"><?=htmlspecialchars(dashCell($d['serial_number']??''))?></td><td><?=htmlspecialchars(dashCell($d['grade']??''))?></td><td><?=htmlspecialchars(dashCell($d['touch']??''))?></td><td><?=htmlspecialchars(dashCell($d['webcam']??''))?></td><td class="notes"><?=htmlspecialchars(dashCell($d['owner_notes']??''))?></td><td><?=htmlspecialchars(dashCell($d['owner_location']??($d['branch']??'')))?></td><?php endif;?>
<td><span class="badge <?=$d['status']==='Sold'?'sold':''?>"><?=htmlspecialchars($d['status'])?></span></td><?php if($reportMode==='overview'):?><td class="maint"><?=($d['_item_type']??'')==='device'?htmlspecialchars(ownerMaint($maintenance[$d['serial_number']]??[])):'-'?></td><?php endif;?><?php if($reportMode==='sold'):?><td><?=htmlspecialchars($d['sold_by_name']??'Unknown')?></td><td><?=$d['sold_at']?date('d M Y H:i',strtotime($d['sold_at'])):'—'?></td><td><?=moneyCell($d['selling_price']??null)?></td><?php endif;?><td class="action-cell"><?php if(($d['status']??'')==='In Stock'):?><button type="button" class="btn sale-btn" data-item-type="<?=htmlspecialchars($d['_item_type'])?>" data-serial="<?=htmlspecialchars($d['serial_number'])?>" data-model="<?=htmlspecialchars(ownerModelDisplay($d))?>"><i class="fas fa-receipt"></i> Update Sale Details</button><?php else:?><button type="button" class="btn disabled" disabled><i class="fas fa-check"></i> Sold</button><?php endif;?></td></tr><?php endforeach;?></tbody></table><?php endif;?></div></section></main>
<div class="modal-bg" id="saleModal"><div class="modal"><div class="modal-head"><div><strong>Update Sale Details</strong><div id="saleItemLabel" style="font-size:.8rem;color:var(--m);margin-top:.2rem"></div></div><button type="button" class="close" id="closeModal">&times;</button></div><form method="post" class="modal-body"><input type="hidden" name="update_sale_details" value="1"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['owner_report_csrf'])?>"><input type="hidden" name="item_type" id="modalItemType"><input type="hidden" name="serial_number" id="modalSerial"><div class="g"><label>Sales Person</label><select name="sales_person" required><option value="">-- Select Sales Person --</option><?php foreach($salesPeople as $sp):?><option value="<?=$sp['id']?>"><?=htmlspecialchars($sp['full_name'])?></option><?php endforeach;?></select></div><div class="g"><label>Selling Price (KES)</label><input type="number" name="selling_price" min="0.01" step="0.01" required></div><div class="g"><label>Payment Status</label><select name="payment_status" required><option value="">-- Select --</option><option value="paid">Paid</option><option value="unpaid">Unpaid</option></select></div><div class="g"><label>Payment Method <span style="font-weight:400;color:var(--m)">(Optional)</span></label><select name="payment_method"><option value="">-- Not specified --</option><option value="cash">Cash</option><option value="mpesa-till">M-Pesa Till</option><option value="mpesa-pochi">M-Pesa Pochi</option><option value="bank-transfer">Bank Transfer</option></select></div><div class="g"><label>Notes <span style="font-weight:400;color:var(--m)">(Optional)</span></label><textarea name="sale_notes" placeholder="Enter sale notes, reference, customer details, or any other relevant note"></textarea></div><div class="modal-actions"><button type="button" class="btn secondary" id="cancelModal">Cancel</button><button type="submit" class="btn"><i class="fas fa-save"></i> Save Sale Details</button></div></form></div></div>
<script>const modal=document.getElementById('saleModal');const close=()=>modal.classList.remove('open');document.querySelectorAll('.sale-btn').forEach(b=>b.addEventListener('click',()=>{document.getElementById('modalItemType').value=b.dataset.itemType;document.getElementById('modalSerial').value=b.dataset.serial;document.getElementById('saleItemLabel').textContent=b.dataset.model+' — SN: '+b.dataset.serial;modal.classList.add('open')}));document.getElementById('closeModal').addEventListener('click',close);document.getElementById('cancelModal').addEventListener('click',close);modal.addEventListener('click',e=>{if(e.target===modal)close()});</script></body></html>
