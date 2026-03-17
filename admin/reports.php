<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireStaff();
$db = getDB();
$activePage = 'reports';
$pageTitle  = 'Reports';
$breadcrumb = ['Reports'=>null];

$from  = $_GET['from'] ?? date('Y-m-01');
$to    = $_GET['to']   ?? date('Y-m-d');

// CSV Export
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    if ($type === 'sales') {
        $rows = $db->prepare("SELECT s.id, o.id AS order_id, u.name AS customer, s.amount, s.payment_method, s.sale_date, ru.name AS recorded_by FROM sales s JOIN orders o ON s.order_id=o.id JOIN customers c ON o.customer_id=c.id JOIN users u ON c.user_id=u.id LEFT JOIN users ru ON s.recorded_by=ru.id WHERE s.sale_date BETWEEN ? AND ? ORDER BY s.sale_date");
        $rows->execute([$from,$to]);
        $data = $rows->fetchAll();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sales_report_'.$from.'_to_'.$to.'.csv"');
        $out = fopen('php://output','w');
        fputcsv($out,['Sale ID','Order ID','Customer','Amount (GH₵)','Payment Method','Date','Recorded By']);
        foreach ($data as $r) fputcsv($out,[$r['id'],$r['order_id'],$r['customer'],$r['amount'],$r['payment_method'],$r['sale_date'],$r['recorded_by']]);
        fclose($out); exit;
    }
    if ($type === 'inventory') {
        $rows = $db->query("SELECT id,name,fabric_type,color,quantity_yards,reorder_level,cost_per_yard,supplier FROM fabrics ORDER BY name")->fetchAll();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="inventory_report_'.date('Y-m-d').'.csv"');
        $out = fopen('php://output','w');
        fputcsv($out,['ID','Name','Type','Color','Qty (yds)','Reorder Level','Cost/yd','Supplier','Status']);
        foreach ($rows as $r) { $status = $r['quantity_yards'] <= $r['reorder_level'] ? 'Low Stock' : 'In Stock'; fputcsv($out,[$r['id'],$r['name'],$r['fabric_type'],$r['color'],$r['quantity_yards'],$r['reorder_level'],$r['cost_per_yard'],$r['supplier'],$status]); }
        fclose($out); exit;
    }
    if ($type === 'orders') {
        $rows = $db->prepare("SELECT o.id, u.name AS customer, s.name AS style, f.name AS fabric, o.quantity, o.status, o.total_amount, o.created_at FROM orders o JOIN customers c ON o.customer_id=c.id JOIN users u ON c.user_id=u.id LEFT JOIN styles s ON o.style_id=s.id LEFT JOIN fabrics f ON o.fabric_id=f.id WHERE o.created_at::date BETWEEN ? AND ? ORDER BY o.created_at");
        $rows->execute([$from,$to]);
        $data = $rows->fetchAll();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="orders_report_'.$from.'_to_'.$to.'.csv"');
        $out = fopen('php://output','w');
        fputcsv($out,['Order ID','Customer','Style','Fabric','Qty','Status','Amount (GH₵)','Date']);
        foreach ($data as $r) fputcsv($out,[$r['id'],$r['customer'],$r['style'],$r['fabric'],$r['quantity'],$r['status'],$r['total_amount'],$r['created_at']]);
        fclose($out); exit;
    }
}

// Stats for display
$totalSales = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM sales WHERE sale_date BETWEEN ? AND ?"); $totalSales->execute([$from,$to]);
$totalOrders= $db->prepare("SELECT COUNT(*) FROM orders WHERE created_at::date BETWEEN ? AND ?");       $totalOrders->execute([$from,$to]);
$newCusts   = $db->prepare("SELECT COUNT(*) FROM users WHERE role='customer' AND created_at::date BETWEEN ? AND ?"); $newCusts->execute([$from,$to]);
$completedO = $db->prepare("SELECT COUNT(*) FROM orders WHERE status='completed' AND updated_at::date BETWEEN ? AND ?"); $completedO->execute([$from,$to]);

$bySyle = $db->query("SELECT s.name, COUNT(o.id) AS cnt FROM orders o LEFT JOIN styles s ON o.style_id=s.id GROUP BY o.style_id ORDER BY cnt DESC LIMIT 5")->fetchAll();
$byPay  = $db->query("SELECT payment_method, SUM(amount) AS total FROM sales GROUP BY payment_method")->fetchAll();
$payLabels = json_encode(array_column($byPay,'payment_method'));
$payData   = json_encode(array_column($byPay,'total'));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div><h3><i class="bi bi-file-earmark-bar-graph-fill text-pink me-2"></i>Reports</h3><div class="subtitle">Generate and export business reports</div></div>
</div>

