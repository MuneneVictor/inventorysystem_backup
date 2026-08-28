<?php
ob_start();
if(!isset($ownerKey,$ownerLabel))die('Inventory export configuration missing.');
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/auth_check.php';
require_once __DIR__.'/owner_inventory_access.php';
require_once __DIR__.'/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

requireOwnerInventoryAccess($conn);

$table=$ownerKey==='imans_hustle'?'iman_hustle_items':'iman_inventory_items';
$type=$ownerKey==='imans_hustle'?'hustle':'inventory';
$mode=trim((string)($_GET['report']??'overview'));if(!in_array($mode,['overview','instock','sold'],true))$mode='overview';
$serial=trim((string)($_GET['serial']??''));$model=trim((string)($_GET['model']??''));$location=strtoupper(trim((string)($_GET['location']??'')));$status=trim((string)($_GET['status']??''));$df=trim((string)($_GET['date_from']??''));$dt=trim((string)($_GET['date_to']??''));
$isSearch=($serial!==''||$model!=='');
if($isSearch){$df='';$dt='';}
else{if($df==='')$df=date('Y-m-01');if($dt==='')$dt=date('Y-m-d');}
$sql="SELECT * FROM `$table` WHERE 1=1";$p=[];
if($mode==='instock')$sql.=" AND status='In Stock'";elseif($mode==='sold')$sql.=" AND status='Sold'";elseif($status!==''){$sql.=' AND status=:s';$p['s']=$status;}
if($serial!==''){$sql.=' AND serial_number LIKE :sn';$p['sn']=$serial."%";}if($model!==''){$sql.=' AND model_name LIKE :m';$p['m']=$model."%";}
if($location!==''&&in_array($location,['KIMATHI','MOI','WAREHOUSE'],true)){$sql.=' AND location=:l';$p['l']=$location;}
$dateColumn=$mode==='sold'?'sold_at':'date_added';
if($df!==''){$sql.=" AND $dateColumn>=:df";$p['df']=$df.' 00:00:00';}
if($dt!==''){$sql.=" AND $dateColumn<:dt";$p['dt']=date('Y-m-d',strtotime($dt.' +1 day')).' 00:00:00';}
$sql.=' ORDER BY '.($mode==='sold'?'sold_at':'date_added').' DESC,id DESC';
$st=$conn->prepare($sql);$st->execute($p);$rows=$st->fetchAll(PDO::FETCH_ASSOC);
function xd($v){$v=trim((string)($v??''));return$v===''?'-':$v;}function xp($d){if(($d['selling_price']??null)!==null&&($d['buying_price']??null)!==null)return(float)$d['selling_price']-(float)$d['buying_price'];if(($d['planned_selling_price']??null)!==null&&($d['buying_price']??null)!==null)return(float)$d['planned_selling_price']-(float)$d['buying_price'];return'-';}
$headers=$type==='hustle'?['#','Asset ID','MFG','Model','Item Type','Form Factor','CPU','RAM','Storage','Serial','Grade','B.P','S.P','PROFIT','NOTES','LOCATION','Status']:['#','Asset ID','Buying $','Selling $','BP','SP','PROFIT','MFG','Model','Item Type','CPU','RAM','Storage','Serial #','Grade','Touch Screen','Webcam','Notes','LOCATION','Status'];
if($mode==='sold')array_push($headers,'Sales Person','Sold At','Actual Selling Price','Payment');
$book=new Spreadsheet();$sheet=$book->getActiveSheet();$sheet->setTitle(substr($ownerLabel.' '.$mode,0,31));
foreach($headers as$i=>$h)$sheet->setCellValue(Coordinate::stringFromColumnIndex($i+1).'1',$h);
$last=Coordinate::stringFromColumnIndex(count($headers));$sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true);$sheet->getStyle('A1:'.$last.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7B729');
$r=2;$n=1;
foreach($rows as$d){
 if($type==='hustle')$vals=[$n++,xd($d['asset_id']),xd($d['manufacturer']),xd($d['model_name']),xd($d['item_type']),xd($d['form_factor']),xd($d['processor']),xd($d['ram']),xd($d['storage']),xd($d['serial_number']),xd($d['grade']),$d['buying_price']??'-',$d['planned_selling_price']??'-',xp($d),xd($d['notes']),xd($d['location']),$d['status']];
 else $vals=[$n++,xd($d['asset_id']),$d['buying_usd']??'-',$d['selling_usd']??'-',$d['buying_price']??'-',$d['planned_selling_price']??'-',xp($d),xd($d['manufacturer']),xd($d['model_name']),xd($d['item_type']),xd($d['processor']),xd($d['ram']),xd($d['storage']),xd($d['serial_number']),xd($d['grade']),xd($d['touch_screen']),xd($d['webcam']),xd($d['notes']),xd($d['location']),$d['status']];
 if($mode==='sold')array_push($vals,xd($d['sales_person']),xd($d['sold_at']),$d['selling_price']??'-',xd($d['payment_status']));
 foreach($vals as$i=>$v)$sheet->setCellValue(Coordinate::stringFromColumnIndex($i+1).$r,$v);$r++;
}
for($i=1;$i<=count($headers);$i++)$sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
$sheet->getStyle('A1:'.$last.max(1,$r-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->freezePane('A2');
while(ob_get_level())ob_end_clean();
$fn=preg_replace('/[^A-Za-z0-9_-]+/','_',strtolower($ownerLabel.'_'.$mode.'_'.date('Y-m-d'))).'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$fn.'"');(new Xlsx($book))->save('php://output');exit;
