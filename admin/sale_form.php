<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireStaff();
$db = getDB();
$activePage = 'sales';
$pageTitle  = 'Record Sale';
$breadcrumb = ['Sales'=>BASE_URL.'/admin/sales.php', 'Record Sale'=>null];

$orderId = (int)($_GET['order_id'] ?? 0);
$completedOrders = $db->query("
    SELECT o.id, u.name AS customer_name, o.total_amount
    FROM orders o JOIN customers c ON o.customer_id=c.id JOIN users u ON c.user_id=u.id
    WHERE o.status='completed' AND o.id NOT IN (SELECT order_id FROM sales)
")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $oid   = (int)$_POST['order_id'];
    $amt   = (float)$_POST['amount'];
    $meth  = $_POST['payment_method'] ?? 'cash';
    $date  = $_POST['sale_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    $user  = currentUser();

    if (!$oid) $errors[] = 'Order is required.';
    if ($amt <= 0) $errors[] = 'Amount must be greater than zero.';

    if (!$errors) {
        $db->prepare("INSERT INTO sales(order_id,amount,payment_method,recorded_by,sale_date,notes) VALUES(?,?,?,?,?,?)")
           ->execute([$oid,$amt,$meth,$user['id'],$date,$notes]);
        auditLog('record_sale',"Sale recorded for order #$oid, amount: $amt");
        setFlash('success','Sale recorded successfully!');
        redirect(BASE_URL.'/admin/sales.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div style="max-width:600px;">
  <div class="page-header">
    <div><h3><i class="bi bi-cash-stack text-pink me-2"></i>Record Sale</h3><div class="subtitle">Link a payment to a completed order</div></div>
    <a href="<?= BASE_URL ?>/admin/sales.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
  <div class="card-studio">
    <div class="card-body">
      <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <?php if (!$completedOrders): ?>
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No completed orders without a recorded sale. Mark orders as completed first.</div>
      <?php else: ?>
      <form method="POST">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-600">Completed Order *</label>
            <select class="form-select" name="order_id" required>
              <option value="">— Select Order —</option>
              <?php foreach ($completedOrders as $o): ?>
                <option value="<?= $o['id'] ?>" <?= $orderId==$o['id']?'selected':'' ?>>#<?= $o['id'] ?> – <?= clean($o['customer_name']) ?> (<?= ghcFormat($o['total_amount']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-600">Amount (GH₵) *</label>
            <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-600">Payment Method</label>
            <select class="form-select" name="payment_method">
              <option value="cash">Cash</option>
              <option value="mobile_money">Mobile Money</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-600">Sale Date</label>
            <input type="date" class="form-control" name="sale_date" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-12">
            <label class="form-label fw-600">Notes</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="Optional notes…"></textarea>
          </div>
        </div>
        <hr class="divider-pink mt-3">
        <button type="submit" class="btn btn-fashion"><i class="bi bi-save me-2"></i>Record Sale</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
