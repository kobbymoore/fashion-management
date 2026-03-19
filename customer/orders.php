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
        if ($col === 'custom_image') $sql = "ALTER TABLE orders ADD COLUMN IF NOT EXISTS $col VARCHAR(255)";
        $db->exec($sql);
    }
}
// Ensure custom_voice is TEXT (in case it was previously VARCHAR)
$db->exec("ALTER TABLE orders ALTER COLUMN custom_voice TYPE TEXT");

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
        $styleRow = null;
        if ($styleId > 0) {
            $styleRow = $db->prepare("SELECT base_price FROM styles WHERE id=?");
            $styleRow->execute([$styleId]); $styleRow = $styleRow->fetch();
        }
        $is_custom = ($styleId == -1) ? 1 : 0;
        $custom_voice = $_POST['custom_voice_base64'] ?? null;
        $custom_desc = trim($_POST['custom_description'] ?? '');
        $custom_img_url = trim($_POST['custom_image_url'] ?? '');

        $sid_val = ($styleId == -1) ? null : $styleId;

        $db->prepare("INSERT INTO orders(customer_id,style_id,fabric_id,quantity,status,notes,self_bust,self_waist,self_hips,self_height,total_amount, is_custom, custom_voice, custom_description, custom_image_url) VALUES(?,?,?,?,'pending',?,?,?,?,?,?,?,?,?,?)")
           ->execute([$cid,$sid_val,$fabricId,$qty,$notes,$sBust,$sWaist,$sHips,$sHeight,$total, $is_custom, $custom_voice, $custom_desc, $custom_img_url]);
        
        $newId = $db->lastInsertId();
        auditLog('place_order',"Customer #$cid placed ".($is_custom?'CUSTOM ':'')."order #$newId");

        // Notify staff
        $staffUsers = $db->query("SELECT id FROM users WHERE role IN('staff','admin') AND is_active=TRUE")->fetchAll();
        foreach ($staffUsers as $su) addNotification($su['id'], "New ".($is_custom?'CUSTOM ':'')."order #$newId received from ".clean($user['name']).".");

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
                <small class="text-muted"><?= clean(substr($s['description'],0,60)) ?>…</small>
              </div>
              <div class="style-check"><i class="bi bi-check-circle-fill"></i></div>
            </div>
          </label>
        </div>
      <?php endforeach; ?>

      <!-- CUSTOM DESIGN OPTION -->
      <div class="col-6 col-sm-4 col-lg-3">
        <label class="style-selector-card" for="style_custom">
          <input type="radio" name="style_id_sel" id="style_custom" value="-1" class="d-none style-radio"
                 <?= (($_POST['style_id'] ?? 0) == -1) ? 'checked' : '' ?>>
          <div class="style-card style-selectable bg-pink-50 border-pink" data-id="-1" data-price="0">
            <div class="d-flex flex-column align-items-center justify-content-center" style="height:150px; background:white;">
              <i class="bi bi-pencil-square text-pink fs-1"></i>
              <span class="fw-bold text-pink small mt-2">REQUEST CUSTOM</span>
            </div>
            <div class="style-card-body">
              <div class="style-card-title">Bespoke / Custom</div>
              <div class="style-card-price">Price: To be Quoted</div>
              <small class="text-muted">Upload your own design & ideas</small>
            </div>
            <div class="style-check"><i class="bi bi-check-circle-fill"></i></div>
          </div>
        </label>
      </div>
    </div>
  </div>

  <!-- Order Form -->
  <div class="col-lg-7">
    <div class="card-studio">
      <div class="card-header"><h5><i class="bi bi-clipboard-fill text-pink me-2"></i>Order Details</h5></div>
      <div class="card-body">
        <form method="POST" id="orderForm" enctype="multipart/form-data">
          <input type="hidden" name="style_id" id="hiddenStyleId" value="<?= (int)($_POST['style_id']??0) ?>">

          <!-- Custom Order Fields -->
          <div id="customOrderFields" class="mb-4 p-3 border rounded bg-light" style="display:none;">
            <div class="alert alert-purple d-flex align-items-center mb-3" style="font-size: 0.85rem; border: 1px dashed var(--purple-300);">
              <i class="bi bi-info-circle-fill fs-5 me-3"></i>
              <div>
                <strong>Pro Tip:</strong> For the best results, upload your design to 
                <a href="https://postimages.org" target="_blank" class="fw-bold text-purple-700">postimages.org</a> 
                and paste the <strong>Direct Link</strong> below! 🚀
              </div>
            </div>
            
            <h6 class="text-pink mb-3"><i class="bi bi-magic me-2"></i>Custom Request Details</h6>
            <div class="mb-3">
              <label class="form-label fw-bold">Design Description *</label>
              <textarea name="custom_description" class="form-control" rows="4" placeholder="Describe the outfit, neckline, length, etc."></textarea>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-bold"><i class="bi bi-link-45deg me-1"></i>Picture Reference (URL)</label>
                <input type="text" name="custom_image_url" class="form-control" placeholder="Paste Direct Link from postimages.org">
                <small class="text-muted">Links work best on our system! 🏙️</small>
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
        <p class="text-muted small mt-1">Call us at <strong>+233 553 423 057</strong><br>or visit our studio.</p>
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
    const sid = card.dataset.id;
    document.getElementById('hiddenStyleId').value = sid;
    
    // Toggle Custom Fields
    const customFields = document.getElementById('customOrderFields');
    if (sid == "-1") {
        customFields.style.display = 'block';
    } else {
        customFields.style.display = 'none';
    }

    // trigger price update
    updateOrderPrice(sid);
  });
});

function updateOrderPrice(styleId) {
  const priceBox = document.getElementById('priceEstimate');
  const priceBoxContainer = document.querySelector('.price-estimate-box');
  if (!priceBox) return;

  if (styleId == "-1") {
      priceBox.textContent = 'To be Quoted';
      priceBoxContainer?.classList.remove('d-none');
      return;
  }
  
  const price = parseFloat(window.stylePrices[styleId] || 0);
  const qty   = parseInt(document.getElementById('quantity')?.value || 1);
  priceBox.textContent = 'GH₵ ' + (price * qty).toFixed(2);
  priceBoxContainer?.classList.remove('d-none');
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
</script>

<?php require_once __DIR__ . '/../includes/customer_footer.php'; ?>
