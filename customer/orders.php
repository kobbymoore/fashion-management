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

// Add custom order fields if missing (Schema Check) - Safe Migration
$checkCols = ['is_custom','custom_image','custom_voice','custom_description','custom_image_url'];
foreach ($checkCols as $col) {
    $res = $db->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='orders' AND column_name=?");
    $res->execute([$col]);
    if (!$res->fetch()) {
        $sql = "ALTER TABLE orders ADD COLUMN IF NOT EXISTS $col " . (($col === 'is_custom') ? "BOOLEAN DEFAULT FALSE" : "TEXT");
        if ($col === 'custom_image') $sql = "ALTER TABLE orders ADD COLUMN IF NOT EXISTS $col TEXT";
        $db->exec($sql);
        // Ensure custom_image is TEXT if it was previously VARCHAR
        if ($col === 'custom_image') $db->exec("ALTER TABLE orders ALTER COLUMN custom_image TYPE TEXT");
    }
}
// Add batch_id for grouping multi-style orders
$db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS batch_id VARCHAR(50)");

// Final set of columns for commitment/payment
$payCols = ['payment_method','payment_status','payment_reference'];
foreach($payCols as $pc) {
    if ($pc === 'payment_status') {
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS $pc VARCHAR(30) DEFAULT 'unpaid'");
    } else {
        $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS $pc TEXT");
    }
}
// Ensure custom_voice is TEXT (in case it was previously VARCHAR)
$db->exec("ALTER TABLE orders ALTER COLUMN custom_voice TYPE TEXT");

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items = $_POST['items'] ?? []; // Array of [style_id => ['qty'=>N, 'fabric_id'=>ID, 'custom_fabric'=>S]]
    $isCustomSubmit = isset($_POST['is_custom_submit']) && $_POST['is_custom_submit'] == '1';

    if (empty($items) && !$isCustomSubmit) {
        $errors[] = 'Please select at least one style or a custom design.';
    } else {
        $cid = $customer['id'];
        
        $notes = trim($_POST['notes'] ?? '');
        $sBust = $_POST['self_bust'] ?: null;
        $sWaist = $_POST['self_waist'] ?: null;
        $sHips = $_POST['self_hips'] ?: null;
        $sHeight = $_POST['self_height'] ?: null;
        
        $pay_method = $_POST['payment_method'] ?? 'cash';
        $pay_ref    = trim($_POST['payment_reference'] ?? '');
        $pay_status = (!empty($pay_ref)) ? 'pending_verification' : 'unpaid';
        $batch_id   = 'BATCH-' . strtoupper(bin2hex(random_bytes(4)));

        $db->beginTransaction();
        try {
            // 1. Handle Custom Style
            if ($isCustomSubmit) {
                $custom_qty = max(1, (int)($_POST['custom_quantity'] ?? 1));
                $custom_voice = $_POST['custom_voice_base64'] ?? null;
                $custom_desc = trim($_POST['custom_description'] ?? '');
                
                // Handle Image (Prioritize Base64 for Vercel/Serverless compatibility)
                $custom_img_path = $_POST['custom_image_base64'] ?? null;
                
                // Fallback to traditional upload for local environments if Base64 missing
                if (!$custom_img_path && isset($_FILES['custom_image']) && $_FILES['custom_image']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['custom_image']['name'], PATHINFO_EXTENSION);
                    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    if (in_array(strtolower($ext), $allowed)) {
                        $filename = 'custom_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $uploadDir = UPLOADS_PATH . 'custom_requests/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                        
                        if (move_uploaded_file($_FILES['custom_image']['tmp_name'], $uploadDir . $filename)) {
                            $custom_img_path = 'assets/uploads/custom_requests/' . $filename;
                        }
                    }
                }
                
                $fIdRaw = $_POST['custom_fabric_id'] ?? '';
                $fId = ($fIdRaw === 'other') ? null : (int)$fIdRaw;
                $customFabric = ($fIdRaw === 'other') ? trim($_POST['custom_fabric_details'] ?? '') : null;

                $db->prepare("INSERT INTO orders(customer_id,style_id,fabric_id,custom_fabric,quantity,status,notes,self_bust,self_waist,self_hips,self_height,total_amount, is_custom, custom_voice, custom_description, custom_image, payment_method, payment_status, payment_reference, batch_id) VALUES(?,?,?,?,?,'pending',?,?,?,?,?,0.00, TRUE, ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$cid, null, $fId, $customFabric, $custom_qty, $notes, $sBust, $sWaist, $sHips, $sHeight, $custom_voice, $custom_desc, $custom_img_path, $pay_method, $pay_status, $pay_ref, $batch_id]);
            }

            // 2. Handle Standard Styles
            foreach ($items as $sid => $data) {
                $sid = (int)$sid;
                $qty = max(1, (int)($data['qty'] ?? 1));
                if ($sid <= 0) continue;
                
                $fIdRaw = $data['fabric_id'] ?? '';
                $fId = ($fIdRaw === 'other') ? null : (int)$fIdRaw;
                $customFabric = ($fIdRaw === 'other') ? trim($data['custom_fabric'] ?? '') : null;

                // Get style price
                $sStmt = $db->prepare("SELECT base_price FROM styles WHERE id=?");
                $sStmt->execute([$sid]);
                $sPrice = (float)$sStmt->fetchColumn();
                $itemTotal = $sPrice * $qty;

                $db->prepare("INSERT INTO orders(customer_id,style_id,fabric_id,custom_fabric,quantity,status,notes,self_bust,self_waist,self_hips,self_height,total_amount, is_custom, payment_method, payment_status, payment_reference, batch_id) VALUES(?,?,?,?,?,'pending',?,?,?,?,?,?, FALSE, ?, ?, ?, ?)")
                   ->execute([$cid, $sid, $fId, $customFabric, $qty, $notes, $sBust, $sWaist, $sHips, $sHeight, $itemTotal, $pay_method, $pay_status, $pay_ref, $batch_id]);
            }

            $db->commit();
            
            // Notify staff
            $u = currentUser();
            $staffUsers = $db->query("SELECT id FROM users WHERE role IN('staff','admin') AND is_active=TRUE")->fetchAll();
            foreach ($staffUsers as $su) {
                addNotification($su['id'], "New batch order ($batch_id) received from ".clean($u['name']).".");
            }

            auditLog('place_batch_order', "Customer #{$u['id']} placed batch order: $batch_id");
            setFlash('success', 'Your batch order has been placed successfully! 🥂');
            redirect(BASE_URL . '/customer/dashboard.php');
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Failed to place order: ' . $e->getMessage();
        }
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
    <h5 class="mb-3"><i class="bi bi-stars text-gold me-2"></i>Choose One or More Styles</h5>
    <div class="row g-3" id="stylesGrid">
      <?php foreach ($styles as $s): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="style-card style-selectable" data-id="<?= $s['id'] ?>" onclick="toggleStyle(this)">
            <div class="style-card-img-container" style="height:150px; overflow:hidden; background:var(--pink-50);">
              <?php 
                $img = !empty($s['image_path']) ? $s['image_path'] : 'assets/images/styles/placeholder.png';
                $displayImg = (strpos($img, 'http') === 0) ? $img : BASE_URL . '/' . $img;
              ?>
              <img src="<?= $displayImg ?>" alt="<?= clean($s['name']) ?>" style="width:100%; height:100%; object-fit:cover;">
            </div>
            <div class="style-card-body">
              <div class="style-card-title"><?= clean($s['name']) ?></div>
              <div class="style-card-price"><?= ghcFormat($s['base_price']) ?></div>
              <small class="text-muted"><?= clean(substr($s['description'],0,40)) ?>…</small>
            </div>
            <div class="style-check"><i class="bi bi-check-circle-fill"></i></div>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- Bespoke Card -->
      <div class="col-6 col-md-4 col-lg-3">
        <div class="style-card style-selectable border-purple-dashed bg-purple-50" data-id="custom" onclick="toggleStyle(this)">
          <div class="d-flex flex-column align-items-center justify-content-center" style="height:150px; background:white;">
            <i class="bi bi-pencil-square text-purple fs-1"></i>
            <span class="fw-bold text-purple small mt-2">REQUEST CUSTOM</span>
          </div>
          <div class="style-card-body">
            <div class="style-card-title text-purple">Bespoke Design</div>
            <div class="style-card-price">Price: To be Quoted</div>
            <small class="text-muted">Tell us your unique vision</small>
          </div>
          <div class="style-check bg-purple"><i class="bi bi-check-circle-fill"></i></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Order Form -->
  <div class="col-lg-7">
    <div class="card-studio">
      <div class="card-header"><h5><i class="bi bi-cart shadow-sm me-2"></i>Selected Outfits</h5></div>
      <div class="card-body">
        <form method="POST" id="orderForm" enctype="multipart/form-data">
          <div id="selectionList" class="mb-3 d-flex flex-column gap-2">
            <div class="text-muted small py-2"><i class="bi bi-info-circle me-1"></i>Please select one or more styles above...</div>
          </div>
          <input type="hidden" name="is_custom_submit" id="isCustomSubmit" value="0">

          <!-- Custom Order Fields -->
          <div id="customOrderFields" class="mb-4 p-3 border rounded bg-light" style="display:none;">
            <div class="alert alert-purple d-flex align-items-center mb-3" style="font-size: 0.85rem; border: 1px dashed var(--purple-300);">
              <i class="bi bi-camera-fill fs-5 me-3"></i>
              <div>
                <strong>Direct Upload:</strong> You can now upload your design reference directly! No need for external sites. 🚀
              </div>
            </div>
            
            <h6 class="text-pink mb-3"><i class="bi bi-magic me-2"></i>Custom Request Details</h6>
            <div class="mb-3">
              <label class="form-label fw-bold">Design Description *</label>
              <textarea name="custom_description" class="form-control" rows="4" placeholder="Describe the outfit, neckline, length, etc."></textarea>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-bold"><i class="bi bi-image me-1"></i>Official Design Reference (Upload)</label>
                <input type="file" id="customImageInput" class="form-control" accept="image/*">
                <input type="hidden" name="custom_image_base64" id="customImageBase64">
                <small class="text-muted">Upload a picture of the design you want! 🏙️</small>
                <div id="imagePreview" class="mt-2 d-none">
                    <img id="imgPreview" src="" class="img-thumbnail" style="max-height: 150px;">
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold"><i class="bi bi-mic-fill me-1"></i>Voice Note Instruction</label>
                <div id="voiceRecorderUI" class="p-2 border rounded bg-white">
                  <div class="d-flex align-items-center gap-2">
                    <button type="button" id="startRecord" class="btn btn-sm btn-outline-danger"><i class="bi bi-record-circle me-1"></i>Record</button>
                    <button type="button" id="stopRecord" class="btn btn-sm btn-danger d-none"><i class="bi bi-stop-circle me-1"></i>Stop</button>
                    <span id="recordTimer" class="small text-muted d-none">0:00</span>
                    <div id="voicePreview" class="d-none flex-grow-1">
                      <audio id="audioPlayback" controls style="height: 30px; width: 100%;"></audio>
                    </div>
                  </div>
                  <input type="hidden" name="custom_voice_base64" id="customVoiceBase64">
                </div>
                <small class="text-muted">Explain your design in your own voice! 🎙️</small>
              </div>
            </div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-12">
              <label class="form-label fw-600">Special Instructions (General)</label>
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

          <div class="card bg-light border-0 mt-4">
            <div class="card-body p-3">
              <h6 class="text-pink mb-3"><i class="bi bi-shield-check me-2"></i>Payment & Commitment</h6>
              <div class="row g-3">
                <div class="col-sm-6">
                  <label class="form-label fw-600">Intended Payment Method *</label>
                  <select class="form-select" name="payment_method" required>
                    <option value="mobile_money">Mobile Money (MTN/Vodafone/AirtelTigo)</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cash">Cash on Delivery / Pickup</option>
                  </select>
                </div>
                <div class="col-sm-6">
                  <label class="form-label fw-600">Transaction ID (Optional)</label>
                  <input type="text" class="form-control" name="payment_reference" placeholder="Enter ID if paying upfront">
                  <small class="text-muted">Only if you want to pay before we start! 💳</small>
                </div>
              </div>

              <!-- Payment Instructions Box -->
              <div id="paymentInstructions" class="mt-3 p-3 rounded border border-info bg-info bg-opacity-10" style="display:none;">
                  <h6 class="text-info-emphasis small fw-bold mb-2"><i class="bi bi-info-circle me-1"></i>How to Pay:</h6>
                  <div id="momoDetails" style="display:none;">
                      <p class="small mb-1"><strong>MTN/Vodafone/AirtelTigo:</strong> Send amount to <strong>055 342 3057</strong></p>
                      <p class="small mb-0 text-muted">Reference: [Your Name] / [Order]</p>
                  </div>
                  <div id="bankDetails" style="display:none;">
                      <p class="small mb-1"><strong>Bank:</strong> Ecobank Ghana</p>
                      <p class="small mb-1"><strong>Account Name:</strong> Fashion Studio GH</p>
                      <p class="small mb-0"><strong>Account Number:</strong> 90210100171322</p>
                  </div>
                  <div id="cashDetails" style="display:none;">
                      <p class="small mb-0"><strong>Cash:</strong> Please pay at our studio in Adum, Kumasi upon pickup.</p>
                  </div>
              </div>
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" id="commitment" required>
                <label class="form-check-label small fw-bold" for="commitment">
                  I understand this is a binding order and I commit to paying the final amount upon completion/delivery.
                </label>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-fashion w-100 mt-4"><i class="bi bi-bag-check-fill me-2"></i>Confirm & Place Order</button>
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
        <p class="text-muted small mt-1">Call us at <strong>+233 553 423 057</strong><br>or visit our studio.</p>
      </div>
    </div>
  </div>
</div>

<script>
window.stylePrices = <?= json_encode($stylePrices) ?>;
const cart = {}; // {id: quantity}
const styleNames = {};

<?php foreach($styles as $s): ?>
    styleNames[<?= $s['id'] ?>] = "<?= clean($s['name']) ?>";
<?php endforeach; ?>
window.fabrics = <?= json_encode($fabrics) ?>;

function toggleStyle(el) {
    const id = el.getAttribute('data-id');
    const isCustom = (id === 'custom');

    if (el.classList.contains('selected')) {
        el.classList.remove('selected');
        if (isCustom) {
            document.getElementById('customOrderFields').style.display = 'none';
            document.getElementById('isCustomSubmit').value = '0';
        } else {
            delete cart[id];
        }
    } else {
        el.classList.add('selected');
        if (isCustom) {
            document.getElementById('customOrderFields').style.display = 'block';
            document.getElementById('isCustomSubmit').value = '1';
        } else {
            cart[id] = 1;
        }
    }
    updateCartUI();
}

function updateQty(id, delta) {
    if (id === 'custom') {
        const input = document.getElementById('customQtyInput');
        let val = parseInt(input.value) + delta;
        input.value = Math.max(1, val);
    } else {
        cart[id] = Math.max(1, (cart[id] || 1) + delta);
    }
    updateCartUI();
}

function togglePerItemCustomFabric(id, val) {
    const target = document.getElementById(`customFabricGroup_${id}`);
    if (target) target.style.display = (val === 'other') ? 'block' : 'none';
}

function updateCartUI() {
    const list = document.getElementById('selectionList');
    const priceBox = document.getElementById('priceEstimate');
    const priceBoxContainer = document.querySelector('.price-estimate-box');
    
    list.innerHTML = "";
    let subTotal = 0;
    let hasItems = false;
    let hasCustom = (document.getElementById('isCustomSubmit').value === '1');

    // Standard Styles
    Object.keys(cart).forEach(id => {
        hasItems = true;
        const name = styleNames[id];
        const price = parseFloat(window.stylePrices[id] || 0);
        const qty = cart[id];
        subTotal += (price * qty);

        const fabricOpts = window.fabrics.map(f => `
            <option value="${f.id}">${f.name} (${f.color}) - ${f.quantity_yards} yds</option>
        `).join('');

        const item = document.createElement('div');
        item.className = "cart-item p-3 border rounded mb-2 bg-white shadow-sm";
        item.innerHTML = `
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-tag-fill text-pink"></i>
                    <div>
                        <div class="fw-bold small">${name}</div>
                        <div class="text-muted smaller">GH₵ ${price.toFixed(2)} each</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 100px;">
                        <button class="btn btn-outline-secondary" type="button" onclick="updateQty(${id}, -1)">-</button>
                        <input type="text" class="form-control text-center" value="${qty}" readonly name="items[${id}][qty]">
                        <button class="btn btn-outline-secondary" type="button" onclick="updateQty(${id}, 1)">+</button>
                    </div>
                    <i class="bi bi-x-circle text-danger cursor-pointer" onclick="document.querySelector('.style-selectable[data-id=\\'${id}\\']').click()"></i>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-12">
                    <label class="smaller fw-600 mb-1">Choose Fabric for this style *</label>
                    <select class="form-select form-select-sm" name="items[${id}][fabric_id]" required onchange="togglePerItemCustomFabric(${id}, this.value)">
                        <option value="">— Select Fabric —</option>
                        ${fabricOpts}
                        <option value="other">Other / My own fabric (Specify...)</option>
                    </select>
                </div>
                <div class="col-12 mt-2" id="customFabricGroup_${id}" style="display:none;">
                    <textarea class="form-control form-control-sm" name="items[${id}][custom_fabric]" rows="1" placeholder="Specify fabric details..."></textarea>
                </div>
            </div>
        `;
        list.appendChild(item);
    });

    // Custom Style
    if (hasCustom) {
        hasItems = true;
        const qty = parseInt(document.getElementById('customQtyInput')?.value || 1);
        
        const fabricOpts = window.fabrics.map(f => `
            <option value="${f.id}">${f.name} (${f.color}) - ${f.quantity_yards} yds</option>
        `).join('');

        const item = document.createElement('div');
        item.className = "cart-item p-3 border rounded border-purple-200 bg-purple-50 mb-2 shadow-sm";
        item.innerHTML = `
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-magic text-purple"></i>
                    <div>
                        <div class="fw-bold small text-purple">Custom Bespoke Design</div>
                        <div class="text-muted smaller">Price: TBD</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 100px;">
                        <button class="btn btn-outline-purple" type="button" onclick="updateQty('custom', -1)">-</button>
                        <input type="text" id="customQtyInput" class="form-control text-center" value="${qty}" readonly name="custom_quantity">
                        <button class="btn btn-outline-purple" type="button" onclick="updateQty('custom', 1)">+</button>
                    </div>
                    <i class="bi bi-x-circle text-danger cursor-pointer" onclick="document.querySelector('.style-selectable[data-id=\\'custom\\']').click()"></i>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-12">
                    <label class="smaller fw-600 text-purple mb-1">Fabric choice *</label>
                    <select class="form-select form-select-sm border-purple-200" name="custom_fabric_id" required onchange="togglePerItemCustomFabric('custom', this.value)">
                        <option value="">— Select Fabric —</option>
                        ${fabricOpts}
                        <option value="other">Other / My own fabric (Specify...)</option>
                    </select>
                </div>
                <div class="col-12 mt-2" id="customFabricGroup_custom" style="display:none;">
                    <textarea class="form-control form-control-sm border-purple-200" name="custom_fabric_details" rows="1" placeholder="Specify fabric details for custom design..."></textarea>
                </div>
            </div>
        `;
        list.appendChild(item);
    }

    if (!hasItems) {
        list.innerHTML = '<div class="text-muted small py-2 text-center"><i class="bi bi-cart-x me-1"></i>Your cart is empty. Select a style above!</div>';
        if (priceBoxContainer) priceBoxContainer.style.display = 'none';
    } else {
        if (priceBox) priceBox.innerHTML = `<strong>Total: GH₵ ${subTotal.toFixed(2)}</strong> ${hasCustom ? '<br><small class="text-purple">+ Custom Design price TBD</small>' : ''}`;
        if (priceBoxContainer) priceBoxContainer.style.display = 'block';
    }
}

document.getElementById('quantity')?.addEventListener('input', updateCartUI);

// Add CSS for style selection
const styleEl = document.createElement('style');
styleEl.textContent = `
.style-selectable { cursor:pointer; position:relative; transition:all .2s; }
.style-selectable:hover { transform:translateY(-4px); box-shadow:var(--shadow-md); border-color:var(--pink-300)!important; }
.style-selectable.selected { border:2px solid var(--pink-500)!important; box-shadow:var(--shadow-md); }
.style-check { display:none; position:absolute; top:8px; right:8px; background:var(--pink-500); color:white; border-radius:50%; width:26px; height:26px; align-items:center; justify-content:center; }
.style-selectable.selected .style-check { display:flex; }
.btn-outline-purple { border-color: var(--purple-500); color: var(--purple-500); }
.btn-outline-purple:hover { background: var(--purple-500); color: white; }
.smaller { font-size: 0.75rem; }
.cursor-pointer { cursor: pointer; }
`;
document.head.appendChild(styleEl);
// Voice Recorder Logic
let mediaRecorder;
let audioChunks = [];
let startTime;
let timerInterval;

const startBtn = document.getElementById('startRecord');
const stopBtn = document.getElementById('stopRecord');
const timerDisp = document.getElementById('recordTimer');
const preview = document.getElementById('voicePreview');
const audioPlayback = document.getElementById('audioPlayback');
const base64Input = document.getElementById('customVoiceBase64');
const imageInput = document.getElementById('customImageInput');
const imageBase64 = document.getElementById('customImageBase64');
const imagePreview = document.getElementById('imagePreview');
const imgPreview = document.getElementById('imgPreview');

if (imageInput) {
    imageInput.onchange = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onloadend = () => {
                imageBase64.value = reader.result;
                imgPreview.src = reader.result;
                imagePreview.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        }
    };
}

