<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireStaff();
$db = getDB();
$activePage = 'inventory';
$pageTitle  = 'Inventory Management';
$breadcrumb = ['Inventory'=>null];

// Handle delete
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $db->prepare("DELETE FROM fabrics WHERE id=?")->execute([$did]);
    auditLog('delete_fabric',"Deleted fabric #$did");
    setFlash('success','Fabric removed from inventory.');
    redirect(BASE_URL.'/admin/inventory.php');
}

$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';
$page   = max(1,(int)($_GET['page']??1));
$perPage = 20;
$where  = 'WHERE 1=1';
$params = [];
if ($search) { $where .= ' AND (name LIKE ? OR fabric_type LIKE ? OR supplier LIKE ?)'; $params = array_merge($params,["%$search%","%$search%","%$search%"]); }
if ($filter === 'low') { $where .= ' AND quantity_yards <= reorder_level'; }
$total = $db->prepare("SELECT COUNT(*) FROM fabrics $where"); $total->execute($params);
$totalCount = (int)$total->fetchColumn();
$pg = paginate($totalCount, $perPage, $page);
$stmt = $db->prepare("SELECT * FROM fabrics $where ORDER BY quantity_yards ASC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$fabrics = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h3><i class="bi bi-box-seam-fill text-pink me-2"></i>Inventory Management</h3>
    <div class="subtitle"><?= $totalCount ?> fabric/supply items</div>
  </div>
  <a href="<?= BASE_URL ?>/admin/inventory_form.php" class="btn btn-fashion"><i class="bi bi-plus-lg me-2"></i>Add Fabric</a>
</div>

<div class="card-studio mb-3"><div class="card-body py-2">
  <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
    <div class="search-box flex-grow-1" style="max-width:300px;"><i class="bi bi-search"></i>
      <input type="text" class="form-control" name="q" value="<?= clean($search) ?>" placeholder="Search fabric, type, supplier…">
    </div>
    <div class="d-flex gap-2">
      <a href="?filter=low<?= $search?"&q=".urlencode($search):'' ?>" class="btn btn-sm <?= $filter==='low'?'btn-danger':'btn-outline-danger' ?>"><i class="bi bi-exclamation-triangle me-1"></i>Low Stock</a>
      <a href="?" class="btn btn-sm btn-outline-secondary">All</a>
    </div>
    <button class="btn btn-fashion btn-sm">Search</button>
  </form>
</div></div>

<div class="card-studio">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-studio mb-0">
        <thead><tr><th>#</th><th>Name</th><th>Type</th><th>Color</th><th>Qty (yards)</th><th>Reorder Level</th><th>Cost/yd</th><th>Supplier</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($fabrics as $f):
          $isLow = $f['quantity_yards'] <= $f['reorder_level'];
        ?>
          <tr class="<?= $isLow?'table-danger-soft':'' ?>">
            <td><?= $f['id'] ?></td>
            <td class="fw-600"><?= clean($f['name']) ?></td>
            <td><?= clean($f['fabric_type']) ?></td>
            <td><span class="badge bg-light text-dark border"><?= clean($f['color']) ?></span></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?= number_format($f['quantity_yards'],1) ?>
                <div class="progress flex-grow-1" style="height:6px;max-width:80px;">
                  <?php $pct = min(100, ($f['quantity_yards'] / max($f['reorder_level']*3,1))*100); ?>
                  <div class="progress-bar <?= $isLow?'bg-danger':'bg-success' ?>" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
            </td>
            <td><?= $f['reorder_level'] ?></td>
            <td><?= ghcFormat($f['cost_per_yard']) ?></td>
            <td class="small text-muted"><?= clean($f['supplier'] ?? '—') ?></td>
            <td><?= $isLow ? '<span class="low-stock-badge"><i class="bi bi-exclamation-triangle-fill"></i>Low Stock</span>' : '<span class="badge bg-success">In Stock</span>' ?></td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= BASE_URL ?>/admin/inventory_form.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-fashion"><i class="bi bi-pencil"></i></a>
                <a href="?delete=<?= $f['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this fabric? This cannot be undone."><i class="bi bi-trash"></i></a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$fabrics): ?>
          <tr><td colspan="10" class="text-center text-muted py-5"><i class="bi bi-box-seam fs-1 d-block mb-2"></i>No fabrics found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-body border-top d-flex justify-content-between">
    <small class="text-muted">Showing <?= count($fabrics) ?> of <?= $totalCount ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for($i=1;$i<=$pg['pages'];$i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?q=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
