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

// Group orders by batch_id
$groups = [];
foreach ($orders as $o) {
    if ($o['batch_id']) {
        $groups[$o['batch_id']][] = $o;
    } else {
        $groups['standalone_'.$o['id']][] = $o; // ID prevents collision
    }
}

// Add payment fields if missing (Schema Check)
$res = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name='orders' AND column_name='payment_status'");
if (!$res->fetch()) {
    $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT 'unpaid'");
    $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS tx_ref VARCHAR(100) UNIQUE");
    $db->exec("ALTER TABLE sales ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100)");
}
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

<?php if ($groups): ?>
  <div class="row g-3">
    <?php foreach ($groups as $bid => $items): 
        $o = $items[0]; // Representative order for the card header
        $isBatch = (count($items) > 1 || (!str_contains($bid, 'standalone_')));
    ?>
      <div class="col-12"><div class="order-card p-3 shadow-sm border rounded bg-white">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
          <div>
            <div class="order-id fw-bold text-pink">
                <?= $isBatch ? '<i class="bi bi-collection-fill me-1"></i>Batch Order' : 'Order #'.$o['id'] ?>
                · <?= timeAgo($o['created_at']) ?>
            </div>
            <?php if ($isBatch): ?><div class="text-muted smaller">Batch ID: <?= clean($o['batch_id'] ?? 'N/A') ?></div><?php endif; ?>
          </div>
          <div class="text-end">
            <div class="text-muted" style="font-size:.75rem;">Placed on <?= date('M d, Y', strtotime($o['created_at'])) ?></div>
          </div>
        </div>

        <!-- Items in this batch -->
        <div class="batch-items d-flex flex-column gap-2 mb-3">
          <?php foreach ($items as $item): ?>
            <div class="p-2 border rounded bg-light d-flex justify-content-between align-items-center">
              <div>
                <span class="fw-bold"><?= clean($item['style_name'] ?? 'Custom Design') ?></span>
                <?php if ($item['is_custom']): ?><span class="badge bg-purple-100 text-purple-700 mx-1">Custom</span><?php endif; ?>
                <div class="text-muted smaller">
                    <i class="bi bi-layers me-1"></i><?= $item['fabric_id'] ? clean($item['fabric_name']).' ('.clean($item['fabric_color']).')' : 'Custom: '.clean($item['custom_fabric'] ?? '—') ?>
                    · Qty: <?= $item['quantity'] ?>
                </div>
              </div>
              <div class="text-end">
                <?= statusBadge($item['status']) ?>
                <div class="fw-bold text-pink"><?= ghcFormat($item['total_amount']) ?></div>
                <div class="d-flex gap-1 mt-1 justify-content-end">
                  <?php if ($item['status'] === 'pending'): ?>
                    <form action="<?= BASE_URL ?>/customer/cancel_order.php" method="POST" onsubmit="return confirm('Cancel this item?')">
                      <input type="hidden" name="order_id" value="<?= $item['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Cancel"><i class="bi bi-x-circle"></i></button>
                    </form>
                  <?php endif; ?>
                  <?php if ($item['status'] === 'approved' && ($item['payment_status'] ?? 'unpaid') !== 'paid'): ?>
                    <a href="<?= BASE_URL ?>/customer/pay.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-fashion py-0 px-2" style="font-size:0.75rem;">Pay</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Shared Notes/Payment Info -->
        <div class="d-flex justify-content-between align-items-end mt-2 flex-wrap gap-2">
            <div class="notes-section flex-grow-1">
                <?php if ($o['notes']): ?>
                    <div class="text-muted smaller opacity-75">
                        <i class="bi bi-chat-left-dots me-1"></i><?= clean(substr($o['notes'],0,150)) ?>...
                    </div>
                <?php endif; ?>
                <div class="mt-1">
                    <span class="badge bg-secondary smaller"><?= strtoupper(str_replace('_',' ',$o['payment_method']??'CASH')) ?></span>
                    <?php if ($o['payment_reference']): ?>
                        <code class="smaller ms-1">ID: <?= clean($o['payment_reference']) ?></code>
                    <?php endif; ?>
                </div>
            </div>
            <div class="total-section text-end">
                <?php 
                    $batchTotal = array_sum(array_column($items, 'total_amount'));
                    $anyCustom = count(array_filter($items, fn($i) => $i['is_custom'])) > 0;
                ?>
                <div class="text-muted smaller">Estimated Batch Total:</div>
                <div class="fw-700 fs-4 text-pink"><?= ghcFormat($batchTotal) ?><?= $anyCustom ? '*' : '' ?></div>
                <?php if ($anyCustom): ?><small class="text-purple smaller">* Includes custom designs TBD</small><?php endif; ?>
            </div>
        </div>
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