if (startBtn) {
    startBtn.onclick = async () => {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];

        mediaRecorder.ondataavailable = (event) => audioChunks.push(event.data);
        mediaRecorder.onstop = () => {
            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            const reader = new FileReader();
            reader.readAsDataURL(audioBlob);
            reader.onloadend = () => {
                base64Input.value = reader.result;
                audioPlayback.src = reader.result;
                preview.classList.remove('d-none');
            };
            stream.getTracks().forEach(track => track.stop());
        };

        mediaRecorder.start();
        startBtn.classList.add('d-none');
        stopBtn.classList.remove('d-none');
        timerDisp.classList.remove('d-none');
        
        startTime = Date.now();
        timerInterval = setInterval(() => {
            const sec = Math.floor((Date.now() - startTime) / 1000);
            timerDisp.textContent = Math.floor(sec/60) + ":" + (sec%60).toString().padStart(2,'0');
        }, 1000);
    };

    stopBtn.onclick = () => {
        mediaRecorder.stop();
        stopBtn.classList.add('d-none');
        startBtn.classList.remove('d-none');
        startBtn.textContent = "Re-record";
        clearInterval(timerInterval);
    };
}

// Payment Toggle Logic
document.querySelector('select[name="payment_method"]')?.addEventListener('change', function() {
    const instr = document.getElementById('paymentInstructions');
    const momo = document.getElementById('momoDetails');
    const bank = document.getElementById('bankDetails');
    const cash = document.getElementById('cashDetails');

    instr.style.display = 'block';
    momo.style.display = this.value === 'mobile_money' ? 'block' : 'none';
    bank.style.display = this.value === 'bank_transfer' ? 'block' : 'none';
    cash.style.display = this.value === 'cash' ? 'block' : 'none';
});
</script>

<?php require_once __DIR__ . '/../includes/customer_footer.php'; ?>
