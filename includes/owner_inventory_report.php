<?php
if (!isset($ownerKey,$ownerLabel,$reportMode)) die('Inventory report configuration missing.');
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/auth_check.php';
require_once __DIR__.'/owner_inventory_access.php';

$access=requireOwnerInventoryAccess($conn);
$user_id=(int)$access['user_id'];

$config=$ownerKey==='imans_hustle'
    ? ['table'=>'iman_hustle_items','type'=>'hustle','folder'=>'imanhus']
    : ['table'=>'iman_inventory_items','type'=>'inventory','folder'=>'imaninv'];

$table=$config['table'];
$type=$config['type'];

function ownDash($v){$v=trim((string)($v??''));return $v===''?'-':$v;}
function ownMoneyRaw($v){return $v===null||$v===''?'-':'KES '.number_format((float)$v,2);}
function ownUsdRaw($v){return $v===null||$v===''?'-':'$'.number_format((float)$v,2);}
function ownProfit($d){
    if(($d['selling_price']??null)!==null&&($d['buying_price']??null)!==null)return(float)$d['selling_price']-(float)$d['buying_price'];
    if(($d['planned_selling_price']??null)!==null&&($d['buying_price']??null)!==null)return(float)$d['planned_selling_price']-(float)$d['buying_price'];
    return null;
}
function moneyCell($raw){
    return '<span class="money-value" data-money="'.htmlspecialchars((string)$raw,ENT_QUOTES,'UTF-8').'">.......</span>';
}
function ownerQueryString(array $changes=[]){
    $base=$_GET;
    unset($base['ajax_search'],$base['field'],$base['term']);
    foreach($changes as $k=>$v){
        if($v===null)unset($base[$k]); else $base[$k]=$v;
    }
    return http_build_query(array_filter($base,fn($v)=>$v!==''));
}

if(empty($_SESSION['owner_report_csrf']))$_SESSION['owner_report_csrf']=bin2hex(random_bytes(32));

/*
 * Resolve the actual database column names used by this owner table.
 * Older owner inventory tables may use serial/model aliases instead of
 * serial_number/model_name. The report and AJAX search use the real columns.
 */
$ownerColumnAliases=[
    'serial_number'=>['serial_number','serial','serial_no','serialnumber'],
    'model_name'=>['model_name','model'],
    'item_type'=>['item_type','type'],
    'status'=>['status'],
    'location'=>['location','owner_location','branch'],
    'date_added'=>['date_added','created_at','added_at'],
    'sold_at'=>['sold_at','date_sold']
];

$ownerAvailableColumns=[];
try{
    $columnStmt=$conn->query("SHOW COLUMNS FROM `$table`");
    foreach($columnStmt->fetchAll(PDO::FETCH_ASSOC) as $columnRow){
        $columnName=(string)($columnRow['Field']??'');
        if($columnName!=='')$ownerAvailableColumns[strtolower($columnName)]=$columnName;
    }
}catch(Throwable $e){
    $ownerAvailableColumns=[];
}

function ownerActualColumn(array $available,array $aliases):?string{
    foreach($aliases as $alias){
        $key=strtolower($alias);
        if(isset($available[$key]))return $available[$key];
    }
    return null;
}

$ownerColumns=[];
foreach($ownerColumnAliases as $canonical=>$aliases){
    $ownerColumns[$canonical]=ownerActualColumn($ownerAvailableColumns,$aliases);
}

