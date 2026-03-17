<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireStaff();
$db = getDB();
$activePage = 'sales';
$pageTitle  = 'Sales Records';
$breadcrumb = ['Sales'=>null];

$from  = $_GET['from'] ?? date('Y-m-01');
$to    = $_GET['to']   ?? date('Y-m-d');
$page  = max(1,(int)($_GET['page']??1));
$perPage = 15;

$total = $db->prepare("SELECT COUNT(*) FROM sales WHERE sale_date BETWEEN ? AND ?");
$total->execute([$from, $to]);
$totalCount = (int)$total->fetchColumn();
$pg = paginate($totalCount, $perPage, $page);

$totalRevenue = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM sales WHERE sale_date BETWEEN ? AND ?");
$totalRevenue->execute([$from,$to]); $totalRevenue = (float)$totalRevenue->fetchColumn();

$stmt = $db->prepare("
    SELECT s.*, o.id AS order_id, u.name AS customer_name, ru.name AS recorded_name
    FROM sales s
    JOIN orders o ON s.order_id=o.id
    JOIN customers c ON o.customer_id=c.id
    JOIN users u ON c.user_id=u.id
    LEFT JOIN users ru ON s.recorded_by=ru.id
    WHERE s.sale_date BETWEEN ? AND ?
    ORDER BY s.created_at DESC
    LIMIT {$pg['perPage']} OFFSET {$pg['offset']}
");
$stmt->execute([$from,$to]);
$sales = $stmt->fetchAll();

// Chart: daily sales for the period
$dailySales = $db->prepare("SELECT sale_date, SUM(amount) AS total FROM sales WHERE sale_date BETWEEN ? AND ? GROUP BY sale_date ORDER BY sale_date");
$dailySales->execute([$from,$to]);
$dailyRows = $dailySales->fetchAll();
$chartDates = json_encode(array_column($dailyRows,'sale_date'));
$chartAmts  = json_encode(array_column($dailyRows,'total'));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h3><i class="bi bi-cash-stack text-pink me-2"></i>Sales Records</h3>
    <div class="subtitle">Revenue from <?= $from ?> to <?= $to ?></div>
  </div>
  <a href="<?= BASE_URL ?>/admin/sale_form.php" class="btn btn-fashion"><i class="bi bi-plus-lg me-2"></i>Record Sale</a>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-3">
  <div class="col-sm-4">
    <div class="stat-card green">
      <div class="stat-icon green"><i class="bi bi-cash-coin"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= ghcFormat($totalRevenue) ?></div>
        <div class="stat-label">Period Revenue</div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card blue">
      <div class="stat-icon blue"><i class="bi bi-receipt"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $totalCount ?></div>
        <div class="stat-label">Transactions</div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card gold">
      <div class="stat-icon gold"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $totalCount > 0 ? ghcFormat($totalRevenue / $totalCount) : 'GH₵ 0' ?></div>
        <div class="stat-label">Avg per Sale</div>
      </div>
    </div>
  </div>
</div>

<!-- Chart -->
<div class="card-studio mb-3">
  <div class="card-header">
    <h5><i class="bi bi-bar-chart-fill text-pink me-2"></i>Daily Sales (<?= $from ?> – <?= $to ?>)</h5>
    <form method="GET" class="d-flex gap-2 align-items-center">
      <input type="date" class="form-control form-control-sm" name="from" value="<?= $from ?>">
      <span class="text-muted">to</span>
      <input type="date" class="form-control form-control-sm" name="to" value="<?= $to ?>">
      <button class="btn btn-fashion btn-sm">Filter</button>
    </form>
  </div>
  <div class="card-body"><canvas id="salesChart" height="80"></canvas></div>
</div>

<!-- Table -->
<div class="card-studio">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-studio mb-0">
        <thead><tr><th>#</th><th>Order #</th><th>Customer</th><th>Amount</th><th>Method</th><th>Date</th><th>Recorded by</th></tr></thead>
        <tbody>
        <?php foreach ($sales as $s): ?>
          <tr>
            <td><?= $s['id'] ?></td>
            <td><a href="<?= BASE_URL ?>/admin/order_detail.php?id=<?= $s['order_id'] ?>" class="text-pink fw-600">#<?= $s['order_id'] ?></a></td>
            <td><?= clean($s['customer_name']) ?></td>
            <td class="fw-600 text-success"><?= ghcFormat($s['amount']) ?></td>
            <td><span class="badge bg-light text-dark border"><?= str_replace('_',' ',ucfirst($s['payment_method'])) ?></span></td>
            <td><?= $s['sale_date'] ?></td>
            <td class="small text-muted"><?= clean($s['recorded_name'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$sales): ?><tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-cash fs-1 d-block mb-2"></i>No sales in this period.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>makeBarChart('salesChart', <?= $chartDates ?>, <?= $chartAmts ?>, 'Sales (GH₵)');</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
