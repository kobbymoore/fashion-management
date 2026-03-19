<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireStaff();
$db = getDB();
$activePage = 'measurements';
$mId  = (int)($_GET['id'] ?? 0);       // existing measurement
$cId  = (int)($_GET['customer_id'] ?? 0);
$m    = [];
$customer = null;

if ($mId) {
    $stmt = $db->prepare("SELECT * FROM measurements WHERE id=?");
    $stmt->execute([$mId]);
    $m = $stmt->fetch();
    if ($m) $cId = $m['customer_id'];
}
if ($cId) {
    $stmt = $db->prepare("SELECT c.*, u.name FROM customers c JOIN users u ON c.user_id=u.id WHERE c.id=?");
    $stmt->execute([$cId]);
    $customer = $stmt->fetch();
}
if (!$customer) { setFlash('danger','Customer not found.'); redirect(BASE_URL.'/admin/measurements.php'); }

$pageTitle  = $mId ? 'Edit Measurements' : 'Record Measurements';
$breadcrumb = ['Measurements'=>BASE_URL.'/admin/measurements.php', $pageTitle=>null];
$errors = [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $fields = ['bust','waist','hips','height','shoulder','inseam','sleeve_length','neck'];
    $vals   = [];
    foreach ($fields as $f) $vals[$f] = !empty($_POST[$f]) ? (float)$_POST[$f] : null;
    $notes = trim($_POST['notes'] ?? '');
    $user  = currentUser();

    if ($mId) {
        $sql = "UPDATE measurements SET bust=?,waist=?,hips=?,height=?,shoulder=?,inseam=?,sleeve_length=?,neck=?,notes=?,recorded_by=? WHERE id=?";
        $db->prepare($sql)->execute([...array_values($vals), $notes, $user['id'], $mId]);
        auditLog('update_measurement',"Updated measurement #$mId for customer #$cId");
    } else {
        $sql = "INSERT INTO measurements(customer_id,bust,waist,hips,height,shoulder,inseam,sleeve_length,neck,notes,recorded_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)";
        $db->prepare($sql)->execute([$cId, ...array_values($vals), $notes, $user['id']]);
        auditLog('add_measurement',"Added measurement for customer #$cId");
    }
    setFlash('success','Measurements saved successfully!');
    redirect(BASE_URL.'/admin/customer_view.php?id='.$cId);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:800px;">
  <div class="page-header">
    <div>
      <h3><i class="bi bi-rulers text-pink me-2"></i><?= $pageTitle ?></h3>
      <div class="subtitle">Customer: <strong><?= clean($customer['name']) ?></strong></div>
    </div>
    <a href="<?= BASE_URL ?>/admin/customer_view.php?id=<?= $cId ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
  <div class="card-studio">
    <div class="card-header">
      <h5><i class="bi bi-person-fill me-2 text-pink"></i><?= clean($customer['name']) ?>'s Measurements</h5>
      <span class="badge bg-pink-soft text-pink border">All values in inches</span>
    </div>
    <div class="card-body">
      <div class="alert alert-info d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-info-circle-fill"></i>
        <span>Enter measurements in inches. Leave blank if not applicable. Values must be realistic (0–120 inches).</span>
      </div>
      <form method="POST" id="measurementForm">
        <div class="measure-grid mb-4">
          <?php
          $measureFields = [
            'bust'         => ['Bust / Chest',    30, 60],
            'waist'        => ['Waist',           20, 50],
            'hips'         => ['Hips',            25, 65],
            'height'       => ['Height',          40, 90],
            'shoulder'     => ['Shoulder Width',  10, 25],
            'inseam'       => ['Inseam',          20, 50],
            'sleeve_length'=> ['Sleeve Length',   15, 40],
            'neck'         => ['Neck',            10, 22],
          ];
          foreach ($measureFields as $key => [$label, $min, $max]):
            $val = $m[$key] ?? ($_POST[$key] ?? '');
          ?>
          <div class="measure-field">
            <label for="<?= $key ?>">
              <?= $label ?>
              <span class="measure-unit"><?= $min ?>–<?= $max ?> in</span>
            </label>
            <input type="number" step="0.25" min="<?= $min ?>" max="<?= $max ?>"
                   class="form-control" id="<?= $key ?>" name="<?= $key ?>"
                   value="<?= $val ?>" placeholder="e.g. 36"
                   data-measure="1" data-min="<?= $min ?>" data-max="<?= $max ?>">
            <div class="invalid-feedback">Must be between <?= $min ?> and <?= $max ?> inches</div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="mb-3">
          <label class="form-label fw-600">Notes</label>
          <textarea class="form-control" name="notes" rows="3" placeholder="e.g. prefers loose fit, has broad shoulders…"><?= clean($m['notes'] ?? '') ?></textarea>
        </div>
        <hr class="divider-pink">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-fashion">
            <i class="bi bi-save me-2"></i>Save Measurements
          </button>
          <a href="<?= BASE_URL ?>/admin/customer_view.php?id=<?= $cId ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
