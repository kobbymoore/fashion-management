<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireStaff();
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("
    SELECT o.*, u.name AS customer_name, u.email, u.phone,
           s.name AS style_name, s.base_price,
           f.name AS fabric_name, f.fabric_type, f.color,
           au.name AS assigned_name, c.id AS customer_id
    FROM orders o
    JOIN customers c ON o.customer_id=c.id
    JOIN users u ON c.user_id=u.id
    LEFT JOIN styles s ON o.style_id=s.id
    LEFT JOIN fabrics f ON o.fabric_id=f.id
    LEFT JOIN users au ON o.assigned_to=au.id
    WHERE o.id=?
");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) { setFlash('danger','Order not found.'); redirect(BASE_URL.'/admin/orders.php'); }
$activePage = 'orders';
$pageTitle  = "Order #$id";
$breadcrumb = ['Orders'=>BASE_URL.'/admin/orders.php', "Order #$id"=>null];
$staff = $db->query("SELECT id,name FROM users WHERE role IN('staff','admin') AND is_active=1")->fetchAll();
$sale  = $db->prepare("SELECT * FROM sales WHERE order_id=?"); $sale->execute([$id]); $sale = $sale->fetch();
$measure = $db->prepare("SELECT m.* FROM measurements m JOIN orders_measurements om ON om.measurement_id=m.id WHERE om.order_id=? UNION SELECT m.* FROM measurements m WHERE m.customer_id=? ORDER BY created_at DESC LIMIT 1");

// Handle assign
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['assign'])) {
    $aId = (int)$_POST['assigned_to'];
    $db->prepare("UPDATE orders SET assigned_to=?,updated_at=NOW() WHERE id=?")->execute([$aId ?: null, $id]);
    auditLog('assign_order',"Order #$id assigned to user #$aId");
    setFlash('success','Order assigned successfully.'); redirect(BASE_URL.'/admin/order_detail.php?id='.$id);
}

$statusSteps = ['pending'=>0,'approved'=>1,'in-progress'=>2,'completed'=>3,'cancelled'=>3];
$curStep = $statusSteps[$order['status']] ?? 0;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card-studio mb-3">
      <div class="card-header">
        <h5><i class="bi bi-bag-fill text-pink me-2"></i>Order #<?= $id ?> Details</h5>
        <?= statusBadge($order['status']) ?>
      </div>
      <div class="card-body">
        <!-- Status Timeline -->
        <div class="status-timeline mb-4">
          <?php $steps = ['Pending','Approved','In Progress','Completed'];
          foreach ($steps as $i => $label): ?>
            <div class="status-step">
              <div class="step-dot <?= $i < $curStep ? 'done' : ($i===$curStep?'active':'') ?>">
                <?= $i < $curStep ? '<i class="bi bi-check-lg"></i>' : ($i+1) ?>
              </div>
              <div class="step-label"><?= $label ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="row g-3">
          <div class="col-sm-6"><label class="text-muted small">Style</label><p class="fw-600"><?= clean($order['style_name'] ?? '—') ?></p></div>
          <div class="col-sm-6"><label class="text-muted small">Fabric</label><p class="fw-600"><?= clean($order['fabric_name'] ?? '—') ?> <?= $order['fabric_name']?'('.clean($order['color']).')':'' ?></p></div>
          <div class="col-sm-6"><label class="text-muted small">Quantity</label><p class="fw-600"><?= $order['quantity'] ?></p></div>
          <div class="col-sm-6"><label class="text-muted small">Total Amount</label><p class="fw-600 text-pink fs-5"><?= ghcFormat($order['total_amount']) ?></p></div>
          <?php if ($order['notes']): ?>
          <div class="col-12"><label class="text-muted small">Customer Notes</label><p class="bg-light rounded p-2 small"><?= clean($order['notes']) ?></p></div>
          <?php endif; ?>
        </div>
        <?php if ($order['self_bust'] || $order['self_waist']): ?>
        <hr class="divider-pink">
        <h6 class="mb-2 text-muted">Self-Reported Measurements</h6>
        <div class="row g-2 small">
          <?php foreach(['bust','waist','hips','height'] as $f): if($order["self_$f"]): ?>
            <div class="col-6 col-sm-3"><strong><?= ucfirst($f) ?></strong><br><?= $order["self_$f"] ?> in</div>
          <?php endif; endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Assignment -->
    <div class="card-studio">
      <div class="card-header"><h5><i class="bi bi-person-badge-fill text-pink me-2"></i>Assign to Tailor</h5></div>
      <div class="card-body">
        <form method="POST" class="d-flex gap-2 align-items-end flex-wrap">
          <div class="flex-grow-1">
            <label class="form-label fw-600 small">Tailor / Staff Member</label>
            <select class="form-select" name="assigned_to">
              <option value="">— Unassigned —</option>
              <?php foreach ($staff as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $order['assigned_to']==$s['id']?'selected':'' ?>><?= clean($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" name="assign" class="btn btn-fashion"><i class="bi bi-check2 me-1"></i>Assign</button>
        </form>
        <?php if ($order['assigned_name']): ?>
          <div class="mt-2 text-muted small"><i class="bi bi-person-check-fill text-success me-1"></i>Currently assigned to <strong><?= clean($order['assigned_name']) ?></strong></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Customer Card -->
    <div class="card-studio mb-3">
      <div class="card-header"><h5><i class="bi bi-person-fill text-pink me-2"></i>Customer</h5></div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="sidebar-avatar"><?= strtoupper(substr($order['customer_name'],0,1)) ?></div>
          <div>
            <strong><?= clean($order['customer_name']) ?></strong><br>
            <small class="text-muted"><?= clean($order['email']) ?></small>
          </div>
        </div>
        <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= clean($order['phone']) ?></small><br>
        <a href="<?= BASE_URL ?>/admin/customer_view.php?id=<?= $order['customer_id'] ?>" class="btn btn-outline-fashion btn-sm mt-3 w-100">
          <i class="bi bi-person me-1"></i>View Profile
        </a>
      </div>
    </div>

    <!-- Payment Card -->
    <div class="card-studio mb-3">
      <div class="card-header"><h5><i class="bi bi-cash text-pink me-2"></i>Payment</h5></div>
      <div class="card-body">
        <?php if ($sale): ?>
          <div class="d-flex justify-content-between mb-1 small">
            <span>Amount Paid</span><strong class="text-success"><?= ghcFormat($sale['amount']) ?></strong>
          </div>
          <div class="d-flex justify-content-between mb-1 small">
            <span>Method</span><strong><?= str_replace('_',' ',ucfirst($sale['payment_method'])) ?></strong>
          </div>
          <div class="d-flex justify-content-between small">
            <span>Sale Date</span><strong><?= $sale['sale_date'] ?></strong>
          </div>
        <?php else: ?>
          <p class="text-muted small">No payment recorded yet.</p>
          <?php if ($order['status']==='completed'): ?>
          <a href="<?= BASE_URL ?>/admin/sale_form.php?order_id=<?= $id ?>" class="btn btn-success btn-sm w-100">
            <i class="bi bi-plus me-1"></i>Record Payment
          </a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="card-studio">
      <div class="card-header"><h5>Quick Actions</h5></div>
      <div class="card-body d-flex flex-column gap-2">
        <a href="<?= BASE_URL ?>/admin/measurement_form.php?customer_id=<?= $order['customer_id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-rulers me-2"></i>View/Add Measurements</a>
        <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-2"></i>Back to Orders</a>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
