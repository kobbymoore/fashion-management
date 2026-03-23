<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireStaff();
$db = getDB();
$user = currentUser();
$activePage = 'orders';
$pageTitle  = 'Order Management';
$breadcrumb = ['Orders'=>null];

// Handle status update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_status'])) {
    $oid    = (int)$_POST['order_id'];
    $status = $_POST['status'];
    $allowed = ['pending','approved','in-progress','completed','cancelled'];
    if (in_array($status, $allowed)) {
        $db->prepare("UPDATE orders SET status=?,updated_at=NOW() WHERE id=?")->execute([$status,$oid]);
        // Auto-deduct inventory on completion
        if ($status === 'completed') {
            $ord = $db->prepare("SELECT fabric_id,quantity FROM orders WHERE id=?");
            $ord->execute([$oid]);
            $row = $ord->fetch();
            if ($row['fabric_id']) {
                $db->prepare("UPDATE fabrics SET quantity_yards=quantity_yards-(? * 2.5) WHERE id=?")->execute([$row['quantity'],$row['fabric_id']]);
                $user = currentUser();
                $db->prepare("INSERT INTO inventory_log(fabric_id,change_qty,reason,recorded_by) VALUES(?,?,?,?)")
                   ->execute([$row['fabric_id'], -($row['quantity']*2.5), "Order #$oid completed", $user['id']]);
            }
            // Notify customer
            $custStmt = $db->prepare("SELECT c.user_id FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.id=?");
            $custStmt->execute([$oid]);
            $cust = $custStmt->fetch();
            if ($cust) addNotification($cust['user_id'], "Your order #$oid is now complete! Please visit us for pickup.");
        }
        auditLog('update_order_status',"Order #$oid → $status");
        setFlash('success',"Order #$oid status updated to ".ucfirst($status).".");
    }
    redirect(BASE_URL.'/admin/orders.php');
}

$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1,(int)($_GET['page']??1));
$perPage      = 15;

