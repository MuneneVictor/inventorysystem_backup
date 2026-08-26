<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/owner_inventory_access.php';
$access=requireOwnerInventoryAccess($conn);$user_id=(int)$access['user_id'];
function safe($v){return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');}
$id=(int)($_GET['id']??0);
if($id<=0){http_response_code(400);die("Unable to open Update Specs because the selected item's inventory ID is missing or invalid.");}
$q=$conn->prepare("SELECT * FROM `iman_hustle_items` WHERE id=? LIMIT 1");$q->execute([$id]);$item=$q->fetch(PDO::FETCH_ASSOC);
if(!$item){http_response_code(404);die("The selected Iman's Hustle item could not be found. It may have been removed or the link may be outdated.");}
$error='';$success='';
if(($item['status']??'')!=='In Stock'){$error='Specs cannot be changed because this item is currently marked as '.($item['status']??'Unknown').'. Only In Stock items can have hardware specifications updated.';}
if($_SERVER['REQUEST_METHOD']==='POST' && $error==='') {
    $cpuRaw=trim((string)($_POST['processor']??''));
    $ramRaw=trim((string)($_POST['ram']??''));
    $storageRaw=trim((string)($_POST['storage']??''));
    $notes=trim((string)($_POST['notes']??''));

    $newCpu=$cpuRaw===''?($item['processor']??null):$cpuRaw;
    $newRam=$ramRaw===''?($item['ram']??null):$ramRaw;
    $newStorage=$storageRaw===''?($item['storage']??null):$storageRaw;

    if(
        $newCpu===($item['processor']??null) &&
        $newRam===($item['ram']??null) &&
        $newStorage===($item['storage']??null) &&
        $notes===''
    ) {
        $error='No changes were entered. Enter a new CPU, RAM or Storage value, or add a maintenance note before saving.';
    } else {
        try {
            $conn->beginTransaction();

            $m=$conn->prepare("INSERT INTO owner_inventory_maintenance
                (owner_key,item_id,old_processor,new_processor,old_ram,new_ram,old_storage,new_storage,notes,performed_by)
                VALUES(?,?,?,?,?,?,?,?,?,?)");
            $m->execute([
                'imans_hustle',
                $id,
                $item['processor']??null,
                $newCpu,
                $item['ram']??null,
                $newRam,
                $item['storage']??null,
                $newStorage,
                $notes!==''?$notes:null,
                $user_id
            ]);

            $u=$conn->prepare("UPDATE `iman_hustle_items`
                              SET processor=?,ram=?,storage=?,planned_selling_price=NULL
                              WHERE id=?");
            $u->execute([$newCpu,$newRam,$newStorage,$id]);

            $conn->commit();

            $success='The specification update was saved and added to maintenance history. The planned selling price was cleared so it can be reviewed after the hardware change.';

            $q->execute([$id]);
            $item=$q->fetch(PDO::FETCH_ASSOC);
        } catch(Throwable $e) {
            if($conn->inTransaction()) $conn->rollBack();
            $error='We could not update the specifications. '.$e->getMessage();
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Update Specs | <?=safe($item['serial_number'] ?: '#'.$id)?></title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"><style>
:root{--primary:#1a4b2a;--gray-50:#f9fafb;--gray-100:#f3f4f6;--gray-200:#e5e7eb;--gray-300:#d1d5db;--gray-400:#9ca3af;--gray-500:#6b7280;--gray-700:#374151;--gray-800:#1f2937;--radius-md:.5rem;--radius-lg:.75rem;--radius-xl:1rem;--shadow-sm:0 1px 2px rgb(0 0 0 /.05)}*{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,system-ui;background:var(--gray-100);color:var(--gray-800);line-height:1.5}.main-content{padding:2rem;margin-left:260px;width:calc(100% - 260px);min-height:100vh}.page-header,.card{background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-xl);box-shadow:var(--shadow-sm)}.page-header{padding:1.5rem 2rem;margin-bottom:1.5rem}.page-header h1{font-size:1.65rem;display:flex;gap:.75rem;align-items:center;flex-wrap:wrap}.page-header h1 i{color:var(--primary)}.serial-code{font-family:monospace;background:var(--gray-100);padding:.25rem .7rem;border-radius:var(--radius-md);font-size:.95rem}.breadcrumb{color:var(--gray-500);font-size:.88rem;margin-top:.45rem}.breadcrumb a{color:var(--primary);text-decoration:none}.page-help{margin-top:.65rem;color:var(--gray-500);font-size:.88rem;max-width:900px}.alert{padding:1rem 1.2rem;border-radius:var(--radius-md);margin-bottom:1.25rem;display:flex;gap:.65rem;align-items:flex-start}.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}.alert-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}.card{overflow:hidden}.card-header{background:var(--gray-50);padding:1rem 1.5rem;border-bottom:1px solid var(--gray-200);font-weight:650}.card-body{padding:1.5rem}.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;margin-bottom:1.5rem}.info-item{padding:.8rem 1rem;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--radius-lg)}.info-label{font-size:.65rem;color:var(--gray-500);font-weight:700;text-transform:uppercase}.info-value{margin-top:.2rem;font-weight:600}.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem}.form-group{display:flex;flex-direction:column;gap:.4rem}.form-group label{font-size:.85rem;font-weight:600;color:var(--gray-700)}.form-group input,.form-group textarea{padding:.75rem 1rem;border:1px solid var(--gray-300);border-radius:var(--radius-md);font:inherit}.form-group input:focus,.form-group textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,75,42,.1)}.help-text{font-size:.72rem;color:var(--gray-400)}.form-group textarea{min-height:85px}.btn{padding:.75rem 1.2rem;border:0;border-radius:var(--radius-md);font-weight:600;text-decoration:none;display:inline-flex;justify-content:center;align-items:center;gap:.45rem;cursor:pointer}.btn-success{background:#10b981;color:#fff}.btn-secondary{background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-300)}.actions{display:flex;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap}@media(max-width:1200px){.main-content{margin-left:0;width:100%;padding:5rem 1rem 1rem}}@media(max-width:768px){.form-row{grid-template-columns:1fr}.actions{flex-direction:column}.btn{width:100%}}
</style></head><body><?php include __DIR__.'/../includes/sidebar.php';?><div class="main-content"><div class="page-header"><h1><i class="fas fa-microchip"></i> Update Item Specs <span class="serial-code"><?=safe($item['serial_number'] ?: '#'.$id)?></span></h1><div class="breadcrumb"><a href="../dashboard/superadmindashboard.php"><i class="fas fa-home"></i> Dashboard</a> / <a href="overview.php">Iman's Hustle</a> / Update Specs</div><div class="page-help">The item you selected is already loaded. Enter only the hardware details that changed. Leaving a field blank keeps its current value.</div></div>
<?php if($error):?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><div><strong>Specifications were not updated.</strong><br><?=safe($error)?></div></div><?php endif;?><?php if($success):?><div class="alert alert-success"><i class="fas fa-check-circle"></i><div><strong>Update completed.</strong><br><?=safe($success)?></div></div><?php endif;?>
<div class="card"><div class="card-header"><i class="fas fa-info-circle"></i> Current Item Information</div><div class="card-body"><div class="info-grid"><div class="info-item"><div class="info-label">Serial</div><div class="info-value"><?=safe($item['serial_number'] ?: '-')?></div></div><div class="info-item"><div class="info-label">Model</div><div class="info-value"><?=safe($item['model_name'] ?: '-')?></div></div><div class="info-item"><div class="info-label">Item Type</div><div class="info-value"><?=safe($item['item_type'] ?: '-')?></div></div><div class="info-item"><div class="info-label">Processor</div><div class="info-value"><?=safe($item['processor'] ?: '-')?></div></div><div class="info-item"><div class="info-label">RAM</div><div class="info-value"><?=safe($item['ram'] ?: '-')?></div></div><div class="info-item"><div class="info-label">Storage</div><div class="info-value"><?=safe($item['storage'] ?: '-')?></div></div><div class="info-item"><div class="info-label">Status</div><div class="info-value"><?=safe($item['status'] ?: '-')?></div></div></div>
<?php if(($item['status']??'')==='In Stock'):?><form method="post"><div class="form-row"><div class="form-group"><label>New Processor / CPU</label><input name="processor" placeholder="Leave blank to keep <?=safe($item['processor'] ?: 'current value')?>"><span class="help-text">Only enter a value if the processor changed.</span></div><div class="form-group"><label>New RAM</label><input name="ram" placeholder="Leave blank to keep <?=safe($item['ram'] ?: 'current value')?>"><span class="help-text">Example: 16GB</span></div></div><div class="form-row"><div class="form-group"><label>New Storage</label><input name="storage" placeholder="Leave blank to keep <?=safe($item['storage'] ?: 'current value')?>"><span class="help-text">Example: 512GB SSD</span></div><div class="form-group"><label>Maintenance Notes</label><textarea name="notes" placeholder="Describe what was changed, replaced or repaired"></textarea></div></div><div class="actions"><button class="btn btn-success"><i class="fas fa-save"></i> Save Specification Update</button><a class="btn btn-secondary" href="edit_device.php?id=<?=$id?>"><i class="fas fa-edit"></i> Edit Other Details</a><a class="btn btn-secondary" href="overview.php"><i class="fas fa-arrow-left"></i> Back to Overview</a></div></form><?php endif;?></div></div></div><?php require_once __DIR__.'/../includes/footer.php';?></body></html>