<!-- Date Range -->
<div class="card-studio mb-3"><div class="card-body py-2">
  <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
    <label class="fw-600 small text-muted">Report Period:</label>
    <input type="date" class="form-control form-control-sm" name="from" value="<?= $from ?>">
    <span class="text-muted">to</span>
    <input type="date" class="form-control form-control-sm" name="to" value="<?= $to ?>">
    <button class="btn btn-fashion btn-sm">Update</button>
  </form>
</div></div>

<!-- Summary -->
<div class="row g-3 mb-4">
  <div class="col-6 col-sm-3">
    <div class="stat-card green"><div class="stat-icon green"><i class="bi bi-cash-coin"></i></div>
    <div class="stat-info"><div class="stat-value"><?= ghcFormat((float)$totalSales->fetchColumn()) ?></div><div class="stat-label">Total Revenue</div></div></div>
  </div>
  <div class="col-6 col-sm-3">
    <div class="stat-card blue"><div class="stat-icon blue"><i class="bi bi-bag"></i></div>
    <div class="stat-info"><div class="stat-value"><?= $totalOrders->fetchColumn() ?></div><div class="stat-label">Orders Placed</div></div></div>
  </div>
  <div class="col-6 col-sm-3">
    <div class="stat-card pink"><div class="stat-icon pink"><i class="bi bi-people"></i></div>
    <div class="stat-info"><div class="stat-value"><?= $newCusts->fetchColumn() ?></div><div class="stat-label">New Customers</div></div></div>
  </div>
  <div class="col-6 col-sm-3">
    <div class="stat-card gold"><div class="stat-icon gold"><i class="bi bi-star"></i></div>
    <div class="stat-info"><div class="stat-value"><?= $completedO->fetchColumn() ?></div><div class="stat-label">Completed Orders</div></div></div>
  </div>
</div>

<div class="row g-3">
  <!-- Export Cards -->
  <div class="col-lg-5">
    <div class="card-studio mb-3">
      <div class="card-header"><h5><i class="bi bi-download text-pink me-2"></i>Export Reports (CSV)</h5></div>
      <div class="card-body d-flex flex-column gap-3">
        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-xl">
          <div><i class="bi bi-cash-stack text-success fs-4 me-2"></i><strong>Sales Report</strong><div class="text-muted small">Date-range sales data</div></div>
          <a href="?export=sales&from=<?= $from ?>&to=<?= $to ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-file-excel me-1"></i>Download</a>
        </div>
        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-xl">
          <div><i class="bi bi-box-seam text-primary fs-4 me-2"></i><strong>Inventory Report</strong><div class="text-muted small">Current stock levels</div></div>
          <a href="?export=inventory" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-excel me-1"></i>Download</a>
        </div>
        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-xl">
          <div><i class="bi bi-bag text-pink fs-4 me-2"></i><strong>Orders Report</strong><div class="text-muted small">Date-range orders</div></div>
          <a href="?export=orders&from=<?= $from ?>&to=<?= $to ?>" class="btn btn-outline-fashion btn-sm"><i class="bi bi-file-excel me-1"></i>Download</a>
        </div>
      </div>
    </div>

    <!-- Top Styles -->
    <div class="card-studio">
      <div class="card-header"><h5><i class="bi bi-trophy-fill text-gold me-2"></i>Top Styles</h5></div>
      <div class="card-body p-0">
        <?php foreach ($bySyle as $i => $s): ?>
          <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-pink-soft text-pink border"><?= $i+1 ?></span>
              <span class="small fw-600"><?= clean($s['name'] ?? 'Unspecified') ?></span>
            </div>
            <span class="badge bg-light text-dark border"><?= $s['cnt'] ?> orders</span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Payment Breakdown -->
  <div class="col-lg-7">
    <div class="card-studio">
      <div class="card-header"><h5><i class="bi bi-pie-chart-fill text-pink me-2"></i>Revenue by Payment Method</h5></div>
      <div class="card-body d-flex justify-content-center align-items-center" style="min-height:300px;">
        <canvas id="payChart" style="max-height:300px;"></canvas>
      </div>
    </div>
  </div>
</div>

<script>makeDoughnutChart('payChart', <?= $payLabels ?>, <?= $payData ?>);</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
