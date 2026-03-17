<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();
if (hasRole('staff')) redirect(BASE_URL.'/admin/dashboard.php');

$db   = getDB();
$user = currentUser();
$activePage = 'order';
$pageTitle  = 'Place an Order';

$custStmt = $db->prepare("SELECT * FROM customers WHERE user_id=?");
$custStmt->execute([$user['id']]);
$customer = $custStmt->fetch();
if (!$customer) redirect(BASE_URL.'/auth/logout.php');
$cid = $customer['id'];

$styles  = $db->query("SELECT * FROM styles WHERE is_active=TRUE ORDER BY name")->fetchAll();
$fabrics = $db->query("SELECT * FROM fabrics WHERE quantity_yards > 0 ORDER BY name")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $styleId  = (int)($_POST['style_id'] ?? 0);
    $fabricId = (int)($_POST['fabric_id'] ?? 0);
    $qty      = max(1,(int)($_POST['quantity'] ?? 1));
    $notes    = trim($_POST['notes'] ?? '');
    $sBust    = !empty($_POST['self_bust'])   ? (float)$_POST['self_bust']   : null;
    $sWaist   = !empty($_POST['self_waist'])  ? (float)$_POST['self_waist']  : null;
    $sHips    = !empty($_POST['self_hips'])   ? (float)$_POST['self_hips']   : null;
    $sHeight  = !empty($_POST['self_height']) ? (float)$_POST['self_height'] : null;

    if (!$styleId) $errors[] = 'Please select a style.';
    if (!$fabricId) $errors[] = 'Please select a fabric.';

    if (!$errors) {
        $styleRow = $db->prepare("SELECT base_price FROM styles WHERE id=?");
        $styleRow->execute([$styleId]); $styleRow = $styleRow->fetch();
        $total = ($styleRow['base_price'] ?? 0) * $qty;

        $db->prepare("INSERT INTO orders(customer_id,style_id,fabric_id,quantity,status,notes,self_bust,self_waist,self_hips,self_height,total_amount) VALUES(?,?,?,?,'pending',?,?,?,?,?,?)")
           ->execute([$cid,$styleId,$fabricId,$qty,$notes,$sBust,$sWaist,$sHips,$sHeight,$total]);
        $newId = $db->lastInsertId();
        auditLog('place_order',"Customer #$cid placed order #$newId");

        // Notify staff
        $staffUsers = $db->query("SELECT id FROM users WHERE role IN('staff','admin') AND is_active=TRUE")->fetchAll();
        foreach ($staffUsers as $su) addNotification($su['id'], "New order #$newId received from ".clean($user['name']).".");

        setFlash('success',"Order #$newId placed successfully! Our team will review it shortly.");
        redirect(BASE_URL.'/customer/order_history.php');
    }
}

$stylePrices = [];
foreach ($styles as $s) $stylePrices[$s['id']] = $s['base_price'];
require_once __DIR__ . '/../includes/customer_header.php';
?>

<div class="page-header">
  <div>
    <h3><i class="bi bi-bag-plus-fill text-pink me-2"></i>Place an Order</h3>
    <div class="subtitle">Browse our styles and request a custom outfit</div>
  </div>
</div>

