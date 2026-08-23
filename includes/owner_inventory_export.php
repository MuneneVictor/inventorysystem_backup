<?php
// Buffer everything while building the workbook so no stray output corrupts the XLSX file.
ob_start();

if (!isset($ownerKey, $ownerLabel)) { die('Inventory export configuration missing.'); }
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$role = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];
if (!in_array($role, ['super_admin','inventory_admin','manager'])) { die('ACCESS DENIED.'); }

$reportMode = trim($_GET['report'] ?? 'overview');
if (!in_array($reportMode, ['overview','instock','sold'], true)) $reportMode='overview';
$user_branch='';
if ($role === 'manager') {
    $q=$conn->prepare('SELECT branch FROM users WHERE id=?'); $q->execute([$user_id]);
    $user_branch=(string)($q->fetchColumn() ?: '');
}
$serial=trim($_GET['serial'] ?? ''); $model=trim($_GET['model'] ?? '');
$branch=trim($_GET['branch'] ?? ''); $category=trim($_GET['category'] ?? ''); $status=trim($_GET['status'] ?? '');
$date_from=trim($_GET['date_from'] ?? ''); $date_to=trim($_GET['date_to'] ?? '');
if ($reportMode==='overview') {
    if ($date_from==='') $date_from=date('Y-m-01');
    if ($date_to==='') $date_to=date('Y-m-d');
}

$sql="SELECT d.*, c.category_name, ua.full_name added_by_name, us.full_name sold_by_name
      FROM devices d LEFT JOIN categories c ON c.id=d.category_id
      LEFT JOIN users ua ON ua.id=d.added_by LEFT JOIN users us ON us.id=d.sold_by
      WHERE d.inventory_owner=:owner";
$params=['owner'=>$ownerKey];
if ($reportMode==='instock') $sql.=" AND d.status='In Stock'";
elseif ($reportMode==='sold') $sql.=" AND d.status='Sold'";
elseif ($status!=='') { $sql.=' AND d.status=:status'; $params['status']=$status; }
if ($role==='manager' && $user_branch!=='') { $sql.=' AND d.branch=:manager_branch'; $params['manager_branch']=$user_branch; }
elseif ($branch!=='') { $sql.=' AND d.branch=:branch'; $params['branch']=$branch; }
if ($serial!=='') { $sql.=' AND d.serial_number LIKE :serial'; $params['serial']="%$serial%"; }
if ($model!=='') { $sql.=' AND d.model_name LIKE :model'; $params['model']="%$model%"; }
if ($category!=='') { $sql.=' AND d.category_id=:category'; $params['category']=(int)$category; }
if ($reportMode==='overview' && $date_from!=='') { $sql.=' AND DATE(d.date_added)>=:date_from'; $params['date_from']=$date_from; }
if ($reportMode==='overview' && $date_to!=='') { $sql.=' AND DATE(d.date_added)<=:date_to'; $params['date_to']=$date_to; }
if ($reportMode==='sold' && $date_from!=='') { $sql.=' AND DATE(d.sold_at)>=:date_from'; $params['date_from']=$date_from; }
if ($reportMode==='sold' && $date_to!=='') { $sql.=' AND DATE(d.sold_at)<=:date_to'; $params['date_to']=$date_to; }
$sql .= $reportMode==='sold' ? ' ORDER BY d.sold_at DESC' : ' ORDER BY d.date_added DESC';
$stmt=$conn->prepare($sql); $stmt->execute($params); $devices=$stmt->fetchAll(PDO::FETCH_ASSOC);

