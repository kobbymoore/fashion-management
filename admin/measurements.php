<?php
require_once __DIR__ . '/../includes/auth_guard.php';
// Include shared includes
require_once __DIR__ . '/../includes/functions.php';

// Also show admin measurements page
$db = getDB();
$activePage = 'measurements';
$pageTitle  = 'All Measurements';
$breadcrumb = ['Measurements'=>null];
requireStaff();

$search = trim($_GET['q'] ?? '');
$page   = max(1,(int)($_GET['page']??1));
$perPage = 15;
$where = $search ? "WHERE u.name LIKE ? OR u.email LIKE ?" : "";
$params = $search ? ["%$search%","%$search%"] : [];

$total = $db->prepare("SELECT COUNT(*) FROM measurements m JOIN customers c ON m.customer_id=c.id JOIN users u ON c.user_id=u.id $where");
$total->execute($params);
$totalCount = (int)$total->fetchColumn();
$pg = paginate($totalCount, $perPage, $page);

$stmt = $db->prepare("
    SELECT m.*, u.name AS customer_name, ru.name AS recorded_name
    FROM measurements m
    JOIN customers c ON m.customer_id=c.id
    JOIN users u ON c.user_id=u.id
    LEFT JOIN users ru ON m.recorded_by=ru.id
    $where ORDER BY m.created_at DESC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$measures = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div><h3><i class="bi bi-rulers text-pink me-2"></i>Measurement Records</h3><div class="subtitle"><?= $totalCount ?> records</div></div>
</div>

<div class="card-studio mb-3"><div class="card-body py-2">
  <form method="GET" class="d-flex gap-2">
    <div class="search-box"><i class="bi bi-search"></i>
      <input type="text" class="form-control" name="q" value="<?= clean($search) ?>" placeholder="Search customer name…">
    </div>
    <button class="btn btn-fashion btn-sm">Search</button>
    <?php if ($search): ?><a href="?" class="btn btn-outline-secondary btn-sm">Clear</a><?php endif; ?>
  </form>
</div></div>

<div class="card-studio">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-studio mb-0">
        <thead>
          <tr><th>Customer</th><th>Bust</th><th>Waist</th><th>Hips</th><th>Height</th><th>Shoulder</th><th>Recorded By</th><th>Date</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($measures as $m): ?>
          <tr>
            <td class="fw-600"><?= clean($m['customer_name']) ?></td>
            <td><?= $m['bust']? $m['bust'].' in' : '—' ?></td>
            <td><?= $m['waist']? $m['waist'].' in' : '—' ?></td>
            <td><?= $m['hips']? $m['hips'].' in' : '—' ?></td>
            <td><?= $m['height']? $m['height'].' in' : '—' ?></td>
            <td><?= $m['shoulder']? $m['shoulder'].' in' : '—' ?></td>
            <td class="small text-muted"><?= clean($m['recorded_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= date('M j, Y', strtotime($m['created_at'])) ?></td>
            <td><a href="<?= BASE_URL ?>/admin/measurement_form.php?id=<?= $m['id'] ?>&customer_id=<?= $m['customer_id'] ?>" class="btn btn-sm btn-outline-fashion"><i class="bi bi-pencil"></i></a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$measures): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No measurements recorded yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