/* Full-table AJAX search, independent of pagination/date filters. */
if(isset($_GET['ajax_search'])){
    // Prevent any accidental buffered HTML/warnings from corrupting the JSON body.
    while(ob_get_level()>0)ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    try{
        $field=$_GET['field']??'';
        $term=trim((string)($_GET['term']??''));

        if(!in_array($field,['serial_number','model_name'],true)){
            throw new Exception('Invalid search field.');
        }

        if(strlen($term)<2){
            echo json_encode(['ok'=>true,'results'=>[]],JSON_UNESCAPED_UNICODE);
            exit;
        }

        $searchColumn=$ownerColumns[$field]??null;
        if($searchColumn===null){
            throw new Exception('The '.$field.' column could not be found in this owner inventory table.');
        }

        $serialColumn=$ownerColumns['serial_number']??null;
        $modelColumn=$ownerColumns['model_name']??null;
        $itemTypeColumn=$ownerColumns['item_type']??null;
        $statusColumn=$ownerColumns['status']??null;
        $locationColumn=$ownerColumns['location']??null;

        $serialSelect=$serialColumn!==null?"`$serialColumn` AS serial_number":"NULL AS serial_number";
        $modelSelect=$modelColumn!==null?"`$modelColumn` AS model_name":"NULL AS model_name";
        $itemTypeSelect=$itemTypeColumn!==null?"`$itemTypeColumn` AS item_type":"NULL AS item_type";
        $statusSelect=$statusColumn!==null?"`$statusColumn` AS status":"NULL AS status";
        $locationSelect=$locationColumn!==null?"`$locationColumn` AS location":"NULL AS location";

        $sql="SELECT id,$serialSelect,$modelSelect,$itemTypeSelect,$statusSelect,$locationSelect
              FROM `$table`
              WHERE `$searchColumn` LIKE :term
              ORDER BY id DESC
              LIMIT 20";

        $st=$conn->prepare($sql);
        $st->execute(['term'=>$term.'%']);

        echo json_encode([
            'ok'=>true,
            'results'=>$st->fetchAll(PDO::FETCH_ASSOC)
        ],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    }catch(Throwable $e){
        http_response_code(500);
        echo json_encode([
            'ok'=>false,
            'error'=>'Search failed: '.$e->getMessage(),
            'results'=>[]
        ],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}

/* Update Sale Details: available for any In Stock item, including monitors. */
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['update_sale_details'])){
    try{
        if(!hash_equals($_SESSION['owner_report_csrf'],(string)($_POST['csrf_token']??'')))throw new Exception('Security validation failed. Refresh the page and try again.');

        $itemId=(int)($_POST['item_id']??0);
        $salesPerson=trim((string)($_POST['sales_person']??''));
        $sellingPrice=(float)($_POST['selling_price']??0);
        $paymentStatus=strtolower(trim((string)($_POST['payment_status']??'')));

        if($itemId<=0)throw new Exception('Invalid item.');
        if($salesPerson==='')throw new Exception('Please enter the sales person.');
        if($sellingPrice<=0)throw new Exception('Please enter a valid selling price.');
        if(!in_array($paymentStatus,['paid','unpaid'],true))throw new Exception('Please select Paid or Unpaid.');

        $conn->beginTransaction();
        $st=$conn->prepare("SELECT * FROM `$table` WHERE id=? FOR UPDATE");
        $st->execute([$itemId]);
        $item=$st->fetch(PDO::FETCH_ASSOC);

        if(!$item)throw new Exception('Item was not found.');
        if(($item['status']??'')!=='In Stock')throw new Exception('Update Sale Details is only available for items that are currently In Stock.');

        $up=$conn->prepare("UPDATE `$table`
                            SET status='Sold',sales_person=?,selling_price=?,payment_status=?,sold_at=NOW()
                            WHERE id=?");
        $up->execute([$salesPerson,$sellingPrice,$paymentStatus,$itemId]);

        $serialLabel=trim((string)($item['serial_number']??''))?:'#'.$itemId;
        try{
            $log=$conn->prepare("INSERT INTO activity_logs(user_id,action,details)VALUES(?,'Updated owner sale details',?)");
            $log->execute([$user_id,"{$ownerLabel} item {$serialLabel} marked Sold; sales person: {$salesPerson}; price: KES ".number_format($sellingPrice,2)."; payment: {$paymentStatus}"]);
        }catch(Throwable $e){}

        $conn->commit();
        $_SESSION['owner_report_success']="Sale details updated successfully for {$serialLabel}.";
    }catch(Throwable $e){
        if($conn->inTransaction())$conn->rollBack();
        $_SESSION['owner_report_error']=$e->getMessage();
    }
    $qs=$_SERVER['QUERY_STRING']??'';
    header('Location: '.basename($_SERVER['PHP_SELF']).($qs!==''?'?'.$qs:''));
    exit;
}

$flashSuccess=$_SESSION['owner_report_success']??'';
$flashError=$_SESSION['owner_report_error']??'';
unset($_SESSION['owner_report_success'],$_SESSION['owner_report_error']);

$serial=trim((string)($_GET['serial']??''));
$model=trim((string)($_GET['model']??''));
$location=strtoupper(trim((string)($_GET['location']??'')));
$status=trim((string)($_GET['status']??''));

$allowedPerPage=[100,200,300,400,500];
$perPage=(int)($_GET['per_page']??100);
if(!in_array($perPage,$allowedPerPage,true))$perPage=100;
$page=max(1,(int)($_GET['page']??1));

$hasDateFrom=array_key_exists('date_from',$_GET);
$hasDateTo=array_key_exists('date_to',$_GET);
$date_from=trim((string)($_GET['date_from']??''));
$date_to=trim((string)($_GET['date_to']??''));

$isSearch=$serial!==''||$model!=='';
if($isSearch){
    // Serial/model searches intentionally ignore the month window and search the entire table.
    $date_from='';
    $date_to='';
}else{
    if(!$hasDateFrom||$date_from==='')$date_from=date('Y-m-01');
    if(!$hasDateTo||$date_to==='')$date_to=date('Y-m-d');
}

$where=['1=1'];
$params=[];

if($reportMode==='instock' && !empty($ownerColumns['status']))$where[]="`{$ownerColumns['status']}`='In Stock'";
elseif($reportMode==='sold' && !empty($ownerColumns['status']))$where[]="`{$ownerColumns['status']}`='Sold'";
elseif($status!=='' && !empty($ownerColumns['status'])){$where[]="`{$ownerColumns['status']}`=:status";$params['status']=$status;}

if($serial!==''){$where[]='serial_number LIKE :serial';$params['serial']=$serial.'%';}
if($model!==''){$where[]='model_name LIKE :model';$params['model']=$model.'%';}
if($location!==''&&in_array($location,['KIMATHI','MOI','WAREHOUSE'],true)&&!empty($ownerColumns['location'])){$where[]="`{$ownerColumns['location']}`=:location";$params['location']=$location;}

$dateColumn=$reportMode==='sold'?'sold_at':'date_added';
if($date_from!==''){$where[]="$dateColumn>=:df_start";$params['df_start']=$date_from.' 00:00:00';}
if($date_to!==''){$where[]="$dateColumn<:dt_end";$params['dt_end']=date('Y-m-d',strtotime($date_to.' +1 day')).' 00:00:00';}

$whereSql=implode(' AND ',$where);

/* Fast aggregate query: no full result fetch for cards/counts. */
$sumSql="SELECT COUNT(*) total_rows,
                COALESCE(SUM(status='In Stock'),0) in_stock_rows,
                COALESCE(SUM(status='Sold'),0) sold_rows,
                COALESCE(SUM(buying_price),0) buying_value,
                COALESCE(SUM(planned_selling_price),0) planned_value,
                COALESCE(SUM(selling_price),0) revenue,
                COALESCE(SUM(CASE WHEN selling_price IS NOT NULL AND buying_price IS NOT NULL THEN selling_price-buying_price ELSE 0 END),0) realized_profit
         FROM `$table` WHERE $whereSql";
$sumStmt=$conn->prepare($sumSql);
$sumStmt->execute($params);
$sum=$sumStmt->fetch(PDO::FETCH_ASSOC)?:[];

$total=(int)($sum['total_rows']??0);
$inStock=(int)($sum['in_stock_rows']??0);
$sold=(int)($sum['sold_rows']??0);
$buyingValue=(float)($sum['buying_value']??0);
$plannedValue=(float)($sum['planned_value']??0);
$revenue=(float)($sum['revenue']??0);
$realizedProfit=(float)($sum['realized_profit']??0);

$totalPages=max(1,(int)ceil($total/$perPage));
if($page>$totalPages)$page=$totalPages;
$offset=($page-1)*$perPage;

$orderColumn=$reportMode==='sold'?'sold_at':'date_added';
$listSql="SELECT * FROM `$table`
          WHERE $whereSql
          ORDER BY $orderColumn DESC,id DESC
          LIMIT :limit OFFSET :offset";
$st=$conn->prepare($listSql);
foreach($params as $k=>$v)$st->bindValue(':'.$k,$v);
$st->bindValue(':limit',$perPage,PDO::PARAM_INT);
$st->bindValue(':offset',$offset,PDO::PARAM_INT);
$st->execute();
$items=$st->fetchAll(PDO::FETCH_ASSOC);

/* Only fetch maintenance for visible rows. */
$maintenance=[];
if($reportMode==='overview'&&$items){
    $ids=array_map('intval',array_column($items,'id'));
    $ph=implode(',',array_fill(0,count($ids),'?'));
    try{
        $m=$conn->prepare("SELECT om.*,u.full_name performed_by_name
                           FROM owner_inventory_maintenance om
                           LEFT JOIN users u ON u.id=om.performed_by
                           WHERE om.owner_key=? AND om.item_id IN ($ph)
                           ORDER BY om.date_performed ASC");
        $m->execute(array_merge([$ownerKey],$ids));
        foreach($m->fetchAll(PDO::FETCH_ASSOC)as$row)$maintenance[(int)$row['item_id']][]=$row;
    }catch(Throwable $e){}
}
function ownMaintenanceText($rows){
    if(!$rows)return'-';
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

$pageTitle=$reportMode==='overview'?'Overview':($reportMode==='instock'?'In Stock':'Sold');
$fromRow=$total===0?0:$offset+1;
$toRow=min($offset+$perPage,$total);
$exportQuery=http_build_query(array_filter([
    'serial'=>$serial,'model'=>$model,'location'=>$location,'status'=>$status,
    'date_from'=>$date_from,'date_to'=>$date_to
],fn($v)=>$v!==''));
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
.badge{display:inline-block;padding:.22rem .52rem;background:#dcfce7;color:#166534;border-radius:999px;font-weight:750}.badge.sold{background:#fee2e2;color:#b91c1c}.undersold{display:inline-block;margin-left:.35rem;padding:.18rem .42rem;border-radius:999px;background:#fee2e2;color:#b91c1c;font-size:.65rem;font-weight:800}.type{font-size:.65rem;padding:.12rem .35rem;border-radius:999px;background:#e0e7ff;color:#3730a3;margin-left:.3rem}.notes,.maint{white-space:pre-line!important;max-width:300px;line-height:1.4}.action-cell{display:flex;gap:.35rem;align-items:center}.mini{padding:.4rem .55rem;font-size:.7rem}
.flash{padding:.9rem 1rem;border-radius:10px;margin:1rem 0}.ok{background:#ecfdf5;color:#065f46}.err{background:#fef2f2;color:#991b1b}.empty{padding:3rem;text-align:center;color:var(--m)}
.modal-bg{display:none;position:fixed;inset:0;background:#0008;z-index:5000;align-items:center;justify-content:center;padding:1rem}.modal-bg.open{display:flex}.modal{background:#fff;border-radius:14px;width:min(480px,100%);box-shadow:0 20px 60px #0005}.modal-head{padding:1rem 1.2rem;border-bottom:1px solid var(--b);display:flex;justify-content:space-between;align-items:center}.modal-body{padding:1.2rem}.modal-body .g{margin-bottom:1rem}.close{background:none;border:0;font-size:1.4rem;cursor:pointer}.modal-actions{display:flex;justify-content:flex-end;gap:.6rem}
@media(max-width:1200px){.main{margin-left:0;padding:5rem 1rem 1rem}}@media(max-width:700px){.main{padding:4.5rem .75rem .75rem}.quick .btn{flex:1;justify-content:center}}

.money-toggle{background:#111827}.money-value{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.03em}
.pagination-wrap{background:#fff;border:1px solid var(--b);border-radius:14px;box-shadow:0 1px 2px #0000000d;margin-top:1rem;padding:1rem 1.2rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}
.page-info{color:var(--m);font-size:.82rem}.pagination{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap}
.page-link{min-width:38px;height:38px;padding:0 .7rem;border:1px solid var(--b);border-radius:9px;background:#fff;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;font-weight:650;font-size:.84rem}
.page-link.active{background:#163867;color:#fff;border-color:#163867}.page-link.disabled{opacity:.4;pointer-events:none}
.per-page{display:flex;align-items:center;gap:.45rem;font-size:.82rem;color:var(--m)}.per-page select{padding:.48rem .6rem;border:1px solid #d1d5db;border-radius:8px;background:#fff}

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
        This page shows the current month's records by default. Search by Serial Number or Model to find records across the full inventory, including older items. Results are paginated to keep the system responsive as the inventory grows.
      </div>
    </div>
    <div class="quick">
      <button class="btn money-toggle" type="button" id="moneyToggle"><i class="fas fa-eye"></i> View Summary Money</button>
      <a class="btn" href="add_device.php"><i class="fas fa-plus"></i> Add Item</a>
      <a class="btn secondary" href="bulk_upload.php"><i class="fas fa-file-excel"></i> Bulk Upload</a>
      <a class="btn excel" href="export_excel.php?report=<?=urlencode($reportMode)?>&<?=htmlspecialchars($exportQuery)?>"><i class="fas fa-download"></i> Export Excel</a>
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
  <div class="card"><div class="n"><?=moneyCell('KES '.number_format($buyingValue,2))?></div><div class="l">Buying value</div></div>
  <div class="card"><div class="n"><?=moneyCell('KES '.number_format($plannedValue,2))?></div><div class="l">Planned selling value</div></div>
  <?php if($reportMode==='sold'):?>
  <div class="card"><div class="n"><?=moneyCell('KES '.number_format($revenue,2))?></div><div class="l">Actual sales</div></div>
  <div class="card"><div class="n"><?=moneyCell('KES '.number_format($realizedProfit,2))?></div><div class="l">Realized profit</div></div>
  <?php endif;?>
</section>

<section class="filters">
<form class="grid" method="get" id="filterForm">
  <input type="hidden" name="page" value="1">
  <div class="g"><label>Serial Number</label><input id="serialSearch" name="serial" autocomplete="off" value="<?=htmlspecialchars($serial)?>" placeholder="Type serial number..."><div id="serialSuggestions" class="suggestions"></div></div>
  <div class="g"><label>Model</label><input id="modelSearch" name="model" autocomplete="off" value="<?=htmlspecialchars($model)?>" placeholder="Type model name..."><div id="modelSuggestions" class="suggestions"></div></div>
  <div class="g"><label>Location</label><select name="location"><option value="">All locations</option><?php foreach(['KIMATHI','MOI','WAREHOUSE'] as$l):?><option value="<?=$l?>" <?=$location===$l?'selected':''?>><?=$l?></option><?php endforeach;?></select></div>
  <?php if($reportMode==='overview'):?><div class="g"><label>Status</label><select name="status"><option value="">All</option><option value="In Stock" <?=$status==='In Stock'?'selected':''?>>In Stock</option><option value="Sold" <?=$status==='Sold'?'selected':''?>>Sold</option></select></div><?php endif;?>
  <div class="g"><label><?=$reportMode==='sold'?'Sold From':'Added From'?></label><input type="date" name="date_from" value="<?=htmlspecialchars($date_from)?>"></div>
  <div class="g"><label><?=$reportMode==='sold'?'Sold To':'Added To'?></label><input type="date" name="date_to" value="<?=htmlspecialchars($date_to)?>"></div>
  <div class="g"><label>Items Per Page</label><select name="per_page"><?php foreach($allowedPerPage as$size):?><option value="<?=$size?>" <?=$perPage===$size?'selected':''?>><?=$size?></option><?php endforeach;?></select></div>
  <div class="filter-actions"><button class="btn" type="submit"><i class="fas fa-filter"></i> Filter</button><a class="btn secondary" href="<?=basename($_SERVER['PHP_SELF'])?>">Reset</a></div>
</form>
</section>

<section class="tablebox"><div class="scroll">
<?php if(!$items):?><div class="empty"><i class="fas fa-box-open fa-2x"></i><p>No matching items found.</p></div><?php else:?>
<table>
<thead><tr>
<th>#</th>
<?php if($type==='hustle'):?>
<th>Asset ID</th><th>MFG</th><th>Model</th><th>Type</th><th>Form Factor</th><th>CPU</th><th>RAM</th><th>Storage</th><th>Serial</th><th>Grade</th><th>B.P</th><th>S.P</th><?php if($reportMode!=='instock'):?><th>Profit</th><?php endif;?><th>Notes</th><th>Location</th>
<?php else:?>
<th>Asset ID</th><th>Buying $</th><th>Selling $</th><th>BP</th><th>SP</th><?php if($reportMode!=='instock'):?><th>Profit</th><?php endif;?><th>MFG</th><th>Model</th><th>Type</th><th>CPU</th><th>RAM</th><th>Storage</th><th>Serial #</th><th>Grade</th><th>Touch</th><th>Webcam</th><th>Notes</th><th>Location</th>
<?php endif;?>
<th>Status</th>
<?php if($reportMode==='overview'):?><th>Maintenance / Changes</th><?php endif;?>
<?php if($reportMode==='sold'):?><th>Sales Person</th><th>Sold At</th><th>Actual Selling Price</th><th>Payment</th><?php endif;?>
<th>Actions</th>
</tr></thead>
<tbody>
<?php foreach($items as$i=>$d):
$p=ownProfit($d);
$isRowSold=(($d['status']??'')==='Sold');
$rowProfit=($reportMode==='overview' && !$isRowSold)?null:$p;
$isUndersold=$isRowSold
    && ($d['selling_price']??null)!==null
    && ($d['planned_selling_price']??null)!==null
    && (float)$d['selling_price'] < (float)$d['planned_selling_price'];
?>
<tr>
<td><?=($offset+$i+1)?></td>
<?php if($type==='hustle'):?>
<td><?=htmlspecialchars(ownDash($d['asset_id']))?></td><td><?=htmlspecialchars(ownDash($d['manufacturer']))?></td>
<td><?=htmlspecialchars(ownDash($d['model_name']))?></td><td><?=htmlspecialchars(ownDash($d['item_type']))?></td>
<td><?=htmlspecialchars(ownDash($d['form_factor']))?></td><td><?=htmlspecialchars(ownDash($d['processor']))?></td>
<td><?=htmlspecialchars(ownDash($d['ram']))?></td><td><?=htmlspecialchars(ownDash($d['storage']))?></td>
<td><?=htmlspecialchars(ownDash($d['serial_number']))?></td><td><?=htmlspecialchars(ownDash($d['grade']))?></td>
<td><?=htmlspecialchars(ownMoneyRaw($d['buying_price']??null))?></td><td><?=htmlspecialchars(ownMoneyRaw($d['planned_selling_price']??null))?></td>
<?php if($reportMode!=='instock'):?>
<td>
  <?=($reportMode==='overview' && !$isRowSold)?'':htmlspecialchars($rowProfit===null?'-':ownMoneyRaw($rowProfit))?>
  <?php if($isUndersold && $reportMode==='overview'):?><span class="undersold">Undersold</span><?php endif;?>
</td>
<?php endif;?>
<td class="notes"><?=htmlspecialchars(ownDash($d['notes']))?></td><td><?=htmlspecialchars(ownDash($d['location']))?></td>
<?php else:?>
<td><?=htmlspecialchars(ownDash($d['asset_id']))?></td><td><?=htmlspecialchars(ownUsdRaw($d['buying_usd']??null))?></td><td><?=htmlspecialchars(ownUsdRaw($d['selling_usd']??null))?></td>
<td><?=htmlspecialchars(ownMoneyRaw($d['buying_price']??null))?></td><td><?=htmlspecialchars(ownMoneyRaw($d['planned_selling_price']??null))?></td>
<?php if($reportMode!=='instock'):?>
<td>
  <?=($reportMode==='overview' && !$isRowSold)?'':htmlspecialchars($rowProfit===null?'-':ownMoneyRaw($rowProfit))?>
  <?php if($isUndersold && $reportMode==='overview'):?><span class="undersold">Undersold</span><?php endif;?>
</td>
<?php endif;?>
<td><?=htmlspecialchars(ownDash($d['manufacturer']))?></td><td><?=htmlspecialchars(ownDash($d['model_name']))?></td>
<td><?=htmlspecialchars(ownDash($d['item_type']))?></td><td><?=htmlspecialchars(ownDash($d['processor']))?></td><td><?=htmlspecialchars(ownDash($d['ram']))?></td>
<td><?=htmlspecialchars(ownDash($d['storage']))?></td><td><?=htmlspecialchars(ownDash($d['serial_number']))?></td><td><?=htmlspecialchars(ownDash($d['grade']))?></td>
<td><?=htmlspecialchars(ownDash($d['touch_screen']))?></td><td><?=htmlspecialchars(ownDash($d['webcam']))?></td><td class="notes"><?=htmlspecialchars(ownDash($d['notes']))?></td><td><?=htmlspecialchars(ownDash($d['location']))?></td>
<?php endif;?>
<td><span class="badge <?=($d['status']??'')==='Sold'?'sold':''?>"><?=htmlspecialchars(ownDash($d['status']??null))?></span></td>
<?php if($reportMode==='overview'):?><td class="maint"><?=htmlspecialchars(ownMaintenanceText($maintenance[(int)$d['id']]??[]))?></td><?php endif;?>
<?php if($reportMode==='sold'):?>
<td><?=htmlspecialchars(ownDash($d['sales_person']))?></td>
<td><?=htmlspecialchars(ownDash($d['sold_at']))?></td>
<td>
  <?=htmlspecialchars(ownMoneyRaw($d['selling_price']??null))?>
  <?php if($isUndersold):?><span class="undersold">Undersold</span><?php endif;?>
</td>
<td><?=htmlspecialchars(ownDash($d['payment_status']))?></td>
<?php endif;?>
<td class="action-cell">
  <a class="btn mini edit" href="edit_device.php?id=<?=(int)$d['id']?>"><i class="fas fa-edit"></i> Edit</a>
  <?php $isInStock=(($d['status']??'')==='In Stock'); $isMonitor=(strtolower(trim((string)($d['item_type']??'')))==='monitor'); ?>

  <?php if($isInStock && !$isMonitor):?>
  <a class="btn mini spec" href="update_specs.php?id=<?=(int)$d['id']?>"><i class="fas fa-microchip"></i> Update Specs</a>
  <?php endif;?>

  <?php if($isInStock):?>
  <button type="button" class="btn mini saleBtn" data-id="<?=(int)$d['id']?>" data-label="<?=htmlspecialchars(ownDash($d['serial_number']??null).' — '.ownDash($d['model_name']??null),ENT_QUOTES)?>"><i class="fas fa-cash-register"></i> Update Sale Details</button>
  <?php endif;?>
</td>
</tr>
<?php endforeach;?>
</tbody></table>
<?php endif;?>
</div></section>

<?php if($total>0):?>
<section class="pagination-wrap">
  <div class="page-info">Showing <?=number_format($fromRow)?>–<?=number_format($toRow)?> of <?=number_format($total)?> items</div>
  <nav class="pagination" aria-label="Inventory pagination">
    <a class="page-link <?=$page<=1?'disabled':''?>" href="?<?=htmlspecialchars(ownerQueryString(['page'=>max(1,$page-1)]))?>"><i class="fas fa-chevron-left"></i> Previous</a>
    <?php
      $start=max(1,$page-2);$end=min($totalPages,$page+2);
      if($start>1){
          echo '<a class="page-link" href="?'.htmlspecialchars(ownerQueryString(['page'=>1])).'">1</a>';
          if($start>2)echo '<span class="page-link disabled">…</span>';
      }
      for($pn=$start;$pn<=$end;$pn++){
          echo '<a class="page-link '.($pn===$page?'active':'').'" href="?'.htmlspecialchars(ownerQueryString(['page'=>$pn])).'">'.$pn.'</a>';
      }
      if($end<$totalPages){
          if($end<$totalPages-1)echo '<span class="page-link disabled">…</span>';
          echo '<a class="page-link" href="?'.htmlspecialchars(ownerQueryString(['page'=>$totalPages])).'">'.$totalPages.'</a>';
      }
    ?>
    <a class="page-link <?=$page>=$totalPages?'disabled':''?>" href="?<?=htmlspecialchars(ownerQueryString(['page'=>min($totalPages,$page+1)]))?>">Next <i class="fas fa-chevron-right"></i></a>
  </nav>
  <form class="per-page" method="get">
    <?php foreach($_GET as$k=>$v): if(!in_array($k,['per_page','page','ajax_search','field','term'],true)):?>
      <input type="hidden" name="<?=htmlspecialchars($k)?>" value="<?=htmlspecialchars((string)$v)?>">
    <?php endif; endforeach;?>
    <label for="perPageBottom">Per page</label>
    <select id="perPageBottom" name="per_page" onchange="this.form.submit()">
      <?php foreach($allowedPerPage as$size):?><option value="<?=$size?>" <?=$perPage===$size?'selected':''?>><?=$size?></option><?php endforeach;?>
    </select>
  </form>
</section>
<?php endif;?>

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

let moneyVisible=false;
const moneyToggle=document.getElementById('moneyToggle');
function renderMoney(){
  document.querySelectorAll('.money-value').forEach(el=>{el.textContent=moneyVisible?el.dataset.money:'.......';});
  if(moneyToggle)moneyToggle.innerHTML=moneyVisible?'<i class="fas fa-eye-slash"></i> Hide Summary Money':'<i class="fas fa-eye"></i> View Summary Money';
}
if(moneyToggle)moneyToggle.addEventListener('click',()=>{moneyVisible=!moneyVisible;renderMoney();});
renderMoney();

function setupAjaxSuggest(inputId,boxId,field){
  const input=document.getElementById(inputId),box=document.getElementById(boxId);
  if(!input||!box)return;
  let timer=null,controller=null;

  input.addEventListener('input',()=>{
    clearTimeout(timer);
    if(controller)controller.abort();

    const term=input.value.trim();
    if(term.length<2){
      box.innerHTML='';
      box.classList.remove('open');
      return;
    }

    timer=setTimeout(async()=>{
      controller=new AbortController();
      try{
        const params=new URLSearchParams({ajax_search:'1',field:field,term:term});
        const response=await fetch(window.location.pathname+'?'+params.toString(),{
          method:'GET',
          headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
          cache:'no-store',
          signal:controller.signal
        });

        const raw=await response.text();
        let data;
        try{
          data=JSON.parse(raw);
        }catch(e){
          const clean=raw.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
          throw new Error(clean ? clean.substring(0,220) : 'The server returned an empty or invalid search response.');
        }
        if(!response.ok||data.ok===false)throw new Error(data.error||'Search request failed.');

        box.innerHTML='';
        const results=data.results||[];

        if(!results.length){
          box.innerHTML='<div class="suggestion"><strong>No matches found</strong><small>No match was found in the full inventory.</small></div>';
          box.classList.add('open');
          return;
        }

        results.forEach(r=>{
          const d=document.createElement('div');
          d.className='suggestion';
          const main=field==='serial_number'?(r.serial_number||''):(r.model_name||'');
          d.innerHTML='<strong>'+escapeHtml(main||'-')+'</strong><small>'+escapeHtml((r.serial_number||'-')+' • '+(r.model_name||'-')+' • '+(r.status||'-'))+'</small>';
          d.addEventListener('click',()=>{
            input.value=main;
            const form=input.form;
            const dateFrom=form.querySelector('[name="date_from"]');
            const dateTo=form.querySelector('[name="date_to"]');
            if(dateFrom)dateFrom.value='';
            if(dateTo)dateTo.value='';
            const pageField=form.querySelector('[name="page"]');
            if(pageField)pageField.value='1';
            box.classList.remove('open');
            form.requestSubmit();
          });
          box.appendChild(d);
        });

        box.classList.add('open');
      }catch(e){
        if(e.name==='AbortError')return;
        box.innerHTML='<div class="suggestion" style="color:#991b1b;background:#fef2f2"><strong>Search error</strong><small>'+escapeHtml(e.message)+'</small></div>';
        box.classList.add('open');
      }
    },120);
  });

  document.addEventListener('click',e=>{if(!box.contains(e.target)&&e.target!==input)box.classList.remove('open');});
}
function escapeHtml(s){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
setupAjaxSuggest('serialSearch','serialSuggestions','serial_number');
setupAjaxSuggest('modelSearch','modelSuggestions','model_name');
</script>
<?php require_once __DIR__.'/footer.php'; ?>
</body></html>