$maintenance=[];
if ($reportMode==='overview' && $devices) {
    $sns=array_column($devices,'serial_number'); $ph=implode(',',array_fill(0,count($sns),'?'));
    $m=$conn->prepare("SELECT m.*, u.full_name performed_by_name FROM maintenance m LEFT JOIN users u ON u.id=m.performed_by WHERE m.device_serial IN ($ph) ORDER BY m.date_performed ASC");
    $m->execute($sns);
    foreach($m->fetchAll(PDO::FETCH_ASSOC) as $row) $maintenance[$row['device_serial']][]=$row;
}
function ownerExportSpecs($d){$a=[]; foreach(['processor','graphics'] as $k) if(!empty($d[$k])&&$d[$k]!=='None')$a[]=$d[$k]; if(!empty($d['ram']))$a[]=$d['ram'].'GB RAM'; if(!empty($d['storage_capacity']))$a[]=$d['storage_capacity'].'GB '.($d['storage_type']??''); if(!empty($d['touch'])&&$d['touch']!=='N/A')$a[]=$d['touch']; return implode(' | ',$a);}
function ownerExportMaintenance($rows){ if(!$rows)return 'No maintenance records'; $out=[]; foreach($rows as $r){$c=[]; if($r['old_ram']!==null||$r['new_ram']!==null)$c[]='RAM: '.($r['old_ram']??'?').'GB -> '.($r['new_ram']??'?').'GB'; if($r['old_storage']!==null||$r['new_storage']!==null)$c[]='Storage: '.($r['old_storage']??'?').'GB -> '.($r['new_storage']??'?').'GB'; if(($r['old_graphics']??'')!==''||($r['new_graphics']??'')!=='')$c[]='Graphics: '.($r['old_graphics']?:'?').' -> '.($r['new_graphics']?:'?'); if(!empty($r['notes']))$c[]='Notes: '.$r['notes']; $out[]=date('d M Y H:i',strtotime($r['date_performed'])).' - '.implode('; ',$c).' - By '.($r['performed_by_name']??'Unknown');} return implode("\n",$out); }

$sheetFile = new Spreadsheet();
$sheet=$sheetFile->getActiveSheet();
$pageTitle=$reportMode==='overview'?'Overview':($reportMode==='instock'?'In Stock':'Sold');
$sheet->setTitle(substr($ownerLabel.' '.$pageTitle,0,31));

$headers=['#','Serial Number','Category','Model','Specifications','Status','Branch','Place','Date Added','Added By'];
if ($reportMode==='overview') $headers[]='Maintenance / Changes';
if ($reportMode==='sold') { $headers[]='Sold By'; $headers[]='Sold At'; $headers[]='Selling Price'; }

$lastCol=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
$sheet->setCellValue('A1', $ownerLabel.' - '.$pageTitle);
$sheet->mergeCells('A1:'.$lastCol.'1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

if ($reportMode==='overview') {
    $sheet->setCellValue('A2', 'Date range: '.$date_from.' to '.$date_to);
    $sheet->mergeCells('A2:'.$lastCol.'2');
}
$headerRow=$reportMode==='overview'?4:3;
foreach($headers as $i=>$h) { $cell=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1).$headerRow; $sheet->setCellValue($cell,$h); }
$sheet->getStyle('A'.$headerRow.':'.$lastCol.$headerRow)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A'.$headerRow.':'.$lastCol.$headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF217346');

$r=$headerRow+1;
foreach($devices as $i=>$d){
    $values=[
        $i+1,$d['serial_number'],$d['category_name']??'',$d['model_name']??'',ownerExportSpecs($d),$d['status']??'',
        $d['branch']??'',$d['place']??'', $d['date_added']?date('Y-m-d H:i',strtotime($d['date_added'])):'', $d['added_by_name']??'System'
    ];
    if($reportMode==='overview') $values[]=ownerExportMaintenance($maintenance[$d['serial_number']]??[]);
    if($reportMode==='sold') { $values[]=$d['sold_by_name']??'Unknown'; $values[]=$d['sold_at']?date('Y-m-d H:i',strtotime($d['sold_at'])):''; $values[]=$d['selling_price']!==null?(float)$d['selling_price']:''; }
    foreach($values as $i2=>$v) { $cell=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i2+1).$r; $sheet->setCellValue($cell,$v); }
    $r++;
}

foreach(range(1,count($headers)) as $ci){
    $col=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci);
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$sheet->getStyle('A'.$headerRow.':'.$lastCol.max($headerRow,$r-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');
$sheet->getStyle('A1:'.$lastCol.max(1,$r-1))->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
if ($reportMode==='overview') {
    $maintCol=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(11);
    $sheet->getStyle($maintCol.($headerRow+1).':'.$maintCol.max($headerRow+1,$r-1))->getAlignment()->setWrapText(true);
    $sheet->getColumnDimension($maintCol)->setWidth(55);
}
$sheet->freezePane('A'.($headerRow+1));

$filename=preg_replace('/[^A-Za-z0-9_-]+/','_',strtolower($ownerLabel.'_'.$reportMode.'_'.date('Y-m-d'))).'.xlsx';

// IMPORTANT: An XLSX file is a ZIP container. Any whitespace, notice or HTML output
// before the workbook bytes will make Excel report that the file is corrupted.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Expires: 0');

$writer=new Xlsx($sheetFile);
$writer->save('php://output');
exit;