$where = 'WHERE 1=1';
$params = [];
if ($user['role'] === 'staff') {
    $where .= ' AND o.assigned_to = ?';
    $params[] = $user['id'];
}
if ($filterStatus) { $where .= ' AND o.status=?'; $params[] = $filterStatus; }
if ($search) { $where .= ' AND (u.name ILIKE ? OR CAST(o.id AS TEXT) LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$totalStmt = $db->prepare("SELECT COUNT(*) FROM orders o JOIN customers c ON o.customer_id=c.id JOIN users u ON c.user_id=u.id $where");
$totalStmt->execute($params);
$totalCount = (int)$totalStmt->fetchColumn();
$pg = paginate($totalCount, $perPage, $page);

$stmt = $db->prepare("
    SELECT o.*, u.name AS customer_name, s.name AS style_name, f.name AS fabric_name,
           au.name AS assigned_name
    FROM orders o
    JOIN customers c ON o.customer_id=c.id
    JOIN users u ON c.user_id=u.id
    LEFT JOIN styles s ON o.style_id=s.id
    LEFT JOIN fabrics f ON o.fabric_id=f.id
    LEFT JOIN users au ON o.assigned_to=au.id
    $where ORDER BY o.created_at DESC
    LIMIT {$pg['perPage']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$staff = $db->query("SELECT id,name FROM users WHERE role IN('staff','admin') AND is_active=TRUE")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h3><i class="bi bi-bag-fill text-pink me-2"></i>Order Management</h3>
    <div class="subtitle"><?= $totalCount ?> order(s) total</div>
  </div>
</div>

<!-- Filters -->
<div class="card-studio mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
      <div class="search-box" style="min-width:220px;">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control form-control-sm" name="q" placeholder="Customer name or order #" value="<?= clean($search) ?>">
      </div>
      <select class="form-select form-select-sm" name="status" style="width:160px;">
        <option value="">All Statuses</option>
        <?php foreach(['pending','approved','in-progress','completed','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-fashion btn-sm">Filter</button>
      <?php if ($filterStatus || $search): ?>
        <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline-secondary btn-sm">Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Quick status tabs -->
<div class="d-flex gap-2 mb-3 flex-wrap">
  <?php
  $cntSql = "SELECT status, COUNT(*) cnt FROM orders";
  $cntParams = [];
  if ($user['role'] === 'staff') {
      $cntSql .= " WHERE assigned_to = ?";
      $cntParams = [$user['id']];
  }
  $cntSql .= " GROUP BY status";
  $cntStmt = $db->prepare($cntSql);
  $cntStmt->execute($cntParams);
  $statusCounts = $cntStmt->fetchAll(PDO::FETCH_KEY_PAIR);
  
  $allStatuses = ['pending','approved','in-progress','completed','cancelled'];
  foreach ($allStatuses as $s):
    $cnt = $statusCounts[$s] ?? 0;
    $active = $filterStatus===$s ? 'btn-fashion' : 'btn-outline-secondary';
  ?>
    <a href="?status=<?= $s ?>" class="btn btn-sm <?= $active ?>">
      <?= ucfirst($s) ?> <span class="badge bg-white text-dark ms-1"><?= $cnt ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="card-studio">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-studio mb-0">
        <thead><tr><th>#</th><th>Customer</th><th>Batch</th><th>Style</th><th>Fabric</th><th>Qty</th><th>Assigned</th><th>Status</th><th>Amount</th><th>Actions</th></tr></thead>
        <tbody>
        <?php 
        $lastBatch = null;
        $batchToggle = false;
        foreach ($orders as $o): 
            if ($o['batch_id'] && $o['batch_id'] !== $lastBatch) {
                $batchToggle = !$batchToggle;
                $lastBatch = $o['batch_id'];
            } elseif (!$o['batch_id']) {
                $batchToggle = false;
                $lastBatch = null;
            }
            $rowClass = ($o['batch_id'] && $batchToggle) ? 'bg-pink-50' : '';
        ?>
          <tr class="<?= $rowClass ?>">
            <td><a href="<?= BASE_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>" class="fw-600 text-pink">#<?= $o['id'] ?></a></td>
            <td>
                <?= clean($o['customer_name']) ?><br>
                <small class="text-muted smaller"><?= timeAgo($o['created_at']) ?></small>
            </td>
            <td>
                <?php if ($o['batch_id']): ?>
                    <span class="badge bg-pink-100 text-pink-700 smaller" title="<?= $o['batch_id'] ?>">
                        <i class="bi bi-collection-fill"></i>
                    </span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td class="small">
              <?php if ($o['is_custom']): ?>
                <span class="badge bg-purple-100 text-purple-700 border-purple-200 mb-1" style="font-size:0.65rem">
                  <i class="bi bi-magic me-1"></i>CUSTOM
                </span><br>
              <?php endif; ?>
              <?= clean($o['style_name'] ?? 'Bespoke Order') ?>
            </td>
            <td class="small"><?= $o['fabric_id'] ? clean($o['fabric_name']) : 'Custom: '.clean($o['custom_fabric'] ?? '—') ?></td>
            <td><?= $o['quantity'] ?></td>
            <td class="small"><?= clean($o['assigned_name'] ?? '—') ?></td>
            <td><?= statusBadge($o['status']) ?></td>
            <td class="fw-600"><?= ghcFormat($o['total_amount']) ?></td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= BASE_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                <button class="btn btn-sm btn-outline-fashion" data-bs-toggle="modal" data-bs-target="#statusModal<?= $o['id'] ?>"><i class="bi bi-arrow-repeat"></i></button>
              </div>
              <!-- Status Modal -->
              <div class="modal fade" id="statusModal<?= $o['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-sm">
                  <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Update Order #<?= $o['id'] ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <form method="POST">
                      <div class="modal-body">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <input type="hidden" name="update_status" value="1">
                        <label class="form-label fw-600">New Status</label>
                        <select class="form-select" name="status">
                          <?php foreach(['pending','approved','in-progress','completed','cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="modal-footer"><button class="btn btn-fashion">Save</button></div>
                    </form>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
          <tr><td colspan="10" class="text-center text-muted py-5"><i class="bi bi-bag-x-fill fs-1 d-block mb-2"></i>No orders found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-body border-top d-flex justify-content-between">
    <small class="text-muted">Showing <?= count($orders) ?> of <?= $totalCount ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for($i=1;$i<=$pg['pages'];$i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?status=<?= urlencode($filterStatus) ?>&q=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
