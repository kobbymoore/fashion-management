<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();
if (hasRole('staff')) redirect(BASE_URL.'/admin/dashboard.php');

$db   = getDB();
$user = currentUser();
$activePage = 'dashboard';

// Get customer record
$custStmt = $db->prepare("SELECT * FROM customers WHERE user_id=?");
$custStmt->execute([$user['id']]);
$customer = $custStmt->fetch();
if (!$customer) { setFlash('danger','Customer profile not found.'); redirect(BASE_URL.'/auth/logout.php'); }
$cid = $customer['id'];

// Get latest measurement
$stmt = $db->prepare("SELECT * FROM measurements WHERE customer_id=? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$cid]);
$m = $stmt->fetch() ?: [];

$pageTitle  = 'My Measurements';
$breadcrumb = ['Dashboard' => BASE_URL.'/customer/dashboard.php', 'My Measurements' => null];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $fields = ['bust','waist','hips','height','shoulder','inseam','sleeve_length','neck'];
    $vals   = [];
    foreach ($fields as $f) $vals[$f] = !empty($_POST[$f]) ? (float)$_POST[$f] : null;
    $notes = trim($_POST['notes'] ?? '');

    // Insert new record (history of measurements is good)
    $sql = "INSERT INTO measurements(customer_id,bust,waist,hips,height,shoulder,inseam,sleeve_length,neck,notes,recorded_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)";
    $db->prepare($sql)->execute([$cid, ...array_values($vals), $notes, $user['id']]);
    
    auditLog('add_self_measurement',"Customer #$cid updated their own measurements");
    setFlash('success','Your measurements have been saved successfully! 🥂');
    redirect(BASE_URL.'/customer/dashboard.php');
}

require_once __DIR__ . '/../includes/customer_header.php';
?>

<div style="max-width:800px;">
  <div class="page-header">
    <div>
      <h3><i class="bi bi-rulers text-pink me-2"></i>My Measurements</h3>
      <div class="subtitle">Keep your sizes updated for a perfect fit every time.</div>
    </div>
    <a href="<?= BASE_URL ?>/customer/dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>

  <div class="card-studio">
    <div class="card-header">
      <h5><i class="bi bi-person-fill me-2 text-pink"></i>Standard Measurements</h5>
      <span class="badge bg-pink-soft text-pink border">All values in inches</span>
    </div>
    <div class="card-body">
      <div class="alert alert-info d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-info-circle-fill"></i>
        <span>Enter your measurements in inches. These will be automatically filled when you place new orders.</span>
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
            $val = $m[$key] ?? '';
          ?>
          <div class="measure-field">
            <label for="<?= $key ?>">
              <?= $label ?>
              <span class="measure-unit"><?= $min ?>–<?= $max ?> in</span>
            </label>
            <input type="number" step="0.25" min="<?= $min ?>" max="<?= $max ?>"
                   class="form-control" id="<?= $key ?>" name="<?= $key ?>"
                   value="<?= $val ?>" placeholder="e.g. 36">
            <div class="invalid-feedback">Must be between <?= $min ?> and <?= $max ?> inches</div>
          </div>
          <?php endforeach; ?>
        </div>
        
        <div class="mb-3">
          <label class="form-label fw-600">Special Sizing Notes</label>
          <textarea class="form-control" name="notes" rows="3" placeholder="e.g. prefers a tight fit, has broad shoulders, etc."><?= clean($m['notes'] ?? '') ?></textarea>
        </div>

        <hr class="divider-pink mt-4">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-fashion">
            <i class="bi bi-save me-2"></i>Save My Measurements
          </button>
          <a href="<?= BASE_URL ?>/customer/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/customer_footer.php'; ?>
