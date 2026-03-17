<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireStaff();

$db = getDB();
$activePage = 'dashboard';
$pageTitle  = 'Dashboard';

// ─── Stats ────────────────────────────────────────────────
$totalCustomers  = $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$totalOrders     = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders   = $db->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$inProgressOrders= $db->query("SELECT COUNT(*) FROM orders WHERE status='in-progress'")->fetchColumn();
$completedOrders = $db->query("SELECT COUNT(*) FROM orders WHERE status='completed'")->fetchColumn();
$todaySales      = $db->query("SELECT COALESCE(SUM(amount),0) FROM sales WHERE DATE(sale_date)=CURDATE()")->fetchColumn();
$monthSales      = $db->query("SELECT COALESCE(SUM(amount),0) FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())")->fetchColumn();
$lowStockCount   = $db->query("SELECT COUNT(*) FROM fabrics WHERE quantity_yards <= reorder_level")->fetchColumn();
$totalFabrics    = $db->query("SELECT COUNT(*) FROM fabrics")->fetchColumn();

// ─── Recent Orders ────────────────────────────────────────
$recentOrders = $db->query("
    SELECT o.*, u.name AS customer_name, s.name AS style_name
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    JOIN users u ON c.user_id = u.id
    LEFT JOIN styles s ON o.style_id = s.id
    ORDER BY o.created_at DESC LIMIT 6
")->fetchAll();

// ─── Low-stock Fabrics ────────────────────────────────────
$lowStockFabrics = $db->query("
    SELECT * FROM fabrics WHERE quantity_yards <= reorder_level ORDER BY quantity_yards ASC LIMIT 5
")->fetchAll();

// ─── Monthly Sales for Chart ──────────────────────────────
$monthlySales = $db->query("
    SELECT DATE_FORMAT(sale_date,'%b') AS mon, SUM(amount) AS total
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY MONTH(sale_date) ORDER BY sale_date
")->fetchAll();
$chartLabels = json_encode(array_column($monthlySales, 'mon'));
$chartData   = json_encode(array_column($monthlySales, 'total'));

// ─── Order Status for Doughnut ───────────────────────────
$orderStats = $db->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status")->fetchAll();
$oLabels = json_encode(array_column($orderStats, 'status'));
$oData   = json_encode(array_column($orderStats, 'cnt'));

// ─── Audit Log ───────────────────────────────────────────
$auditItems = $db->query("
    SELECT al.*, u.name FROM audit_log al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 8
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ─── Stats Row ─── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="stat-card pink">
      <div class="stat-icon pink"><i class="bi bi-people-fill"></i></div>
      <div class="stat-info">
        <div class="stat-value" data-count="<?= $totalCustomers ?>"><?= $totalCustomers ?></div>
        <div class="stat-label">Total Customers</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card blue">
      <div class="stat-icon blue"><i class="bi bi-bag-fill"></i></div>
      <div class="stat-info">
        <div class="stat-value" data-count="<?= $totalOrders ?>"><?= $totalOrders ?></div>
        <div class="stat-label">Total Orders</div>
        <div class="stat-change up"><i class="bi bi-arrow-up-circle me-1"></i><?= $pendingOrders ?> pending</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card green">
      <div class="stat-icon green"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-info">
        <div class="stat-value">GH₵ <?= number_format($monthSales, 0) ?></div>
        <div class="stat-label">Month Sales</div>
        <div class="stat-change <?= $todaySales > 0 ? 'up' : '' ?>"><i class="bi bi-calendar2-check me-1"></i>Today: <?= ghcFormat($todaySales) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card <?= $lowStockCount > 0 ? 'red' : 'purple' ?>">
      <div class="stat-icon <?= $lowStockCount > 0 ? 'red' : 'purple' ?>"><i class="bi bi-box-seam-fill"></i></div>
      <div class="stat-info">
        <div class="stat-value" data-count="<?= $totalFabrics ?>"><?= $totalFabrics ?></div>
        <div class="stat-label">Fabric Types</div>
        <?php if ($lowStockCount > 0): ?>
          <div class="stat-change down"><i class="bi bi-exclamation-triangle me-1"></i><?= $lowStockCount ?> low stock!</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ─── Order Status Mini Cards ─── -->
<div class="row g-3 mb-4">
  <?php
  $statuses = ['pending'=>['warning','hourglass-split'],'approved'=>['info','check2-circle'],'in-progress'=>['primary','arrow-repeat'],'completed'=>['success','check-circle-fill'],'cancelled'=>['danger','x-circle-fill']];
  foreach ($statuses as $s => [$cls, $ico]):
    $cnt = $db->prepare("SELECT COUNT(*) FROM orders WHERE status=?");
    $cnt->execute([$s]); $c = $cnt->fetchColumn();
  ?>
  <div class="col-6 col-sm-4 col-lg-2-custom">
    <div class="card-studio text-center p-3">
      <i class="bi bi-<?= $ico ?> text-<?= $cls ?>" style="font-size:1.5rem;"></i>
      <div class="fw-700 fs-4 mt-1"><?= $c ?></div>
      <div class="text-muted" style="font-size:.75rem;"><?= ucfirst($s) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ─── Charts + Recent Orders ─── -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card-studio h-100">
      <div class="card-header">
        <h5><i class="bi bi-bar-chart-line-fill text-pink me-2"></i>Monthly Sales</h5>
        <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-sm btn-outline-fashion">View Reports</a>
      </div>
      <div class="card-body">
        <canvas id="salesChart" height="100"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card-studio h-100">
      <div class="card-header"><h5><i class="bi bi-pie-chart-fill text-pink me-2"></i>Order Status</h5></div>
      <div class="card-body d-flex flex-column align-items-center justify-content-center">
        <canvas id="orderChart" height="180"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- ─── Recent Orders + Low Stock ─── -->
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card-studio">
      <div class="card-header">
        <h5><i class="bi bi-bag-check-fill text-pink me-2"></i>Recent Orders</h5>
        <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-sm btn-outline-fashion">All Orders</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-studio mb-0">
            <thead><tr><th>#</th><th>Customer</th><th>Style</th><th>Status</th><th>Amount</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($recentOrders as $o): ?>
            <tr>
              <td><a href="<?= BASE_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>" class="text-pink fw-600">#<?= $o['id'] ?></a></td>
              <td><?= clean($o['customer_name']) ?></td>
              <td><?= clean($o['style_name'] ?? '—') ?></td>
              <td><?= statusBadge($o['status']) ?></td>
              <td class="fw-600"><?= ghcFormat($o['total_amount']) ?></td>
              <td class="text-muted"><?= timeAgo($o['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$recentOrders): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No orders yet</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card-studio mb-3">
      <div class="card-header">
        <h5><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Low Stock Alert</h5>
        <a href="<?= BASE_URL ?>/admin/inventory.php" class="btn btn-sm btn-outline-fashion">Inventory</a>
      </div>
      <div class="card-body p-0">
        <?php if ($lowStockFabrics): ?>
          <ul class="list-group list-group-flush">
          <?php foreach ($lowStockFabrics as $f): ?>
            <li class="list-group-item d-flex align-items-center justify-content-between px-3">
              <div>
                <div class="fw-600 small"><?= clean($f['name']) ?></div>
                <div class="text-muted" style="font-size:.75rem;"><?= $f['fabric_type'] ?> · <?= $f['color'] ?></div>
              </div>
              <span class="low-stock-badge"><i class="bi bi-exclamation-triangle-fill"></i><?= $f['quantity_yards'] ?> yds</span>
            </li>
          <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-center text-muted py-4 px-3">
            <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
            All fabrics are well-stocked!
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card-studio">
      <div class="card-header"><h5><i class="bi bi-journal-check text-pink me-2"></i>Recent Activity</h5></div>
      <div class="card-body py-0">
        <?php foreach ($auditItems as $a): ?>
          <div class="activity-item">
            <div class="activity-dot"></div>
            <div class="activity-text">
              <strong><?= clean($a['name'] ?? 'System') ?></strong> – <?= clean($a['action']) ?>
            </div>
            <div class="activity-time"><?= timeAgo($a['created_at']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
makeLineChart('salesChart', <?= $chartLabels ?>, <?= $chartData ?>, 'Sales (GH₵)');
makeDoughnutChart('orderChart', <?= $oLabels ?>, <?= $oData ?>);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
