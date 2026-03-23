<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireStaff();
$user = currentUser();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT c.*, u.name, u.email, u.phone, u.role, u.created_at,
           (SELECT COUNT(*) FROM orders WHERE customer_id=c.id) AS order_count
    FROM customers c JOIN users u ON c.user_id=u.id WHERE c.id=?
");
$stmt->execute([$id]);
$customer = $stmt->fetch();
if (!$customer) { setFlash('danger','Customer not found.'); redirect(BASE_URL.'/admin/customers.php'); }

$measurements = $db->prepare("SELECT m.*, u.name AS recorded_by_name FROM measurements m LEFT JOIN users u ON m.recorded_by=u.id WHERE m.customer_id=? ORDER BY m.created_at DESC");
$measurements->execute([$id]);
$measurements = $measurements->fetchAll();

$orders = $db->prepare("
    SELECT o.*, s.name AS style_name, f.name AS fabric_name
    FROM orders o LEFT JOIN styles s ON o.style_id=s.id LEFT JOIN fabrics f ON o.fabric_id=f.id
    WHERE o.customer_id=? ORDER BY o.created_at DESC
");
$orders->execute([$id]);
$orders = $orders->fetchAll();

$activePage = 'customers';
$pageTitle  = 'Customer Profile';
$breadcrumb = ['Customers'=>BASE_URL.'/admin/customers.php', 'Profile'=>null];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
  <!-- Profile Card -->
  <div class="col-lg-4">
    <div class="card-studio text-center p-4 mb-3">
      <div class="profile-avatar-lg"><?= strtoupper(substr($customer['name'],0,1)) ?></div>
      <h5 class="mb-0"><?= clean($customer['name']) ?></h5>
      <span class="badge bg-pink-soft text-pink border mt-1">Customer</span>
      <hr class="divider-pink">
      <ul class="list-unstyled text-start small">
        <li class="mb-2"><i class="bi bi-envelope text-pink me-2"></i><?= clean($customer['email']) ?></li>
        <li class="mb-2"><i class="bi bi-telephone text-pink me-2"></i><?= clean($customer['phone']) ?></li>
        <li class="mb-2"><i class="bi bi-geo-alt text-pink me-2"></i><?= clean($customer['address'] ?? 'Not provided') ?></li>
        <li><i class="bi bi-calendar text-pink me-2"></i>Joined <?= date('M j, Y', strtotime($customer['created_at'])) ?></li>
      </ul>
      <div class="d-flex gap-2 mt-3">
        <?php if ($user['role'] === 'admin'): ?>
        <a href="<?= BASE_URL ?>/admin/customer_edit.php?id=<?= $customer['user_id'] ?>" class="btn btn-fashion btn-sm flex-fill">
          <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/admin/measurement_form.php?customer_id=<?= $id ?>" class="btn btn-outline-fashion btn-sm flex-fill">
          <i class="bi bi-rulers me-1"></i>Measure
        </a>
      </div>
    </div>

    <!-- Stats -->
    <div class="card-studio p-3">
      <h6 class="mb-3 text-muted"><i class="bi bi-bar-chart me-2"></i>Quick Stats</h6>
      <div class="d-flex justify-content-between mb-2 small">
        <span>Total Orders</span><strong><?= $customer['order_count'] ?></strong>
      </div>
      <div class="d-flex justify-content-between mb-2 small">
        <span>Measurements</span><strong><?= count($measurements) ?></strong>
      </div>
      <?php
      $spent = $db->prepare("SELECT COALESCE(SUM(s.amount),0) FROM sales s JOIN orders o ON s.order_id=o.id WHERE o.customer_id=?");
      $spent->execute([$id]);
      ?>
      <div class="d-flex justify-content-between small">
        <span>Total Spent</span><strong class="text-pink"><?= ghcFormat($spent->fetchColumn()) ?></strong>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="col-lg-8">
    <ul class="nav nav-tabs mb-0 border-0" id="profileTabs">
      <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabOrders">Orders (<?= count($orders) ?>)</a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabMeasure">Measurements (<?= count($measurements) ?>)</a></li>
    </ul>
    <div class="tab-content card-studio rounded-top-0">
      <div class="tab-pane fade show active p-0" id="tabOrders">
        <div class="table-responsive">
          <table class="table table-studio mb-0">
            <thead><tr><th>#</th><th>Style</th><th>Fabric</th><th>Status</th><th>Amount</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td><a href="<?= BASE_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>" class="text-pink fw-600">#<?= $o['id'] ?></a></td>
                <td><?= clean($o['style_name'] ?? '—') ?></td>
                <td><?= clean($o['fabric_name'] ?? '—') ?></td>
                <td><?= statusBadge($o['status']) ?></td>
                <td><?= ghcFormat($o['total_amount']) ?></td>
                <td class="small text-muted"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                <td><a href="<?= BASE_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?><tr><td colspan="7" class="text-center text-muted py-4">No orders yet</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="tab-pane fade p-3" id="tabMeasure">
        <?php if ($measurements): ?>
          <?php foreach ($measurements as $m): ?>
            <div class="border rounded-xl p-3 mb-3 bg-light" style="border-color: var(--pink-100)!important;">
              <div class="d-flex justify-content-between mb-2">
                <strong class="small">Recorded by <?= clean($m['recorded_by_name'] ?? 'Staff') ?></strong>
                <small class="text-muted"><?= date('M j, Y', strtotime($m['created_at'])) ?></small>
              </div>
              <div class="measure-grid">
                <?php $fields = ['bust'=>'Bust','waist'=>'Waist','hips'=>'Hips','height'=>'Height','shoulder'=>'Shoulder','inseam'=>'Inseam','sleeve_length'=>'Sleeve','neck'=>'Neck'];
                foreach ($fields as $key => $label): if ($m[$key]): ?>
                  <div class="measure-field">
                    <label><?= $label ?></label>
                    <div class="fw-600"><?= $m[$key] ?> <small class="text-muted">in</small></div>
                  </div>
                <?php endif; endforeach; ?>
              </div>
              <?php if ($m['notes']): ?><p class="text-muted small mt-2 mb-0"><i class="bi bi-chat-left-text me-1"></i><?= clean($m['notes']) ?></p><?php endif; ?>
              <a href="<?= BASE_URL ?>/admin/measurement_form.php?id=<?= $m['id'] ?>&customer_id=<?= $id ?>" class="btn btn-sm btn-outline-fashion mt-2"><i class="bi bi-pencil me-1"></i>Edit</a>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-center py-4 text-muted">
            <i class="bi bi-rulers fs-1 d-block mb-2 text-pink"></i>
            No measurements recorded yet.
            <a href="<?= BASE_URL ?>/admin/measurement_form.php?customer_id=<?= $id ?>" class="btn btn-fashion btn-sm d-block w-auto mx-auto mt-3">
              <i class="bi bi-plus-lg me-1"></i>Add Measurements
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
