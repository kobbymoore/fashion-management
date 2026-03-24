<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();
if (hasRole('staff')) redirect(BASE_URL.'/admin/dashboard.php');

$db   = getDB();
$user = currentUser();
$activePage = 'dashboard';
$pageTitle  = 'My Dashboard';

// Get customer record
$custStmt = $db->prepare("SELECT * FROM customers WHERE user_id=?");
$custStmt->execute([$user['id']]);
$customer = $custStmt->fetch();
if (!$customer) { setFlash('danger','Customer profile not found.'); redirect(BASE_URL.'/auth/logout.php'); }
$cid = $customer['id'];

$totalOrders    = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id=?"); $totalOrders->execute([$cid]);
$pendingOrders  = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id=? AND status='pending'"); $pendingOrders->execute([$cid]);
$completedOrders= $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id=? AND status='completed'"); $completedOrders->execute([$cid]);

$recentOrders = $db->prepare("
    SELECT o.*, s.name AS style_name, f.name AS fabric_name
    FROM orders o LEFT JOIN styles s ON o.style_id=s.id LEFT JOIN fabrics f ON o.fabric_id=f.id
    WHERE o.customer_id=? ORDER BY o.created_at DESC LIMIT 4
");
$recentOrders->execute([$cid]);
$recentOrders = $recentOrders->fetchAll();

$hasMeasure = $db->prepare("SELECT id FROM measurements WHERE customer_id=?"); $hasMeasure->execute([$cid]);
$hasMeasure = (bool)$hasMeasure->fetch();

// Notifications
$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$notifs->execute([$user['id']]);
$notifs = $notifs->fetchAll();
$db->prepare("UPDATE notifications SET is_read=TRUE WHERE user_id=?")->execute([$user['id']]);

require_once __DIR__ . '/../includes/customer_header.php';
?>

<div class="row g-3 mb-4 align-items-stretch">
  <!-- Welcome -->
  <div class="col-lg-7">
    <div class="card h-100" style="background:linear-gradient(135deg,var(--dark) 0%,var(--dark-3) 50%,var(--pink-700) 100%);border:none;border-radius:var(--radius);color:white;padding:2rem;">
      <h3 class="mb-1" style="color:white;">Welcome back, <?= clean(explode(' ',$user['name'])[0]) ?>! 👋</h3>
      <p style="color:rgba(255,255,255,.7);">Here's a summary of your fashion journey with us.</p>
      <div class="d-flex gap-3 mt-3 flex-wrap">
        <div class="text-center"><div style="font-size:1.75rem;font-weight:700;"><?= $totalOrders->fetchColumn() ?></div><div style="font-size:.8rem;color:rgba(255,255,255,.6);">Total Orders</div></div>
        <div class="text-center"><div style="font-size:1.75rem;font-weight:700;color:var(--gold);"><?= $pendingOrders->fetchColumn() ?></div><div style="font-size:.8rem;color:rgba(255,255,255,.6);">Pending</div></div>
        <div class="text-center"><div style="font-size:1.75rem;font-weight:700;color:#10b981;"><?= $completedOrders->fetchColumn() ?></div><div style="font-size:.8rem;color:rgba(255,255,255,.6);">Completed</div></div>
      </div>
      <a href="<?= BASE_URL ?>/customer/orders.php" class="btn btn-fashion mt-3 align-self-start">
        <i class="bi bi-bag-plus me-2"></i>Place New Order
      </a>
    </div>
  </div>

  <!-- Quick Links -->
  <div class="col-lg-5">
    <div class="row g-2 h-100">
      <div class="col-6">
        <a href="<?= BASE_URL ?>/customer/orders.php" class="service-card d-block text-decoration-none h-100">
          <div class="service-icon"><i class="bi bi-bag-plus-fill"></i></div>
          <strong class="d-block small">New Order</strong>
          <small class="text-muted">Browse styles & order</small>
        </a>
      </div>
      <div class="col-6">
        <a href="<?= BASE_URL ?>/customer/order_history.php" class="service-card d-block text-decoration-none h-100">
          <div class="service-icon"><i class="bi bi-clock-history"></i></div>
          <strong class="d-block small">Order History</strong>
          <small class="text-muted">Track your orders</small>
        </a>
      </div>
      <div class="col-6">
        <a href="<?= BASE_URL ?>/customer/profile.php" class="service-card d-block text-decoration-none h-100">
          <div class="service-icon"><i class="bi bi-person-fill"></i></div>
          <strong class="d-block small">My Profile</strong>
          <small class="text-muted">Update info</small>
        </a>
      </div>
      <div class="col-6">
        <a href="<?= BASE_URL ?>/customer/measurements.php" class="service-card d-block text-decoration-none h-100 <?= !$hasMeasure?'border-danger':'' ?>">
          <div class="service-icon" style="<?= !$hasMeasure?'background:#fef2f2;color:#ef4444;':'' ?>"><i class="bi bi-rulers"></i></div>
          <strong class="d-block small"><?= $hasMeasure?'Measurements':'No Measurements' ?></strong>
          <small class="text-muted"><?= $hasMeasure?'View/Update your sizes':'Set your measurements' ?></small>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Recent Orders -->
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card-studio">
      <div class="card-header">
        <h5><i class="bi bi-bag-fill text-pink me-2"></i>Recent Orders</h5>
        <a href="<?= BASE_URL ?>/customer/order_history.php" class="btn btn-sm btn-outline-fashion">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if ($recentOrders): ?>
          <div class="table-responsive"><table class="table table-studio mb-0">
            <thead><tr><th>#</th><th>Style</th><th>Fabric</th><th>Status</th><th>Amount</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($recentOrders as $o): ?>
              <tr>
                <td class="fw-600 text-pink">#<?= $o['id'] ?></td>
                <td><?= clean($o['style_name'] ?? '—') ?></td>
                <td class="small"><?= clean($o['fabric_name'] ?? '—') ?></td>
                <td><?= statusBadge($o['status']) ?></td>
                <td><?= ghcFormat($o['total_amount']) ?></td>
                <td class="small text-muted"><?= timeAgo($o['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php else: ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-bag-x-fill fs-1 d-block mb-3 text-pink"></i>
            You haven't placed any orders yet.
            <a href="<?= BASE_URL ?>/customer/orders.php" class="btn btn-fashion btn-sm d-block w-auto mx-auto mt-3">
              <i class="bi bi-bag-plus me-2"></i>Place Your First Order
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Notifications -->
  <div class="col-lg-4">
    <div class="card-studio">
      <div class="card-header"><h5><i class="bi bi-bell-fill text-pink me-2"></i>Notifications</h5></div>
      <div class="card-body py-0">
        <?php if ($notifs): ?>
          <?php foreach ($notifs as $n): ?>
            <div class="activity-item">
              <div class="activity-dot green"></div>
              <div class="activity-text"><?= clean($n['message']) ?></div>
              <div class="activity-time"><?= timeAgo($n['created_at']) ?></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-center text-muted py-3 small"><i class="bi bi-bell-slash d-block fs-3 mb-2"></i>No notifications</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/customer_footer.php'; ?>