<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-4">
  <!-- Styles Grid -->
  <div class="col-12">
    <h5 class="mb-3"><i class="bi bi-stars text-gold me-2"></i>Choose a Style</h5>
    <div class="row g-3" id="stylesGrid">
      <?php foreach ($styles as $s): ?>
        <div class="col-6 col-sm-4 col-lg-3">
          <label class="style-selector-card" for="style_<?= $s['id'] ?>">
            <input type="radio" name="style_id_sel" id="style_<?= $s['id'] ?>" value="<?= $s['id'] ?>" class="d-none style-radio"
                   <?= (($_POST['style_id'] ?? 0) == $s['id']) ? 'checked' : '' ?>>
            <div class="style-card style-selectable" data-id="<?= $s['id'] ?>" data-price="<?= $s['base_price'] ?>">
              <div style="height:130px;background:linear-gradient(135deg,var(--pink-100),var(--pink-200));display:flex;align-items:center;justify-content:center;font-size:3rem;">
                <?= ['👗','👘','🥻','🩱','👔','🧥'][array_search($s,$styles)%6] ?>
              </div>
              <div class="style-card-body">
                <div class="style-card-title"><?= clean($s['name']) ?></div>
                <div class="style-card-price"><?= ghcFormat($s['base_price']) ?></div>
                <small class="text-muted"><?= clean(substr($s['description'],0,60)) ?>…</small>
              </div>
              <div class="style-check"><i class="bi bi-check-circle-fill"></i></div>
            </div>
          </label>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Order Form -->
  <div class="col-lg-7">
    <div class="card-studio">
      <div class="card-header"><h5><i class="bi bi-clipboard-fill text-pink me-2"></i>Order Details</h5></div>
      <div class="card-body">
        <form method="POST" id="orderForm">
          <input type="hidden" name="style_id" id="hiddenStyleId" value="<?= (int)($_POST['style_id']??0) ?>">
          <div class="row g-3">
            <div class="col-sm-8">
              <label class="form-label fw-600">Fabric *</label>
              <select class="form-select" name="fabric_id" required>
                <option value="">— Select Fabric —</option>
                <?php foreach ($fabrics as $f): ?>
                  <option value="<?= $f['id'] ?>" <?= (($_POST['fabric_id']??0)==$f['id'])?'selected':'' ?>>
                    <?= clean($f['name']) ?> (<?= clean($f['color']) ?>) – <?= $f['quantity_yards'] ?> yds left
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-4">
              <label class="form-label fw-600">Quantity</label>
              <input type="number" class="form-control" name="quantity" id="quantity" min="1" max="10" value="<?= (int)($_POST['quantity']??1) ?>">
            </div>
            <div class="col-12">
              <label class="form-label fw-600">Special Instructions</label>
              <textarea class="form-control" name="notes" rows="3" placeholder="e.g. I prefer a loose fit, zipper at back, by March 20th…"><?= clean($_POST['notes']??'') ?></textarea>
            </div>
          </div>

          <hr class="divider-pink my-3">
          <h6 class="mb-2 text-muted"><i class="bi bi-rulers me-1"></i>Optional: Self-Reported Measurements (inches)</h6>
          <small class="text-muted d-block mb-3">Our staff will always verify in-person, but you can provide an estimate to help us prepare.</small>
          <div class="row g-2">
            <div class="col-6 col-sm-3"><label class="form-label small fw-600">Bust</label><input type="number" step="0.5" class="form-control form-control-sm" name="self_bust" value="<?= $_POST['self_bust']??'' ?>" placeholder="e.g. 36"></div>
            <div class="col-6 col-sm-3"><label class="form-label small fw-600">Waist</label><input type="number" step="0.5" class="form-control form-control-sm" name="self_waist" value="<?= $_POST['self_waist']??'' ?>" placeholder="e.g. 28"></div>
            <div class="col-6 col-sm-3"><label class="form-label small fw-600">Hips</label><input type="number" step="0.5" class="form-control form-control-sm" name="self_hips" value="<?= $_POST['self_hips']??'' ?>" placeholder="e.g. 38"></div>
            <div class="col-6 col-sm-3"><label class="form-label small fw-600">Height</label><input type="number" step="0.5" class="form-control form-control-sm" name="self_height" value="<?= $_POST['self_height']??'' ?>" placeholder="e.g. 64"></div>
          </div>

          <div class="price-estimate-box alert alert-info d-none mt-3 d-flex align-items-center justify-content-between">
            <span><i class="bi bi-calculator me-2"></i>Estimated Cost:</span>
            <strong id="priceEstimate" class="fs-5">GH₵ 0.00</strong>
          </div>
          <hr class="divider-pink">
          <button type="submit" class="btn btn-fashion"><i class="bi bi-bag-check-fill me-2"></i>Submit Order</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Info Panel -->
  <div class="col-lg-5">
    <div class="card-studio mb-3">
      <div class="card-header"><h5><i class="bi bi-info-circle-fill text-pink me-2"></i>How It Works</h5></div>
      <div class="card-body">
        <?php $steps = [['bag-plus','Place Your Order','Select style, fabric & notes'],['rulers','Get Measured','Staff will record your exact measurements'],['arrow-repeat','We Craft Your Look','Our tailors get to work'],['check2-circle','Pick It Up','Collect your custom outfit!']];
        foreach ($steps as $i => [$icon,$title,$desc]): ?>
          <div class="d-flex align-items-start gap-3 mb-3">
            <div class="step-dot done" style="width:32px;height:32px;font-size:.85rem;flex-shrink:0;margin-top:2px;"><?= $i+1 ?></div>
            <div><strong class="d-block small"><?= $title ?></strong><small class="text-muted"><?= $desc ?></small></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card-studio">
      <div class="card-body text-center py-4">
        <i class="bi bi-telephone-fill text-pink fs-2 d-block mb-2"></i>
        <strong>Need Help?</strong>
        <p class="text-muted small mt-1">Call us at <strong>+233 200 000 001</strong><br>or visit our studio.</p>
      </div>
    </div>
  </div>
</div>

<script>
window.stylePrices = <?= json_encode($stylePrices) ?>;

// Style selection
document.querySelectorAll('.style-selectable').forEach(card => {
  card.addEventListener('click', () => {
    document.querySelectorAll('.style-selectable').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    document.getElementById('hiddenStyleId').value = card.dataset.id;
    // trigger price update
    const sel = document.getElementById('style_id');
    if (sel) sel.value = card.dataset.id;
    document.getElementById('style_id') || (window.stylePrices && updateOrderPrice(card.dataset.id));
  });
});

function updateOrderPrice(styleId) {
  const price = parseFloat(window.stylePrices[styleId] || 0);
  const qty   = parseInt(document.getElementById('quantity')?.value || 1);
  document.getElementById('priceEstimate').textContent = 'GH₵ ' + (price * qty).toFixed(2);
  document.querySelector('.price-estimate-box')?.classList.remove('d-none');
}

document.getElementById('quantity')?.addEventListener('input', () => {
  const sid = document.getElementById('hiddenStyleId')?.value;
  if (sid) updateOrderPrice(sid);
});

// Pre-select if coming back from validation
const preStyle = document.getElementById('hiddenStyleId')?.value;
if (preStyle) document.querySelectorAll('.style-selectable[data-id="'+preStyle+'"]').forEach(c=>c.classList.add('selected'));

// Add CSS for style selection
const styleEl = document.createElement('style');
styleEl.textContent = `
.style-selectable { cursor:pointer; position:relative; transition:all .2s; }
.style-selectable:hover { transform:translateY(-4px); box-shadow:var(--shadow-md); border-color:var(--pink-300)!important; }
.style-selectable.selected { border:2px solid var(--pink-500)!important; box-shadow:var(--shadow-md); }
.style-check { display:none; position:absolute; top:8px; right:8px; background:var(--pink-500); color:white; border-radius:50%; width:26px; height:26px; align-items:center; justify-content:center; }
.style-selectable.selected .style-check { display:flex; }
`;
document.head.appendChild(styleEl);
</script>

<?php require_once __DIR__ . '/../includes/customer_footer.php'; ?>
