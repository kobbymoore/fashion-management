<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireStaff();
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$f  = [];
if ($id) {
    $stmt = $db->prepare("SELECT * FROM fabrics WHERE id=?");
    $stmt->execute([$id]); $f = $stmt->fetch();
    if (!$f) { setFlash('danger','Fabric not found.'); redirect(BASE_URL.'/admin/inventory.php'); }
}
$pageTitle  = $id ? 'Edit Fabric' : 'Add Fabric';
$activePage = 'inventory';
$breadcrumb = ['Inventory'=>BASE_URL.'/admin/inventory.php', $pageTitle=>null];
$errors = [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name    = trim($_POST['name']    ?? '');
    $type    = trim($_POST['fabric_type'] ?? '');
    $color   = trim($_POST['color']   ?? '');
    $qty     = (float)($_POST['quantity_yards'] ?? 0);
    $cost    = (float)($_POST['cost_per_yard']  ?? 0);
    $supp    = trim($_POST['supplier'] ?? '');
    $reorder = (float)($_POST['reorder_level'] ?? 5);

    if (!$name) $errors[] = 'Fabric name is required.';
    if ($qty < 0) $errors[] = 'Quantity cannot be negative.';

    if (!$errors) {
        $user = currentUser();
        if ($id) {
            $old = $db->prepare("SELECT quantity_yards FROM fabrics WHERE id=?"); $old->execute([$id]);
            $oldQty = (float)$old->fetchColumn();
            $db->prepare("UPDATE fabrics SET name=?,fabric_type=?,color=?,quantity_yards=?,cost_per_yard=?,supplier=?,reorder_level=? WHERE id=?")
               ->execute([$name,$type,$color,$qty,$cost,$supp,$reorder,$id]);
            if ($qty !== $oldQty) {
                $db->prepare("INSERT INTO inventory_log(fabric_id,change_qty,reason,recorded_by) VALUES(?,?,?,?)")
                   ->execute([$id, $qty-$oldQty, 'Manual adjustment', $user['id']]);
            }
            auditLog('update_fabric',"Updated fabric #$id");
        } else {
            $db->prepare("INSERT INTO fabrics(name,fabric_type,color,quantity_yards,cost_per_yard,supplier,reorder_level) VALUES(?,?,?,?,?,?,?)")
               ->execute([$name,$type,$color,$qty,$cost,$supp,$reorder]);
            $newId = $db->lastInsertId();
            $db->prepare("INSERT INTO inventory_log(fabric_id,change_qty,reason,recorded_by) VALUES(?,?,?,?)")
               ->execute([$newId, $qty, 'Initial stock', $user['id']]);
            auditLog('add_fabric',"Added fabric: $name");
        }
        setFlash('success','Fabric saved successfully.'); redirect(BASE_URL.'/admin/inventory.php');
    }
}
require_once __DIR__ . '/../includes/header.php';
?>
<div style="max-width:680px;">
  <div class="page-header">
    <div>
      <h3><i class="bi bi-box-seam-fill text-pink me-2"></i><?= $pageTitle ?></h3>
      <div class="subtitle"><?= $id ? 'Update fabric details' : 'Add new fabric to inventory' ?></div>
    </div>
    <a href="<?= BASE_URL ?>/admin/inventory.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
  <div class="card-studio">
    <div class="card-body">
      <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="POST">
        <div class="row g-3">
          <div class="col-sm-8"><label class="form-label fw-600">Fabric Name *</label>
            <input type="text" class="form-control" name="name" value="<?= clean($f['name'] ?? '') ?>" required></div>
          <div class="col-sm-4"><label class="form-label fw-600">Type</label>
            <input type="text" class="form-control" name="fabric_type" placeholder="e.g. Ankara" value="<?= clean($f['fabric_type'] ?? '') ?>"></div>
          <div class="col-sm-6"><label class="form-label fw-600">Color</label>
            <input type="text" class="form-control" name="color" placeholder="e.g. Rose Pink" value="<?= clean($f['color'] ?? '') ?>"></div>
          <div class="col-sm-6"><label class="form-label fw-600">Supplier</label>
            <input type="text" class="form-control" name="supplier" placeholder="e.g. Makola Market" value="<?= clean($f['supplier'] ?? '') ?>"></div>
          <div class="col-sm-4"><label class="form-label fw-600">Quantity (yards) *</label>
            <input type="number" step="0.5" min="0" class="form-control" name="quantity_yards" value="<?= $f['quantity_yards'] ?? 0 ?>" required></div>
          <div class="col-sm-4"><label class="form-label fw-600">Cost per Yard (GH₵)</label>
            <input type="number" step="0.01" min="0" class="form-control" name="cost_per_yard" value="<?= $f['cost_per_yard'] ?? 0 ?>"></div>
          <div class="col-sm-4"><label class="form-label fw-600">Reorder Level (yards)</label>
            <input type="number" step="0.5" min="0" class="form-control" name="reorder_level" value="<?= $f['reorder_level'] ?? 5 ?>">
            <small class="text-muted">Alert when below this</small></div>
        </div>
        <hr class="divider-pink mt-4">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-fashion"><i class="bi bi-save me-2"></i>Save Fabric</button>
          <a href="<?= BASE_URL ?>/admin/inventory.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
