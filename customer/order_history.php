<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();
if (hasRole('staff')) redirect(BASE_URL.'/admin/dashboard.php');
$db = getDB(); $user = currentUser();
$activePage = 'history'; $pageTitle = 'My Orders';
$cust = $db->prepare("SELECT id FROM customers WHERE user_id=?"); $cust->execute([$user['id']]); $cust = $cust->fetch();
if (!$cust) redirect(BASE_URL.'/auth/logout.php');
$cid = $cust['id'];

$status = $_GET['status'] ?? '';
$page   = max(1,(int)($_GET['page']??1));
$perPage= 10;
$where  = $status ? "AND o.status=?" : "";
$params = $status ? [$cid,$status] : [$cid];

$total = $db->prepare("SELECT COUNT(*) FROM orders o WHERE o.customer_id=? $where"); $total->execute($params);
$totalCount = (int)$total->fetchColumn();
$pg = paginate($totalCount, $perPage, $page);
$stmt = $db->prepare("
    SELECT o.*, s.name AS style_name, s.description AS style_desc, f.name AS fabric_name, f.color AS fabric_color
    FROM orders o LEFT JOIN styles s ON o.style_id=s.id LEFT JOIN fabrics f ON o.fabric_id=f.id
    WHERE o.customer_id=? $where ORDER BY o.created_at DESC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}
");
$stmt->execute($params); $orders = $stmt->fetchAll();
require_once __DIR__ . '/../includes/customer_header.php';
?>

<div class="page-header">
  <div><h3><i class="bi bi-clock-history text-pink me-2"></i>My Orders</h3><div class="subtitle"><?= $totalCount ?> order(s) total</div></div>
  <a href="<?= BASE_URL ?>/customer/orders.php" class="btn btn-fashion"><i class="bi bi-plus me-1"></i>New Order</a>
</div>

<!-- Status filter -->
<div class="d-flex gap-2 mb-3 flex-wrap">
  <a href="?" class="btn btn-sm <?= !$status?'btn-fashion':'btn-outline-secondary' ?>">All (<?= $totalCount ?>)</a>
  <?php foreach(['pending'=>'warning','approved'=>'info','in-progress'=>'primary','completed'=>'success','cancelled'=>'danger'] as $s=>$cls):
    $cnt = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id=? AND status=?"); $cnt->execute([$cid,$s]);
    $c = $cnt->fetchColumn();
    if ($c > 0):
  ?>
    <a href="?status=<?= $s ?>" class="btn btn-sm <?= $status===$s?"btn-$cls":(($s==='in-progress'||$s==='approved')?'btn-outline-secondary':'btn-outline-secondary') ?>">
      <?= ucfirst($s) ?> (<?= $c ?>)
    </a>
  <?php endif; endforeach; ?>
</div>

<?php if ($orders): ?>
  <div class="row g-3">
    <?php foreach ($orders as $o): ?>
      <div class="col-12"><div class="order-card">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
          <div>
            <div class="order-id">Order #<?= $o['id'] ?> · <?= timeAgo($o['created_at']) ?></div>
            <h5 class="mb-1"><?= clean($o['style_name'] ?? 'Custom Style') ?></h5>
            <?php if ($o['fabric_name']): ?>
              <div class="text-muted small"><i class="bi bi-layers me-1"></i><?= clean($o['fabric_name']) ?> (<?= clean($o['fabric_color']) ?>)</div>
            <?php endif; ?>
            <?php if ($o['notes']): ?><div class="text-muted small mt-1"><i class="bi bi-chat-left me-1"></i><?= clean(substr($o['notes'],0,100)) ?></div><?php endif; ?>
          </div>
          <div class="text-end">
            <?= statusBadge($o['status']) ?>
            <div class="fw-700 fs-5 text-pink mt-1"><?= ghcFormat($o['total_amount']) ?></div>
            <div class="text-muted" style="font-size:.75rem;">Qty: <?= $o['quantity'] ?></div>
          </div>
        </div>

        <!-- Status bar -->
        <?php
        $steps = ['pending'=>0,'approved'=>1,'in-progress'=>2,'completed'=>3,'cancelled'=>3];
        $cur = $steps[$o['status']] ?? 0; $isCancelled = $o['status']==='cancelled';
        $labels = ['Placed','Approved','In Progress','Completed'];
        ?>
        <div class="status-timeline mt-3" style="max-width:500px;">
          <?php foreach ($labels as $i => $label): ?>
            <div class="status-step">
              <div class="step-dot <?= $isCancelled?'':($i<$cur?'done':($i===$cur?'active':'')) ?>">
                <?= (!$isCancelled&&$i<$cur) ? '<i class="bi bi-check-lg"></i>' : ($i+1) ?>
              </div>
              <div class="step-label"><?= $label ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($isCancelled): ?><div class="text-danger small mt-2"><i class="bi bi-x-circle me-1"></i>This order was cancelled.</div><?php endif; ?>
      </div></div>
    <?php endforeach; ?>
  </div>
  <?php if ($pg['pages']>1): ?>
    <nav class="mt-3"><ul class="pagination justify-content-center">
      <?php for($i=1;$i<=$pg['pages'];$i++): ?><li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?status=<?= urlencode($status) ?>&page=<?= $i ?>"><?= $i ?></a></li><?php endfor; ?>
    </ul></nav>
  <?php endif; ?>
<?php else: ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-bag-x-fill fs-1 d-block mb-3 text-pink"></i>
    No orders<?= $status ? ' with status <b>'.ucfirst($status).'</b>' : '' ?>.
    <a href="<?= BASE_URL ?>/customer/orders.php" class="btn btn-fashion btn-sm d-block w-auto mx-auto mt-3"><i class="bi bi-plus me-1"></i>Place First Order</a>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/customer_footer.php'; ?>
